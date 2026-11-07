<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Operator-editable community text (ADR 0054): the description used by search
 * and link previews, an optional tagline, and the board directory's welcome
 * heading and introduction. All four are plain text. Each stored value is either
 * a normalized string within its limit or '' — and '' always means "use the
 * documented default", so clearing a field restores the default.
 */
final class BrandIdentity
{
    public const DEFAULT_DIRECTORY_HEADING = 'Community boards';
    public const DEFAULT_DIRECTORY_INTRO = 'Browse a board to read topics or start a discussion.';

    /**
     * Setting key => form field, label, character limit and whether line breaks
     * are kept (as paragraphs). The order is the form order.
     *
     * @var array<string,array{field:string,label:string,max:int,multiline:bool}>
     */
    public const FIELDS = [
        'brand_tagline' => ['field' => 'site_tagline', 'label' => 'Tagline', 'max' => 80, 'multiline' => false],
        'brand_description' => ['field' => 'site_description', 'label' => 'Community description', 'max' => 300, 'multiline' => false],
        'brand_directory_heading' => ['field' => 'directory_heading', 'label' => 'Welcome heading', 'max' => 80, 'multiline' => false],
        'brand_directory_intro' => ['field' => 'directory_intro', 'label' => 'Welcome introduction', 'max' => 600, 'multiline' => true],
    ];

    /** The built-in description, which names the community. */
    public static function defaultDescription(string $siteName): string
    {
        return $siteName . ' — a community forum.';
    }

    /**
     * Plain-text normalization shared by the form and the shell: unify line
     * endings, turn tabs and other horizontal whitespace into single spaces,
     * and trim. Single-line fields fold line breaks into spaces; multi-line
     * fields keep line breaks (each line renders as a paragraph) and collapse
     * runs of blank lines.
     */
    public static function normalize(string $value, bool $multiline): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if (!$multiline) {
            $value = str_replace("\n", ' ', $value);
        }
        $value = preg_replace('/[^\S\n]+/u', ' ', $value) ?? '';
        if ($multiline) {
            $value = preg_replace('/ *\n */u', "\n", $value) ?? '';
            $value = preg_replace('/\n{3,}/', "\n\n", $value) ?? '';
        }
        return trim($value);
    }

    /** @return ?string a refusal sentence, or null when the normalized value is acceptable */
    public static function error(string $normalized, string $key): ?string
    {
        $field = self::FIELDS[$key];
        if (preg_match('//u', $normalized) !== 1) {
            return $field['label'] . ' must be valid text.';
        }
        // Control and bidirectional-override characters can disguise text; a
        // line break is the only control character a multi-line field keeps.
        if (preg_match('/[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}-\x{9F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $normalized) === 1) {
            return $field['label'] . ' cannot contain control characters.';
        }
        if (mb_strlen($normalized) > $field['max']) {
            return sprintf('%s must be %d characters or fewer (currently %d).', $field['label'], $field['max'], mb_strlen($normalized));
        }
        return null;
    }

    /**
     * Stored values keyed by setting key. Anything that is not a valid
     * normalized string is treated as unset, so a damaged row falls back to the
     * default rather than rendering unexpected text.
     *
     * @param array<string,mixed> $settings
     * @return array<string,string>
     */
    public static function storedValues(array $settings): array
    {
        $values = [];
        foreach (self::FIELDS as $key => $field) {
            $value = $settings[$key] ?? '';
            $value = is_string($value) ? self::normalize($value, $field['multiline']) : '';
            $values[$key] = self::error($value, $key) === null ? $value : '';
        }
        return $values;
    }

    /**
     * The same values named for the public shell.
     *
     * @param array<string,mixed> $settings
     * @return array{tagline:string,description:string,directory_heading:string,directory_intro:string}
     */
    public static function stored(array $settings): array
    {
        $values = self::storedValues($settings);
        return [
            'tagline' => $values['brand_tagline'],
            'description' => $values['brand_description'],
            'directory_heading' => $values['brand_directory_heading'],
            'directory_intro' => $values['brand_directory_intro'],
        ];
    }

    /** @return list<string> one paragraph per non-empty line; the default when $intro is '' */
    public static function paragraphs(string $intro): array
    {
        $paragraphs = array_values(array_filter(
            array_map('trim', explode("\n", $intro)),
            static fn (string $line): bool => $line !== '',
        ));
        return $paragraphs !== [] ? $paragraphs : [self::DEFAULT_DIRECTORY_INTRO];
    }
}
