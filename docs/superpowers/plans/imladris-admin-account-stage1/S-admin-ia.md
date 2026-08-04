# S-admin-ia — Admin information-architecture reconciliation

Imladris design system (live project `c3e02753-607c-40b6-994c-9ba1a65bb367`, mirror
`C:/Users/htper/community-forums/docs/design-system/imladris`) vs RetroBoards production
(`C:/Users/htper/community-forums`).

Read in full: `components/admin/AdminNav.jsx`, `components/admin/admin.card.html`,
`components.css:323-342`, the topbar/head/tab-strip region of all ten
`templates/admin-*/*.dc.html`, `templates/admin/_nav.php`, `templates/layout.php`,
`public/assets/app.css:2800-2932` + `:3279-3387`, `public/assets/app.js:766-875`.

---

## 0. Three corrections to the briefing facts (verified against source)

These matter because the rest of the reconciliation depends on them.

1. **There is no `Operator desk · <Section>` eyebrow on any of the ten screens.** The brief
   states each design screen renders one. `grep -rn "Operator desk" docs/design-system/imladris/`
   returns exactly one hit — and it is the *obituary* for that element, in
   `components/admin/admin.card.html:43`:
   > "Measured against the pages it replaces, this chrome is 10px *shorter*: the redundant
   > &ldquo;Operator desk&nbsp;·&nbsp;Area&rdquo; kicker is gone, the mode pill moved into the
   > identity row, and the heading drops from 2.4rem to 2.1rem."

   In every one of the ten `.dc.html` files the `<h1>` is the **first child** of the content
   column, immediately after the `<x-import …AdminNav…>` element. The eyebrow is deleted design,
   not current design.

2. **AdminOverview's tab strip carries no trailing static span.** The brief claims a trailing
   `"Moderation · Content · People · Appearance · Notifications · Integrations · Settings"` span.
   `AdminOverview.dc.html:289-294` is four `<sc-if>` blocks and nothing else — Dashboard on/off,
   Audit log on/off, then `</nav>`. `grep -n "Moderation" AdminOverview.dc.html` hits only audit-log
   sample rows (`'Anti-abuse: first post'`) and a triage line. That crutch existed because the
   old screens had no cross-section nav; AdminNav made it redundant and it was removed.

3. **Production's rail has 26 leaf entries, not 22.** Counting `templates/admin/_nav.php:7-50`:
   Dashboard 1, Moderation 5, Content 2, People 4, Appearance 3, Notifications 2, Integrations 6,
   Settings 3 = **26**. The 22 in the brief is what you get after subtracting the four entries
   the design has no home for (Reports, Approvals, Appeals, Anti-abuse) — which is the correct
   number for the *mapped* set, and is a nice confirmation that the mapping below is exhaustive:
   26 production entries − 4 unmapped = 22, plus Permission simulator (a production route that is
   currently *not* a nav entry) = **23 = the exact number of design tabs**.

---

## 1. Nav-model verdict — answered from AdminNav.jsx, not intuition

**The design REPLACES the vertical grouped rail with a two-level horizontal chrome. It does not
keep the rail. It does not nest a tab strip inside a rail group.**

Five independent proofs from source:

| # | Evidence | Location |
|---|---|---|
| 1 | `ADMIN_AREAS` is a **flat array of ten objects**. There is no `group` key, no nesting, no `children`. | `AdminNav.jsx:8-19` |
| 2 | The component renders **one** `<nav className="admin-tier" aria-label="Admin areas">` containing a single `.map()` over all ten. There is no second loop and no group heading element. | `AdminNav.jsx:60-73` |
| 3 | The CSS is a **horizontal scroller**: `.admin-tier { display: flex; gap: 4px; padding: 0 26px 9px; overflow-x: auto; scrollbar-width: thin; }` with `.admin-tier-item { flex: none; white-space: nowrap; }`. A vertical rail is `flex-direction: column` (which is exactly what production has at `app.css:2841`). | `components.css:337-342` |
| 4 | The component's own doc comment states the intent and the rank separation: *"Two rows, one sticky block: the identity row (mark · exit · mode pill) and the area tier listing all ten admin areas. The tier uses the PILL register — the same idiom the forum topbar uses for primary nav — so it never reads as a second copy of a page's own underline sub-tabs."* | `AdminNav.jsx:32-39` |
| 5 | The card demo shows the composition literally: `<AdminNav area={area}/>` then `<h1>Boards & tags</h1>` then `<nav className="inner">` (the underline sub-tabs). Caption: *"The tier is a pill row, the page's own sections are underline tabs, and the page heading sits between them — three signals keeping the two ranks apart."* | `admin.card.html:32-43` |

So the model is **tier (10 pills, global, always present) → H1 → tab strip (2–3 underline tabs,
scoped to the active area) → pane**. Depth 2, both horizontal, no rail anywhere.

### What production must change

| Production today | Design | Classification |
|---|---|---|
| `.admin { grid-template-columns: 224px minmax(0,1fr); grid-template-areas: "head head" / "nav pane" }` — a 224px sticky vertical rail inside `main` (`app.css:2800-2812`, `:2839-2854`) | full-bleed 2-row sticky `.admin-bar` above the content column; content column is `max-width: 1100–1160px; margin: 0 auto` | **copy** |
| Admin pages use layout `variant=app`, so `layout.php:56-64` renders `partials/topbar` **and** `partials/sidebar` (the member board rail) **and then** the 224px admin rail inside `main` — two vertical rails side by side | AdminNav is `position: sticky; top: 0` and is the *only* chrome. No forum topbar, no member sidebar. | **copy** (needs a new `variant`, see §6) |
| 8 group headings `<h2 class="admin-nav-group-title">` (`_nav.php:62-63`) | no grouping element exists | **copy** |
| 26 leaves all visible on every admin page | 10 tier pills always + 2–3 tabs for the active area only | **copy** |
| `<span class="pill pill-admin">Admin mode</span>` inside `.admin-head` (every leaf template) | `.admin-bar-mode` in the identity row, `margin-left: auto`, uppercase `.72rem`, `--surface-review`/`--on-review` | **copy** |
| Per-page eyebrows: `Accountability`, `Operator desk`, `Appearance`, `Runtime controls`, `Moderation`, `Operations`, `Warden's table` (17 instances, listed §5.4) | none | **copy** (delete) |
| `.admin-head h1 { font-size: 1.9rem }` (`app.css:2825-2828`) | `font-size: 2.1rem; font-family: var(--font-display); font-weight: 500; line-height: 1.1; letter-spacing: -0.01em` | **copy** |
| Mobile: `.admin-sections-toggle` + drawer + scrim + focus trap (`app.js:766-875`, `app.css:3310-3387`) | tier scrolls horizontally; tab strip `flex-wrap: wrap`. No drawer. | **copy** — and see §6.5 |

