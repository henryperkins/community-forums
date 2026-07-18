# PR #44 Safety Remediation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement `docs/superpowers/specs/2026-07-18-pr44-safety-remediation-design.md` (revised 2026-07-18 per written-spec review): close the seven release-blocking findings on PR #44, repair the named controller/repository boundaries, and turn every verification gate green — without widening moderator authority, changing flag defaults, or weakening any test.

**Architecture:** Scoped, service-first (spec "Approach 3"). One shared thread-read service gates every thread render including moderation/reply/edit failure re-renders; `/mod/u/{id}` becomes actor-aware and board-scoped; board delete revalidates inside a locking transaction; read models move behind focused service methods; idempotency reuses the existing `submission_idempotency` seam (no migration — the next free slot `0078` stays free and `SCHEMA.md` stays at v1.38).

**Tech stack:** PHP 8.2 (CI captures on 8.4), plain PHP templates, hand-wired container in `App::buildContainer()`, PHPUnit (strict; suites `unit`/`integration`), Playwright evidence harness in `tests/browser` (serial, `workers:1`, desktop 1280×800 / mobile 390×844), MariaDB (system service locally, `mariadb:11` in CI).

---

## Verified baseline (do not re-derive; re-confirm in Task 0)

- Local branch `admin-console-remediation`, head = spec-revision commit on top of `1e29780`. Only `docs/evidence/browser/**/*.png` are dirty in the original checkout — **preserve them untouched** (final gate 10).
- PR #44 CI run `29631399688` (head `80efe39`) — exactly **14 browser failures**:

  | # | Project | Case | Time |
  |---|---|---|---|
  | 1 | desktop | `composer-shell.spec.ts:502` slash/reference popovers do not reflow | 30.1s timeout |
  | 2 | desktop | `composer-shell.spec.ts:576` emoji dialog traps focus… | **8.3s — real assertion failure** |
  | 3 | desktop | `gate-a.spec.ts:410` package lifecycle | 30.0s timeout |
  | 4 | desktop | `gate-a.spec.ts:926` admin webhooks | 30.0s timeout |
  | 5 | desktop | `gate-a.spec.ts:1168` reorder + archive boards | 30.0s timeout |
  | 6 | mobile | `api-tokens.spec.ts:80` no-JS mint | 30.0s timeout |
  | 7 | mobile | `composer-shell.spec.ts:502` popovers | 30.1s timeout |
  | 8 | mobile | `gate-a.spec.ts:369` role editor | 30.0s timeout |
  | 9 | mobile | `gate-a.spec.ts:410` package lifecycle | 30.0s timeout |
  | 10 | mobile | `gate-a.spec.ts:889` API tokens mint/revoke | 30.0s timeout |
  | 11 | mobile | `gate-a.spec.ts:926` webhooks | 30.0s timeout |
  | 12 | mobile | `gate-a.spec.ts:1168` reorder + archive | 30.0s timeout |
  | 13 | mobile | `gate-a.spec.ts:1237` email dashboard | 30.0s timeout |
  | 14 | mobile | `invitations.spec.ts:104` invitations console | 30.0s timeout |

  Triage signals: 12 of 14 are uniform ~30s Playwright test-timeout kills (a hung `expect`/navigation, not slow success); the failure set skews mobile; the only non-timeout is desktop emoji `:576`. `npm run evidence` is three `prepare.sh`-separated segments — segment 1 (`thread-view-study` + `rich-content`, "21 passed") was green, the 14 are all segment 2, and because segment 2 exited non-zero **segment 3 (`admin-remediation.spec.ts`) never ran in CI** — its status is unknown until Task 0.
- `main`'s own run `29620879445` (`084ed0c`) also failed; its step log is expired and its artifact is screenshots-only, so the Composer-shell pre-existing/PR-added split **cannot** be taken from CI. Task 0 derives it locally.
- The two Thread Intelligence failures are **not** in `ThreadIntelligenceConcurrencyTest` (that file is green). They are:
  - `ThreadIntelligenceQueueTest::test_resume_rechecks_content_after_a_private_to_public_sweep` (`tests/Integration/ThreadIntelligence/ThreadIntelligenceQueueTest.php:676`) — ERROR;
  - `ThreadIntelligenceRepositoryTest::test_prune_revalidates_when_a_job_concurrently_becomes_review_protected` (`…/ThreadIntelligenceRepositoryTest.php:777`) — FAILURE (child exits 255).

  Shared proven cause: MariaDB raises `SQLSTATE HY000 / 1020 "Record has changed since last read"` when a REPEATABLE-READ transaction issues a **locking read** (`LOCK IN SHARE MODE` / `FOR UPDATE`) on a row another connection changed and committed after the transaction's read view formed (MariaDB snapshot-isolation semantics; MySQL/InnoDB silently re-reads latest-committed instead). Throw sites: `ThreadIntelligenceQueue::lockCurrentVisibilityOrFail` (`src/Service/ThreadIntelligence/ThreadIntelligenceQueue.php:245-251`) and the locking re-check in `ThreadIntelligenceGenerationRepository::pruneEligible` (`src/Repository/ThreadIntelligenceGenerationRepository.php:227,256`).

## Reference documents

- Spec: `docs/superpowers/specs/2026-07-18-pr44-safety-remediation-design.md` (§ numbers below cite it)
- Deviation/record targets: `docs/adr/0021-admin-console-remediation-and-deferrals.md`, `docs/history/admin-ux-remediation-2026-07-18.md`, `ADMIN.md` §3.4 + §13 changelog
- House rules that bite here: CLAUDE.md — PDO (`LIMIT`/`OFFSET` clamped+inlined, no reused named placeholders), strict PHPUnit, CSP (no inline script/style), PE (no-JS first), counters need matching `RepairService` predicates, controllers thin / services own rules / repos single-table.

## Priority order

