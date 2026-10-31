<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

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
}
