# V — adversarial verification of D-admin-settings.md

**Verdict:** the report's *production* research is strong — I re-opened every template, controller,
service, test and CSS block it cites and the substance holds. But it is built on a **stale copy of
the design source**, and it makes one **structural error** that would misdirect a whole slice.

Bottom line: keep ~85% of the report; fix the five refuted claims, reclassify four rows, add ten
missed differences, and re-derive every design line citation against the current file.

---

## 0. The report read a superseded design file

`docs/design-system/imladris/templates/admin-settings/AdminSettings.dc.html` was rewritten in the
working copy **today at 20:36** (`git diff HEAD` → 4 insertions, 16 deletions).

| | Report's premise | Current file on disk |
| --- | --- | --- |
| Length | 299 lines | **288 lines** |
| Markup ends | :216 | **:204** |
| `<script type="text/x-dc">` | :217-297 | **:205-285** |
| Topbar | inline sticky 58px bar, :22-28 | **`<x-import … AdminNav area="settings">` at :22** |
| Page head eyebrow | `Operator desk · Settings`, :34 | **deleted** |
| Page head pill | `Admin mode`, :37 | **deleted** (now `modeLabel` on AdminNav) |
| H1 | `General & intelligence` 2.4rem, :35 | `General & intelligence` **2.1rem, :26** |

Everything below the head is offset by **+12** in the report. Spot-check:

| Element | Report says | Actually |
| --- | --- | --- |
| `Identity` H2 | :51 | **:39** |
| `Save name` | :59 | **:47** |
| `The council needs a name.` | :61 | **:49** |
| invite-conflict `role="alert"` | :80 | **:68** |
| `Invitations feature is enabled` | :82 | **:70** |
| TI intro | :95 | **:83** |
| status rail | :98-112 | **:86-100** |
| `Recovery controls` | :116 | **:104** |
| `Daily budget` | :126 | **:114** |
| `Queue states` | :147 | **:135** |
| contract grid / `<dl>` | :159 / :163-165 | **:147 / :151-153** |
| evidence header / filter | :171-178 | **:159-166** |
| empty state | :203-208 | **:191-196** |
| x-dc `queueStates` | :279-285 | **:267-273** |

The *content* claims survive (the strings are all still there, and the topbar strings moved intact
into `components/admin/AdminNav.jsx`), so this is staleness, not fabrication — but every citation in
the adoption plan must be re-derived or an implementer will edit the wrong lines.

---

## 1. Refuted claims

### R1 — "The design does not represent Feature flags." **FALSE.**

The report's preamble states in bold: *"**Does the design represent Feature flags? NO.**"*, row 52
calls `/admin/features` *"entirely unmodelled"*, and slice **S5** proposes to *"apply the same
stat-card, table and callout classes authored in S2-S4"* — i.e. invent an idiom.

There is a whole design screen for it:

- `docs/design-system/imladris/templates/admin-features/AdminFeatures.dc.html` — **492 lines**,
  `x-import … AdminNav **area="features"**` (:22), H1 `Features & badges` (:26), three inner tabs
  `Feature flags` · `Badge rules` · `Custom emoji` (:29-34), a flash `role="status"` block (:37-42),
  a 4-up stat grid `sc-for list="{{ stats }}"` (:58-66), grouped tables with the columns
  **`Flag` · `Effective` · `Default` · `Override` · `Rollback / enablement note` · `Readiness / next step`**
  (:73-78), and a corrupt-overrides `role="alert"` recovery path (:54-56).
- Its intro paragraph (:47) reads, verbatim: *"A read-only view of the declared flags, their
  configured overrides in `settings.features`, and the effective runtime state. Readiness
  distinguishes rows that are not simply shipped. **Enablement stays a deliberate `settings.features`
  write — there are intentionally no toggles here.**"* — the design already encodes production's
  no-toggles rule.
- `docs/design-system/imladris/PRODUCTION.md` Part 2 maps `admin/*` to *"the ten `templates/admin-*`
  templates, unified by `components/admin/AdminNav`"* and states *"Every production surface is now
  represented or classified behavior-only — `manifest.json → unresolved_gaps` is `[]`."*
- Even the superseded `PRODUCTION_PARITY.md:23` lists a *"features console (+corrupt-overrides +
  readiness)"*.

