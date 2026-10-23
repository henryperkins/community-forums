# Settings hardening, colour and responsive adaptation

Local-only work for the owner's combined `impeccable harden colorize adapt`
request. Imladris, existing content, routes, permissions, form actions and
progressive enhancement are retained. No publication or deployment is claimed.
**Verification is incomplete:** browser, full PHPUnit and asset-runtime checks
retain the failures below. Changes are uncommitted on `main`; nothing was
committed, pushed or deployed.

## Scope

- Persistent numbered labels and individual focus indicators for custom profile
  fields; named passkey actions; board-specific preference names and pressed
  states; current-section navigation on Appeals and a labelled appeal-text
  recovery control.
- Shared semantic callouts for verification and orphan/account/organisation
  errors, with focus on a rejected form's summary. Digest errors are associated
  with their controls, preserve rejected values and focus only the first error.
- Native member select arrows (including forced colours), `--field-rule` on
  custom-field frames, `--gold-ink` on board hashes, the gold Twilight preview
  accent, and labelled 44px preference/delivery targets.
- Container-responsive account navigation, form columns, recovery codes and
  organisation panels. Long names and validation summaries wrap without
  displacing controls. Folder errors and their form each span the whole row.

No new bookmark-management, email discovery, email/username editing, security
history or notification-matrix workflow was added. Existing ADR 0035 and ADR
0014 carryovers remain open. The reduced-motion policy and unresolved mute-pill
design decision are unchanged. The shared subscription controls also appear in
Topic tools; their existing POST names and channel semantics are preserved.

## Verification

- Focused PHP: **74 tests, 842 assertions passed** (`AppSettingsHardeningTest`,
  account console, moderation appeals, folders/feeds and notification repairs).
- `npm run build` and `npm run check:assets`: passed using the installed Vite
  8.1.3. The package manifests and dependencies were not changed by this task.
- PHP lint and scoped `git diff --check`: passed.
- `npm run test:assets`: **34 passed, 1 failed**. The real Workers Assets
  rehearsal stops in the installed Miniflare constructor's option validation
  (`modules`, `script`, `scriptPath` are absent). No Worker code or test was
  changed; this option-validation failure remains unresolved.
- Mechanical detector: no template findings. Its 224 shared-stylesheet findings
  describe existing type-scale/colour advisories, intentional left rules and a
  `revert-layer` false positive, not 224 new settings defects. The raw run is
  retained in [detector.json](detector.json).
- Initial browser pass: phone/tablet reflow and forced-colour checks passed.
  The no-JavaScript axe runner failed on native POST documents; the confirmation
  pass tried axe's single-document runner (there are no settings iframes), but
  that did **not** resolve the failure. The fixture now clears its member's
  board preferences between cases.
- Confirmation browser: **31 passed, 13 skipped, 6 failed** across
  `settings-hardening`, `account-console`, `field-error-a11y` and `totp`.
  All six failures are the new profile/organisation/subscription cases (desktop
  and mobile) throwing `Execution context was destroyed` inside axe. Earlier
  native-form assertions passed, but assertions and captures after the failed
  scan did not execute. These are unresolved checks, not successful a11y proof.
  The 13 skips are explicit viewport exclusions (the existing console matrix
  plus the new tablet matrix, which runs only in the desktop project).
- Passing browser checks include light, Twilight and system-dark account scans;
  original field-error focus and draft preservation; session revocation;
  native keyboard navigation; first controls within 390px phone viewports;
  TOTP enrolment/login/disable without JavaScript; 320/761/861/1024/1280px
  reflow; 32px root-text simulation; and forced-colour native select focus.
- Full PHPUnit with `memory_limit=1G`: **3,343 tests, 29,481 assertions,
  15 failures, 2 warnings, 1 skipped**. The run completed in 5m28s and reported
  102 MB memory. See [phpunit-full.log](phpunit-full.log) and
  [phpunit.xml](phpunit.xml); focused results are in
  [phpunit-focused.log](phpunit-focused.log). The full suite is **not green**.

## Retained full-suite failures

