<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\EgressBlockedException;
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

final class WebhookDeliveryWorkerTest extends TestCase
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
    private function hookWithDelivery(string $eventId = 'e1'): array
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $id = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($id, 'ping', $eventId, '{"event":"ping"}', 6);
        return ['webhook_id' => $id, 'delivery_id' => $did];
    }

    public function test_2xx_marks_delivered_and_signs_with_the_secret(): void
    {
        $ids = $this->hookWithDelivery();
        $fake = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));
        $stats = $this->worker($fake)->run();
        self::assertSame(1, $stats['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);
        self::assertSame(
            'sha256=' . hash_hmac('sha256', $fake->calls[0]['headers']['X-RetroBoards-Timestamp'] . '.{"event":"ping"}', 'topsecret'),
            $fake->calls[0]['headers']['X-RetroBoards-Signature'],
        );
    }

    public function test_failure_retries_with_backoff_then_dead_letters(): void
    {
        $ids = $this->hookWithDelivery();
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        $stats = $this->worker($fail)->run();
        self::assertSame(1, $stats['retrying']);
        $row = $this->deliv->find($ids['delivery_id']);
        self::assertSame('queued', $row['status']);
        self::assertSame(1, (int) $row['attempt_count']);
        self::assertNotNull($row['next_attempt_at']);

        $this->db->run('UPDATE webhook_deliveries SET attempt_count = 5, next_attempt_at = NULL WHERE id = ?', [$ids['delivery_id']]);
        $stats2 = $this->worker($fail)->run();
        self::assertSame(1, $stats2['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);
    }

    public function test_egress_blocked_dead_letters_immediately(): void
    {
        $ids = $this->hookWithDelivery();
        $blocked = new FakeWebhookTransport(static function (): WebhookResponse {
            throw new EgressBlockedException('blocked');
        });
        $stats = $this->worker($blocked)->run();
        self::assertSame(1, $stats['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);
    }

    public function test_circuit_breaker_auto_disables_after_threshold(): void
    {
        $ids = $this->hookWithDelivery();
        $this->db->run('UPDATE webhooks SET consecutive_failures = 14 WHERE id = ?', [$ids['webhook_id']]);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(503, 'HTTP 503'));
        $this->worker($fail)->run();
        self::assertSame(0, (int) $this->hooks->findById($ids['webhook_id'])['is_active']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'webhook_auto_disabled' AND target_id = ?",
            [$ids['webhook_id']],
        ));
    }

    public function test_paused_endpoint_rows_are_not_delivered(): void
    {
        $ids = $this->hookWithDelivery();
        $this->hooks->disable($ids['webhook_id'], 'paused');
        $fake = new FakeWebhookTransport();
        $stats = $this->worker($fake)->run();
        self::assertSame(0, $stats['delivered']);
        self::assertCount(0, $fake->calls);
    }

    public function test_dark_flag_delivers_nothing(): void
    {
        $this->hookWithDelivery();
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $fake = new FakeWebhookTransport();
        $stats = $this->worker($fake)->run();
        self::assertSame(['delivered' => 0, 'retrying' => 0, 'dead' => 0, 'skipped' => 0], $stats);
        self::assertCount(0, $fake->calls);
    }

    public function test_dead_letters_at_the_row_max_attempts_snapshot_not_live_config(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $hid = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($hid, 'ping', 'snap', '{"event":"ping"}', 1);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        $stats = $this->worker($fail)->run();
        self::assertSame(1, $stats['dead']);
        self::assertSame('dead', $this->deliv->find($did)['status']);
    }

    public function test_breaker_skips_same_endpoints_remaining_rows_in_one_run(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $hid = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $this->deliv->enqueue($hid, 'ping', 'r1', '{"event":"ping"}', 6);
        $this->deliv->enqueue($hid, 'ping', 'r2', '{"event":"ping"}', 6);
        $this->db->run('UPDATE webhooks SET consecutive_failures = 14 WHERE id = ?', [$hid]);

        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(503, 'HTTP 503'));
        $stats = $this->worker($fail)->run();

        self::assertCount(1, $fail->calls);
        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, (int) $this->hooks->findById($hid)['is_active']);
    }

    public function test_rotation_emits_two_comma_separated_signatures(): void
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'oldsecret', $admin);
        $this->vault()->rotate($ref, 'newsecret', $admin, 3600);
        $id = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $this->deliv->enqueue($id, 'ping', 'rot1', '{"event":"ping"}', 6);

        $fake = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));
        $this->worker($fake)->run();

        $ts = $fake->calls[0]['headers']['X-RetroBoards-Timestamp'];
        $new = 'sha256=' . hash_hmac('sha256', $ts . '.{"event":"ping"}', 'newsecret');
        $old = 'sha256=' . hash_hmac('sha256', $ts . '.{"event":"ping"}', 'oldsecret');
        self::assertSame($new . ', ' . $old, $fake->calls[0]['headers']['X-RetroBoards-Signature']);
    }
}
