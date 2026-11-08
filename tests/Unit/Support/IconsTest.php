<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Icons;
use App\Support\IconSet;
use PHPUnit\Framework\TestCase;

/**
 * ADR 0060: the Fluent icon set is generated from pinned, licensed sources;
 * the artwork follows the member's icon size; selected states draw Filled;
 * and the set holds exactly the icons the templates draw.
 */
final class IconsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_generated_icon_set_matches_the_reviewed_sources(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::ROOT . '/bin/build-icons.php') . ' --check 2>&1';
        exec($command, $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame('@fluentui/svg-icons 1.1.341', IconSet::SOURCE);
        $licence = (string) file_get_contents(self::ROOT . '/resources/icons/fluent/LICENSE');
        self::assertStringContainsString('MIT License', $licence);
        self::assertStringContainsString('Microsoft Corporation', $licence);
    }

    public function test_every_path_is_plain_path_data(): void
    {
        $paths = 0;
        foreach (IconSet::GLYPHS as $name => $styles) {
            foreach ($styles as $style => $sizes) {
                foreach ($sizes as $size => $glyphPaths) {
                    self::assertNotSame([], $glyphPaths, "{$name} {$style} {$size}");
                    foreach ($glyphPaths as $path) {
                        // The partial prints these raw; only path commands may pass.
                        self::assertMatchesRegularExpression('/^[MmLlHhVvCcSsQqTtAaZz0-9eE.,\s+\-]+$/D', $path['d']);
                        $paths++;
                    }
                }
            }
        }
        self::assertGreaterThan(100, $paths);
    }

    public function test_artwork_follows_the_icon_size_preference(): void
    {
        self::assertSame(16, Icons::glyph('bell')['size'] ?? null);
        self::assertSame(16, Icons::glyph('bell', 'standard')['size'] ?? null);
        self::assertSame(20, Icons::glyph('bell', 'large')['size'] ?? null);
        self::assertSame(16, Icons::glyph('bell', 'enormous')['size'] ?? null, 'An unknown size draws standard icons.');
        self::assertNotSame(Icons::glyph('bell', 'standard'), Icons::glyph('bell', 'large'));
    }

    public function test_single_size_artwork_is_drawn_at_either_setting(): void
    {
        // The star and reactions are drawn at 18px from the 20px artwork;
        // empty-state marks at 30px from the 28px artwork.
        foreach (['commend-star' => 20, 'heart' => 20, 'surprised' => 20, 'inbox' => 28, 'board-empty' => 28] as $name => $size) {
            self::assertSame($size, Icons::glyph($name, 'standard')['size'] ?? null, $name);
            self::assertSame($size, Icons::glyph($name, 'large')['size'] ?? null, $name);
        }
    }

    public function test_selected_states_draw_filled_and_fall_back_to_the_default(): void
    {
        $regular = Icons::glyph('bell', 'standard');
        $filled = Icons::glyph('bell', 'standard', 'filled');
        self::assertNotNull($filled);
        self::assertNotSame($regular, $filled);
        self::assertSame(IconSet::GLYPHS['bell']['filled'][16], $filled['paths']);

        // An icon without a Filled style keeps its own.
        self::assertSame(Icons::glyph('plus', 'standard'), Icons::glyph('plus', 'standard', 'filled'));
        // Pin and Sparkle are solid marks at every size.
        self::assertSame(['filled'], array_keys(IconSet::GLYPHS['pin']));
        self::assertSame(['filled'], array_keys(IconSet::GLYPHS['sparkle']));
    }

    public function test_the_star_is_filled_until_it_is_asked_for_its_outline(): void
    {
        // ADR 0036: one star; outlined while unset. The outline is Star Regular.
        self::assertSame(['filled', 'regular'], array_keys(IconSet::GLYPHS['commend-star']));
        self::assertSame(IconSet::GLYPHS['commend-star']['filled'][20], Icons::glyph('commend-star')['paths'] ?? null);
        self::assertSame(IconSet::GLYPHS['commend-star']['regular'][20], Icons::glyph('commend-star', 'standard', 'regular')['paths'] ?? null);
    }

    public function test_the_forum_theme_paints_icons_as_filled_artwork(): void
    {
        // Rules such as `.fab svg` and `.chip svg` stroke any svg for the
        // retired stroked glyphs; the unlayered theme keeps them off the
        // partial's filled artwork.
        foreach (['/resources/forum/theme.css', '/public/assets/forum-theme.css'] as $css) {
            self::assertStringContainsString(
                'svg.icon { fill: currentColor; stroke: none; }',
                (string) file_get_contents(self::ROOT . $css),
                $css,
            );
        }
    }

    public function test_unknown_and_retired_names_draw_nothing(): void
    {
        self::assertNull(Icons::glyph('no-such-icon'));
        foreach (['star', 'star-filled', 'eight-point-star', 'panel-left', 'user-plus'] as $retired) {
            self::assertFalse(Icons::has($retired), $retired);
        }
    }

    public function test_the_set_holds_exactly_the_icons_the_templates_draw(): void
    {
        $drawn = self::namesDrawnByTemplates();
        sort($drawn);
        $set = Icons::names();
        sort($set);

        self::assertSame([], array_values(array_diff($drawn, $set)), 'A template draws an icon the set does not have.');
        self::assertSame([], array_values(array_diff($set, $drawn)), 'The set carries an icon no template draws.');
    }

    /** @return list<string> */
    private static function namesDrawnByTemplates(): array
    {
        $names = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/templates', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $php = (string) file_get_contents($file->getPathname());
            // Literal names, and the icon keys of menus built from arrays
            // (the settings rail and the conversation confirm menus).
            preg_match_all("~partials/icon',\s*\['name'\s*=>\s*'([a-z0-9-]+)'~", $php, $literal);
            preg_match_all("~'icon'\s*=>\s*'([a-z0-9-]+)'~", $php, $keyed);
            array_push($names, ...$literal[1], ...$keyed[1]);
        }
        // Names chosen in code: reactions, the conversation rail toggle and the
        // appearance previews.
        $reactions = (string) file_get_contents(self::ROOT . '/templates/partials/reaction_face.php');
        preg_match_all("~^\s*'[^']+'\s*=>\s*'([a-z0-9-]+)',~m", $reactions, $reactionNames);
        array_push($names, ...$reactionNames[1]);
        $conversation = (string) file_get_contents(self::ROOT . '/templates/dm/show.php');
        self::assertSame(1, preg_match("~\\\$railIcon = [^?]+\? '([a-z0-9-]+)' : '([a-z0-9-]+)';~", $conversation, $rail));
        array_push($names, $rail[1], $rail[2]);
        $appearance = (string) file_get_contents(self::ROOT . '/templates/account/appearance.php');
        self::assertSame(1, preg_match('~foreach \(\[([^\]]+)\] as \$previewIcon\)~', $appearance, $preview));
        preg_match_all("~'([a-z0-9-]+)'~", $preview[1], $previewNames);
        array_push($names, ...$previewNames[1]);

        return array_values(array_unique($names));
    }
}
