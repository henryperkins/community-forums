<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

final class AppAdminArchiveTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->categoryId = $this->makeCategory('General');
    }

    public function test_member_cannot_create_thread_or_reply_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcboard', 'name' => 'ArcBoard']);
        $thread = $this->makeThread($board, $member, 'Pre-archive topic'); // seeded BEFORE archive
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $create = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'New topic after archive',
            'body' => 'Should be rejected.',
        ]);
        $this->assertStatus(403, $create);

        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Should be rejected too.']);
        $this->assertStatus(403, $reply);
    }

    public function test_owner_cannot_edit_or_delete_a_post_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcowner']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcedit', 'name' => 'ArcEdit']);
        $thread = $this->makeThread($board, $member, 'Editable topic', 'Original body here.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/edit', ['body' => 'Trying to edit after archive.']));
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/delete'));
    }

    public function test_admin_and_board_moderator_are_also_blocked_from_writing(): void
    {
        $author = $this->makeUser(['username' => 'arcauth']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcmod', 'name' => 'ArcMod']);
        $thread = $this->makeThread($board, $author, 'Topic in a soon-archived board');
        $mod = $this->makeUser(['username' => 'arcmoduser']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'admin reply blocked']));

        $this->logoutClient();
        $this->actingAs($mod);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'mod reply blocked']));
    }

    public function test_unarchive_restores_writability(): void
    {
        $member = $this->makeUser(['username' => 'rearcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'rearc', 'name' => 'ReArc']);
        $thread = $this->makeThread($board, $member, 'Reopenable topic');

        // Archive then unarchive through the admin service path (not raw SQL).
        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $this->post('/admin/boards/' . $board['id'] . '/archive');
        $this->post('/admin/boards/' . $board['id'] . '/unarchive');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unarchive_board'"));

        $this->logoutClient();
        $this->actingAs($member);
        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Posting works again.']);
        $this->assertRedirectContains($reply, '/t/' . $thread['thread_id']);
    }
}
