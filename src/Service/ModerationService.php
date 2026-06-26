<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Security\WriteGate;

/**
 * Inline admin moderation (Phase 1 is admin/site-scoped): pin/unpin,
 * lock/unlock, and soft-delete any post. Every action writes an append-only
 * moderation_log row. Per-board scoped moderators are Phase 2.
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
    ) {
    }

    /** @return array{thread:array<string,mixed>, pinned:bool} */
    public function togglePin(User $admin, int $threadId): array
    {
        $thread = $this->requireThread($admin, $threadId);
        $pinned = (int) $thread['is_pinned'] === 0;

        $this->db->transaction(function () use ($admin, $threadId, $thread, $pinned): void {
            $this->threads->setPinned($threadId, $pinned);
            $this->log->log([
                'actor_id' => $admin->id(),
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
    public function toggleLock(User $admin, int $threadId): array
    {
        $thread = $this->requireThread($admin, $threadId);
        $locked = (int) $thread['is_locked'] === 0;

        $this->db->transaction(function () use ($admin, $threadId, $thread, $locked): void {
            $this->threads->setLocked($threadId, $locked);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $locked ? 'lock' : 'unlock',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['is_locked' => (int) $thread['is_locked']],
                'after' => ['is_locked' => $locked ? 1 : 0],
            ]);
        });

        return ['thread' => $thread, 'locked' => $locked];
    }

    /** Admin soft-delete of any post; reason required. @return array<string,mixed> the post */
    public function deletePost(User $admin, int $postId, string $reason): array
    {
        $this->assertAdmin($admin);

        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException(['reason' => 'A reason is required to delete a post.']);
        }

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }

        $this->db->transaction(function () use ($admin, $post, $postId, $reason): void {
            if ($this->posts->softDelete($postId, $admin->id()) === 0) {
                return; // already deleted concurrently — nothing to adjust or audit
            }
            $this->posting->applyDeletionCounters($post);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'delete_post',
                'target_type' => 'post',
                'target_id' => $postId,
                'reason' => $reason,
                'before' => ['is_deleted' => 0],
                'after' => ['is_deleted' => 1, 'deleted_by' => $admin->id()],
            ]);
        });

        return $post;
    }

    /** @return array<string,mixed> */
    private function requireThread(User $admin, int $threadId): array
    {
        $this->assertAdmin($admin);
        $thread = $this->threads->find($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Moderation requires an administrator.');
        }
        $this->writeGate->assertCanWrite($admin);
    }
}
