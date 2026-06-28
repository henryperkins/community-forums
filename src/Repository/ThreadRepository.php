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

    /** Thread joined with its board (for read gates + locked checks). @return array<string,mixed>|null */
    public function findWithBoard(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT t.*, b.slug AS board_slug, b.name AS board_name, b.visibility AS board_visibility,
                    b.post_min_role AS board_post_min_role, b.allow_anonymous AS board_allow_anonymous,
                    b.require_approval AS board_require_approval, b.assignment_mode AS board_assignment_mode,
                    b.tags_enabled AS board_tags_enabled, b.wiki_enabled AS board_wiki_enabled,
                    b.id AS board_id, au.username AS author_username, au.display_name AS author_display_name
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             WHERE t.id = ?',
            [$id],
        );
    }

    /**
     * One page of non-deleted threads for a board: pinned first, then by the
     * reader's chosen sort — last activity (default, idx_threads_inbox), newest,
     * or reply count (P3-01) — with author + last-poster names joined.
     *
     * @param string $sort one of last_post|newest|replies (already validated)
     * @return array<int,array<string,mixed>>
     */
    public function listByBoard(int $boardId, int $limit, int $offset, string $sort = 'last_post'): array
    {
        // LIMIT/OFFSET are app-controlled integers, inlined after an int cast
        // because native prepared statements can't bind them as placeholders.
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        // Map the reader's thread_sort preference to a fixed ORDER BY via a
        // whitelist match() so the enum can never reach SQL as raw text; pinned
        // threads always lead, and the default mirrors Phase 2 "last activity".
        $orderBy = match ($sort) {
            'newest' => 't.is_pinned DESC, t.created_at DESC, t.id DESC',
            'replies' => 't.is_pinned DESC, t.reply_count DESC, t.id DESC',
            default => 't.is_pinned DESC, t.last_post_at DESC, t.id DESC',
        };
        return $this->db->fetchAll(
            // last_post_user identity is intentionally NOT selected: the listing
            // shows only last_post_at, and joining the last poster would leak the
            // real author of an anonymous final reply. Add a masked column if a
            // "last reply by" byline is ever introduced.
            'SELECT t.*, au.username AS author_username, au.display_name AS author_display_name,
                    COALESCE(op.is_anonymous, 0) AS op_is_anonymous
             FROM threads t
             JOIN users au ON au.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1
             WHERE t.board_id = :board_id AND t.is_deleted = 0 AND t.is_pending = 0
             ORDER BY ' . $orderBy . '
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['board_id' => $boardId],
        );
    }

    public function countByBoard(int $boardId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads WHERE board_id = ? AND is_deleted = 0 AND is_pending = 0',
            [$boardId],
        );
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
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug FROM threads t JOIN boards b ON b.id = t.board_id
             WHERE t.user_id = ? AND t.is_deleted = 0 AND t.is_pending = 0 AND b.visibility = 'public'
               AND NOT EXISTS (SELECT 1 FROM posts op WHERE op.thread_id = t.id AND op.is_op = 1 AND op.is_anonymous = 1)
             ORDER BY t.created_at DESC LIMIT " . $limit,
            [$userId],
        );
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
            'SELECT h.*, u.username AS actor_username, u.display_name AS actor_display_name
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
