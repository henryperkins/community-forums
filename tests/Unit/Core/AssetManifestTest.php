<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Support\AssetManifest;
use PHPUnit\Framework\TestCase;

final class AssetManifestTest extends TestCase
{
    public function test_build_urls_resolve_to_existing_fingerprinted_files(): void
    {
        $root = dirname(__DIR__, 3);
        $manifest = new AssetManifest($root);
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $manifest->version());
        foreach ($manifest->urls() as $url) {
            self::assertStringStartsWith('/assets/dist/', $url);
            self::assertFileExists($root . '/public' . $url);
        }
    }

    public function test_missing_or_invalid_entries_fall_back_to_source_without_a_broken_editor(): void
    {
        $root = sys_get_temp_dir() . '/retroboards-asset-manifest-' . bin2hex(random_bytes(6));
        mkdir($root . '/config', 0777, true);
        mkdir($root . '/public/assets', 0777, true);
        file_put_contents($root . '/public/assets/app.js', 'window.example = true;');
        try {
            $missing = new AssetManifest($root);
            self::assertMatchesRegularExpression('#^/assets/app\.js\?v=[a-f0-9]{16}$#', $missing->urls()['app.js']);
            self::assertArrayNotHasKey('wysiwyg-composer.js', $missing->urls());

            file_put_contents($root . '/config/assets.json', json_encode([
                'version' => 'not-a-version',
                'urls' => [
                    'app.js' => '/assets/dist/../../private.js',
                    'composer.js' => 'https://untrusted.example/script.js',
                    'wysiwyg-composer.js' => '/assets/dist/missing-chunk.js',
                ],
            ], JSON_THROW_ON_ERROR));
            $invalid = new AssetManifest($root);
            self::assertSame($missing->urls(), $invalid->urls());
            self::assertSame($missing->version(), $invalid->version());
        } finally {
            unlink($root . '/config/assets.json');
            unlink($root . '/public/assets/app.js');
            rmdir($root . '/public/assets');
            rmdir($root . '/public');
            rmdir($root . '/config');
            rmdir($root);
        }
    }
}
