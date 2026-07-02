<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Registry;

use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

/**
 * TM-SC-01 (byte-flip rejected), TM-SC-03 (wrong-key / revoked-key rejected),
 * TM-SC-04 (forged rotation rejected) - pure, no DB.
 */
final class TrustChainVerifierTest extends TestCase
{
    private TrustChainVerifier $verifier;
    private SigningHarness $root;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->verifier = new TrustChainVerifier();
        $this->root = SigningHarness::generate('root-1');
        $this->now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function keyRow(SigningHarness $key, array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'registry_id' => 1,
            'key_id' => $key->keyId(),
            'algorithm' => 'ed25519',
            'public_key' => $key->publicKey(),
            'status' => 'active',
            'valid_from' => null,
            'valid_until' => null,
        ], $overrides);
    }

    private function expectCode(string $code, callable $fn): void
    {
        try {
            $fn();
            self::fail("expected RegistryVerificationException($code)");
        } catch (RegistryVerificationException $e) {
            self::assertSame($code, $e->code);
        }
    }

    public function test_valid_snapshot_verifies_and_decodes(): void
    {
        $snap = $this->root->mintSnapshot();
        $doc = $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            $snap['key_id'],
            [$this->keyRow($this->root)],
            'rb-registry-snapshot.v1',
            $this->now,
        );
        self::assertSame('rb-registry-snapshot.v1', $doc->format);
        self::assertSame('acme/midnight-theme', $doc->payload['packages'][0]['uid']);
        self::assertSame('root-1', $doc->keyId);
    }

    public function test_one_flipped_byte_is_rejected(): void
    {
        $snap = $this->root->mintSnapshot();
        $keys = [$this->keyRow($this->root)];
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            SigningHarness::tamper($snap['json']),
            $snap['signature'],
            'root-1',
            $keys,
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            $snap['json'],
            SigningHarness::tamper($snap['signature']),
            'root-1',
            $keys,
            'rb-registry-snapshot.v1',
            $this->now,
        ));
    }

    public function test_unknown_wrong_and_revoked_keys_are_rejected(): void
    {
        $snap = $this->root->mintSnapshot();
        $stranger = SigningHarness::generate('root-1'); // same id, different key material
        $this->expectCode('unknown_key', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [$this->keyRow($stranger)],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('revoked_key', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [$this->keyRow($this->root, ['status' => 'revoked'])],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
    }

    public function test_key_validity_window_and_algorithm_are_enforced(): void
    {
        $snap = $this->root->mintSnapshot();
        $this->expectCode('key_window', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [$this->keyRow($this->root, ['valid_until' => $this->now->modify('-1 hour')->format('Y-m-d H:i:s')])],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('key_window', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [$this->keyRow($this->root, ['valid_from' => $this->now->modify('+1 hour')->format('Y-m-d H:i:s')])],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('algorithm', fn () => $this->verifier->verify(
            $snap['json'],
            $snap['signature'],
            'root-1',
            [$this->keyRow($this->root, ['algorithm' => 'rsa'])],
            'rb-registry-snapshot.v1',
            $this->now,
        ));
    }

    public function test_rotated_key_still_verifies_within_window_but_cannot_sign_a_rotation(): void
    {
        $snap = $this->root->mintSnapshot();
        $rotatedRow = $this->keyRow($this->root, [
            'status' => 'rotated',
            'valid_until' => $this->now->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $doc = $this->verifier->verify($snap['json'], $snap['signature'], 'root-1', [$rotatedRow], 'rb-registry-snapshot.v1', $this->now);
        self::assertSame('rb-registry-snapshot.v1', $doc->format);

        $rotation = $this->root->mintRotation(SigningHarness::generate('root-2'));
        $this->expectCode('inactive_key', fn () => $this->verifier->verifyRotation(
            $rotation['json'],
            $rotation['signature'],
            'root-1',
            [$rotatedRow],
            $this->now,
        ));
    }

    public function test_malformed_and_wrong_format_documents_are_rejected(): void
    {
        $keys = [$this->keyRow($this->root)];
        $raw = 'not-json';
        $this->expectCode('malformed_document', fn () => $this->verifier->verify(
            $raw,
            $this->root->sign($raw),
            'root-1',
            $keys,
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $adv = $this->root->mintAdvisory();
        $this->expectCode('wrong_format', fn () => $this->verifier->verify(
            $adv['json'],
            $adv['signature'],
            'root-1',
            $keys,
            'rb-registry-snapshot.v1',
            $this->now,
        ));
        $this->expectCode('wrong_format', fn () => $this->verifier->verify(
            $adv['json'],
            $adv['signature'],
            'root-1',
            $keys,
            'rb-not-a-format.v9',
            $this->now,
        ));
    }

    public function test_valid_rotation_yields_the_successor_key_material(): void
    {
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);
        $out = $this->verifier->verifyRotation(
            $rotation['json'],
            $rotation['signature'],
            'root-1',
            [$this->keyRow($this->root)],
            $this->now,
        );
        self::assertSame('root-2', $out['key_id']);
        self::assertSame($successor->publicKey(), $out['public_key']);
    }

    public function test_forged_rotations_are_rejected(): void
    {
        $attacker = SigningHarness::generate('evil-1');
        $successor = SigningHarness::generate('root-2');
        $keys = [$this->keyRow($this->root)];

        // Signed by a key the registry never pinned (TM-SC-04).
        $forged = $attacker->mintRotation($successor);
        $this->expectCode('unknown_key', fn () => $this->verifier->verifyRotation(
            $forged['json'],
            $forged['signature'],
            'evil-1',
            $keys,
            $this->now,
        ));

        // old_key_id in the document does not match the signing key.
        $mismatch = $attacker->mintRotation($successor); // old_key_id = 'evil-1'
        $this->expectCode('rotation_signer_mismatch', fn () => $this->verifier->verifyRotation(
            $mismatch['json'],
            $this->root->sign($mismatch['json']),
            'root-1',
            $keys,
            $this->now,
        ));

        // Garbage successor key material.
        $badMaterial = $this->root->mintRotation($successor);
        $payload = json_decode($badMaterial['json'], true);
        $payload['new_public_key'] = base64_encode('short');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->expectCode('rotation_key_material', fn () => $this->verifier->verifyRotation(
            (string) $json,
            $this->root->sign((string) $json),
            'root-1',
            $keys,
            $this->now,
        ));

        // A rotation must name a different successor id.
        $selfRotation = $this->root->mintRotation(SigningHarness::generate('root-1'));
        $this->expectCode('rotation_key_id', fn () => $this->verifier->verifyRotation(
            $selfRotation['json'],
            $selfRotation['signature'],
            'root-1',
            $keys,
            $this->now,
        ));
    }
}
