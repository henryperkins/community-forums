<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudRunBrowserCiContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private string $scratch;

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/rb-browser-ci-contract-' . bin2hex(random_bytes(6));
        mkdir($this->scratch, 0700);
    }

    protected function tearDown(): void
    {
        $this->remove($this->scratch);
    }

    public function test_source_step_rejects_non_exact_shas_before_any_fetch_or_shell_evaluation(): void
    {
        $step = $this->build()['steps'][0];
        $script = str_replace('$$', '$', $step['args'][1]);
        $sentinel = $this->scratch . '/unexpected-shell-evaluation';
        foreach (['main', 'abc', str_repeat('a', 40) . '; touch ' . $sentinel, '$(touch ' . $sentinel . ')'] as $sha) {
            $result = $this->command(['bash', '-c', $script], [
                'CI_SOURCE_SHA' => $sha,
                'CI_CONTROL_SHA' => str_repeat('b', 40),
            ]);
            self::assertSame(2, $result['exit'], $result['stderr']);
            self::assertStringContainsString('Exact source and trusted control commit SHAs', $result['stderr']);
            self::assertFileDoesNotExist($sentinel);
        }
    }

    public function test_final_build_step_requires_boolean_success_after_artifact_publication(): void
    {
        $steps = $this->build()['steps'];
        self::assertTrue($steps[1]['allowFailure']);
        self::assertSame('publish-evidence', $steps[2]['id']);
        self::assertSame('enforce-result', $steps[3]['id']);
        $path = $this->scratch . '/result.json';
        // Each case executes the exact final-step Python command, not a duplicate predicate.
        foreach ([[true, 0], [false, 1], ['true', 1], [null, 1]] as [$passed, $expected]) {
            file_put_contents($path, json_encode(['passed' => $passed], JSON_THROW_ON_ERROR));
            $command = ['python3', ...$steps[3]['args']];
            $command[count($command) - 1] = $path;
            $result = $this->command($command);
            self::assertSame($expected, $result['exit'], $result['stderr']);
        }
    }

    public function test_packager_redacts_private_fields_excludes_symlinks_and_preserves_failure(): void
    {
        $source = $this->scratch . '/source';
        $evidence = $source . '/docs/evidence/browser';
        mkdir($evidence, 0700, true);
        file_put_contents($evidence . '/synthetic.json', json_encode([
            'config' => ['webServer' => ['env' => ['PRIVATE_CREDENTIAL' => 'private-env-value']]],
            'headers' => ['Authorization' => 'private-header-value'],
            'cookies' => [['value' => 'private-cookie-value']],
            'api_token' => 'private-token-value',
            'has_password' => true,
            'status' => 200,
        ], JSON_THROW_ON_ERROR));
        file_put_contents($evidence . '/synthetic.png', "\x89PNG\r\n\x1a\nsynthetic-fixture");
        file_put_contents($evidence . '/raw.log', 'private-raw-log');
        $outside = $this->scratch . '/outside.json';
        file_put_contents($outside, '{"outside":"private-outside-value"}');
        symlink($outside, $evidence . '/outside.json');
        $output = $this->scratch . '/output';
        $result = $this->command(['python3', self::ROOT . '/deploy/cloudrun/browser-ci/package.py',
            '--source', $source, '--output', $output, '--exit-code', '1', '--stage', 'uploads']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        $record = json_decode((string) file_get_contents($output . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($record['passed']);
        self::assertSame(1, $record['exit_code']);
        self::assertSame('uploads', $record['last_stage']);
        self::assertSame(['browser/synthetic.json', 'browser/synthetic.png'], $record['artifacts']);
        $inspect = $this->command(['python3', '-c',
            'import sys,tarfile; a=tarfile.open(sys.argv[1]); print(a.extractfile("browser/synthetic.json").read().decode())',
            $output . '/evidence.tar.gz']);
        self::assertSame(0, $inspect['exit'], $inspect['stderr']);
        self::assertStringNotContainsString('private-', $inspect['stdout']);
        self::assertSame(['config' => ['webServer' => []], 'has_password' => true, 'status' => 200],
            json_decode($inspect['stdout'], true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_configuration_dry_run_has_only_ci_permissions_and_rejects_invalid_control(): void
    {
        $script = self::ROOT . '/deploy/cloudrun/browser-ci/configure.py';
        $result = $this->command(['python3', $script, '--control-sha', str_repeat('a', 40)]);
        self::assertSame(0, $result['exit'], $result['stderr']);
        $config = json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['roles/logging.logWriter'], $config['plan']['project_roles']);
        self::assertSame('roles/storage.objectCreator', $config['plan']['bucket_role']);
        self::assertSame(14, $config['plan']['artifact_expiry_days']);
        self::assertSame(str_repeat('a', 40), $config['build']['substitutions']['_CONTROL_SHA']);
        self::assertStringNotContainsString('retroboards-app@', $result['stdout']);
        $invalid = $this->command(['python3', $script, '--control-sha', 'main']);
        self::assertSame(1, $invalid['exit']);
        self::assertStringContainsString('full reviewed, pushed control commit SHA', $invalid['stderr']);
    }

    public function test_completed_run_requires_capture_unified_and_every_upload_project_artifact(): void
    {
        $source = $this->scratch . '/source';
        mkdir($source);
        $output = $this->scratch . '/output';
        $command = ['python3', self::ROOT . '/deploy/cloudrun/browser-ci/package.py',
            '--source', $source, '--output', $output, '--exit-code', '0', '--stage', 'complete'];
        self::assertSame(1, $this->command($command)['exit']);
        $record = json_decode((string) file_get_contents($output . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($record['passed']);
        self::assertNotEmpty($record['missing_required_artifacts']);
        $files = [
            'browser/desktop/home.png' => "\x89PNG\r\n\x1a\nsynthetic",
            'unified-notifications-and-settings/browser-results.json' => '{"completed":true}',
            'image-upload-reliability/runtime-limits.json' => '{"php":"8.2.34"}',
        ];
        foreach (['desktop', 'mobile', 'webkit-mobile'] as $project) {
            $files['image-upload-reliability/' . $project . '-published.png'] = "\x89PNG\r\n\x1a\nsynthetic";
        }
        foreach ($files as $name => $content) {
            $path = $source . '/docs/evidence/' . $name;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, $content);
        }
        self::assertSame(0, $this->command($command)['exit']);
        $record = json_decode((string) file_get_contents($output . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($record['passed']);
        self::assertSame([], $record['missing_required_artifacts']);
        unlink($source . '/docs/evidence/image-upload-reliability/webkit-mobile-published.png');
        self::assertSame(1, $this->command($command)['exit']);
    }

    public function test_setup_reads_paginated_webhooks_with_the_installed_gh_cli_format(): void
    {
        $script = 'import importlib.util,json,sys; s=importlib.util.spec_from_file_location("ci",sys.argv[1]);'
            . ' m=importlib.util.module_from_spec(s); s.loader.exec_module(m); print(json.dumps(m.hook_pages(sys.argv[2])))';
        $result = $this->command(['python3', '-c', $script,
            self::ROOT . '/deploy/cloudrun/browser-ci/configure.py', " [{\"id\":1}]\n[{\"id\":2}]\n"]);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame([['id' => 1], ['id' => 2]], json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR));
        $invalid = $this->command(['python3', '-c', $script,
            self::ROOT . '/deploy/cloudrun/browser-ci/configure.py', '{"unexpected":"private-value"}']);
        self::assertSame(1, $invalid['exit']);
        self::assertStringContainsString('Unexpected webhook page shape', $invalid['stderr']);
        self::assertStringNotContainsString('private-value', $invalid['stderr']);
    }

    public function test_setup_accepts_existing_named_cloud_build_hook_without_changing_private_query_values(): void
    {
        $script = 'import importlib.util,sys; s=importlib.util.spec_from_file_location("ci",sys.argv[1]);'
            . ' m=importlib.util.module_from_spec(s); s.loader.exec_module(m); print(m.hook_target(sys.argv[2],"old-id","new-id"))';
        $prefix = 'https://cloudbuild.googleapis.com/v1/projects/rising-woods-449718-v6/locations/us-east4/triggers/';
        foreach (['old-id', 'retroboards-main'] as $identifier) {
            $result = $this->command(['python3', '-c', $script,
                self::ROOT . '/deploy/cloudrun/browser-ci/configure.py',
                $prefix . $identifier . ':webhook?key=dummy-key&secret=dummy-secret&trigger=old-id']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame($prefix . 'new-id:webhook?key=dummy-key&secret=dummy-secret&trigger=new-id', trim($result['stdout']));
        }
        $invalid = $this->command(['python3', '-c', $script,
            self::ROOT . '/deploy/cloudrun/browser-ci/configure.py',
            'https://example.invalid/v1/triggers/retroboards-main:webhook']);
        self::assertSame(1, $invalid['exit']);
        self::assertStringContainsString('expected Cloud Build endpoint', $invalid['stderr']);
    }

    /** @return array<string,mixed> */
    private function build(): array
    {
        return json_decode((string) file_get_contents(self::ROOT . '/deploy/cloudrun/browser-ci/cloudbuild.json'),
            true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $command @param array<string,string> $env @return array{exit:int,stdout:string,stderr:string} */
    private function command(array $command, array $env = []): array
    {
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes,
            self::ROOT, array_merge(getenv(), $env));
        if (!is_resource($process)) {
            self::fail('Could not start contract command');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $child) {
            if ($child !== '.' && $child !== '..') {
                $this->remove($path . '/' . $child);
            }
        }
        rmdir($path);
    }
}
