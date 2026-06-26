<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppModerationTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $member;
    /** @var array<string,mixed> */
    private array $board;
    /** @var array{thread_id:int,slug:string} */
    private array $thread;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'mod']);
        $this->member = $this->makeUser(['username' => 'member']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'general']);
        $this->thread = $this->makeThread($this->board, $this->member, 'A topic', 'OP body');
    }

    private function threadUrl(): string
    {
        return '/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug'];
    }

    public function test_admin_pins_and_unpins_with_audit(): void
    {
        $this->actingAs($this->admin);
        $this->get($this->threadUrl());

        $this->post('/mod/t/' . $this->thread['thread_id'] . '/pin');
        self::assertSame(1, (int) $this->threads()->find($this->thread['thread_id'])['is_pinned']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'pin'"));

        $this->get($this->threadUrl());
        $this->post('/mod/t/' . $this->thread['thread_id'] . '/pin');
        self::assertSame(0, (int) $this->threads()->find($this->thread['thread_id'])['is_pinned']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unpin'"));
    }

    public function test_admin_locks_and_unlocks_with_audit(): void
    {
        $this->actingAs($this->admin);
        $this->get($this->threadUrl());

        $this->post('/mod/t/' . $this->thread['thread_id'] . '/lock');
        self::assertSame(1, (int) $this->threads()->find($this->thread['thread_id'])['is_locked']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'lock'"));

        // Toggle back: unlock, with its own audit row.
        $this->get($this->threadUrl());
        $this->post('/mod/t/' . $this->thread['thread_id'] . '/lock');
        self::assertSame(0, (int) $this->threads()->find($this->thread['thread_id'])['is_locked']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unlock'"));
    }

    public function test_admin_soft_deletes_any_post_with_reason_and_audit(): void
    {
        $reply = $this->posting()->reply($this->userEntity($this->member), $this->thread['thread_id'], ['body' => 'spam reply']);

        $this->actingAs($this->admin);
        $this->get($this->threadUrl());
        $this->post('/posts/' . $reply . '/delete', ['reason' => 'spam']);

        $post = $this->posts()->find($reply);
        self::assertSame(1, (int) $post['is_deleted']);
        self::assertSame((int) $this->admin['id'], (int) $post['deleted_by']);

        $log = $this->db->fetch("SELECT * FROM moderation_log WHERE action = 'delete_post'");
        self::assertNotNull($log);
        self::assertSame('spam', $log['reason']);
        self::assertSame('post', $log['target_type']);
    }

    public function test_admin_delete_requires_a_reason(): void
    {
        $reply = $this->posting()->reply($this->userEntity($this->member), $this->thread['thread_id'], ['body' => 'reply']);

        $this->actingAs($this->admin);
        $this->get($this->threadUrl());
        $this->post('/posts/' . $reply . '/delete', ['reason' => '']);

        self::assertSame(0, (int) $this->posts()->find($reply)['is_deleted']);
    }

    public function test_non_admin_cannot_moderate(): void
    {
        $this->actingAs($this->member);
        $this->get($this->threadUrl());
        $this->assertStatus(403, $this->post('/mod/t/' . $this->thread['thread_id'] . '/pin'));
        $this->assertStatus(403, $this->post('/mod/t/' . $this->thread['thread_id'] . '/lock'));
    }
}
