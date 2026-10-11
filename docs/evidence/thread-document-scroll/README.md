# Thread document scrolling — 2026-10-07

Canonical topics use document scrolling and a bottom-sticky reply dock. The
646px reading measure remains shared by the head, stream and composer. The old
inner overflow, reserved gutter, right inset, `-23px` compensation and initial
post-fragment re-scroll are removed. Short topics retain a viewport-based
minimum height; the mobile shell grows with long topics so its header stays
sticky.

Scrollbar thumbs use `--border-strong` with a transparent track and standard
thin width. The WebKit fallback uses the same semantic ink and a transparent
border. Native focus scrolling reserves the dock's measured border-box height
plus 12px; a 24rem fallback covers ordinary native forms before scripts arrive
or when scripting is disabled. ResizeObserver includes keyboard/safe-area
padding changes.

## Verification

All browser fixtures used `retroboards_e2e_thread_scroll_20261007`, separate
from the developer instance. Full PHPUnit runs used their own private databases.

| Lane | Result |
|---|---|
| Full PHPUnit | 3,134 tests, 23,831 assertions, 1 skipped; final assets unchanged during run |
| Final Imladris verification | 24 tests, 305 assertions; runtime assets current |
| Asset build/check and Worker tests | Build/check passed; 24 Worker tests passed |
| Thread remediation + startup | 20 passed, 20 project-specific skips |
| Final new-topic submit + keyboard inset | 3 passed, 1 project-specific skip |
| Chromium scroll matrix | 27 checks passed |
| WebKit scroll matrix | 27 checks passed |
| Final native Tab matrix | 6 lanes; 42/42 desktop, 30/30 mobile, 51/45 no-JS stops; none obscured |

Each scroll matrix covers 1280×800, 390×844 and 320×844; light and dark; scripting
enabled, app/composer bundles blocked, and scripting disabled. Assertions cover
natural document overflow, no inner scroll offset, no horizontal overflow,
shared stream/dock widths, semantic scrollbar colors, sticky dock placement,
post-fragment visibility and the header remaining within its containing block.
Chromium uses wheel input. Mobile WebKit uses programmatic document scrolling
because Playwright does not support wheel input in that lane. These are browser
emulations, not physical-device or software-keyboard coverage.

The expanded-draft focus regression was observed failing before dock-aware
scroll padding and passing afterward. The delayed-startup test now checks the
painted origins, widths, dock geometry and CLS rather than requiring a naturally
flowing document's total offscreen height to remain fixed. It also verifies that
wheel input scrolls the document while the inner wrapper stays at scrollTop 0.

The broader initial browser run had 66 passes, 44 skips and 6 failures. The
startup-height assertion was updated for the new scrolling contract and its
suite passed on confirmation. Five remaining Study-test failures reproduced
with the original HEAD CSS/JS:

- Topic tools focus trap: a nested summary inside closed details is included in
  the focus cycle.
- Source-adapter quote test: it clicks Source while the empty dock is collapsed.
- Reaction label: the expected label comes from hidden `innerText`.
- No-JavaScript Study workflow: the existing test times out; native submit
  clicking was separately confirmed with its POST intercepted.
- Keyboard post actions: it expects whole-toolbar opacity 0 although the current
  toolbar contract keeps it at 1 and hides individual controls.

A separate new-topic confirmation timed out waiting for networkidle after a
successful submission. Both desktop and mobile submission cases passed on the
final focused rerun. One intermediate private-browser run was invalidated when
PHPUnit bootstrap rebuilt that throwaway database; it was discarded, the fixture
was reseeded, and the final focused run passed. No existing developer database
was reset.

Committed evidence includes the [Chromium matrix](chromium-scroll-matrix.json),
[WebKit matrix](webkit-scroll-matrix.json), and dark-theme captures at
[1280px](chromium-normal-1280-dark.png) and [390px](chromium-normal-390-dark.png).
Raw logs and remaining screenshots stay in local
`output/playwright/thread-scroll/`.
Committed screenshot baselines were restored after saving the new captures.
Those five cases were handled in the separate
[interaction repair verification](../thread-interaction-repairs/README.md).
At the initial scroll capture, the complete browser suite was not claimed green.

## Existing public preview

The public test link references the final generated delivery assets, and served
bytes match the checkout:

- CSS `app-style-CfRfJrUW.css`: SHA-256
  `d9d56379ca5e935be81f59e9e201553c6a05257d27870a1b9bb2b303bccc92cb`.
- JS `app-19fd5d5688ab4175.js`: SHA-256
  `19fd5d5688ab4175a87a3cdf7b37378716fc81ba6eeb7333f0d224356f46fee2`.

The served-byte check above was captured before the release commit. It proves
the existing developer preview; production is verified separately after push.
