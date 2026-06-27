<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class BoardRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> all boards, category+position ordered */
    public function allOrdered(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM boards ORDER BY category_id ASC, position ASC, id ASC',
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function byCategory(int $categoryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM boards WHERE category_id = ? ORDER BY position ASC, id ASC',
            [$categoryId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM boards WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM boards WHERE slug = ?', [$slug]);
    }

    public function slugExists(string $slug): bool
    {
        if ($this->db->fetchValue('SELECT 1 FROM boards WHERE slug = ? LIMIT 1', [$slug]) !== false) {
            return true;
        }
        // Old slugs are globally reserved to avoid collisions / broken links.
        return $this->db->fetchValue('SELECT 1 FROM board_slug_history WHERE old_slug = ? LIMIT 1', [$slug]) !== false;
    }

    /** Resolve a historical slug to the board's current slug (for 301 redirects). */
    public function currentSlugForOld(string $oldSlug): ?array
    {
        return $this->db->fetch(
            'SELECT b.* FROM board_slug_history h JOIN boards b ON b.id = h.board_id WHERE h.old_slug = ?',
            [$oldSlug],
        );
    }

    /**
     * @param array{category_id:int,slug:string,name:string,description:?string,position?:int,visibility?:string,post_min_role?:string,allow_anonymous?:int} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO boards (category_id, slug, name, description, position, post_min_role, visibility, allow_anonymous, created_at)
             VALUES (:category_id, :slug, :name, :description, :position, :post_min_role, :visibility, :allow_anonymous, UTC_TIMESTAMP())',
            [
                'category_id' => $data['category_id'],
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'position' => $data['position'] ?? $this->nextPosition($data['category_id']),
                'post_min_role' => $data['post_min_role'] ?? 'user',
                'visibility' => $data['visibility'] ?? 'public',
                'allow_anonymous' => !empty($data['allow_anonymous']) ? 1 : 0,
            ],
        );
    }

    /**
     * @param array{category_id:int,slug:string,name:string,description:?string,visibility:string,post_min_role:string,allow_anonymous?:int} $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->run(
            'UPDATE boards SET category_id = :category_id, slug = :slug, name = :name, description = :description,
                visibility = :visibility, post_min_role = :post_min_role, allow_anonymous = :allow_anonymous WHERE id = :id',
            [
                'category_id' => $data['category_id'],
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'],
                'visibility' => $data['visibility'],
                'post_min_role' => $data['post_min_role'],
                'allow_anonymous' => !empty($data['allow_anonymous']) ? 1 : 0,
                'id' => $id,
            ],
        );
    }

    public function recordSlugChange(int $boardId, string $oldSlug): void
    {
        $this->db->run(
            'INSERT INTO board_slug_history (board_id, old_slug, changed_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$boardId, $oldSlug],
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM boards WHERE id = ?', [$id]);
    }

    public function hasThreads(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM threads WHERE board_id = ? LIMIT 1', [$id]) !== false;
    }

    public function nextPosition(int $categoryId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM boards WHERE category_id = ?',
            [$categoryId],
        );
    }

    /** Counter bump when a new thread (and its OP) is created. */
    public function onThreadCreated(int $boardId, int $threadId, string $at): void
    {
        $this->db->run(
            'UPDATE boards SET thread_count = thread_count + 1, post_count = post_count + 1,
                last_thread_id = :tid, last_post_at = :at WHERE id = :id',
            ['tid' => $threadId, 'at' => $at, 'id' => $boardId],
        );
    }

    /** Counter bump when a reply is created. */
    public function onReplyCreated(int $boardId, string $at): void
    {
        $this->db->run(
            'UPDATE boards SET post_count = post_count + 1, last_post_at = :at WHERE id = :id',
            ['at' => $at, 'id' => $boardId],
        );
    }

    public function decrementPostCount(int $boardId, int $delta = 1): void
    {
        $this->db->run(
            'UPDATE boards SET post_count = GREATEST(0, CAST(post_count AS SIGNED) - ?) WHERE id = ?',
            [$delta, $boardId],
        );
    }

    /** Re-add to post_count when a soft-deleted post is restored (P2-08). */
    public function incrementPostCount(int $boardId, int $delta = 1): void
    {
        $this->db->run(
            'UPDATE boards SET post_count = post_count + ? WHERE id = ?',
            [$delta, $boardId],
        );
    }

    /** Recompute the board's last-activity cache from its newest non-deleted post. */
    public function recomputeLastPost(int $boardId): void
    {
        $row = $this->db->fetch(
            'SELECT p.thread_id, p.created_at FROM posts p
             JOIN threads t ON t.id = p.thread_id
             WHERE t.board_id = ? AND p.is_deleted = 0 AND t.is_deleted = 0
             ORDER BY p.created_at DESC, p.id DESC LIMIT 1',
            [$boardId],
        );
        if ($row === null) {
            $this->db->run('UPDATE boards SET last_thread_id = NULL, last_post_at = NULL WHERE id = ?', [$boardId]);
            return;
        }
        $this->db->run(
            'UPDATE boards SET last_thread_id = ?, last_post_at = ? WHERE id = ?',
            [(int) $row['thread_id'], $row['created_at'], $boardId],
        );
    }
}
