<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceRetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThreadIntelligenceRetryPolicyTest extends TestCase
{
    /** @return iterable<string,array{int,int}> */
    public static function transientSchedule(): iterable
    {
        yield 'one minute' => [1, 60];
        yield 'five minutes' => [2, 300];
        yield 'thirty minutes' => [3, 1_800];
        yield 'two hours' => [4, 7_200];
        yield 'six hours' => [5, 21_600];
    }

    #[DataProvider('transientSchedule')]
    public function test_transient_delays_use_the_pinned_schedule_with_deterministic_positive_jitter(
        int $retryNumber,
        int $base,
    ): void {
        $first = ThreadIntelligenceRetryPolicy::transientDelaySeconds(17, $retryNumber);
        $again = ThreadIntelligenceRetryPolicy::transientDelaySeconds(17, $retryNumber);

        self::assertSame($first, $again);
        self::assertGreaterThan($base, $first, 'jitter is positive rather than a negative early retry');
        self::assertLessThanOrEqual($base + max(1, (int) floor($base * 0.10)), $first);
        self::assertNotSame(
            $first,
            ThreadIntelligenceRetryPolicy::transientDelaySeconds(18, $retryNumber),
            'the stable thread key spreads neighboring jobs',
        );
    }

    public function test_retry_after_only_extends_the_computed_delay_and_is_capped_at_one_day(): void
    {
        $computed = ThreadIntelligenceRetryPolicy::transientDelaySeconds(41, 1);

        self::assertSame($computed, ThreadIntelligenceRetryPolicy::transientDelaySeconds(41, 1, $computed - 1));
        self::assertSame(3_600, ThreadIntelligenceRetryPolicy::transientDelaySeconds(41, 1, 3_600));
        self::assertSame(86_400, ThreadIntelligenceRetryPolicy::transientDelaySeconds(41, 1, 999_999));
        self::assertSame($computed, ThreadIntelligenceRetryPolicy::transientDelaySeconds(41, 1, -5));
    }

    public function test_jitter_stays_positive_for_the_largest_valid_integer_thread_id(): void
    {
        $delay = ThreadIntelligenceRetryPolicy::transientDelaySeconds(PHP_INT_MAX, 1);

        self::assertGreaterThan(60, $delay);
        self::assertLessThanOrEqual(66, $delay);
    }

    public function test_initial_call_plus_five_transient_retries_then_dead_on_the_sixth_failed_call(): void
    {
        foreach (range(0, 4) as $priorRetries) {
            $decision = ThreadIntelligenceRetryPolicy::decision(
                ThreadIntelligenceFailureCode::TRANSPORT,
                1,
                $priorRetries,
            );
            self::assertSame('retry', $decision['job_state']);
            self::assertSame('retry', $decision['generation_status']);
            self::assertSame($priorRetries + 1, $decision['retry_number']);
            self::assertTrue($decision['increment_attempt']);
        }

        $terminal = ThreadIntelligenceRetryPolicy::decision(
            ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE,
            1,
            5,
        );
        self::assertSame('dead', $terminal['job_state']);
        self::assertSame('dead', $terminal['generation_status']);
        self::assertNull($terminal['delay_seconds']);
    }

    /** @return iterable<string,array{string}> */
    public static function oneRetryFailures(): iterable
    {
        yield 'truncated output' => [ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED];
        yield 'schema invalid' => [ThreadIntelligenceFailureCode::SCHEMA_INVALID];
        yield 'complete response validation failed' => [ThreadIntelligenceFailureCode::VALIDATION_FAILED];
    }

    #[DataProvider('oneRetryFailures')]
    public function test_truncation_and_complete_response_validation_retry_once_then_require_review(string $code): void
    {
        $first = ThreadIntelligenceRetryPolicy::decision($code, 1, 0);
        self::assertSame('retry', $first['job_state']);
        self::assertSame('retry', $first['generation_status']);
        self::assertSame(300, $first['delay_seconds']);
        self::assertTrue($first['increment_attempt']);

        $second = ThreadIntelligenceRetryPolicy::decision($code, 2, 1);
        self::assertSame('review_required', $second['job_state']);
        self::assertSame('review_required', $second['generation_status']);
        self::assertNull($second['delay_seconds']);
    }

    public function test_terminal_and_stale_mappings_are_safe_and_explicit(): void
    {
        $flagged = ThreadIntelligenceRetryPolicy::decision(ThreadIntelligenceFailureCode::MODERATION_FLAGGED, 1, 0);
        self::assertSame('review_required', $flagged['job_state']);
        self::assertSame('rejected', $flagged['generation_status']);
        self::assertFalse($flagged['latch_provider']);

        foreach ([ThreadIntelligenceFailureCode::AUTHENTICATION, ThreadIntelligenceFailureCode::INVALID_MODEL] as $code) {
            $blocked = ThreadIntelligenceRetryPolicy::decision($code, 1, 0);
            self::assertSame('review_required', $blocked['job_state']);
            self::assertSame('review_required', $blocked['generation_status']);
            self::assertTrue($blocked['latch_provider']);
        }

        $tooLarge = ThreadIntelligenceRetryPolicy::decision(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE, 4, 0);
        self::assertSame('review_required', $tooLarge['job_state']);
        self::assertSame('review_required', $tooLarge['generation_status']);

        $stale = ThreadIntelligenceRetryPolicy::decision(ThreadIntelligenceFailureCode::STALE_EVIDENCE, 1, 0);
        self::assertSame('queued', $stale['job_state']);
        self::assertSame('stale', $stale['generation_status']);
        self::assertSame(0, $stale['delay_seconds']);
        self::assertFalse($stale['increment_attempt']);
    }

    /** @return iterable<string,array{string}> */
    public static function deferrals(): iterable
    {
        yield 'missing credential' => ['missing_credential'];
        yield 'community memory flag' => ['community_memory_disabled'];
        yield 'automatic context flag' => ['automated_context_disabled'];
        yield 'global pause' => ['generation_paused'];
        yield 'per-thread pause' => ['automation_paused'];
        yield 'provider latch' => ['provider_blocked'];
        yield 'hourly cap' => ['hourly_limit'];
        yield 'daily budget' => ['budget_exhausted'];
    }

    #[DataProvider('deferrals')]
    public function test_site_and_policy_deferrals_never_increment_failure_attempts(string $code): void
    {
        $decision = ThreadIntelligenceRetryPolicy::decision($code, 0, 0);

        self::assertSame('deferred', $decision['job_state']);
        self::assertSame('retry', $decision['generation_status']);
        self::assertFalse($decision['increment_attempt']);
        self::assertFalse($decision['latch_provider']);
    }
}
