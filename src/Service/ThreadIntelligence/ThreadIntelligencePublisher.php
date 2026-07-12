<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Service\ContentReferenceService;
use App\Support\Markdown;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

/**
 * Commits one already-validated brief as a single public state transition.
 *
 * This service deliberately has no provider or moderation dependency. The
 * source thread is the canonical serialization lock for AI and curator writes;
 * every mutable evidence check is repeated after that lock is held.
 */
final class ThreadIntelligencePublisher
{
    public function __construct(
        private readonly Database $db,
        private readonly ThreadRepository $threads,
        private readonly ThreadIntelligenceJobRepository $jobs,
        private readonly ThreadIntelligenceGenerationRepository $generations,
        private readonly ThreadIntelligenceEvidenceBuilder $evidenceBuilder,
        private readonly Markdown $markdown,
        private readonly ContentReferenceService $contentReferences,
    ) {
    }

    /** @param array<string,mixed> $job */
    public function publish(
        int $generationId,
        string $leaseToken,
        array $job,
        ThreadIntelligenceEvidencePack $evidence,
        ValidatedThreadIntelligenceOutput $output,
    ): ThreadIntelligencePublishResult {
        if ($generationId < 1 || $leaseToken === '') {
            throw new InvalidArgumentException('publication requires a generation and lease token');
        }
        $expectedActivityVersion = filter_var(
            $job['activity_version'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );
        if ($expectedActivityVersion === false
            || (int) ($job['thread_id'] ?? 0) !== $evidence->threadId()) {
            throw new InvalidArgumentException('publication job must match the evidence thread and activity version');
        }

        $publishedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        try {
            return $this->db->transaction(function () use (
                $generationId,
                $leaseToken,
                $job,
                $evidence,
                $output,
                $publishedAt,
                $expectedActivityVersion,
            ): ThreadIntelligencePublishResult {
                $thread = $this->threads->findForUpdate($evidence->threadId());
                $lockedJob = $this->jobs->findForUpdate($evidence->threadId());
                $this->assertLeaseAndActivity($lockedJob, $leaseToken, (int) $expectedActivityVersion);

                // Dependent rows are locked only after the owning thread and
                // job. These range locks also serialize curator promotion and
                // absent relationship insertion for this source thread.
                $this->db->fetchAll(
                    'SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY id FOR UPDATE',
                    [$evidence->threadId()],
                );
                $postRows = $this->db->fetchAll(
                    'SELECT id, thread_id, is_deleted, is_pending FROM posts WHERE thread_id = ? ORDER BY id FOR UPDATE',
                    [$evidence->threadId()],
                );
                $this->db->fetchAll(
                    "SELECT id FROM related_threads
                     WHERE source_thread_id = ? AND relation_type = 'related'
                     ORDER BY id FOR UPDATE",
                    [$evidence->threadId()],
                );

                $generation = $this->db->fetch(
                    'SELECT * FROM thread_intelligence_generations WHERE id = ? FOR UPDATE',
                    [$generationId],
                );
                $this->assertRequestedGeneration($generation, $generationId, $evidence);
                $this->assertCurrentEvidence($thread, $lockedJob, $evidence, $output, $postRows);

                $summaryId = $this->insertAiSummaryAndSources($evidence, $output, $publishedAt);
                $this->contentReferences->capture('summary', $summaryId, $output->canonicalMarkdown());
                $this->replaceAiRelationshipOverlays($generationId, $evidence, $output, $publishedAt);
                $this->generations->complete($generationId, [
                    'status' => 'published',
                    'published_summary_id' => $summaryId,
                    'published_at' => $publishedAt->format('Y-m-d H:i:s'),
                ]);

                // Task 4 requires the ledger row to be terminal-published
                // before its checkpoint can be released.
                if (!$this->jobs->releasePublished(
                    $evidence->threadId(),
                    $leaseToken,
                    (int) $expectedActivityVersion,
                    $generationId,
                    $evidence->lastPostId(),
                    $evidence->snapshotHash(),
                    $evidence->fullReconcile(),
                    $publishedAt,
                )) {
                    throw new StaleThreadIntelligenceEvidence('activity_version_changed');
                }

                return new ThreadIntelligencePublishResult($summaryId, $generationId);
            });
        } catch (StaleThreadIntelligenceEvidence $stale) {
            // With an owning transaction, the failed public transition has
            // rolled back. Nested callers are safe because every normal stale
            // condition is detected above before a public mutation.
            $this->finalizeStale(
                $generationId,
                $evidence->threadId(),
                $leaseToken,
                (int) $expectedActivityVersion,
                $publishedAt,
            );
            throw $stale;
        }
    }

    /** @param array<string,mixed>|null $job */
    private function assertLeaseAndActivity(?array $job, string $leaseToken, int $expectedActivityVersion): void
    {
        if ($job === null || $job['state'] !== 'running') {
            throw new StaleThreadIntelligenceEvidence('lease_inactive');
        }
        if (!is_string($job['lease_token']) || !hash_equals($job['lease_token'], $leaseToken)) {
            throw new StaleThreadIntelligenceEvidence('lease_changed');
        }
        if ((int) $job['activity_version'] !== $expectedActivityVersion) {
            throw new StaleThreadIntelligenceEvidence('activity_version_changed');
        }
        if ((int) ($job['automation_paused'] ?? 0) === 1) {
            throw new StaleThreadIntelligenceEvidence('automation_paused');
        }
    }

    /** @param array<string,mixed>|null $generation */
    private function assertRequestedGeneration(
        ?array $generation,
        int $generationId,
        ThreadIntelligenceEvidencePack $evidence,
    ): void {
        if ($generation === null) {
            throw new LogicException('publication generation does not exist: ' . $generationId);
        }
        if ((int) $generation['thread_id'] !== $evidence->threadId()) {
            throw new LogicException('publication generation belongs to another thread');
        }
        if ($generation['status'] !== 'requested') {
            throw new LogicException('publication generation is already terminal');
        }
        if ($this->nullablePositiveInt($generation['baseline_summary_id'] ?? null) !== $evidence->baselineSummaryId()) {
            throw new StaleThreadIntelligenceEvidence('generation_baseline_changed');
        }
        if (is_string($generation['source_snapshot_hash'] ?? null)
            && !hash_equals($generation['source_snapshot_hash'], $evidence->snapshotHash())) {
            throw new StaleThreadIntelligenceEvidence('generation_snapshot_changed');
        }
        foreach ([
            'source_post_ids' => $evidence->sourcePostIds(),
            'candidate_thread_ids' => $evidence->candidateThreadIds(),
        ] as $column => $expected) {
            if ($generation[$column] === null) {
                continue;
            }
            $decoded = json_decode((string) $generation[$column], true);
            if (!is_array($decoded) || array_map('intval', $decoded) !== $expected) {
                throw new StaleThreadIntelligenceEvidence('generation_evidence_changed');
            }
        }
    }

    /**
     * @param array<string,mixed>|null $thread
     * @param array<string,mixed> $lockedJob
     * @param list<array<string,mixed>> $postRows
     */
    private function assertCurrentEvidence(
        ?array $thread,
        array $lockedJob,
        ThreadIntelligenceEvidencePack $evidence,
        ValidatedThreadIntelligenceOutput $output,
        array $postRows,
    ): void {
        if ($thread === null) {
            throw new StaleThreadIntelligenceEvidence('thread_removed');
        }
        if ((int) $thread['is_deleted'] !== 0 || (int) $thread['is_pending'] !== 0) {
            throw new StaleThreadIntelligenceEvidence('thread_not_live');
        }
        $board = $this->db->fetch(
            'SELECT id, visibility FROM boards WHERE id = ? LOCK IN SHARE MODE',
            [(int) $thread['board_id']],
        );
        if ($board === null || $board['visibility'] !== 'public') {
            throw new StaleThreadIntelligenceEvidence('visibility_changed');
        }

        $currentBaseline = $this->db->fetch(
            "SELECT id FROM thread_summaries
             WHERE thread_id = ? AND status = 'published'
             ORDER BY version DESC, id DESC LIMIT 1 FOR UPDATE",
            [$evidence->threadId()],
        );
        $currentBaselineId = $currentBaseline === null ? null : (int) $currentBaseline['id'];
        if ($currentBaselineId !== $evidence->baselineSummaryId()) {
            throw new StaleThreadIntelligenceEvidence('baseline_changed');
        }

        $byId = [];
        foreach ($postRows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        // Historical baseline citations remain in the evidence pack so the
        // model can replace them. Only citations carried into the new public
        // brief must still be live; otherwise an already-ineligible baseline
        // could never recover.
        foreach ($output->sourcePostIds() as $sourceId) {
            $row = $byId[$sourceId] ?? null;
            if ($row === null
                || (int) $row['thread_id'] !== $evidence->threadId()
                || (int) $row['is_deleted'] !== 0
                || (int) $row['is_pending'] !== 0) {
                throw new StaleThreadIntelligenceEvidence('source_changed');
            }
        }
        if (array_diff($output->sourcePostIds(), $evidence->sourcePostIds()) !== []) {
            throw new StaleThreadIntelligenceEvidence('citation_changed');
        }
        if (array_diff($output->relatedThreadIds(), $evidence->candidateThreadIds()) !== []) {
            throw new StaleThreadIntelligenceEvidence('candidate_changed');
        }

        try {
            $current = $this->evidenceBuilder->build($evidence->threadId(), $lockedJob);
        } catch (ThreadIntelligenceProviderException) {
            // Growth or source changes may make previously bounded evidence no
            // longer rebuildable. This validated response is stale; it is not
            // a new provider failure and must settle/requeue through the common
            // stale path.
            throw new StaleThreadIntelligenceEvidence('evidence_recompute_failed');
        }
        if ($current->baselineSummaryId() !== $evidence->baselineSummaryId()) {
            throw new StaleThreadIntelligenceEvidence('baseline_changed');
        }
        if ($current->sourcePostIds() !== $evidence->sourcePostIds()) {
            throw new StaleThreadIntelligenceEvidence('source_changed');
        }
        if ($current->candidateThreadIds() !== $evidence->candidateThreadIds()) {
            throw new StaleThreadIntelligenceEvidence('candidate_changed');
        }
        if (!hash_equals($current->snapshotHash(), $evidence->snapshotHash())
            || $current->lastPostId() !== $evidence->lastPostId()
            || $current->fullReconcile() !== $evidence->fullReconcile()) {
            throw new StaleThreadIntelligenceEvidence('source_snapshot_changed');
        }
    }

    private function insertAiSummaryAndSources(
        ThreadIntelligenceEvidencePack $evidence,
        ValidatedThreadIntelligenceOutput $output,
        DateTimeImmutable $publishedAt,
    ): int {
        $version = 1 + (int) $this->db->fetchValue(
            'SELECT COALESCE(MAX(version), 0) FROM thread_summaries WHERE thread_id = ?',
            [$evidence->threadId()],
        );
        $stamp = $publishedAt->format('Y-m-d H:i:s');
        $this->db->run(
            "UPDATE thread_summaries
             SET status = 'retired', retired_at = ?, updated_at = ?
             WHERE thread_id = ? AND status = 'published'",
            [$stamp, $stamp, $evidence->threadId()],
        );
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, reviewer_id,
                 parent_summary_id, published_at, created_at)
             VALUES (?, 'ai', 'published', ?, ?, ?, NULL, NULL, ?, ?, ?)",
            [
                $evidence->threadId(),
                $output->canonicalMarkdown(),
                $this->markdown->render($output->canonicalMarkdown()),
                $version,
                $evidence->baselineSummaryId(),
                $stamp,
                $stamp,
            ],
        );
        foreach ($output->sourcePostIds() as $postId) {
            $this->db->run(
                'INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
                [$summaryId, $postId],
            );
        }
        return $summaryId;
    }

