# Handoff: Presence — the roll, the rail widget, the seat's leaf

## Overview

This bundle closes the loop on ADR 0031 (presence remediation) between `henryperkins/community-forums` and its design system.

The direction is unusual for a handoff, so it is stated first. **Production is ahead of the design on this surface.** ADR 0031 adopted the design system's `templates/users-online/UsersOnline.dc.html` into `templates/users_online.php`, found the design's presence classes had never rendered once anywhere, and fixed nineteen findings on the real page. The design system has now taken those decisions back — the shape of the roll, the copy, the away colour, the cap, the viewer on their own roster, the `role="img"` dot on `/u/{name}`, the seat's leaf honouring the toggle — **and** folded in the eight CSS corrections `app.css` raised on the real ground (its ADR 0031 block, lines 16591–16680, names each of them as "what the mirror does NOT have").

So the work this bundle asks for is small and specific:

1. **Refresh the mirror.** `docs/design-system/imladris/` is source-only (per its own `PREVIEW_STATUS.md`) and stale for presence: its `templates/users-online/` is the pre-adoption design (a hand-rolled topbar and rail, *Wardens* and *New this week* tabs), and it has no `components/presence/`, no `ForumNav`, no `BoardRail`. Everything under `design/` here lands at its canonical path inside the mirror.
2. **Regenerate `public/assets/imladris.css`** from the refreshed `tokens/` + `components.css`, then **retire the restatements** in `app.css`'s ADR 0031 block that the regenerated layer now carries (listed below). Mind `AppImladrisFidelityTest`, which pins the transfer block character-for-character.
3. **Three optional deltas** where the design and `users_online.php` still differ (a magnifier in the search field; the content column's width; the guidance grid's breakpoint). None is a defect; each is a choice, described below.
4. **Do not build** the things ADR 0031 deferred and the design still draws behind a toggle — listed under *Do not build*.

There is nothing to implement from scratch. The value of the bundle is that the source of truth and the shipped page now agree, so the next fidelity audit finds no drift here.

## About the design files

The files in `design/` are **design references authored in HTML** — prototypes showing intended look and behaviour. They are **not production code to copy.**

The template is a Design Component (`.dc.html`): a template of markup plus a small JavaScript logic class, rendered by `support.js`. Constructs like `<sc-if>`, `<sc-for>`, `{{ hole }}` and `<x-import>` belong to that runtime and have no meaning outside it. **Do not port them.** The `.jsx` components are React and are what the design-system compiler builds into `_ds_bundle.js`; the mirror in this repository has no compiler, so — as `PREVIEW_STATUS.md` already says — none of these files is executable there. Read them for structure, values and behaviour.

The target codebase is **PHP 8 server-rendered templates** (`$this->layout(...)` / `$this->partial(...)`), progressively enhanced with vanilla JS (`public/assets/app.js`), styled by `public/assets/app.css` over the generated `public/assets/imladris.css`. Strict CSP, no inline handlers, everything must work with JavaScript off. `PRODUCTION.md` in the design system holds that contract.

If you are implementing this somewhere other than that codebase, recreate the designs in whatever environment already exists there using its established patterns; if none exists yet, choose the framework that suits the project. The HTML is the specification, never the artifact to ship.

## Fidelity

**High-fidelity.** Colours, typography, spacing and states are final and given as token names. Reproduce them precisely — and use the **token names, never resolved hex values**. The twilight register (`[data-theme="dark"]`) flips every token; a literal hex is a bug that will not flip. Hex appears in this document only so you can verify you have the right token.

---

## What production already has (verified at `main` = `966a5b1c`)

Do not redo these. They are listed so the design's decisions can be traced to the code that already carries them.

