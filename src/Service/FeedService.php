<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\FollowRepository;

/** Query-time Following and Latest feeds. No fan-out storage. */
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
    public function forUser(int $userId, int $page = 1, int $perPage = 20, bool $expanded = true): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $followed = $this->follows->followingIds($userId);
        $followedBoards = $expanded ? $this->follows->followingBoardIds($userId) : [];
        $followedTags = $expanded ? $this->follows->followingTagIds($userId) : [];
        // Defensive: drop anyone in a block relationship with the viewer.
        $blocked = $this->blocks->blockedMap($userId, $followed);
        $followed = array_values(array_filter($followed, static fn (int $id): bool => !isset($blocked[$id])));
        if ($followed === [] && $followedBoards === [] && $followedTags === []) {
            return ['items' => [], 'page' => $page, 'has_more' => false];
        }

        $memberBoardIds = $this->members->boardIdsFor($userId);

        $clauses = [];
        $followParams = [];
        if ($followed !== []) {
            $fPlace = implode(',', array_fill(0, count($followed), '?'));
            $clauses[] = "p.user_id IN ($fPlace)";
            $followParams = array_merge($followParams, $followed);
        }
        if ($followedBoards !== []) {
            $bPlace = implode(',', array_fill(0, count($followedBoards), '?'));
            $clauses[] = "t.board_id IN ($bPlace)";
            $followParams = array_merge($followParams, $followedBoards);
        }
        if ($followedTags !== []) {
            $tPlace = implode(',', array_fill(0, count($followedTags), '?'));
            $clauses[] = "b.tags_enabled = 1 AND EXISTS (
                SELECT 1
                FROM thread_tags tt
                JOIN tags tg ON tg.id = tt.tag_id AND tg.is_enabled = 1
                WHERE tt.thread_id = t.id AND tt.tag_id IN ($tPlace)
            )";
            $followParams = array_merge($followParams, $followedTags);
        }
        $followClause = '(' . implode(' OR ', $clauses) . ')';

        $boardClause = "b.visibility = 'public'";
        $boardParams = [];
        if ($memberBoardIds !== []) {
            $mPlace = implode(',', array_fill(0, count($memberBoardIds), '?'));
            $boardClause = "(b.visibility = 'public' OR (b.visibility = 'private' AND b.id IN ($mPlace)))";
            $boardParams = $memberBoardIds;
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
             WHERE $followClause AND p.is_deleted = 0 AND p.is_pending = 0 AND p.is_anonymous = 0
               AND t.is_deleted = 0 AND t.is_pending = 0
               AND NOT EXISTS (SELECT 1 FROM blocks bl
                               WHERE (bl.user_id = ? AND bl.blocked_user_id = p.user_id)
                                  OR (bl.user_id = p.user_id AND bl.blocked_user_id = ?))
               AND ($boardClause)
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT " . $limit . ' OFFSET ' . $offset,
            array_merge($followParams, [$userId, $userId], $boardParams),
        );

        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }
        return ['items' => $rows, 'page' => $page, 'has_more' => $hasMore];
    }

    /** @return array{items:array<int,array<string,mixed>>, page:int, has_more:bool} */
    public function latest(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $memberBoardIds = $this->members->boardIdsFor($userId);
        $params = [$userId, $userId];

        $boardClause = "b.visibility = 'public'";
        if ($memberBoardIds !== []) {
            $mPlace = implode(',', array_fill(0, count($memberBoardIds), '?'));
            $boardClause = "(b.visibility = 'public' OR (b.visibility = 'private' AND b.id IN ($mPlace)))";
            $params = array_merge($params, $memberBoardIds);
        }

        $offset = ($page - 1) * $perPage;
        $limit = $perPage + 1;

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.thread_id, p.user_id, p.is_op, p.body, p.created_at,
                    t.title AS thread_title, t.slug AS thread_slug, b.slug AS board_slug,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             JOIN users u ON u.id = p.user_id
             WHERE p.is_deleted = 0 AND p.is_pending = 0 AND p.is_anonymous = 0
               AND t.is_deleted = 0 AND t.is_pending = 0
               AND NOT EXISTS (SELECT 1 FROM blocks bl
                               WHERE (bl.user_id = ? AND bl.blocked_user_id = p.user_id)
                                  OR (bl.user_id = p.user_id AND bl.blocked_user_id = ?))
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
