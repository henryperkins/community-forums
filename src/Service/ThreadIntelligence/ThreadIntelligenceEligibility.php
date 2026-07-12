<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\ThreadIntelligenceJobRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * One fail-closed policy for durable enqueueing, provider generation, and an
 * authorized explicit refresh. Enqueueing records content eligibility while
 * operational outages defer already-eligible work at generation time.
 */
final class ThreadIntelligenceEligibility
{
    private const INITIAL_POST_THRESHOLD = 8;
    private const INCREMENTAL_POST_THRESHOLD = 5;
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Database $db,
        private readonly FeatureFlags $flags,
        private readonly ThreadIntelligenceConfig $config,
        private readonly ThreadIntelligenceSettings $settings,
        private readonly ThreadIntelligenceBudget $budget,
        private readonly ThreadIntelligenceJobRepository $jobs,
    ) {
    }

    public function forEnqueue(int $threadId, DateTimeImmutable $now): ThreadIntelligenceEligibilityResult
    {
        return $this->decide($threadId, $this->jobs->find($threadId), $now, enqueueOnly: true, explicit: false);
    }

    /**
     * Re-evaluates enqueue content after the queue has locked the thread's
     * current visibility. Content reads deliberately remain nonlocking and
     * precede the queue's job-row lock, matching canonical post -> job order.
     * The caller must retain the visibility lock through its mutation.
     *
     * @param array{id:int,is_deleted:int,is_pending:int,visibility:string} $thread
     */
    public function forEnqueueLocked(
        int $threadId,
        array $thread,
        DateTimeImmutable $now,
    ): ThreadIntelligenceEligibilityResult {
        if ((int) ($thread['id'] ?? 0) !== $threadId) {
            throw new InvalidArgumentException('locked enqueue eligibility requires the requested thread');
        }

        return $this->decide(
            $threadId,
            $this->jobs->find($threadId),
            $now,
            enqueueOnly: true,
            explicit: false,
            currentThread: $thread,
        );
    }

    /** @param array<string,mixed> $job */
    public function forGeneration(array $job, DateTimeImmutable $now): ThreadIntelligenceEligibilityResult
    {
        $threadId = filter_var($job['thread_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($threadId === false) {
            throw new InvalidArgumentException('generation eligibility requires a positive thread_id');
        }

        // Never trust the claim-time array for mutable pause/cadence/evidence
        // state. Current persisted values win; the passed row is only a safe
        // fallback for a caller observing a concurrently removed thread row.
        $current = $this->jobs->find((int) $threadId);
        $effective = $current === null ? $job : array_replace($job, $current);
        $explicit = ($effective['trigger_code'] ?? null) === ThreadIntelligenceQueue::TRIGGER_CURATOR_REFRESH;
        return $this->decide((int) $threadId, $effective, $now, enqueueOnly: false, explicit: $explicit);
    }

    public function forExplicitRefresh(int $threadId, DateTimeImmutable $now): ThreadIntelligenceEligibilityResult
    {
        return $this->decide($threadId, $this->jobs->find($threadId), $now, enqueueOnly: false, explicit: true);
    }

    /** @param array<string,mixed>|null $job */
    private function decide(
        int $threadId,
        ?array $job,
        DateTimeImmutable $now,
        bool $enqueueOnly,
        bool $explicit,
        ?array $currentThread = null,
    ): ThreadIntelligenceEligibilityResult {
        if (!$this->flags->enabled('community_memory')) {
            return $this->denied('community_memory_disabled', 'Thread memory is disabled');
        }
        if (!$this->flags->enabled('automated_context')) {
            return $this->denied('automated_context_disabled', 'Automatic context is disabled');
        }

        $thread = $currentThread ?? $this->db->fetch(
            'SELECT t.id, t.is_deleted, t.is_pending, b.visibility
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             WHERE t.id = ?',
            [$threadId],
        );
        $publicMessage = 'Refresh is available only for eligible public threads';
        if ($thread === null) {
            return $this->denied('thread_not_found', $publicMessage);
        }
        if ($thread['visibility'] !== 'public') {
            return $this->denied('board_not_public', $publicMessage);
        }
        if ((int) $thread['is_deleted'] !== 0) {
            return $this->denied('thread_deleted', $publicMessage);
        }
        if ((int) $thread['is_pending'] !== 0) {
            return $this->denied('thread_pending', $publicMessage);
        }

        $checkpoint = $this->positiveIntOrNull($job['last_processed_post_id'] ?? null);
        $counts = $this->eligiblePostCounts($threadId, $checkpoint);
        if ($checkpoint === null && $counts['total'] < self::INITIAL_POST_THRESHOLD) {
            return $this->denied('initial_post_threshold', 'Not enough eligible posts for automatic refresh');
        }

        $reconcile = (int) ($job['reconcile_required'] ?? 0) === 1;
        if ($checkpoint !== null && !$explicit && !$reconcile && $counts['after_checkpoint'] < self::INCREMENTAL_POST_THRESHOLD) {
            return $this->denied('post_delta_threshold', 'Not enough new eligible posts for automatic refresh');
        }

        // Content writes remain durable across a temporary operational outage;
        // the worker/explicit paths below perform the provider-time deferrals.
        if ($enqueueOnly) {
            return $this->allowed();
        }

        if (in_array($job['state'] ?? null, ['dead', 'review_required'], true)) {
            return $this->denied('terminal_state', 'Automatic refresh requires administrator attention');
        }
        if ((int) ($job['automation_paused'] ?? 0) === 1) {
            return $this->denied('automation_paused', 'Automatic refresh is paused for this thread');
        }

        $pause = $this->settings->generationPause();
        if ($pause['corrupt']) {
            return $this->denied('generation_pause_invalid', 'Automatic refresh is paused while site settings are checked');
        }
        if ($pause['paused']) {
            return $this->denied('generation_paused', 'Automatic refresh is paused by the site');
        }
        if (!$this->config->providerReady()) {
            return $this->denied('credentials_missing', 'Automatic refresh is unavailable until the provider is configured');
        }

        $provider = $this->settings->providerHealth();
        if ($provider['corrupt']) {
            return $this->denied('provider_health_invalid', 'Automatic refresh is paused while the provider configuration is checked');
        }
        if ($provider['blocked']) {
            return $this->denied('provider_blocked', 'Automatic refresh is paused while the provider configuration is checked');
        }

        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));
        $budget = $this->budget->status($nowUtc);
        if ($budget['corrupt']) {
            return $this->denied('budget_invalid', 'Automatic refresh is paused while the site budget is checked');
        }
        if ($budget['exhausted']) {
            return $this->denied(
                'budget_exhausted',
                'Daily refresh capacity has been reached',
                $this->parseUtc($budget['next_reset_at']),
            );
        }

        $lastGeneratedAt = $this->parseNullableUtc($job['last_generated_at'] ?? null);
        if ($lastGeneratedAt !== null) {
            $next = $lastGeneratedAt->modify('+1 hour');
            if ($nowUtc < $next) {
                $message = $explicit
                    ? 'Refresh available after ' . $next->setTimezone($now->getTimezone())->format('Y-m-d H:i:s T')
                    : 'One successful refresh per hour is allowed';
                return $this->denied('hourly_limit', $message, $next);
            }
        }

        if (!$explicit) {
            $dueAt = $this->parseNullableUtc($job['due_at'] ?? null);
            if ($dueAt !== null && $nowUtc < $dueAt) {
                return $this->denied('quiet_window', 'Waiting for recent activity to settle', $dueAt);
            }
        }

        return $this->allowed();
    }

    /** @return array{total:int,after_checkpoint:int} */
    private function eligiblePostCounts(int $threadId, ?int $checkpoint): array
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN id > :checkpoint THEN 1 ELSE 0 END) AS after_checkpoint
             FROM posts
             WHERE thread_id = :thread_id AND is_deleted = 0 AND is_pending = 0',
            ['checkpoint' => $checkpoint ?? 0, 'thread_id' => $threadId],
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'after_checkpoint' => (int) ($row['after_checkpoint'] ?? 0),
        ];
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int) $parsed;
    }

    private function parseNullableUtc(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? $this->parseUtc($value) : null;
    }

    private function parseUtc(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . self::UTC_FORMAT, $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $parsed->format(self::UTC_FORMAT) !== $value) {
            throw new InvalidArgumentException('stored Thread Intelligence time is invalid');
        }
        return $parsed;
    }

    private function allowed(): ThreadIntelligenceEligibilityResult
    {
        return new ThreadIntelligenceEligibilityResult(true, 'eligible', 'Eligible');
    }

    private function denied(string $code, string $message, ?DateTimeImmutable $next = null): ThreadIntelligenceEligibilityResult
    {
        return new ThreadIntelligenceEligibilityResult(false, $code, $message, $next);
    }
}
