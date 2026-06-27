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
