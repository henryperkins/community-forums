<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Bounded board-visibility invalidation.
 *
 * This is the sole exceptional explicit lock order: boards -> jobs. It issues
 * no thread-row FOR UPDATE and never touches summary, source, or relationship
 * rows (InnoDB can still take its normal parent-FK check while inserting a new
 * job). The worker invokes it outside any caller-owned transaction so this
 * transaction commits before normal threads -> jobs claims begin.
 */
final class ThreadIntelligenceBoardSweep
{
    public function __construct(private readonly Database $db)
    {
    }

    /** O(1): the visibility-changing admin transaction performs one update. */
    public function markVisibilityChanged(int $boardId): void
    {
        $this->db->run(
            'UPDATE boards SET thread_intelligence_sweep_after_id = 0 WHERE id = ?',
            [$boardId],
        );
    }

    /**
     * @return array{board_id:int,visibility:string,processed:int,cursor:?int,complete:bool}|array{}
     */
    public function runBatch(int $limit = 250, ?DateTimeImmutable $now = null): array
    {
        if ($this->db->pdo()->inTransaction()) {
            throw new LogicException('board sweep requires a top-level transaction boundary');
        }
        $limit = max(1, min(250, $limit));
        $nowUtc = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));

        return $this->db->transaction(function () use ($limit, $nowUtc): array {
            // Exact plan SQL: one board lock, skip a board already owned by a
            // concurrent sweep or visibility-changing administrator.
            $board = $this->db->fetch(<<<'SQL'
                SELECT id, visibility, thread_intelligence_sweep_after_id
                FROM boards
                WHERE thread_intelligence_sweep_after_id IS NOT NULL
                ORDER BY id
                LIMIT 1
                FOR UPDATE SKIP LOCKED
                SQL);
            if ($board === null) {
                return [];
            }

            $boardId = (int) $board['id'];
            $afterId = (int) $board['thread_intelligence_sweep_after_id'];
            // Exact plan lookahead: always read up to 251 IDs. Only the bounded
            // prefix requested by this run is allowed to touch job rows.
            $rows = $this->db->fetchAll(<<<'SQL'
                SELECT id
                FROM threads
                WHERE board_id = :board_id AND id > :after_id
                ORDER BY id
                LIMIT 251
                SQL, ['board_id' => $boardId, 'after_id' => $afterId]);
            $threadIds = array_map(static fn (array $row): int => (int) $row['id'], array_slice($rows, 0, $limit));

            if ($threadIds !== []) {
                if ($board['visibility'] === 'public') {
                    $this->upsertPublicJobs($threadIds, $nowUtc->modify('+15 minutes'));
                } else {
                    $this->idlePrivateJobs($threadIds);
                }
            }

            $hasMore = count($rows) > count($threadIds);
            $cursor = $hasMore && $threadIds !== [] ? $threadIds[array_key_last($threadIds)] : null;
            $this->db->run(
                'UPDATE boards SET thread_intelligence_sweep_after_id = ? WHERE id = ?',
                [$cursor, $boardId],
            );

            return [
                'board_id' => $boardId,
                'visibility' => (string) $board['visibility'],
                'processed' => count($threadIds),
                'cursor' => $cursor,
                'complete' => !$hasMore,
            ];
        });
    }

    /** @param list<int> $threadIds */
    private function upsertPublicJobs(array $threadIds, DateTimeImmutable $dueAt): void
    {
        $due = $dueAt->format('Y-m-d H:i:s');
        foreach ($threadIds as $threadId) {
            $this->db->run(
                "INSERT INTO thread_intelligence_jobs
                    (thread_id, state, trigger_code, trigger_reason, due_at, activity_version,
                     reconcile_required, created_at, updated_at)
                 VALUES (:thread_id, 'queued', :trigger_code, NULL, :due_at, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    activity_version = activity_version + 1,
                    trigger_code = VALUES(trigger_code),
                    trigger_reason = NULL,
                    reconcile_required = 1,
                    due_at = CASE
                        WHEN state IN ('dead', 'review_required', 'running') THEN due_at
                        WHEN automation_paused = 1 THEN NULL
                        ELSE VALUES(due_at)
                    END,
                    state = CASE
                        WHEN state IN ('dead', 'review_required', 'running') THEN state
                        WHEN automation_paused = 1 THEN 'idle'
                        ELSE 'queued'
                    END,
                    updated_at = UTC_TIMESTAMP()",
                [
                    'thread_id' => $threadId,
                    'trigger_code' => ThreadIntelligenceQueue::TRIGGER_BOARD_VISIBILITY_CHANGED,
                    'due_at' => $due,
                ],
            );
        }
    }

    /** @param list<int> $threadIds */
    private function idlePrivateJobs(array $threadIds): void
    {
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'idle', due_at = NULL, updated_at = UTC_TIMESTAMP()
             WHERE thread_id IN ($placeholders) AND state IN ('queued', 'retry')",
            $threadIds,
        );
    }
}
