<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Publisher signing-key custody (`publisher_signing_keys`, migration 0070;
 * inert until Inc 5). Mirrors RegistryTrustKeyRepository, keyed on publisher.
 */
final class PublisherSigningKeyRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> newest first — the list handed to TrustChainVerifier. */
    public function forPublisher(int $publisherId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM publisher_signing_keys WHERE publisher_id = ? ORDER BY id DESC',
            [$publisherId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM publisher_signing_keys WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findKey(int $publisherId, string $keyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM publisher_signing_keys WHERE publisher_id = ? AND key_id = ?',
            [$publisherId, $keyId],
        );
    }

    public function pin(int $publisherId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int
    {
        return $this->db->insert(
            'INSERT INTO publisher_signing_keys (publisher_id, key_id, algorithm, public_key, status, valid_from, valid_until)
             VALUES (?, ?, \'ed25519\', ?, \'active\', ?, ?)',
            [$publisherId, $keyId, $publicKey, $validFrom, $validUntil],
        );
    }

    public function markRotated(int $id): void
    {
        $this->db->run(
            "UPDATE publisher_signing_keys SET status = 'rotated', valid_until = UTC_TIMESTAMP() WHERE id = ?",
            [$id],
        );
    }

    public function revoke(int $id, string $reason): void
    {
        $this->db->run(
            "UPDATE publisher_signing_keys SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ?",
            [$reason, $id],
        );
    }
}
