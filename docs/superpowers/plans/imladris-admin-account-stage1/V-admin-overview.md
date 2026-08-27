# V — admin-overview: adversarial verification of D-admin-overview.md

**Verdict: the production-side half of the report is accurate and well cited; the design-side half is written against a superseded file and must be redone.**

I opened every file. Production citations (`templates/admin/dashboard.php`, `templates/admin/audit.php`,
`src/Controller/AdminController.php`, `src/Service/AdminDashboardService.php`, `src/Service/AuditQueryService.php`,
`src/Repository/ModerationLogRepository.php`, `src/Support/helpers.php`, `public/assets/app.css`, the tests, ADR 0023,
the 2026-07-18 plan) are correct to within ±1 line in every case I checked, and the feature-removed calls all hold
under grep. The design-side citations do not survive: **every design line number in the report is wrong, and five
rows describe markup that no longer exists at the stated design-source path.**

---

## 0. Root cause — the design source moved under the peer

`docs/design-system/imladris/templates/admin-overview/AdminOverview.dc.html` is **modified in the working tree**
(`git status: M`, mtime `2026-08-03 20:36:49`). Its committed HEAD version (`44bfd8a`) is what the report describes.
The file now at the stated design-source path is 394 lines, not 405, and the diff is structural:

```
-  <div style="position: sticky; ... height: 58px; ...">      ← the 58px topbar, star SVG,
-    ... <span ...>Imladris</span> ...                            "Imladris" wordmark and
-    <a ...>Back to the council</a>                               "Back to the council" link
-  </div>
+  <x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav" area="overview" hint-size="100%,101px"></x-import>

-  <div style="max-width: 1160px; ... padding: 26px 28px 110px;">
+  <div style="max-width: 1160px; ... padding: 22px 28px 110px;">

-    <div style="display: flex; align-items: flex-start; ...">
-      <div>
-        <span ...>Operator desk</span>                          ← the head eyebrow is DELETED
-        <h1 style="... font-size: 2.4rem; ...">Admin console</h1>
-      </div>
-      <span style="... background: var(--surface-review); ...">Admin mode</span>   ← pill MOVED into AdminNav
-    </div>
+    <h1 style="margin: 0; ... font-size: 2.1rem; ...">Admin console</h1>

-    <nav ... style="... margin: 22px 0 0; ...">
+    <nav ... style="... margin: 16px 0 0; ...">
-      <span ...>Moderation · Content · People · Appearance · Notifications · Integrations · Settings</span>  ← DELETED
```

This is not drift — it is a deliberate, documented design decision. `docs/design-system/imladris/components/admin/admin.card.html:43`:

> "The tier is a pill row, the page's own sections are underline tabs, and the page heading sits between them —
> three signals keeping the two ranks apart. Measured against the pages it replaces, this chrome is 10px *shorter*:
> the redundant "Operator desk · Area" kicker is gone, the mode pill moved into the identity row, and the heading
> drops from 2.4rem to 2.1rem."

**Caveat for the parent agent:** the working-tree change is uncommitted and lands from the same 2026-08-03 DesignSync
pull that added `docs/design-system/imladris/components/admin/`, `PRODUCTION.md`, `RETIRED.md`, and four new
`templates/admin-*` folders. If the intended design source for this pass is HEAD rather than the working tree, most
of §1 below reverses. Everything in §2–§4 is independent of that question.

---

## 1. REFUTED

### R1 — Rows 1, 3, 4, 5, 7 describe markup that no longer exists; every design line number is wrong

| Report row | Report says design is | Design actually is now |
|---|---|---|
| 1 Topbar `(:24-30)` | Sticky 58px bar, 8-point star, `Imladris`, `Back to the council` | `AdminOverview.dc.html:24` is one line: `<x-import ... AdminNav area="overview">`. No inline topbar at all. |
| 2 Page frame `(:32)` | `padding: 26px 28px 110px` | `:26` — `padding: 22px 28px 110px` |
| 3 Head geometry `(:35-41)` | flex `align-items:flex-start`, h1 **2.4rem**, pill `margin-top:8px` | `:29` — a bare `<h1 style="margin: 0; … font-size: 2.1rem; …">Admin console</h1>`. No wrapper, no flex row. |
| 4 Eyebrow skin `(:37)` | head eyebrow `Operator desk`, `.68rem`, `--gold-ink`, `.18em` | **The head eyebrow does not exist.** Deleted per `admin.card.html:43`. Only the four *section* eyebrows remain (`:47, :83, :102, :120`, `.64rem/.16em`). |
| 5 Admin-mode pill `(:40)` | in the head, `--surface-review`/`--on-review` | Moved into the shared bar: `AdminNav.jsx:58` `<span className="admin-bar-mode">`, default `modeLabel = 'Admin mode'` (`:45`). |
| 7 Pseudo-nav span `(:49)` | non-interactive `Moderation · Content · …· Settings` | **Deleted.** The subnav at `:32-37` is four `sc-if` buttons and nothing else. |

