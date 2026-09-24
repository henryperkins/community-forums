<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Stylesheet contracts from the login/logout polish pass (ADR 0039). They pin
 * invariants, not line positions; the browser evidence proves the rendered
 * result (`tests/browser/auth-polish.spec.ts`).
 */
final class AuthGateStylesContractTest extends TestCase
{
    private const PRIMITIVE = '/var\(--(?:river|gold|green|parchment|ink|mist|twilight)-\d/';

    public function test_the_auth_stage_paints_from_semantic_tokens_only(): void
    {
        $offenders = [];
        $seen = 0;
        foreach (self::rules(self::css()) as [$selector, $body]) {
            $selectors = array_map('trim', explode(',', $selector));
            $isStage = $selectors !== [] && array_filter(
                $selectors,
                static fn (string $s): bool => preg_match('/^\.(?:variant-auth|auth-stage|auth-brand)\b/', $s) !== 1,
            ) === [];
            if (!$isStage) {
                continue;
            }
            $seen++;
            foreach (array_filter(array_map('trim', explode(';', $body))) as $declaration) {
                if (!str_starts_with($declaration, '--') && preg_match(self::PRIMITIVE, $declaration) === 1) {
                    $offenders[] = $selector . ' { ' . $declaration . ' }';
                }
            }
        }

        self::assertGreaterThan(5, $seen, 'The auth stage rules were not found.');
        self::assertSame([], $offenders, "The auth stage is twilight in both registers and paints from --stage-* (DESIGN.md Semantic-Only):\n" . implode("\n", $offenders));
    }

    public function test_the_stage_tokens_hold_in_both_registers(): void
    {
        $css = self::css();
        foreach (['--stage-ground', '--stage-raised', '--stage-ink', '--stage-accent'] as $token) {
            self::assertMatchesRegularExpression('/:root\s*\{[^}]*' . preg_quote($token, '/') . '\s*:/', $css, $token . ' is not defined.');
            // The stage must not flip, so no twilight register may re-point it.
            self::assertDoesNotMatchRegularExpression('/\[data-theme="(?:dark|system)"\]\s*\{[^}]*' . preg_quote($token, '/') . '\s*:/', $css);
        }
    }

    public function test_focus_on_the_stage_takes_the_stage_gold(): void
    {
        $focus = self::rule(self::css(), '.variant-auth .auth-brand:focus-visible, .variant-auth .skip-link:focus-visible');

        // The page's --accent is evergreen by day: 1.75:1 on the stage ground.
        self::assertMatchesRegularExpression('/outline-color:\s*var\(--stage-accent\)/', $focus);
    }

    public function test_an_engraved_frame_draws_its_edge_in_the_field_rule(): void
    {
        $css = self::css();
        $frame = self::rule($css, '.input-engraved, .textarea-engraved, textarea.textarea-engraved');

        self::assertMatchesRegularExpression('/border:\s*1\.5px solid var\(--field-rule\)/', $frame);
        // gold-200 measured 1.30:1 on the parchment card; 1.4.11 asks 3:1.
        self::assertMatchesRegularExpression('/:root\s*\{[^}]*--field-rule:\s*var\(--gold-700\)/', $css);
        // Twilight takes gold-600 in BOTH registers, or `system` stays on gold-700.
        self::assertMatchesRegularExpression('/\[data-theme="dark"\]\s*\{[^}]*--field-rule:\s*var\(--gold-600\)/', $css);
        self::assertMatchesRegularExpression(
            '/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*\[data-theme="system"\]\s*\{[^}]*--field-rule:\s*var\(--gold-600\)/',
            $css,
        );
        // The DM composer it once listed is always a .composer-shell now.
        self::assertStringNotContainsString('.dm-form:not(.composer-shell)', $css);
    }

    public function test_auth_links_keep_the_page_link_colour(): void
    {
        foreach (self::rules(self::css()) as [$selector, $body]) {
            if (in_array('.auth-links a', array_map('trim', explode(',', $selector)), true)) {
                // --brand is green-500 by night: 2.86:1 on the twilight card.
                self::assertDoesNotMatchRegularExpression('/(^|;)\s*color\s*:/', $body, $selector);
            }
        }
        self::assertMatchesRegularExpression('/text-decoration:\s*underline/', self::rule(self::css(), '.auth-links a, .callout a, .muted a, .empty a, .directory-guest-note a'));
    }

    public function test_a_button_hover_deepens_its_own_fill(): void
    {
        $hover = self::rule(self::css(), '.btn:hover');

        // --brand-hover is evergreen in both registers and untouched by branding.
        self::assertMatchesRegularExpression('/background:\s*color-mix\(in srgb, var\(--accent\) 82%, #000\)/', $hover);
        self::assertStringNotContainsString('--brand-hover', $hover);
    }

    private static function css(): string
    {
        $css = (string) file_get_contents(dirname(__DIR__, 3) . '/public/assets/app.css');

        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /** @return list<array{0:string,1:string}> innermost `selector { body }` pairs */
    private static function rules(string $css): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $m): array => [trim((string) preg_replace('/\s+/', ' ', $m[1])), $m[2]],
            $matches,
        );
    }

    /** The body of the last rule whose selector is exactly $selector. */
    private static function rule(string $css, string $selector): string
    {
        $found = null;
        foreach (self::rules($css) as [$candidate, $body]) {
            if ($candidate === $selector) {
                $found = $body;
            }
        }
        self::assertNotNull($found, $selector . ' is missing from the application stylesheet.');

        return (string) $found;
    }
}
