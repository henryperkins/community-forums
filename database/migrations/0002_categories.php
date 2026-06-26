<?php

declare(strict_types=1);

/** 0002 · categories — presentation/ordering grouping for boards. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE categories (
              id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name     VARCHAR(64)     NOT NULL,
              position INT             NOT NULL DEFAULT 0,
              PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS categories');
    }
};
