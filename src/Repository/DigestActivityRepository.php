<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class DigestActivityRepository
{
    public function __construct(private Database $db) {}

    public function sources(int $userId): array
    {
        return array_map(static fn (array $row): array => [
            'target_type' => $row['target_type'], 'target_id' => (int) $row['target_id'],
        ], $this->db->fetchAll("SELECT target_type, target_id FROM subscriptions WHERE user_id = ? AND frequency = 'daily' AND email_enabled = 1", [$userId]));
    }

    /** Both the original source selection and its current settings must permit activity. */
    public function activity(int $userId, array $payload, array $scope): array
    {
        $threadIds = $boardIds = [];
        foreach ($payload['sources']['subscriptions'] as $source) {
            if ($source['target_type'] === 'thread') { $threadIds[] = (int) $source['target_id']; }
            else { $boardIds[] = (int) $source['target_id']; }
        }
        $threadIds = implode(',', $threadIds ?: [0]);
        $boardIds = implode(',', $boardIds ?: [0]);
        $access = NotificationEligibility::board($scope);
        $blocks = NotificationEligibility::unblocked((string) $userId, 'p.user_id');
        return $this->db->fetchAll(
            "SELECT t.id AS thread_id, t.title, t.slug, COUNT(*) AS n
             FROM posts p JOIN threads t ON t.id = p.thread_id JOIN boards b ON b.id = t.board_id
             WHERE p.is_deleted = 0 AND p.is_pending = 0 AND p.user_id <> :uid
               AND p.created_at > :start AND p.created_at <= :end AND p.id <= :max_id
               AND t.is_deleted = 0 AND t.is_pending = 0 AND $access AND $blocks
               AND (
                 (t.id IN ($threadIds) AND EXISTS (SELECT 1 FROM subscriptions s
                    WHERE s.user_id = :uid2 AND s.target_type = 'thread' AND s.target_id = t.id
                      AND s.frequency = 'daily' AND s.email_enabled = 1))
                 OR (t.board_id IN ($boardIds) AND EXISTS (SELECT 1 FROM subscriptions sb
                    WHERE sb.user_id = :uid3 AND sb.target_type = 'board' AND sb.target_id = t.board_id
                      AND sb.frequency = 'daily' AND sb.email_enabled = 1)
                   AND NOT EXISTS (SELECT 1 FROM subscriptions so WHERE so.user_id = :uid4
                    AND so.target_type = 'thread' AND so.target_id = t.id))
               )
             GROUP BY t.id, t.title, t.slug ORDER BY MAX(p.created_at) DESC, t.id DESC",
            ['uid' => $userId, 'start' => $payload['window_start_utc'], 'end' => $payload['window_end_utc'],
             'max_id' => $payload['max_post_id'], 'uid2' => $userId, 'uid3' => $userId, 'uid4' => $userId],
        );
    }
}
