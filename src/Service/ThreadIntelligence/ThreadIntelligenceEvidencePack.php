<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use WeakMap;

/**
 * One immutable local evidence plan under one stable source snapshot.
 *
 * Raw evidence slices remain private and all generic/debug serialization is
 * metadata-only. The sole intentional evidence exit is a typed request for one
 * validated chronological window.
 */
final class ThreadIntelligenceEvidencePack implements JsonSerializable
{
    /** @var WeakMap<self,array<string,mixed>>|null */
    private static ?WeakMap $payloads = null;

    private readonly ?int $baselineSummaryId;
    /** @var list<int> */
    private readonly array $candidateThreadIds;
    private readonly int $windowCount;
    private readonly bool $requestEligible;

    /**
     * @param list<int> $sourcePostIds
     * @param list<ThreadIntelligenceRelatedCandidate> $candidates
     * @param list<list<ThreadIntelligenceEvidencePost>> $slices
     * @param list<int> $estimatedInputTokens
     */
    public function __construct(
        private readonly int $threadId,
        string $threadTitle,
        ?ThreadIntelligenceBaseline $baseline,
        private readonly array $sourcePostIds,
        array $candidates,
        private readonly string $snapshotHash,
        private readonly int $lastPostId,
        private readonly bool $fullReconcile,
        string $boardVisibility,
        array $slices,
        private readonly array $estimatedInputTokens,
    ) {
        if ($threadId < 1 || $lastPostId < 0) {
            throw new InvalidArgumentException('evidence pack IDs must be nonnegative and the thread positive');
        }
        if (preg_match('/\A[0-9a-f]{64}\z/', $snapshotHash) !== 1) {
            throw new InvalidArgumentException('snapshot hash must be lowercase SHA-256');
        }
        if (!array_is_list($sourcePostIds) || !array_is_list($candidates) || !array_is_list($slices) || !array_is_list($estimatedInputTokens)) {
            throw new InvalidArgumentException('evidence pack collections must be lists');
        }
        if (count($slices) < 1 || count($slices) > 4 || count($slices) !== count($estimatedInputTokens)) {
            throw new InvalidArgumentException('evidence pack must contain one to four estimated slices');
        }
        foreach ($sourcePostIds as $sourcePostId) {
            if (!is_int($sourcePostId) || $sourcePostId < 1) {
                throw new InvalidArgumentException('source post IDs must be positive integers');
            }
        }
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof ThreadIntelligenceRelatedCandidate) {
                throw new InvalidArgumentException('pack candidates must be typed');
            }
        }
        foreach ($slices as $slice) {
            if (!is_array($slice) || !array_is_list($slice)) {
                throw new InvalidArgumentException('evidence slices must be lists');
            }
            foreach ($slice as $post) {
                if (!$post instanceof ThreadIntelligenceEvidencePost) {
                    throw new InvalidArgumentException('evidence slices must contain typed posts');
                }
            }
        }
        foreach ($estimatedInputTokens as $estimate) {
            if (!is_int($estimate) || $estimate < 1) {
                throw new InvalidArgumentException('slice estimates must be positive integers');
            }
        }

        $this->baselineSummaryId = $baseline?->summaryId;
        $this->candidateThreadIds = array_map(
            static fn (ThreadIntelligenceRelatedCandidate $candidate): int => $candidate->threadId,
            $candidates,
        );
        $this->windowCount = count($slices);
        $this->requestEligible = $boardVisibility === 'public';
        self::$payloads ??= new WeakMap();
        self::$payloads[$this] = [
            'thread_title' => $threadTitle,
            'baseline' => $baseline,
            'candidates' => $candidates,
            'slices' => $slices,
        ];
    }

    public function threadId(): int
    {
        return $this->threadId;
    }

    public function baselineSummaryId(): ?int
    {
        return $this->baselineSummaryId;
    }

    /** @return list<int> */
    public function sourcePostIds(): array
    {
        return $this->sourcePostIds;
    }

    /** @return list<int> */
    public function candidateThreadIds(): array
    {
        return $this->candidateThreadIds;
    }

    public function snapshotHash(): string
    {
        return $this->snapshotHash;
    }

    public function lastPostId(): int
    {
        return $this->lastPostId;
    }

    public function fullReconcile(): bool
    {
        return $this->fullReconcile;
    }

    public function windowCount(): int
    {
        return $this->windowCount;
    }

    public function estimatedInputTokens(int $windowIndex): int
    {
        $this->assertWindowIndex($windowIndex);
        return $this->estimatedInputTokens[$windowIndex];
    }

    /** @internal requests are created iteratively by ThreadIntelligenceEvidenceBuilder */
    public function request(int $windowIndex, ?ValidatedThreadIntelligenceOutput $carryForward): ThreadIntelligenceRequest
    {
        $this->assertWindowIndex($windowIndex);
        if ($windowIndex === 0 && $carryForward !== null) {
            throw new InvalidArgumentException('window 0 must not have a carry-forward');
        }
        if ($windowIndex > 0 && $carryForward === null) {
            throw new InvalidArgumentException('later evidence windows require a validated carry-forward');
        }
        if (!$this->requestEligible) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::VALIDATION_FAILED);
        }
        $payload = $this->payload();

        return new ThreadIntelligenceRequest(
            threadId: $this->threadId,
            threadTitle: $payload['thread_title'],
            baseline: $payload['baseline'],
            carryForward: $carryForward === null ? null : ThreadIntelligenceCarryForward::fromValidated($carryForward),
            posts: $payload['slices'][$windowIndex],
            candidates: $payload['candidates'],
            sourceSnapshotHash: $this->snapshotHash,
            promptVersion: ThreadIntelligencePromptBuilder::VERSION,
            windowNumber: $windowIndex,
            windowCount: $this->windowCount,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->safeMetadata();
    }

    /** @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return $this->safeMetadata();
    }

    /** @return array<string,mixed> */
    public function __serialize(): array
    {
        return $this->safeMetadata();
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('metadata-only evidence packs cannot be deserialized');
    }

    /** Identity-keyed request material cannot be inherited by a clone. */
    private function __clone()
    {
    }

    private function assertWindowIndex(int $windowIndex): void
    {
        if ($windowIndex < 0 || $windowIndex >= $this->windowCount) {
            throw new InvalidArgumentException('window index is outside this evidence pack');
        }
    }

    /** @return array<string,mixed> */
    private function safeMetadata(): array
    {
        return [
            'thread_id' => $this->threadId,
            'baseline_summary_id' => $this->baselineSummaryId,
            'source_post_ids' => $this->sourcePostIds,
            'candidate_thread_ids' => $this->candidateThreadIds(),
            'snapshot_hash' => $this->snapshotHash,
            'last_post_id' => $this->lastPostId,
            'full_reconcile' => $this->fullReconcile,
            'window_count' => $this->windowCount,
            'estimated_input_tokens' => $this->estimatedInputTokens,
        ];
    }

    /** @return array{thread_title:string,baseline:?ThreadIntelligenceBaseline,candidates:list<ThreadIntelligenceRelatedCandidate>,slices:list<list<ThreadIntelligenceEvidencePost>>} */
    private function payload(): array
    {
        if (self::$payloads === null || !isset(self::$payloads[$this])) {
            throw new LogicException('evidence request material is unavailable after serialization');
        }
        /** @var array{thread_title:string,baseline:?ThreadIntelligenceBaseline,candidates:list<ThreadIntelligenceRelatedCandidate>,slices:list<list<ThreadIntelligenceEvidencePost>>} */
        return self::$payloads[$this];
    }
}
