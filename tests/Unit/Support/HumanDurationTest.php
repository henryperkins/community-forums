<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * human_duration(): rate-limit waits shown to people ("wait 3473 second(s)" →
 * "wait about 58 minutes"). Rounds minutes UP so the promise is never shorter
 * than the real wait.
 */
final class HumanDurationTest extends TestCase
{
    public function test_seconds_under_a_minute(): void
    {
        self::assertSame('1 second', human_duration(1));
        self::assertSame('2 seconds', human_duration(2));
        self::assertSame('59 seconds', human_duration(59));
        self::assertSame('1 second', human_duration(0)); // clamped floor
    }

    public function test_minutes_round_up_so_the_promise_is_never_short(): void
    {
        self::assertSame('about 1 minute', human_duration(60));
        self::assertSame('about 2 minutes', human_duration(61));
        self::assertSame('about 58 minutes', human_duration(3473));
    }

    public function test_hours(): void
    {
        self::assertSame('about 1 hour', human_duration(3600));
        self::assertSame('about 1 hour 1 minute', human_duration(3660));
        self::assertSame('about 2 hours', human_duration(7200));
    }
}
