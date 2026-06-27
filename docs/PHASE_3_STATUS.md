# Phase 3 — status, evidence index & carryover ledger

**Scope of this document:** what Phase 3 work is implemented (and what is only partially built) on `main` verified against the acceptance bar, the test/evidence that backs each item, the carryover ledger from Phase 2, and the Gate B items that remain plan-only with their destination. This is an engineering status record, not a product-owner sign-off.

> A roadmap/status label is never proof of completion (PHASE_3_PLAN §10.2). Every "done" row below links to an automated test or a runnable command.

## 1. Summary

- **Gate A: core engineering landed on `main`, but NOT yet at the acceptance bar.** The security-critical cores are real and tested (shared-composer server pipeline, image-safety + private/DM delivery, anti-abuse engine + holds, 90-day IP-retention purge, branding colors/theme, SEO, tour plumbing). The **user-facing finish layer and the required evidence packages are incomplete** — a 2026-06-27 acceptance-bar audit found ≥1 blocker in every Gate A workstream **except P3-10 (SEO)**. See §11 for the verified per-workstream gaps; the §3 statuses below are reconciled to that audit.
- **Automated suite:** `composer test` → **405 tests / 1390 assertions green** (PHP 8.5, MySQL). Baseline before Phase 3 was 275/987. (Green ≠ at-bar: the suite covers what was built, not the unbuilt Gate A sub-requirements catalogued in §11.)
- **Adversarial review pass:** a 7-dimension multi-agent review (authz/leak, upload safety, XSS, anti-abuse, schema/tx, CSP, test gaps) raised 12 findings; 11 confirmed and **all fixed** (held-content leaks in search/feed/digest/thread-URL, an idempotency concurrency race, an attachment-retention data-loss window, and smaller hardening) — see §9.
- **Acceptance-bar audit (2026-06-27):** a second 17-workstream multi-agent audit verified each workstream against PHASE_3_PLAN §4/§6/§14 (not the status labels). It corrected several rows that earlier revisions over-labeled "Implemented" — full results in §11.
- **Schema:** 6 additive migrations (`0042`–`0047`), all reconciled in `SCHEMA.md`; clean-install (`migrate:fresh`) applies all 47 migrations.
- **Feature flags:** every Phase 3 subsystem is independently disable-able (`FeatureFlags`), and the anti-abuse posture defaults to the safest mode (observe).

## 2. Entry gate (PHASE_3_PLAN §2)

Phase 2 is feature-complete on `main` (see `docs/PHASE_2_STATUS.md`); the only open Phase 2 item is the **product-owner Gate A/B sign-off** (a process gate, not a code gate). Phase 3 engineering proceeded against the satisfied code gate. The sign-off remains required before Phase 3 can formally close.

## 3. Workstream status & evidence

> **Reconciled 2026-06-27** against the Gate A acceptance bar (PHASE_3_PLAN §4/§6/§14). Statuses reflect *verified* state, not aspirational labels: **"Partial"** means the core works but named Gate A sub-requirements or evidence are missing (detailed in §11). Earlier revisions over-labeled P3-01/02/03/04/05/07/11 as "Implemented."

