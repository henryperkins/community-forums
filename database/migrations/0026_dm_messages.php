<?php

declare(strict_types=1);

/** 0026 · dm_messages — messages within a conversation (DESIGN §8.2). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE dm_messages (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              conversation_id BIGINT UNSIGNED NOT NULL,
              user_id         BIGINT UNSIGNED NOT NULL,
              body            TEXT            NOT NULL,
              body_html       MEDIUMTEXT      NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_dm_conv (conversation_id, created_at),
              CONSTRAINT fk_dm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
              CONSTRAINT fk_dm_user FOREIGN KEY (user_id)         REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS dm_messages');
    }
};
