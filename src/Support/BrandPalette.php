<?php

declare(strict_types=1);

namespace App\Support;

use App\Security\Packages\ThemeTokenPolicy;

/** Resolved branding colours shared by delivery, preview, and contrast review. */
final class BrandPalette
{
    public const CSS_VERSION = '3';
    /** @return array<string,array<string,array<string,string>>> */
    public static function presets(?array $packageTokens = null): array
    {
        // One shared definition (ADR 0054): every role a package may set comes
        // from ThemeTokenPolicy's baseline, the same values the package build
        // gate and contrast review use. Only application-owned roles (status,
        // focus, field rule, inverse/cool grounds, derived hover/press) and the
        // translucent dark washes as the stylesheet really declares them are
        // added here.
        $classic = [
            'light' => array_replace(ThemeTokenPolicy::baseline('light'), [
                '--surface-cool' => '#eef1ed', '--surface-inverse' => '#1e2730',
                '--gold-soft' => '#f4ebcf',
                '--surface-done' => '#edf3ed', '--on-done' => '#24402f',
                '--brand-hover' => '#24402f', '--brand-press' => '#1c2e24',
                '--focus-ring' => 'rgba(194,154,68,.7)', '--field-rule' => '#9a7530',
            ]),
            'dark' => array_replace(ThemeTokenPolicy::baseline('dark'), [
                '--surface-cool' => '#eef1ed', '--surface-inverse' => '#f5efe1',
                // The baseline flattens these washes for contrast arithmetic;
                // the stylesheet declares them translucent.
                '--brand-subtle' => 'rgba(78,116,89,.20)',
                '--gold-soft' => 'rgba(194,154,68,.16)',
                '--surface-done' => 'rgba(78,116,89,.20)', '--on-done' => '#bcd0bf',
                '--brand-hover' => '#6e9479', '--brand-press' => '#1c2e24',
                '--focus-ring' => 'rgba(210,176,98,.75)', '--field-rule' => '#b08a3a',
            ]),
        ];
        // Package builds contain validated token data, never raw operator CSS.
        // Their base tokens also apply in dark mode before dark_tokens override.
        if ($packageTokens !== null) {
            foreach (['light', 'dark'] as $mode) {
                $overrides = (array) ($packageTokens['tokens'] ?? []);
                if ($mode === 'dark') {
                    $overrides = array_replace($overrides, (array) ($packageTokens['dark_tokens'] ?? []));
                }
                foreach ($overrides as $token => $value) {
                    if (is_string($token) && is_string($value) && ThemeTokenPolicy::isKnown($token)
                        && ThemeTokenPolicy::type($token) !== 'asset'
                        && ThemeTokenPolicy::validateValue($token, $value, []) === null) {
                        $classic[$mode][$token] = $value;
                    }
                }
            }
            // An active package owns the preset; the stored style preset
            // resumes only when the package is disabled or safe mode suppresses it.
            return self::previewDefaults(array_fill_keys(array_keys(BrandStyle::PRESETS), $classic));
        }

        $retro = [
            'light' => array_replace($classic['light'], [
                '--surface' => '#fff7dc', '--surface-2' => '#f6e8c4', '--surface-3' => '#f0dfb3',
                '--surface-page' => '#f6e8c4', '--surface-raised' => '#fff7dc',
                '--surface-sunken' => '#f0dfb3', '--surface-soft' => '#fff1bd',
                '--surface-cool' => '#f0e7d4', '--surface-inverse' => '#30251a',
                '--text' => '#241706', '--text-strong' => '#241706', '--text-body' => '#392b16',
                '--text-muted' => '#655131', '--text-faint' => '#6b5738', '--text-inverse' => '#fff7dc',
                '--accent' => '#8f3d12', '--accent-contrast' => '#ffffff', '--accent-2' => '#a95e12',
                '--brand' => '#8f3d12', '--brand-hover' => '#70300e', '--brand-press' => '#57240b',
                '--brand-subtle' => '#f4dfb4', '--on-brand-subtle' => '#63330f',
                '--border' => '#c78b45', '--border-hair' => '#d4b276', '--border-soft' => '#deca9e',
                '--border-strong' => '#9f7137', '--field-rule' => '#8f6429',
                '--gold-soft' => '#f4dfb4', '--gold-ink' => '#6b450f', '--rule-gold' => '#a95e12',
            ]),
            'dark' => array_replace($classic['dark'], [
                '--surface' => '#382c20', '--surface-2' => '#211b14', '--surface-3' => '#30251a',
                '--surface-page' => '#211b14', '--surface-raised' => '#30251a',
                '--surface-sunken' => '#382c20', '--surface-soft' => '#403122',
                '--surface-cool' => '#302b25', '--surface-inverse' => '#fff7dc',
                '--text' => '#fff1d2', '--text-strong' => '#fff7dc', '--text-body' => '#f0dfbd',
                '--text-muted' => '#d4bd94', '--text-faint' => '#c4ae8a', '--text-inverse' => '#241706',
                '--accent' => '#f0b778', '--accent-contrast' => '#241706', '--accent-2' => '#e6ad69',
                '--brand' => '#d99b58', '--brand-hover' => '#f0b778', '--brand-press' => '#ffd2a0',
                '--brand-contrast' => '#241706',
                '--brand-subtle' => '#49331e', '--on-brand-subtle' => '#f0d3a2',
                '--border' => '#776047', '--border-hair' => '#776047', '--border-soft' => '#57432f',
                '--border-strong' => '#ad8b5e', '--field-rule' => '#c79c60',
                '--gold-soft' => '#49331e', '--gold-ink' => '#f0c98c', '--rule-gold' => '#e6ad69',
            ]),
        ];
        // Curated style presets (ADR 0054 increment 4): complete surface
        // palettes for both modes. Brand, accent, status and focus roles stay
        // Classic's; BrandPaletteTest checks every text role on every ground,
        // including each sidebar treatment.
        $ledger = [
            'light' => array_replace($classic['light'], [
                '--surface' => '#f9fbf8', '--surface-2' => '#eef1ed', '--surface-3' => '#e2e8e3',
                '--surface-page' => '#eef1ed', '--surface-raised' => '#f9fbf8',
                '--surface-sunken' => '#e2e8e3', '--surface-soft' => '#f4f7f3',
                '--surface-cool' => '#e6ece8', '--surface-inverse' => '#1c252b',
                '--border' => '#cfd8d1', '--border-hair' => '#cfd8d1', '--border-soft' => '#dce3dd',
                '--border-strong' => '#87958b',
                '--brand-subtle' => '#dde9df', '--on-brand-subtle' => '#24402f', '--gold-soft' => '#f1e8cc',
            ]),
            'dark' => array_replace($classic['dark'], [
                '--surface' => '#222d35', '--surface-2' => '#131a1f', '--surface-3' => '#1a232a',
                '--surface-page' => '#131a1f', '--surface-raised' => '#1a232a',
                '--surface-sunken' => '#222d35', '--surface-soft' => '#1e2830',
                '--surface-inverse' => '#eef1ed',
                '--border' => '#34424c', '--border-hair' => '#34424c', '--border-soft' => '#29353e',
                '--border-strong' => '#506170',
            ]),
        ];
        $vellum = [
            'light' => array_replace($classic['light'], [
                '--surface' => '#fffdf8', '--surface-2' => '#f3f0e9', '--surface-3' => '#e9e4d9',
                '--surface-page' => '#f3f0e9', '--surface-raised' => '#fffdf8',
                '--surface-sunken' => '#e9e4d9', '--surface-soft' => '#f9f7f1',
                '--surface-cool' => '#edece6', '--surface-inverse' => '#23211d',
                '--border' => '#dad3c4', '--border-hair' => '#dad3c4', '--border-soft' => '#e5e0d5',
                '--border-strong' => '#958e80',
            ]),
            'dark' => array_replace($classic['dark'], [
                '--surface' => '#2a2622', '--surface-2' => '#181614', '--surface-3' => '#211e1b',
                '--surface-page' => '#181614', '--surface-raised' => '#211e1b',
                '--surface-sunken' => '#2a2622', '--surface-soft' => '#25221e',
                '--surface-inverse' => '#f3f0e9',
                '--text' => '#f6f1e8', '--text-strong' => '#fbf8f2', '--text-body' => '#e4ded3',
                '--text-muted' => '#c2baad', '--text-faint' => '#aaa295',
                '--border' => '#3c3732', '--border-hair' => '#3c3732', '--border-soft' => '#322e29',
                '--border-strong' => '#5e574f',
            ]),
        ];
        return self::previewDefaults(['classic' => $classic, 'retro' => $retro, 'ledger' => $ledger, 'vellum' => $vellum]);
    }

