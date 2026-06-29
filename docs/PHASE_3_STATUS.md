# Phase 3 status and closeout ledger

**Reconciled:** 2026-06-29

**Engineering state:** Gate A implementation and in-repo evidence are complete for the
current RetroBoards codebase.

**Formal state:** product-owner acceptance is still required. This document records
engineering status and evidence; it is not a product-owner sign-off.

## Summary

- Gate A core polish is implemented: preferences, shared Markdown composer, local
  drafts, image uploads, central anti-abuse controls, IP retention purge, branding,
  SEO, product tour, and feature-flag rollback paths.
- The final closeout pass fixed the remaining small Gate A code gaps: reading
  defaults now present as 20/20, emoji shortcodes render through the server
  Markdown pipeline, uploads fail safely under configured disk pressure, branded
  emails use operator `site_name`, the tour has a replay entry point and focus
  handling, and browser-local drafts now clear only after a confirmed navigation.
- Browser evidence now drives the Phase 3 JavaScript paths, not only page loads:
  preferences, toolbar/preview/emoji, draft reload/discard/success-clear, upload
  thumbnail/alt text, branding live preview, and tour replay.
- Gate B work that is not in this codebase is explicitly deferred in
  [ADR 0002](adr/0002-phase-3-gate-b-deferrals.md).
- Later release-train work has resolved several original Gate B deferrals without
  reopening Phase 3: restricted non-image attachments, TOTP/recovery, read-only
  API tokens, webhook delivery, first-party hook producers, avatar upload/removal,
  safe signature moderation, board folders, and saved feed filters now have
  deploy-dark implementation evidence in their destination slices. Open Phase 3
  carryovers remain appeals, server-side draft sync, advanced theming, account
  deactivation/reactivation/export/delete, bookmark folders, custom profile
  fields, and the public/plugin runtime.

## Evidence Index

| Evidence | Result |
|---|---|
| PHPUnit full suite | `composer test` passed: 430 tests, 1481 assertions |
| Focused Phase 3 PHP suite | Preferences, composer, upload, tour, branded email, notification/digest workers |
| Browser evidence | `cd tests/browser && npm run evidence` -> 10 passed; screenshots in `docs/evidence/browser/{desktop,mobile}` |
| Browser coverage map | [docs/evidence/browser/README.md](evidence/browser/README.md) |
| Closeout evidence note | [docs/evidence/phase3-closeout.md](evidence/phase3-closeout.md) |
| Backup/restore rehearsal | [docs/evidence/backup-restore/README.md](evidence/backup-restore/README.md) and `rehearsal.log` |
| Composer ADR | [docs/adr/0001-composer-engine.md](adr/0001-composer-engine.md) |
| Gate B deferrals | [docs/adr/0002-phase-3-gate-b-deferrals.md](adr/0002-phase-3-gate-b-deferrals.md) |

## Workstream Status

| ID | Workstream | Status | Evidence / notes |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Gate A complete | `FeatureFlags`, `config/config.php`, this ledger |
| P3-01 | Preferences and settings IA | Gate A complete | `PreferenceSchemaTest`, `AppUserPreferencesTest`, browser screenshot `15-reading-preferences` |
| P3-02 | Composer engine and shared core | Gate A complete | `MarkdownRoundTripTest`, `AppComposerTest`, browser composer journey |
| P3-03 | Drafts and submission resilience | Gate A complete for local drafts | Browser journey covers autosave, reload restore, Drafts view, discard, and success-clear. Server-side draft sync is Gate B deferred. |
| P3-04 | Media and attachment safety | Gate A complete for images; non-image carryover implemented later | `AppImageUploadTest`, private/DM media tests, disk-pressure guard, browser upload evidence. Restricted PDF/text-family uploads are now implemented behind the Phase 4 `expanded_files` flag and still need final browser/runbook evidence. |
| P3-05 | Rate limits, anti-abuse, IP purge | Gate A complete | Rate-limit, content-approval, audit, spam seam, and IP retention tests |
| P3-06 | Appeals and moderator-scope extensions | Deferred | See ADR 0002 |
| P3-07 | Branding and themes | Gate A complete for core branding | Branding settings/live preview/contrast/cache busting and branded emails are covered. Retro skin/custom CSS/logo variants are deferred. |
| P3-08 | Performance, queries, caching | Gate A engineering complete for current architecture | Indexes/bounded queries/cache headers landed. No fragment/render cache was introduced, so cache-isolation risk is limited to existing private-media and public asset rules. Production-like load/soak remains a release-environment evidence item. |
| P3-09 | Accessibility and interaction quality | Gate A engineering complete | Keyboard toolbar, `aria-pressed`, reduced motion, touch targets, tour focus trap/Escape/restore, mobile browser evidence. Formal AT audit remains a release sign-off artifact. |
| P3-10 | SEO and public discovery | Gate A complete | `AppSeoVisibilityTest`, sitemap/robots/canonical/noindex behavior |
| P3-11 | Onboarding and learnability | Gate A complete | `AppProductTourTest`, replay button, browser tour replay evidence |
| P3-12 | Account security and polish | Partially resolved in later slices | TOTP/recovery is implemented as the Phase 5 Gate A identity fallback. Avatar upload/removal and signature hardening are implemented behind `profile_media`. Deactivation/reactivation/export/delete, bookmark folders, and custom profile fields remain open. |
| P3-13 | Internal extensions, webhooks, API | Trusted prerequisites resolved; public runtime deferred | Read-only API tokens, webhook delivery, service secrets, and first-party hook producers are deploy-dark Phase 5 prerequisites. Public/untrusted plugin execution remains deferred until the Gate B sandbox. |
| P3-14 | Operations, release, closeout | Engineering complete | Evidence index, browser harness, backup/restore rehearsal, feature flags, and deferral ADR are present. Product-owner acceptance remains external. |

## Gate B Deferrals

The following remain open after the 2026-06-29 carryover reconciliation and must
not be treated as accepted because adjacent scaffolding exists:

- appeals workflow;
- server-side draft sync;
- retro skin, guarded custom CSS, and logo variants;
- account deactivation/reactivation, self-serve export/delete, bookmark folders,
  and custom profile fields;
- public/untrusted plugin runtime and sandboxed server extensions.

Later destination slices have resolved the following original Phase 3 deferrals
without making them Phase 3 work again: restricted non-image attachments
(`expanded_files`), TOTP/recovery (`0054`), avatar upload/removal and signature
hardening (`profile_media`), board folders/saved feeds, read-only API tokens,
webhook delivery, service secrets, and first-party hook producers.

The owner, rationale, risk, and destination for each item are recorded in
[ADR 0002](adr/0002-phase-3-gate-b-deferrals.md).

## Release Sign-Off Items

These are process or environment-specific gates that code changes cannot complete
inside the repository:

- product-owner Phase 3 acceptance;
- production-like load/soak run on the target deployment profile;
- formal accessibility/assistive-technology audit sign-off;
- formal security/privacy review sign-off for the target deployment.

No critical/high engineering blocker is known in the current codebase after the
closeout fixes and test runs.
