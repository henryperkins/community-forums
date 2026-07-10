<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * One locally retrieved related-topic candidate: thread ID, title, bounded
 * public opener excerpt, shared enabled tags, deterministic scores/rank, and
 * last activity time. No author or private data may enter this shape.
 */
final readonly class ThreadIntelligenceRelatedCandidate
{
    private const TIME_PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/';

    /** @param list<string> $sharedTags */
    public function __construct(
        public int $threadId,
        public string $title,
        public string $excerpt,
        public array $sharedTags,
        public int $sharedTagCount,
        public float $relevance,
        public int $rank,
        public string $lastActivityAtUtc,
    ) {
        if ($threadId < 1) {
            throw new InvalidArgumentException('candidate thread id must be positive');
        }
        if (mb_strlen($excerpt) > 500) {
            throw new InvalidArgumentException('candidate excerpt is bounded to 500 characters');
        }
        if (!array_is_list($sharedTags)) {
            throw new InvalidArgumentException('shared tags must be a list');
        }
        foreach ($sharedTags as $tag) {
            if (!is_string($tag) || $tag === '') {
                throw new InvalidArgumentException('shared tags must be nonempty strings');
            }
        }
        if ($sharedTagCount < 0 || $rank < 1) {
            throw new InvalidArgumentException('candidate scores must be plausible');
        }
        if (preg_match(self::TIME_PATTERN, $lastActivityAtUtc) !== 1) {
            throw new InvalidArgumentException('activity time must be ISO-8601 UTC (YYYY-MM-DDTHH:MM:SSZ)');
        }
    }
}