- [ ] **P0:** Task 2 (thread-read gate on every moderation path) and Task 3 (board-scoped `/mod/u/{id}`) — the two disclosure findings.
- [ ] **P1:** Task 1 (TI harness/runtime — unblocks Task 6's concurrency test), Task 5 (dashboard draft loss), Task 6 (board delete), Task 8 (token re-mint).
- [ ] **P1/P2:** Task 4 (IP recency), Task 7 (read models, pagination, boundaries).
- [ ] **Gates:** Task 9 (browser green-up + full suites), Task 10 (recording + final gates).

Execute in numeric order. Task 1 must precede Task 6 (its two-connection pattern is reused). Tasks 2→3 share the `BoardAuthority` extraction. Within Task 7 the subtasks are independent.

## Global constraints

- [ ] Work in the implementation worktree only (Task 0). Never edit or run tests from the original dirty checkout; never touch its modified evidence PNGs.
- [ ] Test-first: every behavior change starts with the smallest failing PHPUnit regression, run red before the fix (`vendor/bin/phpunit <file> --filter <method>`), then green.
- [ ] No new migrations, no `SCHEMA.md` edits (§7 decision). If any task appears to need DDL, stop — the spec forbids it.
- [ ] No Playwright timeout raises, no assertion weakening, no `test.skip`, no documenting red tests as "pre-existing" (spec out-of-scope list).
- [ ] Preserve CSP: no inline `<script>`/`<style>`/`on*=` in any touched template. Every new form works no-JS. New hidden idempotency fields follow the composer precedent (`templates/partials/composer_shell.php:62`, server-rendered `bin2hex(random_bytes(16))`).
- [ ] PDO rules: inline clamped ints for `LIMIT`/`OFFSET` and `IN (...)` id lists (`array_map('intval', …)` + `implode`, per `ThreadRepository::listPending`); unique named placeholders.
- [ ] Feature-flag defaults untouched. No pushing, no PR updates, no review-thread actions — commits stay local until the user instructs otherwise.
- [ ] Commit per task (conventional message + `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`), on the worktree branch.
- [ ] PHPUnit (`retroboards_test`) and the browser harness (`retroboards_e2e`) use different DBs but one MariaDB — run them serially, and never run two worktrees' harnesses at once (`prepare.sh` drops/recreates `retroboards_e2e` by name).

---

## Task 0: Worktrees, environment, reproduce-first baselines

**Files:** none in-repo (worktrees + a scratch triage note under the session scratchpad).

**Instructions:**

- [ ] From the original checkout: `git worktree add ../community-forums-pr44 -b pr44-remediation admin-console-remediation` (implementation worktree, branch from the local head that already includes the spec + this plan).
- [ ] `git worktree add ../community-forums-main-baseline --detach 084ed0c` (throwaway baseline worktree at current `origin/main`).
- [ ] In each worktree: `cp <original>/.env .env`, `composer install`, `mkdir -p storage/ratelimit-e2e storage/packages-e2e`, and in `tests/browser`: `npm ci && npx playwright install --with-deps chromium`.
- [ ] **Baseline leg (run first, alone):** in `community-forums-main-baseline/tests/browser`, `npm run evidence`. Record every failing case verbatim. This adjudicates the spec's open question: how many Composer-shell cases (`:502` desktop/mobile, `:576` desktop) fail on `main`, and whether anything else does. (Expect ≥2; the PR run shows 3.)
- [ ] **PR reproduction leg:** in `community-forums-pr44`, `composer test` — confirm exactly the two TI failures named above (plus zero others); then `cd tests/browser && npm run evidence` — confirm the 14, capture whether segment 3 (`admin-remediation.spec.ts`) passes now that it actually runs, and for the eleven 30s admin timeouts inspect the **first** failing case's trace/state in seeded order (`docs/evidence/browser/.artifacts/`) before reading later ones as independent — the uniform-timeout + mobile-skew signature suggests one shared cause (a hung element/overlay on admin pages at 390×844 is the leading hypothesis; verify, don't assume).
- [ ] Write the triage note (baseline split, first-divergence findings, segment-3 status) to the scratchpad; Task 9 consumes it, Task 10 records the final numbers in the disposition doc.
- [ ] If (and only if) the shared cause corrupts *all* admin-page interaction such that Tasks 2–8 could not capture browser evidence, pull that single fix forward from Task 9 now, with its own red-first spec check.

**Acceptance criteria:**

- [ ] Both worktrees build; `composer test` reproduces exactly 2 known failures; `npm run evidence` reproduces exactly the known 14 (± differences recorded, not assumed).
- [ ] Composer-shell baseline on `main` is written down with case names — the "2 + 12" decomposition is replaced by measured numbers.

**Verification:** the recorded triage note exists and names every failing case on both branches.

---

## Task 1: Thread Intelligence concurrency repair (MariaDB 1020)

**Files:**
- Modify: `src/Repository/ThreadIntelligenceGenerationRepository.php` (pruneEligible re-check, `:227-260`)
- Modify: `src/Service/ThreadIntelligence/ThreadIntelligenceQueue.php` (`lockCurrentVisibilityOrFail`, `:245-251`) — if adjudicated runtime-side
- Modify: `tests/Integration/ThreadIntelligence/ThreadIntelligenceQueueTest.php` (`:676-710`) — if adjudicated harness-side
- Modify: `tests/Integration/ThreadIntelligence/ThreadIntelligenceRepositoryTest.php` (`:777-869`)

**Instructions:**

