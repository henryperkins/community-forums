<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
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
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageLifecycleServiceTest extends TestCase
{
    private SigningHarness $root;

    /** @var array<string,mixed> */
    private array $seeded;

    private string $artifactDir;

    private PackageArtifactStore $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-lifecycle-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);

        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $this->admin = $admin;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    /** @param array<string,\App\Service\Registry\RegistryFetchResult> $responses */
    private function service(array $responses = []): PackageLifecycleService
    {
        $acquisition = new PackageAcquisitionService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ManifestValidator(),
            new ArrayRegistryTransport($responses),
        );

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
            $acquisition,
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            30,
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

    /** @return array<string,mixed> */
    private function lastHistory(): array
    {
        return (new PackageHistoryRepository($this->db))->forPackage((int) $this->seeded['package_id'], 1)[0];
    }

    /** @return list<string> */
    private function auditActions(): array
    {
        $stmt = $this->db->run(
            "SELECT action FROM moderation_log WHERE target_type = 'package' AND target_id = :package_id ORDER BY id",
            ['package_id' => $this->seeded['package_id']],
        );

        return array_map(static fn (array $row): string => (string) $row['action'], $stmt->fetchAll());
    }

    public function test_plan_reports_manifest_permissions_compat_and_no_refusal(): void
    {
        $plan = $this->service()->plan($this->admin, (int) $this->seeded['package_id']);

        self::assertNull($plan['refusal']);
        self::assertTrue($plan['compatible']);
        self::assertSame('acme/midnight-theme', $plan['manifest']->uid);
        self::assertNotEmpty($plan['permissions']);
        self::assertNull($plan['installed']);
    }

    public function test_plan_surfaces_gate_refusals_instead_of_throwing(): void
    {
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);

        $plan = $this->service()->plan($this->admin, (int) $this->seeded['package_id']);

        self::assertSame('locally_blocked', $plan['refusal']['code']);
    }

    public function test_install_consent_enable_disable_happy_path(): void
    {
        $service = $this->service();
        $packageId = (int) $this->seeded['package_id'];

        $installedId = $service->install($this->admin, 'password123', $packageId);
        $installs = new InstalledPackageRepository($this->db);
        $perms = new InstalledPackagePermissionRepository($this->db);

        $row = $installs->find($installedId);
        self::assertSame('installed', $row['state']);
        self::assertSame($this->seeded['release_digest'], $row['digest']);
        self::assertSame('reviewed_declarative', $row['trust_class']);
        self::assertGreaterThan(0, $perms->ungrantedCount($installedId), 'granted=0 until consent');

        $this->assertPolicyRefusal('not_consented', fn () => $service->enable($this->admin, 'password123', $installedId));

        $granted = $service->consent($this->admin, 'password123', $installedId);
        self::assertSame(1, $granted);
        self::assertSame(0, $perms->ungrantedCount($installedId));

        $service->enable($this->admin, 'password123', $installedId);
        self::assertSame('enabled', $installs->find($installedId)['state']);

        $service->disable($this->admin, $installedId);
        self::assertSame('disabled', $installs->find($installedId)['state']);

        $service->enable($this->admin, 'password123', $installedId);
        self::assertSame('enabled', $installs->find($installedId)['state']);

        $events = array_map(
            static fn (array $history): string => (string) $history['event'],
            array_reverse((new PackageHistoryRepository($this->db))->forInstall($installedId)),
        );
        self::assertSame(['install', 'consent', 'enable', 'disable', 'enable'], $events);
        self::assertSame(
            ['package_install', 'package_consent', 'package_enable', 'package_disable', 'package_enable'],
            $this->auditActions(),
        );

        $transparency = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertContains('install', array_column($transparency, 'event'));
    }

    public function test_double_install_refuses(): void
    {
        $service = $this->service();
        $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);

        $this->assertPolicyRefusal(
            'already_installed',
            fn () => $service->install($this->admin, 'password123', (int) $this->seeded['package_id']),
        );
    }

    public function test_wrong_password_is_a_validation_error_and_installs_nothing(): void
    {
        try {
            $this->service()->install($this->admin, 'wrong', (int) $this->seeded['package_id']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }

    public function test_refused_install_writes_the_exact_failure_stage_and_no_rows(): void
    {
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);

        $this->assertPolicyRefusal(
            'locally_blocked',
            fn () => $this->service()->install($this->admin, 'password123', (int) $this->seeded['package_id']),
        );

        self::assertNull(
            (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']),
            'validate-first: a policy refusal persists no install row',
        );
        $history = $this->lastHistory();
        self::assertSame('install', $history['event']);
        self::assertSame('policy', $history['failure_stage']);
        self::assertSame('locally_blocked', $history['detail']);
    }

    public function test_incompatible_core_range_refuses_at_the_compatibility_stage(): void
    {
        $future = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '9.0.0',
            'manifest' => ['core' => ['min' => '99.0.0', 'max' => null]],
        ], $this->artifactDir);

        $this->assertPolicyRefusal(
            'incompatible_core',
            fn () => $this->service()->install(
                $this->admin,
                'password123',
                (int) $this->seeded['package_id'],
                (int) $future['release_id'],
            ),
        );
        self::assertSame('compatibility', $this->lastHistory()['failure_stage']);
    }

    public function test_revoked_digest_cannot_be_installed_or_enabled(): void
    {
        $service = $this->service();
        $packageId = (int) $this->seeded['package_id'];
        $installedId = $service->install($this->admin, 'password123', $packageId);
        $service->consent($this->admin, 'password123', $installedId);

        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REVOKE-1',
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $packageId,
            'affected_version_range' => null,
            'affected_digest' => $this->seeded['release_digest'],
            'severity' => 'critical',
            'action' => 'revoke',
            'summary' => 'compromised',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $this->assertPolicyRefusal('advisory_revoked', fn () => $service->enable($this->admin, 'password123', $installedId));
    }

    public function test_enable_with_tampered_artifact_quarantines(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);

        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered bytes');

        $this->assertPolicyRefusal('artifact_tampered', fn () => $service->enable($this->admin, 'password123', $installedId));

        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('quarantined', $row['state']);
        self::assertSame('failed', $row['health']);
        self::assertNotNull($row['quarantine_reason']);
    }

    public function test_zero_permission_package_enables_without_a_consent_step(): void
    {
        $bare = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '3.0.0',
            'manifest' => ['permissions' => ['data_classes' => []]],
        ], $this->artifactDir);

        $service = $this->service();
        $installedId = $service->install(
            $this->admin,
            'password123',
            (int) $this->seeded['package_id'],
            (int) $bare['release_id'],
        );
        $service->enable($this->admin, 'password123', $installedId);

        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }

    public function test_lifecycle_works_while_the_registry_is_unreachable(): void
    {
        $service = $this->service([]);
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);
        $service->enable($this->admin, 'password123', $installedId);
        $service->disable($this->admin, $installedId);

        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }
}
