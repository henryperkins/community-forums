<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

use App\Support\Base64Url;

/**
 * Test-only WebAuthn authenticator: real OpenSSL keypairs and signed payloads.
 */
final class WebAuthnHarness
{
    public function __construct(
        private readonly string $rpId = 'localhost',
        private readonly string $origin = 'http://localhost:8000',
    ) {
    }

    /** @return array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string, alg:int} */
    public function createCredential(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        assert($key !== false);
        $details = openssl_pkey_get_details($key);
        assert(is_array($details));
        $cose = self::coseMap([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => self::pad32($details['ec']['x']),
            -3 => self::pad32($details['ec']['y']),
        ]);

        return ['privateKey' => $key, 'credentialId' => random_bytes(32), 'coseKey' => $cose, 'alg' => -7];
    }

    /** @return array{privateKey:\OpenSSLAsymmetricKey, credentialId:string, coseKey:string, alg:int} */
    public function rs256Credential(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);
        $details = openssl_pkey_get_details($key);
        assert(is_array($details));
        $cose = self::coseMap([1 => 3, 3 => -257, -1 => $details['rsa']['n'], -2 => $details['rsa']['e']]);

        return ['privateKey' => $key, 'credentialId' => random_bytes(32), 'coseKey' => $cose, 'alg' => -257];
    }

    /** @param array{type?:string, origin?:string, rpId?:string, flags?:int, signCount?:int} $overrides */
    public function registrationPayload(array $cred, string $challenge, array $overrides = []): string
    {
        $clientData = $this->clientData(
            $overrides['type'] ?? 'webauthn.create',
            $challenge,
            $overrides['origin'] ?? $this->origin,
        );
        $authData = $this->authData(
            $overrides['rpId'] ?? $this->rpId,
            $overrides['flags'] ?? (0x01 | 0x04 | 0x40),
            $overrides['signCount'] ?? 0,
            $cred['credentialId'],
            $cred['coseKey'],
        );
        $attObj = "\xa3"
            . self::tstr('fmt') . self::tstr('none')
            . self::tstr('attStmt') . "\xa0"
            . self::tstr('authData') . self::bstr($authData);

        return json_encode([
            'id' => Base64Url::encode($cred['credentialId']),
            'rawId' => Base64Url::encode($cred['credentialId']),
            'type' => 'public-key',
            'transports' => ['internal'],
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientData),
                'attestationObject' => Base64Url::encode($attObj),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array{type?:string, origin?:string, rpId?:string, flags?:int, challengeOverride?:string, tamperSignature?:bool} $overrides */
    public function assertionPayload(array $cred, string $challenge, int $signCount, array $overrides = []): string
    {
        $clientData = $this->clientData(
            $overrides['type'] ?? 'webauthn.get',
            $overrides['challengeOverride'] ?? $challenge,
            $overrides['origin'] ?? $this->origin,
        );
        $authData = $this->authData(
            $overrides['rpId'] ?? $this->rpId,
            $overrides['flags'] ?? (0x01 | 0x04),
            $signCount,
            null,
            null,
        );
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $cred['privateKey'], OPENSSL_ALGO_SHA256);
        if (($overrides['tamperSignature'] ?? false) === true) {
            $signature[5] = chr(ord($signature[5]) ^ 0xff);
        }

        return json_encode([
            'id' => Base64Url::encode($cred['credentialId']),
            'rawId' => Base64Url::encode($cred['credentialId']),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientData),
                'authenticatorData' => Base64Url::encode($authData),
                'signature' => Base64Url::encode($signature),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function authData(string $rpId, int $flags, int $signCount, ?string $credentialId, ?string $coseKey): string
    {
        $out = hash('sha256', $rpId, true) . chr($flags) . pack('N', $signCount);
        if ($credentialId !== null && $coseKey !== null) {
            $out .= str_repeat("\xAA", 16) . pack('n', strlen($credentialId)) . $credentialId . $coseKey;
        }
        return $out;
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => Base64Url::encode($challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
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
            $out .= self::int($k) . (is_int($v) ? self::int($v) : self::bstr($v));
        }
        return $out;
    }

    private static function int(int $v): string
    {
        return $v >= 0 ? self::head(0, $v) : self::head(1, -1 - $v);
    }

    private static function bstr(string $s): string
    {
        return self::head(2, strlen($s)) . $s;
    }

    private static function tstr(string $s): string
    {
        return self::head(3, strlen($s)) . $s;
    }

    private static function head(int $major, int $value): string
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
