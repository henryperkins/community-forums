<?php

declare(strict_types=1);

/** 0010 · moderation_log — append-only audit trail (polymorphic target). */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE moderation_log (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              actor_id    BIGINT UNSIGNED NULL,
              action      VARCHAR(40)     NOT NULL,
              target_type ENUM('thread','post','user','board','category','setting') NOT NULL,
              target_id   BIGINT UNSIGNED NOT NULL,
              reason      VARCHAR(255)    NULL,
              before_json JSON            NULL,
              after_json  JSON            NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_modlog_target (target_type, target_id),
              KEY idx_modlog_actor (actor_id, created_at),
              CONSTRAINT fk_modlog_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS moderation_log');
    }
};
