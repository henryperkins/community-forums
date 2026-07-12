<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

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
        foreach ([
            'key points' => $keyPoints,
            'open questions' => $openQuestions,
            'related topics' => $relatedTopics,
            'source post ids' => $sourcePostIds,
            'related thread ids' => $relatedThreadIds,
        ] as $name => $items) {
            if (!array_is_list($items)) {
                throw new InvalidArgumentException($name . ' must be a list');
            }
        }
        foreach ([...$keyPoints, ...$openQuestions] as $item) {
            if (!is_array($item)
                || !array_key_exists('source_post_ids', $item)
                || !is_array($item['source_post_ids'])
                || !array_is_list($item['source_post_ids'])) {
                throw new InvalidArgumentException('validated item source post ids must be a list');
            }
        }
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
