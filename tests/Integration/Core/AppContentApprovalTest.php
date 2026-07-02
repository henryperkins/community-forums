<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * P3-05: board approval holds + the moderation approval queue, plus block-mode
 * enforcement. Held content is not visible and does not inflate counters until a
 * moderator releases it; a released item then behaves like a normal post.
 */
final class AppContentApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    public function test_board_requiring_approval_holds_a_new_thread(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'modqueue', 'name' => 'ModQueue']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);

        $author = $this->makeUser(['username' => 'holder']);
        $this->actingAs($author);

        $res = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Held topic', 'body' => 'Please review me.']);
        $this->assertRedirectContains($res, '/c/modqueue');

        // Held: pending flag set, hidden from the board list, board counters untouched.
        $thread = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [(int) $board['id']]);
        self::assertSame(1, (int) $thread['is_pending']);
        $this->assertDontSeeText($this->get('/c/modqueue'), 'Held topic');
        self::assertSame(0, (int) $this->boards()->find((int) $board['id'])['thread_count']);
        self::assertSame(0, (int) $this->users()->find((int) $author['id'])['post_count']);

        // Moderator releases it → visible, counters applied.
        $admin = $this->makeAdmin(['username' => 'approver']);
        $this->actingAs($admin);
        $this->assertSeeText($this->get('/mod/approvals'), 'Held topic');
        $approve = $this->post('/mod/approvals/thread/' . (int) $thread['id'] . '/approve');
        $this->assertRedirect($approve, '/mod/approvals');

        $thread = $this->threads()->find((int) $thread['id']);
        self::assertSame(0, (int) $thread['is_pending']);
        $this->assertSeeText($this->get('/c/modqueue'), 'Held topic');
        self::assertSame(1, (int) $this->boards()->find((int) $board['id'])['thread_count']);
        self::assertSame(1, (int) $this->users()->find((int) $author['id'])['post_count']);

        // The release is audited with the acting moderator.
        self::assertNotFalse($this->db->fetchValue(
            "SELECT 1 FROM moderation_log WHERE action = 'approve_pending' AND target_type = 'thread' AND target_id = ? AND actor_id = ?",
            [(int) $thread['id'], (int) $admin['id']],
        ));
    }

    public function test_held_reply_is_hidden_then_released(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'rq', 'name' => 'RQ']);
        $author = $this->makeUser(['username' => 'op2']);
        $thread = $this->makeThread($board, $author, 'Live topic', 'Opening.');

        // Turn on approval AFTER the live thread exists, so only the reply is held.
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $replier = $this->makeUser(['username' => 'replier2']);
        $this->actingAs($replier);

        $res = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'My held reply text.']);
        $this->assertRedirectContains($res, '/t/' . $thread['thread_id']);

        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
        $this->assertDontSeeText($this->get($url), 'My held reply text.');
        self::assertSame(0, (int) $this->threads()->find($thread['thread_id'])['reply_count']);

        $reply = $this->db->fetch('SELECT * FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$thread['thread_id']]);
        self::assertSame(1, (int) $reply['is_pending']);

        $admin = $this->makeAdmin(['username' => 'approver2']);
        $this->actingAs($admin);
        $this->post('/mod/approvals/post/' . (int) $reply['id'] . '/approve');

        $this->assertSeeText($this->get($url), 'My held reply text.');
        self::assertSame(1, (int) $this->threads()->find($thread['thread_id'])['reply_count']);
    }

    public function test_block_mode_rejects_content_and_creates_nothing(): void
    {
        (new SettingRepository($this->db))->set('antiabuse_mode', 'block');
        (new SettingRepository($this->db))->set('antiabuse_blocked_words', ['spamword']);

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'clean']);
        $author = $this->makeUser(['username' => 'blocked']);
        $this->actingAs($author);

        $res = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Hi', 'body' => 'buy spamword now']);
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE user_id = ?', [(int) $author['id']]));

        // The block is audited with a system actor (no content id).
        self::assertNotFalse($this->db->fetchValue(
            "SELECT 1 FROM moderation_log WHERE action = 'auto_block' AND actor_id IS NULL",
        ));
    }

    public function test_scoped_moderator_cannot_release_other_boards_content(): void
    {
        $cat = $this->makeCategory();
        $boardA = $this->makeBoard($cat, ['slug' => 'board-a']);
        $boardB = $this->makeBoard($cat, ['slug' => 'board-b']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $boardB['id']]);

        // A non-admin moderator scoped to board A only.
        $mod = $this->makeUser(['username' => 'scopedmod', 'role' => 'moderator']);
        (new \App\Repository\BoardModeratorRepository($this->db))->assign((int) $boardA['id'], (int) $mod['id']);

        // Held thread in board B (scoped mod posts elsewhere; use a plain author).
        $author = $this->makeUser(['username' => 'bauthor']);
        $this->actingAs($author);
        $this->post('/threads', ['board_id' => (int) $boardB['id'], 'title' => 'B held', 'body' => 'review me']);
        $held = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [(int) $boardB['id']]);

        // The board-A moderator may not approve board-B content.
        $this->actingAs($mod);
        $res = $this->post('/mod/approvals/thread/' . (int) $held['id'] . '/approve');
        $this->assertStatus(403, $res);
        self::assertSame(1, (int) $this->threads()->find((int) $held['id'])['is_pending']); // still held
    }

    public function test_stale_thread_approval_does_not_flash_success(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'stale-approval']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);

        $author = $this->makeUser(['username' => 'staleauthor']);
        $this->actingAs($author);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Already handled', 'body' => 'review me']);
        $held = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [(int) $board['id']]);
        $this->db->run('UPDATE threads SET is_pending = 0 WHERE id = ?', [(int) $held['id']]);

        $this->actingAs($this->makeAdmin(['username' => 'staleapprover']));
        $this->assertRedirect($this->post('/mod/approvals/thread/' . (int) $held['id'] . '/approve'), '/mod/approvals');
        $body = $this->get('/mod/approvals')->body();
        self::assertStringContainsString('Topic was already handled.', $body);
        self::assertStringNotContainsString('Topic approved and published.', $body);
    }

    public function test_held_thread_page_is_hidden_from_others(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'held-page']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $author = $this->makeUser(['username' => 'heldauthor']);
        $this->actingAs($author);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Secret held', 'body' => 'hush']);
        $held = $this->db->fetch('SELECT * FROM threads WHERE board_id = ?', [(int) $board['id']]);
        $url = '/t/' . (int) $held['id'] . '-' . $held['slug'];

        // Author + a moderator can load it; a stranger and a guest cannot.
        $this->assertStatus(200, $this->get($url)); // author
        $this->logoutClient();
        $this->assertStatus(404, $this->get($url)); // guest
        $stranger = $this->makeUser(['username' => 'stranger']);
        $this->actingAs($stranger);
        $this->assertStatus(404, $this->get($url));
    }

    public function test_held_content_is_excluded_from_search(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'search-hold']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $author = $this->makeUser(['username' => 'searchhold']);
        $this->actingAs($author);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Zorblax marketing spam', 'body' => 'buy zorblax']);

        // A guest searching the public board must not see the held title/body.
        $this->logoutClient();
        $res = $this->get('/search', ['q' => 'Zorblax']);
        $this->assertDontSeeText($res, 'Zorblax marketing spam');
    }

    public function test_held_thread_is_excluded_from_the_inbox(): void
    {
        // Engagement cutover in the past so the "unread" filter/badge is active.
        (new SettingRepository($this->db))->set('engagement_cutover_at', gmdate('Y-m-d H:i:s', time() - 3600));

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'inbox-hold', 'name' => 'InboxHold']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);

        $author = $this->makeUser(['username' => 'inboxauthor']);
        $this->actingAs($author);
        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Held inbox topic', 'body' => 'review me']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_pending FROM threads WHERE board_id = ?', [(int) $board['id']]));

        // A different user must not see the held thread title in ANY inbox filter,
        // and the unread badge must not count it (the Inbox was the one read
        // surface the held-content hardening missed).
        $viewer = $this->makeUser(['username' => 'inboxviewer']);
        $this->actingAs($viewer);
        foreach (['newest', 'active', 'unanswered', 'unread'] as $filter) {
            $this->assertDontSeeText($this->get('/inbox', ['filter' => $filter]), 'Held inbox topic');
        }
        $cutover = gmdate('Y-m-d H:i:s', time() - 3600);
        self::assertSame(0, (new ThreadUserRepository($this->db))->unreadCount((int) $viewer['id'], false, $cutover));
    }

    public function test_repair_excludes_held_content_from_counters(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'repair-hold']);
        $author = $this->makeUser(['username' => 'repairauthor']);
        $thread = $this->makeThread($board, $author, 'Live for repair', 'Opening.');

        // Hold replies, then add one held reply.
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $replier = $this->makeUser(['username' => 'repairreplier']);
        $this->actingAs($replier);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'held reply for repair']);
        $reply = $this->db->fetch('SELECT * FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$thread['thread_id']]);
        self::assertSame(1, (int) $reply['is_pending']);

        // Scramble the denormalised counters, then reconcile from authoritative rows.
        $this->db->run('UPDATE threads SET reply_count = 99, last_post_id = ? WHERE id = ?', [(int) $reply['id'], $thread['thread_id']]);
        (new RepairService($this->db))->repairAll();

        // The held reply is neither counted nor chosen as last activity, and its
        // author gains no counted post — repair must match the runtime, which
        // defers all of this until approval.
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$thread['thread_id']]));
        self::assertSame($opId, (int) $this->db->fetchValue('SELECT last_post_id FROM threads WHERE id = ?', [$thread['thread_id']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT post_count FROM users WHERE id = ?', [(int) $replier['id']]));
    }

    public function test_moderator_is_exempt_from_holds(): void
    {
        (new SettingRepository($this->db))->set('antiabuse_mode', 'hold');
        (new SettingRepository($this->db))->set('antiabuse_blocked_words', ['spamword']);
        // blocked word would normally block; staff are trusted and skip the filter.
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'staff']);
        $mod = $this->makeUser(['username' => 'modposter', 'role' => 'moderator']);
        $this->actingAs($mod);

        $res = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Staff', 'body' => 'contains spamword but staff']);
        $this->assertRedirectContains($res, '/t/');
        $thread = $this->db->fetch('SELECT * FROM threads WHERE user_id = ?', [(int) $mod['id']]);
        self::assertSame(0, (int) $thread['is_pending']);
    }
}
