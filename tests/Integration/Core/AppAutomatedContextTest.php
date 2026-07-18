<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\SinceLastReadContextService;
use Tests\Support\TestCase;

final class AppAutomatedContextTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_since_last_read_context_is_available_without_an_override(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'contextauthor']);
        $viewer = $this->makeUser(['username' => 'contextviewer']);
        $board = $this->makeBoard($this->makeCategory('Context Default'), ['slug' => 'context-default']);
        $thread = $this->makeThread($board, $author, 'Context default topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread default-on reply.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Since you last read', $page->body());
        self::assertStringContainsString('Unread default-on reply.', $page->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));
    }

    public function test_since_last_read_context_can_be_rolled_back_with_explicit_false(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => false]);
        $author = $this->makeUser(['username' => 'contextrollbackauthor']);
        $viewer = $this->makeUser(['username' => 'contextrollbackviewer']);
        $board = $this->makeBoard($this->makeCategory('Context Rollback'), ['slug' => 'context-rollback-explicit']);
        $thread = $this->makeThread($board, $author, 'Context rollback topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread rollback reply.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringNotContainsString('Since you last read', $page->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM since_last_read_context'));
    }

    public function test_since_last_read_context_uses_previous_read_marker_before_marking_read(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'contextauthor2']);
        $viewer = $this->makeUser(['username' => 'contextviewer2']);
        $board = $this->makeBoard($this->makeCategory('Context'), ['slug' => 'context-board']);
        $thread = $this->makeThread($board, $author, 'Context topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $firstReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'First unread update with useful context.']);
        $secondReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Second unread update with more detail.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Since you last read', $page->body());
        self::assertStringContainsString('First unread update with useful context.', $page->body());
        self::assertStringContainsString('Second unread update with more detail.', $page->body());

        $row = $this->db->fetch('SELECT * FROM since_last_read_context WHERE user_id = ? AND thread_id = ?', [(int) $viewer['id'], $thread['thread_id']]);
        self::assertIsArray($row);
        self::assertSame($opId, (int) $row['from_post_id']);
        self::assertSame($secondReply, (int) $row['to_post_id']);
        self::assertSame(2, (int) $row['post_count']);
        self::assertStringContainsString('First unread update with useful context.', (string) $row['context_text']);

        self::assertSame($secondReply, (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
        self::assertGreaterThan($firstReply, $secondReply);
    }

    public function test_since_last_read_context_counts_full_window_with_bounded_items(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'contextauthor3']);
        $viewer = $this->makeUser(['username' => 'contextviewer3']);
        $board = $this->makeBoard($this->makeCategory('Context Window'), ['slug' => 'context-window']);
        $thread = $this->makeThread($board, $author, 'Context window topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $first = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'First sampled update.']);
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Second sampled update.']);
        $third = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Third counted update.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $context = (new SinceLastReadContextService($this->db))->forThread((int) $viewer['id'], $thread['thread_id'], 2);

        self::assertIsArray($context);
        self::assertSame(3, $context['post_count']);
        self::assertSame($third, $context['to_post_id']);
        self::assertCount(2, $context['items']);
        self::assertSame($first, $context['items'][0]['post_id']);
        self::assertSame(3, (int) $this->db->fetchValue(
            'SELECT post_count FROM since_last_read_context WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_since_last_read_context_advances_read_marker_when_engagement_is_disabled(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true, 'engagement' => false]);
        $author = $this->makeUser(['username' => 'contextauthor4']);
        $viewer = $this->makeUser(['username' => 'contextviewer4']);
        $board = $this->makeBoard($this->makeCategory('Context Read Marker'), ['slug' => 'context-read-marker']);
        $thread = $this->makeThread($board, $author, 'Context read marker topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread follow-up one.']);
        $latestReply = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Unread follow-up two.']);
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $opId],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('Since you last read', $page->body());
        self::assertSame($latestReply, (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [(int) $viewer['id'], $thread['thread_id']],
        ));
    }

    public function test_since_last_read_context_links_to_the_post_page_when_items_are_off_screen(): void
    {
        $this->makeAdmin();
        $this->setFlags(['automated_context' => true]);
        $author = $this->makeUser(['username' => 'contextauthor5']);
        $viewer = $this->makeUser(['username' => 'contextviewer5']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('Context Paging'), ['slug' => 'context-paging']);
        $thread = $this->makeThread($board, $author, 'Context paging topic', 'Opening post.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $replyIds = [];
        for ($i = 1; $i <= 15; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Unread paging update ' . $i . '.'],
            );
        }
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[8]],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString(
            '/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?page=2#p' . $replyIds[9],
            $page->body(),
        );
        self::assertStringNotContainsString(
            'href="#p' . $replyIds[9] . '"',
            $page->body(),
        );
    }

    public function test_staff_context_links_rank_posts_against_deleted_stub_pages(): void
    {
        $this->setFlags(['automated_context' => true]);
        $viewer = $this->makeAdmin(['username' => 'contextstaff']);
        $author = $this->makeUser(['username' => 'contextstubauthor']);
        (new UserPreferenceRepository($this->db))->merge((int) $viewer['id'], ['posts_per_page' => 10]);
        $board = $this->makeBoard($this->makeCategory('Context Staff Paging'), ['slug' => 'context-staff-paging']);
        $thread = $this->makeThread($board, $author, 'Context staff paging topic', 'Opening post.');

        $deleted = $this->posting()->reply(
            $this->userEntity($author),
            $thread['thread_id'],
            ['body' => 'Deleted row that still occupies a staff-stream slot.'],
        );
        $this->db->run('UPDATE posts SET is_deleted = 1, deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$deleted]);

        $replyIds = [];
        for ($i = 1; $i <= 9; $i++) {
            $replyIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $thread['thread_id'],
                ['body' => 'Staff paging update ' . $i . '.'],
            );
        }
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], $thread['thread_id'], $replyIds[7]],
        );

        $this->actingAs($viewer);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString(
            '/t/' . $thread['thread_id'] . '-' . $thread['slug'] . '?page=2#p' . $replyIds[8],
            $page->body(),
        );
        self::assertStringNotContainsString('href="#p' . $replyIds[8] . '"', $page->body());
    }
}
