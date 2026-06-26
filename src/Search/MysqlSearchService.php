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

    public function search(string $query, ?User $viewer, int $limit = 20): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            // Below the InnoDB FULLTEXT min token size — no results rather than a scan.
            return [];
        }
        $limit = max(1, min(50, $limit));

        [$visSql, $visParams] = $this->visibility($viewer);

        $threads = $this->db->fetchAll(
            "SELECT 'thread' AS type, t.id AS thread_id, t.slug, t.title, b.slug AS board_slug, b.name AS board_name,
                    MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
             FROM threads t JOIN boards b ON b.id = t.board_id
             WHERE t.is_deleted = 0 AND ($visSql)
               AND MATCH(t.title) AGAINST (? IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC LIMIT " . $limit,
            array_merge([$query], $visParams, [$query]),
        );

        $posts = $this->db->fetchAll(
            "SELECT 'post' AS type, p.id AS post_id, p.body, p.thread_id, t.slug AS thread_slug, t.title,
                    b.slug AS board_slug, b.name AS board_name,
                    MATCH(p.body) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.is_deleted = 0 AND t.is_deleted = 0 AND ($visSql)
               AND MATCH(p.body) AGAINST (? IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC LIMIT " . $limit,
            array_merge([$query], $visParams, [$query]),
        );

        $results = [];
        foreach ($threads as $r) {
            $results[] = [
                'type' => 'thread',
                'thread_id' => (int) $r['thread_id'],
                'slug' => (string) $r['slug'],
                'title' => (string) $r['title'],
                'snippet' => '',
                'board_slug' => (string) $r['board_slug'],
                'board_name' => (string) $r['board_name'],
                'url' => '/t/' . (int) $r['thread_id'] . '-' . $r['slug'],
                'score' => (float) $r['score'],
            ];
        }
        foreach ($posts as $r) {
            $results[] = [
                'type' => 'post',
                'thread_id' => (int) $r['thread_id'],
                'slug' => (string) $r['thread_slug'],
                'title' => (string) $r['title'],
                'snippet' => $this->snippet((string) $r['body'], $query),
                'board_slug' => (string) $r['board_slug'],
                'board_name' => (string) $r['board_name'],
                'url' => '/t/' . (int) $r['thread_id'] . '-' . $r['thread_slug'] . '#p' . (int) $r['post_id'],
                'score' => (float) $r['score'],
            ];
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
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
