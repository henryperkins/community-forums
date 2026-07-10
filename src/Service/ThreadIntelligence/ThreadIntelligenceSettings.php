<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Repository\SettingRepository;
use DateTimeImmutable;
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
    private const TIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly ThreadIntelligenceConfig $config,
        private readonly string $appKey,
        private readonly string $apiKey,
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
            || !is_string($value['code'] ?? null) || strlen($value['code']) > 64
            || !is_string($value['blocked_at'] ?? null)
            || !is_string($value['fingerprint'] ?? null)) {
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
        if ($safeCode === '' || strlen($safeCode) > 64) {
            throw new InvalidArgumentException('provider block codes must be bounded');
        }
        $this->settings->set(self::PROVIDER_HEALTH_KEY, [
            'code' => $safeCode,
            'blocked_at' => $at->format(self::TIME_FORMAT),
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
        $runId = bin2hex(random_bytes(16));
        $this->settings->set(self::HEARTBEAT_KEY, [
            'run_id' => $runId,
            'status' => 'running',
            'worker_label' => substr($workerLabel, 0, 64),
            'started_at' => $at->format(self::TIME_FORMAT),
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

        $invalidJson = new \stdClass();
        $current = $this->settings->get(self::HEARTBEAT_KEY, $invalidJson);
        if (!is_array($current) || ($current['run_id'] ?? null) !== $runId) {
            // A newer run owns the heartbeat; an older completion never overwrites it.
            return;
        }

        $this->settings->set(self::HEARTBEAT_KEY, [
            'run_id' => $runId,
            'status' => $status,
            'worker_label' => $current['worker_label'] ?? '',
            'started_at' => $current['started_at'] ?? null,
            'completed_at' => $at->format(self::TIME_FORMAT),
            'processed' => $counts['processed'],
            'succeeded' => $counts['succeeded'],
            'failed' => $counts['failed'],
        ]);
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

        if (!is_array($value)
            || !is_string($value['run_id'] ?? null)
            || !in_array($value['status'] ?? null, self::HEARTBEAT_STATUSES, true)
            || !is_string($value['worker_label'] ?? null) || strlen($value['worker_label']) > 64
            || !$this->isValidTime($value['started_at'] ?? null)
            || !($value['completed_at'] === null || $this->isValidTime($value['completed_at']))
            || !$this->isValidCount($value['processed'] ?? null)
            || !$this->isValidCount($value['succeeded'] ?? null)
            || !$this->isValidCount($value['failed'] ?? null)) {
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

    private function isValidTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $value) === 1;
    }

    private function isValidCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }
}
