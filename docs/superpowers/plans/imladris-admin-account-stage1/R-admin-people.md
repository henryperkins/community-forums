# R — correction addendum for `D-admin-people.md`

**Scope.** `D-admin-people.md` was written against the pre-refresh revision of
`docs/design-system/imladris/templates/admin-people/AdminPeople.dc.html`. The mirror was refreshed
mid-pass. This addendum re-anchors every design citation, strikes the rows the refresh invalidated,
folds in every finding from `V-admin-people.md`, and restates the classification counts. **Where this
file and `D-admin-people.md` disagree, this file wins.** The production half of D survives unchanged
except where noted in §E.

## 0. True metrics of the current design file

| | Value |
|---|---|
| File | `docs/design-system/imladris/templates/admin-people/AdminPeople.dc.html` |
| Total lines | **616** (616 newlines, file ends with `\n`) |
| Markup (`<x-dc>` … `</x-dc>`) | **`:9` – `:339`** |
| Rendered screen root | `:20` – `:338` |
| `<script type="text/x-dc">` | **`:340` – `:614`** (`</body>` `:615`, `</html>` `:616`) |

D's "628 lines; markup ends at line 351, script 352–626" describes the pre-refresh file and is
wholly superseded. V's "617 lines … script `:340-614`" is right about the script and **off by one**
on the total (617 is the phantom line produced by splitting on the trailing newline). Use **616**.

`AdminNav.jsx` is **76** lines (V said 77 — same off-by-one).

---

## A. Corrected top-to-bottom section order (verbatim headings, true anchors)

Everything above the content column is now **shared chrome, not page content**.

| # | Section | Line | Verbatim text |
|---|---|---|---|
| **X0** | `<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="people" hint-size="100%,101px">` | **`:22`** | *no page-authored text* — the identity row and area tier are `components/admin/AdminNav.jsx` |
| **X1** | Content column `max-width: 1100px; margin: 0 auto; padding: 22px 28px 110px` | **`:24`** | — |
| **D1** | `<h1>` — **first child of the column**, `margin: 0`, `font-size: 2.1rem` | **`:26`** | **"Roles & capabilities"** |
| **D2** | `<nav aria-label="People sections">`, `margin: 16px 0 0` | **`:28-33`** | **"Roles"** · **"Permission simulator"** |
| **D3** | *(view=roles)* info callout | **`:38-41`** | "Resolver posture: **shadow** (`CAPABILITIES_MODE`). … Try changes safely in the **permission simulator**." |
| **D4** | Filter bar | **`:43-57`** | `placeholder="Search roles"` (`:46`); segmented **"All"/"Protected anchors"/"Custom"** (`:48-55`); count `{{ roleResultLabel }}` (`:56`) |
| **D5** | Roles table card *(no heading)* | **`:59-86`** | thead `:61-69` — **Name · Key · Kind · Version · Capabilities · Active assignments · (sr-only "Actions" `:68`)**; rows `:71-84` |
| **D5e** | Roles empty state | **`:87-92`** | h3 **"No roles match this filter"** (`:89`) / p "Clear the search, or create a custom role below." (`:90`) |
| **D6** | h2 **"Create a custom role"** | **`:95-139`** | h2 `:96`; intro `:97`; `role="alert"` `:98-100`; name/description grid `:102-111`; capability fieldsets `:112-129`; footer `:130-137` |
| **D7** | *(view=record)* back button | **`:146`** | **"All roles"** |
| **D8** | Record head | **`:148-151`** | h2 `{{ recordName }}` (`:149`, 1.9rem) + mono `v{{ recordVersion }}` (`:150`) |
| **D9** | Record kind note | **`:152`** | "`{{ recordKey }}` — {{ recordKindNote }} Active assignments affected by changes: **{{ recordImpact }}**." |
| **D10** | *(system)* h3 **"Capabilities held"** | **`:154-163`** | h3 `:156`; 2-col `<ul>` `:157-161` |
| **D11** | *(custom)* h3 **"Edit definition"** | **`:165-183`** | h3 `:167`; name/description grid `:168-177`; **"Save (bumps version)"** `:179`; `role="status"` **"Saved — now v{n}."** `:180` |
| **D12** | h3 **"Clone into a new custom role"** | **`:185-205`** | h3 `:186`; intro `:187`; form `:188-198`; alert `:199-201`; status `:202-204` |
| **D13** | h3 **"Assignments"** | **`:207-238`** | h3 `:208`; table `:210-234`; empty "No one has been assigned this role yet." `:237` |
| **D14** | h4 **"Assign this role"** *(same card)* | **`:240-274`** | h4 `:240`; 3-col grid form `:241-274` |
| **D15** | *(view=simulator)* intro | **`:282`** | "Runs `can(actor, capability, target, time)` on the real resolver. …" |
| **D16** | h2 **"Simulate"** | **`:284-310`** | h2 `:285`; 2-col grid form `:286-309` |
| **D17** | h2 **"The simulator could not answer"** | **`:312-317`** | h2 `:314`; rust left-rule card |
| **D18** | h2 **"Result"** | **`:319-333`** | h2 `:321`; verdict row `:322-326`; `<ul>` `:327-331` |

