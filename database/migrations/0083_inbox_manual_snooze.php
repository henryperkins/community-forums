<?php

declare(strict_types=1);

/** Add explicit manual Inbox hiding without changing existing timed snoozes. */
return new class {
    public function up(\PDO $pdo): void
    {
        if (!$this->columnExists($pdo)) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE thread_user
                  ADD COLUMN snoozed_indefinitely TINYINT(1) NOT NULL DEFAULT 0 AFTER snoozed_until
            SQL);
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->columnExists($pdo)) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE thread_user DROP COLUMN snoozed_indefinitely
            SQL);
        }
    }

    private function columnExists(\PDO $pdo): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'thread_user'
              AND COLUMN_NAME = 'snoozed_indefinitely'
        SQL);
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }
};
