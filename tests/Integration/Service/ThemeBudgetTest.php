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
use App\Repository\PackageThemeRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\SettingRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\BaselineMetricsService;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Packages\ThemeAssetScanner;
use App\Service\Packages\ThemeBuildService;
use App\Service\Packages\ThemeStateService;
use App\Service\Phase5BudgetReportService;
use App\Service\Registry\ArrayRegistryTransport;
use App\Support\Phase5Budgets;
use Tests\Support\TestCase;

final class ThemeBudgetTest extends TestCase
{
    private string $artifactDir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactDir = sys_get_temp_dir() . '/rb-theme-budget-test-' . bin2hex(random_bytes(4));
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

    public function test_theme_build_apply_budget_key_exists(): void
    {
        $budget = Phase5Budgets::get('theme.build_apply_p95');

        self::assertSame('Theme build + activate (declarative package)', $budget['metric']);
        self::assertSame(10_000, $budget['target']);
        self::assertSame('p95', $budget['statistic']);
        self::assertSame('inc4', $budget['measurable_at']);
    }

    public function test_measure_theme_build_apply_produces_a_p95_sample(): void
    {
        $themes = $this->themeState();
        $result = (new BaselineMetricsService($this->db))->measureThemeBuildApply(
            $this->lifecycle($themes),
            $themes,
            $this->db,
            $this->store,
            samples: 2,
        );

        self::assertSame(2, $result['samples']);
        self::assertGreaterThan(0.0, $result['p95']);
        self::assertLessThan(10_000.0, $result['p95'], 'D11 declarative package target sanity on the test fixture');
        self::assertSame('synthetic theme package build/activate samples', $result['data_fixture']);
    }

    public function test_budget_report_marks_theme_build_apply_measured(): void
    {
        $themes = $this->themeState();
        $report = new Phase5BudgetReportService(
            $this->db,
            null,
            null,
            $this->lifecycle($themes),
            $this->updates(),
            $this->store,
            $themes,
        );
        $rows = [];
        foreach ($report->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['theme.build_apply_p95']['status']);
        self::assertStringContainsString('ms theme build/apply', $rows['theme.build_apply_p95']['measured']);
        self::assertStringContainsString('Theme build/apply p50/p95/p99', $report->render());
    }

    private function lifecycle(?ThemeStateService $themes = null): PackageLifecycleService
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
            null,
            $themes,
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

    private function themeState(): ThemeStateService
    {
        return new ThemeStateService(
            $this->db,
            new PackageThemeRepository($this->db),
            new InstalledPackageRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            $this->store,
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            new ThemeBuildService($this->db, new PackageThemeRepository($this->db), new ManifestValidator(), new ThemeAssetScanner()),
            new WriteGate(),
            new ReauthGate(new PasswordHasher()),
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new PackageHistoryRepository($this->db),
        );
    }
}
