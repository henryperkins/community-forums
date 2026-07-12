<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
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

        $rows = $this->tu()->inbox((int) $me['id'], 'starred', false, ThreadUserRepository::NO_CUTOVER, 20, 0);
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
        $rows = $this->tu()->inbox((int) $member['id'], 'active', false, ThreadUserRepository::NO_CUTOVER, 50, 0);
        $titles = array_column($rows, 'title');
        self::assertContains('Public topic', $titles);
        self::assertNotContains('Secret topic', $titles, 'private board thread is never listed for a non-member');

        // Admin sees both.
        $adminRows = $this->tu()->inbox((int) $admin['id'], 'active', true, ThreadUserRepository::NO_CUTOVER, 50, 0);
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

        $this->actingAs($me);
        $r = $this->get('/inbox', ['filter' => 'active']);
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Inbox');
        $this->assertSeeText($r, 'Unread');               // filter tab
        $this->assertSeeText($r, 'Some topic');
        self::assertStringContainsString('data-inbox-reading', $r->body());
        self::assertStringContainsString('data-inbox-back', $r->body());
        self::assertStringContainsString('data-inbox-reading-content', $r->body());
        self::assertStringContainsString('class="rail-filter mobile-only mobile-search-link" href="/search"', $r->body());
    }
}
