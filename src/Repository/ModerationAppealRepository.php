<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ModerationAppealRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM moderation_appeals WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function activeForTarget(int $appellantId, string $targetType, int $targetId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM moderation_appeals
             WHERE appellant_id = ? AND target_type = ? AND target_id = ? AND status = 'open'
             LIMIT 1",
            [$appellantId, $targetType, $targetId],
        );
    }

    /**
     * @param array{
     *   appellant_id:int,target_type:string,target_id:int,moderation_log_id:?int,
     *   original_action:?string,target_summary:?string,reason:string
     * } $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO moderation_appeals
               (appellant_id, target_type, target_id, moderation_log_id, original_action, target_summary, reason, status, created_at)
             VALUES
               (:appellant_id, :target_type, :target_id, :moderation_log_id, :original_action, :target_summary, :reason, \'open\', UTC_TIMESTAMP())',
            $data,
        );
    }

    public function event(int $appealId, ?int $actorId, string $event, ?string $note = null): int
    {
        return $this->db->insert(
            'INSERT INTO moderation_appeal_events (appeal_id, actor_id, event, note, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$appealId, $actorId, $event, $note],
        );
    }

    public function resolve(int $appealId, int $actorId, string $outcome, string $note): bool
    {
        return $this->db->run(
            'UPDATE moderation_appeals
             SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = \'open\'',
            [$outcome, $note !== '' ? $note : null, $actorId, $appealId],
        )->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, r.username AS resolver_username
             FROM moderation_appeals a
             LEFT JOIN users r ON r.id = a.resolved_by
             WHERE a.appellant_id = ?
             ORDER BY a.created_at DESC, a.id DESC',
            [$userId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function openQueue(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            'SELECT a.*, u.username AS appellant_username, u.display_name AS appellant_display_name
             FROM moderation_appeals a
             JOIN users u ON u.id = a.appellant_id
             WHERE a.status = \'open\'
             ORDER BY a.created_at ASC, a.id ASC
             LIMIT ' . $limit,
        );
    }
}
