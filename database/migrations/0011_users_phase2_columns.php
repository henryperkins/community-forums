<?php

declare(strict_types=1);

/**
 * 0011 · users — Phase 2 column additions (PHASE_2_PLAN §7.1).
 * Cosmetic title/signature, extended profile, privacy/DM/presence controls,
 * timezone-aware digest watermark fields, presence heartbeat, and the first
 * non-monogram avatar source (set by OAuth avatar-import). avatar_path and
 * onboarded_at remain Phase 3.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              ADD COLUMN title                VARCHAR(64)  NULL,
              ADD COLUMN signature            TEXT         NULL,
              ADD COLUMN website              VARCHAR(255) NULL,
              ADD COLUMN pronouns             VARCHAR(32)  NULL,
              ADD COLUMN avatar_source        ENUM('monogram','upload','gravatar','oauth') NOT NULL DEFAULT 'monogram',
              ADD COLUMN profile_visibility   ENUM('public','members') NOT NULL DEFAULT 'public',
              ADD COLUMN allow_dms            ENUM('everyone','members','none') NOT NULL DEFAULT 'members',
              ADD COLUMN show_presence        TINYINT(1)   NOT NULL DEFAULT 1,
              ADD COLUMN timezone             VARCHAR(64)  NULL,
              ADD COLUMN digest_hour          TINYINT      NULL,
              ADD COLUMN last_daily_digest_at DATETIME     NULL,
              ADD COLUMN last_seen_at         DATETIME     NULL,
              ADD KEY idx_users_last_seen (last_seen_at)
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              DROP KEY idx_users_last_seen,
              DROP COLUMN title,
              DROP COLUMN signature,
              DROP COLUMN website,
              DROP COLUMN pronouns,
              DROP COLUMN avatar_source,
              DROP COLUMN profile_visibility,
              DROP COLUMN allow_dms,
              DROP COLUMN show_presence,
              DROP COLUMN timezone,
              DROP COLUMN digest_hour,
              DROP COLUMN last_daily_digest_at,
              DROP COLUMN last_seen_at
        SQL);
    }
};
