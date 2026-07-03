<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Immutable releases (`package_releases`, migration 0049). */
final class PackageReleaseRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM package_releases WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findVersion(int $packageId, string $version): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM package_releases WHERE package_id = ? AND version = ?',
            [$packageId, $version],
        );
    }

    /** @param array{package_id:int,version:string,digest:string,source_url:?string,license:?string,core_min:?string,core_max:?string,channel:string} $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO package_releases (package_id, version, digest, source_url, license, core_min, core_max, channel, published_at)
             VALUES (:package_id, :version, :digest, :source_url, :license, :core_min, :core_max, :channel, UTC_TIMESTAMP())',
            $row,
        );
    }

    /** Inc 3: persist the verified signed release metadata after acquisition. */
    public function hydrateSignedMetadata(int $id, string $manifestJson, string $signature, string $keyId, string $reviewStatus): void
    {
        $this->db->run(
            'UPDATE package_releases
                SET manifest_json = :manifest_json, signature = :signature, signed_key_id = :signed_key_id, review_status = :review_status
              WHERE id = :id',
            [
                'manifest_json' => $manifestJson,
                'signature' => $signature,
                'signed_key_id' => $keyId,
                'review_status' => $reviewStatus,
                'id' => $id,
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackage(int $packageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_releases WHERE package_id = ? ORDER BY id DESC',
            [$packageId],
        );
    }

    public function setAdvisoryStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE package_releases SET advisory_status = ? WHERE id = ?', [$status, $id]);
    }

    public function setReviewStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE package_releases SET review_status = ? WHERE id = ?', [$status, $id]);
    }
}
