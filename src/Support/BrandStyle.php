<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Curated style presets (ADR 0054 increment 4): each preset is a complete
 * combination of surface palette, sidebar treatment, heading/body pairing and
 * corner radii. An operator may override the sidebar, the type pairing or the
 * corners individually; the surfaces always come from the preset (or an active
 * package). Precedence per axis: operator override → active package → preset.
 *
 * The values are fixed vocabularies: the shell stamps them on <html> as data
 * attributes and the Forum theme maps them to tokens, so no operator text
 * reaches CSS. Stored legacy keys remain valid; labels describe the active
 * system-font presentation (ADR 0056).
 */
final class BrandStyle
{
    /** @var array<string,array{label:string,summary:string,rail:string,type:string,corners:string}> */
    public const PRESETS = [
        'classic' => [
            'label' => 'Forum',
            'summary' => 'Neutral surfaces, a contrasting sidebar, system sans-serif text and standard corners.',
            'rail' => 'sunken', 'type' => 'imladris', 'corners' => 'imladris',
        ],
        'retro' => [
            'label' => 'Retro',
            'summary' => 'Warm surfaces with the standard sidebar, system sans-serif text and corners.',
            'rail' => 'sunken', 'type' => 'imladris', 'corners' => 'imladris',
        ],
        'ledger' => [
            'label' => 'Ledger',
            'summary' => 'Cool surfaces, a sidebar flush with the page, system serif headings and crisp corners.',
            'rail' => 'flush', 'type' => 'book', 'corners' => 'crisp',
        ],
        'vellum' => [
            'label' => 'Vellum',
            'summary' => 'Warm neutral surfaces, a raised sidebar, system sans-serif text and soft corners.',
            'rail' => 'raised', 'type' => 'lapidary', 'corners' => 'soft',
        ],
    ];

    /** @var array<string,string> sidebar treatment => label */
    public const RAILS = [
        'sunken' => 'Sunken',
        'flush' => 'Flush with the page',
        'raised' => 'Raised panel',
    ];

    /** @var array<string,string> heading/body pairing => label (body text always uses the system sans-serif stack) */
    public const TYPES = [
        'imladris' => 'System sans-serif',
        'book' => 'System serif headings',
        'lapidary' => 'System sans-serif headings',
    ];

    /** @var array<string,string> corner scale => label */
    public const CORNERS = [
        'crisp' => 'Crisp (2–6px)',
        'imladris' => 'Standard (4–12px)',
        'soft' => 'Soft (6–12px)',
    ];

    /** @var list<string> */
    public const DENSITIES = ['comfortable', 'compact'];

    /** Setting key => [axis, vocabulary] for the individual overrides. */
    public const OVERRIDES = [
        'brand_sidebar_style' => ['rail', self::RAILS],
        'brand_type_pairing' => ['type', self::TYPES],
        'brand_corners' => ['corners', self::CORNERS],
    ];

    public static function preset(mixed $value): string
    {
        return is_string($value) && isset(self::PRESETS[$value]) ? $value : 'classic';
    }

    public static function density(mixed $value): string
    {
        return is_string($value) && in_array($value, self::DENSITIES, true) ? $value : 'comfortable';
    }

    /** An override value, or '' (use the preset) for anything unknown. */
    public static function override(string $key, mixed $value): string
    {
        $vocabulary = self::OVERRIDES[$key][1] ?? [];
        return is_string($value) && isset($vocabulary[$value]) ? $value : '';
    }

    /**
     * The effective sidebar, type and corners for a settings document. While a
     * package theme is served it owns the base, so an axis without an operator
     * override resolves to '' (nothing stamped: the package or built-in tokens).
     *
     * @param array<string,mixed> $settings
     * @return array{rail:string,type:string,corners:string}
     */
    public static function effective(array $settings, bool $packageServed): array
    {
        $preset = self::PRESETS[self::preset($settings['brand_theme_preset'] ?? null)];
        $style = [];
        foreach (self::OVERRIDES as $key => [$axis]) {
            $chosen = self::override($key, $settings[$key] ?? null);
            $style[$axis] = $chosen !== '' ? $chosen : ($packageServed ? '' : $preset[$axis]);
        }
        return $style;
    }

    /**
     * HTML attributes for an effective style. Without a package, values equal
     * to the built-in defaults are omitted, so an unstyled install renders
     * with the current Forum defaults. While a package is served every resolved value is
     * stamped: an override that restates a built-in default must still win
     * over the package's own tokens.
     *
     * @param array{rail:string,type:string,corners:string} $style
     * @return array<string,string> attribute => value
     */
    public static function attributes(array $style, bool $packageServed = false): array
    {
        $defaults = ['rail' => 'sunken', 'type' => 'imladris', 'corners' => 'imladris'];
        $names = ['rail' => 'data-rail', 'type' => 'data-type-pairing', 'corners' => 'data-corners'];
        $attributes = [];
        foreach ($names as $axis => $name) {
            $value = $style[$axis] ?? '';
            if ($value !== '' && ($packageServed || $value !== $defaults[$axis])) {
                $attributes[$name] = $value;
            }
        }
        return $attributes;
    }
}
