<?php

declare(strict_types=1);

namespace App\Support;

final class Str
{
    /** URL-safe slug: lowercase ASCII, hyphen-separated, length-capped. */
    public static function slug(string $text, int $maxLength = 180): string
    {
        $text = trim($text);
        // Best-effort transliteration of accented characters to ASCII.
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($maxLength > 0 && strlen($text) > $maxLength) {
            $text = rtrim(substr($text, 0, $maxLength), '-');
        }

        return $text === '' ? 'topic' : $text;
    }

    /** Up to two uppercase initials for a monogram avatar. */
    public static function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/[\s_\-.]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            $first = mb_substr($part, 0, 1, 'UTF-8');
            if (preg_match('/\p{L}|\p{N}/u', $first) === 1) {
                $letters .= $first;
            }
            if (mb_strlen($letters, 'UTF-8') >= 2) {
                break;
            }
        }

        if ($letters === '') {
            $letters = mb_substr($name, 0, 2, 'UTF-8');
        } elseif (mb_strlen($letters, 'UTF-8') === 1 && mb_strlen($name, 'UTF-8') >= 2) {
            $letters .= mb_substr($name, 1, 1, 'UTF-8');
        }

        return mb_strtoupper($letters, 'UTF-8');
    }

    /** Plain-text snippet (no markdown/html) capped at $length chars. */
    public static function snippet(string $text, int $length = 140): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
    }
}
