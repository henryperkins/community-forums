<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\AuthenticatorData;
use App\Security\WebAuthn\CborDecoder;
use App\Security\WebAuthn\CoseKey;
use App\Support\Base64Url;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class WebAuthnHarnessTest extends TestCase
{
    public function test_registration_payload_decomposes_into_valid_protocol_pieces(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $payload = json_decode($h->registrationPayload($cred, $challenge), true);

        $clientData = json_decode((string) Base64Url::decode($payload['response']['clientDataJSON']), true);
        self::assertSame('webauthn.create', $clientData['type']);
        self::assertSame(Base64Url::encode($challenge), $clientData['challenge']);
        self::assertSame('http://localhost:8000', $clientData['origin']);

        $attObj = CborDecoder::decode((string) Base64Url::decode($payload['response']['attestationObject']));
        self::assertSame('none', $attObj['fmt']);
        self::assertSame([], $attObj['attStmt']);
        $auth = AuthenticatorData::parse($attObj['authData']);
        self::assertSame($cred['credentialId'], $auth->credentialId);
        self::assertSame($cred['coseKey'], $auth->credentialPublicKey);
        self::assertSame(CoseKey::ALG_ES256, CoseKey::fromCbor((string) $auth->credentialPublicKey)->alg);
    }

    public function test_assertion_signature_verifies_against_the_minted_cose_key_for_both_algorithms(): void
    {
        $h = new WebAuthnHarness();
        foreach ([$h->createCredential(), $h->rs256Credential()] as $cred) {
            $challenge = random_bytes(32);
            $payload = json_decode($h->assertionPayload($cred, $challenge, 5), true);
            $authData = (string) Base64Url::decode($payload['response']['authenticatorData']);
            $clientDataRaw = (string) Base64Url::decode($payload['response']['clientDataJSON']);
            $sig = (string) Base64Url::decode($payload['response']['signature']);
            $key = CoseKey::fromCbor($cred['coseKey']);
            self::assertTrue($key->verify($authData . hash('sha256', $clientDataRaw, true), $sig));
        }
    }

    public function test_tamper_override_breaks_the_signature(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $payload = json_decode($h->assertionPayload($cred, random_bytes(32), 1, ['tamperSignature' => true]), true);
        $authData = (string) Base64Url::decode($payload['response']['authenticatorData']);
        $clientDataRaw = (string) Base64Url::decode($payload['response']['clientDataJSON']);
        $sig = (string) Base64Url::decode($payload['response']['signature']);
        self::assertFalse(CoseKey::fromCbor($cred['coseKey'])->verify($authData . hash('sha256', $clientDataRaw, true), $sig));
    }
}
