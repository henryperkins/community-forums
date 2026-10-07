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
`/`, `/c/*`, `/tags` and `/tags/*`), so the pill is still the way back to the surface's
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

### Startup geometry follow-up — 2026-09-20

The mobile drawer geometry is selected by CSS `scripting: enabled` before the
deferred application bundle arrives. Native fragment links keep the drawer
reachable while that bundle is delayed or blocked; installed handlers adopt an
already-open drawer and preserve keyboard focus. The no-JavaScript stacked rail,
including the bounded account-settings rail, keeps its existing presentation.
The same startup contract covers the thread scroll area and reply dock; native
focus exposes the full form until its controller is installed. A focused textarea
keeps Source mode and its caret when the rich-editor module arrives.

The local warm CLS measurement fell from 1.047470 to 0.038149. Keyboard, no-JS,
blocked-bundle and delayed-editor evidence, plus the authorized presentation
baseline refresh and release checks, are recorded in
[`docs/evidence/performance/2026-09-20/cls-fix/README.md`](../evidence/performance/2026-09-20/cls-fix/README.md).

### Chrome consistency follow-up — 2026-10-06

The shared header receives explicit shell capabilities from the layout. Plain
error pages keep member identity and primary destinations without emitting
controls for absent rails or reading panes. Boards is current on tag routes and
authorized canonical topics, whose parent board remains current in the rail.

Category board links and personal folder shortcuts now share one renderer for
active state, unread counts, board URLs and visibility labels. The unread source
is the existing read-gated Inbox aggregate; duplicate shortcuts repeat a board's
count without increasing the queue total. Lost private-board access removes both
copies. Saved feeds retain their own active state and public presence stays in
the footer; the rail contains neither Inbox filters nor direct messages.

The document-scrolling desktop rail clears the sticky header. Enhanced phone
drawers focus their first usable control, wrap Tab, make covered main content
inert, and return focus on dismissal. Resizing releases the covered content and
keeps focus visible, including when the persisted desktop rail is closed. Native
fragment links and the stacked no-JavaScript rail remain available. Board and
Messages overlays share the same filtering and Tab-wrapping helper.

Messages restores a saved details column only after its stylesheets are ready.
WebKit can run the deferred controller before those sheets arrive, so an early
position probe cannot distinguish a column from an overlay. Until styles load,
the default remains closed; a user choice made meanwhile takes precedence.
Explicit details fragments remain open during resize, while a restored column
closes when it becomes an overlay without overwriting the saved preference.

The operator shell keeps its horizontal area tier above 860px. At 860px and
below, a native disclosure names the current area and opens the same ordered,
role- and flag-gated destinations; active areas stay non-links. ADMIN §9.2/§9.4
records this responsive adaptation. Narrow subscription panels put the title,
delivery controls and off action on successive rows; their container size
accounts for space taken by both sidebars. Long titles wrap at a readable width
instead of widening the settings document. Long community names
shorten in the operator header while controls and the brand mark keep their size.

Validation and limits are recorded in
[`docs/evidence/chrome-consistency-2026-10-06/README.md`](../evidence/chrome-consistency-2026-10-06/README.md).

### Shared-chrome design handoff — 2026-10-06

Upstream's review of the shared chrome arrived after the follow-up above and
read an older checkout. Reconciling it changed production in one respect: the
design layer no longer gives the current Boards pill and the current rail board
`cursor: default`, which contradicted decision 1. A computed-style comparison of
the old and new bundles across member, moderator, admin and guest routes found no
other difference.

The handoff's two production checks need no change. The standalone bell has
rendered since 2026-09-20. The console's exit link stays "Back to the forum"
(ADMIN.md §9.2, ADR 0024). Production's contract also stands where the design
differs, and those differences are raised upstream:

- Boards is current on authorized topics and on `/tags*`.
- The Messages pill carries a count.
- The bell is a 40px target with its own badge anchor.
- The narrow console tier is a disclosure.

