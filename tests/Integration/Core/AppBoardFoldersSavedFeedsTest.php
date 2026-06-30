<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

final class AppBoardFoldersSavedFeedsTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_personal_organization_routes_are_dark_by_default(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'orgdark']);
        $board = $this->makeBoard($this->makeCategory('Org Dark'));
        $thread = $this->makeThread($board, $user, 'Dark bookmark', 'Body');
        $this->actingAs($user);

        $this->assertStatus(404, $this->post('/settings/board-folders', ['name' => 'Work']));
        $this->assertStatus(404, $this->post('/settings/board-folders/1/boards', ['board_id' => (int) $board['id']]));
        $this->assertStatus(404, $this->post('/settings/saved-feeds', ['name' => 'Mine', 'board_id' => (int) $board['id']]));
        $this->assertStatus(404, $this->post('/settings/bookmark-folders', ['name' => 'Read later']));
        $this->assertStatus(404, $this->post('/settings/bookmark-folders/1/threads', ['thread_id' => (int) $thread['thread_id']]));
    }

    public function test_board_settings_page_stays_available_without_personal_organization_flags(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'orgshell']);
        $this->makeBoard($this->makeCategory('Org Shell'), ['name' => 'Shell Board', 'slug' => 'shell-board']);
        $this->actingAs($user);

        $page = $this->get('/settings/boards');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Organize your boards', $page->body());
        self::assertStringNotContainsString('personal-org-grid', $page->body());
        self::assertStringNotContainsString('board-folder-card', $page->body());
        self::assertStringNotContainsString('saved-feed-card', $page->body());
        self::assertStringNotContainsString('bookmark-folder-card', $page->body());
    }

    public function test_user_creates_private_folder_and_saved_feed_filter(): void
    {
        $this->makeAdmin();
        $this->setFlags(['board_folders' => true, 'saved_feeds' => true]);
        $user = $this->makeUser(['username' => 'orguser']);
        $other = $this->makeUser(['username' => 'orgother']);
        $board = $this->makeBoard($this->makeCategory('Org'), ['slug' => 'org-board']);
        $this->actingAs($user);

        $this->assertRedirect($this->post('/settings/board-folders', ['name' => 'Work']));
        $folderId = (int) $this->db->fetchValue('SELECT id FROM board_folders WHERE user_id = ? AND name = ?', [(int) $user['id'], 'Work']);
        self::assertGreaterThan(0, $folderId);

        $this->assertRedirect($this->post('/settings/board-folders/' . $folderId . '/boards', ['board_id' => (int) $board['id']]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM board_folder_boards WHERE folder_id = ? AND board_id = ?',
            [$folderId, (int) $board['id']],
        ));

        $this->assertRedirect($this->post('/settings/saved-feeds', [
            'name' => 'My org board',
            'board_id' => (int) $board['id'],
            'digest_enabled' => '1',
        ]));
        $feed = $this->db->fetch('SELECT * FROM saved_feed_filters WHERE user_id = ? AND name = ?', [(int) $user['id'], 'My org board']);
        self::assertIsArray($feed);
        self::assertSame(1, (int) $feed['digest_enabled']);
        self::assertSame([(int) $board['id']], json_decode((string) $feed['filter_json'], true)['board_ids']);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_folders WHERE user_id = ?', [(int) $other['id']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM saved_feed_filters WHERE user_id = ?', [(int) $other['id']]));

        $page = $this->get('/settings/boards');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('personal-org-grid', $page->body());
        self::assertStringContainsString('board-folder-card', $page->body());
        self::assertStringContainsString('Work', $page->body());
        self::assertStringContainsString('My org board', $page->body());
        self::assertStringContainsString('Saved feeds', $page->body());
        self::assertStringContainsString('id="board-folder-id"', $page->body());
        self::assertStringContainsString('id="board-folder-board-id"', $page->body());
        self::assertStringContainsString('id="saved-feed-board-id"', $page->body());
    }

    public function test_invalid_personal_organization_names_do_not_hit_the_500_handler(): void
    {
        $this->makeAdmin();
        $this->setFlags(['board_folders' => true, 'saved_feeds' => true]);
        $user = $this->makeUser(['username' => 'orginvalid']);
        $board = $this->makeBoard($this->makeCategory('Org Invalid'), ['slug' => 'org-invalid']);
        $this->actingAs($user);

        $this->assertRedirect($this->post('/settings/board-folders', ['name' => '']), '/settings/boards');
        $this->assertStatus(200, $this->get('/settings/boards'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM board_folders WHERE user_id = ?', [(int) $user['id']]));

        $tooLong = str_repeat('x', 81);
        $this->assertRedirect($this->post('/settings/saved-feeds', [
            'name' => $tooLong,
            'board_id' => (int) $board['id'],
        ]), '/settings/boards');
        $page = $this->get('/settings/boards');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Name must be 1 to 80 characters.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM saved_feed_filters WHERE user_id = ?', [(int) $user['id']]));
    }

    public function test_board_folder_and_saved_feed_rendering_hides_boards_that_are_no_longer_readable(): void
    {
        $admin = $this->makeAdmin(['username' => 'orgscopeadmin']);
        $this->setFlags(['board_folders' => true, 'saved_feeds' => true]);
        $user = $this->makeUser(['username' => 'orgscopeuser']);
        $board = $this->makeBoard($this->makeCategory('Scoped Org'), [
            'name' => 'Veiled Council',
            'slug' => 'veiled-council',
            'visibility' => 'private',
        ]);
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $board['id'], (int) $user['id'], (int) $admin['id']);

        $this->actingAs($user);
        $this->assertRedirect($this->post('/settings/board-folders', ['name' => 'Council rail']), '/settings/boards');
        $folderId = (int) $this->db->fetchValue('SELECT id FROM board_folders WHERE user_id = ? AND name = ?', [(int) $user['id'], 'Council rail']);
        $this->assertRedirect($this->post('/settings/board-folders/' . $folderId . '/boards', ['board_id' => (int) $board['id']]), '/settings/boards');
        $this->assertRedirect($this->post('/settings/saved-feeds', [
            'name' => 'Private briefings',
            'board_id' => (int) $board['id'],
        ]), '/settings/boards');

        $before = $this->get('/settings/boards');
        $this->assertStatus(200, $before);
        self::assertStringContainsString('Veiled Council', $before->body());
        self::assertStringContainsString('1 board filter', $before->body());

        $members->remove((int) $board['id'], (int) $user['id']);

        $after = $this->get('/settings/boards');
        $this->assertStatus(200, $after);
        self::assertStringContainsString('Council rail', $after->body());
        self::assertStringContainsString('Private briefings', $after->body());
        self::assertStringNotContainsString('Veiled Council', $after->body());
        self::assertStringContainsString('0 board filters', $after->body());
        self::assertStringNotContainsString('1 board filter', $after->body());
    }

    public function test_user_creates_private_bookmark_folder_for_starred_threads(): void
    {
        $this->makeAdmin();
        $this->setFlags(['bookmark_folders' => true]);
        $user = $this->makeUser(['username' => 'bookmarkuser']);
        $other = $this->makeUser(['username' => 'bookmarkother']);
        $board = $this->makeBoard($this->makeCategory('Bookmarks'), ['slug' => 'bookmarks']);
        $thread = $this->makeThread($board, $user, 'Keep this', 'OP.');
        (new \App\Repository\ThreadUserRepository($this->db))->setStar((int) $user['id'], (int) $thread['thread_id'], true);
        $this->actingAs($user);

        $this->assertRedirect($this->post('/settings/bookmark-folders', ['name' => 'Read later']), '/settings/boards');
        $folderId = (int) $this->db->fetchValue('SELECT id FROM thread_bookmark_folders WHERE user_id = ? AND name = ?', [(int) $user['id'], 'Read later']);
        self::assertGreaterThan(0, $folderId);

        $this->assertRedirect($this->post('/settings/bookmark-folders/' . $folderId . '/threads', ['thread_id' => (int) $thread['thread_id']]), '/settings/boards');
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_bookmark_folder_threads WHERE folder_id = ? AND thread_id = ?',
            [$folderId, (int) $thread['thread_id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_bookmark_folders WHERE user_id = ?', [(int) $other['id']]));

        $page = $this->get('/settings/boards');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('bookmark-folder-list', $page->body());
        self::assertStringContainsString('Read later', $page->body());
        self::assertStringContainsString('Keep this', $page->body());
        self::assertStringContainsString('id="bookmark-folder-id"', $page->body());
        self::assertStringContainsString('id="bookmark-thread-id"', $page->body());
    }

    public function test_bookmark_folder_rejects_unstarred_threads(): void
    {
        $this->makeAdmin();
        $this->setFlags(['bookmark_folders' => true]);
        $user = $this->makeUser(['username' => 'bookmarkinvalid']);
        $board = $this->makeBoard($this->makeCategory('Bookmark Invalid'), ['slug' => 'bookmark-invalid']);
        $thread = $this->makeThread($board, $user, 'Not starred', 'OP.');
        $this->actingAs($user);
        $this->post('/settings/bookmark-folders', ['name' => 'Read later']);
        $folderId = (int) $this->db->fetchValue('SELECT id FROM thread_bookmark_folders WHERE user_id = ?', [(int) $user['id']]);

        $this->assertRedirect($this->post('/settings/bookmark-folders/' . $folderId . '/threads', ['thread_id' => (int) $thread['thread_id']]), '/settings/boards');
        $this->assertSeeText($this->get('/settings/boards'), 'Star the thread before adding it to a bookmark folder.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_bookmark_folder_threads WHERE folder_id = ?', [$folderId]));
    }

    public function test_bookmark_folders_hide_threads_that_are_no_longer_readable(): void
    {
        $admin = $this->makeAdmin(['username' => 'bookmarkscopeadmin']);
        $this->setFlags(['bookmark_folders' => true]);
        $user = $this->makeUser(['username' => 'bookmarkscopeuser']);
        $board = $this->makeBoard($this->makeCategory('Secret Bookmarks'), [
            'name' => 'Secret Reading',
            'slug' => 'secret-reading',
            'visibility' => 'private',
        ]);
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $board['id'], (int) $user['id'], (int) $admin['id']);
        $thread = $this->makeThread($board, $user, 'Secret thread', 'OP.');
        $threads = new ThreadUserRepository($this->db);
        $threads->setStar((int) $user['id'], (int) $thread['thread_id'], true);

        $this->actingAs($user);
        $this->assertRedirect($this->post('/settings/bookmark-folders', ['name' => 'Read later']), '/settings/boards');
        $folderId = (int) $this->db->fetchValue('SELECT id FROM thread_bookmark_folders WHERE user_id = ? AND name = ?', [(int) $user['id'], 'Read later']);
        $this->assertRedirect($this->post('/settings/bookmark-folders/' . $folderId . '/threads', ['thread_id' => (int) $thread['thread_id']]), '/settings/boards');

        $before = $this->get('/settings/boards');
        $this->assertStatus(200, $before);
        self::assertStringContainsString('Secret thread', $before->body());

        $members->remove((int) $board['id'], (int) $user['id']);

        $after = $this->get('/settings/boards');
        $this->assertStatus(200, $after);
        self::assertStringContainsString('Read later', $after->body());
        self::assertStringNotContainsString('Secret thread', $after->body());
    }

    public function test_unstarred_threads_are_pruned_from_bookmark_folder_membership_on_render(): void
    {
        $this->makeAdmin();
        $this->setFlags(['bookmark_folders' => true]);
        $user = $this->makeUser(['username' => 'bookmarkprune']);
        $board = $this->makeBoard($this->makeCategory('Bookmark Prune'), ['slug' => 'bookmark-prune']);
        $thread = $this->makeThread($board, $user, 'Pruned topic', 'OP.');
        $threads = new ThreadUserRepository($this->db);
        $threads->setStar((int) $user['id'], (int) $thread['thread_id'], true);

        $this->actingAs($user);
        $this->assertRedirect($this->post('/settings/bookmark-folders', ['name' => 'Keep handy']), '/settings/boards');
        $folderId = (int) $this->db->fetchValue('SELECT id FROM thread_bookmark_folders WHERE user_id = ? AND name = ?', [(int) $user['id'], 'Keep handy']);
        $this->assertRedirect($this->post('/settings/bookmark-folders/' . $folderId . '/threads', ['thread_id' => (int) $thread['thread_id']]), '/settings/boards');

        $threads->setStar((int) $user['id'], (int) $thread['thread_id'], false);

        $page = $this->get('/settings/boards');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('Keep handy', $page->body());
        self::assertStringNotContainsString('Pruned topic', $page->body());
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_bookmark_folder_threads WHERE folder_id = ? AND thread_id = ?',
            [$folderId, (int) $thread['thread_id']],
        ));
    }

    public function test_limited_custom_profile_fields_render_on_public_profile(): void
    {
        $this->makeAdmin();
        $this->setFlags(['custom_profile_fields' => true]);
        $user = $this->makeUser(['username' => 'customprofile']);
        $this->actingAs($user);

        $this->assertRedirect($this->post('/settings/account', [
            'display_name' => 'Custom Profile',
            'custom_label_1' => 'Favorite editor',
            'custom_value_1' => 'Vim <script>alert(1)</script>',
            'custom_label_2' => 'Timezone',
            'custom_value_2' => 'UTC',
        ]), '/settings/account');

        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_profile_fields WHERE user_id = ?', [(int) $user['id']]));
        $profile = $this->get('/u/customprofile');
        $this->assertSeeText($profile, 'Favorite editor');
        $this->assertSeeText($profile, 'Vim &lt;script&gt;alert(1)&lt;/script&gt;');
        $this->assertDontSeeText($profile, '<script>alert(1)</script>');
        $this->assertSeeText($profile, 'Timezone');
        $this->assertSeeText($profile, 'UTC');
    }

    public function test_custom_profile_fields_are_bounded(): void
    {
        $this->makeAdmin();
        $this->setFlags(['custom_profile_fields' => true]);
        $user = $this->makeUser(['username' => 'custominvalid']);
        $this->actingAs($user);

        $res = $this->post('/settings/account', [
            'display_name' => 'Custom Invalid',
            'custom_label_1' => str_repeat('l', 41),
            'custom_value_1' => 'value',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Custom profile labels are limited to 40 characters.');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_profile_fields WHERE user_id = ?', [(int) $user['id']]));
    }
}
