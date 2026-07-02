<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

/**
 * Foundation F6 - the signed-artifact harness's own contract. The real
 * verifier is Inc 2 (P5-01 SP2); until then this proves the fixtures are
 * genuine Ed25519 artifacts: mint/verify round-trips, one flipped byte fails,
 * expiry/rotation docs carry the right claims. Real crypto, not mocks.
 */
final class SigningHarnessTest extends TestCase
{
    public function test_snapshot_mints_and_verifies_and_tamper_fails(): void
    {
        $root = SigningHarness::generate();
        $snap = $root->mintSnapshot();

        self::assertSame('test-root-1', $snap['key_id']);
        self::assertTrue(SigningHarness::verify($snap['json'], $snap['signature'], $root->publicKey()));
        self::assertFalse(SigningHarness::verify(SigningHarness::tamper($snap['json']), $snap['signature'], $root->publicKey()));

        $doc = json_decode($snap['json'], true);
        self::assertSame('rb-registry-snapshot.v1', $doc['format']);
        self::assertSame('rb-test', $doc['registry']);
        self::assertGreaterThan(strtotime($doc['generated_at']), strtotime($doc['expires_at']));
    }

    public function test_wrong_key_fails_verification(): void
    {
        $root = SigningHarness::generate();
        $other = SigningHarness::generate('test-root-2');
        $snap = $root->mintSnapshot();

        self::assertNotSame($root->publicKey(), $other->publicKey());
        self::assertFalse(SigningHarness::verify($snap['json'], $snap['signature'], $other->publicKey()));
    }

    public function test_expired_snapshot_carries_past_expiry(): void
    {
        $snap = SigningHarness::generate()->mintExpiredSnapshot();
        $doc = json_decode($snap['json'], true);
        self::assertLessThan(time(), strtotime($doc['expires_at']));
    }

    public function test_release_digest_is_document_sha256_and_doc_verifies(): void
    {
        $root = SigningHarness::generate();
        $rel = $root->mintRelease();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $rel['digest']);
        self::assertSame(hash('sha256', $rel['json']), $rel['digest']);
        self::assertTrue(SigningHarness::verify($rel['json'], $rel['signature'], $root->publicKey()));
        $doc = json_decode($rel['json'], true);
        self::assertSame('rb-release.v1', $doc['format']);
        self::assertSame('acme/midnight-theme', $rel['uid']);
        self::assertSame('1.0.0', $rel['version']);
        self::assertJson($rel['manifest_json']);
    }

    public function test_mint_release_digest_pins_exact_document_bytes(): void
    {
        $root = SigningHarness::generate();
        $release = $root->mintRelease(['version' => '2.0.0']);

        self::assertSame(hash('sha256', $release['json']), $release['digest']);
        self::assertTrue(SigningHarness::verify($release['json'], $release['signature'], $root->publicKey()));

        $doc = json_decode($release['json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('rb-release.v1', $doc['format']);
        self::assertSame('acme/midnight-theme', $doc['uid']);
        self::assertSame('2.0.0', $doc['version']);
        self::assertSame('approved', $doc['review']['status']);
        self::assertSame('rb-manifest.v2', $doc['manifest']['format']);
        self::assertSame($doc['manifest'], $release['manifest']);
        self::assertSame(json_encode($doc['manifest'], JSON_UNESCAPED_SLASHES), $release['manifest_json']);

        // One flipped byte is a different artifact: different digest, failed signature.
        $tampered = SigningHarness::tamper($release['json']);
        self::assertNotSame($release['digest'], hash('sha256', $tampered));
        self::assertFalse(SigningHarness::verify($tampered, $release['signature'], $root->publicKey()));
    }

    public function test_mint_manifest_permission_lists_replace_wholesale(): void
    {
        $root = SigningHarness::generate();
        $manifest = $root->mintManifest(['permissions' => ['data_classes' => []], 'type' => 'automation']);

        self::assertSame([], $manifest['permissions']['data_classes']);
        self::assertSame('automation', $manifest['type']);
        self::assertSame('rb-manifest.v2', $manifest['format']);
    }

    public function test_mint_manifest_theme_block_merges_one_level_deep(): void
    {
        $root = SigningHarness::generate();
        $manifest = $root->mintManifest(['theme' => ['tokens' => ['--accent' => '#112233']]]);

        self::assertSame('#112233', $manifest['theme']['tokens']['--accent']);
        self::assertSame(1, $manifest['theme']['schema_version']);

        $auto = $root->mintManifest(['type' => 'automation', 'theme' => null]);
        self::assertArrayNotHasKey('theme', $auto);
    }

    public function test_mint_release_envelope_wraps_the_exact_document(): void
    {
        $root = SigningHarness::generate();
        $envelope = $root->mintReleaseEnvelope(['review_status' => 'submitted']);

        $body = json_decode($envelope['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('rb-release-envelope.v1', $body['format']);
        self::assertSame($envelope['release']['json'], $body['document']);
        self::assertSame($envelope['release']['signature'], base64_decode($body['signature'], true));
        self::assertSame($root->keyId(), $body['key_id']);
        self::assertSame('submitted', json_decode($body['document'], true)['review']['status']);
    }

    public function test_advisory_and_rotation_docs_verify_with_expected_claims(): void
    {
        $root = SigningHarness::generate();
        $adv = $root->mintAdvisory(['action' => 'revoke']);
        self::assertTrue(SigningHarness::verify($adv['json'], $adv['signature'], $root->publicKey()));
        self::assertSame('revoke', json_decode($adv['json'], true)['action']);

        $next = SigningHarness::generate('test-root-2');
        $rot = $root->mintRotation($next);
        // Signed by the old key; names the new key and its public key.
        self::assertSame('test-root-1', $rot['key_id']);
        self::assertTrue(SigningHarness::verify($rot['json'], $rot['signature'], $root->publicKey()));
        $doc = json_decode($rot['json'], true);
        self::assertSame('rb-key-rotation.v1', $doc['format']);
        self::assertSame('test-root-2', $doc['new_key_id']);
        self::assertSame($next->publicKey(), base64_decode($doc['new_public_key'], true));
    }

    public function test_tamper_changes_exactly_one_byte_and_length_is_preserved(): void
    {
        $bytes = '{"a":1}';
        $tampered = SigningHarness::tamper($bytes);
        self::assertSame(strlen($bytes), strlen($tampered));
        self::assertNotSame($bytes, $tampered);
    }

    public function test_harness_exposes_no_secret_key(): void
    {
        // Public-key-only invariant: the harness API surface must not leak
        // signing material to fixtures or seeded rows.
        $methods = array_map(
            static fn (\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass(SigningHarness::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        foreach ($methods as $name) {
            self::assertDoesNotMatchRegularExpression('/secret|private/i', $name);
        }
        self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen(SigningHarness::generate()->publicKey()));
    }
}
