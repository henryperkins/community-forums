<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\ServerExtensionRepository;
use App\Service\Extension\ExtensionSandbox;
use App\Worker\ServerExtensionWorker;
use Tests\Support\TestCase;

final class ServerExtensionWorkerTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()",
            [json_encode($flags, JSON_THROW_ON_ERROR)],
        );
    }

    public function test_schema_tables_exist(): void
    {
        foreach (['server_extension_handlers', 'server_extension_jobs', 'server_extension_runs', 'server_extension_kv'] as $table) {
            self::assertSame(
                1,
                (int) $this->db->fetchValue(
                    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                ),
                "missing extension runtime table {$table}",
            );
        }
    }

    public function test_worker_is_dark_until_flag_enabled(): void
    {
        $repo = new ServerExtensionRepository($this->db);
        $this->createQueuedJob($repo);

        $stats = (new ServerExtensionWorker($repo, new UnavailableSandbox(), false))->run();

        self::assertSame(['ran' => 0, 'failed' => 0, 'skipped' => 1, 'quarantined' => 0], $stats);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM server_extension_jobs LIMIT 1'));
    }

    public function test_unsupported_sandbox_fails_closed_and_keeps_runtime_unavailable(): void
    {
        $this->setFlags(['server_extensions' => true]);
        $repo = new ServerExtensionRepository($this->db);
        $this->createQueuedJob($repo);

        $stats = (new ServerExtensionWorker($repo, new UnavailableSandbox(), true))->run();

        self::assertSame(['ran' => 0, 'failed' => 0, 'skipped' => 1, 'quarantined' => 0], $stats);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM server_extension_jobs LIMIT 1'));
    }

    public function test_worker_records_successful_async_run(): void
    {
        $this->setFlags(['server_extensions' => true]);
        $repo = new ServerExtensionRepository($this->db);
        $jobId = $this->createQueuedJob($repo);

        $stats = (new ServerExtensionWorker($repo, new SuccessfulSandbox(), true))->run();

        self::assertSame(['ran' => 1, 'failed' => 0, 'skipped' => 0, 'quarantined' => 0], $stats);
        self::assertSame('succeeded', (string) $this->db->fetchValue('SELECT status FROM server_extension_jobs WHERE id = ?', [$jobId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM server_extension_runs WHERE job_id = ?', [$jobId]));
    }

    private function createQueuedJob(ServerExtensionRepository $repo): int
    {
        $admin = $this->makeAdmin(['username' => 'ext-admin']);
        $packageId = $this->db->insert(
            "INSERT INTO packages (package_uid, name, type, trust_class, created_at)
             VALUES ('local.test.extension', 'Local Extension', 'server_extension', 'isolated_server', UTC_TIMESTAMP())",
        );
        $installedId = $this->db->insert(
            "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at)
             VALUES (?, REPEAT('a', 64), 'isolated_server', 'approved', 'enabled', ?, UTC_TIMESTAMP())",
            [$packageId, (int) $admin['id']],
        );
        $handlerId = $repo->upsertHandler($installedId, [
            'handler_key' => 'topic-created',
            'entrypoint' => 'extension.php',
            'events' => ['topic.created'],
            'jobs' => [],
            'permissions' => ['broker' => [], 'outbound_hosts' => []],
            'resource_limits' => ['time_ms' => 1000, 'memory_mb' => 64, 'cpu_ms' => 500, 'output_kb' => 64, 'disk_kb' => 512],
            'storage_quota_bytes' => 262144,
        ]);
        return $repo->enqueue($handlerId, 'topic.created', ['thread_id' => 123]);
    }
}

final class UnavailableSandbox implements ExtensionSandbox
{
    public function probe(): array
    {
        return ['supported' => false, 'adapter' => 'fake', 'reason' => 'unsupported'];
    }

    public function run(array $handler, array $job): array
    {
        throw new \RuntimeException('should not run');
    }
}

final class SuccessfulSandbox implements ExtensionSandbox
{
    public function probe(): array
    {
        return ['supported' => true, 'adapter' => 'fake', 'reason' => null];
    }

    public function run(array $handler, array $job): array
    {
        return [
            'status' => 'succeeded',
            'exit_code' => 0,
            'duration_ms' => 12,
            'output_bytes' => 18,
            'stdout_json' => ['ok' => true],
            'error' => null,
        ];
    }
}
