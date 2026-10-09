<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Emoji reactions (P2-02). The unique key uq_reaction (post_id, user_id, emoji)
 * makes every operation idempotent: a toggle can never create a duplicate row.
 */
final class ReactionRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Idempotent toggle. Returns 'added' or 'removed'. INSERT IGNORE relies on
     * the unique key so concurrent identical requests cannot double-insert.
     */
    public function toggle(int $postId, int $userId, string $emoji): string
    {
        $inserted = $this->db->run(
            'INSERT IGNORE INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$postId, $userId, $emoji],
        )->rowCount();

        if ($inserted > 0) {
            return 'added';
        }

        $this->db->run(
            'DELETE FROM reactions WHERE post_id = ? AND user_id = ? AND emoji = ?',
            [$postId, $userId, $emoji],
        );
        return 'removed';
    }

    public function exists(int $postId, int $userId, string $emoji): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM reactions WHERE post_id = ? AND user_id = ? AND emoji = ? LIMIT 1',
            [$postId, $userId, $emoji],
        ) !== false;
    }

    /** @return array<string,int> emoji => count, for one post */
    public function countsForPost(int $postId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT emoji, COUNT(*) AS n FROM reactions WHERE post_id = ? GROUP BY emoji ORDER BY n DESC, emoji ASC',
            [$postId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['emoji']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Grouped counts for many posts at once (avoids N+1 in the thread stream).
     *
     * @param list<int> $postIds
     * @return array<int,array<string,int>> post_id => [emoji => count]
     */
    public function countsForPosts(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ($postIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($postIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT post_id, emoji, COUNT(*) AS n FROM reactions
             WHERE post_id IN ($place) GROUP BY post_id, emoji ORDER BY n DESC, emoji ASC",
            $postIds,
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['post_id']][(string) $r['emoji']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Which emoji the given user has reacted with, per post (to highlight the
     * viewer's own reactions).
     *
     * @param list<int> $postIds
     * @return array<int,list<string>> post_id => [emoji, ...]
     */
    public function userReactionsForPosts(int $userId, array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ($postIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($postIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT post_id, emoji FROM reactions WHERE user_id = ? AND post_id IN ($place)",
            array_merge([$userId], $postIds),
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['post_id']][] = (string) $r['emoji'];
        }
        return $out;
    }

    /**
     * Newest eligible other members, capped at six per post/emoji in one query.
     * Call only after authorizing the viewer's read of every supplied post.
     * Counts intentionally stay aggregate; these names are a private member hint.
     *
     * @param list<int> $postIds
     * @return array<int,array<string,list<string>>>
     */
    public function reactorsForPosts(int $viewerId, array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ($viewerId <= 0 || $postIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($postIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT post_id, emoji, username FROM (
                SELECT r.post_id, r.emoji, u.username,
                    ROW_NUMBER() OVER (PARTITION BY r.post_id, r.emoji ORDER BY r.created_at DESC, r.id DESC) AS reactor_rank
                FROM reactions r
                JOIN users u ON u.id = r.user_id
                JOIN posts p ON p.id = r.post_id
                JOIN threads t ON t.id = p.thread_id
                JOIN boards b ON b.id = t.board_id
                WHERE r.post_id IN ($place) AND r.user_id <> ? AND u.status = 'active'
                    AND (p.is_anonymous = 0 OR r.user_id <> p.user_id)
                    AND NOT EXISTS (
                        SELECT 1 FROM blocks blocked
                        WHERE (blocked.user_id = ? AND blocked.blocked_user_id = r.user_id)
                            OR (blocked.user_id = r.user_id AND blocked.blocked_user_id = ?)
                    )
                    AND (b.visibility <> 'private' OR u.role = 'admin' OR EXISTS (
                        SELECT 1 FROM board_members membership
                        WHERE membership.board_id = b.id AND membership.user_id = r.user_id
                    ))
            ) eligible WHERE reactor_rank <= 6 ORDER BY post_id, emoji, reactor_rank",
            array_merge($postIds, [$viewerId, $viewerId, $viewerId]),
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['post_id']][(string) $row['emoji']][] = (string) $row['username'];
        }
        return $out;
    }

    /** Reactions received on a post from users other than $authorId (reputation contribution). */
    public function receivedCount(int $postId, int $authorId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reactions WHERE post_id = ? AND user_id <> ?',
            [$postId, $authorId],
        );
    }
}
