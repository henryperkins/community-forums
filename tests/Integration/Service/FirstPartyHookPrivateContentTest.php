<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\App;
use App\Repository\SettingRepository;
use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

/**
 * SP0 private-content-absence proof for the first-party domain producers
 * (SLICE-FIRST-PARTY-HOOKS). Two guarantees, proved against the real kernel:
 *   1. board-visibility gate - hidden/private board topics and DM reports emit
 *      NO delivery at all; and
 *   2. payload minimization - the events that DO fire carry IDs + enums only,
 *      never titles, bodies, emails, or free-text reasons.
 *
 * No production code changes: a RED here is a real content leak in a producer.
 */
final class FirstPartyHookPrivateContentTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', [
            'webhooks' => true,
            'service_secrets' => true,
            'first_party_hooks' => true,
        ]);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->admin = $this->makeAdmin(['username' => 'privadmin', 'password' => 'password123']);
    }

    /** @param list<string> $events */
    private function registerEndpoint(array $events): int
    {
        return (new WebhookRepository($this->db))->insert(
            'priv-' . bin2hex(random_bytes(4)),
            'https://example.test/hook',
            json_encode($events) ?: '[]',
            'svcsec_test',
            (int) $this->admin['id'],
        );
    }

    /** @param array<string,mixed> $author */
    private function createThreadOverHttp(array $author, int $boardId, string $title, string $body): void
    {
        $this->actingAs($author);
        self::assertContains($this->post('/threads', [
            'board_id' => $boardId,
            'title' => $title,
            'body' => $body,
            'idempotency_key' => 'priv-' . bin2hex(random_bytes(6)),
        ])->status(), [302, 303]);
    }

    /** @return list<string> raw delivery payloads for an endpoint+event */
    private function payloads(int $webhookId, string $event): array
    {
        $rows = $this->db->fetchAll(
            'SELECT payload FROM webhook_deliveries WHERE webhook_id = ? AND event_type = ? ORDER BY id',
            [$webhookId, $event],
        );
        return array_map(static fn (array $r): string => (string) $r['payload'], $rows);
    }

    public function test_hidden_and_private_board_topics_emit_no_delivery(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $cat = $this->makeCategory();
        $hidden = $this->makeBoard($cat, ['slug' => 'priv-hidden', 'visibility' => 'hidden']);
        $private = $this->makeBoard($cat, ['slug' => 'priv-private', 'visibility' => 'private']);

        $author = $this->makeUser(['username' => 'hiddenauthor']);
        $this->createThreadOverHttp($author, (int) $hidden['id'], 'Hidden title MUST NOT SHIP', 'Hidden body MUST NOT SHIP');

        $this->actingAs($this->admin);
        $this->createThreadOverHttp($this->admin, (int) $private['id'], 'Private title MUST NOT SHIP', 'Private body MUST NOT SHIP');

        self::assertSame([], $this->payloads($topicHook, 'topic.created'), 'non-public boards emit no domain event');
    }

    public function test_public_topic_payload_carries_ids_and_enums_only(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'priv-public']);
        $author = $this->makeUser(['username' => 'publicauthor']);
        $this->createThreadOverHttp($author, (int) $board['id'], 'Public title MUST NOT SHIP', 'Public body MUST NOT SHIP');

        $payloads = $this->payloads($topicHook, 'topic.created');
        self::assertCount(1, $payloads);
        $raw = $payloads[0];
        self::assertStringNotContainsString('Public title MUST NOT SHIP', $raw);
        self::assertStringNotContainsString('Public body MUST NOT SHIP', $raw);

        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('thread:', (string) $payload['id']);
        self::assertIsArray($payload['data']);
        foreach (['title', 'body', 'body_html', 'content', 'excerpt'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload['data'], "data must not carry $forbidden");
        }
    }

    public function test_dm_report_emits_no_report_event_and_leaks_no_dm_content(): void
    {
        $reportHook = $this->registerEndpoint(['report.created']);
        $alice = $this->makeUser(['username' => 'dmpriva']);
        $this->makeUser(['username' => 'dmprivb']);
        $public = $this->makeBoard($this->makeCategory(), ['slug' => 'dm-priv-establish']);
        $this->makeThread($public, $alice, 'Establish sender', 'gives alice a post');

        $this->actingAs($alice);
        $this->assertRedirectContains(
            $this->post('/messages', ['to' => 'dmprivb', 'body' => 'DM body MUST NOT SHIP']),
            '/messages/',
        );
        $messageId = (int) $this->db->fetchValue('SELECT id FROM dm_messages ORDER BY id DESC LIMIT 1');

        $bob = $this->users()->findByUsername('dmprivb');
        self::assertNotNull($bob);
        $this->actingAs($bob);
        $this->assertRedirectContains(
            $this->post('/dm/' . $messageId . '/report', ['reason_code' => 'abuse', 'reason' => 'DM reason MUST NOT SHIP']),
            '/messages/',
        );

        self::assertSame([], $this->payloads($reportHook, 'report.created'), 'DM reports never become a public webhook event');
    }

    public function test_public_report_payload_omits_reason_reporter_and_body(): void
    {
        $reportHook = $this->registerEndpoint(['report.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'priv-report']);
        $author = $this->makeUser(['username' => 'reportedauthor']);
        $thread = $this->makeThread($board, $author, 'Reportable topic', 'Reportable OP body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [(int) $thread['thread_id']]);

        $reporter = $this->makeUser(['username' => 'reporterpriv']);
        $this->actingAs($reporter);
        $this->assertRedirectContains(
            $this->post('/posts/' . $postId . '/report', ['reason_code' => 'spam', 'reason' => 'Report reason MUST NOT SHIP']),
            '/t/',
        );

        $payloads = $this->payloads($reportHook, 'report.created');
        self::assertCount(1, $payloads);
        $raw = $payloads[0];
        self::assertStringNotContainsString('Report reason MUST NOT SHIP', $raw);
        self::assertStringNotContainsString('Reportable OP body', $raw);

        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('report:', (string) $payload['id']);
        foreach (['reason', 'reason_text', 'note', 'body', 'reporter_username'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload['data']);
        }
    }

    /** @param array<string,mixed> $author */
    private function createAnonThreadOverHttp(array $author, int $boardId, string $title, string $body): void
    {
        $this->actingAs($author);
        self::assertContains($this->post('/threads', [
            'board_id' => $boardId,
            'title' => $title,
            'body' => $body,
            'is_anonymous' => '1',
            'idempotency_key' => 'anon-' . bin2hex(random_bytes(6)),
        ])->status(), [302, 303]);
    }

    private function latestPostId(int $threadId): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id DESC LIMIT 1', [$threadId]);
    }

    public function test_anonymous_public_topic_masks_author_id(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'anon-topic', 'allow_anonymous' => 1]);
        $author = $this->makeUser(['username' => 'anontopicauthor']);

        $this->createAnonThreadOverHttp($author, (int) $board['id'], 'Anon topic title', 'Anon topic body');

        $payloads = $this->payloads($topicHook, 'topic.created');
        self::assertCount(1, $payloads);
        $data = json_decode($payloads[0], true)['data'];
        self::assertNull($data['author_id'], 'the real author of an anonymous post must never reach a package');
        self::assertTrue($data['is_anonymous']);
        self::assertNotSame((int) $author['id'], $data['author_id']);
    }

    public function test_non_anonymous_public_topic_still_carries_author_id(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'named-topic']);
        $author = $this->makeUser(['username' => 'namedtopicauthor']);

        $this->createThreadOverHttp($author, (int) $board['id'], 'Named topic title', 'Named topic body');

        $data = json_decode($this->payloads($topicHook, 'topic.created')[0], true)['data'];
        self::assertSame((int) $author['id'], $data['author_id'], 'named authorship is preserved (no over-masking)');
        self::assertFalse($data['is_anonymous']);
    }

    public function test_anonymous_public_reply_masks_author_id(): void
    {
        $replyHook = $this->registerEndpoint(['reply.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'anon-reply', 'allow_anonymous' => 1]);
        $op = $this->makeUser(['username' => 'replyop']);
        $thread = $this->makeThread($board, $op, 'Reply host topic', 'host op body');
        $replier = $this->makeUser(['username' => 'anonreplier']);

        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/t/' . (int) $thread['thread_id'] . '/reply', [
            'body' => 'anon reply body',
            'is_anonymous' => '1',
            'idempotency_key' => 'anonr-' . bin2hex(random_bytes(6)),
        ]), '/t/' . (int) $thread['thread_id']);

        $data = json_decode($this->payloads($replyHook, 'reply.created')[0], true)['data'];
        self::assertNull($data['author_id']);
        self::assertTrue($data['is_anonymous']);
    }

    public function test_anonymous_self_edit_masks_author_and_editor(): void
    {
        $editHook = $this->registerEndpoint(['post.edited']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'anon-edit', 'allow_anonymous' => 1]);
        $op = $this->makeUser(['username' => 'editop']);
        $thread = $this->makeThread($board, $op, 'Edit host topic', 'host op body');
        $replier = $this->makeUser(['username' => 'anoneditor']);

        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/t/' . (int) $thread['thread_id'] . '/reply', [
            'body' => 'original anon body',
            'is_anonymous' => '1',
            'idempotency_key' => 'anone-' . bin2hex(random_bytes(6)),
        ]), '/t/' . (int) $thread['thread_id']);
        $replyId = $this->latestPostId((int) $thread['thread_id']);

        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/posts/' . $replyId . '/edit', ['body' => 'edited anon body']), '/t/');

        $data = json_decode($this->payloads($editHook, 'post.edited')[0], true)['data'];
        self::assertNull($data['author_id']);
        self::assertNull($data['edited_by_id'], 'a self-edit actor id would re-identify the masked author');
        self::assertTrue($data['is_anonymous']);
    }

    public function test_anonymous_self_delete_masks_author_and_deleter(): void
    {
        $deleteHook = $this->registerEndpoint(['post.deleted']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'anon-delete', 'allow_anonymous' => 1]);
        $op = $this->makeUser(['username' => 'deleteop']);
        $thread = $this->makeThread($board, $op, 'Delete host topic', 'host op body');
        $replier = $this->makeUser(['username' => 'anondeleter']);

        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/t/' . (int) $thread['thread_id'] . '/reply', [
            'body' => 'anon body to delete',
            'is_anonymous' => '1',
            'idempotency_key' => 'anond-' . bin2hex(random_bytes(6)),
        ]), '/t/' . (int) $thread['thread_id']);
        $replyId = $this->latestPostId((int) $thread['thread_id']);

        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/posts/' . $replyId . '/delete'), '/t/');

        $data = json_decode($this->payloads($deleteHook, 'post.deleted')[0], true)['data'];
        self::assertNull($data['author_id']);
        self::assertNull($data['deleted_by_id'], 'a self-delete actor id would re-identify the masked author');
        self::assertTrue($data['is_anonymous']);
    }

    public function test_anonymous_accepted_answer_masks_answer_author_id(): void
    {
        $solvedHook = $this->registerEndpoint(['thread.solved']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'anon-solved', 'allow_anonymous' => 1]);
        $op = $this->makeUser(['username' => 'solveop']);
        $thread = $this->makeThread($board, $op, 'Solve host topic', 'host op body');
        $answerer = $this->makeUser(['username' => 'anonanswerer']);

        $this->actingAs($answerer);
        $this->assertRedirectContains($this->post('/t/' . (int) $thread['thread_id'] . '/reply', [
            'body' => 'anon accepted answer body',
            'is_anonymous' => '1',
            'idempotency_key' => 'anons-' . bin2hex(random_bytes(6)),
        ]), '/t/' . (int) $thread['thread_id']);
        $answerId = $this->latestPostId((int) $thread['thread_id']);

        $this->actingAs($op);
        $this->assertRedirectContains($this->post('/posts/' . $answerId . '/accept'), '/t/');

        $data = json_decode($this->payloads($solvedHook, 'thread.solved')[0], true)['data'];
        self::assertNull($data['answer_author_id'], 'an anonymous accepted answer must not reveal its author');
        self::assertTrue($data['is_anonymous']);
    }

    public function test_member_registered_payload_omits_email(): void
    {
        $memberHook = $this->registerEndpoint(['member.registered']);
        $this->logoutClient();
        $this->get('/register');
        $this->assertRedirectContains($this->post('/register', [
            'username' => 'freshpriv',
            'email' => 'freshpriv@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]), '/');

        $payloads = $this->payloads($memberHook, 'member.registered');
        self::assertCount(1, $payloads);
        self::assertStringNotContainsString('freshpriv@example.test', $payloads[0], 'email is PII and must never ship');
        $payload = json_decode($payloads[0], true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('user:', (string) $payload['id']);
        self::assertArrayNotHasKey('email', $payload['data']);
    }
}
