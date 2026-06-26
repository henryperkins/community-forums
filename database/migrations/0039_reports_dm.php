<?php

declare(strict_types=1);

/**
 * 0039 · reports — allow reporting a DM message (P2-07). A report now targets
 * either a post (post_id) or a direct message (dm_message_id); post_id becomes
 * nullable. Staff see only the reported message + local context (no DM browser).
 * SCHEMA §7 #16.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE reports MODIFY post_id BIGINT UNSIGNED NULL');
        $pdo->exec(<<<'SQL'
            ALTER TABLE reports
              ADD COLUMN dm_message_id BIGINT UNSIGNED NULL AFTER post_id,
              ADD KEY idx_reports_dm (dm_message_id),
              ADD CONSTRAINT fk_report_dm FOREIGN KEY (dm_message_id) REFERENCES dm_messages(id) ON DELETE CASCADE
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE reports DROP FOREIGN KEY fk_report_dm');
        $pdo->exec('ALTER TABLE reports DROP COLUMN dm_message_id');
        // Restore the original NOT NULL (safe on greenfield; DM-only rows would block this).
        $pdo->exec('ALTER TABLE reports MODIFY post_id BIGINT UNSIGNED NOT NULL');
    }
};
