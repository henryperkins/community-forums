<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Security\Packages\ThemeTokenPolicy;
use PHPUnit\Framework\TestCase;

final class ThemeBaselineFidelityTest extends TestCase
{
    public function test_policy_baselines_match_app_css(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../../public/assets/app.css');
        foreach (['light' => ':root', 'dark' => '[data-theme="dark"]'] as $variant => $selector) {
            $block = $this->block($css, $selector);
            foreach (ThemeTokenPolicy::baseline($variant) as $token => $value) {
                if ($variant === 'dark' && !str_contains($block, $token . ':')) {
                    continue;
                }
                if (preg_match('/' . preg_quote($token, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\b/', $block, $m) === 1) {
                    self::assertSame(strtolower($m[1]), $value, "$variant $token");
                }
            }
        }
        self::assertStringContainsString('background-image: var(--surface-texture, none)', $css);
    }

    private function block(string $css, string $selector): string
    {
        $start = strpos($css, $selector);
        self::assertNotFalse($start, $selector);
        $open = strpos($css, '{', $start);
        $close = strpos($css, '}', (int) $open);

        return substr($css, (int) $open, (int) $close - (int) $open);
    }
}
