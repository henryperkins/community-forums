<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Request;

/**
 * Resolves the client IP used for rate-limit keying (P3-05). Behind a trusted
 * reverse proxy the real client is in X-Forwarded-For, but that header is
 * attacker-controlled, so we only honour it when REMOTE_ADDR is a configured
 * trusted proxy, and then take the right-most address that is NOT itself a
 * trusted proxy — the closest hop the trusted infrastructure actually saw. With
 * no trusted proxies configured we always use REMOTE_ADDR (the safe default).
 */
final class ClientIdentifier
{
    /** @param list<string> $trustedProxies IPs or CIDR ranges */
    public function __construct(private array $trustedProxies = [])
    {
    }

    public function ipFor(Request $request): string
    {
        $remote = $request->ip();
        if ($this->trustedProxies === [] || !$this->isTrusted($remote)) {
            return $remote;
        }
        $xff = (string) ($request->header('X-Forwarded-For') ?? '');
        if ($xff === '') {
            return $remote;
        }
        $hops = array_values(array_filter(array_map('trim', explode(',', $xff))));
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            if (filter_var($hops[$i], FILTER_VALIDATE_IP) !== false && !$this->isTrusted($hops[$i])) {
                return $hops[$i];
            }
        }
        return $remote;
    }

    private function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $range) {
            if ($this->matches($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private function matches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }
        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $rem)) & 0xff);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