- [ ] Re-run each failure individually first (`vendor/bin/phpunit tests/Integration/ThreadIntelligence/ThreadIntelligenceQueueTest.php --filter test_resume_rechecks_content_after_a_private_to_public_sweep`, same for the prune test) and in the full suite, capturing the 1020 traces.
- [ ] Adjudicate per the spec's rule (runtime defect → fix runtime; harness mis-models MariaDB → fix harness with evidence the production invariant is still exercised):
  - **Prune (Failure B) is production-reachable:** the prune worker's transaction does a non-locking eligibility read, a concurrent connection commits `state='review_required'`, and the `FOR UPDATE` re-check then throws 1020 and crashes the worker — on MariaDB the very race `pruneEligible`'s revalidate-under-lock exists to absorb. Fix the **runtime**: treat 1020 at that locking re-check as "row changed since read" and resolve it to the already-designed outcome (re-read under a fresh view / treat the row as ineligible this pass — nothing pruned), mirroring how `IdempotencyRepository::record` absorbs 1205/1213. Detection by errno (`$e->errorInfo[1] === 1020`), never by message text.
  - **Resume (Failure A):** decide whether any production request can non-locking-read the `boards` row and later locking-read it *inside one transaction* across a competing commit (trace `Database::transaction` nesting into `resumeAndRequeue`'s callers). If yes → same runtime treatment at `lockCurrentVisibilityOrFail` (1020 ⇒ the existing "visibility changed → reconcile" path). If no → the test's parent-side `beginTransaction()` + pre-read at `:689-693` pins a snapshot no production code pins: restructure the test so the parent's transaction/read view starts *after* the competitor's commit, keeping both connections, the competing commit, and every existing assertion intact.
- [ ] Whatever the split, both tests must remain genuinely two-connection (spec: they must not become single-connection unit tests), and any runtime 1020-handling gets its own focused unit/integration coverage proving the clean outcome (worker survives, returns "nothing pruned"/"reconcile", no 500).
- [ ] Do not change CI's MariaDB image, session isolation level, or `innodb_snapshot_isolation` — that would suppress the signal, not fix the mismatch.

**Acceptance criteria:**

- [ ] Both named tests pass individually and inside `composer test`; the TI directory shows 0 errors/failures (1 pre-existing skip is acceptable if unrelated — record it).
- [ ] The second-connection choreography (child process / `secondDatabase()`) is still present in both tests.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/ThreadIntelligence/`
- [ ] `composer test`

---

## Task 2: Shared thread-read service + read→authority→validation ordering (spec §1)

**Files:**
- Create: `src/Security/BoardAuthority.php`
- Create: `src/Service/ThreadReadService.php`
- Modify: `src/Service/ModerationService.php`, `src/Service/ThreadSplitMergeService.php`
- Modify: `src/Controller/ModerationController.php`, `src/Controller/ThreadController.php`, `src/Controller/PostController.php`
- Modify: `src/Core/App.php` (bindings)
- Create: `tests/Integration/Core/AppModerationReadGateTest.php`
- Reference (must stay green): `AppModeratorScopeTest`, `AppThreadSplitMergeTest`, `AppModerationDraftLossTest`, `AppPrivateBoardAccessTest`, `AppPrivateBoardMembershipTest`, `AppContentApprovalTest`, `AppFeatureFlagTest::test_split_merge_is_available_by_default_and_can_be_disabled`

**Context (verified):** the read gate exists only in `ThreadController::loadReadableThread()` (`ThreadController.php:477-507`); `renderThread()` trusts callers. Three ungated re-render callers: `ModerationController::rerenderThread` (`:143-152`), `PostController::reply` (`:80`), `PostController::edit` (`:129`). `ModerationService::moveThread` (`:255-309`) validates the destination (`ValidationException`, `:265-268`) before source authority (`:269`) — the exploitable 422; it also early-returns a success redirect (leaking the slug in `Location`) on `src === dest` **before any check** (`:261-264`). `requireModeratableThread` (`:312`) 404s-then-403s, an existence oracle on pin/lock/restore/reveal. `canModerate` (`:56-67`) wraps WriteGate + `BoardModeratorRepository::isModerator` in `AuthorityGate::allows`. Planning discovery: the reply path is *not* a content leak today (`PostingService::reply` runs the `canPost` gate before body validation, `PostingService.php:218-246`) — but a validation failure on a **pending** thread re-renders its title to any poster, and the catch-paths are one refactor away from regressing; they join the shared loader.

**Instructions:**

*Red tests first* (`AppModerationReadGateTest`, seeding via `makeBoard($cat,['visibility'=>'private'])` + `BoardMemberRepository::add` / `BoardModeratorRepository::assign`, approval via `UPDATE boards SET require_approval=1`):

- [ ] Unrelated logged-in user POSTs `/mod/t/{privateThread}/move` with `board_id=0` → expect **404**, body contains neither title nor OP text (today: 422 with both).
- [ ] Same POST with `board_id = <thread's own board>` → **404** (today: 302 whose `Location` carries the slug).
- [ ] Unrelated user POSTs `/mod/t/{privateThread}/merge` with `target_thread_id = <same id>` → **404** (today: 422 leak, `split_merge` default-on).
- [ ] Unrelated user POSTs `/mod/t/{privateThread}/pin` → **404** (today: 403 — oracle).
- [ ] Pending-thread variants of the move/merge cases (public board, `is_pending=1`, non-author actor) → **404**, title absent.
- [ ] Non-member moderator **assigned** to a private board: `GET /t/{id}` → **200** (the §1 readability decision — today 404); their failed move (`board_id=0`) → **422** with typed context preserved.
- [ ] Readable non-moderator (public board member) POSTs move → **403** (authority after read).
- [ ] Authorized mod merges into a nonexistent target vs. an unreadable private target → **identical 422** (`target_thread_id` error) — no oracle; merge into a readable thread on a board they don't moderate → **403**.
- [ ] Reply path: stranger POSTs empty reply to a *pending* public thread → **404** (today 422 + title); the pending thread's **author** posting an invalid reply → still **422** with `reply_old` preserved.

*Implementation:*

- [ ] `BoardAuthority` (new, `src/Security/`): ctor `(WriteGate, BoardModeratorRepository, BoardRepository, ?AuthorityGate = null)`. Relocate the bodies of `ModerationService::canModerate` and `moderableBoardIds` verbatim (same `AuthorityGate::legacy()` fallback, same caps/context), plus `isAssigned(User $user, int $boardId): bool` = raw `isModerator()` (no WriteGate — reading must not consume write state; "a suspended admin can read but not write").
- [ ] `ThreadReadService` (new): ctor `(ThreadRepository, BoardPolicy, BoardMemberRepository, BoardAuthority)`. `loadForUser(?User $user, int $threadId): array`: `findWithBoard` → missing/`is_deleted` → `NotFoundException`; `$isMember` via `BoardMemberRepository::isMember`; **readable = `BoardPolicy::canRead([...visibility], $user, $isMember) OR ($user && BoardAuthority::isAssigned($user, $boardId))`** (the §1 decision; `BoardPolicy` stays pure — assignment resolved here and passed nowhere) → else 404; pending rule preserved **verbatim** (author `owns()` OR `BoardAuthority::canModerate($user, $boardId)` — the same WriteGate-consuming predicate `loadReadableThread` uses today, deliberately unchanged) → else 404. Return the thread row.
- [ ] `ModerationService`: ctor gains `BoardAuthority` + `ThreadReadService`; `canModerate`/`moderableBoardIds` become delegations (public signatures unchanged — `ThreadSplitMergeService`, `ThreadController`, `ReportController`, `AppealService` keep working). Reorder `moveThread`: (1) `readService->loadForUser($mod, $threadId)` [404]; (2) `assertCanModerate($mod, $src, THREAD_MOVE)` [403]; (3) `src === dest` no-op return (now reachable only by an authorized reader); (4) destination find → `ValidationException` [422]; (5) destination authority [403]; (6) both `assertNotArchived` [403]; (7) unchanged transaction. `requireModeratableThread` (pin/lock) and the post-targeted paths (`deletePost`/`restorePost`/`revealAuthor`: load post → load its thread through `ThreadReadService` with the actor) get the read gate before authority so unreadable actors uniformly 404.
- [ ] `ThreadSplitMergeService`: ctor gains `ThreadReadService`. `split`: source via `loadForUser` [404] → existing authority [403] → existing validation. `merge`: source via `loadForUser` [404] → source authority [403] → `same-id` → 422; **target** resolved via `loadForUser` from the actor's perspective, where missing *or unreadable* both become `ValidationException(['target_thread_id' => 'Choose a valid target thread.'])` (replacing today's `NotFoundException`, and deliberately identical for both cases); readable target → target authority [403].
- [ ] `ModerationController::rerenderThread`: replace the bare `findWithBoard` + `is_deleted` check with `ThreadReadService::loadForUser($this->currentUser(), $threadId)`.
- [ ] `ThreadController::loadReadableThread`: body becomes a one-line delegation to `ThreadReadService` (public behavior identical except assigned-mod readability).
- [ ] `PostController::reply`/`edit` catch-blocks: replace `findWithBoard` + null-check with `ThreadReadService::loadForUser($user, …)`.
- [ ] §4 move-destination boundary: `renderThread` builds `move_boards` from `moderableBoardIds` (`ThreadController.php:209-213`) — if any board-row assembly happens controller-side, add `ModerationService::moveDestinations(User): array` (ids → `BoardRepository` rows) and consume it; if it is already service-shaped, record a no-change disposition in Task 10.
- [ ] `App::buildContainer()`: bind `BoardAuthority`, `ThreadReadService`; extend the `ModerationService` (`App.php:1891`) and `ThreadSplitMergeService` (`:1368`) bindings. All unconditional (no flag ternaries — same as today).

**Acceptance criteria:**

