<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\TestCase;

/**
 * Pins the bounded evidence-retention policy (plan Task 4): published rows
 * live with their thread, unpublished terminal rows expire 90 days after
 * completion, evidence behind a live dead/review_required job is retained
 * until resolution, requested rows are never deleted directly, and pruning is
 * deliberately independent of feature flags and the generation brake.
 */
final class ThreadIntelligenceEvidenceRetentionTest extends TestCase
{
    private function generations(): ThreadIntelligenceGenerationRepository
    {
        return new ThreadIntelligenceGenerationRepository($this->db);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }

    private function seedThreadId(): int
    {
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $this->makeUser());
        return (int) $thread['thread_id'];
    }

    /** Creates a generation row in $status whose completed_at is $daysOld days before now. */
    private function agedGeneration(int $threadId, string $status, int $daysOld, ?int $publishedSummaryId = null): int
    {
        $id = $this->generations()->start([
            'thread_id' => $threadId,
            'trigger_code' => 'post_created',
            'model' => 'gpt-5.6-luna',
            'reasoning_effort' => 'low',
            'prompt_version' => 'thread-intelligence-v1',
        ]);
        if ($status !== 'requested') {
            $evidence = ['status' => $status];
            if ($publishedSummaryId !== null) {
                $evidence['published_summary_id'] = $publishedSummaryId;
            }
            $this->generations()->complete($id, $evidence);
            $this->db->run(
                'UPDATE thread_intelligence_generations SET completed_at = ? WHERE id = ?',
                [$this->now()->modify("-{$daysOld} days")->format('Y-m-d H:i:s'), $id],
            );
        } else {
            $this->db->run(
                'UPDATE thread_intelligence_generations SET requested_at = ? WHERE id = ?',
                [$this->now()->modify("-{$daysOld} days")->format('Y-m-d H:i:s'), $id],
            );
        }
        return $id;
    }

    private function existingIds(): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['id'],
            $this->db->fetchAll('SELECT id FROM thread_intelligence_generations ORDER BY id'),
        );
    }

    public function test_unpublished_terminal_rows_become_eligible_only_after_ninety_days(): void
    {
        $threadId = $this->seedThreadId();
        $eligible = [];
        foreach (['succeeded', 'retry', 'failed', 'rejected', 'stale'] as $status) {
            $eligible[] = $this->agedGeneration($threadId, $status, 91);
        }
        $tooFresh = $this->agedGeneration($threadId, 'failed', 89);

        $pruned = $this->generations()->pruneEligible($this->now(), 500);

        self::assertSame(count($eligible), $pruned);
        $remaining = $this->existingIds();
        foreach ($eligible as $id) {
            self::assertNotContains($id, $remaining);
        }
        self::assertContains($tooFresh, $remaining, '89-day-old evidence stays inside the window');
    }

    public function test_published_rows_remain_for_the_thread_lifetime(): void
    {
        $threadId = $this->seedThreadId();
        $summaryId = $this->insertSummary($threadId);
        $published = $this->agedGeneration($threadId, 'published', 400, $summaryId);

        self::assertSame(0, $this->generations()->pruneEligible($this->now(), 500));
        self::assertContains($published, $this->existingIds());
    }

    public function test_requested_rows_are_never_deleted_directly(): void
    {
        $threadId = $this->seedThreadId();
        $requested = $this->agedGeneration($threadId, 'requested', 200);

        self::assertSame(0, $this->generations()->pruneEligible($this->now(), 500));
        self::assertContains($requested, $this->existingIds());
    }

    public function test_evidence_behind_a_live_dead_or_review_job_is_retained_until_resolution(): void
    {
        $threadId = $this->seedThreadId();
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $jobs->upsertStale($threadId, 'post_created', null, $this->now());
        $this->db->run("UPDATE thread_intelligence_jobs SET state = 'dead' WHERE thread_id = ?", [$threadId]);

        $deadEvidence = $this->agedGeneration($threadId, 'dead', 200);
        $oldFailure = $this->agedGeneration($threadId, 'failed', 200);

        self::assertSame(0, $this->generations()->pruneEligible($this->now(), 500), 'diagnostic evidence for an unresolved job is kept');
        self::assertContains($deadEvidence, $this->existingIds());
        self::assertContains($oldFailure, $this->existingIds());

        // Resolution long ago: the job left dead and has been quiet past the window.
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'idle', updated_at = ? WHERE thread_id = ?",
            [$this->now()->modify('-91 days')->format('Y-m-d H:i:s'), $threadId],
        );
        self::assertSame(2, $this->generations()->pruneEligible($this->now(), 500), 'resolved evidence enters the 90-day clock');

        // A recently active job conservatively retains resolved dead evidence.
        $recent = $this->agedGeneration($threadId, 'review_required', 200);
        $this->db->run(
            "UPDATE thread_intelligence_jobs SET state = 'queued', updated_at = ? WHERE thread_id = ?",
            [$this->now()->format('Y-m-d H:i:s'), $threadId],
        );
        self::assertSame(0, $this->generations()->pruneEligible($this->now(), 500));
        self::assertContains($recent, $this->existingIds());
    }

    public function test_prune_is_bounded_per_call_and_never_touches_job_rows(): void
    {
        $threadId = $this->seedThreadId();
        (new ThreadIntelligenceJobRepository($this->db))->upsertStale($threadId, 'post_created', null, $this->now());
        for ($i = 0; $i < 3; $i++) {
            $this->agedGeneration($threadId, 'failed', 100);
        }

        self::assertSame(2, $this->generations()->pruneEligible($this->now(), 2), 'the limit bounds one call');
        self::assertSame(1, $this->generations()->pruneEligible($this->now(), 0), 'a nonpositive limit is clamped up to one');
        self::assertSame(0, $this->generations()->pruneEligible($this->now(), 500));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_intelligence_jobs WHERE thread_id = ?', [$threadId]), 'pruning never deletes the job row');
    }

    public function test_pruning_is_independent_of_feature_flags_and_the_generation_brake(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('features', ['community_memory' => false, 'automated_context' => false]);
        $settings->set('thread_intelligence_generation_paused', '1');

        $threadId = $this->seedThreadId();
        $this->agedGeneration($threadId, 'stale', 120);

        self::assertSame(1, $this->generations()->pruneEligible($this->now(), 500), 'rollback or pause must not suspend the retention policy');
    }

    private function insertSummary(int $threadId): int
    {
        $author = $this->makeUser();
        return $this->db->insert(
            "INSERT INTO thread_summaries (thread_id, kind, status, body, body_html, version, author_id, published_at, created_at)
             VALUES (?, 'manual', 'published', 'body', '<p>body</p>', 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$threadId, (int) $author['id']],
        );
    }
}
