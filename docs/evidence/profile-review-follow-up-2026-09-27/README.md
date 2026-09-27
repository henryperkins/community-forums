# Profile review follow-up — 2026-09-27

A verification of `e4bc0acd` reproduced [the correction record](../profile-review-fixes-2026-09-27/README.md)
and found six gaps. This record covers their correction, described in
[ADR 0040](../../adr/0040-profile-system-components-and-recent-activity.md#2026-09-27-correction-follow-up).
It records the verification of the commit that lands on `main` and does not claim a deployment.

## Corrections

| Gap | Correction | Pinned by |
| --- | --- | --- |
| Tablet titles | At 820–834px the details card sits beside the main column, each title gets about 280px, and every fixture title was clipped. Titles now also wrap under `(hover: none), (pointer: coarse)`. A mouse keeps the one-line title and its tooltip. | `profile-surface.spec.ts`: touch tablets wrap activity titles while a mouse keeps one line |
| "1 posts" | The visually hidden unit after a "Most active in" count is singular for one post. | `AppProfileActivityTest::test_most_active_in_speaks_a_singular_or_plural_post_unit` |
| Spec drift | COMMUNITY §6 (v0.7) describes badge descriptions as native disclosures, not hover tooltips. | Document change |
| Badge reflow | The label aligns to the chips' text baseline, and a description wraps at its chip's width in the profile's secondary copy size. | The no-JavaScript badge check now asserts that the label and the neighbouring chips keep their page positions |
| Guest link target | The Log in link takes the cover's centred 44px `::after` at 860px and below. | The phone stat check now counts four 44px targets |
| Changelog | The profile section records the review corrections and this follow-up. | Document change |

## Verification

| Check | Result |
| --- | --- |
| PHPUnit, four shards on private databases | 3,112 tests, 23,592 assertions, no failures, one expected skip |
| Profile integration suite | 28 tests, 373 assertions |
| `profile-surface.spec.ts`, desktop and mobile | 28 passed |
| `npm run check:assets` / `npm run test:assets` | Current; 15 passed |
| `composer check:imladris` | Current after the digest refresh |
| `git diff --check` | Clean |

Templates, `app.css`, and COMMUNITY.md all feed the application digest. Before the refresh the
only failure was `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources`.
The landing commit refreshes `config/imladris-runtime-baseline.json` and the manifest to
`816e6d020fa9a7e0deb9df9e71835f1e3143b603e4bc32ed9125efdc2d582a45`, as `e4bc0acd` did, and the
suite above ran after that refresh. The skip is the destructive migration rehearsal, as in the
earlier record.

Browser environment: PHP 8.4.25, local MariaDB, Chromium through Playwright 1.61.1, an
isolated database and port. No physical-device, WebKit, or Firefox verification is claimed.

## Capture method

Chromium drops touch emulation during a full-page screenshot and for the rest of that page:
`(hover: none)` and `(pointer: coarse)` stop matching, so a full-page capture of a touch state
renders the mouse rules. The tablet captures therefore use a fresh 820×1180 touch context per
theme and a viewport screenshot that ends at the list, and the test asserts the wrap again after
capturing. The profile had no pointer-keyed rules before this change, so the earlier profile
captures are unaffected.

## Captures

- [Tablet 820px, touch, light](tablet/review-activity-820-light.png) and [dark](tablet/review-activity-820-dark.png)
- [Open badge, desktop, no JavaScript](desktop/review-badge-no-js.png)
- [Open badge, phone, no JavaScript](mobile/review-badge-no-js.png)

[results.json](results.json) records the checks and capture hashes.

## Reproduction

Create the private databases with the local development account, then:

```sh
DB_TEST_DATABASE=<private test database> MAIL_DRIVER=sendmail MAIL_FROM='' \
  vendor/bin/phpunit tests/Integration/Core/AppProfileActivityTest.php
npm run check:assets
npm run test:assets

DB_DATABASE=<private e2e database> RATELIMIT_PATH=<scratch> PACKAGES_STORAGE_PATH=<scratch> \
  bash tests/browser/prepare.sh
cd tests/browser
DB_DATABASE=<private e2e database> E2E_PORT=<free port> RATELIMIT_PATH=<scratch> \
  PACKAGES_STORAGE_PATH=<scratch> RB_EVIDENCE_DIR=<scratch> \
  npx playwright test 'browser/profile-surface\.spec\.ts'
```
