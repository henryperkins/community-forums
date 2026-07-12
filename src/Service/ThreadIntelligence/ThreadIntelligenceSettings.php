<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Repository\SettingRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Validated Thread Intelligence operational settings (plan Task 4).
 *
 * - Global generation brake: the repository's emergency-brake idiom, NOT
 *   feature-flag normalization. Only the canonical JSON strings '1'/'0' are
 *   valid; missing means unpaused; any other present value fails PAUSED with
 *   an operator warning (`corrupt`).
 * - Provider health latch: bounded failure code + an APP_KEY-keyed HMAC
 *   fingerprint of (model, effort, credential). A configuration change flips
 *   the fingerprint and clears the latch automatically. Never stores the
 *   credential or an unkeyed credential hash.
 * - Worker heartbeat: run-ID-owned status object; an older run can never
 *   overwrite a newer running heartbeat.
 */
final class ThreadIntelligenceSettings
{
    public const PAUSE_KEY = 'thread_intelligence_generation_paused';
    public const PROVIDER_HEALTH_KEY = 'thread_intelligence_provider_health';
    public const HEARTBEAT_KEY = 'thread_intelligence_worker_heartbeat';

    private const HEARTBEAT_STATUSES = ['running', 'ok', 'error'];
    private const HEARTBEAT_FIELDS = [
        'run_id', 'status', 'worker_label', 'started_at', 'completed_at', 'processed', 'succeeded', 'failed',
    ];
    private const MAX_RUN_ID_LENGTH = 64;
    private const MAX_WORKER_LABEL_LENGTH = 64;
    private const TIME_FORMAT = 'Y-m-d\TH:i:s\Z';
    private const SAFE_CODE_PATTERN = '/\A[a-z0-9][a-z0-9_.-]{0,63}\z/';
    private const FINGERPRINT_PATTERN = '/\A[0-9a-f]{64}\z/';

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly ThreadIntelligenceConfig $config,
        private readonly string $appKey,
        private readonly string $apiKey,
        private readonly Database $db,
    ) {
    }

    // ---- generation pause (emergency brake) ---------------------------------

    /** @return array{paused:bool, corrupt:bool} */
    public function generationPause(): array
    {
        $key = self::PAUSE_KEY;
        if (!$this->settings->has($key)) {
            return ['paused' => false, 'corrupt' => false];
        }
        $invalidJson = new \stdClass();
        $value = $this->settings->get($key, $invalidJson);
        return match (true) {
            $value === '0' => ['paused' => false, 'corrupt' => false],
            $value === '1' => ['paused' => true, 'corrupt' => false],
            default => ['paused' => true, 'corrupt' => true],
        };
    }

    public function setGenerationPaused(bool $paused): void
    {
        $this->settings->set(self::PAUSE_KEY, $paused ? '1' : '0');
    }

    // ---- provider configuration health latch ------------------------------------

    /** @return array{blocked:bool, code:?string, blocked_at:?string, corrupt:bool} */
    public function providerHealth(): array
    {
        if (!$this->settings->has(self::PROVIDER_HEALTH_KEY)) {
            return ['blocked' => false, 'code' => null, 'blocked_at' => null, 'corrupt' => false];
        }

        $invalidJson = new \stdClass();
        $value = $this->settings->get(self::PROVIDER_HEALTH_KEY, $invalidJson);
        if ($value === null) {
            // Explicitly cleared by an administrator.
            return ['blocked' => false, 'code' => null, 'blocked_at' => null, 'corrupt' => false];
        }
        if (!is_array($value)
            || !is_string($value['code'] ?? null) || preg_match(self::SAFE_CODE_PATTERN, $value['code']) !== 1
            || !$this->isValidTime($value['blocked_at'] ?? null)
            || !is_string($value['fingerprint'] ?? null) || preg_match(self::FINGERPRINT_PATTERN, $value['fingerprint']) !== 1) {
            // Invalid health JSON fails blocked with an admin warning.
            return ['blocked' => true, 'code' => null, 'blocked_at' => null, 'corrupt' => true];
        }

        if (!hash_equals($this->configFingerprint(), $value['fingerprint'])) {
            // The keyed model/effort/credential fingerprint changed: the latch
            // clears automatically after configuration repair.
            return ['blocked' => false, 'code' => null, 'blocked_at' => null, 'corrupt' => false];
        }

        return ['blocked' => true, 'code' => $value['code'], 'blocked_at' => $value['blocked_at'], 'corrupt' => false];
    }

    public function blockProvider(string $safeCode, DateTimeImmutable $at): void
    {
        if (preg_match(self::SAFE_CODE_PATTERN, $safeCode) !== 1) {
            throw new InvalidArgumentException('provider block codes must be bounded');
        }
        $this->settings->set(self::PROVIDER_HEALTH_KEY, [
            'code' => $safeCode,
            'blocked_at' => $this->formatUtc($at),
            'fingerprint' => $this->configFingerprint(),
        ]);
    }

    public function clearProviderBlock(): void
    {
        $this->settings->set(self::PROVIDER_HEALTH_KEY, null);
    }

    /** APP_KEY-keyed HMAC over canonical (model, effort, credential) — never an unkeyed hash. */
    private function configFingerprint(): string
    {
        return hash_hmac(
            'sha256',
            json_encode([$this->config->model(), $this->config->reasoningEffort(), $this->apiKey], JSON_THROW_ON_ERROR),
            $this->appKey,
        );
    }

    // ---- worker heartbeat ------------------------------------------------------------

    public function heartbeatStarted(string $workerLabel, DateTimeImmutable $at): string
    {
        if (trim($workerLabel) === '') {
            throw new InvalidArgumentException('heartbeat worker labels must be nonempty');
        }

        $runId = bin2hex(random_bytes(16));
        $this->settings->set(self::HEARTBEAT_KEY, [
            'run_id' => $runId,
            'status' => 'running',
            'worker_label' => substr($workerLabel, 0, self::MAX_WORKER_LABEL_LENGTH),
            'started_at' => $this->formatUtc($at),
            'completed_at' => null,
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ]);
        return $runId;
    }

    /** @param array{processed:int, succeeded:int, failed:int} $counts */
    public function heartbeatFinished(string $runId, string $status, array $counts, DateTimeImmutable $at): void
    {
        if (!in_array($status, ['ok', 'error'], true)) {
            throw new InvalidArgumentException('a heartbeat completes as ok or error');
        }
        foreach (['processed', 'succeeded', 'failed'] as $count) {
            if (!is_int($counts[$count] ?? null) || $counts[$count] < 0) {
                throw new InvalidArgumentException('heartbeat counts must be nonnegative integers');
            }
        }

        $this->db->transaction(function () use ($runId, $status, $counts, $at): void {
            $raw = $this->db->fetchValue(
                'SELECT `value` FROM settings WHERE `key` = ? FOR UPDATE',
                [self::HEARTBEAT_KEY],
            );
            $current = $raw === false || $raw === null ? null : json_decode((string) $raw, true);
            if (!$this->isValidHeartbeatRecord($current)
                || $current['run_id'] !== $runId
                || $current['status'] !== 'running') {
                // The ownership check and write share one row lock. If a newer
                // start committed while this completion waited, it is observed
                // here and the old run cannot overwrite it.
                return;
            }

            $this->db->run(
                'UPDATE settings SET `value` = :value, updated_at = UTC_TIMESTAMP() WHERE `key` = :key',
                [
                    'value' => json_encode([
                        'run_id' => $runId,
                        'status' => $status,
                        'worker_label' => $current['worker_label'] ?? '',
                        'started_at' => $current['started_at'] ?? null,
                        'completed_at' => $this->formatUtc($at),
                        'processed' => $counts['processed'],
                        'succeeded' => $counts['succeeded'],
                        'failed' => $counts['failed'],
                    ], JSON_THROW_ON_ERROR),
                    'key' => self::HEARTBEAT_KEY,
                ],
            );
        });
    }

    /**
     * @return array{exists:bool, corrupt:bool, run_id:?string, status:?string, worker_label:?string,
     *               started_at:?string, completed_at:?string, processed:?int, succeeded:?int, failed:?int}
     */
    public function heartbeat(): array
    {
        $absent = [
            'exists' => false, 'corrupt' => false, 'run_id' => null, 'status' => null, 'worker_label' => null,
            'started_at' => null, 'completed_at' => null, 'processed' => null, 'succeeded' => null, 'failed' => null,
        ];
        if (!$this->settings->has(self::HEARTBEAT_KEY)) {
            return $absent;
        }

        $invalidJson = new \stdClass();
        $value = $this->settings->get(self::HEARTBEAT_KEY, $invalidJson);
        $corrupt = $absent;
        $corrupt['exists'] = true;
        $corrupt['corrupt'] = true;

        if (!$this->isValidHeartbeatRecord($value)) {
            return $corrupt;
        }

        return [
            'exists' => true,
            'corrupt' => false,
            'run_id' => $value['run_id'],
            'status' => $value['status'],
            'worker_label' => $value['worker_label'],
            'started_at' => $value['started_at'],
            'completed_at' => $value['completed_at'],
            'processed' => $value['processed'],
            'succeeded' => $value['succeeded'],
            'failed' => $value['failed'],
        ];
    }

    private function isValidHeartbeatRecord(mixed $value): bool
    {
        if (!is_array($value) || !$this->hasExactKeys($value, self::HEARTBEAT_FIELDS)) {
            return false;
        }
        if (!is_string($value['run_id'])
            || $value['run_id'] === ''
            || strlen($value['run_id']) > self::MAX_RUN_ID_LENGTH
            || !in_array($value['status'], self::HEARTBEAT_STATUSES, true)
            || !is_string($value['worker_label'])
            || $value['worker_label'] === ''
            || strlen($value['worker_label']) > self::MAX_WORKER_LABEL_LENGTH
            || !$this->isValidTime($value['started_at'])
            || !$this->isValidCount($value['processed'])
            || !$this->isValidCount($value['succeeded'])
            || !$this->isValidCount($value['failed'])) {
            return false;
        }

        return $value['status'] === 'running'
            ? $value['completed_at'] === null
            : $this->isValidTime($value['completed_at']);
    }

    /** @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        return $actual === $expected;
    }

    private function isValidTime(mixed $value): bool
    {
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $value) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!' . self::TIME_FORMAT, $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format(self::TIME_FORMAT) === $value;
    }

    private function isValidCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    private function formatUtc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
