<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use Tests\Support\TestCase;

final class AppBoardIdentityDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'board_identity_admin']);
    }

    public function test_member_sees_board_identity_topics_heading_and_board_rows_around_one_composer(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_member']);
        $board = $this->makeBoard($this->makeCategory('Board identity'), [
            'slug' => 'board-identity',
            'name' => 'Board Identity',
            'description' => 'A board with a distinct purpose.',
        ]);
        $this->makeThread($board, $member, 'A populated topic');
        $this->actingAs($member);

        $response = $this->get('/c/board-identity');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString('aria-label="Breadcrumb"', $body);
        self::assertStringContainsString('href="/">Forum index</a>', $body);
        self::assertStringContainsString('data-board-identity', $body);
        // The eyebrow names the category the board files under, not the constant
        // word "Board".
        self::assertStringContainsString('<p class="eyebrow">Board identity</p>', $body);
        // The facts are a labelled register, not an interpunct-separated line:
        // each value names itself, so "24" is never read as the post count.
        self::assertStringContainsString('<dl class="board-identity-facts" aria-label="Board facts">', $body);
        self::assertStringContainsString('data-board-fact="topics"', $body);
        self::assertStringContainsString('data-board-fact="posts"', $body);
        self::assertStringContainsString('data-board-fact="visibility"', $body);
        self::assertStringContainsString('<dt>Access</dt>', $body);
        self::assertStringContainsString('<dd>Public</dd>', $body);
        self::assertStringContainsString('data-board-topics', $body);
        self::assertStringContainsString('>Latest activity<', $body);
        self::assertStringContainsString('>Topics<', $body);
        self::assertStringContainsString('Pinned first, then last post', $body);
        self::assertStringContainsString("Following affects your discovery feed; it does not change this board's order.", $body);
        self::assertStringContainsString('thread-row-board', $body);
        self::assertStringContainsString('class="thread-row-activity"', $body);
        self::assertSame(1, substr_count($body, '0 replies'));
        self::assertSame(1, substr_count($body, 'details class="composer-details"'));
        self::assertSame(1, substr_count($body, 'action="/threads"'));
        $this->assertOrder($body, ['Follow board', 'New topic']);
    }

    public function test_board_rows_keep_one_left_edge_and_lead_with_the_title(): void
    {
        $member = $this->makeUser(['username' => 'board_row_grid_member']);
        $board = $this->makeBoard($this->makeCategory('Row grid'), ['slug' => 'row-grid']);
        $this->makeThread($board, $member, 'A read topic');
        $this->actingAs($member);

        $body = $this->get('/c/row-grid')->body();

        // The unread gutter is emitted read or unread, so the monogram column
        // starts at the same x on every row.
        self::assertSame(1, substr_count($body, 'class="unread-slot"'));
        self::assertStringNotContainsString('<span class="unread-dot"', $body);
        // The title still leads its line.
        self::assertStringContainsString('<span class="thread-title-line">', $body);
        // Board rows carry no chip stack ABOVE the title — status sits after it.
        self::assertStringNotContainsString('thread-row-chips', $body);
        // Status and star each own a reserved cell, emitted whether or not this
        // topic fills them, so no row is a different width from its neighbours.
        self::assertSame(1, substr_count($body, '<span class="thread-row-status">'), 'status cell is reserved');
        self::assertSame(1, substr_count($body, '<span class="thread-row-star">'), 'star cell is reserved');
        // One reply count, visible and with its noun — not a glyph plus an
        // sr-only twin saying the same thing twice.
        self::assertStringContainsString('<span class="thread-row-replies">0 replies</span>', $body);
        self::assertStringNotContainsString('<span class="sr-only">0 replies</span>', $body);
        // Elapsed time is what a column is scanned for; the exact instant stays
        // on the element.
        self::assertMatchesRegularExpression('/<time datetime="[^"]+" title="[^"]+UTC">[^<]+<\/time>/', $body);
    }

    public function test_board_rows_state_status_once_in_its_own_column(): void
    {
        $member = $this->makeUser(['username' => 'board_flag_member']);
        $board = $this->makeBoard($this->makeCategory('Row flags'), ['slug' => 'row-flags']);
        $thread = $this->makeThread($board, $member, 'A solved and pinned topic');
        $threads = $this->threads();
        $threads->setStatus((int) $thread['thread_id'], 'solved', (int) $member['id']);
        $threads->setPinned((int) $thread['thread_id'], true);
        $this->actingAs($member);

        $body = $this->get('/c/row-flags')->body();

        // Status is a pill in the reserved column AFTER the title, so the title
        // is still the first thing read — the objection the old meta-line flag
        // answered — and it is stated exactly once.
        self::assertStringContainsString('<span class="thread-row-status">', $body);
        self::assertStringContainsString('chip chip-solved', $body);
        self::assertSame(1, substr_count($body, 'chip-solved'), 'status is stated once');
        self::assertStringNotContainsString('thread-flag', $body);
        // Pinned and Locked are not status: they qualify the title and ride its
        // line as bare marks, never as pills.
        self::assertStringContainsString('<span class="thread-mark is-pinned" title="Pinned">', $body);
        self::assertStringNotContainsString('chip chip-pinned', $body);
        // A chip stacked ABOVE the title stays forbidden on this presentation.
        self::assertStringNotContainsString('thread-row-chips', $body);
        // The row classes survive for the shared presentation's status rule.
        self::assertStringContainsString('thread-status-solved', $body);
        self::assertStringContainsString('thread-pinned', $body);
    }

    public function test_condensed_identity_duplicates_the_slab_without_a_second_keyboard_stop(): void
    {
        $member = $this->makeUser(['username' => 'board_condensed_member']);
        $board = $this->makeBoard($this->makeCategory('Condensed identity'), [
            'slug' => 'condensed-identity',
            'name' => 'Condensed Identity',
        ]);
        // Populated on purpose: the empty state carries a THIRD trigger of its
        // own, and the pair this test guards is the slab's and the bar's echo.
        $this->makeThread($board, $member, 'A topic so the board is not empty');
        $this->actingAs($member);

        $body = $this->get('/c/condensed-identity')->body();

        self::assertStringContainsString('<div class="board-identity-sticky" aria-hidden="true">', $body);
        self::assertStringContainsString('class="board-identity-condensed-facts"', $body);
        // Two composer triggers now; only the slab's is reachable by keyboard,
        // and only the slab's is server-rendered with aria-expanded.
        self::assertSame(2, substr_count($body, 'data-open-topic-composer'));
        self::assertSame(1, substr_count($body, 'tabindex="-1" hidden data-open-topic-composer'));
        self::assertSame(1, substr_count($body, 'data-open-topic-composer aria-controls="new-topic" aria-expanded="false"'));
    }

    public function test_following_a_board_asks_for_the_shared_on_state(): void
    {
        $member = $this->makeUser(['username' => 'board_follow_on_state']);
        $board = $this->makeBoard($this->makeCategory('Follow on state'), ['slug' => 'follow-on-state']);
        $this->actingAs($member);

        $before = $this->get('/c/follow-on-state')->body();
        self::assertStringContainsString('class="btn btn-secondary" type="submit" data-follow-board', $before);
        self::assertStringNotContainsString('btn-secondary btn-on', $before);

        $this->assertRedirect($this->post('/b/' . (int) $board['id'] . '/follow'));

        $after = $this->get('/c/follow-on-state')->body();
        self::assertStringContainsString('class="btn btn-secondary btn-on" type="submit" data-follow-board', $after);
    }

    public function test_guest_keeps_login_joinbar_without_follow_or_new_topic_controls(): void
    {
        $board = $this->makeBoard($this->makeCategory('Guest board'), ['slug' => 'guest-board']);

        $response = $this->get('/c/guest-board');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString('class="joinbar"', $body);
        self::assertStringContainsString('href="/login?next=/c/guest-board"', $body);
        self::assertStringNotContainsString('action="/b/' . (int) $board['id'] . '/follow"', $body);
        self::assertStringNotContainsString('data-open-topic-composer', $body);
        self::assertStringNotContainsString('details class="composer-details"', $body);
        self::assertStringNotContainsString('action="/threads"', $body);
    }

    public function test_reader_without_post_authority_has_no_new_topic_trigger_or_form(): void
    {
        $reader = $this->makeUser(['username' => 'board_identity_reader']);
        $this->makeBoard($this->makeCategory('Read only by role'), [
            'slug' => 'role-read-only',
            'post_min_role' => 'admin',
        ]);
        $this->actingAs($reader);

        $response = $this->get('/c/role-read-only');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringNotContainsString('data-open-topic-composer', $body);
        self::assertStringNotContainsString('details class="composer-details"', $body);
        self::assertStringNotContainsString('action="/threads"', $body);
    }

    public function test_archived_board_keeps_archive_wording_without_new_topic_controls(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_archive_reader']);
        $board = $this->makeBoard($this->makeCategory('Archive identity'), ['slug' => 'archive-identity']);
        $this->makeThread($board, $member, 'Archived but readable');
        $this->boards()->setArchived((int) $board['id'], true);
        $this->actingAs($member);

        $response = $this->get('/c/archive-identity');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString('This board is retired and read-only.', $body);
        self::assertStringContainsString('Archived', $body);
        self::assertStringNotContainsString('data-open-topic-composer', $body);
        self::assertStringNotContainsString('details class="composer-details"', $body);
        self::assertStringNotContainsString('action="/threads"', $body);
    }

    public function test_empty_writable_board_keeps_topic_section_and_empty_copy(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_empty_member']);
        $this->makeBoard($this->makeCategory('Empty identity'), ['slug' => 'empty-identity']);
        $this->actingAs($member);

        $response = $this->get('/c/empty-identity');

        $this->assertStatus(200, $response);
        self::assertStringContainsString('data-board-topics', $response->body());
        self::assertStringContainsString('No topics here yet.', $response->body());
    }

    public function test_board_rows_carry_the_viewers_own_state(): void
    {
        $member = $this->makeUser(['username' => 'board_viewer_state']);
        $mate = $this->makeUser(['username' => 'board_viewer_mate']);
        $board = $this->makeBoard($this->makeCategory('Viewer state'), ['slug' => 'viewer-state']);
        $starred = $this->makeThread($board, $member, 'A topic I starred');
        $assigned = $this->makeThread($board, $member, 'A topic assigned to someone');
        $this->actingAs($member);

        $threadUsers = new \App\Repository\ThreadUserRepository($this->db);
        $threadUsers->setStar((int) $member['id'], (int) $starred['thread_id'], true);
        $threadUsers->setSnooze((int) $member['id'], (int) $starred['thread_id'], '2030-01-02 03:04:05');
        $this->db->run(
            'INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [(int) $assigned['thread_id'], (int) $mate['id'], (int) $member['id']],
        );

        $body = $this->get('/c/viewer-state')->body();

        // A topic you starred must read as starred on its own board, not only in
        // your inbox. These three columns were rendered but never selected until
        // the board query learned to carry the viewer's state.
        self::assertStringContainsString('<span class="thread-star" title="Starred"', $body);
        self::assertStringContainsString('assigned to @board_viewer_mate', $body);
        self::assertStringContainsString('snoozed until Jan 2, 2030', $body);
    }

    public function test_a_guest_board_carries_no_viewer_state_joins(): void
    {
        $member = $this->makeUser(['username' => 'board_guest_state']);
        $board = $this->makeBoard($this->makeCategory('Guest state'), ['slug' => 'guest-state']);
        $thread = $this->makeThread($board, $member, 'A starred topic');
        (new \App\Repository\ThreadUserRepository($this->db))
            ->setStar((int) $member['id'], (int) $thread['thread_id'], true);

        $body = $this->get('/c/guest-state')->body();

        // The star belongs to the member who set it, never to whoever is looking.
        self::assertStringNotContainsString('thread-star', $body);
        self::assertStringNotContainsString('unread-toggle', $body);
        self::assertStringContainsString('<span class="thread-row-star">', $body);
    }

    public function test_empty_writable_board_invites_the_first_topic_and_drops_pagination(): void
    {
        $member = $this->makeUser(['username' => 'board_empty_cta']);
        $this->makeBoard($this->makeCategory('Empty invite'), [
            'slug' => 'empty-invite',
            'name' => 'Empty Invite',
        ]);
        $this->actingAs($member);

        $body = $this->get('/c/empty-invite')->body();

        self::assertStringContainsString('Be the first to open one in #Empty Invite.', $body);
        // The CTA carries the composer hook. Without it, app.js hides the
        // <summary> the moment a promoted trigger exists, and the anchor would
        // jump to a <details> that nothing can open — a dead button.
        self::assertStringContainsString(
            'class="btn btn-secondary board-empty-cta" href="#new-topic" data-open-topic-composer',
            $body,
        );
        // Nothing to page through, so the nav is absent rather than empty.
        self::assertStringNotContainsString('pagination-board', $body);
    }

    public function test_empty_read_only_board_states_the_absence_without_an_invitation(): void
    {
        $reader = $this->makeUser(['username' => 'board_empty_reader']);
        $this->makeBoard($this->makeCategory('Empty read only'), [
            'slug' => 'empty-read-only',
            'post_min_role' => 'admin',
        ]);
        $this->actingAs($reader);

        $body = $this->get('/c/empty-read-only')->body();

        self::assertStringContainsString('No topics here yet.', $body);
        self::assertStringNotContainsString('Be the first to open one', $body);
        self::assertStringNotContainsString('board-empty-cta', $body);
    }

    public function test_board_pagination_states_the_page_and_offers_two_moves(): void
    {
        $member = $this->makeUser(['username' => 'board_pagination_member']);
        $board = $this->makeBoard($this->makeCategory('Board pagination'), ['slug' => 'board-pagination']);
        for ($i = 0; $i < 21; $i++) {
            $this->makeThread($board, $member, 'Paged topic ' . $i);
        }
        $this->actingAs($member);

        $first = $this->get('/c/board-pagination')->body();
        $second = $this->get('/c/board-pagination', ['page' => 2])->body();

        self::assertStringContainsString('Showing 20 of 21 topics', $first);
        // Previous is unavailable on page 1 — and an unavailable move is not a
        // link at all, so it is neither focusable nor announced as one.
        self::assertStringContainsString('<span class="page is-disabled" aria-disabled="true">Previous</span>', $first);
        self::assertStringContainsString('page=2">Next</a>', $first);
        self::assertStringNotContainsString('>1</a>', $first, 'no numbered strip on the board');

        self::assertStringContainsString('Showing 1 of 21 topics', $second);
        self::assertStringContainsString('page=1">Previous</a>', $second);
        self::assertStringContainsString('<span class="page is-disabled" aria-disabled="true">Next</span>', $second);
    }

    public function test_the_shared_numbered_pagination_survives_off_the_board(): void
    {
        $member = $this->makeUser(['username' => 'board_shared_pagination']);
        $board = $this->makeBoard($this->makeCategory('Shared pagination'), ['slug' => 'shared-pagination']);
        $thread = $this->makeThread($board, $member, 'A topic with many replies');
        for ($i = 0; $i < 25; $i++) {
            $this->posting()->reply(
                $this->userEntity($member),
                (int) $thread['thread_id'],
                ['body' => 'Reply number ' . $i],
            );
        }
        $this->actingAs($member);

        $body = $this->get('/t/' . (int) $thread['thread_id'] . '-' . $thread['slug'])->body();

        // The board's two-move variant is opt-in; every other caller keeps the
        // numbered strip it had.
        self::assertStringContainsString('class="pagination"', $body);
        self::assertStringNotContainsString('pagination-board', $body);
    }

    public function test_follow_form_requires_community_and_expanded_feeds_together(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_flag_member']);
        $board = $this->makeBoard($this->makeCategory('Follow flags'), ['slug' => 'follow-flags']);
        $this->actingAs($member);
        $followAction = 'action="/b/' . (int) $board['id'] . '/follow"';

        $this->setFeatureFlags(['community' => false, 'expanded_feeds' => true]);
        $communityOff = $this->get('/c/follow-flags');
        $this->assertStatus(200, $communityOff);
        self::assertStringNotContainsString($followAction, $communityOff->body());

        $this->setFeatureFlags(['community' => true, 'expanded_feeds' => false]);
        $expandedFeedsOff = $this->get('/c/follow-flags');
        $this->assertStatus(200, $expandedFeedsOff);
        self::assertStringNotContainsString($followAction, $expandedFeedsOff->body());
    }

    public function test_following_state_is_exposed_by_the_real_post_button(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_follower']);
        $board = $this->makeBoard($this->makeCategory('Following identity'), ['slug' => 'following-identity']);
        $this->actingAs($member);
        $this->assertRedirect($this->post('/b/' . (int) $board['id'] . '/follow'));

        $response = $this->get('/c/following-identity');

        $this->assertStatus(200, $response);
        self::assertMatchesRegularExpression(
            '~<button\b(?=[^>]*aria-pressed="true")[^>]*>\s*Following\s*</button>~',
            $response->body(),
        );
    }

    public function test_tag_route_keeps_the_default_shared_thread_row_contract(): void
    {
        $member = $this->makeUser(['username' => 'board_identity_tag_member']);
        $board = $this->makeBoard($this->makeCategory('Shared rows'), ['slug' => 'shared-row-board']);
        $thread = $this->makeThread($board, $member, 'Shared row topic');
        $tags = new TagRepository($this->db);
        $tagId = $tags->create('shared-row', 'Shared row', null, (int) $member['id']);
        $tags->setForThread((int) $thread['thread_id'], [$tagId], (int) $member['id']);
        $this->actingAs($member);

        $response = $this->get('/tags/shared-row');
        $body = $response->body();

        $this->assertStatus(200, $response);
        self::assertStringContainsString('class="thread-row', $body);
        self::assertStringContainsString('<a class="thread-title"', $body);
        self::assertStringNotContainsString('thread-row-board', $body);
    }

    /** @param array<string,bool> $flags */
    private function setFeatureFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    /** @param list<string> $needles */
    private function assertOrder(string $body, array $needles): void
    {
        $previous = -1;
        foreach ($needles as $needle) {
            $position = strpos($body, $needle);
            self::assertNotFalse($position, "Missing expected text: $needle");
            self::assertGreaterThan($previous, $position, "$needle rendered out of order");
            $previous = $position;
        }
    }
}
