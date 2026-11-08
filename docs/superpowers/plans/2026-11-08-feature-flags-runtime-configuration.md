# Feature flags and runtime configuration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the anti-abuse settings page and audit describe the enforced mode, preserve configuration changes with their audit records, and make manual flag procedures safe alongside console writes.

**Architecture:** Reuse `AntiAbuseService::mode()` for effective moderation posture. Keep settings persistence in `SettingRepository`, transaction ownership in services, and feature interpretation in `FeatureFlags`; use the existing row-lock API for documented manual edits.

**Tech Stack:** Vanilla PHP 8.2+, PDO MySQL/MariaDB, PHPUnit, server-rendered PHP forms, Playwright.

**Spec:** `DECISIONS.md` §2; `PRODUCT_DESIGN.md` §13; `ADMIN.md` §3.8 and §9.3; `AGENTS.md` write-path and release rules; `docs/adr/0049-guarded-feature-flag-toggles.md`; `docs/runbooks/operations.md` §2; `docs/runbooks/package_themes.md` §Safe Mode.

**Status:** Implementation in progress; Tasks 1–3 verified, Task 4 pending.

**Analysis revision:** `b1d2a591b3846fda586cd66ffd2346438de8286c`, current HEAD when inspected. References below use this committed revision. Unrelated working-tree edits are outside this plan.

## Global Constraints

- "Every multi-table mutation runs inside `$db->transaction(fn)`" — `AGENTS.md:83`.
- "To add a service/repo, hand-write its `$c->bind(...)` in `buildContainer()`." — `AGENTS.md:76`.
- "No inline `<script>`/`<style>` anywhere" — `AGENTS.md:103`.
- Block-mode saves retain the existing review, draft preservation, administrator, account-state, CSRF and feature gates — `ADMIN.md:271` and `tests/Integration/Admin/AdminSafeguardsTest.php:13-81`.
- Safe-mode entry stays available independently of `package_themes`; exit still requires the current password — `docs/runbooks/package_themes.md:9-12,62-77`.
- Preserve the request-cache lifecycle and explicit feature-cache invalidation. Retain declared defaults and existing unknown-override handling.
- Application changes use one final `composer test` run and focused browser evidence for UI-visible changes, following ADR 0051. Do not add GitHub Actions dependencies or revive the retired Imladris release gates.

## Findings and evidence

### F1 The moderation form and audit use a different mode fallback from enforcement

**(A) Source:** `AntiAbuseService::mode()` takes a nonempty stored override, otherwise `antiabuse.mode`, and normalizes an invalid value to `observe` (`src/Service/AntiAbuseService.php:190-195`). `AdminSettingsService::moderationModel()` instead reads the stored value with a literal `observe` fallback (`src/Service/AdminSettingsService.php:56-64`). Its audit `before` value repeats that fallback (`src/Service/AdminSettingsService.php:156-168`). The template selects the model value unless a submitted draft supplies one (`templates/admin/moderation.php:9,31-36`).

**(B) Reproduction:** `docs/evidence/feature-flags-runtime-config-2026-11-08/analysis-mode-probe.json` records the probe time and observations. With no stored override, configured `hold` and `block` are enforced by `mode()`, but the form model returns `observe`. A stored empty string also differs; an invalid stored string is displayed verbatim while enforcement returns `observe`. A valid stored `hold` override agrees in both services.

The probe calls the committed service methods against real PDO SQLite in memory for settings SELECTs. It does not establish MySQL write behavior, HTTP behavior or browser results.

**Analysis:** A GET can show a weaker mode than the service enforces. An ordinary save using that selected value can replace an inherited `hold` or `block` with an explicit `observe`; the current audit also starts from `observe` rather than the inherited enforced mode. These save/audit effects follow from the quoted template and write method; they have not been reproduced through HTTP in this analysis.

**Proposed disposition:** Task 1. Use the existing effective-mode resolver for the initial model and audit `before` value, while preserving submitted values on validation/review pages.

