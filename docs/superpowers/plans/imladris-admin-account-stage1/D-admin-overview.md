# D — admin-overview: design vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-overview/AdminOverview.dc.html`
(markup lines 21–275; `<script type="text/x-dc">` state machine lines 277–405)

**Production targets read in full:**
- `C:/Users/htper/community-forums/templates/admin/dashboard.php` (118 lines)
- `C:/Users/htper/community-forums/templates/admin/audit.php` (124 lines)
- `C:/Users/htper/community-forums/src/Controller/AdminController.php:26-77` (`dashboard()`, `audit()`), `:506-512` (`dashboardView()`)
- `C:/Users/htper/community-forums/src/Service/AdminDashboardService.php` (the whole `summary()` model)
- `C:/Users/htper/community-forums/src/Service/AuditQueryService.php` (filter validation + paging)
- `C:/Users/htper/community-forums/src/Repository/ModerationLogRepository.php:47-140` (`recent`, `search`, `searchCount`, `searchFilters`)
- `C:/Users/htper/community-forums/templates/admin/_nav.php` (the locked 8-group rail)
- `C:/Users/htper/community-forums/public/assets/app.css` (`.card`:159, `.eyebrow`:37, `.admin-head`:2813, `.admin-pane`:2923, dashboard block :2936-3094, `.filter-*`:3126-3138, admin table block :3211-3277, mobile block :3278-3427)
- `C:/Users/htper/community-forums/src/Support/helpers.php:65-75` (`human_datetime`)
- Routes confirmed at `src/Core/App.php:2204` (`GET /admin`) and `:2210` (`GET /admin/audit`)
- Binding plan: `docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md`; ADR `docs/adr/0023-admin-console-audit-round-2.md` items 4 and 6

**Token check for this screen:** every `var(--…)` used by AdminOverview (`--presence`, `--surface-review`, `--on-review`, `--artifact-link`, `--focus-ring`, `--border-soft`, `--surface-sunken`, `--gold-ink`, `--radius-lg/md/sm`, `--shadow-sm/md`, `--font-mono/label/display`, `--rust`, `--amber`, `--success`) resolves in the generated `public/assets/imladris.css`. **No missing tokens on this screen** — the `--gold-050` gap reported by F1 is in admin-integrations / admin-members, not here.

---

## 1. Section order comparison

| # | Design (AdminOverview.dc.html, top→bottom) | Production |
|---|---|---|
| 1 | **Topbar** — elven-star SVG + `Imladris` wordmark + `‹ Back to the council` (:24-30) | none on the page; `templates/layout.php` owns the app shell/topbar |
| 2 | **Head** — eyebrow `Operator desk`, h1 `Admin console`, pill `Admin mode` (:35-41) | `dashboard.php:4-10` — identical strings, `.admin-head` |
| 3 | **Section subnav** — tabs `Dashboard` / `Audit log` + non-interactive span `Moderation · Content · People · Appearance · Notifications · Integrations · Settings` (:44-50) | `dashboard.php:12` → `admin/_nav.php` — 8 grouped sections, feature-aware, 224px sticky rail + mobile drawer |
| 4 | **Intro** — “Start with the live queues and health signals, then review what has changed across the council.” (:55) | `dashboard.php:15` `.pane-intro` — “Start with live queues and health signals, then review what has changed across the community.” |
| 5 | **Queue health** — eyebrow `Live operations`, h2 `Queue health`, right-side `Live` chip, 4 cards (:57-91) | `dashboard.php:17-36` — identical strings, `.status-legend`, `$queue_cards` (4 or 5) |
| 6 | **Needs attention** — eyebrow `Triage`, h2 `Needs attention`, count pill, list + empty state (:93-112) | `dashboard.php:38-61` — identical strings, `.attention-panel` |
| 7 | **Community today** — eyebrow `Community pulse`, h2 `Community today`, 4 cards (:114-128) | `dashboard.php:63-81` — identical strings, 2 cards |
| 8 | **Recent activity** — eyebrow `Audit trail`, h2 `Recent activity`, `View full audit log →`, 5-col table (:130-158) | `dashboard.php:83-116` — identical strings, plus a scroll-cue shell |
| 9 | *(audit tab)* **Intro** (:165) | `audit.php:21` (separate route, own head: eyebrow `Accountability`, h1 `Audit log`) |
| 10 | **Filter form card** — 6 fields, Apply/Reset, result label (:167-209) | `audit.php:22-63` — same 6 fields, same order, same placeholders |
| 11 | **Loading skeleton** (:211-219) | *none* |
| 12 | **Error state** (:222-228) | *none* |
| 13 | **Table card** (6 cols) + empty state (:230-261) | `audit.php:65-111` |
| 14 | **Pager** — Previous / `Page N of M` / Next (:263-269) | `audit.php:113-121` — count paragraph then `.pager` |

