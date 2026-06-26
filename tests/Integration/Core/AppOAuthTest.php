<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Domain\User;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
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
        return new OAuthService($this->db, new OAuthIdentityRepository($this->db), new UserRepository($this->db));
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

    public function test_username_generation_avoids_collisions(): void
    {
        $svc = $this->svc();
        self::assertSame('AdaLovelace', $svc->generateUsername('Ada Lovelace'));
        $this->makeUser(['username' => 'AdaLovelace']);
        self::assertSame('AdaLovelace1', $svc->generateUsername('Ada Lovelace'));
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
}
