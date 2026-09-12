<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Validated presence configuration (ADR 0031).
 *
 * The six presence knobs were previously read straight out of `config/config.php`
 * with a bare `(int)` cast, which turned every typo into a silent zero: an empty
 * PRESENCE_ONLINE_WINDOW_SECONDS meant "seen within 0 seconds", i.e. nobody is
 * ever online, with nothing anywhere to say why. Worse, the two windows are not
 * independent — a heartbeat longer than the online window makes an actively
 * browsing member flicker out of the roster between writes, because the row is
 * allowed to go staler than the window that reads it.
 *
 * Following {@see \App\Service\ThreadIntelligence\ThreadIntelligenceConfig}: an
 * invalid or out-of-range value is REPLACED with its named default rather than
 * clamped into range, and surfaces as a bounded warning that names the setting
 * without echoing the raw value. The cross-field rules are checked afterwards,
 * once every individual value is known good.
 */
final class PresenceConfig
{
    public const DEFAULT_HEARTBEAT_SECONDS = 60;
    public const DEFAULT_ONLINE_WINDOW_SECONDS = 300;
    public const DEFAULT_AWAY_WINDOW_SECONDS = 900;
    public const DEFAULT_ROSTER_MAX = 200;
    public const DEFAULT_RAIL_LIMIT = 5;
    public const DEFAULT_PAGE_SIZE = 12;

    /** @param list<string> $warnings */
    private function __construct(
        private int $heartbeatSeconds,
        private int $onlineWindowSeconds,
        private int $awayWindowSeconds,
        private int $rosterMax,
        private int $railLimit,
        private int $pageSize,
        private array $warnings,
    ) {
    }

    /** @param array<string,mixed> $config the `presence` config block */
    public static function fromArray(array $config): self
    {
        $warnings = [];

        $heartbeat = self::intInRange($config, 'heartbeat_seconds', 5, 3600, self::DEFAULT_HEARTBEAT_SECONDS, $warnings);
        $online = self::intInRange($config, 'online_window_seconds', 30, 86400, self::DEFAULT_ONLINE_WINDOW_SECONDS, $warnings);
        $away = self::intInRange($config, 'away_window_seconds', 30, 86400, self::DEFAULT_AWAY_WINDOW_SECONDS, $warnings);
        $rosterMax = self::intInRange($config, 'roster_max', 10, 5000, self::DEFAULT_ROSTER_MAX, $warnings);
        $railLimit = self::intInRange($config, 'rail_limit', 1, 50, self::DEFAULT_RAIL_LIMIT, $warnings);
        $pageSize = self::intInRange($config, 'page_size', 1, 200, self::DEFAULT_PAGE_SIZE, $warnings);

        // Cross-field rules. Each one names the *pair*, because reporting a single
        // setting would be misleading: neither value is wrong on its own.
        //
        // ORDER MATTERS. The away repair below can lower $online, so it has to run
        // BEFORE the heartbeat rule — otherwise a three-knob misconfiguration
        // (heartbeat 1000 / online 2000 / away 1500) passes the heartbeat check
        // against the operator's 2000, then has $online replaced with 300 behind
        // that rule's back, and ships heartbeat 1000 > online 300: exactly the
        // pair the first rule exists to reject, with no warning naming it.
        if ($away < $online) {
            $warnings[] = 'presence.away_window_seconds is shorter than presence.online_window_seconds, so the'
                . ' away band would be empty; using default ' . self::DEFAULT_AWAY_WINDOW_SECONDS . '.';
            $away = self::DEFAULT_AWAY_WINDOW_SECONDS;
            if ($away < $online) {
                // A second operator-set knob is being replaced: say so, or the
                // dashboard under-reports what actually changed.
                $warnings[] = 'presence.online_window_seconds no longer fits inside the away window;'
                    . ' using default ' . self::DEFAULT_ONLINE_WINDOW_SECONDS . '.';
                $online = self::DEFAULT_ONLINE_WINDOW_SECONDS;
            }
        }
        if ($heartbeat > $online) {
            $warnings[] = 'presence.heartbeat_seconds is longer than presence.online_window_seconds, so members'
                . ' would drop out of the roster while still browsing; using defaults '
                . self::DEFAULT_HEARTBEAT_SECONDS . '/' . self::DEFAULT_ONLINE_WINDOW_SECONDS . '.';
            $heartbeat = self::DEFAULT_HEARTBEAT_SECONDS;
            $online = self::DEFAULT_ONLINE_WINDOW_SECONDS;
        }
        if ($railLimit > $rosterMax) {
            $warnings[] = 'presence.rail_limit exceeds presence.roster_max, so the rail could never fill;'
                . ' using default ' . self::DEFAULT_RAIL_LIMIT . '.';
            $railLimit = self::DEFAULT_RAIL_LIMIT;
        }
        if ($pageSize > $rosterMax) {
            $warnings[] = 'presence.page_size exceeds presence.roster_max, so a page could never fill;'
                . ' using default ' . self::DEFAULT_PAGE_SIZE . '.';
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        return new self($heartbeat, $online, $away, $rosterMax, $railLimit, $pageSize, $warnings);
    }

    /**
     * Accepts an int or an integer-shaped string inside [$min,$max]; anything
     * else falls back to $default with a bounded warning naming the setting.
     *
     * @param array<string,mixed> $config
     * @param list<string> $warnings
     */
    private static function intInRange(array $config, string $key, int $min, int $max, int $default, array &$warnings): int
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        $int = null;
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            $int = (int) trim($value);
        }

        if ($int === null || $int < $min || $int > $max) {
            $warnings[] = "presence.$key is invalid or out of range ($min-$max); using default $default";
            return $default;
        }

        return $int;
    }

    public function heartbeatSeconds(): int
    {
        return $this->heartbeatSeconds;
    }

    /** Seen within this many seconds ⇒ "here now" (the leaf dot). */
    public function onlineWindowSeconds(): int
    {
        return $this->onlineWindowSeconds;
    }

    /**
     * The OUTER band. Seen within this many seconds but outside the online
     * window ⇒ "stepped away"; outside it entirely ⇒ not on the roster at all.
     */
    public function awayWindowSeconds(): int
    {
        return $this->awayWindowSeconds;
    }

    /** Hard ceiling on rows fetched for one roster read — see ADR 0031 on the `N+` count. */
    public function rosterMax(): int
    {
        return $this->rosterMax;
    }

    /** Rows the board rail shows before it defers to "+N more". */
    public function railLimit(): int
    {
        return $this->railLimit;
    }

    /** Rows per page on /users-online. */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
