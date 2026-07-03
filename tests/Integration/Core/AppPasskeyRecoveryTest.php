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

final class AppPasskeyRecoveryTest extends TestCase
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
    private function enroll(string $password = 'password123', ?string $nickname = null): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
            'nickname' => $nickname,
        ]));

        return $cred;
    }

    private function passkeyLogin(string $email, array $cred, int $signCount): Response
    {
        $this->get('/login');
        $res = $this->post('/login/passkey/challenge', ['email' => $email]);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);

        return $this->post('/login/passkey', [
            'email' => $email,
            'credential' => $this->harness->assertionPayload($cred, $challenge, $signCount),
        ]);
    }

    public function test_password_totp_and_recovery_paths_survive_passkey_enrollment(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->enroll();

        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $start);
        $secret = $this->extractAuthenticatorSecret($start);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        $recovery = $this->extractRecoveryCodes($confirm)[0];
        $this->logoutClient();

        $this->get('/login');
        $mfaPage = $this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $mfaPage);
        $this->assertSeeText($mfaPage, 'Two-factor verification');
        $this->assertRedirect($this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($mfaPage),
            'code' => (new Totp())->code($secret, time() + 30),
        ]));
        $this->assertStatus(200, $this->get('/settings/security'));
        $this->post('/logout', []);

        $this->get('/login');
        $mfaPage2 = $this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $mfaPage2);
        $this->assertRedirect($this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($mfaPage2),
            'code' => $recovery,
        ]));
        $this->assertStatus(200, $this->get('/settings/security'));
    }

    public function test_lost_authenticator_journey_fallback_revoke_reenroll(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $lost = $this->enroll(nickname: 'Lost key');
        $this->logoutClient();

        $this->get('/login');
        $this->assertRedirect($this->post('/login', ['email' => (string) $user['email'], 'password' => 'password123']));
        preg_match('#/settings/security/passkeys/(\d+)/revoke#', $this->get('/settings/security')->body(), $m);
        self::assertNotEmpty($m);
        $this->assertRedirect(
            $this->post("/settings/security/passkeys/{$m[1]}/revoke", ['current_password' => 'password123']),
            '/settings/security',
        );

        $replacement = $this->enroll(nickname: 'New key');
        $this->post('/logout', []);
        $ok = $this->passkeyLogin((string) $user['email'], $replacement, 1);
        $this->assertStatus(200, $ok);
        self::assertTrue(json_decode($ok->body(), true)['ok']);
        $this->post('/logout', []);

        $this->assertStatus(422, $this->passkeyLogin((string) $user['email'], $lost, 9));
    }

    public function test_suspended_user_cannot_mint_an_enrollment_challenge(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?",
            [(int) $user['id']],
        );

        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        $this->assertStatus(403, $res);
    }

    public function test_one_registration_challenge_admits_exactly_one_credential(): void
    {
        $this->actingAs($this->makeUser());
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $first = $this->harness->registrationPayload($this->harness->createCredential(), $challenge);
        $second = $this->harness->registrationPayload($this->harness->createCredential(), $challenge);

        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $first]));
        $this->assertStatus(422, $this->post('/settings/security/passkeys', ['credential' => $second]));
    }

    public function test_export_includes_passkey_metadata_only(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $cred = $this->enroll(nickname: 'Export me');

        $export = $this->post('/settings/account/export');
        $this->assertStatus(200, $export);
        self::assertSame('application/json; charset=UTF-8', $export->getHeader('Content-Type'));
        $payload = json_decode($export->body(), true);
        self::assertSame('Export me', $payload['passkeys'][0]['nickname']);
        self::assertSame('internal', $payload['passkeys'][0]['transports']);
        self::assertIsBool($payload['passkeys'][0]['backed_up']);
        self::assertArrayHasKey('created_at', $payload['passkeys'][0]);
        self::assertArrayHasKey('last_used_at', $payload['passkeys'][0]);
        self::assertStringNotContainsString(Base64Url::encode($cred['credentialId']), $export->body(), 'raw credential ids never leave');
        self::assertStringNotContainsString(Base64Url::encode($cred['coseKey']), $export->body(), 'public key material stays out of account exports');
    }

    private function extractAuthenticatorSecret(Response $response): string
    {
        self::assertMatchesRegularExpression('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body());
        preg_match('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body(), $m);

        return $m[1];
    }

    /** @return list<string> */
    private function extractRecoveryCodes(Response $response): array
    {
        preg_match_all('/<code>([A-F0-9-]+)<\/code>/', $response->body(), $m);
        self::assertNotEmpty($m[1]);

        return $m[1];
    }

    private function extractMfaToken(Response $response): string
    {
        self::assertMatchesRegularExpression('/name="mfa_token" value="([a-f0-9]+)"/', $response->body());
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $response->body(), $m);

        return $m[1];
    }
}
