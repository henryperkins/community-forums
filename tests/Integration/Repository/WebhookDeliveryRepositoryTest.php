<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

final class WebhookDeliveryRepositoryTest extends TestCase
{
    private WebhookRepository $hooks;
    private WebhookDeliveryRepository $deliv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = new WebhookRepository($this->db);
        $this->deliv = new WebhookDeliveryRepository($this->db);
    }

    private function hook(): int
    {
        $admin = $this->makeAdmin();
        return $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', 'svcsec_x', (int) $admin['id']);
    }

    public function test_enqueue_dedupes_same_triple_but_allows_distinct_event_type(): void
    {
        $h = $this->hook();
        self::assertGreaterThan(0, $this->deliv->enqueue($h, 'post.edited', 'src1', '{}', 6));
        self::assertSame(0, $this->deliv->enqueue($h, 'post.edited', 'src1', '{}', 6));
        self::assertGreaterThan(0, $this->deliv->enqueue($h, 'post.deleted', 'src1', '{}', 6));
    }

    public function test_claim_only_returns_active_endpoint_rows_and_is_backoff_aware(): void
    {
        $h = $this->hook();
        $id = $this->deliv->enqueue($h, 'ping', 'e1', '{}', 6);
        $row = $this->deliv->claim(10)[0];
        self::assertSame($id, (int) $row['id']);
        self::assertSame('https://x.test/h', $row['url']);
        self::assertSame('svcsec_x', $row['secret_ref']);

        $this->hooks->disable($h, 'paused');
        self::assertSame([], $this->deliv->claim(10));
        $this->hooks->enable($h);

        $this->deliv->recordFailure($id, 500, 'x', gmdate('Y-m-d H:i:s', time() + 3600), false);
        self::assertSame([], $this->deliv->claim(10));
    }

    public function test_transitions_and_requeue(): void
    {
        $h = $this->hook();

        $a = $this->deliv->enqueue($h, 'ping', 'a', '{}', 6);
        $this->deliv->recordFailure($a, 500, 'boom', '2030-01-01 00:00:00', false);
        $this->deliv->markDelivered($a, 200);
        self::assertSame('delivered', $this->deliv->find($a)['status']);
        self::assertNull($this->deliv->find($a)['error']);

        $b = $this->deliv->enqueue($h, 'ping', 'b', '{}', 6);
        $this->deliv->recordFailure($b, 500, 'boom', null, true);
        self::assertSame('dead', $this->deliv->find($b)['status']);

        self::assertSame(0, $this->deliv->requeue($h + 1, $b));
        self::assertSame('dead', $this->deliv->find($b)['status']);

        self::assertSame(1, $this->deliv->requeue($h, $b));
        $row = $this->deliv->find($b);
        self::assertSame('queued', $row['status']);
        self::assertSame(0, (int) $row['attempt_count']);
        self::assertNull($row['response_status']);
        self::assertSame(0, $this->deliv->requeue($h, $a));
    }
}
