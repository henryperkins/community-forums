# Unified member chrome — evidence (ADR 0032)

Captured by `tests/browser/unified-chrome.spec.ts`
(`cd tests/browser && npm run evidence:chrome`), 13 tests × `desktop` (1280×800) and
`mobile` (390×844 @2x); five cases skip outside their target viewport.

## Why this directory exists

The topbar and the rail are the design system's `ForumNav` and `BoardRail`, rendered by
`partials/topbar.php` and `partials/sidebar.php` in the design's own class vocabulary and
styled by the layered `/assets/imladris.css`. The 2026-08-27 transfer copies of that
chrome are gone from `app.css`. A port like that can fail silently in exactly one way: an
unlayered rule left behind in `app.css` beats the layer's regardless of specificity, so
the design's value never renders while every class is present and correct. The first
capture of this spec found two such leaks — `a { color }` painting every rail item
evergreen, and the `h2` display rule blowing the roster's "Online" label up to 1.75rem —
which `app.css`'s "Member chrome" block now hands back to the layer with `revert-layer`.

## Frames

| File | Shows |
|---|---|
| `desktop/01-chrome-light.png` | The bar and the rail on `/`: lockup, surface pills with the active one in the brand wash, the search pill, the rail toggle, New topic, the seat with its leaf; the rail's categories, rows, the private tag, and the roster in the footer slot. |
| `desktop/02-chrome-dark.png` | The same in twilight — the mark, the pills and the leaf re-theme through tokens. |
| `desktop/04-rail-closed.png` | `⌘B`: the rail hidden and the toggle's band unfilled, persisted across reload. |
| `desktop/05-guest.png` | A guest: the design's Log in pill, with production's Sign up beside it. |
| `mobile/01-chrome-light.png`, `02-chrome-dark.png` | The phone bar packed to one row: hamburger, mark, pills, the 40px search glyph, the compose glyph, the seat. |
| `mobile/03-drawer-open.png` | The rail as the off-canvas drawer behind the hamburger, roster included. |
| `mobile/05-guest.png` | The guest bar on a phone. |
| `desktop/08-long-account-name.png` | A saved 40-character display name fits at 1100px, with the account menu open and its keyboard focus ring visible. |
| `mobile/08-long-account-name.png` | The same account's native menu and focus ring stay within the phone viewport. |
| `desktop/09-unread-counts.png`, `mobile/09-unread-counts.png` | After three previews, a real 102-topic unread queue shows 99 in the topbar, rail, and Unread filter tally. |
| `desktop/10-inbox-menu.png`, `mobile/10-inbox-menu.png` | Additional rendered spot check: a visible scope menu at 924px and 390px, with the account control still reachable and no horizontal overflow. |

## What the spec measures, and why it measures rather than matches

- the bar is 62px and the rail 272px, from the layer, and the active board carries the
  design's **2px left rule**, not the retired 3px inset shadow;
- the house mark's colour **changes between registers** (inline SVG on `currentColor`);
- the seat's leaf **has a box** and takes a **different colour with `.is-away`**, now that
  the topbar has no unlayered dot rule of its own; the profile dot still does the same;
- a rail row with avatars off keeps a **bare dot with a box**;
- a rail row hovers with **no underline** and "See everyone online" **changes colour** on
  hover — the two drifts the transfer bridge had forced;
- `⌘B` hides the rail, unfills the toggle's band, and **survives a reload**; on a phone the
  hamburger opens the drawer and the persisted toggle is not shown;
- a guest sees **Log in** and **Sign up**; on a phone **New topic** survives as its glyph.
- saved 40-character and unbroken 64-character account names fit at **901, 924,
  1024, 1080, 1081, 1100, 1280, and 390px**, in both themes; the full name remains accessible, the
  avatar keeps its size, and every menu action stays visible and keyboard reachable.
- real Inbox previews keep the topbar, rail, and Unread filter tally synchronized
  across 102 → 101 → 100 → 99 and 2 → 1 → 0, with the `99+` cap, singular
  accessible labels, removal of zero badges, and the same result after reload.

## Completion review of CommunitySystem.zip

