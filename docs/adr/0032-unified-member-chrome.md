# ADR 0032: The member chrome is the design system's — ForumNav, BoardRail, PresenceList, verbatim

**Date:** 2026-09-12
**Status:** Implemented in the working tree; verification results below.
**Relates to:** the presence handoff (`docs/design-system/imladris/_archive/design_handoff_presence/README.md`)
and its ADR 0031; the 2026-08-27 member-surfaces production transfer (the
"compatibility bridge" in `app.css`, mirrored in the design system's
`components.css`); ADR 0024 obligation 4 (the runtime baseline); PRODUCT_DESIGN
§13 (completion evidence); the mirror's `LOCAL_RECONCILIATION.md` entry of the
same date.

## Context

The presence handoff shipped the design system's new **unified member chrome**:
`components/forum/ForumNav.jsx` (the topbar), `components/forum/BoardRail.jsx`
(the rail) and `components/presence/PresenceList.jsx` (the roster in the rail's
footer). Upstream wrote them because its own templates had hand-rolled the bar
seven times, no two alike; production had already unified on one topbar partial
and one rail partial on 2026-08-27, when the member-surfaces transfer copied the
design's then-current per-template chrome into `app.css` under production's own
class names (`.topbar*`, `.sidebar`, `.nav-*`), byte-for-byte mirrored into the
design system's `components.css` so the fidelity test could pin the bridge.

Comparing the new components' CSS with that bridge showed the two agreed on
almost every value — height, gutters, lockup, pills, search pill, toggles, rail
width, category labels, board rows, unread pills, locked rows, the roster's
footer — and disagreed on a short list: the search pill's position, the right
cluster's gap, the toggle glyph, a divider, the active board's marker, the seat
label's face, and the responsive steps. The larger cost was structural: the
bridge was a transcription. Every sync since 2026-08-27 has re-transcribed the
chrome by hand, and two of the design's decisions (the rail row's hover ground,
the roster footer link's hover accent) could not land at all, because the
bridge's unlayered copies beat the layer's rules regardless of specificity.

The supplied `CommunitySystem.zip` and the user's instruction require precise
template fidelity with feature gaps closed or clearly documented. The partials
adopt the design's class vocabulary, the layered `/assets/imladris.css` styles
the shell directly, and the bridge's shell section is retired from both files.
The bundle's prototype runtime stays source-only, as its README requires.

## Decisions

### 1. The partials render the design's DOM, in its vocabulary

`partials/topbar.php` renders `ForumNav`: `header.forum-bar` › `.forum-bar-brand`
(mark + wordmark) · `nav.forum-bar-surfaces` of `.forum-bar-surface` pills with
the Inbox `.forum-bar-count` · `.forum-bar-searchwrap` › `a.forum-bar-search`
(glyph, label, `⌘K`) · `.forum-bar-right` with the `.forum-bar-railtoggle`s, the
`.forum-bar-divider`, `.forum-bar-compose` and the seat (`.forum-bar-user` with
the `.avatar-wrap` monogram, its leaf, and `.forum-bar-username`) or the guest's
`.forum-bar-signin`. `partials/sidebar.php` renders `BoardRail`: `nav.board-rail`
of `.board-rail-cat` labels and `.board-rail-item` rows (`.is-active`,
`.is-locked`, `.board-rail-unread`, `.board-rail-tag`, `.board-rail-note`) with
the presence widget in `.board-rail-foot`. The active pill and the active board
carry `aria-current="page"` and **keep their `href`**, where the components render
the active entry inert: a surface here spans a family of routes (Boards covers
`/`, `/c/*` and `/tag/*`), so the pill is still the way back to the surface's
root, and ADR 0028's shared-navigation contract pins it. Other necessary PHP
and progressive-enhancement adaptations are enumerated below.

