<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * File-based migration runner. Each file in the migrations directory returns an
 * object exposing up(PDO) and down(PDO). Applied migrations are tracked in
 * schema_migrations so re-running is a no-op. DDL auto-commits in MySQL, so we
 * record success per-migration rather than wrapping the whole run in one txn.
 */
final class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsPath,
    ) {
    }

    /** @param callable(string):void|null $log */
    public function migrate(?callable $log = null): int
    {
        $this->ensureTable();
        $applied = $this->appliedNames();
        $count = 0;

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            $migration->up($this->pdo);
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, UTC_TIMESTAMP())');
            $stmt->execute([$name]);
            $count++;
            if ($log) {
                $log("migrated: $name");
            }
        }

        return $count;
    }

    /** @param callable(string):void|null $log */
    public function rollback(?callable $log = null): int
    {
        $this->ensureTable();
        $applied = array_reverse($this->appliedNames());
        $files = $this->files();
        $count = 0;

        foreach ($applied as $name) {
            if (!isset($files[$name])) {
                continue;
            }
            $migration = require $files[$name];
            $migration->down($this->pdo);
            $stmt = $this->pdo->prepare('DELETE FROM schema_migrations WHERE name = ?');
            $stmt->execute([$name]);
            $count++;
            if ($log) {
                $log("rolled back: $name");
            }
            // Roll back one batch only? For Phase 1 a full rollback is fine on
            // greenfield installs; callers that want one step pass a sliced set.
        }

        return $count;
    }

    /**
     * Drop every table in the current database and re-run all migrations.
     * Used by the test harness and `migrate:fresh`.
     *
     * @param callable(string):void|null $log
     */
    public function fresh(?callable $log = null): int
    {
        $this->dropAllTables();
        if ($log) {
            $log('dropped all tables');
        }
        return $this->migrate($log);
    }

    /** @return array<string,bool> name => applied */
    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->appliedNames();
        $status = [];
        foreach (array_keys($this->files()) as $name) {
            $status[$name] = in_array($name, $applied, true);
        }
        return $status;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return list<string> */
    private function appliedNames(): array
    {
        $rows = $this->pdo->query('SELECT name FROM schema_migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval', $rows);
    }

    /** @return array<string,string> name => absolute path, sorted */
    private function files(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);
        $map = [];
        foreach ($files as $file) {
            $map[basename($file, '.php')] = $file;
        }
        return $map;
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