Line drift on every other design citation (report → actual): intro `:55`→`:42`; queue eyebrow `:60`→`:47`;
Live chip `:63`→`:50`; queue grid `:65`→`:52`; `Reports open` `:67`→`:54`; amber `Waiting` `:76,:82`→`:63,:69`;
`Email queue` `:85-88`→`:72-75`; attention pill `:99`→`:86`; attention `li` `:103-106`→`:90-93`;
attention empty `:110`→`:97`; activity grid `:117`→`:104`; activity card `:119-125`→`:106-112`;
recent-activity card `:130-134`→`:117-121`; `View full audit log →` `:136`→`:123`;
dashboard `th` `:140-144`→`:127-131`; audit intro `:165`→`:152`; filter form `:167`→`:154`;
filter grid `:168`→`:155`; loading skeleton `:211-219`→`:198-207`; error state `:222-228`→`:209-215`;
audit `th` `:234-239`→`:221-226`; target link `:247`→`:234`; change cell `:249`→`:236`;
empty state `:254-260`→`:241-247`; pager `:263-269`→`:250-256`. The x-dc script starts at `:264`, not `:277`,
so every `x-dc:NNN` citation is +13.

**Consequence:** the report's proposed actions for rows 3, 4, 5 are now *backwards*. It proposes keeping and
restyling production's `Operator desk` / `Accountability` eyebrow and its in-head `Admin mode` pill, and raising
the h1 to 2.4rem. The current design says: delete the head eyebrow, move the mode pill out of the page head into
the shared admin bar, and set the h1 to 2.1rem (production is at 1.9rem — `app.css:2825-2828`). Adopting the
report as written would move production *away* from the design on all three.

### R2 — Row 18 / slice S2 will break a shipped Playwright pin

The report justifies renaming `AdminDashboardService.php:62` `'Reports'` → `'Reports open'` on the grounds that
"the substring regex pin at `AppAdminDashboardRemediationTest.php:305` survives". That PHPUnit pin does survive
(`~data-queue-status="unavailable"[^>]*>.*?Reports~s`). But the browser contract pins the exact strings:

```ts
// tests/browser/admin-dashboard.spec.ts:99-101
await expect(page.locator('[data-queue-status] .queue-card-head')).toHaveText([
  'Reports', 'Approval hold', 'Appeals', 'Email failures', 'Thread Intelligence',
]);
```

`toHaveText(string[])` is full-string equality per element (whitespace-normalised), **not** substring —
`toContainText` is the substring form. `Reports open` fails it. Slice S2 lists only the PHPUnit test plus new
assertions and does not mention this spec. `docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md:39`
additionally pins "dashboard queue/activity labels" as part of the red integration contract, so the rename is a
contract change, not a copy fix. (The report's *other* claim about this locator — that dropping
`text-transform: uppercase` is safe — is correct: `toHaveText` reads `textContent`, not rendered text.)

### R3 — Row 36's "decorative CSS `::after`" mitigation is not proven safe

Adding the trailing `→` as `::after` keeps `dashboard.php:89` byte-identical, so the PHPUnit pin at
`AppAdminDashboardRemediationTest.php:280` holds. But `tests/browser/admin-dashboard.spec.ts:105` matches by
accessible name — `page.getByRole('link', { name: 'View full audit log' })`, default full-string — and Chromium
includes CSS generated content in accname computation. The house pattern for a decorative arrow in this very file
is an aria-hidden span (`dashboard.php:92`: `Scroll for Target and Reason <span aria-hidden="true">→</span>`),
which would break the byte-identical PHPUnit pin instead. The tension between the two pins is real and unresolved;
the report presents it as solved at "medium" risk without naming the accname exposure.

### R4 — Row 57 proposes building something neither side has

The report's own two columns read "Design: one state for both (x-dc:336,395)" and "Production: one state
(audit.php:107)" — i.e. **no difference** — and it is nonetheless filed as a `copy` difference with the action
"Add an unfiltered variant … ('No moderation or admin actions have been recorded yet.')". Verified against the
current x-dc (`:321-333`): `isEmpty` sets `pool = []`, `rows = []`, `noRows = true`, and the single
`Nothing matches these filters` block renders for both the no-data and no-match cases. Inventing a second empty
state the design never models is not one of the four sanctioned deviations. (`base_query` does exist at
`AuditQueryService.php:73`, so it is buildable — that is not a warrant under these rules.)

