<?php

declare(strict_types=1);

/**
 * 0086 · Keep distinct Unicode reactions distinct in unique keys and lookups.
 *
 * Older utf8mb4_general_ci collations equate supplementary-plane emoji.
 * Preserve the existing values and shortcode capacity, changing only equality.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $column = $pdo->query(<<<'SQL'
            SELECT COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'reactions'
              AND COLUMN_NAME = 'emoji'
        SQL);
        if ($column->fetchColumn() !== 'utf8mb4_bin') {
            $pdo->exec(<<<'SQL'
                ALTER TABLE reactions
                  MODIFY emoji VARCHAR(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
            SQL);
        }
    }

    public function down(\PDO $pdo): void
    {
        // Forward-only: restoring a lossy collation could merge distinct keys.
        // A greenfield rollback drops the whole table in migration 0019.
    }
};
