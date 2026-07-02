<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 4 (P5-03): declarative theme package builds/assets/state.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_theme_builds (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              installed_package_id BIGINT UNSIGNED NOT NULL,
              package_id           BIGINT UNSIGNED NOT NULL,
              release_id           BIGINT UNSIGNED NOT NULL,
              source_digest        CHAR(64)        NOT NULL,
              token_schema_version SMALLINT UNSIGNED NOT NULL,
              tokens_json          MEDIUMTEXT      NOT NULL,
              validation_json      MEDIUMTEXT      NOT NULL,
              css                  MEDIUMTEXT      NOT NULL,
              css_digest           CHAR(64)        NOT NULL,
              built_by             BIGINT UNSIGNED NULL,
              created_at           DATETIME        NOT NULL DEFAULT (UTC_TIMESTAMP()),
              PRIMARY KEY (id),
              UNIQUE KEY uniq_theme_build (installed_package_id, source_digest),
              KEY idx_theme_build_css_digest (css_digest),
              CONSTRAINT fk_theme_build_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
              CONSTRAINT fk_theme_build_package FOREIGN KEY (package_id)           REFERENCES packages(id)            ON DELETE CASCADE,
              CONSTRAINT fk_theme_build_release FOREIGN KEY (release_id)           REFERENCES package_releases(id)    ON DELETE CASCADE,
              CONSTRAINT fk_theme_build_user    FOREIGN KEY (built_by)             REFERENCES users(id)               ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE package_theme_assets (
              id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              build_id BIGINT UNSIGNED NOT NULL,
              name     VARCHAR(32)     NOT NULL,
              mime     VARCHAR(64)     NOT NULL,
              bytes    MEDIUMBLOB      NOT NULL,
              byte_len INT UNSIGNED    NOT NULL,
              digest   CHAR(64)        NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uniq_theme_asset_name (build_id, name),
              KEY idx_theme_asset_digest (digest),
              CONSTRAINT fk_theme_asset_build FOREIGN KEY (build_id) REFERENCES package_theme_builds(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE theme_state (
              id              TINYINT UNSIGNED NOT NULL,
              active_build_id BIGINT UNSIGNED NULL,
              lkg_build_id    BIGINT UNSIGNED NULL,
              activated_by    BIGINT UNSIGNED NULL,
              activated_at    DATETIME NULL,
              updated_at      DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
              PRIMARY KEY (id),
              CONSTRAINT fk_theme_state_active FOREIGN KEY (active_build_id) REFERENCES package_theme_builds(id) ON DELETE SET NULL,
              CONSTRAINT fk_theme_state_lkg    FOREIGN KEY (lkg_build_id)    REFERENCES package_theme_builds(id) ON DELETE SET NULL,
              CONSTRAINT fk_theme_state_user   FOREIGN KEY (activated_by)    REFERENCES users(id)                ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec('INSERT IGNORE INTO theme_state (id) VALUES (1)');

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge',
                                'theme_activate','theme_rollback','theme_deactivate') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM package_history WHERE event IN ('theme_activate','theme_rollback','theme_deactivate')");

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge') NOT NULL
        SQL);

        $pdo->exec('DROP TABLE IF EXISTS theme_state');
        $pdo->exec('DROP TABLE IF EXISTS package_theme_assets');
        $pdo->exec('DROP TABLE IF EXISTS package_theme_builds');
    }
};
