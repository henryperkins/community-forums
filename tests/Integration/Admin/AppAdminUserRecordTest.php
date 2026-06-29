<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

final class AppAdminUserRecordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist so the first-run setup gate is satisfied; otherwise
        // every request (guest or non-admin) is shadowed by a /setup redirect
        // before it can reach the real auth checks. Matches the sibling admin
        // tests' pattern (e.g. AppUserSettingsTest).
        $this->makeAdmin();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $user = $this->makeUser(['username' => 'subject0']);
        $this->assertRedirectContains($this->get('/admin/users/' . (int) $user['id']), '/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(['username' => 'plainuser']));
        $user = $this->makeUser(['username' => 'subject1']);
        $this->assertStatus(403, $this->get('/admin/users/' . (int) $user['id']));
    }

    public function test_missing_subject_is_404(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/users/999999'));
    }

    public function test_directory_lists_a_user(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->makeUser(['username' => 'listedperson']);
        self::assertStringContainsString('listedperson', $this->get('/admin/users')->body());
    }

    public function test_admin_grants_manual_badge_visible_and_notified(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'grantadmin']));
        $sub = $this->makeUser(['username' => 'badgeme']);
        $sid = (int) $sub['id'];

        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff', 'reason' => 'core team']),
            '/admin/users/' . $sid,
        );

        self::assertStringContainsString('Staff', $this->get('/u/badgeme')->body()); // visible on the profile
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'badge'",
            [$sid],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.grant' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_grant_auto_slug_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noauto'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'welcome']));
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug($sid, 'welcome'));
    }

    public function test_revoke_auto_slug_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noauto2'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/badges/revoke', ['slug' => 'welcome']));
    }

    public function test_revoke_clears_held_manual_badge(): void
    {
        $this->actingAs($this->makeAdmin());
        $sub = $this->makeUser(['username' => 'revoke_me']);
        $sid = (int) $sub['id'];
        (new BadgeRepository($this->db))->awardBySlug($sid, 'staff');

        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/badges/revoke', ['slug' => 'staff']),
            '/admin/users/' . $sid,
        );
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug($sid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.revoke' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_title_override_wins_then_clear_reverts_to_derived(): void
    {
        $this->actingAs($this->makeAdmin());
        $sub = $this->makeUser(['username' => 'titled']);
        $sid = (int) $sub['id'];
        $this->db->run('UPDATE users SET reputation = 60 WHERE id = ?', [$sid]); // derived ladder = 'Regular'

        $this->post('/admin/users/' . $sid . '/title', ['title' => 'Grand Poobah']);
        self::assertStringContainsString('Grand Poobah', $this->get('/u/titled')->body());

        $this->post('/admin/users/' . $sid . '/title', ['title' => '']);
        $profile = $this->get('/u/titled')->body();
        self::assertStringNotContainsString('Grand Poobah', $profile);
        self::assertStringContainsString('Regular', $profile);

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_title' AND target_id = ?",
            [$sid],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_title' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_title_over_64_chars_is_422_and_preserves_typed_text(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'longtitle'])['id'];
        $long = str_repeat('x', 65);
        $res = $this->post('/admin/users/' . $sid . '/title', ['title' => $long]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString($long, $res->body()); // anti-draft-loss: typed text re-rendered
    }

    public function test_csrf_rejected_without_token(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'csrfsub'])['id'];
        $this->assertStatus(403, $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff'], withToken: false));
    }

    public function test_grant_does_not_change_reputation(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'repsub'])['id'];
        $before = (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$sid]);
        $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff']);
        self::assertSame($before, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$sid]));
    }
}
