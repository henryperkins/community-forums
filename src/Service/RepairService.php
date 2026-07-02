<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\UserRepository;
use App\Service\Registry\RegistryAdvisoryService;

/**
 * Idempotent counter reconciliation / repair (PHASE_2_PLAN §2 "all denormalised
 * counters have reconciliation tests or a repair command", Milestone 1).
 *
 * Each method recomputes a denormalised counter from the authoritative rows and
 * is safe to run repeatedly. Used by `bin/console repair:*` and by tests to
 * assert the transactional counters never drift.
 */
final class RepairService
{
    public function __construct(private Database $db, private int $solvedBonus = 5)
    {
    }

    /** users.post_count = number of the user's non-deleted, non-held posts. */
    public function repairUserPostCounts(): int
    {
        return $this->db->run(
            'UPDATE users u SET post_count = (
                SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.is_deleted = 0 AND p.is_pending = 0
             )',
        )->rowCount();
    }

    /**
     * users.reputation is now reconciled from the Phase 4 reputation ledger.
     * The rebuild backfills canonical reaction and accepted-answer events, reverses
     * stale rows, then makes users.reputation agree with active ledger deltas.
     */
    public function repairReputation(): int
    {
        $stats = (new ReputationLedgerService($this->db, new UserRepository($this->db)))
            ->rebuildFromCanonical($this->solvedBonus);
        return $stats['reconciled_users'];
    }

    /**
     * Compatibility entry point for older runbooks/tests. Solved-answer bonuses
     * are part of the ledger rebuild, so this is intentionally idempotent.
     */
    public function reputationSolvedBonus(): int
    {
        return $this->repairReputation();
    }

    /**
     * threads: reply_count = non-OP non-deleted non-held posts; last_post_* =
     * newest non-deleted non-held post (NULL when none). Held (pending) posts are
     * excluded to match the runtime, which defers all counters until approval.
     */
    public function repairThreadCounters(): int
    {
        $this->db->run(
            'UPDATE threads t SET reply_count = (
                SELECT COUNT(*) FROM posts p
                WHERE p.thread_id = t.id AND p.is_deleted = 0 AND p.is_pending = 0 AND p.is_op = 0
             )',
        );
        // last_post_* from the newest non-deleted, non-held post in the thread.
        $this->db->run(
            'UPDATE threads t
             LEFT JOIN (
                SELECT x.thread_id, x.id AS pid, x.user_id AS uid, x.created_at AS at
                FROM posts x
                JOIN (
                    SELECT thread_id, MAX(id) AS max_id
                    FROM posts WHERE is_deleted = 0 AND is_pending = 0 GROUP BY thread_id
                ) m ON m.thread_id = x.thread_id AND m.max_id = x.id
             ) lp ON lp.thread_id = t.id
             SET t.last_post_id = lp.pid, t.last_post_user_id = lp.uid, t.last_post_at = lp.at',
        );
        return 1;
    }

    /**
     * boards: thread_count = non-deleted non-held threads; post_count = non-deleted
     * non-held posts across non-deleted non-held threads; last_thread_id/
     * last_post_at = newest visible activity. Held content is excluded to match the
     * runtime, which counts it only once a moderator approves it.
     */
    public function repairBoardCounters(): int
    {
        $this->db->run(
            'UPDATE boards b SET thread_count = (
                SELECT COUNT(*) FROM threads t WHERE t.board_id = b.id AND t.is_deleted = 0 AND t.is_pending = 0
             )',
        );
        $this->db->run(
            'UPDATE boards b SET post_count = (
                SELECT COUNT(*) FROM posts p
                JOIN threads t ON t.id = p.thread_id
                WHERE t.board_id = b.id AND p.is_deleted = 0 AND p.is_pending = 0
                  AND t.is_deleted = 0 AND t.is_pending = 0
             )',
        );
        $this->db->run(
            'UPDATE boards b
             LEFT JOIN (
                SELECT t.board_id, x.thread_id AS tid, x.created_at AS at
                FROM posts x
                JOIN threads t ON t.id = x.thread_id
                JOIN (
                    SELECT t2.board_id, MAX(p2.id) AS max_id
                    FROM posts p2 JOIN threads t2 ON t2.id = p2.thread_id
                    WHERE p2.is_deleted = 0 AND p2.is_pending = 0
                      AND t2.is_deleted = 0 AND t2.is_pending = 0
                    GROUP BY t2.board_id
                ) m ON m.board_id = t.board_id AND m.max_id = x.id
             ) lp ON lp.board_id = b.id
             SET b.last_thread_id = lp.tid, b.last_post_at = lp.at',
        );
        return 1;
    }

    /** Run every repair pass. @return array<string,int> */
    /**
     * Reconcile the topic_workflow `solved` status projection from the canonical
     * accepted-answer marker (`threads.accepted_answer_post_id`). Mirrors the live
     * SolvedAnswerService sync — only `open`/`needs_answer` ⇄ `solved` — so
     * staff-set statuses (`decision_made`/`archived`) are never clobbered. Lets an
     * operator run `repair` to pick up answers accepted while `topic_workflow` was
     * disabled (the flag gates the live sync). @return int rows changed
     */
    public function repairThreadStatuses(): int
    {
        $toSolved = $this->db->run(
            "UPDATE threads
                SET status = 'solved', status_changed_at = UTC_TIMESTAMP()
              WHERE accepted_answer_post_id IS NOT NULL
                AND is_deleted = 0
                AND status IN ('open', 'needs_answer')",
        )->rowCount();
        $toOpen = $this->db->run(
            "UPDATE threads
                SET status = 'open', status_changed_at = UTC_TIMESTAMP()
              WHERE accepted_answer_post_id IS NULL
                AND is_deleted = 0
                AND status = 'solved'",
        )->rowCount();
        return $toSolved + $toOpen;
    }

    /**
     * Ensure the "≥1 active recoverable owner" invariant (decision #27) is
     * satisfiable: if any active admin exists but no active protected owner is
     * designated, designate the earliest active admin. Idempotent — a no-op once
     * an owner exists or when there is no admin (fresh install pre-setup).
     * @return int rows inserted
     */
    public function repairProtectedOwners(): int
    {
        // An "active owner" requires a live account (users.status = 'active'), not
        // just the write-once is_active flag — matches ProtectedOwnerRepository so
        // a stale row from a deactivated owner does not mask a lost invariant.
        $hasActiveOwner = (int) $this->db->fetchValue(
            "SELECT EXISTS(
                SELECT 1 FROM protected_owners po
                JOIN users u ON u.id = po.user_id
                WHERE po.is_active = 1 AND u.status = 'active'
            )",
        ) === 1;
        if ($hasActiveOwner) {
            return 0;
        }
        $adminId = $this->db->fetchValue(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1",
        );
        if ($adminId === null) {
            return 0;
        }
        return (new ProtectedOwnerRepository($this->db))->designateOrReactivate((int) $adminId, null) ? 1 : 0;
    }

    /**
     * Recompute packages.latest_release_id: highest stable version per package,
     * matching RegistrySnapshotService::latestStableId.
     */
    public function repairPackageLatestReleases(): int
    {
        $changed = 0;
        foreach ($this->db->fetchAll('SELECT id, latest_release_id FROM packages') as $package) {
            $best = null;
            foreach ($this->db->fetchAll("SELECT id, version FROM package_releases WHERE package_id = ? AND channel = 'stable'", [(int) $package['id']]) as $release) {
                if ($best === null || version_compare((string) $release['version'], (string) $best['version'], '>')) {
                    $best = $release;
                }
            }

            $target = $best === null ? null : (int) $best['id'];
            $current = $package['latest_release_id'] === null ? null : (int) $package['latest_release_id'];
            if ($target !== $current) {
                $this->db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$target, (int) $package['id']]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Recompute package and release advisory_status from cached advisories,
     * matching RegistryAdvisoryService's escalate-only fold.
     */
    public function repairPackageAdvisoryStatuses(): int
    {
        $changed = 0;
        foreach ($this->db->fetchAll('SELECT id, advisory_status FROM packages') as $package) {
            $packageId = (int) $package['id'];
            $advisories = $this->db->fetchAll('SELECT * FROM package_advisories WHERE package_id = ?', [$packageId]);

            $status = 'none';
            foreach ($advisories as $advisory) {
                $status = RegistryAdvisoryService::escalate(
                    $status,
                    RegistryAdvisoryService::ACTION_STATUS[(string) $advisory['action']] ?? 'none',
                );
            }
            if ($status !== (string) $package['advisory_status']) {
                $this->db->run('UPDATE packages SET advisory_status = ? WHERE id = ?', [$status, $packageId]);
                $changed++;
            }

            foreach ($this->db->fetchAll('SELECT id, version, digest, advisory_status FROM package_releases WHERE package_id = ?', [$packageId]) as $release) {
                $releaseStatus = 'none';
                foreach ($advisories as $advisory) {
                    $digest = $advisory['affected_digest'] ?? null;
                    $hit = $digest !== null
                        ? hash_equals((string) $digest, (string) $release['digest'])
                        : RegistryAdvisoryService::affectsVersion($advisory['affected_version_range'] ?? null, (string) $release['version']);
                    if ($hit) {
                        $releaseStatus = RegistryAdvisoryService::escalate(
                            $releaseStatus,
                            RegistryAdvisoryService::ACTION_STATUS[(string) $advisory['action']] ?? 'none',
                        );
                    }
                }
                if ($releaseStatus !== (string) $release['advisory_status']) {
                    $this->db->run('UPDATE package_releases SET advisory_status = ? WHERE id = ?', [$releaseStatus, (int) $release['id']]);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    public function repairAll(): array
    {
        return $this->db->transaction(function (): array {
            $out = [
                'user_post_counts' => $this->repairUserPostCounts(),
                'thread_counters' => $this->repairThreadCounters(),
                'board_counters' => $this->repairBoardCounters(),
                'thread_statuses' => $this->repairThreadStatuses(),
                'protected_owners' => $this->repairProtectedOwners(),
                'package_latest' => $this->repairPackageLatestReleases(),
                'package_advisory' => $this->repairPackageAdvisoryStatuses(),
                'reputation' => $this->repairReputation(),
            ];
            return $out;
        });
    }
}
