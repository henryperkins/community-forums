# D — admin-settings: design vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-settings/AdminSettings.dc.html`
(299 lines; markup ends at line 216, `<script type="text/x-dc">` runs 217-297)

**Production homes (three separate routes, not one screen):**

| Route | Template | Controller |
| --- | --- | --- |
| `GET /admin/settings` (App.php:2205) | `templates/admin/settings.php` (66 lines) | `src/Controller/AdminSettingsController.php::general` (:17) |
| `POST /admin/site` (App.php:2329) | — (422 re-renders `admin/settings`) | `AdminSettingsController::updateSite` (:24) |
| `POST /admin/settings/registration` (App.php:2207) | — (422 re-renders `admin/settings`) | `AdminSettingsController::updateRegistration` (:41) |
| `POST /admin/settings` (App.php:2206) | — | `AdminSettingsController::obsoleteCombinedUpdate` (:64) → **404 tombstone** |
| `GET /admin/thread-intelligence` (App.php:2304) | `templates/admin/thread_intelligence.php` (152 lines) | `src/Controller/AdminThreadIntelligenceController.php::index` (:14) |
| 7 TI POST routes (App.php:2305-2311) | — | `AdminThreadIntelligenceController` (:23-80) |
| `GET /admin/features` (App.php:2303) | `templates/admin/features.php` (111 lines) | `src/Controller/AdminFeatureController.php::index` (:118) |

> The brief said `src/Controllers/...`; the real namespace directory is `src/Controller/` (singular).

**Does the design represent Feature flags? NO.** The design screen has exactly two tabs
(`AdminSettings.dc.html:41-44`: `General & registration`, `Thread Intelligence`). Production's
Settings nav group has three entries (`templates/admin/_nav.php:45-49`: `settings`, `features`,
`thread_intelligence`). `/admin/features` is entirely **unrepresented** by the design and is
classified below as **feature-added** — it must be kept, styled in the idiom, and must stay
toggle-free (`AdminFeatureController.php:54-64` comment; `docs/runbooks/operations.md` §2).

---

## 1. Section-order comparison

### Design order (verbatim eyebrows / headings, top to bottom)

| # | Design element | Verbatim string |
| --- | --- | --- |
| D1 | Sticky topbar (:22-28) | elven-star SVG + wordmark `Imladris`; back link `Back to the council` |
| D2 | Page head eyebrow (:34) | `Operator desk · Settings` |
| D3 | Page head H1 (:35) | `General & intelligence` |
| D4 | Head pill (:37) | `Admin mode` |
| D5 | Local tab nav (:40-45), `aria-label="Settings sections"` | `General & registration` · `Thread Intelligence` |
| **Tab A — General & registration** (:48-90), 2-column grid | | |
| D6 | `<section>` H2 (:51) | `Identity` |
| D7 | intro (:52) | `The name the council goes by — in the topbar, in every email, and on the sign-in page.` |
| D8 | field label (:55) | `Community name` (input `maxlength="80"`) |
| D9 | submit (:59) | `Save name` |
| D10 | success chip (:60) `role="status"` | `Saved.` |
| D11 | error (:61) `role="alert"` | `The council needs a name.` |
| D12 | `<section>` H2 (:67) | `Registration` |
| D13 | intro (:68) | `Choose whether new members can join directly, need an invitation, or cannot register.` |
| D14 | field label (:71) | `Registration mode` |
| D15 | options (:73-75) | `Open — anyone can register` / `Invite only (invitation required)` / `Closed (no new sign-ups)` |
| D16 | help (:77) | `Existing members can continue signing in in every mode.` |
| D17 | conflict banner (:80) `role="alert"` | `Registration mode is “invite” but the invitations feature is off — registration is effectively closed.` |
| D18 | checkbox (:82) | `Invitations feature is enabled` |
| D19 | submit (:84) | `Save registration mode` |
| D20 | success chip (:85) | `Saved.` |
| **Tab B — Thread Intelligence** (:93-212) | | |
| D21 | intro paragraph (:95) | `Automated context for long topics. The council approves; the model proposes. Everything it writes is evidenced below, and the egress brake is one button away.` |
| D22 | status card 1 (:98-102), left rule `--success` | `Provider` / `Healthy` / `No latch set` |
| D23 | status card 2 (:103-107), left rule `--info` | `Heartbeat` / `Nominal` / `Last run 6 minutes ago` |
| D24 | status card 3 (:108-112), left rule `--warning` | `Generation` / `{{ generationState }}` / `Global provider egress brake` |
| D25 | `<section>` H2 (:116) | `Recovery controls` |
| D26 | buttons (:118-119) | `{{ pauseAction }}` (= `Pause generation` / `Resume generation`) · `Retry provider configuration` |
| D27 | status chip (:120) | `Health latch cleared.` |
| D28 | helper (:122) | `Provider retry clears only the current health latch. Configure credentials outside this page.` |
| D29 | `<section>` H2 (:126) | `Daily budget` |
| D30 | meter row 1 (:130-133) | `Calls` / `{{ callsLabel }}` / 8px pill track, `--accent` fill at `width: 67%` |
| D31 | meter row 2 (:137-140) | `Input tokens` / `{{ tokensLabel }}` / `--gold-500` fill at `width: 68%` |
| D32 | reset line (:143), mono | `Resets 2026-08-03 00:00 UTC` |
| D33 | `<section>` H2 (:147), lapidary-caps eyebrow skin | `Queue states` |
| D34 | 5-up queue grid (:148-156) | count → label → unit |
| D35 | `<section>` H2 (:161), 330px column | `Generation contract` |
| D36 | `<dl>` (:163-165) | `Model` / `Reasoning effort` / `Prompt version` |
| D37 | `<section>` H2 (:171), 1fr column | `Recent generation evidence` |
| D38 | segmented filter (:172-177) | `All` · `Failed only` |
| D39 | run count (:178) | `{{ evidenceLabel }}` (= `1 run` / `N runs`) |
| D40 | table head (:181-187) | `When` · `Topic` · `Outcome` · `Input tokens` · `Digest` |
| D41 | filtered empty (:203-208) | H3 `Nothing has failed`; P `Every generation in the retained window completed.` |

### Production order

`templates/admin/settings.php`

