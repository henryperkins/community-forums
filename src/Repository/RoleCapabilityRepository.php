<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over role_capabilities.
 */
final class RoleCapabilityRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<string> */
    public function roleKeysHolding(string $capabilityKey): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.role_key
             FROM role_capabilities rc
             JOIN roles r ON r.id = rc.role_id
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE c.capability_key = ?',
            [$capabilityKey],
        );

        return array_map(static fn (array $row): string => (string) $row['role_key'], $rows);
    }

    /** @return list<string> */
    public function keysForRole(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.capability_key
             FROM role_capabilities rc
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE rc.role_id = ?
             ORDER BY c.id ASC',
            [$roleId],
        );

        return array_map(static fn (array $row): string => (string) $row['capability_key'], $rows);
    }

    /** @param list<int> $capabilityIds */
    public function replaceForRole(int $roleId, array $capabilityIds): void
    {
        $this->db->run('DELETE FROM role_capabilities WHERE role_id = ?', [$roleId]);

        foreach ($capabilityIds as $capabilityId) {
            $this->db->run(
                'INSERT IGNORE INTO role_capabilities (role_id, capability_id) VALUES (?, ?)',
                [$roleId, (int) $capabilityId],
            );
        }
    }
}
