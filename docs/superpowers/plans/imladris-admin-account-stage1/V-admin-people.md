# V — adversarial verification of `D-admin-people.md`

Verdict: **the production half of the report is excellent and survives verification almost
line-for-line. The design half is written against a superseded revision of
`AdminPeople.dc.html` and is materially wrong in four places.**

Everything below was checked by opening the file, not by reading the report.

---

## 0. The finding that governs the rest: the design source changed under the report

`docs/design-system/imladris/templates/admin-people/AdminPeople.dc.html` is an **uncommitted
working-tree modification** (`git diff` vs `44bfd8a`: 4 insertions, 16 deletions), written at
**2026-08-03 20:36:49**. The report describes a 628-line file; the file on disk is **617 lines**
(markup ends at `:339 </x-dc>`; the `x-dc` script runs `:340-614`). The committed HEAD version is
the one the report read — its citations match HEAD exactly.

**Every design line citation in the report is +12 relative to the current file.** Mechanical
correction: subtract 12. Examples: callout `:50-53` → `:38-41`; roles table `:71-98` → `:59-93`;
create card `:107-151` → `:95-139`; record back link `:158` → `:146`; assignments `:219-250` →
`:207-238`; simulator error `:324-329` → `:312-317`.

The diff is not cosmetic. It replaced the hand-drawn topbar **and the entire page head** with:

```html
  <x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="people" hint-size="100%,101px"></x-import>

  <div style="max-width: 1100px; margin: 0 auto; padding: 22px 28px 110px;">

    <h1 style="… font-size: 2.1rem; …">Roles &amp; capabilities</h1>
```

Deleted in the same edit: the `Operator desk · People` eyebrow, the `Admin mode` pill, and the
inline sticky topbar. Page padding `26px → 22px`, h1 `2.4rem → 2.1rem`, tab strip margin
`22px → 16px`.

---

## 1. Refuted claims

### 1.1 `C1` — "Add `<span class="eyebrow">Operator desk · People</span>` to all three templates"

**Refuted.** The current design has **no eyebrow**, and its removal was deliberate and documented.
`docs/design-system/imladris/components/admin/admin.card.html:43`:

> "The tier is a pill row, the page's own sections are underline tabs, and the page heading sits
> between them — three signals keeping the two ranks apart. Measured against the pages it replaces,
> this chrome is 10px *shorter*: the redundant &ldquo;Operator desk&nbsp;·&nbsp;Area&rdquo; kicker
> is gone, the mode pill moved into the identity row, and the heading drops from 2.4rem to 2.1rem."

The design's answer to "which area am I in" is now the tier's `aria-current` pill; production's is
`_nav.php:79`'s `.admin-nav-link.active` + `aria-current="page"`. Both templates already have no
eyebrow (`roles.php:16-19`, `role_edit.php:29-32`, `role_simulator.php:7-10`), so **there is no
difference here at all**. Drop C1, and drop the eyebrow assertion from Slice 1.

(The report's supporting citations are themselves correct — `admin/dashboard.php:6` and
`admin/settings.php:14` do carry `<span class="eyebrow">Operator desk</span>`, and
`.admin-head .eyebrow` is at `app.css:2822`. Note production's convention is the bare
`Operator desk`, never `Operator desk · {Area}`.)

### 1.2 `K11` — "Topbar … Do not port. The design's topbar is a prototype frame, not a component."

**Refuted.** It is a component, and it is *the* shared admin chrome.
`docs/design-system/imladris/components/admin/AdminNav.jsx` (77 lines), documented at
`README.md:109` ("the admin chrome every `Admin —` template mounts") and `PRODUCTION.md:52`
("the ten `templates/admin-*` templates, unified by `components/admin/AdminNav`").

Anatomy (`AdminNav.jsx:50-75`): one sticky block, two rows —

