<?php

declare(strict_types=1);

/**
 * 0042 · users — Phase 3 columns. `avatar_path` is the local copy of a user's
 * avatar produced by the P3-04 media pipeline (avatar_source='upload') or a
 * resolved Gravatar (avatar_source='gravatar'); `onboarded_at` records product-
 * tour completion so it persists cross-device (PHASE_3_PLAN §8.1 / P3-11, P3-12;
 * SCHEMA.md §1 — these columns are in the consolidated shape but BUILT here).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              ADD COLUMN avatar_path  VARCHAR(255) NULL AFTER pronouns,
              ADD COLUMN onboarded_at DATETIME     NULL AFTER email_verified_at
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              DROP COLUMN avatar_path,
              DROP COLUMN onboarded_at
        SQL);
    }
};