- [ ] All new red tests green; every referenced existing file still green (notably: `testModeratorCannotMoveIntoABoardTheyDoNotModerate` still 403, split draft-loss still 422, flag-off split/merge still 404, private/pending page tests unchanged).
- [ ] No response on any `/mod/t/*` or `/mod/p/*` route distinguishes "exists but unreadable" from "does not exist".

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppModerationReadGateTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppModeratorScopeTest.php tests/Integration/Core/AppThreadSplitMergeTest.php tests/Integration/Core/AppModerationDraftLossTest.php tests/Integration/Core/AppPrivateBoardAccessTest.php tests/Integration/Core/AppPrivateBoardMembershipTest.php tests/Integration/Core/AppContentApprovalTest.php`
- [ ] `vendor/bin/phpunit --filter test_split_merge_is_available_by_default_and_can_be_disabled tests/Integration/Core/AppFeatureFlagTest.php`

---

## Task 3: Board-scoped `/mod/u/{id}` + warn scoping + admin-only notes + warn idempotency (spec §2)

**Files:**
- Modify: `src/Service/UserModerationService.php`, `src/Controller/UserModerationController.php`, `templates/mod/user.php`, `src/Core/App.php` (binding `:1234-1248`)
- Create: `tests/Integration/Core/AppModUserPanelScopeTest.php`
- Modify: `tests/Integration/Core/AppModUserPanelTest.php`, `tests/Integration/Core/AppUserModerationTest.php`
- Reference (green): `tests/Integration/Admin/AdminUserBulkTest.php`, `tests/Integration/Admin/AppAdminUserRecordTest.php`

**Context (verified):** `requireStaff()` (`UserModerationController.php:126-136`) and `assertStaff()` (`UserModerationService.php:411-422`) both admit "moderates ≥1 board anywhere"; `history()` (`:370-407`) returns all warnings (with `board_id` selected but unfiltered and unrendered), **all private notes**, all bans, and the full audit trail to any such moderator. `warn()` (`:60-73`) inserts a request-supplied `board_id` unvalidated and omits it from the audit row. `addNote()` writes no audit row. The warn form has no board select — only a hidden echo (`templates/mod/user.php:99-101`). No participation predicate exists; the nearest board-IN pattern is `ThreadRepository/PostRepository::listPending`. `warnings`/`user_notes` have **no repository classes** — this service's established file-local pattern is inline SQL, and this task follows it (creating repositories for them is out of scope). `IdempotencyRepository` is bound unconditionally (`App.php:931`) and always injected as a nullable last ctor arg elsewhere.

**Instructions:**

*Red tests first:*

- [ ] Moderator of board A, subject who participated **only** in board B: `GET /mod/u/{subject}` → **404**; `POST /mod/u/{subject}/warn` → **404** (today: 200 / insert).
- [ ] In-scope moderator (subject authored in their board, including a soft-deleted or pending post — assert the deleted-content case explicitly): GET → **200**; page shows a `board_id` select naming the overlap board; page does **not** contain: the "Private staff notes" heading, ban rows, audit-trail rows, a site-wide warning (seeded with `board_id NULL`), a warning seeded on an out-of-scope board, or the subject's email string.
- [ ] Mod `POST /mod/u/{id}/note` → **403**; admin note flow unchanged (regression).
- [ ] Mod warns with `board_id` = out-of-scope board and with a nonexistent id → **identical 422** ("Choose a board you moderate where this member has participated."); with in-overlap `board_id` → redirect, exactly one `warnings` row carrying that `board_id`, and the `moderation_log` row's `after_json` contains it.
- [ ] Admin warns with no board (site-wide, `board_id NULL`) and with a named real board → both succeed; admin warn with a nonexistent board id → 422.
- [ ] Duplicate warn: two POSTs with the same `idempotency_key` → exactly **one** `warnings` row, one audit row; second POST is a normal success redirect to `/mod/u/{id}`.
- [ ] Warn 422 re-render preserves the typed reason, the selected board, **and the same `idempotency_key`** in the re-rendered form.
- [ ] Admin panel regression: full history (warnings incl. site-wide, notes, bans, audit) still rendered; `/admin/users/{id}` link still admin-only.

*Implementation:*

- [ ] Participation predicate — per the file-local raw-SQL pattern, two queries composed in the service (deliberately **without** `is_deleted`/`is_pending` filters; comment the accountability rule and the §2 anonymous-authorship decision): `SELECT DISTINCT board_id FROM threads WHERE user_id = ? AND board_id IN (<inlined ids>)` and `SELECT DISTINCT t.board_id FROM posts p JOIN threads t ON t.id = p.thread_id WHERE p.user_id = ? AND t.board_id IN (<inlined ids>)`; union → participation boards.
- [ ] `UserModerationService::panelFor(User $actor, int $subjectId): array` (new): subject via `requireSubject` [404]. Admin → `{scope:'admin', subject, history: $this->history($subjectId), warn_board_options: all boards}`. Non-admin staff → overlap = actor's `boardsFor()` ∩ participation; **empty ⇒ `NotFoundException('User not found.')`** (byte-identical to the missing-subject 404); model = `{scope:'moderator', subject: <whitelisted keys only — id, username, display_name, role, status, suspended_until, created_at, last_seen_at, post_count, reputation; no email>, overlap_boards (id/name/slug via BoardRepository), warnings: scoped query `WHERE user_id = ? AND board_id IN (<overlap>)` + board-name map}`. No notes/bans/log keys exist in the moderator model at all ("not queried or rendered").
- [ ] `warn()` → `warn(User $actor, int $subjectId, string $reason, ?int $boardId = null, ?string $idempotencyKey = null): void`: `assertStaff` → `requireReason` → `requireSubject`; **non-admin:** `boardId` required and revalidated server-side as (assigned via `boardMods->isModerator`) AND (subject participated in that board) — any miss ⇒ the one uniform `ValidationException`; **admin:** `boardId` optional, must exist when given. Idempotency (composer pattern, `PostingService.php:112-162` shape): pre-txn `hash` + `findWithContext` match on context `mod_warn` ⇒ silent return; inside the existing transaction switch the insert to `$this->db->insert(...)` to capture the warning id, then `record($actor->id(), $key, 'mod_warn', 'warning', $warningId)` — `false` ⇒ `throw DuplicateSubmissionException` (rolls back). Audit gains `'after' => ['board_id' => $boardId]`.
- [ ] `addNote()`: `assertStaff` → `assertAdmin` (mods get 403; the admin record's note form is unaffected).
- [ ] Controller: `show()`/`panel()` render from `panelFor()` (both the 200 and the 422 re-render paths — the scoped actor must never see the full model even on error); `warn()` passes `board_id` (`int('board_id',0) ?: null` stays) + `$request->str('idempotency_key')`, and its `old` adds both; `run()` gains `catch (DuplicateSubmissionException) { return $this->redirectWithFlash('/mod/u/'.$subjectId, $okMessage); }` (the spec's replay-the-original-outcome).
- [ ] `templates/mod/user.php`: warn form gets a real `<select name="board_id">` — moderator: overlap boards only, required; admin: `"Site-wide (no board)"` empty option + full list; selected = `$oldv('warn','board_id')`. Hidden `idempotency_key` (old value on re-render, else fresh `bin2hex(random_bytes(16))`). Warnings history renders the board name (or "Site-wide"). Wrap the note form + notes/bans/audit sections in the admin conditional; the moderator variant renders only identity summary + overlap + scoped warnings + warn form.
- [ ] `App.php:1234`: `UserModerationService` binding gains `BoardRepository` and `IdempotencyRepository` (nullable last params, wired unconditionally, matching the house seam).
- [ ] Confirm `AdminUserController::bulkApply` still compiles against the new `warn()` signature (it passes neither board nor key — both default null; idempotency correctly inert for bulk).

**Acceptance criteria:**

- [ ] All new tests green; `AppModUserPanelTest` / `AppUserModerationTest` / `AdminUserBulkTest` / `AppAdminUserRecordTest` green (note `AppModUserPanelTest::test_board_moderator_can_open_the_panel` seeds no participation — update its fixture to make the subject participate in the mod's board, which is now the admission condition; that edit is a scoped-behavior update, not assertion-weakening — say so in its diff comment).
- [ ] A board moderator can complete the ADMIN §5.1 workflow (open in-scope participant, warn with board attribution) and nothing else.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppModUserPanelScopeTest.php tests/Integration/Core/AppModUserPanelTest.php tests/Integration/Core/AppUserModerationTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Admin/AdminUserBulkTest.php tests/Integration/Admin/AppAdminUserRecordTest.php`

---

## Task 4: Recent-IP samples ordered by recency (spec §6)

