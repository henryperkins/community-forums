<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\App;
use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\Request;
use App\Core\View;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceOperationsService;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceViewService;
use App\Support\Markdown;
use App\Worker\ThreadIntelligenceWorker;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\Support\TestCase;

#[Group('nonparallel')]
final class ThreadIntelligenceOperationsServiceTest extends TestCase
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

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $configOverrides */
    private function operations(array $configOverrides = []): array
    {
        $apiKey = (string) ($configOverrides['api_key'] ?? 'sk-test-operations-secret');
        $config = ThreadIntelligenceConfig::fromArray([
            'api_key' => $apiKey,
            'model' => $configOverrides['model'] ?? 'gpt-5.6-luna',
            'reasoning_effort' => $configOverrides['reasoning_effort'] ?? 'low',
            'daily_call_limit' => $configOverrides['daily_call_limit'] ?? 100,
            'daily_input_token_limit' => $configOverrides['daily_input_token_limit'] ?? 1_000_000,
        ]);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $generations = new ThreadIntelligenceGenerationRepository($this->db);
        $budget = new ThreadIntelligenceBudget($this->db, $config);
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $eligibility = new ThreadIntelligenceEligibility($this->db, $flags, $config, $settings, $budget, $jobs);
        $queue = new ThreadIntelligenceQueue($this->db, $jobs, $eligibility);
        $operations = new ThreadIntelligenceOperationsService(
            $this->db,
            $flags,
            $config,
            $settings,
            $budget,
            $eligibility,
            $queue,
            $jobs,
            $generations,
        );
        return compact('operations', 'settings', 'jobs', 'generations', 'budget', 'config');
    }

    /** @return array{thread_id:int,author_id:int,post_ids:list<int>} */
    private function seedThread(int $posts = 8): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $threadId = (int) $this->makeThread($board, $author)['thread_id'];
        $ids = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < $posts; $i++) {
            $ids[] = $this->db->insert(
                'INSERT INTO posts (thread_id,user_id,body,body_html,is_op,is_anonymous,is_deleted,is_pending,created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$threadId, $author['id'], 'Evidence ' . $i, '<p>Evidence</p>', $this->now()->modify('-2 hours +' . $i . ' minutes')->format('Y-m-d H:i:s')],
            );
        }
        return ['thread_id' => $threadId, 'author_id' => (int) $author['id'], 'post_ids' => $ids];
    }

    private function flags(bool $memory, bool $context): void
    {
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => $memory,
            'automated_context' => $context,
        ]);
    }

    public function test_status_is_complete_and_redacted_with_never_run_heartbeat_classification(): void
    {
        $this->flags(false, true);
        $bundle = $this->operations();
        $seed = $this->seedThread();
        $bundle['jobs']->upsertStale($seed['thread_id'], 'post_created', null, $this->now());

        $status = $bundle['operations']->status();

        self::assertSame(['community_memory' => false, 'automated_context' => true], $status['flags']);
        self::assertTrue($status['credential_ready']);
        self::assertSame(['paused' => false, 'corrupt' => false], $status['pause']);
        self::assertFalse($status['provider']['blocked']);
        self::assertSame('never_run', $status['heartbeat']['classification']);
        self::assertSame(1, $status['queue']['queued']);
        self::assertSame('gpt-5.6-luna', $status['model']);
        self::assertSame('low', $status['reasoning_effort']);
        self::assertSame('thread-intelligence-v1', $status['prompt_version']);
        self::assertArrayHasKey('used_calls', $status['budget']);

        $encoded = json_encode($status, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sk-test-operations-secret', $encoded);
        self::assertStringNotContainsString('fingerprint', $encoded);
        self::assertStringNotContainsString((string) $this->config->get('app.key'), $encoded);
    }

    public function test_heartbeat_classification_distinguishes_stale_interrupted_error_and_healthy(): void
    {
        $this->flags(true, true);
        $bundle = $this->operations();

        $run = $bundle['settings']->heartbeatStarted('old-ok', $this->now()->modify('-8 minutes'));
        $bundle['settings']->heartbeatFinished($run, 'ok', ['processed' => 1, 'succeeded' => 1, 'failed' => 0], $this->now()->modify('-7 minutes'));
        self::assertSame('stale', $bundle['operations']->status()['heartbeat']['classification']);

        $bundle['settings']->heartbeatStarted('stuck', $this->now()->modify('-11 minutes'));
        self::assertSame('interrupted', $bundle['operations']->status()['heartbeat']['classification']);

        $run = $bundle['settings']->heartbeatStarted('error', $this->now()->modify('-1 minute'));
        $bundle['settings']->heartbeatFinished($run, 'error', ['processed' => 1, 'succeeded' => 0, 'failed' => 1], $this->now());
        self::assertSame('attention', $bundle['operations']->status()['heartbeat']['classification']);

        $run = $bundle['settings']->heartbeatStarted('healthy', $this->now()->modify('-1 minute'));
        $bundle['settings']->heartbeatFinished($run, 'ok', ['processed' => 0, 'succeeded' => 0, 'failed' => 0], $this->now());
        self::assertSame('healthy', $bundle['operations']->status()['heartbeat']['classification']);
    }

    public function test_retry_and_reconcile_recover_terminal_work_but_honor_every_shared_gate(): void
    {
        $seed = $this->seedThread();
        $this->flags(false, true);
        $bundle = $this->operations();
        $bundle['jobs']->upsertStale($seed['thread_id'], 'post_created', null, $this->now());
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'review_required', due_at = NULL, attempt_count = 3, last_error_code = 'schema_invalid' WHERE thread_id = ?",
            [$seed['thread_id']],
        );

        $denied = $bundle['operations']->retry($seed['thread_id']);
        self::assertFalse($denied->queued);
        self::assertSame('community_memory_disabled', $denied->code);
        self::assertSame('review_required', $bundle['jobs']->find($seed['thread_id'])['state']);
        self::assertSame(3, (int) $bundle['jobs']->find($seed['thread_id'])['attempt_count']);

        $this->flags(true, true);
        $bundle = $this->operations();
        $retried = $bundle['operations']->retry($seed['thread_id']);
        self::assertTrue($retried->queued);
        self::assertSame('queued', $bundle['jobs']->find($seed['thread_id'])['state']);
        self::assertSame(0, (int) $bundle['jobs']->find($seed['thread_id'])['attempt_count']);

        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'dead', due_at = NULL WHERE thread_id = ?", [$seed['thread_id']]);
        $reconciled = $bundle['operations']->reconcile($seed['thread_id']);
        self::assertTrue($reconciled->queued);
        $job = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame('queued', $job['state']);
        self::assertSame(1, (int) $job['reconcile_required']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_RECONCILE, $job['trigger_code']);
    }

    public function test_retry_returns_exact_next_time_feedback_without_mutating_hourly_limited_work(): void
    {
        $this->flags(true, true);
        $seed = $this->seedThread();
        $bundle = $this->operations();
        $bundle['jobs']->upsertStale($seed['thread_id'], 'post_created', null, $this->now());
        $last = $this->now()->modify('-30 minutes')->format('Y-m-d H:i:s');
        $this->db->run('UPDATE thread_intelligence_jobs SET state = ?, last_generated_at = ? WHERE thread_id = ?', [
            'review_required', $last, $seed['thread_id'],
        ]);

        $result = $bundle['operations']->retry($seed['thread_id']);

        self::assertFalse($result->queued);
        self::assertSame('hourly_limit', $result->code);
        self::assertSame($this->now()->modify('+30 minutes')->format('Y-m-d H:i'), $result->nextEligibleAt?->format('Y-m-d H:i'));
        self::assertSame('review_required', $bundle['jobs']->find($seed['thread_id'])['state']);
    }

    public function test_pruning_ignores_flags_credentials_provider_latch_and_global_pause(): void
    {
        $this->flags(false, false);
        $seed = $this->seedThread();
        $bundle = $this->operations(['api_key' => '']);
        $bundle['settings']->setGenerationPaused(true);
        $bundle['settings']->blockProvider('authentication', $this->now());
        $generationId = $bundle['generations']->start(['thread_id' => $seed['thread_id'], 'trigger_code' => 'post_created']);
        $bundle['generations']->complete($generationId, ['status' => 'failed', 'failure_code' => 'transport']);
        $this->db->run(
            'UPDATE thread_intelligence_generations SET completed_at = ? WHERE id = ?',
            [$this->now()->modify('-91 days')->format('Y-m-d H:i:s'), $generationId],
        );

        self::assertSame(1, $bundle['operations']->pruneEvidence(500));
        self::assertNull($this->db->fetch('SELECT id FROM thread_intelligence_generations WHERE id = ?', [$generationId]));
    }

    public function test_clear_provider_latch_never_accepts_or_returns_a_credential(): void
    {
        $this->flags(true, true);
        $bundle = $this->operations();
        $bundle['settings']->blockProvider('invalid_model', $this->now());
        self::assertTrue($bundle['settings']->providerHealth()['blocked']);

        $bundle['operations']->clearProviderLatch();

        self::assertFalse($bundle['settings']->providerHealth()['blocked']);
    }

    public function test_app_binds_lazy_singletons_and_injects_the_shared_configured_markdown_into_validation(): void
    {
        $app = new App(
            $this->config,
            $this->db,
            $this->rateLimiter,
            null,
            new FakeThreadIntelligenceProvider(),
            new FakeThreadIntelligenceOutputModerator(),
        );
        $method = (new ReflectionClass($app))->getMethod('buildContainer');
        $container = $method->invoke($app, new Request('GET', '/', [], [], [], []));

        self::assertSame($container->get(ThreadIntelligenceWorker::class), $container->get(ThreadIntelligenceWorker::class));
        self::assertSame($container->get(ThreadIntelligenceOperationsService::class), $container->get(ThreadIntelligenceOperationsService::class));
        $validator = $container->get(ThreadIntelligenceOutputValidator::class);
        $property = (new ReflectionClass($validator))->getProperty('markdown');
        self::assertSame($container->get(Markdown::class), $property->getValue($validator));
    }

    public function test_console_help_lists_all_five_safe_operations_commands(): void
    {
        $output = shell_exec(PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/bin/console') . ' help');
        self::assertIsString($output);
        foreach ([
            'worker:thread-intelligence [limit]',
            'thread-intelligence:status',
            'thread-intelligence:retry <thread-id>',
            'thread-intelligence:reconcile <thread-id>',
            'thread-intelligence:prune-evidence [limit]',
        ] as $command) {
            self::assertStringContainsString($command, $output);
        }
    }

    public function test_data_preserving_runtime_rollback_sequence(): void
    {
        $settingsRepository = new SettingRepository($this->db);
        $settingsRepository->set('features', [
            'search' => false,
            'community_memory' => true,
            'automated_context' => true,
        ]);

        $seed = $this->seedThread();
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, reviewer_id,
                 parent_summary_id, published_at, created_at)
             VALUES (?, 'ai', 'published', ?, ?, 1, NULL, NULL, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], 'Retained rollback living brief', '<p>Retained rollback living brief</p>'],
        );
        $this->db->run(
            'INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
            [$summaryId, $seed['post_ids'][0]],
        );
        $generationId = $this->db->insert(
            "INSERT INTO thread_intelligence_generations
                (thread_id, trigger_code, status, published_summary_id, source_post_ids,
                 requested_at, completed_at, published_at)
             VALUES (?, 'post_created', 'published', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], $summaryId, json_encode([$seed['post_ids'][0]], JSON_THROW_ON_ERROR)],
        );

        $bundle = $this->operations(['api_key' => 'test-thread-intelligence-key']);
        $bundle['jobs']->upsertStale($seed['thread_id'], 'post_created', null, $this->now());
        $provider = new FakeThreadIntelligenceProvider();
        $moderator = new FakeThreadIntelligenceOutputModerator();

        $runtime = function (string $apiKey) use ($provider, $moderator): array {
            $items = $this->config->all();
            $items['thread_intelligence']['api_key'] = $apiKey;
            $config = new Config($items);
            $app = new App($config, $this->db, $this->rateLimiter, null, $provider, $moderator);
            $method = (new ReflectionClass($app))->getMethod('buildContainer');
            $container = $method->invoke($app, new Request('GET', '/', [], [], [], []));
            return [$config, $app, $container];
        };
        $rowIds = fn (): array => [
            'summaries' => array_map(
                'intval',
                array_column($this->db->fetchAll(
                    'SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY id',
                    [$seed['thread_id']],
                ), 'id'),
            ),
            'generations' => array_map(
                'intval',
                array_column($this->db->fetchAll(
                    'SELECT id FROM thread_intelligence_generations WHERE thread_id = ? ORDER BY id',
                    [$seed['thread_id']],
                ), 'id'),
            ),
            'jobs' => array_map(
                'intval',
                array_column($this->db->fetchAll(
                    'SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? ORDER BY thread_id',
                    [$seed['thread_id']],
                ), 'thread_id'),
            ),
        ];

        $expectedIds = [
            'summaries' => [$summaryId],
            'generations' => [$generationId],
            'jobs' => [$seed['thread_id']],
        ];
        self::assertSame($expectedIds, $rowIds());
        $this->useCommittedFixtures();
        $assertPreserved = function (string $label) use ($provider, $rowIds, $expectedIds): void {
            self::assertSame(0, $provider->callCount(), "$label must make zero provider calls");
            self::assertSame($expectedIds, $rowIds(), "$label must preserve lifecycle row counts and IDs");
        };

        $bundle['settings']->setGenerationPaused(true);
        [, , $paused] = $runtime('test-thread-intelligence-key');
        self::assertSame(
            ['processed' => 0, 'succeeded' => 0, 'failed' => 0],
            $paused->get(ThreadIntelligenceWorker::class)->run(1, 'rollback-paused'),
        );
        self::assertTrue($paused->get(ThreadIntelligenceOperationsService::class)->status()['pause']['paused']);
        $assertPreserved('global pause');

        $overrides = $settingsRepository->get('features', []);
        self::assertIsArray($overrides);
        $overrides['automated_context'] = false;
        $settingsRepository->set('features', $overrides);
        [, , $contextDisabled] = $runtime('test-thread-intelligence-key');
        self::assertSame(
            ['processed' => 0, 'succeeded' => 0, 'failed' => 0],
            $contextDisabled->get(ThreadIntelligenceWorker::class)->run(1, 'rollback-context-disabled'),
        );
        self::assertFalse($contextDisabled->get(ThreadIntelligenceOperationsService::class)->status()['flags']['automated_context']);
        $assertPreserved('automated_context rollback pin');

        $overrides['community_memory'] = false;
        $settingsRepository->set('features', $overrides);
        [, , $memoryDisabled] = $runtime('test-thread-intelligence-key');
        self::assertSame(
            ['processed' => 0, 'succeeded' => 0, 'failed' => 0],
            $memoryDisabled->get(ThreadIntelligenceWorker::class)->run(1, 'rollback-memory-disabled'),
        );
        self::assertFalse($memoryDisabled->get(ThreadIntelligenceOperationsService::class)->status()['flags']['community_memory']);
        $assertPreserved('community_memory rollback pin');

        [, , $credentialRemoved] = $runtime('');
        self::assertSame(
            ['processed' => 0, 'succeeded' => 0, 'failed' => 0],
            $credentialRemoved->get(ThreadIntelligenceWorker::class)->run(1, 'rollback-credential-empty'),
        );
        self::assertFalse($credentialRemoved->get(ThreadIntelligenceOperationsService::class)->status()['credential_ready']);
        $assertPreserved('empty provider credential');

        [$restoredConfig, , $restored] = $runtime('test-thread-intelligence-key');
        $restoredOverrides = $settingsRepository->get('features', []);
        self::assertIsArray($restoredOverrides);
        unset($restoredOverrides['automated_context'], $restoredOverrides['community_memory']);
        $settingsRepository->set('features', $restoredOverrides);
        $bundle['settings']->setGenerationPaused(false);
        self::assertSame(['search' => false], $settingsRepository->get('features'));
        self::assertSame(['paused' => false, 'corrupt' => false], $bundle['settings']->generationPause());
        $assertPreserved('runtime restore before read');

        $model = $restored->get(ThreadIntelligenceViewService::class)->forThread($seed['thread_id'], null);
        self::assertSame($summaryId, $model['living_brief']['id']);
        self::assertSame(1, $model['living_brief']['version']);
        $sources = array_map(static function (array $source): array {
            $source['url'] = '/t/' . (int) $source['thread_id'] . '#p' . (int) $source['id'];
            return $source;
        }, $model['sources']);
        $html = (new View((string) $restoredConfig->get('paths.templates')))->partial(
            'partials/living_brief',
            [
                'living_brief' => $model['living_brief'],
                'living_brief_sources' => $sources,
                'living_brief_related' => [],
            ],
        );
        self::assertStringContainsString('Retained rollback living brief', $html);
        self::assertStringContainsString('Version 1', $html);
        self::assertStringContainsString('Post #' . $seed['post_ids'][0], $html);
        $assertPreserved('restored read and render without replacement');
    }
}
