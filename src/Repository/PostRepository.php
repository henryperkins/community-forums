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
     * @param array{thread_id:int,user_id:int,body:string,body_html:string,is_op?:bool,parent_post_id?:?int} $data
     */
    public function create(array $data): int
    {
        // posts.ip is VARBINARY(16) — store packed (inet_pton), NULL if absent/invalid.
        $ip = isset($data['ip']) && is_string($data['ip']) && $data['ip'] !== ''
            ? (@inet_pton($data['ip']) ?: null)
            : null;

        return $this->db->insert(
            'INSERT INTO posts (thread_id, user_id, parent_post_id, body, body_html, is_op, ip, created_at)
             VALUES (:thread_id, :user_id, :parent_post_id, :body, :body_html, :is_op, :ip, UTC_TIMESTAMP())',
            [
                'thread_id' => $data['thread_id'],
                'user_id' => $data['user_id'],
                'parent_post_id' => $data['parent_post_id'] ?? null,
                'body' => $data['body'],
                'body_html' => $data['body_html'],
                'is_op' => !empty($data['is_op']) ? 1 : 0,
                'ip' => $ip,
            ],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM posts WHERE id = ?', [$id]);
    }

    /** Post joined with its thread + board, for permission and lock checks. @return array<string,mixed>|null */
    public function findWithContext(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT p.*, t.is_locked AS thread_locked, t.is_deleted AS thread_deleted, t.slug AS thread_slug,
                    t.board_id AS board_id, b.slug AS board_slug, b.visibility AS board_visibility
             FROM posts p
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE p.id = ?',
            [$id],
        );
    }

    /**
     * One page of non-deleted posts for a thread, oldest first, with author.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByThread(int $threadId, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            'SELECT p.*, u.username AS author_username, u.display_name AS author_display_name, u.role AS author_role
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = :thread_id AND p.is_deleted = 0
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['thread_id' => $threadId],
        );
    }

    public function countByThread(int $threadId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts WHERE thread_id = ? AND is_deleted = 0',
            [$threadId],
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
            'UPDATE posts SET is_deleted = 1, deleted_by = ? WHERE id = ? AND is_deleted = 0',
            [$byUserId, $id],
        )->rowCount();
    }

    /** @return int rows affected (0 if it was not deleted — caller skips counters) */
    public function restore(int $id): int
    {
        return $this->db->run(
            'UPDATE posts SET is_deleted = 0, deleted_by = NULL WHERE id = ? AND is_deleted = 1',
            [$id],
        )->rowCount();
    }
}
