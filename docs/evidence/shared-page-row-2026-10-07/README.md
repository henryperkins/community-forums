# Shared page row — 2026-10-07

The owner's correction puts the Inbox title, unread information, Show with its
topic count, Sort, overflow and creation in one physical row. The former title
and view bands no longer occupy the list pane. The same consolidation applies
to other simple member-page headings and their applicable controls.

## Scope and behavior

- Messages identity, All/Unread and conversation search occupy the shared row.
  Search remains a native GET form inside a disclosure; its query and unread
  filter survive navigation, and enhanced instant filtering still works.
- Search identity and independent scope/order choices, standalone Notifications
  identity/history/actions, Feed view or saved-feed management, Leaderboard
  windows, and tag breadcrumb/title/follow action share creation's row.
- Compose, all thirteen account settings views, Tags, Who is at the council,
  Appeals, profile connection lists, Privacy and Email preferences use the same
  simple heading convention.
- Directory tabs and board/topic breadcrumbs already filled the row. Home,
  board and topic content headings, profile identity, conversation/New message
  headings and error status retain their content hierarchy. The home Notices
  pane retains its directory tabs and in-pane controls. Guests retain original
  headings, navigation and forms without an empty create band.

Full choices, names and count units remain accessible when narrow containers
use compact captions. Targets retain a 44px floor; native menus anchor to the
whole row to stay within the viewport. Long accepted tag and saved-feed names
wrap while Follow, Manage and creation retain their space. Long feed excerpts
and tag cards wrap without introducing document horizontal overflow.

URLs, flags, POST fields, CSRF and server write gates retain their existing
contracts. Inbox unread preview updates now find the moved topic count and
singular/plural label in the shared row. A discovered Enter-then-Tab race is
fixed by positioning an open menu before native Tab traversal; the handler
does not intercept Tab or force focus.

## Final verification

| Check | Result | Record |
| --- | --- | --- |
| Full PHPUnit | 3,147 tests, 24,340 assertions, no failures; one skip | [PHP log](validation/phpunit.log) |
| Chromium shared row, long contexts and Inbox header | 36 passed | [Chromium log](validation/chromium-layout.log) |
| WebKit same focused suites | 36 passed | [WebKit log](validation/webkit-layout.log) |
| Chromium Messages and live unread synchronization | 6 passed | [Regression log](validation/chromium-messages-chrome.log) |
| Chromium unified Notifications | 20 passed | [Notifications log](validation/chromium-notifications.log) |
| Existing creation/dialog regressions, both engines | 12 passed each, no skips | [Chromium](create-regressions/chromium/results.log), [WebKit](create-regressions/webkit/results.log) |
| Menu Tab timing, both engines | 40 focused runs passed | [Race report](menu-tab-race.md) |
| Asset worker suite | 24 passed | [Worker log](validation/asset-worker-tests.log) |
| Imladris/runtime contracts | 24 tests, 305 assertions passed | [Runtime log](validation/imladris-runtime-tests.log) |
| Generated asset freshness | Current | [Assets](validation/assets-freshness.log), [Imladris](validation/imladris-freshness.log) |

The skipped migration 0077 down/up rehearsal requires its dedicated,
fixture-free `retroboards_thread_intelligence_clean` database. The full suite
used `retroboards_test_review_dialog_20261007`.

The shared-row suites exercise 320, 390, 860, 861, 901 and 1440 CSS px; light
and dark themes; medium and Large text; enhanced and JavaScript-disabled paths;
both desktop and touch projects; one-row geometry, target height, no horizontal
overflow, native popover bounds, one main h1, full long names, Follow toggling,
query/filter/order preservation, Escape/focus restoration, menu exclusivity,
surrounding-room scroll dismissal and scoped WCAG axe checks. The integration
contracts pin header ownership, room/flash boundaries, page-limited bulk fields,
CSRF, empty results, utility routes, feature gates and guest headings.

