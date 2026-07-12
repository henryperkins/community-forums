<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Response;
use App\Repository\SettingRepository;
use App\Security\Totp;
use App\Security\WebAuthn\RelyingParty;
use App\Support\Base64Url;
use Tests\Support\Phase5\WebAuthnHarness;
use Tests\Support\TestCase;

final class AppPasskeyLoginTest extends TestCase
{
    private WebAuthnHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $rp = new RelyingParty((string) $this->config->get('app.url', 'http://localhost:8000'), null, 'testing');
        $this->harness = new WebAuthnHarness($rp->rpId(), $rp->origin());
    }

    /** @return array<string,mixed> */
    private function enroll(array $user, string $password = 'password123'): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
        ]));
        return $cred;
    }

    /** @return array{challenge:string,options:array<string,mixed>} */
    private function loginChallenge(string $email): array
    {
        $this->get('/login');
        $res = $this->post('/login/passkey/challenge', ['email' => $email]);
        $this->assertStatus(200, $res);
        $options = json_decode($res->body(), true)['options'];
        return ['challenge' => (string) Base64Url::decode($options['challenge']), 'options' => $options];
    }

    public function test_login_affordance_is_hidden_when_the_relying_party_is_unusable(): void
    {
        // Default-on passkeys must not offer a sign-in button that is guaranteed
        // to 422 (production behind TLS-at-the-edge with a stale http:// APP_URL):
        // the affordance renders only when the ceremony policy is satisfiable.
        $items = $this->config->all();
        $items['app']['url'] = 'http://forum.example.com';
        $items['app']['env'] = 'production';
        $this->app = new \App\Core\App(new \App\Core\Config($items), $this->db, $this->rateLimiter);

        $this->logoutClient();
        $body = $this->get('/login')->body();
        self::assertStringNotContainsString('data-passkey-signin', $body, 'unusable RP must hide the passkey affordance');
        self::assertStringContainsString('name="email"', $body, 'password login stays the baseline');
    }

    public function test_registered_credential_signs_in_the_right_account(): void
    {
        $user = $this->makeUser(['username' => 'pk_login']);
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        self::assertContains(Base64Url::encode($cred['credentialId']), array_column($c['options']['allowCredentials'], 'id'));
        self::assertCount(8, $c['options']['allowCredentials'], 'real and decoy responses use the same fixed slot count');

        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
            'next' => '/settings/security',
        ]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('/settings/security', $json['redirect']);
        $this->assertStatus(200, $this->get('/settings/security'));
    }

    public function test_registered_credential_defaults_to_the_community_inbox_without_next(): void
    {
        $user = $this->makeUser(['username' => 'pk_inbox']);
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $challenge = $this->loginChallenge((string) $user['email']);
        $response = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $challenge['challenge'], 1),
        ]);

        $this->assertStatus(200, $response);
        self::assertSame('/inbox', json_decode($response->body(), true)['redirect']);
    }

    public function test_unknown_or_passkeyless_email_gets_fixed_shape_decoys_and_never_signs_in(): void
    {
        $c = $this->loginChallenge('nobody@retro.test');
        $secondUnknown = json_decode($this->post('/login/passkey/challenge', ['email' => 'nobody@retro.test'])->body(), true);
        self::assertTrue($secondUnknown['ok']);
        self::assertCount(8, $c['options']['allowCredentials']);
        self::assertSame(
            array_column($c['options']['allowCredentials'], 'id'),
            array_column($secondUnknown['options']['allowCredentials'], 'id'),
            'decoys must be stable across challenge requests so real IDs are not identifiable by set-diffing',
        );
        foreach ($c['options']['allowCredentials'] as $entry) {
            self::assertSame(['internal', 'hybrid', 'usb', 'nfc', 'ble'], $entry['transports']);
        }

        $knownNoPasskey = $this->makeUser(['username' => 'known_no_pk']);
        $knownNoPk = $this->loginChallenge((string) $knownNoPasskey['email']);
        self::assertCount(8, $knownNoPk['options']['allowCredentials']);

        $cred = $this->harness->createCredential();
        $res = $this->post('/login/passkey', [
            'email' => 'nobody@retro.test',
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
        self::assertStringNotContainsString('nobody', $res->body());
    }

    public function test_login_challenge_rate_limit_keeps_the_json_error_contract(): void
    {
        $this->get('/login');
        for ($i = 0; $i < 30; $i++) {
            $this->assertStatus(200, $this->post('/login/passkey/challenge', ['email' => 'rate-limit@example.test']));
        }
        $limited = $this->post('/login/passkey/challenge', ['email' => 'rate-limit@example.test']);
        $this->assertStatus(429, $limited);
        $json = json_decode($limited->body(), true);
        self::assertFalse($json['ok']);
        self::assertArrayHasKey('rate_limit', $json['errors']);
    }

    public function test_passkey_login_rate_limit_is_subject_keyed_and_cleared_after_success(): void
    {
        $user = $this->makeUser(['username' => 'pk_rate_clear']);
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        for ($i = 0; $i < 9; $i++) {
            $c = $this->loginChallenge((string) $user['email']);
            $bad = $this->harness->createCredential();
            $this->assertStatus(422, $this->post('/login/passkey', [
                'email' => (string) $user['email'],
                'credential' => $this->harness->assertionPayload($bad, $c['challenge'], $i + 1),
            ]));
        }

        $c = $this->loginChallenge((string) $user['email']);
        $success = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 10),
        ]);
        $this->assertStatus(200, $success);
        $this->post('/logout', []);

        $next = $this->loginChallenge((string) $user['email']);
        $badAgain = $this->harness->createCredential();
        $afterClear = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($badAgain, $next['challenge'], 11),
        ]);
        self::assertSame(422, $afterClear->status(), 'without clearSubject this would be a 429 after the successful tenth attempt');
    }

    public function test_assertion_replay_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        $payload = $this->harness->assertionPayload($cred, $c['challenge'], 1);
        $this->assertStatus(200, $this->post('/login/passkey', ['email' => (string) $user['email'], 'credential' => $payload]));
        $this->post('/logout', []);
        $this->get('/login');
        $this->assertStatus(422, $this->post('/login/passkey', ['email' => (string) $user['email'], 'credential' => $payload]));
    }

    public function test_challenge_minted_for_one_account_rejects_another_accounts_credential(): void
    {
        $alice = $this->makeUser(['username' => 'pk_alice2']);
        $this->actingAs($alice);
        $this->enroll($alice);
        $this->logoutClient();

        $bob = $this->makeUser(['username' => 'pk_bob2']);
        $this->actingAs($bob);
        $bobCred = $this->enroll($bob);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $alice['email']);
        $res = $this->post('/login/passkey', [
            'email' => (string) $alice['email'],
            'credential' => $this->harness->assertionPayload($bobCred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_altered_signature_fails_generically(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1, ['tamperSignature' => true]),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_banned_account_cannot_passkey_sign_in(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c = $this->loginChallenge((string) $user['email']);
        $this->db->run("UPDATE users SET status = 'banned' WHERE id = ?", [(int) $user['id']]);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c['challenge'], 1),
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('not permitted', $res->body());
    }

    public function test_non_increasing_counter_signs_in_and_writes_a_risk_audit_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $this->logoutClient();

        $c1 = $this->loginChallenge((string) $user['email']);
        $this->assertStatus(200, $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c1['challenge'], 5),
        ]));
        $this->post('/logout', []);

        $c2 = $this->loginChallenge((string) $user['email']);
        $res = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c2['challenge'], 5),
        ]);
        self::assertSame(200, $res->status(), 'anomaly must not block sign-in');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'passkey_counter_anomaly' AND target_id = ?",
            [(int) $user['id']],
        ));
        self::assertSame(5, (int) $this->db->fetchValue(
            'SELECT sign_count FROM webauthn_credentials WHERE user_id = ?',
            [(int) $user['id']],
        ), 'stored sign_count remains a high-water mark after an anomaly');
    }

    public function test_totp_enrolled_account_requires_a_uv_assertion(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll($user);
        $secret = $this->enrollTotp();
        $this->logoutClient();

        $c1 = $this->loginChallenge((string) $user['email']);
        $noUv = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c1['challenge'], 1, ['flags' => 0x01]),
        ]);
        $this->assertStatus(422, $noUv);
        self::assertStringContainsString('two-factor', strtolower($noUv->body()));

        $c2 = $this->loginChallenge((string) $user['email']);
        $withUv = $this->post('/login/passkey', [
            'email' => (string) $user['email'],
            'credential' => $this->harness->assertionPayload($cred, $c2['challenge'], 2),
        ]);
        self::assertSame(200, $withUv->status(), 'UV assertion is multi-factor and bypasses the TOTP interstitial');
        self::assertNotEmpty($secret);
    }

    private function enrollTotp(string $password = 'password123'): string
    {
        $enroll = $this->post('/settings/security/totp/enroll', ['current_password' => $password]);
        $this->assertStatus(200, $enroll);
        $secret = $this->extractAuthenticatorSecret($enroll);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => $password,
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        return $secret;
    }

    private function extractAuthenticatorSecret(Response $response): string
    {
        self::assertMatchesRegularExpression('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body());
        preg_match('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body(), $m);
        return $m[1];
    }
}
