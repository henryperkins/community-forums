<?php

declare(strict_types=1);

/**
 * 0027 · reports — user reports of posts (DESIGN §8.2 + ADMIN §10.2; SCHEMA §7.5).
 * Lifecycle open→triaged→resolved/dismissed. "One open report per (user, post)"
 * dedupe is enforced in app logic. notify_reporter drives reporter
 * outcome-notifications (PHASE_2 §3, ADMIN §3.1/§11).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE reports (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              reporter_id     BIGINT UNSIGNED NOT NULL,
              post_id         BIGINT UNSIGNED NOT NULL,
              reason_code     ENUM('spam','harassment','off_topic','nsfw','illegal','other') NULL,
              reason          VARCHAR(255)    NULL,
              status          ENUM('open','triaged','resolved','dismissed') NOT NULL DEFAULT 'open',
              assigned_to     BIGINT UNSIGNED NULL,
              handled_by      BIGINT UNSIGNED NULL,
              notify_reporter TINYINT(1)      NOT NULL DEFAULT 0,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              resolved_at     DATETIME        NULL,
              PRIMARY KEY (id),
              KEY idx_reports_status (status, created_at),
              KEY idx_reports_post (post_id),
              CONSTRAINT fk_report_post     FOREIGN KEY (post_id)     REFERENCES posts(id) ON DELETE CASCADE,
              CONSTRAINT fk_report_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS reports');
    }
};
