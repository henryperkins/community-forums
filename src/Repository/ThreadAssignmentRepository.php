<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ThreadAssignmentRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function current(int $threadId): ?array
    {
        return $this->db->fetch(
            'SELECT ta.*, u.username AS assigned_username, u.display_name AS assigned_display_name
             FROM thread_assignments ta
             JOIN users u ON u.id = ta.assigned_user_id
             WHERE ta.thread_id = ?',
            [$threadId],
        );
    }

    public function assign(int $threadId, int $assignedUserId, int $actorId): void
    {
        $this->db->run(
            'INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at)
             VALUES (:tid, :uid, :actor, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE assigned_user_id = VALUES(assigned_user_id),
                                     assigned_by = VALUES(assigned_by),
                                     assigned_at = UTC_TIMESTAMP()',
            ['tid' => $threadId, 'uid' => $assignedUserId, 'actor' => $actorId],
        );
    }

    public function unassign(int $threadId): void
    {
        $this->db->run('DELETE FROM thread_assignments WHERE thread_id = ?', [$threadId]);
    }

    public function addHistory(
        int $threadId,
        ?int $previousUserId,
        ?int $assignedUserId,
        int $actorId,
        string $action,
        ?string $reason = null,
    ): void {
        $this->db->run(
            'INSERT INTO thread_assignment_history
                (thread_id, previous_user_id, assigned_user_id, actor_id, action, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$threadId, $previousUserId, $assignedUserId, $actorId, $action, $reason],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $threadId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            'SELECT h.*,
                    prev.username AS previous_username,
                    assignee.username AS assigned_username,
                    actor.username AS actor_username
             FROM thread_assignment_history h
             LEFT JOIN users prev ON prev.id = h.previous_user_id
             LEFT JOIN users assignee ON assignee.id = h.assigned_user_id
             JOIN users actor ON actor.id = h.actor_id
             WHERE h.thread_id = ?
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT ' . $limit,
            [$threadId],
        );
    }
}
