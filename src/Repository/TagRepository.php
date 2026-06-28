<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class TagRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM tags WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $tag = $this->db->fetch('SELECT * FROM tags WHERE slug = ?', [$slug]);
        if ($tag !== null) {
            return $tag;
        }
        return $this->db->fetch(
            'SELECT t.* FROM tag_aliases a JOIN tags t ON t.id = a.tag_id WHERE a.alias_slug = ?',
            [$slug],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allEnabled(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM tags WHERE is_enabled = 1 AND visibility = 'public' ORDER BY name ASC, id ASC",
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allForAdmin(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tags ORDER BY is_enabled DESC, name ASC, id ASC',
        );
    }

    public function create(string $slug, string $name, ?string $description, int $actorId): int
    {
        return $this->db->insert(
            'INSERT INTO tags (slug, name, description, created_by, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$slug, $name, $description, $actorId],
        );
    }

    public function update(int $id, string $slug, string $name, ?string $description, bool $enabled, string $visibility = 'public'): void
    {
        $visibility = in_array($visibility, ['public', 'hidden'], true) ? $visibility : 'public';
        $current = $this->find($id);
        if ($current !== null && (string) $current['slug'] !== $slug) {
            $this->db->run(
                'INSERT IGNORE INTO tag_aliases (alias_slug, tag_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
                [(string) $current['slug'], $id],
            );
        }
        $this->db->run(
            'UPDATE tags SET slug = ?, name = ?, description = ?, visibility = ?, is_enabled = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$slug, $name, $description, $visibility, $enabled ? 1 : 0, $id],
        );
    }

    public function mergeInto(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }
        $source = $this->find($sourceId);
        $target = $this->find($targetId);
        if ($source === null || $target === null) {
            return;
        }

        $this->db->transaction(function () use ($sourceId, $targetId, $source): void {
            $this->db->run(
                'INSERT IGNORE INTO thread_tags (thread_id, tag_id, added_by, created_at)
                 SELECT thread_id, ?, added_by, created_at FROM thread_tags WHERE tag_id = ?',
                [$targetId, $sourceId],
            );
            $this->db->run(
                "INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at)
                 SELECT user_id, 'tag', ?, created_at FROM follows WHERE target_type = 'tag' AND target_id = ?",
                [$targetId, $sourceId],
            );
            $this->db->run('DELETE FROM thread_tags WHERE tag_id = ?', [$sourceId]);
            $this->db->run("DELETE FROM follows WHERE target_type = 'tag' AND target_id = ?", [$sourceId]);
            $this->db->run(
                'INSERT IGNORE INTO tag_aliases (alias_slug, tag_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
                [(string) $source['slug'], $targetId],
            );
            $this->db->run('UPDATE tags SET is_enabled = 0, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$sourceId]);
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function forThread(int $threadId): array
    {
        return $this->db->fetchAll(
            "SELECT t.*
             FROM thread_tags tt JOIN tags t ON t.id = tt.tag_id
             WHERE tt.thread_id = ? AND t.is_enabled = 1 AND t.visibility = 'public'
             ORDER BY t.name ASC",
            [$threadId],
        );
    }

    /** @param list<int> $tagIds */
    public function setForThread(int $threadId, array $tagIds, int $actorId): void
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), fn (int $id): bool => $id > 0)));
        if ($tagIds !== []) {
            $place = implode(',', array_fill(0, count($tagIds), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id FROM tags WHERE is_enabled = 1 AND visibility = 'public' AND id IN ($place)",
                $tagIds,
            );
            $tagIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        }
        $this->db->transaction(function () use ($threadId, $tagIds, $actorId): void {
            $this->db->run('DELETE FROM thread_tags WHERE thread_id = ?', [$threadId]);
            foreach ($tagIds as $tagId) {
                $this->db->run(
                    'INSERT IGNORE INTO thread_tags (thread_id, tag_id, added_by, created_at)
                     VALUES (?, ?, ?, UTC_TIMESTAMP())',
                    [$threadId, $tagId, $actorId],
                );
            }
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function threadsForTag(int $tagId, int $viewerId, bool $isAdmin, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        [$visSql, $params] = $this->visibility($isAdmin, $viewerId);
        array_unshift($params, $tagId);

        return $this->db->fetchAll(
            "SELECT t.*, b.slug AS board_slug, b.name AS board_name, b.visibility AS board_visibility,
                    u.username AS author_username, u.display_name AS author_display_name,
                    COALESCE(op.is_anonymous, 0) AS op_is_anonymous
             FROM thread_tags tt
             JOIN threads t ON t.id = tt.thread_id
             JOIN boards b ON b.id = t.board_id
             JOIN users u ON u.id = t.user_id
             LEFT JOIN posts op ON op.thread_id = t.id AND op.is_op = 1
             WHERE tt.tag_id = ? AND b.tags_enabled = 1 AND t.is_deleted = 0 AND t.is_pending = 0 AND ($visSql)
             ORDER BY t.last_post_at DESC, t.id DESC
             LIMIT " . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function countThreadsForTag(int $tagId, int $viewerId, bool $isAdmin): int
    {
        [$visSql, $params] = $this->visibility($isAdmin, $viewerId);
        array_unshift($params, $tagId);
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*)
             FROM thread_tags tt
             JOIN threads t ON t.id = tt.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE tt.tag_id = ? AND b.tags_enabled = 1 AND t.is_deleted = 0 AND t.is_pending = 0 AND ($visSql)",
            $params,
        );
    }

    /** @return array{0:string,1:list<int>} */
    private function visibility(bool $isAdmin, int $viewerId): array
    {
        if ($isAdmin) {
            return ['1=1', []];
        }
        return [
            "(b.visibility = 'public'
              OR (b.visibility = 'private'
                  AND EXISTS (SELECT 1 FROM board_members bm WHERE bm.board_id = b.id AND bm.user_id = ?)))",
            [$viewerId],
        ];
    }
}
