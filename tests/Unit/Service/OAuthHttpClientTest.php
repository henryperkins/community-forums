<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\OAuth\HttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Inc 8 (P5-12) — response interpretation for the outbound OAuth/OIDC client.
 * The load-bearing rule: an HTTP error status WITHOUT a parseable JSON body
 * (load-balancer maintenance page, gateway error) is a FAILED FETCH and must
 * throw like a transport error, so the JwksCache / discovery stale-on-outage
 * fallbacks apply to the most common outage shape. An error status WITH a
 * JSON body still returns it — token endpoints speak structured errors
 * (e.g. invalid_grant) over 400s.
 */
final class OAuthHttpClientTest extends TestCase
{
    public function test_http_error_without_json_body_throws_like_a_transport_failure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503');
        self::decode('<html>503 Service Unavailable</html>', 503);
    }

    public function test_http_error_with_a_structured_json_body_is_returned(): void
    {
        self::assertSame(
            ['error' => 'invalid_grant'],
            self::decode('{"error":"invalid_grant"}', 400),
            'token-endpoint OAuth errors keep their structured body',
        );
    }

    public function test_ok_json_body_decodes(): void
    {
        self::assertSame(['issuer' => 'https://idp.test'], self::decode('{"issuer":"https://idp.test"}', 200));
    }

    public function test_ok_non_json_body_decodes_to_an_empty_document(): void
    {
        // A 200 that is not JSON is an INVALID document, not an outage —
        // callers fail closed on it (jwks_invalid / discovery refusals).
        self::assertSame([], self::decode('not json', 200));
    }

    public function test_http_error_with_scalar_json_still_counts_as_unparseable(): void
    {
        $this->expectException(RuntimeException::class);
        self::decode('"maintenance"', 502);
    }

    /** @return array<string,mixed> */
    private static function decode(string $raw, int $status): array
    {
        $client = new class extends HttpClient {
            /** @return array<string,mixed> */
            public static function decodePublic(string $raw, int $status): array
            {
                return self::decodeResponse($raw, $status);
            }
        };
        return $client::decodePublic($raw, $status);
    }
}
