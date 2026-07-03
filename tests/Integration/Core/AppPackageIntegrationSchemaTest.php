<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPackageIntegrationSchemaTest extends TestCase
{
    public function test_settings_and_credential_tables_match_documented_shape(): void
    {
        self::assertSame(
            ['id', 'installed_package_id', 'setting_key', 'value_json', 'secret_ref', 'is_secret', 'updated_by', 'updated_at'],
            $this->columns('installed_package_settings'),
        );
        self::assertSame(
            ['id', 'installed_package_id', 'kind', 'api_token_id', 'webhook_id', 'label', 'scopes_json', 'events_json', 'created_by', 'created_at', 'revoked_at'],
            $this->columns('installed_package_credentials'),
        );
    }

    public function test_settings_unique_key_and_secret_index_exist(): void
    {
        $idx = $this->indexes('installed_package_settings');
        self::assertContains('uq_install_setting', $idx);
        self::assertContains('idx_install_setting_secret', $idx);
    }

    public function test_credential_foreign_keys_reference_b2_tables(): void
    {
        $fks = $this->foreignKeys('installed_package_credentials');
        self::assertSame('installed_packages', $fks['fk_install_cred_install'] ?? null);
        self::assertSame('api_tokens', $fks['fk_install_cred_api_token'] ?? null);
        self::assertSame('webhooks', $fks['fk_install_cred_webhook'] ?? null);
        self::assertSame('users', $fks['fk_install_cred_user'] ?? null);
    }

    public function test_package_history_enum_gains_credential_and_settings_events(): void
    {
        $type = (string) $this->db->fetch(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_history' AND COLUMN_NAME = 'event'",
        )['t'];
        self::assertStringContainsString("'settings_update'", $type);
        self::assertStringContainsString("'credential_mint'", $type);
        self::assertStringContainsString("'credential_revoke'", $type);
    }

    public function test_moderation_log_target_type_gains_publisher(): void
    {
        $type = (string) $this->db->fetch(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
        )['t'];
        self::assertStringContainsString("'publisher'", $type);
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['c'],
            $this->db->fetchAll(
                'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [$table],
            ),
        );
    }

    /** @return list<string> */
    private function indexes(string $table): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['n'],
            $this->db->fetchAll(
                'SELECT DISTINCT INDEX_NAME AS n FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            ),
        );
    }

    /** @return array<string,string> constraint_name => referenced_table */
    private function foreignKeys(string $table): array
    {
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT CONSTRAINT_NAME AS c, REFERENCED_TABLE_NAME AS r FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table],
        ) as $row) {
            $out[(string) $row['c']] = (string) $row['r'];
        }

        return $out;
    }
}
