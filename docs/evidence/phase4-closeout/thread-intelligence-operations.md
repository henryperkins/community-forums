# Thread Intelligence Operations Evidence

**Scope:** deterministic browser/a11y/no-JS capture, clean install and upgrade,
backup/restore semantics, rollback, pre-flip regression, and default-on
verification.

**Status:** every graduation gate ran and was recorded on 2026-07-12. The two
production defaults are `true`, with independent explicit-false rollback pins.

## Host Adaptations

- This is a Windows-native linked worktree. Status and commit evidence use
  native `git.exe` to avoid WSL CRLF noise.
- Every Windows PHP process uses the worktree `storage/cache` as
  `PHP_INI_SCAN_DIR` and Git for Windows' OpenSSL configuration.
- PHPUnit selects `DB_TEST_DATABASE`, not `DB_DATABASE`. The direct migration
  test therefore uses
  `DB_TEST_DATABASE=retroboards_thread_intelligence_clean`; console migration
  commands continue to use `DB_DATABASE`.
- Browser seed, fixture subprocesses, PHP server, and CI share one deterministic
  dummy `APP_KEY` and dummy `OPENAI_API_KEY`. The fixture injects the fake
  provider and moderator, so no provider network call occurs.
- Desktop and mobile share one evidence database. Their fixture thread titles
  are project-specific and every global latch, pause, and budget mutation is
  restored.

## Browser, No-JS, and Accessibility

The repository Playwright harness is used because the Browser plugin is not
available. The target flow is seeded public topics -> member, curator, and
operator actions -> accessible preserved output at 1280x800 and 390x844.

Recorded 2026-07-12 (UTC), local Windows host. Fixture threads live on the
dedicated public `ti-briefs` board so gate-a's `/c/general` page-1 journeys
stay undisturbed:

- 10:06:06Z `npm run prepare-db && npx playwright test
  thread-intelligence.spec.ts` — 12 passed (6 desktop + 6 mobile, including
  the `javaScriptEnabled: false` journey and both scoped axe scans).
- 10:07:41Z `npx playwright test thread-intelligence.spec.ts --grep
  'no-JS|axe'` — 4 passed; the axe scans reported zero serious or critical
  findings.
- 09:57:47Z `npx playwright test gate-a.spec.ts thread-intelligence.spec.ts`
  — the five journeys the earlier in-`general` fixture had displaced (poll
  vote, topic workflow, content references) pass again on both viewports.

Committed assets are `75-thread-intelligence-fallback.png` through
`79-admin-thread-intelligence.png` under both
`docs/evidence/browser/desktop/` and `docs/evidence/browser/mobile/`.

## Migration and Upgrade

Recorded 2026-07-12 (UTC), local Windows host, MariaDB 11.4 dev container:

- 09:51:19Z `APP_ENV=testing DB_DATABASE=retroboards_thread_intelligence_clean
  php bin/console migrate:fresh` — fresh database, applied 77 migration(s)
  through `0077_thread_intelligence`.
- 09:51:28Z `APP_ENV=testing DB_DATABASE=retroboards_thread_intelligence_clean
  php bin/console migrate:status` — every migration applied, none pending.
- 09:51:29Z `DB_TEST_DATABASE=retroboards_thread_intelligence_clean
  vendor/bin/phpunit tests/Integration/Core/AppThreadIntelligenceMigrationTest.php
  --filter test_0077_down_and_up_rehearsal_on_fixture_free_schema` — OK
  (1 test, 26 assertions). Direct migration DDL restores `0077` in `finally`.
- 09:51:30Z `APP_ENV=testing DB_DATABASE=retroboards_thread_intelligence_upgrade
  php bin/console verify:upgrade --force` — PASS (17/17 checks).

## Backup and Restore

`tests/backup/rehearse.sh` retains its whole-table row-count/checksum comparison
and additionally requires nonzero source and restored counts for:

- `thread_intelligence_jobs`;
- `thread_intelligence_generations`;
- published `thread_summaries.kind='ai'` rows;
- `thread_summary_sources` belonging to AI summaries; and
- selected AI relationship overlays.

The source is seeded through the same deterministic real-worker fixture used by
Playwright. Recorded 2026-07-12 09:55:53–09:56:17 UTC:
`tests/backup/rehearse.sh` — REHEARSAL PASSED. 285132-byte dump, 116 tables,
744 rows; every table's row count and checksum matched after restore; the TI
lifecycle matched source/restored (12 jobs, 18 generations, 12 `kind='ai'`
summaries, 96 AI citations, 8 selected AI overlays); restored `migrate` was a
no-op; `repair` ran clean; the restored app booted and served seeded content
(HTTP 200).

## Pre-Flip Regression

Recorded 2026-07-12 12:17–12:29 UTC in one Git Bash shell with
`OPENSSL_CONF=C:\Program Files\Git\usr\ssl\openssl.cnf` and
`PHP_INI_SCAN_DIR` pointed at the worktree's `storage/cache`:

- `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` — OK, 2172 tests, 12542
  assertions, 1 skipped.
- `vendor/bin/phpunit --no-progress` — OK, 2172 tests, 12542 assertions, 1
  skipped.

The identical counts validate the pre-flip baseline. The assertion total is
the observed worktree result and supersedes the earlier 12155 estimate.

## Default-On Focused Regression

After changing only `community_memory` and `automated_context` to `true`, the
flag, carryover, deterministic-context, related-worker, complete Thread
Intelligence, and admin suites passed on 2026-07-12: 224 tests, 1744 assertions.
The new zero-override liveness canaries and independent explicit-false rollback
tests are included in that count.

## Post-Flip Final Verification

Recorded 2026-07-12 on the same Windows host:

- `APP_ENV=testing DB_DATABASE=retroboards_thread_intelligence_final php
  bin/console verify:upgrade --force` — PASS (17/17 checks).
- `tests/backup/rehearse.sh` — REHEARSAL PASSED: 116 tables, 744 rows, whole-
  table counts/checksums and the Thread Intelligence lifecycle matched after
  restore; migrate was a no-op, repair was clean, and the restored app returned
  HTTP 200.
- `npm run evidence` — 83 passed / 1 intentionally skipped across desktop and
  mobile; all 12 Thread Intelligence journeys passed. The shared-database
  webhook evidence now removes each project-local endpoint before closing its
  receiver so later projects cannot retry a stale endpoint.
- `npm run a11y` — 28 passed in the main accessibility set, followed by 4
  passed in the Thread Intelligence no-JS/axe subset.
- In one Git Bash shell with the crypto environment from the pre-flip gate,
  `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` ran 13:05:07–13:11:07 UTC
  and `vendor/bin/phpunit --no-progress` ran 13:11:08–13:17:02 UTC. Both were
  OK at **2177 tests, 12564 assertions, 1 skipped**.

The identical fresh/reused counts close the final double-suite requirement and
authorize `default_on: complete` in the evidence index.
