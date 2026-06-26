<?php

declare(strict_types=1);

/** 0036 · follows — asymmetric follow graph, user→user in v1 (COMMUNITY §11). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE follows (
              user_id     BIGINT UNSIGNED NOT NULL,
              target_type ENUM('user','tag','board') NOT NULL DEFAULT 'user',
              target_id   BIGINT UNSIGNED NOT NULL,
              created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (user_id, target_type, target_id),
              KEY idx_follow_target (target_type, target_id),
              CONSTRAINT fk_follow_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS follows');
    }
};
