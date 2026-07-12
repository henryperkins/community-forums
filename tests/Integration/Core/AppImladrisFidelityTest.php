<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
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

    public function test_admin_sibling_pages_render_inside_the_operator_console_register(): void
    {
        $admin = $this->makeAdmin(['username' => 'consolekeeper']);
        $this->actingAs($admin);

        foreach (['/admin/users', '/admin/email', '/admin/structure'] as $path) {
            $res = $this->get($path);
            $this->assertStatus(200, $res);
            $this->assertSeeText($res, 'admin-pane');
        }
    }

    public function test_settings_sibling_pages_render_inside_the_lapidary_console(): void
    {
        $user = $this->makeUser(['username' => 'console_scribe']);
        $this->actingAs($user);

        foreach (['/settings/privacy', '/settings/appearance', '/settings/preferences', '/settings/composing'] as $path) {
            $res = $this->get($path);
            $this->assertStatus(200, $res);
            $this->assertSeeText($res, 'settings-screen');
            $this->assertSeeText($res, 'settings-pane');
            $this->assertSeeText($res, 'scribe-panel');
        }

        $privacy = $this->get('/settings/privacy');
        $this->assertSeeText($privacy, 'gem-check');
    }

    public function test_settings_pages_keep_one_main_landmark_and_real_section_headings(): void
    {
        $user = $this->makeUser(['username' => 'landmark_scribe']);
        $this->actingAs($user);

        foreach ([
            '/settings/account',
            '/settings/privacy',
            '/settings/security',
            '/settings/appearance',
            '/settings/preferences',
            '/settings/composing',
            '/settings/notifications',
            '/settings/connections',
            '/settings/sessions',
            '/settings/blocks',
            '/settings/boards',
            '/drafts',
        ] as $path) {
            $res = $this->get($path);
            $this->assertStatus(200, $res);
            self::assertSame(1, substr_count($res->body(), '<main '), $path . ' should render only the layout main landmark.');
        }

        $security = $this->get('/settings/security')->body();
        self::assertStringContainsString('<h2 class="scribe-panel-head">Password</h2>', $security);
        self::assertStringContainsString('<h2 class="scribe-panel-head">Two-factor authentication</h2>', $security);

        $notifications = $this->get('/settings/notifications')->body();
        self::assertStringContainsString('<h2 class="scribe-panel-head">Daily digest</h2>', $notifications);
    }

    public function test_board_preference_toggles_keep_link_button_active_state(): void
    {
        $user = $this->makeUser(['username' => 'board_toggler']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Toggled Board', 'slug' => 'toggled-board']);
        $this->actingAs($user);

        $this->assertRedirect($this->post('/settings/boards/toggle', [
            'board_id' => (int) $board['id'],
            'pref' => 'favorite',
        ]));
        $this->assertRedirect($this->post('/settings/boards/toggle', [
            'board_id' => (int) $board['id'],
            'pref' => 'mute',
        ]));

        $body = $this->get('/settings/boards')->body();
        self::assertStringNotContainsString('toggle-link', $body);
        self::assertStringContainsString('class="linkbtn btn-on"', $body);
        self::assertStringContainsString('Favorited', $body);
        self::assertStringContainsString('Muted', $body);
    }

    public function test_tag_index_counts_match_each_viewers_visible_topics(): void
    {
        (new SettingRepository($this->db))->set('features', ['tags' => true]);
        $author = $this->makeUser(['username' => 'tag_counter']);
        $member = $this->makeUser(['username' => 'tag_member']);
        $admin = $this->makeAdmin(['username' => 'tag_admin']);
        $tagRepo = new TagRepository($this->db);
        $tagId = $tagRepo->create('counted-tag', 'Counted Tag', 'A counted public tag.', (int) $author['id']);

        $categoryId = $this->makeCategory('Tagged spaces');
        $publicBoard = $this->makeBoard($categoryId, ['name' => 'Public Tags', 'slug' => 'public-tags']);
        $privateBoard = $this->makeBoard($categoryId, ['name' => 'Private Tags', 'slug' => 'private-tags', 'visibility' => 'private']);
        $hiddenBoard = $this->makeBoard($categoryId, ['name' => 'Hidden Tags', 'slug' => 'hidden-tags', 'visibility' => 'hidden']);
        $boardMembers = new BoardMemberRepository($this->db);
        $boardMembers->add((int) $privateBoard['id'], (int) $author['id'], (int) $admin['id']);
        $boardMembers->add((int) $privateBoard['id'], (int) $member['id'], (int) $admin['id']);

        $publicThread = $this->makeThread($publicBoard, $author, 'Public tagged topic', 'Visible to everyone.');
        $privateThread = $this->makeThread($privateBoard, $author, 'Private tagged topic', 'Visible only to members.');
        $hiddenThread = $this->makeThread($hiddenBoard, $author, 'Hidden tagged topic', 'Visible to admins on discovery routes.');

        $tagRepo->setForThread((int) $publicThread['thread_id'], [$tagId], (int) $author['id']);
        $tagRepo->setForThread((int) $privateThread['thread_id'], [$tagId], (int) $author['id']);
        $tagRepo->setForThread((int) $hiddenThread['thread_id'], [$tagId], (int) $author['id']);

        $guestIndex = $this->get('/tags')->body();
        self::assertStringContainsString('Counted Tag', $guestIndex);
        self::assertStringContainsString('<span class="tag-count">1 topic</span>', $guestIndex);
        self::assertStringNotContainsString('<span class="tag-count">1 topics</span>', $guestIndex);

        $guestDetail = $this->get('/tags/counted-tag')->body();
        self::assertStringContainsString('Public tagged topic', $guestDetail);
        self::assertStringNotContainsString('Private tagged topic', $guestDetail);
        self::assertStringNotContainsString('Hidden tagged topic', $guestDetail);

        $this->actingAs($member);
        $memberIndex = $this->get('/tags')->body();
        self::assertStringContainsString('<span class="tag-count">2 topics</span>', $memberIndex);

        $memberDetail = $this->get('/tags/counted-tag')->body();
        self::assertStringContainsString('Public tagged topic', $memberDetail);
        self::assertStringContainsString('Private tagged topic', $memberDetail);
        self::assertStringNotContainsString('Hidden tagged topic', $memberDetail);

        $this->actingAs($admin);
        $adminIndex = $this->get('/tags')->body();
        self::assertStringContainsString('<span class="tag-count">3 topics</span>', $adminIndex);

        $adminDetail = $this->get('/tags/counted-tag')->body();
        self::assertStringContainsString('Public tagged topic', $adminDetail);
        self::assertStringContainsString('Private tagged topic', $adminDetail);
        self::assertStringContainsString('Hidden tagged topic', $adminDetail);
    }

    public function test_lapidary_toggle_css_covers_gem_variants_and_captions(): void
    {
        $css = file_get_contents(__DIR__ . '/../../../public/assets/app.css');
        self::assertIsString($css);

        foreach (['.toggle-stack', '.gem-leaf', '.gem-gold', '.gem-river', '.gem-sub'] as $selector) {
            self::assertStringContainsString($selector, $css);
        }
    }

    public function test_reading_surfaces_render_inside_the_reading_rooms_shell(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Audit trails', 'slug' => 'audit-trails']);
        $user = $this->makeUser(['username' => 'reader']);
        $this->makeThread($board, $user, 'Who changed what', 'The diff is small; the audit trail must be whole.');
        $this->actingAs($user);

        foreach (['/', '/c/audit-trails', '/feed'] as $path) {
            $res = $this->get($path);
            $this->assertStatus(200, $res);
            $this->assertSeeText($res, 'read-main');
            $this->assertSeeText($res, 'read-pad');
        }

        $search = $this->get('/search', ['q' => 'audit']);
        $this->assertStatus(200, $search);
        $this->assertSeeText($search, 'read-main');
        $this->assertSeeText($search, 'read-pad');
        $this->assertSeeText($search, 'search-form');
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

    public function test_message_thread_renders_grouped_letters_and_report_form(): void
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
        // Reimagine: messages are de-boxed into grouped "letters" (one author line
        // per run), not the old bordered .dm-bubble cards.
        $this->assertSeeText($res, 'dm-scroll-inner');
        $this->assertSeeText($res, 'dm-day-private');
        $this->assertSeeText($res, 'Private — only those named here can read');
        $this->assertSeeText($res, 'dm-group');
        $this->assertSeeText($res, 'dm-body');
        $this->assertDontSeeText($res, 'dm-bubble');
        // Mine wears the one ceremonial gold plate; both message bodies render.
        $this->assertSeeText($res, 'class="dm-group mine"');
        $this->assertSeeText($res, 'First private counsel.');
        $this->assertSeeText($res, 'An open-letter reply.');
        // The per-message report is a hover-revealed ··· that still opens the
        // report form with no JavaScript (native <details>).
        $this->assertSeeText($res, 'dm-line-menu');
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

    public function test_topic_workflow_renders_topic_tools_controls(): void
    {
        (new SettingRepository($this->db))->set('features', ['topic_workflow' => true]);
        $admin = $this->makeAdmin(['username' => 'warden_bar_admin']);
        $author = $this->makeUser(['username' => 'warden_bar_author']);
        $board = $this->makeBoard($this->makeCategory('Warden Bar'), ['name' => 'Warden Bar']);
        $thread = $this->makeThread($board, $author, 'Reading attention as a map', 'Opening body.');

        $this->actingAs($admin);
        $res = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'data-topic-tools');
        $this->assertSeeText($res, 'data-topic-tools-section="standing"');
        $this->assertSeeText($res, 'action="/t/' . (int) $thread['thread_id'] . '/status"');
        $this->assertSeeText($res, 'action="/t/' . (int) $thread['thread_id'] . '/snooze"');
        $this->assertDontSeeText($res, 'class="workflow-bar');
    }

    public function test_split_merge_flag_renders_topic_restructuring_surface(): void
    {
        (new SettingRepository($this->db))->set('features', ['split_merge' => true]);
        $admin = $this->makeAdmin(['username' => 'split_surface_admin']);
        $author = $this->makeUser(['username' => 'split_surface_author']);
        $board = $this->makeBoard($this->makeCategory('Split Surface'), ['name' => 'Split Surface']);
        $thread = $this->makeThread($board, $author, 'Split this topic', 'Opening body.');
        $this->actingAs($author);
        $this->post('/t/' . (int) $thread['thread_id'] . '/reply', ['body' => 'Reply that can be moved.']);

        $this->actingAs($admin);
        $res = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'sm-panel');
        $this->assertSeeText($res, 'Split into a new topic');
        $this->assertSeeText($res, 'Merge this topic');
        $this->assertSeeText($res, 'post_ids[]');
        $this->assertSeeText($res, '/mod/t/' . (int) $thread['thread_id'] . '/split');
        $this->assertSeeText($res, '/mod/t/' . (int) $thread['thread_id'] . '/merge');
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

    public function test_quiet_thread_header_and_topic_tools_keep_member_controls_once(): void
    {
        $opener = $this->makeUser();
        $reader = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $opener, 'Actions', 'Opening.');

        $this->actingAs($reader);
        $res = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'thread-facts');
        $this->assertSeeText($res, 'star-btn');
        $this->assertSeeText($res, 'data-topic-tools-section="watch"');
        self::assertSame(1, substr_count($res->body(), 'action="/t/' . (int) $thread['thread_id'] . '/subscribe"'));
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
