<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\FollowRepository;
use App\Service\FeedService;
use Tests\Support\TestCase;

/**
 * Masked-anonymous posting (ADMIN §1.3, PHASE_2_PLAN §3 Gate A): a per-board
 * opt-in where a post's PUBLIC author identity is hidden ("Anonymous") while the
 * real users.user_id is preserved. Anonymity must hold on every public surface
 * (thread view, listings, profile, feed, notifications); moderators can reveal
 * the author via an audited action; reputation/totals are unaffected.
 */
final class AppAnonymousPostingTest extends TestCase
{
    private int $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'siteadmin']);
        $this->cat = $this->makeCategory();
    }

    private function anonBoard(string $slug = 'anon'): array
    {
        return $this->makeBoard($this->cat, ['slug' => $slug, 'allow_anonymous' => 1]);
    }

    private function opPostId(int $threadId): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);
    }

    // ---- read-path masking -------------------------------------------------

    public function test_anonymous_byline_is_masked_in_thread_view(): void
    {
        $board = $this->anonBoard();
        $alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Real']);
        $t = $this->posting()->createThread($this->userEntity($alice), [
            'board_id' => (int) $board['id'], 'title' => 'Anon topic', 'body' => 'secret body here', 'is_anonymous' => '1',
        ]);
        // A normal (non-anon) reply by a different user on the same board.
        $bob = $this->makeUser(['username' => 'bob', 'display_name' => 'Bob Real']);
        $this->posting()->reply($this->userEntity($bob), $t['thread_id'], ['body' => 'visible reply text']);

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
        $this->assertStatus(200, $resp);
        // Content is public; identity of the anonymous OP is masked.
        $this->assertSeeText($resp, 'secret body here');
        $this->assertSeeText($resp, 'Anonymous');
        $this->assertDontSeeText($resp, 'Alice Real');
        $this->assertDontSeeText($resp, '/u/alice');
        // The non-anon replier is shown normally.
        $this->assertSeeText($resp, 'Bob Real');
        $this->assertSeeText($resp, '/u/bob');
    }

    public function test_anonymous_admin_post_hides_the_staff_badge(): void
    {
        $board = $this->anonBoard();
        $boss = $this->makeAdmin(['username' => 'boss', 'display_name' => 'Boss Person']);
        $t = $this->posting()->createThread($this->userEntity($boss), [
            'board_id' => (int) $board['id'], 'title' => 'Notice', 'body' => 'from a masked admin', 'is_anonymous' => '1',
        ]);

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
        $this->assertSeeText($resp, 'Anonymous');
        $this->assertDontSeeText($resp, 'Boss Person');
        $this->assertDontSeeText($resp, 'badge-staff');
    }

    public function test_listing_masks_anonymous_thread_starter(): void
    {
        $board = $this->anonBoard();
        $alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Real']);
        $this->posting()->createThread($this->userEntity($alice), [
            'board_id' => (int) $board['id'], 'title' => 'Listed anon topic', 'body' => 'x', 'is_anonymous' => '1',
        ]);

        $resp = $this->get('/c/' . $board['slug']);
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Listed anon topic');   // title still listed
        $this->assertSeeText($resp, 'by Anonymous');
        $this->assertDontSeeText($resp, 'Alice Real');
        $this->assertDontSeeText($resp, '/u/alice');
    }

    // ---- server-side gate --------------------------------------------------

    public function test_anonymity_is_gated_by_board_setting_server_side(): void
    {
        $anon = $this->anonBoard('anon');
        $normal = $this->makeBoard($this->cat, ['slug' => 'plain', 'allow_anonymous' => 0]);
        $u = $this->makeUser(['username' => 'u1']);

        // Anon board honours the opt-in (thread + reply).
        $ta = $this->posting()->createThread($this->userEntity($u), [
            'board_id' => (int) $anon['id'], 'title' => 'A', 'body' => 'a', 'is_anonymous' => '1',
        ]);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_anonymous FROM posts WHERE id = ?', [$this->opPostId($ta['thread_id'])]));
        $ra = $this->posting()->reply($this->userEntity($u), $ta['thread_id'], ['body' => 'r', 'is_anonymous' => '1']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_anonymous FROM posts WHERE id = ?', [$ra]));

        // Normal board forces is_anonymous=0 even when requested.
        $tn = $this->posting()->createThread($this->userEntity($u), [
            'board_id' => (int) $normal['id'], 'title' => 'B', 'body' => 'b', 'is_anonymous' => '1',
        ]);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_anonymous FROM posts WHERE id = ?', [$this->opPostId($tn['thread_id'])]));
        $rn = $this->posting()->reply($this->userEntity($u), $tn['thread_id'], ['body' => 'r2', 'is_anonymous' => '1']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_anonymous FROM posts WHERE id = ?', [$rn]));
    }

    // ---- author-attributed surfaces (exclude, not mask) --------------------

    public function test_anonymous_content_is_excluded_from_profile_activity(): void
    {
        $board = $this->anonBoard();
        $carl = $this->makeUser(['username' => 'carl', 'display_name' => 'Carl']);
        $this->posting()->createThread($this->userEntity($carl), [
            'board_id' => (int) $board['id'], 'title' => 'Public Topic Carl', 'body' => 'x',
        ]);
        $this->posting()->createThread($this->userEntity($carl), [
            'board_id' => (int) $board['id'], 'title' => 'Hidden Topic Carl', 'body' => 'y', 'is_anonymous' => '1',
        ]);

        $resp = $this->get('/u/carl');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Public Topic Carl');
        $this->assertDontSeeText($resp, 'Hidden Topic Carl');
    }

    public function test_anonymous_posts_are_excluded_from_the_following_feed(): void
    {
        $board = $this->anonBoard();
        $author = $this->makeUser(['username' => 'feedauthor']);
        $viewer = $this->makeUser(['username' => 'follower']);
        (new FollowRepository($this->db))->follow((int) $viewer['id'], (int) $author['id']);

        // One normal thread, one anonymous thread by the followed author.
        $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'], 'title' => 'Normal Feed Topic', 'body' => 'normalfeedbody',
        ]);
        $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'], 'title' => 'Anon Feed Topic', 'body' => 'anonfeedbody', 'is_anonymous' => '1',
        ]);

        $feed = new FeedService(
            $this->db,
            new FollowRepository($this->db),
            new BlockRepository($this->db),
            new BoardMemberRepository($this->db),
        );
        $bodies = array_map(static fn (array $r): string => (string) $r['body'], $feed->forUser((int) $viewer['id'])['items']);
        self::assertContains('normalfeedbody', $bodies);
        self::assertNotContains('anonfeedbody', $bodies);
    }

    // ---- notifications -----------------------------------------------------

    public function test_notification_actor_is_masked_for_an_anonymous_reply(): void
    {
        $board = $this->anonBoard();
        $alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Real']);
        $bob = $this->makeUser(['username' => 'bob', 'display_name' => 'Bob Real']);

        // Driven over HTTP so the container's PostingService (with notifications)
        // auto-subscribes Alice and fans out Bob's reply.
        $this->actingAs($alice);
        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'], 'title' => 'Notify topic', 'body' => 'opening',
        ]));
        $threadId = (int) $this->db->fetchValue(
            'SELECT id FROM threads WHERE board_id = ? ORDER BY id DESC LIMIT 1',
            [(int) $board['id']],
        );

        $this->actingAs($bob);
        $this->assertRedirectContains($this->post('/t/' . $threadId . '/reply', [
            'body' => 'an anon reply', 'is_anonymous' => '1',
        ]), '#p');

        $this->actingAs($alice);
        $resp = $this->get('/notifications');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Anonymous replied');
        $this->assertDontSeeText($resp, 'Bob Real');
    }

    // ---- moderator reveal --------------------------------------------------

    public function test_reveal_is_authorised_audited_and_keeps_the_byline_masked(): void
    {
        $board = $this->anonBoard();
        $alice = $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Real']);
        $t = $this->posting()->createThread($this->userEntity($alice), [
            'board_id' => (int) $board['id'], 'title' => 'Reveal me', 'body' => 'x', 'is_anonymous' => '1',
        ]);
        $postId = $this->opPostId($t['thread_id']);
        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];

        // A normal member cannot reveal.
        $rando = $this->makeUser(['username' => 'rando']);
        $this->actingAs($rando);
        $this->assertStatus(403, $this->post('/mod/p/' . $postId . '/reveal'));

        // A moderator of a DIFFERENT board cannot reveal.
        $otherBoard = $this->anonBoard('other');
        $wrongMod = $this->makeUser(['username' => 'wrongmod']);
        (new BoardModeratorRepository($this->db))->assign((int) $otherBoard['id'], (int) $wrongMod['id']);
        $this->actingAs($wrongMod);
        $this->assertStatus(403, $this->post('/mod/p/' . $postId . '/reveal'));

        // This board's moderator can reveal — sees the real name once via flash.
        $mod = $this->makeUser(['username' => 'mod']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->actingAs($mod);
        $this->assertRedirect($this->post('/mod/p/' . $postId . '/reveal'), $url . '#p' . $postId);

        // The reveal is NOT a public un-mask: the byline stays "Anonymous" and
        // never links to the real profile, even for the revealing moderator.
        $after = $this->get($url);
        $this->assertSeeText($after, '<span class="post-author">Anonymous</span>');
        $this->assertDontSeeText($after, '/u/alice');

        // Exactly one audited reveal recorded, capturing the real author in the log.
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'reveal_anon' AND target_type = 'post' AND target_id = ?",
            [$postId],
        ));
        $after_json = (string) $this->db->fetchValue(
            "SELECT after_json FROM moderation_log WHERE action = 'reveal_anon' AND target_id = ?",
            [$postId],
        );
        self::assertStringContainsString('alice', $after_json);
    }

    public function test_reveal_rejected_on_a_non_anonymous_post(): void
    {
        $board = $this->anonBoard();
        $alice = $this->makeUser(['username' => 'alice']);
        $t = $this->posting()->createThread($this->userEntity($alice), [
            'board_id' => (int) $board['id'], 'title' => 'Normal', 'body' => 'x', // not anonymous
        ]);
        $postId = $this->opPostId($t['thread_id']);
        $mod = $this->makeUser(['username' => 'mod']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->actingAs($mod);
        $this->assertStatus(403, $this->post('/mod/p/' . $postId . '/reveal'));
    }

    // ---- admin toggle ------------------------------------------------------

    public function test_admin_can_toggle_allow_anonymous_on_a_board(): void
    {
        $admin = $this->makeAdmin(['username' => 'boss']);
        $board = $this->makeBoard($this->cat, ['slug' => 'toggleme', 'allow_anonymous' => 0]);
        $this->actingAs($admin);

        $this->assertRedirect($this->post('/admin/boards/' . (int) $board['id'], [
            'category_id' => $this->cat, 'name' => 'Toggle Me', 'slug' => 'toggleme', 'visibility' => 'public', 'allow_anonymous' => '1',
        ]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT allow_anonymous FROM boards WHERE id = ?', [(int) $board['id']]));

        // Omitting the checkbox turns it back off.
        $this->assertRedirect($this->post('/admin/boards/' . (int) $board['id'], [
            'category_id' => $this->cat, 'name' => 'Toggle Me', 'slug' => 'toggleme', 'visibility' => 'public',
        ]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT allow_anonymous FROM boards WHERE id = ?', [(int) $board['id']]));
    }
}
