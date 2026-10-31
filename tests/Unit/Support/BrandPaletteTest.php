<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BrandPalette;
use PHPUnit\Framework\TestCase;

final class BrandPaletteTest extends TestCase
{
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
