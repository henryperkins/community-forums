<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * The production OpenAI HTTP exchange (ADR 0019). Hardcoded to
 * https://api.openai.com with exactly two permitted paths — an operator-
 * supplied base URL is deliberately not a feature, so the credential cannot
 * be redirected to an untrusted host. TLS is verified, redirects refused,
 * transfers are HTTPS-only, and the response body is capped at 1 MiB. The
 * credential lives only in this object and is redacted from debug output;
 * every thrown failure carries a safe code and nothing from the wire.
 */
final class CurlOpenAiTransport implements OpenAiTransport
{
    private const BASE_URL = 'https://api.openai.com';
    private const ALLOWED_PATHS = ['/v1/responses', '/v1/moderations'];
    private const MAX_RESPONSE_BYTES = 1_048_576;
    private const MAX_RETRY_AFTER_SECONDS = 86_400;

    public function __construct(
        private readonly string $apiKey,
        private readonly ThreadIntelligenceConfig $config,
    ) {
    }

    public function post(string $path, array $payload, int $timeoutSeconds): OpenAiTransportResponse
    {
        if (!in_array($path, self::ALLOWED_PATHS, true)) {
            throw new InvalidArgumentException('OpenAI transport path is not allowed');
        }

        $body = '';
        $writer = self::boundedWriter($body);

        $retryAfter = null;
        $headerParser = static function ($handle, string $header) use (&$retryAfter): int {
            if (stripos($header, 'retry-after:') === 0) {
                $value = trim(substr($header, strlen('retry-after:')));
                if (preg_match('/\A\d+\z/', $value) === 1) {
                    $retryAfter = min((int) $value, self::MAX_RETRY_AFTER_SECONDS);
                }
            }
            return strlen($header);
        };

        $handle = curl_init(self::BASE_URL . $path);
        if ($handle === false) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT);
        }

        // The header parser is additive to the pinned production option set: it
        // reads only safe status metadata and Retry-After, never the body.
        curl_setopt_array($handle, $this->curlOptions($payload, $timeoutSeconds, $writer) + [
            CURLOPT_HEADERFUNCTION => $headerParser,
        ]);

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($ok === false || $status < 100) {
            // Connection/TLS/timeout/size-cap failures; cURL's error string can
            // embed hostnames or URLs, so none of it crosses this boundary.
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT);
        }

        $decoded = json_decode($body, true, 128);

        return new OpenAiTransportResponse($status, is_array($decoded) ? $decoded : null, $retryAfter);
    }

    /**
     * The exact production option set (plan Task 3). Data values are bound to
     * locals; nothing here is caller-controlled except the JSON payload.
     *
     * @param array<string,mixed> $payload
     * @return array<int,mixed>
     */
    private function curlOptions(array $payload, int $timeoutSeconds, callable $writer): array
    {
        return [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeoutSeconds(),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => $writer,
        ];
    }

    /** Accumulates at most 1 MiB into $body; returning 0 aborts the transfer. */
    private static function boundedWriter(string &$body): callable
    {
        return static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        };
    }

    /** @return array<string,string> credential-free debug view */
    public function __debugInfo(): array
    {
        return ['baseUrl' => self::BASE_URL, 'apiKey' => '[redacted]'];
    }
}
