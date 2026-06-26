<?php

declare(strict_types=1);

/** 0009 · posts — canonical Markdown body + cached sanitised body_html. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE posts (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id      BIGINT UNSIGNED NOT NULL,
              user_id        BIGINT UNSIGNED NOT NULL,
              parent_post_id BIGINT UNSIGNED NULL,
              body           MEDIUMTEXT      NOT NULL,
              body_html      MEDIUMTEXT      NULL,
              is_op          TINYINT(1)      NOT NULL DEFAULT 0,
              is_anonymous   TINYINT(1)      NOT NULL DEFAULT 0,
              is_deleted     TINYINT(1)      NOT NULL DEFAULT 0,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              edited_at      DATETIME        NULL,
              edited_by      BIGINT UNSIGNED NULL,
              deleted_by     BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_posts_thread (thread_id, created_at),
              KEY idx_posts_author (user_id),
              CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id),
              CONSTRAINT fk_posts_user   FOREIGN KEY (user_id)   REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS posts');
    }
};
