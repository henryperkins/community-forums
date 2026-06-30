<?php

declare(strict_types=1);

/**
 * 0059 - Account lifecycle, export, and deletion grace period.
 *
 * Adds explicit self-service lifecycle states plus durable deletion requests.
 * Users are not hard-deleted; a purge anonymizes PII while preserving content
 * and audit history.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              MODIFY status ENUM('active','deactivated','pending_deletion','deleted','suspended','banned') NOT NULL DEFAULT 'active'
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE account_deletion_requests (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id      BIGINT UNSIGNED NOT NULL,
              requested_by BIGINT UNSIGNED NOT NULL,
              status       ENUM('pending','canceled','purged') NOT NULL DEFAULT 'pending',
              requested_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              purge_after  DATETIME        NOT NULL,
              canceled_at  DATETIME        NULL,
              canceled_by  BIGINT UNSIGNED NULL,
              purged_at    DATETIME        NULL,
              reason       VARCHAR(255)    NULL,
              PRIMARY KEY (id),
              KEY idx_account_delete_user_status (user_id, status),
              KEY idx_account_delete_due (status, purge_after),
              CONSTRAINT fk_account_delete_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_account_delete_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_account_delete_canceled_by FOREIGN KEY (canceled_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS account_deletion_requests');
        $pdo->exec("UPDATE users SET status = 'active' WHERE status IN ('deactivated','pending_deletion','deleted')");
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              MODIFY status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active'
        SQL);
    }
};
