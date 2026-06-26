<?php

declare(strict_types=1);

/** 0007 · board_slug_history — powers admin slug-change 301 redirects. */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE board_slug_history (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              board_id   BIGINT UNSIGNED NOT NULL,
              old_slug   VARCHAR(64)     NOT NULL,
              changed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_board_slug_history_old_slug (old_slug),
              KEY idx_board_slug_history_board (board_id),
              CONSTRAINT fk_board_slug_history_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS board_slug_history');
    }
};
