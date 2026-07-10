<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/** Deterministic, bounded retry and terminal-state policy. */
final class ThreadIntelligenceRetryPolicy
{
    /** @var list<int> */
    private const TRANSIENT_DELAYS = [60, 300, 1_800, 7_200, 21_600];

    private const DEFERRALS = [
        'missing_credential',
        'credentials_missing',
        'community_memory_disabled',
        'automated_context_disabled',
        'generation_paused',
        'generation_pause_invalid',
        'automation_paused',
        'provider_blocked',
        'provider_health_invalid',
        'hourly_limit',
        'quiet_window',
        'budget_exhausted',
        'budget_invalid',
    ];

    private const TRANSIENT_FAILURES = [
        ThreadIntelligenceFailureCode::TRANSPORT,
        ThreadIntelligenceFailureCode::RATE_LIMITED,
        ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE,
        ThreadIntelligenceFailureCode::MODERATION_TRANSPORT,
    ];

    private function __construct()
    {
    }

    public static function transientDelaySeconds(
        int $threadId,
        int $retryNumber,
        ?int $retryAfterSeconds = null,
    ): int {
        if ($threadId < 1 || $retryNumber < 1 || $retryNumber > count(self::TRANSIENT_DELAYS)) {
            throw new InvalidArgumentException('transient retry requires a positive thread and retry number one through five');
        }

        $base = self::TRANSIENT_DELAYS[$retryNumber - 1];
        $maximumJitter = max(1, (int) floor($base * 0.10));
        // The unsigned CRC is stable and bounded before modulo; multiplying a
        // BIGINT-shaped ID can overflow PHP's integer and produce early jitter.
        $spread = (int) sprintf('%u', crc32((string) $threadId));
        $jitter = 1 + ($spread % $maximumJitter);
        $computed = $base + $jitter;

        if ($retryAfterSeconds !== null && $retryAfterSeconds >= 0) {
            $computed = max($computed, min(86_400, $retryAfterSeconds));
        }
        return min(86_400, $computed);
    }

    /**
     * @return array{job_state:string,generation_status:string,delay_seconds:?int,retry_number:int,
     *               increment_attempt:bool,latch_provider:bool}
     */
    public static function decision(string $failureCode, int $sameFailureCount, int $transientRetryCount): array
    {
        if ($sameFailureCount < 0 || $transientRetryCount < 0) {
            throw new InvalidArgumentException('failure counters must be nonnegative');
        }

        if (in_array($failureCode, self::DEFERRALS, true)) {
            return self::result('deferred', 'retry', null, $transientRetryCount, false, false);
        }

        if ($failureCode === ThreadIntelligenceFailureCode::STALE_EVIDENCE) {
            return self::result('queued', 'stale', 0, $transientRetryCount, false, false);
        }

        if (in_array($failureCode, [
            ThreadIntelligenceFailureCode::AUTHENTICATION,
            ThreadIntelligenceFailureCode::INVALID_MODEL,
        ], true)) {
            return self::result('review_required', 'review_required', null, $transientRetryCount, true, true);
        }

        if ($failureCode === ThreadIntelligenceFailureCode::MODERATION_FLAGGED) {
            return self::result('review_required', 'rejected', null, $transientRetryCount, true, false);
        }

        if ($failureCode === ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE) {
            return self::result('review_required', 'review_required', null, $transientRetryCount, true, false);
        }

        if (in_array($failureCode, [
            ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED,
            ThreadIntelligenceFailureCode::SCHEMA_INVALID,
            ThreadIntelligenceFailureCode::VALIDATION_FAILED,
        ], true)) {
            return $sameFailureCount <= 1
                ? self::result('retry', 'retry', 300, $transientRetryCount + 1, true, false)
                : self::result('review_required', 'review_required', null, $transientRetryCount, true, false);
        }

        if (in_array($failureCode, self::TRANSIENT_FAILURES, true)) {
            if ($transientRetryCount >= count(self::TRANSIENT_DELAYS)) {
                return self::result('dead', 'dead', null, $transientRetryCount, true, false);
            }
            return self::result('retry', 'retry', null, $transientRetryCount + 1, true, false);
        }

        throw new InvalidArgumentException('unknown thread-intelligence retry decision code');
    }

    public static function isDeferral(string $code): bool
    {
        return in_array($code, self::DEFERRALS, true);
    }

    public static function isTransientFailure(string $code): bool
    {
        return in_array($code, self::TRANSIENT_FAILURES, true);
    }

    /**
     * @return array{job_state:string,generation_status:string,delay_seconds:?int,retry_number:int,
     *               increment_attempt:bool,latch_provider:bool}
     */
    private static function result(
        string $jobState,
        string $generationStatus,
        ?int $delaySeconds,
        int $retryNumber,
        bool $incrementAttempt,
        bool $latchProvider,
    ): array {
        return [
            'job_state' => $jobState,
            'generation_status' => $generationStatus,
            'delay_seconds' => $delaySeconds,
            'retry_number' => $retryNumber,
            'increment_attempt' => $incrementAttempt,
            'latch_provider' => $latchProvider,
        ];
    }
}
