<?php

declare(strict_types=1);

namespace App\Service\OAuth\Oidc;

use App\Core\OidcVerificationException;
use App\Repository\IdentityProviderRepository;
use App\Service\OAuth\HttpClient;

/**
 * Issuer-pinned JWKS cache (P5-12, TM-ID-03). Keys are fetched only from a
 * URL that is same-origin with the provider's pinned HTTPS issuer — no
 * discovery document, cached value, or caller can move a key fetch
 * off-issuer. Documents are cached on the provider row with a TTL; a
 * transport outage falls back to the stale cache for keys() (existing
 * sign-ins keep verifying) but never for refresh() — an unknown kid must be
 * answered by the live issuer or not at all. Integrity violations (malformed
 * documents) always fail closed.
 */
final class JwksCache
{
    public const TTL_SECONDS = 86400;

    public function __construct(
        private IdentityProviderRepository $providers,
        private HttpClient $http,
    ) {
    }

    /**
     * @param array<string,mixed> $row identity_providers row
     * @return array<string,mixed> decoded JWKS document
     */
    public function keys(array $row, string $jwksUri, ?int $now = null): array
    {
        $this->assertTrustedUri((string) ($row['issuer'] ?? ''), $jwksUri);
        $now ??= time();

        $cached = $this->cached($row);
        if ($cached !== null && $this->freshAt($row, $now)) {
            return $cached;
        }

        try {
            return $this->fetchAndPersist($row, $jwksUri);
        } catch (OidcVerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($cached !== null) {
                return $cached;
            }
            throw $e;
        }
    }

    /**
     * Forced refetch — the single unknown-kid rotation retry.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function refresh(array $row, string $jwksUri): array
    {
        $this->assertTrustedUri((string) ($row['issuer'] ?? ''), $jwksUri);
        return $this->fetchAndPersist($row, $jwksUri);
    }

    // ---- internals ---------------------------------------------------------

    private function assertTrustedUri(string $issuer, string $jwksUri): void
    {
        if ($issuer === '' || !OidcDiscovery::sameOrigin($issuer, $jwksUri)) {
            throw new OidcVerificationException('jwks_uri_untrusted');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function fetchAndPersist(array $row, string $jwksUri): array
    {
        $doc = $this->http->getJson($jwksUri);
        if (!isset($doc['keys']) || !is_array($doc['keys'])) {
            throw new OidcVerificationException('jwks_invalid');
        }
        $this->providers->cacheJwks((int) $row['id'], (string) json_encode($doc));
        return $doc;
    }

    /** @param array<string,mixed> $row @return array<string,mixed>|null */
    private function cached(array $row): ?array
    {
        $decoded = json_decode((string) ($row['jwks_cache_json'] ?? ''), true);
        return is_array($decoded) && isset($decoded['keys']) && is_array($decoded['keys']) ? $decoded : null;
    }

    /** @param array<string,mixed> $row */
    private function freshAt(array $row, int $now): bool
    {
        $at = $row['jwks_cached_at'] ?? null;
        if (!is_string($at) || $at === '') {
            return false;
        }
        $ts = strtotime($at . ' UTC');
        return $ts !== false && ($now - $ts) < self::TTL_SECONDS;
    }
}
