# Repository verification — 2026-10-07

These checks describe the original implementation before review remediation.
The final fixes, rebuilt assets, and repeated verification are recorded in
[regression-fixes/README.md](regression-fixes/README.md). Historical logs and
baseline comparisons below remain intact.

The change is uncommitted on `main`, starting at `f8d4350ff417f3a8d68d7870fdc57947104a5643`.
The owner-provided prompt was present before this work and is preserved.
The [complete file list](changed-files.md) names each implementation and contract change.

- `DB_TEST_DATABASE=retroboards_test_create_menu composer test`: 3,140 tests, 24,019 assertions, no failures, one skip. The skipped migration 0077 down/up rehearsal requires its separate dedicated database.
- `composer build:imladris` and `composer check:imladris`: current generated assets from the unchanged design mirror.
- `DB_TEST_DATABASE=retroboards_test_create_menu composer verify:imladris`: 24 tests, 305 assertions, no failures.
- `npm run build`, `npm run check:assets`: current fingerprinted delivery files and manifest.
- `npm run test:assets`: 24 tests passed.
- Final Chromium header/compose regressions: 50 passed, 14 project-specific skips.
- Final Chromium Messages audit: 16 passed. Messages polling: 13 passed, 13 project-specific skips. DM reimagination: 2 passed, 2 project-specific skips.
- Final Chromium Messages refinement: 4 passed, 5 project-specific skips, 1 pre-existing copy assertion failure; see the untouched-HEAD comparison below.
- PHP syntax, JavaScript syntax and `git diff --check`: passed.
- Impeccable detector review: 228 existing findings; none target an added source line. The existing visual identity remains the authority.

Detailed outputs are in [`validation/`](validation/). The application baseline is
`90b4b820b63ab0e64b70d7ef6e5ea03e9e14f5286dcb6aa564ae7e4816b35138`.
The mirror component files and generated Imladris CSS have no diff; the generated
runtime manifest changes only its application-surface digest.

## Baseline comparisons

[`baseline/native-room-comparison.json`](baseline/native-room-comparison.json)
records 112 native conversation checks against the changed application and
untouched `HEAD`, on Chromium and WebKit, all seven widths, both themes and
both font preferences. It also checks that the baseline worktree's tracked
files are unmodified. Its private database, port and worktree are recorded.
The matching strict no-page-scroll assertion fails before this change at
192px of overflow in the affected native phone lanes. The comparison fixture
now has 16–28px; its 144px (8rem) reading floor remains, and Send is visible.
The browser matrix's independent fixture has its own measured values in the
main README and results JSON.

[`baseline/refinement-head-failure.log`](baseline/refinement-head-failure.log)
and [`baseline/refinement-change.log`](baseline/refinement-change.log) show
that the old Messages reference-state test expects the previous “New accounts”
copy. Both applications render the current copy and fail the same assertion.
That assertion remains unchanged. The HEAD test ran at localhost:8026 using
`retroboards_e2e_create_menu_baseline`; the changed test used localhost:8023
and `retroboards_e2e_create_menu`.

The original short-screen contract passed on HEAD, and its reading floor and
Send checks pass on the changed application. The phone reading-height
expectation now deducts only the measured subheader height, as specified by
ADR 0043. Initial latest-letter visits reveal the dock when content growth
places it below a short viewport; fragments, parked readers and existing page
scroll keep their position.

## Reproduction

Each browser group starts with `tests/browser/prepare.sh` on a private database,
and has separate rate-limit and package stores. The normal local reset script
expects a different container name, so these runs created the disposable DB
with the local MariaDB container and used `DB_RESET_CONTAINER=create-menu-nonexistent`
for the script's migrate-fresh/seed path. The seed and servers use deterministic
test keys. No production database or running developer server was changed.

The comparison helper is [`baseline/native-room-comparison.cjs`](baseline/native-room-comparison.cjs).
Its constants name the two prepared workspaces, private databases and ports.
Both servers must be running before invoking it. It creates only its seeded
accounts and conversation, measures the strict viewport assertion, records
failures and continues through every combination.

The broader browser matrix and capture commands are documented in
[`README.md`](README.md). Incidental captures written by legacy specs into
other evidence slices were restored. The owned PHP servers on 8023, 8024 and 8026 are stopped. The private databases
and untouched-HEAD worktree remain available for reproduction. No commit, push
or PR was performed.
