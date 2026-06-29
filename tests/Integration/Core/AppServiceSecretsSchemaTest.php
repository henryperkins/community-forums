<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Schema-shape checks for the B2 service-secret registry (migration 0055).
 * Additive + inert: the tables exist and match the documented shape; behavior
 * lives in SecretVaultTest. "Inert schema is not evidence" (DESIGN §13).
 */
final class AppServiceSecretsSchemaTest extends TestCase
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

    private function indexIsUnique(string $table, string $index): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0',
            [$table, $index],
        ) > 0;
    }

    public function test_service_secret_tables_exist(): void
    {
        self::assertTrue($this->tableExists('service_secrets'));
        self::assertTrue($this->tableExists('service_secret_versions'));
    }

    public function test_reference_table_shape(): void
    {
        $ref = $this->column('service_secrets', 'secret_ref');
        self::assertNotNull($ref);
        self::assertSame('varchar', $ref['type']);
        self::assertSame('varchar(64)', $ref['column_type']);
        self::assertTrue($this->indexIsUnique('service_secrets', 'uq_service_secret_ref'), 'secret_ref must be uniquely indexed');

        $latest = $this->column('service_secrets', 'latest_version');
        self::assertNotNull($latest);
        self::assertSame('int', $latest['type']);

        $status = $this->column('service_secrets', 'status');
        self::assertNotNull($status);
        self::assertSame("enum('active','revoked')", $status['column_type']);
    }

    public function test_version_table_stores_binary_material(): void
    {
        foreach (['ciphertext', 'nonce', 'tag'] as $col) {
            $c = $this->column('service_secret_versions', $col);
            self::assertNotNull($c, "missing column service_secret_versions.$col");
            self::assertSame('varbinary', $c['type'], "$col must be VARBINARY");
        }
        $state = $this->column('service_secret_versions', 'state');
        self::assertNotNull($state);
        self::assertSame("enum('current','retired','destroyed')", $state['column_type']);
        self::assertTrue(
            $this->indexIsUnique('service_secret_versions', 'uq_service_secret_version'),
            '(secret_id, version) must be uniquely indexed',
        );
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_secret_versions'
                   AND INDEX_NAME = 'idx_service_secret_prune'",
            ),
        );
    }

    public function test_version_fk_cascades_from_parent(): void
    {
        self::assertSame(
            'CASCADE',
            $this->db->fetchValue(
                "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'service_secret_versions'
                   AND CONSTRAINT_NAME = 'fk_service_secret_version_secret'",
            ),
        );
    }
}
