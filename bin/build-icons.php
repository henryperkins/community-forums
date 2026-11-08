#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build src/Support/IconSet.php from resources/icons/icons.json and the pinned
 * Fluent System Icons sources in resources/icons/fluent/ (ADR 0060).
 *
 * The sources are third-party files, so only validated path data survives:
 * each file must be one <svg> whose viewBox matches its size and whose only
 * children are <path> elements with plain path commands. The partial echoes
 * that data raw, which is safe only because this build refuses anything else.
 */
$arguments = array_slice($argv, 1);
$check = $arguments === ['--check'];
if ($arguments !== [] && !$check) {
    fwrite(STDERR, "Usage: php bin/build-icons.php [--check]\n");
    exit(2);
}

$root = dirname(__DIR__);
$sourceDir = $root . '/resources/icons/fluent';
$target = $root . '/src/Support/IconSet.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$json = file_get_contents($root . '/resources/icons/icons.json');
if ($json === false) {
    $fail('Cannot read resources/icons/icons.json');
}
$manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
foreach (['source', 'version', 'license', 'icons'] as $key) {
    if (!isset($manifest[$key])) {
        $fail('icons.json is missing "' . $key . '".');
    }
}
if (!is_file($sourceDir . '/LICENSE')) {
    $fail('resources/icons/fluent/LICENSE is missing; the Fluent artwork ships under its MIT licence.');
}

const ICON_SIZES = [12, 16, 20, 24, 28, 32, 48];
const ICON_STYLES = ['regular', 'filled'];
const PATH_DATA = '/^[MmLlHhVvCcSsQqTtAaZz0-9eE.,\s+\-]+$/D';
const SVG_NS = 'http://www.w3.org/2000/svg';

/** @return list<array{d: string, rule?: string}> */
$readArtwork = static function (string $file, int $size) use ($fail): array {
    $svg = file_get_contents($file);
    if ($svg === false) {
        $fail('Cannot read ' . basename($file));
    }
    $document = new DOMDocument();
    if (!$document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS)) {
        $fail(basename($file) . ' is not well-formed SVG.');
    }
    $svgElement = $document->documentElement;
    if ($svgElement === null || $svgElement->namespaceURI !== SVG_NS || $svgElement->localName !== 'svg'
        || $svgElement->getAttribute('viewBox') !== '0 0 ' . $size . ' ' . $size) {
        $fail(basename($file) . ' must be an <svg> with viewBox "0 0 ' . $size . ' ' . $size . '".');
    }
    $paths = [];
    foreach ($svgElement->childNodes as $node) {
        if ($node instanceof DOMText && trim($node->data) === '') {
            continue;
        }
        if (!$node instanceof DOMElement || $node->namespaceURI !== SVG_NS || $node->localName !== 'path') {
            $fail(basename($file) . ' may contain only <path> elements.');
        }
        $path = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $value = $attribute->value;
            switch ($attribute->name) {
                case 'd':
                    if (preg_match(PATH_DATA, $value) !== 1) {
                        $fail(basename($file) . ' has path data outside the path-command alphabet.');
                    }
                    $path['d'] = trim((string) preg_replace('/\s+/', ' ', $value));
                    break;
                case 'fill-rule':
                case 'clip-rule':
                    if (!in_array($value, ['evenodd', 'nonzero'], true)) {
                        $fail(basename($file) . ' has an unknown ' . $attribute->name . '.');
                    }
                    if ($attribute->name === 'fill-rule') {
                        $path['rule'] = $value;
                    }
                    break;
                case 'fill':
                    break; // The partial paints with currentColor.
                default:
                    $fail(basename($file) . ' has an unexpected attribute: ' . $attribute->name);
            }
        }
        if (!isset($path['d'])) {
            $fail(basename($file) . ' has a <path> without data.');
        }
        $paths[] = $path;
    }
    if ($paths === []) {
        $fail(basename($file) . ' draws nothing.');
    }
    return $paths;
};

$quote = static fn (string $value): string => "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'"]) . "'";

$used = [];
$body = '';
foreach ($manifest['icons'] as $name => $icon) {
    if (!is_string($name) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name) !== 1) {
        $fail('Icon names are lowercase words joined by hyphens: ' . var_export($name, true));
    }
    $fluent = $icon['fluent'] ?? null;
    if (!is_string($fluent) || preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/D', $fluent) !== 1) {
        $fail($name . ' needs a Fluent icon slug such as "comment_add".');
    }
    $sizes = $icon['sizes'] ?? [];
    $styles = $icon['styles'] ?? ['regular'];
    if (!is_array($sizes) || $sizes === [] || array_diff($sizes, ICON_SIZES) !== [] || count(array_unique($sizes)) !== count($sizes)) {
        $fail($name . ' lists sizes outside ' . implode(', ', ICON_SIZES) . '.');
    }
    if (!is_array($styles) || $styles === [] || array_diff($styles, ICON_STYLES) !== [] || count(array_unique($styles)) !== count($styles)) {
        $fail($name . ' lists styles outside regular, filled.');
    }
    $body .= '        ' . $quote($name) . ' => [ // Fluent ' . $fluent . "\n";
    foreach ($styles as $style) {
        $body .= '            ' . $quote($style) . " => [\n";
        foreach ($sizes as $size) {
            $file = $fluent . '_' . $size . '_' . $style . '.svg';
            $used[$file] = true;
            if (!is_file($sourceDir . '/' . $file)) {
                $fail('Missing Fluent source resources/icons/fluent/' . $file);
            }
            $paths = array_map(
                static fn (array $path): string => '[\'d\' => ' . $quote($path['d'])
                    . (isset($path['rule']) ? ', \'rule\' => ' . $quote($path['rule']) : '') . ']',
                $readArtwork($sourceDir . '/' . $file, (int) $size),
            );
            $body .= '                ' . (int) $size . ' => [' . implode(', ', $paths) . "],\n";
        }
        $body .= "            ],\n";
    }
    $body .= "        ],\n";
}

foreach (glob($sourceDir . '/*.svg') ?: [] as $file) {
    if (!isset($used[basename($file)])) {
        $fail('Unreferenced Fluent source resources/icons/fluent/' . basename($file) . '; remove it or map it in icons.json.');
    }
}

$source = $manifest['source'] . ' ' . $manifest['version'];
$php = <<<PHP
<?php

// Generated by bin/build-icons.php; edit resources/icons/ instead.

declare(strict_types=1);

namespace App\Support;

/**
 * Fluent System Icons artwork for templates/partials/icon.php (ADR 0060):
 * {$source}, {$manifest['license']} licence (resources/icons/fluent/LICENSE).
 * Each forum icon name lists its styles, the first being the one drawn by
 * default, and the sizes it is drawn from. The build validated every path,
 * so the partial may print the data as-is. {@see Icons} picks the variant.
 */
final class IconSet
{
    public const SOURCE = {$quote($source)};

    /** @var array<string, array<string, array<int, list<array{d: string, rule?: string}>>>> */
    public const GLYPHS = [
{$body}    ];
}

PHP;

if ($check) {
    if (!is_file($target) || file_get_contents($target) !== $php) {
        fwrite(STDERR, "Icon set needs rebuilding: src/Support/IconSet.php\n");
        exit(1);
    }
    fwrite(STDOUT, "Icon set is current.\n");
    exit(0);
}
if (file_put_contents($target, $php) !== strlen($php)) {
    throw new RuntimeException('Cannot write src/Support/IconSet.php');
}
fwrite(STDOUT, 'Built icon set: ' . count($manifest['icons']) . " icons.\n");
