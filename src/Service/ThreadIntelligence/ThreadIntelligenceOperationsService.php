<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use DateTimeImmutable;
use DateTimeZone;

/** Shared safe operator surface for HTTP and console workflows. */
final class ThreadIntelligenceOperationsService
{
    private const JOB_STATES = ['idle', 'queued', 'running', 'retry', 'dead', 'review_required'];

    public function __construct(
        private readonly Database $db,
        private readonly FeatureFlags $flags,
        private readonly ThreadIntelligenceConfig $config,
        private readonly ThreadIntelligenceSettings $settings,
        private readonly ThreadIntelligenceBudget $budget,
        private readonly ThreadIntelligenceEligibility $eligibility,
        private readonly ThreadIntelligenceQueue $queue,
        private readonly ThreadIntelligenceJobRepository $jobs,
        private readonly ThreadIntelligenceGenerationRepository $generations,
    ) {
    }

    /** @return array<string,mixed> credential- and fingerprint-free status */
    public function status(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $counts = array_fill_keys(self::JOB_STATES, 0);
        foreach ($this->db->fetchAll(
            'SELECT state, COUNT(*) AS aggregate_count FROM thread_intelligence_jobs GROUP BY state',
        ) as $row) {
            if (is_string($row['state'] ?? null) && array_key_exists($row['state'], $counts)) {
                $counts[$row['state']] = (int) $row['aggregate_count'];
            }
        }

        $heartbeat = $this->settings->heartbeat();
        $heartbeat['classification'] = $this->heartbeatClassification($heartbeat, $now);

        return [
            'flags' => [
                'community_memory' => $this->flags->enabled('community_memory'),
                'automated_context' => $this->flags->enabled('automated_context'),
            ],
            'credential_ready' => $this->config->providerReady(),
            'pause' => $this->settings->generationPause(),
            'provider' => $this->settings->providerHealth(),
            'heartbeat' => $heartbeat,
            'queue' => $counts,
            'model' => $this->config->model(),
            'reasoning_effort' => $this->config->reasoningEffort(),
            'prompt_version' => ThreadIntelligencePromptBuilder::VERSION,
            'budget' => $this->budget->status($now),
            'configuration_warnings' => $this->config->warnings(),
        ];
    }

    public function retry(int $threadId): ThreadIntelligenceQueueResult
    {
        return $this->recover($threadId, false);
    }

    public function reconcile(int $threadId): ThreadIntelligenceQueueResult
    {
        return $this->recover($threadId, true);
    }

    public function pruneEvidence(int $limit = 500): int
    {
        return $this->generations->pruneEligible(
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            max(1, min(500, $limit)),
        );
    }

    public function clearProviderLatch(): void
    {
        $this->settings->clearProviderBlock();
    }

