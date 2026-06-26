<?php

declare(strict_types=1);

/**
 * 0020 · subscriptions — per-channel + frequency thread/board subscriptions
 * (DESIGN §8.3). Supersedes thread_user.is_subscribed. A thread setting
 * overrides its board (precedence resolved in app logic).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE subscriptions (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id        BIGINT UNSIGNED NOT NULL,
              target_type    ENUM('board','thread') NOT NULL,
              target_id      BIGINT UNSIGNED NOT NULL,
              email_enabled  TINYINT(1) NOT NULL DEFAULT 1,
              in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
              frequency      ENUM('instant','daily','off') NOT NULL DEFAULT 'instant',
              created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_sub (user_id, target_type, target_id),
              KEY idx_sub_target (target_type, target_id),
              CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS subscriptions');
    }
};
