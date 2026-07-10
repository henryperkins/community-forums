<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Repository\SettingRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Tests\Support\TestCase;

/**
 * Pins the validated operational settings (plan Task 4): the canonical
 * emergency-brake normalization ('1'/'0' strings, corrupt fails paused), the
 * keyed provider-health fingerprint with automatic clear on config change,
 * and the run-ID-owned worker heartbeat.
 */
final class ThreadIntelligenceSettingsTest extends TestCase
{
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
