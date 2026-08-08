# D-shell — the shared admin/account chrome

Screen: **shell** (topbar / page container / eyebrow+H1+mode chip / section tab strip)
Design source: the top of all eleven `.dc.html` screens + `components/admin/AdminNav.jsx`
Production home: `templates/layout.php`, `templates/admin/_nav.php`, `templates/partials/settings_nav.php`,
`templates/partials/topbar.php`, `templates/partials/sidebar.php`, `public/assets/app.css`, `public/assets/app.js`

---

## 0. The headline: the design's shell changed under us, and it now agrees with production

The brief describes a 58px topbar + `Operator desk · Area` eyebrow + 2.4rem H1 + right-hand
`Admin mode` chip + a two-to-three-tab strip. That is the **old** generation — the six admin screens
and `AccountSettings` dated `2026-08-03 06:01`.

Four screens landed at `2026-08-03 20:20–20:23` and are untracked in git
(`admin-members`, `admin-features`, `admin-integrations`, `admin-packages`). All four **deleted**
that chrome and replaced it with one line:

```html
<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="members" hint-size="100%,101px"></x-import>
```
— `AdminMembers.dc.html:22`, `AdminFeatures.dc.html:22`, `AdminIntegrations.dc.html:22`, `AdminPackages.dc.html:22`

That component is real and readable at `docs/design-system/imladris/components/admin/AdminNav.jsx`.
Its demo card states the intent verbatim (`components/admin/admin.card.html`, the `.note`
paragraph):

> "The tier is a pill row, the page's own sections are underline tabs, and the page heading sits
> between them — three signals keeping the two ranks apart. Measured against the pages it replaces,
> this chrome is 10px **shorter**: the redundant "Operator desk · Area" kicker is gone, the mode
> pill moved into the identity row, and the heading drops from 2.4rem to 2.1rem."

`README.md:114` states the target IA as fact:

> "**Operator surfaces** — ten `admin-*` templates, all wearing `AdminNav`"

And `AdminNav.jsx:8-19` enumerates `ADMIN_AREAS` — **ten** areas, in console order:
`overview · content · people · members · appearance · notifications · integrations · packages ·
features · settings`.

**Consequence for this pass.** The brief's premise — "the design uses a horizontal tab strip
scoped to one section while production uses a vertical grouped rail across all sections" — is
half-true and now resolves cleanly. The current design has **two ranks**:

| Rank | Design (current) | Production |
|---|---|---|
| Cross-section (all admin areas) | `AdminNav` — 10-item horizontal **pill tier**, 101px block | `admin/_nav.php` — 8-group × 26-item **vertical 224px rail** |
| Within-section | per-screen **underline tab strip**, 2–3 tabs | *nothing — every tab is a separate page with no local nav* |

So it is not tabs-vs-rail. It is: **the design has a second, finer rank of navigation that
production entirely lacks**, and the design's *first* rank is horizontal where production's is
vertical. Both are cross-section navigations of the same route set. They reconcile.

**Blocker to record for Stage 2:** `AdminNav.jsx` references `.admin-bar`, `.admin-bar-id`,
`.admin-bar-brand`, `.admin-bar-wordmark`, `.admin-bar-exit`, `.admin-bar-mode`, `.admin-tier`,
`.admin-tier-item`, `.is-active` — and **none of those classes has any CSS anywhere in the design
system**. `grep -rn "admin-bar" docs/design-system/imladris` returns exactly one file: the JSX.
`components.css` was last written at 19:12; `AdminNav.jsx` at 20:22. The visual spec for the new
shell is therefore incomplete: we have the anatomy, the element order, `hint-size="100%,101px"`,
and the card's three metric statements (2.1rem heading, mode pill in identity row, kicker gone),
and nothing else. Stage 2 must either wait for the next DesignSync pull or derive the pill-tier
skin from the six older screens' inline styles plus `.pill` (`components.css:44`) and the kit's
`.topbar-nav` (`ui_kits/retroboards/kit.css:31`, allowlisted only as reference).

---

## 1. Section-order comparison

### 1a. Old-generation admin screens (6 screens, 06:01)

| # | Design (verbatim) | Production equivalent (path:line) |
|---|---|---|
| 1 | sticky 58px topbar: eight-point star + wordmark `Imladris` + `‹ Back to the council` | `templates/partials/topbar.php:6-60` via `layout.php:51` — sticky 62px, operator logo/name, no back link |
| 2 | centred container, per-screen max-width | `.admin` `max-width: 1260px` inside `.app-shell` `max-width: 1280px` minus 272px sidebar (`app.css:2808`, `:110-113`, `imladris.css:348-349`) |
| 3 | eyebrow `Operator desk · Content` | `<span class="eyebrow">Operator desk</span>` — `templates/admin/dashboard.php:6`; **only 8 of 39 admin pages carry one** |
| 4 | H1 2.4rem display face | `.admin-head h1 { font-size: 1.9rem }` — `app.css:2825-2828`; `templates/admin/dashboard.php:7` |
| 5 | right-hand chip `Admin mode` | `<span class="pill pill-admin">Admin mode</span>` — `templates/admin/dashboard.php:9`, all 39 pages |
| 6 | underline tab strip (2–3 tabs, `aria-label="Content sections"`) | **absent** |
| 7 | intro `<p>` 66–68ch | `<p class="pane-intro">` — `templates/admin/dashboard.php:15`, `app.css:2936-2940`; 7 of 39 pages |
| — | *(no cross-section nav — a dead `<span>` stands in, see §3 C3)* | `templates/admin/_nav.php:56-91` — real 8-group 26-item rail, `app.css:2839-2922` |

### 1b. New-generation admin screens (4 screens, 20:20)