**Verdict on order:** production already renders the design's dashboard order exactly (intro → queue health → needs attention → community today → recent activity) — that order is asserted by `tests/Integration/Core/AppAdminDashboardRemediationTest.php:264-274` and `tests/browser/admin-dashboard.spec.ts:92`. The audit page order also matches except that production nests everything in one `.card` and puts the result count *below* the table instead of in the filter action row.

---

## 2. Difference table

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Topbar | constraint | Sticky 58px bar, 8-point star SVG, `Imladris` wordmark, `Back to the council` (:24-30) | No per-page topbar; `templates/layout.php:19-40` renders the shell from `$brand` | Do not port. The layout owns brand + back-navigation | low |
| 2 | Page frame | copy | `max-width:1160px; margin:0 auto; padding:26px 28px 110px` (:32) | `.admin` grid `max-width:1260px`, 224px rail + 28px gap, `padding:24px 28px 64px` (app.css:2799-2812) | Keep the rail (locked). Adopt the 110px bottom gutter | low |
| 3 | Head geometry | copy | `align-items:flex-start`, h1 `2.4rem` display/500, pill `margin-top:8px`, no bottom rule (:35-41) | `.admin-head` `align-items:center`, h1 `1.9rem`, `border-bottom:1px solid var(--border-hair)` (app.css:2813-2834) | Raise h1 to 2.4rem, switch to flex-start. Keep the head rule — it separates head from rail in the 3-node grid | low |
| 4 | Eyebrow skin | copy | `.68rem`, `var(--gold-ink)`, `.18em` (:37); section eyebrows `.64rem`, `.16em` (:60,96,115,133) | `.eyebrow` `.72rem`, `var(--text-muted)`, `var(--tracking-caps)`=.16em (app.css:37-43) | Scope admin eyebrow size/colour; tracking already matches at section level | low |
| 5 | Admin-mode pill | copy | `var(--surface-review)` / `var(--on-review)`, `4px 12px`, `.72rem`, `.08em` (:40) | `.pill-admin { background: var(--accent); color: var(--accent-contrast); }` (app.css:106) | Swap to the review pair | low |
| 6 | Section navigation | feature-changed | Two client tabs (`Dashboard`, `Audit log`) as `<button onClick>` (:45-48) | Grouped 8-section rail with real hrefs, `aria-current`, feature-aware disabled spans, mobile drawer (`_nav.php:7-92`, app.css:2839+) | **Keep the rail.** ADR 0023 item 6 + ADMIN §9.2 lock the IA; the design's tab strip is a per-screen elision. Do not add a tab strip | high |
| 7 | Pseudo-nav span | feature-removed | Non-interactive `Moderation · Content · People · Appearance · Notifications · Integrations · Settings` (:49) | Real links (`_nav.php:12-49`) | Do not ship — dead chrome, and it reverts ADR 0023 item 6 | low |
| 8 | Dashboard intro copy | copy | “Start with **the** live queues and health signals, then review what has changed across the **council**.” (:55) | “Start with live queues and health signals, then review what has changed across the community.” (dashboard.php:15) | Insert “the”; keep “community” (fiction already de-registered) | low |
| 9 | Intro measure | copy | `max-width:66ch; margin:0 0 26px` (:55) | `.pane-intro` `max-width:64ch; margin:0 0 4px` (app.css:2936-2940) | 66ch; spacing already handled by `.admin-pane { gap:22px }` | low |
| 10 | Queue-health heading | copy | h2 `1.5rem` display/500 (:61) | h2 inherits `1.75rem` (app.css:35) | Set admin section h2 to 1.5rem (and 1.35rem for the card-embedded h2s at :97,:134) | low |
| 11 | Live chip | copy | 7px dot `var(--presence)`, no glow, `.72rem`, `.06em`, `--text-faint` (:63) | `.status-legend i` `var(--success)` + 3px ring, `.7rem`, `--text-muted` (app.css:2955-2969) | Swap token, drop the ring, match sizes | low |
| 12 | Queue grid | copy | `repeat(4, 1fr); gap:14px` (:65) | `repeat(auto-fit, minmax(160px,1fr)); gap:12px` (app.css:2970-2974) | 4 fixed columns at desktop, keep the ≤860px collapse. Note a 5th (Thread Intelligence) card wraps | low |
| 13 | Queue card skin | copy | `padding:16px 17px 14px; gap:3px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm)`, hover `--shadow-md`, `border-left:3px solid` status (:66) | `.card` `border-radius:var(--radius)`(7px)`; padding:18px`, no shadow (app.css:159-167); `.queue-card` `min-height:168px; gap:8px`, 3px `::before` bar (app.css:2975-2999) | Adopt padding/gap/radius-lg/shadows in the admin scope. The `::before` bar is an equivalent mechanism — keep it | medium (`.card` is global; scope the override) |
| 14 | Queue card label | copy | Sentence case, `.74rem`, `.05em`, `--text-muted`, **no** `text-transform` (:67) | `.queue-card-head` uppercase, `.68rem`, `.06em` (app.css:3002-3008) | Drop the uppercase; match size/tracking | low |
| 15 | Queue card count | copy | `var(--font-mono)`, weight 400, `1.9rem` (:68) | `.queue-card-count` `var(--font-display)`, `2.1rem` (app.css:3009-3014) | Switch to mono 1.9rem | low |
| 16 | Queue card detail | copy | `.84rem`, `--text-faint`, `text-wrap:pretty` (:69) | `.queue-card-detail` `--text-muted`, inherits 1rem (app.css:3015-3018) | Adopt | low |
| 17 | Queue card state | copy | `.66rem`, `.1em`, uppercase, colour = status ramp (:70) | `.65rem`, `.07em` (app.css:3019-3028) | Tracking to `.1em` | low |
| 18 | Card title wording | copy | `Reports open` (:67) | `'Reports'` (AdminDashboardService.php:62) | Rename to “Reports open” (the regex pin at AppAdminDashboardRemediationTest.php:305 matches a substring, so it survives) | low |
| 19 | Email card | feature-changed | `Email queue` / count 0 / “Last run 11 minutes ago” / success `Clear` (:85-88) | `Email failures`, counts failed deliveries, detail varies over `email` flag + `Mailer::isConfigured()` + `EmailDomainVerifier::blockedReason()` (AdminDashboardService.php:86-98) | Keep production's metric, detail matrix and href (`/admin/email?status=failed`); adopt only the card skin | low |
| 20 | Amber “Waiting” tier | feature-removed | `--amber` left rule + `Waiting` state on Approval hold and Appeals (:72-83) | Only `attention` / `clear` / `unavailable` (AdminDashboardService.php:66,77,84,97; app.css:2992-2993) | Do not invent a third tier. The plan pins exactly three statuses (2026-07-18 plan, Task 3) | low |
| 21 | Unavailable card state | feature-added | Not modelled | Flag-off cards render as `<div class="… is-static">` with `--text-faint` rule and `Unavailable` (dashboard.php:27-33; app.css:2993,2999,3028) | Keep; render it in the design idiom (faint rule, faint state label, no hover lift) | medium |
| 22 | Thread Intelligence card | feature-added | 4 cards, fixed | Conditional 5th card when `community_memory` or `automated_context` is on, with heartbeat detail (AdminDashboardService.php:101-124) | Keep; the 4-col grid wraps it onto a second row | medium |
| 23 | Card detail micro-facts | feature-removed | “3 unclaimed · 1 harassment” (:69), “1 past the 72-hour promise” (:81), “Last run 11 minutes ago” (:87) | No per-reason breakdown, no appeal-age SLA, no worker-run timestamp on this card | Do not build. Production's details are computed facts; the design's are sample copy with an unbacked service promise | low |
| 24 | Attention count badge | copy | Pill: `padding:2px 10px; border-radius:999px; background:var(--surface-sunken)`, mono `.82rem`, `--text-body` (:99) | 30px circle, `--gold-soft` on `--gold-ink` (app.css:3032-3043) | Adopt the sunken pill (use `var(--radius-pill)` for the 999px) | low |
| 25 | Attention row anatomy | copy | `li` flex baseline, `gap:12px`, `padding:9px 0`, `border-bottom:1px solid var(--border-hair)`; link `flex:1`, `.97rem` (:103-106) | `.link-list li { padding:6px 0; border-bottom:1px solid var(--border) }` (app.css:596-597) | Adopt padding/rule/typography | low |
| 26 | Attention age column | feature-removed | Right-aligned age: `oldest 4h`, `oldest 9h`, `3d`, `2d` (:105, x-dc:353-358) | Attention entries carry only `label` + `href` (AdminDashboardService.php:126-169) | Do not build — no age is computed for any of the six attention sources | low |
| 27 | Attention empty copy | copy | “No pending operator work right now. **The queues are clear.**” (:110) | “No pending operator work right now.” (dashboard.php:47) | Append the second sentence | low |
| 28 | Attention panel rule | copy | Plain card, no left rule (:93) | `.attention-panel { border-left: 3px solid var(--accent-2) }` (app.css:3029-3031) | Drop the left rule (the design reserves left rules for status) | low |
| 29 | Community-today card set | feature-removed | 4 cards: `New topics` (Across 5 boards), `Replies` (Down 4% on last week), `New members`, `Commends given` (x-dc:375-380) | Only `new_users_today` and `active_users` are computed (AdminDashboardService.php:42-43,173-186) | Do not build the three missing metrics — new topics, reply trend and reputation totals would each need a new query and a trend baseline | low |
| 30 | “Active now” card | feature-added | Not modelled | `Active now` / “Seen in the last 15 minutes” → `/admin/users` (AdminDashboardService.php:180-185) | Keep; style in the idiom | low |
| 31 | Activity card label | constraint | `New members` (x-dc:378) | `New users today` (AdminDashboardService.php:176) — pinned by ADR 0023 item 4 and `tests/browser/admin-dashboard.spec.ts:104` | Keep the production label; renaming reverts a shipped remediation | low |
| 32 | Activity grid | copy | `repeat(4, 1fr); gap:14px` (:117) | `repeat(2, minmax(0,1fr)); gap:12px` (app.css:3047-3051) | Keep 2 columns (only 2 cards exist); adopt `gap:14px` | low |
| 33 | Activity card anatomy | copy | `padding:15px 17px`, radius-lg, no resting shadow, hover `--shadow-sm`; title `.78rem`/`.04em`/`--text-body`; detail `.82rem`/`--text-faint`/`margin-top:3px`; count mono `1.35rem` (:119-125) | `.card` padding 18px/radius 7px; `.activity-card-title` `--text-strong`; detail reuses `.queue-card-detail` (`--text-muted`); `.activity-card-count` display `2rem` (app.css:3052-3079) | Adopt | low |
| 34 | Activity detail strings | feature-removed | “Across 5 boards”, “Down 4% on last week”, “3 awaiting verification”, “Steady” (x-dc:376-379) | “Accounts created since 00:00 UTC”, “Seen in the last 15 minutes” (AdminDashboardService.php:177,184) | Keep production's factual details — the design's are sample/trend copy production cannot compute | low |
| 35 | Recent-activity section skin | copy | Card `padding:18px 20px 8px`, radius-lg, `--shadow-sm`; eyebrow `.64rem`; h2 `1.35rem` (:130-134) | `.card` padding 18px, radius 7px, no shadow (app.css:159-167); h2 inherits 1.75rem | Adopt | low |
| 36 | “View full audit log” | copy | Trailing `→`, `.76rem`, `var(--accent)` (:136) | `<a href="/admin/audit">View full audit log</a>` (dashboard.php:89) — the **exact** string `href="/admin/audit">View full audit log</a>` is asserted at AppAdminDashboardRemediationTest.php:280 | Add the arrow as a decorative CSS `::after` so the pinned markup survives; also match `.76rem` (app.css:3083-3086 already sets `.76rem`) | medium |
| 37 | Audit table head skin | copy | `.66rem`, `.12em`, `--text-faint`, `border-bottom:1px solid var(--border-soft)` (:140-144, :234-239) | `.admin .audit th` `.68rem`, `.04em`, `var(--gold-ink)`, `border-bottom:1.5px solid var(--border-strong)` (app.css:3239-3250) | Adopt tracking/colour/rule weight. **Shared by every admin table** — scope or accept the sweep | medium |
| 38 | Timestamp format | copy | `2 Aug 09:14`, mono `.78rem`, `--text-faint`, nowrap (:149, x-dc:279) | `human_datetime()` → `Aug 2, 2026 at 09:14 UTC` (helpers.php:65-75; dashboard.php:104; audit.php:80) | Adopt the compact mono register via a **new, audit-scoped** formatter; keep UTC explicit (column header note or `<time datetime="…">`). Do **not** change `human_datetime` — it is used site-wide | medium |
| 39 | Recent rows shown | copy | 6 (x-dc:381) | `recent(10)` (AdminDashboardService.php:48) | Cut to 6 | low |
| 40 | Overflow-cue shell | feature-added | None | `.activity-table-shell[data-overflow-cue]` + `<p class="table-scroll-cue">Scroll for Target and Reason →</p>` + `.table-scroll[role=region][tabindex=0]` (dashboard.php:91-93; app.css:3087-3094, 3400-3427) — pinned by AppAdminDashboardRemediationTest.php:284 and admin-dashboard.spec.ts:177 | **Keep.** 2026-07-18 plan Task 4/5 and ADR 0023 item 5 own it | high |
| 41 | Dashboard empty audit | copy | Not modelled (the `empty` data state simply yields no rows) | `<tr><td colspan="5" class="muted">No moderation or admin actions yet.</td></tr>` (dashboard.php:100) | Keep the string; restyle as a centred empty block in the design's register | low |
| 42 | Row hover | copy | None | `.admin .audit tr:hover td { background: var(--surface-sunken) }` (app.css:3267-3269) | Design has no row hover; keep (a scan affordance, no conflict at rest) or drop for verbatim parity — recommend keep | low |
| 43 | Audit as a screen | constraint | A client tab inside the same component (:163, `goAudit`) | Its own route `GET /admin/audit` (App.php:2210) with its own head (eyebrow `Accountability`, h1 `Audit log`) (audit.php:10-16) | Keep the real URL and the separate head — DESIGN §5.3 requires shareable, crawlable URLs; the tab is client state | low |
| 44 | Audit intro copy | copy | “Every moderation and admin action, append-only. Filter it, page through it, and follow a target's whole trail from its own record.” `max-width:68ch` (:165) | “Every moderation and admin action, append-only **(ADMIN §3.6)**. Filter, page, and follow a target's trail from its own record screen.” (audit.php:21) | Adopt the design sentence verbatim — it also removes an internal spec citation leaking into user-facing copy | low |
| 45 | Audit card structure | copy | Three blocks: filter card (:167), table card (:231), pager **outside** any card (:264) | One `<section class="card">` wrapping filter + table + count + pager (audit.php:20-122) | Split into three | low |
| 46 | Filter grid | copy | `repeat(3, 1fr); gap:14px 16px` (:168) | `repeat(auto-fit, minmax(180px,1fr)); gap:12px 16px` (app.css:3129-3134) | 3 fixed columns at desktop, collapse below 860px | low |
| 47 | Filter field labels | copy | uppercase `.68rem`, `.1em`, `--text-faint` (:170) | `.field > span` bold `.9rem`, sentence case (app.css:268) | Add a `.filter-grid .field > span` skin | low |
| 48 | Inputs | copy | `padding:8px 11px`, `var(--radius-md)`, `var(--border-soft)`, `background:var(--surface-page)`; focus `border-color:var(--gold-500); box-shadow:0 0 0 3px var(--focus-ring)` (:171-201) | `.input` `padding:9px 11px`, `border-radius:6px`, `var(--border)`, `background:var(--surface)` (app.css:256-264) | Adopt in the admin scope (`.admin .input`) | medium (`.input` is global) |
| 49 | Filter set + placeholders | copy | Actor / Action / Target type / Target # / From / To; placeholders “Username or display name”, “e.g. suspend, update_board”; target types `user, board, category, thread, post, setting, webhook, tag` + “Any target” (:169-202) | **Identical** field set, order, placeholders and option list (audit.php:24-57) | No change — already verbatim | low |
| 50 | Filter semantics | feature-changed | Client: actor `includes()` substring, action `includes()` substring, type/target exact, date string compare (x-dc:338-346) | Actor resolved to ids via `UserRepository::idsMatchingName`, capped at 500 with an outright refusal (`AuditQueryService.php:77-101`); action is a **prefix** `LIKE 'x%'` (`ModerationLogRepository.php:108-112`); type/target exact; `from`/`to` are inclusive `created_at` bounds (`:126-133`) | Keep production semantics. The “e.g. suspend, update_board” placeholder is honest for a prefix match | low |
| 51 | Result label | copy | Right-aligned inside the actions row: `N entries` / `1 entry` (:207, x-dc:393) | `<p class="muted">N matching entr(y\|ies).</p>` below the table (audit.php:113) | Move into the actions row; adopt the shorter wording | low |
| 52 | Apply / Reset | copy | `Apply filters` primary (accent / accent-contrast); `Reset` is a client `<button type="button">` with `1.5px var(--border-soft)` (:205-206) | `Apply filters` `.btn .btn-small`; `Reset` is `<a class="btn btn-small btn-ghost" href="/admin/audit">` (audit.php:60-61) | Keep the link (PE — reset must work with JS off); adopt the outlined-ghost skin | low |
| 53 | Filter validation | feature-added | None | 422 re-render preserving typed values, with `field_attrs()`/`field_error()` on `actor`, `target_id`, `from`, `to`; messages “Use a numeric target ID.”, “Use YYYY-MM-DD.”, “That filter matches too many accounts — add more of the name, or use the exact username.” (AdminController.php:59-74; AuditQueryService.php:56-87; audit.php:26-56) | Keep; style the inline errors in the design idiom (aria wiring already shipped under ADR 0023 item 5) | medium |
| 54 | Loading skeleton | constraint | Six pulsing bars, `animation: adPulse 1.6s var(--ease-calm) infinite` with `@keyframes` in an inline `<helmet><style>` (:211-219, :16-18) | None — the page is server-rendered in one pass | **Do not build.** Inline `<style>` is CSP-illegal and PE forbids client rendering. Ship only the empty and error registers | low |
| 55 | Error state | feature-removed | Card with rust left rule: h2 “The log could not be read”, p “The audit trail is append-only and intact — this is a read failure, not a gap in the record.”, `Try again` (:222-228) | A read failure becomes a kernel 500 error page (`App::process` catches `Throwable`) | Do not build a per-panel retry — there is no partial-failure path to reach it. Folding the sentence into the generic 500 copy is a separate decision | low |
| 56 | Empty state | copy | Centred block **inside** the table shell, below the header row: h3 “Nothing matches these filters”, p “The record is complete; this slice of it is simply empty.”, `Reset filters` (:254-260) | `<tr><td colspan="6" class="muted">No audit entries match these filters.</td></tr>` (audit.php:107) | Adopt the centred block; keep the header row rendered; the reset control becomes `<a href="/admin/audit">` | low |
| 57 | Unfiltered vs filtered empty | copy | One state for both (x-dc:336,395) | One state (audit.php:107) | Add an unfiltered variant when `base_query` is empty (“No moderation or admin actions have been recorded yet.”) — the model already exposes `base_query` (AuditQueryService.php:73) | low |
| 58 | Target column | feature-changed | **Every** target is a link, `var(--artifact-link)`, mono `.78rem` (:247) | Only `target_type === 'user'` links to `/admin/users/{id}`; all other types render as plain text (audit.php:84-89) | Keep production behavior — no generic target resolver exists. Style resolved links `--artifact-link`/mono; leave unresolved targets plain mono `--text-muted`. Do not ship dead links | medium |
| 59 | Change column | feature-changed | Plain mono one-liner: `status → resolved`, `name, slug`, `+3 entries` (:249, x-dc:279-302) | `<details class="audit-change"><summary>Details</summary>` with raw `before_json` / `after_json`, `—` when both are empty (audit.php:92-103; app.css:6189-6190) | Keep the disclosure — the stored data is JSON, not a prose diff, and 2026-07-18 plan Task 1 pins “precise before_json/after_json”. Restyle `summary` to the design's mono `.74rem`/`--text-faint` | medium |
| 60 | Pager | copy | Both controls always rendered, `disabled` at bounds, `Page N of M` centred, ghost skin, `margin-top:18px`, outside the card, `space-between` (:263-268) | `Previous` only when `page > 0`; `Next` only when `has_next`; no page label; inside the card (audit.php:114-121; app.css:727) | Render both controls always (disabled `<span aria-disabled="true">` at bounds — an `<a>` cannot be disabled), compute `Page N of M` from `total`/`per_page`, move outside the card, `space-between` | low |
| 61 | Page size | copy | 10 per page; pager hidden at ≤10 rows (x-dc:305,396) | `AUDIT_PER_PAGE = 50` (AdminController.php:28) | Keep 50 (operator surface, 200 hard cap in the repo). Adopt the “hide the pager when `total <= per_page`” rule | low |
| 62 | Table scroll region | feature-added | None | `.table-scroll` `role="region" tabindex="0" aria-label="Audit log entries"`, `min-width:760px` on the table, focus ring (audit.php:65; app.css:3217-3238) | Keep (ADR 0023 item 5 accessibility wiring) | medium |
| 63 | Column headers | copy | When / Actor / Action / Target / Reason / Change (:234-239); dashboard drops Change (:140-144) | Identical on both (audit.php:69-74; dashboard.php:96) | No change | low |

