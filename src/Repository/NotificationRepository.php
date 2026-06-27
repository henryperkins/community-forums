<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * In-app notifications (P2-03). Rows store only minimal identifiers; the read
 * gate is re-applied at render/click time so a notification can never leak
 * content the recipient has since lost access to (PHASE_2_PLAN §11).
 */
final class NotificationRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Insert a notification. Returns the new id, or 0 if a same-kind row already
     * exists for this (recipient, actor, post/thread) — a cheap dedupe so a
     * retry or a re-reaction does not stack identical bell entries.
     *
     * @param array{user_id:int,type:string,actor_id?:?int,thread_id?:?int,post_id?:?int,conversation_id?:?int} $n
     */
    public function create(array $n): int
    {
        return $this->db->insert(
            'INSERT INTO notifications (user_id, type, actor_id, thread_id, post_id, conversation_id, is_read, created_at)
             VALUES (:uid, :type, :actor, :tid, :pid, :cid, 0, UTC_TIMESTAMP())',
            [
                'uid' => $n['user_id'],
                'type' => $n['type'],
                'actor' => $n['actor_id'] ?? null,
                'tid' => $n['thread_id'] ?? null,
                'pid' => $n['post_id'] ?? null,
                'cid' => $n['conversation_id'] ?? null,
            ],
        );
    }

    /**
     * Idempotent reaction notification: at most one unread 'reaction' row per
     * (recipient, actor, post) so repeated react/unreact toggles don't spam.
     */
    public function createReactionOnce(int $recipientId, int $actorId, int $threadId, int $postId): int
    {
        $exists = $this->db->fetchValue(
            "SELECT 1 FROM notifications
             WHERE user_id = ? AND type = 'reaction' AND actor_id = ? AND post_id = ? AND is_read = 0 LIMIT 1",
            [$recipientId, $actorId, $postId],
        );
        if ($exists !== false) {
            return 0;
        }
        return $this->create([
            'user_id' => $recipientId, 'type' => 'reaction',
            'actor_id' => $actorId, 'thread_id' => $threadId, 'post_id' => $postId,
        ]);
    }

    /**
     * Idempotent follow notification: at most one unread 'follow' row per
     * (recipient, actor) so unfollow/refollow churn doesn't spam the bell.
     */
    public function createFollowOnce(int $recipientId, int $actorId): int
    {
        $exists = $this->db->fetchValue(
            "SELECT 1 FROM notifications
             WHERE user_id = ? AND type = 'follow' AND actor_id = ? AND is_read = 0 LIMIT 1",
            [$recipientId, $actorId],
        );
        if ($exists !== false) {
            return 0;
        }
        return $this->create([
            'user_id' => $recipientId, 'type' => 'follow', 'actor_id' => $actorId,
        ]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId],
        );
    }

    /** @return array<int,array<string,mixed>> the most recent notifications with actor + thread context */
    public function recent(int $userId, int $limit = 20): array
    {
        $limit = max(1, $limit);
        $rows = $this->db->fetchAll(
            'SELECT n.*, a.username AS actor_username, a.display_name AS actor_display_name,
                    t.title AS thread_title, t.slug AS thread_slug,
                    COALESCE(pp.is_anonymous, 0) AS post_is_anonymous
             FROM notifications n
             LEFT JOIN users a ON a.id = n.actor_id
             LEFT JOIN threads t ON t.id = n.thread_id
             LEFT JOIN posts pp ON pp.id = n.post_id
             WHERE n.user_id = ?
             ORDER BY n.id DESC
             LIMIT ' . $limit,
            [$userId],
        );

        // Mask the actor of content posted anonymously (reply / new thread /
        // mention). actor_id is retained for dedupe + deep links; only the
        // displayed identity collapses to "Anonymous" so the bell and list
        // (and any consumer of recent()) cannot leak the real author.
        foreach ($rows as &$r) {
            if ((int) ($r['post_is_anonymous'] ?? 0) === 1
                && in_array($r['type'], ['reply', 'new_thread', 'mention'], true)) {
                $r['actor_username'] = null;
                $r['actor_display_name'] = 'Anonymous';
            }
        }
        unset($r);

        return $rows;
    }

    public function markRead(int $userId, int $id): void
    {
        $this->db->run(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [$id, $userId],
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function clear(int $userId): void
    {
        $this->db->run('DELETE FROM notifications WHERE user_id = ?', [$userId]);
    }
}
