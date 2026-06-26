<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

/**
 * Idempotent counter reconciliation / repair (PHASE_2_PLAN §2 "all denormalised
 * counters have reconciliation tests or a repair command", Milestone 1).
 *
 * Each method recomputes a denormalised counter from the authoritative rows and
 * is safe to run repeatedly. Used by `bin/console repair:*` and by tests to
 * assert the transactional counters never drift.
 */
final class RepairService
{
    public function __construct(private Database $db, private int $solvedBonus = 5)
    {
    }

    /** users.post_count = number of the user's non-deleted posts. */
    public function repairUserPostCounts(): int
    {
        return $this->db->run(
            'UPDATE users u SET post_count = (
                SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.is_deleted = 0
             )',
        )->rowCount();
    }

    /**
     * users.reputation = Σ reactions received on the user's non-deleted posts,
     * excluding self-reactions (COMMUNITY §2.1/§10). The solved-answer bonus is
     * layered in by the community milestone via reputationSolvedBonus().
     */
    public function repairReputation(): int
    {
        return $this->db->run(
            "UPDATE users u SET reputation = (
                SELECT COALESCE(COUNT(*), 0)
                FROM reactions r
                JOIN posts p ON p.id = r.post_id
                WHERE p.user_id = u.id AND p.is_deleted = 0 AND r.user_id <> u.id
             )",
        )->rowCount();
    }

    /**
     * Add the accepted-answer reputation bonus on top of the reaction-derived
     * base (COMMUNITY §2.1). Must run AFTER repairReputation(), which sets the
     * reaction base. A bonus accrues once per accepted answer whose author is not
     * the thread OP (self-answers earn nothing — see SolvedAnswerService).
     */
    public function reputationSolvedBonus(): int
    {
        return $this->db->run(
            'UPDATE users u SET reputation = reputation + (
                SELECT COALESCE(COUNT(*), 0) * :bonus
                FROM threads t
                JOIN posts p ON p.id = t.accepted_answer_post_id
                WHERE p.user_id = u.id AND p.user_id <> t.user_id
                  AND t.is_deleted = 0 AND p.is_deleted = 0
             )',
            ['bonus' => $this->solvedBonus],
        )->rowCount();
    }

    /**
     * threads: reply_count = non-OP non-deleted posts; last_post_* = newest
     * non-deleted post (NULL when none).
     */
    public function repairThreadCounters(): int
    {
        $this->db->run(
            'UPDATE threads t SET reply_count = (
                SELECT COUNT(*) FROM posts p
                WHERE p.thread_id = t.id AND p.is_deleted = 0 AND p.is_op = 0
             )',
        );
        // last_post_* from the newest non-deleted post in the thread.
        $this->db->run(
            'UPDATE threads t
             LEFT JOIN (
                SELECT x.thread_id, x.id AS pid, x.user_id AS uid, x.created_at AS at
                FROM posts x
                JOIN (
                    SELECT thread_id, MAX(id) AS max_id
                    FROM posts WHERE is_deleted = 0 GROUP BY thread_id
                ) m ON m.thread_id = x.thread_id AND m.max_id = x.id
             ) lp ON lp.thread_id = t.id
             SET t.last_post_id = lp.pid, t.last_post_user_id = lp.uid, t.last_post_at = lp.at',
        );
        return 1;
    }

    /**
     * boards: thread_count = non-deleted threads; post_count = non-deleted posts
     * across non-deleted threads; last_thread_id/last_post_at = newest activity.
     */
    public function repairBoardCounters(): int
    {
        $this->db->run(
            'UPDATE boards b SET thread_count = (
                SELECT COUNT(*) FROM threads t WHERE t.board_id = b.id AND t.is_deleted = 0
             )',
        );
        $this->db->run(
            'UPDATE boards b SET post_count = (
                SELECT COUNT(*) FROM posts p
                JOIN threads t ON t.id = p.thread_id
                WHERE t.board_id = b.id AND p.is_deleted = 0 AND t.is_deleted = 0
             )',
        );
        $this->db->run(
            'UPDATE boards b
             LEFT JOIN (
                SELECT t.board_id, x.thread_id AS tid, x.created_at AS at
                FROM posts x
                JOIN threads t ON t.id = x.thread_id
                JOIN (
                    SELECT t2.board_id, MAX(p2.id) AS max_id
                    FROM posts p2 JOIN threads t2 ON t2.id = p2.thread_id
                    WHERE p2.is_deleted = 0 AND t2.is_deleted = 0
                    GROUP BY t2.board_id
                ) m ON m.board_id = t.board_id AND m.max_id = x.id
             ) lp ON lp.board_id = b.id
             SET b.last_thread_id = lp.tid, b.last_post_at = lp.at',
        );
        return 1;
    }

    /** Run every repair pass. @return array<string,int> */
    public function repairAll(): array
    {
        return $this->db->transaction(function (): array {
            $out = [
                'user_post_counts' => $this->repairUserPostCounts(),
                'thread_counters' => $this->repairThreadCounters(),
                'board_counters' => $this->repairBoardCounters(),
                'reputation' => $this->repairReputation(),
            ];
            // Layer the solved-answer bonus onto the reaction-derived base.
            $this->reputationSolvedBonus();
            return $out;
        });
    }
}
