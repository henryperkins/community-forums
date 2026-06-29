<?php

declare(strict_types=1);

/**
 * 0055 · Phase 5 Gate A prerequisite (B2) — encrypted service-secret registry.
 *
 * ADDITIVE + INERT. Reversible-secret vault (SecretVault) built on SecretBox.
 * service_secrets holds opaque references; service_secret_versions holds the
 * AES-256-GCM material per version. Nothing reads these until a consumer
 * (provider/webhook) and the dark `service_secrets` flag turn on.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret') NOT NULL
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE service_secrets (
              id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
              secret_ref      VARCHAR(64)      NOT NULL,
              owner_type      VARCHAR(32)      NOT NULL DEFAULT 'generic',
              owner_id        BIGINT UNSIGNED  NULL,
              label           VARCHAR(190)     NOT NULL,
              status          ENUM('active','revoked') NOT NULL DEFAULT 'active',
              latest_version  INT UNSIGNED     NOT NULL DEFAULT 0,
              created_by      BIGINT UNSIGNED  NULL,
              revoked_by      BIGINT UNSIGNED  NULL,
              created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              revoked_at      DATETIME         NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_service_secret_ref (secret_ref),
              KEY idx_service_secret_owner (owner_type, owner_id),
              CONSTRAINT fk_service_secret_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_service_secret_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE service_secret_versions (
              id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
              secret_id     BIGINT UNSIGNED  NOT NULL,
              version       INT UNSIGNED     NOT NULL,
              ciphertext    VARBINARY(4096)  NOT NULL,
              nonce         VARBINARY(12)    NOT NULL,
              tag           VARBINARY(16)    NOT NULL,
              cipher        VARCHAR(32)      NOT NULL DEFAULT 'aes-256-gcm',
              key_version   INT UNSIGNED     NOT NULL DEFAULT 1,
              state         ENUM('current','retired','destroyed') NOT NULL DEFAULT 'current',
              created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              retire_after  DATETIME         NULL,
              retired_at    DATETIME         NULL,
              destroyed_at  DATETIME         NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_service_secret_version (secret_id, version),
              KEY idx_service_secret_prune (state, retire_after),
              CONSTRAINT fk_service_secret_version_secret FOREIGN KEY (secret_id) REFERENCES service_secrets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS service_secret_versions');
        $pdo->exec('DROP TABLE IF EXISTS service_secrets');
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'service_secret'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting') NOT NULL
        SQL);
    }
};
