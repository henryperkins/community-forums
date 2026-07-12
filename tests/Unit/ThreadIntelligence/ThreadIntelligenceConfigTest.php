<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the validated Thread Intelligence configuration contract (plan Task 1).
 *
 * Every value normalizes to a conservative typed default when missing or
 * invalid — an unsafe operator value is REPLACED with its named default (never
 * clamped into range) and surfaces as a bounded configuration warning. The
 * credential itself is never held, rendered, or echoed.
 */
final class ThreadIntelligenceConfigTest extends TestCase
{
    public function test_defaults_match_the_approved_pre_evaluation_posture(): void
    {
        $config = ThreadIntelligenceConfig::fromArray([]);
        self::assertSame('gpt-5.6-luna', $config->model());
        self::assertSame('low', $config->reasoningEffort());
        self::assertSame(100, $config->dailyCallLimit());
        self::assertSame(1_000_000, $config->dailyInputTokenLimit());
        self::assertSame(32_000, $config->maxInputTokens());
        self::assertSame(16_000, $config->maxOutputTokens());
        self::assertSame(5, $config->connectTimeoutSeconds());
        self::assertSame(60, $config->timeoutSeconds());
        self::assertFalse($config->providerReady());
    }

    public function test_defaults_produce_no_configuration_warnings(): void
    {
        self::assertSame([], ThreadIntelligenceConfig::fromArray([])->warnings());
    }

    public function test_accepts_every_locked_reasoning_effort(): void
    {
        foreach (['none', 'low', 'medium', 'high', 'max'] as $effort) {
            $config = ThreadIntelligenceConfig::fromArray(['reasoning_effort' => $effort]);
            self::assertSame($effort, $config->reasoningEffort());
            self::assertSame([], $config->warnings());
        }
    }

    public function test_invalid_reasoning_effort_falls_back_to_low_with_warning(): void
    {
        foreach (['minimal', 'LOW', 'turbo', '', '  ', 42, null, ['low']] as $invalid) {
            $config = ThreadIntelligenceConfig::fromArray(['reasoning_effort' => $invalid]);
            self::assertSame('low', $config->reasoningEffort(), var_export($invalid, true));
            self::assertNotSame([], $config->warnings(), var_export($invalid, true));
        }
    }

    public function test_valid_model_slugs_are_accepted(): void
    {
        foreach (['gpt-5.6-luna', 'gpt-4.1-mini', 'o4:high_v2.x-1', str_repeat('a', 128)] as $slug) {
            $config = ThreadIntelligenceConfig::fromArray(['model' => $slug]);
            self::assertSame($slug, $config->model());
            self::assertSame([], $config->warnings());
        }
    }

    public function test_empty_or_malformed_model_falls_back_with_warning(): void
    {
        foreach (['', '   ', 'bad slug', "model\n", 'model/../etc', str_repeat('a', 129), 7, null] as $invalid) {
            $config = ThreadIntelligenceConfig::fromArray(['model' => $invalid]);
            self::assertSame('gpt-5.6-luna', $config->model(), var_export($invalid, true));
            self::assertNotSame([], $config->warnings(), var_export($invalid, true));
        }
    }

    /**
     * @return array<string, array{key:string, getter:string, default:int, min:int, max:int}>
     */
    public static function numericLimitProvider(): array
    {
        return [
            'daily calls' => ['key' => 'daily_call_limit', 'getter' => 'dailyCallLimit', 'default' => 100, 'min' => 1, 'max' => 10_000],
            'daily input tokens' => ['key' => 'daily_input_token_limit', 'getter' => 'dailyInputTokenLimit', 'default' => 1_000_000, 'min' => 1_000, 'max' => 1_000_000_000],
            'request input tokens' => ['key' => 'max_input_tokens', 'getter' => 'maxInputTokens', 'default' => 32_000, 'min' => 1_000, 'max' => 1_000_000],
            'request output tokens' => ['key' => 'max_output_tokens', 'getter' => 'maxOutputTokens', 'default' => 16_000, 'min' => 1_000, 'max' => 100_000],
            'connect timeout' => ['key' => 'connect_timeout_seconds', 'getter' => 'connectTimeoutSeconds', 'default' => 5, 'min' => 1, 'max' => 30],
            'generation timeout' => ['key' => 'timeout_seconds', 'getter' => 'timeoutSeconds', 'default' => 60, 'min' => 5, 'max' => 300],
        ];
    }

