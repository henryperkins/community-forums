<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Support\BrandIdentity;
use Tests\Support\TestCase;

/**
 * ADR 0054 identity increment: one community identity on every entry point.
 * Logos resolve once and render on the member chrome, the console bar and the
 * auth stage; System lets the device choose without script; operator text has
 * defaults that clearing restores; members' appearance choices picture the
 * resolved theme.
 */
final class AppBrandIdentityTest extends TestCase
{
    private const AUTH_PAGES = ['/login', '/register', '/forgot', '/reset?token=expired', '/verify'];

    public function test_every_auth_screen_shows_the_community_logo_and_falls_back_to_the_house_mark(): void
    {
        $this->makeAdmin();
        $settings = new SettingRepository($this->db);
        $settings->set('site_name', 'Harbor Commons');

        foreach (self::AUTH_PAGES as $page) {
            $body = $this->authPage($page);
            self::assertStringContainsString('class="auth-brand-star"', $body, $page);
            self::assertStringContainsString('<span class="auth-brand-name">Harbor Commons</span>', $body, $page);
            self::assertStringNotContainsString('auth-brand-logo', $body, $page);
        }

        $settings->set('brand_logo_path', '/media/41');
        foreach (self::AUTH_PAGES as $page) {
            $body = $this->authPage($page);
            self::assertStringContainsString('<a class="auth-brand auth-brand-has-logo" href="/" aria-label="Harbor Commons">', $body, $page);
            self::assertStringContainsString('<img class="auth-brand-logo" src="/media/41" alt="" height="40">', $body, $page);
            self::assertStringNotContainsString('class="auth-brand-star"', $body, $page);
        }
        // The public chrome shows the same identity.
        self::assertStringContainsString('<img class="brand-logo" src="/media/41" alt="" height="28">', $this->get('/')->body());

        // A removed logo returns every surface to the house mark at once.
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'site_name' => 'Harbor Commons', 'theme_default' => 'system', 'remove_logo' => '1',
        ])), '/admin/branding');
        self::assertStringContainsString('class="admin-bar-star"', $this->get('/admin/branding')->body());
        $this->logoutClient();
        self::assertSame('', $settings->getString('brand_logo_path'));
        self::assertStringContainsString('class="auth-brand-star"', $this->authPage('/login'));
        self::assertStringContainsString('class="forum-bar-mark"', $this->get('/')->body());

        // Branding switched off keeps the built-in identity even with a saved logo.
        $settings->set('brand_logo_path', '/media/41');
        $settings->set('features', ['branding' => false]);
        self::assertStringContainsString('class="auth-brand-star"', $this->authPage('/login'));
        self::assertStringNotContainsString('/media/41', $this->get('/')->body());
    }

    public function test_system_mode_lets_the_device_choose_the_logo_without_script(): void
    {
        $this->makeAdmin();
        $settings = new SettingRepository($this->db);
        $settings->set('site_name', 'Variant Commons');
        $settings->set('brand_theme_default', 'system');
        $settings->set('brand_logo_light_path', '/media/11');
        $settings->set('brand_logo_dark_path', '/media/12');

        $picture = '<picture class="brand-picture"><source media="(prefers-color-scheme: dark)" srcset="/media/12"><img class="brand-logo" src="/media/11" alt="" height="28"></picture>';
        self::assertStringContainsString($picture, $this->get('/')->body());
        self::assertStringContainsString(
            '<picture class="brand-picture"><source media="(prefers-color-scheme: dark)" srcset="/media/12"><img class="auth-brand-logo" src="/media/11" alt="" height="40"></picture>',
            $this->authPage('/login'),
        );

        // The base logo fills whichever variant is missing.
        $settings->set('brand_logo_path', '/media/10');
        $settings->set('brand_logo_dark_path', '');
        $body = $this->get('/')->body();
        self::assertStringContainsString('<source media="(prefers-color-scheme: dark)" srcset="/media/10"><img class="brand-logo" src="/media/11"', $body);

        // One shared image needs no choice at all.
        $settings->set('brand_logo_light_path', '');
        $body = $this->get('/')->body();
        self::assertStringContainsString('<img class="brand-logo" src="/media/10" alt="" height="28">', $body);
        self::assertStringNotContainsString('<picture', $body);

        // Only a dark variant: the device's dark mode shows it and light mode
        // keeps the house mark, both decided by the stylesheet's media rule.
        $settings->set('brand_logo_path', '');
        $settings->set('brand_logo_dark_path', '/media/12');
        $body = $this->get('/')->body();
        self::assertMatchesRegularExpression('#<span class="brand-when-light"><svg class="forum-bar-mark"[^>]*>.*?</svg>\s*<span class="forum-bar-wordmark">Variant Commons</span></span><span class="brand-when-dark"><img class="brand-logo" src="/media/12" alt="" height="28"></span>#s', $body);
        $login = $this->authPage('/login');
        self::assertMatchesRegularExpression('#<span class="brand-when-light"><svg class="auth-brand-star".*?<span class="auth-brand-name">Variant Commons</span></span><span class="brand-when-dark"><img class="auth-brand-logo" src="/media/12"#s', $login);

        $css = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
        self::assertMatchesRegularExpression('/@media not all and \(prefers-color-scheme: dark\) \{\s*:root\[data-theme="system"\] \.brand-when-dark,/', $css);
        self::assertMatchesRegularExpression('/@media \(prefers-color-scheme: dark\) \{\s*:root\[data-theme="system"\] \.brand-when-light,/', $css);
        self::assertMatchesRegularExpression('/\.brand-picture, \.brand-when-light, \.brand-when-dark \{ display: contents; \}/', $css);
    }

    public function test_an_explicit_member_mode_renders_that_modes_logo_only(): void
    {
        $this->makeAdmin();
        $settings = new SettingRepository($this->db);
        $settings->set('brand_logo_path', '/media/20');
        $settings->set('brand_logo_light_path', '/media/21');
        $settings->set('brand_logo_dark_path', '/media/22');
        $member = $this->makeUser();
        $this->actingAs($member);
        $preferences = new UserPreferenceRepository($this->db);

        foreach (['light' => '/media/21', 'dark' => '/media/22'] as $mode => $logo) {
            $preferences->merge((int) $member['id'], ['theme' => $mode]);
            $body = $this->get('/')->body();
            self::assertStringContainsString('<img class="brand-logo" src="' . $logo . '" alt="" height="28">', $body);
            self::assertStringNotContainsString('<picture', $body);
            self::assertStringNotContainsString('/media/20', $body);
        }
        $preferences->merge((int) $member['id'], ['theme' => 'system']);
        self::assertStringContainsString('srcset="/media/22"><img class="brand-logo" src="/media/21"', $this->get('/')->body());
    }

    public function test_community_text_reaches_its_public_surfaces_and_clearing_restores_defaults(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $category = $this->makeCategory();
        $this->makeBoard($category, ['slug' => 'harbor-board', 'name' => 'Harbor board', 'description' => 'Board-specific summary.']);

        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'site_name' => 'Harbor Commons',
            'theme_default' => 'system',
            'site_tagline' => '  Boats,   tides and   people  ',
            'site_description' => "A friendly harbour forum\nfor sailors.",
            'directory_heading' => 'Welcome aboard',
            'directory_intro' => "Pick a board.\r\n\r\n\r\nSay hello in General.",
        ])), '/admin/branding');
        $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1"), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['brand_tagline', 'brand_description', 'brand_directory_heading', 'brand_directory_intro'], $audit['changed_text']);
        $this->logoutClient();

        $home = $this->get('/')->body();
        self::assertStringContainsString('<p class="forum-directory__tagline">Boats, tides and people</p>', $home);
        self::assertStringContainsString('<h1>Welcome aboard</h1>', $home);
        self::assertStringContainsString('<p>Pick a board.</p>', $home);
        self::assertStringContainsString('<p>Say hello in General. Follow your topics in the <a href="/inbox">inbox</a>.</p>', $home);
        self::assertStringNotContainsString(BrandIdentity::DEFAULT_DIRECTORY_HEADING, $home);
        self::assertStringContainsString('<meta name="description" content="A friendly harbour forum for sailors.">', $home);
        self::assertStringContainsString('<meta property="og:description" content="A friendly harbour forum for sailors.">', $home);
        self::assertStringContainsString('<p class="auth-tagline">Boats, tides and people</p>', $this->authPage('/login'));
        // A page with its own description keeps it.
        self::assertStringContainsString('<meta name="description" content="Board-specific summary.">', $this->get('/c/harbor-board')->body());

        $this->actingAs($admin);
        $form = $this->get('/admin/branding')->body();
        self::assertStringContainsString('value="Boats, tides and people"', $form);
        self::assertStringContainsString(">Pick a board.\n\nSay hello in General.</textarea>", $form);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'site_name' => 'Harbor Commons', 'theme_default' => 'system',
            'site_tagline' => '', 'site_description' => '', 'directory_heading' => '', 'directory_intro' => '',
        ])), '/admin/branding');
        $this->logoutClient();

        $home = $this->get('/')->body();
        self::assertStringNotContainsString('forum-directory__tagline', $home);
        self::assertStringContainsString('<h1>Community boards</h1>', $home);
        self::assertStringContainsString('<p>Browse a board to read topics or start a discussion. Follow your topics in the <a href="/inbox">inbox</a>.</p>', $home);
        self::assertStringContainsString('<meta name="description" content="Harbor Commons — a community forum.">', $home);
        self::assertStringNotContainsString('auth-tagline', $this->authPage('/login'));
    }

    public function test_invalid_community_text_returns_the_whole_draft_without_saving(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new SettingRepository($this->db);
        $settings->set('site_name', 'Saved Commons');
        $settings->set('brand_directory_heading', 'Saved heading');

        $tooLong = str_repeat('a', 81);
        $cases = [
            ['directory_heading' => $tooLong, 'field' => 'directory_heading', 'message' => 'Welcome heading must be 80 characters or fewer (currently 81).'],
            ['site_tagline' => "Bell\u{7}ringer", 'field' => 'site_tagline', 'message' => 'Tagline cannot contain control characters.'],
            ['site_description' => "Hidden \u{202E}reversal", 'field' => 'site_description', 'message' => 'Community description cannot contain control characters.'],
            ['directory_intro' => str_repeat('b', 601), 'field' => 'directory_intro', 'message' => 'Welcome introduction must be 600 characters or fewer (currently 601).'],
        ];
        foreach ($cases as $case) {
            $field = $case['field'];
            $message = $case['message'];
            unset($case['field'], $case['message']);
            $response = $this->post('/admin/branding', $case + [
                'site_name' => 'Unsaved Commons',
                'theme_default' => 'dark',
                'site_tagline' => 'Unsaved tagline',
                'directory_intro' => 'Unsaved introduction',
                'color_primary' => '#2e4a3a',
            ]);
            $this->assertStatus(422, $response);
            $body = $response->body();
            self::assertStringContainsString(htmlspecialchars($message, ENT_QUOTES), $body);
            self::assertMatchesRegularExpression('/name="' . $field . '"[^>]*aria-invalid="true"/', $body);
            self::assertStringContainsString('value="Unsaved Commons"', $body);
            self::assertStringContainsString('value="#2e4a3a"', $body);
            if ($field !== 'site_tagline') {
                self::assertStringContainsString('value="Unsaved tagline"', $body);
            }
            if ($field !== 'directory_intro') {
                self::assertStringContainsString('>Unsaved introduction</textarea>', $body);
            }
            self::assertSame('Saved Commons', $settings->getString('site_name'));
            self::assertSame('Saved heading', $settings->getString('brand_directory_heading'));
            self::assertSame('', $settings->getString('brand_tagline'));
        }

        // Structured text only: an array value is refused, not coerced.
        $response = $this->post('/admin/branding', $this->fresh(['site_name' => 'Unsaved Commons', 'directory_heading' => ['x']]));
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Welcome heading must be text.');
        self::assertSame('Saved heading', $settings->getString('brand_directory_heading'));
    }

    public function test_community_text_is_escaped_and_damaged_rows_fall_back(): void
    {
        $this->makeAdmin();
        $settings = new SettingRepository($this->db);
        $settings->set('brand_tagline', '<script>alert(1)</script>');
        $settings->set('brand_directory_heading', str_repeat('x', 200));
        $settings->set('brand_description', ['not' => 'text']);

        $home = $this->get('/')->body();
        self::assertStringContainsString('<p class="forum-directory__tagline">&lt;script&gt;alert(1)&lt;/script&gt;</p>', $home);
        self::assertStringNotContainsString('<script>alert(1)</script>', $home);
        self::assertStringContainsString('<h1>Community boards</h1>', $home);
        self::assertStringContainsString('content="RetroBoards — a community forum."', $home);
    }

    public function test_a_form_without_the_text_fields_keeps_saved_text(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new SettingRepository($this->db);
        $settings->set('brand_tagline', 'Saved tagline');
        $settings->set('brand_directory_heading', 'Saved heading');

        // A branding page opened before these fields existed posts none of them.
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['site_name' => 'Older form', 'theme_default' => 'system'])), '/admin/branding');

        self::assertSame('Saved tagline', $settings->getString('brand_tagline'));
        self::assertSame('Saved heading', $settings->getString('brand_directory_heading'));
        $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1"), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $audit['changed_text']);

        // Present but empty is the deliberate clear.
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['site_name' => 'Older form', 'theme_default' => 'system', 'site_tagline' => ''])), '/admin/branding');
        self::assertSame('', $settings->getString('brand_tagline'));
        self::assertSame('Saved heading', $settings->getString('brand_directory_heading'));
    }

    public function test_reset_keeps_community_text_with_the_name(): void
    {
        $this->actingAs($this->makeAdmin());
        $settings = new SettingRepository($this->db);
        $settings->set('brand_tagline', 'Kept tagline');
        $settings->set('brand_directory_intro', 'Kept introduction');
        $settings->set('brand_logo_path', '/media/9');

        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['reset' => '1', 'reset_confirm' => 'RESET'])), '/admin/branding');

        self::assertSame('Kept tagline', $settings->getString('brand_tagline'));
        self::assertSame('Kept introduction', $settings->getString('brand_directory_intro'));
        self::assertSame('', $settings->getString('brand_logo_path'));
        $this->assertSeeText($this->get('/admin/branding'), 'Keeps the community name, community text and active package theme.');
    }

    public function test_member_appearance_choices_picture_the_resolved_theme(): void
    {
        $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($member);

        $page = $this->get('/settings/appearance');
        $this->assertStatus(200, $page);
        $body = $page->body();
        foreach (['Light' => 'light', 'Dark' => 'dark'] as $label => $mode) {
            self::assertStringContainsString('<span class="choice-card-title">' . $label . '</span><span class="choice-card-desc">Forum · ' . $mode . ' backgrounds</span>', $body);
        }
        self::assertStringContainsString('<span class="choice-card-title">System</span>', $body);
        self::assertStringNotContainsString('Parchment', $body);
        self::assertStringNotContainsString('Twilight', $body);
        self::assertSame(4, substr_count($body, 'class="theme-thumb-mode" data-thumb-mode='));
        self::assertStringContainsString('<link rel="stylesheet" href="/brand.css?v=', $body);
        // The member's own appearance preferences are untouched by the preview.
        self::assertStringContainsString('name="density" value="comfortable" checked', $body);

        $classic = $this->get('/brand.css')->body();
        self::assertStringContainsString('.theme-thumb [data-thumb-mode="light"]{--thumb-page:#ffffff;--thumb-panel:#ffffff;', $classic);
        self::assertStringContainsString('.theme-thumb [data-thumb-mode="dark"]{--thumb-page:#111827;--thumb-panel:#1f2937;', $classic);

        $settings = new SettingRepository($this->db);
        $settings->set('brand_theme_preset', 'retro');
        $settings->set('brand_color_primary', '#8f3d12');
        $settings->set('brand_color_accent', '#b0611c');
        $settings->set('brand_version', 'thumbs');
        $retro = $this->get('/brand.css')->body();
        self::assertStringContainsString('.theme-thumb [data-thumb-mode="light"]{--thumb-page:#f6e8c4;--thumb-panel:#fff7dc;', $retro);
        self::assertStringContainsString('.theme-thumb [data-thumb-mode="dark"]{--thumb-page:#211b14;--thumb-panel:#30251a;', $retro);
        self::assertSame(2, substr_count($retro, '--thumb-action:#8f3d12;'));
        self::assertSame(2, substr_count($retro, '--thumb-accent:#b0611c'));
        $this->assertSeeText($this->get('/settings/appearance'), 'Retro · dark backgrounds');
    }

    private function authPage(string $path): string
    {
        [$route, $query] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($query, $params);
        $response = $this->get($route, $params);
        self::assertContains($response->status(), [200, 400], $path);
        self::assertStringContainsString('class="auth-stage"', $response->body(), $path);
        return $response->body();
    }

    /** A form opened now carries the live branding revision (ADR 0054). */
    private function fresh(array $values): array
    {
        $revision = (new SettingRepository($this->db))->get('brand_revision', 0);
        return $values + ['base_revision' => (string) (is_int($revision) ? $revision : 0)];
    }
}
