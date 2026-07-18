<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-07: operator branding. Name/colours/theme are applied through the shell and
 * the dynamic /brand.css (the strict CSP forbids inline styles), invalid input is
 * rejected, reset restores the safe defaults, and only admins can change it.
 */
final class AppBrandingThemeTest extends TestCase
{
    public function test_admin_branding_recolours_via_brand_css(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin']);
        $this->actingAs($admin);

        $res = $this->post('/admin/branding', [
            'site_name' => 'Lakeside Forum',
            'color_primary' => '#123456',
            'color_accent' => '#abcdef',
            'theme_default' => 'dark',
        ]);
        $this->assertRedirect($res, '/admin/branding');

        // The dynamic stylesheet maps the primary colour onto --accent and the
        // accent colour onto --accent-2.
        $css = $this->get('/brand.css');
        $this->assertStatus(200, $css);
        self::assertStringContainsString('text/css', (string) $css->getHeader('content-type'));
        $this->assertSeeText($css, '--accent:#123456');
        $this->assertSeeText($css, '--accent-contrast:#ffffff');
        $this->assertSeeText($css, '--accent-2:#abcdef');
        $this->assertSeeText($css, '--brand-accent-contrast:#0f1218');

        // Regression guard: both brand tokens must be CONSUMED by the stylesheet,
        // otherwise the colour picker is inert (the accent previously emitted a
        // --brand-accent token that no rule referenced).
        $appCss = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');
        self::assertStringContainsString('var(--accent-2)', $appCss, 'the brand accent colour must drive at least one rule');
        self::assertStringContainsString('var(--accent)', $appCss);

        // The shell now links it and shows the new name; the signed-out default
        // theme is dark.
        $home = $this->get('/');
        $this->assertSeeText($home, '/brand.css?v=');
        $this->assertSeeText($home, 'Lakeside Forum');
    }

    public function test_name_change_retires_the_placeholder(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin2']);
        $this->actingAs($admin);
        $this->post('/admin/branding', ['site_name' => 'CoolCommunity', 'theme_default' => 'system']);

        $home = $this->get('/');
        $this->assertSeeText($home, 'CoolCommunity');
        $this->assertSeeText($home, '<title>CoolCommunity</title>');
    }

    public function test_reset_restores_defaults(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin3']);
        $this->actingAs($admin);
        $this->post('/admin/branding', ['site_name' => 'Temp', 'color_primary' => '#222222', 'theme_default' => 'system']);
        $this->assertSeeText($this->get('/brand.css'), '#222222');

        $this->post('/admin/branding', ['reset' => '1', 'reset_confirm' => 'RESET']);
        // Colours cleared → brand.css carries no overrides and the shell stops linking it.
        $this->assertDontSeeText($this->get('/brand.css'), '#222222');
        $this->assertDontSeeText($this->get('/'), '/brand.css');
    }

    public function test_invalid_colour_is_rejected(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin4']);
        $this->actingAs($admin);
        $res = $this->post('/admin/branding', ['site_name' => 'X', 'color_primary' => 'red', 'theme_default' => 'system']);
        $this->assertStatus(422, $res);
    }

    public function test_low_contrast_brand_colour_is_rejected(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin6']);
        $this->actingAs($admin);
        $res = $this->post('/admin/branding', ['site_name' => 'X', 'color_primary' => '#7a7a7a', 'theme_default' => 'system']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'readable button text');
    }

    public function test_non_admin_cannot_change_branding(): void
    {
        $this->makeAdmin(); // satisfy setup gate
        $user = $this->makeUser(['username' => 'plebeian']);
        $this->actingAs($user);
        $this->assertStatus(403, $this->get('/admin/branding'));
        $this->assertStatus(403, $this->post('/admin/branding', ['site_name' => 'Hijack', 'theme_default' => 'system']));
    }

    public function test_branding_subsystem_can_be_disabled(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin5']);
        $this->actingAs($admin);
        // Set a colour while enabled, then disable the subsystem.
        $this->post('/admin/branding', ['site_name' => 'Soon Off', 'color_primary' => '#654321', 'theme_default' => 'system']);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['branding' => false]);

        // The admin form/update 404 and /brand.css emits no stored colours.
        $this->assertStatus(404, $this->get('/admin/branding'));
        $this->assertStatus(404, $this->post('/admin/branding', ['site_name' => 'Nope', 'theme_default' => 'system']));
        $this->assertDontSeeText($this->get('/brand.css'), '#654321');
    }

    public function test_custom_css_requires_flag_and_confirmation_before_emitting(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin7']);
        $this->actingAs($admin);
        $settings = new \App\Repository\SettingRepository($this->db);

        $settings->set('features', ['custom_css' => true]);
        $res = $this->post('/admin/branding', [
            'site_name' => 'CSS Forum',
            'theme_default' => 'system',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css' => '.brand-name{letter-spacing:.04em;}',
        ]);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Confirm that custom CSS can affect the whole site.');

        $res = $this->post('/admin/branding', [
            'site_name' => 'CSS Forum',
            'theme_default' => 'system',
            'theme_preset' => 'retro',
            'custom_css_enabled' => '1',
            'custom_css_ack' => '1',
            'custom_css' => '.brand-name{letter-spacing:.04em;}',
        ]);
        $this->assertRedirect($res, '/admin/branding');

        $css = $this->get('/brand.css');
        $this->assertSeeText($css, '--surface:#fff7dc');
        $this->assertSeeText($css, '.brand-name{letter-spacing:.04em;}');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'branding_update' AND actor_id = ?", [$admin['id']]));

        $settings->set('features', ['custom_css' => false]);
        $css = $this->get('/brand.css');
        $this->assertSeeText($css, '--surface:#fff7dc');
        $this->assertDontSeeText($css, '.brand-name{letter-spacing:.04em;}');
    }

    public function test_custom_css_rejects_unsafe_constructs(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandadmin8']);
        $this->actingAs($admin);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['custom_css' => true]);

        $res = $this->post('/admin/branding', [
            'site_name' => 'Unsafe CSS',
            'theme_default' => 'system',
            'custom_css_enabled' => '1',
            'custom_css_ack' => '1',
            'custom_css' => '@import url("https://evil.example/x.css");',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Custom CSS cannot import external stylesheets.');
    }

    public function test_dark_default_theme_uses_dark_logo_variant(): void
    {
        $this->makeAdmin();
        $settings = new \App\Repository\SettingRepository($this->db);
        $settings->set('site_name', 'Variant Forum');
        $settings->set('brand_logo_path', '/media/base-logo');
        $settings->set('brand_logo_light_path', '/media/light-logo');
        $settings->set('brand_logo_dark_path', '/media/dark-logo');
        $settings->set('brand_theme_default', 'dark');
        $settings->set('brand_version', 'variant1');

        $home = $this->get('/');

        $this->assertSeeText($home, '/media/dark-logo');
        $this->assertDontSeeText($home, '/media/base-logo');
        $this->assertDontSeeText($home, '/media/light-logo');
    }
}
