<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppModerationAppealsTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $member;
    /** @var array{thread_id:int,slug:string} */
    private array $thread;
    private int $replyId;

    protected function setUp(): void
    {
        parent::setUp();
        // Appeals graduated to default-on (GA 2026-07-02, ADR 0007); pin the flag
        // on explicitly so this suite is isolated from any operator override.
        (new SettingRepository($this->db))->set('features', ['appeals' => true]);
        $this->admin = $this->makeAdmin(['username' => 'appeal-admin']);
        $this->member = $this->makeUser(['username' => 'appeal-member']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'appeals']);
        $this->thread = $this->makeThread($board, $this->member, 'Appealable topic', 'Opening post');
        $this->replyId = $this->posting()->reply($this->userEntity($this->member), $this->thread['thread_id'], ['body' => 'Reply to remove']);
        $this->actingAs($this->admin);
        $this->get('/t/' . $this->thread['thread_id'] . '-' . $this->thread['slug']);
        $this->post('/posts/' . $this->replyId . '/delete', ['reason' => 'cleanup']);
    }

    public function test_member_opens_one_active_appeal_for_deleted_post(): void
    {
        $this->actingAs($this->member);
        $this->get('/appeals');

        $response = $this->post('/appeals/posts/' . $this->replyId, ['reason' => 'This should be restored.']);
        $this->assertRedirect($response, '/appeals');

        $appeal = $this->db->fetch('SELECT * FROM moderation_appeals WHERE target_type = ? AND target_id = ?', ['post', $this->replyId]);
        self::assertNotNull($appeal);
        self::assertSame('open', (string) $appeal['status']);
        self::assertSame((int) $this->member['id'], (int) $appeal['appellant_id']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_appeal_events WHERE appeal_id = ? AND event = 'opened'", [(int) $appeal['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'appeal_opened' AND target_type = 'post' AND target_id = ?", [$this->replyId]));

        $duplicate = $this->post('/appeals/posts/' . $this->replyId, ['reason' => 'Second try.']);
        $this->assertStatus(422, $duplicate);
        self::assertStringContainsString('Second try.', $duplicate->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_appeals WHERE target_type = ? AND target_id = ?', ['post', $this->replyId]));
    }

    public function test_appeals_page_renders_no_js_form_for_deleted_post(): void
    {
        $this->actingAs($this->member);

        $page = $this->get('/appeals');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('/appeals/posts/' . $this->replyId, $page->body());
        self::assertStringContainsString('name="reason"', $page->body());
        self::assertStringContainsString('Reply to remove', $page->body());
        self::assertStringContainsString('Submit appeal', $page->body());
    }

    public function test_appeals_page_renders_no_js_form_for_user_moderation_action(): void
    {
        $this->actingAs($this->admin);
        $this->post('/mod/u/' . $this->member['id'] . '/warn', ['reason' => 'Mind the local rules.']);
        $logId = (int) $this->db->fetchValue(
            "SELECT id FROM moderation_log WHERE action = 'warn' AND target_type = 'user' AND target_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $this->member['id']],
        );

        $this->actingAs($this->member);
        $page = $this->get('/appeals');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('/appeals/modlog/' . $logId, $page->body());
        self::assertStringContainsString('warn', $page->body());
        self::assertStringContainsString('Mind the local rules.', $page->body());
        self::assertStringContainsString('Submit appeal', $page->body());
    }

    public function test_appeals_page_renders_no_js_form_for_cleared_avatar_action(): void
    {
        $this->db->run(
            'UPDATE users SET avatar_path = ?, avatar_source = ? WHERE id = ?',
            ['/media/321', 'upload', (int) $this->member['id']],
        );

        $this->actingAs($this->admin);
        $this->post('/admin/users/' . (int) $this->member['id'] . '/avatar/remove');
        $logId = (int) $this->db->fetchValue(
            "SELECT id FROM moderation_log WHERE action = 'clear_avatar' AND target_type = 'user' AND target_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $this->member['id']],
        );

        $this->actingAs($this->member);
        $page = $this->get('/appeals');

        $this->assertStatus(200, $page);
        self::assertStringContainsString('/appeals/modlog/' . $logId, $page->body());
        self::assertStringContainsString('clear_avatar', $page->body());
        self::assertStringContainsString('Submit appeal', $page->body());
    }

    public function test_deleted_post_appeal_expires_after_30_days(): void
    {
        $this->db->run(
            "UPDATE posts SET deleted_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY) WHERE id = ?",
            [$this->replyId],
        );
        $this->actingAs($this->member);
        $this->get('/appeals');

        $response = $this->post('/appeals/posts/' . $this->replyId, ['reason' => 'Too late.']);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'within 30 days');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_appeals'));
    }

    public function test_invalid_array_reason_rerenders_without_string_cast_warnings(): void
    {
        $this->actingAs($this->member);
        $this->get('/appeals');

        $response = $this->post('/appeals/posts/' . $this->replyId, ['reason' => ['crafted']]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Explain why you are appealing this moderation action.');
        self::assertStringContainsString('name="reason"', $response->body());
    }

    public function test_admin_reverses_deleted_post_appeal_and_notifies_appellant(): void
    {
        $this->actingAs($this->member);
        $this->get('/appeals');
        $this->post('/appeals/posts/' . $this->replyId, ['reason' => 'Please restore.']);
        $appealId = (int) $this->db->fetchValue('SELECT id FROM moderation_appeals');

        $this->actingAs($this->admin);
        $queue = $this->get('/mod/appeals');
        $this->assertStatus(200, $queue);
        $this->assertSeeText($queue, 'Please restore.');
        $response = $this->post('/mod/appeals/' . $appealId . '/resolve', ['outcome' => 'reversed', 'note' => 'Restored.']);

        $this->assertRedirect($response, '/mod/appeals');
        self::assertSame('reversed', (string) $this->db->fetchValue('SELECT status FROM moderation_appeals WHERE id = ?', [$appealId]));
        self::assertSame(0, (int) $this->posts()->find($this->replyId)['is_deleted']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_appeal_events WHERE appeal_id = ? AND event = 'reversed'", [$appealId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'appeal_resolved' AND target_type = 'post' AND target_id = ?", [$this->replyId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mod'", [(int) $this->member['id']]));
    }

    public function test_appeal_queue_is_board_scoped_for_non_admin_moderators(): void
    {
        // Appeal #1 lives in the first board (the deleted reply from setUp).
        $this->actingAs($this->member);
        $this->get('/appeals');
        $this->post('/appeals/posts/' . $this->replyId, ['reason' => 'Reinstate my first-board reply.']);

        // A second board the moderator will NOT be assigned to, with its own appeal.
        $otherBoard = $this->makeBoard($this->makeCategory(), ['slug' => 'second-board']);
        $otherThread = $this->makeThread($otherBoard, $this->member, 'Second topic', 'Second OP');
        $otherReply = $this->posting()->reply($this->userEntity($this->member), $otherThread['thread_id'], ['body' => 'Second reply']);
        $this->actingAs($this->admin);
        $this->get('/t/' . $otherThread['thread_id'] . '-' . $otherThread['slug']);
        $this->post('/posts/' . $otherReply . '/delete', ['reason' => 'cleanup']);
        $this->actingAs($this->member);
        $this->get('/appeals');
        $this->post('/appeals/posts/' . $otherReply, ['reason' => 'Reinstate my second-board reply.']);

        // A board moderator assigned ONLY to the first board (role stays 'user' —
        // board authority comes from board_moderators, not users.role).
        $firstBoardId = (int) $this->db->fetchValue('SELECT board_id FROM threads WHERE id = ?', [$this->thread['thread_id']]);
        $mod = $this->makeUser(['username' => 'scopedmod']);
        (new BoardModeratorRepository($this->db))->assign($firstBoardId, (int) $mod['id']);

        $this->actingAs($mod);
        $queue = $this->get('/mod/appeals');
        $this->assertStatus(200, $queue);
        $this->assertSeeText($queue, 'Reinstate my first-board reply.');
        self::assertStringNotContainsString(
            'Reinstate my second-board reply.',
            $queue->body(),
            'a board moderator must not see appeals for boards they do not moderate',
        );

        // A user who moderates nothing cannot reach the queue at all.
        $this->actingAs($this->makeUser(['username' => 'nobodymod']));
        self::assertSame(403, $this->get('/mod/appeals')->status());

        // Admin still sees both appeals site-wide.
        $this->actingAs($this->admin);
        $adminQueue = $this->get('/mod/appeals');
        $this->assertSeeText($adminQueue, 'Reinstate my first-board reply.');
        $this->assertSeeText($adminQueue, 'Reinstate my second-board reply.');
    }
}
