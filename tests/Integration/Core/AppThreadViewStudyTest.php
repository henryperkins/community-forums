<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

final class AppThreadViewStudyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_post_titles_use_the_real_title_service_and_stay_hidden_for_anonymous_posts(): void
    {
        $author = $this->makeUser([
            'username' => 'study_title_author',
        ]);
        $anonymous = $this->makeUser([
            'username' => 'study_hidden_title',
        ]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Archivist', 5, $author['id']]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Secret Warden', 1000, $anonymous['id']]);
        $board = $this->makeBoard($this->makeCategory('Study Titles'), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $author, 'Titles remain cosmetic', 'Opening record.');
        $this->posting()->reply($this->userEntity($anonymous), (int) $thread['thread_id'], [
            'body' => 'A masked contribution.',
            'is_anonymous' => 1,
        ]);

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-author-title="Archivist"', $page->body());
        self::assertStringNotContainsString('Secret Warden', $page->body());
        self::assertStringNotContainsString('data-author-title="Legend"', $page->body());
    }

    public function test_guest_keeps_the_public_ledger_without_write_tools(): void
    {
        $author = $this->makeUser(['username' => 'study_guest_author']);
        $board = $this->makeBoard($this->makeCategory('Study Guest'));
        $thread = $this->makeThread($board, $author, 'The public ledger remains', 'Opening record.');

        $this->actingAs($author);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/status', [
            'status' => 'needs_answer',
            'reason' => 'Awaiting counsel',
        ]));
        $this->logoutClient();

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-thread-study', $page->body());
        self::assertStringContainsString('data-thread-status="needs_answer"', $page->body());
        self::assertStringContainsString('data-thread-status-history', $page->body());
        self::assertStringContainsString('Awaiting counsel', $page->body());
        self::assertStringContainsString('study_guest_author ·', $page->body());
        self::assertStringNotContainsString('data-topic-tools-open', $page->body());
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/status"', $page->body());
        self::assertStringNotContainsString('class="workflow-bar', $page->body());
    }

    public function test_guest_status_history_masks_anonymous_op_actor_identity(): void
    {
        $author = $this->makeUser([
            'username' => 'study_hidden_status_actor',
            'display_name' => 'Hidden Status Actor',
        ]);
        $board = $this->makeBoard($this->makeCategory('Study Private Ledger'), ['allow_anonymous' => 1]);
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'],
            'title' => 'Anonymous standing remains private',
            'body' => 'An anonymous opening record.',
            'is_anonymous' => 1,
        ]);

        $this->actingAs($author);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/status', [
            'status' => 'needs_answer',
            'reason' => 'Privacy-safe ledger actor',
        ]));
        $this->logoutClient();

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        self::assertSame(1, substr_count($page->body(), 'data-thread-status-history'));
        self::assertStringContainsString('Anonymous ·', $page->body());
        self::assertStringNotContainsString('study_hidden_status_actor', $page->body());
        self::assertStringNotContainsString('Hidden Status Actor', $page->body());
    }

    public function test_member_gets_basic_topic_tools_but_no_moderation_forms(): void
    {
        $author = $this->makeUser(['username' => 'study_member_author']);
        $viewer = $this->makeUser(['username' => 'study_member_viewer']);
        $board = $this->makeBoard($this->makeCategory('Study Member'));
        $thread = $this->makeThread($board, $author, 'Member tools are scoped', 'Opening record.');

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-topic-tools-open', $page->body());
        self::assertStringContainsString('data-topic-tools', $page->body());
        self::assertStringContainsString('icon-eight-point-star', $page->body());
        self::assertStringContainsString('<path d="M50 6 L59 41 L94 50 L59 59 L50 94 L41 59 L6 50 L41 41 Z"/>', $page->body());
        self::assertStringContainsString('icon-x', $page->body());
        self::assertStringContainsString('data-topic-tools-section="watch"', $page->body());
        self::assertStringContainsString('data-topic-tools-section="standing"', $page->body());
        self::assertStringContainsString('action="/t/' . $thread['thread_id'] . '/snooze"', $page->body());
        self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/pin"', $page->body());
    }

    public function test_workflow_open_and_solved_each_render_one_canonical_chip(): void
    {
        $op = $this->makeUser(['username' => 'study_status_op']);
        $answerer = $this->makeUser(['username' => 'study_status_answerer']);
        $board = $this->makeBoard($this->makeCategory('Study Status'));
        $thread = $this->makeThread($board, $op, 'One status at a time', 'Opening record.');

        $open = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertSame(1, substr_count($open->body(), 'data-thread-status="open"'));

        $answerId = $this->posting()->reply($this->userEntity($answerer), (int) $thread['thread_id'], ['body' => 'The answer.']);
        $this->actingAs($op);
        $this->assertRedirect($this->post('/posts/' . $answerId . '/accept'));
        $solved = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertSame(1, substr_count($solved->body(), 'data-thread-status="solved"'));
    }

    public function test_suspended_privileged_reader_gets_no_write_gated_thread_controls_in_default_gate_mode(): void
    {
        $suspended = $this->makeAdmin([
            'username' => 'study_suspended_admin',
            'status' => 'suspended',
            'suspended_until' => '2099-01-01 00:00:00',
        ]);
        $author = $this->makeUser(['username' => 'study_active_author']);
        $board = $this->makeBoard($this->makeCategory('Study Suspension'));
        $thread = $this->makeThread($board, $author, 'State beats role', 'Opening record.');
        $answer = $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'Candidate answer.']);

        $this->actingAs($suspended);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $html = $page->body();

        self::assertStringContainsString('Your account cannot post right now.', $html);
        self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/pin"', $html);
        self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/lock"', $html);
        self::assertStringNotContainsString('action="/posts/' . $answer . '/accept"', $html);
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/tags"', $html);
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/snooze"', $html);
        self::assertStringNotContainsString('data-topic-tools-section="memory"', $html);
        self::assertStringNotContainsString('data-topic-tools-section="management"', $html);
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/summary"', $html);
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/related"', $html);
        self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/split"', $html);
        self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/merge"', $html);
    }

    public function test_moderation_and_memory_forms_render_once_inside_scoped_tools(): void
    {
        $admin = $this->makeAdmin(['username' => 'study_tools_admin']);
        $author = $this->makeUser(['username' => 'study_tools_author']);
        $board = $this->makeBoard($this->makeCategory('Study Management'));
        $this->db->run('UPDATE boards SET assignment_mode = ?, wiki_enabled = 1 WHERE id = ?', ['staff', $board['id']]);
        $thread = $this->makeThread($board, $author, 'Management stays scoped', 'Opening record.');
        $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'Movable reply.']);

        $this->actingAs($admin);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $html = $page->body();

        self::assertStringContainsString('data-topic-tools-section="memory"', $html);
        self::assertStringContainsString('data-topic-tools-section="management"', $html);
        self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/pin"'));
        self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/lock"'));
        self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/split"'));
        self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/merge"'));
        self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/assign"'));
        self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/poll"'));
        self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/summary"'));
        self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/summary/refresh"'));
        self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/related"'));
        self::assertStringNotContainsString('class="workflow-actions', $html);
    }

    public function test_post_toolbar_is_signed_in_capability_scoped_and_no_js_reachable(): void
    {
        $author = $this->makeUser(['username' => 'study_toolbar_author']);
        $viewer = $this->makeUser(['username' => 'study_toolbar_viewer']);
        $board = $this->makeBoard($this->makeCategory('Study Toolbar'));
        $thread = $this->makeThread($board, $author, 'Actions remain real forms', 'Opening record.');

        $guest = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringNotContainsString('data-post-toolbar', $guest->body());

        $this->actingAs($viewer);
        $member = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('data-post-toolbar', $member->body());
        self::assertStringContainsString('data-quote-post', $member->body());
        self::assertStringContainsString('data-copy-post', $member->body());
        self::assertStringContainsString('icon-plus', $member->body());
        self::assertStringContainsString('icon-quote', $member->body());
        self::assertStringContainsString('icon-more-horizontal', $member->body());
        self::assertStringNotContainsString('Remove (warden)', $member->body());
    }

    public function test_quote_is_hidden_when_an_active_viewer_cannot_reply(): void
    {
        $author = $this->makeAdmin(['username' => 'study_quote_author']);
        $viewer = $this->makeUser(['username' => 'study_quote_reader']);
        $board = $this->makeBoard($this->makeCategory('Study Quote'), ['post_min_role' => 'moderator']);
        $thread = $this->makeThread($board, $author, 'Quote follows reply authority', 'Opening record.');

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        self::assertStringContainsString('data-post-toolbar', $page->body());
        self::assertStringContainsString('data-copy-post', $page->body());
        self::assertStringNotContainsString('data-quote-post', $page->body());
        self::assertStringContainsString("You don't have permission to reply in this board.", $page->body());
    }

    public function test_owner_edit_and_delete_forms_keep_one_native_no_js_copy(): void
    {
        $author = $this->makeUser(['username' => 'study_owner_actions']);
        $board = $this->makeBoard($this->makeCategory('Study Owner Actions'));
        $thread = $this->makeThread($board, $author, 'Owner actions remain native', 'Opening record.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($author);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $html = $page->body();

        self::assertSame(1, substr_count($html, 'action="/posts/' . $opId . '/edit"'));
        self::assertSame(1, substr_count($html, 'action="/posts/' . $opId . '/delete"'));
        self::assertStringContainsString('id="post-edit-' . $opId . '"', $html);
        self::assertStringContainsString('data-post-disclosure-open="post-edit-' . $opId . '"', $html);
        self::assertStringContainsString('<summary class="linkbtn">Edit</summary>', $html);
    }

    public function test_moderator_post_forms_are_capability_scoped_single_copy_and_native_without_js(): void
    {
        $author = $this->makeUser(['username' => 'study_post_form_author']);
        $moderator = $this->makeUser(['username' => 'study_post_form_moderator']);
        $board = $this->makeBoard($this->makeCategory('Study Post Forms'), ['allow_anonymous' => 1]);
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [$board['id']]);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $moderator['id']);
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'],
            'title' => 'Moderator forms remain native',
            'body' => 'Anonymous opening record.',
            'is_anonymous' => 1,
        ]);
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($moderator);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $html = $page->body();

        self::assertSame(1, substr_count($html, 'action="/posts/' . $opId . '/delete"'));
        self::assertSame(1, substr_count($html, 'action="/mod/p/' . $opId . '/reveal"'));
        self::assertSame(1, substr_count($html, 'action="/posts/' . $opId . '/wiki"'));
        self::assertSame(1, substr_count($html, 'action="/posts/' . $opId . '/report"'));
        self::assertStringContainsString('id="post-remove-' . $opId . '"', $html);
        self::assertStringContainsString('id="post-report-' . $opId . '"', $html);
        self::assertStringContainsString('name="reason"', $html);
        self::assertStringContainsString('name="reason_code"', $html);
    }

    public function test_suspended_admin_and_board_moderator_get_no_post_write_controls(): void
    {
        $author = $this->makeUser(['username' => 'study_suspended_post_author']);
        $reactor = $this->makeUser(['username' => 'study_reaction_seed']);
        $admin = $this->makeAdmin([
            'username' => 'study_suspended_post_admin',
            'status' => 'suspended',
            'suspended_until' => '2099-01-01 00:00:00',
        ]);
        $moderator = $this->makeUser(['username' => 'study_suspended_post_moderator']);
        $board = $this->makeBoard($this->makeCategory('Study Suspended Posts'), ['allow_anonymous' => 1]);
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [$board['id']]);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $moderator['id']);
        $this->users()->setStatus((int) $moderator['id'], 'suspended', '2099-01-01 00:00:00');
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'],
            'title' => 'Account state closes post actions',
            'body' => 'Anonymous opening record.',
            'is_anonymous' => 1,
        ]);
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($reactor);
        $this->assertRedirect($this->post('/posts/' . $opId . '/react', ['emoji' => '👍']));

        foreach ([$admin, $moderator] as $suspended) {
            $this->actingAs($suspended);
            $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
            $html = $page->body();

            self::assertStringContainsString('data-post-toolbar', $html);
            self::assertStringContainsString('data-copy-post', $html);
            self::assertStringContainsString('reaction reaction-static', $html);
            self::assertStringNotContainsString('data-quote-post', $html);
            self::assertStringNotContainsString('class="reaction-form', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/react"', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/accept"', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/edit"', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/delete"', $html);
            self::assertStringNotContainsString('action="/mod/p/' . $opId . '/reveal"', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/wiki"', $html);
            self::assertStringNotContainsString('action="/posts/' . $opId . '/report"', $html);
            self::assertStringNotContainsString('data-post-disclosure-open', $html);
        }
    }

    public function test_stream_inserts_a_divider_when_the_utc_calendar_day_changes(): void
    {
        $author = $this->makeUser(['username' => 'study_day_author']);
        $board = $this->makeBoard($this->makeCategory('Study Days'));
        $thread = $this->makeThread($board, $author, 'Days divide the record', 'Opening record.');
        $reply = $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'A later record.']);
        $this->db->run("UPDATE posts SET created_at = '2026-07-13 09:00:00' WHERE id = ?", [$reply]);
        $this->db->run("UPDATE posts SET created_at = '2026-07-12 09:00:00' WHERE thread_id = ? AND is_op = 1", [$thread['thread_id']]);

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('data-post-day="2026-07-13"', $page->body());
    }
}
