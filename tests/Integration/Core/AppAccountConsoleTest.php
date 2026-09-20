<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Response;
use App\Repository\SettingRepository;
use App\Repository\WebAuthnCredentialRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class AppAccountConsoleTest extends TestCase
{
    private const INTRO = 'Everything this community knows about you, and everything it does on your behalf.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_passwordless_security_and_lifecycle_offer_first_password_without_impossible_prompts(): void
    {
        $user = $this->makePasswordlessUser();
        $this->actingAs($user);
        $security = $this->get('/settings/security');
        self::assertStringContainsString('id="set-password"', $security->body());
        self::assertStringContainsString('action="/settings/security/set-password"', $security->body());
        self::assertStringNotContainsString('name="current_password"', $security->body());
        $lifecycle = $this->get('/settings/account/lifecycle');
        self::assertStringNotContainsString('name="current_password"', $lifecycle->body());
        self::assertStringContainsString('href="/settings/security#set-password"', $lifecycle->body());
        foreach (['/settings/security/totp/enroll', '/settings/account/deactivate', '/settings/account/delete/request', '/settings/security'] as $path) {
            $response = $this->post($path);
            $this->assertStatus(422, $response);
            self::assertStringContainsString('Set a password', $response->body());
            self::assertStringNotContainsString('Your current password is incorrect.', $response->body());
        }
    }

    public function test_security_first_password_validates_and_works_with_oauth_dark(): void
    {
        $this->setFeatureFlags(['oauth' => false]);
        $user = $this->makePasswordlessUser();
        $this->actingAs($user);
        $mismatch = $this->post('/settings/security/set-password', ['new_password' => 'new-secure-password', 'new_password_confirm' => 'different-password']);
        $this->assertStatus(422, $mismatch);
        self::assertStringContainsString('aria-describedby="err-new_password_confirm"', $mismatch->body());
        self::assertStringNotContainsString('new-secure-password', $mismatch->body());
        self::assertStringNotContainsString('different-password', $mismatch->body());
        self::assertNull($this->users()->find((int) $user['id'])['password_hash']);
        $good = $this->post('/settings/security/set-password', ['new_password' => 'new-secure-password', 'new_password_confirm' => 'new-secure-password']);
        $this->assertRedirect($good, '/settings/security');
        self::assertTrue(password_verify('new-secure-password', $this->users()->find((int) $user['id'])['password_hash']));
        self::assertStringContainsString('Change password', $this->get('/settings/security')->body());
        $this->assertStatus(404, $this->post('/settings/connections/set-password'));
        $this->assertStatus(404, $this->get('/settings/connections'));
        $refused = $this->post('/settings/security/set-password', ['new_password' => 'replacement-password', 'new_password_confirm' => 'replacement-password']);
        $this->assertStatus(422, $refused);
        self::assertStringContainsString('already has a password', $refused->body());
        self::assertTrue(password_verify('new-secure-password', $this->users()->find((int) $user['id'])['password_hash']));
    }

    public function test_first_password_refuses_stale_passwordless_snapshot(): void
    {
        $row = $this->makePasswordlessUser();
        $stale = $this->userEntity($row);
        $hasher = new \App\Security\PasswordHasher();
        $service = new \App\Service\AccountService($this->db, $this->users(), $hasher, new \App\Security\ReauthGate($hasher), new \App\Security\WriteGate(), $this->config);
        $this->users()->setPassword($stale->id(), $hasher->hash('first-password'));
        try {
            $service->setInitialPassword($stale, ['new_password' => 'stale-replacement', 'new_password_confirm' => 'stale-replacement']);
            self::fail('A stale passwordless identity must not overwrite a password.');
        } catch (\App\Core\ValidationException $error) {
            self::assertArrayHasKey('new_password', $error->errors);
        }
        self::assertTrue($hasher->verify('first-password', $this->users()->find($stale->id())['password_hash']));
    }

    public function test_first_password_is_rate_limited_and_restricted_accounts_cannot_set_credentials(): void
    {
        $user = $this->makePasswordlessUser();
        $this->actingAs($user);
        for ($i = 0; $i < 10; $i++) {
            $this->assertStatus(422, $this->post('/settings/security/set-password', ['new_password' => 'short']));
        }
        $this->assertStatus(429, $this->post('/settings/security/set-password', ['new_password' => 'short']));
        foreach (['banned', 'suspended', 'deactivated', 'pending_deletion'] as $status) {
            $restricted = $this->makePasswordlessUser();
            $this->db->run('UPDATE users SET status = ?, suspended_until = ? WHERE id = ?', [$status, gmdate('Y-m-d H:i:s', time() + 3600), $restricted['id']]);
            $this->actingAs($this->users()->find((int) $restricted['id']));
            $this->assertStatus(403, $this->post('/settings/security/set-password', ['new_password' => 'restricted-password', 'new_password_confirm' => 'restricted-password']));
            self::assertNull($this->users()->find((int) $restricted['id'])['password_hash']);
        }
    }

    public function test_passwordless_accounts_can_recover_from_deactivation_and_cancel_pending_deletion(): void
    {
        $deactivated = $this->makePasswordlessUser();
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [$deactivated['id']]);
        $this->actingAs($this->users()->find((int) $deactivated['id']));
        $page = $this->get('/settings/account/lifecycle');
        self::assertStringContainsString('action="/settings/account/reactivate"', $page->body());
        self::assertStringNotContainsString('name="current_password"', $page->body());
        $this->assertRedirect($this->post('/settings/account/reactivate'), '/settings/account/lifecycle');
        self::assertSame('active', $this->users()->find((int) $deactivated['id'])['status']);

        $pending = $this->makeUser();
        $this->actingAs($pending);
        $this->assertRedirect($this->post('/settings/account/delete/request', ['current_password' => 'password123']), '/settings/account/lifecycle');
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$pending['id']]);
        $this->actingAs($this->users()->find((int) $pending['id']));
        $page = $this->get('/settings/account/lifecycle');
        self::assertStringContainsString('action="/settings/account/delete/cancel"', $page->body());
        self::assertStringNotContainsString('name="current_password"', $page->body());
        $this->assertRedirect($this->post('/settings/account/delete/cancel'), '/settings/account/lifecycle');
        self::assertSame('active', $this->users()->find((int) $pending['id'])['status']);
    }

    /** @return array<string,mixed> */
    private function makePasswordlessUser(): array
    {
        $user = $this->makeUser();
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$user['id']]);
        return $this->users()->find((int) $user['id']);
    }

    /** @return iterable<string,array{string,string}> */
    public static function accountRoutes(): iterable
    {
        yield 'profile' => ['/settings/account', 'profile'];
        yield 'security' => ['/settings/security', 'security'];
        yield 'privacy' => ['/settings/privacy', 'privacy'];
        yield 'appearance' => ['/settings/appearance', 'appearance'];
        yield 'reading' => ['/settings/preferences', 'reading'];
        yield 'composing' => ['/settings/composing', 'composing'];
        yield 'drafts' => ['/drafts', 'drafts'];
        yield 'boards' => ['/settings/boards', 'boards'];
        yield 'notifications' => ['/settings/notifications', 'notifications'];
        yield 'connections' => ['/settings/connections', 'connections'];
        yield 'blocks' => ['/settings/blocks', 'blocks'];
        yield 'sessions' => ['/settings/sessions', 'sessions'];
        yield 'account lifecycle' => ['/settings/account/lifecycle', 'account'];
    }

    public function test_account_destinations_keep_guest_login_redirects(): void
    {
        foreach (self::accountRoutes() as [$path]) {
            $response = $this->get($path);
            $this->assertRedirectContains($response, '/login?next=');
        }
    }

    #[DataProvider('accountRoutes')]
    public function test_all_account_routes_render_one_common_head_and_explicit_active_destination(
        string $path,
        string $active,
    ): void {
        $this->actingAs($this->makeUser());

        $response = $this->get($path);
        $this->assertStatus(200, $response);
        $body = $response->body();

        self::assertSame(1, substr_count($body, '<span class="eyebrow">Account</span>'));
        self::assertSame(1, substr_count($body, '<h1>Account settings</h1>'));
        self::assertSame(1, substr_count($body, self::INTRO));
        self::assertSame(2, substr_count($body, 'aria-label="Settings sections"'));
        self::assertStringContainsString('data-settings-mobile-nav>', $body);
        if ($path === '/drafts') {
            self::assertStringNotContainsString('<h1>Drafts</h1>', $body);
        }

        $this->assertActiveRail($response, $active);
    }

    public function test_account_rail_groups_links_and_icons_follow_the_adjudicated_order(): void
    {
        $this->actingAs($this->makeUser());
        $response = $this->get('/settings/account');
        $this->assertStatus(200, $response);
        $nav = $this->settingsNav($response);

        self::assertSame(1, substr_count($nav, '<span class="settings-rail-title">Account</span>'));
        self::assertSame(1, substr_count($nav, '<span class="settings-rail-title">Reading &amp; writing</span>'));
        self::assertSame(1, substr_count($nav, '<span class="settings-rail-title">Community</span>'));

        self::assertSame(
            [
                ['profile', 'security', 'privacy'],
                ['appearance', 'reading', 'composing', 'drafts', 'boards'],
                ['notifications', 'connections', 'blocks', 'sessions', 'account', 'appeals'],
            ],
            $this->settingsGroupKeys($nav),
            'Each destination must remain owned by its adjudicated rail group.',
        );

        $expected = [
            ['/settings/account', 'Profile', 'settings-profile'],
            ['/settings/security', 'Security', 'shield'],
            ['/settings/privacy', 'Privacy', 'eye'],
            ['/settings/appearance', 'Appearance', 'sun'],
            ['/settings/preferences', 'Reading', 'book'],
            ['/settings/composing', 'Composing', 'edit-3'],
            ['/drafts', 'Drafts', 'file'],
            ['/settings/boards', 'Boards', 'menu'],
            ['/settings/notifications', 'Notifications', 'bell'],
            ['/settings/connections', 'Connections', 'link'],
            ['/settings/blocks', 'Blocks', 'ban'],
            ['/settings/sessions', 'Sessions', 'monitor'],
            ['/settings/account/lifecycle', 'Account', 'archive'],
            ['/appeals', 'Appeals', 'flag'],
        ];

        $previous = -1;
        foreach ($expected as [$href, $label, $icon]) {
            $needle = 'href="' . $href . '"';
            $position = strpos($nav, $needle);
            self::assertNotFalse($position, "Missing account rail destination {$href}.");
            self::assertGreaterThan($previous, $position, "{$href} rendered out of order.");
            self::assertSame(1, substr_count($nav, $needle), "{$label} must have exactly one rail link.");
            self::assertMatchesRegularExpression(
                '~<a\b(?=[^>]*href="' . preg_quote($href, '~') . '")[^>]*>.*?icon-' . preg_quote($icon, '~') . '.*?<span>' . preg_quote($label, '~') . '</span></a>~s',
                $nav,
            );
            $previous = $position;
        }

        $replay = strpos($nav, 'data-tour-replay');
        self::assertNotFalse($replay);
        self::assertGreaterThan($previous, $replay);
        self::assertMatchesRegularExpression('/<button\b[^>]*type="button"[^>]*data-tour-replay[^>]*>Replay tour<\/button>/', $nav);
        self::assertDoesNotMatchRegularExpression('/<a\b[^>]*data-tour-replay/', $nav);
    }

    public function test_account_rail_silently_omits_every_dark_feature_destination(): void
    {
        $this->setFeatureFlags([
            'drafts' => false,
            'oauth' => false,
            'account_lifecycle' => false,
            'appeals' => false,
            'product_tour' => false,
        ]);
        $this->actingAs($this->makeUser());

        $response = $this->get('/settings/account');
        $this->assertStatus(200, $response);
        $nav = $this->settingsNav($response);

        foreach (['/drafts', '/settings/connections', '/settings/account/lifecycle', '/appeals'] as $href) {
            self::assertStringNotContainsString('href="' . $href . '"', $nav);
        }
        self::assertStringNotContainsString('data-tour-replay', $nav);
        self::assertStringNotContainsString('is-disabled', $nav);
        self::assertStringNotContainsString('aria-disabled', $nav);
    }

    /** @return iterable<string,array{string,string}> */
    public static function conditionalRailFeatures(): iterable
    {
        yield 'drafts' => ['drafts', 'href="/drafts"'];
        yield 'oauth' => ['oauth', 'href="/settings/connections"'];
        yield 'account lifecycle' => ['account_lifecycle', 'href="/settings/account/lifecycle"'];
        yield 'appeals' => ['appeals', 'href="/appeals"'];
        yield 'product tour' => ['product_tour', 'data-tour-replay'];
    }

    #[DataProvider('conditionalRailFeatures')]
    public function test_each_conditional_rail_control_is_owned_by_exactly_one_feature(
        string $darkFeature,
        string $darkNeedle,
    ): void {
        $controls = [
            'drafts' => 'href="/drafts"',
            'oauth' => 'href="/settings/connections"',
            'account_lifecycle' => 'href="/settings/account/lifecycle"',
            'appeals' => 'href="/appeals"',
            'product_tour' => 'data-tour-replay',
        ];
        $flags = array_fill_keys(array_keys($controls), true);
        $flags[$darkFeature] = false;
        $this->setFeatureFlags($flags);
        $this->actingAs($this->makeUser());

        $response = $this->get('/settings/account');
        $this->assertStatus(200, $response);
        $nav = $this->settingsNav($response);

        foreach ($controls as $feature => $needle) {
            if ($feature === $darkFeature) {
                self::assertStringNotContainsString($darkNeedle, $nav, "{$feature} must own only its dark control.");
                continue;
            }

            self::assertStringContainsString($needle, $nav, "{$feature} must remain visible while {$darkFeature} is dark.");
        }
    }

    public function test_appeals_is_only_a_working_unowned_destination_in_this_slice(): void
    {
        $this->actingAs($this->makeUser());

        $settings = $this->get('/settings/account');
        self::assertStringContainsString('href="/appeals"', $this->settingsNav($settings));

        $appeals = $this->get('/appeals');
        $this->assertStatus(200, $appeals);
        self::assertStringContainsString('aria-label="Settings sections"', $appeals->body());
        self::assertStringNotContainsString('aria-current="page"', $this->settingsNav($appeals));
        self::assertStringNotContainsString('<h1>Account settings</h1>', $appeals->body());
    }

    /** @return iterable<string,array{string,array<string,string>,string,string}> */
    public static function htmlValidationActions(): iterable
    {
        yield 'profile' => [
            '/settings/account',
            ['display_name' => 'Preserved name', 'website' => 'not-a-url'],
            'profile',
            'Enter a valid http(s) URL.',
        ];
        yield 'password' => [
            '/settings/security',
            ['current_password' => 'wrong', 'new_password' => 'newpassword456', 'new_password_confirm' => 'newpassword456'],
            'security',
            'Your current password is incorrect.',
        ];
        yield 'totp enroll' => [
            '/settings/security/totp/enroll',
            ['current_password' => 'wrong'],
            'security',
            'Your current password is incorrect.',
        ];
        yield 'totp confirm without pending enrollment' => [
            '/settings/security/totp/confirm',
            ['current_password' => 'password123', 'totp_code' => '000000'],
            'security',
            'Not enabled.',
        ];
        yield 'totp recovery rotation without MFA' => [
            '/settings/security/totp/recovery/rotate',
            ['current_password' => 'password123'],
            'security',
            'Enable two-factor authentication before rotating recovery codes.',
        ];
        yield 'totp disable' => [
            '/settings/security/totp/disable',
            ['current_password' => 'wrong', 'disable_code' => '000000'],
            'security',
            'Your current password is incorrect.',
        ];
        yield 'deactivate' => [
            '/settings/account/deactivate',
            ['current_password' => 'wrong'],
            'account',
            'Your current password is incorrect.',
        ];
        yield 'reactivate active account' => [
            '/settings/account/reactivate',
            [],
            'account',
            'Only a self-deactivated account without a pending deletion or site restriction can be reactivated.',
        ];
        yield 'request deletion' => [
            '/settings/account/delete/request',
            ['current_password' => 'wrong'],
            'account',
            'Your current password is incorrect.',
        ];
        yield 'cancel nonexistent deletion' => [
            '/settings/account/delete/cancel',
            [],
            'account',
            'No pending deletion request is available to cancel.',
        ];
    }

    /** @param array<string,string> $input */
    #[DataProvider('htmlValidationActions')]
    public function test_every_existing_html_422_keeps_its_explicit_active_owner(
        string $path,
        array $input,
        string $active,
        string $visibleContext,
    ): void {
        $this->actingAs($this->makeUser(['password' => 'password123']));

        $response = $this->post($path, $input);
        $this->assertStatus(422, $response);
        $this->assertActiveRail($response, $active);
        self::assertStringContainsString($visibleContext, $response->body());
        if ($path === '/settings/account') {
            self::assertStringContainsString('value="Preserved name"', $response->body());
            self::assertStringContainsString('value="not-a-url"', $response->body());
        }
    }

    public function test_passkey_rename_422_keeps_security_active_and_shows_the_error(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $id = $this->makePasskey((int) $user['id'], 'Desk key');

        $response = $this->post("/settings/security/passkeys/{$id}/rename", ['nickname' => '']);
        $this->assertStatus(422, $response);
        $this->assertActiveRail($response, 'security');
        self::assertStringContainsString('Pick a name between 1 and 120 characters.', $response->body());
    }

    public function test_passkey_revoke_422_keeps_security_active_and_shows_the_error(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $id = $this->makePasskey((int) $user['id'], 'Desk key');

        $response = $this->post("/settings/security/passkeys/{$id}/revoke", []);
        $this->assertStatus(422, $response);
        $this->assertActiveRail($response, 'security');
        self::assertStringContainsString('Confirm this change with your password or a passkey.', $response->body());
    }

    private function assertActiveRail(Response $response, string $active): void
    {
        $nav = $this->settingsNav($response);
        self::assertSame(1, substr_count($nav, 'aria-current="page"'), 'Exactly one account rail item must be current.');
        self::assertSame(1, substr_count($nav, ' is-active'), 'Exactly one account rail item must have the active class.');
        self::assertMatchesRegularExpression(
            '~<a\b(?=[^>]*data-settings-key="' . preg_quote($active, '~') . '")(?=[^>]*class="[^"]*\bis-active\b)(?=[^>]*aria-current="page")[^>]*>~',
            $nav,
        );
    }

    private function settingsNav(Response $response): string
    {
        self::assertSame(
            1,
            preg_match('~<nav\b[^>]*class="[^"]*settings-rail[^"]*"[^>]*aria-label="Settings sections"[^>]*>(?<nav>.*?)</nav>~s', $response->body(), $match),
            'The grouped account settings navigation is missing.',
        );

        return $match['nav'];
    }

    public function test_mobile_chooser_reuses_the_filtered_desktop_navigation_and_is_closed(): void
    {
        $this->actingAs($this->makeUser());
        $this->setFeatureFlags(['drafts' => false, 'oauth' => false, 'account_lifecycle' => false, 'appeals' => false]);
        $response = $this->get('/settings/security');
        self::assertSame(1, preg_match('~<details class="settings-mobile-nav" data-settings-mobile-nav>\s*<summary>Settings: Security</summary>\s*<nav[^>]+>(.*?)</nav>~s', $response->body(), $matches));
        self::assertSame(trim($this->settingsNav($response)), trim($matches[1]));
        self::assertStringNotContainsString('href="/appeals"', $matches[1]);
        self::assertStringNotContainsString('href="/drafts"', $matches[1]);
        self::assertSame(1, substr_count($matches[1], 'aria-current="page"'));
    }

    /** @return list<list<string>> */
    private function settingsGroupKeys(string $nav): array
    {
        self::assertSame(
            3,
            preg_match_all('~<div class="settings-rail-group">(?<group>.*?)</div>~s', $nav, $groups),
            'The settings rail must render exactly three groups.',
        );

        return array_map(static function (string $group): array {
            preg_match_all('~data-settings-key="([^"]+)"~', $group, $keys);

            return $keys[1];
        }, $groups['group']);
    }

    /** @param array<string,bool> $flags */
    private function setFeatureFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    private function makePasskey(int $userId, string $nickname): int
    {
        return (new WebAuthnCredentialRepository($this->db))->create([
            'user_id' => $userId,
            'credential_id' => random_bytes(32),
            'public_key' => "\xa1\x01\x02",
            'sign_count' => 0,
            'aaguid' => str_repeat("\0", 16),
            'transports' => 'internal',
            'is_discoverable' => 1,
            'is_backup_eligible' => 1,
            'is_backed_up' => 1,
            'nickname' => $nickname,
        ]);
    }
}
