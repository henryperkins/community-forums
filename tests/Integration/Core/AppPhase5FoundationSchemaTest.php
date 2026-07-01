<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Phase 5 foundation schema reconciliation (PHASE_5_PLAN §8.2, §10.1).
 *
 * Proves the additive, inert foundation migrations (0049–0053) apply on a clean
 * install and produce the documented shape — the Milestone-1 "no Gate A feature
 * depends on an undocumented table/column" gate. These are schema-shape checks,
 * not behavior: the subsystems stay dark (see AppFeatureFlagTest), so "inert
 * schema is not evidence" of a shipped feature — this only certifies the shape.
 */
final class AppPhase5FoundationSchemaTest extends TestCase
{
    private function tableExists(string $table): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) === 1;
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

    public function test_all_foundation_tables_exist(): void
    {
        $tables = [
            // 0049 registry / packages
            'package_registries', 'registry_trust_keys', 'package_publishers',
            'packages', 'package_releases', 'installed_packages',
            'installed_package_permissions', 'package_history', 'package_advisories',
            'local_package_blocks',
            // 0050 capabilities / roles
            'capabilities', 'roles', 'role_capabilities', 'role_assignments',
            'role_assignment_history', 'protected_owners', 'owner_transfer_history',
            // 0051 webauthn
            'webauthn_credentials', 'webauthn_challenges',
            // 0052 provider registry
            'identity_providers', 'provider_aliases',
            // 0053 invitations
            'invitations', 'invitation_redemptions',
        ];
        foreach ($tables as $table) {
            self::assertTrue($this->tableExists($table), "missing Phase 5 foundation table: $table");
        }
    }

    public function test_oauth_provider_widened_to_string_and_linkage_added(): void
    {
        // §8.2 #15 / §8.4: the fixed ENUM is converted to a string so new providers
        // arrive by configuration, BEFORE any new value is enabled.
        $provider = $this->column('oauth_identities', 'provider');
        self::assertNotNull($provider);
        self::assertSame('varchar', $provider['type'], 'oauth_identities.provider must be widened from enum to varchar');
        self::assertSame('varchar(64)', $provider['column_type']);

        // Additive registry linkage, nullable (no behavior change yet).
        $configId = $this->column('oauth_identities', 'provider_config_id');
        self::assertNotNull($configId, 'provider_config_id linkage column should exist');
        self::assertSame('YES', $configId['nullable'], 'provider_config_id must be nullable until Milestone-5 repoint');
    }

    public function test_existing_oauth_identity_round_trips_after_widen(): void
    {
        // The widen must preserve legacy values byte-for-byte (decision #32).
        $this->makeUser(['username' => 'p5oauth']);
        $uid = (int) $this->db->fetchValue('SELECT id FROM users WHERE username = ?', ['p5oauth']);
        $this->db->run(
            "INSERT INTO oauth_identities (user_id, provider, provider_user_id, email, email_verified, created_at)
             VALUES (?, 'google', 'sub-p5-123', 'p5@example.com', 1, UTC_TIMESTAMP())",
            [$uid],
        );
        $stored = $this->db->fetchValue(
            'SELECT provider FROM oauth_identities WHERE provider_user_id = ?',
            ['sub-p5-123'],
        );
        self::assertSame('google', $stored, 'legacy provider value must survive the enum→string widen unchanged');
    }

    public function test_system_roles_seeded_as_protected_anchors(): void
    {
        // decision #18: built-in roles seeded as protected compatibility anchors.
        $rows = $this->db->fetchAll(
            "SELECT role_key, role_rank, is_protected, kind FROM roles WHERE kind = 'system' ORDER BY role_rank",
        );
        self::assertCount(4, $rows);
        self::assertSame(
            ['system.guest', 'system.user', 'system.moderator', 'system.admin'],
            array_map(static fn (array $r): string => (string) $r['role_key'], $rows),
        );
        foreach ($rows as $r) {
            self::assertSame(1, (int) $r['is_protected'], "system role {$r['role_key']} must be protected");
        }
        // Ranks ascend guest < user < moderator < admin (boards.post_min_role floor).
        $ranks = array_map(static fn (array $r): int => (int) $r['role_rank'], $rows);
        self::assertSame($ranks, [0, 10, 20, 30]);
    }

    public function test_capability_catalogue_is_seeded_by_0066(): void
    {
        // The owner-approved taxonomy (A1, ADR 0012) is now transcribed in
        // CapabilityCatalog and seeded by migration 0066 (Foundation F3),
        // deploy-dark behind the `capabilities` flag.
        self::assertSame(
            54,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM capabilities'),
            'capability catalogue is seeded by migration 0066 from CapabilityCatalog',
        );
    }

    public function test_builtin_providers_and_aliases_seeded(): void
    {
        $providers = $this->db->fetchAll('SELECT provider_key, is_enabled FROM identity_providers ORDER BY provider_key');
        self::assertSame(
            ['apple', 'github', 'google'],
            array_map(static fn (array $r): string => (string) $r['provider_key'], $providers),
        );
        foreach ($providers as $p) {
            self::assertSame(0, (int) $p['is_enabled'], 'seeded providers must be dark (live OAuth still reads config)');
        }
        self::assertSame(
            3,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM provider_aliases'),
            'identity-preservation aliases for the three legacy providers must be seeded',
        );
    }

    public function test_webauthn_stores_only_binary_public_material(): void
    {
        $credId = $this->column('webauthn_credentials', 'credential_id');
        $pubKey = $this->column('webauthn_credentials', 'public_key');
        self::assertNotNull($credId);
        self::assertNotNull($pubKey);
        self::assertSame('varbinary', $credId['type']);
        self::assertSame('varbinary', $pubKey['type']);
        // Challenges are one-time: a consumed_at column must exist.
        self::assertNotNull($this->column('webauthn_challenges', 'consumed_at'));
    }

    public function test_invitations_are_hash_only(): void
    {
        // §9 "Invitation binding": no raw token column — only the sha256 hash,
        // which is unique. A plaintext `token` column would be a finding.
        self::assertNull($this->column('invitations', 'token'), 'invitations must not store a raw token');
        $hash = $this->column('invitations', 'token_hash');
        self::assertNotNull($hash);
        self::assertSame('char', $hash['type']);
        self::assertSame('char(64)', $hash['column_type']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations'
                   AND INDEX_NAME = 'uq_invitation_token' AND NON_UNIQUE = 0",
            ),
            'invitations.token_hash must be uniquely indexed',
        );
    }

    public function test_owner_lifecycle_lock_has_supporting_user_index(): void
    {
        // Last-owner lifecycle mutations lock active admins by role/status. Keep
        // that locking read on a narrow index so it does not scan-lock users.
        self::assertSame(
            'role,status,id',
            (string) $this->db->fetchValue(
                "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND INDEX_NAME = 'idx_users_role_status_id'",
            ),
            'users(role, status, id) must be indexed for owner/admin FOR UPDATE guards',
        );
    }

    public function test_secret_and_separation_invariants_are_locked(): void
    {
        // decision #35: provider/package secrets are an encrypted-service REFERENCE
        // only — the sole secret-bearing column on identity_providers is
        // `client_secret_ref`. A future migration adding a plaintext
        // secret/token/private column would be a finding; lock it here.
        $providerSecretCols = $this->db->fetchAll(
            "SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'identity_providers'
               AND (COLUMN_NAME LIKE '%secret%' OR COLUMN_NAME LIKE '%token%' OR COLUMN_NAME LIKE '%private%')",
        );
        self::assertSame(
            ['client_secret_ref'],
            array_map(static fn (array $r): string => (string) $r['c'], $providerSecretCols),
            'identity_providers must expose only client_secret_ref (an encrypted-service reference), never a plaintext secret',
        );

        // §8.2 #1: registry trust roots store PUBLIC key material only — never a
        // private trust-root/signing key.
        self::assertNotNull($this->column('registry_trust_keys', 'public_key'));
        $trustPrivateCols = $this->db->fetchAll(
            "SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registry_trust_keys'
               AND (COLUMN_NAME LIKE '%private%' OR COLUMN_NAME LIKE '%secret%')",
        );
        self::assertSame([], $trustPrivateCols, 'registry_trust_keys must hold no private/secret key material');

        // §13.2: install state carries its OWN digest, independent of the (nullable)
        // release pointer, so a registry rollback can never rewrite an installed digest.
        $digest = $this->column('installed_packages', 'digest');
        self::assertNotNull($digest);
        self::assertSame('NO', $digest['nullable'], 'installed_packages.digest must be NOT NULL (independent of release_id)');
        self::assertSame('YES', (string) $this->column('installed_packages', 'release_id')['nullable']);
    }
}
