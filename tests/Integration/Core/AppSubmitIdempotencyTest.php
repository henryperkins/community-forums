<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\IdempotencyRepository;
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

    /**
     * Repository contract behind the §9 #4 concurrency fix: a second writer that
     * collides on the unique (user, key) loses the race — record() returns false
     * (not an exception, not an overwrite) so the caller can replay the winner.
     * The sequential HTTP tests above only exercise the pre-transaction find();
     * this drives the duplicate-key INSERT path directly.
     */
    public function test_record_reports_a_lost_race_on_a_duplicate_key(): void
    {
        $author = $this->makeUser(['username' => 'idemrepo']);
        $repo = new IdempotencyRepository($this->db);
        $key = (string) $repo->hash('client-token-42');

        self::assertTrue(
            $repo->record((int) $author['id'], $key, 'thread', 'thread', 101),
            'the first writer claims the key',
        );
        self::assertFalse(
            $repo->record((int) $author['id'], $key, 'thread', 'thread', 202),
            'a concurrent duplicate must lose the race (false), not throw or overwrite',
        );
        // The stored result still points at the first writer; the loser never wins.
        self::assertSame(
            ['result_type' => 'thread', 'result_id' => 101],
            $repo->find((int) $author['id'], $key),
        );
    }

    /**
     * Service branch behind the §9 #4 fix, exercised through the real stack: the
     * in-transaction claim, not just the pre-transaction find(). A pre-seeded
     * claim that resolves to a *reply* lets the request slip past createThread's
     * early dedup (which only short-circuits on a matching 'thread' result) and
     * fall into the transaction, where the unique-key INSERT collides on that row
     * — exactly the concurrency window (find() missed, record() lost the race).
     * DuplicateSubmissionException is raised, and the result_type guard refuses to
     * replay a reply as a thread, so the duplicate is rejected rather than
     * mis-served. This drives record()→false and the catch branch with no stub
     * (the repositories are final by convention).
     */
    public function test_in_transaction_collision_is_rejected_as_a_duplicate(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'idemrace']);
        $author = $this->makeUser(['username' => 'idemracer']);
        $this->actingAs($author);

        $repo = new IdempotencyRepository($this->db);
        $key = (string) $repo->hash('tok-cross');
        self::assertTrue($repo->record((int) $author['id'], $key, 'reply', 'post', 555));

        $res = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Crosses a reply token',
            'body' => 'A genuinely new thread body.',
            'idempotency_key' => 'tok-cross',
        ]);

        // The in-transaction claim collided and the cross-type guard rejected it,
        // so the client is told it was already submitted rather than getting a
        // second logical post under the same key. (The loser row is not rolled
        // back under the nested-transaction test harness — Database::transaction
        // reuses the active transaction without savepoints — so we assert the
        // user-visible rejection, not the row count.)
        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'already submitted');
    }
}
