# Imladris → production: admin & account surface adoption — **Stage 1 (inventory & comparison)**

Date: 2026-08-03 · Status: **Stage 1 complete and reviewed; blocking decisions resolved (§4). Stage 2 Slice 0 landed — documents only, still no production code.**
Companion: [`2026-08-03-imladris-admin-account-ledger.md`](2026-08-03-imladris-admin-account-ledger.md) — the full deviation ledger.

Governing rule for the whole exercise: **copy the design verbatim.** The only sanctioned deviations
are `feature-added`, `feature-removed`, `feature-changed`, `constraint`. Aesthetic preference is not
one of them; anything that cannot be classified into those four is a plain `copy` difference and
production changes to match.

---

## 1. The design inputs are not what the brief assumed

The brief listed **seven** design screens from `docs/design-system/imladris/`. The local mirror was
one sync behind the live Claude Design project (`c3e02753-607c-40b6-994c-9ba1a65bb367`, updated
2026-08-03 18:00 UTC). Verified via DesignSync (read-only):

| Finding | Consequence |
|---|---|
| The live project has **ten** `templates/admin-*` screens, not six. Missing locally: `admin-features`, `admin-integrations`, `admin-members`, `admin-packages`. | Admin design coverage is **37 of 39** production admin pages, not ~14. Most of the "no design representation" set evaporates. |
| `ui_kits/admin/` and `feature-ui/{polls,tags,moderation}/` were **deleted upstream** on 2026-08-03. The retired admin kit's README said "SUPERSEDED by templates" and mapped all ten destinations to `templates/admin-*`. | The brief's "read them only to understand intent" is right, and `PRODUCTION_PARITY.md`'s admin row (which names `ui_kits/admin` as the owner) is stale. |
| `PRODUCTION_PARITY.md` is superseded by **`PRODUCTION.md`**, whose admin row reads: *"the ten `templates/admin-*` templates, unified by `components/admin/AdminNav`."* A new `REDUNDANCY-AUDIT.md` records the retirements as executed. | The authoritative mapping is `PRODUCTION.md` + `AdminNav.jsx`, not `PRODUCTION_PARITY.md`. |
| The six mirrored admin screens had also changed: the per-screen sticky topbar **and** the `Operator desk · <Area>` eyebrow + `Admin mode` pill were replaced by one `<x-import …AdminNav…>`. `admin.card.html:43` documents the removal. | **There is no eyebrow on any current admin design screen.** Every "add an eyebrow" instruction is inverted — production's 12 page-head eyebrows get *deleted*. |
| `AccountSettings.dc.html` lost only the Reading ▸ Pagination `Default sort` select. | It kept its own topbar and eyebrow — the admin inversion does **not** carry across to it. |

### What was synced into the repo (design docs only — zero production code)

Added: the four missing admin screens, `components/admin/{AdminNav.jsx,AdminNav.d.ts,admin.card.html}`,
`PRODUCTION.md`, `REDUNDANCY-AUDIT.md`, `github.md`, `RETIRED.md`.
Refreshed: the six admin screens, `AccountSettings.dc.html`, `README.md`, `CHANGELOG.md`.

**Deliberately *not* taken** (the mirror is ahead of upstream, or the change is build-coupled):

- `tokens/colors.css` — the mirror's `--surface-staff`/`--on-staff` pair is a documented, test-backed
  WCAG-AA correction (`LOCAL_RECONCILIATION.md` 18–24; commit `8ffefce`). Upstream still paints
  `.badge-staff` from the numbered ramp at 3.55:1, which also fails to flip in twilight.
- `production-contract.json` — upstream regressed `group_dms` to `implemented_dark` (it graduated
  default-on 2026-07-18, ADR 0022) and dropped `reconciled_through_commit`, which
  `ImladrisRuntimeAssetTest` pins to the literal `6d81da59…`.
- `manifest.json` — upstream re-files the ADR 0021/0023 gaps against `templates/admin-*` (correct in
  substance) but a non-empty `unresolved_gaps` makes `check:imladris` red. Fold into ADR 0024.
- `components.css` — upstream adds three sections (`.admin-bar`/`.admin-tier`, `.thread-list.is-board`,
  `.presence-widget`). Taking it requires `composer build:imladris`, which regenerates production
  assets — Stage 2 work. The upstream file is parked at
  `scratchpad/stage1/components.css.upstream-2026-08-03`; it can be re-fetched at any time.

