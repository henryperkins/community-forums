<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Strict base64url (RFC 4648 section 5, unpadded).
 */
final class Base64Url
{
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): ?string
    {
        if ($encoded === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
            return null;
        }

        $remainder = strlen($encoded) % 4;
        if ($remainder === 1) {
            return null;
        }

        $b64 = strtr($encoded, '-_', '+/');
        if ($remainder > 0) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false) {
            return null;
        }

        return self::encode($decoded) === $encoded ? $decoded : null;
    }
}
