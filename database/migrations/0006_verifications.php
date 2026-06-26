<?php

declare(strict_types=1);

/**
 * 0006 · verifications — created in Phase 1 but DORMANT; every writer (email
 * verify, email change, password reset) needs the Phase 2 email worker.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE verifications (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id    BIGINT UNSIGNED NOT NULL,
              type       ENUM('email_verify','email_change','password_reset') NOT NULL,
              token_hash CHAR(64)        NOT NULL,
              new_email  VARCHAR(255)    NULL,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at DATETIME        NOT NULL,
              used_at    DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_verif_token (token_hash),
              KEY idx_verif_user (user_id, type),
              CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS verifications');
    }
};
