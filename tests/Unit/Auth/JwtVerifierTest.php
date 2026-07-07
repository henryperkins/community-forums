<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Core\OidcVerificationException;
use App\Service\OAuth\Oidc\JwtVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Inc 8 (P5-12) — the issuer-pinned id_token verifier (ADR 0004 D8). Covers the
 * TM-ID-01 fixture (cross-issuer / wrong audience / wrong azp rejected), the
 * nonce arm of TM-ID-02, stale time claims, the alg allowlist (incl. `none`
 * and HS256 key-confusion forgeries), and kid selection/rotation semantics.
 * All keys are generated in-test; no network, no DB.
 */
final class JwtVerifierTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const ISSUER = 'https://idp.test';
    private const CLIENT = 'client-1';
    private const NONCE = 'nonce-abc';

    /** @var \OpenSSLAsymmetricKey|null */
    private static $keyA = null;
    /** @var \OpenSSLAsymmetricKey|null */
    private static $keyB = null;

    // ---- happy paths -------------------------------------------------------

    public function test_valid_token_returns_claims(): void
    {
        $claims = $this->verify($this->token($this->claims()));

        self::assertSame('sub-1', $claims['sub']);
        self::assertSame('ada@example.test', $claims['email']);
    }

    public function test_rotated_key_verifies_when_new_kid_is_in_the_set(): void
    {
        $jwt = $this->token($this->claims(), key: self::keyB(), kid: 'kid-b');
        $claims = $this->verify($jwt, jwks: $this->jwks(['kid-a' => self::keyA(), 'kid-b' => self::keyB()]));

        self::assertSame('sub-1', $claims['sub']);
    }

    public function test_kidless_token_accepted_only_with_a_single_candidate_key(): void
    {
        $jwt = $this->token($this->claims(), kid: null);

        self::assertSame('sub-1', $this->verify($jwt)['sub']);

        $this->expectExceptionObject(new OidcVerificationException('unknown_kid'));
        $this->verify($jwt, jwks: $this->jwks(['kid-a' => self::keyA(), 'kid-b' => self::keyB()]));
    }

    public function test_exp_within_leeway_is_accepted(): void
    {
        $claims = $this->claims(['exp' => self::NOW - JwtVerifier::LEEWAY_SECONDS + 5]);
        self::assertSame('sub-1', $this->verify($this->token($claims))['sub']);
    }

    // ---- TM-ID-01: issuer / audience / azp ---------------------------------

    public function test_cross_issuer_token_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('issuer_mismatch'));
        $this->verify($this->token($this->claims(['iss' => 'https://evil.test'])));
    }

    public function test_wrong_audience_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('audience_mismatch'));
        $this->verify($this->token($this->claims(['aud' => 'someone-else'])));
    }

    public function test_audience_array_with_wrong_azp_is_rejected(): void
    {
        $claims = $this->claims(['aud' => [self::CLIENT, 'other'], 'azp' => 'other']);
        $this->expectExceptionObject(new OidcVerificationException('azp_mismatch'));
        $this->verify($this->token($claims));
    }

    public function test_multiple_audiences_without_azp_are_rejected(): void
    {
        $claims = $this->claims(['aud' => [self::CLIENT, 'other']]);
        unset($claims['azp']);
        $this->expectExceptionObject(new OidcVerificationException('azp_missing'));
        $this->verify($this->token($claims));
    }

    public function test_audience_array_with_matching_azp_is_accepted(): void
    {
        $claims = $this->claims(['aud' => [self::CLIENT, 'other'], 'azp' => self::CLIENT]);
        self::assertSame('sub-1', $this->verify($this->token($claims))['sub']);
    }

    public function test_wrong_azp_on_single_audience_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('azp_mismatch'));
        $this->verify($this->token($this->claims(['azp' => 'other'])));
    }

    // ---- TM-ID-02 (nonce arm) ----------------------------------------------

    public function test_missing_nonce_claim_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['nonce']);
        $this->expectExceptionObject(new OidcVerificationException('nonce_mismatch'));
        $this->verify($this->token($claims));
    }

    public function test_wrong_nonce_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('nonce_mismatch'));
        $this->verify($this->token($this->claims(['nonce' => 'stolen'])));
    }

    public function test_empty_expected_nonce_fails_closed(): void
    {
        // The core always sends a nonce; verifying without one is a caller bug
        // and must never become an accept-anything path.
        $this->expectExceptionObject(new OidcVerificationException('nonce_missing'));
        (new JwtVerifier())->verify(
            $this->token($this->claims()),
            $this->jwks(['kid-a' => self::keyA()]),
            self::ISSUER,
            self::CLIENT,
            '',
            ['RS256'],
            self::NOW,
        );
    }

    // ---- stale / future time claims ----------------------------------------

    public function test_expired_token_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('token_expired'));
        $this->verify($this->token($this->claims(['exp' => self::NOW - JwtVerifier::LEEWAY_SECONDS - 5])));
    }

    public function test_missing_exp_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['exp']);
        $this->expectExceptionObject(new OidcVerificationException('token_expired'));
        $this->verify($this->token($claims));
    }

    public function test_missing_iat_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['iat']);
        $this->expectExceptionObject(new OidcVerificationException('iat_missing'));
        $this->verify($this->token($claims));
    }

    public function test_future_iat_beyond_leeway_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('issued_in_future'));
        $this->verify($this->token($this->claims(['iat' => self::NOW + JwtVerifier::LEEWAY_SECONDS + 5])));
    }

    public function test_future_nbf_is_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('not_yet_valid'));
        $this->verify($this->token($this->claims(['nbf' => self::NOW + JwtVerifier::LEEWAY_SECONDS + 5])));
    }

    // ---- signature / algorithm ---------------------------------------------

    public function test_tampered_payload_is_rejected(): void
    {
        $jwt = $this->token($this->claims());
        [$h, $p, $s] = explode('.', $jwt);
        $forged = self::b64u((string) json_encode($this->claims(['sub' => 'attacker']) ));

        $this->expectExceptionObject(new OidcVerificationException('signature_invalid'));
        $this->verify($h . '.' . $forged . '.' . $s);
    }

    public function test_alg_none_is_rejected(): void
    {
        $h = self::b64u((string) json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $p = self::b64u((string) json_encode($this->claims()));

        $this->expectExceptionObject(new OidcVerificationException('algorithm_not_allowed'));
        $this->verify($h . '.' . $p . '.');
    }

    public function test_hs256_key_confusion_forgery_is_rejected(): void
    {
        // Classic alg-confusion: attacker HMACs with the PUBLIC key bytes. The
        // allowlist must refuse before any key material is touched.
        $details = openssl_pkey_get_details(self::keyA());
        self::assertIsArray($details);
        $h = self::b64u((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'kid-a']));
        $p = self::b64u((string) json_encode($this->claims()));
        $sig = self::b64u(hash_hmac('sha256', $h . '.' . $p, (string) $details['key'], true));

        $this->expectExceptionObject(new OidcVerificationException('algorithm_not_allowed'));
        $this->verify($h . '.' . $p . '.' . $sig);
    }

    public function test_unknown_kid_is_rejected_with_a_refreshable_reason(): void
    {
        $jwt = $this->token($this->claims(), key: self::keyB(), kid: 'kid-unknown');
        try {
            $this->verify($jwt);
            self::fail('Expected unknown_kid rejection.');
        } catch (OidcVerificationException $e) {
            // The caller keys a single forced JWKS refresh off this reason.
            self::assertSame('unknown_kid', $e->reason);
        }
    }

    public function test_structurally_invalid_tokens_are_rejected(): void
    {
        $this->expectExceptionObject(new OidcVerificationException('malformed_token'));
        $this->verify('not-a-jwt');
    }

    public function test_missing_sub_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['sub']);
        $this->expectExceptionObject(new OidcVerificationException('subject_missing'));
        $this->verify($this->token($claims));
    }

    public function test_unverified_header_exposes_kid_for_cache_refresh(): void
    {
        $jwt = $this->token($this->claims(), kid: 'kid-a');
        self::assertSame('kid-a', JwtVerifier::unverifiedHeader($jwt)['kid'] ?? null);
        self::assertSame([], JwtVerifier::unverifiedHeader('garbage'));
    }

    public function test_jwk_with_a_spurious_leading_zero_modulus_still_verifies(): void
    {
        // RFC 7518 mandates minimal octets for n/e, but sloppy IdPs pad them;
        // the DER builder canonicalises (same rule as WebAuthn's CoseKey), so
        // the padded form must select the same key material.
        $details = openssl_pkey_get_details(self::keyA());
        self::assertIsArray($details);
        $jwks = ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => 'kid-a',
            'n' => self::b64u("\x00" . (string) $details['rsa']['n']),
            'e' => self::b64u((string) $details['rsa']['e']),
        ]]];

        self::assertSame('sub-1', $this->verify($this->token($this->claims()), jwks: $jwks)['sub']);
    }

    public function test_padded_signature_segment_is_rejected(): void
    {
        // JWS segments are unpadded base64url (RFC 7515); the strict shared
        // decoder refuses padded re-encodings of an otherwise valid signature.
        $jwt = $this->token($this->claims());
        [$h, $p, $s] = explode('.', $jwt);

        $this->expectExceptionObject(new OidcVerificationException('signature_invalid'));
        $this->verify($h . '.' . $p . '.' . $s . '==');
    }

    // ---- helpers ------------------------------------------------------------

    /** @param array<string,mixed> $overrides */
    private function claims(array $overrides = []): array
    {
        return $overrides + [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT,
            'sub' => 'sub-1',
            'nonce' => self::NONCE,
            'iat' => self::NOW - 30,
            'exp' => self::NOW + 600,
            'email' => 'ada@example.test',
        ];
    }

    /** @param array<string,mixed> $claims @param \OpenSSLAsymmetricKey|null $key */
    private function token(array $claims, $key = null, ?string $kid = 'kid-a'): string
    {
        $key ??= self::keyA();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if ($kid !== null) {
            $header['kid'] = $kid;
        }
        $h = self::b64u((string) json_encode($header));
        $p = self::b64u((string) json_encode($claims));
        openssl_sign($h . '.' . $p, $sig, $key, OPENSSL_ALGO_SHA256);
        return $h . '.' . $p . '.' . self::b64u($sig);
    }

    /** @param array<string,mixed>|null $jwks @return array<string,mixed> */
    private function verify(string $jwt, ?array $jwks = null): array
    {
        return (new JwtVerifier())->verify(
            $jwt,
            $jwks ?? $this->jwks(['kid-a' => self::keyA()]),
            self::ISSUER,
            self::CLIENT,
            self::NONCE,
            ['RS256'],
            self::NOW,
        );
    }

    /** @param array<string,\OpenSSLAsymmetricKey> $keys @return array<string,mixed> */
    private function jwks(array $keys): array
    {
        $out = [];
        foreach ($keys as $kid => $key) {
            $details = openssl_pkey_get_details($key);
            self::assertIsArray($details);
            $out[] = [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $kid,
                'n' => self::b64u((string) $details['rsa']['n']),
                'e' => self::b64u((string) $details['rsa']['e']),
            ];
        }
        return ['keys' => $out];
    }

    /** @return \OpenSSLAsymmetricKey */
    private static function keyA()
    {
        return self::$keyA ??= self::newKey();
    }

    /** @return \OpenSSLAsymmetricKey */
    private static function keyB()
    {
        return self::$keyB ??= self::newKey();
    }

    /** @return \OpenSSLAsymmetricKey */
    private static function newKey()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            self::fail('openssl_pkey_new failed: ' . (string) openssl_error_string());
        }
        return $key;
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
