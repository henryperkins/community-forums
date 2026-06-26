<?php

declare(strict_types=1);

/**
 * 0028 · bans — site/board bans, source of truth + history (ADMIN §10.1).
 * users.status/suspended_until is the denormalised fast-path cache.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE bans (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id    BIGINT UNSIGNED NOT NULL,
              scope      ENUM('site','board') NOT NULL DEFAULT 'site',
              board_id   BIGINT UNSIGNED NULL,
              type       ENUM('post','full') NOT NULL DEFAULT 'post',
              reason     VARCHAR(255)    NOT NULL,
              created_by BIGINT UNSIGNED NOT NULL,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at DATETIME        NULL,
              lifted_at  DATETIME        NULL,
              lifted_by  BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_bans_active (user_id, expires_at, lifted_at),
              KEY idx_bans_board (board_id),
              CONSTRAINT fk_bans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS bans');
    }
};
