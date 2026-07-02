<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackageThemeRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\ThemeTokenPolicy;

/**
 * Builds a declarative theme package into a deterministic served stylesheet.
 */
final class ThemeBuildService
{
    public function __construct(
        private Database $db,
        private PackageThemeRepository $themes,
        private ManifestValidator $manifests,
        private ThemeAssetScanner $scanner,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /**
     * @param array<string,mixed> $install installed_packages row
     * @param array<string,mixed> $manifest decoded rb-manifest.v2 from a verified release
     * @return array<string,mixed>
     */
    public function ensureBuild(array $install, string $uid, array $manifest, ?int $actorId = null): array
    {
        $installedId = (int) $install['id'];
        $sourceDigest = (string) $install['digest'];
        $existing = $this->themes->findBuildFor($installedId, $sourceDigest);
        if ($existing !== null) {
            return $existing;
        }

        $version = is_string($manifest['version'] ?? null) ? $manifest['version'] : '';
        $theme = $this->manifests->validate($manifest, $uid, $version)->theme;
        if ($theme === null) {
            throw new PackagePolicyException('theme_missing', 'This package does not declare a theme.');
        }

        $scanned = [];
        $total = 0;
        foreach ($theme['assets'] as $asset) {
            $out = $this->scanner->scan($asset['name'], $asset['kind'], $asset['bytes']);
            $total += strlen($out['bytes']);
            if ($total > ThemeAssetScanner::MAX_TOTAL_BYTES) {
                throw new PackagePolicyException('theme_asset', 'Theme assets are too large after re-encoding.');
            }
            $scanned[$asset['name']] = $out;
        }

        $contrast = $this->assertContrast($theme['tokens'], $theme['dark_tokens']);
        $assetDigests = array_map(static fn (array $asset): string => $asset['digest'], $scanned);
        $css = self::emitCss($theme['tokens'], $theme['dark_tokens'], $assetDigests);
        $cssDigest = hash('sha256', $css);

        $buildId = $this->db->transaction(function () use ($install, $installedId, $sourceDigest, $theme, $scanned, $contrast, $css, $cssDigest, $actorId): int {
            $buildId = $this->themes->createBuild([
                'installed_package_id' => $installedId,
                'package_id' => (int) $install['package_id'],
                'release_id' => (int) $install['release_id'],
                'source_digest' => $sourceDigest,
                'token_schema_version' => $theme['schema_version'],
                'tokens_json' => json_encode(
                    ['tokens' => $theme['tokens'], 'dark_tokens' => $theme['dark_tokens']],
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
                'validation_json' => json_encode(['contrast' => $contrast], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'css' => $css,
                'css_digest' => $cssDigest,
                'built_by' => $actorId,
            ]);

            foreach ($scanned as $name => $asset) {
                $this->themes->addAsset($buildId, (string) $name, $asset['mime'], $asset['bytes'], $asset['digest']);
            }

            return $buildId;
        });

        $this->telemetry?->emit('theme.lifecycle', ['action' => 'build', 'package' => $uid, 'digest' => $cssDigest]);

        $build = $this->themes->findBuild($buildId);
        if ($build === null) {
            throw new \RuntimeException('Theme build row disappeared after creation.');
        }

        return $build;
    }

    /**
     * @param array<string,string> $tokens
     * @param array<string,string> $darkTokens
     * @return list<array{variant:string,fg:string,bg:string,ratio:float}>
     */
    private function assertContrast(array $tokens, array $darkTokens): array
    {
        $report = [];
        foreach (['light' => $tokens, 'dark' => array_replace($tokens, $darkTokens)] as $variant => $overrides) {
            $effective = array_replace(ThemeTokenPolicy::baseline($variant), array_filter(
                $overrides,
                static fn (string $token): bool => ThemeTokenPolicy::type($token) === 'color',
                ARRAY_FILTER_USE_KEY,
            ));
            foreach (ThemeTokenPolicy::contrastPairs() as $pair) {
                $ratio = self::contrastRatio($effective[$pair['fg']], $effective[$pair['bg']]);
                $report[] = [
                    'variant' => $variant,
                    'fg' => $pair['fg'],
                    'bg' => $pair['bg'],
                    'ratio' => round($ratio, 2),
                ];
                if ($ratio < $pair['min']) {
                    throw new PackagePolicyException('theme_contrast', sprintf(
                        'Contrast %s on %s is %.2f:1 in the %s variant; %.1f:1 is required.',
                        $pair['fg'],
                        $pair['bg'],
                        $ratio,
                        $variant,
                        $pair['min'],
                    ));
                }
            }
        }

        return $report;
    }

    /**
     * @param array<string,string> $tokens
     * @param array<string,string> $darkTokens
     * @param array<string,string> $assetDigests asset name to served digest
     */
    public static function emitCss(array $tokens, array $darkTokens, array $assetDigests): string
    {
        $emit = static function (array $set) use ($assetDigests): string {
            $out = '';
            foreach (array_keys(ThemeTokenPolicy::TOKENS) as $name) {
                if (!array_key_exists($name, $set)) {
                    continue;
                }
                $value = $set[$name];
                if (ThemeTokenPolicy::type($name) === 'asset') {
                    $value = 'url("/theme/asset/' . $assetDigests[$value] . '")';
                }
                $out .= $name . ':' . $value . ';';
            }

            return $out;
        };

        $css = ':root{' . $emit($tokens) . '}';
        if ($darkTokens !== []) {
            $dark = $emit($darkTokens);
            $css .= "\n" . '[data-theme="dark"]{' . $dark . '}';
            $css .= "\n" . '@media (prefers-color-scheme: dark){:root[data-theme="system"]{' . $dark . '}}';
        }

        return $css;
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $linear = array_map(
            static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            [
                hexdec(substr($hex, 0, 2)) / 255,
                hexdec(substr($hex, 2, 2)) / 255,
                hexdec(substr($hex, 4, 2)) / 255,
            ],
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
