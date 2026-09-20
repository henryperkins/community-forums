<?php

declare(strict_types=1);
namespace App\Repository;

use App\Core\Database;

final class BoardFolderRepository
{
    public function __construct(private Database $db) {}

    public function findOwned(int $userId, int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM board_folders WHERE user_id=? AND id=?', [$userId, $id]);
    }

    public function forUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT f.id AS folder_id, f.name AS folder_name, b.id AS board_id, b.name AS board_name,
            b.slug AS board_slug, b.visibility AS board_visibility FROM board_folders f
            LEFT JOIN board_folder_boards fb ON fb.folder_id=f.id LEFT JOIN boards b ON b.id=fb.board_id
            WHERE f.user_id=? ORDER BY f.position,f.id,fb.position,b.name', [$userId]);
    }

    public function create(int $userId, string $name): int
    {
        return $this->db->insert('INSERT INTO board_folders (user_id,name,position,created_at)
            VALUES (?,?,COALESCE((SELECT next_pos FROM (SELECT MAX(position)+1 AS next_pos FROM board_folders WHERE user_id=?) x),0),UTC_TIMESTAMP())', [$userId, $name, $userId]);
    }

    public function rename(int $userId, int $id, string $name): void
    {
        $this->db->run('UPDATE board_folders SET name=?, updated_at=UTC_TIMESTAMP() WHERE user_id=? AND id=?', [$name, $userId, $id]);
    }

    public function delete(int $userId, int $id): void
    {
        // The FK cascades only the folder associations.
        $this->db->run('DELETE FROM board_folders WHERE user_id=? AND id=?', [$userId, $id]);
    }

    public function addBoard(int $folderId, int $boardId): void
    {
        $this->db->run('INSERT INTO board_folder_boards (folder_id,board_id,position,created_at)
            VALUES (?,?,COALESCE((SELECT next_pos FROM (SELECT MAX(position)+1 AS next_pos FROM board_folder_boards WHERE folder_id=?) x),0),UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE position=position', [$folderId, $boardId, $folderId]);
    }

    public function removeBoard(int $folderId, int $boardId): void
    {
        $this->db->run('DELETE FROM board_folder_boards WHERE folder_id=? AND board_id=?', [$folderId, $boardId]);
    }
}
