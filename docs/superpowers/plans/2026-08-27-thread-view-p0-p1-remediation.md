# Thread View P0/P1 Remediation Implementation Plan

> **Execution:** Use `superpowers:executing-plans` task-by-task. This session executes inline because agent delegation is disabled. Do not commit unless separately authorized.

**Goal:** Resolve every verified P0 and P1 Thread View finding while preserving stable permalinks, locked cursor storage, progressive enhancement, generated-asset ownership, and the user's existing worktree edits.

**Architecture:** Keep `thread_user.last_read_post_id` as cursor identity and centralize tuple-order SQL in repositories/services. Separate ordinary page-one navigation from explicit unread intent. Carry failed-write render context explicitly. Correct presentation in source-owned CSS/templates, rebuild generated assets, and make browser evidence self-cleaning and project-isolated.

**Tech stack:** PHP 8.2+, MySQL/MariaDB, PHPUnit, server-rendered PHP templates, vanilla JavaScript/CSS, Imladris asset builder, Playwright.

## Global constraints

- Approved design: `docs/superpowers/specs/2026-08-27-thread-view-p0-p1-remediation-design.md`.
- Preserve the pre-existing edits in `src/Controller/ThreadController.php`, `templates/thread.php`, and `tests/Integration/Core/AppAutomatedContextTest.php`; reconcile them rather than discarding them.
- Add migration `0082`; do not add a read timestamp or per-post receipt table.
- Use a unique private PHPUnit database and a separate unique browser database. Drop only those exact throwaway databases after verification.
- Modify `docs/design-system/imladris/` sources, then run `composer build:imladris`; never hand-edit generated Imladris outputs.
- Leave `config/imladris-runtime-baseline.json` byte-for-byte unchanged.
- Tests precede production edits and must be observed failing for the intended reason.
- No commits, pushes, merges, deployments, or runtime-baseline refreshes.

## Task 1: Lock permalink, unread, flag, and failed-reply contracts

**Files:**

- Modify: `tests/Integration/Core/AppAutomatedContextTest.php`
- Modify/Create focused tests under `tests/Integration/Core/` for permalink and failed-reply behavior
- Modify: `src/Controller/ThreadController.php`
- Modify: `src/Controller/Controller.php`
- Modify: `src/Controller/PostController.php`
- Modify reader resume-link templates/partials found by `rg '?page|#p|last_read|unread' templates src`
- Modify: `templates/thread.php` and the post partial used for the first-unread boundary

- [ ] Add failing tests for page-less page 1, real fragment targets, explicit `?unread=1`, caught-up readers, and all four flag combinations.
- [ ] Add a failing 422 reply test proving typed body/page survive and `last_read_post_id` does not advance.
- [ ] Run only those methods and record the intended failures.
- [ ] Implement precedence: validation page → explicit `page` → explicit `unread` redirect → page 1.
- [ ] Advance read state only on successful GET and render a first-unread marker independently of automated-context richness.
- [ ] Convert reader resume links to explicit unread intent while retaining canonical page-one permalinks.
- [ ] Re-run the focused tests green.

## Task 2: Make cursor comparisons chronological and indexed

**Files:**

- Modify: `tests/Integration/Core/AppAutomatedContextTest.php`
- Modify/add repository/service integration tests for unread lists, mark-read monotonicity, context, and repair
- Modify: `src/Repository/PostRepository.php`
- Modify: `src/Repository/ThreadUserRepository.php`
- Modify: `src/Service/SinceLastReadContextService.php`
- Modify: `src/Service/RepairService.php`
- Add: `database/migrations/0082_posts_read_order_index.php`
- Modify: `SCHEMA.md`

- [ ] Add skewed fixtures where chronological order differs from numeric ID order; assert literal expected post/page/unread values.
- [ ] Add a failing query-count/dedicated-location test for first-unread resolution.
- [ ] Add failing repair coverage proving `last_post_*` follows `(created_at,id)`, not `MAX(id)`.
- [ ] Run focused tests red.
- [ ] Implement one dedicated indexed first-unread-location query and tuple-consistent unread predicates.
- [ ] Make `markRead()` validate ownership/visibility and advance monotonically by tuple.
- [ ] Make context range and repair endpoint tuple-correct.
- [ ] Add the composite index migration and update SCHEMA shape/version/changelog.
- [ ] Re-run focused tests and migration rehearsal green.

## Task 3: Repair split/merge cursors and preserve moderation 422 state

**Files:**

- Modify/add split/merge integration tests
- Modify: `src/Service/ThreadSplitMergeService.php`
- Modify: `src/Controller/ModerationController.php`
- Modify: `templates/partials/thread_restructure.php`
- Modify: `src/Controller/ThreadController.php` only where render-page precedence is shared