**Deleted outright by the refresh — these are no longer sections of this screen:**
`D0` sticky 58px topbar (old `:22-28`), `D1` eyebrow "Operator desk · People" (old `:34`),
`D1` "Admin mode" pill (old `:37`), and the two-column head wrapper (old `:32-38`).

**There is no eyebrow anywhere on this screen.** The `<h1>` at `:26` is the immediate first child of
the content column.

### A.1 Anchor-offset rule (V's blanket "−12" is wrong at the top of the file)

The refresh deleted 16 lines and inserted 4, all between old `:21` and old `:39`. So:

| Old range | Correction |
|---|---|
| old `:1` – `:20` | **unchanged** (helmet, `@keyframes apRise` `:16`, `@template description` `:10`) |
| old `:22-28` (topbar) | **deleted** → new `:22` is the `x-import` |
| old `:30` (content column) | **new `:24`** — *−6, not −12* |
| old `:32-38` (head block) | eyebrow / pill / wrapper **deleted**; only the h1 survives → **new `:26`** — *−9, not −12* |
| old `:40` – `:628` | **−12** |

D's `:16` (K8 keyframes) and `:10` (`@template description`) must **not** be shifted. D's `:30`
(A1, content column) shifts −6. Everything else in D shifts −12.

### A.2 Re-anchored citation index (every design `:NNN` in D)

`§0`: `:40-45`→`:28-33` · `:41-42`→`:29-30` · `:43-44`→`:31-32` · `:533-535`→`:521-523` · `:10`→**`:10`**

`§2 constraint`: K1 `:16`→**`:16`** · K2 `:533-535`→`:521-523`, `:41-44`→`:29-32` · K3 `:94`→`:82` ·
K4 `:52`→`:40` · K5 forms → create `:101`, clone `:188`, assign `:241` (save button `:179`) ·
K6 `:192`→`:180`, `:215`→`:203`, `:111`→`:99`, `:212`→`:200`, `:284`→`:272` ·
K7 `:148`→`:136`, `:555`→`:543` · K8 `:332`→`:320`, keyframes `:16`→**`:16`** · K11 → **struck, see §C.2**

`§2 copy`: C1 → **struck, see §C.1** · C2 `:40-45`→`:28-33` · C3 `:50-53`→`:38-41` ·
C4 `:71`→`:59` · C5 `:88-89`→`:76-77` · C6 `:77-79`→`:65-67`, `:91-93`→`:79-81` · C7 `:86`→`:74` ·
C8 `:94`→`:82` · C9 `:109`→`:97` · C10 `:127`→`:115` · C11 `:124-141`→`:112-129` · C12 `:114`→`:102` ·
C13 `:134`→`:122` · C14 `:142-149`→`:130-137` · C15 `:68`→`:56`, `:545`→`:533` · C16 `:158`→`:146` ·
C17 `:160-163`→`:148-151` · C18 `:569`→`:557` · C19 `:169-173`→`:157-161` · C20 `:199`→`:187` ·
C21 `:200-210`→`:188-198` · C22 `:219-286`→`:207-274` · C23 `:237-238`→`:225-226` · C24 `:235`→`:223` ·
C25 `:233`→`:221` · C26 `:253`→`:241`, `:282`→`:270` · C27 `:261-263`→`:249-251` ·
C28 `:267-276`→`:255-264` · C29 `:298`→`:286`, `:320`→`:308` · C30 `:317-318`→`:305-306` ·
C31 `:325-328`→`:313-316` · C32 `:335-336`→`:323-324`

