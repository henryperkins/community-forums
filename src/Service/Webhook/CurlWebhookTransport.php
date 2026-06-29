<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Security\EgressGuard;

/** SSRF-hardened cURL transport for outbound webhook delivery. */
final class CurlWebhookTransport implements WebhookTransport
{
    public function __construct(private EgressGuard $guard, private int $maxResponseBytes = 65536)
    {
    }

    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse
    {
        $ip = $this->guard->validate($url);

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = $k . ': ' . $v;
        }

        $bytes = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $hdrs,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$bytes): int {
                $bytes += strlen($chunk);
                return $bytes > $this->maxResponseBytes ? -1 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($status === 0) {
            return new WebhookResponse(0, 'curl error ' . $errno);
        }
        return new WebhookResponse($status, ($status >= 200 && $status < 300) ? null : ('HTTP ' . $status));
    }
}