**Files:**
- Modify: `src/Service/UserModerationService.php` (`revealPii`, `:238-257`)
- Modify: `tests/Integration/Admin/AppAdminUserRecordTest.php` (or a focused service test beside it)

**Instructions:**

- [ ] Red test: seed the subject with two sessions and two posts where packed-byte order inverts recency (e.g. older activity from `10.0.0.9`, newer from `10.0.0.10` — `inet_pton` orders `.9 < .10`, `ORDER BY ip` would surface the old one first); assert `revealPii()['session_ips'][0]` and `['post_ips'][0]` are the **most recent** addresses. Sessions: `SessionRepository::create` + raw `UPDATE sessions SET ip = ?, last_seen_at = ?`; posts: create via `makeThread`/reply with `ip` input + raw `UPDATE posts SET created_at = ?`.
- [ ] Replace the two queries: `SELECT ip FROM sessions WHERE user_id = ? AND ip IS NOT NULL GROUP BY ip ORDER BY MAX(last_seen_at) DESC LIMIT 5` (`sessions.last_seen_at` exists — migration `0005_sessions.php:16`) and `SELECT ip FROM posts WHERE user_id = ? AND ip IS NOT NULL GROUP BY ip ORDER BY MAX(created_at) DESC LIMIT 5`. Still five distinct addresses; reveal stays admin-only + audited (no change to the `view_pii` row).

**Acceptance criteria / Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php` — recency test green, existing PII-reveal audit tests untouched and green.

---

## Task 5: Dashboard + structure draft preservation, overlay wins (spec §5)

**Files:**
- Modify: `src/Service/AdminDashboardService.php`, `src/Controller/AdminController.php` (`dashboardView` `:539-561`, `structureView` `:524-530`, `reorder` catch `:434-438`), `src/Core/App.php` (`AdminDashboardService` binding `:1919`)
- Modify: `tests/Integration/Core/AppAdminModerationTest.php` (+ new focused cases)

**Context (verified):** `dashboardView` merges `[defaults incl. 'settings_errors'=>[], 'settings_old'=>[]] + $extra` — the left operand wins in PHP array-union, so both 422 paths (`:87-96` site name, `:104-111` settings) silently drop the payload. `structureView` has the identical shape, currently saved only by key disjointness; `reorder()`'s catch duplicates the assembly inline.

**Instructions:**

- [ ] Red tests: `POST /admin/site` with an 81-char name → **422**, response contains the validation message **and** the typed name in the input; `POST /admin/settings` with an invalid `antiabuse_mode` → **422** with message + typed values echoed. (Today both render the pristine dashboard at 422.)
- [ ] Move the full dashboard model assembly (settings, feature-gated custom emoji, mailer/domain state, mode lists — everything `dashboardView` builds at `:544-560`) into `AdminDashboardService::dashboardModel(array $overlay = []): array`, composing its existing `summary()` and applying the overlay with **replacement** semantics (`array_replace($base, $overlay)`); extend the service ctor/binding with the repositories the controller was using (`SettingRepository`, the custom-emoji source, `FeatureFlags` it already has).
- [ ] `dashboardView()` becomes: render `admin/dashboard` with `dashboardModel($extra)` at `$status`.
- [ ] `structureView()`: build `$base` then `array_replace($base, $extra)`; convert `reorder()`'s inline catch to `structureView(['reorder_error' => …], 422)`.

**Acceptance criteria:**

- [ ] Both 422 paths preserve error + typed input; every existing `structureView` caller's 422 context (`create_category_error`, `create_board_errors`, `update_category_*`, `reorder_error`) still renders (regression: `AppAdminTest`, `AppAdminStructureReorderTest`).

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Core/AppAdminTest.php tests/Integration/Core/AppAdminStructureReorderTest.php`

---

## Task 6: Authoritative, transactional board delete (spec §3)

**Files:**
- Create: `src/Service/BoardRecountService.php`
- Modify: `src/Service/AdminService.php` (`deleteBoard` `:313-369`, new preview method), `src/Repository/BoardRepository.php`, `src/Controller/AdminController.php` (`confirmBoardView` `:603-675`, `deleteBoard` `:366-383`), `templates/admin/structure_confirm.php`, `src/Core/App.php`
- Modify: `tests/Integration/Admin/AdminBoardSettingsTest.php`, `tests/Integration/Core/AppAdminTest.php`
- Create: `tests/Integration/Admin/AdminBoardDeleteConcurrencyTest.php`

**Context (verified):** preview reads denormalized `boards.thread_count` (`AdminController.php:606`); all validation runs before the transaction (`AdminService.php:315-336`); the gate is raw `hasThreads()` (any row, incl. soft-deleted) so a hidden-content board previews as empty then 422s on POST — the dead end; nothing locks board rows (`BoardRepository` has zero `FOR UPDATE`; the idiom to copy is `ThreadRepository.php:68`); the success flash branches on the stale denormalized count; `Database::transaction` nests inline (no savepoints); the post-commit `threadIntelligenceBoardSweep?->markVisibilityChanged($destId)` hook lives at `:364-368`. Dependent rows ride the verified FK graph (CASCADE: `board_moderators`, `board_members`, `user_board_prefs`, `board_folder_boards`, `board_slug_history`; SET NULL: `reputation_events`, `badge_rules`, `invitations.onboarding_board_id`) — add **no** per-table cleanup.

**Instructions:**

*Red tests first:*

- [ ] Board whose only thread is soft-deleted: `GET /admin/boards/{id}/delete` shows count **1** with the "(including hidden, held, and deleted)" label and offers a destination (today: shows 0/deletable); POST without destination → 422; POST with destination → success, thread row moved, and the destination's `thread_count`/`post_count`/last-post **exclude** it (recount predicates) — assert both the row move and the counter values.
- [ ] Same for a pending-thread board.
- [ ] Success flash reports the actual moved count from the service return.
- [ ] Concurrency (`AdminBoardDeleteConcurrencyTest`, modeled on `ThreadIntelligenceConcurrencyTest`: `#[Group('nonparallel')]`, committed fixtures + truncate-teardown, `new Database($GLOBALS['__RB_TEST_DBCONFIG'])` second connection): connection B commits `is_archived=1` on the chosen destination **after** the preview would have offered it; connection A's `deleteBoard` then fails with the archived-destination `ValidationException`, and source board, threads, destination, counters, and audit log are all unchanged. (Deterministic — no lock-wait choreography. The FK-insert-blocking variant is optional; if attempted, use the `proc_open` child pattern from `ThreadIntelligenceRepositoryTest` and mind Task 1's 1020 learnings.)

*Implementation:*

