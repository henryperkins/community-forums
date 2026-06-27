<?php

declare(strict_types=1);

/**
 * 0044 · submission_idempotency — at-most-once logical submit (P3-03,
 * PHASE_3_PLAN §8.5: "A logical submit creates at most one thread/post/DM for a
 * user/context/idempotency key").
 *
 * The composer sends a client-generated idempotency token with each new-thread /
 * reply / DM submit. The first commit records (user, key) → result here inside
 * the same transaction; a duplicate (double-click, retry, browser resend,
 * optimistic-client retry) collides on the unique key and replays the original
 * result instead of creating a second post.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE submission_idempotency (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              idem_key    CHAR(64)        NOT NULL,                 -- sha256 of the client token
              context     VARCHAR(32)     NOT NULL,                 -- thread | reply | dm
              result_type VARCHAR(32)     NOT NULL,                 -- thread | post | dm_message
              result_id   BIGINT UNSIGNED NOT NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_idem (user_id, idem_key),
              KEY idx_idem_sweep (created_at),
              CONSTRAINT fk_idem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS submission_idempotency');
    }
};
