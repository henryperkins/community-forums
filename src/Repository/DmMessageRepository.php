<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Messages within a DM conversation (P2-07). */
final class DmMessageRepository
{
    public function __construct(private Database $db)
    {
    }

    public function create(int $conversationId, int $userId, string $body, string $bodyHtml): int
    {
        return $this->db->insert(
            'INSERT INTO dm_messages (conversation_id, user_id, body, body_html, created_at)
             VALUES (:cid, :uid, :body, :html, UTC_TIMESTAMP())',
            ['cid' => $conversationId, 'uid' => $userId, 'body' => $body, 'html' => $bodyHtml],
        );
    }

    /** @return array<int,array<string,mixed>> oldest-first page with author handles */
    public function listByConversation(int $conversationId, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            'SELECT m.*, u.username AS author_username, u.display_name AS author_display_name
             FROM dm_messages m JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = ?
             ORDER BY m.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$conversationId],
        );
    }

    /** @return array<int,array<string,mixed>> oldest-first page filtered to one user's membership interval */
    public function listVisibleForUser(int $conversationId, int $userId, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            'SELECT m.*, u.username AS author_username, u.display_name AS author_display_name
             FROM conversation_participants cp
             JOIN dm_messages m ON m.conversation_id = cp.conversation_id
                AND m.id > cp.joined_after_message_id
                AND (cp.left_at IS NULL OR m.created_at <= cp.left_at)
             JOIN users u ON u.id = m.user_id
             WHERE cp.conversation_id = ? AND cp.user_id = ?
             ORDER BY m.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$conversationId, $userId],
        );
    }

    public function countByConversation(int $conversationId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM dm_messages WHERE conversation_id = ?', [$conversationId]);
    }

    public function countVisibleForUser(int $conversationId, int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*)
             FROM conversation_participants cp
             JOIN dm_messages m ON m.conversation_id = cp.conversation_id
                AND m.id > cp.joined_after_message_id
                AND (cp.left_at IS NULL OR m.created_at <= cp.left_at)
             WHERE cp.conversation_id = ? AND cp.user_id = ?',
            [$conversationId, $userId],
        );
    }

    /** @return array<string,mixed>|null the message with its conversation id */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM dm_messages WHERE id = ?', [$id]);
    }

    public function latestId(int $conversationId): int
    {
        return (int) $this->db->fetchValue('SELECT COALESCE(MAX(id), 0) FROM dm_messages WHERE conversation_id = ?', [$conversationId]);
    }
}
