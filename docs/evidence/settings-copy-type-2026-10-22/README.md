# Settings clarity, typography and polish

Owner-requested `impeccable clarify typeset polish` follow-up. Preserve the
Imladris identity and existing native settings workflows; no publication is
authorized or claimed. This evidence is separate from the preceding
[layout pass](../settings-layout-2026-10-22/README.md).

**Not publication-ready:** confirmation exposes an unresolved narrow-pane
subscription layout defect. The selected browser tests pass, but full PHPUnit
is not green and earlier no-JavaScript axe/runtime checks remain unresolved.
Changes are uncommitted on `main`; nothing was committed, pushed or deployed.

## Independent assessment before the type scan

- **Authority and fit:** existing Cormorant display headings, EB Garamond prose,
  Marcellus controls/labels and JetBrains Mono data all have distinct jobs.
  `resources/imladris/tokens/fonts.css` ships Marcellus at 400 only; the generic
  `.field > span` requests 600 and settings currently inherits that synthetic
  emphasis. Preserve the families; use the delivered weight for labels.
- **Hierarchy:** settings panel captions use `.72rem`, field labels `.82rem`,
  organization headings `1.24rem`; the latter should use the existing Title
  role. A long digest-time label competes with the value and wraps on phones.
  Keep a short label and move its existing timezone caveat into associated help.
- **Scale:** the last pass introduced two `--text-small` references, but the
  authored scale names that role `--text-sm`. Correct that local defect, use
  Fine for field labels and Meta for restrained panel captions, and leave
  operator-console typography unchanged.
- **Reading:** body remains the book serif at its existing size. Supporting
  prose/help takes the existing narrow measure (56ch) and readable leading;
  avoid adding another face or another size scale.
- **Stress:** retain the existing container reflow, user font-size preferences,
  long-name wrapping, visible focus and native disclosures. Persistent creation
  labels must replace reliance on examples without hiding any required detail.
- **Delivery:** self-hosted WOFF2 assets already use `font-display: swap` and
  serif fallback stacks. No new fonts, downloads or weight files are needed.

## Message hierarchy

Use a concise, shared settings purpose statement. Clarify board folders without
the metaphor of private rails; name creation inputs and notification opt-out.
Keep all restrictions, timezone behavior, independent avatar-save semantics,
privacy masking and server validation meaning unchanged. No new workflow or
feature-completion claim is introduced.

## Implemented scope

- One shared, shorter settings introduction across all thirteen account panes.
- Persistent Board folder / Feed / Bookmark folder name labels and a visible
  board-filter label. Examples no longer stand in for the field's name.
- A short **Daily digest time** label with programmatically associated timezone
  help, including the existing UTC fallback and validation-error association.
- Explicit, target-named **Turn off notifications** actions that still mask
  unavailable subscriptions; plain Forum panes and density descriptions.
- Fine-sized regular Marcellus labels, Meta-sized panel captions, Title-sized
  organization headings, narrow supporting prose, and 44px settings actions in
  every input mode. No new fonts or animation.
- Replace the layout pass's undefined `--text-small` references with authored
  roles. Update the stale lifecycle alert-class expectation to require the new
  focusable danger summary; select the actual Messages coarse-pointer block
  before asserting its existing touch rules, instead of assuming it is first.

## Verification

- Focused PHP: **126 tests, 1,194 assertions passed**; token-definition contract:
  **1 test, 1 assertion passed**. The two stale test assumptions and the undefined
  token now pass; the historical failed runs remain recorded separately.
- `npm run build`, `npm run check:assets`, PHP lint and scoped
  `git diff --check`: passed. Package files/dependencies are not changed by this
  task; the installed Vite remains 8.1.3.
- Initial type scan: **176 shared font-size advisories, 1 font-alias advisory**,
  none in account templates. They describe the full legacy application
  stylesheet, not 177 new settings defects. This task changes the member
  register only, not unrelated admin/queue typography. Raw findings are in
  [type-before.json](type-before.json).
- No current settings critique snapshot was returned by the snapshot helper;
  this is an independent pass, not closure of an unrelated critique backlog.
- Initial browser inspection: **39 passed, 1 skipped, 2 failed**. The failures
  were the new native test's exact `getByLabel` lookup for the digest select;
  the accessibility snapshot already named the combobox **Daily digest time**.
  The correction uses that role/name, without adding an ARIA label to conceal
  the native label or disabling any accessibility check.
- The single correction batch also puts each subscription title before its
  delivery controls and gives Save/opt-out target-specific accessible names.
  Its intended narrow-pane action stacking still fails visual confirmation
  (see below). Unavailable targets remain masked. Native verification now
  exercises Save as well as opt-out at the normal project sizes.
- The preceding desktop Favorite/Mute top-edge assertion passed in the initial
  run after normalizing settings targets to 44px; its historical failure is not
  rewritten or hidden.
- Final type scan: **175 shared font-size advisories, 1 font-alias advisory**;
  no account-template findings and no newly introduced arbitrary type size.
  The remaining shared legacy findings are retained in
  [type-after.json](type-after.json), not treated as permission to rewrite
  unrelated surfaces.

- Confirmation browser: **41 passed, 1 skipped**, in 2.4 minutes; see
  [browser.log](browser.log). The selected cases span `settings-copy-type`,
  `settings-layout` and `account-settings-repairs` in desktop/mobile Chromium.
  The skip excludes the mobile duplicate of the desktop-driven width matrix.
  Native creation, disclosure/error recovery, subscription Save/opt-out,
  restricted-account opt-out, folder/feed shortcuts and profile draft journeys
  passed. The earlier desktop Favorite/Mute alignment assertion also passed.
