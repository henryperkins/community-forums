<?php

declare(strict_types=1);

/**
 * 0054 · Phase 5 Gate A prerequisite — TOTP + recovery codes.
 *
 * ADDITIVE + OPT-IN. This resolves the ADR-0004 B1 blocker without requiring
 * MFA for ordinary users by default. TOTP secrets are encrypted with the
 * application key before storage; recovery/login challenge tokens are hash-only.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_totp_credentials (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id             BIGINT UNSIGNED NOT NULL,
              secret_ciphertext   VARBINARY(255)  NOT NULL,
              secret_nonce        VARBINARY(12)   NOT NULL,
              secret_tag          VARBINARY(16)   NOT NULL,
              algorithm           ENUM('sha1')    NOT NULL DEFAULT 'sha1',
              digits              TINYINT UNSIGNED NOT NULL DEFAULT 6,
              period_seconds      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
              enabled_at          DATETIME        NULL,
              verified_at         DATETIME        NULL,
              disabled_at         DATETIME        NULL,
              last_used_step      BIGINT UNSIGNED NULL,
              created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_totp_user (user_id),
              KEY idx_totp_enabled (enabled_at, disabled_at),
              CONSTRAINT fk_totp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE user_recovery_codes (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              batch_id    CHAR(32)        NOT NULL,
              code_hash   CHAR(64)        NOT NULL,
              used_at     DATETIME        NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_recovery_code_hash (code_hash),
              KEY idx_recovery_user_unused (user_id, used_at),
              CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE mfa_login_challenges (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              token_hash  CHAR(64)        NOT NULL,
              next_path   VARCHAR(255)    NOT NULL DEFAULT '/',
              ip          VARBINARY(16)   NULL,
              user_agent  VARCHAR(255)    NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at  DATETIME        NOT NULL,
              consumed_at DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_mfa_login_token (token_hash),
              KEY idx_mfa_login_user (user_id, consumed_at),
              KEY idx_mfa_login_expires (expires_at),
              CONSTRAINT fk_mfa_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS mfa_login_challenges');
        $pdo->exec('DROP TABLE IF EXISTS user_recovery_codes');
        $pdo->exec('DROP TABLE IF EXISTS user_totp_credentials');
    }
};
