# D — admin-people: `AdminPeople.dc.html` vs production Roles & capabilities

Design source: `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-people/AdminPeople.dc.html`
(628 lines; markup ends at line 351, `<script type="text/x-dc">` runs 352–626).

Production home (verified, read in full):

| File | Route(s) |
|---|---|
| `C:/Users/htper/community-forums/templates/admin/roles.php` | `GET /admin/roles`, 422 re-render of `POST /admin/roles` |
| `C:/Users/htper/community-forums/templates/admin/role_edit.php` | `GET /admin/roles/{id}`, 422 re-render of `POST /admin/roles/{id}`, `/clone`, `/assignments`, `/role-assignments/{id}/renew` |
| `C:/Users/htper/community-forums/templates/admin/role_simulator.php` | `GET /admin/roles/simulator` |
| `C:/Users/htper/community-forums/src/Controller/AdminRoleController.php` | all nine routes, registered `src/Core/App.php:2219-2227` |

Supporting production truth read for this pass: `src/Service/RoleService.php`,
`src/Service/RoleAssignmentService.php`, `src/Service/PermissionSimulatorService.php`,
`src/Security/CapabilityCatalog.php`, `src/Security/EnforcedCapabilities.php`,
`src/Security/ReauthGate.php`, `database/migrations/0050_phase5_capabilities_roles.php`,
`templates/admin/_nav.php`, `public/assets/app.css`, `tests/browser/gate-a.spec.ts`,
`tests/browser/a11y.spec.ts`.

Note: the controller lives at `src/Controller/AdminRoleController.php` (singular `Controller`), not
`src/Controllers/` as the brief spelled it. There is no `src/Controllers/` directory.

---

## 0. Scope verdict — Users, Invitations and Badge rules are NOT in this screen

**Authoritative answer: no.** `AdminPeople.dc.html` models exactly two views plus one drill-in:

* the `Roles` tab (roles table + create-a-custom-role form),
* the role record drill-in (definition / capabilities held / clone / assignments),
* the `Permission simulator` tab.

Evidence: the only navigation the screen declares is `<nav aria-label="People sections">` at
`AdminPeople.dc.html:40-45`, whose entire membership is `Roles` (`:41-42`) and
`Permission simulator` (`:43-44`). The three view gates are `showRoles`, `showRecord`,
`showSimulator` (`:533-535`). The screen's own `@template description` (`:10`) says so verbatim:

> "The people section of the operator's desk: the role table with resolver posture, a drill-in role record (definition, capability fieldsets, clone, assignments), and the permission simulator that runs can(actor, capability, target, time)"

Nothing in the 628 lines mentions a user directory, an invitation, or a badge rule.

Production's **People** nav group has four entries (`templates/admin/_nav.php:22-27`):

```
['key' => 'users',        'label' => 'Users',       'href' => '/admin/users'],
['key' => 'roles',        'label' => 'Roles',       'href' => '/admin/roles',       'flag' => 'capabilities'],
['key' => 'invitations',  'label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations'],
['key' => 'badge_rules',  'label' => 'Badge rules', 'href' => '/admin/badge-rules', 'flag' => 'badge_rules'],
```

So three of the four People surfaces are **unrepresented by this screen**:

| Production surface | Templates | Represented by AdminPeople? | Where it *is* represented |
|---|---|---|---|
| Users directory + record + bulk confirm | `templates/admin/users.php` (175 ln), `user_record.php` (337 ln), `users_bulk_confirm.php` (67 ln) | **No** | `docs/design-system/imladris/templates/admin-members/AdminMembers.dc.html` — "Directory" ×14, "Invitations" ×4 (untracked, landed 2026-08-03 20:20) |
| Invitations | `templates/admin/invitations.php` (102 ln) | **No** | same `AdminMembers.dc.html` |
| Badge rules | `templates/admin/badge_rules.php` (82 ln), `badge_rule_preview.php` | **No** | `docs/design-system/imladris/templates/admin-features/AdminFeatures.dc.html` — "Badge rules" ×3 |

**Do not invent anatomy for them inside this screen.** They belong to the `admin-members` and
`admin-features` Stage-1 scopes. If those two screens are held back, Users / Invitations / Badge
rules simply keep their current production markup — this migration must not touch them.

### Intent evidence from the retired `ui_kits/admin` (reference only — DO NOT PORT)

Recorded solely because the brief asked. `ui_kits/*` is superseded by `templates/` and is
explicitly excluded from the asset build (`src/Support/ImladrisAssetBuilder.php:19-25`).

* **Users** — `ui_kits/admin/AdminSections.jsx:127-158`: one `.card` with an `.inline-form`
  search (`placeholder="Search username, name, or email"`, max-width 320) + a secondary
  `Search` button, then `<table className="audit">` with columns
  `User | Role | State | Regard | Joined` — user cell is `Monogram size="sm"` + username link +
  muted display name; role is `.role-pill role-{role}`; state is `.state state-{state}`; regard is
  `.tnum`; joined is `.mono`. Footer is `<nav className="pager">` with a disabled `Previous` and a
  `Next`. Lexicon violation: the **Regard** column heading is fiction (production heading would be
  "Reputation" / "Posts").
* **User record** — `AdminSections.jsx:161-203`: `← All users` back `linkbtn`, an identity card with
  `Monogram size="lg" gilt` + display name + mono `@username` and a `<dl className="id-stats">`
  of `Role / State / Regard / Profile`, then a "Cosmetic title" card
  (`Effective: … · Derived ladder: Legend`) and a "Badges" card
  (grant form + `Held manual badges` list with `✦` markers and `Revoke` linkbtns).
  Fiction throughout: `Master of the House`, `Loremaster of Imladris`, `Regard`.
* **Badge rules** — `AdminSections.jsx:205-240`: a "Create rule" card
  (`Badge` select / `Rule` select `Post count · Thread count · Reputation · Solved answers` /
  `Threshold` number / `Board scope` select) and a "Rules" card as a `.link-list` of
  `<strong>{badge}</strong> <span class="rule-meta">{rule} ≥ {threshold} · {board}</span>` +
  `.badge`/`.badge-muted` Enabled/Disabled chip + `Preview / Backfill / Disable / Revoke awards`
  linkbtns. Note the kit's one-click `Backfill` and `Revoke awards` contradict the typed-confirm
  requirement locked by the 2026-07-02 plan Task 7 — another reason it is reference-only.
