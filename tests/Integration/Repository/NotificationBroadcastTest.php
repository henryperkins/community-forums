<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\NotificationRepository;
use Tests\Support\TestCase;

final class NotificationBroadcastTest extends TestCase
{
    public function test_broadcast_inserts_for_active_users_excluding_actor_and_inactive(): void
    {
        $actor = $this->makeUser(['username' => 'bcactor']);
        $alice = $this->makeUser(['username' => 'bcalice']);
        $bob = $this->makeUser(['username' => 'bcbob']);
        $banned = $this->makeUser(['username' => 'bcbanned', 'status' => 'banned']);
        $suspended = $this->makeUser(['username' => 'bcsusp', 'status' => 'suspended']);

        $count = (new NotificationRepository($this->db))->broadcastAnnouncement((int) $actor['id']);

        self::assertSame(2, $count, 'only the two active non-actor members are notified');

        // Each active non-actor member gets exactly one row…
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $alice['id']],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $bob['id']],
        ));
        // …the actor and inactive accounts get none.
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id IN (?, ?, ?)",
            [(int) $actor['id'], (int) $banned['id'], (int) $suspended['id']],
        ));

        // Rows carry no body/thread — they signal "see the banner".
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND (thread_id IS NOT NULL OR post_id IS NOT NULL)",
        ));
    }
}
