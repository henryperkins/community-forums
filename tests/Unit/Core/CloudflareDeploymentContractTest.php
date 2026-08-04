<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudflareDeploymentContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_worker_keeps_one_stateful_container_behind_the_production_custom_domain(): void
    {
        $config = $this->read('wrangler.jsonc');

        self::assertStringContainsString('"name": "retroboards"', $config);
        self::assertStringContainsString('"class_name": "ForumContainer"', $config);
        self::assertStringContainsString('"max_instances": 1', $config);
        self::assertStringContainsString('"pattern": "forum.candidary.online"', $config);
        self::assertStringContainsString('"custom_domain": true', $config);
    }

    public function test_worker_replaces_untrusted_forwarding_and_passes_runtime_secrets_to_php(): void
    {
        $worker = $this->read('worker/index.js');

        self::assertStringContainsString('request.headers.get("CF-Connecting-IP")', $worker);
        self::assertStringContainsString('forwarded.headers.set("X-Forwarded-For", clientIp)', $worker);
        self::assertStringContainsString('forwarded.headers.delete("X-Forwarded-For")', $worker);
        foreach (['APP_KEY', 'DB_PASSWORD', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'] as $secret) {
            self::assertStringContainsString($secret . ': env.' . $secret, $worker);
        }
    }

    public function test_container_boot_requires_persistent_storage_and_migrates_before_apache(): void
    {
        $dockerfile = $this->read('Dockerfile');
        $entrypoint = $this->read('deploy/entrypoint.sh');

        self::assertStringContainsString('s3fs', $dockerfile);
        self::assertStringContainsString(': "${R2_ACCESS_KEY_ID:', $entrypoint);
        self::assertStringContainsString(': "${R2_SECRET_ACCESS_KEY:', $entrypoint);
        self::assertStringContainsString('php /var/www/html/bin/console migrate', $entrypoint);
        self::assertLessThan(
            strpos($entrypoint, 'exec docker-php-entrypoint'),
            strpos($entrypoint, 'php /var/www/html/bin/console migrate'),
        );
    }

    private function read(string $relativePath): string
    {
        $path = self::ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
