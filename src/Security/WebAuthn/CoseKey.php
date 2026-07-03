<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

/**
 * COSE_Key parser for the accepted WebAuthn algorithms: ES256 and RS256.
 */
final class CoseKey
{
    public const ALG_ES256 = -7;
    public const ALG_RS256 = -257;

    private const EC_P256_SPKI_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    private const RSA_ALGORITHM_IDENTIFIER = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    private function __construct(
        public readonly int $alg,
        private readonly string $pem,
    ) {
    }

    public static function fromCbor(string $cborBytes): self
    {
        $map = CborDecoder::decode($cborBytes);
        if (!is_array($map)) {
            throw new WebAuthnException('malformed_key', 'COSE key is not a CBOR map.');
        }

        $kty = $map[1] ?? null;
        $alg = $map[3] ?? null;

        if ($kty === 2 && $alg === self::ALG_ES256) {
            $crv = $map[-1] ?? null;
            $x = $map[-2] ?? null;
            $y = $map[-3] ?? null;
            if ($crv !== 1 || !is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new WebAuthnException('malformed_key', 'COSE EC2 key is not a valid P-256 point encoding.');
            }

            $pem = self::pem(self::EC_P256_SPKI_PREFIX . "\x04" . $x . $y);
            self::assertOpenSslAccepts($pem);
            return new self(self::ALG_ES256, $pem);
        }

        if ($kty === 3 && $alg === self::ALG_RS256) {
            $n = $map[-1] ?? null;
            $e = $map[-2] ?? null;
            if (!is_string($n) || !is_string($e)) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unsupported modulus or exponent size.');
            }

            $n = ltrim($n, "\0");
            $e = ltrim($e, "\0");
            if (strlen($n) < 256 || strlen($n) > 512 || strlen($e) < 1 || strlen($e) > 4) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unsupported modulus or exponent size.');
            }

            $exp = self::decodeSmallUint($e);
            if ($exp < 3 || ($exp & 1) === 0) {
                throw new WebAuthnException('malformed_key', 'COSE RSA key has an unusable public exponent.');
            }

            $rsa = self::derSequence(self::derUint($n) . self::derUint($e));
            $spki = self::derSequence(self::RSA_ALGORITHM_IDENTIFIER . self::derBitString($rsa));
            $pem = self::pem($spki);
            self::assertOpenSslAccepts($pem);
            return new self(self::ALG_RS256, $pem);
        }

        throw new WebAuthnException('unsupported_algorithm', 'Only ES256 and RS256 credentials are accepted.');
    }

    public function toPem(): string
    {
        return $this->pem;
    }

    public function verify(string $data, string $signature): bool
    {
        $key = @openssl_pkey_get_public($this->pem);
        if ($key === false) {
            return false;
        }
        return openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private static function assertOpenSslAccepts(string $pem): void
    {
        if (@openssl_pkey_get_public($pem) === false) {
            throw new WebAuthnException('malformed_key', 'COSE key does not decode to a usable OpenSSL public key.');
        }
    }

    private static function decodeSmallUint(string $bytes): int
    {
        $value = 0;
        foreach (unpack('C*', $bytes) as $byte) {
            $value = ($value << 8) | $byte;
        }
        return $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\0");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derBitString(string $content): string
    {
        return "\x03" . self::derLength(strlen($content) + 1) . "\x00" . $content;
    }

    private static function derUint(string $raw): string
    {
        $raw = ltrim($raw, "\0");
        if ($raw === '') {
            $raw = "\0";
        }
        if ((ord($raw[0]) & 0x80) !== 0) {
            $raw = "\0" . $raw;
        }
        return "\x02" . self::derLength(strlen($raw)) . $raw;
    }
}
