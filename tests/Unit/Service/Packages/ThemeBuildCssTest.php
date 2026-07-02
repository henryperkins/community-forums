<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Service\Packages\ThemeBuildService;
use PHPUnit\Framework\TestCase;

final class ThemeBuildCssTest extends TestCase
{
    public function test_emit_is_deterministic_and_catalogue_ordered(): void
    {
        $a = ThemeBuildService::emitCss(['--surface' => '#fff7dc', '--accent' => '#8f3d12'], [], []);
        $b = ThemeBuildService::emitCss(['--accent' => '#8f3d12', '--surface' => '#fff7dc'], [], []);

        self::assertSame($a, $b);
        self::assertSame(':root{--surface:#fff7dc;--accent:#8f3d12;}', $a);
    }

    public function test_dark_tokens_emit_both_dark_scopes(): void
    {
        $css = ThemeBuildService::emitCss(['--accent' => '#8f3d12'], ['--surface' => '#141210'], []);

        self::assertStringContainsString('[data-theme="dark"]{--surface:#141210;}', $css);
        self::assertStringContainsString('@media (prefers-color-scheme: dark){:root[data-theme="system"]{--surface:#141210;}}', $css);
    }

    /** TM-TH-02: the only url() the builder can emit is the local asset route. */
    public function test_asset_tokens_emit_local_urls_only(): void
    {
        $digest = str_repeat('ab', 32);
        $css = ThemeBuildService::emitCss(['--surface-texture' => 'parchment'], [], ['parchment' => $digest]);

        self::assertStringContainsString('--surface-texture:url("/theme/asset/' . $digest . '");', $css);
        self::assertSame(0, preg_match('/url\(\s*(?!["\']?\/theme\/asset\/)/i', $css));
        self::assertStringNotContainsString('http', $css);
        self::assertStringNotContainsString('@import', $css);
    }
}
