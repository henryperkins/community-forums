<?php

declare(strict_types=1);

/**
 * 0063 - Email delivery retry/backoff state.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE email_deliveries
              ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
              ADD COLUMN max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER attempt_count,
              ADD COLUMN last_attempt_at DATETIME NULL AFTER max_attempts,
              ADD COLUMN next_attempt_at DATETIME NULL AFTER last_attempt_at,
              ADD KEY idx_deliv_retry (status, next_attempt_at, id)
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE email_deliveries
              DROP KEY idx_deliv_retry,
              DROP COLUMN next_attempt_at,
              DROP COLUMN last_attempt_at,
              DROP COLUMN max_attempts,
              DROP COLUMN attempt_count
        SQL);
    }
};
