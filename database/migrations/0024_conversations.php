<?php

declare(strict_types=1);

/** 0024 · conversations — DM conversation header (DESIGN §8.2). One-to-one in v1. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE conversations (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_message_at DATETIME        NULL,
              PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS conversations');
    }
};
