<?php

declare(strict_types=1);

/**
 * 0021 · notifications — in-app notification feed (DESIGN §8.2, enum reconciled
 * SCHEMA §7.3/#13). actor_id/thread_id/post_id/conversation_id are minimal
 * identifiers re-checked against the read gate at render/click time; no FKs
 * (conversation_id forward-references a later table — SCHEMA §6 FK note).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE notifications (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id         BIGINT UNSIGNED NOT NULL,
              type            ENUM('reply','mention','reaction','dm','mod',
                                   'new_post','new_thread','follow','badge','solved',
                                   'announcement') NOT NULL,
              actor_id        BIGINT UNSIGNED NULL,
              thread_id       BIGINT UNSIGNED NULL,
              post_id         BIGINT UNSIGNED NULL,
              conversation_id BIGINT UNSIGNED NULL,
              is_read         TINYINT(1)      NOT NULL DEFAULT 0,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_notif_user (user_id, is_read, created_at),
              CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS notifications');
    }
};