### The governance problem this creates (must not be papered over)

`ADMIN.md §9.2` (line 561-563) says verbatim: **"Console information architecture — Left-nav,
grouped:"** followed by the eight-group table. `ADMIN.md §9.4` says the console *"collapses to one
column with the section nav in a drawer"*. Neither `DECISIONS.md` nor `DESIGN.md` mentions the
admin nav model at all (grepped: zero hits for `admin nav`, `left-nav`, `console IA`, `grouped
rail`, `admin rail`). Under the precedence chain that makes **ADMIN.md the highest authority
currently on record**, and it mandates the very rail the design deletes.

Further, `docs/adr/0023-admin-console-audit-round-2.md:17` records the grouped rail as an
**accepted round-2 remediation**: *"Console IA per ADMIN §9.2: grouped admin nav (Dashboard ·
Moderation · Content · People · Appearance · Notifications · Integrations · Settings) with real
Moderation entries…"*. Rule 8 of this pass forbids silently reverting a binding decision.

**Required process, not optional:** adopting the design's tier requires (a) an amendment to
ADMIN.md §9.2 and §9.4, and (b) a new ADR that supersedes the IA clause of ADR 0023 while
explicitly preserving its other three findings (real Moderation entries, Appeals dashboard card,
inbound links for the two orphan consoles). All three survive the change — see §4.

---

## 2. The authoritative table

Legend for the "Deviation" column: `copy` = production must change to match; `feature-added` =
production capability the design never modeled; `feature-removed` = design chrome production does
not implement; `feature-changed`; `constraint`.

### 2.1 Areas that map cleanly

Tier order = `ADMIN_AREAS` order (`AdminNav.jsx:9-18`). Tab order = source order in each
`.dc.html`. `H1` and `nav aria-label` are quoted verbatim from the design.

---

#### 1 · Overview — H1 `Admin console` — `<nav aria-label="Admin sections">` — column 1160px

| Tab | Route (verified `App.php`) | Production template | Flag |
|---|---|---|---|
| `Dashboard` | `GET /admin` — `App.php:2204` | `templates/admin/dashboard.php` | — |
| `Audit log` | `GET /admin/audit` — `App.php:2210` | `templates/admin/audit.php` | — |

- Design H1 **already matches** production `dashboard.php:7` `<h1>Admin console</h1>`. No change.
- `Audit log` **moves nav group**: production rail puts it under `Moderation` (`_nav.php:15`). See §4.
- `dashboard.php:20,41,66,86` eyebrows `Live operations` / `Triage` / `Community pulse` /
  `Audit trail` are **section** eyebrows *inside* the pane and are preserved — AdminOverview keeps
  them (`AdminOverview.dc.html:304` `Live operations`). Only the `admin-head` eyebrow at
  `dashboard.php:6` (`Operator desk`) is deleted.

---

#### 2 · Content — H1 `Boards &amp; tags` — `<nav aria-label="Content sections">` — column 1100px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Boards & categories` | `GET /admin/structure` — `App.php:2328` | `structure.php` | — |
| ↳ drill-in | `GET /admin/boards/{id}/edit` — `App.php:2337` | `board_edit.php` | — |
| ↳ drill-in | `GET /admin/categories/{id}/delete`, `/admin/boards/{id}/delete`, `/admin/boards/{id}/archive`, `/admin/boards/{id}/unarchive` — `App.php:2335,2340,2349,2351` | `structure_confirm.php` | — |
| `Tags` | `GET /admin/tags` — `App.php:2154` | `tags.php` | `tags` |
| ↳ drill-in | `GET /admin/tags/{id}/merge` — `App.php:2158` | `tag_merge_confirm.php` | `tags` |

Production H1s `Boards & categories` (`structure.php:10`) and `Tags` (`tags.php:13`) both become
the single area H1 `Boards & tags`, with the old H1 demoted to the tab label.

---

#### 3 · People — H1 `Roles &amp; capabilities` — `<nav aria-label="People sections">` — column 1100px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Roles` | `GET /admin/roles` — `App.php:2219` | `roles.php` | `capabilities` |
| ↳ drill-in | `GET /admin/roles/{id}` — `App.php:2222` | `role_edit.php` | `capabilities` |
| `Permission simulator` | `GET /admin/roles/simulator` — `App.php:2221` | `role_simulator.php` | `capabilities` |

- Design H1 **already matches** production `roles.php:17` `<h1>Roles &amp; capabilities</h1>`.
- `Permission simulator` is **promoted from orphan to nav tab**. Today it is *not* in the rail;
  ADR 0023 finding 13 resolved its orphan status with an inbound link from `/admin/roles`
  (asserted at `tests/Integration/Admin/AppAdminNavIaTest.php:74`). The design gives it a real
  nav position. Classification **feature-changed** (same concept, stronger mechanics). The ADR
  0023 deferral is *superseded upward*, not reverted — the inbound link should stay.
- Flag gate confirmed at `src/Controller/AdminRoleController.php:27-31`: `gate()` throws
  `NotFoundException` when `capabilities` is off, so a flag-off tab must render disabled, not 404.

---

#### 4 · Members — H1 `Members &amp; invitations` — `<nav aria-label="Member sections">` — column 1160px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Directory` | `GET /admin/users` — `App.php:2362` | `users.php` | — |
| ↳ drill-in | `GET /admin/users/{id}` — `App.php:2365` | `user_record.php` | — |
| ↳ drill-in | `POST /admin/users/bulk` (renders confirm) — `App.php:2363` | `users_bulk_confirm.php` | — |
| `Invitations` | `GET /admin/invitations` — `App.php:2216` | `invitations.php` | `invitations` |

