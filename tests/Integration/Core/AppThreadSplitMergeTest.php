<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppThreadSplitMergeTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $member;
    /** @var array<string,mixed> */
    private array $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setFlags(['split_merge' => true]);
        $this->admin = $this->makeAdmin(['username' => 'split-admin']);
        $this->member = $this->makeUser(['username' => 'split-member']);
        $this->board = $this->makeBoard($this->makeCategory(), ['slug' => 'split']);
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()",
            [json_encode($flags, JSON_THROW_ON_ERROR)],
        );
    }

    public function test_admin_splits_selected_replies_into_new_thread(): void
    {
        $thread = $this->makeThread($this->board, $this->member, 'Original topic', 'Opening post');
        $firstReply = $this->posting()->reply($this->userEntity($this->member), $thread['thread_id'], ['body' => 'First reply']);
        $secondReply = $this->posting()->reply($this->userEntity($this->member), $thread['thread_id'], ['body' => 'Second reply']);

        $this->actingAs($this->admin);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $response = $this->post('/mod/t/' . $thread['thread_id'] . '/split', [
            'title' => 'Split topic',
            'post_ids' => (string) $firstReply,
        ]);

        $newThreadId = (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Split topic'");
        self::assertGreaterThan(0, $newThreadId);
        $this->assertRedirect($response, '/t/' . $newThreadId . '-split-topic');
        self::assertSame($newThreadId, (int) $this->posts()->find($firstReply)['thread_id']);
        self::assertSame(1, (int) $this->posts()->find($firstReply)['is_op']);
        self::assertSame((int) $thread['thread_id'], (int) $this->posts()->find($secondReply)['thread_id']);
        self::assertSame(1, (int) $this->threads()->find($thread['thread_id'])['reply_count']);
        self::assertSame(0, (int) $this->threads()->find($newThreadId)['reply_count']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'split_thread' AND target_id = ?", [(int) $thread['thread_id']]));
    }

    public function test_split_updates_touched_counters_without_global_repair(): void
    {
        $unrelated = $this->makeUser(['username' => 'split-unrelated']);
        $this->db->run('UPDATE users SET post_count = 123 WHERE id = ?', [(int) $unrelated['id']]);
        $thread = $this->makeThread($this->board, $this->member, 'Counter source', 'Opening post');
        $firstReply = $this->posting()->reply($this->userEntity($this->member), $thread['thread_id'], ['body' => 'Counter reply one']);
        $this->posting()->reply($this->userEntity($this->member), $thread['thread_id'], ['body' => 'Counter reply two']);

        $this->actingAs($this->admin);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->post('/mod/t/' . $thread['thread_id'] . '/split', [
            'title' => 'Counter split',
            'post_ids' => (string) $firstReply,
        ]);
        $newThreadId = (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Counter split'");

        self::assertSame(1, (int) $this->threads()->find($thread['thread_id'])['reply_count']);
        self::assertSame(0, (int) $this->threads()->find($newThreadId)['reply_count']);
        self::assertSame(123, (int) $this->db->fetchValue('SELECT post_count FROM users WHERE id = ?', [(int) $unrelated['id']]));
    }

    public function test_admin_merges_source_thread_into_target_and_redirects_old_url(): void
    {
        $source = $this->makeThread($this->board, $this->member, 'Source topic', 'Source OP');
        $sourceReply = $this->posting()->reply($this->userEntity($this->member), $source['thread_id'], ['body' => 'Source reply']);
        $target = $this->makeThread($this->board, $this->member, 'Target topic', 'Target OP');

        $this->actingAs($this->admin);
        $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $response = $this->post('/mod/t/' . $source['thread_id'] . '/merge', [
            'target_thread_id' => (int) $target['thread_id'],
        ]);

        $this->assertRedirect($response, '/t/' . $target['thread_id'] . '-' . $target['slug']);
        self::assertSame(1, (int) $this->threads()->find($source['thread_id'])['is_deleted']);
        self::assertSame((int) $target['thread_id'], (int) $this->posts()->find($sourceReply)['thread_id']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_op FROM posts WHERE thread_id = ? AND body = ?', [(int) $target['thread_id'], 'Source OP']));
        self::assertSame(2, (int) $this->threads()->find($target['thread_id'])['reply_count']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_redirects WHERE old_thread_id = ? AND canonical_thread_id = ?', [(int) $source['thread_id'], (int) $target['thread_id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'merge_thread' AND target_id = ?", [(int) $source['thread_id']]));

        $old = $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->assertRedirect($old, '/t/' . $target['thread_id'] . '-' . $target['slug']);
    }

    public function test_merge_updates_touched_counters_without_global_repair(): void
    {
        $unrelated = $this->makeUser(['username' => 'merge-unrelated']);
        $this->db->run('UPDATE users SET post_count = 456 WHERE id = ?', [(int) $unrelated['id']]);
        $source = $this->makeThread($this->board, $this->member, 'Merge counter source', 'Source OP');
        $this->posting()->reply($this->userEntity($this->member), $source['thread_id'], ['body' => 'Source reply']);
        $target = $this->makeThread($this->board, $this->member, 'Merge counter target', 'Target OP');

        $this->actingAs($this->admin);
        $this->get('/t/' . $source['thread_id'] . '-' . $source['slug']);
        $this->post('/mod/t/' . $source['thread_id'] . '/merge', [
            'target_thread_id' => (int) $target['thread_id'],
        ]);

        self::assertSame(1, (int) $this->threads()->find($source['thread_id'])['is_deleted']);
        self::assertSame(2, (int) $this->threads()->find($target['thread_id'])['reply_count']);
        self::assertSame(456, (int) $this->db->fetchValue('SELECT post_count FROM users WHERE id = ?', [(int) $unrelated['id']]));
    }
}
