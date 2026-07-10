<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Production Moderations API check (ADR 0019): runs after schema validation
 * and before publication over the complete canonical brief plus every related
 * explanation. Fails CLOSED — any transport, status, or shape problem throws
 * `moderation_transport` so an unmoderated result can never publish. Exposes
 * only the flagged verdict and bounded category names.
 */
final class OpenAiThreadIntelligenceOutputModerator implements ThreadIntelligenceOutputModerator
{
    private const MODEL = 'omni-moderation-latest';
    private const TIMEOUT_SECONDS = 15;
    private const MAX_CATEGORIES = 32;
    private const MAX_CATEGORY_LENGTH = 64;

    public function __construct(private readonly OpenAiTransport $transport)
    {
    }

    public function moderate(string $text): ThreadIntelligenceModerationResult
    {
        try {
            $response = $this->transport->post(
                '/v1/moderations',
                ['model' => self::MODEL, 'input' => $text],
                self::TIMEOUT_SECONDS,
            );
        } catch (ThreadIntelligenceProviderException) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT);
        }

        if ($response->statusCode !== 200 || $response->json === null) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT);
        }

        $result = $response->json['results'][0] ?? null;
        if (!is_array($result) || !is_bool($result['flagged'] ?? null)) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT);
        }

        $categories = [];
        foreach (is_array($result['categories'] ?? null) ? $result['categories'] : [] as $name => $flagged) {
            if ($flagged !== true || !is_string($name) || $name === '') {
                continue;
            }
            $categories[] = substr($name, 0, self::MAX_CATEGORY_LENGTH);
            if (count($categories) >= self::MAX_CATEGORIES) {
                break;
            }
        }

        return new ThreadIntelligenceModerationResult($result['flagged'], $categories);
    }
}
