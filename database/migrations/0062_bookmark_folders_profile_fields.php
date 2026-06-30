<?php

declare(strict_types=1);

/**
 * 0062 - Bookmark folders and bounded custom profile fields.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_bookmark_folders (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              name        VARCHAR(80)     NOT NULL,
              position    INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at  DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_thread_bookmark_folder_name (user_id, name),
              KEY idx_thread_bookmark_folder_user (user_id, position, id),
              CONSTRAINT fk_thread_bookmark_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_bookmark_folder_threads (
              folder_id   BIGINT UNSIGNED NOT NULL,
              thread_id   BIGINT UNSIGNED NOT NULL,
              position    INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (folder_id, thread_id),
              KEY idx_thread_bookmark_folder_thread (thread_id),
              CONSTRAINT fk_thread_bookmark_item_folder FOREIGN KEY (folder_id) REFERENCES thread_bookmark_folders(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_bookmark_item_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE user_profile_fields (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              label       VARCHAR(40)     NOT NULL,
              value       VARCHAR(160)    NOT NULL,
              position    TINYINT UNSIGNED NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at  DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_user_profile_field_position (user_id, position),
              KEY idx_user_profile_field_user (user_id, position),
              CONSTRAINT fk_user_profile_field_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS user_profile_fields');
        $pdo->exec('DROP TABLE IF EXISTS thread_bookmark_folder_threads');
        $pdo->exec('DROP TABLE IF EXISTS thread_bookmark_folders');
    }
};
