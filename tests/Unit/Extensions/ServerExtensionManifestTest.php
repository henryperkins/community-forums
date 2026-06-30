<?php

declare(strict_types=1);

namespace Tests\Unit\Extensions;

use App\Service\Extension\ServerExtensionManifestValidator;
use PHPUnit\Framework\TestCase;

final class ServerExtensionManifestTest extends TestCase
{
    public function test_valid_server_extension_manifest_normalizes_handlers(): void
    {
        $manifest = [
            'schema' => 'server_extension.v1',
            'entrypoint' => 'extension.php',
            'events' => ['topic.created'],
            'jobs' => ['refresh-related'],
            'permissions' => [
                'broker' => ['core.notifications.create'],
                'outbound_hosts' => [],
            ],
            'resource_limits' => [
                'cpu_ms' => 500,
                'memory_mb' => 64,
                'time_ms' => 1000,
                'output_kb' => 64,
                'disk_kb' => 512,
            ],
            'storage_quota_kb' => 256,
        ];

        $validated = (new ServerExtensionManifestValidator())->validate($manifest);

        self::assertSame('extension.php', $validated['entrypoint']);
        self::assertSame(['topic.created'], $validated['events']);
        self::assertSame(['refresh-related'], $validated['jobs']);
        self::assertSame([], $validated['permissions']['outbound_hosts']);
        self::assertSame(262144, $validated['storage_quota_bytes']);
    }

    public function test_manifest_rejects_network_by_default_and_path_escape_entrypoint(): void
    {
        $validator = new ServerExtensionManifestValidator();

        $this->expectException(\InvalidArgumentException::class);
        $validator->validate([
            'schema' => 'server_extension.v1',
            'entrypoint' => '../escape.php',
            'events' => ['topic.created'],
            'permissions' => ['outbound_hosts' => ['example.com']],
        ]);
    }

    public function test_manifest_requires_supported_schema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ServerExtensionManifestValidator())->validate([
            'schema' => 'server_extension.v2',
            'entrypoint' => 'extension.php',
        ]);
    }
}
