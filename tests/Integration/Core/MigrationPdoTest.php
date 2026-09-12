<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Database;
use App\Core\MigrationPdo;
use App\Core\MigrationStatement;
use App\Core\SchemaRaceRetry;
use PDOException;
use Tests\Support\TestCase;

/**
 * MigrationPdo is what `bin/console migrate*` connects through. Every entry
 * point a migration can use must run under the connection's SchemaRaceRetry
 * policy, and the policy must be inert on a non-Vitess server. The probes use a
 * session-scoped TEMPORARY table on the migration connection itself, so
 * nothing leaks into the shared test schema.
 */
final class MigrationPdoTest extends TestCase
{
    private function migrationPdo(?SchemaRaceRetry $retry = null): MigrationPdo
    {
        return (new Database($GLOBALS['__RB_TEST_DBCONFIG']))->migrationPdo($retry);
    }

    public function test_policy_is_chosen_from_the_server_version(): void
    {
        $pdo = $this->migrationPdo();
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

        self::assertSame(
            stripos($version, 'vitess') !== false,
            $pdo->retry()->isActive(),
            "retry should be active only on Vitess (server reports $version)",
        );
    }

    public function test_every_statement_entry_point_runs_under_the_retry_policy(): void
    {
        $sleeps = 0;
        $policy = new SchemaRaceRetry(
            3,
            0,
            static function () use (&$sleeps): void {
                $sleeps++;
            },
            static fn (PDOException $e): bool => str_contains($e->getMessage(), 'Duplicate entry'),
        );
        $pdo = $this->migrationPdo($policy);
        $pdo->exec('CREATE TEMPORARY TABLE rb_schema_race_probe (id INT NOT NULL PRIMARY KEY)');
        $pdo->exec('INSERT INTO rb_schema_race_probe (id) VALUES (1)');

        // exec(): the duplicate insert is classified as retryable, so it is
        // attempted maxAttempts times (two pauses) before the failure surfaces.
        $this->assertRetried($sleeps, 2, static fn () => $pdo->exec('INSERT INTO rb_schema_race_probe (id) VALUES (1)'));

        // query()
        $this->assertRetried($sleeps, 4, static fn () => $pdo->query('INSERT INTO rb_schema_race_probe (id) VALUES (1)'));

        // prepare() hands back the retrying statement class, and its execute()
        // shares the same policy.
        $stmt = $pdo->prepare('INSERT INTO rb_schema_race_probe (id) VALUES (?)');
        self::assertInstanceOf(MigrationStatement::class, $stmt);
        $this->assertRetried($sleeps, 6, static fn () => $stmt->execute([1]));

        // A failure the classifier rejects is not retried at all.
        $this->assertRetried($sleeps, 6, static fn () => $pdo->exec('SELECT no_such_column FROM rb_schema_race_probe'));
    }

    /** @param callable():mixed $operation */
    private function assertRetried(int &$sleeps, int $expectedSleepsAfter, callable $operation): void
    {
        try {
            $operation();
            self::fail('expected the probe statement to fail');
        } catch (PDOException $e) {
            self::assertSame($expectedSleepsAfter, $sleeps, $e->getMessage());
        }
    }
}
