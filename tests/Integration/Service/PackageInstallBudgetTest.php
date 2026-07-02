<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

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
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\BaselineMetricsService;
use App\Service\Phase5BudgetReportService;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\TestCase;

final class PackageInstallBudgetTest extends TestCase
{
    private string $artifactDir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactDir = sys_get_temp_dir() . '/rb-budget-test-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);

        parent::tearDown();
    }

    public function test_measure_install_update_produces_a_p95_sample(): void
    {
        $result = (new BaselineMetricsService($this->db))->measureInstallUpdate(
            $this->lifecycle(),
            $this->updates(),
            $this->db,
            $this->store,
            samples: 2,
        );

        self::assertSame(2, $result['samples']);
        self::assertGreaterThan(0.0, $result['p95']);
        self::assertLessThan(10_000.0, $result['p95'], 'D11 target sanity on the test fixture');
    }

    public function test_install_update_sampler_requires_caller_owned_rollback_transaction(): void
    {
        $this->pdo->rollBack();
        try {
            (new BaselineMetricsService($this->db))->measureInstallUpdate(
                $this->lifecycle(),
                $this->updates(),
                $this->db,
                $this->store,
                samples: 1,
            );
            self::fail('expected sampler to refuse outside a rollback transaction');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('transaction', $e->getMessage());
        } finally {
            $this->pdo->beginTransaction();
        }
    }

    public function test_budget_report_marks_package_install_update_measured(): void
    {
        $report = new Phase5BudgetReportService(
            $this->db,
            null,
            null,
            $this->lifecycle(),
            $this->updates(),
            $this->store,
        );
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['package.install_update_p95']['status']);
        self::assertStringContainsString('ms install/update', $rows['package.install_update_p95']['measured']);
        self::assertStringContainsString('Package install/update p50/p95/p99', $report->render());
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
            new ManifestValidator(),
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
}
