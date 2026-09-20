<?php

declare(strict_types=1);
namespace App\Repository;

use App\Core\Database;

final class SavedFeedRepository
{
    public function __construct(private Database $db) {}

    public function findOwned(int $userId, int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM saved_feed_filters WHERE user_id = ? AND id = ?', [$userId, $id]);
    }

    public function forUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM saved_feed_filters WHERE user_id = ? ORDER BY position, id', [$userId]);
    }

    public function enabledForDigest(int $userId): array
    {
        return $this->db->fetchAll('SELECT id, filter_json FROM saved_feed_filters WHERE user_id = ? AND digest_enabled = 1 ORDER BY id', [$userId]);
    }

    public function create(int $userId, string $name, string $json, bool $digest): int
    {
        return $this->db->insert('INSERT INTO saved_feed_filters (user_id,name,filter_json,digest_enabled,position,created_at)
            VALUES (?,?,?,?,COALESCE((SELECT next_pos FROM (SELECT MAX(position)+1 AS next_pos FROM saved_feed_filters WHERE user_id=?) x),0),UTC_TIMESTAMP())',
            [$userId, $name, $json, (int) $digest, $userId]);
    }

    public function update(int $userId, int $id, string $name, string $json, bool $digest): void
    {
        $this->db->run('UPDATE saved_feed_filters SET name=?, filter_json=?, digest_enabled=?, updated_at=UTC_TIMESTAMP() WHERE user_id=? AND id=?', [$name, $json, (int) $digest, $userId, $id]);
    }

    public function disableDigest(int $userId, int $id): void
    {
        $this->db->run('UPDATE saved_feed_filters SET digest_enabled=0, updated_at=UTC_TIMESTAMP() WHERE user_id=? AND id=?', [$userId, $id]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->db->run('DELETE FROM saved_feed_filters WHERE user_id=? AND id=?', [$userId, $id]);
    }
}
