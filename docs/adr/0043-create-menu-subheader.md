# ADR 0043: One create control in a shared row under the member header

**Date:** 2026-10-07
**Status:** Accepted
**Supersedes in part:** ADR 0032 decision 1 (`.forum-bar-compose` in the bar),
decision 3 ("New topic on a phone"), decision 4 ("the compose glyph"), and the
phone first-row control list in ADR 0042's 2026-10-07 addendum.

## Context

The header's New topic control started a public topic, while a separate plus
beside the Messages title started a private message. Both tasks now start from
one create control in a shared content row beneath the member header. Removing
creation from the header leaves more room for its persistent phone controls.

The owner settled these decisions on 2026-10-07. The existing header heights,
route rows, board actions, write gates and progressive-enhancement contracts
continue to apply.

## Decision

### Header and shared row

- Remove `.forum-bar-compose` from the production topbar. Its other controls
  and height rules remain: 62px for one row, 108px for the two-row phone bar
  under ADR 0042. The phone's first row contains the drawer opener, lockup,
  search, bell and seat.
- Render `partials/subheader.php` inside `<main id="main">`, ahead of the flash
  slot, in both the `app` and `plain` layouts. Every signed-in member who gets
  the member header gets this row, including `/compose`, `/messages/new` and
  error pages. The skip link lands on the row within the main landmark.
- Move board and topic breadcrumbs and the Boards directory's
  `nav.forum-directory__tabs` into the row's leading side. Each renders once;
  page headings stay in their pages. Other pages leave the leading side empty.
- Guests get the row only where it carries leading content. They get no create
  control and no empty band.
- The row scrolls with ordinary pages. In Messages and Inbox, the room's panes
  scroll internally and the row stays above the room.

### Create control

- Above 860px, a quiet "+ New" trigger with a chevron opens a native
  `<details>` menu containing New topic, then New message. At 860px and below,
  the trigger shows only a plus in a 44px target, named "New topic or message".
- New topic uses the layout's existing `compose_board` value to link to
  `/compose?board=<slug>` on board pages and authorized topics. Elsewhere it
  links to `/compose`. This adds no global-shell query.
- New message links to `/messages/new`. On a Messages page with JavaScript and
  an existing compose panel, an ordinary activation opens that panel in place.
  Modified link activations retain the native destination.
- With `dms` off, the control is a direct New topic link. It shows the New topic
  label above 860px and only the plus at smaller widths, retaining its
  accessible name.
- The matching destination carries `aria-current="page"` on `/compose` and
  `/messages/new`.
- Suspended, banned and DM-throttled members get the same control. Existing
  destinations and POST handlers explain and enforce `WriteGate` and
  `DirectMessageService::isThrottledNewUser`; the shell adds no eligibility
  lookups.
- The native disclosure and links work without JavaScript. The existing Inbox
  menu code also manages the create menu: one open menu, viewport placement,
  Escape with trigger focus restoration, outside-click dismissal and
  surrounding-page scroll dismissal. Scrolling inside a menu keeps it open.

### Messages dialog and draft recovery

Retire `summary.dm-new-btn` beside the Messages title. Keep the compose form in
a `div.dm-compose-panel` that is hidden until opened; a summary-less `<details>`
would introduce the browser's default "Details" control.
Render this panel after the room as a direct child of the main landmark, outside
the responsive list pane and room grid. Creation therefore works when the list
pane is hidden on conversation and new-message pages at 900px and below.

JavaScript opens the panel from the shared New message link, focuses its
recipient field, contains dialog focus and restores create-trigger focus on
dismissal. The form continues to submit `origin=dialog`. A rejected submission
re-renders `/messages` with the same panel visible and the typed recipient,
title and body retained. Server rendering opens that panel even without
JavaScript. Close and Cancel retain ordinary `/messages` links for the native
path. Without JavaScript, starting a new message follows `/messages/new`.

Recipient suggestions close when focus leaves their input, invalidating pending
responses and cancelling the debounce/request without clearing typed recipients
or message text. Pointer selection keeps the input focused until the option is
chosen. Escape in the recipient input dismisses its open suggestions first;
after focus has moved elsewhere, Escape dismisses the dialog normally.

### Geometry and visual identity

The shared row uses Imladris semantic surface, border, spacing, type, corner and
focus tokens. Its trigger has a quiet hairline and tonal hover treatment. The
leading breadcrumbs wrap long board names within their available width, including
the accepted 80-character limit, while retaining the full text and create target.
The board slab's New topic button remains that page's accent action. The slab
button, condensed sticky echo, phone FAB and `#new-topic` composer all stay.

`--topbar-h` and document start scroll padding continue to describe the sticky
header alone. A separate `--subheader-h` describes the row: a 61px CSS fallback
(44px target, two 8px vertical gutters and a 1px hairline), updated to its actual
border-box height by `ResizeObserver` when available.

Messages and Inbox subtract that row height from their existing room height.
Messages no longer cancels the main column's padding with negative margins
that would pull its shell over the row. The established conversation
min-content growth on short or zoomed screens remains; the row adds no new
document overflow to height-bounded rooms. Flash adjacency, docked composer and
new-messages pill placement retain their existing contracts. The no-JavaScript
phone rail and room share the available column height.

When the readable floor makes a short conversation grow, JavaScript brings
the dock into view on an initial visit to its latest letters, using the same
document-scroll path as composer focus. Fragments, a reader who has scrolled
away from the latest letters, and existing page scroll keep their place.
Without JavaScript, Large text at 320, 390 and 860px still reaches that floor
and grows beyond an 844px viewport. The baseline comparison records 16–28px
of overflow here against 192px on untouched `HEAD`; Send remains visible.
Those native limits remain explicit browser-test failures in the evidence.

Page content keeps the Chrome-On-Top Rule: content at 30 or below, the member
bar at `--z-chrome` (40), and content popovers from 42. The create popover uses
the existing menu tier at 50, below the phone drawer.

## Consequences

The production header departs from the design mirror's `ForumNav`, which still
draws compose in the bar. The deviation is recorded in
[`LOCAL_RECONCILIATION.md`](../design-system/imladris/LOCAL_RECONCILIATION.md).
The mirrored component and generated `.forum-bar-compose` rules remain as
they were; production's compose markup and application-only rules are removed.

## Evidence

The [test and browser record](../evidence/create-menu-2026-10-07/README.md)
records the checks and their limits. `AppCreateMenuTest` pins the shared row,
leading content, board-aware destinations, flag-off behavior, current items
and unchanged eligibility controls. Existing member-shell, Messages and
Thread Intelligence contracts now target the shared control. The create-menu
browser spec covers route/account matrices, viewport and theme combinations,
Large text, native navigation, menu focus, dialog recovery and room geometry.
The [review remediation record](../evidence/create-menu-2026-10-07/regression-fixes/README.md)
adds dialog visibility through the 900px pane boundary, recipient blur/late-response
cancellation and pointer selection, retained drafts, shared Messages colors,
and full-length breadcrumb wrapping in Chromium and WebKit.
