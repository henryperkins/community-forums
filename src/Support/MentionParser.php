<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extracts @username mentions from canonical Markdown (P2-05, COMPOSER §6).
 *
 * Rules: a mention is `@` + 3–32 username chars, not preceded by a word char or
 * a second `@` (so emails like name@host and `@@x` don't match) and not inside
 * an inline code span or fenced code block. Results are de-duplicated
 * case-insensitively and capped at the per-post mention limit (DECISIONS §6 #8).
 */
final class MentionParser
{
    public const MAX = 10;

    /** @return list<string> up to MAX unique handles, in first-seen order */
    public static function parse(string $markdown): array
    {
        $stripped = self::stripCode($markdown);

        if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{3,32})\b/', $stripped, $m) === false) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ($m[1] as $handle) {
            $key = strtolower($handle);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $handle;
            if (count($out) >= self::MAX) {
                break;
            }
        }
        return $out;
    }

    /** Blank out fenced ``` blocks and inline `code` so mentions inside them don't count. */
    private static function stripCode(string $text): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/`[^`]*`/', ' ', $text) ?? $text;
        return $text;
    }
}
