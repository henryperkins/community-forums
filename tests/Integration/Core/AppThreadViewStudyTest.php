<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

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
        self::assertStringNotContainsString('data-topic-tools-open', $page->body());
        self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/status"', $page->body());
        self::assertStringNotContainsString('class="workflow-bar', $page->body());
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
    }
}
