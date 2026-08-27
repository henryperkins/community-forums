# F2 — Binding decisions for the Imladris admin/account adoption

**Analyst role:** binding-decisions. This document constrains what the migration is *allowed*
to change. Everything listed in §1 is a **CONSTRAINT** deviation (or a locked behavior the
design must not touch) — **not** a "copy" fix. Nothing here may be silently reverted.

**Screens in scope** (`docs/design-system/imladris/templates/`):
`admin-overview/AdminOverview.dc.html`, `admin-people/AdminPeople.dc.html`,
`admin-content/AdminContent.dc.html`, `admin-appearance/AdminAppearance.dc.html`,
`admin-notifications/AdminNotifications.dc.html`, `admin-settings/AdminSettings.dc.html`,
`account-settings/AccountSettings.dc.html`.

**Sources read in full:** `docs/adr/0021-*.md`, `docs/adr/0023-*.md`,
`docs/superpowers/plans/2026-07-02-admin-ux-remediation.md`,
`docs/superpowers/plans/2026-07-18-admin-dashboard-ui-remediation.md`,
`docs/superpowers/plans/2026-07-18-admin-audit-round2-remediation.md`,
`docs/superpowers/plans/2026-08-02-imladris-forum-surfaces-production.md`,
`DECISIONS.md`, `PRODUCT_DESIGN.md` (§5, §6.11–§6.14, §9, §13), `ADMIN.md`, `USER.md`,
`PHASE_5_STATUS.md`.

---

## 1. BINDING LIST

Each row: **source** → **what it locks** → **what a verbatim design copy would do instead**.
All are CONSTRAINT deviations.

### 1.1 Platform mechanics (apply to every screen)

