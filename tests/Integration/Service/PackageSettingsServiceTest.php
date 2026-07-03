<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\Packages\PackageSettingsService;
use App\Service\SecretVault;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageSettingsServiceTest extends TestCase
{
    private User $admin;
    /** @var array<string,mixed> */
    private array $seeded;
    private int $installedId;

    protected function setUp(): void
    {
        parent::setUp();

        $root = SigningHarness::generate();
        $this->seeded = RegistryFixtures::seed($this->db, $root, null, [
            'type' => 'remote_app',
            'package_uid' => 'acme/sync-app',
            'name' => 'Sync App',
            'trust_class' => 'reviewed_declarative',
            'release' => ['manifest' => ['settings_schema' => ['fields' => [
                ['key' => 'api_key', 'type' => 'string',  'label' => 'API key', 'required' => true, 'secret' => true],
                ['key' => 'mode',    'type' => 'select',  'label' => 'Mode',    'options' => ['light', 'dark']],
                ['key' => 'notify',  'type' => 'boolean', 'label' => 'Notify'],
            ]]]],
        ]);

        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $this->admin = $admin;

        $this->installedId = (new InstalledPackageRepository($this->db))->create([
            'package_id' => (int) $this->seeded['package_id'],
            'release_id' => (int) $this->seeded['release_id'],
            'digest' => (string) $this->seeded['release_digest'],
            'source_registry_id' => (int) $this->seeded['registry_id'],
            'publisher_id' => (int) $this->seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => null,
            'compat_max' => null,
            'installed_by' => $this->admin->id(),
        ]);
    }

    private function service(bool $secretsEnabled = true): PackageSettingsService
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => $secretsEnabled]);
        $flags = new FeatureFlags(new SettingRepository($this->db));

        return new PackageSettingsService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackageSettingsRepository($this->db),
            new SecretVault(
                $this->db,
                new ServiceSecretRepository($this->db),
                new SecretBox(str_repeat('a', 64)),
                new ModerationLogRepository($this->db),
                $flags,
                $this->config,
            ),
            new ManifestValidator(),
            new PackageHistoryRepository($this->db),
            new ModerationLogRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            $flags,
            $this->config,
        );
    }

    private function revealVault(): SecretVault
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => true]);
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    public function test_secret_setting_persists_only_a_ref_never_plaintext(): void
    {
        $this->service()->save($this->admin, 'password123', $this->installedId, [
            'api_key' => 'sk-live-123', 'mode' => 'dark', 'notify' => '1',
        ]);

        $row = (new InstalledPackageSettingsRepository($this->db))->find($this->installedId, 'api_key');
        self::assertNotNull($row);
        self::assertNull($row['value_json']);
        self::assertSame(1, (int) $row['is_secret']);
        self::assertStringStartsWith('svcsec_', (string) $row['secret_ref']);
        self::assertStringNotContainsString('sk-live-123', json_encode($row));

        $summary = (string) (new InstalledPackageRepository($this->db))->find($this->installedId)['settings_json'];
        self::assertStringNotContainsString('sk-live-123', $summary);

        self::assertSame('sk-live-123', $this->revealVault()->reveal((string) $row['secret_ref']));

        $describe = $this->service()->describe($this->installedId);
        self::assertTrue($describe['has_secret']['api_key']);
        self::assertSame('dark', $describe['values']['mode']);
        self::assertTrue($describe['values']['notify']);
        self::assertStringNotContainsString('sk-live-123', json_encode($describe));
    }

    public function test_secret_write_fails_closed_when_vault_disabled(): void
    {
        try {
            $this->service(false)->save($this->admin, 'password123', $this->installedId, ['api_key' => 'sk-x']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringNotContainsString('sk-x', json_encode($e->old));
        }

        self::assertNull((new InstalledPackageSettingsRepository($this->db))->find($this->installedId, 'api_key'));
    }

    public function test_secret_write_requires_reauth(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->save($this->admin, 'wrong-password', $this->installedId, ['api_key' => 'sk-y']);
    }

    public function test_required_secret_with_no_existing_value_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->save($this->admin, '', $this->installedId, ['mode' => 'light']);
    }
}
