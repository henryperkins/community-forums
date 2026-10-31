<?php

declare(strict_types=1);

/**
 * 0084 · Branding revisions (ADR 0054, increment 2).
 *
 * ADDITIVE. An append-only history of the published branding document: each
 * row is the whole document after one change, the keys that changed, the
 * actor, and how it was made (`baseline` records the configuration the first
 * recorded change replaced; then `publish`, `restore` or `reset`). The current
 * pointer stays in `settings.brand_revision`, which every change locks, so a
 * stale editing session is detected instead of silently overwriting newer
 * work. Logos are referenced by their `/media/{id}` paths; brand media is
 * never purged, so a revision's images remain restorable.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS branding_revisions (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              document_json     MEDIUMTEXT      NOT NULL,
              changed_keys_json TEXT            NOT NULL,
              action            ENUM('baseline','publish','restore','reset') NOT NULL,
              restored_from_id  BIGINT UNSIGNED NULL,
              actor_id          BIGINT UNSIGNED NULL,
              created_at        DATETIME        NOT NULL,
              PRIMARY KEY (id),
              KEY idx_branding_revision_actor (actor_id),
              CONSTRAINT fk_branding_revision_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS branding_revisions');
    }
};
