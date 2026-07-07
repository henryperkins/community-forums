<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\IdentityProviderRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use Tests\Support\ScriptedOAuthHttpClient;
use Tests\Support\TestCase;

/**
 * Inc 8 (P5-12) — the generic-OIDC provider end-to-end through the real HTTP
 * kernel, shaped on the accepted A2 GitLab configuration (issuer-pinned
 * discovery, /oauth/* endpoints, RS256, numeric sub, verified-email claim).
 * Outbound HTTP is scripted; id_tokens are signed with an in-test RSA key.
 *
 * Threat fixtures carried here: TM-ID-01 (cross-issuer/aud/azp), TM-ID-02
 * (state replay, nonce, PKCE possession), TM-ID-03 (off-issuer JWKS refusal +
 * pinned rotation refresh), TM-ID-04 (verified-email collision requires
 * linked-login proof), plus §9 outage / disable-fallback / closed-registration
 * / sole-method arms.
 */
final class AppOidcProviderTest extends TestCase
{
    private const ISSUER = 'https://gitlab.test';
    private const AUTHORIZE = 'https://gitlab.test/oauth/authorize';
    private const TOKEN = 'https://gitlab.test/oauth/token';
    private const JWKS = 'https://gitlab.test/oauth/discovery/keys';
    private const WELL_KNOWN = 'https://gitlab.test/.well-known/openid-configuration';
    private const CLIENT = 'client-gl-1';
    private const SECRET = 'glpat-style-secret-1';

    /** @var array<string,\OpenSSLAsymmetricKey> */
    private static array $keys = [];

