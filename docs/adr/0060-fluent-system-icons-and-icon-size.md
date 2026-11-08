# ADR 0060 — Fluent System Icons and the member icon size

**Date:** 2026-11-08
**Status:** Accepted. Implementation and local browser evidence are in
[`docs/evidence/fluent-icons-2026-11-08/`](../evidence/fluent-icons-2026-11-08/README.md).
Production deployment and verification are separate outcomes.

## Context

`templates/partials/icon.php` drew 50 hand-made glyphs: Lucide-style strokes
on a 24-unit grid (1.8 units, about 1.2px at the 16px default), three filled
marks and two custom marks from ADR 0057. Several call sites borrowed a glyph
made for another job:

- `bell-off` (Mute conversation) drew a muted speaker, not a bell;
- the Boards settings item used the drawer's hamburger;
- Delete and Delete topic used the close X, and Add a reaction the generic plus;
- an empty board and an empty conversation list shared one chat bubble.

A design proposal for the Forum theme recommended Microsoft's Fluent UI System
Icons: Regular by default, Filled for selected states, the Comment family for
discussion and Chat for real-time messaging, MIT-licensed SVGs bundled
locally. It also suggested 20px icons for thread metadata and 24px for
navigation and toolbars. The forum draws row marks at 9px beside metadata,
menus and toolbars at 16px and the top bar's bell at 18px. Fluent draws each
size for the size it is shown at: shrinking 20px artwork to 16px leaves 0.8px
lines between pixels, and 20px marks beside 12–13px metadata change the
density of every list.

## Decision

1. **One icon source.** Every glyph the partial draws is a Fluent System Icon
   from `@fluentui/svg-icons` 1.1.341 (MIT, Copyright (c) 2020 Microsoft
   Corporation). The sources are pinned in `resources/icons/fluent/` with the
   licence. `resources/icons/icons.json` maps each forum icon name to its
   Fluent icon, sizes and styles. `bin/build-icons.php` validates every file
   (one `<svg>`, only `<path>` children, path-command data) and generates
   `src/Support/IconSet.php` (`composer build:icons`, `composer check:icons`).
   The partial still prints one inline SVG per call, painted with
   `currentColor`; no package, sprite or CDN is added and the CSP is
   unchanged. Older rules stroke any `svg` inside `.fab`, `.chip`, `.badge`,
   `.accepted-flag` or `.nav-toggle-ic`, because they were written for the
   stroked glyphs. The unlayered Forum theme therefore paints `svg.icon` with
   `currentColor` and no stroke. Hand-drawn SVGs outside the partial keep
   those rules.

2. **Names stay; artwork changes.** The `icon icon-<name>` class hooks are
   unchanged. Where Fluent's own metaphor differs, the name keeps its job and
   takes Fluent's mark: `arrow-up` (send) is Send, `settings-profile` Person
   Circle, `settings` the Settings gear, `file` (Drafts) Drafts, `ban` Person
   Prohibited, `book` (Reading) Book Open, `monitor` (Sessions) Phone Laptop
   and `party-popper` (Celebrate) Balloon, as Fluent has no party popper.
   `bell-off` becomes Alert Off. `log-out` is Arrow Exit: Fluent retires Sign
   Out in favour of Door Arrow Right, whose badge muddies at 16px. Catch me up
   keeps History, because Fluent's Catch Up reads as a squiggle at 14px.

3. **New names where a job borrowed a glyph.** `new-topic` (Comment Add) on
   the New topic buttons and trigger, `boards` (Comment Multiple) on the
   Boards settings item, `post-link` (Comment Link) on a post's Copy link,
   `board-empty` (Comment Multiple) on an empty board, `emoji-add` on Add a
   reaction, `delete` (a bin) on Delete, and `appeal` (Gavel) on Appeals.
   Chat stays for conversations, Add for the New menu, Dismiss for closing
   and Copy for message text and profile links. The retired `star` and
   `star-filled` (ADR 0036) and the unused `panel-left` and `user-plus` leave
   the set. A unit test holds the set to exactly the icons the templates draw.

