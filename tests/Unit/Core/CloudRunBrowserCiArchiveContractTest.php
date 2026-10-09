<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudRunBrowserCiArchiveContractTest extends TestCase
{
    public function test_untrusted_archive_limits_and_data_only_verification(): void
    {
        $root = __DIR__ . '/../../..';
        $process = proc_open(['python3', '-B', $root . '/deploy/cloudrun/browser-ci/test_enforce.py'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('', $stdout);
        self::assertStringContainsString('OK', $stderr);
    }
}
