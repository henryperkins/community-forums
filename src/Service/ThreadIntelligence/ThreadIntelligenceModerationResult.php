<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * Output-moderation verdict: flagged or not, plus bounded category names.
 * Nothing else from the moderation response crosses this boundary.
 */
final readonly class ThreadIntelligenceModerationResult
{
    /** @param list<string> $flaggedCategories */
    public function __construct(
        public bool $flagged,
        public array $flaggedCategories = [],
    ) {
        if (!array_is_list($flaggedCategories)) {
            throw new InvalidArgumentException('flagged categories must be a list');
        }
        if (count($flaggedCategories) > 32) {
            throw new InvalidArgumentException('flagged categories must stay bounded');
        }
        foreach ($flaggedCategories as $category) {
            if (!is_string($category) || $category === '' || strlen($category) > 64) {
                throw new InvalidArgumentException('each flagged category must be a bounded string');
            }
        }
    }
}
