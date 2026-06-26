<?php

declare(strict_types=1);

/**
 * 0017 · thread_user — per-user thread state: read position + star (DESIGN §8.2).
 * Unread = thread.last_post_id > last_read_post_id. is_subscribed omitted
 * (superseded by `subscriptions`, SCHEMA §7.4).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_user (
              user_id           BIGINT UNSIGNED NOT NULL,
              thread_id         BIGINT UNSIGNED NOT NULL,
              last_read_post_id BIGINT UNSIGNED NULL,
              is_starred        TINYINT(1)      NOT NULL DEFAULT 0,
              PRIMARY KEY (user_id, thread_id),
              KEY idx_tu_starred (user_id, is_starred),
              CONSTRAINT fk_tu_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
              CONSTRAINT fk_tu_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS thread_user');
    }
};
