<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Append-only branding revisions (ADR 0054, increment 2). Each row is the whole
 * published branding document after one change, with the keys that changed,
 * who made it and how (baseline, publish, restore or reset). Rows are never
 * edited or deleted, so every revision stays restorable.
 */
final class BrandingRevisionRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string,mixed> $document
     * @param list<string> $changedKeys
     */
    public function create(array $document, array $changedKeys, string $action, ?int $actorId, ?int $restoredFrom = null): int
    {
        return $this->db->insert(
            'INSERT INTO branding_revisions (document_json, changed_keys_json, action, restored_from_id, actor_id, created_at)
             VALUES (:document, :changed, :action, :restored_from, :actor, UTC_TIMESTAMP())',
            [
                'document' => json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'changed' => json_encode(array_values($changedKeys), JSON_THROW_ON_ERROR),
                'action' => $action,
                'restored_from' => $restoredFrom,
                'actor' => $actorId,
            ],
        );
    }

    /** @return array<string,mixed>|null with decoded `document` and `changed_keys` */
    public function find(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT r.*, u.username AS actor_username, u.display_name AS actor_display_name
             FROM branding_revisions r LEFT JOIN users u ON u.id = r.actor_id
             WHERE r.id = ?',
            [$id],
        );
        return $row === null ? null : $this->decode($row);
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM branding_revisions');
    }

    /** @return list<array<string,mixed>> newest first */
    public function page(int $limit, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $rows = $this->db->fetchAll(
            'SELECT r.*, u.username AS actor_username, u.display_name AS actor_display_name
             FROM branding_revisions r LEFT JOIN users u ON u.id = r.actor_id
             ORDER BY r.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
        );
        return array_map(fn (array $row): array => $this->decode($row), $rows);
    }

    /**
     * Revisions published after $revisionId, oldest first — what a stale
     * editing session would overwrite.
     *
     * @return list<array<string,mixed>>
     */
    public function after(int $revisionId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.*, u.username AS actor_username, u.display_name AS actor_display_name
             FROM branding_revisions r LEFT JOIN users u ON u.id = r.actor_id
             WHERE r.id > ? ORDER BY r.id ASC LIMIT 50',
            [$revisionId],
        );
        return array_map(fn (array $row): array => $this->decode($row), $rows);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decode(array $row): array
    {
        $document = json_decode((string) $row['document_json'], true);
        $changed = json_decode((string) $row['changed_keys_json'], true);
        $row['document'] = is_array($document) ? $document : [];
        $row['changed_keys'] = is_array($changed) ? array_values(array_filter($changed, 'is_string')) : [];
        $row['id'] = (int) $row['id'];
        $row['actor_id'] = $row['actor_id'] !== null ? (int) $row['actor_id'] : null;
        $row['restored_from_id'] = $row['restored_from_id'] !== null ? (int) $row['restored_from_id'] : null;
        unset($row['document_json'], $row['changed_keys_json']);
        return $row;
    }
}
