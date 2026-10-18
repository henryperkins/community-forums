# DKIM selector configuration and cache regression

Local verification on 2026-10-18 passed. The configured selector is `cf2024-1`; a cached result for `default` is ignored until the operator refreshes the domain status. The generic application default remains `default`, and an explicit site selector setting still takes precedence over environment configuration.

## Browser evidence

Playwright CLI drove the real PHP application at `http://localhost:8078` against the private `retroboards_e2e_dkim_20261018` database. Desktop Chromium used a 1280×800 viewport; mobile Chromium emulation used 390×844 with touch and a 2× device scale. No CSS or template changes were made.

Both viewport flows passed these checks:

1. Seed an old `default` cache with SPF pass and DKIM fail. GET `/admin/email` displays selector `cf2024-1`, SPF/DKIM unknown, no old checked date, and blocked sending while verified-domain enforcement is enabled. The GET leaves the old cache and refresh audit count unchanged.
2. Submit the actual CSRF-protected **Refresh SPF/DKIM status** form. The page displays SPF pass, DKIM pass, and verified. The database stores selector `cf2024-1`, DKIM host `cf2024-1._domainkey.example.test`, and one new refresh audit row. A fresh GET retains the verified state.
3. Seed an old `default` cache with SPF/DKIM pass. The next GET still displays `cf2024-1` as unknown and blocks sending; the wrong selector's passing cache cannot establish readiness. The GET leaves that old cache untouched.
4. `email_deliveries` remains empty throughout. Neither flow clicks **Send test email**. No document-level horizontal overflow is present in the captured states.

[browser-results.json](browser-results.json) records the assertions' DOM values and persisted state. Screenshots and accessibility snapshots are paired under [desktop](desktop/) and [mobile](mobile/) for `stale-failed-unknown`, `refreshed-verified`, and `stale-passed-blocked`. Desktop verified and mobile blocked screenshots were visually inspected.

DNS results are synthetic, stored through the existing `email_dns_txt_records` fixture setting. Mail uses the in-memory `array` transport and `dkim-evidence@example.test`. This is local browser evidence; it does not establish real DNS, mail delivery, physical-device behavior, or a live deployment.

## Other verification

- Full local PHPUnit suite: **3,236 tests, 25,678 assertions, one skip**, exit 0, 01:38.096. Exact output: [phpunit.log](phpunit.log).
- Focused PHPUnit regression: **35 tests, 263 assertions**, passed. The pre-fix run had the three intended failures for stale failed cache, stale passing cache, and missing production selector configuration.
- `composer check:imladris` passed. Generic-default and explicit-environment configuration smoke checks passed. Independent code review found no actionable findings. `git diff --check` passed.

## Production release

Application commit `06a05e3bbd69f1a96b62ff305dbe9a55f8f49157` was pushed to
`main`. Both automatic deployment lanes completed for that exact commit:
Cloud Build `4086757a-06f5-435f-8008-2e0b8c8e7731` and Workers Build
`1a8f86c1-f2d9-4086-b250-0515637b3922` succeeded. The Cloud Run service and
all four scheduled jobs use that image. A targeted environment update applied
`MAIL_DKIM_SELECTOR=cf2024-1` to all five resources, preserving their images
and other configuration. Revision `retroboards-00029-2b5` serves 100% of traffic.

Execution `retroboards-cron-5m-vwdzq` performed an authenticated public HTTPS
GET of `/admin/email`, submitted its native CSRF-protected domain refresh form
(HTTP 303), then independently fetched the same public page (HTTP 200). The
final HTML reported selector `cf2024-1`, SPF pass, DKIM pass, verified readiness,
and a checked timestamp of 2026-10-18 03:44 UTC. The probe required the DNS fixture
setting to be absent/empty and used real runtime DNS. It created one operator
session for the unambiguous active protected owner, shortened its expiry to five
minutes, and confirmed revocation afterward. Cookies, CSRF tokens, credentials
and raw admin HTML were not logged. No test email was sent by this probe.

Public health returned HTTP 200 with application/database `ok`; all 55 live
asset hashes matched manifest `2371e926206bda24`. The deployed asset-retention
record and generated-asset check completed without changing the manifest.

Sanitized receipts: [live admin refresh](production/live-admin-refresh.json),
[Cloud Run and build state](production/after-runtime-selector-gcp.json),
[runtime setting changes](production/runtime-update.json),
[Worker release](production/worker-release-final.json),
[image digest](production/revision-image-digest-final.json), and
[live assets and health](production/live-assets.json).

## Reproduce the focused browser flow

Prerequisites: installed application dependencies, an installed Chromium browser, Playwright CLI wrapper from the Playwright skill, and an existing empty local disposable database with a name matching `retroboards_e2e_dkim_*`. The fixture refuses other database names, non-local hosts, database sockets, non-test application mode, other mail transports/senders, or another configured selector. Credentials are loaded privately through the normal `.env` loader.

From the repository root, migrate the chosen private database, then run the capture script:

```bash
APP_ENV=test DB_DATABASE=retroboards_e2e_dkim_20261018 \
  MAIL_DRIVER=array MAIL_FROM=dkim-evidence@example.test MAIL_DKIM_SELECTOR=cf2024-1 \
  php bin/console migrate

DB_DATABASE=retroboards_e2e_dkim_20261018 \
  node docs/evidence/email-dkim-selector-2026-10-18/capture.cjs
```

`capture.cjs` starts and stops its own PHP server on port 8078, refuses an occupied port, uses separate rate-limit/package directories, and closes both of its browser sessions. `E2E_PORT`, `E2E_CHROMIUM_PATH`, and `PLAYWRIGHT_CLI_WRAPPER` can select another local port, installed Chromium executable, or skill-wrapper location. Temporary CLI configuration/snapshots go under `output/playwright/email-dkim-selector-20261018`; the requested evidence is written here. The runner does not reset or drop a database.
