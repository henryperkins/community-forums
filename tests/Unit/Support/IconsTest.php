<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Icons;
use App\Support\IconSet;
use App\Support\NotificationPresenter;
use PHPUnit\Framework\TestCase;

/**
 * ADR 0060: the Fluent icon set is generated from pinned, licensed sources;
 * the artwork follows the member's icon size; selected states draw Filled;
 * every icon comes from the set and nothing strokes one; and the set holds
 * exactly the icons templates, notification rows, the composer and the
 * stylesheets draw.
 */
final class IconsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Icons stylesheets draw as data URIs, with the artwork size each uses. */
    private const STYLESHEET_ICONS = ['flash-success' => 16, 'flash-error' => 16];

    /** The only inline SVG a template draws itself: artwork that is not an icon. */
    private const NON_ICON_ARTWORK = [
        'templates/admin/branding_revision.php' => 1, // a branding colour swatch
        'templates/partials/brand_mark.php' => 1, // the community's default mark (DESIGN.md)
        'templates/partials/poll.php' => 1, // a poll result's bar
        'templates/profile/show.php' => 1, // the retired cover ornament, hidden by the Forum theme (ADR 0057)
    ];

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

    public function test_icons_are_filled_and_no_rule_strokes_one(): void
    {
        // Every icon is filled artwork. The unlayered theme's guard keeps a
        // later rule that strokes an svg from outlining one.
        foreach (['/resources/forum/theme.css', '/public/assets/forum-theme.css'] as $css) {
            self::assertStringContainsString(
                'svg.icon { fill: currentColor; stroke: none; }',
                (string) file_get_contents(self::ROOT . $css),
                $css,
            );
        }
        // No rule strokes an svg: the only stroke left outlines a branding
        // colour swatch, which is not an icon.
        $stroking = [];
        foreach (['/resources/forum/components.css', '/resources/forum/theme.css', '/public/assets/forum-base.css', '/public/assets/forum-theme.css', '/public/assets/app.css'] as $css) {
            $source = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(self::ROOT . $css));
            preg_match_all('~([^{}]+)\{([^{}]*)\}~', $source, $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selector, $body]) {
                if (preg_match('~(?:^|;)\s*stroke[a-z-]*\s*:(?!\s*none\s*(?:;|$))~', $body) === 1) {
                    $stroking[] = $css . ' ' . trim($selector);
                }
            }
        }
        self::assertSame(['/public/assets/app.css .branding-revision-swatch rect'], $stroking);
    }

    public function test_stylesheets_draw_their_icons_from_the_set(): void
    {
        // The admin console's flash marks are masks on a shared partial, so
        // they carry the set's artwork as data URIs.
        $known = [];
        foreach (self::STYLESHEET_ICONS as $name => $size) {
            $known[$name] = [$size, array_column(IconSet::GLYPHS[$name]['regular'][$size], 'd')];
        }
        $drawn = [];
        foreach (['/public/assets/forum-base.css', '/public/assets/forum-theme.css', '/public/assets/app.css'] as $css) {
            preg_match_all('~url\("data:image/svg\+xml,([^"]*)"\)~', (string) file_get_contents(self::ROOT . $css), $uris);
            foreach ($uris[1] as $uri) {
                self::assertStringNotContainsString('stroke', $uri, 'A stylesheet icon is filled artwork.');
                self::assertSame(1, preg_match("~viewBox='0 0 (\d+) (\d+)'~", $uri, $box), $uri);
                self::assertSame($box[1], $box[2]);
                preg_match_all("~ d='([^']+)'~", $uri, $paths);
                $icon = [(int) $box[1], $paths[1]];
                self::assertContains($icon, $known, 'A stylesheet draws an icon the set does not carry: ' . $uri);
                $drawn[] = $icon;
            }
        }
        foreach ($known as $name => $icon) {
            self::assertContains($icon, $drawn, $name . ' is drawn by a stylesheet');
        }
    }

    public function test_the_composer_draws_its_icons_from_its_generated_block(): void
    {
        $manifest = json_decode((string) file_get_contents(self::ROOT . '/resources/icons/icons.json'), true, 512, JSON_THROW_ON_ERROR);
        $listed = $manifest['scripts']['public/assets/composer.js'];
        $script = (string) file_get_contents(self::ROOT . '/public/assets/composer.js');
        // Each toolbar action names its icon; the trigger, More and Close draw theirs.
        preg_match_all("~\bicon: '([a-z0-9-]+)'~", $script, $actions);
        preg_match_all("~actionIcon\('([a-z0-9-]+)'\)~", $script, $direct);
        $used = array_values(array_unique([...$actions[1], ...$direct[1]]));
        sort($used);
        $expected = $listed;
        sort($expected);
        self::assertSame($expected, $used, 'icons.json lists exactly the icons composer.js draws.');
        // The block carries each listed icon's default artwork at every size
        // it is drawn from (`composer check:icons` holds it current).
        self::assertSame(1, preg_match('~// BEGIN GENERATED ICONS[^\n]*\n(?<block>.*?)\n\s*// END GENERATED ICONS~s', $script, $match));
        foreach ($listed as $name) {
            $styles = IconSet::GLYPHS[$name];
            $sizes = reset($styles);
            foreach ($sizes as $size => $paths) {
                self::assertStringContainsString($size . ": [['" . $paths[0]['d'] . "'", $match['block'], $name . ' ' . $size);
            }
        }
    }

    public function test_only_the_set_draws_icons(): void
    {
        // Templates draw icons through partials/icon.php. The other inline SVG
        // is artwork that is not an icon.
        $svg = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/templates', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen(self::ROOT) + 1);
            $count = substr_count((string) file_get_contents($file->getPathname()), '<svg');
            if ($count > 0 && $relative !== 'templates/partials/icon.php') {
                $svg[$relative] = $count;
            }
        }
        ksort($svg);
        $expected = self::NON_ICON_ARTWORK;
        ksort($expected);
        self::assertSame($expected, $svg);

        // Scripts draw icons only through the composer's actionIcon().
        $scripts = [...glob(self::ROOT . '/public/assets/*.js') ?: [], ...glob(self::ROOT . '/src/client/*/*.ts') ?: []];
        self::assertNotSame([], $scripts);
        foreach ($scripts as $script) {
            $source = (string) file_get_contents($script);
            self::assertStringNotContainsString('<svg', $source, basename($script));
            $expected = basename($script) === 'composer.js' ? 2 : 0; // the svg and its paths in actionIcon()
            self::assertSame($expected, substr_count($source, 'createElementNS('), basename($script));
        }
        // PHP builds no SVG markup outside templates.
        $php = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($php as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                self::assertStringNotContainsString('<svg', (string) file_get_contents($file->getPathname()), $file->getFilename());
            }
        }
    }

    public function test_unknown_and_retired_names_draw_nothing(): void
    {
        self::assertNull(Icons::glyph('no-such-icon'));
        foreach (['star', 'star-filled', 'eight-point-star', 'user-plus'] as $retired) {
            self::assertFalse(Icons::has($retired), $retired);
        }
    }

    public function test_the_set_holds_exactly_the_icons_drawn(): void
    {
        $drawn = self::namesDrawn();
        sort($drawn);
        $set = Icons::names();
        sort($set);

        self::assertSame([], array_values(array_diff($drawn, $set)), 'Something draws an icon the set does not have.');
        self::assertSame([], array_values(array_diff($set, $drawn)), 'The set carries an icon nothing draws.');
    }

    /** @return list<string> names drawn by templates, the notification rows, scripts and stylesheets */
    private static function namesDrawn(): array
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
        // Notification rows, the composer and the stylesheets.
        array_push($names, ...array_values(NotificationPresenter::ICONS));
        $manifest = json_decode((string) file_get_contents(self::ROOT . '/resources/icons/icons.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['scripts'] as $scriptNames) {
            array_push($names, ...$scriptNames);
        }
        array_push($names, ...array_keys(self::STYLESHEET_ICONS));

        return array_values(array_unique($names));
    }
}
