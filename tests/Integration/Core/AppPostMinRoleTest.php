<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Per-board `post_min_role` enforcement (PHASE_2_PLAN §3, Gate A): the board's
 * minimum posting role gates BOTH thread creation and replies. The role
 * vocabulary is shared with users.role and roles are cumulative
 * (admin ⊇ moderator ⊇ user). Reads are unaffected — only writes are gated.
 */
final class AppPostMinRoleTest extends TestCase
{
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist or the app stays in fresh-install (/setup) mode.
        $this->makeAdmin(['username' => 'siteadmin']);
        $this->categoryId = $this->makeCategory();
    }

    public function test_moderator_board_blocks_regular_user_for_threads_and_replies(): void
    {
        $mod = $this->makeUser(['username' => 'mod', 'role' => 'moderator']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'staff', 'post_min_role' => 'moderator']);
        $thread = $this->makeThread($board, $mod, 'Staff topic', 'OP');

        // A regular user can READ the board/thread but cannot write.
        $user = $this->makeUser(['username' => 'plain']);
        $this->actingAs($user);
        $view = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $view);
        $this->assertSeeText($view, "You don't have permission to reply in this board.");

        $this->assertStatus(403, $this->post('/threads', [
            'board_id' => $board['id'], 'title' => 'sneaky', 'body' => 'nope',
        ]));
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'nope']));

        // The blocked attempts created nothing.
        self::assertSame(1, (int) $this->boards()->find((int) $board['id'])['thread_count']);

        // A moderator can start a thread and reply.
        $this->actingAs($mod);
        $this->assertRedirectContains($this->post('/threads', [
            'board_id' => $board['id'], 'title' => 'Mod thread', 'body' => 'hello there',
        ]), '/t/');
        $this->assertRedirectContains(
            $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'a mod reply']),
            '#p',
        );
    }

    public function test_admin_board_blocks_moderator_but_allows_admin(): void
    {
        $admin = $this->makeAdmin(['username' => 'boss']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'announce', 'post_min_role' => 'admin']);
        $thread = $this->makeThread($board, $admin, 'Announcement', 'OP');

        // A global moderator is below the admin floor → blocked on both paths.
        $mod = $this->makeUser(['username' => 'mod2', 'role' => 'moderator']);
        $this->actingAs($mod);
        $this->assertStatus(403, $this->post('/threads', [
            'board_id' => $board['id'], 'title' => 'x', 'body' => 'yyyy',
        ]));
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'yyyy']));

        // The admin can.
        $this->actingAs($admin);
        $this->assertRedirectContains($this->post('/threads', [
            'board_id' => $board['id'], 'title' => 'Notice', 'body' => 'hello all',
        ]), '/t/');
        $this->assertRedirectContains(
            $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'an admin reply']),
            '#p',
        );
    }

    public function test_default_user_board_allows_any_active_user(): void
    {
        $author = $this->makeUser(['username' => 'a1']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'open', 'post_min_role' => 'user']);
        $thread = $this->makeThread($board, $author, 'Open topic', 'OP');

        $user = $this->makeUser(['username' => 'a2']);
        $this->actingAs($user);
        $this->assertRedirectContains($this->post('/threads', [
            'board_id' => $board['id'], 'title' => 'Mine', 'body' => 'hi everyone',
        ]), '/t/');
        $this->assertRedirectContains(
            $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'my normal reply']),
            '#p',
        );
    }
}
