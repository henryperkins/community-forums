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

    public function test_login_renders_the_ceremonial_auth_gate(): void
    {
        $res = $this->get('/login');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'auth-stage');
        $this->assertSeeText($res, 'auth-stage-star');
        $this->assertSeeText($res, 'auth-brand');
        $this->assertSeeText($res, 'auth-eyebrow');
        $this->assertSeeText($res, 'auth-form');
        $this->assertSeeText($res, 'auth-colophon');
    }

    public function test_settings_account_renders_the_lapidary_console(): void
    {
        $user = $this->makeUser(['username' => 'scribe', 'display_name' => 'House Scribe']);
        $this->actingAs($user);

        $res = $this->get('/settings/account');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'settings-screen');
        $this->assertSeeText($res, 'settings-head');
        $this->assertSeeText($res, 'settings-pane');
        $this->assertSeeText($res, 'scribe-panel');
        $this->assertSeeText($res, 'field-grid');
    }

    public function test_admin_dashboard_renders_the_operator_console_register(): void
    {
        $admin = $this->makeAdmin(['username' => 'operator']);
        $this->actingAs($admin);

        $res = $this->get('/admin');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'admin-subnav');
        $this->assertSeeText($res, 'admin-pane');
        $this->assertSeeText($res, 'pane-intro');
        $this->assertSeeText($res, 'Recent activity');   // the audit register section renders (empty-state here)
    }

    public function test_admin_branding_renders_inside_the_operator_console_register(): void
    {
        $admin = $this->makeAdmin(['username' => 'brandkeeper']);
        $this->actingAs($admin);

        $res = $this->get('/admin/branding');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'admin-head');
        $this->assertSeeText($res, 'admin-pane');
        $this->assertSeeText($res, 'brand-cols');
        $this->assertSeeText($res, 'brand-preview');
    }

    public function test_messages_index_renders_the_private_counsel_reading_room(): void
    {
        $sender = $this->makeUser(['username' => 'imladris_dm_sender', 'display_name' => 'Imladris Sender']);
        $recipient = $this->makeUser(['username' => 'imladris_dm_recipient', 'display_name' => 'Imladris Recipient']);
        $this->makeThread($this->makeBoard($this->makeCategory()), $sender, 'Introductions', 'I can now write private counsel.');

        $this->actingAs($sender);
        $this->assertRedirect($this->post('/messages', [
            'to' => $recipient['username'],
            'body' => 'Private counsel opens here.',
        ]));

        $res = $this->get('/messages');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'dm-shell');
        $this->assertSeeText($res, 'dm-listpane');
        $this->assertSeeText($res, 'dm-listpane-head');
        $this->assertSeeText($res, 'Private counsel');
        $this->assertSeeText($res, 'dm-row');
    }

    public function test_message_thread_renders_open_letter_bubbles_and_report_form(): void
    {
        $sender = $this->makeUser(['username' => 'letter_sender', 'display_name' => 'Letter Sender']);
        $recipient = $this->makeUser(['username' => 'letter_recipient', 'display_name' => 'Letter Recipient']);
        $this->makeThread($this->makeBoard($this->makeCategory()), $sender, 'Letters', 'I can now start conversations.');

        $this->actingAs($sender);
        $this->assertRedirect($this->post('/messages', [
            'to' => $recipient['username'],
            'body' => 'First private counsel.',
        ]));
        $conversationId = (int) $this->db->fetchValue('SELECT id FROM conversations ORDER BY id DESC LIMIT 1');

        $this->actingAs($recipient);
        $this->assertRedirect($this->post('/messages/' . $conversationId, ['body' => 'An open-letter reply.']));

        $this->actingAs($sender);
        $res = $this->get('/messages/' . $conversationId);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'dm-shell reading');
        $this->assertSeeText($res, 'dm-threadpane');
        $this->assertSeeText($res, 'dm-thread-head');
        $this->assertSeeText($res, 'dm-scroll');
        $this->assertSeeText($res, 'dm-bubble');
        $this->assertSeeText($res, 'dm-report-form');
    }

    public function test_moderation_reports_render_the_wardens_table(): void
    {
        $admin = $this->makeAdmin(['username' => 'warden_admin']);
        $reporter = $this->makeUser(['username' => 'warden_reporter']);
        $reported = $this->makeUser(['username' => 'warden_reported']);
        $this->makeThread($this->makeBoard($this->makeCategory()), $reporter, 'Reporter introduction', 'I can now start conversations.');

        $this->actingAs($reporter);
        $this->assertRedirect($this->post('/messages', [
            'to' => $reported['username'],
            'body' => 'Opening counsel.',
        ]));
        $conversationId = (int) $this->db->fetchValue('SELECT id FROM conversations ORDER BY id DESC LIMIT 1');

        $this->actingAs($reported);
        $this->assertRedirect($this->post('/messages/' . $conversationId, ['body' => 'wardens-table-report-marker']));
        $messageId = (int) $this->db->fetchValue(
            'SELECT id FROM dm_messages WHERE conversation_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$conversationId, (int) $reported['id']],
        );

        $this->actingAs($reporter);
        $this->assertRedirect($this->post('/dm/' . $messageId . '/report', [
            'reason_code' => 'harassment',
            'reason' => 'Please review this.',
        ]));

        $this->actingAs($admin);
        $res = $this->get('/mod/reports');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'mod-head');
        $this->assertSeeText($res, 'mod-subnav');
        $this->assertSeeText($res, 'mod-pane');
        $this->assertSeeText($res, 'report-row is-urgent');
        $this->assertSeeText($res, 'wardens-table-report-marker');
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
        $this->assertSeeText($res, 'thread-actions');   // the one-line member control bar renders…
        $this->assertSeeText($res, 'star-btn');         // …with the star control…
        $this->assertSeeText($res, 'Notify: Instant');  // …and the notify control gathered into it
    }

    public function test_profile_tabs_render_and_posts_tab_lists_activity(): void
    {
        $user = $this->makeUser(['username' => 'tabbed', 'display_name' => 'Tabbed User']);
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $user, 'Tabbed topic', 'Body of the tabbed topic.');

        $overview = $this->get('/u/tabbed');
        $this->assertStatus(200, $overview);
        $this->assertSeeText($overview, 'profile-tabs');
        // Default view: the Overview tab is the active one, Posts is not.
        $this->assertSeeText($overview, 'aria-current="page" href="/u/tabbed">Overview');
        $this->assertDontSeeText($overview, 'aria-current="page" href="/u/tabbed?tab=posts">Posts');

        // ?tab=posts switches the active tab (proves the param is honoured, not ignored)…
        $posts = $this->get('/u/tabbed', ['tab' => 'posts']);
        $this->assertStatus(200, $posts);
        $this->assertSeeText($posts, 'aria-current="page" href="/u/tabbed?tab=posts">Posts');
        $this->assertDontSeeText($posts, 'aria-current="page" href="/u/tabbed">Overview');
        // …and the Posts tab lists the user's activity.
        $this->assertSeeText($posts, 'Tabbed topic');
    }
}
