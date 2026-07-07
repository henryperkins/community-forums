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

    /** @var array<string,mixed>|null the request-lifetime resolved discovery document */
    private ?array $doc = null;

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
        $endpoint = (string) $this->document()['authorization_endpoint'];
        // RFC 6749 §3.1: the advertised endpoint may already carry a query
        // component (e.g. Azure B2C policy endpoints) — retain it, join with &.
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query([
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
            // Rotation: exactly one forced refresh through the pinned URL.
            // `unknown_kid` is the explicit signal; a signature failure can be
            // the same rotation when the IdP omits `kid` (RFC 7515 allows it)
            // or re-keys under a reused kid, so it earns the same single retry.
            if (!in_array($e->reason, ['unknown_kid', 'signature_invalid'], true)) {
                throw $e;
            }
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

    /**
     * The validated (or validly cached) discovery document, resolved at most
     * once per instance — exchange() and identity() run in the same callback
     * request and must not fetch (or UPDATE the row cache) twice.
     *
     * @return array<string,mixed>
     */
    private function document(): array
    {
        if ($this->doc !== null) {
            return $this->doc;
        }

        $cached = json_decode((string) ($this->row['discovery_cache_json'] ?? ''), true);
        if (!is_array($cached)
            || !isset($cached['authorization_endpoint'], $cached['token_endpoint'], $cached['jwks_uri'])
        ) {
            $cached = null;
        }
        if ($cached !== null && $this->discoveryFresh()) {
            return $this->doc = $cached;
        }

        $discoveryUrl = (string) ($this->row['discovery_url'] ?? '');
        try {
            $doc = $this->discovery->fetch((string) $this->row['issuer'], $discoveryUrl !== '' ? $discoveryUrl : null);
        } catch (OidcVerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($cached !== null) {
                return $this->doc = $cached; // outage: stale beats down
            }
            throw $e;
        }
        $this->providers->cacheDiscovery((int) $this->row['id'], (string) json_encode($doc));
        return $this->doc = $doc;
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
