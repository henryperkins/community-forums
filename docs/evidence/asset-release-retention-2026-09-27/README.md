# Layout failure across asset releases — 2026-09-27

Prepared locally against `e4bc0acd`; this record does not claim a production
deployment or a physical iPhone test. Implementation decision: ADR 0041.
The [review correction](#review-correction--pin-the-deployed-baseline) below
supersedes the initial policy of advancing retention on each distinct build.

## Reproduction and cause

The user supplied a live iPhone screenshot showing the skip link as ordinary
text, duplicate rail controls, and the board rail above the directory. At
19:37 UTC the live page at `https://forum.candidary.online/` referenced the
current stylesheet and rendered correctly in Chromium and WebKit. The actual
phone's request trace was unavailable.

Direct live requests established:

| Asset | Status |
| --- | --- |
| Current `app-style-C45PPeLE.css` | 200, exact local SHA-256 |
| Previous `app-style-CF_djGmG.css` | 404 |
| Two releases back `app-style-DNsShsBb.css` | 404 |
| Current/previous `imladris-style-CU9grDIH.css` | 200, exact local SHA-256 |
| Two releases back `imladris-style-Cw9wS_6P.css` | 404 |

A read-only Playwright interception changed the live guest HTML's application
stylesheet reference to the preceding release's URL. The real live asset
response was 404. WebKit then reproduced the screenshot's failure pattern:
body margin 8px, static skip link, static board rail, and main content beginning
at y=545px. [Reproduction screenshot](reproduction-live-previous-webkit.png).
This proves a matching deployment failure mechanism, not the missing phone
network trace. The current stylesheet restores margin 0, the offscreen skip
link, fixed mobile drawer, and main content starting at y=62px.

The previous builder cleared every old fingerprinted file, and the Worker
rejected paths absent from its current manifest. The fix retains exact files
for the current and two preceding asset releases, including lazy dependencies.
Four old files were recovered from Git; the current CSS and JS remain byte-for-
byte unchanged. The runtime Imladris baseline was explicitly reconciled to
include those retained delivery files.

## Initial verification

| Check | Result |
| --- | --- |
| Build regression before fix | Failed on release 2 dropping release 1's stylesheet; [red output](retention-red.txt) |
| Asset tests | 16 passed; [output](asset-tests.txt) |
| Full `composer test -- --display-skipped` | 3,111 tests, 23,590 assertions, no failures, one expected skip; [output](phpunit.txt) |
| `composer verify:imladris` | 24 tests, 305 assertions; [output](imladris.txt) |
| `npm run check:assets` | Current output and manifest |
| Docker assets stage | Built successfully; manifest identical to host; all 54 assets match their hashes in both output directories |
| Chromium browser checks | 12 passed, desktop and mobile |
| WebKit browser checks | 12 passed, desktop and mobile |
| `git diff --check`, Node syntax checks | Passed |

The PHP skip is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`,
which requires its dedicated destructive migration rehearsal. The first PHP
run identified the expected Imladris digest change from restored delivery files;
the full suite above ran after that explicit reconciliation.

The build test runs four real successive builds, checks the complete retained
graph, verifies old files are removed after the window, verifies repeat-build
stability and `--check`, upgrades a legacy manifest, and refuses altered bytes
or private paths. The runtime test serves retained files through a real Workers
Assets binding, verifying status, cache policy, and exact bytes.

## Browser evidence

Browser plugin unavailable; used the repository's Playwright 1.61.1 harness.
PHP 8.4.25 and local MariaDB serve `http://localhost:8041` against the isolated
`retroboards_layout_e2e` database. PHPUnit uses `retroboards_layout_test`.
Desktop is 1280×800; mobile is 390×844 at device scale factor 2. No live login or
production content mutation was performed.

Flow: sign in → load the board directory with current, previous, or two-release-
old asset references → open/close the mobile rail or native account disclosure.
Actual local files answer every asset request; only HTML references are varied.

| Browser contract | Result |
| --- | --- |
| Page identity and meaningful content | HTTP 200, signed-in identity and board-directory heading |
| Framework/error overlay | Real server-rendered content; no runtime errors |
| Console and assets | No page errors, console warnings/errors, or failed asset responses |
| Geometry | Hidden skip link, zero body margin, 62px header, no horizontal overflow |
| Mobile with JavaScript | Closed fixed rail, content begins below header, exactly one menu control, drawer opens/closes |
| Mobile without JavaScript | Existing intentional stacked-rail fallback, no dead menu controls, native account menu works |
| Desktop | Grid shell, sticky rail, account disclosure works |

Previous-release captures:

- [WebKit mobile](mobile-webkit-js.png) · [WebKit desktop](desktop-webkit-js.png)
- [Chromium mobile](mobile-chromium-js.png) · [Chromium desktop](desktop-chromium-js.png)
- [WebKit mobile without JS](mobile-webkit-no-js.png) · [WebKit desktop without JS](desktop-webkit-no-js.png)
- [Chromium mobile without JS](mobile-chromium-no-js.png) · [Chromium desktop without JS](desktop-chromium-no-js.png)

Run the browser spec from `tests/browser` with the dedicated DB, port, rate-limit
and package-store environment used by the harness:

```sh
npx playwright test asset-release-layout.spec.ts
E2E_LAYOUT_BROWSER=webkit npx playwright test asset-release-layout.spec.ts
```

The retention window is bounded. Older tabs beyond two intervening asset
releases may require a reload; rolling back outside the retained window still
requires matching the Worker assets to the container's HTML. A production
release must verify the recovered old CSS URLs return 200 as well as the current
page, and should be followed by a reload on the reporting iPhone.

## Review correction — pin the deployed baseline

Review reproduced a gap in the initial implementation: three local edits and
builds, without any deployment, evicted the live release's stylesheet. The new
regression test failed against that builder with `Build dropped a deployed page
dependency` ([failure output](baseline-red.txt)).

Independent review also reproduced lost retained files after a failed output
write. The added fault-injection test failed with an absent deployed file
([failure output](baseline-publication-red.txt)). Publication now stages both
complete asset trees, preserves directory backups during installation, and
commits the manifest last. Caught installation failures restore the prior
directories; failed rollback preserves the backup locations for recovery.
The real Docker build then exposed an overlayfs `EXDEV` when renaming a copied
asset directory. A complete-backup-copy fallback passed both the Docker build
and injected success/rollback cases ([regression failure](baseline-overlayfs-red.txt)).

`config/assets.json.deployedReleases` now pins three deployed versions. Normal
builds replace only the current candidate and preserve every pin, including
their complete lazy-module and font graphs. Storage is bounded to the candidate
plus those three versions. Reverting source files can reproduce the baseline
without recovering deleted assets. Old manifests seed the baseline once.

Only `npm run assets:record-release -- <verified-deployed-version>` advances the
baseline, after a successful deployment is verified externally. It rejects a
version that differs from current sources or the previously built manifest,
and cannot be combined with `--check`. Commit its output before the next
release. Recording the same version twice is idempotent. This local correction
did not record a new production deployment; the asset version remains
`dd4fdf0fe62cc55a` and all 54 published file hashes remain unchanged.

Validation after the correction used isolated local databases
`retroboards_asset_fix_4thvkx_test` and `retroboards_asset_fix_4thvkx_e2e`, with
the browser server on `http://localhost:8142`. The original logs and captures
above are preserved.

| Check | Result |
| --- | --- |
| Asset suite | 23 passed, including repeated changed builds, source reverts, explicit retirement, legacy upgrades, corrupt/private assets, stale-version refusal, recording idempotency, retries after failed writes/directory installs/manifest installs, and overlayfs publication/rollback; [output](baseline-asset-tests.txt) |
| Full `composer test -- --display-skipped` | 3,111 tests, 23,590 assertions, no failures, the same one expected migration-rehearsal skip; [output](baseline-phpunit.txt) |
| Browser layout checks | 12 Chromium and 12 WebKit checks passed across desktop/mobile and JS on/off; [result lines](baseline-browser-tests.txt) |
| Docker assets stage | Built successfully; manifest identical to host and all 54 hashes verified in both delivery directories; [output](baseline-docker-parity.txt) |
| `npm run check:assets` | Generated output and manifest current; [output](baseline-check-assets.txt) |
| `composer check:imladris`, Node syntax, `git diff --check` | Passed |

## Integration with current main — `0f7b7486`

Before committing this change, `main` advanced with the profile follow-up in
`0f7b7486`. Its [Workers Build](https://dash.cloudflare.com/a77e479f6736120eadd99973dbeb705e/workers/services/view/retroboards/production/builds/9ed0c3d1-f726-4b7e-bee8-0a54813028d7)
completed successfully, and all 50 live asset hashes matched that commit's
manifest ([hash results](integration/upstream-live-assets.json)). The checkout
was fast-forwarded while preserving the retention fixes, then its already-live
asset version `82a41e8eb74b03dc` was explicitly recorded.

The final deployed baseline is `82a41e8eb74b03dc` (`0f7b7486`),
`dd4fdf0fe62cc55a` (`e4bc0acd`), and `125b5d8c76e46574` (`f2f616de`).
Its 52 files include the latest profile styles and two restored application
stylesheets; the oldest original pin was retired only after verifying the new
deployment. The earlier 54-file results and captures remain historical evidence
of the initial fix. The final application surface digest is
`0101fb084bc2af6566b74c51d973a4ba265665ccbb866dabfdb071970a81fbb0`.

Fresh validation used isolated local databases
`retroboards_asset_release_n6h7t5_test` and
`retroboards_asset_release_n6h7t5_e2e`, with the browser server on port 8143.

| Check | Result |
| --- | --- |
| Full PHPUnit suite | 3,112 tests, 23,592 assertions, no failures, the same one expected migration-rehearsal skip; [output](integration/phpunit.txt) |
| Asset suite | 23 passed; [output](integration/asset-tests.txt) |
| Browser layout | 12 Chromium and 12 WebKit checks passed, desktop/mobile and JS on/off; [results](integration/browser-tests.txt), [WebKit mobile](integration/mobile-webkit-js.png), [WebKit desktop](integration/desktop-webkit-js.png) |
| Docker assets stage | Build succeeded; manifest identical to host and all 52 files match in both output trees; [output](integration/docker-parity.txt) |
| Generated assets and Imladris | [Asset check](integration/check-assets.txt), [Imladris check](integration/check-imladris.txt), and [24 Imladris tests](integration/verify-imladris.txt) passed |

These integration checks precede publication of the retention fix itself.
