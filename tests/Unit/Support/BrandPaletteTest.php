<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Security\Packages\ThemeTokenPolicy;
use App\Support\BrandPalette;
use PHPUnit\Framework\TestCase;

final class BrandPaletteTest extends TestCase
{
    public function test_built_in_selected_menu_labels_remain_readable_after_clearing_colours(): void
    {
        foreach (BrandPalette::presets() as $preset => $modes) {
            foreach ($modes as $mode => $base) {
                $palette = BrandPalette::withColours($base, '', '');
                self::assertGreaterThanOrEqual(4.5, BrandPalette::contrastRatio($palette['--brand'], $palette['--brand-contrast']), "$preset $mode selected menu");
            }
        }
    }

    public function test_operator_colours_cover_selected_actions_and_highlights_without_recolouring_status(): void
    {
        foreach (BrandPalette::presets() as $modes) {
            foreach ($modes as $base) {
                $base['--warning'] = '#abcdef';
                $base['--surface-review'] = '#fedcba';
                $base['--success'] = '#123456';
                $base['--presence'] = '#654321';
                $colours = BrandPalette::withColours($base, '#005fcc', '#a33300');
                self::assertSame('#005fcc', $colours['--brand']);
                self::assertSame('#005fcc', $colours['--accent']);
                self::assertSame('#a33300', $colours['--gold']);
                self::assertSame('#a33300', $colours['--rule-gold']);
                self::assertNotSame($base['--brand-subtle'], $colours['--brand-subtle']);
                self::assertNotSame($base['--gold-soft'], $colours['--gold-soft']);
                foreach (['--warning', '--surface-review', '--success', '--presence'] as $status) {
                    self::assertSame($base[$status], $colours[$status]);
                }
            }
        }
    }

    public function test_derived_pairs_remain_readable_for_extreme_and_midpoint_colours(): void
    {
        foreach (BrandPalette::presets()['classic'] as $base) {
            foreach (['#ffffff', '#000000', '#777777', '#005fcc', '#ff00ff', '#ffff00'] as $colour) {
                $palette = BrandPalette::withColours($base, $colour, $colour);
                foreach ([
                    ['--accent', '--accent-contrast'], ['--brand', '--brand-contrast'],
                    ['--brand-hover', '--brand-hover-contrast'], ['--brand-press', '--brand-press-contrast'],
                    ['--brand-subtle', '--on-brand-subtle'], ['--gold-soft', '--gold-ink'],
                    ['--accent-2', '--brand-accent-contrast'],
                    ['--brand-accent-hover', '--brand-accent-hover-contrast'],
                ] as [$background, $foreground]) {
                    self::assertGreaterThanOrEqual(4.5, BrandPalette::contrastRatio($palette[$background], $palette[$foreground]), "$colour $foreground on $background");
                }
            }
        }
    }

    public function test_clearing_colours_restores_package_roles_and_derivation_uses_its_surface(): void
    {
        $package = ['tokens' => ['--surface-raised' => '#ffffff', '--surface' => '#ffffff', '--brand' => '#446688', '--gold-soft' => '#ddddee']];
        // Package surface aliases are intentionally limited by its existing policy.
        $base = BrandPalette::presets($package)['classic']['light'];
        $custom = BrandPalette::withColours($base, '#005fcc', '#a33300');
        $otherBase = array_replace($base, ['--surface-raised' => '#000000']);
        $other = BrandPalette::withColours($otherBase, '#005fcc', '#a33300');
        self::assertNotSame($custom['--brand-subtle'], $other['--brand-subtle']);
        self::assertSame('#446688', BrandPalette::withColours($base, '', '')['--brand']);
        self::assertSame('#ddddee', BrandPalette::withColours($base, '', '')['--gold-soft']);
        self::assertSame($base['--brand'], BrandPalette::withColours($base, '', '#a33300')['--brand']);
        self::assertSame($base['--gold-soft'], BrandPalette::withColours($base, '#005fcc', '')['--gold-soft']);
    }