Both leaves **move nav group**: production rail puts Users and Invitations under `People`
(`_nav.php:23,25`). See §4.

---

#### 5 · Appearance — H1 `Branding &amp; themes` — `<nav aria-label="Appearance sections">` — column 1100px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Branding` | `GET /admin/branding` — `App.php:2152` | `branding.php` | `branding` |
| `Themes` | `GET /admin/themes` — `App.php:2235` | `themes.php` | `package_themes` |
| ↳ drill-in | `GET /admin/themes/safe-mode` — `App.php:2236` | `theme_safe_mode.php` | `package_themes` |

`theme_safe_mode.php:5` sets `$this->section('variant', 'plain')` — deliberate (safe mode must
render without theme chrome). Keep. **constraint** — this one page keeps a reduced shell and does
*not* get the tier.

The design's Branding pane includes "custom CSS behind an acknowledgement, the typed RESET
guard". Production's `custom_css` flag is **default OFF** (`FeatureFlags.php`). That sub-block is
a §5-style gap for the Appearance reconciler, not an IA issue.

---

#### 6 · Notifications — H1 `Email &amp; announcements` — `<nav aria-label="Notification sections">` — column 1140px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Email` | `GET /admin/email` — `App.php:2296` | `email.php` | `email` |
| `Announcements` | `GET /admin/announcements` — `App.php:2313` | `announcements.php` | `announcements` |

No group move. Production H1s `Email delivery` / `Announcements` collapse into the area H1.

---

#### 7 · Integrations — H1 `Tokens, webhooks &amp; sign-in` — `<nav aria-label="Integration sections">` — column 1160px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `API tokens` | `GET /admin/api-tokens` — `App.php:2211` | `api_tokens.php` | `api_tokens` |
| `Webhooks` | `GET /admin/webhooks` — `App.php:2287` | `webhooks.php` | `webhooks` |
| ↳ drill-in | `GET /admin/webhooks/{id}` — `App.php:2289` | `webhook_detail.php` | `webhooks` |
| `Sign-in providers` | `GET /admin/providers` — `App.php:2229` | `providers.php` | `provider_registry` |
| ↳ drill-in | `GET /admin/providers/{id}/disable` — `App.php:2233` | `provider_disable.php` | `provider_registry` |

The group survives but **narrows**: Packages, Registry trust and Extensions leave for area 8. See §4.

---

#### 8 · Packages — H1 `Packages &amp; registries` — `<nav aria-label="Supply chain sections">` — column 1160px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Packages` | `GET /admin/packages` — `App.php:2242` | `packages.php` | `package_registry` |
| ↳ drill-in | `GET /admin/packages/{id}` — `App.php:2254` | `package_detail.php` | `package_registry` |
| ↳ partial | (rendered inside `package_detail.php`) | `_package_integration.php` | `package_registry` |
| ↳ partial | (rendered inside `package_detail.php`) | `_package_review_form.php` | `package_registry` |
| ↳ drill-in | `POST /admin/packages/{id}/plan` — `App.php:2255` | `package_plan.php` | `package_registry` |
| ↳ drill-in | `GET /admin/packages/{id}/consent` — `App.php:2257` | `package_consent.php` | `package_registry` |
| ↳ drill-in | `GET /admin/packages/security` — `App.php:2244` | `package_security.php` | `package_registry` |
| ↳ drill-in | `GET /admin/packages/publishers/{id}` — `App.php:2247` | `package_publisher.php` | `package_registry` |
| `Registry trust` | `GET /admin/registries` — `App.php:2277` | `registries.php` | `package_registry` |
| `Extensions` | `GET /admin/extensions` — `App.php:2312` | `extensions.php` | `server_extensions` (**default OFF**) |

Tab-membership proof for the drill-ins: `AdminPackages.dc.html:582-583` computes
`atCatalogue: s.view === 'catalogue' || 'detail' || 'plan' || 'consent' || 'security'` — i.e. the
install-plan → consent chain **and** the emergency-brake/publishers view all keep the `Packages`
tab lit. `Publishers` is an `<h2>` table *inside* the security view (`AdminPackages.dc.html:294`),
so publisher trust belongs to `Packages`, not `Registry trust`. **`package_publisher.php` must
change its tab** (see §4).

`server_extensions` is default-OFF, so the `Extensions` tab ships permanently disabled on a stock
install. The design shows it as a live tab with a "reserved server-extensions probe" body — that
is the **feature-added** flag-disabled state the design never modeled (see §6.3).

---

#### 9 · Features — H1 `Features &amp; badges` — `<nav aria-label="Capability sections">` — column 1160px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `Feature flags` | `GET /admin/features` — `App.php:2303` | `features.php` | — |
| `Badge rules` | `GET /admin/badge-rules` — `App.php:2315` | `badge_rules.php` | `badge_rules` |
| ↳ drill-in | `GET /admin/badge-rules/{id}/preview` — `App.php:2317` | `badge_rule_preview.php` | `badge_rules` |
| `Custom emoji` | `GET /admin/custom-emoji` — `App.php:2324` | `custom_emoji.php` | `custom_emoji` |

All three **move nav group**: Feature flags is under `Settings` today (`_nav.php:47`), Badge rules
under `People` (`_nav.php:26`), Custom emoji under `Appearance` (`_nav.php:31`). See §4. This is
the single most disruptive area — it is assembled entirely from three different current groups.

---

#### 10 · Settings — H1 `General &amp; intelligence` — `<nav aria-label="Settings sections">` — column 1100px

| Tab | Route | Production template | Flag |
|---|---|---|---|
| `General & registration` | `GET /admin/settings` — `App.php:2205` | `settings.php` | — |
| `Thread Intelligence` | `GET /admin/thread-intelligence` — `App.php:2304` | `thread_intelligence.php` | `community_memory` **or** `automated_context` (`flags_any`, `_nav.php:48`) |

Note the brief listed `General & registration` as this screen's H1 — it is the **tab** label.
The H1 is `General &amp; intelligence` (`AdminSettings.dc.html:414`). Production `settings.php:15`
currently uses `<h1>General & registration</h1>`; that string demotes to the tab.

