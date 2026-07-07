<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Identity-provider registry rows (P5-12, tables from 0052/0074). Generic-OIDC
 * providers are configuration, not code: issuer pin, client credentials
 * (secret as an opaque `svcsec_*` reference — never plaintext), claim map, and
 * the discovery/JWKS caches. Builtin rows (google/apple/github) are inert
 * reference data for migration linkage; the live builtin flow still resolves
 * from config('oauth').
 */
final class IdentityProviderRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM identity_providers WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $providerKey): ?array
    {
        return $this->db->fetch('SELECT * FROM identity_providers WHERE provider_key = ?', [$providerKey]);
    }

    /** @return array<int,array<string,mixed>> every registry row, stable order */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM identity_providers ORDER BY type ASC, display_name ASC, id ASC');
    }

    /** @return array<int,array<string,mixed>> enabled configuration-only providers */
    public function enabledGenericOidc(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM identity_providers WHERE type = 'generic_oidc' AND is_enabled = 1 ORDER BY display_name ASC, id ASC",
        );
    }

    /**
     * @param array{provider_key:string,display_name:string,issuer:string,client_id:string,client_secret_ref:string,claim_map_json?:?string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO identity_providers
                (provider_key, display_name, protocol, type, issuer, client_id, client_secret_ref, claim_map_json, is_enabled)
             VALUES (:pkey, :name, 'oidc', 'generic_oidc', :issuer, :client_id, :secret_ref, :claim_map, 0)",
            [
                'pkey' => $data['provider_key'],
                'name' => $data['display_name'],
                'issuer' => $data['issuer'],
                'client_id' => $data['client_id'],
                'secret_ref' => $data['client_secret_ref'],
                'claim_map' => $data['claim_map_json'] ?? null,
            ],
        );
    }

    public function keyExists(string $providerKey): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM identity_providers WHERE provider_key = ? LIMIT 1',
            [$providerKey],
        ) !== false;
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->run('UPDATE identity_providers SET is_enabled = ? WHERE id = ?', [$enabled ? 1 : 0, $id]);
    }

    public function updateHealth(int $id, string $status): void
    {
        $this->db->run(
            'UPDATE identity_providers SET health_status = ?, health_checked_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $id],
        );
    }

    public function cacheDiscovery(int $id, string $json): void
    {
        $this->db->run(
            'UPDATE identity_providers SET discovery_cache_json = ?, discovery_cached_at = UTC_TIMESTAMP() WHERE id = ?',
            [$json, $id],
        );
    }

    public function cacheJwks(int $id, string $json): void
    {
        $this->db->run(
            'UPDATE identity_providers SET jwks_cache_json = ?, jwks_cached_at = UTC_TIMESTAMP() WHERE id = ?',
            [$json, $id],
        );
    }
}
