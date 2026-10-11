<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudflareDeploymentContractTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_worker_keeps_the_production_custom_domain_without_the_retired_container(): void
    {
        $config = $this->read('wrangler.jsonc');
        $worker = $this->read('worker/index.js');
        $package = json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode($this->read('package-lock.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertStringContainsString('"name": "retroboards"', $config);
        self::assertStringContainsString('"pattern": "forum.candidary.online"', $config);
        self::assertStringContainsString('"custom_domain": true', $config);
        self::assertStringNotContainsString('ForumContainer', $worker);
        self::assertStringNotContainsString('@cloudflare/containers', $worker);
        self::assertArrayNotHasKey('@cloudflare/containers', $package['dependencies']);
        self::assertArrayNotHasKey('@cloudflare/containers', $lock['packages']['']['dependencies']);
        self::assertArrayNotHasKey('node_modules/@cloudflare/containers', $lock['packages']);
        foreach (['APP_KEY', 'DB_PASSWORD', 'DB_SSL_CA_PEM', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'CLOUDFLARE_EMAIL_API_TOKEN'] as $secret) {
            self::assertStringNotContainsString($secret, $worker);
        }
        // SaaS zone-route ownership and the resulting Wrangler trigger inputs
        // plus migration continuity and the removed bindings/vars are checked
        // with Wrangler's real config parser in
        // tests/worker/deployment-routes.test.mjs (runbook §16).
        self::assertStringNotContainsString('"pattern": "*/*"', $config);
    }

    /**
     * The Worker forwards dynamic requests to Cloud Run with a token only it can
     * mint, and replaces the client-supplied forwarding header on the way.
     * Header and token semantics run against real Requests in
     * tests/worker/origin.test.mjs; the app's side is CloudRunDeploymentContractTest.
     */
    public function test_worker_forwards_to_the_cloud_run_origin_with_its_own_token_and_client_ip(): void
    {
        $worker = $this->read('worker/index.js');
        $origin = $this->read('worker/origin.mjs');
        $config = $this->read('wrangler.jsonc');

        self::assertStringContainsString('return await fetchOrigin(request, env);', $worker);
        // A failed token mint or origin fetch is a retryable 502, not a 1101.
        self::assertStringContainsString('status: 502', $worker);
        self::assertStringContainsString('request.headers.get("CF-Connecting-IP")', $origin);
        self::assertStringContainsString('upstream.headers.set("X-Forwarded-For", clientIp)', $origin);
        self::assertStringContainsString('upstream.headers.delete("X-Forwarded-For")', $origin);
        self::assertStringContainsString('"X-Serverless-Authorization"', $origin);
        self::assertStringContainsString('"ORIGIN_URL": "https://retroboards-616731728350.us-east4.run.app"', $config);
        // Cron work moved to Cloud Scheduler. Only an explicit empty list removes
        // deployed schedules; a missing key would leave them firing.
        self::assertStringContainsString('"triggers": { "crons": [] }', $config);
        self::assertStringNotContainsString('scheduled(', $worker);
    }

    /**
     * The app has one canonical origin, APP_URL: sessions, CSRF, passkeys and
     * OAuth callbacks are all bound to it. Every other hostname that reaches the
     * Worker must redirect there, which is what makes attaching a hostname
     * before (or keeping one after) a canonical-origin move safe (runbook §16).
     * The redirect semantics themselves are covered by tests/worker/canonical.test.mjs.
     */
    public function test_worker_redirects_every_non_canonical_hostname_to_app_url(): void
    {
        $worker = $this->read('worker/index.js');
        $canonical = $this->read('worker/canonical.mjs');

        self::assertStringContainsString('import { canonicalRedirect } from "./canonical.mjs"', $worker);
        // First thing in fetch(): nothing (assets included) is served on a non-canonical host.
        self::assertMatchesRegularExpression(
            '/async fetch\(request, env\) \{(?:\s*\/\/[^\n]*)*\s*return canonicalRedirect\(request, env\) \?\? routeRequest\(/',
            $worker,
        );
        self::assertStringContainsString('new URL(String(env?.APP_URL', $canonical);
        // A cached redirect would loop against the reverse one after APP_URL flips.
        self::assertStringContainsString('"Cache-Control": "no-store"', $canonical);
    }

    // Asset routing/cache semantics run against actual Requests/Responses and
    // the Workers Assets binding in `npm run test:assets` (tests/worker/).

    private function read(string $relativePath): string
    {
        $path = self::ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
