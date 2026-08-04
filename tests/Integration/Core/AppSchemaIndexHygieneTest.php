<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * Schema hygiene guard for redundant secondary indexes (migrations 0079 + 0080).
 *
 * A non-unique BTREE index whose columns are a leftmost prefix of another index
 * on the same table can never be the only way to answer a query: InnoDB resolves
 * every one of its lookups — and every foreign key it covers — through the wider
 * index. Keeping both only costs insert throughput, memory and storage, and can
 * push the optimizer into an index_merge intersect where a single ref lookup
 * would do (which is exactly what `idx_cp_user` did to the DM inbox query).
 *
 * These are the checks behind the PlanetScale "redundant index" recommendations;
 * asserting the invariant here catches a reintroduction at `composer test` time
 * instead of in a production advisory weeks later.
 */
#[Group('nonparallel')]
final class AppSchemaIndexHygieneTest extends TestCase
{
    /** Indexes dropped by 0079/0080, each with the index that still covers its reads. */
    private const DROPPED = [
        ['user_profile_fields', 'idx_user_profile_field_user', 'uq_user_profile_field_position'],
        ['conversation_participants', 'idx_cp_user', 'idx_cp_active_user'],
        ['link_previews', 'idx_preview_source', 'uq_preview_source_url'],
    ];

    public function test_no_index_duplicates_a_leftmost_prefix_of_another(): void
    {
        $redundant = [];
        foreach ($this->comparableIndexes() as $table => $indexes) {
            foreach ($indexes as $name => $index) {
                if ($index['unique']) {
                    continue; // a unique index is a constraint, never merely redundant
                }
                foreach ($indexes as $otherName => $other) {
                    if ($otherName === $name || !$this->isPrefixOf($index['columns'], $other['columns'])) {
                        continue;
                    }
                    $redundant[] = sprintf(
                        '%s.%s (%s) is covered by %s (%s)',
                        $table,
                        $name,
                        implode(', ', $index['columns']),
                        $otherName,
                        implode(', ', $other['columns']),
                    );
                    break;
                }
            }
        }

        self::assertSame(
            [],
            $redundant,
            "Redundant indexes found — drop them in a migration (see 0079/0080):\n  " . implode("\n  ", $redundant),
        );
    }

    public function test_the_dropped_indexes_stay_dropped_and_their_cover_survives(): void
    {
        $indexes = $this->comparableIndexes();
        foreach (self::DROPPED as [$table, $dropped, $cover]) {
            self::assertArrayHasKey($table, $indexes, "$table must exist");
            self::assertArrayNotHasKey($dropped, $indexes[$table], "$table.$dropped was dropped as redundant");
            self::assertArrayHasKey($cover, $indexes[$table], "$table.$cover must survive to cover the reads");
        }
    }

    /**
     * Every BTREE index in the test schema, keyed table => index => shape.
     * FULLTEXT/SPATIAL indexes and prefix (`SUB_PART`) indexes are excluded:
     * their coverage cannot be compared on column names alone.
     *
     * @return array<string,array<string,array{columns:list<string>,unique:bool}>>
     */
    private function comparableIndexes(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE, SUB_PART, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
        );

        $indexes = [];
        $skip = [];
        foreach ($rows as $row) {
            $table = (string) $row['TABLE_NAME'];
            $name = (string) $row['INDEX_NAME'];
            if (strtoupper((string) $row['INDEX_TYPE']) !== 'BTREE' || $row['SUB_PART'] !== null) {
                $skip[$table][$name] = true;
                continue;
            }
            $indexes[$table][$name]['columns'][] = (string) $row['COLUMN_NAME'];
            $indexes[$table][$name]['unique'] = ((int) $row['NON_UNIQUE']) === 0;
        }

        foreach ($skip as $table => $names) {
            foreach (array_keys($names) as $name) {
                unset($indexes[$table][$name]);
            }
        }

        return $indexes;
    }

    /**
     * True when $candidate is a leftmost prefix of (or identical to) $wider.
     *
     * @param list<string> $candidate
     * @param list<string> $wider
     */
    private function isPrefixOf(array $candidate, array $wider): bool
    {
        return count($candidate) <= count($wider)
            && array_slice($wider, 0, count($candidate)) === $candidate;
    }
}
