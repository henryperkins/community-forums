<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Migrator;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * Schema-shape checks for migration 0077 (Thread Intelligence — plan Task 1).
 *
 * Asserts the durable job/generation tables, the summary lineage/authorship
 * changes, the related-topic AI overlay, the bounded board-sweep cursor, and
 * ledger idempotency, all against the freshly migrated test database.
 */
#[Group('nonparallel')]
final class AppThreadIntelligenceMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__ . '/../../../database/migrations/0077_thread_intelligence.php';

    /** @return array{data_type:string,column_type:string,is_nullable:string}|null */
    private function column(string $table, string $col): ?array
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col],
        );
        return $row === null ? null : [
            'data_type' => (string) $row['data_type'],
            'column_type' => (string) $row['column_type'],
            'is_nullable' => (string) $row['is_nullable'],
        ];
    }

    /** @return list<string> index column names in sequence order (empty = no such index) */
    private function indexColumns(string $table, string $index): array
    {
        $rows = $this->db->fetchAll(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$table, $index],
        );
        return array_map(static fn (array $r): string => (string) $r['COLUMN_NAME'], $rows);
    }

    /** True when ANY index on $table covers exactly $columns in order. */
    private function hasIndexOn(string $table, array $columns): bool
    {
        $rows = $this->db->fetchAll(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table],
        );
        $byIndex = [];
        foreach ($rows as $row) {
            $byIndex[(string) $row['INDEX_NAME']][] = (string) $row['COLUMN_NAME'];
        }
        return in_array($columns, $byIndex, true);
    }

    /** @return array{delete_rule:string, referenced_table:string}|null FK info for a single-column FK */
    private function foreignKey(string $table, string $column): ?array
    {
        $row = $this->db->fetch(
            'SELECT rc.DELETE_RULE AS delete_rule, kcu.REFERENCED_TABLE_NAME AS referenced_table
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND kcu.TABLE_SCHEMA = DATABASE()
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column],
        );
        return $row === null ? null : [
            'delete_rule' => (string) $row['delete_rule'],
            'referenced_table' => (string) $row['referenced_table'],
        ];
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $name = $this->db->fetchValue(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column],
        );
        return $name === false || $name === null ? null : (string) $name;
    }

    private function quotedIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) === 1;
    }

    /**
     * Rebuild the fixture-free shared database after an interrupted direct up().
     * This runs only on the RED/error path; successful up() already leaves 0077.
     */
    private function restoreAfterFailedDirectUp(): void
    {
        $migrator = new Migrator($this->pdo, (string) $this->config->get('paths.migrations'));
        $migrator->fresh();
    }

    // ---- thread_intelligence_jobs ------------------------------------------

    public function test_jobs_table_has_thread_primary_key_and_locked_states(): void
    {
        self::assertSame(['thread_id'], $this->indexColumns('thread_intelligence_jobs', 'PRIMARY'), 'thread_id must be the sole primary key');

        $state = $this->column('thread_intelligence_jobs', 'state');
        self::assertNotNull($state);
        foreach (['idle', 'queued', 'running', 'retry', 'dead', 'review_required'] as $locked) {
            self::assertStringContainsString("'$locked'", $state['column_type']);
        }
        self::assertSame(6, substr_count($state['column_type'], "'") / 2, 'exactly the six locked states');
    }

    public function test_jobs_table_has_cadence_checkpoint_pause_and_lease_fields(): void
    {
        $expectations = [
            'trigger_code' => ['varchar(64)', 'NO'],
            'trigger_reason' => ['varchar(255)', 'YES'],
            'due_at' => ['datetime', 'YES'],
            'lease_token' => ['char(64)', 'YES'],
            'lease_expires_at' => ['datetime', 'YES'],
            'attempt_count' => ['int(10) unsigned', 'NO'],
            'last_error_code' => ['varchar(64)', 'YES'],
            'last_processed_post_id' => ['bigint(20) unsigned', 'YES'],
            'last_generated_at' => ['datetime', 'YES'],
            'last_full_reconcile_at' => ['datetime', 'YES'],
            'automation_paused' => ['tinyint(1)', 'NO'],
            'paused_by' => ['bigint(20) unsigned', 'YES'],
            'paused_at' => ['datetime', 'YES'],
            'source_snapshot_hash' => ['char(64)', 'YES'],
            'activity_version' => ['bigint(20) unsigned', 'NO'],
            'reconcile_required' => ['tinyint(1)', 'NO'],
            'created_at' => ['datetime', 'NO'],
            'updated_at' => ['datetime', 'YES'],
        ];
        foreach ($expectations as $name => [$type, $nullable]) {
            $col = $this->column('thread_intelligence_jobs', $name);
            self::assertNotNull($col, "missing jobs column: $name");
            // MySQL 8 omits display widths (e.g. "int unsigned"); MariaDB keeps them.
            $normalized = str_replace(['(10)', '(20)'], '', $col['column_type']);
            self::assertSame(str_replace(['(10)', '(20)'], '', $type), $normalized, "jobs.$name type");
            self::assertSame($nullable, $col['is_nullable'], "jobs.$name nullability");
        }
    }

    public function test_jobs_table_ownership_and_claim_indexes(): void
    {
        $thread = $this->foreignKey('thread_intelligence_jobs', 'thread_id');
        self::assertNotNull($thread);
        self::assertSame('threads', $thread['referenced_table']);
        self::assertSame('CASCADE', $thread['delete_rule'], 'thread deletion must cascade its job row');

        $pausedBy = $this->foreignKey('thread_intelligence_jobs', 'paused_by');
        self::assertNotNull($pausedBy);
        self::assertSame('users', $pausedBy['referenced_table']);
        self::assertSame('SET NULL', $pausedBy['delete_rule']);

        $checkpoint = $this->foreignKey('thread_intelligence_jobs', 'last_processed_post_id');
        self::assertNotNull($checkpoint);
        self::assertSame('posts', $checkpoint['referenced_table']);
        self::assertSame('SET NULL', $checkpoint['delete_rule']);

        self::assertTrue($this->hasIndexOn('thread_intelligence_jobs', ['state', 'due_at', 'thread_id']), 'due-claim index');
        self::assertTrue($this->hasIndexOn('thread_intelligence_jobs', ['state', 'lease_expires_at', 'thread_id']), 'expired-lease claim index');
    }

    // ---- thread_intelligence_generations ------------------------------------

    public function test_generations_table_has_attempt_provenance_usage_and_timestamp_fields(): void
    {
        $status = $this->column('thread_intelligence_generations', 'status');
        self::assertNotNull($status);
        foreach (['requested', 'succeeded', 'published', 'retry', 'failed', 'dead', 'review_required', 'rejected', 'stale'] as $locked) {
            self::assertStringContainsString("'$locked'", $status['column_type']);
        }
        self::assertSame(9, substr_count($status['column_type'], "'") / 2, 'exactly the nine attempt statuses');

        foreach ([
            'trigger_code', 'retry_number', 'window_number',
            'baseline_summary_id', 'published_summary_id',
            'source_snapshot_hash', 'source_post_ids', 'candidate_thread_ids',
            'request_fingerprint', 'prompt_version', 'model', 'reasoning_effort', 'provider_response_id',
            'estimated_input_tokens', 'input_tokens', 'output_tokens', 'reasoning_tokens', 'cached_tokens',
            'failure_code', 'failure_message',
            'requested_at', 'completed_at', 'published_at',
        ] as $name) {
            self::assertNotNull($this->column('thread_intelligence_generations', $name), "missing generations column: $name");
        }

        self::assertSame('varchar(255)', $this->column('thread_intelligence_generations', 'failure_message')['column_type'], 'safe failure detail is capped at 255 characters');
        self::assertSame('char(64)', $this->column('thread_intelligence_generations', 'request_fingerprint')['column_type']);
        self::assertSame('YES', $this->column('thread_intelligence_generations', 'request_fingerprint')['is_nullable']);

        // JSON ID lists: MySQL 8 reports 'json'; MariaDB aliases JSON to longtext.
        foreach (['source_post_ids', 'candidate_thread_ids'] as $jsonCol) {
            $type = $this->column('thread_intelligence_generations', $jsonCol)['data_type'];
            self::assertContains($type, ['json', 'longtext'], "$jsonCol must be a JSON column (got $type)");
        }
    }

    public function test_generations_table_lifecycle_ownership_and_indexes(): void
    {
        $thread = $this->foreignKey('thread_intelligence_generations', 'thread_id');
        self::assertNotNull($thread);
        self::assertSame('threads', $thread['referenced_table']);
        self::assertSame('CASCADE', $thread['delete_rule'], 'the thread FK is the lifecycle owner');

        foreach (['baseline_summary_id', 'published_summary_id'] as $summaryFk) {
            $col = $this->column('thread_intelligence_generations', $summaryFk);
            self::assertNotNull($col);
            self::assertSame('YES', $col['is_nullable'], "$summaryFk must be nullable");
            $fk = $this->foreignKey('thread_intelligence_generations', $summaryFk);
            self::assertNotNull($fk);
            self::assertSame('thread_summaries', $fk['referenced_table']);
            self::assertSame('SET NULL', $fk['delete_rule'], "$summaryFk must not cascade summary deletes into evidence");
            self::assertTrue($this->hasIndexOn('thread_intelligence_generations', [$summaryFk]), "$summaryFk index");
        }

        self::assertTrue($this->hasIndexOn('thread_intelligence_generations', ['thread_id', 'id']), 'per-thread evidence index');
        self::assertTrue($this->hasIndexOn('thread_intelligence_generations', ['status', 'completed_at', 'id']), 'retention/attention index');
    }

    // ---- thread_summaries lineage -------------------------------------------

    public function test_thread_summaries_accepts_ai_kind_with_nullable_author_and_parent_lineage(): void
    {
        $kind = $this->column('thread_summaries', 'kind');
        self::assertNotNull($kind);
        self::assertStringContainsString("'ai'", $kind['column_type']);
        self::assertStringContainsString("'manual'", $kind['column_type']);
        self::assertStringContainsString("'canonical_answer'", $kind['column_type']);

        $author = $this->column('thread_summaries', 'author_id');
        self::assertNotNull($author);
        self::assertSame('YES', $author['is_nullable'], 'AI versions have no human author');
        $authorFk = $this->foreignKey('thread_summaries', 'author_id');
        self::assertNotNull($authorFk);
        self::assertSame('users', $authorFk['referenced_table']);
        self::assertSame('SET NULL', $authorFk['delete_rule'], 'deleting a human author must not delete summary history');

        $parent = $this->column('thread_summaries', 'parent_summary_id');
        self::assertNotNull($parent, 'lineage column missing');
        self::assertSame('YES', $parent['is_nullable']);
        $parentFk = $this->foreignKey('thread_summaries', 'parent_summary_id');
        self::assertNotNull($parentFk);
        self::assertSame('thread_summaries', $parentFk['referenced_table'], 'lineage is a self-reference');
        self::assertSame('SET NULL', $parentFk['delete_rule']);
    }

    // ---- related_threads AI overlay ------------------------------------------

    public function test_related_threads_keeps_source_enum_and_pair_uniqueness_and_gains_ai_overlay(): void
    {
        $source = $this->column('related_threads', 'source');
        self::assertNotNull($source);
        self::assertSame("enum('curated','tag','search','merge')", $source['column_type'], 'the existing source vocabulary must not change');

        self::assertSame(
            ['source_thread_id', 'related_thread_id', 'relation_type'],
            $this->indexColumns('related_threads', 'uq_related_pair'),
            'uq_related_pair must remain unchanged',
        );
        self::assertSame(
            3,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'related_threads' AND INDEX_NAME = 'uq_related_pair' AND NON_UNIQUE = 0",
            ),
            'uq_related_pair must remain a unique key',
        );

        $generation = $this->column('related_threads', 'ai_generation_id');
        self::assertNotNull($generation);
        self::assertSame('YES', $generation['is_nullable']);
        $generationFk = $this->foreignKey('related_threads', 'ai_generation_id');
        self::assertNotNull($generationFk);
        self::assertSame('thread_intelligence_generations', $generationFk['referenced_table']);
        self::assertSame('SET NULL', $generationFk['delete_rule']);

        self::assertSame('varchar(255)', $this->column('related_threads', 'ai_reason')['column_type']);
        self::assertSame('YES', $this->column('related_threads', 'ai_reason')['is_nullable']);
        self::assertSame('tinyint(1)', $this->column('related_threads', 'ai_selected')['column_type']);
        self::assertSame('YES', $this->column('related_threads', 'ai_selected_at')['is_nullable']);

        self::assertTrue(
            $this->hasIndexOn('related_threads', ['source_thread_id', 'ai_selected', 'status', 'id']),
            'current-overlay read index',
        );
    }

    // ---- board sweep cursors ---------------------------------------------------

    public function test_board_sweep_cursor_and_keyset_indexes_exist(): void
    {
        $cursor = $this->column('boards', 'thread_intelligence_sweep_after_id');
        self::assertNotNull($cursor);
        self::assertSame('YES', $cursor['is_nullable'], 'NULL means no sweep is due');
        self::assertStringContainsString('unsigned', $cursor['column_type']);

        self::assertSame(
            ['thread_intelligence_sweep_after_id', 'id'],
            $this->indexColumns('boards', 'idx_boards_ti_sweep'),
        );
        self::assertSame(
            ['board_id', 'id'],
            $this->indexColumns('threads', 'idx_threads_board_id'),
            'keyset batches need (board_id, id)',
        );
    }

    // ---- ledger idempotency ------------------------------------------------------

    public function test_applying_migrations_twice_is_idempotent_through_the_ledger(): void
    {
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM schema_migrations WHERE name = '0077_thread_intelligence'",
            ),
            '0077 must be recorded exactly once',
        );

        $migrator = new Migrator($this->pdo, (string) $this->config->get('paths.migrations'));
        self::assertTrue($migrator->isSynced(), 'ledger must exactly match the migration files');
        self::assertSame(0, $migrator->migrate(), 're-running migrate must apply nothing');
    }

    public function test_0077_discovers_a_drifted_legacy_summary_author_foreign_key(): void
    {
        self::assertSame(
            0,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_summaries'),
            'direct migration rehearsal requires a fixture-free summary table',
        );

        $migration = require self::MIGRATION;
        $legacyConstraint = 'fk_summary_author_legacy_drift';
        $fixture = [
            'category_id' => null,
            'board_id' => null,
            'thread_id' => null,
            'owner_id' => null,
            'author_id' => null,
            'summary_id' => null,
        ];
        $upCompleted = false;

        try {
            $migration->down($this->pdo);
            $this->pdo->exec('ALTER TABLE thread_summaries DROP FOREIGN KEY fk_summary_author');
            $this->pdo->exec(
                'ALTER TABLE thread_summaries ADD CONSTRAINT '
                . $this->quotedIdentifier($legacyConstraint)
                . ' FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
            );
            self::assertSame($legacyConstraint, $this->foreignKeyName('thread_summaries', 'author_id'));

            $suffix = bin2hex(random_bytes(5));
            $fixture['category_id'] = (int) $this->db->insert(
                'INSERT INTO categories (name) VALUES (?)',
                ['TI migration ' . $suffix],
            );
            $fixture['owner_id'] = (int) $this->db->insert(
                'INSERT INTO users (username, email) VALUES (?, ?)',
                ['ti_owner_' . $suffix, 'ti_owner_' . $suffix . '@example.test'],
            );
            $fixture['author_id'] = (int) $this->db->insert(
                'INSERT INTO users (username, email) VALUES (?, ?)',
                ['ti_author_' . $suffix, 'ti_author_' . $suffix . '@example.test'],
            );
            $fixture['board_id'] = (int) $this->db->insert(
                'INSERT INTO boards (category_id, slug, name) VALUES (?, ?, ?)',
                [$fixture['category_id'], 'ti-migration-' . $suffix, 'TI Migration ' . $suffix],
            );
            $fixture['thread_id'] = (int) $this->db->insert(
                'INSERT INTO threads (board_id, user_id, title, slug) VALUES (?, ?, ?, ?)',
                [$fixture['board_id'], $fixture['owner_id'], 'Migration fixture', 'migration-fixture-' . $suffix],
            );
            $fixture['summary_id'] = (int) $this->db->insert(
                "INSERT INTO thread_summaries (thread_id, kind, status, body, body_html, version, author_id)
                 VALUES (?, 'manual', 'published', 'Legacy summary body', '<p>Legacy summary body</p>', 3, ?)",
                [$fixture['thread_id'], $fixture['author_id']],
            );

            $migrationFailure = null;
            try {
                $migration->up($this->pdo);
                $upCompleted = true;
            } catch (\Throwable $e) {
                $migrationFailure = $e;
            }
            if ($migrationFailure !== null) {
                self::fail(
                    '0077 must discover the FK covering thread_summaries.author_id; direct up() failed: '
                    . $migrationFailure->getMessage(),
                );
            }

            $summary = $this->db->fetch(
                'SELECT body, version, author_id FROM thread_summaries WHERE id = ?',
                [$fixture['summary_id']],
            );
            self::assertNotNull($summary, 'the pre-existing summary must survive migration 0077');
            self::assertSame('Legacy summary body', $summary['body']);
            self::assertSame(3, (int) $summary['version']);
            self::assertSame($fixture['author_id'], (int) $summary['author_id']);

            $this->db->run('DELETE FROM users WHERE id = ?', [$fixture['author_id']]);
            $summary = $this->db->fetch(
                'SELECT author_id FROM thread_summaries WHERE id = ?',
                [$fixture['summary_id']],
            );
            self::assertNotNull($summary, 'deleting an author must not delete summary history');
            self::assertNull($summary['author_id'], 'the migrated author FK must use ON DELETE SET NULL');
        } finally {
            try {
                if ($fixture['thread_id'] !== null) {
                    $this->db->run('DELETE FROM threads WHERE id = ?', [$fixture['thread_id']]);
                }
                if ($fixture['board_id'] !== null) {
                    $this->db->run('DELETE FROM boards WHERE id = ?', [$fixture['board_id']]);
                }
                if ($fixture['category_id'] !== null) {
                    $this->db->run('DELETE FROM categories WHERE id = ?', [$fixture['category_id']]);
                }
                foreach (['owner_id', 'author_id'] as $userKey) {
                    if ($fixture[$userKey] !== null) {
                        $this->db->run('DELETE FROM users WHERE id = ?', [$fixture[$userKey]]);
                    }
                }
            } finally {
                if (!$upCompleted) {
                    $this->restoreAfterFailedDirectUp();
                }
            }
        }
    }

    public function test_0077_down_and_up_rehearsal_on_fixture_free_schema(): void
    {
        self::assertSame(
            'retroboards_thread_intelligence_clean',
            (string) $this->db->fetchValue('SELECT DATABASE()'),
            'direct down/up rehearsal must run only on its dedicated throwaway database',
        );
        foreach (['thread_summaries', 'related_threads', 'threads', 'boards'] as $table) {
            self::assertSame(
                0,
                (int) $this->db->fetchValue('SELECT COUNT(*) FROM ' . $this->quotedIdentifier($table)),
                "0077 down() is fixture-free only; $table is populated",
            );
        }

        $migration = require self::MIGRATION;
        $downCompleted = false;
        try {
            $migration->down($this->pdo);
            $downCompleted = true;

            self::assertFalse($this->tableExists('thread_intelligence_jobs'));
            self::assertFalse($this->tableExists('thread_intelligence_generations'));

            $kind = $this->column('thread_summaries', 'kind');
            self::assertNotNull($kind);
            self::assertStringNotContainsString("'ai'", $kind['column_type']);
            self::assertSame('NO', $this->column('thread_summaries', 'author_id')['is_nullable']);
            self::assertSame('CASCADE', $this->foreignKey('thread_summaries', 'author_id')['delete_rule']);
            self::assertNull($this->column('thread_summaries', 'parent_summary_id'));

            foreach (['ai_generation_id', 'ai_reason', 'ai_selected', 'ai_selected_at'] as $column) {
                self::assertNull($this->column('related_threads', $column), "old related_threads shape still has $column");
            }
            self::assertNull($this->column('boards', 'thread_intelligence_sweep_after_id'));
            self::assertSame([], $this->indexColumns('threads', 'idx_threads_board_id'));
        } finally {
            if ($downCompleted) {
                try {
                    $migration->up($this->pdo);
                } catch (\Throwable $failure) {
                    $this->restoreAfterFailedDirectUp();
                    throw $failure;
                }
            } else {
                $this->restoreAfterFailedDirectUp();
            }
        }

        self::assertTrue($this->tableExists('thread_intelligence_jobs'));
        self::assertTrue($this->tableExists('thread_intelligence_generations'));
        self::assertStringContainsString("'ai'", $this->column('thread_summaries', 'kind')['column_type']);
        self::assertSame('YES', $this->column('thread_summaries', 'author_id')['is_nullable']);
        self::assertSame('SET NULL', $this->foreignKey('thread_summaries', 'author_id')['delete_rule']);
        self::assertNotNull($this->column('thread_summaries', 'parent_summary_id'));
        self::assertNotNull($this->column('related_threads', 'ai_generation_id'));
        self::assertNotNull($this->column('boards', 'thread_intelligence_sweep_after_id'));
        self::assertSame(['board_id', 'id'], $this->indexColumns('threads', 'idx_threads_board_id'));
    }
}
