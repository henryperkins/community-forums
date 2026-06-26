<?php

declare(strict_types=1);

/**
 * 0025 · conversation_participants — membership + per-participant read state
 * (DESIGN §8.2). last_read_message_id drives DM unread counts.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE conversation_participants (
              conversation_id      BIGINT UNSIGNED NOT NULL,
              user_id              BIGINT UNSIGNED NOT NULL,
              last_read_message_id BIGINT UNSIGNED NULL,
              PRIMARY KEY (conversation_id, user_id),
              KEY idx_cp_user (user_id),
              CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
              CONSTRAINT fk_cp_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS conversation_participants');
    }
};
