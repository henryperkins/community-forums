# Deploy-Dark Feature Inventory

**Date:** 2026-07-02 (graduation readiness ranking added; Phase 5 rows
reconciled with `PHASE_5_STATUS.md`)

This inventory lists feature flags that default to `false` in
`src/Core/FeatureFlags.php`, plus recently graduated flags retained here for
rollback traceability. A default-dark flag means the code and schema may be
present in a deploy, but the feature is unavailable until an operator explicitly
enables it through the `features` setting.

The Phase 3/4 dark flags are additionally ranked in
[Graduation Readiness Ranking](#graduation-readiness-ranking-phase-34-dark-flags)
by how little evidence each still needs before it can graduate.

Runtime source of truth: `src/Core/FeatureFlags.php`.

## Phase 3 / Phase 3 Carryover

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_drafts` | Authenticated cross-device draft sync, conflict handling, `/drafts` server list/discard | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppServerDraftsTest` (available-by-default + rollback), `AppAccountLifecycleTest` (export/purge), browser `28-server-draft-conflict`, `.composer-draft-sync` axe pass, runbook `docs/runbooks/server_drafts.md`, ADR 0010. Retained here for traceability. |
| `wysiwyg_composer` | Optional Milkdown WYSIWYG layer over the canonical Markdown textarea | Deploy-dark; narrow flag under the `rich_composer` kill switch. Acceptance evidence: ADR 0013, runbook `docs/runbooks/wysiwyg_composer.md`, `AppComposerTest`, `AppComposerSuggestTest`, `AppMentionLinkRenderTest`, `MarkdownRoundTripTest`, `npm run check:wysiwyg`, browser `wysiwyg-composer.spec.ts` (CSP, source mode, no-op edit, preview parity, chips, internal URL paste, mobile smoke, textarea fallback), and `a11y.spec.ts` WYSIWYG toolbar/picker/source scans. Rollback is `wysiwyg_composer=false`; emergency rollback is `rich_composer=false`. |
| `appeals` | Self-service moderation appeals and staff appeal queue | Deploy-dark; focused PHPUnit exists, awaiting browser/a11y/runbook evidence |
| `custom_css` | Guarded raw CSS editor for trusted operators | Deploy-dark; awaiting safe-mode/mobile/operator evidence |

## Phase 4 Gate A

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `topic_workflow` | Canonical status, history, snooze, assignment | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: browser `29-topic-workflow`, `.wf-actions`/`.wf-bar` axe pass, runbook `docs/runbooks/topic_workflow.md`. Retained here for traceability. |
| `group_dms` | Group conversation creation and invites | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `tags` | Curated tag catalogue and thread tagging | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppFeatureFlagTest`, `AppPhase4GateATest`, runbook `docs/runbooks/phase4-tags-feeds-reputation.md`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `expanded_feeds` | Board/tag follows, expanded Following and Latest feeds | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppFollowFeedTest`, runbook `docs/runbooks/phase4-tags-feeds-reputation.md`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `reputation_ledger` | Reputation-event ledger and windowed rankings | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppLeaderboardTest`, runbook `docs/runbooks/phase4-tags-feeds-reputation.md`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `badge_rules` | Custom badge rules, preview, backfill, revoke history | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppAdminBadgeRulesTest` (available-by-default + flag-rollback-with-history-intact), `AppFeatureFlagTest`, browser `32-badge-rules`/`33-badge-rule-preview`/`34-badge-rule-backfilled`, `/admin/badge-rules` axe pass, runbook `docs/runbooks/badge_rules.md`. Retained here for traceability. |
| `community_memory` | Summaries, related topics, wiki revisions | Accepted Gate A engineering baseline; default-dark until intentional enablement |
| `content_references` | Persisted board/thread/post/DM/summary references and read-gated cards | Deploy-dark carryover; awaiting browser/no-JS and inaccessible-target evidence |

## Phase 4 Carryover Completion

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `link_previews` | Allowlisted server-fetched URL metadata, admin purge/refresh | Deploy-dark; awaiting browser, crawler/noindex, load, and runbook evidence |
| `expanded_files` | PDF/text-family uploads, scanner/quarantine state, download-only serving | Deploy-dark; awaiting browser/no-JS, scanner-outage smoke, and operator runbook |
| `polls` | One-poll-per-thread no-JS create/vote/close/result flow | **Graduated 2026-06-30 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: browser `25-poll-voted`, `.poll-panel` axe pass, runbook `docs/runbooks/polls.md`. Retained here for traceability. |
| `custom_emoji` | Operator-managed shortcode assets and optional reactions | Deploy-dark; awaiting browser/a11y evidence and media moderation runbook |
| `slash_giphy` | Progressive slash insertion and client-side GIPHY picker config | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; **inert until `giphy_public_key` is set**; reversible via `features` override or by clearing the key). Acceptance evidence: `AppCustomEmojiGiphyTest` (incl. operator-rollback re-gate), `AppPhase4CarryoverFoundationTest`, browser `26-slash-menu`/`27-giphy-inserted`, `.composer-slash-menu` axe + keyboard pass, runbook `docs/runbooks/slash_giphy.md`. Retained here for traceability. |
| `split_merge` | Moderator split/merge routes, redirects, audit, touched-counter repair | Deploy-dark; awaiting browser/runbook evidence and larger repair rehearsal |
| `profile_media` | Avatar upload/removal, signature hardening, moderator signature removal | Deploy-dark; awaiting browser/a11y evidence and moderation runbook |
| `board_folders` | Private personal board folders | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `bookmark_folders` | Private folders for starred/bookmarked threads | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `saved_feeds` | Private saved feed filters and digest-composition groundwork | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `custom_profile_fields` | Bounded extra public profile fields | Deploy-dark; awaiting browser/a11y evidence and profile/privacy copy review |
| `account_lifecycle` | Export, deactivate/reactivate, 30-day deletion request/cancel, purge/anonymization | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppAccountLifecycleTest`, `AppFeatureFlagTest` (default-on + operator rollback), browser `35-account-lifecycle`/`36-account-deletion-scheduled` (no-JS member journey), `/settings/account/lifecycle` axe pass, runbook `docs/runbooks/account_lifecycle.md`. Retained here for traceability. |
| `automated_context` | Since-last-read context and suggested related topics | Deploy-dark; awaiting browser/no-JS evidence, replay/disable runbook, worker proof, and stale-link policy |

## Graduation Readiness Ranking (Phase 3/4 Dark Flags)

Ranked 2026-07-02 by **remaining evidence effort** (least first), after
cross-checking each flag's "awaiting" list against evidence already on disk:
captured browser PNGs under `docs/evidence/browser/`, Playwright specs, focused
PHPUnit suites, the worker smokes recorded in
`docs/evidence/phase2-4-completion.md`, ADRs, and `docs/runbooks/`. This is an
engineering-readiness order, **not** a product-priority order.

The graduation pattern is the one `polls` and `topic_workflow` used: capture the
missing evidence → flip the `FeatureFlags` default and update
`AppFeatureFlagTest` → land the runbook under `docs/runbooks/` → update this
inventory. The entries already marked graduated below have runbooks; entries
still listed as "Need: runbook" do not yet.

### Tier 1 — browser evidence already captured; docs and sign-off remain

1. **`server_drafts`** — ✓ **Graduated 2026-07-02 (default-ON).** Server-side
   copy of composer drafts so a member can start a post on one device and finish
   on another: conflict handling when the local and server copies diverge,
   `/drafts` list/discard, 90-day retention, 50-draft quota, and inclusion in
   account export/purge.
   - Evidence completed: `AppServerDraftsTest` (rewritten to available-by-default
     + operator rollback); `tests/browser/server-drafts.spec.ts` capture
     `28-server-draft-conflict.png` promoted into the standard `npm run evidence`
     run; the Gate A `16-drafts-view.png` journey now exercises the server-owned
     `/drafts` list (compose→sync→list→discard, plus clear-on-successful-submit)
     as the shipping default; `.composer-draft-sync` conflict-panel axe pass added
     to `tests/browser/a11y.spec.ts`; `worker:drafts` retention sweep; operator
     runbook `docs/runbooks/server_drafts.md`; scope record ADR 0010.
2. **`slash_giphy`** — ✓ **Graduated 2026-07-02 (default-ON, inert until
   `giphy_public_key` is set).** Progressive-enhancement slash menu in the
   composer for approved insert snippets plus direct GIPHY media insertion;
   strictly client-side picker (public key, rating cap, attribution, direct media
   URLs — no server proxy/cache); CSP is relaxed only when the flag and a key are
   configured, so the flip ships zero behaviour change until an operator opts in.
   - Evidence completed: `AppCustomEmojiGiphyTest` (config payload, CSP gating,
     plus `test_slash_giphy_is_default_on_and_operator_rollback_regates_route_and_csp`);
     `AppPhase4CarryoverFoundationTest` asserts the flag default-on; desktop+mobile
     captures `26-slash-menu.png` + `27-giphy-inserted.png` now driven by the
     Gate A slash journey asserting the ARIA combobox roles / arrow-key selection /
     Enter-insert / Escape-close; `.composer-slash-menu` axe + keyboard pass added
     to `tests/browser/a11y.spec.ts`; operator runbook `docs/runbooks/slash_giphy.md`
     (key custody, rating, attribution, client-direct data-flow disclosure, CSP
     relaxation, dual kill switch).

### Tier 2 — two missing items each; existing evidence patterns cover them

3. **`badge_rules`** — ✓ **Graduated 2026-07-02 (default-ON).** Operator-defined
   badge award rules over the constrained vocabulary
   `post_count`/`thread_count`/`reputation`/`solved_count`, with preview,
   enable/disable, backfill, and an append-only award/revoke ledger. Admin-only
   surface (awards happen only on an explicit Backfill — no cron).
   - Evidence completed: `AppAdminBadgeRulesTest` (rewritten to
     available-by-default + operator rollback, plus
     `test_badge_rules_flag_rollback_preserves_award_history` — disable/re-enable
     the flag with `badge_award_history` intact); `AppFeatureFlagTest` asserts the
     flag default-on; the `phase 4 badge rules` Playwright journey drives the
     create → preview → enable → backfill → disable → revoke flow, capturing
     `32-badge-rules`/`33-badge-rule-preview`/`34-badge-rule-backfilled` on desktop
     + mobile; `/admin/badge-rules` added to the admin dark-surface axe scan in
     `tests/browser/a11y.spec.ts`; operator runbook `docs/runbooks/badge_rules.md`.
4. **`account_lifecycle`** — ✓ **Graduated 2026-07-02 (default-ON).** Self-serve
   JSON export, reversible deactivate/reactivate, deletion request with 30-day
   grace and cancel, then purge/anonymization via `worker:purge-accounts` (refuses
   accounts no longer `pending_deletion`); mutations run in transactions
   (2026-06-30 hardening); policy ADR 0006.
   - Evidence completed: `AppAccountLifecycleTest` (now exercises the shipped
     default — export-without-secrets, deactivate/reactivate, grace cancel,
     final-admin guard, anonymizing purge, and the not-`pending_deletion` skip);
     `AppFeatureFlagTest` split into `test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible`
     (default-on plus operator rollback to 404) and `test_appeals_carryover_defaults_dark`
     (appeals stays independently dark); the `phase 4 account lifecycle` Playwright
     journey drives export→deactivate→reactivate→request→cancel through the no-JS
     forms (dedicated `dana` account), capturing `35-account-lifecycle` +
     `36-account-deletion-scheduled` on desktop + mobile; `/settings/account/lifecycle`
     added to the member axe scan in `tests/browser/a11y.spec.ts`; operator runbook
     `docs/runbooks/account_lifecycle.md`.
   - Fixed during graduation: `bin/console worker:purge-accounts` was constructing
     `AccountLifecycleService` with a bare `PasswordHasher` after the F7 `ReauthGate`
     refactor (a `TypeError` — the pre-refactor "smoke exit 0" claim had silently
     regressed). Now wires `new ReauthGate(new PasswordHasher())`; re-verified exit 0.
5. **`appeals`** — members appeal eligible deleted/moderated actions through
   no-JS forms on `/appeals`; the staff queue is board-scoped like the report
   queue (2026-06-30 hardening), with reverse restoration, notification, and
   audit; policy ADR 0007.
   - Have: `AppModerationAppealsTest`; ADR 0007.
   - Need: browser + a11y evidence + runbook (simple form flows — cheap to
     capture).

### Tier 3 — small surfaces, but evidence must be captured from scratch

6. **`content_references`** — board/thread/post/DM/summary references persisted
   at write time and rendered as reference cards only when the viewer can read
   the target; inaccessible targets are redacted.
   - Have: `AppContentReferenceTest`; DM and summary references share the same
     read-gated path.
   - Need: browser/no-JS capture + the inaccessible-target redaction proof (no
     runbook demanded). The e2e seed's private-board fixture
     (`14-private-board-member.png`) already supports the redaction scenario.
7. **`custom_emoji`** — operator-managed static PNG/WebP shortcode assets
   rendered through the server Markdown sanitizer, optionally usable as
   reactions.
   - Have: `AppCustomEmojiGiphyTest` (shared with `slash_giphy`).
   - Need: browser/a11y evidence + media-moderation runbook.
8. **`profile_media`** — local avatar upload/removal plus signature hardening
   (three-line height enforcement) and audited moderator signature removal.
   - Have: `AppProfileMediaTest`; the upload-capture Playwright pattern exists
     (`17-composer-upload.png`).
   - Need: browser/a11y evidence + moderation runbook.
9. **`custom_profile_fields`** — bounded extra public profile fields
   (migration `0062`).
   - Have: focused coverage in `AppBoardFoldersSavedFeedsTest` (the `0062`
     slice).
   - Need: browser/a11y evidence + the profile/privacy copy review — the first
     entry blocked on a product-owner step rather than engineering.

### Tier 4 — require operational rehearsals beyond Playwright

10. **`split_merge`** — moderator thread split/merge over the existing
    thread-operation/redirect schema, with audit and touched thread/board
    counters maintained inside the transaction.
    - Have: `AppThreadSplitMergeTest`; in-transaction counter maintenance landed
      in the 2026-06-30 slice.
    - Need: browser + runbook evidence + the larger seeded-scale repair
      rehearsal.
11. **`expanded_files`** — PDF/text-family uploads with content sniffing,
    scan-pending default, quarantine helpers, stale-scan cleanup, and
    download-only `nosniff` delivery.
    - Have: `AppExpandedFilesTest`; `worker:attachment-scans` smoke exit 0.
    - Need: browser/no-JS evidence + a deliberate scanner-outage smoke +
      operator runbook.
12. **`custom_css`** — guarded raw CSS editor for trusted operators (part of the
    advanced-theming slice); policy ADR 0009. Highest-trust surface in this
    list.
    - Have: `AppBrandingThemeTest`; ADR 0009.
    - Need: safe-mode recovery evidence (prove an operator can boot out of
      broken CSS), mobile evidence, and an operator runbook.
13. **`automated_context`** — deterministic since-last-read context built from
    local post/read-state data before the current view advances the read
    marker, plus suggested related topics refreshed by `worker:related-topics`
    (public threads only).
    - Have: `AppAutomatedContextTest` + `RelatedTopicRefreshWorkerTest`; worker
      smokes recorded (dark run `linked=0 skipped=1`; e2e exit 0).
    - Need: browser/no-JS evidence, replay/disable runbook, worker proof on
      live data, and a stale-link policy.

### Tier 5 — full graduation packages across large surfaces

14. **`link_previews`** — allowlisted server-side URL metadata fetch with egress
    validation, metadata sanitization, kill switch, and admin purge/refresh;
    private-board posts and DMs are never fetched. The only flag with a load
    requirement.
    - Have: `AppLinkPreviewTest`.
    - Need: browser, crawler/noindex, and load evidence, plus a runbook covering
      the egress/allowlist posture.
15. **`community_memory`** — manual summaries with source display and
    publish/retire/restore, curated related topics, and wiki edit history with
    revert under board `wiki_enabled` enforcement. Accepted Gate A engineering
    baseline.
    - Have: Gate A regression coverage in `AppPhase4GateATest`.
    - Need: browser/a11y across several journeys (summary lifecycle, wiki
      history/revert) + runbook + the intentional-enablement decision.
16. **`group_dms`** — bounded group-conversation creation, membership
    intervals, owner actions, unread/history boundaries, admin-actionable
    reports, inactive-account rejection, and DM-report rate limiting. Largest
    remaining member-facing surface; accepted Gate A engineering baseline.
    - Have: Gate A regression coverage (`AppPhase4GateATest`;
      `AppDirectMessageTest` covers the DM substrate).
    - Need: journey browser/a11y evidence, abuse/moderation runbook, and the
      intentional-enablement decision.

## Phase 5 Gate A

Phase 5 flags are gated on their own workstreams' Milestone-0 approvals and
acceptance evidence (`PHASE_5_PLAN.md` §2/§13), so they are not part of the
graduation readiness ranking above. Per-flag implementation states (R0–R5) are
machine-tracked in `docs/phase5/requirement-ledger.json`; the summary below is
as of 2026-07-02.

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `package_registry` | Signed registry, package catalogue/install/update foundation | Deploy-dark; Inc 2/3 implementation landed — signed trust chain, snapshots/advisories, staff catalogue, install/consent/enable/update/rollback/uninstall/export, health worker, browser/axe evidence, and runbook. Enabled packages are eligibility only until their runtimes are enabled. |
| `package_themes` | Declarative theme packages, preview, activation, safe mode, rollback | Deploy-dark; Inc 4 implementation landed behind `package_themes` — strict theme manifest block, token/asset/contrast gates, deterministic digest-addressed CSS builds, admin preview isolation, password-reauth activation/LKG rollback, flag-independent safe mode, fail-safe deactivation/repair, browser/axe evidence, and runbook. Rollback contract unchanged: set `features.package_themes=false` or enter safe mode; active/LKG rows are preserved. |
| `capabilities` | DB-backed roles/capability resolver and scoped grants | Deploy-dark; foundation + Increment 1 resolver shadow landed — catalogue/role-map seed (`0066`), protected-owner spine, `CapabilityResolver` + fail-open `ResolverShadow`, zero-mismatch parity corpus (`docs/evidence/phase5/resolver-parity.md`), no-JS role editor/simulator with browser/axe evidence. Live authorization is still legacy; enforcement cutover is Increment 6 after shadow soak |
| `passkeys` | WebAuthn registration, sign-in, step-up | Deploy-dark; schema landed inert (`0051`), TOTP/recovery fallback prerequisite (B1) resolved; implementation and real-browser/authenticator evidence pending |
| `provider_registry` | Generic OIDC and provider registry expansion | Deploy-dark; schema landed (`0052`, google/apple/github seeded as dark builtin rows); generic-OIDC implementation pending; A2 (first named provider) required before P5-12 acceptance |
| `invitations` | Invitation lifecycle and invite-based registration | Deploy-dark; schema landed inert (`0053`, hash-only tokens); lifecycle implementation pending |
| `service_secrets` | Encrypted service-secret vault for providers/webhooks | Deploy-dark; storage/rotate/revoke/prune seam implemented with focused evidence (R3) and consumed by webhook delivery; doubles as the write/rotate kill switch (reveal/revoke/prune stay available while dark); provider/remote-app consumers deferred |
| `api_tokens` | Admin/service Bearer tokens and read-only `/api/v1` | Deploy-dark; implemented with focused release evidence (R3) — PHPUnit across flag/schema/service/endpoints/admin plus browser `20`/`21` mint → show-once → revoke; broad enablement pending workstream acceptance |
| `webhooks` | Outbound webhook delivery engine and admin UI | Deploy-dark; implemented with focused release evidence (R3) — engine/worker/admin PHPUnit plus browser `22`/`23` register → delivery log; broad enablement pending workstream acceptance |
| `first_party_hooks` | Code-only first-party domain hooks and webhook producers | Deploy-dark; implemented (R3) — public-board domain producers with IDs/state-only payloads; private/hidden-board and DM events suppressed until endpoint-level data-class permissions exist |

## Phase 5 Gate B / Reserved

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_extensions` | Sandboxed isolated server-extension runtime | Deploy-dark; manifest validation, fail-closed Bubblewrap probe seam, async worker, and admin inspection landed (`0065`); no public/untrusted runtime until Phase 5 Gate B sandbox acceptance |
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
- `tags`, `expanded_feeds`, and `reputation_ledger` graduated out of deploy-dark
  on 2026-07-01: their `FeatureFlags` defaults are now `true`. They are retained
  here for traceability, remain reversible via the `features` override, and are
  mapped to the imported Imladris design system in
  `docs/design-system/imladris/ACTIVATED_FEATURES.md`.
- `board_folders`, `bookmark_folders`, and `saved_feeds` graduated out of
  deploy-dark on 2026-07-01: their `FeatureFlags` defaults are now `true`. They
  are retained here for traceability, remain reversible via the `features`
  override, and share the private personal-organization settings surface.
- `server_drafts` graduated out of deploy-dark on 2026-07-02 (the first Tier 1
  graduation from the readiness ranking): its `FeatureFlags` default is now
  `true`. Acceptance completed the ADR 0010 evidence list — the browser conflict
  capture moved into the standard `npm run evidence` run, a `.composer-draft-sync`
  conflict-panel axe pass was added, and the operator runbook landed at
  `docs/runbooks/server_drafts.md`. It is retained here for traceability and
  remains reversible via the `features` override; the browser-local `drafts`
  fallback is unaffected when it is disabled.
- The graduation readiness ranking (added 2026-07-02) orders the Phase 3/4 dark
  flags by remaining evidence effort only; enablement order stays a product
  decision. `group_dms` and `community_memory` additionally require an
  intentional-enablement decision on top of their evidence packages, and
  `custom_profile_fields` is blocked on a profile/privacy copy review rather
  than engineering work.
