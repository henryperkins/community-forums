<?php

declare(strict_types=1);

/**
 * 0057 - Phase 5 Gate A prerequisite (B2 sub-project 3): webhook delivery.
 *
 * ADDITIVE. Endpoint config stores only a SecretVault svcsec_* reference.
 * The delivery ledger adds idempotency, retry/backoff, and dead-letter state.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE webhooks (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name                 VARCHAR(80)     NOT NULL,
              url                  VARCHAR(512)    NOT NULL,
              events               JSON            NOT NULL,
              secret_ref           VARCHAR(64)     NOT NULL,
              is_active            TINYINT(1)      NOT NULL DEFAULT 1,
              consecutive_failures INT UNSIGNED    NOT NULL DEFAULT 0,
              disabled_at          DATETIME        NULL,
              disabled_reason      VARCHAR(190)    NULL,
              last_status          INT             NULL,
              last_delivered_at    DATETIME        NULL,
              created_by           BIGINT UNSIGNED NOT NULL,
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_webhook_active (is_active),
              CONSTRAINT fk_webhook_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE webhook_deliveries (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              webhook_id      BIGINT UNSIGNED NOT NULL,
              event_type      VARCHAR(80)     NOT NULL,
              event_id        VARCHAR(64)     NOT NULL,
              payload         MEDIUMTEXT      NOT NULL,
              status          ENUM('queued','delivered','dead') NOT NULL DEFAULT 'queued',
              attempt_count   INT UNSIGNED    NOT NULL DEFAULT 0,
              max_attempts    INT UNSIGNED    NOT NULL,
              next_attempt_at DATETIME        NULL,
              last_attempt_at DATETIME        NULL,
              response_status INT             NULL,
              error           VARCHAR(255)    NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              delivered_at    DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_delivery_idem (webhook_id, event_type, event_id),
              KEY idx_delivery_claim (status, next_attempt_at),
              CONSTRAINT fk_delivery_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'webhook'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS webhook_deliveries');
        $pdo->exec('DROP TABLE IF EXISTS webhooks');
    }
};
