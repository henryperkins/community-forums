<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CapabilityCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Inc 6 follow-up: the literal-vs-catalogue invariant. Any quoted
 * `core.<area>.<action>` string anywhere in src/ must name a catalogued
 * capability — a typo'd key would otherwise fail-dark under enforce
 * (deny everyone, including admins) with no CI signal.
 */
final class CapabilityLiteralsTest extends TestCase
{
    public function test_scanner_catches_a_planted_typo(): void
    {
        $source = <<<'PHP'
        <?php
        $a = 'core.thread.lock';
        $b = "core.thred.lock";
        $prefix = 'core.';
        PHP;

        $found = self::extractCapabilityLiterals($source);
        self::assertContains('core.thread.lock', $found);
        self::assertContains('core.thred.lock', $found);
        self::assertNotContains('core.', $found, 'bare prefixes are not keys');
    }

    public function test_every_capability_literal_in_src_is_catalogued(): void
    {
        $root = dirname(__DIR__, 3) . '/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        $seen = 0;
        $bad = [];
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (self::extractCapabilityLiterals((string) file_get_contents($file->getPathname())) as $key) {
                $seen++;
                if (!CapabilityCatalog::has($key)) {
                    $bad[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $key;
                }
            }
        }

        self::assertGreaterThan(100, $seen, 'scanner regression: expected to find the definitional tables');
        self::assertSame([], $bad, "uncatalogued capability literals in src/:\n" . implode("\n", $bad));
    }

    /** @return list<string> every quoted core.<segment>.<segment>[...] literal in the source */
    private static function extractCapabilityLiterals(string $source): array
    {
        preg_match_all('/[\'"](core\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'"]/', $source, $m);

        return $m[1];
    }
}
