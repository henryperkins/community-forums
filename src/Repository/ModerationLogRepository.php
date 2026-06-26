<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Append-only moderation/admin audit trail. Rows are never edited or deleted.
 */
final class ModerationLogRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{
     *   actor_id:?int, action:string, target_type:string, target_id:int,
     *   reason?:?string, before?:mixed, after?:mixed
     * } $entry
     */
    public function log(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO moderation_log (actor_id, action, target_type, target_id, reason, before_json, after_json, created_at)
             VALUES (:actor_id, :action, :target_type, :target_id, :reason, :before_json, :after_json, UTC_TIMESTAMP())',
            [
                'actor_id' => $entry['actor_id'],
                'action' => $entry['action'],
                'target_type' => $entry['target_type'],
                'target_id' => $entry['target_id'],
                'reason' => $entry['reason'] ?? null,
                'before_json' => $this->encode($entry['before'] ?? null),
                'after_json' => $this->encode($entry['after'] ?? null),
            ],
        );
    }

    /** @return array<int,array<string,mixed>> recent entries with actor handle */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            'SELECT m.*, u.username AS actor_username, u.display_name AS actor_display_name
             FROM moderation_log m
             LEFT JOIN users u ON u.id = m.actor_id
             ORDER BY m.id DESC
             LIMIT ' . $limit,
        );
    }

    public function countForTarget(string $targetType, int $targetId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM moderation_log WHERE target_type = ? AND target_id = ?',
            [$targetType, $targetId],
        );
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