- JavaScript-enabled axe scans completed for six copy/type routes in both
  themes and both projects, plus the four-route layout matrix. No rules were
  disabled. The native form cases do not run axe and therefore do **not** close
  the preceding pass's six no-JavaScript execution-context exceptions.
- Full PHPUnit (`memory_limit=1G`): **3,351 tests, 29,562 assertions,
  13 failures, 2 warnings, 1 skipped**, with zero errors. See
  [phpunit-full.log](phpunit-full.log) and [phpunit.xml](phpunit.xml).

## Full-suite comparison and retained failures

Compared failing JUnit identities with the preceding layout run: **16 → 13**,
with no newly failing identities. These three failures are absent from the
completed full run, not merely the focused checks:

- `ImladrisRuntimeAssetTest::test_every_required_runtime_variable_has_a_definition`
- `MessagesStylesContractTest::test_touch_targets_reach_44px_and_small_labels_meet_the_type_floor`
- `AppAccountLifecycleTest::test_a_scoped_lifecycle_error_falls_back_when_its_form_is_not_rendered`

The remaining thirteen are the same identities recorded in the
[hardening failure breakdown](../settings-hardening-2026-10-22/README.md#retained-full-suite-failures):

- Six `CloudRunBrowserCiContractTest` cases: final-step/archive completeness,
  actual Docker failure recording, archive creation, independent-suite runner,
  private-field/symlink packaging, and completed-artifact requirements.
- `CloudRunBrowserCiPackageContractTest::test_non_object_unified_results_fail_completion_and_preserve_the_diagnostic_archive`.
- `CloudRunBrowserCiUploaderContractTest::test_streaming_create_only_transport_and_credential_privacy_contracts`.
- Four `CloudRunDeploymentContractTest` cases: private instance/SQL/bucket
  contract, configured REST mail transport, cron schedule/no migration, and
  ordered console-batch execution.
- `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources`:
  the changed application presentation remains intentionally unreconciled with
  `config/imladris-runtime-baseline.json`. That publication baseline was not
  refreshed to make a check green.

The twelve unchanged deployment/browser-CI contracts still encounter the
Windows-host diagnostics documented in the earlier evidence. No clean
pre-change baseline was captured, so they are retained failures, not proven
pre-existing ones. The two PHP warnings remain denied `symlink()` creation in
`CloudRunBrowserCiContractTest` and failed `proc_open()` in
`CloudRunDeploymentContractTest`. The skip is the fixture-free migration
down/up rehearsal in `AppThreadIntelligenceMigrationTest`.

## Confirmation capture review

Reviewed the saved desktop Profile and Notifications light captures, phone
Twilight Boards and Notifications captures, and the 320px/enlarged-text
Notifications capture. Labels, helper hierarchy, existing serif roles and
linear organization sections retain Imladris. Desktop subscription titles
precede controls and the closing actions align as intended.

Computed [desktop](desktop/type-metrics.json) and
[phone](mobile/type-metrics.json) type evidence each contain twelve states:
six routes × two themes. At the 16px root, measured labels are 13.76px and
captions 12.48px, both at weight 400; Marcellus is loaded and visible buttons
are at least 44px high. The [desktop](desktop/layout-metrics.json) and
[phone](mobile/layout-metrics.json) layout measurements retain 16px field gaps,
24px task gaps, 32px organization gaps and 11px switch-track/text separation.
Favorite/Mute top-edge offsets are zero in both projects. Mobile settings have
two actual grid rows (chooser and pane), rather than phantom tail rows.

### Unresolved: subscription actions do not stack in narrow panes

The final [phone Notifications capture](mobile/type-notifications-dark.png)
still places Save and opt-out side by side. In
[320px Notifications with a 32px root](mobile/large-text-notifications.png),
the frequency value becomes unreadable and Save wraps into single-letter
lines. Staying inside the viewport is **not** successful readable reflow.

Source review identifies the local cause in `public/assets/app.css`: the
two-column `.account-subscription-list .account-subscription-row` selector has
greater specificity than the single-column `.account-subscription-row` rule
inside `@container account-subscriptions`. The container condition does not
increase specificity, so the intended narrow override loses. The viewport-fit
assertions check control bounds, not action stacking, minimum readable width
or legible selected values; passing them does not close this defect.

This is a remaining implementation defect, not an intentional design exception.
A follow-up should repair the override and pin readable stacking with explicit
geometry/readability assertions. No third UI edit, build or capture round was
performed after confirmation, and responsive acceptance is not claimed.

## Isolation and remaining limits

- PHPUnit used `retroboards_test_settings_20261022`; browser fixtures used
  `retroboards_unified_e2e_settings_20261022` at `http://localhost:8048`.
  The Playwright-owned server has stopped; port 8048 has no listener.
- The preceding six no-JavaScript axe failures (`Execution context was
  destroyed`) were not rerun in this selected confirmation matrix and remain
  unresolved. Passing new native workflows is not replacement a11y evidence.
- The last `npm run test:assets` remains **34 passed, 1 failed**: installed
  Miniflare rejects absent `modules`, `script` and `scriptPath` options. That
  runtime rehearsal was not rerun in this copy/type pass.
- Captures use browser emulation and enlarged root text, not physical devices,
  actual browser zoom, other browser engines or external identity providers.
- Existing reduced-motion policy, mute-pill decision and deferred account
  workflows are unchanged. Package/dependency edits, unrelated captures and
  other pre-existing workspace changes were left intact. Disposable schemas
  and local fixture storage remain available; no broad cleanup was performed.
- Generated delivery assets/manifest reflect the local build. No publication
  baseline, production setting or deployment was updated.
