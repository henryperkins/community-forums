<?php

declare(strict_types=1);

/**
 * 0077 · Thread Intelligence graduation (ADR 0019) — durable AI generation state.
 *
 * Adds the per-thread job queue/state row and the immutable generation-attempt
 * evidence ledger; extends `thread_summaries` with AI authorship (`kind='ai'`,
 * nullable author) and version lineage (`parent_summary_id`); overlays AI
 * selection metadata on `related_threads` without touching its pair uniqueness
 * or source vocabulary; and adds the bounded board-sweep cursor plus the
 * `(board_id, id)` keyset index used by visibility sweeps.
 *
 * Additive and data-preserving. `down()` exists for the fixture-free deployment
 * rehearsal only — production rollback is runtime-level (flags/pause/key) and
 * keeps every row (docs/runbooks/thread_intelligence.md).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_intelligence_jobs (
              thread_id               BIGINT UNSIGNED NOT NULL,
              state                   ENUM('idle','queued','running','retry','dead','review_required') NOT NULL DEFAULT 'idle',
              trigger_code            VARCHAR(64) NOT NULL,
              trigger_reason          VARCHAR(255) NULL,
              due_at                  DATETIME NULL,
              lease_token             CHAR(64) NULL,
              lease_expires_at        DATETIME NULL,
              attempt_count           INT UNSIGNED NOT NULL DEFAULT 0,
              last_error_code         VARCHAR(64) NULL,
              last_processed_post_id  BIGINT UNSIGNED NULL,
              last_generated_at       DATETIME NULL,
              last_full_reconcile_at  DATETIME NULL,
              automation_paused       TINYINT(1) NOT NULL DEFAULT 0,
              paused_by               BIGINT UNSIGNED NULL,
              paused_at               DATETIME NULL,
              source_snapshot_hash    CHAR(64) NULL,
              activity_version        BIGINT UNSIGNED NOT NULL DEFAULT 0,
              reconcile_required      TINYINT(1) NOT NULL DEFAULT 0,
              created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at              DATETIME NULL,
              PRIMARY KEY (thread_id),
              KEY idx_ti_jobs_due (state, due_at, thread_id),
              KEY idx_ti_jobs_lease (state, lease_expires_at, thread_id),
              CONSTRAINT fk_ti_job_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_ti_job_paused_by FOREIGN KEY (paused_by) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_ti_job_checkpoint FOREIGN KEY (last_processed_post_id) REFERENCES posts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE thread_intelligence_generations (
              id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              thread_id               BIGINT UNSIGNED NOT NULL,
              trigger_code            VARCHAR(64) NOT NULL,
              status                  ENUM('requested','succeeded','published','retry','failed','dead','review_required','rejected','stale') NOT NULL DEFAULT 'requested',
              retry_number            INT UNSIGNED NOT NULL DEFAULT 0,
              window_number           INT UNSIGNED NOT NULL DEFAULT 0,
              baseline_summary_id     BIGINT UNSIGNED NULL,
              published_summary_id    BIGINT UNSIGNED NULL,
              source_snapshot_hash    CHAR(64) NULL,
              source_post_ids         JSON NULL,
              candidate_thread_ids    JSON NULL,
              request_fingerprint     CHAR(64) NULL,
              prompt_version          VARCHAR(64) NULL,
              model                   VARCHAR(128) NULL,
              reasoning_effort        VARCHAR(16) NULL,
              provider_response_id    VARCHAR(128) NULL,
              estimated_input_tokens  INT UNSIGNED NULL,
              input_tokens            INT UNSIGNED NULL,
              output_tokens           INT UNSIGNED NULL,
              reasoning_tokens        INT UNSIGNED NULL,
              cached_tokens           INT UNSIGNED NULL,
              failure_code            VARCHAR(64) NULL,
              failure_message         VARCHAR(255) NULL,
              requested_at            DATETIME NOT NULL,
              completed_at            DATETIME NULL,
              published_at            DATETIME NULL,
              PRIMARY KEY (id),
              KEY idx_ti_gen_thread (thread_id, id),
              KEY idx_ti_gen_retention (status, completed_at, id),
              KEY idx_ti_gen_baseline (baseline_summary_id),
              KEY idx_ti_gen_published (published_summary_id),
              CONSTRAINT fk_ti_gen_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
              CONSTRAINT fk_ti_gen_baseline FOREIGN KEY (baseline_summary_id) REFERENCES thread_summaries(id) ON DELETE SET NULL,
              CONSTRAINT fk_ti_gen_published FOREIGN KEY (published_summary_id) REFERENCES thread_summaries(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // AI authorship + lineage on the existing summary history. Discover the
        // pre-existing author FK by its schema shape because compatible upgraded
        // databases may not retain 0048's original constraint name. It flips
        // CASCADE -> SET NULL so deleting/anonymizing a human author can never
        // delete summary history.
        $authorForeignKey = $pdo->query(<<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'thread_summaries'
              AND COLUMN_NAME = 'author_id'
              AND REFERENCED_TABLE_NAME = 'users'
              AND REFERENCED_COLUMN_NAME = 'id'
            ORDER BY CONSTRAINT_NAME
            LIMIT 1
        SQL)->fetchColumn();
        if (!is_string($authorForeignKey) || $authorForeignKey === '') {
            throw new \RuntimeException('Missing foreign key covering thread_summaries.author_id');
        }
        $quotedAuthorForeignKey = str_replace('`', '``', $authorForeignKey);

        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_summaries
              MODIFY kind ENUM('manual','canonical_answer','ai') NOT NULL DEFAULT 'manual'
        SQL);
        $pdo->exec("ALTER TABLE thread_summaries DROP FOREIGN KEY `{$quotedAuthorForeignKey}`");
        $pdo->exec('ALTER TABLE thread_summaries MODIFY author_id BIGINT UNSIGNED NULL');
        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_summaries
              ADD CONSTRAINT fk_summary_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_summaries
              ADD COLUMN parent_summary_id BIGINT UNSIGNED NULL AFTER reviewer_id,
              ADD CONSTRAINT fk_summary_parent FOREIGN KEY (parent_summary_id) REFERENCES thread_summaries(id) ON DELETE SET NULL
        SQL);

        // AI selection overlay on deterministic relationships. The source enum and
        // uq_related_pair stay untouched; curated rows are never overlaid.
        $pdo->exec(<<<'SQL'
            ALTER TABLE related_threads
              ADD COLUMN ai_generation_id BIGINT UNSIGNED NULL,
              ADD COLUMN ai_reason VARCHAR(255) NULL,
              ADD COLUMN ai_selected TINYINT(1) NOT NULL DEFAULT 0,
              ADD COLUMN ai_selected_at DATETIME NULL,
              ADD KEY idx_related_ai_overlay (source_thread_id, ai_selected, status, id),
              ADD CONSTRAINT fk_related_ai_generation FOREIGN KEY (ai_generation_id)
                REFERENCES thread_intelligence_generations(id) ON DELETE SET NULL
        SQL);

        // Bounded board-visibility sweep cursor: NULL = no sweep due, 0 = start,
        // positive = last processed thread ID (keyset resume point).
        $pdo->exec(<<<'SQL'
            ALTER TABLE boards
              ADD COLUMN thread_intelligence_sweep_after_id BIGINT UNSIGNED NULL,
              ADD KEY idx_boards_ti_sweep (thread_intelligence_sweep_after_id, id)
        SQL);

        $pdo->exec('ALTER TABLE threads ADD KEY idx_threads_board_id (board_id, id)');
    }

    public function down(\PDO $pdo): void
    {
        // Fixture-free rehearsal only (deployed rollback is runtime-level and
        // data-preserving). Drop dependents before their referenced objects.
        $pdo->exec('ALTER TABLE threads DROP KEY idx_threads_board_id');

        $pdo->exec(<<<'SQL'
            ALTER TABLE boards
              DROP KEY idx_boards_ti_sweep,
              DROP COLUMN thread_intelligence_sweep_after_id
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE related_threads
              DROP FOREIGN KEY fk_related_ai_generation,
              DROP KEY idx_related_ai_overlay,
              DROP COLUMN ai_generation_id,
              DROP COLUMN ai_reason,
              DROP COLUMN ai_selected,
              DROP COLUMN ai_selected_at
        SQL);

        $pdo->exec('DROP TABLE IF EXISTS thread_intelligence_generations');
        $pdo->exec('DROP TABLE IF EXISTS thread_intelligence_jobs');

        // AI rows cannot survive the narrowed enum / NOT NULL author restore.
        $pdo->exec("DELETE FROM thread_summaries WHERE kind = 'ai' OR author_id IS NULL");
        $pdo->exec('ALTER TABLE thread_summaries DROP FOREIGN KEY fk_summary_parent');
        $pdo->exec('ALTER TABLE thread_summaries DROP COLUMN parent_summary_id');
        $pdo->exec('ALTER TABLE thread_summaries DROP FOREIGN KEY fk_summary_author');
        $pdo->exec('ALTER TABLE thread_summaries MODIFY author_id BIGINT UNSIGNED NOT NULL');
        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_summaries
              ADD CONSTRAINT fk_summary_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE thread_summaries
              MODIFY kind ENUM('manual','canonical_answer') NOT NULL DEFAULT 'manual'
        SQL);
    }
};
