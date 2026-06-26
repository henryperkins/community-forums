<?php

declare(strict_types=1);

/** 0001 · users — first member account slice (PHASE_1_MIGRATIONS §3). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              username          VARCHAR(32)     NOT NULL,
              email             VARCHAR(255)    NOT NULL,
              password_hash     VARCHAR(255)    NULL,
              display_name      VARCHAR(64)     NULL,
              role              ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
              location          VARCHAR(64)     NULL,
              bio               TEXT            NULL,
              post_count        INT UNSIGNED    NOT NULL DEFAULT 0,
              reputation        INT             NOT NULL DEFAULT 0,
              status            ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
              suspended_until   DATETIME        NULL,
              email_verified_at DATETIME        NULL,
              created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_users_username (username),
              UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS users');
    }
};
