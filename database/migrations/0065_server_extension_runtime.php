<?php

declare(strict_types=1);

/**
 * 0065 - Phase 5 Gate B server-extension runtime state.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE server_extension_handlers (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              installed_package_id BIGINT UNSIGNED NOT NULL,
              handler_key          VARCHAR(190)    NOT NULL,
              entrypoint           VARCHAR(255)    NOT NULL,
              events_json          JSON            NULL,
              jobs_json            JSON            NULL,
              permissions_json     JSON            NULL,
              resource_limits_json JSON            NULL,
              storage_quota_bytes  BIGINT UNSIGNED NOT NULL DEFAULT 0,
              status               ENUM('enabled','disabled','quarantined') NOT NULL DEFAULT 'disabled',
              quarantine_reason    VARCHAR(255)    NULL,
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_extension_handler (installed_package_id, handler_key),
              KEY idx_extension_handler_status (status),
              CONSTRAINT fk_extension_handler_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE server_extension_jobs (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              handler_id    BIGINT UNSIGNED NOT NULL,
              event_name    VARCHAR(190)    NULL,
              payload_json  JSON            NULL,
              status        ENUM('queued','running','succeeded','failed','quarantined') NOT NULL DEFAULT 'queued',
              attempts      INT UNSIGNED    NOT NULL DEFAULT 0,
              max_attempts  INT UNSIGNED    NOT NULL DEFAULT 3,
              available_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              locked_at     DATETIME        NULL,
              last_error    VARCHAR(255)    NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_extension_job_claim (status, available_at, id),
              KEY idx_extension_job_handler (handler_id),
              CONSTRAINT fk_extension_job_handler FOREIGN KEY (handler_id) REFERENCES server_extension_handlers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE server_extension_runs (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              job_id        BIGINT UNSIGNED NULL,
              handler_id    BIGINT UNSIGNED NOT NULL,
              status        ENUM('succeeded','failed','timeout','quarantined') NOT NULL,
              exit_code     INT             NULL,
              duration_ms   INT UNSIGNED    NULL,
              output_bytes  INT UNSIGNED    NOT NULL DEFAULT 0,
              stdout_json   JSON            NULL,
              error         VARCHAR(255)    NULL,
              started_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              finished_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_extension_run_job (job_id),
              KEY idx_extension_run_handler (handler_id, started_at),
              CONSTRAINT fk_extension_run_job FOREIGN KEY (job_id) REFERENCES server_extension_jobs(id) ON DELETE SET NULL,
              CONSTRAINT fk_extension_run_handler FOREIGN KEY (handler_id) REFERENCES server_extension_handlers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE server_extension_kv (
              installed_package_id BIGINT UNSIGNED NOT NULL,
              kv_key               VARCHAR(190)    NOT NULL,
              value_blob           MEDIUMBLOB      NOT NULL,
              bytes                INT UNSIGNED    NOT NULL,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (installed_package_id, kv_key),
              CONSTRAINT fk_extension_kv_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS server_extension_kv');
        $pdo->exec('DROP TABLE IF EXISTS server_extension_runs');
        $pdo->exec('DROP TABLE IF EXISTS server_extension_jobs');
        $pdo->exec('DROP TABLE IF EXISTS server_extension_handlers');
    }
};
