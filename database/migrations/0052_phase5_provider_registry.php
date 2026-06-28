<?php

declare(strict_types=1);

/**
 * 0052 · Phase 5 foundation — identity-provider registry + generic-OIDC config,
 * and the `oauth_identities.provider` enum→string widen
 * (PHASE_5_PLAN §8.2 #15, §8.3 grp 4, §8.4).
 *
 * ADDITIVE + (one) REVERSIBLE CONVERSION. This is the documented exception to
 * "strictly additive" (§3 def-of-done, fixed 2026-06-26): `oauth_identities.provider`
 * is converted from a fixed ENUM('google','apple','github') to VARCHAR(64) so new
 * providers can be added by configuration rather than a schema ALTER each time.
 * The widen happens BEFORE any new provider value is enabled (§8.4) so a rollback
 * to the previous app version still only ever produces the three legacy values.
 *
 * Existing google/apple/github identity rows are preserved byte-for-byte: ENUM
 * labels become the identical VARCHAR values, and the uq_provider_identity unique
 * key is retained. The live OAuth sign-in path keeps resolving from config('oauth')
 * while `provider_registry` is dark; the seeded provider rows + alias map are inert
 * reference data for the Milestone-5 migration that will repoint identities at the
 * registry without duplication/orphaning (decision #32, §9 "Provider migration").
 *
 * Provider/client secrets are NEVER stored in plaintext here — only an opaque
 * reference into the accepted encrypted secret service (decision #35).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Provider registry / configuration (§8.2 #15) ─────────────────────
        $pdo->exec(<<<'SQL'
            CREATE TABLE identity_providers (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              provider_key      VARCHAR(64)     NOT NULL,              -- stable id: 'google'|'apple'|'github'|'oidc:acme'...
              display_name      VARCHAR(190)    NOT NULL,
              protocol          ENUM('oauth2','oidc') NOT NULL DEFAULT 'oidc',
              type              ENUM('builtin','generic_oidc','package') NOT NULL DEFAULT 'builtin',
              issuer            VARCHAR(512)    NULL,                  -- pinned OIDC issuer
              discovery_url     VARCHAR(512)    NULL,
              jwks_uri          VARCHAR(512)    NULL,
              jwks_cache_json   MEDIUMTEXT      NULL,
              jwks_cached_at    DATETIME        NULL,
              client_id         VARCHAR(255)    NULL,
              client_secret_ref VARCHAR(190)    NULL,                  -- reference into encrypted secret service (NEVER plaintext)
              claim_map_json    TEXT            NULL,                  -- normalized-claim mapping
              is_enabled        TINYINT(1)      NOT NULL DEFAULT 0,    -- deploy-dark
              health_status     ENUM('unknown','ok','degraded','down') NOT NULL DEFAULT 'unknown',
              health_checked_at DATETIME        NULL,
              created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_provider_key (provider_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Migration aliases (§8.2 #15) ─────────────────────────────────────
        // Maps a historical provider string to its canonical provider_key so the
        // enum→registry migration never duplicates or orphans an identity.
        $pdo->exec(<<<'SQL'
            CREATE TABLE provider_aliases (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              alias        VARCHAR(64)     NOT NULL,                   -- historical oauth_identities.provider value
              provider_key VARCHAR(64)     NOT NULL,                   -- canonical identity_providers.provider_key
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_provider_alias (alias)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Widen the provider key (§8.2 #15, §8.4) ──────────────────────────
        // ENUM → VARCHAR(64). Existing 'google'/'apple'/'github' rows keep their
        // exact string values; the (provider, provider_user_id) unique key stays.
        $pdo->exec(<<<'SQL'
            ALTER TABLE oauth_identities
              MODIFY COLUMN provider VARCHAR(64) NOT NULL
        SQL);

        // Additive linkage to the registry. NULL until the Milestone-5 migration
        // repoints identities; uniqueness still derives from the provider string,
        // so this does NOT change account-resolution behavior yet.
        $pdo->exec(<<<'SQL'
            ALTER TABLE oauth_identities
              ADD COLUMN provider_config_id BIGINT UNSIGNED NULL AFTER provider,
              ADD KEY idx_oauth_provider_config (provider_config_id),
              ADD CONSTRAINT fk_oauth_provider_config FOREIGN KEY (provider_config_id) REFERENCES identity_providers(id) ON DELETE SET NULL
        SQL);

        // ── Seed the three accepted built-in providers (inert reference) ─────
        $pdo->exec(<<<'SQL'
            INSERT IGNORE INTO identity_providers (provider_key, display_name, protocol, type, issuer, is_enabled) VALUES
              ('google', 'Google', 'oidc',   'builtin', 'https://accounts.google.com', 0),
              ('apple',  'Apple',  'oidc',   'builtin', 'https://appleid.apple.com',    0),
              ('github', 'GitHub', 'oauth2', 'builtin', NULL,                            0)
        SQL);

        $pdo->exec(<<<'SQL'
            INSERT IGNORE INTO provider_aliases (alias, provider_key) VALUES
              ('google', 'google'),
              ('apple',  'apple'),
              ('github', 'github')
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        // Drop the registry linkage first so identity_providers can be dropped.
        $pdo->exec('ALTER TABLE oauth_identities DROP FOREIGN KEY fk_oauth_provider_config');
        $pdo->exec('ALTER TABLE oauth_identities DROP COLUMN provider_config_id');

        // Restore the original fixed enum. Safe while the feature is dark: only the
        // three legacy values can exist (provider expansion never enabled here).
        $pdo->exec(<<<'SQL'
            ALTER TABLE oauth_identities
              MODIFY COLUMN provider ENUM('google','apple','github') NOT NULL
        SQL);

        $pdo->exec('DROP TABLE IF EXISTS provider_aliases');
        $pdo->exec('DROP TABLE IF EXISTS identity_providers');
    }
};