Two contract mismatches are directly exposed by these changes:

1. `MessagesStylesContractTest::test_touch_targets_reach_44px_and_small_labels_meet_the_type_floor`
   assumes the first `@media (pointer: coarse)` block belongs to Messages. The
   new settings touch-target block now appears first; Messages rules were not
   changed. This assertion still fails and needs selector-scoped parsing.
2. `AppAccountLifecycleTest::test_a_scoped_lifecycle_error_falls_back_when_its_form_is_not_rendered`
   still expects the removed `error-list` class. The response contains the
   password-error text in the new focusable danger callout, but the stale class
   assertion was not updated in the correction batch.

One publication-reconciliation failure is intentionally retained:

- `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources`
  rejects the changed application presentation digest, as does
  `composer build:imladris`. No runtime/design baseline was refreshed.

Twelve failures are in unchanged deployment/browser-CI contracts on this
Windows host; no clean pre-change run was captured, so they are not presented
as proven pre-existing failures:

- `CloudRunBrowserCiContractTest` (6):
  `test_child_final_step_requires_outer_success_and_a_complete_matching_archive`,
  `test_outer_command_records_actual_docker_failure_after_it_returns`,
  `test_archive_creation_failure_cannot_leave_a_success_record_or_old_archive`,
  `test_runner_attempts_every_independent_suite_and_retains_the_first_failure`,
  `test_packager_redacts_private_fields_excludes_symlinks_and_preserves_failure`,
  `test_completed_run_requires_capture_unified_and_every_upload_project_artifact`.
  Diagnostics include Windows paths passed to Bash, a missing `/workspace`
  build context, and backslash archive entries where forward slashes are
  expected.
- `CloudRunBrowserCiPackageContractTest::test_non_object_unified_results_fail_completion_and_preserve_the_diagnostic_archive`:
  unexpected missing-artifact diagnostics in the packaged result.
- `CloudRunBrowserCiUploaderContractTest::test_streaming_create_only_transport_and_credential_privacy_contracts`:
  the Python subprocess has three errors because Windows lacks `os.O_NOFOLLOW`.
- `CloudRunDeploymentContractTest` (4):
  `test_service_is_one_private_instance_on_the_cloud_sql_connector_and_the_data_bucket`,
  `test_production_mail_configuration_selects_the_configured_rest_transport`,
  `test_cron_jobs_keep_the_former_worker_schedule_and_never_migrate`,
  `test_console_batch_runs_every_command_in_order_and_reports_any_failure`.
  The first three report CRLF-tainted values; the last cannot start its
  subprocess. The run's two PHP warnings are a denied symlink and this failed
  `proc_open()`.

No further visual/test correction loop was performed after confirmation. The
six unresolved browser axe exceptions are retained in [browser.log](browser.log).

## Reviewed captures

Reviewed the desktop profile and Twilight appearance comparisons, phone
notifications and no-JavaScript initial Profile view, plus the 320px Boards,
861px Profile and enlarged-type 761px Appearance captures. The native chooser,
wrapping fields, theme preview, delivery switches and native select arrows
remain inside their available width. These are browser emulations, not physical
device or actual browser-zoom tests. No third visual iteration was performed.

## Isolation and limits

Browser DB: `retroboards_unified_e2e_settings_20261022`, local origin
`http://localhost:8046`. PHPUnit DB: `retroboards_test_settings_20261022`.
Both are newly created, private schemas; no shared developer database or live
account was used. Captures are kept in this directory, not historical evidence.

The 128 MB default PHP memory ceiling stopped the first full `composer test`
run in `ThemeAssetScannerTest`; the complete rerun used `memory_limit=1G`.
The application presentation digest is intentionally not re-baselined merely
to make a check green: ADR 0024 reserves that reconciliation for publication.
Actual device keyboards, physical touch devices, other browser engines and
external identity-provider authorisation were not tested.
The owned PHP server on port 8046 was stopped. Disposable test schemas and
`storage/settings-hardening-packages/` / `storage/settings-hardening-ratelimit/`
remain local test resources, not release files.
