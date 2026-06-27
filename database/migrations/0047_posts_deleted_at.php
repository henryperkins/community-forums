<?php

declare(strict_types=1);

/**
 * 0047 · posts.deleted_at — soft-delete timestamp (P3-04 retention).
 *
 * The orphan-attachment sweep must NOT reclaim a deleted post's media inside the
 * restore/appeal window (PHASE_3_PLAN §8.5: physical deletion does not precede
 * rollback/appeal requirements). Recording WHEN a post was soft-deleted lets the
 * sweep apply a grace window before deleting the underlying files. Additive,
 * NULL default; restore() clears it so a restored post is never swept.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE posts ADD COLUMN deleted_at DATETIME NULL AFTER deleted_by');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE posts DROP COLUMN deleted_at');
    }
};
