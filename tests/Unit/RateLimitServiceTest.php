<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Security\ArrayRateLimiter;
use App\Security\ClientIdentifier;
use App\Service\RateLimitService;
use PHPUnit\Framework\TestCase;

/**
 * P3-05: the central limiter enforces named policies and the client identifier
 * only trusts X-Forwarded-For behind a configured proxy.
 */
final class RateLimitServiceTest extends TestCase
{
    private function request(string $remote, string $xff = ''): Request
    {
        $server = ['REMOTE_ADDR' => $remote];
        if ($xff !== '') {
            $server['HTTP_X_FORWARDED_FOR'] = $xff;
        }
        return new Request('POST', '/threads', [], [], [], $server);
    }

    public function test_enforce_blocks_after_the_window_is_exhausted(): void
    {
        $config = new Config(['rate_limits' => ['t' => [2, 60]]]);
        $svc = new RateLimitService(new ArrayRateLimiter(), $config, new ClientIdentifier([]));
        $req = $this->request('198.51.100.9');

        $svc->enforce('t', $req); // 1
        $svc->enforce('t', $req); // 2

        $this->expectException(HttpException::class);
        $svc->enforce('t', $req); // 3 → 429
    }

    public function test_unknown_policy_is_a_no_op(): void
    {
        $svc = new RateLimitService(new ArrayRateLimiter(), new Config(['rate_limits' => []]), new ClientIdentifier([]));
        $svc->enforce('missing', $this->request('198.51.100.9'));
        $this->addToAssertionCount(1); // no throw
    }

    public function test_clear_resets_the_window(): void
    {
        $config = new Config(['rate_limits' => ['t' => [1, 60]]]);
        $svc = new RateLimitService(new ArrayRateLimiter(), $config, new ClientIdentifier([]));
        $req = $this->request('198.51.100.9');
        $svc->enforce('t', $req);
        $svc->clear('t', $req);
        $svc->enforce('t', $req); // would throw if not cleared
        $this->addToAssertionCount(1);
    }

    public function test_client_identifier_ignores_xff_without_trusted_proxy(): void
    {
        $id = new ClientIdentifier([]);
        self::assertSame('8.8.8.8', $id->ipFor($this->request('8.8.8.8', '203.0.113.7')));
    }

    public function test_client_identifier_honours_xff_behind_trusted_proxy(): void
    {
        $id = new ClientIdentifier(['10.0.0.0/8']);
        // REMOTE_ADDR is the trusted proxy; the real client is the right-most
        // untrusted hop in X-Forwarded-For.
        self::assertSame('203.0.113.7', $id->ipFor($this->request('10.1.2.3', '203.0.113.7, 10.0.0.5')));
    }

    public function test_client_identifier_rejects_spoofed_xff_from_untrusted_remote(): void
    {
        $id = new ClientIdentifier(['10.0.0.0/8']);
        // REMOTE_ADDR is NOT a trusted proxy → XFF is ignored entirely.
        self::assertSame('8.8.8.8', $id->ipFor($this->request('8.8.8.8', '203.0.113.7')));
    }
}
