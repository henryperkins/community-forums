<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\FollowRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Profile activity surfaces aligned to the Imladris user-profile reference:
 * five progressive-enhancement tabs, read-gated activity, bounded paging,
 * searchable connections, and scope-aware moderator context.
 */
final class AppProfileActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'siteadmin']);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} board, author */
    private function seedAuthor(string $username = 'galadriel'): array
    {
        $author = $this->makeUser(['username' => $username, 'display_name' => 'Galadriel']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'counsel', 'name' => 'Counsel']);

        return [$board, $author];
    }

    private function assertTextOrder(string $body, string $first, string $second): void
    {
        $firstAt = strpos($body, $first);
        $secondAt = strpos($body, $second);
        self::assertNotFalse($firstAt, 'First marker was not rendered.');
        self::assertNotFalse($secondAt, 'Second marker was not rendered.');
        self::assertLessThan($secondAt, $firstAt, sprintf('Expected "%s" before "%s".', $first, $second));
    }

    public function test_moderator_context_matches_actual_member_record_scope(): void
    {
        [$board, $author] = $this->seedAuthor();
        $otherBoard = $this->makeBoard($this->makeCategory('Elsewhere'), ['slug' => 'elsewhere']);
        $plain = $this->makeUser(['username' => 'plain-viewer']);
        $globalModerator = $this->makeUser(['username' => 'global-moderator', 'role' => 'moderator']);
        $inScope = $this->makeUser(['username' => 'in-scope-moderator']);
        $outOfScope = $this->makeUser(['username' => 'out-of-scope-moderator']);
        $admin = $this->makeAdmin(['username' => 'profile-admin']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $inScope['id']);
        (new BoardModeratorRepository($this->db))->assign((int) $otherBoard['id'], (int) $outOfScope['id']);
        $this->makeThread($board, $author, 'Scoped participation');

        foreach ([$plain, $globalModerator, $outOfScope, $author] as $viewer) {
            $this->actingAs($viewer);
            $page = $this->get('/u/galadriel');
            $this->assertStatus(200, $page);
            $this->assertDontSeeText($page, 'Moderator context');
            $this->assertDontSeeText($page, '/mod/u/' . (int) $author['id']);
        }

        foreach ([$inScope, $admin] as $viewer) {
            $this->actingAs($viewer);
            $page = $this->get('/u/galadriel');
            $this->assertStatus(200, $page);
            $this->assertSeeText($page, 'Moderator context');
            $this->assertSeeText($page, 'No active sanctions.');
            $this->assertSeeText($page, '/mod/u/' . (int) $author['id']);
            $this->assertSeeText($page, 'Open member record');
        }
    }

    public function test_moderator_context_states_a_live_suspension(): void
    {
        $author = $this->makeUser([
            'username' => 'sanctioned',
            'status' => 'suspended',
            'suspended_until' => '2099-01-01 00:00:00',
        ]);
        $this->actingAs($this->makeAdmin(['username' => 'sanction-admin']));

        $page = $this->get('/u/sanctioned');

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Moderator context');
        $this->assertSeeText($page, 'This member is suspended.');
        $this->assertSeeText($page, '/mod/u/' . (int) $author['id']);
    }

    public function test_topics_search_pagination_and_page_clamping(): void
    {
        [$board, $author] = $this->seedAuthor();
        for ($i = 1; $i <= 22; $i++) {
            $this->makeThread($board, $author, sprintf('Topic number %02d', $i));
        }

        $first = $this->get('/u/galadriel', ['tab' => 'threads']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, '22 entries');
        $this->assertSeeText($first, 'Page 1 of 2');
        $this->assertSeeText($first, 'Topic number 22');
        $this->assertDontSeeText($first, 'Topic number 01');

        $clamped = $this->get('/u/galadriel', ['tab' => 'threads', 'page' => '999']);
        $this->assertStatus(200, $clamped);
        $this->assertSeeText($clamped, 'Page 2 of 2');
        $this->assertSeeText($clamped, 'Topic number 01');
        $this->assertDontSeeText($clamped, 'Topic number 22');

        $hit = $this->get('/u/galadriel', ['tab' => 'threads', 'q' => 'number 07']);
        $this->assertStatus(200, $hit);
        $this->assertSeeText($hit, 'Topic number 07');
        $this->assertSeeText($hit, '1 entry');
        $this->assertDontSeeText($hit, 'Topic number 08');

        $literalWildcard = $this->get('/u/galadriel', ['tab' => 'threads', 'q' => '%']);
        $this->assertStatus(200, $literalWildcard);
        $this->assertSeeText($literalWildcard, 'Nothing matches');
    }

    public function test_posts_search_pagination_and_page_clamping(): void
    {
        [$board, $author] = $this->seedAuthor();
        $starter = $this->makeUser(['username' => 'post-starter']);
        $thread = $this->makeThread($board, $starter, 'A long exchange');
        for ($i = 1; $i <= 22; $i++) {
            $this->posting()->reply(
                $this->userEntity($author),
                (int) $thread['thread_id'],
                ['body' => sprintf('Profile response %02d', $i)],
            );
        }

        $first = $this->get('/u/galadriel', ['tab' => 'posts']);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, '22 entries');
        $this->assertSeeText($first, 'Page 1 of 2');
        $this->assertSeeText($first, 'Profile response 22');
        $this->assertDontSeeText($first, 'Profile response 01');

        $clamped = $this->get('/u/galadriel', ['tab' => 'posts', 'page' => '999']);
        $this->assertStatus(200, $clamped);
        $this->assertSeeText($clamped, 'Page 2 of 2');
        $this->assertSeeText($clamped, 'Profile response 01');

        $hit = $this->get('/u/galadriel', ['tab' => 'posts', 'q' => 'response 13']);
        $this->assertStatus(200, $hit);
        $this->assertSeeText($hit, 'Profile response 13');
        $this->assertSeeText($hit, '1 entry');

        $literalWildcard = $this->get('/u/galadriel', ['tab' => 'posts', 'q' => '_']);
        $this->assertStatus(200, $literalWildcard);
        $this->assertSeeText($literalWildcard, 'Nothing matches');
    }

    public function test_topics_and_posts_sort_by_newest_or_external_commends(): void
    {
        [$board, $author] = $this->seedAuthor();
        $reader = $this->makeUser(['username' => 'commend-reader']);
        $starter = $this->makeUser(['username' => 'sort-starter']);

        $olderTopic = $this->makeThread($board, $author, 'Older admired topic');
        $newerTopic = $this->makeThread($board, $author, 'Newer quiet topic');
        $olderOp = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$olderTopic['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ? WHERE id = ?', ['2026-01-01 00:00:00', $olderTopic['thread_id']]);
        $this->db->run('UPDATE threads SET created_at = ? WHERE id = ?', ['2026-01-02 00:00:00', $newerTopic['thread_id']]);
        $this->db->run(
            'INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$olderOp, (int) $reader['id'], '👍'],
        );

        $replyThread = $this->makeThread($board, $starter, 'Reply sorting topic');
        $olderPost = $this->posting()->reply($this->userEntity($author), $replyThread['thread_id'], ['body' => 'Older admired reply']);
        $newerPost = $this->posting()->reply($this->userEntity($author), $replyThread['thread_id'], ['body' => 'Newer quiet reply']);
        $this->db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-01-01 00:00:00', $olderPost]);
        $this->db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-01-02 00:00:00', $newerPost]);
        $this->db->run(
            'INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$olderPost, (int) $reader['id'], '👍'],
        );

        $topicNewest = $this->get('/u/galadriel', ['tab' => 'threads', 'sort' => 'newest']);
        $this->assertTextOrder($topicNewest->body(), 'Newer quiet topic', 'Older admired topic');
        $topicCommends = $this->get('/u/galadriel', ['tab' => 'threads', 'sort' => 'commends']);
        $this->assertTextOrder($topicCommends->body(), 'Older admired topic', 'Newer quiet topic');

        $postNewest = $this->get('/u/galadriel', ['tab' => 'posts', 'sort' => 'newest']);
        $this->assertTextOrder($postNewest->body(), 'Newer quiet reply', 'Older admired reply');
        $postCommends = $this->get('/u/galadriel', ['tab' => 'posts', 'sort' => 'commends']);
        $this->assertTextOrder($postCommends->body(), 'Older admired reply', 'Newer quiet reply');
    }

    public function test_profile_activity_excludes_nonpublic_pending_deleted_and_anonymous_content(): void
    {
        [$public, $author] = $this->seedAuthor();
        $hidden = $this->makeBoard($this->makeCategory('Hidden'), ['slug' => 'hidden-seat', 'visibility' => 'hidden']);
        $private = $this->makeBoard($this->makeCategory('Private'), ['slug' => 'private-seat', 'visibility' => 'private']);
        $privateMembers = new BoardMemberRepository($this->db);
        $privateMembers->add((int) $private['id'], (int) $author['id'], null);

        $this->makeThread($public, $author, 'Visible public topic', 'Visible public opening');
        $this->makeThread($hidden, $author, 'Hidden topic marker', 'Hidden opening marker');
        $this->makeThread($private, $author, 'Private topic marker', 'Private opening marker');
        $deleted = $this->makeThread($public, $author, 'Deleted topic marker', 'Deleted opening marker');
        $pending = $this->makeThread($public, $author, 'Pending topic marker', 'Pending opening marker');
        $anonymous = $this->makeThread($public, $author, 'Anonymous topic marker', 'Anonymous opening marker');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pending['thread_id']]);
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE thread_id = ? AND is_op = 1', [$anonymous['thread_id']]);

        $starter = $this->makeUser(['username' => 'visibility-starter']);
        $privateMembers->add((int) $private['id'], (int) $starter['id'], null);
        $publicThread = $this->makeThread($public, $starter, 'Public reply container');
        $hiddenThread = $this->makeThread($hidden, $starter, 'Hidden reply container');
        $privateThread = $this->makeThread($private, $starter, 'Private reply container');
        $visibleReply = $this->posting()->reply($this->userEntity($author), $publicThread['thread_id'], ['body' => 'Visible reply marker']);
        $hiddenReply = $this->posting()->reply($this->userEntity($author), $hiddenThread['thread_id'], ['body' => 'Hidden reply marker']);
        $privateReply = $this->posting()->reply($this->userEntity($author), $privateThread['thread_id'], ['body' => 'Private reply marker']);
        $deletedReply = $this->posting()->reply($this->userEntity($author), $publicThread['thread_id'], ['body' => 'Deleted reply marker']);
        $pendingReply = $this->posting()->reply($this->userEntity($author), $publicThread['thread_id'], ['body' => 'Pending reply marker']);
        $anonymousReply = $this->posting()->reply($this->userEntity($author), $publicThread['thread_id'], ['body' => 'Anonymous reply marker']);
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$deletedReply]);
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$pendingReply]);
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE id = ?', [$anonymousReply]);

        $topics = $this->get('/u/galadriel', ['tab' => 'threads']);
        $this->assertStatus(200, $topics);
        $this->assertSeeText($topics, 'Visible public topic');
        foreach (['Hidden topic marker', 'Private topic marker', 'Deleted topic marker', 'Pending topic marker', 'Anonymous topic marker'] as $marker) {
            $this->assertDontSeeText($topics, $marker);
        }

        $posts = $this->get('/u/galadriel', ['tab' => 'posts']);
        $this->assertStatus(200, $posts);
        $this->assertSeeText($posts, 'Visible public opening');
        $this->assertSeeText($posts, 'Visible reply marker');
        foreach (['Hidden opening marker', 'Private opening marker', 'Deleted opening marker', 'Pending opening marker', 'Anonymous opening marker', 'Hidden reply marker', 'Private reply marker', 'Deleted reply marker', 'Pending reply marker', 'Anonymous reply marker'] as $marker) {
            $this->assertDontSeeText($posts, $marker);
        }
        self::assertGreaterThan(0, $visibleReply);
        self::assertGreaterThan(0, $hiddenReply);
        self::assertGreaterThan(0, $privateReply);
    }

    public function test_commends_lists_only_externally_commended_public_posts(): void
    {
        [$board, $author] = $this->seedAuthor();
        $reader = $this->makeUser(['username' => 'commend-reader-two']);
        $admired = $this->makeThread($board, $author, 'Externally commended post');
        $selfOnly = $this->makeThread($board, $author, 'Self-commended post');
        $quiet = $this->makeThread($board, $author, 'Uncommended post');
        $admiredOp = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$admired['thread_id']]);
        $selfOp = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$selfOnly['thread_id']]);
        $this->db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$admiredOp, (int) $reader['id'], '👍']);
        $this->db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$selfOp, (int) $author['id'], '👍']);

        $page = $this->get('/u/galadriel', ['tab' => 'commends']);

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Most commended');
        $this->assertSeeText($page, 'Externally commended post');
        $this->assertDontSeeText($page, 'Self-commended post');
        $this->assertDontSeeText($page, 'Uncommended post');
        unset($quiet);
    }

    public function test_connections_support_search_modes_legacy_routes_and_owner_removal(): void
    {
        [, $author] = $this->seedAuthor();
        $follower = $this->makeUser(['username' => 'lindir', 'display_name' => 'Lindir']);
        $followed = $this->makeUser(['username' => 'celeborn', 'display_name' => 'Celeborn']);
        $follows = new FollowRepository($this->db);
        $follows->follow((int) $follower['id'], (int) $author['id']);
        $follows->follow((int) $author['id'], (int) $followed['id']);
        $this->actingAs($author);

        $followers = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertStatus(200, $followers);
        $this->assertSeeText($followers, 'Followers · 1');
        $this->assertSeeText($followers, 'Following · 1');
        $this->assertSeeText($followers, 'Lindir');
        $this->assertSeeText($followers, '/u/galadriel/followers/' . (int) $follower['id'] . '/remove');
        $this->assertSeeText($followers, 'Remove follower');

        $following = $this->get('/u/galadriel', ['tab' => 'connections', 'c' => 'following', 'cq' => 'cele']);
        $this->assertStatus(200, $following);
        $this->assertSeeText($following, 'Celeborn');
        $this->assertDontSeeText($following, 'Lindir');

        $miss = $this->get('/u/galadriel', ['tab' => 'connections', 'cq' => '%']);
        $this->assertStatus(200, $miss);
        $this->assertSeeText($miss, 'Nothing matches');

        $this->assertStatus(200, $this->get('/u/galadriel/followers'));
        $this->assertStatus(200, $this->get('/u/galadriel/following'));
        $removed = $this->post('/u/galadriel/followers/' . (int) $follower['id'] . '/remove');
        $this->assertRedirect($removed, '/u/galadriel/followers');
        self::assertFalse($follows->isFollowing((int) $follower['id'], (int) $author['id']));
    }

    public function test_connections_empty_state_and_privacy_match_legacy_routes(): void
    {
        [, $author] = $this->seedAuthor();
        $viewer = $this->makeUser(['username' => 'connection-viewer']);

        $empty = $this->get('/u/galadriel', ['tab' => 'connections', 'c' => 'following']);
        $this->assertStatus(200, $empty);
        $this->assertSeeText($empty, 'No one here yet');

        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [(int) $author['id']]);
        $guestGated = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertStatus(200, $guestGated);
        $this->assertSeeText($guestGated, 'signed-in members');

        $this->actingAs($viewer);
        $memberView = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertStatus(200, $memberView);
        $this->assertDontSeeText($memberView, 'signed-in members');

        (new BlockRepository($this->db))->block((int) $author['id'], (int) $viewer['id']);
        $this->assertStatus(404, $this->get('/u/galadriel', ['tab' => 'connections']));
        $this->assertStatus(404, $this->get('/u/galadriel/followers'));
    }

    public function test_community_disabled_hides_community_tabs_and_falls_back_to_overview(): void
    {
        $this->seedAuthor();
        (new SettingRepository($this->db))->set('features', ['community' => false]);

        foreach (['commends', 'connections'] as $tab) {
            $page = $this->get('/u/galadriel', ['tab' => $tab]);
            $this->assertStatus(200, $page);
            $this->assertSeeText($page, 'aria-current="page"');
            $this->assertSeeText($page, 'No public activity yet');
            $this->assertDontSeeText($page, '?tab=commends');
            $this->assertDontSeeText($page, '?tab=connections');
        }
    }

    public function test_unknown_tab_falls_back_to_overview(): void
    {
        $this->seedAuthor();

        $page = $this->get('/u/galadriel', ['tab' => 'not-a-tab']);

        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'aria-current="page"');
        $this->assertSeeText($page, 'No public activity yet');
    }
}
