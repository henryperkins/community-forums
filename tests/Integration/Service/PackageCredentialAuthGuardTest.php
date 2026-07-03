<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\FeatureFlags;
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
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\ApiPrincipal;
use App\Security\EgressGuard;
use App\Security\Packages\ManifestValidator;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use App\Service\Packages\PackageCredentialAuthGuard;
use App\Service\Packages\PackageIntegrationService;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/**
 * Package-owned API-token authentication guard. Human/legacy tokens pass through
 * unchanged; package-owned tokens fail closed when execution is disabled, the
 * credential link is revoked, the install is not enabled/approved, or a
 * local/advisory block applies. Authentication is exercised through a real
 * ApiTokenService that receives the guard.
 */
final class PackageCredentialAuthGuardTest extends TestCase
{
    private SigningHarness $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        (new SettingRepository($this->db))->set('features', ['api_tokens' => true, 'service_secrets' => true]);
    }

    private function guard(?Config $config = null): PackageCredentialAuthGuard
    {
        return new PackageCredentialAuthGuard(
            new InstalledPackageCredentialRepository($this->db),
            new InstalledPackageRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new SettingRepository($this->db),
            $config ?? $this->config,
        );
    }

    private function authService(bool $withGuard = true): ApiTokenService
    {
        return new ApiTokenService(
            $this->db,
            new ApiTokenRepository($this->db),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            $withGuard ? $this->guard() : null,
        );
    }

    private function vault(FeatureFlags $ff): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            $ff,
            $this->config,
        );
    }

    private function integration(): PackageIntegrationService
    {
        $ff = new FeatureFlags(new SettingRepository($this->db));

        return new PackageIntegrationService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new InstalledPackageSettingsRepository($this->db),
            new InstalledPackageCredentialRepository($this->db),
            $this->authService(false),
            new WebhookService(
                $this->db,
                new WebhookRepository($this->db),
                new WebhookDeliveryRepository($this->db),
                $this->vault($ff),
                new ModerationLogRepository($this->db),
                $ff,
                $this->config,
                new ReauthGate(new PasswordHasher()),
                new WriteGate(),
                new EgressGuard(false, []),
            ),
            new ApiTokenRepository($this->db),
            new WebhookRepository($this->db),
            $this->vault($ff),
            new ManifestValidator(),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new ModerationLogRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            $ff,
            new SettingRepository($this->db),
            $this->config,
        );
    }

    /** @return array{0:User,1:int} */
    private function enabledRemoteApp(): array
    {
        $seeded = RegistryFixtures::seed($this->db, $this->root, null, [
            'type' => 'remote_app',
            'publisher_uid' => 'acme',
            'package_uid' => 'acme/remote-app',
            'name' => 'Acme Remote',
        ]);
        $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
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
            'installed_by' => $admin->id(),
        ]);
        $installs->setState($installedId, 'enabled');
        (new InstalledPackagePermissionRepository($this->db))->replaceWithGrants(
            $installedId,
            [['kind' => 'api_scope', 'key' => 'read:boards', 'risk' => 'medium', 'granted' => true]],
            $admin->id(),
        );

        return [$admin, $installedId];
    }

    /** @return array{token:string,link_id:int,token_id:int,installed_id:int,digest:string,uid:string} */
    private function provisionPackageToken(): array
    {
        [$admin, $installedId] = $this->enabledRemoteApp();
        $res = $this->integration()->provisionCredentials($admin, 'password123', $installedId);
        $linkId = (int) $res['credentials'][0]['id'];
        $link = (new InstalledPackageCredentialRepository($this->db))->find($linkId);
        $install = (new InstalledPackageRepository($this->db))->find($installedId);

        return [
            'token' => (string) $res['api_token'],
            'link_id' => $linkId,
            'token_id' => (int) $link['api_token_id'],
            'installed_id' => $installedId,
            'digest' => (string) $install['digest'],
            'uid' => 'acme/remote-app',
        ];
    }

    public function test_package_owned_token_authenticates_while_link_and_install_are_safe(): void
    {
        $p = $this->provisionPackageToken();
        $principal = $this->authService()->authenticate('Bearer ' . $p['token']);
        self::assertInstanceOf(ApiPrincipal::class, $principal);
        self::assertSame(['read:boards'], $principal->scopes());
    }

    public function test_human_token_still_authenticates_when_package_execution_is_disabled(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
        $human = $this->authService(false)->mint($admin, 'password123', 'human token', ['read:boards'], null);
        (new SettingRepository($this->db))->set('package_execution_disabled', '1');

        // A token with no credential link is human/legacy and passes through the guard.
        $principal = $this->authService()->authenticate('Bearer ' . $human['token']);
        self::assertInstanceOf(ApiPrincipal::class, $principal);
    }

    public function test_package_owned_token_is_denied_when_execution_is_disabled(): void
    {
        $p = $this->provisionPackageToken();
        (new SettingRepository($this->db))->set('package_execution_disabled', '1');
        self::assertNull($this->authService()->authenticate('Bearer ' . $p['token']));
    }

    public function test_package_owned_token_is_denied_after_credential_link_is_revoked(): void
    {
        $p = $this->provisionPackageToken();
        // Revoke ONLY the credential link; leave the raw api_tokens row active.
        (new InstalledPackageCredentialRepository($this->db))->markRevoked($p['link_id']);
        self::assertNull(
            $this->db->fetchValue('SELECT revoked_at FROM api_tokens WHERE id = ?', [$p['token_id']]),
            'the api_tokens row itself is not revoked — denial is by link state',
        );

        self::assertNull($this->authService()->authenticate('Bearer ' . $p['token']));
    }

    public function test_package_owned_token_is_denied_when_install_is_disabled_quarantined_or_uninstalled(): void
    {
        $p = $this->provisionPackageToken();
        foreach (['disabled', 'quarantined', 'uninstalled'] as $state) {
            (new InstalledPackageRepository($this->db))->setState($p['installed_id'], $state);
            self::assertNull(
                $this->authService()->authenticate('Bearer ' . $p['token']),
                "state=$state must deny package-owned auth",
            );
        }
    }

    public function test_package_owned_token_is_denied_when_review_local_block_or_advisory_is_unsafe(): void
    {
        $p = $this->provisionPackageToken();
        (new LocalPackageBlockRepository($this->db))->add($p['digest'], $p['uid'], 'incident', null);
        self::assertNull($this->authService()->authenticate('Bearer ' . $p['token']));
    }

    public function test_is_execution_disabled_honours_db_setting_and_config_break_glass(): void
    {
        // DB setting path.
        (new SettingRepository($this->db))->set('package_execution_disabled', '1');
        self::assertTrue($this->guard()->isExecutionDisabled());

        // Config break-glass path (DB setting off).
        (new SettingRepository($this->db))->set('package_execution_disabled', '0');
        $breakGlass = new Config(array_replace_recursive($this->config->all(), [
            'packages' => ['execution_disabled' => true],
        ]));
        self::assertTrue($this->guard($breakGlass)->isExecutionDisabled());
        self::assertFalse($this->guard()->isExecutionDisabled());
    }
}
