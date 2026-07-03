<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Security\WebAuthn\CoseKey;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnException;
use App\Security\WebAuthn\WebAuthnVerifier;
use App\Support\Base64Url;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\WebAuthnHarness;

final class WebAuthnPolicyTest extends TestCase
{
    public function test_origin_is_normalized_and_rp_id_defaults_to_the_full_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com:443/', null, 'production');
        self::assertSame('https://forum.example.com', $rp->origin());
        self::assertSame('forum.example.com', $rp->rpId());
        self::assertSame(hash('sha256', 'forum.example.com', true), $rp->rpIdHash());

        $dev = new RelyingParty('http://localhost:8000', null, 'local');
        self::assertSame('http://localhost:8000', $dev->origin());
        self::assertSame('localhost', $dev->rpId());
    }

    public function test_rp_id_override_must_be_a_registrable_suffix_of_the_host(): void
    {
        $rp = new RelyingParty('https://forum.example.com', 'example.com', 'production');
        self::assertSame('example.com', $rp->rpId());

        try {
            new RelyingParty('https://forum.example.com', 'other.com', 'production');
            self::fail('Non-suffix override must refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('invalid_rp_id', $e->code);
        }

        $this->expectException(WebAuthnException::class);
        new RelyingParty('https://forum.example.com', 'ple.com', 'production');
    }

    public function test_production_over_plain_http_hard_refuses_ceremonies(): void
    {
        $rp = new RelyingParty('http://forum.example.com', null, 'production');
        try {
            $rp->assertUsable();
            self::fail('Insecure production origin must hard-refuse');
        } catch (WebAuthnException $e) {
            self::assertSame('insecure_origin', $e->code);
        }

        (new RelyingParty('https://forum.example.com', null, 'production'))->assertUsable();
        (new RelyingParty('http://localhost:8000', null, 'production'))->assertUsable();
        (new RelyingParty('http://forum.example.com', null, 'testing'))->assertUsable();
        $this->addToAssertionCount(3);
    }

    public function test_unusable_app_url_refuses(): void
    {
        $this->expectException(WebAuthnException::class);
        new RelyingParty('not-a-url', null, 'production');
    }

    private function verifier(): WebAuthnVerifier
    {
        return new WebAuthnVerifier(new RelyingParty('http://localhost:8000', null, 'testing'));
    }

    public function test_registration_happy_path_returns_the_storable_credential(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $r = $this->verifier()->verifyRegistration(json_decode($h->registrationPayload($cred, $challenge), true), $challenge);
        self::assertSame($cred['credentialId'], $r->credentialId);
        self::assertSame($cred['coseKey'], $r->publicKey);
        self::assertSame(0, $r->signCount);
        self::assertSame(str_repeat("\xAA", 16), $r->aaguid);
        self::assertSame('internal', $r->transports);
        self::assertTrue($r->userVerified);
    }

    public function test_registration_rejects_wrong_origin_wrong_rp_wrong_type_and_stale_challenge(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $cases = [
            ['overrides' => ['origin' => 'https://evil.test'], 'code' => 'origin_mismatch'],
            ['overrides' => ['rpId' => 'evil.test'], 'code' => 'rp_id_mismatch'],
            ['overrides' => ['type' => 'webauthn.get'], 'code' => 'wrong_ceremony_type'],
            ['overrides' => ['flags' => 0x40], 'code' => 'user_presence_required'],
        ];
        foreach ($cases as $case) {
            try {
                $this->verifier()->verifyRegistration(
                    json_decode($h->registrationPayload($cred, $challenge, $case['overrides']), true),
                    $challenge,
                );
                self::fail('Expected refusal: ' . $case['code']);
            } catch (WebAuthnException $e) {
                self::assertSame($case['code'], $e->code);
            }
        }

        try {
            $this->verifier()->verifyRegistration(
                json_decode($h->registrationPayload($cred, random_bytes(32)), true),
                $challenge,
            );
            self::fail('Expected challenge_mismatch');
        } catch (WebAuthnException $e) {
            self::assertSame('challenge_mismatch', $e->code);
        }
    }

    public function test_registration_rejects_raw_id_spoofing(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $payload = json_decode($h->registrationPayload($cred, $challenge), true);
        $payload['rawId'] = Base64Url::encode(random_bytes(32));
        try {
            $this->verifier()->verifyRegistration($payload, $challenge);
            self::fail('Expected credential_mismatch');
        } catch (WebAuthnException $e) {
            self::assertSame('credential_mismatch', $e->code);
        }
    }

    public function test_assertion_happy_path_counter_anomaly_and_signature_tamper(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $v = $this->verifier();

        $ok = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 6), true), $challenge, $cred['coseKey'], 5, false);
        self::assertTrue($ok->userVerified);
        self::assertSame(6, $ok->signCount);
        self::assertFalse($ok->counterAnomaly);

        $anomaly = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 5), true), $challenge, $cred['coseKey'], 5, false);
        self::assertTrue($anomaly->counterAnomaly);

        $zero = $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 0), true), $challenge, $cred['coseKey'], 0, false);
        self::assertFalse($zero->counterAnomaly);

        try {
            $v->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge, 7, ['tamperSignature' => true]), true), $challenge, $cred['coseKey'], 5, false);
            self::fail('Expected bad_signature');
        } catch (WebAuthnException $e) {
            self::assertSame('bad_signature', $e->code);
        }
    }

    public function test_assertion_enforces_uv_when_required_and_rejects_cross_key_signatures(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->createCredential();
        $challenge = random_bytes(32);
        $v = $this->verifier();

        try {
            $v->verifyAssertion(
                json_decode($h->assertionPayload($cred, $challenge, 3, ['flags' => 0x01]), true),
                $challenge,
                $cred['coseKey'],
                1,
                true,
            );
            self::fail('Expected uv_required');
        } catch (WebAuthnException $e) {
            self::assertSame('uv_required', $e->code);
        }

        $other = $h->createCredential();
        try {
            $v->verifyAssertion(json_decode($h->assertionPayload($other, $challenge, 3), true), $challenge, $cred['coseKey'], 1, false);
            self::fail('Expected bad_signature');
        } catch (WebAuthnException $e) {
            self::assertSame('bad_signature', $e->code);
        }
    }

    public function test_rs256_assertion_verifies_end_to_end(): void
    {
        $h = new WebAuthnHarness();
        $cred = $h->rs256Credential();
        $challenge = random_bytes(32);
        $reg = $this->verifier()->verifyRegistration(json_decode($h->registrationPayload($cred, $challenge), true), $challenge);
        $challenge2 = random_bytes(32);
        $ok = $this->verifier()->verifyAssertion(json_decode($h->assertionPayload($cred, $challenge2, 1), true), $challenge2, $reg->publicKey, 0, false);
        self::assertSame(1, $ok->signCount);
        self::assertSame(CoseKey::ALG_RS256, CoseKey::fromCbor($reg->publicKey)->alg);
    }
}
