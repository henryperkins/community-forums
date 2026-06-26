<?php

declare(strict_types=1);

/** 0034 · user_board_prefs — per-user board organization: favorite/mute/order (USER §7.1). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_board_prefs (
              user_id     BIGINT UNSIGNED NOT NULL,
              board_id    BIGINT UNSIGNED NOT NULL,
              is_favorite TINYINT(1)      NOT NULL DEFAULT 0,
              is_muted    TINYINT(1)      NOT NULL DEFAULT 0,
              position    INT             NULL,
              PRIMARY KEY (user_id, board_id),
              CONSTRAINT fk_ubp_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
              CONSTRAINT fk_ubp_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS user_board_prefs');
    }
};
