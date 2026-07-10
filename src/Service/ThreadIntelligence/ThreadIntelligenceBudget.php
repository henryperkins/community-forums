<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Conservative daily generation budget (plan Task 4) over the single
 * `thread_intelligence_daily_budget` settings row.
 *
 * A reservation atomically takes one call PLUS the full configured
 * per-request input-token ceiling under SELECT ... FOR UPDATE, so concurrent
 * workers cannot overspend; reconciliation refunds only unused input tokens
 * against actual reported usage. Counters reset at the UTC day boundary.
 * Corrupt stored data fails UNAVAILABLE with an operator warning — spend is
 * never silently reset.
 */
final class ThreadIntelligenceBudget
{
    public const KEY = 'thread_intelligence_daily_budget';

    public function __construct(
        private readonly Database $db,
        private readonly ThreadIntelligenceConfig $config,
    ) {
    }

    /**
     * @return array{date:string, reserved_calls:int, used_calls:int, reserved_input_tokens:int,
     *               used_input_tokens:int, call_limit:int, input_token_limit:int,
     *               exhausted:bool, next_reset_at:string, corrupt:bool}
     */
    public function status(DateTimeImmutable $now): array
    {
        $today = $this->utcDate($now);
        $raw = $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [self::KEY]);

        $counters = ['date' => $today, 'reserved_calls' => 0, 'used_calls' => 0, 'reserved_input_tokens' => 0, 'used_input_tokens' => 0];
        $corrupt = false;
        if ($raw !== false && $raw !== null) {
            $decoded = $this->decodeCounters((string) $raw);
            if ($decoded === null || $decoded['date'] > $today) {
                $corrupt = true;
            } elseif ($decoded['date'] === $today) {
                $counters = $decoded;
            }
            // A prior-day row simply reads as the fresh window above.
        }

        $callsTotal = $counters['reserved_calls'] + $counters['used_calls'];
        $tokensTotal = $counters['reserved_input_tokens'] + $counters['used_input_tokens'];
        $exhausted = $corrupt
            || $callsTotal + 1 > $this->config->dailyCallLimit()
            || $tokensTotal + $this->config->maxInputTokens() > $this->config->dailyInputTokenLimit();

