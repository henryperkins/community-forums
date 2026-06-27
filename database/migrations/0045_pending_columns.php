<?php

declare(strict_types=1);

/**
 * 0045 · threads/posts approval-hold flag (P3-05, ADMIN §10.2).
 *
 * `is_pending` appears in the consolidated SCHEMA.md shape but was never built in
 * Phases 1–2 (SCHEMA §7: a column's presence in SCHEMA.md is not evidence its
 * migration shipped). Phase 3's anti-abuse holds + board approval queue need it,
 * so it is created here. Additive and default 0, so existing content stays live.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE threads
              ADD COLUMN is_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted,
              ADD KEY idx_threads_pending (is_pending, board_id)
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE posts
              ADD COLUMN is_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted,
              ADD KEY idx_posts_pending (is_pending, thread_id)
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE threads DROP KEY idx_threads_pending, DROP COLUMN is_pending');
        $pdo->exec('ALTER TABLE posts DROP KEY idx_posts_pending, DROP COLUMN is_pending');
    }
};
