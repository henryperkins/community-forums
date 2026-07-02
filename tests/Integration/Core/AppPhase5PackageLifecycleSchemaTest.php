<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPhase5PackageLifecycleSchemaTest extends TestCase
{
    /** @return array<string,array{type:string,nullable:string}> */
    private function columns(string $table): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
        );
        $stmt->execute(['t' => $table]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['COLUMN_NAME']] = ['type' => $row['COLUMN_TYPE'], 'nullable' => $row['IS_NULLABLE']];
        }
        return $out;
    }

    public function test_0069_adds_the_lifecycle_columns_and_enum_widens(): void
    {
        $cols = $this->columns('installed_packages');
        foreach ([
            'pinned', 'update_policy', 'staged_release_id', 'staged_digest', 'settings_json', 'export_json',
            'exported_at', 'retain_until', 'uninstalled_at', 'quarantine_reason', 'last_health_check_at',
        ] as $col) {
            self::assertArrayHasKey($col, $cols, "installed_packages.$col missing");
        }
        self::assertSame("enum('manual','notify')", $cols['update_policy']['type']);
        self::assertSame(
            "enum('installed','enabled','disabled','quarantined','uninstalling','uninstalled')",
            $cols['state']['type'],
        );
        self::assertSame(
            "enum('capability','data_class','outbound_host','job','broker_service','api_scope','event')",
            $this->columns('installed_package_permissions')['kind']['type'],
        );
        self::assertStringContainsString("'update_staged','export','purge'", $this->columns('package_history')['event']['type']);

        $fk = $this->db->pdo()->query(
            "SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installed_packages' AND COLUMN_NAME = 'staged_release_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
        )->fetchColumn();
        self::assertSame('package_releases', $fk);
    }

    public function test_0070_creates_the_review_security_tables(): void
    {
        $keys = $this->columns('publisher_signing_keys');
        foreach (['publisher_id', 'key_id', 'algorithm', 'public_key', 'status', 'valid_from', 'valid_until'] as $col) {
            self::assertArrayHasKey($col, $keys);
        }
        self::assertSame("enum('active','rotated','revoked')", $keys['status']['type']);

        $decisions = $this->columns('package_review_decisions');
        foreach (['package_id', 'release_id', 'version', 'digest', 'decision', 'decided_at', 'source', 'evidence_json'] as $col) {
            self::assertArrayHasKey($col, $decisions);
        }
        self::assertSame("enum('approved','rejected','revoked')", $decisions['decision']['type']);
        self::assertSame('char(64)', $decisions['digest']['type']);

        $log = $this->columns('package_transparency_log');
        foreach (['package_uid', 'version', 'digest', 'event', 'source', 'actor_id', 'registry_id', 'detail', 'created_at'] as $col) {
            self::assertArrayHasKey($col, $log);
        }
        self::assertArrayNotHasKey('updated_at', $log, 'transparency log is append-only');
        self::assertSame(
            "enum('release_verified','install','update','rollback','uninstall','quarantine','force_disable','revoked')",
            $log['event']['type'],
        );
    }
}
