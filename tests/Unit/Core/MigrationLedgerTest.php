<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Foundation F1 — the migration-number ledger guard.
 *
 * `Migrator` orders migrations by their zero-padded 4-digit prefix, so two
 * files sharing a number (or a hole in the sequence) silently reorders or drops
 * a migration. The Phase 5 Gate A program plan (§C) allocates one number per
 * increment precisely to avoid the "everyone grabs 0066" collision; this test
 * is the enforcement — it fails CI the moment `database/migrations/` grows a
 * duplicate number, a gap, or a malformed filename, instead of letting the
 * collision through to a broken `migrate`.
 */
final class MigrationLedgerTest extends TestCase
{
    /**
     * Pure analyzer over a list of migration basenames. Shared by the real-dir
     * guard and the synthetic red-case tests so the detection logic itself is
     * proven, not just asserted against a currently-clean tree.
     *
     * @param list<string> $basenames
     * @return array{numbers:list<int>, malformed:list<string>, duplicates:list<int>, gaps:list<int>}
     */
    private static function analyze(array $basenames): array
    {
        $malformed = [];
        $seen = [];
        $duplicates = [];
        foreach ($basenames as $name) {
            if (preg_match('/^(\d{4})_[a-z0-9]+(?:_[a-z0-9]+)*\.php$/', $name, $m) !== 1) {
                $malformed[] = $name;
                continue;
            }
            $n = (int) $m[1];
            if (isset($seen[$n])) {
                $duplicates[] = $n;
            }
            $seen[$n] = true;
        }
        $numbers = array_keys($seen);
        sort($numbers);

        $gaps = [];
        if ($numbers !== []) {
            $max = $numbers[count($numbers) - 1];
            for ($i = 1; $i <= $max; $i++) {
                if (!isset($seen[$i])) {
                    $gaps[] = $i;
                }
            }
        }
        sort($duplicates);

        return ['numbers' => $numbers, 'malformed' => $malformed, 'duplicates' => $duplicates, 'gaps' => $gaps];
    }

    /** @return list<string> */
    private static function realMigrationBasenames(): array
    {
        $dir = dirname(__DIR__, 3) . '/database/migrations';
        $files = glob($dir . '/*.php');
        self::assertIsArray($files, "migrations directory not readable at $dir");
        self::assertNotEmpty($files, 'no migrations found on disk');

        return array_map('basename', $files);
    }

    public function test_migration_numbers_are_wellformed_unique_and_gapless(): void
    {
        $report = self::analyze(self::realMigrationBasenames());

        self::assertSame([], $report['malformed'], 'malformed migration filename(s): ' . implode(', ', $report['malformed']));
        self::assertSame([], $report['duplicates'], 'duplicate migration number(s): ' . implode(', ', $report['duplicates']));
        self::assertSame([], $report['gaps'], 'gap(s) in migration sequence at: ' . implode(', ', $report['gaps']));
    }

    public function test_sequence_starts_at_one_and_is_contiguous_to_the_count(): void
    {
        $report = self::analyze(self::realMigrationBasenames());
        $numbers = $report['numbers'];

        self::assertNotEmpty($numbers);
        self::assertSame(1, $numbers[0], 'sequence must start at 0001');
        // Gapless + unique ⇒ the count equals the highest number.
        self::assertSame(count($numbers), $numbers[count($numbers) - 1], 'count must equal the max number for a gapless, unique ledger');
    }

    public function test_analyzer_flags_a_gap(): void
    {
        $report = self::analyze(['0001_users.php', '0002_boards.php', '0004_threads.php']);
        self::assertSame([3], $report['gaps']);
        self::assertSame([], $report['duplicates']);
        self::assertSame([], $report['malformed']);
    }

    public function test_analyzer_flags_a_duplicate_number(): void
    {
        // The exact collision the §C table prevents: two increments both take
        // the same number (e.g. owner-lifecycle + registry snapshots on 0067).
        $report = self::analyze(['0001_a.php', '0002_b.php', '0002_c.php']);
        self::assertSame([2], $report['duplicates']);
        self::assertSame([], $report['gaps']);
        self::assertSame([], $report['malformed']);
    }

    public function test_analyzer_flags_malformed_names(): void
    {
        $report = self::analyze(['1_users.php', '0002-boards.php', '0003_Threads.php', '0004_ok.php']);
        self::assertContains('1_users.php', $report['malformed'], '3-digit prefix is malformed');
        self::assertContains('0002-boards.php', $report['malformed'], 'hyphen separator is malformed');
        self::assertContains('0003_Threads.php', $report['malformed'], 'uppercase is malformed');
        self::assertNotContains('0004_ok.php', $report['malformed']);
    }
}
