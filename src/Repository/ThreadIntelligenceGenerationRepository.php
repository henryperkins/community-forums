<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Append-only Thread Intelligence attempt/evidence ledger (migration 0077).
 *
 * Rows are immutable within their retention window: start() opens a
 * `requested` row, recordRequest() may populate the request-evidence fields
 * exactly once (in the same short transaction that reserves budget — a
 * nonnull request_fingerprint therefore proves a committed reservation), and
 * complete() performs exactly one requested→terminal transition. Column
 * whitelists reject any credential, raw prompt, raw response, or post body at
 * the API boundary; bounded pruning is the sole deletion path besides
 * thread-owned cascade.
 */
final class ThreadIntelligenceGenerationRepository
{
    private const START_COLUMNS = [
        'thread_id', 'trigger_code', 'retry_number', 'window_number',
        'baseline_summary_id', 'model', 'reasoning_effort', 'prompt_version',
    ];

    private const TERMINAL_COLUMNS = [
        'status', 'failure_code', 'failure_message', 'provider_response_id',
        'input_tokens', 'output_tokens', 'reasoning_tokens', 'cached_tokens',
        'published_summary_id', 'published_at',
    ];

    private const TERMINAL_STATUSES = [
        'succeeded', 'published', 'retry', 'failed', 'dead', 'review_required', 'rejected', 'stale',
    ];

    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $attempt @return int the new generation id */
    public function start(array $attempt): int
    {
        $unknown = array_diff(array_keys($attempt), self::START_COLUMNS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('generation attempts may not carry: ' . implode(', ', $unknown));
        }
        if (!isset($attempt['thread_id'], $attempt['trigger_code'])) {
            throw new InvalidArgumentException('thread_id and trigger_code are required');
        }

        return $this->db->insert(
            "INSERT INTO thread_intelligence_generations
                (thread_id, trigger_code, status, retry_number, window_number,
                 baseline_summary_id, model, reasoning_effort, prompt_version, requested_at)
             VALUES (:thread_id, :trigger_code, 'requested', :retry_number, :window_number,
                 :baseline_summary_id, :model, :reasoning_effort, :prompt_version, UTC_TIMESTAMP())",
            [
                'thread_id' => (int) $attempt['thread_id'],
                'trigger_code' => substr((string) $attempt['trigger_code'], 0, 64),
                'retry_number' => (int) ($attempt['retry_number'] ?? 0),
                'window_number' => (int) ($attempt['window_number'] ?? 0),
                'baseline_summary_id' => $attempt['baseline_summary_id'] ?? null,
                'model' => isset($attempt['model']) ? substr((string) $attempt['model'], 0, 128) : null,
                'reasoning_effort' => isset($attempt['reasoning_effort']) ? substr((string) $attempt['reasoning_effort'], 0, 16) : null,
                'prompt_version' => isset($attempt['prompt_version']) ? substr((string) $attempt['prompt_version'], 0, 64) : null,
            ],
        );
    }

    /**
     * Populates the request-evidence fields exactly once while the row is
     * still `requested`. Runs in the caller's short budget transaction: a
     * committed nonnull fingerprint means the reservation committed with it.
     *
     * @param list<int> $sourcePostIds
     * @param list<int> $candidateThreadIds
     */
    public function recordRequest(int $id, string $snapshotHash, array $sourcePostIds, array $candidateThreadIds, string $requestFingerprint, int $estimatedInputTokens): void
    {
        $updated = $this->db->run(
            "UPDATE thread_intelligence_generations
             SET source_snapshot_hash = :snapshot_hash,
                 source_post_ids = :source_post_ids,
                 candidate_thread_ids = :candidate_thread_ids,
                 request_fingerprint = :request_fingerprint,
                 estimated_input_tokens = :estimated_input_tokens
             WHERE id = :id AND status = 'requested' AND request_fingerprint IS NULL",
            [
                'snapshot_hash' => $snapshotHash,
                'source_post_ids' => json_encode(array_values(array_map('intval', $sourcePostIds)), JSON_THROW_ON_ERROR),
                'candidate_thread_ids' => json_encode(array_values(array_map('intval', $candidateThreadIds)), JSON_THROW_ON_ERROR),
                'request_fingerprint' => $requestFingerprint,
                'estimated_input_tokens' => max(0, $estimatedInputTokens),
                'id' => $id,
            ],
        )->rowCount();

        if ($updated !== 1) {
            throw new LogicException('request evidence may be recorded exactly once per requested attempt');
        }
    }