    private function replaceAiRelationshipOverlays(
        int $generationId,
        ThreadIntelligenceEvidencePack $evidence,
        ValidatedThreadIntelligenceOutput $output,
        DateTimeImmutable $publishedAt,
    ): void {
        $stamp = $publishedAt->format('Y-m-d H:i:s');
        foreach ($output->relatedTopics() as $topic) {
            $this->db->run(
                "INSERT INTO related_threads
                    (source_thread_id, related_thread_id, relation_type, source, reason, status, curator_id,
                     ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
                 VALUES (?, ?, 'related', 'search', NULL, 'approved', NULL, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    status = CASE WHEN source IN ('tag', 'search') THEN 'approved' ELSE status END,
                    ai_generation_id = CASE WHEN source IN ('tag', 'search') THEN VALUES(ai_generation_id) ELSE ai_generation_id END,
                    ai_reason = CASE WHEN source IN ('tag', 'search') THEN VALUES(ai_reason) ELSE ai_reason END,
                    ai_selected = CASE WHEN source IN ('tag', 'search') THEN 1 ELSE ai_selected END,
                    ai_selected_at = CASE WHEN source IN ('tag', 'search') THEN VALUES(ai_selected_at) ELSE ai_selected_at END",
                [
                    $evidence->threadId(),
                    $topic['thread_id'],
                    $generationId,
                    mb_substr($topic['explanation'], 0, 255),
                    $stamp,
                    $stamp,
                ],
            );
        }

        // New selections now exist. Retire only older AI overlay state and do
        // not touch curator/merge-owned rows or their reasons/identities.
        $this->db->run(
            "UPDATE related_threads
             SET ai_generation_id = NULL, ai_reason = NULL, ai_selected = 0, ai_selected_at = NULL
             WHERE source_thread_id = ? AND relation_type = 'related'
               AND source IN ('tag', 'search') AND ai_selected = 1
               AND (ai_generation_id IS NULL OR ai_generation_id <> ?)",
            [$evidence->threadId(), $generationId],
        );
    }

    private function finalizeStale(
        int $generationId,
        int $threadId,
        string $leaseToken,
        int $expectedActivityVersion,
        DateTimeImmutable $now,
    ): void {
        $this->db->transaction(function () use ($generationId, $threadId, $leaseToken, $expectedActivityVersion, $now): void {
            // Retain the same public mutation order for the stale settlement.
            $this->threads->findForUpdate($threadId);
            $job = $this->jobs->findForUpdate($threadId);
            $generation = $this->db->fetch(
                'SELECT thread_id, status FROM thread_intelligence_generations WHERE id = ? FOR UPDATE',
                [$generationId],
            );
            if ($generation === null || (int) $generation['thread_id'] !== $threadId) {
                return;
            }
            if ($generation['status'] === 'requested') {
                $this->generations->complete($generationId, ['status' => 'stale']);
            }

            // Never surrender a newer worker's foreign lease. If this response
            // still owns the token, release() either queues the same activity or
            // detects a newer version and queues that version atomically.
            if ($job !== null
                && $job['state'] === 'running'
                && is_string($job['lease_token'])
                && hash_equals($job['lease_token'], $leaseToken)) {
                $this->jobs->release(
                    $threadId,
                    $leaseToken,
                    $expectedActivityVersion,
                    'queued',
                    $now,
                    null,
                );
            }
        });
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int) $parsed;
    }
}
