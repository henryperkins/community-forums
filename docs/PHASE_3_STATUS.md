# Phase 3 status and closeout ledger

**Reconciled:** 2026-06-30

**Engineering state:** Gate A implementation and in-repo evidence are complete for
the current RetroBoards codebase. The final closeout harness now includes a
production-like Nginx/PHP-FPM/MariaDB/k6 profile under `tests/prodlike/`.

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
- Later release-train work has resolved most original Gate B deferrals without
  reopening Phase 3: restricted non-image attachments, TOTP/recovery, read-only
  API tokens, webhook delivery, first-party hook producers, avatar upload/removal,
  safe signature moderation, board folders, and saved feed filters now have
  deploy-dark implementation evidence in their destination slices. The 2026-06-30
  carryover slice also implements appeals, advanced theming, account lifecycle
  export/delete, bookmark folders, bounded custom profile fields, and server-side
  draft sync. Public/untrusted plugin runtime is not a Phase 3 deliverable; ADR
  0011 records it as a Phase 5 Gate B security boundary.

## Evidence Index

| Evidence | Result |
|---|---|
| PHPUnit full suite | Current closeout target: `composer test` (803 tests as of 2026-06-30) |
| Focused Phase 3 PHP suite | Preferences, composer, upload, tour, branded email, notification/digest workers |
| Browser evidence | `cd tests/browser && npm run evidence` -> 27 passed / 1 skipped; screenshots in `docs/evidence/browser/{desktop,mobile}` |
| Production-like browser evidence | `npm run evidence:prodlike`, `npm run evidence:dark:prodlike`, and `npm run a11y:prodlike` target `http://127.0.0.1:8021` when `tests/prodlike/compose.yml` is running |
| Load/soak evidence path | Pending final 20-VU / 15-minute run. `docs/evidence/phase3-load/phase3-load-summary.json` currently records an interrupted diagnostic run after the full gate was skipped at operator direction on 2026-06-30. |
| Production-like worker smoke | `docker compose -f tests/prodlike/compose.yml exec -T app php bin/console ...` for `worker:email 100`, `worker:digest`, `worker:drafts`, `worker:attachments`, `worker:attachment-scans 60`, `worker:webhooks 100`, and `worker:extensions 100` all exited 0 against `retroboards_prodlike` |
| Browser coverage map | [docs/evidence/browser/README.md](evidence/browser/README.md) |
| Closeout evidence note | [docs/evidence/phase3-closeout.md](evidence/phase3-closeout.md) |
| Final acceptance artifact | [docs/evidence/phase3-final-acceptance.md](evidence/phase3-final-acceptance.md) |
| Formal AT audit artifact | [docs/evidence/phase3-at-audit.md](evidence/phase3-at-audit.md) |
| Security/privacy review artifact | [docs/evidence/phase3-security-privacy-review.md](evidence/phase3-security-privacy-review.md) |
| Backup/restore rehearsal | [docs/evidence/backup-restore/README.md](evidence/backup-restore/README.md) and the latest saved closeout log `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log` |
| Composer ADR | [docs/adr/0001-composer-engine.md](adr/0001-composer-engine.md) |
| Gate B deferrals | [docs/adr/0002-phase-3-gate-b-deferrals.md](adr/0002-phase-3-gate-b-deferrals.md) |

## Workstream Status

