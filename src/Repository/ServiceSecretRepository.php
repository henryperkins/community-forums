<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Single-table SQL for the B2 service-secret registry (service_secrets +
 * service_secret_versions). All times are UTC; LIMIT/INTERVAL quantities are
 * int-cast and concatenated, never bound (PDO EMULATE_PREPARES=false).
 */
final class ServiceSecretRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insertSecret(string $ref, string $ownerType, ?int $ownerId, string $label, ?int $createdBy): int
    {
        return $this->db->insert(
            "INSERT INTO service_secrets (secret_ref, owner_type, owner_id, label, status, latest_version, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'active', 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$ref, $ownerType, $ownerId, $label, $createdBy],
        );
    }

    /** @param array{ciphertext:string,nonce:string,tag:string} $enc */
    public function insertCurrentVersion(int $secretId, int $version, array $enc): int
    {
        return $this->db->insert(
            "INSERT INTO service_secret_versions (secret_id, version, ciphertext, nonce, tag, cipher, key_version, state, created_at)
             VALUES (?, ?, ?, ?, ?, 'aes-256-gcm', 1, 'current', UTC_TIMESTAMP())",
            [$secretId, $version, $enc['ciphertext'], $enc['nonce'], $enc['tag']],
        );
    }

    /** @return array<string,mixed>|null */
    public function findSecretByRef(string $ref): ?array
    {
        return $this->db->fetch('SELECT * FROM service_secrets WHERE secret_ref = ?', [$ref]);
    }

    /** @return array<string,mixed>|null */
    public function lockSecretByRef(string $ref): ?array
    {
        return $this->db->fetch(
            'SELECT id, latest_version, status FROM service_secrets WHERE secret_ref = ? FOR UPDATE',
            [$ref],
        );
    }

    /** @return array<string,mixed>|null */
    public function currentVersionRow(int $secretId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM service_secret_versions WHERE secret_id = ? AND state = 'current' ORDER BY version DESC LIMIT 1",
            [$secretId],
        );
    }

    public function retireCurrentVersion(int $secretId, int $graceSeconds): void
    {
        $grace = max(0, $graceSeconds);
        $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'retired', retired_at = UTC_TIMESTAMP(),
                 retire_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . $grace . " SECOND)
             WHERE secret_id = ? AND state = 'current'",
            [$secretId],
        );
    }

    public function bumpLatestVersion(int $secretId, int $version): void
    {
        $this->db->run(
            'UPDATE service_secrets SET latest_version = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$version, $secretId],
        );
    }

    /** @return array<int,array<string,mixed>> current + in-grace retired, newest version first */
    public function usableVersionRows(int $secretId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM service_secret_versions
             WHERE secret_id = ?
               AND (state = 'current' OR (state = 'retired' AND retire_after IS NOT NULL AND retire_after > UTC_TIMESTAMP()))
             ORDER BY version DESC",
            [$secretId],
        );
    }

    public function versionCount(int $secretId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ?', [$secretId]);
    }

    public function markRevoked(int $secretId, ?int $actorId): void
    {
        $this->db->run(
            "UPDATE service_secrets SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [$actorId, $secretId],
        );
    }

    public function retireAllVersions(int $secretId): void
    {
        // Make every non-destroyed version immediately prunable.
        $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'retired',
                 retired_at = COALESCE(retired_at, UTC_TIMESTAMP()),
                 retire_after = UTC_TIMESTAMP()
             WHERE secret_id = ? AND state IN ('current', 'retired')",
            [$secretId],
        );
    }

    /** @return array<int,array<string,mixed>> id/secret_id/version of retired versions past grace */
    public function pruneCandidates(int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT id, secret_id, version FROM service_secret_versions
             WHERE state = 'retired' AND retire_after IS NOT NULL AND retire_after <= UTC_TIMESTAMP()
             ORDER BY id LIMIT " . $limit,
        );
    }

    /** Idempotent: only a still-`retired` row transitions. Returns affected rows. */
    public function destroyVersion(int $versionId): int
    {
        return $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'destroyed', destroyed_at = UTC_TIMESTAMP(), ciphertext = '', nonce = '', tag = ''
             WHERE id = ? AND state = 'retired'",
            [$versionId],
        )->rowCount();
    }

    public function acquirePruneLock(): bool
    {
        return (int) $this->db->fetchValue("SELECT GET_LOCK('rb_secret_prune', 0)") === 1;
    }

    public function releasePruneLock(): void
    {
        $this->db->run("SELECT RELEASE_LOCK('rb_secret_prune')");
    }
}
