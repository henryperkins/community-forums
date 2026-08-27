<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\PostRepository;
use App\Repository\ThreadUserRepository;
use App\Service\RepairService;
use App\Service\SinceLastReadContextService;
use Tests\Support\TestCase;

final class ThreadReadCursorRepositoryTest extends TestCase
{
    /**
     * Build a target topic whose chronologically newest post has a numerically
     * smaller id than the stored cursor. This is the shape produced when an
     * older source topic is merged into a newer target after timestamps have
     * been imported or corrected.
     *
     * @return array{viewer:array<string,mixed>,board:array<string,mixed>,source:array<string,mixed>,target:array<string,mixed>,source_op:int,cursor:int,moved:int}
     */
    private function seedSkewedReadOrder(string $suffix): array
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'cursor_author_' . $suffix]);
        $viewer = $this->makeUser(['username' => 'cursor_viewer_' . $suffix]);
        $board = $this->makeBoard(
            $this->makeCategory('Cursor order ' . $suffix),
            ['slug' => 'cursor-order-' . $suffix],
        );

        $source = $this->makeThread($board, $author, 'Cursor source ' . $suffix, 'Source opener.');
        $sourceOp = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $source['thread_id']],
        );
        $moved = $this->posting()->reply(
            $this->userEntity($author),
            (int) $source['thread_id'],
            ['body' => 'Moved but chronologically newest.'],
        );

        $target = $this->makeThread($board, $author, 'Cursor target ' . $suffix, 'Target opener.');
        $targetOp = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $target['thread_id']],
        );
        $cursor = $this->posting()->reply(
            $this->userEntity($author),
            (int) $target['thread_id'],
            ['body' => 'Stored read cursor.'],
        );

        self::assertLessThan($cursor, $moved, 'fixture requires the moved post to have the smaller id');
        $this->db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-27 09:00:00', $targetOp]);
        $this->db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-27 10:00:00', $cursor]);
        $this->db->run(
            'UPDATE posts SET thread_id = ?, created_at = ? WHERE id = ?',
            [(int) $target['thread_id'], '2026-08-27 11:00:00', $moved],
        );
        $this->db->run(
            'UPDATE threads SET last_post_id = ?, last_post_user_id = ?, last_post_at = ?, reply_count = 2 WHERE id = ?',
            [$moved, (int) $author['id'], '2026-08-27 11:00:00', (int) $target['thread_id']],
        );
        $this->db->run(
            'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0)',
            [(int) $viewer['id'], (int) $target['thread_id'], $cursor],
        );

        return [
            'viewer' => $viewer,
            'board' => $board,
            'source' => $source,
            'target' => $target,
            'source_op' => $sourceOp,
            'cursor' => $cursor,
            'moved' => $moved,
        ];
    }

    public function test_first_unread_location_uses_one_tuple_order_query(): void
    {
        $fixture = $this->seedSkewedReadOrder('location');
        $repo = new PostRepository($this->db);
        $this->db->resetMetrics();

        $location = $repo->firstUnreadLocationForUser(
            (int) $fixture['viewer']['id'],
            (int) $fixture['target']['thread_id'],
            2,
        );

        self::assertSame(['post_id' => $fixture['moved'], 'page' => 2], $location);
        self::assertSame(1, $this->db->metrics()['queries'], 'first unread post and page must come from one indexed query');
    }

    public function test_mark_read_is_monotonic_in_tuple_order_and_rejects_invalid_candidates(): void
    {
        $fixture = $this->seedSkewedReadOrder('mark');
        $repo = new ThreadUserRepository($this->db);
        $viewerId = (int) $fixture['viewer']['id'];
        $targetId = (int) $fixture['target']['thread_id'];

        $repo->markRead($viewerId, $targetId, $fixture['moved']);
        self::assertSame($fixture['moved'], (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [$viewerId, $targetId],
        ));

        $numericallyHigherButEarlier = (new PostRepository($this->db))->create([
            'thread_id' => $targetId,
            'user_id' => (int) $fixture['viewer']['id'],
            'body' => 'Higher id, earlier time.',
            'body_html' => '<p>Higher id, earlier time.</p>',
        ]);
        $this->db->run(
            'UPDATE posts SET created_at = ? WHERE id = ?',
            ['2026-08-27 09:30:00', $numericallyHigherButEarlier],
        );

        $repo->markRead($viewerId, $targetId, $numericallyHigherButEarlier);
        $repo->markRead($viewerId, $targetId, $fixture['source_op']);

        self::assertSame($fixture['moved'], (int) $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [$viewerId, $targetId],
        ));
    }

    public function test_unread_flags_counts_and_inbox_compare_cursor_tuples(): void
    {
        $fixture = $this->seedSkewedReadOrder('surfaces');
        $repo = new ThreadUserRepository($this->db);
        $viewerId = (int) $fixture['viewer']['id'];
        $targetId = (int) $fixture['target']['thread_id'];
        $boardId = (int) $fixture['board']['id'];

        self::assertTrue($repo->unreadFlags($viewerId, [$targetId], ThreadUserRepository::NO_CUTOVER)[$targetId]);
        self::assertSame(1, $repo->unreadCountForBoard($viewerId, $boardId, ThreadUserRepository::NO_CUTOVER));
        self::assertSame(1, $repo->unreadCount($viewerId, false, ThreadUserRepository::NO_CUTOVER));
        self::assertSame(
            ['Cursor target surfaces'],
            array_column($repo->inbox($viewerId, 'unread', false, ThreadUserRepository::NO_CUTOVER, 20, 0), 'title'),
        );
    }

    public function test_since_last_read_and_repair_choose_the_chronological_endpoint(): void
    {
        $fixture = $this->seedSkewedReadOrder('context');
        $viewerId = (int) $fixture['viewer']['id'];
        $targetId = (int) $fixture['target']['thread_id'];

        $context = (new SinceLastReadContextService($this->db))->forThread($viewerId, $targetId);

        self::assertIsArray($context);
        self::assertSame(1, $context['post_count']);
        self::assertSame($fixture['moved'], $context['to_post_id']);
        self::assertSame([$fixture['moved']], array_column($context['items'], 'post_id'));

        $this->db->run(
            'UPDATE threads SET last_post_id = ?, last_post_at = ? WHERE id = ?',
            [$fixture['cursor'], '2026-08-27 10:00:00', $targetId],
        );
        (new RepairService($this->db))->repairThreadCounters();
        $thread = $this->db->fetch('SELECT last_post_id, last_post_at FROM threads WHERE id = ?', [$targetId]);

        self::assertSame($fixture['moved'], (int) $thread['last_post_id']);
        self::assertSame('2026-08-27 11:00:00', (string) $thread['last_post_at']);
    }

    public function test_posts_have_the_read_order_covering_index(): void
    {
        $columns = $this->db->fetchAll(
            "SELECT COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'idx_posts_thread_read'
             ORDER BY SEQ_IN_INDEX",
        );

        self::assertSame(
            ['thread_id', 'is_deleted', 'is_pending', 'created_at', 'id'],
            array_column($columns, 'COLUMN_NAME'),
        );
    }
}
