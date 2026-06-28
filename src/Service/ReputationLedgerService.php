<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\UserRepository;

final class ReputationLedgerService
{
    public function __construct(
        private Database $db,
        private UserRepository $users,
    ) {
    }

    public function apply(
        int $userId,
        ?int $boardId,
        string $sourceType,
        ?int $sourceId,
        string $logicalKey,
        int $delta,
        ?string $eventAt = null,
    ): void {
        if ($delta === 0) {
            return;
        }
        $eventAt ??= gmdate('Y-m-d H:i:s');
        $this->db->transaction(function () use ($userId, $boardId, $sourceType, $sourceId, $logicalKey, $delta, $eventAt): void {
            $inserted = $this->db->run(
                'INSERT IGNORE INTO reputation_events
                    (user_id, board_id, source_type, source_id, logical_key, delta, applied_delta, event_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$userId, $boardId, $sourceType, $sourceId, $logicalKey, $delta, $delta, $eventAt],
            )->rowCount();
            if ($inserted === 1) {
                $this->users->incrementReputation($userId, $delta);
                return;
            }
            $restored = $this->db->run(
                'UPDATE reputation_events
                 SET user_id = ?, board_id = ?, source_type = ?, source_id = ?,
                     reversed_at = NULL, reversed_by = NULL, reversal_reason = NULL,
                     delta = ?, applied_delta = ?, event_at = ?
                 WHERE logical_key = ? AND reversed_at IS NOT NULL',
                [$userId, $boardId, $sourceType, $sourceId, $delta, $delta, $eventAt, $logicalKey],
            )->rowCount();
            if ($restored === 1) {
                $this->users->incrementReputation($userId, $delta);
            }
        });
    }

    public function reverse(string $logicalKey, ?int $actorId = null, ?string $reason = null): bool
    {
        return $this->db->transaction(function () use ($logicalKey, $actorId, $reason): bool {
            $event = $this->db->fetch('SELECT * FROM reputation_events WHERE logical_key = ? AND reversed_at IS NULL', [$logicalKey]);
            if ($event === null || $event['reversed_at'] !== null) {
                return false;
            }
            $updated = $this->db->run(
                'UPDATE reputation_events
                 SET reversed_at = UTC_TIMESTAMP(), reversed_by = ?, reversal_reason = ?
                 WHERE id = ? AND reversed_at IS NULL',
                [$actorId, $reason !== null ? mb_substr($reason, 0, 255) : null, (int) $event['id']],
            )->rowCount();
            if ($updated !== 1) {
                return false;
            }
            $this->users->incrementReputation((int) $event['user_id'], -1 * (int) $event['applied_delta']);
            return true;
        });
    }

    public function reconcileUser(int $userId): void
    {
        $total = (int) $this->db->fetchValue(
            'SELECT COALESCE(SUM(applied_delta), 0)
             FROM reputation_events
             WHERE user_id = ? AND reversed_at IS NULL',
            [$userId],
        );
        $this->db->run('UPDATE users SET reputation = GREATEST(0, ?) WHERE id = ?', [$total, $userId]);
    }

