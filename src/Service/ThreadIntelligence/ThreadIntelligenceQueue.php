<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Repository\ThreadIntelligenceJobRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/** Durable, transaction-participating stale markers and curator pause controls. */
final class ThreadIntelligenceQueue
{
    public const TRIGGER_POST_CREATED = 'post_created';
    public const TRIGGER_POST_APPROVED = 'post_approved';
    public const TRIGGER_POST_EDITED = 'post_edited';
    public const TRIGGER_WIKI_EDITED = 'wiki_edited';
    public const TRIGGER_WIKI_REVERTED = 'wiki_reverted';
    public const TRIGGER_POST_DELETED = 'post_deleted';
    public const TRIGGER_POST_RESTORED = 'post_restored';
    public const TRIGGER_THREAD_MOVED = 'thread_moved';
    public const TRIGGER_THREAD_SPLIT = 'thread_split';
    public const TRIGGER_THREAD_MERGED = 'thread_merged';
    public const TRIGGER_CURATOR_REFRESH = 'curator_refresh';
    public const TRIGGER_RECONCILE = 'reconcile';
    public const TRIGGER_BOARD_VISIBILITY_CHANGED = 'board_visibility_changed';

    public const TRIGGERS = [
        self::TRIGGER_POST_CREATED,
        self::TRIGGER_POST_APPROVED,
        self::TRIGGER_POST_EDITED,
        self::TRIGGER_WIKI_EDITED,
        self::TRIGGER_WIKI_REVERTED,
        self::TRIGGER_POST_DELETED,
        self::TRIGGER_POST_RESTORED,
        self::TRIGGER_THREAD_MOVED,
        self::TRIGGER_THREAD_SPLIT,
        self::TRIGGER_THREAD_MERGED,
        self::TRIGGER_CURATOR_REFRESH,
        self::TRIGGER_RECONCILE,
        self::TRIGGER_BOARD_VISIBILITY_CHANGED,
    ];

    private const RECONCILE_TRIGGERS = [
        self::TRIGGER_POST_APPROVED,
        self::TRIGGER_POST_EDITED,
        self::TRIGGER_WIKI_EDITED,
        self::TRIGGER_WIKI_REVERTED,
        self::TRIGGER_POST_DELETED,
        self::TRIGGER_POST_RESTORED,
        self::TRIGGER_THREAD_MOVED,
        self::TRIGGER_THREAD_SPLIT,
        self::TRIGGER_THREAD_MERGED,
        self::TRIGGER_RECONCILE,
        self::TRIGGER_BOARD_VISIBILITY_CHANGED,
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ThreadIntelligenceJobRepository $jobs,
        private readonly ThreadIntelligenceEligibility $eligibility,
    ) {
    }

    public function markStale(
        int $threadId,
        string $trigger,
        ?string $reason = null,
        ?DateTimeImmutable $now = null,
    ): void {
        $this->assertTrigger($trigger);
        $nowUtc = $this->utc($now);
        $reconcile = in_array($trigger, self::RECONCILE_TRIGGERS, true);
        $decision = $this->eligibility->forEnqueue($threadId, $nowUtc);

        if (!$decision->eligible) {
            // A reconciliation event must not be lost merely because it added
            // fewer than five post IDs. The initial eight-post product floor is
            // still absolute.
            if ($decision->code === 'post_delta_threshold' && $reconcile) {
                $this->upsertEligible($threadId, $trigger, $reason, $nowUtc, true);
                return;
            }

            // A privacy/state transition invalidates an existing claim version
            // and idles queued work, but never creates provider-due work.
            if (in_array($decision->code, ['board_not_public', 'thread_deleted', 'thread_pending'], true)) {
                $this->idleIneligibleExisting($threadId, $trigger, $reason, $reconcile);
            }
            return;
        }

        $this->upsertEligible($threadId, $trigger, $reason, $nowUtc, $reconcile);
    }

