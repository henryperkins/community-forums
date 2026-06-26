<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thread/board subscriptions (P2-03). Each row carries independent in-app/email
 * channels and an instant|daily|off frequency. A thread subscription overrides
 * the board subscription for that thread (DESIGN §8.3, PHASE_2_PLAN §2).
 */
final class SubscriptionRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function get(int $userId, string $targetType, int $targetId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM subscriptions WHERE user_id = ? AND target_type = ? AND target_id = ?',
            [$userId, $targetType, $targetId],
        );
    }

    public function set(int $userId, string $targetType, int $targetId, bool $inApp, bool $email, string $frequency): void
    {
        $this->db->run(
            'INSERT INTO subscriptions (user_id, target_type, target_id, in_app_enabled, email_enabled, frequency, created_at)
             VALUES (:uid, :tt, :tid, :ina, :em, :freq, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE in_app_enabled = VALUES(in_app_enabled), email_enabled = VALUES(email_enabled), frequency = VALUES(frequency)',
            ['uid' => $userId, 'tt' => $targetType, 'tid' => $targetId, 'ina' => $inApp ? 1 : 0, 'em' => $email ? 1 : 0, 'freq' => $frequency],
        );
    }

    public function delete(int $userId, string $targetType, int $targetId): void
    {
        $this->db->run(
            'DELETE FROM subscriptions WHERE user_id = ? AND target_type = ? AND target_id = ?',
            [$userId, $targetType, $targetId],
        );
    }

    /**
     * The subscription that actually governs a thread for a user: the thread
     * row if present, else the board row, else null.
     *
     * @return array<string,mixed>|null
     */
    public function effectiveForThread(int $userId, int $threadId, int $boardId): ?array
    {
        $thread = $this->get($userId, 'thread', $threadId);
        if ($thread !== null) {
            return $thread;
        }
        return $this->get($userId, 'board', $boardId);
    }

    /**
     * Precedence-resolved subscriber set for a new post in a thread: every user
     * subscribed to the thread, plus board subscribers who lack a thread row,
     * with frequency != 'off'. The actor, blocked users and inaccessible
     * recipients are filtered by NotificationService, not here.
     *
     * @return array<int,array{user_id:int,in_app_enabled:int,email_enabled:int,frequency:string}>
     */
    public function subscribersForThread(int $threadId, int $boardId): array
    {
        return $this->db->fetchAll(
            "SELECT s.user_id, s.in_app_enabled, s.email_enabled, s.frequency FROM (
                SELECT user_id, in_app_enabled, email_enabled, frequency
                FROM subscriptions WHERE target_type = 'thread' AND target_id = ?
                UNION
                SELECT bs.user_id, bs.in_app_enabled, bs.email_enabled, bs.frequency
                FROM subscriptions bs
                WHERE bs.target_type = 'board' AND bs.target_id = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM subscriptions ts
                    WHERE ts.target_type = 'thread' AND ts.target_id = ? AND ts.user_id = bs.user_id
                  )
             ) s
             WHERE s.frequency <> 'off'",
            [$threadId, $boardId, $threadId],
        );
    }

    /** @return array<int,array<string,mixed>> the user's subscriptions, for the settings list */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM subscriptions WHERE user_id = ? ORDER BY target_type, target_id',
            [$userId],
        );
    }

    /**
     * The user's active subscriptions with the thread title / board name resolved
     * for the /settings/notifications list. Targets that no longer exist are
     * dropped (LEFT JOIN + filter) so the list never links to deleted content.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForUserWithContext(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT s.target_type, s.target_id, s.in_app_enabled, s.email_enabled, s.frequency,
                    t.title AS thread_title, t.slug AS thread_slug, t.is_deleted AS thread_deleted,
                    b.name AS board_name, b.slug AS board_slug
             FROM subscriptions s
             LEFT JOIN threads t ON s.target_type = 'thread' AND t.id = s.target_id
             LEFT JOIN boards  b ON s.target_type = 'board'  AND b.id = s.target_id
             WHERE s.user_id = ? AND s.frequency <> 'off'
               AND (
                    (s.target_type = 'thread' AND t.id IS NOT NULL AND t.is_deleted = 0)
                 OR (s.target_type = 'board'  AND b.id IS NOT NULL)
               )
             ORDER BY s.target_type, s.target_id",
            [$userId],
        );
    }
}
