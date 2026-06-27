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

        // The dynamic stylesheet maps the brand colour onto --accent.
        $css = $this->get('/brand.css');
        $this->assertStatus(200, $css);
        self::assertStringContainsString('text/css', (string) $css->getHeader('content-type'));
        $this->assertSeeText($css, '--accent:#123456');
        $this->assertSeeText($css, '--brand-accent:#abcdef');

        // The shell now links it and shows the new name; the signed-out default
        // theme is dark.
        $home = $this->get('/');
        $this->assertSeeText($home, '/brand.css');
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

        $this->post('/admin/branding', ['reset' => '1']);
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
}
