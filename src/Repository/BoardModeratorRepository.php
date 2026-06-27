<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Per-board moderator assignments (P2-08). The capability model is board-scoped:
 * a user may moderate a board iff they are an admin OR have a row here for that
 * board. This repository is the single source the scope resolver consults.
 */
final class BoardModeratorRepository
{
    public function __construct(private Database $db)
    {
    }

    public function isModerator(int $boardId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM board_moderators WHERE board_id = ? AND user_id = ? LIMIT 1',
            [$boardId, $userId],
        ) !== false;
    }

    /** @return int rows affected (1 on a real assignment, 0 if already a moderator). */
    public function assign(int $boardId, int $userId): int
    {
        return $this->db->run(
            'INSERT IGNORE INTO board_moderators (board_id, user_id) VALUES (?, ?)',
            [$boardId, $userId],
        )->rowCount();
    }

    /** @return int rows removed (1 on a real removal, 0 if not a moderator). */
    public function unassign(int $boardId, int $userId): int
    {
        return $this->db->run('DELETE FROM board_moderators WHERE board_id = ? AND user_id = ?', [$boardId, $userId])->rowCount();
    }

    /** @return list<int> board ids this user moderates */
    public function boardsFor(int $userId): array
    {
        $rows = $this->db->fetchAll('SELECT board_id FROM board_moderators WHERE user_id = ?', [$userId]);
        return array_map(static fn (array $r): int => (int) $r['board_id'], $rows);
    }

    /** @return array<int,array<string,mixed>> moderators of a board with handles */
    public function moderatorsFor(int $boardId): array
    {
        return $this->db->fetchAll(
            'SELECT bm.user_id, u.username, u.display_name
             FROM board_moderators bm JOIN users u ON u.id = bm.user_id
             WHERE bm.board_id = ? ORDER BY u.username',
            [$boardId],
        );
    }
}
