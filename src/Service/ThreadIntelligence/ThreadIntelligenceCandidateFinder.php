<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;

/**
 * Deterministic, public-only related-topic retrieval.
 *
 * FULLTEXT follows MysqlSearchService's locked trim, three-character minimum,
 * public/live gate, and IN NATURAL LANGUAGE MODE conventions. Candidate
 * scoring cannot call its presentation-limited search() result: that method
 * discards hits before per-thread aggregation. Instead this query computes the
 * complete target score (title hit + best eligible post-body hit), applies the
 * entire stable order in SQL, and repeats the final ID tie-break in PHP.
 */
final class ThreadIntelligenceCandidateFinder
{
    private const MAX_CANDIDATES = 20;
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<ThreadIntelligenceRelatedCandidate> */
    public function find(int $threadId, int $limit = self::MAX_CANDIDATES): array
    {
        $source = $this->db->fetch(
            "SELECT t.id, t.title, t.board_id, b.category_id, b.tags_enabled
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             WHERE t.id = ? AND t.is_deleted = 0 AND t.is_pending = 0
               AND b.visibility = 'public'",
            [$threadId],
        );
        if ($source === null) {
            return [];
        }

        $limit = max(1, min(self::MAX_CANDIDATES, $limit));
        $query = trim((string) $source['title']);
        $params = [
            'tag_source_thread' => $threadId,
            'source_tags_enabled' => (int) $source['tags_enabled'],
            'source_thread' => $threadId,
            'curated_source_thread' => $threadId,
        ];
        $relevanceSql = '0.0';
        if (mb_strlen($query) >= 3) {
            $relevanceSql = "MATCH(t.title) AGAINST (:title_query IN NATURAL LANGUAGE MODE)
                + COALESCE((
                    SELECT MAX(MATCH(relevance_post.body) AGAINST (:body_query IN NATURAL LANGUAGE MODE))
                    FROM posts relevance_post
                    WHERE relevance_post.thread_id = t.id
                      AND relevance_post.is_deleted = 0
                      AND relevance_post.is_pending = 0
                ), 0.0)";
            $params['title_query'] = $query;
            $params['body_query'] = $query;
        }

        $sourceBoardId = (int) $source['board_id'];
        $sourceCategoryId = (int) $source['category_id'];
        $params['source_board'] = $sourceBoardId;
        $params['source_category'] = $sourceCategoryId;

        // Rank IDs/scores first. Opening MEDIUMTEXT bodies are fetched only for
        // the final bounded targets below, so PHP memory does not scale with the
        // forum's total public thread count.
        $ranked = $this->db->fetchAll(
            "SELECT t.id AS thread_id, t.title,
                    COALESCE(t.last_post_at, t.created_at) AS activity_at,
                    COALESCE(shared.shared_tag_count, 0) AS shared_tag_count,
                    ($relevanceSql) AS relevance,
                    CASE
                        WHEN t.board_id = :source_board THEN 0
                        WHEN b.category_id = :source_category THEN 1
                        ELSE 2
                    END AS scope
             FROM threads t
             JOIN boards b ON b.id = t.board_id AND b.visibility = 'public'
             LEFT JOIN (
                 SELECT target.thread_id, COUNT(DISTINCT tag.id) AS shared_tag_count
                 FROM thread_tags source_tag
                 JOIN tags tag ON tag.id = source_tag.tag_id
                   AND tag.is_enabled = 1 AND tag.visibility = 'public'
                 JOIN thread_tags target ON target.tag_id = source_tag.tag_id
                   AND target.thread_id <> source_tag.thread_id
                 JOIN threads target_thread ON target_thread.id = target.thread_id
                   AND target_thread.is_deleted = 0 AND target_thread.is_pending = 0
                 JOIN boards target_board ON target_board.id = target_thread.board_id
                   AND target_board.visibility = 'public' AND target_board.tags_enabled = 1
                 WHERE source_tag.thread_id = :tag_source_thread
                   AND :source_tags_enabled = 1
                 GROUP BY target.thread_id
             ) shared ON shared.thread_id = t.id
             WHERE t.id <> :source_thread
               AND t.is_deleted = 0 AND t.is_pending = 0
               AND EXISTS (
                   SELECT 1 FROM posts opener
                   WHERE opener.thread_id = t.id AND opener.is_op = 1
                     AND opener.is_deleted = 0 AND opener.is_pending = 0
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM related_threads rt
                   WHERE rt.source_thread_id = :curated_source_thread
                     AND rt.related_thread_id = t.id
                     AND rt.source = 'curated'
                     AND rt.status = 'approved'
               )
             ORDER BY shared_tag_count DESC, relevance DESC, scope ASC,
                      activity_at DESC, t.id ASC
             LIMIT $limit",
            $params,
        );
        if ($ranked === []) {
            return [];
        }

        $targetIds = array_map(static fn (array $row): int => (int) $row['thread_id'], $ranked);
        $sharedTags = $this->sharedTags(
            $threadId,
            (int) $source['tags_enabled'] === 1,
            $targetIds,
        );
        $payloads = $this->candidatePayloads($targetIds);
        $ranked = array_map(static fn (array $row): array => [
            'thread_id' => (int) $row['thread_id'],
            'title' => (string) $row['title'],
            'shared_tag_count' => (int) $row['shared_tag_count'],
            'relevance' => (float) $row['relevance'],
            'scope' => (int) $row['scope'],
            'activity_at' => (string) $row['activity_at'],
        ], $ranked);
        $ranked = array_values(array_filter(
            $ranked,
            static fn (array $row): bool => isset($payloads[$row['thread_id']]),
        ));

        usort($ranked, static function (array $left, array $right): int {
            return ($right['shared_tag_count'] <=> $left['shared_tag_count'])
                ?: ($right['relevance'] <=> $left['relevance'])
                ?: ($left['scope'] <=> $right['scope'])
                ?: strcmp($right['activity_at'], $left['activity_at'])
                ?: ($left['thread_id'] <=> $right['thread_id']);
        });

        return array_map(fn (array $candidate, int $index): ThreadIntelligenceRelatedCandidate =>
            new ThreadIntelligenceRelatedCandidate(
                threadId: $candidate['thread_id'],
                title: $payloads[$candidate['thread_id']]['title'],
                excerpt: $this->plainExcerpt($payloads[$candidate['thread_id']]['body']),
                sharedTags: $sharedTags[$candidate['thread_id']] ?? [],
                sharedTagCount: $candidate['shared_tag_count'],
                relevance: $candidate['relevance'],
                rank: $index + 1,
                lastActivityAtUtc: str_replace(' ', 'T', $candidate['activity_at']) . 'Z',
            ),
            $ranked,
            array_keys($ranked),
        );
    }

