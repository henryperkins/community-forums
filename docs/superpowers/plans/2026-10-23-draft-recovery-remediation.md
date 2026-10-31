# Complete composer draft recovery implementation plan

This is the historical October 24–25 local implementation plan. Its original
authorization and completion record are retained below. Release integration was
subsequently authorized on October 31 and is recorded in [ADR 0052](../../adr/0052-complete-composer-draft-recovery.md)
and the [release evidence](../../evidence/draft-mobile-release-2026-10-31/README.md).

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task by task. The owner explicitly authorized all three audited fixes, regression coverage and local Chromium/WebKit verification. No commit, push or deployment is authorized.

**Goal:** Resolve F-01, F-02 and F-03 without losing existing drafts or weakening the submission-receipt protections.

**Architecture:** Retain the existing plain-body localStorage keys for older tabs and add a versioned allowed-field snapshot alongside them. Share that state contract with server metadata and safe server-rendered Resume. Compare the complete permitted state before replacing a draft; retain the existing three recovery choices.

**Tech stack:** Existing vanilla JavaScript/PHP, MariaDB, PHPUnit and installed Playwright Chromium/WebKit. No new dependencies or migrations.

**Spec:** COMPOSER.md §§8, 13, 15; USER.md composing preferences; ADRs 0010 and 0050; the [original audit summary](../../evidence/draft-recovery-2026-10-23/README.md#original-audit). The user's subsequent fix request authorizes this existing concrete scope.

## Global constraints

- Preserve unrelated work and the original audit history; use isolated throwaway test databases.
- Preserve exact body/title text, Unicode, valid recipient formats and explicit false/empty values.
- Restore only fields present and enabled in the actual form; preserve board and DM feature/authorization gates.
- Keep server-rendered HTML/forms usable without JavaScript, strict CSP and existing Imladris tokens.
- No new viewport, native keyboard, custom gesture or Back-handling code.
- Run the final full PHP suite once, generated-asset/Worker checks for changed inputs, and focused browser evidence in both requested engines. Do not deploy.

## Review focus

- Older raw-body drafts and already-open older tabs remain readable; malformed snapshots never become posted JSON.
- Title-only, anonymity-only, board changes and optional group-title/recipient fields survive without leaking disabled values.
- Async hydration, overlapping saves and recovery choices use current edits and cannot silently overwrite a newer copy.
- Confirmed submission cleanup preserves newer same-body/different-field drafts in another tab.
- Resume checks ownership/context/feature flags and uses canonical local destinations; missing or inaccessible targets do not silently retarget a draft.

### Task 1: Pin the failures and preserve the starting state

- [x] Record source revision, dirty files and a verified recovery archive before application edits.
- [x] Add kernel regressions for safe owned Resume, full-state metadata, ownership/feature gates and submission cleanup.
- [x] Add browser regressions for full/legacy/local-only restoration, title-only saves, complete conflict differences and all three choices, and local/server/no-JS Resume.
- [x] Run the new focused tests against the original source and retain their meaningful failures.

### Task 2: Implement the shared recovery contract

**Files:** `public/assets/composer.js`, `public/assets/app.css`, new `src/Support/ComposerDraft.php`, base/compose/thread/conversation/draft controllers, `ServerDraftRepository`, and `templates/account/drafts.php`.

- [x] Implement versioned allowed-field snapshots without changing existing plain-body storage semantics. Include title, board, anonymity and enabled DM wrapper fields; exclude credentials and disabled fields.
- [x] Reconcile complete snapshots, serialize/coalesce draft saves, and preserve newer local edits during hydration, confirmation and cross-tab recovery.
- [x] Render named local/server copies and identifiable changed fields; use current edits for every recovery choice and preserve both copies until a choice.
- [x] Generate safe local/server Resume destinations and prefill owned server drafts through the ordinary GET composers, including no-JS and existing read gates.
- [x] Pin empty/title-only and same-body/newer-field cleanup behavior without changing retention, quota or permission policy.
- [x] Run the focused regressions to green and inspect the rendered comparison at narrow and landscape widths in both engines.

### Task 3: Qualify the final candidate

- [x] Update the composer contract/runbook and record the decision/evidence without modifying the earlier audit.
- [x] Build fingerprinted assets; run their consistency/Worker checks.
- [x] Run `composer test` once for the final candidate using a private test database.
- [x] Run the final focused Chromium/WebKit regressions, relevant existing submission/legacy-draft cases, and comparison accessibility checks; retain logs, screenshots and exact counts.
- [x] Obtain a focused code review, resolve confirmed issues, review the final diff and original-file hashes, and clean up only owned test resources.
- [x] Report local implementation/proxy verification and physical-device limits separately. Leave Git publication and deployment untouched.

## Completion record

All authorized local tasks are complete. Final evidence: `docs/evidence/draft-recovery-2026-10-23/README.md`. The final 112-case browser matrix completed with 108 passes and four explicit WebKit harness skips; all 32 new cases per engine passed. The final PHP suite passed 3,371 tests and 29,834 assertions with one existing dedicated-migration skip. Two additional rich retry-key assertions passed. Physical-device checks remain NOT READY and the verification ceiling remains proxy, separately recorded. No commit, push or deployment occurred. Only owned test resources were removed; unrelated starting hashes and the recovery archive were retained.
