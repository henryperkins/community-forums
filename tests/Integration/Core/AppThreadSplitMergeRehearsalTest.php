<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * Seeded-scale repair rehearsal for thread split/merge (deploy-dark graduation
 * gate for `split_merge`, 2026-07-03). Proves that the counter maintenance
 * ThreadSplitMergeService performs inside its own transaction
 * (threads.reply_count/last_post_*, boards.thread_count/post_count/last_*)
 * matches a from-scratch RepairService recompute AT SCALE — i.e. a batch of
 * splits and merges across several boards leaves ZERO counter drift.
 *
 * Method: seed scale -> repair to a baseline -> snapshot -> drive splits+merges
 * over HTTP as an admin -> snapshot (in-transaction-maintained values) -> repair
 * (recompute from scratch) -> snapshot (authoritative values) -> assert the last
 * two snapshots are identical (no drift) and differ from the pre-op baseline
 * (non-vacuous). RepairService::repairThreadCounters()/repairBoardCounters()
 * return a constant 1 (they recompute every row), so drift is detected by
 * snapshot-diff, not by a repaired-row count.
 */
final class AppThreadSplitMergeRehearsalTest extends TestCase
{
    private function enableSplitMerge(): void
    {
        $this->db->run(
            "INSERT INTO settings (`key`, value, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()",
            [json_encode(['split_merge' => true], JSON_THROW_ON_ERROR)],
        );
    }

    /** @return array{threads:list<array<string,mixed>>, boards:list<array<string,mixed>>} */
    private function snapshotCounters(): array
    {
        return [
            'threads' => $this->db->fetchAll(
                'SELECT id, reply_count, last_post_id, last_post_user_id, last_post_at
                   FROM threads WHERE is_deleted = 0 ORDER BY id',
            ),
            'boards' => $this->db->fetchAll(
                'SELECT id, thread_count, post_count, last_thread_id, last_post_at
                   FROM boards ORDER BY id',
            ),
        ];
    }

    public function test_split_merge_leaves_no_counter_drift_at_scale(): void
    {
        $this->enableSplitMerge();
        $admin = $this->makeAdmin(['username' => 'rehearsal-admin']);
        $member = $this->makeUser(['username' => 'rehearsal-member']);

        // --- Seed scale: 3 boards x 8 threads x 4 replies (120 posts). ---
        $boards = [];
        for ($b = 0; $b < 3; $b++) {
            $boards[] = $this->makeBoard($this->makeCategory('Rehearsal ' . $b), ['slug' => 'rehearsal-' . $b]);
        }
        /** @var list<array{thread_id:int, slug:string, replies:list<int>}> $threads */
        $threads = [];
        foreach ($boards as $board) {
            for ($t = 0; $t < 8; $t++) {
                $thread = $this->makeThread($board, $member, 'Rehearsal ' . $board['slug'] . '-' . $t, 'Opening post');
                $replies = [];
                for ($r = 0; $r < 4; $r++) {
                    $replies[] = $this->posting()->reply(
                        $this->userEntity($member),
                        $thread['thread_id'],
                        ['body' => 'Reply ' . $r . ' in ' . $thread['slug']],
                    );
                }
                $threads[] = ['thread_id' => (int) $thread['thread_id'], 'slug' => $thread['slug'], 'replies' => $replies];
            }
        }

        // Establish an authoritative baseline, then snapshot it.
        $repair = new RepairService($this->db);
        $repair->repairThreadCounters();
        $repair->repairBoardCounters();
        $baseline = $this->snapshotCounters();

        // --- Drive a batch of splits and merges over HTTP as the admin. ---
        $this->actingAs($admin);

        // Split the first two replies out of every third thread into a new topic.
        foreach ($threads as $i => $t) {
            if ($i % 3 !== 0) {
                continue;
            }
            $resp = $this->post('/mod/t/' . $t['thread_id'] . '/split', [
                'title' => 'Split of ' . $t['slug'],
                'post_ids' => implode(',', array_slice($t['replies'], 0, 2)),
            ]);
            $this->assertRedirectContains($resp, '/t/');
        }

        // Merge each even-indexed thread into the next odd-indexed one (intra-board).
        for ($i = 0; $i + 1 < count($threads); $i += 2) {
            $resp = $this->post('/mod/t/' . $threads[$i]['thread_id'] . '/merge', [
                'target_thread_id' => $threads[$i + 1]['thread_id'],
            ]);
            $this->assertRedirectContains($resp, '/t/' . $threads[$i + 1]['thread_id']);
        }

        // Snapshot the in-transaction-maintained state after the ops.
        $afterOps = $this->snapshotCounters();
        self::assertNotEquals($baseline, $afterOps, 'the split/merge batch should have changed the counter landscape');

        // Recompute every counter from scratch; snapshot the authoritative state.
        $repair->repairThreadCounters();
        $repair->repairBoardCounters();
        $afterRepair = $this->snapshotCounters();

        // The crux: in-transaction maintenance == from-scratch recompute → zero drift.
        self::assertEquals(
            $afterRepair,
            $afterOps,
            'split/merge in-transaction counter maintenance drifted from RepairService at scale',
        );
    }
}
