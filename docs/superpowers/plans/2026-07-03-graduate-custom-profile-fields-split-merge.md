# Graduate `custom_profile_fields` + `split_merge` — Design + Implementation Plan (Archived)

> Archived design record — implementation plan + design spec(s) merged during the graduate-cpf-split-merge doc consolidation; see the ADR / runbook / PR referenced below for shipped status.

---

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

---

# Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flip two Phase 4 deploy-dark flags (`custom_profile_fields`, `split_merge`) to default-ON with the full DESIGN §13 acceptance evidence (PHPUnit + browser/a11y + a seeded-scale repair rehearsal + runbooks + inventory reconciliation).

**Architecture:** Follow the established graduation ritual (precedents: `polls`, `appeals`, `custom_emoji`, `profile_media`). `custom_profile_fields` is **render-gated** (marker-based test); `split_merge` is **route-gated** (route-based test) and additionally needs a seeded-scale proof that its in-transaction counter maintenance matches `RepairService`. Each flag lands as its own scoped commit on the current branch.

**Tech Stack:** Vanilla PHP 8.2+, MySQL/MariaDB, PHPUnit (integration via `Tests\Support\TestCase` in-process kernel), Playwright + `@axe-core/playwright` for browser/a11y evidence.

## Global Constraints

- **Branch:** stay on `docs/phase5-gatea-plan-remediation` (owner decision). Keep graduation commits scoped; do NOT stage the pre-existing uncommitted Phase 5 doc hunks (`PHASE_5_STATUS.md`, `docs/evidence/deploy-dark-features.md` provider_registry line, `docs/phase5/threat-models/fixtures.json`, the untracked Inc 5 spec) into graduation commits.
- **No PHPUnit CI** — run `composer test` locally; green before any "done" claim.
- **GA comment format** in `FeatureFlags::DEFAULTS`: `// GA default-on (2026-07-03; reversible via features override)`.
- **Concurrency guard:** before each multi-file commit, verify no concurrent Codex session is editing the same files: `ps -eo pid,etimes,args | grep -i 'codex' | grep -v grep` and `find src tests docs -mmin -3 -type f`. (Verified clear at plan time.)
- **Screenshot numbers are NOT globally unique** and not pixel-baselined. Highest on disk is `49`. Before committing a number, run `ls docs/evidence/browser/desktop/ | sort -n | tail` AND `grep -rn "'5[0-9]-" tests/browser/*.spec.ts` to confirm `50`/`51`/`52` are free.
- **Strict CSP** — no inline `<script>`/`<style>` in any template touched.
- **Deploy-dark inventory arithmetic is hand-maintained** — recompute tallies with `grep -cE '=> true,' src/Core/FeatureFlags.php` / `grep -cE '=> false,'` (currently 35 true / 22 false → 37 / 20 after this work). Never trust the doc's own counts.

## Planning refinements vs. the approved spec (`docs/superpowers/specs/2026-07-03-graduate-custom-profile-fields-split-merge-design.md`)

- **Rehearsal form changed** — the spec called for a standalone `tests/split_merge/rehearse.php` reading `repairAll()`'s return map. Two discoveries force a change: (a) `RepairService::repairThreadCounters()`/`repairBoardCounters()` `return 1` unconditionally (they blindly recompute every row), so drift can only be detected by **snapshot-diff**; (b) wiring `ThreadSplitMergeService` outside the HTTP kernel drags in `ModerationService`→`PostingService` (18-arg constructor), which rots silently on constructor drift. The rehearsal is therefore an **HTTP-driven PHPUnit scale test** (Task 3) that exercises the real controller→service→repository path and lives in `composer test`; its PASS run is captured to `docs/evidence/split-merge-repair-rehearsal.md`. **Flag this to the owner before/at execution.**
- **`custom_profile_fields` has no dark-posture assertion to remove** — verified: `AppAdminFeaturesTest` only renders an all-off page; `AppBoardFoldersSavedFeedsTest` enables the flag for its own tests. So its graduation is flag-flip + a new default-on test (no dark-list edit). Only `split_merge` has a dark-list entry (`AppPhase4CarryoverFoundationTest`).

---

### Task 1: Graduate `custom_profile_fields` (flag flip + default-on test)

**Files:**
- Modify: `src/Core/FeatureFlags.php:73`
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (add one method)

**Interfaces:**
- Consumes: `TestCase` helpers `makeUser`, `actingAs`, `get`, `assertStatus`; local `setFlags()`; `FeatureFlags`/`SettingRepository`.
- Produces: nothing downstream depends on this task's code.

**Context (verified):** `AccountController::accountForm` renders `templates/account/settings.php` at `GET /settings/account` with `'custom_profile_fields' => FeatureFlags::enabled('custom_profile_fields')` (a bool). When truthy, the template renders `<legend ...>Custom profile fields</legend>` and `name="custom_label_1"` inputs (settings.php:95–108). Render-gated: no route to 404.

- [ ] **Step 1: Write the failing test** — add to `tests/Integration/Core/AppFeatureFlagTest.php` (before the final `}`):

