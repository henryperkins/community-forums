<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * Member controls (P2-10): privacy, reading preferences (server-enforced),
 * board organization, blocks list, and digest settings.
 */
final class AppUserPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_privacy_settings_persist(): void
    {
        $user = $this->makeUser(['username' => 'priv']);
        $this->actingAs($user);

        $this->assertStatus(200, $this->get('/settings/privacy'));
        $res = $this->post('/settings/privacy', [
            'profile_visibility' => 'members',
            'allow_dms' => 'none',
            // show_presence unchecked → off
            'hide_from_leaderboard' => '1',
        ]);
        $this->assertRedirect($res, '/settings/privacy');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('members', $row['profile_visibility']);
        self::assertSame('none', $row['allow_dms']);
        self::assertSame(0, (int) $row['show_presence']);

        $prefs = (new UserPreferenceRepository($this->db))->get((int) $user['id']);
        self::assertTrue($prefs['hide_from_leaderboard']);
    }

    public function test_reading_preference_is_server_enforced(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'pref-board']);
        $user = $this->makeUser(['username' => 'reader']);

        $t = $this->makeThread($board, $user, 'Long thread');
        for ($i = 0; $i < 10; $i++) {
            $this->posting()->reply($this->userEntity($user), $t['thread_id'], ['body' => "reply $i"]);
        }

        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->actingAs($user);
        // Default (20/page): 11 posts fit on one page.
        $this->assertDontSeeText($this->get($url), 'page=2');

        // Set 10/page: now there is a second page.
        $this->post('/settings/preferences', ['posts_per_page' => '10']);
        $this->assertSeeText($this->get($url), 'page=2');
    }

    public function test_board_mute_hides_from_sidebar(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'muteme', 'name' => 'MuteMe']);
        // A second board gives us a page whose MAIN content doesn't list 'muteme',
        // so the only place '/c/muteme' can appear is the sidebar nav.
        $this->makeBoard($cat, ['slug' => 'elsewhere', 'name' => 'Elsewhere']);
        $user = $this->makeUser(['username' => 'muter']);
        $this->actingAs($user);

        $this->assertSeeText($this->get('/c/elsewhere'), '/c/muteme');

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'mute']);
        self::assertSame(1, (int) (new UserBoardPrefRepository($this->db))->forUser((int) $user['id'])[(int) $board['id']]['is_muted']);

        $this->assertDontSeeText($this->get('/c/elsewhere'), '/c/muteme');
    }

    public function test_board_favorite_persists(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'favme']);
        $user = $this->makeUser(['username' => 'faver']);
        $this->actingAs($user);

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'favorite']);
        self::assertSame(1, (int) (new UserBoardPrefRepository($this->db))->forUser((int) $user['id'])[(int) $board['id']]['is_favorite']);
    }

    public function test_blocks_page_lists_blocked_users(): void
    {
        $me = $this->makeUser(['username' => 'blocker']);
        $foe = $this->makeUser(['username' => 'foe']);
        (new BlockRepository($this->db))->block((int) $me['id'], (int) $foe['id']);

        $this->actingAs($me);
        $res = $this->get('/settings/blocks');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, '@foe');
    }

    public function test_digest_settings_persist(): void
    {
        $user = $this->makeUser(['username' => 'digester']);
        $this->actingAs($user);

        $res = $this->post('/settings/notifications', ['timezone' => 'America/New_York', 'digest_hour' => '8']);
        $this->assertRedirect($res, '/settings/notifications');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('America/New_York', $row['timezone']);
        self::assertSame(8, (int) $row['digest_hour']);
    }

    public function test_guest_cannot_reach_member_controls(): void
    {
        $this->assertRedirectContains($this->get('/settings/privacy'), '/login');
        $this->assertRedirectContains($this->get('/settings/preferences'), '/login');
        $this->assertRedirectContains($this->get('/settings/sessions'), '/login');
        $this->assertRedirectContains($this->get('/settings/blocks'), '/login');
        $this->assertRedirectContains($this->get('/settings/boards'), '/login');
    }
}
