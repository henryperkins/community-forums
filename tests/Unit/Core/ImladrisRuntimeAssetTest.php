<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FeatureFlags;
use App\Support\ImladrisAssetBuilder;
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

    public function test_application_quiet_thread_rows_reset_design_system_hover_motion(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/app.css');
        self::assertSame(
            1,
            preg_match('/\.thread-row:hover\s*\{(?<declarations>[^}]*)\}/', $css, $matches),
            'The application quiet-row hover rule is missing.',
        );
        self::assertMatchesRegularExpression('/\btransform\s*:\s*none\s*;?/', $matches['declarations']);
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

    public function test_asset_builder_filters_spacing_contract_from_a_crlf_checkout(): void
    {
        $root = $this->makeAssetBuilderFixture(useCrlfTextSources: true);

        try {
            $class = 'Tests\\Unit\\Core\\CrlfFixture\\ImladrisAssetBuilder';
            if (!class_exists($class, false)) {
                $source = (string) file_get_contents(self::ROOT . '/src/Support/ImladrisAssetBuilder.php');
                $source = (string) preg_replace('/^<\?php\s*/', '', $source, 1);
                $source = str_replace(
                    'namespace App\\Support;',
                    'namespace Tests\\Unit\\Core\\CrlfFixture;',
                    $source,
                );
                $source = str_replace(["\r\n", "\r"], "\n", $source);
                eval(str_replace("\n", "\r\n", $source));
            }

            try {
                /** @var object{build:callable():list<string>} $builder */
                $builder = new $class($root);
                $files = $builder->build();
                $error = null;
            } catch (\RuntimeException $exception) {
                $files = [];
                $error = $exception->getMessage();
            }

            self::assertNull($error, (string) $error);
            self::assertContains('public/assets/imladris.css', $files);
            self::assertStringNotContainsString(
                'animation-duration: 0.001ms',
                (string) file_get_contents($root . '/public/assets/imladris.css'),
            );
            self::assertSame(
                "line one\nline two\n",
                file_get_contents($root . '/public/assets/fonts/imladris/LICENSES/test.txt'),
            );
            self::assertSame(
                "\x00\x01\r\n\x02\xff",
                file_get_contents($root . '/public/assets/fonts/imladris/test.woff2'),
            );
        } finally {
            $this->removeFixtureDirectory($root);
        }
    }

    public function test_asset_check_normalizes_text_outputs_but_keeps_fonts_byte_exact(): void
    {
        $root = $this->makeAssetBuilderFixture();

        try {
            $builder = new ImladrisAssetBuilder($root);
            $files = $builder->build();

            foreach ($files as $relative) {
                if (!in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['css', 'json', 'txt'], true)) {
                    continue;
                }
                $path = $root . '/' . $relative;
                $content = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($path));
                file_put_contents($path, str_replace("\n", "\r\n", $content));
            }

            self::assertSame([], $builder->check());

            $font = $root . '/public/assets/fonts/imladris/test.woff2';
            file_put_contents($font, (string) file_get_contents($font) . "\x03");
            self::assertContains(
                'Generated file is stale: public/assets/fonts/imladris/test.woff2',
                $builder->check(),
            );
        } finally {
            $this->removeFixtureDirectory($root);
        }
    }

    public function test_design_tool_uploads_do_not_publish_browser_captures(): void
    {
        $uploadRoot = 'docs/design-system/imladris/uploads';
        $allowed = [
            $uploadRoot . '/359C3D62-2E24-4AEC-B0AB-BF886AFBC174.png',
            $uploadRoot . '/577F8AEF-DE44-4290-BBFE-C5F94AF207C2.png',
            $uploadRoot . '/5EF4ED15-812F-4EC1-B78A-0DA477B2AF75.png',
            $uploadRoot . '/621F9E9A-DC24-4EDE-A9D9-C7039CF04EA4.png',
            $uploadRoot . '/IMG_0209.png',
        ];
        $command = 'git -C ' . escapeshellarg(self::ROOT)
            . ' ls-files -- ' . escapeshellarg($uploadRoot);
        exec($command, $tracked, $status);
        self::assertSame(0, $status, 'Unable to inspect tracked design-tool uploads.');

        $unexpected = array_values(array_filter(
            array_diff($tracked, $allowed),
            static fn (string $relative): bool => is_file(self::ROOT . '/' . $relative),
        ));
        sort($unexpected);
        self::assertSame([], $unexpected, 'Only explicitly reviewed upload assets may be tracked.');

        $gitignore = (string) file_get_contents(self::ROOT . '/.gitignore');
        self::assertStringContainsString(
            '/docs/design-system/imladris/uploads/',
            $gitignore,
            'Design-tool uploads must be ignored by default; add reviewed assets explicitly with git add -f.',
        );
    }

    private function makeAssetBuilderFixture(bool $useCrlfTextSources = false): string
    {
        $root = sys_get_temp_dir() . '/rb-imladris-eol-' . bin2hex(random_bytes(6));
        $files = [
            'docs/design-system/imladris/manifest.json' => json_encode([
                'unresolved_gaps' => [],
                'inspected_commit' => 'fixture',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'docs/design-system/imladris/production-contract.json' => json_encode([
                'unresolved_gaps' => [],
                'reconciled_through_commit' => 'fixture',
                'composer' => ['spec' => 'fixture'],
                'surface_specs' => [],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'config/imladris-runtime-baseline.json' => json_encode([
                'reconciled_through_commit' => 'fixture',
                'composer_contract' => 'fixture',
                'application_surface' => [
                    'roots' => [],
                    'files' => [],
                    'extensions' => [],
                    'excluded' => [],
                    'sha256' => hash('sha256', "\n"),
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
            'docs/design-system/imladris/tokens/fonts.css' => "@font-face { src: url('../assets/fonts/test.woff2'); }\n",
            'docs/design-system/imladris/tokens/colors.css' => ":root { --ink-700: #222; }\n",
            'docs/design-system/imladris/tokens/typography.css' => ":root { --text-size-body: 1.0625rem; }\n",
            'docs/design-system/imladris/tokens/spacing.css' => <<<'CSS'
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
    }
}
CSS,
            'docs/design-system/imladris/components.css' => ".thread-row { display: flex; }\n",
            'docs/design-system/imladris/assets/fonts/LICENSES/test.txt' => "line one\nline two\n",
            'docs/design-system/imladris/assets/fonts/test.woff2' => "\x00\x01\r\n\x02\xff",
        ];

        foreach ($files as $relative => $content) {
            $path = $root . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            if ($useCrlfTextSources
                && in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), ['css', 'json', 'txt'], true)) {
                $content = str_replace(["\r\n", "\r"], "\n", $content);
                $content = str_replace("\n", "\r\n", $content);
            }
            file_put_contents($path, $content);
        }

        return $root;
    }

    private function removeFixtureDirectory(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($root);
    }
}
