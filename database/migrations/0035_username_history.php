<?php

declare(strict_types=1);

/** 0035 · username_history — username change history for redirects + moderation (USER §7.1). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE username_history (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id      BIGINT UNSIGNED NOT NULL,
              old_username VARCHAR(32)     NOT NULL,
              changed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_uh_user (user_id),
              KEY idx_uh_old (old_username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS username_history');
    }
};
