<?php

declare(strict_types=1);

/**
 * 0012 · boards — Phase 2 column additions (PHASE_2_PLAN §7.1).
 * edit_window_seconds (0 = unlimited) and is_archived (Gate-B board archive).
 * Cheap no-op-by-default flags; require_approval stays Phase 3.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE boards
              ADD COLUMN edit_window_seconds INT        NOT NULL DEFAULT 0,
              ADD COLUMN is_archived         TINYINT(1) NOT NULL DEFAULT 0
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE boards
              DROP COLUMN edit_window_seconds,
              DROP COLUMN is_archived
        SQL);
    }
};