    /**
     * Exactly one requested→terminal transition; terminal rows are
     * update-closed. Safe failure detail is truncated to 255 characters.
     *
     * @param array<string,mixed> $terminalEvidence
     */
    public function complete(int $id, array $terminalEvidence): void
    {
        $unknown = array_diff(array_keys($terminalEvidence), self::TERMINAL_COLUMNS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('terminal evidence may not carry: ' . implode(', ', $unknown));
        }
        $status = $terminalEvidence['status'] ?? null;
        if (!in_array($status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidArgumentException('status must be a terminal generation status');
        }

        $updated = $this->db->run(
            "UPDATE thread_intelligence_generations
             SET status = '" . $status . "',
                 failure_code = :failure_code,
                 failure_message = :failure_message,
                 provider_response_id = :provider_response_id,
                 input_tokens = :input_tokens,
                 output_tokens = :output_tokens,
                 reasoning_tokens = :reasoning_tokens,
                 cached_tokens = :cached_tokens,
                 published_summary_id = :published_summary_id,
                 published_at = :published_at,
                 completed_at = UTC_TIMESTAMP()
             WHERE id = :id AND status = 'requested'",
            [
                'failure_code' => isset($terminalEvidence['failure_code']) ? substr((string) $terminalEvidence['failure_code'], 0, 64) : null,
                'failure_message' => isset($terminalEvidence['failure_message']) ? substr((string) $terminalEvidence['failure_message'], 0, 255) : null,
                'provider_response_id' => isset($terminalEvidence['provider_response_id']) ? substr((string) $terminalEvidence['provider_response_id'], 0, 128) : null,
                'input_tokens' => $terminalEvidence['input_tokens'] ?? null,
                'output_tokens' => $terminalEvidence['output_tokens'] ?? null,
                'reasoning_tokens' => $terminalEvidence['reasoning_tokens'] ?? null,
                'cached_tokens' => $terminalEvidence['cached_tokens'] ?? null,
                'published_summary_id' => $terminalEvidence['published_summary_id'] ?? null,
                'published_at' => $terminalEvidence['published_at'] ?? null,
                'id' => $id,
            ],
        )->rowCount();

        if ($updated !== 1) {
            throw new LogicException('generation rows accept exactly one requested-to-terminal transition');
        }
    }

    /**
     * Still-`requested` rows older than the owning ten-minute lease cutoff,
     * oldest first, for worker crash reconciliation. Bounded at 100.
     *
     * @return list<array<string,mixed>>
     */
    public function abandonedRequested(DateTimeImmutable $leaseCutoff, int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));

        return array_values($this->db->fetchAll(
            "SELECT * FROM thread_intelligence_generations
             WHERE status = 'requested' AND requested_at <= :cutoff
             ORDER BY id ASC
             LIMIT " . $limit,
            ['cutoff' => $leaseCutoff->format('Y-m-d H:i:s')],
        ));
    }

    /** @return list<array<string,mixed>> newest attempts first */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return array_values($this->db->fetchAll(
            'SELECT * FROM thread_intelligence_generations ORDER BY id DESC LIMIT ' . $limit,
        ));
    }

    /**
     * Bounded retention pruning (the sole deletion path besides thread
     * cascade): unpublished terminal rows 90 days after completion; evidence
     * behind a live dead/review_required job is retained until that state
     * resolves and the job has been quiet past the window (updated_at is the
     * conservative resolution proxy). Published and requested rows are never
     * deleted here. Deliberately independent of flags, credentials, and the
     * generation brake.
     */
    public function pruneEligible(DateTimeImmutable $now, int $limit): int
    {
        $limit = max(1, min($limit, 500));
        $cutoff = $now->modify('-90 days')->format('Y-m-d H:i:s');

        return $this->db->transaction(function () use ($cutoff, $limit): int {
            $rows = $this->db->fetchAll(
                "SELECT g.id
                 FROM thread_intelligence_generations g
                 LEFT JOIN thread_intelligence_jobs j ON j.thread_id = g.thread_id
                 WHERE g.completed_at IS NOT NULL
                   AND g.completed_at <= :terminal_cutoff
                   AND (
                        g.status IN ('succeeded', 'retry', 'failed', 'rejected', 'stale')
                     OR (
                          g.status IN ('dead', 'review_required')
                          AND (j.thread_id IS NULL OR (j.state NOT IN ('dead', 'review_required') AND j.updated_at <= :resolution_cutoff))
                        )
                   )
                   AND (j.thread_id IS NULL OR j.state NOT IN ('dead', 'review_required'))
                 ORDER BY g.id ASC
                 LIMIT " . $limit,
                ['terminal_cutoff' => $cutoff, 'resolution_cutoff' => $cutoff],
            );

            if ($rows === []) {
                return 0;
            }

            $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return $this->db->run(
                "DELETE FROM thread_intelligence_generations WHERE id IN ($placeholders)",
                $ids,
            )->rowCount();
        });
    }
}
