<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Request;
use App\Core\Response;
use App\Repository\MfaRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\Totp;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WriteGate;
use App\Service\MfaService;
use App\Support\Base64Url;
use Tests\Support\Phase5\WebAuthnHarness;
use Tests\Support\TestCase;

/**
 * Every redirect a request can name goes through Controller::localPath: the
 * sign-in `next` (password, second factor, passkey), the tour's `next`, and
 * the `return` field of the engagement, workflow, member-surface and
 * link-preview forms. Each value below leaves the site once a browser
 * resolves it: tab, LF and CR are stripped from a Location and "\" reads as
 * "/", so all of them become "//evil.example".
 */
final class AppLocalReturnTest extends TestCase
{
    private const OFF_SITE = [
        'tab' => "/\t/evil.example",
        'newline' => "/\n/evil.example",
        'carriage return' => "/\r/evil.example",
        'backslash' => '/\\evil.example',
        'protocol-relative' => '//evil.example',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_password_sign_in_keeps_only_a_local_next(): void
    {
        $this->makeUser(['email' => 'next@example.test', 'password' => 'password123']);

        $this->get('/login', ['next' => '/settings/account']);
        $this->assertRedirect($this->signIn('next@example.test', '/settings/account'), '/settings/account');

        foreach (self::OFF_SITE as $case => $next) {
            $this->logoutClient();
            $form = $this->get('/login', ['next' => $next]);
            self::assertStringContainsString('<input type="hidden" name="next" value="/inbox">', $form->body(), $case);
            $this->assertRedirect($this->signIn('next@example.test', $next), '/inbox');
        }

        // An array-shaped next falls back too, without an array-to-string warning.
        $this->logoutClient();
        $form = $this->get('/login', ['next' => ['/settings/account']]);
        self::assertStringContainsString('<input type="hidden" name="next" value="/inbox">', $form->body());
    }

    public function test_the_second_factor_keeps_only_a_local_next(): void
    {
        $user = $this->makeUser(['email' => 'mfa-next@example.test', 'password' => 'password123']);
        $codes = $this->enrollTotp($user);

        $this->logoutClient();
        $this->get('/login');
        $challenge = $this->signIn('mfa-next@example.test', '/settings/security');
        $this->assertRedirect($this->completeMfa($challenge, array_shift($codes), '/settings/security'), '/settings/security');

        foreach (self::OFF_SITE as $case => $next) {
            $this->logoutClient();
            $this->get('/login');
            $challenge = $this->signIn('mfa-next@example.test', $next);
            self::assertStringContainsString('<input type="hidden" name="next" value="/inbox">', $challenge->body(), $case);
            $this->assertRedirect($this->completeMfa($challenge, array_shift($codes), $next), '/inbox');
        }
    }

    /** A challenge minted before this check shipped may still hold such a path. */
    public function test_the_second_factor_does_not_follow_a_stored_off_site_next(): void
    {
        $user = $this->makeUser(['email' => 'mfa-stored@example.test', 'password' => 'password123']);
        $codes = $this->enrollTotp($user);
        $challenges = new MfaRepository($this->db);
        $complete = function (string $storedNext) use ($challenges, $user, &$codes): Response {
            $this->logoutClient();
            $this->get('/login');
            $token = $challenges->createLoginChallenge((int) $user['id'], $storedNext, '127.0.0.1', 'phpunit');
            return $this->post('/login/mfa', ['mfa_token' => $token, 'code' => array_shift($codes)]);
        };

        $this->assertRedirect($complete('/settings/security'), '/settings/security');
        foreach (self::OFF_SITE as $next) {
            $this->assertRedirect($complete($next), '/inbox');
        }
    }

    public function test_the_mfa_service_stores_only_a_local_next(): void
    {
        $user = $this->userEntity($this->makeUser());
        $service = new MfaService(
            new MfaRepository($this->db),
            $this->users(),
            new ReauthGate(new PasswordHasher()),
            new SecretBox((string) $this->config->get('app.key', '')),
            new Totp(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            $this->config,
        );
        $request = new Request('POST', '/login', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $stored = fn (string $token): string => (string) $this->db->fetchValue(
            'SELECT next_path FROM mfa_login_challenges WHERE token_hash = ?',
            [hash('sha256', $token)],
        );

        self::assertSame('/settings/security', $stored($service->beginLoginChallenge($user, $request, '/settings/security')));
        foreach (self::OFF_SITE as $case => $next) {
            self::assertSame('/', $stored($service->beginLoginChallenge($user, $request, $next)), $case);
        }
    }

    public function test_passkey_sign_in_answers_with_only_a_local_redirect(): void
    {
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $rp = new RelyingParty((string) $this->config->get('app.url', 'http://localhost:8000'), null, 'testing');
        $harness = new WebAuthnHarness($rp->rpId(), $rp->origin());
        $user = $this->makeUser(['username' => 'pk_next']);
        $this->actingAs($user);
        $credential = $this->enrollPasskey($harness);

        $signCount = 0;
        $passkeySignIn = function (string $next) use ($harness, $credential, $user, &$signCount): string {
            $this->logoutClient();
            $this->get('/login');
            $options = json_decode($this->post('/login/passkey/challenge', ['email' => (string) $user['email']])->body(), true)['options'];
            $response = $this->post('/login/passkey', [
                'email' => (string) $user['email'],
                'credential' => $harness->assertionPayload($credential, (string) Base64Url::decode($options['challenge']), ++$signCount),
                'next' => $next,
            ]);
            $this->assertStatus(200, $response);
            return (string) json_decode($response->body(), true)['redirect'];
        };

        self::assertSame('/settings/security', $passkeySignIn('/settings/security'));
        foreach (self::OFF_SITE as $case => $next) {
            self::assertSame('/inbox', $passkeySignIn($next), $case);
        }
    }

    public function test_finishing_the_tour_returns_only_to_a_local_next(): void
    {
        $this->actingAs($this->makeUser());

        $this->assertRedirect($this->post('/onboarding/complete', ['next' => '/settings/account']), '/settings/account');
        foreach (self::OFF_SITE as $next) {
            $this->assertRedirect($this->post('/onboarding/complete', ['next' => $next]), '/');
        }
    }

    public function test_engagement_forms_return_only_to_a_local_path(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'return-engagement']);
        $thread = $this->makeThread($board, $this->makeUser(), 'Where the star lands');
        $id = $thread['thread_id'];
        $topic = '/t/' . $id . '-' . $thread['slug'];
        $this->actingAs($this->makeUser());

        $this->assertRedirect($this->post('/t/' . $id . '/star', ['return' => '/inbox?scope=starred']), '/inbox?scope=starred');
        foreach (self::OFF_SITE as $return) {
            $this->assertRedirect($this->post('/t/' . $id . '/star', ['return' => $return]), $topic);
            $this->assertRedirect($this->post('/t/' . $id . '/read', ['state' => 'read', 'return' => $return]), $topic);
            $this->assertRedirect($this->post('/c/return-engagement/read', ['return' => $return]), '/c/return-engagement');
        }
    }

    public function test_workflow_forms_return_only_to_a_local_path(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $this->db->run("UPDATE boards SET assignment_mode = 'self' WHERE id = ?", [(int) $board['id']]);
        $member = $this->makeUser();
        $thread = $this->makeThread($board, $member, 'Where the snooze lands');
        $id = $thread['thread_id'];
        $topic = '/t/' . $id . '-' . $thread['slug'];
        $this->actingAs($member);

        $this->assertRedirect($this->post('/t/' . $id . '/snooze', ['until' => 'tomorrow', 'return' => '/inbox?scope=snoozed']), '/inbox?scope=snoozed');
        foreach (self::OFF_SITE as $return) {
            $this->assertRedirect($this->post('/t/' . $id . '/snooze', ['until' => 'tomorrow', 'return' => $return]), $topic);
            $this->assertRedirect($this->post('/t/' . $id . '/assign', ['self' => '1', 'return' => $return]), $topic);
        }
    }

    public function test_member_surface_preferences_return_only_to_a_local_path(): void
    {
        $this->actingAs($this->makeUser());

        $this->assertRedirect($this->post('/settings/member-surfaces', ['rail_open' => '0', 'return' => '/inbox?scope=unread']), '/inbox?scope=unread');
        foreach (self::OFF_SITE as $return) {
            $this->assertRedirect($this->post('/settings/member-surfaces', ['rail_open' => '0', 'return' => $return]), '/');
        }
    }

    public function test_link_preview_console_returns_only_to_a_local_path(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $this->actingAs($this->makeAdmin());
        $optIn = '/admin/link-previews/boards/' . (int) $board['id'];

        $this->assertRedirect($this->post($optIn, ['enabled' => '1', 'return' => '/admin/link-previews?status=failed']), '/admin/link-previews?status=failed');
        foreach (self::OFF_SITE as $return) {
            $this->assertRedirect($this->post($optIn, ['enabled' => '1', 'return' => $return]), '/admin/link-previews');
        }
    }

    private function signIn(string $email, string $next): Response
    {
        return $this->post('/login', ['email' => $email, 'password' => 'password123', 'next' => $next]);
    }

    private function completeMfa(Response $challenge, string $code, string $next): Response
    {
        self::assertMatchesRegularExpression('/name="mfa_token" value="([a-f0-9]+)"/', $challenge->body());
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $challenge->body(), $m);
        return $this->post('/login/mfa', ['mfa_token' => $m[1], 'code' => $code, 'next' => $next]);
    }

    /**
     * @param array<string,mixed> $user
     * @return list<string> single-use recovery codes
     */
    private function enrollTotp(array $user): array
    {
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        preg_match('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $start->body(), $secret);
        self::assertArrayHasKey(1, $secret);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret[1]),
        ]);
        preg_match_all('/<code>([A-F0-9-]+)<\/code>/', $confirm->body(), $codes);
        self::assertCount(10, $codes[1]);
        return $codes[1];
    }

    /** @return array<string,mixed> */
    private function enrollPasskey(WebAuthnHarness $harness): array
    {
        $res = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        $this->assertStatus(200, $res);
        $challenge = (string) Base64Url::decode(json_decode($res->body(), true)['options']['challenge']);
        $credential = $harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $harness->registrationPayload($credential, $challenge),
        ]));
        return $credential;
    }
}