Five design-side questions are recorded with the sync. One needs an owner
decision: whether `/leaderboard` keeps the standard rail, which production
renders, or takes the routes block the design draws. The active console area
stays a non-link under ADMIN.md §9.2.

The mirror ledger is
[`LOCAL_RECONCILIATION.md`](../design-system/imladris/LOCAL_RECONCILIATION.md)
(2026-10-06, shared chrome). The comparison is recorded in
[`docs/evidence/shared-chrome-handoff-2026-10-06/README.md`](../evidence/shared-chrome-handoff-2026-10-06/README.md).

### Header hardening — 2026-10-06

A dual-agent critique of the header scored it 27/40; its snapshot is
`.impeccable/critique/2026-10-06T21-51-37Z__templates-partials-topbar-php.md`.
It found two defects reproducible in a browser and three controls that ignored
their context. The fixes live in `app.css`'s Member chrome block, the topbar
partial, the layout and `app.js`. The layer is unchanged.

- **Stacking.**
  - Before: the layer stacks the bar at 20, and production's phone directory
    bar and post toolbars stack at 30. They painted over the bar and its account
    menu, so a tap on a moderator's Log out reached the page.
  - After: the bar sits at `--z-chrome` (40), an application token beside
    `--z-scrim` (55) and `--z-drawer` (60) in ADR 0039's block. Overlays that
    must cover the bar already start at 42.
  - DESIGN.md records the order as the Chrome-On-Top Rule.
- **The operator's lockup.**
  - Before: the design's lockup is `flex: none`, sized for a fixed short name.
    Production's lockup holds whatever the operator chose: up to 80
    characters, or a logo of any proportion. A 32-character name overflowed
    `/inbox` at 901px, and a 6:1 logo started a third phone row that lifted
    the first above the viewport.
  - After: the lockup has no flex basis and grows only to its own width, so it
    yields before the routes, search and account controls do. The wordmark ends
    in an ellipsis, the logo scales inside its 28px band, and the accessible
    name stays the full site name.
  - Side effect: above 1080px the search pill and the member's name take their
    room back, so a long name shows fewer characters at 1280px than at 1024px.
- **New topic** now opens the composer on the board being read, via `?board=`
  on board pages and on topics the viewer can see. Compose re-checks posting
  rights and falls back as before.
- **The reading-pane toggle** appears only from 1280px, where the pane is a
  column. ⌘J and ⌘B act only while their toggle is drawn.
- **The pane toggles** are toggle buttons with fixed names ("Board rail",
  "Reading pane") and `aria-pressed`, like the star toggle. The Hide/Show names
  and `aria-expanded` are gone.

The console bar is unchanged. It is a design-owned console class, which
`app.css` may not override, so any change to its stacking belongs in the
mirror. The critique's other findings remain open: the state marks, the phone
row's targets and order, and the polish items. The evidence is in
[`docs/evidence/header-hardening-2026-10-06/README.md`](../evidence/header-hardening-2026-10-06/README.md).

### Unread marks and the current surface — 2026-10-06

This follow-up takes the critique's state-mark finding, in the direction the
owner chose: the Inbox count leads.

- **The Inbox count leads.** It wears the thread row's unread mark, the gold
  dot with its 2px halo, beside medium-weight numerals in JetBrains Mono. The
  dot is a `::before`, so the preview's count updates keep it. It is the one
  saturated gold point the bar spends.
- **One quiet count.** Messages, the bell and the account menu's Notifications
  row share one chip: the gold-soft wash with gold ink, in JetBrains Mono at
  the 0.7rem chip floor, with tabular figures. This retires the bell's solid
  gold disc, which was set in bold EB Garamond and painted from primitives.
- **The bell's count** now sits beside the glyph, so "99+" no longer covers
  the bell. The bell keeps its 40px floor and grows only by the count. Its
  hover takes the toggles' sunken wash; the old hover dimmed the glyph to
  2.89:1 in twilight.
