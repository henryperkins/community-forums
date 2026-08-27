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

if (!function_exists('iso_datetime')) {
    /**
     * Machine-readable UTC stamp for a <time datetime> attribute. Pairs with
     * {@see post_datetime}, which abbreviates the same instant for display.
     */
    function iso_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        return $ts === false ? '' : gmdate('c', $ts);
    }
}

if (!function_exists('post_datetime')) {
    /**
     * Compact byline stamp for the thread stream. The Imladris thread-view
     * reference renders the post time inline in the byline ("Jul 10 at 09:14"),
     * not a full absolute stamp — at 390px the long form plus an author title,
     * OP and Staff wraps the byline onto a second line. The year is appended only
     * when it is not the current one, so an ordinary reading day stays on one
     * line without ever mislabelling an old post. The unabbreviated UTC value
     * remains available through the element's datetime/title attributes.
     */
    function post_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        if ($ts === false) {
            return '';
        }
        return gmdate(gmdate('Y', $ts) === gmdate('Y') ? 'M j \a\t H:i' : 'M j, Y \a\t H:i', $ts);
    }
}

if (!function_exists('relative_datetime')) {
    /**
     * Elapsed-time stamp for an activity COLUMN — the board list's "when did
     * this last move?".
     *
     * A column is read by comparing its rows, and "6 hours ago" answers that
     * question in a glance where "Aug 27, 2026 at 04:38 UTC" makes you do the
     * arithmetic. It is also short: the absolute form is ~24 unwrappable
     * characters, which overflows any column narrow enough to leave the title
     * its measure. The exact instant stays on the element's datetime/title
     * attributes, so nothing is actually lost.
     *
     * Deliberately coarse — no "3 minutes ago" precision on a list that is only
     * ever scanned, and no future tense: a clock skew reads as "just now".
     */
    function relative_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        if ($ts === false) {
            return '';
        }
        $seconds = time() - $ts;
        if ($seconds < 60) {
            return 'just now';
        }
        $plural = static fn (int $n, string $unit): string => $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago';
        if ($seconds < 3600) {
            return $plural(intdiv($seconds, 60), 'minute');
        }
        if ($seconds < 86400) {
            return $plural(intdiv($seconds, 3600), 'hour');
        }
        $days = intdiv($seconds, 86400);
        if ($days === 1) {
            return 'yesterday';
        }
        if ($days < 7) {
            return $days . ' days ago';
        }
        if ($days < 35) {
            return $plural(intdiv($days, 7), 'week');
        }
        if ($days < 365) {
            return $plural(max(1, intdiv($days, 30)), 'month');
        }
        return $plural(intdiv($days, 365), 'year');
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
     * When $describedBy is supplied, it is preserved as a help-text reference
     * and the error id is appended on invalid fields. Server-rendered attribute
     * only — no JS involved, so the strict CSP is untouched.
     *
     * @param array<string,string> $errors
     */
    function field_attrs(array $errors, string $field, ?string $id = null, ?string $describedBy = null): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (empty($errors[$field])) {
            return $describedBy !== null && $describedBy !== ''
                ? ' aria-describedby="' . $esc($describedBy) . '"'
                : '';
        }
        $id ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
        $description = $describedBy !== null && $describedBy !== '' ? $describedBy . ' ' . $id : $id;
        $focus = array_key_first($errors) === $field ? ' autofocus' : '';
        return ' aria-invalid="true" aria-describedby="' . $esc($description) . '"' . $focus;
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
