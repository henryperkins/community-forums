<?php

declare(strict_types=1);

/**
 * 0081 · Link-preview enablement: per-board opt-in and author removal.
 *
 * ADDITIVE. Completes the `link_previews` carryover so the flag can graduate to
 * default-on (ADR 0025):
 *
 *  - `boards.link_previews_enabled` implements the DECISIONS §6 #5 locked
 *    decision ("opt-in per board"). It defaults to 0 so turning the feature flag
 *    on never starts unfurling links on a board whose operator did not ask for
 *    it — the flag makes the subsystem *available*, the board column makes it
 *    *active*, and the host allowlist decides what may be fetched.
 *
 *  - `link_previews.status` gains a `removed` member plus `removed_by` /
 *    `removed_at`. Author removal has to outlive an edit: `purged` is revived by
 *    the queue upsert (`status = IF(status = 'purged', 'queued', status)`), so a
 *    member who removed a card would see it return the next time they touched
 *    the post. `removed` is sticky through that upsert and only the remover (or
 *    an operator, on the record) can undo it.
 *
 * Both directions are information_schema-guarded so a partially-applied upgrade
 * can be re-run, and `down()` drops the columns before narrowing the ENUM back.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'boards', 'link_previews_enabled')) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE boards
                  ADD COLUMN link_previews_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER wiki_enabled
            SQL);
        }

        $pdo->exec(<<<'SQL'
            ALTER TABLE link_previews
              MODIFY COLUMN status ENUM('queued','fetched','blocked','failed','purged','removed')
                NOT NULL DEFAULT 'queued'
        SQL);

        if (!$this->columnExists($pdo, 'link_previews', 'removed_by')) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE link_previews
                  ADD COLUMN removed_by BIGINT UNSIGNED NULL AFTER error,
                  ADD COLUMN removed_at DATETIME NULL AFTER removed_by
            SQL);
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'link_previews', 'removed_by')) {
            $pdo->exec('ALTER TABLE link_previews DROP COLUMN removed_at, DROP COLUMN removed_by');
        }

        // Any row still parked in the member-removed state has no narrower
        // equivalent; fold it into `purged` so the ENUM can shrink without
        // MySQL rewriting those rows to the empty string.
        $pdo->exec("UPDATE link_previews SET status = 'purged' WHERE status = 'removed'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE link_previews
              MODIFY COLUMN status ENUM('queued','fetched','blocked','failed','purged')
                NOT NULL DEFAULT 'queued'
        SQL);

        if ($this->columnExists($pdo, 'boards', 'link_previews_enabled')) {
            $pdo->exec('ALTER TABLE boards DROP COLUMN link_previews_enabled');
        }
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        SQL);
        $stmt->execute([':table' => $table, ':column' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
