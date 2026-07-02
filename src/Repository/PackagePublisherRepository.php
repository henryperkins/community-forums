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
}
