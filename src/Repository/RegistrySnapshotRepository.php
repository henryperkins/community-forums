<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Verified-snapshot offline cache + anti-replay watermark (`registry_snapshots`, 0068). */
final class RegistrySnapshotRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null newest applied snapshot by doc-declared generated_at */
    public function latestFor(int $registryId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_snapshots WHERE registry_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1',
            [$registryId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findByDigest(int $registryId, string $digest): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_snapshots WHERE registry_id = ? AND digest = ?',
            [$registryId, $digest],
        );
    }

    public function record(
        int $registryId,
        string $digest,
        string $document,
        string $signature,
        string $keyId,
        string $generatedAt,
        string $expiresAt,
    ): int {
        return $this->db->insert(
            'INSERT INTO registry_snapshots (registry_id, digest, document, signature, key_id, generated_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$registryId, $digest, $document, $signature, $keyId, $generatedAt, $expiresAt],
        );
    }
}
