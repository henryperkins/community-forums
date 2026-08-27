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

    /**
     * Push the thread back to unread by hand — the board row's read marker.
     *
     * markRead() cannot be reused in reverse: it is monotonic by construction
     * (GREATEST), so asking it for a lower watermark silently no-ops. This
     * clears the watermark instead, and INSERTs the row when none exists —
     * deleting the row would fall the thread back to the engagement cutover,
     * which on a default install reads as *read* and would invert the action.
     */
    public function markUnread(int $userId, int $threadId): void
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred)
             VALUES (:uid, :tid, NULL, 0)
             ON DUPLICATE KEY UPDATE last_read_post_id = NULL',
            ['uid' => $userId, 'tid' => $threadId],
        );
    }

    /**
     * Clear every unread topic on one board in a single statement. Still
     * monotonic — a topic already read past its last post stays where it is.
     * Threads with no posts at all carry no watermark to advance to.
     */
    public function markBoardRead(int $userId, int $boardId): void
    {
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred)
             SELECT :uid, t.id, t.last_post_id, 0
             FROM threads t
             WHERE t.board_id = :bid
               AND t.is_deleted = 0
               AND t.is_pending = 0
               AND t.last_post_id IS NOT NULL
             ON DUPLICATE KEY UPDATE
               last_read_post_id = GREATEST(COALESCE(last_read_post_id, 0), VALUES(last_read_post_id))',
            ['uid' => $userId, 'bid' => $boardId],
        );
    }

    /**
     * Unread topics on one board, for the board page's own count.
     *
     * Deliberately NOT unreadCount()'s predicate: this counts exactly the rows
     * unreadFlags() puts a dot on, so the header's number always equals the
     * dots below it. Board visibility is already settled by the caller's read
     * gate, and a muted board still shows its own unread state to a reader who
     * has navigated to it.
     */
    public function unreadCountForBoard(int $userId, int $boardId, string $cutover): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*)
             FROM threads t
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE t.board_id = ?
               AND t.is_deleted = 0
               AND t.is_pending = 0
               AND (' . $this->unreadStateSql() . ')',
            [$userId, $boardId, $cutover],
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
    public function unreadCount(int $userId, bool $isAdmin, string $cutover, bool $workflowEnabled = true): int
    {
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*)
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             LEFT JOIN user_board_prefs ubp ON ubp.user_id = ? AND ubp.board_id = t.board_id
             WHERE t.is_deleted = 0
               AND t.is_pending = 0
               AND ($visSql)
               " . $this->unreadQueueFragment($workflowEnabled),
            array_merge([$userId, $userId], $visParams, [$cutover]),
        );
    }

    /**
     * One page of the personal Inbox. Phase 4 adds workflow filters while
     * preserving the same board-visibility gate. Normal filters exclude active
     * snoozes; the snoozed filter is the explicit personal recovery view.
     *
     * @return array<int,array<string,mixed>> threads + unread/star/queue flags
     */
    public function inbox(
        int $userId,
        string $filter,
        bool $isAdmin,
        string $cutover,
        int $limit,
        int $offset,
        bool $workflowEnabled = true,
        bool $mentionsEnabled = true,
    ): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        // Param order follows SQL text order: SELECT unread cases (two cutovers),
        // JOIN (userId for thread state + board preference), WHERE visibility,
        // then filter.
        $params = [$cutover, $cutover, $userId, $userId];
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        foreach ($visParams as $p) {
            $params[] = $p;
        }
        [$where, $order] = $this->filterFragment(
            $filter,
            $userId,
            $cutover,
            $params,
            $workflowEnabled,
            $mentionsEnabled,
        );

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
                    END AS is_unread,
                    " . $this->inboxUnreadStateSql($workflowEnabled) . " AS is_inbox_unread
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             LEFT JOIN user_board_prefs ubp ON ubp.user_id = ? AND ubp.board_id = t.board_id
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

        if (!$workflowEnabled) {
            // A rollback must suppress retained workflow state from every Inbox
            // presentation, not merely remove its filters. Accepted answers and
            // pin/lock remain core topic state and intentionally survive it.
            foreach ($rows as &$row) {
                $row['status'] = $row['accepted_answer_post_id'] !== null ? 'solved' : 'open';
                $row['snoozed_until'] = null;
                $row['assigned_user_id'] = null;
                $row['assigned_username'] = null;
                $row['assigned_display_name'] = null;
            }
            unset($row);
        }

        if ($filter === 'for_you') {
            $rows = $this->annotateForYouReasons($userId, $rows, $workflowEnabled, $mentionsEnabled);
        }

        return $rows;
    }

    public function countInbox(
        int $userId,
        string $filter,
        bool $isAdmin,
        string $cutover,
        bool $workflowEnabled = true,
        bool $mentionsEnabled = true,
    ): int
    {
        $params = [$userId, $userId]; // thread-state + board-preference JOINs
        [$visSql, $visParams] = $this->visibility($isAdmin, $userId);
        foreach ($visParams as $p) {
            $params[] = $p;
        }
        [$where] = $this->filterFragment(
            $filter,
            $userId,
            $cutover,
            $params,
            $workflowEnabled,
            $mentionsEnabled,
        );
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*)
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             LEFT JOIN user_board_prefs ubp ON ubp.user_id = ? AND ubp.board_id = t.board_id
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
    private function filterFragment(
        string $filter,
        int $userId,
        string $cutover,
        array &$params,
        bool $workflowEnabled,
        bool $mentionsEnabled,
    ): array
    {
        $visible = $this->normalInboxVisibility($workflowEnabled);
        switch ($filter) {
            case 'for_you':
                $signals = [];
                if ($workflowEnabled) {
                    $signals[] = 'EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = ' . (int) $userId . ')';
                }
                if ($mentionsEnabled) {
                    $signals[] = $this->notificationSignal($userId, 'mention');
                }
                $signals[] = $this->notificationSignal($userId, 'reply');
                $signals[] = $this->threadSubscriptionSignal($userId);
                $signals[] = $this->boardSubscriptionSignal($userId);
                $signals[] = 'COALESCE(tu.is_starred, 0) = 1';
                $signals[] = 'EXISTS (SELECT 1 FROM follows fb WHERE fb.user_id = ' . (int) $userId . " AND fb.target_type = 'board' AND fb.target_id = t.board_id)";
                $signals[] = 'EXISTS (SELECT 1 FROM follows ft JOIN thread_tags tt ON tt.tag_id = ft.target_id
                                   WHERE ft.user_id = ' . (int) $userId . " AND ft.target_type = 'tag' AND tt.thread_id = t.id)";
                return [
                    $visible . ' AND (' . implode("\n                        OR ", $signals) . ')',
                    $this->forYouOrder($userId, $workflowEnabled, $mentionsEnabled),
                ];
            case 'mentions':
                if (!$mentionsEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
                return [
                    $visible . ' AND ' . $this->notificationSignal($userId, 'mention'),
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'replies':
                return [$visible . ' AND ' . $this->notificationSignal($userId, 'reply'), 't.last_post_at DESC, t.id DESC'];
            case 'watching':
                return [
                    $visible . ' AND ' . $this->effectiveSubscriptionSignal($userId),
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'needs_answer':
                if (!$workflowEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
                return [$visible . " AND (t.status = 'needs_answer' OR t.reply_count = 0)", 't.last_post_at DESC, t.id DESC'];
            case 'assigned':
                if (!$workflowEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
                return [
                    $visible . ' AND EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = ' . (int) $userId . ')',
                    't.last_post_at DESC, t.id DESC',
                ];
            case 'decisions':
                if (!$workflowEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
                return [$visible . " AND t.status = 'decision_made'", 't.status_changed_at DESC, t.id DESC'];
            case 'solved':
                if (!$workflowEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
                return [$visible . " AND t.status = 'solved'", 't.status_changed_at DESC, t.id DESC'];
            case 'snoozed':
                if (!$workflowEnabled) {
                    return ['AND 1 = 0', 't.last_post_at DESC, t.id DESC'];
                }
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
                    $this->unreadQueueFragment($workflowEnabled),
                    't.last_post_at DESC, t.id DESC',
                ];
            default: // active
                return [$visible, 't.is_pinned DESC, t.last_post_at DESC, t.id DESC'];
        }
    }

    private function forYouOrder(int $userId, bool $workflowEnabled, bool $mentionsEnabled): string
    {
        $uid = (int) $userId;
        $weights = [];
        if ($workflowEnabled) {
            $weights[] = "CASE WHEN EXISTS (SELECT 1 FROM thread_assignments xa WHERE xa.thread_id = t.id AND xa.assigned_user_id = $uid) THEN 90 ELSE 0 END";
        }
        if ($mentionsEnabled) {
            $weights[] = 'CASE WHEN ' . $this->notificationSignal($userId, 'mention') . ' THEN 80 ELSE 0 END';
        }
        $weights[] = 'CASE WHEN ' . $this->notificationSignal($userId, 'reply') . ' THEN 70 ELSE 0 END';
        $weights[] = 'CASE WHEN ' . $this->threadSubscriptionSignal($userId) . ' THEN 60 ELSE 0 END';
        $weights[] = 'CASE WHEN ' . $this->boardSubscriptionSignal($userId) . ' THEN 50 ELSE 0 END';
        $weights[] = 'CASE WHEN COALESCE(tu.is_starred, 0) = 1 THEN 40 ELSE 0 END';
        $weights[] = "CASE WHEN EXISTS (SELECT 1 FROM follows fb WHERE fb.user_id = $uid AND fb.target_type = 'board' AND fb.target_id = t.board_id) THEN 30 ELSE 0 END";
        $weights[] = "CASE WHEN EXISTS (SELECT 1 FROM follows ft JOIN thread_tags tt ON tt.tag_id = ft.target_id
                                    WHERE ft.user_id = $uid AND ft.target_type = 'tag' AND tt.thread_id = t.id) THEN 20 ELSE 0 END";
        return '(' . implode("\n                + ", $weights) . ') DESC, t.last_post_at DESC, t.id DESC';
    }

    /**
     * Attach the top deterministic reason label shown by the UI. Page-size
     * bounded, and every check stays inside the same read-gated result set.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function annotateForYouReasons(int $userId, array $rows, bool $workflowEnabled, bool $mentionsEnabled): array
    {
        foreach ($rows as &$row) {
            $threadId = (int) $row['id'];
            if ($workflowEnabled && (int) ($row['assigned_user_id'] ?? 0) === $userId) {
                $row['for_you_reason'] = 'Assigned to you';
                continue;
            }
            if ($mentionsEnabled && $this->hasNotificationSignal($userId, $threadId, 'mention')) {
                $row['for_you_reason'] = 'Mentioned you';
                continue;
            }
            if ($this->hasNotificationSignal($userId, $threadId, 'reply')) {
                $row['for_you_reason'] = 'Replies to your topic';
                continue;
            }
            if ($this->hasThreadSubscription($userId, $threadId)) {
                $row['for_you_reason'] = 'Watched topic';
                continue;
            }
            if ($this->hasBoardSubscription($userId, $threadId, (int) $row['board_id'])) {
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

    /** Normal personal views honor a future snooze only while workflow is live. */
    private function normalInboxVisibility(bool $workflowEnabled): string
    {
        return $workflowEnabled ? 'AND (tu.snoozed_until IS NULL OR tu.snoozed_until <= UTC_TIMESTAMP())' : '';
    }

    /** The one unread predicate shared by the Unread queue and its badge. */
    private function unreadQueueFragment(bool $workflowEnabled): string
    {
        return $this->normalInboxVisibility($workflowEnabled) . '
               AND COALESCE(ubp.is_muted, 0) = 0
               AND (' . $this->unreadStateSql() . ')';
    }

    private function unreadStateSql(): string
    {
        return 'CASE
                  WHEN tu.thread_id IS NULL THEN (t.last_post_at IS NOT NULL AND t.last_post_at > ?)
                  ELSE (t.last_post_id IS NOT NULL AND (tu.last_read_post_id IS NULL OR t.last_post_id > tu.last_read_post_id))
                END';
    }

    /** Per-row queue membership mirrors the badge and Unread-filter predicate. */
    private function inboxUnreadStateSql(bool $workflowEnabled): string
    {
        return 'CASE
                  WHEN COALESCE(ubp.is_muted, 0) = 0
                    ' . $this->normalInboxVisibility($workflowEnabled) . '
                    AND (' . $this->unreadStateSql() . ')
                  THEN 1 ELSE 0
                END';
    }

    /** A delivery-backed mention or reply signal, not a raw body-text approximation. */
    private function notificationSignal(int $userId, string $type): string
    {
        $uid = (int) $userId;
        return "EXISTS (
                    SELECT 1
                    FROM notifications n
                    JOIN posts np ON np.id = n.post_id
                    WHERE n.user_id = $uid
                      AND n.thread_id = t.id
                      AND n.type = '$type'
                      AND n.actor_id <> $uid
                      AND np.thread_id = t.id
                      AND np.is_deleted = 0
                      AND np.is_pending = 0
                )";
    }

    /** An active thread row is the first half of subscription precedence. */
    private function threadSubscriptionSignal(int $userId): string
    {
        $uid = (int) $userId;
        return "EXISTS (SELECT 1 FROM subscriptions st
                        WHERE st.user_id = $uid
                          AND st.target_type = 'thread'
                          AND st.target_id = t.id
                          AND st.frequency <> 'off')";
    }

    /** A board row applies only when there is no thread-level override at all. */
    private function boardSubscriptionSignal(int $userId): string
    {
        $uid = (int) $userId;
        return "EXISTS (SELECT 1 FROM subscriptions sb
                        WHERE sb.user_id = $uid
                          AND sb.target_type = 'board'
                          AND sb.target_id = t.board_id
                          AND sb.frequency <> 'off'
                          AND NOT EXISTS (
                              SELECT 1 FROM subscriptions st
                              WHERE st.user_id = $uid
                                AND st.target_type = 'thread'
                                AND st.target_id = t.id
                          ))";
    }

    private function effectiveSubscriptionSignal(int $userId): string
    {
        return '(' . $this->threadSubscriptionSignal($userId) . ' OR ' . $this->boardSubscriptionSignal($userId) . ')';
    }

    private function hasNotificationSignal(int $userId, int $threadId, string $type): bool
    {
        return $this->db->fetchValue(
            'SELECT 1
             FROM notifications n
             JOIN posts np ON np.id = n.post_id
             WHERE n.user_id = ?
               AND n.thread_id = ?
               AND n.type = ?
               AND n.actor_id <> ?
               AND np.thread_id = ?
               AND np.is_deleted = 0
               AND np.is_pending = 0
             LIMIT 1',
            [$userId, $threadId, $type, $userId, $threadId],
        ) !== false;
    }

    private function hasThreadSubscription(int $userId, int $threadId): bool
    {
        return $this->db->fetchValue(
            "SELECT 1 FROM subscriptions
             WHERE user_id = ? AND target_type = 'thread' AND target_id = ? AND frequency <> 'off'
             LIMIT 1",
            [$userId, $threadId],
        ) !== false;
    }

    private function hasBoardSubscription(int $userId, int $threadId, int $boardId): bool
    {
        return $this->db->fetchValue(
            "SELECT 1 FROM subscriptions sb
             WHERE sb.user_id = ?
               AND sb.target_type = 'board'
               AND sb.target_id = ?
               AND sb.frequency <> 'off'
               AND NOT EXISTS (
                   SELECT 1 FROM subscriptions st
                   WHERE st.user_id = ? AND st.target_type = 'thread' AND st.target_id = ?
               )
             LIMIT 1",
            [$userId, $boardId, $userId, $threadId],
        ) !== false;
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
