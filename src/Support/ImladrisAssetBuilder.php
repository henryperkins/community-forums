<?php

declare(strict_types=1);

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Builds the small, CSP-safe runtime closure from the imported design system.
 * Preview code, documentation CSS, screenshots, and archived app snapshots are
 * deliberately outside the allowlist.
 */
final class ImladrisAssetBuilder
{
    /** @var list<string> */
    private const CSS_SOURCES = [
        'tokens/fonts.css',
        'tokens/colors.css',
        'tokens/typography.css',
        'tokens/spacing.css',
        'components.css',
    ];

    /** @var list<string> */
    private const MANAGED_DIRECTORIES = [
        'resources/imladris',
        'public/assets/fonts/imladris',
    ];

    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    /** @return list<string> */
    public function build(): array
    {
        $expected = $this->expectedFiles();
        $this->removeUnexpectedFiles($expected);

        foreach ($expected as $relative => $content) {
            $path = $this->path($relative);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create directory: ' . $directory);
            }
            if (file_put_contents($path, $content) === false) {
                throw new RuntimeException('Unable to write generated asset: ' . $path);
            }
        }

        return array_keys($expected);
    }

    /** @return list<string> */
    public function check(): array
    {
        $expected = $this->expectedFiles();
        $errors = [];

        foreach ($expected as $relative => $content) {
            $path = $this->path($relative);
            if (!is_file($path)) {
                $errors[] = 'Missing generated file: ' . $relative;
                continue;
            }
            if (file_get_contents($path) !== $content) {
                $errors[] = 'Generated file is stale: ' . $relative;
            }
        }

        foreach ($this->managedFiles() as $relative) {
            if (!array_key_exists($relative, $expected)) {
                $errors[] = 'Unexpected generated file: ' . $relative;
            }
        }

        sort($errors);
        return $errors;
    }

    public function applicationSurfaceDigest(): string
    {
        $baseline = $this->jsonFile($this->path('config/imladris-runtime-baseline.json'));
        $scope = $baseline['application_surface'] ?? null;
        if (!is_array($scope)) {
            throw new RuntimeException('The Imladris application baseline is missing application_surface.');
        }

        return $this->digestApplicationSurface($scope);
    }

    /** @return array<string,string> */
    private function expectedFiles(): array
    {
        $sourceRoot = $this->path('docs/design-system/imladris');
        $manifestPath = $sourceRoot . '/manifest.json';
        $manifest = $this->jsonFile($manifestPath);
        if (($manifest['unresolved_gaps'] ?? null) !== []) {
            throw new RuntimeException('The imported Imladris manifest has unresolved parity gaps.');
        }

        $contractRelative = 'docs/design-system/imladris/production-contract.json';
        $contractContent = $this->textFile($this->path($contractRelative));
        $productionContract = json_decode($contractContent, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($productionContract) || ($productionContract['unresolved_gaps'] ?? null) !== []) {
            throw new RuntimeException('The imported Imladris production contract has unresolved parity gaps.');
        }

        $applicationBaseline = $this->jsonFile($this->path('config/imladris-runtime-baseline.json'));
        $surface = $applicationBaseline['application_surface'] ?? null;
        if (!is_array($surface) || !is_string($surface['sha256'] ?? null)) {
            throw new RuntimeException('The Imladris application baseline is incomplete.');
        }
        $applicationDigest = $this->digestApplicationSurface($surface);
        if (!hash_equals($surface['sha256'], $applicationDigest)) {
            throw new RuntimeException(
                'Production presentation changed after Imladris reconciliation. '
                . 'Review the design-system contract before updating config/imladris-runtime-baseline.json. '
                . 'Current digest: ' . $applicationDigest,
            );
        }
        if (($productionContract['reconciled_through_commit'] ?? null)
            !== ($applicationBaseline['reconciled_through_commit'] ?? null)) {
            throw new RuntimeException('The Imladris production contract and application baseline audit commits differ.');
        }
        if (($productionContract['composer']['spec'] ?? null) !== ($applicationBaseline['composer_contract'] ?? null)) {
            throw new RuntimeException('The Imladris production contract and application baseline composer versions differ.');
        }
        $contractSurfaceSpecs = $productionContract['surface_specs'] ?? null;
        $baselineFiles = $surface['files'] ?? null;
        if (!is_array($contractSurfaceSpecs) || !is_array($baselineFiles)) {
            throw new RuntimeException('The Imladris production surface-spec contract is incomplete.');
        }
        foreach ($contractSurfaceSpecs as $surfaceSpec) {
            if (!is_string($surfaceSpec) || !in_array($surfaceSpec, $baselineFiles, true)) {
                throw new RuntimeException('The application baseline does not cover design-system surface spec: ' . (string) $surfaceSpec);
            }
        }

        $expected = [];
        $sections = [];
        $sourceHashes = [];

        foreach (self::CSS_SOURCES as $relative) {
            $sourcePath = $sourceRoot . '/' . $relative;
            $content = $this->textFile($sourcePath);
            $this->validateCssSource($relative, $content);
            $expected['resources/imladris/' . $relative] = $content;
            $sourceHashes[$relative] = hash('sha256', $content);

            $publicContent = $this->runtimeCss($relative, $content);
            $layer = $relative === 'components.css' ? 'imladris.components' : 'imladris.tokens';
            $sections[] = "/* Source: {$relative} */\n@layer {$layer} {\n{$publicContent}\n}\n";
        }

        $fontRoot = $sourceRoot . '/assets/fonts';
        if (!is_dir($fontRoot)) {
            throw new RuntimeException('Missing imported font directory: ' . $fontRoot);
        }
        $fontFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fontRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['woff2', 'txt'], true)) {
                continue;
            }
            $absolute = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($absolute, strlen(str_replace('\\', '/', $fontRoot))), '/');
            $content = (string) file_get_contents($file->getPathname());
            $fontFiles[] = $relative;
            $expected['resources/imladris/assets/fonts/' . $relative] = $content;
            $expected['public/assets/fonts/imladris/' . $relative] = $content;
        }
        sort($fontFiles);

        $header = "/* Generated from the allowlisted Imladris runtime sources.\n"
            . "   Run `composer build:imladris`; do not edit this file directly. */\n"
            . "@layer imladris.tokens, imladris.components;\n\n";
        $expected['public/assets/imladris.css'] = $header . implode("\n", $sections);

        $runtimeManifest = [
            'source' => 'docs/design-system/imladris',
            'inspected_commit' => $manifest['inspected_commit'] ?? null,
            'application_baseline' => [
                'reconciled_through_commit' => $applicationBaseline['reconciled_through_commit'] ?? null,
                'composer_contract' => $applicationBaseline['composer_contract'] ?? null,
                'surface_sha256' => $applicationDigest,
            ],
            'design_contract' => [
                'source' => $contractRelative,
                'sha256' => hash('sha256', $contractContent),
            ],
            'css_sources' => self::CSS_SOURCES,
            'source_sha256' => $sourceHashes,
            'font_files' => $fontFiles,
            'excluded' => [
                '_archive',
                '_ds_bundle.js',
                'components/doc.css',
                'feature-ui',
                'templates',
                'ui_kits',
            ],
            'runtime_filters' => [
                'tokens/spacing.css' => 'Reduced-motion timing declarations remain application-owned.',
            ],
        ];
        $expected['resources/imladris/manifest.json'] = json_encode(
            $runtimeManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";

        ksort($expected);
        return $expected;
    }

    private function validateCssSource(string $relative, string $content): void
    {
        if (preg_match('/https?:\/\//i', $content) === 1) {
            throw new RuntimeException($relative . ' contains a remote URL.');
        }
        if (preg_match('/--text-body\s*:\s*[0-9.]+(?:rem|px)\b/i', $content) === 1) {
            throw new RuntimeException($relative . ' reintroduces the --text-body color/size collision.');
        }
        if ($relative === 'tokens/typography.css'
            && preg_match('/--text-size-body\s*:\s*1\.0625rem\b/', $content) !== 1) {
            throw new RuntimeException('tokens/typography.css is missing --text-size-body.');
        }
    }

    private function runtimeCss(string $relative, string $content): string
    {
        if ($relative === 'tokens/fonts.css') {
            $content = str_replace('../assets/fonts/', 'fonts/imladris/', $content);
        }

        if ($relative === 'tokens/spacing.css') {
            $reducedMotion = <<<'CSS'
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
    }
}
CSS;
            $replacement = '/* Reduced-motion behavior remains application-owned at runtime. */';
            $content = str_replace($reducedMotion, $replacement, $content, $count);
            if ($count !== 1) {
                throw new RuntimeException('tokens/spacing.css reduced-motion contract changed; reconcile the runtime filter.');
            }
        }

        if (str_contains($content, '!important')) {
            throw new RuntimeException($relative . ' contains a runtime !important declaration that can invert layer priority.');
        }

        return $content;
    }

    /** @return array<string,mixed> */
    private function jsonFile(string $path): array
    {
        $value = json_decode($this->textFile($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Expected a JSON object: ' . $path);
        }
        return $value;
    }

    private function textFile(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('Missing Imladris source: ' . $path);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read Imladris source: ' . $path);
        }
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    /** @param array<string,mixed> $scope */
    private function digestApplicationSurface(array $scope): string
    {
        $extensions = $scope['extensions'] ?? null;
        $roots = $scope['roots'] ?? null;
        $individualFiles = $scope['files'] ?? null;
        $excluded = $scope['excluded'] ?? null;
        if (!is_array($extensions) || !is_array($roots) || !is_array($individualFiles) || !is_array($excluded)) {
            throw new RuntimeException('The Imladris application baseline scope is invalid.');
        }

        $allowedExtensions = array_fill_keys(array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $extensions,
        ), true);
        $excludedFiles = array_fill_keys(array_map(
            static fn (mixed $value): string => ltrim(str_replace('\\', '/', (string) $value), '/'),
            $excluded,
        ), true);
        $files = [];

        foreach ($individualFiles as $relative) {
            $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
            $this->assertSafeRelativePath($relative);
            $files[$relative] = true;
        }

        foreach ($roots as $relativeRoot) {
            $relativeRoot = rtrim(ltrim(str_replace('\\', '/', (string) $relativeRoot), '/'), '/');
            $this->assertSafeRelativePath($relativeRoot);
            $directory = $this->path($relativeRoot);
            if (!is_dir($directory)) {
                throw new RuntimeException('Missing application baseline root: ' . $relativeRoot);
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $extension = strtolower($file->getExtension());
                if (!isset($allowedExtensions[$extension])) {
                    continue;
                }
                $absolute = str_replace('\\', '/', $file->getPathname());
                $relative = ltrim(substr($absolute, strlen($this->root)), '/');
                if (!isset($excludedFiles[$relative])) {
                    $files[$relative] = true;
                }
            }
        }

        ksort($files);
        $entries = [];
        foreach (array_keys($files) as $relative) {
            $content = $this->textFile($this->path($relative));
            $entries[] = $relative . "\0" . hash('sha256', $content);
        }

        return hash('sha256', implode("\n", $entries) . "\n");
    }

    private function assertSafeRelativePath(string $relative): void
    {
        if ($relative === '' || str_starts_with($relative, '/') || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
            throw new RuntimeException('Unsafe application baseline path: ' . $relative);
        }
    }

    /** @param array<string,string> $expected */
    private function removeUnexpectedFiles(array $expected): void
    {
        foreach ($this->managedFiles() as $relative) {
            if (array_key_exists($relative, $expected)) {
                continue;
            }
            $path = $this->path($relative);
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Unable to remove stale generated file: ' . $path);
            }
        }
    }

    /** @return list<string> */
    private function managedFiles(): array
    {
        $files = [];
        foreach (self::MANAGED_DIRECTORIES as $relativeRoot) {
            $directory = $this->path($relativeRoot);
            if (!is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $absolute = str_replace('\\', '/', $file->getPathname());
                $files[] = ltrim(substr($absolute, strlen($this->root)), '/');
            }
        }
        if (is_file($this->path('public/assets/imladris.css'))) {
            $files[] = 'public/assets/imladris.css';
        }
        sort($files);
        return $files;
    }

    private function path(string $relative): string
    {
        return $this->root . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }
}
