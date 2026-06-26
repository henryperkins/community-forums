<?php

declare(strict_types=1);

/**
 * 0032 · oauth_identities — linked third-party logins (USER §7.1). avatar_url
 * caches the imported provider avatar (sets users.avatar_source='oauth').
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE oauth_identities (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id          BIGINT UNSIGNED NOT NULL,
              provider         ENUM('google','apple','github') NOT NULL,
              provider_user_id VARCHAR(191)    NOT NULL,
              email            VARCHAR(255)    NULL,
              email_verified   TINYINT(1)      NOT NULL DEFAULT 0,
              avatar_url       VARCHAR(512)    NULL,
              created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_login_at    DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_provider_identity (provider, provider_user_id),
              KEY idx_oauth_user (user_id),
              CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS oauth_identities');
    }
};
