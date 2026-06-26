<?php

declare(strict_types=1);

namespace App\Search;

use App\Domain\User;

/**
 * Replaceable search interface (DECISIONS §2: MySQL FULLTEXT now, Meilisearch
 * later behind this seam). Implementations MUST apply the read gate before
 * returning or linking to a result (PHASE_2_PLAN §2, §11).
 *
 * A result: ['type'=>'thread'|'post', 'thread_id'=>int, 'slug'=>string,
 *            'title'=>string, 'snippet'=>string (HTML-safe), 'board_slug'=>string,
 *            'board_name'=>string, 'url'=>string, 'score'=>float].
 */
interface SearchService
{
    /** @return array<int,array<string,mixed>> ranked, read-gated results */
    public function search(string $query, ?User $viewer, int $limit = 20): array;
}
