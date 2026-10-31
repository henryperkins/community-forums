<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Logo selection shared by every identity surface (ADR 0054). Each mode
 * resolves its own variant with the base logo as fallback:
 * light = light variant ?: base, dark = dark variant ?: base. An explicit
 * Light/Dark theme renders that mode's logo. System renders both choices
 * server-side and lets the device colour preference pick, so the first paint
 * is already correct and no script is involved:
 *
 * - single:  both modes resolve to the same image (or the theme is explicit);
 * - picture: both modes have different images — a <picture> whose dark source
 *            the browser selects with prefers-color-scheme;
 * - split:   only one mode has an image — the image and the built-in mark are
 *            both rendered and a prefers-color-scheme rule shows one of them;
 * - none:    no image for the relevant mode(s) — the built-in mark.
 */
final class BrandLogo
{
    /**
     * @return array{kind:'none'|'single'|'picture'|'split',src:?string,light:?string,dark:?string}
     */
    public static function plan(string $base, string $light, string $dark, string $theme): array
    {
        $lightLogo = self::path($light) ?? self::path($base);
        $darkLogo = self::path($dark) ?? self::path($base);
        if ($theme === 'light' || $theme === 'dark') {
            $src = $theme === 'dark' ? $darkLogo : $lightLogo;
            return ['kind' => $src === null ? 'none' : 'single', 'src' => $src, 'light' => $lightLogo, 'dark' => $darkLogo];
        }
        if ($lightLogo === $darkLogo) {
            return ['kind' => $lightLogo === null ? 'none' : 'single', 'src' => $lightLogo, 'light' => $lightLogo, 'dark' => $darkLogo];
        }
        if ($lightLogo !== null && $darkLogo !== null) {
            return ['kind' => 'picture', 'src' => $lightLogo, 'light' => $lightLogo, 'dark' => $darkLogo];
        }
        return ['kind' => 'split', 'src' => null, 'light' => $lightLogo, 'dark' => $darkLogo];
    }

    /**
     * Saved logos are same-origin paths (uploads are /media/{id}) or inline
     * base64 image data. A scheme-relative or otherwise off-site value from a
     * damaged or hand-edited setting is ignored, so it can never become a
     * cross-origin request.
     */
    private static function path(string $value): ?string
    {
        return preg_match('#^/(?![/\\\\])[^\s"\'<>\\\\]*$#D', $value) === 1
            || preg_match('#^data:image/(?:png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/]+={0,2}$#D', $value) === 1
            ? $value : null;
    }
}
