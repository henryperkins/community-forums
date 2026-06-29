<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\EgressBlockedException;
use App\Support\Cidr;

/**
 * SSRF egress policy for outbound webhook delivery.
 *
 * The relaxed tier applies only when every resolved address is operator
 * allowlisted. Otherwise every resolved address must be public-safe.
 */
final class EgressGuard
{
    private const DENY = [
        '127.0.0.0/8',
        '::1/128',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
        '169.254.0.0/16',
        'fe80::/10',
        '0.0.0.0/8',
        '100.64.0.0/10',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::ffff:0:0/96',
    ];

    /** @var callable(string):array<int,string> */
    private $resolver;

    /**
     * @param list<string> $allowedCidrs
     * @param null|callable(string):array<int,string> $resolver
     */
    public function __construct(
        private bool $allowHttp,
        private array $allowedCidrs,
        ?callable $resolver = null,
    ) {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = [];
            $a = @gethostbynamel($host);
            if (is_array($a)) {
                $ips = $a;
            }
            $recs = @dns_get_record($host, DNS_AAAA);
            if (is_array($recs)) {
                foreach ($recs as $r) {
                    if (isset($r['ipv6'])) {
                        $ips[] = (string) $r['ipv6'];
                    }
                }
            }
            return $ips;
        };
    }

    public function validate(string $url): string
    {
        [$scheme, $host, $port] = $this->parse($url);
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        $ips = array_values(array_filter($ips, static fn (mixed $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false));
        if ($ips === []) {
            throw new EgressBlockedException('Host did not resolve.');
        }

        return $this->classify($ips, $scheme, $port);
    }

    public function validateStatic(string $url): void
    {
        [$scheme, $host, $port] = $this->parse($url);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->classify([$host], $scheme, $port);
        }
    }

    /** @return array{0:string,1:string,2:int} */
    private function parse(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new EgressBlockedException('Malformed URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new EgressBlockedException('Credentials in URL are not allowed.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new EgressBlockedException('Only http(s) is allowed.');
        }
        $host = trim((string) $parts['host'], '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        return [$scheme, $host, $port];
    }

    /** @param array<int,string> $ips */
    private function classify(array $ips, string $scheme, int $port): string
    {
        $allAllowlisted = true;
        foreach ($ips as $ip) {
            if (!$this->inAllowlist($ip)) {
                $allAllowlisted = false;
                break;
            }
        }

        if ($allAllowlisted) {
            return $ips[0];
        }

        foreach ($ips as $ip) {
            if ($this->inDeny($ip)) {
                throw new EgressBlockedException('Resolves to a blocked address.');
            }
        }
        if ($scheme !== 'https' && !$this->allowHttp) {
            throw new EgressBlockedException('Only HTTPS is allowed for public targets.');
        }
        $allowedPorts = $this->allowHttp ? [443, 80] : [443];
        if (!in_array($port, $allowedPorts, true)) {
            throw new EgressBlockedException('Port ' . $port . ' is not allowed.');
        }
        return $ips[0];
    }

    private function inAllowlist(string $ip): bool
    {
        foreach ($this->allowedCidrs as $cidr) {
            if (Cidr::contains($ip, (string) $cidr)) {
                return true;
            }
        }
        return false;
    }

    private function inDeny(string $ip): bool
    {
        foreach (self::DENY as $cidr) {
            if (Cidr::contains($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }
}
