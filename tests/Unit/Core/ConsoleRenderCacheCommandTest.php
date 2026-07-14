<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class ConsoleRenderCacheCommandTest extends TestCase
{
    public function test_console_exposes_the_render_cache_repair_contract(): void
    {
        $console = (string) file_get_contents(dirname(__DIR__, 3) . '/bin/console');

        self::assertStringContainsString("case 'repair:render-cache':", $console);
        self::assertStringContainsString("'--dry-run'", $console);
        self::assertStringContainsString("'--batch='", $console);
        self::assertStringContainsString('MarkdownCacheRepairService', $console);
        self::assertStringContainsString(
            'repair:render-cache [--dry-run] [--batch=500]',
            $console,
        );
    }
}
