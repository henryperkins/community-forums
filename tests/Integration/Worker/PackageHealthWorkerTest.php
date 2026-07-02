<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

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
use App\Service\Packages\PackageHealthService;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Worker\PackageHealthWorker;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageHealthWorkerTest extends TestCase
{
    private SigningHarness $root;

    /** @var array<string,mixed> */
    private array $seeded;

    private string $artifactDir;

    private PackageArtifactStore $store;

    private User $admin;

    private int $installedId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-health-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        (new PackageRegistryRepository($this->db))->setEnabled((int) $this->seeded['registry_id'], true);

        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $this->admin = $admin;

        $this->installedId = $this->lifecycle()->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $this->lifecycle()->consent($this->admin, 'password123', $this->installedId);
        $this->lifecycle()->enable($this->admin, 'password123', $this->installedId);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
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

    private function health(): PackageHealthService
    {
        return new PackageHealthService(
            $this->db,
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ModerationLogRepository($this->db),
        );
    }

    private function advisoryServiceWithEnforcement(): RegistryAdvisoryService
    {
        return new RegistryAdvisoryService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new ModerationLogRepository($this->db),
            null,
            $this->health(),
        );
    }

    private function blocklistServiceWithEnforcement(): LocalBlocklistService
    {
        return new LocalBlocklistService(
            new LocalPackageBlockRepository($this->db),
            new PackageRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            $this->health(),
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

    public function test_dark_flag_makes_the_worker_a_pure_noop(): void
    {
        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered');

        $stats = (new PackageHealthWorker($this->health(), false))->run();

        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, $stats['checked']);
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
    }

    public function test_tampered_bytes_quarantine_the_install(): void
    {
        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered');

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['quarantined']);
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('quarantined', $row['state']);
        self::assertSame('failed', $row['health']);
        self::assertNotNull($row['last_health_check_at']);

        $history = (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0];
        self::assertSame('quarantine', $history['event']);
        self::assertNull($history['actor_id'], 'worker actions carry no actor');
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_quarantine' AND target_id = ? AND actor_id IS NULL",
                [$this->seeded['package_id']],
            ),
        );
    }

    public function test_missing_artifact_marks_health_degraded_without_false_quarantine(): void
    {
        unlink($this->store->pathFor($this->seeded['release_digest']));

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['checked']);
        self::assertSame(0, $stats['quarantined']);
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('enabled', $row['state'], 'missing local storage is not proof of tampering');
        self::assertSame('degraded', $row['health']);
        self::assertSame('artifact missing during health check', $row['quarantine_reason']);
        self::assertSame(
            0,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM package_history WHERE installed_package_id = ? AND event = 'quarantine'",
                [$this->installedId],
            ),
        );
    }

    public function test_healthy_bytes_mark_health_ok_and_touch_the_check_timestamp(): void
    {
        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['checked']);
        self::assertSame(0, $stats['quarantined']);
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('ok', $row['health']);
        self::assertNotNull($row['last_health_check_at']);
    }

    public function test_force_disable_advisory_disables_the_enabled_install(): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-FD-1',
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'critical',
            'action' => 'force_disable',
            'summary' => 'incident',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['disabled']);
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        $transparency = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertSame('force_disable', $transparency[0]['event']);
        self::assertSame(0, (new PackageHealthWorker($this->health(), true))->run()['disabled']);
    }

    public function test_version_range_advisory_disables_when_release_version_is_unresolvable(): void
    {
        $this->db->run('UPDATE installed_packages SET release_id = NULL WHERE id = ?', [$this->installedId]);
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-FD-UNKNOWN-VERSION',
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'critical',
            'action' => 'force_disable',
            'summary' => 'incident',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['disabled']);
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
    }

    public function test_blocklist_hit_disables_and_cancels_a_matching_staged_target(): void
    {
        (new InstalledPackageRepository($this->db))->stageRelease($this->installedId, (int) $this->seeded['release_id'], $this->seeded['release_digest']);
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('disabled', $row['state']);
        self::assertNull($row['staged_release_id'], 'blocked staged target is cancelled');
        self::assertGreaterThanOrEqual(1, $stats['disabled']);
    }

    public function test_retention_purge_removes_rows_permissions_and_artifact(): void
    {
        $this->lifecycle()->uninstall($this->admin, 'password123', $this->installedId);
        $this->db->run(
            "UPDATE installed_packages SET retain_until = '2020-01-01 00:00:00' WHERE id = ?",
            [$this->installedId],
        );

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['purged']);
        self::assertNull((new InstalledPackageRepository($this->db))->find($this->installedId));
        self::assertSame([], (new InstalledPackagePermissionRepository($this->db))->forInstall($this->installedId));
        self::assertFalse($this->store->has($this->seeded['release_digest']), 'artifact removed after retention');
        $events = array_column((new PackageHistoryRepository($this->db))->forPackage((int) $this->seeded['package_id']), 'event');
        self::assertContains('purge', $events, 'history survives the purge');
    }

    public function test_retention_purge_reclaims_superseded_release_artifacts_too(): void
    {
        // A second cached release beyond the installed one (a prior rollback target).
        $superseded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, ['version' => '1.1.0'], $this->artifactDir);
        self::assertTrue($this->store->has($this->seeded['release_digest']));
        self::assertTrue($this->store->has($superseded['digest']));

        $this->lifecycle()->uninstall($this->admin, 'password123', $this->installedId);
        $this->db->run(
            "UPDATE installed_packages SET retain_until = '2020-01-01 00:00:00' WHERE id = ?",
            [$this->installedId],
        );

        self::assertSame(1, (new PackageHealthWorker($this->health(), true))->run()['purged']);
        self::assertFalse($this->store->has($this->seeded['release_digest']), 'installed digest artifact removed');
        self::assertFalse($this->store->has($superseded['digest']), 'superseded release artifact reclaimed too');
    }

    public function test_notify_policy_counts_available_updates(): void
    {
        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, ['version' => '1.1.0'], $this->artifactDir);
        $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'notify');

        self::assertSame(1, (new PackageHealthWorker($this->health(), true))->run()['updates']);
    }

    public function test_admin_advisory_ingest_enforces_inline_and_blocks_reenable(): void
    {
        $minted = $this->root->mintAdvisory(['action' => 'force_disable', 'affected_version_range' => '<=1.0.0']);

        $this->advisoryServiceWithEnforcement()
            ->ingest((int) $this->seeded['registry_id'], $minted['json'], $minted['signature'], $minted['key_id']);

        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        $this->assertPolicyRefusal('advisory_blocked', fn () => $this->lifecycle()->enable($this->admin, 'password123', $this->installedId));
    }

    public function test_blocklist_add_enforces_inline(): void
    {
        $this->blocklistServiceWithEnforcement()->block($this->admin, $this->seeded['release_digest'], null, 'incident');

        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
    }

    public function test_ingest_can_defer_enforcement_for_batched_worker_runs(): void
    {
        $minted = $this->root->mintAdvisory(['action' => 'force_disable', 'affected_version_range' => '<=1.0.0']);
        $service = $this->advisoryServiceWithEnforcement();

        $service->ingest((int) $this->seeded['registry_id'], $minted['json'], $minted['signature'], $minted['key_id'], enforce: false);
        self::assertSame(
            'enabled',
            (new InstalledPackageRepository($this->db))->find($this->installedId)['state'],
            'deferred ingest does not run a per-advisory enforcement scan',
        );

        $service->reconcileInstalledPolicies();
        self::assertSame(
            'disabled',
            (new InstalledPackageRepository($this->db))->find($this->installedId)['state'],
            'one batched reconcile enforces the whole refresh',
        );
    }
}
