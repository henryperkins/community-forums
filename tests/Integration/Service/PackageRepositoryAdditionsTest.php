<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackagePublisherRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageRepositoryAdditionsTest extends TestCase
{
    public function test_set_settings_summary_writes_and_clears_json(): void
    {
        $installs = new InstalledPackageRepository($this->db);
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
        $id = $installs->create([
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
            'digest' => $seeded['release_digest'],
            'source_registry_id' => $seeded['registry_id'],
            'publisher_id' => $seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => null,
        ]);

        $installs->setSettingsSummary($id, '{"greeting":"hi"}');
        self::assertSame('{"greeting":"hi"}', $installs->find($id)['settings_json']);

        $installs->setSettingsSummary($id, null);
        self::assertNull($installs->find($id)['settings_json']);
    }

    public function test_publisher_accessors_read_status_and_owned_packages(): void
    {
        $publishers = new PackagePublisherRepository($this->db);
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
        $publisherId = $seeded['publisher_id'];

        self::assertSame($publisherId, (int) $publishers->find($publisherId)['id']);
        self::assertNotSame([], $publishers->all());

        $publishers->markVerified($publisherId, null);
        self::assertNotNull($publishers->find($publisherId)['verified_at']);

        $publishers->setStatus($publisherId, 'suspended');
        self::assertSame('suspended', $publishers->find($publisherId)['status']);

        $owned = $publishers->packagesFor($publisherId);
        self::assertContains($seeded['package_id'], array_map(static fn (array $r): int => (int) $r['id'], $owned));
    }
}
