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
        foreach (['APP_KEY', 'DB_PASSWORD', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'CLOUDFLARE_EMAIL_API_TOKEN'] as $secret) {
            self::assertStringContainsString($secret . ': env.' . $secret, $worker);
        }
    }

    public function test_worker_caches_only_public_get_assets_without_cookies(): void
    {
        $worker = $this->read('worker/index.js');

        self::assertStringContainsString('request.method !== "GET"', $worker);
        self::assertStringContainsString('url.pathname === "/brand.css"', $worker);
        self::assertStringContainsString('url.pathname.startsWith("/assets/") && url.searchParams.has("v")', $worker);
        self::assertStringContainsString('caches.default', $worker);
        self::assertStringContainsString('cache.match(cacheKey)', $worker);
        self::assertStringContainsString('headers.delete("Set-Cookie")', $worker);
        self::assertStringContainsString('public, max-age=300, s-maxage=3600', $worker);
        self::assertStringContainsString('cache.put(cacheKey, cacheable.clone())', $worker);
        self::assertStringContainsString('X-RetroBoards-Cache', $worker);
    }

    public function test_worker_allows_cold_start_time_for_mounts_and_migrations(): void
    {
        $worker = $this->read('worker/index.js');

        self::assertStringContainsString('startAndWaitForPorts', $worker);
        self::assertStringContainsString('instanceGetTimeoutMS: 120_000', $worker);

        // Readiness is deliberately NOT given a cold-start-sized window. The SDK
        // retries the probe every 300ms for the whole duration, so a long window
        // does not rescue a broken boot -- it floods the container instead.
        self::assertStringContainsString('portReadyTimeoutMS: 30_000', $worker);
    }

    /**
     * Regression: readiness probed `GET /`, which this app answers with a 302 to
     * /setup. The Workers fetch follows redirects, so a single probe cost two
     * full PHP+MySQL renders and exceeded the SDK's fixed 5s PING_TIMEOUT_MS.
     * The container was never marked ready and every request hung.
     */
    public function test_readiness_probe_targets_a_static_file_not_an_app_route(): void
    {
        $worker = $this->read('worker/index.js');
        $vhost = $this->read('deploy/apache-vhost.conf');

        self::assertStringContainsString('pingEndpoint = "ping/ping.txt"', $worker);
        self::assertFileExists(self::ROOT . '/public/ping.txt');

        // Apache only rewrites to index.php when the target does not exist, so a
        // real file under public/ is served without entering PHP at all.
        self::assertStringContainsString('RewriteCond %{REQUEST_FILENAME} !-f', $vhost);
    }

    /**
     * Regression: exec() starts with an almost empty environment (HOME, PATH,
     * PWD) rather than inheriting the variables handed to the container, so the
     * cron workers ran with no DB_* configuration until this was passed through.
     */
    public function test_cron_console_commands_receive_the_container_environment(): void
    {
        $worker = $this->read('worker/index.js');

        self::assertMatchesRegularExpression(
            '/exec\(\s*\["php", CONSOLE, \.\.\.args\],\s*\{\s*env: this\.envVars,/',
            $worker,
        );
        // output() buffers stdout/stderr as ArrayBuffers, not strings.
        self::assertStringContainsString('new TextDecoder()', $worker);
    }

    public function test_container_boot_requires_persistent_storage_and_migrates_before_apache(): void
    {
        $dockerfile = $this->read('Dockerfile');
        $apacheVhost = $this->read('deploy/apache-vhost.conf');
        $entrypoint = $this->read('deploy/entrypoint.sh');

        self::assertStringContainsString('s3fs', $dockerfile);
        self::assertStringContainsString('EXPOSE 8080', $dockerfile);
        self::assertStringContainsString('ErrorLog /var/log/apache2/error.log', $apacheVhost);
        self::assertStringContainsString('CustomLog /var/log/apache2/access.log combined', $apacheVhost);
        self::assertStringContainsString('rm -f /var/log/apache2/error.log', $dockerfile);
        self::assertStringContainsString('tail -n 0 -F /var/log/apache2/error.log', $entrypoint);
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