* **Invitations** — the kit has no `AdminSections` entry; only the parity mirror
  `ui_kits/admin/AdminParity.jsx:588-620` (a faithful recreation of the *existing*
  `admin/invitations.php`, including the secret-link flash), so it carries no new design intent.
* **Roles** — likewise only a parity mirror; `ui_kits/admin/parity-data.js:3` names
  "roles, sign-in providers, invitations" as recreations of the `.php` templates.
  `AdminPeople.dc.html` supersedes it.

---

## 1. Section order comparison

### Design order (verbatim headings / eyebrows, top to bottom)

| # | Design section | Line | Verbatim text |
|---|---|---|---|
| D0 | Sticky topbar | `:22-28` | 8-point star SVG + wordmark **"Imladris"** + back link **"Back to the council"** |
| D1 | Page head — eyebrow | `:34` | **"Operator desk · People"** |
| D1 | Page head — h1 | `:35` | **"Roles & capabilities"** |
| D1 | Page head — mode pill | `:37` | **"Admin mode"** |
| D2 | Local tabs `aria-label="People sections"` | `:40-45` | **"Roles"** · **"Permission simulator"** |
| D3 | *(view=roles)* Info callout | `:50-53` | "Resolver posture: **shadow** (`CAPABILITIES_MODE`). Under `shadow` the legacy rules decide and the resolver only shadow-compares; under `enforce` the resolver decides and fails closed. System roles are protected compatibility anchors and cannot be edited — clone one to adapt it. Try changes safely in the **permission simulator**." |
| D4 | Filter bar | `:55-69` | search `placeholder="Search roles"`; segmented **"All" / "Protected anchors" / "Custom"**; right-aligned count `{{ roleResultLabel }}` |
| D5 | Roles table card *(no heading)* | `:71-98` | cols **Name · Key · Kind · Version · Capabilities · Active assignments · (sr-only "Actions")** |
| D5e | Roles empty state | `:99-104` | h3 **"No roles match this filter"** / p "Clear the search, or create a custom role below." |
| D6 | h2 **"Create a custom role"** | `:107-151` | intro "Only delegable capabilities are offered — protected authority is never on this list. Creating a role is a reauthenticated action."; `role="alert"` banner; Name / Description (optional) grid; 2-col `<fieldset><legend>{group}</legend>` capability grid; **"Confirm your password"** + **"Create role"** + `{{ selectedCapLabel }}` |
| D7 | *(view=record)* back button | `:158` | **"All roles"** |
| D8 | Record head | `:160-163` | h2 `{{ recordName }}` + mono `v{{ recordVersion }}` |
| D9 | Record kind note | `:164` | "`{{ recordKey }}` — {{ recordKindNote }} Active assignments affected by changes: **{{ recordImpact }}**." |
| D10 | *(system)* h3 **"Capabilities held"** | `:166-175` | 2-col `<ul>` of `<code>` keys |
| D11 | *(custom)* h3 **"Edit definition"** | `:177-195` | Name / Description grid; **"Save (bumps version)"**; `role="status"` **"Saved — now v{n}."** |
| D12 | h3 **"Clone into a new custom role"** | `:197-217` | "Cloning copies only currently-enforceable capabilities, so the copy is never wider than the anchor."; New role name + Confirm your password + **"Clone"**; alert + status lines |
| D13 | h3 **"Assignments"** | `:219-250` | table **Member · Scope · Window · Status · (blank)**; empty "No one has been assigned this role yet." |
| D14 | h4 **"Assign this role"** *(inside D13's card)* | `:252-286` | 3-col grid: Member username / Scope / Board / category id (blank = site-wide) / Starts (UTC) (blank starts now) / Ends (UTC) (blank never expires) / Confirm your password → **"Assign"** |
| D15 | *(view=simulator)* intro | `:294` | "Runs `can(actor, capability, target, time)` on the real resolver. While capabilities are in shadow, answers predict the post-cutover decision; live requests still use legacy authority." |
| D16 | h2 **"Simulate"** | `:296-322` | 2-col grid: Actor (username, id, or guest) / Capability / Board id (optional target) / At (optional, UTC) → **"Simulate"** |
| D17 | h2 **"The simulator could not answer"** | `:324-329` | rust left-rule card, `{{ simErrorText }}` |
| D18 | h2 **"Result"** | `:331-345` | **Allowed** / **Denied** chip + statement; `<ul>`: "Decisive rule: `{src}`" / "Reason: {…}" / "Via role: `{key}`" |

### Production order

`templates/admin/roles.php`

| # | Section | Line |
|---|---|---|
| P1 | `header.admin-head` → h1 "Roles & capabilities" + `.pill.pill-admin` "Admin mode" | `:16-19` |
| P2 | `admin/_nav` partial — full 8-group vertical rail | `:20` |
| P3 | `p.muted` resolver-posture paragraph (incl. the extra telemetry sentence + inline `<a href="/admin/roles/simulator">`) | `:23-30` |
| P4 | `section.card` h2 **"Roles"** → `.table-scroll[role=region][aria-label="Role definitions"]` → `table.audit` | `:32-52` |
| P5 | `section.card` h2 **"Create a custom role"** → `form.stacked` | `:54-91` |

`templates/admin/role_edit.php`

| # | Section | Line |
|---|---|---|
| P6 | `header.admin-head` → h1 `{name} <small>v{n}</small>` + pill | `:29-32` |
| P7 | `admin/_nav` | `:33` |
| P8 | `p.muted` "`{key}` - {kindNote} Active assignments affected by changes: **{n}**." | `:36-40` |
| P9 | *(custom)* `section.card` h2 **"Edit definition"** — incl. the capability fieldsets | `:42-79` |
| P10 | *(system)* `section.card` h2 **"Capabilities held"** — flat `<ul>` | `:80-87` |
| P11 | `section.card` h2 **"Clone into a new custom role"** | `:89-104` |
| P12 | *(custom only)* `section.card` h2 **"Assignments"** | `:120-169` |
| P13 | *(custom only)* `section.card` h2 **"Assign this role"** — separate card | `:171-220` |

`templates/admin/role_simulator.php`

| # | Section | Line |
|---|---|---|
| P14 | `header.admin-head` → h1 "Permission simulator" + pill | `:7-10` |
| P15 | `admin/_nav` | `:11` |
| P16 | `p.muted` intro | `:14-15` |
| P17 | `section.card` h2 **"Simulate"** — `form method="get"` | `:17-39` |
| P18 | `section.card` h2 **"Result"** — holds *both* the error and the decision | `:41-61` |

### Order deltas

1. Design has **no `<h2>Roles</h2>`** over the table (P4 has one).
2. Design merges **Assignments + Assign this role into one card** (D13/D14 as h3 + h4); production
   splits them into two `h2` cards (P12, P13).
3. Design's **capability fieldsets are inside "Edit definition"** on the record and inside
   "Create a custom role" on the list — identical to production (P9, P5). No delta.
4. Design puts **Capabilities held (system) before Edit definition (custom)**; production reverses
   the source order (P9 then P10). They are mutually exclusive branches, so the rendered order is
   identical — **no real delta**.
5. Design has **no admin rail**; production has the locked 8-group rail (P2/P7/P15).
6. Design **inserts a filter bar (D4)** between the callout and the table; production has none.
7. Design **splits the simulator error into its own card (D17) above Result (D18)**; production
   nests the error inside the Result card (P18).

---

## 2. Difference table

Risk key: **low** = markup/CSS only; **medium** = touches a 422 round-trip, a pinned test selector,
or server behavior; **high** = changes authorization, data, or a locked decision.

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| K1 | Whole screen | constraint | ~200 inline `style="…"` + `style-hover=` / `style-focus=` attrs, `<helmet><style>` with `@keyframes apRise`, `<x-dc>` runtime, `./ds-base.js` + `./support.js` | `SecurityHeaders` emits `style-src 'self'` with no `style-src-attr`; zero `style="` in `templates/**/*.php` | Every rule becomes an external class in `public/assets/app.css` (unlayered). Pixels, spacing and order must still match. Do not port `ds-base.js`/`support.js`. | low |
| K2 | Three views ↔ three URLs | constraint | one client screen, `view: 'roles' \| 'record' \| 'simulator'` (`:533-535`); tabs are `<button onClick={{ goRoles }}>` (`:41-44`) | three real routes `App.php:2219,2221,2222`; each template sets its own `$this->section('title', …)` and its own `<h1>` | Tabs become `<a href="/admin/roles">` / `<a href="/admin/roles/simulator">` with `aria-current="page"`. Each page keeps its own `<h1>`. Ship as one shared partial included by all three templates. | low |
| K3 | Roles table row action | constraint | `<button onClick="{{ r.open }}">{{ r.action }}</button>` (`:94`) | `<a href="/admin/roles/{id}">` (`roles.php:46`) | Keep the anchor; give it the design's outlined-control skin. | low |
| K4 | Callout inline link | constraint | `<button onClick="{{ goSimulator }}">permission simulator</button>` (`:52`) | `<a href="/admin/roles/simulator">permission simulator</a>` (`roles.php:30`) | Production already correct — keep the anchor, adopt the callout skin only. | low |
| K5 | All four mutating forms | constraint | `<form onSubmit="{{ createRole }}">` etc., no method/action/token | `method="post"` + real action + `$this->csrfField()` (`roles.php:56-57`, `role_edit.php:45-46, 91-92, 142-143, 146-147, 173-174`) | Keep `method="post"`, real actions and `csrfField()` on every form. Never propose an exemption. | low |
| K6 | Success + failure feedback | constraint | inline client state: `role="status"` "Saved — now v{n}." (`:192`), "Cloned. …" (`:215`); `role="alert"` create/clone/assign errors (`:111, 212, 284`) | POST→redirect→GET flash (`AdminRoleController.php:67, 131, 151, 187, 215, 249`) and 422 re-render carrying `->errors` + `->old` (`:69, 133, 156, 189, 251`) | Keep PRG + flash for success and the 422 anti-draft-loss re-render for failure. The design's status/alert lines become the flash region and the field-error paragraphs. | medium |
| K7 | "{n} capabilities selected" | constraint | live counter `selectedCapLabel` (`:148`, `:555`) recomputed on every checkbox change | none | May ship only as a PE decoration in `public/assets/*.js` reading checkbox state via a `data-*` hook; absent (not empty-but-styled) with JS off. | low |
| K8 | Result card entry animation | constraint | `animation: apRise 180ms var(--ease-calm) both` (`:332`) + `@keyframes apRise` in `<helmet><style>` (`:16`) | none | Move the keyframes into `app.css` and wrap in `@media (prefers-reduced-motion: no-preference)`. | low |
| K9 | Every interpolation | constraint | `{{ r.name }}`, `{{ a.username }}`, `{{ simReason }}` … raw | `$e(…)` everywhere (`roles.php:40-46`, `role_simulator.php:49-56`) | Every value escaped with `$e()` / `$this->e()`. | low |
| K10 | Availability | constraint | screen renders unconditionally | `AdminRoleController::gate()` throws `NotFoundException` when `capabilities` is off (`AdminRoleController.php:27-32`); nav renders the disabled span (`_nav.php:24, 80-84`, note "Disabled until the feature flag is enabled") | Preserve the 404 gate and the disabled-nav idiom. The design models neither. | medium |
| K11 | Topbar | constraint | star SVG + "Imladris" + "Back to the council" (`:22-28`) | `templates/layout.php` owns the shell; brand name from `$brand['name']` | Do not port. The design's topbar is a prototype frame, not a component. | low |
| C1 | Page head | copy | eyebrow **"Operator desk · People"** (`:34`) above the h1 | no eyebrow on any of the three templates (`roles.php:16-19`, `role_edit.php:29-32`, `role_simulator.php:7-10`) | Add `<span class="eyebrow">Operator desk · People</span>` inside `.admin-head` on all three (precedent `admin/dashboard.php:6`, `admin/settings.php:14`; `.admin-head .eyebrow` already styled at `app.css:2822`). | low |
| C2 | Local tabs | copy | two-item strip with 2px `--gold-500` underline on the current tab (`:40-45`) | absent; `/admin/roles/simulator` is reachable only from the prose link at `roles.php:30` and has no nav entry | Add the strip (as links, per K2) to all three templates. | low |
| C3 | Resolver posture | copy | info callout: `--surface-info` ground, `--river-200` border, 3px `--info` left rule, info icon, `--on-info` text, `max-width: 82ch` (`:50-53`) | plain `<p class="muted">` (`roles.php:23-30`) | Author a `.callout` / `.callout-info` anatomy in `app.css` and wrap the paragraph. | low |
| C4 | Roles table card | copy | no heading; card is `padding: 6px 20px 10px`, `--radius-lg`, `--shadow-sm` (`:71`) | `<h2>Roles</h2>` (`roles.php:33`) | Drop the h2, adopt the card padding/shadow — but keep `.table-scroll`'s `role="region"` + `aria-label="Role definitions"` so the region stays named. | low |
| C5 | Kind column | copy | chip: "Protected anchor" on `--surface-review`/`--on-review`, "Custom" on `--surface-sunken`/`--text-muted`, `999px` (`:88-89`) | bare text, same two words (`roles.php:42`) | Wrap in the chip. Use `var(--radius-pill)`, not `999px`. | low |
| C6 | Numeric columns | copy | Version / Capabilities / Active assignments are `text-align: right` + `--font-mono` `.82rem`; version prefixed `v` (`:77-79, 91-93`) | left-aligned body text; `v<?= (int) …` only on version (`roles.php:43-45`) | Right-align + mono the three numeric columns; keep the `int` casts. | low |
| C7 | Key column | copy | `<code>` on `--surface-sunken`, `--radius-sm`, `.76rem` (`:86`) | bare `<code>` (`roles.php:41`); `.audit code` already tints at `app.css:610` | Reconcile `.audit code` to the design's radius/size within the roles table. | low |
| C8 | Row action | copy | outlined pill-ish control, `1.5px --border-soft`, `--radius-md`, `.72rem` label, hover `--surface-sunken` (`:94`) | plain link (`roles.php:46`) | Skin the anchor as the outlined control. | low |
| C9 | Create-role intro | copy | "Only delegable capabilities are offered — protected authority is never on this list. Creating a role is a reauthenticated action." (`:109`) | the delegable half is buried in every legend (`roles.php:71`); the reauth half is nowhere | Add the intro paragraph; shorten the legend (C10). | low |
| C10 | Fieldset legend | copy | `{{ g.label }}` only, `.68rem` uppercase `--gold-ink` (`:127`) | "`{Group}` capabilities (delegable only; protected authority is never offered)" (`roles.php:71`) | Reduce to the group label; the sentence moves to C9. Record edit already uses "`{Group}` capabilities" (`role_edit.php:59`) — reduce that too. | low |
| C11 | Capability fieldsets | copy | `grid-template-columns: repeat(2, 1fr); gap: 14px` of bordered fieldsets, `--radius-md`, `--border-hair` (`:124-141`) | full-width stacked `<fieldset>` inside `.stacked` (`roles.php:69-81`) | Adopt the 2-col grid. Collapse to 1 col below the admin breakpoint. | low |
| C12 | Name / Description | copy | `grid-template-columns: 1fr 1.6fr; gap: 14px 18px` (`:114`) | `.stacked` vertical labels (`roles.php:58-66`) | Adopt the grid. Keep `field_attrs()` / `field_error()` (see A2). | low |
| C13 | High-risk marker | copy | pill "High risk", uppercase `.62rem`, `color-mix(in srgb, var(--rust) 12%, var(--surface-raised))` ground, `--danger` ink (`:134`) | `<span class="pill">high risk</span>` (`roles.php:76`, `role_edit.php:64`) | Adopt casing + the rust wash. `.pill` is already uppercase at `app.css:1490`, so change the source text to "High risk". | low |
| C14 | Create-role footer | copy | password + submit + count on one `align-items: flex-end` row (`:142-149`) | password stacked above a `.form-actions` row (`roles.php:84-89`) | Adopt the inline row (count per K7). | low |
| C15 | Static role count | copy | `{{ roleResultLabel }}` → "5 roles" / "1 role" (`:68`, `:545`) | none | Adoptable *without* the filter as a static total from `count($rows)`. Ship the label; do not ship the filter (R1). | low |
| C16 | Record back link | copy | "‹ All roles" chevron + label above the record head (`:158`) | **no back link anywhere on `role_edit.php`** | Add `<a href="/admin/roles">All roles</a>` with the chevron SVG. | low |
| C17 | Record head | copy | h2 `{{ recordName }}` baseline-aligned with mono `v{{ recordVersion }}` in `--text-faint` (`:160-163`) | `<h1>{name} <small>v{n}</small></h1>` inside `.admin-head` (`role_edit.php:30`) | Adopt the baseline row + mono version. **Keep it an `<h1>`** — `/admin/roles/{id}` is its own URL and must title itself; the design's h2 is an artefact of the single-screen prototype. | low |
| C18 | Record kind note | copy | "Protected system anchor, read-only." (`:569`) | "Protected system anchor (decision #18), read-only." (`role_edit.php:38`) | Drop "(decision #18)" — an internal citation leaking into operator copy. | low |
| C19 | Capabilities held | copy | `<ul>` as `grid-template-columns: repeat(2, 1fr); gap: 5px 18px`, list-style none (`:169-173`) | flat `<ul><li><code>` (`role_edit.php:83-85`) | Adopt the 2-col grid. | low |
| C20 | Clone card copy | copy | "Cloning copies only currently-enforceable capabilities, so the copy is never wider than the anchor." (`:199`) | the parenthetical "(cloning copies only currently-enforceable capabilities)" sits on the *list* page (`roles.php:29`); the clone card itself has no explanation (`role_edit.php:89-104`) | Move/duplicate the sentence into the clone card in the design's wording. | low |
| C21 | Clone form | copy | one `align-items: flex-end` row: name (flex 1) + password (`flex: 0 1 240px`) + outlined "Clone" (`:200-210`) | `.stacked` (`role_edit.php:91-103`) | Adopt the inline row; keep the scoped `$cloneErrorContext` paragraphs. | low |
| C22 | Assignments + Assign | copy | one card, `h3 "Assignments"` then `h4 "Assign this role"` (`.68rem`/`.16em` caps) (`:219-286`) | two `section.card`s with `h2` each (`role_edit.php:120, 171`) | Merge into one card with the h3/h4 pair. Both forms keep their own `action` and CSRF token. | low |
| C23 | Assignment status | copy | filled pill: Active on `--surface-done`/`--on-done`, Revoked on `--surface-pending`/`--on-pending`, `999px`, `.66rem` (`:237-238`) | `.state.state-{status}` dot-and-lowercase-word (`role_edit.php:139`; `app.css:3465-3497`) | Adopt the filled pill and title-case labels — and extend to four statuses (A5). | low |
| C24 | Window column | copy | mono `.78rem` `--text-muted`, single string "now → 2026-11-01" (`:235`) | two escaped values joined by `&rarr;` with 'now' / 'no expiry' fallbacks (`role_edit.php:138`) | Typography only; keep the server-side composition. | low |
| C25 | Member cell | copy | mono `.82rem` in `--artifact-link` (`:233`) | plain link to `/u/{username}` (`role_edit.php:131`) | Adopt the mono + `--artifact-link` skin; keep the real profile href. | low |
| C26 | Assign form layout | copy | `grid-template-columns: repeat(3, 1fr); gap: 14px 16px; align-items: end`, submit row spans `1 / -1` (`:253, 282`) | `.stacked` (`role_edit.php:173-219`) | Adopt the 3-col grid. | low |
| C27 | Scope select | copy | order + labels `Site-wide` / `Category` / `Board` (`:261-263`) | order + labels `Site-wide` / `A single board` / `A single category` (`role_edit.php:184`) | Adopt the design's terser labels and order. **Must preserve** the `$old['assignment']['scope_type']` selected round-trip (`role_edit.php:185`). | medium |
| C28 | Assign field hints | copy | "Board / category id **(blank = site-wide)**", "Starts (UTC) **(blank starts now)**", "Ends (UTC) **(blank never expires)**"; placeholders `2026-08-10 09:00` / `2026-11-01 00:00` (`:267-276`) | "Board/category id (leave blank for site-wide)", "Starts (UTC) (optional — blank starts now)", "Ends (UTC) (optional — blank never expires)"; placeholder `YYYY-MM-DD HH:MM` (`role_edit.php:191-206`) | Adopt the design's shorter hints and the sample-date placeholders — but keep the literal format visible somewhere, because `RoleAssignmentService.php:261` rejects with "Use the format YYYY-MM-DD HH:MM (UTC), optionally with seconds." | low |
| C29 | Simulator form | copy | `grid-template-columns: repeat(2, 1fr); gap: 14px 18px; align-items: end`, submit spans full width (`:298, 320`) | `.stacked` (`role_simulator.php:19-38`) | Adopt the 2-col grid. Keep `method="get"`. | low |
| C30 | Simulator "At" label | copy | "At (optional, UTC)" with the format only in the placeholder (`:317-318`) | "At (optional, UTC `YYYY-MM-DD HH:MM`)" (`role_simulator.php:34`) | Adopt the shorter label; keep a real placeholder. Do not hard-code a fiction-adjacent future date without checking it reads sensibly. | low |
| C31 | Simulator error | copy | its own card, h2 **"The simulator could not answer"**, 3px `--rust` left rule (`:325-328`) | `<p class="field-error">` *inside* the Result card (`role_simulator.php:44-45`) | Split into the design's dedicated error card above Result. | low |
| C32 | Simulator verdict | copy | pill: Allowed on `--surface-done`/`--on-done`; Denied on the rust wash / `--danger`; uppercase `.74rem`/`.1em` (`:335-336`) | `<strong>Allowed</strong>` / `<strong>Denied</strong>` (`role_simulator.php:48`) | Adopt the pill. `tests/browser/gate-a.spec.ts:394` asserts `getByText('Denied')` — the text must survive. | medium |
| A1 | Page frame | feature-added | single 1100px centred column, no rail (`:30`) | locked 8-group sticky rail + mobile drawer (`_nav.php:7-50, 52-92`; `app.css:2839`), pinned by ADR 0023 shipped item 6 / ADMIN §9.2 | Keep the rail. The design's frame is a per-screen elision; render the design's column *inside* `.admin-pane`. | high |
| A2 | Field errors | feature-added | one `role="alert"` banner per form (`:111, 212, 284`) | per-field `field_error()` / `field_attrs()` — error id + `aria-describedby` + `aria-invalid` + autofocus-on-first-error (`roles.php:59-87`, helpers `src/Support/helpers.php:100,123`), ADR 0023 item 5 | Keep per-field wiring. The design's banner may be **added above** the form as a summary; it must not replace `field_error()`. | medium |
| A3 | Table scroll regions | feature-added | plain `<table>` (`:72, 222`) | `<div class="table-scroll" tabindex="0" role="region" aria-label="…">` (`roles.php:34`, `role_edit.php:125`; `app.css:3217-3236`) | Keep both regions and their labels. | low |
| A4 | Resolver-posture prose | feature-added | design omits it | "Unknown mode values run `shadow` and emit `capabilities.mode_invalid` telemetry." (`roles.php:26-27`) | Keep the sentence inside the new callout — it documents real behavior. | low |
| A5 | Assignment statuses | feature-added | two states only: `active` / `revoked` (`:237-238`, seeds `:385-395`) | four: `revoked` / `scheduled` / `expired` / `active` (`RoleAssignmentService.php:204-206`) | Keep all four. `scheduled` → `--surface-pending`/`--on-pending`; `expired` → the muted/sunken chip; only `active` gets the done pair. `.state-scheduled` / `.state-expired` currently have no colour rule (`app.css:3481-3497`) — author them. | medium |
| A6 | Per-row renew | feature-added | design has only a `Revoke` button (`:241`) | an inline `ends_at` + password + "Renew" form per active row, with row-scoped 422 errors that survive a concurrent revoke (`role_edit.php:146-161`; `AdminRoleController.php:231-256`) | Keep it. Style the two row controls as an inline cluster in the design idiom; preserve `aria-label`s and the `$renewAssignmentId` scoping. | medium |
| A7 | Assign "Reason" | feature-added | absent | `<label>Reason (optional)` maxlength 255 (`role_edit.php:209-211`), passed through to `RoleAssignmentService::grant` | Keep as a 7th cell in the 3-col grid. | low |
| A8 | Board picker + scope names | feature-added | free-text id only (`:268`) | `<datalist id="assignment-board-options">` of every board (`role_edit.php:192-195`) and separate `$boardNames`/`$categoryNames` maps so a category scope_id never resolves through the board map (`role_edit.php:108-118, 132-137`) | Keep both. The comment at `role_edit.php:113-115` records why (review finding V4). | medium |
| A9 | Simulator "Via role" line | feature-added | always renders, falls back to `'none'` (`:342`, `:494`) | rendered only when `$d->roleKey !== null`, and appends " at {scopeType} #{scopeId}" (`role_simulator.php:55-57`) | Keep the conditional and the scope suffix. | low |
| A10 | Simulator labels | feature-added | `actorLabel` is just `@name`; `targetLabel` is ` on board #n` (`:495-496`) | actor label is `{username} (#id, {role}, {status})`; target label is redacted against the *viewer's* read access → "Board #n (restricted)" / "Board #n (missing)" (`PermissionSimulatorService.php:60-61, 69-78`) | Keep. This is a real leak guard; the design never modeled it. | high |
| R1 | Roles filter bar | feature-removed | search `placeholder="Search roles"` (`:58`), segmented `All / Protected anchors / Custom` (`:60-67`), filtered count (`:68`), empty state "No roles match this filter" / "Clear the search, or create a custom role below." (`:99-104`) | `AdminRoleController::index` takes no query params; `rolesView()` renders `RoleService::listWithMeta()` unfiltered (`AdminRoleController.php:44-50, 259-268`) | **Do not build and do not ship dead chrome.** Record as a gap in the new ADR. The static count is separately adoptable (C15). Note the table can never be empty — migration `0050_phase5_capabilities_roles.php:183-187` seeds Guest/User/Moderator/Admin — so the empty state has no reachable trigger either. Extra hazard: a search `<form action="/admin/roles">` would break the pinned selector `form[action="/admin/roles"] button[type="submit"]` at `tests/browser/gate-a.spec.ts:389`. | medium |
| R2 | Assignments on system roles | feature-removed | the Assignments card (`:219`) is **outside** the `recordIsSystem`/`recordIsCustom` gates, and `SEED_ASSIGNMENTS` keys role id 2 — the *system* 'Warden' (`:377, 384-388`) | the whole Assignments + Assign block is wrapped in `if (!$isSystem)` (`role_edit.php:106 … :221`), and `RoleAssignmentService.php:71` refuses: "Only custom roles can be assigned here; built-in authority is managed by the board-moderator and member tools." | **Do not build.** Keep the `!$isSystem` guard. Showing an assignment table on a system anchor would be dead chrome over a path that always 422s. | high |
| G1 | Capability catalogue | feature-changed | a hard-coded 19-key JS array with an explicit `group` field and invented keys (`core.board.create`, `core.user.grant_badge`, `core.moderation.claim`, …) (`:353-373`) | `CapabilityCatalog::all()` filtered to `delegable && !protected` (`AdminRoleController.php:35-41`), grouped in the template by `ucfirst(explode('.', $key)[1])` then `ksort` (`roles.php:8-13`, `role_edit.php:21-26`); ~45 delegable keys; disabled unless `EnforcedCapabilities::has()` (21 keys, `src/Security/EnforcedCapabilities.php:16-26`) | Design wins on presentation (2-col bordered fieldsets, gold-ink legends, high-risk pill, "(not yet enforceable)"). Production wins on data: real catalogue, derived grouping, enforceable clamp. Never hard-code a key list in a template. | medium |
| G2 | Capability description | feature-changed | `c.description` — the internal third-person description (`:133`, e.g. "Open a new board inside a category.") | `$meta['consent'] ?? $meta['description']` — the operator/consent phrasing (`roles.php:75`, `role_edit.php:63`), e.g. "Delete other members' posts in boards this role moderates." | Keep production's consent-first fallback; adopt the design's `<code>key</code> — description` typography (code in `--text-strong`, prose in `--text-muted`). | low |

**Counts:** copy 32 · feature-added 10 · feature-removed 2 · feature-changed 2 · constraint 11 (57 rows).

---

## 3. Fiction strings

Governing rule 3. Every one of these is a **constraint** deviation: adopt the register, never the words.

| # | Design string (verbatim) | Line | Proposed production string |
|---|---|---|---|
| F1 | `Imladris` (wordmark) | `:25` | Do not port. `templates/layout.php` renders the operator's `$brand['name']`. |
| F2 | Eight-point elven star `<svg viewBox="0 0 100 100">` | `:24` | Do not port. Not a RetroBoards mark; layout uses `$brand['logo_path']`. |
| F3 | `Back to the council` | `:27` | Do not port (the shell owns global nav). If ever needed: "Back to the forum". |
| F4 | `Warden` (role name) | `:377` | `Moderator` — production's seeded name (`0050_phase5_capabilities_roles.php:186`). |
| F5 | `The warden’s table.` (role description) | `:377` | `Site moderator (compatibility anchor)` — production's seeded description. |
| F6 | `Elder` | `:378` | `Admin`. |
| F7 | `Member` / `Everyone with a seat.` | `:376` | `User` / `Authenticated member (compatibility anchor)`. |
| F8 | `Tag warden` / `custom.tag_warden` | `:380` | `Tag moderator` / `custom.tag_moderator`. |
| F9 | `core.role.member` / `core.role.moderator` / `core.role.admin` | `:376-378` | `system.user` / `system.moderator` / `system.admin` — the real seeded keys. |
| F10 | `Grant or revoke a mark of esteem.` | `:368` | The key does not exist. Production's nearest is `core.user.manage` → "Administer member records: titles, signatures, and manual badges." In general: "mark of esteem" → "badge". |
| F11 | `erestor`, `glorfindel`, `lindir`, `melian`, `bilbo`, `celebrian`, `elrond`, `galadriel` | `:385-394`, `:472` | Neutral placeholders. All sample rows come from the DB in production, so these simply disappear. |
| F12 | `placeholder="erestor"` on the simulator Actor field | `:301` | A neutral placeholder or none. Production currently has no placeholder (`role_simulator.php:21`) — safest is to leave it empty. |
| F13 | `board — Practice`, `category — The archive`, `board — Archive` | `:386-391` | Real board/category names resolved server-side (`role_edit.php:132-137`). |
| F14 | `Archivist` / `custom.archivist` / `Keeps the archive tidy; no authority over people.` | `:379` | Not fiction — safe as neutral sample copy, but it is still sample data and must not be seeded. |
| F15 | Curly quotes in `Name an actor — a username, an id, or “guest”.` | `:467` | Production uses straight quotes in the equivalent message (`PermissionSimulatorService.php:52`): `use a username, a numeric id, or "guest"`. Keep production's. |

No fiction appears in the screen's *chrome* copy (headings, labels, hints) — only in the seed data
and the topbar. That makes admin-people one of the cleanest screens in the set.

---

## 4. State inventory

| # | Design state | Verbatim design string | Production equivalent | Verdict |
|---|---|---|---|---|
| S1 | Roles list, filter miss | "No roles match this filter" / "Clear the search, or create a custom role below." (`:101-102`) | none | **GAP by design (R1).** No filter exists and the table is never empty (4 seeded system roles). Do not build. |
| S2 | Roles list, count | "5 roles" / "1 role" (`:545`) | none | **GAP.** Adoptable as a static `count($rows)` label (C15). |
| S3 | Create role — name missing | "A role needs a name." (`:415`) | "A role name between 1 and 190 characters is required." (`RoleService.php:219`), rendered by `field_error($errors,'name')` (`roles.php:61`) | Different string. Production's is more precise; the design's is better register. Copy-level microcopy decision, but changing it is a **server-side** string — needs its own test note. |
| S4 | Create role — no capability | "Pick at least one capability — an empty role grants nothing." (`:416`) | "Pick at least one capability." (`RoleService.php:232`), at `roles.php:82` | Production string is a prefix of the design's. Adopting the design's suffix is a safe, honest upgrade. |
| S5 | Create role — password missing | "Confirm your password. Creating a role is a reauthenticated action." (`:417`) | `ReauthGate.php:40` (configurable missing-password message) / `:43` "Your current password is incorrect." / `:61` "Confirm this change with your password or a passkey." — at `roles.php:87` | Production distinguishes *missing* from *wrong*; the design does not. Keep production's distinction; the "reauthenticated action" clause moves to the section intro (C9). |
| S6 | Create role — name taken | none | "A role with this name already exists." (`RoleService.php:261`) | **Design gap.** Production behavior stands. |
| S7 | Create role — non-enforceable pick | none (design just disables the checkbox) | "'{key}' is not yet enforceable; it can be granted once its routes cut over to the resolver." (`RoleService.php:247`) | **Design gap.** Keep — the disabled attribute is not a security boundary. |
| S8 | Record — saved | `role="status"` "Saved — now v{n}." (`:192`) | redirect to **/admin/roles** with flash "Role updated." (`AdminRoleController.php:131`) | Mechanism differs (K6) *and* destination differs: production leaves the record. Recommend redirecting to `/admin/roles/{id}` with a flash that echoes the new version — a behavior change requiring its own test. |
| S9 | Clone — validation | `role="alert"` "Give the clone a name and confirm your password." (`:212`) | scoped field errors (`role_edit.php:96-101`) from "Source role not found." / "The source role has no capabilities to clone." / "The source role has no enforceable capabilities to clone yet." (`RoleService.php:102,107,122`) + ReauthGate | Production is strictly richer; keep it. The design's single line is a prototype simplification. |
| S10 | Clone — success | `role="status"` "Cloned. The new role is in the table, holding only enforceable capabilities." (`:215`) | flash "Role cloned as an editable custom role." + redirect to `/admin/roles` (`AdminRoleController.php:151`) | Adopt the design's more informative wording as the flash text; keep PRG. |
| S11 | Assignments — empty | "No one has been assigned this role yet." (`:249`) | **exact match** at `role_edit.php:123` | Already aligned. |
| S12 | Assignment status | "Active" / "Revoked" (`:237-238`) | `active` / `scheduled` / `expired` / `revoked` (`RoleAssignmentService.php:204-206`) | **Design gap (A5).** Ship four chips. |
| S13 | Assign — validation | `role="alert"` "A username and your password are both required." (`:284`) | 11 distinct messages: "Only custom roles can be assigned here…" / "No such member." / "That member already holds this role at this scope…" / "Scope must be site, category, or board." / "Pick the target {type}." / "No such {type}." / "Use the format YYYY-MM-DD HH:MM (UTC), optionally with seconds." / "The expiry must be in the future." / "The expiry must be after the start." / "You do not hold every capability in this role at that scope." / "'{key}' is not yet enforceable…" (`RoleAssignmentService.php:71,77,94,216,222,226,261,267,270,298,313`) | Production is far richer, incl. the grantor-ceiling escalation guard. Keep every message per-field. |
| S14 | Renew — validation | none | "A renewal needs a new expiry." / "Revoked assignments cannot be renewed; create a new grant." / "The expiry must be after the assignment start." / "This assignment is not active." (`RoleAssignmentService.php:159,165,174,128`), row-scoped at `role_edit.php:154-161` | **Design gap (A6).** Keep. |
| S15 | Simulator — error card | h2 "The simulator could not answer"; texts "Name an actor — a username, an id, or “guest”." (`:467`) and "Pick a capability to test." (`:468`) | no such heading; errors are "No member matches \"{ref}\"; use a username, a numeric id, or \"guest\"." (`PermissionSimulatorService.php:52`) and "Time must be UTC \"YYYY-MM-DD HH:MM\"." (`:64`), rendered inside the Result card (`role_simulator.php:44-45`) | Adopt the separate card + heading (C31). **Real gap:** production has **no** server-side "pick a capability" path — `<select required>` (`role_simulator.php:24`) blocks it client-side, and with HTML validation bypassed `AdminRoleController.php:81` silently renders no result. Add a server-side message. |
| S16 | Simulator — idle | no Result card until `sim` is set (`:331`) | no Result card while `$result === null` (`role_simulator.php:41`) | **Match.** Neither offers an idle hint; acceptable. |
| S17 | Simulator — verdict | "Allowed" / "Denied" chips (`:335-336`) | `<strong>Allowed</strong>` / `<strong>Denied</strong>` (`role_simulator.php:48`) | Text matches; skin differs (C32). Pinned by `tests/browser/gate-a.spec.ts:394`. |
| S18 | Capability not enforceable | "(not yet enforceable)" (`:135`) | **exact match** (`roles.php:77`, `role_edit.php:65`) | Already aligned. |
| S19 | High risk | "High risk" (`:134`) | "high risk" (`roles.php:76`, `role_edit.php:64`) | Casing only (C13). |
| S20 | Kind labels | "Protected anchor" / "Custom" (`:88-89`) | **exact words** (`roles.php:42`) | Aligned; chip skin only (C5). |
| S21 | Row action labels | "View / clone" / "Edit" (`:550`) | **exact match** (`roles.php:46`) | Already aligned. |
| S22 | "{n} capabilities selected" | `selectedCapLabel` (`:555`) | none | **GAP — PE decoration only (K7).** |
| S23 | Loading / skeleton | **none in this screen** | n/a | Nothing to reject. Unlike AdminOverview, admin-people models no loading state. |
| S24 | Flag-off | **none** | 404 from `AdminRoleController::gate()` + the disabled nav span "Disabled until the feature flag is enabled" (`_nav.php:5, 80-84`) | **Design gap (K10).** The design has no flag concept at all. |

---

## 5. Slice proposal

Every slice ends with: `php bin/build-imladris-assets.php --print-application-digest` →
paste into `config/imladris-runtime-baseline.json` → `application_surface.sha256`, then
`composer check:imladris` + `composer verify:imladris`. Every slice ends with a CSP scan
(`rg -n "<script|<style| on[a-z]+=" templates/admin -S` must show nothing new) and a
`javaScriptEnabled: false` pass over the touched routes.

### Slice 1 — People chrome parity (eyebrow, tabs, callout, page frame)
**Touches** `templates/admin/roles.php`, `role_edit.php`, `role_simulator.php`, a new shared
partial `templates/admin/_roles_tabs.php`, `public/assets/app.css` (`.callout`, `.callout-info`,
`.text-tabs`-style local strip, `.eyebrow` size/colour reconciliation).
**Delivers** C1, C2, C3, A4, K2, K4, K11.
**Tests** — Integration: all three routes return 200 with the eyebrow "Operator desk · People",
both tab links present with correct `href`s and exactly one `aria-current="page"`; `/admin/roles`
callout still contains the telemetry sentence and the simulator anchor. Browser: `npm run a11y`
on `/admin/roles` and `/admin/roles/simulator` (already covered at `a11y.spec.ts:178, 208`);
new no-JS test navigating `/admin/roles` → `/admin/roles/simulator` via the tab link.

### Slice 2 — Roles table anatomy
**Touches** `templates/admin/roles.php` (table only), `public/assets/app.css`.
**Delivers** C4, C5, C6, C7, C8, C15, A3, K3, K9.
**Watch** `tests/browser/gate-a.spec.ts:382` asserts `getByText('system.admin')` and `:389` selects
`form[action="/admin/roles"] button[type="submit"]` — the key text and the single-form assumption
must survive.
**Tests** — Integration: kind chip markup, right-aligned numeric cells, static "N roles" label
reflecting `count($rows)`, `.table-scroll` region label unchanged. Browser: gate-a role-creation
flow re-run; axe on `/admin/roles`.

### Slice 3 — Create-a-custom-role form
**Touches** `templates/admin/roles.php` (form), `public/assets/app.css`, optionally
`public/assets/app.js` (the K7 counter).
**Delivers** C9, C10, C11, C12, C13, C14, A2, G1, G2, K5, K7.
**Tests** — Integration: a 422 from `POST /admin/roles` re-renders with `->errors` + `->old`,
`field_attrs()` still emits `aria-invalid`/`aria-describedby`, and previously-checked
`capabilities[]` stay checked (`roles.php:68`); disabled state still derives from
`EnforcedCapabilities::has()`; the grouped legends still come from the real catalogue, not a
literal list. Browser: create a role no-JS; with JS on, the counter increments and is absent with
JS off.

### Slice 4 — Role record header + capabilities/definition cards
**Touches** `templates/admin/role_edit.php` (lines 28–87), `public/assets/app.css`.
**Delivers** C16, C17, C18, C19, plus the definition form's share of C11/C12/C13/G1/G2.
**Tests** — Integration: back link `href="/admin/roles"` present on both system and custom records;
`h1` is still the role name; "(decision #18)" is gone; system record renders "Capabilities held"
and no edit form; the `$defErrorContext` scoping still lands definition errors only on the
definition form (`role_edit.php:13-17`). Browser: axe on `/admin/roles/{id}` (existing
`tests/browser/role-assignments.spec.ts`).

### Slice 5 — Clone, Assignments and Assign
**Touches** `templates/admin/role_edit.php` (lines 89–221), `public/assets/app.css`
(`.state-scheduled`, `.state-expired`, filled status pills, inline row cluster).
**Delivers** C20, C21, C22, C23, C24, C25, C26, C27, C28, A5, A6, A7, A8, K5, K6; records R2.
**Tests** — Integration (`tests/Integration/Core/AppRoleAssignmentTest.php`): all four statuses
render distinct chips; renew 422 stays row-scoped and survives a concurrent revoke
(`role_edit.php:154-161`); assign 422 preserves every `$old['assignment']` field including the
new scope-select order; category scope ids still resolve through `$categoryNames`, not
`$boardNames`; the whole block still absent for system roles. Browser: no-JS assign + revoke +
renew; axe on `/admin/roles/{id}`.

### Slice 6 — Permission simulator
**Touches** `templates/admin/role_simulator.php`, `public/assets/app.css`,
`src/Controller/AdminRoleController.php` (S15 only).
**Delivers** C29, C30, C31, C32, A9, A10, K8.
**Behavior change** (own test): a submitted simulation with an empty `capability` must return the
message "Pick a capability to test." instead of silently rendering nothing
(`AdminRoleController.php:81`).
**Tests** — Integration: error renders in its own card with the h2
"The simulator could not answer"; a decision renders the Allowed/Denied pill and, when
`roleKey === null`, omits the "Via role" line; the redacted target label still appears for a board
the admin cannot read. Browser: `gate-a.spec.ts:393-395` still finds "Denied"; axe on
`/admin/roles/simulator`; reduced-motion honoured.

### Slice 7 — Decisions & deferrals ADR (no code)
**Touches** a new `docs/adr/0024-*.md` (next free number after 0023), `PHASE_5_STATUS.md`.
**Records** R1 (roles search / kind filter / filtered empty state — not built, not chromed;
plus the `form[action="/admin/roles"]` selector hazard), R2 (system-role assignments — design
shows, production forbids), S8 (record-save redirect target), S3/S4/S5 (server-side validation
microcopy changes), and the confirmation that Users / Invitations / Badge rules are out of this
screen's scope and belong to `admin-members` / `admin-features`.
**Tests** none — but per PRODUCT_DESIGN §13 no slice above may be called complete without the ADR entry
for whatever it deferred.

**Ordering.** 1 → 2 → 3 → 4 → 5 → 6 are independently shippable and independently testable in that
order; 2/3 and 4/5 can run in parallel if the `app.css` additions are namespaced per slice. Slice 7
should be opened first (as a stub) and closed last.
