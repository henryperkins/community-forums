<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/** Immutable identifiers for one committed Thread Intelligence publication. */
final readonly class ThreadIntelligencePublishResult
{
    public int $summaryId;
    public int $generationId;

    public function __construct(int $summaryId, int $generationId)
    {
        if ($summaryId < 1 || $generationId < 1) {
            throw new InvalidArgumentException('published summary and generation IDs must be positive');
        }
        $this->summaryId = $summaryId;
        $this->generationId = $generationId;
    }
}
