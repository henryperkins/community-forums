<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Per-user board organization (USER §4.3, P2-10): favorite, mute, and manual
 * ordering. Favorites surface in a sidebar group; muted boards are excluded from
 * the sidebar and unread counts.
 */
final class UserBoardPrefRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array{is_favorite:int,is_muted:int,position:?int}> board_id => prefs */
    public function forUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT board_id, is_favorite, is_muted, position FROM user_board_prefs WHERE user_id = ?',
            [$userId],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['board_id']] = [
                'is_favorite' => (int) $r['is_favorite'],
                'is_muted' => (int) $r['is_muted'],
                'position' => $r['position'] === null ? null : (int) $r['position'],
            ];
        }
        return $out;
    }

    /** @return list<int> board ids the user has muted */
    public function mutedBoardIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT board_id FROM user_board_prefs WHERE user_id = ? AND is_muted = 1',
            [$userId],
        );
        return array_map(static fn (array $r): int => (int) $r['board_id'], $rows);
    }

    private function upsert(int $userId, int $boardId, string $column, int|null $value): void
    {
        // $column is a fixed whitelist value chosen by the caller, never user input.
        $this->db->run(
            "INSERT INTO user_board_prefs (user_id, board_id, $column)
             VALUES (:uid, :bid, :val)
             ON DUPLICATE KEY UPDATE $column = VALUES($column)",
            ['uid' => $userId, 'bid' => $boardId, 'val' => $value],
        );
    }

    public function setFavorite(int $userId, int $boardId, bool $favorite): void
    {
        $this->upsert($userId, $boardId, 'is_favorite', $favorite ? 1 : 0);
    }

    public function setMuted(int $userId, int $boardId, bool $muted): void
    {
        $this->upsert($userId, $boardId, 'is_muted', $muted ? 1 : 0);
    }

    public function setPosition(int $userId, int $boardId, ?int $position): void
    {
        $this->upsert($userId, $boardId, 'position', $position);
    }
}
