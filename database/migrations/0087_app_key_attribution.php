<?php

declare(strict_types=1);

/**
 * 0087 · Record which application key protects each stored secret or verifier
 * (ADR 0059). Every column is nullable and older code ignores it.
 *
 * - user_totp_credentials.secret_key_id     encryption key id that sealed the TOTP secret
 * - service_secret_versions.key_id          encryption key id that sealed the vault version
 * - user_recovery_codes.hmac_key_id         HMAC key id that keyed code_hash
 * - verifications.hmac_key_id               HMAC key id that keyed an email_change token_hash
 * - email_deliveries.unsubscribe_key_id     HMAC key id that signed the message's unsubscribe link
 *
 * NULL means "written before this migration". The key is unknown until a record
 * is opened or a code is used: legacy recovery codes are never attributed by
 * assumption. The legacy service_secret_versions.key_version (always 1, never
 * read) is left untouched. Each ALTER is information_schema-guarded so a run
 * that died half-way converges instead of replaying it.
 */
return new class {
    private const COLUMNS = [
        ['user_totp_credentials', 'secret_key_id', 'secret_tag'],
        ['service_secret_versions', 'key_id', 'key_version'],
        ['user_recovery_codes', 'hmac_key_id', 'code_hash'],
        ['verifications', 'hmac_key_id', 'token_hash'],
        ['email_deliveries', 'unsubscribe_key_id', 'message_id'],
    ];

    public function up(\PDO $pdo): void
    {
        foreach (self::COLUMNS as [$table, $column, $after]) {
            if (!$this->columnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} CHAR(16) NULL AFTER {$after}");
            }
        }
        if (!$this->indexExists($pdo, 'email_deliveries', 'idx_deliv_unsub_key')) {
            $pdo->exec('ALTER TABLE email_deliveries ADD KEY idx_deliv_unsub_key (unsubscribe_key_id, sent_at)');
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'email_deliveries', 'idx_deliv_unsub_key')) {
            $pdo->exec('ALTER TABLE email_deliveries DROP INDEX idx_deliv_unsub_key');
        }
        foreach (array_reverse(self::COLUMNS) as [$table, $column]) {
            if ($this->columnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE {$table} DROP COLUMN {$column}");
            }
        }
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        SQL);
        $stmt->execute([':table_name' => $table, ':column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(\PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        SQL);
        $stmt->execute([':table_name' => $table, ':index_name' => $index]);
        return (int) $stmt->fetchColumn() > 0;
    }
};
