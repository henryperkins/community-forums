<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Registry sources (`package_registries`, migration 0049). */
final class PackageRegistryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM package_registries ORDER BY id ASC');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM package_registries WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySourceId(string $sourceId): ?array
    {
        return $this->db->fetch('SELECT * FROM package_registries WHERE source_id = ?', [$sourceId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function enabled(): array
    {
        return $this->db->fetchAll('SELECT * FROM package_registries WHERE is_enabled = 1 ORDER BY id ASC');
    }

    public function create(string $sourceId, string $displayName, string $baseUrl): int
    {
        return $this->db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 0)',
            [$sourceId, $displayName, $baseUrl],
        );
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->run('UPDATE package_registries SET is_enabled = ? WHERE id = ?', [$enabled ? 1 : 0, $id]);
    }

    /** Record the last verified snapshot digest and document-declared window. */
    public function recordSnapshot(int $id, string $digest, string $generatedAt, string $expiresAt): void
    {
        $this->db->run(
            'UPDATE package_registries SET last_snapshot_digest = ?, last_snapshot_at = ?, snapshot_expires_at = ? WHERE id = ?',
            [$digest, $generatedAt, $expiresAt, $id],
        );
    }
}
