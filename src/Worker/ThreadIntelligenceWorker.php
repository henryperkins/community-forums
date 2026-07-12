<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\StaleThreadIntelligenceEvidence;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePack;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceRetryPolicy;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ValidatedThreadIntelligenceOutput;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Bounded leased orchestration for evidence-bound Thread Intelligence work. */
final class ThreadIntelligenceWorker
{
    public function __construct(
        private readonly Database $db,
        private readonly FeatureFlags $flags,
        private readonly ThreadIntelligenceConfig $config,
        private readonly ThreadIntelligenceSettings $settings,
        private readonly ThreadIntelligenceBudget $budget,
        private readonly ThreadIntelligenceJobRepository $jobs,
        private readonly ThreadIntelligenceGenerationRepository $generations,
        private readonly ThreadIntelligenceBoardSweep $boardSweep,
        private readonly ThreadIntelligenceEligibility $eligibility,
        private readonly ThreadIntelligenceEvidenceBuilder $evidence,
        private readonly ThreadIntelligenceProvider $provider,
        private readonly ThreadIntelligenceOutputValidator $validator,
        private readonly ThreadIntelligenceOutputModerator $moderator,
        private readonly ThreadIntelligencePublisher $publisher,
        private readonly string $fingerprintKey = 'thread-intelligence-request-fingerprint',
    ) {
    }

    /** @return array{processed:int,succeeded:int,failed:int} */
    public function run(int $limit = 25, string $workerLabel = 'cli'): array
    {
        $limit = max(1, min(100, $limit));
        $now = $this->now();
        $counts = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        $runId = $this->settings->heartbeatStarted($workerLabel, $now);
        $heartbeatStatus = 'ok';

        try {
            $this->reconcileAbandoned($now);

            // The sweep owns the sole boards -> jobs exception and commits
            // before claims, provider work, or the canonical thread -> job path.
            $this->boardSweep->runBatch(250, $now);

            if (!$this->siteReady($this->now())) {
                return $counts;
            }

            for ($index = 0; $index < $limit; $index++) {
                $claimNow = $this->now();
                $claimed = $this->jobs->claimDue(1, $claimNow);
                if ($claimed === []) {
                    break;
                }
                $counts['processed']++;
                if ($this->process($claimed[0], $claimNow)) {
                    $counts['succeeded']++;
                } else {
                    $counts['failed']++;
                }

                // An authentication/model failure can engage the latch while
                // processing this row. Stop before another claim.
                if (!$this->siteReady($this->now())) {
                    break;
                }
            }
            return $counts;
        } catch (Throwable $failure) {
            $heartbeatStatus = 'error';
            throw $failure;
        } finally {
            $this->settings->heartbeatFinished(
                $runId,
                $heartbeatStatus,
                $counts,
                $this->now(),
            );
        }
    }

    private function siteReady(DateTimeImmutable $now): bool
    {
        $this->flags->invalidate();
        if (!$this->flags->enabled('community_memory') || !$this->flags->enabled('automated_context')) {
            return false;
        }
        if (!$this->config->providerReady()) {
            return false;
        }
        $pause = $this->settings->generationPause();
        if ($pause['paused'] || $pause['corrupt']) {
            return false;
        }
        $provider = $this->settings->providerHealth();
        if ($provider['blocked'] || $provider['corrupt']) {
            return false;
        }
        $budget = $this->budget->status($now);
        return !$budget['exhausted'] && !$budget['corrupt'];
    }

