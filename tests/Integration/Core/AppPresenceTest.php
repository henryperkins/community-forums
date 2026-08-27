<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\SettingRepository;
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

    public function test_guest_roster_is_public_and_server_rendered(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $online = $this->makeUser(['username' => 'publiconline']);
        $hidden = $this->makeUser(['username' => 'privatepresence']);
        $this->setPresence($online, $now, 1);
        $this->setPresence($hidden, $now, 0);

        $shell = $this->get('/');
        $this->assertStatus(200, $shell);
        self::assertStringContainsString('data-presence', $shell->body());
        $this->assertSeeText($shell, 'publiconline');
        $this->assertDontSeeText($shell, 'privatepresence');

        $json = $this->get('/presence');
        $this->assertStatus(200, $json);
        $this->assertSeeText($json, 'publiconline');
        $this->assertDontSeeText($json, 'privatepresence');

        $directory = $this->get('/users-online');
        $this->assertStatus(200, $directory);
        $this->assertSeeText($directory, 'publiconline');
        $this->assertDontSeeText($directory, 'privatepresence');
    }

    public function test_roster_lists_only_visible_online_members_with_server_json_parity(): void
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
        $shell = $this->get('/');
        $res = $this->get('/presence');
        $this->assertStatus(200, $shell);
        $this->assertStatus(200, $res);
        $serverRoster = $this->presenceMarkup($shell->body());

        self::assertStringContainsString('onlinejoe', $serverRoster);
        $this->assertSeeText($res, 'onlinejoe');
        self::assertStringNotContainsString('hiddenkate', $serverRoster);
        $this->assertDontSeeText($res, 'hiddenkate');   // show_presence = 0
        self::assertStringNotContainsString('stalemax', $serverRoster);
        $this->assertDontSeeText($res, 'stalemax');     // not seen recently
        self::assertStringNotContainsString('rosterviewer', $serverRoster);
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
        $shell = $this->get('/');
        $res = $this->get('/presence');
        $this->assertDontSeeText($shell, 'foepresence');
        $this->assertDontSeeText($res, 'foepresence');
    }

    public function test_presence_feature_dark_hides_shell_and_json_route(): void
    {
        $online = $this->makeUser(['username' => 'darkpresence']);
        $this->setPresence($online, gmdate('Y-m-d H:i:s'), 1);
        (new SettingRepository($this->db))->set('features', ['presence' => false]);

        $shell = $this->get('/');
        $this->assertStatus(200, $shell);
        self::assertStringNotContainsString('data-presence', $shell->body());
        $this->assertDontSeeText($shell, 'darkpresence');
        $this->assertStatus(404, $this->get('/presence'));
        $this->assertStatus(404, $this->get('/users-online'));
    }

    private function presenceMarkup(string $html): string
    {
        self::assertSame(1, preg_match('#<section class="presence-widget".*?</section>#s', $html, $match));
        return $match[0];
    }
}