The review closes four retained behavior gaps alongside the template port:
Compose moves “posting here” when its destination changes; topic pages mark
their authorized parent board; mobile primary navigation keeps Messages in a
scrollable group; and a closed drawer is excluded from keyboard navigation,
with Escape returning focus to its opener.

The final review also closes stale unread counts, the account control shrinking
to zero at narrow desktop widths, and Inbox menus remaining hidden after
positioning. Each was reproduced by a failing browser assertion before the
fix. Scope and row menu checks now assert visibility before measuring bounds;
the retained CSS transfer carries the same specificity fix in both files.

Archive audit: 19 of 21 design files match after line-ending normalization.
Only `components.css` and `tokens/colors.css` differ, with every retained
correction recorded in `LOCAL_RECONCILIATION.md`. The archived README and all
six reference screenshots are byte-identical to the ZIP. The older component
prompt's loading/location examples remain reference-only under the README's
explicit deferrals.

The chrome spec refreshes its time-relative presence cast before each test and
restores exact previous display names, timestamps, privacy, onboarding, and preference values
in teardown after closing the browser context. Assertions cover the native
POST rail toggle with JavaScript disabled, mobile native board/Messages/account
navigation, menu bounds, the 44px drawer opener, and no horizontal page overflow.

Final verification on 2026-09-12 used PHP 8.5.4, Node 24.21.0, and the repository's
Playwright Chromium harness (Browser plugin unavailable). The automated server
ran at `http://localhost:8019` against a fresh `retroboards_chrome_review`
database; the additional rendered spot check used port 8020 with the same
fixtures. The flow was `/inbox` → open a scope menu → select another scope →
render its real queue. Page title/heading, visible content, console health,
menu visibility, navigation, and no horizontal overflow all passed at 924×800
and 390×844. The chrome and rail axe scans found no serious/critical WCAG
A/AA violations. Automated member-surface scans also covered the main content.

| Check | Final result |
|---|---|
| `MAIL_DRIVER=sendmail MAIL_FROM='' COMPOSER_PROCESS_TIMEOUT=0 composer test` | 2,780 tests, 20,343 assertions, no failures; 1 skip and 6 deprecations; 1m40s. The explicit empty sender preserves the suite's unconfigured-mail contract regardless of local `.env`. |
| `MAIL_DRIVER=sendmail MAIL_FROM='' composer verify:imladris` | Generated assets current; 24 tests, 296 assertions passed. |
| `unified-chrome.spec.ts` | 21 passed, 5 viewport skips. |
| `users-online-remediation.spec.ts` | 32 passed. |
| `member-surfaces.spec.ts` | 12 passed, 2 viewport skips. |
| `board-index-remediation.spec.ts` | 16 passed. |
| `forum-inbox-remediation.spec.ts` | 24 passed. |
| Combined browser run | 105 passed, 7 viewport skips, no failures; 1.5 minutes. |
| PHP/JS syntax and `git diff --check` | Passed. |

The five browser specs were run together after `npm run prepare-db`, with
`E2E_PORT=8019`, `DB_DATABASE=retroboards_chrome_review`, and separate temporary
rate-limit/package directories. The unread regression's temporary accounts and
boards were confirmed absent afterward. The review database, its scoped grant,
and the port-8020 inspection server were removed after verification; the
pre-existing development server and its database were left intact. Existing
evidence outside the five tested surface directories was preserved.

The full suite still reports six PHP deprecations and one skipped test; this
review does not claim those are resolved. The browser results cover Chromium,
not an additional Safari/Firefox pass. The intentional production adaptations
and deferred features remain in ADR 0032.

Additional frames:

| File | Shows |
|---|---|
| `desktop/06-rail-closed-nojs.png` | The persisted rail setting survives a real POST, reload, and travel to Inbox with scripting disabled. |
| `mobile/07-account-menu-nojs.png` | The native account disclosure and Settings link remain reachable and within the phone viewport. |

Presence-page captures in `../imladris-users-online-remediation/` wait for fonts
and the 200ms entry animation before capture, avoiding a partially transparent
frame. Supplied reference screenshots remain under
`../../design-system/imladris/_archive/design_handoff_presence/screenshots/`.
The implementation's deliberate adaptations and deferred features are listed
in [ADR 0032](../../adr/0032-unified-member-chrome.md#feature-gap-accounting).