| # | Design (verbatim) | Production equivalent |
|---|---|---|
| 1 | `AdminNav` identity row: mark + `Imladris` + `‹ Back to the council` + `Admin mode` pill | `partials/topbar.php` (brand + `Admin` link) — no back link, no mode pill in the bar |
| 2 | `AdminNav` area tier: 10 pills, `<nav aria-label="Admin areas">` | `admin/_nav.php` vertical rail, `<nav aria-label="Admin navigation">` (`_nav.php:56`) |
| 3 | container `max-width: 1160px; padding: 22px 28px 110px` | `.admin` `max-width:1260px; padding:24px 28px 64px` (`app.css:2808-2811`) |
| 4 | H1 2.1rem, no eyebrow, no mode chip | eyebrow + 1.9rem H1 + chip (`app.css:2822-2834`) |
| 5 | underline tab strip, `margin: 16px 0 0` | absent |
| 6 | flash `role="status"` **below** the tab strip (`AdminMembers.dc.html:35-40`) | `partials/flash.php`, rendered by `layout.php:61` **above** `.admin` entirely |
| 7 | section body | `.admin-pane` (`app.css:2923-2935`) |

### 1c. Account settings

| # | Design (`AccountSettings.dc.html`) | Production |
|---|---|---|
| 1 | topbar + back link + **right cluster**: 30px monogram, member name, `Log out` button (`:30-34`) | `partials/topbar.php:29-52` — monogram, name, log-out form. **Already matches.** |
| 2 | container `max-width: 1064px; padding: 30px 28px 132px` (`:37`) | `.settings-screen { max-width: 1000px; padding: 26px 28px 64px }` (`app.css:2602-2607`) |
| 3 | eyebrow `Your seat at the council` (`:41`) | `<span class="eyebrow">Account</span>` — `templates/account/settings.php:5`, all 13 pages |
| 4 | H1 2.4rem `Account settings` (`:42`) | `.settings-head h1 { font-size: 1.95rem }` (`app.css:2615`); same string |
| 5 | intro `<p>` 62ch (`:43`) | **absent on every account page** |
| 6 | grid `232px 1fr`, gap 30px (`:46`) | `.settings { grid-template-columns: 188px minmax(0,1fr); gap: 2px 30px }` ≥720px (`app.css:2054`) |
| 7 | rail with 3 group headings + 13 icon items, sticky `top: 84px` (`:49-82`) | `partials/settings_nav.php:29-36` — flat, no headings, no icons, 14 items + a button; sticky `calc(var(--topbar-h) + 22px)` = **84px exactly** (`app.css:2057`) |
| 8 | panes | `.settings-pane` (`app.css:2619-2624`) |
| 9 | fixed unsaved-changes bar (`:477-483`) | none |
| 10 | fixed saved toast `Saved to your seat.` (`:486-491`) | `.flash` at top of `main` (`app.css:189`) |

---

## 2. Exact container + tab-strip metrics (for the one shared partial)

### Containers

| Screen | max-width | padding | head block | H1 | eyebrow | mode chip | tab margin |
|---|---|---|---|---|---|---|---|
| `admin-overview` | **1160px** | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk` | yes | `22px 0 0` |
| `admin-content` | 1100px | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk · Content` | yes | `22px 0 0` |
| `admin-people` | 1100px | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk · People` | yes | `22px 0 0` |
| `admin-appearance` | 1100px | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk · Appearance` | yes | `22px 0 0` |
| `admin-notifications` | **1140px** | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk · Notifications` | yes | `22px 0 0` |
| `admin-settings` | 1100px | `26px 28px 110px` | flex row, gap 20 | 2.4rem | `Operator desk · Settings` | yes | `22px 0 0` |
| `admin-members` | **1160px** | `22px 28px 110px` | bare `<h1>` | **2.1rem** | — | in `AdminNav` | `16px 0 0` |
| `admin-features` | **1160px** | `22px 28px 110px` | bare `<h1>` | **2.1rem** | — | in `AdminNav` | `16px 0 0` |
| `admin-integrations` | **1160px** | `22px 28px 110px` | bare `<h1>` | **2.1rem** | — | in `AdminNav` | `16px 0 0` |
| `admin-packages` | **1160px** | `22px 28px 110px` | bare `<h1>` | **2.1rem** | — | in `AdminNav` | `16px 0 0` |
| `account-settings` | **1064px** | `30px 28px 132px` | eyebrow+H1+intro, `margin-bottom: 24px` | 2.4rem | `Your seat at the council` | — | n/a (rail) |

The 1100/1140/1160 spread is old-generation drift; **the current generation standardises on
`1160px` / `22px 28px 110px`**. Stage 2 should ship one metric: `max-width: 1160px;
padding: 22px 28px 110px` for admin, `1064px` / `30px 28px 132px` for account.

### Topbar (identical string across all seven old-generation screens)

```
position: sticky; top: 0; z-index: 20; height: 58px;
display: flex; align-items: center; gap: 16px; padding: 0 26px;
background: color-mix(in srgb, var(--surface-raised) 92%, transparent);
backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-hair);
box-sizing: border-box;
```
Mark: 24×24 `viewBox="0 0 100 100"`, `fill: var(--accent)`, outer path `opacity=".2"` + solid inner star.
Wordmark: `font-family: var(--font-display); font-weight: 600; font-size: 1.25rem; color: var(--text-strong); letter-spacing: .01em`, gap 10px.
Back link: `margin-left: 14px; gap: 5px; font-family: var(--font-label); font-size: .78rem; letter-spacing: .03em; color: var(--text-muted)`, 13×13 chevron-left, `style-hover="color: var(--accent)"`.

Production (`app.css:77-83`, `:1449-1454`): `min-height: var(--topbar-h)` = **62px** (`imladris.css:351`),
`position: sticky; top: 0; z-index: 20`, `background: color-mix(in srgb, var(--surface-raised) 90%, transparent)`,
`backdrop-filter: blur(10px)`, `border-bottom: 1px solid var(--border-hair)`.
**Deltas: 62px vs 58px; 90% vs 92% mix; `padding: 0 16px` on `.topbar-inner` vs `0 26px`; brand 1.4rem vs 1.25rem; star 26px vs 24px.** All copy.

### Head block (old generation)

```
container:  display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;
eyebrow:    display: block; font-family: var(--font-label); font-size: .68rem;
            letter-spacing: .18em; text-transform: uppercase; color: var(--gold-ink);
h1:         margin: 7px 0 0; font-family: var(--font-display); font-weight: 500;
            font-size: 2.4rem; line-height: 1.1; letter-spacing: -0.01em; color: var(--text-strong);
