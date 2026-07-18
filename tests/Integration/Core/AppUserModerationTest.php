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
        $board = $this->makeBoard($this->makeCategory());
        $modA = $this->makeUser(['username' => 'moda']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modA['id']);

        $this->actingAs($modA);
        $this->post('/mod/u/' . $this->bad['id'] . '/warn', ['reason' => 'mind the rules']);
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