    private ScriptedOAuthHttpClient $http;
    private int $configId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // installed site (setup gate)
    }

    // ---- redirect leg -------------------------------------------------------

    public function test_redirect_carries_state_pkce_nonce_from_cached_discovery(): void
    {
        $this->provisionProvider();

        $q = $this->startFlow();

        self::assertSame(self::CLIENT, $q['client_id']);
        self::assertSame('code', $q['response_type']);
        self::assertSame('openid profile email', $q['scope']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertNotSame('', (string) $q['state']);
        self::assertNotSame('', (string) $q['nonce']);
        self::assertNotSame('', (string) $q['code_challenge']);
        self::assertStringEndsWith('/auth/gitlab/callback', (string) $q['redirect_uri']);
        self::assertSame([], $this->http->calls, 'cached discovery means zero network on the redirect leg');
    }

    public function test_cold_discovery_fetches_once_then_reuses_the_cache(): void
    {
        $this->provisionProvider(primeCaches: false);
        $this->http->script(self::WELL_KNOWN, $this->discoveryDoc());

        $this->startFlow();
        self::assertSame([self::WELL_KNOWN], $this->http->urls());
        self::assertNotNull($this->providers()->find($this->configId)['discovery_cache_json']);

        $this->startFlow();
        self::assertSame([self::WELL_KNOWN], $this->http->urls(), 'second flow rides the cache');
    }

    public function test_discovery_outage_fails_soft_not_500(): void
    {
        $this->provisionProvider(primeCaches: false);
        $this->http->script(self::WELL_KNOWN, new \RuntimeException('OAuth HTTP request failed: timeout'));

        $res = $this->get('/auth/gitlab/redirect');

        $this->assertRedirectContains($res, '/login');
    }

    public function test_authorize_endpoint_with_existing_query_joins_with_ampersand(): void
    {
        // RFC 6749 §3.1: the advertised endpoint may carry a query component
        // (e.g. Azure B2C policy endpoints) which the client must retain.
        $this->provisionProvider(primeCaches: false);
        $endpoint = self::AUTHORIZE . '?policy=b2c_1_signin';
        $this->providers()->cacheDiscovery($this->configId, (string) json_encode(
            $this->discoveryDoc(['authorization_endpoint' => $endpoint]),
        ));

        $res = $this->get('/auth/gitlab/redirect');

        self::assertSame(302, $res->status());
        $location = (string) $res->getHeader('Location');
        self::assertStringStartsWith($endpoint . '&client_id=', $location, 'the endpoint query survives; ours joins with &');
        self::assertSame(1, substr_count($location, '?'), 'exactly one query separator in the authorize URL');
    }

    // ---- happy path ---------------------------------------------------------

    public function test_happy_path_creates_account_with_registry_linkage_and_pkce_proof(): void
    {
        $this->provisionProvider();
        $before = $this->users()->count();

        $q = $this->startFlow();
        $res = $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $this->assertRedirect($res, '/');
        self::assertSame($before + 1, $this->users()->count());

        $identity = (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213');
        self::assertNotNull($identity);
        self::assertSame($this->configId, (int) $identity['provider_config_id'], 'new identities carry registry linkage');
        self::assertSame('ada@glab.test', $identity['email']);

        $user = $this->users()->find((int) $identity['user_id']);
        self::assertSame('ada@glab.test', $user['email'], 'verified provider email lands on the account');
        self::assertNotNull($user['email_verified_at']);

        // PKCE possession proof: the exchanged verifier hashes to the challenge.
        $exchange = $this->http->calls[0];
        self::assertSame(self::TOKEN, $exchange['url']);
        $form = $exchange['form'];
        self::assertSame('authorization_code', $form['grant_type']);
        self::assertSame('code-1', $form['code']);
        self::assertSame(self::SECRET, $form['client_secret'], 'client secret resolved from the vault');
        self::assertSame(
            $q['code_challenge'],
            rtrim(strtr(base64_encode(hash('sha256', (string) $form['code_verifier'], true)), '+/', '-_'), '='),
        );
    }

    public function test_returning_login_reuses_the_account(): void
    {
        $this->provisionProvider();
        $q1 = $this->startFlow();
        $this->completeCallback($q1, ['id_token' => $this->idToken(['nonce' => $q1['nonce']])]);
        $first = (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213');
        $this->logoutClient();

        $q2 = $this->startFlow();
        $res = $this->completeCallback($q2, ['id_token' => $this->idToken(['nonce' => $q2['nonce']])]);

        $this->assertRedirect($res, '/');
        $again = (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213');
        self::assertSame((int) $first['user_id'], (int) $again['user_id']);
    }

    // ---- TM-ID-01: issuer / audience / azp ----------------------------------

    public function test_cross_issuer_token_is_rejected(): void
    {
        $this->assertCallbackRejected(fn (array $q) => $this->idToken(['nonce' => $q['nonce'], 'iss' => 'https://evil.test']));
    }

    public function test_wrong_audience_token_is_rejected(): void
    {
        $this->assertCallbackRejected(fn (array $q) => $this->idToken(['nonce' => $q['nonce'], 'aud' => 'other-client']));
    }

    public function test_wrong_azp_token_is_rejected(): void
    {
        $this->assertCallbackRejected(fn (array $q) => $this->idToken([
            'nonce' => $q['nonce'], 'aud' => [self::CLIENT, 'other-client'], 'azp' => 'other-client',
        ]));
    }

    // ---- TM-ID-02: state / nonce / PKCE -------------------------------------

    public function test_missing_nonce_claim_is_rejected(): void
    {
        $this->assertCallbackRejected(function (array $q): string {
            $claims = $this->claims(['nonce' => $q['nonce']]);
            unset($claims['nonce']);
            return $this->signToken($claims);
        });
    }

    public function test_wrong_nonce_is_rejected(): void
    {
        $this->assertCallbackRejected(fn (array $q) => $this->idToken(['nonce' => 'stolen-nonce']));
    }

    public function test_state_mismatch_is_rejected_before_any_exchange(): void
    {
        $this->provisionProvider();
        $before = $this->users()->count();
        $this->startFlow();

        $res = $this->get('/auth/gitlab/callback', ['code' => 'code-1', 'state' => 'forged-state']);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count());
        self::assertSame([], $this->http->calls, 'no token exchange on a state mismatch');
    }

    public function test_replayed_callback_is_rejected_after_completion(): void
    {
        $this->provisionProvider();
        $q = $this->startFlow();
        $token = ['id_token' => $this->idToken(['nonce' => $q['nonce']])];
        $this->assertRedirect($this->completeCallback($q, $token), '/');
        $countAfterFirst = $this->users()->count();
        $this->logoutClient(); // fresh browser; attacker replays the captured URL

        $replay = $this->get('/auth/gitlab/callback', ['code' => 'code-1', 'state' => $q['state']]);

        $this->assertRedirectContains($replay, '/login');
        self::assertSame($countAfterFirst, $this->users()->count(), 'replay must not mint anything');
    }

    // ---- TM-ID-03: JWKS pinning + rotation ----------------------------------

    public function test_rotated_kid_triggers_one_pinned_refresh_and_succeeds(): void
    {
        $this->provisionProvider(); // cache holds kid-1
        $this->http->script(self::JWKS, $this->jwksDoc('kid-1', 'kid-2'));

        $q = $this->startFlow();
        $res = $this->completeCallback($q, [
            'id_token' => $this->idToken(['nonce' => $q['nonce']], kid: 'kid-2'),
        ]);

        $this->assertRedirect($res, '/');
        $jwksFetches = array_values(array_filter($this->http->urls(), fn (string $u) => $u === self::JWKS));
        self::assertCount(1, $jwksFetches, 'exactly one forced refresh, from the pinned URL');
    }

    public function test_kidless_token_rotation_refreshes_once_and_succeeds(): void
    {
        // RFC 7515 makes `kid` optional: a single-key IdP that omits it still
        // rotates. The stale cached key fails the signature, which must earn
        // the same single pinned refresh that unknown_kid gets.
        $this->provisionProvider(); // cache holds kid-1's key
        $this->http->script(self::JWKS, $this->jwksDoc('kid-2'));

        $q = $this->startFlow();
        $res = $this->completeCallback($q, [
            'id_token' => $this->signToken($this->claims(['nonce' => $q['nonce']]), kid: 'kid-2', omitKid: true),
        ]);

        $this->assertRedirect($res, '/');
        $jwksFetches = array_values(array_filter($this->http->urls(), fn (string $u) => $u === self::JWKS));
        self::assertCount(1, $jwksFetches, 'exactly one forced refresh answers a kid-less rotation');
    }

    public function test_stale_discovery_is_resolved_once_per_callback(): void
    {
        $this->provisionProvider();
        $q = $this->startFlow(); // rides the primed cache — zero fetches
        // Age the row cache so the CALLBACK request sees it expired.
        $this->db->run(
            'UPDATE identity_providers SET discovery_cached_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE id = ?',
            [$this->configId],
        );
        $this->http->script(self::WELL_KNOWN, $this->discoveryDoc());

        $res = $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $this->assertRedirect($res, '/');
        $discoveryFetches = array_values(array_filter($this->http->urls(), fn (string $u) => $u === self::WELL_KNOWN));
        self::assertCount(1, $discoveryFetches, 'exchange() and identity() share one resolved document per callback');
    }

    public function test_tampered_cache_cannot_move_jwks_off_issuer(): void
    {
        $this->provisionProvider();
        // Poison the cached discovery document (defense-in-depth: even a bad
        // cache row must not move key fetches off-issuer).
        $this->providers()->cacheDiscovery($this->configId, (string) json_encode(
            $this->discoveryDoc(['jwks_uri' => 'https://attacker.test/keys']),
        ));
        $this->providers()->cacheJwks($this->configId, '');
        $before = $this->users()->count();

        $q = $this->startFlow();
        $res = $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count());
        foreach ($this->http->urls() as $url) {
            self::assertStringNotContainsString('attacker.test', $url, 'no fetch may leave the pinned issuer');
        }
    }

    // ---- TM-ID-04: verified-email collision ----------------------------------

    public function test_verified_email_collision_requires_linked_login_proof(): void
    {
        $this->provisionProvider();
        $local = $this->makeUser(['username' => 'collider', 'email' => 'ada@glab.test']);
        $before = $this->users()->count();

        // Signed out: a verified-email match must NOT log in or merge.
        $q = $this->startFlow();
        $res = $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count(), 'no account created on collision');
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213'));
        self::assertSame(0, (new OAuthIdentityRepository($this->db))->countForUser((int) $local['id']), 'local account not silently linked');

        // The proof: log in AS the local owner, then link explicitly.
        $this->actingAs($local);
        $q2 = $this->startFlow();
        $linked = $this->completeCallback($q2, ['id_token' => $this->idToken(['nonce' => $q2['nonce']])]);

        $this->assertRedirect($linked, '/settings/connections');
        self::assertSame(1, (new OAuthIdentityRepository($this->db))->countForUser((int) $local['id']));
    }

    // ---- §9 outage / disable / registration / sole-method --------------------

    public function test_token_endpoint_outage_fails_clean(): void
    {
        $this->provisionProvider();
        $before = $this->users()->count();
        $q = $this->startFlow();

        $this->http->script(self::TOKEN, new \RuntimeException('OAuth HTTP request failed: timeout'));
        $res = $this->get('/auth/gitlab/callback', ['code' => 'code-1', 'state' => $q['state']]);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count());
    }

    public function test_disable_retains_identities_and_removes_the_surface(): void
    {
        $this->provisionProvider();
        $q = $this->startFlow();
        $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);
        $this->logoutClient();

        self::assertStringContainsString('/auth/gitlab/redirect', $this->get('/login')->body(), 'enabled provider is offered');

        $this->providers()->setEnabled($this->configId, false);

        $this->assertStatus(404, $this->get('/auth/gitlab/redirect'));
        $this->assertStatus(404, $this->get('/auth/gitlab/callback', ['code' => 'x', 'state' => 'y']));
        self::assertStringNotContainsString('/auth/gitlab/redirect', $this->get('/login')->body(), 'disabled provider disappears from sign-in');
        self::assertNotNull(
            (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213'),
            'identities are retained across disable',
        );
    }

    public function test_disabled_provider_identity_stays_visible_and_unlinkable(): void
    {
        $this->provisionProvider();
        $q = $this->startFlow();
        $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]); // signed in as the new member

        $this->providers()->setEnabled($this->configId, false);

        // Identities are retained by design; the member must still see the
        // linkage and reach Disconnect even though the provider is gone from
        // sign-in.
        $body = $this->get('/settings/connections')->body();
        self::assertStringContainsString('GitLab', $body, 'the retained identity keeps its operator label');
        self::assertStringContainsString('name="provider" value="gitlab"', $body, 'the Disconnect form still targets it');

        // With a password in place, disconnecting the retained identity works.
        $identity = (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213');
        $this->db->run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash('pw-123456', PASSWORD_DEFAULT), (int) $identity['user_id']],
        );
        $res = $this->post('/settings/connections/unlink', ['provider' => 'gitlab']);
        $this->assertRedirect($res, '/settings/connections');
        self::assertStringContainsString('Disconnected GitLab.', $this->get('/settings/connections')->body(), 'the flash uses the label, not the slug');
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213'));
    }

    public function test_dark_provider_registry_flag_hides_even_enabled_rows(): void
    {
        $this->provisionProvider();
        (new SettingRepository($this->db))->set('features', ['service_secrets' => true, 'provider_registry' => false]);

        $this->assertStatus(404, $this->get('/auth/gitlab/redirect'));
        self::assertStringNotContainsString('/auth/gitlab/redirect', $this->get('/login')->body());
    }

    public function test_closed_registration_blocks_a_new_oidc_signup(): void
    {
        $this->provisionProvider();
        (new SettingRepository($this->db))->set('registration_mode', 'closed');
        $before = $this->users()->count();

        $q = $this->startFlow();
        $res = $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count());
    }

    public function test_generic_provider_counts_toward_sole_method_protection(): void
    {
        $this->provisionProvider();
        $q = $this->startFlow();
        $this->completeCallback($q, ['id_token' => $this->idToken(['nonce' => $q['nonce']])]);

        $identity = (new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213');
        $sole = (new OAuthIdentityRepository($this->db))->soleMethodAccounts('gitlab');
        self::assertContains((int) $identity['user_id'], array_map(fn (array $r) => (int) $r['id'], $sole));

        // Unlinking the only method is refused (still signed in from the flow).
        $res = $this->post('/settings/connections/unlink', ['provider' => 'gitlab']);
        $this->assertRedirect($res, '/settings/connections');
        self::assertNotNull((new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213'));
    }

    // ---- helpers -------------------------------------------------------------

    private function provisionProvider(bool $primeCaches = true): void
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => true, 'provider_registry' => true]);
        $this->http = new ScriptedOAuthHttpClient();
        $this->withOAuthHttp($this->http);

        $ref = $this->vault()->store('identity_provider', null, 'gitlab client secret', self::SECRET);
        $repo = $this->providers();
        $this->configId = $repo->create([
            'provider_key' => 'gitlab',
            'display_name' => 'GitLab',
            'issuer' => self::ISSUER,
            'client_id' => self::CLIENT,
            'client_secret_ref' => $ref,
        ]);
        $repo->setEnabled($this->configId, true);

        if ($primeCaches) {
            $repo->cacheDiscovery($this->configId, (string) json_encode($this->discoveryDoc()));
            $repo->cacheJwks($this->configId, (string) json_encode($this->jwksDoc('kid-1')));
        }
    }

    /** @return array<string,string> authorize-URL query params */
    private function startFlow(): array
    {
        $res = $this->get('/auth/gitlab/redirect');
        self::assertSame(302, $res->status(), 'redirect leg should 302 to the provider');
        $location = (string) $res->getHeader('Location');
        self::assertStringStartsWith(self::AUTHORIZE . '?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        /** @var array<string,string> $q */
        return $q;
    }

    /** @param array<string,string> $q @param array<string,mixed> $tokenResponse */
    private function completeCallback(array $q, array $tokenResponse): \App\Core\Response
    {
        $this->http->replace(self::TOKEN, $tokenResponse);
        return $this->get('/auth/gitlab/callback', ['code' => 'code-1', 'state' => (string) $q['state']]);
    }

    /** @param callable(array<string,string>):string $tokenFor */
    private function assertCallbackRejected(callable $tokenFor): void
    {
        $this->provisionProvider();
        $before = $this->users()->count();

        $q = $this->startFlow();
        $res = $this->completeCallback($q, ['id_token' => $tokenFor($q)]);

        $this->assertRedirectContains($res, '/login');
        self::assertSame($before, $this->users()->count(), 'a rejected token must not mint an account');
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('gitlab', '4213'));
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function claims(array $overrides = []): array
    {
        return $overrides + [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT,
            'sub' => '4213',
            'iat' => time() - 30,
            'exp' => time() + 600,
            'email' => 'ada@glab.test',
            'email_verified' => true,
            'name' => 'Ada Lovelace',
            'preferred_username' => 'ada',
            'picture' => 'https://gitlab.test/avatar/ada.png',
        ];
    }

    /** @param array<string,mixed> $overrides */
    private function idToken(array $overrides = [], string $kid = 'kid-1'): string
    {
        return $this->signToken($this->claims($overrides), $kid);
    }

    /** @param array<string,mixed> $claims $omitKid signs with $kid's key but leaves the header kid-less (RFC 7515 optional) */
    private function signToken(array $claims, string $kid = 'kid-1', bool $omitKid = false): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if (!$omitKid) {
            $header['kid'] = $kid;
        }
        $h = self::b64u((string) json_encode($header));
        $p = self::b64u((string) json_encode($claims));
        openssl_sign($h . '.' . $p, $sig, self::key($kid), OPENSSL_ALGO_SHA256);
        return $h . '.' . $p . '.' . self::b64u($sig);
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function discoveryDoc(array $overrides = []): array
    {
        return $overrides + [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::AUTHORIZE,
            'token_endpoint' => self::TOKEN,
            'jwks_uri' => self::JWKS,
        ];
    }

    /** @return array<string,mixed> */
    private function jwksDoc(string ...$kids): array
    {
        $keys = [];
        foreach ($kids as $kid) {
            $details = openssl_pkey_get_details(self::key($kid));
            self::assertIsArray($details);
            $keys[] = [
                'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid,
                'n' => self::b64u((string) $details['rsa']['n']),
                'e' => self::b64u((string) $details['rsa']['e']),
            ];
        }
        return ['keys' => $keys];
    }

    private function providers(): IdentityProviderRepository
    {
        return new IdentityProviderRepository($this->db);
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox((string) $this->config->get('app.key', '')),
            new ModerationLogRepository($this->db),
            new \App\Core\FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    /** @return \OpenSSLAsymmetricKey */
    private static function key(string $kid)
    {
        if (!isset(self::$keys[$kid])) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false) {
                self::fail('openssl_pkey_new failed');
            }
            self::$keys[$kid] = $key;
        }
        return self::$keys[$kid];
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
