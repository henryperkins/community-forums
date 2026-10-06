# Handoff: Shared chrome — one bar, one rail, one roster

## Overview

The **shared chrome** of RetroBoards, in the Imladris design system: the member top bar, the board rail with its Online roster, and the operator console bar. This handoff also covers the **per-route contract** that says what each piece shows on each route.

On 2026-10-06 all 23 templates in the design system were reviewed for consistency in this chrome. Nearly every defect was the design drifting away from what production already renders. Those are fixed in the design system now. For a developer working in **`henryperkins/community-forums`** this handoff gives you:

1. **The contract** that `templates/partials/topbar.php`, `sidebar.php`, `presence_person.php` and `templates/admin/_console.php` must keep. Treat it as a parity checklist.
2. **Four component changes** to mirror into `docs/design-system/imladris/`.
3. **Production deltas to verify**: places where the design asserts something the reviewed checkout did not clearly show.
4. **Open decisions**. Do not implement these until they are decided.

## About the design files

The files in `design/` are **design references created in HTML**. They are React components compiled into a browser bundle, plus page templates (`.dc.html`) that compose them. They show the intended look and behaviour. They are **not production code to copy**.

Recreate this chrome in the codebase's existing environment. Production is vanilla PHP and MySQL, server-rendered. The chrome is already server-side partials (ADR 0032 for the member chrome, ADR 0024 for the console), styled by `/assets/imladris.css` (the design layer) and `public/assets/app.css`. Production already uses the same class vocabulary (`.forum-bar*`, `.board-rail*`, `.presence-*`, `.admin-bar*`, `.admin-tier*`), so the work is parity, not a port.

Component sources carry a `.txt` suffix so the design-system compiler ignores them. The `.dc.html` templates will not run from this folder because their loaders are not included. Open them in the design-system project to see them live, or read them as markup.

## Fidelity

**High-fidelity.** Every value below is transcribed from `public/assets/app.css` via the design system's `components.css` and `tokens/`. Match it to the pixel. Hex values are given for both registers: **day** (parchment) and **twilight** (`[data-theme="dark"]`).

---

## Shell layout

### Member app shell (every member route)

```
body
├─ header.forum-bar                 sticky top:0, z 20, height 62px (--topbar-h)
└─ .app-shell                       row, fills the remaining height
   ├─ nav.board-rail#sidebar-nav    272px (--sidebar-w), own scroll
   └─ main#main                     flex 1 1 0, min-width 0, own scroll
```

- **Main pane, design values.** `padding: 26px 40px 90px`, then an inner wrapper at `max-width: <route>; margin: 0 auto`.
  - Route widths: Board index, Search and Leaderboard 760px. Users online 860px. Compose 700px. Account settings 1008px.
  - Account settings uses 132px bottom padding to clear its fixed save bar.
  - Board page uses `padding: 0 34px 64px` with `max-width: var(--maxw)` (1280px), because its evergreen masthead band runs full width.
  - Thread view uses its own centred grid (`padding: 24px 28px 0`).
  - The inbox is three panes: rail, queue (`flex: 0 1 660px; min-width: 430px; padding: 26px 30px 40px; border-right: 1px solid --border-hair`), then the reading pane.
- **Rail closed** (⌘B or the toggle). The rail is not rendered and main takes the width.

### Console shell (`/admin/*`, `/mod/*`)

```
.admin-bar          sticky top:0, z 20: identity row (58px) + area tier
main.admin-console  h1.admin-title → nav.admin-tabs → flash → .admin-pane
```

Design body: `max-width: 1100–1160px` (content-driven per area), `margin: 0 auto`, `padding: 22px 28px 110px`. The member top bar and rail never render in the console (`layout.php`: `$showChrome = $variant !== 'auth' && $variant !== 'admin'`).

---

## Components

### 1. Member top bar — `ForumNav` · `header.forum-bar`

