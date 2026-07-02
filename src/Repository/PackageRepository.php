<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Registry package identities (`packages`, migration 0049). */
final class PackageRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $packageUid): ?array
    {
        return $this->db->fetch('SELECT * FROM packages WHERE package_uid = ?', [$packageUid]);
    }

    /** @param array{package_uid:string,registry_id:?int,publisher_id:?int,name:string,type:string,trust_class:string} $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO packages (package_uid, registry_id, publisher_id, name, type, trust_class)
             VALUES (:package_uid, :registry_id, :publisher_id, :name, :type, :trust_class)',
            $row,
        );
    }

    public function setLatestRelease(int $id, ?int $releaseId): void
    {
        $this->db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$releaseId, $id]);
    }

    public function setAdvisoryStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE packages SET advisory_status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function catalog(): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, r.source_id AS registry_source_id, r.display_name AS registry_name,
                    pub.publisher_uid, pub.display_name AS publisher_name
             FROM packages p
             LEFT JOIN package_registries r ON r.id = p.registry_id
             LEFT JOIN package_publishers pub ON pub.id = p.publisher_id
             ORDER BY p.package_uid ASC',
        );
    }
}
