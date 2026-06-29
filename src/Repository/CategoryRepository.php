<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class CategoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM categories ORDER BY position ASC, id ASC');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public function create(string $name, ?int $position = null): int
    {
        $position ??= $this->nextPosition();
        return $this->db->insert(
            'INSERT INTO categories (name, position) VALUES (?, ?)',
            [$name, $position],
        );
    }

    public function update(int $id, string $name, int $position): void
    {
        $this->db->run('UPDATE categories SET name = ?, position = ? WHERE id = ?', [$name, $position, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM categories WHERE id = ?', [$id]);
    }

    public function hasBoards(int $id): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM boards WHERE category_id = ? LIMIT 1', [$id]) !== false;
    }

    public function nextPosition(): int
    {
        return (int) $this->db->fetchValue('SELECT COALESCE(MAX(position), -1) + 1 FROM categories');
    }

    /**
     * Dense renumber to 0..n-1 in the submitted order. Caller wraps this in a
     * transaction; categories.position has no unique key, so no offset dance is
     * needed. Ids are clamped to int (EMULATE_PREPARES=false).
     *
     * @param array<int,int> $orderedIds
     */
    public function setPositions(array $orderedIds): void
    {
        $pos = 0;
        foreach ($orderedIds as $id) {
            $this->db->run('UPDATE categories SET position = ? WHERE id = ?', [$pos, (int) $id]);
            $pos++;
        }
    }
}
