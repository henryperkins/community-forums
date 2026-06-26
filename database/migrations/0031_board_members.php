<?php

declare(strict_types=1);

/**
 * 0031 · board_members — membership for private/hidden boards, read-gate (ADMIN §10.1).
 * Private boards stay admin-only until explicit rows exist (P2-08 activates this).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE board_members (
              board_id   BIGINT UNSIGNED NOT NULL,
              user_id    BIGINT UNSIGNED NOT NULL,
              added_by   BIGINT UNSIGNED NULL,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (board_id, user_id),
              KEY idx_bm_user (user_id),
              CONSTRAINT fk_bm_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
              CONSTRAINT fk_bm_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS board_members');
    }
};