    public function test_retro_text_and_links_remain_readable_on_each_content_surface(): void
    {
        foreach (BrandPalette::presets()['retro'] as $mode => $palette) {
            foreach (['--surface', '--surface-2', '--surface-3', '--surface-page', '--surface-raised', '--surface-sunken'] as $surface) {
                foreach (['--text', '--text-muted', '--text-body', '--text-strong', '--text-faint', '--accent'] as $text) {
                    self::assertGreaterThanOrEqual(4.5, BrandPalette::contrastRatio($palette[$text], $palette[$surface]), "$mode $text on $surface");
                }
            }
            self::assertGreaterThanOrEqual(4.5, BrandPalette::contrastRatio($palette['--accent'], $palette['--accent-contrast']));
        }
    }

    public function test_warning_reports_the_weakest_package_surface_without_changing_the_package(): void
    {
        $package = ['tokens' => ['--surface' => '#ffffff'], 'dark_tokens' => ['--surface' => '#000000']];
        $palettes = BrandPalette::presets($package);
        self::assertSame($palettes['classic'], $palettes['retro'], 'Active packages suppress Retro.');
        $warnings = BrandPalette::warnings($palettes['classic'], '#ffffff', '');
        self::assertStringContainsString('1.00:1', implode(' ', $warnings));
        self::assertStringContainsString('light surfaces', implode(' ', $warnings));
        self::assertSame('#ffffff', $palettes['classic']['light']['--surface']);
    }

    public function test_contrast_review_does_not_accept_malformed_package_tokens_as_css(): void
    {
        $palettes = BrandPalette::presets(['tokens' => ['--surface' => 'red;}body{display:none', '--unknown' => 'bad']]);
        self::assertSame('#faf6ec', $palettes['classic']['light']['--surface']);
        self::assertArrayNotHasKey('--unknown', $palettes['classic']['light']);
    }

    public function test_package_roles_and_branding_share_one_definition(): void
    {
        // ADR 0054: every colour role a package may set resolves, in the
        // built-in Classic base, to the policy baseline the build gate and
        // contrast review use. Only the translucent dark washes differ, as the
        // stylesheet declares them translucent and the baseline flattens them.
        $translucent = ['dark' => ['--brand-subtle', '--gold-soft']];
        $classic = BrandPalette::presets()['classic'];
        foreach (['light', 'dark'] as $mode) {
            $baseline = ThemeTokenPolicy::baseline($mode);
            foreach (ThemeTokenPolicy::TOKENS as $token => $type) {
                if ($type !== 'color' || in_array($token, $translucent[$mode] ?? [], true)) {
                    continue;
                }
                self::assertArrayHasKey($token, $classic[$mode], "$mode $token is missing from the branding base");
                if (isset($baseline[$token])) {
                    self::assertSame($baseline[$token], $classic[$mode][$token], "$mode $token");
                }
            }
        }
        // The semantic surfaces the shell paints from are package-settable;
        // focus, field rules and status families are not.
        foreach (['--surface-page', '--surface-raised', '--surface-sunken', '--border-hair', '--border-soft', '--border-strong'] as $role) {
            self::assertTrue(ThemeTokenPolicy::isKnown($role), $role);
        }
        foreach (['--focus-ring', '--field-rule', '--success', '--warning', '--presence'] as $role) {
            self::assertFalse(ThemeTokenPolicy::isKnown($role), $role);
        }
    }