`/users-online` is ported verbatim from `templates/users-online/UsersOnline.dc.html`
in the same pass: the 860px column with its `uoRise` entry, the design's
margins, the tab padding and count tracking, the search form's measure and its
magnifier and focus ring, the roll as a card around the grid, the empty state
as a card, the pager's own buttons, the auto-fit guidance grid and the `.84rem`
foot. The three "optional deltas" the handoff named are therefore all taken.

### 2. The bridge loses its shell section — in both files

The "Shared shell" part of the 2026-08-27 transfer (`.topbar*`, `.brand*`,
`.identity-menu*`, `.sidebar`, `.nav-*`, `.board-rail-name`,
`.board-unread-count`, `.compose-board-*`, `.presence-widget` … `.presence-all`,
their phone rules and their focus rules) is removed from `app.css` **and** from
the mirror's `components.css`, which carry the bridge byte-for-byte; the route-
scoped `.app-shell` / `.main` layout rules stay. `AppImladrisFidelityTest` keeps
pinning the bridge, minus the two contracts that lived in the retired section
(`.topbar-primary`, `.compose-board-picker`), and gains a test that the runtime
carries the design's chrome and `app.css` restates none of the old vocabulary.
Three older generations of the same chrome that `app.css` still carried
underneath the bridge (Phase 1 shell, the Phase 4 phone rules, the base shell)
go with it — 868 lines fewer.

### 3. `app.css` keeps only what the layer cannot express

One block, "Member chrome — what the layer cannot express", each rule naming
its reason:

- **The `--maxw` centring.** The design's shell is full-width; the design's own
  README fixes `--maxw` at 1280px and production centres on it. The bar spans
  the viewport as the design's does, and its contents sit inside the column:
  `padding-inline: max(22px, calc((100% - var(--maxw)) / 2))`, the design's 22px
  gutter as the floor and its 1080px step kept. Two rules, removable the day the
  product goes edge-to-edge.
- **The sticky desktop rail and the phone drawer.** The design's shell is a
  fixed-height flex column that scrolls the rail inside itself; ours scrolls
  the document, so the rail is sticky and capped at the viewport, and below
  861px it is the off-canvas drawer behind `.nav-toggle` (the design's rail
  merely shrinks to 208px, which on a phone leaves nothing for content).
- **The persisted rail-closed state** (`⌘B`, `body.is-rail-closed`): the design
  renders no rail; we hide it.
- **The account menu**, a `<details>` the design's bare profile link does not
  have, with the chevron as its cue. **The operator's logo**, which replaces
  the lockup. **The seat's monogram size**, which the design's component sets
  on itself. **"Sign up"** beside the design's Log in pill.
- **The pane toggles as POST forms**, so the state persists without JavaScript;
  the glyph's filled band is drawn only while the pane is shown, as the
  component draws it.
- **New topic on a phone.** The design hides compose below 720px; only a board
  page has a floating compose, so the control stays as its glyph elsewhere.
- **The phone bar.** The design's phone steps (1080 / 900 / 720) assume a bar
  with no hamburger, no compose and no account seat; ours carries all three, so
  below 861px the row is packed the way the transfer packed it — 40px controls,
  tighter gaps — or a 390px viewport scrolls sideways. The compose link carries
  an `aria-label` so it keeps its name when its label hides.
- **Colour and type handed back.** `app.css` styles bare elements unlayered
  (`a { color: var(--accent) }`, the display rule on `h2`), and an unlayered
  declaration beats the layer's regardless of specificity — the chrome's links
  went evergreen and the roster's "Online" heading went to display type. The
  chrome's links and `h2.presence-title` declare `revert-layer` for exactly
  those properties, so the layer's values are what render.
- **Hover underlines.** `app.css`'s own unlayered `a:hover` underline reaches
  every link in the chrome and beats the layer's rules; a pill, a rail row and a
  roster row would underline in gold on hover. The design's base `a:hover` does
  the same in its own specimens (an oversight on a control, raised upstream) and
  the transfer suppressed it explicitly, so it stays suppressed. And the **focus
  ring** every other member-surface control wears.

