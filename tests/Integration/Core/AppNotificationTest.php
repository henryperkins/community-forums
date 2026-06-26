<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\BlockRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Service\NotificationService;
use Tests\Support\TestCase;

/**
 * Subscription fan-out + in-app notifications (P2-03). Covers precedence
 * (thread overrides board), actor/block exclusion, idempotent reaction notices,
 * and the instant-email enqueue with the post:user idempotency key.
 */
final class AppNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function notifier(?Mailer $mailer = null): NotificationService
    {
        return new NotificationService(
            $this->db,
            new NotificationRepository($this->db),
            new SubscriptionRepository($this->db),
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new BlockRepository($this->db),
            $this->users(),
            new FeatureFlags(new SettingRepository($this->db)),
            $mailer ?? new ArrayMailer(),
        );
    }

    /** @return array{0:array<string,mixed>,1:int} thread-with-board ctx + a reply id by the author */
    private function threadWithReply(array $author): array
    {
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Subscribed thread', 'OP.');
        $ctx = $this->threads()->findWithBoard($thread['thread_id']);
        $replyId = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'A reply.']);
        return [$ctx, $replyId];
    }

    private function notifCount(int $userId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId]);
    }

    public function testThreadSubscriptionOverridesBoardForFanout(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $boardId = (int) $ctx['board_id'];
        $threadId = (int) $ctx['id'];

        $subs = new SubscriptionRepository($this->db);
        $offUser = $this->makeUser();   // board=instant but thread=off → silenced
        $onUser = $this->makeUser();    // board=instant, no thread row → notified
        $subs->set((int) $offUser['id'], 'board', $boardId, true, true, 'instant');
        $subs->set((int) $offUser['id'], 'thread', $threadId, true, true, 'off');
        $subs->set((int) $onUser['id'], 'board', $boardId, true, true, 'instant');

        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        self::assertSame(0, $this->notifCount((int) $offUser['id']), 'thread Off overrides board Instant');
        self::assertSame(1, $this->notifCount((int) $onUser['id']), 'board Instant subscriber notified');
    }

    public function testAuthorAndBlockedUsersAreExcluded(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $threadId = (int) $ctx['id'];

        $subs = new SubscriptionRepository($this->db);
        $blocker = $this->makeUser();
        $subs->set((int) $blocker['id'], 'thread', $threadId, true, true, 'instant');
        $subs->set((int) $author['id'], 'thread', $threadId, true, true, 'instant'); // author subscribed
        (new BlockRepository($this->db))->block((int) $blocker['id'], (int) $author['id']); // blocker blocked the actor

        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        self::assertSame(0, $this->notifCount((int) $author['id']), 'actor never notified of their own post');
        self::assertSame(0, $this->notifCount((int) $blocker['id']), 'blocked pair excluded from fan-out');
    }

    public function testInstantEmailEnqueuedOnceWithIdempotencyKey(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $threadId = (int) $ctx['id'];

        $sub = $this->makeUser();
        (new SubscriptionRepository($this->db))->set((int) $sub['id'], 'thread', $threadId, true, true, 'instant');

        // Two fan-out passes (e.g. a retry) must not double-enqueue the email.
        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');
        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        $key = $replyId . ':' . (int) $sub['id'];
        $rows = (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_deliveries WHERE idempotency_key = ?', [$key]);
        self::assertSame(1, $rows, 'idempotency key dedupes the instant email across retries');
    }

    public function testSuppressedAddressIsNeverEnqueued(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $sub = $this->makeUser(['email' => 'blocked@example.test']);
        (new SubscriptionRepository($this->db))->set((int) $sub['id'], 'thread', (int) $ctx['id'], true, true, 'instant');
        (new EmailSuppressionRepository($this->db))->suppress('blocked@example.test', 'unsubscribe');

        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_deliveries WHERE user_id = ?', [(int) $sub['id']]));
        self::assertSame(1, $this->notifCount((int) $sub['id']), 'in-app still delivered to a suppressed-email user');
    }

    public function testFailsClosedWhenMailUnconfiguredButInAppContinues(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $sub = $this->makeUser();
        (new SubscriptionRepository($this->db))->set((int) $sub['id'], 'thread', (int) $ctx['id'], true, true, 'instant');

        // SendmailMailer with no From ⇒ not configured.
        $this->notifier(new \App\Mail\SendmailMailer(''))->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_deliveries'), 'no email enqueued when transport unconfigured');
        self::assertSame(1, $this->notifCount((int) $sub['id']), 'in-app notification still delivered');
    }

    public function testBellAndMarkAllReadOverHttp(): void
    {
        $author = $this->makeUser();
        [$ctx, $replyId] = $this->threadWithReply($author);
        $reader = $this->makeUser();
        (new SubscriptionRepository($this->db))->set((int) $reader['id'], 'thread', (int) $ctx['id'], true, true, 'instant');
        $this->notifier()->fanOutNewPost((int) $author['id'], $ctx, $replyId, false, 'A reply.');

        $this->actingAs($reader);
        $bell = $this->get('/notifications/bell', ['format' => 'json']);
        $this->assertStatus(200, $bell);
        $data = json_decode($bell->body(), true);
        self::assertSame(1, $data['unread']);

        $this->post('/notifications/read-all');
        self::assertSame(0, (new NotificationRepository($this->db))->unreadCount((int) $reader['id']));
    }
}
