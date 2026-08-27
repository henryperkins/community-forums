<?php

declare(strict_types=1);

/**
 * 0082 · Cover chronological per-thread read-cursor lookups.
 *
 * ADDITIVE. `last_read_post_id` remains the DECISIONS §3 cursor identity; the
 * application resolves that post's (created_at, id) tuple. This index covers
 * the live-post filter and render-order range used by first-unread resolution,
 * context generation, pagination rank, and chronological repairs.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        if (!$this->indexExists($pdo, 'posts', 'idx_posts_thread_read')) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE posts
                  ADD INDEX idx_posts_thread_read (thread_id, is_deleted, is_pending, created_at, id)
            SQL);
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'posts', 'idx_posts_thread_read')) {
            $pdo->exec('ALTER TABLE posts DROP INDEX idx_posts_thread_read');
        }
    }

    private function indexExists(\PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND INDEX_NAME = :index_name
        SQL);
        $stmt->execute([':table' => $table, ':index_name' => $index]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
