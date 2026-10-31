<?php

declare(strict_types=1);

namespace App\Support;

use App\Security\Packages\ThemeTokenPolicy;

/** Resolved branding colours shared by delivery, preview, and contrast review. */
final class BrandPalette
{
    /** @return array<string,array<string,array<string,string>>> */
    public static function presets(?array $packageTokens = null): array
    {
        $classic = [
            'light' => array_replace(ThemeTokenPolicy::baseline('light'), [
                '--surface-page' => '#f5efe1', '--surface-raised' => '#faf6ec',
                '--surface-sunken' => '#ece4d2', '--surface-cool' => '#eef1ed',
                '--surface-inverse' => '#1e2730', '--border-hair' => '#ded2b8',
                '--border-soft' => '#dce3dd', '--border-strong' => '#94a095',
                '--text-body' => '#313b33', '--text-faint' => '#5c685d',
                '--gold-soft' => '#f4ebcf', '--gold-ink' => '#7e5f22',
                '--field-rule' => '#9a7530',
            ]),
            'dark' => array_replace(ThemeTokenPolicy::baseline('dark'), [
                '--surface-page' => '#161d24', '--surface-raised' => '#1e2730',
                '--surface-sunken' => '#283440', '--surface-cool' => '#eef1ed',
                '--surface-inverse' => '#f5efe1', '--border-hair' => '#36434f',
                '--border-soft' => '#2c3742', '--border-strong' => '#51606c',
                '--text-body' => '#d7dcd2', '--text-faint' => '#94a095',
                '--brand-subtle' => 'rgba(78,116,89,.20)',
                '--gold-soft' => 'rgba(194,154,68,.16)', '--gold-ink' => '#d2b062',
                '--field-rule' => '#b08a3a',
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
            // An active package owns the preset; the stored Retro choice resumes
            // only when the package is disabled or safe mode suppresses it.
            return ['classic' => $classic, 'retro' => $classic];
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
                '--brand-subtle' => '#49331e', '--on-brand-subtle' => '#f0d3a2',
                '--border' => '#776047', '--border-hair' => '#776047', '--border-soft' => '#57432f',
                '--border-strong' => '#ad8b5e', '--field-rule' => '#c79c60',
                '--gold-soft' => '#49331e', '--gold-ink' => '#f0c98c', '--rule-gold' => '#e6ad69',
            ]),
        ];
        return ['classic' => $classic, 'retro' => $retro];
    }

    /** @param array<string,string> $palette @return array<string,string> */
    public static function withColours(array $palette, string $primary, string $accent): array
    {
        if (self::isHex($primary)) {
            $palette['--accent'] = strtolower($primary);
            $palette['--brand-primary'] = strtolower($primary);
            $palette['--accent-contrast'] = self::contrastInk($primary);
        }
        if (self::isHex($accent)) {
            $palette['--accent-2'] = strtolower($accent);
            $palette['--brand-accent'] = strtolower($accent);
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
    public static function previewCss(array $presets, string $primary, string $accent): string
    {
        $css = '';
        foreach ($presets as $preset => $modes) {
            $selector = '.brand-preview[data-brand-preset="' . $preset . '"]';
            foreach ($modes as $mode => $palette) {
                $palette = self::withColours($palette, $primary, $accent);
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

    /**
     * Both modes matter even when the guest default is explicit: members choose
     * their own mode. Report the weakest real surface instead of button-only ink.
     * @param array<string,array<string,string>> $modes
     * @return list<string>
     */
    public static function warnings(array $modes, string $primary, string $accent): array
    {
        $warnings = [];
        foreach ($modes as $mode => $palette) {
            $surfaces = array_intersect_key($palette, array_flip([
                '--surface', '--surface-2', '--surface-3',
                '--surface-page', '--surface-raised', '--surface-sunken',
            ]));
            foreach (['Primary colour' => [$primary, 4.5, 'links'], 'Accent colour' => [$accent, 3.0, 'indicators']] as $label => [$colour, $minimum, $use]) {
                if (!self::isHex($colour)) {
                    continue;
                }
                $ratio = min(array_map(static fn (string $surface): float => self::contrastRatio($colour, $surface), $surfaces));
                if ($ratio < $minimum) {
                    $warnings[] = sprintf('%s gives %s only %.2f:1 contrast on %s surfaces; %.1f:1 is needed.', $label, $use, $ratio, $mode, $minimum);
                }
            }
        }
        foreach (['Primary colour' => $primary, 'Accent colour' => $accent] as $label => $colour) {
            if (self::isHex($colour) && self::contrastRatio($colour, self::contrastInk($colour)) < 4.5) {
                $warnings[] = $label . ' does not support readable button text at 4.5:1 contrast.';
            }
        }
        return $warnings;
    }

    public static function isHex(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    public static function contrastInk(string $colour): string
    {
        return self::contrastRatio($colour, '#ffffff') >= self::contrastRatio($colour, '#0f1218') ? '#ffffff' : '#0f1218';
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
