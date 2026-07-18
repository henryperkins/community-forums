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

    /**
     * Recent entries, single-table (PR #44 spec §4) — actor handles are
     * reattached by AuditQueryService::enrich() in the consumers.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            'SELECT m.* FROM moderation_log m ORDER BY m.id DESC LIMIT ' . $limit,
        );
    }

    /**
     * Filterable page of the audit trail for the /admin/audit screen
     * (ADMIN §3.6), single-table (PR #44 spec §4). Filters: `actor_ids`
     * (pre-resolved by AuditQueryService — the substring lookup lives with
     * UserRepository now), `action` (prefix), `target_type` (exact),
     * `target_id` (exact), `from`/`to` (created_at date bounds, inclusive,
     * validated upstream). LIMIT/OFFSET are clamped + inlined
     * (EMULATE_PREPARES=false forbids binding them); every value gets its
     * own named placeholder.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function search(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->searchFilters($filters);
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT m.* FROM moderation_log m';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /** @param array<string,mixed> $filters */
    public function searchCount(array $filters): int
    {
        [$where, $params] = $this->searchFilters($filters);
        $sql = 'SELECT COUNT(*) FROM moderation_log m';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return (int) $this->db->fetchValue($sql, $params);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function searchFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $actorIds = $filters['actor_ids'] ?? null;
        if (is_array($actorIds) && $actorIds !== []) {
            $where[] = 'm.actor_id IN (' . implode(',', array_map('intval', $actorIds)) . ')';
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'm.action LIKE :action';
            $params['action'] = $action . '%';
        }

        $targetType = trim((string) ($filters['target_type'] ?? ''));
        if ($targetType !== '') {
            $where[] = 'm.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        $targetId = trim((string) ($filters['target_id'] ?? ''));
        if ($targetId !== '' && ctype_digit($targetId)) {
            $where[] = 'm.target_id = :target_id';
            $params['target_id'] = (int) $targetId;
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $where[] = 'm.created_at >= :from_at';
            $params['from_at'] = $from . ' 00:00:00';
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $where[] = 'm.created_at <= :to_at';
            $params['to_at'] = $to . ' 23:59:59';
        }

        return [$where, $params];
    }

    /**
     * Recent entries for one target, newest first, single-table — consumers
     * enrich actor handles via AuditQueryService.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentForTarget(string $targetType, int $targetId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            'SELECT m.* FROM moderation_log m
             WHERE m.target_type = ? AND m.target_id = ?
             ORDER BY m.id DESC
             LIMIT ' . $limit,
            [$targetType, $targetId],
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
