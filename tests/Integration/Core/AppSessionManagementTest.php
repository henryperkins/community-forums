<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SessionRepository;
use Tests\Support\TestCase;

/**
 * Active sessions & devices (P2-10): list, revoke one, and "log out everywhere
 * else", with current-session vs other-session behaviour kept distinct.
 */
final class AppSessionManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** Seed an extra (other-device) session row and return its sessions.id (hash). */
    private function seedSession(int $userId, string $ua = 'OtherDevice'): string
    {
        $raw = bin2hex(random_bytes(32));
        $id = hash('sha256', $raw);
        (new SessionRepository($this->db))->create([
            'id' => $id,
            'user_id' => $userId,
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => $ua,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        return $id;
    }

    public function test_lists_sessions_and_marks_current(): void
    {
        $user = $this->makeUser(['username' => 'devices']);
        $this->actingAs($user);
        $this->seedSession((int) $user['id'], 'PhoneSafari');

        $res = $this->get('/settings/sessions');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'This device');     // current session marker
        $this->assertSeeText($res, 'PhoneSafari');      // the other device
    }

    public function test_revoke_one_session(): void
    {
        $user = $this->makeUser(['username' => 'revoker']);
        $this->actingAs($user);
        $other = $this->seedSession((int) $user['id']);

        $sessions = new SessionRepository($this->db);
        self::assertNotNull($sessions->findActive($other));

        $res = $this->post('/settings/sessions/revoke', ['sid' => $other]);
        $this->assertRedirect($res, '/settings/sessions');
        self::assertNull($sessions->findActive($other));
    }

    public function test_cannot_revoke_another_users_session(): void
    {
        $user = $this->makeUser(['username' => 'mallory']);
        $victim = $this->makeUser(['username' => 'victim']);
        $victimSession = $this->seedSession((int) $victim['id']);

        $this->actingAs($user);
        $this->post('/settings/sessions/revoke', ['sid' => $victimSession]);

        // The victim's session is untouched — revoke is user-scoped.
        self::assertNotNull((new SessionRepository($this->db))->findActive($victimSession));
    }

    public function test_log_out_everywhere_else_keeps_current(): void
    {
        $user = $this->makeUser(['username' => 'everywhere']);
        $this->actingAs($user);
        $a = $this->seedSession((int) $user['id'], 'A');
        $b = $this->seedSession((int) $user['id'], 'B');

        $this->post('/settings/sessions/revoke-others');

        $sessions = new SessionRepository($this->db);
        self::assertNull($sessions->findActive($a));
        self::assertNull($sessions->findActive($b));

        // The current session still works (the client can still load a gated page).
        $this->assertStatus(200, $this->get('/settings/sessions'));
    }
}
