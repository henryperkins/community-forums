<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Publisher identities (`package_publishers`, migration 0049). */
final class PackagePublisherRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $publisherUid): ?array
    {
        return $this->db->fetch('SELECT * FROM package_publishers WHERE publisher_uid = ?', [$publisherUid]);
    }

    /** Find or create by uid; snapshot ingest derives uid from the package namespace. */
    public function ensure(string $publisherUid, string $displayName): int
    {
        $existing = $this->findByUid($publisherUid);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO package_publishers (publisher_uid, display_name) VALUES (?, ?)',
            [$publisherUid, $displayName],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM package_publishers WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM package_publishers ORDER BY display_name');
    }

    /** @param string $status active|suspended|revoked */
    public function setStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE package_publishers SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Provenance of who verified lives in moderation_log (written by the caller); the row only stamps the time. */
    public function markVerified(int $id, ?int $actorId): void
    {
        $this->db->run('UPDATE package_publishers SET verified_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function packagesFor(int $publisherId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM packages WHERE publisher_id = ? ORDER BY package_uid',
            [$publisherId],
        );
    }
}