* `.admin-bar-id`: `EightPointStar` mark + `.admin-bar-wordmark` "Imladris" + `.admin-bar-exit`
  back link (`backLabel = 'Back to the council'`, `AdminNav.jsx:44`) + `.admin-bar-mode` pill
  (`modeLabel = 'Admin mode'`, `:45`, suppressible via `modeLabel={null}`).
* `<nav class="admin-tier" aria-label="Admin areas">` — ten `.admin-tier-item` pills with
  `aria-current="page"` on the active one.

The two fiction strings stay correctly flagged (F1/F2/F3). But "do not port, prototype frame" is
wrong: the identity row and the area tier are the system's canonical admin chrome, and the
`Admin mode` pill has *moved into it* — so production's `.pill.pill-admin` inside `.admin-head`
(`roles.php:18`, `role_edit.php:31`, `role_simulator.php:9`) is now a **placement** difference the
report does not record at all.

### 1.3 `A1` — "design shows a single 1100px centred column, no rail … the design's frame is a per-screen elision"

**Refuted as stated.** The design now models admin-area navigation explicitly.
`AdminNav.jsx:8-19` defines `ADMIN_AREAS` in console order:

```
Overview · Content · People · Members · Appearance · Notifications · Integrations · Packages · Features · Settings
```

Production has a vertical **8-group** rail (`_nav.php:7-50`) whose People group holds four entries
(`:22-27`). The design's IA splits them differently: roles → `admin-people`, users + invitations →
`admin-members`, badges → `admin-features` (`README.md:114`).

The report's **action is still right** — keep the rail — but for reasons it did not give, and the
classification is wrong (see §2.1). Binding support the report should have cited:

* ADR 0023 shipped item 6 (`docs/adr/0023-admin-console-audit-round-2.md:17`): "Console IA per
  ADMIN §9.2: grouped admin nav (Dashboard · Moderation · Content · People · Appearance ·
  Notifications · Integrations · Settings) … and inbound links for the two orphaned consoles
  (`/admin/roles/simulator`, `/admin/packages/security`)."
* `tests/Integration/Core/AppAdminDashboardRemediationTest.php:94-119` asserts the eight
  `.admin-nav-group-title` strings **in order** and all 26 destinations.
* `tests/browser/admin-dashboard.spec.ts:60-72` asserts the same via `expectGroupedDirectory`.

This is a real, unrecorded design-vs-production IA conflict, and it belongs in the ADR as an
adjudication, not as a shrug.

### 1.4 Order-delta #3 — "Design's capability fieldsets are inside 'Edit definition' on the record … identical to production (P9, P5). **No delta.**"

**Refuted, and it conceals a real feature-added.** In the design, `capGroups` occurs **exactly
once**, at `:113`, inside the *Create a custom role* form on the roles-list view:

```
$ grep -n "capGroups\|recordCaps" AdminPeople.dc.html
113:              <sc-for list="{{ capGroups }}" as="g" hint-placeholder-count="4">
158:              <sc-for list="{{ recordCaps }}" as="c" hint-placeholder-count="8">
542:      capGroups: groups,
558:      recordCaps: open ? open.caps.map((k) => ({ key: k })) : [],
```

The record's Edit-definition card (`:165-183`) contains **only** h3 "Edit definition", Name,
Description (optional), a "Save (bumps version)" button and the `role="status"` "Saved — now
v{n}." line. No fieldsets, no checkboxes. And `recordCaps` — the read-only key list — renders only
under `recordIsSystem` (`:154`). **So the design gives a custom role's record no way to see, let
alone change, its capabilities.**

Production does both: `role_edit.php:57-69` renders the grouped fieldsets inside the definition
form, with `$checked = (array) ($old['capabilities'] ?? $current_keys)` (`:7`) and
`name="capabilities[]"` (`:62`) — a live 422 round-trip.

Correct classification: **feature-added** (keep the functionality, style it in the design idiom).
Consequence for the plan: the report's Slice 4 line "plus the definition form's share of
C11/C12/C13/G1/G2" is an *extrapolation* of list-page anatomy onto a card the design never modeled
— legitimate, but it must be recorded as such, not shipped as verbatim adoption.

