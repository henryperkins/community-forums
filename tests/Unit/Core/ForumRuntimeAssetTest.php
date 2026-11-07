<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class ForumRuntimeAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_generated_forum_assets_match_the_reviewed_sources(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::ROOT . '/bin/build-forum-css.php') . ' --check 2>&1';
        exec($command, $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }

    public function test_forum_theme_has_final_cascade_authority_without_font_downloads(): void
    {
        $base = (string) file_get_contents(self::ROOT . '/public/assets/forum-base.css');
        $application = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        $theme = (string) file_get_contents(self::ROOT . '/public/assets/forum-theme.css');

        self::assertStringContainsString('@layer forum.components, application;', $base);
        self::assertStringContainsString('@layer forum.components {', $base);
        self::assertStringContainsString('@layer application {', $application);
        self::assertStringNotContainsString('@layer ', $theme);
        self::assertStringContainsString('--font-ui: system-ui,', $theme);
        self::assertStringContainsString('--font-body: var(--font-ui);', $theme);
        self::assertStringContainsString('--measure-prose: 65ch;', $theme);
        self::assertStringContainsString('prefers-color-scheme: dark', $theme);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $theme);
        self::assertStringNotContainsString('@font-face', $base . $theme);
        self::assertStringNotContainsString('@import', $base . $theme);
        self::assertStringNotContainsString('fonts/imladris/', $base . $theme);
    }
}
