<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Content reports (P2-08). A report targets a post or a DM message. "One open
 * report per (reporter, target)" dedupe is enforced here in app logic. The queue
 * is board-scoped: a moderator sees only post-reports for boards they moderate;
 * an admin sees all (incl. DM reports).
 */
final class ReportRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return int new id, or 0 when the reporter already has an open report for this post */
    public function createPostReport(int $reporterId, int $postId, ?string $reasonCode, string $reason, bool $notifyReporter): int
    {
        $existing = $this->db->fetchValue(
            "SELECT 1 FROM reports WHERE reporter_id = ? AND post_id = ? AND status IN ('open','triaged') LIMIT 1",
            [$reporterId, $postId],
        );
        if ($existing !== false) {
            return 0;
        }
        return $this->db->insert(
            'INSERT INTO reports (reporter_id, post_id, reason_code, reason, status, notify_reporter, created_at)
             VALUES (?, ?, ?, ?, \'open\', ?, UTC_TIMESTAMP())',
            [$reporterId, $postId, $reasonCode ?: null, $reason !== '' ? $reason : null, $notifyReporter ? 1 : 0],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM reports WHERE id = ?', [$id]);
    }

    /** The board a post-report belongs to (for scope checks); null for DM reports. */
    public function boardIdFor(array $report): ?int
    {
        if ($report['post_id'] === null) {
            return null;
        }
        $id = $this->db->fetchValue(
            'SELECT t.board_id FROM posts p JOIN threads t ON t.id = p.thread_id WHERE p.id = ?',
            [(int) $report['post_id']],
        );
        return $id === false || $id === null ? null : (int) $id;
    }

    /**
     * Open/triaged reports visible to a moderator. Admins see all (post + DM);
     * board moderators see post-reports for their boards only.
     *
     * @param list<int> $boardIds
     * @return array<int,array<string,mixed>>
     */
    public function queue(bool $isAdmin, array $boardIds, int $limit = 50): array
    {
        $limit = max(1, $limit);
        if ($isAdmin) {
            return $this->db->fetchAll(
                "SELECT r.*, rep.username AS reporter_username,
                        p.body AS post_body, p.thread_id, t.slug AS thread_slug, t.title AS thread_title, b.slug AS board_slug,
                        dm.body AS dm_body, dm.body_html AS dm_body_html, dm.conversation_id AS dm_conversation_id,
                        dm_sender.username AS dm_sender_username, dm_sender.display_name AS dm_sender_display_name,
                        c.kind AS dm_conversation_kind, c.title AS dm_conversation_title
                 FROM reports r
                 JOIN users rep ON rep.id = r.reporter_id
                 LEFT JOIN posts p ON p.id = r.post_id
                 LEFT JOIN threads t ON t.id = p.thread_id
                 LEFT JOIN boards b ON b.id = t.board_id
                 LEFT JOIN dm_messages dm ON dm.id = r.dm_message_id
                 LEFT JOIN users dm_sender ON dm_sender.id = dm.user_id
                 LEFT JOIN conversations c ON c.id = dm.conversation_id
                 WHERE r.status IN ('open','triaged')
                 ORDER BY r.created_at ASC LIMIT " . $limit,
            );
        }
        if ($boardIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($boardIds), '?'));
        return $this->db->fetchAll(
            "SELECT r.*, rep.username AS reporter_username,
                    p.body AS post_body, p.thread_id, t.slug AS thread_slug, t.title AS thread_title, b.slug AS board_slug
             FROM reports r
             JOIN users rep ON rep.id = r.reporter_id
             JOIN posts p ON p.id = r.post_id
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE r.status IN ('open','triaged') AND t.board_id IN ($place)
             ORDER BY r.created_at ASC LIMIT " . $limit,
            $boardIds,
        );
    }

    public function claim(int $id, int $modId): void
    {
        $this->db->run(
            "UPDATE reports SET status = 'triaged', assigned_to = ? WHERE id = ? AND status = 'open'",
            [$modId, $id],
        );
    }

    public function setStatus(int $id, string $status, int $modId): void
    {
        $this->db->run(
            'UPDATE reports SET status = ?, handled_by = ?, resolved_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $modId, $id],
        );
    }
}
