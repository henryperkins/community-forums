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

    public function test_directory_filters_by_role_via_get(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->makeUser(['username' => 'plainmember']);
        $this->makeAdmin(['username' => 'anotheradmin']);
        $body = $this->get('/admin/users', ['role' => 'admin'])->body();
        self::assertStringContainsString('anotheradmin', $body);
        self::assertStringNotContainsString('>plainmember<', $body);
    }

    public function test_directory_exposes_sortable_headers_and_bulk_foundation(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->makeUser(['username' => 'sortablesub']);
        $body = $this->get('/admin/users')->body();
        // Sortable header links carry the sort key as a shareable GET URL.
        self::assertStringContainsString('sort=username', $body);
        self::assertStringContainsString('sort=reputation', $body);
        // Bulk selection is wired to the two-step confirm flow (2026-07-18 remediation).
        self::assertStringContainsString('name="selected[]"', $body);
        self::assertStringContainsString('action="/admin/users/bulk"', $body);
        self::assertStringContainsString('name="bulk_action"', $body);
    }

    public function test_directory_filter_values_are_preserved_in_controls(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->makeUser(['username' => 'statefiltersub', 'status' => 'suspended']);
        $body = $this->get('/admin/users', ['status' => 'suspended', 'sort' => 'username', 'direction' => 'asc'])->body();
        // The selected filter is repopulated so the GET URL is shareable + sticky.
        self::assertStringContainsString('value="suspended" selected', $body);
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

    public function test_failed_manual_badge_grant_preserves_typed_reason(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'badgereason'])['id'];

        $res = $this->post('/admin/users/' . $sid . '/badges/grant', [
            'slug' => 'welcome',
            'reason' => 'manual badge reason survives',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('manual badge reason survives', $res->body());
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

    public function test_record_shows_moderation_controls_for_a_normal_user(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'controlsub'])['id'];
        $body = $this->get('/admin/users/' . $sid)->body();
        self::assertStringContainsString('action="/admin/users/' . $sid . '/warn"', $body);
        self::assertStringContainsString('action="/admin/users/' . $sid . '/suspend"', $body);
        self::assertStringContainsString('action="/admin/users/' . $sid . '/ban"', $body);
    }

    public function test_admin_cannot_see_suspend_ban_controls_on_own_record(): void
    {
        $admin = $this->makeAdmin(['username' => 'selfadmin']);
        $this->actingAs($admin);
        $body = $this->get('/admin/users/' . (int) $admin['id'])->body();
        self::assertStringContainsString('cannot suspend or ban your own account', $body);
        self::assertStringNotContainsString('action="/admin/users/' . (int) $admin['id'] . '/ban"', $body);
    }

    public function test_warn_records_warning_and_returns_to_admin_record(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'warnme'])['id'];
        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/warn', ['reason' => 'mind the rules']),
            '/admin/users/' . $sid,
        );
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [$sid]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'warn' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_empty_warn_reason_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'warnempty'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/warn', ['reason' => '']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [$sid]));
    }

    public function test_note_adds_private_note_and_returns_to_admin_record(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noteme'])['id'];
        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/note', ['body' => 'watch this account']),
            '/admin/users/' . $sid,
        );
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes WHERE subject_user_id = ?', [$sid]));
    }

    public function test_empty_note_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noteempty'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/note', ['body' => '   ']));
    }

    public function test_suspend_sets_status_and_returns_to_admin_record(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'suspendme'])['id'];
        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/suspend', ['reason' => 'cooling off', 'until' => '2030-01-01 00:00:00']),
            '/admin/users/' . $sid,
        );
        self::assertSame('suspended', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
    }

    public function test_suspend_without_reason_is_422_and_preserves_until(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'suspendbad'])['id'];
        $res = $this->post('/admin/users/' . $sid . '/suspend', ['reason' => '', 'until' => '2031-05-05 12:00:00']);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('2031-05-05 12:00:00', $res->body()); // anti-draft-loss
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
    }

    public function test_suspend_with_invalid_until_is_422_and_preserves_reason(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'suspenddatebad'])['id'];
        $res = $this->post('/admin/users/' . $sid . '/suspend', ['reason' => 'typed justification', 'until' => '2026-13-99']);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('typed justification', $res->body());
        self::assertStringContainsString('2026-13-99', $res->body());
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
    }

    public function test_ban_then_lift_returns_to_admin_record(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'banme'])['id'];
        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/ban', ['reason' => 'abuse', 'confirm_username' => 'banme']),
            '/admin/users/' . $sid,
        );
        self::assertSame('banned', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));

        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/lift', []),
            '/admin/users/' . $sid,
        );
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = $this->makeAdmin(['username' => 'selfsuspend']);
        $this->actingAs($admin);
        $this->assertStatus(422, $this->post('/admin/users/' . (int) $admin['id'] . '/suspend', ['reason' => 'x']));
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $admin['id']]));
    }

    public function test_admin_cannot_ban_another_admin(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'banneradmin']));
        $other = $this->makeAdmin(['username' => 'targetadmin']);
        // Correct typed confirmation so the request reaches the peer-admin rule
        // (the confirmation 422 would otherwise mask the 403).
        $this->assertStatus(403, $this->post('/admin/users/' . (int) $other['id'] . '/ban', ['reason' => 'x', 'confirm_username' => 'targetadmin']));
        self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [(int) $other['id']]));
    }
}
