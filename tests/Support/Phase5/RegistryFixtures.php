<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

use App\Core\Database;

/**
 * Foundation F6 - seeds the dev/test-only first registry over the inert 0049
 * tables: one dark registry, one active ed25519 trust key (public bytes only),
 * one publisher, one reviewed declarative theme package, and one signed
 * approved release minted by the given SigningHarness. Rows live inside the
 * caller's test transaction. Production trust roots are an operator ceremony,
 * never seeded by code.
 */
final class RegistryFixtures
{
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    public static function seed(Database $db, SigningHarness $root, ?string $artifactDir = null): array
    {
        $registryId = $db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 0)',
            ['rb-test', 'RB Test Registry', 'https://registry.invalid'],
        );

        $trustKeyId = $db->insert(
            'INSERT INTO registry_trust_keys (registry_id, key_id, algorithm, public_key, status, valid_from) VALUES (?, ?, ?, ?, \'active\', UTC_TIMESTAMP())',
            [$registryId, $root->keyId(), 'ed25519', $root->publicKey()],
        );

        $publisherId = $db->insert(
            'INSERT INTO package_publishers (publisher_uid, display_name, verified_at) VALUES (?, ?, UTC_TIMESTAMP())',
            ['acme', 'Acme Themes'],
        );

        $packageId = $db->insert(
            'INSERT INTO packages (package_uid, registry_id, publisher_id, name, type, trust_class) VALUES (?, ?, ?, ?, ?, ?)',
            ['acme/midnight-theme', $registryId, $publisherId, 'Midnight Theme', 'theme', 'reviewed_declarative'],
        );

        $release = $root->mintRelease();
        $releaseId = $db->insert(
            'INSERT INTO package_releases (package_id, version, digest, source_url, license, core_min, core_max, manifest_json, signature, signed_key_id, review_status, channel, advisory_status, published_at)'
            . ' VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, \'approved\', \'stable\', \'none\', UTC_TIMESTAMP())',
            [
                $packageId,
                $release['version'],
                $release['digest'],
                $release['manifest']['license'] ?? null,
                $release['manifest']['core']['min'] ?? null,
                $release['manifest']['core']['max'] ?? null,
                $release['manifest_json'],
                $release['signature'],
                $release['key_id'],
            ],
        );
        self::writeArtifact($artifactDir, $release['digest'], $release['json']);

        return [
            'registry_id' => (int) $registryId,
            'trust_key_id' => (int) $trustKeyId,
            'publisher_id' => (int) $publisherId,
            'package_id' => (int) $packageId,
            'release_id' => (int) $releaseId,
            'release_digest' => $release['digest'],
            'release_document' => $release['json'],
        ];
    }

    /**
     * Seed one more hydrated, approved release for the seeded package.
     *
     * @param array{package_id:int} $seeded
     * @param array<string,mixed> $overrides
     * @return array{release_id:int,digest:string,document:string,version:string}
     */
    public static function seedRelease(
        Database $db,
        SigningHarness $root,
        array $seeded,
        array $overrides = [],
        ?string $artifactDir = null,
    ): array {
        $release = $root->mintRelease($overrides + ['version' => '1.1.0']);
        $releaseId = $db->insert(
            'INSERT INTO package_releases (package_id, version, digest, source_url, license, core_min, core_max, manifest_json, signature, signed_key_id, review_status, channel, advisory_status, published_at)'
            . ' VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, \'approved\', \'stable\', \'none\', UTC_TIMESTAMP())',
            [
                $seeded['package_id'],
                $release['version'],
                $release['digest'],
                $release['manifest']['license'] ?? null,
                $release['manifest']['core']['min'] ?? null,
                $release['manifest']['core']['max'] ?? null,
                $release['manifest_json'],
                $release['signature'],
                $release['key_id'],
            ],
        );
        $db->run(
            'UPDATE packages SET latest_release_id = ? WHERE id = ?',
            [$releaseId, $seeded['package_id']],
        );
        self::writeArtifact($artifactDir, $release['digest'], $release['json']);

        return [
            'release_id' => (int) $releaseId,
            'digest' => $release['digest'],
            'document' => $release['json'],
            'version' => $release['version'],
        ];
    }

    private static function writeArtifact(?string $artifactDir, string $digest, string $document): void
    {
        if ($artifactDir === null) {
            return;
        }
        if (!is_dir($artifactDir)) {
            mkdir($artifactDir, 0775, true);
        }
        file_put_contents($artifactDir . '/' . $digest . '.json', $document);
    }
}
