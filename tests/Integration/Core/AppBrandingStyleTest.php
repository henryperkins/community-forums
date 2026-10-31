<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * ADR 0054 increment 4: curated style presets. A preset is a complete
 * combination (surfaces, sidebar, heading type, corners) stamped on <html> as
 * fixed vocabularies; individual overrides replace one part; the density
 * default applies only where a member has not chosen. Unstyled installs render
 * exactly as before.
 */
final class AppBrandingStyleTest extends TestCase
{
    public function test_an_unstyled_install_stamps_nothing(): void
    {
        $this->makeAdmin();
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringNotContainsString('data-rail', $html);
        self::assertStringNotContainsString('data-type-pairing', $html);
        self::assertStringNotContainsString('data-corners', $html);
        self::assertStringContainsString('data-density="comfortable"', $html);
    }

    public function test_a_preset_stamps_its_combination_and_publishes_its_palette(): void
    {
        $this->makeAdmin();
        $this->settings(['brand_theme_preset' => 'ledger']);
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringContainsString('data-rail="flush"', $html);
        self::assertStringContainsString('data-type-pairing="book"', $html);
        self::assertStringContainsString('data-corners="crisp"', $html);

        $css = $this->get('/brand.css', ['v' => 'ledger'])->body();
        self::assertStringContainsString('--surface-page:#eef1ed', $this->rule($css, ':root'));
        self::assertStringContainsString('--surface-page:#131a1f', $this->rule($css, '[data-theme="dark"]'));
        // The sample and the member thumbnails carry the preset too.
        self::assertMatchesRegularExpression('/\.brand-preview\[data-brand-preset="vellum"\]\[data-brand-theme="dark"\]\{[^}]*--surface-page:#181614;/', $css);
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="light"\]\{--thumb-page:#eef1ed;/', $css);
        // Members' appearance choices name the serving preset.
        $this->actingAs($this->makeUser());
        $this->assertSeeText($this->get('/settings/appearance'), 'Ledger · light backgrounds');
    }

    public function test_an_override_replaces_one_part_and_the_rest_follow_the_preset(): void
    {
        $this->makeAdmin();
        $this->settings(['brand_theme_preset' => 'vellum', 'brand_corners' => 'crisp', 'brand_sidebar_style' => 'sunken']);
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringContainsString('data-corners="crisp"', $html);
        self::assertStringContainsString('data-type-pairing="lapidary"', $html);
        // Sunken is the built-in rail, so nothing needs stamping for it.
        self::assertStringNotContainsString('data-rail', $html);

        // Classic with overrides: only the overridden parts are stamped.
        $this->settings(['brand_theme_preset' => 'classic', 'brand_corners' => '', 'brand_sidebar_style' => 'raised', 'brand_type_pairing' => 'book']);
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringContainsString('data-rail="raised"', $html);
        self::assertStringContainsString('data-type-pairing="book"', $html);
        self::assertStringNotContainsString('data-corners', $html);
    }

    public function test_the_branding_pages_keep_the_built_in_style_and_the_sample_shows_it(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->settings(['brand_theme_preset' => 'vellum', 'brand_type_pairing' => 'book']);
        $page = $this->get('/admin/branding');
        $this->assertStatus(200, $page);
        $html = $this->htmlTag($page->body());
        self::assertStringNotContainsString('data-rail', $html);
        self::assertStringNotContainsString('data-type-pairing', $html);
        self::assertSame(1, preg_match('/<div class="brand-preview brand-preview-shell"[^>]*>/', $page->body(), $sample));
        self::assertStringContainsString('data-rail="raised" data-type-pairing="book" data-corners="soft"', $sample[0]);
        self::assertStringContainsString('<option value="" selected data-brand-style-preset-option>Preset — Raised panel</option>', $page->body());
        self::assertStringContainsString('<option value="vellum" selected>Vellum</option>', $page->body());
        // History is a recovery page too.
        self::assertStringNotContainsString('data-rail', $this->htmlTag($this->get('/admin/branding/history')->body()));
    }

