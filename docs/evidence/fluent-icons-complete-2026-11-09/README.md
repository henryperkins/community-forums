# Every icon from the Fluent set: evidence

**Date:** 2026-11-09 (UTC) · **Base:** `11b07629` (`main`), after recording the
live release `23ca787930a7a0a7` ([record](../asset-record-23ca787930a7a0a7/README.md))
· **Decision:** [ADR 0060, Completion](../../adr/0060-fluent-system-icons-and-icon-size.md#completion-2026-11-09)
· **Asset candidate:** `276fd01648589411`

Local evidence only: PHP 8.5.4, Node 22.22.1, Playwright 1.61.1 and Chromium
149.0.7827.55 at 1280 × 800 (desktop) and 390 × 844 (mobile). WebKit, Firefox,
physical devices and manual assistive-technology testing are not covered.
Production deployment and verification are separate outcomes.

## Results

| Check | Result |
|---|---|
| `composer check:icons`, `composer check:forum`, `npm run check:assets` | Current. `deployedReleases` pins `23ca787930a7a0a7`, so the files production serves stay published |
| `composer test` | 3,675 tests and 36,329 assertions: 1 failure, `AppEmailChangeTest::test_request_keeps_the_old_contact_and_shows_pending_address`, which gets a 422 here and fails the same way on `main` ([earlier evidence](../fluent-icons-2026-11-08/README.md#limits)); 1 skipped; 8 deprecations |
| `fluent-icons-complete.spec.ts` | 11 passed; 1 skipped by design (the desktop measurements at phone width) |
| Hand-made and outlined icons, `icon-crawl.cjs` | `main`: 18 kinds of hand-made icon on 280 page loads. This change: none, and no outlined icon among 4,747 icons on 280 page loads |
| Comparison with `main`, 20 specs | 77 failing tests on both, the same tests with the same first error; none fails only on this change |
| `branding-styles.spec.ts` on browser-named databases | 6 failing tests on both, the same tests; 7 passed on both |

## What the spec checks

`tests/browser/fluent-icons-complete.spec.ts`, with data from
`tests/browser/fluent-icons-fixture.php` (its own member, one notification of
each type and, until teardown, a board members cannot post in):

- **Notification rows.** All 11 types draw their own icon (Arrow Reply,
  Comment Add, Comment, Mention, Emoji, Person Add, Ribbon, Checkmark Circle,
  Chat, Shield and Megaphone) at 16px with standard icons and 20px with
  larger ones, from artwork made for that size, filled and unstroked.
- **The composer.** The 11 formatting buttons draw the set's icons at the
  icon size, and Heading draws Text Header 2 from its 20px artwork at either
  size. Attach images, More formatting, the emoji trigger and the emoji
  dialog's Close draw Attach, Add, Emoji and Dismiss.
- **Verification emblems.** Checkmark Circle and Error Circle at 48px from the
  48px artwork, on a valid and an invalid link.
- **Marks with their own size** keep it at either icon size, drawing the
  artwork for the member's size: the topic breadcrumb's chevron (13px), the
  top bar's search (13px; the icon size once its label is hidden at 1000px),
  the online-members and profile search fields (14px), the drafts sync check
  (12px) and the lock on a board the member cannot post in (11px).
- **Pane toggles.** Panel Regular while the pane is hidden and Panel Filled
  while it is shown, at the icon size. `header-unread-marks.spec.ts` also
  checks both states in both themes for a shape change and 3:1 contrast.
- **Admin console.** The bell (18px), the way back (13px), the reorder arrows
  (14px and 12px), the tag search (14px) and the roles callout's Info (16px).
  A success flash masks Fluent's Checkmark Circle, and every console select
  shows an arrow.
- **A sweep** of 21 routes as a guest, a member and an administrator. Every
  rendered `<svg>` is a filled, unstroked Fluent icon, or the community's
  default mark, a poll bar, a branding swatch or the retired profile ornament.

## Screenshots

- Notifications: `{desktop,mobile}-notifications-{standard,large}-{light,dark}.png`.
- Composer: `{desktop,mobile}-composer-{standard,large}-{light,dark}.png`.
  At phone width only the essential formatting buttons and More show.
- Verification: `{desktop,mobile}-verify-ok.png`, `{desktop,mobile}-verify-invalid.png`.
- Pane toggles and the rail lock: `desktop-pane-toggles-{standard,large}.png`,
  `desktop-rail-lock-{standard,large}.png`.
- Admin console: `{desktop,mobile}-admin-structure.png`, `-admin-flash.png`,
  `-admin-select.png` and `-admin-roles-callout.png`.

## Before and after

`icon-crawl.cjs` makes GET requests only. It visits pages as a guest, a member
and an administrator at desktop width, and as the member and the administrator
at phone width. It follows same-origin links and loads one page per route
pattern. It reports every rendered `<svg>` that is not a Fluent icon or listed
artwork, and every icon painted as an outline.

- `crawl-main-11b07629.json` (`main`): 3,632 icons from 46 names and 18 kinds
  of hand-made icon: the top bar's search, the profile activity toggle, the
  profile and online-members search fields, the invalid-link emblem, the
  breadcrumb chevron, the pane toggles, the notification rows, the composer's
  formatting buttons and emoji trigger, the drafts sync check, the admin bar's
  way back and bell, the reorder arrows, the tag search and the roles
  callout. None was outlined.
- `crawl-after.json` (this change): 4,747 icons from 66 names, no hand-made
  icon and no outlined icon. The pages that drew hand-made icons now draw
  Fluent ones, so more icons are counted.

The crawl does not reach every state: an accepted answer, the verified emblem,
a flash message or a board the member cannot post in. The spec checks those.

## Comparison with `main`

`regression-comparison.txt` lists the runs. Twenty specs touching icons, the
composer, the header, the admin console and the profile ran against this
change and against `main` (`11b07629`). Each run used its own server and
seeded database.

- **77 failing tests on both**, the same tests with the same first error.
  They include 14 tests whose fixture refuses these databases' names,
  header-row geometry and type expectations, counts that depend on data
  earlier tests leave, composer measurements and an axe finding. The file
  lists each with its first error. They predate this change and are not
  addressed here.
- **None fails only on this change.**
- `branding-styles.spec.ts` needs a database name containing `browser`.
  On `retroboards_fluent_browser` and `retroboards_fluentmain_browser` the
  same 6 tests fail on both and 7 pass on both. The passing tests include the
  large-text and keyboard checks of the admin style controls, whose selects
  now show the browser's arrow.

## Limits

- Chromium only. Forced colours, Windows High Contrast and screen readers
  were not checked for each icon. The icons are decorative (`aria-hidden`),
  and control labels are unchanged.
- The admin console's flash masks paint `--on-done` and `--danger` through a
  mask. Under forced colours the background is forced, as it was before this
  change.
- The online-members page is rate-limited per account (`presence`, 120 in 5
  minutes, shared with the presence heartbeat). Clear the private rate-limit
  directory between repeated runs.

## Reproduce

```sh
composer check:icons && composer check:forum && npm run check:assets
APP_KEY=<64 hex digits> DB_TEST_DATABASE=<private> COMPOSER_PROCESS_TIMEOUT=0 composer test
DB_DATABASE=<private e2e database> bash tests/browser/prepare.sh
(cd tests/browser && npx playwright test fluent-icons-complete.spec.ts header-unread-marks.spec.ts fluent-icons.spec.ts)
node docs/evidence/fluent-icons-complete-2026-11-09/icon-crawl.cjs http://localhost:8011 crawl.json
```