| ID | Workstream | Status | Evidence (tests / commands) |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Implemented | `FeatureFlags` (Phase 3 flags), `config/config.php` (uploads/antiabuse/rate_limits/retention), this document |
| P3-01 | Preferences & settings IA | **Partial** — functionally complete | Done: versioned schema/defaults/reset/**export**/**upgrade path**, appearance enforcement, reading pagination, reading display toggles + thread sort server-enforced, **composing toggles (enter-to-send/preview/smart-lists) applied in `composer.js`**, malformed-JSON recovery tested. **Only remaining (§11):** browser/Playwright evidence for the composer JS behaviour (cross-cutting, §10) + a cosmetic per-page-select default. `PreferenceSchemaTest.php`, `AppUserPreferencesTest.php`, `AppReadingPreferencesTest.php` |
| P3-02 | Composer engine & shared core | **Partial** (server core solid) | Done: one canonical render/sanitize pipeline, verbatim storage, round-trip + XSS corpus, real kill switch, 9-button toolbar + counter + server preview. **Gaps (§11):** no emoji / active-toolbar-state / keyboard-shortcuts / fenced-code button; DM+edit parity untested; no keyboard/mobile/no-JS evidence. `MarkdownRoundTripTest.php`, `AppComposerTest.php`. **ADR:** `docs/adr/0001-composer-engine.md` |
| P3-03 | Drafts & submission resilience | **Partial** (idempotency done) | Done & tested: idempotency keys (in-txn claim + replay + race fix). Done, untested: per-context localStorage autosave. **Gaps (§11):** no Drafts view, no explicit discard, signed-out→signed-in recovery broken, draft cleared on `submit` before POST confirms; local-draft layer has **0 tests**. `AppSubmitIdempotencyTest.php`; `submission_idempotency` table. **Server draft sync = Gate B.** |
| P3-04 | Media & attachment safety (images) | **Partial** (server-safe; client UX open) | Done & well-tested: sniff/re-encode/dimension/bomb guard, private-board + DM authz delivery, orphan cleanup. **Gaps (§11):** composer media UX unbuilt (no progress bar, thumbnail, alt-text editing, reorder/remove); no polyglot or disk-pressure test; no mobile-upload/browser evidence. `AppImageUploadTest.php`, `AppPrivateMediaAccessTest.php`, `OrphanAttachmentCleanupTest.php`, `SanitizationTest.php`. `worker:attachments`. **Non-image files = Gate B.** |
| P3-05 | Rate limits & anti-abuse + IP purge | **Partial** (engine + controls + spam seam done) | Done & tested: RateLimitService, AntiAbuseService (observe→flag→hold→block) + **pluggable spam-scoring seam**, approval holds, proxy-aware ClientIdentifier, audited system actions, IP-purge worker, admin UI for registration mode + anti-abuse mode/blocked-words + board `require_approval`. **Gaps (§11):** central limiter **bypassed** by login/DM/password-reset (raw REMOTE_ADDR → collapses behind a proxy); first-post hold missing. `RateLimitServiceTest.php`, `AppContentApprovalTest.php`, `AppAutomationAuditTest.php`, `IpRetentionPurgeTest.php`, `AppAdminModerationTest.php`, `AppSpamSeamTest.php`; `/admin` settings; `/mod/approvals`; `worker:purge-ips` |
| P3-06 | Appeals & moderator scope | **Gate B — not started** | — (board-moderator assignment already shipped in Phase 2) |
| P3-07 | Branding & themes | **Partial** (core) | Done & tested: site name, primary/accent colors via `/brand.css`, light/dark/system default, reset, audit, admin-only + kill switch, asset-upload safety. **Gaps (§11):** no live preview; no WCAG contrast validation (`--accent-contrast` static, never recomputed); no `/brand.css` cache-busting; single logo (bar wants light/dark); email metadata not retired. `AppBrandingThemeTest.php`; `/admin/branding`, `/brand.css`. **Retro skin + guarded custom CSS = Gate B.** |
| P3-08 | Performance, queries, caching | **Partial** (thin slice) | Done: pending-content indexes, bounded sitemap (LIMIT 5000), media + brand.css cache headers, one batched reaction query. **Gaps (§11):** no numeric budgets; no load/soak suite; **no cache classification/isolation/invalidation tests** (no fragment/render cache exists); no EXPLAIN pass; no asset compression/OPcache config. |
| P3-09 | Accessibility & interaction quality | **Partial** | Done: reduced-motion, focus-visible, skip-link, landmarks, `aria-live` preview/tour, keyboard toolbar, 44px touch targets, focusable spoilers. **Gaps (§11):** entire formal package open (no axe/pa11y, no keyboard/AT matrix, no defect log) + real defects (composer active-state, branding contrast, tour dialog aria-modal/focus-trap/Escape, spoiler AT semantics). |
| P3-10 | SEO & public discovery | **Implemented (core)** — closest to bar | Done & tested: sitemap/robots, canonical+OG on boards/threads, noindex on private/auth surfaces, sitemap exclusion-leak tests (no data exposure). **Minor gaps (§11):** profile pages lack canonical/description/og:type; gated profile not noindex; no XML-validation/og:image. `AppSeoVisibilityTest.php`; `/sitemap.xml`, `/robots.txt` |
| P3-11 | Onboarding & learnability | **Partial** | Done & tested: first-sign-in trigger, server persistence, skip, graceful-failure, flag gating, open-redirect fix. **Gaps (§11):** **replay has no UI entry point** (dead `[data-tour-replay]` hook); no skip/mobile/focus/graceful-failure or browser tests; dialog lacks focus-trap/Escape. `AppProductTourTest.php`; `users.onboarded_at`, `/onboarding/complete·replay`, `public/assets/tour.js` |
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

All migrations are additive and independently deployable with their feature flags off.

