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
