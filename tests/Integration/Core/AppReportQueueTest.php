<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * Post reports + scoped queue (P2-08): submit/dedupe, staff alerts, board-scoped
 * visibility, and claim/resolve with reporter outcome-notification.
 */
final class AppReportQueueTest extends TestCase
{
    /** @var array<string,mixed> */ private array $admin;
    /** @var array<string,mixed> */ private array $modA;
    /** @var array<string,mixed> */ private array $reporter;
    /** @var array<string,mixed> */ private array $board;
    private int $replyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
        $this->modA = $this->makeUser(['username' => 'moda']);
        $this->reporter = $this->makeUser(['username' => 'reporter']);
        $author = $this->makeUser(['username' => 'author']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'gen']);
        (new BoardModeratorRepository($this->db))->assign((int) $this->board['id'], (int) $this->modA['id']);
        $thread = $this->makeThread($this->board, $author, 'Topic', 'OP');
        $this->replyId = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'a reportable reply']);
    }

    private function modNotifs(int $userId): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mod'", [$userId]);
    }

    public function testSubmitDedupesAndAlertsStaff(): void
    {
        $this->actingAs($this->reporter);
        $this->get('/t/' . $this->db->fetchValue('SELECT thread_id FROM posts WHERE id = ?', [$this->replyId]) . '-x');
        $this->post('/posts/' . $this->replyId . '/report', ['reason_code' => 'spam', 'reason' => 'junk', 'notify_reporter' => '1']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reports WHERE post_id = ?', [$this->replyId]));

        // Duplicate open report is deduped.
        $this->post('/posts/' . $this->replyId . '/report', ['reason_code' => 'spam', 'reason' => 'again']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reports WHERE post_id = ?', [$this->replyId]));

        // Board moderator + admin alerted; reporter is not self-alerted.
        self::assertGreaterThanOrEqual(1, $this->modNotifs((int) $this->modA['id']));
        self::assertGreaterThanOrEqual(1, $this->modNotifs((int) $this->admin['id']));
        self::assertSame(0, $this->modNotifs((int) $this->reporter['id']));
    }

    public function testQueueExactMultipleOfPageSizeHasNoNextLink(): void
    {
        // PR #44 spec §4: has_next came from count(rows) === PER_PAGE — a dead
        // Next link when the scoped queue is an exact page multiple. Fifty
        // distinct posts, one reporter, filtered to this board.
        $author = $this->makeUser(['username' => 'bulkauthor']);
        $thread = $this->makeThread($this->board, $author, 'Busy topic', 'OP body.');
        $postIds = [];
        for ($i = 0; $i < 50; $i++) {
            $postIds[] = (int) $this->db->insert(
                'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, UTC_TIMESTAMP())',
                [(int) $thread['thread_id'], (int) $author['id'], 'Reportable ' . $i, '<p>Reportable</p>'],
            );
        }
        $this->actingAs($this->reporter);
        foreach ($postIds as $postId) {
            $this->post('/posts/' . $postId . '/report', ['reason_code' => 'spam']);
        }

        $this->actingAs($this->modA);
        $first = $this->get('/mod/reports', ['board_id' => (string) (int) $this->board['id']]);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, '50');
        $this->assertDontSeeText($first, 'Next');

        $second = $this->get('/mod/reports', ['board_id' => (string) (int) $this->board['id'], 'page' => '1']);
        $this->assertStatus(200, $second);
    }

    public function testQueueIsBoardScoped(): void
    {
        $this->actingAs($this->reporter);
        $this->post('/posts/' . $this->replyId . '/report', ['reason_code' => 'spam']);

        // Board moderator sees it.
        $this->actingAs($this->modA);
        $r = $this->get('/mod/reports');
        $this->assertStatus(200, $r);
        $this->assertSeeText($r, 'Topic');

        // A user who moderates nothing gets 404.
        $this->actingAs($this->makeUser());
        $this->assertStatus(404, $this->get('/mod/reports'));

        // Admin sees all.
        $this->actingAs($this->admin);
        $this->assertStatus(200, $this->get('/mod/reports'));
    }

    public function testResolveNotifiesOptedInReporter(): void
    {
        $this->actingAs($this->reporter);
        $this->post('/posts/' . $this->replyId . '/report', ['reason_code' => 'spam', 'notify_reporter' => '1']);
        $reportId = (int) $this->db->fetchValue('SELECT id FROM reports WHERE post_id = ?', [$this->replyId]);
        self::assertSame(0, $this->modNotifs((int) $this->reporter['id']), 'no outcome notice yet');

        $this->actingAs($this->modA);
        $this->post('/mod/reports/' . $reportId . '/claim');
        self::assertSame('triaged', (string) $this->db->fetchValue('SELECT status FROM reports WHERE id = ?', [$reportId]));

        $this->post('/mod/reports/' . $reportId . '/resolve');
        self::assertSame('resolved', (string) $this->db->fetchValue('SELECT status FROM reports WHERE id = ?', [$reportId]));
        self::assertSame(1, $this->modNotifs((int) $this->reporter['id']), 'opted-in reporter notified of outcome');
    }

    public function testOutOfScopeModeratorCannotResolve(): void
    {
        $this->actingAs($this->reporter);
        $this->post('/posts/' . $this->replyId . '/report', ['reason_code' => 'spam']);
        $reportId = (int) $this->db->fetchValue('SELECT id FROM reports WHERE post_id = ?', [$this->replyId]);

        // A moderator of a different board cannot resolve this report.
        $otherBoard = $this->makeBoard($this->makeCategory(), ['slug' => 'other']);
        $modB = $this->makeUser(['username' => 'modb']);
        (new BoardModeratorRepository($this->db))->assign((int) $otherBoard['id'], (int) $modB['id']);

        $this->actingAs($modB);
        $this->assertStatus(403, $this->post('/mod/reports/' . $reportId . '/resolve'));
        self::assertSame('open', (string) $this->db->fetchValue('SELECT status FROM reports WHERE id = ?', [$reportId]));
    }
}
