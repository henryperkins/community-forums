<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
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
    public function __construct(
        private Database $db,
        private TagRepository $tags,
    ) {
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
        if ($target === null || (int) ($target['is_enabled'] ?? 0) !== 1) {
            throw new ValidationException(['target_id' => 'Choose a different target tag to merge into.']);
        }

        return [
            'source' => $source,
            'target' => $target,
            'associations' => $this->tags->countAssociationsForTag($sourceId),
        ];
    }

    /** @return array{source:array<string,mixed>,target:array<string,mixed>,association_count:int} */
    public function mergeConfirmation(int $sourceId, int $targetId): array
    {
        $impact = $this->mergeImpact($sourceId, $targetId);
        return [
            'source' => $impact['source'],
            'target' => $impact['target'],
            'association_count' => $impact['associations'],
        ];
    }

    /** Validate and mutate against the same locked tag rows. */
    public function merge(int $sourceId, int $targetId): void
    {
        $this->db->transaction(function () use ($sourceId, $targetId): void {
            $tagIds = array_values(array_unique([$sourceId, $targetId]));
            sort($tagIds, SORT_NUMERIC);
            $locked = [];
            foreach ($tagIds as $tagId) {
                $locked[$tagId] = $this->tags->findForUpdate($tagId);
            }

            $source = $locked[$sourceId] ?? null;
            if ($source === null) {
                throw new NotFoundException('Tag not found.');
            }
            $target = $targetId > 0 && $targetId !== $sourceId ? ($locked[$targetId] ?? null) : null;
            if ($target === null || (int) ($target['is_enabled'] ?? 0) !== 1) {
                throw new ValidationException(['target_id' => 'Choose a different target tag to merge into.']);
            }

            $this->tags->mergeLocked($sourceId, $targetId, (string) $source['slug']);
        });
    }
}
