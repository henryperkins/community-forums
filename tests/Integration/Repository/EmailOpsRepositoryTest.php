<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SubscriptionRepository;
use Tests\Support\TestCase;

final class EmailOpsRepositoryTest extends TestCase
{
    public function test_recent_filters_by_kind_and_email_and_counts(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $deliv->enqueue(null, 'a@example.test', 'instant', 'Hi A', 'k-a');
        $deliv->enqueue(null, 'b@example.test', 'digest', 'Hi B', null);

        $instant = $deliv->recent(50, 0, null, 'instant', null);
        self::assertCount(1, $instant);
        self::assertSame('a@example.test', (string) $instant[0]['email']);

        self::assertSame(2, $deliv->count(null, null, null));
        self::assertSame(1, $deliv->count(null, 'instant', null));
        self::assertSame(1, $deliv->count(null, null, 'b@example.test'));
        self::assertCount(1, $deliv->recent(50, 0, null, null, 'b@example.test'));
    }

    public function test_requeue_only_affects_failed_rows(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $id = $deliv->enqueue(null, 'c@example.test', 'instant', null, 'k-c');
        self::assertSame(0, $deliv->requeue($id)); // still queued → no-op

        $deliv->markFailed($id, 'boom');
        self::assertSame(1, $deliv->requeue($id));
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id = ?', [$id]));
        self::assertNull($this->db->fetchValue('SELECT error FROM email_deliveries WHERE id = ?', [$id]));
    }

    public function test_suppression_list_and_count_filter_by_reason(): void
    {
        $supp = new EmailSuppressionRepository($this->db);
        $supp->suppress('one@example.test', 'manual');
        $supp->suppress('two@example.test', 'bounce');

        self::assertCount(2, $supp->list(50, 0, null));
        self::assertSame(2, $supp->count(null));
        self::assertSame(1, $supp->count('manual'));
        self::assertCount(1, $supp->list(50, 0, 'bounce'));
    }

    public function test_subscription_cascade_helpers_toggle_email_channel(): void
    {
        $u = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $subs = new SubscriptionRepository($this->db);
        $subs->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');

        $subs->disableEmailForUser((int) $u['id']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));

        $subs->enableEmailForUser((int) $u['id']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
    }
}
