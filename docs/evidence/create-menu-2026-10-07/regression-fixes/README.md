# Review remediation — 2026-10-07

All three review findings are fixed in the uncommitted working tree based on
`f8d4350ff417f3a8d68d7870fdc57947104a5643`:

1. The Messages compose panel renders once beside the room, as a direct main
   child. It remains visible when the list pane is hidden at 900px and below.
   Its existing Messages colors apply outside the room as well.
2. Recipient blur closes suggestions, clears the debounce, aborts the request,
   and invalidates responses already being processed. Typed recipients, selected
   chips and message drafts survive dismissal. Escape first closes suggestions
   in the recipient input, then closes the dialog; after Tab it closes the dialog
   immediately. Mouse and touch selection preserve input focus. The existing
   `mousedown` handling is retained; cancelling `pointerdown` suppressed WebKit's
   compatibility click and was removed after a failing touch test.
3. Shared breadcrumbs wrap long board names within the leading column while
   retaining the full text, existing typography, gutters and 44px create target.

The PHP structure/422 contracts are in
[`AppCreateMenuTest.php`](../../../../tests/Integration/Core/AppCreateMenuTest.php).
The browser regressions are in
[`create-menu-regressions.spec.ts`](../../../../tests/browser/create-menu-regressions.spec.ts).
The normal `evidence` and `evidence:chrome` scripts run this suite in a separate
prepared database group so its deliberately long board fixtures cannot affect
other evidence suites.

## Final verification

The full fresh PHP run passed: **3,141 tests, 24,076 assertions, one skip**.
The skipped migration 0077 down/up rehearsal requires its separate
`retroboards_thread_intelligence_clean` database.

The focused browser suite passed **12 tests in Chromium and 12 in WebKit**,
with no failures or skips. It exercises all three Messages routes at 320, 390,
860, 861, 900, 901 and 1440px across the matching desktop/touch projects,
light/normal and dark/Large appearance, visible modal bounds, Tab containment,
Escape and focus restoration, Cancel, body/chip draft retention, recipient blur,
aborted and uncancellable late responses, mouse/tap selection, themed chip and
selected-option colors, scoped modal WCAG axe checks, and native validation.
Long-name checks use 34, 42 and 80 characters at 320, 390, 861 and 1440px,
as guests and members in both appearance variants.

The final Chromium header/compose, Messages audit/polling, and delayed recipient
commit group passed **80 tests**, with 44 intentional desktop/mobile project
skips. WebKit's existing delayed Enter/comma recipient-commit regression passed
one test, with its intentional mobile duplicate skipped.

Both complete shared-row matrices were repeated on the final source:

| Browser | Passed | Known native failures | Project skips | Route checks | Unique required combinations |
| --- | ---: | ---: | ---: | ---: | ---: |
| Chromium | 18 | 2 | 8 | 2,801 | 2,688 |
| WebKit | 18 | 2 | 8 | 2,801 | 2,688 |

All 5,376 required combinations completed. The two failing tests per browser
remain the native representative-capture and native member-matrix tests.
Each browser has eight failed geometry rows for six unique native Large-text
conversation cases at 320, 390 and 860px. Their failure signatures match the
original evidence; they have no horizontal overflow and Send remains fully
visible. Every other assertion passed. Both CLI runs exit 1, retaining these
failures. See [`results.json`](results.json) and the four browser/project JSON
files for the final geometry records. Only the focused captures are retained
from these reruns; the original complete matrix captures remain in the parent
folder.

The asset worker suite passed **24 tests**. Imladris/runtime and fingerprinted
asset freshness, JavaScript syntax, PHP template syntax and diff checks pass.
Generated assets and their application digest were refreshed after the final
touch correction. The final source hashes and delivery version are in
[`source-snapshot.json`](source-snapshot.json); sanitized outputs are in
[`validation/`](validation/).

The original evidence folder retains the pre-remediation source snapshot,
captures, logs and untouched-HEAD comparisons. They are historical evidence;
this folder records the repaired source. Red tests for the original regressions,
the moved dialog's token inheritance, and WebKit touch selection remain in
`validation/red-*.log` beside the final passing runs.

## Rendered evidence

Twenty-four focused captures are retained under `<browser>/<desktop|mobile>/`.
Representative visual inspection found the dialog controls visible, themed chips
readable with their borders/removal controls, full long breadcrumbs clear of the
create target, and typed native validation drafts retained:

- [WebKit phone dialog](webkit/mobile/conversation-compose-dialog.png): 390×844
  CSS px, dark/Large, with a restored unsent draft and visible Close/Cancel/Send.
- [WebKit light recipient chip](webkit/mobile/recipient-chip-light.png) and
  [dark recipient chip](webkit/mobile/recipient-chip-dark.png): 390×844 CSS px,
  Large text. The dark image shows the beginning of the draft preview within
  the dialog's scroll view.
- [WebKit phone long breadcrumb](webkit/mobile/long-topic-breadcrumb.png): full
  80-character name wraps in three lines, dark/Large, 390×844 CSS px.
- [Chromium native draft](chromium/mobile/native-validation-draft.png): the
  standalone New message page preserves an invalid recipient and typed body
  without application JavaScript, 390×844 CSS px.

Touch-project PNGs use 2× raster resolution. Desktop dialog and breadcrumb
captures use 1440×844 CSS px. The native validation test deliberately uses
390×844 CSS px in both projects. Screenshot labels describe the captured state;
the broader width/theme/account combinations are asserted by the tests.

## Limits and reproduction

Chromium and WebKit are automated desktop/touch browser lanes, not physical
device checks. Production has not been deployed or independently verified.
The existing strict native Large-text conversation viewport failures remain
explicit in the broader matrix. The original
[untouched-HEAD comparison](../baseline/native-room-comparison.json) records
the same affected native lanes with greater overflow before this change.

Breadcrumb assertions cover the shared leading column on both board/topic
pages and full-page horizontal bounds on topics. The board slab's separate
heading already overflows with an unbroken long token on untouched HEAD; that
existing heading behavior is outside the reported breadcrumb regression.

Runs use disposable `retroboards_e2e_*` databases, isolated ports, rate-limit
stores and package stores. Prepare a private database with `prepare.sh` before
each broader group. The local container is `retroboards-mariadb`; its root socket
uses `docker exec -u 0`, and `DB_RESET_CONTAINER=fix-nonexistent` bypasses the
script's differently named container reset. For focused reproduction:

```bash
cd /home/ubuntu/community-forums/tests/browser
DB_DATABASE=retroboards_e2e_create_fix_chromium_target E2E_PORT=8039 \
  RB_EVIDENCE_DIR=/tmp/create-fix-chromium npx playwright test create-menu-regressions.spec.ts
DB_DATABASE=retroboards_e2e_create_fix_webkit_target E2E_PORT=8040 \
  E2E_LAYOUT_BROWSER=webkit RB_EVIDENCE_DIR=/tmp/create-fix-webkit \
  npx playwright test create-menu-regressions.spec.ts
```

The fixes add no schema changes or new global-shell reads. Original unrelated
work is preserved. No commit, push or deployment was performed.
