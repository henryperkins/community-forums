<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BrandingDocument;
use App\Support\BrandStyle;
use PHPUnit\Framework\TestCase;

/** ADR 0054 increment 4: style resolution — override → package → preset. */
final class BrandStyleTest extends TestCase
{
    public function test_a_preset_supplies_every_part_and_overrides_replace_one(): void
    {
        self::assertSame(['rail' => 'flush', 'type' => 'book', 'corners' => 'crisp'], BrandStyle::effective(['brand_theme_preset' => 'ledger'], false));
        self::assertSame(['rail' => 'flush', 'type' => 'lapidary', 'corners' => 'crisp'], BrandStyle::effective(['brand_theme_preset' => 'ledger', 'brand_type_pairing' => 'lapidary'], false));
        // Unknown presets and values fall back to Classic and to the preset.
        self::assertSame(['rail' => 'sunken', 'type' => 'imladris', 'corners' => 'imladris'], BrandStyle::effective(['brand_theme_preset' => 'neon', 'brand_corners' => 'round'], false));
        self::assertSame('classic', BrandStyle::preset(null));
        self::assertSame('comfortable', BrandStyle::density('spacious'));
        self::assertSame('compact', BrandStyle::density('compact'));
        self::assertSame('', BrandStyle::override('brand_sidebar_style', ['flush']));
    }

    public function test_a_served_package_owns_every_part_without_an_override(): void
    {
        self::assertSame(['rail' => '', 'type' => '', 'corners' => ''], BrandStyle::effective(['brand_theme_preset' => 'vellum'], true));
        $style = BrandStyle::effective(['brand_theme_preset' => 'vellum', 'brand_corners' => 'imladris'], true);
        self::assertSame(['rail' => '', 'type' => '', 'corners' => 'imladris'], $style);
        // Over a package even a value equal to the built-in default is stamped.
        self::assertSame(['data-corners' => 'imladris'], BrandStyle::attributes($style, true));
    }

    public function test_history_describes_style_values_by_their_labels(): void
    {
        self::assertSame('Preset', BrandingDocument::describe(['brand_sidebar_style' => ''], 'brand_sidebar_style'));
        self::assertSame('Flush with the page', BrandingDocument::describe(['brand_sidebar_style' => 'flush'], 'brand_sidebar_style'));
        self::assertSame('System sans-serif headings', BrandingDocument::describe(['brand_type_pairing' => 'lapidary'], 'brand_type_pairing'));
        self::assertSame('Vellum', BrandingDocument::describe(['brand_theme_preset' => 'vellum'], 'brand_theme_preset'));
        self::assertSame('Compact', BrandingDocument::describe(['brand_density_default' => 'compact'], 'brand_density_default'));
        // A stored document is normalised the same way.
        $document = BrandingDocument::normalize(['brand_corners' => 'round', 'brand_density_default' => 'spacious', 'brand_theme_preset' => 'neon'], 'Commons');
        self::assertSame(['', 'comfortable', 'classic'], [$document['brand_corners'], $document['brand_density_default'], $document['brand_theme_preset']]);
    }

    public function test_built_in_values_are_not_stamped_without_a_package(): void
    {
        self::assertSame([], BrandStyle::attributes(BrandStyle::effective([], false)));
        self::assertSame([], BrandStyle::attributes(BrandStyle::effective(['brand_theme_preset' => 'retro'], false)));
        self::assertSame(
            ['data-rail' => 'raised', 'data-type-pairing' => 'lapidary', 'data-corners' => 'soft'],
            BrandStyle::attributes(BrandStyle::effective(['brand_theme_preset' => 'vellum'], false)),
        );
    }
}