**Counts:** copy 41 · feature-added 6 · feature-removed 7 · feature-changed 5 · constraint 4 (63 rows).

---

## 3. Fiction strings

Production's `dashboard.php` and `audit.php` currently contain **no** Tolkien fiction — the only copy defect in production is the internal spec citation `(ADMIN §3.6)` in the audit intro. Everything below is fiction that a verbatim copy would *introduce*.

| # | Design string (path:line) | Proposed production string |
|---|---|---|
| 1 | `Imladris` wordmark in the topbar (:27) | Do not port. `templates/layout.php` renders `$brand['name']` |
| 2 | Eight-point elven-star SVG (:26) | Do not port. Not a RetroBoards mark; layout uses `$brand['logo_path']` |
| 3 | `Back to the council` (:29) | `Back to the forum` — but do not port at all; the layout owns back-navigation |
| 4 | “Start with the live queues and health signals, then review what has changed across **the council**.” (:55) | “…across the community.” (production already correct at dashboard.php:15) |
| 5 | `Commends given` activity card (x-dc:379) | Metric is not computed — drop the card entirely. If a reputation metric is ever added, label it “Reputation given” |
| 6 | “1 appeal past its **72-hour promise**” / “1 past the 72-hour promise” (:81, x-dc:356) | No SLA is implemented. Use production's “Open moderation appeals” / “N open appeals are waiting for a staff decision.” |
| 7 | “**Wardens** may now merge tags” (audit sample reason, x-dc:290) | “Moderators may now merge tags” — sample data only; never seed it into fixtures |
| 8 | Actors `erestor`, `elrond`, `glorfindel`, `melian`, `celebrian` (x-dc:279-302) | Neutral fixture handles (`admin_a`, `mod_b`, …) if these rows are ever reused as test seed |
| 9 | “Off-topic, moved to **#lore**” (x-dc:279) | Neutral sample reason |
| 10 | “**Keeper of the record** — 100 accepted” (x-dc:288) | Neutral badge name from the production badge catalogue |
| 11 | Webhook “**ledger-sync**” failing since Tuesday (x-dc:357) | Sample only; production computes no webhook-health attention line |