        return $counters + [
            'call_limit' => $this->config->dailyCallLimit(),
            'input_token_limit' => $this->config->dailyInputTokenLimit(),
            'exhausted' => $exhausted,
            'next_reset_at' => $this->nextUtcMidnight($now)->format('Y-m-d H:i:s'),
            'corrupt' => $corrupt,
        ];
    }

    /**
     * @return array{reserved:bool, reservation:?array{date:string, input_tokens:int}, retry_at:?DateTimeImmutable, corrupt:bool}
     */
    public function reserve(DateTimeImmutable $now): array
    {
        $today = $this->utcDate($now);
        $ceiling = $this->config->maxInputTokens();

        return $this->db->transaction(function () use ($now, $today, $ceiling): array {
            $counters = $this->lockCounters($today);
            if ($counters === null) {
                return ['reserved' => false, 'reservation' => null, 'retry_at' => null, 'corrupt' => true];
            }

            $callsTotal = $counters['reserved_calls'] + $counters['used_calls'];
            $tokensTotal = $counters['reserved_input_tokens'] + $counters['used_input_tokens'];
            if ($callsTotal + 1 > $this->config->dailyCallLimit()
                || $tokensTotal + $ceiling > $this->config->dailyInputTokenLimit()) {
                return ['reserved' => false, 'reservation' => null, 'retry_at' => $this->nextUtcMidnight($now), 'corrupt' => false];
            }

            $counters['reserved_calls']++;
            $counters['reserved_input_tokens'] += $ceiling;
            $this->writeCounters($counters);

            return [
                'reserved' => true,
                'reservation' => ['date' => $today, 'input_tokens' => $ceiling],
                'retry_at' => null,
                'corrupt' => false,
            ];
        });
    }

    /**
     * Moves one reserved call to used and refunds the unused input-token
     * difference; exact zero cancels a reservation known not to have crossed
     * the provider boundary, while missing usage conservatively keeps the full
     * reservation used. A prior-day reservation needs no current-counter
     * mutation.
     *
     * @param array{date:string, input_tokens:int} $reservation
     */
    public function reconcile(array $reservation, ?int $actualInputTokens): void
    {
        $this->db->transaction(function () use ($reservation, $actualInputTokens): void {
            $raw = $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ? FOR UPDATE', [self::KEY]);
            if ($raw === false || $raw === null) {
                return;
            }
            $counters = $this->decodeCounters((string) $raw);
            if ($counters === null || $counters['date'] !== $reservation['date']) {
                return; // rolled over (or corrupt — never mutate a window the reservation does not own)
            }

            $reserved = (int) $reservation['input_tokens'];
            if ($actualInputTokens === 0) {
                // The worker uses exact zero only when it can prove no provider
                // call occurred (lost lease or a live pre-egress gate). Refund
                // both reserved dimensions instead of inventing a used call.
                $counters['reserved_calls'] = max(0, $counters['reserved_calls'] - 1);
                $counters['reserved_input_tokens'] = max(0, $counters['reserved_input_tokens'] - $reserved);
                $this->writeCounters($counters);
                return;
            }
            $used = $actualInputTokens === null ? $reserved : max(0, $actualInputTokens);

            $counters['reserved_calls'] = max(0, $counters['reserved_calls'] - 1);
            $counters['used_calls']++;
            $counters['reserved_input_tokens'] = max(0, $counters['reserved_input_tokens'] - $reserved);
            $counters['used_input_tokens'] += $used;
            $this->writeCounters($counters);
        });
    }

    /**
     * Finalizes a crashed worker's committed reservation: actual provider
     * consumption is unknown, so one full reservation moves to used. A
     * prior-day reservation already vanished with its window.
     */
    public function settleAbandoned(DateTimeImmutable $requestedAt, DateTimeImmutable $now): void
    {
        if ($this->utcDate($requestedAt) !== $this->utcDate($now)) {
            return;
        }
        $this->reconcile(['date' => $this->utcDate($requestedAt), 'input_tokens' => $this->config->maxInputTokens()], null);
    }

    // ---- internals -------------------------------------------------------------

    /**
     * Ensures the settings row exists, locks it FOR UPDATE, decodes, and
     * applies UTC rollover. Returns null when stored data is corrupt (spend is
     * never silently reset).
     *
     * @return array{date:string, reserved_calls:int, used_calls:int, reserved_input_tokens:int, used_input_tokens:int}|null
     */
    private function lockCounters(string $today): ?array
    {
        $this->db->run(
            'INSERT INTO settings (`key`, `value`, updated_at)
             VALUES (:key, :value, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE `key` = `key`',
            ['key' => self::KEY, 'value' => json_encode($this->freshCounters($today), JSON_THROW_ON_ERROR)],
        );

        $raw = $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ? FOR UPDATE', [self::KEY]);
        if ($raw === false || $raw === null) {
            return null;
        }

        $decoded = $this->decodeCounters((string) $raw);
        if ($decoded === null) {
            return null;
        }
        if ($decoded['date'] > $today) {
            return null;
        }
        if ($decoded['date'] !== $today) {
            return $this->freshCounters($today);
        }
        return $decoded;
    }

    /** @return array{date:string, reserved_calls:int, used_calls:int, reserved_input_tokens:int, used_input_tokens:int} */
    private function freshCounters(string $date): array
    {
        return ['date' => $date, 'reserved_calls' => 0, 'used_calls' => 0, 'reserved_input_tokens' => 0, 'used_input_tokens' => 0];
    }

    /** @return array{date:string, reserved_calls:int, used_calls:int, reserved_input_tokens:int, used_input_tokens:int}|null */
    private function decodeCounters(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !$this->isCanonicalUtcDate($decoded['date'] ?? null)) {
            return null;
        }
        $counters = ['date' => $decoded['date']];
        foreach (['reserved_calls', 'used_calls', 'reserved_input_tokens', 'used_input_tokens'] as $field) {
            if (!is_int($decoded[$field] ?? null) || $decoded[$field] < 0) {
                return null;
            }
            $counters[$field] = $decoded[$field];
        }
        return $counters;
    }

    private function isCanonicalUtcDate(mixed $value): bool
    {
        if (!is_string($value)
            || preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /** @param array{date:string, reserved_calls:int, used_calls:int, reserved_input_tokens:int, used_input_tokens:int} $counters */
    private function writeCounters(array $counters): void
    {
        $this->db->run(
            'UPDATE settings SET `value` = :value, updated_at = UTC_TIMESTAMP() WHERE `key` = :key',
            ['value' => json_encode($counters, JSON_THROW_ON_ERROR), 'key' => self::KEY],
        );
    }

    private function utcDate(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }

    private function nextUtcMidnight(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->modify('+1 day')->setTime(0, 0, 0);
    }
}
