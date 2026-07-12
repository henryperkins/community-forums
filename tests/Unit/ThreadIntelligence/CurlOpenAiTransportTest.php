<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\CurlOpenAiTransport;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the cURL safety envelope (plan Task 3) WITHOUT any network use: the
 * fixed host, the two-path allowlist enforced before cURL initialization, the
 * exact production option set (TLS verification, HTTPS-only protocols, no
 * redirects, bounded body writer), and credential non-exposure.
 */
final class CurlOpenAiTransportTest extends TestCase
{
    private const KEY = 'sk-test-unit-key-000000000000';

    private function transport(): CurlOpenAiTransport
    {
        return new CurlOpenAiTransport(self::KEY, ThreadIntelligenceConfig::fromArray(['api_key' => self::KEY]));
    }

    /** @param array<string,mixed> $payload @return array<int,mixed> */
    private function options(array $payload, int $timeoutSeconds, ?callable $writer = null): array
    {
        $method = new ReflectionMethod(CurlOpenAiTransport::class, 'curlOptions');
        return $method->invoke($this->transport(), $payload, $timeoutSeconds, $writer ?? static fn ($h, string $c): int => strlen($c));
    }

    // ---- fixed host + path allowlist ---------------------------------------

    public function test_base_url_is_hardcoded_to_the_openai_host(): void
    {
        $constant = (new ReflectionClass(CurlOpenAiTransport::class))->getConstant('BASE_URL');
        self::assertSame('https://api.openai.com', $constant);
    }

    public function test_only_the_two_product_paths_are_permitted_and_rejection_happens_before_curl(): void
    {
        foreach (['/v1/chat/completions', '/v1/files', 'https://evil.example/v1/responses', '/v1/responses/../admin', ''] as $path) {
            try {
                $this->transport()->post($path, [], 5);
                self::fail("path must be rejected: $path");
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('not allowed', $e->getMessage());
                self::assertStringNotContainsString(self::KEY, $e->getMessage());
            }
        }
    }

    // ---- exact production option set ---------------------------------------------

    public function test_production_options_pin_tls_redirect_protocol_timeout_and_header_posture(): void
    {
        $payload = ['model' => 'gpt-5.6-luna', 'input' => []];
        $options = $this->options($payload, 60);

        self::assertTrue($options[CURLOPT_POST]);
        self::assertSame(
            ['Authorization: Bearer ' . self::KEY, 'Content-Type: application/json'],
            $options[CURLOPT_HTTPHEADER],
        );
        self::assertSame(
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $options[CURLOPT_POSTFIELDS],
        );
        self::assertSame(5, $options[CURLOPT_CONNECTTIMEOUT], 'configured 5-second connect timeout');
        self::assertSame(60, $options[CURLOPT_TIMEOUT], 'caller-supplied request timeout');
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION], 'redirects are refused');
        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        self::assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        self::assertSame(CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
        self::assertFalse($options[CURLOPT_HEADER]);
        self::assertIsCallable($options[CURLOPT_WRITEFUNCTION]);
    }

    public function test_moderation_timeout_is_independent_of_the_generation_timeout(): void
    {
        self::assertSame(15, $this->options([], 15)[CURLOPT_TIMEOUT]);
    }

    // ---- bounded 1 MiB writer -----------------------------------------------------

    public function test_the_body_writer_caps_accumulation_at_one_mebibyte(): void
    {
        $method = new ReflectionMethod(CurlOpenAiTransport::class, 'boundedWriter');
        $body = '';
        $writer = $method->invokeArgs(null, [&$body]);

        $chunk = str_repeat('a', 1_000_000);
        self::assertSame(1_000_000, $writer(null, $chunk));
        self::assertSame(48_576, $writer(null, str_repeat('b', 48_576)), 'exactly 1 MiB total is still accepted');
        self::assertSame(1_048_576, strlen($body));
        self::assertSame(0, $writer(null, 'x'), 'the byte past 1 MiB aborts the transfer');
        self::assertSame(1_048_576, strlen($body), 'nothing past the cap is retained');
    }

    // ---- credential non-exposure ------------------------------------------------------

    public function test_the_key_is_never_exposed_through_getters_or_debug_output(): void
    {
        $transport = $this->transport();

        foreach ((new ReflectionClass($transport))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            self::assertDoesNotMatchRegularExpression(
                '/\A(get)?(api)?key\z/i',
                $method->getName(),
                'no public accessor may return the credential',
            );
        }

        ob_start();
        var_dump($transport);
        $dumped = (string) ob_get_clean();
        self::assertStringNotContainsString(self::KEY, $dumped, 'debug output must redact the credential');
    }
}
