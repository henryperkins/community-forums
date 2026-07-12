<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;

/**
 * The complete provider request contract — the privacy boundary. Only typed
 * evidence posts and candidates may enter; account, session, report, DM,
 * moderation, and credential data have no field to ride in on. The baseline
 * is the exact published summary (identical at every window); the
 * carry-forward is the prior window's validated intermediate state and is
 * null exactly at window 0.
 */
final readonly class ThreadIntelligenceRequest
{
    private const HASH_PATTERN = '/\A[0-9a-f]{64}\z/';

    /**
     * @param list<ThreadIntelligenceEvidencePost> $posts
     * @param list<ThreadIntelligenceRelatedCandidate> $candidates
     */
    public function __construct(
        public int $threadId,
        public string $threadTitle,
        public ?ThreadIntelligenceBaseline $baseline,
        public ?ThreadIntelligenceCarryForward $carryForward,
        public array $posts,
        public array $candidates,
        public string $sourceSnapshotHash,
        public string $promptVersion,
        public int $windowNumber,
        public int $windowCount,
    ) {
        if ($threadId < 1) {
            throw new InvalidArgumentException('thread id must be positive');
        }
        if (!array_is_list($posts)) {
            throw new InvalidArgumentException('posts must be a list');
        }
        foreach ($posts as $post) {
            if (!$post instanceof ThreadIntelligenceEvidencePost) {
                throw new InvalidArgumentException('posts must be ThreadIntelligenceEvidencePost instances');
            }
        }
        if (!array_is_list($candidates)) {
            throw new InvalidArgumentException('candidates must be a list');
        }
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof ThreadIntelligenceRelatedCandidate) {
                throw new InvalidArgumentException('candidates must be ThreadIntelligenceRelatedCandidate instances');
            }
        }
        if (preg_match(self::HASH_PATTERN, $sourceSnapshotHash) !== 1) {
            throw new InvalidArgumentException('source snapshot hash must be 64 lowercase hex characters');
        }
        if ($promptVersion === '' || strlen($promptVersion) > 64) {
            throw new InvalidArgumentException('prompt version must be a bounded nonempty string');
        }
        if ($windowCount < 1 || $windowNumber < 0 || $windowNumber >= $windowCount) {
            throw new InvalidArgumentException('window number must lie inside the window count');
        }
        if ($windowNumber === 0 && $carryForward !== null) {
            throw new InvalidArgumentException('window 0 must not carry forward prior state');
        }
        if ($windowNumber > 0 && $carryForward === null) {
            throw new InvalidArgumentException('windows after the first require the prior validated carry-forward');
        }
    }
}