### 4. What production keeps that the template does not draw

Recorded so the next fidelity audit does not file them as drift: the drawer
and its hamburger; the account menu and its chevron; Sign up; operator
branding (logo and dynamic site name — the fidelity test pins the name as the
lockup's accessible name); the `99+` cap on counts; the POST-form toggles; the
compose glyph; on `/users-online`, the Search submit button (the prototype
filters as you type; a GET form still submits on Enter without it, but a
button is what a mouse finds without JavaScript) and the tally's `aria-live`,
which is inert here because nothing updates the tally in place.

### 5. Two drifts the bridge had forced are gone

With the bridge's unlayered `.presence-list a:hover` and `.presence-all` gone,
rail rows hover on `--surface-sunken` as the design draws them, and "See
everyone online" takes the accent on hover. Both are measured by
`unified-chrome.spec.ts`. ADR 0031's own block sheds two more restatements
whose reasons were the bridge (the list reset had already gone with the
handoff; the compact padding and the rail-row rules go now); the ones that
remain name the unlayered rule they answer (`.monogram`'s 36px, the profile
avatar's dot).

## Consequences

- The class vocabulary changes on every app route. `app.js` reads the inbox
  badge by `.forum-bar-count` and toggles `.is-on` on the pane buttons;
  `tour.js` anchors on the new selectors; ten test files were renamed with it.
- A future mirror sync that touches `ForumNav`, `BoardRail` or `PresenceList`
  regenerates the runtime and lands here without a transcription; the fidelity
  test fails if anyone reintroduces the retired vocabulary in `app.css`.
- Geometry adaptations include the `--maxw` centring, sticky document rail,
  phone drawer and touch controls described in decision 3. These are explicit
  production adaptations, not claims of byte-identical prototype markup.

## Notification and organization repair — 2026-09-20

The unified-notification repair adds the persistent primary notification bell
with an initial authorized count. The existing `data-bell` tour hook remains on
that visible control. The count and eligibility scope are lazy and memoized;
adding the bell must not add eager shell queries to plain/JSON/health requests.
The poll refreshes every notification count/link node and retains the full
accessible count when the visual badge reads `99+`.

At 380px and narrower, the primary route group moves to a second full-width
row, with a 108px member header and matching drawer/scrim offsets. The added
bell otherwise leaves too little width to read even the Boards label at 320px.
Every route retains its full label, focus gutter and ordinary link behavior;
keyboard, touch and no-JavaScript checks cover Boards, Inbox and Messages.
This narrow-header adaptation spends vertical space to retain usable routes.

Owned saved-feed and board-folder shortcuts are added before category groups
without replacing category browsing, presence or the composer's destination
selection. They retain privacy and feature gates and tolerate unavailable
schema in the global shell. Originally empty saved-feed filters retain Latest
discovery; explicit selections use current canonical read permission within
the selected boards. This is a deliberate repair of the selectable private-board
workflow, not an expansion of ordinary Latest discovery. A revoked or malformed
selection never falls back to All boards. The [combined evidence index](../evidence/unified-notifications-and-settings/README.md)
tracks fresh browser/query evidence. This repairs exposed workflows and does
not introduce the deferred last-20 dropdown from proposed ADR 0035.

On account pages at phone widths, a native closed section chooser replaces the
long local settings navigation. Without JavaScript, the board rail remains
available in a keyboard-focusable region capped at 176px; its real board,
folder, feed and presence links remain reachable by scrolling. This account-only
adaptation was required because the uncapped rail alone consumed 723px before
the form. The enhanced drawer and desktop sticky rail retain their existing
behavior. Initial Security, Profile and Notifications controls are measured
before any scroll or chooser expansion in the combined browser gate.

## Feature-gap accounting

| Handoff behavior | Resolution |
|---|---|
| Unified topbar, rail, presence rows | Shared PHP partials use the design vocabulary and generated component layer on every member route. |
| Presence roll values | Adopted the 860px column, magnifier, card, pager, and auto-fit guidance; retained a submit button for the GET search. |
| Compose destination note | Closed: changing the select or rail destination moves the single “posting here” note to the selected board. |
| Active board on a topic | Closed: the layout passes the already authorized topic board to the shared rail; no extra lookup or disclosure. |
| Phone Messages entry | Closed: all primary links remain available in a scrollable group instead of hiding Messages. |
| Closed phone drawer | Closed: hidden visibility removes off-screen links from keyboard navigation; opening restores them. No-JS navigation remains visible. |
| Unread counts after opening an Inbox preview | Closed: topbar and matching rail badge decrement together, retain `99+`, refresh their accessible names and tooltip, and disappear at zero. The Unread filter tally updates in step. The Inbox row carries its already authorized board slug for this update. |
| Account control at narrow desktop widths | Closed: below the 1080px name breakpoint the icon cluster cannot shrink; the search field yields space. Verified at 901, 924, and 1024px in addition to the existing desktop/phone sizes. |
| Inbox scope and row menus | Closed: the positioned-state CSS now matches the specificity of the hidden open-menu state. Both menus become visible within the viewport after placement. The correction is kept identical in the design mirror and application transfer block. |
| Full member directory / Everyone, Staff, New this week | Deferred by ADR 0031: requires a separate privacy/disclosure decision. This roll includes only the privacy-filtered presence pool. |
| Loading skeleton / Presence is not answering | Deferred by ADR 0031: first paint is server-rendered; an error card needs a defined poll-failure state. |
| Board-presence subline (`.presence-where`) | Deferred: no production location field exists. Sample board locations are not shipped. |
| Warden chip | Not adopted: production's admin-only chip remains Staff. |

The profile template import updates the mirrored design and its accessible presence
dot; the handoff expressly does not request a fresh profile-page implementation.
Branding, feature gates, privacy gates, active links, persisted POST forms, the
native account menu, Sign up, and mobile Compose remain production behavior.

The bundled `PresenceList.prompt.md` still includes older sample guidance for
locations, loading skeletons, and named dots. Its sample is preserved in the
source mirror; the handoff README's explicit **Do not build** list and ADR 0031
govern production. Rows expose state in text with decorative dots; only the
profile dot receives an accessible image role. No sample `/members` route,
location data, skeleton, or error card is introduced by that prompt.

## Evidence

- `tests/Integration/Core/AppImladrisFidelityTest.php` — the bridge is still one
  bounded, hex-free section carried verbatim by `app.css`; the runtime carries
  `.forum-bar`, `.board-rail` and `.presence-widget`; `app.css` carries none of
  `.topbar-primary`, `.topbar-search-entry`, `.topbar-inner`, `.nav-boards`,
  `.nav-cat`, `.sidebar {`, `.sidebar-home`, `.presence-list a {`; the home
  page renders the design's DOM. `AppMemberShellTest`, `AppForumIndexRemediationTest`,
  `AppThreadStateTest`, `AppComposeMemberSurfaceTest`, `AppPresenceDirectoryTest`
  pin the renamed markup.
- `tests/browser/unified-chrome.spec.ts` — 13 tests × desktop and mobile (five
  cases skip outside their target viewport): the bar and the rail at the
  design's geometry in both registers (frames
  `docs/evidence/imladris-unified-chrome/{desktop,mobile}/`); the seat's leaf
  and the profile dot still take the away colour; a rail row without an avatar
  keeps a dot with a box; rows hover without an underline and the footer link
  takes the accent; the rail state persists across `⌘B` and a reload and the
  drawer opens; a guest's Log in pill and Sign up; the compose glyph on a phone.
  Its first capture is what found the two cascade leaks (the evergreen links,
  the display-type "Online") that `revert-layer` now hands back.
- Current completion-review results are recorded in
  `docs/evidence/imladris-unified-chrome/README.md`. Earlier capture counts have
  been replaced by fresh runs, rather than inferred from existing image files.
