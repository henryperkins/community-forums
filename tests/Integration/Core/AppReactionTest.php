<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ReactionRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * Reactions + reaction-derived reputation (P2-02). Covers the acceptance
 * scenarios: reaction retry/idempotency, self-reaction = 0 rep, delete adjusts
 * reputation, and the write gate.
 */
final class AppReactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // initialise the app so the setup gate doesn't intercept HTTP routes
    }

    /** @return array{author:array<string,mixed>, board:array<string,mixed>, thread:array{thread_id:int,slug:string}, op_id:int} */
    private function scenario(): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'React to me', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        return compact('author', 'board', 'thread') + ['op_id' => $opId];
    }

    private function reputation(int $userId): int
    {
        return (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$userId]);
    }

    private function reactionRows(int $postId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM reactions WHERE post_id = ?', [$postId]);
    }

    public function testReactionFromAnotherUserTogglesReputation(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        $this->assertRedirect($r);
        self::assertSame(1, $this->reactionRows($s['op_id']));
        self::assertSame(1, $this->reputation((int) $s['author']['id']), 'received reaction grants +1');

        // Toggling the same emoji removes it and the reputation.
        $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        self::assertSame(0, $this->reactionRows($s['op_id']));
        self::assertSame(0, $this->reputation((int) $s['author']['id']));
    }

    public function testSelfReactionContributesZeroReputation(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['author']);

        $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '🎉']);
        self::assertSame(1, $this->reactionRows($s['op_id']), 'self-reaction may exist');
        self::assertSame(0, $this->reputation((int) $s['author']['id']), 'but never grants the author reputation');
    }

    public function testToggleIsIdempotentAndNeverDuplicates(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $repo = new ReactionRepository($this->db);

        self::assertSame('added', $repo->toggle($s['op_id'], (int) $fan['id'], '👍'));
        self::assertSame(1, $this->reactionRows($s['op_id']));
        self::assertSame('removed', $repo->toggle($s['op_id'], (int) $fan['id'], '👍'));
        self::assertSame(0, $this->reactionRows($s['op_id']));

        // Distinct emoji accumulate; same emoji never duplicates.
        $repo->toggle($s['op_id'], (int) $fan['id'], '👍');
        $repo->toggle($s['op_id'], (int) $fan['id'], '❤️');
        self::assertSame(2, $this->reactionRows($s['op_id']));
        $counts = $repo->countsForPost($s['op_id']);
        self::assertCount(2, $counts);
        self::assertSame(1, $counts['👍']);
        self::assertSame(1, $counts['❤️']);
    }

    public function testDisallowedEmojiIsRejected(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '💀', 'format' => 'json']);
        $this->assertStatus(422, $r);
        self::assertSame(0, $this->reactionRows($s['op_id']));
    }

    public function testDeletingReactedPostRemovesReputation(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        // Author writes a reply; the fan reacts to it (the reply earns rep).
        $replyId = $this->posting()->reply($this->userEntity($s['author']), $s['thread']['thread_id'], ['body' => 'A reply that gets a reaction.']);
        (new ReactionRepository($this->db))->toggle($replyId, (int) $fan['id'], '🔥');
        (new RepairService($this->db))->repairReputation();
        self::assertSame(1, $this->reputation((int) $s['author']['id']));

        // Author deletes the reacted reply → reputation recomputes downward.
        $this->actingAs($s['author']);
        $this->post('/posts/' . $replyId . '/delete');
        self::assertSame(0, $this->reputation((int) $s['author']['id']));
    }

    public function testSuspendedUserCannotReact(): void
    {
        $s = $this->scenario();
        $suspended = $this->makeUser(['status' => 'suspended']);
        $this->actingAs($suspended);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']);
        $this->assertStatus(403, $r);
        self::assertSame(0, $this->reactionRows($s['op_id']));
    }

    public function testReactionJsonReturnsState(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);

        $r = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '💯', 'format' => 'json']);
        $this->assertStatus(200, $r);
        $data = json_decode($r->body(), true);
        self::assertTrue($data['ok']);
        self::assertSame('added', $data['state']);
        self::assertSame(1, $data['counts']['💯']);
    }

    public function testPrivateBoardMemberCanReactAndStarButOutsiderCannot(): void
    {
        // A private-board member can read/post, so engagement must work for them
        // too — the gate must resolve membership, not fail closed (ENG-1).
        $members = new \App\Repository\BoardMemberRepository($this->db);
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $members->add((int) $board['id'], (int) $author['id'], null); // author must belong to post the OP
        $thread = $this->makeThread($board, $author, 'Members only', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $fan = $this->makeUser();
        $members->add((int) $board['id'], (int) $fan['id'], null);
        $this->actingAs($fan);

        // React (service path) and star (controller path) both succeed for a member.
        $this->assertRedirect($this->post('/posts/' . $opId . '/react', ['emoji' => '👍']));
        self::assertSame(1, $this->reactionRows($opId));
        self::assertSame(1, $this->reputation((int) $author['id']));
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/star'));

        // A non-member is still blocked (404, no existence leak, no rep change).
        $outsider = $this->makeUser();
        $this->actingAs($outsider);
        $blocked = $this->post('/posts/' . $opId . '/react', ['emoji' => '❤️', 'format' => 'json']);
        $this->assertStatus(404, $blocked);
        self::assertSame(1, $this->reactionRows($opId), 'outsider reaction is rejected');
    }

    public function testThreadNamesTheGestureInsteadOfABareEmoji(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser();
        $this->actingAs($fan);
        $this->assertRedirect($this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍']));

        $page = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringContainsString('class="reaction-name">Commend</span>', $html);
        self::assertStringContainsString('icon-commend-star', $html);
        self::assertStringNotContainsString('reaction-bare', $html);
        self::assertStringContainsString('class="reaction-name">Kindled</span>', $html);
        self::assertStringContainsString('class="reaction-name">Seconded</span>', $html);
        self::assertStringContainsString('class="reaction-name">Illuminating</span>', $html);
        self::assertStringContainsString('name="emoji" value="👍"', $html);
    }

    public function testReactionNamesAreNewestCappedAndFilteredWithoutChangingCounts(): void
    {
        $s = $this->scenario();
        $viewer = $this->makeUser();
        $repo = new ReactionRepository($this->db);
        $names = [];
        for ($i = 0; $i < 8; $i++) {
            $fan = $this->makeUser(['username' => 'reactor' . $i]);
            $names[] = $fan['username'];
            $repo->toggle($s['op_id'], (int) $fan['id'], '👍');
        }
        $blocked = $this->makeUser(['username' => 'blockedreactor']);
        $blocks = new \App\Repository\BlockRepository($this->db);
        $blocks->block((int) $blocked['id'], (int) $viewer['id']);
        $repo->toggle($s['op_id'], (int) $blocked['id'], '👍');
        $otherBlocked = $this->makeUser(['username' => 'otherblockedreactor']);
        $blocks->block((int) $viewer['id'], (int) $otherBlocked['id']);
        $repo->toggle($s['op_id'], (int) $otherBlocked['id'], '👍');
        $inactive = $this->makeUser(['username' => 'inactivereactor', 'status' => 'banned']);
        $repo->toggle($s['op_id'], (int) $inactive['id'], '👍');
        $repo->toggle($s['op_id'], (int) $viewer['id'], '👍');

        $this->actingAs($viewer);
        $response = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍', 'format' => 'json']);
        $this->assertStatus(200, $response);
        $data = json_decode($response->body(), true);
        self::assertArrayHasKey('reactors', $data);
        self::assertSame(array_slice(array_reverse($names), 0, 6), $data['reactors']);
        self::assertSame(11, $data['counts']['👍'], 'hidden names remain in aggregate counts');

        $html = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug'])->body();
        self::assertStringContainsString('@reactor7, @reactor6, @reactor5, @reactor4, @reactor3, @reactor2 and 5 others', $html);
        self::assertStringNotContainsString('blockedreactor', $html);
        self::assertStringNotContainsString('inactivereactor', $html);
    }

    public function testPrivateBoardReactionNamesExcludeFormerMembers(): void
    {
        $members = new \App\Repository\BoardMemberRepository($this->db);
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $members->add((int) $board['id'], (int) $author['id'], null);
        $thread = $this->makeThread($board, $author);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);
        $former = $this->makeUser(['username' => 'formerreactor']);
        $admin = $this->makeAdmin(['username' => 'adminreactor']);
        $repo = new ReactionRepository($this->db);
        $repo->toggle($postId, (int) $former['id'], '👍');
        $repo->toggle($postId, (int) $admin['id'], '👍');
        $this->actingAs($author);
        $response = $this->post('/posts/' . $postId . '/react', ['emoji' => '👍', 'format' => 'json']);
        $this->assertStatus(200, $response);
        $data = json_decode($response->body(), true);
        self::assertArrayHasKey('reactors', $data);
        self::assertSame(['adminreactor'], $data['reactors']);
        self::assertSame(3, $data['counts']['👍']);
        $html = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug'])->body();
        self::assertStringContainsString('You, @adminreactor and 1 other', $html);
        self::assertStringNotContainsString('formerreactor', $html);
    }

    public function testReactionNamesFollowTheEffectiveSuspensionState(): void
    {
        $s = $this->scenario();
        $repo = new ReactionRepository($this->db);
        // Mirrors User::isActive(): an elapsed timed suspension names its member
        // again, while current and indefinite suspensions only count.
        $restored = $this->makeUser(['username' => 'restoredreactor', 'status' => 'suspended',
            'suspended_until' => gmdate('Y-m-d H:i:s', time() - 3600)]);
        $current = $this->makeUser(['username' => 'currentreactor', 'status' => 'suspended',
            'suspended_until' => gmdate('Y-m-d H:i:s', time() + 3600)]);
        $indefinite = $this->makeUser(['username' => 'indefinitereactor', 'status' => 'suspended']);
        foreach ([$indefinite, $current, $restored] as $fan) {
            $repo->toggle($s['op_id'], (int) $fan['id'], '👍');
        }

        $this->actingAs($s['author']);
        $response = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍', 'format' => 'json']);
        $this->assertStatus(200, $response);
        $data = json_decode($response->body(), true);
        self::assertSame(['restoredreactor'], $data['reactors']);
        self::assertSame(4, $data['counts']['👍']);
        $html = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug'])->body();
        self::assertStringContainsString('You, @restoredreactor and 2 others', $html);
        self::assertStringNotContainsString('currentreactor', $html);
        self::assertStringNotContainsString('indefinitereactor', $html);
    }

    public function testThreadRendersOneEmptyReactionAnnouncerOnlyWithEngagement(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['author']);
        $path = '/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug'];
        // Enhanced failures write into this region; it must already exist and
        // be empty, because a region inserted with its message may stay silent.
        $html = $this->get($path)->body();
        self::assertSame(1, substr_count($html, 'data-reaction-announcer'));
        self::assertStringContainsString('<p class="sr-only" role="status" data-reaction-announcer></p>', $html);

        (new \App\Repository\SettingRepository($this->db))->set('features', ['engagement' => false]);
        self::assertStringNotContainsString('data-reaction-announcer', $this->get($path)->body());
    }

    public function testReactionMarkupHasAccessibleMemberTipAndNoGuestNames(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser(['username' => 'namedreactor']);
        $this->db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [$fan['id']]);
        (new ReactionRepository($this->db))->toggle($s['op_id'], (int) $fan['id'], '👍');
        $guestHtml = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug'])->body();
        self::assertStringNotContainsString('namedreactor', $guestHtml);
        self::assertStringNotContainsString('reaction-tip', $guestHtml);
        self::assertStringContainsString('<span class="reaction-n"><span class="reaction-n-val">1</span></span>', $guestHtml);
        $this->actingAs($s['author']);
        $html = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug'])->body();
        self::assertStringContainsString('data-label="Commend"', $html);
        self::assertStringContainsString('aria-describedby="reaction-tip-' . $s['op_id'] . '-0"', $html);
        self::assertStringContainsString('class="reaction-tip" aria-hidden="true"', $html);
        self::assertStringContainsString('<span class="reaction-tip-label">Commend</span><span>@namedreactor</span>', $html);
        self::assertStringNotContainsString('title="Commend"', $html);
        self::assertStringContainsString('aria-pressed="false" data-label="Commend"', $html);
    }

    public function testReactionNamesAreBatchedAcrossPostsAndEmoji(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser(['username' => 'batchreactor']);
        $replyId = $this->posting()->reply($this->userEntity($s['author']), $s['thread']['thread_id'], ['body' => 'Reply']);
        $repo = new ReactionRepository($this->db);
        $repo->toggle($s['op_id'], (int) $fan['id'], '👍');
        $repo->toggle($s['op_id'], (int) $fan['id'], '🔥');
        $repo->toggle($replyId, (int) $fan['id'], '👍');
        $queryCount = $this->db->metrics()['queries'];
        self::assertEquals([
            $s['op_id'] => ['👍' => ['batchreactor'], '🔥' => ['batchreactor']],
            $replyId => ['👍' => ['batchreactor']],
        ], $repo->reactorsForPosts((int) $s['author']['id'], [$s['op_id'], $replyId]));
        self::assertSame(1, $this->db->metrics()['queries'] - $queryCount);
    }

    public function testAnonymousSelfReactionNeverNamesTheAuthorInHtmlOrJson(): void
    {
        $author = $this->makeUser(['username' => 'maskedreactor']);
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => $board['id'], 'title' => 'Anonymous topic', 'body' => 'Anonymous opening.', 'is_anonymous' => true,
        ]);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);
        $repo = new ReactionRepository($this->db);
        $repo->toggle($postId, (int) $author['id'], '👍');
        $fan = $this->makeUser(['username' => 'publicreactor']);
        $repo->toggle($postId, (int) $fan['id'], '👍');
        $viewer = $this->makeUser();
        $this->actingAs($viewer);

        $response = $this->post('/posts/' . $postId . '/react', ['emoji' => '👍', 'format' => 'json']);
        $this->assertStatus(200, $response);
        $data = json_decode($response->body(), true);
        self::assertSame(['publicreactor'], $data['reactors']);
        self::assertSame(3, $data['counts']['👍'], 'masked self-reactions still contribute to aggregate counts');
        $html = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug'])->body();
        self::assertStringNotContainsString('maskedreactor', $html);
        self::assertStringContainsString('You, @publicreactor and 1 other', $html);

        $this->actingAs($author);
        $html = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug'])->body();
        self::assertStringContainsString('<span>You, @' . $viewer['username'] . ' and @publicreactor</span>', $html, 'the author sees only the viewer-relative You label');
        $response = $this->post('/posts/' . $postId . '/react', ['emoji' => '👍', 'format' => 'json']);
        self::assertNotContains('maskedreactor', json_decode($response->body(), true)['reactors']);
    }

    public function testReactionCannotRevealNamesOnDeletedOrHeldTargets(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser(['username' => 'protectedreactor']);
        (new ReactionRepository($this->db))->toggle($s['op_id'], (int) $fan['id'], '👍');
        $this->actingAs($this->makeUser());
        foreach (['deleted_thread', 'held_thread', 'held_post'] as $state) {
            $this->db->run('UPDATE threads SET is_deleted = ?, is_pending = ? WHERE id = ?', [
                $state === 'deleted_thread' ? 1 : 0, $state === 'held_thread' ? 1 : 0, $s['thread']['thread_id'],
            ]);
            $this->db->run('UPDATE posts SET is_pending = ? WHERE id = ?', [$state === 'held_post' ? 1 : 0, $s['op_id']]);
            $response = $this->post('/posts/' . $s['op_id'] . '/react', ['emoji' => '👍', 'format' => 'json']);
            $this->assertStatus(404, $response);
            self::assertStringNotContainsString('protectedreactor', $response->body());
        }
    }

    public function testOnlyUnknownReactorsKeepTheNativeTitle(): void
    {
        $s = $this->scenario();
        $fan = $this->makeUser(['username' => 'hiddenreactor', 'status' => 'suspended']);
        (new ReactionRepository($this->db))->toggle($s['op_id'], (int) $fan['id'], '👍');
        $this->actingAs($s['author']);
        $response = $this->get('/t/' . $s['thread']['thread_id'] . '-' . $s['thread']['slug']);
        $this->assertStatus(200, $response);
        self::assertStringContainsString('title="Commend"', $response->body());
        self::assertStringNotContainsString('class="reaction-tip"', $response->body());
        self::assertStringNotContainsString('hiddenreactor', $response->body());
        self::assertStringContainsString('<span class="reaction-n-val">1</span>', $response->body());
    }
}
