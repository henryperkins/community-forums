<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ThreadRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $boardId, int $userId, string $title, string $slug, bool $pending = false): int
    {
        return $this->db->insert(
            'INSERT INTO threads (board_id, user_id, title, slug, is_pending, created_at, last_post_at)
             VALUES (:board_id, :user_id, :title, :slug, :pending, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            ['board_id' => $boardId, 'user_id' => $userId, 'title' => $title, 'slug' => $slug, 'pending' => $pending ? 1 : 0],
        );
    }

    /** Clear/set a thread's approval-hold flag (P3-05). */
    public function setPending(int $id, bool $pending): void
    {
        $this->db->run('UPDATE threads SET is_pending = ? WHERE id = ?', [$pending ? 1 : 0, $id]);
    }

    /**
     * Approval queue (P3-05): pending threads (OP held), optionally scoped to a
     * set of board ids (NULL = all, for admins).
     *
     * @param list<int>|null $boardIds
     * @return array<int,array<string,mixed>>
     */
    public function listPending(?array $boardIds, int $limit = 100): array
    {
        $limit = max(1, $limit);
        $scope = '';
        if ($boardIds !== null) {
            if ($boardIds === []) {
                return [];
            }
            $scope = ' AND t.board_id IN (' . implode(',', array_map('intval', $boardIds)) . ')';
        }
        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.slug, t.created_at, t.board_id,
                    u.username AS author_username, b.slug AS board_slug, b.name AS board_name
             FROM threads t
             JOIN users u ON u.id = t.user_id
             JOIN boards b ON b.id = t.board_id
             WHERE t.is_pending = 1 AND t.is_deleted = 0' . $scope . '
             ORDER BY t.created_at ASC, t.id ASC
             LIMIT ' . $limit,
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM threads WHERE id = ?', [$id]);
    }

    /** Canonical mutation lock: callers acquire this before dependent rows. @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM threads WHERE id = ? FOR UPDATE', [$id]);
    }

    /** Thread joined with its board (for read gates + locked checks). @return array<string,mixed>|null */
    public function findWithBoard(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT t.*, b.slug AS board_slug, b.name AS board_name, b.visibility AS board_visibility,
                    b.post_min_role AS board_post_min_role, b.allow_anonymous AS board_allow_anonymous,
                    b.require_approval AS board_require_approval, b.assignment_mode AS board_assignment_mode,
                    b.tags_enabled AS board_tags_enabled, b.wiki_enabled AS board_wiki_enabled,
                    b.is_archived AS board_is_archived,
                    b.id AS board_id, au.username AS author_username, au.display_name AS author_display_name
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             WHERE t.id = ?',
            [$id],
        );
    }

    /** @return array<string,mixed>|null target thread for an old merged source id */
    public function redirectTarget(int $sourceThreadId): ?array
    {
        return $this->db->fetch(
            'SELECT t.*
             FROM thread_redirects r
             JOIN threads t ON t.id = r.canonical_thread_id
             WHERE r.old_thread_id = ?',
            [$sourceThreadId],
        );
    }

    /**
     * The board page's one page of topics, in the board's fixed order.
     *
     * $viewerId annotates each row with that member's own state — starred,
     * snoozed, assigned — because a topic you starred must read as starred on
     * its own board, not only in your inbox. Guests pass null and nothing is
     * joined.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByBoard(
        int $boardId,
        int $limit,
        int $offset,
        ?int $viewerId = null,
        bool $workflowEnabled = true,
        bool $engagementEnabled = true,
    ): array {
        return $this->listBoardRows(
            $boardId,
            $limit,
            $offset,
            't.is_pinned DESC, t.last_post_at DESC, t.id DESC',
            $viewerId,
            $workflowEnabled,
            $engagementEnabled,
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listNewestByBoard(int $boardId, int $limit, int $offset): array
    {
        return $this->listBoardRows($boardId, $limit, $offset, 't.is_pinned DESC, t.created_at DESC, t.id DESC');
    }

    /**
     * One page of non-deleted threads for a board, with author + OP anonymity
     * state joined. $orderBy comes only from the constant-order public methods.
     *
     * @return array<int,array<string,mixed>>
     */
    private function listBoardRows(
        int $boardId,
        int $limit,
        int $offset,
        string $orderBy,
        ?int $viewerId = null,
        bool $workflowEnabled = true,
        bool $engagementEnabled = true,
    ): array {
        // LIMIT/OFFSET are app-controlled integers, inlined after an int cast
        // because native prepared statements can't bind them as placeholders.
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $params = ['board_id' => $boardId];
        $viewerSelect = '';
        $viewerJoin = '';
        if ($viewerId !== null) {
            $params['viewer_id'] = $viewerId;
            // The assignment joins carry no viewer id — an assignment belongs to
            // the topic, not to the reader — so only thread_user is keyed to it.
            $viewerSelect = ',
                    COALESCE(tu.is_starred, 0) AS is_starred,
                    tu.snoozed_until AS snoozed_until,
                    ta.assigned_user_id,
                    assignee.username AS assigned_username';
            $viewerJoin = '
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = :viewer_id
             LEFT JOIN thread_assignments ta ON ta.thread_id = t.id
             LEFT JOIN users assignee ON assignee.id = ta.assigned_user_id';
        }

        $rows = $this->db->fetchAll(
            // last_post_user identity is intentionally NOT selected: the listing
            // shows only last_post_at, and joining the last poster would leak the
            // real author of an anonymous final reply. Add a masked column if a
            // "last reply by" byline is ever introduced. The viewer joins above
            // are subject to the same rule — they carry the viewer's own state,
            // never another member's identity.
            'SELECT t.*, au.username AS author_username, au.display_name AS author_display_name,
                    COALESCE(op.is_anonymous, 0) AS op_is_anonymous' . $viewerSelect . '
             FROM threads t
             JOIN users au ON au.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1' . $viewerJoin . '
             WHERE t.board_id = :board_id AND t.is_deleted = 0 AND t.is_pending = 0
             ORDER BY ' . $orderBy . '
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );

        if ($viewerId !== null && (!$workflowEnabled || !$engagementEnabled)) {
            // A rollback must suppress retained state from every presentation,
            // not merely remove its controls — mirroring
            // ThreadUserRepository::inbox(). Each flag owns its own columns:
            // topic_workflow owns snooze and assignment, engagement owns the
            // star. Rolling engagement back while a star still rendered would
            // leave a mark with no control to clear it.
            foreach ($rows as &$row) {
                if (!$workflowEnabled) {
                    $row['snoozed_until'] = null;
                    $row['assigned_user_id'] = null;
                    $row['assigned_username'] = null;
                }
                if (!$engagementEnabled) {
                    $row['is_starred'] = 0;
                }
            }
            unset($row);
        }

        return $rows;
    }

    public function countByBoard(int $boardId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads WHERE board_id = ? AND is_deleted = 0 AND is_pending = 0',
            [$boardId],
        );
    }

    /**
     * Public Board Index facts and bounded topic peeks for an already
     * policy-filtered set of board ids. The visible-board CTE is deliberately
     * supplied by the caller: this query cannot discover a hidden/private board
     * that NavigationService did not first admit through BoardPolicy.
     *
     * One statement computes every board-level rank signal and at most five
     * topic rows per board. No personal thread_user/user_board_prefs state is
     * joined, which keeps the directory identical for every reader.
     *
     * @param list<int> $boardIds
     * @return array<int,array{
     *     latest_activity_at:?string,
     *     newest_thread_at:?string,
     *     unanswered_count:int,
     *     top_commend_count:int,
     *     settled_count:int,
     *     latest_settled_at:?string,
     *     topics:list<array<string,mixed>>
     * }>
     */
    public function directorySignals(array $boardIds, string $sort, int $peek): array
    {
        $boardIds = array_values(array_unique(array_filter(array_map('intval', $boardIds), static fn (int $id): bool => $id > 0)));
        if ($boardIds === []) {
            return [];
        }

        $peek = in_array($peek, [0, 3, 5], true) ? $peek : 3;
        $sort = in_array($sort, ['category', 'active', 'newest', 'unanswered', 'top', 'solved'], true)
            ? $sort
            : 'category';

        $visibleSelects = [];
        $params = [];
        foreach ($boardIds as $ordinal => $boardId) {
            $visibleSelects[] = ($ordinal === 0 ? 'SELECT ? AS board_id' : 'SELECT ?') . ', ' . $ordinal . ' AS ordinal';
            $params[] = $boardId;
        }

        $topicOrder = match ($sort) {
            'newest' => 'created_at DESC, id DESC',
            'unanswered' => 'is_unanswered DESC, created_at DESC, id DESC',
            'top' => 'commend_count DESC, last_post_at DESC, id DESC',
            'solved' => 'is_settled DESC, COALESCE(status_changed_at, last_post_at) DESC, id DESC',
            default => 'last_post_at DESC, id DESC',
        };
        $topicMatch = match ($sort) {
            'unanswered' => 'r.is_unanswered = 1',
            'solved' => 'r.is_settled = 1',
            default => '1 = 1',
        };
        $peekJoin = $peek === 0 ? '1 = 0' : 'r.topic_rank <= ' . $peek . ' AND ' . $topicMatch;

        $rows = $this->db->fetchAll(
            'WITH visible_boards AS (
                 ' . implode(' UNION ALL ', $visibleSelects) . '
             ),
             reaction_counts AS (
                 SELECT p.thread_id, COUNT(r.id) AS commend_count
                 FROM posts p
                 JOIN threads scoped ON scoped.id = p.thread_id
                 JOIN visible_boards vb ON vb.board_id = scoped.board_id
                 JOIN reactions r ON r.post_id = p.id AND r.user_id <> p.user_id
                 WHERE p.is_op = 1 AND p.is_deleted = 0 AND p.is_pending = 0
                 GROUP BY p.thread_id
             ),
             base AS (
                 SELECT t.id, t.board_id, t.title, t.slug, t.created_at,
                        t.last_post_at, t.reply_count, t.status, t.status_changed_at,
                        u.username AS author_username,
                        u.display_name AS author_display_name,
                        u.role AS author_role,
                        COALESCE(op.is_anonymous, 0) AS op_is_anonymous,
                        COALESCE(rc.commend_count, 0) AS commend_count,
                        CASE WHEN t.status = \'needs_answer\' OR t.reply_count = 0 THEN 1 ELSE 0 END AS is_unanswered,
                        CASE WHEN t.status IN (\'solved\', \'decision_made\') THEN 1 ELSE 0 END AS is_settled
                 FROM threads t
                 JOIN visible_boards vb ON vb.board_id = t.board_id
                 JOIN users u ON u.id = t.user_id
                 -- Anonymity is a property of the OP that survives its
                 -- moderation state, so this join matches the canonical one in
                 -- listBoardRows and does NOT filter on is_deleted/is_pending.
                 -- Filtering here made COALESCE fail OPEN: a thread whose OP
                 -- was soft-deleted lost the row carrying is_anonymous = 1,
                 -- defaulted to 0, and the peek printed the real author of a
                 -- topic that was posted anonymously.
                 LEFT JOIN posts op
                   ON op.thread_id = t.id
                  AND op.is_op = 1
                 LEFT JOIN reaction_counts rc ON rc.thread_id = t.id
                 WHERE t.is_deleted = 0 AND t.is_pending = 0
             ),
             aggregates AS (
                 SELECT board_id,
                        MAX(last_post_at) AS latest_activity_at,
                        MAX(created_at) AS newest_thread_at,
                        SUM(is_unanswered) AS unanswered_count,
                        MAX(commend_count) AS top_commend_count,
                        SUM(is_settled) AS settled_count,
                        MAX(CASE WHEN is_settled = 1 THEN COALESCE(status_changed_at, last_post_at) END) AS latest_settled_at
                 FROM base
                 GROUP BY board_id
             ),
             ranked AS (
                 SELECT base.*,
                        ROW_NUMBER() OVER (PARTITION BY board_id ORDER BY ' . $topicOrder . ') AS topic_rank
                 FROM base
             )
             SELECT vb.board_id,
                    a.latest_activity_at, a.newest_thread_at,
                    COALESCE(a.unanswered_count, 0) AS unanswered_count,
                    COALESCE(a.top_commend_count, 0) AS top_commend_count,
                    COALESCE(a.settled_count, 0) AS settled_count,
                    a.latest_settled_at,
                    r.id AS thread_id, r.title, r.slug, r.created_at, r.last_post_at,
                    r.reply_count, r.status, r.status_changed_at,
                    r.author_username, r.author_display_name, r.author_role,
                    r.op_is_anonymous, r.commend_count
             FROM visible_boards vb
             LEFT JOIN aggregates a ON a.board_id = vb.board_id
             LEFT JOIN ranked r ON r.board_id = vb.board_id AND ' . $peekJoin . '
             ORDER BY vb.ordinal ASC, r.topic_rank ASC',
            $params,
        );

        $signals = [];
        foreach ($boardIds as $boardId) {
            $signals[$boardId] = [
                'latest_activity_at' => null,
                'newest_thread_at' => null,
                'unanswered_count' => 0,
                'top_commend_count' => 0,
                'settled_count' => 0,
                'latest_settled_at' => null,
                'topics' => [],
            ];
        }
        foreach ($rows as $row) {
            $boardId = (int) $row['board_id'];
            $signals[$boardId]['latest_activity_at'] = $row['latest_activity_at'] !== null ? (string) $row['latest_activity_at'] : null;
            $signals[$boardId]['newest_thread_at'] = $row['newest_thread_at'] !== null ? (string) $row['newest_thread_at'] : null;
            $signals[$boardId]['unanswered_count'] = (int) $row['unanswered_count'];
            $signals[$boardId]['top_commend_count'] = (int) $row['top_commend_count'];
            $signals[$boardId]['settled_count'] = (int) $row['settled_count'];
            $signals[$boardId]['latest_settled_at'] = $row['latest_settled_at'] !== null ? (string) $row['latest_settled_at'] : null;
            if ($row['thread_id'] !== null) {
                $row['thread_id'] = (int) $row['thread_id'];
                $row['reply_count'] = (int) $row['reply_count'];
                $row['op_is_anonymous'] = (int) $row['op_is_anonymous'];
                $row['commend_count'] = (int) $row['commend_count'];
                $signals[$boardId]['topics'][] = $row;
            }
        }

        return $signals;
    }

    /**
     * Recent non-deleted threads by author, for the PUBLIC profile. Restricted
     * to public boards so a member's activity never reveals the existence or
     * titles of threads in hidden/private boards (the board read/list gate).
     * Threads whose OP was posted anonymously are EXCLUDED (anonymity lives on
     * the OP post, so a NOT EXISTS guard keeps them off the author's profile).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentByUser(int $userId, int $limit): array
    {
        return $this->listByUser($userId, 'newest', '', $limit, 0);
    }

    /**
     * Profile Topics tab: public, attributable topics with a bounded page,
     * literal-wildcard search, and newest/commend ordering.
     *
     * @param 'newest'|'commends' $sort
     * @return array<int,array<string,mixed>>
     */
    public function listByUser(
        int $userId,
        string $sort = 'newest',
        string $query = '',
        int $limit = 20,
        int $offset = 0,
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(1_000_000, $offset));
        [$where, $params] = $this->profileFilter($userId, $query);
        $order = $sort === 'commends'
            ? 'commend_count DESC, t.created_at DESC, t.id DESC'
            : 't.created_at DESC, t.id DESC';

        return $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug, b.name AS board_name,
                    (SELECT op.body FROM posts op
                      WHERE op.thread_id = t.id AND op.is_op = 1
                        AND op.is_deleted = 0 AND op.is_pending = 0 AND op.is_anonymous = 0
                      ORDER BY op.id ASC LIMIT 1) AS excerpt_body,
                    (SELECT COUNT(*) FROM reactions r
                       JOIN posts reacted_op ON reacted_op.id = r.post_id
                      WHERE reacted_op.thread_id = t.id AND reacted_op.is_op = 1
                        AND reacted_op.is_deleted = 0 AND reacted_op.is_pending = 0
                        AND r.user_id <> reacted_op.user_id) AS commend_count
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             WHERE $where
             ORDER BY $order
             LIMIT " . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function countByUser(int $userId, string $query = ''): int
    {
        [$where, $params] = $this->profileFilter($userId, $query);

        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM threads t JOIN boards b ON b.id = t.board_id WHERE $where",
            $params,
        );
    }

    /** @return array{0:string,1:list<mixed>} */
    private function profileFilter(int $userId, string $query): array
    {
        $where = "t.user_id = ? AND t.is_deleted = 0 AND t.is_pending = 0
               AND b.visibility = 'public'
               AND EXISTS (
                    SELECT 1 FROM posts visible_op
                    WHERE visible_op.thread_id = t.id AND visible_op.is_op = 1
                      AND visible_op.is_deleted = 0 AND visible_op.is_pending = 0
                      AND visible_op.is_anonymous = 0
               )";
        $params = [$userId];
        $query = trim($query);
        if ($query !== '') {
            $like = $this->literalLike($query);
            $where .= ' AND (t.title LIKE ? OR b.slug LIKE ? OR b.name LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return [$where, $params];
    }

    private function literalLike(string $query): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
    }

    public function incrementReplyCount(int $id, int $delta = 1): void
    {
        $this->db->run(
            'UPDATE threads SET reply_count = GREATEST(0, CAST(reply_count AS SIGNED) + ?) WHERE id = ?',
            [$delta, $id],
        );
    }

    public function updateLastPost(int $id, int $postId, int $userId, string $at): void
    {
        $this->db->run(
            'UPDATE threads SET last_post_id = :pid, last_post_user_id = :uid, last_post_at = :at WHERE id = :id',
            ['pid' => $postId, 'uid' => $userId, 'at' => $at, 'id' => $id],
        );
    }

    /** Recompute last_post_* from the newest non-deleted post (used after a delete). */
    public function recomputeLastPost(int $id): void
    {
        $row = $this->db->fetch(
            'SELECT id, user_id, created_at FROM posts
             WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 ORDER BY created_at DESC, id DESC LIMIT 1',
            [$id],
        );
        if ($row === null) {
            $this->db->run(
                'UPDATE threads SET last_post_id = NULL, last_post_user_id = NULL, last_post_at = NULL WHERE id = ?',
                [$id],
            );
            return;
        }
        $this->db->run(
            'UPDATE threads SET last_post_id = :pid, last_post_user_id = :uid, last_post_at = :at WHERE id = :id',
            ['pid' => (int) $row['id'], 'uid' => (int) $row['user_id'], 'at' => $row['created_at'], 'id' => $id],
        );
    }

    public function setBoard(int $id, int $boardId): void
    {
        $this->db->run('UPDATE threads SET board_id = ? WHERE id = ?', [$boardId, $id]);
    }

    /** Set/clear the accepted ("solved") answer post (COMMUNITY §11). */
    public function setAcceptedAnswer(int $id, ?int $postId): void
    {
        $this->db->run('UPDATE threads SET accepted_answer_post_id = ? WHERE id = ?', [$postId, $id]);
    }

    public function setStatus(int $id, string $status, ?int $actorId): void
    {
        $this->db->run(
            'UPDATE threads SET status = ?, status_changed_at = UTC_TIMESTAMP(), status_changed_by = ? WHERE id = ?',
            [$status, $actorId, $id],
        );
    }

    public function addStatusHistory(int $id, ?int $actorId, ?string $previous, string $status, ?string $reason): void
    {
        $this->db->run(
            'INSERT INTO thread_status_history (thread_id, actor_id, previous_status, new_status, reason, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, $actorId, $previous, $status, $reason],
        );
    }

    /** @return array<int,array<string,mixed>> newest first */
    public function statusHistory(int $id, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            'SELECT h.*, u.username AS actor_username, u.display_name AS actor_display_name,
                    u.role AS actor_role,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM posts op
                        WHERE op.thread_id = h.thread_id AND op.user_id = h.actor_id
                          AND op.is_op = 1 AND op.is_anonymous = 1
                    ) THEN 1 ELSE 0 END AS actor_is_anonymous
             FROM thread_status_history h
             LEFT JOIN users u ON u.id = h.actor_id
             WHERE h.thread_id = ?
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT ' . $limit,
            [$id],
        );
    }

    public function setPinned(int $id, bool $pinned): void
    {
        $this->db->run('UPDATE threads SET is_pinned = ? WHERE id = ?', [$pinned ? 1 : 0, $id]);
    }

    public function setLocked(int $id, bool $locked): void
    {
        $this->db->run('UPDATE threads SET is_locked = ? WHERE id = ?', [$locked ? 1 : 0, $id]);
    }

    public function softDelete(int $id, int $byUserId): void
    {
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$id]);
    }

    public function incrementViewCount(int $id): void
    {
        $this->db->run('UPDATE threads SET view_count = view_count + 1 WHERE id = ?', [$id]);
    }
}
