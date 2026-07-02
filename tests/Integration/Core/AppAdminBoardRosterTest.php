<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * Admin board-roster UI (P2-08 operator controls): an admin assigns/removes
 * scoped board moderators and private/hidden-board members from the board edit
 * page. Asserts the data-layer write, the audit row, the access/scope effect of
 * each action end-to-end, the validation guards, and the admin-only gate.
 */
final class AppAdminBoardRosterTest extends TestCase
{
    /** @var array<string,mixed> */ private array $admin;
    /** @var array<string,mixed> */ private array $alice;
    /** @var array<string,mixed> */ private array $board;
    /** @var array<string,mixed> */ private array $privateBoard;
    /** @var array{thread_id:int,slug:string} */ private array $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $category = $this->makeCategory();
        $this->admin = $this->makeAdmin();
        $this->alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice A']);
        $this->board = $this->makeBoard($category, ['slug' => 'general', 'name' => 'General']);
        $this->privateBoard = $this->makeBoard($category, ['slug' => 'secret', 'name' => 'Secret', 'visibility' => 'private']);
        $this->thread = $this->makeThread($this->board, $this->admin, 'Pin me', 'body');
    }

    private function boardMods(): BoardModeratorRepository
    {
        return new BoardModeratorRepository($this->db);
    }

    private function boardMembers(): BoardMemberRepository
    {
        return new BoardMemberRepository($this->db);
    }

    private function auditCount(string $action, ?int $boardId = null): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM moderation_log WHERE action = ? AND target_type = ? AND target_id = ?',
            [$action, 'board', $boardId ?? (int) $this->board['id']],
        );
    }

    // ---- moderators -------------------------------------------------------

    public function test_admin_assigns_a_moderator_and_the_scope_takes_effect(): void
    {
        $this->actingAs($this->admin);
        $this->assertRedirect(
            $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => 'alice']),
            '/admin/boards/' . $this->board['id'] . '/edit',
        );

        self::assertTrue($this->boardMods()->isModerator((int) $this->board['id'], (int) $this->alice['id']));
        self::assertSame(1, $this->auditCount('assign_moderator'));

        // The assignment actually grants board-scoped moderation: Alice can now pin.
        $this->actingAs($this->alice);
        $this->assertRedirect($this->post('/mod/t/' . $this->thread['thread_id'] . '/pin'));
        self::assertSame(1, (int) $this->threads()->find($this->thread['thread_id'])['is_pinned']);
    }

    public function test_assigning_an_unknown_username_is_rejected_and_explained(): void
    {
        $this->actingAs($this->admin);
        // A failed assign re-renders the board edit page at 422 (anti-draft-loss),
        // rather than redirecting and dropping the typed username.
        $resp = $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => 'ghost']);
        $this->assertStatus(422, $resp);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_moderators WHERE board_id = ?', [(int) $this->board['id']]));
        self::assertSame(0, $this->auditCount('assign_moderator'));

        // The reason is shown inline on the re-rendered page, and the typed value survives.
        $this->assertSeeText($resp, 'No member found');
        $this->assertSeeText($resp, 'ghost');
    }

    public function test_assigning_an_admin_as_a_board_moderator_is_rejected(): void
    {
        $other = $this->makeAdmin(['username' => 'bossadmin']);
        $this->actingAs($this->admin);
        $resp = $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => 'bossadmin']);
        $this->assertStatus(422, $resp);

        self::assertFalse($this->boardMods()->isModerator((int) $this->board['id'], (int) $other['id']));
        self::assertSame(0, $this->auditCount('assign_moderator'));
        $this->assertSeeText($resp, 'already moderates every board');
    }

    public function test_duplicate_moderator_assignment_is_rejected_without_a_second_row(): void
    {
        $this->boardMods()->assign((int) $this->board['id'], (int) $this->alice['id']);

        $this->actingAs($this->admin);
        $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => 'alice']);

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM board_moderators WHERE board_id = ? AND user_id = ?',
            [(int) $this->board['id'], (int) $this->alice['id']],
        ));
        // No spurious audit row for the rejected duplicate.
        self::assertSame(0, $this->auditCount('assign_moderator'));
    }

    public function test_admin_removes_a_moderator_and_the_scope_is_revoked(): void
    {
        $this->boardMods()->assign((int) $this->board['id'], (int) $this->alice['id']);

        $this->actingAs($this->admin);
        $this->assertRedirect($this->post(
            '/admin/boards/' . $this->board['id'] . '/moderators/remove',
            ['user_id' => (int) $this->alice['id']],
        ));

        self::assertFalse($this->boardMods()->isModerator((int) $this->board['id'], (int) $this->alice['id']));
        self::assertSame(1, $this->auditCount('unassign_moderator'));

        // Scope is gone: Alice can no longer moderate the board.
        $this->actingAs($this->alice);
        $this->assertStatus(403, $this->post('/mod/t/' . $this->thread['thread_id'] . '/pin'));
        self::assertSame(0, (int) $this->threads()->find($this->thread['thread_id'])['is_pinned']);
    }

    // ---- members ----------------------------------------------------------

    public function test_admin_adds_a_member_granting_private_board_access_then_removes_it(): void
    {
        $this->actingAs($this->admin);
        $this->assertRedirect(
            $this->post('/admin/boards/' . $this->privateBoard['id'] . '/members', ['username' => 'alice']),
            '/admin/boards/' . $this->privateBoard['id'] . '/edit',
        );
        self::assertTrue($this->boardMembers()->isMember((int) $this->privateBoard['id'], (int) $this->alice['id']));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'add_member' AND target_id = ?",
            [(int) $this->privateBoard['id']],
        ));

        // Granted: Alice can now read the private board over HTTP.
        $this->actingAs($this->alice);
        $this->assertStatus(200, $this->get('/c/secret'));

        // Revoked: removal immediately closes access again.
        $this->actingAs($this->admin);
        $this->assertRedirect($this->post(
            '/admin/boards/' . $this->privateBoard['id'] . '/members/remove',
            ['user_id' => (int) $this->alice['id']],
        ));
        self::assertFalse($this->boardMembers()->isMember((int) $this->privateBoard['id'], (int) $this->alice['id']));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'remove_member' AND target_id = ?",
            [(int) $this->privateBoard['id']],
        ));

        $this->actingAs($this->alice);
        $this->assertStatus(404, $this->get('/c/secret'));
    }

    public function test_adding_an_unknown_member_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $resp = $this->post('/admin/boards/' . $this->privateBoard['id'] . '/members', ['username' => 'nobody']);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'No member found');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_members WHERE board_id = ?', [(int) $this->privateBoard['id']]));
        self::assertSame(0, $this->auditCount('add_member', (int) $this->privateBoard['id']));
    }

    public function test_duplicate_member_is_rejected_without_a_second_row(): void
    {
        $this->boardMembers()->add((int) $this->privateBoard['id'], (int) $this->alice['id'], (int) $this->admin['id']);

        $this->actingAs($this->admin);
        $this->post('/admin/boards/' . $this->privateBoard['id'] . '/members', ['username' => 'alice']);

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM board_members WHERE board_id = ? AND user_id = ?',
            [(int) $this->privateBoard['id'], (int) $this->alice['id']],
        ));
        self::assertSame(0, $this->auditCount('add_member', (int) $this->privateBoard['id']));
    }

    public function test_unassigning_a_non_moderator_is_rejected_without_audit(): void
    {
        // Alice was never assigned to this board.
        $this->actingAs($this->admin);
        $resp = $this->post('/admin/boards/' . $this->board['id'] . '/moderators/remove', ['user_id' => (int) $this->alice['id']]);
        $this->assertStatus(422, $resp);
        self::assertSame(0, $this->auditCount('unassign_moderator'));
        $this->assertSeeText($resp, 'does not moderate this board');
    }

    public function test_removing_a_non_member_is_rejected_without_audit(): void
    {
        // Alice was never added to the private board.
        $this->actingAs($this->admin);
        $resp = $this->post('/admin/boards/' . $this->privateBoard['id'] . '/members/remove', ['user_id' => (int) $this->alice['id']]);
        $this->assertStatus(422, $resp);
        self::assertSame(0, $this->auditCount('remove_member', (int) $this->privateBoard['id']));
        $this->assertSeeText($resp, 'not on this board');
    }

    public function test_username_is_normalized_so_an_at_prefix_and_whitespace_resolve(): void
    {
        $this->actingAs($this->admin);
        $this->assertRedirect($this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => '  @alice ']));

        self::assertTrue($this->boardMods()->isModerator((int) $this->board['id'], (int) $this->alice['id']));
        self::assertSame(1, $this->auditCount('assign_moderator'));
    }

    public function test_a_blank_username_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $resp = $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => '   ']);
        $this->assertStatus(422, $resp);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_moderators WHERE board_id = ?', [(int) $this->board['id']]));
        self::assertSame(0, $this->auditCount('assign_moderator'));
        $this->assertSeeText($resp, 'Enter a username');
    }

    public function test_roster_escapes_a_member_display_name(): void
    {
        $evil = $this->makeUser(['username' => 'mallory', 'display_name' => '<script>alert(1)</script>']);
        $this->boardMembers()->add((int) $this->board['id'], (int) $evil['id'], (int) $this->admin['id']);

        $this->actingAs($this->admin);
        $resp = $this->get('/admin/boards/' . $this->board['id'] . '/edit');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, '<script>alert(1)</script>');
        $this->assertSeeText($resp, '&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    // ---- rendering + authorization ---------------------------------------

    public function test_edit_page_lists_current_moderators_and_members(): void
    {
        $bob = $this->makeUser(['username' => 'bob']);
        $this->boardMods()->assign((int) $this->board['id'], (int) $this->alice['id']);
        $this->boardMembers()->add((int) $this->board['id'], (int) $bob['id'], (int) $this->admin['id']);

        $this->actingAs($this->admin);
        $resp = $this->get('/admin/boards/' . $this->board['id'] . '/edit');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Moderators');
        $this->assertSeeText($resp, 'Members');
        $this->assertSeeText($resp, '@alice');
        $this->assertSeeText($resp, '@bob');
    }

    public function test_a_non_admin_cannot_manage_the_roster(): void
    {
        // Even an assigned board moderator (non-admin) is denied — roster management
        // is admin-only, not a board-moderator capability. Covers all four routes,
        // including the destructive /remove ones.
        $this->boardMods()->assign((int) $this->board['id'], (int) $this->alice['id']);
        $this->boardMembers()->add((int) $this->board['id'], (int) $this->alice['id'], (int) $this->admin['id']);
        $this->actingAs($this->alice);

        $this->assertStatus(403, $this->post('/admin/boards/' . $this->board['id'] . '/moderators', ['username' => 'bob']));
        $this->assertStatus(403, $this->post('/admin/boards/' . $this->board['id'] . '/moderators/remove', ['user_id' => (int) $this->alice['id']]));
        $this->assertStatus(403, $this->post('/admin/boards/' . $this->board['id'] . '/members', ['username' => 'bob']));
        $this->assertStatus(403, $this->post('/admin/boards/' . $this->board['id'] . '/members/remove', ['user_id' => (int) $this->alice['id']]));

        // The non-admin's denied requests changed nothing.
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_moderators WHERE board_id = ?', [(int) $this->board['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_members WHERE board_id = ?', [(int) $this->board['id']]));
    }

    public function test_roster_actions_on_a_missing_board_404(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(404, $this->post('/admin/boards/999999/moderators', ['username' => 'alice']));
        $this->assertStatus(404, $this->post('/admin/boards/999999/moderators/remove', ['user_id' => (int) $this->alice['id']]));
        $this->assertStatus(404, $this->post('/admin/boards/999999/members', ['username' => 'alice']));
        $this->assertStatus(404, $this->post('/admin/boards/999999/members/remove', ['user_id' => (int) $this->alice['id']]));
    }
}
