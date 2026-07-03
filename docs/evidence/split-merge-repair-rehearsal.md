# Split/Merge Repair Rehearsal (`split_merge` graduation gate)

**Date:** 2026-07-03

Seeded-scale proof that the denormalized-counter maintenance
`ThreadSplitMergeService` performs inside its own transaction matches a
from-scratch `RepairService` recompute — i.e. a batch of splits and merges across
several boards leaves **zero counter drift**. This is the "larger seeded-scale
repair rehearsal" the deploy-dark inventory lists as `split_merge`'s remaining
operational gate.

## Method

`tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php`:

1. Seed 3 boards × 8 threads × 4 replies (120 posts).
2. `RepairService::repairThreadCounters()` + `repairBoardCounters()` → baseline;
   snapshot `threads.{reply_count,last_post_*}` and
   `boards.{thread_count,post_count,last_*}`.
3. Drive 8 splits + 12 merges over HTTP as an admin (real controller → service →
   repository path).
4. Snapshot the in-transaction-maintained counters (assert they differ from the
   baseline — non-vacuous).
5. Recompute from scratch and snapshot again.
6. Assert the post-ops snapshot equals the post-repair snapshot → zero drift.

Drift is detected by snapshot-diff because `repairThreadCounters()` /
`repairBoardCounters()` return a constant `1` (they recompute every row rather
than reporting changed rows).

## Result

```
$ vendor/bin/phpunit --filter test_split_merge_leaves_no_counter_drift_at_scale
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.6
.                                                                   1 / 1 (100%)
Time: 00:01.888, Memory: 34.00 MB
OK (1 test, 42 assertions)
```

**PASS** — no counter drift after a seeded-scale split/merge batch. (The 42
assertions are the 20 split/merge redirect checks plus the non-vacuous baseline
diff and the zero-drift equality.)

## Known limit

`RepairService` reconciles `last_post_*` by `MAX(id)`, while the live service
orders by `created_at DESC, id DESC`. These agree for naturally-ordered posts
(id monotonic with creation time); a backdated/imported post whose highest id is
not its latest timestamp could show a one-row `last_post_*` difference on repair.
That is a repair-recompute artifact, not split/merge drift, and is out of scope
for this graduation.
