<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AttachmentRepository;
use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Support\Markdown;

/**
 * One-to-one direct messages (P2-07). Every send runs the account-state write
 * gate, block checks (either direction), and the recipient's allow_dms setting;
 * starting a new conversation also applies a new-user throttle. DMs can never be
 * used by a suspended/banned account to bypass write restrictions.
 *
 * Reporting exposes only the single reported message to staff (the report row
 * stores dm_message_id); there is no general DM-browsing capability.
 */
final class DirectMessageService
{
    private const BODY_MAX = 5000;
    private const TITLE_MAX = 120;

    public function __construct(
        private Database $db,
        private ConversationRepository $conversations,
        private DmMessageRepository $messages,
        private UserRepository $users,
        private BlockRepository $blocks,
        private WriteGate $writeGate,
        private Markdown $markdown,
        private NotificationService $notifications,
        private Config $config,
        private ?AttachmentRepository $attachments = null,
        private ?ContentReferenceService $contentReferences = null,
    ) {
    }

    /**
     * Start (or reuse) a conversation with $recipientId and send the first message.
     *
     * @return array{conversation_id:int, message_id:int}
     */
    public function start(User $sender, int $recipientId, string $body): array
    {
        $this->writeGate->assertCanWrite($sender);
        $recipient = $this->users->find($recipientId);
        if ($recipient === null) {
            throw new NotFoundException('That person could not be found.');
        }
        $this->assertCanContact($sender, $recipient, newConversation: true);
        $body = $this->validateBody($body);

        return $this->db->transaction(function () use ($sender, $recipientId, $body): array {
            $conversationId = $this->conversations->findOrCreateBetween($sender->id(), $recipientId);
            $messageId = $this->deliver($sender, $conversationId, $body);
            return ['conversation_id' => $conversationId, 'message_id' => $messageId];
        });
    }