---

## 2. Misclassifications

### 2.1 `A1` — `feature-added` → **`feature-changed`**
Same concept (navigating the ten/eight admin areas), different mechanics (horizontal 10-area pill
tier vs vertical 8-group rail). "Production has functionality the design never modeled" is false as
of the 20:36 sync. Design wins on presentation only where it does not disturb the ADR-0023-locked,
test-pinned grouping; production wins on IA. Record the conflict in the new ADR.

### 2.2 `K11` — `constraint` → **`feature-changed`** (+ `constraint` for two strings only)
Nothing about CSP/PE/authz prevents reproducing an identity row and an area tier. The only genuine
constraint is the fiction lexicon ("Imladris", "Back to the council") and the fact that
`templates/layout.php` owns the shell. The rest is a real anatomy difference.

### 2.3 Order-delta #3 / Slice 4 premise — `no delta` → **`feature-added`**
See §1.4.

Every other `constraint` in the report survives scrutiny and names a real production constraint:
K1 (CSP `style-src 'self'`, verified: **zero** `style="` in `templates/**/*.php`), K2/K3 (PE +
three real routes, `App.php:2219-2227`), K4, K5 (CSRF), K6 (PRG + anti-draft-loss), K7 (PE), K8
(inline `<style>` under CSP), K9 (escaping), K10 (`capabilities` flag → `NotFoundException`,
`AdminRoleController.php:27-32`).

---

## 3. Missed differences

| # | Section | Design (current lines) | Production | Classification |
|---|---|---|---|---|
| M1 | AdminNav identity row + area tier | `AdminNav.jsx:50-75`; mounted at `AdminPeople.dc.html:22` | `_nav.php` 8-group rail; `Admin mode` pill in `.admin-head` not an identity row | feature-changed |
| M2 | Capability editing on a custom role's record | absent (`:165-183`) | `role_edit.php:57-69` full fieldsets + `capabilities[]` round-trip | feature-added |
| M3 | Assign submit label | `Assign` (`:271`) | `Assign role` (`role_edit.php:218`) | copy |
| M4 | Assignments table actions header | **empty** `<th scope="col">` (`:216`) — note the roles table *does* carry an sr-only "Actions" (`:68`) | `<span class="sr-only">Actions</span>` (`role_edit.php:127`) | feature-added — **and copying the design here would revert ADR 0023 shipped item 5, which enumerates "empty `<th>`s" as a fixed a11y pocket** |
| M5 | Em dash vs ASCII hyphen | `—` at `:121` (`</code> — {{ c.description }}`), `:152`, `:294` (`— pick —`) | `-` at `roles.php:75`, `role_edit.php:37`, `:63`, `role_simulator.php:25` (`- pick -`), `:49` | copy — and *not* an encoding constraint: `templates/admin/*.php` uses real em dashes in ≥10 files |
| M6 | Simulator result connector | verdict pill is a flex sibling; no connector (`:322-326`) | `<strong>Denied</strong> - <code>…` (`role_simulator.php:49`) | copy (folded into C32, but the ` - ` must be deleted explicitly) |
| M7 | Actor field label | plain `Actor (username, id, or guest)` (`:288`) | `guest` wrapped in `<code>` (`role_simulator.php:20`) | copy (minor) |

### Missed binding decision — ADR 0023 **Deferral #4**

> "**Deep-admin field-error wiring residue.** `registries.php` and **`role_edit.php`** render field
> errors legibly but are not yet wired to their inputs via the new helpers (their duplicated error
> keys need per-form scoping first to avoid duplicate element ids). Mechanical follow-up; the
> helpers and the pattern exist."

