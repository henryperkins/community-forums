<?php

declare(strict_types=1);

/**
 * 0019 · reactions — emoji reactions on posts (DESIGN §8.2). One per
 * (user, post, emoji). Reaction received = +1 reputation (self-reactions
 * excluded in app logic, not by a DB constraint — COMMUNITY §2.1/§10).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE reactions (
              id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              post_id    BIGINT UNSIGNED NOT NULL,
              user_id    BIGINT UNSIGNED NOT NULL,
              emoji      VARCHAR(16)     NOT NULL,
              created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_reaction (post_id, user_id, emoji),
              CONSTRAINT fk_react_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
              CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS reactions');
    }
};