    /** @param array<string,mixed> $job */
    private function process(array $job, DateTimeImmutable $now): bool
    {
        $threadId = (int) $job['thread_id'];
        $leaseToken = is_string($job['lease_token'] ?? null) ? $job['lease_token'] : '';
        $activityVersion = (int) ($job['activity_version'] ?? -1);
        if ($threadId < 1 || $leaseToken === '' || $activityVersion < 0) {
            return false;
        }

        $this->flags->invalidate();
        $eligibility = $this->eligibility->forGeneration($job, $now);
        if (!$eligibility->eligible) {
            $this->deferClaim($job, $eligibility->code, $eligibility->nextEligibleAt, $now);
            return false;
        }

        // The first audit row precedes evidence assembly. A small baseline-ID
        // read keeps publication lineage consistent without persisting content.
        $baselineId = $this->currentBaselineId($threadId);
        $generationId = $this->startGeneration($job, 0, $baselineId);

        try {
            $pack = $this->evidence->build($threadId, $job);
        } catch (ThreadIntelligenceProviderException $failure) {
            $this->finalizeFailure($generationId, $job, $failure, $this->now());
            return false;
        }

        $carryForward = null;
        for ($window = 0; $window < $pack->windowCount(); $window++) {
            $boundaryNow = $this->now();
            if ($window > 0) {
                // Audit the planned call before constructing material for it.
                $generationId = $this->startGeneration($job, $window, $pack->baselineSummaryId());
            }

            try {
                $request = $this->evidence->requestForWindow($pack, $window, $carryForward);
            } catch (ThreadIntelligenceProviderException $failure) {
                $this->finalizeFailure($generationId, $job, $failure, $this->now());
                return false;
            }

            $reservation = $this->reserveAndRecord($generationId, $pack, $window, $request->promptVersion, $boundaryNow);
            if (!$reservation['reserved']) {
                $this->generations->complete($generationId, [
                    'status' => 'retry',
                    'failure_code' => 'budget_exhausted',
                    'failure_message' => 'budget_exhausted',
                ]);
                $this->jobs->release(
                    $threadId,
                    $leaseToken,
                    $activityVersion,
                    'retry',
                    $reservation['retry_at'] ?? $boundaryNow->modify('+1 minute'),
                    null,
                );
                return false;
            }

            /** @var array{date:string,input_tokens:int} $budgetReservation */
            $budgetReservation = $reservation['reservation'];

            if (!$this->jobs->renewLease(
                $threadId,
                $leaseToken,
                $activityVersion,
                $boundaryNow->modify('+' . ThreadIntelligenceJobRepository::LEASE_SECONDS . ' seconds'),
            )) {
                $this->budget->reconcile($budgetReservation, 0);
                $this->completeIfRequested($generationId, ['status' => 'stale', 'failure_code' => ThreadIntelligenceFailureCode::STALE_EVIDENCE]);
                return false;
            }

            // Recheck the complete shared policy immediately before egress.
            // The current reservation itself may make status() say that a
            // *next* call is exhausted, so that one code is already satisfied.
            $this->flags->invalidate();
            $boundary = $this->eligibility->forGeneration($job, $boundaryNow);
            if (!$boundary->eligible && $boundary->code !== 'budget_exhausted') {
                $this->budget->reconcile($budgetReservation, 0);
                if (in_array($boundary->code, ['board_not_public', 'thread_deleted', 'thread_pending', 'thread_not_found'], true)) {
                    $this->completeIfRequested($generationId, ['status' => 'stale', 'failure_code' => ThreadIntelligenceFailureCode::STALE_EVIDENCE]);
                    $this->jobs->release($threadId, $leaseToken, $activityVersion, 'idle', null, null);
                } else {
                    $this->completeIfRequested($generationId, ['status' => 'retry', 'failure_code' => $boundary->code]);
                    $this->jobs->release(
                        $threadId,
                        $leaseToken,
                        $activityVersion,
                        'retry',
                        $boundary->nextEligibleAt ?? $boundaryNow->modify('+1 minute'),
                        null,
                    );
                }
                return false;
            }

            if (!$this->evidence->snapshotIsCurrent($pack, $job)) {
                $this->budget->reconcile($budgetReservation, 0);
                $this->completeIfRequested($generationId, [
                    'status' => 'stale',
                    'failure_code' => ThreadIntelligenceFailureCode::STALE_EVIDENCE,
                ]);
                $this->jobs->release(
                    $threadId,
                    $leaseToken,
                    $activityVersion,
                    'queued',
                    $boundaryNow,
                    null,
                );
                return false;
            }

            try {
                // Provider and moderator are deliberately outside every DB
                // transaction. Only typed request/result objects cross here.
                $result = $this->provider->generate($request);
            } catch (ThreadIntelligenceProviderException $failure) {
                $this->budget->reconcile($budgetReservation, null);
                $this->finalizeFailure($generationId, $job, $failure, $this->now());
                return false;
            }

            $this->budget->reconcile($budgetReservation, $result->usage->inputTokens);

            try {
                $validated = $this->validator->validate($result, $request);

                // Moderation is a second external provider request. Renew and
                // re-run every live privacy/rollback gate after generation so
                // content that became private during the first call never
                // crosses the moderation boundary.
                $moderationNow = $this->now();
                if (!$this->jobs->renewLease(
                    $threadId,
                    $leaseToken,
                    $activityVersion,
                    $moderationNow->modify('+' . ThreadIntelligenceJobRepository::LEASE_SECONDS . ' seconds'),
                )) {
                    $terminal = $this->terminalEvidence('stale', $result);
                    $terminal['failure_code'] = ThreadIntelligenceFailureCode::STALE_EVIDENCE;
                    $this->completeIfRequested($generationId, $terminal);
                    return false;
                }

                $this->flags->invalidate();
                $moderationBoundary = $this->eligibility->forGeneration($job, $moderationNow);
                if (!$moderationBoundary->eligible && $moderationBoundary->code !== 'budget_exhausted') {
                    $visibilityFailure = in_array(
                        $moderationBoundary->code,
                        ['board_not_public', 'thread_deleted', 'thread_pending', 'thread_not_found'],
                        true,
                    );
                    $terminal = $this->terminalEvidence($visibilityFailure ? 'stale' : 'retry', $result);
                    $terminal['failure_code'] = $visibilityFailure
                        ? ThreadIntelligenceFailureCode::STALE_EVIDENCE
                        : $moderationBoundary->code;
                    $this->completeIfRequested($generationId, $terminal);
                    $this->jobs->release(
                        $threadId,
                        $leaseToken,
                        $activityVersion,
                        $visibilityFailure ? 'idle' : 'retry',
                        $visibilityFailure ? null : ($moderationBoundary->nextEligibleAt ?? $moderationNow->modify('+1 minute')),
                        null,
                    );
                    return false;
                }


                if (!$this->evidence->snapshotIsCurrent($pack, $job)) {
                    $terminal = $this->terminalEvidence('stale', $result);
                    $terminal['failure_code'] = ThreadIntelligenceFailureCode::STALE_EVIDENCE;
                    $this->completeIfRequested($generationId, $terminal);
                    $this->jobs->release(
                        $threadId,
                        $leaseToken,
                        $activityVersion,
                        'queued',
                        $moderationNow,
                        null,
                    );
                    return false;
                }

                $moderation = $this->moderator->moderate($validated->moderationText());
                if ($moderation->flagged) {
                    throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::MODERATION_FLAGGED);
                }
            } catch (ThreadIntelligenceProviderException $failure) {
                $this->finalizeFailure($generationId, $job, $failure, $this->now(), $result);
                return false;
            }

            if ($window + 1 < $pack->windowCount()) {
                $this->generations->complete($generationId, $this->terminalEvidence('succeeded', $result));
                $carryForward = $validated;
                continue;
            }

            try {
                $this->publisher->publish($generationId, $leaseToken, $job, $pack, $validated);
                return true;
            } catch (StaleThreadIntelligenceEvidence) {
                // Publisher owns stale completion and lease-safe requeue.
                return false;
            }
        }

