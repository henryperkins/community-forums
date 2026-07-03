<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Package-owned credential links over `installed_package_credentials` (migration 0073). */
final class InstalledPackageCredentialRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> active + revoked. */
    public function forInstall(int $installedId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM installed_package_credentials WHERE installed_package_id = ? ORDER BY id DESC',
            [$installedId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function activeForInstall(int $installedId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM installed_package_credentials
             WHERE installed_package_id = ? AND revoked_at IS NULL ORDER BY id DESC',
            [$installedId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByApiToken(int $apiTokenId): ?array
    {
        return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE api_token_id = ? ORDER BY id DESC LIMIT 1', [$apiTokenId]);
    }

    /** @return array<string,mixed>|null */
    public function findByWebhook(int $webhookId): ?array
    {
        return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE webhook_id = ? ORDER BY id DESC LIMIT 1', [$webhookId]);
    }

    public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO installed_package_credentials
                (installed_package_id, kind, api_token_id, label, scopes_json, created_by, created_at)
             VALUES (?, \'api_token\', ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$installedId, $apiTokenId, $label, $scopesJson, $createdBy],
        );
    }

    public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO installed_package_credentials
                (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
             VALUES (?, \'webhook\', ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$installedId, $webhookId, $label, $eventsJson, $createdBy],
        );
    }

    /** Idempotent: only the first call flips revoked_at. @return affected row count. */
    public function markRevoked(int $id): int
    {
        return $this->db->run(
            'UPDATE installed_package_credentials
             SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL',
            [$id],
        )->rowCount();
    }

    public function deleteFor(int $installedId): void
    {
        $this->db->run('DELETE FROM installed_package_credentials WHERE installed_package_id = ?', [$installedId]);
    }
}
