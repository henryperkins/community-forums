<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use RuntimeException;

/**
 * Minimal HTTPS client for OAuth token/userinfo calls. Isolated so providers
 * stay declarative and so it can be swapped in tests. Always verifies TLS.
 */
class HttpClient
{
    /**
     * POST application/x-www-form-urlencoded, parse a JSON response.
     *
     * @param array<string,string> $form
     * @param array<string,string> $headers
     * @return array<string,mixed>
     */
    public function postForm(string $url, array $form, array $headers = []): array
    {
        return $this->request('POST', $url, http_build_query($form), array_merge([
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], $this->kv($headers)));
    }

    /**
     * GET with a bearer token, parse a JSON response.
     *
     * @return array<string,mixed>
     */
    public function getJson(string $url, string $bearer): array
    {
        return $this->request('GET', $url, null, [
            'Authorization: Bearer ' . $bearer,
            'Accept: application/json',
            'User-Agent: RetroBoards',
        ]);
    }

    /**
     * @param list<string> $headers
     * @return array<string,mixed>
     */
    protected function request(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('OAuth HTTP request failed: ' . $err);
        }
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,string> $headers @return list<string> */
    private function kv(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = $k . ': ' . $v;
        }
        return $out;
    }

    /**
     * Decode a JWT payload WITHOUT signature verification. Safe here only because
     * the token is received directly from the provider's token endpoint over a
     * verified-TLS back channel (never from the browser front channel).
     *
     * @return array<string,mixed>
     */
    public static function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
