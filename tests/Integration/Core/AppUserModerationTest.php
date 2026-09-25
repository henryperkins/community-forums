<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * User moderation records (P2-08): suspend/ban/lift (admin), warn/note (staff),
 * the bans system-of-record, write-gate integration, and self/admin protection.
 */
final class AppUserModerationTest extends TestCase
{
    /** @var array<string,mixed> */ private array $admin;
    /** @var array<string,mixed> */ private array $bad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
        $this->bad = $this->makeUser(['username' => 'baduser']);
    }

    private function userStatus(int $id): string
    {
        return (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$id]);
    }

    public function test_lift_during_pending_deletion_keeps_normal_writes_blocked(): void
    {
        $this->actingAs($this->bad);
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/ban', ['reason' => 'Restriction during grace']));
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/lift'));
        self::assertSame('pending_deletion', $this->userStatus((int) $this->bad['id']));
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
        $this->assertStatus(303, $this->post('/settings/account/delete/cancel'));
    }

    public function test_lift_cannot_reactivate_self_deactivated_or_deleted_accounts(): void
    {
        $this->actingAs($this->admin);
        foreach (['deactivated', 'deleted'] as $status) {
            $this->users()->setStatus((int) $this->bad['id'], $status, null);
            $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/lift'));
            self::assertSame($status, $this->userStatus((int) $this->bad['id']));
        }
    }

    public function test_new_timed_suspension_cannot_replace_an_existing_full_site_ban(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/ban', ['reason' => 'Full ban']));
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', ['reason' => 'Additional suspension', 'until' => '2030-01-01 00:00:00']));
        self::assertSame('banned', $this->userStatus((int) $this->bad['id']), 'Full-ban cache precedence also governs queued recipient eligibility.');
        $this->db->run("UPDATE users SET suspended_until = '2020-01-01 00:00:00' WHERE id = ?", [$this->bad['id']]);
        $this->db->run("UPDATE bans SET expires_at = '2020-01-01 00:00:00' WHERE user_id = ? AND type = 'post'", [$this->bad['id']]);
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Full ban still applies']));
    }

    public function test_suspend_reconciles_a_live_full_ban_even_when_cached_status_is_active(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/ban', ['reason' => 'Full ban']));
        $this->users()->setStatus((int) $this->bad['id'], 'active', null);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', ['reason' => 'Additional suspension', 'until' => '2030-01-01 00:00:00']));
        self::assertSame('banned', $this->userStatus((int) $this->bad['id']));
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Full ban still applies']));
    }

    public function test_indefinite_suspension_survives_a_shorter_second_suspension(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', ['reason' => 'Indefinite site hold']));
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'One-hour follow-up',
            'until' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]));
        self::assertNull(
            $this->users()->find((int) $this->bad['id'])['suspended_until'],
            'The active indefinite restriction must remain the write-gate fast path.',
        );

        // An already elapsed follow-up stands in for the hour passing; the
        // original indefinite restriction must still deny a member write.
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'Expired follow-up', 'until' => '2020-01-01 00:00:00',
        ]));
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still suspended']));
    }

    public function test_shorter_suspension_cannot_shorten_a_live_finite_hold(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'Long hold', 'until' => '2032-01-01 00:00:00',
        ]));
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'Short follow-up', 'until' => '2020-01-01 00:00:00',
        ]));
        self::assertSame('2032-01-01 00:00:00', $this->users()->find((int) $this->bad['id'])['suspended_until']);
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still suspended']));
    }

    public function test_longer_suspension_extends_the_fast_path_and_lift_releases_all_overlaps(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'First hold', 'until' => '2030-01-01 00:00:00',
        ]));
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'Extended hold', 'until' => '2032-01-01 00:00:00',
        ]));
        self::assertSame('2032-01-01 00:00:00', $this->users()->find((int) $this->bad['id'])['suspended_until']);
        $this->assertStatus(303, $this->post('/mod/u/' . $this->bad['id'] . '/lift'));
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $this->assertStatus(303, $this->post('/settings/account', ['display_name' => 'Lifted account']));
    }

    public function testAdminSuspendThenLift(): void
    {
        $this->actingAs($this->admin);
        $this->post('/mod/u/' . $this->bad['id'] . '/suspend', ['reason' => 'cooling off', 'until' => '2030-01-01 00:00:00']);

        self::assertSame('suspended', $this->userStatus((int) $this->bad['id']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM bans WHERE user_id = ? AND scope = 'site'", [(int) $this->bad['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'suspend' AND target_id = ?", [(int) $this->bad['id']]));

        $this->post('/mod/u/' . $this->bad['id'] . '/lift');
        self::assertSame('active', $this->userStatus((int) $this->bad['id']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM bans WHERE user_id = ? AND lifted_at IS NULL', [(int) $this->bad['id']]));
    }

    public function testBannedUserIsWriteGated(): void
    {
        $this->actingAs($this->admin);
        $this->post('/mod/u/' . $this->bad['id'] . '/ban', ['reason' => 'abuse']);
        self::assertSame('banned', $this->userStatus((int) $this->bad['id']));

        // The banned user can no longer create content (write gate reads users.status).
        $board = $this->makeBoard($this->makeCategory());
        $this->actingAs($this->users()->find((int) $this->bad['id']));
        $r = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'nope', 'body' => 'nope']);
        $this->assertStatus(403, $r);
    }

    public function testCannotSuspendSelfOrAnotherAdmin(): void
    {
        $this->actingAs($this->admin);
        // Self → validation error: the staff panel re-renders at 422 with the
        // message inline (no draft-dropping redirect), no status change.
        $self = $this->post('/mod/u/' . $this->admin['id'] . '/suspend', ['reason' => 'x']);
        $this->assertStatus(422, $self);
        $this->assertSeeText($self, 'You cannot moderate your own account.');
        self::assertSame('active', $this->userStatus((int) $this->admin['id']));

        // Another admin → forbidden.
        $admin2 = $this->makeAdmin(['username' => 'admin2']);
        $this->assertStatus(403, $this->post('/mod/u/' . $admin2['id'] . '/ban', ['reason' => 'x']));
        self::assertSame('active', $this->userStatus((int) $admin2['id']));
    }

    public function testBoardModeratorCanWarnButNotSuspend(): void
    {
        // Scoped-behavior update (PR #44, spec §2): a moderator warn requires
        // the subject to have participated in the mod's board, and a board_id
        // attribution inside that overlap.
        $board = $this->makeBoard($this->makeCategory());
        $modA = $this->makeUser(['username' => 'moda']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modA['id']);
        $this->makeThread($board, $this->bad, 'Warned topic', 'Warned body.');

        $this->actingAs($modA);
        $this->post('/mod/u/' . $this->bad['id'] . '/warn', ['reason' => 'mind the rules', 'board_id' => (string) (int) $board['id']]);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [(int) $this->bad['id']]));

        // Suspend is admin-only.
        $this->assertStatus(403, $this->post('/mod/u/' . $this->bad['id'] . '/suspend', ['reason' => 'no']));
        self::assertSame('active', $this->userStatus((int) $this->bad['id']));
    }

    /**
     * Audit 2026-07-17 N3 (prior #41): a ValidationException on /mod/u/* must
     * re-render the staff panel (/mod/u/{id}) at 422 with the error inline and
     * the typed input preserved — the anti-draft-loss pattern the
     * /admin/users/{id} record already follows — never a flash redirect that
     * drops the input.
     */
    public function testWarnValidationRerendersPanelNotRedirect(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $modA = $this->makeUser(['username' => 'moda']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modA['id']);
        // Scoped panel admission (PR #44, spec §2): the 422 re-render is the
        // scoped panel, so the subject must participate in the mod's board.
        $this->makeThread($board, $this->bad, 'Rerender topic', 'Rerender body.');

        $this->actingAs($modA);
        $r = $this->post('/mod/u/' . $this->bad['id'] . '/warn', ['reason' => '   ']);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'A reason is required.');
        // The 422 body is the staff panel, carrying a retry form back to
        // the same action so the flow recovers without retyping.
        $this->assertSeeText($r, '@baduser');
        $this->assertSeeText($r, '/mod/u/' . $this->bad['id'] . '/warn');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [(int) $this->bad['id']]));
    }

    public function testSuspendValidationKeepsTypedReason(): void
    {
        $this->actingAs($this->admin);
        $r = $this->post('/mod/u/' . $this->bad['id'] . '/suspend', [
            'reason' => 'Repeated harassment third strike',
            'until' => 'not-a-timestamp',
        ]);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'Use a valid UTC timestamp');
        $this->assertSeeText($r, 'Repeated harassment third strike');
        self::assertSame('active', $this->userStatus((int) $this->bad['id']));
    }

    public function testNoteValidationRerendersWithError(): void
    {
        $this->actingAs($this->admin);
        $r = $this->post('/mod/u/' . $this->bad['id'] . '/note', ['body' => '']);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'A note cannot be empty.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes WHERE subject_user_id = ?', [(int) $this->bad['id']]));
    }

    public function testPlainMemberCannotWarn(): void
    {
        $this->actingAs($this->makeUser(['username' => 'nobody']));
        $this->assertStatus(403, $this->post('/mod/u/' . $this->bad['id'] . '/warn', ['reason' => 'x']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [(int) $this->bad['id']]));
    }
}