The report inferred the gap from the AdminSettings screen having only two inner tabs. That inference
is wrong: in the design, **Features is a sibling top-level admin area, not a Settings sub-tab.**

**Consequences:** row 52's `feature-added` classification is wrong; `/admin/features` is *out of
scope for this screen* and must be diffed against `AdminFeatures.dc.html`. **S5 as written is a
copy-fidelity failure** — it would ship an invented idiom for a screen that already has a finished
design (which, note, also models Badge rules and Custom emoji as tabs of the same screen — a further
IA difference production does not have).

### R2 — "The topbar is the prototype's chrome, not a page section." **FALSE.**

Row 1 dismisses it. In the current source it is `components/admin/AdminNav` — a first-class,
versioned system component with:

- `components/admin/AdminNav.jsx:8-20` — `ADMIN_AREAS`, **ten** areas in console order
  (Overview, Content, People, Members, Appearance, Notifications, Integrations, Packages, Features,
  Settings), each with a real relative href;
- `components/admin/AdminNav.jsx:63-74` — a `<nav className="admin-tier" aria-label="Admin areas">`
  pill row with `aria-current="page"` on the active item;
- canonical CSS shipped in `docs/design-system/imladris/components.css:328-342`
  (`.admin-bar`, `.admin-bar-id`, `.admin-bar-brand`, `.admin-bar-wordmark`, `.admin-bar-exit`,
  `.admin-bar-mode`, `.admin-tier`, `.admin-tier-item`, `.admin-tier-item.is-active`);
- its own spec card, `components/admin/admin.card.html:1` — *"Admin nav — the unifying bar …
  Identity row plus the ten-area pill tier that every Admin template shares"*.

The identity row (wordmark / back link / mode pill) is indeed not portable. **The ten-area tier is,
and the report never mentions it at all** (see M2).

### R3 — Row 2's rationale: "the design's [tab strip] is a per-screen elision of the locked IA." **FALSE.**

`components/admin/admin.card.html:30` labels the demo *"In place — **the bar above a page's own
sub-tabs**"* and renders exactly that two-level composition (`.admin-tier` pills + an `.inner`
underline tab strip). The design's IA is deliberate and fully specified: global area bar + per-page
inner sections.

The report's *conclusion* (keep production's 8-group vertical rail; don't build a client-state tab
strip) is still defensible under ADR 0023 item 6 — I verified it locks *"grouped admin nav (Dashboard
· Moderation · Content · People · Appearance · Notifications · Integrations · Settings)"*
(`docs/adr/0023-admin-console-audit-round-2.md:17`), and `templates/admin/_nav.php:45-49` implements
the Settings group as three rail entries. But the *reason* given is false, and the PE argument is
overstated: PE forbids the prototype's `onClick` client state, **not** a server-rendered tab strip of
links. Record this as an honest `feature-changed` against a specified design IA (design: 10 areas +
inner tabs; production: 8 groups / 26 destinations, ADR-locked), not as prototype sloppiness.

### R4 — "render the detail as a relative time … via `human_datetime()`" (row 23 / F12). **FALSE about the helper.**

`src/Support/helpers.php:64-76`:

```php
return gmdate('M j, Y \a\t H:i', $ts) . ' UTC';
```

`human_datetime()` returns an **absolute** string (`Aug 3, 2026 at 09:12 UTC`). It cannot produce the
design's `Last run 6 minutes ago`. The only other helper is `human_duration(int $seconds)`
(`helpers.php:144`), which formats a duration, not an elapsed-since. **No relative-time helper
exists** — the design's string needs a new one, which the report does not budget for.
(The underlying data claim is fine: `completed_at` is in the read model,
`ThreadIntelligenceSettings.php:206` `heartbeat()` → `:233`.)

### R5 — "no toggles … ADR 0021 deferral #7" (row 52). **Miscited.**

