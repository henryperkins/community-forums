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
 * (TM-TH-04) and the generated Forum baseline values used to compute effective
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
        // Brand ramp (legacy names remain part of the package contract).
        '--brand' => 'color', '--brand-hover' => 'color', '--brand-press' => 'color',
        '--brand-subtle' => 'color', '--on-brand-subtle' => 'color',
        '--gold' => 'color', '--gold-soft' => 'color', '--gold-ink' => 'color',
        // Semantic surfaces and rules the shell actually paints from
        // (ADR 0054). Field rules, focus and status families stay
        // application-controlled and are deliberately absent.
        '--surface-page' => 'color', '--surface-raised' => 'color', '--surface-sunken' => 'color',
        '--border-hair' => 'color', '--border-soft' => 'color', '--border-strong' => 'color',
        '--rule-gold' => 'color',
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

    // Transcribed from public/assets/forum-theme.css (:root / [data-theme="dark"])
    // and kept in lock-step by ThemeBaselineFidelityTest.
    private const BASELINE_LIGHT = [
        '--surface' => '#ffffff',
        '--surface-2' => '#f8fafc',
        '--surface-3' => '#eef2f6',
        '--border' => '#cbd5e1',
        '--text' => '#172b4d',
        '--text-muted' => '#475569',
        '--text-strong' => '#172b4d',
        '--text-body' => '#243247',
        '--text-faint' => '#526175',
        '--text-inverse' => '#ffffff',
        '--accent' => '#1d4ed8',
        '--accent-contrast' => '#ffffff',
        '--accent-2' => '#1d4ed8',
        '--danger' => '#b42318',
        '--brand' => '#1d4ed8',
        '--brand-hover' => '#1e40af',
        '--brand-press' => '#1e3a8a',
        '--brand-subtle' => '#e8efff',
        '--on-brand-subtle' => '#1e3a8a',
        '--gold' => '#1d4ed8',
        '--gold-soft' => '#e8efff',
        '--gold-ink' => '#1e40af',
        '--surface-page' => '#ffffff',
        '--surface-raised' => '#ffffff',
        '--surface-sunken' => '#eef2f6',
        '--border-hair' => '#cbd5e1',
        '--border-soft' => '#e2e8f0',
        '--border-strong' => '#64748b',
        '--rule-gold' => '#64748b',
    ];

    private const BASELINE_DARK = [
        '--surface' => '#1f2937',
        '--surface-2' => '#111827',
        '--surface-3' => '#263445',
        '--border' => '#475569',
        '--text' => '#f8fafc',
        '--text-muted' => '#cbd5e1',
        '--text-strong' => '#f8fafc',
        '--text-body' => '#e5e7eb',
        '--text-faint' => '#b6c2d1',
        '--text-inverse' => '#111827',
        '--accent' => '#93c5fd',
        '--accent-contrast' => '#111827',
        '--accent-2' => '#93c5fd',
        '--danger' => '#fda29b',
        '--brand' => '#93c5fd',
        '--brand-hover' => '#bfdbfe',
        '--brand-press' => '#dbeafe',
        '--brand-subtle' => '#243c60',
        '--on-brand-subtle' => '#dbeafe',
        '--gold' => '#93c5fd',
        '--gold-soft' => '#243c60',
        '--gold-ink' => '#bfdbfe',
        '--surface-page' => '#111827',
        '--surface-raised' => '#1f2937',
        '--surface-sunken' => '#263445',
        '--border-hair' => '#475569',
        '--border-soft' => '#334155',
        '--border-strong' => '#94a3b8',
        '--rule-gold' => '#94a3b8',
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
            // The palette's real "text on brand-tinted fill" pairing (chips,
            // active rows, joinbar). --text-inverse on --brand was a plan-level
            // mismodel: app.css never pairs those two, (the original Inc 4 correction is retained in the decision history).
            ['fg' => '--on-brand-subtle', 'bg' => '--brand-subtle', 'min' => 4.5],
            // The semantic surfaces most text actually sits on (ADR 0054).
            ['fg' => '--text-body', 'bg' => '--surface-page', 'min' => 4.5],
            ['fg' => '--text-body', 'bg' => '--surface-raised', 'min' => 4.5],
            ['fg' => '--text-faint', 'bg' => '--surface-raised', 'min' => 4.5],
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
