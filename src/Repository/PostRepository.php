<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class PostRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{thread_id:int,user_id:int,body:string,body_html:string,is_op?:bool,is_anonymous?:bool,parent_post_id?:?int} $data
     */
    public function create(array $data): int
    {
        // posts.ip is VARBINARY(16) — store packed (inet_pton), NULL if absent/invalid.
        $ip = isset($data['ip']) && is_string($data['ip']) && $data['ip'] !== ''
            ? (@inet_pton($data['ip']) ?: null)
            : null;

        return $this->db->insert(
            'INSERT INTO posts (thread_id, user_id, parent_post_id, body, body_html, is_op, is_anonymous, is_pending, ip, created_at)
             VALUES (:thread_id, :user_id, :parent_post_id, :body, :body_html, :is_op, :is_anonymous, :is_pending, :ip, UTC_TIMESTAMP())',
            [
                'thread_id' => $data['thread_id'],
                'user_id' => $data['user_id'],
                'parent_post_id' => $data['parent_post_id'] ?? null,
                'body' => $data['body'],
                'body_html' => $data['body_html'],
                'is_op' => !empty($data['is_op']) ? 1 : 0,
                'is_anonymous' => !empty($data['is_anonymous']) ? 1 : 0,
                'is_pending' => !empty($data['is_pending']) ? 1 : 0,
                'ip' => $ip,
            ],
        );
    }

    /** Clear/set a post's approval-hold flag (P3-05). */
    public function setPending(int $id, bool $pending): void
    {
        $this->db->run('UPDATE posts SET is_pending = ? WHERE id = ?', [$pending ? 1 : 0, $id]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM posts WHERE id = ?', [$id]);
    }

    /**
     * Lock the selected live posts in canonical render order.
     *
     * @param list<int> $ids
     * @return array<int,array<string,mixed>>
     */
    public function findManyForUpdate(int $threadId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM posts
             WHERE thread_id = ? AND id IN ($placeholders) AND is_deleted = 0
             ORDER BY created_at ASC, id ASC
             FOR UPDATE",
            array_merge([$threadId], $ids),
        );
    }

    /** Post joined with its thread + board, for permission and lock checks. @return array<string,mixed>|null */
    public function findWithContext(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT p.*, t.is_locked AS thread_locked, t.is_deleted AS thread_deleted, t.slug AS thread_slug,
                    t.board_id AS board_id, b.slug AS board_slug, b.visibility AS board_visibility,
                    b.is_archived AS board_is_archived
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.id = ?',
            [$id],
        );
    }

    /**
     * One page of a thread's posts, oldest first, with author. By default only
     * live rows; $includeDeleted=true is the STAFF stream — soft-deleted rows
     * kept in place as restorable stubs (ADMIN §3.3 — "delete" preserves
     * content for restore and accountability). Approval-held (is_pending=1,
     * P3-05) rows are always excluded: they surface in /mod/approvals, and the
     * author is told their reply awaits release.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByThread(int $threadId, int $limit, int $offset, bool $includeDeleted = false): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $deletedClause = $includeDeleted ? '' : ' AND p.is_deleted = 0';
        return $this->db->fetchAll(
            'SELECT p.*, u.username AS author_username, u.display_name AS author_display_name, u.role AS author_role,
                    u.signature AS author_signature, u.reputation AS author_reputation, u.title AS author_title
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = :thread_id' . $deletedClause . ' AND p.is_pending = 0
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['thread_id' => $threadId],
        );
    }

    /** Pagination companion to {@see listByThread} — same visibility rules. */
    public function countByThread(int $threadId, bool $includeDeleted = false): int
    {
        $deletedClause = $includeDeleted ? '' : ' AND is_deleted = 0';
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts WHERE thread_id = ?' . $deletedClause . ' AND is_pending = 0',
            [$threadId],
        );
    }

    /**
     * Distinct visible participants in a thread (authors of non-deleted posts),
     * ordered by first contribution, for the topic-header avatar stack (§5.1).
     * Anonymous posts are EXCLUDED so the stack can never deanonymise a masked
     * author — mirrors the same guard in {@see recentByUser}.
     *
     * @return array<int,array<string,mixed>>
     */
    public function participantsForThread(int $threadId, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            'SELECT u.id AS user_id, u.username AS author_username, u.display_name AS author_display_name,
                    u.role AS author_role, MIN(p.created_at) AS first_at
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = ? AND p.is_deleted = 0 AND p.is_pending = 0 AND p.is_anonymous = 0
             GROUP BY u.id, u.username, u.display_name, u.role
             ORDER BY first_at ASC, u.id ASC
             LIMIT ' . $limit,
            [$threadId],
        );
    }

    /** Count of distinct non-anonymous participants (drives the "+N" overflow). */
    public function participantCountForThread(int $threadId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(DISTINCT user_id) FROM posts
             WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 AND is_anonymous = 0',
            [$threadId],
        );
    }

    /**
     * True when any member OTHER than $userId still has a live (non-deleted)
     * post in the thread — including approval-held replies. Gates topic
     * retraction: the opener may only delete their opening post while they are
     * the thread's sole participant, so removing it can never erase someone
     * else's contribution.
     */
    public function hasOtherParticipants(int $threadId, int $userId): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT EXISTS(SELECT 1 FROM posts WHERE thread_id = ? AND user_id <> ? AND is_deleted = 0)',
            [$threadId, $userId],
        ) === 1;
    }

    /** @return array<int,int> user_id => rank boost */
    public function nonAnonymousParticipantRanks(int $threadId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id, MIN(created_at) AS first_at
             FROM posts
             WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 AND is_anonymous = 0
             GROUP BY user_id
             ORDER BY first_at ASC',
            [$threadId],
        );
        $rank = [];
        $boost = 300;
        foreach ($rows as $row) {
            $rank[(int) $row['user_id']] = $boost;
            $boost = max(100, $boost - 10);
        }
        return $rank;
    }

    /**
     * 1-based page (at $perPage) on which a post appears in the thread render
     * order — created_at ASC, id ASC over visible posts, matching
     * {@see listByThread} with the SAME $includeDeleted stream (staff viewers
     * paginate the with-deleted stream, so their refocus must rank against it
     * too). Lets a failed inline edit re-render the page that actually contains
     * the post. Returns 1 when the post is missing/hidden.
     */
    public function pageOfPost(int $threadId, int $postId, int $perPage, bool $includeDeleted = false): int
    {
        $perPage = max(1, $perPage);
        $deletedClause = $includeDeleted ? '' : ' AND is_deleted = 0';
        $target = $this->db->fetch(
            'SELECT created_at, id FROM posts WHERE id = ? AND thread_id = ?' . $deletedClause . ' AND is_pending = 0',
            [$postId, $threadId],
        );
        if ($target === null) {
            return 1;
        }
        // Rank = count of visible posts up to and including the target in render
        // order; its page is ceil(rank / perPage). Native prepares (emulation off)
        // forbid reusing a named placeholder, so created_at is bound twice.
        $rank = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts
             WHERE thread_id = :tid' . $deletedClause . ' AND is_pending = 0
               AND (created_at < :ca1 OR (created_at = :ca2 AND id <= :pid))',
            ['tid' => $threadId, 'ca1' => (string) $target['created_at'], 'ca2' => (string) $target['created_at'], 'pid' => $postId],
        );
        return max(1, (int) ceil($rank / $perPage));
    }

    /**
     * Approval queue (P3-05): pending non-OP replies, oldest first, with author +
     * thread + board context. Optionally scoped to a set of board ids (NULL = all,
     * for admins). OP holds are surfaced via the pending-threads query instead.
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
            $ids = implode(',', array_map('intval', $boardIds));
            $scope = ' AND t.board_id IN (' . $ids . ')';
        }
        return $this->db->fetchAll(
            'SELECT p.id, p.body, p.created_at, p.thread_id,
                    u.username AS author_username, t.title AS thread_title, t.slug AS thread_slug,
                    b.id AS board_id, b.slug AS board_slug, b.name AS board_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.is_pending = 1 AND p.is_deleted = 0 AND p.is_op = 0' . $scope . '
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT ' . $limit,
        );
    }

    /**
     * Recent non-deleted posts by author for the PUBLIC profile activity tab.
     * Restricted to public boards so activity never reveals hidden/private-board
     * content (mirrors ThreadRepository::recentByUser). Joins thread title/slug.
     * Anonymous posts are EXCLUDED — listing them under their author's profile
     * would defeat the masking (the page itself is the author's identity).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentByUser(int $userId, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT p.id, p.thread_id, p.body, p.created_at, p.is_op,
                    t.title AS thread_title, t.slug AS thread_slug, b.slug AS board_slug
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.user_id = ? AND p.is_deleted = 0 AND p.is_anonymous = 0 AND p.is_pending = 0
               AND t.is_deleted = 0 AND b.visibility = 'public'
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT " . $limit,
            [$userId],
        );
    }

    public function update(int $id, string $body, string $bodyHtml, int $editedBy): void
    {
        $this->db->run(
            'UPDATE posts SET body = :body, body_html = :body_html, edited_at = UTC_TIMESTAMP(), edited_by = :editor
             WHERE id = :id',
            ['body' => $body, 'body_html' => $bodyHtml, 'editor' => $editedBy, 'id' => $id],
        );
    }

    /** @return int rows affected (0 if it was already deleted — caller skips counters) */
    public function softDelete(int $id, int $byUserId): int
    {
        return $this->db->run(
            'UPDATE posts SET is_deleted = 1, deleted_by = ?, deleted_at = UTC_TIMESTAMP() WHERE id = ? AND is_deleted = 0',
            [$byUserId, $id],
        )->rowCount();
    }

    /** @return int rows affected (0 if it was not deleted — caller skips counters) */
    public function restore(int $id): int
    {
        // Clear deleted_at so a restored post's media is never reclaimed by the sweep.
        return $this->db->run(
            'UPDATE posts SET is_deleted = 0, deleted_by = NULL, deleted_at = NULL WHERE id = ? AND is_deleted = 1',
            [$id],
        )->rowCount();
    }
}
