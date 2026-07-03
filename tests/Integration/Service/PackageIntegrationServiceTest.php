<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\ModerationLogRepository;
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
use App\Security\Packages\PackagePolicyException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use App\Service\Packages\PackageIntegrationService;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageIntegrationServiceTest extends TestCase
{
    private SigningHarness $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
    }

    private function service(array $flags = ['api_tokens' => true, 'service_secrets' => true]): PackageIntegrationService
    {
        (new SettingRepository($this->db))->set('features', $flags);
        $ff = new FeatureFlags(new SettingRepository($this->db));

        return new PackageIntegrationService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new InstalledPackageSettingsRepository($this->db),
            new InstalledPackageCredentialRepository($this->db),
            $this->apiTokenService(),
            $this->webhookService($ff),
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

    private function apiTokenService(): ApiTokenService
    {
        return new ApiTokenService(
            $this->db,
            new ApiTokenRepository($this->db),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
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

    private function webhookService(FeatureFlags $ff): WebhookService
    {
        return new WebhookService(
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
        );
    }

    /**
     * @param list<string> $granted
     * @param list<string> $ungranted
     * @return array{0:User,1:int}
     */
    private function enabledRemoteApp(array $granted, array $ungranted = [], string $state = 'enabled'): array
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
        $installs->setState($installedId, $state);

        $perms = [];
        foreach ($granted as $scope) {
            $perms[] = ['kind' => 'api_scope', 'key' => $scope, 'risk' => 'medium', 'granted' => true];
        }
        foreach ($ungranted as $scope) {
            $perms[] = ['kind' => 'api_scope', 'key' => $scope, 'risk' => 'medium', 'granted' => false];
        }
        (new InstalledPackagePermissionRepository($this->db))->replaceWithGrants($installedId, $perms, $admin->id());

        return [$admin, $installedId];
    }

    private function assertRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    public function test_provision_reveals_token_once_stores_only_a_hash_and_authenticates_as_a_scope_only_principal(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards', 'read:threads']);

        $res = $this->service()->provisionCredentials($admin, 'password123', $installedId);

        self::assertNotNull($res['api_token']);
        self::assertStringStartsWith('rbt_', $res['api_token']);
        self::assertNull($res['webhook_secret']);
        self::assertCount(1, $res['credentials']);
        self::assertSame('api_token', $res['credentials'][0]['kind']);

        // Hash-only storage: the api_tokens row keeps only the sha256, never the plaintext.
        $stored = (string) $this->db->fetchValue(
            'SELECT token_hash FROM api_tokens WHERE token_hash = ?',
            [hash('sha256', $res['api_token'])],
        );
        self::assertSame(hash('sha256', $res['api_token']), $stored);

        // Human-token separation: authenticates as a scope-only ApiPrincipal (never a User/role).
        $principal = $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']);
        self::assertInstanceOf(ApiPrincipal::class, $principal);
        self::assertSame(['read:boards', 'read:threads'], $principal->scopes());
        self::assertTrue($principal->hasScope('read:boards'));
        self::assertSame($admin->id(), $principal->createdBy(), 'created_by = minting admin, provenance only');

        // Lifecycle history records a credential_mint.
        $events = array_column(
            (new PackageHistoryRepository($this->db))->forInstall($installedId),
            'event',
        );
        self::assertContains('credential_mint', $events);
    }

    public function test_undeclared_or_unknown_scope_is_denied_and_audited_never_minted(): void
    {
        // TM-SC-08: on a fully-consented install (ungrantedCount===0 is a locked
        // provisioning guard), a granted-but-unknown/future scope local code does
        // not support is excluded from the minted token and audited, never minted.
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards', 'admin:everything']);

        $res = $this->service()->provisionCredentials($admin, 'password123', $installedId);

        $principal = $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']);
        self::assertNotNull($principal);
        self::assertSame(['read:boards'], $principal->scopes(), 'only granted scopes local code supports are minted');

        $denied = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_scope_denied'",
        );
        self::assertSame(1, $denied, 'the unknown scope is audited exactly once');
    }

    public function test_repeated_provisioning_refuses_a_second_active_api_token(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $svc = $this->service();

        $first = $svc->provisionCredentials($admin, 'password123', $installedId);
        self::assertNotNull($first['api_token']);

        $this->assertRefusal(
            'credential_exists',
            fn () => $svc->provisionCredentials($admin, 'password123', $installedId),
        );
        self::assertCount(
            1,
            array_values(array_filter(
                (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId),
                static fn (array $row): bool => (string) $row['kind'] === 'api_token',
            )),
            'exactly one active package-owned api_token link remains',
        );
    }

    public function test_ungranted_permission_blocks_provisioning_and_mints_nothing(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards'], ['read:threads']);
        // read:threads left ungranted -> ungrantedCount > 0 -> refuse before any mint.
        $this->assertRefusal(
            'not_consented',
            fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
        );
        self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId));
    }

    public function test_non_enabled_install_cannot_provision(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards'], [], 'installed');
        $this->assertRefusal(
            'invalid_state',
            fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
        );
    }

    public function test_service_secrets_dark_fails_closed(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $this->expectException(ValidationException::class);
        // service_secrets OFF (api_tokens on) -> hard predecessor missing -> fail closed, mint nothing.
        $this->service(['api_tokens' => true, 'service_secrets' => false])
            ->provisionCredentials($admin, 'password123', $installedId);
    }

    public function test_emergency_execution_disable_blocks_provisioning(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        (new SettingRepository($this->db))->set('package_execution_disabled', '1');
        $this->assertRefusal(
            'execution_disabled',
            fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
        );
    }

    public function test_wrong_password_blocks_provisioning(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $this->expectException(ValidationException::class);
        $this->service()->provisionCredentials($admin, 'WRONG', $installedId);
    }

    public function test_rotate_revokes_the_old_token_and_reveals_a_new_one_once(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $svc = $this->service();
        $first = $svc->provisionCredentials($admin, 'password123', $installedId);
        $credentialId = (int) $first['credentials'][0]['id'];

        $rotated = $svc->rotateCredential($admin, 'password123', $installedId, $credentialId);
        self::assertNull($rotated['secret']);
        self::assertNotNull($rotated['token']);
        self::assertStringStartsWith('rbt_', $rotated['token']);
        self::assertNotSame($first['api_token'], $rotated['token']);

        self::assertNull(
            $this->apiTokenService()->authenticate('Bearer ' . $first['api_token']),
            'the rotated-out token no longer authenticates',
        );
        $principal = $this->apiTokenService()->authenticate('Bearer ' . $rotated['token']);
        self::assertNotNull($principal);
        self::assertSame(['read:boards'], $principal->scopes());
    }

    public function test_revoke_kills_authentication_and_is_idempotent(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $svc = $this->service();
        $res = $svc->provisionCredentials($admin, 'password123', $installedId);
        $credentialId = (int) $res['credentials'][0]['id'];

        $svc->revokeCredential($admin, $installedId, $credentialId);
        self::assertNull(
            $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']),
            'a revoked credential cannot authenticate',
        );

        // Idempotent: a second revoke is a silent no-op (no exception, no re-audit).
        $svc->revokeCredential($admin, $installedId, $credentialId);
        $revokes = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_credential_revoke'",
        );
        self::assertSame(1, $revokes);
    }

    public function test_insert_webhook_link_round_trips_and_holds_kind_invariant(): void
    {
        [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
        $webhookId = (new WebhookRepository($this->db))->insert('pkg-hook', 'https://hooks.acme.test/rb', '["topic.created"]', '', $admin->id());
        $repo = new InstalledPackageCredentialRepository($this->db);

        $id = $repo->insertWebhook($installedId, $webhookId, 'pkg:acme/inbox-sync#' . $installedId, '["topic.created"]', $admin->id());
        self::assertGreaterThan(0, $id);

        $row = $repo->findByWebhook($webhookId);
        self::assertNotNull($row);
        self::assertSame('webhook', (string) $row['kind']);
        self::assertSame($webhookId, (int) $row['webhook_id']);
        self::assertNull($row['api_token_id']);
        self::assertNull($row['scopes_json']);
        self::assertNull($row['revoked_at']);
        self::assertContains($id, array_map(static fn (array $c): int => (int) $c['id'], $repo->activeForInstall($installedId)));
    }
}
