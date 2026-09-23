<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Request;
use App\Core\Response;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use Tests\Support\TestCase;

/**
 * Server and markup repairs from the Messages audit of 027d878: the header
 * overflow is a disclosure, a group composer addresses the group, a no-JS send
 * lands on its letter, list previews are plain text, one h1 per page, owner tools and
 * unread rows name what they are about, the details panel's presence has no
 * stray separator, and an empty poll tick writes nothing.
 */
final class AppMessagesServerMarkupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: int} */
    private function pair(): array
    {
        $alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Avery']);
        $bob = $this->makeUser(['username' => 'bob', 'display_name' => 'Bob Brooks']);
        $this->db->run('UPDATE users SET post_count = 1 WHERE id IN (?, ?)', [$alice['id'], $bob['id']]);
        $id = (new ConversationRepository($this->db))->findOrCreateBetween((int) $alice['id'], (int) $bob['id']);
        return [$this->users()->find((int) $alice['id']), $this->users()->find((int) $bob['id']), $id];
    }

    private function group(array $owner, array ...$members): int
    {
        return (new ConversationRepository($this->db))->createGroup(
            (int) $owner['id'],
            'Wardens',
            array_map(static fn (array $member): int => (int) $member['id'], $members),
        );
    }

    /** The section of $html from the first $start to the next $end (exclusive). */
    private function slice(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        self::assertNotFalse($from, 'Missing ' . $start);
        $to = strpos($html, $end, $from + strlen($start));
        return substr($html, $from, $to === false ? null : $to - $from);
    }

    public function testMoreActionsIsADisclosureWithoutMenuRoles(): void
    {
        [$alice, , $id] = $this->pair();
        $this->actingAs($alice);
        $menu = $this->slice($this->get('/messages/' . $id)->body(), '<details class="dm-menu">', '</details>');

        self::assertStringContainsString('<div class="dm-menu-pop">', $menu);
        self::assertStringNotContainsString('role="menu"', $menu);
        self::assertStringNotContainsString('role="menuitem"', $menu);
        self::assertStringContainsString('Mute conversation', $menu);
    }

    public function testGroupComposerAddressesTheGroupAndADirectOneTheCounterpart(): void
    {
        [$alice, $bob, $direct] = $this->pair();
        $carol = $this->makeUser(['username' => 'carol']);
        $group = $this->group($alice, $bob, $carol);
        $this->actingAs($alice);

        $groupPage = $this->get('/messages/' . $group)->body();
        self::assertStringContainsString('placeholder="Message the group…"', $groupPage);
        self::assertStringNotContainsString('placeholder="Message @bob…"', $groupPage);
        self::assertStringContainsString('placeholder="Message @bob…"', $this->get('/messages/' . $direct)->body());

        // The standalone form addresses a prefilled group the same way.
        self::assertStringContainsString('placeholder="Message the group…"', $this->get('/messages/new', ['to' => 'bob, carol'])->body());
        self::assertStringContainsString('placeholder="Message @bob…"', $this->get('/messages/new', ['to' => '@bob'])->body());
        $titled = $this->post('/messages', ['to' => 'bob, carol', 'title' => 'Wardens', 'body' => '']);
        self::assertSame(422, $titled->status());
        self::assertStringContainsString('placeholder="Message the group…"', $titled->body());
    }

    public function testANoJsReplyLandsOnItsLetterOnTheNewestPageOfALongConversation(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messages = new DmMessageRepository($this->db);
        for ($i = 1; $i <= 60; $i++) {
            $messages->create($id, (int) $bob['id'], 'Letter ' . $i, '<p>Letter ' . $i . '</p>');
        }
        $this->actingAs($alice);

        // land=letter is what the composer's <noscript> field sends without scripting.
        $response = $this->post('/messages/' . $id, ['body' => 'The newest letter.', 'land' => 'letter']);
        $newId = (int) $this->db->fetchValue('SELECT MAX(id) FROM dm_messages WHERE conversation_id = ?', [$id]);
        $this->assertStatus(303, $response);
        self::assertSame('/messages/' . $id . '#m' . $newId, $response->getHeader('location'));

        // The fragment resolves on the page the redirect opens (the newest, page 2).
        $page = $this->get('/messages/' . $id)->body();
        self::assertStringContainsString('id="m' . $newId . '"', $page);
        self::assertStringContainsString('href="/messages/' . $id . '?page=1"', $page);

        // A scripted send carries no land field and stays unanchored: app.js pins
        // it to the end, which a #m fragment would suppress on reload and Back.
        $scripted = $this->post('/messages/' . $id, ['body' => 'A scripted letter.']);
        $this->assertStatus(303, $scripted);
        self::assertSame('/messages/' . $id, $scripted->getHeader('location'));
    }

    public function testANoJsStartLandsOnTheFirstLetterAndReplaysTheSameAnchor(): void
    {
        [$alice, , $id] = $this->pair();
        $this->makeUser(['username' => 'carol']);
        $this->actingAs($alice);

        $payload = ['to' => 'bob', 'body' => 'Opening counsel.', 'idempotency_key' => 'tok-audit-start', 'land' => 'letter'];
        $first = $this->post('/messages', $payload);
        $messageId = (int) $this->db->fetchValue('SELECT MAX(id) FROM dm_messages WHERE conversation_id = ?', [$id]);
        $this->assertStatus(303, $first);
        self::assertSame('/messages/' . $id . '#m' . $messageId, $first->getHeader('location'));
        self::assertSame($first->getHeader('location'), $this->post('/messages', $payload)->getHeader('location'));

        $group = $this->post('/messages', ['to' => 'bob, carol', 'title' => 'Wardens', 'body' => 'Group opening.', 'land' => 'letter']);
        $this->assertStatus(303, $group);
        self::assertMatchesRegularExpression('~^/messages/(\d+)#m(\d+)$~', (string) $group->getHeader('location'));
        preg_match('~^/messages/(\d+)#m(\d+)$~', (string) $group->getHeader('location'), $match);
        self::assertSame(
            (int) $match[1],
            (int) $this->db->fetchValue('SELECT conversation_id FROM dm_messages WHERE id = ?', [(int) $match[2]]),
        );
        self::assertNotSame($id, (int) $match[1]);

        // Scripted starts (no land field), including the list's dialog, stay unanchored.
        $scripted = $this->post('/messages', ['to' => 'bob', 'body' => 'Scripted counsel.', 'origin' => 'dialog']);
        $this->assertStatus(303, $scripted);
        self::assertSame('/messages/' . $id, $scripted->getHeader('location'));
    }

    public function testEveryDmSendFormCarriesTheLandingFieldOnlyForNoScript(): void
    {
        [$alice, , $id] = $this->pair();
        $this->actingAs($alice);
        $field = '<noscript hidden><input type="hidden" name="land" value="letter"></noscript>';
        $forms = [
            '/messages/' . $id => 'dm-conversation-' . $id,
            '/messages/new' => 'dm-new-page',
            '/messages' => 'dm-new-dialog',
        ];

        foreach ($forms as $route => $instance) {
            $page = $this->get($route)->body();
            $form = $this->slice($page, 'data-composer-instance="' . $instance . '"', '</form>');
            self::assertSame(1, substr_count($form, $field), $route);
            // Only inside <noscript>, so a page with scripting never sends it.
            self::assertSame(substr_count($page, $field), substr_count($page, 'name="land"'), $route);
        }
    }

    public function testListPreviewIsPlainTextWithTheYouPrefix(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $this->actingAs($bob);
        $markdown = "```php\necho 'hi';\n```\n\nSee `worker:packages`, **bold**, _em_ and [the runbook](https://example.com/runbook).\n\n- Keep it quiet.\n- Keep the trail.\n\n> Quoted counsel";
        $this->assertStatus(303, $this->post('/messages/' . $id, ['body' => $markdown]));

        $this->actingAs($alice);
        $row = $this->slice($this->get('/messages')->body(), 'href="/messages/' . $id . '"', '</a>');
        $preview = $this->slice($row, '<span class="dm-preview">', '</span>');
        $expected = "echo 'hi'; See worker:packages, bold, em and the runbook. Keep it quiet. Keep the trail. Quoted counsel";
        self::assertSame('<span class="dm-preview">' . htmlspecialchars($expected, ENT_QUOTES), $preview);
        foreach (['```', '`', '**', '_em_', '](', '- Keep', '&gt;'] as $markup) {
            self::assertStringNotContainsString($markup, $preview);
        }
        // The instant filter keeps the raw opening the server's ?q= LIKE reads.
        self::assertStringContainsString('<li data-dm-search-text="' . htmlspecialchars(mb_strimwidth($markdown, 0, 120, ''), ENT_QUOTES) . '">', $this->get('/messages')->body());
        self::assertStringContainsString('example.com/runbook', mb_strimwidth($markdown, 0, 120, ''));

        // The viewer's own last letter keeps its "You:" label ahead of the words.
        $this->assertStatus(303, $this->post('/messages/' . $id, ['body' => '**Agreed** — `ship` it.']));
        $row = $this->slice($this->get('/messages')->body(), 'href="/messages/' . $id . '"', '</a>');
        self::assertStringContainsString('<span class="dm-preview"><span class="dm-preview-you">You:</span> Agreed — ship it.</span>', $row);

        // A letter whose words are its Markdown needs no second copy for the filter.
        $this->assertStatus(303, $this->post('/messages/' . $id, ['body' => 'Plain counsel.']));
        self::assertStringNotContainsString('data-dm-search-text', $this->get('/messages')->body());
    }

    public function testEachMessagesPageHasExactlyOneH1(): void
    {
        [$alice, , $id] = $this->pair();
        $this->actingAs($alice);

        $index = $this->get('/messages')->body();
        self::assertSame(1, preg_match_all('/<h1\b/', $index));
        self::assertStringContainsString('<h1 class="dm-listpane-title">Messages</h1>', $index);

        foreach (['/messages/' . $id => 'dm-thread-title', '/messages/new' => 'dm-thread-title'] as $route => $class) {
            $body = $this->get($route)->body();
            self::assertSame(1, preg_match_all('/<h1\b/', $body), $route);
            self::assertMatchesRegularExpression('/<h1 class="' . $class . '">/', $body, $route);
            self::assertStringContainsString('<h2 class="dm-listpane-title">Messages</h2>', $body, $route);
        }
    }

    public function testOwnerToolsNameTheMemberTheyActOn(): void
    {
        [$alice, $bob] = $this->pair();
        $carol = $this->makeUser(['username' => 'carol', 'display_name' => 'Carol Chen']);
        $group = $this->group($alice, $bob, $carol);
        $this->actingAs($alice);
        $members = $this->slice($this->get('/messages/' . $group)->body(), '<ul class="dm-members">', '</ul>');

        foreach (['Bob Brooks', 'Carol Chen'] as $name) {
            self::assertStringContainsString('aria-label="Make owner: ' . $name . '">Make owner</button>', $members);
            self::assertStringContainsString('aria-label="Remove ' . $name . '">Remove</button>', $members);
        }
        self::assertSame(2, substr_count($members, '>Make owner</button>'));
    }

    public function testUnreadRowsAnnounceUnreadBeforeTheirNameAndPreview(): void
    {
        [$alice, $bob, $id] = $this->pair();
        (new DmMessageRepository($this->db))->create($id, (int) $bob['id'], 'Unread counsel', '<p>Unread counsel</p>');
        $this->actingAs($alice);
        $row = $this->slice($this->get('/messages')->body(), 'href="/messages/' . $id . '"', '</a>');

        $announced = strpos($row, '<span class="sr-only">Unread. </span>');
        self::assertNotFalse($announced);
        self::assertLessThan(strpos($row, 'class="dm-other"'), $announced);
        self::assertLessThan(strpos($row, 'class="dm-preview"'), $announced);
        self::assertStringContainsString('<span class="dm-unread-dot" aria-hidden="true"></span>', $row);
        self::assertStringNotContainsString('aria-label="Unread"', $row);

        // Once read, the row carries no unread announcement at all.
        $this->get('/messages/' . $id);
        $row = $this->slice($this->get('/messages')->body(), 'href="/messages/' . $id . '"', '</a>');
        self::assertStringNotContainsString('<span class="sr-only">Unread. </span>', $row);
        self::assertStringNotContainsString('dm-unread-dot', $row);
    }

    public function testDetailsPanelPresenceHasNoLeadingSeparatorButTheHeaderKeepsIt(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP(), show_presence = 1 WHERE id = ?', [$bob['id']]);
        $this->actingAs($alice);
        $body = $this->get('/messages/' . $id)->body();

        $separator = '<span aria-hidden="true"> · </span>';
        $header = $this->slice($body, '<p class="dm-thread-sub">', '</p>');
        self::assertStringContainsString('@bob<span class="dm-presence is-online" data-dm-presence="' . $bob['id'] . '">' . $separator, $header);
        $railIdentity = $this->slice($body, '<div class="dm-rail-id">', '<span class="dm-tier-pill">');
        self::assertStringContainsString('<span class="dm-presence is-online" data-dm-presence="' . $bob['id'] . '"><span class="presence-dot-bare"', $railIdentity);
        self::assertStringNotContainsString(' · ', $railIdentity);
    }

    public function testGroupMemberPresenceKeepsItsSeparatorAfterTheHandle(): void
    {
        [$alice, $bob] = $this->pair();
        $carol = $this->makeUser(['username' => 'carol']);
        $group = $this->group($alice, $bob, $carol);
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP(), show_presence = 1 WHERE id = ?', [$bob['id']]);
        $this->actingAs($alice);
        $members = $this->slice($this->get('/messages/' . $group)->body(), '<ul class="dm-members">', '</ul>');

        self::assertStringContainsString('@bob<span class="dm-presence is-online" data-dm-presence="' . $bob['id'] . '"><span aria-hidden="true"> · </span>', $members);
    }

    /** Only kernel statements for one request, as AppRequestPerformanceTest measures them. */
    private function measuredPoll(int $conversationId, int $after): Response
    {
        $token = $this->csrfToken();
        $this->db->resetMetrics();
        return $this->app->handle(new Request('POST', '/messages/' . $conversationId . '/poll', [], [
            'after' => $after, '_token' => $token,
        ], $this->cookies, ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'phpunit']));
    }

    public function testAnEmptyPollTickReadsOnceAndWritesNothing(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messages = new DmMessageRepository($this->db);
        $seen = $messages->create($id, (int) $bob['id'], 'Seen', '<p>Seen</p>');
        $this->actingAs($alice);
        $this->get('/messages/' . $id);
        $conversations = new ConversationRepository($this->db);
        self::assertSame($seen, (int) $conversations->membership($id, (int) $alice['id'])['last_read_message_id']);

        // Session, user, settings and the setup gate belong to the kernel; the
        // tick adds the conversation, one participants read, the after-id read,
        // the presence block map and the nav count. It was 11 statements plus an
        // empty BEGIN/COMMIT: the membership and receipt rows were read twice.
        $empty = $this->measuredPoll($id, $seen);
        self::assertSame(200, $empty->status());
        self::assertLessThanOrEqual(9, $this->db->metrics()['queries']);
        $data = json_decode($empty->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('', $data['html']);
        self::assertSame($seen, $data['last_id']);
        self::assertSame($seen, (int) $conversations->membership($id, (int) $alice['id'])['last_read_message_id']);

        $new = $messages->create($id, (int) $bob['id'], 'New', '<p>New</p>');
        $delivered = $this->measuredPoll($id, $seen);
        self::assertLessThanOrEqual(11, $this->db->metrics()['queries']);
        $data = json_decode($delivered->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('id="m' . $new . '"', $data['html']);
        self::assertSame($new, (int) $conversations->membership($id, (int) $alice['id'])['last_read_message_id']);
        self::assertSame(0, $data['dm_unread']);
    }

    public function testPollReceiptWatermarkComesFromTheActiveCounterpart(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messages = new DmMessageRepository($this->db);
        $conversations = new ConversationRepository($this->db);
        $mine = $messages->create($id, (int) $alice['id'], 'Mine', '<p>Mine</p>');
        $this->actingAs($alice);
        $poll = fn (): array => json_decode($this->post('/messages/' . $id . '/poll', ['after' => $mine])->body(), true, flags: JSON_THROW_ON_ERROR);

        self::assertNull($poll()['other_last_read_message_id']);
        $conversations->markRead($id, (int) $bob['id'], $mine);
        self::assertSame($mine, $poll()['other_last_read_message_id']);
        self::assertSame($conversations->otherLastReadMessageId($id, (int) $alice['id']), $poll()['other_last_read_message_id']);

        $carol = $this->makeUser(['username' => 'carol']);
        $group = $this->group($alice, $bob, $carol);
        $conversations->markRead($group, (int) $bob['id'], $mine);
        self::assertNull(json_decode($this->post('/messages/' . $group . '/poll')->body(), true, flags: JSON_THROW_ON_ERROR)['other_last_read_message_id']);
    }
}
