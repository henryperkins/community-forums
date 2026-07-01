<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over `protected_owners` (Foundation F5). Backs the "≥1 active
 * recoverable owner" invariant (decision #27) consumed by LastOwnerGuard and
 * RepairService. Returns scalars/bools; all business logic lives in the guard.
 */
final class ProtectedOwnerRepository
{
    public function __construct(private Database $db)
    {
    }

    public function hasAnyActiveOwner(): bool
    {
        return (int) $this->db->fetchValue('SELECT EXISTS(SELECT 1 FROM protected_owners WHERE is_active = 1)') === 1;
    }

    public function isActiveOwner(int $userId): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT EXISTS(SELECT 1 FROM protected_owners WHERE user_id = ? AND is_active = 1)',
            [$userId],
        ) === 1;
    }

    public function activeOwnerCountExcluding(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM protected_owners WHERE is_active = 1 AND user_id <> ?',
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