## 5. Feature flags & kill switches

`features` setting (JSON) overrides per-flag defaults (all default **on** so a fresh install is fully functional and tests exercise every path):

`rich_composer`, `drafts`, `uploads`, `anti_abuse`, `branding`, `seo`, `product_tour` (Phase 3) + the Phase 2 flags.

Enforcement posture (separate from availability) lives in config:
- `antiabuse.mode` = `observe` (default) → `flag` → `hold` → `block`. A `settings.antiabuse_mode` value overrides config without a deploy.
- Disabling `uploads` turns off the upload endpoint and unbinds the upload finalize step; existing media still serves.
- `branding`/`brand.css` falls back to built-in chrome when colors are unset/invalid.

## 6. Operational runbooks (new in Phase 3)

| Need | Command / switch |
|---|---|
| Anonymise old IPs (90-day retention) | `php bin/console worker:purge-ips [days]` (idempotent, audited) |
| Reclaim orphaned upload storage | `php bin/console worker:attachments` |
| Force textarea-only composer | set `features.rich_composer = false` |
| Pause uploads (keep reads) | set `features.uploads = false` |
| Anti-spam to observe-only | set `settings.antiabuse_mode = observe` |
| Release/Reject held content | `/mod/approvals` (admins + scoped moderators) |
| Reset branding | `/admin/branding` → "Reset to defaults" |

## 7. Carryover ledger (Phase 2 → Phase 3)

| Item | Decision | Owner | Notes |
|---|---|---|---|
| Phase 2 product-owner Gate A/B sign-off | **Blocks Phase 3 close** | Product owner | Code gate satisfied; sign-off outstanding |
| Notification matrix / digests / presence / OAuth / sessions | Stay Phase 2 | — | Phase 3 may improve UX/perf only; acceptance not reset |
| Outbound webhooks / spam scoring | Net-new Phase 3 (P3-13) | — | Reconciled 2026-06-26; nothing to carry over |
| Data export/delete | Phase 2 accepted | — | Phase 3 may polish UX; policy not reopened |

## 8. Deferred to Gate B (with destination)

TOTP + recovery codes, moderation appeals + category-scoped resolution polish, retro skin + guarded custom CSS, server-side draft sync, restricted non-image attachments, internal hook/plugin system, outbound webhooks + delivery ledger, admin API tokens, account deactivation, avatar upload UI + Gravatar, bookmark folders, custom profile fields. Each is owned by its P3-06/07/12/13 workstream and must be accepted or re-scoped before Phase 3 closes (PHASE_3_PLAN §4 Gate B, §14).

## 9. Adversarial review — findings & resolutions

A multi-agent review of the diff raised 12 findings; each was independently verified before being accepted. 11 were confirmed and fixed; 1 was a duplicate of an already-covered test-gap.

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

## 10. Cross-cutting gaps / not in this slice

These span multiple workstreams and are owned by none; per-workstream detail is in §11.

- **Numeric performance budgets + production-like load/soak suite** (P3-08) — not captured; makes the §14 perf checkboxes uncheckable and leaves P3-05's anti-abuse "load impact" unmeasured.
- **Full automated + manual accessibility audit** with a defect log (P3-09) — shared-component fixes landed; the audit package itself (axe/pa11y, keyboard + AT matrices, a11y screenshots) is open.
- **Browser/Playwright evidence for the new surfaces** (composer / upload / branding / tour / approvals / preferences) — server + unit coverage only; the existing suite captures only the 14 Phase-2 pages.
- **Formal security/privacy review artifact** — only the inline §9 code-review ledger exists; no SECURITY/PRIVACY/threat-model doc covering IP-purge, export/delete, or DM-media.
- **WCAG contrast validation for operator branding** — straddles P3-07/P3-09, owned by neither; arbitrary primary/accent hex ships with format-only validation.
- **Phase-3 rollback rehearsal** — kill switches are built and enforced in code, but flag-off tests cover only 3 of 7 Phase-3 flags; no documented staged-rollback rehearsal.
- Milkdown/rich-WYSIWYG editor spike — deliberately deferred in favour of the server-rendered textarea + vanilla-JS enhancement (see ADR).

## 11. Verified Gate A acceptance-bar audit (2026-06-27)

A 17-workstream multi-agent audit re-verified each Gate A claim against the code/tests and the PHASE_3_PLAN §4/§6/§14 bar (not the labels above). **Result: Gate A is not releasable** — only P3-10 carries no blocker. The cores are real and tested; the named UX sub-requirements and the evidence packages are not. The blockers below are the must-fix items per Gate A workstream.

