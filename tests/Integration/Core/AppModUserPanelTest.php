<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * The /mod/u/{id} staff panel (ADMIN §3.4 warn/note for board moderators) —
 * 2026-07-18 remediation. Before this panel existed the /mod/u/* POST routes
 * had no UI at all and validation failures flashed the typed reason away.
 */
final class AppModUserPanelTest extends TestCase
{
    /** @return array{mod:array<string,mixed>,subject:array<string,mixed>,admin:array<string,mixed>,board:array<string,mixed>} */
    private function seedScopedModerator(): array
    {
        // An admin must exist or the first-run setup gate (SetupService::
        // isInitialized = adminCount > 0) redirects every request to /setup.
        $admin = $this->makeAdmin(['username' => 'panelroot']);
        $category = $this->makeCategory('Panel');
        $board = $this->makeBoard($category);
        $mod = $this->makeUser(['username' => 'panelmod', 'password' => 'password123']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $subject = $this->makeUser(['username' => 'panelsubject']);
        // Scoped-behavior update (PR #44, spec §2): panel admission for a
        // non-admin moderator now requires the subject to have participated in
        // a board they moderate — seed that participation.
        $this->makeThread($board, $subject, 'Panel fixture topic', 'Panel fixture body.');
        return ['mod' => $mod, 'subject' => $subject, 'admin' => $admin, 'board' => $board];
    }

    public function test_board_moderator_can_open_the_panel(): void
    {
        $seed = $this->seedScopedModerator();
        $this->actingAs($seed['mod']);

        $res = $this->get('/mod/u/' . (int) $seed['subject']['id']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '@panelsubject');
        $this->assertSeeText($res, 'Issue a warning');
        // The reduced record never exposes PII or role controls (ADMIN §5.1).
        $this->assertDontSeeText($res, (string) $seed['subject']['email']);
        $this->assertDontSeeText($res, '/admin/users/');
    }

    public function test_warn_failure_rerenders_the_panel_with_the_typed_input(): void
    {
        $seed = $this->seedScopedModerator();
        $this->actingAs($seed['mod']);

        // Empty reason → the service refuses; the panel re-renders at 422 with
        // the error visible instead of a flash redirect that drops context.
        $res = $this->post('/mod/u/' . (int) $seed['subject']['id'] . '/warn', ['reason' => '   ']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'A reason is required.');
        self::assertMatchesRegularExpression('/name="reason"[^>]*value="   "/', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings'));
    }

    public function test_note_failure_preserves_nothing_lost_and_success_lands_back_on_panel(): void
    {
        // Scoped-behavior update (PR #44, spec §2): notes are admin-only now
        // (mods get 403 — covered in AppModUserPanelScopeTest), so the
        // anti-draft-loss contract for the note form is exercised as an admin.
        $seed = $this->seedScopedModerator();
        $this->actingAs($seed['admin']);

        $fail = $this->post('/mod/u/' . (int) $seed['subject']['id'] . '/note', ['body' => '']);
        $this->assertStatus(422, $fail);
        $this->assertSeeText($fail, 'A note cannot be empty.');

        $ok = $this->post('/mod/u/' . (int) $seed['subject']['id'] . '/note', ['body' => 'Keeps escalating in #general.']);
        $this->assertRedirectContains($ok, '/mod/u/' . (int) $seed['subject']['id']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes'));
    }

    public function test_regular_member_gets_403_and_guest_is_bounced_to_login(): void
    {
        $this->makeAdmin(); // past the first-run setup gate
        $subject = $this->makeUser();

        $this->assertRedirectContains($this->get('/mod/u/' . (int) $subject['id']), '/login');

        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/mod/u/' . (int) $subject['id']));
    }

    public function test_admin_panel_links_to_the_full_record(): void
    {
        $subject = $this->makeUser();
        $this->actingAs($this->makeAdmin());

        $res = $this->get('/mod/u/' . (int) $subject['id']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '/admin/users/' . (int) $subject['id']);
    }

    public function test_reports_queue_links_to_the_author_panel(): void
    {
        $admin = $this->makeAdmin(); // before the report POST: setup gate needs an admin
        $category = $this->makeCategory('Queue');
        $board = $this->makeBoard($category);
        $author = $this->makeUser(['username' => 'reportedauthor']);
        $made = $this->makeThread($board, $author, 'Reported topic', 'Offending body.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$made['thread_id']]);
        $reporter = $this->makeUser();
        $this->actingAs($reporter);
        $this->post('/posts/' . $postId . '/report', ['reason_code' => 'spam', 'reason' => 'spammy']);

        $this->actingAs($admin);
        $queue = $this->get('/mod/reports');
        $this->assertStatus(200, $queue);
        $this->assertSeeText($queue, '/mod/u/' . (int) $author['id']);
        $this->assertSeeText($queue, 'Warn author');
    }
}
