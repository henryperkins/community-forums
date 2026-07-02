<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Public trust-key material only (`registry_trust_keys`, migration 0049). */
final class RegistryTrustKeyRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function forRegistry(int $registryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM registry_trust_keys WHERE registry_id = ? ORDER BY id DESC',
            [$registryId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM registry_trust_keys WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findKey(int $registryId, string $keyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_trust_keys WHERE registry_id = ? AND key_id = ?',
            [$registryId, $keyId],
        );
    }

    public function pin(int $registryId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int
    {
        return $this->db->insert(
            'INSERT INTO registry_trust_keys (registry_id, key_id, algorithm, public_key, status, valid_from, valid_until)
             VALUES (?, ?, \'ed25519\', ?, \'active\', ?, ?)',
            [$registryId, $keyId, $publicKey, $validFrom, $validUntil],
        );
    }

    /** Rotated keys stop signing new transitions: window closes now. */
    public function markRotated(int $id): void
    {
        $this->db->run(
            "UPDATE registry_trust_keys SET status = 'rotated', valid_until = UTC_TIMESTAMP() WHERE id = ?",
            [$id],
        );
    }

    public function revoke(int $id, string $reason): void
    {
        $this->db->run(
            "UPDATE registry_trust_keys SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ?",
            [$reason, $id],
        );
    }
}
