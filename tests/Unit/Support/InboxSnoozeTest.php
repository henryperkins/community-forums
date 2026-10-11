<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\InboxSnooze;
use PHPUnit\Framework\TestCase;

final class InboxSnoozeTest extends TestCase
{
    public function test_database_deadlines_are_compared_as_utc_regardless_of_php_default_timezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        try {
            foreach (['Asia/Tokyo', 'America/Chicago'] as $timezone) {
                date_default_timezone_set($timezone);
                self::assertTrue(InboxSnooze::hidden(gmdate('Y-m-d H:i:s', time() + 3600)), $timezone . ' future UTC deadline');
                self::assertFalse(InboxSnooze::hidden(gmdate('Y-m-d H:i:s', time() - 3600)), $timezone . ' expired UTC deadline');
            }
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_an_exact_deadline_has_expired_and_empty_or_invalid_deadlines_are_visible(): void
    {
        self::assertFalse(InboxSnooze::hidden(gmdate('Y-m-d H:i:s')));
        self::assertFalse(InboxSnooze::hidden(null));
        self::assertFalse(InboxSnooze::hidden(''));
        self::assertFalse(InboxSnooze::hidden('   '));
        self::assertFalse(InboxSnooze::hidden('not-a-date'));
    }

    public function test_manual_hiding_does_not_expire_with_a_missing_or_old_deadline(): void
    {
        self::assertTrue(InboxSnooze::hidden(null, true));
        self::assertTrue(InboxSnooze::hidden('2000-01-01 00:00:00', true));
    }
}
