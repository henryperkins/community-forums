<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Request;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\UserRepository;
use App\Repository\WebhookRepository;
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

    /** RepairService exactly as App::buildContainer() wires it, so these tests pin the binding too. */
    private function repair(): RepairService
    {
        $container = (new \ReflectionMethod($this->app, 'buildContainer'))->invoke($this->app, new Request('GET', '/'));

        return $container->get(RepairService::class);
    }

    /**
     * An enabled remote_app install holding a live package API token and an active
     * package webhook, linked through installed_package_credentials.
     *
     * @return array{installed_id:int,digest:string,api_token_id:int,webhook_id:int}
     */
    private function remoteAppWithLiveCredentials(): array
    {
        $seeded = RegistryFixtures::seed($this->db, $this->root, null, [
            'source_id' => 'rb-test-remote',
            'publisher_uid' => 'acme-remote',
            'package_uid' => 'acme/inbox-sync',
            'name' => 'Acme Inbox Sync',
            'type' => 'remote_app',
        ]);
        $installs = new InstalledPackageRepository($this->db);
        $installedId = $installs->create([
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
            'digest' => $seeded['release_digest'],
            'source_registry_id' => $seeded['registry_id'],
            'publisher_id' => $seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => null,
            'compat_max' => null,
            'installed_by' => $this->admin->id(),
        ]);
        $installs->setState($installedId, 'enabled');

        $label = 'pkg:acme/inbox-sync#' . $installedId;
        $tokenId = (new ApiTokenRepository($this->db))
            ->insert($label, hash('sha256', 'rbt_repair_' . $installedId), '["read:boards"]', $this->admin->id(), null);
        $webhookId = (new WebhookRepository($this->db))
            ->insert($label, 'https://hooks.acme.test/rb', '["topic.created"]', '', $this->admin->id());
        $credentials = new InstalledPackageCredentialRepository($this->db);
        $credentials->insertApiToken($installedId, $tokenId, $label, '["read:boards"]', $this->admin->id());
        $credentials->insertWebhook($installedId, $webhookId, $label, '["topic.created"]', $this->admin->id());

        return [
            'installed_id' => $installedId,
            'digest' => $seeded['release_digest'],
            'api_token_id' => $tokenId,
            'webhook_id' => $webhookId,
        ];
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

        $repair = $this->repair();

        self::assertSame(1, $repair->repairInstalledPackageStates());
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM package_transparency_log WHERE package_uid = 'acme/midnight-theme' AND event = 'force_disable'",
            ),
        );
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_force_disable' AND target_id = ? AND actor_id IS NULL",
                [$this->seeded['package_id']],
            ),
        );
        self::assertSame(0, $repair->repairInstalledPackageStates(), 'idempotent');
        self::assertArrayHasKey('installed_packages', $repair->repairAll());
    }

    public function test_repair_force_disable_records_match_the_worker_record_shape(): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REPAIR-SHAPE',
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

        $this->repair()->repairInstalledPackageStates();

        // PackageHealthService::securityDisable records new_version on the disable
        // history row; the repair mirror must not drift by omitting it.
        $history = $this->db->fetch(
            "SELECT new_version, new_digest, detail FROM package_history
             WHERE installed_package_id = ? AND event = 'disable' ORDER BY id DESC LIMIT 1",
            [$this->installedId],
        );
        self::assertSame('1.0.0', $history['new_version'], 'repair disable history carries the version like the worker');
        self::assertSame($this->seeded['release_digest'], $history['new_digest']);
        self::assertStringStartsWith('repair reconcile: ', (string) $history['detail'], 'repair-originated disables stay distinguishable');

        // The worker leaves transparency registry_id NULL; the repair mirror must too.
        $transparency = $this->db->fetch(
            "SELECT registry_id FROM package_transparency_log
             WHERE package_uid = 'acme/midnight-theme' AND event = 'force_disable' ORDER BY id DESC LIMIT 1",
        );
        self::assertNull($transparency['registry_id'], 'repair matches the worker: transparency registry_id stays NULL');
    }

    public function test_repair_disables_enabled_installs_of_a_suspended_publisher(): void
    {
        (new PackagePublisherRepository($this->db))->setStatus((int) $this->seeded['publisher_id'], 'suspended');

        self::assertSame(1, $this->repair()->repairInstalledPackageStates());

        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        self::assertSame(
            'repair reconcile: publisher suspended',
            $this->db->fetchValue(
                "SELECT detail FROM package_history
                 WHERE installed_package_id = ? AND event = 'disable' ORDER BY id DESC LIMIT 1",
                [$this->installedId],
            ),
        );
    }

    public function test_repair_force_disable_tears_down_package_credentials(): void
    {
        $app = $this->remoteAppWithLiveCredentials();
        self::assertSame(1, (int) (new WebhookRepository($this->db))->findById($app['webhook_id'])['is_active'], 'webhook starts live');
        (new LocalPackageBlockRepository($this->db))->add($app['digest'], null, 'incident', null);

        self::assertSame(1, $this->repair()->repairInstalledPackageStates());

        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($app['installed_id'])['state']);
        self::assertSame(
            [],
            (new InstalledPackageCredentialRepository($this->db))->activeForInstall($app['installed_id']),
            'every package credential link is revoked',
        );
        self::assertNotNull(
            (new ApiTokenRepository($this->db))->findById($app['api_token_id'])['revoked_at'],
            'the package API token is revoked, so a later re-enable cannot revive it',
        );
        self::assertSame(
            0,
            (int) (new WebhookRepository($this->db))->findById($app['webhook_id'])['is_active'],
            'the package webhook is paused, so dispatch() stops sending it events',
        );
    }

    public function test_repair_cancels_a_blocked_staged_update_and_labels_it_as_repair(): void
    {
        $next = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, ['version' => '1.1.0']);
        (new InstalledPackageRepository($this->db))->stageRelease($this->installedId, $next['release_id'], $next['digest']);
        (new LocalPackageBlockRepository($this->db))->add($next['digest'], null, 'incident', null);

        self::assertSame(1, $this->repair()->repairInstalledPackageStates());

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('enabled', $row['state'], 'only the staged target is blocked');
        self::assertNull($row['staged_release_id']);
        self::assertSame(
            'cancelled: repair reconcile: local blocklist',
            $this->db->fetchValue(
                "SELECT detail FROM package_history
                 WHERE installed_package_id = ? AND event = 'update_staged' ORDER BY id DESC LIMIT 1",
                [$this->installedId],
            ),
        );
    }

    public function test_console_repair_wires_package_health_into_repair_service(): void
    {
        $console = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/console');

        self::assertSame(1, preg_match("/case 'repair:all':(.*?)break;/s", $console, $block), 'bin/console has a repair:all command');
        self::assertStringContainsString('new RepairService(', $block[1]);
        self::assertStringContainsString(
            '$packageHealth($db)',
            $block[1],
            'bin/console repair must pass PackageHealthService so repair disables tear down package credentials',
        );
    }
}
