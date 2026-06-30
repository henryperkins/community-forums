<?php

declare(strict_types=1);

/**
 * 0064 - Server-side draft sync, deploy-dark behind server_drafts.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE server_drafts (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              context_key VARCHAR(191)    NOT NULL,
              revision    INT UNSIGNED    NOT NULL DEFAULT 1,
              title       VARCHAR(255)    NULL,
              body        MEDIUMTEXT      NOT NULL,
              metadata    JSON            NULL,
              updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at  DATETIME        NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_server_draft_context (user_id, context_key),
              KEY idx_server_draft_user_updated (user_id, updated_at),
              KEY idx_server_draft_expires (expires_at),
              CONSTRAINT fk_server_draft_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS server_drafts');
    }
};
