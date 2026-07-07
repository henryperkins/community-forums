<?php

declare(strict_types=1);

namespace App\Service\OAuth\Oidc;

use App\Core\OidcVerificationException;
use App\Service\OAuth\HttpClient;

/**
 * OIDC discovery-document fetch + validation (P5-12, ADR 0004 D8). The pinned
 * issuer is the only trust anchor: HTTPS-only, the document must assert the
 * identical issuer, endpoints must be HTTPS, and the JWKS URI must be
 * same-origin with the issuer — a discovery document can never redirect key
 * fetches off-issuer (TM-ID-03). Pure of the DB; callers own caching.
 */
final class OidcDiscovery
{
    public function __construct(private HttpClient $http)
    {
    }

    /**
     * @return array<string,mixed> the validated discovery document
     * @throws OidcVerificationException
     */
    public function fetch(string $issuer, ?string $discoveryUrl = null): array
    {
        if (!self::isHttpsUrl($issuer) || !self::isCleanIssuer($issuer)) {
            throw new OidcVerificationException('issuer_invalid');
        }

        $url = $discoveryUrl ?? rtrim($issuer, '/') . '/.well-known/openid-configuration';
        if ($discoveryUrl !== null && (!self::isHttpsUrl($discoveryUrl) || !self::sameOrigin($issuer, $discoveryUrl))) {
            throw new OidcVerificationException('discovery_url_invalid');
        }

        $doc = $this->http->getJson($url);

        if ((string) ($doc['issuer'] ?? '') !== $issuer) {
            throw new OidcVerificationException('issuer_mismatch');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
            $value = $doc[$field] ?? null;
            if (!is_string($value) || !self::isHttpsUrl($value)) {
                throw new OidcVerificationException('discovery_document_invalid');
            }
        }
        if (!self::sameOrigin($issuer, (string) $doc['jwks_uri'])) {
            throw new OidcVerificationException('jwks_uri_untrusted');
        }

        return $doc;
    }

    /** scheme + host + effective port must match — the TM-ID-03 pin. */
    public static function sameOrigin(string $a, string $b): bool
    {
        $pa = parse_url($a);
        $pb = parse_url($b);
        if (!is_array($pa) || !is_array($pb)) {
            return false;
        }
        $origin = static fn (array $p): string => strtolower((string) ($p['scheme'] ?? ''))
            . '://' . strtolower((string) ($p['host'] ?? ''))
            . ':' . (string) ($p['port'] ?? 443);
        return $origin($pa) === $origin($pb);
    }

    private static function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && (string) ($parts['host'] ?? '') !== '';
    }

    private static function isCleanIssuer(string $issuer): bool
    {
        $parts = parse_url($issuer);
        return is_array($parts) && !isset($parts['query']) && !isset($parts['fragment']);
    }
}
