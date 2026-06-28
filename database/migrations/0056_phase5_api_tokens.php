<?php

declare(strict_types=1);

/**
 * 0056 · Phase 5 Gate A prerequisite (B2) — admin/service API tokens.
 *
 * ADDITIVE. Scoped, hash-only Bearer tokens. Also extends the
 * moderation_log.target_type ENUM with 'api_token' (mirroring 0055's
 * 'service_secret') so api_token_* audit rows are valid.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE api_tokens (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(80)     NOT NULL,
              token_hash   CHAR(64)        NOT NULL,
              scopes       JSON            NOT NULL,
              created_by   BIGINT UNSIGNED NOT NULL,
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_used_at DATETIME        NULL,
              expires_at   DATETIME        NULL,
              revoked_at   DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_api_token_hash (token_hash),
              KEY idx_api_token_created_by (created_by),
              KEY idx_api_token_active (revoked_at, expires_at),
              CONSTRAINT fk_api_token_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'api_token'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS api_tokens');
    }
};