`§2 feature-added`: A1 `:30`→**`:24` (−6)**, and see §C.3 · A2 `:111,212,284`→`:99,200,272` ·
A3 `:72,222`→`:60,210` · A5 `:237-238`→`:225-226`, seeds `:385-395`→`:373-383` · A6 `:241`→`:229` ·
A8 `:268`→`:256` · A9 `:342`→`:330`, `:494`→`:482` · A10 `:495-496`→`:483-484`

`§2 feature-removed / changed`: R1 `:58`→`:46`, `:60-67`→`:48-55`, `:68`→`:56`, `:99-104`→`:87-92` ·
R2 `:219`→`:207`, `:377`→`:365`, `:384-388`→`:372-376` · G1 `:353-373`→`:341-361` · G2 `:133`→`:121`

`§3 fiction`: F1 `:25`→**gone** · F2 `:24`→**gone** · F3 `:27`→**gone** · F4/F5 `:377`→`:365` ·
F6 `:378`→`:366` · F7 `:376`→`:364` · F8 `:380`→`:368` · F9 `:376-378`→`:364-366` · F10 `:368`→`:356` ·
F11 `:385-394`→`:373-382`, `:472`→`:460` (**add** `:396` `simActor: 'erestor'` default) ·
F12 `:301`→`:289` · F13 `:386-391`→`:374,375,379` · F14 `:379`→`:367` · F15 `:467`→`:455`

`§4 states`: S1 `:101-102`→`:89-90` · S2 `:545`→`:533` · S3 `:415`→`:403` · S4 `:416`→`:404` ·
S5 `:417`→`:405` · S8 `:192`→`:180` · S9 `:212`→`:200` · S10 `:215`→`:203` · S11 `:249`→`:237` ·
S12 `:237-238`→`:225-226` · S13 `:284`→`:272` · S15 `:467`→`:455`, `:468`→`:456` · S16 `:331`→`:319` ·
S17 `:335-336`→`:323-324` · S18 `:135`→`:123` · S19 `:134`→`:122` · S20 `:88-89`→`:76-77` ·
S21 `:550`→`:538` · S22 `:555`→`:543`

Also re-anchored for `§0`/`§1.4` cross-reference: `capGroups` markup `:113`, script `:542`;
`recordCaps` markup `:158`, script `:558`.

### A.3 New measurements the refresh introduced (supersede any pixel D quoted)

| Property | Old | **Current** |
|---|---|---|
| Column padding | `26px 28px 110px` | **`22px 28px 110px`** (`:24`) |
| `h1` size / margin | `2.4rem` / `7px 0 0` | **`2.1rem` / `0`** (`:26`) |
| Sub-nav top margin | `22px` | **`16px`** (`:28`) |
| Chrome above the column | per-screen 58px topbar + head block | **`AdminNav`, `hint-size="100%,101px"`** (`:22`) |

---

## B. Section-order deltas — corrected

D's `§1` "Order deltas" list is amended:

1. *(unchanged)* Design has no `<h2>Roles</h2>` over the table; production has one (`roles.php:33`).
2. *(unchanged)* Design merges Assignments + Assign into one card (h3 `:208` + h4 `:240`); production
   splits them into two `h2` cards (`role_edit.php:120`, `:171`).
3. **REPLACED.** D said "Design's capability fieldsets are inside 'Edit definition' … no delta."
   **False.** `capGroups` occurs exactly once, at `:113`, inside *Create a custom role* on the
   **list** view. The record's Edit-definition card (`:165-183`) has only name, description, a save
   button and the status line — **no fieldsets, no checkboxes** — and `recordCaps` (`:158`) renders
   only under `recordIsSystem` (`:154`). The design gives a custom role's record no way to see or
   change its capabilities. Production does both (`role_edit.php:57-69`, `capabilities[]` `:62`,
   `$checked = (array) ($old['capabilities'] ?? $current_keys)` `:7`). → new row **M2**,
   classification **feature-added**.
