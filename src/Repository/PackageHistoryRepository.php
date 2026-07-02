<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Append-only lifecycle history over `package_history` (0049 + 0069). */
final class PackageHistoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $entry */
    public function record(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO package_history
                (package_id, installed_package_id, event, actor_id, prior_version, new_version,
                 prior_digest, new_digest, permission_snapshot_json, approval_ref, failure_stage, detail)
             VALUES (:package_id, :installed_package_id, :event, :actor_id, :prior_version, :new_version,
                     :prior_digest, :new_digest, :permission_snapshot_json, :approval_ref, :failure_stage, :detail)',
            [
                'package_id' => $entry['package_id'] ?? null,
                'installed_package_id' => $entry['installed_package_id'] ?? null,
                'event' => $entry['event'],
                'actor_id' => $entry['actor_id'] ?? null,
                'prior_version' => $entry['prior_version'] ?? null,
                'new_version' => $entry['new_version'] ?? null,
                'prior_digest' => $entry['prior_digest'] ?? null,
                'new_digest' => $entry['new_digest'] ?? null,
                'permission_snapshot_json' => $entry['permission_snapshot_json'] ?? null,
                'approval_ref' => $entry['approval_ref'] ?? null,
                'failure_stage' => $entry['failure_stage'] ?? null,
                'detail' => $entry['detail'] ?? null,
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackage(int $packageId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM package_history WHERE package_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$packageId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forInstall(int $installedId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM package_history WHERE installed_package_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$installedId],
        );
    }

    /** @return list<string> */
    public function verifiedDigestsFor(int $packageId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT new_digest AS digest FROM package_history
              WHERE package_id = :package_new AND event IN ('install','update','rollback')
                AND failure_stage IS NULL AND new_digest IS NOT NULL
             UNION
             SELECT DISTINCT prior_digest AS digest FROM package_history
              WHERE package_id = :package_prior AND event IN ('install','update','rollback')
                AND failure_stage IS NULL AND prior_digest IS NOT NULL",
            ['package_new' => $packageId, 'package_prior' => $packageId],
        );

        return array_map(static fn (array $row): string => (string) $row['digest'], $rows);
    }
}
