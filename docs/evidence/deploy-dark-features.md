# Deploy-Dark Feature Inventory

**Date:** 2026-07-01

This inventory lists feature flags that default to `false` in
`src/Core/FeatureFlags.php`. That default means the code and schema may be
present in a deploy, but the feature is unavailable until an operator explicitly
enables it through the `features` setting.

Runtime source of truth: `src/Core/FeatureFlags.php`.

## Phase 3 / Phase 3 Carryover

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_drafts` | Authenticated cross-device draft sync, conflict handling, `/drafts` server list/discard | Deploy-dark; ADR 0010 pull-forward, awaiting final release evidence/signoff |
| `appeals` | Self-service moderation appeals and staff appeal queue | Deploy-dark; focused PHPUnit exists, awaiting browser/a11y/runbook evidence |
| `custom_css` | Guarded raw CSS editor for trusted operators | Deploy-dark; awaiting safe-mode/mobile/operator evidence |

## Phase 4 Gate A

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `topic_workflow` | Canonical status, history, snooze, assignment | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: browser `29-topic-workflow`, `.wf-actions`/`.wf-bar` axe pass, runbook `docs/runbooks/topic_workflow.md`. Retained here for traceability. |
| `group_dms` | Group conversation creation and invites | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `tags` | Curated tag catalogue and thread tagging | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `expanded_feeds` | Board/tag follows, expanded Following and Latest feeds | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `reputation_ledger` | Reputation-event ledger and windowed rankings | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `badge_rules` | Custom badge rules, preview, backfill, revoke history | Deploy-dark carryover; awaiting operator browser evidence and rollback rehearsal |
| `community_memory` | Summaries, related topics, wiki revisions | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `content_references` | Persisted board/thread/post/DM/summary references and read-gated cards | Deploy-dark carryover; awaiting browser/no-JS and inaccessible-target evidence |

## Phase 4 Carryover Completion

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `link_previews` | Allowlisted server-fetched URL metadata, admin purge/refresh | Deploy-dark; awaiting browser, crawler/noindex, load, and runbook evidence |
| `expanded_files` | PDF/text-family uploads, scanner/quarantine state, download-only serving | Deploy-dark; awaiting browser/no-JS, scanner-outage smoke, and operator runbook |
| `polls` | One-poll-per-thread no-JS create/vote/close/result flow | **Graduated 2026-06-30 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: browser `25-poll-voted`, `.poll-panel` axe pass, runbook `docs/runbooks/polls.md`. Retained here for traceability. |
| `custom_emoji` | Operator-managed shortcode assets and optional reactions | Deploy-dark; awaiting browser/a11y evidence and media moderation runbook |
| `slash_giphy` | Progressive slash insertion and client-side GIPHY picker config | Deploy-dark; limited browser evidence exists, awaiting privacy/provider runbook and a11y signoff |
| `split_merge` | Moderator split/merge routes, redirects, audit, touched-counter repair | Deploy-dark; awaiting browser/runbook evidence and larger repair rehearsal |
| `profile_media` | Avatar upload/removal, signature hardening, moderator signature removal | Deploy-dark; awaiting browser/a11y evidence and moderation runbook |
| `board_folders` | Private personal board folders | Deploy-dark; awaiting browser/a11y evidence |
| `bookmark_folders` | Private folders for starred/bookmarked threads | Deploy-dark; awaiting browser/a11y evidence and profile/privacy copy review |
| `saved_feeds` | Private saved feed filters and digest-composition groundwork | Deploy-dark; awaiting browser/a11y evidence and digest-composition decision |
| `custom_profile_fields` | Bounded extra public profile fields | Deploy-dark; awaiting browser/a11y evidence and profile/privacy copy review |
| `account_lifecycle` | Export, deactivate/reactivate, 30-day deletion request/cancel, purge/anonymization | Deploy-dark; awaiting browser/no-JS evidence and scheduled purge runbook |
| `automated_context` | Since-last-read context and suggested related topics | Deploy-dark; awaiting browser/no-JS evidence, replay/disable runbook, worker proof, and stale-link policy |

## Phase 5 Gate A

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `package_registry` | Signed registry, package catalogue/install/update foundation | Deploy-dark; Phase 5 Gate A trust approvals and evidence pending |
| `package_themes` | Declarative theme packages, preview, safe mode | Deploy-dark; Phase 5 Gate A evidence pending |
| `capabilities` | DB-backed roles/capability resolver and scoped grants | Deploy-dark; Phase 5 Gate A evidence pending |
| `passkeys` | WebAuthn registration, sign-in, step-up | Deploy-dark; real-browser/authenticator evidence pending |
| `provider_registry` | Generic OIDC and provider registry expansion | Deploy-dark; Phase 5 Gate A evidence pending |
| `invitations` | Invitation lifecycle and invite-based registration | Deploy-dark; Phase 5 Gate A evidence pending |
| `service_secrets` | Encrypted service-secret vault for providers/webhooks | Deploy-dark; trusted-extension prerequisite, not public runtime |
| `api_tokens` | Admin/service Bearer tokens and read-only `/api/v1` | Deploy-dark; scoped release evidence pending |
| `webhooks` | Outbound webhook delivery engine and admin UI | Deploy-dark; scoped release evidence pending |
| `first_party_hooks` | Code-only first-party domain hooks and webhook producers | Deploy-dark; scoped release evidence pending |

## Phase 5 Gate B / Reserved

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_extensions` | Sandboxed isolated server-extension runtime | Deploy-dark; no public/untrusted runtime until Phase 5 Gate B sandbox acceptance |
| `governance` | Operator groups, approvals, access review | Deploy-dark; reserved for Phase 5 Gate B |
| `service_principals` | Remote-app service identities | Deploy-dark; reserved for Phase 5 Gate B |
| `verified_links` | Verified profile links and richer profile fields | Deploy-dark; reserved for Phase 5 Gate B |

## Notes

- A default-dark flag is not proof that the feature is accepted for broad
  rollout. Acceptance requires the evidence listed in the relevant phase ledger.
- Some dark behavior is controlled by settings rather than feature flags. For
  example, email domain send blocking is opt-in setting/config gated, while
  announcement banners default on through `announcements`.
- TOTP/recovery code support is not listed here because it does not have a
  global default-off feature flag in `FeatureFlags`; it is user/account opt-in.
- `polls` graduated out of deploy-dark on 2026-06-30: its `FeatureFlags` default
  is now `true` (acceptance evidence complete). It is retained for traceability
  and remains reversible via the `features` override.
- `topic_workflow` graduated out of deploy-dark on 2026-07-01: its `FeatureFlags`
  default is now `true` (acceptance evidence complete). Like `polls`, it no longer
  defaults to `false`; it is retained here for traceability and remains reversible
  via the `features` override. The `solved` status projection is gated on the
  flag: disabling `topic_workflow` freezes all workflow status writes (the ✓
  accepted-answer badge remains the community solved indicator); run
  `php bin/console repair` after re-enabling to reconcile the projection from
  accepted answers.
