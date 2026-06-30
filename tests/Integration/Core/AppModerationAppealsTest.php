<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

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
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_appeals WHERE target_type = ? AND target_id = ?', ['post', $this->replyId]));
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
}
