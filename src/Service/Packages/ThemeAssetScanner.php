<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Security\Packages\PackagePolicyException;

/**
 * Neutralizes declared theme assets (TM-TH-03): raster-only allowlist, finfo
 * sniff must agree with the declared kind, and the stored bytes are a full GD
 * re-encode so appended payloads cannot survive.
 */
final class ThemeAssetScanner
{
    public const KINDS = ['png', 'jpeg', 'gif', 'webp'];
    public const MAX_ASSET_BYTES = 131_072;
    public const MAX_TOTAL_BYTES = 262_144;
    public const MAX_ASSETS = 4;

    private const MIME_BY_KIND = [
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /** @return array{mime:string,bytes:string,digest:string} */
    public function scan(string $name, string $kind, string $bytes): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            $this->refuse($name, 'kind must be one of ' . implode(', ', self::KINDS));
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_ASSET_BYTES) {
            $this->refuse($name, 'must be 1 to ' . self::MAX_ASSET_BYTES . ' bytes');
        }

        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($sniffed !== self::MIME_BY_KIND[$kind]) {
            $this->refuse($name, 'content does not match its declared kind');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->refuse($name, 'could not be decoded as an image');
        }

        ob_start();
        $ok = match ($kind) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image, null, 85),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
        };
        $reencoded = (string) ob_get_clean();
        imagedestroy($image);

        if (!$ok || $reencoded === '') {
            $this->refuse($name, 'could not be re-encoded');
        }
        if (strlen($reencoded) > self::MAX_ASSET_BYTES) {
            $this->refuse($name, 'is too large after re-encoding');
        }

        return [
            'mime' => self::MIME_BY_KIND[$kind],
            'bytes' => $reencoded,
            'digest' => hash('sha256', $reencoded),
        ];
    }

    private function refuse(string $name, string $why): never
    {
        throw new PackagePolicyException('theme_asset', 'Theme asset "' . $name . '" ' . $why . '.');
    }
}