Browser fixtures used private `retroboards_e2e_create_fix_chromium_target`,
`retroboards_e2e_create_fix_webkit_target` and
`retroboards_e2e_review_create_20261007` databases. Notifications used
`retroboards_unified_e2e_sharedrow`, which matches its separate guard. Fixture
guards were retained. These are final reconciled runs: earlier runs exposed
small-width overlap, long-name overflow and outdated heading assertions, and
an initial Notifications attempt used the wrong guarded database. Those failures
were addressed before the final runs; this is not an uninterrupted green run.

The final detector scan is not globally clean: it reports 33 existing warnings
and 195 advisories outside added lines, with no finding intersecting the added
row code and none in the new heading helper. Those baseline reports are not
rendered proof. See [detector summary](detector-summary.json).

## Rendered evidence

Final focused captures are in [Chromium](chromium/verified/) and
[WebKit](webkit/verified/). Notifications captures are in
[chromium/notifications](chromium/notifications/); live unread synchronization
is in [chromium/regressions-final](chromium/regressions-final/).
An independent visual review inspected 33 actual captures, including twenty
additional Messages/Search images at 320px and 1440px in both engines. Source
and screenshot hashes and measured row/control rectangles are in
[visual-review/capture-metadata.json](visual-review/capture-metadata.json).
No overlapping or inaccessible row control remained in that review.

- [Phone Inbox, light/Large, native](chromium/verified/chromium/mobile/inbox-390-light-large-native.png)
- [Desktop Inbox](chromium/verified/chromium/desktop/inbox-1440-light-default-js.png)
- [320px tag with an 80-character name](chromium/verified/chromium/mobile/tag-320-light-large-js.png)
- [320px saved feed, dark/Large](chromium/verified/chromium/mobile/saved-feed-320-dark-large-native.png)
- [320px Notifications](chromium/notifications/mobile/standalone-light-320.png)
- [320px Messages, dark/Large](visual-review/webkit/messages-320-dark-large.png)
- [Desktop Search](visual-review/webkit/search-1440-light-medium.png)

Touch-project PNGs use a 2× raster scale. Screenshot filenames describe the
captured state; several named suites loop through additional widths and states
before capturing. The long-name fixture intentionally makes the full title tall
at 320px; it retains the text and accessible actions rather than truncating it.

## Reproduction and delivery

Use `tests/browser` as the working directory and a freshly prepared, explicit
private `retroboards_e2e_*` database with isolated rate-limit and package paths:

```sh
E2E_LAYOUT_BROWSER=chromium npx playwright test shared-subheader-context.spec.ts shared-subheader-long-contexts.spec.ts inbox-header.spec.ts --workers=1
E2E_LAYOUT_BROWSER=webkit npx playwright test shared-subheader-context.spec.ts shared-subheader-long-contexts.spec.ts inbox-header.spec.ts --workers=1
```

Set `RB_EVIDENCE_DIR` to a fresh output directory and `E2E_PORT` to a free private
port. Prepare only the throwaway database; do not point `prepare.sh` at a member
or production database. The separate Notifications suite requires its
`retroboards_unified_e2e_*` database convention.

[Source snapshot](source-snapshot.json) records the base revision and verified
source hashes. The developer tunnel
`https://obituaries-lucky-accordance-king.trycloudflare.com` returned HTTP 200,
healthy application/database status, and byte-identical rebuilt app CSS,
app JS and Imladris CSS at asset version `049200d35635595d`; see
[preview delivery proof](preview-health-assets.json). This verifies the developer
instance at that moment. Git publication, hosted CI and production deployment
are separate evidence lanes.

Limits: installed Playwright Chromium and WebKit, rather than physical devices,
Firefox or assistive technology. Large text is the application preference;
Notifications additionally exercises its established emulated zoom captures.
The original complete create-menu matrix was not rerun here. Its previously
recorded native Large conversation-height carryover remains in
[the preceding remediation record](../create-menu-2026-10-07/regression-fixes/README.md)
and is not cleared by these focused row checks.
