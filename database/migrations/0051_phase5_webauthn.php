<?php

declare(strict_types=1);

/**
 * 0051 · Phase 5 foundation — WebAuthn credentials + challenges
 * (PHASE_5_PLAN §8.2 #14, §8.3 grp 4).
 *
 * ADDITIVE + INERT. Backs passkeys (P5-11), created deploy-dark behind the
 * `passkeys` flag. Passkeys are opt-in with NO backfill (§8.4); existing
 * email/password + OAuth paths are unchanged.
 *
 * Only PUBLIC credential material is ever stored (decision #28, §8.5): the
 * private key never reaches the server. The canonical RP ID / allowed origins
 * are owner-approved Milestone-0 policy (not encoded here). Challenges are
 * one-time, short-lived, and bound to a purpose + session/user; they are never
 * persisted in a reusable form beyond their single consumption.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Registered credentials ───────────────────────────────────────────
        // credential_id is the raw WebAuthn credential id (binary). Named,
        // individually revocable, with created/last-used metadata (def-of-done).
        // A non-monotonic sign_count on a synced passkey is a risk SIGNAL, never
        // an automatic permanent lockout (decision #30) — enforced in code.
        $pdo->exec(<<<'SQL'
            CREATE TABLE webauthn_credentials (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id            BIGINT UNSIGNED NOT NULL,
              credential_id      VARBINARY(1023) NOT NULL,             -- raw credential id bytes (WebAuthn L2 caps length at 1023B; 1023B unique index < InnoDB 3072B limit)
              public_key         VARBINARY(1024) NOT NULL,             -- COSE public key (PUBLIC material only)
              sign_count         BIGINT UNSIGNED NOT NULL DEFAULT 0,
              aaguid             BINARY(16)      NULL,                 -- authenticator model id (not trusted as identity, decision #29)
              transports         VARCHAR(190)    NULL,                 -- e.g. 'usb,nfc,ble,internal,hybrid'
              is_discoverable    TINYINT(1)      NULL,                 -- resident/discoverable credential
              is_backup_eligible TINYINT(1)      NULL,
              is_backed_up       TINYINT(1)      NULL,                 -- synced credential
              nickname           VARCHAR(120)    NULL,
              created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_used_at       DATETIME        NULL,
              revoked_at         DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_webauthn_credid (credential_id),
              KEY idx_webauthn_user (user_id, revoked_at),
              CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── One-time, short-lived ceremony challenges ────────────────────────
        // user_id NULL = usernameless/discoverable login challenge (resolves only
        // by credential identity, never by email/username — Gate B, decision #31).
        // session_token_hash binds the challenge to the issuing session.
        $pdo->exec(<<<'SQL'
            CREATE TABLE webauthn_challenges (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id            BIGINT UNSIGNED NULL,
              session_token_hash CHAR(64)        NULL,                 -- sha256 of the bound session token
              purpose            ENUM('register','login','step_up') NOT NULL,
              challenge          VARBINARY(255)  NOT NULL,             -- random one-time challenge
              created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at         DATETIME        NOT NULL,             -- short-lived
              consumed_at        DATETIME        NULL,                 -- one-time use
              PRIMARY KEY (id),
              KEY idx_webauthn_chal_user (user_id),
              KEY idx_webauthn_chal_expires (expires_at),
              CONSTRAINT fk_webauthn_chal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS webauthn_challenges');
        $pdo->exec('DROP TABLE IF EXISTS webauthn_credentials');
    }
};
