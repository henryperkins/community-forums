<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/** Schema-shape checks for the B2 api_tokens table (migration 0056). */
final class AppApiTokensSchemaTest extends TestCase
{
    /** @return array{type:string,column_type:string}|null */
    private function column(string $table, string $col): ?array
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS type, COLUMN_TYPE AS column_type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col],
        );
        return $row === null ? null : ['type' => (string) $row['type'], 'column_type' => (string) $row['column_type']];
    }

    public function test_api_tokens_table_shape(): void
    {
        $hash = $this->column('api_tokens', 'token_hash');
        self::assertNotNull($hash);
        self::assertSame('char(64)', $hash['column_type']);
        self::assertNull($this->column('api_tokens', 'token'), 'no raw-token column may exist');
        self::assertNotNull($this->column('api_tokens', 'scopes'));
        self::assertSame('json', $this->column('api_tokens', 'scopes')['type']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_tokens'
                   AND INDEX_NAME = 'uq_api_token_hash' AND NON_UNIQUE = 0",
            ),
            'token_hash must be uniquely indexed',
        );
    }

    public function test_moderation_log_enum_accepts_api_token(): void
    {
        $colType = (string) $this->db->fetchValue(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
        );
        self::assertStringContainsString("'api_token'", $colType);
    }
}
