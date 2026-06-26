<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Private/hidden board membership (P2-08). Activating this moves private boards
 * from Phase 1's admin-only hold to member-scoped reads: an added member can
 * read; a removed member immediately loses direct, search, unread, and
 * notification access.
 */
final class BoardMemberRepository
{
    public function __construct(private Database $db)
    {
    }

    public function isMember(int $boardId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ? LIMIT 1',
            [$boardId, $userId],
        ) !== false;
    }

    public function add(int $boardId, int $userId, ?int $addedBy): void
    {
        $this->db->run(
            'INSERT IGNORE INTO board_members (board_id, user_id, added_by, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$boardId, $userId, $addedBy],
        );
    }

    public function remove(int $boardId, int $userId): void
    {
        $this->db->run('DELETE FROM board_members WHERE board_id = ? AND user_id = ?', [$boardId, $userId]);
    }

    /** Board ids the user belongs to (to annotate nav/listings in one query). */
    public function boardIdsFor(int $userId): array
    {
        $rows = $this->db->fetchAll('SELECT board_id FROM board_members WHERE user_id = ?', [$userId]);
        return array_map(static fn (array $r): int => (int) $r['board_id'], $rows);
    }

    /** @return array<int,array<string,mixed>> members of a board with handles */
    public function membersFor(int $boardId): array
    {
        return $this->db->fetchAll(
            'SELECT bm.user_id, u.username, u.display_name, bm.created_at
             FROM board_members bm JOIN users u ON u.id = bm.user_id
             WHERE bm.board_id = ? ORDER BY u.username',
            [$boardId],
        );
    }
}