| # | Element | Verbatim string | Line |
| --- | --- | --- | --- |
| P1 | `.admin-head` eyebrow | `Operator desk` | :14 |
| P2 | H1 | `General & registration` | :15 |
| P3 | pill | `Admin mode` | :17 |
| P4 | `admin/_nav` partial (8-group vertical rail) | — | :20 |
| P5 | `.pane-intro` | `Manage the community name and who can create an account. Each form saves only its own setting.` | :23 |
| P6 | `section.card.settings-card` H2 | `Site name` | :26 |
| P7 | `.muted` | `Shown throughout the community and in system messages.` | :27 |
| P8 | `form → POST /admin/site` label | `Community name` (maxlength 80, `required`, `field_attrs`) | :28-34 |
| P9 | help | `Use 1–80 characters.` | :33 |
| P10 | `field_error()` | (renders `Site name must be 1–80 characters.`) | :35 |
| P11 | submit | `Save site name` | :36 |
| P12 | `section.card.settings-card` H2 | `Registration` | :41 |
| P13 | `.muted` | `Choose whether new members can join directly, need an invitation, or cannot register.` | :42 |
| P14 | `form → POST /admin/settings/registration` label | `Registration mode` | :43-47 |
| P15 | options | identical three strings to D15 | :51 |
| P16 | help | `Existing members can continue signing in in every mode.` | :56 |
| P17 | conflict `.field-error` | identical string to D17 | :58 |
| P18 | `field_error()` | (renders `Unknown registration mode.`) | :61 |
| P19 | submit | `Save registration mode` | :62 |

`templates/admin/thread_intelligence.php`

| # | Element | Verbatim string | Line |
| --- | --- | --- | --- |
| T1 | eyebrow | `Operations` | :6 |
| T2 | H1 | `Thread Intelligence` | :7 |
| T3 | pill | `Admin mode` | :9 |
| T4 | `admin/_nav` | — | :12 |
| T5 | *(no `.pane-intro`)* | — | — |
| T6 | conditional `section.card.ti-attention` H2 | `Needs attention` + `<ul>` of `$dashboard['warnings']` | :15-22 |
| T7 | `.admin-dashboard-grid` card 1 | `Product flags` / count / `community memory on·off · automated context on·off` | :25-29 |
| T8 | card 2 | `Provider` / `Ready`\|`Not ready` / `OpenAI · latched`\|`available` | :30-34 |
| T9 | card 3 | `Worker` / heartbeat classification / heartbeat status \| `never run` | :35-39 |
| T10 | card 4 | `Generation` / `Paused`\|`Running` / `Global provider egress brake` | :40-44 |
| T11 | `section.card.ti-controls` H2 | `Recovery controls` | :48 |
| T12 | two POST forms | `Resume generation` \| `Pause generation`; `Retry provider configuration` | :49-60 |
| T13 | helper `.muted` | identical string to D28 | :61 |
| T14 | `section.card.ti-budget` H2 | `Daily budget` | :70 |
| T15 | `<label>` + `<progress>` | `Calls N of N`; `Input tokens N of N` | :71-76 |
| T16 | `.muted` | `Resets <next_reset_at> UTC` | :77 |
| T17 | `section.admin-dashboard-grid aria-label="Queue states"` (**no visible heading**), 6 cards | label → count → `thread(s)` | :80-88 |
| T18 | `section.card` H2 | `Generation contract` | :91 |
| T19 | `<dl class="ti-metadata">` | `Model` / `Reasoning effort` / `Prompt version` (real values) | :92-96 |
| T20 | `section.card` H2 | `Recent generation evidence` | :100 |
| T21 | empty `.muted` | `No generation attempts have been recorded.` | :102 |
| T22 | `.table-scroll` + `table.audit` head | `ID` · `Thread` · `Status` · `Requested` · `Contract` · `Evidence` · `Actions` | :104-106 |
| T23 | per-row `<details class="ti-evidence">` | summary `Redacted details` + trigger/retry/window, failure, sources, candidates, usage | :122-137 |
| T24 | per-row `.ti-actions` (3 POST forms) | `Retry` · `Reconcile` · `Pause`\|`Resume` | :139-143 |

`templates/admin/features.php` — eyebrow `Runtime controls` (:6), H1 `Feature flags` (:7), pill (:9),
nav (:12), long `.pane-intro` (:15), corrupt warning (:17-19), 4-card stat grid (:21-42),
per-group `section.card` + `table.audit.audit-flags` (:44-86), `section.card` H2 `Unknown overrides` (:88-109).
**No design counterpart.**

### Order verdict

The Thread Intelligence *content* order is a near-exact match once the design's 3 status cards are
reconciled with production's 4 and the `Needs attention` panel is inserted:

```
design : intro → status rail → Recovery controls → Daily budget → Queue states → [Generation contract | Recent generation evidence]
prod   :   —   → [Needs attention] → status rail → Recovery controls → Daily budget → Queue states → Generation contract → Recent generation evidence
```

Only three ordering changes are needed: add the intro paragraph, keep `Needs attention` first
(feature-added), and put the contract + evidence side by side in a `330px 1fr` grid.

The General tab order matches exactly; only the two cards need to become a 2-up grid.

---

## 2. Difference table

