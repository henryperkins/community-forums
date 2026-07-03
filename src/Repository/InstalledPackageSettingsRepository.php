<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Per-install settings over `installed_package_settings` (migration 0073). */
final class InstalledPackageSettingsRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function forInstall(int $installedId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM installed_package_settings WHERE installed_package_id = ? ORDER BY setting_key',
            [$installedId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $installedId, string $key): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM installed_package_settings WHERE installed_package_id = ? AND setting_key = ?',
            [$installedId, $key],
        );
    }

    public function upsert(int $installedId, string $key, ?string $valueJson, ?string $secretRef, bool $isSecret, ?int $updatedBy): int
    {
        return $this->db->insert(
            'INSERT INTO installed_package_settings
                (installed_package_id, setting_key, value_json, secret_ref, is_secret, updated_by, updated_at)
             VALUES (:iid, :k, :val, :ref, :sec, :by, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                id         = LAST_INSERT_ID(id),
                value_json = VALUES(value_json),
                secret_ref = VALUES(secret_ref),
                is_secret  = VALUES(is_secret),
                updated_by = VALUES(updated_by),
                updated_at = UTC_TIMESTAMP()',
            [
                'iid' => $installedId,
                'k' => $key,
                'val' => $valueJson,
                'ref' => $secretRef,
                'sec' => $isSecret ? 1 : 0,
                'by' => $updatedBy,
            ],
        );
    }

    /** @return list<string> svcsec_* references still held by this install (drives cleanup). */
    public function secretRefsFor(int $installedId): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['secret_ref'],
            $this->db->fetchAll(
                'SELECT secret_ref FROM installed_package_settings
                 WHERE installed_package_id = ? AND secret_ref IS NOT NULL',
                [$installedId],
            ),
        );
    }

    public function deleteFor(int $installedId): void
    {
        $this->db->run('DELETE FROM installed_package_settings WHERE installed_package_id = ?', [$installedId]);
    }
}
