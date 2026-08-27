<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

/**
 * Manual read state on the board page: the row's gutter marker and the topics
 * header's "Mark all read".
 *
 * Both are ordinary CSRF-protected form POSTs — the board list must work with
 * no JavaScript, so the marker is a submit button in a per-row form and not a
 * fetch() hook.
 */
final class AppBoardReadStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Without an admin the setup gate answers every route with /setup.
        $this->makeAdmin(['username' => 'read_state_admin']);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>} member, board, thread */
    private function seedBoardWithTopic(string $slug, string $username): array
    {
        $member = $this->makeUser(['username' => $username]);
        $board = $this->makeBoard($this->makeCategory('Read state ' . $slug), ['slug' => $slug]);
        $thread = $this->makeThread($board, $member, 'A topic to mark');
        return [$member, $board, $thread];
    }

    private function threadUsers(): ThreadUserRepository
    {
        return new ThreadUserRepository($this->db);
    }

    public function test_marking_a_topic_unread_actually_lowers_the_read_watermark(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-lower', 'read_state_lower');
        $threadId = (int) $thread['thread_id'];
        $postId = (int) $thread['post_id'];
        $this->actingAs($member);

        // Start from read, the state opening the topic leaves behind.
        $this->threadUsers()->markRead($member['id'], $threadId, $postId);
        self::assertFalse(
            $this->threadUsers()->unreadFlags($member['id'], [$threadId], ThreadUserRepository::NO_CUTOVER)[$threadId],
            'precondition: the topic starts read',
        );

        $response = $this->post('/t/' . $threadId . '/read', [
            'state' => 'unread',
            'return' => '/c/read-lower',
        ]);

        $this->assertRedirect($response, '/c/read-lower');
        // The regression this endpoint exists to avoid: markRead() is monotonic
        // (GREATEST), so reusing it in reverse would silently no-op and the row
        // would come back read.
        self::assertTrue(
            $this->threadUsers()->unreadFlags($member['id'], [$threadId], ThreadUserRepository::NO_CUTOVER)[$threadId],
            'the topic is unread again',
        );
        self::assertStringContainsString('<span class="unread-dot"', $this->get('/c/read-lower')->body());
    }

    public function test_marking_a_topic_read_clears_the_dot_and_the_board_count(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-raise', 'read_state_raise');
        $threadId = (int) $thread['thread_id'];
        $this->actingAs($member);
        $this->threadUsers()->markUnread($member['id'], $threadId);

        $before = $this->get('/c/read-raise')->body();
        self::assertStringContainsString('1 unread', $before);
        self::assertStringContainsString('data-mark-board-read', $before);

        $this->post('/t/' . $threadId . '/read', ['state' => 'read', 'return' => '/c/read-raise']);

        $after = $this->get('/c/read-raise')->body();
        self::assertStringNotContainsString('<span class="unread-dot"', $after);
        self::assertStringNotContainsString('1 unread', $after);
        // The count and the button appear and vanish together — an empty count
        // beside a live button would offer a no-op.
        self::assertStringNotContainsString('data-mark-board-read', $after);
        self::assertStringContainsString('<span class="unread-ring"', $after);
    }

    public function test_mark_all_read_clears_this_board_only(): void
    {
        $member = $this->makeUser(['username' => 'read_state_bulk']);
        $categoryId = $this->makeCategory('Bulk read');
        $here = $this->makeBoard($categoryId, ['slug' => 'bulk-here']);
        $elsewhere = $this->makeBoard($categoryId, ['slug' => 'bulk-elsewhere']);
        $mine = [
            (int) $this->makeThread($here, $member, 'First here')['thread_id'],
            (int) $this->makeThread($here, $member, 'Second here')['thread_id'],
        ];
        $other = (int) $this->makeThread($elsewhere, $member, 'Over there')['thread_id'];
        $this->actingAs($member);
        foreach ([...$mine, $other] as $id) {
            $this->threadUsers()->markUnread($member['id'], $id);
        }

        $response = $this->post('/c/bulk-here/read', ['return' => '/c/bulk-here']);

        $this->assertRedirect($response, '/c/bulk-here');
        $flags = $this->threadUsers()->unreadFlags(
            $member['id'],
            [...$mine, $other],
            ThreadUserRepository::NO_CUTOVER,
        );
        self::assertFalse($flags[$mine[0]]);
        self::assertFalse($flags[$mine[1]]);
        self::assertTrue($flags[$other], 'a neighbouring board keeps its unread state');
    }

    public function test_mark_all_read_is_idempotent(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('bulk-twice', 'read_state_twice');
        $this->actingAs($member);
        $this->threadUsers()->markUnread($member['id'], (int) $thread['thread_id']);

        $this->post('/c/bulk-twice/read');
        $second = $this->post('/c/bulk-twice/read');

        $this->assertRedirect($second);
        self::assertFalse(
            $this->threadUsers()->unreadFlags(
                $member['id'],
                [(int) $thread['thread_id']],
                ThreadUserRepository::NO_CUTOVER,
            )[(int) $thread['thread_id']],
        );
    }

    public function test_both_writes_require_a_csrf_token(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-csrf', 'read_state_csrf');
        $this->actingAs($member);

        $row = $this->post('/t/' . (int) $thread['thread_id'] . '/read', ['state' => 'unread'], false);
        $bulk = $this->post('/c/read-csrf/read', [], false);

        self::assertNotSame(303, $row->status(), 'the row marker is not writable without a token');
        self::assertNotSame(303, $bulk->status(), 'mark all read is not writable without a token');
    }

    public function test_a_guest_is_sent_to_log_in_rather_than_writing(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-guest', 'read_state_guest');
        // A guest's CSRF secret is minted by their first GET; posting cold would
        // be refused by the token gate before the auth gate is ever consulted.
        $this->get('/c/read-guest');

        $response = $this->post('/t/' . (int) $thread['thread_id'] . '/read', ['state' => 'unread']);

        $this->assertRedirectContains($response, '/login');
    }

    public function test_the_board_read_gate_hides_both_routes_on_an_unreadable_board(): void
    {
        $owner = $this->makeUser(['username' => 'read_state_owner']);
        $outsider = $this->makeUser(['username' => 'read_state_outsider']);
        // Seeded public so the owner can open the topic, then closed — the gate
        // under test is the reader's, not the author's.
        $board = $this->makeBoard($this->makeCategory('Read gate'), ['slug' => 'read-gated']);
        $thread = $this->makeThread($board, $owner, 'Private counsel');
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [(int) $board['id']]);
        $this->actingAs($outsider);

        $row = $this->post('/t/' . (int) $thread['thread_id'] . '/read', ['state' => 'unread']);
        $bulk = $this->post('/c/read-gated/read');

        // 404 rather than 403 — a private board never confirms it exists.
        $this->assertStatus(404, $row);
        $this->assertStatus(404, $bulk);
    }

    public function test_rolling_engagement_back_darks_the_routes_and_the_markers(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-dark', 'read_state_dark');
        (new SettingRepository($this->db))->set('features', ['engagement' => false]);
        $this->actingAs($member);

        $row = $this->post('/t/' . (int) $thread['thread_id'] . '/read', ['state' => 'unread']);
        $bulk = $this->post('/c/read-dark/read');
        $body = $this->get('/c/read-dark')->body();

        $this->assertStatus(404, $row);
        $this->assertStatus(404, $bulk);
        self::assertStringNotContainsString('unread-toggle', $body);
        self::assertStringNotContainsString('data-mark-board-read', $body);
        // The gutter is still reserved, so the rows keep one left edge.
        self::assertStringContainsString('class="unread-slot"', $body);
    }

    public function test_rolling_engagement_back_also_takes_retained_stars_off_the_rows(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-dark-star', 'read_state_dark_star');
        $this->threadUsers()->setStar((int) $member['id'], (int) $thread['thread_id'], true);
        $this->actingAs($member);

        $live = $this->get('/c/read-dark-star')->body();
        self::assertStringContainsString('aria-label="Starred"', $live, 'precondition: the star renders');

        (new SettingRepository($this->db))->set('features', ['engagement' => false]);
        $rolledBack = $this->get('/c/read-dark-star')->body();

        // A rollback suppresses retained state from every presentation, not
        // merely its controls. A star with no route to clear it is worse than
        // no star: POST /t/{id}/star is 404 while the flag is down.
        self::assertStringNotContainsString('aria-label="Starred"', $rolledBack);
        $this->assertStatus(404, $this->post('/t/' . (int) $thread['thread_id'] . '/star'));
        // The cell itself stays, so the rows keep one right edge.
        self::assertStringContainsString('<span class="thread-row-star">', $rolledBack);
    }

    public function test_the_gutter_marker_posts_back_to_the_page_the_reader_is_on(): void
    {
        $member = $this->makeUser(['username' => 'read_state_return']);
        $board = $this->makeBoard($this->makeCategory('Return page'), ['slug' => 'read-return']);
        for ($i = 0; $i < 21; $i++) {
            $this->makeThread($board, $member, 'Topic number ' . $i);
        }
        $this->actingAs($member);

        $body = $this->get('/c/read-return', ['page' => 2])->body();

        self::assertStringContainsString('name="return" value="/c/read-return?page=2"', $body);
    }

    public function test_a_suspended_member_may_still_mark_their_own_reading(): void
    {
        [$member, $board, $thread] = $this->seedBoardWithTopic('read-suspended', 'read_state_suspended');
        $this->actingAs($member);
        $this->users()->setStatus((int) $member['id'], 'suspended', gmdate('Y-m-d H:i:s', time() + 86400));

        $response = $this->post('/t/' . (int) $thread['thread_id'] . '/read', [
            'state' => 'unread',
            'return' => '/c/read-suspended',
        ]);

        // Suspension means "read but not write". Opening a topic already moves
        // this same watermark, so refusing the manual marker while the automatic
        // one still fires would contradict itself.
        $this->assertRedirect($response, '/c/read-suspended');
        self::assertTrue(
            $this->threadUsers()->unreadFlags(
                (int) $member['id'],
                [(int) $thread['thread_id']],
                ThreadUserRepository::NO_CUTOVER,
            )[(int) $thread['thread_id']],
        );
    }
}
