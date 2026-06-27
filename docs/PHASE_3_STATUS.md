# Phase 3 — status, evidence index & carryover ledger

**Scope of this document:** what Phase 3 work is implemented on `main`, the test/evidence that backs each item, the carryover ledger from Phase 2, and the Gate B items that remain plan-only with their destination. This is an engineering status record, not a product-owner sign-off.

> A roadmap/status label is never proof of completion (PHASE_3_PLAN §10.2). Every "done" row below links to an automated test or a runnable command.

## 1. Summary

- **Gate A core polish slice: implemented on `main`** across preferences IA, the shared-composer server pipeline, image uploads, anti-abuse + holds, the 90-day IP-retention purge, branding, SEO, and the onboarding tour.
- **Automated suite:** `composer test` → **368 tests / 1248 assertions green** (PHP 8.5, MySQL). Baseline before Phase 3 was 275/987.
- **Adversarial review pass:** a 7-dimension multi-agent review (authz/leak, upload safety, XSS, anti-abuse, schema/tx, CSP, test gaps) raised 12 findings; 11 confirmed and **all fixed** (held-content leaks in search/feed/digest/thread-URL, an idempotency concurrency race, an attachment-retention data-loss window, and smaller hardening) — see §10.
- **Schema:** 6 additive migrations (`0042`–`0047`), all reconciled in `SCHEMA.md`; clean-install (`migrate:fresh`) applies all 47 migrations.
- **Feature flags:** every Phase 3 subsystem is independently disable-able (`FeatureFlags`), and the anti-abuse posture defaults to the safest mode (observe).

## 2. Entry gate (PHASE_3_PLAN §2)

Phase 2 is feature-complete on `main` (see `docs/PHASE_2_STATUS.md`); the only open Phase 2 item is the **product-owner Gate A/B sign-off** (a process gate, not a code gate). Phase 3 engineering proceeded against the satisfied code gate. The sign-off remains required before Phase 3 can formally close.

## 3. Workstream status & evidence

| ID | Workstream | Status | Evidence (tests / commands) |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Implemented | `FeatureFlags` (Phase 3 flags), `config/config.php` (uploads/antiabuse/rate_limits/retention), this document |
| P3-01 | Preferences & settings IA | Implemented | `tests/Unit/Preferences/PreferenceSchemaTest.php`, `tests/Integration/Core/AppUserPreferencesTest.php`; `/settings/appearance·preferences·composing` + reset |
| P3-02 | Composer engine & shared core | Implemented (server) | `tests/Unit/Composer/MarkdownRoundTripTest.php`, `tests/Integration/Core/AppComposerTest.php`; spoilers, `/media` images, server preview `/composer/preview`; `public/assets/composer.js` (progressive enhancement). **ADR:** `docs/adr/0001-composer-engine.md` |
| P3-03 | Drafts & submission resilience | Implemented (idempotency + local drafts) | `tests/Integration/Core/AppSubmitIdempotencyTest.php`; `submission_idempotency` table; localStorage drafts in `composer.js`. **Server draft sync = Gate B.** |
| P3-04 | Media & attachment safety (images) | Implemented | `tests/Integration/Core/AppImageUploadTest.php`, `AppPrivateMediaAccessTest.php`, `tests/Integration/Worker/OrphanAttachmentCleanupTest.php`, `tests/Unit/SanitizationTest.php` + `MarkdownRoundTripTest` (img XSS). `worker:attachments`. **Non-image files = Gate B.** |
| P3-05 | Rate limits & anti-abuse + IP purge | Implemented | `tests/Unit/RateLimitServiceTest.php`, `tests/Integration/Core/AppContentApprovalTest.php`, `AppAutomationAuditTest.php`, `tests/Integration/Worker/IpRetentionPurgeTest.php`; `/mod/approvals`; `worker:purge-ips` |
| P3-06 | Appeals & moderator scope | **Gate B — not started** | — (board-moderator assignment already shipped in Phase 2) |
| P3-07 | Branding & themes | Implemented (core) | `tests/Integration/Core/AppBrandingThemeTest.php`; `/admin/branding`, `/brand.css`, placeholder retirement. **Retro skin + guarded custom CSS = Gate B.** |
| P3-08 | Performance, queries, caching | Partial | Pending-content indexes (`idx_threads_pending`, `idx_posts_pending`); bounded sitemap; public-media cache headers. **Numeric budgets + load suite = open.** |
| P3-09 | Accessibility & interaction quality | Partial | Reduced-motion + focus-visible spoilers/tour, keyboard-operable toolbar, `aria-live` preview. **Full audit + manual AT matrix = open.** |
| P3-10 | SEO & public discovery | Implemented | `tests/Integration/Core/AppSeoVisibilityTest.php`; `/sitemap.xml`, `/robots.txt`, canonical/OG/noindex |
| P3-11 | Onboarding & learnability | Implemented | `tests/Integration/Core/AppProductTourTest.php`; `users.onboarded_at`, `/onboarding/complete·replay`, `public/assets/tour.js` |
| P3-12 | Account security & polish (TOTP, avatars…) | **Gate B — not started** | `users.avatar_path` column built (P3-04 pipeline ready); TOTP/recovery/deactivation/bookmark-folders/profile-fields remain |
| P3-13 | Internal extensions, webhooks, API | **Gate B — not started** | — (`plugins`/`webhooks`/`api_tokens` are create-in-Phase-3; not yet built) |
| P3-14 | Operations, release, closeout | Partial | This index, `worker:*` runbooks below; staged-rollout flags. **Full release/rollback rehearsal + sign-off = open.** |

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

## 10. Known gaps / not in this slice

- Numeric performance budgets + production-like load/soak suite (P3-08) — not captured.
- Full automated + manual accessibility audit with a defect log (P3-09) — shared-component fixes landed; the audit itself is open.
- Browser/Playwright evidence for the new surfaces (composer/upload/branding/tour) — server + unit coverage only in this slice.
- Milkdown/rich-WYSIWYG editor spike — deliberately deferred in favour of the server-rendered textarea + vanilla-JS enhancement (see ADR).
