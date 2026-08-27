<?php

declare(strict_types=1);

namespace App\Search;

use App\Core\Database;
use App\Domain\User;

/**
 * MySQL FULLTEXT search over thread titles (ft_threads_title) and post bodies
 * (ft_posts_body), built in P2-06.
 *
 * Read gate (mirrors BoardPolicy::isListed — search is discovery, so hidden
 * boards are excluded like any other listing): guests see public only; a member
 * also sees private boards they belong to; an admin sees all. Deleted/pending
 * threads and posts are always excluded, and snippets are derived from the
 * canonical Markdown and HTML-escaped (no stored HTML is echoed).
 */
final class MysqlSearchService implements SearchService
{
    public function __construct(private Database $db)
    {
    }

    public function search(SearchQuery $query, ?User $viewer): array
    {
        if (!$query->isSearchable() || ($query->scope === 'mine' && $viewer === null)) {
            return [];
        }

        [$visSql, $visParams] = $this->visibility($viewer);
        $branches = [];
        $params = [];

        if (in_array($query->scope, ['everything', 'topics', 'mine'], true)) {
            $mineSql = $query->scope === 'mine' ? ' AND t.user_id = ?' : '';
            $branches[] = "
                SELECT 'thread' AS result_type, NULL AS post_id,
                       t.id AS thread_id, t.slug, t.title,
                       b.slug AS board_slug, b.name AS board_name,
                       op.body AS snippet_body, t.created_at, t.user_id AS author_id,
                       MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
                FROM threads t
                JOIN boards b ON b.id = t.board_id
                LEFT JOIN posts op
                  ON op.thread_id = t.id
                 AND op.is_op = 1
                 AND op.is_deleted = 0
                 AND op.is_pending = 0
                WHERE t.is_deleted = 0
                  AND t.is_pending = 0
                  AND ($visSql)
                  AND MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE)
                  $mineSql";
            $params[] = $query->query;
            array_push($params, ...$visParams);
            $params[] = $query->query;
            if ($query->scope === 'mine') {
                $params[] = $viewer->id();
            }
        }

        if (in_array($query->scope, ['everything', 'replies', 'mine'], true)) {
            $mineSql = $query->scope === 'mine' ? ' AND p.user_id = ?' : '';
            $branches[] = "
                SELECT 'post' AS result_type, p.id AS post_id,
                       p.thread_id, t.slug, t.title,
                       b.slug AS board_slug, b.name AS board_name,
                       p.body AS snippet_body, p.created_at, p.user_id AS author_id,
                       MATCH(p.body) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
                FROM posts p
                JOIN threads t ON t.id = p.thread_id
                JOIN boards b ON b.id = t.board_id
                WHERE p.is_op = 0
                  AND p.is_deleted = 0
                  AND p.is_pending = 0
                  AND t.is_deleted = 0
                  AND t.is_pending = 0
                  AND ($visSql)
                  AND MATCH(p.body) AGAINST (? IN NATURAL LANGUAGE MODE)
                  $mineSql";
            $params[] = $query->query;
            array_push($params, ...$visParams);
            $params[] = $query->query;
            if ($query->scope === 'mine') {
                $params[] = $viewer->id();
            }
        }

        $order = $query->order === 'newest'
            ? "ranked.created_at DESC,
               CASE WHEN ranked.result_type = 'thread' THEN 0 ELSE 1 END,
               ranked.thread_id DESC, COALESCE(ranked.post_id, 0) DESC"
            : "ranked.score DESC, ranked.created_at DESC,
               CASE WHEN ranked.result_type = 'thread' THEN 0 ELSE 1 END,
               ranked.thread_id DESC, COALESCE(ranked.post_id, 0) DESC";
        $rows = $this->db->fetchAll(
            'SELECT ranked.*
             FROM (' . implode("\nUNION ALL\n", $branches) . ") ranked
             ORDER BY $order
             LIMIT " . $query->limit,
            $params,
        );

        return array_map(function (array $row) use ($query): array {
            $type = (string) $row['result_type'];
            $threadId = (int) $row['thread_id'];
            $postId = $row['post_id'] === null ? null : (int) $row['post_id'];
            $slug = (string) $row['slug'];
            return [
                'type' => $type,
                'post_id' => $postId,
                'thread_id' => $threadId,
                'slug' => $slug,
                'title' => (string) $row['title'],
                'snippet' => $this->snippet((string) ($row['snippet_body'] ?? ''), $query->query),
                'board_slug' => (string) $row['board_slug'],
                'board_name' => (string) $row['board_name'],
                'url' => '/t/' . $threadId . '-' . $slug . ($postId === null ? '' : '#p' . $postId),
                'score' => (float) $row['score'],
                'created_at' => (string) $row['created_at'],
                'author_id' => (int) $row['author_id'],
            ];
        }, $rows);
    }

    /**
     * @return array{0:string,1:list<mixed>} visibility SQL fragment + bound params
     */
    private function visibility(?User $viewer): array
    {
        if ($viewer !== null && $viewer->isAdmin()) {
            return ['1=1', []];
        }
        if ($viewer === null) {
            return ["b.visibility = 'public'", []];
        }
        return [
            "(b.visibility = 'public'
              OR (b.visibility = 'private'
                  AND EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = ?)))",
            [$viewer->id()],
        ];
    }

    /** A short, plain-text, HTML-escaped snippet from Markdown, windowed on the query. */
    private function snippet(string $body, string $query): string
    {
        // Strip code fences / inline code / markdown punctuation, collapse space.
        $text = preg_replace('/```.*?```/s', ' ', $body) ?? $body;
        $text = preg_replace('/`[^`]*`/', ' ', $text) ?? $text;
        $text = preg_replace('/[#>*_~\[\]()`]+/', ' ', $text) ?? $text;
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        $term = (string) (preg_split('/\s+/', $query)[0] ?? '');
        $pos = $term !== '' ? mb_stripos($text, $term) : false;
        $start = $pos === false ? 0 : max(0, $pos - 60);
        $snippet = mb_substr($text, $start, 180);
        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if (mb_strlen($text) > $start + 180) {
            $snippet .= '…';
        }
        return htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