- [ ] `BoardRepository`: add `findForUpdate(int): ?array` (`SELECT * FROM boards WHERE id = ? FOR UPDATE`) and `countThreads(int): int` (`SELECT COUNT(*) FROM threads WHERE board_id = ?` — deliberately unfiltered); **relocate** `recountContent()` out (see next); `recomputeLastPost()` stays (its other caller is `moveThread`; touching it is out of scope — record the consistency note in Task 10).
- [ ] `BoardRecountService` (new): ctor `(Database, BoardRepository)`; `recount(int $boardId): void` = the two `recountContent` UPDATEs **moved verbatim** (predicates already match `RepairService::repairBoardCounters` byte-for-byte — this is a boundary relocation, not a rewrite; keep the docblock cross-reference) + `recomputeLastPost`. Bind in container; rewire any other `recountContent` caller.
- [ ] `AdminService::deleteBoard(User, int, ?int): int` — returns the moved count. `assertAdmin` + a pre-transaction `find` for the controller-facing 404 stay; **everything else moves inside `$db->transaction`**: (1) `findForUpdate` the source and (when given) destination **in ascending id order** — the transaction's first reads are the locking reads (lock-then-read; never non-locking-read a board first — that is the Task 1 MariaDB-1020 trap); source vanished → `NotFoundException`; (2) re-validate from the *locked* rows: self-destination / missing / archived → the existing `ValidationException`s; (3) `$count = boards->countThreads($src)` under the lock (the X-lock blocks concurrent `threads` FK inserts at the parent check, and any straggler would break the later board `DELETE` and roll everything back); (4) `$count > 0 && !$dest` → the existing "still has threads" `ValidationException`; (5) move rows (`UPDATE threads SET board_id …`, capture rowCount); (6) `BoardRecountService::recount($destId)`; (7) audit `move_board_content` (with `threads_moved`) when moving, then `boards->delete($src)` + audit `delete_board`; return the count. **After commit**, the retained `threadIntelligenceBoardSweep?->markVisibilityChanged($destId)` hook, unchanged.
- [ ] `AdminService::boardDeleteImpact(int $boardId, int $selected = 0): array` (new preview): authoritative `countThreads`, destination options (`allOrdered` minus self minus archived), `blocked`, `selected`. `confirmBoardView('delete')` consumes it; `templates/admin/structure_confirm.php` labels the count "N threads (including hidden, held, and deleted)". Archive/unarchive previews are untouched.
- [ ] Controller `deleteBoard`: flash from the returned count — "Moved N thread(s) and deleted the board." / "Board deleted." (drop the stale `thread_count` branch).

**Acceptance criteria:**

