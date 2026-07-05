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
use App\Support\Str;

final class ThreadSplitMergeService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private ModerationService $moderation,
        private ModerationLogRepository $logs,
    ) {
    }

    /** @param list<int> $postIds @return array<string,mixed> new thread row */
    public function split(User $actor, int $sourceThreadId, array $postIds, string $title): array
    {
        $source = $this->threads->find($sourceThreadId);
        if ($source === null || (int) $source['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        if (!$this->moderation->canModerate($actor, (int) $source['board_id'], 'core.thread.split_merge')) {
            throw new ForbiddenException('You do not moderate this board.');
        }

        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0)));
        $title = trim($title);
        if ($title === '') {
            throw new ValidationException(['title' => 'A split thread title is required.']);
        }
        if ($postIds === []) {
            throw new ValidationException(['post_ids' => 'Choose at least one reply to split.']);
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $selected = $this->db->fetchAll(
            "SELECT * FROM posts
             WHERE thread_id = ? AND id IN ($placeholders) AND is_deleted = 0
             ORDER BY created_at ASC, id ASC",
            array_merge([$sourceThreadId], $postIds),
        );
        if (count($selected) !== count($postIds)) {
            throw new ValidationException(['post_ids' => 'Every selected post must belong to this thread.']);
        }
        foreach ($selected as $post) {
            if ((int) $post['is_op'] === 1) {
                throw new ValidationException(['post_ids' => 'The original post cannot be split out of its thread.']);
            }
        }

        $newThreadId = $this->db->transaction(function () use ($actor, $source, $sourceThreadId, $selected, $postIds, $title): int {
            $slug = Str::slug($title);
            $newThreadId = $this->threads->create((int) $source['board_id'], (int) $selected[0]['user_id'], $title, $slug);
            $ids = array_map(static fn (array $p): int => (int) $p['id'], $selected);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $this->db->run("UPDATE posts SET thread_id = ?, is_op = 0 WHERE id IN ($in)", array_merge([$newThreadId], $ids));
            $this->db->run('UPDATE posts SET is_op = 1, parent_post_id = NULL WHERE id = ?', [(int) $selected[0]['id']]);
            $this->recountThread($sourceThreadId);
            $this->recountThread($newThreadId);
            $this->recountBoards([(int) $source['board_id']]);
            $this->logs->log([
                'actor_id' => $actor->id(),
                'action' => 'split_thread',
                'target_type' => 'thread',
                'target_id' => $sourceThreadId,
                'after' => ['new_thread_id' => $newThreadId, 'post_ids' => $postIds],
            ]);
            $this->db->insert(
                "INSERT INTO thread_operations
                    (operation_type, actor_id, source_thread_id, destination_thread_id, status, dry_run_plan, after_snapshot, created_at, applied_at)
                 VALUES ('split', ?, ?, ?, 'applied', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [
                    $actor->id(),
                    $sourceThreadId,
                    $newThreadId,
                    json_encode(['post_ids' => $postIds], JSON_THROW_ON_ERROR),
                    json_encode(['new_thread_id' => $newThreadId], JSON_THROW_ON_ERROR),
                ],
            );
            return $newThreadId;
        });

        $new = $this->threads->find($newThreadId);
        if ($new === null) {
            throw new \RuntimeException('Split thread was not created.');
        }
        return $new;
    }

    /** @return array<string,mixed> target thread row */
    public function merge(User $actor, int $sourceThreadId, int $targetThreadId): array
    {
        if ($sourceThreadId === $targetThreadId) {
            throw new ValidationException(['target_thread_id' => 'Choose a different target thread.']);
        }
        $source = $this->threads->find($sourceThreadId);
        $target = $this->threads->find($targetThreadId);
        if ($source === null || $target === null || (int) $source['is_deleted'] === 1 || (int) $target['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        if (!$this->moderation->canModerate($actor, (int) $source['board_id'], 'core.thread.split_merge')) {
            throw new ForbiddenException('You do not moderate the source board.');
        }
        if (!$this->moderation->canModerate($actor, (int) $target['board_id'], 'core.thread.split_merge')) {
            throw new ForbiddenException('You do not moderate the target board.');
        }

        $this->db->transaction(function () use ($actor, $source, $target, $sourceThreadId, $targetThreadId): void {
            $this->db->run('UPDATE posts SET thread_id = ?, is_op = 0 WHERE thread_id = ?', [$targetThreadId, $sourceThreadId]);
            $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$sourceThreadId]);
            $this->recountThread($sourceThreadId);
            $this->recountThread($targetThreadId);
            $this->recountBoards([(int) $source['board_id'], (int) $target['board_id']]);
            $operationId = $this->db->insert(
                "INSERT INTO thread_operations
                    (operation_type, actor_id, source_thread_id, destination_thread_id, status, dry_run_plan, after_snapshot, created_at, applied_at)
                 VALUES ('merge', ?, ?, ?, 'applied', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [
                    $actor->id(),
                    $sourceThreadId,
                    $targetThreadId,
                    json_encode(['target_thread_id' => $targetThreadId], JSON_THROW_ON_ERROR),
                    json_encode(['target_thread_id' => $targetThreadId], JSON_THROW_ON_ERROR),
                ],
            );
            $this->db->run(
                'INSERT INTO thread_redirects (old_thread_id, canonical_thread_id, operation_id, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE canonical_thread_id = VALUES(canonical_thread_id), operation_id = VALUES(operation_id), created_at = UTC_TIMESTAMP()',
                [$sourceThreadId, $targetThreadId, $operationId],
            );
            $this->logs->log([
                'actor_id' => $actor->id(),
                'action' => 'merge_thread',
                'target_type' => 'thread',
                'target_id' => $sourceThreadId,
                'after' => ['target_thread_id' => $targetThreadId],
            ]);
        });

        $target = $this->threads->find($targetThreadId);
        if ($target === null) {
            throw new \RuntimeException('Merge target disappeared.');
        }
        return $target;
    }

    private function recountThread(int $threadId): void
    {
        $this->db->run(
            'UPDATE threads t SET reply_count = (
                SELECT COUNT(*) FROM posts p
                WHERE p.thread_id = t.id AND p.is_deleted = 0 AND p.is_pending = 0 AND p.is_op = 0
             )
             WHERE t.id = ?',
            [$threadId],
        );
        $this->db->run(
            'UPDATE threads t
             LEFT JOIN (
                SELECT p.thread_id, p.id AS post_id, p.user_id, p.created_at
                FROM posts p
                WHERE p.thread_id = ? AND p.is_deleted = 0 AND p.is_pending = 0
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT 1
             ) lp ON lp.thread_id = t.id
             SET t.last_post_id = lp.post_id, t.last_post_user_id = lp.user_id, t.last_post_at = lp.created_at
             WHERE t.id = ?',
            [$threadId, $threadId],
        );
    }

    /** @param list<int> $boardIds */
    private function recountBoards(array $boardIds): void
    {
        foreach (array_values(array_unique($boardIds)) as $boardId) {
            $this->db->run(
                'UPDATE boards b SET thread_count = (
                    SELECT COUNT(*) FROM threads t
                    WHERE t.board_id = b.id AND t.is_deleted = 0 AND t.is_pending = 0
                 )
                 WHERE b.id = ?',
                [$boardId],
            );
            $this->db->run(
                'UPDATE boards b SET post_count = (
                    SELECT COUNT(*) FROM posts p
                    JOIN threads t ON t.id = p.thread_id
                    WHERE t.board_id = b.id
                      AND p.is_deleted = 0 AND p.is_pending = 0
                      AND t.is_deleted = 0 AND t.is_pending = 0
                 )
                 WHERE b.id = ?',
                [$boardId],
            );
            $this->db->run(
                'UPDATE boards b
                 LEFT JOIN (
                    SELECT t.board_id, p.thread_id, p.created_at
                    FROM posts p
                    JOIN threads t ON t.id = p.thread_id
                    WHERE t.board_id = ?
                      AND p.is_deleted = 0 AND p.is_pending = 0
                      AND t.is_deleted = 0 AND t.is_pending = 0
                    ORDER BY p.created_at DESC, p.id DESC
                    LIMIT 1
                 ) lp ON lp.board_id = b.id
                 SET b.last_thread_id = lp.thread_id, b.last_post_at = lp.created_at
                 WHERE b.id = ?',
                [$boardId, $boardId],
            );
        }
    }
}