    /** Local defaults stop a cleared preview inheriting saved overrides from html. */
    private static function previewDefaults(array $presets): array
    {
        foreach ($presets as &$modes) {
            foreach ($modes as &$palette) {
                $palette['--brand-primary'] = $palette['--accent'];
                $palette['--brand-contrast'] ??= '#faf6ec';
                $palette['--brand-hover-contrast'] = self::contrastInk($palette['--brand-hover']);
                $palette['--brand-press-contrast'] = self::contrastInk($palette['--brand-press']);
                $palette['--brand-primary-hover'] = self::mix($palette['--accent'], '#000000', .82);
                $palette['--brand-primary-press'] = $palette['--brand-primary-hover'];
                $palette['--brand-primary-hover-contrast'] = $palette['--accent-contrast'];
                $palette['--brand-primary-press-contrast'] = $palette['--accent-contrast'];
                $palette['--brand-accent'] = $palette['--accent-2'];
                $palette['--brand-accent-contrast'] = self::contrastInk($palette['--accent-2']);
                $palette['--brand-accent-hover'] = '#b08a3a';
                $palette['--brand-accent-hover-contrast'] = '#1b231d';
            }
        }
        return $presets;
    }

    /** @param array<string,string> $palette @return array<string,string> */
    public static function withColours(array $palette, string $primary, string $accent): array
    {
        $surface = $palette['--surface-raised'] ?? '#faf6ec';
        if (self::isHex($primary)) {
            $palette['--accent'] = strtolower($primary);
            $palette['--brand-primary'] = strtolower($primary);
            $palette['--accent-contrast'] = self::contrastInk($primary);
            $palette['--brand'] = strtolower($primary);
            $palette['--brand-contrast'] = self::contrastInk($primary);
            $palette['--brand-subtle'] = self::mix($primary, $surface, .14);
            $palette['--on-brand-subtle'] = self::readableTone($primary, $palette['--brand-subtle']);
            foreach (['hover' => .82, 'press' => .68] as $state => $weight) {
                $fill = self::actionShade($primary, $weight);
                $palette['--brand-' . $state] = $fill;
                $palette['--brand-' . $state . '-contrast'] = self::contrastInk($fill);
                $palette['--brand-primary-' . $state] = $fill;
                $palette['--brand-primary-' . $state . '-contrast'] = self::contrastInk($fill);
            }
            $palette['--focus-ring'] = strtolower($primary);
        }
        if (self::isHex($accent)) {
            $palette['--accent-2'] = strtolower($accent);
            $palette['--brand-accent'] = strtolower($accent);
            $palette['--gold'] = strtolower($accent);
            $palette['--rule-gold'] = strtolower($accent);
            $palette['--gold-soft'] = self::mix($accent, $surface, .14);
            $palette['--gold-ink'] = self::readableTone($accent, $palette['--gold-soft']);
            $palette['--brand-accent-hover'] = self::actionShade($accent, .82);
            $palette['--brand-accent-hover-contrast'] = self::contrastInk($palette['--brand-accent-hover']);
        }
        if (isset($palette['--accent-2'])) {
            $palette['--brand-accent-contrast'] = self::contrastInk($palette['--accent-2']);
        }
        return $palette;
    }