---

### 2.2 Production templates with NO design representation

Every one of these is a genuine gap in the design system, not a production defect.

| Production template | Route | Nav entry today | Flag | Why unrepresented |
|---|---|---|---|---|
| `templates/admin/moderation.php` (`<h1>Anti-abuse</h1>`) | `GET /admin/moderation` — `App.php:2208` | Moderation › Anti-abuse (`_nav.php:16`) | `anti_abuse` | No design screen covers anti-abuse posture/blocked words. `grep -rl "Anti-abuse"` over the ten screens hits only `AdminOverview` (an audit-log sample row `'Anti-abuse: first post'` and a triage line `'2 topics held by anti-abuse awaiting review'`) and `AdminPackages` (a fictional *package named* "Anti-abuse scanner", `AdminPackages.dc.html:487`). Neither is a settings surface. |
| `templates/mod/reports.php` | `GET /mod/reports` — `App.php:2401` | Moderation › Reports (`_nav.php:12`) | `moderation_queue` | AdminOverview has a `Reports open` **counter** (`AdminOverview.dc.html:54-55`) and a triage line, never the queue. |
| `templates/mod/approvals.php` | `GET /mod/approvals` — `App.php:2394` | Moderation › Approvals (`_nav.php:13`) | `moderation_queue` | `grep -rl "Approvals"` over all ten screens: **zero hits**. |
| `templates/mod/appeals.php` | `GET /mod/appeals` — `App.php:2408` | Moderation › Appeals (`_nav.php:14`) | `appeals` | AdminOverview has an `Appeals` counter (`AdminOverview.dc.html:66-67`) only. |
| `templates/mod/user.php` | `GET /mod/u/{id}` — `App.php:2412` | *(not in the rail)* | `moderation_queue` | The moderator-scoped user record. AdminMembers models the **admin** record (`/admin/users/{id}`) only. |
| `templates/admin/_nav.php` | — | *(is the chrome)* | — | Replaced wholesale by the new partial (§6). |

**Additional production-only IA fact:** the four `templates/mod/*.php` templates **do not render
`admin/_nav`** at all (`grep -rn "admin/_nav" templates/` returns 39 hits, none in `mod/`). They
render their own `.mod-head` + `<nav class="mod-subnav" aria-label="Moderation queues">`
(`reports.php:24-28`) with a `<span class="pill mod-pill">Moderation</span>` and the eyebrow
`Warden's table`. So today the admin rail links *out* to four destinations that then drop the rail
entirely. That is an existing IA break, independent of this design pass, and the design's tier
model is what fixes it (§3).

---

## 3. The eleventh area — the only sanctioned structural addition

The four unrepresented `moderation_queue` / `appeals` / `anti_abuse` surfaces are live,
flag-gated, tested production functionality. Rule: **feature-added — keep it, style it in the
design idiom, record it.** Shipping the tier without them would either orphan four routes or
require deleting working features.

Proposal, minimum-conflict:

```
tier: Overview · Moderation · Content · People · Members · Appearance ·
      Notifications · Integrations · Packages · Features · Settings      (11 items)
```

`Moderation` inserted at index 1. That position satisfies both authorities simultaneously: it
preserves `ADMIN_AREAS`' relative order for the ten, and it matches `ADMIN.md §9.2`'s and
`_nav.php:11`'s "Moderation second" placement, so the round-2 group-order assertion's *intent*
survives.

| Area 1b · Moderation — proposed H1 `Queues & anti-abuse` — `<nav aria-label="Moderation sections">` |
|---|

| Tab | Route | Template | Flag |
|---|---|---|---|
| `Reports` | `GET /mod/reports` | `templates/mod/reports.php` | `moderation_queue` |
| ↳ drill-in | `GET /mod/u/{id}` | `templates/mod/user.php` | `moderation_queue` |
| `Approvals` | `GET /mod/approvals` | `templates/mod/approvals.php` | `moderation_queue` |
| `Appeals` | `GET /mod/appeals` | `templates/mod/appeals.php` | `appeals` |
| `Anti-abuse` | `GET /admin/moderation` | `templates/admin/moderation.php` | `anti_abuse` |

