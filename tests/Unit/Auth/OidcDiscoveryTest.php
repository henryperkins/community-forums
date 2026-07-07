<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Core\OidcVerificationException;
use App\Service\OAuth\Oidc\OidcDiscovery;
use PHPUnit\Framework\TestCase;
use Tests\Support\ScriptedOAuthHttpClient;

/**
 * Inc 8 (P5-12) — discovery-document fetch + validation. The pinned issuer is
 * the only trust anchor: the document must assert the same issuer, and the
 * JWKS URI must be same-origin with it (TM-ID-03 "JWKS fetch from off-issuer
 * URL refused"). Endpoints must be HTTPS.
 */
final class OidcDiscoveryTest extends TestCase
{
    private const ISSUER = 'https://idp.test';
    private const WELL_KNOWN = 'https://idp.test/.well-known/openid-configuration';

    public function test_fetches_the_well_known_document_derived_from_the_issuer(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, $this->document());

        $doc = (new OidcDiscovery($http))->fetch(self::ISSUER);

        self::assertSame([self::WELL_KNOWN], $http->urls());
        self::assertSame(self::ISSUER, $doc['issuer']);
        self::assertSame('https://idp.test/oauth/authorize', $doc['authorization_endpoint']);
        self::assertSame('https://idp.test/oauth/token', $doc['token_endpoint']);
        self::assertSame('https://idp.test/oauth/discovery/keys', $doc['jwks_uri']);
    }

    public function test_gitlab_shaped_document_passes(): void
    {
        // The accepted A2 configuration (docs/phase5/first-oidc-provider.md).
        $http = new ScriptedOAuthHttpClient();
        $http->script('https://gitlab.com/.well-known/openid-configuration', [
            'issuer' => 'https://gitlab.com',
            'authorization_endpoint' => 'https://gitlab.com/oauth/authorize',
            'token_endpoint' => 'https://gitlab.com/oauth/token',
            'jwks_uri' => 'https://gitlab.com/oauth/discovery/keys',
            'userinfo_endpoint' => 'https://gitlab.com/oauth/userinfo',
        ]);

        $doc = (new OidcDiscovery($http))->fetch('https://gitlab.com');
        self::assertSame('https://gitlab.com/oauth/discovery/keys', $doc['jwks_uri']);
    }

    public function test_document_asserting_a_different_issuer_is_rejected(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, $this->document(['issuer' => 'https://evil.test']));

        $this->expectExceptionObject(new OidcVerificationException('issuer_mismatch'));
        (new OidcDiscovery($http))->fetch(self::ISSUER);
    }

    public function test_off_issuer_jwks_uri_is_refused(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, $this->document(['jwks_uri' => 'https://attacker.test/keys']));

        $this->expectExceptionObject(new OidcVerificationException('jwks_uri_untrusted'));
        (new OidcDiscovery($http))->fetch(self::ISSUER);
    }

    public function test_jwks_uri_on_a_different_port_is_refused(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, $this->document(['jwks_uri' => 'https://idp.test:8443/keys']));

        $this->expectExceptionObject(new OidcVerificationException('jwks_uri_untrusted'));
        (new OidcDiscovery($http))->fetch(self::ISSUER);
    }

    public function test_non_https_issuer_is_rejected_before_any_fetch(): void
    {
        $http = new ScriptedOAuthHttpClient();

        try {
            (new OidcDiscovery($http))->fetch('http://idp.test');
            self::fail('Expected issuer_invalid.');
        } catch (OidcVerificationException $e) {
            self::assertSame('issuer_invalid', $e->reason);
        }
        self::assertSame([], $http->calls, 'no network I/O for an invalid issuer');
    }

    public function test_issuer_with_query_or_fragment_is_rejected(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $this->expectExceptionObject(new OidcVerificationException('issuer_invalid'));
        (new OidcDiscovery($http))->fetch('https://idp.test/?tenant=x');
    }

    public function test_explicit_discovery_url_must_be_same_origin_with_the_issuer(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $this->expectExceptionObject(new OidcVerificationException('discovery_url_invalid'));
        (new OidcDiscovery($http))->fetch(self::ISSUER, 'https://other.test/.well-known/openid-configuration');
    }

    public function test_same_origin_explicit_discovery_url_is_used(): void
    {
        $url = 'https://idp.test/tenant-a/.well-known/openid-configuration';
        $http = new ScriptedOAuthHttpClient();
        $http->script($url, $this->document());

        (new OidcDiscovery($http))->fetch(self::ISSUER, $url);
        self::assertSame([$url], $http->urls());
    }

    public function test_missing_or_non_https_endpoints_are_rejected(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, $this->document(['token_endpoint' => 'http://idp.test/oauth/token']));
        try {
            (new OidcDiscovery($http))->fetch(self::ISSUER);
            self::fail('Expected discovery_document_invalid.');
        } catch (OidcVerificationException $e) {
            self::assertSame('discovery_document_invalid', $e->reason);
        }

        $http2 = new ScriptedOAuthHttpClient();
        $doc = $this->document();
        unset($doc['authorization_endpoint']);
        $http2->script(self::WELL_KNOWN, $doc);
        $this->expectExceptionObject(new OidcVerificationException('discovery_document_invalid'));
        (new OidcDiscovery($http2))->fetch(self::ISSUER);
    }

    public function test_network_failure_bubbles_for_the_caller_to_handle(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $http->script(self::WELL_KNOWN, new \RuntimeException('OAuth HTTP request failed: timeout'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timeout');
        (new OidcDiscovery($http))->fetch(self::ISSUER);
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function document(array $overrides = []): array
    {
        return $overrides + [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => 'https://idp.test/oauth/authorize',
            'token_endpoint' => 'https://idp.test/oauth/token',
            'jwks_uri' => 'https://idp.test/oauth/discovery/keys',
        ];
    }
}
