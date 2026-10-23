# Settings layout and distillation

Owner-requested `impeccable layout distill` follow-up to the
[settings hardening pass](../settings-hardening-2026-10-22/README.md).
Imladris, content, permissions and existing form endpoints are retained.
Local-only changes; no commit, push, publication or deployment is claimed.

## Spatial thesis and scope

Operate: choose a section, change a setting, save the relevant task. The
existing main scribe panels lead. Related fields stay close; separate tasks
have more space. Organization names, counts and shortcuts lead their sections;
management is available on demand, not repeated as an always-expanded form.

- One desktop grid row replaces the rail's `1 / span 60`, which manufactured
  empty gutters beneath short forms. Sticky navigation is retained.
- Profile facts use numbered label/value inputs without nested frames, gold
  dots or inter-field marks. One outer settings pane owns container adaptation.
- Organization cards, nested item cards, dashed empty-state boxes and icon
  tiles become plain linear sections with hairlines. One redundant instruction
  after the empty Board folders form is removed; no capability is removed.
- Native management disclosures contain the existing rename/filter/remove/
  delete forms. The failed editor opens in the server response; digest opt-out,
  existing shortcuts, creation and filing forms stay directly accessible.
- Tight field rhythm uses `--space-4`; task groups use `--space-5`; organization
  sections use `--space-6`. Stacked forms no longer force a switch track and
  its label onto separate lines. Favorite/Mute forms travel as one action group
  when a narrow board row wraps, rather than splitting around the board name.

## Independent source/visual assessment

The source assessment preceded the mechanical scan. Incumbent captures came
from the immediately preceding local hardening pass and match the current
templates/stylesheet before this follow-up.

- **Reading order:** Profile's main path is Identity → labelled fields → Save
  profile, with the independently saved Avatar preceding it. Boards leads with
  favorite/mute preferences, then personal organization. Both paths are retained.
- **Grouping:** repeated input frames inside a fieldset inside Identity, and
  icon/empty-state/item cards inside organization cards, competed with the main
  task groups. Remove those shells, not their labels or controls.
- **Rhythm:** the existing global stacked-label margin overrides switch flex
  layout; grid groups have no consistent closing interval. Use local field/task
  spacing without changing shared operator forms.
- **Structure:** the sticky rail spans 60 implicit grid rows despite having one
  sibling pane. Organization editors differ in length and are not equivalent
  dashboard tiles; use one linear sequence and native disclosures.
- **Density:** creation and opt-out stay in the reading path; rename/filter/
  delete are less frequent and get named disclosures. No new workflow or
  feature-completion claim is introduced.
- **Adaptation:** retain the prior container-aware navigation, single-column
  narrow forms and readable control names. DOM order remains the visual and
  keyboard order; disclosures do not depend on scripting.
- **Extremes:** verify long unbroken folder names, multilingual profile values,
  populated/empty organization, rejected edits, 320px width and enlarged text.
  Keep focused errors inside a server-open disclosure and leave opt-out visible.
- **Mechanical scan:** the initial layout-scoped scan returned `[]`. That did
  not detect the phantom grid rows or the hierarchy issues above.

## Verification

- Focused PHP: **75 tests, 857 assertions passed**; see
  [phpunit-focused.log](phpunit-focused.log).
- Asset build and `npm run check:assets`: passed. The local delivery manifest
  is `f8314c2bb1be7f1e`; this is not a published release. Installed Vite is 8.1.3,
  unchanged from the preceding pass; package manifests/dependencies are untouched.
- PHP lint and scoped `git diff --check`: passed.
- Initial browser inspection: **26 passed, 1 skipped, 3 failed**. The two
  folder cases expected a literal space instead of the existing server's
  trimmed empty name. The desktop theme-switch scan reported contrast on the
  Upload avatar button; the correction waits for transitions to finish and
  moves the pointer away before scanning, without disabling a rule or changing
  the palette. See [browser-initial.log](browser-initial.log).
- The one correction batch also groups Favorite/Mute on narrow rows, resets
  scroll position before full-page captures, and removes redundant wrappers.