Classification key: **copy** = production simply changes; **feature-added / feature-removed /
feature-changed / constraint** = sanctioned deviation.

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Topbar | constraint | Sticky 58px bar, elven-star SVG, wordmark `Imladris`, back link `Back to the council` (`AdminSettings.dc.html:22-28`) | `templates/layout.php` owns the app shell; the operator's own `$brand['name']` / `$brand['logo_path']` render there (`layout.php:27,37-40`) | Do not port the topbar. The design's bar is the prototype's chrome, not a page section. | low |
| 2 | Section nav | constraint | Two local tab `<button onClick={{ goGeneral }}>` (`:41-44`) driving client state; a single merged page | Two independent routes in the 8-group sticky vertical rail (`_nav.php:45-49`), pinned by `AppAdminDashboardRemediationTest:107,117` (destination list + `aria-current`) and locked by ADR 0023 item 6 / ADMIN §9.2 | Keep the vertical rail. Do NOT build a tab strip; the design's is a per-screen elision. Adopt the tab *skin* nowhere. | high |
| 3 | Page head H1 | constraint | `General & intelligence` (`:35`) — one H1 for a merged page | `General & registration` (`settings.php:15`) and `Thread Intelligence` (`thread_intelligence.php:7`), the first pinned by `AppAdminDashboardRemediationTest:220` (`assertSeeText($site, 'General & registration')`) | Keep both H1s. Consequence of item 2. | low |
| 4 | Page head eyebrow | copy | `Operator desk · Settings` (`:34`) | `Operator desk` (`settings.php:14`); `Operations` (`thread_intelligence.php:6`) | Adopt `Operator desk · Settings` on settings.php. Leave TI's `Operations` (or promote to `Operator desk · Thread Intelligence` — same register). Also add the missing eyebrow skin: design is `.68rem` in `var(--gold-ink)` at `.18em`; `app.css:37 .eyebrow` is `.72rem` in `var(--text-muted)`. | low |
| 5 | Head pill | — (match) | `Admin mode` (`:37`) | `Admin mode` (`settings.php:17`, `thread_intelligence.php:9`) | None. | — |
| 6 | General layout | copy | `grid-template-columns: repeat(2, 1fr); gap: 16px; align-items: start` (`:49`) | Two stacked `.card.settings-card` (`app.css:3095` caps them at `max-width: 720px`) | Author a 2-up grid class; drop/override the 720px cap inside it. | low |
| 7 | Identity card H2 | copy | `Identity` (`:51`) | `Site name` (`settings.php:26`) | Rename to `Identity`. Update `aria-labelledby="site-name-heading"` id text only if needed (id may stay). | low |
| 8 | Identity intro | copy + fiction | `The name the council goes by — in the topbar, in every email, and on the sign-in page.` (`:52`) | `Shown throughout the community and in system messages.` (`settings.php:27`) | Adopt the de-fictionalised string (see §3). | low |
| 9 | Community name help | feature-added | absent | `Use 1–80 characters.` wired via `field_attrs(..., 'site-name-help')` → `aria-describedby` (`settings.php:32-33`) | KEEP. ADR 0023 item 5 accessibility wiring. Style as the design's `--text-faint` help line. | low |
| 10 | `required` attribute | feature-added | absent | `required` on the input (`settings.php:32`) | KEEP. | low |
| 11 | Save button label | copy | `Save name` (`:59`) | `Save site name` (`settings.php:36`) | Rename to `Save name`. Verify no test pins the old label (none found). | low |
| 12 | Save confirmation | constraint | Inline `role="status"` chip `Saved.` held in client state (`:60`) | PRG: `redirectWithFlash('/admin/settings', 'Site name updated.')` (`AdminSettingsController.php:37`), rendered by `partials/flash` (`layout.php:61`) | Keep PRG. The flash IS the equivalent. Do not add a JS-only chip; a chip that only appears with JS is not a save affordance. Style the flash in the design's `--on-done` register. | medium |
| 13 | Name validation message | copy + fiction | `The council needs a name.` (`:61`) | `Site name must be 1–80 characters.` (`AdminSettingsService.php:72`), rendered by `field_error()` (`settings.php:35`), pinned by `AppAdminDashboardRemediationTest:221` | KEEP the production string (it states the real rule; the design's omits the 80-char bound and is fiction). Adopt the design's `role="alert"` + `--danger` skin. | low |
| 14 | Registration card | — (match) | H2 `Registration`, intro, `Registration mode` label, three option strings, help line (`:67-77`) | identical strings (`settings.php:41-56`) | None. Verbatim match already. | — |
| 15 | Invite-conflict banner | copy | Same string, but rendered as a `role="alert"` rust-wash card with a 3px `--rust` left rule (`:80`) | Same string as a bare `<span class="field-error">` inside the label (`settings.php:58`) | Adopt the callout anatomy (rust wash + left rule + `role="alert"`). New class in `app.css`. | low |
| 16 | `Invitations feature is enabled` checkbox | **feature-removed** | Checkbox toggling the `invitations` flag inside the registration form (`:82`) | No such control anywhere. `invitations_flag_on` is READ-ONLY (`AdminSettingsService.php:45`); enablement is a deliberate `settings.features` write (`AdminFeatureController.php:54-64`; `docs/runbooks/operations.md` §2) | **Do NOT build.** A flag toggle here reverts the no-toggles decision and would let one form write two unrelated settings — exactly what the `obsoleteCombinedUpdate` tombstone exists to prevent. Record as a gap. | high |
| 17 | Form targets | constraint | `onSubmit={{ saveName }}` / `onSubmit={{ saveRegistration }}` — no `action`, no method, no CSRF (`:53,69`) | `POST /admin/site` and `POST /admin/settings/registration` (`settings.php:28,43`), each with `$this->csrfField()`. `POST /admin/settings` is a 404 tombstone (`AdminSettingsController.php:57-67`), pinned by `AppAdminDashboardRemediationTest::test_obsolete_combined_settings_post_is_not_routable` (:64-75), and `test_dashboard_carries_no_settings_or_emoji_forms` asserts `action="/admin/settings"` never appears (:282) | Two separate `<form method="post">` with distinct actions and CSRF. Never merge them; never post to `/admin/settings`. | high |
| 18 | 422 draft round-trip | feature-added | No server round-trip exists | `settings_errors` / `settings_old` re-render at 422 (`AdminSettingsController.php:31-34,48-51`), pinned by `AppAdminDashboardRemediationTest:213-227` and `AppFieldErrorA11yTest:157,168` | Any restructure MUST preserve `$old['site_name']`, `$old['registration_mode']`, and the unknown-mode `<option value="banana" selected>` fallback (`settings.php:48-50`). | high |
| 19 | TI intro paragraph | copy + fiction + accuracy | `Automated context for long topics. The council approves; the model proposes. Everything it writes is evidenced below, and the egress brake is one button away.` (`:95`) | No `.pane-intro` on the TI page at all | Add an intro. **Rewrite, do not just de-fictionalise**: ADR 0019 §2 says "A validated result may publish **without** per-generation human approval", so "The council approves; the model proposes" is factually false. Proposed: `Automated context for long topics. Staff set the terms; the model proposes and local validation decides. Everything it writes is evidenced below, and the egress brake is one button away.` | medium |
| 20 | `Needs attention` panel | feature-added | absent | `section.card.ti-attention` fed by `ThreadIntelligenceAdminService::warnings()` (`thread_intelligence.php:15-22`; service :150-191) — 10 distinct warning conditions incl. corrupt flags, missing credential, latched provider, corrupt budget, stale/interrupted worker, dead/review queues, config warnings | KEEP, first in the pane. Style in the design's rust/gold callout idiom. This is the only surface for `flags_corrupt` and `configuration_warnings`. | medium |
| 21 | Status rail card count | feature-added | 3 cards (`:98-112`) | 4 cards — production adds `Product flags` (`thread_intelligence.php:25-29`) | KEEP the 4th card; the flags pair is the rollback lever (ADR 0019 §1) and there is no other surface for it on this page. Grid becomes 4-up. | low |
| 22 | Provider card fields | feature-changed | `Provider` / `Healthy` / `No latch set` — one fact (`:98-102`) | `Provider` / `Ready`\|`Not ready` (credential) / `OpenAI · latched`\|`available` (latch) — two independent facts (`thread_intelligence.php:30-34`) | Design wins on presentation (label → value → detail, coloured left rule). Production wins on behaviour: credential readiness and latch state are separate warnings (`AdminService:159,167-168`) and must both stay legible. | medium |
| 23 | Heartbeat card | copy | `Heartbeat` / `Nominal` / `Last run 6 minutes ago` (`:103-107`) | `Worker` / classification (`never_run`\|`invalid`\|`running`\|`interrupted`\|`attention`\|`stale`\|`healthy`, `OperationsService.php:212-231`) / raw `status` or `never run` (`thread_intelligence.php:35-39`) | Rename to `Heartbeat`; humanise the classification; render the detail as a relative time from `heartbeat['completed_at']` via `human_datetime()` (`src/Support/helpers.php:65`) — the field is already in the read model (`ThreadIntelligenceSettings.php:226-236`). Do NOT ship the literal `Nominal`. | low |
| 24 | Generation card | — (match) | `Generation` / `Paused`\|`Running` / `Global provider egress brake` (`:108-112`) | identical (`thread_intelligence.php:40-44`) | None. | — |
| 25 | Status-rule colour | copy (**live defect**) | Per-card left rule: `--success` / `--info` / `--warning` (`:98,103,108`) | All four cards are `class="card queue-card is-static"` with **no** status modifier, so `app.css:2985-2991 .queue-card::before` paints every one `--success` — a `Not ready` provider and a `Paused` generation both show a green rule | Add per-state modifiers. `app.css` already has `.queue-status-attention` (rust) and `.queue-status-unavailable` (faint); add an info/warning register and drive it from `credential_ready`, `provider.blocked`, `heartbeat.classification`, `pause.paused`. **Fix before the visual pass** — currently the rail asserts health it has not verified. | medium |
| 26 | Recovery controls | — (match) | H2 `Recovery controls`, `Pause generation`/`Resume generation`, `Retry provider configuration`, helper `Provider retry clears only the current health latch. Configure credentials outside this page.` (`:116-122`) | identical strings (`thread_intelligence.php:48,51,55,59,61`) | None on copy. Adopt the design's outline-button skin (`1.5px solid var(--border-soft)`, transparent ground). | low |
| 27 | `Health latch cleared.` chip | constraint | Inline `role="status"` after the retry click (`:120`) | Flash after PRG: `Provider configuration will be retried.` (`AdminThreadIntelligenceController.php:43`) | Keep PRG. Note the production string is more honest (the retry queues a re-check; it does not assert health). | low |
| 28 | Daily budget meters | constraint | `<div style="height:8px;border-radius:999px;background:var(--surface-sunken)">` wrapping a fill div at `width: 67%` (`:133,140`) | `<progress max value>` (`thread_intelligence.php:72,75`) styled at `app.css:5680` | CSP forbids the inline `style="width:…"`. Keep `<progress>` and skin it to the 8px pill (track `--surface-sunken`; `::-webkit-progress-value` / `::-moz-progress-bar` fills `--accent` for calls, `--gold-500` for tokens). Never write width via an inline attribute. | low |
| 29 | Budget number formatting | copy | `268 of 400`; `612,480 of 900,000` (thousands separators, mono) (`:131,138` + x-dc `:276-277`) | raw ints, no separators, label not mono (`thread_intelligence.php:71,74`) | Adopt: `number_format()` + `--font-mono` value on the right of a baseline-aligned row. | low |
| 30 | Budget reset line | copy | `Resets 2026-08-03 00:00 UTC`, mono `.78rem` `--text-faint` (`:143`) | `Resets <Y-m-d H:i:s> UTC` (`thread_intelligence.php:77`; `Budget.php:64`) | Adopt the mono/faint skin; trim seconds. | low |
| 31 | Budget corrupt/exhausted | feature-added | absent | `budget['corrupt']` and `budget['exhausted']` exist (`Budget.php:56,63,65`); `corrupt` surfaces only via `Needs attention` | KEEP (via item 20). Optionally add an exhausted state to the meter — but only if backed by `exhausted`, not invented. | low |
| 32 | `Queue states` heading | copy | Visible H2 in the lapidary-caps eyebrow skin (`.68rem`, `.16em`, uppercase, `--text-faint`) (`:147`) | `aria-label="Queue states"` only — **no visible heading** (`thread_intelligence.php:80`) | Add the visible heading; keep the accessible name. | low |
| 33 | Queue state set | feature-changed | 5 product-language states: `Pending`, `In flight`, `Summarised`, `Refused`, `Failed` (x-dc `:279-285`) | 6 job states from `ThreadIntelligenceOperationsService::JOB_STATES` (:17) — `idle`, `queued`, `running`, `retry`, `dead`, `review_required` — rendered `ucfirst` + `_`→space (`thread_intelligence.php:83`) | Production's vocabulary wins (they are the real `thread_intelligence_jobs.state` enum, migration `0077_thread_intelligence.php:25`). Grid becomes 6-up / auto-fit. Adopt the design's card anatomy. `Review required` may keep its space-substituted label. | medium |
| 34 | Queue card anatomy | copy | count first (mono `1.45rem`) → label (`.68rem`/`.08em`) → unit (`.78rem`) (`:151-153`) | label (`.queue-card-head`) → count (`.queue-card-count`, `--font-display` `2.1rem`) → detail (`.queue-card-detail`) (`thread_intelligence.php:83-85`; `app.css:3002-3018`) | Reorder to count-first for the queue grid only. **Do not** change `.queue-card` globally — it is shared with `/admin` and `/admin/features`. Author a distinct class (e.g. `.ti-queue-tile`). Note the design uses label-first for the *status* rail and count-first for the *queue* grid: two anatomies, not one. | medium |
| 35 | Contract + evidence layout | copy | `grid-template-columns: 330px 1fr; gap: 16px; align-items: start` (`:159`) | Two stacked full-width `section.card` (`thread_intelligence.php:90,99`) | Adopt the two-column grid; collapse to one column at the existing `860px` admin breakpoint. | low |
| 36 | Generation contract `<dl>` | — (match) | `Model` / `Reasoning effort` / `Prompt version` (`:163-165`) | identical labels in `<dl class="ti-metadata">` (`thread_intelligence.php:92-96`) | None on structure. Adopt the design's uppercase `<dt>` + mono `<code>` `<dd>` skin. | low |
| 37 | Generation contract VALUES | constraint | Hard-coded literals `claude-sonnet-4-6`, `medium`, `ti.summary.v7` (`:163-165`) | Real seam values: `$dashboard['model']` = `ThreadIntelligenceConfig::model()` (default `gpt-5.6-luna`, `Config.php:21`), `reasoning_effort` (default `low`, `Config.php:22`), `prompt_version` = `ThreadIntelligencePromptBuilder::VERSION` = `thread-intelligence-v1` (`PromptBuilder.php:18`) | Values MUST render from the validated config seam (DECISIONS §2 replaceable interfaces; ADMIN §3.10). Never transcribe the literals. This is conflict **C7** from F2. | high |
| 38 | Evidence heading | — (match) | `Recent generation evidence` (`:171`) | identical (`thread_intelligence.php:100`) | None. | — |
| 39 | Evidence filter `All` / `Failed only` | **feature-removed** | Segmented pill control filtering client-side (`:172-177`, x-dc `:243,287-290`) | No filter. `ThreadIntelligenceGenerationRepository::recent()` (:205-212) is an unfiltered `ORDER BY id DESC LIMIT n` | **Do not build in the adoption pass.** PE forbids client filtering, so it needs a real `?evidence=failed` query param, a repository predicate, and a decision on which of the 9 statuses count as failed (`failed`, `dead`, `rejected`, `review_required`, `stale`?). File as an owned follow-on with the status set named. Record as a gap. | medium |
| 40 | Evidence run count | copy | `{{ evidenceLabel }}` → `1 run` / `8 runs`, right-aligned `--text-faint` (`:178`, x-dc `:292`) | absent | Adopt: `count($dashboard['recent_generations'])` (already bounded to 50 by `dashboard(int $recentLimit = 50)`). | low |
| 41 | Evidence column `When` | copy | mono `.77rem` `--text-faint`, `2 Aug 09:12` (`:191`) | `Requested` column: `<?= $e($generation['requested_at']) ?> UTC` (`thread_intelligence.php:119`) | Adopt the mono/faint skin and short format; keep an absolute UTC value available (title attr or full string) — operators diff against logs. | low |
| 42 | Evidence column `Topic` | constraint | Raw thread title for every row (`:192`) | Visibility-gated: `publicThreadLink()` only resolves non-deleted, non-pending threads in `visibility='public'` boards (`AdminService.php:230-241`); otherwise the cell renders `Thread #<id>` with no title (`thread_intelligence.php:112-116`) | KEEP the gate. Adopting the design's plain title column verbatim would leak titles from private/hidden boards into the admin console. Style the link + the bare-id fallback in the same idiom. | high |
| 43 | Evidence column `Outcome` | feature-changed | Binary pill: green `Summarised` / rust `Refused — low signal`, `Failed — provider timeout` (`:194-195`, x-dc `:219-226`) | 9-value enum (`requested`,`succeeded`,`published`,`retry`,`failed`,`dead`,`review_required`,`rejected`,`stale`; migration `0077:58`) rendered as `.state state-active`\|`state-pending` — only two visual registers (`thread_intelligence.php:118`) | Production's status set wins. Adopt the design's pill skin but with THREE registers: done (`--surface-done`/`--on-done`) for `published`/`succeeded`; neutral for `requested`/`retry`/`stale`; rust for `failed`/`dead`/`rejected`/`review_required`. Today `dead` and `rejected` both render as `state-pending` — misleading. | medium |
| 44 | Evidence column `Input tokens` | copy | Right-aligned mono column (`:197`) | Present but buried inside the `<details>` disclosure: `Usage: input …` (`thread_intelligence.php:136`); the value is already in the read model as `usage.input_count` (`AdminService.php:221`) | Promote to a right-aligned mono column. No new data needed. | low |
| 45 | Evidence column `Digest` | **constraint** | `<code>sha256:4b1e…c802</code>` column (`:198`) | Deliberately redacted. `recent()` does `SELECT *` so `request_fingerprint` (migration `0077:66`) IS fetched, but `safeGeneration()` (`AdminService.php:194-227`) drops it along with `provider_response_id` and `source_snapshot_hash`. **`AppAdminThreadIntelligenceTest.php:63` asserts `assertStringNotContainsString($requestFingerprint, $page->body())`** | **Do NOT add the column.** It would break a pinned redaction invariant (ADR 0019 §5/§7: fingerprints stay in the ledger, not the surface). Drop the column from the port. | high |
| 46 | Evidence columns `ID` / `Contract` | feature-added | absent | `#<id>` (`thread_intelligence.php:110`) and a per-row `Contract` cell showing that row's model / effort / prompt version (`:120`) — pinned by `AppAdminThreadIntelligenceTest:60-61` (`admin-safe-model`, `prompt-v1` must render) | KEEP both. The per-row contract is how an operator sees a contract change mid-window; the page-level contract card only shows *current* config. | high |
| 47 | Evidence `<details>` disclosure | feature-added | absent | `<details class="ti-evidence"><summary>Redacted details</summary>` — trigger code, retry number, window number, failure code + message, public source-post links, public candidate-thread links, four usage counters (`thread_intelligence.php:122-137`) | KEEP. Mandated by ADR 0019 §5/§7 and pinned by `AppAdminThreadIntelligenceTest:62` (`Post #<id>` must render). Style the summary in the idiom. | high |
| 48 | Evidence per-row actions | feature-added | absent | `.ti-actions` with three CSRF-protected POST forms: `Retry`, `Reconcile`, `Pause`\|`Resume` (`thread_intelligence.php:139-143` → `AdminThreadIntelligenceController::retryThread/reconcileThread/pauseThread/resumeThread`, App.php:2308-2311), audited (`AdminService.php:115-123,131-138`) | KEEP. ADR 0019 §3 curator control. Style as link-buttons in the design idiom. | high |
| 49 | Evidence table scroll region | feature-added | absent (design table is a bare `<table>`) | `<div class="table-scroll" tabindex="0" role="region" aria-label="Recent redacted generation attempts">` (`thread_intelligence.php:104`) — ADR 0023 item 5; the mobile Playwright spec scrolls it (`tests/browser/thread-intelligence.spec.ts:39-42`) | KEEP. A 7-column table cannot be responsive without it. | medium |
| 50 | Evidence empty state | copy | Centred H3 `Nothing has failed` + P `Every generation in the retained window completed.` (`:203-208`) — this is the *filtered* empty state | `<p class="muted">No generation attempts have been recorded.</p>` (`thread_intelligence.php:102`) | Adopt the centred H3+P anatomy with the truthful unfiltered copy. The design's strings belong to the `Failed only` filter (item 39) and must not be used for the unfiltered case. | low |
| 51 | Loading state | constraint | none in this screen (unlike AdminOverview) | none | Nothing to do — noted for completeness. Server-rendered pages have no loading state. | low |
| 52 | Feature flags screen | **feature-added** | No third tab; `/admin/features` is entirely unmodelled | `templates/admin/features.php` (111 lines) + `AdminFeatureController` (279 lines): 4-card stat grid, 7 grouped tables, readiness classification, unknown-override table, `Operations` deep links | KEEP. Style in the idiom (same stat-card, table and callout classes as the other two). It MUST stay read-only — no toggles (`AdminFeatureController.php:54-64`; ADR 0021 deferral #7). | medium |
| 53 | TI route flag gate | feature-added (deliberate) | n/a | `AdminThreadIntelligenceController::index` calls only `requireAdmin()` — **no flag guard**, and this is intentional: `AppAdminThreadIntelligenceTest::test_dashboard_is_admin_only_readable_with_flags_off_…` (:29,55-57) asserts a 200 with both flags off, and `Both product flags are off` renders as a warning (:58). `_nav.php:48` hides the *link* via `flags_any` | KEEP as-is. Correcting F3's headline: this is a rollback-reachability decision, not drift. The console must stay reachable to unwind a rollback. | low |
| 54 | `noindex` on TI | feature-added | n/a | `$this->noindex(...)` (`AdminThreadIntelligenceController.php:17`) | KEEP. | low |
| 55 | Inline styles / helmet `<style>` | constraint | ~200 inline `style=`/`style-hover=` attributes plus a `<helmet><style>` with `@keyframes asRise` (`:13-17`) | Zero inline styles anywhere in `templates/` | All CSS lands as external classes in `public/assets/app.css` (unlayered — it beats the layered `imladris.css`; see F1). Never `!important`; never re-declare a design token in `app.css :root`. Any new semantic token must be added in three places (see F1). | high |
| 56 | Build tripwire | constraint | n/a | Editing `templates/**` or `public/assets/*` breaks `composer build:imladris` / `check:imladris` / `verify:imladris` until `config/imladris-runtime-baseline.json → application_surface.sha256` is refreshed | Every slice ends with `php bin/build-imladris-assets.php --print-application-digest` → paste → `composer verify:imladris`. | medium |

**Counts:** copy 24 · feature-added 13 · feature-removed 2 · feature-changed 3 · constraint 11
(rows 5, 14, 24, 26, 36, 38 are exact matches and are not counted as differences).

---

## 3. Fiction strings

| # | Design string (path:line) | Proposed production string |
| --- | --- | --- |
| F1 | `Imladris` — topbar wordmark (`:25`) | Do not port. `templates/layout.php:27` renders `$brand['name']` (the operator's own site name). |
| F2 | Eight-point elven star SVG (`:24`) | Do not port. `layout.php:37-40` uses `$brand['logo_path']` / the favicon. |
| F3 | `Back to the council` (`:27`) | `Back to the forum` — but the app shell already provides navigation; do not add a bespoke back link to these pages. |
| F4 | `The name the council goes by — in the topbar, in every email, and on the sign-in page.` (`:52`) | `The name this community goes by — in the topbar, in every email, and on the sign-in page.` |
| F5 | `The council needs a name.` (`:61`) | Keep production's `Site name must be 1–80 characters.` (`AdminSettingsService.php:72`) — it states the real rule and is pinned by `AppAdminDashboardRemediationTest:221`. |
| F6 | `Automated context for long topics. The council approves; the model proposes. Everything it writes is evidenced below, and the egress brake is one button away.` (`:95`) | `Automated context for long topics. Staff set the terms; the model proposes and local validation decides. Everything it writes is evidenced below, and the egress brake is one button away.` (ADR 0019 §2 forbids claiming per-generation human approval.) |
| F7 | `claude-sonnet-4-6` (`:163`) | Render `$dashboard['model']` from `ThreadIntelligenceConfig::model()` (default `gpt-5.6-luna`). |
| F8 | `medium` (`:164`) | Render `$dashboard['reasoning_effort']` (default `low`). |
| F9 | `ti.summary.v7` (`:165`) | Render `$dashboard['prompt_version']` = `ThreadIntelligencePromptBuilder::VERSION` (`thread-intelligence-v1`). |
| F10 | `Nominal` — heartbeat value (`:105`) | Render the real classification: `Healthy` / `Running` / `Stale` / `Interrupted` / `Needs attention` / `Never run` / `Invalid` (`OperationsService.php:212-231`). |
| F11 | `Healthy` / `No latch set` — provider card (`:100-101`) | Render `Ready` / `Not ready` plus `latched` / `available` (production already does; see diff row 22). |
| F12 | `Last run 6 minutes ago` (`:106`) | `human_datetime($dashboard['heartbeat']['completed_at'])` (`src/Support/helpers.php:65`). |
| F13 | `Summarised` / `Refused — low signal` / `Failed — provider timeout` (x-dc `:219-226`) | The real 9-value status enum from `thread_intelligence_generations.status`. |
| F14 | `sha256:4b1e…c802` and the whole `Digest` column (`:186,198`) | No equivalent. Forbidden — `request_fingerprint` is asserted absent from the response body (`AppAdminThreadIntelligenceTest:63`). |
| F15 | Sample topics — `Against the heroic on-call rotation`, `Evaluations as ritual, not gate`, `A smaller composer`, … (x-dc `:219-226`) | Sample data only; production renders real titles through `publicThreadLink()`'s visibility gate. |
| F16 | `Resets 2026-08-03 00:00 UTC` (`:143`) | `Resets <?= $budget['next_reset_at'] ?> UTC` (already real; the date is sample data). |

No `warden`, `regard`, `commend`, `counsel` or `hall` occurrences in this screen — its fiction load is
lighter than the account/moderation screens. But **F4 and F6 are the two strings that would ship
"council" into production if copied verbatim.**

---

## 4. State inventory

| Design state (verbatim) | Where | Production equivalent | Verdict |
| --- | --- | --- | --- |
| `Saved.` (name) | `:60`, x-dc `:258` | Flash `Site name updated.` after 302 to `/admin/settings` (`AdminSettingsController.php:37`) | constraint (PRG). Equivalent exists. |
| `Saved.` (registration) | `:85`, x-dc `:267` | Flash `Registration settings updated.` (`AdminSettingsController.php:54`) | constraint (PRG). Equivalent exists. |
| `The council needs a name.` | `:61`, x-dc `:257` | `Site name must be 1–80 characters.` at 422 via `field_error()` + `aria-describedby` + `aria-invalid` | copy — production's is better; keep it. |
| *(no design equivalent)* | — | `Unknown registration mode.` at 422, with the rejected value preserved as `<option value="banana" selected>` | feature-added — keep. |
| `Registration mode is “invite” but the invitations feature is off — registration is effectively closed.` | `:80`, x-dc `:266` | identical string, same trigger (`settings.php:57-59`) | exact match. Adopt the callout skin only. |
| `Running` / `Paused` (generation) | x-dc `:270` | `!empty($dashboard['pause']['paused']) ? 'Paused' : 'Running'` (`thread_intelligence.php:42`) | exact match. |
| `Pause generation` / `Resume generation` | x-dc `:271` | identical (`thread_intelligence.php:51,55`) | exact match. |
| `Health latch cleared.` | `:120`, x-dc `:273` | Flash `Provider configuration will be retried.` (`AdminThreadIntelligenceController.php:43`) | constraint (PRG) + copy — production's wording is more honest; keep it. |
| `268 of 400` / `612,480 of 900,000` | x-dc `:276-277` | `Calls N of N` / `Input tokens N of N`, unformatted (`thread_intelligence.php:71,74`) | copy — add `number_format()`. |
| Queue counts `12 / 3 / 486 / 24 / 2` over 5 labels | x-dc `:279-285` | Six real `GROUP BY state` counts (`OperationsService.php:36-43`), zero-filled from `JOB_STATES` | feature-changed — real states win. |
| `All` / `Failed only` filter, `8 runs` / `1 run` | `:172-178`, x-dc `:287-292` | No filter; no count | filter = feature-removed (gap); count = copy (add). |
| `Nothing has failed` / `Every generation in the retained window completed.` | `:203-208`, x-dc `:293` | `No generation attempts have been recorded.` (unfiltered only) | copy on anatomy; the design's strings belong to the filter and are dropped with it. |
| *(no design equivalent)* | — | `Needs attention` panel: `Both product flags are off; generation remains dark.` · `Feature flag configuration is invalid; code defaults are in effect.` · `Provider credential is missing.` · `The global generation pause value is invalid and fails paused.` · `Provider health state is invalid and fails blocked.` · `Provider configuration is latched after <code>.` · `Daily budget state is invalid and generation is paused.` · `Worker appears interrupted.` · `Worker heartbeat is stale.` · `Worker reported an error and needs attention.` · `Worker heartbeat state is invalid.` · `N dead thread(s) need recovery.` · `N thread(s) requires review.` + `configuration_warnings` (`AdminService.php:150-191`) | feature-added — 14 states with no design home. Keep all. |
| *(no design equivalent)* | — | `never run` heartbeat detail (`thread_intelligence.php:38`) | feature-added — keep. |
| *(no design equivalent)* | — | `Thread #<id>` fallback when the thread is private/deleted/pending (`thread_intelligence.php:115`) | constraint (visibility) — keep. |
| *(no design equivalent)* | — | Per-row `Redacted details` disclosure + `Retry`/`Reconcile`/`Pause`/`Resume` outcomes flashed from `ThreadIntelligenceQueueResult->message` | feature-added — keep. |
| *(no design equivalent)* | — | `POST /admin/settings` → **404** (`obsoleteCombinedUpdate`) | constraint — keep the tombstone. |
| *(no design equivalent)* | — | Feature-flags screen states: `Effective on/off`, `Default on/off`, `Override on/off`, `No override`, `Missing user UI`, `Missing admin operations`, `Safety-blocked`, `Operational configuration required`, `Reserved (ADR 0018)`, `The settings.features value is not a JSON object…`, `No undeclared keys are present in settings.features.` | feature-added — unrepresented screen; keep every state. |

---

## 5. Why `POST /admin/settings` is a 404 tombstone

`AdminSettingsController::obsoleteCombinedUpdate` (:57-67) carries the reason in its own docblock:

> *"Method-specific tombstone: Router reports a path-only method miss as 405, while stale combined
> settings forms must fail as a non-endpoint. No submitted setting is read or written here."*

History: `docs/history/admin-dashboard-ui-remediation-2026-07-18.md:16` — *"One combined settings POST
could overwrite unrelated keys through defaulted fields."* A single form posting site name +
registration + anti-abuse meant a defaulted field silently clobbered a setting the operator never
touched. The remediation split ownership three ways (`/admin/site`, `/admin/settings/registration`,
`/admin/moderation`), each validating and persisting only its own key with its own precise
before/after audit (`AdminSettingsService.php:66-104`). The plan explicitly says
*"Remove `POST /admin/settings`; do not add a compatibility alias"*
(`docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md:16`).

**Consequence for adoption:** the design's two `onSubmit` handlers must become two `<form
method="post">` with *different* actions. Because the design puts both cards in one grid, the naive
port is a single wrapping form — which would post to the tombstone and 404. `AppAdminDashboardRemediationTest:281-282`
already asserts that `action="/admin/site"` and `action="/admin/settings"` never appear on `/admin`;
the mirror assertion for `/admin/settings` (that exactly two forms with two distinct actions exist)
is worth adding.

---

## 6. Are the budget meters and generation contract backed by real data?

**Yes — both are real; only the design's literal values are fiction.**

- **Budget:** `ThreadIntelligenceBudget::status()` (:37-67) reads the `settings` counter row, returns
  `used_calls`, `reserved_calls`, `used_input_tokens`, `reserved_input_tokens`, `call_limit`
  (default 100, `Config.php:23`), `input_token_limit` (default 1,000,000, `Config.php:24`),
  `exhausted`, `next_reset_at`, `corrupt`. The template already sums used+reserved
  (`thread_intelligence.php:66-67`). **Real. Keep the meters.**
- **Queue states:** real `GROUP BY state` over `thread_intelligence_jobs`
  (`OperationsService.php:36-43`). **Real, but 6 states not 5.**
- **Heartbeat:** real, from the `settings` heartbeat record with a computed classification
  (`OperationsService.php:212-231`). **Real, but the design's `Nominal` is not in the vocabulary.**
- **Generation contract:** real, from the validated config seam
  (`OperationsService.php:58-60`). **Real; the three literals are fiction (F7-F9).**
- **`Digest` column:** the *column* is not backed — `safeGeneration()` deliberately omits
  `request_fingerprint`, and a test asserts it never reaches the body. **Feature-removed by
  constraint; drop the column.**
- **`Failed only` filter:** not backed — `recent()` is unfiltered. **Feature-removed; file as a
  follow-on.**

---

## 7. Slice proposal

Each slice is independently shippable, independently testable, and ends with a runtime-digest
refresh. All CSS lands in `public/assets/app.css` (unlayered; see F1) — never in
`public/assets/imladris.css` or `docs/design-system/imladris/components.css`.

### S1 — General & registration: idiom pass
**Touches:** `templates/admin/settings.php`, `public/assets/app.css`, `config/imladris-runtime-baseline.json`.
**Does:** 2-up card grid (diff 6); `Site name` → `Identity` (7); intro rewrite (8, F4); `Save site
name` → `Save name` (11); invite-conflict banner becomes a rust-wash `role="alert"` callout (15);
eyebrow `Operator desk · Settings` + the gold-ink eyebrow skin (4); design's field/label/input skin.
**Preserves:** both distinct form actions + CSRF (17); `field_attrs`/`field_error`/`aria-describedby`
(9); `required` (10); the 422 `settings_old` round-trip and the unknown-mode `<option … selected>`
fallback (18). **Refuses:** the `Invitations feature is enabled` checkbox (16).
**Tested by:** `AppAdminDashboardRemediationTest::test_each_settings_validation_failure_renders_its_owner_with_draft`,
`::test_obsolete_combined_settings_post_is_not_routable`, `::test_shared_admin_navigation_…`;
`AppFieldErrorA11yTest:157,168`; a new assertion that exactly two forms exist with actions
`/admin/site` and `/admin/settings/registration`; Playwright desktop+mobile screenshot and a
`javaScriptEnabled: false` context saving each form independently.

### S2 — Thread Intelligence status rail, recovery controls, budget
**Touches:** `templates/admin/thread_intelligence.php`, `public/assets/app.css`, baseline.
**Does:** add the intro paragraph (19, F6); keep `Needs attention` first and style it (20); 4-up
status rail with per-state left rules (21, 22, 25 — **fixes the always-green defect**); rename
`Worker` → `Heartbeat` with a humanised classification and a relative last-run time (23, F10/F12);
outline-button recovery row (26); budget meters skinned over `<progress>` with `number_format()` and
the mono reset line (28-30).
**Tested by:** `AppAdminThreadIntelligenceTest` (all cases — especially the flags-off 200 and the
credential/fingerprint non-disclosure asserts); a new test that a not-ready provider and a paused
generation render their non-success modifiers; `tests/browser/thread-intelligence.spec.ts` (`79-admin-thread-intelligence`
desktop+mobile) plus `npm run a11y`.

### S3 — Queue states section
**Touches:** `templates/admin/thread_intelligence.php`, `public/assets/app.css`, baseline.
**Does:** visible `Queue states` heading in the caps-eyebrow skin (32); a new count-first
`.ti-queue-tile` anatomy that does **not** disturb the shared `.queue-card` used by `/admin` and
`/admin/features` (34); 6-up auto-fit grid over the real `JOB_STATES` (33).
**Tested by:** a test asserting all six state labels render with zero-filled counts; visual diff of
`/admin` and `/admin/features` proving `.queue-card` is unchanged.

### S4 — Generation contract + evidence table
**Touches:** `templates/admin/thread_intelligence.php`, `public/assets/app.css`, baseline.
**Does:** `330px 1fr` grid collapsing at 860px (35); `<dl>` skin (36); table skin with the design's
caps `<th>` rule and hairline row borders; three-register outcome pills across the 9-status enum
(43); promote `Input tokens` to a right-aligned mono column from `usage.input_count` (44); run count
(40); centred empty state with truthful copy (50); mono/faint `When` column (41).
**Preserves:** contract values from the seam (37, F7-F9); the visibility-gated topic cell (42); the
`ID` and per-row `Contract` columns (46); the `<details>` disclosure (47); the three action forms
(48); `.table-scroll` (49). **Refuses:** the `Digest` column (45) and the `All`/`Failed only` filter (39).
**Tested by:** `AppAdminThreadIntelligenceTest` (`admin-safe-model`, `prompt-v1`, `Post #<id>` still
render; `$requestFingerprint` still absent); a new test that a `dead` row renders in the danger
register and a `published` row in the done register; mobile Playwright horizontal-scroll shot.

### S5 — Feature flags: idiom pass
**Touches:** `templates/admin/features.php`, `public/assets/app.css`, baseline.
**Does:** apply the same stat-card, table and callout classes authored in S2-S4 so the third Settings
page reads as one system (52). Keeps the screen read-only.
**Tested by:** `AppAdminFeaturesTest`; `tests/browser/admin-features.spec.ts`; an assertion that no
`<form>`, `<input type="checkbox">` or toggle control exists on the page.

### S6 — Decision record for the refusals
**Touches:** a new `docs/adr/0024-*.md` (next free number after 0023), `PHASE_5_STATUS.md`.
**Records:** (a) the invitations-flag checkbox is refused — enablement stays a `settings.features`
write; (b) the evidence `Digest` column is refused permanently — `request_fingerprint` is a pinned
redaction invariant under ADR 0019 §7; (c) the `All`/`Failed only` filter is deferred with the failed
status set named (`failed`, `dead`, `rejected`, `review_required`, `stale`) and a `?evidence=` param
sketch; (d) the design's local tab strip is refused in favour of the ADR 0023 §6 grouped rail; (e)
`/admin/features` has no design representation and is retained as feature-added; (f) `/admin/thread-intelligence`
answering 200 with both flags off is affirmed as deliberate rollback reachability, not drift.
**Tested by:** nothing to run; it is the deferral discipline required by PRODUCT_DESIGN §13 / ADR precedent.

**Order:** S1 and S5 are independent of the rest. S2 → S3 → S4 share the TI template and should land
in that order. S6 can land first (it costs nothing and unblocks the refusals in S1/S4).
