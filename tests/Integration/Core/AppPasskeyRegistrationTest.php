<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Security\WebAuthn\RelyingParty;
use App\Support\Base64Url;
use Tests\Support\Phase5\WebAuthnHarness;
use Tests\Support\TestCase;

final class AppPasskeyRegistrationTest extends TestCase
{
    private WebAuthnHarness $harness;
    private string $rpId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $rp = new RelyingParty((string) $this->config->get('app.url', 'http://localhost:8000'), null, 'testing');
        $this->rpId = $rp->rpId();
        $this->harness = new WebAuthnHarness($rp->rpId(), $rp->origin());
    }

    /** @return array{options: array<string,mixed>} */
    private function mintChallenge(string $password = 'password123'): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => $password]);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        return $json;
    }

    private function createExtraSessionFor(array $user): string
    {
        $id = hash('sha256', 'extra-session-' . bin2hex(random_bytes(16)));
        (new SessionRepository($this->db))->create([
            'id' => $id,
            'user_id' => (int) $user['id'],
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => 'phpunit-other-session',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        return $id;
    }

    public function test_user_enrolls_a_passkey_end_to_end_and_duplicates_are_refused(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $json = $this->mintChallenge();
        $options = $json['options'];
        self::assertSame($this->rpId, $options['rp']['id']);
        self::assertSame([['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]], $options['pubKeyCredParams']);
        self::assertSame('none', $options['attestation']);

        $challenge = (string) Base64Url::decode($options['challenge']);
        $cred = $this->harness->createCredential();
        $store = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
            'nickname' => 'Laptop',
        ]);
        $this->assertStatus(200, $store);
        self::assertTrue(json_decode($store->body(), true)['ok']);

        $page = $this->get('/settings/security');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Laptop');

        $json2 = $this->mintChallenge();
        self::assertSame(Base64Url::encode($cred['credentialId']), $json2['options']['excludeCredentials'][0]['id']);
        $challenge2 = (string) Base64Url::decode($json2['options']['challenge']);
        $dup = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge2),
        ]);
        $this->assertStatus(422, $dup);
    }

    public function test_enrolling_a_passkey_revokes_other_sessions_but_keeps_current_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $otherSession = $this->createExtraSessionFor($user);
        self::assertNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));

        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($cred, $challenge),
        ]));

        self::assertNotNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$otherSession]));
        $current = $this->get('/settings/security');
        $this->assertStatus(200, $current);
    }

    public function test_challenge_requires_a_fresh_factor(): void
    {
        $this->actingAs($this->makeUser());
        $res = $this->post('/settings/security/passkeys/challenge', []);
        $this->assertStatus(422, $res);
        self::assertArrayHasKey('current_password', json_decode($res->body(), true)['errors']);

        $badPassword = hash('sha256', __METHOD__);
        $wrong = $this->post('/settings/security/passkeys/challenge', ['current_password' => $badPassword]);
        $this->assertStatus(422, $wrong);
    }

    public function test_management_rate_limit_keeps_the_json_error_contract(): void
    {
        $this->actingAs($this->makeUser());
        for ($i = 0; $i < 10; $i++) {
            $this->assertStatus(422, $this->post('/settings/security/passkeys/challenge', []));
        }
        $limited = $this->post('/settings/security/passkeys/challenge', []);
        $this->assertStatus(429, $limited);
        $json = json_decode($limited->body(), true);
        self::assertFalse($json['ok']);
        self::assertArrayHasKey('rate_limit', $json['errors']);
    }

    public function test_challenge_is_single_use(): void
    {
        $this->actingAs($this->makeUser());
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $payload = $this->harness->registrationPayload($cred, $challenge);

        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $payload]));
        $replay = $this->post('/settings/security/passkeys', ['credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge)]);
        $this->assertStatus(422, $replay);
    }

    public function test_challenge_minted_for_one_user_cannot_complete_for_another(): void
    {
        $alice = $this->makeUser(['username' => 'pk_alice']);
        $this->actingAs($alice);
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);

        $this->logoutClient();
        $this->actingAs($this->makeUser(['username' => 'pk_bob']));
        $res = $this->post('/settings/security/passkeys', [
            'credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge),
        ]);
        $this->assertStatus(422, $res);
    }

    public function test_wrong_origin_and_wrong_rp_refuse_at_the_http_layer(): void
    {
        $this->actingAs($this->makeUser());
        foreach ([['origin' => 'https://evil.test'], ['rpId' => 'evil.test']] as $overrides) {
            $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
            $res = $this->post('/settings/security/passkeys', [
                'credential' => $this->harness->registrationPayload($this->harness->createCredential(), $challenge, $overrides),
            ]);
            $this->assertStatus(422, $res);
        }
    }

    public function test_step_up_challenge_round_trip_verifies_a_registered_credential(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $challenge = (string) Base64Url::decode($this->mintChallenge()['options']['challenge']);
        $cred = $this->harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', ['credential' => $this->harness->registrationPayload($cred, $challenge)]));

        $step = $this->post('/settings/security/passkeys/step-up-challenge', []);
        $this->assertStatus(200, $step);
        $stepOptions = json_decode($step->body(), true)['options'];
        self::assertSame('required', $stepOptions['userVerification']);
        $stepChallenge = (string) Base64Url::decode($stepOptions['challenge']);

        $res = $this->post('/settings/security/passkeys/challenge', [
            'passkey_assertion' => $this->harness->assertionPayload($cred, $stepChallenge, 1),
        ]);
        $this->assertStatus(200, $res);
    }
}
