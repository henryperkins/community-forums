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
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\RepairService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RepairInstalledPackagesTest extends TestCase
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
        $this->artifactDir = sys_get_temp_dir() . '/rb-repair-install-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);

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

    private function lifecycle(): PackageLifecycleService
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
            new ArrayRegistryTransport([]),
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

    public function test_repair_disables_enabled_installs_matched_by_revoking_advisories(): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REPAIR-1',
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => null,
            'affected_digest' => $this->seeded['release_digest'],
            'severity' => 'critical',
            'action' => 'revoke',
            'summary' => 'compromised',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $repair = new RepairService($this->db);

        self::assertSame(1, $repair->repairInstalledPackageStates());
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        self::assertSame(0, $repair->repairInstalledPackageStates(), 'idempotent');
        self::assertArrayHasKey('installed_packages', $repair->repairAll());
    }
}