---

## 4. State inventory

| Design state | Verbatim string(s) | Production equivalent | Verdict |
|---|---|---|---|
| Dashboard — attention empty | “No pending operator work right now. The queues are clear.” (:110) | “No pending operator work right now.” (dashboard.php:47) | **copy** — append the second sentence |
| Dashboard — audit empty | *not modelled* (`isEmpty` yields no rows) | “No moderation or admin actions yet.” (dashboard.php:100) | **feature-added** (production is more honest); restyle only |
| Dashboard — queue counts | `qReports 7 / qHold 4 / qAppeals 2 / qEmail 0` (x-dc:368-371) | Live SQL counts (AdminDashboardService.php:38-46) | matches |
| Dashboard — card status ramp | rust “Needs review”, amber “Waiting”, success “Clear” (:70,76,82,88) | `attention` (rust) / `clear` (success) / `unavailable` (faint) (app.css:2992-2993,3027-3028) | **feature-removed** (amber tier) + **feature-added** (unavailable) |
| Dashboard — “Live” chip | `Live` + `var(--presence)` dot, no animation (:63) | `.status-legend` “Live” + `--success` dot with glow (dashboard.php:23) | **copy** — token + glow only |
| Audit — loading | 6 pulsing bars, `adPulse 1.6s var(--ease-calm)` (:211-219) | none | **constraint** — do not build (CSP + PE) |
| Audit — error | “The log could not be read” / “The audit trail is append-only and intact — this is a read failure, not a gap in the record.” / “Try again” (:224-226) | kernel 500 page | **feature-removed** — no reachable partial-failure path |
| Audit — empty | “Nothing matches these filters” / “The record is complete; this slice of it is simply empty.” / “Reset filters” (:256-258) | “No audit entries match these filters.” (audit.php:107) | **copy** — adopt the centred block |
| Audit — result count | `1 entry` / `N entries` (x-dc:393) | “N matching entr(y\|ies).” (audit.php:113) | **copy** — reword + relocate into the actions row |
| Audit — page label | `Page N of M` (x-dc:397) | none | **copy** — computable from `total` / `per_page` |
| Audit — pager bounds | `disabled={{ atFirstPage }}` / `{{ atLastPage }}` (:265,267) | control omitted at bounds (audit.php:115,118) | **copy** — render a disabled `<span aria-disabled="true">` |
| Audit — pager visibility | `showPager: rows.length > PAGE_SIZE` (x-dc:396) | `.pager` always present (may be empty) (audit.php:114) | **copy** — hide when `total <= per_page` |
| Audit — validation errors | none | 422 + “Use a numeric target ID.”, “Use YYYY-MM-DD.”, “That filter matches too many accounts — add more of the name, or use the exact username.” (AuditQueryService.php:57,65,84) | **feature-added** — keep, style in idiom |
| Audit — actor-with-no-match | filters client-side to zero rows | short-circuits to `total: 0, has_next: false` without querying (AuditQueryService.php:88-99) | matches (renders the empty state) |
| Audit — retry affordance | `Try again` → `recovered: true` (x-dc:391) | none | **feature-removed** |
| Audit — success/saved | *none — the screen has no mutations* | n/a (audit is read-only) | matches |

