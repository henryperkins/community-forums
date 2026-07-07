<?php

declare(strict_types=1);

namespace App\Service\OAuth\Oidc;

use App\Core\OidcVerificationException;

/**
 * Issuer-pinned OIDC id_token verifier (P5-12, ADR 0004 D8): RS256 signature
 * over a caller-supplied JWKS, then iss / aud / azp / nonce / exp / iat / nbf
 * with a fixed leeway. Pure — no network, no DB, no clock unless `$now` is
 * omitted. Refusals throw OidcVerificationException with a stable reason;
 * `unknown_kid` is the one the caller may answer with a single forced JWKS
 * refresh (TM-ID-03's rotation arm).
 *
 * Only RS256 is implemented. The allowlist parameter can narrow but never
 * widen that — `none` and HMAC-family confusion forgeries are refused before
 * any key material is touched.
 */
final class JwtVerifier
{
    public const LEEWAY_SECONDS = 300;

    /**
     * @param array<string,mixed> $jwks decoded JWKS document (['keys' => [...]])
     * @param list<string> $allowedAlgs
     * @return array<string,mixed> the verified claims
     * @throws OidcVerificationException
     */
    public function verify(
        string $jwt,
        array $jwks,
        string $issuer,
        string $clientId,
        string $expectedNonce,
        array $allowedAlgs = ['RS256'],
        ?int $now = null,
    ): array {
        $now ??= time();
        if ($expectedNonce === '') {
            // The core always sends a nonce; a nonce-less verification request
            // is a caller bug and must fail closed, never verify-anything.
            throw new OidcVerificationException('nonce_missing');
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new OidcVerificationException('malformed_token');
        }
        [$h64, $p64, $s64] = $parts;
        $header = self::jsonPart($h64);
        $claims = self::jsonPart($p64);
        if ($header === null || $claims === null) {
            throw new OidcVerificationException('malformed_token');
        }

        $alg = (string) ($header['alg'] ?? '');
        if ($alg !== 'RS256' || !in_array('RS256', $allowedAlgs, true)) {
            throw new OidcVerificationException('algorithm_not_allowed');
        }

        $key = $this->selectKey($jwks, isset($header['kid']) ? (string) $header['kid'] : null);
        $signature = self::b64uDecode($s64);
        if ($signature === null || $signature === '') {
            throw new OidcVerificationException('signature_invalid');
        }
        if (openssl_verify($h64 . '.' . $p64, $signature, $this->rsaPem($key), OPENSSL_ALGO_SHA256) !== 1) {
            throw new OidcVerificationException('signature_invalid');
        }

        // ---- claims (order: issuer, audience, azp, time, nonce, subject) ----
        if (!isset($claims['iss']) || !is_string($claims['iss']) || $claims['iss'] !== $issuer) {
            throw new OidcVerificationException('issuer_mismatch');
        }

        $aud = $claims['aud'] ?? null;
        $audiences = is_array($aud) ? array_values(array_map('strval', $aud)) : [(string) $aud];
        if (!in_array($clientId, $audiences, true)) {
            throw new OidcVerificationException('audience_mismatch');
        }
        if (count($audiences) > 1 && !isset($claims['azp'])) {
            throw new OidcVerificationException('azp_missing');
        }
        if (isset($claims['azp']) && (string) $claims['azp'] !== $clientId) {
            throw new OidcVerificationException('azp_mismatch');
        }

        if (!is_numeric($claims['exp'] ?? null)) {
            throw new OidcVerificationException('token_expired');
        }
        if ($now >= (int) $claims['exp'] + self::LEEWAY_SECONDS) {
            throw new OidcVerificationException('token_expired');
        }
        if (!is_numeric($claims['iat'] ?? null)) {
            throw new OidcVerificationException('iat_missing');
        }
        if ((int) $claims['iat'] > $now + self::LEEWAY_SECONDS) {
            throw new OidcVerificationException('issued_in_future');
        }
        if (isset($claims['nbf']) && is_numeric($claims['nbf']) && (int) $claims['nbf'] > $now + self::LEEWAY_SECONDS) {
            throw new OidcVerificationException('not_yet_valid');
        }

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || !hash_equals($expectedNonce, $nonce)) {
            throw new OidcVerificationException('nonce_mismatch');
        }

        if (!isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') {
            throw new OidcVerificationException('subject_missing');
        }

        return $claims;
    }

    /**
     * The UNVERIFIED JWT header — only ever for pre-verification routing
     * (e.g. "is this kid in my cached JWKS, or do I refresh first?").
     *
     * @return array<string,mixed> empty on any structural failure
     */
    public static function unverifiedHeader(string $jwt): array
    {
        $first = explode('.', $jwt)[0];
        return self::jsonPart($first) ?? [];
    }

    // ---- internals ---------------------------------------------------------

    /**
     * @param array<string,mixed> $jwks
     * @return array<string,mixed> the selected RSA signing JWK
     */
    private function selectKey(array $jwks, ?string $kid): array
    {
        $candidates = [];
        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (!is_array($key)
                || (string) ($key['kty'] ?? '') !== 'RSA'
                || (isset($key['use']) && (string) $key['use'] !== 'sig')
                || !isset($key['n'], $key['e'])
            ) {
                continue;
            }
            $candidates[] = $key;
        }

        if ($kid !== null) {
            foreach ($candidates as $key) {
                if ((string) ($key['kid'] ?? '') === $kid) {
                    return $key;
                }
            }
            throw new OidcVerificationException('unknown_kid');
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        // No kid and zero-or-many candidates: never guess a key.
        throw new OidcVerificationException('unknown_kid');
    }

    /**
     * RSA public JWK (n, e) → SubjectPublicKeyInfo PEM for openssl_verify.
     *
     * @param array<string,mixed> $jwk
     */
    private function rsaPem(array $jwk): string
    {
        $n = self::b64uDecode((string) $jwk['n']);
        $e = self::b64uDecode((string) $jwk['e']);
        if ($n === null || $e === null || $n === '' || $e === '') {
            throw new OidcVerificationException('jwks_invalid');
        }

        $rsa = self::asn1Seq(self::asn1Int($n) . self::asn1Int($e));
        // rsaEncryption OID 1.2.840.113549.1.1.1 + NULL params.
        $algorithm = self::asn1Seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $spki = self::asn1Seq($algorithm . self::asn1BitString($rsa));

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private static function asn1Len(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function asn1Int(string $raw): string
    {
        if ((ord($raw[0]) & 0x80) !== 0) {
            $raw = "\x00" . $raw; // keep the INTEGER positive
        }
        return "\x02" . self::asn1Len(strlen($raw)) . $raw;
    }

    private static function asn1Seq(string $inner): string
    {
        return "\x30" . self::asn1Len(strlen($inner)) . $inner;
    }

    private static function asn1BitString(string $inner): string
    {
        $inner = "\x00" . $inner; // zero unused bits
        return "\x03" . self::asn1Len(strlen($inner)) . $inner;
    }

    /** @return array<string,mixed>|null */
    private static function jsonPart(string $b64u): ?array
    {
        $raw = self::b64uDecode($b64u);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function b64uDecode(string $b64u): ?string
    {
        $padded = str_pad(strtr($b64u, '-_', '+/'), (int) (ceil(strlen($b64u) / 4) * 4), '=');
        $raw = base64_decode($padded, true);
        return $raw === false ? null : $raw;
    }
}
