<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageThemeRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\ThemeAssetScanner;
use App\Service\Packages\ThemeBuildService;
use App\Service\Packages\ThemeStateService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppThemePackageTest extends TestCase
{
    private string $artifactDir;
    private int $fixtureCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = sys_get_temp_dir() . '/rb-theme-package-' . bin2hex(random_bytes(4));
        $this->makeAdmin();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->artifactDir)) {
            foreach (glob($this->artifactDir . '/*.json') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($this->artifactDir);
        }
        parent::tearDown();
    }

    public function test_no_served_theme_until_explicit_activation(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => true]);
        $this->installedTheme();

        $response = $this->get('/');

        $this->assertStatus(200, $response);
        self::assertStringNotContainsString('/theme/', $response->body());
    }

    public function test_activation_serves_immutable_digest_addressed_css(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => true]);
        $installedId = $this->installedTheme();
        $admin = $this->adminEntity();

        $build = $this->themeState()->activate($admin, 'password123', $installedId);

        $home = $this->get('/');
        $this->assertStatus(200, $home);
        $digest = $this->themeDigestFrom($home->body());
        self::assertSame((string) $build['css_digest'], $digest);

        $css = $this->get('/theme/' . $digest . '.css');
        $this->assertStatus(200, $css);
        self::assertSame('text/css; charset=UTF-8', $css->getHeader('content-type'));
        self::assertStringContainsString('immutable', (string) $css->getHeader('cache-control'));
        self::assertSame('"' . $digest . '"', $css->getHeader('etag'));
        self::assertSame($digest, hash('sha256', $css->body()));
        self::assertStringContainsString(':root{', $css->body());
    }

    public function test_safe_mode_serves_system_theme_while_theme_active(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => true]);
        $installedId = $this->installedTheme();
        $admin = $this->adminEntity();
        $service = $this->themeState();
        $build = $service->activate($admin, 'password123', $installedId);
        $digest = (string) $build['css_digest'];

        $service->setSafeMode($admin, true);

        $home = $this->get('/');
        $this->assertStatus(200, $home);
        self::assertStringNotContainsString('/theme/', $home->body());
        $this->assertStatus(404, $this->get('/theme/' . $digest . '.css'));

        try {
            $service->setSafeMode($admin, false);
            self::fail('expected reauth to exit safe mode');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        $service->setSafeMode($admin, false, 'password123');
        self::assertStringContainsString('/theme/' . $digest . '.css', $this->get('/')->body());
    }

    public function test_brand_css_suppresses_retro_preset_while_package_theme_active(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => true, 'branding' => true]);
        $settings = new SettingRepository($this->db);
        $settings->set('brand_theme_preset', 'retro');

        $retro = $this->get('/brand.css');
        $this->assertStatus(200, $retro);
        self::assertStringContainsString('--surface:#fff7dc', $retro->body());

        $this->themeState()->activate($this->adminEntity(), 'password123', $this->installedTheme());

        $suppressed = $this->get('/brand.css');
        $this->assertStatus(200, $suppressed);
        self::assertStringNotContainsString('--surface:#fff7dc', $suppressed->body());

        $settings->set('brand_color_primary', '#2f6fed');
        $withPrimary = $this->get('/brand.css');
        $this->assertStatus(200, $withPrimary);
        self::assertStringContainsString('--accent:#2f6fed', $withPrimary->body());
    }

    public function test_theme_asset_route_serves_neutralized_bytes(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => true]);
        $this->themeState()->activate($this->adminEntity(), 'password123', $this->installedTheme(['with_asset' => true]));

        $cssDigest = $this->themeDigestFrom($this->get('/')->body());
        $css = $this->get('/theme/' . $cssDigest . '.css');
        $this->assertStatus(200, $css);
        self::assertSame(1, preg_match('#/theme/asset/([a-f0-9]{64})#', $css->body(), $matches));

        $asset = $this->get('/theme/asset/' . $matches[1]);
        $this->assertStatus(200, $asset);
        self::assertSame('image/png', $asset->getHeader('content-type'));
        self::assertStringContainsString('immutable', (string) $asset->getHeader('cache-control'));
        self::assertSame('"' . $matches[1] . '"', $asset->getHeader('etag'));
        self::assertSame($matches[1], hash('sha256', $asset->body()));
    }

    public function test_package_themes_flag_gates_public_theme_routes(): void
    {
        $this->setFlags(['package_registry' => true, 'package_themes' => false]);

        $this->assertStatus(404, $this->get('/theme/' . str_repeat('a', 64) . '.css'));
        $this->assertStatus(404, $this->get('/theme/preview.css'));
        $this->assertStatus(404, $this->get('/theme/asset/' . str_repeat('a', 64)));
    }

    /** @param array{with_asset?:bool} $options */
    private function installedTheme(array $options = []): int
    {
        $this->fixtureCounter++;
        $uid = 'acme/theme-' . $this->fixtureCounter;
        $theme = [];
        if (($options['with_asset'] ?? false) === true) {
            $bytes = $this->pngBytes();
            $theme = [
                'tokens' => ['--accent' => '#8f3d12', '--surface-texture' => 'parchment'],
                'assets' => [[
                    'name' => 'parchment',
                    'kind' => 'png',
                    'sha256' => hash('sha256', $bytes),
                    'data_base64' => base64_encode($bytes),
                ]],
            ];
        }

        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate(), $this->artifactDir, [
            'source_id' => 'rb-test-theme-' . $this->fixtureCounter,
            'publisher_uid' => 'acme-theme-' . $this->fixtureCounter,
            'package_uid' => $uid,
            'release' => ['manifest' => ['theme' => $theme]],
        ]);
        $installedId = (new InstalledPackageRepository($this->db))->create([
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
            'digest' => $seeded['release_digest'],
            'source_registry_id' => $seeded['registry_id'],
            'publisher_id' => $seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => null,
        ]);
        (new InstalledPackageRepository($this->db))->setState($installedId, 'enabled');

        return $installedId;
    }

    private function adminEntity(): User
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $entity = (new UserRepository($this->db))->findEntity((int) $admin['id']);
        self::assertNotNull($entity);

        return $entity;
    }

    private function themeState(): ThemeStateService
    {
        return new ThemeStateService(
            $this->db,
            new PackageThemeRepository($this->db),
            new InstalledPackageRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageArtifactStore($this->artifactDir),
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            new ThemeBuildService($this->db, new PackageThemeRepository($this->db), new ManifestValidator(), new ThemeAssetScanner()),
            new WriteGate(),
            new ReauthGate(new PasswordHasher()),
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new PackageHistoryRepository($this->db),
        );
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    private function themeDigestFrom(string $html): string
    {
        self::assertSame(1, preg_match('#/theme/([a-f0-9]{64})\.css#', $html, $matches));

        return $matches[1];
    }
}