---

## 5. Slice proposal

Every slice touches `templates/**` and/or `public/assets/app.css`, so **each one ends with** `php bin/build-imladris-assets.php --print-application-digest` → paste into `config/imladris-runtime-baseline.json` → `composer check:imladris && composer verify:imladris`. All new CSS lands **unlayered in `public/assets/app.css`** (never `imladris.css`, never `components.css`, never `!important`).

### S1 — Console head + eyebrow register (both screens)
- **Touches:** `templates/admin/dashboard.php`, `templates/admin/audit.php`, `public/assets/app.css` (`.admin-head`, `.eyebrow` in the admin scope, `.pill-admin`, `.pane-intro`, admin `h2` sizes).
- **Diff rows:** 3, 4, 5, 8, 9, 10, 44.
- **Tests:** `AppAdminDashboardRemediationTest::test_dashboard_is_operational_only_and_has_required_order_and_labels` (order pins must stay green) + a new assertion on the two intro sentences; Playwright desktop/mobile screenshots; axe serious/critical.

### S2 — Queue health cards
- **Touches:** `templates/admin/dashboard.php`, `src/Service/AdminDashboardService.php` (title `Reports` → `Reports open` only), `public/assets/app.css` (`.admin-dashboard-grid`, `.queue-card*`, `.status-legend`).
- **Diff rows:** 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23.
- **Tests:** `AppAdminDashboardRemediationTest::test_dashboard_queue_cards_expose_attention_clear_and_unavailable_states` (unchanged), a new test asserting the label is not uppercased in markup and that the 5-card TI variant renders; Playwright card-geometry check at 1280×800 and 390×844.

