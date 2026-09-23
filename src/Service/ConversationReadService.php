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

    /**
     * One tick of the open conversation's short poll. Beyond the kernel's
     * session reads it costs the conversation row, one participants read (the
     * gate, the rank pill, presence and the receipt watermark all come from
     * it), the after-id read, the presence block map and the nav count. A tick
     * that finds nothing new writes nothing: no transaction, no watermark.
     */
    public function poll(User $viewer, int $conversationId, int $after): array
    {
        if (!$this->flags->enabled('dms')) { throw new NotFoundException(); }
        $conversation = $this->conversations->find($conversationId);
        $participants = $conversation === null ? [] : $this->conversations->participants($conversationId);
        $membership = null;
        foreach ($participants as $participant) {
            if ((int) $participant['user_id'] === $viewer->id()) { $membership = $participant; break; }
        }
        $group = ($conversation['kind'] ?? 'direct') === 'group';
        if ($conversation === null || $membership === null || $membership['left_at'] !== null
            || ($group && !$this->flags->enabled('group_dms'))) {
            throw new NotFoundException('Conversation not found.');
        }
        $this->writeGate->assertCanWrite($viewer);
        $messages = $this->messages->afterForUser($conversationId, $viewer->id(), $after);
        // A supplied cursor is never a read watermark; acknowledge only returned
        // rows. markRead is one monotonic UPDATE (GREATEST), so it needs no
        // transaction around it, and the read before it takes no lock.
        if ($messages !== []) {
            $this->conversations->markRead($conversationId, $viewer->id(), (int) end($messages)['id']);
        }
        foreach ($messages as &$message) {
            if (trim((string) $message['body_html']) === '') {
                $message['body_html'] = $this->markdown->render($message['body'], ['link_mentions' => true]);
            }
        }
        unset($message);
        return [
            'messages' => $messages, 'participants' => $participants, 'is_group' => $group,
            'last_id' => $messages !== [] ? (int) end($messages)['id'] : max(0, $after),
            'other_last_read_message_id' => $group ? null : $this->otherLastReadMessageId($participants, $viewer->id()),
            'presence' => $this->presence($viewer, $participants),
            'dm_unread' => $this->conversations->unreadConversationCount($viewer->id()),
            'has_more' => count($messages) === 50,
        ];
    }

    /**
     * ConversationRepository::otherLastReadMessageId() answered from rows the
     * poll already holds: the lowest-id active counterpart's watermark. The
     * viewer's own row may predate this tick's markRead; it is never read here.
     *
     * @param list<array<string,mixed>> $participants
     */
    private function otherLastReadMessageId(array $participants, int $viewerId): ?int
    {
        $other = null;
        foreach ($participants as $participant) {
            $id = (int) $participant['user_id'];
            if ($id !== $viewerId && $participant['left_at'] === null && ($other === null || $id < (int) $other['user_id'])) {
                $other = $participant;
            }
        }
        $value = $other['last_read_message_id'] ?? null;
        return $value === null ? null : (int) $value;
    }
}
