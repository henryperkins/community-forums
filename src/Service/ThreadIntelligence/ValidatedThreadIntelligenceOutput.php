<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * The ONLY shape trusted past the validator. Exposes exactly the product
 * contract: server-composed canonical Markdown, the moderation text (brief +
 * every related explanation), citation/candidate unions, and the validated
 * component fields. Constructed by ThreadIntelligenceOutputValidator.
 */
final readonly class ValidatedThreadIntelligenceOutput
{
    /**
     * @param list<array{markdown:string, source_post_ids:list<int>}> $keyPoints
     * @param list<array{markdown:string, source_post_ids:list<int>}> $openQuestions
     * @param list<array{thread_id:int, explanation:string}> $relatedTopics
     * @param list<int> $sourcePostIds ascending unique union of every citation
     * @param list<int> $relatedThreadIds
     */
    public function __construct(
        private string $canonicalMarkdown,
        private string $moderationText,
        private string $overview,
        private array $keyPoints,
        private array $openQuestions,
        private array $relatedTopics,
        private array $sourcePostIds,
        private array $relatedThreadIds,
    ) {
    }

    public function canonicalMarkdown(): string
    {
        return $this->canonicalMarkdown;
    }

    public function moderationText(): string
    {
        return $this->moderationText;
    }

    public function overview(): string
    {
        return $this->overview;
    }

    /** @return list<array{markdown:string, source_post_ids:list<int>}> */
    public function keyPoints(): array
    {
        return $this->keyPoints;
    }

    /** @return list<array{markdown:string, source_post_ids:list<int>}> */
    public function openQuestions(): array
    {
        return $this->openQuestions;
    }

    /** @return list<array{thread_id:int, explanation:string}> */
    public function relatedTopics(): array
    {
        return $this->relatedTopics;
    }

    /** @return list<int> */
    public function sourcePostIds(): array
    {
        return $this->sourcePostIds;
    }

    /** @return list<int> */
    public function relatedThreadIds(): array
    {
        return $this->relatedThreadIds;
    }
}
