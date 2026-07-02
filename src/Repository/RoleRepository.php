<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over roles. Services own protected-role guards and audit.
 */
final class RoleRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $roleKey): ?array
    {
        return $this->db->fetch('SELECT * FROM roles WHERE role_key = ?', [$roleKey]);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY kind = 'system' DESC, id ASC");
    }

    /** @param array{role_key:string,name:string,description:?string,created_by:?int} $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO roles (role_key, name, kind, is_protected, role_rank, version, description, created_by)
             VALUES (?, ?, 'custom', 0, 0, 1, ?, ?)",
            [$data['role_key'], $data['name'], $data['description'] ?? null, $data['created_by'] ?? null],
        );
    }

    public function updateDefinition(int $id, string $name, ?string $description): int
    {
        return $this->db->run(
            'UPDATE roles SET name = ?, description = ? WHERE id = ?',
            [$name, $description, $id],
        )->rowCount();
    }

    public function bumpVersion(int $id): void
    {
        $this->db->run('UPDATE roles SET version = version + 1 WHERE id = ?', [$id]);
    }
}