**Order, left to right:** brand lockup · surface pills · search · right cluster (pane toggle(s) · divider · New topic · bell · seat). A guest gets **Log in** (production adds **Sign up**) in place of toggles, compose, bell and seat.

| Part | Spec |
|---|---|
| Bar | `display:flex; align-items:center; gap:18px; height:62px; padding:0 22px; box-sizing:border-box`. Background `color-mix(in srgb, --surface-raised 92%, transparent)` + `backdrop-filter: blur(10px)`. `border-bottom: 1px solid --border-hair`. Sticky, `z-index: 20`. |
| Brand `a.forum-bar-brand` → `/` | `gap:10px`. House mark 24×24 (EightPointStar) in `--accent`. Wordmark: Cormorant Garamond 600, 1.25rem, `letter-spacing:.01em`, `--text-strong`, nowrap. |
| Surface pills `nav.forum-bar-surfaces[aria-label=Primary]` | `gap:4px`. Pill: `padding:6px 12px; border-radius:7px`, Marcellus .82rem, `letter-spacing:.02em`, `--text-muted`. Labels in order: **Boards · Inbox · Messages**. |
| Pill hover | bg `--surface-sunken`, ink `--text-body`. |
| Pill active | bg `--brand-subtle`, ink `--on-brand-subtle`, `aria-current="page"`. **Keeps its href** (ADR 0032 #1). |
| Pill inert (no destination yet) | `--text-faint`, no hover, `aria-disabled="true"`, rendered as a `<span>`. |
| Inbox count `.forum-bar-count` | Inside the Inbox pill, gap 7px. `padding:0 6px`, pill radius, bg `--gold-soft`, ink `--gold-ink`, JetBrains Mono .64rem. Capped at **99+**. `aria-label="N unread topic(s)"`. **Signed-in only.** |
| Search `.forum-bar-searchwrap` | `flex:0 1 300px; min-width:180px`. Control `.forum-bar-search`: `height:34px; padding:0 14px; gap:8px`, bg `--surface-sunken`, `1px solid --border-hair`, pill radius. Hover border `--border-soft`. Contents: 13px magnifier (stroke 2, round caps), label "Search the council…" (Marcellus .76rem, `--text-faint`, ellipsis), kbd "⌘K" (Mono .64rem, `--text-faint`, `margin-left:auto`). It is a link to `/search`, or a button when the surface owns a palette. `aria-label="Search the council — Command K"`. |
| Right cluster `.forum-bar-right` | `margin-left:auto; gap:14px`. |
| Pane toggle `.forum-bar-railtoggle` | 30×30, radius 4px, transparent, `--text-faint`. On: bg `--brand-subtle`, ink `--on-brand-subtle`, `aria-pressed="true"`. Hover: bg `--surface-sunken`, ink `--text-body`. Glyph is 16×16: an outlined panel (`rect 1.6,2.6 12.8×10.8 rx1.6`, stroke 1.3) with the shown band filled at opacity .5. The rail band is `x2.2`, the reading-pane band is `x9.8` (`4×9.6 rx1`). The band is drawn only while that pane is shown. |
| Divider | 1×22px, `--border-hair`. |
| New topic | Button, size sm (production `a.btn.btn-small` with a plus glyph) → `/compose`. Hidden on `/compose`. |
| Bell `a.forum-bar-bell` → `/notifications` | 30×30, radius 4px, `--text-muted`. Hover: bg `--surface-sunken`, ink `--text-body`. Glyph 17px, stroke 1.7. Count `.forum-bar-bell-count`: absolute `top:-3px; right:-5px`, `min-width:15px; padding:0 3px`, pill radius, bg `--gold-500`, ink `--ink-900`, Marcellus .6rem, `line-height:15px`, centred, capped at **99+**. The accessible name carries the count ("Notifications, 3 unread"). |
| Seat `a.forum-bar-user` | `gap:9px`. Monogram 30px. Name: Marcellus .8rem, ellipsis, `min-width:0`, so only the name clips. Hover ink `--accent`. Production renders a `<details>` account menu with the same anatomy plus a chevron. |
| Seat presence leaf | Drawn only when the viewer's own state is `online` or `away`. Dot 9×9 at `right:-1px; bottom:-1px`, `box-shadow: 0 0 0 2px --surface-raised`. Online uses `--presence`, away uses `--presence-away`. No leaf when presence is off (ADR 0031). |
| Log in (guest) | `padding:7px 16px; border-radius:7px`, bg `--accent`, ink `--accent-contrast`, Marcellus .76rem, `letter-spacing:.04em`. |

**Responsive behaviour**

- ≤1080px: gap 12, padding `0 16px`, the username hides, the right cluster stops shrinking.
- ≤900px: the wordmark, search label and kbd hide. Search collapses to a 44px round control.
- ≤720px: New topic and the divider hide. Pills reach a 44px min-height with `padding:0 10px`. Toggles grow to 44×44.

**Control links never underline on hover:** `.forum-bar a:hover, .board-rail-item:hover, .board-rail-route:hover, .presence-all:hover { text-decoration:none }`.

### 2. Board rail — `BoardRail` · `nav.board-rail[aria-label=Boards]`

**Order, top to bottom:** routes block (one route only, see the contract) · for each category, a heading then its board rows · footer slot (the roster).

| Part | Spec |
|---|---|
| Rail | `flex:0 0 272px; display:flex; flex-direction:column; gap:2px; padding:20px 12px 32px; overflow-y:auto`. bg `--surface-sunken`, `border-right:1px solid --border-hair`. |
| Category `.board-rail-cat` | `padding:16px 0 6px 12px`. Marcellus .62rem, `letter-spacing:.18em`, uppercase, `--text-faint`. Demo categories: *The Commons*, *Vilya · Expose*. |
| Board row `a.board-rail-item` → `/c/{slug}` | `display:flex; align-items:center; gap:8px; padding:7px 12px; border-left:2px solid transparent; border-radius:0 7px 7px 0`. `--text-muted`, Marcellus .84rem, `letter-spacing:.02em`. Name `.board-rail-name`: `flex:1`, ellipsis. |
| Row hover | bg `--surface-page`, ink `--text-body`. |
| Row active | `border-left-color:--gold-500`, bg `--brand-subtle`, ink `--on-brand-subtle`, `aria-current="page"`. **Keeps its href.** |
| Unread pill `.board-rail-unread` | `padding:0 6px`, pill radius, bg `--gold-soft`, ink `--gold-ink`, Mono .66rem. Hidden at 0. Production caps at 99+. `title` and `aria-label` read "N unread topic(s)". **None for a guest.** |
| Visibility chip `.board-rail-tag` | Shown for any board whose visibility is not `public`. `padding:1px 7px`, `1px solid --border-hair`, pill radius, Marcellus .58rem, `letter-spacing:.1em`, uppercase, `--text-faint`. Text is the visibility word (`restricted`, `private`). **On every rail except the compose picker.** |
| Compose picker row | The same anatomy, but a GET button that selects the board (`aria-pressed`). The chosen row is active and shows the note "posting here" (`.board-rail-note`, Mono .62rem). A board the viewer may not post to renders as `span.board-rail-item.is-locked` (`--text-faint`, `cursor:default`) with an 11px lock glyph and a `title` giving the reason. It is shown, not hidden. |
| Route row `.board-rail-route` | `gap:9px; padding:8px 12px`, same left rule and radius as board rows. 15px glyph, stroke 1.7. Label: Marcellus .88rem. Sub: Mono .64rem, `--text-faint`. Active state matches the board row. |
| Footer `.board-rail-foot` | `margin-top:24px; padding-top:16px; border-top:1px solid --border-hair`. Holds the roster. |

**≤900px:** the rail is 208px with `padding:14px 8px 24px`, and rows get `min-height:44px`. Production adds the phone drawer, the hamburger and a scrim. The design does not draw those.

### 3. Online roster — `PresenceList` in the rail footer · `section.presence-widget`

**Order:** title "Online" with the here-now count · a screen-reader live summary · up to **5** rows · "+N more" · **"See everyone online"** → `/users-online`.

| Part | Spec |
|---|---|
| Title `h2.presence-title` | `display:flex; gap:8px; margin:0 0 8px`. Marcellus .68rem, `letter-spacing:.16em`, uppercase, weight 400, `--text-faint`. Production makes "Online" a link to `/users-online`. |
| Count `.presence-count` | `min-width:20px; padding:1px 6px`, pill radius, bg `--surface-done`, ink `--on-done`, Mono .66rem. Shows members here **now** (demo: 34). |
| Live summary | `p.sr-only[aria-live=polite]`: "N members here now, M away." This is the only live region. The list itself is never live. |
| List `ul.presence-list` | `gap:1px`, no bullets. |
| Row `a.presence-person` → `/u/{username}` | `display:flex; align-items:center; gap:9px; padding:5px 8px; border-radius:7px`, ink `--text-body`. Hover: bg `--surface-sunken`, ink `--text-strong`. Background and colour transition over 140ms on `--ease-calm`. |
| Row avatar | Monogram 24px (font .62rem) with a dot 8×8 at `right:-1px; bottom:-1px`. On the rail the dot's ring is `0 0 0 2px --surface-sunken` (not raised). Online `--presence`, away `--presence-away`. |
| Avatars preference off | No monogram. A bare dot instead: 8×8, `position:static`, no ring. |
| Name `.presence-name` | EB Garamond .86rem, `line-height:1.25`, ellipsis, `gap:6px`. Then the **"you"** chip on the viewer's own row (`padding:0 5px; border-radius:4px; 1px solid --border-soft`; `--text-faint`; Marcellus .56rem, `letter-spacing:.1em`, uppercase). Then a **Staff** chip on staff rows (`--surface-staff` / `--on-staff`, same type). |
| Sub `.presence-sub` | "@handle · Here now" or "@handle · Away", plus " · {where}" when known. Marcellus .7rem, `letter-spacing:.02em`, `--text-faint`, ellipsis. `.presence-where` uses `--gold-ink`. |
| More and empty | `margin:6px 0 0; padding:0 8px`, .78rem, `line-height:1.4`, `--text-faint`. "+N more" is in Marcellus, `letter-spacing:.03em`. Empty copy: "No one is showing as online." |
| Foot `.presence-foot` > `a.presence-all` | `margin-top:10px; padding:0 8px`. Link: Marcellus .74rem, `letter-spacing:.04em`, `--text-muted`. Hover `--accent`. **Never hidden**: on an empty roster it is the only way left to look. |
| Compact density | Row `padding:3px 8px`, avatar 21px. |

**Rules**

- The viewer counts themselves and their row carries "you".
- A viewer whose presence is off is **not on the roll**, and their seat shows no leaf.
- Rows link to the profile on every rail.
- The widget renders on every route that has a rail, including the compose picker.

### 4. Operator bar — `AdminNav` · `.admin-bar`

**Identity row, left to right:** brand · "Back to the council" · right cluster (search · bell · seat · Log out · mode pill).

**Area tier:** pills for **Overview · Moderation · Content · People · Members · Appearance · Notifications · Integrations · Packages · Features · Settings**.

| Part | Spec |
|---|---|
| Block | Sticky, `z-index:20`, the same translucent raised background and blur as the member bar, `border-bottom:1px solid --border-hair`. |
| Identity row `.admin-bar-id` | `display:flex; align-items:center; gap:16px; height:58px; padding:0 26px`. |
| Brand → `/` | Mark 24px in `--accent`. Wordmark Cormorant 600, 1.25rem, `--text-strong`. |
| Exit `a.admin-bar-exit` → `/` | Chevron-left 13px, stroke 2, `gap:5px`. Marcellus .78rem, `letter-spacing:.03em`, `--text-muted`. Hover `--accent`. |
| Right cluster | `margin-left:auto; gap:12px`. |
| Search | `form[role=search]` → `/search`, `flex:0 1 190px`. Input: `padding:5px 11px; border:1px solid --border-soft`, pill radius, bg `--surface-page`, EB Garamond .84rem, placeholder "Search…". Focus: `border-color:--gold-500; box-shadow:0 0 0 3px --focus-ring`. |
| Bell → `/notifications` | Glyph 17px, `--text-muted`, hover `--accent`. Count: absolute `top:-5px; right:-7px`, 15px pill, bg `--gold-500`, ink `--ink-900`, Marcellus .6rem, **99+** cap. The accessible name carries the count. |
| Seat → `/u/{username}` | `gap:7px`. Monogram 28px (`.monogram-sm`), **seeded by username**. Name .86rem, ellipsis. Hover `--accent`. |
| Log out | POST `/logout`. Marcellus .78rem, `letter-spacing:.03em`, `--text-muted`, hover `--accent`. It carries a door glyph that shows only once the word hides. `aria-label="Log out"`. |
| Mode pill `.admin-bar-mode` | `padding:4px 12px`, pill radius, bg `--surface-review`, ink `--on-review`, Marcellus .72rem, `letter-spacing:.08em`, uppercase. Text: "Admin mode", or "Moderation" for a board moderator. |
| Tier `nav.admin-tier[aria-label="Admin areas"]` | `display:flex; gap:4px; padding:0 26px 9px; overflow-x:auto`. Thin scrollbar: 4px, `--border-soft` thumb. |
| Tier item | `padding:6px 10px; border-radius:7px`, Marcellus .8rem, `letter-spacing:.03em`, `--text-muted`. Hover: ink `--text-strong`, bg `--surface-sunken`. |
| Tier item active | bg `--brand-subtle`, ink `--on-brand-subtle`, weight 500, rendered as a `<span aria-current="page">` with **no href**. |
| Page head under the bar | h1: Cormorant 500, 2.1rem, `line-height:1.1`, `letter-spacing:-.01em`. Section tabs: `margin-top:16px`, `border-bottom:1px solid --border-hair`. Tab: `padding:9px 15px; margin-bottom:-1px`, Marcellus .84rem, `letter-spacing:.03em`. Active tab has a 2px `--gold-500` bottom rule with `--text-strong` ink. Inactive tabs are `--text-muted` with a transparent rule. |

**Rules**

- The operator cluster (bell, seat, Log out) renders on **every** area, whenever there is a viewer.
- A board moderator's tier is reduced to Moderation, and the Anti-abuse tab is withheld.

**Compact before cramped**

- ≤900px: the row becomes `min-height:52px; padding:7px 16px; gap:10px`. Search, the username and the Log-out word hide, and the door glyph shows. The tier padding becomes `0 16px 8px`.
- ≤860px: the row wraps, and the bell, Log out and tier items reach 44px targets.

---

## Route contract

Signed-in numbers are global. The **same two counts appear on every member route**: the Inbox pill count (the viewer's unread topics; demo 6) and the bell count (unread notifications; demo 3). A guest gets neither, and no rail unread pills.

| Route | Design template | Active pill | Rail | Active rail row | Notes |
|---|---|---|---|---|---|
| `/` | `board-index` | **Boards** | boards + roster | — | Also frames the Tags, Notifications and Connections panes. |
| `/c/{slug}` | `board-page` | **Boards** | boards + roster | that board | |
| `/tag/{slug}` | `board-index` (Tags) | **Boards** | boards + roster | — | |
| `/inbox` | `forum-inbox` | **Inbox** | boards + roster | — | Two toggles: rail (⌘B) and reading pane (⌘J). Both persist. |
| `/search` | `search` | none | boards + roster | — | **No search control in the bar.** The page owns the field. |
| `/compose` | `compose` | none | **picker** + roster | chosen board, "posting here" | No New topic button. Locked boards shown. No visibility chips. |
| `/t/{id}` | `thread-view` | none | boards + roster | the topic's board | The design's ⌘K opens a palette anchored to the search control. |
| `/u/{username}` | `user-profile` | none | boards + roster | — | A guest on a members-only profile gets the gated card. |
| `/leaderboard` | `leaderboard` | none | **OPEN**, see decisions | — | |
| `/users-online` | `users-online` | none | boards + roster | — | The roster's own link points at this page. |
| `/settings/*` | `account-settings` | none | boards + roster | — | Settings section nav sits inside main (`settings_nav.php`), sticky at `top:26px` within the pane. |
| `/admin/*`, `/mod/*` | `admin-*` (11) | tier: the area | none | — | Operator cluster on every area. |

**Active pill rule:** only `/`, `/c/*` and `/tag/*` light **Boards** (`topbar.php`'s `$isBoards`). `/inbox*` lights Inbox, and `/messages*` lights Messages. Everything else lights nothing.

**Demo rail data**, identical on every rail:

- *The Commons*: announcements, introductions, the-archive (1), the-valley.
- *Vilya · Expose*: interpretability (1), evaluations (2), audit-trails (2, `restricted`), capability-disclosure (`private`).

## Interactions & behaviour

- **⌘K / Ctrl+K:** goes to `/search`, or opens the surface's palette where it owns one. Bound once, in the bar.
- **⌘B:** toggles the rail. **⌘J** (production, inbox only) toggles the reading pane. Toggle state persists through `POST /settings/member-surfaces` without JavaScript; `app.js` applies it in place.
- **Hover and active states:** listed per part above. Chrome has no motion except the roster row's 140ms background/colour transition. Nothing bounces, and all motion respects `prefers-reduced-motion`.
- **Focus:** a 3px `--focus-ring` halo, as on every control in the system.
- **Loading** (roster): three skeleton rows (dot + bar at 68/56/44% width) with a calm 1.6s pulse. The count reads "·". No live summary while loading.
- **Empty** (roster): "No one is showing as online." The foot link stays.
- **Guest:** Log in (and Sign up). No toggles, compose, bell, seat, inbox count or rail unread pills. The roster still shows.
- **Live preferences:** *Show when I'm online* removes the viewer from the roll and the leaf from their seat. *Show avatars* switches roster rows to bare dots. Production threads `rail_avatars` into `presence_person.php`.

## State

| State | Source | Used by |
|---|---|---|
| `rail_open`, `inbox_reading_open` | `member_surfaces` (persisted per member) | bar toggles, rail render, body classes |
| `current_user` → name, username | session | seat, roster "you", guest branch |
| self presence state (`online` / `away` / `offline`) | `presence_snapshot()['self_state']` | seat leaf |
| `inbox_unread_count` | global | Inbox pill count |
| unread notifications | global; `app.js` polls `[data-bell-count]` | bell count |
| `nav` (categories → boards with `unread_count`, `visibility`, `slug`) | global | rail |
| `presence_snapshot()` → members, here, total, capped, `rail_limit` (5) | closure, built only when the rail renders | roster |
| `compose_boards`, `selected_board` | `/compose` only | picker mode |
| `active_thread_board_id` | `/t/{id}` | active rail row |

## Component changes in this review (mirror into `docs/design-system/imladris/`)

| Component | Change | Production today |
|---|---|---|
| `ForumNav` | The inbox count is never drawn without a `viewer`. | Already true: the Inbox pill renders only for a signed-in member. |
| `AdminNav` | `backHref` defaults to the Board index. New `notificationsHref`. The seat links to the profile (`viewer.href`). The monogram is seeded by `viewer.username`. The bell count caps at 99+ and its accessible name carries the count. | Exit and brand → `/`, bell → `/notifications`, seat → `/u/{username}`, monogram partial gets the username. Already true. |
| `PresenceList` | New `allHref` / `allLabel` render `.presence-foot > a.presence-all` inside the widget. Templates no longer hand-roll the link. | `sidebar.php` already renders this markup. |
| `BoardRail` (docs) | `tag` is documented as the visibility chip on every rail except the compose picker. | `sidebar.php` already does this. |

Template-level fixes: Account settings moved into the app shell. Counts are now the same on every member route. Thread view and Compose no longer light Boards. Leaderboard's rail gained unread pills. Visibility chips, roster links and "you" are now on every rail. User profile's guest rail lost its unread pills. The operator cluster is on all eleven admin areas. Full list: the design system's `CHANGELOG.md`, entry 2026-10-06.

## Production deltas to verify

1. **The bell beside the seat.** The design draws `a.forum-bar-bell` between New topic and the seat on every member route, and its CSS cites ADR 0032 (2026-09-20). The `topbar.php` read for this review carries Notifications only inside the account menu (`.identity-menu-panel a[data-bell]`). Confirm against `main`. If the standalone bell is missing, add it before the seat: `a.forum-bar-bell[href=/notifications][data-bell]` with `span.forum-bar-bell-count[data-bell-count][hidden]`, and put the count in the accessible name.
2. **The exit label.** Production says "Back to the forum". The design says "Back to the council" (the system's lexicon renames community to council). Pick one; this is copy, not layout.

These are design-side gaps the design system still owes, listed so nobody "fixes" production toward the design:

- `BoardRail` has no 99+ cap.
- The roster title "Online" is not a link.
- `AdminNav` does not model flag-off areas (production renders `.admin-tier-item.is-disabled` with the note "Disabled until the feature flag is enabled").

## Open decisions (do not implement yet)

1. **Leaderboard's rail.**
   - The design gives `/leaderboard` a routes block in place of the roster: Forum index, Forum inbox, Messages, Drafts, Following, Top contributors.
   - Production renders the standard rail with the roster.
   - One side changes; the design system's README currently records the design's version as a deliberate exception.
2. **Guest pills.** `ForumNav` shows Inbox and Messages to a guest; `topbar.php` hides them. Expect the design to follow production.
3. **Living brief.** The design template has no shell. Production renders it inside the thread page, so no production work is expected.
4. **`<main>` landmark.** `layout.php` gives every app route `<main id="main">`, but most design templates use a `<div>`. This is design-side.
5. **Active console area.** It drops its href, while the member pill and rail row keep theirs. Production matches the design on both sides, so leave it unless ADR 0032 #1 is meant to reach the console.

---

## Design tokens

### Colour

| Token | Day | Twilight |
|---|---|---|
| `--surface-raised` | `#FAF6EC` | `#1E2730` |
| `--surface-page` | `#F5EFE1` | `#161D24` |
| `--surface-sunken` | `#ECE4D2` | `#283440` |
| `--border-hair` | `#DED2B8` | `#36434F` |
| `--border-soft` | `#DCE3DD` | `#2C3742` |
| `--text-strong` | `#1B231D` | `#FAF6EC` |
| `--text-body` | `#313B33` | `#D7DCD2` |
| `--text-muted` | `#515C52` | `#AEB8B4` |
| `--text-faint` | `#5C685D` | `#94A095` |
| `--accent` | `#2E4A3A` | `#D2B062` |
| `--accent-contrast` | `#FAF6EC` | `#161D24` |
| `--brand-subtle` | `#EDF3ED` | `rgba(78,116,89,.20)` |
| `--on-brand-subtle` | `#24402F` | `#BCD0BF` |
| `--gold-500` | `#C29A44` | `#C29A44` |
| `--gold-soft` | `#F4EBCF` | `rgba(194,154,68,.16)` |
| `--gold-ink` | `#7E5F22` | `#D2B062` |
| `--ink-900` (bell-count ink) | `#1B231D` | `#1B231D` |
| `--surface-done` / `--on-done` | `#EDF3ED` / `#24402F` | `rgba(78,116,89,.20)` / `#BCD0BF` |
| `--surface-review` / `--on-review` | `#F4EBCF` / `#7E5F22` | `rgba(194,154,68,.16)` / `#DCC68A` |
| `--presence` | `#4E7459` | `#6E9479` |
| `--presence-away` | `#9A7530` | `#D2B062` |
| `--presence-offline` | `#94A095` | `#94A095` |
| `--focus-ring` | `rgba(194,154,68,.7)` | `rgba(210,176,98,.75)` |

### Layout, radius and motion

- `--topbar-h` 62px · `--sidebar-w` 272px (208px ≤900px) · `--maxw` 1280px · admin identity row 58px.
- Radii: `sm` 4px · `md` 7px · `lg` 12px · `pill` 999px.
- `--ease-calm` `cubic-bezier(.22,.61,.36,1)` · `--dur-fast` 140ms.

### Type

All type is serif and self-hosted WOFF2 under OFL. Production ships no webfonts and falls back to the system serif stacks below.

- **Cormorant Garamond** for wordmark and headings: 500 / 600.
  Stack: `"Cormorant Garamond", "Hoefler Text", Garamond, Georgia, "Times New Roman", serif`.
- **Marcellus** for pills, labels, rail rows and buttons: 400, letterspaced.
  Stack: `"Marcellus", "Optima", "Palatino Linotype", Palatino, "Cormorant Garamond", serif`.
- **EB Garamond** for body and roster names.
  Stack: `"EB Garamond", "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif`.
- **JetBrains Mono** for counts, notes and route subs.
  Stack: `"JetBrains Mono", "SFMono-Regular", ui-monospace, Menlo, Consolas, monospace`.

## Assets

- **House mark** (eight-pointed star). The design uses `EightPointStar` (`assets/elven-star.svg` in the design system). Production inlines the same path data in `topbar.php` and `_console.php`. Never redraw it.
- **Glyphs** are Lucide-style line icons drawn inline: magnifier, bell, lock, chevron-left, log-out door, and the rail route glyphs. The pane-toggle glyph is custom. Exact SVG is in `design/components/forum/ForumNav.jsx.txt`, `BoardRail.jsx.txt` and `admin/AdminNav.jsx.txt`.
- **Avatars** are monograms: initials on a tint picked deterministically from the username, using the sum of char codes mod 10 → `.mono-0…9`. See `design/components/identity/Monogram.jsx.txt`. Seed by **username** everywhere, or one person shows two colours.

## Files

```
design/
├─ components/forum/ForumNav.{jsx,d.ts}.txt      member top bar
├─ components/forum/BoardRail.{jsx,d.ts}.txt     board rail
├─ components/presence/PresenceList.{jsx,d.ts}.txt   Online roster
├─ components/admin/AdminNav.{jsx,d.ts}.txt      operator bar
├─ components/identity/Monogram.jsx.txt          avatar seeding
├─ components.css                                 all chrome CSS (.forum-bar*, .board-rail*, .presence-*, .admin-*)
├─ tokens/{colors,spacing,typography}.css
└─ templates/                                     reference compositions
   ├─ account-settings/AccountSettings.dc.html   the app shell around a page with its own section nav
   ├─ board-page/BoardPage.dc.html               active board row, Boards pill
   ├─ compose/Compose.dc.html                    the picker rail, no pill, no New topic
   ├─ forum-inbox/ForumInbox.dc.html             two pane toggles, Inbox pill
   ├─ leaderboard/Leaderboard.dc.html            the open routes-block rail
   └─ admin-overview/AdminOverview.dc.html       console bar, operator cluster, section tabs
```

Production files this contract governs: `templates/layout.php`, `templates/partials/{topbar,sidebar,presence_person,settings_nav}.php`, `templates/admin/_console.php`, `public/assets/app.css` ("Member chrome" block), `public/assets/app.js` (toggles, presence poll, bell count).
