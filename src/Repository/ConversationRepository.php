<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * DM conversations. Phase 4 extends the Phase 2 one-to-one model with bounded
 * group conversations and membership intervals.
 */
final class ConversationRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM conversations WHERE id = ?', [$id]);
    }

    /** The existing 1:1 conversation between two users, or null. */
    public function between(int $a, int $b): ?int
    {
        $id = $this->db->fetchValue(
             "SELECT c.id FROM conversations c
             WHERE EXISTS (SELECT 1 FROM conversation_participants p WHERE p.conversation_id = c.id AND p.user_id = ?)
               AND EXISTS (SELECT 1 FROM conversation_participants p WHERE p.conversation_id = c.id AND p.user_id = ?)
               AND c.kind = 'direct'
               AND (SELECT COUNT(*) FROM conversation_participants p WHERE p.conversation_id = c.id) = 2
             LIMIT 1",
            [$a, $b],
        );
        return $id === false || $id === null ? null : (int) $id;
    }

    /** Find or create the 1:1 conversation between two users (transaction-safe). */
    public function findOrCreateBetween(int $a, int $b): int
    {
        return $this->db->transaction(function () use ($a, $b): int {
            $existing = $this->between($a, $b);
            if ($existing !== null) {
                return $existing;
            }
            $id = $this->db->insert('INSERT INTO conversations (kind, created_by, created_at) VALUES (\'direct\', ?, UTC_TIMESTAMP())', [$a]);
            $this->db->run("INSERT INTO conversation_participants (conversation_id, user_id, role, joined_after_message_id, joined_at) VALUES (?, ?, 'member', 0, UTC_TIMESTAMP())", [$id, $a]);
            $this->db->run("INSERT INTO conversation_participants (conversation_id, user_id, role, joined_after_message_id, joined_at) VALUES (?, ?, 'member', 0, UTC_TIMESTAMP())", [$id, $b]);
            return $id;
        });
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1',
            [$conversationId, $userId],
        ) !== false;
    }

    /** Historical membership row, including left/removed intervals. @return array<string,mixed>|null */
    public function membership(int $conversationId, int $userId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM conversation_participants WHERE conversation_id = ? AND user_id = ?',
            [$conversationId, $userId],
        );
    }

    public function otherParticipant(int $conversationId, int $userId): ?int
    {
        $id = $this->db->fetchValue(
            'SELECT user_id FROM conversation_participants WHERE conversation_id = ? AND user_id <> ? LIMIT 1',
            [$conversationId, $userId],
        );
        return $id === false || $id === null ? null : (int) $id;
    }

    public function touch(int $conversationId, string $at): void
    {
        $this->db->run('UPDATE conversations SET last_message_at = ? WHERE id = ?', [$at, $conversationId]);
    }

    /** @param list<int> $participantIds */
    public function createGroup(int $ownerId, string $title, array $participantIds, int $joinedAfterMessageId = 0): int
    {
        $participantIds = array_values(array_unique(array_map('intval', array_merge([$ownerId], $participantIds))));
        return $this->db->transaction(function () use ($ownerId, $title, $participantIds, $joinedAfterMessageId): int {
            $id = $this->db->insert(
                "INSERT INTO conversations (kind, title, owner_user_id, created_by, created_at)
                 VALUES ('group', ?, ?, ?, UTC_TIMESTAMP())",
                [$title, $ownerId, $ownerId],
            );
            foreach ($participantIds as $uid) {
                $role = $uid === $ownerId ? 'owner' : 'member';
                $this->db->run(
                    'INSERT INTO conversation_participants
                        (conversation_id, user_id, role, joined_after_message_id, joined_at)
                     VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$id, $uid, $role, $joinedAfterMessageId],
                );
            }
            $this->addEvent($id, $ownerId, 'created', $ownerId, $title);
            return $id;
        });
    }

    public function addParticipant(int $conversationId, int $actorId, int $userId, int $joinedAfterMessageId): void
    {
        $this->db->run(
            'INSERT INTO conversation_participants
                (conversation_id, user_id, role, joined_after_message_id, joined_at, left_at, removed_by)
             VALUES (?, ?, \'member\', ?, UTC_TIMESTAMP(), NULL, NULL)
             ON DUPLICATE KEY UPDATE joined_after_message_id = VALUES(joined_after_message_id),
                                     joined_at = UTC_TIMESTAMP(),
                                     left_at = NULL,
                                     removed_by = NULL,
                                     notification_mode = \'normal\'',
            [$conversationId, $userId, $joinedAfterMessageId],
        );
        $this->addEvent($conversationId, $actorId, 'member_added', $userId, null);
    }

    public function removeParticipant(int $conversationId, int $actorId, int $userId): void
    {
        $this->db->run(
            'UPDATE conversation_participants
             SET left_at = UTC_TIMESTAMP(), removed_by = ?
             WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL',
            [$actorId, $conversationId, $userId],
        );
        $event = $actorId === $userId ? 'member_left' : 'member_removed';
        $this->addEvent($conversationId, $actorId, $event, $userId, null);
    }

    public function renameGroup(int $conversationId, int $actorId, string $title): void
    {
        $this->db->run('UPDATE conversations SET title = ? WHERE id = ? AND kind = \'group\'', [$title, $conversationId]);
        $this->addEvent($conversationId, $actorId, 'renamed', null, $title);
    }

    public function transferOwner(int $conversationId, int $actorId, int $newOwnerId): void
    {
        $this->db->transaction(function () use ($conversationId, $actorId, $newOwnerId): void {
            $this->db->run('UPDATE conversation_participants SET role = \'member\' WHERE conversation_id = ? AND role = \'owner\'', [$conversationId]);
            $updated = $this->db->run(
                'UPDATE conversation_participants SET role = \'owner\' WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL',
                [$conversationId, $newOwnerId],
            )->rowCount();
            if ($updated !== 1) {
                throw new \RuntimeException('New owner must be an active participant.');
            }
            $this->db->run('UPDATE conversations SET owner_user_id = ? WHERE id = ?', [$newOwnerId, $conversationId]);
            $this->addEvent($conversationId, $actorId, 'owner_transferred', $newOwnerId, null);
        });
    }

    public function setMute(int $conversationId, int $userId, bool $muted): void
    {
        $this->db->run(
            'UPDATE conversation_participants SET notification_mode = ? WHERE conversation_id = ? AND user_id = ?',
            [$muted ? 'muted' : 'normal', $conversationId, $userId],
        );
    }

    public function addEvent(int $conversationId, ?int $actorId, string $type, ?int $subjectUserId, ?string $body): void
    {
        $this->db->run(
            'INSERT INTO conversation_events (conversation_id, actor_id, event_type, subject_user_id, body, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$conversationId, $actorId, $type, $subjectUserId, $body !== null ? mb_substr($body, 0, 255) : null],
        );
    }

    /** @return list<int> */
    public function activeParticipantIds(int $conversationId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id FROM conversation_participants WHERE conversation_id = ? AND left_at IS NULL ORDER BY user_id ASC',
            [$conversationId],
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /** @return list<int> active participants whose conversation notifications are not muted */
    public function notificationParticipantIds(int $conversationId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT user_id FROM conversation_participants
             WHERE conversation_id = ? AND left_at IS NULL AND notification_mode = 'normal'
             ORDER BY user_id ASC",
            [$conversationId],
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    public function activeCount(int $conversationId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND left_at IS NULL',
            [$conversationId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function participants(int $conversationId): array
    {
        return $this->db->fetchAll(
            'SELECT cp.*, u.username, u.display_name
             FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = ?
             ORDER BY cp.left_at IS NULL DESC, cp.role = \'owner\' DESC, u.username ASC',
            [$conversationId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $conversationId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            'SELECT e.*, actor.username AS actor_username, subject.username AS subject_username
             FROM conversation_events e
             LEFT JOIN users actor ON actor.id = e.actor_id
             LEFT JOIN users subject ON subject.id = e.subject_user_id
             WHERE e.conversation_id = ?
             ORDER BY e.id DESC
             LIMIT ' . $limit,
            [$conversationId],
        );
    }

    /** Advance a participant's read marker (never regresses). */
    public function markRead(int $conversationId, int $userId, int $lastMessageId): void
    {
        $this->db->run(
            'UPDATE conversation_participants
             SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
             WHERE conversation_id = ? AND user_id = ?',
            [$lastMessageId, $conversationId, $userId],
        );
    }

    /**
     * The user's conversations with the other participant, a last-message preview,
     * and an unread flag. Newest activity first. An optional search term narrows
     * to conversations whose title, latest body, or other participants' names
     * contain it (LIKE wildcards in the term are matched literally).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForUser(int $userId, ?string $q = null): array
    {
        $sql = 'SELECT c.id AS conversation_id, c.kind, c.title, c.last_message_at,
                    ou.id AS other_id, ou.username AS other_username, ou.display_name AS other_display_name,
                    (SELECT COUNT(*) FROM conversation_participants cp WHERE cp.conversation_id = c.id AND cp.left_at IS NULL) AS participant_count,
                    (SELECT GROUP_CONCAT(COALESCE(NULLIF(gu.display_name, \'\'), gu.username) ORDER BY gu.username SEPARATOR \', \')
                     FROM conversation_participants gp JOIN users gu ON gu.id = gp.user_id
                     WHERE gp.conversation_id = c.id AND gp.left_at IS NULL AND gp.user_id <> me.user_id) AS participant_names,
                    lm.id AS last_message_id, lm.body AS last_body, lm.user_id AS last_sender_id,
                    (lm.id IS NOT NULL AND lm.user_id <> me.user_id
                        AND (me.last_read_message_id IS NULL OR lm.id > me.last_read_message_id)) AS is_unread
             FROM conversation_participants me
             JOIN conversations c ON c.id = me.conversation_id
             LEFT JOIN conversation_participants op ON op.conversation_id = c.id AND op.user_id <> me.user_id AND op.left_at IS NULL AND c.kind = \'direct\'
             LEFT JOIN users ou ON ou.id = op.user_id
             LEFT JOIN dm_messages lm ON lm.id = (SELECT MAX(id) FROM dm_messages WHERE conversation_id = c.id)
             WHERE me.user_id = ? AND me.left_at IS NULL';
        $params = [$userId];
        if ($q !== null && $q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $sql .= ' AND (COALESCE(c.title, \'\') LIKE ? OR COALESCE(lm.body, \'\') LIKE ?
                 OR EXISTS (SELECT 1 FROM conversation_participants sp JOIN users su ON su.id = sp.user_id
                            WHERE sp.conversation_id = c.id AND sp.left_at IS NULL AND sp.user_id <> me.user_id
                              AND (su.username LIKE ? OR COALESCE(su.display_name, \'\') LIKE ?)))';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY c.last_message_at DESC, c.id DESC';
        return $this->db->fetchAll($sql, $params);
    }

    /** Conversations with an unread message the user did not send (DM bell/badge). */
    public function unreadConversationCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(DISTINCT me.conversation_id)
             FROM conversation_participants me
             JOIN dm_messages m ON m.conversation_id = me.conversation_id
                AND m.id > me.joined_after_message_id
             WHERE me.user_id = ? AND me.left_at IS NULL AND me.notification_mode = 'normal' AND m.user_id <> ?
               AND (me.last_read_message_id IS NULL OR m.id > me.last_read_message_id)",
            [$userId, $userId],
        );
    }
}
