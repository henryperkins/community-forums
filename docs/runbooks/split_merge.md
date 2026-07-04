# Runbook — Split / Merge (`split_merge`)

Release/operations runbook for the **split_merge** feature: moderator topic
restructuring — splitting selected replies out into a new topic and merging one
topic into another, with a reversible operation ledger, audit entries, old-URL
redirects, and in-transaction counter maintenance. **Default-ON as of
2026-07-03** (the `split_merge` flag graduated out of deploy-dark); fully
reversible via the `features` override.

> **Golden rule:** for any restructure defect, counter-drift report, or abuse of
> the split/merge controls, disable the `split_merge` flag first. Both
> `/mod/t/{id}/split` and `/mod/t/{id}/merge` routes return 404 and the
> restructure panel disappears from the thread view, while all other thread
> moderation (pin/lock/status/remove) and normal reading keep serving.

## What the flag gates

`split_merge` gates the two restructure write routes and the moderator panel that
posts to them. Topics already split or merged before rollback stay as they are —
the new/merged topics, `thread_operations` ledger rows, and `thread_redirects`
are ordinary data and keep working (old merged-source URLs keep 301-redirecting).

Routes gated by the flag (`ModerationController::requireSplitMerge()` throws
`NotFoundException` → 404 when off):

- `POST /mod/t/{id}/split` — move the selected non-OP replies out of topic `{id}`
  into a brand-new topic in the same board.
- `POST /mod/t/{id}/merge` — merge topic `{id}` into a target topic; the source
  is soft-deleted and its old URL 301-redirects to the target.

The thread view hides the **Split or merge topic** panel while the flag is off,
and for any viewer who is not a moderator of that board.

## Roll back / re-enable

The flag lives in the `features` setting. Merge the override rather than
clobbering other feature keys:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["split_merge"]=false; $r->set("features",$f);'
```

Re-enable by setting `split_merge` back to `true` or removing the key, since the
default is now `true`.

Rollback is non-destructive. Split/merge only ever runs on an explicit operator
action, so disabling the flag simply prevents new restructures; it never reverses
one already performed and never touches the ledger, redirects, or counters.

## Operating semantics

- **Authority is per-board, not global.** The panel and both routes gate on
  `canModerate(user, boardId)` — a site admin (admin-any) or the board's assigned
  moderator. **Merge additionally requires authority over *both* the source and
  target boards.** Account state still applies: a suspended/banned moderator
  cannot write.
- **Split moves replies, never the OP.** Only non-OP replies are listed as
  movable; splitting requires at least one selected reply and a new title, and
  redirects to the newly created topic (flash *"Thread split."*).
- **Merge folds one topic into another.** The current topic's posts move into the
  target, the source is soft-deleted, and a `thread_redirects` row makes the old
  source URL 301-redirect to the target (flash *"Thread merged."*). Source and
  target must differ and neither may be deleted.
- **Every operation is double-audited.** Each split writes a `split_thread`
  `moderation_log` row; each merge writes a `merge_thread` row. Both also append a
  `thread_operations` ledger row (`operation_type` `split`/`merge`,
  `status='applied'`, actor, source/destination, snapshot) — the reversible
  operation record.
- **Counters are maintained transactionally.** The whole operation runs inside one
  `$db->transaction`; `threads.reply_count`/`last_post_*` and
  `boards.thread_count`/`post_count`/`last_*` are recomputed for every touched
  topic and board before commit, so denormalized counters cannot drift.
- **Bad input is rejected without side effects.** Empty selection, self-merge, a
  cross-thread post id, or a missing title raise a `ValidationException`; the
  controller redirects back to the source topic with the error as a flash and
  writes nothing.

## Monitoring & known limits

- There is no dedicated worker or new counter for split/merge. It reuses the
  existing thread/board counters, and `RepairService` recomputes exactly those
  from authoritative rows — so `php bin/console repair` reconciles any historical
  drift the same way it does for ordinary posting.
- There is no separate `RateLimitService` policy for split/merge; it is a
  moderator-only action guarded by per-board authority + CSRF.
- Merge is one-directional and not undone by an in-app button. To reverse a merge,
  restore from backup or split the folded replies back out manually; the
  `thread_operations` ledger row preserves the source/destination for forensics.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppThreadSplitMergeTest.php` covers the
  split/merge flow, moderator authority (incl. board-moderator visibility),
  `split_thread`/`merge_thread` audit rows, the `thread_redirects` 301, and
  in-transaction counter maintenance; `tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php`
  is the seeded-scale (3 boards × 8 threads × 4 replies; 8 splits + 12 merges)
  **zero-counter-drift** rehearsal recorded at
  `docs/evidence/split-merge-repair-rehearsal.md`;
  `tests/Integration/Core/AppFeatureFlagTest.php`
  (`test_split_merge_is_available_by_default_and_can_be_disabled`) covers
  default-on availability plus operator rollback re-gating both routes to 404.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/50-split-merge-panel.png`
  captures the moderator restructure panel (both the split and merge forms) on a
  topic; `51-thread-merged.png` captures the target topic after a merge with the
  *"Thread merged."* confirmation. Both are driven by
  `tests/browser/gate-a.spec.ts` (a self-contained journey that creates a topic +
  reply, splits the reply out, then merges it back).
- **Accessibility:** `tests/browser/a11y.spec.ts` scans `.sm-panel` (the expanded
  restructure panel) on desktop and mobile with no serious/critical axe
  violations.
