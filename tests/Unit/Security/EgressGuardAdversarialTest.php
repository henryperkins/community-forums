<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SLICE-WEBHOOKS SP0 — formal SSRF/egress adversarial proof.
 *
 * Two enforcement layers are exercised against one corpus:
 *   - validateStatic() : registration-time guard for LITERAL-IP URLs (no DNS).
 *   - validate()       : delivery-time guard over every resolved A/AAAA record,
 *                        including mixed-result DNS rebinding.
 * Every case must be DENIED (EgressBlockedException) unless it is a public HTTPS
 * target or fully operator-allowlisted. A green run is the evidence artifact; a
 * red run is a real SSRF gap to escalate, not to patch inside this task.
 */
final class EgressGuardAdversarialTest extends TestCase
{
    /** @param array<int,string> $ips */
    private function guard(array $ips = [], bool $allowHttp = false, array $allow = []): EgressGuard
    {
        return new EgressGuard($allowHttp, $allow, static fn (string $host): array => $ips);
    }

    /** @return array<string,array{0:string}> */
    public static function deniedLiteralUrls(): array
    {
        return [
            'loopback v4'            => ['https://127.0.0.1/hook'],
            'private 10/8'           => ['https://10.0.0.5/hook'],
            'private 172.16/12'      => ['https://172.16.9.9/hook'],
            'private 192.168/16'     => ['https://192.168.1.9/hook'],
            'cloud metadata'         => ['https://169.254.169.254/latest/meta-data/'],
            'cgnat 100.64/10'        => ['https://100.64.0.1/hook'],
            'unspecified 0.0.0.0'    => ['https://0.0.0.0/hook'],
            'multicast 224/4'        => ['https://224.0.0.1/hook'],
            'reserved 240/4'         => ['https://240.0.0.1/hook'],
            'ipv6 loopback'          => ['https://[::1]/hook'],
            'ipv6 ula fc00::/7'      => ['https://[fc00::1]/hook'],
            'ipv6 link-local fe80'   => ['https://[fe80::1]/hook'],
            'v4-mapped metadata'     => ['https://[::ffff:169.254.169.254]/hook'],
            'v4-mapped private'      => ['https://[::ffff:10.0.0.1]/hook'],
            'creds in url + public'  => ['https://user:pass@8.8.8.8/hook'],
            'creds in url + host'    => ['https://user:pass@example.test/hook'],
            'non-http scheme'        => ['ftp://93.184.216.34/hook'],
        ];
    }

    #[DataProvider('deniedLiteralUrls')]
    public function test_validate_static_denies_literal_ssrf_targets(string $url): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard()->validateStatic($url);
    }

    public function test_validate_static_allows_public_literal_and_defers_hostnames(): void
    {
        // No exception for a public literal, and a bare hostname is deferred to
        // delivery-time validate() (validateStatic must not perform DNS here).
        $this->guard()->validateStatic('https://93.184.216.34/hook');
        $this->guard()->validateStatic('https://example.test/hook');
        self::assertTrue(true);
    }

    /** @return array<string,array{0:string,1:array<int,string>,2:bool}> */
    public static function deniedResolvedUrls(): array
    {
        return [
            'metadata via dns'      => ['https://metadata.evil.test/latest', ['169.254.169.254'], false],
            'dns rebind mixed'      => ['https://rebind.test/hook',          ['1.2.3.4', '127.0.0.1'], false],
            'private via dns'       => ['https://intranet.test/hook',        ['10.0.0.5'], false],
            'v4-mapped via dns'     => ['https://evil.test/hook',            ['::ffff:169.254.169.254'], false],
            'unresolvable host'     => ['https://nope.test/hook',            [], false],
            'public http denied'    => ['http://public.test/hook',          ['8.8.8.8'], false],
            'public odd port'       => ['https://public.test:8443/hook',     ['8.8.8.8'], false],
        ];
    }

    /**
     * @param array<int,string> $ips
     */
    #[DataProvider('deniedResolvedUrls')]
    public function test_validate_denies_resolved_ssrf_targets(string $url, array $ips, bool $allowHttp): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard($ips, $allowHttp)->validate($url);
    }

    public function test_allowlist_relaxes_only_when_every_resolved_ip_is_allowlisted(): void
    {
        // Fully-allowlisted loopback over http:8011 is permitted and pins the IP.
        $pinned = $this->guard(['127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://localhost:8011/hook');
        self::assertSame('127.0.0.1', $pinned);

        // A rebind that mixes an un-allowlisted public IP with a denied private IP
        // is NOT "all allowlisted", so it falls to the deny path and is blocked.
        $this->expectException(EgressBlockedException::class);
        $this->guard(['8.8.8.8', '127.0.0.1'], false, ['127.0.0.1/32'])->validate('https://rebind.test/hook');
    }
}
