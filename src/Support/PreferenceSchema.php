<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Versioned, validated preference schema (P3-01, USER §4). The per-user JSON
 * blob is untrusted input: every recognized key has a fixed type + allow-list,
 * unknown keys are ignored, and malformed/out-of-range values fall back to the
 * documented default — so a tampered or stale blob can never break rendering or
 * bypass a server-side rule (PHASE_3_PLAN §3, §9 "Preferences").
 *
 * Prefs are grouped into three settings sections (appearance / reading /
 * composing). Each is its own form, so a section update only ever touches its
 * own keys and leaves the rest (and non-schema keys such as
 * `hide_from_leaderboard`) intact.
 *
 * Per-page keys carry a `null` default: they fall back to the server pagination
 * default in PreferenceService, preserving Phase 2 behaviour, and only persist
 * when set to one of the allow-listed overrides.
 */
final class PreferenceSchema
{
    /** Bump when the shape changes; resolve()/migrate() keep old blobs readable. */
    public const VERSION = 2;

    public const THREADS_PER_PAGE = [25, 50, 100];
    public const POSTS_PER_PAGE = [10, 20, 40];

    /**
     * section => [ key => spec ]. spec.type ∈ {enum, enumint, bool}.
     *
     * @var array<string, array<string, array{type:string, values?:list<mixed>, default:mixed}>>
     */
    private const SCHEMA = [
        'appearance' => [
            'theme'          => ['type' => 'enum', 'values' => ['system', 'light', 'dark'], 'default' => 'system'],
            'density'        => ['type' => 'enum', 'values' => ['comfortable', 'compact'], 'default' => 'comfortable'],
            'font_size'      => ['type' => 'enum', 'values' => ['small', 'medium', 'large'], 'default' => 'medium'],
            'reduced_motion' => ['type' => 'bool', 'default' => false],
        ],
        'reading' => [
            'threads_per_page' => ['type' => 'enumint', 'values' => self::THREADS_PER_PAGE, 'default' => null],
            'posts_per_page'   => ['type' => 'enumint', 'values' => self::POSTS_PER_PAGE, 'default' => null],
            'thread_sort'      => ['type' => 'enum', 'values' => ['last_post', 'newest', 'replies'], 'default' => 'last_post'],
            'show_signatures'  => ['type' => 'bool', 'default' => true],
            'show_avatars'     => ['type' => 'bool', 'default' => true],
            'show_reactions'   => ['type' => 'bool', 'default' => true],
        ],
        'composing' => [
            'enter_to_send' => ['type' => 'bool', 'default' => false],
            'show_preview'  => ['type' => 'bool', 'default' => true],
            'smart_lists'   => ['type' => 'bool', 'default' => true],
        ],
    ];

    /** @return list<string> */
    public static function sections(): array
    {
        return array_keys(self::SCHEMA);
    }

    public static function hasSection(string $section): bool
    {
        return isset(self::SCHEMA[$section]);
    }

    /** @return array<string, array{type:string, values?:list<mixed>, default:mixed}> */
    public static function fields(string $section): array
    {
        return self::SCHEMA[$section] ?? [];
    }

    /**
     * Flat key => default for every schema key whose default is non-null, plus
     * the version marker. Per-page keys (null default) are intentionally absent.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        $out = ['__v' => self::VERSION];
        foreach (self::SCHEMA as $fields) {
            foreach ($fields as $key => $spec) {
                if ($spec['default'] !== null) {
                    $out[$key] = $spec['default'];
                }
            }
        }
        return $out;
    }

    /**
     * Merge a stored (untrusted) blob over the defaults: validated schema values
     * win, invalid ones fall back to default, and non-schema keys pass through
     * unchanged (so e.g. `hide_from_leaderboard` survives).
     *
     * @param array<string,mixed> $stored
     * @return array<string,mixed>
     */
    public static function resolve(array $stored): array
    {
        $out = self::defaults();
        foreach (self::SCHEMA as $fields) {
            foreach ($fields as $key => $spec) {
                if (!array_key_exists($key, $stored)) {
                    continue;
                }
                $valid = self::coerce($spec, $stored[$key]);
                if ($valid !== null) {
                    $out[$key] = $valid;
                } elseif ($spec['default'] === null) {
                    // per-page override that was invalid: leave it unset.
                    unset($out[$key]);
                }
            }
        }
        // Preserve unknown stored keys (other subsystems' prefs).
        foreach ($stored as $key => $value) {
            if ($key !== '__v' && !self::isSchemaKey((string) $key)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Sanitize a posted section form into a persistable change-set. Every field
     * in the section is written (so unchecking a box persists `false`); an
     * invalid enum is dropped to its default, and an invalid per-page value is
     * set to null (removed) so the server default applies.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed> key => value|null (null removes the key)
     */
    public static function validateSection(string $section, array $input): array
    {
        $changes = [];
        foreach (self::fields($section) as $key => $spec) {
            if ($spec['type'] === 'bool') {
                $changes[$key] = array_key_exists($key, $input)
                    && (string) $input[$key] !== '0' && $input[$key] !== false;
                continue;
            }
            $valid = array_key_exists($key, $input) ? self::coerce($spec, $input[$key]) : null;
            if ($valid !== null) {
                $changes[$key] = $valid;
            } else {
                // enum → restore default; per-page (null default) → remove.
                $changes[$key] = $spec['default'];
            }
        }
        return $changes;
    }

    private static function isSchemaKey(string $key): bool
    {
        foreach (self::SCHEMA as $fields) {
            if (isset($fields[$key])) {
                return true;
            }
        }
        return false;
    }

    /** @param array{type:string, values?:list<mixed>, default:mixed} $spec */
    private static function coerce(array $spec, mixed $value): mixed
    {
        switch ($spec['type']) {
            case 'bool':
                return (bool) ($value !== false && (string) $value !== '0' && $value !== null);
            case 'enumint':
                $v = is_numeric($value) ? (int) $value : null;
                return $v !== null && in_array($v, $spec['values'] ?? [], true) ? $v : null;
            case 'enum':
            default:
                $v = is_string($value) ? $value : '';
                return in_array($v, $spec['values'] ?? [], true) ? $v : null;
        }
    }
}
