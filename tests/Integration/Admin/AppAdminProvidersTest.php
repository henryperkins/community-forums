<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

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
 * Inc 8 (P5-12) — the operator provider console: add a generic-OIDC provider
 * (secret straight into the vault — §E sequencing: service_secrets first),
 * test its health, and enable/disable it with the TM-ID-09-clause-2 handoff
 * honoured: sole-method accounts are listed BEFORE disable ever happens.
 * Everything sits behind the dark provider_registry flag + admin role +
 * password reauth on mutations.
 */
final class AppAdminProvidersTest extends TestCase
{
    private const ISSUER = 'https://idp.test';
    private const WELL_KNOWN = 'https://idp.test/.well-known/openid-configuration';
    private const JWKS = 'https://idp.test/oauth/discovery/keys';

    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
    }

    // ---- gates ---------------------------------------------------------------

    public function test_flag_dark_hides_every_provider_admin_route(): void
    {
        (new SettingRepository($this->db))->set('features', ['provider_registry' => false]);
        $this->actingAs($this->admin);
        $this->assertStatus(404, $this->get('/admin/providers'));
        $this->assertStatus(404, $this->post('/admin/providers', []));
        $this->assertStatus(404, $this->post('/admin/providers/1/test', []));
        $this->assertStatus(404, $this->post('/admin/providers/1/enable', []));
        $this->assertStatus(404, $this->get('/admin/providers/1/disable'));
        $this->assertStatus(404, $this->post('/admin/providers/1/disable', []));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->enableFlags();
        $this->actingAs($this->makeUser(['username' => 'plainuser']));
        $this->assertStatus(403, $this->get('/admin/providers'));
        $this->assertStatus(403, $this->post('/admin/providers', []));
    }

    // ---- listing ---------------------------------------------------------------

    public function test_index_lists_builtins_and_registry_rows(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $this->createProviderRow('acme', 'Acme ID');

        $body = $this->get('/admin/providers')->body();

        self::assertStringContainsString('Acme ID', $body);
        self::assertStringContainsString('GitHub', $body, 'builtin rows stay visible (env-configured)');
        self::assertStringContainsString('Add an OIDC provider', $body);
    }

    // ---- create ---------------------------------------------------------------

    public function test_create_stores_the_secret_in_the_vault_and_lands_dark(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);

        $res = $this->post('/admin/providers', $this->createInput());

        $this->assertRedirect($res, '/admin/providers');
        $row = $this->providers()->findByKey('gitlab');
        self::assertNotNull($row);
        self::assertSame('GitLab', $row['display_name']);
        self::assertSame('https://gitlab.example', $row['issuer']);
        self::assertSame('generic_oidc', $row['type']);
        self::assertSame(0, (int) $row['is_enabled'], 'new providers land disabled');
        self::assertStringStartsWith('svcsec_', (string) $row['client_secret_ref']);
        self::assertSame('glpat-secret', $this->vault()->reveal((string) $row['client_secret_ref']));

        $log = $this->db->fetch(
            "SELECT * FROM moderation_log WHERE action = 'identity_provider_created' ORDER BY id DESC LIMIT 1",
        );
        self::assertNotNull($log, 'provider creation is audited');
    }

    public function test_create_validation_preserves_typed_input(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);

        // Reserved key: the builtins can never be shadowed.
        $res = $this->post('/admin/providers', $this->createInput(['provider_key' => 'google']));
        self::assertSame(422, $res->status());
        self::assertStringContainsString('https://gitlab.example', $res->body(), 'typed issuer survives the error (anti-draft-loss)');

        // Non-HTTPS issuer.
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['issuer' => 'http://gitlab.example']))->status());
        // Issuer with a query string.
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['issuer' => 'https://gitlab.example/?x=1']))->status());
        // Malformed key.
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['provider_key' => 'Bad Key!']))->status());
        // Invalid claim map JSON.
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['claim_map_json' => '{nope']))->status());

        // Duplicate key.
        $this->createProviderRow('dup', 'Dup');
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['provider_key' => 'dup']))->status());

        // Wrong reauth password.
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['current_password' => 'wrong']))->status());

        self::assertNull($this->providers()->findByKey('gitlab'), 'no row landed from any rejected attempt');
    }

    public function test_trailing_slash_issuer_is_stored_verbatim_and_probes_ok(): void
    {
        // Spec-legal issuers can end in '/' (e.g. Auth0 tenants) and both the
        // discovery echo and the id_token `iss` claim must byte-equal the pin,
        // so the console must never normalise the slash away.
        $this->enableFlags();
        $this->actingAs($this->admin);
        $http = new ScriptedOAuthHttpClient();
        $this->withOAuthHttp($http);
        $slashed = self::ISSUER . '/';
        $http->script(self::WELL_KNOWN, [
            'issuer' => $slashed,
            'authorization_endpoint' => 'https://idp.test/oauth/authorize',
            'token_endpoint' => 'https://idp.test/oauth/token',
            'jwks_uri' => self::JWKS,
        ]);
        $http->script(self::JWKS, ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'kid' => 'k1', 'n' => 'AQAB', 'e' => 'AQAB']]]);

        $this->assertRedirect(
            $this->post('/admin/providers', $this->createInput(['provider_key' => 'slashy', 'issuer' => $slashed])),
            '/admin/providers',
        );
        $row = $this->providers()->findByKey('slashy');
        self::assertSame($slashed, $row['issuer'], 'the issuer pin is stored exactly as published');

        $this->assertRedirect($this->post('/admin/providers/' . (int) $row['id'] . '/test', []), '/admin/providers');
        self::assertSame('ok', $this->providers()->find((int) $row['id'])['health_status'], 'a slash-carrying issuer probes healthy end to end');
    }

    public function test_oversized_issuer_and_claim_map_are_422_not_500(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $secretsBefore = (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secrets', []);

        $long = 'https://idp.test/' . str_repeat('a', 600);
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['issuer' => $long]))->status());

        $bigMap = '{"email":"' . str_repeat('x', 70000) . '"}';
        self::assertSame(422, $this->post('/admin/providers', $this->createInput(['claim_map_json' => $bigMap]))->status());

        self::assertNull($this->providers()->findByKey('gitlab'), 'no row landed');
        self::assertSame(
            $secretsBefore,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secrets', []),
            'a rejected create never stores an orphaned vault secret',
        );
    }

    public function test_create_requires_the_service_secrets_flag_first(): void
    {
        // §E hard sequencing rule 1: the vault must exist before providers.
        (new SettingRepository($this->db))->set('features', ['provider_registry' => true, 'service_secrets' => false]);
        $this->actingAs($this->admin);

        $res = $this->post('/admin/providers', $this->createInput());

        self::assertSame(422, $res->status());
        self::assertStringContainsString('service_secrets', $res->body(), 'the error names the missing flag');
        self::assertNull($this->providers()->findByKey('gitlab'));
    }

    // ---- test / health ----------------------------------------------------------

    public function test_health_probe_records_ok_and_primes_caches(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $http = new ScriptedOAuthHttpClient();
        $this->withOAuthHttp($http);
        $http->script(self::WELL_KNOWN, [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => 'https://idp.test/oauth/authorize',
            'token_endpoint' => 'https://idp.test/oauth/token',
            'jwks_uri' => self::JWKS,
        ]);
        $http->script(self::JWKS, ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'kid' => 'k1', 'n' => 'AQAB', 'e' => 'AQAB']]]);
        $id = $this->createProviderRow('probe', 'Probe IdP');

        $res = $this->post('/admin/providers/' . $id . '/test', []);

        $this->assertRedirect($res, '/admin/providers');
        $row = $this->providers()->find($id);
        self::assertSame('ok', $row['health_status']);
        self::assertNotNull($row['health_checked_at']);
        self::assertNotNull($row['discovery_cache_json'], 'a passing probe primes the discovery cache');
        self::assertNotNull($row['jwks_cache_json'], 'a passing probe primes the JWKS cache');
    }

    public function test_health_probe_records_down_on_failure(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $http = new ScriptedOAuthHttpClient();
        $this->withOAuthHttp($http);
        $http->script(self::WELL_KNOWN, new \RuntimeException('OAuth HTTP request failed: refused'));
        $id = $this->createProviderRow('downidp', 'Down IdP');

        $this->assertRedirect($this->post('/admin/providers/' . $id . '/test', []), '/admin/providers');

        self::assertSame('down', $this->providers()->find($id)['health_status']);
    }

    // ---- enable / disable ---------------------------------------------------------

    public function test_enable_requires_reauth_and_audits(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $id = $this->createProviderRow('enab', 'Enab IdP');

        self::assertSame(422, $this->post('/admin/providers/' . $id . '/enable', ['current_password' => 'wrong'])->status());
        self::assertSame(0, (int) $this->providers()->find($id)['is_enabled']);

        $res = $this->post('/admin/providers/' . $id . '/enable', ['current_password' => 'password123']);

        $this->assertRedirect($res, '/admin/providers');
        self::assertSame(1, (int) $this->providers()->find($id)['is_enabled']);
        self::assertNotNull($this->db->fetch(
            "SELECT * FROM moderation_log WHERE action = 'identity_provider_enabled' ORDER BY id DESC LIMIT 1",
        ));
    }

    public function test_builtin_rows_cannot_be_toggled_from_the_console(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $google = $this->providers()->findByKey('google');

        $res = $this->post('/admin/providers/' . (int) $google['id'] . '/enable', ['current_password' => 'password123']);

        self::assertSame(422, $res->status());
        self::assertSame(0, (int) $this->providers()->findByKey('google')['is_enabled']);
    }

    public function test_builtin_rows_cannot_be_probed_from_the_console(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        // Any network attempt would throw ('unscripted URL') and record `down`
        // — the guard must refuse before the probe ever runs.
        $this->withOAuthHttp(new ScriptedOAuthHttpClient());
        $google = $this->providers()->findByKey('google');

        $res = $this->post('/admin/providers/' . (int) $google['id'] . '/test', []);

        self::assertSame(422, $res->status());
        $after = $this->providers()->findByKey('google');
        self::assertSame($google['health_status'], $after['health_status'], 'no health write on a refused builtin probe');
        self::assertNull($after['discovery_cache_json'] ?? null, 'no cache write onto the env-managed reference row');
    }

    public function test_enable_reauth_error_renders_beside_the_row_form(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $id = $this->createProviderRow('rowerr', 'RowErr IdP');

        $res = $this->post('/admin/providers/' . $id . '/enable', ['current_password' => 'wrong']);

        self::assertSame(422, $res->status());
        $body = $res->body();
        self::assertSame(
            1,
            substr_count($body, 'Your current password is incorrect.'),
            'the reauth error renders exactly once',
        );
        self::assertLessThan(
            (int) strpos($body, 'Add an OIDC provider'),
            (int) strpos($body, 'Your current password is incorrect.'),
            'the error sits in the provider rows table (beside the submitted form), not under the add form',
        );
    }

    public function test_sole_method_count_survives_disable_and_oauth_flag_rollback(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $id = $this->createProviderRow('solo2', 'Solo2 IdP', enabled: true);
        $soleId = $this->users()->create([
            'username' => 'solo2only', 'email' => 'solo2@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        (new OAuthIdentityRepository($this->db))->create([
            'user_id' => $soleId, 'provider' => 'solo2', 'provider_user_id' => 'sub-11', 'provider_config_id' => $id,
        ]);

        // Disabling is exactly when the lockout number matters — it must not
        // drop to a reassuring 0 because the provider left the usable set.
        $this->providers()->setEnabled($id, false);
        self::assertStringContainsString(
            'data-sole-count="1"',
            $this->get('/admin/providers')->body(),
            'a disabled provider still reports the members who depend on it',
        );

        // And the console's usable-set must track enforcement (oauth master flag).
        (new SettingRepository($this->db))->set('features', ['provider_registry' => true, 'service_secrets' => true, 'oauth' => false]);
        self::assertStringContainsString(
            'data-sole-count="1"',
            $this->get('/admin/providers')->body(),
            'the count matches enforcement even with the oauth flag off',
        );
    }

    public function test_disable_confirm_lists_sole_method_accounts_before_disable(): void
    {
        $this->enableFlags();
        $this->actingAs($this->admin);
        $id = $this->createProviderRow('solo', 'Solo IdP', enabled: true);
        $disabledFallbackId = $this->createProviderRow('backup', 'Disabled Backup IdP', enabled: false);

        // An account whose only USABLE sign-in method is this provider: the
        // secondary identity is disabled, so it must not hide the lockout.
        $soleId = $this->users()->create([
            'username' => 'soloonly', 'email' => 'solo@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        (new OAuthIdentityRepository($this->db))->create([
            'user_id' => $soleId, 'provider' => 'solo', 'provider_user_id' => 'sub-9',
            'provider_config_id' => $id,
        ]);
        (new OAuthIdentityRepository($this->db))->create([
            'user_id' => $soleId, 'provider' => 'backup', 'provider_user_id' => 'sub-disabled',
            'provider_config_id' => $disabledFallbackId,
        ]);
        // …and one with a password too (must NOT be listed).
        $mixed = $this->makeUser(['username' => 'mixeduser']);
        (new OAuthIdentityRepository($this->db))->create([
            'user_id' => (int) $mixed['id'], 'provider' => 'solo', 'provider_user_id' => 'sub-10',
            'provider_config_id' => $id,
        ]);

        $confirm = $this->get('/admin/providers/' . $id . '/disable')->body();
        self::assertStringContainsString('soloonly', $confirm, 'sole-method account is surfaced before disable');
        self::assertStringNotContainsString('mixeduser', $confirm, 'password-holding accounts are not scare-listed');

        $res = $this->post('/admin/providers/' . $id . '/disable', ['current_password' => 'password123']);

        $this->assertRedirect($res, '/admin/providers');
        self::assertSame(0, (int) $this->providers()->find($id)['is_enabled']);
        self::assertNotNull(
            (new OAuthIdentityRepository($this->db))->findByProvider('solo', 'sub-9'),
            'identities are retained across disable',
        );
        self::assertNotNull($this->db->fetch(
            "SELECT * FROM moderation_log WHERE action = 'identity_provider_disabled' ORDER BY id DESC LIMIT 1",
        ));
    }

    // ---- helpers ------------------------------------------------------------------

    private function enableFlags(): void
    {
        (new SettingRepository($this->db))->set('features', ['provider_registry' => true, 'service_secrets' => true]);
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function createInput(array $overrides = []): array
    {
        return $overrides + [
            'provider_key' => 'gitlab',
            'display_name' => 'GitLab',
            'issuer' => 'https://gitlab.example',
            'client_id' => 'client-abc',
            'client_secret' => 'glpat-secret',
            'claim_map_json' => '',
            'current_password' => 'password123',
        ];
    }

    private function createProviderRow(string $key, string $name, bool $enabled = false): int
    {
        $id = $this->providers()->create([
            'provider_key' => $key,
            'display_name' => $name,
            'issuer' => self::ISSUER,
            'client_id' => 'client-1',
            'client_secret_ref' => 'svcsec_row',
        ]);
        if ($enabled) {
            $this->providers()->setEnabled($id, true);
        }
        return $id;
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
}