| Decision | Where it lives upstream | Design file that now agrees |
|---|---|---|
| One presence ladder — flag → id → status → `show_presence` → `profile_visibility` → recency → blocks | `PresenceService::state()` | — (service concern) |
| Away state: seen within 300s = here now; within 900s = stepped away; beyond = off the roll | `PresenceConfig` (`online_window_seconds`, `away_window_seconds`) | `presence.card.html` legend copy |
| Away painted gold, not `--amber` | `app.css` `:root { --presence-away: var(--gold-700) }` / dark `--gold-400` | `tokens/colors.css` — now declared in both registers, so the `app.css` declaration becomes redundant once `imladris.css` is regenerated |
| One row anatomy for rail, roll and poller | `templates/partials/presence_person.php`; `app.js` builds the same nodes and diffs `data-presence-sig` | `PresenceList.jsx` → `PresenceRow` |
| Rail cap 5, `data-presence-limit`, server-rendered `+N more` | `partials/sidebar.php` (`$presenceLimit`, `$presenceMore`) | `PresenceList` `max=5`, `total` |
| `count` means here-now; emptiness decided by `total` | `sidebar.php` (`here` badge; `total === 0` → empty) | `PresenceList` `here` / `total` (`count` kept as alias) |
| Viewer on their own roster, *you* chip | `presence_person.php` `is_self` → `.presence-you` | `PresenceRow` `self` |
| Widget never hidden; `[hidden]` made to work | `app.css` guard; nothing sets it on the widget | `components.css` guard, same set |
| Exactly one live region, an `sr-only` summary | `sidebar.php` `[data-presence-summary]` | `PresenceList` summary `<p class="sr-only" aria-live="polite">` |
| Dot `aria-hidden` everywhere; state in text on `.presence-sub` | `presence_person.php` | `PresenceRow` |
| The one exception: `/u/{name}` keeps a named dot with `role="img"` | `templates/profile/show.php` | `templates/user-profile/UserProfile.dc.html` — fixed here; it had `aria-label` on a bare `<span>` |
| Seat's leaf honours the viewer's toggle and the flag | `partials/topbar.php` (`self_state`) | `ForumNav.jsx` — `viewer.presence` draws the leaf for `online`/`away` only |
| Roll is the presence pool only; filters Everyone here · Here now · Away | `templates/users_online.php` | `UsersOnline.dc.html` (directory filters behind an off-by-default toggle) |
| Members-only profiles withheld from guests and from `/presence` | `PresenceService` | `UsersOnline.dc.html` `membersOnly` rows vanish for a guest |
| `last_seen_at` off the wire; sub-line is `@handle · Here now` / `· Away` | `PresenceController` | `PresenceRow` — no timestamp, no `where` on roll rows |

## Refresh the mirror

Copy the contents of `design/` over `docs/design-system/imladris/`, path for path. **The `.jsx`, `.d.ts` and `.card.html` files carry a `.txt` suffix in this bundle** — the design system's own compiler would otherwise register the copies as duplicate components. Drop the suffix as you copy (`ForumNav.jsx.txt` → `ForumNav.jsx`).

```
design/templates/users-online/*            → docs/design-system/imladris/templates/users-online/   (replaces the stale template)
design/templates/user-profile/UserProfile.dc.html → …/templates/user-profile/                     (the role="img" dot)
design/components/presence/*               → …/components/presence/                               (new directory)
design/components/forum/ForumNav.{jsx,d.ts}, BoardRail.{jsx,d.ts}, chrome.card.html → …/components/forum/  (new files; existing forum files untouched)
design/components/identity/Monogram.jsx    → …/components/identity/                               (offline dot now --presence-offline)
design/components/doc.css                  → …/components/
design/tokens/*.css, styles.css, components.css → …/                                             (the stylesheet closure)
```

`ds-base.js` in the template folder resolves `../..` — inside the mirror that is `docs/design-system/imladris/`, where `styles.css` lives. There is no `_ds_bundle.js` there and there should not be one (see `PREVIEW_STATUS.md`); the template documents, it does not run.

## Regenerate `imladris.css`, then prune `app.css`

Once `imladris.css` is rebuilt from the refreshed `tokens/colors.css` and `components.css`, the following rules in `app.css`'s ADR 0031 block are **restatements** of what the layered file now carries, and can be removed — one at a time, running `AppImladrisFidelityTest` and `users-online-remediation.spec.ts` between each:

