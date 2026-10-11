# Header polish — 2026-10-07

Evidence for the `/impeccable polish` pass on the member header, the last pass from the
2026-10-06 critique (`.impeccable/critique/2026-10-06T21-51-37Z__templates-partials-topbar-php.md`).
It takes the critique's minor observations and persona flags, plus one defect that this
session's adapt pass introduced.

The pass touches:
- `app.css`: the global focus rule, the Member chrome block and the colorize block's current rule
- `topbar.php`: the count names, the shortcut hooks, the bell's current state, the account
  button's name, the menu groups and the lockup's wrapper
- `icon.php`: one Lucide glyph
- `app.js`: count names and shortcut hints
- `tour.js`: two lines of copy

The fingerprint in [working-tree.json](working-tree.json) covers the tested working tree on
`553004b1`, which also carries the [hardening](../header-hardening-2026-10-06/README.md),
[unread marks](../header-unread-marks-2026-10-06/README.md) and
[phone rows](../header-phone-rows-2026-10-07/README.md) slices. No commit, push or deployment is
implied. The decisions are recorded in ADR 0032 ("Header polish — 2026-10-07"), and the DESIGN.md
Topbar entry describes the result.

The critique snapshot itself was closed by `impeccable critique-storage`, because `topbar.php` had
changed since it was taken. The tool keeps the snapshot's history.

| Before | After | Captures |
|---|---|---|
| A focused pill, the search field, the pane toggles, New topic and the account button squared off to 2px corners (`app.css` restated the layer's focus rule unlayered, so its radius beat every control's own). | Each control keeps its own corner when focused: 7px pills, the 999px search, 4px toggles. Only the radius was dropped from the restatement, so the outline is unchanged, and the layer's own rule still rounds elements with no radius of their own. This applies site-wide. | [Search, focused](desktop/focus-search.png); [first stop, desktop](desktop/focus-first-stop.png), [phone](mobile/focus-first-stop.png) |
| The lockup, the drawer opener and New topic had the bare outline without the gold halo. | Every bar control wears the halo. The lockup and the account button take the pills' 7px corner for their rings. | The focus captures above. |
| "⌘K" showed on every platform at 10.24px. The search was named "Search the council — Command K" and the toggles' titles said ⌘B and ⌘J, even with JavaScript off, where nothing answers them. | The server claims no shortcut. With JavaScript, each control names its shortcut through `aria-keyshortcuts`, and the hint uses the platform's own modifier: "⌘K" on Apple devices, "Ctrl K" elsewhere, at the 0.7rem chip floor. | [Linux](desktop/hint.png), [Apple](desktop/hint-apple.png) |
| Counts named themselves through `aria-label` on role-less spans, which ARIA prohibits, so browsers read them inconsistently. The server wrote "1 unread conversations". | Each count's words live on its link, as the bell's did: "Inbox, 1 unread topic", "Messages, 3 unread conversations", "Moderation, 1 open report". The digits are `aria-hidden`, and `app.js` keeps each link's name in step with its count. | Asserted in both engines. |
| The bell was never current, not even on `/notifications`. | On `/notifications` the bell is `aria-current="page"`, with the routes' wash and 2px rule. | [Desktop day](desktop/bell-current-light.png), [twilight](desktop/bell-current-dark.png); [phone day](mobile/bell-current-light.png), [twilight](mobile/bell-current-dark.png) |
| The account menu had 8 undivided items, and Settings wore Profile's person glyph. The account button was named "Open account menu for …" even while open. | Hairlines open two later groups: settings and authority, then Log out. Settings wears Lucide's sliders. The button is "Account menu for …", and its expanded state says whether it is open. | [Desktop day](desktop/menu-light.png), [twilight](desktop/menu-dark.png), [phone](mobile/menu-light.png) |
| Log in had no hover state, and Sign up was a 43×19 target. | Log in deepens its fill on hover (the `.btn:hover` recipe), and Sign up is 32px tall. | [Hovered](desktop/guest-hover.png) |
| The tour said "Click the name", though a phone may show only the mark, and "the search box", though search is a glyph from 1080px down. | "This is your community home. Select it any time to come back here." "Find topics and people from search, at the top of every page." | [Desktop](desktop/tour-welcome.png), [phone](mobile/tour-welcome.png) |
| Introduced by the adapt pass: when a phone's name dropped, the lockup link kept the room the name would have used. The tour highlight and the focus ring then framed the mark plus up to about 60px of blank space, and a tap there went home. | The lockup sits in a wrapper that takes the row's spare room as a named size container. The link is the mark plus whatever name fits, and the name hides by container query below the mark plus 3.75rem. On a phone the search therefore joins the actions on the right. Above 860px the wrapper is `display: contents`, so desktop is unchanged. | [Focused at 390px](mobile/lockup-focus-390.png); the phone tour capture above |

## Validation

- **New spec** `tests/browser/header-polish.spec.ts`: see [chromium.txt](chromium.txt).
- **Header specs**: `header-phone-rows`, `header-hardening` and `header-unread-marks` re-ran on
  the final tree in both engines.
- **Existing browser specs** re-ran in Chromium. Every failure reproduces identically on the
  `HEAD` export ([chromium.txt](chromium.txt)).
- **PHPUnit**: full suite, 3,129 tests and 23,772 assertions; the one skip is the dedicated 0077
  rehearsal ([phpunit.txt](phpunit.txt)).
- **Detector**: `impeccable detect` on `topbar.php`, `icon.php` and `sidebar.php` reports no
  findings.
- **Asset checks**: `npm run build`, `check:assets` and `composer check:imladris` are current after
  the digest refresh.

## Scope and environment

- **How it ran**: synthetic Chromium and WebKit on Linux, with emulated phone viewports and touch.
  Both engines report Linux, so the Apple hint was tested by presenting a Mac platform to the
  page, not on Apple hardware.
- **Not covered**: physical devices and assistive-technology certification. The count names were
  checked as computed accessible names, not with a screen reader.
- **Site-wide effect**: dropping the restated radius changes focus visuals on every surface, not
  only the header: controls keep their corners when focused. No other style was changed.
- **Left open**: each of these is a decision for the owner, not a defect.
  - The brand and Boards both lead to `/` while members land on `/inbox`.
  - The layer's wordmark is weight 600, against DESIGN.md's "medium, not bold". The fix belongs
    in the design system.
  - For a guest, Sign up is the quieter of the pair.
  - The search opens with the private lexicon, "Search the council…".
  - Moderators have no open-reports signal on the bar itself.
  - ⌘K loads a page rather than a palette.
