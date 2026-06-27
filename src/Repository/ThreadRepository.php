<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ThreadRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $boardId, int $userId, string $title, string $slug): int
    {
        return $this->db->insert(
            'INSERT INTO threads (board_id, user_id, title, slug, created_at, last_post_at)
             VALUES (:board_id, :user_id, :title, :slug, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            ['board_id' => $boardId, 'user_id' => $userId, 'title' => $title, 'slug' => $slug],
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
                    b.post_min_role AS board_post_min_role,
                    b.id AS board_id, au.username AS author_username, au.display_name AS author_display_name
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             JOIN users au ON au.id = t.user_id
             WHERE t.id = ?',
            [$id],
        );
    }

    /**
     * One page of non-deleted threads for a board: pinned first, then by last
     * activity (idx_threads_inbox), with author + last-poster names joined.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByBoard(int $boardId, int $limit, int $offset): array
    {
        // LIMIT/OFFSET are app-controlled integers, inlined after an int cast
        // because native prepared statements can't bind them as placeholders.
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            'SELECT t.*, au.username AS author_username, au.display_name AS author_display_name,
                    lu.username AS last_post_username, lu.display_name AS last_post_display_name
             FROM threads t
             JOIN users au ON au.id = t.user_id
             LEFT JOIN users lu ON lu.id = t.last_post_user_id
             WHERE t.board_id = :board_id AND t.is_deleted = 0
             ORDER BY t.is_pinned DESC, t.last_post_at DESC, t.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['board_id' => $boardId],
        );
    }

    public function countByBoard(int $boardId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads WHERE board_id = ? AND is_deleted = 0',
            [$boardId],
        );
    }

    /**
     * Recent non-deleted threads by author, for the PUBLIC profile. Restricted
     * to public boards so a member's activity never reveals the existence or
     * titles of threads in hidden/private boards (the board read/list gate).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentByUser(int $userId, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug FROM threads t JOIN boards b ON b.id = t.board_id
             WHERE t.user_id = ? AND t.is_deleted = 0 AND b.visibility = 'public'
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
             WHERE thread_id = ? AND is_deleted = 0 ORDER BY created_at DESC, id DESC LIMIT 1',
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
