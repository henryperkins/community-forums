# Phase 2-4 Completion Evidence

Updated: 2026-06-30

## Implemented Slices

- Email retry/backoff: migration `0063`, `worker:email` retry stats, `/admin/email`
  attempt metadata, CSV export metadata, terminal `failed` after exhausted
  attempts.
- Member-facing appeals: eligible deleted/moderated actions render no-JS forms on
  `/appeals` and post to the existing appeal routes.
- Split/merge counters: touched thread/board counters are maintained inside the
  split/merge transaction; global repair remains rehearsal/repair tooling.
- Server drafts: migration `0064`, dark `server_drafts` flag, JSON
  load/save/discard, no-JS listing/discard, 90-day retention, 50-draft quota,
  `worker:drafts` expired-row cleanup, conflict UI, account export, and account
  purge coverage.
- Server extensions: migration `0065`, dark `server_extensions` flag, manifest
  validation, Bubblewrap fail-closed probe seam, async worker, and admin
  inspection page.

## Local Verification

```bash
./vendor/bin/phpunit tests/Integration/Worker/NotificationEmailWorkerTest.php tests/Integration/Admin/AppAdminEmailTest.php tests/Integration/Repository/EmailOpsRepositoryTest.php tests/Integration/Service/EmailOpsServiceTest.php tests/Integration/Core/AppModerationAppealsTest.php tests/Integration/Core/AppThreadSplitMergeTest.php tests/Integration/Core/AppServerDraftsTest.php tests/Integration/Core/AppAccountLifecycleTest.php tests/Unit/Extensions/ServerExtensionManifestTest.php tests/Integration/Worker/ServerExtensionWorkerTest.php tests/Integration/Admin/AppAdminExtensionsTest.php
```

Result: 61 tests / 326 assertions, green.

Additional local verification captured during closeout:

- `composer test`: 774 tests / 3044 assertions, green.
- `APP_ENV=local DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`:
  17/17 upgrade checks passed through migration `0065`.
- `DB_CONTAINER=forum-software-db-1 DB_ROOT_PASSWORD=root DB_MYSQL_CLIENT=mysql DB_MYSQLDUMP_CLIENT=mysqldump DB_PORT=3307 DB_PASSWORD=retro tests/backup/rehearse.sh`:
  105 tables / 116 rows restored, checksums matched, migrate no-op, repair
  clean, restored app returned 200.
- `cd tests/browser && npm run evidence`: 27 passed / 1 skipped.
- `cd tests/browser && npm run a11y`: 4 passed with no serious/critical axe
  violations.
- `cd tests/browser && npm run evidence:dark`: 2 passed, capturing server draft
  conflict evidence at desktop/mobile.
- Worker smokes against `retroboards_e2e`: `worker:email`, `worker:drafts`,
  `worker:extensions`, `worker:related-topics`, `worker:attachments`,
  `worker:attachment-scans`, `worker:purge-accounts`, and `worker:webhooks`
  all exited 0.

## Remaining Release Gates

The new public-facing behavior remains deploy-dark where noted. Product,
security/privacy, and formal accessibility sign-off remain external gates before
enabling any dark slice for users/operators.
