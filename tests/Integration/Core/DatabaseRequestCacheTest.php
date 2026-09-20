<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Database;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseRequestCacheTest extends TestCase
{
    public function test_table_scoped_writes_preserve_unrelated_reads_and_invalidate_unknown_dependencies(): void
    {
        $db = $this->databaseWithCursor();
        $db->beginRequestCache();
        self::assertSame('original', $this->metadata($db));
        self::assertSame(0, $this->cursor($db));
        $unknown = fn () => $db->remember('unknown', fn () => (int) $db->fetchValue('SELECT position FROM request_cache_cursors'));
        self::assertSame(0, $unknown());

        $db->run('UPDATE request_cache_cursors SET position = 7', [], ['request_cache_cursors']);
        $db->resetMetrics();
        self::assertSame('original', $this->metadata($db));
        self::assertSame(0, $db->metrics()['queries'], 'An unrelated write must retain the metadata read.');
        self::assertSame(7, $this->cursor($db));
        self::assertSame(7, $unknown());
        self::assertSame(2, $db->metrics()['queries'], 'Affected and unclassified reads must be fetched again.');

        $db->run('UPDATE request_cache_values SET value = ?', ['new metadata']);
        self::assertSame('new metadata', $this->metadata($db), 'Unclassified writes still invalidate every snapshot.');
    }

    #[DataProvider('transactionOutcomes')]
    public function test_scoped_transaction_commit_and_rollback_keep_only_unrelated_snapshots(bool $rollback): void
    {
        $db = $this->databaseWithCursor();
        $db->beginRequestCache();
        self::assertSame('original', $this->metadata($db));
        self::assertSame(0, $this->cursor($db));
        // A scoped transaction must discard a pre-transaction cursor snapshot.
        $db->pdo()->exec('UPDATE request_cache_cursors SET position = 3');
        try {
            $db->transaction(function () use ($db, $rollback): void {
                self::assertSame(3, $this->cursor($db));
                $db->run('UPDATE request_cache_cursors SET position = 7', [], ['request_cache_cursors']);
                self::assertSame(7, $this->cursor($db));
                if ($rollback) {
                    throw new RuntimeException('rollback');
                }
            }, ['request_cache_cursors']);
            self::assertFalse($rollback);
        } catch (RuntimeException $e) {
            self::assertTrue($rollback);
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame($rollback ? 3 : 7, $this->cursor($db));
        $db->resetMetrics();
        self::assertSame('original', $this->metadata($db));
        self::assertSame(0, $db->metrics()['queries']);
    }

    public static function transactionOutcomes(): array
    {
        return ['commit' => [false], 'rollback' => [true]];
    }

    #[DataProvider('transactionOutcomes')]
    public function test_unclassified_nested_writes_cannot_be_undone_by_a_scoped_transaction_cache(bool $rollback): void
    {
        $db = $this->databaseWithCursor();
        $db->beginRequestCache();
        self::assertSame('original', $this->metadata($db));
        try {
            $db->transaction(function () use ($db, $rollback): void {
                $db->transaction(function () use ($db): void {
                    $db->run('UPDATE request_cache_values SET value = ?', ['changed inside']);
                });
                self::assertSame('changed inside', $this->metadata($db));
                if ($rollback) {
                    throw new RuntimeException('rollback');
                }
            }, ['request_cache_cursors']);
            self::assertFalse($rollback);
        } catch (RuntimeException $e) {
            self::assertTrue($rollback);
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame($rollback ? 'original' : 'changed inside', $this->metadata($db));
    }

    public function test_nested_scopes_accumulate_so_outer_rollback_discards_inner_snapshots(): void
    {
        $db = $this->databaseWithCursor();
        $db->beginRequestCache();
        self::assertSame('original', $this->metadata($db));
        try {
            $db->transaction(function () use ($db): void {
                $db->transaction(function () use ($db): void {
                    $db->run('UPDATE request_cache_values SET value = ?', ['changed inside'], ['request_cache_values']);
                }, ['request_cache_values']);
                self::assertSame('changed inside', $this->metadata($db));
                throw new RuntimeException('rollback');
            }, ['request_cache_cursors']);
            self::fail('The outer transaction must roll back.');
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame('original', $this->metadata($db));
    }

    private function databaseWithCursor(): Database
    {
        $db = $this->database();
        $db->run('CREATE TEMPORARY TABLE request_cache_cursors (position INT) ENGINE=InnoDB');
        $db->run('INSERT INTO request_cache_cursors VALUES (0)');
        return $db;
    }

    private function metadata(Database $db): mixed
    {
        return $db->remember('metadata', fn () => $db->fetchValue('SELECT value FROM request_cache_values WHERE id = 1'), ['request_cache_values']);
    }

    private function cursor(Database $db): int
    {
        return $db->remember('cursor', fn () => (int) $db->fetchValue('SELECT position FROM request_cache_cursors'), ['request_cache_cursors']);
    }

    public function test_memoization_is_enabled_only_between_request_boundaries(): void
    {
        $db = new Database([]);
        $loads = 0;
        $load = static function () use (&$loads): int {
            return ++$loads;
        };

        self::assertSame(1, $db->remember('value', $load));
        self::assertSame(2, $db->remember('value', $load));
        $db->beginRequestCache();
        self::assertTrue($db->isRequestCacheActive());
        self::assertSame(3, $db->remember('value', $load));
        self::assertSame(3, $db->remember('value', $load));
        $db->clearRequestCache();
        self::assertSame(4, $db->remember('value', $load));
        $db->beginRequestCache();
        self::assertSame(5, $db->remember('value', $load));
        $db->endRequestCache();
        self::assertFalse($db->isRequestCacheActive());
        self::assertSame(6, $db->remember('value', $load));
        self::assertSame(7, $db->remember('value', $load));
    }

    public function test_null_and_false_are_cached_but_failed_loads_are_retried(): void
    {
        $db = new Database([]);
        $db->beginRequestCache();
        self::assertNull($db->remember('missing', static fn () => null));
        self::assertNull($db->remember('missing', static fn () => 'unexpected reload'));
        self::assertFalse($db->remember('false', static fn () => false));
        self::assertFalse($db->remember('false', static fn () => true));

        try {
            $db->remember('failure', static fn () => throw new RuntimeException('temporary failure'));
            self::fail('The loader exception must propagate.');
        } catch (RuntimeException $e) {
            self::assertSame('temporary failure', $e->getMessage());
        }
        self::assertSame('recovered', $db->remember('failure', static fn () => 'recovered'));
    }

    public function test_writes_clear_cached_reads_but_selects_do_not(): void
    {
        $db = $this->database();
        $db->beginRequestCache();
        self::assertSame('original', $this->value($db));
        $db->resetMetrics();
        self::assertSame(1, (int) $db->fetchValue('SELECT 1'));
        self::assertSame('original', $this->value($db));
        self::assertSame(1, $db->metrics()['queries']);

        $db->run('/* mutation */ UPDATE request_cache_values SET value = ? WHERE id = 1', ['updated']);
        self::assertSame('updated', $this->value($db));
        $db->run('DELETE FROM request_cache_values WHERE id = 1');
        self::assertFalse($this->value($db));
        $db->run('INSERT INTO request_cache_values VALUES (1, ?)', ['inserted']);
        self::assertSame('inserted', $this->value($db));
    }

    public function test_transaction_boundaries_and_rollback_discard_cached_snapshots(): void
    {
        $db = $this->database();
        $db->beginRequestCache();
        self::assertSame('original', $this->value($db));
        $db->pdo()->exec("UPDATE request_cache_values SET value = 'before transaction'");

        $db->transaction(function () use ($db): void {
            self::assertSame('before transaction', $this->value($db));
            $db->run('UPDATE request_cache_values SET value = ?', ['committed']);
            self::assertSame('committed', $this->value($db));
        });
        $db->pdo()->exec("UPDATE request_cache_values SET value = 'after transaction'");
        self::assertSame('after transaction', $this->value($db));

        try {
            $db->transaction(function () use ($db): void {
                $db->run('UPDATE request_cache_values SET value = ?', ['rolled back']);
                self::assertSame('rolled back', $this->value($db));
                throw new RuntimeException('rollback');
            });
            self::fail('The transaction must propagate its exception.');
        } catch (RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame('after transaction', $this->value($db));
    }

    public function test_read_only_ctes_preserve_cached_reads_with_nested_expressions_and_comments(): void
    {
        $db = $this->database();
        $db->beginRequestCache();
        self::assertSame('original', $this->value($db));
        $db->resetMetrics();

        $value = $db->fetchValue(<<<'SQL'
            WITH selected AS (
                SELECT value, ') UPDATE request_cache_values' AS marker
                FROM request_cache_values
            ), ranked AS (
                -- DELETE is documentation, and this parenthesis is not SQL: )
                SELECT value, ROW_NUMBER() OVER (ORDER BY value) AS position FROM selected
            )
            SELECT value FROM ranked WHERE position = 1
        SQL);
        self::assertSame('original', $value);
        self::assertSame('original', $this->value($db));
        self::assertSame(1, $db->metrics()['queries']);
    }

    #[DataProvider('cteMutations')]
    public function test_cte_mutation_attempts_invalidate_including_servers_without_cte_writes(string $sql): void
    {
        $db = $this->database();
        $db->beginRequestCache();
        $loads = 0;
        $load = static function () use (&$loads): int {
            return ++$loads;
        };
        self::assertSame(1, $db->remember('mutation probe', $load));
        try {
            $db->run($sql);
        } catch (PDOException $e) {
            // Older MariaDB releases lack WITH UPDATE/DELETE. A rejected write
            // must still invalidate; MySQL executes these same statements.
            self::assertSame(1064, (int) ($e->errorInfo[1] ?? 0));
        }
        self::assertSame(2, $db->remember('mutation probe', $load));
    }

    public static function cteMutations(): array
    {
        return [
            'update after quoted and commented select' => [
                "WITH selected AS (SELECT ') SELECT' AS marker) /* SELECT */ UPDATE request_cache_values SET value = 'updated'",
            ],
            'delete after multiple ctes' => [
                'WITH first_set AS (SELECT 1 AS id), second_set AS (SELECT id FROM first_set) DELETE FROM request_cache_values WHERE id IN (SELECT id FROM second_set)',
            ],
        ];
    }

    public function test_nested_transaction_and_injected_connection_reset_cached_reads(): void
    {
        $db = $this->database();
        $db->beginRequestCache();
        $db->transaction(function () use ($db): void {
            self::assertSame('original', $this->value($db));
            $db->pdo()->exec("UPDATE request_cache_values SET value = 'nested entry'");
            $db->transaction(function () use ($db): void {
                self::assertSame('nested entry', $this->value($db));
                $db->pdo()->exec("UPDATE request_cache_values SET value = 'nested exit'");
            });
            self::assertSame('nested exit', $this->value($db));
        });

        self::assertSame('nested exit', $this->value($db));
        $replacement = $this->database()->pdo();
        $db->setPdo($replacement);
        self::assertSame('original', $this->value($db));
    }

    private function database(): Database
    {
        $db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $db->run('CREATE TEMPORARY TABLE request_cache_values (id INT PRIMARY KEY, value VARCHAR(64)) ENGINE=InnoDB');
        $db->run('INSERT INTO request_cache_values VALUES (1, ?)', ['original']);
        return $db;
    }

    private function value(Database $db): mixed
    {
        return $db->remember('test.value', static fn () => $db->fetchValue('SELECT value FROM request_cache_values WHERE id = 1'));
    }
}
