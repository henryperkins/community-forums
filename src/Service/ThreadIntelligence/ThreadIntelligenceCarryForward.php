<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * The intermediate validated state carried from one reconciliation window to
 * the next. Distinct from the published baseline (which never changes across
 * windows): the carry-forward holds only the prior window's validated
 * overview/key points/open questions/related topics and citation IDs. It
 * carries no provider metadata and never replaces the request baseline.
 */
final readonly class ThreadIntelligenceCarryForward
{
    /**
     * @param list<array{markdown:string, source_post_ids:list<int>}> $keyPoints
     * @param list<array{markdown:string, source_post_ids:list<int>}> $openQuestions
     * @param list<array{thread_id:int, explanation:string}> $relatedTopics
     * @param list<int> $sourcePostIds
     */
    private function __construct(
        public string $overview,
        public array $keyPoints,
        public array $openQuestions,
        public array $relatedTopics,
        public array $sourcePostIds,
    ) {
    }

    public static function fromValidated(ValidatedThreadIntelligenceOutput $output): self
    {
        return new self(
            $output->overview(),
            $output->keyPoints(),
            $output->openQuestions(),
            $output->relatedTopics(),
            $output->sourcePostIds(),
        );
    }
}
