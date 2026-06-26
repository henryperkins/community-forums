<?php

declare(strict_types=1);

/** 0008 · threads — conversations within a board (FK → boards, users). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE threads (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              board_id          BIGINT UNSIGNED NOT NULL,
              user_id           BIGINT UNSIGNED NOT NULL,
              title             VARCHAR(160)    NOT NULL,
              slug              VARCHAR(180)    NOT NULL,
              is_pinned         TINYINT(1)      NOT NULL DEFAULT 0,
              is_locked         TINYINT(1)      NOT NULL DEFAULT 0,
              is_deleted        TINYINT(1)      NOT NULL DEFAULT 0,
              reply_count       INT UNSIGNED    NOT NULL DEFAULT 0,
              view_count        INT UNSIGNED    NOT NULL DEFAULT 0,
              last_post_id      BIGINT UNSIGNED NULL,
              last_post_user_id BIGINT UNSIGNED NULL,
              last_post_at      DATETIME        NULL,
              created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_threads_inbox (board_id, is_pinned DESC, last_post_at DESC),
              KEY idx_threads_author (user_id),
              CONSTRAINT fk_threads_board FOREIGN KEY (board_id) REFERENCES boards(id),
              CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS threads');
    }
};