### R5 — Row 59's plan citation does not support its conclusion

The report keeps `<details class="audit-change">` partly because "2026-07-18 plan Task 1 pins 'precise
before_json/after_json'". `docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md:37` reads
"Assert owning-page redirects and precise `moderation_log.before_json`/`after_json` payloads" — it pins the
**stored** payloads asserted by an integration test, and says nothing about the audit screen's disclosure widget.
The conclusion (keep the disclosure; the stored data is raw JSON, not a prose diff) stands on the data shape alone.

### R6 — Minor citation errors (do not change any disposition)

- Row 61: "200 hard cap in the repo" is right (`ModerationLogRepository.php:71`) but the clamp that governs
  `/admin/audit` is `AuditQueryService.php:72` (`$perPage = max(1, min(200, $perPage))`), never cited.
- Row 2: `.admin` opens at `app.css:2800`, not 2799. Row 46: `.filter-grid` at `3128`, not 3129.
  Row 37: `.admin .audit th` at `3238`, not 3239. Row 42: `tr:hover td` at `3266`, not 3267.
  Row 38: `human_datetime` at `helpers.php:64-76`, not 65-75. All ±1; harmless.
- Header note: the controller is `src/Controller/AdminController.php` (singular) — the report has this right and
  the orchestrator prompt has it wrong (`src/Controllers/`). Worth carrying forward.

### Claims I tried to refute and could not (these hold)

- `AdminDashboardService.php` — `:62` `'Reports'`, `:86-98` Email-failures detail matrix, `:101-124` conditional
  Thread Intelligence 5th card, `:126-169` attention entries carrying **only** `label`+`href` (no age anywhere),
  `:173-186` exactly two activity cards, `:48` `recent(10)`, statuses at `:66,77,84,97` limited to
  `attention|clear|unavailable`. All exactly as reported.
- The three-status pin is real: plan `:77` — "Queue card status is exactly `attention`, `clear`, or `unavailable`."
  Row 20 (no amber "Waiting" tier) is correct.
- `AuditQueryService.php:57/65/84` error strings quoted verbatim and correct; `:77-101` actor→id resolution with
  the 500 refusal; `:88-99` empty-actor short-circuit; `ModerationLogRepository.php:108-112` action is a prefix
  `LIKE 'x%'`. Row 50 is correct.
- Row 55: a read failure really does become a kernel 500 — `src/Core/App.php:414` `catch (Throwable $e) { return
  $this->renderServerError(...) }`. No partial-failure path reaches a per-panel retry.
- Row 6/40/62's binding-decision claims: ADR 0023 item 6 (`docs/adr/0023-admin-console-audit-round-2.md:17`) locks
  the 8-group IA verbatim; item 4 (`:15`) locks the "New users today" label; item 5 (`:16`) owns the
  table-region/field-error a11y wiring; plan `:94` owns `data-overflow-cue`. **No proposed action silently reverts
  a deferral in ADR 0021 or ADR 0023** — I checked all four ADR 0023 deferrals and none is touched.
- Token check: `--presence`, `--surface-review`, `--on-review`, `--artifact-link`, `--focus-ring`, `--gold-500`,
  `--amber`, `--rust`, `--radius-lg`, `--shadow-md`, `--font-mono`, `--ease-calm`, `--border-soft`, `--gold-ink`
  all resolve in `public/assets/imladris.css`. Correct.
- Production section order matches the design's dashboard order, and is pinned at
  `AppAdminDashboardRemediationTest.php:264-274` and `admin-dashboard.spec.ts:92`. Correct.
- Row 49: the six filter fields, their order, placeholders and the eight target types are byte-for-byte identical
  between `AdminOverview.dc.html:156-189` and `audit.php:24-57`. Correct — genuinely already verbatim.

---

## 2. MISCLASSIFIED

**M1 — Row 41 (dashboard audit empty state): `copy` → `feature-added`.**
Design does not model it (x-dc `:355` `recentAudit: (isEmpty ? [] : AUDIT.slice(0, 6))`; there is no `sc-if`
empty branch in the dashboard table at `:133-143`). Production has `No moderation or admin actions yet.`
(`dashboard.php:100`) and the report's action is "keep the string". Production having what the design never
modeled, and keeping it, is `feature-added` by definition. The report's own §4 state inventory calls this exact
item "feature-added (production is more honest)" — the difference table contradicts the state inventory.

