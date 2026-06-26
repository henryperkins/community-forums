<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use Tests\Support\TestCase;

/**
 * Privacy-respecting presence (P2-11): heartbeat recording, and a roster that
 * never exposes a hidden user, a stale user, the viewer, or a blocked member.
 */
final class AppPresenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function setPresence(array $user, string $lastSeen, int $show): void
    {
        $this->db->run('UPDATE users SET last_seen_at = ?, show_presence = ? WHERE id = ?', [$lastSeen, $show, (int) $user['id']]);
    }

    public function test_heartbeat_records_last_seen_for_signed_in_user(): void
    {
        $user = $this->makeUser(['username' => 'beat']);
        self::assertNull($this->users()->find((int) $user['id'])['last_seen_at']);

        $this->actingAs($user);
        $this->get('/');

        self::assertNotNull($this->users()->find((int) $user['id'])['last_seen_at']);
    }

    public function test_roster_lists_only_visible_online_members(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $old = gmdate('Y-m-d H:i:s', time() - 1000);

        $viewer = $this->makeUser(['username' => 'rosterviewer']);
        $online = $this->makeUser(['username' => 'onlinejoe']);
        $hidden = $this->makeUser(['username' => 'hiddenkate']);
        $stale = $this->makeUser(['username' => 'stalemax']);

        $this->setPresence($online, $now, 1);
        $this->setPresence($hidden, $now, 0);   // presence disabled
        $this->setPresence($stale, $old, 1);    // outside the online window

        $this->actingAs($viewer);
        $res = $this->get('/presence');
        $this->assertStatus(200, $res);

        $this->assertSeeText($res, 'onlinejoe');
        $this->assertDontSeeText($res, 'hiddenkate');   // show_presence = 0
        $this->assertDontSeeText($res, 'stalemax');     // not seen recently
        $this->assertDontSeeText($res, 'rosterviewer'); // self excluded
    }

    public function test_blocked_member_is_excluded_from_roster(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $viewer = $this->makeUser(['username' => 'blockowner']);
        $foe = $this->makeUser(['username' => 'foepresence']);
        $this->setPresence($foe, $now, 1);
        (new BlockRepository($this->db))->block((int) $viewer['id'], (int) $foe['id']);

        $this->actingAs($viewer);
        $res = $this->get('/presence');
        $this->assertDontSeeText($res, 'foepresence');
    }

    public function test_presence_requires_login(): void
    {
        $this->assertRedirectContains($this->get('/presence'), '/login');
    }
}
