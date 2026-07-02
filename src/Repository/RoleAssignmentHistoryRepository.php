<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Append-only role and assignment audit wrapper.
 */
final class RoleAssignmentHistoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{assignment_id?:?int,event:string,actor_id:?int,subject_type?:?string,subject_id?:?int,role_id:?int,scope_type?:?string,scope_id?:?int,before:?array,after:?array,reason?:?string} $entry
     */
    public function log(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO role_assignment_history
                (assignment_id, event, actor_id, subject_type, subject_id, role_id, scope_type, scope_id, before_json, after_json, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $entry['assignment_id'] ?? null,
                $entry['event'],
                $entry['actor_id'] ?? null,
                $entry['subject_type'] ?? null,
                $entry['subject_id'] ?? null,
                $entry['role_id'] ?? null,
                $entry['scope_type'] ?? null,
                $entry['scope_id'] ?? null,
                $entry['before'] === null ? null : json_encode($entry['before'], JSON_UNESCAPED_SLASHES),
                $entry['after'] === null ? null : json_encode($entry['after'], JSON_UNESCAPED_SLASHES),
                $entry['reason'] ?? null,
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    public function forRole(int $roleId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->db->fetchAll(
            "SELECT * FROM role_assignment_history WHERE role_id = ? ORDER BY id DESC LIMIT $limit",
            [$roleId],
        );
    }
}