4. **Regular, and Filled for a selected state.** The set star is Star Filled
   and the unset star Star Regular, so `.icon-commend-star.is-outline` now
   paints its artwork instead of stroking the filled glyph. The settings rail
   draws the current section Filled (the partial's `filled` flag). Pin and
   Sparkle stay solid marks. DESIGN.md's marks hold: one five-point star, a
   settings mark for Topic tools, a sparkle only for Living Brief content.

5. **Artwork for the size shown.** Standard icons draw the 16px artwork. The
   star and reactions draw the 20px artwork (shown at 18px) and empty-state
   marks the 28px artwork (shown at 30px), at either icon size.

6. **A member icon size instead of a new default.** Appearance → Icon size
   offers Standard (16px, the default) and Larger (20px), stored as
   `icon_size` in the preference blob (schema v5, additive) and stamped as
   `data-icon-size` on `<html>` with the other appearance choices. The token
   `--icon-size` is 16px, and 20px under `html[data-icon-size="large"]`.
   Icons without a size of their own take it, as do the settings rail,
   account and conversation menus, the create trigger, Topic tools, Inbox help
   and the admin area menu. The bell and conversation icon buttons take
   `max(18px, var(--icon-size))`. Row marks, chips, the star toggle,
   reactions and empty-state marks keep their sizes. With larger icons the
   partial draws the 20px artwork. The default layout keeps today's geometry,
   and members who want bigger icons get artwork drawn for 20px.

## Consequences

- Every member surface shows new artwork. At the default size the geometry is
  unchanged, except the settings rail's icons, which move from 15px to the
  shared 16px. The accepted-answer flag's star is now solid. A `.accepted-flag
  svg` rule had drawn it as a hairline outline.
- With larger icons, marks drawn at 9–15px use the 20px artwork, so their
  lines are slightly lighter than with standard icons. With standard icons,
  the few icons drawn at 18–20px scale the 16px artwork up.
- Notification type icons (`NotificationPresenter` and `notification_row.php`),
  the composer's formatting buttons and the sign-in emblems draw their own
  Lucide-style SVGs outside the partial and are not converted here. They are
  an open carryover for a follow-up.
- Adding an icon means mapping it in `icons.json`, adding its pinned sources
  and running `composer build:icons`. `IconsTest` fails if a template draws an
  unmapped name or the set carries an icon no template draws.
- Visual changes remain regression-sensitive (ADRs 0033, 0036, 0037):
  PRODUCT_DESIGN §13 browser evidence accompanies this decision.

## Evidence and limits

- PHPUnit: `tests/Unit/Support/IconsTest.php` (generated set current, path
  data, size and style selection, template coverage, the theme's fill rule)
  and `tests/Integration/Core/AppIconSizePreferenceTest.php` (form, stamping,
  Filled rail item, fallback, export and reset, the star, forum marks). The
  preference, thread-view, leaderboard, profile and CSS-bridge tests are
  updated. The bridge records the star rule as a deliberate edit.
- Browser: `tests/browser/fluent-icons.spec.ts` at 1280 × 800 and 390 × 844,
  light and dark, including a check that no icon is outlined, plus the
  updated `topic-row-consolidation.spec.ts` and `thread-view-study.spec.ts`.
  A GET-only crawl covered 280 pages as a guest, a member and an admin.
  Before the theme rule it found outlined icons in the menu toggle (link and
  button), the Solved chip and the board's New topic button; after it, none.
  The accepted-answer flag, which the crawl did not reach, was checked
  separately. Results and screenshots are in the evidence folder.
- Not verified here: production deployment, forced-colours and Windows High
  Contrast rendering of every icon, and the carryover surfaces above.

## References

- ADR 0036 (one star), ADR 0057 (marks), DESIGN.md (Layout and components).
- USER.md §4.1 (Appearance), PRODUCT_DESIGN §13 (completion evidence).
- Fluent UI System Icons: <https://github.com/microsoft/fluentui-system-icons>.
