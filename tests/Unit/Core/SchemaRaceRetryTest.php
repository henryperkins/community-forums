<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SchemaRaceRetry;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The migration runner's answer to Vitess's asynchronous schema propagation
 * (docs/runbooks/deployment-cloudflare.md §3): a DML statement issued right
 * after an ALTER can be rejected at planning time because vtgate has not yet
 * refreshed its schema cache. The policy re-sends such statements after a
 * pause, and only those -- everything else must fail exactly as before.
 */
final class SchemaRaceRetryTest extends TestCase
{
    /** @return array<string,array{string,bool}> */
    public static function messages(): array
    {
        return [
            'vtgate planner 1105 (form recorded in the runbook)' => [
                "SQLSTATE[HY000]: General error: 1105 column 'status_changed_at' not found in table 'threads'",
                true,
            ],
            'vtgate semantic analyser' => ['VT03019: symbol status_changed_at not found', true],
            'vtgate table lookup' => ["table 'thread_status_history' not found", true],
            'mysql-compatible wording' => [
                "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status' in 'field list'",
                true,
            ],
            'duplicate column is the replay symptom, never retried' => [
                "SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'status'",
                false,
            ],
            'deadlock' => ['SQLSTATE[40001]: Serialization failure: 1213 Deadlock found', false],
            'syntax error' => ['SQLSTATE[42000]: Syntax error or access violation: 1064', false],
        ];
    }

    #[DataProvider('messages')]
    public function test_classifies_only_stale_schema_errors_as_races(string $message, bool $expected): void
    {
        self::assertSame($expected, SchemaRaceRetry::isSchemaRace(new PDOException($message)));
    }

    public function test_retries_a_race_until_the_statement_plans(): void
    {
        $sleeps = [];
        $policy = new SchemaRaceRetry(60, 250, static function (int $ms) use (&$sleeps): void {
            $sleeps[] = $ms;
        });

        $attempts = 0;
        $result = $policy->run(static function () use (&$attempts): string {
            $attempts++;
            if ($attempts < 3) {
                throw new PDOException("SQLSTATE[HY000]: General error: 1105 column 'x' not found in table 't'");
            }
            return 'planned';
        });

        self::assertSame('planned', $result);
        self::assertSame(3, $attempts);
        self::assertSame([250, 250], $sleeps, 'one pause between each failed attempt and the next');
    }

    public function test_gives_up_after_max_attempts_and_rethrows_the_last_failure(): void
    {
        $sleeps = 0;
        $policy = new SchemaRaceRetry(3, 1, static function () use (&$sleeps): void {
            $sleeps++;
        });

        $attempts = 0;
        try {
            $policy->run(static function () use (&$attempts): never {
                $attempts++;
                throw new PDOException("column 'x' not found in table 't' (attempt $attempts)");
            });
            self::fail('expected the exhausted retry to rethrow');
        } catch (PDOException $e) {
            self::assertStringContainsString('(attempt 3)', $e->getMessage());
        }

        self::assertSame(3, $attempts);
        self::assertSame(2, $sleeps);
    }

    public function test_a_failure_that_is_not_a_race_propagates_immediately(): void
    {
        $sleeps = 0;
        $policy = new SchemaRaceRetry(60, 1, static function () use (&$sleeps): void {
            $sleeps++;
        });

        $attempts = 0;
        try {
            $policy->run(static function () use (&$attempts): never {
                $attempts++;
                throw new PDOException("SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'status'");
            });
            self::fail('expected the non-race failure to propagate');
        } catch (PDOException $e) {
            self::assertStringContainsString('Duplicate column', $e->getMessage());
        }

        self::assertSame(1, $attempts);
        self::assertSame(0, $sleeps);
    }

    public function test_inert_policy_makes_a_single_attempt(): void
    {
        $policy = SchemaRaceRetry::none();
        self::assertFalse($policy->isActive());

        $attempts = 0;
        try {
            $policy->run(static function () use (&$attempts): never {
                $attempts++;
                throw new PDOException("column 'x' not found in table 't'");
            });
            self::fail('expected the single attempt to rethrow');
        } catch (PDOException) {
            self::assertSame(1, $attempts);
        }
    }

    public function test_activates_only_for_vitess_servers(): void
    {
        self::assertTrue(SchemaRaceRetry::forServerVersion('8.4.11-Vitess')->isActive());
        self::assertFalse(SchemaRaceRetry::forServerVersion('11.4.2-MariaDB')->isActive());
        self::assertFalse(SchemaRaceRetry::forServerVersion('8.0.36')->isActive());
        self::assertFalse(SchemaRaceRetry::forServerVersion('')->isActive());
    }

    public function test_a_custom_classifier_replaces_the_default(): void
    {
        $policy = new SchemaRaceRetry(2, 1, static fn (int $ms): null => null, static fn (PDOException $e): bool => str_contains($e->getMessage(), 'probe'));

        $attempts = 0;
        $result = $policy->run(static function () use (&$attempts): string {
            $attempts++;
            if ($attempts === 1) {
                throw new PDOException('probe failure');
            }
            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(2, $attempts);
    }
}
