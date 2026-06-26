<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Migrator;
use PDO;

/**
 * Phase-1 → Phase-2 upgrade rehearsal (PHASE_2_PLAN §6 Milestone 6, §8 "Existing
 * Phase 1 upgrade"). Operationally verifies that the Phase 2 migrations are
 * additive on a POPULATED Phase 1 database: it builds the Phase 1 schema
 * (migrations 0001–0010), seeds representative data, runs the remaining Phase 2
 * migrations, then asserts no Phase 1 row or value was lost and the new
 * tables/columns are present.
 *
 * DESTRUCTIVE: it drops every table in the target database first, so it must be
 * pointed at a throwaway/scratch database, never production. It is a CLI/ops
 * rehearsal (DDL auto-commits in MySQL/MariaDB, so it cannot run inside the
 * transactional PHPUnit harness).
 */
final class UpgradeRehearsal
{
    private const PHASE_1_CUTOFF = '0010';

    /** The ten Phase 1 tables (PHASE_1_MIGRATIONS §6) — every column must survive. */
    private const PHASE_1_TABLES = [
        'users', 'categories', 'settings', 'boards', 'sessions', 'verifications',
        'board_slug_history', 'threads', 'posts', 'moderation_log',
    ];

    /** Phase 2 tables that must exist after the upgrade. */
    private const NEW_TABLES = [
        'board_moderators', 'thread_user', 'blocks', 'reactions', 'subscriptions',
        'notifications', 'email_suppressions', 'email_deliveries', 'conversations',
        'conversation_participants', 'dm_messages', 'reports', 'bans', 'warnings',
        'user_notes', 'board_members', 'oauth_identities', 'user_preferences',
        'user_board_prefs', 'username_history', 'follows', 'badges', 'user_badges',
    ];

    /** Phase 2 column additions that must exist after the upgrade. table => column. */
    private const NEW_COLUMNS = [
        ['users', 'title'], ['users', 'profile_visibility'], ['users', 'allow_dms'],
        ['users', 'show_presence'], ['users', 'avatar_source'], ['users', 'last_seen_at'],
        ['boards', 'is_archived'], ['boards', 'edit_window_seconds'],
        ['threads', 'accepted_answer_post_id'], ['posts', 'ip'], ['sessions', 'ip'],
    ];

    public function __construct(private PDO $pdo, private string $migrationsPath)
    {
    }

