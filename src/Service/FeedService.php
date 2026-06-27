<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\FollowRepository;

/**
 * The Following feed (COMMUNITY §5, P2-09): a paginated, query-time stream of
 * recent posts from the people a member follows. It is computed on read (no
 * fan-out storage) and applies the same gates as everywhere else — deleted
 * content, blocked authors, and boards the viewer can't access are excluded.
 * Only public boards and private boards the viewer belongs to are included;
 * hidden boards are never surfaced through the feed.
 */
final class FeedService
{
    public function __construct(
        private Database $db,
        private FollowRepository $follows,
        private BlockRepository $blocks,
        private BoardMemberRepository $members,
    ) {
    }

    /**
     * @return array{items:array<int,array<string,mixed>>, page:int, has_more:bool}
     */
    public function forUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $followed = $this->follows->followingIds($userId);
        // Defensive: drop anyone in a block relationship with the viewer.
        $blocked = $this->blocks->blockedMap($userId, $followed);
        $followed = array_values(array_filter($followed, static fn (int $id): bool => !isset($blocked[$id])));
        if ($followed === []) {
            return ['items' => [], 'page' => $page, 'has_more' => false];
        }

        $memberBoardIds = $this->members->boardIdsFor($userId);

        $fPlace = implode(',', array_fill(0, count($followed), '?'));
        $params = $followed;

        $boardClause = "b.visibility = 'public'";
        if ($memberBoardIds !== []) {
            $mPlace = implode(',', array_fill(0, count($memberBoardIds), '?'));
            $boardClause = "(b.visibility = 'public' OR (b.visibility = 'private' AND b.id IN ($mPlace)))";
            $params = array_merge($params, $memberBoardIds);
        }

        $offset = ($page - 1) * $perPage;
        // Fetch one extra row to detect a further page without a COUNT.
        $limit = $perPage + 1;

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.thread_id, p.user_id, p.is_op, p.body, p.created_at,
                    t.title AS thread_title, t.slug AS thread_slug, b.slug AS board_slug,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id IN ($fPlace) AND p.is_deleted = 0 AND p.is_anonymous = 0 AND t.is_deleted = 0
               AND ($boardClause)
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT " . $limit . ' OFFSET ' . $offset,
            $params,
        );

        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }
        return ['items' => $rows, 'page' => $page, 'has_more' => $hasMore];
    }
}