### F2 Safe mode changes and their audit insert have no local transaction

**(A) Source:** `ThemeStateService::setSafeMode()` writes `theme_safe_mode`, then inserts an audit entry, then emits telemetry, without calling `Database::transaction()` (`src/Service/Packages/ThemeStateService.php:59-74`). Its controller calls it directly (`src/Controller/AdminThemeController.php:103-118`). `SettingRepository::set()` and `ModerationLogRepository::log()` issue separate database operations (`src/Repository/SettingRepository.php:63-71`; `src/Repository/ModerationLogRepository.php:24-38`). The repository write-path rule requires multi-table mutations to be transactional (`AGENTS.md:83`).

**Analysis:** This is a static transaction-boundary gap. A failure of the audit write after the setting write is the case to reproduce. No failed safe-mode request or partial commit was observed in this analysis.

**Proposed disposition:** Task 2 starts with that failure reproduction outside the integration harness's enclosing transaction. Add the transaction only after the test demonstrates the gap. Keep telemetry after the transaction returns.

### F3 The documented manual flag edits do not use the console row lock

**(A) Source:** The manual examples read `features`, change one key and call `set()` without a transaction or lock (`docs/runbooks/operations.md:80-94`). Console writes acquire `lockForUpdate()` inside `Database::transaction()` (`src/Service/FeatureFlagAdminService.php:61-76`). The locking repository method performs an upsert before `SELECT ... FOR UPDATE` (`src/Repository/SettingRepository.php:81-94`).

**Analysis:** A manual example can read an older whole map and subsequently replace a map changed by another writer. The source establishes the differing write protocols; a collision involving the exact current runbook commands was not executed here.

**Proposed disposition:** Task 3. Retain the manual procedure, including deliberate enablement of console-blocked flags, but use the existing transaction and row-lock API. Preserve the distinction between manual edits and actor-attributed console audit entries.

### F4 The environment template omits relevant mode and recovery controls

**(A) Source:** `ANTIABUSE_MODE`, `PACKAGE_EXECUTION_DISABLED` and `THEME_SAFE_MODE` are read in `config/config.php:221,329-336`. None appears in the inspected `.env.example:1-153`. The theme recovery page already explains the forced environment override and hides its exit form while forced (`templates/admin/theme_safe_mode.php:44-45,59-73`).

**Analysis:** This is a template discoverability gap, not evidence of missing runtime configuration or a broken forced-safe-mode page.

**Proposed disposition:** Task 4. Add the existing controls and their current defaults to the template, with concise precedence and runbook notes.

## Review Focus

- No stored mode: a configured `hold` or `block` must appear as the selected mode and as the audit's enforced `before` value. Task 1 owns these assertions.
- Empty, invalid or non-string stored values: GET uses the same resolution as enforcement; a submitted invalid draft remains visible on a 422. Task 1 owns these assertions.
- Audit failure after a safe-mode write: the persisted setting must retain its previous value, observed without an enclosing harness transaction. Task 2 owns the failure test.
- Two writers, including an initially absent `features` row: both independent changes must survive when following the documented procedure. Task 3 owns the two-connection rehearsal.
- A corrupt or non-encodable override map: a routine manual toggle must refuse it rather than silently replace other overrides. Explicit operator repair remains a separate operation. Task 3 owns these refusal checks.

## Proposed scope and alternatives

Use the existing resolver and persistence APIs. This keeps the change in the affected settings and operations paths and avoids another interpretation of configuration precedence.

Copying fallback logic into the model would be a smaller edit, but would leave a second interpretation to maintain. A general configuration-management subsystem would affect substantially more callers than these findings establish. Neither alternative is part of this proposal.

Tasks 1–4 are independently reviewable. Start with Task 1, whose mismatch is reproduced. Tasks 2 and 3 have explicit database reproduction gates. No flag graduation, schema migration, visual redesign, new CLI command or deployment is included in the proposed implementation scope.

## Task 1 Use effective anti abuse mode in the settings model and audit

**Files:**