    private function recover(int $threadId, bool $reconcile): ThreadIntelligenceQueueResult
    {
        if ($threadId < 1) {
            return new ThreadIntelligenceQueueResult(
                false,
                'thread_not_found',
                'Refresh is available only for eligible public threads',
            );
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->db->transaction(function () use ($threadId, $reconcile, $now): ThreadIntelligenceQueueResult {
            // Recovery follows the canonical thread -> job order and never
            // clears a foreign active lease or a denied terminal state.
            $this->db->fetch(
                'SELECT id FROM threads WHERE id = ? FOR UPDATE',
                [$threadId],
            );
            $original = $this->jobs->findForUpdate($threadId);
            if ($this->hasActiveLease($original, $now)) {
                $next = new DateTimeImmutable((string) $original['lease_expires_at'], new DateTimeZone('UTC'));
                return new ThreadIntelligenceQueueResult(
                    false,
                    'active_lease',
                    'Automatic refresh is already running',
                    $next,
                );
            }

            $terminalState = is_array($original)
                && in_array($original['state'] ?? null, ['dead', 'review_required'], true)
                ? (string) $original['state']
                : null;
            if ($terminalState !== null) {
                // Eligibility must ignore only the state being explicitly
                // recovered; every other shared gate still reads current data.
                $this->db->run(
                    "UPDATE thread_intelligence_jobs SET state = 'idle' WHERE thread_id = ?",
                    [$threadId],
                );
            } elseif (is_array($original) && ($original['state'] ?? null) === 'running') {
                // An expired/interrupted lease is recoverable by an operator.
                $this->db->run(
                    "UPDATE thread_intelligence_jobs
                     SET state = 'idle', lease_token = NULL, lease_expires_at = NULL
                     WHERE thread_id = ?",
                    [$threadId],
                );
            }

            $decision = $this->eligibility->forExplicitRefresh($threadId, $now);
            if (!$decision->eligible) {
                if ($terminalState !== null) {
                    $this->db->run(
                        'UPDATE thread_intelligence_jobs SET state = ? WHERE thread_id = ?',
                        [$terminalState, $threadId],
                    );
                } elseif (is_array($original) && ($original['state'] ?? null) === 'running') {
                    $this->restoreRunning($threadId, $original);
                }
                return new ThreadIntelligenceQueueResult(
                    false,
                    $decision->code,
                    $decision->message,
                    $decision->nextEligibleAt,
                );
            }

            $trigger = $reconcile
                ? ThreadIntelligenceQueue::TRIGGER_RECONCILE
                : ThreadIntelligenceQueue::TRIGGER_CURATOR_REFRESH;
            if ($original === null) {
                $this->jobs->upsertStale($threadId, $trigger, null, $now);
            } else {
                $this->db->run(
                    "UPDATE thread_intelligence_jobs
                     SET state = 'queued',
                         trigger_code = :trigger,
                         trigger_reason = NULL,
                         due_at = :due_at,
                         lease_token = NULL,
                         lease_expires_at = NULL,
                         attempt_count = 0,
                         last_error_code = NULL,
                         reconcile_required = CASE WHEN :reconcile = 1 THEN 1 ELSE reconcile_required END,
                         updated_at = UTC_TIMESTAMP()
                     WHERE thread_id = :thread_id",
                    [
                        'trigger' => $trigger,
                        'due_at' => $now->format('Y-m-d H:i:s'),
                        'reconcile' => $reconcile ? 1 : 0,
                        'thread_id' => $threadId,
                    ],
                );
            }

            return new ThreadIntelligenceQueueResult(true, 'queued', $reconcile ? 'Reconciliation queued' : 'Refresh queued');
        });
    }

    /** @param array<string,mixed>|null $job */
    private function hasActiveLease(?array $job, DateTimeImmutable $now): bool
    {
        return $job !== null
            && ($job['state'] ?? null) === 'running'
            && is_string($job['lease_token'] ?? null)
            && $job['lease_token'] !== ''
            && is_string($job['lease_expires_at'] ?? null)
            && strcmp($job['lease_expires_at'], $now->format('Y-m-d H:i:s')) > 0;
    }

    /** @param array<string,mixed> $original */
    private function restoreRunning(int $threadId, array $original): void
    {
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'running', lease_token = ?, lease_expires_at = ?
             WHERE thread_id = ?",
            [$original['lease_token'], $original['lease_expires_at'], $threadId],
        );
    }

    /** @param array<string,mixed> $heartbeat */
    private function heartbeatClassification(array $heartbeat, DateTimeImmutable $now): string
    {
        if (!($heartbeat['exists'] ?? false)) {
            return 'never_run';
        }
        if ($heartbeat['corrupt'] ?? false) {
            return 'invalid';
        }
        if (($heartbeat['status'] ?? null) === 'running') {
            $started = $this->heartbeatTime($heartbeat['started_at'] ?? null);
            return $started !== null && $started < $now->modify('-' . ThreadIntelligenceJobRepository::LEASE_SECONDS . ' seconds')
                ? 'interrupted'
                : 'running';
        }
        if (($heartbeat['status'] ?? null) === 'error') {
            return 'attention';
        }
        $completed = $this->heartbeatTime($heartbeat['completed_at'] ?? null);
        return $completed !== null && $completed < $now->modify('-5 minutes') ? 'stale' : 'healthy';
    }

    private function heartbeatTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        return $parsed === false ? null : $parsed;
    }
}
