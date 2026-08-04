<?php

declare(strict_types=1);

/**
 * 0079 · Drop the redundant user_profile_fields user index.
 *
 * 0062 created both `UNIQUE KEY uq_user_profile_field_position (user_id, position)`
 * and `KEY idx_user_profile_field_user (user_id, position)` — identical column
 * tuples, so the non-unique key is a strict duplicate that only costs insert
 * throughput, memory and storage. Nothing loses its index: the unique key serves
 * the one read path (`WHERE user_id = ? ORDER BY position`) and keeps `user_id`
 * as its leftmost prefix, so `fk_user_profile_field_user` stays covered.
 *
 * Both directions are guarded by information_schema because the index may already
 * have been dropped (or re-added) out of band — e.g. a PlanetScale schema
 * recommendation applied through the console before this migration runs.
 */
return new class {
    private const INDEX = 'idx_user_profile_field_user';

    public function up(\PDO $pdo): void
    {
        if (!$this->indexExists($pdo)) {
            return;
        }
        $pdo->exec('ALTER TABLE user_profile_fields DROP INDEX ' . self::INDEX);
    }

    public function down(\PDO $pdo): void
    {
        if ($this->indexExists($pdo)) {
            return;
        }
        $pdo->exec('ALTER TABLE user_profile_fields ADD KEY ' . self::INDEX . ' (user_id, position)');
    }

    private function indexExists(\PDO $pdo): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'user_profile_fields'
              AND INDEX_NAME = :index
        SQL);
        $stmt->execute([':index' => self::INDEX]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