        return false;
    }

    /**
     * @return array{reserved:bool,reservation:?array{date:string,input_tokens:int},retry_at:?DateTimeImmutable,corrupt:bool}
     */
    private function reserveAndRecord(
        int $generationId,
        ThreadIntelligenceEvidencePack $pack,
        int $window,
        string $promptVersion,
        DateTimeImmutable $now,
    ): array {
        return $this->db->transaction(function () use ($generationId, $pack, $window, $promptVersion, $now): array {
            $reservation = $this->budget->reserve($now);
            if (!$reservation['reserved']) {
                return $reservation;
            }
            $fingerprintPayload = json_encode([
                'generation_id' => $generationId,
                'thread_id' => $pack->threadId(),
                'snapshot_hash' => $pack->snapshotHash(),
                'source_post_ids' => $pack->sourcePostIds(),
                'candidate_thread_ids' => $pack->candidateThreadIds(),
                'window' => $window,
                'prompt_version' => $promptVersion,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->generations->recordRequest(
                $generationId,
                $pack->snapshotHash(),
                $pack->sourcePostIds(),
                $pack->candidateThreadIds(),
                hash_hmac('sha256', $fingerprintPayload, $this->fingerprintKey),
                $pack->estimatedInputTokens($window),
            );
            return $reservation;
        });
    }

    /** @param array<string,mixed> $job */
    private function startGeneration(array $job, int $window, ?int $baselineId): int
    {
        return $this->generations->start([
            'thread_id' => (int) $job['thread_id'],
            'trigger_code' => (string) ($job['trigger_code'] ?? 'unknown'),
            'retry_number' => max(0, (int) ($job['attempt_count'] ?? 0)),
            'window_number' => $window,
            'baseline_summary_id' => $baselineId,
            'model' => $this->config->model(),
            'reasoning_effort' => $this->config->reasoningEffort(),
            'prompt_version' => \App\Service\ThreadIntelligence\ThreadIntelligencePromptBuilder::VERSION,
        ]);
    }

    private function currentBaselineId(int $threadId): ?int
    {
        $value = $this->db->fetchValue(
            "SELECT id FROM thread_summaries WHERE thread_id = ? AND status = 'published' ORDER BY version DESC, id DESC LIMIT 1",
            [$threadId],
        );
        return $value === false || $value === null ? null : (int) $value;
    }

    /** @param array<string,mixed> $job */
    private function deferClaim(array $job, string $code, ?DateTimeImmutable $next, DateTimeImmutable $now): void
    {
        $idleCodes = [
            'thread_not_found', 'board_not_public', 'thread_deleted', 'thread_pending',
            'initial_post_threshold', 'post_delta_threshold', 'terminal_state',
        ];
        $this->jobs->release(
            (int) $job['thread_id'],
            (string) $job['lease_token'],
            (int) $job['activity_version'],
            in_array($code, $idleCodes, true) ? 'idle' : 'retry',
            in_array($code, $idleCodes, true) ? null : ($next ?? $now->modify('+1 minute')),
            null,
        );
    }

    /** @param array<string,mixed> $job */
    private function finalizeFailure(
        int $generationId,
        array $job,
        ThreadIntelligenceProviderException $failure,
        DateTimeImmutable $now,
        ?\App\Service\ThreadIntelligence\ThreadIntelligenceResult $result = null,
    ): void {
        $code = $failure->safeCode();
        [$sameFailureCount, $transientRetryCount] = $this->failureHistory(
            (int) $job['thread_id'],
            $generationId,
            $code,
        );
        $decision = ThreadIntelligenceRetryPolicy::decision($code, $sameFailureCount, $transientRetryCount);
        $terminal = $this->terminalEvidence($decision['generation_status'], $result);
        $terminal['failure_code'] = $code;
        $terminal['failure_message'] = $code;
        $this->completeIfRequested($generationId, $terminal);

        if ($decision['latch_provider'] || $failure->blocksProvider()) {
            $this->settings->blockProvider($code, $now);
        }

        $dueAt = null;
        if ($decision['job_state'] === 'queued') {
            $dueAt = $now;
        } elseif ($decision['job_state'] === 'retry') {
            $seconds = $decision['delay_seconds'];
            if ($seconds === null) {
                $seconds = ThreadIntelligenceRetryPolicy::transientDelaySeconds(
                    (int) $job['thread_id'],
                    $decision['retry_number'],
                    $failure->retryAfterSeconds(),
                );
            }
            $dueAt = $now->modify('+' . $seconds . ' seconds');
        }

        $this->jobs->release(
            (int) $job['thread_id'],
            (string) $job['lease_token'],
            (int) $job['activity_version'],
            $decision['job_state'],
            $dueAt,
            $decision['increment_attempt'] ? $code : null,
        );
    }

    /** @return array<string,mixed> */
    private function terminalEvidence(
        string $status,
        ?\App\Service\ThreadIntelligence\ThreadIntelligenceResult $result,
    ): array {
        return [
            'status' => $status,
            'provider_response_id' => $result?->responseId,
            'input_tokens' => $result?->usage->inputTokens,
            'output_tokens' => $result?->usage->outputTokens,
            'reasoning_tokens' => $result?->usage->reasoningTokens,
            'cached_tokens' => $result?->usage->cachedTokens,
        ];
    }

    /** @param array<string,mixed> $evidence */
    private function completeIfRequested(int $generationId, array $evidence): void
    {
        $status = $this->db->fetchValue('SELECT status FROM thread_intelligence_generations WHERE id = ?', [$generationId]);
        if ($status === 'requested') {
            $this->generations->complete($generationId, $evidence);
        }
    }

    private function reconcileAbandoned(DateTimeImmutable $now): void
    {
        $cutoff = $now->modify('-' . ThreadIntelligenceJobRepository::LEASE_SECONDS . ' seconds');
        foreach ($this->generations->abandonedRequested($cutoff, 100) as $candidate) {
            $this->db->transaction(function () use ($candidate, $cutoff, $now): void {
                $threadId = (int) $candidate['thread_id'];

                // Mandatory canonical order: owning job, then generation.
                $job = $this->jobs->findForUpdate($threadId);
                $generation = $this->db->fetch(
                    'SELECT * FROM thread_intelligence_generations WHERE id = ? FOR UPDATE',
                    [(int) $candidate['id']],
                );
                if ($generation === null
                    || (int) $generation['thread_id'] !== $threadId
                    || $generation['status'] !== 'requested'
                    || !is_string($generation['requested_at'] ?? null)
                    || strcmp($generation['requested_at'], $cutoff->format('Y-m-d H:i:s')) > 0) {
                    return;
                }

                $activeLease = $job !== null
                    && $job['state'] === 'running'
                    && is_string($job['lease_expires_at'] ?? null)
                    && strcmp($job['lease_expires_at'], $now->format('Y-m-d H:i:s')) > 0;
                if ($activeLease) {
                    return;
                }

                // Revalidation above is immediately adjacent to settlement and
                // completion under the same locks; discovery data is never trusted.
                if (is_string($generation['request_fingerprint'] ?? null)
                    && $generation['request_fingerprint'] !== '') {
                    $requestedAt = DateTimeImmutable::createFromFormat(
                        '!Y-m-d H:i:s',
                        $generation['requested_at'],
                        new DateTimeZone('UTC'),
                    );
                    if ($requestedAt !== false) {
                        $this->budget->settleAbandoned($requestedAt, $now);
                    }
                }
                $this->generations->complete((int) $generation['id'], [
                    'status' => 'failed',
                    'failure_code' => 'worker_interrupted',
                    'failure_message' => 'worker_interrupted',
                ]);
            });
        }
    }

    /** @return array{0:int,1:int} current same-code count and prior consecutive transient count */
    private function failureHistory(int $threadId, int $generationId, string $currentCode): array
    {
        $rows = $this->db->fetchAll(
            "SELECT failure_code
             FROM thread_intelligence_generations
             WHERE thread_id = ? AND id < ? AND failure_code IS NOT NULL
               AND status IN ('retry','failed','dead','review_required','rejected','stale')
             ORDER BY id DESC
             LIMIT 20",
            [$threadId, $generationId],
        );

        $same = 1;
        foreach ($rows as $row) {
            $code = (string) $row['failure_code'];
            if (ThreadIntelligenceRetryPolicy::isDeferral($code)) {
                continue;
            }
            if ($code !== $currentCode) {
                break;
            }
            $same++;
        }

        $transient = 0;
        foreach ($rows as $row) {
            $code = (string) $row['failure_code'];
            if (ThreadIntelligenceRetryPolicy::isDeferral($code)) {
                continue;
            }
            if (!ThreadIntelligenceRetryPolicy::isTransientFailure($code)) {
                break;
            }
            $transient++;
        }

        return [$same, $transient];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
