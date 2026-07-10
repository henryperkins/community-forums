<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * The exact currently published summary supplied to the model as its baseline:
 * nullable summary ID/version plus the published Markdown and its source post
 * IDs. It never changes across the windows of one reconciliation — only the
 * carry-forward does.
 */
final readonly class ThreadIntelligenceBaseline
{
    /** @param list<int> $sourcePostIds */
    public function __construct(
        public ?int $summaryId,
        public ?int $version,
        public string $markdown,
        public array $sourcePostIds,
    ) {
        if ($summaryId !== null && $summaryId < 1) {
            throw new InvalidArgumentException('baseline summary id must be positive');
        }
        if ($version !== null && $version < 1) {
            throw new InvalidArgumentException('baseline version must be positive');
        }
        if (!array_is_list($sourcePostIds)) {
            throw new InvalidArgumentException('baseline source ids must be a list');
        }
        foreach ($sourcePostIds as $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException('baseline source ids must be positive integers');
            }
        }
    }
}
