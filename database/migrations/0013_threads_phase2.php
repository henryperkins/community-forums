<?php

declare(strict_types=1);

/**
 * 0013 · threads — Phase 2 additions: accepted_answer_post_id ("solved",
 * COMMUNITY §11) and the FULLTEXT title index built for search (P2-06).
 * No FK on accepted_answer_post_id (forward/self reference, per SCHEMA §6).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE threads
              ADD COLUMN accepted_answer_post_id BIGINT UNSIGNED NULL,
              ADD FULLTEXT KEY ft_threads_title (title)
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE threads
              DROP KEY ft_threads_title,
              DROP COLUMN accepted_answer_post_id
        SQL);
    }
};
