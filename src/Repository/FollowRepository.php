<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Asymmetric user→user follow graph (COMMUNITY §4, P2-09). v1 only follows
 * users (target_type='user'); tag/board follows are deferred. Block-awareness
 * is enforced by FollowService, not here.
 */
final class FollowRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return bool true if a new follow row was created (false if already following). */
    public function follow(int $userId, int $targetId): bool
    {
        if ($userId === $targetId) {
            return false;
        }
        return $this->db->run(
            "INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at)
             VALUES (?, 'user', ?, UTC_TIMESTAMP())",
            [$userId, $targetId],
        )->rowCount() === 1;
    }

    public function followTarget(int $userId, string $targetType, int $targetId): bool
    {
        if (!in_array($targetType, ['user', 'board', 'tag'], true)) {
            return false;
        }
        if ($targetType === 'user') {
            return $this->follow($userId, $targetId);
        }
        return $this->db->run(
            'INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $targetType, $targetId],
        )->rowCount() === 1;
    }

    public function unfollow(int $userId, int $targetId): void
    {
        $this->db->run(
            "DELETE FROM follows WHERE user_id = ? AND target_type = 'user' AND target_id = ?",
            [$userId, $targetId],
        );
    }

    public function removeFollower(int $targetId, int $followerId): void
    {
        $this->db->run(
            "DELETE FROM follows WHERE user_id = ? AND target_type = 'user' AND target_id = ?",
            [$followerId, $targetId],
        );
    }

    public function unfollowTarget(int $userId, string $targetType, int $targetId): void
    {
        if ($targetType === 'user') {
            $this->unfollow($userId, $targetId);
            return;
        }
        $this->db->run(
            'DELETE FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ?',
            [$userId, $targetType, $targetId],
        );
    }

    public function isFollowing(int $userId, int $targetId): bool
    {
        return $this->db->fetchValue(
            "SELECT 1 FROM follows WHERE user_id = ? AND target_type = 'user' AND target_id = ? LIMIT 1",
            [$userId, $targetId],
        ) !== false;
    }

    public function isFollowingTarget(int $userId, string $targetType, int $targetId): bool
    {
        if ($targetType === 'user') {
            return $this->isFollowing($userId, $targetId);
        }
        return $this->db->fetchValue(
            'SELECT 1 FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ? LIMIT 1',
            [$userId, $targetType, $targetId],
        ) !== false;
    }

    /** People who follow $targetId. */
    public function followerCount(int $targetId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM follows WHERE target_type = 'user' AND target_id = ?",
            [$targetId],
        );
    }

    /** People $userId follows. */
    public function followingCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'user'",
            [$userId],
        );
    }

    /** @return list<int> ids of the users $userId follows (drives the Following feed). */
    public function followingIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT target_id FROM follows WHERE user_id = ? AND target_type = 'user'",
            [$userId],
        );
        return array_map(static fn (array $r): int => (int) $r['target_id'], $rows);
    }

    /** @return list<int> */
    public function followingBoardIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT target_id FROM follows WHERE user_id = ? AND target_type = 'board'",
            [$userId],
        );
        return array_map(static fn (array $r): int => (int) $r['target_id'], $rows);
    }

    /** @return list<int> */
    public function followingTagIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT target_id FROM follows WHERE user_id = ? AND target_type = 'tag'",
            [$userId],
        );
        return array_map(static fn (array $r): int => (int) $r['target_id'], $rows);
    }

    /** @return array<int,array<string,mixed>> follower user rows, newest first */
    public function listFollowers(int $targetId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.display_name, u.title, u.reputation, f.created_at
             FROM follows f JOIN users u ON u.id = f.user_id
             WHERE f.target_type = 'user' AND f.target_id = ?
             ORDER BY f.created_at DESC, f.user_id DESC
             LIMIT " . $limit . ' OFFSET ' . $offset,
            [$targetId],
        );
    }

    /** @return array<int,array<string,mixed>> followed user rows, newest first */
    public function listFollowing(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.display_name, u.title, u.reputation, f.created_at
             FROM follows f JOIN users u ON u.id = f.target_id
             WHERE f.user_id = ? AND f.target_type = 'user'
             ORDER BY f.created_at DESC, f.target_id DESC
             LIMIT " . $limit . ' OFFSET ' . $offset,
            [$userId],
        );
    }
}
