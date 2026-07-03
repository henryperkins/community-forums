<?php

declare(strict_types=1);

namespace App\Security\WebAuthn;

use App\Support\Base64Url;

/**
 * Stateless WebAuthn ceremony verifier.
 */
final class WebAuthnVerifier
{
    public function __construct(private readonly RelyingParty $rp)
    {
    }

    /** @param array<string,mixed> $credential */
    public function verifyRegistration(array $credential, string $expectedChallenge): RegisteredCredential
    {
        $this->rp->assertUsable();
        $response = $credential['response'] ?? null;
        if (($credential['type'] ?? '') !== 'public-key' || !is_array($response)) {
            throw new WebAuthnException('malformed_credential', 'Payload is not a public-key credential.');
        }

        $this->clientData((string) ($response['clientDataJSON'] ?? ''), 'webauthn.create', $expectedChallenge);

        $attBytes = Base64Url::decode((string) ($response['attestationObject'] ?? ''));
        if ($attBytes === null || $attBytes === '') {
            throw new WebAuthnException('malformed_credential', 'attestationObject is not valid base64url.');
        }

        $attObj = CborDecoder::decode($attBytes);
        if (!is_array($attObj) || !is_string($attObj['fmt'] ?? null) || !is_array($attObj['attStmt'] ?? null) || !is_string($attObj['authData'] ?? null)) {
            throw new WebAuthnException('malformed_credential', 'attestationObject shape is invalid.');
        }

        $auth = AuthenticatorData::parse($attObj['authData']);
        $this->checkAuthenticatorData($auth);
        if ($auth->credentialId === null || $auth->credentialPublicKey === null) {
            throw new WebAuthnException('malformed_credential', 'Attested credential data missing from registration.');
        }

        $rawId = Base64Url::decode((string) ($credential['rawId'] ?? ''));
        if ($rawId === null || $rawId === '' || !hash_equals($auth->credentialId, $rawId)) {
            throw new WebAuthnException('credential_mismatch', 'rawId does not match the attested credential id.');
        }

        CoseKey::fromCbor($auth->credentialPublicKey);

        $transports = '';
        if (is_array($credential['transports'] ?? null)) {
            $clean = array_values(array_filter(
                $credential['transports'],
                static fn (mixed $transport): bool => is_string($transport) && preg_match('/^[a-z-]{1,20}$/', $transport) === 1,
            ));
            $transports = substr(implode(',', array_slice($clean, 0, 8)), 0, 190);
        }

        return new RegisteredCredential(
            credentialId: $auth->credentialId,
            publicKey: $auth->credentialPublicKey,
            signCount: $auth->signCount,
            aaguid: $auth->aaguid,
            transports: $transports,
            userVerified: $auth->userVerified(),
            backupEligible: $auth->backupEligible(),
            backedUp: $auth->backedUp(),
        );
    }

    /** @param array<string,mixed> $credential */
    public function verifyAssertion(array $credential, string $expectedChallenge, string $publicKeyCbor, int $storedSignCount, bool $requireUv): AssertionResult
    {
        $this->rp->assertUsable();
        $response = $credential['response'] ?? null;
        if (($credential['type'] ?? '') !== 'public-key' || !is_array($response)) {
            throw new WebAuthnException('malformed_credential', 'Payload is not a public-key credential.');
        }

        $clientDataRaw = $this->clientData((string) ($response['clientDataJSON'] ?? ''), 'webauthn.get', $expectedChallenge);
        $authBytes = Base64Url::decode((string) ($response['authenticatorData'] ?? ''));
        $signature = Base64Url::decode((string) ($response['signature'] ?? ''));
        if ($authBytes === null || $authBytes === '' || $signature === null || $signature === '') {
            throw new WebAuthnException('malformed_credential', 'authenticatorData/signature are not valid base64url.');
        }

        $auth = AuthenticatorData::parse($authBytes);
        $this->checkAuthenticatorData($auth);
        if ($requireUv && !$auth->userVerified()) {
            throw new WebAuthnException('uv_required', 'This action needs a passkey with user verification.');
        }

        $key = CoseKey::fromCbor($publicKeyCbor);
        if (!$key->verify($authBytes . hash('sha256', $clientDataRaw, true), $signature)) {
            throw new WebAuthnException('bad_signature', 'Assertion signature does not verify.');
        }

        $anomaly = $auth->signCount !== 0 && $storedSignCount !== 0 && $auth->signCount <= $storedSignCount;

        return new AssertionResult(
            userVerified: $auth->userVerified(),
            signCount: $auth->signCount,
            counterAnomaly: $anomaly,
        );
    }

    private function clientData(string $b64u, string $expectedType, string $expectedChallenge): string
    {
        $raw = Base64Url::decode($b64u);
        if ($raw === null || $raw === '') {
            throw new WebAuthnException('malformed_client_data', 'clientDataJSON is not valid base64url.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new WebAuthnException('malformed_client_data', 'clientDataJSON is not valid JSON.');
        }
        if (($data['type'] ?? '') !== $expectedType) {
            throw new WebAuthnException('wrong_ceremony_type', 'clientData type mismatch.');
        }

        $challenge = Base64Url::decode((string) ($data['challenge'] ?? ''));
        if ($challenge === null || $challenge === '' || !hash_equals($expectedChallenge, $challenge)) {
            throw new WebAuthnException('challenge_mismatch', 'clientData challenge does not match the issued challenge.');
        }
        if (($data['origin'] ?? '') !== $this->rp->origin()) {
            throw new WebAuthnException('origin_mismatch', 'clientData origin does not match the canonical APP_URL origin.');
        }

        return $raw;
    }

    private function checkAuthenticatorData(AuthenticatorData $auth): void
    {
        if (!hash_equals($this->rp->rpIdHash(), $auth->rpIdHash)) {
            throw new WebAuthnException('rp_id_mismatch', 'rpIdHash does not match the configured RP ID.');
        }
        if (!$auth->userPresent()) {
            throw new WebAuthnException('user_presence_required', 'User-presence flag missing from authenticator data.');
        }
    }
}
