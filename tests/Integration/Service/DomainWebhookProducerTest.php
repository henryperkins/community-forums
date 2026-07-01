<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\App;
use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\TestCase;

final class DomainWebhookProducerTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableWebhookFeatures();
        $this->admin = $this->makeAdmin(['username' => 'hookadmin', 'password' => 'password123']);
    }

    public function test_public_domain_events_enqueue_one_delivery_with_redacted_payloads(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'hooks-public']);
        $author = $this->makeUser(['username' => 'hookauthor']);

        $topicHook = $this->registerEndpoint(['topic.created']);
        $thread = $this->createThreadOverHttp($author, (int) $board['id'], 'Hook Topic Title', 'OP body should not ship');
        $topicPayload = $this->assertOneDelivery($topicHook, 'topic.created', ['Hook Topic Title', 'OP body should not ship']);
        self::assertSame('thread:' . $thread['id'] . ':created', $topicPayload['id']);

        $replyHook = $this->registerEndpoint(['reply.created']);
        $replier = $this->makeUser(['username' => 'hookreplier']);
        $replyId = $this->replyOverHttp($replier, (int) $thread['id'], 'Reply body should not ship');
        $replyPayload = $this->assertOneDelivery($replyHook, 'reply.created', ['Reply body should not ship']);
        self::assertSame('post:' . $replyId . ':created', $replyPayload['id']);

        $editHook = $this->registerEndpoint(['post.edited']);
        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/posts/' . $replyId . '/edit', ['body' => 'Edited body should not ship']), '/t/');
        $editPayload = $this->assertOneDelivery($editHook, 'post.edited', ['Edited body should not ship']);
        self::assertStringStartsWith('post:' . $replyId . ':edited:', $editPayload['id']);

        $deleteHook = $this->registerEndpoint(['post.deleted']);
        $deleteId = $this->replyOverHttp($replier, (int) $thread['id'], 'Delete body should not ship');
        $this->actingAs($replier);
        $this->assertRedirectContains($this->post('/posts/' . $deleteId . '/delete'), '/t/');
        $deletePayload = $this->assertOneDelivery($deleteHook, 'post.deleted', ['Delete body should not ship']);
        self::assertStringStartsWith('post:' . $deleteId . ':deleted:', $deletePayload['id']);

        $solvedHook = $this->registerEndpoint(['thread.solved']);
        $answerer = $this->makeUser(['username' => 'answerer']);
        $answerId = $this->replyOverHttp($answerer, (int) $thread['id'], 'Accepted answer body should not ship');
        $this->actingAs($author);
        $this->assertRedirectContains($this->post('/posts/' . $answerId . '/accept'), '/t/');
        $solvedPayload = $this->assertOneDelivery($solvedHook, 'thread.solved', ['Accepted answer body should not ship']);
        self::assertStringStartsWith('thread:' . (int) $thread['id'] . ':solved:' . $answerId . ':', $solvedPayload['id']);

        $reportCreatedHook = $this->registerEndpoint(['report.created']);
        $reportResolvedHook = $this->registerEndpoint(['report.resolved']);
        $reporter = $this->makeUser(['username' => 'hookreporter']);
        $this->actingAs($reporter);
        $this->assertRedirectContains(
            $this->post('/posts/' . $answerId . '/report', ['reason_code' => 'spam', 'reason' => 'Reason should not ship', 'notify_reporter' => '1']),
            '/t/',
        );
        $reportId = (int) $this->db->fetchValue('SELECT id FROM reports WHERE post_id = ?', [$answerId]);
        $reportCreatedPayload = $this->assertOneDelivery($reportCreatedHook, 'report.created', ['Reason should not ship']);
        self::assertSame('report:' . $reportId . ':created', $reportCreatedPayload['id']);

        $this->actingAs($this->admin);
        $this->assertRedirectContains($this->post('/mod/reports/' . $reportId . '/resolve'), '/mod/reports');
        $reportResolvedPayload = $this->assertOneDelivery($reportResolvedHook, 'report.resolved', ['Reason should not ship']);
        self::assertSame('report:' . $reportId . ':resolved', $reportResolvedPayload['id']);
        self::assertSame('resolved', $reportResolvedPayload['data']['status']);

        $memberHook = $this->registerEndpoint(['member.registered']);
        $this->logoutClient();
        $this->get('/register');
        $this->assertRedirectContains($this->post('/register', [
            'username' => 'newhookmember',
            'email' => 'newhookmember@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]), '/');
        $newMemberId = (int) $this->db->fetchValue("SELECT id FROM users WHERE username = 'newhookmember'");
        $memberPayload = $this->assertOneDelivery($memberHook, 'member.registered', ['newhookmember@example.test', 'newhookmember']);
        self::assertSame('user:' . $newMemberId . ':registered', $memberPayload['id']);

        $banHook = $this->registerEndpoint(['member.banned']);
        $this->actingAs($this->admin);
        $this->assertRedirectContains($this->post('/mod/u/' . $newMemberId . '/ban', ['reason' => 'Ban reason should not ship']), '/u/');
        $banId = (int) $this->db->fetchValue('SELECT id FROM bans WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$newMemberId]);
        $banPayload = $this->assertOneDelivery($banHook, 'member.banned', ['Ban reason should not ship']);
        self::assertSame('user:' . $newMemberId . ':banned:' . $banId, $banPayload['id']);

        $autoHook = $this->registerEndpoint(['moderation.auto_action']);
        (new SettingRepository($this->db))->set('antiabuse_mode', 'hold');
        (new SettingRepository($this->db))->set('antiabuse_blocked_words', ['autohookword']);
        $filtered = $this->makeUser(['username' => 'filteredhook']);
        $this->actingAs($filtered);
        $this->assertRedirectContains($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Held by automation',
            'body' => 'This has autohookword but should not ship',
        ]), '/c/');
        $autoPayload = $this->assertOneDelivery($autoHook, 'moderation.auto_action', ['autohookword', 'Held by automation']);
        self::assertStringStartsWith('moderation:', $autoPayload['id']);
        self::assertSame('hold', $autoPayload['data']['action']);
    }

    public function test_pending_content_emits_only_when_approved(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $replyHook = $this->registerEndpoint(['reply.created']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'approval-hooks']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $author = $this->makeUser(['username' => 'heldtopic']);

        $this->createThreadOverHttp($author, (int) $board['id'], 'Held topic hook', 'Held OP');
        $threadId = (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Held topic hook'");
        self::assertSame(0, $this->deliveryCount($topicHook, 'topic.created'));

        $this->actingAs($this->admin);
        $this->assertRedirect($this->post('/mod/approvals/thread/' . $threadId . '/approve'), '/mod/approvals');
        self::assertSame(1, $this->deliveryCount($topicHook, 'topic.created'));

        $liveBoard = $this->makeBoard($cat, ['slug' => 'reply-approval-hooks']);
        $live = $this->createThreadOverHttp($author, (int) $liveBoard['id'], 'Live before reply hold', 'Live OP');
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $liveBoard['id']]);
        $replier = $this->makeUser(['username' => 'heldreply']);
        $replyId = $this->replyOverHttp($replier, (int) $live['id'], 'Held reply hook');
        self::assertSame(0, $this->deliveryCount($replyHook, 'reply.created'));

        $this->actingAs($this->admin);
        $this->assertRedirect($this->post('/mod/approvals/post/' . $replyId . '/approve'), '/mod/approvals');
        self::assertSame(1, $this->deliveryCount($replyHook, 'reply.created'));
    }

    public function test_idempotent_submit_does_not_enqueue_duplicate_topic_delivery(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'idempotent-hooks']);
        $author = $this->makeUser(['username' => 'idemhook']);
        $this->actingAs($author);
        $input = [
            'board_id' => (int) $board['id'],
            'title' => 'Idempotent hook topic',
            'body' => 'Only one delivery',
            'idempotency_key' => 'same-submit-token',
        ];

        $this->assertRedirectContains($this->post('/threads', $input), '/t/');
        $this->assertRedirectContains($this->post('/threads', $input), '/t/');

        self::assertSame(1, $this->deliveryCount($topicHook, 'topic.created'));
    }

    public function test_private_hidden_and_dm_report_events_are_suppressed(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $reportHook = $this->registerEndpoint(['report.created']);
        $cat = $this->makeCategory();
        $hidden = $this->makeBoard($cat, ['slug' => 'hidden-hooks', 'visibility' => 'hidden']);
        $private = $this->makeBoard($cat, ['slug' => 'private-hooks', 'visibility' => 'private']);
        $author = $this->makeUser(['username' => 'privatehook']);

        $this->createThreadOverHttp($author, (int) $hidden['id'], 'Hidden hook topic', 'Hidden body');
        $this->actingAs($this->admin);
        $this->createThreadOverHttp($this->admin, (int) $private['id'], 'Private hook topic', 'Private body');
        self::assertSame(0, $this->deliveryCount($topicHook, 'topic.created'));

        $alice = $this->makeUser(['username' => 'dmalice']);
        $bob = $this->makeUser(['username' => 'dmbob']);
        $public = $this->makeBoard($cat, ['slug' => 'dm-establish']);
        $this->makeThread($public, $alice, 'Establish DM sender', 'post count');

        $this->actingAs($alice);
        $this->assertRedirectContains($this->post('/messages', ['to' => 'dmbob', 'body' => 'DM report body should not ship']), '/messages/');
        $messageId = (int) $this->db->fetchValue('SELECT id FROM dm_messages ORDER BY id DESC LIMIT 1');
        $this->actingAs($bob);
        $this->assertRedirectContains($this->post('/dm/' . $messageId . '/report', ['reason_code' => 'abuse', 'reason' => 'DM reason should not ship']), '/messages/');
        self::assertSame(0, $this->deliveryCount($reportHook, 'report.created'));
    }

    public function test_first_party_hook_flag_dark_suppresses_domain_events_but_not_ping(): void
    {
        $this->enableWebhookFeatures(firstPartyHooks: false);
        $hook = $this->registerEndpoint(['ping', 'topic.created']);

        $this->webhookService()->sendTestEvent($this->userEntity($this->admin), $hook);
        self::assertSame(1, $this->deliveryCount($hook, 'ping'));

        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'dark-hooks']);
        $author = $this->makeUser(['username' => 'darkhookauthor']);
        $this->createThreadOverHttp($author, (int) $board['id'], 'Dark hook topic', 'No domain delivery');
        self::assertSame(0, $this->deliveryCount($hook, 'topic.created'));
    }

    private function enableWebhookFeatures(bool $firstPartyHooks = true): void
    {
        (new SettingRepository($this->db))->set('features', [
            'webhooks' => true,
            'service_secrets' => true,
            'first_party_hooks' => $firstPartyHooks,
        ]);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    /** @param list<string> $events */
    private function registerEndpoint(array $events): int
    {
        return (new WebhookRepository($this->db))->insert(
            'hook-' . bin2hex(random_bytes(4)),
            'https://example.test/hook',
            json_encode($events) ?: '[]',
            'svcsec_test',
            (int) $this->admin['id'],
        );
    }

    /** @return array{id:int,slug:string} */
    private function createThreadOverHttp(array $author, int $boardId, string $title, string $body): array
    {
        $this->actingAs($author);
        $this->assertContains($this->post('/threads', [
            'board_id' => $boardId,
            'title' => $title,
            'body' => $body,
            'idempotency_key' => 'topic-' . bin2hex(random_bytes(6)),
        ])->status(), [302, 303]);
        $thread = $this->db->fetch('SELECT id, slug FROM threads WHERE title = ?', [$title]);
        self::assertNotNull($thread);
        return ['id' => (int) $thread['id'], 'slug' => (string) $thread['slug']];
    }

    private function replyOverHttp(array $author, int $threadId, string $body): int
    {
        $this->actingAs($author);
        $this->assertRedirectContains($this->post('/t/' . $threadId . '/reply', [
            'body' => $body,
            'idempotency_key' => 'reply-' . bin2hex(random_bytes(6)),
        ]), '/t/' . $threadId);
        return (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id DESC LIMIT 1', [$threadId]);
    }

    /** @param list<string> $forbidden */
    private function assertOneDelivery(int $webhookId, string $event, array $forbidden = []): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM webhook_deliveries WHERE webhook_id = ? AND event_type = ? ORDER BY id ASC',
            [$webhookId, $event],
        );
        self::assertCount(1, $rows, 'Expected exactly one delivery for ' . $event);
        $json = (string) $rows[0]['payload'];
        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString($needle, $json);
        }
        $payload = json_decode($json, true);
        self::assertIsArray($payload);
        self::assertSame($event, $payload['event']);
        self::assertIsString($payload['id']);
        self::assertIsString($payload['occurred_at']);
        self::assertIsArray($payload['data']);
        return $payload;
    }

    private function deliveryCount(int $webhookId, string $event): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ? AND event_type = ?',
            [$webhookId, $event],
        );
    }

    private function webhookService(): WebhookService
    {
        return new WebhookService(
            $this->db,
            new WebhookRepository($this->db),
            new WebhookDeliveryRepository($this->db),
            new SecretVault(
                $this->db,
                new ServiceSecretRepository($this->db),
                new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
                new ModerationLogRepository($this->db),
                new FeatureFlags(new SettingRepository($this->db)),
                $this->config,
            ),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new EgressGuard(false, []),
        );
    }
}
