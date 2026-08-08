<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\EmailDeliveryRepository;
use Tests\Support\TestCase;

final class AppAdminTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $user;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->user = $this->makeUser(['username' => 'regular']);
        $this->categoryId = $this->makeCategory('General');
    }

    public function test_admin_can_reach_console_but_others_cannot(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(200, $this->get('/admin'));
        $this->assertSeeText($this->get('/admin/structure'), 'Boards');

        $this->logoutClient();
        $this->actingAs($this->user);
        $this->assertStatus(403, $this->get('/admin'));
        $this->assertStatus(403, $this->get('/admin/structure'));

        $this->logoutClient();
        $this->assertRedirectContains($this->get('/admin'), '/login');
    }

    public function test_non_admin_post_to_admin_action_is_forbidden(): void
    {
        $this->actingAs($this->user);
        $this->get('/');
        $this->assertStatus(403, $this->post('/admin/site', ['site_name' => 'Hijacked']));
        $this->assertStatus(403, $this->post('/admin/categories', ['name' => 'Sneaky']));
    }

    public function test_admin_updates_site_name_and_audits_it(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin');
        $response = $this->post('/admin/site', ['site_name' => 'New Name']);
        $this->assertRedirect($response, '/admin/settings');

        self::assertSame('New Name', (new \App\Repository\SettingRepository($this->db))->getString('site_name'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'update_setting'"));
    }

    public function test_admin_creates_and_edits_boards_and_categories(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/structure');

        $this->post('/admin/boards', [
            'category_id' => $this->categoryId,
            'name' => 'Announcements',
            'slug' => 'news',
            'visibility' => 'public',
        ]);

        $board = $this->boards()->findBySlug('news');
        self::assertNotNull($board);
        self::assertSame('Announcements', $board['name']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'create_board'"));
    }

    public function test_board_forms_submit_explicit_zero_values_for_unchecked_flags(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'explicit-flags', 'name' => 'Explicit Flags']);
        $this->actingAs($this->admin);

        foreach ([$this->get('/admin/structure'), $this->get('/admin/boards/' . (int) $board['id'] . '/edit')] as $page) {
            $this->assertStatus(200, $page);
            foreach (['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled'] as $flag) {
                self::assertStringContainsString(
                    '<input type="hidden" name="' . $flag . '" value="0">',
                    $page->body(),
                );
            }
        }

        $response = $this->post('/admin/boards', [
            'category_id' => (string) $this->categoryId,
            'name' => 'All Flags Off',
            'slug' => 'all-flags-off',
            'visibility' => 'public',
            'post_min_role' => 'user',
            'assignment_mode' => 'off',
            'edit_window_minutes' => '0',
            'allow_anonymous' => '0',
            'require_approval' => '0',
            'tags_enabled' => '0',
            'wiki_enabled' => '0',
        ]);

        $this->assertRedirect($response, '/admin/structure');
        $created = $this->boards()->findBySlug('all-flags-off');
        self::assertNotNull($created);
        foreach (['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled'] as $flag) {
            self::assertSame(0, (int) $created[$flag], $flag);
        }
    }

    public function test_board_edit_422_preserves_submitted_unchecked_flags_without_mutating(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'stored-flags', 'name' => 'Stored Flags']);
        $this->db->run(
            'UPDATE boards SET allow_anonymous = 1, require_approval = 1, tags_enabled = 1, wiki_enabled = 1 WHERE id = ?',
            [(int) $board['id']],
        );
        $this->actingAs($this->admin);
        $this->get('/admin/boards/' . (int) $board['id'] . '/edit');

        $response = $this->post('/admin/boards/' . (int) $board['id'], [
            'category_id' => (string) $this->categoryId,
            'name' => '',
            'slug' => 'stored-flags',
            'description' => 'Keep this draft',
            'visibility' => 'public',
            'post_min_role' => 'user',
            'assignment_mode' => 'off',
            'edit_window_minutes' => '0',
            'allow_anonymous' => '0',
            'require_approval' => '0',
            'tags_enabled' => '0',
            'wiki_enabled' => '0',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Board name must be');
        $this->assertSeeText($response, 'Keep this draft');
        foreach (['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled'] as $flag) {
            self::assertMatchesRegularExpression(
                '/<input type="checkbox" name="' . $flag . '" value="1"(?![^>]*\bchecked\b)[^>]*>/',
                $response->body(),
                $flag,
            );
        }

        $stored = $this->boards()->find((int) $board['id']);
        self::assertNotNull($stored);
        foreach (['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled'] as $flag) {
            self::assertSame(1, (int) $stored[$flag], $flag);
        }
    }

    public function test_dashboard_users_card_is_labelled_new_users_today(): void
    {
        // Round-2 audit finding 10: the headline number is today's signups but
        // the card just said "Users" — it read as community size.
        $this->actingAs($this->admin);
        self::assertStringContainsString('New users today', $this->get('/admin')->body());
    }

    public function test_explicit_taken_board_slug_is_422_not_silently_suffixed(): void
    {
        // Round-2 audit finding 5: a typed identifier must not be rewritten
        // ("general" → "general-2") without the operator being told.
        $this->actingAs($this->admin);
        $this->makeBoard($this->categoryId, ['slug' => 'general', 'name' => 'General']);
        $res = $this->post('/admin/boards', [
            'category_id' => (string) $this->categoryId,
            'name' => 'Other board',
            'slug' => 'general',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('already in use', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM boards WHERE slug = 'general-2'"));
    }

    public function test_explicit_board_slug_over_64_chars_is_422_not_silently_truncated(): void
    {
        $this->actingAs($this->admin);
        $typedSlug = str_repeat('s', 65);

        $res = $this->post('/admin/boards', [
            'category_id' => (string) $this->categoryId,
            'name' => 'Long slug board',
            'slug' => $typedSlug,
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('64 characters or fewer', $res->body());
        self::assertNull($this->boards()->findBySlug(str_repeat('s', 64)));
    }

    public function test_blank_slug_with_duplicate_name_still_auto_suffixes(): void
    {
        $this->actingAs($this->admin);
        $this->makeBoard($this->categoryId, ['slug' => 'dupe-name', 'name' => 'Dupe Name']);
        $res = $this->post('/admin/boards', [
            'category_id' => (string) $this->categoryId,
            'name' => 'Dupe Name',
            'slug' => '',
        ]);
        $this->assertRedirectContains($res, '/admin/structure');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM boards WHERE slug = 'dupe-name-2'"));
    }

    public function test_dashboard_prioritizes_operational_summary_and_attention_links(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'ops', 'name' => 'Operations']);
        $author = $this->makeUser(['username' => 'ops_author']);
        $thread = $this->makeThread($board, $author, 'Pending topic');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $reporter = $this->makeUser(['username' => 'reporter']);
        $replier = $this->makeUser(['username' => 'ops_replier']);
        $this->actingAs($replier);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Held reply']);
        $replyId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$thread['thread_id']]);

        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$thread['thread_id']]);
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$replyId]);
        $this->db->run(
            "INSERT INTO reports (reporter_id, post_id, reason_code, reason, status, notify_reporter, created_at)
             VALUES (?, ?, 'spam', 'Operator follow-up needed', 'open', 0, UTC_TIMESTAMP())",
            [(int) $reporter['id'], $opId],
        );

        $deliveries = new EmailDeliveryRepository($this->db);
        $deliveryId = $deliveries->enqueue(null, 'ops@example.test', 'system', 'Operator alert');
        $deliveries->markFailed($deliveryId, 'SMTP refused the message.');

        $this->logoutClient();
        $this->actingAs($this->admin);
        $response = $this->get('/admin');

        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'Needs attention');
        $this->assertSeeText($response, 'Reports');
        $this->assertSeeText($response, 'Approval hold');
        $this->assertSeeText($response, 'Email failures');
        $this->assertSeeText($response, '/mod/reports');
        $this->assertSeeText($response, '/mod/approvals');
        $this->assertSeeText($response, '/admin/email?status=failed');
    }

    public function test_board_can_only_be_deleted_when_empty(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'tmp', 'name' => 'Temp']);
        $author = $this->makeUser();
        $this->makeThread($board, $author, 'a thread');

        $this->actingAs($this->admin);
        // The confirmation page shows impact; with no other unarchived board to
        // move the threads to, it blocks (2026-07-18: delete offers a move
        // destination instead of the old flat "empty boards only" refusal).
        $confirm = $this->get('/admin/boards/' . $board['id'] . '/delete');
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, 'no other unarchived board to move them to');

        // A matching typed confirm on a non-empty board is refused (422), not deleted.
        $blocked = $this->post('/admin/boards/' . $board['id'] . '/delete', ['confirm' => 'tmp']);
        $this->assertStatus(422, $blocked);
        self::assertNotNull($this->boards()->find((int) $board['id'])); // still there

        // A mismatched confirm is also refused without mutating.
        $mismatch = $this->post('/admin/boards/' . $board['id'] . '/delete', ['confirm' => 'wrong']);
        $this->assertStatus(422, $mismatch);
        self::assertNotNull($this->boards()->find((int) $board['id']));

        // Make it empty, then deletion succeeds with the matching slug.
        $this->db->run('DELETE FROM posts WHERE thread_id IN (SELECT id FROM threads WHERE board_id = ?)', [(int) $board['id']]);
        $this->db->run('DELETE FROM threads WHERE board_id = ?', [(int) $board['id']]);

        $this->get('/admin/structure');
        $ok = $this->post('/admin/boards/' . $board['id'] . '/delete', ['confirm' => 'tmp']);
        $this->assertRedirect($ok);
        self::assertNull($this->boards()->find((int) $board['id']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'delete_board'"));
    }

    public function test_category_can_only_be_deleted_when_empty(): void
    {
        $this->makeBoard($this->categoryId, ['slug' => 'inside']);
        $this->actingAs($this->admin);

        // Confirmation page blocks a non-empty category.
        $confirm = $this->get('/admin/categories/' . $this->categoryId . '/delete');
        $this->assertStatus(200, $confirm);

        $blocked = $this->post('/admin/categories/' . $this->categoryId . '/delete', ['confirm' => 'General']);
        $this->assertStatus(422, $blocked);
        self::assertNotNull($this->db->fetch('SELECT * FROM categories WHERE id = ?', [$this->categoryId]));

        $empty = $this->makeCategory('Empty');
        // A mismatched confirm on an empty category is refused too.
        $mismatch = $this->post('/admin/categories/' . $empty . '/delete', ['confirm' => 'nope']);
        $this->assertStatus(422, $mismatch);
        self::assertNotNull($this->db->fetch('SELECT * FROM categories WHERE id = ?', [$empty]));

        $ok = $this->post('/admin/categories/' . $empty . '/delete', ['confirm' => 'Empty']);
        $this->assertRedirect($ok);
        self::assertNull($this->db->fetch('SELECT * FROM categories WHERE id = ?', [$empty]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'delete_category'"));
    }

    public function test_board_slug_change_creates_301_redirect(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'oldslug', 'name' => 'Renamed']);
        $this->actingAs($this->admin);
        $this->get('/admin/boards/' . $board['id'] . '/edit');
        $this->post('/admin/boards/' . $board['id'], [
            'category_id' => $this->categoryId,
            'name' => 'Renamed',
            'slug' => 'newslug',
            'visibility' => 'public',
        ]);

        self::assertSame('newslug', $this->boards()->find((int) $board['id'])['slug']);
        self::assertNotNull($this->db->fetch('SELECT * FROM board_slug_history WHERE old_slug = ?', ['oldslug']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'update_board'"));

        $redirect = $this->get('/c/oldslug');
        $this->assertRedirect($redirect, '/c/newslug');
        self::assertSame(301, $redirect->status());
    }

    // ---- Task 5: confirmation, impact, and 422 preservation ---------------

    public function test_structure_destructive_actions_are_not_one_click(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'oneclick', 'name' => 'OneClick']);
        $this->actingAs($this->admin);
        $page = $this->get('/admin/structure');
        $this->assertStatus(200, $page);

        // Delete/archive/category-delete are links to confirmation pages, never
        // inline one-click POST forms.
        $this->assertSeeText($page, 'href="/admin/boards/' . $board['id'] . '/delete"');
        $this->assertSeeText($page, 'href="/admin/boards/' . $board['id'] . '/archive"');
        $this->assertSeeText($page, 'href="/admin/categories/' . $this->categoryId . '/delete"');
        $this->assertDontSeeText($page, 'action="/admin/boards/' . $board['id'] . '/delete"');
        $this->assertDontSeeText($page, 'action="/admin/boards/' . $board['id'] . '/archive"');
        $this->assertDontSeeText($page, 'action="/admin/categories/' . $this->categoryId . '/delete"');
    }

    public function test_content_structure_renders_the_design_body_without_losing_real_forms(): void
    {
        $board = $this->makeBoard($this->categoryId, [
            'slug' => 'quiet-room',
            'name' => 'Quiet Room',
            'description' => '<em>Private planning</em>',
            'visibility' => 'hidden',
        ]);
        $this->boards()->setArchived((int) $board['id'], true);
        $this->actingAs($this->admin);

        $page = $this->get('/admin/structure');

        $this->assertStatus(200, $page);
        $body = $page->body();
        self::assertStringContainsString('class="admin-pane admin-content admin-content-structure"', $body);
        self::assertStringContainsString('Categories group boards; boards are where topics live.', $body);
        self::assertStringContainsString('Renaming a board is safe — its old link keeps working.', $body);
        self::assertStringContainsString('Archiving makes a board read-only — its topics stay readable and searchable.', $body);
        self::assertStringContainsString('href="/c/quiet-room"', $body);
        self::assertStringContainsString('&lt;em&gt;Private planning&lt;/em&gt;', $body);
        self::assertStringContainsString('class="content-board-chip content-board-chip-hidden">Hidden</span>', $body);
        self::assertStringContainsString('class="content-board-chip content-board-chip-archived">Archived</span>', $body);
        self::assertStringContainsString('class="content-icon-button content-icon-button-board"', $body);
        self::assertStringNotContainsString('>↑</button>', $body);
        self::assertStringNotContainsString('>↓</button>', $body);
        self::assertStringContainsString('action="/admin/boards/' . (int) $board['id'] . '/move"', $body);
        self::assertStringContainsString('href="/admin/boards/' . (int) $board['id'] . '/unarchive"', $body);
        self::assertStringContainsString('class="stacked content-board-grid"', $body);
        self::assertStringContainsString('placeholder="derived from the name"', $body);
    }

    public function test_content_structure_renders_the_factual_empty_state(): void
    {
        $this->db->run('DELETE FROM categories WHERE id = ?', [$this->categoryId]);
        $this->actingAs($this->admin);

        $page = $this->get('/admin/structure');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('class="content-empty-state"', $page->body());
        $this->assertSeeText($page, 'No categories yet');
        $this->assertSeeText($page, 'A forum needs at least one board. Add a category below, then put a board inside it.');
    }

    public function test_board_delete_typed_confirmation_422_preserves_the_selected_destination(): void
    {
        $source = $this->makeBoard($this->categoryId, ['slug' => 'source-room', 'name' => 'Source Room']);
        $destination = $this->makeBoard($this->categoryId, ['slug' => 'destination-room', 'name' => 'Destination Room']);
        $author = $this->makeUser(['username' => 'content-confirm-author']);
        $this->makeThread($source, $author, 'Thread to preserve');
        $this->actingAs($this->admin);
        $this->get('/admin/boards/' . (int) $source['id'] . '/delete');

        $response = $this->post('/admin/boards/' . (int) $source['id'] . '/delete', [
            'confirm' => 'not-the-slug',
            'move_to_board_id' => (string) $destination['id'],
        ]);

        $this->assertStatus(422, $response);
        $body = $response->body();
        self::assertStringContainsString('class="card confirm-card content-confirm-card"', $body);
        self::assertMatchesRegularExpression(
            '/<option value="' . (int) $destination['id'] . '" selected>[^<]*Destination Room/s',
            $body,
        );
        self::assertStringContainsString('name="confirm"', $body);
        self::assertNotNull($this->boards()->find((int) $source['id']));
    }

    public function test_archive_requires_typed_slug_confirmation(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'archme', 'name' => 'ArchMe']);
        $this->actingAs($this->admin);

        // GET confirmation page renders (no-JS friendly) and explains the impact.
        $page = $this->get('/admin/boards/' . $board['id'] . '/archive');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'read-only');

        // A missing/mismatched confirmation is refused without mutating.
        $refused = $this->post('/admin/boards/' . $board['id'] . '/archive', ['confirm' => 'wrong']);
        $this->assertStatus(422, $refused);
        self::assertSame(0, (int) $this->boards()->find((int) $board['id'])['is_archived']);

        // The exact slug archives the board.
        $ok = $this->post('/admin/boards/' . $board['id'] . '/archive', ['confirm' => 'archme']);
        $this->assertRedirect($ok, '/admin/structure');
        self::assertSame(1, (int) $this->boards()->find((int) $board['id'])['is_archived']);
    }

    public function test_unarchive_requires_typed_slug_confirmation(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'unarchme', 'name' => 'UnArch']);
        $this->boards()->setArchived((int) $board['id'], true);
        $this->actingAs($this->admin);

        $page = $this->get('/admin/boards/' . $board['id'] . '/unarchive');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'posting');

        $refused = $this->post('/admin/boards/' . $board['id'] . '/unarchive', ['confirm' => 'nope']);
        $this->assertStatus(422, $refused);
        self::assertSame(1, (int) $this->boards()->find((int) $board['id'])['is_archived']);

        $ok = $this->post('/admin/boards/' . $board['id'] . '/unarchive', ['confirm' => 'unarchme']);
        $this->assertRedirect($ok, '/admin/structure');
        self::assertSame(0, (int) $this->boards()->find((int) $board['id'])['is_archived']);
    }

    public function test_create_board_validation_error_rerenders_422_and_preserves_input(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $res = $this->post('/admin/boards', [
            'category_id' => $this->categoryId,
            'name' => '', // invalid → 422
            'slug' => 'keepme',
            'description' => 'Kept description',
            'visibility' => 'hidden',
        ]);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Board name must be');
        // Typed input is preserved rather than dropped.
        $this->assertSeeText($res, 'keepme');
        $this->assertSeeText($res, 'Kept description');
        self::assertNull($this->boards()->findBySlug('keepme'));
    }

    public function test_create_category_validation_error_rerenders_422_and_preserves_input(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $long = str_repeat('x', 65);
        $res = $this->post('/admin/categories', ['name' => $long]);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Category name must be');
        $this->assertSeeText($res, $long);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM categories'));
    }

    public function test_assign_moderator_unknown_user_rerenders_board_edit_422(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'rosterb', 'name' => 'Roster']);
        $this->actingAs($this->admin);
        $this->get('/admin/boards/' . $board['id'] . '/edit');
        $res = $this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => 'ghostuser']);
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'No member found');
        // The typed username survives the failed submit.
        $this->assertSeeText($res, 'ghostuser');
        self::assertStringContainsString('class="admin-pane admin-content admin-content-board-edit"', $res->body());
        self::assertStringContainsString('class="stacked card content-board-grid content-board-edit-form"', $res->body());
        self::assertStringContainsString('class="card admin-cat content-roster-card"', $res->body());
        self::assertStringContainsString('value="ghostuser"', $res->body());
    }
}