    #[DataProvider('numericLimitProvider')]
    public function test_in_range_limits_are_accepted_as_ints_and_numeric_strings(string $key, string $getter, int $default, int $min, int $max): void
    {
        foreach ([$min, $max] as $boundary) {
            $fromInt = ThreadIntelligenceConfig::fromArray([$key => $boundary]);
            self::assertSame($boundary, $fromInt->$getter());
            self::assertSame([], $fromInt->warnings());

            $fromString = ThreadIntelligenceConfig::fromArray([$key => (string) $boundary]);
            self::assertSame($boundary, $fromString->$getter());
            self::assertSame([], $fromString->warnings());
        }
    }

    #[DataProvider('numericLimitProvider')]
    public function test_zero_negative_nonnumeric_and_out_of_range_limits_fall_back_with_warning(string $key, string $getter, int $default, int $min, int $max): void
    {
        $invalidValues = [0, '0', -5, '-5', 'abc', '', '1e3', '5.5', null, [], $max + 1, (string) ($max + 1)];
        if ($min > 1) {
            $invalidValues[] = $min - 1;
        }
        foreach ($invalidValues as $invalid) {
            $config = ThreadIntelligenceConfig::fromArray([$key => $invalid]);
            self::assertSame($default, $config->$getter(), $key . ' <= ' . var_export($invalid, true));
            self::assertNotSame([], $config->warnings(), $key . ' <= ' . var_export($invalid, true));
        }
    }

    public function test_out_of_range_values_are_replaced_with_the_default_not_clamped(): void
    {
        $config = ThreadIntelligenceConfig::fromArray(['daily_call_limit' => 20_000]);
        self::assertSame(100, $config->dailyCallLimit(), 'unsafe values must be replaced with the named default, not clamped to 10000');

        $config = ThreadIntelligenceConfig::fromArray(['timeout_seconds' => 4]);
        self::assertSame(60, $config->timeoutSeconds(), 'a too-small timeout falls back to 60, not up to 5');
    }

    public function test_provider_ready_requires_a_nonempty_credential(): void
    {
        self::assertFalse(ThreadIntelligenceConfig::fromArray(['api_key' => ''])->providerReady());
        self::assertFalse(ThreadIntelligenceConfig::fromArray(['api_key' => '   '])->providerReady());
        self::assertFalse(ThreadIntelligenceConfig::fromArray(['api_key' => 42])->providerReady());
        self::assertTrue(ThreadIntelligenceConfig::fromArray(['api_key' => 'sk-test-abcdefghijklmnop'])->providerReady());
    }

    public function test_credential_never_appears_in_debug_output_or_warnings(): void
    {
        $secret = 'sk-proj-super-secret-credential-0123456789';
        $config = ThreadIntelligenceConfig::fromArray([
            'api_key' => $secret,
            'model' => 'bad slug',
            'reasoning_effort' => 'bogus',
            'daily_call_limit' => 'abc',
        ]);

        self::assertTrue($config->providerReady());
        self::assertNotSame([], $config->warnings());

        $rendered = print_r($config, true)
            . var_export($config, true)
            . json_encode($config->warnings(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($secret, $rendered);
    }

    public function test_warnings_are_bounded_and_do_not_echo_the_raw_invalid_value(): void
    {
        $raw = 'zz-possibly-pasted-credential-9999999999';
        $config = ThreadIntelligenceConfig::fromArray(['daily_call_limit' => $raw]);

        self::assertSame(100, $config->dailyCallLimit());
        self::assertNotSame([], $config->warnings());
        foreach ($config->warnings() as $warning) {
            self::assertIsString($warning);
            self::assertLessThanOrEqual(255, strlen($warning), 'configuration warnings must stay bounded');
            self::assertStringNotContainsString($raw, $warning, 'warnings must not echo the raw invalid value');
        }
    }
}