    /** @param array<string,array<string,string>> $palette */
    public static function css(array $palette): string
    {
        $dark = self::declarations($palette['dark']);
        return ':root{' . self::declarations($palette['light']) . '}'
            . "\n" . '[data-theme="dark"]{' . $dark . '}'
            . "\n" . '@media (prefers-color-scheme: dark){:root[data-theme="system"]{' . $dark . '}}';
    }

    /** @param array<string,array<string,array<string,string>>> $presets */
    /**
     * Each mode's primary and accent (ADR 0054): a mode-specific override, else
     * the shared override, else '' — which keeps the package or preset role.
     *
     * @param array<string,mixed> $document branding settings by key
     * @return array{light:array{primary:string,accent:string},dark:array{primary:string,accent:string}}
     */
    public static function modeColours(array $document): array
    {
        $pick = static function (string $key) use ($document): string {
            $value = $document[$key] ?? '';
            return is_string($value) && self::isHex($value) ? strtolower($value) : '';
        };
        $colours = [];
        foreach (['light', 'dark'] as $mode) {
            $colours[$mode] = [
                'primary' => $pick('brand_color_primary_' . $mode) ?: $pick('brand_color_primary'),
                'accent' => $pick('brand_color_accent_' . $mode) ?: $pick('brand_color_accent'),
            ];
        }
        return $colours;
    }

