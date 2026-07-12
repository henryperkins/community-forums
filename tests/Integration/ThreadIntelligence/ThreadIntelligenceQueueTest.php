<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Tests\Support\TestCase;

final class ThreadIntelligenceQueueTest extends TestCase
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

    private function now(string $time = '2026-07-10 12:00:00', string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($timezone));
    }

    private function setFlags(bool $communityMemory = true, bool $automatedContext = true): void
    {
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => $communityMemory,
            'automated_context' => $automatedContext,
        ]);
    }

    /** @return array{queue:ThreadIntelligenceQueue,settings:ThreadIntelligenceSettings,jobs:ThreadIntelligenceJobRepository,eligibility:ThreadIntelligenceEligibility} */
    private function services(string $apiKey = 'sk-test-thread-intelligence', ?Database $database = null): array
    {
        $database ??= $this->db;
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($database),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $database,
        );
        $jobs = new ThreadIntelligenceJobRepository($database);
        $eligibility = new ThreadIntelligenceEligibility(
            $database,
            new FeatureFlags(new SettingRepository($database)),
            $config,
            $settings,
            new ThreadIntelligenceBudget($database, $config),
            $jobs,
        );

        return [
            'queue' => new ThreadIntelligenceQueue($database, $jobs, $eligibility),
            'settings' => $settings,
            'jobs' => $jobs,
            'eligibility' => $eligibility,
        ];
    }

    /** @return array{thread_id:int,board_id:int,author_id:int,post_ids:list<int>} */
    private function seedThread(int $postCount = 8, string $visibility = 'public'): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $thread = $this->makeThread($board, $author);
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->db->insert(
                'INSERT INTO posts
                    (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$threadId, (int) $author['id'], 'Post ' . $i, '<p>Post</p>', '2026-07-10 09:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00'],
            );
        }
        if ($visibility !== 'public') {
            $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', [$visibility, (int) $board['id']]);
        }
        return [
            'thread_id' => $threadId,
            'board_id' => (int) $board['id'],
            'author_id' => (int) $author['id'],
            'post_ids' => $postIds,
        ];
    }

    /** @return array{thread_id:int,board_id:int,author_id:int,post_ids:list<int>} */
    private function seedThreadOnBoard(int $boardId, int $postCount = 8): array
    {
        $author = $this->makeUser();
        $board = $this->boards()->find($boardId);
        $thread = $this->makeThread($board, $author);
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->db->insert(
                'INSERT INTO posts
                    (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$threadId, (int) $author['id'], 'Post ' . $i, '<p>Post</p>', '2026-07-10 10:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00'],
            );
        }
        return ['thread_id' => $threadId, 'board_id' => $boardId, 'author_id' => (int) $author['id'], 'post_ids' => $postIds];
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
        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $deadline = microtime(true) + 5.0;
        do {
            $info = $observer->fetchValue(
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
    private function waitForProcessOutput($stream, string $needle, string $output = ''): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            stream_set_blocking($stream, true);
            while (($line = fgets($stream)) !== false) {
                $output .= $line;
                if (str_contains($output, $needle)) {
                    stream_set_blocking($stream, false);
                    return $output;
                }
            }
            self::fail("Child output closed before {$needle}; received {$output}");
        }

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
    private function startSweepChild(): array
    {
        $code = <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$config = json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR);
$db = new App\Core\Database($config);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
echo 'READY ' . $db->fetchValue('SELECT CONNECTION_ID()') . "\n";
fflush(STDOUT);
(new App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep($db))->runBatch(
    1,
    new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC')),
);
echo "DONE\n";
PHP;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipes, null, [
            'RB_ROOT' => dirname(__DIR__, 3),
            'RB_CHILD_DB' => base64_encode(json_encode($GLOBALS['__RB_TEST_DBCONFIG'], JSON_THROW_ON_ERROR)),
        ] + getenv());
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [$process, $pipes];
    }

    /** @param array{thread_id:int,author_id:int} $seed @return array{0:resource,1:array<int,resource>} */
    private function startResumeChild(array $seed): array
    {
        $code = <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$dbConfig = json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR);
