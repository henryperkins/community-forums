<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Support\Markdown;
use App\Support\MentionParser;
use App\Support\Str;

/**
 * Create/edit/delete posts and threads. Every write runs through the account
 * write gate, sanitises Markdown to body_html, and maintains the denormalised
 * counters (board.thread_count/post_count, thread.reply_count/last_post_*,
 * users.post_count) inside one transaction so they can never drift.
 */
final class PostingService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BoardRepository $boards,
        private UserRepository $users,
        private Markdown $markdown,
        private WriteGate $writeGate,
        private BoardPolicy $policy,
        private Config $config,
        private ?NotificationService $notifications = null,
    ) {
    }

    /**
     * @param array<string,mixed> $input board_id, title, body
     * @return array{thread_id:int, slug:string}
     */
    public function createThread(User $user, array $input): array
    {
        $this->writeGate->assertCanWrite($user);

        $boardId = (int) ($input['board_id'] ?? 0);
        $board = $this->boards->find($boardId);
        if ($board === null) {
            throw new ValidationException(['board_id' => 'Choose a board to post in.'], $input);
        }
        if (!$this->policy->canPost($board, $user, $this->isBoardMember($boardId, $user->id()))) {
            throw new ForbiddenException('You cannot post in this board.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        $body = (string) ($input['body'] ?? '');
        $this->validate($title, $body, $input, requireTitle: true);

        return $this->db->transaction(function () use ($user, $board, $boardId, $title, $body): array {
            $now = gmdate('Y-m-d H:i:s');
            $slug = Str::slug($title, 180);
            $threadId = $this->threads->create($boardId, $user->id(), $title, $slug);

            $postId = $this->posts->create([
                'thread_id' => $threadId,
                'user_id' => $user->id(),
                'body' => $body,
                'body_html' => $this->markdown->render($body),
                'is_op' => true,
                'ip' => $input['ip'] ?? null,
            ]);

            $this->threads->updateLastPost($threadId, $postId, $user->id(), $now);
            $this->boards->onThreadCreated($boardId, $threadId, $now);
            $this->users->incrementPostCount($user->id(), 1);

            if ($this->notifications !== null) {
                $threadCtx = ['id' => $threadId, 'board_id' => $boardId, 'board_visibility' => $board['visibility'] ?? 'public'];
                // The author follows their own thread so they hear about replies;
                // they are still excluded from this post's own fan-out.
                $this->notifications->autoSubscribeAuthor($user->id(), $threadId);
                $this->notifications->fanOutNewPost($user->id(), $threadCtx, $postId, true, $body);
            }

            return ['thread_id' => $threadId, 'slug' => $slug];
        });
    }

    /**
     * @param array<string,mixed> $input body
     * @return int new post id
     */
    public function reply(User $user, int $threadId, array $input): int
    {
        $this->writeGate->assertCanWrite($user);

        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $board = [
            'visibility' => $thread['board_visibility'],
            'post_min_role' => $thread['board_post_min_role'] ?? 'user',
        ];
        if (!$this->policy->canPost($board, $user, $this->isBoardMember((int) $thread['board_id'], $user->id()))) {
            throw new ForbiddenException('You cannot post in this board.');
        }
        if ((int) $thread['is_locked'] === 1) {
            throw new ForbiddenException('This thread is locked and is not accepting replies.');
        }

        $body = (string) ($input['body'] ?? '');
        $this->validate(null, $body, $input, requireTitle: false);

        return $this->db->transaction(function () use ($user, $thread, $threadId, $body): int {
            $now = gmdate('Y-m-d H:i:s');
            $postId = $this->posts->create([
                'thread_id' => $threadId,
                'user_id' => $user->id(),
                'body' => $body,
                'body_html' => $this->markdown->render($body),
                'is_op' => false,
                'ip' => $input['ip'] ?? null,
            ]);

            $this->threads->incrementReplyCount($threadId, 1);
            $this->threads->updateLastPost($threadId, $postId, $user->id(), $now);
            $this->boards->onReplyCreated((int) $thread['board_id'], $now);
            $this->users->incrementPostCount($user->id(), 1);

            if ($this->notifications !== null) {
                $threadCtx = [
                    'id' => $threadId,
                    'board_id' => (int) $thread['board_id'],
                    'board_visibility' => $thread['board_visibility'] ?? 'public',
                ];
                $this->notifications->fanOutNewPost($user->id(), $threadCtx, $postId, false, $body);
            }

            return $postId;
        });
    }

    /** @param array<string,mixed> $input body */
    public function editOwnPost(User $user, int $postId, array $input): array
    {
        $this->writeGate->assertCanWrite($user);

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        if (!$user->owns((int) $post['user_id'])) {
            throw new ForbiddenException('You can only edit your own posts.');
        }

        $body = (string) ($input['body'] ?? '');
        $this->validate(null, $body, $input, requireTitle: false);

        // Only NEW @mentions introduced by the edit are notified; existing ones
        // are not resent (PHASE_2_PLAN §8 "Mention edit").
        $before = MentionParser::parse((string) $post['body']);
        $after = MentionParser::parse($body);
        $beforeLower = array_map('strtolower', $before);
        $added = array_values(array_filter($after, static fn (string $h): bool => !in_array(strtolower($h), $beforeLower, true)));

        $this->db->transaction(function () use ($post, $postId, $body, $user, $added): void {
            $this->posts->update($postId, $body, $this->markdown->render($body), $user->id());
            if ($this->notifications !== null && $added !== []) {
                $threadCtx = [
                    'id' => (int) $post['thread_id'],
                    'board_id' => (int) $post['board_id'],
                    'board_visibility' => $post['board_visibility'] ?? 'public',
                ];
                $this->notifications->notifyMentions($user->id(), $threadCtx, $postId, $added);
            }
        });

        return $post;
    }

    /**
     * Owner self-delete (no audit log — that is the moderation path). Returns
     * the post row for the controller's redirect.
     *
     * @return array<string,mixed>
     */
    public function deleteOwnPost(User $user, int $postId): array
    {
        $this->writeGate->assertCanWrite($user);

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        if (!$user->owns((int) $post['user_id'])) {
            throw new ForbiddenException('You can only delete your own posts.');
        }

        $this->db->transaction(function () use ($post, $postId, $user): void {
            // Only adjust counters if this call actually performed the delete
            // (guards against a concurrent/duplicate delete double-decrementing).
            if ($this->posts->softDelete($postId, $user->id()) > 0) {
                $this->applyDeletionCounters($post);
            }
        });

        return $post;
    }

    /**
     * Counter maintenance after a post is soft-deleted. Public so the
     * moderation path (admin delete-any) reuses the exact same accounting.
     *
     * @param array<string,mixed> $post
     */
    public function applyDeletionCounters(array $post): void
    {
        $this->boards->decrementPostCount((int) $post['board_id'], 1);
        $this->users->incrementPostCount((int) $post['user_id'], -1);
        if ((int) $post['is_op'] === 0) {
            $this->threads->incrementReplyCount((int) $post['thread_id'], -1);
        }
        // Reputation derives from reactions received; remove this post's
        // contribution (non-self reactions) so rep recomputes downward
        // (COMMUNITY §2.1, PHASE_2_PLAN §7.3).
        $received = $this->receivedReactionCount((int) $post['id'], (int) $post['user_id']);
        if ($received > 0) {
            $this->users->incrementReputation((int) $post['user_id'], -$received);
        }
        // If this post was the thread's accepted answer, clear it and reverse the
        // solved bonus (author != OP). RepairService::reputationSolvedBonus and
        // solvedAnswerCount both already exclude deleted accepted answers, so the
        // runtime path must match or reputation drifts on the next repair.
        $thread = $this->threads->find((int) $post['thread_id']);
        if ($thread !== null && (int) ($thread['accepted_answer_post_id'] ?? 0) === (int) $post['id']) {
            $this->threads->setAcceptedAnswer((int) $post['thread_id'], null);
            if ((int) $post['user_id'] !== (int) $thread['user_id']) {
                $this->users->incrementReputation((int) $post['user_id'], -(int) $this->config->get('community.solved_bonus', 5));
            }
        }
        $this->threads->recomputeLastPost((int) $post['thread_id']);
        $this->boards->recomputeLastPost((int) $post['board_id']);
    }

    /**
     * Inverse of applyDeletionCounters for a restored post (moderation restore,
     * P2-08). Re-adds counters and the post's reputation contribution.
     *
     * @param array<string,mixed> $post
     */
    public function applyRestorationCounters(array $post): void
    {
        $this->boards->incrementPostCount((int) $post['board_id'], 1);
        $this->users->incrementPostCount((int) $post['user_id'], 1);
        if ((int) $post['is_op'] === 0) {
            $this->threads->incrementReplyCount((int) $post['thread_id'], 1);
        }
        $received = $this->receivedReactionCount((int) $post['id'], (int) $post['user_id']);
        if ($received > 0) {
            $this->users->incrementReputation((int) $post['user_id'], $received);
        }
        $this->threads->recomputeLastPost((int) $post['thread_id']);
        $this->boards->recomputeLastPost((int) $post['board_id']);
    }

    /** Private-board membership (board_members) — gates posting in private boards. */
    private function isBoardMember(int $boardId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ? LIMIT 1',
            [$boardId, $userId],
        ) !== false;
    }

    /** Reactions on a post from users other than the author (reputation contribution). */
    private function receivedReactionCount(int $postId, int $authorId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reactions WHERE post_id = ? AND user_id <> ?',
            [$postId, $authorId],
        );
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationException
     */
    private function validate(?string $title, string $body, array $input, bool $requireTitle): void
    {
        $errors = [];

        if ($requireTitle) {
            $title ??= '';
            if ($title === '') {
                $errors['title'] = 'Enter a title.';
            } elseif (mb_strlen($title) > (int) $this->config->get('limits.thread_title_max', 160)) {
                $errors['title'] = 'Title is too long (max 160).';
            }
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            $errors['body'] = 'Write something before posting.';
        } elseif (mb_strlen($body) > (int) $this->config->get('limits.post_body_max', 20000)) {
            $errors['body'] = 'Your post is too long.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }
    }
}
