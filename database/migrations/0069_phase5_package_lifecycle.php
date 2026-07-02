<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 3 (P5-02): installed-package lifecycle columns.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              ADD COLUMN pinned               TINYINT(1)  NOT NULL DEFAULT 0 AFTER health,
              ADD COLUMN update_policy        ENUM('manual','notify') NOT NULL DEFAULT 'manual' AFTER pinned,
              ADD COLUMN staged_release_id    BIGINT UNSIGNED NULL AFTER update_policy,
              ADD COLUMN staged_digest        CHAR(64)    NULL AFTER staged_release_id,
              ADD COLUMN settings_json        MEDIUMTEXT  NULL AFTER staged_digest,
              ADD COLUMN export_json          MEDIUMTEXT  NULL AFTER settings_json,
              ADD COLUMN exported_at          DATETIME    NULL AFTER export_json,
              ADD COLUMN retain_until         DATETIME    NULL AFTER exported_at,
              ADD COLUMN uninstalled_at       DATETIME    NULL AFTER retain_until,
              ADD COLUMN quarantine_reason    VARCHAR(255) NULL AFTER uninstalled_at,
              ADD COLUMN last_health_check_at DATETIME    NULL AFTER quarantine_reason,
              ADD CONSTRAINT fk_installed_staged FOREIGN KEY (staged_release_id)
                REFERENCES package_releases(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              MODIFY state ENUM('installed','enabled','disabled','quarantined','uninstalling','uninstalled')
                NOT NULL DEFAULT 'installed'
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge') NOT NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_package_permissions
              MODIFY kind ENUM('capability','data_class','outbound_host','job','broker_service','api_scope','event') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM installed_package_permissions WHERE kind IN ('api_scope','event')");
        $pdo->exec("DELETE FROM package_history WHERE event IN ('update_staged','export','purge')");
        $pdo->exec("DELETE FROM installed_packages WHERE state = 'uninstalled'");
        $pdo->exec('ALTER TABLE installed_packages DROP FOREIGN KEY fk_installed_staged');
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              MODIFY state ENUM('installed','enabled','disabled','quarantined','uninstalling')
                NOT NULL DEFAULT 'installed'
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              DROP COLUMN last_health_check_at, DROP COLUMN quarantine_reason, DROP COLUMN uninstalled_at,
              DROP COLUMN retain_until, DROP COLUMN exported_at, DROP COLUMN export_json,
              DROP COLUMN settings_json, DROP COLUMN staged_digest, DROP COLUMN staged_release_id,
              DROP COLUMN update_policy, DROP COLUMN pinned
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health') NOT NULL
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_package_permissions
              MODIFY kind ENUM('capability','data_class','outbound_host','job','broker_service') NOT NULL
        SQL);
    }
};
