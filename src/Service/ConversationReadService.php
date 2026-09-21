<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Security\WriteGate;
use App\Support\Markdown;

/** Participant-only incremental reads; no staff bypass. */
final class ConversationReadService
{
    public function __construct(
        private Database $db,
        private ConversationRepository $conversations,
        private DmMessageRepository $messages,
        private BlockRepository $blocks,
        private FeatureFlags $flags,
        private PresenceService $presence,
        private WriteGate $writeGate,
        private Markdown $markdown,
    ) {
    }

    public function presence(User $viewer, array $participants): array
    {
        $blocked = $this->flags->enabled('presence')
            ? $this->blocks->blockedMap($viewer->id(), array_column($participants, 'user_id')) : [];
        $states = [];
        foreach ($participants as $participant) {
            $id = (int) $participant['user_id'];
            $participant['id'] = $id;
            $states[$id] = empty($participant['left_at'])
                ? $this->presence->state($viewer, $participant, isset($blocked[$id]))
                : PresenceService::OFFLINE;
        }
        return $states;
    }

    public function poll(User $viewer, int $conversationId, int $after): array
    {
        if (!$this->flags->enabled('dms')) { throw new NotFoundException(); }
        $conversation = $this->conversations->find($conversationId);
        $membership = $this->conversations->membership($conversationId, $viewer->id());
        $group = ($conversation['kind'] ?? 'direct') === 'group';
        if ($conversation === null || $membership === null || $membership['left_at'] !== null
            || ($group && !$this->flags->enabled('group_dms'))) {
            throw new NotFoundException('Conversation not found.');
        }
        $this->writeGate->assertCanWrite($viewer);
        $messages = $this->db->transaction(function () use ($viewer, $conversationId, $after): array {
            $rows = $this->messages->afterForUser($conversationId, $viewer->id(), $after);
            // A supplied cursor is never a read watermark; acknowledge only returned rows.
            if ($rows !== []) {
                $this->conversations->markRead($conversationId, $viewer->id(), (int) end($rows)['id']);
            }
            return $rows;
        });
        foreach ($messages as &$message) {
            if (trim((string) $message['body_html']) === '') {
                $message['body_html'] = $this->markdown->render($message['body'], ['link_mentions' => true]);
            }
        }
        unset($message);
        $participants = $this->conversations->participants($conversationId);
        return [
            'messages' => $messages, 'participants' => $participants, 'is_group' => $group,
            'last_id' => $messages !== [] ? (int) end($messages)['id'] : max(0, $after),
            'other_last_read_message_id' => $group ? null : $this->conversations->otherLastReadMessageId($conversationId, $viewer->id()),
            'presence' => $this->presence($viewer, $participants),
            'dm_unread' => $this->conversations->unreadConversationCount($viewer->id()),
            'has_more' => count($messages) === 50,
        ];
    }
}
