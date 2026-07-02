<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\UserRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageUpdateServiceTest extends TestCase
{
    private SigningHarness $root;

    /** @var array<string,mixed> */
    private array $seeded;

    private string $artifactDir;

    private PackageArtifactStore $store;

    private User $admin;

    private int $installedId;

    /** @var array<string,mixed> */
    private array $expanded;

    /** @var array<string,mixed> */
    private array $reduced;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-update-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);

        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $this->admin = $admin;

        $this->installedId = $this->lifecycle()->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $this->lifecycle()->consent($this->admin, 'password123', $this->installedId);
        $this->lifecycle()->enable($this->admin, 'password123', $this->installedId);

        $this->expanded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'api_scopes' => ['read:boards'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->reduced = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.2.0',
            'manifest' => ['permissions' => ['data_classes' => []]],
        ], $this->artifactDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    private function lifecycle(): PackageLifecycleService
    {
        return new PackageLifecycleService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageRegistryRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            $this->acquisition(),
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            30,
        );
    }

    private function updates(): PackageUpdateService
    {
        return new PackageUpdateService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageRegistryRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->acquisition(),
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function acquisition(): PackageAcquisitionService
    {
        return new PackageAcquisitionService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ManifestValidator(),
            new ArrayRegistryTransport([]),
        );
    }

    private function assertPolicyRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    /** @return array<string,int> */
    private function grants(): array
    {
        $out = [];
        foreach ((new InstalledPackagePermissionRepository($this->db))->forInstall($this->installedId) as $row) {
            $out[$row['kind'] . ':' . $row['permission_key']] = (int) $row['granted'];
        }

        return $out;
    }

    public function test_update_plan_reports_the_permission_diff(): void
    {
        $plan = $this->updates()->updatePlan($this->admin, $this->installedId, (int) $this->expanded['release_id']);

        self::assertNull($plan['refusal']);
        self::assertTrue($plan['requires_consent']);
        $addedKeys = array_map(static fn (array $permission): string => $permission['kind'] . ':' . $permission['key'], $plan['diff']['added']);
        sort($addedKeys);
        self::assertSame(['api_scope:read:boards', 'outbound_host:api.example.com'], $addedKeys);
        self::assertSame([], $plan['diff']['removed']);
    }

    public function test_permission_increase_stages_and_the_old_version_keeps_its_old_grant_only(): void
    {
        $result = $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);

        self::assertSame('staged', $result['status']);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest'], 'old version stays active');
        self::assertSame($this->expanded['digest'], $row['staged_digest']);
        self::assertSame('enabled', $row['state'], 'staging never touches execution eligibility');
        self::assertSame(['data_class:package.own_storage' => 1], $this->grants(), 'grant snapshot untouched while staged');
        self::assertSame('update_staged', (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0]['event']);
    }

    public function test_apply_staged_swaps_release_and_commits_the_consented_set(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);
        $updates->applyStaged($this->admin, 'password123', $this->installedId);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame((int) $this->expanded['release_id'], (int) $row['release_id']);
        self::assertSame($this->expanded['digest'], $row['digest']);
        self::assertNull($row['staged_release_id']);
        self::assertSame([
            'api_scope:read:boards' => 1,
            'data_class:package.own_storage' => 1,
            'outbound_host:api.example.com' => 1,
        ], $this->grants(), 'staged approval is the consent for the full new set');

        $history = (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0];
        self::assertSame('update', $history['event']);
        self::assertSame('1.0.0', $history['prior_version']);
        self::assertSame('1.1.0', $history['new_version']);
    }

    public function test_permission_reduction_applies_immediately_and_preserves_inherited_grants(): void
    {
        $result = $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']);

        self::assertSame('applied', $result['status']);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame((int) $this->reduced['release_id'], (int) $row['release_id']);
        self::assertSame([], $this->grants(), 'reduction takes effect at activation');
    }

    public function test_pinned_install_refuses_update_but_unpin_restores_it(): void
    {
        $this->lifecycle()->setPinned($this->admin, $this->installedId, true);
        $this->assertPolicyRefusal(
            'pinned',
            fn () => $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']),
        );

        $this->lifecycle()->setPinned($this->admin, $this->installedId, false);
        self::assertSame(
            'applied',
            $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id'])['status'],
        );
        $events = array_column((new PackageHistoryRepository($this->db))->forInstall($this->installedId), 'event');
        self::assertContains('pin', $events);
        self::assertContains('unpin', $events);
    }

    public function test_same_release_and_pending_stage_refuse(): void
    {
        $updates = $this->updates();
        $this->assertPolicyRefusal(
            'same_release',
            fn () => $updates->update($this->admin, 'password123', $this->installedId, (int) $this->seeded['release_id']),
        );

        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);
        $this->assertPolicyRefusal(
            'stage_pending',
            fn () => $updates->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']),
        );

        $updates->cancelStaged($this->admin, $this->installedId);
        self::assertNull((new InstalledPackageRepository($this->db))->find($this->installedId)['staged_release_id']);
        $this->assertPolicyRefusal('no_staged_update', fn () => $updates->applyStaged($this->admin, 'password123', $this->installedId));
    }

    public function test_staged_target_revoked_before_apply_fails_closed(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);

        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REVOKE-STAGED',
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => null,
            'affected_digest' => $this->expanded['digest'],
            'severity' => 'critical',
            'action' => 'revoke',
            'summary' => 'compromised',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $this->assertPolicyRefusal('advisory_revoked', fn () => $updates->applyStaged($this->admin, 'password123', $this->installedId));
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest'], 'prior version remains active');
    }

    public function test_rollback_targets_and_rollback_after_an_update(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']);

        $targets = $updates->rollbackTargets($this->installedId);
        self::assertSame([$this->seeded['release_digest']], array_column($targets, 'digest'), 'only previously verified digests');

        $result = $updates->rollback($this->admin, 'password123', $this->installedId, (int) $this->seeded['release_id']);
        self::assertSame('staged', $result['status']);
        $updates->applyStaged($this->admin, 'password123', $this->installedId);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest']);
        self::assertSame(
            'rollback',
            (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0]['event'],
            'applyStaged derives the rollback event from version order',
        );
    }

    public function test_rollback_to_a_never_verified_release_refuses(): void
    {
        $this->assertPolicyRefusal(
            'rollback_target',
            fn () => $this->updates()->rollback($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']),
        );
    }

    public function test_update_policy_stores_only_the_gate_a_vocabulary(): void
    {
        $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'notify');
        self::assertSame('notify', (new InstalledPackageRepository($this->db))->find($this->installedId)['update_policy']);
        $this->assertPolicyRefusal(
            'update_policy',
            fn () => $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'auto'),
        );
    }
}
