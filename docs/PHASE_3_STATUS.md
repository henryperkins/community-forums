# Phase 3 — status, evidence index & carryover ledger

**Scope of this document:** what Phase 3 work is implemented (and what is only partially built) on `main` verified against the acceptance bar, the test/evidence that backs each item, the carryover ledger from Phase 2, and the Gate B items that remain plan-only with their destination. This is an engineering status record, not a product-owner sign-off.

> A roadmap/status label is never proof of completion (PHASE_3_PLAN §10.2). Every "done" row below links to an automated test or a runnable command.

> **Reconciled 2026-06-28** after the Gate A closeout PRs landed on `main`: **PR #9** (`e2d54ac` — preferences finish, operator trust & safety, spam seam), **PR #10** (`a6b7ee7` — closed-registration on the OAuth path), and **PR #11** (`2113f34` — central-limiter wiring, Drafts view, composer + upload UX, branding live-preview/contrast/cache-bust, mail de-brand). These closed most of the *functional* UX gaps the 2026-06-27 audit recorded; the remaining Gate A surface is now dominated by **evidence packages** (browser, perf/load, accessibility, security/privacy), a few small code items, and the **two product-owner sign-offs**. Rows below reflect this reconciliation.

## 1. Summary

- **Gate A: the functional engineering is largely landed; the gate is gated on evidence + sign-off, not features.** The security-critical cores are real and tested (shared-composer server pipeline, image-safety + private/DM delivery, anti-abuse engine + holds, 90-day IP-retention purge, branding colors/theme, SEO, tour plumbing). PR #9/#10/#11 then closed the user-facing finish layer the 2026-06-27 audit had flagged open: composer toolbar parity, the Drafts view + discard + anon→user recovery, the upload UX (progress/thumbnail/alt-text/reorder/remove), the central rate-limiter wiring, and branding live-preview/contrast-validation/cache-busting. **What remains for Gate A is mostly *proving* the work** — browser/Playwright evidence, perf budgets + a load/soak suite, the formal accessibility audit package, and a security/privacy review artifact — plus a short list of small code items (tour-replay UI entry point; email *subject* de-branding; emoji rendering) and the unrecorded sign-offs. See §11 for the verified per-workstream detail.
- **Automated suite:** `composer test` → **419 tests / 1451 assertions green** (PHP 8.5, MySQL). Baseline before Phase 3 was 275/987; pre-PR-#11 was 405/1390. (Green ≠ at-bar: the suite covers what was built server-side; the new **client JS** layer — composer toolbar/shortcuts, draft autosave/restore/migrate, upload UX — has *no* automated coverage because there is no JS test runner in the repo. See §10.)
- **Adversarial review passes:** (a) the original 7-dimension review (authz/leak, upload safety, XSS, anti-abuse, schema/tx, CSP, test gaps) raised 12 findings; 11 confirmed and **all fixed** — see §9. (b) A second review accompanied **PR #11**: the suite arrived red on two of the PR's own new tests (fixed), and the review found and fixed **three newly-introduced regressions** — a draft duplicate-post hazard, a per-`(account, IP)` throttle weakening, and an unencoded operator `site_name` flowing into the mail `From` header (commit `45faafc`).
- **Acceptance-bar audits:** the 2026-06-27 17-workstream audit (verifying against PHASE_3_PLAN §4/§6/§14, not labels) produced §11; it was re-verified on **2026-06-28** against current `main` after PR #11. Several rows that were "Partial — blocked" are now functionally complete; the residual blockers are evidence/process, plus the items in §11.
- **Schema:** 6 additive migrations (`0042`–`0047`), all reconciled in `SCHEMA.md`; clean-install (`migrate:fresh`) applies all 47 migrations. No new schema in PR #9/#10/#11.
- **Feature flags:** every Phase 3 subsystem is independently disable-able (`FeatureFlags`), and the anti-abuse posture defaults to the safest mode (observe).

## 2. Entry gate (PHASE_3_PLAN §2)

Phase 2 is feature-complete on `main` (see `docs/PHASE_2_STATUS.md`); the only open Phase 2 item is the **product-owner Gate A/B sign-off** (a process gate, not a code gate). Phase 3 engineering proceeded against the satisfied code gate. The sign-off remains required before Phase 3 can formally close.

