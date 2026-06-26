<?php

declare(strict_types=1);

/**
 * 0022 · email_suppressions — bounce/complaint/unsubscribe/manual suppression
 * list (ADMIN §10.1). Email fan-out skips any address present here.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE email_suppressions (
              email      VARCHAR(255) NOT NULL,
              reason     ENUM('bounce','complaint','unsubscribe','manual') NOT NULL,
              created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS email_suppressions');
    }
};
