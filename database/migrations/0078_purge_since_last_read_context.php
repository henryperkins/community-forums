<?php

declare(strict_types=1);

/**
 * 0078 · Purge regenerable unread-context cache after anonymous-author masking.
 *
 * Rows generated before the masking fix may contain an anonymous poster's real
 * username or display name in context_text. The cache is not authoritative and
 * is regenerated on the next eligible thread read, so deleting every row is the
 * only safe upgrade path. Rollback intentionally cannot reconstruct purged data.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('DELETE FROM since_last_read_context');
    }

    public function down(\PDO $pdo): void
    {
        // Intentionally irreversible: cached context is neither authoritative
        // nor safe to reconstruct after it has been purged.
    }
};
