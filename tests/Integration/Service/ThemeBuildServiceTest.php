<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackageThemeRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\ThemeAssetScanner;
use App\Service\Packages\ThemeBuildService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class ThemeBuildServiceTest extends TestCase
{
    private int $fixtureCounter = 0;

    public function test_build_persists_idempotently_and_enforces_contrast(): void
    {
        [$service, $install, $uid, $manifest] = $this->themeFixture();

        $build = $service->ensureBuild($install, $uid, $manifest, null);

        self::assertSame($build['id'], $service->ensureBuild($install, $uid, $manifest, null)['id']);
        self::assertSame(hash('sha256', (string) $build['css']), (string) $build['css_digest']);

        [$service2, $install2, $uid2, $manifest2] = $this->themeFixture([
            'theme' => ['tokens' => ['--text' => '#cccccc', '--surface' => '#dddddd']],
        ]);
        try {
            $service2->ensureBuild($install2, $uid2, $manifest2, null);
            self::fail('expected contrast refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('theme_contrast', $e->code);
        }
    }

    public function test_built_css_has_no_external_urls_and_assets_are_stored(): void
    {
        [$service, $install, $uid, $manifest] = $this->themeFixture(['with_asset' => true]);

        $build = $service->ensureBuild($install, $uid, $manifest, null);
        $css = (string) $build['css'];

        self::assertSame(0, preg_match('/url\(\s*(?!["\']?\/theme\/asset\/)/i', $css));
        self::assertStringNotContainsString('https://', $css);
        $assets = $this->themeRepo()->assetsFor((int) $build['id']);
        self::assertCount(1, $assets);
        self::assertNotNull($this->themeRepo()->findAssetByDigest((string) $assets[0]['digest']));
    }

    /**
     * @param array{theme?:array<string,mixed>,with_asset?:bool} $options
     * @return array{0:ThemeBuildService,1:array<string,mixed>,2:string,3:array<string,mixed>}
     */
    private function themeFixture(array $options = []): array
    {
        $this->fixtureCounter++;
        $uid = 'acme/theme-' . $this->fixtureCounter;
        $theme = $options['theme'] ?? [];
        if (($options['with_asset'] ?? false) === true) {
            $bytes = $this->pngBytes();
            $theme = array_replace($theme, [
                'tokens' => ['--accent' => '#8f3d12', '--surface-texture' => 'parchment'],
                'assets' => [[
                    'name' => 'parchment',
                    'kind' => 'png',
                    'sha256' => hash('sha256', $bytes),
                    'data_base64' => base64_encode($bytes),
                ]],
            ]);
        }

        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate(), null, [
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
        $install = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertNotNull($install);
        $release = json_decode((string) $seeded['release_document'], true, 512, JSON_THROW_ON_ERROR);

        return [
            new ThemeBuildService(
                $this->db,
                $this->themeRepo(),
                new ManifestValidator(),
                new ThemeAssetScanner(),
            ),
            $install,
            $uid,
            $release['manifest'],
        ];
    }

    private function themeRepo(): PackageThemeRepository
    {
        return new PackageThemeRepository($this->db);
    }
}
