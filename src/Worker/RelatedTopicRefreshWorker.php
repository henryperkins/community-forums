<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Database;
use App\Core\FeatureFlags;

final class RelatedTopicRefreshWorker
{
    public function __construct(
        private Database $db,
        private FeatureFlags $flags,
    ) {
    }

    /** @return array{linked:int,skipped:int} */
    public function run(int $limit = 250): array
    {
        if (!$this->flags->enabled('community_memory')
            || !$this->flags->enabled('automated_context')
            || !$this->flags->enabled('tags')) {
            return ['linked' => 0, 'skipped' => 1];
        }

        $limit = max(1, min(1000, $limit));
        $pairs = $this->db->fetchAll(
            "SELECT
                t1.id AS source_thread_id,
                t2.id AS related_thread_id,
                COUNT(DISTINCT tag.id) AS shared_tags,
                GROUP_CONCAT(DISTINCT tag.name ORDER BY tag.name SEPARATOR ', ') AS tag_names
             FROM thread_tags tt1
             JOIN thread_tags tt2 ON tt2.tag_id = tt1.tag_id AND tt2.thread_id <> tt1.thread_id
             JOIN tags tag ON tag.id = tt1.tag_id AND tag.is_enabled = 1
             JOIN threads t1 ON t1.id = tt1.thread_id
             JOIN threads t2 ON t2.id = tt2.thread_id
             JOIN boards b1 ON b1.id = t1.board_id
             JOIN boards b2 ON b2.id = t2.board_id
             WHERE t1.is_deleted = 0 AND t1.is_pending = 0
               AND t2.is_deleted = 0 AND t2.is_pending = 0
               AND b1.visibility = 'public' AND b2.visibility = 'public'
               AND b1.tags_enabled = 1 AND b2.tags_enabled = 1
             GROUP BY t1.id, t2.id
             ORDER BY t1.id ASC, shared_tags DESC, t2.id ASC
             LIMIT " . $limit,
        );

        $linked = 0;
        foreach ($pairs as $pair) {
            $tags = trim((string) ($pair['tag_names'] ?? ''));
            $reason = ((int) $pair['shared_tags'] === 1 ? 'Shares tag: ' : 'Shares tags: ') . $tags;
            $linked += $this->db->run(
                "INSERT INTO related_threads
                    (source_thread_id, related_thread_id, relation_type, source, score, reason, status, curator_id, created_at)
                 VALUES (?, ?, 'related', 'tag', ?, ?, 'approved', NULL, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    score = IF(source = 'tag', VALUES(score), score),
                    reason = IF(source = 'tag', VALUES(reason), reason),
                    status = IF(source = 'tag', 'approved', status)",
                [
                    (int) $pair['source_thread_id'],
                    (int) $pair['related_thread_id'],
                    (float) $pair['shared_tags'],
                    mb_substr($reason, 0, 255),
                ],
            )->rowCount() > 0 ? 1 : 0;
        }

        return ['linked' => $linked, 'skipped' => 0];
    }
}
