<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
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
