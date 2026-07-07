<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\AuthorityGate;
use App\Security\Cap;
use App\Security\WebhookEvents;
use App\Security\WriteGate;

/**
 * Content moderation (P2-08), capability-based and board-scoped: pin/unpin,
 * lock/unlock, soft-delete/restore a post, and move a thread. A user may act on
 * a board iff they are an admin OR an assigned board moderator of it; a move
 * requires the capability on BOTH source and destination. Every action writes an
 * append-only moderation_log row with before/after snapshots, and counter
 * changes commit atomically with the action.
 */
final class ModerationService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private ModerationLogRepository $log,
        private PostingService $posting,
        private WriteGate $writeGate,
        private BoardModeratorRepository $boardMods,
        private BoardRepository $boards,
        private UserRepository $users,
        private ?FirstPartyHookRegistry $hooks = null,
        private ?AuthorityGate $authority = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    /** Non-throwing capability check (admin anywhere, or assigned board moderator). */
    public function canModerate(User $user, int $boardId, string $capability = Cap::POST_DELETE_ANY): bool
    {
        return $this->gate()->allows(
            fn (): bool => $this->writeGate->canWrite($user)
                && ($user->isAdmin() || $this->boardMods->isModerator($boardId, $user->id())),
            $user,
            $capability,
            ['board_id' => $boardId],
            'ModerationService::canModerate',
        );
    }

    /** @return array{thread:array<string,mixed>, pinned:bool} */
    public function togglePin(User $mod, int $threadId): array
    {
        $thread = $this->requireModeratableThread($mod, $threadId, Cap::THREAD_PIN);
        $pinned = (int) $thread['is_pinned'] === 0;

        $this->db->transaction(function () use ($mod, $threadId, $thread, $pinned): void {
            $this->threads->setPinned($threadId, $pinned);
            $this->log->log([
                'actor_id' => $mod->id(),
                'action' => $pinned ? 'pin' : 'unpin',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['is_pinned' => (int) $thread['is_pinned']],
                'after' => ['is_pinned' => $pinned ? 1 : 0],
            ]);
        });

        return ['thread' => $thread, 'pinned' => $pinned];
    }

    /** @return array{thread:array<string,mixed>, locked:bool} */
    public function toggleLock(User $mod, int $threadId): array
    {
        $thread = $this->requireModeratableThread($mod, $threadId, Cap::THREAD_LOCK);
        $locked = (int) $thread['is_locked'] === 0;

        $this->db->transaction(function () use ($mod, $threadId, $thread, $locked): void {
            $this->threads->setLocked($threadId, $locked);
            $this->log->log([
                'actor_id' => $mod->id(),
                'action' => $locked ? 'lock' : 'unlock',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['is_locked' => (int) $thread['is_locked']],
                'after' => ['is_locked' => $locked ? 1 : 0],
            ]);
        });

        return ['thread' => $thread, 'locked' => $locked];
    }

    /** Soft-delete any post within scope; reason required. @return array<string,mixed> the post */
    public function deletePost(User $mod, int $postId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException(['reason' => 'A reason is required to delete a post.']);
        }

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        $this->assertCanModerate($mod, (int) $post['board_id'], Cap::POST_DELETE_ANY);
        $this->assertNotArchived((int) $post['board_id']);

        // Removing the opening post removes the whole topic rather than orphaning
        // a headless, still-listed thread. Moderators legitimately remove abusive
        // or spam topics that already have replies, so — unlike the opener's own
        // retraction — there is no sole-participant guard here. Purge + audit are
        // one transaction; the reason is required (validated above) and the soft
        // delete is reversible through repair tooling.
        if ((int) $post['is_op'] === 1) {
            $this->db->transaction(function () use ($mod, $post, $reason): void {
                $this->posting->purgeThread((int) $post['thread_id'], (int) $post['board_id'], $mod->id());
                $this->log->log([
                    'actor_id' => $mod->id(),
                    'action' => 'delete_thread',
                    'target_type' => 'thread',
                    'target_id' => (int) $post['thread_id'],
                    'reason' => $reason,
                    'before' => ['is_deleted' => 0],
                    'after' => ['is_deleted' => 1, 'deleted_by' => $mod->id()],
                ]);
            });
            return $post;
        }

        $deleted = $this->db->transaction(function () use ($mod, $post, $postId, $reason): bool {
            if ($this->posts->softDelete($postId, $mod->id()) === 0) {
                return false; // already deleted concurrently — nothing to adjust or audit
            }
            $this->posting->applyDeletionCounters($post);
            $this->log->log([
                'actor_id' => $mod->id(),
                'action' => 'delete_post',
                'target_type' => 'post',
                'target_id' => $postId,
                'reason' => $reason,
                'before' => ['is_deleted' => 0],
                'after' => ['is_deleted' => 1, 'deleted_by' => $mod->id()],
            ]);
            return true;
        });

        if ($deleted) {
            $deletedPost = $this->posts->findWithContext($postId);
            if ($deletedPost !== null) {
                $this->emitPostDeleted($deletedPost);
            }
        }

        return $post;
    }

    /** Restore a soft-deleted post within scope. @return array<string,mixed> the post */
    public function restorePost(User $mod, int $postId, string $reason = ''): array
    {
        $post = $this->posts->findWithContext($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }
        $this->assertCanModerate($mod, (int) $post['board_id'], Cap::POST_RESTORE);
        $this->assertNotArchived((int) $post['board_id']);
        if ((int) $post['is_deleted'] === 0) {
            return $post; // already visible
        }

        $this->db->transaction(function () use ($mod, $post, $postId, $reason): void {
            if ($this->posts->restore($postId) === 0) {
                return;
            }
            $this->posting->applyRestorationCounters($post);
            $this->log->log([
                'actor_id' => $mod->id(),
                'action' => 'restore_post',
                'target_type' => 'post',
                'target_id' => $postId,
                'reason' => $reason !== '' ? $reason : null,
                'before' => ['is_deleted' => 1],
                'after' => ['is_deleted' => 0],
            ]);
        });

        return $post;
    }

    /**
     * Move a thread to another board. Requires moderation capability on BOTH
     * source and destination; updates both boards' counters atomically and
     * recomputes their last-post caches (no stale links/leaks).
     *
     * @return array{thread:array<string,mixed>, moved:bool}
     */
    public function moveThread(User $mod, int $threadId, int $destBoardId): array
    {
        $thread = $this->threads->find($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $srcBoardId = (int) $thread['board_id'];
        if ($srcBoardId === $destBoardId) {
            return ['thread' => $thread, 'moved' => false];
        }
        $dest = $this->boards->find($destBoardId);
        if ($dest === null) {
            throw new ValidationException(['board_id' => 'Choose a destination board.']);
        }
        $this->assertCanModerate($mod, $srcBoardId, Cap::THREAD_MOVE);
        $this->assertCanModerate($mod, $destBoardId, Cap::THREAD_MOVE);
        // A move mutates BOTH boards (counters + last-post caches), so an archived
        // board on either end is read-only: moving content out of a frozen source,
        // or into a frozen destination, are both writes that stay closed.
        $this->assertNotArchived($srcBoardId);
        $this->assertNotArchived($destBoardId);

        $this->db->transaction(function () use ($mod, $threadId, $thread, $srcBoardId, $destBoardId): void {
            $postCount = (int) $this->db->fetchValue(
                'SELECT COUNT(*) FROM posts WHERE thread_id = ? AND is_deleted = 0',
                [$threadId],
            );
            $this->threads->setBoard($threadId, $destBoardId);
            $this->db->run(
                'UPDATE boards SET thread_count = GREATEST(0, CAST(thread_count AS SIGNED) - 1),
                    post_count = GREATEST(0, CAST(post_count AS SIGNED) - ?) WHERE id = ?',
                [$postCount, $srcBoardId],
            );
            $this->db->run(
                'UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + ? WHERE id = ?',
                [$postCount, $destBoardId],
            );
            $this->boards->recomputeLastPost($srcBoardId);
            $this->boards->recomputeLastPost($destBoardId);
            $this->log->log([
                'actor_id' => $mod->id(),
                'action' => 'move_thread',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['board_id' => $srcBoardId],
                'after' => ['board_id' => $destBoardId],
            ]);
        });

        return ['thread' => $thread, 'moved' => true];
    }

    /** @return array<string,mixed> */
    private function requireModeratableThread(User $mod, int $threadId, string $capability): array
    {
        $thread = $this->threads->find($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $this->assertCanModerate($mod, (int) $thread['board_id'], $capability);
        $this->assertNotArchived((int) $thread['board_id']);
        return $thread;
    }

    /**
     * Reveal the real author of an anonymous post to a scoped moderator. The
     * reveal IS the audited action (moderation_log 'reveal_anon'); the public
     * render stays masked for everyone, including the revealing moderator's page
     * — the handle is returned only for a one-off flash to the acting mod.
     *
     * @return array{username:string, user_id:int, thread_id:int, thread_slug:string, post_id:int}
     */
    public function revealAuthor(User $mod, int $postId): array
    {
        $post = $this->posts->findWithContext($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }
        $this->assertCanModerate($mod, (int) $post['board_id'], Cap::POST_REVEAL_AUTHOR);
        if ((int) ($post['is_anonymous'] ?? 0) !== 1) {
            throw new ForbiddenException('That post was not posted anonymously.');
        }

        $authorId = (int) $post['user_id'];
        $author = $this->users->find($authorId);
        $username = is_array($author) && isset($author['username']) ? (string) $author['username'] : ('#' . $authorId);

        $this->log->log([
            'actor_id' => $mod->id(),
            'action' => 'reveal_anon',
            'target_type' => 'post',
            'target_id' => $postId,
            'after' => ['user_id' => $authorId, 'username' => $username],
        ]);

        return [
            'username' => $username,
            'user_id' => $authorId,
            'thread_id' => (int) $post['thread_id'],
            'thread_slug' => (string) ($post['thread_slug'] ?? ''),
            'post_id' => $postId,
        ];
    }

    private function assertCanModerate(User $mod, int $boardId, string $capability): void
    {
        $this->writeGate->assertCanWrite($mod);
        if (!$this->canModerate($mod, $boardId, $capability)) {
            throw new ForbiddenException('You do not moderate this board.');
        }
    }

    /**
     * An archived board is frozen for everyone — moderators and admins included.
     * They unarchive first to clean up; there is no role carve-out (ADMIN §4.4).
     */
    private function assertNotArchived(int $boardId): void
    {
        $board = $this->boards->find($boardId);
        if ($board !== null && (int) ($board['is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
    }

    /** @param array<string,mixed> $post */
    private function emitPostDeleted(array $post): void
    {
        if ((string) ($post['board_visibility'] ?? 'public') !== 'public' || (int) ($post['is_pending'] ?? 0) === 1) {
            return;
        }
        $deletedAt = (string) ($post['deleted_at'] ?? '');
        if ($deletedAt === '') {
            return;
        }
        $postId = (int) $post['id'];
        $this->hooks?->emit('post.deleted', WebhookEvents::maskAnonymousAuthor([
            'post_id' => $postId,
            'thread_id' => (int) $post['thread_id'],
            'board_id' => (int) $post['board_id'],
            'author_id' => (int) $post['user_id'],
            'deleted_by_id' => (int) ($post['deleted_by'] ?? 0),
            'is_op' => (int) ($post['is_op'] ?? 0) === 1,
        ], (int) ($post['is_anonymous'] ?? 0) === 1, ['author_id'], ['deleted_by_id']), 'post:' . $postId . ':deleted:' . $deletedAt);
    }
}
