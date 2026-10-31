<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Support\BrandStyle;
use PHPUnit\Framework\TestCase;

/**
 * ADR 0054 increment 4: an exposed style control must reach every supported
 * component. These contracts read the shipped stylesheets: each vocabulary
 * value has a token rule for the shell and the sample, components read the
 * tokens rather than literals, the type pairings name only self-hosted
 * families, and every corner scale honours the Twelve-Max Rule.
 */
final class BrandStyleContractTest extends TestCase
{
    private static function css(string $file): string
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/public/assets/' . $file);
        self::assertIsString($css);
        return $css;
    }

    public function test_every_vocabulary_value_has_a_shell_and_sample_rule(): void
    {
        $app = self::css('app.css');
        $axes = ['data-rail' => BrandStyle::RAILS, 'data-type-pairing' => BrandStyle::TYPES, 'data-corners' => BrandStyle::CORNERS];
        foreach ($axes as $attribute => $vocabulary) {
            foreach (array_keys($vocabulary) as $value) {
                self::assertStringContainsString(
                    ':root[' . $attribute . '="' . $value . '"], .brand-preview[' . $attribute . '="' . $value . '"]',
                    $app,
                    "$attribute=$value",
                );
            }
        }
        // Every preset names values from those vocabularies.
        foreach (BrandStyle::PRESETS as $preset => $style) {
            self::assertArrayHasKey($style['rail'], BrandStyle::RAILS, $preset);
            self::assertArrayHasKey($style['type'], BrandStyle::TYPES, $preset);
            self::assertArrayHasKey($style['corners'], BrandStyle::CORNERS, $preset);
        }
    }

    public function test_the_rail_reads_its_hooks_and_falls_back_to_the_layer(): void
    {
        $app = self::css('app.css');
        self::assertStringContainsString('.board-rail { background: var(--rail-ground, var(--surface-sunken)); }', $app);
        self::assertStringContainsString(".board-rail-item:not(.is-active):hover,\n.board-rail-route:not(.is-active):hover { background: var(--rail-hover, var(--surface-page)); }", $app);
        // The layer's defaults are what the hooks fall back to.
        $layer = self::css('imladris.css');
        self::assertMatchesRegularExpression('/\.board-rail \{[^}]*background: var\(--surface-sunken\);/', $layer);
        self::assertStringContainsString('.board-rail-item:hover { background: var(--surface-page);', $layer);
        // Every treatment keeps its hover on a different ground from the rail.
        preg_match_all('/:root\[data-rail="([a-z]+)"\][^{]*\{ --rail-ground: var\((--[a-z-]+)\); --rail-hover: var\((--[a-z-]+)\); \}/', $app, $rails, PREG_SET_ORDER);
        self::assertCount(count(BrandStyle::RAILS), $rails);
        foreach ($rails as [, $rail, $ground, $hover]) {
            self::assertNotSame($ground, $hover, $rail);
        }
    }

    public function test_type_pairings_name_only_self_hosted_serif_families(): void
    {
        preg_match_all('/@font-face \{ font-family: "([^"]+)"/', self::css('imladris.css'), $faces);
        $shipped = array_values(array_unique($faces[1]));
        self::assertEqualsCanonicalizing(['Cormorant Garamond', 'EB Garamond', 'Marcellus', 'JetBrains Mono'], $shipped);
        $app = self::css('app.css');
        foreach (array_keys(BrandStyle::TYPES) as $pairing) {
            self::assertSame(1, preg_match('/:root\[data-type-pairing="' . $pairing . '"\][^{]*\{\s*--font-display: "([^"]+)"([^;]*);/', $app, $match), $pairing);
            self::assertContains($match[1], $shipped, "$pairing leads with a self-hosted family");
            self::assertStringNotContainsString('sans-serif', $match[2], "$pairing keeps the All-Serif Rule");
            self::assertStringEndsWith('serif', trim($match[2]), "$pairing keeps a serif fallback");
        }
        // Marcellus ships one weight: its pairing never synthesizes another.
        self::assertMatchesRegularExpression('/data-type-pairing="lapidary"\][^{]*\{[^}]*font-synthesis-weight: none;/', $app);
    }

    public function test_components_read_type_tokens_not_literal_families(): void
    {
        foreach (['app.css', 'imladris.css'] as $file) {
            $css = preg_replace('/@font-face \{[^}]*\}/', '', self::css($file));
            preg_match_all('/(?<![-\w])font-family:(?!\s*(?:var\(|inherit|initial|unset|revert-layer|revert))\s*([^;}]+)/', (string) $css, $literals);
            self::assertSame([], $literals[1], "$file sets literal font families outside @font-face");
        }
    }

    public function test_every_corner_scale_honours_the_twelve_max_rule(): void
    {
        $app = self::css('app.css');
        foreach (array_keys(BrandStyle::CORNERS) as $scale) {
            self::assertSame(1, preg_match('/:root\[data-corners="' . $scale . '"\][^{]*\{ --radius-sm: (\d+)px; --radius-md: (\d+)px; --radius-lg: (\d+)px; --radius-xl: (\d+)px; \}/', $app, $radii), $scale);
            [, $sm, $md, $lg, $xl] = array_map('intval', $radii);
            self::assertTrue($sm <= $md && $md <= $lg && $lg <= $xl, "$scale is ordered");
            self::assertLessThanOrEqual(12, $lg, "$scale content containers");
            self::assertLessThanOrEqual(20, $xl, "$scale large containers");
        }
        // The standard scale is exactly the layer's own, so it changes nothing.
        $layer = self::css('imladris.css');
        foreach (['sm' => 4, 'md' => 7, 'lg' => 12, 'xl' => 20] as $step => $px) {
            self::assertMatchesRegularExpression('/--radius-' . $step . ':\s+' . $px . 'px;/', $layer);
        }
        // Compact cards follow the scale, at 6px with the standard corners.
        self::assertStringContainsString('[data-density="compact"] { --radius: calc(var(--radius-md) - 1px); }', $app);
    }

    public function test_components_read_radius_tokens_apart_from_hairline_details_and_pills(): void
    {
        foreach (['app.css', 'imladris.css'] as $file) {
            preg_match_all('/border-radius:\s*([0-9.]+)px/', self::css($file), $literals);
            foreach ($literals[1] as $px) {
                // Sub-4px detail radii (focus rings, marks, thumbnail strokes)
                // and 999px pills are the only literals; everything that holds
                // content reads the scale.
                self::assertTrue((float) $px <= 3 || (float) $px >= 999, "$file literal {$px}px radius");
            }
        }
    }
}
