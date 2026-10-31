<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Security\Packages\ThemeTokenPolicy;
use PHPUnit\Framework\TestCase;

final class ThemeBaselineFidelityTest extends TestCase
{
    public function test_policy_baselines_match_the_generated_imladris_runtime(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../../public/assets/imladris.css');
        $root = $this->declarations($this->block($css, ':root'));
        $checked = 0;
        foreach (['light' => ':root', 'dark' => '[data-theme="dark"]'] as $variant => $selector) {
            $block = $this->block($css, $selector);
            $scope = $variant === 'dark' ? array_replace($root, $this->declarations($block)) : $root;
            foreach (ThemeTokenPolicy::baseline($variant) as $token => $value) {
                if ($variant === 'dark' && !str_contains($block, $token . ':')) {
                    continue;
                }
                // Semantic roles point at primitives (var(--parchment-100)), so
                // resolve the chain; translucent washes are flattened in the
                // policy for contrast arithmetic and are not compared here.
                $resolved = $this->resolve($scope[$token] ?? '', $scope);
                if (preg_match('/\A#[0-9a-fA-F]{6}\z/', $resolved) === 1) {
                    self::assertSame(strtolower($resolved), $value, "$variant $token");
                    $checked++;
                }
            }
        }
        // Every opaque role the policy shares with the shell is verified, the
        // semantic surfaces included (ADR 0054).
        self::assertGreaterThanOrEqual(30, $checked);
        $application = (string) file_get_contents(__DIR__ . '/../../../public/assets/app.css');
        self::assertStringContainsString('background-image: var(--surface-texture, none)', $application);
    }

    /** @return array<string,string> */
    private function declarations(string $block): array
    {
        preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $match) {
            $out[$match[1]] = trim($match[2]);
        }
        return $out;
    }

    /** @param array<string,string> $scope */
    private function resolve(string $value, array $scope): string
    {
        for ($depth = 0; $depth < 8 && preg_match('/\Avar\((--[a-z0-9-]+)\)\z/', $value, $m) === 1; $depth++) {
            $value = trim($scope[$m[1]] ?? '');
        }
        return $value;
    }

    private function block(string $css, string $selector): string
    {
        // The token rule itself: the selector alone at the start of a rule,
        // not the first compound selector that merely begins with it.
        $found = preg_match('/(?:^|[}\s])' . preg_quote($selector, '/') . '\s*\{/m', $css, $match, PREG_OFFSET_CAPTURE);
        self::assertSame(1, $found, $selector);
        $open = strpos($css, '{', (int) $match[0][1]);
        $close = strpos($css, '}', (int) $open);

        return substr($css, (int) $open, (int) $close - (int) $open);
    }
}
