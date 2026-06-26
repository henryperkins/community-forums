<?php

declare(strict_types=1);

/** 0005 · sessions — DB-backed sessions (FK → users). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE sessions (
              id           CHAR(64)        NOT NULL,
              user_id      BIGINT UNSIGNED NOT NULL,
              csrf_secret  CHAR(64)        NOT NULL,
              user_agent   VARCHAR(255)    NULL,
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_seen_at DATETIME        NULL,
              expires_at   DATETIME        NOT NULL,
              revoked_at   DATETIME        NULL,
              PRIMARY KEY (id),
              KEY idx_sessions_user (user_id),
              KEY idx_sessions_active (expires_at, revoked_at),
              CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS sessions');
    }
};
