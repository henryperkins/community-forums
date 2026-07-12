<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Durable per-thread Thread Intelligence queue/state row (migration 0077).
 *
 * One row per thread acts as queue plus current state. Claims use FOR UPDATE
 * SKIP LOCKED with an independent cryptographically random ten-minute lease
 * per row; renew/release are compare-and-set over BOTH the lease token and the
 * expected activity_version, so an in-flight provider response can never erase
 * activity committed during its lease — a version mismatch requeues the newer
 * activity instead of clearing it.
 */
final class ThreadIntelligenceJobRepository
{
    public const LEASE_SECONDS = 600;
    private const CLAIM_SCAN_MULTIPLIER = 10;

    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $threadId): ?array
    {
        return $this->db->fetch('SELECT * FROM thread_intelligence_jobs WHERE thread_id = ?', [$threadId]);
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $threadId): ?array
    {
        return $this->db->fetch('SELECT * FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE', [$threadId]);
    }

    /**
     * Records meaningful activity: creates the row queued, or bumps the
     * existing row's activity_version and debounce time. Terminal review
     * states, running leases, and reconcile_required are preserved; a paused
     * row still records activity (claiming skips it separately).
     */
    public function upsertStale(int $threadId, string $trigger, ?string $reason, DateTimeImmutable $dueAt): void
    {
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id, state, trigger_code, trigger_reason, due_at, activity_version, created_at, updated_at)
             VALUES (:thread_id, 'queued', :trigger_code, :trigger_reason, :due_at, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                activity_version = activity_version + 1,
                trigger_code = VALUES(trigger_code),
                trigger_reason = VALUES(trigger_reason),
                due_at = CASE WHEN state IN ('dead', 'review_required', 'running') THEN due_at ELSE VALUES(due_at) END,
                state = CASE WHEN state IN ('dead', 'review_required', 'running') THEN state ELSE 'queued' END,
                updated_at = UTC_TIMESTAMP()",
            [
                'thread_id' => $threadId,
                'trigger_code' => substr($trigger, 0, 64),
                'trigger_reason' => $reason === null ? null : substr($reason, 0, 255),
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    /** ORs the full-reconciliation requirement; later routine posts never clear it. */
    public function requireReconcile(int $threadId): void
    {
        $this->db->run(
            'UPDATE thread_intelligence_jobs SET reconcile_required = 1, updated_at = UTC_TIMESTAMP() WHERE thread_id = ?',
            [$threadId],
        );
    }

    /**
     * Claims up to $limit due rows in one bounded transaction: due queued/retry
     * work plus expired running leases, skipping paused rows, terminal states,
     * active leases, and rows locked by a concurrent worker (SKIP LOCKED).
     * Each claimed row gets its own random lease token and ten-minute expiry;
     * the returned rows carry the claimed activity_version.
     *
     * @return list<array<string,mixed>>
     */
    public function claimDue(int $limit, DateTimeImmutable $now): array
    {
        $limit = max(1, min($limit, 100));
        $stamp = $now->format('Y-m-d H:i:s');
        $scanBudget = $limit * self::CLAIM_SCAN_MULTIPLIER;

        return $this->db->transaction(function () use ($limit, $now, $stamp, $scanBudget): array {
            // Walk three non-locking, index-backed streams in global due order,
            // then lock each candidate by primary key. Exact-row locking avoids
            // InnoDB next-key locks on later work, while advancing the stream
            // after a skipped row lets a limit-one worker find the next row.
            $streams = [
                'queued' => ['index' => 'idx_ti_jobs_due', 'ready_column' => 'due_at', 'head' => null],
                'retry' => ['index' => 'idx_ti_jobs_due', 'ready_column' => 'due_at', 'head' => null],
                'running' => ['index' => 'idx_ti_jobs_lease', 'ready_column' => 'lease_expires_at', 'head' => null],
            ];
            $nextCandidate = function (string $state, array $stream, ?array $after) use ($stamp): ?array {
                $readyColumn = $stream['ready_column'];
                $afterSql = '';
                $params = ['state' => $state, 'ready_at' => $stamp];
                if ($after !== null) {
                    $afterSql = " AND ($readyColumn > :after_ready
                                      OR ($readyColumn = :same_ready AND thread_id > :after_thread))";
                    $params['after_ready'] = (string) $after['ready_at'];
                    $params['same_ready'] = (string) $after['ready_at'];
                    $params['after_thread'] = (int) $after['thread_id'];
                }

                return $this->db->fetch(
                    "SELECT thread_id, $readyColumn AS ready_at
                     FROM thread_intelligence_jobs FORCE INDEX (" . $stream['index'] . ")
                     WHERE state = :state AND automation_paused = 0
                       AND $readyColumn IS NOT NULL AND $readyColumn <= :ready_at"
                    . $afterSql
                    . " ORDER BY $readyColumn ASC, thread_id ASC LIMIT 1",
                    $params,
                );
            };

            foreach ($streams as $state => $stream) {
                $streams[$state]['head'] = $nextCandidate($state, $stream, null);
            }

            $claimedIds = [];
            $scanned = 0;
            while (count($claimedIds) < $limit && $scanned < $scanBudget) {
                $available = [];
                foreach ($streams as $state => $stream) {
                    if (is_array($stream['head'])) {
                        $available[] = ['state' => $state, ...$stream['head']];
                    }
                }
                if ($available === []) {
                    break;
                }
                usort($available, static function (array $a, array $b): int {
                    $byReadyAt = strcmp((string) $a['ready_at'], (string) $b['ready_at']);
                    return $byReadyAt !== 0 ? $byReadyAt : ((int) $a['thread_id'] <=> (int) $b['thread_id']);
                });

                $candidate = $available[0];
                $scanned++;
                $state = (string) $candidate['state'];
                $streams[$state]['head'] = $nextCandidate($state, $streams[$state], $candidate);

                $locked = $this->db->fetch(
                    "SELECT thread_id FROM thread_intelligence_jobs
                     WHERE thread_id = :thread_id AND automation_paused = 0
                       AND (
                            (state IN ('queued', 'retry') AND due_at IS NOT NULL AND due_at <= :due_now)
                         OR (state = 'running' AND lease_expires_at IS NOT NULL AND lease_expires_at <= :lease_now)
                       )
                     FOR UPDATE SKIP LOCKED",
                    ['thread_id' => (int) $candidate['thread_id'], 'due_now' => $stamp, 'lease_now' => $stamp],
                );
                if ($locked !== null) {
                    $claimedIds[] = (int) $locked['thread_id'];
                }
            }
            if ($claimedIds === []) {
                return [];
            }

            $expiresAt = $now->modify('+' . self::LEASE_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            foreach ($claimedIds as $threadId) {
                $this->db->run(
                    "UPDATE thread_intelligence_jobs
                     SET state = 'running', lease_token = :token, lease_expires_at = :expires_at, updated_at = UTC_TIMESTAMP()
                     WHERE thread_id = :thread_id",
                    ['token' => bin2hex(random_bytes(32)), 'expires_at' => $expiresAt, 'thread_id' => $threadId],
                );
            }

            $claimedPlaceholders = implode(',', array_fill(0, count($claimedIds), '?'));
            return array_values($this->db->fetchAll(
                "SELECT * FROM thread_intelligence_jobs
                 WHERE thread_id IN ($claimedPlaceholders)
                 ORDER BY FIELD(thread_id, $claimedPlaceholders)",
                [...$claimedIds, ...$claimedIds],
            ));
        });
    }

    /** Compare-and-set lease extension: exact token, version, and running state required. */
    public function renewLease(int $threadId, string $leaseToken, int $expectedActivityVersion, DateTimeImmutable $expiresAt): bool
    {
        return $this->db->transaction(function () use ($threadId, $leaseToken, $expectedActivityVersion, $expiresAt): bool {
            $row = $this->findForUpdate($threadId);
            if ($row === null || $row['state'] !== 'running'
                || !is_string($row['lease_token']) || !hash_equals($row['lease_token'], $leaseToken)) {
                return false;
            }
            if ((int) $row['activity_version'] !== $expectedActivityVersion) {
                $this->requeueNewerActivity($threadId);
                return false;
            }
            $this->db->run(
                'UPDATE thread_intelligence_jobs SET lease_expires_at = :expires_at, updated_at = UTC_TIMESTAMP() WHERE thread_id = :thread_id',
                ['expires_at' => $expiresAt->format('Y-m-d H:i:s'), 'thread_id' => $threadId],
            );
            return true;
        });
    }

    /**
     * Releases an owned lease into $state ('idle'|'queued'|'retry'|'dead'|'review_required'),
     * counting one attempt when an error code is recorded. A stale activity
     * version requeues the newer activity (due immediately) and returns false;
     * a foreign token touches nothing.
     */
    public function release(int $threadId, string $leaseToken, int $expectedActivityVersion, string $state, ?DateTimeImmutable $dueAt, ?string $errorCode): bool
    {
        $allowed = ['idle', 'queued', 'retry', 'dead', 'review_required'];
        if (!in_array($state, $allowed, true)) {
            throw new \InvalidArgumentException('release state must be one of ' . implode('|', $allowed));
        }

        return $this->db->transaction(function () use ($threadId, $leaseToken, $expectedActivityVersion, $state, $dueAt, $errorCode): bool {
            $row = $this->findForUpdate($threadId);
            if ($row === null || $row['state'] !== 'running'
                || !is_string($row['lease_token']) || !hash_equals($row['lease_token'], $leaseToken)) {
                return false;
            }

            if ((int) $row['activity_version'] !== $expectedActivityVersion) {
                $this->requeueNewerActivity($threadId);
                return false;
            }

            // Enum literals come from the validated allowlist above, never from data.
            $this->db->run(
                "UPDATE thread_intelligence_jobs
                 SET state = '" . $state . "',
                     due_at = :due_at,
                     lease_token = NULL,
                     lease_expires_at = NULL,
                     last_error_code = :error_code,
                     attempt_count = attempt_count + :attempt_delta,
                     updated_at = UTC_TIMESTAMP()
                 WHERE thread_id = :thread_id",
                [
                    'due_at' => $dueAt?->format('Y-m-d H:i:s'),
                    'error_code' => $errorCode === null ? null : substr($errorCode, 0, 64),
                    'attempt_delta' => $errorCode === null ? 0 : 1,
                    'thread_id' => $threadId,
                ],
            );
            return true;
        });
    }

    /**
     * Successful-publication release: advances checkpoint, cadence, and
     * snapshot, clears failure/attempt state, and (for a full reconcile whose
     * version still matches) the reconcile requirement. A stale activity
     * version requeues instead. $generationId is the published ledger row —
     * the ledger's published_summary_id is the authoritative link; it is
     * accepted here so call sites read as one publication event.
     */
    public function releasePublished(int $threadId, string $leaseToken, int $expectedActivityVersion, int $generationId, int $lastProcessedPostId, string $snapshotHash, bool $fullReconcile, DateTimeImmutable $publishedAt): bool
    {
        if ($generationId < 1) {
            throw new \InvalidArgumentException('generation id must be positive');
        }

        return $this->db->transaction(function () use ($threadId, $leaseToken, $expectedActivityVersion, $generationId, $lastProcessedPostId, $snapshotHash, $fullReconcile, $publishedAt): bool {
            // Atomic publication already owns thread -> job before completing
            // its generation. Keep every release/prune/reconciliation path on
            // the same job -> generation dependent-row order.
            $row = $this->findForUpdate($threadId);
            if ($row === null || $row['state'] !== 'running'
                || !is_string($row['lease_token']) || !hash_equals($row['lease_token'], $leaseToken)) {
                return false;
            }

            $generation = $this->db->fetch(
                'SELECT thread_id, status FROM thread_intelligence_generations WHERE id = ? FOR UPDATE',
                [$generationId],
            );
            if ($generation === null || (int) $generation['thread_id'] !== $threadId || $generation['status'] !== 'published') {
                return false;
            }

            if ((int) $row['activity_version'] !== $expectedActivityVersion) {
                $this->requeueNewerActivity($threadId);
                return false;
            }

            $stamp = $publishedAt->format('Y-m-d H:i:s');
            $this->db->run(
                "UPDATE thread_intelligence_jobs
                 SET state = 'idle',
                     due_at = NULL,
                     lease_token = NULL,
                     lease_expires_at = NULL,
                     attempt_count = 0,
                     last_error_code = NULL,
                     last_processed_post_id = :post_id,
                     last_generated_at = :generated_at,
                     last_full_reconcile_at = CASE WHEN :is_full = 1 THEN :reconciled_at ELSE last_full_reconcile_at END,
                     reconcile_required = CASE WHEN :is_full_again = 1 THEN 0 ELSE reconcile_required END,
                     source_snapshot_hash = :snapshot_hash,
                     updated_at = UTC_TIMESTAMP()
                 WHERE thread_id = :thread_id",
                [
                    'post_id' => $lastProcessedPostId,
                    'generated_at' => $stamp,
                    'is_full' => $fullReconcile ? 1 : 0,
                    'reconciled_at' => $stamp,
                    'is_full_again' => $fullReconcile ? 1 : 0,
                    'snapshot_hash' => $snapshotHash,
                    'thread_id' => $threadId,
                ],
            );
            return true;
        });
    }

    /** The newer activity version stays queued and immediately due; the lease is surrendered. */
    private function requeueNewerActivity(int $threadId): void
    {
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'queued',
                 due_at = UTC_TIMESTAMP(),
                 lease_token = NULL,
                 lease_expires_at = NULL,
                 updated_at = UTC_TIMESTAMP()
             WHERE thread_id = ?",
            [$threadId],
        );
    }
}
