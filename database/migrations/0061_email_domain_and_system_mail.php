<?php

declare(strict_types=1);

/**
 * 0061 - Email domain verification and system mail payloads.
 *
 * Stores cached SPF/DKIM verification state for the configured From domain and
 * gives system email broadcasts a durable per-recipient payload for worker
 * rendering.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE email_domain_status (
              domain        VARCHAR(255) NOT NULL,
              dkim_selector VARCHAR(64)  NOT NULL DEFAULT 'default',
              spf_status    ENUM('unknown','pass','fail') NOT NULL DEFAULT 'unknown',
              dkim_status   ENUM('unknown','pass','fail') NOT NULL DEFAULT 'unknown',
              details       JSON         NULL,
              checked_at    DATETIME     NULL,
              updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE email_deliveries
              ADD COLUMN payload JSON NULL AFTER subject
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE email_deliveries DROP COLUMN payload');
        $pdo->exec('DROP TABLE IF EXISTS email_domain_status');
    }
};
