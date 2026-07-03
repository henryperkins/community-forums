# Design: Graduate `custom_profile_fields` and `split_merge` to default-ON

**Date:** 2026-07-03
**Author:** Henry Perkins (with Claude)
**Status:** Approved design — ready for implementation plan
**Branch:** `docs/phase5-gatea-plan-remediation` (per owner decision; graduation lands as its own scoped commits alongside in-flight Phase 5 docs work)

## Goal

Graduate two Phase 4 deploy-dark feature flags out of deploy-dark (`FeatureFlags::DEFAULTS`
`false` → `true`, operator-reversible via the `features` override), completing their
DESIGN §13 acceptance evidence:

1. **`custom_profile_fields`** — bounded extra public profile fields (member surface).
2. **`split_merge`** — moderator thread split/merge (moderator surface).

Both are the two lowest-remaining-effort entries in the deploy-dark inventory's
Graduation Readiness Ranking (Tier 3 and Tier 4 respectively). This follows the
established graduation ritual used by `polls`, `topic_workflow`, `appeals`,
`custom_emoji`, `profile_media`, etc.

## Owner decisions captured at brainstorming

- **`custom_profile_fields` privacy/copy review** (the inventory's documented
  product-owner blocker): **signed off as-is.** Current settings copy — "Add up to
  three public profile facts. Labels are limited to 40 characters; values to 160." —
  is accepted; no copy change required before flipping.
- **`split_merge` seeded-scale repair rehearsal**: **build it now** as a blocking
  gate (not deferred to follow-up).
- **Branch**: stay on `docs/phase5-gatea-plan-remediation`; keep graduation commits
  well-scoped and separate from the uncommitted Phase 5 docs hunks (do not fold the
  `provider_registry`→GitLab.com / threat-model edits into graduation commits).

## Non-goals

- No schema changes. Both features' schema already shipped (`custom_profile_fields`
  in migration `0062`; `split_merge` over the existing `thread_operations` /
  `thread_redirects` schema). Additive-only rule is not engaged.
- No copy rewrite for `custom_profile_fields` (signed off as-is).
- No change to the `split_merge` service logic — the rehearsal is evidence, not a fix.
  (If the rehearsal surfaces real counter drift, that becomes a separate bug to fix
  before flipping — see risks.)
- No regeneration of the full `npm run evidence` PNG set (deferred follow-up per the
  ritual; other screenshots may now show the newly-on surfaces).

---

## A. `custom_profile_fields` — render-gated Tier-3 graduation

### Current surface (verified)
- **Render-gated, NOT route-gated.** Fields are saved through the existing
  `/settings/profile` POST and rendered on the public profile `/u/{name}`; the flag
  gates the settings panel + profile render only. There is no dedicated route to 404.
  Consumers: `src/Controller/AccountController.php`, `src/Controller/ProfileController.php`,
  `src/Service/AccountService.php`, `templates/account/settings.php` (panel ~L95–107),
  admin toggle in `src/Controller/AdminFeatureController.php`.
- Existing PHPUnit: `AppBoardFoldersSavedFeedsTest` covers the `0062` slice.
- Bounds: up to 3 fields; label ≤ 40 chars; value ≤ 160 chars.

### Changes
1. **Flip default** — `src/Core/FeatureFlags.php`:
   `'custom_profile_fields' => true,  // GA default-on (2026-07-03; reversible via features override)`.
2. **`tests/Integration/Core/AppFeatureFlagTest.php`**:
   - Remove `custom_profile_fields` from the `test_phase4_gate_a_flags_default_dark`
     (or equivalent Phase-4 dark-list) foreach.
   - Add `test_custom_profile_fields_is_available_by_default_and_can_be_disabled` —
     **marker-based** (the `wysiwyg_composer` render-gated variant, not route-404):
     assert the settings profile-fields panel/markers render by default; then
     `setFlags(['custom_profile_fields' => false])` and assert the panel/markers are
     absent. Keep an isolation-neighbour assertion (a still-dark flag stays false).
3. **Browser + a11y** (`tests/browser/`):
   - `seed.php`: add `'custom_profile_fields' => true` to `$evidenceFeatures`
     (redundant vs default, self-documenting); ensure a seeded member has ≥1 profile
     field value to render (set in seed or in-flow).
   - Standalone spec `tests/browser/custom-profile-fields.spec.ts` (appeals-precedent:
     copy the `shot`/`visit`/`login`/`dismissTour` + `EVIDENCE_DIR` helpers inline;
     no shared module). Flow: member edits a profile field in `/settings` → views it
     rendered on their public profile → `shot(page, info, '50-custom-profile-fields')`
     (number provisional; confirm next-free against all specs at implementation time).
   - Register the new spec in `package.json` `evidence` + `evidence:prodlike` scripts
     (specs are named, not auto-discovered).
   - `a11y.spec.ts`: scoped axe scan of the profile-fields panel selector (confirm the
     actual class in `templates/account/settings.php` — e.g. `.profile-fields`).
