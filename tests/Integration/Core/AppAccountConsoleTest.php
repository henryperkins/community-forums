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
        self::assertSame(1, substr_count($body, 'aria-label="Settings sections"'));
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
            'Only deactivated accounts can be reactivated here.',
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
            'No pending deletion request was found.',
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