    public function reconcileAllUsers(): int
    {
        return $this->db->run(
            'UPDATE users u SET reputation = GREATEST(0, (
                SELECT COALESCE(SUM(e.applied_delta), 0)
                FROM reputation_events e
                WHERE e.user_id = u.id AND e.reversed_at IS NULL
             ))',
        )->rowCount();
    }

    /**
     * Rebuild the ledger from canonical reactions + accepted answers, reverse
     * active events whose source no longer exists, then make users.reputation
     * agree with the active ledger.
     *
     * @return array{reaction_events:int,accepted_answer_events:int,reversed_reactions:int,reversed_accepted_answers:int,reconciled_users:int}
     */
    public function rebuildFromCanonical(int $solvedBonus = 5): array
    {
        return $this->db->transaction(function () use ($solvedBonus): array {
            $reactionEvents = $this->db->run(
                "INSERT INTO reputation_events
                    (user_id, board_id, source_type, source_id, logical_key, delta, applied_delta, event_at, created_at)
                 SELECT p.user_id, t.board_id, 'reaction', p.id,
                        CONCAT('reaction:', p.id, ':', r.user_id, ':', SHA1(r.emoji)),
                        1, 1, r.created_at, UTC_TIMESTAMP()
                 FROM reactions r
                 JOIN posts p ON p.id = r.post_id
                 JOIN threads t ON t.id = p.thread_id
                 WHERE p.is_deleted = 0 AND r.user_id <> p.user_id
                 ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    board_id = VALUES(board_id),
                    source_type = VALUES(source_type),
                    source_id = VALUES(source_id),
                    delta = VALUES(delta),
                    applied_delta = VALUES(applied_delta),
                    event_at = VALUES(event_at),
                    reversed_at = NULL,
                    reversed_by = NULL,
                    reversal_reason = NULL",
            )->rowCount();

            $acceptedEvents = $this->db->run(
                "INSERT INTO reputation_events
                    (user_id, board_id, source_type, source_id, logical_key, delta, applied_delta, event_at, created_at)
                 SELECT p.user_id, t.board_id, 'accepted_answer', p.id,
                        CONCAT('accepted_answer:', t.id),
                        ?, ?, COALESCE(t.status_changed_at, p.created_at), UTC_TIMESTAMP()
                 FROM threads t
                 JOIN posts p ON p.id = t.accepted_answer_post_id
                 WHERE t.is_deleted = 0 AND p.is_deleted = 0 AND p.user_id <> t.user_id
                 ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    board_id = VALUES(board_id),
                    source_type = VALUES(source_type),
                    source_id = VALUES(source_id),
                    delta = VALUES(delta),
                    applied_delta = VALUES(applied_delta),
                    event_at = VALUES(event_at),
                    reversed_at = NULL,
                    reversed_by = NULL,
                    reversal_reason = NULL",
                [$solvedBonus, $solvedBonus],
            )->rowCount();

            $reversedReactions = $this->db->run(
                "UPDATE reputation_events e
                 SET reversed_at = UTC_TIMESTAMP(), reversal_reason = 'canonical_rebuild'
                 WHERE e.source_type = 'reaction' AND e.reversed_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1
                       FROM reactions r
                       JOIN posts p ON p.id = r.post_id
                       WHERE p.id = e.source_id
                         AND p.user_id = e.user_id
                         AND p.is_deleted = 0
                         AND r.user_id <> p.user_id
                         AND e.logical_key = CONCAT('reaction:', p.id, ':', r.user_id, ':', SHA1(r.emoji))
                   )",
            )->rowCount();

            $reversedAccepted = $this->db->run(
                "UPDATE reputation_events e
                 SET reversed_at = UTC_TIMESTAMP(), reversal_reason = 'canonical_rebuild'
                 WHERE e.source_type = 'accepted_answer' AND e.reversed_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1
                       FROM threads t
                       JOIN posts p ON p.id = t.accepted_answer_post_id
                       WHERE e.logical_key = CONCAT('accepted_answer:', t.id)
                         AND e.source_id = p.id
                         AND e.user_id = p.user_id
                         AND t.is_deleted = 0
                         AND p.is_deleted = 0
                         AND p.user_id <> t.user_id
                   )",
            )->rowCount();

            return [
                'reaction_events' => $reactionEvents,
                'accepted_answer_events' => $acceptedEvents,
                'reversed_reactions' => $reversedReactions,
                'reversed_accepted_answers' => $reversedAccepted,
                'reconciled_users' => $this->reconcileAllUsers(),
            ];
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function leaderboard(string $window, ?int $boardId, int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $where = ['e.reversed_at IS NULL'];
        $params = [];
        if ($window === 'week') {
            $where[] = 'e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)';
        } elseif ($window === 'month') {
            $where[] = 'e.event_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)';
        }
        if ($boardId !== null) {
            $where[] = 'e.board_id = ?';
            $params[] = $boardId;
        }
        return $this->db->fetchAll(
            'SELECT u.id, u.username, u.display_name, u.title, u.post_count,
                    SUM(e.applied_delta) AS reputation
             FROM reputation_events e
             JOIN users u ON u.id = e.user_id
             LEFT JOIN user_preferences pf ON pf.user_id = u.id
             WHERE ' . implode(' AND ', $where) . "
               AND u.status <> 'banned'
               AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pf.prefs, '$.hide_from_leaderboard')), 'false') <> 'true'
             GROUP BY u.id, u.username, u.display_name, u.title, u.post_count
             HAVING reputation > 0
             ORDER BY reputation DESC, u.id DESC
             LIMIT " . $limit,
            $params,
        );
    }
}
