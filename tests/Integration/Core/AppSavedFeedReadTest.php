<?php

declare(strict_types=1);
namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppSavedFeedReadTest extends TestCase
{
    private function fixture(): array
    {
        $this->makeAdmin(); $user = $this->makeUser(); $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Selected visible topic');
        $other = $this->makeThread($this->makeBoard($this->makeCategory()), $author, 'Unrelated public topic');
        $this->actingAs($user);
        $this->post('/settings/saved-feeds', ['name' => 'My scope', 'board_id' => $board['id'], 'digest_enabled' => '1']);
        $id = (int) $this->db->fetchValue('SELECT id FROM saved_feed_filters WHERE user_id = ?', [$user['id']]);
        return compact('user', 'author', 'board', 'thread', 'other', 'id');
    }

    public function test_owned_feed_opens_and_manages_with_rail_shortcut(): void
    {
        $f = $this->fixture(); $id = $f['id'];
        $page = $this->get('/feeds/saved/' . $id); $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Selected visible topic'); $this->assertDontSeeText($page, 'Unrelated public topic');
        $dom = new \DOMDocument(); @$dom->loadHTML($page->body());
        self::assertSame(1, (new \DOMXPath($dom))->query('//*[@id="sidebar-nav"]//a[@href="/feeds/saved/' . $id . '"]')->length);
        $this->assertRedirect($this->post('/settings/saved-feeds/' . $id, ['name' => 'Renamed', 'board_id' => $f['board']['id']]));
        $this->assertSeeText($this->get('/feeds/saved/' . $id), 'Renamed');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT digest_enabled FROM saved_feed_filters WHERE id = ?', [$id]));
        $this->assertRedirect($this->post('/settings/saved-feeds/' . $id . '/delete'));
        $this->assertStatus(404, $this->get('/feeds/saved/' . $id));
        self::assertNotNull($this->db->fetch('SELECT id FROM threads WHERE id = ?', [$f['thread']['thread_id']]));
    }

    public function test_selected_revoked_and_corrupt_filters_never_expand_to_all_boards(): void
    {
        $f = $this->fixture(); $path = '/feeds/saved/' . $f['id'];
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$f['board']['id']]);
        $page = $this->get($path); $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, 'Selected visible topic'); $this->assertDontSeeText($page, 'Unrelated public topic');
        foreach (['null', '{"board_ids":"oops","sort":"latest"}', '{"board_ids":[0],"sort":"latest"}', '{"board_ids":[],"sort":"latest","unsupported_filter":true}'] as $json) {
            $this->db->run('UPDATE saved_feed_filters SET filter_json = ? WHERE id = ?', [$json, $f['id']]);
            $page = $this->get($path); $this->assertStatus(200, $page); $this->assertSeeText($page, 'unavailable');
            $this->assertDontSeeText($page, 'Unrelated public topic');
        }
        $this->db->run('UPDATE saved_feed_filters SET filter_json = ? WHERE id = ?', ['{"board_ids":[],"sort":"latest"}', $f['id']]);
        $this->assertSeeText($this->get($path), 'Unrelated public topic');
    }

    public function test_foreign_ids_are_not_readable_or_mutable(): void
    {
        $f = $this->fixture(); $this->actingAs($f['author']);
        $this->assertStatus(404, $this->get('/feeds/saved/' . $f['id']));
        $this->assertStatus(404, $this->post('/settings/saved-feeds/' . $f['id'], ['digest_enabled' => '0']));
        $this->assertStatus(404, $this->post('/settings/saved-feeds/' . $f['id'] . '/delete'));
    }

    public function test_all_restricted_states_allow_only_digest_reduction(): void
    {
        $f = $this->fixture(); $id = $f['id'];
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$f['board']['id']]);
        foreach (['suspended', 'deactivated', 'pending_deletion', 'banned'] as $state) {
            $this->db->run("UPDATE users SET status = ?, suspended_until = '2099-01-01' WHERE id = ?", [$state, $f['user']['id']]);
            $this->db->run('UPDATE saved_feed_filters SET digest_enabled = 1 WHERE id = ?', [$id]);
            $this->assertRedirect($this->post('/settings/saved-feeds/' . $id, ['digest_enabled' => '0']));
            $this->assertStatus(403, $this->post('/settings/saved-feeds/' . $id, ['digest_enabled' => '1']));
            $this->assertStatus(403, $this->post('/settings/saved-feeds/' . $id, ['digest_enabled' => '0', 'name' => 'Changed']));
            $row = $this->db->fetch('SELECT * FROM saved_feed_filters WHERE id = ?', [$id]);
            self::assertSame('My scope', $row['name']); self::assertSame(0, (int) $row['digest_enabled']);
        }
    }

    public function test_folder_management_and_duplicate_names_preserve_records(): void
    {
        $f = $this->fixture();
        $this->assertRedirect($this->post('/settings/board-folders', ['name' => 'Work']));
        $id = (int) $this->db->fetchValue('SELECT id FROM board_folders WHERE user_id = ?', [$f['user']['id']]);
        $this->assertRedirect($this->post('/settings/board-folders/0/boards', ['folder_id' => $id, 'board_id' => $f['board']['id']]));
        $this->assertSeeText($this->get('/feed'), 'Work');
        $this->assertStatus(422, $this->post('/settings/board-folders', ['name' => 'Work']));
        $this->assertStatus(422, $this->post('/settings/saved-feeds', ['name' => 'My scope']));
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$f['board']['id']]);
        $this->assertRedirect($this->post('/settings/board-folders/' . $id . '/boards/' . $f['board']['id'] . '/remove'));
        $this->assertRedirect($this->post('/settings/board-folders/' . $id . '/rename', ['name' => 'New folder']));
        $this->assertRedirect($this->post('/settings/board-folders/' . $id . '/delete'));
        self::assertNotNull($this->db->fetch('SELECT id FROM boards WHERE id = ?', [$f['board']['id']]));
    }
    public function test_legacy_multiple_selected_boards_survive_updates_and_exclude_blocked_and_anonymous_posts(): void
    {
        $f = $this->fixture(); $otherBoard = (int) $this->db->fetchValue('SELECT board_id FROM threads WHERE id=?', [$f['other']['thread_id']]);
        $ids = [(int) $f['board']['id'], $otherBoard];
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', [json_encode(['board_ids' => $ids, 'sort' => 'latest']), $f['id']]);
        $page = $this->get('/feeds/saved/' . $f['id']);
        $this->assertSeeText($page, 'Selected visible topic'); $this->assertSeeText($page, 'Unrelated public topic');
        $settings = $this->get('/settings/boards');
        $dom = new \DOMDocument(); @$dom->loadHTML($settings->body()); $xp = new \DOMXPath($dom);
        self::assertSame(2, $xp->query('//select[@name="board_ids[]"]//option[@selected]')->length);
        $this->assertRedirect($this->post('/settings/saved-feeds/' . $f['id'], ['name' => 'Legacy selection', 'board_ids' => array_map('strval', $ids), 'digest_enabled' => '1']));
        self::assertSame($ids, json_decode($this->db->fetchValue('SELECT filter_json FROM saved_feed_filters WHERE id=?', [$f['id']]), true)['board_ids']);
        $this->db->run('UPDATE posts SET is_anonymous=1 WHERE thread_id=?', [$f['thread']['thread_id']]);
        $page = $this->get('/feeds/saved/' . $f['id']); $this->assertDontSeeText($page, 'Selected visible topic');
        foreach ([[(int) $f['user']['id'], (int) $f['author']['id']], [(int) $f['author']['id'], (int) $f['user']['id']]] as [$a, $b]) {
            $blocks = new \App\Repository\BlockRepository($this->db); $blocks->block($a, $b);
            $this->assertDontSeeText($this->get('/feeds/saved/' . $f['id']), 'Unrelated public topic'); $blocks->unblock($a, $b);
        }
        (new \App\Repository\SettingRepository($this->db))->set('features', ['saved_feeds' => false]);
        $this->assertStatus(404, $this->get('/feeds/saved/' . $f['id']));
        $this->assertStatus(404, $this->post('/settings/saved-feeds/' . $f['id'], ['digest_enabled' => '0']));
    }

    public function test_duplicate_rename_and_invalid_folder_add_preserve_form_selection(): void
    {
        $f = $this->fixture();
        $this->post('/settings/saved-feeds', ['name' => 'Another feed']);
        $response = $this->post('/settings/saved-feeds/' . $f['id'], ['name' => 'Another feed', 'board_id' => $f['board']['id'], 'digest_enabled' => '1']);
        $this->assertStatus(422, $response); $this->assertSeeText($response, 'already have');
        self::assertSame('My scope', $this->db->fetchValue('SELECT name FROM saved_feed_filters WHERE id=?', [$f['id']]));
        $this->post('/settings/board-folders', ['name' => 'First']); $this->post('/settings/board-folders', ['name' => 'Second']);
        $folder = (int) $this->db->fetchValue("SELECT id FROM board_folders WHERE user_id=? AND name='Second'", [$f['user']['id']]);
        $this->assertStatus(422, $this->post('/settings/board-folders/' . $folder . '/rename', ['name' => 'First']));
        $response = $this->post('/settings/board-folders/0/boards', ['folder_id' => $folder, 'board_id' => '']);
        $this->assertStatus(422, $response);
        $dom = new \DOMDocument(); @$dom->loadHTML($response->body()); $xp = new \DOMXPath($dom);
        self::assertSame(1, $xp->query('//select[@name="folder_id"]//option[@value="' . $folder . '" and @selected]')->length);
        $this->actingAs($f['author']);
        foreach (['/rename', '/delete', '/boards/' . $f['board']['id'] . '/remove'] as $suffix) {
            $this->assertStatus(404, $this->post('/settings/board-folders/' . $folder . $suffix, ['name' => 'Foreign']));
        }
    }

    public function test_malformed_form_fields_return_validation_errors_without_mutation(): void
    {
        $f = $this->fixture();
        foreach ([['name' => ['nested']], ['name' => 'Valid', 'digest_enabled' => ['nested']], ['name' => 'Valid', 'board_id' => ['nested']]] as $input) {
            $this->assertStatus(422, $this->post('/settings/saved-feeds', $input));
        }
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM saved_feed_filters WHERE user_id=?', [$f['user']['id']]));
    }

    public function test_explicit_selected_boards_use_direct_read_access_while_all_keeps_latest_discovery(): void
    {
        $f = $this->fixture(); $url = '/feeds/saved/' . $f['id'];
        (new \App\Repository\SettingRepository($this->db))->set('features', ['notifications' => false, 'email' => false]);
        $this->db->run("UPDATE boards SET visibility='hidden' WHERE id=?", [$f['board']['id']]);
        $this->assertSeeText($this->get($url), 'Selected visible topic');
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', ['{"board_ids":[],"sort":"latest"}', $f['id']]);
        $this->assertDontSeeText($this->get($url), 'Selected visible topic');
        $this->db->run('UPDATE saved_feed_filters SET filter_json=? WHERE id=?', [json_encode(['board_ids'=>[(int) $f['board']['id']], 'sort'=>'latest']), $f['id']]);
        $this->db->run("UPDATE boards SET visibility='private' WHERE id=?", [$f['board']['id']]);
        $this->db->run("UPDATE users SET role='admin' WHERE id=?", [$f['user']['id']]);
        $this->assertSeeText($this->get($url), 'Selected visible topic');
        $this->db->run("UPDATE users SET role='user' WHERE id=?", [$f['user']['id']]);
        $mods = new \App\Repository\BoardModeratorRepository($this->db); $mods->assign((int) $f['board']['id'], (int) $f['user']['id']);
        $this->assertSeeText($this->get($url), 'Selected visible topic');
        $mods->unassign((int) $f['board']['id'], (int) $f['user']['id']);
        $this->assertDontSeeText($this->get($url), 'Selected visible topic');
    }

}
