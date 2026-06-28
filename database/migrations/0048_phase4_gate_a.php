<?php

declare(strict_types=1);

/**
 * 0048 · Phase 4 Gate A additive schema.
 *
 * Implements the documented Gate A data shape without dropping/replacing any
 * Phase 1–3 table: canonical topic status/history, personal snooze,
 * assignment, group-DM membership intervals/events, curated tags, board/tag
 * follows support, reputation ledger, custom badge rules/history, manual
 * summaries, related topics, wiki revisions, split/merge audit, and stable
 * reference metadata.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE threads
              ADD COLUMN status ENUM('open','needs_answer','solved','decision_made','archived') NOT NULL DEFAULT 'open' AFTER is_deleted,
              ADD COLUMN status_changed_at DATETIME NULL AFTER status,
              ADD COLUMN status_changed_by BIGINT UNSIGNED NULL AFTER status_changed_at,
              ADD KEY idx_threads_status (status, is_deleted, is_pending, last_post_at),
              ADD CONSTRAINT fk_threads_status_user FOREIGN KEY (status_changed_by) REFERENCES users(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE posts
              ADD COLUMN is_wiki TINYINT(1) NOT NULL DEFAULT 0 AFTER is_op,
              ADD KEY idx_posts_wiki (thread_id, is_wiki)
        SQL);

        $pdo->exec("UPDATE threads SET status = 'solved', status_changed_at = UTC_TIMESTAMP() WHERE accepted_answer_post_id IS NOT NULL");

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_status_history (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id       BIGINT UNSIGNED NOT NULL,
              actor_id        BIGINT UNSIGNED NULL,
              previous_status ENUM('open','needs_answer','solved','decision_made','archived') NULL,
              new_status      ENUM('open','needs_answer','solved','decision_made','archived') NOT NULL,
              reason          VARCHAR(255) NULL,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tsh_thread (thread_id, created_at),
              KEY idx_tsh_actor (actor_id, created_at),
              CONSTRAINT fk_tsh_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_tsh_actor  FOREIGN KEY (actor_id)  REFERENCES users(id)   ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            INSERT INTO thread_status_history (thread_id, actor_id, previous_status, new_status, reason, created_at)
            SELECT id, NULL, NULL, status, 'phase4_backfill', UTC_TIMESTAMP()
            FROM threads
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_user
              ADD COLUMN snoozed_until DATETIME NULL AFTER is_starred,
              ADD COLUMN inbox_note VARCHAR(120) NULL AFTER snoozed_until,
              ADD KEY idx_tu_snooze (user_id, snoozed_until)
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE boards
              ADD COLUMN assignment_mode ENUM('off','self','staff') NOT NULL DEFAULT 'off' AFTER require_approval,
              ADD COLUMN tags_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER assignment_mode,
              ADD COLUMN wiki_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tags_enabled
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_assignments (
              thread_id       BIGINT UNSIGNED NOT NULL,
              assigned_user_id BIGINT UNSIGNED NOT NULL,
              assigned_by      BIGINT UNSIGNED NOT NULL,
              assigned_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (thread_id),
              KEY idx_thread_assign_user (assigned_user_id, assigned_at),
              CONSTRAINT fk_thread_assign_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_assign_user   FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_assign_actor  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_assignment_history (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id          BIGINT UNSIGNED NOT NULL,
              previous_user_id   BIGINT UNSIGNED NULL,
              assigned_user_id   BIGINT UNSIGNED NULL,
              actor_id           BIGINT UNSIGNED NOT NULL,
              action             ENUM('assign','reassign','unassign') NOT NULL,
              reason             VARCHAR(255) NULL,
              created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tah_thread (thread_id, created_at),
              KEY idx_tah_actor (actor_id, created_at),
              CONSTRAINT fk_tah_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_tah_prev   FOREIGN KEY (previous_user_id) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_tah_user   FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_tah_actor  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE conversations
              ADD COLUMN kind ENUM('direct','group') NOT NULL DEFAULT 'direct' AFTER id,
              ADD COLUMN title VARCHAR(120) NULL AFTER kind,
              ADD COLUMN owner_user_id BIGINT UNSIGNED NULL AFTER title,
              ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER owner_user_id,
              ADD KEY idx_conversations_kind (kind, last_message_at),
              ADD CONSTRAINT fk_conv_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
              ADD CONSTRAINT fk_conv_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE conversation_participants
              ADD COLUMN role ENUM('owner','member') NOT NULL DEFAULT 'member' AFTER user_id,
              ADD COLUMN joined_after_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER role,
              ADD COLUMN joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER joined_after_message_id,
              ADD COLUMN left_at DATETIME NULL AFTER joined_at,
              ADD COLUMN removed_by BIGINT UNSIGNED NULL AFTER left_at,
              ADD COLUMN notification_mode ENUM('normal','muted') NOT NULL DEFAULT 'normal' AFTER removed_by,
              ADD KEY idx_cp_active_user (user_id, left_at),
              ADD CONSTRAINT fk_cp_removed_by FOREIGN KEY (removed_by) REFERENCES users(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE conversation_events (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              conversation_id BIGINT UNSIGNED NOT NULL,
              actor_id        BIGINT UNSIGNED NULL,
              event_type      ENUM('created','renamed','member_added','member_removed','member_left','owner_transferred','muted','unmuted') NOT NULL,
              subject_user_id BIGINT UNSIGNED NULL,
              body            VARCHAR(255) NULL,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_ce_conv (conversation_id, id),
              CONSTRAINT fk_ce_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
              CONSTRAINT fk_ce_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_ce_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE tags (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug          VARCHAR(64) NOT NULL,
              name          VARCHAR(80) NOT NULL,
              description   VARCHAR(255) NULL,
              visibility    ENUM('public','hidden') NOT NULL DEFAULT 'public',
              is_enabled    TINYINT(1) NOT NULL DEFAULT 1,
              created_by    BIGINT UNSIGNED NULL,
              created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tags_slug (slug),
              KEY idx_tags_enabled (is_enabled, visibility, name),
              CONSTRAINT fk_tags_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE tag_aliases (
              alias_slug VARCHAR(64) NOT NULL,
              tag_id     BIGINT UNSIGNED NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (alias_slug),
              KEY idx_tag_alias_tag (tag_id),
              CONSTRAINT fk_tag_alias_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_tags (
              thread_id  BIGINT UNSIGNED NOT NULL,
              tag_id     BIGINT UNSIGNED NOT NULL,
              added_by   BIGINT UNSIGNED NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (thread_id, tag_id),
              KEY idx_thread_tags_tag (tag_id, thread_id),
              CONSTRAINT fk_thread_tags_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_tags_tag    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_tags_actor  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE reputation_events (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id        BIGINT UNSIGNED NOT NULL,
              board_id       BIGINT UNSIGNED NULL,
              source_type    VARCHAR(32) NOT NULL,
              source_id      BIGINT UNSIGNED NULL,
              logical_key    VARCHAR(120) NOT NULL,
              delta          INT NOT NULL,
              applied_delta  INT NOT NULL,
              event_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              reversed_at    DATETIME NULL,
              reversed_by    BIGINT UNSIGNED NULL,
              reversal_reason VARCHAR(255) NULL,
              created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_reputation_logical (logical_key),
              KEY idx_rep_user_time (user_id, event_at),
              KEY idx_rep_board_time (board_id, event_at),
              CONSTRAINT fk_rep_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_rep_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL,
              CONSTRAINT fk_rep_reversed_by FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE badges
              ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER kind,
              ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER is_enabled,
              ADD COLUMN rule_version INT UNSIGNED NULL AFTER display_order
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE badge_rules (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              badge_id     BIGINT UNSIGNED NOT NULL,
              rule_type    ENUM('post_count','thread_count','reputation','solved_count') NOT NULL,
              threshold    INT UNSIGNED NOT NULL,
              board_id     BIGINT UNSIGNED NULL,
              repeatable   TINYINT(1) NOT NULL DEFAULT 0,
              is_enabled   TINYINT(1) NOT NULL DEFAULT 0,
              version      INT UNSIGNED NOT NULL DEFAULT 1,
              created_by   BIGINT UNSIGNED NULL,
              created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at   DATETIME NULL,
              PRIMARY KEY (id),
              KEY idx_badge_rules_badge (badge_id, is_enabled),
              CONSTRAINT fk_badge_rule_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
              CONSTRAINT fk_badge_rule_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL,
              CONSTRAINT fk_badge_rule_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE badge_award_history (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id         BIGINT UNSIGNED NOT NULL,
              badge_id        BIGINT UNSIGNED NOT NULL,
              badge_rule_id   BIGINT UNSIGNED NULL,
              achievement_key VARCHAR(160) NOT NULL,
              action          ENUM('award','revoke') NOT NULL,
              actor_id        BIGINT UNSIGNED NULL,
              reason          VARCHAR(255) NULL,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_badge_award_once (user_id, badge_id, achievement_key, action),
              KEY idx_badge_hist_user (user_id, created_at),
              CONSTRAINT fk_bah_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_bah_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
              CONSTRAINT fk_bah_rule FOREIGN KEY (badge_rule_id) REFERENCES badge_rules(id) ON DELETE SET NULL,
              CONSTRAINT fk_bah_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_summaries (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id     BIGINT UNSIGNED NOT NULL,
              kind          ENUM('manual','canonical_answer') NOT NULL DEFAULT 'manual',
              status        ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
              body          MEDIUMTEXT NOT NULL,
              body_html     MEDIUMTEXT NULL,
              version       INT UNSIGNED NOT NULL DEFAULT 1,
              author_id     BIGINT UNSIGNED NOT NULL,
              reviewer_id   BIGINT UNSIGNED NULL,
              published_at  DATETIME NULL,
              retired_at    DATETIME NULL,
              created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME NULL,
              PRIMARY KEY (id),
              KEY idx_summary_thread (thread_id, status, version),
              CONSTRAINT fk_summary_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_summary_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_summary_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_summary_sources (
              summary_id BIGINT UNSIGNED NOT NULL,
              post_id    BIGINT UNSIGNED NOT NULL,
              PRIMARY KEY (summary_id, post_id),
              CONSTRAINT fk_summary_source_summary FOREIGN KEY (summary_id) REFERENCES thread_summaries(id) ON DELETE CASCADE,
              CONSTRAINT fk_summary_source_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE related_threads (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              source_thread_id   BIGINT UNSIGNED NOT NULL,
              related_thread_id  BIGINT UNSIGNED NOT NULL,
              relation_type      ENUM('related','duplicate','merged_from') NOT NULL DEFAULT 'related',
              source             ENUM('curated','tag','search','merge') NOT NULL DEFAULT 'curated',
              score              DECIMAL(6,3) NULL,
              reason             VARCHAR(255) NULL,
              status             ENUM('suggested','approved','rejected','retired') NOT NULL DEFAULT 'approved',
              curator_id         BIGINT UNSIGNED NULL,
              created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_related_pair (source_thread_id, related_thread_id, relation_type),
              KEY idx_related_target (related_thread_id, status),
              CONSTRAINT fk_related_source FOREIGN KEY (source_thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_related_target FOREIGN KEY (related_thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_related_curator FOREIGN KEY (curator_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE post_revisions (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              post_id     BIGINT UNSIGNED NOT NULL,
              editor_id   BIGINT UNSIGNED NOT NULL,
              body        MEDIUMTEXT NOT NULL,
              body_html   MEDIUMTEXT NULL,
              reason      VARCHAR(255) NULL,
              created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_post_revisions_post (post_id, id),
              CONSTRAINT fk_post_revision_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
              CONSTRAINT fk_post_revision_editor FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_operations (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              operation_type         ENUM('split','merge') NOT NULL,
              actor_id               BIGINT UNSIGNED NOT NULL,
              source_thread_id       BIGINT UNSIGNED NOT NULL,
              destination_thread_id  BIGINT UNSIGNED NULL,
              status                 ENUM('planned','applied','failed','rolled_back') NOT NULL DEFAULT 'planned',
              dry_run_plan           JSON NOT NULL,
              before_snapshot        JSON NULL,
              after_snapshot         JSON NULL,
              failure_reason         VARCHAR(255) NULL,
              created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              applied_at             DATETIME NULL,
              PRIMARY KEY (id),
              KEY idx_thread_ops_source (source_thread_id, created_at),
              CONSTRAINT fk_thread_ops_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_ops_source FOREIGN KEY (source_thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_thread_ops_dest FOREIGN KEY (destination_thread_id) REFERENCES threads(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_redirects (
              old_thread_id       BIGINT UNSIGNED NOT NULL,
              canonical_thread_id BIGINT UNSIGNED NOT NULL,
              operation_id        BIGINT UNSIGNED NULL,
              created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (old_thread_id),
              KEY idx_redirect_canonical (canonical_thread_id),
              CONSTRAINT fk_redirect_old FOREIGN KEY (old_thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_redirect_canonical FOREIGN KEY (canonical_thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_redirect_operation FOREIGN KEY (operation_id) REFERENCES thread_operations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE content_references (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              source_type     ENUM('post','dm_message','summary') NOT NULL,
              source_id       BIGINT UNSIGNED NOT NULL,
              target_type     ENUM('board','thread','post') NOT NULL,
              target_id       BIGINT UNSIGNED NULL,
              token           VARCHAR(160) NOT NULL,
              resolved_at     DATETIME NULL,
              unavailable     TINYINT(1) NOT NULL DEFAULT 0,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_content_ref_source (source_type, source_id),
              KEY idx_content_ref_target (target_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS content_references');
        $pdo->exec('DROP TABLE IF EXISTS thread_redirects');
        $pdo->exec('DROP TABLE IF EXISTS thread_operations');
        $pdo->exec('DROP TABLE IF EXISTS post_revisions');
        $pdo->exec('DROP TABLE IF EXISTS related_threads');
        $pdo->exec('DROP TABLE IF EXISTS thread_summary_sources');
        $pdo->exec('DROP TABLE IF EXISTS thread_summaries');
        $pdo->exec('DROP TABLE IF EXISTS badge_award_history');
        $pdo->exec('DROP TABLE IF EXISTS badge_rules');
        $pdo->exec('ALTER TABLE badges DROP COLUMN rule_version, DROP COLUMN display_order, DROP COLUMN is_enabled');
        $pdo->exec('DROP TABLE IF EXISTS reputation_events');
        $pdo->exec('DROP TABLE IF EXISTS thread_tags');
        $pdo->exec('DROP TABLE IF EXISTS tag_aliases');
        $pdo->exec('DROP TABLE IF EXISTS tags');
        $pdo->exec('DROP TABLE IF EXISTS conversation_events');
        $pdo->exec('ALTER TABLE conversation_participants DROP FOREIGN KEY fk_cp_removed_by');
        $pdo->exec('ALTER TABLE conversation_participants DROP COLUMN notification_mode, DROP COLUMN removed_by, DROP COLUMN left_at, DROP COLUMN joined_at, DROP COLUMN joined_after_message_id, DROP COLUMN role');
        $pdo->exec('ALTER TABLE conversations DROP FOREIGN KEY fk_conv_owner');
        $pdo->exec('ALTER TABLE conversations DROP FOREIGN KEY fk_conv_creator');
        $pdo->exec('ALTER TABLE conversations DROP COLUMN created_by, DROP COLUMN owner_user_id, DROP COLUMN title, DROP COLUMN kind');
        $pdo->exec('DROP TABLE IF EXISTS thread_assignment_history');
        $pdo->exec('DROP TABLE IF EXISTS thread_assignments');
        $pdo->exec('ALTER TABLE boards DROP COLUMN wiki_enabled, DROP COLUMN tags_enabled, DROP COLUMN assignment_mode');
        $pdo->exec('ALTER TABLE thread_user DROP COLUMN inbox_note, DROP COLUMN snoozed_until');
        $pdo->exec('DROP TABLE IF EXISTS thread_status_history');
        $pdo->exec('ALTER TABLE threads DROP FOREIGN KEY fk_threads_status_user');
        $pdo->exec('ALTER TABLE posts DROP COLUMN is_wiki');
        $pdo->exec('ALTER TABLE threads DROP COLUMN status_changed_by, DROP COLUMN status_changed_at, DROP COLUMN status');
    }
};