`composer verify:imladris` (13 tests, 91 assertions) and the full suite (**2442 tests, 17419
assertions, 2 skipped**) are green at the end of Stage 1.

---

## 2. Screen → production mapping

Authority: `components/admin/AdminNav.jsx` `ADMIN_AREAS` (the tier order) + `PRODUCTION.md`, verified
route-by-route against `App::buildRouter()`.

| # | Design screen | H1 | Tabs | Production templates |
|---|---|---|---|---|
| 1 | `admin-overview` | Admin console | Dashboard · Audit log | `dashboard.php`, `audit.php` |
| 2 | `admin-content` | Boards & tags | Boards & categories · Tags | `structure.php`, `board_edit.php`, `structure_confirm.php`, `tags.php`, `tag_merge_confirm.php` |
| 3 | `admin-people` | Roles & capabilities | Roles · Permission simulator | `roles.php`, `role_edit.php`, `role_simulator.php` |
| 4 | `admin-members` | Members & invitations | Directory · Invitations | `users.php`, `user_record.php`, `users_bulk_confirm.php`, `invitations.php` |
| 5 | `admin-appearance` | Branding & themes | Branding · Themes | `branding.php`, `themes.php`, `theme_safe_mode.php` |
| 6 | `admin-notifications` | Email & announcements | Email · Announcements | `email.php`, `announcements.php` |
| 7 | `admin-integrations` | Tokens, webhooks & sign-in | API tokens · Webhooks · Sign-in providers | `api_tokens.php`, `webhooks.php`, `webhook_detail.php`, `providers.php`, `provider_disable.php` |
| 8 | `admin-packages` | Packages & registries | Packages · Registry trust · Extensions | `packages.php`, `package_detail.php`, `package_plan.php`, `package_consent.php`, `package_security.php`, `package_publisher.php`, `_package_integration.php`, `_package_review_form.php`, `registries.php`, `extensions.php` |
| 9 | `admin-features` | Features & badges | Feature flags · Badge rules · Custom emoji | `features.php`, `badge_rules.php`, `badge_rule_preview.php`, `custom_emoji.php` |
| 10 | `admin-settings` | General & intelligence | General & registration · Thread Intelligence | `settings.php`, `thread_intelligence.php` |
| 11 | `account-settings` | Account settings | grouped rail, 13 items | all 13 `templates/account/*.php` + `partials/settings_nav.php` |

Coverage: `templates/admin/` holds 42 files, 3 of them `_`-prefixed partials → **39 pages; the eleven
screens cover 37.**

### Production pages with no design representation

| Surface | Route | Flag | Policy |
|---|---|---|---|
| `admin/moderation.php` (Anti-abuse) | `/admin/moderation` | `anti_abuse` (ON) | Shared chrome only, **no body adoption**. The only admin page with zero design content anywhere. Raise the gap upstream. |
| `mod/{reports,approvals,appeals,user}.php` | `/mod/*` | `moderation_queue`, `appeals` | `ADMIN_AREAS` has **no Moderation area**. Conditional slice; the tier must be role-filtered or omitted (a board moderator would see ten destinations that all 403). |
| `appeals/index.php` | `/appeals` | `appeals` | Account shell only; the rail entry already exists and is recorded `feature-added`. |
| `admin/board_edit.php` | `/admin/boards/{id}/edit` | core | Adopt **by extrapolation**, explicitly labelled — the design draws an `Edit` link to nothing. |
| `admin/extensions.php` | `/admin/extensions` | `server_extensions` (**OFF**) | Represented but unshippable as drawn — the design's body copy asserts a state the controller 404s. Ship the tab disabled; rewrite the copy. |

### Design sections with no production home — **do not build, do not ship dead chrome**

`Regard` reputation pane · password strength meter · 2FA QR square + Cancel · persistent recovery
codes · typed profile-field schema · `Hidden — wardens only` · `Members I have replied to` ·
per-event email switches · drafts autosave composer · amber "Waiting" queue tier · attention-row ages
· three uncomputed Community-today metrics · audit error-retry · roles filter bar + its empty state ·
assignments on system roles · bounce/complaint ingestion · 30-day delivery retention · the
invitations-flag checkbox · the evidence `Digest` column · `Ready for acceptance` · the recovery
drill · a theme `deactivate` button · relative timestamps. Each is recorded in the ledger §4.

