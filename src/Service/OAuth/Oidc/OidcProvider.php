<?php

declare(strict_types=1);

namespace App\Service\OAuth\Oidc;

use App\Core\OidcVerificationException;
use App\Repository\IdentityProviderRepository;
use App\Service\OAuth\HttpClient;
use App\Service\OAuth\NormalizedIdentity;
use App\Service\OAuth\OAuthProvider;
use App\Service\SecretVault;

/**
 * A configuration-only OIDC provider built from an identity_providers row
 * (P5-12, ADR 0004 D8): pinned-issuer discovery (row-cached, 0074 columns) →
 * issuer-pinned JwksCache → JwtVerifier (iss/aud/azp/nonce/time, RS256) →
 * ClaimMapper. No provider-specific code — the accepted A2 GitLab.com target
 * is exactly one row of this. The client secret is resolved per exchange from
 * the vault (`svcsec_*` reference), never held on the row.
 *
 * Availability posture mirrors JwksCache: a transport outage falls back to
 * the stale discovery cache when one exists; integrity violations always
 * fail closed.
 */
final class OidcProvider implements OAuthProvider
{
    public const DISCOVERY_TTL_SECONDS = 86400;
    public const SCOPES = 'openid profile email';

    /** @param array<string,mixed> $row identity_providers row */
    public function __construct(
        private array $row,
        private IdentityProviderRepository $providers,
        private OidcDiscovery $discovery,
        private JwksCache $jwks,
        private JwtVerifier $verifier,
        private ClaimMapper $mapper,
        private SecretVault $vault,
        private HttpClient $http,
    ) {
    }

    public function name(): string
    {
        return (string) $this->row['provider_key'];
    }

    public function label(): string
    {
        $label = (string) ($this->row['display_name'] ?? '');
        return $label !== '' ? $label : ucfirst($this->name());
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->row['is_enabled'] ?? false)
            && (string) ($this->row['client_id'] ?? '') !== ''
            && (string) ($this->row['client_secret_ref'] ?? '') !== '';
    }

    public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string
    {
        $doc = $this->document();
        return (string) $doc['authorization_endpoint'] . '?' . http_build_query([
            'client_id' => (string) $this->row['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): array
    {
        $doc = $this->document();
        return $this->http->postForm((string) $doc['token_endpoint'], [
            'client_id' => (string) $this->row['client_id'],
            'client_secret' => $this->vault->reveal((string) $this->row['client_secret_ref']),
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function identity(array $tokens, ?string $expectedNonce = null): NormalizedIdentity
    {
        $idToken = (string) ($tokens['id_token'] ?? '');
        if ($idToken === '') {
            throw new OidcVerificationException('id_token_missing');
        }

        $jwksUri = (string) $this->document()['jwks_uri'];
        $keys = $this->jwks->keys($this->row, $jwksUri);
        try {
            $claims = $this->verifyToken($idToken, $keys, (string) $expectedNonce);
        } catch (OidcVerificationException $e) {
            if ($e->reason !== 'unknown_kid') {
                throw $e;
            }
            // Rotation: exactly one forced refresh through the pinned URL.
            $claims = $this->verifyToken($idToken, $this->jwks->refresh($this->row, $jwksUri), (string) $expectedNonce);
        }

        return $this->mapper->map($claims, $this->row);
    }

    // ---- internals ---------------------------------------------------------

    /** @param array<string,mixed> $keys @return array<string,mixed> */
    private function verifyToken(string $idToken, array $keys, string $expectedNonce): array
    {
        return $this->verifier->verify(
            $idToken,
            $keys,
            (string) $this->row['issuer'],
            (string) $this->row['client_id'],
            $expectedNonce,
        );
    }

    /** @return array<string,mixed> the validated (or validly cached) discovery document */
    private function document(): array
    {
        $cached = json_decode((string) ($this->row['discovery_cache_json'] ?? ''), true);
        if (!is_array($cached)
            || !isset($cached['authorization_endpoint'], $cached['token_endpoint'], $cached['jwks_uri'])
        ) {
            $cached = null;
        }
        if ($cached !== null && $this->discoveryFresh()) {
            return $cached;
        }

        $discoveryUrl = (string) ($this->row['discovery_url'] ?? '');
        try {
            $doc = $this->discovery->fetch((string) $this->row['issuer'], $discoveryUrl !== '' ? $discoveryUrl : null);
        } catch (OidcVerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($cached !== null) {
                return $cached; // outage: stale beats down
            }
            throw $e;
        }
        $this->providers->cacheDiscovery((int) $this->row['id'], (string) json_encode($doc));
        return $doc;
    }

    private function discoveryFresh(): bool
    {
        $at = $this->row['discovery_cached_at'] ?? null;
        if (!is_string($at) || $at === '') {
            return false;
        }
        $ts = strtotime($at . ' UTC');
        return $ts !== false && (time() - $ts) < self::DISCOVERY_TTL_SECONDS;
    }
}
