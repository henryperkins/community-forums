<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-03: a logical submit creates at most one thread/reply per (user, client
 * idempotency token). A double-click / retry / browser resend replays the first
 * result instead of creating a duplicate.
 */
final class AppSubmitIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    public function test_duplicate_thread_submit_creates_one_thread(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'idem']);
        $author = $this->makeUser(['username' => 'idemposter']);
        $this->actingAs($author);

        $payload = ['board_id' => (int) $board['id'], 'title' => 'Once', 'body' => 'Only once.', 'idempotency_key' => 'tok-thread-1'];
        $first = $this->post('/threads', $payload);
        $second = $this->post('/threads', $payload);

        $this->assertRedirectContains($first, '/t/');
        $this->assertRedirectContains($second, '/t/');
        self::assertSame(
            (string) $first->getHeader('location'),
            (string) $second->getHeader('location'),
            'the replay must point at the same thread',
        );
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE user_id = ?', [(int) $author['id']]));
    }

    public function test_duplicate_reply_submit_creates_one_post(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'idem2']);
        $author = $this->makeUser(['username' => 'idemreplier']);
        $thread = $this->makeThread($board, $author, 'Topic', 'Opening.');
        $this->actingAs($author);

        $before = (int) $this->db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$thread['thread_id']]);
        $payload = ['body' => 'My one reply.', 'idempotency_key' => 'tok-reply-9'];
        $this->post('/t/' . $thread['thread_id'] . '/reply', $payload);
        $this->post('/t/' . $thread['thread_id'] . '/reply', $payload);

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts WHERE thread_id = ? AND is_op = 0',
            [$thread['thread_id']],
        ));
        self::assertSame($before + 1, (int) $this->db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_missing_key_does_not_dedupe(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'idem3']);
        $author = $this->makeUser(['username' => 'nokey']);
        $this->actingAs($author);

        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'A', 'body' => 'first']);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'B', 'body' => 'second']);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE user_id = ?', [(int) $author['id']]));
    }
}
