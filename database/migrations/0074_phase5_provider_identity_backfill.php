<?php

declare(strict_types=1);

/**
 * 0074 · Phase 5 Inc 8 (P5-12) — discovery-document cache + the enum→registry
 * identity backfill (PHASE_5_PLAN §7 Milestone 5, §9 "Provider migration").
 *
 * 1. ADDITIVE DDL: `identity_providers.discovery_cache_json/discovery_cached_at`,
 *    the OIDC discovery-document cache beside the 0052 JWKS cache, so authorize/
 *    callback flows resolve endpoints without a per-request fetch (the D11
 *    `oidc.discovery_p95_cached` budget).
 *
 * 2. DML BACKFILL: point every legacy `oauth_identities` row at its canonical
 *    registry row via the 0052 alias map (alias → provider_key → id). Uniqueness
 *    and account resolution still derive from the provider STRING — provider
 *    keys are 1:1 with registry rows (uq_provider_key), so the (provider_config,
 *    sub) identity key of decision #32 is satisfied without repointing the
 *    physical unique key. Rows whose provider string has no alias are left
 *    unlinked (never guessed, never dropped). Idempotent: only NULL linkage is
 *    ever written, so re-running (or re-rehearsing) is safe.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE identity_providers
              ADD COLUMN discovery_cache_json MEDIUMTEXT NULL AFTER jwks_cached_at,
              ADD COLUMN discovery_cached_at  DATETIME   NULL AFTER discovery_cache_json
        SQL);

        $this->backfill($pdo);
    }

    /**
     * Pure-DML linkage backfill, exposed separately so the migration
     * reconciliation test can exercise it inside the transactional harness
     * (re-running up() would re-issue auto-committing DDL).
     */
    public function backfill(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            UPDATE oauth_identities oi
              JOIN provider_aliases pa ON pa.alias = oi.provider
              JOIN identity_providers ip ON ip.provider_key = pa.provider_key
               SET oi.provider_config_id = ip.id
             WHERE oi.provider_config_id IS NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        // Reverse the backfill first, then the additive columns.
        $pdo->exec('UPDATE oauth_identities SET provider_config_id = NULL');
        $pdo->exec(<<<'SQL'
            ALTER TABLE identity_providers
              DROP COLUMN discovery_cached_at,
              DROP COLUMN discovery_cache_json
        SQL);
    }
};
