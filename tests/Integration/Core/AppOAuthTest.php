<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Controller\OAuthController;
use App\Core\FeatureFlags;
use App\Core\Request;
use App\Domain\User;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\RegistrationPolicy;
use App\Service\OAuth\GoogleProvider;
use App\Service\OAuth\NormalizedIdentity;
use App\Service\OAuthService;
use Tests\Support\TestCase;

/**
 * OAuth account resolution + linking (P2-10). Covers the security-critical
 * decision tree: returning login, new signup with avatar import, verified-email
 * collision (never auto-merge), banned block, explicit linking, and the
 * last-login-method protection on unlink. Token exchange is network-bound and
 * exercised via the provider classes; resolution is tested directly.
 */
final class AppOAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function svc(): OAuthService
    {
        return new OAuthService($this->db, new OAuthIdentityRepository($this->db), new UserRepository($this->db), $this->registrationPolicy());
    }

    private function registrationPolicy(): RegistrationPolicy
    {
        // Fresh per call: FeatureFlags memoizes its settings read.
        return new RegistrationPolicy($this->settings(), new FeatureFlags(new SettingRepository($this->db)));
    }

    private function settings(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    private function identity(string $sub = 'sub-1', ?string $email = 'oauth@example.test', bool $verified = true): NormalizedIdentity
    {
        return new NormalizedIdentity('google', $sub, $email, $verified, 'Ada Lovelace', 'https://cdn.example/ada.png');
    }

    public function test_new_signup_creates_account_and_imports_avatar(): void
    {
        $out = $this->svc()->resolve($this->identity(), null);

        self::assertSame('created', $out['action']);
        $user = $out['user'];
        self::assertInstanceOf(User::class, $user);

        $row = $this->users()->find($user->id());
        self::assertSame('AdaLovelace', $row['username']);          // derived from display name
        self::assertSame('oauth', $row['avatar_source']);            // avatar imported
        self::assertNotNull($row['email_verified_at']);             // provider asserted verified

        $identity = (new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-1');
        self::assertNotNull($identity);
        self::assertSame('https://cdn.example/ada.png', $identity['avatar_url']);
    }

    public function test_returning_identity_logs_in_same_account(): void
    {
        $svc = $this->svc();
        $first = $svc->resolve($this->identity(), null);
        $again = $svc->resolve($this->identity(), null);

        self::assertSame('login', $again['action']);
        self::assertSame($first['user']->id(), $again['user']->id());
    }

    public function test_login_and_created_outcomes_redirect_to_the_community_inbox(): void
    {
        $request = new Request('GET', '/auth/google/callback', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'phpunit',
        ]);
        $buildContainer = new \ReflectionMethod($this->app, 'buildContainer');
        $container = $buildContainer->invoke($this->app, $request);
        $controller = new OAuthController($container);
        $handleOutcome = new \ReflectionMethod($controller, 'handleOutcome');

        $returning = User::fromRow($this->makeUser(['username' => 'oauth_returning']));
        $this->assertRedirect($handleOutcome->invoke($controller, [
            'action' => 'login',
            'user' => $returning,
        ], 'google'), '/inbox');

        $created = User::fromRow($this->makeUser(['username' => 'oauth_created']));
        $this->assertRedirect($handleOutcome->invoke($controller, [
            'action' => 'created',
            'user' => $created,
        ], 'google'), '/inbox');
    }

    public function test_verified_email_collision_never_auto_merges(): void
    {
        $local = $this->makeUser(['username' => 'localowner', 'email' => 'collide@example.test']);
        $before = $this->users()->count();

        $out = $this->svc()->resolve($this->identity('sub-x', 'collide@example.test', true), null);

        self::assertSame('collision', $out['action']);
        self::assertArrayNotHasKey('user', $out);
        self::assertSame($before, $this->users()->count());          // no account created
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-x'));
        // The local account is untouched (not silently linked).
        self::assertSame(0, (new OAuthIdentityRepository($this->db))->countForUser((int) $local['id']));
    }

    public function test_banned_account_cannot_sign_in_via_provider(): void
    {
        $created = $this->svc()->resolve($this->identity('sub-ban'), null);
        $this->users()->setStatus($created['user']->id(), 'banned');

        $out = $this->svc()->resolve($this->identity('sub-ban'), null);
        self::assertSame('banned', $out['action']);
    }

    public function test_link_to_current_user_and_repeat_is_already_linked(): void
    {
        $svc = $this->svc();
        $current = User::fromRow($this->makeUser(['username' => 'linker']));

        $linked = $svc->resolve($this->identity('sub-link'), $current);
        self::assertSame('linked', $linked['action']);
        self::assertTrue((new OAuthIdentityRepository($this->db))->existsForUserProvider($current->id(), 'google'));

        // A second, different google identity while already linked → already_linked.
        $again = $svc->resolve($this->identity('sub-link-2'), $current);
        self::assertSame('already_linked', $again['action']);
    }

    public function test_identity_linked_to_another_account_is_rejected(): void
    {
        $svc = $this->svc();
        $owner = User::fromRow($this->makeUser(['username' => 'owner_a']));
        $svc->resolve($this->identity('sub-shared'), $owner);

        $intruder = User::fromRow($this->makeUser(['username' => 'intruder_b']));
        $out = $svc->resolve($this->identity('sub-shared'), $intruder);
        self::assertSame('already_linked_elsewhere', $out['action']);
    }

    public function test_unlink_blocks_removing_last_login_method(): void
    {
        $svc = $this->svc();
        // OAuth-only account (no password).
        $id = $this->users()->create([
            'username' => 'oauthonly', 'email' => 'only@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        $svc->linkToUser($id, $this->identity('sub-only'));
        $user = $this->users()->findEntity($id);

        try {
            $svc->unlink($user, 'google');
            self::fail('Expected unlinking the only login method to be blocked.');
        } catch (\App\Core\ValidationException $e) {
            self::assertArrayHasKey('provider', $e->errors);
        }

        // After setting a password, unlink is allowed.
        $this->users()->setPassword($id, (new PasswordHasher())->hash('password123'));
        $user = $this->users()->findEntity($id);
        self::assertTrue($svc->unlink($user, 'google'));
        self::assertSame(0, (new OAuthIdentityRepository($this->db))->countForUser($id));
    }

    public function test_unlink_ignores_disabled_provider_identities_when_protecting_the_last_method(): void
    {
        $svc = new OAuthService(
            $this->db,
            new OAuthIdentityRepository($this->db),
            new UserRepository($this->db),
            $this->registrationPolicy(),
            null,
            static fn (): array => ['google'],
        );
        $id = $this->users()->create([
            'username' => 'oauthdisabledfallback', 'email' => 'oauthdisabledfallback@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        $svc->linkToUser($id, $this->identity('sub-primary'));
        $svc->linkToUser($id, new NormalizedIdentity('backup', 'sub-disabled', null, false, null, null));
        $user = $this->users()->findEntity($id);

        try {
            $svc->unlink($user, 'google');
            self::fail('Expected unlinking the only usable OAuth method to be blocked.');
        } catch (\App\Core\ValidationException $e) {
            self::assertArrayHasKey('provider', $e->errors);
        }

        self::assertNotNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-primary'));
        self::assertNotNull((new OAuthIdentityRepository($this->db))->findByProvider('backup', 'sub-disabled'));
    }

    public function test_username_generation_avoids_collisions(): void
    {
        $svc = $this->svc();
        self::assertSame('AdaLovelace', $svc->generateUsername('Ada Lovelace'));
        $this->makeUser(['username' => 'AdaLovelace']);
        self::assertSame('AdaLovelace1', $svc->generateUsername('Ada Lovelace'));
    }

    // ---- closed registration gate (P3-05) --------------------------------

    public function test_closed_registration_blocks_a_brand_new_oauth_signup(): void
    {
        $this->settings()->set('registration_mode', 'closed');
        $before = $this->users()->count();

        $out = $this->svc()->resolve($this->identity('sub-closed'), null);

        self::assertSame('registration_closed', $out['action']);
        self::assertArrayNotHasKey('user', $out);                    // nobody is logged in
        self::assertSame($before, $this->users()->count());          // no account created
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-closed'));
    }

    public function test_unknown_persisted_registration_mode_blocks_a_brand_new_oauth_signup(): void
    {
        // A corrupt setting or future restrictive mode must not fail open.
        $this->settings()->set('registration_mode', 'banana');
        $before = $this->users()->count();

        $out = $this->svc()->resolve($this->identity('sub-unknown-mode'), null);

        self::assertSame('registration_closed', $out['action']);
        self::assertArrayNotHasKey('user', $out);
        self::assertSame($before, $this->users()->count());
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-unknown-mode'));
    }

    public function test_closed_registration_still_lets_an_existing_identity_log_in(): void
    {
        // Provision the account while sign-ups are open…
        $created = $this->svc()->resolve($this->identity('sub-return'), null);
        self::assertSame('created', $created['action']);

        // …then close registration: the returning user must still get in.
        $this->settings()->set('registration_mode', 'closed');
        $again = $this->svc()->resolve($this->identity('sub-return'), null);

        self::assertSame('login', $again['action']);
        self::assertSame($created['user']->id(), $again['user']->id());
    }

    // ---- invite-only registration gate (P5-13) ----------------------------

    public function test_invite_mode_blocks_a_brand_new_oauth_signup_with_invite_only_action(): void
    {
        // Invite-only sites provision no accounts from a provider identity:
        // the invitation must be redeemed on /register first.
        $this->settings()->set('registration_mode', 'invite');
        $this->settings()->set('features', ['invitations' => true]);
        $before = $this->users()->count();

        $out = $this->svc()->resolve($this->identity('sub-invite'), null);

        self::assertSame('registration_invite_only', $out['action']);
        self::assertArrayNotHasKey('user', $out);                    // nobody is logged in
        self::assertSame($before, $this->users()->count());          // no account created
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-invite'));
    }

    public function test_invite_mode_with_dark_flag_degrades_to_closed_for_oauth(): void
    {
        // Explicit invitation rollback must fail closed for OAuth provisioning.
        $this->settings()->set('registration_mode', 'invite');
        $this->settings()->set('features', ['invitations' => false]);

        $out = $this->svc()->resolve($this->identity('sub-invite-dark'), null);

        self::assertSame('registration_closed', $out['action']);
        self::assertNull((new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-invite-dark'));
    }

    public function test_closed_registration_still_lets_a_signed_in_user_link_a_provider(): void
    {
        // Linking a provider to an already-existing account is not a sign-up, so
        // the closed-registration gate must not block it.
        $this->settings()->set('registration_mode', 'closed');
        $current = User::fromRow($this->makeUser(['username' => 'closedlinker']));

        $out = $this->svc()->resolve($this->identity('sub-link-closed'), $current);

        self::assertSame('linked', $out['action']);
        self::assertTrue((new OAuthIdentityRepository($this->db))->existsForUserProvider($current->id(), 'google'));
    }

    // ---- provider + controller guards ------------------------------------

    public function test_google_authorize_url_carries_state_pkce_and_nonce(): void
    {
        $provider = new GoogleProvider('client-123', 'secret-abc');
        self::assertTrue($provider->isConfigured());
        $url = $provider->authorizeUrl('https://app.test/auth/google/callback', 'STATEX', 'CHALLENGEX', 'NONCEX');
        self::assertStringContainsString('state=STATEX', $url);
        self::assertStringContainsString('code_challenge=CHALLENGEX', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString('nonce=NONCEX', $url);

        self::assertFalse((new GoogleProvider('', ''))->isConfigured());
    }

    public function test_unconfigured_provider_routes_are_404(): void
    {
        // No OAuth credentials configured in the test environment.
        $this->assertStatus(404, $this->get('/auth/google/redirect'));
        $this->assertStatus(404, $this->get('/auth/github/callback'));
        $this->assertStatus(404, $this->get('/auth/unknown/redirect'));
    }

    public function test_connections_page_requires_login(): void
    {
        $this->assertRedirectContains($this->get('/settings/connections'), '/login');
    }

    public function test_oauth_only_account_can_set_password(): void
    {
        $id = $this->users()->create([
            'username' => 'needspw', 'email' => 'needspw@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        $row = $this->users()->find($id);
        $this->actingAs($row);

        $res = $this->post('/settings/connections/set-password', [
            'new_password' => 'brandnewpass', 'new_password_confirm' => 'brandnewpass',
        ]);
        $this->assertRedirect($res, '/settings/connections');

        $hash = $this->users()->find($id)['password_hash'];
        self::assertNotNull($hash);
        self::assertTrue((new PasswordHasher())->verify('brandnewpass', $hash));
    }

    public function test_unverified_provider_email_does_not_occupy_the_unique_email_slot(): void
    {
        // A provider can surface an UNVERIFIED primary email; it must not squat a
        // real address on the globally-unique users.email and deny the rightful
        // owner registration (email squatting).
        $out = $this->svc()->resolve($this->identity('sub-unv', 'victim@corp.test', false), null);
        self::assertSame('created', $out['action']);

        $row = $this->users()->find($out['user']->id());
        self::assertNotSame('victim@corp.test', $row['email'], 'unverified provider email must not land on users.email');
        self::assertStringEndsWith('.oauth.invalid', (string) $row['email']);
        self::assertNull($row['email_verified_at']);

        // The victim's real address is still free to register.
        self::assertFalse($this->users()->emailExists('victim@corp.test'));
        // …but it is preserved on the identity row for later promotion.
        $identity = (new OAuthIdentityRepository($this->db))->findByProvider('google', 'sub-unv');
        self::assertSame('victim@corp.test', $identity['email']);
    }

    public function test_verified_provider_email_still_lands_on_the_account(): void
    {
        $out = $this->svc()->resolve($this->identity('sub-ok', 'real@example.test', true), null);
        $row = $this->users()->find($out['user']->id());
        self::assertSame('real@example.test', $row['email']);
        self::assertNotNull($row['email_verified_at']);
    }
}
