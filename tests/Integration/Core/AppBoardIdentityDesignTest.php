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
        self::assertStringContainsString('data-board-fact="topics"', $body);
        self::assertStringContainsString('data-board-fact="posts"', $body);
        self::assertStringContainsString('Public board', $body);
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
        // The star travels with the title instead of floating to the row's end.
        self::assertStringContainsString('<span class="thread-title-line">', $body);
        // Board rows carry no chip stack: status is a word on the meta line.
        self::assertStringNotContainsString('thread-row-chips', $body);
        // The glyph is decorative; the count keeps its noun for screen readers.
        self::assertStringContainsString('<span class="sr-only">0 replies</span>', $body);
    }

    public function test_board_rows_state_status_as_meta_flags_while_shared_rows_keep_chips(): void
    {
        $member = $this->makeUser(['username' => 'board_flag_member']);
        $board = $this->makeBoard($this->makeCategory('Row flags'), ['slug' => 'row-flags']);
        $thread = $this->makeThread($board, $member, 'A solved and pinned topic');
        $threads = $this->threads();
        $threads->setStatus((int) $thread['thread_id'], 'solved', (int) $member['id']);
        $threads->setPinned((int) $thread['thread_id'], true);
        $this->actingAs($member);

        $body = $this->get('/c/row-flags')->body();

        self::assertStringContainsString('<span class="thread-flag is-solved">Solved</span>', $body);
        self::assertStringContainsString('<span class="thread-flag is-pinned">Pinned</span>', $body);
        self::assertStringNotContainsString('chip chip-solved', $body);
        self::assertStringNotContainsString('chip chip-pinned', $body);
        // The 3px status rule still keys off the row class, not the flag.
        self::assertStringContainsString('thread-status-solved', $body);
        self::assertStringContainsString('thread-pinned', $body);
    }

    public function test_condensed_identity_duplicates_the_slab_without_a_second_keyboard_stop(): void
    {
        $member = $this->makeUser(['username' => 'board_condensed_member']);
        $this->makeBoard($this->makeCategory('Condensed identity'), [
            'slug' => 'condensed-identity',
            'name' => 'Condensed Identity',
        ]);
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
