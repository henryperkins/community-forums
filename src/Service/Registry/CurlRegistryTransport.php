<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;

/**
 * SSRF-hardened GET for registry documents: EgressGuard-validated, DNS pinned
 * via CURLOPT_RESOLVE, redirects refused, response capped.
 */
final class CurlRegistryTransport implements RegistryTransport
{
    public function __construct(
        private EgressGuard $guard,
        private int $maxBytes = 1_048_576,
        private int $timeoutSeconds = 10,
    ) {
    }

    public function fetch(string $url): RegistryFetchResult
    {
        try {
            $ip = $this->guard->validate($url);
        } catch (EgressBlockedException $e) {
            return new RegistryFetchResult(0, '', 'egress blocked: ' . $e->getMessage());
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        $body = '';
        $overflow = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$overflow): int {
                $body .= $chunk;
                if (strlen($body) > $this->maxBytes) {
                    $overflow = true;

                    return -1;
                }

                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($overflow) {
            return new RegistryFetchResult(0, '', 'response exceeded ' . $this->maxBytes . ' byte cap');
        }
        if ($status === 0) {
            return new RegistryFetchResult(0, '', 'curl error ' . $errno);
        }

        return new RegistryFetchResult($status, $body, null);
    }
}
