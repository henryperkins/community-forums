<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * ADR 0054 increment 3: independent light and dark colours. Each mode resolves
 * mode-specific override → shared override → package → preset, and the public
 * stylesheet, the sample, the thumbnails and the contrast review all use that
 * one resolution. Existing shared colours keep applying to both modes.
 */
final class AppBrandingPaletteTest extends TestCase
{
    public function test_shared_colours_still_apply_to_both_modes(): void
    {
        $this->makeAdmin();
        $this->settings(['brand_color_primary' => '#123456', 'brand_color_accent' => '#b0611c']);
        $css = $this->get('/brand.css', ['v' => 'shared'])->body();
        self::assertStringContainsString('--accent:#123456', $this->rule($css, ':root'));
        self::assertStringContainsString('--accent:#123456', $this->rule($css, '[data-theme="dark"]'));
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="light"\]\{[^}]*--thumb-action:#123456;/', $css);
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="dark"\]\{[^}]*--thumb-action:#123456;/', $css);
    }

    public function test_a_mode_override_wins_for_its_mode_only_everywhere(): void
    {
        $this->makeAdmin();
        $this->settings([
            'brand_color_primary' => '#123456',
            'brand_color_primary_dark' => '#9fc3a8',
            'brand_color_accent_light' => '#8f3d12',
        ]);
        $css = $this->get('/brand.css', ['v' => 'modes'])->body();
        $light = $this->rule($css, ':root');
        $dark = $this->rule($css, '[data-theme="dark"]');
        self::assertStringContainsString('--accent:#123456', $light);
        self::assertStringContainsString('--accent-2:#8f3d12', $light);
        self::assertStringContainsString('--accent:#9fc3a8', $dark);
        self::assertStringNotContainsString('--accent-2:#8f3d12', $dark);
        // System on a dark device takes the dark resolution too.
        self::assertMatchesRegularExpression('/@media \(prefers-color-scheme: dark\)\{:root\[data-theme="system"\]\{[^}]*--accent:#9fc3a8;/', $css);
        // The sample and the member thumbnails resolve the same way.
        self::assertMatchesRegularExpression('/\.brand-preview\[data-brand-preset="classic"\]\[data-brand-theme="dark"\]\{[^}]*--accent:#9fc3a8;/', $css);
        self::assertMatchesRegularExpression('/\.brand-preview\[data-brand-preset="classic"\]\[data-brand-theme="light"\]\{[^}]*--accent:#123456;/', $css);
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="dark"\]\{[^}]*--thumb-action:#9fc3a8;/', $css);
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="light"\]\{[^}]*--thumb-action:#123456;/', $css);
        // Per-mode colours alone are enough to load the stylesheet.
        $this->settings(['brand_color_primary' => '', 'brand_color_accent_light' => '']);
        self::assertStringContainsString('/brand.css?v=', $this->get('/')->body());
    }

    public function test_clearing_overrides_restores_the_shared_colour_then_the_preset(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->publish(['color_primary' => '#123456', 'color_primary_dark' => '#9fc3a8']), '/admin/branding');
        self::assertStringContainsString('--accent:#9fc3a8', $this->rule($this->get('/brand.css', ['v' => 'a'])->body(), '[data-theme="dark"]'));

        $this->assertRedirect($this->publish(['color_primary' => '#123456', 'color_primary_dark' => '']), '/admin/branding');
        self::assertSame('', $this->setting('brand_color_primary_dark'));
        self::assertStringContainsString('--accent:#123456', $this->rule($this->get('/brand.css', ['v' => 'b'])->body(), '[data-theme="dark"]'));

        $this->assertRedirect($this->publish(['color_primary' => '', 'color_primary_dark' => '']), '/admin/branding');
        // Classic's own dark action colour, untouched by any override.
        self::assertStringNotContainsString('--accent:', $this->rule($this->get('/brand.css', ['v' => 'c'])->body(), '[data-theme="dark"]'));
    }

    public function test_a_dark_override_resolves_a_dark_contrast_warning(): void
    {
        $this->actingAs($this->makeAdmin());
        // Classic's evergreen reads well by day and poorly on twilight surfaces.
        $warned = $this->post('/admin/branding', $this->fresh(['color_primary' => '#2e4a3a']));
        $this->assertStatus(422, $warned);
        $this->assertSeeText($warned, 'Primary colour gives links only');
        $this->assertSeeText($warned, 'on dark surfaces');
        self::assertSame(1, preg_match('/Suggested for readable links in dark mode: <code>(#[0-9a-f]{6})<\/code>/', $warned->body(), $suggestion));

        // Using the suggestion for dark mode alone needs no acknowledgement.
        $this->assertRedirect($this->post('/admin/branding', $this->fresh([
            'color_primary' => '#2e4a3a', 'color_primary_dark' => $suggestion[1],
        ])), '/admin/branding');
        self::assertSame('#2e4a3a', $this->setting('brand_color_primary'));
        self::assertSame($suggestion[1], $this->setting('brand_color_primary_dark'));
        $css = $this->get('/brand.css', ['v' => 'fixed'])->body();
        // Evergreen is Classic's own light action colour, so light emits no
        // override; dark takes the suggested tone.
        self::assertMatchesRegularExpression('/\.theme-thumb \[data-thumb-mode="light"\]\{[^}]*--thumb-action:#2e4a3a;/', $css);
        self::assertStringContainsString('--accent:' . $suggestion[1], $this->rule($css, '[data-theme="dark"]'));
    }

    public function test_an_acknowledgement_is_bound_to_the_mode_colours_it_reviewed(): void
    {
        $this->actingAs($this->makeAdmin());
        $values = $this->fresh(['color_primary' => '#123456', 'color_primary_dark' => '#1c2e24']);
        $review = $this->post('/admin/branding', $values);
        $this->assertStatus(422, $review);
        $this->assertSeeText($review, 'Dark mode primary gives links only');
        self::assertSame(1, preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token));

        // Reusing that review for a different dark colour is refused.
        $changed = $this->post('/admin/branding', ['color_primary_dark' => '#20302a', 'contrast_ack' => '1', 'contrast_review' => $token[1]] + $values);
        $this->assertStatus(422, $changed);
        self::assertSame('', $this->setting('brand_color_primary_dark'));

        $this->assertRedirect($this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]), '/admin/branding');
        self::assertSame('#1c2e24', $this->setting('brand_color_primary_dark'));
        $audit = json_decode((string) $this->db->fetchValue("SELECT after_json FROM moderation_log WHERE action = 'branding_contrast_override' ORDER BY id DESC LIMIT 1"), true);
        self::assertSame('#1c2e24', $audit['mode_colours']['dark']['primary']);
        self::assertSame('#123456', $audit['mode_colours']['light']['primary']);
    }

    public function test_invalid_mode_colours_return_the_draft_and_the_colours_reset_clears_them(): void
    {
        $this->actingAs($this->makeAdmin());
        $invalid = $this->post('/admin/branding', $this->fresh(['site_name' => 'Kept draft', 'color_accent_dark' => 'golden']));
        $this->assertStatus(422, $invalid);
        $this->assertSeeText($invalid, 'Dark mode accent must be a hex value');
        self::assertStringContainsString('value="golden"', $invalid->body());
        self::assertStringContainsString('value="Kept draft"', $invalid->body());
        self::assertMatchesRegularExpression('/<details class="branding-field branding-field-wide branding-mode-colours" open>/', $invalid->body());

        $this->settings(['brand_color_primary' => '#123456', 'brand_color_primary_light' => '#234567', 'brand_color_accent_dark' => '#d2b062']);
        $this->assertRedirect($this->post('/admin/branding/reset/colours'), '/admin/branding');
        foreach (['brand_color_primary', 'brand_color_primary_light', 'brand_color_accent_dark'] as $key) {
            self::assertSame('', $this->setting($key), $key);
        }
    }

    public function test_a_form_without_the_mode_fields_keeps_them(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->settings(['brand_color_primary_dark' => '#9fc3a8']);
        $this->assertRedirect($this->post('/admin/branding', $this->fresh(['site_name' => 'Older form'])), '/admin/branding');
        self::assertSame('#9fc3a8', $this->setting('brand_color_primary_dark'));
    }

    public function test_restoring_a_revision_from_before_mode_colours_clears_them(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertRedirect($this->publish(['color_primary' => '#123456']), '/admin/branding');
        // Make that revision look as it was recorded before the per-mode keys existed.
        $old = (int) $this->setting('brand_revision');
        $document = json_decode((string) $this->db->fetchValue('SELECT document_json FROM branding_revisions WHERE id = ?', [$old]), true);
        foreach (['brand_color_primary_light', 'brand_color_accent_light', 'brand_color_primary_dark', 'brand_color_accent_dark'] as $key) {
            unset($document[$key]);
        }
        $this->db->run('UPDATE branding_revisions SET document_json = ? WHERE id = ?', [json_encode($document, JSON_THROW_ON_ERROR), $old]);

        $this->assertRedirect($this->publish(['color_primary' => '#123456', 'color_primary_dark' => '#9fc3a8']), '/admin/branding');
        $review = $this->get('/admin/branding/history/' . $old);
        $this->assertStatus(200, $review);
        $this->assertSeeText($review, 'Dark mode primary');
        self::assertSame(1, preg_match('/name="base_revision" value="([0-9]+)"/', $review->body(), $base));
        $values = ['base_revision' => $base[1]];
        if (preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $review->body(), $token) === 1) {
            $values += ['contrast_ack' => '1', 'contrast_review' => $token[1]];
        }
        $this->assertRedirect($this->post('/admin/branding/history/' . $old . '/restore', $values), '/admin/branding');
        self::assertSame('', $this->setting('brand_color_primary_dark'));
        self::assertSame('#123456', $this->setting('brand_color_primary'));
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

    /** The declarations of the first top-level rule with exactly this selector. */
    private function rule(string $css, string $selector): string
    {
        self::assertSame(1, preg_match('/(?:^|\n)' . preg_quote($selector, '/') . '\{([^}]*)\}/', $css, $match), $selector);
        return $match[1];
    }

    private function fresh(array $values): array
    {
        $revision = (new SettingRepository($this->db))->get('brand_revision', 0);
        return $values + ['site_name' => 'Palette Commons', 'theme_default' => 'system', 'base_revision' => (string) (is_int($revision) ? $revision : 0)];
    }

    /** Publish as a fresh form, acknowledging the exact review if one appears. */
    private function publish(array $values): \App\Core\Response
    {
        $values = $this->fresh($values);
        $response = $this->post('/admin/branding', $values);
        if ($response->status() === 422 && preg_match('/name="contrast_review" value="([a-f0-9]{64})"/', $response->body(), $token) === 1) {
            $response = $this->post('/admin/branding', $values + ['contrast_ack' => '1', 'contrast_review' => $token[1]]);
        }
        return $response;
    }
}
