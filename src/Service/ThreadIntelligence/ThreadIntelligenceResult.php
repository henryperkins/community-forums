<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * Provider-independent generation result: the decoded structured output, an
 * opaque bounded response ID, the completion status/reason, and usage counts.
 * It never carries a raw response body.
 */
final readonly class ThreadIntelligenceResult
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INCOMPLETE = 'incomplete';

    /** @param array<string,mixed> $output decoded structured output */
    public function __construct(
        public array $output,
        public ?string $responseId,
        public string $status,
        public ?string $incompleteReason,
        public ThreadIntelligenceUsage $usage,
    ) {
        if (!in_array($status, [self::STATUS_COMPLETED, self::STATUS_INCOMPLETE], true)) {
            throw new InvalidArgumentException('status must be completed or incomplete');
        }
        if ($responseId !== null && strlen($responseId) > 128) {
            throw new InvalidArgumentException('response id must stay bounded');
        }
        if ($incompleteReason !== null && strlen($incompleteReason) > 64) {
            throw new InvalidArgumentException('incomplete reason must stay bounded');
        }
    }
}
