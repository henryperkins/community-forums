<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\OidcVerificationException;
use App\Repository\IdentityProviderRepository;
use App\Service\OAuth\Oidc\JwksCache;
use Tests\Support\ScriptedOAuthHttpClient;
use Tests\Support\TestCase;

/**
 * Inc 8 (P5-12) — the issuer-pinned JWKS cache (TM-ID-03). Keys are fetched
 * only from a same-origin-with-issuer HTTPS URL, cached on the provider row
 * with a TTL, force-refreshed exactly when an unknown kid demands it, and an
 * outage falls back to the stale cache for keys() but never for refresh().
 */
final class JwksCacheTest extends TestCase
{
    private const ISSUER = 'https://idp.test';
    private const JWKS_URI = 'https://idp.test/oauth/discovery/keys';

    public function test_cold_fetch_persists_the_cache(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, $this->jwks('kid-1'));

        $keys = $cache->keys($row, self::JWKS_URI);

        self::assertSame('kid-1', $keys['keys'][0]['kid']);
        self::assertCount(1, $http->calls);
        $fresh = $this->providers()->find((int) $row['id']);
        self::assertNotNull($fresh['jwks_cache_json']);
        self::assertNotNull($fresh['jwks_cached_at']);
    }

    public function test_fresh_cache_short_circuits_the_network(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, $this->jwks('kid-1'));
        $cache->keys($row, self::JWKS_URI);

        $row = $this->providers()->find((int) $row['id']);
        $keys = $cache->keys($row, self::JWKS_URI);

        self::assertSame('kid-1', $keys['keys'][0]['kid']);
        self::assertCount(1, $http->calls, 'a fresh cache must not refetch');
    }

    public function test_stale_cache_refetches(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, $this->jwks('kid-1'), $this->jwks('kid-2'));
        $cache->keys($row, self::JWKS_URI);
        $this->ageJwksCache((int) $row['id'], JwksCache::TTL_SECONDS + 60);

        $keys = $cache->keys($this->providers()->find((int) $row['id']), self::JWKS_URI);

        self::assertSame('kid-2', $keys['keys'][0]['kid']);
        self::assertCount(2, $http->calls);
    }

    public function test_refresh_forces_a_refetch_even_when_fresh(): void
    {
        // The rotation arm: an unknown kid triggers exactly one forced refresh
        // through the same pinned URL, and the rotated key then verifies.
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, $this->jwks('kid-1'), $this->jwks('kid-1', 'kid-2'));
        $cache->keys($row, self::JWKS_URI);

        $keys = $cache->refresh($this->providers()->find((int) $row['id']), self::JWKS_URI);

        self::assertCount(2, $http->calls);
        self::assertSame(['kid-1', 'kid-2'], array_column($keys['keys'], 'kid'));
        $persisted = json_decode((string) $this->providers()->find((int) $row['id'])['jwks_cache_json'], true);
        self::assertSame(['kid-1', 'kid-2'], array_column($persisted['keys'], 'kid'), 'refresh persists');
    }

    public function test_off_issuer_jwks_uri_is_refused_without_any_fetch(): void
    {
        [$row, $http, $cache] = $this->build();

        try {
            $cache->keys($row, 'https://attacker.test/keys');
            self::fail('Expected jwks_uri_untrusted.');
        } catch (OidcVerificationException $e) {
            self::assertSame('jwks_uri_untrusted', $e->reason);
        }
        self::assertSame([], $http->calls, 'no network I/O to an off-issuer URL');
        self::assertNull($this->providers()->find((int) $row['id'])['jwks_cache_json'], 'cache not poisoned');

        $this->expectExceptionObject(new OidcVerificationException('jwks_uri_untrusted'));
        $cache->refresh($row, 'http://idp.test/keys'); // https required too
    }

    public function test_malformed_jwks_document_is_refused_and_not_cached(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, ['unexpected' => 'shape']);

        try {
            $cache->keys($row, self::JWKS_URI);
            self::fail('Expected jwks_invalid.');
        } catch (OidcVerificationException $e) {
            self::assertSame('jwks_invalid', $e->reason);
        }
        self::assertNull($this->providers()->find((int) $row['id'])['jwks_cache_json']);
    }

    public function test_outage_falls_back_to_a_stale_cache_for_keys_but_not_refresh(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, $this->jwks('kid-1'), new \RuntimeException('OAuth HTTP request failed: timeout'));
        $cache->keys($row, self::JWKS_URI);
        $this->ageJwksCache((int) $row['id'], JwksCache::TTL_SECONDS + 60);
        $row = $this->providers()->find((int) $row['id']);

        // keys(): stale beats down — existing sign-ins keep verifying.
        $keys = $cache->keys($row, self::JWKS_URI);
        self::assertSame('kid-1', $keys['keys'][0]['kid']);

        // refresh(): an unknown kid cannot be answered from a stale cache.
        $this->expectException(\RuntimeException::class);
        $cache->refresh($row, self::JWKS_URI);
    }

    public function test_outage_with_no_cache_at_all_bubbles(): void
    {
        [$row, $http, $cache] = $this->build();
        $http->script(self::JWKS_URI, new \RuntimeException('OAuth HTTP request failed: timeout'));

        $this->expectException(\RuntimeException::class);
        $cache->keys($row, self::JWKS_URI);
    }

    // ---- helpers ------------------------------------------------------------

    /** @return array{0:array<string,mixed>,1:ScriptedOAuthHttpClient,2:JwksCache} */
    private function build(): array
    {
        $id = $this->providers()->create([
            'provider_key' => 'oidc-jwks-test',
            'display_name' => 'JWKS Test IdP',
            'issuer' => self::ISSUER,
            'client_id' => 'client-1',
            'client_secret_ref' => 'svcsec_test',
        ]);
        $http = new ScriptedOAuthHttpClient();
        return [$this->providers()->find($id), $http, new JwksCache($this->providers(), $http)];
    }

    private function providers(): IdentityProviderRepository
    {
        return new IdentityProviderRepository($this->db);
    }

    private function ageJwksCache(int $id, int $seconds): void
    {
        $this->db->run(
            'UPDATE identity_providers SET jwks_cached_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $seconds . ' SECOND) WHERE id = ?',
            [$id],
        );
    }

    /** @return array<string,mixed> */
    private function jwks(string ...$kids): array
    {
        return ['keys' => array_map(
            static fn (string $kid): array => ['kty' => 'RSA', 'use' => 'sig', 'kid' => $kid, 'n' => 'AQAB', 'e' => 'AQAB'],
            $kids,
        )];
    }
}
