# S — Stage-1 completeness synthesis

Reviewed: `F1`–`F5`, twelve `D-*.md`, ten `V-*.md`. Every load-bearing claim below was re-checked
against source (design files, `templates/`, `tests/`, `public/assets/`, `docs/adr/`) rather than
taken from the reports.

**Overall verdict on the pass.** The production-side research is genuinely excellent — across ten
adversarial passes the verifiers could not break a single production *behaviour* claim. The design
side is systematically compromised: seven of twelve screens were diffed against a revision that was
overwritten mid-pass (mtime `2026-08-03 20:36`), and two screens were never adversarially verified
at all. The pass is **not yet safe to convert into an implementation plan** without the corrections
in §1 and §5.

---

## 1. WHAT IS MISSING FROM THE PASS

### 1.1 Two screens have no adversarial verification

`V-admin-members.md` and `V-admin-integrations.md` **do not exist** (`ls scratchpad/stage1/` → 10
`V-*` files for 12 `D-*` files). Every other screen had a verifier find between four and eleven
substantive errors in its D report, including fabricated design strings (`D-admin-content` R1),
inverted actions (`D-admin-appearance` #2/#41), and test-breaking proposals. There is no reason to
believe these two are the exceptions.

Two errors are already visible in the un-verified reports without a dedicated pass:

- **`D-admin-integrations` row 86 and slice S1 instruct new CSS into `public/assets/imladris.css`.**
  That is the *generated* file (`src/Support/ImladrisAssetBuilder.php`), it is fingerprinted by
  `config/imladris-runtime-baseline.json`, and F1's landing rule is explicit that it is never
  hand-edited. Nine other D reports correctly say `app.css`. This single line would break
  `composer verify:imladris` and be overwritten by the next build.
- **`D-admin-members` #81 prescribes `--gold-050`** for the invitation one-time-link banner.
  F1 established `--gold-050` exists nowhere (`tokens/colors.css` stops at `--gold-100`) and that
  transcribing it fails `ImladrisRuntimeAssetTest::test_every_required_runtime_variable_has_a_definition`.
  The substitute (`--gold-soft`) is named in F1 but not in the screen report.
  `D-admin-integrations` row 6 has the same banner and names no token at all.

**Action:** run V passes for `admin-members` and `admin-integrations` before either is scheduled.

### 1.2 The design source moved under seven reports; the corrected index was never written

`git status` shows six admin screens plus `AccountSettings` and `components.css` modified in the
working tree at 20:36–20:37. Consequences the reports carry:

| Report | Offset | Substantive inversion |
|---|---|---|
| `D-admin-overview` | design cites wrong; `x-dc` +13 | rows 1,3,4,5,7 describe deleted markup |
| `D-admin-people` | −12 | `C1` (add eyebrow) is now backwards |
| `D-admin-content` | ~−12; file is 570 lines not 582 | R1: the eyebrow string it quotes is **fabricated** |
| `D-admin-appearance` | −12; 373 lines not 385 | #2/#41 (add eyebrow) now backwards |
| `D-admin-notifications` | −12; 441 lines not 452 | D3 (add eyebrow) refuted; D5 (pill) relocated |
| `D-admin-settings` | −12; 288 lines not 299 | rows 4,5 describe deleted markup |
| `D-account-settings` | none material | only the Reading `Default sort` select was removed |

Nobody has produced the re-anchored citation index. Stage 2 cannot execute against `:NNN` numbers
that are uniformly wrong.

### 1.3 Design regions never read

- **`components/admin/AdminNav.jsx` + `admin.card.html` + `components.css:324–342`** are now part of
  every admin screen's design source (each screen is one `<x-import>` line). `D-admin-overview`
  never opened them (V §Mi1). `D-shell` opened the JSX but asserted the CSS did not exist — refuted
  by `V-shell` R2; I re-verified: the skin is at `docs/design-system/imladris/components.css:328–342`.
- **`D-admin-settings` concluded "the design does not represent Feature flags"** and proposed
  inventing an idiom (S5). `templates/admin-features/AdminFeatures.dc.html` is 492 lines and owns
  it, including production's own no-toggles rule verbatim at `:47`.
- **`D-admin-appearance` concluded custom emoji is "not modelled anywhere"** and proposed inventing
  an idiom (Slice 5). `AdminFeatures.dc.html:216–283` models it in full, including a byte-identical
  empty state.
- **`D-admin-packages` reported "feature-removed sweep: none found."** The Extensions tab is a live
  tab in every state and its body asserts the page is viewable while `server_extensions` is dark —
  which production cannot do (`AdminExtensionController.php:20–22` 404s). Found only by the verifier.

### 1.4 Production pages never diffed

Two, and both are correctly *identified* but never analysed:

| Page | Status |
|---|---|
| `templates/admin/moderation.php` (anti-abuse) | Zero design content on any of the eleven screens. Named as unrepresented by F3 and `D-admin-members` #95; never diffed. |
| `templates/mod/{reports,approvals,appeals,user}.php` | `ADMIN_AREAS` has no Moderation area. Discussed only as a *proposal* (`D-shell` S12/S49). No screen owns them. |

`templates/appeals/index.php` is in `settings_nav.php` but has no `AccountSettings` tab; it appears
only in F3 and in `V-shell` N6's fiction list. `templates/admin/board_edit.php` *was* diffed
(`D-admin-content` #45).

### 1.5 Cross-cutting concerns never assigned to any slice

1. **Where `.admin-bar` / `.admin-tier` CSS comes from.** It exists only in the docs mirror
   (`docs/design-system/imladris/components.css:328–342`); `grep` confirms it is **absent** from
   `resources/imladris/components.css` and from the generated `public/assets/imladris.css`. Either
   the runtime input is regenerated (a design-contract review step) or the block is re-authored in
   `app.css`. F5 follow-up 1 raises it; no slice owns it.
2. **`.presence-staff` reintroduces the AA regression** the mirror deliberately fixed
   (`--gold-100`/`--gold-700`, 3.55:1, does not flip). F5 follow-up 2; unassigned.
3. **F1 defect H5** — `--surface-staff`/`--on-staff` are missing from `app.css`'s
   `@media (prefers-color-scheme: dark) { [data-theme="system"] }` block, and `layout.php` defaults
   to `system`. `D-shell` files it "out of scope"; no slice owns it.
4. **The runtime-baseline digest is one shared file.** Eleven D reports each say "refresh
   `application_surface.sha256` at the end of my slice". Concurrent slices will collide on
   `config/imladris-runtime-baseline.json`. Needs one rule and one owner.
5. **Eleven reports each propose "a new `docs/adr/0024-*.md`".** Next free number is 0024 (verified).
   Eleven ADR 0024s cannot exist.
6. **`human_relative()` does not exist.** `helpers.php` has `human_datetime()` (absolute) and
   `human_duration()` (a duration). Three screens want relative time (`admin-settings` "Last run 6
   minutes ago", `admin-integrations` #15/#50, `admin-members` #94 — which correctly refuses it).
   `V-admin-settings` R4 caught that `human_datetime()` cannot produce it. No owner, no decision.
7. **Timestamp formatter conflict.** `D-admin-overview` S5 proposes a *new* `audit_datetime()`;
   `D-admin-integrations` #15 proposes reusing `human_datetime()`. Same register, two answers.
8. **`.eyebrow` is now settled in the opposite direction from four reports.** The current design has
   no page-head eyebrow on any admin screen (`grep -rn "Operator desk"` across the design system →
   one hit, `admin.card.html:43`, documenting its *removal*). Production ships one on
   `dashboard.php:6`, `settings.php:14`, `branding.php:11`, `custom_emoji.php:12`, `audit.php:12`,
   `features.php:6`, `moderation.php`, `thread_intelligence.php:6`. Nobody wrote the decision:
   delete the eight, or keep them and record the divergence.
9. **`.pill-admin` blast radius.** `V-shell` M5: it is not only the head chip — it is also
   `Recovery` (`theme_safe_mode.php:11`) and, critically, the **execution-disabled emergency status
   pill** at `package_security.php:18`. Recolouring it globally repaints a kill-switch indicator in
   "needs review" amber. Four reports propose the recolour; none scopes it.
10. **Responsive contract as a standing criterion.** Every new grid in every slice needs the ≤860px
    collapse, and the design's tier has its own answer (`overflow-x: auto` + thin scrollbar,
    `components.css:335–339`) that collides with production's drawer + the no-JS expanded-grid
    fallback (`app.css:3292–3301`). Assigned per-screen in two reports, standing nowhere.
11. **`npm run evidence` does not cover the screens in scope.** I read
    `tests/browser/package.json`: `evidence` runs `gate-a`, `server-drafts`, `appeals`, `group-dms`,
    `api-tokens`, `providers`, `invitations`, `thread-intelligence`, `composer-shell`,
    `admin-features`, `admin-remediation`, `admin-dashboard` (+ three thread specs). It does **not**
    run `webhooks`, `package-security`, `package-review`, `package-integrations`,
    `role-assignments`, `passkeys`, `totp`, `profile-surface`, `imladris-forum-surfaces`. Several
    slices say "run `npm run evidence`" and would leave their own surface uncaptured. There is also
    **no `content-console.spec.ts`** (structure/tags have no dedicated spec at all).

### 1.6 One factual contradiction between reports that must be settled now

**F3's headline finding — "`/admin/thread-intelligence` has no feature-flag guard … Fix regardless
of design adoption" — is wrong**, and `D-shell` S50 repeats it as an escalation.
`V-admin-settings` row 53 has it right and I verified the pin:
`tests/Integration/Admin/AppAdminThreadIntelligenceTest.php:29` —
`test_dashboard_is_admin_only_readable_with_flags_off_and_never_discloses_credentials_or_evidence_text`
sets both flags false, asserts **200**, and asserts `Both product flags are off` renders. The 200 is
deliberate rollback reachability (ADR 0019); the nav entry is flag-gated at `_nav.php:48`. "Fixing"
it turns the suite red.

---

## 2. CROSS-SCREEN DUPLICATION → ONE PARTIAL, ONE CSS BLOCK

Anatomy that appears on 2+ screens and must not be re-authored per page. Screens listed are the
design screens whose adoption touches it.

| # | Partial / CSS block | Screens | What it is |
|---|---|---|---|
| 1 | `templates/partials/admin_bar.php` + `.admin-bar*` / `.admin-tier*` | all 10 admin | Identity row (mark, wordmark, exit link, mode pill) over the area tier. One `<x-import>` on every screen. Skin at `components.css:328–342`. |
| 2 | `templates/partials/section_tabs.php` + `.section-tabs*` | all 10 admin + `/mod` | Underline sub-tab strip, `aria-label="… sections"`, `aria-current="page"`, 2px `--gold-500` active rule, `margin-bottom:-1px`. **Production already has it** as `.mod-subnav` (`app.css:4522–4553`) — it is a rename + 2px padding + .02rem type, not new authoring (V-shell R4). |
| 3 | `templates/partials/flash.php` + `.flash` / `.flash-secret` / `.flash-error` | shell, overview, content, members, features, integrations, packages, account | Design flash = `--surface-done` + `--green-200` + 3px `--success` left rule + 16px check SVG, placed **inside** the pane below the tabs. Only three declarations differ from production (V-shell R7). Gold `.flash-secret` variant for minted credentials (tokens, webhook secrets, invitation links) — use `--gold-soft`, **not** `--gold-050`. Four separate slices currently propose this independently. |
| 4 | `.card` (settings-scoped sibling for account) | every screen | `--surface-raised` / `--border-hair` / `--radius-lg` / `--shadow-xs\|sm` / `padding:18px 20px`. `app.css:159` currently forces `--surface`/`--border`/7px and wins (unlayered beats layered). One decision, one block. Interacts with `.scribe-panel` (see #19). |
| 5 | `.admin .audit` table register | audit, tokens, webhooks, deliveries, providers, packages catalogue, releases, registry keys, users directory, invitations, emoji, themes, badge rules, TI evidence, roles, assignments | `th` `.66rem`/`.12em`/`--text-faint` + `--border-soft` rule; hairline rows; mono `--text-faint` nowrap timestamps; right-aligned numeric columns; `<code>` chips at `--radius-sm`/`.76rem`. |
| 6 | `.state-*` / status-pill map | tokens, webhooks, deliveries, providers, packages, themes, invitations, users, role assignments, email log, TI outcomes, tags | Filled pills over the four semantic pairs (`--surface-done/--on-done`, `--surface-pending/--on-pending`, `--surface-review/--on-review`, rust wash + `--danger`). Must **author the missing `.state-scheduled` and `.state-expired`** (today they fall through to `--ink-300`). |
| 7 | `templates/partials/empty_state.php` + `.state-empty` | audit, tags, users, tokens, endpoints, deliveries, invitations, packages, registries, emoji, badge rules, drafts, blocks, subscriptions, TI evidence, roles | Centred `h3` + explanatory `p` (+ optional reset control), replacing 15 one-line `colspan` muted rows. |
| 8 | `templates/partials/pager.php` + `.pager` | audit, tags catalogue, users directory, email delivery log | `Previous` · `Page N of M` · `Next`; ends render a **disabled `<span aria-disabled>` that emits no `href`** (`AppAdminEmailTest:315` asserts `page=2` is absent). `aria-label` per ADR 0023 item 5. |
| 9 | `.filter-grid` / `.filter-actions` + result label | audit, users directory, email delivery log, tags catalogue | Uppercase captioned labels above controls; `align-items: flex-end` (**not** baseline — V-admin-notifications R4); Apply + Reset (Reset is a GET link, PE); right-aligned `N items` count inside the action row. |
| 10 | `.confirm-card` + `.impact-list` | structure_confirm, tag_merge_confirm, users_bulk_confirm, package_plan, package_consent, provider_disable, badge_rule_preview, theme_safe_mode | Rust 3px left rule, `--shadow-sm`, uppercase micro-`<dt>` + mono `<code>` `<dd>`, danger + ghost button pair. |
| 11 | `templates/partials/back_link.php` | role_edit, user_record, users_bulk_confirm, webhook_detail, provider_disable, package_detail/plan/consent/security/publisher, badge_rule_preview, board_edit | Chevron + label. **Production has zero back affordances on any drill-in**; the design has one on nine. |
| 12 | `.callout` family (info / review / danger) | roles resolver posture, features corrupt overrides, packages extensions, TI needs-attention, branding custom-CSS flag-off, email not-ready + blocked, structure blocked-delete, announcements broadcast reach, settings invite conflict, safe-mode forced | Left-ruled washes over the semantic pairs. No `.callout` class exists in `app.css` today. |
| 13 | `.reauth-field` + one label string | roles ×4, themes ×3, tokens, webhooks ×3, providers ×3, packages ×12, user_record role, account security ×4, lifecycle ×2 | ~30 sites. Production uses **three** different labels today (`Confirm your password`, `Current password`, `Your password (re-authentication)`). Pick one; never drop the field. |
| 14 | `.check-grid` (2-col bordered fieldsets, gold-ink legends) | roles capabilities, token scopes, webhook events, package permissions, badge-rule config, board settings | `<code>key</code> — description` rows with a `High risk` / `(not yet enforceable)` marker slot. |
| 15 | `.spec-list` (`<dl>`, uppercase micro-`<dt>`, mono `<dd>`) | themes active theme, TI generation contract, package provenance/installation/plan, user_record status, merge/delete impact | `components/doc.css` is deliberately outside the runtime closure and asserted absent — author it in `app.css`. |
| 16 | `monogram_*` row treatment | users directory, bulk-confirm subjects, badge preview, blocks, provider sole-accounts, connections provider mark, user record identity | Helpers already exist (`src/Support/helpers.php:21,29`); nothing is rendered today. |
| 17 | `.table-scroll` region + `data-overflow-cue` | every table on every screen | ADR 0023 item 5 + ADR 0021 sweep. Carries a **landed bugfix** (`app.css:3223–3227`, `position: relative`, without which absolutely-positioned `.sr-only` headers stretch the layout viewport on mobile Chrome). The design's bare `overflow-x:auto` must never be copied. |
| 18 | `.admin-split` / two-up grid family | package detail ×2, user record ×3, integrations ×3, notifications, settings general, TI contract+evidence, invitations, branding, roles capabilities | `repeat(auto-fit, minmax(330px,1fr))` and `330px 1fr`, all collapsing at 860px. |
| 19 | `templates/partials/settings_nav.php` + `.settings-rail` | account-settings | Grouped rail mirroring the `_nav.php` group idiom (`app.css:2859–2878`), icons via `partials/icon.php`, `aria-label`, `aria-current`. |
| 20 | Account panel substrate + `scribe-panel-head` | 13 account panes | `.scribe-panel` is used **only** in `templates/account/` (7 files — V-account R1 refutes "used across admin"), but it is a shipped **design-system component** in `resources/imladris/components.css:237–248` and is pinned by `AppImladrisFidelityTest` at `:69-70, :138, :143, :170-171, :174`. Retiring it is a DS-inventory change, not a restyle. |

---

## 3. SEQUENCING — final implementation order

Foundations first (0–4), then bodies by risk-adjusted value, then account panes, then the two
conditional slices. Every slice ends with: CSP scan
(`rg -n "<script|<style| on[a-z]+=" templates/ -S`), `vendor/bin/phpunit` read to completion with a
private `DB_TEST_DATABASE`, the named Playwright specs on **desktop and mobile**, a
`javaScriptEnabled:false` context over the touched routes, screenshots to
`docs/evidence/<slice>/{desktop,mobile,comparisons}/`, and **one** digest refresh
(`php bin/build-imladris-assets.php --print-application-digest` → `application_surface.sha256` →
`composer check:imladris && composer verify:imladris`). Only one slice may be in flight against the
baseline file at a time.

### Slice 0 — Adjudication ADR (no code)
- **Touches:** `docs/adr/0024-imladris-admin-account-adoption.md`, `docs/superpowers/plans/2026-08-03-imladris-admin-account.md`, `docs/design-system/imladris/LOCAL_RECONCILIATION.md`.
- **Decides (each is currently unresolved and blocks code):** (a) horizontal 10-area tier vs `ADMIN.md` §9.2 *"Left-nav, grouped"* — an **authoritative-spec amendment**, not a design call (V-shell R11); (b) whether an 11th `Moderation` area exists and where `Audit log` lives; (c) `variant=console` (drop `partials/sidebar.php`) vs ADMIN §9.4 *"reuse the app shell"* (V-shell M4); (d) does `.admin-bar` replace or stack under `partials/topbar.php` (V-shell N2 — decides whether every `calc(100vh - var(--topbar-h))` becomes `− 101px`); (e) does the admin surface keep bell/search/identity/log-out, which `AdminNav` deletes (V-shell N1); (f) `--topbar-h` 62→58 (21 call sites, V-shell R12); (g) eyebrow: delete production's eight admin eyebrows or diverge; (h) `Admin mode` pill placement **and** a separate class so `package_security.php:18` is not repainted; (i) container widths (1160 / 1100 / 1140 are still three values — V-shell N11); (j) responsive: design's scrolling tier vs production's drawer + no-JS expanded grid; (k) role-filtered tier for board moderators (ADR 0023 D1 404/403, ADMIN §9.4 least-privilege — V-shell N4); (l) `theme_safe_mode.php`'s `variant=plain`; (m) where `.admin-bar`/`.admin-tier` CSS lands; (n) `human_relative()` yes/no + one timestamp formatter; (o) `.scribe-panel` — retire from the DS or scope a sibling (M1/R1 on account); (p) fiction-remediation scope boundary; (q) `Release` vs `Remove`, `Reports open` vs `Reports`, and every other rename that moves a pinned selector.
- **Also records** every `feature-removed` gap from all twelve reports in one ledger.
- **Out of scope:** any template, CSS or JS edit.
- **Tested by:** nothing executes. Reviewed against DECISIONS > DESIGN > SCHEMA > ADMIN/USER, ADR 0019/0021/0022/0023.
- **Depends on:** the two missing V passes (§1.1).

### Slice 1 — Re-anchor + live-defect pre-fixes
- **Touches:** the plan doc (corrected design line index for all twelve screens); `public/assets/app.css`.
- **Fixes, before any adoption can be built on top of them:** (i) de-duplicate `.brand-preview-*` (`app.css:876–903` vs `:3515–3565`) — today the bar is pinned to the static token `--brand`, which `/brand.css` never emits, so it shows neither the typed nor the **saved** colour on any install (V-admin-appearance R6); (ii) add `--surface-staff`/`--on-staff` to the `[data-theme="system"]` dark block (F1 H5); (iii) TI status rail — all four cards emit bare `queue-card is-static` so `.queue-card::before` paints `--success` even on `Not ready` / `Paused`; (iv) author `.state-scheduled` / `.state-expired`.
- **Out of scope:** all design adoption. This is a defect slice.
- **Tested by:** new `AppBrandingThemeTest` case + a Playwright spec that types a hex and asserts the computed `background-color` of `.brand-preview-bar` changes **and** a JS-off render showing the saved colour; a contrast assertion on the staff pair under `data-theme="system"` + `prefers-color-scheme: dark`; `AppAdminThreadIntelligenceTest` extended for the non-success modifiers.
- **Depends on:** Slice 0 (m) only.

### Slice 2 — Shared console chrome
- **Touches:** new `templates/partials/admin_bar.php`; `templates/admin/_nav.php` (rewritten as the tier, drawer markup retained); new `templates/partials/section_tabs.php`; `templates/partials/flash.php`; `templates/layout.php` (variant branch, flash slot, suppress `[data-nav-toggle]` in the console branch — V-shell N3, announcement-banner placement — N12); `public/assets/app.js` (drawer retargeted); `public/assets/app.css` (`.admin-bar*`, `.admin-tier*`, `.section-tabs*` generalised from `.mod-subnav`, `.flash`/`.flash-secret`/`.flash-error`); the heads of all 39 admin templates.
- **Out of scope:** any per-screen body anatomy; any copy change except the verbatim-pinned `Disabled until the feature flag is enabled`; `/mod/*` (Slice 18).
- **Tested by:** `AppAdminNavIaTest:31,74`; `AppAdminDashboardRemediationTest:94–119`; `admin-dashboard.spec.ts:60–72` (`expectGroupedDirectory`), `gate-a.spec.ts:1261`; new — tier marks the right area per route; every flag-dark area renders the pinned note and **no link**; `javaScriptEnabled:false` reaching `/admin/settings` and all areas; full drawer contract (44px control, `inert`, Tab containment, Escape/scrim/link close, focus restore, body scroll lock, resize cleanup) **plus** the no-JS expanded-grid fallback at `app.css:3292–3301`; axe — three navs must not share an accessible name (V-shell N10).
- **Depends on:** 0, 1.

### Slice 3 — Shared component CSS + shared partials
- **Touches:** `public/assets/app.css` (blocks 4–10, 12–18 from §2); new `templates/partials/{pager,back_link,empty_state}.php`. Existing markup inherits what it can; no body rewrites.
- **Out of scope:** per-screen markup restructures; `.scribe-panel` retirement (Slice 15).
- **Tested by:** `AppImladrisFidelityTest` (this is where `.card`, `brand-cols`, `brand-preview`, `field-grid` pins bite); `ImladrisRuntimeAssetTest` (no `!important`, no token re-declaration in `app.css :root`, every required runtime var defined — `--gold-050` must **not** appear); Playwright visual diff on two unrelated admin screens in both themes; axe under `data-theme="system"` + dark.
- **Depends on:** 0 (m, o), 1.

### Slice 4 — Account rail + account shell
- **Touches:** `templates/partials/settings_nav.php` (three group headings via the `.admin-nav-group*` idiom, icons, reorder, `aria-label="Settings sections"` + `aria-current="page"`, keep the silent-omit flag idiom and the Replay-tour button); 13 `templates/account/*.php` heads (intro ¶, `drafts.php` h1 consistency); `public/assets/app.css` (`.settings-screen` 1000→1064, padding, `.settings` 188→232px, rail item skin, active marker 3px `--accent-2` inset → 2px `--gold-500` left rule).
- **Out of scope:** panel substrate, form controls, pane bodies; importing the admin disabled-note idiom into the member rail (that string is pinned to the admin nav); a `Regard` rail item.
- **Tested by:** `AppImladrisFidelityTest:145–167` (one `<main>` per page); new — rail item set per flag combination (`drafts`, `oauth`, `account_lifecycle`, `appeals`, `product_tour` on/off); `aria-current` on each of 13 routes; no-JS navigation to all destinations; axe.
- **Depends on:** 0, 3. **Not** dependent on 2.

### Slice 5 — admin-overview (dashboard + audit)
- **Touches:** `templates/admin/{dashboard,audit}.php`; `AdminDashboardService.php` (`recent(10)`→`recent(6)` only); `AdminController.php` (page count through both the happy and 422 paths); `helpers.php` (the single timestamp formatter from Slice 0(n)); `app.css`.
- **Out of scope:** renaming `Reports` → `Reports open` (`admin-dashboard.spec.ts:99–101` uses `toHaveText`, which is full-string equality — the plan's "substring pin survives" reasoning is wrong); the `→` on *View full audit log* as a CSS `::after` (Chromium includes generated content in the accessible name that `admin-dashboard.spec.ts:105` matches); an unfiltered-vs-filtered empty split (neither side models it); the loading skeleton; the per-panel error retry; the amber "Waiting" tier; attention ages; the three uncomputed Community-today metrics.
- **Tested by:** `AppAdminDashboardRemediationTest:264–274` (order), `:280` (byte-pinned audit link), `:284` (`data-overflow-cue`), `admin-dashboard.spec.ts:92,99–101,104,105,177`; new — `Page N of M`, disabled pager ends emitting no `href`, filtered-empty copy, the audit 422 preserving typed filter values, a non-user target rendering unlinked.
- **Depends on:** 2, 3.

### Slice 6 — admin-content (structure, tags, confirmations, board edit)
- **Touches:** `templates/admin/{structure,tags,structure_confirm,tag_merge_confirm,board_edit}.php`; `app.css`. Tag catalogue search/sort/uses/pager is a **separate sub-slice** (it is the only repository-signature change: `TagRepository::allForAdmin`, `TagController::renderAdminTags` must carry `q`/`sort`/`page` through every 422).
- **Out of scope:** one-click delete/archive (ADMIN §4.5 + ADR 0021 lock GET confirm + typed confirm + impact + the forced move-destination picker); the boundary-reorder error string (production's no-op is tested at `AppAdminStructureReorderTest:82`); category slugs; drag reorder (ADR 0021 #8); the design's *"Archiving hides a board without losing a word of it."* (archive is read-only-but-visible); the six deferred board fields (ADR 0021 #6).
- **Tested by:** `AppAdminStructureReorderTest`, `AppAdminArchiveTest`, `AppTagAdminTest:40,79–81`, `AppFieldErrorA11yTest:24–35`, `AppAdminTest:98`; new — escaped board description, `/c/{slug}` href, capitalised `Hidden`/`Private` chips, structure empty state, Tags tab disabled when the flag is off. Playwright: a **new** `content-console.spec.ts` (none exists) desktop+mobile + a no-JS walk of every destructive confirmation.
- **Depends on:** 2, 3.

### Slice 7 — admin-people (roles, record, simulator)
- **Touches:** `templates/admin/{roles,role_edit,role_simulator}.php`; `AdminRoleController.php` (only the missing server-side "Pick a capability to test." path); `app.css`.
- **Out of scope:** the roles search / segmented filter / filtered empty state (no filter exists; the table can never be empty — four system roles are seeded; and a search `<form action="/admin/roles">` breaks `gate-a.spec.ts:389`, `role-assignments.spec.ts:104` **and** `:204`); assignments on system roles (`RoleAssignmentService.php:71` refuses); hard-coding a capability list.
- **Tested by:** `gate-a.spec.ts:382,389,394`, `role-assignments.spec.ts`, `AppAdminNavIaTest:74`, `admin-features.spec.ts:104`, `a11y.spec.ts:178,208`; new — four assignment status chips, row-scoped renew 422 surviving a concurrent revoke, the create-role 422 keeping `capabilities[]` checked. Record that the design gives a custom role's record **no** capability editor, so production's fieldsets are `feature-added` and their restyle is an extrapolation (V-admin-people §1.4). Resolve ADR 0023 deferral #4 (`role_edit.php` has zero `field_error()` calls) or restate it.
- **Depends on:** 2, 3.

### Slice 8 — admin-appearance (branding, themes, safe mode)
- **Touches:** `templates/admin/{branding,themes,theme_safe_mode}.php`; `AdminThemeController.php` (map policy codes to sentences; carry the failing form's identity so activation/rollback errors anchor); `BrandingController.php` (reset-422 carrying the sibling form's `->old`); `app.css`.
- **Out of scope:** unconditional Custom CSS (flag OFF **and** safety-blocked); a `deactivate` button (no route exists); a real `GET /admin/themes/{id}/activate` confirmation route (its own ADR — use `<details>` for now); the eight-point star as "fiction" (it is shipped production chrome at `partials/topbar.php:11`).
- **Tested by:** all 11 `AppBrandingThemeTest` cases + 12 `AppThemePackageTest` cases; `AppImladrisFidelityTest:97–98` (`brand-cols` / `brand-preview` class names must survive the restructure); `AppFieldErrorA11yTest`; new — a 422 still reveals the custom-CSS textarea, the reset-422 preserves the typed site name, an `installed` package renders the pending pill.
- **Depends on:** 1 (preview fix), 2, 3.

### Slice 9 — admin-notifications (email + announcements)
- **Touches:** `templates/admin/{email,announcements}.php`; `EmailOpsService.php` (`total_pages`); `AnnouncementService::consoleModel()` + `UserRepository` (one new active-member count, excluding the actor); `app.css`; `app.js` (character counter).
- **Out of scope:** the F24 three-fact transport/From/domain block moving or shrinking (ADR 0023 item 3, pinned at `AppAdminEmailTest:68–72,93–97`); `verify`/`reset` kinds (structurally unreachable — those mails bypass `email_deliveries`); any promise of automatic bounce/complaint capture; the "log keeps thirty days" sentence (no retention purge exists); the design's empty `<th>` (reverts an ADR 0023 item-5 fix).
- **Tested by:** `AppAdminEmailTest:68–72,75–84,93–97,124–133,146,186–187,207–216,236–237,301–320,315`; `AdminAnnouncementTest:78,102`; `admin-remediation.spec.ts:200–223`. **If `Remove` → `Release` is adopted, `gate-a.spec.ts:1281` (`getByRole('button', {name:'Remove'})`) changes in the same commit** — the D report's claim that `AppAdminEmailTest:124–133` pins it is false. `gate-a.spec.ts:1266–1269` pins four headings by role — keep them `<h2>`.
- **Depends on:** 2, 3.

### Slice 10 — admin-settings (general, registration, Thread Intelligence)
- **Touches:** `templates/admin/{settings,thread_intelligence}.php`; `app.css`.
- **Out of scope:** the `Invitations feature is enabled` checkbox (enablement is a deliberate `settings.features` write); merging the two forms (`POST /admin/settings` is a 404 tombstone, pinned at `AppAdminDashboardRemediationTest:64–75`); the evidence `Digest` column (`request_fingerprint` is negatively pinned at `AppAdminThreadIntelligenceTest:63`); the `All`/`Failed only` filter; the three hard-coded contract literals; treating the flags-off 200 as a defect (§1.6); `/admin/features` (Slice 13 owns it — it has its own design screen).
- **Tested by:** `AppAdminDashboardRemediationTest:64–75,213–227`; `AppFieldErrorA11yTest:153–174`; `AppAdminThreadIntelligenceTest` (flags-off 200, `Both product flags are off`, `admin-safe-model`, `prompt-v1`, `Post #<id>` present; fingerprint absent); `thread-intelligence.spec.ts:39–42`; new — exactly two forms with distinct actions, six queue states zero-filled, all 13 `Needs attention` warnings retained.
- **Depends on:** 1 (rail colour fix), 2, 3.

### Slice 11 — admin-members (directory, record, bulk, invitations)
- **Gate:** blocked until `V-admin-members` exists.
- **Touches:** `templates/admin/{users,user_record,users_bulk_confirm,invitations}.php`; `AdminUserController.php` (bulk-specific reason message, actionable count); `app.css`; `app.js` (selected-count decoration on the existing `[data-bulk-toggle]` IIFE).
- **Out of scope:** relative timestamps (no `human_relative()` unless Slice 0(n) authorised it); ban types/durations/board scope and alt-account signals (ADR 0021 #4/#9); the client-side `banRequiresUsername` switch (production's server check is unconditional); `--gold-050` (use `--gold-soft`).
- **Tested by:** the 422/429 round-trip for every form context (`$error_context`/`$errs`/`$old`), the `bulk_selected` re-tick (ADR 0023 item 4), the typed-confirm ban server check, the warn `idempotency_key` replay, one `view_pii` audit row per reveal, role change with `capabilities` rolled back; `invitations.spec.ts`; new — bulk pre-flight `skipped — administrator` marker + actionable confirm-button count.
- **Depends on:** 2, 3.

### Slice 12 — admin-integrations (tokens, webhooks, detail, providers, disable)
- **Gate:** blocked until `V-admin-integrations` exists.
- **Touches:** `templates/admin/{api_tokens,webhooks,webhook_detail,providers,provider_disable}.php`; `app.css` — **not `imladris.css`**, correcting the D report.
- **Out of scope:** dropping the webhook delete or rotate re-auth (ADR 0021, `WebhookService.php:147–152`); dropping provider-enable re-auth or the `enable_error_id` row-scoped routing; the design's scope/event catalogues (production's are the enforced/wire contracts); `extensions.php` (Slice 14); claiming synchronous delivery (`worker:webhooks` is async); token-expiry honesty (needs an `expired` state first).
- **Tested by:** `api-tokens.spec.ts`, `webhooks.spec.ts`, `providers.spec.ts` (note `webhooks.spec.ts` is **not** in `npm run evidence` — invoke `npm run evidence:webhooks`/`evidence:integrations`); `AppAdminProvidersTest` (the `data-sole-count` anchor); new — em-dash secret labels, the 409 replay flash, 422 draft preservation on all four forms.
- **Depends on:** 2, 3.

### Slice 13 — admin-features (flags, badge rules, preview, emoji)
- **Touches:** `templates/admin/{features,badge_rules,badge_rule_preview,custom_emoji}.php`; `app.css`.
- **Out of scope:** `Ready for acceptance` (retired by ADR 0022 and negatively pinned at `AppAdminFeaturesTest:89` **and** `admin-features.spec.ts:91–92`); the design's readiness assignments (they downgrade `custom_css` from `Safety-blocked`); shortening `Effective on` / `Override on|off` (destroys the negative assertions at `AppAdminFeaturesTest:36,44–57` that prove fail-dark normalisation); the design's badge-rule **create** flash (`BadgeRepository::createRule` inserts `is_enabled = 0` — rules are created inert, pinned at `gate-a.spec.ts:1106`); renaming `Custom emoji saved.` without updating `a11y.spec.ts:487` + `gate-a.spec.ts:727`; rendering `BadgeRuleService::preview()['total']` as a total (it is `count()` of a LIMIT-100 page while `backfill()` runs at 1000); *"Assets are served from the media root"* (there is no `/emoji/*` route and no `public/emoji/`); a duplicate-rule error string without the unique index; the design's inline `position:absolute` `.sr-only` (use `app.css:692` and keep `.table-scroll`'s `position: relative`); any toggle.
- **Tested by:** `AppAdminFeaturesTest`, `AppAdminBadgeRulesTest`, `AppFieldErrorA11yTest:194`, `admin-features.spec.ts:83,91–92,96–97,110`, `gate-a.spec.ts:727,1084,1103,1106,1112,1127,1137`, `a11y.spec.ts:476,478,487,488`. Note `/admin/features` is admin-only but **not** flag-gated and must stay reachable with all 57 flags off (`AppAdminFeaturesTest:133–164`); `/admin/badge-rules` 404s a guest while `/admin/custom-emoji` 302s (gate ordering differs).
- **Depends on:** 2, 3.

### Slice 14 — admin-packages (catalogue, detail, plan, consent, security, publisher, registries, extensions)
- **Touches:** the nine `templates/admin/package*|registries|extensions` files + two `_package_*` partials; `AdminPackageSecurityController.php` (review-form `old` round-trip); `app.css`.
- **Out of scope:** the Extensions *"reserved and dark under Gate B"* callout copy — it is a factual lie on the only page state that can render (the controller 404s while the flag is dark); dropping the `package_uid` row from the reauth-gated install plan; dropping the `Advisories & blocklist` counts card **and** the security intro together (that strands the console with zero links to `/admin/registries`); putting `required` controls inside a closed `<details>` (Chromium aborts the submit silently); copying the design's label-less inputs over the ADR 0023 sr-only labels; the stale-snapshot alert on disabled registries.
- **Tested by:** `package-security.spec.ts`, `package-review.spec.ts`, `package-integrations.spec.ts` (all outside `npm run evidence` — use `evidence:packages` / `evidence:integrations`); `AppFieldErrorA11yTest`; new — the review-form `old` round-trip with `<details>` forced open on error, the preselected current review decision, every reauth still refusing without a password.
- **Depends on:** 2, 3.

### Slice 15 — account panes A: substrate, Profile, Security
- **Touches:** `app.css` (settings card substrate per Slice 0(o), `.input-engraved`, select chevron, field labels, one boolean idiom); `templates/account/{settings,security}.php`.
- **Out of scope:** the password strength meter; the QR "cipher" box; a 2FA cancel affordance; a persistent recovery-code grid (codes are HMAC-hashed); a typed-field profile schema; dropping any `current_password` gate; converting `<h2 class="scribe-panel-head">` to a `<span>`.
- **Tested by:** `AppImladrisFidelityTest:69–70,138,143,170–171,174` — **the single most likely red run in the whole plan**; `totp.spec.ts`, `passkeys.spec.ts` (both outside `npm run evidence` — use `evidence:passkeys`); new — full TOTP enroll→confirm→rotate→disable each still 422ing without a password; the profile 422 preserving `custom_label_N`/`custom_value_N`; the `custom_profile_fields` flag-off render staying clean; every converted checkbox round-tripping on and off.
- **Depends on:** 0(o), 3, 4.

### Slice 16 — account panes B: Privacy, Appearance, Reading, Composing, Notifications, Connections, Sessions, Blocks
- **Touches:** those eight templates; `app.css` (swatch/density, two-up grids, dividers, row anatomy); `app.js` (theme mirror onto `document.documentElement` as a decoration over the real POST).
- **Out of scope:** `Default sort` / `thread_sort` (retired at `PreferenceSchema::VERSION = 3`); `Hidden — wardens only`; `Members I have replied to`; per-event email switches; folding Composing into Reading (it is a real route); the false claim *"Email and password always stay available"*; the false claim *"Sessions expire after 30 days of inactivity"* (expiry is absolute).
- **Tested by:** new — `/settings/preferences` exposes exactly the v3 reading keys and no `thread_sort`; a JS-off test proving theme applies after POST and a JS-on test proving `dataset.theme` flips on change **and** reverts on reload without saving; the connections page never claiming email/password availability when `has_password` is false; axe on all eight.
- **Depends on:** 3, 4, 15.

### Slice 17 — account panes C: Boards, Drafts, Lifecycle (+ optional save-affordance decoration)
- **Touches:** `templates/account/{boards,drafts,lifecycle}.php`; `public/assets/composer.js` (the JS-built local-draft rows); optionally `app.js` + a fixed-bar partial + the `.flash` toast skin.
- **Out of scope:** a composer inside settings; the sticky unsaved-changes bar as the **only** save affordance (per-form submits stay); dropping the delete password gate for a typed `DELETE`; a client-only saved toast.
- **Tested by:** each of `board_folders` / `saved_feeds` / `bookmark_folders` rolled back individually; `server-drafts.spec.ts`; a no-JS test of deactivate→reactivate and delete-request→cancel; if the bar ships, a JS-off context proving every section still saves with no bar present.
- **Depends on:** 3, 4, 15.

### Slice 18 — `/mod/*` console chrome + the Moderation area *(conditional on Slice 0(b)(k))*
- **Touches:** `templates/mod/{reports,approvals,appeals,user}.php`, `templates/admin/moderation.php`, `templates/appeals/index.php`; `app.css`.
- **Out of scope:** showing a full admin tier to a board moderator (ADR 0023 D1 + ADMIN §9.4 least-privilege — the tier must be role-filtered or omitted); reports-queue bulk actions (ADR 0023 deferral #1); thread-level restore (deferral #2); a deputy-facing roster (deferral #3).
- **Tested by:** ADR 0023 D1 (404 without authority, 403 on action) still holds for every linked destination; `appeals.spec.ts`; axe.
- **Depends on:** 0, 2, 3.

### Slice 19 — De-fiction pass + baseline/evidence closeout
- **Touches:** the unpinned in-scope fiction only, per Slice 0(p): `admin/branding.php:20`, `appeals/index.php:5,28`, `account/privacy.php:40`, `mod/*` "Warden's table" ×4, `auth/login.php:4`, `auth/verify.php:7`; plus `config/imladris-runtime-baseline.json` and `docs/evidence/`, `docs/history/`, `PHASE_5_STATUS.md`.
- **Out of scope:** the four **pinned** fiction strings — `Removed by a warden` (`AppDeletedPostStubTest:38,49,60,89`, `thread-content-presentation.spec.ts:76`), `Commends` (`AppCouncilTopicFidelityTest:48,51`, `AppProfileFidelityTest:15`), `Private counsel` (`AppImladrisFidelityTest:293,302`), `sort=commends` (`AppProfileActivityTest:188–194`). Those need their own owner-approved change. Note `profile/show.php:269,270,314` also ships `Regard` as user-visible chrome — changing only `privacy.php` leaves the two surfaces disagreeing two clicks apart.
- **Tested by:** a repo-wide no-fiction assertion over the touched surfaces; `composer check:imladris && composer verify:imladris` green; `git diff --check` clean; visual QA of the full desktop/mobile + prototype-comparison sheets.
- **Depends on:** all prior slices.

---

## 4. THE UNREPRESENTED SET

Production surfaces with **no** design representation across all eleven design screens, and the
recommended policy for each. (Coverage arithmetic: `templates/admin/` holds 42 files, three
`_`-prefixed partials → **39 pages**; the eleven screens cover **37**.)

| Surface | Route | Flag | Policy |
|---|---|---|---|
| `templates/admin/moderation.php` (Anti-abuse: mode select + blocked words) | `GET/POST /admin/moderation` | `anti_abuse` (ON) | **Restyle to the shared chrome only** (Slices 2+3), no body adoption. Raise the ownership gap upstream — every other admin page has a design owner; this one has zero design content anywhere. Record in ADR 0024. |
| `templates/admin/board_edit.php` (settings + moderator roster + member roster) | `GET /admin/boards/{id}/edit` + four roster POSTs | core | **Adopt by extrapolation** inside Slice 6, explicitly labelled as extrapolation: reuse the Add-a-board grid for settings and the category-card/board-row anatomy for the rosters. The design draws an `Edit` link to nothing. Keep the four 422 roster re-render paths (ADR 0023 #3 keeps this admin-only). |
| `templates/mod/reports.php` | `GET /mod/reports` | `moderation_queue` (ON) | **Slice 18, conditional.** Restyle to the shared chrome; role-filter or omit the tier. Never show a board moderator ten destinations that all 403. |
| `templates/mod/approvals.php` | `GET /mod/approvals` | `moderation_queue` | same |
| `templates/mod/appeals.php` | `GET /mod/appeals` | `appeals` (ON) | same |
| `templates/mod/user.php` | `GET /mod/u/{id}` | core (ADR 0023 D1 = 404 without authority) | same |
| `templates/appeals/index.php` (member-facing) | `GET /appeals` | `appeals` | **Restyle to the account shell only** (Slice 4 rail entry already exists). The design's `AccountSettings` has no Appeals tab — record as `feature-added`. Also carries fiction (`Council record`). |
| `templates/account/composing.php` | `GET/POST /settings/composing` | core | **Keep the route; adopt the design's Composing *content***, which lives inside the Reading pane at `AccountSettings.dc.html:349–354` (three switches, verbatim in concept). The rail item is `feature-added`, not "never modelled" — correct `D-account-settings` H3/#65 accordingly. Slice 16. |
| `GET /admin/email/export` (CSV) | — | `email` | **Leave alone.** Behaviour-only, no page. Style only the link that reaches it (Slice 9). |
| `GET /settings/preferences/export` (download) | — | core | **Leave alone.** Style the link (Slice 16). |
| `POST /admin/link-previews/{id}/refresh|purge` | — | `link_previews` (**OFF**) | **Leave alone; defer with the ADR.** ADR 0021 deferral #7 already owns it as "Missing admin operations". Do **not** invent a console. |
| `templates/admin/extensions.php` | `GET /admin/extensions` | `server_extensions` (**OFF**) | **Represented but unshippable as drawn.** `AdminPackages` shows an always-live Extensions tab whose body claims the flag is dark — a state production cannot render. Ship the tab **disabled** with the pinned note; rewrite the callout copy. Record as `feature-removed` (V-admin-packages R1 — the D report's "none found" sweep is wrong). |

**Design-side sections with no production home** (do **not** build, do **not** ship dead chrome —
record each in ADR 0024): the `Regard` reputation-ledger pane; the password strength meter; the 2FA
QR square and Cancel; persistent recovery codes; the typed profile-field schema; `Hidden — wardens
only`; `Members I have replied to`; per-event email switches; `Default sort`; the drafts autosave
composer; the amber "Waiting" queue tier; attention-row ages; `Commends given` / three uncomputed
Community-today metrics; the audit error-retry; the roles filter bar and its empty state;
assignments on system roles; the category "old slug keeps working" claim; bounce/complaint
ingestion; the 30-day delivery retention; the invitations-flag checkbox; the evidence `Digest`
column; the `All`/`Failed only` evidence filter; `Ready for acceptance`; the recovery drill; a
`deactivate` theme button; relative timestamps.

---

## 5. RISKS

**R1 — `AppImladrisFidelityTest` is the plan's most likely red run, and only one report opened it.**
It pins `scribe-panel` on `/settings/account` and four more panes, `field-grid`, `gem-check` on
`/settings/privacy`, the literal `<h2 class="scribe-panel-head">Password</h2>` and
`<h2 class="scribe-panel-head">Two-factor authentication</h2>`, `<h2 class="scribe-panel-head">Daily
digest</h2>`, `brand-cols` / `brand-preview`, and one `<main>` per settings page. Slices 3, 15 and
16 each propose to delete or convert something it pins. *Mitigation:* Slice 0(o) decides
`.scribe-panel`'s fate before Slice 3 is scheduled; the answer must be "keep the heading element,
restyle it into the eyebrow register", never "convert `<h2>` to `<span>`".

**R2 — Six Playwright pins would go red from renames the reports treat as safe.** `Reports` →
`Reports open` (`toHaveText` is full-string equality); the `→` arrow via CSS `::after` (generated
content enters the accessible name); `Remove` → `Release` (`gate-a.spec.ts:1281`, and the report
names the wrong test); `Custom emoji saved.` (`a11y.spec.ts:487`, `gate-a.spec.ts:727`);
`Badge rule created.` and the two count regexes (`gate-a.spec.ts:1103,1127,1137`); the three heading
names at `admin-features.spec.ts:83` / `gate-a.spec.ts:1084,1112` under a single-h1 collapse.
*Mitigation:* Slice 0(q) adjudicates each rename; a rename and its spec change ship in the same
commit; and remember `.github/workflows/browser-evidence.yml` is the **only** CI — local PHPUnit
green is not green.

**R3 — Two negative assertions cannot survive a shortened label.** `AppAdminFeaturesTest:44–57`
asserts `Override off` present **and `Override on` absent** to prove `{"passkeys":"false"}` reads as
a rollback; `:89` asserts `Ready for acceptance` absent. You cannot assert the absence of a bare
`on`. *Mitigation:* keep the long strings, or land a replacement assertion anchored on a stable
class or `data-*` in the same commit.

**R4 — Anti-draft-loss is at risk on every restructured form.** 32 distinct 422 paths exist across
the inventory. Highest-density: `user_record.php` (six form contexts sharing `$error_context`),
`role_edit.php` (four scoped contexts, and ADR 0023 deferral #4 says its errors are *deliberately*
un-wired pending per-form id scoping), `structure.php` (`array_replace` so 422 context wins over
base keys), the account panes, `_package_review_form.php` (which has **no** `old` round-trip at all
— a pre-existing gap, worsened by the proposed `<details>`). *Mitigation:* every slice's test list
must include a POST-invalid-then-assert-typed-value-survives case per form it touches, plus the
`bulk_selected` re-tick and the `<option value="banana" selected>` fallback.

**R5 — Feature flags.** Four traps: the design renders every panel unconditionally, so Custom CSS
(`custom_css` OFF **and** safety-blocked) and Extensions (`server_extensions` OFF) would ship live;
the tab strips must never link a dark route (`AdminRoleController`, `AdminApiTokenController`,
`AdminWebhookController`, `AdminProviderController`, `AdminExtensionController`, `TagController` all
throw `NotFoundException`); `/admin/features` is admin-only but **not** flag-gated and must stay
reachable with all flags off; and `/admin/thread-intelligence` answering 200 with both TI flags dark
is **deliberate and test-pinned** — do not "fix" it (§1.6). Also note `/admin/badge-rules` gates the
flag *before* auth (guest → 404) while `/admin/custom-emoji` gates auth first (guest → 302).

**R6 — CSP.** ~2,174 inline `style=` + 193 `style-hover=` across the eleven screens, plus a
`<helmet><style>` per screen. Production has **zero** inline style attributes and
`SecurityHeaders::csp()` emits `style-src 'self'` with no `style-src-attr`. Every rule becomes an
external class. Two specific traps: the budget-meter fill (`style="width:67%"`) must come from
`<progress>` or a data-attribute bucket, never an inline width; and CSSOM writes
(`element.style.setProperty()` from external JS) *are* legal and are the sanctioned mechanism for
the branding preview. Standing gate: `rg -n "<script|<style| on[a-z]+=" templates/ -S`.

**R7 — The imladris asset build and `verify:imladris`.** `config/imladris-runtime-baseline.json`
digests `templates/**` and `public/assets/**` plus the four spec docs and `FeatureFlags.php`. One
character breaks `build:imladris`, `check:imladris` **and** `verify:imladris` (the exception comes
from the shared `expectedFiles()`). Three compounding hazards: (a) only
`application_surface.sha256` may be refreshed, from
`php bin/build-imladris-assets.php --print-application-digest`; (b) parallel slices will collide on
that one file — serialise it; (c) `D-admin-integrations` instructs edits into the **generated**
`public/assets/imladris.css`, which would be silently overwritten by the next build and fails the
contract. Never `!important` (the builder hard-refuses it); never re-declare a design token in
`app.css :root`; never transcribe `var(--gold-050)` (use `--gold-soft`).

**R8 — Layering.** `app.css` is entirely unlayered; `imladris.css` is entirely inside
`@layer imladris.*`. Unlayered normal declarations beat every layered one regardless of specificity,
so `app.css` wins every contested property — 181 of 211 design-system class names are contested.
Any "the design system already styles this" reasoning is wrong unless the property is uncontested.
And any new semantic colour token must be added in **three** places: `tokens/colors.css :root`,
`tokens/colors.css [data-theme="dark"]`, and `app.css`'s
`@media (prefers-color-scheme: dark) { [data-theme="system"] }` block — because `layout.php` defaults
to `system`, which `imladris.css`'s `[data-theme="dark"]` never matches.

**R9 — Authoritative-spec conflicts are being carried as "copy".** Replacing the grouped left-nav
with a horizontal tier contradicts `ADMIN.md` §9.2 (*"Left-nav, grouped"*), and dropping the member
sidebar contradicts §9.4 (*"Same look, distinct mode — reuse the app shell and tokens"*). ADMIN.md
outranks a design-system pull in the precedence chain. These require a recorded spec amendment, not
a restyle slice. Two reports labelled them plain `copy`; one labelled the whole design nav
`feature-added` (i.e. "the design is silent"), which is false.

**R10 — Mobile/no-JS regressions in the port.** The drawer contract (44px control, `inert`, Tab
containment, Escape/scrim/link close, focus restore, body scroll lock, resize cleanup) is locked by
ADMIN §9.4 and the 2026-07-18 plan; the **no-JS half** (`app.css:3292–3301`, "without JS the grouped
directory stays expanded above the page") is the part most at risk when the rail becomes a tier and
is omitted from the drawer inventory in `D-shell`. `variant=console` would also leave a dead
hamburger bound (`app.js:741–763` binds on `navToggle` alone).

**R11 — Evidence coverage is thinner than the slices assume.** `npm run evidence` omits `webhooks`,
`package-security`, `package-review`, `package-integrations`, `role-assignments`, `passkeys`,
`totp`, `profile-surface`; and there is **no** spec at all for structure/tags, branding/themes,
email/announcements, users/user_record, settings, roles list, registries, extensions, custom emoji
or badge rules outside `gate-a.spec.ts`. Under PRODUCT_DESIGN §13 every screen here is UI-visible, so
PHPUnit alone is never sufficient. Each slice must name the *specific* npm script (`evidence`,
`evidence:webhooks`, `evidence:packages`, `evidence:integrations`, `evidence:passkeys`, `a11y`) or
author a new spec.

**R12 — ADR/plan collision.** Eleven reports each propose `docs/adr/0024-*.md`; next free is 0024.
One ADR, one plan doc, one owner, or the deferral ledger fragments and PRODUCT_DESIGN §13's "never silently
dropped" rule fails by accident.