| ID | Verified | Gate A blockers (must-fix) |
|---|---|---|
| P3-01 | Partial — functional work COMPLETE | All functional gaps **CLOSED 2026-06-27**: reading toggles + thread sort server-enforced (`AppReadingPreferencesTest`); preference **export** (`AppUserPreferencesTest`); **schema upgrade path** (`PreferenceSchema::upgrade` — version-stepped + legacy-value normalization, wired into `resolve()`) + **malformed-JSON recovery** (`PreferenceSchemaTest`, `AppUserPreferencesTest`); **composing toggles** (`enter_to_send`/`show_preview`/`smart_lists` — stamped on `<body>`, applied in `composer.js`; server exposure tested in `AppUserPreferencesTest`). **Remaining:** browser/Playwright evidence verifying the composer JS at runtime (cross-cutting, §10) + a cosmetic per-page-select default value. |
| P3-02 | Partial | Toolbar missing **emoji**, **active/aria-pressed state**, **keyboard shortcuts**, fenced-code-block button; DM + edit surface-parity untested; no keyboard/mobile/no-JS evidence or kill-switch smoke |
| P3-03 | Partial | No **Drafts view**; no **explicit discard**; **signed-out→signed-in recovery broken** (no anon→user key migration); draft cleared on `submit` event before the POST confirms (lost on failure); **0 tests** on the local-draft layer |
| P3-04 | Partial | Composer media UX unbuilt: no **progress bar / thumbnail / alt-text editing / reorder / remove**; no polyglot or disk-pressure test; no mobile-upload/browser evidence |
| P3-05 | Partial | **CLOSED 2026-06-27:** admin UI for **registration mode** (open/closed, enforced in `AuthController`), **anti-abuse mode + blocked-words**, board **`require_approval`** toggle (`AppAdminModerationTest`); **spam-scoring provider seam** — `App\Service\Spam\SpamScorer` contract + `NullSpamScorer` default, consulted by `AntiAbuseService` (score→severity, mode-clamped, audited, fail-safe, capped at hold), swappable via the container (`AppSpamSeamTest`). **Remaining:** central limiter **bypassed** by login/DM/password-reset (raw REMOTE_ADDR → collapses behind a proxy); optional **first-post hold**; approval-mode registration (needs `users.status='pending'` migration + queue) |
| P3-07 | Partial | **No live preview**; **no WCAG contrast validation** (`--accent-contrast` is a static token, never recomputed for a custom accent → unreadable buttons); **no `/brand.css` cache-busting** (stale ≤5 min); single logo (bar wants light/dark); email metadata still "RetroBoards" |
| P3-08 | Partial | No numeric budgets; no load/soak suite; no cache classification/isolation/invalidation/bypass tests (no fragment/render cache exists at all); no EXPLAIN pass; no asset compression/OPcache config |
| P3-09 | Partial | No automated scans (axe/pa11y/Lighthouse); no manual keyboard matrix; no AT/screen-reader matrix; no defect log; no a11y screenshots; plus code defects (composer active-state, branding contrast, tour dialog aria-modal/focus-trap/Escape, weak spoiler AT semantics) |
| P3-10 | **Implemented (core)** | No blockers. Major/minor only: profile metadata (canonical/description/og:type), gated-profile noindex, sitemap XML-validation, og:image |
| P3-11 | Partial | **Replay has no UI entry point** (`[data-tour-replay]` hook is rendered by no template); skip/mobile/focus/graceful-failure tests absent; dialog has no focus-trap/Escape/restore |
| P3-14 | Partial | No load rehearsal; rollback rehearsal covers only 3/7 Phase-3 flags; no Phase-3 browser/smoke evidence; no formal security/privacy review; health = `/healthz` only; **product-owner sign-off unrecorded** |

**Terminal blocker (process):** the Phase 3 Gate A product-owner sign-off is unrecorded **and** the Phase 2 Gate A/B sign-off remains outstanding and explicitly "blocks Phase 3 close" (§7). No engineering work substitutes for either.

**Gate B (P3-06 / 07B / 12 / 13 / 03B / 04B):** all six confirmed greenfield — only inert schema scaffolding exists (`users.avatar_path`, `attachments.kind='file'`), neither ever written. The §14 checklist allows "built **or** formally re-scoped," but **no re-scope/deferral ADR exists for any** — so none can be silently dropped before Phase 3 closes.
