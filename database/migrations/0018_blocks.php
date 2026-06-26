<?php

declare(strict_types=1);

/**
 * 0018 · blocks — block list (USER §7.1). Blocked users can't DM/@mention the
 * blocker and their notifications to the blocker are suppressed. Pulled forward
 * to the baseline so mentions (P2-05) and DMs (P2-07) have a block predicate.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE blocks (
              user_id         BIGINT UNSIGNED NOT NULL,
              blocked_user_id BIGINT UNSIGNED NOT NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (user_id, blocked_user_id),
              KEY idx_blocks_blocked (blocked_user_id),
              CONSTRAINT fk_block_user    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS blocks');
    }
};
