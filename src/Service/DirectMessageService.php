<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
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
            $messageId = $this->deliver($sender, $conversationId, $recipientId, $body);
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
        $otherId = $this->conversations->otherParticipant($conversationId, $sender->id());
        if ($otherId === null) {
            throw new NotFoundException('Conversation not found.');
        }
        $other = $this->users->find($otherId);
        if ($other !== null) {
            // Re-check block/allow-DMs at send time, but not the new-user gate
            // (they are already in an established conversation).
            $this->assertCanContact($sender, $other, newConversation: false);
        }
        $body = $this->validateBody($body);

        return $this->db->transaction(fn (): int => $this->deliver($sender, $conversationId, $otherId, $body));
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
        if (!$this->conversations->isParticipant((int) $message['conversation_id'], $reporter->id())) {
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
    }

    /** Insert the message, advance conversation + read marker, notify the recipient. */
    private function deliver(User $sender, int $conversationId, int $recipientId, string $body): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $messageId = $this->messages->create($conversationId, $sender->id(), $body, $this->markdown->render($body));
        $this->conversations->touch($conversationId, $now);
        $this->conversations->markRead($conversationId, $sender->id(), $messageId); // sender has "read" their own
        $this->notifications->notifyDm($sender->id(), $recipientId, $conversationId);
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
}