    /**
     * The same two colours for both modes: the shared-override shape.
     *
     * @return array{light:array{primary:string,accent:string},dark:array{primary:string,accent:string}}
     */
    public static function sharedColours(string $primary, string $accent): array
    {
        return self::modeColours(['brand_color_primary' => $primary, 'brand_color_accent' => $accent]);
    }

    /** @param array{light:array{primary:string,accent:string},dark:array{primary:string,accent:string}}|string $colours */
    public static function previewCss(array $presets, array|string $colours, string $accent = ''): string
    {
        $colours = is_array($colours) ? $colours : self::sharedColours($colours, $accent);
        $css = '';
        foreach ($presets as $preset => $modes) {
            $selector = '.brand-preview[data-brand-preset="' . $preset . '"]';
            foreach ($modes as $mode => $palette) {
                $palette = self::withColours($palette, $colours[$mode]['primary'], $colours[$mode]['accent']);
                $palette['--preview-accent'] = $palette['--accent'];
                $palette['--preview-accent-contrast'] = $palette['--accent-contrast'];
                $palette['--preview-accent-2'] = $palette['--accent-2'];
                $palette['--preview-accent-2-contrast'] = $palette['--brand-accent-contrast'];
                $rule = $selector . '[data-brand-theme="' . $mode . '"]';
                $system = $selector . '[data-brand-theme="system"]';
                $declarations = '{' . self::declarations($palette) . '}';
                $css .= "\n" . $rule . $declarations;
                $css .= $mode === 'dark'
                    ? "\n" . '@media (prefers-color-scheme: dark){' . $system . $declarations . '}'
                    : "\n" . $system . $declarations;
            }
        }
        return $css;
    }

    /** Thumbnail variable => the resolved palette role it pictures. */
    private const THUMBNAIL_ROLES = [
        '--thumb-page' => '--surface-page',
        '--thumb-panel' => '--surface-raised',
        '--thumb-rule' => '--border-hair',
        '--thumb-text' => '--text-body',
        '--thumb-faint' => '--text-faint',
        // Filled buttons paint from --accent/--accent-contrast (app.css .btn).
        '--thumb-action' => '--accent',
        '--thumb-action-ink' => '--accent-contrast',
        '--thumb-selected' => '--brand-subtle',
        '--thumb-accent' => '--accent-2',
    ];

    /**
     * Member appearance thumbnails (ADR 0054). Each mode's picture is scoped to
     * its own element, so a member in dark mode still sees the true light
     * palette in the Light choice. Values come from the same resolved palettes
     * as /brand.css: active package or preset, then operator colours.
     *
     * @param array<string,array<string,string>> $modes resolved palettes keyed light/dark
     */
    public static function thumbnailCss(array $modes): string
    {
        $css = '';
        foreach (['light', 'dark'] as $mode) {
            $declarations = [];
            foreach (self::THUMBNAIL_ROLES as $variable => $role) {
                $value = $modes[$mode][$role] ?? null;
                if (is_string($value) && self::isColourValue($value)) {
                    $declarations[$variable] = $value;
                }
            }
            if ($declarations !== []) {
                $css .= "\n" . '.theme-thumb [data-thumb-mode="' . $mode . '"]{' . self::declarations($declarations) . '}';
            }
        }
        return $css;
    }