4. **Runbook** `docs/runbooks/custom_profile_fields.md` — clone `docs/runbooks/polls.md`
   structure: banner, golden rule (disable-first, non-destructive: disabling stops new
   field edits + hides rendering but preserves stored values), what it gates + the
   `/settings/profile` write path and `/u/{name}` render, roll back/re-enable php
   snippet, operating semantics (3-field / 40 / 160 bounds; fields are **public**),
   monitoring & known limits, acceptance-evidence list.

---

## B. `split_merge` — Tier-4 graduation with a seeded-scale repair rehearsal

### Current surface (verified)
- **Route-gated**: `POST /mod/t/{id}/split`, `POST /mod/t/{id}/merge`
  (`src/Core/App.php` L1870–1871). `ModerationController::split()`/`merge()` gate on
  `requireSplitMerge()` (404 when flag off) + `canModerate(actor, boardId)`.
- **Service** `src/Service/ThreadSplitMergeService.php` maintains, inside
  `$db->transaction()`:
  - `threads.reply_count`, `threads.last_post_id` / `last_post_user_id` / `last_post_at`
    (`recountThread`, clauses `is_deleted=0 AND is_pending=0 AND is_op=0` for reply_count).
  - `boards.thread_count`, `boards.post_count`, `boards.last_thread_id` / `last_post_at`
    (`recountBoards`).
  - Audit rows: `thread_operations` (`split`/`merge`, `status='applied'`),
    `thread_redirects` (merge only), `moderation_log`.
  - Merge sets source thread `is_deleted=1`, reassigns its posts to target
    (`is_op=0`), writes a redirect old→canonical.
- **UI** `templates/thread.php` L363: `<details>` "Split or merge topic" panel gated on
  `features['split_merge'] && can_moderate_board`; split form (pick replies + title) at
  L375, merge form (target thread) at L397. Selector `.sm-panel` / `.topic-restructure`.
- Existing PHPUnit `AppThreadSplitMergeTest` (6 methods): split into new thread;
  split touched-counter maintenance without global repair; merge + old-URL redirect;
  merge touched-counter maintenance; scoped-board-moderator sees the surface; merge
  recounts last-activity by timestamp not post id.

### Changes
1. **Flip default** — `src/Core/FeatureFlags.php`:
   `'split_merge' => true,  // GA default-on (2026-07-03; reversible via features override)`.
