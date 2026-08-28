<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserBoardPrefRepository;
use Tests\Support\TestCase;

/**
 * Per-user thread state (P2-01): read position, stars, unread derivation, and
 * the personal Inbox filters. Covers read-isolation, star/filter, and the
 * inaccessible-content exclusion acceptance scenarios.
 */
final class AppThreadStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // initialise the app so the setup gate doesn't intercept HTTP routes
    }

    private function tu(): ThreadUserRepository
    {
        return new ThreadUserRepository($this->db);
    }

    private function opId(int $threadId): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);
    }

    public function testOpeningAThreadAdvancesOnlyTheViewersReadPosition(): void
    {
        $author = $this->makeUser();
        $reader = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Read me', 'Opening.');
        $tid = $thread['thread_id'];

        $this->actingAs($reader);
        $this->get('/t/' . $tid . '-' . $thread['slug']);

        $readerRow = $this->tu()->find((int) $reader['id'], $tid);
        self::assertNotNull($readerRow, 'reader gets a thread_user row on view');
        self::assertSame($this->opId($tid), (int) $readerRow['last_read_post_id']);
        // The author never opened the thread → no read row for them.
        self::assertNull($this->tu()->find((int) $author['id'], $tid), 'another user state is untouched');
    }

    public function testUnreadFlagTracksNewActivityAgainstReadPosition(): void
    {
        $author = $this->makeUser();
        $reader = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Unread', 'Opening.');
        $tid = $thread['thread_id'];
        $cutover = ThreadUserRepository::NO_CUTOVER;

        // Reader opens it → read.
        $this->actingAs($reader);
        $this->get('/t/' . $tid . '-' . $thread['slug']);
        self::assertFalse($this->tu()->unreadFlags((int) $reader['id'], [$tid], $cutover)[$tid]);

        // A new reply arrives → unread again for the reader.
        $this->posting()->reply($this->userEntity($author), $tid, ['body' => 'A new reply.']);
        self::assertTrue($this->tu()->unreadFlags((int) $reader['id'], [$tid], $cutover)[$tid], 'new post re-marks unread');
    }

    public function testStarTogglesIdempotentlyAndIsPerUser(): void
    {
        $author = $this->makeUser();
        $a = $this->makeUser();
        $b = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Star me', 'Opening.');
        $tid = $thread['thread_id'];

        $this->actingAs($a);
        $this->post('/t/' . $tid . '/star');
        self::assertTrue($this->tu()->isStarred((int) $a['id'], $tid));
        self::assertFalse($this->tu()->isStarred((int) $b['id'], $tid), 'star is per-user');

        // Toggling again removes it (idempotent end-state).
        $this->post('/t/' . $tid . '/star');
        self::assertFalse($this->tu()->isStarred((int) $a['id'], $tid));
    }

    public function testStarredInboxFilterShowsOnlyStarredThreads(): void
    {
        $author = $this->makeUser();
        $me = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $t1 = $this->makeThread($board, $author, 'Starred one', 'x');
        $t2 = $this->makeThread($board, $author, 'Not starred', 'y');

        $this->tu()->setStar((int) $me['id'], $t1['thread_id'], true);

        $rows = $this->tu()->inbox((int) $me['id'], 'starred', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
        $titles = array_column($rows, 'title');
        self::assertContains('Starred one', $titles);
        self::assertNotContains('Not starred', $titles);
    }

    public function testInboxNeverSurfacesInaccessiblePrivateBoardThreads(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $publicBoard = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $privateBoard = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $this->makeThread($publicBoard, $admin, 'Public topic', 'x');
        $this->makeThread($privateBoard, $admin, 'Secret topic', 'y');

        // Non-admin "active" inbox excludes the private board.
        $rows = $this->tu()->inbox((int) $member['id'], 'unread', 'active', false, '2000-01-01 00:00:00', 50, 0);
        $titles = array_column($rows, 'title');
        self::assertContains('Public topic', $titles);
        self::assertNotContains('Secret topic', $titles, 'private board thread is never listed for a non-member');

        // Admin sees both.
        $adminRows = $this->tu()->inbox((int) $admin['id'], 'unread', 'active', true, '2000-01-01 00:00:00', 50, 0);
        self::assertContains('Secret topic', array_column($adminRows, 'title'));
    }

    public function testUnreadCountRespectsCutoverForUntrackedThreads(): void
    {
        $author = $this->makeUser();
        $me = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Pre-launch', 'x');

        // With the far-future default cutover, an untracked thread is NOT unread.
        self::assertSame(0, $this->tu()->unreadCount((int) $me['id'], false, ThreadUserRepository::NO_CUTOVER));

        // After stamping the cutover in the past, post-cutover activity is unread.
        (new SettingRepository($this->db))->set('engagement_cutover_at', '2000-01-01 00:00:00');
        $cutover = '2000-01-01 00:00:00';
        self::assertSame(1, $this->tu()->unreadCount((int) $me['id'], false, $cutover));
    }

    public function testUnreadCountAndQueueBothExcludeActiveSnoozesAndMutedBoards(): void
    {
        $author = $this->makeUser();
        $me = $this->makeUser();
        $visibleBoard = $this->makeBoard($this->makeCategory());
        $mutedBoard = $this->makeBoard($this->makeCategory());
        $snoozed = $this->makeThread($visibleBoard, $author, 'Snoozed unread', 'x');
        $muted = $this->makeThread($mutedBoard, $author, 'Muted unread', 'y');
        $cutover = '2000-01-01 00:00:00';

        $this->tu()->setSnooze((int) $me['id'], (int) $snoozed['thread_id'], '2999-01-01 00:00:00');
        (new UserBoardPrefRepository($this->db))->setMuted((int) $me['id'], (int) $mutedBoard['id'], true);

        $rows = $this->tu()->inbox((int) $me['id'], 'unread', 'active', false, $cutover, 20, 0);
        self::assertSame([], array_column($rows, 'title'));
        self::assertSame(0, $this->tu()->unreadCount((int) $me['id'], false, $cutover));

        // A muted row remains available in non-Unread Inbox views, but its raw
        // unread decoration must not claim a place in the queue/badge.
        $this->tu()->setStar((int) $me['id'], (int) $muted['thread_id'], true);
        $active = $this->tu()->inbox((int) $me['id'], 'starred', 'active', false, $cutover, 20, 0);
        $mutedRow = array_values(array_filter(
            $active,
            static fn (array $row): bool => $row['title'] === 'Muted unread',
        ));
        self::assertCount(1, $mutedRow);
        self::assertSame(1, (int) $mutedRow[0]['is_unread']);
        self::assertSame(0, (int) $mutedRow[0]['is_inbox_unread']);
    }

    public function testMentionFiltersUseCanonicalDeliveredMentionSignals(): void
    {
        $me = $this->makeUser(['username' => 'signal_member']);
        $actor = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $falseMention = $this->makeThread($board, $actor, 'Prefix is not a mention', 'Opening.');
        $canonicalMention = $this->makeThread($board, $actor, 'Canonical delivered mention', 'Opening.');

        $this->posting()->reply($this->userEntity($actor), (int) $falseMention['thread_id'], [
            'body' => '@signal_membership is a different handle.',
        ]);
        $postId = $this->posting()->reply($this->userEntity($actor), (int) $canonicalMention['thread_id'], [
            'body' => 'The delivery record, rather than the body text, is canonical.',
        ]);
        (new NotificationRepository($this->db))->create([
            'user_id' => (int) $me['id'],
            'type' => 'mention',
            'actor_id' => (int) $actor['id'],
            'thread_id' => (int) $canonicalMention['thread_id'],
            'post_id' => $postId,
        ]);

        $mentions = $this->tu()->inbox((int) $me['id'], 'mentions', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
        self::assertContains('Canonical delivered mention', array_column($mentions, 'title'));
        self::assertNotContains('Prefix is not a mention', array_column($mentions, 'title'));

        $forYou = $this->tu()->inbox((int) $me['id'], 'for_you', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
        $rows = array_values(array_filter($forYou, static fn (array $row): bool => $row['title'] === 'Canonical delivered mention'));
        self::assertCount(1, $rows);
        self::assertSame('Mentioned you', $rows[0]['for_you_reason']);
    }

    public function testReplyFiltersUseCanonicalDeliveredReplySignals(): void
    {
        $me = $this->makeUser();
        $actor = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $me, 'Reply delivery is canonical', 'Opening.');
        $threadId = (int) $thread['thread_id'];

        $this->posting()->reply($this->userEntity($me), $threadId, ['body' => 'My own clarification.']);
        self::assertNotContains(
            'Reply delivery is canonical',
            array_column($this->tu()->inbox((int) $me['id'], 'replies', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0), 'title'),
            'A member must not receive a reply cue for their own post.',
        );

        $postId = $this->posting()->reply($this->userEntity($actor), $threadId, ['body' => 'A delivered reply.']);
        (new NotificationRepository($this->db))->create([
            'user_id' => (int) $me['id'],
            'type' => 'reply',
            'actor_id' => (int) $actor['id'],
            'thread_id' => $threadId,
            'post_id' => $postId,
        ]);

        $replies = $this->tu()->inbox((int) $me['id'], 'replies', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
        self::assertContains('Reply delivery is canonical', array_column($replies, 'title'));
        $forYou = $this->tu()->inbox((int) $me['id'], 'for_you', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
        $rows = array_values(array_filter($forYou, static fn (array $row): bool => $row['title'] === 'Reply delivery is canonical'));
        self::assertCount(1, $rows);
        self::assertSame('Replies to your topic', $rows[0]['for_you_reason']);
    }

    public function testWatchingAndForYouHonorThreadSubscriptionOffOverBoardWatch(): void
    {
        $me = $this->makeUser();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Thread override is off', 'Opening.');
        $threadId = (int) $thread['thread_id'];
        $subs = new SubscriptionRepository($this->db);

        $subs->set((int) $me['id'], 'board', (int) $board['id'], true, true, 'instant');
        $subs->set((int) $me['id'], 'thread', $threadId, false, false, 'off');

        self::assertNotContains(
            'Thread override is off',
            array_column($this->tu()->inbox((int) $me['id'], 'watching', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0), 'title'),
        );
        self::assertNotContains(
            'Thread override is off',
            array_column($this->tu()->inbox((int) $me['id'], 'for_you', 'active', false, ThreadUserRepository::NO_CUTOVER, 20, 0), 'title'),
        );
    }

    public function testInboxRouteRequiresLogin(): void
    {
        $r = $this->get('/inbox');
        $this->assertRedirectContains($r, '/login');
    }

    public function testThreadPageRendersEngagementControls(): void
    {
        $author = $this->makeUser();
        $reader = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Render check', 'Opening.');

        $this->actingAs($reader);
        $r = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Star');                 // star control
        $this->assertSeeText($r, '/react');               // reaction form action
    }

    public function testInboxPageRendersForMember(): void
    {
        $me = $this->makeUser();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Some topic', 'x');
        $topicId = (int) $this->db->fetchValue('SELECT id FROM threads WHERE board_id = ? ORDER BY id DESC LIMIT 1', [(int) $board['id']]);
        $this->tu()->setStar((int) $me['id'], $topicId, true);

        $this->actingAs($me);
        $r = $this->get('/inbox', ['scope' => 'starred', 'order' => 'active']);
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Inbox');
        $this->assertSeeText($r, 'Unread');               // filter tab
        $this->assertSeeText($r, 'Some topic');
        self::assertStringContainsString('data-inbox-reading', $r->body());
        self::assertStringContainsString('data-inbox-back', $r->body());
        self::assertStringContainsString('data-inbox-reading-content', $r->body());
        self::assertStringContainsString('class="topbar-action topbar-search-entry" href="/search"', $r->body());
    }
}
