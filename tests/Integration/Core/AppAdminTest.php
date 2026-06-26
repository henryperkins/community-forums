<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

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
        $this->assertRedirect($response, '/admin');

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

    public function test_board_can_only_be_deleted_when_empty(): void
    {
        $board = $this->makeBoard($this->categoryId, ['slug' => 'tmp', 'name' => 'Temp']);
        $author = $this->makeUser();
        $this->makeThread($board, $author, 'a thread');

        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $blocked = $this->post('/admin/boards/' . $board['id'] . '/delete');
        $this->assertRedirect($blocked);
        self::assertNotNull($this->boards()->find((int) $board['id'])); // still there

        // Make it empty, then deletion succeeds.
        $this->db->run('DELETE FROM posts WHERE thread_id IN (SELECT id FROM threads WHERE board_id = ?)', [(int) $board['id']]);
        $this->db->run('DELETE FROM threads WHERE board_id = ?', [(int) $board['id']]);

        $this->get('/admin/structure');
        $ok = $this->post('/admin/boards/' . $board['id'] . '/delete');
        $this->assertRedirect($ok);
        self::assertNull($this->boards()->find((int) $board['id']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'delete_board'"));
    }

    public function test_category_can_only_be_deleted_when_empty(): void
    {
        $this->makeBoard($this->categoryId, ['slug' => 'inside']);
        $this->actingAs($this->admin);
        $this->get('/admin/structure');

        $blocked = $this->post('/admin/categories/' . $this->categoryId . '/delete');
        $this->assertRedirect($blocked);
        self::assertNotNull($this->db->fetch('SELECT * FROM categories WHERE id = ?', [$this->categoryId]));

        $empty = $this->makeCategory('Empty');
        $this->get('/admin/structure');
        $ok = $this->post('/admin/categories/' . $empty . '/delete');
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
}