- Modify: `src/Service/AdminSettingsService.php:26-34,56-64,156-168`.
- Modify: `src/Core/App.php:2438-2445`.
- Test: `tests/Integration/Core/AppAdminModerationTest.php`.
- Reuse regression coverage: `tests/Integration/Admin/AdminSafeguardsTest.php`.
- Add focused browser evidence to `docs/evidence/feature-flags-runtime-config-2026-11-08/` during implementation.

**Interfaces:**

- Consume the existing `AntiAbuseService::mode(): string`.
- Add `private AntiAbuseService $antiAbuse` to `AdminSettingsService::__construct()` before its optional branding collaborator, and supply it in the existing App binding.
- Keep `moderationModel(array $overlay = []): array` and `updateAntiAbuse(User $admin, array $input, bool $confirmed = false): bool` unchanged.

- [x] Add `test_moderation_form_shows_effective_mode_without_a_database_override`: for configured `observe`, `hold` and `block`, delete only the `antiabuse_mode` setting, rebuild the App with that config, GET `/admin/moderation`, and assert the matching `<option value="..." selected>`.
- [x] Add `test_moderation_form_resolves_stored_mode_like_enforcement`: configured `block` plus stored `hold` selects `hold`; stored `''` selects `block`; an invalid stored string selects `observe`; a non-string stored value falls back through the runtime resolver.
- [x] Add `test_moderation_audit_before_records_the_enforced_inherited_mode`: configured `block` with no stored mode, POST a valid `flag` save, and assert the newest matching audit has `before_json.antiabuse_mode === 'block'` and `after_json.antiabuse_mode === 'flag'`.
- [x] Add `test_invalid_moderation_draft_is_preserved_over_effective_mode`: POST an invalid mode while config is `hold`; assert 422, the submitted value remains in the form, and the persisted mode is unchanged. Retain the existing Block review/draft assertions.
- [x] Run `vendor/bin/phpunit tests/Integration/Core/AppAdminModerationTest.php` and record which new assertions fail on the base revision. Do not infer a failing test from the direct-service probe alone.
- [x] Inject the existing resolver and use `mode()` for the initial model and the audit `before` mode. Keep template draft precedence and saved blocked-word ownership intact.
- [x] Run the changed test file and `vendor/bin/phpunit tests/Integration/Admin/AdminSafeguardsTest.php`. Capture `/admin/moderation` with an inherited `hold` at 1280×800 and 390×844, with JavaScript enabled and disabled; verify the selected mode and ordinary save/review path. No styling change is intended.
- [x] Review and commit this task's explicit files after its checks pass. Do not stage unrelated working-tree changes.

## Task 2 Keep safe mode and its audit record in one transaction

**Files:**

- Modify, after reproduction: `src/Service/Packages/ThemeStateService.php:59-74`.
- Test: `tests/Integration/Core/AppThemePackageTest.php`.

**Interfaces:**

- Keep `setSafeMode(User $admin, bool $enabled, ?string $currentPassword = null): void`.
- Consume `Database::transaction(callable $callback, ?array $writtenTables = null): mixed`, existing settings and audit methods, and the existing test helper `withoutHarnessTransaction(callable $callback): mixed`.

- [x] Add `test_safe_mode_audit_failure_preserves_the_previous_setting` using a private test database and an actual database write failure. On MySQL/MariaDB with foreign keys enforced, a deliberately non-existent actor id can reject the audit insert through `fk_modlog_actor` (`database/migrations/0010_moderation_log.php:23`). Assert that this exact failure occurred; do not silently replace it with a mocked repository.
- [x] Execute that case inside `withoutHarnessTransaction()`, restoring any committed fixture state in `finally`. Assert the stored setting from the real database after the exception. A rollback assertion made only inside the normal test transaction does not prove atomicity here.
- [x] Run the new case on the base revision and record the observed outcome. If the expected partial-write case is not reproduced, stop this task's patch and update the finding rather than claiming confirmation.
- [x] Once reproduced, wrap the setting and audit writes together in `Database::transaction()`. Keep the existing account-state guard, password requirement for exit, stored string values and post-transaction telemetry.
- [x] Verify one audit entry on successful entry and exit, no changed setting on rejected exit or failed audit, and continued forced-safe-mode behavior. Reuse `test_safe_mode_serves_system_theme_while_theme_active` and `test_admin_safe_mode_route_serves_system_theme_while_theme_active`.
- [x] Run `vendor/bin/phpunit tests/Integration/Core/AppThemePackageTest.php`, review the explicit diff and commit the task after its checks pass.

