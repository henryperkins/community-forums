<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PDO;
use Tests\Support\TestCase;

/**
 * Pins the durable queue/lease state machine and the immutable generation
 * ledger (plan Task 4): single-row-per-thread upsert semantics, FOR UPDATE
 * SKIP LOCKED claims with per-row leases, compare-and-set renew/release with
 * activity-version requeue, once-only request evidence, and the redacted
 * column whitelist.
 *
 * Cross-connection tests commit their fixtures (a second PDO cannot see the
 * harness transaction) and reset committed state in tearDown, preserving the
 * migration-seeded reference tables (AppSearchTest pattern).
 */
final class ThreadIntelligenceRepositoryTest extends TestCase
{
    private bool $committedFixtures = false;

    private function jobs(): ThreadIntelligenceJobRepository
    {
        return new ThreadIntelligenceJobRepository($this->db);
    }

    private function generations(): ThreadIntelligenceGenerationRepository
    {
        return new ThreadIntelligenceGenerationRepository($this->db);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    /** @return array{thread_id:int, post_id:int} */
    private function seedThread(): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$thread['thread_id']]);
        return ['thread_id' => (int) $thread['thread_id'], 'post_id' => $postId];
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

    protected function tearDown(): void
    {
        if ($this->committedFixtures) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Preserve migration-seeded reference tables (see AppSearchTest and
            // the reference-table seed gotcha) — TRUNCATE auto-commits.
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

    // ---- job upsert semantics ------------------------------------------------

    public function test_find_returns_null_before_and_the_row_after_the_first_upsert(): void
    {
        $seed = $this->seedThread();
        self::assertNull($this->jobs()->find($seed['thread_id']));

        $due = $this->now()->modify('+15 minutes');
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $due);

        $row = $this->jobs()->find($seed['thread_id']);
        self::assertNotNull($row);
        self::assertSame('queued', $row['state']);
        self::assertSame('post_created', $row['trigger_code']);
        self::assertSame(1, (int) $row['activity_version']);
        self::assertSame(0, (int) $row['reconcile_required']);
        self::assertSame(0, (int) $row['automation_paused']);
        self::assertSame($due->format('Y-m-d H:i:s'), $row['due_at']);
    }

    public function test_repeated_upserts_keep_one_row_increment_activity_and_move_due_at(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('+15 minutes'));
        $later = $this->now()->modify('+45 minutes');
        $this->jobs()->upsertStale($seed['thread_id'], 'post_edited', 'body change', $later);

        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs WHERE thread_id = ?', [$seed['thread_id']]));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame(2, (int) $row['activity_version']);
        self::assertSame('post_edited', $row['trigger_code']);
        self::assertSame('body change', $row['trigger_reason']);
        self::assertSame($later->format('Y-m-d H:i:s'), $row['due_at']);
    }

    public function test_ordinary_upserts_preserve_terminal_states_running_leases_and_the_reconcile_flag(): void
    {
        $dead = $this->seedThread();
        $this->jobs()->upsertStale($dead['thread_id'], 'post_created', null, $this->now());
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'dead', due_at = NULL, reconcile_required = 1 WHERE thread_id = ?", [$dead['thread_id']]);

        $this->jobs()->upsertStale($dead['thread_id'], 'post_created', null, $this->now()->modify('+15 minutes'));
        $row = $this->jobs()->find($dead['thread_id']);
        self::assertSame('dead', $row['state'], 'stale activity must not revive a dead job');
        self::assertNull($row['due_at'], 'a dead job must not become provider-due');
        self::assertSame(2, (int) $row['activity_version'], 'activity is still recorded');
        self::assertSame(1, (int) $row['reconcile_required'], 'routine posts never clear required reconciliation');

        $review = $this->seedThread();
        $this->jobs()->upsertStale($review['thread_id'], 'post_created', null, $this->now());
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'review_required', due_at = NULL WHERE thread_id = ?", [$review['thread_id']]);
        $this->jobs()->upsertStale($review['thread_id'], 'post_created', null, $this->now());
        self::assertSame('review_required', $this->jobs()->find($review['thread_id'])['state']);

        $running = $this->seedThread();
        $this->jobs()->upsertStale($running['thread_id'], 'post_created', null, $this->now());
        $claimed = $this->jobs()->claimDue(5, $this->now()->modify('+16 minutes'));
        $claimedRow = null;
        foreach ($claimed as $c) {
            if ((int) $c['thread_id'] === $running['thread_id']) {
                $claimedRow = $c;
            }
        }
        self::assertNotNull($claimedRow);
        $this->jobs()->upsertStale($running['thread_id'], 'post_created', null, $this->now()->modify('+30 minutes'));
        $row = $this->jobs()->find($running['thread_id']);
        self::assertSame('running', $row['state'], 'an active lease is not interrupted by new activity');
        self::assertSame($claimedRow['lease_token'], $row['lease_token']);
        self::assertSame((int) $claimedRow['activity_version'] + 1, (int) $row['activity_version']);
    }

    // ---- claims and leases ---------------------------------------------------------

    public function test_claim_due_leases_due_rows_and_ignores_future_paused_idle_and_terminal_rows(): void
    {
        $due = $this->seedThread();
        $future = $this->seedThread();
        $paused = $this->seedThread();
        $deadRow = $this->seedThread();
        $review = $this->seedThread();
        $idle = $this->seedThread();

        $this->jobs()->upsertStale($due['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->jobs()->upsertStale($future['thread_id'], 'post_created', null, $this->now()->modify('+10 minutes'));
        $this->jobs()->upsertStale($paused['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->db->run('UPDATE thread_intelligence_jobs SET automation_paused = 1 WHERE thread_id = ?', [$paused['thread_id']]);
        $this->jobs()->upsertStale($deadRow['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'dead' WHERE thread_id = ?", [$deadRow['thread_id']]);
        $this->jobs()->upsertStale($review['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'review_required' WHERE thread_id = ?", [$review['thread_id']]);
        $this->jobs()->upsertStale($idle['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL WHERE thread_id = ?", [$idle['thread_id']]);

        $claimed = $this->jobs()->claimDue(10, $this->now());

        self::assertCount(1, $claimed);
        self::assertSame($due['thread_id'], (int) $claimed[0]['thread_id']);
        self::assertSame('running', $claimed[0]['state']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', (string) $claimed[0]['lease_token']);
        self::assertSame(1, (int) $claimed[0]['activity_version'], 'the claimed activity version is returned');
        self::assertSame(
            $this->now()->modify('+10 minutes')->format('Y-m-d H:i:s'),
            $claimed[0]['lease_expires_at'],
            'leases run ten minutes',
        );
    }

    public function test_claim_due_does_not_reclaim_an_active_lease_but_reclaims_an_expired_one_with_a_new_token(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $first = $this->jobs()->claimDue(5, $this->now());
        self::assertCount(1, $first);
        $firstToken = (string) $first[0]['lease_token'];

        self::assertSame([], $this->jobs()->claimDue(5, $this->now()->modify('+5 minutes')), 'an active lease must be skipped');

        $reclaimed = $this->jobs()->claimDue(5, $this->now()->modify('+11 minutes'));
        self::assertCount(1, $reclaimed);
        self::assertNotSame($firstToken, (string) $reclaimed[0]['lease_token'], 'an expired lease is reclaimed under a fresh token');
        self::assertSame('running', $reclaimed[0]['state']);
    }

    public function test_claim_due_skips_rows_locked_by_a_concurrent_worker_via_skip_locked(): void
    {
        $a = $this->seedThread();
        $b = $this->seedThread();
        $this->jobs()->upsertStale($a['thread_id'], 'post_created', null, $this->now()->modify('-2 minutes'));
        $this->jobs()->upsertStale($b['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->useCommittedFixtures();

        // Worker 1 (harness connection) claims inside an open transaction so its
        // row lock is held while worker 2 tries to claim.
        $this->pdo->beginTransaction();
        $mine = $this->jobs()->claimDue(1, $this->now());
        self::assertCount(1, $mine);
        self::assertSame($a['thread_id'], (int) $mine[0]['thread_id'], 'the oldest due row is claimed first');

        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $otherDb->run('SET SESSION innodb_lock_wait_timeout = 2');
        $theirs = (new ThreadIntelligenceJobRepository($otherDb))->claimDue(1, $this->now());

        self::assertCount(1, $theirs, 'a limit-one worker must scan past the older locked row');
        self::assertSame($b['thread_id'], (int) $theirs[0]['thread_id']);

        $this->pdo->rollBack();
    }

    public function test_claim_due_bounds_the_scan_when_a_locked_prefix_exceeds_its_budget(): void
    {
        $lockedThreadIds = [];
        for ($i = 0; $i < 10; $i++) {
            $seed = $this->seedThread();
            $lockedThreadIds[] = $seed['thread_id'];
            $this->jobs()->upsertStale(
                $seed['thread_id'],
                'post_created',
                null,
                $this->now()->modify('-20 minutes +' . $i . ' seconds'),
            );
        }
        $available = $this->seedThread();
        $this->jobs()->upsertStale($available['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        foreach ($lockedThreadIds as $threadId) {
            $this->db->fetch('SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE', [$threadId]);
        }

        $otherDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $otherDb->run('SET SESSION innodb_lock_wait_timeout = 2');
        $otherJobs = new ThreadIntelligenceJobRepository($otherDb);
        self::assertSame(
            [],
            $otherJobs->claimDue(1, $this->now()),
            'one claim transaction examines only a fixed multiple of its requested limit',
        );

        $this->pdo->rollBack();
        self::assertCount(1, $otherJobs->claimDue(1, $this->now()), 'work remains claimable after the locked prefix clears');
    }

    // ---- compare-and-set renew/release ------------------------------------------------

    public function test_renew_lease_requires_the_exact_token(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $claimed = $this->jobs()->claimDue(1, $this->now())[0];
        $token = (string) $claimed['lease_token'];

        self::assertFalse($this->jobs()->renewLease($seed['thread_id'], str_repeat('0', 64), 1, $this->now()->modify('+20 minutes')));
        self::assertSame('running', $this->jobs()->find($seed['thread_id'])['state'], 'a foreign token is a no-op');
        self::assertTrue($this->jobs()->renewLease($seed['thread_id'], $token, 1, $this->now()->modify('+20 minutes')));
        self::assertSame(
            $this->now()->modify('+20 minutes')->format('Y-m-d H:i:s'),
            $this->jobs()->find($seed['thread_id'])['lease_expires_at'],
        );
    }

    public function test_renew_lease_with_an_owned_stale_activity_version_requeues_newer_activity(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $claimed = $this->jobs()->claimDue(1, $this->now())[0];
        $token = (string) $claimed['lease_token'];

        $this->jobs()->upsertStale($seed['thread_id'], 'post_edited', null, $this->now()->modify('+15 minutes'));

        self::assertFalse($this->jobs()->renewLease($seed['thread_id'], $token, 1, $this->now()->modify('+20 minutes')));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('queued', $row['state']);
        self::assertSame(2, (int) $row['activity_version']);
        self::assertNotNull($row['due_at']);
        self::assertNull($row['lease_token']);
        self::assertNull($row['lease_expires_at']);
    }

    public function test_release_applies_the_terminal_transition_only_under_the_owned_lease(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $token = (string) $this->jobs()->claimDue(1, $this->now())[0]['lease_token'];

        self::assertFalse($this->jobs()->release($seed['thread_id'], str_repeat('0', 64), 1, 'retry', $this->now()->modify('+5 minutes'), 'transport'));
        self::assertSame('running', $this->jobs()->find($seed['thread_id'])['state'], 'a foreign token must not move the row');

        self::assertTrue($this->jobs()->release($seed['thread_id'], $token, 1, 'retry', $this->now()->modify('+5 minutes'), 'transport'));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('retry', $row['state']);
        self::assertSame('transport', $row['last_error_code']);
        self::assertSame($this->now()->modify('+5 minutes')->format('Y-m-d H:i:s'), $row['due_at']);
        self::assertNull($row['lease_token']);
        self::assertSame(1, (int) $row['attempt_count'], 'a failed release counts one attempt');
    }

    public function test_release_with_a_stale_activity_version_requeues_current_activity_instead_of_clearing_it(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $token = (string) $this->jobs()->claimDue(1, $this->now())[0]['lease_token'];

        // New activity lands while the worker holds the lease.
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('+15 minutes'));

        self::assertFalse($this->jobs()->release($seed['thread_id'], $token, 1, 'idle', null, null));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('queued', $row['state'], 'the newer activity is requeued, not idled away');
        self::assertNotNull($row['due_at']);
        self::assertSame(2, (int) $row['activity_version'], 'the newer version is preserved');
        self::assertNull($row['lease_token']);
    }

    public function test_release_published_advances_checkpoint_and_cadence_only_when_the_version_still_matches(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $this->db->run('UPDATE thread_intelligence_jobs SET reconcile_required = 1 WHERE thread_id = ?', [$seed['thread_id']]);
        $token = (string) $this->jobs()->claimDue(1, $this->now())[0]['lease_token'];
        $generationId = $this->startGeneration($seed['thread_id']);
        $publishedAt = $this->now()->modify('+2 minutes');
        $hash = str_repeat('ef', 32);
        $this->generations()->complete($generationId, [
            'status' => 'published',
            'published_at' => $publishedAt->format('Y-m-d H:i:s'),
        ]);

        self::assertTrue($this->jobs()->releasePublished($seed['thread_id'], $token, 1, $generationId, $seed['post_id'], $hash, true, $publishedAt));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('idle', $row['state']);
        self::assertNull($row['due_at']);
        self::assertNull($row['lease_token']);
        self::assertSame(0, (int) $row['attempt_count']);
        self::assertNull($row['last_error_code']);
        self::assertSame($seed['post_id'], (int) $row['last_processed_post_id']);
        self::assertSame($publishedAt->format('Y-m-d H:i:s'), $row['last_generated_at']);
        self::assertSame($publishedAt->format('Y-m-d H:i:s'), $row['last_full_reconcile_at'], 'a full reconcile stamps its own cadence field');
        self::assertSame($hash, $row['source_snapshot_hash']);
        self::assertSame(0, (int) $row['reconcile_required'], 'a matching full reconcile clears the requirement');

        // Stale-version publication attempts requeue instead of overwriting.
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('+20 minutes'));
        $token2 = (string) $this->jobs()->claimDue(1, $this->now()->modify('+21 minutes'))[0]['lease_token'];
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('+40 minutes'));

        self::assertFalse($this->jobs()->releasePublished($seed['thread_id'], $token2, 2, $generationId, $seed['post_id'], str_repeat('aa', 32), false, $publishedAt));
        $after = $this->jobs()->find($seed['thread_id']);
        self::assertSame('queued', $after['state']);
        self::assertSame($hash, $after['source_snapshot_hash'], 'a stale publication must not advance the snapshot');
    }

    public function test_release_published_rejects_a_generation_that_is_still_requested(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $token = (string) $this->jobs()->claimDue(1, $this->now())[0]['lease_token'];
        $requestedGenerationId = $this->startGeneration($seed['thread_id']);

        self::assertFalse($this->jobs()->releasePublished(
            $seed['thread_id'],
            $token,
            1,
            $requestedGenerationId,
            $seed['post_id'],
            str_repeat('ab', 32),
            false,
            $this->now(),
        ));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('running', $row['state']);
        self::assertSame($token, $row['lease_token']);
        self::assertNull($row['last_processed_post_id']);
    }

    public function test_release_published_rejects_a_published_generation_owned_by_another_thread(): void
    {
        $seed = $this->seedThread();
        $other = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $token = (string) $this->jobs()->claimDue(1, $this->now())[0]['lease_token'];
        $foreignGenerationId = $this->startGeneration($other['thread_id']);
        $this->generations()->complete($foreignGenerationId, ['status' => 'published']);

        self::assertFalse($this->jobs()->releasePublished(
            $seed['thread_id'],
            $token,
            1,
            $foreignGenerationId,
            $seed['post_id'],
            str_repeat('cd', 32),
            false,
            $this->now(),
        ));
        $row = $this->jobs()->find($seed['thread_id']);
        self::assertSame('running', $row['state']);
        self::assertSame($token, $row['lease_token']);
        self::assertNull($row['last_processed_post_id']);
    }

    public function test_release_published_locks_job_before_generation_to_match_atomic_publication(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $claim = $this->jobs()->claimDue(1, $this->now())[0];
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->generations()->complete($generationId, ['status' => 'published']);
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $this->db->fetch('SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE', [$seed['thread_id']]);

        $childCode = <<<'PHP'
require $argv[1];
$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$db = new \App\Core\Database($payload['db']);
fwrite(STDOUT, "ready:" . $db->fetchValue('SELECT CONNECTION_ID()') . "\n");
fflush(STDOUT);
$released = (new \App\Repository\ThreadIntelligenceJobRepository($db))->releasePublished(
    $payload['thread_id'],
    $payload['lease_token'],
    $payload['activity_version'],
    $payload['generation_id'],
    $payload['post_id'],
    str_repeat('ab', 32),
    false,
    new \DateTimeImmutable('2026-07-10 12:02:00', new \DateTimeZone('UTC')),
);
fwrite(STDOUT, "result:" . ($released ? '1' : '0') . "\n");
PHP;
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode, dirname(__DIR__, 3) . '/vendor/autoload.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode([
            'db' => $GLOBALS['__RB_TEST_DBCONFIG'],
            'thread_id' => $seed['thread_id'],
            'lease_token' => $claim['lease_token'],
            'activity_version' => (int) $claim['activity_version'],
            'generation_id' => $generationId,
            'post_id' => $seed['post_id'],
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);

        $ready = trim((string) fgets($pipes[1]));
        self::assertMatchesRegularExpression('/\Aready:(\d+)\z/', $ready);
        preg_match('/ready:(\d+)/', $ready, $readyMatch);
        $this->waitForConnectionQuery((int) $readyMatch[1], 'thread_intelligence_jobs WHERE thread_id');

        $parentAcquiredGeneration = true;
        $previousLockWait = (int) $this->db->fetchValue('SELECT @@SESSION.innodb_lock_wait_timeout');
        try {
            $this->db->run('SET SESSION innodb_lock_wait_timeout = 1');
            $this->db->fetch('SELECT id FROM thread_intelligence_generations WHERE id = ? FOR UPDATE', [$generationId]);
        } catch (\PDOException) {
            $parentAcquiredGeneration = false;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->db->run('SET SESSION innodb_lock_wait_timeout = ' . $previousLockWait);
        stream_set_timeout($pipes[1], 5);
        $result = trim((string) fgets($pipes[1]));
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertTrue(
            $parentAcquiredGeneration,
            'job-first release must wait before generation so an atomic publisher can finish job -> generation without a cycle',
        );
        self::assertSame(0, $exitCode, $stderr);
        self::assertSame('', trim($stderr));
        self::assertSame('result:1', $result);
    }

    // ---- generation ledger ---------------------------------------------------------------

    /** @return int */
    private function startGeneration(int $threadId, string $trigger = 'post_created'): int
    {
        return $this->generations()->start([
            'thread_id' => $threadId,
            'trigger_code' => $trigger,
            'retry_number' => 0,
            'window_number' => 0,
            'baseline_summary_id' => null,
            'model' => 'gpt-5.6-luna',
            'reasoning_effort' => 'low',
            'prompt_version' => 'thread-intelligence-v1',
        ]);
    }

    public function test_start_and_record_request_capture_redacted_evidence_exactly_once(): void
    {
        $seed = $this->seedThread();
        $id = $this->startGeneration($seed['thread_id']);

        $row = $this->db->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$id]);
        self::assertSame('requested', $row['status']);
        self::assertNull($row['request_fingerprint'], 'no committed reservation yet');
        self::assertNotNull($row['requested_at']);

        $this->generations()->recordRequest($id, str_repeat('ab', 32), [$seed['post_id']], [77], str_repeat('cd', 32), 4200);
        $row = $this->db->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$id]);
        self::assertSame(str_repeat('ab', 32), $row['source_snapshot_hash']);
        self::assertSame([$seed['post_id']], json_decode((string) $row['source_post_ids'], true));
        self::assertSame([77], json_decode((string) $row['candidate_thread_ids'], true));
        self::assertSame(str_repeat('cd', 32), $row['request_fingerprint']);
        self::assertSame(4200, (int) $row['estimated_input_tokens']);

        try {
            $this->generations()->recordRequest($id, str_repeat('ab', 32), [], [], str_repeat('cd', 32), 1);
            self::fail('request evidence may be recorded exactly once');
        } catch (LogicException $e) {
            self::assertStringContainsString('once', $e->getMessage());
        }
    }

    public function test_record_request_rejects_non_list_and_nonpositive_id_evidence(): void
    {
        $seed = $this->seedThread();
        $cases = [
            'associative source IDs' => [['post' => $seed['post_id']], []],
            'zero source ID' => [[0], []],
            'string source ID' => [['1'], []],
            'negative candidate ID' => [[$seed['post_id']], [-1]],
        ];

        foreach ($cases as $label => [$sourceIds, $candidateIds]) {
            $id = $this->startGeneration($seed['thread_id']);
            try {
                $this->generations()->recordRequest(
                    $id,
                    str_repeat('ab', 32),
                    $sourceIds,
                    $candidateIds,
                    str_repeat('cd', 32),
                    100,
                );
                self::fail($label . ' must be rejected before persistence');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('positive integer ID lists', $e->getMessage(), $label);
            }
        }
    }

    public function test_generation_rows_reject_credential_prompt_response_and_body_fields(): void
    {
        $seed = $this->seedThread();

        foreach (['api_key', 'raw_prompt', 'prompt', 'raw_response', 'response_body', 'post_body', 'generated_text'] as $forbidden) {
            try {
                $this->generations()->start([
                    'thread_id' => $seed['thread_id'],
                    'trigger_code' => 'post_created',
                    $forbidden => 'must never persist',
                ]);
                self::fail("start() must reject field: $forbidden");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $id = $this->startGeneration($seed['thread_id']);
        try {
            $this->generations()->complete($id, ['status' => 'failed', 'raw_response' => 'nope']);
            self::fail('complete() must reject raw provider fields');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_complete_performs_exactly_one_requested_to_terminal_transition_with_bounded_detail(): void
    {
        $seed = $this->seedThread();
        $id = $this->startGeneration($seed['thread_id']);

        $this->generations()->complete($id, [
            'status' => 'failed',
            'failure_code' => 'transport',
            'failure_message' => str_repeat('m', 400),
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $row = $this->db->fetch('SELECT * FROM thread_intelligence_generations WHERE id = ?', [$id]);
        self::assertSame('failed', $row['status']);
        self::assertSame('transport', $row['failure_code']);
        self::assertSame(255, strlen((string) $row['failure_message']), 'safe messages are truncated to 255 characters');
        self::assertNotNull($row['completed_at']);

        try {
            $this->generations()->complete($id, ['status' => 'published']);
            self::fail('terminal rows are update-closed');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->generations()->complete($this->startGeneration($seed['thread_id']), ['status' => 'requested']);
            self::fail('requested is not a terminal status');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_abandoned_requested_returns_the_oldest_bounded_rows(): void
    {
        $seed = $this->seedThread();
        $old1 = $this->startGeneration($seed['thread_id']);
        $old2 = $this->startGeneration($seed['thread_id']);
        $old3 = $this->startGeneration($seed['thread_id']);
        $fresh = $this->startGeneration($seed['thread_id']);
        $terminal = $this->startGeneration($seed['thread_id']);
        $this->generations()->complete($terminal, ['status' => 'failed', 'failure_code' => 'transport']);

        $cutoffStamp = $this->now()->modify('-11 minutes')->format('Y-m-d H:i:s');
        foreach ([$old1, $old2, $old3] as $oldId) {
            $this->db->run('UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?', [$cutoffStamp, $oldId]);
        }
        $this->db->run('UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?', [$this->now()->format('Y-m-d H:i:s'), $fresh]);

        $abandoned = $this->generations()->abandonedRequested($this->now()->modify('-10 minutes'), 2);
        self::assertSame([$old1, $old2], array_map(static fn (array $r): int => (int) $r['id'], $abandoned), 'oldest first, bounded by the limit');

        $all = $this->generations()->abandonedRequested($this->now()->modify('-10 minutes'));
        self::assertSame([$old1, $old2, $old3], array_map(static fn (array $r): int => (int) $r['id'], $all), 'fresh and terminal rows are excluded');
    }

    public function test_abandoned_requested_excludes_a_request_owned_by_an_actively_renewed_lease(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->db->run(
            'UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?',
            [$this->now()->modify('-20 minutes')->format('Y-m-d H:i:s'), $generationId],
        );
        $this->useCommittedFixtures();

        $ownerDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $ownerJobs = new ThreadIntelligenceJobRepository($ownerDb);
        $claim = $ownerJobs->claimDue(1, $this->now())[0];
        self::assertTrue($ownerJobs->renewLease(
            $seed['thread_id'],
            (string) $claim['lease_token'],
            (int) $claim['activity_version'],
            $this->now()->modify('+20 minutes'),
        ));

        $reconcilerDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $reconciler = new ThreadIntelligenceGenerationRepository($reconcilerDb);
        self::assertSame(
            [],
            $reconciler->abandonedRequested($this->now()->modify('-10 minutes')),
            'a reconciler must not finalize evidence owned by another worker\'s active renewed lease',
        );

        $ownerDb->run(
            'UPDATE thread_intelligence_jobs SET lease_expires_at = ? WHERE thread_id = ?',
            [$this->now()->modify('-1 minute')->format('Y-m-d H:i:s'), $seed['thread_id']],
        );
        self::assertSame(
            [$generationId],
            array_map(
                static fn (array $row): int => (int) $row['id'],
                $reconciler->abandonedRequested($this->now()->modify('-10 minutes')),
            ),
            'the request becomes reconcilable after its owning lease expires',
        );
    }

    public function test_generation_list_readers_return_decoded_id_arrays(): void
    {
        $seed = $this->seedThread();
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->generations()->recordRequest(
            $generationId,
            str_repeat('ab', 32),
            [$seed['post_id']],
            [73, 91],
            str_repeat('cd', 32),
            100,
        );
        $this->db->run(
            'UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?',
            [$this->now()->modify('-20 minutes')->format('Y-m-d H:i:s'), $generationId],
        );

        $abandoned = $this->generations()->abandonedRequested($this->now()->modify('-10 minutes'))[0];
        self::assertSame([$seed['post_id']], $abandoned['source_post_ids']);
        self::assertSame([73, 91], $abandoned['candidate_thread_ids']);

        $recent = $this->generations()->recent(10);
        $listed = null;
        foreach ($recent as $row) {
            if ((int) $row['id'] === $generationId) {
                $listed = $row;
                break;
            }
        }
        self::assertNotNull($listed);
        self::assertSame([$seed['post_id']], $listed['source_post_ids']);
        self::assertSame([73, 91], $listed['candidate_thread_ids']);
    }

    public function test_generation_list_readers_reject_corrupt_id_array_shapes(): void
    {
        $seed = $this->seedThread();
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->db->run(
            "UPDATE thread_intelligence_generations SET source_post_ids = JSON_OBJECT('post', ?) WHERE id = ?",
            [$seed['post_id'], $generationId],
        );

        try {
            $this->generations()->recent(10);
            self::fail('generation ID evidence must be a JSON list of positive integers');
        } catch (LogicException $e) {
            self::assertStringContainsString('ID evidence', $e->getMessage());
        }
    }

    public function test_prune_revalidates_when_a_job_concurrently_becomes_review_protected(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now());
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, updated_at = ? WHERE thread_id = ?",
            [$this->now()->modify('-100 days')->format('Y-m-d H:i:s'), $seed['thread_id']],
        );
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->generations()->complete($generationId, ['status' => 'failed']);
        $this->db->run(
            'UPDATE thread_intelligence_generations SET completed_at = ? WHERE id = ?',
            [$this->now()->modify('-100 days')->format('Y-m-d H:i:s'), $generationId],
        );
        $this->useCommittedFixtures();

        // Hold the protecting transition open. A non-locking eligibility read
        // can still see the previously committed idle row and race its delete.
        $this->pdo->beginTransaction();
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'review_required', updated_at = UTC_TIMESTAMP() WHERE thread_id = ?",
            [$seed['thread_id']],
        );

        $childCode = <<<'PHP'
$root = getcwd();
require $root . '/vendor/autoload.php';
\App\Core\Env::load($root . '/.env');
$config = \App\Core\Config::fromFile($root . '/config/config.php');
$dbConfig = $config->all()['db'];
$dbConfig['database'] = \App\Core\Env::get('DB_TEST_DATABASE', 'retroboards_test');
$db = new \App\Core\Database($dbConfig);
fwrite(STDOUT, "ready:" . $db->fetchValue('SELECT CONNECTION_ID()') . "\n");
fflush(STDOUT);
$count = (new \App\Repository\ThreadIntelligenceGenerationRepository($db))->pruneEligible(
    new \DateTimeImmutable('2026-07-10 12:00:00', new \DateTimeZone('UTC')),
    1,
);
fwrite(STDOUT, "result:" . $count . "\n");
fflush(STDOUT);
PHP;
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
        );
        self::assertIsResource($process);
        fclose($pipes[0]);

        $readyLine = fgets($pipes[1]);
        $resultLine = null;
        if (PHP_OS_FAMILY === 'Windows') {
            self::assertMatchesRegularExpression('/ready:(\d+)/', (string) $readyLine);
            preg_match('/ready:(\d+)/', (string) $readyLine, $readyMatch);
            $this->waitForConnectionQuery((int) $readyMatch[1], 'SELECT thread_id FROM thread_intelligence_jobs');
        } else {
            $read = [$pipes[1]];
            $write = [];
            $except = [];
            $readable = stream_select($read, $write, $except, 1);
            if ($readable === 1) {
                $resultLine = fgets($pipes[1]);
            } else {
                self::assertMatchesRegularExpression('/ready:(\d+)/', (string) $readyLine);
                preg_match('/ready:(\d+)/', (string) $readyLine, $readyMatch);
                $this->waitForConnectionQuery((int) $readyMatch[1], 'SELECT thread_id FROM thread_intelligence_jobs');
            }
        }

        // Release the job row after either the vulnerable delete completed or
        // the hardened pruner demonstrably waited for the protecting lock.
        $this->pdo->commit();
        if ($resultLine === null) {
            stream_set_timeout($pipes[1], 5);
            $resultLine = fgets($pipes[1]);
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertMatchesRegularExpression('/\Aready:\d+\z/', trim((string) $readyLine));
        self::assertSame(0, $exitCode, $stderr);
        self::assertSame('', trim($stderr));
        self::assertSame('result:0', trim((string) $resultLine));
        self::assertNotNull(
            $this->db->fetch('SELECT id FROM thread_intelligence_generations WHERE id = ?', [$generationId]),
            'evidence must survive a concurrent transition into review_required',
        );
    }

    public function test_prune_locks_job_before_generation_to_avoid_atomic_publication_deadlocks(): void
    {
        $seed = $this->seedThread();
        $this->jobs()->upsertStale($seed['thread_id'], 'post_created', null, $this->now());
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, updated_at = ? WHERE thread_id = ?",
            [$this->now()->modify('-100 days')->format('Y-m-d H:i:s'), $seed['thread_id']],
        );
        $generationId = $this->startGeneration($seed['thread_id']);
        $this->generations()->complete($generationId, ['status' => 'failed']);
        $this->db->run(
            'UPDATE thread_intelligence_generations SET completed_at = ? WHERE id = ?',
            [$this->now()->modify('-100 days')->format('Y-m-d H:i:s'), $generationId],
        );
        $this->useCommittedFixtures();

        $this->pdo->beginTransaction();
        $this->db->fetch('SELECT thread_id FROM thread_intelligence_jobs WHERE thread_id = ? FOR UPDATE', [$seed['thread_id']]);

        $childCode = <<<'PHP'
$root = getcwd();
require $root . '/vendor/autoload.php';
\App\Core\Env::load($root . '/.env');
$config = \App\Core\Config::fromFile($root . '/config/config.php');
$dbConfig = $config->all()['db'];
$dbConfig['database'] = \App\Core\Env::get('DB_TEST_DATABASE', 'retroboards_test');
$db = new \App\Core\Database($dbConfig);
fwrite(STDOUT, "ready:" . $db->fetchValue('SELECT CONNECTION_ID()') . "\n");
fflush(STDOUT);
$count = (new \App\Repository\ThreadIntelligenceGenerationRepository($db))->pruneEligible(
    new \DateTimeImmutable('2026-07-10 12:00:00', new \DateTimeZone('UTC')),
    1,
);
fwrite(STDOUT, "result:" . $count . "\n");
PHP;
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 3),
        );
        self::assertIsResource($process);
        fclose($pipes[0]);

        $ready = trim((string) fgets($pipes[1]));
        self::assertMatchesRegularExpression('/\Aready:(\d+)\z/', $ready);
        preg_match('/ready:(\d+)/', $ready, $readyMatch);
        $this->waitForConnectionQuery((int) $readyMatch[1], 'FOR UPDATE');

        $parentAcquiredGeneration = true;
        $previousLockWait = (int) $this->db->fetchValue('SELECT @@SESSION.innodb_lock_wait_timeout');
        try {
            $this->db->run('SET SESSION innodb_lock_wait_timeout = 1');
            $this->db->fetch('SELECT id FROM thread_intelligence_generations WHERE id = ? FOR UPDATE', [$generationId]);
        } catch (\PDOException) {
            $parentAcquiredGeneration = false;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->db->run('SET SESSION innodb_lock_wait_timeout = ' . $previousLockWait);
        stream_set_timeout($pipes[1], 5);
        $result = trim((string) fgets($pipes[1]));
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertTrue(
            $parentAcquiredGeneration,
            'pruning must wait on the publisher-owned job before touching generation evidence',
        );
        self::assertSame(0, $exitCode, $stderr);
        self::assertSame('', trim($stderr));
        self::assertSame('result:1', $result);
    }

    public function test_recent_lists_newest_attempts_first(): void
    {
        $seed = $this->seedThread();
        $first = $this->startGeneration($seed['thread_id']);
        $second = $this->startGeneration($seed['thread_id']);

        $recent = $this->generations()->recent(10);
        self::assertGreaterThanOrEqual(2, count($recent));
        self::assertSame($second, (int) $recent[0]['id']);
        self::assertSame($first, (int) $recent[1]['id']);

        self::assertCount(1, $this->generations()->recent(1));
    }
}
