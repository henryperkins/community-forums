<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Service\ContentReferenceService;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceRetryPolicy;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Security\BoardPolicy;
use App\Worker\ThreadIntelligenceWorker;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

final class CallbackThreadIntelligenceProvider implements ThreadIntelligenceProvider
{
    /** @var list<ThreadIntelligenceRequest> */
    public array $requests = [];

    /** @param \Closure(ThreadIntelligenceRequest,int):ThreadIntelligenceResult $callback */
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function generate(ThreadIntelligenceRequest $request): ThreadIntelligenceResult
    {
        $this->requests[] = $request;
        return ($this->callback)($request, count($this->requests));
    }
}

final class CallbackThreadIntelligenceModerator implements ThreadIntelligenceOutputModerator
{
    /** @var list<string> */
    public array $texts = [];

    /** @param \Closure(string,int):\App\Service\ThreadIntelligence\ThreadIntelligenceModerationResult $callback */
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function moderate(string $text): \App\Service\ThreadIntelligence\ThreadIntelligenceModerationResult
    {
        $this->texts[] = $text;
        return ($this->callback)($text, count($this->texts));
    }
}

#[Group('nonparallel')]
final class ThreadIntelligenceWorkerTest extends TestCase
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

    /** @return array{processed:int,succeeded:int,failed:int} */
    private function runWorker(ThreadIntelligenceWorker $worker, int $limit = 25, string $label = 'cli'): array
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
            $this->committedFixtures = true;
        }
        return $worker->run($limit, $label);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function enableFlags(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => true,
            'automated_context' => true,
        ]);
    }

    /** @return array{thread_id:int,board_id:int,author_id:int,post_ids:list<int>} */
    private function seedThread(int $postCount = 8, int $bodyBytes = 32): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $thread = $this->makeThread($board, $author, 'Durable generation topic', str_repeat('O', $bodyBytes));
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$threadId],
        )];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->db->insert(
                'INSERT INTO posts
                    (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [
                    $threadId,
                    (int) $author['id'],
                    str_repeat(chr(65 + ($i % 20)), $bodyBytes),
                    '<p>Evidence</p>',
                    $this->now()->modify('-2 hours +' . $i . ' minutes')->format('Y-m-d H:i:s'),
                ],
            );
        }

        return [
            'thread_id' => $threadId,
            'board_id' => (int) $board['id'],
            'author_id' => (int) $author['id'],
            'post_ids' => $postIds,
        ];
    }

    /** @param list<int> $sourceIds */
    private function providerResult(array $sourceIds, string $responseId = 'resp_worker'): ThreadIntelligenceResult
    {
        $first = $sourceIds[0];
        $last = $sourceIds[array_key_last($sourceIds)];
        return new ThreadIntelligenceResult([
            'overview' => [
                'markdown' => 'The discussion records a stable evidence-bound overview for the current public thread.',
                'source_post_ids' => [$first, $last],
            ],
            'key_points' => [
                ['markdown' => 'Participants established the first supported point.', 'source_post_ids' => [$first]],
                ['markdown' => 'Participants established the second supported point.', 'source_post_ids' => [$last]],
            ],
            'open_questions' => [
                ['markdown' => 'The remaining question still needs a documented answer.', 'source_post_ids' => [$last]],
            ],
            'related_topics' => [],
        ], $responseId, ThreadIntelligenceResult::STATUS_COMPLETED, null, new ThreadIntelligenceUsage(321, 123, 17, 9));
    }

    private function resultForRequest(ThreadIntelligenceRequest $request, string $responseId): ThreadIntelligenceResult
    {
        $ids = array_map(static fn ($post): int => $post->postId, $request->posts);
        if ($ids === [] && $request->carryForward !== null) {
            $ids = $request->carryForward->sourcePostIds;
        }
        if ($ids === [] && $request->baseline !== null) {
            $ids = $request->baseline->sourcePostIds;
        }
        return $this->providerResult($ids, $responseId);
    }

    /** @param array<string,mixed> $overrides */
    private function services(
        ThreadIntelligenceProvider $provider,
        ?ThreadIntelligenceOutputModerator $moderator = null,
        array $overrides = [],
        ?Database $database = null,
    ): array {
        $database ??= $this->db;
        $apiKey = (string) ($overrides['api_key'] ?? 'sk-test-thread-intelligence');
        $config = ThreadIntelligenceConfig::fromArray([
            'api_key' => $apiKey,
            'daily_call_limit' => $overrides['daily_call_limit'] ?? 100,
            'daily_input_token_limit' => $overrides['daily_input_token_limit'] ?? 1_000_000,
            'max_input_tokens' => $overrides['max_input_tokens'] ?? 32_000,
            'max_output_tokens' => $overrides['max_output_tokens'] ?? 16_000,
        ]);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($database),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $database,
        );
        $jobs = new ThreadIntelligenceJobRepository($database);
        $generations = new ThreadIntelligenceGenerationRepository($database);
        $budget = new ThreadIntelligenceBudget($database, $config);
        $flags = new FeatureFlags(new SettingRepository($database));
        $eligibility = new ThreadIntelligenceEligibility($database, $flags, $config, $settings, $budget, $jobs);
        $evidence = new ThreadIntelligenceEvidenceBuilder(
            $database,
            new ThreadIntelligenceCandidateFinder($database),
            $config,
        );
        $markdown = new Markdown(new HtmlSanitizer());
        $publisher = new ThreadIntelligencePublisher(
            $database,
            new ThreadRepository($database),
            $jobs,
            $generations,
            $evidence,
            $markdown,
            new ContentReferenceService(
                $database,
                new BoardRepository($database),
                new ThreadRepository($database),
                new PostRepository($database),
                new TagRepository($database),
                new BoardMemberRepository($database),
                new BoardPolicy(),
                true,
            ),
        );
        $worker = new ThreadIntelligenceWorker(
            $database,
            $flags,
            $config,
            $settings,
            $budget,
            $jobs,
            $generations,
            new ThreadIntelligenceBoardSweep($database),
            $eligibility,
            $evidence,
            $provider,
            new ThreadIntelligenceOutputValidator($markdown),
            $moderator ?? new FakeThreadIntelligenceOutputModerator(),
            $publisher,
        );

        return compact('worker', 'settings', 'jobs', 'generations', 'budget', 'eligibility', 'evidence', 'config');
    }

    /** @param array{thread_id:int} $seed */
    private function due(array $seed, ?DateTimeImmutable $dueAt = null): void
    {
        (new ThreadIntelligenceJobRepository($this->db))->upsertStale(
            $seed['thread_id'],
            ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
            null,
            $dueAt ?? $this->now()->modify('-1 minute'),
        );
    }

    public function test_below_eight_posts_and_the_quiet_boundary_make_zero_provider_calls(): void
    {
        $this->enableFlags();
        $provider = new FakeThreadIntelligenceProvider();
        $services = $this->services($provider);

        $below = $this->seedThread(7);
        $this->due($below);
        $quiet = $this->seedThread(8);
        $this->due($quiet, $this->now()->modify('+15 minutes'));

        $counts = $this->runWorker($services['worker'], 25, 'phpunit-zero');

        self::assertSame(0, $provider->callCount());
        self::assertSame(1, $counts['processed'], 'the below-threshold row is safely classified; future work is not claimed');
        self::assertSame('idle', $services['jobs']->find($below['thread_id'])['state']);
        self::assertSame('queued', $services['jobs']->find($quiet['thread_id'])['state']);
    }

    public function test_exactly_eight_posts_produce_one_budgeted_call_and_atomic_publication(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult($this->providerResult($seed['post_ids']));
        $services = $this->services($provider);

        $counts = $this->runWorker($services['worker'], 25, 'phpunit-publish');

        self::assertSame(['processed' => 1, 'succeeded' => 1, 'failed' => 0], $counts);
        self::assertSame(1, $provider->callCount());
        self::assertSame('idle', $services['jobs']->find($seed['thread_id'])['state']);
        self::assertSame('published', $this->db->fetchValue(
            'SELECT status FROM thread_intelligence_generations WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame('ai', $this->db->fetchValue(
            "SELECT kind FROM thread_summaries WHERE thread_id = ? AND status = 'published'",
            [$seed['thread_id']],
        ));
        $budget = $services['budget']->status($this->now());
        self::assertSame(1, $budget['used_calls']);
        self::assertSame(0, $budget['reserved_calls']);
    }

    public function test_five_new_posts_refresh_incrementally_and_carry_the_exact_curator_baseline(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $baselineId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, reviewer_id, published_at, created_at)
             VALUES (?, 'manual', 'published', ?, ?, 7, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], 'Curator exact baseline.', '<p>Curator exact baseline.</p>', $seed['author_id'], $seed['author_id']],
        );
        $this->db->run(
            'INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
            [$baselineId, $seed['post_ids'][0]],
        );
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id,state,trigger_code,due_at,activity_version,last_processed_post_id,last_generated_at,created_at,updated_at)
             VALUES (?, 'queued', 'post_created', ?, 1, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [
                $seed['thread_id'],
                $this->now()->modify('-1 minute')->format('Y-m-d H:i:s'),
                $seed['post_ids'][array_key_last($seed['post_ids'])],
                $this->now()->modify('-2 hours')->format('Y-m-d H:i:s'),
            ],
        );
        $newIds = [];
        for ($i = 0; $i < 5; $i++) {
            $newIds[] = $this->db->insert(
                'INSERT INTO posts (thread_id,user_id,body,body_html,is_op,is_anonymous,is_deleted,is_pending,created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$seed['thread_id'], $seed['author_id'], 'New evidence ' . $i, '<p>New</p>', $this->now()->modify('-30 minutes +' . $i . ' minutes')->format('Y-m-d H:i:s')],
            );
        }
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult($this->providerResult($newIds));
        $services = $this->services($provider);

        $this->runWorker($services['worker']);

        $request = $provider->requests()[0];
        self::assertSame($newIds, array_map(static fn ($post): int => $post->postId, $request->posts));
        self::assertSame($baselineId, $request->baseline?->summaryId);
        self::assertSame(7, $request->baseline?->version);
        self::assertSame('Curator exact baseline.', $request->baseline?->markdown);
        self::assertSame([$seed['post_ids'][0]], $request->baseline?->sourcePostIds);
        self::assertSame(8, (int) $this->db->fetchValue(
            "SELECT version FROM thread_summaries WHERE thread_id = ? AND status = 'published'",
            [$seed['thread_id']],
        ));
    }

    public function test_retirement_pause_defers_calls_until_explicit_resume(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $this->db->run(
            'UPDATE thread_intelligence_jobs SET automation_paused = 1, paused_by = ?, paused_at = UTC_TIMESTAMP() WHERE thread_id = ?',
            [$seed['author_id'], $seed['thread_id']],
        );
        $provider = new FakeThreadIntelligenceProvider();
        $services = $this->services($provider);

        $this->runWorker($services['worker']);
        self::assertSame(0, $provider->callCount());

        $queue = new ThreadIntelligenceQueue($this->db, $services['jobs'], $services['eligibility']);
        $queue->resumeAndRequeue($seed['thread_id'], $seed['author_id'], $this->now());
        self::assertSame(0, (int) $services['jobs']->find($seed['thread_id'])['automation_paused']);
        $this->db->run('UPDATE thread_intelligence_jobs SET due_at = ? WHERE thread_id = ?', [
            $this->now()->modify('-1 minute')->format('Y-m-d H:i:s'),
            $seed['thread_id'],
        ]);
        $provider->queueResult($this->providerResult($seed['post_ids']));

        $this->runWorker($services['worker']);
        self::assertSame(1, $provider->callCount());
    }

    public function test_multiwindow_reconciliation_renews_each_call_and_only_publishes_the_final_row(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread(8, 1_150);
        $this->due($seed);
        $this->db->run('UPDATE thread_intelligence_jobs SET reconcile_required = 1 WHERE thread_id = ?', [$seed['thread_id']]);
        $leaseExpiries = [];
        $provider = new CallbackThreadIntelligenceProvider(function (ThreadIntelligenceRequest $request, int $call) use (&$leaseExpiries, $seed): ThreadIntelligenceResult {
            self::assertFalse($this->pdo->inTransaction(), 'provider calls must never run in a database transaction');
            $leaseExpiries[] = (string) $this->db->fetchValue(
                'SELECT lease_expires_at FROM thread_intelligence_jobs WHERE thread_id = ?',
                [$seed['thread_id']],
            );
            if ($call === 1) {
                usleep(1_100_000);
            }
            return $this->resultForRequest($request, 'resp_window_' . $call);
        });
        $services = $this->services($provider, overrides: ['max_input_tokens' => 8_000, 'max_output_tokens' => 1_000]);

        $this->runWorker($services['worker']);

        self::assertGreaterThan(1, count($provider->requests));
        self::assertLessThanOrEqual(4, count($provider->requests));
        self::assertCount(count($provider->requests), $leaseExpiries);
        self::assertGreaterThan(
            $leaseExpiries[0],
            $leaseExpiries[1],
            'later windows renew from the current boundary time, not the worker start time',
        );
        self::assertSame(range(0, count($provider->requests) - 1), array_map(
            static fn (ThreadIntelligenceRequest $request): int => $request->windowNumber,
            $provider->requests,
        ));
        $statuses = $this->db->run(
            'SELECT status FROM thread_intelligence_generations WHERE thread_id = ? ORDER BY window_number',
            [$seed['thread_id']],
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(array_fill(0, count($statuses) - 1, 'succeeded'), array_slice($statuses, 0, -1));
        self::assertSame('published', $statuses[array_key_last($statuses)]);
        self::assertSame(count($provider->requests), $services['budget']->status($this->now())['used_calls']);
    }

    public function test_validation_precedes_moderation_and_both_external_boundaries_are_outside_transactions(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $events = [];
        $provider = new CallbackThreadIntelligenceProvider(function (ThreadIntelligenceRequest $request) use (&$events): ThreadIntelligenceResult {
            self::assertFalse($this->pdo->inTransaction());
            $events[] = 'provider';
            return $this->resultForRequest($request, 'resp_order');
        });
        $moderator = new CallbackThreadIntelligenceModerator(function (string $text) use (&$events) {
            self::assertFalse($this->pdo->inTransaction());
            self::assertStringContainsString('### Key points', $text, 'moderation receives locally composed validated Markdown');
            $events[] = 'moderator';
            return new \App\Service\ThreadIntelligence\ThreadIntelligenceModerationResult(false);
        });

        $this->runWorker($this->services($provider, $moderator)['worker']);

        self::assertSame(['provider', 'moderator'], $events);
    }

    public function test_candidate_visibility_is_rechecked_before_the_external_moderation_call(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $candidateAuthor = $this->makeUser();
        $candidateBoard = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $candidate = $this->makeThread(
            $candidateBoard,
            $candidateAuthor,
            'Durable generation topic follow-up',
            'Related public evidence.',
        );
        $provider = new CallbackThreadIntelligenceProvider(function (ThreadIntelligenceRequest $request) use ($candidateBoard, $candidate): ThreadIntelligenceResult {
            self::assertContains(
                (int) $candidate['thread_id'],
                array_map(static fn ($related): int => $related->threadId, $request->candidates),
            );
            $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [(int) $candidateBoard['id']]);
            return $this->resultForRequest($request, 'resp_private_before_moderation');
        });
        $moderator = new CallbackThreadIntelligenceModerator(
            static fn (string $text): \App\Service\ThreadIntelligence\ThreadIntelligenceModerationResult =>
                new \App\Service\ThreadIntelligence\ThreadIntelligenceModerationResult(false),
        );
        $services = $this->services($provider, $moderator);

        $this->runWorker($services['worker'], 1, 'moderation-privacy-boundary');

        self::assertCount(1, $provider->requests);
        self::assertCount(0, $moderator->texts, 'newly ineligible evidence must not cross the moderation provider boundary');
        self::assertSame('stale', $this->db->fetchValue(
            'SELECT status FROM thread_intelligence_generations WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'",
            [$seed['thread_id']],
        ));
    }

    public function test_candidate_visibility_is_rechecked_after_reservation_before_provider_egress(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $candidateAuthor = $this->makeUser();
        $candidateBoard = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $this->makeThread(
            $candidateBoard,
            $candidateAuthor,
            'Durable generation topic candidate',
            'Candidate body must remain public.',
        );
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult($this->providerResult($seed['post_ids'], 'resp_should_not_leave'));
        $services = $this->services($provider);

        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $trigger = 'ti_candidate_privacy_' . bin2hex(random_bytes(4));
        $this->pdo->exec(
            "CREATE TRIGGER `{$trigger}` AFTER UPDATE ON thread_intelligence_generations
             FOR EACH ROW
             BEGIN
               IF OLD.request_fingerprint IS NULL AND NEW.request_fingerprint IS NOT NULL THEN
                 UPDATE boards SET visibility = 'private' WHERE id = " . (int) $candidateBoard['id'] . ";
               END IF;
             END",
        );
        $this->pdo->beginTransaction();

        try {
            $this->runWorker($services['worker'], 1, 'candidate-privacy-boundary');
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS `' . $trigger . '`');
        }

        self::assertSame(0, $provider->callCount(), 'a newly private candidate must not cross the generation provider boundary');
        self::assertSame('stale', $this->db->fetchValue(
            'SELECT status FROM thread_intelligence_generations WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame('queued', $services['jobs']->find($seed['thread_id'])['state']);
        $budget = $services['budget']->status($this->now());
        self::assertSame(0, $budget['used_calls']);
        self::assertSame(0, $budget['reserved_calls']);
    }

    public function test_each_reconciliation_window_rechecks_live_feature_flags_before_provider_egress(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread(8, 1_150);
        $this->due($seed);
        $this->db->run('UPDATE thread_intelligence_jobs SET reconcile_required = 1 WHERE thread_id = ?', [$seed['thread_id']]);
        $provider = new CallbackThreadIntelligenceProvider(function (ThreadIntelligenceRequest $request, int $call): ThreadIntelligenceResult {
            if ($call === 1) {
                (new SettingRepository($this->db))->set('features', [
                    'community_memory' => true,
                    'automated_context' => false,
                ]);
            }
            return $this->resultForRequest($request, 'resp_flag_window_' . $call);
        });
        $services = $this->services($provider, overrides: ['max_input_tokens' => 8_000, 'max_output_tokens' => 1_000]);

        $this->runWorker($services['worker']);

        self::assertCount(1, $provider->requests, 'a live rollback stops before the next provider call');
        $job = $services['jobs']->find($seed['thread_id']);
        self::assertSame('retry', $job['state']);
        self::assertSame(0, (int) $job['attempt_count']);
        $budget = $services['budget']->status($this->now());
        self::assertSame(1, $budget['used_calls'], 'the canceled second window never consumes a provider call');
        self::assertSame(0, $budget['reserved_calls']);
    }

    public function test_a_nontransient_failure_does_not_skip_the_first_transient_backoff(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $this->due($seed);
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueException(new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::VALIDATION_FAILED));
        $provider->queueException(new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT));
        $services = $this->services($provider);

        $this->runWorker($services['worker'], 1, 'validation-first');
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET due_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE thread_id = ?",
            [$seed['thread_id']],
        );

        $before = $this->now();
        $this->runWorker($services['worker'], 1, 'transport-second');
        $after = $this->now();

        $job = $services['jobs']->find($seed['thread_id']);
        self::assertSame('retry', $job['state']);
        $dueAt = new DateTimeImmutable((string) $job['due_at'], new DateTimeZone('UTC'));
        $firstDelay = ThreadIntelligenceRetryPolicy::transientDelaySeconds($seed['thread_id'], 1);
        self::assertGreaterThanOrEqual($before->modify('+' . ($firstDelay - 1) . ' seconds'), $dueAt);
        self::assertLessThanOrEqual($after->modify('+' . $firstDelay . ' seconds'), $dueAt);
        self::assertSame(
            [ThreadIntelligenceFailureCode::VALIDATION_FAILED, ThreadIntelligenceFailureCode::TRANSPORT],
            $this->db->run(
                'SELECT failure_code FROM thread_intelligence_generations WHERE thread_id = ? ORDER BY id',
                [$seed['thread_id']],
            )->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function test_failure_preserves_last_good_and_authentication_engages_the_cross_run_latch(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread();
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id,kind,status,body,body_html,version,author_id,reviewer_id,published_at,created_at)
             VALUES (?, 'manual', 'published', 'Last good', '<p>Last good</p>', 1, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], $seed['author_id'], $seed['author_id']],
        );
        $this->db->run('INSERT INTO thread_summary_sources (summary_id,post_id) VALUES (?,?)', [$summaryId, $seed['post_ids'][0]]);
        $this->due($seed);
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueException(new ThreadIntelligenceProviderException(
            ThreadIntelligenceFailureCode::AUTHENTICATION,
            null,
            true,
        ));
        $services = $this->services($provider);

        $this->runWorker($services['worker']);

        self::assertSame('review_required', $services['jobs']->find($seed['thread_id'])['state']);
        self::assertTrue($services['settings']->providerHealth()['blocked']);
        self::assertSame($summaryId, (int) $this->db->fetchValue(
            "SELECT id FROM thread_summaries WHERE thread_id = ? AND status = 'published'",
            [$seed['thread_id']],
        ));

        $other = $this->seedThread();
        $this->due($other);
        $this->runWorker($services['worker']);
        self::assertSame(1, $provider->callCount(), 'the provider latch defers other work across runs');
        self::assertSame('queued', $services['jobs']->find($other['thread_id'])['state']);
    }

    public function test_evidence_too_large_is_an_audited_no_call_review_outcome(): void
    {
        $this->enableFlags();
        $seed = $this->seedThread(8, 4_000);
        $this->due($seed);
        $provider = new FakeThreadIntelligenceProvider();
        $services = $this->services($provider, overrides: ['max_input_tokens' => 6_000, 'max_output_tokens' => 1_000]);

        $this->runWorker($services['worker']);

        self::assertSame(0, $provider->callCount());
        self::assertSame('review_required', $services['jobs']->find($seed['thread_id'])['state']);
        $generation = $this->db->fetch(
            'SELECT status,failure_code,request_fingerprint FROM thread_intelligence_generations WHERE thread_id = ?',
            [$seed['thread_id']],
        );
        self::assertSame('review_required', $generation['status']);
        self::assertSame(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE, $generation['failure_code']);
        self::assertNull($generation['request_fingerprint'], 'no provider reservation was made');
    }

    public function test_zero_claim_and_failed_runs_always_finish_the_heartbeat(): void
    {
        $this->enableFlags();
        $provider = new FakeThreadIntelligenceProvider();
        $services = $this->services($provider);

        self::assertSame(['processed' => 0, 'succeeded' => 0, 'failed' => 0], $this->runWorker($services['worker'], 25, 'zero-claim'));
        $beat = $services['settings']->heartbeat();
        self::assertSame('ok', $beat['status']);
        self::assertSame('zero-claim', $beat['worker_label']);
        self::assertSame(0, $beat['processed']);

        $seed = $this->seedThread();
        $this->due($seed);
        $provider->queueException(new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT));
        $this->runWorker($services['worker'], 25, 'failed-attempt');
        $beat = $services['settings']->heartbeat();
        self::assertSame('ok', $beat['status'], 'handled job failures do not make the worker process unhealthy');
        self::assertSame(1, $beat['failed']);
    }
}
