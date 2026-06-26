<?php

declare(strict_types=1);

/** 0038 · user_badges — awarded badges (COMMUNITY §11). awarded_by set for manual grants. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_badges (
              user_id    BIGINT UNSIGNED NOT NULL,
              badge_id   BIGINT UNSIGNED NOT NULL,
              awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              awarded_by BIGINT UNSIGNED NULL,
              PRIMARY KEY (user_id, badge_id),
              CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
              CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS user_badges');
    }
};