- **The current surface** carries a 2px rule in the pill's own ink, alongside
  the design's wash (which measures 1.04:1 on its own). The rule is a border,
  so it survives forced colours, and it is evergreen, not gold.
- **The pane toggles** draw their band filled while the pane is shown and
  outlined while it is hidden, the star toggle's grammar. At full strength the
  filled band measures 10.08:1 by day, up from 2.65:1, and the two toggles no
  longer read as the same empty box when off.

All of this is CSS in `app.css`, scoped to the member bar. The console keeps
its own bell, and the home page's notices count is untouched. The design's
ForumNav still draws a gold-soft chip on Inbox alone and draws the band only
while the pane is shown; the next shared-chrome sync should take these changes
or answer them. The evidence is in
[`docs/evidence/header-unread-marks-2026-10-06/README.md`](../evidence/header-unread-marks-2026-10-06/README.md).

### Phone rows and the drawer — 2026-10-07

This follow-up takes the critique's phone findings. The owner kept ADR 0042's
two rows, so only the details changed; ADR 0042 carries a matching addendum.

- **Targets and spacing.** At the 860px drawer breakpoint the first row's
  controls (drawer opener, lockup, search, New topic, bell and seat) are 44px
  targets with 8px between them, up from 40px with 2px. The gap eases toward
  4px only below about 340px, so the widest first row, a "99+" bell at 320px,
  still fits without a sideways scroll. The lockup is the one control that
  yields further, never past its mark.
- **Route labels** keep the desktop's 13px (`.82rem`), up from 11.8px, with the
  same 44px floor and 8px between routes.
- **Reading and focus order.** CSS draws the routes' row last, so Tab and a
  screen reader's swipe used to drop from the lockup to the routes and climb
  back to search. `app.js` now moves the routes after the account controls
  while the drawer breakpoint applies, and back beside the lockup above it.
  Without JavaScript the order stays the desktop one, which is complete.
- **A lone route keeps one row.** A guest's bar, or a member's with Inbox and
  Messages switched off, spent the second row on Boards alone, where the lockup
  already leads. Such a bar keeps one row at 62px, and the drawer, scrim and
  scroll offsets follow it. A guest's Sign up and Log in keep the 44px floor.
- **The community's name** is no longer hidden from 900px down. It shortens
  with an ellipsis and, where fewer than about five letters would show, drops
  whole so the mark stands alone, never a stub. Above 1080px the name and the
  search now share the room, which fixes the default name being clipped at
  1180px while the search kept its full 300px.
- **The divider** is rendered only beside a pane toggle. It was an orphan at
  721–860px and on the plain error page.
- **The drawer's close control.** The rail's "Close board rail" link is now a
  44px cross at the drawer's top corner, in the Topic tools style. It works in
  both the native fragment drawer and the enhanced one, which used to hide it.
  The enhanced drawer focuses it on opening, as Topic tools focuses its close
  button.
- **Without scripting** there is no drawer, and the phone rail sat sticky under
  the bar, covering the content and taking its taps once the page scrolled
  (`notifications-unified.spec.ts:139`). It is now a bounded block above the
  content that scrolls away, the treatment account pages already had.
- **Touch screens above 860px** (a tablet, or a phone held sideways) keep the
  same 44px floor on the single-row bar (`pointer: coarse`).

The design layer still hides the name at 900px and still sizes phone controls
at 40px. The next shared-chrome sync should take these changes or answer them.
The evidence is in
[`docs/evidence/header-phone-rows-2026-10-07/README.md`](../evidence/header-phone-rows-2026-10-07/README.md).

### Header polish — 2026-10-07

The last pass from the 2026-10-06 critique takes its minor observations and
persona flags.

- **Focus keeps the control's shape.** `app.css` restated the layer's global
  `:focus-visible` rule unlayered, `border-radius: 2px` included, so it beat
  every control's own radius: a focused pill, search field or button squared
  off. The restatement keeps its outline but drops the radius. The layer's own
  rule still rounds what has no radius of its own. This applies site-wide,
  because the cause was global.
