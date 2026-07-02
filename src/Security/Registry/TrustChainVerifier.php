<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * Pure Ed25519 trust-chain verifier for rb-*.v1 signed documents (P5-01 SP2).
 *
 * PUBLIC-KEY-ONLY: candidate keys are registry_trust_keys rows (public bytes);
 * private material never reaches this class. Every failure is a coded
 * RegistryVerificationException, so callers fail closed.
 */
final class TrustChainVerifier
{
    /** Tolerated clock skew for future-dated documents checked by callers. */
    public const CLOCK_SKEW_SECONDS = 300;

    private const FORMATS = [
        'rb-registry-snapshot.v1',
        'rb-release.v1',
        'rb-advisory.v1',
        'rb-key-rotation.v1',
    ];

    /**
     * @param list<array<string,mixed>> $trustKeys registry_trust_keys rows for one registry
     */
    public function verify(
        string $documentJson,
        string $signature,
        string $keyId,
        array $trustKeys,
        string $expectedFormat,
        \DateTimeImmutable $now,
    ): VerifiedDocument {
        if (!in_array($expectedFormat, self::FORMATS, true)) {
            throw new RegistryVerificationException('wrong_format', 'Unsupported document format: ' . $expectedFormat . '.');
        }

        $key = $this->selectKey($keyId, $trustKeys, $now, requireActive: false);

        $valid = false;
        try {
            $valid = sodium_crypto_sign_verify_detached($signature, $documentJson, (string) $key['public_key']);
        } catch (\SodiumException) {
            $valid = false;
        }
        if (!$valid) {
            throw new RegistryVerificationException('bad_signature', 'Detached signature does not verify for key ' . $keyId . '.');
        }

        $payload = json_decode($documentJson, true);
        if (!is_array($payload)) {
            throw new RegistryVerificationException('malformed_document', 'Signed document is not a JSON object.');
        }
        if (($payload['format'] ?? null) !== $expectedFormat) {
            throw new RegistryVerificationException(
                'wrong_format',
                'Expected ' . $expectedFormat . ', got ' . (string) ($payload['format'] ?? '(none)') . '.',
            );
        }

        return new VerifiedDocument($expectedFormat, $payload, $keyId);
    }

    /**
     * A rotation transition must be signed by a currently active key: rotated
     * or revoked keys cannot introduce a successor, and the successor cannot
     * introduce itself.
     *
     * @param list<array<string,mixed>> $trustKeys
     * @return array{key_id:string,public_key:string}
     */
    public function verifyRotation(
        string $documentJson,
        string $signature,
        string $keyId,
        array $trustKeys,
        \DateTimeImmutable $now,
    ): array {
        $this->selectKey($keyId, $trustKeys, $now, requireActive: true);
        $doc = $this->verify($documentJson, $signature, $keyId, $trustKeys, 'rb-key-rotation.v1', $now);

        if ((string) ($doc->payload['old_key_id'] ?? '') !== $keyId) {
            throw new RegistryVerificationException('rotation_signer_mismatch', 'Rotation old_key_id must equal the signing key id.');
        }

        $newKeyId = trim((string) ($doc->payload['new_key_id'] ?? ''));
        if ($newKeyId === '' || $newKeyId === $keyId) {
            throw new RegistryVerificationException('rotation_key_id', 'Rotation must name a distinct new_key_id.');
        }

        $material = base64_decode((string) ($doc->payload['new_public_key'] ?? ''), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RegistryVerificationException('rotation_key_material', 'new_public_key must be a base64 32-byte Ed25519 public key.');
        }

        return ['key_id' => $newKeyId, 'public_key' => $material];
    }

    /**
     * @param list<array<string,mixed>> $trustKeys
     * @return array<string,mixed>
     */
    private function selectKey(string $keyId, array $trustKeys, \DateTimeImmutable $now, bool $requireActive): array
    {
        $key = null;
        foreach ($trustKeys as $row) {
            if ((string) $row['key_id'] === $keyId) {
                $key = $row;
                break;
            }
        }
        if ($key === null) {
            throw new RegistryVerificationException('unknown_key', 'No pinned trust key with id ' . $keyId . '.');
        }

        if ((string) $key['algorithm'] !== 'ed25519') {
            throw new RegistryVerificationException('algorithm', 'Only ed25519 trust keys are accepted.');
        }

        $status = (string) $key['status'];
        if ($status === 'revoked') {
            throw new RegistryVerificationException('revoked_key', 'Trust key ' . $keyId . ' is revoked.');
        }
        if ($requireActive && $status !== 'active') {
            throw new RegistryVerificationException('inactive_key', 'A rotation must be signed by a currently active key.');
        }
        if (!in_array($status, ['active', 'rotated'], true)) {
            throw new RegistryVerificationException('inactive_key', 'Trust key ' . $keyId . ' is not usable.');
        }

        $from = $key['valid_from'] ?? null;
        if ($from !== null && $now < new \DateTimeImmutable((string) $from, new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('key_window', 'Trust key ' . $keyId . ' is not yet valid.');
        }

        $until = $key['valid_until'] ?? null;
        if ($until !== null && $now > new \DateTimeImmutable((string) $until, new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('key_window', 'Trust key ' . $keyId . ' is outside its validity window.');
        }

        return $key;
    }
}