Verified: `role_edit.php` uses **0** `field_error()`/`field_attrs()` calls; `roles.php` uses **7**.
The report's `A2` cites ADR 0023 item 5 and `roles.php:59-87` only, and never surfaces that the
record page's un-wired errors are an *owned deferral* — whose stated precondition ("per-form
scoping first") is exactly what Slices 4 and 5 touch. Nothing in the report **reverts** the
deferral (the `$defErrorContext`/`$cloneErrorContext`/`$assignErrorContext`/`$renewAssignmentId`
scoping at `role_edit.php:13-17` is explicitly preserved), but the new ADR must either close
deferral #4 or restate it.

### Missed pinned selectors

* `form[action="/admin/roles"] button[type="submit"]` is used at **three** places, not one:
  `gate-a.spec.ts:389`, `role-assignments.spec.ts:104`, `role-assignments.spec.ts:204`. The report's
  R1 hazard note names only gate-a.
* `tests/Integration/Admin/AppAdminNavIaTest.php:74` asserts `href="/admin/roles/simulator"` appears
  in `/admin/roles`'s body — this is the ADR 0023 item 6 orphaned-console remediation. The report
  describes the prose link as a deficiency ("reachable only from prose link `roles.php:30`") without
  noting it is a shipped, test-pinned decision.
* `AppAdminFeaturesTest.php:104` / `admin-features.spec.ts:104` pin
  `<a href="/admin/roles">Roles &amp; resolver posture</a>` inbound from `/admin/features`.

### Fiction strings — no misses

The report's F1-F15 list is complete against the current file, with one correction: the topbar
strings now live in `AdminNav.jsx:44` (`backLabel`) and `:53` (wordmark), not in
`AdminPeople.dc.html`. `Warden`/`Elder`/`Member`/`core.role.*` are confirmed fiction —
`0050_phase5_capabilities_roles.php:183-187` seeds `system.guest`/`system.user`/
`system.moderator`/`system.admin` as Guest/User/Moderator/Admin with `(compatibility anchor)`
descriptions, exactly as F4-F9 propose. `core.board.create`, `core.user.grant_badge` and
`core.moderation.claim` do not exist anywhere in `src/Security/` — F10 and G1 are right.

---

## 4. Citation errors

| Report | Actual | Note |
|---|---|---|
| all design `:NNN` | `NNN - 12` | stale revision (§0) |
| "628 lines; markup ends at line 351" | 617 lines; markup ends `:339`, script `:340-614` | |
| `PermissionSimulatorService.php:52` | **:49** | `'No member matches "…"; use a username, a numeric id, or "guest".'` |
| `PermissionSimulatorService.php:60-61` | **:54-55** | actor label composition |
| `PermissionSimulatorService.php:64` | **:63** | `'Time must be UTC "YYYY-MM-DD HH:MM".'` |
| `PermissionSimulatorService.php:69-78` | block is `:68-80`; `(missing)` `:73`, `(restricted)` `:76` | close enough |
| `RoleService.php:261` ("name already exists") | **:202 and :262** | |
| G1 "~45 delegable keys" | **49** delegable-and-unprotected of **54** total | verified by executing `CapabilityCatalog::all()` |

---

## 5. What survives verification unchanged

I opened every production file cited. The following are **exactly right**, including line numbers:

* Controller path `src/Controller/AdminRoleController.php` (there is no `src/Controllers/`).
* Routes `App.php:2219-2227` (nine); `:2219` index, `:2221` simulator, `:2222` edit.
* `AdminRoleController.php`: gate `:27-32`; `delegableCatalogue()` `:35-41`; `index()` takes no
  query params `:44-50`; `rolesView()` unfiltered `:259-268`; flashes `:67,131,151,187,215,249`;
  422 re-renders `:69,133,156,189,251`; update redirects to `/admin/roles` `:131`; simulator
  short-circuits on empty capability `:81` (so an empty capability really does render nothing).