    /** @param list<int> $targetIds @return array<int,list<string>> thread ID => canonical shared tag names */
    private function sharedTags(int $sourceThreadId, bool $sourceTagsEnabled, array $targetIds): array
    {
        if (!$sourceTagsEnabled || $targetIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT target.thread_id, tag.id AS tag_id, tag.name
             FROM thread_tags source
             JOIN tags tag ON tag.id = source.tag_id
               AND tag.is_enabled = 1 AND tag.visibility = 'public'
             JOIN thread_tags target ON target.tag_id = source.tag_id
               AND target.thread_id <> source.thread_id
             JOIN threads target_thread ON target_thread.id = target.thread_id
               AND target_thread.is_deleted = 0 AND target_thread.is_pending = 0
             JOIN boards target_board ON target_board.id = target_thread.board_id
               AND target_board.visibility = 'public' AND target_board.tags_enabled = 1
             WHERE source.thread_id = ? AND target.thread_id IN ($placeholders)
             ORDER BY target.thread_id ASC, tag.name ASC, tag.id ASC",
            [$sourceThreadId, ...$targetIds],
        );

        $byThread = [];
        $seen = [];
        foreach ($rows as $row) {
            $targetId = (int) $row['thread_id'];
            $tagId = (int) $row['tag_id'];
            if (isset($seen[$targetId][$tagId])) {
                continue;
            }
            $seen[$targetId][$tagId] = true;
            $byThread[$targetId][] = (string) $row['name'];
        }
        return $byThread;
    }

    /** @param list<int> $targetIds @return array<int,array{title:string,body:string}> */
    private function candidatePayloads(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT target.id AS thread_id, target.title, opener.body
             FROM threads target
             JOIN boards target_board ON target_board.id = target.board_id
               AND target_board.visibility = 'public'
             JOIN posts opener ON opener.thread_id = target.id
               AND opener.is_op = 1
               AND opener.is_deleted = 0
               AND opener.is_pending = 0
             WHERE target.id IN ($placeholders)
               AND target.is_deleted = 0
               AND target.is_pending = 0
               AND opener.id = (
                   SELECT MIN(first_opener.id)
                   FROM posts first_opener
                   WHERE first_opener.thread_id = target.id
                     AND first_opener.is_op = 1
                     AND first_opener.is_deleted = 0
                     AND first_opener.is_pending = 0
               )
             ORDER BY target.id ASC
             LOCK IN SHARE MODE",
            $targetIds,
        );
        $payloads = [];
        foreach ($rows as $row) {
            $payloads[(int) $row['thread_id']] = [
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
            ];
        }
        return $payloads;
    }

    private function plainExcerpt(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/!\[([^]]*)]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^]]+)]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/[`#>*_~\[\]()]+/u', ' ', $text) ?? $text;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return mb_substr($text, 0, 500);
    }
}
