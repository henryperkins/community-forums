<?php

declare(strict_types=1);

/** 0037 · badges — fixed badge catalogue (COMMUNITY §11). Seeded idempotently in P2-09. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE badges (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug        VARCHAR(48)  NOT NULL,
              name        VARCHAR(64)  NOT NULL,
              description VARCHAR(255) NOT NULL,
              icon        VARCHAR(64)  NULL,
              kind        ENUM('auto','manual') NOT NULL DEFAULT 'auto',
              PRIMARY KEY (id),
              UNIQUE KEY uq_badge_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS badges');
    }
};
