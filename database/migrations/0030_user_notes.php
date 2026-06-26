<?php

declare(strict_types=1);

/** 0030 · user_notes — private staff notes on an account, never user-visible (ADMIN §10.1). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_notes (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              subject_user_id BIGINT UNSIGNED NOT NULL,
              author_id       BIGINT UNSIGNED NOT NULL,
              body            TEXT            NOT NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_notes_subject (subject_user_id, created_at),
              CONSTRAINT fk_notes_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS user_notes');
    }
};
