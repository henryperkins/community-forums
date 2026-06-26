<?php

declare(strict_types=1);

/**
 * 0023 · email_deliveries — durable per-send queue + delivery log (ADMIN §10.1).
 * idempotency_key = post_id+':'+user_id for transactional 'instant' fan-out
 * (DESIGN §9.6); NULL for digest/test/system (InnoDB permits multiple NULLs).
 * uq_deliv_idem dedupes one send per (post, recipient).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE email_deliveries (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id         BIGINT UNSIGNED NULL,
              email           VARCHAR(255) NOT NULL,
              kind            ENUM('instant','digest','test','system') NOT NULL,
              subject         VARCHAR(255) NULL,
              status          ENUM('queued','sent','bounced','complained','suppressed','failed') NOT NULL DEFAULT 'queued',
              error           VARCHAR(255) NULL,
              message_id      VARCHAR(191) NULL,
              idempotency_key VARCHAR(191) NULL,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              sent_at         DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_deliv_idem (idempotency_key),
              KEY idx_deliv_user (user_id, created_at),
              KEY idx_deliv_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS email_deliveries');
    }
};