- [ ] No dead end: any deletable-looking board is actually deletable exactly as previewed; preview count == rows moved, always.
- [ ] Existing delete tests (`test_delete_with_move_relocates_threads_recounts_and_deletes`, `test_delete_with_threads_and_no_destination_is_refused`, `test_confirm_page_offers_destination_for_non_empty_board`, `AppAdminTest::test_board_can_only_be_deleted_when_empty`) green, updated only where the *preview* presentation changed.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AdminBoardSettingsTest.php tests/Integration/Admin/AdminBoardDeleteConcurrencyTest.php tests/Integration/Core/AppAdminTest.php`

---

## Task 7: Read models, pagination, repository boundaries (spec §4)

Each subtask is red-test-first and independently committable. The shared pagination rule: `has_next = page_end_offset < total` — 0-based routes (audit, users, reports): `($page + 1) * $per_page < $total`; 1-based (email): `$page * $per_page < $total`. Never fetch-one-extra; every surface already has (or gains) a real total. Exact-multiple seeding trick: give the seeded rows a unique filterable marker (action prefix / username prefix / kind) and assert through that filter, so ambient fixture rows can't skew the count.

### 7a. Audit query service + `ModerationLogRepository` single-tabling + date validation

**Files:** create `src/Service/AuditQueryService.php`; modify `src/Repository/ModerationLogRepository.php`, `src/Repository/UserRepository.php`, `src/Controller/AdminController.php` (`audit` `:48-74`), `src/Service/AdminDashboardService.php`, `src/Service/UserModerationService.php` (`history` log leg), `templates/admin/audit.php`, `src/Core/App.php`; modify `tests/Integration/Admin/AdminAuditPageTest.php`.

- [ ] Red: `from=banana` → **422** with an inline "Use YYYY-MM-DD." error, the typed `banana` still in the field, and **no rows rendered** (today: 200 over `created_at >= 'banana 00:00:00'`); `to` likewise; valid inclusive `from`/`to` bounds still filter (existing test); exact-multiple: insert 50 rows with action prefix `plancheck.` via `ModerationLogRepository::log`, filter `action=plancheck.` → page 0 full, **no Next**; actor substring filter still matches by username/display name; actor with zero matches yields zero rows (not unfiltered rows); dashboard audit card and `/mod/u` audit trail still show actor usernames.
- [ ] `AuditQueryService` (ctor `ModerationLogRepository`, `UserRepository`): `page(array $raw, int $page, int $perPage = 50): array{rows, filters, total, page, per_page, has_next, base_query}` — trims/allowlists exactly the current filter set; dates must exactly round-trip `Y-m-d` (`DateTimeImmutable::createFromFormat` + re-format equality) else `ValidationException` carrying the typed values; resolves the `actor` substring via new `UserRepository::idsMatchingName(string $q, int $limit = 500): array` (LIKE over username/display_name, ids only) — empty resolution short-circuits to `rows=[], total=0`; calls the repo with `actor_ids`; enriches; computes `has_next` from `total`. Public `enrich(array $rows): array` batches actor ids through `UserRepository::contactsForIds` and reattaches `actor_username`/`actor_display_name` (template keys unchanged).
- [ ] `ModerationLogRepository`: `search`/`searchCount` drop the `users` JOIN and the `u.*` LIKE, gain `actor_ids` (inlined int list); `recent()` and `recentForTarget()` (`:145-157`) go single-table (`SELECT m.* …`). `log()` untouched.
- [ ] Consumers: `AdminController::audit` marshals → `try page() catch ValidationException` → 422 render with errors + preserved filters + empty rows; `AdminDashboardService` enriches its `recent(10)` through the service (ctor/binding update); `UserModerationService::history` enriches its log leg the same way (admin panel path — this is the `recentForTarget` blast-radius call site from the spec).

### 7b. `/admin/users` directory + bulk boundary

**Files:** modify `src/Repository/UserRepository.php`, `src/Service/UserModerationService.php`, `src/Controller/AdminUserController.php` (`directoryView` `:357-376`, `bulkConfirm`/`bulkApply` `:51-119`); modify `tests/Integration/Admin/AdminUserBulkTest.php` (+ a directory pagination case).

- [ ] Red: seed exactly 50 users with username prefix `pageruser`, filter `q=pageruser` → page 0 full, **no Next** (today `has_next` true on `count===PER_PAGE`); page 1 renders empty without error.
- [ ] `UserRepository::directoryCount(array $filters): int` reusing the existing private `directoryFilters()` builder (that shared-builder is why the count lives here).
- [ ] Move the directory model (`rows`, `total`, `has_next`, filters echo) and the bulk confirm/apply orchestration (validation, the per-member `warn`/`suspend` loop, skip accounting — `AdminUserController.php:76-119`) into `UserModerationService` methods (`directoryModel`, `bulkPlan`, `bulkApply`); the controller marshals and renders only. All existing bulk semantics (shared-input abort at zero applied, per-member skips, flash summary) are behavior-preserving — the bulk tests must pass unmodified.

### 7c. Email dashboard model + honest non-failed-requeue test

**Files:** modify `src/Service/EmailOpsService.php`, `src/Controller/AdminEmailController.php` (`index` `:33-69`); modify `tests/Integration/Admin/AppAdminEmailTest.php`, `tests/Integration/Core/AppModerationDraftLossTest.php` (`:112-119`).

- [ ] Red: exact-multiple — seed 50 deliveries of a unique kind, filter to it → **no Next** at page 1 (1-based); page 2 empty.
- [ ] `EmailOpsService::dashboardModel(?string $status, ?string $kind, ?string $email, int $page, int $perPage = 50): array` — the whole `:45-68` assembly moves in; `has_next = $page * $perPage < $total`.
- [ ] Rewrite the non-failed requeue test to the spec's honest shape: seed a **real** delivery row in a non-failed status (e.g. `sent`), POST its requeue, assert the no-op flash **and** that the row's status/attempt fields did not change (today the test requeues id `999999` — it proves only the missing-row path).

### 7d. Reports queue model + pagination

**Files:** modify `src/Service/ReportService.php`, `src/Controller/ReportController.php` (`queue` `:46-97`); modify `tests/Integration/Core/AppReportQueueTest.php`.

- [ ] Red: 50 open reports on one board, board filter → page 0 full, no Next.
- [ ] `ReportService::queueModel(User $user, array $raw, int $page): array` — scope resolution (`moderableBoardIds`, empty-scope `NotFoundException`), filter allowlisting, rows + `queueCount` total + board options + `has_next` (0-based) move in; controller thins. Board-scope behavior byte-identical (`testQueueIsBoardScoped` green unmodified).

### 7e. Appeal view models

**Files:** modify `src/Service/AppealService.php`, `src/Controller/AppealController.php` (`openPost` `:29-45`, `openModerationLog` `:48-64`, `resolve` `:77-103`); extend `tests/Integration/Core/AppModerationAppealsTest.php`.

- [ ] Red (if not already covered): invalid resolve outcome → 422 re-render of the queue with typed `outcome`/`note` preserved; invalid member appeal reason → 422 with the eligible-targets list still rendered.
- [ ] `AppealService::memberViewModel(int $userId): array{appeals, eligible}` and `queueViewModel(User $actor): array{appeals, outcomes}`; all four controller render sites (happy + 422 for both surfaces) consume them.

### 7f. Webhook detail model

**Files:** modify `src/Service/WebhookService.php`, `src/Controller/AdminWebhookController.php` (`show`/`update`/`rotate`/`delete` — four duplicated assemblies); reference `tests/Integration/Admin/AdminWebhookTest.php` (green unmodified).

- [ ] `WebhookService::detailModel(int $id): ?array{webhook, deliveries, events_catalogue}`; the four call sites consume it and overlay only `errors`/`error_context`/`new_secret`. Pure boundary move — the webhook test file is the regression harness.

### 7g. Tag service + honest merge impact

**Files:** create `src/Service/TagService.php`; modify `src/Repository/TagRepository.php`, `src/Controller/TagController.php` (`mergeConfirm` `:135-158`, `merge` `:161-176`), `templates/admin/tag_merge_confirm.php`, `src/Core/App.php`; create `tests/Integration/Core/AppTagMergeTest.php`.

- [ ] Red: source tag associated with a visible thread, a soft-deleted thread, a pending thread, and a thread on a `tags_enabled=0` board → confirm page shows **4** "tag associations" (today `countThreadsForTag` shows 1); execute merge → all 4 `thread_tags` rows point at the target (target count 4, source 0), alias row written, source disabled — preview == moved set.
- [ ] `TagRepository::countAssociationsForTag(int $tagId): int` — `SELECT COUNT(*) FROM thread_tags WHERE tag_id = ?` (single-table; matches `mergeInto`'s source set exactly; `countThreadsForTag` remains for the public listing).
- [ ] `TagService` (ctor `TagRepository`): `mergeImpact(int $sourceId, int $targetId): array` and `merge(int $sourceId, int $targetId): void` (guards + `mergeInto`; no new audit row — recording that gap is Task 10's disposition, adding audit is out of scope). Controller consumes; template labels "N tag associations (includes hidden, held, and deleted threads)".

**Verification (whole task):**

- [ ] `vendor/bin/phpunit tests/Integration/Admin/AdminAuditPageTest.php tests/Integration/Admin/AdminUserBulkTest.php tests/Integration/Admin/AppAdminEmailTest.php tests/Integration/Admin/AdminWebhookTest.php`
- [ ] `vendor/bin/phpunit tests/Integration/Core/AppReportQueueTest.php tests/Integration/Core/AppModerationAppealsTest.php tests/Integration/Core/AppTagMergeTest.php tests/Integration/Core/AppTagAdminTest.php tests/Integration/Core/AppModerationDraftLossTest.php`

---

## Task 8: Replay-safe API-token minting via `submission_idempotency` (spec §7)

**Files:**
- Modify: `src/Service/ApiTokenService.php` (`mint` `:46-96`), `src/Controller/AdminApiTokenController.php`, `templates/admin/api_tokens.php`, `src/Core/App.php` (`:863-873`)
- Modify: `tests/Integration/Api/AdminApiTokenTest.php`, `tests/Integration/Service/ApiTokenServiceTest.php`

**Context (verified):** mint renders the plaintext straight in the POST response (correct — never Flash) with an in-code "accepted minor wart" comment (`AdminApiTokenController.php:53-55`) and a template warning "refreshing … mints another token" (`templates/admin/api_tokens.php:19`). The mint transaction wraps exactly insert + audit (`ApiTokenService.php:83-93`) — the `record()` call composes atomically. `submission_idempotency.context` is free-form VARCHAR(32) — the new `api_token_mint` context needs no schema change. No existing test covers the re-mint. Unlike the composer, a duplicate is **never replayed** (plaintext isn't stored) — it is a 409, a mapping no current catch site has, so the controller must catch.

**Instructions:**

*Red tests first:*

- [ ] Service: `mint(...)` twice with the same key → second call throws `DuplicateSubmissionException`; `api_tokens` count 1; exactly one `api_token_minted` audit row. (`ApiTokenServiceTest` constructs the service without trailing optional args — the new param must default null so those tests compile.)
- [ ] HTTP: POST mint → 200 containing `rbt_`; identical re-POST (same `idempotency_key`) → **409**, body contains no `rbt_`, a conflict notice, and the token table; `COUNT(*) api_tokens` = 1; one mint audit row.
- [ ] 422 path (wrong reauth password) re-renders with the **same** key in the hidden field (regex it out of both responses and compare); a corrected resubmit with that key then succeeds once.
- [ ] Two fresh GETs render different keys.

*Implementation:*

- [ ] `ApiTokenService`: ctor appends `?IdempotencyRepository $idempotency = null`; `mint(User, string, string, array, ?int, ?string $idempotencyKey = null)`. After WriteGate + flag + reauth + field validation (so idempotency state is not probeable pre-auth): `$key = $this->idempotency?->hash($idempotencyKey)`; if `$key` and `findWithContext` matches context `api_token_mint` → `throw DuplicateSubmissionException` (no replay — by design). Inside the existing transaction, after insert + audit: `record($admin->id(), $key, 'api_token_mint', 'api_token', $id)` — `false` ⇒ `throw DuplicateSubmissionException` (rolls back token + audit). Wire the new arg at `App.php:863-873`.
- [ ] Controller: `index()` and every `mint()` render path supply the form key (`old['idempotency_key']` when re-rendering, else fresh); `mint()` passes `$request->str('idempotency_key')` and gains `catch (DuplicateSubmissionException)` → the same view with `tokens`/`scopes_catalogue`, `new_token => null`, `conflict => true`, **status 409**.
- [ ] Template: hidden `idempotency_key` in the create form (old value else `bin2hex(random_bytes(16))`, composer precedent); delete the "Do not reload this page…" paragraph (`:19`) — the `<strong>` "Copy this token now — it will not be shown again" line stays (both Playwright specs assert `/will not be shown again/`); add the conflict banner block (`if (!empty($conflict))`: "That token request was already processed. No new token was minted — the original was shown once. Start again if you still need one.").

**Acceptance criteria:**

- [ ] Refreshing the mint POST can no longer create a credential; the first response still shows the plaintext exactly once, never via Flash; flag-dark 404 and suspended-admin 403 behavior unchanged.

**Verification:**

- [ ] `vendor/bin/phpunit tests/Integration/Api/AdminApiTokenTest.php tests/Integration/Service/ApiTokenServiceTest.php`

---

## Task 9: Browser evidence green-up + new evidence journeys (spec §8)

**Files:**
- Modify (as triage dictates): product templates/CSS/JS behind the failing flows; possibly `tests/browser/gate-a.spec.ts`, `api-tokens.spec.ts`, `invitations.spec.ts`, `composer-shell.spec.ts` — selector/flow updates only where the *product* legitimately changed (e.g. Task 8's 409), never assertion weakening
- Modify: `tests/browser/admin-remediation.spec.ts` (new journeys)

**Instructions:**

- [ ] Work from Task 0's triage note. For the eleven uniform 30s admin timeouts, fix the **proven shared cause** first (per first-divergence inspection), then re-run the affected specs; treat any residue individually. Every fix is product-side or an honest selector update for intentionally-changed UI — no timeout raises, no `.skip`, no assertion weakening.
- [ ] Composer-shell: fix desktop `:576` (the real assertion failure — dialog focus/geometry/recents per the spec's own assertions) and the `:502` popover-reflow timeouts on both projects, regardless of which side of the main/PR split Task 0 assigned them — the spec puts *every* failing case in scope.
- [ ] Update flows the remediation intentionally changed: the two API-token specs gain the no-second-mint step — after the mint assertion, `page.reload()` (re-POSTs), expect the 409 conflict copy, still exactly one token row, and no `rbt_` code block.
- [ ] Add the spec-required journeys to `admin-remediation.spec.ts` (it runs in its own `prepare.sh`-isolated segment; seeded principals: `alice@retro.test` moderates `#general`, `bob@retro.test` participates there):
  - desktop + mobile: dashboard invalid site-name/settings POST → 422 with error and typed value visible (screenshots);
  - board-moderator panel: alice opens a `#general` participant → scoped board select visible, no "Private staff notes" heading, no global history; warn succeeds; an out-of-scope subject's URL → 404 page (screenshot pair);
  - board delete: confirm page shows the authoritative "(including hidden, held, and deleted)" count for a board with hidden content, then delete-with-move succeeds (screenshot);
  - API-token refresh-no-remint (screenshot of the 409 state).
