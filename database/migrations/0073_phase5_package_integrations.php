<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 5 (P5-04 / P5-07-A part 2): package integration runtime.
 * Adds per-install settings + package-owned credential links, and widens the
 * two enums the credential/settings lifecycle and publisher security-response
 * console depend on. Additive-only; inert until the Inc 5 services + `package_registry`.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE installed_package_settings (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              installed_package_id BIGINT UNSIGNED NOT NULL,
              setting_key          VARCHAR(80)     NOT NULL,
              value_json           MEDIUMTEXT      NULL,
              secret_ref           VARCHAR(64)     NULL,
              is_secret            TINYINT(1)      NOT NULL DEFAULT 0,
              updated_by           BIGINT UNSIGNED NULL,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_install_setting (installed_package_id, setting_key),
              KEY idx_install_setting_secret (secret_ref),
              CONSTRAINT fk_install_setting_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
              CONSTRAINT fk_install_setting_user    FOREIGN KEY (updated_by)           REFERENCES users(id)              ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE installed_package_credentials (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              installed_package_id BIGINT UNSIGNED NOT NULL,
              kind                 ENUM('api_token','webhook') NOT NULL,
              api_token_id         BIGINT UNSIGNED NULL,
              webhook_id           BIGINT UNSIGNED NULL,
              label                VARCHAR(120)    NOT NULL,
              scopes_json          MEDIUMTEXT      NULL,
              events_json          MEDIUMTEXT      NULL,
              created_by           BIGINT UNSIGNED NULL,
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              revoked_at           DATETIME        NULL,
              PRIMARY KEY (id),
              KEY idx_install_cred_install   (installed_package_id),
              KEY idx_install_cred_api_token (api_token_id),
              KEY idx_install_cred_webhook   (webhook_id),
              CONSTRAINT fk_install_cred_install   FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
              CONSTRAINT fk_install_cred_api_token FOREIGN KEY (api_token_id)         REFERENCES api_tokens(id)         ON DELETE SET NULL,
              CONSTRAINT fk_install_cred_webhook   FOREIGN KEY (webhook_id)           REFERENCES webhooks(id)           ON DELETE SET NULL,
              CONSTRAINT fk_install_cred_user      FOREIGN KEY (created_by)           REFERENCES users(id)              ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge',
                                'theme_activate','theme_rollback','theme_deactivate',
                                'settings_update','credential_mint','credential_revoke') NOT NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM package_history WHERE event IN ('settings_update','credential_mint','credential_revoke')");
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'publisher'");

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge',
                                'theme_activate','theme_rollback','theme_deactivate') NOT NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package') NOT NULL
        SQL);

        $pdo->exec('DROP TABLE IF EXISTS installed_package_credentials');
        $pdo->exec('DROP TABLE IF EXISTS installed_package_settings');
    }
};
