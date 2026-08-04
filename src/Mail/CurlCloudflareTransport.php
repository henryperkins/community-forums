<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * cURL transport for the Cloudflare Email Sending REST API.
 *
 * No EgressGuard here, unlike webhooks or link previews: the URL is not
 * operator- or member-supplied, it is a constant api.cloudflare.com endpoint, so
 * there is no SSRF surface to defend. TLS verification stays on regardless.
 */
final class CurlCloudflareTransport implements CloudflareTransport
{
    private const MAX_RESPONSE_BYTES = 65536;

    public function post(string $url, string $token, array $payload, int $timeoutSeconds): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new MailException('Could not encode the message for the Cloudflare Email Sending API.');
        }

        $response = '';
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => 'curl unavailable'];
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$response): int {
                if (strlen($response) < self::MAX_RESPONSE_BYTES) {
                    $response .= $chunk;
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status === 0) {
            return ['status' => 0, 'body' => 'curl error ' . $errno . ($error !== '' ? ': ' . $error : '')];
        }

        return ['status' => $status, 'body' => $response];
    }
}
