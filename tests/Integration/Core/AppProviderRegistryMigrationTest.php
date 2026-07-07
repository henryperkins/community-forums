<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Inc 8 (P5-12) migration reconciliation — §9 "Provider migration". Migration
 * 0074 backfills `oauth_identities.provider_config_id` from the 0052 alias map
 * so every legacy enum-era identity points at its canonical registry row, with
 * no duplication, no orphaning, and no change to string-keyed resolution.
 *
 * DDL correctness is asserted against the bootstrap-migrated schema; the DML
 * backfill is exercised directly via the migration's public backfill() hook
 * (pure UPDATE — safe inside the per-test transaction, unlike re-running up()).
 */
final class AppProviderRegistryMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__ . '/../../../database/migrations/0074_phase5_provider_identity_backfill.php';

    public function test_discovery_cache_columns_landed_additively(): void
    {
        self::assertFileExists(self::MIGRATION, 'Inc 8 migration 0074 must exist');

        // Discovery-document cache beside the 0052 JWKS cache — both nullable,
        // so pre-existing rows and the seeded builtin providers stay valid.
        $json = $this->column('identity_providers', 'discovery_cache_json');
        self::assertNotNull($json, 'identity_providers.discovery_cache_json should exist');
        self::assertSame('mediumtext', $json['type']);
        self::assertSame('YES', $json['nullable']);

        $at = $this->column('identity_providers', 'discovery_cached_at');
        self::assertNotNull($at, 'identity_providers.discovery_cached_at should exist');
        self::assertSame('datetime', $at['type']);
        self::assertSame('YES', $at['nullable']);
    }

    public function test_backfill_links_legacy_identities_without_duplication_or_orphaning(): void
    {
        $userA = (int) $this->makeUser(['username' => 'mig_user_a'])['id'];
        $userB = (int) $this->makeUser(['username' => 'mig_user_b'])['id'];

        // A generic provider whose historical rows used a different string —
        // the alias map is exactly for this indirection.
        $configId = (int) $this->db->insert(
            "INSERT INTO identity_providers (provider_key, display_name, protocol, type, issuer, is_enabled)
             VALUES ('oidc-mig', 'Mig IdP', 'oidc', 'generic_oidc', 'https://idp.mig.test', 0)",
        );
        $this->db->run(
            "INSERT INTO provider_aliases (alias, provider_key) VALUES ('legacy-mig', 'oidc-mig')",
        );

        $insertIdentity = function (int $userId, string $provider, string $sub): int {
            return (int) $this->db->insert(
                'INSERT INTO oauth_identities (user_id, provider, provider_user_id, email_verified, created_at)
                 VALUES (?, ?, ?, 0, UTC_TIMESTAMP())',
                [$userId, $provider, $sub],
            );
        };

        // Legacy builtin (canonical alias 'google' → seeded 'google' row).
        $googleRow = $insertIdentity($userA, 'google', 'mig-sub-google');
        // Historical alias → canonical generic provider.
        $aliasRow = $insertIdentity($userB, 'legacy-mig', 'mig-sub-alias');
        // No alias at all: must stay NULL (tolerated, never dropped or guessed).
        $unmappedRow = $insertIdentity($userA, 'mig-unmapped', 'mig-sub-none');
        // Already linked: the backfill must not repoint it.
        $prelinkedRow = $insertIdentity($userB, 'oidc-mig', 'mig-sub-linked');
        $this->db->run(
            'UPDATE oauth_identities SET provider_config_id = ? WHERE id = ?',
            [$configId, $prelinkedRow],
        );

        $before = $this->countIdentities();

        $migration = require self::MIGRATION;
        $migration->backfill($this->db->pdo());

        // No rows created or lost; string-keyed uniqueness untouched.
        self::assertSame($before, $this->countIdentities());

        $googleConfig = (int) $this->db->fetchValue(
            "SELECT id FROM identity_providers WHERE provider_key = 'google'",
        );
        self::assertSame($googleConfig, $this->linkOf($googleRow), 'builtin identity links to its seeded registry row');
        self::assertSame($configId, $this->linkOf($aliasRow), 'aliased identity links to the canonical provider');
        self::assertNull($this->linkOfNullable($unmappedRow), 'identity with no alias stays unlinked, not orphaned/dropped');
        self::assertSame($configId, $this->linkOf($prelinkedRow), 'already-linked identity is untouched');

        // Idempotent: a second run changes nothing (safe for re-rehearsal).
        $migration->backfill($this->db->pdo());
        self::assertSame($before, $this->countIdentities());
        self::assertSame($googleConfig, $this->linkOf($googleRow));
        self::assertNull($this->linkOfNullable($unmappedRow));
    }

    public function test_backfill_never_merges_distinct_subjects_across_providers(): void
    {
        // Two identities with the SAME subject on different providers must map to
        // different registry rows — the (provider_config, sub) identity key from
        // decision #32 stays collision-free through the migration.
        $userA = (int) $this->makeUser(['username' => 'mig_col_a'])['id'];
        $userB = (int) $this->makeUser(['username' => 'mig_col_b'])['id'];

        $rowGoogle = (int) $this->db->insert(
            "INSERT INTO oauth_identities (user_id, provider, provider_user_id, email_verified, created_at)
             VALUES (?, 'google', 'shared-sub-1', 0, UTC_TIMESTAMP())",
            [$userA],
        );
        $rowGithub = (int) $this->db->insert(
            "INSERT INTO oauth_identities (user_id, provider, provider_user_id, email_verified, created_at)
             VALUES (?, 'github', 'shared-sub-1', 0, UTC_TIMESTAMP())",
            [$userB],
        );

        $migration = require self::MIGRATION;
        $migration->backfill($this->db->pdo());

        $g = $this->linkOf($rowGoogle);
        $h = $this->linkOf($rowGithub);
        self::assertNotSame($g, $h, 'same subject on different providers must never converge on one config');
        self::assertSame($userA, (int) $this->db->fetchValue('SELECT user_id FROM oauth_identities WHERE id = ?', [$rowGoogle]));
        self::assertSame($userB, (int) $this->db->fetchValue('SELECT user_id FROM oauth_identities WHERE id = ?', [$rowGithub]));
    }

    // ---- helpers -----------------------------------------------------------

    private function countIdentities(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM oauth_identities');
    }

    private function linkOf(int $identityId): int
    {
        $v = $this->linkOfNullable($identityId);
        self::assertNotNull($v, "identity $identityId should be linked");
        return $v;
    }

    private function linkOfNullable(int $identityId): ?int
    {
        $row = $this->db->fetch('SELECT provider_config_id FROM oauth_identities WHERE id = ?', [$identityId]);
        self::assertNotNull($row);
        return $row['provider_config_id'] === null ? null : (int) $row['provider_config_id'];
    }

    /** @return array{type:string,column_type:string,nullable:string}|null */
    private function column(string $table, string $column): ?array
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS type, COLUMN_TYPE AS column_type, IS_NULLABLE AS nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
        return $row === null ? null : [
            'type' => (string) $row['type'],
            'column_type' => (string) $row['column_type'],
            'nullable' => (string) $row['nullable'],
        ];
    }
}
