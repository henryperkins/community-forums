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

    /**
     * The words of sanitised body HTML (Markdown::render output) as one line of
     * plain text: images become their alt text, block boundaries become spaces,
     * entities decode and whitespace collapses. No Markdown punctuation survives
     * because none reaches body_html. The result is unescaped — escape on output.
     */
    public static function plainText(string $html): string
    {
        // strip_tags() would drop an image's words; keep its alt, re-encoded so
        // a literal "<" in the alt survives strip_tags() and decodes below.
        $text = preg_replace_callback('/<img\b[^>]*>/i', static function (array $img): string {
            $alt = preg_match('/\salt="([^"]*)"/i', $img[0], $match) === 1
                ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : '';
            return ' ' . htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ';
        }, $html) ?? $html;
        // The renderer emits "</p><pre>" with no whitespace between blocks.
        $text = preg_replace('~<(?:/?(?:p|div|pre|blockquote|ul|ol|li|h[1-6]|table|thead|tbody|tfoot|tr|th|td)|br|hr)\b[^>]*>~i', ' $0', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
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
