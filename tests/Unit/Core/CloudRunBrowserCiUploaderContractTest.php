<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudRunBrowserCiUploaderContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_streaming_create_only_transport_and_credential_privacy_contracts(): void
    {
        $process = proc_open(['python3', '-B', self::ROOT . '/deploy/cloudrun/browser-ci/test_upload.py'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Ran 7 tests', $stderr);
        self::assertStringContainsString('OK', $stderr);
    }

    public function test_diagnostic_publication_preserves_test_status(): void
    {
        $build = json_decode((string) file_get_contents(self::ROOT . '/deploy/cloudrun/browser-ci/cloudbuild.json'),
            true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('package.py upload.py', $build['steps'][0]['args'][1]);
        $publish = $build['steps'][2]['args'][1];
        self::assertStringContainsString('"test_status":"see_provider_result"', $publish);
        self::assertStringContainsString('python3 /workspace/control/upload.py', $publish);
        self::assertStringContainsString('--outer-result /workspace/runner-exit.json', $publish);
        self::assertStringNotContainsString('gcloud storage', $publish);
        self::assertTrue($build['steps'][2]['allowFailure']);
        self::assertSame('test-result', $build['steps'][3]['id']);
    }
}
