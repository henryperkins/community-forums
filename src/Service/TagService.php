<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Repository\TagRepository;

/**
 * Admin tag-merge orchestration (PR #44 spec §4): the impact preview counts
 * the exact association set mergeInto() moves — every thread_tags row,
 * including hidden, held, and deleted threads — so the admin is never told
 * "1 thread" while 4 move. The merge itself still writes no audit row; that
 * recorded gap stays a Task-10 disposition, not scope here.
 */
final class TagService
{
    public function __construct(private TagRepository $tags)
    {
    }

    /**
     * @return array{source:array<string,mixed>, target:array<string,mixed>, associations:int}
     */
    public function mergeImpact(int $sourceId, int $targetId): array
    {
        $source = $this->tags->find($sourceId);
        if ($source === null) {
            throw new NotFoundException('Tag not found.');
        }
        $target = $targetId > 0 && $targetId !== $sourceId ? $this->tags->find($targetId) : null;
        if ($target === null) {
            throw new ValidationException(['target_id' => 'Choose a different target tag to merge into.']);
        }

        return [
            'source' => $source,
            'target' => $target,
            'associations' => $this->tags->countAssociationsForTag($sourceId),
        ];
    }

    /** Same guards as the preview, then the repository's transactional merge. */
    public function merge(int $sourceId, int $targetId): void
    {
        $this->mergeImpact($sourceId, $targetId);
        $this->tags->mergeInto($sourceId, $targetId);
    }
}