2. **`tests/Integration/Core/AppFeatureFlagTest.php`**:
   - Remove `split_merge` from the Phase-4 dark-list foreach.
   - Add `test_split_merge_is_available_by_default_and_can_be_disabled` —
     **route-based** (`polls` precedent): a `POST /mod/t/{id}/split` as a moderator
     redirects (not 404) by default (`assertRedirectContains` / `assertStatus` per the
     controller's success/validation behavior); then `setFlags(['split_merge'=>false])`
     and assert `assertStatus(404, ...)`. Keep an isolation-neighbour assertion.
     - Note: pick an assertion that is robust to per-test transaction isolation
       (assert observable HTTP behavior — redirect vs 404 — not row counts).
3. **Seeded-scale repair rehearsal** — new standalone harness
   `tests/split_merge/rehearse.php` (+ optional `rehearse.sh` wrapper), modeled on
   `tests/backup/rehearse.sh` and `verify:upgrade`:
   - **Real commits, not PHPUnit isolation.** The rehearsal MUST run outside the
     TestCase transaction-rollback harness (which would nullify the service's own
     `$db->transaction()` and prevent real cross-transaction scale). Boot the container
     like `bin/console` against the **e2e / scratch DB** (`retroboards_e2e`, already
     local). **Refuse `APP_ENV=production`** (mirror `verify:upgrade`).
   - Procedure:
     1. `migrate:fresh` (or dedicated scratch) then seed **N boards × M threads × K
        replies** (scale large enough that counter drift would be visible — e.g.
        N≥3, M≥20, K≥10; final numbers set in the plan).
     2. Run `repair` (via `RepairService::repairAll()`) → **baseline**, zeroing any
        seed-induced drift.
     3. Perform a batch of split + merge operations by calling
        `ThreadSplitMergeService::split()` / `merge()` directly through the container
        (HTTP path is already covered by PHPUnit + browser evidence; this rehearsal is
        about data integrity at scale).
     4. Run `repair` again and read the returned `array<string,int>` map. **Assert
        `thread_counters === 0` AND `board_counters === 0`** — i.e. the from-scratch
        recompute finds nothing to fix, proving the in-transaction maintenance is
        correct at scale.
     5. Print a counter table (before-ops / after-ops / after-repair per touched
        counter) and a `PASS`/`FAIL`; **exit non-zero on any drift.**
   - Capture the transcript to **`docs/evidence/split-merge-repair-rehearsal.md`**
     (precedent: `docs/evidence/phase2-4-completion.md` worker smokes,
     `docs/evidence/phase5/resolver-parity.md`).
4. **Browser + a11y** (`tests/browser/`):
   - `seed.php`: add `'split_merge' => true` to `$evidenceFeatures`; ensure a thread
     with ≥2 replies exists in a board the evidence moderator can moderate (add seed
     data if the standard seed lacks a splittable thread), plus a second thread to
     serve as a merge target.
   - Standalone spec `tests/browser/split-merge.spec.ts`: moderator opens the "Split
     or merge topic" panel, splits selected replies into a new thread, then merges two
     threads → `shot(... '51-thread-split')` and `shot(... '52-thread-merge')` (numbers
     provisional; confirm next-free against all specs).
   - Register in `package.json` `evidence` + `evidence:prodlike`.
   - `a11y.spec.ts`: scoped axe scan of `.sm-panel` (moderator thread view).
5. **Runbook** `docs/runbooks/split_merge.md` — clone `polls.md`: banner, golden rule
   (disable-first is non-destructive: disabling re-gates the routes to 404 but leaves
   already-applied splits/merges, redirects, and audit rows intact), what it gates +
   routes (`/mod/t/{id}/split`, `/mod/t/{id}/merge`), operating semantics (OP cannot be
   split out; merge soft-deletes source + writes redirect; audit via `thread_operations`
   + `moderation_log`), the `repair`-no-drift check referencing the rehearsal evidence,
   monitoring & known limits, acceptance-evidence list.

---

## C. Docs sweep and cross-file reconciliation

1. **`docs/evidence/deploy-dark-features.md`**:
   - Bump the `**Date:**` line.
   - Rewrite the `custom_profile_fields` and `split_merge` rows to the
     `**Graduated 2026-07-03 — now default-ON**` form with their acceptance-evidence
     lists.
   - In the **Graduation Readiness Ranking**, mark both entries graduated **in place**
     with their numbers retained (custom_profile_fields is Tier 3 entry #9; split_merge
     is Tier 4 entry #10) — matching the existing convention for entries #1–8
     (e.g. "✓ **Graduated 2026-07-03 (default-ON).**"). Do NOT renumber.
   - Add a **Notes** bullet for each.
   - **Recompute the Source Code Audit tallies** — do NOT trust the doc's own
     arithmetic. Verify with `grep -cE '=> true,' src/Core/FeatureFlags.php` and
     `grep -cE '=> false,'` (expected 35→37 true, 22→20 false) and update the "declares
     N flags", "M default true / P default false", table-row count, and
     "retained graduated" count lines accordingly.
2. **`PHASE_4_STATUS.md`**: remove both from any default-`false` enumeration; bump
   "Last updated".
3. **`CLAUDE.md`**: verify whether the feature-flags paragraph names either flag (it
   enumerates specific graduated flags + `group_dms`/`community_memory` as remaining
   dark — it may not name these two). Update only if named.
4. **`tests/browser/README.md`**: update if it describes flag posture (it has gone
   stale on prior graduations).
5. **Closing check**: `grep -rn 'deploy-dark' --include='*.md' . | grep -iE 'custom_profile_fields|split_merge'`
   to confirm no stale posture references remain.

---

## Verification (before claiming done — DESIGN §13)

- Full `composer test` green (flipping a default-ON flag makes the surface render on
  every thread + routes go live — catches tests that assumed dark).
- `custom-profile-fields.spec.ts` and `split-merge.spec.ts` pass; a11y scans pass;
  **visually Read** each new screenshot to confirm it shows the real surface.
- `tests/split_merge/rehearse.php` prints `PASS` with `thread_counters=0`,
  `board_counters=0`; transcript saved to the evidence file.
- Re-verify the deploy-dark audit tallies with the two `grep -c` commands above.

## Risks & mitigations

- **Rehearsal reveals real drift** (service vs `RepairService` WHERE-clause mismatch):
  this would be a genuine data-integrity bug. Stop, fix the mismatch, re-run — do NOT
  flip the flag until the rehearsal is green. (CLAUDE.md: "Every counter you increment
  must have a matching recompute in `RepairService` with identical WHERE clauses.")
- **Flipping `split_merge` on breaks other browser journeys**: the `.sm-panel`
  `<details>` is collapsed by default and only shows for board moderators, so low risk
  to non-mod screenshots; verify the gate-a moderator views still pass.
- **Concurrent Codex session** editing the same shared files (FeatureFlags,
  AppFeatureFlagTest, gate-a/a11y specs, inventory): detect before and during multi-
  commit work (`ps -eo pid,etimes,args | grep -i 'codex'`; `find … -mmin -3`). Verified
  clear at design time.
- **Screenshot number collisions**: numbers are not globally unique and not pixel-
  baselined; `ls docs/evidence/browser/desktop/ | sort -n` AND grep the specs for the
  chosen numbers before committing.
- **e2e DB assumptions**: `rehearse.php` assumes `retroboards_e2e` exists locally (no
  docker container); mirror `tests/browser/prepare.sh`'s assumption.

## Out of scope / follow-ups

- Full `npm run evidence` PNG-set regeneration (other screenshots may now show the
  newly-on surfaces) — regenerate when convenient.
- Any product decision to *enable* these for a specific deployment — graduation makes
  them available-by-default and evidenced; enablement posture stays operator-controlled.
