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

    public function testHttpSendUsesCentralDmLimiter(): void
    {
        $this->config = new \App\Core\Config(array_replace_recursive($this->config->all(), [
            'rate_limits' => ['dm' => [1, 600]],
        ]));
        $this->app = new \App\Core\App($this->config, $this->db, $this->rateLimiter);
        $alice = $this->established(['username' => 'dmhttplimit']);
        $bob = $this->makeUser(['username' => 'dmrecipient']);

        $this->actingAs($alice);
        $this->get('/messages/new');
        $first = $this->post('/messages', ['to' => 'dmrecipient', 'body' => 'one']);
        $this->assertRedirectContains($first, '/messages/');

        $this->get('/messages/new');
        $blocked = $this->post('/messages', ['to' => 'dmrecipient', 'body' => 'two']);
        $this->assertStatus(429, $blocked);
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

    /**
     * A reply that fails validation re-renders the conversation in place (422)
     * with the typed body echoed back, instead of redirecting to an empty
     * composer. The echoed body keeps location.pathname === the form action with
     * a non-empty textarea, which is the signal composer.js uses to preserve the
     * local draft — so a failed reply no longer silently discards the user's text
     * (P3-03 draft-loss follow-up).
     */
    public function testFailedReplyOverHttpReRendersWithTypedBody(): void
    {
        $alice = $this->established(['username' => 'draftreplyalice']);
        $bob = $this->established(['username' => 'draftreplybob']);
        $convId = $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'Hello')['conversation_id'];

        $this->actingAs($bob);
        $tooLong = str_repeat('x', 5001);                       // > BODY_MAX (5000) → ValidationException
        $res = $this->post('/messages/' . $convId, ['body' => $tooLong]);

        // Re-rendered in place (not a PRG redirect) so the draft-keep heuristic fires.
        $this->assertStatus(422, $res);
        self::assertStringContainsString('Your message is too long.', $res->body());
        self::assertStringContainsString($tooLong, $res->body(), 'the submitted body is echoed back so the draft is not discarded');
        // Nothing was persisted — only the opening message exists.
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM dm_messages WHERE conversation_id = ?', [$convId]));
    }

    public function test_messages_index_unread_filter_lists_only_unread_conversations(): void
    {
        $recipient = $this->established(['username' => 'filt_recipient', 'display_name' => 'Filter Recipient']);
        $alfa = $this->established(['username' => 'filt_alfa', 'display_name' => 'Alfa Sender']);
        $bravo = $this->established(['username' => 'filt_bravo', 'display_name' => 'Bravo Sender']);

        // Two inbound conversations for the recipient, both initially unread.
        $this->dm()->start($this->userEntity($alfa), (int) $recipient['id'], 'From Alfa.');
        $bravoConv = $this->dm()->start($this->userEntity($bravo), (int) $recipient['id'], 'From Bravo.')['conversation_id'];

        // The recipient reads the Bravo conversation (viewing marks it read).
        $this->actingAs($recipient);
        $this->get('/messages/' . (int) $bravoConv);

        // "All" lists both conversations…
        $all = $this->get('/messages');
        $this->assertStatus(200, $all);
        $this->assertSeeText($all, 'Alfa Sender');
        $this->assertSeeText($all, 'Bravo Sender');

        // …"Unread" lists only the still-unread Alfa conversation, and marks the
        // chip active so the control reflects the applied filter.
        $unread = $this->get('/messages', ['filter' => 'unread']);
        $this->assertStatus(200, $unread);
        $this->assertSeeText($unread, 'Alfa Sender');
        $this->assertDontSeeText($unread, 'Bravo Sender');
        $this->assertSeeText($unread, 'class="pill is-active" href="/messages?filter=unread" aria-current="page"');
    }

    public function test_messages_index_search_narrows_by_name_and_last_body(): void
    {
        $me = $this->established(['username' => 'srch_me', 'display_name' => 'Search Me']);
        $alfa = $this->established(['username' => 'srch_alfa', 'display_name' => 'Alfa Correspondent']);
        $bravo = $this->established(['username' => 'srch_bravo', 'display_name' => 'Bravo Correspondent']);

        $this->dm()->start($this->userEntity($alfa), (int) $me['id'], 'News from the northern marches.');
        $this->dm()->start($this->userEntity($bravo), (int) $me['id'], 'Provisions ledger for the feast.');

        $this->actingAs($me);

        // By the other participant's name…
        $byName = $this->get('/messages', ['q' => 'alfa']);
        $this->assertStatus(200, $byName);
        $this->assertSeeText($byName, 'Alfa Correspondent');
        $this->assertDontSeeText($byName, 'Bravo Correspondent');

        // …and by the last message body; the typed query round-trips into the box.
        $byBody = $this->get('/messages', ['q' => 'provisions']);
        $this->assertStatus(200, $byBody);
        $this->assertSeeText($byBody, 'Bravo Correspondent');
        $this->assertDontSeeText($byBody, 'Alfa Correspondent');
        self::assertStringContainsString('name="q" value="provisions"', $byBody->body());
    }

    public function test_messages_index_search_treats_like_wildcards_literally(): void
    {
        $me = $this->established(['username' => 'wild_me']);
        $alfa = $this->established(['username' => 'wild_alfa', 'display_name' => 'Wildcard Alfa']);
        $this->dm()->start($this->userEntity($alfa), (int) $me['id'], 'A plain letter.');

        $this->actingAs($me);
        // '%' must be a literal character, not a match-everything wildcard.
        $res = $this->get('/messages', ['q' => '%']);
        $this->assertStatus(200, $res);
        $this->assertDontSeeText($res, 'Wildcard Alfa');
        $this->assertSeeText($res, 'No letters match your search.');
    }

    public function test_direct_conversation_read_receipt_flips_from_sent_to_read(): void
    {
        $alice = $this->established(['username' => 'rcpt_alice', 'display_name' => 'Receipt Alice']);
        $bob = $this->established(['username' => 'rcpt_bob', 'display_name' => 'Receipt Bob']);
        $convId = (int) $this->dm()->start($this->userEntity($alice), (int) $bob['id'], 'A first word.')['conversation_id'];

        // Bob has not opened the conversation — Alice's last letter is only Sent.
        $this->actingAs($alice);
        $before = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $before);
        self::assertStringContainsString('class="dm-receipt"', $before->body());
        self::assertStringContainsString('Sent</span>', $before->body());

        // Bob opens it (viewing marks read) — Alice now sees Read.
        $this->actingAs($bob);
        $this->get('/messages/' . $convId);
        $this->actingAs($alice);
        $after = $this->get('/messages/' . $convId);
        self::assertStringContainsString('Read</span>', $after->body());
        self::assertStringNotContainsString('Sent</span>', $after->body());

        // Bob replies — the newest letter is his, so Alice's view shows no receipt.
        $this->actingAs($bob);
        $this->post('/messages/' . $convId, ['body' => 'A reply.']);
        $this->actingAs($alice);
        $replied = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $replied);
        self::assertStringNotContainsString('class="dm-receipt"', $replied->body());
    }

    public function test_messages_index_renders_search_form_and_compose_dialog(): void
    {
        $me = $this->established(['username' => 'ui_me']);
        $this->actingAs($me);

        $res = $this->get('/messages');
        $this->assertStatus(200, $res);
        // The list search is a real GET form (works with no JS)…
        self::assertStringContainsString('class="dm-search"', $res->body());
        self::assertStringContainsString('name="q"', $res->body());
        // …and the round "+" is a native <details> compose dialog posting to the
        // existing /messages endpoint, with the group title gated dark.
        self::assertStringContainsString('dm-compose-details', $res->body());
        self::assertStringContainsString('name="to"', $res->body());
        self::assertStringNotContainsString('name="title"', $res->body());
    }
}
