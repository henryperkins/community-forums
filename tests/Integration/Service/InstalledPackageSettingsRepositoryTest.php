<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class InstalledPackageSettingsRepositoryTest extends TestCase
{
    public function test_upsert_inserts_then_updates_in_place_on_conflict(): void
    {
        $repo = new InstalledPackageSettingsRepository($this->db);
        $install = $this->seedInstall();

        $id = $repo->upsert($install, 'greeting', '"hello"', null, false, null);
        self::assertGreaterThan(0, $id);

        $again = $repo->upsert($install, 'greeting', '"world"', null, false, null);
        self::assertSame($id, $again, 'unique key collision must update the same row, not insert');

        $row = $repo->find($install, 'greeting');
        self::assertSame('"world"', $row['value_json']);
        self::assertSame(0, (int) $row['is_secret']);
        self::assertNull($row['secret_ref']);
        self::assertCount(1, $repo->forInstall($install));
    }

    public function test_secret_refs_harvest_only_secret_rows(): void
    {
        $repo = new InstalledPackageSettingsRepository($this->db);
        $install = $this->seedInstall();

        $repo->upsert($install, 'api_base', '"https://x.test"', null, false, null);
        $repo->upsert($install, 'api_key', null, 'svcsec_' . str_repeat('a', 12), true, null);

        self::assertSame(['svcsec_' . str_repeat('a', 12)], $repo->secretRefsFor($install));
    }

    public function test_delete_for_clears_all_rows(): void
    {
        $repo = new InstalledPackageSettingsRepository($this->db);
        $install = $this->seedInstall();
        $repo->upsert($install, 'a', '"1"', null, false, null);
        $repo->upsert($install, 'b', '"2"', null, false, null);

        $repo->deleteFor($install);
        self::assertSame([], $repo->forInstall($install));
    }

    private function seedInstall(): int
    {
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());

        return (new InstalledPackageRepository($this->db))->create([
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
    }
}
