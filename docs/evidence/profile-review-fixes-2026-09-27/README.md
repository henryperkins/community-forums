# Profile review corrections — 2026-09-27

F1–F15 from the profile review of `f2f616de` are implemented locally. This
record covers the source changes, regenerated assets, and real PHP browser
output. It does not claim a deployment. The original profile-system evidence
is preserved in its own directory.

## Verification

| Check | Result |
| --- | --- |
| `composer test -- --display-skipped` | Exit 0; 3,111 tests, 23,590 assertions, no failures, one expected skip |
| Profile integration suite | 27 tests, 371 assertions |
| `composer check:imladris` / `composer verify:imladris` | Current assets; 24 tests, 305 assertions |
| `npm run check:assets` / `npm run test:assets` | Current manifest and assets; 15 tests passed |
| `profile-surface.spec.ts`, desktop and mobile | 26 passed |
| `git diff --check` | Passed |

The skipped PHP test is
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
It requires its separate destructive migration-rehearsal database and was not
run for this UI correction. The final PHP run used its own process session
and returned exit 0; an earlier concurrent wrapper had exited with a
termination signal after PHPUnit printed its completed results.

The three new integration tests reproduced the missing guest invitation,
persistent block explanation, and named removal actions before implementation.
All eight new browser checks failed against the starting implementation and
passed after correction. Existing raw-HTML assertions for counts now inspect
rendered text, so adding number spans preserves their behavior checks.

Browser environment: PHP 8.4.25, local MariaDB, Chromium through Playwright
1.61.1, `http://localhost:8037`, isolated `retroboards_profile_fixes_e2e` database.
PHPUnit used `retroboards_profile_fixes_test`. The Browser plugin was unavailable;
the repository's Playwright harness was used. Viewports include 1440×900,
1160×900, 1024×900, 390×844/900, and 320×844/900. Checks cover both themes,
guest/member/owner/moderator views, and JavaScript-disabled navigation.
No physical-device, WebKit, or Firefox verification is claimed.

| Browser check | Evidence |
| --- | --- |
| Page identity and meaningful content | Route status, profile title, activity rows, and controls asserted |
| Console and response health | No unexpected warnings, errors, page exceptions, or HTTP errors in the exercised flows |
| Accessibility | No serious/critical profile axe findings in the tested states; native badge keyboard and no-JS checks |
| Interaction | Search, sort, paging, disclosures, follower removal, menu, copy link, block/unblock, reload, guest login link |
| Responsive rendering | Title/metadata geometry, search-height parity, card columns, menu bounds, and horizontal overflow assertions |
| Screenshots | Selected final captures below; fixed-position capture artifacts from earlier runs are excluded |

## Finding coverage

| ID | Result |
| --- | --- |
| F1 | Recent activity titles wrap fully at phone widths; time and disclosure stay at the first line. |
| F2 | Both follower surfaces include display name and unique handle in each removal button's accessible text. |
| F3 | The viewer's own block has a persistent cover status after reload; the other person's block is not disclosed. |
| F4 | Counts and pager numerals use JetBrains Mono with tabular figures. |
| F5 | Topics and Posts use relative time labels with exact `datetime` and UTC `title` metadata. |
| F6 | Moderator context uses the shared info callout and semantic border colour. |
| F7 | Search buttons match their field height in activity and connection toolbars. |
| F8 | Guest login copy respects community/DM flags, the target's DM preference, and recipient restrictions. The return link works without JS. |
| F9 | Website labels omit the scheme/final slash; `href` keeps the full destination. |
| F10 | Phone connection identities wrap and removal buttons move underneath. |
| F11 | “signed-in members” stays together in the private-profile card. |
| F12 | A wide details card places its sections side by side through a container query. |
| F13 | Native badge disclosures expose descriptions to keyboard, touch, and no-JS users. |
| F14 | Small badge and list commend stars render at 13px. |
| F15 | Board activity counts include a visually hidden post unit. |

The mechanical Impeccable scan found no warnings on changed lines. Its one
changed-line advisory concerns the cover note's `.93rem` type, which follows
the existing profile secondary-copy size. The full stylesheet's unrelated
findings were not treated as new defects. An independent read-only code review
found no production regressions or missing F1–F15 work.

D1/D2 and O1–O5 remain proposals or separate work, recorded in
[ADR 0040](../../adr/0040-profile-system-components-and-recent-activity.md#remaining-proposals-from-that-review).
This includes the cover's existing gold-plate conflict and the reported shared
phone top-bar clipping. No approval of those design choices is implied.

## Reproduction

Create the two throwaway databases using the local development DB account, then:

```sh
DB_TEST_DATABASE=retroboards_profile_fixes_test MAIL_DRIVER=sendmail MAIL_FROM='' \
  COMPOSER_PROCESS_TIMEOUT=0 composer test -- --display-skipped
DB_TEST_DATABASE=retroboards_profile_fixes_test composer verify:imladris
npm run check:assets
npm run test:assets

DB_DATABASE=retroboards_profile_fixes_e2e \
  RATELIMIT_PATH=/tmp/rb-profile-fixes-ratelimit \
  PACKAGES_STORAGE_PATH=/tmp/rb-profile-fixes-packages bash tests/browser/prepare.sh
cd tests/browser
DB_DATABASE=retroboards_profile_fixes_e2e E2E_PORT=8037 \
  RATELIMIT_PATH=/tmp/rb-profile-fixes-ratelimit \
  PACKAGES_STORAGE_PATH=/tmp/rb-profile-fixes-packages \
  RB_EVIDENCE_DIR=/tmp/rb-profile-fixes-final npx playwright test profile-surface.spec.ts
```

## Captures

- [Desktop guest overview](desktop/review-activity-1440-light.png)
- [Laptop details card, dark](desktop/review-activity-1024-dark.png)
- [Phone activity, light](mobile/review-activity-390-light.png)
- [320px activity, dark](mobile/review-activity-320-dark.png)
- [Phone follower cards, dark](mobile/review-own-followers-dark.png)
- [Search controls and timestamps](desktop/review-threads-light.png)
- [Moderator callout, dark](desktop/moderator-overview-dark.png)
- [Private-profile card](mobile/guest-gated-light.png)
- [Badge explanation without JavaScript](desktop/review-badge-no-js.png)
- [Persistent block explanation, phone](mobile/review-blocked-persistent-dark.png)

[results.json](results.json) records the checks and capture hashes. The final
block scenario was also rerun after adding the closed-menu captures, so the
new explanation is visible in full.