---

## 3. Classification counts

Per-report, before deduplication. `copy` is not a deviation — it is plain work.

| Design screen | copy | feature-added | feature-removed | feature-changed | constraint |
|---|---:|---:|---:|---:|---:|
| shell (cross-screen chrome) | 33 | 5 | 1 | 1 | 13 |
| admin-overview | 41 | 8 | 8 | 7 | 4 |
| admin-content | 33 | 5 | 1 | 4 | 9 |
| admin-people | 35 | 11 | 2 | 4 | 10 |
| admin-members | 66 | 26 | 1 | 2 | 7 |
| admin-appearance | 38 | 9 | 1 | 10 | 14 |
| admin-notifications | 43 | 14 | 2 | 5 | 11 |
| admin-integrations | 57 | 13 | 0 | 14 | 9 |
| admin-packages | 58 | 40 | 1 | 8 | 4 |
| admin-features | 39 | 12 | 4 | 9 | 11 |
| admin-settings | 27 | 14 | 2 | 5 | 13 |
| account-settings | 73 | 13 | 7 | 14 | 11 |
| **Total** | **543** | **170** | **30** | **83** | **116** |

After deduplication the ledger carries **34 constraint rows**, of which C-01…C-16 are cross-screen.

---

## 4. Three decisions that blocked Stage 2 — **all resolved 2026-08-03**

These were not design calls. Each pitted the design system against a higher authority in the
precedence chain. All three were put to the operator and answered; the outcomes are recorded in
[ADR 0024](../../adr/0024-imladris-admin-account-adoption.md) and `ADMIN.md` §9.2/§9.4 are amended.

| | Decision | Outcome |
|---|---|---|
| **D1** | Console IA: design tier vs `ADMIN.md` §9.2 left-nav | **Adopt the tier; amend the spec.** ADR 0023's IA clause superseded in part; its three other findings kept. |
| **D2** | Where `/mod/*` + `/admin/moderation` live | **An eleventh area, `Moderation`, at tier index 1** — preserves `ADMIN_AREAS` order *and* ADMIN.md's "Moderation second". `Audit log` moves to Overview. |
| **D3** | What the identity row drops | **Keep search / bell / monogram / sign-out**, styled in the design's own right-cluster idiom (`AccountSettings.dc.html:30-34`). Recorded `feature-added`. |

Final tier order: Overview · **Moderation** · Content · People · Members · Appearance ·
Notifications · Integrations · Packages · Features · Settings.

The original framing of each decision follows.

### D1 — The console information architecture (the big one)

`AdminNav.jsx` is a **flat ten-area horizontal pill tier** with no grouping element and no Moderation
area. `ADMIN.md` §9.2 says verbatim *"Console information architecture — Left-nav, grouped:"* over an
eight-group table; §9.4 says *"reuse the app shell and tokens"* and *"the section nav in a drawer"*.
`docs/adr/0023:17` records the grouped rail as a **shipped remediation** *"per ADMIN §9.2"*.

ADMIN.md outranks a design-system pull. Adopting the tier therefore requires amending **ADMIN.md §9.2
and §9.4** and superseding ADR 0023's IA clause — a spec amendment, not a restyle.

Blast radius if adopted: `AppAdminNavIaTest:31-36`, `AppAdminDashboardRemediationTest:77-120` (which
asserts **all 26 destinations render on one page in strict group order** — structurally impossible
under scoped tabs), `admin-dashboard.spec.ts:61,93-105`, `AppImladrisFidelityTest:81`, plus
`app.js:766-875` (drawer + focus trap) and `app.css:2800-2932`, `:3279-3387`.

Mitigating facts, both verified: the design's own nav renders `<a href>` when no JS handler is
supplied, so the tier is **progressive-enhancement-compatible and zero-JS**; and ten of the eleven
placement moves are pure `copy`.

### D2 — The eleventh area

The four `/mod/*` surfaces plus `/admin/moderation` are live, flag-gated, tested functionality with
no design home. Under the rules that is `feature-added`: keep it, style it in the idiom, record it.
The minimum-conflict proposal inserts `Moderation` at tier index 1 — which simultaneously preserves
`ADMIN_AREAS`' relative order and matches ADMIN.md §9.2's and `_nav.php:11`'s "Moderation second".

