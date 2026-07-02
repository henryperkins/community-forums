<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over role_assignments. Decision-time reads include expired rows;
 * CapabilityRules enforces temporal windows directly.
 */
final class RoleAssignmentRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function rowsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT ra.*, r.role_key, r.role_rank
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id
             WHERE ra.subject_type = 'user'
               AND ra.subject_id = ?
               AND ra.revoked_at IS NULL",
            [$userId],
        );
    }

    /**
     * @param array{subject_type?:string,subject_id:int,role_id:int,scope_type?:string,scope_id?:?int,grantor_id?:?int,reason?:?string,starts_at?:?string,ends_at?:?string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO role_assignments
                (subject_type, subject_id, role_id, scope_type, scope_id, grantor_id, reason, starts_at, ends_at, assignment_version, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())',
            [
                $data['subject_type'] ?? 'user',
                $data['subject_id'],
                $data['role_id'],
                $data['scope_type'] ?? 'site',
                $data['scope_id'] ?? null,
                $data['grantor_id'] ?? null,
                $data['reason'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
            ],
        );
    }

    /**
     * @param list<int> $roleIds
     * @return array<int,int>
     */
    public function countActiveForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $in = implode(',', array_map('intval', $roleIds));
        $rows = $this->db->fetchAll(
            "SELECT role_id, COUNT(*) AS n
             FROM role_assignments
             WHERE role_id IN ($in)
               AND revoked_at IS NULL
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
               AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())
             GROUP BY role_id",
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['role_id']] = (int) $row['n'];
        }

        return $out;
    }
}
