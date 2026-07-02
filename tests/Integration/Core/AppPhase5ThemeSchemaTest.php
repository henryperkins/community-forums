<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackageThemeRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppPhase5ThemeSchemaTest extends TestCase
{
    public function test_theme_tables_match_documented_shape(): void
    {
        self::assertSame(
            ['id', 'installed_package_id', 'package_id', 'release_id', 'source_digest', 'token_schema_version', 'tokens_json', 'validation_json', 'css', 'css_digest', 'built_by', 'created_at'],
            $this->columns('package_theme_builds'),
        );
        self::assertSame(
            ['id', 'build_id', 'name', 'mime', 'bytes', 'byte_len', 'digest'],
            $this->columns('package_theme_assets'),
        );
        self::assertSame(
            ['id', 'active_build_id', 'lkg_build_id', 'activated_by', 'activated_at', 'updated_at'],
            $this->columns('theme_state'),
        );
    }

    public function test_theme_state_seed_row_exists_and_is_empty(): void
    {
        $row = $this->db->fetch('SELECT * FROM theme_state WHERE id = 1');

        self::assertNotNull($row);
        self::assertNull($row['active_build_id']);
        self::assertNull($row['lkg_build_id']);
    }

    public function test_package_history_enum_gains_theme_events(): void
    {
        $column = $this->db->fetch(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_history' AND COLUMN_NAME = 'event'",
        );

        self::assertStringContainsString("'theme_activate'", (string) $column['t']);
        self::assertStringContainsString("'theme_rollback'", (string) $column['t']);
        self::assertStringContainsString("'theme_deactivate'", (string) $column['t']);
    }

    public function test_repository_round_trips_a_build_with_assets_and_state(): void
    {
        $fixtures = $this->seedRegistryFixtureWithInstall();
        $repo = new PackageThemeRepository($this->db);
        $css = ':root{--accent:#8f3d12;}';
        $cssDigest = hash('sha256', $css);
        $buildId = $repo->createBuild([
            'installed_package_id' => $fixtures['installed_id'],
            'package_id' => $fixtures['package_id'],
            'release_id' => $fixtures['release_id'],
            'source_digest' => str_repeat('a', 64),
            'token_schema_version' => 1,
            'tokens_json' => '{"--accent":"#8f3d12"}',
            'validation_json' => '{"contrast":[]}',
            'css' => $css,
            'css_digest' => $cssDigest,
            'built_by' => null,
        ]);

        $assetDigest = hash('sha256', 'PNGBYTES');
        $repo->addAsset($buildId, 'parchment', 'image/png', 'PNGBYTES', $assetDigest);

        self::assertSame($buildId, (int) $repo->findBuildFor($fixtures['installed_id'], str_repeat('a', 64))['id']);
        self::assertSame('PNGBYTES', $repo->findAssetByDigest($assetDigest)['bytes']);
        self::assertCount(1, $repo->assetsFor($buildId));
        self::assertArrayNotHasKey('bytes', $repo->assetsFor($buildId)[0]);

        $repo->setState($buildId, null, null);
        self::assertSame($buildId, (int) $repo->state()['active_build_id']);
        self::assertSame('installed', $repo->findCssByDigest($cssDigest)['install_state']);
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $rows = $this->db->fetchAll(
            'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table],
        );

        return array_map(static fn (array $r): string => (string) $r['c'], $rows);
    }

    /** @return array{installed_id:int,package_id:int,release_id:int} */
    private function seedRegistryFixtureWithInstall(): array
    {
        $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
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

        return [
            'installed_id' => $installedId,
            'package_id' => $seeded['package_id'],
            'release_id' => $seeded['release_id'],
        ];
    }
}
