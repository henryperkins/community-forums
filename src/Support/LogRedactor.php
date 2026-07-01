<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Structured-log field redaction (Foundation F8). Every telemetry/log context
 * array passes through redact() so secrets, credentials, challenges, tokens,
 * PII, and private content cannot leak into ordinary logs. Matching is by
 * sensitive key - a substring list plus a boundary list for short fragments -
 * and by sensitive value shape. Digests and numeric IDs are provenance, not
 * secrets, and pass through. Over-redaction is acceptable; leakage is not.
 */
final class LogRedactor
{
    public const REDACTED = '[redacted]';

    /** Case-insensitive substring match anywhere in the key. */
    private const SENSITIVE_KEY_SUBSTRINGS = [
        'password', 'passphrase', 'secret', 'token', 'challenge', 'credential',
        'authorization', 'cookie', 'signature', 'private', 'api_key',
        'recovery', 'totp', 'nonce', 'email', 'ciphertext', 'body', 'content',
    ];

    /** Short/ambiguous fragments: exact key, `x_*`, or `*_x` only. */
    private const SENSITIVE_KEY_BOUNDARY = ['ip', 'tag', 'otp'];

    /** Value shapes that redact regardless of key. */
    private const SENSITIVE_VALUE_PATTERNS = [
        '/^rbt_[0-9a-f]+$/i',
        '/^svcsec_[0-9a-f]+$/i',
        '/^Bearer\s+\S+/i',
    ];

    /**
     * @param array<array-key,mixed> $fields
     * @return array<array-key,mixed>
     */
    public static function redact(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $out[$key] = self::REDACTED;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::redact($value);
                continue;
            }
            if (is_string($value) && self::isSensitiveValue($value)) {
                $out[$key] = self::REDACTED;
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEY_SUBSTRINGS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        foreach (self::SENSITIVE_KEY_BOUNDARY as $frag) {
            if ($lower === $frag || str_starts_with($lower, $frag . '_') || str_ends_with($lower, '_' . $frag)) {
                return true;
            }
        }

        return false;
    }

    private static function isSensitiveValue(string $value): bool
    {
        foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