    /**
     * Start a bounded group conversation and send its first message.
     *
     * @param list<int> $recipientIds
     * @return array{conversation_id:int, message_id:int}
     */
    public function startGroup(User $sender, array $recipientIds, string $title, string $body): array
    {
        $this->writeGate->assertCanWrite($sender);
        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), fn (int $id): bool => $id > 0 && $id !== $sender->id())));
        $cap = max(3, (int) $this->config->get('dm.group_participant_cap', 12));
        if (count($recipientIds) + 1 > $cap) {
            throw new ValidationException(['to' => 'That group has too many participants.']);
        }
        if ($recipientIds === []) {
            throw new ValidationException(['to' => 'Add at least one other member.']);
        }
        $title = $this->validateTitle($title, $recipientIds);
        foreach ($recipientIds as $recipientId) {
            $recipient = $this->users->find($recipientId);
            if ($recipient === null) {
                throw new ValidationException(['to' => 'One of those members could not be found.']);
            }
            $this->assertCanContact($sender, $recipient, newConversation: true);
        }
        $body = $this->validateBody($body);

        return $this->db->transaction(function () use ($sender, $recipientIds, $title, $body): array {
            $conversationId = $this->conversations->createGroup($sender->id(), $title, $recipientIds, 0);
            $messageId = $this->deliver($sender, $conversationId, $body);
            return ['conversation_id' => $conversationId, 'message_id' => $messageId];
        });
    }

    /** Reply within an existing conversation the sender belongs to. */
    public function reply(User $sender, int $conversationId, string $body): int
    {
        $this->writeGate->assertCanWrite($sender);
        if (!$this->conversations->isParticipant($conversationId, $sender->id())) {
            throw new NotFoundException('Conversation not found.');
        }
        $conversation = $this->conversations->find($conversationId);
        if ($conversation === null) {
            throw new NotFoundException('Conversation not found.');
        }
        foreach ($this->conversations->activeParticipantIds($conversationId) as $participantId) {
            if ($participantId === $sender->id()) {
                continue;
            }
            $participant = $this->users->find($participantId);
            if ($participant !== null && (string) ($conversation['kind'] ?? 'direct') === 'direct') {
                // Preserve Phase 2 direct-DM semantics at send time. Groups do
                // not get silently rewritten by later block changes; members can
                // mute/leave/report instead.
                $this->assertCanContact($sender, $participant, newConversation: false);
            }
        }
        $body = $this->validateBody($body);

        return $this->db->transaction(fn (): int => $this->deliver($sender, $conversationId, $body));
    }

    public function addParticipant(User $actor, int $conversationId, int $userId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $conversation = $this->groupForOwnerAction($actor, $conversationId);
        $recipient = $this->users->find($userId);
        if ($recipient === null) {
            throw new NotFoundException('That person could not be found.');
        }
        $this->assertCanContact($actor, $recipient, newConversation: true);
        $cap = max(3, (int) $this->config->get('dm.group_participant_cap', 12));
        if ($this->conversations->activeCount($conversationId) + 1 > $cap) {
            throw new ValidationException(['member' => 'That group has too many participants.']);
        }
        $boundary = $this->messages->latestId((int) $conversation['id']);
        $this->conversations->addParticipant($conversationId, $actor->id(), $userId, $boundary);
    }

    public function removeParticipant(User $actor, int $conversationId, int $userId): void
    {
        $this->writeGate->assertCanWrite($actor);
        if ($actor->id() !== $userId) {
            $this->groupForOwnerAction($actor, $conversationId);
        } elseif (!$this->conversations->isParticipant($conversationId, $actor->id())) {
            throw new NotFoundException('Conversation not found.');
        }
        $conversation = $this->conversations->find($conversationId);
        if ($conversation === null || (string) ($conversation['kind'] ?? 'direct') !== 'group') {
            throw new NotFoundException('Conversation not found.');
        }
        if ((int) ($conversation['owner_user_id'] ?? 0) === $userId && $this->conversations->activeCount($conversationId) > 1) {
            throw new ValidationException(['member' => 'Transfer ownership before the owner leaves.']);
        }
        $this->conversations->removeParticipant($conversationId, $actor->id(), $userId);
    }

    public function rename(User $actor, int $conversationId, string $title): void
    {
        $this->writeGate->assertCanWrite($actor);
        $conversation = $this->groupForOwnerAction($actor, $conversationId);
        $ids = $this->conversations->activeParticipantIds($conversationId);
        $this->conversations->renameGroup($conversationId, $actor->id(), $this->validateTitle($title, $ids));
    }

    public function mute(User $actor, int $conversationId, bool $muted): void
    {
        $this->writeGate->assertCanWrite($actor);
        if (!$this->conversations->isParticipant($conversationId, $actor->id())) {
            throw new NotFoundException('Conversation not found.');
        }
        $this->conversations->setMute($conversationId, $actor->id(), $muted);
    }

    public function transferOwner(User $actor, int $conversationId, int $newOwnerId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $this->groupForOwnerAction($actor, $conversationId);
        if (!$this->conversations->isParticipant($conversationId, $newOwnerId)) {
            throw new ValidationException(['member' => 'Choose a current group member.']);
        }
        $this->conversations->transferOwner($conversationId, $actor->id(), $newOwnerId);
    }

    /**
     * Report a specific DM message. The reporter must be a participant; staff
     * then see only this message + local context. One open report per
     * (reporter, message).
     */
    public function reportMessage(User $reporter, int $messageId, ?string $reasonCode, string $reason): void
    {
        $message = $this->messages->find($messageId);
        if ($message === null) {
            throw new NotFoundException('Message not found.');
        }
        $membership = $this->conversations->membership((int) $message['conversation_id'], $reporter->id());
        $joinedAfter = $membership !== null ? (int) ($membership['joined_after_message_id'] ?? 0) : PHP_INT_MAX;
        $leftAt = $membership['left_at'] ?? null;
        $createdAt = strtotime((string) ($message['created_at'] ?? '') . ' UTC');
        $leftTs = $leftAt !== null ? strtotime((string) $leftAt . ' UTC') : false;
        if ($membership === null
            || (int) $message['id'] <= $joinedAfter
            || ($leftTs !== false && $createdAt !== false && $createdAt > $leftTs)) {
            // Don't reveal a conversation the reporter isn't part of.
            throw new NotFoundException('Message not found.');
        }
        $existing = $this->db->fetchValue(
            "SELECT 1 FROM reports WHERE reporter_id = ? AND dm_message_id = ? AND status IN ('open','triaged') LIMIT 1",
            [$reporter->id(), $messageId],
        );
        if ($existing !== false) {
            return; // dedupe: reuse the open report rather than spamming the queue
        }
        $reason = trim($reason);
        $this->db->run(
            'INSERT INTO reports (reporter_id, dm_message_id, reason_code, reason, status, created_at)
             VALUES (?, ?, ?, ?, \'open\', UTC_TIMESTAMP())',
            [$reporter->id(), $messageId, $reasonCode ?: null, $reason !== '' ? $reason : null],
        );
        $this->notifications->notifyDmReport($reporter->id(), (int) $message['conversation_id']);
    }

    /** Insert the message, advance conversation + read marker, notify the recipient. */
    private function deliver(User $sender, int $conversationId, string $body): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $messageId = $this->messages->create($conversationId, $sender->id(), $body, $this->markdown->render($body));
        // Bind any /media/{id} the message references to this DM (private). P3-04.
        if ($this->attachments !== null) {
            $ids = \App\Service\AttachmentService::referencedIds($body);
            if ($ids !== []) {
                $this->attachments->finalizeForDm($sender->id(), $messageId, $ids);
            }
        }
        $this->contentReferences?->capture('dm_message', $messageId, $body);
        $this->conversations->touch($conversationId, $now);
        $this->conversations->markRead($conversationId, $sender->id(), $messageId); // sender has "read" their own
        foreach ($this->conversations->notificationParticipantIds($conversationId) as $recipientId) {
            if ($recipientId !== $sender->id()) {
                $this->notifications->notifyDm($sender->id(), $recipientId, $conversationId);
            }
        }
        return $messageId;
    }

    /** @param array<string,mixed> $recipient users row */
    private function assertCanContact(User $sender, array $recipient, bool $newConversation): void
    {
        $recipientId = (int) $recipient['id'];
        if ($recipientId === $sender->id()) {
            throw new ValidationException(['to' => 'You cannot message yourself.']);
        }
        if ($this->blocks->blockedEitherWay($sender->id(), $recipientId)) {
            throw new ForbiddenException('You cannot message this person.');
        }
        $status = (string) ($recipient['status'] ?? 'active');
        $suspendedUntil = $recipient['suspended_until'] ?? null;
        $suspendedUntilTs = is_string($suspendedUntil) && $suspendedUntil !== ''
            ? strtotime($suspendedUntil . ' UTC')
            : false;
        if ($status === 'banned' || $status === 'suspended' || ($suspendedUntilTs !== false && $suspendedUntilTs > time())) {
            throw new ForbiddenException('This person cannot receive direct messages.');
        }
        $allow = (string) ($recipient['allow_dms'] ?? 'members');
        if ($allow === 'none' && !$sender->isAdmin()) {
            throw new ForbiddenException('This person is not accepting direct messages.');
        }
        if ($newConversation && $this->isThrottledNewUser($sender)) {
            throw new ForbiddenException('New accounts cannot start conversations yet. Post something first or come back later.');
        }
    }

    /** Anti-spam: brand-new accounts (no posts, very young) cannot start new DMs. */
    private function isThrottledNewUser(User $sender): bool
    {
        if ($sender->isAdmin() || $sender->isModerator()) {
            return false;
        }
        $row = $sender->toArray();
        $posts = (int) ($row['post_count'] ?? 0);
        if ($posts >= (int) $this->config->get('dm.new_user_min_posts', 1)) {
            return false;
        }
        $createdAt = strtotime((string) ($row['created_at'] ?? '') . ' UTC');
        $minAge = (int) $this->config->get('dm.new_user_min_age_minutes', 1440) * 60;
        if ($createdAt !== false && (time() - $createdAt) >= $minAge) {
            return false;
        }
        return true;
    }

    private function validateBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            throw new ValidationException(['body' => 'Write a message before sending.']);
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            throw new ValidationException(['body' => 'Your message is too long.']);
        }
        return $body;
    }

    /** @param list<int> $participantIds */
    private function validateTitle(string $title, array $participantIds): string
    {
        $title = trim($title);
        if ($title === '') {
            $names = [];
            foreach (array_slice($participantIds, 0, 3) as $id) {
                $row = $this->users->find((int) $id);
                if ($row !== null) {
                    $names[] = ($row['display_name'] ?? '') !== '' ? (string) $row['display_name'] : (string) $row['username'];
                }
            }
            $title = $names !== [] ? implode(', ', $names) : 'Group conversation';
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            throw new ValidationException(['title' => 'Group title is too long.']);
        }
        return $title;
    }

    /** @return array<string,mixed> */
    private function groupForOwnerAction(User $actor, int $conversationId): array
    {
        $conversation = $this->conversations->find($conversationId);
        if ($conversation === null || (string) ($conversation['kind'] ?? 'direct') !== 'group') {
            throw new NotFoundException('Conversation not found.');
        }
        if ((int) ($conversation['owner_user_id'] ?? 0) !== $actor->id()) {
            throw new ForbiddenException('Only the group owner can do that.');
        }
        if (!$this->conversations->isParticipant($conversationId, $actor->id())) {
            throw new NotFoundException('Conversation not found.');
        }
        return $conversation;
    }
}