    /** The two colour forms the code-owned and package palettes use. */
    private static function isColourValue(string $value): bool
    {
        return preg_match('/^(?:#[0-9a-fA-F]{6}|rgba\(\d{1,3},\d{1,3},\d{1,3},(?:0|1|0?\.\d{1,3})\))$/D', $value) === 1;
    }

    /**
     * Both modes matter even when the guest default is explicit: members choose
     * their own mode. Report the weakest real surface instead of button-only ink.
     * @param array<string,array<string,string>> $modes
     * @return list<string>
     */
    public static function warnings(array $modes, string $primary, string $accent): array
    {
        return self::modeWarnings($modes, self::sharedColours($primary, $accent));
    }

    /**
     * Contrast review with each mode's own colours (ADR 0054). A warning names
     * the field to change: the mode's override when one is set, else the
     * shared colour.
     *
     * @param array<string,array<string,string>> $modes
     * @param array{light:array{primary:string,accent:string},dark:array{primary:string,accent:string}} $colours
     * @param array<string,bool> $overridden e.g. ['primary_dark' => true]
     * @return list<string>
     */
    public static function modeWarnings(array $modes, array $colours, array $overridden = []): array
    {
        $warnings = [];
        $buttonChecks = [];
        foreach ($modes as $mode => $palette) {
            $primary = $colours[$mode]['primary'] ?? '';
            $accent = $colours[$mode]['accent'] ?? '';
            $primaryLabel = !empty($overridden['primary_' . $mode]) ? ucfirst($mode) . ' mode primary' : 'Primary colour';
            $accentLabel = !empty($overridden['accent_' . $mode]) ? ucfirst($mode) . ' mode accent' : 'Accent colour';
            $surfaces = array_intersect_key($palette, array_flip([
                '--surface', '--surface-2', '--surface-3',
                '--surface-page', '--surface-raised', '--surface-sunken',
            ]));
            foreach ([$primaryLabel => [$primary, 4.5, 'links'], $accentLabel => [$accent, 3.0, 'indicators']] as $label => [$colour, $minimum, $use]) {
                if (!self::isHex($colour)) {
                    continue;
                }
                $buttonChecks[$label] = $colour;
                $ratio = min(array_map(static fn (string $surface): float => self::contrastRatio($colour, $surface), $surfaces));
                if ($ratio < $minimum) {
                    $warnings[] = sprintf('%s gives %s only %.2f:1 contrast on %s surfaces; %.1f:1 is needed.', $label, $use, $ratio, $mode, $minimum);
                }
            }
            $resolved = self::withColours($palette, $primary, $accent);
            $pairs = [];
            if (self::isHex($primary)) {
                $pairs += [
                    'selected navigation' => ['--on-brand-subtle', '--brand-subtle'],
                    'selected menu' => ['--brand-contrast', '--brand'],
                    'hovered actions' => ['--brand-hover-contrast', '--brand-hover'],
                    'pressed actions' => ['--brand-press-contrast', '--brand-press'],
                ];
            }
            if (self::isHex($accent)) {
                $pairs['unread counts'] = ['--gold-ink', '--gold-soft'];
                $pairs['hovered accent actions'] = ['--brand-accent-hover-contrast', '--brand-accent-hover'];
                // Accent ink is also used on ordinary content (board labels).
                $ratio = min(array_map(static fn (string $surface): float => self::contrastRatio($resolved['--gold-ink'], $surface), $surfaces));
                if ($ratio < 4.5) {
                    $warnings[] = sprintf('Accent text gives board labels only %.2f:1 contrast on %s surfaces; 4.5:1 is needed.', $ratio, $mode);
                }
            }
            foreach ($pairs as $use => [$foreground, $background]) {
                $ratio = self::contrastRatio($resolved[$foreground], $resolved[$background]);
                if ($ratio < 4.5) {
                    $warnings[] = sprintf('Colours give %s only %.2f:1 contrast in %s; 4.5:1 is needed.', $use, $ratio, $mode);
                }
            }
        }
        foreach ($buttonChecks as $label => $colour) {
            if (self::contrastRatio($colour, self::contrastInk($colour)) < 4.5) {
                $warnings[] = $label . ' does not support readable button text at 4.5:1 contrast.';
            }
        }
        return array_values(array_unique($warnings));
    }