mode chip:  flex: 0 0 auto; margin-top: 8px; padding: 4px 12px; border-radius: 999px;
            background: var(--surface-review); color: var(--on-review);
            font-family: var(--font-label); font-size: .72rem; letter-spacing: .08em; text-transform: uppercase;
```

Production `.eyebrow` (`app.css:37-43`): `.72rem`, `var(--text-muted)`, `letter-spacing: var(--tracking-caps)` (= .16em).
Production `.pill-admin` (`app.css:106`, `components.css:50`): `background: var(--accent); color: var(--accent-contrast)`;
`.pill` = `padding: 2px 10px; border-radius: var(--radius-pill); font-size: .72rem; letter-spacing: .04em`.
Production `.admin-head` (`app.css:2813-2834`): `gap: 13px; margin-bottom: 20px; padding-bottom: 16px;
border-bottom: 1px solid var(--border-hair)`; **the design head block has no bottom rule** (the tab strip's
`border-bottom` carries it).

### Tab strip (byte-identical across all eleven screens)

```
nav:      display: flex; flex-wrap: wrap; gap: 2px;
          margin: 22px 0 0   (old gen)  |  16px 0 0  (new gen);
          border-bottom: 1px solid var(--border-hair);
item:     padding: 9px 15px; margin-bottom: -1px; border: 0;
          border-bottom: 2px solid transparent; background: transparent;
          font-family: var(--font-label); font-size: .84rem; letter-spacing: .03em;
          color: var(--text-muted); cursor: pointer;
hover:    color: var(--text-strong);
active:   border-bottom-color: var(--gold-500); color: var(--text-strong); aria-current="page"
```
`aria-label` per screen, verbatim: `Admin sections` · `Content sections` · `People sections` ·
`Appearance sections` · `Notification sections` · `Settings sections` · `Member sections` ·
`Capability sections` · `Integration sections` · `Supply chain sections`; account rail is
`Settings sections`.

Production `.subnav` (`app.css:295-297`): `display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 20px;
border-bottom: 1px solid var(--border)`; item `padding: 8px 12px; color: var(--text-muted)`; active
`color: var(--text); border-bottom: 2px solid var(--accent-2)`. **The substrate already exists** —
the deltas are gap 6→2, margin, `--border`→`--border-hair`, `--accent-2`→`--gold-500`, and the
missing `font-family: var(--font-label); font-size: .84rem; letter-spacing: .03em`.

### Account rail

```
grid:        grid-template-columns: 232px 1fr; gap: 30px; align-items: start;
nav:         position: sticky; top: 84px; display: flex; flex-direction: column; gap: 2px;
group head:  padding: 0 0 6px 12px  (first)  |  14px 0 6px 12px  (later);
             font-family: var(--font-label); font-size: .62rem; letter-spacing: .18em;
             text-transform: uppercase; color: var(--text-faint);
item:        display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 12px;
             text-align: left; border: 0; border-left: 2px solid transparent;
             border-radius: 0 var(--radius-md) var(--radius-md) 0; background: transparent;
             color: var(--text-muted); font-family: var(--font-label); font-size: .86rem;
             letter-spacing: .02em;
active:      border-left-color: var(--gold-500); background: var(--brand-subtle);
             color: var(--on-brand-subtle); aria-current="page"
