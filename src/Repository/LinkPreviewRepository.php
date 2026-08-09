<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Single-table wrapper over `link_previews`, plus the source-context join the
 * operator console needs to name the post a row belongs to (the same shape
 * PostRepository::findWithContext uses).
 *
 * Status vocabulary:
 *   queued   — discovered in a body, not fetched yet
 *   fetched  — metadata stored; the only status that renders a card
 *   blocked  — refused by the allowlist / EgressGuard (never retried silently)
 *   failed   — transport or parse error
 *   purged   — operator wiped the metadata; re-queued the next time the body is saved
 *   removed  — the author (or an operator, on the record) suppressed the card;
 *              sticky across edits, only a restore brings it back
 */
final class LinkPreviewRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Idempotent queue insert. Returns true when the row was created or a
     * previously purged row was revived — `removed` is deliberately sticky so
     * editing a post cannot resurrect a card its author took down.
     */
    public function queue(string $sourceType, int $sourceId, string $url, string $urlHash): bool
    {
        return $this->db->run(
            "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, created_at)
             VALUES (?, ?, ?, ?, 'queued', UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE status = IF(status = 'purged', 'queued', status), updated_at = UTC_TIMESTAMP()",
            [$sourceType, $sourceId, $url, $urlHash],
        )->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM link_previews WHERE id = ?', [$id]);
    }

    /**
     * Every renderable row for a set of sources, oldest first.
     *
     * @param list<int> $sourceIds
     * @return array<int,array<string,mixed>>
     */
    public function fetchedForSources(string $sourceType, array $sourceIds): array
    {
        if ($sourceIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));

        return $this->db->fetchAll(
            "SELECT * FROM link_previews
             WHERE source_type = ? AND source_id IN ($placeholders) AND status = 'fetched'
             ORDER BY id ASC",
            array_merge([$sourceType], $sourceIds),
        );
    }

    /**
     * Rows an author may act on for a set of sources: the rendered ones plus the
     * ones they already removed, so the thread can offer "restore".
     *
     * @param list<int> $sourceIds
     * @return array<int,array<string,mixed>>
     */
    public function authorVisibleForSources(string $sourceType, array $sourceIds): array
    {
        if ($sourceIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));

        return $this->db->fetchAll(
            "SELECT * FROM link_previews
             WHERE source_type = ? AND source_id IN ($placeholders) AND status IN ('fetched','removed')
             ORDER BY id ASC",
            array_merge([$sourceType], $sourceIds),
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function queued(int $limit): array
    {
        // EMULATE_PREPARES=false: LIMIT is clamped and concatenated, never bound.
        $limit = max(1, min(100, $limit));

        return $this->db->fetchAll(
            "SELECT * FROM link_previews WHERE status = 'queued' ORDER BY created_at ASC, id ASC LIMIT " . $limit,
        );
    }

    public function countQueued(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM link_previews WHERE status = 'queued'");
    }

    /** @return array<string,int> status => count, every declared status present */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(['queued', 'fetched', 'blocked', 'failed', 'purged', 'removed'], 0);
        foreach ($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM link_previews GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Console listing: newest first, optionally narrowed to one status, joined
     * to the post/thread/board that owns the row so an operator can see where a
     * blocked URL came from without a second query per row.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listRecent(?string $status, int $limit): array
    {
        $limit = max(1, min(200, $limit));
        $where = '';
        $params = [];
        if ($status !== null && $status !== '') {
            $where = 'WHERE lp.status = ?';
            $params[] = $status;
        }

        return $this->db->fetchAll(
            "SELECT lp.*,
                    t.id AS thread_id, t.slug AS thread_slug, t.title AS thread_title,
                    b.id AS board_id, b.name AS board_name, b.slug AS board_slug,
                    b.visibility AS board_visibility, b.link_previews_enabled AS board_previews_enabled
             FROM link_previews lp
             LEFT JOIN posts p ON lp.source_type = 'post' AND p.id = lp.source_id
             LEFT JOIN threads t ON t.id = p.thread_id
             LEFT JOIN boards b ON b.id = t.board_id
             $where
             ORDER BY lp.id DESC
             LIMIT " . $limit,
            $params,
        );
    }

    public function markQueued(int $id): void
    {
        $this->db->run(
            "UPDATE link_previews
             SET status = 'queued', final_url = NULL, http_status = NULL, error = NULL,
                 title = NULL, description = NULL, image_url = NULL, site_name = NULL,
                 fetched_at = NULL, purged_at = NULL, removed_by = NULL, removed_at = NULL,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [$id],
        );
    }

    public function markPurged(int $id): void
    {
        $this->db->run(
            "UPDATE link_previews
             SET status = 'purged', final_url = NULL, http_status = NULL, error = NULL,
                 title = NULL, description = NULL, image_url = NULL, site_name = NULL,
                 metadata = NULL, purged_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [$id],
        );
    }

    /**
     * Author (or operator) suppression. The metadata is wiped exactly as a purge
     * wipes it — a removed card must not leave a fetched title behind — but the
     * status keeps the row out of the re-queue path for good.
     */
    public function markRemoved(int $id, int $actorId): void
    {
        $this->db->run(
            "UPDATE link_previews
             SET status = 'removed', final_url = NULL, http_status = NULL, error = NULL,
                 title = NULL, description = NULL, image_url = NULL, site_name = NULL,
                 metadata = NULL, fetched_at = NULL,
                 removed_by = ?, removed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [$actorId, $id],
        );
    }

    public function markBlocked(int $id, string $error): void
    {
        $this->markError($id, 'blocked', $error);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->markError($id, 'failed', $error);
    }

    /** @param array{title:?string,description:?string,image_url:?string,site_name:?string} $meta */
    public function markFetched(int $id, string $finalUrl, int $httpStatus, array $meta, ?string $metadataJson): void
    {
        $this->db->run(
            "UPDATE link_previews
             SET status = 'fetched', final_url = ?, http_status = ?, title = ?, description = ?,
                 image_url = ?, site_name = ?, metadata = ?, error = NULL,
                 fetched_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?",
            [
                $finalUrl,
                $httpStatus,
                $meta['title'],
                $meta['description'],
                $meta['image_url'],
                $meta['site_name'],
                $metadataJson,
                $id,
            ],
        );
    }

    private function markError(int $id, string $status, string $error): void
    {
        $this->db->run(
            'UPDATE link_previews SET status = ?, error = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, mb_substr($error, 0, 255), $id],
        );
    }
}
