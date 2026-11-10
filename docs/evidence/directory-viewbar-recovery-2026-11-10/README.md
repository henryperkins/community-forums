# Directory viewbar recovery

Recovered 2026-11-10 from stash `e9579a5918b47ebee092ec430a3d761b8c15bbb1`
onto the current Forum theme, following the repository-wide branch/worktree review.

## Change and source disposition

- Mobile order and topic-preview controls now share the desktop controls' 44px
  targets, explicit boundaries, readable filled selection and semibold selection
  cue. Mobile labels use the Forum label style; the settings link is underlined.
- Guest/introductory links receive the stashed spacing, the mobile heading uses
  `--text-display-md`, and forced-color control boundaries cover the mobile panel.
- The settings link uses `--text-fine` rather than the stash's equivalent literal
  `.875rem`. The later Fluent icon and Living Brief citation rules remain in the
  current source.
- Reviewed all four stashed detector exceptions: `.9375rem` is the existing 15px
  compact chrome step; `1.0625rem` is the existing 17px lede/topic-title step;
  `1.75rem` is the existing 28px auth-title step; `#000` is the danger-hover
  color-mix endpoint. Each is scoped to `resources/forum/theme.css`. No exact
  existing size token represents these intermediate steps. The detector returned
  `[]` (`logs/design-detect.json`).

The baseline captures (`before-390.png`, `before-1280.png`, `before-viewbar.json`)
show the old mobile pill controls and the already-conventional desktop controls.

## Browser evidence

`tests/browser/board-index-hardening.spec.ts` runs against the disposable local
database `retroboards_e2e_recovered_directory` at `http://localhost:8025`.

- The initial desktop-project run passed eight of nine cases. Its guest no-JS
  focus check used programmatic focus after a pointer click, which did not enter
  keyboard modality. The check now tabs forward and back before asserting
  `:focus-visible`; the **six affected cases passed** on the final rerun. The
  other three cases already passed with identical application inputs.
- **Two additional mobile-project cases passed** with Chromium touch emulation.
  These cover guest/member modal focus, dismissal, selected controls and handoff
  between phone and desktop widths.
- Coverage includes 320, 390, 760, 861, 1024, 1280 and 1440px widths, landscape,
  enlarged text, long board names, actual GET/POST view changes, saved member
  preferences, light/dark themes, the no-JavaScript native disclosure, forced
  colors and visible keyboard focus.
- Rendered controls meet the checked 44px target, 14px default text floor,
  4.5:1 text contrast and 3:1 boundary/fill contrast. Both selected controls retain
  a heavier text cue. The sheet axe scans report no violations.
- Captures and measurements are in `browser/` and `browser-touch/`; the initial
  failure and the final rerun are both retained in `logs/`.

The captures were inspected at their shipped sizes. This evidence uses Chromium
and emulated viewports/input; it does not claim a physical-device or assistive-
technology review.

## Final application check

`composer test -- --display-deprecations --display-skipped` completed at
**06:44:01 UTC** on PHP 8.5.4: **3,675 tests, 36,332 assertions, no errors or
failures**. The full output is retained in `logs/phpunit-final.log`.

- The run used the private database `retroboards_test_recovered_work_20261110`,
  a fresh schema prepared before the suite, test-only credentials and APP_KEY,
  the repository's upload settings, and `COMPOSER_PROCESS_TIMEOUT=0`.
- Eight PHP 8.5 deprecations were reported in existing reflection, PDO, cURL and
  image-cleanup calls. One direct migration down/up rehearsal was skipped because
  it requires its dedicated fixture-free database; this change adds no migrations.
- Earlier attempts had incomplete test configuration or inherited the five-minute
  Composer timeout. The completed run supersedes them. Application inputs did not
  change after this run; their source hashes remain recorded below.

## Generated assets and retention

The previously live release `276fd01648589411` was verified by the recovered
checker ([receipt](../asset-evidence-recovery-2026-11-10/live-before.json)) before
being recorded as deployed. Then `composer build:forum` and `npm run build`
generated candidate **`857e839d00bf1bf2`** from current source.

- `npm run check:assets`: passed.
- `npm run test:assets`: **35 passed**.
- Pins: `276fd01648589411`, `23ca787930a7a0a7`, `11b63fb564c36ce9`.
- Retiring the oldest pin (`0ab71f5ca65e7fa0`) removes only
  `forum-theme-style-CNIV-nj8.css`. Every file of the previously live release is
  retained. The old generated stash bundle/manifest were superseded by this build.

`asset-recovery.json` records the source hashes, candidate and retention change.
Publication and exact production deployment receipts belong on the recovery pull
request, avoiding a proof-only follow-up deployment.