hover:       background: var(--surface-sunken); color: var(--text-body);
icon:        15×15, stroke-width 1.7, Lucide-family
```
Production (`app.css:2054-2061`, `:2629-2634`): 188px column, `gap: 2px 30px`, sticky
`calc(var(--topbar-h) + 22px)` = **84px — an exact match**; item `min-height: 38px`,
`border-radius: var(--radius-sm)`, active `box-shadow: inset 3px 0 0 var(--accent-2)` +
`background: var(--brand-subtle)` + `color: var(--on-brand-subtle)`. No group headings, no icons.

---

## 3. Difference table

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| S1 | Topbar wordmark/mark | **constraint** | Hardcoded eight-point star + literal `Imladris` at 1.25rem (`AdminOverview.dc.html:26-27`; `AdminNav.jsx:53`) | Operator-configurable: `$brand['logo_path']` else star + `$site_name` (`partials/topbar.php:11`); `BrandingController` owns name/logo/favicon/colours (`src/Controller/BrandingController.php:17-24`) | Keep production's branded brand block; adopt the design's *metrics* (24px mark, 1.25rem display wordmark, gap 10/11). Never print `Imladris`. | low |
| S2 | Topbar height | copy | 58px | 62px (`imladris.css:351` `--topbar-h`) | Decide once and set `--topbar-h: 58px`; **it is load-bearing** — `app.css` reads it at :78, :88, :115, :1669, :1732, :1759, :1768, :1877, :1897, :2057, :2846, :3330, :3377. Retest sticky offsets and the mobile drawer. | **high** |
| S3 | Topbar translucency | copy | `color-mix(… 92% …)` | `… 90% …` (`app.css:1450`) | Change 90→92. | low |
| S4 | Topbar horizontal padding | copy | `0 26px` | `.topbar-inner { padding: 0 16px }` (`app.css:89`) | Change to 26px. | low |
| S5 | Back-to-forum link | copy | `‹ Back to the council`, font-label .78rem/.03em, `--text-muted`, hover `--accent` (`AdminOverview.dc.html:29`) | **No back link anywhere on admin or account.** `grep -rn "Back to" templates/admin templates/account` → 2 hits only, both in-section (`admin/badge_rule_preview.php:13`, `admin/structure_confirm.php:27`) | Add a `‹ Back to the forum` link. Copy string is fiction (§4). | low |
| S6 | Admin-mode chip skin | copy | `--surface-review` / `--on-review`, `padding: 4px 12px`, `letter-spacing: .08em` | `.pill-admin` = `--accent` / `--accent-contrast`, `padding: 2px 10px`, `.04em` (`app.css:106`, `components.css:44-50`) | Restyle `.pill-admin` to the review pair; **do not** re-declare tokens in `app.css :root` (F1 rule). | low |
| S7 | Admin-mode chip placement | copy | Old gen: right of the head row. New gen: inside the `AdminNav` identity row (`AdminNav.jsx:58`) | Right of `.admin-head` via `margin-left: auto` (`app.css:2832-2834`) | Follow the *new* generation: move into the bar. | med |
| S8 | Page container width | copy | 1160px full-bleed, no rails | `.admin` 1260px nested inside `.app-shell` (1280px) minus the 272px member sidebar ⇒ **~1008px usable**, then −224px rail −28px gap ⇒ **~700px pane** | See §5 — this is the single biggest visual gap and needs a shell decision, not a max-width tweak. | **high** |
| S9 | Member sidebar on admin pages | copy | Absent — the admin console is its own full-width surface | Present: `layout.php:56-64` renders `partials/sidebar.php` on every `variant=app` page, and no admin template opts out except `theme_safe_mode.php:5` | Give admin/account a shell variant with no member rail (see §5 slice A). | **high** |
| S10 | Cross-section nav orientation | copy | Horizontal 10-pill tier, 101px, above the H1 (`AdminNav.jsx:60-73`) | Vertical 224px sticky grouped rail beside the pane (`admin/_nav.php:56-91`, `app.css:2839-2854`) | Adopt the tier. **Preserve the 8-group IA's reachability** — ADR 0023 item 6 locks the group list, not its orientation; `AdminOverview.dc.html:49` prints the same group names as a caption, so the design knows about them. | **high** |
| S11 | Section tab strip | **feature-added → invert** | Every screen has 2–3 underline tabs scoped to the area | **No production page has any local nav.** Each destination is a separate template with only the global rail | Author the strip once as a partial. This is a genuinely new rank of nav, not a restyle. | med |
| S12 | Moderation queues have no design area | **feature-added** | `ADMIN_AREAS` (`AdminNav.jsx:8-19`) has ten areas and **no Moderation area**; `/mod/reports`, `/mod/approvals`, `/mod/appeals`, `/admin/moderation` are unhomed | `admin/_nav.php:11-17` — a 5-item `Moderation` group, added deliberately by ADR 0023 shipped item 6 | Add an **eleventh** area `Moderation` between `overview` and `content` with tabs Reports · Approvals · Appeals · Anti-abuse. Do not drop it. | med |
| S13 | Feature-flag disabled state | **feature-added** | No flag concept; every tab renders unconditionally | `<span class="admin-nav-link is-disabled" aria-disabled="true" data-destination="…">` + the verbatim-pinned note `Disabled until the feature flag is enabled` (`admin/_nav.php:5, 80-84`; `app.css:2916-2922`) | Extend the pill-tier and tab-strip idioms to carry a disabled variant. The note string is pinned by regression tests — keep it byte-for-byte. | med |
| S14 | Two incompatible flag-off idioms | copy | n/a | Admin nav renders a disabled span; settings rail *silently omits* (`partials/settings_nav.php:12-27`) | Pick one. Recommend: omit in the member rail (a member cannot act on a flag), disable-with-note in the operator console. Record the choice. | low |
| S15 | Mobile drawer | **feature-added** | All eleven screens are desktop-only compositions; no breakpoint, no drawer | `.admin-sections-toggle` + `[data-admin-nav]` drawer: 44px control, `inert`, Tab containment, Escape/scrim/link close, focus restore, `body { overflow: hidden }`, resize cleanup (`admin/_nav.php:52-59, 92`; `app.css:3278-3387`; `app.js:769-875`) | Keep every behaviour. A horizontal pill tier is *easier* to make responsive (h-scroll), but the drawer contract is locked by ADMIN §9.4 + the 2026-07-18 dashboard plan. | **high** |
| S16 | Eyebrow present | copy | Every screen (old gen) and every section | **8 of 39 admin pages** carry `<span class="eyebrow">`; 7 carry `.pane-intro` | Add eyebrow + intro to the remaining ~31. Register already adopted verbatim (`Operator desk`, `Accountability`, `Live operations` …). | low |
| S17 | Eyebrow skin | copy | `.68rem`, `var(--gold-ink)`, `.18em` | `.eyebrow { font-size: .72rem; color: var(--text-muted); letter-spacing: var(--tracking-caps) }` = .16em (`app.css:37-43`) | Change size + colour. Tracking .16→.18 only on the page-head eyebrow (section eyebrows are .16em in the design too). | low |
| S18 | Eyebrow copy shape | copy | `Operator desk · Content` (area appended) | `Operator desk` (bare) on all four pages that have one | The new generation **removes** the kicker entirely (`admin.card.html` note). Follow the new generation: drop the eyebrow on admin, keep it on account. | low |
| S19 | H1 size | copy | 2.4rem (old) → **2.1rem** (new) | `.admin-head h1 { 1.9rem }` (`app.css:2825`); `.settings-head h1 { 1.95rem }` (`app.css:2615`) | 2.1rem admin, 2.4rem account. Mobile: production drops to 1.65rem (`app.css:3289`); the design has no mobile spec — keep production's. | low |
| S20 | Head bottom rule | copy | None — the tab strip's `border-bottom` is the only rule | `.admin-head { padding-bottom: 16px; border-bottom: 1px solid var(--border-hair) }` (`app.css:2819-2820`) | Remove; the tier + strip supply the rules. | low |
| S21 | Flash placement | copy | Inside the container, **below** the tab strip, `role="status"`, `--surface-done` ground with a 3px `--success` left rule (`AdminMembers.dc.html:35-40`) | `partials/flash.php` — plain `<div class="flash" role="status">`, rendered by `layout.php:61` **above** `.admin`, outside the container | Move the flash into the pane and restyle. `role="status"` already correct. | med |
| S22 | Account container width | copy | 1064px | 1000px (`app.css:2603`) | 1000→1064. | low |
| S23 | Account container padding | copy | `30px 28px 132px` | `26px 28px 64px` (`app.css:2606`) | Adopt; the 132px bottom is headroom for the unsaved bar. | low |
| S24 | Account intro paragraph | copy | 62ch intro under the H1 (`AccountSettings.dc.html:43`) | **Absent on all 13 account pages** | Add a per-section intro. | low |
| S25 | Account rail width | copy | 232px | 188px (`app.css:2054`) | 188→232. | low |
| S26 | Account rail group headings | copy | 3 lapidary-caps headings: `Account`, `Reading & writing`, `Council` (`:50, :60, :71`) | Flat list, no headings (`settings_nav.php:29-32`) | Add headings. Third is fiction (§4). | low |
| S27 | Account rail icons | copy | Every item has a 15×15 stroke-1.7 icon | None | Add. `partials/icon.php` already exists as the icon substrate. | low |
| S28 | Account rail active marker | copy | `border-left: 2px solid var(--gold-500)`, `border-radius: 0 md md 0` | `box-shadow: inset 3px 0 0 var(--accent-2)`, `border-radius: var(--radius-sm)` (`app.css:2061`) | 3px `--accent-2` → 2px `--gold-500`; asymmetric radius. Same change applies to `.admin-nav-link.active` (`app.css:2911-2915`). | low |
| S29 | Account rail sticky offset | — | `top: 84px` | `calc(var(--topbar-h) + 22px)` = 84px (`app.css:2057`) | **Already matches.** If S2 changes `--topbar-h` to 58, bump the addend to 26 to hold 84. | low |
| S30 | Account topbar right cluster | — | monogram 30px + name + `Log out` (`:30-34`) | monogram + name + log-out form (`partials/topbar.php:29-52`) | **Already matches.** Adopt the button skin (`padding: 6px 13px; border: 1.5px solid var(--border-soft); border-radius: var(--radius-md)`). | low |
| S31 | Account rail: `Regard` | **feature-removed** | `Regard` rail item + panel (`:57-58`) | No `/settings/regard` route; F2 confirmed no reputation-ledger surface exists | Do not build. Do not ship the rail item. Record the gap. | low |
| S32 | Account rail: `Composing` | **feature-added** | No design tab | `/settings/composing` → `templates/account/composing.php` (`settings_nav.php:10`) | Keep. Style in the idiom, place in `Reading & writing`. | low |
| S33 | Account rail: `Appeals` | **feature-added** | No design tab | `/appeals`, flag-gated (`settings_nav.php:25-27`) | Keep. Flag-gated → keep the omit idiom. | low |
| S34 | Account rail: `Replay tour` | **feature-added** | No design equivalent | `<button class="linkbtn subnav-action" data-tour-replay>` (`settings_nav.php:33-35`) | Keep. It is an action, not a destination — place below the last group, visually separated. | low |
| S35 | Rail item order | copy | Profile · Security · Privacy · Regard ‖ Appearance · Reading · Drafts · Boards ‖ Notifications · Connections · Blocks · Sessions · Account | Profile · Security · Privacy · Appearance · Reading · Composing · Drafts · Notifications · Connections · Sessions · Blocks · Boards · Account · Appeals | Reorder to the design's grouping, folding Composing after Reading and Appeals after Account. | low |
| S36 | Tab-strip navigation mechanism | **constraint** | `<button onClick="{{ goAudit }}">` + `this.setState({ view: 'audit' })` (`AdminOverview.dc.html:336+`) | Every tab destination is already a real route (`src/Core/App.php:2210` `/admin/audit`, `:2221` `/admin/roles/simulator`, `:2216` `/admin/invitations`, `:2303` `/admin/features`, …) | Render `<a href>`, not `<button>`. DESIGN.md §5.3 requires shareable URLs; PE forbids client routing. Presentation identical. | low |
| S37 | All styling is inline `style=` | **constraint** | ~2,174 inline `style=` + 193 `style-hover=` across 11 screens; zero `class=` attributes | `SecurityHeaders` emits `style-src 'self'` with no `style-src-attr`; production has **zero** inline style attributes in `templates/` | Author every rule as an external class in `public/assets/app.css` (F1 landing rule). Rendered pixels must still match. | med |
| S38 | `<helmet><style>` + `@keyframes` | **constraint** | Each screen ships `@keyframes adRise/adPulse/acRise/…` in an inline `<style>` (`AdminOverview.dc.html:13-18`) | No inline `<style>` in any template | Move keyframes into `app.css`. Honour `data-reduced-motion` (`layout.php:23`). | low |
| S39 | `ds-base.js` / `support.js` | **constraint** | Every screen loads the dc-runtime harness | DESIGN.md §6.14 forbids shipping prototype runtime code | Never port. Reference only. | low |
| S40 | Every `href` is `"#"` | **constraint** | Zero real hrefs in any screen | Real route table at `src/Core/App.php` `buildRouter()` | Derive every href from `buildRouter()`. There are no fictional route names to reject — there are no routes at all. | low |
| S41 | `AdminNav` classes have no CSS | **constraint** | `.admin-bar*`, `.admin-tier*` referenced by `AdminNav.jsx` but defined nowhere (`grep -rn "admin-bar" docs/design-system/imladris` → 1 hit, the JSX) | n/a | Escalate: request the next DesignSync pull, or derive from the old screens + `.pill` + the card's three metrics and record the derivation in `LOCAL_RECONCILIATION.md`. | **high** |
| S42 | Design "Extensions" tab renders enabled | **constraint** | `AdminPackages.dc.html:33-34` — third tab `Extensions`, unconditional | `server_extensions` defaults **OFF** (`FeatureFlags.php:100`); `PRODUCTION_PARITY.md` still binds "reserved ⇒ disabled nav entry only — by rule" | Render it disabled-with-note. | low |
| S43 | `theme_safe_mode.php` is `variant=plain` | copy | Safe mode is modelled as a *card* inside the Themes tab, not a page | The only admin page that opts out of the app shell (`admin/theme_safe_mode.php:5`) yet still renders the full `admin/_nav` drawer at :13 | Needs an explicit decision, not a silent copy. Either fold safe mode into `/admin/themes` or give it the console chrome. | med |
| S44 | Sticky unsaved-changes bar | **constraint** | Fixed bottom bar `You have unsaved changes.` · `Discard` · `Save changes` over one global client dirty buffer (`AccountSettings.dc.html:477-483`) | 13 independent server-owned POST forms, each with a 422 anti-draft-loss re-render | Design wins on presentation, production on mechanics. The bar may only be a JS decoration over the *current section's* real form; it must never be the only save affordance. | med |
| S45 | Saved toast | **constraint** | Fixed centred pill toast, `--green-800` ground (`AccountSettings.dc.html:486-491`) | `.flash` banner at the top of `main` (`app.css:189`) after a 303 | Adopt the toast *skin* for the post-redirect flash; do not adopt the client-only lifecycle. | low |
| S46 | Loading skeleton | **constraint** | `dataState: 'loading'` drives six pulsing bars via `@keyframes adPulse` (`AdminOverview.dc.html:212-219`) | Server-rendered pages have no loading state | Ship only `empty` and `error`. Recorded by F1; restated here because it is shell-level. | low |
| S47 | No breadcrumb / return affordance on drill-ins | copy | `AdminPeople` role record shows an `All roles` back link | None: `user_record`, `role_edit`, `package_detail`, `webhook_detail`, `board_edit`, `package_publisher` have no return affordance | With the tier + tab strip, a drill-in keeps its area pill and its tab lit; add an explicit back link per drill-in. | med |
| S48 | Nav-key inconsistency | copy | n/a | `admin/package_publisher.php:14` lights `registries`; `admin/package_security.php:11` lights `packages` — both are drill-ins off `/admin/packages` | Pick `packages` for both. | low |
| S49 | `/mod/*` pages leave the console | copy | n/a | `templates/mod/*.php` use `<div class="mod reports-view">` + `<header class="mod-head">` and never include `admin/_nav` | With S12's Moderation area they must wear the console chrome, or the tier lies about where you are. | med |
| S50 | `/admin/thread-intelligence` has no flag guard | copy *(pre-existing defect)* | n/a | `AdminThreadIntelligenceController::index` (`src/Controller/AdminThreadIntelligenceController.php:14-20`) calls only `requireAdmin()`; the nav entry is flag-gated (`admin/_nav.php:48`) but the route answers 200 with both flags dark. Peers throw `NotFoundException` | Fix independently of this migration. Brief constraint 6. | med |

Counts: **copy 27** · **feature-added 6** · **feature-removed 1** · **feature-changed 0** · **constraint 12** … *(see §7 for the reconciled tally used in the structured return)*

---

## 4. Fiction strings in the shell

| Design string (verbatim) | Where | Proposed production string |
|---|---|---|
| `Imladris` (wordmark) | all 7 old screens' topbar; `AdminNav.jsx:53` | Do not port. Render `$site_name` / `$branding['logo_path']` (`partials/topbar.php:11`). |
| The eight-point elven star SVG | all 7 topbars; `AdminNav.jsx` `<Mark/>` | Not a RetroBoards mark. Production already has its own star (`partials/topbar.php:11`) and honours an operator logo. |
| `Back to the council` | `AdminOverview.dc.html:29` (×7); `AdminNav.jsx:44` default | `Back to the forum` |
| `Your seat at the council` | `AccountSettings.dc.html:41` (eyebrow) | `Account` — **already shipped** at `templates/account/settings.php:5`. Keep production's. |
| `Everything the council knows about you, and everything it does on your behalf. Changes are held until you save them.` | `AccountSettings.dc.html:43` | `Everything this community knows about you, and everything it does on your behalf.` — and **drop** the second sentence: it describes the client dirty buffer production will not build (S44). |
| `Council` (rail group heading) | `AccountSettings.dc.html:71` | `Community` |
| `Regard` (rail item) | `AccountSettings.dc.html:57-58` | n/a — the item is feature-removed (S31). Were it ever built: `Reputation`. |
| `Saved to your seat.` | `AccountSettings.dc.html:490` | `Saved.` |
| `Moderation · Content · People · Appearance · Notifications · Integrations · Settings` | `AdminOverview.dc.html:49` | Not fiction, but **dead chrome**: a non-interactive `<span>` standing in for the real nav. Do not port the span; the group names are already the production IA (`admin/_nav.php:7-49`). |
| `Start with the live queues and health signals, then review what has changed across the council.` | `AdminOverview.dc.html:55` | `…across the community.` — **already shipped correctly** at `templates/admin/dashboard.php:15`. |

No other fiction appears in the shell region. (Screen-body fiction is F1's and the per-screen agents' territory.)

---

## 5. Can the design IA host all ~40 production admin pages?

**Yes — with one addition, and one shell decision.**

### 5a. Route arithmetic

`admin/_nav.php` exposes **26** navigable items across 8 groups.
`ADMIN_AREAS` × per-screen tabs exposes **23** destinations:

| Design area | Tabs (verbatim) | Production routes |
|---|---|---|
| `overview` | Dashboard · Audit log | `/admin`, `/admin/audit` |
| `content` | Boards & categories · Tags | `/admin/structure`, `/admin/tags` |
| `people` | Roles · Permission simulator | `/admin/roles`, `/admin/roles/simulator` |
| `members` | Directory · Invitations | `/admin/users`, `/admin/invitations` |
| `appearance` | Branding · Themes | `/admin/branding`, `/admin/themes` |
| `notifications` | Email · Announcements | `/admin/email`, `/admin/announcements` |
| `integrations` | API tokens · Webhooks · Sign-in providers | `/admin/api-tokens`, `/admin/webhooks`, `/admin/providers` |
| `packages` | Packages · Registry trust · Extensions | `/admin/packages`, `/admin/registries`, `/admin/extensions` |
| `features` | Feature flags · Badge rules · Custom emoji | `/admin/features`, `/admin/badge-rules`, `/admin/custom-emoji` |
| `settings` | General & registration · Thread Intelligence | `/admin/settings`, `/admin/thread-intelligence` |

26 − 23 = 3 net. Resolving exactly:
- **design lacks 4**: `/mod/reports`, `/mod/approvals`, `/mod/appeals`, `/admin/moderation` (the whole Moderation group minus Audit log, which `overview` absorbs)
- **design adds 1**: `/admin/roles/simulator`, which production reaches only as a drill-in

⇒ **the ten areas cover 38 of 39 production admin templates.** The one orphan is
`templates/admin/moderation.php`; the four `templates/mod/*.php` are orphans of the same group.

### 5b. Drill-ins fit without new nav

Nine drill-in pages (`/admin/users/{id}`, `/admin/roles/{id}`, `/admin/packages/{id}`,
`/admin/packages/security`, `/admin/packages/publishers/{id}`, `/admin/webhooks/{id}`,
`/admin/boards/{id}/edit`, `/admin/themes/safe-mode`, `/admin/badge-rules/{id}/preview`) and seven
confirmation interstitials hang off their area's tab: the area pill and the tab both stay lit, and a
back link (S47) returns you. **Two interstitials are POST-rendered** and must not be turned into GET
pages: `admin/users_bulk_confirm.php` (from `POST /admin/users/bulk`) and `admin/package_plan.php`
(from `POST /admin/packages/{id}/plan`).

### 5c. The reconciliation

1. **Add an eleventh area, `Moderation`, second in the tier** (after Overview), with tabs
   `Reports · Approvals · Appeals · Anti-abuse`. Classification: **feature-added** — production has
   functionality the design never modelled; keep it, style it in the idiom, record it. This also
   preserves ADR 0023 shipped item 6, which is binding.
2. **Move `Audit log` out of `overview` into `Moderation`**, matching `admin/_nav.php:15`. *Or* keep
   the design's placement and record the divergence. Recommend following production: the design put
   it under `overview` before it had a Moderation area to put it in.
3. **Every area pill and every tab must carry the disabled variant** (S13) with the pinned note.
4. **`/mod/*` must wear the console chrome** (S49) or the tier misreports location.

### 5d. The shell decision (S8/S9) — the real blocker

The design gives the console **1160px of full-bleed width and no rails**. Production nests it in
`.app-shell` (1280px) behind a 272px member sidebar, then a 224px admin rail, leaving **~700px** of
pane. Replacing the vertical rail with a horizontal tier recovers the 224px but not the 272px.

Recommendation: introduce a third layout variant — `variant=console` — that renders the topbar and
`main` but **not** `partials/sidebar.php`. `layout.php:56-81` already branches three ways
(`app` / `auth` / else); this is a fourth branch with no risk to the member surfaces, and
`admin/theme_safe_mode.php:5` already proves the opt-out mechanism works. Classify: **copy** —
production changes to match. Risk high: `/settings/*` shares the shell and the member rail is the
member's primary navigation there, so account pages probably keep `variant=app` while admin pages
move to `variant=console`. That asymmetry needs recording.

---

## 6. State inventory

| Design state | Design string / mechanism (verbatim) | Production equivalent | Verdict |
|---|---|---|---|
| Active tab | `aria-current="page"` + `border-bottom: 2px solid var(--gold-500)` | `.subnav a.active` + `aria-current="page"` (`app.css:297`; `admin/_nav.php:79`) | present — restyle only |
| Active rail item | `border-left: 2px solid var(--gold-500)`, `--brand-subtle` ground | `box-shadow: inset 3px 0 0 var(--accent-2)`, `--brand-subtle` ground (`app.css:2061`, `:2911-2915`) | present — restyle only |
| Tab hover | `style-hover="color: var(--text-strong)"` | `.admin .subnav a.admin-nav-link:hover { color: var(--text-strong); background: var(--surface-sunken) }` (`app.css:2906-2910`) | present |
| Flash / success | `role="status"`, `--surface-done` ground, `--green-200` border, 3px `--success` left rule, check icon, `{{ flashText }}` (`AdminMembers.dc.html:35-40`) | `<div class="flash" role="status">` (`partials/flash.php:3`, `app.css:189`) | present — restyle + relocate (S21) |
| One-time secret banner | `--gold-050` ground, `--gold-200` border, 3px `--gold-500` left rule (`AdminIntegrations.dc.html:44-45`) | screen-level, not shell | out of scope — but note `--gold-050` **does not exist** (F1) |
| Unsaved changes | `You have unsaved changes.` · `Discard` · `Save changes`, fixed bottom bar (`AccountSettings.dc.html:477-483`) | none | **constraint** — JS decoration only (S44) |
| Saved | `Saved to your seat.`, fixed centred pill toast (`AccountSettings.dc.html:486-491`) | `.flash` after 303 | skin adopt, lifecycle rejected (S45) |
| Loading | `dataState: 'loading'` → six pulsing `@keyframes adPulse` bars (`AdminOverview.dc.html:212-219`) | none — server-rendered | **do not ship** (S46) |
| Empty | `dataState: 'empty'` → `pool = []` (`AdminOverview.dc.html:334-336`) | `.state-*` classes exist in `app.css` | ship |
| Error | `dataState: 'error'` + `recovered` recovery flag | none at shell level | ship as a server-rendered card |
| Disabled nav item | **no design state** | `<span class="admin-nav-link is-disabled" aria-disabled="true">` + `Disabled until the feature flag is enabled` (`admin/_nav.php:5, 81-84`) | **feature-added** — extend the idiom (S13) |
| Mobile drawer open | **no design state** | `body.admin-nav-open`, `aria-expanded`, `inert`, scrim, focus trap, focus restore (`app.js:793-875`) | **feature-added** — preserve wholesale (S15) |
| Guest / logged-out | **no design state** | `partials/topbar.php:53-57` — `Guest` pill, Log in, Sign up | **feature-added** — admin/account are authed-only, so shell-irrelevant, but the topbar partial is shared |
| Theme | `data-theme="{{ themeAttr }}"` on the screen root (`AccountSettings.dc.html:21`) | `data-theme` stamped on `<html>` server-side (`layout.php:20`); default `system` | present, and **better** (flash-free). Note F1 defect H5: `--surface-staff`/`--on-staff` never flip on `data-theme="system"`. |

---

## 7. Reconciled classification tally

Re-reading the four buckets strictly, S43 and S50 are production-internal decisions rather than
design-vs-production differences, and S14 is a production self-consistency issue. They stay in the
table (Stage 2 must act on them) but the tally below counts every row exactly once:

- **copy — 27**: S2, S3, S4, S5, S6, S7, S8, S9, S10, S14, S16, S17, S18, S19, S20, S21, S22, S23, S24, S25, S26, S27, S28, S35, S43, S47, S48, S49, S50 *(29 rows; S29 and S30 are already-matching and excluded)*
- **feature-added — 6**: S11, S12, S13, S15, S32, S33, S34 *(7 rows)*
- **feature-removed — 1**: S31
- **feature-changed — 0**
- **constraint — 12**: S1, S36, S37, S38, S39, S40, S41, S42, S44, S45, S46

Final counts used in the structured return: copy **29**, feature-added **7**, feature-removed **1**,
feature-changed **0**, constraint **11**. (S1 is a constraint; S42 is a constraint; the remaining
nine are S36–S41, S44–S46.)

---

## 8. Slice proposal

Five independently shippable, independently testable slices. Each is one commit with its own
evidence. **Slice 0 gates all others.**

### Slice 0 — Resolve the shell contract (decision only, no code)
- **Touches**: `docs/adr/0024-imladris-admin-shell.md` (next free number after 0023),
  `docs/design-system/imladris/LOCAL_RECONCILIATION.md`
- **Decides**: (a) tier-vs-rail — adopt the 10-area horizontal tier + per-area tab strip;
  (b) the eleventh `Moderation` area and where `Audit log` lives; (c) `variant=console` for admin,
  `variant=app` retained for account; (d) `--topbar-h` 62 → 58 or hold at 62;
  (e) the derivation source for `.admin-bar`/`.admin-tier` given S41, or a hold pending DesignSync;
  (f) safe mode's home (S43).
- **Tested by**: nothing executes. Reviewed against ADR 0023 item 6, ADMIN.md §9.2/§9.4, DESIGN.md
  §5.3. **Do not start Slice 1 until this is signed off** — S8/S10/S15 are all high-risk and all
  downstream of it.

### Slice 1 — The console shell partial
- **Touches**: new `templates/partials/admin_bar.php` (identity row + 11-area tier),
  `templates/admin/_nav.php` (rewritten as the tier; drawer markup retained),
  `templates/layout.php` (`variant=console` branch), `public/assets/app.css`
  (`.admin-bar*`, `.admin-tier*`, revised `.admin`, `.admin-head`, `--topbar-h`),
  `public/assets/app.js` (drawer retargeted at the tier), all 39 `templates/admin/*.php` heads,
  `templates/mod/*.php` (4 files, to adopt the chrome).
- **Tested by**:
  - PHPUnit: extend `tests/Integration/Core/AppFeatureFlagTest.php` to assert every disabled area
    renders the pinned note and no link; a new test asserting the tier marks the right area per route.
  - Playwright desktop + mobile: every area pill reachable; the drawer contract re-verified
    (44px control, Tab containment, Escape/scrim/link close, focus restoration, `body` scroll lock,
    resize cleanup) — this is a *port*, so every existing assertion must still pass.
  - `javaScriptEnabled: false` context proving the tier reaches `/admin/settings` and every area.
  - CSP scan: `rg -n "<script|<style| on[a-z]+=" templates/admin templates/mod templates/layout.php -S` clean.
  - `php bin/build-imladris-assets.php --print-application-digest` → refresh
    `config/imladris-runtime-baseline.json` → `composer check:imladris && composer verify:imladris`.

### Slice 2 — The per-area tab strip
- **Touches**: new `templates/partials/section_tabs.php`, `public/assets/app.css` (`.section-tabs*`),
  all 39 admin templates (pass the area's tab list + active key), plus the disabled-tab variant.
- **Tested by**: PHPUnit asserting each area's tabs render as `<a href>` with the correct
  `aria-current="page"`, and that a flag-dark tab renders as a disabled span with the pinned note;
  Playwright no-JS navigation across every tab in every area; axe serious/critical clean.
- **Independent of Slice 3+**: the strip is additive.

### Slice 3 — Head block, eyebrow and intro coverage
- **Touches**: `public/assets/app.css` (`.eyebrow` size/colour, `.admin-head` rule removal, H1 sizes,
  `.pill-admin` recolour, container metrics), the ~31 admin templates missing an eyebrow/intro.
- **Tested by**: PHPUnit asserting every admin route renders exactly one `<h1>` and one `.eyebrow`;
  Playwright screenshots at desktop + mobile against `docs/evidence/<slice>/comparisons/`;
  contrast check on the recoloured `.pill-admin` (`--surface-review`/`--on-review`) in both themes —
  and specifically on `data-theme="system"` under `prefers-color-scheme: dark`, given F1 defect H5.
- **Purely visual** — ships independently of 1 and 2.

### Slice 4 — The account rail
- **Touches**: `templates/partials/settings_nav.php` (group headings, icons, reorder, Composing +
  Appeals placement, Replay tour footer), `public/assets/app.css` (`.settings-screen` 1000→1064,
  padding, `.settings` 188→232px, rail item skin, active marker), the 13 `templates/account/*.php`
  heads (intro paragraph), `templates/appeals/index.php`.
- **Tested by**: PHPUnit asserting the rail renders the expected item set for each flag combination
  (drafts / oauth / account_lifecycle / appeals / product_tour on and off); Playwright no-JS
  navigation to all 14 destinations; a save-round-trip test per section proving the 422
  anti-draft-loss path still carries `->errors` + `->old` after the restructure.
- **Independent of Slices 1–3** — the account surface keeps `variant=app` and shares no partial
  with the admin console.

### Slice 5 — Flash relocation and the toast skin
- **Touches**: `templates/layout.php` (flash out of the shared `main` for console/settings, or a
  slot mechanism), `templates/partials/flash.php`, `public/assets/app.css` (`.flash` restyle,
  toast variant, keyframes moved out of the design's `<helmet><style>`).
- **Tested by**: PHPUnit asserting a flash still renders after every redirecting POST on the audited
  routes; Playwright confirming placement below the tab strip and `role="status"` announcement;
  reduced-motion honoured (`layout.php:23` `data-reduced-motion`).
- **Smallest slice; ship it first if Slice 0 stalls.**

### Out of scope but must be filed
- **S50** — `/admin/thread-intelligence` answers 200 with `community_memory` and `automated_context`
  both dark (`src/Controller/AdminThreadIntelligenceController.php:14-20`). A route-gating defect,
  not a design difference. File and fix separately.
- **F1 defect H5** — `--surface-staff`/`--on-staff` absent from `app.css`'s
  `@media (prefers-color-scheme: dark) { [data-theme="system"] }` block. Blocks any confident
  claim about the mode chip's twilight rendering.