4. *(unchanged)* Capabilities-held-before-Edit-definition is a source-order artefact of mutually
   exclusive branches — no rendered delta.
5. **REPLACED.** D said "Design has no admin rail." **False as of the refresh.** The design mounts
   `AdminNav` at `:22`, which renders a 10-area horizontal tier (`AdminNav.jsx:8-19`, `:60-74`).
   The delta is *tier vs rail*, not *nothing vs rail*. → rows **N1**, **N2**.
6. *(unchanged)* Design inserts a filter bar (`:43-57`); production has none → R1.
7. *(unchanged)* Design splits the simulator error (`:312-317`) above Result (`:319-333`);
   production nests it inside the Result card (`role_simulator.php:44-45`).
8. **NEW.** Design has **no page-level eyebrow and no page-level "Admin mode" pill**; production
   carries `<span class="pill pill-admin">Admin mode</span>` inside `.admin-head` on all three
   templates (`roles.php:18`, `role_edit.php:31`, `role_simulator.php:9`). → row **N2**.

---

## C. Rows whose ACTION is INVERTED by the chrome change

### C.1 `C1` — **STRUCK. Do not add an eyebrow.**

D said: *"Add `<span class="eyebrow">Operator desk · People</span>` inside `.admin-head` on all
three [templates]."*

**Inverted.** Verified by literal grep: `Operator desk` returns **zero** hits in the current
`AdminPeople.dc.html`. The removal is deliberate and documented at
`docs/design-system/imladris/components/admin/admin.card.html:43`:

> "…this chrome is 10px *shorter*: the redundant &ldquo;Operator desk&nbsp;·&nbsp;Area&rdquo; kicker
> is gone, the mode pill moved into the identity row, and the heading drops from 2.4rem to 2.1rem."

Verified on the production side: `roles.php:16-19`, `role_edit.php:29-32` and `role_simulator.php:7-10`
contain **no** eyebrow. So after the refresh **there is no difference here at all** — C1 is not
"reversed", it is a non-row. Delete it from the ledger and from Slice 1's deliverables and tests.