    public function requestRefresh(int $threadId, ?DateTimeImmutable $now = null): ThreadIntelligenceQueueResult
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $decision = $this->eligibility->forExplicitRefresh($threadId, $now);
        if (!$decision->eligible) {
            return new ThreadIntelligenceQueueResult(
                false,
                $decision->code,
                $decision->message,
                $decision->nextEligibleAt,
            );
        }

        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));
        $queued = $this->db->transaction(function () use ($threadId, $nowUtc): bool {
            $current = $this->lockCurrentVisibilityOrFail($threadId);
            if (!$this->isCurrentlyPublic($current)) {
                return false;
            }
            $this->jobs->upsertStale($threadId, self::TRIGGER_CURATOR_REFRESH, null, $nowUtc);
            return true;
        });
        if (!$queued) {
            return new ThreadIntelligenceQueueResult(
                false,
                'board_not_public',
                'Refresh is available only for eligible public threads',
            );
        }
        return new ThreadIntelligenceQueueResult(true, 'eligible', 'Refresh queued');
    }

    public function setAutomationPaused(
        int $threadId,
        bool $paused,
        ?int $actorId,
        ?DateTimeImmutable $now = null,
    ): void {
        $stamp = $this->utc($now)->format('Y-m-d H:i:s');
        $this->db->transaction(function () use ($threadId, $paused, $actorId, $stamp): void {
            $this->db->run(
                "INSERT INTO thread_intelligence_jobs
                    (thread_id, state, trigger_code, due_at, automation_paused, paused_by, paused_at,
                     activity_version, created_at, updated_at)
                 VALUES (:thread_id, 'idle', :trigger_code, NULL, :paused, :paused_by, :paused_at,
                         0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    due_at = CASE
                        WHEN :pause_due = 1 AND state IN ('queued', 'retry', 'idle') THEN NULL
                        ELSE due_at
                    END,
                    state = CASE
                        WHEN :pause_state = 1 AND state IN ('queued', 'retry', 'idle') THEN 'idle'
                        ELSE state
                    END,
                    automation_paused = :paused_again,
                    paused_by = :paused_by_again,
                    paused_at = :paused_at_again,
                    updated_at = UTC_TIMESTAMP()",
                [
                    'thread_id' => $threadId,
                    'trigger_code' => self::TRIGGER_CURATOR_REFRESH,
                    'paused' => $paused ? 1 : 0,
                    'paused_by' => $paused ? $actorId : null,
                    'paused_at' => $paused ? $stamp : null,
                    'pause_due' => $paused ? 1 : 0,
                    'pause_state' => $paused ? 1 : 0,
                    'paused_again' => $paused ? 1 : 0,
                    'paused_by_again' => $paused ? $actorId : null,
                    'paused_at_again' => $paused ? $stamp : null,
                ],
            );
        });
    }

    public function resumeAndRequeue(
        int $threadId,
        int $actorId,
        ?DateTimeImmutable $now = null,
    ): void {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'));
        $this->db->transaction(function () use ($threadId, $actorId, $now, $nowUtc): void {
            $current = $this->lockCurrentVisibilityOrFail($threadId);
            $decision = $current === null
                ? null
                : $this->eligibility->forEnqueueLocked($threadId, $current, $nowUtc);
            // Content policy reads precede this current job lock. Canonical
            // post writes therefore never cycle with resume as job -> posts.
            $this->jobs->findForUpdate($threadId);
            $this->setAutomationPaused($threadId, false, $actorId, $now);
            if ($this->isCurrentlyPublic($current)
                && $decision !== null
                && ($decision->eligible || $decision->code === 'post_delta_threshold')) {
                $this->jobs->upsertStale(
                    $threadId,
                    self::TRIGGER_CURATOR_REFRESH,
                    null,
                    $nowUtc,
                );
            }
        });
    }

    private function upsertEligible(
        int $threadId,
        string $trigger,
        ?string $reason,
        DateTimeImmutable $nowUtc,
        bool $reconcile,
    ): void {
        $this->db->transaction(function () use ($threadId, $trigger, $reason, $nowUtc, $reconcile): void {
            $current = $this->lockCurrentVisibilityOrFail($threadId);
            if (!$this->isCurrentlyPublic($current)) {
                $this->idleIneligibleExistingLocked($threadId, $trigger, $reason, $reconcile);
                return;
            }
            $this->jobs->upsertStale($threadId, $trigger, $reason, $nowUtc->modify('+15 minutes'));
            if ($reconcile) {
                $this->jobs->requireReconcile($threadId);
            }
            // A curator retirement is stricter than ordinary queue exclusion:
            // stale evidence is recorded, but it has no provider-due timestamp.
            $this->db->run(
                "UPDATE thread_intelligence_jobs
                 SET due_at = CASE WHEN state IN ('queued', 'retry', 'idle') THEN NULL ELSE due_at END,
                     state = CASE WHEN state IN ('queued', 'retry', 'idle') THEN 'idle' ELSE state END,
                     updated_at = UTC_TIMESTAMP()
                 WHERE thread_id = ? AND automation_paused = 1",
                [$threadId],
            );
        });
    }

    /**
     * Linearizes current visibility without ever waiting in the exceptional
     * sweep's inverse order. STRAIGHT_JOIN fixes thread -> board acquisition;
     * a shared SKIP LOCKED read lets independent threads on the same board
     * enqueue together while converting visibility-write contention into a
     * bounded transient rollback. The diagnostic read distinguishes a removed
     * thread from a locked target.
     *
     * @return array{id:int,is_deleted:int,is_pending:int,visibility:string}|null
     */
    private function lockCurrentVisibilityOrFail(int $threadId): ?array
    {
        $current = $this->db->fetch(
            'SELECT t.id, t.is_deleted, t.is_pending, b.visibility
             FROM threads t
             STRAIGHT_JOIN boards b ON b.id = t.board_id
             WHERE t.id = ?
             LOCK IN SHARE MODE SKIP LOCKED',
            [$threadId],
        );
        if ($current !== null) {
            return $current;
        }

        if ($this->db->fetchValue('SELECT 1 FROM threads WHERE id = ? LIMIT 1', [$threadId]) !== false) {
            throw new RuntimeException('thread visibility is busy; retry the canonical mutation');
        }
        return null;
    }

    /** @param array{id:int,is_deleted:int,is_pending:int,visibility:string}|null $current */
    private function isCurrentlyPublic(?array $current): bool
    {
        return $current !== null
            && $current['visibility'] === 'public'
            && (int) $current['is_deleted'] === 0
            && (int) $current['is_pending'] === 0;
    }

    private function idleIneligibleExisting(int $threadId, string $trigger, ?string $reason, bool $reconcile): void
    {
        $this->db->transaction(function () use ($threadId, $trigger, $reason, $reconcile): void {
            $current = $this->lockCurrentVisibilityOrFail($threadId);
            if ($this->isCurrentlyPublic($current)) {
                return;
            }
            $this->idleIneligibleExistingLocked($threadId, $trigger, $reason, $reconcile);
        });
    }

    private function idleIneligibleExistingLocked(int $threadId, string $trigger, ?string $reason, bool $reconcile): void
    {
        $this->db->run(
            "UPDATE thread_intelligence_jobs
             SET activity_version = activity_version + 1,
                 trigger_code = :trigger_code,
                 trigger_reason = :trigger_reason,
                 reconcile_required = CASE WHEN :reconcile = 1 THEN 1 ELSE reconcile_required END,
                 due_at = CASE WHEN state IN ('queued', 'retry') THEN NULL ELSE due_at END,
                 state = CASE WHEN state IN ('queued', 'retry') THEN 'idle' ELSE state END,
                 updated_at = UTC_TIMESTAMP()
             WHERE thread_id = :thread_id",
            [
                'trigger_code' => $trigger,
                'trigger_reason' => $reason === null ? null : substr($reason, 0, 255),
                'reconcile' => $reconcile ? 1 : 0,
                'thread_id' => $threadId,
            ],
        );
    }

    private function assertTrigger(string $trigger): void
    {
        if (!in_array($trigger, self::TRIGGERS, true)) {
            throw new InvalidArgumentException('unknown Thread Intelligence trigger');
        }
    }

    private function utc(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }
}
