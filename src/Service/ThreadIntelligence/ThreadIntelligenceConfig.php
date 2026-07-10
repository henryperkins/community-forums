<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Validated Thread Intelligence configuration (ADR 0019).
 *
 * Normalizes the operator-supplied `thread_intelligence` config block to
 * conservative typed defaults. An invalid or out-of-range value is REPLACED
 * with its named default — never clamped into range — and surfaces as a
 * bounded configuration warning that names the setting without echoing the
 * raw value (a mis-pasted credential must not leak through a warning).
 *
 * The credential itself is never held here: only its readiness. The transport
 * receives the raw key directly at composition time (App::buildContainer()).
 */
final class ThreadIntelligenceConfig
{
    public const DEFAULT_MODEL = 'gpt-5.6-luna';
    public const DEFAULT_REASONING_EFFORT = 'low';
    public const DEFAULT_DAILY_CALL_LIMIT = 100;
    public const DEFAULT_DAILY_INPUT_TOKEN_LIMIT = 1_000_000;
    public const DEFAULT_MAX_INPUT_TOKENS = 32_000;
    public const DEFAULT_MAX_OUTPUT_TOKENS = 16_000;
    public const DEFAULT_CONNECT_TIMEOUT_SECONDS = 5;
    public const DEFAULT_TIMEOUT_SECONDS = 60;

    public const REASONING_EFFORTS = ['none', 'low', 'medium', 'high', 'max'];
    // \A..\z (not ^..$): PCRE's $ would accept a trailing newline.
    private const MODEL_PATTERN = '/\A[A-Za-z0-9._:-]{1,128}\z/';

    /** @param list<string> $warnings */
    private function __construct(
        private bool $providerReady,
        private string $model,
        private string $reasoningEffort,
        private int $dailyCallLimit,
        private int $dailyInputTokenLimit,
        private int $maxInputTokens,
        private int $maxOutputTokens,
        private int $connectTimeoutSeconds,
        private int $timeoutSeconds,
        private array $warnings,
    ) {
    }

    /** @param array<string,mixed> $config the `thread_intelligence` config block */
    public static function fromArray(array $config): self
    {
        $warnings = [];

        $apiKey = $config['api_key'] ?? '';
        $providerReady = is_string($apiKey) && trim($apiKey) !== '';

        // Strict, untrimmed validation: the Env loader already trims values, so
        // embedded whitespace here means a malformed setting, not sloppiness.
        $model = self::DEFAULT_MODEL;
        if (array_key_exists('model', $config)) {
            $candidate = $config['model'];
            if (is_string($candidate) && preg_match(self::MODEL_PATTERN, $candidate) === 1) {
                $model = $candidate;
            } else {
                $warnings[] = 'thread_intelligence.model is invalid; using default ' . self::DEFAULT_MODEL;
            }
        }

        $effort = self::DEFAULT_REASONING_EFFORT;
        if (array_key_exists('reasoning_effort', $config)) {
            $candidate = $config['reasoning_effort'];
            if (is_string($candidate) && in_array($candidate, self::REASONING_EFFORTS, true)) {
                $effort = $candidate;
            } else {
                $warnings[] = 'thread_intelligence.reasoning_effort is invalid; using default ' . self::DEFAULT_REASONING_EFFORT;
            }
        }

        $dailyCallLimit = self::intInRange($config, 'daily_call_limit', 1, 10_000, self::DEFAULT_DAILY_CALL_LIMIT, $warnings);
        $dailyInputTokenLimit = self::intInRange($config, 'daily_input_token_limit', 1_000, 1_000_000_000, self::DEFAULT_DAILY_INPUT_TOKEN_LIMIT, $warnings);
        $maxInputTokens = self::intInRange($config, 'max_input_tokens', 1_000, 1_000_000, self::DEFAULT_MAX_INPUT_TOKENS, $warnings);
        $maxOutputTokens = self::intInRange($config, 'max_output_tokens', 1_000, 100_000, self::DEFAULT_MAX_OUTPUT_TOKENS, $warnings);
        $connectTimeoutSeconds = self::intInRange($config, 'connect_timeout_seconds', 1, 30, self::DEFAULT_CONNECT_TIMEOUT_SECONDS, $warnings);
        $timeoutSeconds = self::intInRange($config, 'timeout_seconds', 5, 300, self::DEFAULT_TIMEOUT_SECONDS, $warnings);

        return new self(
            $providerReady,
            $model,
            $effort,
            $dailyCallLimit,
            $dailyInputTokenLimit,
            $maxInputTokens,
            $maxOutputTokens,
            $connectTimeoutSeconds,
            $timeoutSeconds,
            $warnings,
        );
    }

    /**
     * Accepts an int or an integer-shaped string inside [$min,$max]; anything
     * else falls back to $default with a bounded warning naming the setting.
     *
     * @param array<string,mixed> $config
     * @param list<string> $warnings
     */
    private static function intInRange(array $config, string $key, int $min, int $max, int $default, array &$warnings): int
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        $int = null;
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            $int = (int) trim($value);
        }

        if ($int === null || $int < $min || $int > $max) {
            $warnings[] = "thread_intelligence.$key is invalid or out of range ($min-$max); using default $default";
            return $default;
        }

        return $int;
    }

    public function providerReady(): bool
    {
        return $this->providerReady;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function reasoningEffort(): string
    {
        return $this->reasoningEffort;
    }

    public function dailyCallLimit(): int
    {
        return $this->dailyCallLimit;
    }

    public function dailyInputTokenLimit(): int
    {
        return $this->dailyInputTokenLimit;
    }

    public function maxInputTokens(): int
    {
        return $this->maxInputTokens;
    }

    public function maxOutputTokens(): int
    {
        return $this->maxOutputTokens;
    }

    public function connectTimeoutSeconds(): int
    {
        return $this->connectTimeoutSeconds;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    /** @return list<string> bounded, credential-free operator warnings */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