- [ ] Add failing tests for split cursor rebasing, merge timestamp/ID skew, source cursor cleanup, and 422 page/selection preservation.
- [ ] Run focused tests red.
- [ ] Rebase/clear invalid cursors inside the existing split/merge transaction.
- [ ] Carry render page and selected IDs into the validation re-render; include current page in the form/action context.
- [ ] Re-run focused tests green.

## Task 4: Close the Living Brief history evidence gap

**Files:**

- Modify: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

- [ ] Add a regression using the real publish/amend service path and assert descending versions plus restore forms.
- [ ] Run it green against the existing implementation; if it fails, diagnose before changing production.

## Task 5: Correct Thread View presentation and selector ownership

**Files:**

- Modify/add focused markup/runtime tests (`AppImladrisFidelityTest`, `AppPollTest`, or a dedicated Thread View class)
- Modify: `templates/thread.php`
- Modify: `docs/design-system/imladris/components.css`
- Modify: `public/assets/app.css`
- Generated by build: `resources/imladris/*`, `public/assets/imladris.css`, asset manifests

- [ ] Add failing assertions for no `<meter>`, semantic poll bar text/SVG, non-clipping operational facts, and invalid form state.
- [ ] Add browser assertions for desktop no-wrap/mobile wrap and cascade-visible danger frame.
- [ ] Run PHP/browser-focused checks red.
- [ ] Replace `<meter>` with CSP-safe SVG progress presentation and visible text.
- [ ] Separate ellipsized identity prose from assignment/snooze metadata; fix desktop/mobile wrapping.
- [ ] Reconcile field spacing and scope hint/link-preview selectors in design-system source; add the app-level invalid override.
- [ ] Run `composer build:imladris` and source/generated asset checks.
- [ ] Re-run focused tests green.

## Task 6: Retire the stale design-system bundle

**Files:**

- Modify: `tests/Integration/Core/ImladrisRuntimeAssetTest.php` (or its actual path)
- Delete: `docs/design-system/imladris/_ds_bundle.js`
- Modify: `src/Support/ImladrisAssetBuilder.php`
- Modify relevant Imladris README/SKILL/preview loader documentation and validation

- [ ] Add a failing runtime-asset test proving the stale generated bundle is absent and previews cannot silently claim it as current.
- [ ] Run the test red while the file exists.
- [ ] Remove the tracked bundle, remove its excluded-manifest special case, and update preview/documentation ownership consistently.
- [ ] Rebuild/check Imladris assets and re-run the test green.

## Task 7: Make browser evidence idempotent and keyboard-real

**Files:**

- Modify: `tests/browser/thread-view-study.spec.ts`
- Modify: `tests/browser/package.json`
- Modify browser preparation/helpers only where needed for per-project isolation
- Refresh: `docs/evidence/browser/desktop/80-thread-study.png`, `81-thread-tools.png`, and mobile equivalents
- Modify evidence README if commands/ownership change

- [ ] Add a fixture-state snapshot/assertion around repeated runs; observe the current leak.
- [ ] Wrap status/star/reaction/no-JS mutations in exact cleanup, including failure paths.
- [ ] Prepare the database separately for desktop and mobile evidence projects.
- [ ] Replace direct focus injection with real Tab traversal and assert two-layer Escape/focus restoration.
- [ ] Assert every fragment destination exists after navigation.
- [ ] Run the focused spec twice without reset and assert no state delta.
- [ ] Run independent desktop/mobile captures and visually inspect regenerated evidence.

## Task 8: Full verification and handoff

- [ ] Lint every changed PHP file and run JavaScript/TypeScript syntax checks.
- [ ] Run focused PHPUnit classes, then `php vendor/bin/phpunit` on the private test database.
- [ ] Run `php bin/console verify:upgrade` against a private scratch database.
- [ ] Run `composer build:imladris`, `composer check:imladris`, and authoritative Imladris verification without retaining any baseline change.
- [ ] Run focused desktop/mobile Playwright, no-JavaScript, keyboard, a11y, and repeated-run contracts on the private browser database.
- [ ] Inspect the target flow in the connected browser at desktop and mobile widths and display the final screenshots.
- [ ] Run `git diff --check`, confirm runtime baseline is unchanged, and audit status against the three pre-existing modified files.
- [ ] Remove only the exact throwaway databases/directories created for this work and report their recoverability.

## Completion condition

Every verified P0/P1 contract is green with current evidence. Any remaining red must be separated as an existing baseline failure with direct proof; no finding may be silently deferred.
