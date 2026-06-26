<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * One-to-one DM conversations (P2-07). The participant schema can hold more than
 * two, but Phase 2 is strictly one-to-one (DECISIONS, locked decisions).
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
            'SELECT c.id FROM conversations c
             WHERE EXISTS (SELECT 1 FROM conversation_participants p WHERE p.conversation_id = c.id AND p.user_id = ?)
               AND EXISTS (SELECT 1 FROM conversation_participants p WHERE p.conversation_id = c.id AND p.user_id = ?)
               AND (SELECT COUNT(*) FROM conversation_participants p WHERE p.conversation_id = c.id) = 2
             LIMIT 1',
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
            $id = $this->db->insert('INSERT INTO conversations (created_at) VALUES (UTC_TIMESTAMP())');
            $this->db->run('INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)', [$id, $a]);
            $this->db->run('INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)', [$id, $b]);
            return $id;
        });
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1',
            [$conversationId, $userId],
        ) !== false;
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
     * and an unread flag. Newest activity first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT c.id AS conversation_id, c.last_message_at,
                    ou.id AS other_id, ou.username AS other_username, ou.display_name AS other_display_name,
                    lm.id AS last_message_id, lm.body AS last_body, lm.user_id AS last_sender_id,
                    (lm.id IS NOT NULL AND lm.user_id <> me.user_id
                        AND (me.last_read_message_id IS NULL OR lm.id > me.last_read_message_id)) AS is_unread
             FROM conversation_participants me
             JOIN conversations c ON c.id = me.conversation_id
             JOIN conversation_participants op ON op.conversation_id = c.id AND op.user_id <> me.user_id
             JOIN users ou ON ou.id = op.user_id
             LEFT JOIN dm_messages lm ON lm.id = (SELECT MAX(id) FROM dm_messages WHERE conversation_id = c.id)
             WHERE me.user_id = ?
             ORDER BY c.last_message_at DESC, c.id DESC',
            [$userId],
        );
    }

    /** Conversations with an unread message the user did not send (DM bell/badge). */
    public function unreadConversationCount(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM conversation_participants me
             JOIN (SELECT conversation_id, MAX(id) AS max_id FROM dm_messages GROUP BY conversation_id) lm
               ON lm.conversation_id = me.conversation_id
             JOIN dm_messages m ON m.id = lm.max_id
             WHERE me.user_id = ? AND m.user_id <> ?
               AND (me.last_read_message_id IS NULL OR lm.max_id > me.last_read_message_id)',
            [$userId, $userId],
        );
    }
}
