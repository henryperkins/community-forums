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
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    public static function seed(Database $db, SigningHarness $root): array
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
            'INSERT INTO package_releases (package_id, version, digest, license, core_min, core_max, manifest_json, signature, signed_key_id, review_status, channel, published_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'approved\', \'stable\', UTC_TIMESTAMP())',
            [
                $packageId,
                $release['version'],
                $release['digest'],
                'MIT',
                '0.1.0',
                null,
                $release['manifest_json'],
                $release['signature'],
                $release['key_id'],
            ],
        );

        return [
            'registry_id' => (int) $registryId,
            'trust_key_id' => (int) $trustKeyId,
            'publisher_id' => (int) $publisherId,
            'package_id' => (int) $packageId,
            'release_id' => (int) $releaseId,
        ];
    }
}
