<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Service\ContentReferenceService;
use App\Service\ThreadIntelligence\StaleThreadIntelligenceEvidence;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePack;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ValidatedThreadIntelligenceOutput;
use App\Security\BoardPolicy;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * Publication is the final privacy and concurrency boundary. These tests use
 * only local, already-validated output: no provider or moderation seam exists
 * in the publisher fixture.
 */
#[Group('nonparallel')]
final class ThreadIntelligencePublisherTest extends TestCase
{
    private bool $committedFixtures = false;

    protected function tearDown(): void
    {
        if ($this->committedFixtures) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $preserve = [
                'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
                'capabilities', 'role_capabilities', 'theme_state',
            ];
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!in_array($table, $preserve, true)) {
                    $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $table) . '`');
                }
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->committedFixtures = false;
        }
        parent::tearDown();
    }

    private function useCommittedFixtures(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->committedFixtures = true;
    }

    private function waitForConnectionQuery(int $connectionId, string $needle): void
    {
        $deadline = microtime(true) + 5.0;
        do {
            $info = $this->db->fetchValue(
                'SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?',
                [$connectionId],
            );
            if (is_string($info) && str_contains($info, $needle)) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
        self::fail("Timed out waiting for connection {$connectionId} query: {$needle}");
    }

    /** @param resource $stream */
    private function waitForChildOutput($stream, string $needle, string $output = ''): string
    {
        $deadline = microtime(true) + 5.0;
        do {
            $chunk = stream_get_contents($stream);
            if ($chunk !== false) {
                $output .= $chunk;
            }
            if (str_contains($output, $needle)) {
                return $output;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);
        self::fail("Timed out waiting for child output {$needle}; received {$output}");
    }

    /** @return array{0:resource,1:array<int,resource>} */
    private function startChild(string $code, array $environment): array
    {
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
            $environment + [
                'RB_ROOT' => dirname(__DIR__, 3),
                'RB_CHILD_DB' => base64_encode(json_encode($GLOBALS['__RB_TEST_DBCONFIG'], JSON_THROW_ON_ERROR)),
            ],
        );
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [$process, $pipes];
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private function finishChild($process, array $pipes, string $stdout): string
    {
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        if (is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), "Child failed: {$stderr}; stdout: {$stdout}");
        self::assertSame('', trim($stderr));
        return $stdout;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    private function builder(?Database $database = null): ThreadIntelligenceEvidenceBuilder
    {
        $database ??= $this->db;
        return new ThreadIntelligenceEvidenceBuilder(
            $database,
            new ThreadIntelligenceCandidateFinder($database),
            ThreadIntelligenceConfig::fromArray([]),
        );
    }

    private function references(?Database $database = null): ContentReferenceService
    {
        $database ??= $this->db;
        return new ContentReferenceService(
            $database,
            new BoardRepository($database),
            new ThreadRepository($database),
            new PostRepository($database),
            new TagRepository($database),
            new BoardMemberRepository($database),
            new BoardPolicy(),
            true,
        );
    }

    private function publisher(?Database $database = null): ThreadIntelligencePublisher
    {
        $database ??= $this->db;
        return new ThreadIntelligencePublisher(
            $database,
            new ThreadRepository($database),
            new ThreadIntelligenceJobRepository($database),
            new ThreadIntelligenceGenerationRepository($database),
            $this->builder($database),
            new Markdown(new HtmlSanitizer()),
            $this->references($database),
        );
    }

    /**
     * @return array{
     *   thread_id:int,board_id:int,author_id:int,post_ids:list<int>,candidate_ids:list<int>,
     *   baseline_id:int,job:array<string,mixed>,generation_id:int,evidence:ThreadIntelligenceEvidencePack
     * }
     */
    private function scenario(): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Atomic publication source', 'Opening evidence post.');
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$threadId],
        )];
        for ($index = 1; $index < 8; $index++) {
            $postIds[] = $this->posting()->reply(
                $this->userEntity($author),
                $threadId,
                ['body' => 'Evidence reply ' . $index . '.'],
            );
        }

        $candidateIds = [];
        foreach (['Existing tag candidate', 'Existing search candidate', 'Absent candidate', 'Curated candidate', 'Old selection'] as $title) {
            $candidateIds[] = (int) $this->makeThread($board, $author, $title, $title . ' opening body.')['thread_id'];
        }

        $baselineId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, reviewer_id, published_at, created_at)
             VALUES (?, 'manual', 'published', ?, ?, 4, ?, ?, ?, ?)",
            [$threadId, 'Current human baseline.', '<p>Current human baseline.</p>', (int) $author['id'], (int) $author['id'], '2026-07-10 10:00:00', '2026-07-10 10:00:00'],
        );
        $this->db->run(
            'INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
            [$baselineId, $postIds[0]],
        );

        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $jobs->upsertStale($threadId, ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $claimed = $jobs->claimDue(1, $this->now());
        self::assertCount(1, $claimed);
        $job = $claimed[0];
        $evidence = $this->builder()->build($threadId, $job);

        $generations = new ThreadIntelligenceGenerationRepository($this->db);
        $generationId = $generations->start([
            'thread_id' => $threadId,
            'trigger_code' => (string) $job['trigger_code'],
            'baseline_summary_id' => $evidence->baselineSummaryId(),
        ]);
        $generations->recordRequest(
            $generationId,
            $evidence->snapshotHash(),
            $evidence->sourcePostIds(),
            $evidence->candidateThreadIds(),
            hash('sha256', 'publisher-request-' . $generationId),
            $evidence->estimatedInputTokens(0),
        );

        return [
            'thread_id' => $threadId,
            'board_id' => (int) $board['id'],
            'author_id' => (int) $author['id'],
            'post_ids' => $postIds,
            'candidate_ids' => $candidateIds,
            'baseline_id' => $baselineId,
            'job' => $job,
            'generation_id' => $generationId,
            'evidence' => $evidence,
        ];
    }

    /** @param list<int> $sourceIds @param list<array{thread_id:int,explanation:string}> $related */
    private function validatedOutput(array $sourceIds, array $related = [], string $overview = 'Published atomic overview.'): ValidatedThreadIntelligenceOutput
    {
        $markdown = $overview . "\n\n### Key points\n\n- Stable point one.\n- Stable point two.\n\n### Open questions\n\n- Stable question.";
        return new ValidatedThreadIntelligenceOutput(
            $markdown,
            $markdown . ($related === [] ? '' : "\n\n" . implode("\n", array_column($related, 'explanation'))),
            $overview,
            [
                ['markdown' => 'Stable point one.', 'source_post_ids' => [$sourceIds[0]]],
                ['markdown' => 'Stable point two.', 'source_post_ids' => [$sourceIds[0]]],
            ],
            [['markdown' => 'Stable question.', 'source_post_ids' => [$sourceIds[array_key_last($sourceIds)]]]],
            $related,
            array_values(array_unique([$sourceIds[0], $sourceIds[array_key_last($sourceIds)]])),
            array_column($related, 'thread_id'),
        );
    }

    public function test_publish_replaces_the_public_brief_overlays_and_checkpoint_in_one_transaction(): void
    {
        $scenario = $this->scenario();
        [$tagTarget, $searchTarget, $absentTarget, , $oldTarget] = $scenario['candidate_ids'];
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id,
                 ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
             VALUES
                (?, ?, 'related', 'tag', 'tag-owned reason', 'suggested', ?, NULL, NULL, 0, NULL, UTC_TIMESTAMP()),
                (?, ?, 'related', 'search', 'search-owned reason', 'approved', ?, NULL, NULL, 0, NULL, UTC_TIMESTAMP()),
                (?, ?, 'related', 'search', 'old relationship reason', 'approved', ?, NULL, 'old AI reason', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [
                $scenario['thread_id'], $tagTarget, $scenario['author_id'],
                $scenario['thread_id'], $searchTarget, $scenario['author_id'],
                $scenario['thread_id'], $oldTarget, $scenario['author_id'],
            ],
        );

        $related = [
            ['thread_id' => $tagTarget, 'explanation' => 'AI selected the tag candidate.'],
            ['thread_id' => $searchTarget, 'explanation' => 'AI selected the search candidate.'],
            ['thread_id' => $absentTarget, 'explanation' => 'AI selected the absent candidate.'],
        ];
        $output = $this->validatedOutput($scenario['post_ids'], $related);
        $result = $this->publisher()->publish(
            $scenario['generation_id'],
            (string) $scenario['job']['lease_token'],
            $scenario['job'],
            $scenario['evidence'],
            $output,
        );

        self::assertSame($scenario['generation_id'], $result->generationId);
        self::assertGreaterThan(0, $result->summaryId);
        $summary = $this->db->fetch('SELECT * FROM thread_summaries WHERE id = ?', [$result->summaryId]);
        self::assertIsArray($summary);
        self::assertSame('ai', $summary['kind']);
        self::assertSame('published', $summary['status']);
        self::assertNull($summary['author_id']);
        self::assertSame($scenario['baseline_id'], (int) $summary['parent_summary_id']);
        self::assertSame(5, (int) $summary['version']);
        self::assertSame($output->canonicalMarkdown(), $summary['body']);
        self::assertSame((new Markdown(new HtmlSanitizer()))->render($output->canonicalMarkdown()), $summary['body_html']);
        self::assertSame('retired', $this->db->fetchValue('SELECT status FROM thread_summaries WHERE id = ?', [$scenario['baseline_id']]));
        self::assertSame($output->sourcePostIds(), array_map(
            'intval',
            $this->db->run('SELECT post_id FROM thread_summary_sources WHERE summary_id = ? ORDER BY post_id', [$result->summaryId])->fetchAll(\PDO::FETCH_COLUMN),
        ));

        foreach ($related as $expected) {
            $row = $this->db->fetch(
                "SELECT * FROM related_threads WHERE source_thread_id = ? AND related_thread_id = ? AND relation_type = 'related'",
                [$scenario['thread_id'], $expected['thread_id']],
            );
            self::assertIsArray($row);
            self::assertSame(1, (int) $row['ai_selected']);
            self::assertSame($scenario['generation_id'], (int) $row['ai_generation_id']);
            self::assertSame($expected['explanation'], $row['ai_reason']);
            self::assertSame($expected['thread_id'] === $absentTarget ? 'search' : ($expected['thread_id'] === $tagTarget ? 'tag' : 'search'), $row['source']);
            if ($expected['thread_id'] !== $absentTarget) {
                self::assertNotNull($row['curator_id'], 'AI overlay must not overwrite existing identity fields');
                self::assertStringContainsString('owned reason', (string) $row['reason']);
            }
        }
        $old = $this->db->fetch(
            "SELECT * FROM related_threads WHERE source_thread_id = ? AND related_thread_id = ? AND relation_type = 'related'",
            [$scenario['thread_id'], $oldTarget],
        );
        self::assertSame(0, (int) $old['ai_selected']);
        self::assertNull($old['ai_generation_id']);
        self::assertNull($old['ai_reason']);
        self::assertNull($old['ai_selected_at']);

        $generation = $this->db->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]);
        self::assertSame('published', $generation['status']);
        self::assertSame($result->summaryId, (int) $generation['published_summary_id']);
        self::assertNotNull($generation['published_at']);
        $job = (new ThreadIntelligenceJobRepository($this->db))->find($scenario['thread_id']);
        self::assertSame('idle', $job['state']);
        self::assertNull($job['lease_token']);
        self::assertSame(max($scenario['post_ids']), (int) $job['last_processed_post_id']);
        self::assertNotNull($job['last_generated_at']);
        self::assertNotNull($job['last_full_reconcile_at']);
        self::assertSame($scenario['evidence']->snapshotHash(), $job['source_snapshot_hash']);
        self::assertSame(0, (int) $job['reconcile_required']);
    }

    public function test_a_curated_row_at_lock_time_is_never_overwritten_by_ai(): void
    {
        $scenario = $this->scenario();
        $target = $scenario['candidate_ids'][3];
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id,
                 ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
             VALUES (?, ?, 'related', 'curated', 'Curator owns this reason', 'rejected', ?, NULL, NULL, 0, NULL, UTC_TIMESTAMP())",
            [$scenario['thread_id'], $target, $scenario['author_id']],
        );
        // A rejected curated row is still a deterministic candidate, but its
        // ownership must win when the overlay rows are locked for publication.
        $scenario['evidence'] = $this->builder()->build($scenario['thread_id'], $scenario['job']);
        $output = $this->validatedOutput($scenario['post_ids'], [[
            'thread_id' => $target,
            'explanation' => 'AI must not replace curator-owned state.',
        ]]);

        $this->publisher()->publish(
            $scenario['generation_id'],
            (string) $scenario['job']['lease_token'],
            $scenario['job'],
            $scenario['evidence'],
            $output,
        );

        $row = $this->db->fetch(
            "SELECT * FROM related_threads WHERE source_thread_id = ? AND related_thread_id = ? AND relation_type = 'related'",
            [$scenario['thread_id'], $target],
        );
        self::assertSame('curated', $row['source']);
        self::assertSame('Curator owns this reason', $row['reason']);
        self::assertSame($scenario['author_id'], (int) $row['curator_id']);
        self::assertSame(0, (int) $row['ai_selected']);
        self::assertNull($row['ai_generation_id']);
        self::assertNull($row['ai_reason']);
    }

    public function test_visibility_source_candidate_baseline_lease_and_activity_changes_finalize_stale_without_public_mutation(): void
    {
        foreach (['visibility', 'source', 'candidate', 'baseline', 'lease', 'activity'] as $change) {
            $scenario = $this->scenario();
            $this->db->run(
                "INSERT INTO related_threads
                    (source_thread_id, related_thread_id, relation_type, source, status, ai_reason, ai_selected, ai_selected_at, created_at)
                 VALUES (?, ?, 'related', 'search', 'approved', 'last good selection', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [$scenario['thread_id'], $scenario['candidate_ids'][4]],
            );

            match ($change) {
                'visibility' => $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$scenario['board_id']]),
                'source' => $this->db->run('UPDATE posts SET body = ?, edited_at = ? WHERE id = ?', ['Changed after validation.', '2026-07-10 12:01:00', $scenario['post_ids'][1]]),
                'candidate' => $this->db->run('UPDATE threads SET title = ? WHERE id = ?', ['Candidate changed after validation', $scenario['candidate_ids'][0]]),
                'baseline' => $this->replaceBaseline($scenario),
                'lease' => $this->db->run(
                    "UPDATE thread_intelligence_jobs
                     SET state = 'queued', due_at = ?, lease_token = NULL, lease_expires_at = NULL
                     WHERE thread_id = ?",
                    [$this->now()->format('Y-m-d H:i:s'), $scenario['thread_id']],
                ),
                'activity' => (new ThreadIntelligenceJobRepository($this->db))->upsertStale($scenario['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_EDITED, null, $this->now()),
            };

            $summariesBefore = $this->db->fetchAll('SELECT * FROM thread_summaries WHERE thread_id = ? ORDER BY id', [$scenario['thread_id']]);
            $relatedBefore = $this->db->fetchAll('SELECT * FROM related_threads WHERE source_thread_id = ? ORDER BY id', [$scenario['thread_id']]);
            try {
                $this->publisher()->publish(
                    $scenario['generation_id'],
                    (string) $scenario['job']['lease_token'],
                    $scenario['job'],
                    $scenario['evidence'],
                    $this->validatedOutput($scenario['post_ids']),
                );
                self::fail('Expected stale publication for ' . $change);
            } catch (StaleThreadIntelligenceEvidence) {
            }

            self::assertSame($summariesBefore, $this->db->fetchAll('SELECT * FROM thread_summaries WHERE thread_id = ? ORDER BY id', [$scenario['thread_id']]), $change);
            self::assertSame($relatedBefore, $this->db->fetchAll('SELECT * FROM related_threads WHERE source_thread_id = ? ORDER BY id', [$scenario['thread_id']]), $change);
            self::assertSame('stale', $this->db->fetchValue('SELECT status FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]), $change);
            $job = (new ThreadIntelligenceJobRepository($this->db))->find($scenario['thread_id']);
            self::assertSame('queued', $job['state'], $change);
            self::assertNull($job['lease_token'], $change);
            self::assertNotNull($job['due_at'], $change);
            // Keep later loop fixtures from being claimed ahead of their own
            // thread. Each stale case above has already asserted its durable
            // queued state.
            $this->db->run(
                "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL WHERE thread_id = ?",
                [$scenario['thread_id']],
            );
        }
    }

    /** @param array<string,mixed> $scenario */
    private function replaceBaseline(array $scenario): \PDOStatement
    {
        $this->db->run("UPDATE thread_summaries SET status = 'retired', retired_at = UTC_TIMESTAMP() WHERE id = ?", [$scenario['baseline_id']]);
        return $this->db->run(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, published_at, created_at)
             VALUES (?, 'manual', 'published', 'New curator baseline.', '<p>New curator baseline.</p>', 5, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$scenario['thread_id'], $scenario['author_id']],
        );
    }

    public function test_fresh_reconciliation_can_replace_a_baseline_that_already_cites_an_ineligible_post(): void
    {
        $scenario = $this->scenario();
        $invalidBaselineSource = $scenario['post_ids'][0];
        $this->db->run(
            'UPDATE posts SET is_deleted = 1, deleted_at = ? WHERE id = ?',
            ['2026-07-10 11:30:00', $invalidBaselineSource],
        );
        $freshEvidence = $this->builder()->build($scenario['thread_id'], $scenario['job']);
        self::assertContains(
            $invalidBaselineSource,
            $freshEvidence->sourcePostIds(),
            'the exact baseline retains its historical citation until a valid replacement publishes',
        );

        $generations = new ThreadIntelligenceGenerationRepository($this->db);
        $generationId = $generations->start([
            'thread_id' => $scenario['thread_id'],
            'trigger_code' => (string) $scenario['job']['trigger_code'],
            'baseline_summary_id' => $freshEvidence->baselineSummaryId(),
        ]);
        $generations->recordRequest(
            $generationId,
            $freshEvidence->snapshotHash(),
            $freshEvidence->sourcePostIds(),
            $freshEvidence->candidateThreadIds(),
            hash('sha256', 'replacement-after-invalid-citation-' . $generationId),
            $freshEvidence->estimatedInputTokens(0),
        );
        $liveSourceIds = array_values(array_slice($scenario['post_ids'], 1));
        $result = $this->publisher()->publish(
            $generationId,
            (string) $scenario['job']['lease_token'],
            $scenario['job'],
            $freshEvidence,
            $this->validatedOutput($liveSourceIds, [], 'Replacement omits the ineligible historical citation.'),
        );

        $publishedSources = array_map(
            'intval',
            $this->db->run(
                'SELECT post_id FROM thread_summary_sources WHERE summary_id = ? ORDER BY post_id',
                [$result->summaryId],
            )->fetchAll(PDO::FETCH_COLUMN),
        );
        self::assertNotContains($invalidBaselineSource, $publishedSources);
        self::assertSame([$liveSourceIds[0], $liveSourceIds[array_key_last($liveSourceIds)]], $publishedSources);
    }

    public function test_publish_time_evidence_overflow_is_settled_as_stale_and_requeued(): void
    {
        $scenario = $this->scenario();
        $this->db->run(
            'UPDATE posts SET body = ?, edited_at = ? WHERE id = ?',
            [str_repeat('oversized evidence ', 4_000), '2026-07-10 12:01:00', $scenario['post_ids'][1]],
        );

        try {
            $this->publisher()->publish(
                $scenario['generation_id'],
                (string) $scenario['job']['lease_token'],
                $scenario['job'],
                $scenario['evidence'],
                $this->validatedOutput($scenario['post_ids']),
            );
            self::fail('Evidence that can no longer be rebuilt must be stale');
        } catch (StaleThreadIntelligenceEvidence $stale) {
            self::assertSame('evidence_recompute_failed', $stale->reason);
        }

        self::assertSame('stale', $this->db->fetchValue('SELECT status FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]));
        $job = (new ThreadIntelligenceJobRepository($this->db))->find($scenario['thread_id']);
        self::assertSame('queued', $job['state']);
        self::assertNull($job['lease_token']);
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'", [$scenario['thread_id']]));
    }

    public function test_stale_settlement_never_surrenders_a_new_workers_foreign_lease(): void
    {
        $scenario = $this->scenario();
        $foreignToken = str_repeat('f', 64);
        $this->db->run(
            'UPDATE thread_intelligence_jobs SET lease_token = ? WHERE thread_id = ?',
            [$foreignToken, $scenario['thread_id']],
        );

        try {
            $this->publisher()->publish(
                $scenario['generation_id'],
                (string) $scenario['job']['lease_token'],
                $scenario['job'],
                $scenario['evidence'],
                $this->validatedOutput($scenario['post_ids']),
            );
            self::fail('The replaced lease must make the response stale');
        } catch (StaleThreadIntelligenceEvidence) {
        }

        self::assertSame('stale', $this->db->fetchValue('SELECT status FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]));
        $job = (new ThreadIntelligenceJobRepository($this->db))->find($scenario['thread_id']);
        self::assertSame('running', $job['state']);
        self::assertSame($foreignToken, $job['lease_token']);
        self::assertSame($scenario['job']['lease_expires_at'], $job['lease_expires_at']);
        self::assertSame((int) $scenario['job']['activity_version'], (int) $job['activity_version']);
    }

    public function test_a_late_failure_after_overlay_work_rolls_back_every_public_and_checkpoint_mutation(): void
    {
        $scenario = $this->scenario();
        $selectedTarget = $scenario['candidate_ids'][0];
        $oldTarget = $scenario['candidate_ids'][4];
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, status, ai_reason, ai_selected, ai_selected_at, created_at)
             VALUES (?, ?, 'related', 'search', 'approved', 'keep this selection', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$scenario['thread_id'], $oldTarget],
        );
        $output = $this->validatedOutput(
            $scenario['post_ids'],
            [['thread_id' => $selectedTarget, 'explanation' => 'This overlay must roll back.']],
            'See /t/' . $selectedTarget . ' while proving late rollback.',
        );
        $this->useCommittedFixtures();
        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $beforeSummaries = $observer->fetchAll('SELECT * FROM thread_summaries WHERE thread_id = ? ORDER BY id', [$scenario['thread_id']]);
        $beforeRelated = $observer->fetchAll('SELECT * FROM related_threads WHERE source_thread_id = ? ORDER BY id', [$scenario['thread_id']]);
        $beforeReferences = $observer->fetchAll("SELECT * FROM content_references WHERE source_type = 'summary' ORDER BY id");
        $beforeGeneration = $observer->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]);
        $beforeJob = (new ThreadIntelligenceJobRepository($observer))->find($scenario['thread_id']);

        $trigger = 'rb_test_fail_ti_publish';
        $this->pdo->exec('DROP TRIGGER IF EXISTS `' . $trigger . '`');
        $this->pdo->exec(
            "CREATE TRIGGER `{$trigger}` BEFORE UPDATE ON thread_intelligence_generations
             FOR EACH ROW
             BEGIN
                 IF NEW.status = 'published' THEN
                     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced late publication failure';
                 END IF;
             END",
        );
        try {
            $this->publisher(new Database($GLOBALS['__RB_TEST_DBCONFIG']))->publish(
                $scenario['generation_id'],
                (string) $scenario['job']['lease_token'],
                $scenario['job'],
                $scenario['evidence'],
                $output,
            );
            self::fail('The test trigger must force a late generation-completion failure');
        } catch (\PDOException $failure) {
            self::assertStringContainsString('forced late publication failure', $failure->getMessage());
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS `' . $trigger . '`');
        }

        self::assertSame($beforeSummaries, $observer->fetchAll('SELECT * FROM thread_summaries WHERE thread_id = ? ORDER BY id', [$scenario['thread_id']]));
        self::assertSame($beforeRelated, $observer->fetchAll('SELECT * FROM related_threads WHERE source_thread_id = ? ORDER BY id', [$scenario['thread_id']]));
        self::assertSame($beforeReferences, $observer->fetchAll("SELECT * FROM content_references WHERE source_type = 'summary' ORDER BY id"));
        self::assertSame($beforeGeneration, $observer->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]));
        self::assertSame($beforeJob, (new ThreadIntelligenceJobRepository($observer))->find($scenario['thread_id']));
    }

    public function test_waiting_curator_promotes_the_ai_row_inserted_while_it_waits_for_the_source_lock(): void
    {
        $scenario = $this->scenario();
        $admin = $this->makeAdmin();
        $target = $scenario['candidate_ids'][0];
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        (new ThreadRepository($this->db))->findForUpdate($scenario['thread_id']);
        $child = <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$db = new App\Core\Database(json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR));
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$actor = App\Domain\User::fromRow((new App\Repository\UserRepository($db))->find((int) getenv('RB_ACTOR_ID')));
$service = new App\Service\CommunityMemoryService(
    $db,
    new App\Repository\ThreadRepository($db),
    new App\Repository\PostRepository($db),
    new App\Repository\BoardModeratorRepository($db),
    new App\Repository\BoardMemberRepository($db),
    new App\Security\BoardPolicy(),
    new App\Security\WriteGate(),
    new App\Support\Markdown(new App\Support\HtmlSanitizer()),
);
echo 'READY ' . $db->fetchValue('SELECT CONNECTION_ID()') . "\n";
flush();
$service->addRelated($actor, (int) getenv('RB_SOURCE_ID'), (int) getenv('RB_TARGET_ID'), 'Curator won the serialized race');
echo "DONE\n";
PHP;
        [$process, $pipes] = $this->startChild($child, [
            'RB_ACTOR_ID' => (string) $admin['id'],
            'RB_SOURCE_ID' => (string) $scenario['thread_id'],
            'RB_TARGET_ID' => (string) $target,
        ]);
        $stdout = $this->waitForChildOutput($pipes[1], 'READY');
        preg_match('/READY (\d+)/', $stdout, $match);
        $this->waitForConnectionQuery((int) ($match[1] ?? 0), 'FOR UPDATE');

        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, status,
                 ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
             VALUES (?, ?, 'related', 'search', 'approved', ?, 'AI inserted first', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$scenario['thread_id'], $target, $scenario['generation_id']],
        );
        $this->pdo->commit();
        $stdout = $this->waitForChildOutput($pipes[1], 'DONE', $stdout);
        $this->finishChild($process, $pipes, $stdout);

        $observer = new \App\Core\Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $row = $observer->fetch(
            "SELECT * FROM related_threads WHERE source_thread_id = ? AND related_thread_id = ? AND relation_type = 'related'",
            [$scenario['thread_id'], $target],
        );
        self::assertSame('curated', $row['source']);
        self::assertSame('Curator won the serialized race', $row['reason']);
        self::assertSame((int) $admin['id'], (int) $row['curator_id']);
        self::assertNull($row['ai_generation_id']);
        self::assertNull($row['ai_reason']);
        self::assertSame(0, (int) $row['ai_selected']);
        self::assertNull($row['ai_selected_at']);
    }

    public function test_waiting_publisher_observes_an_absent_pair_becoming_curated_before_its_source_lock(): void
    {
        $scenario = $this->scenario();
        $admin = $this->makeAdmin();
        $target = $scenario['candidate_ids'][0];
        $this->useCommittedFixtures();

        $child = <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$db = new App\Core\Database(json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR));
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$jobs = new App\Repository\ThreadIntelligenceJobRepository($db);
$job = $jobs->find((int) getenv('RB_SOURCE_ID'));
$builder = new App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder(
    $db,
    new App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder($db),
    App\Service\ThreadIntelligence\ThreadIntelligenceConfig::fromArray([]),
);
$evidence = $builder->build((int) getenv('RB_SOURCE_ID'), $job);
$postIds = json_decode(base64_decode((string) getenv('RB_POST_IDS')), true, 512, JSON_THROW_ON_ERROR);
$targetId = (int) getenv('RB_TARGET_ID');
$markdown = "Race-safe overview.\n\n### Key points\n\n- Point one.\n- Point two.\n\n### Open questions\n\n- Question.";
$output = new App\Service\ThreadIntelligence\ValidatedThreadIntelligenceOutput(
    $markdown,
    $markdown . "\n\nRace explanation.",
    'Race-safe overview.',
    [
        ['markdown' => 'Point one.', 'source_post_ids' => [$postIds[0]]],
        ['markdown' => 'Point two.', 'source_post_ids' => [$postIds[0]]],
    ],
    [['markdown' => 'Question.', 'source_post_ids' => [$postIds[count($postIds) - 1]]]],
    [['thread_id' => $targetId, 'explanation' => 'Race explanation.']],
    [$postIds[0], $postIds[count($postIds) - 1]],
    [$targetId],
);
$references = new App\Service\ContentReferenceService(
    $db,
    new App\Repository\BoardRepository($db),
    new App\Repository\ThreadRepository($db),
    new App\Repository\PostRepository($db),
    new App\Repository\TagRepository($db),
    new App\Repository\BoardMemberRepository($db),
    new App\Security\BoardPolicy(),
    true,
);
$publisher = new App\Service\ThreadIntelligence\ThreadIntelligencePublisher(
    $db,
    new App\Repository\ThreadRepository($db),
    $jobs,
    new App\Repository\ThreadIntelligenceGenerationRepository($db),
    $builder,
    new App\Support\Markdown(new App\Support\HtmlSanitizer()),
    $references,
);
echo 'READY ' . $db->fetchValue('SELECT CONNECTION_ID()') . "\n";
flush();
fgets(STDIN);
try {
    $publisher->publish((int) getenv('RB_GENERATION_ID'), (string) $job['lease_token'], $job, $evidence, $output);
    echo "PUBLISHED\n";
} catch (App\Service\ThreadIntelligence\StaleThreadIntelligenceEvidence) {
    echo "STALE\n";
}
PHP;
        [$process, $pipes] = $this->startChild($child, [
            'RB_SOURCE_ID' => (string) $scenario['thread_id'],
            'RB_TARGET_ID' => (string) $target,
            'RB_GENERATION_ID' => (string) $scenario['generation_id'],
            'RB_POST_IDS' => base64_encode(json_encode($scenario['post_ids'], JSON_THROW_ON_ERROR)),
        ]);
        $stdout = $this->waitForChildOutput($pipes[1], 'READY');
        preg_match('/READY (\d+)/', $stdout, $match);
        $this->pdo->beginTransaction();
        (new ThreadRepository($this->db))->findForUpdate($scenario['thread_id']);
        fwrite($pipes[0], "GO\n");
        fflush($pipes[0]);
        fclose($pipes[0]);
        $this->waitForConnectionQuery((int) ($match[1] ?? 0), 'FOR UPDATE');

        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id, created_at)
             VALUES (?, ?, 'related', 'curated', 'Curator inserted while publisher waited', 'approved', ?, UTC_TIMESTAMP())",
            [$scenario['thread_id'], $target, (int) $admin['id']],
        );
        $this->pdo->commit();
        $stdout = $this->waitForChildOutput($pipes[1], 'STALE', $stdout);
        $stdout = $this->finishChild($process, $pipes, $stdout);
        self::assertStringNotContainsString('PUBLISHED', $stdout);

        $observer = new \App\Core\Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $row = $observer->fetch(
            "SELECT * FROM related_threads WHERE source_thread_id = ? AND related_thread_id = ? AND relation_type = 'related'",
            [$scenario['thread_id'], $target],
        );
        self::assertSame('curated', $row['source']);
        self::assertSame('Curator inserted while publisher waited', $row['reason']);
        self::assertSame((int) $admin['id'], (int) $row['curator_id']);
        self::assertNull($row['ai_generation_id']);
        self::assertSame('stale', $observer->fetchValue('SELECT status FROM thread_intelligence_generations WHERE id = ?', [$scenario['generation_id']]));
        self::assertSame(0, (int) $observer->fetchValue("SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'", [$scenario['thread_id']]));
    }
}
