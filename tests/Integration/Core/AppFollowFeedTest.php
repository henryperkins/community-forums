<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use Tests\Support\TestCase;

/**
 * Follows + the Following feed (P2-09): block-aware follow toggling, a
 * new-follower notification, and a query-time feed gated to accessible content.
 */
final class AppFollowFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    public function test_follow_toggle_creates_edge_and_notifies(): void
    {
        $a = $this->makeUser(['username' => 'alice']);
        $b = $this->makeUser(['username' => 'bob']);
        $this->actingAs($a);

        $res = $this->post('/u/bob/follow');
        $this->assertRedirect($res, '/u/bob');
        self::assertTrue((new FollowRepository($this->db))->isFollowing((int) $a['id'], (int) $b['id']));

        // Target gets exactly one follow notification.
        $notifs = new NotificationRepository($this->db);
        self::assertSame(1, $notifs->unreadCount((int) $b['id']));

        // Toggling again unfollows.
        $this->post('/u/bob/follow');
        self::assertFalse((new FollowRepository($this->db))->isFollowing((int) $a['id'], (int) $b['id']));
    }

    public function test_cannot_follow_self(): void
    {
        $a = $this->makeUser(['username' => 'solo']);
        $this->actingAs($a);

        $this->post('/u/solo/follow');
        self::assertFalse((new FollowRepository($this->db))->isFollowing((int) $a['id'], (int) $a['id']));
    }

    public function test_blocked_pair_cannot_follow(): void
    {
        $a = $this->makeUser(['username' => 'ava']);
        $b = $this->makeUser(['username' => 'ben']);
        // Ben blocks Ava.
        (new BlockRepository($this->db))->block((int) $b['id'], (int) $a['id']);

        $this->actingAs($a);
        $this->post('/u/ben/follow');
        self::assertFalse((new FollowRepository($this->db))->isFollowing((int) $a['id'], (int) $b['id']));
    }

    public function test_following_feed_includes_followed_and_excludes_others(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'general', 'visibility' => 'public']);

        $viewer = $this->makeUser(['username' => 'viewer']);
        $followed = $this->makeUser(['username' => 'followed']);
        $stranger = $this->makeUser(['username' => 'stranger']);

        $this->makeThread($board, $followed, 'Followed Topic', 'hello from followed');
        $this->makeThread($board, $stranger, 'Stranger Topic', 'hello from stranger');

        (new FollowRepository($this->db))->follow((int) $viewer['id'], (int) $followed['id']);

        $this->actingAs($viewer);
        $res = $this->get('/feed');
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Followed Topic');
        $this->assertDontSeeText($res, 'Stranger Topic');
    }

    public function test_feed_excludes_deleted_and_private_board_activity(): void
    {
        $cat = $this->makeCategory();
        $public = $this->makeBoard($cat, ['slug' => 'pub', 'visibility' => 'public']);
        $private = $this->makeBoard($cat, ['slug' => 'priv', 'visibility' => 'private']);

        $viewer = $this->makeUser(['username' => 'watch']);
        $author = $this->makeUser(['username' => 'maker']);
        (new FollowRepository($this->db))->follow((int) $viewer['id'], (int) $author['id']);

        // A deleted public thread must not appear.
        $deleted = $this->makeThread($public, $author, 'Deleted Topic', 'gone soon');
        $this->threads()->softDelete($deleted['thread_id'], (int) $author['id']);

        // The author is a member of the private board and posts there; the viewer
        // is NOT a member, so it must not surface in their feed.
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);
        $this->makeThread($private, $author, 'Secret Topic', 'members only');

        // A visible public thread does appear.
        $this->makeThread($public, $author, 'Visible Topic', 'for everyone');

        $this->actingAs($viewer);
        $res = $this->get('/feed');
        $this->assertSeeText($res, 'Visible Topic');
        $this->assertDontSeeText($res, 'Deleted Topic');
        $this->assertDontSeeText($res, 'Secret Topic');
    }
}