## 3. Workstream status & evidence

> **Reconciled 2026-06-28** against the Gate A acceptance bar (PHASE_3_PLAN §4/§6/§14) on current `main`. **"Partial"** now generally means the feature is built and works but a named *evidence* artifact (browser/perf/a11y) or a small code/test item is missing (detailed in §11) — it no longer implies the core UX is unbuilt.

| ID | Workstream | Status | Evidence (tests / commands) |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Implemented | `FeatureFlags` (Phase 3 flags), `config/config.php` (uploads/antiabuse/rate_limits/retention), this document |
| P3-01 | Preferences & settings IA | **Partial** — functionally complete | Done: versioned schema/defaults/reset/**export**/**upgrade path**, appearance no-JS theming, reading pagination + display toggles + thread sort server-enforced, **composing toggles applied end-to-end in `composer.js`**, malformed-JSON recovery. **Remaining (§11):** browser/Playwright evidence for the composer JS (cross-cutting, §10) + a cosmetic per-page-`<select>` default (shows 25/10 when unsaved; real default is 20). `PreferenceSchemaTest.php`, `AppUserPreferencesTest.php`, `AppReadingPreferencesTest.php` |
| P3-02 | Composer engine & shared core | **Partial** (feature-complete; evidence/coverage open) | Done: one canonical render/sanitize pipeline, verbatim storage, round-trip + XSS corpus, real kill switch, **full toolbar incl. emoji + fenced-code + active/aria-pressed state + Ctrl/Meta+B/I/K/E shortcuts** (PR #11). **Gaps (§11):** composer.js has **0 automated tests**; DM + edit canonical-render parity unasserted (only thread↔reply); no keyboard/mobile/no-JS browser evidence; **emoji button inserts literal `:smile:`** but no emoji extension in `Markdown.php` → renders as text. `MarkdownRoundTripTest.php`, `AppComposerTest.php`. **ADR:** `docs/adr/0001-composer-engine.md` |
| P3-03 | Drafts & submission resilience | **Partial** (built; JS untested) | Done: idempotency keys (in-txn claim + replay + race fix); **Drafts view** (`DraftController` + `templates/account/drafts.php`), **explicit discard**, **anon→user key migration**, autosave/restore — all in `composer.js` (PR #11). **Gaps (§11):** **0 tests** on the JS draft lifecycle (no JS runner); genuine **network-failure draft loss** — cleared on `submit` before the POST confirms (the deliberate trade-off of the duplicate-post fix; a true 5xx with no 422 re-render loses the body). `AppSubmitIdempotencyTest.php`, `AppComposerTest::test_drafts_route_renders_browser_local_shell` (server shell only). **Server draft sync = Gate B.** |
| P3-04 | Media & attachment safety (images) | **Partial** (server-safe + UX built; coverage/evidence open) | Done & tested: sniff/re-encode/dimension/bomb guard, **polyglot re-encode test**, private-board + DM authz delivery, orphan cleanup. Done (PR #11): composer media UX — **progress bar, thumbnail, alt-text editing, reorder, remove**, paste/drop, DM-purpose tagging. **Gaps (§11):** no disk-pressure threshold (config has size/dim caps but no min-free/storage-cap) + untested write-failure branch; the media UX has **0 automated coverage**; no mobile-upload/browser evidence. `AppImageUploadTest.php`, `AppPrivateMediaAccessTest.php`, `OrphanAttachmentCleanupTest.php`. `worker:attachments`. **Non-image files = Gate B.** |
| P3-05 | Rate limits & anti-abuse + IP purge | **Implemented** (engine + controls + spam seam + central limiter) | Done & tested: RateLimitService now used by **login/register/password-reset/DM** (PR #11; account-keyed for signed-in callers), AntiAbuseService (observe→flag→hold→block) + **pluggable spam-scoring seam**, approval holds, proxy-aware ClientIdentifier, audited system actions, IP-purge worker, admin UI for registration mode + anti-abuse mode/blocked-words + board `require_approval`. **Minor remaining (§11):** optional first-post hold; approval-mode registration (needs `users.status='pending'` migration + queue). `RateLimitServiceTest.php`, `AuthControllerTest.php`, `AppDirectMessageTest.php`, `AppPasswordResetTest.php`, `AppContentApprovalTest.php`, `AppAutomationAuditTest.php`, `IpRetentionPurgeTest.php`, `AppAdminModerationTest.php`, `AppSpamSeamTest.php` |
| P3-06 | Appeals & moderator scope | **Gate B — not started** | — (board-moderator assignment already shipped in Phase 2) |
| P3-07 | Branding & themes | **Partial** (core + preview/contrast/cache-bust done) | Done & tested: site name, primary/accent colors via `/brand.css`, light/dark/system default, reset, audit, admin-only + kill switch, asset-upload safety; **live preview**, **WCAG contrast validation** (rejects low-contrast primary/accent), **`/brand.css?v=` cache-busting**, **branded mail `From` name** (PR #11). **Gaps (§11):** single logo (bar wants light/dark variants); **email *subject*/body still hardcode "RetroBoards"** (`EmailVerificationService`/`NotificationEmailWorker` read `app.name`, not operator `site_name`). `AppBrandingThemeTest.php`; `/admin/branding`, `/brand.css`. **Retro skin + guarded custom CSS = Gate B.** |
| P3-08 | Performance, queries, caching | **Partial** (thin slice) | Done: pending-content indexes, bounded sitemap (LIMIT 5000), media + brand.css cache headers, one batched reaction query. **Gaps (§11):** no numeric budgets; no load/soak suite; **no cache classification/isolation/invalidation tests** (no fragment/render cache exists); no EXPLAIN pass; no asset compression/OPcache config. *(Untouched by PR #9/#10/#11.)* |
| P3-09 | Accessibility & interaction quality | **Partial** | Done: reduced-motion, focus-visible, skip-link, landmarks, `aria-live` preview/tour, keyboard toolbar, 44px touch targets, focusable spoilers; **composer active-state now exposed via `aria-pressed`** and **branding contrast now validated** (PR #11). **Gaps (§11):** entire formal package open (no axe/pa11y/Lighthouse, no keyboard/AT matrix, no defect log) + remaining code defects (tour dialog aria-modal/focus-trap/Escape, spoiler AT semantics). |
| P3-10 | SEO & public discovery | **Implemented (core)** — closest to bar | Done & tested: sitemap/robots, canonical+OG on boards/threads, noindex on private/auth surfaces, sitemap exclusion-leak tests (no data exposure). **Minor gaps (§11):** profile pages lack canonical/description/og:type; gated profile not noindex; no XML-validation/og:image. `AppSeoVisibilityTest.php`; `/sitemap.xml`, `/robots.txt` |
| P3-11 | Onboarding & learnability | **Partial** | Done & tested: first-sign-in trigger, server persistence, skip, graceful-failure, flag gating, open-redirect fix. **Gaps (§11):** **replay has no UI entry point** (dead `[data-tour-replay]` hook — endpoint works, nothing renders the affordance); no skip/mobile/focus/graceful-failure or browser tests; dialog lacks focus-trap/Escape. `AppProductTourTest.php`; `users.onboarded_at`, `/onboarding/complete·replay`, `public/assets/tour.js` |
| P3-12 | Account security & polish (TOTP, avatars…) | **Gate B — not started** | `users.avatar_path` column exists but is **inert** (never read/written); `/settings/security` is Phase-2 password-change only. TOTP/recovery/reauth/security-notifications/admin-reset/deactivation/avatar-upload-UI/Gravatar/bookmark-folders/profile-fields all remain |
| P3-13 | Internal extensions, webhooks, API | **Gate B — not started** | — (`plugins`/`webhooks`/`api_tokens` are create-in-Phase-3; not yet built) |
| P3-14 | Operations, release, closeout | **Partial** | Done: green suite, schema reconciliation, evidence index, release notes, 7 staged-rollout flags, backup/restore rehearsal, core runbooks. **Gaps (§11):** no load rehearsal; rollback rehearsal tests only 3/7 flags; no Phase-3 browser/smoke evidence; no formal security/privacy review; health = `/healthz` only; **product-owner sign-off unrecorded** (double-gated with Phase 2). |

## 4. Schema delta (reconciled in `SCHEMA.md`)

| Migration | Adds | Notes |
|---|---|---|
| `0042_users_phase3` | `users.avatar_path`, `users.onboarded_at` | Built-in-Phase-3 columns from the consolidated shape |
| `0043_attachments` | `attachments` (rich lifecycle) | Resolves the §8.2 #1 schema gap (temp/finalized/deleted, storage_key, sha256, visibility, timestamps) |
| `0044_submission_idempotency` | `submission_idempotency` | At-most-once submit (§8.5) |
| `0045_pending_columns` | `threads.is_pending`, `posts.is_pending` (+ indexes) | In SCHEMA.md but never migrated in P1–P2; created here |
| `0046_boards_require_approval` | `boards.require_approval` | Same — board approval hold |
| `0047_posts_deleted_at` | `posts.deleted_at` | Soft-delete timestamp → attachment-retention grace window (review fix) |

All migrations are additive and independently deployable with their feature flags off. PR #9/#10/#11 added no schema. (A `brand_version` value used for `/brand.css` cache-busting is stored in the key-value `settings` table, not a migration.)

## 5. Feature flags & kill switches

`features` setting (JSON) overrides per-flag defaults (all default **on** so a fresh install is fully functional and tests exercise every path):

`rich_composer`, `drafts`, `uploads`, `anti_abuse`, `branding`, `seo`, `product_tour` (Phase 3) + the Phase 2 flags.

Enforcement posture (separate from availability) lives in config:
- `antiabuse.mode` = `observe` (default) → `flag` → `hold` → `block`. A `settings.antiabuse_mode` value overrides config without a deploy.
- Disabling `uploads` turns off the upload endpoint and unbinds the upload finalize step; existing media still serves.
- `branding`/`brand.css` falls back to built-in chrome when colors are unset/invalid.
- Disabling `drafts` 404s `/drafts`, hides the topbar + settings-nav Drafts links, and stamps `data-drafts="0"` so `composer.js` skips the local-draft layer.

## 6. Operational runbooks (new in Phase 3)

| Need | Command / switch |
|---|---|
| Anonymise old IPs (90-day retention) | `php bin/console worker:purge-ips [days]` (idempotent, audited) |
| Reclaim orphaned upload storage | `php bin/console worker:attachments` |
| Force textarea-only composer | set `features.rich_composer = false` |
| Pause uploads (keep reads) | set `features.uploads = false` |
| Pause browser-local drafts | set `features.drafts = false` |
| Anti-spam to observe-only | set `settings.antiabuse_mode = observe` |
| Release/Reject held content | `/mod/approvals` (admins + scoped moderators) |
| Reset branding | `/admin/branding` → "Reset to defaults" (also bumps `brand_version`) |

## 7. Carryover ledger (Phase 2 → Phase 3)

| Item | Decision | Owner | Notes |
|---|---|---|---|
| Phase 2 product-owner Gate A/B sign-off | **Blocks Phase 3 close** | Product owner | Code gate satisfied; sign-off outstanding |
| Notification matrix / digests / presence / OAuth / sessions | Stay Phase 2 | — | Phase 3 may improve UX/perf only; acceptance not reset |
| Outbound webhooks / spam scoring | Net-new Phase 3 (P3-13) | — | Reconciled 2026-06-26; nothing to carry over |
| Data export/delete | Phase 2 accepted | — | Phase 3 may polish UX; policy not reopened |

## 8. Deferred to Gate B (with destination)

TOTP + recovery codes, moderation appeals + category-scoped resolution polish, retro skin + guarded custom CSS, server-side draft sync, restricted non-image attachments, internal hook/plugin system, outbound webhooks + delivery ledger, admin API tokens, account deactivation, avatar upload UI + Gravatar, bookmark folders, custom profile fields. Each is owned by its P3-06/07/12/13 workstream and must be accepted or re-scoped before Phase 3 closes (PHASE_3_PLAN §4 Gate B, §14). **No re-scope/deferral ADR exists for any Gate B item yet** (`docs/adr/` holds only `0001-composer-engine.md`), so none can be silently dropped at close.

## 9. Adversarial review — findings & resolutions

A multi-agent review of the original diff raised 12 findings; each was independently verified before being accepted. 11 were confirmed and fixed; 1 was a duplicate of an already-covered test-gap.

| # | Sev | Finding | Resolution |
|---|---|---|---|
| 1 | High | Held threads/posts surfaced in FULLTEXT search (incl. to guests) | `is_pending = 0` added to both search queries; regression test in `AppContentApprovalTest` |
| 2 | High | Held posts surfaced (with body) in the Following feed | `is_pending = 0` added to `FeedService` |
| 3 | High | Orphan sweep reclaimed media of just-deleted posts (no grace) → broke restore/appeal | `posts.deleted_at` (mig 0047) + `uploads.deleted_grace_days` (30); retain/reclaim test added |
| 4 | High | Idempotency race: concurrent double-submit could create a duplicate | Key claimed inside the txn before side effects; collision → rollback + replay (`DuplicateSubmissionException`) |
| 5 | High | DM-media authorization untested | `AppPrivateMediaAccessTest::test_dm_media_is_restricted_to_participants` |
| 6 | High | Private/public cache-control untested | Cache-header assertions in the media tests |
| 7 | High | Scoped-moderator approval-queue authz untested | `AppContentApprovalTest::test_scoped_moderator_cannot_release_other_boards_content` |
| 8 | Med | Held thread page loadable by direct URL | `ThreadController::loadReadableThread` 404s held threads except author/mod; test added |
| 9 | Low | Held posts counted in the daily digest | `is_pending = 0` added to `DailyDigestWorker` |
| 10 | Low | Re-encoded output size unbounded | Output re-checked against `uploads.max_bytes` |
| 11 | Low | Onboarding `next` open redirect | `next` constrained to same-origin paths |

**PR #11 closeout review (2026-06-28).** A second 6-dimension adversarial review + local run accompanied the Gate A closeout merge. The suite arrived **red** on two of the PR's own new tests (`between()`→`findOrCreateBetween()`; bare `/t/{id}` 301-redirects to canonical `/t/{id}-{slug}`) — both fixed. Three newly-introduced regressions were found and fixed in commit `45faafc`: (a) **draft duplicate-post hazard** — the submit handler re-saved instead of clearing the draft, repopulating the composer with just-posted text after the success redirect (reverted to clear-on-submit); (b) **throttle weakening** — `RateLimitService::key()` keyed signed-in callers by `(account, IP)`, letting IP rotation multiply DM/post/upload allowance (restored account-only); (c) **mail header** — operator `site_name` flowed raw into the `From` header (RFC-2047-encoded + CR/LF-stripped).

## 10. Cross-cutting gaps / not in this slice

These span multiple workstreams and are owned by none; per-workstream detail is in §11. **These are now the dominant Gate A gap.**

- **Browser/Playwright evidence for the new surfaces** (composer / upload / branding / tour / drafts / preferences) — server + unit coverage only; the Gate A Playwright spec (`tests/browser/gate-a.spec.ts`) still captures only ~14 Phase-2-style page screenshots and drives none of the new JS journeys.
- **No JS test runner exists** (no `package.json`/jest/vitest) → the entire client layer added in PR #11 — composer toolbar/shortcuts/active-state, draft autosave/restore/discard/migrate, upload progress/alt/reorder/remove — has **zero automated coverage**. This is the highest-leverage testing gap.
- **Numeric performance budgets + production-like load/soak suite** (P3-08) — not captured; makes the §14 perf checkboxes uncheckable and leaves P3-05's anti-abuse "load impact" unmeasured.
- **Full automated + manual accessibility audit** with a defect log (P3-09) — shared-component fixes landed (incl. composer `aria-pressed` and branding contrast validation in PR #11); the audit package itself (axe/pa11y, keyboard + AT matrices, a11y screenshots) is open.
- **Formal security/privacy review artifact** — only the inline §9 code-review ledger exists; no SECURITY/PRIVACY/threat-model doc covering IP-purge, export/delete, or DM-media.
- **Phase-3 rollback rehearsal** — kill switches are built and enforced in code, but flag-off tests cover only 3 of 7 Phase-3 flags; no documented staged-rollback rehearsal.
- Milkdown/rich-WYSIWYG editor spike — deliberately deferred in favour of the server-rendered textarea + vanilla-JS enhancement (see ADR).

## 11. Verified Gate A acceptance-bar audit (2026-06-28, reconciled)

Re-verified against the code/tests on current `main` after PR #11. **Result: Gate A is not yet releasable, but the residual gaps are now evidence/process plus a short code list — not unbuilt features.** P3-05 joined P3-10 as blocker-free on the functional axis; the cores are real and tested. The items below are the must-fix-for-Gate-A list per workstream.

| ID | Verified | Gate A remaining (must-fix) |
|---|---|---|
| P3-01 | Partial — functional work COMPLETE | Browser/Playwright evidence verifying the composer JS at runtime (cross-cutting, §10); cosmetic per-page-`<select>` default (shows 25/10 when unsaved; real default 20). |
| P3-02 | Partial — feature-complete | composer.js has **0 automated tests** (no JS runner); DM + edit canonical-render parity unasserted (only thread↔reply); no keyboard/mobile/no-JS browser evidence; **emoji button inserts literal `:smile:`** with no emoji extension in `Markdown.php` → renders as text (cheap: add a shortcode extension or change the inserted token). |
| P3-03 | Partial — built | **0 tests** on the JS draft lifecycle (autosave/restore/discard/`migrateAnonDrafts`/`renderDraftsPage`); **network-failure draft loss** — cleared on `submit` before the POST confirms (deliberate trade-off of the duplicate-post fix; resolving both cleanly needs a server success-signal). |
| P3-04 | Partial — server-safe + UX built | No disk-pressure threshold (size/dim caps only) + untested `writeFile` failure branch; the composer media UX (progress/thumbnail/alt/reorder/remove/paste/drop) has **0 automated coverage**; no mobile-upload/browser evidence. |
| P3-05 | **Functionally complete** | Central limiter now used by login/register/password-reset/DM (account-keyed). Minor/optional only: first-post hold; approval-mode registration (needs `users.status='pending'` migration + queue). |
| P3-07 | Partial — core + preview/contrast/cache-bust done | Light/dark **logo variants** (single logo only — template discloses the deferral); **email *subject*/body still say "RetroBoards"** (`EmailVerificationService.php:106`, `NotificationEmailWorker.php:119` read `app.name`, not operator `site_name`) — PR #11 branded only the `From` display name. |
| P3-08 | Partial | No numeric budgets; no load/soak suite; no cache classification/isolation/invalidation/bypass tests (no fragment/render cache exists at all); no EXPLAIN pass; no asset compression/OPcache config |
| P3-09 | Partial | No automated scans (axe/pa11y/Lighthouse); no manual keyboard matrix; no AT/screen-reader matrix; no defect log; no a11y screenshots; plus code defects (tour dialog aria-modal/focus-trap/Escape, weak spoiler AT semantics). *(composer active-state + branding contrast addressed in PR #11.)* |
| P3-10 | **Implemented (core)** | No blockers. Major/minor only: profile metadata (canonical/description/og:type), gated-profile noindex, sitemap XML-validation, og:image |
| P3-11 | Partial | **Replay has no UI entry point** (`[data-tour-replay]` hook is rendered by no template — the standout functional Gate A item); skip/mobile/focus/graceful-failure tests absent; dialog has no focus-trap/Escape/restore |
| P3-14 | Partial | No load rehearsal; rollback rehearsal covers only 3/7 Phase-3 flags; no Phase-3 browser/smoke evidence; no formal security/privacy review; health = `/healthz` only; **product-owner sign-off unrecorded** |

**Terminal blocker (process):** the Phase 3 Gate A product-owner sign-off is unrecorded **and** the Phase 2 Gate A/B sign-off remains outstanding and explicitly "blocks Phase 3 close" (§7). No engineering work substitutes for either.

**Gate B (P3-06 / 07B / 12 / 13 / 03B / 04B):** all six confirmed greenfield — only inert schema scaffolding exists (`users.avatar_path`, `attachments.kind='file'`), neither ever written; migrations stop at `0047`. The §14 checklist allows "built **or** formally re-scoped," but **no re-scope/deferral ADR exists for any** — so none can be silently dropped before Phase 3 closes.