## Task 3 Make the manual override recipes use the existing row lock

**Files:**

- Modify: `docs/runbooks/operations.md:80-94`.
- Add implementation-time rehearsal and receipt: `docs/evidence/feature-flags-runtime-config-2026-11-08/runbook-lock-rehearsal.php` and `runbook-lock-rehearsal.json`.

**Interfaces:**

- Consume `Database::transaction()`, `SettingRepository::lockForUpdate(string $key, mixed $seed): mixed`, `FeatureFlags::isOverrideMap(mixed $overrides): bool` and `SettingRepository::set(string $key, mixed $value): void`.
- Retain the existing inspect, disable-one-key and remove-one-key operator actions.

- [x] Reproduce the collision with two connections to a throwaway database: pause a manual writer after reading the map, commit another key through the locking console protocol, resume the manual writer and record the final map. Preserve this as the baseline result, not a production incident claim.
- [x] Put each documented mutation inside `Database::transaction()`. Acquire `lockForUpdate('features', new stdClass())` before reading/modifying; reject a map that fails `isOverrideMap()` or cannot be JSON-encoded; persist the edited map as an object. Preserve every other key.
- [x] Keep explicit repair instructions separate from routine toggles. Explain that these manual commands do not create an actor-attributed console audit record; retain the ability to deliberately enable a console-blocked flag.
- [x] Rehearse the revised sequence with an absent row and an existing row. Assert both writers' key changes survive, including a writer following the console protocol. Also check that removing the last override stores `{}`, and corrupt/unwritable maps are refused without replacement.
- [x] Review the exact published command bodies, store the rehearsal receipt and commit only the runbook/evidence files for this task. This task alone does not require another application-wide PHPUnit run.

## Task 4 Document existing mode and recovery environment controls

**Files:**

- Modify: `.env.example`.

**Interfaces:**

- Document existing keys; do not change the executable configuration array.

- [ ] Add `ANTIABUSE_MODE=observe`, noting that a nonempty stored `antiabuse_mode` takes precedence and listing `observe|flag|hold|block`.
- [ ] Add `PACKAGE_EXECUTION_DISABLED=false`, explaining its flag-independent OR relationship with the stored emergency-disable setting and linking `docs/runbooks/package_integrations.md`.
- [ ] Add `THEME_SAFE_MODE=false`, explaining that it forces safe mode until removed/disabled and linking `docs/runbooks/package_themes.md`.
- [ ] Compare names and defaults directly with `config/config.php:221,329-336`; check surrounding template comments and links. Do not add schema-only or reserved-feature enablement instructions.
- [ ] Review and commit the template-only diff. Do not write a test that merely mirrors these three static assignments.

## Final application verification

- [ ] Review the combined diff and ensure unrelated edits are preserved.
- [ ] Run `composer test` once for the final application candidate, after Tasks 1 and 2 are settled. Repeat only for changed inputs, failures or unresolved concerns.
- [ ] Retain the focused Task 1 browser evidence and Task 2/3 database failure/concurrency receipts. Run generated-asset or Worker checks only if their inputs actually change.
- [ ] Update `CHANGELOG.md` with shipped behavior only when implementation is verified. Any accepted deferral must remain in an ADR, not disappear from the work record.
- [ ] Report implementation, verification and publication separately. A later release must follow the exact-deployment verification in ADR 0051 and the Cloud Run runbook; publication is outside this analysis/planning handoff.
