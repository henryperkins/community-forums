<?php

declare(strict_types=1);

/**
 * 0043 · attachments — image upload lifecycle (P3-04, COMPOSER §16.2).
 *
 * Richer than the consolidated SCHEMA.md sketch, resolving the schema gap called
 * out in PHASE_3_PLAN §8.2 #1: explicit temp/finalized/deleted state, an
 * unguessable storage_key + content sha256 (dedupe + integrity), a visibility
 * dimension derived from the parent at finalize time, alt text, and
 * finalized/deleted timestamps for orphan cleanup. The same table backs per-post
 * and per-DM media plus operator brand assets (purpose).
 *
 * Invariants (PHASE_3_PLAN §8.5): a row is 'temp' until its parent content
 * commits, at which point finalize() flips it to 'finalized' and stamps the
 * owning post_id/dm_message_id and visibility. Media never becomes visible
 * before its parent + authorization context are committed.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE attachments (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id        BIGINT UNSIGNED NOT NULL,                    -- owner/uploader
              post_id        BIGINT UNSIGNED NULL,                        -- set at finalize (post media)
              dm_message_id  BIGINT UNSIGNED NULL,                        -- set at finalize (DM media)
              purpose        ENUM('post','dm','brand_logo','brand_favicon','avatar') NOT NULL DEFAULT 'post',
              kind           ENUM('image','file') NOT NULL DEFAULT 'image',
              status         ENUM('temp','finalized','deleted') NOT NULL DEFAULT 'temp',
              storage_key    VARCHAR(255)    NOT NULL,                    -- relative path under the media root, unguessable
              sha256         CHAR(64)        NOT NULL,                    -- content hash (dedupe + integrity)
              mime           VARCHAR(100)    NOT NULL,
              size_bytes     INT UNSIGNED    NOT NULL,
              width          INT UNSIGNED    NULL,
              height         INT UNSIGNED    NULL,
              alt            VARCHAR(255)    NULL,
              visibility     ENUM('public','private') NOT NULL DEFAULT 'public', -- derived from parent at finalize
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              finalized_at   DATETIME        NULL,
              deleted_at     DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_attach_storage_key (storage_key),
              KEY idx_attach_owner (user_id),
              KEY idx_attach_post (post_id),
              KEY idx_attach_dm (dm_message_id),
              KEY idx_attach_sweep (status, created_at),
              CONSTRAINT fk_attach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS attachments');
    }
};
