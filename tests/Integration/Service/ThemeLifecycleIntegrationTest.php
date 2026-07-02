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
use App\Repository\PackageThemeRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\SettingRepository;
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
use App\Service\Packages\ThemeAssetScanner;
use App\Service\Packages\ThemeBuildService;
use App\Service\Packages\ThemeStateService;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\RepairService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class ThemeLifecycleIntegrationTest extends TestCase
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
        $this->artifactDir = sys_get_temp_dir() . '/rb-theme-lifecycle-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        (new PackageRegistryRepository($this->db))->setEnabled((int) $this->seeded['registry_id'], true);

        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $this->admin = $admin;

        $themes = $this->themeState();
        $this->installedId = $this->lifecycle($themes)->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $this->lifecycle($themes)->consent($this->admin, 'password123', $this->installedId);
        $this->lifecycle($themes)->enable($this->admin, 'password123', $this->installedId);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    public function test_disabling_active_theme_package_clears_theme_state(): void
    {
        $themes = $this->themeState();
        $build = $themes->activate($this->admin, 'password123', $this->installedId);
        self::assertSame((int) $build['id'], (new PackageThemeRepository($this->db))->state()['active_build_id']);

        $this->lifecycle($themes)->disable($this->admin, $this->installedId);

        $state = (new PackageThemeRepository($this->db))->state();
        self::assertNull($state['active_build_id']);
        self::assertNull($state['lkg_build_id']);

        $history = (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0];
        self::assertSame('theme_deactivate', $history['event']);
        self::assertSame('disabled', $history['detail']);
    }

    public function test_uninstalling_active_theme_package_clears_theme_state(): void
    {
        $themes = $this->themeState();
        $build = $themes->activate($this->admin, 'password123', $this->installedId);
        self::assertSame((int) $build['id'], (new PackageThemeRepository($this->db))->state()['active_build_id']);

        $this->lifecycle($themes)->uninstall($this->admin, 'password123', $this->installedId);

        $state = (new PackageThemeRepository($this->db))->state();
        self::assertNull($state['active_build_id']);
        self::assertSame('uninstalled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
    }

    public function test_lifecycle_quarantine_clears_stale_active_theme_state(): void
    {
        $themes = $this->themeState();
        $build = $themes->activate($this->admin, 'password123', $this->installedId);
        (new InstalledPackageRepository($this->db))->setState($this->installedId, 'disabled');
        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered bytes');

        try {
            $this->lifecycle($themes)->enable($this->admin, 'password123', $this->installedId);
            self::fail('expected tampered artifact to quarantine');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_tampered', $e->code);
        }

        self::assertSame('quarantined', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        self::assertNull((new PackageThemeRepository($this->db))->state()['active_build_id']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM package_history WHERE installed_package_id = ? AND event = 'theme_deactivate' AND prior_digest = ?",
                [$this->installedId, $build['css_digest']],
            ),
        );
    }

    public function test_disabling_lkg_theme_package_clears_only_lkg_state(): void
    {
        $themes = $this->themeState();
        $first = $themes->activate($this->admin, 'password123', $this->installedId);
        $secondInstalledId = $this->installAdditionalTheme('lkg');
        $second = $themes->activate($this->admin, 'password123', $secondInstalledId);

        $state = (new PackageThemeRepository($this->db))->state();
        self::assertSame((int) $second['id'], $state['active_build_id']);
        self::assertSame((int) $first['id'], $state['lkg_build_id']);

        $this->lifecycle($themes)->disable($this->admin, $this->installedId);

        $state = (new PackageThemeRepository($this->db))->state();
        self::assertSame((int) $second['id'], $state['active_build_id']);
        self::assertNull($state['lkg_build_id']);
    }

    public function test_repair_theme_state_clears_dangling_pointers(): void
    {
        $themes = $this->themeState();
        $build = $themes->activate($this->admin, 'password123', $this->installedId);
        (new InstalledPackageRepository($this->db))->setState($this->installedId, 'disabled');

        $repair = new RepairService($this->db, 5, $themes);

        self::assertSame(1, $repair->repairThemeState());
        self::assertNull((new PackageThemeRepository($this->db))->state()['active_build_id']);
        self::assertSame(0, $repair->repairThemeState(), 'repair is idempotent once the stale pointer is gone');
        self::assertArrayHasKey('theme_state', $repair->repairAll());
        self::assertSame((string) $build['css_digest'], $this->db->fetchValue(
            "SELECT css_digest FROM package_theme_builds WHERE id = ?",
            [$build['id']],
        ));
    }

    private function installAdditionalTheme(string $suffix): int
    {
        $root = SigningHarness::generate('test-root-' . $suffix);
        $seeded = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'source_id' => 'rb-test-theme-' . $suffix,
            'publisher_uid' => 'acme-theme-' . $suffix,
            'package_uid' => 'acme/theme-' . $suffix,
            'name' => 'Theme ' . ucfirst($suffix),
        ]);
        (new PackageRegistryRepository($this->db))->setEnabled((int) $seeded['registry_id'], true);
        $themes = $this->themeState();
        $installedId = $this->lifecycle($themes)->install($this->admin, 'password123', (int) $seeded['package_id']);
        $this->lifecycle($themes)->consent($this->admin, 'password123', $installedId);
        $this->lifecycle($themes)->enable($this->admin, 'password123', $installedId);

        return $installedId;
    }

    private function lifecycle(?ThemeStateService $themes = null): PackageLifecycleService
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
            null,
            $themes,
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