| `app.css` rule (ADR 0031 block) | Now in the system as |
|---|---|
| `:root { --presence-away; --presence-offline }` + dark blocks | `tokens/colors.css` — both tokens, both registers. **Keep the bare-`:root` declaration until the regenerated `imladris.css` is confirmed to declare `--presence` in light too**; the original reason it was declared unlayered was that `--presence` only had a light value via the layer. |
| `.presence-dot.is-away` | `components.css` line ~130 |
| `.presence-dot-bare { position: static; … 8px }` | `components.css` — now 8px and `position: static` |
| `.presence-list { list-style: none; margin: 0; padding: 0 }` | `components.css` `.presence-list` (already carried the reset) |
| `.presence-widget[hidden], .presence-row[hidden], .presence-more[hidden], .presence-empty[hidden]` | `components.css`, same four, `display: none !important`. `.users-online-empty[hidden]` is an app class and stays in `app.css`. |
| `.presence-person .monogram { 24px }` | `components.css` — binds to `.monogram` now, not `.monogram-sm` |
| `.sidebar .presence-person .avatar-wrap .presence-dot { box-shadow: 0 0 0 2px var(--surface-sunken) }` | `components.css` — `.board-rail …, .sidebar …` |
| `a.presence-person:hover { text-decoration: none }` | `components.css` `a.presence-person:hover` |
| `.presence-you` (outline chip) | `components.css` — outline, `--text-faint`, uppercase, `.56rem` / `.1em` |
| `[data-density="compact"] .presence-person { padding: 3px 8px }` + monogram 21px | `components.css`, same two |
| `.presence-all` (`app.css` line 15011) | `components.css` `.presence-all` — `--font-label` `.74rem` `.04em` `--text-muted`, hover `--accent`. **Compare before deleting**; the app's rule was not read for this bundle. |

What stays in `app.css` by design: the `/users-online` shell (`.users-online*`), the guidance strip (`.presence-guidance*`), `.btn.is-disabled`. The design carries those inline on the page rather than as system classes, so the app layer is their right home.

---

## Screens / views

### 1. `/users-online` — the roll (`design/templates/users-online/UsersOnline.dc.html`)

**Purpose.** See who chose to be seen, and go to them.

**Shell.** The standard member shell: topbar (`ForumNav`, 62px) over a row of rail (`BoardRail`, 272px, `--surface-sunken`, `border-right: 1px solid var(--border-hair)`) + one scrolling column. No pill in the topbar is active — `/users-online` is not a surface of its own. The rail is byte-identical to every other route's, including its own *See everyone online* link pointing at this page.

**Column.** `flex: 1 1 0; min-width: 0; overflow-y: auto; padding: 26px 40px 90px`, inner wrapper `max-width: 860px; margin: 0 auto`, entering with `uoRise` (200ms ease-out, `translateY(6px)` → 0, opacity 0 → 1). Production uses `width: min(100%, 960px)`; 860 gives three columns of rows at 1280px, 960 gives four. Either is acceptable — pick one and keep it.

