<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\Packages\ManifestValidator;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageHealthService;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageSecurityResponseService;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageSecurityResponseServiceTest extends TestCase
{
    private User $admin;
    private string $artifactDir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
        $this->artifactDir = sys_get_temp_dir() . '/rb-sec-' . bin2hex(random_bytes(4));
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

    private function flags(): FeatureFlags
    {
        return new FeatureFlags(new SettingRepository($this->db));
    }

    private function reauth(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            $this->flags(),
            $this->config,
        );
    }

    private function integrations(): PackageIntegrationService
    {
        return new PackageIntegrationService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new InstalledPackageSettingsRepository($this->db),
            new InstalledPackageCredentialRepository($this->db),
            new ApiTokenService($this->db, new ApiTokenRepository($this->db), new ModerationLogRepository($this->db), $this->flags(), $this->config, $this->reauth(), new WriteGate()),
            new WebhookService($this->db, new WebhookRepository($this->db), new WebhookDeliveryRepository($this->db), $this->vault(), new ModerationLogRepository($this->db), $this->flags(), $this->config, $this->reauth(), new WriteGate(), new EgressGuard(false, [])),
            new ApiTokenRepository($this->db),
            new WebhookRepository($this->db),
            $this->vault(),
            new ManifestValidator(),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new ModerationLogRepository($this->db),
            $this->reauth(),
            new WriteGate(),
            $this->flags(),
            new SettingRepository($this->db),
            $this->config,
        );
    }

    private function enforcement(): PackageHealthService
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

    private function securityService(?Config $config = null): PackageSecurityResponseService
    {
        return new PackageSecurityResponseService(
            $this->db,
            new SettingRepository($this->db),
            new RegistryAdvisoryService($this->db, new TrustChainVerifier(), new RegistryTrustKeyRepository($this->db), new PackageAdvisoryRepository($this->db), new PackageRepository($this->db), new PackageReleaseRepository($this->db), new ModerationLogRepository($this->db)),
            new LocalBlocklistService(new LocalPackageBlockRepository($this->db), new PackageRepository($this->db), $this->reauth(), new WriteGate(), new ModerationLogRepository($this->db)),
            $this->enforcement(),
            $this->integrations(),
            new PackagePublisherRepository($this->db),
            new PublisherSigningKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->reauth(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            $config ?? $this->config,
        );
    }

    /** Seed one enabled remote_app install + one package-owned webhook linked as a credential. @return array{install_id:int,webhook_id:int,package_uid:string} */
    private function seedEnabledIntegration(): array
    {
        $root = SigningHarness::generate('sec-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'type' => 'remote_app',
            'trust_class' => 'reviewed_remote',
            'publisher_uid' => 'acme-apps',
            'publisher_name' => 'Acme Apps',
            'package_uid' => 'acme/webhook-app',
            'name' => 'Webhook App',
        ]);
        $installs = new InstalledPackageRepository($this->db);
        $installId = $installs->create([
            'package_id' => $ids['package_id'],
            'release_id' => $ids['release_id'],
            'digest' => $ids['release_digest'],
            'source_registry_id' => $ids['registry_id'],
            'publisher_id' => $ids['publisher_id'],
            'trust_class' => 'reviewed_remote',
            'review_status' => 'approved',
            'compat_min' => null,
            'compat_max' => null,
            'installed_by' => $this->admin->id(),
        ]);
        $installs->setState($installId, 'enabled');

        $webhookId = (int) $this->db->insert(
            "INSERT INTO webhooks (name, url, events, secret_ref, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            ['acme-app-hook', 'https://app.acme.test/hooks', json_encode(['thread.created']), 'svcsec_' . str_repeat('b', 32), $this->admin->id()],
        );
        $this->db->insert(
            "INSERT INTO installed_package_credentials (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
             VALUES (?, 'webhook', ?, 'events', ?, ?, UTC_TIMESTAMP())",
            [$installId, $webhookId, json_encode(['thread.created']), $this->admin->id()],
        );

        return ['install_id' => $installId, 'webhook_id' => $webhookId, 'package_uid' => 'acme/webhook-app'];
    }

    public function test_execution_disabled_predicate_reflects_setting_and_config_break_glass_independently_of_flag(): void
    {
        self::assertFalse($this->securityService()->isExecutionDisabled(), 'off by default');

        // Config break-glass forces it on even while package_registry is dark.
        $items = $this->config->all();
        $items['packages']['execution_disabled'] = true;
        $breakGlass = $this->securityService(new Config($items));

        self::assertFalse($this->flags()->enabled('package_registry'), 'package_registry is dark');
        self::assertTrue($breakGlass->isExecutionDisabled(), 'config break-glass is flag-independent');
    }

    public function test_emergency_disable_requires_reauth_and_leaves_runtime_live_on_bad_password(): void
    {
        $svc = $this->securityService();
        try {
            $svc->setExecutionDisabled($this->admin, 'WRONG-PASSWORD', true, 'panic');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertFalse($svc->isExecutionDisabled(), 'runtime stays live when reauth fails');
    }

    public function test_emergency_disable_pauses_package_owned_delivery_appends_transparency_and_preserves_management(): void
    {
        $seed = $this->seedEnabledIntegration();
        $svc = $this->securityService();

        $affected = $svc->setExecutionDisabled($this->admin, 'password123', true, 'incident-42');
        self::assertSame(1, $affected, 'one active integration install affected');

        // Runtime suppressed: the predicate the delivery worker/dispatch + credential-auth consult is on.
        self::assertTrue($svc->isExecutionDisabled());
        // Belt: the package-owned webhook is paused so the existing delivery worker naturally skips it.
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_active FROM webhooks WHERE id = ?', [$seed['webhook_id']]));
        // Transparency append per affected install.
        $entries = (new PackageTransparencyLogRepository($this->db))->forPackageUid($seed['package_uid'], 10);
        self::assertNotSame([], array_filter($entries, static fn (array $r): bool => $r['event'] === 'force_disable'));
        // Management preserved while disabled: the install is not torn down (operator can still view/revoke/export/uninstall).
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($seed['install_id'])['state']);

        // Re-enabling clears the brake (does not auto-resume delivery).
        $svc->setExecutionDisabled($this->admin, 'password123', false, null);
        self::assertFalse($svc->isExecutionDisabled());
        $actions = array_column((new ModerationLogRepository($this->db))->recent(20), 'action');
        self::assertContains('package_execution_disabled', $actions);
        self::assertContains('package_execution_enabled', $actions);
    }

    public function test_overview_lists_publishers_advisories_and_blocklist_from_the_shared_sources(): void
    {
        $root = SigningHarness::generate('ov-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'publisher_uid' => 'globex',
            'publisher_name' => 'Globex',
            'package_uid' => 'globex/plugin',
        ]);
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'adv-ov-1',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'high',
            'action' => 'warn',
            'summary' => 'test advisory',
            'signed_evidence' => '{}',
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]);
        (new LocalPackageBlockRepository($this->db))->add(str_repeat('a', 64), 'globex/plugin', 'manual block', $this->admin->id());

        $overview = $this->securityService()->overview();

        self::assertNotSame([], array_filter($overview['publishers'], static fn (array $r): bool => $r['publisher_uid'] === 'globex'));
        self::assertNotSame([], array_filter($overview['advisories'], static fn (array $r): bool => $r['advisory_uid'] === 'adv-ov-1'));
        self::assertNotSame([], array_filter($overview['blocklist'], static fn (array $r): bool => $r['package_uid'] === 'globex/plugin'));
        self::assertFalse($overview['execution_disabled']);
        self::assertIsInt($overview['affected_installs']);
    }

    public function test_publisher_detail_returns_records_or_null_for_unknown(): void
    {
        $root = SigningHarness::generate('pd-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'publisher_uid' => 'initech',
            'publisher_name' => 'Initech',
            'package_uid' => 'initech/app',
        ]);
        $svc = $this->securityService();

        $detail = $svc->publisherDetail($ids['publisher_id']);
        self::assertNotNull($detail);
        self::assertSame('initech', $detail['publisher']['publisher_uid']);
        self::assertNotSame([], array_filter($detail['packages'], static fn (array $r): bool => $r['package_uid'] === 'initech/app'));

        self::assertNull($svc->publisherDetail(999999));
    }
}
