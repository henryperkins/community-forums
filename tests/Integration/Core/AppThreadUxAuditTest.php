<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * Regression coverage for the thread/board UX audit (2026-07):
 *   #1 deleting the opening post retracts the topic (guarded) instead of
 *      leaving a headless, still-listed thread — and a moderator removing an
 *      opening post removes the whole topic (no sole-participant guard);
 *   #2 an assigned board moderator sees the pin/lock/remove controls their
 *      server permission already allows (previously admin-only in the view);
 *   #3 post actions (reply/react) keep the page + #anchor on paginated threads;
 *   #5 the no-JS board composer carries an idempotency key so a double-submit
 *      cannot create duplicate topics.
 */
final class AppThreadUxAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // initialise the app so the first-run setup gate doesn't intercept HTTP routes
    }

    // ---- #1 OP delete retracts a sole-authored topic ----------------------

    public function test_deleting_opening_post_retracts_a_sole_authored_topic(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $author = $this->makeUser(['username' => 'opener']);
        $thread = $this->makeThread($board, $author, 'Retract me', 'only my post');
        $opId = $this->opPostId($thread['thread_id']);

        $this->actingAs($author);
        $resp = $this->post('/posts/' . $opId . '/delete');

        // Sent back to the board, not to the now-removed thread.
        $this->assertRedirectContains($resp, '/c/general');
        // Thread is soft-deleted and drops off the board's thread tally...
        self::assertSame(1, (int) $this->threads()->find($thread['thread_id'])['is_deleted']);
        self::assertSame(0, (int) $this->boards()->find((int) $board['id'])['thread_count']);
        // ...so the board no longer lists an empty, orphaned topic.
        $this->assertDontSeeText($this->get('/c/general'), 'Retract me');
    }

    public function test_opening_post_delete_is_blocked_once_others_have_replied(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $author = $this->makeUser(['username' => 'opener2']);
        $other = $this->makeUser(['username' => 'replier']);
        $thread = $this->makeThread($board, $author, 'Shared topic', 'opening');
        $opId = $this->opPostId($thread['thread_id']);
        $this->posting()->reply($this->userEntity($other), $thread['thread_id'], ['body' => 'a reply from someone else']);

        $this->actingAs($author);
        $resp = $this->post('/posts/' . $opId . '/delete');

        // Refused: it would erase the other member's reply, so nothing is removed.
        $this->assertRedirectContains($resp, '#p' . $opId);
        self::assertSame(0, (int) $this->threads()->find($thread['thread_id'])['is_deleted']);
        self::assertSame(0, (int) $this->posts()->find($opId)['is_deleted']);
        $this->assertSeeText($this->get('/c/general'), 'Shared topic');
    }

    // ---- #2 board-moderator controls are visible (not admin-only) ---------

    public function test_board_moderator_sees_pin_lock_and_remove_controls(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $mod = $this->makeUser(['username' => 'boardmod']);
        $member = $this->makeUser(['username' => 'poster']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $thread = $this->makeThread($board, $member, 'Moderatable', 'body');
        $reply = $this->posting()->reply($this->userEntity($member), $thread['thread_id'], ['body' => 'a reply']);

        $this->actingAs($mod);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'action="/mod/t/' . $thread['thread_id'] . '/pin"');
        $this->assertSeeText($page, 'action="/mod/t/' . $thread['thread_id'] . '/lock"');
        $this->assertSeeText($page, 'action="/posts/' . $reply . '/delete"');
        $this->assertSeeText($page, 'Remove (mod)');
    }

    public function test_plain_member_sees_no_moderation_controls(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $member = $this->makeUser(['username' => 'poster2']);
        $thread = $this->makeThread($board, $member, 'Ordinary', 'body');
        $this->posting()->reply($this->userEntity($member), $thread['thread_id'], ['body' => 'own reply']);
        $viewer = $this->makeUser(['username' => 'viewer']);

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, '/mod/t/' . $thread['thread_id'] . '/pin');
        $this->assertDontSeeText($page, 'Remove (mod)');
    }

    public function test_moderator_removing_the_opening_post_removes_the_whole_topic(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $mod = $this->makeUser(['username' => 'topicmod']);
        $author = $this->makeUser(['username' => 'spammer']);
        $other = $this->makeUser(['username' => 'bystander']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $thread = $this->makeThread($board, $author, 'Spam topic', 'buy now');
        $opId = $this->opPostId($thread['thread_id']);
        $reply = $this->posting()->reply($this->userEntity($other), $thread['thread_id'], ['body' => 'why is this here']);

        $this->actingAs($mod);
        $resp = $this->post('/posts/' . $opId . '/delete', ['reason' => 'spam topic']);

        // No sole-participant guard for moderators: the whole topic goes, replies
        // and all — and the thread is no longer orphaned on the board.
        $this->assertRedirectContains($resp, '/c/general');
        self::assertSame(1, (int) $this->threads()->find($thread['thread_id'])['is_deleted']);
        self::assertSame(1, (int) $this->posts()->find($opId)['is_deleted']);
        self::assertSame(1, (int) $this->posts()->find($reply)['is_deleted']);
        self::assertSame(0, (int) $this->boards()->find((int) $board['id'])['thread_count']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'delete_thread' AND target_id = ?",
            [$thread['thread_id']],
        ));
        $this->assertDontSeeText($this->get('/c/general'), 'Spam topic');
    }

    // ---- #3 post actions preserve page + anchor on paginated threads ------

    public function test_post_action_redirects_keep_the_page_on_paginated_threads(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $author = $this->makeUser(['username' => 'longthread']);
        $viewer = $this->makeUser(['username' => 'reactor']);
        $thread = $this->makeThread($board, $author, 'Long topic', 'op body'); // post #1 (page 1)
        $opId = $this->opPostId($thread['thread_id']);

        // Ten replies + a 10/page preference for the viewer → the thread spans two pages.
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $lastReply = 0;
        for ($i = 0; $i < 10; $i++) {
            $lastReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'reply ' . $i]);
        }
        // $lastReply is the 11th post → page 2 at 10/page.

        $this->actingAs($viewer);
        $threadUrl = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        // Reacting to a page-2 post returns to page 2 with its anchor.
        $react = $this->post('/posts/' . $lastReply . '/react', ['emoji' => '👍']);
        $this->assertRedirect($react, $threadUrl . '?page=2#p' . $lastReply);

        // Reacting to the opening post stays on the canonical page-1 URL (no ?page).
        $reactOp = $this->post('/posts/' . $opId . '/react', ['emoji' => '👍']);
        $this->assertRedirect($reactOp, $threadUrl . '#p' . $opId);

        // A brand-new reply lands on the last page where the new post actually is.
        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', [
            'body' => 'newest post',
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $this->assertRedirectContains($reply, '?page=2#p');
    }

    // ---- #5 no-JS board composer is idempotent ----------------------------

    public function test_board_composer_renders_a_no_js_idempotency_key(): void
    {
        $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $this->actingAs($this->makeUser(['username' => 'composer']));

        $page = $this->get('/c/general');

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'name="idempotency_key"');
    }

    public function test_duplicate_new_topic_submit_creates_only_one_topic(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'general', 'name' => 'General']);
        $this->actingAs($this->makeUser(['username' => 'deduper']));

        $body = [
            'board_id' => (int) $board['id'],
            'title' => 'Only once',
            'body' => 'no dupes please',
            'idempotency_key' => bin2hex(random_bytes(16)),
        ];
        $this->assertRedirect($this->post('/threads', $body));
        $this->assertRedirect($this->post('/threads', $body));

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM threads WHERE board_id = ? AND title = ?',
            [(int) $board['id'], 'Only once'],
        ));
    }

    private function opPostId(int $threadId): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);
    }
}
