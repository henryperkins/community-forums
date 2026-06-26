<?php

declare(strict_types=1);

/** 0016 · board_moderators — per-board moderator assignments (DESIGN §8.2). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE board_moderators (
              board_id  BIGINT UNSIGNED NOT NULL,
              user_id   BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (board_id, user_id),
              CONSTRAINT fk_bmod_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
              CONSTRAINT fk_bmod_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS board_moderators');
    }
};
