<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BoardRepository;

/**
 * Denormalised board-counter recomputation after bulk thread moves (PR #44
 * spec §4 boundary move: the multi-table UPDATEs lived in BoardRepository,
 * which is otherwise a single-table wrapper). The SQL moved verbatim.
 */
final class BoardRecountService
{
    public function __construct(
        private Database $db,
        private BoardRepository $boards,
    ) {
    }

    /**
     * Recompute thread_count and post_count for ONE board from authoritative
     * rows, using the exact WHERE clauses RepairService::repairBoardCounters()
     * uses (is_deleted = 0 AND is_pending = 0 on both threads and posts), then
     * refresh the last-activity cache. Used after a bulk thread move so the
     * destination board's denormalised counters cannot drift.
     */
    public function recount(int $boardId): void
    {
        $this->db->run(
            'UPDATE boards b SET thread_count = (
                SELECT COUNT(*) FROM threads t WHERE t.board_id = b.id AND t.is_deleted = 0 AND t.is_pending = 0
             ) WHERE b.id = ?',
            [$boardId],
        );
        $this->db->run(
            'UPDATE boards b SET post_count = (
                SELECT COUNT(*) FROM posts p
                JOIN threads t ON t.id = p.thread_id
                WHERE t.board_id = b.id AND p.is_deleted = 0 AND p.is_pending = 0
                  AND t.is_deleted = 0 AND t.is_pending = 0
             ) WHERE b.id = ?',
            [$boardId],
        );
        $this->boards->recomputeLastPost($boardId);
    }
}
