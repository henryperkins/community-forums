<?php

declare(strict_types=1);

/**
 * 0060 - Moderation appeals.
 *
 * Durable, immutable user appeals for deleted posts and user-targeted
 * moderation actions. Events append every state change; appeals are never
 * physically deleted by ordinary cleanup.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE moderation_appeals (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              appellant_id      BIGINT UNSIGNED NOT NULL,
              target_type       ENUM('post','user') NOT NULL,
              target_id         BIGINT UNSIGNED NOT NULL,
              moderation_log_id BIGINT UNSIGNED NULL,
              original_action   VARCHAR(40)     NULL,
              target_summary    VARCHAR(255)    NULL,
              reason            TEXT            NOT NULL,
              status            ENUM('open','upheld','modified','reversed','dismissed') NOT NULL DEFAULT 'open',
              resolution_note   TEXT            NULL,
              resolved_by       BIGINT UNSIGNED NULL,
              resolved_at       DATETIME        NULL,
              created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at        DATETIME        NULL,
              PRIMARY KEY (id),
              KEY idx_appeals_appellant (appellant_id, created_at),
              KEY idx_appeals_status (status, created_at),
              KEY idx_appeals_target (target_type, target_id, status),
              KEY idx_appeals_log (moderation_log_id),
              CONSTRAINT fk_appeal_appellant FOREIGN KEY (appellant_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_appeal_log FOREIGN KEY (moderation_log_id) REFERENCES moderation_log(id) ON DELETE SET NULL,
              CONSTRAINT fk_appeal_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE moderation_appeal_events (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              appeal_id  BIGINT UNSIGNED NOT NULL,
              actor_id   BIGINT UNSIGNED NULL,
              event      ENUM('opened','upheld','modified','reversed','dismissed') NOT NULL,
              note       TEXT            NULL,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_appeal_events_appeal (appeal_id, created_at),
              CONSTRAINT fk_appeal_event_appeal FOREIGN KEY (appeal_id) REFERENCES moderation_appeals(id) ON DELETE CASCADE,
              CONSTRAINT fk_appeal_event_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS moderation_appeal_events');
        $pdo->exec('DROP TABLE IF EXISTS moderation_appeals');
    }
};
