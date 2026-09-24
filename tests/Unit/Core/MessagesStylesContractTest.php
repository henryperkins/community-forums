<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Stylesheet contracts for the Messages surface and the shared prose links it
 * renders (the 027d878 Messages audit: P1-3, P1-4, P1-6, P2-2, P2-4, P2-5,
 * P3-3, P3-6). These pin invariants, not line positions, so the planned merge
 * of the three generations of Messages CSS can move rules freely as long as
 * the behaviour survives. The browser evidence proves the rendered result.
 */
final class MessagesStylesContractTest extends TestCase
{
    private const PRIMITIVE = '/var\(--(?:river|gold|green|parchment|ink|mist|twilight)-\d/';

    public function test_prose_links_are_underlined_at_rest_and_keep_the_gold_rule(): void
    {
        $css = self::css();
        $rest = self::rule($css, '.formatted-content a');

        // WCAG 1.4.1: a link inside a sentence is marked by more than its hue.
        self::assertMatchesRegularExpression('/text-decoration-line:\s*underline/', $rest);
        // Longhands only: the `text-decoration` shorthand would reset the
        // global `a` rule's translucent-gold underline colour to currentColor.
        self::assertDoesNotMatchRegularExpression('/(^|;)\s*text-decoration\s*:/', $rest);
        self::assertMatchesRegularExpression(
            '/(^|})\s*a\s*\{[^}]*text-decoration-color:\s*color-mix\(in srgb, var\(--gold-500\) 45%, transparent\)/',
            $css,
        );
        self::assertMatchesRegularExpression('/text-decoration-thickness:/', self::rule($css, '.formatted-content a:hover'));
        // Only an image link goes without — mentions are plain links and stay ruled.
        self::assertMatchesRegularExpression(
            '/text-decoration-line:\s*none/',
            self::rule($css, '.formatted-content a:has(> img:not(.custom-emoji))'),
        );
        self::assertStringNotContainsString('.formatted-content a:not(.mention)', $css);
    }

    public function test_messages_pager_links_are_controls_not_prose(): void
    {
        $pager = self::rule(self::css(), '.dm-earlier, .dm-latest', 'border');

        self::assertMatchesRegularExpression('/border:\s*1\.5px solid var\(--border-soft\)/', $pager);
        self::assertMatchesRegularExpression('/background:\s*var\(--surface-raised\)/', $pager);
        self::assertMatchesRegularExpression('/font-family:\s*var\(--font-label\)/', $pager);
        self::assertMatchesRegularExpression('/text-decoration:\s*none/', $pager);
    }

    public function test_messages_rules_paint_from_semantic_tokens_only(): void
    {
        $offenders = [];
        foreach (self::rules(self::css()) as [$selector, $body]) {
            $selectors = array_map('trim', explode(',', $selector));
            // A rule is a Messages rule when every selector in it is one; a
            // shared rule that merely lists a .dm- member is not.
            $isMessages = $selectors !== [] && array_filter(
                $selectors,
                static fn (string $s): bool => !str_contains($s, '.dm-'),
            ) === [];
            if (!$isMessages && $selector !== '.reference-card:hover') {
                continue;
            }
            foreach (array_filter(array_map('trim', explode(';', $body))) as $declaration) {
                // Custom-property declarations are the token layer itself.
                if (str_starts_with($declaration, '--')) {
                    continue;
                }
                if (preg_match(self::PRIMITIVE, $declaration) === 1) {
                    $offenders[] = $selector . ' { ' . $declaration . ' }';
                }
            }
        }

        self::assertSame([], $offenders, "Messages rules must paint from semantic tokens (DESIGN.md Semantic-Only):\n" . implode("\n", $offenders));
    }