- [ ] All new/updated cases assert axe-clean where the sibling cases do, and reuse the existing `shot()` naming convention.
- [ ] Full serial run: `cd tests/browser && npm run evidence` — **zero failures across all three segments**; then `npm run a11y`.
- [ ] Inspect refreshed screenshots for sanity (no tour overlays, no clipped controls) and the browser console for errors on touched routes.

**Acceptance criteria:**

- [ ] Every case in Task 0's measured failure set passes; the complete evidence workflow is green end-to-end on the implementation worktree.

**Verification:**

- [ ] `cd tests/browser && npm run evidence` (zero failures) and `npm run a11y`

---

## Task 10: Record deviations + dispositions, then run the final gates

**Files:**
- Modify: `docs/adr/0021-admin-console-remediation-and-deferrals.md`, `docs/history/admin-ux-remediation-2026-07-18.md`, `ADMIN.md` (§3.4 + §13 changelog)

**Instructions (recording — spec "Recorded deviations and follow-ups"):**

- [ ] ADR 0021: append deferral **#10** — ADMIN §4.4 board **soft-delete with reserved slugs** (and optional soft-delete-threads path) deferred; this remediation codifies hard-delete-with-forced-move, and hard delete cascades `board_slug_history` away (actively un-reserving slugs) — deferred, not dropped. Add a short "Post-review decisions (2026-07-18, PR #44 review)" note: **notes are admin-only**, narrowing §3.4's "Add mod note → `mod.user.warn`" mapping, because globally-scoped `user_notes` under any-board-mod read was strictly worse; and the **follow-up**: the reports queue's pre-existing unaudited de-anonymization (`ReportRepository.php:77,85,102,107` selects raw authors; `templates/mod/reports.php:91-113` renders `@username` + "Warn author…", bypassing the audited `/mod/p/{id}/reveal`) — logged for its own fix, not expanded here.
- [ ] Disposition doc: new section "PR #44 review remediation — 2026-07-18": finding → disposition table for all seven findings and each named boundary item (task numbers + test names as evidence pointers); Task 0's measured browser-failure split and final green run; explicit no-behavior dispositions for the pure-consistency comments (redirect-helper style notes; `recomputeLastPost` remaining in `BoardRepository`; tag merge still unaudited) and a line noting the redundant panel staff check fell out of the `panelFor` refactor.
- [ ] `ADMIN.md`: one-line annotation at §3.4 (notes admin-only, cite ADR 0021) + a §13 changelog entry for both deviations.

**Final gates (spec, verbatim — all in the implementation worktree):**

- [ ] 1. Focused PHPUnit files/methods from every task re-run green.
- [ ] 2. `php -l` over every changed PHP file.
- [ ] 3. `php bin/console migrate:status` unchanged — this plan ships **no** migration — and `SCHEMA.md` untouched (`git diff --stat` proves both).
- [ ] 4. `composer test` — zero failures, errors, warnings, risky tests.
- [ ] 5. Focused Playwright desktop/mobile checks for the repaired journeys.
- [ ] 6. Complete `cd tests/browser && npm run evidence` — zero failures.
- [ ] 7. Screenshot + axe + browser-console inspection (no serious/critical findings, no console errors).
- [ ] 8. `DB_DATABASE=retroboards_e2e php bin/console repair`, then direct SQL parity checks of `boards.thread_count`/`post_count`/`threads.reply_count`/`users.post_count` against authoritative rows (the command's constant "1 rows" output is uninformative — compare values, don't trust the log).
- [ ] 9. `git diff --check`; audit changed/untracked files (no stray fixtures, no `.artifacts` additions staged).
- [ ] 10. In the **original** checkout: `git status docs/evidence/browser` — the pre-existing PNG modifications are byte-identical to before this work began.

- [ ] Only after every gate is green is `pr44-remediation` eligible to be pushed — **stop and ask the user**; pushing, PR updates, review-thread resolution, and merge all require explicit instruction (spec).

---

## Finding-to-task map

- [ ] Finding 1 — moderation re-render discloses private/pending threads: **Task 2**.
- [ ] Finding 2 — unscoped `/mod/u/{id}` panel/writes: **Task 3**.
- [ ] Finding 3 — dashboard 422 drops errors/typed input: **Task 5**.
- [ ] Finding 4 — board-delete preview vs. delete mismatch + out-of-transaction validation: **Task 6**.
- [ ] Finding 5 — pagination (`/admin/audit`, `/admin/email`, `/mod/reports`, `/admin/users`): **Tasks 7a–7d**; tag-merge impact: **7g**; audit dates: **7a**; IP ordering: **Task 4**; duplicate submissions: **Tasks 3 + 8**; boundary moves: **Tasks 2, 3, 5, 7a–7g**.
- [ ] Finding 6 — token re-mint on refresh: **Task 8**.
- [ ] Finding 7 — red gates (browser + TI): **Tasks 0, 1, 9**; final proof: **Task 10**.
- [ ] Spec deviations + follow-up recording: **Task 10**.