`docs/adr/0021-admin-console-remediation-and-deferrals.md:68-70`, deferral **#7**, is the
**`link_previews` operations console** — nothing to do with feature-flag toggles. The no-toggles rule
is real but sourced elsewhere: the docblock at `src/Controller/AdminFeatureController.php:54-63`
(*"Deliberately not a toggle — enablement stays an explicit settings.features write
(docs/runbooks/operations.md §2)"*) plus `AdminFeatures.dc.html:47`. The report cites those correctly
in row 16; the ADR number in row 52 is wrong and must not be carried into ADR 0024.

### R6 — The structured summary's 'Needs attention' action contradicts the report body and would regress the page.

Body row 20: *"KEEP, first in the pane."* Structured summary: *"KEEP, first in the pane. **Only
surface for flags_corrupt and configuration_warnings.**"* Executing the summary would drop 11 of the
13 literal warnings emitted by `ThreadIntelligenceAdminService::warnings()`
(`src/Service/ThreadIntelligence/ThreadIntelligenceAdminService.php:149-190`): flags-off, credential
missing, pause corrupt, provider corrupt, provider latched, budget corrupt, worker
interrupted/stale/attention/invalid, dead queue, review-required queue. It would also break
`AppAdminThreadIntelligenceTest` (asserts `Both product flags are off` renders,
`tests/Integration/Admin/AppAdminThreadIntelligenceTest.php:58`). The row's own condition count is
also inconsistent across the report: "10 distinct warning conditions" (row 20) vs "14 warning states"
(summary) vs 13 strings enumerated in §4. Actual: **13 literal warnings + a `configuration_warnings`
passthrough.**

### R7 — The classification counts are wrong.

Report claims `copy 24 · feature-added 13 · feature-removed 2 · feature-changed 3 · constraint 11`
(and repeats it in the structured summary). Counting the 56 rows of its own table:

| bucket | claimed | actual |
| --- | --- | --- |
| copy | 24 | **19** |
| feature-added | 13 | 13 ✓ |
| feature-removed | 2 | 2 ✓ |
| feature-changed | 3 | 3 ✓ |
| constraint | 11 | **13** |
| match (uncounted) | 6 | 6 ✓ |

19+13+2+3+13+6 = 56 ✓.

---

## 2. Misclassifications

### MC1 — Row 25 (status-rule colour): `copy` → **feature-added / owned defect fix**

The defect is real and I confirmed it: `public/assets/app.css:2985-2991` paints
`.queue-card::before { background: var(--success) }` and the only modifiers are
`.queue-status-attention` (rust) / `.queue-status-unavailable` (faint) at `:2992-2993`;
`templates/admin/thread_intelligence.php:25,30,35,40` emit bare `class="card queue-card is-static"`.
So a `Not ready` provider and a `Paused` generation both render a green rule. **Good catch.**

But it is not a `copy` difference, because the design's rules are **static per-card identity
colours**, not state-driven: `AdminSettings.dc.html:86` Provider `--success`, `:91` Heartbeat
`--info`, `:96` Generation `--warning`, with **no** state logic anywhere in the x-dc script.
Copying the design verbatim would leave a green rule under `Not ready` *and* pin the Generation card
to `--warning` forever. The report's proposed state-driven modifiers are a production improvement
**beyond** the design and must be recorded as such.

### MC2 — Row 37 (generation-contract values): `constraint` → **no difference**

Production already renders the seam: `templates/admin/thread_intelligence.php:93-95` echoes
`$dashboard['model'] / ['reasoning_effort'] / ['prompt_version']`, fed by
`ThreadIntelligenceOperationsService::status()` (`:58-60`) from
`ThreadIntelligenceConfig::DEFAULT_MODEL = 'gpt-5.6-luna'` (`:21`),
`DEFAULT_REASONING_EFFORT = 'low'` (`:22`) and
`ThreadIntelligencePromptBuilder::VERSION = 'thread-intelligence-v1'` (`:18`). All verified.
`claude-sonnet-4-6` / `medium` / `ti.summary.v7` are prototype **sample data**, not Tolkien fiction
and not a design decision — every `.dc.html` carries sample data. There is nothing to change and no
constraint to record; this is a "match, do not regress" note. Same objection to F7-F9 and to F15/F16,
which the report itself already labels "sample data only".

### MC3 — Row 51 (loading state): `constraint` on an explicit non-difference

The row says design has none, production has none, "nothing to do". That is not a deviation of any
kind; it inflates the constraint bucket. Drop it.

### MC4 — Row 52 (`/admin/features`): `feature-added` → **out of scope (own design screen)** — see R1.

### MC5 — Row 43 (Outcome pill): the three-register proposal exceeds the design

The design models exactly **two** registers (`:182` done, `:183` rust). Mapping production's 9-status
enum onto three registers is sound behaviour-wise, but it is an *added* deviation on top of
`feature-changed` and should be recorded explicitly rather than folded in silently.

---

## 3. Missed differences

| # | Missed | Classification |
| --- | --- | --- |
| M1 | **Production's `.pane-intro`** — `templates/admin/settings.php:23` *"Manage the community name and who can create an account. Each form saves only its own setting."* The design's General tab has **no** pane-level intro (per-card intros only, `:40`/`:56`); a pane intro appears only on the TI tab (`:83`). The report lists it as P5 in the order table and then **never classifies it**. | copy (delete or justify) |
| M2 | **The AdminNav ten-area pill tier** — `AdminNav.jsx:8-20,63-74`; `components.css:337-342`. Design: 10 areas, horizontal pills, sticky, `aria-label="Admin areas"`. Production: 8 groups / 26 destinations, vertical rail (`templates/admin/_nav.php:9-49`), ADR 0023 item 6. The report addresses only the *inner* tab strip. | feature-changed (ADR-locked IA) |
| M3 | **Field-label skin** — design renders `Community name` (`:43`) and `Registration mode` (`:59`) as lapidary caps (`.68rem`, `letter-spacing:.1em`, `text-transform:uppercase`, `--text-faint`). Production ships a default `.field > span`. Not itemised anywhere. | copy |
| M4 | **Case treatment of rail labels** — design status labels are **not** uppercase (`:87`, `.74rem`/`.05em`/`--text-muted`) and neither are queue-tile labels (`:140`, `.68rem`/`.08em`); production `.queue-card-head` sets `text-transform: uppercase` (`app.css:3002-3008`). The report covers the *order* (count-first) but not the case. | copy |
| M5 | **H1 type scale** — design `2.1rem` font-display 500, `-0.01em` (`:26`); production `.admin-head h1 { font-size: 1.9rem }` (`app.css:2825-2827`). | copy |
| M6 | **Card H2 scale split** — design uses `1.3rem` for the two General cards (`:39`,`:55`) and `1.25rem` for every TI section (`:104`,`:114`,`:149`,`:159`). | copy |
| M7 | **Elevation split** — the evidence card carries `box-shadow: var(--shadow-sm)` (`:157`) while the Generation-contract card beside it does not (`:148`); same on the two General cards (`:38`,`:54`). Deliberate; unmentioned. | copy |
| M8 | **Measure caps** — card intros `max-width: 48ch` (`:40`,`:56`), TI intro `70ch` (`:83`), recovery helper `62ch` (`:110`), evidence-empty copy centred at `34px 20px` (`:192`). | copy |
| M9 | **Recovery controls are bare `<button onClick>`** in the design (`:106-107`) with no form; production ships two `<form method="post">` + `csrfField()` (`thread_intelligence.php:49-60`). The report scores row 26 as a pure match and never records the PE/CSRF constraint for these controls. | constraint |
| M10 | **Design's queue unit is a flat `threads`** for every tile (x-dc `:268-272`); production pluralises (`thread_intelligence.php:85`). Trivial, but it is a string difference production should keep. | feature-added (keep) |

**Fiction the report missed:** the x-dc sample `siteName: 'Imladris'` (`:220`) — the Community-name
field's placeholder value. Trivial, but it is the same word as F1 and will be pasted by anyone
transcribing the field. Otherwise the fiction inventory is good: `Imladris`, the eight-point star,
`Back to the council` (now `AdminNav.jsx:46` `backLabel`), `the council goes by`, `The council needs a
name.`, `The council approves` are all correctly caught, and the ADR 0019 §2 objection to *"The
council approves; the model proposes"* is a genuinely sharp catch.

---

## 4. What I verified and could not break

Every production citation I checked resolved, and the substance is right:

- **Routes** — `src/Core/App.php:2205,2206,2207,2303,2304,2305-2311,2329` exactly as tabulated.
- **Tombstone** — `AdminSettingsController::obsoleteCombinedUpdate` (`:64-67`) throws
  `NotFoundException`; docblock at `:57-63` says what the report quotes; pinned by
  `AppAdminDashboardRemediationTest::test_obsolete_combined_settings_post_is_not_routable` (`:64-75`).
- **422 round-trip** — `AdminSettingsController.php:30-34,47-51`; the unknown-mode fallback
  `<option value="banana" selected>` is asserted at `AppAdminDashboardRemediationTest:224`;
  `settings.php:48-50` renders it.
- **Validation string** — `AdminSettingsService.php:69-72` `'Site name must be 1–80 characters.'`;
  asserted at `AppAdminDashboardRemediationTest:220` (report says :221 — off by one; the
  `General & registration` assert is :219, not :220).
- **a11y wiring** — `AppFieldErrorA11yTest::test_split_settings_pages_wire_validation_errors_to_their_owner_fields`
  at `:153`, site block `:157-165` (`site-name-help`), registration block `:168-174`. ADR 0023 item 5
  confirmed at `0023-…:16`.
- **Invitations flag has no toggle** — I grepped `set('features'` across `src/`: **zero hits**; no
  template posts a `features[...]` field. The `feature-removed` call is correct, and the design's own
  Features screen agrees (`AdminFeatures.dc.html:47`).
- **Redaction invariant** — `safeGeneration()` (`ThreadIntelligenceAdminService.php:193-226`) omits
  `request_fingerprint`; `AppAdminThreadIntelligenceTest:63`
  `assertStringNotContainsString($requestFingerprint, …)`. The Digest-column refusal is correct.
- **Visibility gate** — `publicThreadLink()` (`:229-241`) requires
  `t.is_deleted = 0 AND t.is_pending = 0 AND b.visibility = 'public'`. Correct and load-bearing.
- **Queue states** — `ThreadIntelligenceOperationsService::JOB_STATES` (`:17`) is exactly
  `idle, queued, running, retry, dead, review_required`, zero-filled at `:36`. Six, not five.
- **Heartbeat** — `heartbeatClassification()` (`:212-231`) returns
  `never_run|invalid|running|interrupted|attention|stale|healthy`; `Nominal` is not in the
  vocabulary. Correct.
- **Budget** — `ThreadIntelligenceBudget::status()` (`:37-67`), `exhausted` `:56/:63`,
  `next_reset_at` `Y-m-d H:i:s` `:64`, `corrupt` `:65`. Correct.
- **Flags-off reachability** — `AdminThreadIntelligenceController::index` (`:14-20`) calls only
  `requireAdmin()` + `noindex()`; `AppAdminThreadIntelligenceTest:29,55-58` pins the 200 and the
  `Both product flags are off` warning; `_nav.php:48` hides only the link via `flags_any`. The
  report's correction of "F3" is right.
- **CSS** — `.settings-card { max-width: 720px }` `app.css:3095`; `.eyebrow` `.72rem`/`--text-muted`
  `app.css:37-43`; `.queue-card-count` font-display `2.1rem` `:3009`; `.ti-budget progress` `:5680`
  (width only — the report calls it "styled", which overstates it).
- **Playwright** — `tests/browser/thread-intelligence.spec.ts:39-42` scrolls
  `.thread-intelligence-admin .table-scroll` on the mobile project. Correct.
- **Namespace** — `src/Controller/` (singular) is right; the brief's `src/Controllers/` is wrong.
  Good catch by the report.

No production-behaviour claim in the report turned out to be false.

---

## 5. Required corrections before this plan is executable

1. **Re-derive every design citation** against the 288-line working copy; delete rows 4 and 5 (the
   eyebrow and pill no longer exist on this screen) and re-source the topbar row to
   `components/admin/AdminNav.jsx` + `components.css:328-342`.
2. **Replace S5.** `/admin/features` is not this screen's problem and is not unrepresented — diff it
   against `templates/admin-features/AdminFeatures.dc.html` in its own pass (that screen also models
   Badge rules and Custom emoji as sibling tabs, which production splits across three routes).
3. **Reclassify** rows 25 (→ feature-added / defect fix), 37 (→ match), 51 (→ drop), 52 (→ out of
   scope); record MC5 explicitly.
4. **Fix the 'Needs attention' action** in the structured summary to match the body (keep all 13
   warnings + `configuration_warnings`).
5. **Budget a new relative-time helper** or drop `Last run 6 minutes ago` — `human_datetime()` cannot
   produce it.
6. **Drop ADR 0021 #7** from row 52's citation; cite `AdminFeatureController.php:54-63` +
   `docs/runbooks/operations.md` §2 (and now `AdminFeatures.dc.html:47`).
7. **Add M1-M10** to the difference table; M1 (the orphan `.pane-intro`) and M2 (the ten-area tier)
   are the two that change what ships.
8. **Fix the counts** (copy 19, constraint 13).
