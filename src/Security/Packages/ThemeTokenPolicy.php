<?php

declare(strict_types=1);

namespace App\Security\Packages;

/**
 * Code-owned catalogue of the design tokens a declarative theme package may
 * set (P5-03 Gate A: tokens + approved local assets only — PHASE_5_PLAN §4
 * lines 120-123, §5 #14). Token NAMES are a closed whitelist and token VALUES
 * match per-type grammars that structurally cannot express selectors,
 * additional declarations, url()/@import, or script-like constructs
 * (TM-TH-01/TM-TH-02). Also owns the WCAG pairs the build gate enforces
 * (TM-TH-04) and the app.css baseline values used to compute effective
 * contrast for partial token sets. Mirrors ApiScopes/CapabilityCatalog:
 * static data, not a service.
 */
final class ThemeTokenPolicy
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, 'color'|'length'|'font'|'asset'> */
    public const TOKENS = [
        // Semantic surfaces / text / lines (the brand.css-compatible set).
        '--surface' => 'color', '--surface-2' => 'color', '--surface-3' => 'color',
        '--border' => 'color', '--text' => 'color', '--text-muted' => 'color',
        '--text-strong' => 'color', '--text-body' => 'color', '--text-faint' => 'color',
        '--text-inverse' => 'color', '--accent' => 'color', '--accent-contrast' => 'color',
        '--accent-2' => 'color', '--danger' => 'color',
        // Imladris brand ramp.
        '--brand' => 'color', '--brand-hover' => 'color', '--brand-press' => 'color',
        '--brand-subtle' => 'color', '--on-brand-subtle' => 'color',
        '--gold' => 'color', '--gold-soft' => 'color', '--gold-ink' => 'color',
        // Shape.
        '--radius-sm' => 'length', '--radius-md' => 'length', '--radius-lg' => 'length',
        '--radius-xl' => 'length', '--radius-pill' => 'length', '--radius' => 'length',
        // Typography (names only — CSP + the zero-url() grammar make remote fonts impossible).
        '--font-display' => 'font', '--font-label' => 'font', '--font-body' => 'font',
        '--font-mono' => 'font', '--font' => 'font',
        // Approved asset hook: consumed by app.css `body { background-image: var(--surface-texture, none) }`.
        '--surface-texture' => 'asset',
    ];

    private const GENERIC_FONTS = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
        'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded'];

    // Transcribed from public/assets/app.css (:root / [data-theme="dark"]) — keep in
    // lock-step; ThemeBaselineFidelityTest (Task 5) pins these against the real file.
    private const BASELINE_LIGHT = [
        '--surface' => '#faf6ec',
        '--surface-2' => '#f5efe1',
        '--surface-3' => '#ece4d2',
        '--border' => '#ded2b8',
        '--text' => '#1b231d',
        '--text-muted' => '#515c52',
        '--text-strong' => '#1b231d',
        '--text-inverse' => '#faf6ec',
        '--accent' => '#2e4a3a',
        '--accent-contrast' => '#faf6ec',
        '--accent-2' => '#c29a44',
        '--danger' => '#9c4a33',
        '--brand' => '#2e4a3a',
    ];

    private const BASELINE_DARK = [
        '--surface' => '#283440',
        '--surface-2' => '#161d24',
        '--surface-3' => '#1e2730',
        '--border' => '#36434f',
        '--text' => '#ece4d2',
        '--text-muted' => '#aeb8b4',
        '--text-strong' => '#faf6ec',
        '--text-inverse' => '#1b231d',
        '--accent' => '#d2b062',
        '--accent-contrast' => '#161d24',
        '--accent-2' => '#c29a44',
        '--danger' => '#db8c73',
        '--brand' => '#4e7459',
    ];

    public static function isKnown(string $token): bool
    {
        return isset(self::TOKENS[$token]);
    }

    public static function type(string $token): string
    {
        if (!isset(self::TOKENS[$token])) {
            throw new \InvalidArgumentException("unknown theme token: $token");
        }
        return self::TOKENS[$token];
    }

    /**
     * @param list<string> $assetNames declared asset names (for 'asset' tokens)
     * @return ?string null when valid, else a human-readable refusal
     */
    public static function validateValue(string $token, string $value, array $assetNames): ?string
    {
        if (!isset(self::TOKENS[$token])) {
            return 'Unknown theme token.';
        }
        if (strlen($value) > 256 || $value === '') {
            return 'Token value must be 1-256 characters.';
        }
        // Structural guard shared by every grammar: nothing that can escape a
        // declaration or reference anything may appear at all (TM-TH-01/02).
        if (preg_match('/[{};\\\\<>@]|\/\*|url\s*\(|expression\s*\(|javascript\s*:|data\s*:|!\s*important/i', $value) === 1) {
            return 'Token value contains a forbidden construct.';
        }
        return match (self::TOKENS[$token]) {
            'color' => preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1
                ? null : 'Colour tokens must be a 6-digit hex value like #8f3d12.',
            'length' => preg_match('/\A(0|(?:\d{1,3}|\d{1,2}\.\d{1,2})(?:px|rem|em))\z/', $value) === 1
                ? null : 'Length tokens must be 0 or a px/rem/em value (max 3 digits).',
            'font' => self::fontError($value),
            'asset' => preg_match('/\A[a-z0-9][a-z0-9-]{0,30}\z/', $value) === 1 && in_array($value, $assetNames, true)
                ? null : 'Asset tokens must name a declared theme asset.',
        };
    }

    private static function fontError(string $value): ?string
    {
        foreach (explode(',', $value) as $family) {
            $family = trim($family);
            if ($family === '') {
                return 'Font stacks cannot contain empty entries.';
            }
            if (in_array($family, self::GENERIC_FONTS, true)) {
                continue;
            }
            if (preg_match('/\A"[A-Za-z0-9][A-Za-z0-9 \-]{0,40}"\z/', $family) === 1) {
                continue;
            }
            if (preg_match('/\A[A-Za-z][A-Za-z0-9\-]{0,40}\z/', $family) === 1) {
                continue;
            }
            return 'Font stacks may only contain quoted family names and generic keywords.';
        }
        return null;
    }

    /** @return list<array{fg:string,bg:string,min:float}> */
    public static function contrastPairs(): array
    {
        return [
            ['fg' => '--text', 'bg' => '--surface', 'min' => 4.5],
            ['fg' => '--text', 'bg' => '--surface-2', 'min' => 4.5],
            ['fg' => '--text-muted', 'bg' => '--surface', 'min' => 4.5],
            ['fg' => '--accent-contrast', 'bg' => '--accent', 'min' => 4.5],
            ['fg' => '--text-inverse', 'bg' => '--brand', 'min' => 4.5],
        ];
    }

    /** @return array<string,string> */
    public static function baseline(string $variant): array
    {
        return $variant === 'dark'
            ? array_replace(self::BASELINE_LIGHT, self::BASELINE_DARK)
            : self::BASELINE_LIGHT;
    }
}