### D3 — What the identity row deletes

`.admin-bar-id` holds only mark + wordmark + exit link + mode pill. That removes the search form,
notification bell, user monogram and log-out that `partials/topbar.php` renders on every admin page
today. Retention or removal must be recorded, not silent — note the design itself *keeps* a right
cluster on the member screen (`AccountSettings.dc.html:30-34`).

---

## 5. Live defects found during the pass (not design work)

Three, all verified against source. They must be fixed before adoption is layered on top.

1. **The staff badge does not flip for the default theme.** `layout.php:4` defaults `theme` to
   `system`; `app.css` has two token blocks (`[data-theme="dark"]` `:789`, `@media (prefers-color-scheme: dark) { [data-theme="system"] }` `:831`) and **neither declares `--surface-staff`/`--on-staff`**,
   while `imladris.css` has no `prefers-color-scheme` block at all. A `system`-theme user on a dark OS
   gets the light-register staff chip on a twilight surface — the exact bug `8ffefce` claimed to fix,
   which landed only on the explicit-`dark` path. `.badge-staff` renders on every thread an admin has
   posted in (`partials/post.php:55`). No test catches it.
2. **The branding preview bar shows neither the typed nor the saved colour.** `.brand-preview-*` is
   duplicated (`app.css:876-903` vs `:3515-3565`) and the bar is pinned to the static `--brand`
   token, which `/brand.css` never emits.
3. **The Thread Intelligence status rail always paints success.** All four cards emit bare
   `queue-card is-static`, so `.queue-card::before` renders `--success` even on `Not ready` / `Paused`.

Also settled: `/admin/thread-intelligence` answering **200 with both TI flags dark is deliberate**
(ADR 0019, `AppAdminThreadIntelligenceTest:29-71`). An earlier report called it a missing flag guard;
"fixing" it turns the suite red.

---

## 6. Sequenced implementation plan

Every slice ends with: a CSP scan (`rg -n "<script|<style| on[a-z]+=" templates/ -S`),
`vendor/bin/phpunit` on a private `DB_TEST_DATABASE`, the named Playwright specs on **desktop and
mobile**, a `javaScriptEnabled:false` pass over the touched routes, and screenshots under
`docs/evidence/<slice>/`.

| # | Slice | Touches | Evidence |
|---|---|---|---|
| 0 | **Adjudication ADR** (no code) | `docs/adr/0024-imladris-admin-account-adoption.md`, this plan, `LOCAL_RECONCILIATION.md` | reviewed, not executed |
| 1 | **Live-defect pre-fixes + mirror repair** | `app.css`, `manifest.json`, `production-contract.json`, mirror `components.css` + `.presence-staff` patch, `composer build:imladris` | `evidence` + `a11y` |
| 2 | **Shared console chrome** | new `admin/_console.php`, `layout.php` (4th variant), `app.js` (delete the drawer), `app.css`, 39 admin template heads | `evidence` + `a11y` |
| 3 | **Shared component CSS + partials** | `app.css` (audit tables, state pills, callouts, spec lists, filter grids, confirm cards, check grids), new `pager`/`back_link`/`empty_state` partials | `evidence` + `a11y` + `evidence:integrations` |
| 4 | **Account rail + shell** | `partials/settings_nav.php`, 13 account heads, `app.css` | `evidence` + `a11y` + **new `account-console.spec.ts`** |
| 5 | admin-overview | `dashboard.php`, `audit.php`, `AdminDashboardService`, `AdminController` | `admin-dashboard` + `admin-remediation` |
| 6 | admin-content | `structure*`, `tags*`, `board_edit.php` | **new `content-console.spec.ts`** + `admin-remediation` |
| 7 | admin-people | `roles*`, `role_edit`, `role_simulator` | `role-assignments` (+ add it to `npm run evidence`) |
| 8 | admin-appearance | `branding`, `themes`, `theme_safe_mode` | `admin-features`, `gate-a`, `a11y` |
| 9 | admin-notifications | `email`, `announcements`, `EmailOpsService` | `admin-remediation`, `gate-a`, `a11y` |
| 10 | admin-settings + TI | `settings`, `thread_intelligence` | `thread-intelligence`, `admin-remediation`, `a11y` |
| 11 | admin-members | `users`, `user_record`, `users_bulk_confirm`, `invitations` | `admin-remediation`, `invitations`, `gate-a`, `a11y` |
| 12 | admin-integrations | `api_tokens`, `webhooks*`, `providers*` | `evidence:integrations`, `providers` |
| 13 | admin-features | `features`, `badge_rules*`, `custom_emoji` | `admin-features`, `gate-a`, `a11y` |
| 14 | admin-packages | the 9 package/registry/extension templates + 2 partials | `evidence:packages`, `evidence:integrations`, `a11y` |
| 15 | account A — substrate, Profile, Security | `account/{settings,security}.php`, `app.css` | `evidence:passkeys`, `a11y`, `account-console` |
| 16 | account B — 8 panes | privacy, appearance, preferences, composing, notifications, connections, sessions, blocks | `account-console`, `gate-a`, `a11y` |
| 17 | account C — Boards, Drafts, Lifecycle | those three + `composer.js` | `server-drafts`, `a11y`, `account-console` |
| 18 | `/mod/*` chrome *(conditional on D2)* | `mod/*`, `admin/moderation.php`, `appeals/index.php` | `appeals`, `admin-remediation`, `a11y` |
| 19 | De-fiction pass + closeout | unpinned fiction strings, baseline digest, evidence | the full evidence sweep |

