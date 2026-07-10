<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Tests\Support\TestCase;

/**
 * Pins the conservative daily budget (plan Task 4): atomic one-call +
 * full-input-ceiling reservations under a locked settings row, refund-only
 * reconciliation, UTC rollover, corrupt-state fail-unavailable, and abandoned
 * settlement. The lock proof commits fixtures and uses a second connection.
 */
final class ThreadIntelligenceBudgetTest extends TestCase
{
    private const KEY = 'thread_intelligence_daily_budget';

    private bool $committedFixtures = false;

    private function budget(int $callLimit = 100, int $tokenLimit = 1_000_000, int $ceiling = 32_000): ThreadIntelligenceBudget
    {
        return new ThreadIntelligenceBudget($this->db, ThreadIntelligenceConfig::fromArray([
            'daily_call_limit' => $callLimit,
            'daily_input_token_limit' => $tokenLimit,
            'max_input_tokens' => $ceiling,
        ]));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    /** @return array<string,mixed> */
    private function storedCounters(): array
    {
        return json_decode((string) $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [self::KEY]), true);
    }

    protected function tearDown(): void
    {
        if ($this->committedFixtures) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $preserve = [
                'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
                'capabilities', 'role_capabilities', 'theme_state',
            ];
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!in_array($table, $preserve, true)) {
                    $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $table) . '`');
                }
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->committedFixtures = false;
        }
        parent::tearDown();
    }

    // ---- reservation arithmetic -----------------------------------------------

    public function test_a_reservation_takes_one_call_plus_the_full_input_ceiling(): void
    {
        $result = $this->budget()->reserve($this->now());

        self::assertTrue($result['reserved']);
        self::assertSame('2026-07-10', $result['reservation']['date']);
        self::assertSame(32_000, $result['reservation']['input_tokens']);

        $counters = $this->storedCounters();
        self::assertSame('2026-07-10', $counters['date']);
        self::assertSame(1, $counters['reserved_calls']);
        self::assertSame(0, $counters['used_calls']);
        self::assertSame(32_000, $counters['reserved_input_tokens']);
        self::assertSame(0, $counters['used_input_tokens']);
    }

    public function test_exhausted_call_budget_denies_with_the_next_utc_midnight(): void
    {
        $budget = $this->budget(callLimit: 2);
        self::assertTrue($budget->reserve($this->now())['reserved']);
        self::assertTrue($budget->reserve($this->now())['reserved']);

        $denied = $budget->reserve($this->now());
        self::assertFalse($denied['reserved']);
        self::assertNull($denied['reservation']);
        self::assertSame('2026-07-11 00:00:00', $denied['retry_at']->format('Y-m-d H:i:s'), 'denial reports the next UTC budget window');
        self::assertFalse($denied['corrupt']);

        self::assertSame(2, $this->storedCounters()['reserved_calls'], 'a denial reserves nothing');
    }

    public function test_insufficient_token_headroom_denies_even_with_calls_remaining(): void
    {
        $budget = $this->budget(callLimit: 100, tokenLimit: 3_000, ceiling: 2_000);
        self::assertTrue($budget->reserve($this->now())['reserved'], 'first full ceiling fits');
        self::assertFalse($budget->reserve($this->now())['reserved'], 'a second full ceiling would overspend the daily tokens');
    }

    public function test_reconcile_moves_the_call_to_used_and_refunds_only_unused_tokens(): void
    {
        $budget = $this->budget();
        $reservation = $budget->reserve($this->now())['reservation'];

        $budget->reconcile($reservation, 500);

        $counters = $this->storedCounters();
        self::assertSame(0, $counters['reserved_calls']);
        self::assertSame(1, $counters['used_calls']);
        self::assertSame(0, $counters['reserved_input_tokens']);
        self::assertSame(500, $counters['used_input_tokens'], 'actual usage is recorded; the difference is refunded');
    }

    public function test_missing_usage_keeps_the_conservative_full_reservation_used(): void
    {
        $budget = $this->budget();
        $reservation = $budget->reserve($this->now())['reservation'];

        $budget->reconcile($reservation, null);

        $counters = $this->storedCounters();
        self::assertSame(1, $counters['used_calls']);
        self::assertSame(32_000, $counters['used_input_tokens']);
        self::assertSame(0, $counters['reserved_input_tokens']);
    }

    // ---- UTC rollover -------------------------------------------------------------

    public function test_utc_rollover_resets_counters_before_reserving(): void
    {
        $budget = $this->budget(callLimit: 1);
        self::assertTrue($budget->reserve($this->now())['reserved']);
        self::assertFalse($budget->reserve($this->now())['reserved'], 'day one is exhausted');

        $dayTwo = $this->now()->modify('+1 day');
        $status = $budget->status($dayTwo);
        self::assertSame(0, $status['reserved_calls'], 'status reports the new empty window');
        self::assertFalse($status['exhausted']);

        self::assertTrue($budget->reserve($dayTwo)['reserved'], 'the new UTC day reserves cleanly');
        self::assertSame('2026-07-11', $this->storedCounters()['date']);
    }

    public function test_reconciling_a_prior_day_reservation_after_rollover_is_a_no_op(): void
    {
        $budget = $this->budget();
        $reservation = $budget->reserve($this->now())['reservation'];

        $dayTwo = $this->now()->modify('+1 day');
        self::assertTrue($budget->reserve($dayTwo)['reserved'], 'rollover resets, then reserves for day two');
        $before = $this->storedCounters();

        $budget->reconcile($reservation, 900);
        self::assertSame($before, $this->storedCounters(), 'a prior-day reservation needs no current-counter mutation');
    }

    // ---- abandoned settlement --------------------------------------------------------

    public function test_settle_abandoned_moves_one_same_day_full_reservation_to_used(): void
    {
        $budget = $this->budget();
        $budget->reserve($this->now());

        $budget->settleAbandoned($this->now()->modify('-5 minutes'), $this->now());

        $counters = $this->storedCounters();
        self::assertSame(0, $counters['reserved_calls']);
        self::assertSame(1, $counters['used_calls']);
        self::assertSame(0, $counters['reserved_input_tokens']);
        self::assertSame(32_000, $counters['used_input_tokens'], 'unknown consumption is conservatively kept as used');
    }

    public function test_settle_abandoned_from_a_prior_day_is_a_no_op(): void
    {
        $budget = $this->budget();
        $budget->reserve($this->now());
        $before = $this->storedCounters();

        $budget->settleAbandoned($this->now()->modify('-1 day'), $this->now());
        self::assertSame($before, $this->storedCounters());
    }

    // ---- corrupt state ------------------------------------------------------------------

    public function test_corrupt_budget_data_fails_unavailable_without_resetting_spend(): void
    {
        // settings.value has a json_valid() CHECK, so corruption means valid
        // JSON of the wrong shape — e.g. a stringly-typed counter.
        $corrupt = '{"date":"2026-07-10","reserved_calls":"corrupted","used_calls":0,"reserved_input_tokens":0,"used_input_tokens":0}';
        $this->db->run(
            'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [self::KEY, $corrupt],
        );

        $denied = $this->budget()->reserve($this->now());
        self::assertFalse($denied['reserved']);
        self::assertTrue($denied['corrupt'], 'corruption is an operator warning');
        self::assertSame(
            $corrupt,
            (string) $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [self::KEY]),
            'corrupt spend data is never silently reset',
        );

        self::assertTrue($this->budget()->status($this->now())['corrupt']);
    }

    // ---- concurrency ------------------------------------------------------------------------

    public function test_concurrent_reservations_serialize_on_the_locked_budget_row(): void
    {
        $this->budget()->reserve($this->now());
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->committedFixtures = true;

        // Connection A holds the budget-row lock inside an open transaction.
        $this->pdo->beginTransaction();
        $this->budget()->reserve($this->now());

        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $otherDb->run('SET SESSION innodb_lock_wait_timeout = 1');
        $otherBudget = new ThreadIntelligenceBudget($otherDb, ThreadIntelligenceConfig::fromArray([]));

        try {
            $otherBudget->reserve($this->now());
            self::fail('a concurrent reservation must wait on the row lock (and here, time out)');
        } catch (PDOException $e) {
            self::assertStringContainsString('lock', strtolower($e->getMessage()));
        }

        $this->pdo->rollBack();

        // With the lock released the second connection reserves against the
        // committed counters — no double-spend window existed in between.
        $result = $otherBudget->reserve($this->now());
        self::assertTrue($result['reserved']);
        self::assertSame(2, $this->storedCounters()['reserved_calls'], 'one committed + one new reservation');
    }
}
