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

    /** Personal Phase 4 snooze state. NULL means visible in normal inbox filters. */
    public function setSnooze(int $userId, int $threadId, ?string $until): void
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, snoozed_until)
             VALUES (:uid, :tid, :until_at)
             ON DUPLICATE KEY UPDATE snoozed_until = VALUES(snoozed_until)',
            ['uid' => $userId, 'tid' => $threadId, 'until_at' => $until],
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
     * One page of the personal Inbox. Phase 4 adds workflow filters while
     * preserving the same board-visibility gate. Normal filters exclude active
     * snoozes; the snoozed filter is the explicit personal recovery view.
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

        $rows = $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug, b.name AS board_name, b.visibility AS board_visibility,
                    au.username AS author_username, au.display_name AS author_display_name,
                    COALESCE(op.is_anonymous, 0) AS op_is_anonymous,
                    COALESCE(tu.is_starred, 0) AS is_starred,
                    tu.snoozed_until AS snoozed_until,
                    ta.assigned_user_id,
                    assignee.username AS assigned_username,
                    assignee.display_name AS assigned_display_name,
                    CASE
                      WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                      ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                    END AS is_unread
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             LEFT JOIN thread_assignments ta ON ta.thread_id = t.id
             LEFT JOIN users assignee ON assignee.id = ta.assigned_user_id
             WHERE t.is_deleted = 0
               AND t.is_pending = 0
               AND ($visSql)
               $where
             ORDER BY $order
             LIMIT " . $limit . ' OFFSET ' . $offset,
            $params,
        );

        if ($filter === 'for_you') {
            $rows = $this->annotateForYouReasons($userId, $rows);
        }

        return $rows;
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
        $visible = 'AND (tu.snoozed_until IS NULL OR tu.snoozed_until <= UTC_TIMESTAMP())';
        switch ($filter) {
            case 'for_you':
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
                return [
                    $visible . ' AND (
                        EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = ?)
                        OR EXISTS (SELECT 1 FROM posts mp WHERE mp.thread_id = t.id AND mp.is_deleted = 0 AND mp.is_pending = 0
                                   AND mp.user_id <> ? AND mp.body LIKE CONCAT(\'%@\', (SELECT username FROM users WHERE id = ?), \'%\'))
                        OR (t.user_id = ? AND t.reply_count > 0)
                        OR EXISTS (SELECT 1 FROM subscriptions st WHERE st.user_id = ' . (int) $userId . " AND st.target_type = 'thread' AND st.target_id = t.id AND st.frequency <> 'off')
                        OR EXISTS (SELECT 1 FROM subscriptions sb WHERE sb.user_id = " . (int) $userId . " AND sb.target_type = 'board' AND sb.target_id = t.board_id AND sb.frequency <> 'off')
                        OR COALESCE(tu.is_starred, 0) = 1
                        OR EXISTS (SELECT 1 FROM follows fb WHERE fb.user_id = " . (int) $userId . " AND fb.target_type = 'board' AND fb.target_id = t.board_id)
                        OR EXISTS (SELECT 1 FROM follows ft JOIN thread_tags tt ON tt.tag_id = ft.target_id
                                   WHERE ft.user_id = " . (int) $userId . " AND ft.target_type = 'tag' AND tt.thread_id = t.id)
                    )",
                    $this->forYouOrder($userId),
                ];
            case 'mentions':
                $params[] = $userId;
                $params[] = $userId;
                return [
                    $visible . ' AND EXISTS (
                        SELECT 1 FROM posts mp
                        WHERE mp.thread_id = t.id AND mp.is_deleted = 0 AND mp.is_pending = 0
                          AND mp.user_id <> ? AND mp.body LIKE CONCAT(\'%@\', (SELECT username FROM users WHERE id = ?), \'%\')
                    )',
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'replies':
                $params[] = $userId;
                return [$visible . ' AND t.user_id = ? AND t.reply_count > 0', 't.last_post_at DESC, t.id DESC'];
            case 'watching':
                return [
                    $visible . ' AND (
                        EXISTS (SELECT 1 FROM subscriptions st WHERE st.user_id = ' . (int) $userId . " AND st.target_type = 'thread' AND st.target_id = t.id AND st.frequency <> 'off')
                        OR EXISTS (SELECT 1 FROM subscriptions sb WHERE sb.user_id = " . (int) $userId . " AND sb.target_type = 'board' AND sb.target_id = t.board_id AND sb.frequency <> 'off')
                    )",
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'needs_answer':
                return [$visible . " AND (t.status = 'needs_answer' OR t.reply_count = 0)", 't.last_post_at DESC, t.id DESC'];
            case 'assigned':
                return [
                    $visible . ' AND EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = ' . (int) $userId . ')',
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'decisions':
                return [$visible . " AND t.status = 'decision_made'", 't.status_changed_at DESC, t.id DESC'];
            case 'solved':
                return [$visible . " AND t.status = 'solved'", 't.status_changed_at DESC, t.id DESC'];
            case 'snoozed':
                return ['AND tu.snoozed_until > UTC_TIMESTAMP()', 'tu.snoozed_until ASC, t.last_post_at DESC, t.id DESC'];
            case 'drafts':
                return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
            case 'starred':
                return [$visible . ' AND COALESCE(tu.is_starred, 0) = 1', 't.last_post_at DESC, t.id DESC'];
            case 'mine':
                $params[] = $userId;
                return [$visible . ' AND t.user_id = ?', 't.created_at DESC, t.id DESC'];
            case 'unanswered':
                return [$visible . ' AND t.reply_count = 0', 't.created_at DESC, t.id DESC'];
            case 'newest':
                return [$visible, 't.created_at DESC, t.id DESC'];
            case 'unread':
                $params[] = $cutover;
                return [
                    $visible . ' AND (CASE
                            WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                            ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                          END)',
                    't.last_post_at DESC, t.id DESC',
                ];
            default: // active
                return [$visible, 't.is_pinned DESC, t.last_post_at DESC, t.id DESC'];
        }
    }

    private function forYouOrder(int $userId): string
    {
        $uid = (int) $userId;
        return "(CASE WHEN EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = $uid) THEN 90 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM posts mp WHERE mp.thread_id = t.id AND mp.is_deleted = 0 AND mp.is_pending = 0
                                      AND mp.user_id <> $uid AND mp.body LIKE CONCAT('%@', (SELECT username FROM users WHERE id = $uid), '%')) THEN 80 ELSE 0 END
                + CASE WHEN t.user_id = $uid AND t.reply_count > 0 THEN 70 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM subscriptions st WHERE st.user_id = $uid AND st.target_type = 'thread' AND st.target_id = t.id AND st.frequency <> 'off') THEN 60 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM subscriptions sb WHERE sb.user_id = $uid AND sb.target_type = 'board' AND sb.target_id = t.board_id AND sb.frequency <> 'off') THEN 50 ELSE 0 END
                + CASE WHEN COALESCE(tu.is_starred, 0) = 1 THEN 40 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM follows fb WHERE fb.user_id = $uid AND fb.target_type = 'board' AND fb.target_id = t.board_id) THEN 30 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM follows ft JOIN thread_tags tt ON tt.tag_id = ft.target_id
                                    WHERE ft.user_id = $uid AND ft.target_type = 'tag' AND tt.thread_id = t.id) THEN 20 ELSE 0 END) DESC,
                t.last_post_at DESC, t.id DESC";
    }

    /**
     * Attach the top deterministic reason label shown by the UI. Page-size
     * bounded, and every check stays inside the same read-gated result set.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function annotateForYouReasons(int $userId, array $rows): array
    {
        $username = (string) ($this->db->fetchValue('SELECT username FROM users WHERE id = ?', [$userId]) ?: '');
        foreach ($rows as &$row) {
            $threadId = (int) $row['id'];
            if ((int) ($row['assigned_user_id'] ?? 0) === $userId) {
                $row['for_you_reason'] = 'Assigned to you';
                continue;
            }
            if ($username !== '' && $this->db->fetchValue(
                'SELECT 1 FROM posts WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 AND user_id <> ? AND body LIKE ? LIMIT 1',
                [$threadId, $userId, '%@' . $username . '%'],
            ) !== false) {
                $row['for_you_reason'] = 'Mentioned you';
                continue;
            }
            if ((int) $row['user_id'] === $userId && (int) $row['reply_count'] > 0) {
                $row['for_you_reason'] = 'Replies to your topic';
                continue;
            }
            if ($this->db->fetchValue(
                "SELECT 1 FROM subscriptions WHERE user_id = ? AND target_type = 'thread' AND target_id = ? AND frequency <> 'off' LIMIT 1",
                [$userId, $threadId],
            ) !== false) {
                $row['for_you_reason'] = 'Watched topic';
                continue;
            }
            if ($this->db->fetchValue(
                "SELECT 1 FROM subscriptions WHERE user_id = ? AND target_type = 'board' AND target_id = ? AND frequency <> 'off' LIMIT 1",
                [$userId, (int) $row['board_id']],
            ) !== false) {
                $row['for_you_reason'] = 'Watched board';
                continue;
            }
            if (!empty($row['is_starred'])) {
                $row['for_you_reason'] = 'Starred by you';
                continue;
            }
            if ($this->db->fetchValue(
                "SELECT 1 FROM follows WHERE user_id = ? AND target_type = 'board' AND target_id = ? LIMIT 1",
                [$userId, (int) $row['board_id']],
            ) !== false) {
                $row['for_you_reason'] = 'Followed board';
                continue;
            }
            $row['for_you_reason'] = 'Followed tag';
        }
        unset($row);

        return $rows;
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
