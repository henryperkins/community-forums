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
        $this->actingAs($user);

        $this->assertStatus(404, $this->post('/settings/board-folders', ['name' => 'Work']));
        $this->assertStatus(404, $this->post('/settings/board-folders/1/boards', ['board_id' => (int) $board['id']]));
        $this->assertStatus(404, $this->post('/settings/saved-feeds', ['name' => 'Mine', 'board_id' => (int) $board['id']]));
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
}
