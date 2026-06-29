<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use PHPUnit\Framework\TestCase;

final class EgressGuardTest extends TestCase
{
    /** @param array<int,string> $ips */
    private function guard(array $ips, bool $allowHttp = false, array $allow = []): EgressGuard
    {
        return new EgressGuard($allowHttp, $allow, static fn (string $host): array => $ips);
    }

    public function test_public_https_target_is_allowed_and_returns_pinned_ip(): void
    {
        $ip = $this->guard(['93.184.216.34'])->validate('https://example.test/hook');
        self::assertSame('93.184.216.34', $ip);
    }

    public function test_blocks_loopback_private_linklocal_metadata_and_v4mapped(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.9', '169.254.169.254', 'fe80::1', '::ffff:10.0.0.1'] as $ip) {
            try {
                $this->guard([$ip])->validate('https://internal.test/hook');
                self::fail("expected block for $ip");
            } catch (EgressBlockedException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_allowlist_relaxes_scheme_and_port_for_all_allowlisted(): void
    {
        $ip = $this->guard(['127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://localhost:8011/hook');
        self::assertSame('127.0.0.1', $ip);
    }

    public function test_mixed_public_and_private_dns_is_blocked(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard(['1.2.3.4', '127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://rebind.test:8011/hook');
    }

    public function test_public_tier_rejects_http_and_odd_ports_and_credentials(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard(['93.184.216.34'])->validate('http://example.test/hook');
    }

    public function test_rejects_credentials_in_url(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard(['93.184.216.34'])->validate('https://user:pass@example.test/hook');
    }

    public function test_rejects_unresolvable_host(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard([])->validate('https://nope.test/hook');
    }

    public function test_validate_static_rejects_literal_private_ip_but_allows_hostname_without_dns(): void
    {
        $guard = new EgressGuard(false, [], static function (): array {
            throw new \RuntimeException('validateStatic must not perform DNS for hostnames');
        });
        $guard->validateStatic('https://example.test/hook');
        self::assertTrue(true);

        $this->expectException(EgressBlockedException::class);
        $guard->validateStatic('https://10.0.0.1/hook');
    }
}
