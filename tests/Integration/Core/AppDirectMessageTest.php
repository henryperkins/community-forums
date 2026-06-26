<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Mail\ArrayMailer;
use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Security\WriteGate;
use App\Service\DirectMessageService;
use App\Service\NotificationService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use Tests\Support\TestCase;

/**
 * One-to-one DMs (P2-07): eligibility matrix (block / allow_dms / account state /
 * new-user throttle), unread + notification, and the reported-context privacy.
 */
final class AppDirectMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function dm(): DirectMessageService
    {
        $notifs = new NotificationService(
            $this->db,
            new NotificationRepository($this->db),
            new SubscriptionRepository($this->db),
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new BlockRepository($this->db),
            $this->users(),
            new FeatureFlags(new SettingRepository($this->db)),
            new ArrayMailer(),
        );
        return new DirectMessageService(
            $this->db,
            new ConversationRepository($this->db),
            new DmMessageRepository($this->db),
            $this->users(),
            new BlockRepository($this->db),
            new WriteGate(),
            new Markdown(new HtmlSanitizer()),
            $notifs,
            $this->config,
        );
    }

    /** An "established" sender (passes the new-user throttle) — give them a post. */
    private function established(array $attrs = []): array
    {
        $u = $this->makeUser($attrs);
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $u, 'Hi', 'establishing a post.');
        return $this->users()->find((int) $u['id']);
    }

    public function testEstablishedUsersCanExchangeMessages(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();

        $result = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Hello Bob!');
        self::assertGreaterThan(0, $result['conversation_id']);

        // Bob has an unread conversation + a 'dm' notification.
        self::assertSame(1, (new ConversationRepository($this->db))->unreadConversationCount((int) $bob['id']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'dm'", [(int) $bob['id']]));

        // Reusing the pair does not create a second conversation.
        $again = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Another one');
        self::assertSame($result['conversation_id'], $again['conversation_id']);
    }

    public function testBlockPreventsMessaging(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        (new BlockRepository($this->db))->block((int) $bob['id'], (int) $alice['id']); // bob blocked alice

        $this->expectException(\App\Core\ForbiddenException::class);
        $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Can I reach you?');
    }

    public function testAllowDmsNoneRejectsMessages(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        $this->db->run("UPDATE users SET allow_dms = 'none' WHERE id = ?", [(int) $bob['id']]);

        $this->expectException(\App\Core\ForbiddenException::class);
        $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Hi');
    }

    public function testSuspendedUserCannotSend(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        // Suspend only after establishing, so the gate is exercised at send time.
        $this->users()->setStatus((int) $alice['id'], 'suspended', null);
        $alice = $this->users()->find((int) $alice['id']);

        $this->expectException(\App\Core\ForbiddenException::class);
        $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Hi');
    }

    public function testNewUserCannotStartConversations(): void
    {
        $fresh = $this->makeUser(); // 0 posts, just created → throttled
        $bob = $this->makeUser();

        $this->expectException(\App\Core\ForbiddenException::class);
        $this->dm()->start($this->userEntity($fresh), (int) $bob['id'], 'Hi');
    }

    public function testReportingExposesOnlyToParticipants(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        $result = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'A questionable message.');
        $messageId = $result['message_id'];

        // Bob (a participant) can report it → one report row.
        $this->dm()->reportMessage($this->userEntity($bob), $messageId, 'harassment', 'not nice');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reports WHERE dm_message_id = ?', [$messageId]));

        // A duplicate report by the same user is deduped.
        $this->dm()->reportMessage($this->userEntity($bob), $messageId, 'harassment', 'again');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reports WHERE dm_message_id = ?', [$messageId]));

        // A non-participant cannot report (and isn't told the message exists).
        $stranger = $this->makeUser();
        $this->expectException(\App\Core\NotFoundException::class);
        $this->dm()->reportMessage($this->userEntity($stranger), $messageId, 'spam', '');
    }

    public function testReplyDeliversAndNotifies(): void
    {
        $alice = $this->established();
        $bob = $this->established(['username' => 'bobestablished']);
        $convId = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Hello')['conversation_id'];

        // Bob reads (clears notification path) then replies.
        $this->dm()->reply($this->userEntity($bob), $convId, 'Hi back!');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'dm'", [(int) $alice['id']]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM dm_messages WHERE conversation_id = ?', [$convId]));
    }

    public function testConversationViewMarksReadOverHttp(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        $convId = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Ping')['conversation_id'];
        self::assertSame(1, (new ConversationRepository($this->db))->unreadConversationCount((int) $bob['id']));

        $this->actingAs($bob);
        $r = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $r);
        self::assertSame(0, (new ConversationRepository($this->db))->unreadConversationCount((int) $bob['id']), 'viewing marks the conversation read');
    }

    public function testLongConversationOpensOnNewestPageAndClearsUnread(): void
    {
        $alice = $this->established();
        $bob = $this->makeUser();
        $convId = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'SENTINEL-0001')['conversation_id'];

        // Push the conversation well past one page (perPage = 50).
        $msgs = new DmMessageRepository($this->db);
        for ($i = 2; $i <= 60; $i++) {
            $msgs->create($convId, (int) $alice['id'], sprintf('SENTINEL-%04d', $i), sprintf('<p>SENTINEL-%04d</p>', $i));
        }
        $latest = $msgs->latestId($convId);
        self::assertSame(1, (new ConversationRepository($this->db))->unreadConversationCount((int) $bob['id']));

        $this->actingAs($bob);
        $res = $this->get('/messages/' . $convId);          // no ?page → newest page
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'SENTINEL-0060');         // newest message is visible
        $this->assertDontSeeText($res, 'SENTINEL-0001');     // oldest page is not shown by default

        // The unread badge clears even though the conversation spans multiple pages.
        self::assertSame(0, (new ConversationRepository($this->db))->unreadConversationCount((int) $bob['id']));
        self::assertSame($latest, (int) $this->db->fetchValue(
            'SELECT last_read_message_id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?',
            [$convId, (int) $bob['id']],
        ));
    }
}
