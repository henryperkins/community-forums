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

    private function xpath(\App\Core\Response $response): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());

        return new \DOMXPath($document);
    }

    /** @return array<string,string> row title => the commend figure rendered beside it */
    private function rowCommends(\App\Core\Response $response): array
    {
        $counts = [];
        foreach ($this->xpath($response)->query('//li[contains(@class,"profile-row")]') as $row) {
            $title = (new \DOMXPath($row->ownerDocument))->query('.//a[contains(@class,"profile-row-title")]', $row)->item(0);
            $count = (new \DOMXPath($row->ownerDocument))->query('.//span[contains(@class,"profile-row-commends")]', $row)->item(0);
            if ($title !== null && $count !== null) {
                $counts[trim($title->textContent)] = trim($count->textContent);
            }
        }

        return $counts;
    }

    public function test_profile_counts_open_connections_and_excerpts_use_rendered_text(): void
    {
        [$board, $author] = $this->seedAuthor();
        $this->db->run('UPDATE users SET bio = ? WHERE id = ?', ['Keeper of the lamps.', (int) $author['id']]);
        $this->makeThread($board, $author, 'Rendered excerpt topic', 'A **bold claim** and a [record](https://example.com/record).');

        $page = $this->get('/u/galadriel');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'href="/u/galadriel?tab=connections"');
        $this->assertSeeText($page, 'href="/u/galadriel?tab=connections&amp;c=following"');
        $this->assertDontSeeText($page, 'href="/u/galadriel/followers"');
        $this->assertDontSeeText($page, 'href="/u/galadriel/following"');
        self::assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/u/galadriel">#', $page->body());
        self::assertMatchesRegularExpression('#<meta name="description" content="Galadriel \(@galadriel\) — Keeper of the lamps\.">#', $page->body());
        $this->assertSeeText($page, 'A bold claim and a record.');

        foreach (['posts', 'threads'] as $tab) {
            $list = $this->get('/u/galadriel', ['tab' => $tab]);
            $this->assertSeeText($list, 'A bold claim and a record.');
            $this->assertDontSeeText($list, '**bold claim**');
            $this->assertDontSeeText($list, 'example.com');
            self::assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/u/galadriel">#', $list->body());
        }
    }

    public function test_excerpts_fall_back_to_the_body_when_the_render_cache_is_blank(): void
    {
        [$board, $author] = $this->seedAuthor();
        $topic = $this->makeThread($board, $author, 'Uncached excerpt topic', 'Words kept without a cache.');
        $this->db->run('UPDATE posts SET body_html = NULL WHERE thread_id = ?', [$topic['thread_id']]);

        foreach (['posts', 'threads'] as $tab) {
            $this->assertSeeText($this->get('/u/galadriel', ['tab' => $tab]), 'Words kept without a cache.');
        }
        $this->assertSeeText($this->get('/u/galadriel'), 'Words kept without a cache.');
    }

    public function test_commend_figures_count_only_other_members(): void
    {
        [$board, $author] = $this->seedAuthor();
        $first = $this->makeUser(['username' => 'first-reader']);
        $second = $this->makeUser(['username' => 'second-reader']);
        $topic = $this->makeThread($board, $author, 'Counted topic');
        $op = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$topic['thread_id']]);
        foreach ([[(int) $author['id'], '👍'], [(int) $first['id'], '👍'], [(int) $second['id'], '🎉']] as [$reactor, $emoji]) {
            $this->db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$op, $reactor, $emoji]);
        }

        self::assertSame(['Counted topic' => '2'], $this->rowCommends($this->get('/u/galadriel', ['tab' => 'threads'])));
        self::assertSame(['Counted topic' => '2'], $this->rowCommends($this->get('/u/galadriel', ['tab' => 'posts', 'sort' => 'commends'])));
        $this->assertSeeText($this->get('/u/galadriel'), '2 commends');
        $commends = $this->xpath($this->get('/u/galadriel', ['tab' => 'commends']));
        self::assertSame('2', trim($commends->query('//span[contains(@class,"profile-commend-count")]')->item(0)->textContent));
    }

    public function test_commends_empty_state_sits_under_its_section_heading(): void
    {
        $this->seedAuthor();

        $page = $this->get('/u/galadriel', ['tab' => 'commends']);

        $this->assertSeeText($page, '<h3>No commended posts yet.</h3>');
        self::assertSame(1, $this->xpath($page)->query('//section[contains(@class,"profile-commend-list")]/h2')->length);
    }

    public function test_block_asks_before_it_commits_and_applies_its_intent_once(): void
    {
        [, $author] = $this->seedAuthor();
        $blocker = $this->makeUser(['username' => 'blocker']);
        $follows = new FollowRepository($this->db);
        $blocks = new BlockRepository($this->db);
        $follows->follow((int) $blocker['id'], (int) $author['id']);
        $this->actingAs($blocker);

        $page = $this->get('/u/galadriel');
        $this->assertSeeText($page, 'can no longer message you or mention you');
        $this->assertSeeText($page, 'data-copy-status');
        $xpath = $this->xpath($page);
        // The only block form on the page is the confirmation step.
        self::assertSame(1, $xpath->query('//form[@action="/u/galadriel/block"]')->length);
        self::assertSame(1, $xpath->query('//details[@class="profile-block"]/form[@action="/u/galadriel/block"][.//input[@name="intent" and @value="block"]][.//button[normalize-space()="Block @galadriel"]]')->length);

        // A double submit, or a stale tab's confirmation, never undoes a block.
        $this->assertRedirect($this->post('/u/galadriel/block', ['intent' => 'block']), '/u/galadriel');
        $this->assertRedirect($this->post('/u/galadriel/block', ['intent' => 'block']), '/u/galadriel');
        self::assertTrue($blocks->blocks((int) $blocker['id'], (int) $author['id']));
        self::assertFalse($follows->isFollowing((int) $blocker['id'], (int) $author['id']));

        $blocked = $this->get('/u/galadriel');
        $this->assertSeeText($blocked, '>Unblock<');
        $this->assertDontSeeText($blocked, 'Block @galadriel');
        self::assertSame(1, $this->xpath($blocked)->query('//form[@action="/u/galadriel/block"][.//input[@name="intent" and @value="unblock"]]')->length);

        $this->post('/u/galadriel/block', ['intent' => 'unblock']);
        $this->post('/u/galadriel/block', ['intent' => 'unblock']);
        self::assertFalse($blocks->blocks((int) $blocker['id'], (int) $author['id']));

        // Forms that send no intent (the DM and settings lists) still toggle.
        $this->post('/u/galadriel/block');
        self::assertTrue($blocks->blocks((int) $blocker['id'], (int) $author['id']));
        $this->assertRedirect($this->post('/u/galadriel/block', ['return' => "/\t/evil.example"]), '/u/galadriel');
        self::assertFalse($blocks->blocks((int) $blocker['id'], (int) $author['id']));
    }

    public function test_connections_page_past_the_first_screen_and_honor_a_local_return(): void
    {
        [, $author] = $this->seedAuthor();
        $follows = new FollowRepository($this->db);
        $first = null;
        for ($i = 1; $i <= 21; $i++) {
            $follower = $this->makeUser(['username' => sprintf('follower%02d', $i), 'display_name' => sprintf('Followers Fan %02d', $i)]);
            $follows->follow((int) $follower['id'], (int) $author['id']);
            $first ??= $follower;
        }
        self::assertNotNull($first);
        $this->actingAs($author);

        $firstPage = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertSeeText($firstPage, 'Page 1 of 2');
        $this->assertSeeText($firstPage, 'Followers Fan 21');
        $this->assertDontSeeText($firstPage, 'Followers Fan 01');

        $secondPage = $this->get('/u/galadriel', ['tab' => 'connections', 'page' => '2']);
        $this->assertSeeText($secondPage, 'Page 2 of 2');
        $this->assertSeeText($secondPage, 'Followers Fan 01');
        $this->assertDontSeeText($secondPage, 'Followers Fan 21');
        $this->assertSeeText($secondPage, 'name="return" value="/u/galadriel?tab=connections&amp;page=2"');
        $this->assertSeeText($this->get('/u/galadriel', ['tab' => 'connections', 'page' => '99']), 'Page 2 of 2');

        // A search for the word "followers" is a search, not the default mode.
        $search = $this->get('/u/galadriel', ['tab' => 'connections', 'cq' => 'followers']);
        $this->assertSeeText($search, 'Page 1 of 2');
        $this->assertSeeText($search, 'href="/u/galadriel?tab=connections&amp;cq=followers&amp;page=2"');

        $legacy = $this->get('/u/galadriel/followers', ['page' => '2']);
        $this->assertSeeText($legacy, 'Page 2 of 2');
        $this->assertSeeText($legacy, 'Followers Fan 01');
        $this->assertSeeText($legacy, '<span class="muted person-rep">0 regard</span>');
        $this->assertSeeText($legacy, 'name="return" value="/u/galadriel/followers?page=2"');
        self::assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/u/galadriel">#', $legacy->body());

        $removed = $this->post('/u/galadriel/followers/' . (int) $first['id'] . '/remove', [
            'return' => '/u/galadriel?tab=connections&page=2',
        ]);
        $this->assertRedirect($removed, '/u/galadriel?tab=connections&page=2');
        self::assertFalse($follows->isFollowing((int) $first['id'], (int) $author['id']));

        foreach (['https://evil.example/phish', '//evil.example', "/\t/evil.example", '/\\evil.example'] as $offSite) {
            $next = $this->makeUser();
            $follows->follow((int) $next['id'], (int) $author['id']);
            $rejected = $this->post('/u/galadriel/followers/' . (int) $next['id'] . '/remove', ['return' => $offSite]);
            $this->assertRedirect($rejected, '/u/galadriel/followers');
        }
    }

    public function test_following_pages_on_the_tab_and_the_standalone_list(): void
    {
        [, $author] = $this->seedAuthor();
        $follows = new FollowRepository($this->db);
        for ($i = 1; $i <= 21; $i++) {
            $followed = $this->makeUser(['username' => sprintf('followed%02d', $i), 'display_name' => sprintf('Followed %02d', $i)]);
            $follows->follow((int) $author['id'], (int) $followed['id']);
        }

        $tab = $this->get('/u/galadriel', ['tab' => 'connections', 'c' => 'following', 'page' => '2']);
        $this->assertSeeText($tab, 'Page 2 of 2');
        $this->assertSeeText($tab, 'Followed 01');
        $this->assertDontSeeText($tab, 'Followed 21');
        $this->assertSeeText($tab, 'href="/u/galadriel?tab=connections&amp;c=following"');

        $legacy = $this->get('/u/galadriel/following');
        $this->assertSeeText($legacy, 'Page 1 of 2');
        $this->assertSeeText($legacy, 'Followed 21');
        $this->assertDontSeeText($legacy, 'Followed 01');
    }

    public function test_guest_connection_lists_leave_out_members_only_accounts(): void
    {
        [, $author] = $this->seedAuthor();
        $follows = new FollowRepository($this->db);
        $open = $this->makeUser(['username' => 'lindir', 'display_name' => 'Lindir']);
        $private = $this->makeUser(['username' => 'celebrian', 'display_name' => 'Celebrian']);
        $privateFollowed = $this->makeUser(['username' => 'arwen', 'display_name' => 'Arwen']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id IN (?, ?)", [(int) $private['id'], (int) $privateFollowed['id']]);
        $follows->follow((int) $open['id'], (int) $author['id']);
        $follows->follow((int) $private['id'], (int) $author['id']);
        $follows->follow((int) $author['id'], (int) $privateFollowed['id']);

        $guestTab = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertSeeText($guestTab, 'Lindir');
        $this->assertDontSeeText($guestTab, 'celebrian');
        $this->assertSeeText($this->get('/u/galadriel', ['tab' => 'connections', 'cq' => 'celeb']), 'Nothing matches');
        $this->assertDontSeeText($this->get('/u/galadriel', ['tab' => 'connections', 'c' => 'following']), 'arwen');
        $guestLegacy = $this->get('/u/galadriel/followers');
        $this->assertSeeText($guestLegacy, 'Lindir');
        $this->assertDontSeeText($guestLegacy, 'celebrian');
        $this->assertDontSeeText($this->get('/u/galadriel/following'), 'arwen');

        $this->actingAs($this->makeUser(['username' => 'signed-in-reader']));
        $memberTab = $this->get('/u/galadriel', ['tab' => 'connections']);
        $this->assertSeeText($memberTab, 'Celebrian');
        $this->assertSeeText($this->get('/u/galadriel', ['tab' => 'connections', 'c' => 'following']), 'Arwen');
        $this->assertSeeText($this->get('/u/galadriel/followers'), 'Celebrian');
    }

    public function test_standalone_empty_list_names_what_will_appear(): void
    {
        $this->seedAuthor();

        $followers = $this->get('/u/galadriel/followers');
        $this->assertSeeText($followers, 'No followers yet.');
        $this->assertSeeText($followers, 'When members follow Galadriel, they will be listed here.');
        $this->assertSeeText($this->get('/u/galadriel/following'), 'Galadriel is not following anyone yet.');
    }

    public function test_profile_pages_skip_the_composer_script(): void
    {
        $this->seedAuthor();
        $this->makeUser(['username' => 'gated-seat']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE username = 'gated-seat'");
        $composer = '#/assets/(?:dist/)?composer[-.]#';

        self::assertMatchesRegularExpression($composer, $this->get('/')->body());
        foreach (['/u/galadriel', '/u/galadriel/followers', '/u/galadriel/following', '/u/gated-seat'] as $path) {
            self::assertDoesNotMatchRegularExpression($composer, $this->get($path)->body(), $path);
        }
    }

    public function test_gated_profile_is_not_indexed(): void
    {
        $author = $this->makeUser(['username' => 'gated-seat']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [(int) $author['id']]);

        foreach (['/u/gated-seat', '/u/gated-seat/followers'] as $path) {
            $page = $this->get($path);
            $this->assertSeeText($page, 'name="robots" content="noindex, nofollow"');
            $this->assertSeeText($page, 'content="This profile is visible to signed-in members."');
            self::assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/u/gated-seat">#', $page->body());
        }

        $this->actingAs($this->makeUser(['username' => 'gated-reader']));
        $this->assertDontSeeText($this->get('/u/gated-seat'), 'noindex');
    }
}
