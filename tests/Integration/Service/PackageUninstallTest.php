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
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageUninstallTest extends TestCase
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
        $this->artifactDir = sys_get_temp_dir() . '/rb-uninstall-' . bin2hex(random_bytes(4));
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

    private function service(): PackageLifecycleService
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

    public function test_uninstall_disables_first_exports_and_sets_retention_from_the_manifest(): void
    {
        $withRetention = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '2.0.0',
            'manifest' => ['install' => ['retention_days' => 14]],
        ], $this->artifactDir);

        $service = $this->service();
        $installedId = $service->install(
            $this->admin,
            'password123',
            (int) $this->seeded['package_id'],
            (int) $withRetention['release_id'],
        );
        $service->consent($this->admin, 'password123', $installedId);
        $service->enable($this->admin, 'password123', $installedId);

        $export = $service->uninstall($this->admin, 'password123', $installedId);

        self::assertSame('rb-install-export.v1', $export['format']);
        self::assertSame('acme/midnight-theme', $export['package']['uid']);
        self::assertNull($export['settings']);
        self::assertNotEmpty($export['permissions']);

        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('uninstalled', $row['state']);
        self::assertNotNull($row['export_json']);
        self::assertNotNull($row['uninstalled_at']);
        $expected = (new \DateTimeImmutable($row['uninstalled_at'], new \DateTimeZone('UTC')))->modify('+14 days');
        self::assertEqualsWithDelta(
            $expected->getTimestamp(),
            (new \DateTimeImmutable($row['retain_until'], new \DateTimeZone('UTC')))->getTimestamp(),
            5.0,
        );

        $events = array_column((new PackageHistoryRepository($this->db))->forInstall($installedId), 'event');
        self::assertSame(['uninstall', 'disable'], array_slice($events, 0, 2), 'execution is disabled before removal');

        self::assertNotEmpty(
            (new InstalledPackagePermissionRepository($this->db))->forInstall($installedId),
            'grant snapshot is retained through the retention window',
        );
    }

    public function test_export_is_available_standalone_and_records_history(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);

        $export = $service->export($this->admin, $installedId);

        self::assertSame('rb-install-export.v1', $export['format']);
        self::assertSame('approved', $export['provenance']['review']['decision']);
        self::assertNotNull((new InstalledPackageRepository($this->db))->find($installedId)['exported_at']);
        self::assertSame('export', (new PackageHistoryRepository($this->db))->forInstall($installedId, 1)[0]['event']);
    }

    public function test_uninstall_twice_refuses_and_reinstall_revives_the_row(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->uninstall($this->admin, 'password123', $installedId);

        $this->assertPolicyRefusal('invalid_state', fn () => $service->uninstall($this->admin, 'password123', $installedId));

        $revivedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        self::assertSame($installedId, $revivedId, 'UNIQUE(package_id) forces row revival');
        $row = (new InstalledPackageRepository($this->db))->find($revivedId);
        self::assertSame('installed', $row['state']);
        self::assertNull($row['export_json']);
    }

    public function test_reverify_restores_a_quarantined_install_to_disabled_only_when_bytes_match(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);

        $path = $this->store->pathFor($this->seeded['release_digest']);
        $original = file_get_contents($path);
        self::assertIsString($original);
        file_put_contents($path, 'tampered');
        $this->assertPolicyRefusal('artifact_tampered', fn () => $service->enable($this->admin, 'password123', $installedId));

        self::assertFalse($service->reverify($this->admin, $installedId), 'bytes still wrong means it stays quarantined');
        self::assertSame('quarantined', (new InstalledPackageRepository($this->db))->find($installedId)['state']);

        file_put_contents($path, $original);
        self::assertTrue($service->reverify($this->admin, $installedId));
        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('disabled', $row['state'], 'never auto-enables');
        self::assertSame('ok', $row['health']);
        self::assertNull($row['quarantine_reason']);

        $this->assertPolicyRefusal('not_quarantined', fn () => $service->reverify($this->admin, $installedId));
    }
}
