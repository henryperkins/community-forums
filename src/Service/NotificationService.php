<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Mail\Mailer;
use App\Repository\BlockRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\NotificationRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Support\MentionParser;

/**
 * Notification fan-out (P2-03/P2-04/P2-05). Invoked inside the originating write
 * transaction so notification rows and durable email-outbox rows commit or roll
 * back with the post (PHASE_2_PLAN §7.3).
 *
 * Invariants:
 *  - the actor never receives a notification for their own action;
 *  - blocked pairs (either direction) and recipients without board access are
 *    excluded from in-app AND email;
 *  - a mentioned user who is also a subscriber gets one in-app row, and the
 *    email idempotency key (post:user) guarantees one instant email regardless
 *    of how many fan-out paths match;
 *  - suppressed addresses are never enqueued; email fails closed when the
 *    transport is not configured, while in-app delivery continues.
 */
final class NotificationService
{
    public function __construct(
        private Database $db,
        private NotificationRepository $notifs,
        private SubscriptionRepository $subs,
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private BlockRepository $blocks,
        private UserRepository $users,
        private FeatureFlags $flags,
        private Mailer $mailer,
    ) {
    }

    /** Subscribe a thread's author to their own thread so they hear about replies. */
    public function autoSubscribeAuthor(int $userId, int $threadId): void
    {
        $this->subs->set($userId, 'thread', $threadId, true, true, 'instant');
    }

    /**
     * Fan out a newly created post: @mentions first, then thread/board
     * subscribers (precedence-resolved), excluding the actor, blocked pairs and
     * inaccessible recipients.
     *
     * @param array<string,mixed> $thread thread row incl. id, board_id, board_visibility
     */
    public function fanOutNewPost(int $actorId, array $thread, int $postId, bool $isNewThread, string $body): void
    {
        if (!$this->flags->enabled('notifications')) {
            return;
        }

        $threadId = (int) $thread['id'];
        $boardId = (int) $thread['board_id'];
        $visibility = (string) ($thread['board_visibility'] ?? 'public');
        $emailOn = $this->flags->enabled('email') && $this->mailer->isConfigured();

        // --- @mentions (P2-05) ---
        $notified = $this->flags->enabled('mentions')
            ? $this->dispatchMentions($actorId, $thread, $postId, MentionParser::parse($body), $emailOn)
            : [];

        // --- subscribers (P2-03), thread-over-board precedence ---
        $subscribers = $this->subs->subscribersForThread($threadId, $boardId);
        if ($subscribers === []) {
            return;
        }
        $ids = array_map(static fn (array $s): int => (int) $s['user_id'], $subscribers);
        $accessible = $this->accessibleSet($ids, $visibility, $boardId);
        $contacts = $emailOn ? $this->users->contactsForIds($ids) : [];

        foreach ($subscribers as $s) {
            $uid = (int) $s['user_id'];
            if ($uid === $actorId || !isset($accessible[$uid]) || $this->blocks->blockedEitherWay($actorId, $uid)) {
                continue;
            }
            if (!isset($notified[$uid]) && (int) $s['in_app_enabled'] === 1) {
                $this->notifs->create([
                    'user_id' => $uid, 'type' => $isNewThread ? 'new_thread' : 'reply',
                    'actor_id' => $actorId, 'thread_id' => $threadId, 'post_id' => $postId,
                ]);
                $notified[$uid] = true;
            }
            if ($emailOn && (int) $s['email_enabled'] === 1 && $s['frequency'] === 'instant') {
                $email = $contacts[$uid]['email'] ?? null;
                if ($email !== null) {
                    $this->enqueueInstant($uid, $email, $postId);
                }
            }
        }
    }

    /**
     * Notify a set of @handles (used by edits to alert only the NEWLY mentioned
     * users — existing mentions are not resent; PHASE_2_PLAN §8 "Mention edit").
     *
     * @param array<string,mixed> $thread
     * @param list<string> $handles
     */
    public function notifyMentions(int $actorId, array $thread, int $postId, array $handles): void
    {
        if (!$this->flags->enabled('notifications') || !$this->flags->enabled('mentions') || $handles === []) {
            return;
        }
        $emailOn = $this->flags->enabled('email') && $this->mailer->isConfigured();
        $this->dispatchMentions($actorId, $thread, $postId, $handles, $emailOn);
    }

    /**
     * Resolve handles → eligible recipients and create one 'mention' notification
     * each (+ instant email when enabled). Returns the set of notified user ids.
     *
     * @param array<string,mixed> $thread
     * @param list<string> $handles
     * @return array<int,bool>
     */
    private function dispatchMentions(int $actorId, array $thread, int $postId, array $handles, bool $emailOn): array
    {
        $notified = [];
        if ($handles === []) {
            return $notified;
        }
        $threadId = (int) $thread['id'];
        $boardId = (int) $thread['board_id'];
        $visibility = (string) ($thread['board_visibility'] ?? 'public');

        $mentioned = $this->users->findByUsernames($handles);
        $ids = array_map(static fn (array $u): int => $u['id'], $mentioned);
        $accessible = $this->accessibleSet($ids, $visibility, $boardId);

        foreach ($mentioned as $u) {
            $uid = $u['id'];
            if ($uid === $actorId || !isset($accessible[$uid]) || $this->blocks->blockedEitherWay($actorId, $uid)) {
                continue;
            }
            $this->notifs->create([
                'user_id' => $uid, 'type' => 'mention',
                'actor_id' => $actorId, 'thread_id' => $threadId, 'post_id' => $postId,
            ]);
            $notified[$uid] = true;
            if ($emailOn) {
                $this->enqueueInstant($uid, $u['email'], $postId);
            }
        }
        return $notified;
    }

    /** A reaction notifies the post author once (in-app only), unless self or blocked. */
    public function notifyReaction(int $actorId, array $post): void
    {
        if (!$this->flags->enabled('notifications')) {
            return;
        }
        $authorId = (int) $post['user_id'];
        if ($authorId === $actorId || $this->blocks->blockedEitherWay($actorId, $authorId)) {
            return;
        }
        $this->notifs->createReactionOnce($authorId, $actorId, (int) $post['thread_id'], (int) $post['id']);
    }

    private function enqueueInstant(int $userId, string $email, int $postId): void
    {
        if ($this->suppress->isSuppressed($email)) {
            return;
        }
        // idempotency_key = post:user — at most one instant email per (post, recipient).
        $this->deliveries->enqueue($userId, $email, 'instant', null, $postId . ':' . $userId);
    }

    /**
     * Which of $ids may currently access the board: everyone for public/hidden;
     * admins + board_members for private. Re-checked at fan-out so a revoked
     * member is dropped (PHASE_2_PLAN §11 "notification payload leaks revoked
     * access").
     *
     * @param list<int> $ids
     * @return array<int,bool> accessible user_id => true
     */
    private function accessibleSet(array $ids, string $visibility, int $boardId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        if ($visibility !== 'private') {
            $set = [];
            foreach ($ids as $i) {
                $set[$i] = true;
            }
            return $set;
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $set = [];
        foreach ($this->db->fetchAll("SELECT id FROM users WHERE role = 'admin' AND id IN ($place)", $ids) as $r) {
            $set[(int) $r['id']] = true;
        }
        foreach ($this->db->fetchAll("SELECT user_id FROM board_members WHERE board_id = ? AND user_id IN ($place)", array_merge([$boardId], $ids)) as $r) {
            $set[(int) $r['user_id']] = true;
        }
        return $set;
    }
}
