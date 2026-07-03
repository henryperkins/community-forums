<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Local install state over `installed_packages` (0049 + 0069). */
final class InstalledPackageRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM installed_packages WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByPackage(int $packageId): ?array
    {
        return $this->db->fetch('SELECT * FROM installed_packages WHERE package_id = ?', [$packageId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             ORDER BY p.package_uid',
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activeWithContext(): array
    {
        return $this->db->fetchAll(
            "SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type,
                    p.advisory_status AS package_advisory_status,
                    pub.status AS publisher_status,
                    r.version AS release_version, r.advisory_status AS release_advisory_status
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             LEFT JOIN package_publishers pub ON pub.id = p.publisher_id
             LEFT JOIN package_releases r ON r.id = ip.release_id
             WHERE ip.state <> 'uninstalled'
             ORDER BY ip.id",
        );
    }

    /** @param array<string,mixed> $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO installed_packages
                (package_id, release_id, digest, source_registry_id, publisher_id, trust_class,
                 review_status, state, health, compat_min, compat_max, installed_by)
             VALUES (:package_id, :release_id, :digest, :source_registry_id, :publisher_id, :trust_class,
                     :review_status, \'installed\', \'unknown\', :compat_min, :compat_max, :installed_by)',
            [
                'package_id' => $row['package_id'],
                'release_id' => $row['release_id'],
                'digest' => $row['digest'],
                'source_registry_id' => $row['source_registry_id'],
                'publisher_id' => $row['publisher_id'],
                'trust_class' => $row['trust_class'],
                'review_status' => $row['review_status'],
                'compat_min' => $row['compat_min'],
                'compat_max' => $row['compat_max'],
                'installed_by' => $row['installed_by'],
            ],
        );
    }

    /** @param array<string,mixed> $row */
    public function reviveForInstall(int $id, array $row): void
    {
        $this->db->run(
            'UPDATE installed_packages SET
                release_id = :release_id, digest = :digest, source_registry_id = :source_registry_id,
                publisher_id = :publisher_id, trust_class = :trust_class, review_status = :review_status,
                state = \'installed\', health = \'unknown\', pinned = 0, update_policy = \'manual\',
                staged_release_id = NULL, staged_digest = NULL, settings_json = NULL, export_json = NULL,
                exported_at = NULL, retain_until = NULL, uninstalled_at = NULL, quarantine_reason = NULL,
                last_health_check_at = NULL, compat_min = :compat_min, compat_max = :compat_max,
                installed_by = :installed_by, installed_at = UTC_TIMESTAMP()
             WHERE id = :id',
            [
                'release_id' => $row['release_id'],
                'digest' => $row['digest'],
                'source_registry_id' => $row['source_registry_id'],
                'publisher_id' => $row['publisher_id'],
                'trust_class' => $row['trust_class'],
                'review_status' => $row['review_status'],
                'compat_min' => $row['compat_min'],
                'compat_max' => $row['compat_max'],
                'installed_by' => $row['installed_by'],
                'id' => $id,
            ],
        );
    }

    public function setState(int $id, string $state): void
    {
        $this->db->run('UPDATE installed_packages SET state = ? WHERE id = ?', [$state, $id]);
    }

    public function setStateIfCurrent(int $id, string $expectedState, string $state): bool
    {
        $stmt = $this->db->run(
            'UPDATE installed_packages SET state = :state WHERE id = :id AND state = :expected',
            ['state' => $state, 'id' => $id, 'expected' => $expectedState],
        );

        return $stmt->rowCount() === 1;
    }

    public function setHealth(int $id, string $health, ?string $quarantineReason, ?string $checkedAtUtc = null): void
    {
        $this->db->run(
            'UPDATE installed_packages
             SET health = :health, quarantine_reason = :reason,
                 last_health_check_at = COALESCE(:checked, UTC_TIMESTAMP())
             WHERE id = :id',
            ['health' => $health, 'reason' => $quarantineReason, 'checked' => $checkedAtUtc, 'id' => $id],
        );
    }

    public function activateRelease(int $id, int $releaseId, string $digest, ?string $compatMin, ?string $compatMax, string $reviewStatus): void
    {
        $this->db->run(
            'UPDATE installed_packages
             SET release_id = :rid, digest = :digest, compat_min = :cmin, compat_max = :cmax,
                 review_status = :review, staged_release_id = NULL, staged_digest = NULL
             WHERE id = :id',
            ['rid' => $releaseId, 'digest' => $digest, 'cmin' => $compatMin, 'cmax' => $compatMax, 'review' => $reviewStatus, 'id' => $id],
        );
    }

    public function stageRelease(int $id, ?int $releaseId, ?string $digest): void
    {
        $this->db->run(
            'UPDATE installed_packages SET staged_release_id = :rid, staged_digest = :digest WHERE id = :id',
            ['rid' => $releaseId, 'digest' => $digest, 'id' => $id],
        );
    }

    public function setPinned(int $id, bool $pinned): void
    {
        $this->db->run('UPDATE installed_packages SET pinned = ? WHERE id = ?', [$pinned ? 1 : 0, $id]);
    }

    public function setUpdatePolicy(int $id, string $policy): void
    {
        $this->db->run('UPDATE installed_packages SET update_policy = ? WHERE id = ?', [$policy, $id]);
    }

    public function markUninstalled(int $id, string $retainUntilUtc): void
    {
        $this->db->run(
            'UPDATE installed_packages
             SET state = \'uninstalled\', uninstalled_at = UTC_TIMESTAMP(), retain_until = :retain_until,
                 staged_release_id = NULL, staged_digest = NULL
             WHERE id = :id',
            ['retain_until' => $retainUntilUtc, 'id' => $id],
        );
    }

    public function storeExport(int $id, string $exportJson): void
    {
        $this->db->run(
            'UPDATE installed_packages SET export_json = ?, exported_at = UTC_TIMESTAMP() WHERE id = ?',
            [$exportJson, $id],
        );
    }

    public function setSettingsSummary(int $id, ?string $json): void
    {
        $this->db->run(
            'UPDATE installed_packages SET settings_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$json, $id],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function purgeable(string $nowUtc): array
    {
        return $this->db->fetchAll(
            "SELECT ip.*, p.package_uid
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             WHERE ip.state = 'uninstalled' AND ip.retain_until IS NOT NULL AND ip.retain_until <= ?",
            [$nowUtc],
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM installed_packages WHERE id = ?', [$id]);
    }
}