- Both layout-scoped scans returned `[]`: [before](layout-before.json),
  [after](layout-after.json). Source/render inspection still found issues the
  scanner could not detect.
- Asset-runtime tests: **34 passed, 1 failed**, again in the unchanged Miniflare
  constructor option validation (`modules` / `script` / `scriptPath` absent).
  See [assets-tests.log](assets-tests.log). No runtime workaround was introduced.

- Browser confirmation: **28 passed, 1 skipped, 1 failed**; see
  [browser.log](browser.log). All native profile/folder/feed flows and selected
  legacy journeys passed, including restricted-account digest opt-out.
  The explicit width/enlarged-text matrix passed. The one skip is the mobile
  project exclusion for the desktop-driven width matrix.
- The retained failure is the desktop `settings layout remains accessible and
  grouped in both registers` case: the newly added action-row assertion compares
  button **top edges**, although the wrapper center-aligns differently sized
  Favorite/Mute controls. The wide capture shows a shared row, and phone
  measurements show zero top offsets, but the desktop assertion still fails.
  Its later desktop notification/privacy scans, measurements and captures did
  not execute. Do not treat the combined desktop case as passing accessibility
  proof. The mobile case completed both themes for all four routes.
- Settled desktop Profile scans passed both themes in confirmation; no contrast
  rule was disabled and no palette change was made. This does not erase the
  preceding pass's six no-JavaScript axe execution-context exceptions.
- Phone computed values are retained in
  [layout-metrics.json](mobile/layout-metrics.json): 16px field gaps, 24px task
  gaps, 32px organization gaps, 44px switch labels, 11px track/text separation,
  and two layout rows (native chooser + content), not 60 implicit rows.

- Full PHPUnit: **3,347 tests, 29,520 assertions, 16 failures, 2 warnings,
  1 skipped**. See [phpunit-full.log](phpunit-full.log) and
  [phpunit.xml](phpunit.xml). Comparing failing test identities with the
  [preceding run](../settings-hardening-2026-10-22/README.md) finds its same
  15 failures plus one new local failure:
  `ImladrisRuntimeAssetTest::test_every_required_runtime_variable_has_a_definition`
  reports the two organization styles' undefined `--text-small` token. The
  authored name is `--text-sm`. This is a regression from this pass, not an
  environment failure. The owner's subsequent type/polish request includes
  correcting it; this historical run remains failed.

The owner then requested a separate `clarify typeset polish` refinement; these
results remain the pre-copy/type-polish snapshot, not a claim about later code.

## Confirmation review

Reviewed the 1280px light Boards capture, phone Twilight populated/open folder
view, desktop Twilight rejected folder edit with an 80-character name, and
phone measurements alongside the initial Profile/notification and enlarged
text captures. Major tasks retain their main panels; organization uses readable
linear sections; error summaries and controls stay within the pane. Native
disclosures, named actions and document/focus order remain complete without JS.
The empty desktop tail is gone, and the phone Favorite/Mute pair shares a row.
No further layout correction or capture round was performed after confirmation.

Browser database: `retroboards_unified_e2e_settings_20261022`; PHP database:
`retroboards_test_settings_20261022`. Both are the private, disposable schemas
created for the preceding pass, not the shared developer or production DB.
Browser origin: `http://localhost:8047`.
The Playwright-owned server stopped after confirmation; port 8047 has no
remaining listener. Disposable schemas and local fixture storage are retained.

The browser command selects `settings-layout.spec.ts` and the existing
`account-settings-repairs.spec.ts` journeys with:

```text
--grep "settings layout|folder shortcuts|saved feeds can be|clearing a legacy|saved-feed validation|member can turn off a saved-feed|invalid avatar preserves|Enter in a profile"
```

Full PHP runs use `php -d memory_limit=1G vendor/phpunit/phpunit/phpunit
--log-junit docs/evidence/settings-layout-2026-10-22/phpunit.xml` against the
private PHP schema. Actual device keyboards, physical touch hardware, other
browser engines and actual browser zoom are not covered by these emulations.
