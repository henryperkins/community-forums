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

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM role_assignments WHERE id = ?', [$id]);
    }

    /**
     * The existing non-revoked assignment for this exact (subject, role, scope),
     * if any — used to refuse a duplicate grant. `scope_id <=> ?` is the NULL-safe
     * equality operator so a second site-scoped (scope_id IS NULL) grant is caught
     * too. Revoked rows are excluded so a revoke-then-regrant is allowed.
     *
     * @return array<string,mixed>|null
     */
    public function findActiveDuplicate(int $subjectId, int $roleId, string $scopeType, ?int $scopeId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM role_assignments
             WHERE subject_type = 'user' AND subject_id = ? AND role_id = ?
               AND scope_type = ? AND scope_id <=> ?
               AND revoked_at IS NULL
             LIMIT 1",
            [$subjectId, $roleId, $scopeType, $scopeId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM role_assignments WHERE id = ? FOR UPDATE', [$id]);
    }

    public function revoke(int $id, int $revokedBy): void
    {
        $this->db->run(
            'UPDATE role_assignments
             SET revoked_at = UTC_TIMESTAMP(), revoked_by = ?, assignment_version = assignment_version + 1
             WHERE id = ? AND revoked_at IS NULL',
            [$revokedBy, $id],
        );
    }

    public function updateEndsAt(int $id, string $endsAt): void
    {
        $this->db->run(
            'UPDATE role_assignments
             SET ends_at = ?, assignment_version = assignment_version + 1
             WHERE id = ?',
            [$endsAt, $id],
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForRole(int $roleId): array
    {
        return $this->db->fetchAll(
            "SELECT ra.*, u.username
             FROM role_assignments ra
             JOIN users u ON u.id = ra.subject_id AND ra.subject_type = 'user'
             WHERE ra.role_id = ?
             ORDER BY ra.id DESC",
            [$roleId],
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
