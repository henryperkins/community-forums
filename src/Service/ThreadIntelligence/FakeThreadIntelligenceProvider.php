<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use LogicException;

/**
 * Deterministic test/evidence provider: dequeues scripted results or typed
 * exceptions in order. Requests are retained in process memory only (so tests
 * can assert the privacy boundary and window sequencing); the redacted
 * metadata view is what anything durable would be allowed to see.
 */
final class FakeThreadIntelligenceProvider implements ThreadIntelligenceProvider
{
    /** @var list<ThreadIntelligenceResult|ThreadIntelligenceProviderException> */
    private array $queue = [];

    /** @var list<ThreadIntelligenceRequest> */
    private array $requests = [];

    public function queueResult(ThreadIntelligenceResult $result): void
    {
        $this->queue[] = $result;
    }

    public function queueException(ThreadIntelligenceProviderException $exception): void
    {
        $this->queue[] = $exception;
    }

    public function generate(ThreadIntelligenceRequest $request): ThreadIntelligenceResult
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new LogicException('FakeThreadIntelligenceProvider: no scripted outcome queued');
        }
        $next = array_shift($this->queue);
        if ($next instanceof ThreadIntelligenceProviderException) {
            throw $next;
        }
        return $next;
    }

    /** @return list<ThreadIntelligenceRequest> every request seen, in order (test memory only) */
    public function requests(): array
    {
        return $this->requests;
    }

    public function callCount(): int
    {
        return count($this->requests);
    }

    /** @return list<array<string,int|string|null>> the redacted per-request metadata view */
    public function recordedMetadata(): array
    {
        return array_map(static fn (ThreadIntelligenceRequest $request): array => [
            'thread_id' => $request->threadId,
            'window_number' => $request->windowNumber,
            'window_count' => $request->windowCount,
            'post_count' => count($request->posts),
            'candidate_count' => count($request->candidates),
            'source_snapshot_hash' => $request->sourceSnapshotHash,
            'prompt_version' => $request->promptVersion,
            'baseline_summary_id' => $request->baseline?->summaryId,
        ], $this->requests);
    }
}