**M2 — Row 42 (row hover): `copy` → `feature-added`.**
Design has no `tr:hover`; production has `.admin .audit tr:hover td { background: var(--surface-sunken) }`
(`app.css:3266`); the recommendation is "keep". A `copy` difference is by definition one production must change
to match. "Keep" is only available under `feature-added`.

**M3 — Row 61 (10 vs 50 per page): `copy` → `feature-changed`.**
Same concept, different mechanics, and the report keeps production's 50 on operator-surface grounds — that is the
`feature-changed` disposition ("design wins on presentation; production wins on behavior"). Filed as `copy` with a
"keep production" action, which is self-contradictory.

**M4 — Row 32 (activity grid 4 cols → 2): `copy` → downstream of row 29's `feature-removed`.**
Only two metrics exist, so two columns is the correct rendering of a `feature-removed` set, not a `copy`
difference production must change.

**M5 — Row 54 (loading skeleton): the named constraint is the wrong one.**
The report leads with "inline `<style>` is CSP-illegal". A `@keyframes` block is perfectly shippable from
`public/assets/app.css`, so CSP does not forbid the skeleton — it forbids the *inline* delivery, which the
governing rules explicitly call "a MECHANISM constraint, not a licence to change the visual result". The load-bearing
constraint is progressive enhancement: the page is server-rendered in one pass, so there is no loading state to
render. The report does say this second; it should be the only reason given.

The report's other three `constraint` calls (rows 1, 43, 31) each name a real production constraint —
layout-owns-the-shell, PE + PRODUCT_DESIGN §5.3 crawlable URLs, and ADR 0023 item 4 respectively — and survive.

---

## 3. MISSED

**Mi1 — The shared `AdminNav` chrome: the design's biggest admin decision, entirely absent.** *(feature-changed)*
All ten `admin-*` design templates now mount one component (`AdminOverview.dc.html:24`; the same import at line 22
of the nine siblings). `docs/design-system/imladris/README.md:109` — "the admin chrome every `Admin —` template
mounts"; `:114` — "ten `admin-*` templates, all wearing `AdminNav`"; `PRODUCTION.md:52` — "unified by
`components/admin/AdminNav`". Anatomy (`components/admin/AdminNav.jsx:50-75`):

- `.admin-bar` → `.admin-bar-id`: `EightPointStar` mark + `.admin-bar-wordmark` "Imladris" + `.admin-bar-exit`
  back link + `.admin-bar-mode` pill (omitted when `modeLabel` is null — `:58`).
- `nav.admin-tier[aria-label="Admin areas"]` → ten `.admin-tier-item` pills in console order, `.is-active` +
  `aria-current="page"` on the current one (`:60-73`).
- The ten areas (`AdminNav.jsx:8-19`): **Overview · Content · People · Members · Appearance · Notifications ·
  Integrations · Packages · Features · Settings**.

