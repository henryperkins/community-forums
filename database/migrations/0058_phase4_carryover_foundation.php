<?php

declare(strict_types=1);

/**
 * 0058 - Phase 4 carryover foundation.
 *
 * ADDITIVE. Supplies deploy-dark data shapes for ADR 0003 carryovers that were
 * previously schema-only or absent: previews, expanded file quarantine, polls,
 * custom emoji, private board folders, saved feed filters, profile moderation,
 * and deterministic since-last-read context. Application behavior remains
 * feature-flag dark until each service/controller slice is enabled.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE link_previews (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              source_type     ENUM('post','dm_message','summary') NOT NULL,
              source_id       BIGINT UNSIGNED NOT NULL,
              url             VARCHAR(1024) NOT NULL,
              url_hash        CHAR(64)      NOT NULL,
              final_url       VARCHAR(1024) NULL,
              status          ENUM('queued','fetched','blocked','failed','purged') NOT NULL DEFAULT 'queued',
              title           VARCHAR(255)  NULL,
              description     VARCHAR(500)  NULL,
              image_url       VARCHAR(1024) NULL,
              site_name       VARCHAR(120)  NULL,
              http_status     SMALLINT UNSIGNED NULL,
              metadata        JSON          NULL,
              error           VARCHAR(255)  NULL,
              fetched_at      DATETIME      NULL,
              purged_at       DATETIME      NULL,
              created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME      NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_preview_source_url (source_type, source_id, url_hash),
              KEY idx_preview_status (status, created_at),
              KEY idx_preview_source (source_type, source_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE attachments
              ADD COLUMN scan_status ENUM('pending','clean','quarantined','failed','skipped') NOT NULL DEFAULT 'clean' AFTER visibility,
              ADD COLUMN scan_checked_at DATETIME NULL AFTER scan_status,
              ADD COLUMN quarantined_at DATETIME NULL AFTER scan_checked_at,
              ADD COLUMN quarantine_reason VARCHAR(255) NULL AFTER quarantined_at,
              ADD COLUMN download_name VARCHAR(255) NULL AFTER quarantine_reason,
              ADD KEY idx_attach_scan (scan_status, created_at)
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE polls (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id      BIGINT UNSIGNED NOT NULL,
              question       VARCHAR(255)    NOT NULL,
              mode           ENUM('single','multiple') NOT NULL DEFAULT 'single',
              status         ENUM('open','closed','disabled') NOT NULL DEFAULT 'open',
              results_policy ENUM('after_vote_or_close') NOT NULL DEFAULT 'after_vote_or_close',
              created_by     BIGINT UNSIGNED NOT NULL,
              closes_at      DATETIME        NULL,
              closed_at      DATETIME        NULL,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at     DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_poll_thread (thread_id),
              KEY idx_poll_status (status, closes_at),
              CONSTRAINT fk_poll_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_poll_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE poll_options (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              poll_id     BIGINT UNSIGNED NOT NULL,
              body        VARCHAR(255)    NOT NULL,
              position    INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_poll_options_poll (poll_id, position, id),
              CONSTRAINT fk_poll_option_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE poll_votes (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              poll_id     BIGINT UNSIGNED NOT NULL,
              option_id   BIGINT UNSIGNED NOT NULL,
              user_id     BIGINT UNSIGNED NOT NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_poll_vote_option (poll_id, option_id, user_id),
              KEY idx_poll_vote_user (user_id, poll_id),
              CONSTRAINT fk_poll_vote_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
              CONSTRAINT fk_poll_vote_option FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
              CONSTRAINT fk_poll_vote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE custom_emoji (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              shortcode       VARCHAR(40)     NOT NULL,
              name            VARCHAR(80)     NOT NULL,
              image_path      VARCHAR(255)    NOT NULL,
              mime            ENUM('image/png','image/webp') NOT NULL,
              is_enabled      TINYINT(1)      NOT NULL DEFAULT 1,
              allow_reactions TINYINT(1)      NOT NULL DEFAULT 0,
              created_by      BIGINT UNSIGNED NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_custom_emoji_shortcode (shortcode),
              KEY idx_custom_emoji_enabled (is_enabled, allow_reactions),
              CONSTRAINT fk_custom_emoji_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec("ALTER TABLE reactions MODIFY emoji VARCHAR(48) NOT NULL");

        $pdo->exec(<<<'SQL'
            ALTER TABLE users
              ADD COLUMN signature_removed_at DATETIME NULL AFTER last_seen_at,
              ADD COLUMN signature_removed_by BIGINT UNSIGNED NULL AFTER signature_removed_at,
              ADD COLUMN avatar_removed_at DATETIME NULL AFTER signature_removed_by,
              ADD COLUMN avatar_removed_by BIGINT UNSIGNED NULL AFTER avatar_removed_at,
              ADD KEY idx_users_signature_removed_by (signature_removed_by),
              ADD KEY idx_users_avatar_removed_by (avatar_removed_by),
              ADD CONSTRAINT fk_users_signature_removed_by FOREIGN KEY (signature_removed_by) REFERENCES users(id) ON DELETE SET NULL,
              ADD CONSTRAINT fk_users_avatar_removed_by FOREIGN KEY (avatar_removed_by) REFERENCES users(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE board_folders (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     BIGINT UNSIGNED NOT NULL,
              name        VARCHAR(80)     NOT NULL,
              position    INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at  DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_board_folder_name (user_id, name),
              KEY idx_board_folder_user (user_id, position, id),
              CONSTRAINT fk_board_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE board_folder_boards (
              folder_id   BIGINT UNSIGNED NOT NULL,
              board_id    BIGINT UNSIGNED NOT NULL,
              position    INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (folder_id, board_id),
              KEY idx_board_folder_board (board_id),
              CONSTRAINT fk_board_folder_item_folder FOREIGN KEY (folder_id) REFERENCES board_folders(id) ON DELETE CASCADE,
              CONSTRAINT fk_board_folder_item_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE saved_feed_filters (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id        BIGINT UNSIGNED NOT NULL,
              name           VARCHAR(80)     NOT NULL,
              filter_json    JSON            NOT NULL,
              digest_enabled TINYINT(1)      NOT NULL DEFAULT 0,
              position       INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at     DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_saved_feed_name (user_id, name),
              KEY idx_saved_feed_user (user_id, position, id),
              CONSTRAINT fk_saved_feed_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE since_last_read_context (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id       BIGINT UNSIGNED NOT NULL,
              thread_id     BIGINT UNSIGNED NOT NULL,
              from_post_id  BIGINT UNSIGNED NULL,
              to_post_id    BIGINT UNSIGNED NULL,
              post_count    INT UNSIGNED    NOT NULL DEFAULT 0,
              context_text  MEDIUMTEXT      NOT NULL,
              generated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expires_at    DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_context_window (user_id, thread_id, from_post_id, to_post_id),
              KEY idx_context_thread (thread_id, generated_at),
              CONSTRAINT fk_context_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_context_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_context_from_post FOREIGN KEY (from_post_id) REFERENCES posts(id) ON DELETE SET NULL,
              CONSTRAINT fk_context_to_post FOREIGN KEY (to_post_id) REFERENCES posts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS since_last_read_context');
        $pdo->exec('DROP TABLE IF EXISTS saved_feed_filters');
        $pdo->exec('DROP TABLE IF EXISTS board_folder_boards');
        $pdo->exec('DROP TABLE IF EXISTS board_folders');
        $pdo->exec('ALTER TABLE users DROP FOREIGN KEY fk_users_avatar_removed_by');
        $pdo->exec('ALTER TABLE users DROP FOREIGN KEY fk_users_signature_removed_by');
        $pdo->exec('ALTER TABLE users DROP COLUMN avatar_removed_by, DROP COLUMN avatar_removed_at, DROP COLUMN signature_removed_by, DROP COLUMN signature_removed_at');
        $pdo->exec('DROP TABLE IF EXISTS custom_emoji');
        $pdo->exec("ALTER TABLE reactions MODIFY emoji VARCHAR(16) NOT NULL");
        $pdo->exec('DROP TABLE IF EXISTS poll_votes');
        $pdo->exec('DROP TABLE IF EXISTS poll_options');
        $pdo->exec('DROP TABLE IF EXISTS polls');
        $pdo->exec('ALTER TABLE attachments DROP COLUMN download_name, DROP COLUMN quarantine_reason, DROP COLUMN quarantined_at, DROP COLUMN scan_checked_at, DROP COLUMN scan_status');
        $pdo->exec('DROP TABLE IF EXISTS link_previews');
    }
};
