<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\PresenceConfig;
use PHPUnit\Framework\TestCase;

/**
 * Presence configuration validation (ADR 0031, review finding 18).
 *
 * The six knobs used to be read with a bare `(int)` cast, so any typo became a
 * silent zero — an empty PRESENCE_ONLINE_WINDOW_SECONDS meant "seen within 0
 * seconds", i.e. nobody is ever online, with nothing anywhere saying why. The
 * two windows are also not independent, and no code enforced the relationship.
 */
final class PresenceConfigTest extends TestCase
{
    public function test_defaults_apply_when_nothing_is_configured(): void
    {
        $config = PresenceConfig::fromArray([]);

        self::assertSame(60, $config->heartbeatSeconds());
        self::assertSame(300, $config->onlineWindowSeconds());
        self::assertSame(900, $config->awayWindowSeconds());
        self::assertSame(200, $config->rosterMax());
        self::assertSame(5, $config->railLimit());
        self::assertSame(12, $config->pageSize());
        self::assertSame([], $config->warnings());
    }

    public function test_integer_shaped_strings_are_accepted(): void
    {
        // Env::get() hands back strings; the config block is not pre-cast.
        $config = PresenceConfig::fromArray(['online_window_seconds' => '120', 'rail_limit' => ' 7 ']);

        self::assertSame(120, $config->onlineWindowSeconds());
        self::assertSame(7, $config->railLimit());
        self::assertSame([], $config->warnings());
    }

    public function test_a_non_numeric_value_falls_back_and_warns_without_echoing_it(): void
    {
        $config = PresenceConfig::fromArray(['online_window_seconds' => 'banana']);

        self::assertSame(300, $config->onlineWindowSeconds());
        self::assertCount(1, $config->warnings());
        self::assertStringContainsString('presence.online_window_seconds', $config->warnings()[0]);
        // A mis-pasted value must not be reflected back into an operator-visible
        // warning; the setting is named, the value never is.
        self::assertStringNotContainsString('banana', $config->warnings()[0]);
    }

    public function test_an_empty_string_does_not_become_a_silent_zero(): void
    {
        $config = PresenceConfig::fromArray(['online_window_seconds' => '']);

        self::assertSame(300, $config->onlineWindowSeconds());
        self::assertNotSame([], $config->warnings());
    }

    public function test_out_of_range_values_are_replaced_not_clamped(): void
    {
        $config = PresenceConfig::fromArray(['page_size' => 100000, 'rail_limit' => 0]);

        self::assertSame(12, $config->pageSize());
        self::assertSame(5, $config->railLimit());
        self::assertCount(2, $config->warnings());
    }

    public function test_a_heartbeat_longer_than_the_online_window_is_rejected_as_a_pair(): void
    {
        // The failure this prevents: the row is allowed to go staler than the
        // window that reads it, so an actively browsing member flickers out of
        // the roster between heartbeats.
        $config = PresenceConfig::fromArray([
            'heartbeat_seconds' => 600,
            'online_window_seconds' => 300,
        ]);

        self::assertSame(60, $config->heartbeatSeconds());
        self::assertSame(300, $config->onlineWindowSeconds());
        self::assertStringContainsString('heartbeat_seconds', implode(' ', $config->warnings()));
    }

    public function test_an_away_window_shorter_than_the_online_window_is_rejected(): void
    {
        $config = PresenceConfig::fromArray([
            'online_window_seconds' => 600,
            'away_window_seconds' => 120,
        ]);

        self::assertSame(900, $config->awayWindowSeconds());
        self::assertGreaterThanOrEqual($config->onlineWindowSeconds(), $config->awayWindowSeconds());
    }

    public function test_the_away_repair_cannot_manufacture_the_pair_the_heartbeat_rule_rejects(): void
    {
        // Three knobs at once. The heartbeat rule used to run first and pass
        // against the operator's own online window (2000); the away repair then
        // lowered that window to 300 behind its back, shipping heartbeat 1000 >
        // online 300 — the exact state the first rule exists to prevent, with no
        // warning naming it.
        $config = PresenceConfig::fromArray([
            'heartbeat_seconds' => 1000,
            'online_window_seconds' => 2000,
            'away_window_seconds' => 1500,
        ]);

        self::assertLessThanOrEqual($config->onlineWindowSeconds(), $config->heartbeatSeconds());
        self::assertLessThanOrEqual($config->awayWindowSeconds(), $config->onlineWindowSeconds());
        // And every replaced knob is named, so the dashboard cannot under-report.
        $warnings = implode(' | ', $config->warnings());
        self::assertStringContainsString('away_window_seconds', $warnings);
        self::assertStringContainsString('online_window_seconds', $warnings);
        self::assertStringContainsString('heartbeat_seconds', $warnings);
    }

    public function test_a_rail_limit_or_page_size_beyond_the_roster_cap_is_rejected(): void
    {
        $config = PresenceConfig::fromArray([
            'roster_max' => 10,
            'rail_limit' => 40,
            'page_size' => 50,
        ]);

        self::assertSame(10, $config->rosterMax());
        self::assertSame(5, $config->railLimit());
        self::assertSame(12, $config->pageSize());
    }
}
