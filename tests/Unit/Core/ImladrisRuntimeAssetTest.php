<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FeatureFlags;
use PHPUnit\Framework\TestCase;

final class ImladrisRuntimeAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources(): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(self::ROOT . '/bin/build-imladris-assets.php')
            . ' --check';
        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertFileExists(self::ROOT . '/public/assets/imladris.css');

        $css = (string) file_get_contents(self::ROOT . '/public/assets/imladris.css');
        self::assertStringContainsString('Generated from the allowlisted Imladris runtime sources', $css);
        self::assertStringContainsString('@font-face', $css);
        self::assertMatchesRegularExpression('/--text-body\s*:\s*var\(--ink-700\)/', $css);
        self::assertMatchesRegularExpression('/--text-size-body\s*:\s*1\.0625rem/', $css);
        self::assertDoesNotMatchRegularExpression('/https?:\/\//i', $css);
        self::assertStringNotContainsString('!important', $css);
        self::assertStringNotContainsString('animation-duration: 0.001ms', $css);
        self::assertStringNotContainsString('/* Source: _archive/', $css);
        self::assertStringNotContainsString('components/doc.css', $css);
    }

    public function test_production_contract_classifies_every_declared_feature_flag(): void
    {
        $path = self::ROOT . '/docs/design-system/imladris/production-contract.json';
        self::assertFileExists($path);

        $contract = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $contract['unresolved_gaps'] ?? null);

        $classified = array_values(array_unique(array_merge(
            $contract['flags']['default_on'] ?? [],
            $contract['flags']['implemented_dark'] ?? [],
            $contract['flags']['reserved_dark'] ?? [],
        )));
        $declared = array_keys(FeatureFlags::defaults());
        sort($classified);
        sort($declared);

        self::assertSame($declared, $classified);
    }

    public function test_imported_composer_contract_is_current_and_has_no_superseded_anatomy(): void
    {
        $composer = (string) file_get_contents(
            self::ROOT . '/docs/design-system/imladris/components/forum/Composer.jsx',
        );

        self::assertStringContainsString('composer-shell', $composer);
        self::assertStringContainsString('composer-box', $composer);
        self::assertStringContainsString('composer-upload-tray', $composer);
        self::assertStringNotContainsString('Posting as', $composer);
        self::assertStringNotContainsString('className="composer-id"', $composer);
    }

    public function test_reviewed_application_baseline_covers_forum_presentation_and_composer_contracts(): void
    {
        $path = self::ROOT . '/config/imladris-runtime-baseline.json';
        self::assertFileExists($path);

        $baseline = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('6d81da590a12bd09bb8d0e282c042aa03d755a94', $baseline['reconciled_through_commit'] ?? null);
        self::assertSame('COMPOSER.md v0.8', $baseline['composer_contract'] ?? null);
        self::assertContains('templates', $baseline['application_surface']['roots'] ?? []);
        self::assertContains('public/assets', $baseline['application_surface']['roots'] ?? []);
        self::assertContains('USER.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('ADMIN.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('COMMUNITY.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('COMPOSER.md', $baseline['application_surface']['files'] ?? []);
        self::assertContains('src/Core/FeatureFlags.php', $baseline['application_surface']['files'] ?? []);
        self::assertContains('public/assets/imladris.css', $baseline['application_surface']['excluded'] ?? []);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $baseline['application_surface']['sha256'] ?? '');

        $contractPath = self::ROOT . '/docs/design-system/imladris/production-contract.json';
        $contract = json_decode((string) file_get_contents($contractPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($baseline['reconciled_through_commit'], $contract['reconciled_through_commit'] ?? null);
        self::assertSame($baseline['composer_contract'], $contract['composer']['spec'] ?? null);
        self::assertSame(
            ['USER.md', 'ADMIN.md', 'COMMUNITY.md', 'COMPOSER.md'],
            $contract['surface_specs'] ?? null,
        );

        $runtime = json_decode(
            (string) file_get_contents(self::ROOT . '/resources/imladris/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('docs/design-system/imladris/production-contract.json', $runtime['design_contract']['source'] ?? null);
    }

    public function test_application_css_does_not_redeclare_design_system_foundations(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');

        self::assertDoesNotMatchRegularExpression('/\:root\s*\{[^}]*--parchment-50\s*:/s', $css);
        self::assertStringContainsString('font-size: var(--text-size-body)', $css);
        self::assertStringContainsString('background-image: var(--surface-texture, none)', $css);
    }

    public function test_every_required_runtime_variable_has_a_definition(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/imladris.css')
            . "\n"
            . (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $definitions);
        $defined = array_fill_keys($definitions[1], true);

        preg_match_all('/var\((--[a-z0-9-]+)(\s*,[^)]*)?\)/i', $css, $uses, PREG_SET_ORDER);
        $missing = [];
        foreach ($uses as $use) {
            if (isset($defined[$use[1]]) || ($use[2] ?? '') !== '') {
                continue;
            }
            $missing[$use[1]] = true;
        }

        self::assertSame([], array_keys($missing));
    }
}
