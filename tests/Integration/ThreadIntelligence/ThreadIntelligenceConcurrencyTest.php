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
use App\Security\BoardPolicy;
use App\Service\ContentReferenceService;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Worker\ThreadIntelligenceWorker;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

final class ConcurrencyProvider implements ThreadIntelligenceProvider
{
    public int $calls = 0;

    /** @param \Closure(ThreadIntelligenceRequest,int):ThreadIntelligenceResult $callback */
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function generate(ThreadIntelligenceRequest $request): ThreadIntelligenceResult
    {
        $this->calls++;
        return ($this->callback)($request, $this->calls);
    }
}

#[Group('nonparallel')]
final class ThreadIntelligenceConcurrencyTest extends TestCase
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

    private function commitFixtures(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->committedFixtures = true;
    }

    private function secondDatabase(): Database
    {
        return new Database($GLOBALS['__RB_TEST_DBCONFIG']);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{thread_id:int,board_id:int,author_id:int,post_ids:list<int>} */
    private function seedDueThread(): array
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => true, 'automated_context' => true]);
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $thread = $this->makeThread($board, $author, 'Concurrency source', 'Opening evidence.');
        $threadId = (int) $thread['thread_id'];
        $ids = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < 8; $i++) {
            $ids[] = $this->db->insert(
                'INSERT INTO posts (thread_id,user_id,body,body_html,is_op,is_anonymous,is_deleted,is_pending,created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$threadId, $author['id'], 'Evidence ' . $i, '<p>Evidence</p>', $this->now()->modify('-2 hours +' . $i . ' minutes')->format('Y-m-d H:i:s')],
            );
        }
        (new ThreadIntelligenceJobRepository($this->db))->upsertStale(
            $threadId,
            ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
            null,
            $this->now()->modify('-1 minute'),
        );
        return ['thread_id' => $threadId, 'board_id' => (int) $board['id'], 'author_id' => (int) $author['id'], 'post_ids' => $ids];
    }

    private function providerResult(ThreadIntelligenceRequest $request, string $id = 'resp_concurrency'): ThreadIntelligenceResult
    {
        $ids = array_map(static fn ($post): int => $post->postId, $request->posts);
        $first = $ids[0];
        $last = $ids[array_key_last($ids)];
        return new ThreadIntelligenceResult([
            'overview' => ['markdown' => 'The current evidence supports a concurrency-safe overview.', 'source_post_ids' => [$first, $last]],
            'key_points' => [
                ['markdown' => 'The first point is supported.', 'source_post_ids' => [$first]],
                ['markdown' => 'The second point is supported.', 'source_post_ids' => [$last]],
            ],
            'open_questions' => [['markdown' => 'One supported question remains.', 'source_post_ids' => [$last]]],
            'related_topics' => [],
        ], $id, ThreadIntelligenceResult::STATUS_COMPLETED, null, new ThreadIntelligenceUsage(100, 50, 5, 0));
    }

    /** @return array{worker:ThreadIntelligenceWorker,jobs:ThreadIntelligenceJobRepository,generations:ThreadIntelligenceGenerationRepository,budget:ThreadIntelligenceBudget,settings:ThreadIntelligenceSettings} */
    private function worker(Database $db, ThreadIntelligenceProvider $provider): array
    {
        $apiKey = 'sk-test-thread-intelligence';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $db,
        );
        $jobs = new ThreadIntelligenceJobRepository($db);
        $generations = new ThreadIntelligenceGenerationRepository($db);
        $budget = new ThreadIntelligenceBudget($db, $config);
        $flags = new FeatureFlags(new SettingRepository($db));
        $eligibility = new ThreadIntelligenceEligibility($db, $flags, $config, $settings, $budget, $jobs);
        $evidence = new ThreadIntelligenceEvidenceBuilder($db, new ThreadIntelligenceCandidateFinder($db), $config);
        $markdown = new Markdown(new HtmlSanitizer());
        $publisher = new ThreadIntelligencePublisher(
            $db,
            new ThreadRepository($db),
            $jobs,
            $generations,
            $evidence,
            $markdown,
            new ContentReferenceService(
                $db,
                new BoardRepository($db),
                new ThreadRepository($db),
                new PostRepository($db),
                new TagRepository($db),
                new BoardMemberRepository($db),
                new BoardPolicy(),
                true,
            ),
        );
        return [
            'worker' => new ThreadIntelligenceWorker(
                $db,
                $flags,
                $config,
                $settings,
                $budget,
                $jobs,
                $generations,
                new ThreadIntelligenceBoardSweep($db),
                $eligibility,
                $evidence,
                $provider,
                new ThreadIntelligenceOutputValidator($markdown),
                new FakeThreadIntelligenceOutputModerator(),
                $publisher,
            ),
            'jobs' => $jobs,
            'generations' => $generations,
            'budget' => $budget,
            'settings' => $settings,
        ];
    }

    public function test_active_leases_skip_and_expired_leases_are_recovered_by_another_connection(): void
    {
        $seed = $this->seedDueThread();
        $claimed = (new ThreadIntelligenceJobRepository($this->db))->claimDue(1, $this->now());
        self::assertCount(1, $claimed);
        $this->commitFixtures();

        $second = $this->secondDatabase();
        $provider = new ConcurrencyProvider(fn (ThreadIntelligenceRequest $request): ThreadIntelligenceResult => $this->providerResult($request));
        $bundle = $this->worker($second, $provider);
        self::assertSame(0, $bundle['worker']->run(1, 'active-skip')['processed']);
        self::assertSame(0, $provider->calls);

        $this->db->run(
            'UPDATE thread_intelligence_jobs SET lease_expires_at = ? WHERE thread_id = ?',
            [$this->now()->modify('-1 second')->format('Y-m-d H:i:s'), $seed['thread_id']],
        );
        self::assertSame(1, $bundle['worker']->run(1, 'expired-recover')['succeeded']);
        self::assertSame(1, $provider->calls);
    }

    public function test_a_second_worker_cannot_claim_or_publish_the_first_workers_generation(): void
    {
        $seed = $this->seedDueThread();
        $this->commitFixtures();
        $secondDb = $this->secondDatabase();
        $secondProvider = new ConcurrencyProvider(fn (ThreadIntelligenceRequest $request): ThreadIntelligenceResult => $this->providerResult($request, 'resp_second'));
        $secondWorker = $this->worker($secondDb, $secondProvider)['worker'];
        $firstProvider = new ConcurrencyProvider(function (ThreadIntelligenceRequest $request) use ($secondWorker): ThreadIntelligenceResult {
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(0, $secondWorker->run(1, 'competing-worker')['processed']);
            return $this->providerResult($request, 'resp_first');
        });

        $this->worker($this->db, $firstProvider)['worker']->run(1, 'owning-worker');

        self::assertSame(1, $firstProvider->calls);
        self::assertSame(0, $secondProvider->calls);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM thread_intelligence_generations WHERE thread_id = ? AND status = 'published'",
            [$seed['thread_id']],
        ));
    }

    public function test_newer_activity_committed_during_the_call_survives_and_stales_the_response(): void
    {
        $seed = $this->seedDueThread();
        $this->commitFixtures();
        $second = $this->secondDatabase();
        $provider = new ConcurrencyProvider(function (ThreadIntelligenceRequest $request) use ($second, $seed): ThreadIntelligenceResult {
            self::assertFalse($this->pdo->inTransaction());
            $second->run(
                'UPDATE thread_intelligence_jobs SET activity_version = activity_version + 1, updated_at = UTC_TIMESTAMP() WHERE thread_id = ?',
                [$seed['thread_id']],
            );
            return $this->providerResult($request);
        });

        $this->worker($this->db, $provider)['worker']->run(1, 'newer-activity');

        $job = (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id']);
        self::assertSame(2, (int) $job['activity_version']);
        self::assertSame('queued', $job['state']);
        self::assertSame('stale', $this->db->fetchValue(
            'SELECT status FROM thread_intelligence_generations WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'", [$seed['thread_id']]));
    }

    public function test_visibility_change_between_call_and_publish_never_leaks_output(): void
    {
        $seed = $this->seedDueThread();
        $this->commitFixtures();
        $second = $this->secondDatabase();
        $provider = new ConcurrencyProvider(function (ThreadIntelligenceRequest $request) use ($second, $seed): ThreadIntelligenceResult {
            self::assertFalse($this->pdo->inTransaction());
            $second->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$seed['board_id']]);
            return $this->providerResult($request);
        });

        $this->worker($this->db, $provider)['worker']->run(1, 'visibility-race');

        self::assertSame('stale', $this->db->fetchValue('SELECT status FROM thread_intelligence_generations WHERE thread_id = ?', [$seed['thread_id']]));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'", [$seed['thread_id']]));
    }

    public function test_abandoned_reserved_generation_is_settled_and_completed_after_owning_job_revalidation(): void
    {
        $seed = $this->seedDueThread();
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => 'sk-test-thread-intelligence']);
        $budget = new ThreadIntelligenceBudget($this->db, $config);
        $reservation = $budget->reserve($this->now());
        self::assertTrue($reservation['reserved']);
        $generations = new ThreadIntelligenceGenerationRepository($this->db);
        $generationId = $generations->start(['thread_id' => $seed['thread_id'], 'trigger_code' => 'post_created']);
        $generations->recordRequest($generationId, str_repeat('a', 64), $seed['post_ids'], [], str_repeat('b', 64), 500);
        $this->db->run(
            'UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?',
            [$this->now()->modify('-11 minutes')->format('Y-m-d H:i:s'), $generationId],
        );
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, lease_token = NULL, lease_expires_at = NULL WHERE thread_id = ?",
            [$seed['thread_id']],
        );
        $this->commitFixtures();

        $provider = new ConcurrencyProvider(fn (ThreadIntelligenceRequest $request): ThreadIntelligenceResult => $this->providerResult($request));
        $bundle = $this->worker($this->secondDatabase(), $provider);
        $bundle['worker']->run(1, 'crash-reconcile');

        $row = $this->db->fetch('SELECT status,failure_code FROM thread_intelligence_generations WHERE id = ?', [$generationId]);
        self::assertSame('failed', $row['status']);
        self::assertSame('worker_interrupted', $row['failure_code']);
        $status = $bundle['budget']->status($this->now());
        self::assertSame(0, $status['reserved_calls']);
        self::assertSame(1, $status['used_calls']);
    }

    public function test_older_heartbeat_run_id_cannot_overwrite_a_newer_run(): void
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => true, 'automated_context' => true]);
        $provider = new ConcurrencyProvider(fn (ThreadIntelligenceRequest $request): ThreadIntelligenceResult => $this->providerResult($request));
        $bundle = $this->worker($this->db, $provider);
        $old = $bundle['settings']->heartbeatStarted('old', $this->now()->modify('-2 minutes'));
        $new = $bundle['settings']->heartbeatStarted('new', $this->now()->modify('-1 minute'));

        $bundle['settings']->heartbeatFinished($old, 'error', ['processed' => 9, 'succeeded' => 0, 'failed' => 9], $this->now());
        self::assertSame($new, $bundle['settings']->heartbeat()['run_id']);
        self::assertSame('running', $bundle['settings']->heartbeat()['status']);
    }
}
