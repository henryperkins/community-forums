<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * Minimal CBOR decoder for the WebAuthn subset (RFC 8949).
 */
final class CborDecoder
{
    private const MAX_DEPTH = 8;
    private const MAX_ITEMS = 1024;
    private const MAX_BYTES = 1048576;

    public static function decode(string $bytes): mixed
    {
        [$value, $rest] = self::decodeFirst($bytes);
        if ($rest !== '') {
            throw new WebAuthnException('malformed_cbor', 'Trailing bytes after CBOR value.');
        }
        return $value;
    }

    /** @return array{0:mixed,1:string} */
    public static function decodeFirst(string $bytes): array
    {
        $offset = 0;
        $value = self::item($bytes, $offset, 0);
        return [$value, substr($bytes, $offset)];
    }

    private static function item(string $bytes, int &$offset, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new WebAuthnException('malformed_cbor', 'CBOR nesting exceeds depth cap.');
        }

        $initial = self::byte($bytes, $offset);
        $major = $initial >> 5;
        $info = $initial & 0x1f;
        if ($info === 31) {
            throw new WebAuthnException('malformed_cbor', 'Indefinite-length CBOR is not accepted.');
        }

        return match ($major) {
            0 => self::length($bytes, $offset, $info),
            1 => -1 - self::length($bytes, $offset, $info),
            2, 3 => self::str($bytes, $offset, self::length($bytes, $offset, $info)),
            4 => self::arr($bytes, $offset, self::length($bytes, $offset, $info), $depth),
            5 => self::map($bytes, $offset, self::length($bytes, $offset, $info), $depth),
            7 => match ($info) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new WebAuthnException('malformed_cbor', 'Unsupported CBOR simple or float value.'),
            },
            default => throw new WebAuthnException('malformed_cbor', 'Unsupported CBOR major type ' . $major . '.'),
        };
    }

    private static function byte(string $bytes, int &$offset): int
    {
        if ($offset >= strlen($bytes)) {
            throw new WebAuthnException('malformed_cbor', 'Unexpected end of CBOR input.');
        }
        return ord($bytes[$offset++]);
    }

    private static function length(string $bytes, int &$offset, int $info): int
    {
        if ($info < 24) {
            return $info;
        }

        $extra = match ($info) {
            24 => 1,
            25 => 2,
            26 => 4,
            27 => 8,
            default => throw new WebAuthnException('malformed_cbor', 'Reserved CBOR additional-info value.'),
        };

        $length = 0;
        for ($i = 0; $i < $extra; $i++) {
            $length = ($length << 8) | self::byte($bytes, $offset);
        }
        if ($length < 0 || $length > self::MAX_BYTES) {
            throw new WebAuthnException('malformed_cbor', 'CBOR length out of accepted range.');
        }
        return $length;
    }

    private static function str(string $bytes, int &$offset, int $length): string
    {
        if ($offset + $length > strlen($bytes)) {
            throw new WebAuthnException('malformed_cbor', 'CBOR string exceeds available input.');
        }
        $value = substr($bytes, $offset, $length);
        $offset += $length;
        return $value;
    }

    /** @return list<mixed> */
    private static function arr(string $bytes, int &$offset, int $count, int $depth): array
    {
        if ($count > self::MAX_ITEMS) {
            throw new WebAuthnException('malformed_cbor', 'CBOR array exceeds item cap.');
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::item($bytes, $offset, $depth + 1);
        }
        return $out;
    }

    /** @return array<int|string, mixed> */
    private static function map(string $bytes, int &$offset, int $count, int $depth): array
    {
        if ($count > self::MAX_ITEMS) {
            throw new WebAuthnException('malformed_cbor', 'CBOR map exceeds item cap.');
        }

        $out = [];
        $seen = [];
        for ($i = 0; $i < $count; $i++) {
            $key = self::item($bytes, $offset, $depth + 1);
            if (!is_int($key) && !is_string($key)) {
                throw new WebAuthnException('malformed_cbor', 'CBOR map key must be an integer or string.');
            }
            if (is_string($key) && preg_match('/^-?(?:0|[1-9][0-9]*)$/', $key) === 1) {
                throw new WebAuthnException('malformed_cbor', 'Numeric-string CBOR map keys are not accepted.');
            }

            $tag = (is_int($key) ? 'i:' : 's:') . $key;
            if (isset($seen[$tag])) {
                throw new WebAuthnException('malformed_cbor', 'Duplicate CBOR map key.');
            }
            $seen[$tag] = true;
            $out[$key] = self::item($bytes, $offset, $depth + 1);
        }
        return $out;
    }
}
