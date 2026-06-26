<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Block list access + the shared block predicate (PHASE_2_PLAN Milestone 0).
 *
 * Semantics: a row (user_id = blocker, blocked_user_id = target) means the
 * blocker no longer wants contact from the target. For interaction gates
 * (DMs, mentions, follows, notification fan-out) the canonical predicate is
 * blockedEitherWay(): contact is denied if EITHER party blocked the other, so
 * a blocked user cannot reach the blocker and the blocker is not contacted.
 */
final class BlockRepository
{
    public function __construct(private Database $db)
    {
    }

    public function block(int $userId, int $blockedUserId): void
    {
        if ($userId === $blockedUserId) {
            return;
        }
        $this->db->run(
            'INSERT IGNORE INTO blocks (user_id, blocked_user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$userId, $blockedUserId],
        );
    }

    public function unblock(int $userId, int $blockedUserId): void
    {
        $this->db->run(
            'DELETE FROM blocks WHERE user_id = ? AND blocked_user_id = ?',
            [$userId, $blockedUserId],
        );
    }

    /** True when $blockerId has blocked $targetId (directional). */
    public function blocks(int $blockerId, int $targetId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM blocks WHERE user_id = ? AND blocked_user_id = ? LIMIT 1',
            [$blockerId, $targetId],
        ) !== false;
    }

    /** Canonical interaction predicate: a block exists in either direction. */
    public function blockedEitherWay(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }
        return $this->db->fetchValue(
            'SELECT 1 FROM blocks
             WHERE (user_id = ? AND blocked_user_id = ?)
                OR (user_id = ? AND blocked_user_id = ?)
             LIMIT 1',
            [$a, $b, $b, $a],
        ) !== false;
    }

    /** @return array<int,array<string,mixed>> users this person has blocked, newest first */
    public function listBlocked(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT b.blocked_user_id, b.created_at, u.username, u.display_name
             FROM blocks b JOIN users u ON u.id = b.blocked_user_id
             WHERE b.user_id = ? ORDER BY b.created_at DESC',
            [$userId],
        );
    }

    /** @param list<int> $candidateIds @return array<int,bool> id => blockedEitherWay($userId, id) */
    public function blockedMap(int $userId, array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_filter($candidateIds, fn ($i) => (int) $i !== $userId)));
        if ($candidateIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($candidateIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT user_id, blocked_user_id FROM blocks
             WHERE (user_id = ? AND blocked_user_id IN ($place))
                OR (blocked_user_id = ? AND user_id IN ($place))",
            array_merge([$userId], $candidateIds, [$userId], $candidateIds),
        );
        $map = [];
        foreach ($rows as $r) {
            $other = (int) $r['user_id'] === $userId ? (int) $r['blocked_user_id'] : (int) $r['user_id'];
            $map[$other] = true;
        }
        return $map;
    }
}
