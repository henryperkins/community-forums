<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/**
 * Foundation F6 - the dev/test-only trust-root + first-registry seed.
 * Production roots stay out-of-band; this proves the seeded rows are usable
 * fixtures: the stored public key verifies harness signatures, the release row
 * carries signed metadata, and no secret material exists in the seeded shape.
 */
final class RegistryFixturesTest extends TestCase
{
    public function test_seed_creates_a_dark_registry_with_verifying_trust_key(): void
    {
        $root = SigningHarness::generate();
        $ids = RegistryFixtures::seed($this->db, $root);

        $registry = $this->db->fetch('SELECT * FROM package_registries WHERE id = ?', [$ids['registry_id']]);
        self::assertNotNull($registry);
        self::assertSame('rb-test', $registry['source_id']);
        self::assertSame(0, (int) $registry['is_enabled'], 'seeded registry must stay dark');

        $key = $this->db->fetch('SELECT * FROM registry_trust_keys WHERE id = ?', [$ids['trust_key_id']]);
        self::assertNotNull($key);
        self::assertSame('ed25519', $key['algorithm']);
        self::assertSame('active', $key['status']);
        self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($key['public_key']), 'public key only: 32 bytes, never the 64-byte secret');

        $snap = $root->mintSnapshot();
        self::assertTrue(SigningHarness::verify($snap['json'], $snap['signature'], $key['public_key']));
        self::assertFalse(SigningHarness::verify(SigningHarness::tamper($snap['json']), $snap['signature'], $key['public_key']));
    }

    public function test_seed_creates_publisher_package_and_signed_approved_release(): void
    {
        $root = SigningHarness::generate();
        $ids = RegistryFixtures::seed($this->db, $root);

        $package = $this->db->fetch('SELECT * FROM packages WHERE id = ?', [$ids['package_id']]);
        self::assertNotNull($package);
        self::assertSame('acme/midnight-theme', $package['package_uid']);
        self::assertSame('theme', $package['type']);
        self::assertSame('reviewed_declarative', $package['trust_class']);

        $release = $this->db->fetch('SELECT * FROM package_releases WHERE id = ?', [$ids['release_id']]);
        self::assertNotNull($release);
        self::assertSame('1.0.0', $release['version']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $release['digest']);
        self::assertSame($root->keyId(), $release['signed_key_id']);
        self::assertSame('approved', $release['review_status']);
        self::assertJson((string) $release['manifest_json']);
        self::assertNotSame('', (string) $release['signature']);
    }
}
