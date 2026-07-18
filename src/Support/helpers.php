<?php

declare(strict_types=1);

use App\Support\Str;

/**
 * Global helper functions (loaded via composer "files" autoload).
 * Thin wrappers around App\Support\Str so templates and services read cleanly.
 */

if (!function_exists('slugify')) {
    function slugify(string $text, int $maxLength = 180): string
    {
        return Str::slug($text, $maxLength);
    }
}

if (!function_exists('monogram_initials')) {
    /** Up to two uppercase initials derived from a display name / username. */
    function monogram_initials(string $name): string
    {
        return Str::initials($name);
    }
}

if (!function_exists('monogram_class')) {
    /** A deterministic palette class (mono-0..mono-9) from a username. */
    function monogram_class(string $seed): string
    {
        return 'mono-' . (hexdec(substr(md5($seed), 0, 8)) % 10);
    }
}

if (!function_exists('mask_author')) {
    /**
     * Single decision point for rendering a post/thread author byline. When the
     * post is anonymous every field collapses to the constant "Anonymous"
     * identity — no display name, username, profile link, monogram seed, or role
     * — so nothing can fingerprint or correlate the real author across posts
     * (ADMIN §1.3 masked-identity posting). The real user_id is never touched, so
     * owner/mod affordances and reputation are unaffected; unmasking is a
     * separate, audited moderator action.
     *
     * @return array{label:string, profile_url:?string, mono_name:string, mono_seed:string, is_staff:bool}
     */
    function mask_author(?string $displayName, ?string $username, ?string $role = 'user', bool $isAnon = false): array
    {
        if ($isAnon) {
            return ['label' => 'Anonymous', 'profile_url' => null, 'mono_name' => 'Anonymous', 'mono_seed' => '', 'is_staff' => false];
        }
        $username = (string) $username;
        $label = ($displayName ?? '') !== '' ? (string) $displayName : $username;
        return [
            'label' => $label !== '' ? $label : 'Unknown',
            'profile_url' => $username !== '' ? '/u/' . $username : null,
            'mono_name' => $label,
            'mono_seed' => $username,
            'is_staff' => $role === 'admin',
        ];
    }
}

if (!function_exists('human_datetime')) {
    function human_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        if ($ts === false) {
            return '';
        }
        return gmdate('M j, Y \a\t H:i', $ts) . ' UTC';
    }
}

if (!function_exists('human_date')) {
    function human_date(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        return $ts === false ? '' : gmdate('M j, Y', $ts);
    }
}

if (!function_exists('field_error')) {
    /**
     * Accessible field-error line (round-2 audit finding 11): renders the
     * message with a stable id so the input can reference it. Pair with
     * {@see field_attrs} ON the input. Escapes internally — safe to echo raw.
     * $alert adds role="alert" for the page/row-level notices that should be
     * announced assertively (per-field lines rely on aria-describedby instead —
     * a live region on every field would be noisy).
     *
     * @param array<string,string> $errors
     */
    function field_error(array $errors, string $field, ?string $id = null, bool $alert = false): string
    {
        $message = $errors[$field] ?? null;
        if ($message === null || $message === '') {
            return '';
        }
        $id ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<p class="field-error" id="' . $esc($id) . '"' . ($alert ? ' role="alert"' : '') . '>' . $esc((string) $message) . '</p>';
    }
}

if (!function_exists('field_attrs')) {
    /**
     * Attributes for an input whose field is in error: aria-invalid +
     * aria-describedby pointing at {@see field_error}'s id, plus autofocus on
     * the FIRST errored field so a 422 re-render lands focus on the problem.
     * Emits nothing when the field is clean. Server-rendered attribute only —
     * no JS involved, so the strict CSP is untouched.
     *
     * @param array<string,string> $errors
     */
    function field_attrs(array $errors, string $field, ?string $id = null): string
    {
        if (empty($errors[$field])) {
            return '';
        }
        $id ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $focus = array_key_first($errors) === $field ? ' autofocus' : '';
        return ' aria-invalid="true" aria-describedby="' . $esc($id) . '"' . $focus;
    }
}

if (!function_exists('human_duration')) {
    /**
     * A wait shown to people: "12 seconds" / "about 58 minutes" / "about 2
     * hours". Minutes and hours round UP so the promise is never shorter than
     * the real wait (rate-limit copy, round-2 audit finding 10).
     */
    function human_duration(int $seconds): string
    {
        $seconds = max(1, $seconds);
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        $minutes = intdiv($seconds + 59, 60);
        if ($minutes < 60) {
            return 'about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        return 'about ' . $hours . ' hour' . ($hours === 1 ? '' : 's')
            . ($rem > 0 ? ' ' . $rem . ' minute' . ($rem === 1 ? '' : 's') : '');
    }
}