$db = new App\Core\Database($dbConfig);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$config = App\Service\ThreadIntelligence\ThreadIntelligenceConfig::fromArray([
    'api_key' => 'sk-test-thread-intelligence',
]);
$settings = new App\Service\ThreadIntelligence\ThreadIntelligenceSettings(
    new App\Repository\SettingRepository($db),
    $config,
    base64_decode((string) getenv('RB_APP_KEY')),
    'sk-test-thread-intelligence',
    $db,
);
$jobs = new App\Repository\ThreadIntelligenceJobRepository($db);
$eligibility = new App\Service\ThreadIntelligence\ThreadIntelligenceEligibility(
    $db,
    new App\Core\FeatureFlags(new App\Repository\SettingRepository($db)),
    $config,
    $settings,
    new App\Service\ThreadIntelligence\ThreadIntelligenceBudget($db, $config),
    $jobs,
);
echo 'READY ' . $db->fetchValue('SELECT CONNECTION_ID()') . "\n";
fflush(STDOUT);
(new App\Service\ThreadIntelligence\ThreadIntelligenceQueue($db, $jobs, $eligibility))->resumeAndRequeue(
    (int) getenv('RB_THREAD_ID'),
    (int) getenv('RB_ACTOR_ID'),
    new DateTimeImmutable('2026-07-10 12:02:00', new DateTimeZone('UTC')),
);
echo "DONE\n";
PHP;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipes, null, [
            'RB_ROOT' => dirname(__DIR__, 3),
            'RB_CHILD_DB' => base64_encode(json_encode($GLOBALS['__RB_TEST_DBCONFIG'], JSON_THROW_ON_ERROR)),
            'RB_APP_KEY' => base64_encode((string) $this->config->get('app.key')),
            'RB_THREAD_ID' => (string) $seed['thread_id'],
            'RB_ACTOR_ID' => (string) $seed['author_id'],
        ] + getenv());
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [$process, $pipes];
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private function finishChild($process, array $pipes, string $stdout): void
    {
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), "Child failed: {$stderr}; stdout: {$stdout}");
        self::assertStringNotContainsString('Deadlock', $stderr);
    }

    public function test_trigger_constants_are_complete_and_unknown_triggers_are_rejected(): void
    {
        $expected = [
            'post_created', 'post_approved', 'post_edited', 'wiki_edited', 'wiki_reverted',
            'post_deleted', 'post_restored', 'thread_moved', 'thread_split', 'thread_merged',
            'curator_refresh', 'reconcile', 'board_visibility_changed',
        ];
        self::assertSame($expected, ThreadIntelligenceQueue::TRIGGERS);

        $this->setFlags();
        $seed = $this->seedThread();
        $this->expectException(InvalidArgumentException::class);
        $this->services()['queue']->markStale($seed['thread_id'], 'unbounded_custom_trigger', null, $this->now());
    }

    public function test_repeated_meaningful_events_upsert_one_row_and_debounce_from_the_latest_event_in_utc(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();

        $bundle['queue']->markStale(
            $seed['thread_id'],
            ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
            null,
            $this->now('2026-07-10 08:00:00', 'America/New_York'),
        );
        $bundle['queue']->markStale(
            $seed['thread_id'],
            ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
            'latest activity',
            $this->now('2026-07-10 12:05:30'),
        );

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        $row = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame('queued', $row['state']);
        self::assertSame(2, (int) $row['activity_version']);
        self::assertSame('latest activity', $row['trigger_reason']);
        self::assertSame('2026-07-10 12:20:30', $row['due_at']);
    }

    public function test_reconciliation_triggers_or_the_flag_and_routine_activity_never_clears_it(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now()->modify('-1 minute'));
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'idle', due_at = NULL, last_processed_post_id = ?, reconcile_required = 0
             WHERE thread_id = ?",
            [$seed['post_ids'][7], $seed['thread_id']],
        );
        $reconcileTriggers = [
            ThreadIntelligenceQueue::TRIGGER_POST_APPROVED,
            ThreadIntelligenceQueue::TRIGGER_POST_EDITED,
            ThreadIntelligenceQueue::TRIGGER_WIKI_EDITED,
            ThreadIntelligenceQueue::TRIGGER_WIKI_REVERTED,
            ThreadIntelligenceQueue::TRIGGER_POST_DELETED,
            ThreadIntelligenceQueue::TRIGGER_POST_RESTORED,
            ThreadIntelligenceQueue::TRIGGER_THREAD_MOVED,
            ThreadIntelligenceQueue::TRIGGER_THREAD_SPLIT,
            ThreadIntelligenceQueue::TRIGGER_THREAD_MERGED,
            ThreadIntelligenceQueue::TRIGGER_RECONCILE,
            ThreadIntelligenceQueue::TRIGGER_BOARD_VISIBILITY_CHANGED,
        ];

        foreach ($reconcileTriggers as $index => $trigger) {
            $bundle['queue']->markStale($seed['thread_id'], $trigger, null, $this->now()->modify('+' . $index . ' seconds'));
            self::assertSame(1, (int) $bundle['jobs']->find($seed['thread_id'])['reconcile_required'], $trigger);
        }

        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now()->modify('+1 minute'));
        self::assertSame(1, (int) $bundle['jobs']->find($seed['thread_id'])['reconcile_required']);
    }

    public function test_paused_activity_updates_evidence_but_never_becomes_provider_due(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now());

        $before = $bundle['jobs']->find($seed['thread_id']);
        $bundle['queue']->markStale(
            $seed['thread_id'],
            ThreadIntelligenceQueue::TRIGGER_POST_EDITED,
            'body changed',
            $this->now()->modify('+5 minutes'),
        );

        $row = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame(1, (int) $row['automation_paused']);
        self::assertSame($seed['author_id'], (int) $row['paused_by']);
        self::assertSame('idle', $row['state']);
        self::assertNull($row['due_at']);
        self::assertSame((int) $before['activity_version'] + 1, (int) $row['activity_version']);
        self::assertSame('post_edited', $row['trigger_code']);
        self::assertSame('body changed', $row['trigger_reason']);
        self::assertSame(1, (int) $row['reconcile_required']);
        self::assertSame([], $bundle['jobs']->claimDue(1, $this->now()->modify('+1 day')));
    }

    public function test_ordinary_upsert_preserves_dead_review_required_and_active_running_lease_state(): void
    {
        $this->setFlags();
        $bundle = $this->services();

        foreach (['dead', 'review_required'] as $terminal) {
            $seed = $this->seedThread();
            $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
            $this->db->run(
                "UPDATE thread_intelligence_jobs SET state = ?, due_at = NULL, reconcile_required = 1 WHERE thread_id = ?",
                [$terminal, $seed['thread_id']],
            );
            $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now()->modify('+1 minute'));
            $row = $bundle['jobs']->find($seed['thread_id']);
            self::assertSame($terminal, $row['state']);
            self::assertNull($row['due_at']);
            self::assertSame(1, (int) $row['reconcile_required']);
        }

        $running = $this->seedThread();
        $bundle['queue']->markStale($running['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now()->modify('-20 minutes'));
        $claimed = $bundle['jobs']->claimDue(1, $this->now())[0];
        $before = $bundle['jobs']->find($running['thread_id']);

        $bundle['queue']->markStale($running['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_EDITED, null, $this->now()->modify('+1 minute'));
        $after = $bundle['jobs']->find($running['thread_id']);
        self::assertSame('running', $after['state']);
        self::assertSame($claimed['lease_token'], $after['lease_token']);
        self::assertSame($before['lease_expires_at'], $after['lease_expires_at']);
        self::assertSame($before['due_at'], $after['due_at']);
        self::assertSame((int) $before['activity_version'] + 1, (int) $after['activity_version']);
    }

    public function test_private_thread_activity_never_creates_due_work_and_invalidates_existing_work(): void
    {
        $this->setFlags();
        $private = $this->seedThread(8, 'private');
        $bundle = $this->services();

        $bundle['queue']->markStale($private['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        self::assertNull($bundle['jobs']->find($private['thread_id']), 'fresh private activity creates no provider work');

        $public = $this->seedThread();
        $bundle['queue']->markStale($public['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $before = $bundle['jobs']->find($public['thread_id']);
        $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', ['private', $public['board_id']]);
        $bundle['queue']->markStale($public['thread_id'], ThreadIntelligenceQueue::TRIGGER_THREAD_MOVED, null, $this->now()->modify('+1 minute'));
        $after = $bundle['jobs']->find($public['thread_id']);
        self::assertSame('idle', $after['state']);
        self::assertNull($after['due_at']);
        self::assertSame((int) $before['activity_version'] + 1, (int) $after['activity_version']);
        self::assertSame(1, (int) $after['reconcile_required']);
    }

    public function test_enqueue_visibility_check_serializes_with_a_concurrent_private_flip(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $this->useCommittedFixtures();

        // Keep the caller's canonical transaction open after enqueue. The
        // conditional write must retain a source-board read lock so an admin
        // cannot commit a private flip and finish its marker before this job is
        // visible to the subsequent sweep.
        $this->pdo->beginTransaction();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());

        $adminDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $adminDb->run('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $adminDb->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$seed['board_id']]);
            $this->pdo->rollBack();
            self::fail('a visibility flip must wait until the conditional enqueue commits');
        } catch (PDOException $e) {
            self::assertStringContainsString('lock', strtolower($e->getMessage()));
        }
        $this->pdo->commit();

        $adminDb->transaction(function () use ($adminDb, $seed): void {
            $adminDb->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$seed['board_id']]);
            (new ThreadIntelligenceBoardSweep($adminDb))->markVisibilityChanged($seed['board_id']);
        });
        (new ThreadIntelligenceBoardSweep($adminDb))->runBatch(250, $this->now());

        $row = (new ThreadIntelligenceJobRepository($adminDb))->find($seed['thread_id']);
        self::assertSame('idle', $row['state']);
        self::assertNull($row['due_at']);
    }

    public function test_same_board_different_thread_enqueues_share_the_visibility_lock(): void
    {
        $this->setFlags();
        $first = $this->seedThread();
        $second = $this->seedThreadOnBoard($first['board_id']);
        $firstBundle = $this->services();
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $firstBundle['queue']->markStale($first['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());

        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $otherDb->run('SET SESSION innodb_lock_wait_timeout = 1');
        $otherDb->pdo()->beginTransaction();
        try {
            $this->services(database: $otherDb)['queue']->markStale(
                $second['thread_id'],
                ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
                null,
                $this->now(),
            );
            $otherDb->pdo()->commit();
            $this->pdo->commit();
        } finally {
            if ($otherDb->pdo()->inTransaction()) {
                $otherDb->pdo()->rollBack();
            }
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        self::assertSame('queued', (new ThreadIntelligenceJobRepository($observer))->find($first['thread_id'])['state']);
        self::assertSame('queued', (new ThreadIntelligenceJobRepository($observer))->find($second['thread_id'])['state']);
    }

    public function test_mark_stale_rolls_back_transiently_instead_of_deadlocking_with_a_public_missing_job_sweep(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        (new ThreadIntelligenceBoardSweep($this->db))->markVisibilityChanged($seed['board_id']);
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $this->db->fetch('SELECT id FROM threads WHERE id = ? FOR UPDATE', [$seed['thread_id']]);
        [$process, $pipes] = $this->startSweepChild();
        $stdout = $this->waitForProcessOutput($pipes[1], 'READY');
        preg_match('/READY (\d+)/', $stdout, $matches);

        try {
            $this->waitForConnectionQuery((int) ($matches[1] ?? 0), 'thread_intelligence_jobs');
            try {
                $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
                self::fail('the canonical mutation must roll back transiently rather than wait in a lock cycle');
            } catch (\RuntimeException $e) {
                self::assertSame('thread visibility is busy; retry the canonical mutation', $e->getMessage());
            }
            $this->pdo->rollBack();
            $stdout = $this->waitForProcessOutput($pipes[1], 'DONE', $stdout);
            $this->finishChild($process, $pipes, $stdout);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        self::assertSame('board_visibility_changed', (new ThreadIntelligenceJobRepository($observer))->find($seed['thread_id'])['trigger_code']);
    }

    public function test_stale_private_decision_cannot_idle_a_completed_private_to_public_sweep(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$seed['board_id']]);
        $this->useCommittedFixtures();

        $adminDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $adminDb->pdo()->beginTransaction();
        $adminDb->run("UPDATE boards SET visibility = 'public' WHERE id = ?", [$seed['board_id']]);
        (new ThreadIntelligenceBoardSweep($adminDb))->markVisibilityChanged($seed['board_id']);

        $this->pdo->beginTransaction();
        try {
            $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_THREAD_MOVED, null, $this->now());
            self::fail('the stale private decision must not wait for and overwrite the later public sweep');
        } catch (\RuntimeException $e) {
            self::assertSame('thread visibility is busy; retry the canonical mutation', $e->getMessage());
        } finally {
            $this->pdo->rollBack();
        }

        $adminDb->pdo()->commit();
        (new ThreadIntelligenceBoardSweep($adminDb))->runBatch(250, $this->now());
        $row = (new ThreadIntelligenceJobRepository($adminDb))->find($seed['thread_id']);
        self::assertSame('queued', $row['state']);
        self::assertSame('board_visibility_changed', $row['trigger_code']);
        self::assertNotNull($row['due_at']);
    }

    public function test_request_refresh_queues_immediately_with_exact_success_feedback(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();

        $result = $bundle['queue']->requestRefresh($seed['thread_id'], $this->now());

        self::assertTrue($result->queued);
        self::assertSame('eligible', $result->code);
        self::assertSame('Refresh queued', $result->message);
        self::assertNull($result->nextEligibleAt);
        $row = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame('curator_refresh', $row['trigger_code']);
        self::assertSame('queued', $row['state']);
        self::assertSame('2026-07-10 12:00:00', $row['due_at'], 'explicit refresh bypasses the debounce quiet window');
    }

    public function test_explicit_refresh_force_intent_survives_claim_and_generation_recheck(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'idle', due_at = NULL, last_processed_post_id = ?
             WHERE thread_id = ?",
            [$seed['post_ids'][7], $seed['thread_id']],
        );

        self::assertTrue($bundle['queue']->requestRefresh($seed['thread_id'], $this->now())->queued);
        $claimed = $bundle['jobs']->claimDue(1, $this->now());
        self::assertCount(1, $claimed);
        self::assertSame('curator_refresh', $claimed[0]['trigger_code']);
        self::assertTrue(
            $bundle['eligibility']->forGeneration($claimed[0], $this->now())->eligible,
            'the worker recheck must honor the durable explicit-refresh trigger',
        );
    }

    public function test_request_refresh_hourly_denial_is_exact_and_does_not_mutate_the_job(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(13);
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $this->db->run(
            'UPDATE thread_intelligence_jobs SET last_processed_post_id = ?, last_generated_at = ? WHERE thread_id = ?',
            [$seed['post_ids'][7], '2026-07-10 12:30:00', $seed['thread_id']],
        );
        $before = $bundle['jobs']->find($seed['thread_id']);

        $result = $bundle['queue']->requestRefresh(
            $seed['thread_id'],
            $this->now('2026-07-10 09:00:00', 'America/New_York'),
        );

        self::assertFalse($result->queued);
        self::assertSame('hourly_limit', $result->code);
        self::assertSame('Refresh available after 2026-07-10 09:30:00 EDT', $result->message);
        self::assertSame('2026-07-10T13:30:00+00:00', $result->nextEligibleAt?->format(DATE_ATOM));
        self::assertSame($before, $bundle['jobs']->find($seed['thread_id']), 'a denied direct POST is nonmutating');
    }

    public function test_pause_and_resume_record_actor_metadata_and_requeue_current_state(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());

        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));
        $paused = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame(1, (int) $paused['automation_paused']);
        self::assertSame($seed['author_id'], (int) $paused['paused_by']);
        self::assertSame('2026-07-10 12:01:00', $paused['paused_at']);
        self::assertSame('idle', $paused['state']);
        self::assertNull($paused['due_at']);

        $bundle['queue']->resumeAndRequeue($seed['thread_id'], $seed['author_id'], $this->now()->modify('+2 minutes'));
        $resumed = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame(0, (int) $resumed['automation_paused']);
        self::assertNull($resumed['paused_by']);
        self::assertNull($resumed['paused_at']);
        self::assertSame('queued', $resumed['state']);
        self::assertSame('curator_refresh', $resumed['trigger_code']);
        self::assertSame('2026-07-10 12:02:00', $resumed['due_at']);
    }

    public function test_resume_rechecks_content_after_a_private_to_public_sweep(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$seed['board_id']]);
        $this->useCommittedFixtures();

        // Establish a repeatable-read snapshot while the board is private. A
        // completed admin flip and sweep must win over this stale snapshot when
        // resume evaluates the content under its current-visibility lock.
        $this->pdo->beginTransaction();
        self::assertSame(
            'private',
            $this->db->fetchValue('SELECT visibility FROM boards WHERE id = ?', [$seed['board_id']]),
        );

        $adminDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $adminDb->transaction(function () use ($adminDb, $seed): void {
            $adminDb->run("UPDATE boards SET visibility = 'public' WHERE id = ?", [$seed['board_id']]);
            (new ThreadIntelligenceBoardSweep($adminDb))->markVisibilityChanged($seed['board_id']);
        });
        (new ThreadIntelligenceBoardSweep($adminDb))->runBatch(250, $this->now());

        $bundle['queue']->resumeAndRequeue($seed['thread_id'], $seed['author_id'], $this->now()->modify('+2 minutes'));
        $this->pdo->commit();

        $row = (new ThreadIntelligenceJobRepository($adminDb))->find($seed['thread_id']);
        self::assertSame(0, (int) $row['automation_paused']);
        self::assertSame('queued', $row['state']);
        self::assertSame('curator_refresh', $row['trigger_code']);
        self::assertSame('2026-07-10 12:02:00', $row['due_at']);
    }

    public function test_resume_does_not_wait_on_an_open_canonical_post_write_before_the_job_update(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));
        $this->useCommittedFixtures();

        // Canonical content work owns its post row before the eventual queue
        // write. Resume must not hold the job and then wait for this post.
        $this->pdo->beginTransaction();
        $this->db->run('UPDATE posts SET body = ? WHERE id = ?', ['Edited while resume races', $seed['post_ids'][1]]);
        [$process, $pipes] = $this->startResumeChild($seed);
        $stdout = $this->waitForProcessOutput($pipes[1], 'READY');
        $childFinished = false;
        try {
            $stdout = $this->waitForProcessOutput($pipes[1], 'DONE', $stdout);
            $childFinished = true;
            self::assertTrue($this->pdo->inTransaction(), 'resume completed while the canonical post write remained open');

            $bundle['queue']->markStale(
                $seed['thread_id'],
                ThreadIntelligenceQueue::TRIGGER_POST_EDITED,
                null,
                $this->now()->modify('+3 minutes'),
            );
            $this->pdo->commit();
            $this->finishChild($process, $pipes, $stdout);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (!$childFinished) {
                $stdout = $this->waitForProcessOutput($pipes[1], 'DONE', $stdout);
                $this->finishChild($process, $pipes, $stdout);
            }
        }

        $row = (new ThreadIntelligenceJobRepository(new Database($GLOBALS['__RB_TEST_DBCONFIG'])))->find($seed['thread_id']);
        self::assertSame(0, (int) $row['automation_paused']);
        self::assertSame('queued', $row['state']);
        self::assertSame('post_edited', $row['trigger_code']);
        self::assertSame('2026-07-10 12:18:00', $row['due_at']);
    }

    public function test_pausing_an_active_running_job_does_not_mutate_its_lease(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now()->modify('-20 minutes'));
        $bundle['jobs']->claimDue(1, $this->now());
        $before = $bundle['jobs']->find($seed['thread_id']);

        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));
        $after = $bundle['jobs']->find($seed['thread_id']);

        self::assertSame('running', $after['state']);
        self::assertSame($before['lease_token'], $after['lease_token']);
        self::assertSame($before['lease_expires_at'], $after['lease_expires_at']);
        self::assertSame($before['due_at'], $after['due_at']);
        self::assertSame(1, (int) $after['automation_paused']);
    }

    public function test_resume_requeues_current_content_while_generation_remains_deferred_by_missing_credentials(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services(apiKey: '');
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));

        $bundle['queue']->resumeAndRequeue($seed['thread_id'], $seed['author_id'], $this->now()->modify('+2 minutes'));

        $row = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame(0, (int) $row['automation_paused']);
        self::assertSame('queued', $row['state'], 'resume must not strand already-eligible stale evidence as idle');
        self::assertSame('2026-07-10 12:02:00', $row['due_at']);
        self::assertSame(
            'credentials_missing',
            $bundle['eligibility']->forGeneration($row, $this->now()->modify('+2 minutes'))->code,
            'queueing current evidence does not bypass provider-time credential policy',
        );
    }

    public function test_resume_preserves_low_delta_intent_while_global_pause_and_hourly_cadence_defer_generation(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'idle', due_at = NULL, last_processed_post_id = ?
             WHERE thread_id = ?",
            [$seed['post_ids'][7], $seed['thread_id']],
        );
        $bundle['queue']->setAutomationPaused($seed['thread_id'], true, $seed['author_id'], $this->now()->modify('+1 minute'));
        $bundle['settings']->setGenerationPaused(true);

        $bundle['queue']->resumeAndRequeue($seed['thread_id'], $seed['author_id'], $this->now()->modify('+2 minutes'));

        $row = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame('queued', $row['state']);
        self::assertSame('curator_refresh', $row['trigger_code']);
        self::assertSame('generation_paused', $bundle['eligibility']->forGeneration($row, $this->now()->modify('+2 minutes'))->code);

        $bundle['settings']->setGenerationPaused(false);
        self::assertTrue(
            $bundle['eligibility']->forGeneration($row, $this->now()->modify('+2 minutes'))->eligible,
            'the durable curator trigger survives a low post delta after the operational pause clears',
        );

        $this->db->run(
            'UPDATE thread_intelligence_jobs SET last_generated_at = ? WHERE thread_id = ?',
            ['2026-07-10 11:30:00', $seed['thread_id']],
        );
        $hourly = $bundle['jobs']->find($seed['thread_id']);
        self::assertSame('hourly_limit', $bundle['eligibility']->forGeneration($hourly, $this->now())->code);
    }

    public function test_queue_writes_join_the_callers_canonical_transaction(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $bundle = $this->services();
        $this->useCommittedFixtures();
        $other = new Database($GLOBALS['__RB_TEST_DBCONFIG']);

        $this->pdo->beginTransaction();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        self::assertNull((new ThreadIntelligenceJobRepository($other))->find($seed['thread_id']), 'another connection cannot see the uncommitted queue write');
        $this->pdo->rollBack();
        self::assertNull((new ThreadIntelligenceJobRepository($other))->find($seed['thread_id']), 'caller rollback removes the queue write');

        $this->pdo->beginTransaction();
        $bundle['queue']->markStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $this->now());
        $this->pdo->commit();
        self::assertNotNull((new ThreadIntelligenceJobRepository($other))->find($seed['thread_id']), 'caller commit publishes content and queue state together');
    }
}