### S3 — Needs-attention panel
- **Touches:** `templates/admin/dashboard.php`, `public/assets/app.css` (`.attention-panel`, `.attention-total`, `.attention-list`).
- **Diff rows:** 24, 25, 26, 27, 28.
- **Tests:** `AppAdminTest:161` stays green; new integration test for the two-sentence empty state with all queues clear; Playwright.

### S4 — Community today
- **Touches:** `templates/admin/dashboard.php`, `public/assets/app.css` (`.activity-card-grid`, `.activity-card*`).
- **Diff rows:** 29, 30, 31, 32, 33, 34.
- **Tests:** `admin-dashboard.spec.ts:104` (`['New users today','Active now']`) must stay green — the slice deliberately changes no labels.

### S5 — Audit table register (shared by dashboard + audit page)
- **Touches:** `public/assets/app.css` (`.admin .audit th/td/code`, target-link colour), `src/Support/helpers.php` (new `audit_datetime()` — **do not touch `human_datetime`**), `templates/admin/dashboard.php`, `templates/admin/audit.php`, `src/Service/AdminDashboardService.php` (`recent(10)` → `recent(6)`).
- **Diff rows:** 35, 36, 37, 38, 39, 41, 42, 58, 59, 63.
- **Tests:** new unit test for the formatter (fixed UTC input → exact output, plus the UTC marker); integration assertion that `href="/admin/audit">View full audit log</a>` is still byte-identical (arrow is CSS-only); integration assertion that a non-user target renders unlinked; Playwright.

