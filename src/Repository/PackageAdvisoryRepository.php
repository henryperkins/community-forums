<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Cached signed advisories (`package_advisories`, migration 0049). */
final class PackageAdvisoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $advisoryUid): ?array
    {
        return $this->db->fetch('SELECT * FROM package_advisories WHERE advisory_uid = ?', [$advisoryUid]);
    }

    /**
     * @param array{advisory_uid:string,registry_id:?int,package_id:?int,affected_version_range:?string,affected_digest:?string,severity:string,action:string,summary:?string,signed_evidence:?string,issued_at:?string} $row
     */
    public function upsert(array $row): int
    {
        $existing = $this->findByUid($row['advisory_uid']);
        if ($existing === null) {
            return $this->db->insert(
                'INSERT INTO package_advisories
                   (advisory_uid, registry_id, package_id, affected_version_range, affected_digest, severity, action, summary, signed_evidence, issued_at)
                 VALUES (:advisory_uid, :registry_id, :package_id, :affected_version_range, :affected_digest, :severity, :action, :summary, :signed_evidence, :issued_at)',
                $row,
            );
        }

        $this->db->run(
            'UPDATE package_advisories
                SET registry_id = :registry_id, package_id = :package_id,
                    affected_version_range = :affected_version_range, affected_digest = :affected_digest,
                    severity = :severity, action = :action, summary = :summary,
                    signed_evidence = :signed_evidence, issued_at = :issued_at
              WHERE id = :id',
            [
                'registry_id' => $row['registry_id'],
                'package_id' => $row['package_id'],
                'affected_version_range' => $row['affected_version_range'],
                'affected_digest' => $row['affected_digest'],
                'severity' => $row['severity'],
                'action' => $row['action'],
                'summary' => $row['summary'],
                'signed_evidence' => $row['signed_evidence'],
                'issued_at' => $row['issued_at'],
                'id' => (int) $existing['id'],
            ],
        );

        return (int) $existing['id'];
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackage(int $packageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_advisories WHERE package_id = ? ORDER BY id DESC',
            [$packageId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, p.package_uid
             FROM package_advisories a
             LEFT JOIN packages p ON p.id = a.package_id
             ORDER BY a.id DESC',
        );
    }

    public function acknowledge(int $id, int $userId): void
    {
        $this->db->run(
            'UPDATE package_advisories SET acknowledged_at = UTC_TIMESTAMP(), acknowledged_by = ? WHERE id = ?',
            [$userId, $id],
        );
    }
}
