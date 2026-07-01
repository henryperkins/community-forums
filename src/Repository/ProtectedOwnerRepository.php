<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over `protected_owners` (Foundation F5). Backs the "≥1 active
 * recoverable owner" invariant (decision #27) consumed by LastOwnerGuard and
 * RepairService. Returns scalars/bools; all business logic lives in the guard.
 *
 * "Active owner" = a designated row (`is_active = 1`) whose *account* is still
 * active (`users.status = 'active'`). `is_active` alone is a write-once flag no
 * path clears, and `users.status` gained deactivated/pending_deletion/deleted
 * states (migration 0059) — so recoverable-owner-ness must always derive from
 * the live account status, mirroring the legacy `activeAdminCountExcluding`
 * predicate the guard's parity fallback uses.
 */
final class ProtectedOwnerRepository
{
    public function __construct(private Database $db)
    {
    }

    public function hasAnyActiveOwner(): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT EXISTS(
                SELECT 1 FROM protected_owners po
                JOIN users u ON u.id = po.user_id
                WHERE po.is_active = 1 AND u.status = 'active'
            )",
        ) === 1;
    }

    public function isActiveOwner(int $userId): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT EXISTS(
                SELECT 1 FROM protected_owners po
                JOIN users u ON u.id = po.user_id
                WHERE po.user_id = ? AND po.is_active = 1 AND u.status = 'active'
            )",
            [$userId],
        ) === 1;
    }

    public function activeOwnerCountExcluding(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM protected_owners po
             JOIN users u ON u.id = po.user_id
             WHERE po.is_active = 1 AND u.status = 'active' AND po.user_id <> ?",
            [$userId],
        );
    }

    public function designate(int $userId, ?int $designatedBy = null): bool
    {
        return $this->db->run(
            'INSERT IGNORE INTO protected_owners (user_id, is_active, designated_by, designated_at, created_at)
             VALUES (?, 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$userId, $designatedBy],
        )->rowCount() > 0;
    }
}
