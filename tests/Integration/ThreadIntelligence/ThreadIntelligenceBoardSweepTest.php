<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PDOException;
use Tests\Support\TestCase;

final class ThreadIntelligenceBoardSweepTest extends TestCase
{
    private bool $committedFixtures = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Board sweeps own a top-level transaction by contract. End the test
        // harness transaction so every ordinary runBatch() exercises a real
        // begin/commit; tearDown performs committed-fixture cleanup.
        $this->pdo->commit();
        $this->committedFixtures = true;
    }

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

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    /** @return array{board_id:int,author_id:int,thread_ids:list<int>} */
    private function seedBoard(int $threadCount, string $visibility = 'public'): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => $visibility]);
        $threadIds = [];
        for ($i = 0; $i < $threadCount; $i++) {
            $threadIds[] = $this->db->insert(
                'INSERT INTO threads
                    (board_id, user_id, title, slug, is_deleted, is_pending, created_at, last_post_at)
                 VALUES (?, ?, ?, ?, 0, 0, ?, ?)',
                [
                    (int) $board['id'],
                    (int) $author['id'],
                    'Sweep thread ' . $i,
                    'sweep-' . bin2hex(random_bytes(8)),
                    '2026-07-10 09:00:00',
                    '2026-07-10 09:00:00',
                ],
            );
        }
        return ['board_id' => (int) $board['id'], 'author_id' => (int) $author['id'], 'thread_ids' => $threadIds];
    }

    private function useCommittedFixtures(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->committedFixtures = true;
    }

    private function cursor(int $boardId, ?Database $db = null): mixed
    {
        return ($db ?? $this->db)->fetchValue(
            'SELECT thread_intelligence_sweep_after_id FROM boards WHERE id = ?',
            [$boardId],
        );
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
    private function waitForProcessOutput($stream, string $needle, string $output = ''): string
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
    private function startChild(string $code, array $extraEnv = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = $extraEnv + [
            'RB_ROOT' => dirname(__DIR__, 3),
            'RB_CHILD_DB' => base64_encode(json_encode($GLOBALS['__RB_TEST_DBCONFIG'], JSON_THROW_ON_ERROR)),
        ];
        $process = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipes, null, $env);
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [$process, $pipes];
    }

    /** @param resource $process @param array<int,resource> $pipes */
    private function finishChild($process, array $pipes, string $stdout = ''): string
    {
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, "Child failed: {$stderr}; stdout: {$stdout}");
        self::assertStringNotContainsString('Deadlock', $stderr);
        return $stdout;
    }

    private function childBootstrap(): string
    {
        return <<<'PHP'
require getenv('RB_ROOT') . '/vendor/autoload.php';
$config = json_decode(base64_decode((string) getenv('RB_CHILD_DB')), true, 512, JSON_THROW_ON_ERROR);
$db = new App\Core\Database($config);
$db->run('SET SESSION innodb_lock_wait_timeout = 10');
$connectionId = (int) $db->fetchValue('SELECT CONNECTION_ID()');
echo "READY {$connectionId}\n";
flush();
PHP;
    }

    public function test_marker_resets_only_the_named_board_cursor_to_zero(): void
    {
        $first = $this->seedBoard(1);
        $second = $this->seedBoard(1);
        $this->db->run('UPDATE boards SET thread_intelligence_sweep_after_id = 99 WHERE id IN (?, ?)', [$first['board_id'], $second['board_id']]);

        (new ThreadIntelligenceBoardSweep($this->db))->markVisibilityChanged($first['board_id']);

        self::assertSame(0, (int) $this->cursor($first['board_id']));
        self::assertSame(99, (int) $this->cursor($second['board_id']));
    }

    public function test_run_batch_refuses_a_caller_owned_transaction_before_taking_any_lock(): void
    {
        $seed = $this->seedBoard(1);
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($seed['board_id']);
        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);

        $this->pdo->beginTransaction();
        try {
            $sweep->runBatch(250, $this->now());
            self::fail('runBatch must own and close the exceptional board-to-jobs transaction');
        } catch (LogicException $e) {
            self::assertSame('board sweep requires a top-level transaction boundary', $e->getMessage());
        } finally {
            $this->pdo->rollBack();
        }

        self::assertSame(0, (int) $this->cursor($seed['board_id'], $observer));
        self::assertSame(0, (int) $observer->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs'));
    }

    public function test_public_sweep_reads_251_processes_250_advances_and_finishes_without_duplicates(): void
    {
        $seed = $this->seedBoard(503);
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($seed['board_id']);

        $first = $sweep->runBatch(250, $this->now());
        self::assertSame([
            'board_id' => $seed['board_id'],
            'visibility' => 'public',
            'processed' => 250,
            'cursor' => $seed['thread_ids'][249],
            'complete' => false,
        ], $first);
        self::assertSame($seed['thread_ids'][249], (int) $this->cursor($seed['board_id']));
        self::assertSame(250, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_intelligence_jobs WHERE thread_id IN (' . implode(',', array_map('intval', $seed['thread_ids'])) . ')',
        ));
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_ids'][250]],
        ), 'the 251st lookahead ID is not processed');

        $second = $sweep->runBatch(250, $this->now());
        self::assertSame(250, $second['processed']);
        self::assertSame($seed['thread_ids'][499], $second['cursor']);
        self::assertFalse($second['complete']);

        $final = $sweep->runBatch(250, $this->now());
        self::assertSame(3, $final['processed']);
        self::assertNull($final['cursor']);
        self::assertTrue($final['complete']);
        self::assertNull($this->cursor($seed['board_id']));
        self::assertSame(503, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs'));
        self::assertSame(503, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM thread_intelligence_jobs
             WHERE trigger_code = 'board_visibility_changed' AND state = 'queued'
               AND due_at = '2026-07-10 12:15:00' AND reconcile_required = 1 AND activity_version = 1",
        ));
        self::assertSame([], $sweep->runBatch(250, $this->now()));
    }

    public function test_limit_is_hard_capped_at_250_and_nonpositive_limits_clamp_to_one(): void
    {
        $capped = $this->seedBoard(503);
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($capped['board_id']);
        self::assertSame(250, $sweep->runBatch(999, $this->now())['processed']);
        self::assertSame($capped['thread_ids'][249], (int) $this->cursor($capped['board_id']));
        $this->db->run(
            'UPDATE boards SET thread_intelligence_sweep_after_id = NULL WHERE id = ?',
            [$capped['board_id']],
        );

        $one = $this->seedBoard(3);
        $sweep->markVisibilityChanged($one['board_id']);
        self::assertSame(1, $sweep->runBatch(0, $this->now())['processed']);
        self::assertSame($one['thread_ids'][0], (int) $this->cursor($one['board_id']));
    }

    public function test_exactly_250_completes_but_a_251st_lookahead_keeps_the_cursor_open(): void
    {
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $exact = $this->seedBoard(250);
        $sweep->markVisibilityChanged($exact['board_id']);
        $exactResult = $sweep->runBatch(250, $this->now());
        self::assertSame(250, $exactResult['processed']);
        self::assertTrue($exactResult['complete']);
        self::assertNull($exactResult['cursor']);

        $lookahead = $this->seedBoard(251);
        $sweep->markVisibilityChanged($lookahead['board_id']);
        $first = $sweep->runBatch(250, $this->now());
        self::assertSame(250, $first['processed']);
        self::assertFalse($first['complete']);
        self::assertSame($lookahead['thread_ids'][249], $first['cursor']);
        self::assertSame(1, $sweep->runBatch(250, $this->now())['processed']);
        self::assertNull($this->cursor($lookahead['board_id']));
    }

    public function test_public_sweep_preserves_pause_terminal_states_and_an_active_running_lease(): void
    {
        $seed = $this->seedBoard(4);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        foreach ($seed['thread_ids'] as $threadId) {
            $jobs->upsertStale($threadId, 'post_created', null, $this->now()->modify('-1 hour'));
        }
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, automation_paused = 1 WHERE thread_id = ?", [$seed['thread_ids'][0]]);
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'dead', due_at = NULL WHERE thread_id = ?", [$seed['thread_ids'][1]]);
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'review_required', due_at = NULL WHERE thread_id = ?", [$seed['thread_ids'][2]]);
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET state = 'running', lease_token = ?, lease_expires_at = ?, due_at = ? WHERE thread_id = ?",
            [str_repeat('a', 64), '2026-07-10 12:10:00', '2026-07-10 11:00:00', $seed['thread_ids'][3]],
        );
        $runningBefore = $jobs->find($seed['thread_ids'][3]);

        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($seed['board_id']);
        $sweep->runBatch(250, $this->now());

        $paused = $jobs->find($seed['thread_ids'][0]);
        self::assertSame('idle', $paused['state']);
        self::assertNull($paused['due_at']);
        self::assertSame(1, (int) $paused['automation_paused']);
        self::assertSame('dead', $jobs->find($seed['thread_ids'][1])['state']);
        self::assertSame('review_required', $jobs->find($seed['thread_ids'][2])['state']);
        $runningAfter = $jobs->find($seed['thread_ids'][3]);
        self::assertSame('running', $runningAfter['state']);
        self::assertSame($runningBefore['lease_token'], $runningAfter['lease_token']);
        self::assertSame($runningBefore['lease_expires_at'], $runningAfter['lease_expires_at']);
        self::assertSame($runningBefore['due_at'], $runningAfter['due_at']);
    }

    public function test_private_and_hidden_sweeps_change_only_queued_and_retry_jobs_to_idle(): void
    {
        foreach (['private', 'hidden'] as $visibility) {
            $seed = $this->seedBoard(6, $visibility);
            $jobs = new ThreadIntelligenceJobRepository($this->db);
            foreach ($seed['thread_ids'] as $threadId) {
                $jobs->upsertStale($threadId, 'post_created', null, $this->now());
            }
            $states = ['queued', 'retry', 'idle', 'running', 'dead', 'review_required'];
            foreach ($states as $index => $state) {
                $lease = $state === 'running' ? str_repeat('b', 64) : null;
                $expiry = $state === 'running' ? '2026-07-10 12:10:00' : null;
                $this->db->run(
                    'UPDATE thread_intelligence_jobs SET state = ?, lease_token = ?, lease_expires_at = ? WHERE thread_id = ?',
                    [$state, $lease, $expiry, $seed['thread_ids'][$index]],
                );
            }
            $before = [];
            foreach ($seed['thread_ids'] as $threadId) {
                $before[$threadId] = $jobs->find($threadId);
            }

            $sweep = new ThreadIntelligenceBoardSweep($this->db);
            $sweep->markVisibilityChanged($seed['board_id']);
            $sweep->runBatch(250, $this->now());

            foreach ($seed['thread_ids'] as $index => $threadId) {
                $after = $jobs->find($threadId);
                if (in_array($states[$index], ['queued', 'retry'], true)) {
                    self::assertSame('idle', $after['state'], $visibility . ' ' . $states[$index]);
                    self::assertNull($after['due_at']);
                    continue;
                }
                self::assertSame($before[$threadId], $after, $visibility . ' must not mutate ' . $states[$index]);
            }
        }
    }

    public function test_locked_first_board_is_skipped_and_one_other_marked_board_is_processed(): void
    {
        $first = $this->seedBoard(1);
        $second = $this->seedBoard(1);
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($first['board_id']);
        $sweep->markVisibilityChanged($second['board_id']);
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $this->db->fetch('SELECT id FROM boards WHERE id = ? FOR UPDATE', [$first['board_id']]);

        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $result = (new ThreadIntelligenceBoardSweep($otherDb))->runBatch(250, $this->now());
        self::assertSame($second['board_id'], $result['board_id']);
        self::assertNull($this->cursor($second['board_id'], $otherDb));
        self::assertSame(0, (int) $this->cursor($first['board_id'], $otherDb));
        $this->pdo->rollBack();
    }

    public function test_interrupted_transaction_rolls_back_cursor_and_jobs_then_resumes_without_skip_or_duplicate(): void
    {
        $seed = $this->seedBoard(503);
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $jobs->upsertStale($seed['thread_ids'][0], 'post_created', null, $this->now());
        $sweep->markVisibilityChanged($seed['board_id']);
        $this->useCommittedFixtures();

        // Force a real mid-batch database interruption: the sweep owns its
        // board transaction, reaches the first job, then times out on a lock.
        $this->pdo->beginTransaction();
        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->fetch(
            'SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE',
            [$seed['thread_ids'][0]],
        );
        $otherDb->run('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            (new ThreadIntelligenceBoardSweep($otherDb))->runBatch(250, $this->now());
            self::fail('the injected job lock must interrupt and roll back the sweep transaction');
        } catch (PDOException $e) {
            self::assertStringContainsString('lock', strtolower($e->getMessage()));
        }

        self::assertSame(0, (int) $this->cursor($seed['board_id'], $otherDb));
        self::assertSame(1, (int) $otherDb->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs'));
        self::assertSame(1, (int) $otherDb->fetchValue(
            'SELECT activity_version FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_ids'][0]],
        ));
        $this->pdo->rollBack();

        $resumed = (new ThreadIntelligenceBoardSweep($otherDb))->runBatch(250, $this->now());
        self::assertSame($seed['thread_ids'][249], $resumed['cursor']);
        self::assertSame(250, (int) $otherDb->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs'));
        self::assertSame(249, (int) $otherDb->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs WHERE activity_version = 1'));
        self::assertSame(2, (int) $otherDb->fetchValue(
            'SELECT activity_version FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_ids'][0]],
        ));
    }

    public function test_a_completed_batch_commits_before_the_caller_can_begin_normal_work(): void
    {
        $seed = $this->seedBoard(2);
        (new ThreadIntelligenceBoardSweep($this->db))->markVisibilityChanged($seed['board_id']);
        $this->useCommittedFixtures();

        $workerDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        (new ThreadIntelligenceBoardSweep($workerDb))->runBatch(250, $this->now());

        $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        self::assertNull($this->cursor($seed['board_id'], $observer));
        self::assertSame(2, (int) $observer->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs'));
        self::assertFalse($workerDb->pdo()->inTransaction(), 'the exceptional boards-to-jobs transaction is closed before any normal claim');
    }

    public function test_visibility_flip_waits_then_resets_cursor_and_next_sweep_uses_new_visibility(): void
    {
        $seed = $this->seedBoard(503, 'public');
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        foreach (array_slice($seed['thread_ids'], 0, 250) as $threadId) {
            $jobs->upsertStale($threadId, 'post_created', null, $this->now());
        }
        $sweep->markVisibilityChanged($seed['board_id']);
        $this->useCommittedFixtures();
        $advanced = $sweep->runBatch(250, $this->now());
        self::assertSame($seed['thread_ids'][249], $advanced['cursor']);
        self::assertSame($seed['thread_ids'][249], (int) $this->cursor($seed['board_id']));

        // Hold the exact sweep board claim while the later administrator flip
        // waits. The admin commits after this owner releases and resets to 0.
        $this->pdo->beginTransaction();
        $locked = $this->db->fetch(<<<'SQL'
            SELECT id, visibility, thread_intelligence_sweep_after_id
            FROM boards
            WHERE thread_intelligence_sweep_after_id IS NOT NULL
            ORDER BY id
            LIMIT 1
            FOR UPDATE SKIP LOCKED
            SQL);
        self::assertSame($seed['board_id'], (int) $locked['id']);

        $code = $this->childBootstrap() . <<<'PHP'
$boardId = (int) getenv('RB_BOARD_ID');
$db->transaction(function () use ($db, $boardId): void {
    $db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$boardId]);
    (new App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep($db))->markVisibilityChanged($boardId);
});
echo "DONE\n";
PHP;
        [$process, $pipes] = $this->startChild($code, ['RB_BOARD_ID' => (string) $seed['board_id']]);
        $stdout = $this->waitForProcessOutput($pipes[1], 'READY');
        preg_match('/READY (\d+)/', $stdout, $matches);
        $connectionId = (int) ($matches[1] ?? 0);

        try {
            $this->waitForConnectionQuery($connectionId, 'UPDATE boards');
            $this->pdo->commit();
            $stdout = $this->waitForProcessOutput($pipes[1], 'DONE', $stdout);
            $this->finishChild($process, $pipes, $stdout);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $workerDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        self::assertSame('private', $workerDb->fetchValue('SELECT visibility FROM boards WHERE id = ?', [$seed['board_id']]));
        self::assertSame(0, (int) $this->cursor($seed['board_id'], $workerDb));

        $result = (new ThreadIntelligenceBoardSweep($workerDb))->runBatch(250, $this->now());
        self::assertSame('private', $result['visibility']);
        self::assertSame($seed['thread_ids'][249], $result['cursor']);
        self::assertSame(250, (int) $workerDb->fetchValue(
            "SELECT COUNT(*) FROM thread_intelligence_jobs WHERE state = 'idle' AND due_at IS NULL",
        ));
    }

    public function test_canonical_thread_job_order_and_exceptional_board_job_order_do_not_deadlock(): void
    {
        // Direction 1: canonical publication owns threads -> jobs while a sweep
        // owns boards and waits for the job. Canonical never needs the board, so
        // it can commit and release the sweep without a lock cycle.
        $first = $this->seedBoard(1);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $jobs->upsertStale($first['thread_ids'][0], 'post_created', null, $this->now());
        $sweep = new ThreadIntelligenceBoardSweep($this->db);
        $sweep->markVisibilityChanged($first['board_id']);
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $this->db->fetch('SELECT id FROM threads WHERE id = ? FOR UPDATE', [$first['thread_ids'][0]]);

        $sweepCode = $this->childBootstrap() . <<<'PHP'
(new App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep($db))->runBatch(1, new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC')));
echo "DONE\n";
PHP;
        [$process, $pipes] = $this->startChild($sweepCode);
        $stdout = $this->waitForProcessOutput($pipes[1], 'READY');
        preg_match('/READY (\d+)/', $stdout, $matches);
        try {
            $this->waitForConnectionQuery((int) ($matches[1] ?? 0), 'thread_intelligence_jobs');
            $this->db->fetch(
                'SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE',
                [$first['thread_ids'][0]],
            );
            $this->pdo->commit();
            $stdout = $this->waitForProcessOutput($pipes[1], 'DONE', $stdout);
            $this->finishChild($process, $pipes, $stdout);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        // Direction 2: the exceptional path owns boards -> jobs while canonical
        // work owns the thread and waits for the job. The exceptional path has
        // no later thread/summary lock and can commit without a cycle.
        $second = $this->seedBoard(1);
        $jobs->upsertStale($second['thread_ids'][0], 'post_created', null, $this->now());
        $sweep->markVisibilityChanged($second['board_id']);

        $this->pdo->beginTransaction();
        $lockedBoard = $this->db->fetch(<<<'SQL'
            SELECT id, visibility, thread_intelligence_sweep_after_id
            FROM boards
            WHERE thread_intelligence_sweep_after_id IS NOT NULL
            ORDER BY id
            LIMIT 1
            FOR UPDATE SKIP LOCKED
            SQL);
        self::assertSame($second['board_id'], (int) $lockedBoard['id']);
        $this->db->fetch(
            'SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE',
            [$second['thread_ids'][0]],
        );

        $canonicalCode = $this->childBootstrap() . <<<'PHP'
$threadId = (int) getenv('RB_THREAD_ID');
$db->transaction(function () use ($db, $threadId): void {
    $db->fetch('SELECT id FROM threads WHERE id = ? FOR UPDATE', [$threadId]);
    echo "THREAD_LOCKED\n";
    flush();
    $db->fetch('SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE', [$threadId]);
});
echo "DONE\n";
PHP;
        [$process2, $pipes2] = $this->startChild($canonicalCode, ['RB_THREAD_ID' => (string) $second['thread_ids'][0]]);
        $stdout2 = $this->waitForProcessOutput($pipes2[1], 'THREAD_LOCKED');
        preg_match('/READY (\d+)/', $stdout2, $matches2);
        try {
            $this->waitForConnectionQuery((int) ($matches2[1] ?? 0), 'thread_intelligence_jobs');
            $this->pdo->commit();
            $stdout2 = $this->waitForProcessOutput($pipes2[1], 'DONE', $stdout2);
            $this->finishChild($process2, $pipes2, $stdout2);
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }
}
