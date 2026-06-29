<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/** Schema-shape checks for the B2 webhooks tables (migration 0057). */
final class AppWebhooksSchemaTest extends TestCase
{
    private function dataType(string $table, string $col): ?string
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col],
        );
        return $row === null ? null : (string) $row['t'];
    }

    public function test_webhooks_table_stores_a_secret_ref_not_plaintext(): void
    {
        self::assertNotNull($this->dataType('webhooks', 'secret_ref'), 'secret_ref column must exist');
        self::assertNull($this->dataType('webhooks', 'secret'), 'no plaintext secret column may exist');
        self::assertContains($this->dataType('webhooks', 'events'), ['json', 'longtext']);
    }

    public function test_webhook_deliveries_has_idempotency_and_claim_indexes(): void
    {
        $unique = (int) $this->db->fetchValue(
            "SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_deliveries'
               AND INDEX_NAME = 'uq_delivery_idem' AND NON_UNIQUE = 0",
        );
        self::assertSame(3, $unique, 'uq_delivery_idem must span webhook_id, event_type, event_id');

        $claim = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_deliveries'
               AND INDEX_NAME = 'idx_delivery_claim'",
        );
        self::assertGreaterThan(0, $claim, 'idx_delivery_claim must exist');
    }

    public function test_moderation_log_enum_accepts_webhook(): void
    {
        $colType = (string) $this->db->fetchValue(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
        );
        self::assertStringContainsString("'webhook'", $colType);
    }
}