```php
    public function test_custom_profile_fields_is_available_by_default_and_can_be_disabled(): void
    {
        // custom_profile_fields graduated to default-on (GA 2026-07-03): the
        // bounded "Custom profile fields" panel renders on /settings/account with
        // no features override, and an operator can still roll it back via the
        // features setting. Render-gated (no route), so this asserts the panel
        // markers rather than a route 404 (the wysiwyg_composer variant).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('custom_profile_fields', $flags->all());
        self::assertTrue($flags->enabled('custom_profile_fields'), 'custom_profile_fields graduated to default-on');

        // Isolation: graduating this flag must not enable a dark neighbour.
        self::assertFalse($flags->enabled('group_dms'));

        $member = $this->makeUser(['username' => 'cpf_default_member']);
        $this->actingAs($member);

        // Available by default: the settings panel renders its bounded field rows.
        $settings = $this->get('/settings/account');
        $this->assertStatus(200, $settings);
        self::assertStringContainsString('Custom profile fields', $settings->body());
        self::assertStringContainsString('name="custom_label_1"', $settings->body());

        // Operator rollback: disabling hides the panel; core profile editing stays.
        $this->setFlags(['custom_profile_fields' => false]);
        $rolledBack = $this->get('/settings/account');
        $this->assertStatus(200, $rolledBack);
        self::assertStringNotContainsString('name="custom_label_1"', $rolledBack->body());
        self::assertStringContainsString('name="signature"', $rolledBack->body());
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter test_custom_profile_fields_is_available_by_default_and_can_be_disabled`
Expected: FAIL — the flag is still `false`, so `enabled('custom_profile_fields')` is false and the panel markers are absent (assertion on `assertTrue(...)` / `Custom profile fields` fails).

- [ ] **Step 3: Flip the default** — `src/Core/FeatureFlags.php:73`, change:

```php
        'custom_profile_fields' => false, // bounded extra public profile fields
```

to:

```php
        'custom_profile_fields' => true,  // GA default-on (2026-07-03; reversible via features override) — bounded extra public profile fields
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter test_custom_profile_fields_is_available_by_default_and_can_be_disabled`
Expected: PASS.

- [ ] **Step 5: Run the full suite to catch any test that assumed the flag was dark**

Run: `composer test`
Expected: green. If a test fails because it assumed the custom-fields panel is absent, update it to reflect the graduated default (the flag is now on unless a test sets `features` off). Do not weaken the new test.

- [ ] **Step 6: Commit**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "$(cat <<'EOF'
feat(phase4): graduate custom_profile_fields to default-ON

Bounded public profile fields (migration 0062) are now available by default,
operator-reversible via the features override. Adds the marker-based
default-on/rollback test (render-gated: the /settings/account panel renders by
default and disappears under a features override).

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Graduate `split_merge` (flag flip + default-on test + carryover dark-list move)

**Files:**
- Modify: `src/Core/FeatureFlags.php:68`
- Modify: `tests/Integration/Core/AppPhase4CarryoverFoundationTest.php:16-41`
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (add one method)

**Interfaces:**
- Consumes: `TestCase` helpers `makeUser`, `makeAdmin`, `makeBoard`, `makeCategory`, `makeThread`, `actingAs`, `post`, `assertRedirectContains`, `assertStatus`; local `setFlags()`.
- Produces: nothing downstream depends on this task's code.

**Context (verified):** `POST /mod/t/{id}/split` and `/merge` (App.php:1870-1871) → `ModerationController::split/merge`, which call `requireSplitMerge()` (404 when flag off) **before** `requireUser()`, and catch `ValidationException` → 302 redirect to the thread. `makeThread(...)` returns `['thread_id','slug']`. `AppPhase4CarryoverFoundationTest:31-41` currently asserts `split_merge` **dark** in the `$carryovers` foreach; `AppThreadSplitMergeTest`/`AppImladrisFidelityTest` enable it explicitly in setUp (stay green after the flip).

