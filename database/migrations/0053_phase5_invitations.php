<?php

declare(strict_types=1);

/**
 * 0053 · Phase 5 foundation — invitations + redemptions
 * (PHASE_5_PLAN §8.2 #16, §8.3 grp 5).
 *
 * ADDITIVE + INERT. Backs the invitation lifecycle (P5-13), created deploy-dark
 * behind the `invitations` flag. Invitations start empty; existing users are
 * never retroactively reclassified as invited (§8.4).
 *
 * Tokens are stored HASH-ONLY (sha256) — the raw token is shown once at creation
 * and never persisted/logged (§9 "Invitation binding"). An invitation is
 * onboarding evidence, NOT authority (decision #36): `onboarding_role_id` may
 * reference only a non-privileged role, enforced by the invitation service —
 * granting a sensitive/custom role always requires a separate authenticated
 * assignment/approval path. Registration-mode enforcement itself stays in
 * settings (a pre-Phase-5 admin control, §4 conditional carryover); this table
 * does not substitute a token for registration enforcement.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE invitations (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              token_hash         CHAR(64)        NOT NULL,             -- sha256 of the single-use token (raw never stored)
              created_by         BIGINT UNSIGNED NULL,
              email              VARCHAR(255)    NULL,                 -- optional binding to one address
              domain             VARCHAR(190)    NULL,                 -- optional binding to an approved domain
              onboarding_role_id BIGINT UNSIGNED NULL,                -- optional NON-privileged onboarding role only
              onboarding_board_id BIGINT UNSIGNED NULL,               -- optional board membership grant
              max_uses           INT UNSIGNED    NOT NULL DEFAULT 1,
              used_count         INT UNSIGNED    NOT NULL DEFAULT 0,
              expires_at         DATETIME        NULL,
              revoked_at         DATETIME        NULL,
              revoked_by         BIGINT UNSIGNED NULL,
              created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_invitation_token (token_hash),
              KEY idx_invitation_email (email),
              KEY idx_invitation_creator (created_by),
              KEY idx_invitation_expires (expires_at),
              CONSTRAINT fk_invite_creator FOREIGN KEY (created_by)          REFERENCES users(id)  ON DELETE SET NULL,
              CONSTRAINT fk_invite_revoker FOREIGN KEY (revoked_by)          REFERENCES users(id)  ON DELETE SET NULL,
              CONSTRAINT fk_invite_role    FOREIGN KEY (onboarding_role_id)  REFERENCES roles(id)  ON DELETE SET NULL,
              CONSTRAINT fk_invite_board   FOREIGN KEY (onboarding_board_id) REFERENCES boards(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // Redemption is atomic with account creation/link (§8.5); concurrent
        // redemption cannot exceed max_uses (enforced by the service via a guarded
        // UPDATE). One row per successful redemption.
        $pdo->exec(<<<'SQL'
            CREATE TABLE invitation_redemptions (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              invitation_id BIGINT UNSIGNED NOT NULL,
              user_id       BIGINT UNSIGNED NULL,                     -- account created/linked on redemption
              ip            VARBINARY(16)   NULL,                     -- packed via inet_pton (project convention)
              redeemed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_redemption_invite (invitation_id),
              KEY idx_redemption_user (user_id),
              CONSTRAINT fk_redemption_invite FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
              CONSTRAINT fk_redemption_user   FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS invitation_redemptions');
        $pdo->exec('DROP TABLE IF EXISTS invitations');
    }
};