    /**
     * @param callable(string):void $log
     * @return array{ok:bool, checks:list<array{name:string,ok:bool,detail:string}>}
     */
    public function run(callable $log): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail = '') use (&$checks, $log): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
            $log(sprintf('  [%s] %s%s', $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? " — $detail" : ''));
        };

        $log('1. Resetting target database to a clean slate…');
        $this->dropAllTables();

        $log('2. Building Phase 1 schema (migrations 0001–0010)…');
        $applied = $this->applyPhase1();
        $add('Phase 1 schema built', $applied === 10, "$applied/10 migrations applied");

        // Full column inventory BEFORE the Phase 2 migrations, so the additive
        // check below is exhaustive (every Phase 1 column, not a hand-picked few).
        $phase1Columns = $this->columnInventory(self::PHASE_1_TABLES);

        $log('3. Seeding representative Phase 1 data…');
        $this->seedPhase1();
        $before = $this->snapshot();
        $log(sprintf('   seeded: %d users, %d boards, %d threads, %d posts', $before['users'], $before['boards'], $before['threads'], $before['posts']));

        $log('4. Applying Phase 2 migrations (0011 → latest)…');
        $phase2 = (new Migrator($this->pdo, $this->migrationsPath))->migrate(fn (string $m) => $log("   $m"));
        $add('Phase 2 migrations applied', $phase2 > 0, "$phase2 migrations");

        $log('5. Verifying data preservation…');
        $after = $this->snapshot();
        foreach ($before as $table => $count) {
            $add("$table row count preserved", ($after[$table] ?? -1) === $count, "before=$count after=" . ($after[$table] ?? 'missing'));
        }
        $add('Sample username preserved', $this->scalar("SELECT username FROM users WHERE username = 'legacy_admin'") === 'legacy_admin');
        $add('Sample post body preserved', $this->scalar("SELECT body FROM posts WHERE is_op = 1 ORDER BY id LIMIT 1") === 'Legacy opening post body.');
        $add("Reputation value preserved", (int) $this->scalar("SELECT reputation FROM users WHERE username = 'legacy_member'") === 42);
        $add('Settings value preserved', $this->scalar("SELECT `value` FROM settings WHERE `key` = 'site_name'") === '"Legacy Forum"');

        $log('6. Verifying new Phase 2 tables exist…');
        $missingTables = array_values(array_filter(self::NEW_TABLES, fn (string $t) => !$this->tableExists($t)));
        $add('All Phase 2 tables present', $missingTables === [], $missingTables === [] ? count(self::NEW_TABLES) . ' tables' : 'missing: ' . implode(', ', $missingTables));

        $log('7. Verifying new Phase 2 columns exist…');
        $missingCols = [];
        foreach (self::NEW_COLUMNS as [$t, $col]) {
            if (!$this->columnExists($t, $col)) {
                $missingCols[] = "$t.$col";
            }
        }
        $add('All Phase 2 columns present', $missingCols === [], $missingCols === [] ? count(self::NEW_COLUMNS) . ' columns' : 'missing: ' . implode(', ', $missingCols));

        $log('8. Verifying EVERY Phase 1 column retained (additive, no drops)…');
        $afterColumns = $this->columnInventory(self::PHASE_1_TABLES);
        $droppedCols = array_values(array_diff($phase1Columns, $afterColumns));
        $add('Phase 1 columns retained', $droppedCols === [], $droppedCols === [] ? count($phase1Columns) . ' columns intact' : 'dropped: ' . implode(', ', $droppedCols));

        $log('9. Verifying badge catalogue seeded…');
        $badgeCount = (int) $this->scalar('SELECT COUNT(*) FROM badges');
        $add('Badge catalogue seeded', $badgeCount >= 11, "$badgeCount badges");

        $ok = array_reduce($checks, fn (bool $carry, array $c) => $carry && $c['ok'], true);
        return ['ok' => $ok, 'checks' => $checks];
    }

    private function applyPhase1(): int
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(255) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);
        $count = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (substr($name, 0, 4) > self::PHASE_1_CUTOFF) {
                continue;
            }
            $migration = require $file;
            $migration->up($this->pdo);
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, UTC_TIMESTAMP())');
            $stmt->execute([$name]);
            $count++;
        }
        return $count;
    }

    private function seedPhase1(): void
    {
        $this->pdo->exec(
            "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('site_name', '\"Legacy Forum\"', UTC_TIMESTAMP())",
        );
        $this->pdo->exec(
            "INSERT INTO users (username, email, password_hash, role, status, bio, reputation, post_count, created_at)
             VALUES ('legacy_admin', 'admin@legacy.test', 'x', 'admin', 'active', 'I am the admin.', 0, 1, UTC_TIMESTAMP()),
                    ('legacy_member', 'member@legacy.test', 'x', 'user', 'active', 'Long-time member.', 42, 3, UTC_TIMESTAMP())",
        );
        $adminId = (int) $this->scalar("SELECT id FROM users WHERE username = 'legacy_admin'");
        $memberId = (int) $this->scalar("SELECT id FROM users WHERE username = 'legacy_member'");

        $this->pdo->exec("INSERT INTO categories (name, position) VALUES ('General', 0)");
        $catId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO boards (category_id, slug, name, description, visibility) VALUES ($catId, 'general', 'General', 'Talk about anything.', 'public')",
        );
        $boardId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO threads (board_id, user_id, title, slug, reply_count, created_at)
             VALUES ($boardId, $memberId, 'A legacy topic', 'a-legacy-topic', 1, UTC_TIMESTAMP())",
        );
        $threadId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO posts (thread_id, user_id, body, body_html, is_op, created_at)
             VALUES ($threadId, $memberId, 'Legacy opening post body.', '<p>Legacy opening post body.</p>', 1, UTC_TIMESTAMP()),
                    ($threadId, $adminId, 'A legacy reply.', '<p>A legacy reply.</p>', 0, UTC_TIMESTAMP())",
        );
        $this->pdo->exec(
            "INSERT INTO moderation_log (actor_id, action, target_type, target_id, created_at)
             VALUES ($adminId, 'thread.pin', 'thread', $threadId, UTC_TIMESTAMP())",
        );
    }

    /** @return array<string,int> Phase 1 table => row count */
    private function snapshot(): array
    {
        $out = [];
        foreach (['users', 'categories', 'boards', 'threads', 'posts', 'settings', 'moderation_log'] as $t) {
            $out[$t] = (int) $this->scalar("SELECT COUNT(*) FROM `$t`");
        }
        return $out;
    }

    private function scalar(string $sql): mixed
    {
        $v = $this->pdo->query($sql)->fetchColumn();
        return $v === false ? null : $v;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
        );
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Every column across the given tables, as "table.column" — for an
     * exhaustive before/after additive diff.
     *
     * @param list<string> $tables
     * @return list<string>
     */
    private function columnInventory(array $tables): array
    {
        if ($tables === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($tables), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT CONCAT(table_name, '.', column_name) AS c
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name IN ($place)",
        );
        $stmt->execute($tables);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
        );
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    }

    private function dropAllTables(): void
    {
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables === []) {
            return;
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