*Cross-screen consequence (out of this screen's scope, record it in the ADR):* the design's
system-wide kicker removal now makes production's surviving `<span class="eyebrow">Operator desk</span>`
at `templates/admin/dashboard.php:6`, `settings.php:14` and `branding.php:11` the divergence. That
adjudication belongs to `admin-overview` / `admin-settings` / `admin-appearance`, **not here** — but
under no circumstance may this screen's work propagate an eyebrow onto the roles templates.

### C.2 `K11` — **INVERTED classification. `constraint` → `feature-changed`; retitled `N2`.**

D said: *"Topbar … Do not port. The design's topbar is a prototype frame, not a component."*

**Inverted.** The topbar was never a prototype frame and is now unmistakably a shared component:
`AdminPeople.dc.html:22` mounts `ImladrisDesignSystem_c3e027.AdminNav`, and every `admin-*` template
mounts the same thing. Anatomy (`components/admin/AdminNav.jsx`, 76 lines):

* `.admin-bar-id` (`:52-59`): `Mark` + `.admin-bar-wordmark` **"Imladris"** (`:53`) +
  `.admin-bar-exit` back link, `backLabel = 'Back to the council'` (`:44`) +
  `.admin-bar-mode` pill, `modeLabel = 'Admin mode'` (`:45`, suppressible via `modeLabel={null}`).
* `<nav className="admin-tier" aria-label="Admin areas">` (`:60-74`) — see N1.

**Corrected row N2 — feature-changed.** Same concept (a persistent admin identity strip that also
says "you are in admin mode"), different mechanics: design puts the wordmark, the exit link and the
mode pill in one shared identity row above every admin page; production puts `Admin mode` in each
page's own `.admin-head` next to the `<h1>` and lets `templates/layout.php` own the brand.
**Action:** production wins on placement mechanics only where the shell already owns it — keep
`templates/layout.php` as the brand owner and keep `$brand['name']`/`$brand['logo_path']`; the
`Admin mode` pill's *relocation* is a genuine open question for the console shell and must be
adjudicated in the ADR, not decided inside a roles-page slice. **Do not port the wordmark, the star
or the back link** — those three remain `constraint`, already carried as F1/F2/F3, and they now live
in `AdminNav.jsx:53`/`:44`, **not** in `AdminPeople.dc.html`.

### C.3 `A1` — **INVERTED premise. `feature-added` → `feature-changed`; retitled `N1`.**

D said: *"Page frame — feature-added: design [is a] single 1100px centred column, no rail … the
design's frame is a per-screen elision."*

**Inverted.** The design explicitly models admin-area navigation. `AdminNav.jsx:8-19` declares
`ADMIN_AREAS` in console order:

> Overview · Content · People · Members · Appearance · Notifications · Integrations · Packages ·
> Features · Settings

with `aria-current="page"` on the active pill (`:66`, `:70`). "Production has functionality the
design never modeled" is false.

**Corrected row N1 — feature-changed.** Same concept (navigate the admin areas), different
mechanics: 10-item horizontal pill tier vs production's 8-group vertical rail
(`templates/admin/_nav.php:7-50`). **Action is unchanged — keep the rail — but on binding grounds
D did not cite:**

* ADR 0023 shipped item 6 (`docs/adr/0023-admin-console-audit-round-2.md:17`) locks the grouped IA.
* `tests/Integration/Core/AppAdminDashboardRemediationTest.php:94-119` pins the eight group titles
  **in order** plus all 26 destinations.
* `tests/browser/admin-dashboard.spec.ts:60-72` pins the same via `expectGroupedDirectory`.

The design's IA also splits People differently (roles → `admin-people`; users + invitations →
`admin-members`; badges → `admin-features`, `README.md:114`). That is a real, unrecorded
design-vs-production IA conflict and must land in the ADR as an **adjudication**, not a shrug.
Render the design's 1100px column (`:24`) *inside* `.admin-pane`.

### C.4 Section-order rows `D0` and the `D1` sub-rows — **no longer page sections**

`D0` (sticky topbar), the `D1` eyebrow and the `D1` "Admin mode" pill are chrome, not page content,
and are absent from the file. They must not appear in any per-screen order comparison, and nothing in
this screen's slices may add a topbar, wordmark, back link, eyebrow or mode pill to
`roles.php` / `role_edit.php` / `role_simulator.php`.

---

## D. Rows whose quoted design string is FABRICATED / no longer present

Each checked with a literal grep against the current file.

