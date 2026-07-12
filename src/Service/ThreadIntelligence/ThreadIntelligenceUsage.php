<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * Token usage as reported by the provider; every count is nullable because a
 * provider may omit any of them. Only these four integers ever reach the
 * evidence ledger or logs — never a payload.
 */
final readonly class ThreadIntelligenceUsage
{
    public function __construct(
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?int $reasoningTokens,
        public ?int $cachedTokens,
    ) {
        foreach ([$inputTokens, $outputTokens, $reasoningTokens, $cachedTokens] as $count) {
            if ($count !== null && $count < 0) {
                throw new InvalidArgumentException('token counts must be nonnegative');
            }
        }
    }
}
