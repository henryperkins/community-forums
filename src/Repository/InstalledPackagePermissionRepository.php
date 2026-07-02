<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Permission snapshot rows over `installed_package_permissions` (0049 + 0069). */
final class InstalledPackagePermissionRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function forInstall(int $installedId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM installed_package_permissions
             WHERE installed_package_id = ?
             ORDER BY CAST(kind AS CHAR), permission_key',
            [$installedId],
        );
    }

    /** @param list<array{kind:string,key:string,risk:string}> $permissions */
    public function replaceDeclared(int $installedId, array $permissions): void
    {
        $this->deleteFor($installedId);
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted)
             VALUES (:id, :kind, :key, :risk, 1, 0)',
        );
        foreach ($permissions as $permission) {
            $stmt->execute([
                'id' => $installedId,
                'kind' => $permission['kind'],
                'key' => $permission['key'],
                'risk' => $permission['risk'],
            ]);
        }
    }

    /** @param list<array{kind:string,key:string,risk:string,granted:bool}> $permissions */
    public function replaceWithGrants(int $installedId, array $permissions, int $grantedBy): void
    {
        $this->deleteFor($installedId);
        $granted = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted, granted_at, granted_by)
             VALUES (:id, :kind, :key, :risk, 1, 1, UTC_TIMESTAMP(), :by)',
        );
        $ungranted = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted)
             VALUES (:id, :kind, :key, :risk, 1, 0)',
        );
        foreach ($permissions as $permission) {
            if (!empty($permission['granted'])) {
                $granted->execute([
                    'id' => $installedId,
                    'kind' => $permission['kind'],
                    'key' => $permission['key'],
                    'risk' => $permission['risk'],
                    'by' => $grantedBy,
                ]);
                continue;
            }
            $ungranted->execute([
                'id' => $installedId,
                'kind' => $permission['kind'],
                'key' => $permission['key'],
                'risk' => $permission['risk'],
            ]);
        }
    }

    public function grantAll(int $installedId, int $grantedBy): int
    {
        $stmt = $this->db->run(
            'UPDATE installed_package_permissions
             SET granted = 1, granted_at = UTC_TIMESTAMP(), granted_by = :by
             WHERE installed_package_id = :id AND declared = 1 AND granted = 0',
            ['by' => $grantedBy, 'id' => $installedId],
        );
        return $stmt->rowCount();
    }

    public function ungrantedCount(int $installedId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM installed_package_permissions
             WHERE installed_package_id = ? AND declared = 1 AND granted = 0',
            [$installedId],
        );
    }

    public function deleteFor(int $installedId): void
    {
        $this->db->run('DELETE FROM installed_package_permissions WHERE installed_package_id = ?', [$installedId]);
    }
}
