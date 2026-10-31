<?php

declare(strict_types=1);

/**
 * 0085 · Private branding drafts (ADR 0054, increment 2b).
 *
 * ADDITIVE. One unpublished branding document per administrator, with the
 * revision it was started from so publishing it later is checked against
 * newer work. Drafts are private to their author: they are never served to
 * anyone else, and their uploaded images stay private media until published.
 * Deleting the account deletes its draft.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS branding_drafts (
              admin_id         BIGINT UNSIGNED NOT NULL,
              document_json    MEDIUMTEXT      NOT NULL,
              base_revision_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
              created_at       DATETIME        NOT NULL,
              updated_at       DATETIME        NOT NULL,
              PRIMARY KEY (admin_id),
              CONSTRAINT fk_branding_draft_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS branding_drafts');
    }
};
