<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\CoseKey;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class CoseKeyTest extends TestCase
{
    public function test_es256_cose_key_verifies_a_real_openssl_signature_and_rejects_tampering(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        self::assertNotFalse($key);
        $d = openssl_pkey_get_details($key);
        self::assertIsArray($d);
        $cose = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => self::pad32($d['ec']['x']), -3 => self::pad32($d['ec']['y'])]);

        $parsed = CoseKey::fromCbor($cose);
        self::assertSame(CoseKey::ALG_ES256, $parsed->alg);

        $data = 'authenticator-data||client-data-hash';
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);
        self::assertTrue($parsed->verify($data, $sig));
        self::assertFalse($parsed->verify($data . 'x', $sig));
        $sig[4] = chr(ord($sig[4]) ^ 0x01);
        self::assertFalse($parsed->verify($data, $sig));
    }

    public function test_rs256_cose_key_verifies_and_pem_matches_openssl_view_of_the_key(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $d = openssl_pkey_get_details($key);
        self::assertIsArray($d);
        $cose = self::coseMap([1 => 3, 3 => -257, -1 => $d['rsa']['n'], -2 => $d['rsa']['e']]);

        $parsed = CoseKey::fromCbor($cose);
        self::assertSame(CoseKey::ALG_RS256, $parsed->alg);
        self::assertSame(trim($d['key']), trim($parsed->toPem()));

        $data = 'assertion-bytes';
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);
        self::assertTrue($parsed->verify($data, $sig));
    }

    public function test_rejects_unsupported_algorithms_and_malformed_keys(): void
    {
        $okp = self::coseMap([1 => 1, 3 => -8, -1 => 6, -2 => random_bytes(32)]);
        try {
            CoseKey::fromCbor($okp);
            self::fail('EdDSA must be refused');
        } catch (WebAuthnException $e) {
            self::assertSame('unsupported_algorithm', $e->code);
        }

        $bad = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => random_bytes(31), -3 => random_bytes(32)]);
        try {
            CoseKey::fromCbor($bad);
            self::fail('Short coordinate must be refused');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        $offCurve = self::coseMap([1 => 2, 3 => -7, -1 => 1, -2 => str_repeat("\0", 32), -3 => str_repeat("\0", 32)]);
        try {
            CoseKey::fromCbor($offCurve);
            self::fail('Off-curve point must be refused before storage');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        $badRsa = self::coseMap([1 => 3, 3 => -257, -1 => "\x80" . str_repeat("\0", 255), -2 => "\x02"]);
        try {
            CoseKey::fromCbor($badRsa);
            self::fail('Even RSA exponent must be refused before storage');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_key', $e->code);
        }

        $this->expectException(WebAuthnException::class);
        CoseKey::fromCbor("\x01");
    }

    private static function pad32(string $bin): string
    {
        return str_pad(ltrim($bin, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /** @param array<int, int|string> $entries */
    private static function coseMap(array $entries): string
    {
        $out = chr(0xa0 + count($entries));
        foreach ($entries as $k => $v) {
            $out .= self::cborInt($k);
            $out .= is_int($v) ? self::cborInt($v) : self::cborBstr($v);
        }
        return $out;
    }

    private static function cborInt(int $v): string
    {
        return $v >= 0 ? self::cborHead(0, $v) : self::cborHead(1, -1 - $v);
    }

    private static function cborBstr(string $s): string
    {
        return self::cborHead(2, strlen($s)) . $s;
    }

    private static function cborHead(int $major, int $value): string
    {
        $m = $major << 5;
        if ($value < 24) {
            return chr($m | $value);
        }
        if ($value < 256) {
            return chr($m | 24) . chr($value);
        }
        if ($value < 65536) {
            return chr($m | 25) . pack('n', $value);
        }
        return chr($m | 26) . pack('N', $value);
    }
}
