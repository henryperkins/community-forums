<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Production Responses API provider (ADR 0019): store:false, no tools, the
 * strict structured-output schema, the configured model/effort/output
 * ceiling, and a site-scoped HMAC safety identifier (never a member or
 * thread ID). Classifies every failure into the safe code taxonomy without
 * carrying response bodies past decoding.
 */
final class OpenAiThreadIntelligenceProvider implements ThreadIntelligenceProvider
{
    private const SAFETY_IDENTIFIER_LABEL = 'retroboards-thread-intelligence-site';

    public function __construct(
        private readonly OpenAiTransport $transport,
        private readonly ThreadIntelligenceConfig $config,
        private readonly ThreadIntelligencePromptBuilder $promptBuilder,
        private readonly string $appKey,
    ) {
    }

    public function generate(ThreadIntelligenceRequest $request): ThreadIntelligenceResult
    {
        $payload = [
            'model' => $this->config->model(),
            'reasoning' => ['effort' => $this->config->reasoningEffort()],
            'store' => false,
            'tools' => [],
            'max_output_tokens' => $this->config->maxOutputTokens(),
            'safety_identifier' => hash_hmac('sha256', self::SAFETY_IDENTIFIER_LABEL, $this->appKey),
            'text' => ['format' => ThreadIntelligenceSchema::responseFormat()],
            'input' => $this->promptBuilder->build($request),
        ];

        $response = $this->transport->post('/v1/responses', $payload, $this->config->timeoutSeconds());

        return $this->parse($response);
    }

    private function parse(OpenAiTransportResponse $response): ThreadIntelligenceResult
    {
        $status = $response->statusCode;
        $json = $response->json;

        if ($status === 401 || $status === 403) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::AUTHENTICATION, null, blocksProvider: true);
        }
        if ($status === 429) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::RATE_LIMITED, $response->retryAfterSeconds);
        }
        if ($status === 400 && $this->isInvalidModelError($json)) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::INVALID_MODEL, null, blocksProvider: true);
        }
        if ($status !== 200) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE);
        }
        if ($json === null) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
        }

        // Classify incomplete output before any JSON/schema handling.
        $responseStatus = $json['status'] ?? null;
        if ($responseStatus === 'incomplete') {
            $reason = $json['incomplete_details']['reason'] ?? null;
            if ($reason === 'max_output_tokens') {
                throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED);
            }
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
        }
        if ($responseStatus !== 'completed') {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE);
        }

        $text = $this->outputText($json);
        if ($text === null) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
        }
        $structured = json_decode($text, true, 32);
        if (!is_array($structured)) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
        }

        $responseId = is_string($json['id'] ?? null) ? substr($json['id'], 0, 128) : null;

        return new ThreadIntelligenceResult(
            $structured,
            $responseId,
            ThreadIntelligenceResult::STATUS_COMPLETED,
            null,
            $this->usage($json),
        );
    }

    /** @param array<string,mixed>|null $json */
    private function isInvalidModelError(?array $json): bool
    {
        $error = $json['error'] ?? null;
        if (!is_array($error)) {
            return false;
        }
        return ($error['code'] ?? null) === 'model_not_found' || ($error['param'] ?? null) === 'model';
    }

    /** @param array<string,mixed> $json */
    private function outputText(array $json): ?string
    {
        $output = $json['output'] ?? null;
        if (!is_array($output)) {
            return null;
        }
        foreach ($output as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }
        return null;
    }

    /** @param array<string,mixed> $json */
    private function usage(array $json): ThreadIntelligenceUsage
    {
        $usage = is_array($json['usage'] ?? null) ? $json['usage'] : [];

        return new ThreadIntelligenceUsage(
            $this->intOrNull($usage['input_tokens'] ?? null),
            $this->intOrNull($usage['output_tokens'] ?? null),
            $this->intOrNull($usage['output_tokens_details']['reasoning_tokens'] ?? null),
            $this->intOrNull($usage['input_tokens_details']['cached_tokens'] ?? null),
        );
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
