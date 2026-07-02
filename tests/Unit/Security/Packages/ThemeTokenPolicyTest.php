<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\ThemeTokenPolicy;
use PHPUnit\Framework\TestCase;

final class ThemeTokenPolicyTest extends TestCase
{
    public function test_catalogue_shape_and_types(): void
    {
        $tokens = ThemeTokenPolicy::TOKENS;
        self::assertArrayHasKey('--accent', $tokens);
        self::assertSame('color', ThemeTokenPolicy::type('--accent'));
        self::assertSame('font', ThemeTokenPolicy::type('--font-body'));
        self::assertSame('length', ThemeTokenPolicy::type('--radius'));
        self::assertSame('asset', ThemeTokenPolicy::type('--surface-texture'));
        self::assertFalse(ThemeTokenPolicy::isKnown('--not-a-token'));
        foreach ($tokens as $name => $type) {
            self::assertMatchesRegularExpression('/\A--[a-z][a-z0-9-]{1,40}\z/', $name);
            self::assertContains($type, ['color', 'length', 'font', 'asset']);
        }
        self::assertSame(1, ThemeTokenPolicy::SCHEMA_VERSION);
    }

    public function test_unknown_token_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThemeTokenPolicy::type('--nope');
    }

    /** TM-TH-01: values that could smuggle selectors/declarations are refused. */
    public function test_selector_and_overlay_constructs_are_refused(): void
    {
        $hostile = [
            '#fff;}body{display:none',            // close the block, new selector
            '#ffffff}.login{visibility:hidden',
            'red;position:fixed',                  // extra declaration
            '#fff !important',
            'var(--text)',                         // indirection
            'calc(1px + 1px)',
            '#ffffff/*x*/',
            "#fff\n}",
            '#ffff',                               // malformed hex lengths
        ];
        foreach ($hostile as $value) {
            self::assertNotNull(ThemeTokenPolicy::validateValue('--accent', $value, []), $value);
        }
    }

    /** TM-TH-02: url()/@import/remote/data vectors are refused in every grammar. */
    public function test_url_import_remote_vectors_are_refused(): void
    {
        foreach (['url(https://evil.example/x.png)', 'url(//evil)', '@import "x"', 'url(data:text/html;base64,x)', 'image-set(url(x))'] as $value) {
            self::assertNotNull(ThemeTokenPolicy::validateValue('--accent', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--font-body', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--radius', $value, []), $value);
            self::assertNotNull(ThemeTokenPolicy::validateValue('--surface-texture', $value, []), $value);
        }
    }

    public function test_valid_values_pass_per_grammar(): void
    {
        self::assertNull(ThemeTokenPolicy::validateValue('--accent', '#8F3D12', []));   // case-insensitive input
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '7px', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '0', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--radius', '0.5rem', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--font-body', '"EB Garamond", Georgia, serif', []));
        self::assertNull(ThemeTokenPolicy::validateValue('--surface-texture', 'parchment', ['parchment']));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--surface-texture', 'missing', ['parchment']));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--radius', '10000px', []));
        self::assertNotNull(ThemeTokenPolicy::validateValue('--font-body', 'Arial, javascript:x', []));
    }

    public function test_contrast_pairs_and_baselines(): void
    {
        $pairs = ThemeTokenPolicy::contrastPairs();
        self::assertNotEmpty($pairs);
        foreach ($pairs as $pair) {
            self::assertTrue(ThemeTokenPolicy::isKnown($pair['fg']));
            self::assertTrue(ThemeTokenPolicy::isKnown($pair['bg']));
            self::assertSame(4.5, $pair['min']);
        }
        foreach (['light', 'dark'] as $variant) {
            $baseline = ThemeTokenPolicy::baseline($variant);
            foreach ($pairs as $pair) {
                self::assertArrayHasKey($pair['fg'], $baseline, "$variant baseline missing {$pair['fg']}");
                self::assertArrayHasKey($pair['bg'], $baseline, "$variant baseline missing {$pair['bg']}");
                self::assertMatchesRegularExpression('/\A#[0-9a-f]{6}\z/', $baseline[$pair['fg']]);
            }
        }
    }

    /**
     * Each shipped baseline must itself satisfy every contrast pair at its
     * declared minimum — guards against transcription slips and against
     * contrast pairs that mismodel the real palette (a partial theme package
     * would otherwise inherit a baseline-only failure).
     */
    public function test_baselines_are_self_consistent_with_contrast_pairs(): void
    {
        foreach (['light', 'dark'] as $variant) {
            $baseline = ThemeTokenPolicy::baseline($variant);
            foreach (ThemeTokenPolicy::contrastPairs() as $pair) {
                $ratio = self::contrastRatio($baseline[$pair['fg']], $baseline[$pair['bg']]);
                self::assertGreaterThanOrEqual($pair['min'], $ratio, sprintf(
                    '%s baseline: %s (%s) on %s (%s) is %.2f:1, below %.1f:1',
                    $variant,
                    $pair['fg'],
                    $baseline[$pair['fg']],
                    $pair['bg'],
                    $baseline[$pair['bg']],
                    $ratio,
                    $pair['min'],
                ));
            }
        }
    }

    /** WCAG 2.x contrast ratio between two #rrggbb colours. */
    private static function contrastRatio(string $fgHex, string $bgHex): float
    {
        $l1 = self::relativeLuminance($fgHex);
        $l2 = self::relativeLuminance($bgHex);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        $lin = [];
        foreach (str_split(ltrim($hex, '#'), 2) as $channel) {
            $c = hexdec($channel) / 255.0;
            $lin[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }
}
