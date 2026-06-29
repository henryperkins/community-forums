<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Attachment lifecycle persistence (P3-04). Rows are created 'temp' on upload and
 * flipped to 'finalized' (bound to a post/DM + visibility) only once the parent
 * content commits, so media can never become visible before its parent and
 * authorization context exist (PHASE_3_PLAN §8.5).
 */
final class AttachmentRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data @return int new attachment id */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO attachments (user_id, purpose, kind, status, storage_key, sha256, mime, size_bytes, width, height, alt, visibility, scan_status, download_name, created_at)
             VALUES (:user_id, :purpose, :kind, :status, :storage_key, :sha256, :mime, :size, :w, :h, :alt, :visibility, :scan_status, :download_name, UTC_TIMESTAMP())',
            [
                'user_id' => (int) $data['user_id'],
                'purpose' => (string) ($data['purpose'] ?? 'post'),
                'kind' => (string) ($data['kind'] ?? 'image'),
                'status' => (string) ($data['status'] ?? 'temp'),
                'storage_key' => (string) $data['storage_key'],
                'sha256' => (string) $data['sha256'],
                'mime' => (string) $data['mime'],
                'size' => (int) $data['size_bytes'],
                'w' => $data['width'] ?? null,
                'h' => $data['height'] ?? null,
                'alt' => $data['alt'] ?? null,
                'visibility' => (string) ($data['visibility'] ?? 'public'),
                'scan_status' => (string) ($data['scan_status'] ?? 'clean'),
                'download_name' => $data['download_name'] ?? null,
            ],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM attachments WHERE id = ?', [$id]);
    }

    /**
     * Bind a set of the owner's temp uploads to a post + visibility. Only the
     * owner's still-temp rows are affected (idempotent finalize). Returns count.
     *
     * @param list<int> $ids
     */
    public function finalizeForPost(int $ownerId, int $postId, array $ids, string $visibility): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }
        $in = implode(',', $ids);
        return $this->db->run(
            "UPDATE attachments
                SET status = 'finalized', post_id = :pid, visibility = :vis, finalized_at = UTC_TIMESTAMP()
              WHERE id IN ($in) AND user_id = :uid AND status = 'temp'",
            ['pid' => $postId, 'vis' => $visibility, 'uid' => $ownerId],
        )->rowCount();
    }

    /** @param list<int> $ids */
    public function finalizeForDm(int $ownerId, int $dmMessageId, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }
        $in = implode(',', $ids);
        return $this->db->run(
            "UPDATE attachments
                SET status = 'finalized', dm_message_id = :mid, purpose = 'dm', visibility = 'private', finalized_at = UTC_TIMESTAMP()
              WHERE id IN ($in) AND user_id = :uid AND status = 'temp'",
            ['mid' => $dmMessageId, 'uid' => $ownerId],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> finalized images for a post */
    public function listForPost(int $postId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM attachments WHERE post_id = ? AND status = 'finalized' ORDER BY id ASC",
            [$postId],
        );
    }

    /**
     * Finalize a standalone (parentless) public asset such as a brand logo or
     * favicon (P3-07). Only the owner's own temp row is affected.
     */
    public function finalizeBrandAsset(int $id, int $ownerId): bool
    {
        return $this->db->run(
            "UPDATE attachments
                SET status = 'finalized', visibility = 'public', finalized_at = UTC_TIMESTAMP()
              WHERE id = ? AND user_id = ? AND status = 'temp'",
            [$id, $ownerId],
        )->rowCount() > 0;
    }

    public function setAlt(int $id, int $ownerId, string $alt): void
    {
        $this->db->run(
            'UPDATE attachments SET alt = ? WHERE id = ? AND user_id = ?',
            [$alt !== '' ? mb_substr($alt, 0, 255) : null, $id, $ownerId],
        );
    }

    public function markDeleted(int $id): void
    {
        $this->db->run("UPDATE attachments SET status = 'deleted', deleted_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
    }

    public function markScanClean(int $id): void
    {
        $this->db->run(
            "UPDATE attachments
             SET scan_status = 'clean', scan_checked_at = UTC_TIMESTAMP(), quarantined_at = NULL, quarantine_reason = NULL
             WHERE id = ? AND status <> 'deleted'",
            [$id],
        );
    }

    public function quarantine(int $id, string $reason): void
    {
        $this->db->run(
            "UPDATE attachments
             SET scan_status = 'quarantined', scan_checked_at = UTC_TIMESTAMP(), quarantined_at = UTC_TIMESTAMP(), quarantine_reason = ?
             WHERE id = ? AND status <> 'deleted'",
            [mb_substr(trim($reason), 0, 255), $id],
        );
    }

    public function failPendingOlderThan(string $cutoff, int $limit = 500): int
    {
        $limit = max(1, $limit);
        return $this->db->run(
            "UPDATE attachments
             SET scan_status = 'failed', scan_checked_at = UTC_TIMESTAMP(), quarantine_reason = 'scan_timeout'
             WHERE kind = 'file' AND scan_status = 'pending' AND created_at < ?
             ORDER BY id ASC LIMIT " . $limit,
            [$cutoff],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingScans(int $limit = 500): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT * FROM attachments
             WHERE kind = 'file' AND status <> 'deleted' AND scan_status = 'pending'
             ORDER BY created_at ASC, id ASC LIMIT " . $limit,
        );
    }

    /**
     * Temp uploads abandoned before $cutoff (UTC datetime) — candidates for the
     * orphan sweep.
     *
     * @return array<int,array<string,mixed>>
     */
    public function tempOlderThan(string $cutoff, int $limit = 500): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT * FROM attachments WHERE status = 'temp' AND created_at < ? ORDER BY id ASC LIMIT " . $limit,
            [$cutoff],
        );
    }

    /**
     * Finalized post attachments whose parent post was soft-deleted BEFORE
     * $cutoff (UTC datetime) — past the restore/appeal grace window, so the files
     * can be reclaimed. Posts deleted before this column existed (deleted_at
     * NULL) are conservatively retained.
     *
     * @return array<int,array<string,mixed>>
     */
    public function finalizedWithDeletedPost(string $cutoff, int $limit = 500): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT a.* FROM attachments a
             JOIN posts p ON p.id = a.post_id
             WHERE a.status = 'finalized' AND a.post_id IS NOT NULL
               AND p.is_deleted = 1 AND p.deleted_at IS NOT NULL AND p.deleted_at < ?
             ORDER BY a.id ASC LIMIT " . $limit,
            [$cutoff],
        );
    }
}
