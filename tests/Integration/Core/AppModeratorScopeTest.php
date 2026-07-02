<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * Board-scoped moderation (P2-08): an assigned board moderator can act within
 * their board and nowhere else; an admin acts everywhere. Covers pin, delete,
 * thread move (counters + audit), and post restore.
 */
final class AppModeratorScopeTest extends TestCase
{
    /** @var array<string,mixed> */ private array $admin;
    /** @var array<string,mixed> */ private array $modA;
    /** @var array<string,mixed> */ private array $member;
    /** @var array<string,mixed> */ private array $boardA;
    /** @var array<string,mixed> */ private array $boardB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
        $this->modA = $this->makeUser(['username' => 'moda']);
        $this->member = $this->makeUser(['username' => 'memberx']);
        $this->boardA = $this->makeBoard($this->makeCategory(), ['slug' => 'aaa', 'name' => 'Aaa']);
        $this->boardB = $this->makeBoard($this->makeCategory(), ['slug' => 'bbb', 'name' => 'Bbb']);
        (new BoardModeratorRepository($this->db))->assign((int) $this->boardA['id'], (int) $this->modA['id']);
    }

    public function testBoardModeratorCanDiscoverModerationQueueFromChromeAndAdminDeadEnd(): void
    {
        $thread = $this->makeThread($this->boardA, $this->member, 'Reported in scope', 'needs review');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $reporter = $this->makeUser(['username' => 'queue_reporter']);
        $this->db->run(
            "INSERT INTO reports (reporter_id, post_id, reason_code, reason, status, notify_reporter, created_at)
             VALUES (?, ?, 'spam', 'review this', 'open', 0, UTC_TIMESTAMP())",
            [(int) $reporter['id'], $postId],
        );

        $this->actingAs($this->modA);

        $home = $this->get('/');
        $this->assertStatus(200, $home);
        $this->assertSeeText($home, 'href="/mod/reports"');
        $this->assertSeeText($home, 'Moderation');
        $this->assertSeeText($home, '<span class="mod-count">1</span>');

        $admin = $this->get('/admin');
        $this->assertStatus(403, $admin);
        $this->assertSeeText($admin, 'href="/mod/reports"');
        $this->assertSeeText($admin, 'Moderation queue');
    }

    public function testBoardModeratorCanActOnlyInTheirBoard(): void
    {
        $tA = $this->makeThread($this->boardA, $this->member, 'In A', 'x');
        $tB = $this->makeThread($this->boardB, $this->member, 'In B', 'y');

        $this->actingAs($this->modA);
        $this->get('/t/' . $tA['thread_id'] . '-' . $tA['slug']);
        $this->assertRedirect($this->post('/mod/t/' . $tA['thread_id'] . '/pin'));
        self::assertSame(1, (int) $this->threads()->find($tA['thread_id'])['is_pinned'], 'mod pins in own board');

        // Out of scope: board B.
        $this->assertStatus(403, $this->post('/mod/t/' . $tB['thread_id'] . '/pin'));
        self::assertSame(0, (int) $this->threads()->find($tB['thread_id'])['is_pinned']);
    }

    public function testBoardModeratorCanDeleteInScopeAndAdminAnywhere(): void
    {
        $tB = $this->makeThread($this->boardB, $this->member, 'In B', 'y');
        $reply = $this->posting()->reply($this->userEntity($this->member), $tB['thread_id'], ['body' => 'spam']);

        // modA does not moderate B → 403.
        $this->actingAs($this->modA);
        $this->assertStatus(403, $this->post('/posts/' . $reply . '/delete', ['reason' => 'spam']));
        self::assertSame(0, (int) $this->posts()->find($reply)['is_deleted']);

        // admin can.
        $this->actingAs($this->admin);
        $this->post('/posts/' . $reply . '/delete', ['reason' => 'spam']);
        self::assertSame(1, (int) $this->posts()->find($reply)['is_deleted']);
    }

    public function testAdminMoveUpdatesCountersAtomicallyAndAudits(): void
    {
        $t = $this->makeThread($this->boardA, $this->member, 'Movable', 'x'); // 1 OP post
        $this->posting()->reply($this->userEntity($this->member), $t['thread_id'], ['body' => 'reply']); // 2 posts

        $this->actingAs($this->admin);
        $this->post('/mod/t/' . $t['thread_id'] . '/move', ['board_id' => (int) $this->boardB['id']]);

        self::assertSame((int) $this->boardB['id'], (int) $this->threads()->find($t['thread_id'])['board_id']);
        self::assertSame(0, (int) $this->boards()->find((int) $this->boardA['id'])['thread_count']);
        self::assertSame(0, (int) $this->boards()->find((int) $this->boardA['id'])['post_count']);
        self::assertSame(1, (int) $this->boards()->find((int) $this->boardB['id'])['thread_count']);
        self::assertSame(2, (int) $this->boards()->find((int) $this->boardB['id'])['post_count']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'move_thread'"));
    }

    public function testModeratorCannotMoveIntoABoardTheyDoNotModerate(): void
    {
        $t = $this->makeThread($this->boardA, $this->member, 'Movable', 'x');
        $this->actingAs($this->modA); // moderates A only
        $this->assertStatus(403, $this->post('/mod/t/' . $t['thread_id'] . '/move', ['board_id' => (int) $this->boardB['id']]));
        self::assertSame((int) $this->boardA['id'], (int) $this->threads()->find($t['thread_id'])['board_id'], 'move blocked, thread stays put');
    }

    public function testRestoreReinstatesPostAndCounters(): void
    {
        $t = $this->makeThread($this->boardA, $this->member, 'Restorable', 'x');
        $reply = $this->posting()->reply($this->userEntity($this->member), $t['thread_id'], ['body' => 'reply']);

        $this->actingAs($this->modA);
        $this->post('/posts/' . $reply . '/delete', ['reason' => 'mistake']);
        self::assertSame(1, (int) $this->posts()->find($reply)['is_deleted']);

        $this->post('/mod/p/' . $reply . '/restore', ['reason' => 'on reflection']);
        self::assertSame(0, (int) $this->posts()->find($reply)['is_deleted'], 'post restored');
        self::assertSame(1, (int) $this->threads()->find($t['thread_id'])['reply_count'], 'reply_count restored');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'restore_post'"));
    }
}