- [ ] **Step 1: Write the failing test** — add to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
    public function test_split_merge_is_available_by_default_and_can_be_disabled(): void
    {
        // split_merge graduated to default-on (GA 2026-07-03): the moderator
        // split/merge routes are live for an in-scope moderator with no features
        // override, and an operator can still take the surface offline (404).
        $author = $this->makeUser(['username' => 'sm_default_author']);
        $board = $this->makeBoard($this->makeCategory('Split Merge Default'), ['slug' => 'sm-default']);
        $thread = $this->makeThread($board, $author, 'Split merge default', 'Opening post');
        $this->actingAs($this->makeAdmin(['username' => 'sm_default_admin']));

        // Available by default: the split route is live. An empty selection fails
        // validation and redirects back to the thread (proving it is not 404-dark).
        $this->assertRedirectContains(
            $this->post('/mod/t/' . $thread['thread_id'] . '/split', ['title' => 'Attempted split']),
            '/t/' . $thread['thread_id'],
        );
        self::assertTrue((new FeatureFlags(new SettingRepository($this->db)))->enabled('split_merge'));

        // Isolation: graduating split_merge must not enable a dark neighbour.
        self::assertFalse((new FeatureFlags(new SettingRepository($this->db)))->enabled('group_dms'));

        // Operator rollback: disabling the flag takes both routes offline (404).
        $this->setFlags(['split_merge' => false]);
        $this->assertStatus(404, $this->post('/mod/t/' . $thread['thread_id'] . '/split', ['title' => 'x']));
        $this->assertStatus(404, $this->post('/mod/t/' . $thread['thread_id'] . '/merge', ['target_thread_id' => 1]));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter test_split_merge_is_available_by_default_and_can_be_disabled`
Expected: FAIL — flag is `false`, so `requireSplitMerge()` throws 404 and the first `assertRedirectContains` fails (got 404, not a redirect).

- [ ] **Step 3: Flip the default** — `src/Core/FeatureFlags.php:68`, change:

```php
        'split_merge' => false,        // moderator split/merge dry-run/apply/repair operations
```

to:

```php
        'split_merge' => true,         // GA default-on (2026-07-03; reversible via features override) — moderator split/merge operations
```

- [ ] **Step 4: Move `split_merge` out of the carryover dark-list** — in `tests/Integration/Core/AppPhase4CarryoverFoundationTest.php`:

Change the graduated-flags foreach (line 21) to include `split_merge`:

```php
        foreach (['board_folders', 'bookmark_folders', 'saved_feeds', 'slash_giphy', 'profile_media', 'custom_emoji', 'split_merge'] as $flag) {
            self::assertArrayHasKey($flag, $flags->all(), "$flag must be declared, not merely unknown");
            self::assertTrue($flags->enabled($flag), "$flag should be default-on after graduation");
        }
```

Remove `'split_merge',` from the `$carryovers` array so it reads:

```php
        $carryovers = [
            'link_previews',
            'expanded_files',
            'automated_context',
        ];
```

Update the comment block above (lines 16–20) to append: `` `split_merge` graduated on 2026-07-03. ``

- [ ] **Step 5: Run both affected tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'test_split_merge_is_available_by_default_and_can_be_disabled|test_phase4_carryover_flags_have_expected_default_posture_and_override_independently'`
Expected: PASS (both).

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: green. If a moderator-viewing-a-thread test now unexpectedly sees the `.sm-panel`, update that test to reflect the graduated default.

- [ ] **Step 7: Commit**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppPhase4CarryoverFoundationTest.php
git commit -m "$(cat <<'EOF'
feat(phase4): graduate split_merge to default-ON

Moderator thread split/merge is now available by default (in-scope moderators),
operator-reversible via the features override (routes 404 when disabled). Adds
the route-based default-on/rollback test and moves split_merge from the carryover
dark-list to the graduated set in AppPhase4CarryoverFoundationTest.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Seeded-scale split/merge repair rehearsal (PHPUnit) + evidence transcript

**Files:**
- Create: `tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php`
- Create: `docs/evidence/split-merge-repair-rehearsal.md`

**Interfaces:**
- Consumes: `TestCase` (`makeAdmin`, `makeUser`, `makeBoard`, `makeCategory`, `makeThread`, `posting()`, `userEntity`, `actingAs`, `get`, `post`, `assertRedirectContains`, `$this->db->fetchAll/run`); `App\Service\RepairService` (`repairThreadCounters()`, `repairBoardCounters()`).
- Produces: the evidence markdown consumed by Task 7's inventory update.

**Context (verified):** `ThreadSplitMergeService` maintains `threads.reply_count`/`last_post_*` and `boards.thread_count`/`post_count`/`last_*` inside `$db->transaction()`. `RepairService::repairThreadCounters()`/`repairBoardCounters()` recompute those from scratch and **return a constant `1`** — so drift is detected by snapshot-diff, not the return value. `posting()->reply($userEntity, $threadId, ['body'=>...])` returns a new post id and (as constructed by `TestCase::posting()`) has no rate-limiter/anti-abuse, so it seeds scale cleanly. Replies/OPs are created in monotonic id≈timestamp order, so `RepairService`'s `MAX(id)` last-post tiebreak agrees with the service's `created_at DESC, id DESC` (they only diverge for backdated posts — out of scope; note it in the runbook known-limits).

- [ ] **Step 1: Write the rehearsal test** — create `tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php`:

```php
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
```

- [ ] **Step 2: Run the rehearsal test**

Run: `vendor/bin/phpunit --filter test_split_merge_leaves_no_counter_drift_at_scale`
Expected: PASS. If it FAILS on the final `assertEquals`, the failure diff names the drifted thread/board rows — this is a real counter-maintenance bug (a WHERE-clause mismatch between `ThreadSplitMergeService` and `RepairService`). **Stop and fix the mismatch; do not proceed.** (CLAUDE.md: counters must have matching recompute WHERE clauses.)

- [ ] **Step 3: Capture the evidence transcript** — create `docs/evidence/split-merge-repair-rehearsal.md`:

```markdown
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
2. `RepairService::repairThreadCounters()` + `repairBoardCounters()` → baseline; snapshot
   `threads.{reply_count,last_post_*}` and `boards.{thread_count,post_count,last_*}`.
3. Drive 8 splits + 12 merges over HTTP as an admin (real controller → service →
   repository path).
4. Snapshot the in-transaction-maintained counters (assert they differ from the
   baseline — non-vacuous).
5. Recompute from scratch and snapshot again.
6. Assert the post-ops snapshot equals the post-repair snapshot → zero drift.

Drift is detected by snapshot-diff because `repairThreadCounters()`/
`repairBoardCounters()` return a constant `1` (they recompute every row rather than
reporting changed rows).

## Result

```
$ vendor/bin/phpunit --filter test_split_merge_leaves_no_counter_drift_at_scale
<PASTE THE ACTUAL COMMAND OUTPUT HERE — the "OK (1 test, N assertions)" line>
```

**PASS** — no counter drift after a seeded-scale split/merge batch.

## Known limit

`RepairService` reconciles `last_post_*` by `MAX(id)`, while the live service
orders by `created_at DESC, id DESC`. These agree for naturally-ordered posts
(id monotonic with creation time); a backdated/imported post whose highest id is
not its latest timestamp could show a one-row `last_post_*` difference on repair.
That is a repair-recompute artifact, not split/merge drift, and is out of scope
for this graduation.
```

Replace the `<PASTE ...>` line with the real output from Step 2's run.

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php docs/evidence/split-merge-repair-rehearsal.md
git commit -m "$(cat <<'EOF'
test(phase4): seeded-scale split/merge repair rehearsal (zero-drift gate)

Proves ThreadSplitMergeService's in-transaction counter maintenance matches a
from-scratch RepairService recompute after a batch of splits+merges across
several boards. Drift is detected by snapshot-diff (repair returns a constant 1).
Captures the PASS transcript to docs/evidence/split-merge-repair-rehearsal.md.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Browser + a11y evidence — `custom_profile_fields`

**Files:**
- Modify: `tests/browser/seed.php` (add flag to `$evidenceFeatures`)
- Modify: `templates/account/settings.php:96` (add a scannable class hook)
- Create: `tests/browser/custom-profile-fields.spec.ts`
- Modify: `tests/browser/package.json` (register the spec in `evidence` + `evidence:prodlike`)
- Modify: `tests/browser/a11y.spec.ts` (scoped axe scan)

**Interfaces:**
- Consumes: seeded `bob@retro.test` / `password123`; the render-gated settings panel from Task 1.
- Produces: `docs/evidence/browser/{desktop,mobile}/50-custom-profile-fields.png`.

**Context (verified):** `bob` is a seeded member and can edit `/settings/account`; no extra seed fixture is needed (he sets the field in-flow). The a11y helper is `expectNoSeriousA11yViolations(page, info, include?)` where `include` is a CSS selector; `login`/`visit` helpers exist in `a11y.spec.ts`. The custom-fields panel is `<fieldset class="field">` with `<legend>Custom profile fields</legend>` — add a class hook for a clean axe scope.

- [ ] **Step 1: Add a scannable class hook** — `templates/account/settings.php:96`, change:

```php
            <fieldset class="field">
```

to:

```php
            <fieldset class="field custom-profile-fields">
```

- [ ] **Step 2: Enable the flag on the evidence seed** — `tests/browser/seed.php`, inside `$evidenceFeatures` (after the `custom_emoji` line ~66), add:

```php
    'custom_profile_fields' => true, // GA default-on (2026-07-03); listed explicitly so the /settings/account custom-fields panel is captured
```

- [ ] **Step 3: Write the browser spec** — create `tests/browser/custom-profile-fields.spec.ts`:

```typescript
import { test, expect, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * custom_profile_fields browser evidence (GA 2026-07-03). A member sets a bounded
 * custom profile field on /settings/account (no-JS form POST → PRG) and it renders
 * on their public profile. Proves the server-rendered custom-fields surface works
 * without JavaScript. Seeded credentials: bob@retro.test / password123.
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await dismissTour(page);
}

test('member sets a custom profile field and it renders on their public profile', async ({ page }, info) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/settings/account');
  const label = `Homelab (${info.project.name})`;
  const value = `Rack of Raspberry Pis ${info.project.name}`;
  await page.fill('input[name="custom_label_1"]', label);
  await page.fill('input[name="custom_value_1"]', value);
  await page.getByRole('button', { name: 'Save changes' }).click();

  // PRG back to the settings form; the saved value round-trips into the input.
  await page.waitForLoadState('load');
  await visit(page, '/settings/account');
  await expect(page.locator('input[name="custom_value_1"]')).toHaveValue(value);
  await shot(page, info, '50-custom-profile-fields');

  // Read path: the field renders on bob's public profile.
  await visit(page, '/u/bob');
  await expect(page.getByText(value)).toBeVisible();
});
```

- [ ] **Step 4: Register the spec in the evidence scripts** — `tests/browser/package.json`, append ` custom-profile-fields.spec.ts` to the `evidence` script (line 7) and the `evidence:prodlike` script (line 12). After editing, the `evidence` script reads:

```json
    "evidence": "bash prepare.sh && playwright test gate-a.spec.ts server-drafts.spec.ts appeals.spec.ts custom-profile-fields.spec.ts split-merge.spec.ts",
```

(Task 5 adds `split-merge.spec.ts`; if doing Task 4 first, append just `custom-profile-fields.spec.ts` now and `split-merge.spec.ts` in Task 5.)

- [ ] **Step 5: Add the scoped a11y scan** — `tests/browser/a11y.spec.ts`, add a top-level test (near the other phase-4 scans, e.g. after the profile-media scan ~L395):

```typescript
test('phase 4 custom profile fields panel has no serious axe violations', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/settings/account');
  await expect(page.locator('.custom-profile-fields')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.custom-profile-fields');
});
```

- [ ] **Step 6: Confirm the screenshot number is free**

Run: `ls docs/evidence/browser/desktop/ | sort -n | tail -3; grep -rn "'50-" tests/browser/*.spec.ts`
Expected: `50` is not already on disk or claimed by another spec. If taken, pick the next free number and update the spec + this plan.

- [ ] **Step 7: Run the browser spec + a11y scan (requires the e2e DB `retroboards_e2e`)**

Run:
```bash
cd tests/browser && npm install >/dev/null 2>&1; \
  bash prepare.sh && \
  npx playwright test custom-profile-fields.spec.ts && \
  npx playwright test a11y.spec.ts -g "custom profile fields"
```
Expected: PASS (desktop + mobile projects). `prepare.sh` migrates `retroboards_e2e` fresh and runs `seed.php`.

- [ ] **Step 8: Visually confirm the screenshot** — `Read docs/evidence/browser/desktop/50-custom-profile-fields.png` and confirm it shows the settings page with the saved custom field (label/value populated), not an error or empty panel.

- [ ] **Step 9: Commit**

```bash
cd "$(git rev-parse --show-toplevel)"
git add templates/account/settings.php tests/browser/seed.php tests/browser/custom-profile-fields.spec.ts tests/browser/package.json tests/browser/a11y.spec.ts docs/evidence/browser/desktop/50-custom-profile-fields.png docs/evidence/browser/mobile/50-custom-profile-fields.png
git commit -m "$(cat <<'EOF'
test(phase4): browser + a11y evidence for custom_profile_fields

No-JS member journey (set a bounded custom field on /settings/account → renders
on the public profile) captured desktop+mobile as 50-custom-profile-fields; adds
a scoped .custom-profile-fields axe scan and a class hook for it; enables the flag
on the evidence seed and registers the spec in the evidence scripts.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Browser + a11y evidence — `split_merge`

**Files:**
- Modify: `tests/browser/seed.php` (add flag + a `$ensureSplitMergeFixture` closure and invoke it)
- Create: `tests/browser/split-merge.spec.ts`
- Modify: `tests/browser/package.json` (register spec)
- Modify: `tests/browser/a11y.spec.ts` (scoped `.sm-panel` scan)

**Interfaces:**
- Consumes: seeded `alice@retro.test` (moderator of `#general`); the route-gated surface from Task 2; `.sm-panel` from `templates/thread.php`.
- Produces: `docs/evidence/browser/{desktop,mobile}/51-thread-split.png`, `52-thread-merge.png`.

**Context (verified):** The panel (`templates/thread.php:363`) shows only when `features['split_merge'] && can_moderate_board`, inside a collapsed `<details><summary>Split or merge topic</summary>` → `.sm-panel`. Split form posts `post_ids[]` + `title`; merge form posts `target_thread_id`. Success flashes are `Thread split.` / `Thread merged.`. Serial single-DB (desktop then mobile): the spec must operate on throwaway data per pass — it splits a reply into a **new** topic and merges that new topic away, so each pass consumes one seeded reply (seed ≥4) and its own split-off topic. `seed.php` already imports `PostingService`, `ThreadRepository`, `PostRepository`, `BoardRepository`, `UserRepository`, `Markdown`, `HtmlSanitizer`, `WriteGate`, `BoardPolicy`, and `$config`.

- [ ] **Step 1: Enable the flag + add the seed fixture** — `tests/browser/seed.php`:

Add to `$evidenceFeatures` (after the `custom_profile_fields` line from Task 4):

```php
    'split_merge' => true, // GA default-on (2026-07-03); listed explicitly so the moderator split/merge panel is captured
```

Define a fixture closure alongside the other `$ensure...` closures (e.g. after `$ensureShortcutPoll`):

```php
$ensureSplitMergeFixture = static function () use ($db, $users, $config): bool {
    $general = $db->fetch("SELECT id FROM boards WHERE slug = 'general' LIMIT 1");
    $bob = $users->findByUsername('bob');
    $alice = $users->findByUsername('alice');
    if ($general === null || $bob === null || $alice === null) {
        return false;
    }
    // Idempotent: only seed once (a re-seed keeps the same rehearsal topics).
    if ($db->fetchValue("SELECT id FROM threads WHERE title = 'Restructure rehearsal source' LIMIT 1") !== false) {
        return true;
    }
    $posting = new PostingService(
        $db,
        new ThreadRepository($db),
        new PostRepository($db),
        new BoardRepository($db),
        new UserRepository($db),
        new Markdown(new HtmlSanitizer()),
        new WriteGate(),
        new BoardPolicy(),
        $config,
    );
    $bobEntity = \App\Domain\User::fromRow($bob);
    $source = $posting->createThread($bobEntity, [
        'board_id' => (int) $general['id'],
        'title' => 'Restructure rehearsal source',
        'body' => 'A topic seeded so a moderator can rehearse split/merge in the browser.',
    ]);
    for ($i = 1; $i <= 4; $i++) {
        $posting->reply($bobEntity, $source['thread_id'], [
            'body' => 'Rehearsal reply ' . $i . ' — a movable post for the split panel.',
        ]);
    }
    $posting->createThread(\App\Domain\User::fromRow($alice), [
        'board_id' => (int) $general['id'],
        'title' => 'Restructure rehearsal target',
        'body' => 'The destination topic for a merge rehearsal.',
    ]);
    return true;
};
```

Invoke it next to the other fixtures (search for where `$ensureShortcutPoll(` / `$ensureAppealFixture(` are called and add):

```php
$ensureSplitMergeFixture();
```

- [ ] **Step 2: Write the browser spec** — create `tests/browser/split-merge.spec.ts`:

```typescript
import { test, expect, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * split_merge browser evidence (GA 2026-07-03). A moderator of #general opens the
 * collapsed "Split or merge topic" panel on a seeded thread, splits a reply into a
 * new topic (no-JS form POST → PRG), then merges that new topic into a seeded
 * target. Self-contained per pass (each run consumes one seeded reply and merges
 * away its own split-off topic), so it is safe on the shared serial e2e DB.
 * Seeded credentials: alice@retro.test / password123 (moderator of #general).
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await dismissTour(page);
}

test('a moderator splits a reply into a new topic and merges it away (no-JS)', async ({ page }, info) => {
  await login(page, 'alice@retro.test');

  // Find the seeded target topic's id first (needed for the merge form).
  await visit(page, '/c/general');
  const targetHref = await page.getByRole('link', { name: 'Restructure rehearsal target' }).getAttribute('href');
  const targetId = (targetHref ?? '').match(/\/t\/(\d+)/)?.[1];
  expect(targetId, 'seeded merge-target thread id').toBeTruthy();

  // Open the seeded source topic and expand the split/merge panel.
  await page.getByRole('link', { name: 'Restructure rehearsal source' }).click();
  await page.waitForURL(/\/t\/\d+/);
  await page.locator('summary', { hasText: 'Split or merge topic' }).click();
  await expect(page.locator('.sm-panel')).toBeVisible();
  await shot(page, info, '51-thread-split');

  // Split the first available reply into a new topic.
  const splitForm = page.locator('form[action$="/split"]');
  await splitForm.locator('input[name="post_ids[]"]').first().check();
  await splitForm.locator('input[name="title"]').fill(`Split-off ${info.project.name} ${Date.now()}`);
  await splitForm.getByRole('button', { name: 'Split replies out' }).click();
  await expect(page.locator('.flash')).toContainText('Thread split.');

  // Now on the split-off topic — merge it into the seeded target.
  await page.locator('summary', { hasText: 'Split or merge topic' }).click();
  const mergeForm = page.locator('form[action$="/merge"]');
  await mergeForm.locator('input[name="target_thread_id"]').fill(String(targetId));
  await shot(page, info, '52-thread-merge');
  await mergeForm.getByRole('button', { name: 'Merge topics' }).click();
  await expect(page.locator('.flash')).toContainText('Thread merged.');
});
```

- [ ] **Step 3: Register the spec** — `tests/browser/package.json`, append ` split-merge.spec.ts` to the `evidence` and `evidence:prodlike` scripts (see Task 4 Step 4 for the final form).

- [ ] **Step 4: Add the scoped a11y scan** — `tests/browser/a11y.spec.ts`, add:

```typescript
test('phase 4 split/merge panel has no serious axe violations', async ({ page }, info) => {
  await login(page, 'alice@retro.test');
  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Restructure rehearsal source' }).click();
  await page.waitForURL(/\/t\/\d+/);
  await page.locator('summary', { hasText: 'Split or merge topic' }).click();
  await expect(page.locator('.sm-panel')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info, '.sm-panel');
});
```

- [ ] **Step 5: Confirm screenshot numbers `51`/`52` are free**

Run: `ls docs/evidence/browser/desktop/ | sort -n | tail -3; grep -rn "'5[12]-" tests/browser/*.spec.ts`
Expected: not already claimed. If taken, renumber in the spec + this plan.

- [ ] **Step 6: Run the browser spec + a11y scan**

Run:
```bash
cd tests/browser && bash prepare.sh && \
  npx playwright test split-merge.spec.ts && \
  npx playwright test a11y.spec.ts -g "split/merge"
```
Expected: PASS (desktop + mobile).

- [ ] **Step 7: Visually confirm** — `Read docs/evidence/browser/desktop/51-thread-split.png` and `52-thread-merge.png`; confirm they show the expanded "Split a topic, or merge two" panel with the split reply list / filled merge form.

- [ ] **Step 8: Commit**

```bash
cd "$(git rev-parse --show-toplevel)"
git add tests/browser/seed.php tests/browser/split-merge.spec.ts tests/browser/package.json tests/browser/a11y.spec.ts docs/evidence/browser/desktop/51-thread-split.png docs/evidence/browser/mobile/51-thread-split.png docs/evidence/browser/desktop/52-thread-merge.png docs/evidence/browser/mobile/52-thread-merge.png
git commit -m "$(cat <<'EOF'
test(phase4): browser + a11y evidence for split_merge

No-JS moderator journey (expand the split/merge panel → split a reply into a new
topic → merge it into a target) captured desktop+mobile as 51-thread-split /
52-thread-merge; adds a scoped .sm-panel axe scan; seeds dedicated rehearsal
topics and enables the flag on the evidence seed; registers the spec.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Runbooks

**Files:**
- Create: `docs/runbooks/custom_profile_fields.md`
- Create: `docs/runbooks/split_merge.md`

**Interfaces:**
- Consumes: nothing. Produces docs referenced by Task 7's inventory rows.

**Context (verified):** Clone `docs/runbooks/polls.md` structure: banner (default-ON date + reversible), Golden rule (disable-first, non-destructive), What the flag gates + routes, Roll back / re-enable (the `php -r ... $f["flag"]=false ...` snippet), Operating semantics, Monitoring & known limits, Acceptance evidence.

- [ ] **Step 1: Write `docs/runbooks/custom_profile_fields.md`**

```markdown
# Runbook — Custom profile fields (`custom_profile_fields`)

Release/operations runbook for the **custom_profile_fields** feature (bounded
extra public profile facts). **Default-ON as of 2026-07-03** (graduated out of
deploy-dark); fully reversible via the `features` override. Schema shipped in
migration `0062`.

> **Golden rule:** for any defect, **disable the `custom_profile_fields` flag
> first** (the settings panel and profile rendering disappear; the rest of the
> account surface keeps serving), then investigate. Disabling is non-destructive —
> stored field values are retained and reappear when the flag is re-enabled.

## What the flag gates

Render-gated (no dedicated route). When on, `GET /settings/account` renders the
"Custom profile fields" panel and the values render on the public profile
`/u/{username}`. When off, the panel is not rendered and the fields do not appear
on profiles. Editing/saving still flows through the existing
`POST /settings/account` (`AccountController::updateAccount`); with the flag off,
the custom fields are simply not part of the form.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/PHASE_2_RUNBOOK.md` §2. Disabling is the first response to any defect and is
non-destructive:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["custom_profile_fields"]=false; $r->set("features",$f);'
```

Re-enable by setting `custom_profile_fields` back to `true` or removing the key
(the default is now `true`).

## Operating semantics

- **Bounded** — up to **three** fields; label ≤ **40** chars, value ≤ **160**
  chars (enforced server-side; the inputs also cap length).
- **Public** — the fields are shown on the member's public profile to anyone,
  including logged-out visitors. Copy on the settings panel states this ("public
  profile facts"); signed off as the shipping wording 2026-07-03.
- **Progressive enhancement** — plain server-rendered form fields, no inline
  script; works with JavaScript disabled under the strict CSP.
- **No denormalized counter** — nothing to reconcile in `RepairService`.

## Monitoring & known limits

- **Not separately rate-limited** — edits go through the normal account-update
  path; abuse is bounded by the 3-field / length caps. If abused, disable the flag
  for that release.
- **No moderation surface for field content** — values are free text within the
  length caps; there is no dedicated review queue. If a field is abused, an admin
  can clear it via the member's account or disable the flag.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppFeatureFlagTest.php`
  (`test_custom_profile_fields_is_available_by_default_and_can_be_disabled`) and
  the `0062` slice in `tests/Integration/Core/AppBoardFoldersSavedFeedsTest.php`.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/50-custom-profile-fields.png`
  (`tests/browser/custom-profile-fields.spec.ts`) — set a field → renders on the
  public profile.
- **Accessibility:** `tests/browser/a11y.spec.ts` — axe scan of the
  `.custom-profile-fields` panel, desktop + mobile, no serious/critical violations.
```

- [ ] **Step 2: Write `docs/runbooks/split_merge.md`**

```markdown
# Runbook — Thread split/merge (`split_merge`)

Release/operations runbook for the **split_merge** feature (moderator thread
split and merge). **Default-ON as of 2026-07-03** (graduated out of deploy-dark);
fully reversible via the `features` override. Operates over the existing
`thread_operations` / `thread_redirects` schema.

> **Golden rule:** for any defect, **disable the `split_merge` flag first** (both
> routes 404, the panel disappears, the rest of the thread keeps serving), then
> investigate. Disabling is non-destructive — already-applied splits/merges, their
> redirects, and audit rows are retained.

## What the flag gates

`split_merge` gates two POST routes (CSRF-protected), each 404 when the flag is
off (the in-controller `requireSplitMerge()` gate fires before the auth check):

- `POST /mod/t/{id}/split` — move selected non-OP replies into a new topic.
- `POST /mod/t/{id}/merge` — fold this topic's posts into a target topic, soft-
  delete the source, and write an old→canonical redirect.

The "Split or merge topic" panel (`.sm-panel`, collapsed `<details>`) renders on
the thread view only for a board moderator (`can_moderate_board`) when the flag is
on.

## Roll back / re-enable

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["split_merge"]=false; $r->set("features",$f);'
```

Re-enable by setting `split_merge` back to `true` or removing the key (the default
is now `true`).

## Operating semantics

- **Authority** — admin anywhere, or an assigned board moderator
  (`ModerationService::canModerate`); banned/suspended actors are blocked by
  `WriteGate` ("state beats role").
- **Split** — the OP cannot be split out; every selected post must belong to the
  source thread; the earliest selected post becomes the new topic's OP.
- **Merge** — all source posts move to the target (the old OP becomes a normal
  reply), the source is soft-deleted, and `/t/{old}` 302-redirects to the target.
  Cross-board merges recount **both** boards.
- **Audit** — every split/merge writes a `thread_operations` row (`status=applied`)
  and a `moderation_log` entry (`split_thread` / `merge_thread`); merges also write
  a `thread_redirects` row.
- **Counters** — `threads.reply_count`/`last_post_*` and `boards.thread_count`/
  `post_count`/`last_*` are maintained inside the operation's transaction. Run
  `php bin/console repair` to reconcile if you ever suspect drift; it recomputes
  from authoritative rows.

## Monitoring & known limits

- **Repair no-drift verification** — the seeded-scale rehearsal
  (`tests/Integration/Core/AppThreadSplitMergeRehearsalTest.php`,
  `docs/evidence/split-merge-repair-rehearsal.md`) proves a batch of splits/merges
  across several boards leaves zero counter drift vs. a from-scratch `repair`.
- **`last_post_*` tiebreak** — `repair` reconciles `last_post_*` by `MAX(id)` while
  the live service orders by `created_at DESC, id DESC`. They agree for naturally-
  ordered posts; a backdated post could show a one-row `last_post_*` difference on
  repair (a recompute artifact, not operation drift).
- **Not separately rate-limited** — the routes require moderator authority, which
  bounds abuse; add a policy before broad enablement if a board sees churn.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppThreadSplitMergeTest.php` (split/merge +
  redirect + in-transaction counters + timestamp recount),
  `AppFeatureFlagTest::test_split_merge_is_available_by_default_and_can_be_disabled`
  (default-on redirect + operator rollback to 404), and the seeded-scale
  rehearsal `AppThreadSplitMergeRehearsalTest`.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/51-thread-split.png` +
  `52-thread-merge.png` (`tests/browser/split-merge.spec.ts`).
- **Accessibility:** `tests/browser/a11y.spec.ts` — axe scan of the `.sm-panel`,
  desktop + mobile, no serious/critical violations.
```

- [ ] **Step 3: Commit**

```bash
git add docs/runbooks/custom_profile_fields.md docs/runbooks/split_merge.md
git commit -m "$(cat <<'EOF'
docs(phase4): operator runbooks for custom_profile_fields + split_merge

Disable-first golden rule, what each flag gates, roll back/re-enable snippet,
operating semantics, monitoring/known limits, and the acceptance-evidence list —
cloned from the polls runbook convention.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Deploy-dark inventory + status reconciliation

**Files:**
- Modify: `docs/evidence/deploy-dark-features.md`
- Modify: `PHASE_4_STATUS.md`
- Verify (edit only if named): `CLAUDE.md`, `tests/browser/README.md`

**Interfaces:**
- Consumes: the runbooks + evidence artifacts from Tasks 3–6. Produces the reconciled inventory.

- [ ] **Step 1: Recompute the flag tallies**

Run: `grep -cE '=> true,' src/Core/FeatureFlags.php; grep -cE '=> false,' src/Core/FeatureFlags.php`
Expected: `37` and `20`. Use these exact numbers in the edits below.

- [ ] **Step 2: Update `docs/evidence/deploy-dark-features.md`**
  - Bump the `**Date:**` header note to mention the 2026-07-03 `custom_profile_fields` + `split_merge` graduation.
  - In "Source Code Audit": update `declares 57 flags: 35 default true, 22 default false` → `37 default true, 20 default false`; update the table-row breakdown (`22 current default-dark` → `20`, `17 retained graduated` → `19`; total rows unchanged at 39).
  - Rewrite the **`custom_profile_fields`** row (Phase 4 Carryover Completion table, ~L80) and the **`split_merge`** row (~L75) to the graduated form, e.g.:
    `| \`split_merge\` | Moderator split/merge routes, redirects, audit, touched-counter repair | **Graduated 2026-07-03 — now default-ON** (reversible via \`features\` override). Acceptance evidence: \`AppThreadSplitMergeTest\`, \`AppFeatureFlagTest\` (default-on + rollback), seeded-scale rehearsal \`AppThreadSplitMergeRehearsalTest\` + \`docs/evidence/split-merge-repair-rehearsal.md\`, browser \`51-thread-split\`/\`52-thread-merge\`, \`.sm-panel\` axe pass, runbook \`docs/runbooks/split_merge.md\`. Retained here for traceability. |`
    and for custom_profile_fields cite `AppBoardFoldersSavedFeedsTest`, `AppFeatureFlagTest`, browser `50-custom-profile-fields`, `.custom-profile-fields` axe pass, runbook `docs/runbooks/custom_profile_fields.md`.
  - In the **Graduation Readiness Ranking**, mark Tier 3 entry **#9 `custom_profile_fields`** and Tier 4 entry **#10 `split_merge`** graduated **in place** (numbers retained), matching the `✓ **Graduated 2026-07-03 (default-ON).**` convention of entries #1–8.
  - Add a **Notes** bullet for each (mirroring the existing per-flag graduation bullets), noting reversibility and that stored values / applied operations survive rollback.

- [ ] **Step 3: Update `PHASE_4_STATUS.md`**

Run: `grep -n 'custom_profile_fields\|split_merge\|Last updated' PHASE_4_STATUS.md`
Then remove both flags from any default-`false` / deploy-dark enumeration and bump the "Last updated" date to 2026-07-03. If neither flag is enumerated there, make only the date bump.

- [ ] **Step 4: Verify CLAUDE.md and tests/browser/README.md**

Run: `grep -n 'custom_profile_fields\|split_merge' CLAUDE.md tests/browser/README.md`
Expected: likely no matches (the CLAUDE.md flags paragraph names graduated flags + `group_dms`/`community_memory`, not these two). Edit only if a stale posture reference is found.

- [ ] **Step 5: Closing stale-reference check**

Run: `grep -rn 'deploy-dark' --include='*.md' . | grep -iE 'custom_profile_fields|split_merge'`
Expected: only the `deploy-dark-features.md` "retained for traceability" rows (now graduated). No line should describe either flag as still-dark.

- [ ] **Step 6: Commit**

```bash
git add docs/evidence/deploy-dark-features.md PHASE_4_STATUS.md CLAUDE.md tests/browser/README.md
git commit -m "$(cat <<'EOF'
docs(phase4): reconcile inventory for custom_profile_fields + split_merge graduation

Rewrites both rows to the graduated form, marks their readiness-ranking entries
graduated in place, recomputes the flag tallies (37 true / 20 false), and bumps
the status date.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

(Only `git add` files that actually changed — drop `CLAUDE.md`/`README.md` from the command if Step 4 found nothing.)

---

### Task 8: Final verification (DESIGN §13 completion gate)

**Files:** none (verification only).

- [ ] **Step 1: Full PHPUnit suite green**

Run: `composer test`
Expected: OK, no failures/warnings/risky. This exercises both flipped defaults across the whole suite.

- [ ] **Step 2: Confirm the rehearsal transcript is real** — open `docs/evidence/split-merge-repair-rehearsal.md` and confirm the Result block contains the actual `OK (1 test, …)` line from Task 3 (not the `<PASTE …>` placeholder).

- [ ] **Step 3: Confirm screenshots exist and show the real surfaces**

Run: `ls -1 docs/evidence/browser/desktop/5[012]-*.png docs/evidence/browser/mobile/5[012]-*.png`
Then `Read` each of `50/51/52` (desktop) once more to confirm content.

- [ ] **Step 4: Tally + stale-reference final check**

Run: `grep -cE '=> true,' src/Core/FeatureFlags.php; grep -cE '=> false,' src/Core/FeatureFlags.php; grep -rn 'deploy-dark' --include='*.md' . | grep -iE 'custom_profile_fields|split_merge'`
Expected: `37`, `20`, and only graduated-form inventory rows.

- [ ] **Step 5: Confirm the commit set is scoped** — `git log --oneline origin/main..HEAD` (or against the branch's fork point) shows only the graduation commits; `git status` shows the pre-existing Phase 5 doc hunks still uncommitted and untouched.

---

## Self-Review (completed against the spec)

- **Spec coverage:** §A → Task 1; §B.1/B.2 → Task 2; §B.3 rehearsal → Task 3 (mechanism refined: snapshot-diff + PHPUnit, see Planning refinements); §A.3/§B.4 browser+a11y → Tasks 4–5; §A.4/§B.5 runbooks → Task 6; §C docs sweep → Task 7; Verification → Task 8. All spec sections map to a task.
- **Placeholder scan:** the only intentional fill-in is the rehearsal transcript's `<PASTE …>` (real command output, captured at execution) — Task 8 Step 2 gates it. Screenshot numbers are provisional-by-design with a confirm-free-number step each.
- **Type/name consistency:** `setFlags`/`enableSplitMerge` local helpers match their files' existing patterns; `posting()->reply(...)`, `makeThread(...)['thread_id']`, `assertRedirectContains`, `expectNoSeriousA11yViolations(page, info, include)`, `.sm-panel`, `name="custom_label_1"`, flash strings `Thread split.`/`Thread merged.` are all verified against source.
