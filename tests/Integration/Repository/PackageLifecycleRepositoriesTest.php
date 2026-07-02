<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageLifecycleRepositoriesTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $seeded;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
    }

    private function makeInstall(): int
    {
        return (new InstalledPackageRepository($this->db))->create([
            'package_id' => $this->seeded['package_id'],
            'release_id' => $this->seeded['release_id'],
            'digest' => $this->seeded['release_digest'],
            'source_registry_id' => $this->seeded['registry_id'],
            'publisher_id' => $this->seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => $this->makeAdmin()['id'],
        ]);
    }

    public function test_install_row_lifecycle_round_trip(): void
    {
        $repo = new InstalledPackageRepository($this->db);
        $id = $this->makeInstall();

        $row = $repo->find($id);
        self::assertSame('installed', $row['state']);
        self::assertSame(0, (int) $row['pinned']);
        self::assertSame('manual', $row['update_policy']);
        self::assertSame($row['id'], $repo->findByPackage($this->seeded['package_id'])['id']);

        $repo->setState($id, 'enabled');
        $repo->setPinned($id, true);
        $repo->setUpdatePolicy($id, 'notify');
        $repo->stageRelease($id, $this->seeded['release_id'], $this->seeded['release_digest']);
        $row = $repo->find($id);
        self::assertSame(['enabled', 1, 'notify'], [$row['state'], (int) $row['pinned'], $row['update_policy']]);
        self::assertSame($this->seeded['release_digest'], $row['staged_digest']);
        $repo->stageRelease($id, null, null);
        self::assertNull($repo->find($id)['staged_release_id']);

        $repo->setHealth($id, 'failed', 'digest mismatch');
        $row = $repo->find($id);
        self::assertSame('failed', $row['health']);
        self::assertSame('digest mismatch', $row['quarantine_reason']);
        self::assertNotNull($row['last_health_check_at']);

        $context = $repo->activeWithContext();
        self::assertCount(1, $context);
        self::assertSame('acme/midnight-theme', $context[0]['package_uid']);

        $repo->storeExport($id, '{"format":"rb-install-export.v1"}');
        $repo->markUninstalled($id, '2026-01-01 00:00:00');
        $row = $repo->find($id);
        self::assertSame('uninstalled', $row['state']);
        self::assertNotNull($row['uninstalled_at']);
        self::assertSame([], $repo->activeWithContext());
        self::assertCount(1, $repo->purgeable('2026-06-01 00:00:00'));
        self::assertSame([], $repo->purgeable('2025-06-01 00:00:00'));

        $repo->reviveForInstall($id, [
            'release_id' => $this->seeded['release_id'],
            'digest' => $this->seeded['release_digest'],
            'source_registry_id' => $this->seeded['registry_id'],
            'publisher_id' => $this->seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => null,
        ]);
        $row = $repo->find($id);
        self::assertSame('installed', $row['state']);
        self::assertNull($row['export_json']);
        self::assertNull($row['retain_until']);
        self::assertSame(0, (int) $row['pinned']);

        $repo->delete($id);
        self::assertNull($repo->find($id));
    }

    public function test_permission_snapshot_grant_flow(): void
    {
        $perms = new InstalledPackagePermissionRepository($this->db);
        $id = $this->makeInstall();
        $admin = $this->makeAdmin();

        $declared = [
            ['kind' => 'data_class', 'key' => 'package.own_storage', 'risk' => 'low'],
            ['kind' => 'api_scope', 'key' => 'read:boards', 'risk' => 'medium'],
        ];
        $perms->replaceDeclared($id, $declared);
        self::assertSame(2, $perms->ungrantedCount($id));
        $rows = $perms->forInstall($id);
        self::assertCount(2, $rows);
        self::assertSame(0, (int) $rows[0]['granted']);

        self::assertSame(2, $perms->grantAll($id, (int) $admin['id']));
        self::assertSame(0, $perms->ungrantedCount($id));
        self::assertSame(0, $perms->grantAll($id, (int) $admin['id']), 'idempotent');

        $perms->replaceWithGrants($id, [
            ['kind' => 'data_class', 'key' => 'package.own_storage', 'risk' => 'low', 'granted' => true],
            ['kind' => 'outbound_host', 'key' => 'api.example.com', 'risk' => 'medium', 'granted' => false],
        ], (int) $admin['id']);
        $byKey = [];
        foreach ($perms->forInstall($id) as $row) {
            $byKey[$row['permission_key']] = $row;
        }
        self::assertCount(2, $byKey);
        self::assertSame(1, (int) $byKey['package.own_storage']['granted']);
        self::assertNotNull($byKey['package.own_storage']['granted_at']);
        self::assertSame(0, (int) $byKey['api.example.com']['granted']);
        self::assertNull($byKey['api.example.com']['granted_at']);

        $perms->deleteFor($id);
        self::assertSame([], $perms->forInstall($id));
    }

    public function test_history_records_and_verified_digest_set(): void
    {
        $history = new PackageHistoryRepository($this->db);
        $installId = $this->makeInstall();
        $packageId = (int) $this->seeded['package_id'];

        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'install',
            'new_version' => '1.0.0', 'new_digest' => str_repeat('a', 64),
            'permission_snapshot_json' => '[]',
        ]);
        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'update',
            'prior_version' => '1.0.0', 'prior_digest' => str_repeat('a', 64),
            'new_version' => '1.1.0', 'new_digest' => str_repeat('b', 64),
        ]);
        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'health',
            'failure_stage' => 'digest', 'detail' => 'tamper detected',
        ]);

        self::assertCount(3, $history->forPackage($packageId));
        self::assertSame('health', $history->forInstall($installId, 1)[0]['event']);
        $digests = $history->verifiedDigestsFor($packageId);
        sort($digests);
        self::assertSame([str_repeat('a', 64), str_repeat('b', 64)], $digests);
    }

    public function test_review_decisions_and_transparency_log(): void
    {
        $decisions = new PackageReviewDecisionRepository($this->db);
        $log = new PackageTransparencyLogRepository($this->db);
        $packageId = (int) $this->seeded['package_id'];

        $decisions->record([
            'package_id' => $packageId, 'release_id' => $this->seeded['release_id'], 'version' => '1.0.0',
            'digest' => $this->seeded['release_digest'], 'decision' => 'approved',
            'decided_at' => '2026-07-01 00:00:00', 'source' => 'release_document', 'evidence_json' => '{}',
        ]);
        $latest = $decisions->latestForDigest($packageId, $this->seeded['release_digest']);
        self::assertSame('approved', $latest['decision']);
        self::assertNull($decisions->latestForDigest($packageId, str_repeat('f', 64)));
        self::assertCount(1, $decisions->forPackage($packageId));

        $log->record([
            'package_uid' => 'acme/midnight-theme', 'version' => '1.0.0',
            'digest' => $this->seeded['release_digest'], 'event' => 'install', 'source' => 'local',
            'actor_id' => null, 'registry_id' => $this->seeded['registry_id'], 'detail' => 'installed 1.0.0',
        ]);
        self::assertCount(1, $log->forPackageUid('acme/midnight-theme'));
        self::assertCount(1, $log->all());
    }

    public function test_transparency_log_repository_is_append_only(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(PackageTransparencyLogRepository::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        sort($methods);
        self::assertSame(['__construct', 'all', 'forPackageUid', 'record'], $methods);
    }
}
