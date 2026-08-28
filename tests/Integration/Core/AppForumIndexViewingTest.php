<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

final class AppForumIndexViewingTest extends TestCase
{
    private array $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'directory_admin']);
        $this->author = $this->makeUser(['username' => 'directory_author']);
    }

    public function test_query_axes_are_normalized_and_member_preferences_only_fill_omissions(): void
    {
        $category = $this->makeCategory('Council');
        $this->makeBoard($category, ['slug' => 'council-floor']);

        $guest = $this->get('/');
        $this->assertStatus(200, $guest);
        $this->assertDirectoryState($guest->body(), 'boards', 'category', 3);
        foreach (['category', 'active', 'newest', 'unanswered', 'top', 'solved'] as $sort) {
            self::assertStringContainsString('data-directory-sort-option="' . $sort . '"', $guest->body());
        }
        foreach ([0, 3, 5] as $peek) {
            self::assertStringContainsString('data-directory-peek-option="' . $peek . '"', $guest->body());
        }

        $explicit = $this->get('/', ['pane' => 'boards', 'sort' => 'top', 'peek' => '5']);
        $this->assertDirectoryState($explicit->body(), 'boards', 'top', 5);

        $invalid = $this->get('/', ['pane' => 'elsewhere', 'sort' => 'secret', 'peek' => '4']);
        $this->assertDirectoryState($invalid->body(), 'boards', 'category', 3);

        $reader = $this->makeUser(['username' => 'directory_reader']);
        (new UserPreferenceRepository($this->db))->merge((int) $reader['id'], [
            'directory_sort' => 'solved',
            'directory_peek' => 5,
        ]);
        $this->actingAs($reader);

        $remembered = $this->get('/');
        $this->assertDirectoryState($remembered->body(), 'boards', 'solved', 5);

        $sharedLink = $this->get('/', ['sort' => 'newest', 'peek' => '0']);
        $this->assertDirectoryState($sharedLink->body(), 'boards', 'newest', 0);

        $invalidSigned = $this->get('/', ['sort' => 'not-a-sort', 'peek' => '99']);
        $this->assertDirectoryState($invalidSigned->body(), 'boards', 'category', 3);
    }

    public function test_all_public_orders_rank_the_full_directory_and_category_order_stays_grouped(): void
    {
        $category = $this->makeCategory('Council');
        $categoryFirst = $this->makeBoard($category, ['slug' => 'category-first', 'name' => 'Category first']);
        $active = $this->makeBoard($category, ['slug' => 'active-first', 'name' => 'Active first']);
        $newest = $this->makeBoard($category, ['slug' => 'newest-first', 'name' => 'Newest first']);
        $unanswered = $this->makeBoard($category, ['slug' => 'unanswered-first', 'name' => 'Unanswered first']);
        $top = $this->makeBoard($category, ['slug' => 'top-first', 'name' => 'Top first']);
        $solved = $this->makeBoard($category, ['slug' => 'solved-first', 'name' => 'Solved first']);

        $this->topic($categoryFirst, 'Category topic', '2026-01-01 00:00:00', '2026-01-02 00:00:00');
        $this->topic($active, 'Fresh activity', '2026-01-03 00:00:00', '2026-08-27 18:00:00');
        $this->topic($newest, 'Newest opening', '2026-08-27 17:00:00', '2026-08-27 17:00:00');
        $this->topic($unanswered, 'Question one', '2026-02-01 00:00:00', '2026-02-01 00:00:00', 'needs_answer', 0);
        $this->topic($unanswered, 'Question two', '2026-02-02 00:00:00', '2026-02-02 00:00:00', 'open', 0);
        $topTopic = $this->topic($top, 'Most commended', '2026-03-01 00:00:00', '2026-03-02 00:00:00');
        $this->commend($topTopic['op_id'], 4);
        $this->topic($solved, 'Settled one', '2026-04-01 00:00:00', '2026-04-02 00:00:00', 'solved', 2, '2026-08-25 00:00:00');
        $this->topic($solved, 'Settled two', '2026-04-03 00:00:00', '2026-04-04 00:00:00', 'decision_made', 3, '2026-08-26 00:00:00');

        $expectations = [
            'category' => 'category-first',
            'active' => 'active-first',
            'newest' => 'newest-first',
            'unanswered' => 'unanswered-first',
            'top' => 'top-first',
            'solved' => 'solved-first',
        ];
        foreach ($expectations as $sort => $firstSlug) {
            $response = $this->get('/', ['sort' => $sort, 'peek' => '3']);
            $this->assertStatus(200, $response);
            self::assertSame($firstSlug, $this->firstDirectoryBoard($response->body()), $sort);
        }

        $categoryBody = $this->get('/', ['sort' => 'category'])->body();
        self::assertStringContainsString('data-directory-category="Council"', $categoryBody);
        self::assertStringNotContainsString('data-directory-ranked', $categoryBody);

        $rankedBody = $this->get('/', ['sort' => 'active'])->body();
        self::assertStringContainsString('data-directory-ranked', $rankedBody);
        self::assertStringNotContainsString('data-directory-category="Council"', $rankedBody);
        self::assertStringContainsString('the same order every member sees', $rankedBody);
    }

    public function test_peek_sizes_are_bounded_and_topic_filters_match_the_selected_order(): void
    {
        $category = $this->makeCategory('Archive');
        $board = $this->makeBoard($category, ['slug' => 'many-topics']);
        for ($i = 1; $i <= 6; $i++) {
            $this->topic(
                $board,
                'Topic ' . $i,
                sprintf('2026-08-%02d 00:00:00', $i),
                sprintf('2026-08-%02d 01:00:00', $i),
                $i === 1 ? 'needs_answer' : ($i === 2 ? 'solved' : 'open'),
                $i === 1 ? 0 : 1,
                $i === 2 ? '2026-08-20 00:00:00' : null,
            );
        }

        self::assertSame(0, substr_count($this->get('/', ['peek' => '0'])->body(), 'data-directory-topic='));
        self::assertSame(3, substr_count($this->get('/', ['peek' => '3'])->body(), 'data-directory-topic='));
        self::assertSame(5, substr_count($this->get('/', ['peek' => '5'])->body(), 'data-directory-topic='));

        $unanswered = $this->get('/', ['sort' => 'unanswered', 'peek' => '5'])->body();
        self::assertStringContainsString('Topic 1', $unanswered);
        self::assertStringNotContainsString('data-directory-topic="Topic 2"', $unanswered);

        $solved = $this->get('/', ['sort' => 'solved', 'peek' => '5'])->body();
        self::assertStringContainsString('data-directory-topic="Topic 2"', $solved);
        self::assertStringNotContainsString('data-directory-topic="Topic 1"', $solved);
    }

    public function test_directory_visibility_uses_the_board_policy_and_only_peeks_live_topics(): void
    {
        $category = $this->makeCategory('Visibility');
        $public = $this->makeBoard($category, ['slug' => 'visible-public']);
        $hidden = $this->makeBoard($category, ['slug' => 'direct-only', 'visibility' => 'hidden']);
        $private = $this->makeBoard($category, ['slug' => 'members-only', 'visibility' => 'private']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $this->author['id'], null);
        $this->topic($public, 'Visible topic', '2026-08-01 00:00:00', '2026-08-01 00:00:00');
        $pending = $this->topic($public, 'Pending secret', '2026-08-02 00:00:00', '2026-08-02 00:00:00');
        $deleted = $this->topic($public, 'Deleted secret', '2026-08-03 00:00:00', '2026-08-03 00:00:00');
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pending['thread_id']]);
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        $this->topic($hidden, 'Hidden topic', '2026-08-04 00:00:00', '2026-08-04 00:00:00');
        $this->topic($private, 'Private topic', '2026-08-05 00:00:00', '2026-08-05 00:00:00');

        $guest = $this->get('/', ['peek' => '5'])->body();
        self::assertStringContainsString('visible-public', $guest);
        self::assertStringContainsString('Visible topic', $guest);
        self::assertStringNotContainsString('direct-only', $guest);
        self::assertStringNotContainsString('members-only', $guest);
        self::assertStringNotContainsString('Pending secret', $guest);
        self::assertStringNotContainsString('Deleted secret', $guest);

        $member = $this->makeUser(['username' => 'private_member']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $member['id'], null);
        $this->actingAs($member);
        $memberBody = $this->get('/', ['peek' => '5'])->body();
        self::assertStringContainsString('members-only', $memberBody);
        self::assertStringContainsString('Private topic', $memberBody);
        self::assertStringNotContainsString('direct-only', $memberBody);
        self::assertStringNotContainsString('Hidden topic', $memberBody);
    }

    public function test_tags_notices_and_connections_are_real_feature_gated_panes(): void
    {
        $category = $this->makeCategory('Panes');
        $board = $this->makeBoard($category, ['slug' => 'pane-board']);
        $thread = $this->makeThread($board, $this->author, 'Tagged topic');
        $tagId = (new TagRepository($this->db))->create('evidence', 'Evidence', 'Traceable work.', (int) $this->author['id']);
        $this->db->run(
            'INSERT INTO thread_tags (thread_id, tag_id, added_by, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$thread['thread_id'], $tagId, (int) $this->author['id']],
        );

        $tags = $this->get('/', ['pane' => 'tags'])->body();
        $this->assertDirectoryPane($tags, 'tags');
        self::assertStringContainsString('data-directory-tag="evidence"', $tags);
        self::assertStringContainsString('1 topic', $tags);

        $guestNotices = $this->get('/', ['pane' => 'notices'])->body();
        $this->assertDirectoryPane($guestNotices, 'notices');
        self::assertStringContainsString('href="/login?next=%2F%3Fpane%3Dnotices">Log in</a>', $guestNotices);
        self::assertStringContainsString('to see notices about your account.', $guestNotices);

        $guestConnections = $this->get('/', ['pane' => 'connections'])->body();
        $this->assertDirectoryPane($guestConnections, 'connections');
        self::assertStringContainsString('href="/login?next=%2F%3Fpane%3Dconnections">Log in</a>', $guestConnections);
        self::assertStringContainsString('to see your followers and the people you follow.', $guestConnections);

        $reader = $this->makeUser(['username' => 'pane_reader']);
        $following = $this->makeUser(['username' => 'pane_following', 'display_name' => 'Following Person']);
        (new FollowRepository($this->db))->follow((int) $this->author['id'], (int) $reader['id']);
        (new FollowRepository($this->db))->follow((int) $reader['id'], (int) $following['id']);
        $noticeId = (new NotificationRepository($this->db))->createFollowOnce((int) $reader['id'], (int) $this->author['id']);
        $this->actingAs($reader);

        $notices = $this->get('/', ['pane' => 'notices'])->body();
        self::assertStringContainsString('directory_author followed you', $notices);
        self::assertStringContainsString('action="/notifications/' . $noticeId . '/read"', $notices);
        self::assertStringContainsString('action="/notifications/read-all"', $notices);
        self::assertStringContainsString('action="/notifications/clear"', $notices);

        $followers = $this->get('/', ['pane' => 'connections'])->body();
        self::assertStringContainsString('data-connection-mode="followers"', $followers);
        self::assertStringContainsString('@directory_author', $followers);
        self::assertStringNotContainsString('@pane_following', $followers);

        $followingBody = $this->get('/', ['pane' => 'connections', 'connection' => 'following'])->body();
        self::assertStringContainsString('data-connection-mode="following"', $followingBody);
        self::assertStringContainsString('@pane_following', $followingBody);

        (new BlockRepository($this->db))->block((int) $reader['id'], (int) $following['id']);
        $blocked = $this->get('/', ['pane' => 'connections', 'connection' => 'following'])->body();
        self::assertStringNotContainsString('@pane_following', $blocked);

        (new SettingRepository($this->db))->set('features', [
            'tags' => false,
            'notifications' => false,
            'community' => false,
        ]);
        foreach (['tags', 'notices', 'connections'] as $pane) {
            $dark = $this->get('/', ['pane' => $pane])->body();
            $this->assertDirectoryPane($dark, 'boards');
            self::assertStringNotContainsString('href="/?pane=' . $pane . '"', $dark);
        }
    }

    /** @return array{thread_id:int,op_id:int} */
    private function topic(
        array $board,
        string $title,
        string $createdAt,
        string $lastPostAt,
        string $status = 'open',
        int $replyCount = 1,
        ?string $statusChangedAt = null,
    ): array {
        $thread = $this->makeThread($board, $this->author, $title);
        $this->db->run(
            'UPDATE threads SET created_at = ?, last_post_at = ?, status = ?, reply_count = ?, status_changed_at = ? WHERE id = ?',
            [$createdAt, $lastPostAt, $status, $replyCount, $statusChangedAt, $thread['thread_id']],
        );
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$thread['thread_id']],
        );
        return ['thread_id' => $thread['thread_id'], 'op_id' => $opId];
    }

    private function commend(int $postId, int $count): void
    {
        $reactor = $this->makeUser(['username' => 'directory_reactor_' . $postId]);
        for ($i = 1; $i <= $count; $i++) {
            $this->db->run(
                'INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [$postId, (int) $reactor['id'], 'commend-' . $i],
            );
        }
    }

    private function assertDirectoryState(string $body, string $pane, string $sort, int $peek): void
    {
        $this->assertDirectoryPane($body, $pane);
        self::assertStringContainsString('data-directory-sort="' . $sort . '"', $body);
        self::assertStringContainsString('data-directory-peek="' . $peek . '"', $body);
        // A member's controls are <button aria-pressed>; a guest's are <a>, which
        // may not carry aria-pressed at all (ADR 0028), so the selected link
        // states itself with aria-current instead. Either is the "on" state.
        self::assertMatchesRegularExpression(
            '~data-directory-sort-option="' . preg_quote($sort, '~') . '"[^>]*aria-(?:pressed|current)="true"~',
            $body,
        );
        self::assertMatchesRegularExpression(
            '~data-directory-peek-option="' . $peek . '"[^>]*aria-(?:pressed|current)="true"~',
            $body,
        );
    }

    private function assertDirectoryPane(string $body, string $pane): void
    {
        self::assertStringContainsString('data-directory-pane="' . $pane . '"', $body);
        self::assertMatchesRegularExpression(
            '~href="/\?pane=' . preg_quote($pane, '~') . '"[^>]*aria-current="page"~',
            $body,
        );
    }

    private function firstDirectoryBoard(string $body): string
    {
        self::assertMatchesRegularExpression('~data-directory-board="([^"]+)"~', $body);
        preg_match('~data-directory-board="([^"]+)"~', $body, $match);
        return $match[1];
    }
}