### S6 — Audit filter card
- **Touches:** `templates/admin/audit.php`, `public/assets/app.css` (`.filter-form`, `.filter-grid`, `.filter-grid .field > span`, `.admin .input` focus ring, `.form-actions`).
- **Diff rows:** 45 (filter half), 46, 47, 48, 51, 52, 53.
- **Tests:** the existing 422 path — POST-free GET with `from=banana` must still render 422 with the typed value and the field error, and the >500-actor refusal must still show its message; a new assertion that the result label sits inside `.form-actions`; no-JS Playwright context proving Reset navigates to `/admin/audit`.

### S7 — Audit empty state + pager
- **Touches:** `templates/admin/audit.php`, `src/Controller/AdminController.php` (pass a page-count/`per_page` through both the happy and 422 paths), `public/assets/app.css` (`.state-empty` block, `.pager`).
- **Diff rows:** 45 (table/pager split), 56, 57, 60, 61.
- **Tests:** new integration tests for (a) unfiltered-empty copy, (b) filtered-empty copy + reset link, (c) `Page 1 of 3` label, (d) disabled `Previous` on page 0 and disabled `Next` on the last page, (e) pager hidden when `total <= 50`; no-JS Playwright paging journey.

### S8 — Honesty + evidence closeout
- **Touches:** nothing new in code — a verification pass. Confirms: no fiction string from §3 entered any template; `(ADMIN §3.6)` is gone from the audit intro; the 8-group rail is untouched; the `data-overflow-cue` contract still passes.
- **Gates:** `rg -n "<script|<style| on[a-z]+=" templates/admin templates/mod templates/layout.php -S` clean; full `vendor/bin/phpunit` read to completion; `npm run evidence` + `npm run a11y` on desktop and mobile; screenshots to `docs/evidence/<slice>/{desktop,mobile,comparisons}/`; ledger to `docs/history/<slice>-<date>.md`.
