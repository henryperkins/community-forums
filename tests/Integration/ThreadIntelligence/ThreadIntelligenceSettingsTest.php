<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\Database;
use App\Repository\SettingRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Tests\Support\TestCase;

/**
 * Pins the validated operational settings (plan Task 4): the canonical
 * emergency-brake normalization ('1'/'0' strings, corrupt fails paused), the
 * keyed provider-health fingerprint with automatic clear on config change,
 * and the run-ID-owned worker heartbeat.
 */
final class ThreadIntelligenceSettingsTest extends TestCase
{
    private bool $committedFixtures = false;

    private function settings(
        string $model = 'gpt-5.6-luna',
        string $effort = 'low',
        string $apiKey = 'sk-test-key-abc',
    ): ThreadIntelligenceSettings {
        return new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            ThreadIntelligenceConfig::fromArray(['model' => $model, 'reasoning_effort' => $effort, 'api_key' => $apiKey]),
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
    }

    private function repo(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    private function writeRawSetting(string $key, string $rawValue): void
    {
        $this->db->run(
            'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $rawValue],
        );
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    private function validHeartbeat(array $changes = []): array
    {
        return array_replace([
            'run_id' => str_repeat('a', 32),
            'status' => 'running',
            'worker_label' => 'cli',
            'started_at' => '2026-07-10T12:00:00Z',
            'completed_at' => null,
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ], $changes);
    }

    private function useCommittedFixtures(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
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

        self::fail("Timed out waiting for child process output: {$needle}. Received: {$output}");
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

    // ---- generation pause (emergency brake) -----------------------------------

    public function test_missing_pause_setting_means_unpaused_and_not_corrupt(): void
    {
        self::assertSame(['paused' => false, 'corrupt' => false], $this->settings()->generationPause());
    }

    public function test_canonical_strings_are_the_only_valid_pause_values(): void
    {
        $this->settings()->setGenerationPaused(true);
        self::assertSame(['paused' => true, 'corrupt' => false], $this->settings()->generationPause());
        self::assertSame('"1"', (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'thread_intelligence_generation_paused'"), 'the setter persists the JSON string form only');

        $this->settings()->setGenerationPaused(false);
        self::assertSame(['paused' => false, 'corrupt' => false], $this->settings()->generationPause());
        self::assertSame('"0"', (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'thread_intelligence_generation_paused'"));
    }

    public function test_every_noncanonical_present_value_fails_paused_and_corrupt(): void
    {
        // The settings.value column carries a json_valid() CHECK, so invalid
        // JSON cannot physically persist; the realistic corruption class is
        // valid JSON of the wrong type/value, all of which must fail PAUSED.
        $rawValues = [
            'JSON boolean true' => 'true',
            'JSON boolean false' => 'false',
            'JSON integer 1' => '1',
            'JSON integer 0' => '0',
            'other string' => '"yes"',
            'array' => '["1"]',
            'object' => '{"paused":true}',
            'JSON null' => 'null',
        ];
        foreach ($rawValues as $label => $raw) {
            $this->writeRawSetting('thread_intelligence_generation_paused', $raw);
            self::assertSame(['paused' => true, 'corrupt' => true], $this->settings()->generationPause(), $label);
        }
    }

    // ---- provider health latch -----------------------------------------------------

    public function test_block_provider_stores_a_keyed_fingerprint_never_a_credential_hash(): void
    {
        $apiKey = 'sk-test-key-abc';
        $this->settings()->blockProvider('authentication', $this->now());

        $health = $this->settings()->providerHealth();
        self::assertTrue($health['blocked']);
        self::assertSame('authentication', $health['code']);
        self::assertFalse($health['corrupt']);

        $raw = (string) $this->db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'thread_intelligence_provider_health'");
        $stored = json_decode($raw, true);
        $expectedFingerprint = hash_hmac(
            'sha256',
            json_encode(['gpt-5.6-luna', 'low', $apiKey], JSON_THROW_ON_ERROR),
            (string) $this->config->get('app.key'),
        );
        self::assertSame($expectedFingerprint, $stored['fingerprint'], 'the fingerprint is an APP_KEY-keyed HMAC of model+effort+credential');
        self::assertNotSame(hash('sha256', $apiKey), $stored['fingerprint'], 'never a plain credential hash');
        self::assertStringNotContainsString($apiKey, $raw, 'the credential itself is never stored');
    }

    public function test_a_model_effort_or_key_change_clears_the_block_automatically(): void
    {
        $this->settings()->blockProvider('invalid_model', $this->now());
        self::assertTrue($this->settings()->providerHealth()['blocked']);

        self::assertFalse($this->settings(model: 'gpt-5.7-nova')->providerHealth()['blocked'], 'model change clears the latch');
        self::assertFalse($this->settings(effort: 'none')->providerHealth()['blocked'], 'effort change clears the latch');
        self::assertFalse($this->settings(apiKey: 'sk-rotated-key')->providerHealth()['blocked'], 'key rotation clears the latch');
        self::assertTrue($this->settings()->providerHealth()['blocked'], 'the unchanged configuration stays latched');
    }

    public function test_clear_provider_block_and_corrupt_health_behavior(): void
    {
        $this->settings()->blockProvider('authentication', $this->now());
        $this->settings()->clearProviderBlock();
        $health = $this->settings()->providerHealth();
        self::assertFalse($health['blocked']);
        self::assertFalse($health['corrupt']);

        $this->writeRawSetting('thread_intelligence_provider_health', '{"code":123,"blocked_at":false}');
        $health = $this->settings()->providerHealth();
        self::assertTrue($health['blocked'], 'invalid health data fails blocked');
        self::assertTrue($health['corrupt'], 'and raises the admin warning flag');
    }

    public function test_bounded_block_codes_are_enforced(): void
    {
        try {
            $this->settings()->blockProvider(str_repeat('x', 100), $this->now());
            self::fail('block codes must stay bounded');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_timestamp_writers_convert_non_utc_inputs_before_appending_literal_z(): void
    {
        $offset = new DateTimeZone('Asia/Kolkata');
        $startedAt = new DateTimeImmutable('2026-07-10 17:30:00', $offset);
        $completedAt = new DateTimeImmutable('2026-07-10 17:30:05', $offset);

        $this->settings()->blockProvider('authentication', $startedAt);
        self::assertSame('2026-07-10T12:00:00Z', $this->settings()->providerHealth()['blocked_at']);

        $runId = $this->settings()->heartbeatStarted('cli', $startedAt);
        self::assertSame('2026-07-10T12:00:00Z', $this->settings()->heartbeat()['started_at']);

        $this->settings()->heartbeatFinished(
            $runId,
            'ok',
            ['processed' => 0, 'succeeded' => 0, 'failed' => 0],
            $completedAt,
        );
        self::assertSame('2026-07-10T12:00:05Z', $this->settings()->heartbeat()['completed_at']);
    }

    public function test_provider_health_reader_rejects_invalid_code_timestamp_and_fingerprint_shapes(): void
    {
        $this->settings()->blockProvider('authentication', $this->now());
        $raw = (string) $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [ThreadIntelligenceSettings::PROVIDER_HEALTH_KEY]);
        $valid = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $cases = [
            'empty code' => ['code' => ''],
            'impossible timestamp' => ['blocked_at' => '2026-02-30T12:00:00Z'],
            'non-UTC timestamp' => ['blocked_at' => '2026-07-10T12:00:00+00:00'],
            'short fingerprint' => ['fingerprint' => str_repeat('a', 63)],
            'non-hex fingerprint' => ['fingerprint' => str_repeat('z', 64)],
        ];

        foreach ($cases as $label => $change) {
            $this->writeRawSetting(
                ThreadIntelligenceSettings::PROVIDER_HEALTH_KEY,
                json_encode(array_replace($valid, $change), JSON_THROW_ON_ERROR),
            );
            $health = $this->settings()->providerHealth();
            self::assertTrue($health['blocked'], $label . ' must fail blocked');
            self::assertTrue($health['corrupt'], $label . ' must raise the operator warning');
            self::assertNull($health['code'], $label . ' must not surface unvalidated data');
            self::assertNull($health['blocked_at'], $label . ' must not surface unvalidated data');
        }
    }

    // ---- worker heartbeat -------------------------------------------------------------

    public function test_heartbeat_lifecycle_records_running_then_ok_with_integer_counts(): void
    {
        $runId = $this->settings()->heartbeatStarted('cli', $this->now());
        self::assertNotSame('', $runId);

        $beat = $this->settings()->heartbeat();
        self::assertSame('running', $beat['status']);
        self::assertSame('cli', $beat['worker_label']);
        self::assertSame('2026-07-10T12:00:00Z', $beat['started_at']);
        self::assertNull($beat['completed_at']);
        self::assertFalse($beat['corrupt']);

        $this->settings()->heartbeatFinished($runId, 'ok', ['processed' => 0, 'succeeded' => 0, 'failed' => 0], $this->now()->modify('+5 seconds'));
        $beat = $this->settings()->heartbeat();
        self::assertSame('ok', $beat['status']);
        self::assertSame(0, $beat['processed'], 'a zero-job run still completes the heartbeat');
        self::assertSame('2026-07-10T12:00:05Z', $beat['completed_at']);
    }

    public function test_heartbeat_reader_rejects_semantically_impossible_utc_timestamps(): void
    {
        $running = $this->validHeartbeat(['started_at' => '2026-02-30T12:00:00Z']);
        $this->writeRawSetting(
            ThreadIntelligenceSettings::HEARTBEAT_KEY,
            json_encode($running, JSON_THROW_ON_ERROR),
        );
        self::assertTrue($this->settings()->heartbeat()['corrupt'], 'an impossible start date must not pass a shape-only regex');

        $completed = array_replace($running, [
            'status' => 'ok',
            'started_at' => '2026-07-10T12:00:00Z',
            'completed_at' => '2026-04-31T12:00:05Z',
        ]);
        $this->writeRawSetting(
            ThreadIntelligenceSettings::HEARTBEAT_KEY,
            json_encode($completed, JSON_THROW_ON_ERROR),
        );
        self::assertTrue($this->settings()->heartbeat()['corrupt'], 'an impossible completion date must not pass a shape-only regex');
    }

    public function test_heartbeat_reader_enforces_the_complete_exact_record_contract(): void
    {
        $missingCount = $this->validHeartbeat();
        unset($missingCount['failed']);

        $cases = [
            'extra field' => $this->validHeartbeat(['credential' => 'must-never-be-accepted']),
            'missing count' => $missingCount,
            'empty run ID' => $this->validHeartbeat(['run_id' => '']),
            'oversized run ID' => $this->validHeartbeat(['run_id' => str_repeat('a', 65)]),
            'empty worker label' => $this->validHeartbeat(['worker_label' => '']),
            'oversized worker label' => $this->validHeartbeat(['worker_label' => str_repeat('w', 65)]),
            'running with completion time' => $this->validHeartbeat(['completed_at' => '2026-07-10T12:00:05Z']),
            'ok without completion time' => $this->validHeartbeat(['status' => 'ok']),
            'error without completion time' => $this->validHeartbeat(['status' => 'error']),
            'string count' => $this->validHeartbeat(['processed' => '0']),
            'negative count' => $this->validHeartbeat(['succeeded' => -1]),
            'float count' => $this->validHeartbeat(['failed' => 0.5]),
        ];

        foreach ($cases as $label => $record) {
            $this->writeRawSetting(
                ThreadIntelligenceSettings::HEARTBEAT_KEY,
                json_encode($record, JSON_THROW_ON_ERROR),
            );
            $beat = $this->settings()->heartbeat();
            self::assertTrue($beat['exists'], $label);
            self::assertTrue($beat['corrupt'], $label);
            self::assertNull($beat['run_id'], $label . ' must not surface unvalidated record data');
        }

        foreach (['ok', 'error'] as $status) {
            $this->writeRawSetting(
                ThreadIntelligenceSettings::HEARTBEAT_KEY,
                json_encode($this->validHeartbeat([
                    'status' => $status,
                    'completed_at' => '2026-07-10T12:00:05Z',
                ]), JSON_THROW_ON_ERROR),
            );
            $beat = $this->settings()->heartbeat();
            self::assertFalse($beat['corrupt'], $status . ' with a valid completion timestamp is terminal');
            self::assertSame($status, $beat['status']);
        }
    }

    public function test_heartbeat_started_rejects_an_empty_worker_label_instead_of_writing_corrupt_state(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->settings()->heartbeatStarted('', $this->now());
    }

    public function test_an_older_run_cannot_overwrite_a_newer_running_heartbeat(): void
    {
        $oldRun = $this->settings()->heartbeatStarted('cli', $this->now());
        $newRun = $this->settings()->heartbeatStarted('cli', $this->now()->modify('+1 minute'));

        $this->settings()->heartbeatFinished($oldRun, 'error', ['processed' => 5, 'succeeded' => 0, 'failed' => 5], $this->now()->modify('+2 minutes'));

        $beat = $this->settings()->heartbeat();
        self::assertSame('running', $beat['status'], 'the newer run still owns the heartbeat');
        self::assertSame($newRun, $beat['run_id']);

        $this->settings()->heartbeatFinished($newRun, 'error', ['processed' => 2, 'succeeded' => 1, 'failed' => 1], $this->now()->modify('+3 minutes'));
        $beat = $this->settings()->heartbeat();
        self::assertSame('error', $beat['status']);
        self::assertSame(2, $beat['processed']);
        self::assertSame(1, $beat['succeeded']);
        self::assertSame(1, $beat['failed']);
    }

    public function test_an_old_completion_blocked_behind_a_new_start_cannot_overwrite_it_across_connections(): void
    {
        $oldRun = $this->settings()->heartbeatStarted('old-worker', $this->now());
        $this->useCommittedFixtures();

        // Connection A publishes the newer run but deliberately holds its row
        // lock and commit. Connection B begins the old completion concurrently:
        // a non-locking read sees the committed old run, then its write waits.
        $this->pdo->beginTransaction();
        $newRun = $this->settings()->heartbeatStarted('new-worker', $this->now()->modify('+1 minute'));

        $childCode = <<<'PHP'
require $argv[1];

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$db = new \App\Core\Database($payload['db']);
$settings = new \App\Service\ThreadIntelligence\ThreadIntelligenceSettings(
    new \App\Repository\SettingRepository($db),
    \App\Service\ThreadIntelligence\ThreadIntelligenceConfig::fromArray([
        'model' => 'gpt-5.6-luna',
        'reasoning_effort' => 'low',
        'api_key' => 'sk-test-key-abc',
    ]),
    $payload['app_key'],
    'sk-test-key-abc',
    $db,
);

fwrite(STDOUT, "READY:" . $db->fetchValue('SELECT CONNECTION_ID()') . "\n");
fflush(STDOUT);
$settings->heartbeatFinished(
    $payload['old_run'],
    'error',
    ['processed' => 5, 'succeeded' => 0, 'failed' => 5],
    new \DateTimeImmutable('2026-07-10 12:02:00', new \DateTimeZone('UTC')),
);
fwrite(STDOUT, "DONE\n");
PHP;

        $process = null;
        $pipes = [];
        try {
            $process = proc_open(
                [PHP_BINARY, '-r', $childCode, dirname(__DIR__, 3) . '/vendor/autoload.php'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 3),
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode([
                'db' => $GLOBALS['__RB_TEST_DBCONFIG'],
                'app_key' => (string) $this->config->get('app.key'),
                'old_run' => $oldRun,
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            unset($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $output = $this->waitForProcessOutput($pipes[1], 'READY:');
            self::assertMatchesRegularExpression('/READY:(\d+)/', $output);
            preg_match('/READY:(\d+)/', $output, $readyMatch);
            $this->waitForConnectionQuery((int) $readyMatch[1], 'SELECT `value` FROM settings');
            self::assertTrue(proc_get_status($process)['running'], 'the old completion must be waiting behind the newer row write');

            $this->pdo->commit();
            $output = $this->waitForProcessOutput($pipes[1], 'DONE', $output);
            self::assertStringContainsString('DONE', $output);

            $deadline = microtime(true) + 5.0;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(10_000);
            } while (microtime(true) < $deadline);
            self::assertFalse($status['running'], 'the old completion process must exit after the row lock is released');
            self::assertSame('', trim((string) stream_get_contents($pipes[2])));
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }

        $beat = $this->settings()->heartbeat();
        self::assertSame('running', $beat['status'], 'the newer run remains current after the old completion resumes');
        self::assertSame($newRun, $beat['run_id']);
        self::assertSame('new-worker', $beat['worker_label']);
    }

    public function test_heartbeat_statuses_labels_and_counts_are_validated(): void
    {
        $runId = $this->settings()->heartbeatStarted(str_repeat('label-', 30), $this->now());
        $beat = $this->settings()->heartbeat();
        self::assertLessThanOrEqual(64, strlen((string) $beat['worker_label']), 'labels stay bounded');

        try {
            $this->settings()->heartbeatFinished($runId, 'weird', ['processed' => 1, 'succeeded' => 1, 'failed' => 0], $this->now());
            self::fail('only ok|error complete a heartbeat');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->settings()->heartbeatFinished($runId, 'ok', ['processed' => -1, 'succeeded' => 0, 'failed' => 0], $this->now());
            self::fail('counts must be nonnegative integers');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->writeRawSetting('thread_intelligence_worker_heartbeat', '{"status":"running"}');
        self::assertTrue($this->settings()->heartbeat()['corrupt'], 'a heartbeat missing its run/label/time fields is corrupt');

        $this->writeRawSetting('thread_intelligence_worker_heartbeat', '{"run_id":"x","status":"sideways","worker_label":"cli","started_at":"2026-07-10T12:00:00Z","completed_at":null,"processed":0,"succeeded":0,"failed":0}');
        self::assertTrue($this->settings()->heartbeat()['corrupt'], 'unknown statuses are corrupt, not trusted');
    }
}