**Hero.**
- Eyebrow **Presence** — `--font-label` `.68rem`, `letter-spacing: .18em`, uppercase, `--gold-ink`.
- `<h1>` **Who is at the council** — `--font-display` 500, `2.2rem` / `1.12`, `letter-spacing: -0.01em`, `--text-strong`, `margin: 7px 0 0`.
- Lede — `max-width: 62ch`, `1rem` / `1.55`, `--text-muted`, `text-wrap: pretty`, `margin: 8px 0 0`: *"Members who chose to show their presence and have been here recently. A leaf means here now; amber means stepped away."* (Verbatim from production. Note the word *amber* against the gold token — production's own inconsistency; if it changes upstream, change it here.)

**Controls row** — `display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 22px 0 6px`.
- **Filters** — `<nav aria-label="Filter by presence">`, a segmented pill: `display: inline-flex; gap: 4px; padding: 3px; background: var(--surface-sunken); border-radius: 999px`. Three tabs, each with its count: **Everyone here** (total) · **Here now** (here) · **Away** (away). A tab is `display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px 6px 14px; border-radius: 999px; --font-label .76rem; letter-spacing: .03em`. Active: `background: var(--surface-raised); color: var(--text-strong); box-shadow: var(--shadow-sm)`, `aria-current="page"`. Inactive: transparent, `--text-muted`, hover `--text-strong`. The count is `--font-mono` `.68rem`, `letter-spacing: 0`; `--text-muted` on the active tab, `--text-faint` otherwise. In production these are GET links (`?filter=online`) with `is-active`; counts get a `+` suffix when the roster cap bites (`capped`).
- **Search** — `<form role="search">`, `display: flex; align-items: center; gap: 8px; flex: 1; min-width: 220px; max-width: 340px`. The input: `type="search"`, placeholder and `aria-label` **Find a member**, `autocomplete="off"`, `width: 100%; padding: 8px 12px 8px 33px; border: 1px solid var(--border-soft); border-radius: 999px; background: var(--surface-raised); --font-body .93rem; --text-body`. Focus: `border-color: var(--gold-500); box-shadow: 0 0 0 3px var(--focus-ring); outline: none`. **Delta:** the design draws a 14px magnifier (stroke `--text-faint`, 1.9, round caps) at `left: 12px`, vertically centred, hence the 33px left padding; production has no glyph and `padding: 8px 14px`. Adding the glyph is optional — it is a `<svg aria-hidden>` before the input, no JS. When the query is non-empty a **Clear** control follows the field: `--font-label .74rem .04em --text-muted`, hover `--accent` (production: `a.users-online-clear` to the query-less URL — keep it a link). Production also renders a **Search** submit button (`.btn.btn-small`); it is required for the no-JS GET form and the design does not object to it — the prototype filters as you type only because it has no server.
- **Tally** — `margin-left: auto` (right-aligned; production `margin-inline-start: auto`), `--font-label .74rem .04em --text-faint`, `aria-live="polite"`: **N members** / **1 member** (production adds `+` when capped).

**The roll.** A card — `margin-top: 14px; padding: 12px 10px; background: var(--surface-raised); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm)` — containing `<ul class="presence-list presence-grid">`: `display: grid; grid-template-columns: repeat(auto-fill, minmax(232px, 1fr)); gap: 2px 10px`. No heading over the grid: the tabs and the tally already say what it is. Twelve rows per page (`presence.page_size`). Each row is the shared anatomy below.

**Empty** — replaces the card: `margin: 22px 0 0; padding: 26px 20px; text-align: center; --surface-raised; 1px --border-hair; --radius-lg; .95rem / 1.5; --text-muted; text-wrap: pretty`. One sentence, chosen by cause, in this order: a search → *No member on the roll matches “{q}”.* · filter Away → *No one has stepped away.* · filter Here now → *No one is at the council right now.* · otherwise → *No one is showing as online right now.* (Production `.presence-empty.users-online-empty`, same four strings.)

**Pager** — only when there is more than one page. `<nav aria-label="Roll pages">`, `display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 18px`. **Previous** / **Next** are `padding: 7px 15px; border-radius: var(--radius-md); border: 1.5px solid var(--border-soft); background: transparent; --text-body; --font-label .78rem .04em`, hover `background: var(--surface-sunken)`, `rel="prev"` / `rel="next"`. At either end the control is a non-interactive `<span aria-disabled="true">` at `opacity: .45` (production `.btn.btn-small.is-disabled`). Label between: **Page X of Y**, `--font-label .76rem`, `letter-spacing: .06em`, `--text-faint`.

**Guidance strip** — `display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 30px` (production: `repeat(3, 1fr)` collapsing to one column ≤860px — same intent). Three cards, `padding: 15px 16px; --surface-raised; 1px --border-hair; --radius-lg`. Each: a label row (`display: inline-flex; align-items: center; gap: 7px; --font-label .72rem .06em --text-strong`) of an 8px `aria-hidden` dot and the state, then a paragraph `margin: 6px 0 0; .88rem / 1.45; --text-muted; text-wrap: pretty`:
- `--presence` · **Here now** — *Seen in the last few minutes. The leaf is the only mark presence gets.*
- `--presence-away` · **Stepped away** — *Idle for a while but still around. No one is chased off the roll for being quiet.*
- `--presence-offline` · **Not shown** — *Members can turn presence off in their privacy settings. They are here; you simply are not told.*

**Foot** — `margin: 22px 0 0; max-width: 66ch; .84rem / 1.5; --text-faint`: *Presence is optional, and this roll is never the whole community. Turn yours off any time under **privacy settings**.* — the link (`/settings/privacy`) in `--text-muted`, underlined in `--border-soft` with `text-underline-offset: 2px`, hover `--accent`.

### 2. The presence row — one anatomy (`design/components/presence/PresenceList.jsx` → `PresenceRow`; upstream `partials/presence_person.php`)

```
li.presence-row[data-presence-row=@handle][data-presence-sig]
  a.presence-person[href=/u/handle][data-presence-state=online|away][data-presence-self]
    span.avatar-wrap                         (or, avatars off: span.presence-dot.presence-dot-bare[.is-away])
      span.monogram.mono-N   24×24, .62rem   (21×21 under [data-density="compact"])
      span.presence-dot[.is-away]  8×8, absolute right/bottom −1px, ring 2px of the ground it sits on
    span.presence-person-id                  flex column, gap 1px, min-width 0
      span.presence-name                     .86rem / 1.25, nowrap, ellipsis; flex row gap 6px
        [span.presence-you  "you"]           outline chip: 1px --border-soft, --text-faint, --font-label .56rem, .1em, uppercase, radius-sm, padding 0 5px
        [span.presence-staff "Staff"]        --surface-staff / --on-staff, same size
      span.presence-sub                      --font-label .7rem .02em --text-faint, nowrap, ellipsis:  "@handle · Here now" | "@handle · Away"
```

Row: `display: flex; align-items: center; gap: 9px; padding: 5px 8px` (`3px 8px` compact), `border-radius: var(--radius-md)`, `--text-body`, no underline. Hover on the link: `background: var(--surface-sunken); color: var(--text-strong); text-decoration: none`. The dot is decorative (`aria-hidden`) and the state is always in the sub-line text. `data-presence-sig` = `handle:state:s|-:y|-` is what the poller diffs, so an unchanged row keeps its node (and its server-rendered monogram palette).

### 3. The rail widget (`PresenceList`, list layout; upstream `partials/sidebar.php`)

```
section.presence-widget[data-presence][data-presence-limit=5][data-presence-poll]
  h2.presence-title   flex, gap 8px, --font-label .68rem .16em uppercase --text-faint, margin 0 0 8px
    a[href=/users-online] "Online"
    span.presence-count   here-now: inline-flex, min-width 20px, padding 1px 6px, radius 999, --surface-done / --on-done, --font-mono .66rem  ("N+" when capped)
  p.sr-only[aria-live=polite]   "N members here now, M away."   — the ONLY live region
  ul.presence-list      flex column, gap 1px; first 5 rows
  p.presence-empty[hidden unless total === 0]   "No one is showing as online."   .78rem / 1.4 --text-faint, margin 6px 0 0, padding 0 8px
  p.presence-more[hidden unless total > shown]  "+N more"   same, --font-label, .03em
  p.presence-foot  margin-top 10px, padding 0 8px
    a.presence-all[href=/users-online] "See everyone online"   --font-label .74rem .04em --text-muted, hover --accent
```

The widget sits in the rail's footer slot: `margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-hair)`. Its dot rings are punched out of `--surface-sunken`, the rail's ground. **Never hidden**, with or without JS — it is the shell's only route to the roll. The design's `+N more` is `total − shown`, exactly as `sidebar.php` computes it.

### 4. The seat's leaf (`ForumNav.jsx`; upstream `partials/topbar.php`)

The viewer's 30px monogram in the topbar wraps in `.avatar-wrap` with a `.presence-dot[.is-away]` **only when the viewer's own state is `online` or `away`** — the same ladder as everyone else's row, so a member who switched presence off sees no leaf beside their own name, and the leaf goes out with the flag. `aria-hidden`: it is the viewer's own avatar, and the account menu says presence in words.

### 5. The profile dot (`UserProfile.dc.html`; upstream `profile/show.php`)

On `/u/{name}` the 14px dot on the 84px cover avatar (`right: 4px; bottom: 4px`, ring `3px --twilight-800` on the twilight cover) is the **one** dot with an accessible name, because the row has no sub-line: `role="img" aria-label="Here now"` / `"Stepped away"`, `title` the same, `--presence` / `--presence-away`. Offline draws nothing. An `aria-label` on a bare `<span>` is not exposed at all — `role="img"` is what makes it a thing.

---

## Interactions & behaviour

- **Filters and paging are URLs.** `?filter=online|away`, `?q=`, `?page=` — every control a link or a GET form, so the page is complete without JavaScript (`users-online-remediation.spec.ts` runs the filters, search and paging with JS disabled). The prototype's in-page state is a stand-in for the query string.
- **Search** is literal text, case-folded, against display name and handle; a wildcard is just a character. It cannot see past `show_presence`, `profile_visibility` or blocks — the search runs over the roster the ladder already produced.
- **The poller** (`app.js` `shortPoll`) refreshes the rail widget in place every 60s: diffs `data-presence-sig` per row, replaces only changed rows, updates `here` badge / summary / `+N more` / empty, pauses on `visibilitychange`, backs off on failure, **stops on 404**. A rebuilt row wears the neutral monogram palette until the next full load (ADR 0031 D8 — accepted).
- **Twilight.** Every colour above is a token; the away dot must visibly change between registers and must not collapse onto the leaf in dark (`--gold-400` vs `--green-400`). The spec measures this.
- **Reduced motion.** `uoRise` and `presencePulse` are covered by the global clamps already in `app.css`. Do not add a third.
- **Hover** states are listed inline above; there are no focus styles beyond the system's `--focus-ring` on the search field and the UA ring on links and buttons.

## State management

Server-side, all of it: `PresenceController` builds `$directory = { members, here, away, total, shown, capped, page, pages, filter, search }` and `shareViewGlobals()` exposes `presence_snapshot` as a **closure** (built only by the two templates that render the widget). The prototype's `dataState`, `signedIn`, `avatars` and `directoryFilters` tweaks are review knobs for the design, not state to implement — `avatars` corresponds to the existing `rail_avatars` reading preference, which `presence_person.php` already honours.

## Do not build

Deferred upstream in ADR 0031 with the evidence each needs; the design keeps them visible behind toggles so they are not lost, and **off by default** so nothing here ships them by accident.

- **The full member directory** — *Everyone*, *Staff*, *New this week* over every member including those who never opted into presence (`directoryFilters` tweak). A public, paginated, searchable roll of everyone is a disclosure decision, to be taken with whether the product has a public directory at all and how it relates to `hide_from_leaderboard`.
- **Loading skeleton and the *Presence is not answering* card** (`dataState` tweak). The roll is server-rendered on first paint, so a skeleton has nothing to cover; the error card needs a client failure the poller does not yet surface.
- **`.presence-where`** — the *#board* sub-line. No column exists; the class stays unused. (The design's rails still carry `where` on their sample roster; the roll's rows do not.)
- **A *Warden* chip.** The chip says **Staff**, admin-only, because the post bit already says Staff.

## Design tokens

Presence-specific (`design/tokens/colors.css`): `--presence` (= `--leaf` `#4E7459`; `--green-400` `#6E9479` in twilight) · `--presence-away` `--gold-700` `#9A7530` / twilight `--gold-400` `#D2B062` · `--presence-offline` `--ink-300` `#94A095` (both registers) · `--surface-staff` / `--on-staff` (the chip) · `--surface-done` / `--on-done` (the here-now badge).

Shared: `--surface-page`, `--surface-raised`, `--surface-sunken`; `--border-hair`, `--border-soft`; `--text-strong` ink-900, `--text-body` ink-700, `--text-muted` ink-500, `--text-faint` ink-400; `--gold-ink` `#7E5F22` (small-text gold, AA on parchment), `--gold-500` `#C29A44` (focus border), `--focus-ring`; `--accent` (green-700 light / gold-400 twilight); `--rust` (the error card's rule).

Type: `--font-display` Cormorant Garamond · `--font-label` Marcellus · `--font-body` EB Garamond · `--font-mono` JetBrains Mono. Radius: `--radius-sm` 4 · `--radius-md` 7 · `--radius-lg` 12 · pill 999. Shadow: `--shadow-sm`. Motion: `--dur-fast` 140ms, `--ease-calm`.

## Assets

- **Eight-pointed elven star** — the house mark in the topbar, `assets/elven-star.svg` (already in the mirror). Never redrawn.
- **Monograms** — `Monogram` / `partials/monogram.php`: initials on a ground tinted deterministically from the username (`mono-N`); real avatars replace them when present.
- **Lucide**-style line icons for the magnifier (stroke 1.9, round caps) and the topbar's panel toggles.
- **Fonts** ship self-hosted as WOFF2 in the mirror's `assets/fonts/`; `design/tokens/fonts.css` declares them.

## Files

**`design/`** — mirrors the design system's own tree, so it copies straight over `docs/design-system/imladris/`.
- `templates/users-online/UsersOnline.dc.html` — the roll, in the shared shell. Its logic class holds the sample roster (34 here · 8 away, matching the rail's *Online 34*), the four empty sentences and the pager. `ds-base.js`, `support.js` — the prototype runtime, not for porting.
- `templates/user-profile/UserProfile.dc.html` — for the `role="img"` dot and its away state only; the rest of the profile is unchanged from the mirror's copy.
- `components/presence/PresenceList.jsx` + `.d.ts` + `.prompt.md` — `PresenceList` (the widget; `here`, `total`, `max`, `layout`, `avatars`, `loading`, `footer`) and `PresenceRow` (the anatomy). `presence.card.html` — the legend: each dot state, `gilt` / `staff` / `self`, `avatar={false}`, loading, empty, grid.
- `components/forum/ForumNav.jsx` + `.d.ts` — the topbar; `viewer.presence` is the seat's leaf. `BoardRail.jsx` + `.d.ts` — the rail with its footer slot. `chrome.card.html` — both, side by side, with the invariant spelled out.
- `components/identity/Monogram.jsx` — `presence` prop draws the wrapped dot; offline uses `--presence-offline`.
- `styles.css` (entry) → `tokens/{fonts,colors,typography,spacing}.css`, `components.css`, `components/doc.css` — the stylesheet closure. The presence block is `components.css` ~lines 121–140 (dots) and ~1386–1460 (widget, row, grid).

**`screenshots/`** — the prototype at its 1240×900 preview size, signed in as Erestor.
- `01-roll-light.png` · `02-roll-twilight.png` — the roll in both registers. Check the away dot: gold in both, and distinct from the leaf in twilight.
- `03-roll-search.png` — a query (`el`) with the Clear control and the tally updated.
- `04-roll-away-filter.png` — the Away tab, eight rows, no pager.
- `05-rail-widget.png` (2×) — the rail's footer slot: *Online 34*, five rows, *+29 more*, *See everyone online*.
- `06-topbar-seat.png` (2×) — the shared bar; the seat carries the viewer's own leaf.

One caveat on reading them: the captures are DOM re-renders, and their type runs a few percent wider than the browser's, so the roll's sub-lines were captured with their ellipsis rule lifted — otherwise `@galadriel · Here now` showed as `Here no…` in the frame while fitting comfortably on the live page. Any truncation you see in a capture that the prototype does not show is the capture's.

**In the design system, not bundled:** `github.md` (provenance and the open-drift ledger — the entry dated 2026-09-12 is this work), `PRODUCTION.md`, `production-contract.json` (flag map re-derived against `966a5b1c`; presence's six config knobs recorded).
