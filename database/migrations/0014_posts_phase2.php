<?php

declare(strict_types=1);

/**
 * 0014 · posts — Phase 2 additions: ip (post-time IP, ban-evasion signal —
 * Admin-only/audited, 90-day retention purge is a Phase 3 seam) and the
 * FULLTEXT body index built for search (P2-06).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE posts
              ADD COLUMN ip VARBINARY(16) NULL,
              ADD FULLTEXT KEY ft_posts_body (body)
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE posts
              DROP KEY ft_posts_body,
              DROP COLUMN ip
        SQL);
    }
};
