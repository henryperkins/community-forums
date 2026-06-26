<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stateless HMAC tokens for login-free links (e.g. one-click unsubscribe,
 * ADMIN §7.6). The token binds a purpose + value to APP_KEY and is verified
 * with a timing-safe comparison; no DB row is needed.
 */
final class SignedToken
{
    public static function sign(string $purpose, string $value, string $key): string
    {
        return hash_hmac('sha256', $purpose . "\0" . $value, $key);
    }

    public static function verify(string $purpose, string $value, string $token, string $key): bool
    {
        if ($token === '' || $key === '') {
            return false;
        }
        return hash_equals(self::sign($purpose, $value, $key), $token);
    }
}
