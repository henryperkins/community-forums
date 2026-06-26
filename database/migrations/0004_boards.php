<?php

declare(strict_types=1);

/** 0004 · boards — #channels within a category (FK → categories). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE boards (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              category_id     BIGINT UNSIGNED NOT NULL,
              slug            VARCHAR(64)     NOT NULL,
              name            VARCHAR(80)     NOT NULL,
              description     VARCHAR(255)    NULL,
              position        INT             NOT NULL DEFAULT 0,
              post_min_role   ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
              visibility      ENUM('public','hidden','private') NOT NULL DEFAULT 'public',
              allow_anonymous TINYINT(1)      NOT NULL DEFAULT 0,
              thread_count    INT UNSIGNED    NOT NULL DEFAULT 0,
              post_count      INT UNSIGNED    NOT NULL DEFAULT 0,
              last_thread_id  BIGINT UNSIGNED NULL,
              last_post_at    DATETIME        NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_boards_slug (slug),
              KEY idx_boards_cat_pos (category_id, position),
              CONSTRAINT fk_boards_category FOREIGN KEY (category_id) REFERENCES categories(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS boards');
    }
};
