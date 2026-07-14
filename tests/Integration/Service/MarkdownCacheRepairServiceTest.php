<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\UserRepository;
use App\Service\MarkdownCacheRepairService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Support\MarkdownRenderer;
use App\Support\MentionLinker;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\TestCase;

final class MarkdownCacheRepairServiceTest extends TestCase
{
    public function test_rebuild_refreshes_every_markdown_cache_with_context_options(): void
    {
        $ids = $this->seedAllCaches();

        $stats = $this->service()->rebuild(batchSize: 1);

        foreach (['posts', 'dm_messages', 'thread_summaries', 'post_revisions'] as $table) {
            self::assertSame(['scanned' => 1, 'changed' => 1], $stats[$table]);
        }
        $postHtml = (string) $this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$ids['post']]);
        $dmHtml = (string) $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE id = ?', [$ids['dm']]);
        $summaryHtml = (string) $this->db->fetchValue('SELECT body_html FROM thread_summaries WHERE id = ?', [$ids['summary']]);
        $revisionHtml = (string) $this->db->fetchValue('SELECT body_html FROM post_revisions WHERE id = ?', [$ids['revision']]);

        self::assertStringContainsString('<strong>post cache</strong>', $postHtml);
        self::assertStringContainsString('class="mention"', $postHtml);
        self::assertStringContainsString('class="mention"', $dmHtml);
        self::assertStringNotContainsString('class="mention"', $summaryHtml);
        self::assertStringContainsString('class="mention"', $revisionHtml);
    }

    public function test_dry_run_reports_changes_without_writing(): void
    {
        $ids = $this->seedAllCaches();

        $stats = $this->service()->rebuild(batchSize: 2, dryRun: true);

        foreach (['posts', 'dm_messages', 'thread_summaries', 'post_revisions'] as $table) {
            self::assertSame(['scanned' => 1, 'changed' => 1], $stats[$table]);
        }
        self::assertSame('<p>stale post</p>', $this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$ids['post']]));
        self::assertSame('<p>stale dm</p>', $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE id = ?', [$ids['dm']]));
        self::assertSame('<p>stale summary</p>', $this->db->fetchValue('SELECT body_html FROM thread_summaries WHERE id = ?', [$ids['summary']]));
        self::assertSame('<p>stale revision</p>', $this->db->fetchValue('SELECT body_html FROM post_revisions WHERE id = ?', [$ids['revision']]));
    }

    public function test_keyset_batches_are_idempotent(): void
    {
        $ids = $this->seedAllCaches();
        $author = $this->users()->find((int) $ids['author']);
        $replyId = $this->posting()->reply(
            $this->userEntity($author),
            (int) $ids['thread'],
            ['body' => '**second post cache**'],
        );
        $this->db->run('UPDATE posts SET body_html = ? WHERE id = ?', ['<p>stale second post</p>', $replyId]);

        $first = $this->service()->rebuild(batchSize: 1);
        $second = $this->service()->rebuild(batchSize: 1);

        self::assertSame(['scanned' => 2, 'changed' => 2], $first['posts']);
        self::assertSame(['scanned' => 2, 'changed' => 0], $second['posts']);
        foreach (['dm_messages', 'thread_summaries', 'post_revisions'] as $table) {
            self::assertSame(0, $second[$table]['changed']);
        }
    }

    public function test_batch_size_must_be_bounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->rebuild(batchSize: 0);
    }

    public function test_render_failure_names_the_table_and_row_without_partially_writing_the_batch(): void
    {
        $ids = $this->seedAllCaches();
        $author = $this->users()->find((int) $ids['author']);
        $replyId = $this->posting()->reply(
            $this->userEntity($author),
            (int) $ids['thread'],
            ['body' => '**second post cache**'],
        );
        $this->db->run('UPDATE posts SET body_html = ? WHERE id = ?', ['<p>stale second post</p>', $replyId]);

        $renderer = new class implements MarkdownRenderer {
            private int $calls = 0;

            public function render(string $markdown, array $options = []): string
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new RuntimeException('synthetic render failure');
                }
                return '<p>rebuilt</p>';
            }
        };

        try {
            (new MarkdownCacheRepairService($this->db, $renderer))->rebuild(batchSize: 2);
            self::fail('Expected the repair to report the failed cache row.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                "Render-cache rebuild failed at posts row {$replyId}: synthetic render failure",
                $exception->getMessage(),
            );
        }

        self::assertSame(
            '<p>stale post</p>',
            $this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$ids['post']]),
        );
        self::assertSame(
            '<p>stale second post</p>',
            $this->db->fetchValue('SELECT body_html FROM posts WHERE id = ?', [$replyId]),
        );
    }

    /** @return array{post:int,dm:int,summary:int,revision:int,author:int,thread:int} */
    private function seedAllCaches(): array
    {
        $author = $this->makeUser(['username' => 'cacheauthor']);
        $target = $this->makeUser(['username' => 'cachetarget']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'cache-repair']);
        $thread = $this->makeThread($board, $author, 'Cache repair', '**post cache** @cachetarget');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $thread['thread_id']],
        );
        $this->db->run('UPDATE posts SET body_html = ? WHERE id = ?', ['<p>stale post</p>', $postId]);

        $conversationId = (new ConversationRepository($this->db))->findOrCreateBetween(
            (int) $author['id'],
            (int) $target['id'],
        );
        $dmId = (new DmMessageRepository($this->db))->create(
            $conversationId,
            (int) $author['id'],
            '**dm cache** @cachetarget',
            '<p>stale dm</p>',
        );
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries (thread_id, kind, status, body, body_html, version, author_id, created_at)
             VALUES (?, 'manual', 'draft', ?, ?, 1, ?, UTC_TIMESTAMP())",
            [(int) $thread['thread_id'], '**summary cache** @cachetarget', '<p>stale summary</p>', (int) $author['id']],
        );
        $revisionId = $this->db->insert(
            'INSERT INTO post_revisions (post_id, editor_id, body, body_html, reason, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$postId, (int) $author['id'], '**revision cache** @cachetarget', '<p>stale revision</p>', 'test'],
        );

        return [
            'post' => $postId,
            'dm' => $dmId,
            'summary' => $summaryId,
            'revision' => $revisionId,
            'author' => (int) $author['id'],
            'thread' => (int) $thread['thread_id'],
        ];
    }

    private function service(): MarkdownCacheRepairService
    {
        return new MarkdownCacheRepairService(
            $this->db,
            new Markdown(
                new HtmlSanitizer(),
                null,
                new MentionLinker(new UserRepository($this->db), true),
            ),
        );
    }
}