| ID | Workstream | Status | Evidence / notes |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Gate A complete | `FeatureFlags`, `config/config.php`, this ledger |
| P3-01 | Preferences and settings IA | Gate A complete | `PreferenceSchemaTest`, `AppUserPreferencesTest`, browser screenshot `15-reading-preferences` |
| P3-02 | Composer engine and shared core | Gate A complete | `MarkdownRoundTripTest`, `AppComposerTest`, browser composer journey |
| P3-03 | Drafts and submission resilience | Gate A complete; server sync deploy-dark | Browser journey covers autosave, reload restore, Drafts view, discard, and success-clear. Server-side draft sync is implemented behind the dark `server_drafts` flag per ADR 0010, with focused PHP and dark browser evidence. |
| P3-04 | Media and attachment safety | Gate A complete for images; non-image carryover implemented later | `AppImageUploadTest`, private/DM media tests, disk-pressure guard, browser upload evidence. Restricted PDF/text-family uploads are now implemented behind the Phase 4 `expanded_files` flag and still need final browser/runbook evidence. |
| P3-05 | Rate limits, anti-abuse, IP purge | Gate A complete | Rate-limit, content-approval, audit, spam seam, and IP retention tests |
| P3-06 | Appeals and moderator-scope extensions | Resolved in carryover slice | Moderation appeals now have member submission, staff queue, reverse/uphold outcomes, restoration, notification, and audit coverage. Moderator split/merge is implemented behind `split_merge` in the Phase 4 carryover train. |
| P3-07 | Branding and themes | Gate A plus advanced local theming implemented | Branding settings/live preview/contrast/cache busting and branded emails are covered. Retro preset, light/dark logo variants, and guarded custom CSS behind the dark `custom_css` flag are implemented per ADR 0009. |
| P3-08 | Performance, queries, caching | Gate A engineering complete for current architecture | Indexes/bounded queries/cache headers landed. No fragment/render cache was introduced, so cache-isolation risk is limited to existing private-media and public asset rules. Production-like load/soak remains a release-environment evidence item. |
| P3-09 | Accessibility and interaction quality | Gate A engineering complete | Keyboard toolbar, `aria-pressed`, reduced motion, touch targets, tour focus trap/Escape/restore, mobile browser evidence. Formal AT audit remains a release sign-off artifact. |
| P3-10 | SEO and public discovery | Gate A complete | `AppSeoVisibilityTest`, sitemap/robots/canonical/noindex behavior |
| P3-11 | Onboarding and learnability | Gate A complete | `AppProductTourTest`, replay button, browser tour replay evidence |
| P3-12 | Account security and polish | Resolved in later slices, pending rollout evidence | TOTP/recovery is implemented as the Phase 5 Gate A identity fallback. Avatar upload/removal and signature hardening are behind `profile_media`. Account deactivation/reactivation/export/delete, bookmark folders, and bounded custom profile fields are implemented in the 2026-06-30 carryover slice. |
| P3-13 | Internal extensions, webhooks, API | Trusted prerequisites resolved; public runtime is Phase 5 Gate B | Read-only API tokens, webhook delivery, service secrets, first-party hook producers, and the server-extension inspection/worker seam are deploy-dark Phase 5 prerequisites. Public/untrusted plugin execution remains outside Phase 3 until the Gate B sandbox is accepted. |
| P3-14 | Operations, release, closeout | Engineering complete; signoffs pending | Evidence index, browser harness, prodlike profile, backup/restore rehearsal, feature flags, and boundary ADRs are present. Product-owner, formal AT, full load/soak, and security/privacy signoffs are recorded as external gates. |

## Gate B Deferrals

The following remains open after the 2026-06-30 carryover implementation pass and
must not be treated as accepted because adjacent scaffolding exists:

- public/untrusted plugin runtime and sandboxed server extensions.

Later destination slices have resolved the following original Phase 3 deferrals
without making them Phase 3 work again: restricted non-image attachments
(`expanded_files`), TOTP/recovery (`0054`), avatar upload/removal and signature
hardening (`profile_media`), board folders/saved feeds, read-only API tokens,
webhook delivery, service secrets, and first-party hook producers.
The 2026-06-30 carryover train additionally resolves appeals, advanced local
theming/custom CSS, account lifecycle/export/delete, bookmark folders, and
bounded custom profile fields, and server-side draft sync with focused PHPUnit
and dark browser evidence.

The owner, rationale, risk, and destination for each item are recorded in
[ADR 0002](adr/0002-phase-3-gate-b-deferrals.md). The current implementation
gates are [ADR 0006](adr/0006-account-lifecycle-export-delete-policy.md)
for account lifecycle/export/delete,
[ADR 0007](adr/0007-moderation-appeals-policy.md) for appeals,
[ADR 0009](adr/0009-advanced-theming-custom-css-policy.md) for advanced
theming/custom CSS, [ADR 0010](adr/0010-server-draft-sync-scope.md) for
server draft sync, and [ADR 0011](adr/0011-public-plugin-runtime-scope.md)
for the public plugin runtime boundary.

## Release Sign-Off Items

These are process or environment-specific gates that code changes cannot complete
inside the repository:

- product-owner Phase 3 acceptance (`docs/evidence/phase3-final-acceptance.md`);
- production-like load/soak run on the target deployment profile
  (`docs/evidence/phase3-load/`);
- formal accessibility/assistive-technology audit sign-off
  (`docs/evidence/phase3-at-audit.md`);
- formal security/privacy review sign-off for the target deployment
  (`docs/evidence/phase3-security-privacy-review.md`).

No critical/high engineering blocker is known in the current codebase after the
closeout fixes and test runs.