Production has an 8-group *vertical* rail (`templates/admin/_nav.php:7-50`, `aria-label="Admin navigation"`,
224px sticky, mobile drawer) with a materially different taxonomy: Members, Packages and Features are **not**
top-level areas — Invitations lives under People (`_nav.php:25`), Packages under Integrations (`:38`), Feature
flags under Settings (`:47`). The report's disposition ("keep the rail") is very likely still correct — ADR 0023
item 6 and ADMIN §9.2 lock it — but it reaches that from a premise that no longer exists ("the design's tab strip
is a per-screen elision"), and it never records the ten-area list, the pill-vs-link register, the
horizontal-tier-vs-left-rail geometry, or the fact that this is now a **cross-screen** decision binding all ten
admin screens rather than an admin-overview-local one.

**Mi2 — Three ranks of chrome in the design; two in production.** *(copy)*
`admin.card.html:43` states the intended hierarchy: area pill tier → page `<h1>` → the page's own underline
sub-tabs. `AdminOverview.dc.html:32-37` is that third rank (`nav aria-label="Admin sections"`, gap 2px, 2px
`--gold-500` bottom border on the active tab, `margin: 16px 0 0`, `border-bottom: 1px solid var(--border-hair)`).
Production has no page-level sub-nav at all: Dashboard and Audit log are siblings in two *different* rail groups
(`_nav.php:9` under "Dashboard", `_nav.php:15` under "Moderation"). Not recorded anywhere in the report.

**Mi3 — The head eyebrow and the in-head mode pill must be deleted, not restyled.** *(copy)*
Production renders `<span class="eyebrow">Operator desk</span>` (`dashboard.php:6`),
`<span class="eyebrow">Accountability</span>` (`audit.php:12`) and `<span class="pill pill-admin">Admin mode</span>`
in both heads (`dashboard.php:9`, `audit.php:15`). The current design removed the kicker outright and moved the
mode pill into the shared bar. This is the largest single copy difference on the screen and the report has it
inverted (see R1).

**Mi4 — h1 target size.** *(copy)* Design `2.1rem` (`AdminOverview.dc.html:29`); production `1.9rem`
(`app.css:2825-2828`). The report says 2.4rem.

**Mi5 — Section rhythm.** *(copy)* Design sections carry `margin-bottom: 30px` individually (`:44`, `:80`, `:101`);
production uses a uniform `.admin-pane { gap: 22px }` (`app.css:2929`). Report mentions the 22px gap only in
passing under row 9 and never files the 30px rhythm.

**Mi6 — `Community today` has no right-hand slot in the design.** *(copy)* `:102-103` is a plain block —
`<span>` eyebrow then `<h2>`, no flex row. Production wraps it in `.section-heading-row` with an empty `<div>`
(`dashboard.php:64-69`), which forces `justify-content: space-between` on a one-child row.

**Mi7 — Attention list rendering shape.** *(copy)* The design always renders the `<ul>` and appends the empty
`<p>` after it (`:88-98`); production renders the `<p>` **or** the `<ul>` (`dashboard.php:46-60`). Cosmetically
equivalent today, but it changes where the empty sentence sits relative to the list rule.

**Mi8 — Dashboard target cells are never links in production; the report only audited `audit.php`.**
`templates/admin/dashboard.php:107` renders `<?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?>` —
plain text for **every** type, including `user`, which *is* linked on the audit page (`audit.php:85-86`). The
design links every target on both tables (`:139` dashboard is plain-mono, `:234` audit is `--artifact-link` — so
the design is itself asymmetric here too, in the opposite direction). Row 58 covers only the audit page and never
notices the production inconsistency.

**Mi9 — The `code` chip.** *(copy)* Design `padding: 1px 6px; border-radius: var(--radius-sm); font-size: .76rem;
color: var(--text-body)` (`:138`, `:233`). Production `.admin .audit code` (`app.css:3271-3277`):
`border-radius: 4px` — a hardcoded value where the design uses a token — plus `font-size: .82em` and
`color: var(--text-strong)`. Appears only inside slice S5's touch list, never as a difference row.

**Mi10 — The empty state's reset control has a different label from the actions-row reset.** *(copy)*
Design: `Reset` in the form actions (`:193`) and `Reset filters` in the empty block (`:245`); both call the same
`resetFilters` handler (x-dc `:313-316`), which clears the typed fields *and* the applied filters. Production has
one label (`Reset`, `audit.php:61`) and no empty-state control. The report covers the two controls in rows 52 and
56 but never notes they carry two distinct strings.

**Mi11 — Fiction has relocated; the report's fiction table cites lines that no longer contain the strings.**
`Imladris` and `Back to the council` are no longer at `AdminOverview.dc.html:26/27/29`. They are now defaults on
the shared component: `components/admin/AdminNav.jsx:53` (`<span className="admin-bar-wordmark">Imladris</span>`)
and `:44` (`backLabel = 'Back to the council'`), documented at `AdminNav.d.ts:21` ("Where "Back to the council"
goes."). The same `Back to the council` link also appears in `templates/account-settings/AccountSettings.dc.html:29`
and `templates/user-profile/UserProfile.dc.html:29` — it is a cross-screen fiction string needing one production
answer, not an admin-overview-local one. Everything else in the report's fiction table (§3 items 4-11) is still
present in the current file at the corrected line numbers and is correctly called.

---

## 4. What to do with the report

Keep, unchanged: rows 6 (conclusion only), 8-42 except 18/32/36/41/42, 43-63 except 57/59/61. Their production
evidence is sound and their dispositions survive scrutiny.

Redo: the design half of rows 1-7, plus rows 18, 36, 41, 42, 57, 59, 61, against the current
`AdminOverview.dc.html` **and** `components/admin/AdminNav.jsx` — the second file is now part of this screen's
design source and was never opened.

Blocking before any slice ships: resolve whether `AdminOverview.dc.html`'s uncommitted working-tree state is the
design source for this adoption. If it is, rows 3/4/5 invert (delete the head eyebrow and the in-head pill; h1 to
2.1rem) and Mi1/Mi2 become the screen's largest open questions rather than settled ones.
