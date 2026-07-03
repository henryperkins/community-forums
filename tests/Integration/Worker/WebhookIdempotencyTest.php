<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use App\Service\Webhook\FakeWebhookTransport;
use App\Service\Webhook\WebhookResponse;
use App\Service\Webhook\WebhookTransport;
use App\Worker\WebhookDeliveryWorker;
use Tests\Support\TestCase;

/**
 * SLICE-WEBHOOKS SP0 — delivery-idempotency proof.
 *
 * Companion to WebhookDeliveryWorkerTest (which owns backoff / circuit-breaker /
 * dead-letter mechanics). This file proves the at-least-once ledger's idempotency
 * contract: dedup on the (webhook_id,event_type,event_id) triple, effectively-once
 * delivery on success, dead-letter terminality + replay, and that a queued
 * delivery can never be minted for an SSRF URL (guard wired at registration) or
 * while the webhooks flag is dark.
 */
final class WebhookIdempotencyTest extends TestCase
{
    private WebhookRepository $hooks;
    private WebhookDeliveryRepository $deliv;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
        $this->hooks = new WebhookRepository($this->db);
        $this->deliv = new WebhookDeliveryRepository($this->db);
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    private function worker(WebhookTransport $transport): WebhookDeliveryWorker
    {
        return new WebhookDeliveryWorker(
            $this->hooks,
            $this->deliv,
            $this->vault(),
            $transport,
            new FeatureFlags(new SettingRepository($this->db)),
            new ModerationLogRepository($this->db),
            $this->config,
        );
    }

    /** @return array{webhook_id:int,delivery_id:int} */
    private function hookWithDelivery(string $eventId = 'e1', string $event = 'ping'): array
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $id = $this->hooks->insert('idem', 'https://x.test/h', json_encode([$event]) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($id, $event, $eventId, '{"event":"' . $event . '"}', 6);
        return ['webhook_id' => $id, 'delivery_id' => $did];
    }

    public function test_enqueue_dedups_on_the_webhook_event_id_triple(): void
    {
        $ids = $this->hookWithDelivery('evt-dedup');
        // Re-enqueueing the identical triple is a no-op: 0 rows, no duplicate row.
        self::assertSame(0, $this->deliv->enqueue($ids['webhook_id'], 'ping', 'evt-dedup', '{"event":"ping"}', 6));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ? AND event_type = 'ping' AND event_id = 'evt-dedup'",
            [$ids['webhook_id']],
        ));
        // A different event_id for the same webhook/event is a distinct delivery.
        self::assertGreaterThan(0, $this->deliv->enqueue($ids['webhook_id'], 'ping', 'evt-other', '{"event":"ping"}', 6));
    }

    public function test_delivered_row_is_not_reclaimed_on_a_second_worker_run(): void
    {
        $ids = $this->hookWithDelivery('evt-once');
        $ok = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));

        self::assertSame(1, $this->worker($ok)->run()['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);

        // Second drain: the row is 'delivered', claim() only selects 'queued', so
        // nothing is re-sent — at-least-once collapses to effectively-once.
        $stats = $this->worker($ok)->run();
        self::assertSame(0, $stats['delivered']);
        self::assertCount(1, $ok->calls);
    }

    public function test_dead_letter_is_terminal_until_replay_then_delivers_once(): void
    {
        $ids = $this->hookWithDelivery('evt-dead');
        // Force the row to its final attempt, then fail it into the dead-letter state.
        $this->db->run('UPDATE webhook_deliveries SET attempt_count = 5, next_attempt_at = NULL WHERE id = ?', [$ids['delivery_id']]);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        self::assertSame(1, $this->worker($fail)->run()['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);

        // A dead row is not re-claimed by a subsequent drain (terminal).
        $ok = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));
        self::assertSame(0, $this->worker($ok)->run()['delivered']);

        // Explicit replay/requeue returns it to 'queued'; it then delivers once.
        self::assertSame(1, $this->deliv->requeue($ids['webhook_id'], $ids['delivery_id']));
        self::assertSame(1, $this->worker($ok)->run()['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);
    }

    public function test_registration_rejects_ssrf_url_via_static_egress_guard(): void
    {
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $res = $this->post('/admin/webhooks', [
            'name' => 'ssrf attempt',
            'url' => 'https://169.254.169.254/latest/meta-data/',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('not an allowed destination', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_admin_webhook_surface_is_404_while_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->assertStatus(404, $this->get('/admin/webhooks'));
        $this->assertStatus(404, $this->post('/admin/webhooks', [
            'name' => 'dark',
            'url' => 'https://example.test/hook',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]));
    }
}