    /**
     * An automatic suggestion for one mode (ADR 0054): the nearest tone of
     * $colour, moved toward the mode's readable ink, that passes that mode's
     * whole contrast review for $role — the same checks publishing runs, so
     * accepting it clears the role's warnings there. Returns $colour when it
     * already passes and '' when no tone of it can.
     *
     * @param array<string,string> $palette one mode's base palette, as reviewed
     * @param 'primary'|'accent' $role
     */
    public static function suggestion(string $colour, array $palette, string $role = 'primary'): string
    {
        $ground = $palette['--surface-page'] ?? '';
        if (!self::isHex($colour) || !is_string($ground) || !self::isHex($ground)) {
            return '';
        }
        $target = self::contrastRatio('#ffffff', $ground) >= self::contrastRatio('#000000', $ground) ? '#ffffff' : '#000000';
        $other = $role === 'accent' ? 'primary' : 'accent';
        for ($step = 0; $step <= 100; ++$step) {
            $tone = self::mix(strtolower($colour), $target, 1 - $step / 100);
            // Only this role is set, so every warning is this role's.
            if (self::modeWarnings(['mode' => $palette], ['mode' => [$role => $tone, $other => '']]) === []) {
                return $tone;
            }
        }
        return '';
    }

    public static function isHex(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    public static function contrastInk(string $colour): string
    {
        $ink = self::contrastRatio($colour, '#ffffff') >= self::contrastRatio($colour, '#0f1218') ? '#ffffff' : '#0f1218';
        return self::contrastRatio($colour, $ink) >= 4.5 ? $ink : '#000000';
    }

    /** Same sRGB interpolation as the enhanced preview, rounded to whole channels. */
    private static function mix(string $colour, string $ground, float $weight): string
    {
        $result = '#';
        foreach ([1, 3, 5] as $offset) {
            $channel = (int) round(hexdec(substr($colour, $offset, 2)) * $weight + hexdec(substr($ground, $offset, 2)) * (1 - $weight));
            $result .= str_pad(dechex($channel), 2, '0', STR_PAD_LEFT);
        }
        return $result;
    }

    private static function actionShade(string $colour, float $weight): string
    {
        $ink = self::contrastInk($colour);
        // Retain one readable label colour through hover/press, including
        // midpoint colours whose dark ink cannot tolerate the full darkening.
        for ($step = (int) round($weight * 100); $step <= 100; ++$step) {
            $shade = self::mix($colour, '#000000', $step / 100);
            if (self::contrastRatio($shade, $ink) >= 4.5) {
                return $shade;
            }
        }
        return strtolower($colour);
    }

    private static function readableTone(string $colour, string $surface): string
    {
        $target = self::contrastInk($surface);
        for ($step = 0; $step <= 100; ++$step) {
            $tone = self::mix($colour, $target, 1 - $step / 100);
            if (self::contrastRatio($tone, $surface) >= 4.5) {
                return $tone;
            }
        }
        return $target;
    }

    public static function contrastRatio(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $sum = 0.0;
            foreach ([0.2126, 0.7152, 0.0722] as $i => $weight) {
                $channel = hexdec(substr($hex, 1 + $i * 2, 2)) / 255;
                $sum += ($channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4) * $weight;
            }
            return $sum;
        };
        $l1 = $lum($a);
        $l2 = $lum($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** @param array<string,string> $tokens */
    private static function declarations(array $tokens): string
    {
        $css = '';
        foreach ($tokens as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }
        return $css;
    }
}
