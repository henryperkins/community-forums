<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Append-only publication/lifecycle history for released and revoked digests. */
final class PackageTransparencyLogRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $entry */
    public function record(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO package_transparency_log
                (package_uid, version, digest, event, source, actor_id, registry_id, detail)
             VALUES (:package_uid, :version, :digest, :event, :source, :actor_id, :registry_id, :detail)',
            [
                'package_uid' => $entry['package_uid'],
                'version' => $entry['version'] ?? null,
                'digest' => $entry['digest'] ?? null,
                'event' => $entry['event'],
                'source' => $entry['source'],
                'actor_id' => $entry['actor_id'] ?? null,
                'registry_id' => $entry['registry_id'] ?? null,
                'detail' => $entry['detail'] ?? null,
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackageUid(string $packageUid, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            'SELECT * FROM package_transparency_log WHERE package_uid = ? ORDER BY id DESC LIMIT ' . $limit,
            [$packageUid],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        return $this->db->fetchAll('SELECT * FROM package_transparency_log ORDER BY id DESC LIMIT ' . $limit);
    }
}
