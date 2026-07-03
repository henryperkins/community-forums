<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackageRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class InstalledPackageCredentialRepositoryTest extends TestCase
{
    public function test_api_token_link_round_trips_and_revoke_is_idempotent(): void
    {
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate(), null, ['type' => 'remote_app']);
        $adminId = (int) $this->makeAdmin()['id'];

        $installedId = (new InstalledPackageRepository($this->db))->create([
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
            'digest' => $seeded['release_digest'],
            'source_registry_id' => $seeded['registry_id'],
            'publisher_id' => $seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => null,
            'compat_max' => null,
            'installed_by' => $adminId,
        ]);
        $tokenId = (new ApiTokenRepository($this->db))
            ->insert('seed', hash('sha256', 'seed'), '["read:boards"]', $adminId, null);

        $repo = new InstalledPackageCredentialRepository($this->db);
        $linkId = $repo->insertApiToken($installedId, $tokenId, 'pkg:acme/remote-app#' . $installedId, '["read:boards"]', $adminId);

        $row = $repo->find($linkId);
        self::assertNotNull($row);
        self::assertSame('api_token', (string) $row['kind']);
        self::assertSame($tokenId, (int) $row['api_token_id']);
        self::assertNull($row['webhook_id']);
        self::assertSame($linkId, (int) $repo->findByApiToken($tokenId)['id']);
        self::assertCount(1, $repo->activeForInstall($installedId));

        self::assertSame(1, $repo->markRevoked($linkId), 'first revoke flips the row');
        self::assertSame(0, $repo->markRevoked($linkId), 'second revoke is a no-op');
        self::assertCount(0, $repo->activeForInstall($installedId));
        self::assertCount(1, $repo->forInstall($installedId), 'revoked links stay visible');
    }
}
