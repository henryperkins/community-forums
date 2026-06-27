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
    /**
     * Bump when the shape changes. resolve() is version-agnostic — each key is
     * validated independently against its own spec, so older blobs stay readable
     * without an explicit migration; `__v` is a forward-compat marker only.
     */
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
        $stored = self::upgrade($stored);
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
     * Bring a stored (sparse, untrusted) preference blob up to the current
     * {@see VERSION}: run any per-version value transforms, drop known-schema
     * values that no longer validate (so stale data falls back to its default
     * instead of persisting), and stamp `__v`. Unknown keys (other subsystems'
     * prefs) are preserved; an empty blob and a blob written by a newer deploy
     * (`__v` > VERSION) are returned untouched.
     *
     * v1 → v2 was purely additive (the reading-display + composing toggles were
     * introduced at v2), so it needs no value transform yet — {@see transformTo}
     * is the home for a future breaking change (e.g. a renamed enum value) so old
     * blobs keep working across a schema bump. resolve() runs this on every read;
     * a save (updateSection) re-stamps the version, so storage converges.
     *
     * @param array<string,mixed> $stored
     * @return array<string,mixed>
     */
    public static function upgrade(array $stored): array
    {
        if ($stored === []) {
            return $stored;
        }
        $from = isset($stored['__v']) && is_numeric($stored['__v']) ? (int) $stored['__v'] : 1;
        if ($from > self::VERSION) {
            return $stored; // written by a newer version — never downgrade.
        }
        for ($v = $from + 1; $v <= self::VERSION; $v++) {
            $stored = self::transformTo($v, $stored);
        }
        // Drop a known-schema value that no longer validates so its default
        // applies; leave unknown keys (other subsystems' prefs) intact.
        foreach ($stored as $key => $value) {
            if ($key === '__v') {
                continue;
            }
            $spec = self::specFor((string) $key);
            if ($spec !== null && self::coerce($spec, $value) === null) {
                unset($stored[$key]);
            }
        }
        $stored['__v'] = self::VERSION;
        return $stored;
    }

    /**
     * Per-version value transform applied when upgrading across a schema bump.
     * No version has needed one yet (v2 was additive); add a match arm here when
     * a future VERSION renames or retypes a key so old blobs keep working.
     *
     * @param array<string,mixed> $stored
     * @return array<string,mixed>
     */
    private static function transformTo(int $version, array $stored): array
    {
        return match ($version) {
            default => $stored,
        };
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

    /**
     * The spec for a schema key, searched across all sections, or null if the
     * key is not schema-managed.
     *
     * @return array{type:string, values?:list<mixed>, default:mixed}|null
     */
    private static function specFor(string $key): ?array
    {
        foreach (self::SCHEMA as $fields) {
            if (isset($fields[$key])) {
                return $fields[$key];
            }
        }
        return null;
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
