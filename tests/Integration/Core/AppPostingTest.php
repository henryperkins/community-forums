<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPostingTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $author;
    /** @var array<string,mixed> */
    private array $board;
    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->author = $this->makeUser(['username' => 'poster']);
        $categoryId = $this->makeCategory('General');
        $this->board = $this->makeBoard($categoryId, ['slug' => 'general', 'name' => 'General']);
        $this->boardId = (int) $this->board['id'];
    }

    public function test_user_creates_a_thread_with_consistent_counters(): void
    {
        $this->actingAs($this->author);
        $this->get('/c/general');
        $response = $this->post('/threads', [
            'board_id' => $this->boardId,
            'title' => 'My first topic',
            'body' => 'Hello everyone!',
        ]);
        $this->assertRedirectContains($response, '/t/');

        $thread = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [$this->boardId]);
        self::assertNotNull($thread);
        self::assertSame(0, (int) $thread['reply_count']); // reply_count excludes the OP

        $op = $this->db->fetch('SELECT * FROM posts WHERE thread_id = ?', [$thread['id']]);
        self::assertSame(1, (int) $op['is_op']);

        $board = $this->boards()->find($this->boardId);
        self::assertSame(1, (int) $board['thread_count']);
        self::assertSame(1, (int) $board['post_count']);
        self::assertSame(1, (int) $this->users()->find((int) $this->author['id'])['post_count']);
    }

    public function test_reply_updates_counters_transactionally(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Topic', 'OP body');
        $replier = $this->makeUser(['username' => 'replier']);

        $this->actingAs($replier);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $response = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Nice topic!']);
        $this->assertRedirectContains($response, '#p');

        $row = $this->threads()->find($thread['thread_id']);
        self::assertSame(1, (int) $row['reply_count']);
        self::assertSame(2, (int) $this->boards()->find($this->boardId)['post_count']);
        self::assertSame(1, (int) $this->users()->find((int) $replier['id'])['post_count']);
    }

    public function test_locked_thread_rejects_replies(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Locked topic');
        $this->threads()->setLocked($thread['thread_id'], true);

        $replier = $this->makeUser();
        $this->actingAs($replier);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $response = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'let me in']);
        $this->assertStatus(403, $response);
        self::assertSame(0, (int) $this->threads()->find($thread['thread_id'])['reply_count']);
    }

    public function test_owner_can_edit_their_post(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Topic', 'original body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);

        $this->actingAs($this->author);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $response = $this->post('/posts/' . $postId . '/edit', ['body' => 'edited body']);
        $this->assertRedirectContains($response, '#p' . $postId);

        $post = $this->posts()->find($postId);
        self::assertSame('edited body', $post['body']);
        self::assertNotNull($post['edited_at']);
    }

    public function test_user_cannot_edit_another_users_post(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Topic');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);

        $other = $this->makeUser(['username' => 'intruder']);
        $this->actingAs($other);
        $this->get('/');
        $response = $this->post('/posts/' . $postId . '/edit', ['body' => 'hijacked']);
        $this->assertStatus(403, $response);
        self::assertNotSame('hijacked', $this->posts()->find($postId)['body']);
    }

    public function test_user_cannot_delete_another_users_post(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Topic');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);

        $other = $this->makeUser(['username' => 'notyours']);
        $this->actingAs($other);
        $this->get('/');
        $this->assertStatus(403, $this->post('/posts/' . $postId . '/delete'));
        self::assertSame(0, (int) $this->posts()->find($postId)['is_deleted']);
    }

    public function test_owner_soft_delete_hides_post_and_decrements_counters(): void
    {
        $thread = $this->makeThread($this->board, $this->author, 'Topic', 'OP');
        $replier = $this->makeUser(['username' => 'rep']);
        $reply = $this->posting()->reply($this->userEntity($replier), $thread['thread_id'], ['body' => 'zzz-reply-marker-xyz']);

        $this->actingAs($replier);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $response = $this->post('/posts/' . $reply . '/delete');
        $this->assertRedirectContains($response, '/t/');

        $post = $this->posts()->find($reply);
        self::assertSame(1, (int) $post['is_deleted']);
        self::assertSame(0, (int) $this->threads()->find($thread['thread_id'])['reply_count']);
        self::assertSame(1, (int) $this->boards()->find($this->boardId)['post_count']); // OP remains
        self::assertSame(0, (int) $this->users()->find((int) $replier['id'])['post_count']);

        // Deleted post is hidden from the thread view.
        $this->assertDontSeeText($this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']), 'zzz-reply-marker-xyz');
    }

    public function test_xss_payload_in_post_is_sanitised_on_render(): void
    {
        $this->actingAs($this->author);
        $this->get('/c/general');
        $this->post('/threads', [
            'board_id' => $this->boardId,
            'title' => 'XSS attempt',
            'body' => "Hi <script>alert('x')</script> [bad](javascript:alert(1))",
        ]);

        $thread = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [$this->boardId]);

        // The cached, sanitised render contains no executable content.
        $op = $this->db->fetch('SELECT body_html FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['id']]);
        self::assertStringNotContainsString('<script', (string) $op['body_html']);
        self::assertStringNotContainsString('javascript:', (string) $op['body_html']);

        // A reader (guest) never sees a live script or javascript: link in the page.
        $this->logoutClient();
        $view = $this->get('/t/' . $thread['id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $view);
        $this->assertDontSeeText($view, '<script>alert');
        $this->assertDontSeeText($view, 'href="javascript:');
    }

    public function test_guest_cannot_create_thread(): void
    {
        // Guest with a valid CSRF token still gets bounced to login.
        $this->get('/c/general');
        $response = $this->post('/threads', [
            'board_id' => $this->boardId,
            'title' => 'guest topic',
            'body' => 'nope',
        ]);
        $this->assertRedirectContains($response, '/login');
    }

    public function test_empty_body_is_rejected_with_validation_error(): void
    {
        $this->actingAs($this->author);
        $this->get('/c/general');
        $response = $this->post('/threads', [
            'board_id' => $this->boardId,
            'title' => 'Has title',
            'body' => '   ',
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Write something');
    }
}