The existing `.mod-subnav` (`reports.php:24-28`) is already structurally the design's underline
tab strip — three sibling links, one `.active`. It becomes the area tab strip with a fourth entry
(`Anti-abuse`) and the design's class names. The `mod-count` badge inside the Reports link is
**feature-added** (the design's tab strip has no count affordance); style it as the design's
`--surface-review`/`--on-review` pill inline in the tab.

**Authorization caveat (constraint):** `/mod/*` is moderator-accessible while `/admin/*` requires
`requireAdmin()`. A moderator seeing the eleven-pill tier would see ten pills they cannot open.
The tier must therefore filter by authority as well as by flag — moderators get
`Moderation` (+ `Overview›Audit log` per `ADMIN.md §9.1`: *"Moderators see a reduced Console
scoped to their boards (Reports, Audit, limited People)"*). Record as **constraint**; the design
models a single all-powerful operator and has no reduced-console state.

---

## 4. Non-presentational IA conflicts — every placement move

Each row is a production route whose **nav group changes**. These are behavioural IA changes
(a bookmark still works, but muscle memory and every "where do I find X" answer changes), not
styling.

| # | Route | Template | Group today (`_nav.php`) | Design home | Class |
|---|---|---|---|---|---|
| M1 | `/admin/audit` | `audit.php` | Moderation (`:15`) | **Overview** › Audit log | copy |
| M2 | `/admin/users` | `users.php` | People (`:23`) | **Members** › Directory | copy |
| M3 | `/admin/invitations` | `invitations.php` | People (`:25`) | **Members** › Invitations | copy |
| M4 | `/admin/badge-rules` | `badge_rules.php` | People (`:26`) | **Features** › Badge rules | copy |
| M5 | `/admin/custom-emoji` | `custom_emoji.php` | Appearance (`:31`) | **Features** › Custom emoji | copy |
| M6 | `/admin/features` | `features.php` | Settings (`:47`) | **Features** › Feature flags | copy |
| M7 | `/admin/packages` | `packages.php` | Integrations (`:38`) | **Packages** › Packages | copy |
| M8 | `/admin/registries` | `registries.php` | Integrations (`:39`) | **Packages** › Registry trust | copy |
| M9 | `/admin/extensions` | `extensions.php` | Integrations (`:43`) | **Packages** › Extensions | copy |
| M10 | `/admin/packages/publishers/{id}` | `package_publisher.php` | *(page passes `active => 'registries'`, `package_publisher.php:8`)* | **Packages** › Packages (the design's Publishers table lives in the security view, `AdminPackages.dc.html:294`, and `atCatalogue` covers `view==='security'`, `:582`) | copy |
| M11 | `/admin/roles/simulator` | `role_simulator.php` | *(no nav entry; inbound link only, per ADR 0023)* | **People** › Permission simulator (a first-class tab) | feature-changed |
| M12 | `/mod/reports`, `/mod/approvals`, `/mod/appeals`, `/admin/moderation` | `mod/*.php`, `moderation.php` | Moderation (`:12-16`) | *no design home* → new area §3 | feature-added |

### 4.1 Tests and evidence artifacts that assert the current placement

All found by `grep -rn "admin-nav-group\|admin-nav-link\|admin-subnav\|Admin sections" tests/`.

| Artifact | What it asserts | Breaks how |
|---|---|---|
| `tests/Integration/Admin/AppAdminNavIaTest.php:31-33` | `'class="admin-nav-group-title">' . $label` present for `Moderation, Content, People, Appearance, Notifications, Integrations, Settings` on `GET /admin` | **Hard break.** `.admin-nav-group-title` ceases to exist. Rewrite against `.admin-tier-item`. |
| `tests/Integration/Admin/AppAdminNavIaTest.php:34-36` | `href="/mod/reports"`, `/mod/approvals`, `/mod/appeals` on `GET /admin` | **Hard break.** Under the tier those three are tabs of the *Moderation* area and are not rendered on `/admin`. Rewrite to assert the `Moderation` tier pill on `/admin`, and the three tab hrefs on `/mod/reports`. |
| `tests/Integration/Admin/AppAdminNavIaTest.php:39-46` | with `appeals=false`, `href="/mod/appeals"` absent but the text `Appeals` still present ("disabled span, not removed") | **Survives in spirit.** The disabled-span contract must be carried onto the new tab strip (§6.3), otherwise this regresses. |
| `tests/Integration/Admin/AppAdminNavIaTest.php:71-76` | `/admin/roles` links `/admin/roles/simulator`; `/admin/packages` links `/admin/packages/security` | **Survives.** M11 promotes the simulator to a tab; keep the inbound link so this ADR-0023 assertion still passes. |
| `tests/Integration/Core/AppAdminDashboardRemediationTest.php:77-120` | eight group titles **in strict source order** (`assertGreaterThan($cursor, …)`), then all **26** destinations present as `href`/`data-destination`, then `<a href="/admin/settings" class="…active…" aria-current="page">` — all on `GET /admin/settings` | **Hard break, worst offender.** The 26-destination directory can no longer exist on one page. Rewrite: 11 tier destinations in order + the 2 Settings tabs + active-state regex retargeted at `.admin-tier-item.is-active`. |
| `tests/browser/admin-dashboard.spec.ts:61` + `expectGroupedDirectory()` (`:93-105`) | `expect(page.locator('.admin-nav-group-title')).toHaveText(GROUPS)` and 26 `[data-admin-nav] :is(a[href=…], [data-destination=…])` each `toHaveCount(1)` | **Hard break.** Same rewrite; `[data-admin-nav]` selector must become `[data-admin-tier]`. |
| `tests/browser/admin-dashboard.spec.ts` (`expectAxeClean`, `.include('.admin')`) | axe-clean scoped to `.admin` | **Scope break.** The tier moves *outside* `.admin` (it is full-bleed above the content column). The axe include must widen to `.admin-console` or `body`. |
| `tests/Integration/Core/AppImladrisFidelityTest.php:81` | `assertSeeText($res, 'admin-subnav')` | **Hard break.** `.admin-subnav` is deleted. Retarget to `admin-tier`. |
| `docs/evidence/browser/<project>/r2-*.png` (grouped nav + appeals card) — produced per `docs/superpowers/plans/2026-07-18-admin-audit-round2-remediation.md:473` | pixel evidence of the grouped rail | Superseded; must be re-shot under DESIGN §13. |

Also in the blast radius (nav mechanics, not placement): `public/assets/app.js:766-875` (drawer,
scrim, focus trap, 860px `matchMedia`) and `public/assets/app.css:2800-2932`, `:3279-3387`,
`:3434`, `:5759`.

---

## 5. Production nav groups with no design screen after the sync

Of the eight groups in `_nav.php:7-50`, **one** is entirely unrepresented and **one more** is
partially unrepresented.

### 5.1 `Moderation` — no design screen (4 of 5 entries orphaned)

| Entry | Route | Flag | Template |
|---|---|---|---|
| Reports | `/mod/reports` | `moderation_queue` | `templates/mod/reports.php` |
| Approvals | `/mod/approvals` | `moderation_queue` | `templates/mod/approvals.php` |
| Appeals | `/mod/appeals` | `appeals` | `templates/mod/appeals.php` |
| Anti-abuse | `/admin/moderation` | `anti_abuse` | `templates/admin/moderation.php` |
| ~~Audit log~~ | `/admin/audit` | — | *(re-homed to Overview — M1)* |

Plus the unlinked `templates/mod/user.php` (`/mod/u/{id}`).

This is the single largest hole in the design system after the sync: an entire operator domain,
five templates, three feature flags. Resolution in §3.

### 5.2 `Dashboard` — fully represented (Overview)
### 5.3 `Content`, `People`, `Appearance`, `Notifications`, `Integrations`, `Settings` — all represented, but four of them **lose members** to Members/Features/Packages (M2–M9). No group loses *all* its members except Moderation.

### 5.4 Fiction and eyebrow strings to remove (rule 3 — flag every instance)

| Production string | Location | Verdict |
|---|---|---|
| `Warden's table` | `mod/reports.php:18`, `mod/approvals.php:12`, `mod/appeals.php:12`, `mod/user.php:27` | **Imladris fiction.** Delete with the eyebrow; the design has no eyebrow. |
| `Operator desk` | `dashboard.php:6`, `branding.php:11`, `settings.php:14` | Register word, not fiction — but the design explicitly deleted it (`admin.card.html:43`). Delete. |
| `Accountability` | `audit.php:12` | Delete (no eyebrow in design). |
| `Appearance` | `custom_emoji.php:12` | Delete. |
| `Runtime controls` | `features.php:6` | Delete. |
| `Moderation` | `moderation.php:16` | Delete. |
| `Operations` | `thread_intelligence.php:6` | Delete. |
| `Back to the council` (design default, `AdminNav.jsx:45`) | — | **Fiction.** Proposed neutral production string: **`Back to the forum`**. |
| `Imladris` wordmark (`AdminNav.jsx:53`) | — | **Fiction.** Production must render the operator's own community name: `$brand['name']` from `layout.php:6`. |
| `Admin mode` (`AdminNav.jsx:44`) | already production `pill pill-admin` text in every `admin-head` | Neutral. Keep verbatim. |
| Design body copy: *"The chrome the council wears"* (`AdminAppearance.dc.html:39`), *"Categories order the council's rooms"* (`AdminContent.dc.html:84`), *"what has changed across the council"* (`AdminOverview.dc.html:42`), *"Package catalogue"* back-link (`AdminPackages.dc.html:103`) | — | `council` is fiction. Neutral substitutions are the per-screen reconcilers' job; flagged here so none is copied verbatim. |
| `Et Eärello Endorenna utúlien.` | `layout.php:74` (auth colophon) | Fiction, outside admin scope — noted for the auth reconciler. |

---

## 6. The ONE shared partial + the ONE CSS block

### 6.1 The partial

**Replace** `templates/admin/_nav.php` with **`templates/admin/_console.php`** — one partial that
renders the identity row, the tier, the H1 and the area tab strip as a single unit, because the
design treats them as one composition (`admin.card.html:32-43`: bar → heading → underline tabs,
"three signals keeping the two ranks apart"). Splitting them into two partials would let a leaf
template forget the tab strip.

**Call site contract** (replaces today's `['active' => 'x', 'features' => …]`):

```php
<?= $this->partial('admin/_console', ['area' => 'content', 'tab' => 'structure']) ?>
```

Two keys, both **explicit**, never derived from the request path. Deriving from `$request->path()`
cannot work: `/admin/boards/12/edit`, `/admin/users/7`, `/admin/packages/3/consent`,
`/admin/tags/9/merge` match no tab href. Today's `_nav.php` already solves this by explicit
passing (`board_edit.php` passes `active => 'structure'`) — keep that, it is the right design.
For drill-ins, `tab` is the **parent** tab, which is exactly the design's own rule:
`AdminPeople.dc.html:517` `atRoles: s.view !== 'simulator'` (the role record keeps Roles lit),
`AdminMembers.dc.html:616` `atDirectory: s.view !== 'invites'`, `AdminPackages.dc.html:582`
`atCatalogue: view ∈ {catalogue, detail, plan, consent, security}`.

**Data the partial needs:**

| Datum | Source | Notes |
|---|---|---|
| `$area` | call site | one of the 11 keys |
| `$tab` | call site | parent tab key for drill-ins |
| `$features` | `$this->shared('features', [])` — same fallback as `_nav.php:4` | must tolerate a missing `settings` row (`App::shareViewGlobals` wraps every lookup) |
| `$brand['name']` | `$branding['name']` shared global (`layout.php:6`) | replaces the `Imladris` wordmark |
| `$current_user` | shared global | for the moderator-reduced tier (§3 constraint) |
| the map itself | a `const` array inside the partial, mirroring `_nav.php:7-50` | `[area => ['label','h1','aria','tabs' => [tab => ['label','href','flag'|'flags_any']]]]` |

**H1 comes from the map, not the leaf template.** That is what makes the ten (eleven) H1s
consistent — `Boards & tags`, `Members & invitations`, `General & intelligence` etc. are *area*
titles, so no leaf may set its own `<h1>`. Leaf templates keep `$this->section('title', …)` for
`<title>` only.

**Drill-in heading rule** (from the design, verbatim mechanics): a drill-in renders, *below* the
tab strip, a back button then an `<h2>`, never a second `<h1>`:
- `AdminPeople.dc.html:146-149` — `‹ All roles` then `<h2 … font-size: 1.9rem>{{ recordName }}</h2>`
- `AdminMembers.dc.html:205-212` — `‹ All members` then `<h2 … font-size: 2rem>`
- `AdminPackages.dc.html:103-104` — `‹ Package catalogue` then `<h2 … font-size: 2rem>`

So `user_record.php:28`, `role_edit.php:30`, `package_detail.php:34`, `board_edit.php:5`,
`webhook_detail.php:11`, `package_publisher.php:8`, `provider_disable.php:8`,
`structure_confirm.php:5`, `tag_merge_confirm.php:5`, `users_bulk_confirm.php:13`,
`badge_rule_preview.php:8`, `package_plan.php:11`, `package_consent.php:12`,
`package_security.php:8`, `theme_safe_mode.php:10` all demote `<h1>` → `<h2>` and gain the
back link. Fifteen templates.

### 6.2 Markup skeleton (CSP-clean, PE-clean)

```php
<div class="admin-bar">
  <div class="admin-bar-id">
    <span class="admin-bar-brand"><?= $this->partial('partials/star', ['size' => 24]) ?><span class="admin-bar-wordmark"><?= $e($brand['name']) ?></span></span>
    <a class="admin-bar-exit" href="/"><svg …/>Back to the forum</a>
    <span class="admin-bar-mode">Admin mode</span>
  </div>
  <nav class="admin-tier" aria-label="Admin areas" data-admin-tier>
    <!-- active:   --><span class="admin-tier-item is-active" aria-current="page">Content</span>
    <!-- normal:   --><a class="admin-tier-item" href="/admin/structure">Content</a>
    <!-- disabled: --><span class="admin-tier-item is-disabled" aria-disabled="true"
                            data-destination="/admin/packages">Packages<span class="sr-only">Disabled until the feature flag is enabled</span></span>
  </nav>
</div>
<div class="admin-console">
  <h1 class="admin-title">Boards &amp; tags</h1>
  <nav class="admin-tabs" aria-label="Content sections">
    <span class="admin-tab is-active" aria-current="page">Boards &amp; categories</span>
    <a class="admin-tab" href="/admin/tags">Tags</a>
  </nav>
  <div class="admin-pane"><!-- leaf content --></div>
</div>
```

Three deliberate departures from the `.dc.html`, each a **constraint**:

1. **`<button onClick=…>` → `<a href>`.** Every tab in every `.dc.html` is
   `<button onClick="{{ goTags }}">` (e.g. `AdminContent.dc.html:77`). Under PE the tab strip must
   work with JS off, so tabs are links to real routes. The rendered result is identical because
   `.admin-tab`/`.admin-tier-item` reset `border:0; background:transparent` on both element types.
2. **Active item is a `<span>`, not a hrefless `<a>`.** `AdminNav.jsx:70` renders
   `href={active ? undefined : …}` — an anchor without `href` is not focusable and reads as a
   generic element to AT. `<span … aria-current="page">` is the honest equivalent, and
   `.admin-tier-item.is-active { cursor: default }` (`components.css:342`) already anticipates
   non-interactivity.
3. **A disabled state exists at all.** `AdminNav.jsx` has no concept of a flag-off area. Production
   must keep the `is-disabled` + `aria-disabled="true"` + `data-destination="…"` +
   `"Disabled until the feature flag is enabled"` contract from `_nav.php:81-84`, because
   `AppAdminNavIaTest.php:39-46` and both remediation directories assert it. **feature-added**;
   style it as a `.34` -opacity `--text-faint` pill in the tier idiom.
   *Rollup rule:* a tier item is disabled only when **every** tab in its area is flag-off (e.g.
   `Packages` with `package_registry=false` **and** `server_extensions=false`). Otherwise the tier
   item links to the first *enabled* tab's href, not to a fixed area landing page.

### 6.3 Flag → tab map (complete, for the partial's `const`)

```
overview.dashboard        —                     overview.audit           —
moderation.reports        moderation_queue      moderation.approvals     moderation_queue
moderation.appeals        appeals               moderation.antiabuse     anti_abuse
content.structure         —                     content.tags             tags
people.roles              capabilities          people.simulator         capabilities
members.directory         —                     members.invitations      invitations
appearance.branding       branding              appearance.themes        package_themes
notifications.email       email                 notifications.announcements  announcements
integrations.tokens       api_tokens            integrations.webhooks    webhooks
integrations.providers    provider_registry
packages.catalogue        package_registry      packages.registries      package_registry
packages.extensions       server_extensions  ← default OFF (FeatureFlags.php)
features.flags            —                     features.badges          badge_rules
features.emoji            custom_emoji
settings.general          —                     settings.intelligence    flags_any[community_memory, automated_context]
```

Every flag verified present in `src/Core/FeatureFlags.php::DEFAULTS`. Only `server_extensions` is
default-OFF among these (plus `custom_css`, which gates a *sub-block* of Branding, not a tab).

### 6.4 The CSS block

**Half of it already exists upstream and is not yet built.** `docs/design-system/imladris/components.css:323-342`
carries the complete `.admin-bar` / `.admin-bar-id` / `.admin-bar-brand` / `.admin-bar-wordmark` /
`.admin-bar-exit` / `.admin-bar-mode` / `.admin-tier` / `.admin-tier-item` block with the design's
own comments. `public/assets/imladris.css` does **not** contain it
(`grep -n "admin-bar" public/assets/imladris.css` → no hits), because that file is generated and
stale relative to the 2026-08-02 mirror sync. `components.css` is in
`ImladrisAssetBuilder::CSS_SOURCES` (`src/Support/ImladrisAssetBuilder.php:19-26`), so:

> **Step 1: `composer build:imladris`.** That is the entire delivery mechanism for the tier CSS.
> Do not hand-write `.admin-bar` rules into `app.css` — `check:imladris` would fail and the file
> header says "do not edit this file directly."

All tokens it consumes are already in the built `imladris.css`: `--gold-500` (17 refs),
`--brand-subtle` (8), `--on-brand-subtle` (3), `--surface-review` (3), `--on-review` (4),
`--radius-pill` (16), `--weight-semibold` (8), `--weight-medium` (6), `--surface-sunken` (24),
`--border-hair` (21), `--font-label` (23), `--font-display` (6).

**Step 2: the application-owned complement in `public/assets/app.css`,** replacing the block at
`:2800-2932` and the mobile block at `:3279-3387`. This covers exactly what the `.dc.html` screens
carry as inline `style=""` (and therefore what `components.css` does not own):

```css
/* content column — the .dc.html wrapper div, per-area max-width */
.admin-console { max-width: 1160px; margin: 0 auto; padding: 22px 28px 110px; }
.admin-console[data-area="content"],
.admin-console[data-area="people"],
.admin-console[data-area="appearance"],
.admin-console[data-area="settings"]      { max-width: 1100px; }
.admin-console[data-area="notifications"] { max-width: 1140px; }

/* H1 — AdminOverview.dc.html:286 et al, identical in all ten */
.admin-title { margin: 0; font-family: var(--font-display); font-weight: 500;
               font-size: 2.1rem; line-height: 1.1; letter-spacing: -0.01em; color: var(--text-strong); }

/* per-area underline tab strip — AdminContent.dc.html:74-79 et al, identical in all ten */
.admin-tabs { display: flex; flex-wrap: wrap; gap: 2px; margin: 16px 0 0;
              border-bottom: 1px solid var(--border-hair); }
.admin-tab  { padding: 9px 15px; margin-bottom: -1px; border: 0; border-bottom: 2px solid transparent;
              background: transparent; font-family: var(--font-label); font-size: .84rem;
              letter-spacing: .03em; color: var(--text-muted); text-decoration: none; cursor: pointer; }
.admin-tab:hover { color: var(--text-strong); }          /* style-hover="color: var(--text-strong);" */
.admin-tab.is-active { border-bottom-color: var(--gold-500); color: var(--text-strong); cursor: default; }
.admin-tab.is-disabled { color: var(--text-faint); cursor: default; }

/* pane — production-owned, survives */
.admin-pane { display: flex; flex-direction: column; gap: 22px; padding-top: 24px; min-width: 0; }
```

`padding-top: 24px` on the pane is not invented — every `.dc.html` view block opens with
`<div style="padding-top: 24px;">` (e.g. `AdminContent.dc.html:83`, `AdminOverview.dc.html:298`;
`AdminPeople.dc.html:145` uses `20px` for the drill-in, `AdminSettings.dc.html:425` folds it into
the grid).

**Deleted from `app.css`:** `.admin` (grid + 224px column), `.admin-head*`, `.admin-sections-toggle`,
`.admin-nav-scrim`, `.admin .subnav*`, `.admin-nav-drawer-head`, `.admin-nav-group*`,
`.admin-nav-link*`, `.subnav-item-note`, and the entire `has-js` drawer block `:3310-3387`
including `body.admin-nav-open`. **Do not leave them as dead chrome.**

### 6.5 What happens to the mobile drawer in `app.js`

`app.js:766-875` is a self-contained block guarded by
`if (adminNavToggle && adminNav && adminNavScrim)`. It hooks four selectors that the new partial
stops emitting: `[data-admin-nav-toggle]`, `[data-admin-nav]`, `[data-admin-nav-close]`,
`[data-admin-nav-scrim]`. Once `_nav.php` is gone the guard is `false` and the block is inert.

**Delete it** (110 lines) rather than leave it, per the no-dead-chrome rule. Three consequences:

1. **Mobile nav is not lost — it changes mechanism.** `.admin-tier { overflow-x: auto;
   scrollbar-width: thin }` with `flex: none; white-space: nowrap` items is the design's own
   answer, and `components.css:335-336` says so explicitly: *"Overflow stays visible on purpose:
   below ~900px the tier scrolls, and a thin scrollbar is the only honest signal that Settings is
   off-edge."* The tab strip already wraps (`flex-wrap: wrap`). Zero JS. **This is the strongest
   CSP/PE outcome available: the entire admin nav becomes JS-free.**
2. `app.js:869-873` couples the admin drawer to the member sidebar toggle
   (`if (document.body.classList.contains('nav-open')) { setAdminNav(false, false); }`). That
   coupling disappears with the member sidebar itself (§1: admin pages stop rendering
   `partials/sidebar`), so nothing is orphaned.
3. **`ADMIN.md §9.4` must be amended** — it currently mandates *"the section nav in a drawer
   (mirrors the app's mobile pattern)"*. Same ADR as §1.

### 6.6 CSP cleanliness — the mechanism audit

`SecurityHeaders` sets `script-src 'self'; style-src 'self'`, no `'unsafe-inline'`. The ten
`.dc.html` screens violate this in three ways; each has a mechanical fix that preserves the
rendered result exactly:

| `.dc.html` mechanism | Count | Production mechanism |
|---|---|---|
| `style="…"` on every element | thousands | external classes in `imladris.css` (tier, already upstream) + `app.css` (§6.4) |
| `style-hover="…"` / `style-focus="…"` pseudo-attributes (e.g. `AdminContent.dc.html:76` `style-hover="color: var(--text-strong);"`) | ~40 in the nav regions | real `:hover` / `:focus-visible` rules — already how `components.css:341` writes `.admin-tier-item:hover` |
| `<script type="text/x-dc">` behaviour block at the end of every file | 10 | none needed for the nav — it becomes zero-JS (§6.5) |

The one remaining runtime hook is the layout variant. **`layout.php` needs a fourth variant.**
Today `$variant = $this->block('variant', 'app')` (`layout.php:3`) and `app` renders
topbar + sidebar + main (`:56-64`). Add `admin`:

```php
<?php elseif ($variant === 'admin'): ?>
    <?= $content ?>   <!-- the partial emits .admin-bar itself; no topbar, no sidebar -->
```

with `$showChrome = $variant !== 'auth' && $variant !== 'admin'` at `layout.php:14` so
`partials/topbar` is suppressed. `partials/flash` still renders — inside `.admin-console`, above
`.admin-pane`, matching the design's flash placement
(`AdminMembers.dc.html:203-208`: `role="status"`, `--surface-done`/`--green-200`/`--success`,
`margin-top: 20px`, immediately after the tab strip and before the view block).

Every leaf template then adds `$this->section('variant', 'admin')`, except
`theme_safe_mode.php` which keeps `'plain'` (`:5`, deliberate).

### 6.7 Anti-draft-loss (rule 7) is unaffected

The restructure touches chrome only. `moderation.php:14-21` (`$settings_errors`/`$settings_old`),
`api_tokens.php:5` (`$old['scopes']`), `mod/user.php:11-17` (the `$ferr` context-scoped field
error), `mod/appeals.php:5-7` (`$old['appeal_id']`) all live inside `.admin-pane`. Moving the pane
under a new chrome does not touch the 422 re-render path. **Verify** after the move that every
`ValidationException` re-render still passes `area`/`tab` to the partial — a 422 that forgets them
would render an unlit tier, which is the one regression this restructure can introduce.

---

## 7. Summary of classifications

| Class | Count | Items |
|---|---|---|
| **copy** | 10 placement moves (M1–M10) + the whole nav-model swap + eyebrow deletion + 15 `h1`→`h2` demotions | production simply changes |
| **feature-added** | 3 | the Moderation area (§3, 5 templates, 3 flags); flag-disabled tier/tab states; the `mod-count` badge on the Reports tab |
| **feature-removed** | 0 | the design shows nothing production lacks *in the nav chrome* (pane-level gaps are the per-screen reconcilers' business) |
| **feature-changed** | 1 | M11 — Permission simulator promoted from inbound-link orphan to nav tab |
| **constraint** | 5 | `<button onClick>` → `<a href>`; active item `<a href=undefined>` → `<span>`; inline styles → external classes; `<script type="text/x-dc">` → zero JS; moderator-reduced tier (`/mod/*` authz vs `/admin/*` `requireAdmin()`) |
| **doc amendments required** | 3 | `ADMIN.md §9.2` (left-nav grouped), `ADMIN.md §9.4` (drawer), a new ADR superseding the IA clause of `docs/adr/0023` |
