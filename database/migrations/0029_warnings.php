<?php

declare(strict_types=1);

/** 0029 · warnings — formal, user-visible warnings (ADMIN §10.1). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE warnings (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id    BIGINT UNSIGNED NOT NULL,
              issued_by  BIGINT UNSIGNED NOT NULL,
              board_id   BIGINT UNSIGNED NULL,
              reason     VARCHAR(255)    NOT NULL,
              points     TINYINT UNSIGNED NOT NULL DEFAULT 0,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_warn_user (user_id, created_at),
              CONSTRAINT fk_warn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS warnings');
    }
};
