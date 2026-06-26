<?php

declare(strict_types=1);

/**
 * 0041 · users.reputation index (P2-12 hardening). The all-time leaderboard
 * (COMMUNITY §7) orders by reputation over the whole users table; without an
 * index that is a full scan + filesort (verified via EXPLAIN: type=ALL,
 * Using filesort). With this index the query becomes a bounded index range scan
 * (type=range). The leaderboard sorts `reputation DESC, id DESC`; since InnoDB
 * appends the PK (id) to a secondary index, a backward read of (reputation)
 * serves that exact order, so the filesort is also eliminated (EXPLAIN:
 * Using where; Using index). Additive and reversible.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE users ADD KEY idx_users_reputation (reputation)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE users DROP KEY idx_users_reputation');
    }
};