- **The halo reaches every bar control.** The lockup, the drawer opener and
  New topic now wear the gold halo the others did. The lockup and the account
  button take the pills' 7px corner for their focus rings.
- **Shortcut hints are true.** The server claims no shortcut, because nothing
  answers ⌘K without `app.js`. With it, controls named by `data-shortcut`
  gain `aria-keyshortcuts` and a hint in the platform's own modifier: ⌘ on
  Apple devices, Ctrl elsewhere. The handler takes either modifier everywhere.
  The search hint meets the 0.7rem chip floor; it was 0.64rem.
- **A count's words live on its link**, as the bell's always did. Inbox,
  Messages and the account menu's Moderation link read, for example, "Inbox,
  4 unread topics", with the digits `aria-hidden`. A role-less span may not
  carry a name of its own. The server now uses the singular, so it no longer
  renders "1 unread conversations", and `app.js` keeps each link's name in
  step with its count.
- **The bell is current on `/notifications`**, with the routes' wash and 2px
  rule.
- **The account menu falls into groups.** Hairlines set settings and
  authority apart from your own places, and set Log out apart from both.
  Settings wears Lucide's sliders glyph instead of Profile's person. The
  account button is now named "Account menu for …" without the verb "Open",
  since its expanded state already says whether the menu is open.
- **A guest's pair.** Log in deepens its fill on hover (the `.btn:hover`
  recipe), and Sign up's target matches the pill's 32px height instead of 19px.
- **The drawer opener** takes the toggles' hover wash on devices that hover.
- **The tour's first steps** say what is true at every width: "Select it any
  time to come back here" for the lockup, and "from search, at the top of
  every page" for search.
- **The phone lockup holds only what it shows.** The adapt pass hid the name
  by clipping it, and the link kept the room the name would have used. The
  tour's highlight and the focus ring then framed a blank stretch. The lockup
  now sits in a wrapper that takes the row's spare room and serves as a named
  size container, so the link is the mark plus whatever name fits. On a phone
  the search therefore joins the actions on the right. Above 860px the wrapper
  is `display: contents`.

Left open, because each is a decision rather than polish:

- The brand and Boards both lead to `/` while members land on `/inbox`.
- The wordmark's 600 weight comes from the layer, which conflicts with
  DESIGN.md's "medium, not bold". The fix belongs in the design system.
- For a guest, Sign up is the quieter of the pair.
- "Search the council…" opens with the private lexicon.
- Moderators have no open-reports signal on the bar itself.
- ⌘K loads a page rather than a palette.

The evidence is in
[`docs/evidence/header-polish-2026-10-07/README.md`](../evidence/header-polish-2026-10-07/README.md).

### Header review regressions — 2026-10-07

The follow-up review found two width cases the medium-font phone captures did
not cover, and an ambiguous composer destination:

- At 320–350px in desktop Chromium, a classic scrollbar reduces the header's
  available width. The action cluster wrapped into a third row inside the
  fixed 108px header, clipping the first row above the viewport. The bar now
  measures its inline size; gaps ease from 8px to 4px, its outer gutter from
  8px to 4px, and the counted bell's inner padding yields before target sizes.
- At 320px with the member's Large text setting and capped Inbox/Messages
  counts, route labels extended past the viewport. Their font size and 44px
  targets stay intact; horizontal gaps and padding adapt to the available row.
- A numeric board slug could be mistaken for another board's ID. Composer
  links (`?board=`) now prefer an exact listed slug before a legacy ID. Explicit
  `board_id` values and rejected POST data resolve by ID only. A listed but
  unpostable slug uses the existing first-postable fallback, and posting and
  visibility gates remain authoritative.

This refines the phone spacing clauses above without changing ADR 0042's row
count or shared height token. The regression tests run in the standard chrome
and browser-evidence commands. See the
[test and browser record](../evidence/header-regressions-2026-10-07/README.md).