* `roles.php`: `:16-19`, `:20`, `:23-30` (telemetry sentence `:26-27`, clone parenthetical `:29`,
  simulator anchor `:30`), `:32-52`, `:33` `<h2>Roles</h2>`, `:34` `.table-scroll[role=region]`,
  `:41`, `:42`, `:43-45`, `:46`, `:54-91`, `:71` legend, `:75` `consent ?? description`, `:76`,
  `:77`, `:84-89`.
* `role_edit.php`: `:29-32`, `:36-40`, `:38` `(decision #18)`, `:42-79`, `:80-87`, `:89-104`,
  `:106`/`:221` `!$isSystem` guard, `:120-169`, `:123` "No one has been assigned this role yet."
  (exact match), `:127`, `:131`, `:138`, `:139`, `:146-152` renew, `:154-161` row-scoped errors,
  `:171-220`, `:184` scope order/labels, `:191-206` hints, `:192-195` datalist, `:209-211` Reason,
  `:108-118`/`:132-137` board/category name maps.
* `role_simulator.php`: `:7-10`, `:11`, `:14-15`, `:17-39` (`method="get"`), `:24` `required`,
  `:41-61` Result card holding both error and decision, `:44-45`, `:48`, `:55-57` conditional
  Via-role with scope suffix.
* `RoleAssignmentService.php:71` (custom-roles-only refusal), `:204-206` (four computed statuses),
  `:159`, `:165`, `:261`.
* `_nav.php:5` disabled note, `:22-27` People group, `:80-84` disabled span, 8 groups.
* `app.css:610` `.audit code`, `:1490` `.pill` uppercase, `:2822` `.admin-head .eyebrow`, `:2839`
  `.admin .subnav`, `:3217` `.admin .table-scroll`, `:3465-3497` `.state` —
  **and A5 is a genuinely good catch**: the `::before` colour rules cover
  active/sent, paused/queued/pending/suspended, revoked/failed/bounced/complained/banned/deactivated,
  with **no** `.state-scheduled` or `.state-expired`; both would fall back to `--ink-300`.
* `helpers.php:100` `field_error`, `:123` `field_attrs`.
* `gate-a.spec.ts:382` `getByText('system.admin')`, `:389` form selector, `:394` `getByText('Denied')`;
  `a11y.spec.ts:178, 208`.
* No `.callout` class exists in `app.css`; no "capabilities selected" string exists anywhere.
* **R1** holds: `index()` ignores the request, `listWithMeta()` is unfiltered, and the table can
  never be empty (`0050…:183-187` seeds four system roles). Do not build the search/segment/empty state.
* **R2** holds: the Assignments block sits outside both record gates in the design (`:207-275`) and
  `SEED_ASSIGNMENTS` keys role id 2, the *system* Warden (`:365`, `:371-376`); production forbids it
  at `RoleAssignmentService.php:71` and guards it at `role_edit.php:106`.
* **Scope verdict holds and is now better supported**: `ADMIN_AREAS` (`AdminNav.jsx:8-19`) and
  `README.md:114` assign users + invitations to `admin-members` and badges to `admin-features`;
  both template folders exist. Users / Invitations / Badge rules are out of this screen's scope.

---

## 6. Required corrections before this report is actionable

1. Re-read `AdminPeople.dc.html` at its current revision and re-anchor every citation (−12).
2. Delete `C1`. Add the `AdminNav` identity-row/area-tier anatomy and the `Admin mode` pill
   relocation as a new **feature-changed** row.
3. Reclassify `A1` to feature-changed; cite ADR 0023 item 6 +
   `AppAdminDashboardRemediationTest.php:94-119` + `admin-dashboard.spec.ts:60-72` as the reason the
   rail wins; record the People/Members/Features IA split as an adjudicated conflict in the ADR.
4. Rewrite `K11` as feature-changed, keeping only the two fiction strings as constraint.
5. Replace order-delta #3 with a **feature-added** row for record-level capability editing, and
   re-scope Slice 4 accordingly.
6. Add M3-M7, ADR 0023 deferral #4, and the two extra `form[action="/admin/roles"]` selectors.
7. Fix the eight citation errors in §4.
