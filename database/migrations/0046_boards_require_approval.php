<?php

declare(strict_types=1);

/**
 * 0046 · boards.require_approval (P3-05, ADMIN §10.2 hold queue).
 *
 * Like `is_pending`, this column is in the consolidated SCHEMA.md shape but was
 * never built in Phases 1–2. Phase 3 wires it to the approval-hold path: when a
 * board requires approval, new threads/replies are created pending and surface
 * in the moderation approval queue. Additive, default 0 (no behaviour change).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE boards ADD COLUMN require_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_anonymous');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE boards DROP COLUMN require_approval');
    }
};