| Row | Quoted design string | D's line | Grep result | Disposition |
|---|---|---|---|---|
| D0 | 8-point star SVG + "Imladris" + "Back to the council" | `:22-28` | `Back to the council` → **0 hits**; `wordmark` → **0 hits**; `viewBox="0 0 100 100"` → **0 hits** | **Strike.** Section deleted by the refresh. |
| D1 | `Operator desk · People` | `:34` | `Operator desk` → **0 hits** | **Strike.** |
| D1 | `Admin mode` | `:37` | `Admin mode` → **0 hits** | **Strike.** |
| C1 | `Operator desk · People` | `:34` | **0 hits** | **Strike the row** (§C.1). |
| K11 | topbar anatomy | `:22-28` | **0 hits** | **Rewrite as N2** (§C.2); the strings now live in `AdminNav.jsx`. |
| A1 | "no rail" | `:30` | `AdminNav` mount present at `:22` | **Rewrite as N1** (§C.3); the `1100px` column itself survives at **`:24`**. |
| F1 | `Imladris` (wordmark) | `:25` | only hit is the namespace token inside `component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav"` (`:22`) — **not a rendered string** | Re-cite to `AdminNav.jsx:53`. Verdict unchanged: do not port. |
| F2 | eight-point elven star `<svg viewBox="0 0 100 100">` | `:24` | **0 hits** | Re-cite to `AdminNav.jsx:27` (`function Mark()`, which resolves the system's `EightPointStar` off the namespace at `:28`). Verdict unchanged. |
| F3 | `Back to the council` | `:27` | **0 hits** | Re-cite to `AdminNav.jsx:44`. Verdict unchanged. |
| §1 delta 5 | "Design has no admin rail" | — | `ADMIN_AREAS` at `AdminNav.jsx:8-19` | **Strike the premise** (§B.5). |
| §1 delta 3 | "capability fieldsets … inside Edit definition … no delta" | — | `capGroups` appears **once**, at `:113` (list view only) | **Strike; replace with M2** (§B.3). |

**Everything else D quoted is still literally present.** Spot-verified in the current file:
`No roles match this filter` `:89` · `Clear the search, or create a custom role below.` `:90` ·
`Only delegable capabilities are offered — protected authority is never on this list. Creating a role
is a reauthenticated action.` `:97` · `High risk` `:122` · `(not yet enforceable)` `:123` ·
`All roles` `:146` · `Capabilities held` `:156` · `Edit definition` `:167` ·
`Save (bumps version)` `:179` · `Saved — now v{{ recordVersion }}.` `:180` ·
`Clone into a new custom role` `:186` · `Cloning copies only currently-enforceable capabilities, so
the copy is never wider than the anchor.` `:187` ·
`Give the clone a name and confirm your password.` `:200` ·
`Cloned. The new role is in the table, holding only enforceable capabilities.` `:203` ·
`Assignments` `:208` · `No one has been assigned this role yet.` `:237` · `Assign this role` `:240` ·
`Site-wide`/`Category`/`Board` `:249-251` · `(blank = site-wide)` `:255` · `(blank starts now)` `:259` ·
`(blank never expires)` `:263` · placeholders `2026-08-10 09:00` `:260`, `2026-11-01 00:00` `:264` ·
`A username and your password are both required.` `:272` · `Simulate` `:285` ·
`Actor (username, id, or guest)` `:288` · `placeholder="erestor"` `:289` · `— pick —` `:294` ·
`At (optional, UTC)` `:305`, placeholder `2026-08-15 12:00` `:306` ·
`The simulator could not answer` `:314` · `Allowed` `:323` / `Denied` `:324` ·
`Decisive rule:`/`Reason:`/`Via role:` `:328-330` · `A role needs a name.` `:403` ·
`Pick at least one capability — an empty role grants nothing.` `:404` ·
`Confirm your password. Creating a role is a reauthenticated action.` `:405` ·
`Name an actor — a username, an id, or “guest”.` `:455` · `Pick a capability to test.` `:456` ·
`roleResultLabel` `:533` · `View / clone` / `Edit` `:538` · `capabilities selected` `:543` ·
`Protected system anchor, read-only.` `:557`.

---

## E. V-report findings folded in (this addendum is now the single corrected source)

### E.1 Refutations — all adopted

| V § | Finding | Disposition here |
|---|---|---|
| 1.1 | C1 eyebrow refuted | §C.1 — struck |
| 1.2 | K11 "prototype frame" refuted | §C.2 — rewritten as **N2**, feature-changed |
| 1.3 | A1 "no rail" refuted | §C.3 — rewritten as **N1**, feature-changed |
| 1.4 | Order-delta 3 "no delta" refuted | §B.3 — replaced by **M2**, feature-added |

V's blanket "subtract 12 from every design citation" is itself corrected by **§A.1**: `:10` and `:16`
do not move, `:30` moves −6, the old head block does not map at all.

### E.2 Missed differences — added as rows

| # | Section | Design (current line) | Production | Classification |
|---|---|---|---|---|
| **N1** | Admin-area navigation | 10-pill horizontal tier, `AdminNav.jsx:8-19, 60-74`; mounted `:22` | 8-group vertical rail `_nav.php:7-50`, pinned by ADR 0023 item 6 + `AppAdminDashboardRemediationTest.php:94-119` + `admin-dashboard.spec.ts:60-72` | **feature-changed** — keep the rail; adjudicate the IA split in the ADR |
| **N2** | Admin identity row / `Admin mode` placement | `.admin-bar-id` with wordmark + exit + mode pill, `AdminNav.jsx:52-59`; **no page-level pill** in `AdminPeople.dc.html` | `<span class="pill pill-admin">Admin mode</span>` in each page's `.admin-head` (`roles.php:18`, `role_edit.php:31`, `role_simulator.php:9`); brand owned by `templates/layout.php` | **feature-changed** — pill relocation is a console-shell question for the ADR, not a roles-page slice; wordmark/star/back link stay `constraint` (F1/F2/F3) |
| **M2** | Capability editing on a **custom** role's record | **absent** — `:165-183` has no fieldsets; `recordCaps` (`:158`) renders only under `recordIsSystem` (`:154`) | `role_edit.php:57-69` full grouped fieldsets + `name="capabilities[]"` (`:62`) with a live 422 round-trip (`:7`) | **feature-added** — keep it; any styling is an *extrapolation* of list-page anatomy onto a card the design never modeled, and must be recorded as such |
| **M3** | Assign submit label | **"Assign"** (`:271`) | **"Assign role"** (`role_edit.php:218`) | copy |
| **M4** | Assignments table actions header | **empty** `<th scope="col">` (`:216`) — note the *roles* table does carry an sr-only "Actions" (`:68`), matching production `roles.php:36` | `<span class="sr-only">Actions</span>` (`role_edit.php:127`) | **feature-added** — **copying the design here would revert ADR 0023 shipped item 5**, which enumerates empty `<th>`s as a fixed a11y pocket. Keep production's. |
| **M5** | Em dash vs ASCII hyphen | `—` at `:121`, `:152`, `:294` (`— pick —`) | `-` at `roles.php:75`, `role_edit.php:37`, `:63`, `role_simulator.php:25` (`- pick -`), `:49` | copy — **not** an encoding constraint; ≥10 files under `templates/admin/` already use real em dashes |
| **M6** | Simulator result connector | verdict pill is a flex sibling, no connector (`:322-326`) | `<strong>Denied</strong> - <code>…` (`role_simulator.php:49`) | copy — the literal ` - ` must be **deleted explicitly** when C32's pill lands |
| **M7** | Actor field label | plain `Actor (username, id, or guest)` (`:288`) | `guest` wrapped in `<code>` (`role_simulator.php:20`) | copy (minor) |

### E.3 Missed binding decision — ADR 0023 **Deferral #4** (must be closed or restated)

> "**Deep-admin field-error wiring residue.** `registries.php` and **`role_edit.php`** render field
> errors legibly but are not yet wired to their inputs via the new helpers (their duplicated error
> keys need per-form scoping first…)."

Verified: `role_edit.php` contains **0** `field_error()`/`field_attrs()` calls; `roles.php` contains
**7**. Nothing in D or here *reverts* the deferral (the `$defErrorContext`/`$cloneErrorContext`/
`$assignErrorContext`/`$renewAssignmentId` scoping at `role_edit.php:13-17` is explicitly preserved),
but its stated precondition — per-form scoping — is exactly what Slices 4 and 5 touch, so the new
ADR must either close deferral #4 or restate it. Attach this to row **A2**.

### E.4 Missed pinned selectors — attach to R1 / Slice 2

* `form[action="/admin/roles"] button[type="submit"]` is pinned at **three** places, not one:
  `tests/browser/gate-a.spec.ts:389`, `tests/browser/role-assignments.spec.ts:104`, `:204`.
  A roles search `<form action="/admin/roles">` would break all three — an extra reason R1 stands.
* `tests/Integration/Admin/AppAdminNavIaTest.php:74` asserts `href="/admin/roles/simulator"` appears
  in `/admin/roles`'s body. The prose link at `roles.php:30` is **not** a deficiency — it is the
  shipped ADR 0023 item 6 orphaned-console remediation. C2's tab strip must **add** a route, never
  remove that inbound link.
* `AppAdminFeaturesTest.php:104` / `admin-features.spec.ts:104` pin
  `<a href="/admin/roles">Roles &amp; resolver posture</a>` inbound from `/admin/features`.

### E.5 Citation errors in D's production half — corrected (all re-verified here)

| D cites | Correct |
|---|---|
| `PermissionSimulatorService.php:52` | **`:49`** — `'No member matches "…"; use a username, a numeric id, or "guest".'` |
| `PermissionSimulatorService.php:60-61` | **`:54-55`** |
| `PermissionSimulatorService.php:64` | **`:63`** — `'Time must be UTC "YYYY-MM-DD HH:MM".'` |
| `PermissionSimulatorService.php:69-78` | block `:68-80`; `(missing)` **`:73`**, `(restricted)` **`:78`** |
| `RoleService.php:261` ("name already exists") | **`:202` and `:262`** |
| G1 "~45 delegable keys" | **49** delegable-and-unprotected of 54 total |
| "`src/Controllers/`" (brief's spelling) | `src/Controller/AdminRoleController.php` — D already corrected this; retained |

### E.6 What V confirmed unchanged — retained verbatim from D

R1 and R2 both hold (`index()` ignores the request; `listWithMeta()` is unfiltered; four system roles
are seeded at `0050_phase5_capabilities_roles.php:183-187` so the table can never be empty;
`RoleAssignmentService.php:71` refuses system-role assignment and `role_edit.php:106`/`:221` guard it).
D's §0 scope verdict holds and is now better supported by `ADMIN_AREAS` and `README.md:114`: Users,
Invitations and Badge rules belong to `admin-members` / `admin-features` and must not be touched by
this screen's migration. Constraints K1–K10 all survive. `A5` is confirmed a genuine catch —
`app.css:3465-3497` has no `.state-scheduled` / `.state-expired` rule.

---

## F. Corrected classification counts for `admin-people`

| Class | D said | **Corrected** | Movement |
|---|---|---|---|
| copy | 32 | **35** | −C1 (struck); +M3, +M5, +M6, +M7 |
| feature-added | 10 | **11** | −A1 (→N1, reclassified); +M2, +M4 |
| feature-removed | 2 | **2** | R1, R2 unchanged |
| feature-changed | 2 | **4** | +N1 (was A1), +N2 (was K11) |
| constraint | 11 | **10** | −K11 (→N2, reclassified) |
| **Total rows** | **57** | **62** | 1 struck, 2 reclassified in place, 6 added |

Fiction table F1–F15 is unchanged in size (15 rows, counted separately as D had it); F1/F2/F3 are
re-anchored from `AdminPeople.dc.html` to `components/admin/AdminNav.jsx:53`, `:27-28`, `:44`.

State inventory S1–S24 is unchanged in size; only line anchors move (§A.2).

---

## G. Slice corrections

* **Slice 1** — drop the eyebrow entirely. Deliverables become **C2, C3, A4, K2, K4** (C1 and K11
  removed; N1/N2 are ADR-adjudication items, not slice work). Delete the integration assertion
  *"all three routes return 200 with the eyebrow 'Operator desk · People'"*. Keep the tab-link,
  `aria-current="page"`, telemetry-sentence and simulator-anchor assertions, and add a regression
  assertion that **no** `.eyebrow` is introduced on the three roles templates.
* **Slice 2** — add the two extra `form[action="/admin/roles"]` selector sites (E.4) to the Watch list.
* **Slice 4** — restate its premise. The record's Edit-definition card in the design has **no**
  capability fieldsets (M2), so "plus the definition form's share of C11/C12/C13/G1/G2" is an
  explicit extrapolation, not verbatim adoption, and must be labelled as such in the ADR.
* **Slice 5** — add **M3** (Assign → Assign role: keep production's label or change it deliberately)
  and **M4** (keep the sr-only "Actions" `<th>`; do **not** adopt the design's empty `<th>` — it
  would revert ADR 0023 item 5). Add ADR 0023 deferral #4 (E.3) as an explicit close-or-restate.
* **Slice 6** — add **M6** (delete the literal ` - ` connector) and **M7**.
* **Slice 7 (ADR)** — now also records: the AdminNav-vs-rail IA adjudication (N1) including the
  People/Members/Features split; the `Admin mode` pill relocation question (N2); the design's
  system-wide eyebrow removal and its consequence for `dashboard.php:6` / `settings.php:14` /
  `branding.php:11` (flagged, owned elsewhere); M2 as an extrapolation; M4 as an ADR-0023 guard;
  and ADR 0023 deferral #4.