**B1 — Server-rendered + progressive enhancement; no client rendering.**
- Source: `DECISIONS.md` §1 ("server-rendered + progressive enhancement", "vanilla PHP + a
  micro-router"), §2 Rendering row ("Server-rendered HTML + progressive-enhancement JS; no-JS
  fallbacks everywhere"); `PRODUCT_DESIGN.md` §5.3 ("JavaScript enhances navigation but is not
  required"); `2026-07-02-admin-ux-remediation.md` Global Constraints ("Keep no-JS form posts
  working; JavaScript may decorate but cannot be required");
  `2026-07-18-admin-dashboard-ui-remediation.md` Global Constraints ("Preserve
  no-JavaScript operation").
- Locks: every admin/account flow must work as plain server-rendered HTML + forms with JS off.
- Design would do instead: all seven screens are dc-runtime components — `sc-if` / `sc-for`
  client conditionals, `onClick="{{ … }}"` / `onInput="{{ … }}"` handler attributes, and a
  terminal `<script type="text/x-dc">` state machine (`AdminOverview.dc.html:277`,
  `AdminPeople.dc.html:352`, `AdminContent.dc.html:306`, `AdminAppearance.dc.html:247`,
  `AdminNotifications.dc.html:280`, `AdminSettings.dc.html:217`,
  `AccountSettings.dc.html:497`). None of it may ship. Renderer must be `templates/*.php`
  with `$this->e()` escaping; behavior moves to `public/assets/*.js` behind `data-*` hooks.

**B2 — Strict CSP: no inline `<script>`, `<style>`, or `on*=` attributes.**
- Source: `CLAUDE.md` "Security & authorization" (script-src 'self'; style-src 'self'; no
  `'unsafe-inline'`); `2026-07-02` plan Global Constraints ("Preserve CSP discipline: no inline
  `<script>`, `<style>`, or `on*=` handlers in `templates/admin`, `templates/mod`, or
  `templates/layout.php`") with a standing scan
  `rg -n "<script|<style| on[a-z]+=" templates/admin templates/mod templates/layout.php -S`;
  `2026-07-18-admin-audit-round2` Global Constraints ("New CSS goes in `public/assets/app.css`").
- Locks: every one of the design's `style="…"`, `style-hover="…"`, `style-focus="…"` attributes
  and every `<helmet><style>…@keyframes adRise/adPulse…</style></helmet>` block must become
  external CSS classes. Pixels/spacing/order must still match.
- Design would do instead: ~every element carries an inline style; hover/focus states are
  pseudo-attributes; two keyframe animations live in an inline `<style>`.

**B3 — Design-system preview JS and kits never ship.**
- Source: `PRODUCT_DESIGN.md` §6.14 "Imladris runtime ownership" — "design-system preview JavaScript,
  UI kits, documentation CSS, uploads, archived app snapshots … never ship"; `2026-08-02`
  plan Global Constraints ("Do not edit generated `public/assets/imladris.css`, add inline
  script/style, ship prototype runtime code, create handcrafted SVGs, or add dependencies").
- Locks: `templates/*/support.js` and `templates/*/ds-base.js` are reference-only.
- Design would do instead: each screen `<script src="./support.js">` +
  `<helmet><script src="./ds-base.js">`.

**B4 — Generated `imladris.css` is the token layer; app CSS is the feature layer; the
application-surface digest is a gate.**
- Source: `PRODUCT_DESIGN.md` §6.14; `config/imladris-runtime-baseline.json`
  (`application_surface.roots = ["templates","public/assets"]`, `files` includes `ADMIN.md`,
  `USER.md`, `COMMUNITY.md`, `COMPOSER.md`, `src/Core/FeatureFlags.php`; `excluded:
  public/assets/imladris.css`); `2026-08-02` plan Task 5 Step 7 (refresh **only**
  `application_surface.sha256` from
  `php bin/build-imladris-assets.php --print-application-digest`, keep
  `reconciled_through_commit`/`composer_contract`/roots/files/extensions unchanged).
- Locks: any template/CSS/JS edit flips the digest; `composer verify:imladris` fails until the
  digest is deliberately refreshed after review. It also checks feature-flag parity and the
  composer anatomy.
- Design would do instead: nothing — but a migration that edits templates without the digest
  refresh + `composer verify:imladris` run cannot be called done.

**B5 — CSRF on every POST; no new exemptions; escaping via `$this->e()`/`$e`.**
- Source: `CLAUDE.md` (the only exemption is the OAuth callback); `2026-07-18-round2` Global
  Constraints ("CSRF via `$this->csrfField()`; escape via `$e()`").
- Design would do instead: no forms at all — every mutation is an in-memory `setState` closure.
  Each becomes a real `<form method="post">` with `$this->csrfField()`.

**B6 — Anti-draft-loss: failed writes re-render the originating context at 422 with
`->errors` + `->old`.**
- Source: `2026-07-02` plan P1 item + Global Constraints; ADR 0021 "Anti-draft-loss re-renders
  for split/merge/move, role clone, appeal resolution, announcements (429), dashboard settings";
  ADR 0023 item 4 (custom emoji, email suppress/unsuppress) and item 1 (over-length moderation
  input → 422 with input preserved); `2026-07-18-dashboard` Task 1 ("site, registration,
  anti-abuse, and emoji validation returns 422 with field errors and drafts intact").
- Locks: restructuring a form must preserve the old-value round-trip.
- Design would do instead: `AccountSettings.dc.html` replaces per-section POSTs with one
  client-held dirty buffer and a sticky "You have unsaved changes · Discard · Save changes" bar
  and a `touch`/`setState` model. Copying that literally destroys the 422 round-trip for
  ~13 panels. Production must keep **one server-owned form per section**; the sticky bar may
  only be a JS decoration over the real submit button.

**B7 — Short-polling only; no WebSockets, no SSE in v1.**
- Source: `DECISIONS.md` §2 Realtime row ("Short-polling for the bell + presence; SSE later if
  needed; no WebSockets in v1"), §3 #4 and #13; `PRODUCT_DESIGN.md` §6.15.
- Design would do instead: `AdminOverview` shows a pulsing "Live" chip (`@keyframes adPulse`)
  on Queue health and `AdminSettings` shows "Heartbeat · Nominal · Last run 6 minutes ago". Both
  must be server-rendered per request (or refreshed by an existing polling endpoint), never a
  socket/stream.

**B8 — Feature flags gate availability; a default-off flag's section must not render
unconditionally; dark routes must not be linked.**
- Source: `src/Core/FeatureFlags.php`; `2026-07-02` plan Task 1 ("When a default-dark flag is
  disabled, render a non-link disabled nav item with short copy such as `Disabled until the
  feature flag is enabled`; do not point operators at a 404"); ADR 0023 D1 (`/mod` posture);
  `PHASE_5_STATUS.md` "Dark-flag readiness audit (2026-07-13)" ("no link to any console that
  would 404 while its flag is dark (deliberately still no toggles)").
- Still default **OFF**: `custom_css`, `link_previews`, `expanded_files`, `server_extensions`,
  `governance`, `service_principals`, `verified_links`.
- Design would do instead: renders every panel unconditionally. Most relevant: the
  **Custom CSS** block in `AdminAppearance` (see B21).

**B9 — The exact disabled-nav copy string is regression-pinned.**
- Source: `2026-07-18-round2` plan Task 9 Step 2 ("keep `$disabledNote` copy verbatim —
  regression tests reference it"); `templates/admin/_nav.php:5`
  `$disabledNote = 'Disabled until the feature flag is enabled';`.
- Design would do instead: has no disabled state at all.

### 1.2 Admin console IA and safety

**B10 — The eight-group admin nav is locked (ADMIN §9.2), not a decorative subnav.**
- Source: `ADMIN.md` §9.2 (Dashboard · Moderation · Content · People · Appearance ·
  Notifications · Integrations · Settings); ADR 0023 shipped item 6 ("Console IA per ADMIN §9.2:
  grouped admin nav … with real Moderation entries"); `2026-07-18-round2` Task 9 (the exact
  group→item map); `2026-07-18-dashboard` Task 1 ("assert all eight navigation group labels,
  exact destinations, `aria-current`, and disabled explanations") and Task 4 (224px desktop
  grid above 860px, mobile drawer with `data-admin-nav-toggle` / `data-admin-nav` /
  `data-admin-nav-close` / `data-admin-nav-scrim`). Production: `templates/admin/_nav.php:7-51`.
- Design would do instead: **every admin screen renders a two-button local tab strip plus a
  dead, non-interactive `<span>`**: `AdminOverview.dc.html:47`
  `<span …>Moderation · Content · People · Appearance · Notifications · Integrations ·
  Settings</span>`. Shipping that is dead chrome (explicitly forbidden) *and* reverts ADR 0023
  item 6. The 8-group nav must stay; the design's local tabs may only be adopted **as the
  in-section subnav on top of it**, and each tab must be a real route.

**B11 — Local tabs must be real routes, not client state.**
- Source: `PRODUCT_DESIGN.md` §5.3 (real, shareable, crawlable URL per view); `2026-07-18-dashboard`
  Task 5 ("Disable JavaScript in a dedicated context and prove grouped navigation reaches
  `/admin/settings`").
- Design tabs → real production routes (verify against `src/Core/App.php::buildRouter()`):
  Dashboard `/admin` · Audit log `/admin/audit` · Boards & categories `/admin/structure` ·
  Tags `/admin/tags` · Roles `/admin/roles` · Permission simulator `/admin/roles/simulator` ·
  Branding `/admin/branding` · Themes `/admin/themes` · Email `/admin/email` ·
  Announcements `/admin/announcements` · General & registration `/admin/settings` ·
  Thread Intelligence `/admin/thread-intelligence`.

**B12 — Destructive actions are never one-click; typed confirmation + impact copy is locked.**
- Source: `ADMIN.md` §4.5 ("Destructive actions (delete board, delete category) require typed
  confirmation and show impact"), §9.4 ("Safe by default — confirm destructive actions (typed
  confirmation for irreversible ones), show impact"); `2026-07-02` plan Task 5 Acceptance
  ("Destructive actions are not one-click from `/admin/structure`"; "A direct POST without
  confirmation returns 422 or a non-mutating refusal"; "Confirmation pages work with JavaScript
  disabled"), Task 6 (tag merge typed confirmation), Task 7 (badge backfill/revoke typed
  confirmation), Task 8 (API-token revoke typed confirm + password reauth); ADR 0021 (typed-confirm
  ban and branding reset; board delete-with-move).
- Design would do instead: `AdminContent.dc.html:71` `Delete category`, `:99` `Delete`, `:98`
  `{{ archiveLabel }}` are **immediate** — the x-dc handlers `cat.remove` (`:148` of the script
  region), `b.archive`, `b.remove` mutate state with no confirm step, no impact stats, and no
  destination picker for the board's threads. This is a head-on conflict (see §3).
- The design's **tag merge** confirmation (`AdminContent` "Merge … Impact … (includes hidden,
  held, and deleted threads)") *does* match ADR 0021's shipped merge-impact page — adopt it.

**B13 — Structure reorder stays ↑/↓ button forms; drag-and-drop is a recorded deferral.**
- Source: ADR 0021 deferral #8 ("The ↑/↓ button forms remain the mechanism; `POST
  /admin/structure/reorder` is retained (tested) … The dead `data-reorder-*` attributes were
  removed so the DOM stops promising JS that does not exist"); `ADMIN.md` §4.1/§4.5 asks for
  drag-to-reorder — **deferred, not dropped**.
- Design agrees (`AdminContent.dc.html:69-70`, `:95-96` are `Move category up/down` /
  `Move board up/down` buttons). No new drag affordance may be introduced.

**B14 — `/admin/audit` is admin-only site-wide; a board-scoped moderator audit view is deferred.**
- Source: ADR 0021 deferral #5 (`mod.log.view`, ADMIN §3.6 — "`moderation_log` rows do not carry
  a board id"); ADR 0021 shipped item ("`/admin/audit`: filterable, paginated audit-log screen
  (admin, site-wide)"); `ADMIN.md` §3.6 (append-only).
- Design's audit filters (Actor · Action · Target type · Target # · From · To; columns When ·
  Actor · Action · Target · Reason · Change) match production
  `templates/admin/audit.php:26-74` — adopt verbatim. Its "The log could not be read / The audit
  trail is append-only and intact — this is a read failure, not a gap in the record" error state
  is new and good, but must be a server-rendered state, not a fetch failure.

**B15 — Reports-queue bulk actions remain deferred.**
- Source: ADR 0023 deferral #1 (ADMIN §3.2 fourth bullet: "select many → dismiss / delete /
  lock in one step"). Needs row-selection UI + per-item-audited bulk transaction + partial-failure
  semantics.
- Design shows no reports queue at all, so no new bulk affordance may be invented here.

**B16 — Thread-level (OP) restore has no surface; per-reply deleted stubs shipped.**
- Source: ADR 0023 deferral #2 and shipped item 2 (`templates/partials/post_deleted.php`).

**B17 — Deputy-facing roster surface is deferred.**
- Source: ADR 0023 deferral #3 ("roster forms render only on the admin-only board-edit console;
  the deliberate isolation comment in `AdminController::rosterDeputyExit` stands").
- Design's `AdminContent` board editor is admin-only in production; do not widen it.

**B18 — `/mod` posture rule: zero-authority browse → 404; unauthorized action → 403.**
- Source: ADR 0023 **D1**; `ADMIN.md` §9.4 "hide what a role can't do".
- Applies to any `/mod/*` link the admin nav renders (Reports, Approvals, Appeals).

**B19 — `/admin/email` states one fact per line (transport / From / sending domain).**
- Source: ADR 0023 shipped item 3 ("F24 fixed for real … 'Sending is configured' can no longer
  render beside 'Set a From address…'"); `2026-07-18-round2` Task 4. Production:
  `templates/admin/email.php:19-46` (`.email-status-facts`).
- Design would do instead: `AdminNotifications` opens straight at "Sending domain" with
  SPF/DKIM chips and no transport/From honesty block. The three-fact block **must survive**
  above the domain card.

**B20 — Email-template editing, preview and test-send of *templates* remain deferred; the
staff alert matrix / staff inbox remains deferred.**
- Source: ADR 0021 deferrals #1 and #2 (ADMIN §7.4/§7.5 and §7.1–§7.4; the staff matrix is the
  sibling of the member matrix deferred in ADR 0014 — "both should land on one preference-matrix
  substrate").
- Design correctly shows neither. Do not add them.
- The design's **"Send a test email"** card is *not* the deferred item — production already has
  it (`templates/admin/email.php:83-86`); adopt.

**B21 — Custom CSS is flag-gated and currently safety-blocked.**
- Source: `ADMIN.md` §6.2 (Custom CSS = P2, "gated behind an 'advanced' toggle"); `FeatureFlags`
  `'custom_css' => false` (ADR 0009); `PHASE_5_STATUS.md` "Dark-flag readiness audit
  (2026-07-13)": `custom_css` is **safety-blocked** — "theme safe mode does not suppress the
  `custom_css` block in `/brand.css` (`BrandingController::css()` checks only the flag and
  `brand_custom_css_enabled`)". Production already guards it:
  `templates/admin/branding.php:77-92` (`$custom_css_available`, else the explanatory muted line).
- Design would do instead: `AdminAppearance` renders "Enable custom CSS", the textarea, and the
  acknowledgement checkbox unconditionally. It must stay behind `custom_css_available` with the
  existing "not available on this install" explanation.

**B22 — Registration settings: only open / invite / closed. Approval mode, verification-requirement
toggle, password policy, and the rate-limit editor stay deferred.**
- Source: ADR 0021 deferral #3 ("The UI ships only with the enforcement (inert settings are not
  evidence)"); ADR 0023 **D2** ("§9.2 People 'Approval queue' is the *registration* approval mode
  — still deferred"); `src/Security/RegistrationPolicy.php:24` `MODES = ['open','invite','closed']`
  and `effectiveMode()` degrading `invite`→`closed` while `invitations` is dark.
- Design matches exactly — including the warning "Registration mode is 'invite' but the
  invitations feature is off — registration is effectively closed." Adopt; do **not** add an
  "approval" option.

**B23 — Thread Intelligence console is a *credential-free* control plane with a fixed
observe/recover/curate/retain/roll-back contract.**
- Source: `ADMIN.md` §3.10; `DECISIONS.md` §1 + §2 (replaceable seams
  `ThreadIntelligenceProvider`, `ThreadIntelligenceOutputModerator`, `OpenAiTransport`);
  ADR 0019; `docs/runbooks/thread_intelligence.md` owns the canonical settings writes.
- Locks: effective flags, credential readiness, **validated** model/effort, global pause +
  provider-latch health, worker heartbeat, queue-state counts, UTC daily call/input-token budget
  and next reset, configuration warnings, redacted generation evidence; audited POST+CSRF
  pause/resume/clear-latch/retry/reconcile. Rollback order is
  `automated_context=false` → `community_memory=false` → remove credential.
- Design would do instead: `AdminSettings` hard-codes a **Generation contract** panel reading
  `Model: claude-sonnet-4-6`, `Reasoning effort: medium`, `Prompt version: ti.summary.v7`. These
  must render the configured/validated values through the provider seam, never literals. (The
  currently-selected live contract per `ADMIN.md` §3.10 is `low` / `16000`.) The design's
  "Provider retry clears only the current health latch. Configure credentials outside this page."
  is correct and must be kept.

**B24 — Anonymous authorship is masked at render; unmasking is only the audited reveal.**
- Source: `DECISIONS.md` §4 #1; `ADMIN.md` §1.3 / §3.4 (`mod.anon.reveal`); ADR 0021 post-review
  decision ("Anonymous authors stay masked in the reports queue … unmasking remains exclusively
  the audited reveal on the post itself"); `CLAUDE.md` ("Anonymous authorship is masked at render
  time, never stored masked").
- Any actor/author byline the design renders (audit rows, moderation rows, People screen) must
  pass through `mask_author()`.

**B25 — Private staff notes are admin-only.**
- Source: ADR 0021 post-review decision narrowing `ADMIN.md` §3.4's `mod.user.warn` mapping
  (`user_notes` is globally scoped). Do not widen on the People/user surfaces.

**B26 — Ban types/durations/board scope and alt-account/device signals stay deferred.**
- Source: ADR 0021 deferrals #4 and #9 (enforcement rides `users.status` only; board rows and
  expiry sweeps do not exist; suspensions are recorded as `bans.type='post'`).

**B27 — Board settings that have no enforcement stay absent.**
- Source: ADR 0021 deferral #6 (icon/emoji, locked-state distinct from archived, allowed thread
  prefixes, category default-collapsed, bulk archive) — "adding fields now would be inert UI".
- Design's Add-a-board form shows Category · Name · Slug · Description · Visibility · Who can
  post · Edit window · Assignment mode · Allow anonymous posting · Require approval · Allow
  approved tags · Allow wiki-style post editing — i.e. it *correctly* omits the deferred six.
  Do not reintroduce them.

**B28 — `/admin/features` renders read-only readiness classification, with no toggles and no
links to dark consoles.**
- Source: `PHASE_5_STATUS.md` "Dark-flag readiness audit (2026-07-13)"; ADR 0021 deferral #7
  (link_previews ops console). The design has no Feature-flags screen; the existing one stays.

**B29 — Deputy/capability posture: `capabilities` is default-ON but operationally `shadow`.**
- Source: `PHASE_5_STATUS.md` (capabilities "operationally dormant" — posture still `shadow`;
  the enforce flip is an operational cutover per `docs/runbooks/capabilities.md`); ADR 0016;
  `EnforcedCapabilities` clamp (21 of 54 keys have a live route).
- Design's People screen already says exactly this ("Resolver posture: shadow
  (CAPABILITIES_MODE) …", "(not yet enforceable)", "Cloning copies only currently-enforceable
  capabilities"). Adopt — but the posture string must be read from config, not hard-coded.

### 1.3 Member account surface

**B30 — `thread_sort` is retired; board topic order is fixed.**
- Source: `2026-08-02-imladris-forum-surfaces-production.md` Task 1 Step 5 ("Remove `$sort` and
  the entire **Default thread sort** `<label>` from `templates/account/preferences.php`";
  `PreferenceSchema::VERSION = 3`; legacy JSON preserved, never deleted); `USER.md` §4.2 ("Board
  topic order | **Fixed: pinned first, then latest activity.** Newest and Unanswered are Inbox
  filters"); `PRODUCT_DESIGN.md` §5.2. Confirmed shipped: `src/Support/PreferenceSchema.php:141,180`;
  no `thread_sort` control remains under `templates/`.
- Design would do instead: `AccountSettings` Reading panel renders
  **"Default sort · Last post / Newest / Most replies"**. Copying it reverts a locked decision.
  See §3.

**B31 — Preference storage: client-only prefs apply instantly; server-side prefs are
server-enforced; unknown keys are preserved.**
- Source: `USER.md` §4.8; `2026-08-02` Task 1 Step 5 ("Do not add a v3 transform: … `resolve()`
  and `upgrade()` preserve it through the existing unknown-key path").

**B32 — Theme/density/font-size are flash-free, server-stamped on `<html>`.**
- Source: `CLAUDE.md` ("Theming is flash-free — server stamps `data-theme/density/...` on
  `<html>`"); `USER.md` §4.1 ("Appearance prefs are client-applied (instant) and override the
  site default for that user only").
- Design's "Applies the moment you choose it — the rest of this page follows." is compatible
  **only** as a JS decoration over a real POST.

**B33 — Sessions: DB-backed, opaque token, per-session revoke + log-out-everywhere-else;
credential changes revoke other sessions.**
- Source: `USER.md` §3.3; `DECISIONS.md` §5 #9; `CLAUDE.md` ("Call `revokeOtherSessionsFor()`
  after any credential change"); Phase 5 Inc 7 (enabling/disabling TOTP revokes other sessions).
- Design's Sessions panel copy "Sessions expire after 30 days of inactivity. Signing out a device
  revokes its token immediately." must be verified against the real session lifetime before it is
  shipped as a factual claim.

**B34 — Keep-at-least-one-sign-in-method.**
- Source: `USER.md` §2.4 / §3.4; Phase 5 Inc 8 (`OAuthIdentityRepository::soleMethodAccounts()`
  wired into the provider-disable confirm page).
- Design's Connections copy "Email and password always stay available." is **not** true for
  OAuth-only accounts — production offers "set password" (`POST
  /settings/connections/set-password`, `src/Core/App.php:2089`). Rewrite, don't paste.

**B35 — Account lifecycle: deactivate is reversible; delete is 30-day grace, cancellable,
posts anonymised to a "Deleted user" tombstone; protected-owner guard applies.**
- Source: `USER.md` §3.5; `ADMIN.md` §5.5; Phase 5 F5 (`LastOwnerGuard` consulted by
  `AccountLifecycleService::deactivate()`/`requestDeletion()` — last recoverable owner gets a
  422, not a 303).
- Design's Account panel matches shape (typed `DELETE` confirm, password for deactivate). The
  last-owner 422 path must be preserved and is not shown in the design.

**B36 — 2FA is TOTP + recovery codes; passkeys are a separate, shipped, default-on surface.**
- Source: `DECISIONS.md` §5 #12 (2FA P3, delivered Phase 3); `USER.md` §3.3; `PHASE_5_STATUS.md`
  Increment 7 (passkeys default-on 2026-07-09; add/revoke need a present factor, rename needs
  only session+CSRF; MFA settings carry the `mfa_settings` limiter).
- Design shows only TOTP. Passkeys are **feature-added** and must be kept, styled in the idiom.

**B37 — IP capture, retention and audited access.**
- Source: `DECISIONS.md` §4 #5 (login + post IPs, 90-day retention then purge/anonymise,
  Admin-only + audited); `ADMIN.md` §5.5; ADR 0021 shipped "audited PII reveal (email + recent
  IPs) on the user record". No account/admin screen may show raw IPs outside the audited path.

---

## 2. SPEC REQUIREMENTS THE DESIGN SCREENS DO NOT SHOW (must be PRESERVED)

These are things `ADMIN.md` / `USER.md` require and production already ships, but the seven
design screens omit. Dropping them is a spec regression, not a "copy" fix.

### 2.1 Admin console

1. **The whole user-management surface.** `AdminPeople.dc.html` is *only* Roles & capabilities +
   the permission simulator. `ADMIN.md` §5.1 (user directory: search + filters by
   username/email, role, state, join date, last-seen, post count, audited IP; sortable,
   paginated, bulk-selectable; a reduced read-mostly moderator variant), §5.2 (per-user admin
   record: identity/profile, role & scope, state controls with reason + duration + scope +
   type, history, mod notes, signals), §5.3 (assignment workflows; granting Admin requires a
   confirmation step and is audited), §5.5 (PII gating + `user.export`), §5.6 (verification
   status + resend). Shipped as `/admin/users`, `/admin/users/{id}`, `/admin/users/bulk`
   (ADR 0021: bulk moderation end-to-end, replacing the dead phantom UI) and `/mod/u/{id}`.
   **All of it must survive** the People-screen adoption.
2. **The `Integrations` section.** `ADMIN.md` §9.2 requires it; production ships Packages,
   Registry trust, Webhooks, API tokens, Sign-in providers, Extensions
   (`templates/admin/_nav.php:36-43`). No design screen covers it.
3. **Moderation queues in the console.** `ADMIN.md` §9.2/§9.3 require Reports queue and
   Approvals/Appeals reachable from the console; ADR 0023 shipped them as real nav entries plus
   an Appeals dashboard card and attention line. The design's dashboard shows the *cards*
   (Reports open / Approval hold / Appeals) but no nav path.
4. **Anti-abuse / automation rules.** `ADMIN.md` §9.2 Moderation → "Automation rules (filters,
   throttles, approvals)"; production `/admin/moderation` (flag `anti_abuse`).
5. **Badge rules, Invitations, Custom emoji.** Shipped console screens with locked safety
   behavior (badge backfill/revoke typed confirmation — `2026-07-02` Task 7; emoji 422 + honest
   "replaced" flash — ADR 0023 item 4). Absent from the design.
6. **`/admin/features` readiness console.** See B28.
7. **The three-fact email status block.** See B19.
8. **Board delete-with-move destination picker** (ADR 0021 shipped; ADMIN §4.4 "requires
   choosing what happens to its threads"). Absent from `AdminContent`.
9. **Accessible field-error wiring.** ADR 0023 item 5: shared `field_error()` / `field_attrs()`
   (error id + `aria-describedby` + `aria-invalid` + autofocus-on-first-error), `role="alert"`
   on inline error flashes, `scope="col"` on `<th>`, `aria-label` on every bespoke pager and on
   differentiated mod-queue row buttons, `.table-scroll` regions. The design's error paragraphs
   carry `role="alert"` in places but no `aria-describedby`/`aria-invalid` wiring.
   *Known residue (ADR 0023 deferral #4):* `registries.php` and `role_edit.php` are not yet
   wired — that is owned, not a new gap.
10. **Mobile drawer + 44px targets for the console.** `ADMIN.md` §9.4 Responsive;
    `2026-07-18-dashboard` Task 4/Task 5 (`data-admin-nav-*` hooks, 860px breakpoint, 44px
    control, focus trap/restore, Escape/scrim/link close, body scroll lock, resize cleanup) and
    the `data-overflow-cue` table scroll cue. The design screens are desktop-only compositions.
11. **Audit-everything.** `ADMIN.md` §3.6/§9.4 — every config and content change writes
    `moderation_log` with before/after JSON (`2026-07-18-dashboard` Task 1: "precise
    `moderation_log.before_json`/`after_json` payloads"). No design screen shows this obligation.
12. **`POST /admin/settings` must not return.** `2026-07-18-dashboard` Global Constraints
    ("Remove `POST /admin/settings`; do not add a compatibility alias") — settings are owned by
    `AdminSettingsService`/`AdminSettingsController`; the dashboard carries **no** settings or
    emoji forms (Task 3).
13. **Suspension/ban semantics.** ADR 0021: suspensions are recorded as `bans.type='post'`
    (read-only), never `full`.

### 2.2 Member account surface

14. **Identity fields.** `USER.md` §3.2 requires Display name, **Username** (change allowed but
    rate-limited once/30 days; old handle reserved + `/u/{old}` 301; history kept —
    `DECISIONS.md` §5 #2), **Email** (change requires verifying the new address; the old address
    is notified), and Avatar/Signature/Bio. `AccountSettings.dc.html`'s Profile panel contains
    **only** custom profile fields (Pronouns / Location / Field of study / Homepage) — every
    core identity field is missing. Production ships them at `/settings/account`
    (+ `POST /settings/avatar`, `/settings/avatar/remove`).
15. **Composing preferences.** `USER.md` §4.5 (default format, attach-signature default, draft
    autosave behaviour, Enter-to-send vs newline). Production `/settings/composing`. The design
    nav has no Composing entry.
16. **Notification matrix + subscriptions.** `USER.md` §4.6: the per-event × per-channel matrix
    (in-app / email / digest), **quiet hours**, **per-thread mute**, a global **pause all email**
    switch, plus per-subscription frequency (Instant/Daily/Off, thread overrides board), the
    **digest preview**, the **test send**, and the **re-enable a suppressed address** action
    (`ADMIN.md` §7.6). The design's Notifications panel shows only digest cadence, timezone,
    digest hour and a flat subscription list with Unsubscribe.
17. **Privacy options are fixed.** `USER.md` §4.7 + `DECISIONS.md` §5 #6/#7/#8: Profile
    visibility **Public · Members-only**; Show online presence; Allow DMs from **Everyone ·
    Members · No one**; Discoverable by email; Block list. Production
    `templates/account/privacy.php:26-35`. The design invents **"Hidden — wardens only"** and
    **"Members I have replied to"** — neither exists (see §3).
18. **Reading preferences.** `USER.md` §4.2 also requires *Open threads at* (last-read / top),
    *Timezone & time format* (12h/24h; relative vs absolute), *Media* (autoplay off, lazy-load,
    inline vs click), *Links* (open external in new tab). The design's Reading panel shows only
    pagination, the retired sort, "What appears in a thread", and Composing.
19. **Board organization.** `USER.md` §4.3 requires favourite/mute **and reorder favourites** and
    per-user category collapse state; production also ships private board folders
    (`board_folders`), bookmark folders, and saved feeds. The design's Boards panel shows only
    favourite/mute.
20. **Data & Account.** `USER.md` §3.5: export → generated archive → download (the design's
    single "Download account export" button must not lose the request/generate step if
    production has one); deactivate/reactivate; delete → grace → **cancel** path
    (`POST /settings/account/delete/cancel`).
21. **Thread Intelligence member disclosure.** `USER.md` §4.9 — there is **no** member preference
    that sends private content to the provider and no per-member generation. Do not invent one on
    the account screen.
22. **Settings IA parity.** `USER.md` §3.1 names the sections: Account · Security · Connections ·
    Preferences · Notifications · Privacy · Data & Account. The design's 13-item nav is a
    superset/rename; the mapping must be explicit and every production route must remain reachable
    (`/settings`, `/settings/account`, `/settings/account/lifecycle`, `/settings/security`,
    `/settings/privacy`, `/settings/appearance`, `/settings/preferences`, `/settings/composing`,
    `/settings/notifications`, `/settings/connections`, `/settings/blocks`, `/settings/sessions`,
    `/settings/boards`).
23. **Drafts live at `/drafts`, not under `/settings`.** `src/Core/App.php:2037-2041`. The design
    puts a Drafts panel inside account settings; moving the route is a routing change that needs
    its own decision — the design's fiction route names are not authoritative (governing rule 4).

---

## 3. HEAD-ON CONFLICTS (decision wins — flag for the operator)

| # | Design does | Locked decision | Resolution |
|---|---|---|---|
| C1 | `AccountSettings` Reading panel: **"Default sort · Last post / Newest / Most replies"** | `2026-08-02-imladris-forum-surfaces-production.md` Task 1 (retire `thread_sort`, `PreferenceSchema::VERSION = 3`, remove the label) + `USER.md` §4.2 "Fixed: pinned first, then latest activity" + `PRODUCT_DESIGN.md` §5.2 | **Do not build.** The control stays deleted. Record as feature-removed. Operator note: the design predates/ignores the 2026-08-02 fixed-order decision. |
| C2 | `AdminContent` one-click **Delete category** / **Delete** board / **Archive** with no confirm, no impact stats, no thread-destination picker | `ADMIN.md` §4.4/§4.5, §9.4; `2026-07-02` plan Task 5 acceptance criteria; ADR 0021 (board delete-with-move, hard-DELETE-with-forced-move) | **Decision wins.** Keep the GET confirmation routes, typed confirmation, impact copy, and the forced-move destination picker. The design's row buttons become links to those confirmation pages. |
| C3 | Every admin screen's section nav is a dead `<span>` "Moderation · Content · People · Appearance · Notifications · Integrations · Settings" | `ADMIN.md` §9.2; ADR 0023 shipped item 6; `2026-07-18-dashboard` Task 1/Task 4 | **Decision wins.** Keep the real 8-group nav with feature-aware disabled spans. Shipping the `<span>` would be dead chrome (explicitly forbidden). |
| C4 | `AdminPeople` = Roles & simulator only; no user directory, no user record, no bulk moderation | `ADMIN.md` §5.1–§5.6; ADR 0021 (bulk moderation shipped end-to-end "replacing the dead phantom UI") | **Decision wins.** People must contain Users *and* Roles. Adopting the design's People screen as the whole section would delete shipped, audited functionality. |
| C5 | `AccountSettings` Privacy: **"Hidden — wardens only"** profile visibility and **"Members I have replied to"** DM scope | `USER.md` §4.7; `DECISIONS.md` §5 #6/#8; `templates/account/privacy.php:26-35` | **Do not build.** Two options that do not exist. Record as feature-removed; also lexicon fiction ("wardens"). |
| C6 | `AccountSettings` global dirty-buffer + sticky "You have unsaved changes · Discard · Save changes" across all panels | Anti-draft-loss 422 contract (B6); PE (B1) | **Decision wins on mechanics.** One server-owned form per section; the bar may only be a JS decoration. Design wins on presentation. |
| C7 | `AdminSettings` hard-codes the Thread Intelligence generation contract (`claude-sonnet-4-6` / `medium` / `ti.summary.v7`) | `DECISIONS.md` §2 replaceable seams; `ADMIN.md` §3.10 ("validated model/effort"); the selected live contract is `low` / `16000` | **Decision wins.** Render the configured/validated values from the provider seam. Never a literal model name. |
| C8 | `AdminAppearance` shows Custom CSS unconditionally | `custom_css` default OFF + safety-blocked (`PHASE_5_STATUS.md` 2026-07-13); `templates/admin/branding.php:77-92` | **Decision wins.** Keep the flag gate and the "not available on this install" explanation. |
| C9 | `AdminContent` intro: *"Archiving hides a board without losing a word of it."* | `ADMIN.md` §4.4 — archive makes a board **read-only**; content is preserved **and still searchable**. Production `/admin/boards/{id}/archive` unarchive copy explains posting is re-enabled | **Decision wins.** The design's copy is factually wrong about production behavior. Rewrite: "Archiving makes a board read-only — its topics stay readable and searchable." |
| C10 | `AccountSettings` Connections: *"Email and password always stay available."* | `USER.md` §2.4/§3.4 keep-at-least-one-method; OAuth-only accounts must *set* a password (`/settings/connections/set-password`) | **Decision wins.** Rewrite to state the real rule. |
| C11 | `AdminNotifications` opens at "Sending domain" with no transport/From status | ADR 0023 item 3 (F24) — three independent status lines | **Decision wins.** The `.email-status-facts` block stays above the domain card. |
| C12 | **Lexicon:** the design is written in Tolkien register throughout | Governing rule 3 (fiction never ships) **vs.** already-shipped, test-pinned fiction strings in production | **Escalate.** See §3.1 — this needs an operator ruling, not a unilateral fix. |

### 3.1 The lexicon conflict needs an owner decision

Governing rule 3 says no fiction string may ship. **But fiction has already shipped and is
pinned by green regression tests:**

| Production string | Where | Test pin |
|---|---|---|
| `Removed by a warden` | `templates/partials/post_deleted.php:13` | `tests/Integration/Core/AppDeletedPostStubTest.php:38,49,60,89`; `tests/browser/thread-content-presentation.spec.ts:76` — **shipped by ADR 0023 item 2** |
| `Commends` | `templates/partials/post.php:33` | `tests/Integration/Core/AppCouncilTopicFidelityTest.php:48,51`; `tests/Integration/Core/AppProfileFidelityTest.php:15` explicitly says the kit label "is not changed" |
| `Private counsel` | `templates/dm/show.php:23`, `templates/partials/dm_list.php:26,33`, `templates/dm/new.php:24,39` | `tests/Integration/Core/AppImladrisFidelityTest.php:293,302` |
| `sort=commends` query value | `/u/{username}` | `tests/Integration/Core/AppProfileActivityTest.php:188-194` |

Not test-pinned, and therefore free to de-fictionalize **inside this migration's scope**:

| Production string | File | Proposed |
|---|---|---|
| `Tune the public name, colour accents, assets, and preview before the council sees the updated hall.` | `templates/admin/branding.php:20` | "…preview the change before it goes live for everyone." |
| `You still earn regard; you just won't be ranked publicly.` | `templates/account/privacy.php:40` | "You still earn reputation; you just won't be ranked publicly." |
| `Warden's table` (eyebrow) | `templates/mod/{appeals,approvals,reports,user}.php` | "Moderation" |
| `Council record` / "The council record keeps the original action and your reason together." | `templates/appeals/index.php:5,28` | "Moderation record" / "The moderation record keeps…" |
| `Welcome back to the council` | `templates/auth/login.php:4` | "Welcome back" |
| `Your seat at the council is ready.` | `templates/auth/verify.php:7` | "Your account is ready." |

**Recommendation:** treat the de-fictionalization of the four test-pinned strings as a
**separate, owner-approved change** (it requires editing shipped assertions from ADR 0023 and
three fidelity suites). Within this migration, the binding rule is: **introduce no new fiction**.

### 3.2 Fiction strings in the seven design screens → proposed production strings

Every one of these is a CONSTRAINT deviation (governing rule 3). Design string → production string:

| Screen | Design string (verbatim) | Production string |
|---|---|---|
| all | `Imladris` (wordmark) | the operator's `$site_name` / brand mark |
| all | `Back to the council` | `Back to the forum` |
| Overview | `…then review what has changed across the council.` | `…then review what has changed across the community.` |
| Content | `Categories order the council's rooms; boards are the rooms themselves.` | `Categories group boards; boards are where topics live.` |
| Content | `A council needs at least one room. Add a category below, then put a board inside it.` | `A forum needs at least one board. Add a category below, then put a board inside it.` |
| Appearance | `The chrome the council wears.` | `The chrome your community wears.` |
| Appearance | `Safe mode drops the council back to the built-in chrome without uninstalling anything.` | `Safe mode returns the site to the built-in chrome without uninstalling anything.` |
| Appearance | `…so safe mode can always bring the council home.` | `…so safe mode can always restore the built-in chrome.` |
| Appearance | `No package theme is active. The council wears the built-in chrome.` | `No package theme is active. The site uses the built-in chrome.` |
| Settings | `The name the council goes by — in the topbar, in every email, and on the sign-in page.` | `The name your community goes by — in the topbar, in every email, and on the sign-in page.` |
| Settings | `The council needs a name.` | `Your community needs a name.` |
| Settings | `Automated context for long topics. The council approves; the model proposes.` | `Automated context for long topics. Moderators approve; the model proposes.` |
| Settings | `Model: claude-sonnet-4-6` / `Reasoning effort: medium` / `Prompt version: ti.summary.v7` | server-rendered from the validated provider configuration (see C7) |
| Account | `Your seat at the council` (eyebrow) | `Your account` |
| Account | `Everything the council knows about you, and everything it does on your behalf.` | `Everything your account stores, and everything the forum does on your behalf.` |
| Account | `Fields defined by the wardens` | `Fields defined by your admins` |
| Account | `The wardens choose which fields exist; you choose what goes in them.` | `Admins choose which fields exist; you choose what goes in them.` |
| Account | `Hidden — wardens only` (profile visibility option) | **remove** — not a production option (C5) |
| Account | `Regard` (nav item), `Commends`, `Regard is earned, never granted.` | `Reputation` — and the whole panel is a feature gap (below) |
| Account | `A second factor keeps your seat at the council secure even if your password is lost.` | `A second factor keeps your account secure even if your password is stolen.` |
| Account | `Scan the cipher with your authenticator` | `Scan the code with your authenticator` |
| Account | `Saved to your seat.` | `Saved.` |
| Account | `counsel and posting are blocked until you reactivate` | `posting is blocked until you reactivate` |
| Account | `Their public counsel stays readable — blocking is not moderation.` | `Their public posts stay readable — blocking is not moderation.` |
| Account | `Public counsel is preserved under a deleted-member identity` | `Public posts are preserved under a deleted-member identity` |
| Account | `Europe / Rivendell` (timezone option) | real IANA timezone list |
| Account/Notif. | `council.imladris.example`, `erestor@imladris.council`, `Erestor` | seeded/real data |

### 3.3 Design features with no production implementation (feature-removed — do NOT build)

- **`AccountSettings` → "Regard" ledger** (per-event reputation history with Milestone glyphs,
  filters, deltas, "Only you can see this ledger; others see the total"). There is **no**
  member-facing `reputation_events` surface: no controller or template references
  `reputation_events`/`ReputationEvent`, and there is no `ReputationRepository`. `reputation_ledger`
  is default-ON but only powers windowed ranks. Record as a gap; do not ship dead chrome.
- **Profile visibility "Hidden — wardens only"** and **DM scope "Members I have replied to"** (C5).
- **`AdminAppearance` → "Light theme logo / Dark theme logo" upload pair** — verify against
  `templates/admin/branding.php` before adopting; if the variants don't exist, do not add the
  uploaders.
- **`AdminSettings` → "Retry provider configuration"** must map to the real *clear-latch* action
  (`ADMIN.md` §3.10 "clear a repaired provider latch"), not a new endpoint.
- **`AdminNotifications` → suppression "Release"** — production's action is "Remove"
  (`templates/admin/email.php:188`); behavior wins, label may follow the design.

---

## 4. EVIDENCE POLICY (what "done" requires, and where artifacts are filed)

### 4.1 The rule

`PRODUCT_DESIGN.md` §13, line 873 — verbatim:

> **Completion-evidence rule:** anything marked `Live` must be accompanied by the tests, smoke
> checks, or Playwright/browser verification that prove the claim. **UI-visible work needs browser
> verification in addition to server-side tests.**

Reinforced by `CLAUDE.md`: *"Adding a column/table is not shipping a feature — behavior must be
enforced and tested… 'Inert schema is not evidence.'"* and by `PHASE_5_STATUS.md`
(*"'Inert schema is not evidence' (PRODUCT_DESIGN §13)"*). ADR 0021 deferral #3 restates it for this exact
surface: *"The UI ships only with the enforcement (inert settings are not evidence)."*

Every one of these screens is UI-visible ⇒ **PHPUnit alone is never sufficient.**

### 4.2 Required gates before any of these surfaces can be called done

Composed from `2026-07-02` Task 12, `2026-07-18-dashboard` Task 6, `2026-07-18-round2` Task 11,
and `2026-08-02` Task 5 Steps 7–8:

1. **Focused PHPUnit** per changed area, then the **full suite**, read to completion.
   `vendor/bin/phpunit` directly (`composer test` hits Composer's 300 s timeout). Use a private
   `DB_TEST_DATABASE` for parallel sessions; `RB_TEST_FRESH=1` after schema-affecting pulls.
2. **`composer verify:imladris`** — reconcile only reviewed runtime-baseline drift; refresh
   **only** `application_surface.sha256` in `config/imladris-runtime-baseline.json` from
   `php bin/build-imladris-assets.php --print-application-digest`.
3. **`npm run check:wysiwyg`** if composer assets are touched.
4. **Playwright evidence** — `cd tests/browser && npm run evidence` and **`npm run a11y`**
   (axe with serious/critical impact filtering), desktop **and** mobile projects
   (1280×800 / 1266×854 and 390×844).
5. **No-JS pass** — a dedicated `javaScriptEnabled: false` context proving the grouped nav
   reaches `/admin/settings`, that each destructive confirmation page works, and that each
   account-settings section saves.
6. **Console cleanliness** — capture `error`/`warning` console entries and assert the list is
   empty on every audited route.
7. **CSP scan** — `rg -n "<script|<style| on[a-z]+=" templates/admin templates/mod templates/layout.php -S`
   must come back clean.
8. **Mobile drawer / overflow contract** — 44 px control, drawer open, Tab containment,
   Escape/scrim/link close, focus restoration, resize cleanup, `data-overflow-cue` cue/fade
   end state.
9. **`git diff --check`** clean and `git status --short` containing only intended files.
10. **Visual QA** — inspect the desktop/mobile screenshots (and, where a prototype reference
    exists, a two-column "Approved prototype | Production" contact sheet) in the same QA pass;
    fix visible defects through a reviewed code/test commit **before** recording a visual pass.
11. **Honesty rule** (`2026-08-02` Task 5 Step 6): *"Every claim must quote an actual test result
    or inspected artifact; do not record an unrun check as passed."*

### 4.3 Where artifacts are filed

| Artifact | Location | Precedent |
|---|---|---|
| Screenshots | `docs/evidence/browser/desktop/*.png`, `docs/evidence/browser/mobile/*.png` | ADR 0021/0023 evidence; `docs/evidence/browser/README.md` |
| A slice-scoped evidence set | `docs/evidence/<slice-name>/{desktop,mobile,comparisons}/*.png` + `docs/evidence/<slice-name>.md` | `docs/evidence/imladris-forum-surfaces-production{,.md}` |
| Finding → fix → proof ledger | `docs/history/<slice>-<date>.md` | `docs/history/admin-ux-audit-round2-2026-07-18.md`, `admin-dashboard-ui-remediation-2026-07-18.md` |
| Decisions + owned deferrals | `docs/adr/00NN-*.md` (next free number after `0023`) | ADR 0021, ADR 0023 |
| Implementation plan | `docs/superpowers/plans/<date>-<slice>.md` | the four plans read here |
| Runtime baseline digest | `config/imladris-runtime-baseline.json` (`application_surface.sha256` only) | `2026-08-02` Task 5 Step 7 |
| Browser specs | `tests/browser/*.spec.ts`, wired into `npm run evidence` / `npm run a11y` | `admin-remediation.spec.ts`, `admin-dashboard.spec.ts`, `admin-features.spec.ts` |
| Status/carryover | `PHASE_5_STATUS.md` | current |

**Deferral discipline:** any spec promise this migration does not deliver must be recorded in a
new ADR (or attached to ADR 0021/0023) — never silently dropped. Status documents must cite the
ADR rather than claiming `ADMIN.md` §§3.4, 4.1–4.5, 5.5, 7.1–7.5, 9.3 complete (ADR 0021
Consequences).

**CI reality:** there is **no PHPUnit CI**; the only GitHub workflow
(`.github/workflows/browser-evidence.yml`) runs the Playwright capture. Local green is the only
green.

---

## 5. Quick reference — what this migration may NOT change

1. `thread_sort` stays retired; board order stays fixed.
2. Destructive admin actions stay behind typed confirmation + impact pages.
3. The 8-group admin nav stays, with the verbatim `Disabled until the feature flag is enabled` copy.
4. `/admin/users`, `/admin/users/{id}`, `/admin/users/bulk`, `/mod/u/{id}` stay.
5. `/admin/email`'s three-fact status block stays.
6. `custom_css` stays flag-gated.
7. Registration modes stay `open | invite | closed`.
8. `POST /admin/settings` stays removed; the dashboard stays form-free.
9. ↑/↓ reorder stays; no drag-and-drop.
10. Private staff notes stay admin-only; anonymous authors stay masked.
11. Every deferral in ADR 0021 (#1–#10) and ADR 0023 (#1–#4) stays deferred.
12. No new fiction strings.