    public function test_every_explicit_twilight_messages_rule_has_a_system_dark_twin(): void
    {
        $css = self::css();
        $flat = (string) preg_replace('/\s+/', ' ', $css);
        preg_match_all('/\[data-theme="dark"\]\s*([^{},]*\.dm-[^{},]*)\{([^}]*)\}/', $css, $matches, PREG_SET_ORDER);
        self::assertNotEmpty($matches, 'expected at least one explicit-twilight Messages rule');

        foreach ($matches as [, $selector, $body]) {
            $twin = '[data-theme="system"] ' . trim((string) preg_replace('/\s+/', ' ', $selector))
                . ' { ' . trim((string) preg_replace('/\s+/', ' ', $body)) . ' }';
            $at = strpos($flat, $twin);
            self::assertNotFalse(
                $at,
                'Members default to data-theme="system"; an OS-dark twin is missing for: ' . trim($selector),
            );
            $media = strrpos(substr($flat, 0, (int) $at), '@media');
            self::assertNotFalse($media);
            self::assertStringStartsWith(
                '@media (prefers-color-scheme: dark) {',
                substr($flat, (int) $media),
                'The system twin for ' . trim($selector) . ' must sit under prefers-color-scheme: dark.',
            );
        }
    }

    public function test_the_room_never_transitions_its_layout(): void
    {
        foreach (self::rules(self::css()) as [$selector, $body]) {
            if (!str_contains($selector, '.dm-')) {
                continue;
            }
            if (preg_match('/(?:^|;)\s*transition(?:-property)?\s*:([^;]*)/', $body, $transition) !== 1) {
                continue;
            }
            self::assertDoesNotMatchRegularExpression(
                '/grid-template|width|height|(^|[\s,])all([\s,]|$)/',
                $transition[1],
                $selector . ' animates layout; use opacity/transform or no transition.',
            );
        }
    }

    public function test_filter_pills_search_and_recipient_field_use_the_system_states(): void
    {
        $css = self::css();

        $active = self::rule($css, '.dm-listpane-filters .pill.is-active');
        self::assertMatchesRegularExpression('/background:\s*var\(--brand\)/', $active);
        self::assertMatchesRegularExpression('/color:\s*var\(--dm-on-brand\)/', $active);

        self::assertDoesNotMatchRegularExpression('/outline:\s*(none|0)\b/', self::rule($css, '.dm-search input'));
        foreach (['.dm-search input:focus', '.dm-to-field:focus-within'] as $selector) {
            $focus = self::rule($css, $selector);
            self::assertMatchesRegularExpression('/outline:\s*2px solid var\(--accent\)/', $focus, $selector);
            self::assertMatchesRegularExpression('/0 0 0 3px var\(--focus-ring\)/', $focus, $selector);
            self::assertStringNotContainsString('--dm-focus-ring', $focus, $selector);
        }
    }

    public function test_touch_targets_reach_44px_and_small_labels_meet_the_type_floor(): void
    {
        $css = self::css();

        self::assertSame(1, preg_match('/@media \(pointer: coarse\) \{(?<block>.*?)\n\}/s', $css, $touch));
        self::assertMatchesRegularExpression('/\.dm-back, \.dm-iconbtn \{ width: 44px; height: 44px; \}/', $touch['block']);
        self::assertMatchesRegularExpression('/\.dm-earlier, \.dm-latest \{ min-height: 44px; \}/', $touch['block']);
        self::assertStringContainsString('inset: min(0px, calc((100% - 44px) / 2));', $touch['block']);
        foreach (['.dm-new-btn::after', '.dm-listpane-filters .pill::after', '.dm-dotbtn::after'] as $hit) {
            self::assertStringContainsString($hit, $touch['block']);
        }
        self::assertMatchesRegularExpression('/\.dm-linkbtn \{[^}]*min-height: 44px/', $touch['block']);

        foreach (['.dm-thread-eyebrow', '.dm-rail-sec > h3', '.dm-tier-pill'] as $selector) {
            self::assertMatchesRegularExpression(
                '/font-size:\s*var\(--text-(chip|eyebrow)\)/',
                self::rule($css, $selector, 'font-size'),
                $selector . ' must sit on the type ramp at or above its 0.7rem floor.',
            );
        }
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

    /** The body of the last rule whose selector is exactly $selector (optionally one that sets $property). */
    private static function rule(string $css, string $selector, ?string $property = null): string
    {
        $found = null;
        foreach (self::rules($css) as [$candidate, $body]) {
            if ($candidate === $selector && ($property === null || preg_match('/(^|;)\s*' . preg_quote($property, '/') . '\s*:/', $body) === 1)) {
                $found = $body;
            }
        }
        self::assertNotNull($found, $selector . ' is missing from the application stylesheet.');

        return (string) $found;
    }
}