Slices 5–14 are mutually independent once 2 and 3 land; 15–17 depend on 4.

### Evidence coverage is thinner than it looks

`npm run evidence` runs 15 of 28 specs and is the **only** CI. It omits `webhooks`,
`package-security`, `package-review`, `package-integrations`, `role-assignments`, `passkeys`, `totp`,
`profile-surface`. Three admin pages and seven account panes have **zero** spec coverage today, and
`role-assignments.spec.ts` is reachable from no named script at all. Two new specs
(`content-console.spec.ts`, `account-console.spec.ts`) are a delivery obligation of ADR 0024.

---

## 7. Standing execution rules

1. **One ADR, one plan doc, one owner** — `docs/adr/0024-imladris-admin-account-adoption.md`. Eleven
   separately-proposed `0024-*.md` files collapse into sections of it, or DESIGN §13's "deferrals are
   never silently dropped" fails by accident.
2. **`.admin-bar`/`.admin-tier` CSS ships from `composer build:imladris`**, never hand-copied. The
   builder reads `docs/design-system/imladris/`; `resources/imladris/` and `public/assets/imladris.css`
   are *outputs*. The eleven tier class names appear **zero** times in `app.css`, so they are
   uncontested and the layered rules render as authored.
3. **`app.css` is unlayered and beats every `@layer imladris.*` rule** regardless of specificity —
   181 of 211 design-system class names are contested. "The design system already styles this" is
   wrong unless the property is uncontested.
4. **A new semantic colour token lands in three places**: `tokens/colors.css :root`,
   `tokens/colors.css [data-theme="dark"]`, and `app.css`'s `[data-theme="system"]` dark block.
   Never `!important` (the builder refuses it). Never `--gold-050` — it does not exist; use `--gold-soft`.
5. **`config/imladris-runtime-baseline.json` is refreshed once per merge, on `main`, by the merger.**
   No slice branch may contain a change to it.
6. **Never recolour `.pill-admin`** — 41 call sites in three meanings, including the
   execution-disabled emergency brake at `package_security.php:18`. Use the design's own
   `.admin-bar-mode`.
7. **Anti-draft-loss is the standing test obligation** — 32 distinct 422 paths. Every slice's test
   list includes a POST-invalid-then-assert-typed-value-survives case per form it touches.
8. **Fiction never ships.** 4 production strings are *already* fiction **and test-pinned**
   (`Removed by a warden`, `Commends`, `Private counsel`, `sort=commends`) — those need their own
   owner decision, not a unilateral fix.

---

## 8. Method note — a defect in this pass, and its repair

Two analysis workflows ran concurrently. One refreshed the design mirror while the other was diffing
it, so seven of twelve screen reports were written against markup that was overwritten mid-pass. A
re-anchor pass corrected every citation, struck nine rows whose quoted design strings no longer exist
(or never did), and inverted five rows that told production to *add* an eyebrow the design had just
*deleted*. The corrected reports are the ones consolidated into the ledger. Running the sync to
completion before the diffs would have avoided it.

Totals: 44 subagents across three workflows, 0 errors; every screen adversarially verified.