    public function test_publishing_style_is_a_revision_and_the_appearance_reset_clears_it(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'theme_preset' => 'vellum', 'sidebar_style' => 'flush', 'type_pairing' => '', 'corners' => 'crisp', 'density_default' => 'compact',
        ])), '/admin/branding');
        self::assertSame('vellum', $this->setting('brand_theme_preset'));
        self::assertSame('flush', $this->setting('brand_sidebar_style'));
        self::assertSame('', $this->setting('brand_type_pairing'));
        self::assertSame('crisp', $this->setting('brand_corners'));
        self::assertSame('compact', $this->setting('brand_density_default'));
        $history = $this->get('/admin/branding/history')->body();
        // The history row names the changed settings (the first four, then a count).
        self::assertStringContainsString('Community name, Style preset, Sidebar, Corners and 1 more', $history);

        $this->assertRedirect($this->post('/admin/branding/reset/appearance'), '/admin/branding');
        foreach (['brand_sidebar_style', 'brand_type_pairing', 'brand_corners'] as $key) {
            self::assertSame('', $this->setting($key), $key);
        }
        self::assertContains($this->setting('brand_theme_preset'), ['', 'classic']);
        self::assertContains($this->setting('brand_density_default'), ['', 'comfortable']);
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringNotContainsString('data-rail', $html);
        self::assertStringContainsString('data-density="comfortable"', $html);
    }

    public function test_the_full_reset_also_clears_mode_colours_and_style(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->settings([
            'brand_color_primary_dark' => '#9fc3a8', 'brand_color_accent_light' => '#8f3d12',
            'brand_theme_preset' => 'ledger', 'brand_corners' => 'soft', 'brand_density_default' => 'compact',
            'brand_tagline' => 'Kept tagline',
        ]);
        $this->assertRedirect($this->post('/admin/branding', ['reset' => '1', 'reset_confirm' => 'RESET']), '/admin/branding');
        foreach (['brand_color_primary_dark', 'brand_color_accent_light', 'brand_corners'] as $key) {
            self::assertSame('', $this->setting($key), $key);
        }
        self::assertContains($this->setting('brand_theme_preset'), ['', 'classic']);
        self::assertContains($this->setting('brand_density_default'), ['', 'comfortable']);
        self::assertSame('Kept tagline', $this->setting('brand_tagline'));
        self::assertStringNotContainsString('data-corners', $this->htmlTag($this->get('/')->body()));
    }

    public function test_unknown_values_fall_back_and_an_older_form_keeps_the_style(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'theme_preset' => 'neon', 'type_pairing' => 'comic', 'corners' => 'round', 'sidebar_style' => 'floating', 'density_default' => 'spacious',
        ])), '/admin/branding');
        self::assertSame('classic', $this->setting('brand_theme_preset'));
        foreach (['brand_sidebar_style', 'brand_type_pairing', 'brand_corners'] as $key) {
            self::assertSame('', $this->setting($key), $key);
        }
        self::assertSame('comfortable', $this->setting('brand_density_default'));

        $this->settings(['brand_corners' => 'soft', 'brand_density_default' => 'compact']);
        // A branding page opened before these fields existed posts none of them.
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['site_name' => 'Older form'])), '/admin/branding');
        self::assertSame('soft', $this->setting('brand_corners'));
        self::assertSame('compact', $this->setting('brand_density_default'));
    }

    public function test_the_density_default_never_overrides_a_member_choice(): void
    {
        $this->makeAdmin();
        $this->settings(['brand_density_default' => 'compact']);
        self::assertStringContainsString('data-density="compact"', $this->htmlTag($this->get('/')->body()));

        $undecided = $this->makeUser(['username' => 'undecided']);
        $this->actingAs($undecided);
        self::assertStringContainsString('data-density="compact"', $this->htmlTag($this->get('/')->body()));
        $appearance = $this->get('/settings/appearance')->body();
        self::assertMatchesRegularExpression('/<input type="radio" name="density" value="compact" checked>/', $appearance);

        $decided = $this->makeUser(['username' => 'decided']);
        (new UserPreferenceRepository($this->db))->merge((int) $decided['id'], ['density' => 'comfortable']);
        $this->actingAs($decided);
        self::assertStringContainsString('data-density="comfortable"', $this->htmlTag($this->get('/')->body()));
    }

    public function test_turning_branding_off_returns_the_built_in_style(): void
    {
        $this->makeAdmin();
        $this->settings(['brand_theme_preset' => 'ledger', 'brand_density_default' => 'compact']);
        $flags = (new SettingRepository($this->db))->get('features', []);
        $flags = is_array($flags) ? $flags : [];
        $flags['branding'] = false;
        (new SettingRepository($this->db))->set('features', $flags);
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringNotContainsString('data-rail', $html);
        self::assertStringNotContainsString('data-corners', $html);
        self::assertStringContainsString('data-density="comfortable"', $html);
    }

    public function test_a_draft_preview_shows_the_draft_style_to_its_author_only(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'intent' => 'draft', 'theme_preset' => 'vellum', 'density_default' => 'compact',
        ])), '/admin/branding');
        self::assertNotSame('vellum', $this->setting('brand_theme_preset'));
        $this->assertRedirect($this->post('/admin/branding/preview', ['preview' => '1', 'return' => '/']), '/');
        $html = $this->htmlTag($this->get('/')->body());
        self::assertStringContainsString('data-rail="raised"', $html);
        self::assertStringContainsString('data-corners="soft"', $html);
        self::assertStringContainsString('data-density="compact"', $html);

        $this->logoutClient();
        $guest = $this->htmlTag($this->get('/')->body());
        self::assertStringNotContainsString('data-rail', $guest);
        self::assertStringContainsString('data-density="comfortable"', $guest);
    }

    /** @param array<string,string> $values */
    private function settings(array $values): void
    {
        $settings = new SettingRepository($this->db);
        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
        $settings->set('brand_version', bin2hex(random_bytes(4)));
    }

    private function setting(string $key): string
    {
        $value = (new SettingRepository($this->db))->get($key, '');
        return is_int($value) ? (string) $value : (is_string($value) ? $value : '');
    }

    private function htmlTag(string $body): string
    {
        self::assertSame(1, preg_match('/<html\b[^>]*>/', $body, $match));
        return $match[0];
    }

    /** The declarations of the first top-level rule with exactly this selector. */
    private function rule(string $css, string $selector): string
    {
        self::assertSame(1, preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\{([^}]*)\}/', $css, $match), $selector);
        return $match[1];
    }

    /** @param array<string,string> $values */
    private function fresh(array $values): array
    {
        $revision = (new SettingRepository($this->db))->get('brand_revision', 0);
        return $values + ['site_name' => 'Style Commons', 'theme_default' => 'system', 'base_revision' => (string) (is_int($revision) ? $revision : 0)];
    }
}
