<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Per-user thread state (P2-01): read position + star, plus unread derivation
 * and the personal Inbox queries.
 *
 * Unread model (DECISIONS §3): a thread is unread for a user when its newest
 * post id (threads.last_post_id) is greater than the user's last_read_post_id.
 * Threads with no thread_user row are unread only when their last activity is
 * after the launch cutover (settings.engagement_cutover_at) — this avoids a
 * historical-unread flood (PHASE_2_PLAN §7.2). A far-future cutover (the unset
 * default) means no-row threads read as read until the operator stamps launch.
 */
final class ThreadUserRepository
{
    /** Sentinel used when no cutover is configured: nothing historical is unread. */
    public const NO_CUTOVER = '9999-12-31 23:59:59';

    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId, int $threadId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [$userId, $threadId],
        );
    }

    /**
     * Advance the user's read position to $postId. Never regresses (GREATEST),
     * so paging backwards or a slow request cannot un-read newer posts.
     */
    public function markRead(int $userId, int $threadId, int $postId): void
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred)
             VALUES (:uid, :tid, :pid, 0)
             ON DUPLICATE KEY UPDATE last_read_post_id = GREATEST(COALESCE(last_read_post_id, 0), VALUES(last_read_post_id))',
            ['uid' => $userId, 'tid' => $threadId, 'pid' => $postId],
        );
    }

    public function isStarred(int $userId, int $threadId): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT is_starred FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [$userId, $threadId],
        ) === 1;
    }

    public function setStar(int $userId, int $threadId, bool $starred): void
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, is_starred)
             VALUES (:uid, :tid, :star)
             ON DUPLICATE KEY UPDATE is_starred = VALUES(is_starred)',
            ['uid' => $userId, 'tid' => $threadId, 'star' => $starred ? 1 : 0],
        );
    }

    /** Idempotent toggle; returns the resulting starred state. */
    public function toggleStar(int $userId, int $threadId): bool
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, is_starred)
             VALUES (:uid, :tid, 1)
             ON DUPLICATE KEY UPDATE is_starred = 1 - is_starred',
            ['uid' => $userId, 'tid' => $threadId],
        );
        return $this->isStarred($userId, $threadId);
    }

    /**
     * Unread flags for a set of threads (annotates board/thread lists).
     *
     * @param list<int> $threadIds
     * @return array<int,bool> thread_id => is_unread
     */
    public function unreadFlags(int $userId, array $threadIds, string $cutover): array
    {
        $threadIds = array_values(array_unique(array_map('intval', $threadIds)));
        if ($threadIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($threadIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT t.id,
                    CASE
                      WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                      ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                    END AS is_unread
             FROM threads t
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE t.id IN ($place) AND t.is_pending = 0",
            array_merge([$cutover, $userId], $threadIds),
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = (int) $r['is_unread'] === 1;
        }
        return $map;
    }

    /** Total unread threads for the bell/inbox badge, read-gated by board visibility. */
    public function unreadCount(int $userId, bool $isAdmin, string $cutover): int
    {
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*)
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE t.is_deleted = 0
               AND t.is_pending = 0
               AND ($visSql)
               AND (
                 CASE
                   WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                   ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                 END
               )",
            array_merge([$userId], $visParams, [$cutover]),
        );
    }

    /**
     * One page of the personal Inbox. Filters: unread | starred | mine |
     * active | newest | unanswered. Always read-gated by board visibility so a
     * filter never surfaces an inaccessible board/thread.
     *
     * @return array<int,array<string,mixed>> threads + is_unread/is_starred flags
     */
    public function inbox(int $userId, string $filter, bool $isAdmin, string $cutover, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        // Param order follows SQL text order: SELECT CASE (cutover), JOIN (userId), WHERE visibility, then filter.
        $params = [$cutover, $userId];
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        foreach ($visParams as $p) {
            $params[] = $p;
        }
        [$where, $order] = $this->filterFragment($filter, $userId, $cutover, $params);

        return $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug, b.name AS board_name, b.visibility AS board_visibility,
                    au.username AS author_username, au.display_name AS author_display_name,
                    COALESCE(op.is_anonymous, 0) AS op_is_anonymous,
                    COALESCE(tu.is_starred, 0) AS is_starred,
                    CASE
                      WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                      ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                    END AS is_unread
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE t.is_deleted = 0
               AND t.is_pending = 0
               AND ($visSql)
               $where
             ORDER BY $order
             LIMIT " . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function countInbox(int $userId, string $filter, bool $isAdmin, string $cutover): int
    {
        $params = [$userId]; // JOIN
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        foreach ($visParams as $p) {
            $params[] = $p;
        }
        [$where] = $this->filterFragment($filter, $userId, $cutover, $params);
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*)
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE t.is_deleted = 0
               AND t.is_pending = 0
               AND ($visSql)
               $where",
            $params,
        );
    }

    /**
     * WHERE fragment + ORDER BY for a filter, appending any bound params (in SQL
     * text order) to $params by reference.
     *
     * @param list<mixed> $params
     * @return array{0:string,1:string}
     */
    private function filterFragment(string $filter, int $userId, string $cutover, array &$params): array
    {
        switch ($filter) {
            case 'starred':
                return ['AND COALESCE(tu.is_starred, 0) = 1', 't.last_post_at DESC, t.id DESC'];
            case 'mine':
                $params[] = $userId;
                return ['AND t.user_id = ?', 't.created_at DESC, t.id DESC'];
            case 'unanswered':
                return ['AND t.reply_count = 0', 't.created_at DESC, t.id DESC'];
            case 'newest':
                return ['', 't.created_at DESC, t.id DESC'];
            case 'unread':
                $params[] = $cutover;
                return [
                    'AND (CASE
                            WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                            ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                          END)',
                    't.last_post_at DESC, t.id DESC',
                ];
            default: // active
                return ['', 't.is_pinned DESC, t.last_post_at DESC, t.id DESC'];
        }
    }

    /**
     * Read gate for this cross-board LISTING (isListed semantics, not canRead):
     * public boards for everyone, private boards only where the viewer is a
     * board member; hidden boards are never listed. Admins see all. Returns the
     * SQL fragment + its bound params (the board_members EXISTS needs the userId).
     *
     * @return array{0:string,1:list<int>}
     */
    private function visibility(bool $isAdmin, int $userId): array
    {
        if ($isAdmin) {
            return ['1=1', []];
        }
        return [
            "(b.visibility = 'public'
              OR (b.visibility = 'private'
                  AND EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = ?)))",
            [$userId],
        ];
    }
}
