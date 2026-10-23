# Browser CI Queue Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Complete exact-commit hosted browser evidence without a Cloud Build controller holding a build slot while waiting for another build.

**Architecture:** Make the expensive full matrix an explicit operator run. A resumable local coordinator submits the restricted test build and, only after its terminal result, submits the existing isolated verifier. Disable the automatic browser webhook and its trigger; production deployment remains automatic. The verifier needs read-only build metadata and artifact access, not build creation or permission to impersonate the test account.

**Tech Stack:** Python standard library, gcloud, Cloud Build, existing private GCS evidence buckets.

**Spec:** `docs/runbooks/deployment-cloud-run.md` §5a and the owner-approved next-step plan in the 2026-10-23 release conversation.

## Global Constraints

- GitHub Actions is permanently unavailable; local release gates remain required.
- Source and reviewed control commits are full lowercase 40-character SHAs.
- Branch code runs only under the restricted test identity; the verifier never executes or extracts branch artifacts.
- Production triggers, credentials, database and application settings remain outside browser CI.
- Preserve stash `523fc269` and the two original untracked review documents.

## Review Focus

- A queued test build must never cause a verifier build to occupy capacity.
- Concurrent or resumed operator commands must reuse the same in-flight test run where possible and preserve recorded identities.
- A provider failure or allowed failed step must remain a failed verification even if uploaded artifacts claim success.
- Malformed input, mismatched child identity and extra substitutions must fail before trusting artifacts.
- Configuration must disable only the browser hook/trigger, never the production hook; private callback URLs must never appear in output.

### Task 1: Separate test execution from verification

**Files:** `deploy/cloudrun/browser-ci/launcher.py`, `cloudbuild.json`; `tests/Unit/Core/cloud_run_browser_ci_launcher_contracts.py`, `CloudRunBrowserCiLauncherContractTest.php`.

- [x] Add failing tests for a verifier given an active child: one metadata read, no child creation, no waiting, no artifact trust.
- [x] Require `--child-build-id`; verify a terminal child directly, preserving exact metadata, provider-step, bounded artifact and receipt checks.
- [x] Reduce verifier timeout to ten minutes and run focused boundary contracts.

### Task 2: Add a resumable explicit coordinator and retire automatic full runs

**Files:** `deploy/cloudrun/browser-ci/submit.py`, `configure.py`; coordinator tests; deployment runbook.

- [x] Pin submission recipes to the requested reviewed control commit; serialize local coordinators, reuse matching in-flight/successful builds, persist child/verifier IDs in a private state file.
- [x] Wait outside Cloud Build; submit verification only after the child is terminal. Resume failures without silently accepting another source/control pair.
- [x] Disable the existing browser trigger and matching GitHub webhook, preserve the production hook, and remove unused verifier build-create/act-as privileges.
- [x] Test ordering, resumption, duplicate selection, malformed identities, failed child and private configuration output.
- [x] Document explicit launch/resume commands and the distinction between test completion and trusted verification.

### Task 3: Publish and prove

- [ ] Run local focused contracts, required integrated PHPUnit/assets/browser gates and independent review.
- [ ] Publish reviewed controls, apply the narrowly scoped configuration and verify browser automation is disabled while production remains enabled.
- [ ] Run one complete hosted matrix against the final integrated candidate; inspect the trusted receipt and exact identities.
- [ ] Publish final changes, verify both production lanes and live assets, then reconcile deployed asset retention if needed.

## Execution ledger

- 2026-10-23: reused the isolated PR91 checkout on `codex/browser-ci-queue-20261023`; original main and local records untouched.
- Ruling: use explicit exact-commit hosted runs, rather than another event-driven cloud controller. This follows §5a release policy and removes automatic duplicate full matrices; operators launch deliberate regression coverage.
- The active-child regression failed with `child-wait-timeout` before the fix and passes with immediate `child-not-complete` rejection after it.
- Independent review identified premature success persistence and expired successful-build reuse. Success is now stored only after the trusted receipt passes; `--fresh` replaces completed runs while reusing active work.
- Focused PHP boundary suite passes; it invokes verifier, coordinator and configuration Python behavioral contracts as well as artifact enforcement tests.
- Live configuration, full integrated qualification and hosted/production evidence remain pending.
