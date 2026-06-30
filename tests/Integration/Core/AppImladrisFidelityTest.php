<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Imladris Engineering-Handoff fidelity pass: the server-rendered behaviour behind
 * the conversation and profile fidelity work — the participant avatar stack, the
 * grouped (consecutive same-author) posts, the accepted-answer plate caption, the
 * one-line topic action bar, and the tabbed profile activity. Pure render assertions
 * over the real kernel (DESIGN §13: behaviour must be exercised, not just drawn).
 */
final class AppImladrisFidelityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // initialise the app so the setup gate doesn't intercept HTTP routes
    }

    public function test_thread_header_shows_participant_stack_for_multi_author_thread(): void
    {
        $opener = $this->makeUser(['username' => 'opener']);
        $replier = $this->makeUser(['username' => 'replier']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'Many voices', 'Opening.');
        $tid = (int) $thread['thread_id'];

        $this->actingAs($replier);
        $this->post('/t/' . $tid . '/reply', ['body' => 'A second voice joins the topic.']);

        $res = $this->get('/t/' . $tid . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'thread-participants');   // two distinct authors → the stack renders
    }

    public function test_single_author_thread_has_no_participant_stack(): void
    {
        $opener = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'One voice', 'Opening.');

        $res = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertDontSeeText($res, 'thread-participants');   // a single participant gets no stack
    }

    public function test_consecutive_same_author_posts_are_grouped(): void
    {
        $opener = $this->makeUser();
        $replier = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'Grouped', 'Opening.');
        $tid = (int) $thread['thread_id'];

        $this->actingAs($replier);
        $this->post('/t/' . $tid . '/reply', ['body' => 'First reply.']);
        $this->post('/t/' . $tid . '/reply', ['body' => 'Immediate follow-up by the same author.']);

        $res = $this->get('/t/' . $tid . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'post-grouped');   // the second consecutive reply drops its repeated header
    }

    public function test_accepted_answer_renders_the_plate_caption(): void
    {
        $opener = $this->makeUser();
        $replier = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'Solve me', 'Opening question.');
        $tid = (int) $thread['thread_id'];

        $this->actingAs($replier);
        $this->post('/t/' . $tid . '/reply', ['body' => 'Here is the answer.']);
        $reply = $this->db->fetch(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1',
            [$tid],
        );
        $this->assertNotNull($reply, 'Expected the reply submission to create a reply before accepting it.');

        $this->actingAs($opener);   // the OP accepts the reply
        $this->post('/posts/' . (int) $reply['id'] . '/accept');

        $res = $this->get('/t/' . $tid . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Marked as the answer');
        $this->assertSeeText($res, 'accepted-flag');
    }

    public function test_thread_action_bar_groups_member_controls(): void
    {
        $opener = $this->makeUser();
        $reader = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'Actions', 'Opening.');

        $this->actingAs($reader);
        $res = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'thread-actions');   // star + notify gathered into one bar
    }

    public function test_profile_tabs_render_and_posts_tab_lists_activity(): void
    {
        $user = $this->makeUser(['username' => 'tabbed', 'display_name' => 'Tabbed User']);
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $user, 'Tabbed topic', 'Body of the tabbed topic.');

        $overview = $this->get('/u/tabbed');
        $this->assertStatus(200, $overview);
        $this->assertSeeText($overview, 'profile-tabs');
        $this->assertSeeText($overview, 'Overview');

        $posts = $this->get('/u/tabbed', ['tab' => 'posts']);
        $this->assertStatus(200, $posts);
        $this->assertSeeText($posts, 'Tabbed topic');   // the OP post is listed under the Posts tab
    }
}