    public function test_each_mode_resolves_its_own_override_then_the_shared_colour(): void
    {
        self::assertSame(
            ['light' => ['primary' => '#123456', 'accent' => ''], 'dark' => ['primary' => '#9fc3a8', 'accent' => '#d2b062']],
            BrandPalette::modeColours([
                'brand_color_primary' => '#123456',
                'brand_color_primary_dark' => '#9FC3A8',
                'brand_color_accent_dark' => '#d2b062',
                'brand_color_accent_light' => 'not-hex',
            ]),
        );
        self::assertSame(BrandPalette::modeColours([]), BrandPalette::sharedColours('', ''));
        // The two-colour signatures still mean "the same colours in both modes".
        $palettes = BrandPalette::presets()['classic'];
        self::assertSame(
            BrandPalette::warnings($palettes, '#123456', '#abcdef'),
            BrandPalette::modeWarnings($palettes, BrandPalette::sharedColours('#123456', '#abcdef')),
        );
        self::assertSame(BrandPalette::previewCss(BrandPalette::presets(), '#123456', '#abcdef'), BrandPalette::previewCss(BrandPalette::presets(), BrandPalette::sharedColours('#123456', '#abcdef')));
    }

    public function test_accepting_a_suggestion_clears_that_modes_review(): void
    {
        // Suggestions run the review publishing runs, for both roles, in both
        // modes of Classic, Retro and a package palette: taking one never
        // leaves a warning behind, with or without the other role set.
        $package = ['tokens' => ['--accent' => '#8f3d12', '--surface-page' => '#fff8e8', '--surface-raised' => '#fffdf6'], 'dark_tokens' => ['--surface-page' => '#141210', '--surface-raised' => '#1d1a16']];
        $bases = BrandPalette::presets() + ['package' => BrandPalette::presets($package)['classic']];
        $offered = 0;
        foreach ($bases as $base => $modes) {
            foreach ($modes as $mode => $palette) {
                foreach (['primary' => 'accent', 'accent' => 'primary'] as $role => $other) {
                    foreach (['#2e4a3a', '#c29a44', '#808080', '#b0611c', '#005fcc', '#ffffff', '#000000', '#ff00ff'] as $colour) {
                        $suggested = BrandPalette::suggestion($colour, $palette, $role);
                        if ($suggested === '') {
                            continue;
                        }
                        $offered += $suggested !== $colour ? 1 : 0;
                        $partner = BrandPalette::suggestion('#2e4a3a', $palette, $other);
                        foreach (['' , $partner] as $otherColour) {
                            $colours = ['light' => ['primary' => '', 'accent' => ''], 'dark' => ['primary' => '', 'accent' => '']];
                            $colours[$mode] = [$role => $suggested, $other => $otherColour];
                            self::assertSame([], BrandPalette::modeWarnings([$mode => $palette], $colours), "$base $mode $role $colour → $suggested");
                        }
                    }
                }
            }
        }
        self::assertGreaterThan(20, $offered);
        // A colour that already passes is returned as it is.
        self::assertSame('#d2b062', BrandPalette::suggestion('#d2b062', BrandPalette::presets()['classic']['dark'], 'accent'));
    }

    public function test_dark_suggestions_reach_readable_link_contrast(): void
    {
        $dark = BrandPalette::presets()['classic']['dark'];
        $suggested = BrandPalette::suggestion('#2e4a3a', $dark);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $suggested);
        foreach (['--surface-page', '--surface-raised', '--surface-sunken'] as $surface) {
            self::assertGreaterThanOrEqual(4.5, BrandPalette::contrastRatio($suggested, $dark[$surface]), $surface);
        }
        // A colour that already reads well is returned unchanged.
        self::assertSame('#d2b062', BrandPalette::suggestion('#d2b062', $dark));
        self::assertSame('', BrandPalette::suggestion('', $dark));
        // Warnings name the field to change when a mode has its own colour.
        $warnings = BrandPalette::modeWarnings(BrandPalette::presets()['classic'], BrandPalette::modeColours([
            'brand_color_primary' => '#2e4a3a', 'brand_color_primary_dark' => '#1c2e24',
        ]), ['primary_dark' => true]);
        self::assertNotEmpty(preg_grep('/^Dark mode primary gives links only [0-9.]+:1 contrast on dark surfaces; 4\.5:1 is needed\.$/', $warnings));
        foreach ($warnings as $warning) {
            self::assertStringNotContainsString('Primary colour gives links only', $warning);
        }
    }
}
