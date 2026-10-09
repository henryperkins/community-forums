<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudRunBrowserCiLauncherContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_real_parent_enforcement_and_provider_boundary_contracts(): void
    {
        $process = proc_open(['python3', '-B', __DIR__ . '/cloud_run_browser_ci_launcher_contracts.py'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::ROOT);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Ran 12 tests', $stderr);
        self::assertStringContainsString('OK', $stderr);
    }

    public function test_parent_fetches_only_reviewed_controls_and_runs_without_branch_or_docker(): void
    {
        $build = json_decode((string) file_get_contents(self::ROOT . '/deploy/cloudrun/browser-ci/cloudbuild.json'),
            true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('/retroboards-browser-verifier@', $build['serviceAccount']);
        self::assertSame('6300s', $build['timeout']);
        self::assertSame('rising-woods-449718-v6-retroboards-browser-verification', $build['substitutions']['_VERIFY_BUCKET']);
        self::assertCount(2, $build['steps']);
        $source = $build['steps'][0]['args'][1];
        self::assertStringContainsString('launcher.py child-cloudbuild.json enforce.py upload.py', $source);
        self::assertStringContainsString('git fetch -q --depth=1 origin "$$CI_CONTROL_SHA"', $source);
        self::assertStringNotContainsString('CI_SOURCE_SHA', $source);
        self::assertStringNotContainsString('git checkout', $source);
        self::assertStringNotContainsString('/workspace/source', $source);
        $encoded = json_encode($build, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('docker.sock', $encoded);
        self::assertStringNotContainsString('cloud-builders/docker', $encoded);
        self::assertSame('python3', $build['steps'][1]['entrypoint']);
        self::assertSame(['-I', '/workspace/control/launcher.py'], array_slice($build['steps'][1]['args'], 0, 2));
    }
}
