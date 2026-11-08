<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Picks the Fluent artwork the icon partial draws (ADR 0060).
 *
 * Fluent draws each size for the size it is shown at, so the artwork follows
 * the member's icon-size preference: the 16px set for standard icons and the
 * 20px set for larger ones (USER §4.1). Icons drawn from one size only — the
 * star and reactions at 20px, empty-state marks at 28px — use it at either
 * setting. A selected state asks for the Filled style; an icon without one
 * keeps its default style.
 */
final class Icons
{
    /** Member icon-size preference => the artwork size drawn by default. */
    public const SCALES = ['standard' => 16, 'large' => 20];

    /**
     * @return array{size: int, paths: list<array{d: string, rule?: string}>}|null null for an unknown name
     */
    public static function glyph(string $name, string $scale = 'standard', ?string $style = null): ?array
    {
        $styles = IconSet::GLYPHS[$name] ?? null;
        if ($styles === null) {
            return null;
        }
        $sizes = $style !== null && isset($styles[$style]) ? $styles[$style] : reset($styles);
        $want = self::SCALES[$scale] ?? self::SCALES['standard'];
        $size = isset($sizes[$want]) ? $want : self::closest(array_keys($sizes), $want);

        return ['size' => $size, 'paths' => $sizes[$size]];
    }

    public static function has(string $name): bool
    {
        return isset(IconSet::GLYPHS[$name]);
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(IconSet::GLYPHS);
    }

    /** @param list<int> $sizes */
    private static function closest(array $sizes, int $want): int
    {
        usort($sizes, static fn (int $a, int $b): int => [abs($a - $want), -$a] <=> [abs($b - $want), -$b]);

        return $sizes[0];
    }
}
