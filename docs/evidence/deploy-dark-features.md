# Deploy-Dark Feature Inventory

**Date:** 2026-07-09 (`FeatureFlags::DEFAULTS` source audit; 2026-07-03
profile-media graduation reconciled; Phase 5 passkeys row reconciled with
`PHASE_5_STATUS.md`; custom-emoji graduation reconciled; **2026-07-04
`split_merge` + `custom_profile_fields` graduations reconciled** — both flipped
default-ON 2026-07-03 and now carry the full acceptance-evidence packages;
**2026-07-05 Increment 6 (capabilities enforcement cutover) reconciled** — +3
routes newly flag-gated by the existing `capabilities` flag, +1 admin route
added with no flag gate at all, both reconciled below and against
`PHASE_5_STATUS.md`; **2026-07-09 Increments 8–9 reconciled** —
`provider_registry` (Inc 8, generic OIDC, PR #39) and `invitations` (Inc 9,
PR #40) landed deploy-dark and are now *consumed*, no longer inert/reserved;
**2026-07-09 Phase 5 Gate A/B2 default-on authorization reconciled** — accepted
Gate A and B2 support flags now default-ON for fresh installs while Gate B and
unfinished Phase 3/4 carryovers remain default-dark; the Source Code Audit below
is re-run to 2026-07-09 (53 literal `enabled()` keys) and the `invitations` row
carries its two-pass pre-merge review hardening)

This inventory lists feature flags that default to `false` in
`src/Core/FeatureFlags.php`, plus recently graduated flags retained here for
rollback traceability. A default-dark flag means the code and schema may be
present in a deploy, but the feature is unavailable until an operator explicitly
enables it through the `features` setting.

The Phase 3/4 dark flags are additionally ranked in
[Graduation Readiness Ranking](#graduation-readiness-ranking-phase-34-dark-flags)
by how little evidence each still needs before it can graduate.

Runtime source of truth for flag *availability*: `src/Core/FeatureFlags.php`
(the `DEFAULTS` map merged with the `features` settings override). A flag's
*effective* deploy-dark posture also depends on **enforcement** — route/DI gating
in `src/Core/App.php` that makes an off flag 404 or no-op — and on config-gates
that can leave an *on* flag inert (e.g. `giphy_public_key`,
`PACKAGE_EXECUTION_DISABLED`, theme safe mode). Those per-flag nuances are called
out in the rows and the Notes below, not derivable from `DEFAULTS` alone.

## Source Code Audit

Audited 2026-07-09 against `src/Core/FeatureFlags.php`, literal
`FeatureFlags::enabled('...')` call sites in `src/`, and shared
`$features[...]` consumers in templates/bootstrapping code.

- `FeatureFlags::DEFAULTS` declares 57 flags: 47 default `true`, 10 default
  `false`. (Phase 5 Gate A/B2 support flipped `false`->`true` on 2026-07-09;
  the admin feature-inventory canary in
  `tests/Integration/Admin/AppAdminFeaturesTest.php` enforces the `47`/`10`
  split.)
- This deploy-dark inventory has 39 table rows: all 10 current default-dark
  flags, plus 29 retained graduated flags that are default-ON and
  operator-reversible.
- No table flag is absent from `FeatureFlags::DEFAULTS`; no current
  default-dark flag is missing from these tables.
- All 53 unique literal `FeatureFlags::enabled('...')` keys used in `src/` are
  declared in `FeatureFlags::DEFAULTS`. The declared flags without direct
  literal `enabled()` reads in `src/` are expected: `wysiwyg_composer` is
  consumed from the shared feature map in `templates/layout.php`, while
  `governance`, `service_principals`, and `verified_links` are still
  inert/reserved workstreams. (`provider_registry` and `invitations` left this
  list when Inc 8/9 wired their runtimes — 2 and 7 literal `enabled()` reads
  respectively.)
- The default-ON baseline flags intentionally outside this deploy-dark inventory
  are: `engagement`, `notifications`, `email`, `mentions`, `search`, `dms`,
  `moderation_queue`, `community`, `oauth`, `presence`, `announcements`,
  `rich_composer`, `drafts`, `uploads`, `anti_abuse`, `branding`, `seo`, and
  `product_tour`.
- **Increment 6 reconciliation (2026-07-05):** Inc 6 added 3 *routes* under the
  `capabilities` flag (`POST /admin/roles/{id}/assignments`,
  `POST /admin/role-assignments/{id}/revoke`,
  `POST /admin/role-assignments/{id}/renew`; see the `capabilities` row below).
  Separately, `UserModerationService::changeRole()`
  (`POST /admin/users/{id}/role`) is a **new admin route with no feature-flag
  gate at all** — by design (ADR 0016 decision 5): it manages `users.role`,
  which exists independent of Phase 5, so there is nothing to gate dark. It is
  intentionally absent from every table below (nothing to roll back via a
  `features` override) and is reachable — confirmed 422, not 404 — even with
  `capabilities` fully off
  (`docs/evidence/phase5/capabilities-fallback-rehearsal.md`).
- **Phase 5 Gate A/B2 default flip (2026-07-09):** the fresh-install split
  changed from `37`/`20` to `47`/`10`. Inc 8 (generic OIDC, PR #39,
  2026-07-07) and Inc 9 (invitations, PR #40, merged 2026-07-09) wired their
  runtimes, so both now carry literal `enabled()` reads in `src/` (unique-key
  count 51 -> 53) and their Phase 5 Gate A rows below reflect landed evidence.
  Inc 9's `invitations` row additionally reflects two pre-merge
  review-hardening passes merged with PR #40 (honest `invitation.redemption_p95`
  461.79 ms under production-cost Argon2id). Gate B plus the six unfinished
  Phase 3/4 carryovers (`custom_css`, `group_dms`, `community_memory`,
  `link_previews`, `expanded_files`, `automated_context`) remain default-dark.

## Phase 3 / Phase 3 Carryover

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_drafts` | Authenticated cross-device draft sync, conflict handling, `/drafts` server list/discard | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppServerDraftsTest` (available-by-default + rollback), `AppAccountLifecycleTest` (export/purge), browser `28-server-draft-conflict`, `.composer-draft-sync` axe pass, runbook `docs/runbooks/server_drafts.md`, ADR 0010. Retained here for traceability. |
| `wysiwyg_composer` | Milkdown WYSIWYG layer over the canonical Markdown textarea | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override; `rich_composer=false` remains the emergency kill switch). Acceptance evidence: ADR 0013, runbook `docs/runbooks/wysiwyg_composer.md`, `AppComposerTest`, `AppComposerSuggestTest`, `AppMentionLinkRenderTest`, `MarkdownRoundTripTest`, `npm run check:wysiwyg`, browser `wysiwyg-composer.spec.ts` (CSP + GA-default mount with no override, source mode, no-op edit, preview parity, chips, internal URL paste, mobile smoke, textarea fallback), and `a11y.spec.ts` WYSIWYG toolbar/picker/source scans. Retained here for traceability. |
| `appeals` | Self-service moderation appeals and staff appeal queue | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppModerationAppealsTest`, `AppFeatureFlagTest` (default-on + operator rollback to 404), browser `44-appeals-member`/`45-appeals-staff-queue`, `/appeals` + `.appeal-resolve` axe pass, runbook `docs/runbooks/appeals.md`, ADR 0007. Retained here for traceability. |
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
| `content_references` | Persisted references from post/DM/summary bodies, rendered as read-gated board/thread/post/tag cards | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppFeatureFlagTest` (default-on + operator rollback), `AppContentReferenceTest` (post/DM/summary/tag references through read gate), `AppPhase4CarryoverFoundationTest` (default posture), browser `43-content-references-redacted`, `.reference-cards` axe pass. Retained here for traceability. |

## Phase 4 Carryover Completion

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `link_previews` | Allowlisted server-fetched URL metadata, admin purge/refresh | Deploy-dark; awaiting browser, crawler/noindex, load, and runbook evidence |
| `expanded_files` | PDF/text-family uploads, scanner/quarantine state, download-only serving | Deploy-dark; awaiting browser/no-JS, scanner-outage smoke, and operator runbook |
| `polls` | One-poll-per-thread no-JS create/vote/close/result flow | **Graduated 2026-06-30 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: browser `25-poll-voted`, `.poll-panel` axe pass, runbook `docs/runbooks/polls.md`. Retained here for traceability. |
| `custom_emoji` | Operator-managed shortcode assets and optional reactions | **Graduated 2026-07-03 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppCustomEmojiGiphyTest` (available-by-default + rollback, Markdown rendering, reaction compatibility), `AppPhase4CarryoverFoundationTest`, browser `48-custom-emoji-admin`/`49-custom-emoji-thread`, `.custom-emoji-panel`/`.post-body`/`.reactions` axe passes, runbook `docs/runbooks/custom_emoji.md`. Retained here for traceability. |
| `slash_giphy` | Progressive slash insertion and client-side GIPHY picker config | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; **inert until `giphy_public_key` is set**; reversible via `features` override or by clearing the key). Acceptance evidence: `AppCustomEmojiGiphyTest` (incl. operator-rollback re-gate), `AppPhase4CarryoverFoundationTest`, browser `26-slash-menu`/`27-giphy-inserted`, `.composer-slash-menu` axe + keyboard pass, runbook `docs/runbooks/slash_giphy.md`. Retained here for traceability. |
| `split_merge` | Moderator split/merge routes, redirects, audit, touched-counter repair | **Graduated 2026-07-03 — now default-ON** (no longer deploy-dark; reversible via `features` override; routes 404 when disabled). Acceptance evidence: `AppThreadSplitMergeTest`, `AppThreadSplitMergeRehearsalTest` (seeded-scale zero-counter-drift; `docs/evidence/split-merge-repair-rehearsal.md`), `AppFeatureFlagTest` (default-on + operator rollback to 404), browser `50-split-merge-panel`/`51-thread-merged`, `.sm-panel` axe pass, runbook `docs/runbooks/split_merge.md`. Retained here for traceability. |
| `profile_media` | Avatar upload/removal, signature hardening, moderator avatar/signature removal | **Graduated 2026-07-03 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppProfileMediaTest` (available-by-default + rollback, self/admin removal, local-media retirement), `AppFeatureFlagTest`, `AppModerationAppealsTest` (`clear_avatar` appealability), browser `46-profile-media-avatar`/`47-profile-media-moderation`, `.profile-media-panel`/`.profile-media-card` axe passes, runbook `docs/runbooks/profile_media.md`. Retained here for traceability. |
| `board_folders` | Private personal board folders | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `bookmark_folders` | Private folders for starred/bookmarked threads | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `saved_feeds` | Private saved feed filters and digest-composition groundwork | **Graduated 2026-07-01 — now default-ON** (no longer deploy-dark; reversible via `features` override). Acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, Imladris map `docs/design-system/imladris/ACTIVATED_FEATURES.md`. Retained here for traceability. |
| `custom_profile_fields` | Bounded extra public profile fields | **Graduated 2026-07-03 — now default-ON** (no longer deploy-dark; reversible via `features` override; the `/settings/account` panel + public *Profile details* section render-gate on the flag). Acceptance evidence: `AppBoardFoldersSavedFeedsTest` (public-profile render + bounded 422), `AppFeatureFlagTest` (default-on + operator rollback hides the panel while core editing survives), browser `52-custom-profile-fields-edit`/`53-custom-profile-fields-profile`, `.custom-profile-fields` axe pass, runbook `docs/runbooks/custom_profile_fields.md`, privacy/copy review `docs/evidence/custom-profile-fields-privacy-review.md`. Retained here for traceability. |
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
     `AppFeatureFlagTest` covers it via `test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible`
     (default-on plus operator rollback to 404; its dark cross-check now uses
     `group_dms` since appeals graduated 2026-07-02); the `phase 4 account lifecycle` Playwright
     journey drives export→deactivate→reactivate→request→cancel through the no-JS
     forms (dedicated `dana` account), capturing `35-account-lifecycle` +
     `36-account-deletion-scheduled` on desktop + mobile; `/settings/account/lifecycle`
     added to the member axe scan in `tests/browser/a11y.spec.ts`; operator runbook
     `docs/runbooks/account_lifecycle.md`.
   - Fixed during graduation: `bin/console worker:purge-accounts` was constructing
     `AccountLifecycleService` with a bare `PasswordHasher` after the F7 `ReauthGate`
     refactor (a `TypeError` — the pre-refactor "smoke exit 0" claim had silently
     regressed). Now wires `new ReauthGate(new PasswordHasher())`; re-verified exit 0.
5. **`appeals`** — ✓ **Graduated 2026-07-02 (default-ON).** Members appeal
   eligible deleted/moderated actions through no-JS forms on `/appeals`; the
   staff queue is board-scoped like the report queue (2026-06-30 hardening), with
   reverse restoration (delegated to the owning moderation service),
   notification, and an append-only appeal-event audit; policy ADR 0007.
   - Evidence completed: `AppModerationAppealsTest` (member-open → one-active
     → board-scoped-queue → resolve/reverse); `AppFeatureFlagTest`
     `test_appeals_carryover_defaults_on_and_is_operator_reversible` (default-on
     plus operator rollback re-gating every appeals route to 404); the appeals
     Playwright journey `tests/browser/appeals.spec.ts` drives member open →
     staff resolve through the no-JS forms, capturing `44-appeals-member` +
     `45-appeals-staff-queue` on desktop + mobile; the member `/appeals` axe scan
     plus a staff `/mod/appeals` `.appeal-resolve` scan added to
     `tests/browser/a11y.spec.ts`; the appeal-target fixture moved onto the
     standard evidence seed path; operator runbook `docs/runbooks/appeals.md`.

### Tier 3 — small surfaces, but evidence must be captured from scratch

6. **`content_references`** — ✓ **Graduated 2026-07-02 (default-ON).**
   Board/thread/post/DM/summary references persisted at write time and rendered
   as reference cards only when the viewer can read the target; inaccessible
   targets are redacted.
   - Evidence completed: `AppFeatureFlagTest`
     (`test_content_references_are_available_by_default_and_can_be_disabled`
     asserts default-on + operator rollback), `AppContentReferenceTest` (post,
     DM, summary, and tag reference persistence + read-gate), and
     `AppPhase4CarryoverFoundationTest` (default posture); the e2e private-board
     fixture (`14-private-board-member.png`) was reused to capture desktop+mobile
     `43-content-references-redacted` showing a public target card rendered while
     the private-board target is redacted for a non-member; `.reference-cards`
     scoped axe pass added to `tests/browser/a11y.spec.ts`.
7. **`custom_emoji`** — ✓ **Graduated 2026-07-03 (default-ON).**
   Operator-managed static PNG/WebP shortcode assets rendered through the server
   Markdown sanitizer, optionally usable as reactions.
   - Evidence completed: `AppCustomEmojiGiphyTest` (default-on + operator
     rollback, admin create, Markdown rendering outside code/pre, and reaction
     compatibility), `AppPhase4CarryoverFoundationTest` default posture,
     browser captures `48-custom-emoji-admin` + `49-custom-emoji-thread`, scoped
     axe scans for `.custom-emoji-panel`, `.post-body`, and `.reactions`, and
     operator/media-moderation runbook `docs/runbooks/custom_emoji.md`.
8. **`profile_media`** — ✓ **Graduated 2026-07-03 (default-ON).** Local avatar
   upload/removal plus signature hardening (three-line height enforcement) and
   audited admin avatar/signature removal; `clear_avatar` and `clear_signature`
   remain appealable user-moderation actions.
   - Evidence completed: `AppProfileMediaTest` (default-on + operator rollback,
     member upload/remove, admin clear-avatar/clear-signature, attachment row
     deletion for local `/media/{id}` avatars, validation re-render);
     `AppFeatureFlagTest` and `AppPhase4CarryoverFoundationTest` assert the
     default posture and rollback isolation; `AppModerationAppealsTest` covers
     `clear_avatar` appeal eligibility; browser captures
     `46-profile-media-avatar` + `47-profile-media-moderation`; scoped axe scans
     for `.profile-media-panel` and `.profile-media-card`; operator runbook
     `docs/runbooks/profile_media.md`.
9. **`custom_profile_fields`** — ✓ **Graduated 2026-07-03 (default-ON).** Up to
   three member-authored `label`/`value` profile facts (migration `0062`),
   editable on `/settings/account` and rendered on the public profile.
   - Evidence completed: `AppBoardFoldersSavedFeedsTest` (public-profile render,
     XSS escaping, and the bounded-`422` slice), `AppFeatureFlagTest`
     (`test_custom_profile_fields_is_available_by_default_and_can_be_disabled` —
     default-on rendering plus operator rollback hiding the panel while core
     profile editing survives), and the admin feature-inventory count canary in
     `AppAdminFeaturesTest`; desktop+mobile browser captures
     `52-custom-profile-fields-edit`/`53-custom-profile-fields-profile` driven by
     `tests/browser/gate-a.spec.ts`; a `.custom-profile-fields` scoped axe pass in
     `tests/browser/a11y.spec.ts`; operator runbook
     `docs/runbooks/custom_profile_fields.md`; and the profile/privacy copy review
     `docs/evidence/custom-profile-fields-privacy-review.md` (the product step this
     entry was previously blocked on).

### Tier 4 — require operational rehearsals beyond Playwright

10. **`split_merge`** — ✓ **Graduated 2026-07-03 (default-ON).** Moderator thread
    split/merge over the existing thread-operation/redirect schema, with audit and
    touched thread/board counters maintained inside the transaction.
    - Evidence completed: `AppThreadSplitMergeTest`; the seeded-scale
      zero-counter-drift `AppThreadSplitMergeRehearsalTest`
      (`docs/evidence/split-merge-repair-rehearsal.md`) — the "larger seeded-scale
      repair rehearsal" this entry previously listed as outstanding;
      `AppFeatureFlagTest` (default-on plus operator rollback re-gating both
      `/mod/t/{id}/split` and `/merge` routes to 404), and the admin
      feature-inventory count canary in `AppAdminFeaturesTest`; desktop+mobile
      browser captures `50-split-merge-panel`/`51-thread-merged` (create topic +
      reply → split the reply out → merge it back) driven by
      `tests/browser/gate-a.spec.ts`; a `.sm-panel` scoped axe pass in
      `tests/browser/a11y.spec.ts`; operator runbook
      `docs/runbooks/split_merge.md`.
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
as of 2026-07-09.

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `package_registry` | Signed registry, package catalogue/install/update foundation, integration + security-response consoles | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.package_registry=false`. Inc 2/3 implementation landed — signed trust chain, snapshots/advisories, staff catalogue, install/consent/enable/update/rollback/uninstall/export, health worker, browser/axe evidence, and runbook. Inc 5 landed the non-theme runtime bridges behind the same flag — install-scoped settings/secret storage, read-only API tokens, package-owned webhooks, the publisher-trust + local-review consoles, and the `/admin/packages/security` security-response console with a **flag-independent** emergency execution brake (`PACKAGE_EXECUTION_DISABLED` / `package_execution_disabled` setting pauses every package-owned webhook + denies credential auth while leaving view/revoke/export/uninstall intact), with browser/axe evidence (`60`/`61`). Enabled packages are eligibility only until their runtimes are enabled. |
| `package_themes` | Declarative theme packages, preview, activation, safe mode, rollback | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.package_themes=false`. Inc 4 implementation landed behind `package_themes` — strict theme manifest block, token/asset/contrast gates, deterministic digest-addressed CSS builds, admin preview isolation, password-reauth activation/LKG rollback, flag-independent safe mode, fail-safe deactivation/repair, browser/axe evidence, and runbook. Rollback contract unchanged: set `features.package_themes=false` or enter safe mode; active/LKG rows are preserved. |
| `capabilities` | DB-backed roles/capability resolver and scoped grants | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.capabilities=false`. Foundation + Increment 1 resolver shadow landed — catalogue/role-map seed (`0066`), protected-owner spine, `CapabilityResolver` + fail-open `ResolverShadow`, zero-mismatch parity corpus (`docs/evidence/phase5/resolver-parity.md`), no-JS role editor/simulator with browser/axe evidence. **Increment 6 (2026-07-05) landed the enforcement cutover** — `AuthorityGate` (`legacy`/`shadow`/`enforce`, posture via `CAPABILITIES_MODE`) now backs ~30 board/content call sites plus the 4 board-roster POST commands; disabling the flag still gates 3 *new* routes (`POST /admin/roles/{id}/assignments`, `POST /admin/role-assignments/{id}/revoke`, `POST /admin/role-assignments/{id}/renew` — scoped-assignment grant/revoke/renew) to 404, regardless of `CAPABILITIES_MODE`. ADR 0016, runbook `docs/runbooks/capabilities.md`. |
| `passkeys` | WebAuthn registration, sign-in, credential management, step-up, recovery fallback | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.passkeys=false`. Inc 7 implementation landed behind `passkeys` on 2026-07-03 — enrollment/list/rename/revoke, sign-in, step-up, TOTP/recovery fallback compatibility, CDP browser evidence, runbook, and requirement-ledger R4 evidence are present. Privileged-MFA enforcement and usernameless sign-in remain Gate B. |
| `provider_registry` | Generic OIDC and provider registry expansion | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.provider_registry=false`. **Inc 8 implementation landed (2026-07-07)** — generic-OIDC verification core (issuer-pinned discovery -> JwksCache -> JwtVerifier -> ClaimMapper) through the shared OAuth/account-resolution core, `0074` identity backfill + `0075` audit widen, `/admin/providers` console (add with vault-stored secret, health probe, reauth'd enable/disable with the TM-ID-09 sole-method confirm page), TM-ID-01..04 implemented, both oidc D11 rows MEASURED (PASS), browser/axe evidence (`66`-`68`), runbook `docs/runbooks/provider_registry.md`. A2 = GitLab.com as pure configuration (`docs/phase5/first-oidc-provider.md`). Sequencing: `service_secrets` must be live first. |
| `invitations` | Invitation lifecycle and invite-based registration | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.invitations=false`. **Inc 9 implementation landed (2026-07-08)** — admin console (`/admin/invitations`: show-once issue / list / revoke, rate-limited, audited via the `0076` widen), atomic redemption through `/register` + `/invite/{token}` (uniform enumeration, email/exact-domain binding, board grant, **no role application** — decision #36), and the explicit `registration_mode = invite` via `RegistrationPolicy` (closed stays absolute; invite **fails closed when rolled back**, so the flag doubles as the invitation-pause switch and both the form and OAuth provisioning channels agree). TM-IN-01..07 implemented, `invitation.redemption_p95` 461.79 ms MEASURED (PASS) (production-cost Argon2id), browser/axe evidence (`69`-`74`), runbook `docs/runbooks/invitations.md`, defaults decision `docs/phase5/invitation-defaults.md`. |
| `service_secrets` | Encrypted service-secret vault for providers/webhooks | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.service_secrets=false`. Storage/rotate/revoke/prune seam implemented with focused evidence (R3) and consumed by webhook delivery; doubles as the write/rotate kill switch (reveal/revoke/prune stay available when rolled back); provider/remote-app consumers deferred |
| `api_tokens` | Admin/service Bearer tokens and read-only `/api/v1` | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.api_tokens=false`. Implemented with focused release evidence (R3) — PHPUnit across flag/schema/service/endpoints/admin plus browser `20`/`21` mint -> show-once -> revoke; write surfaces remain out of scope |
| `webhooks` | Outbound webhook delivery engine and admin UI | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.webhooks=false`. Implemented with focused release evidence (R3) — engine/worker/admin PHPUnit plus browser `22`/`23` register -> delivery log; endpoint-level policy still controls what may be delivered |
| `first_party_hooks` | Code-only first-party domain hooks and webhook producers | **Graduated 2026-07-09 - now default-ON**; operator-reversible via `features.first_party_hooks=false`. Implemented (R3) — public-board domain producers with IDs/state-only payloads; private/hidden-board and DM events suppressed until endpoint-level data-class permissions exist |

## Phase 5 Gate B / Reserved

| Flag | Surface | Broad-rollout state |
|---|---|---|
| `server_extensions` | Sandboxed isolated server-extension runtime | Deploy-dark; manifest validation, fail-closed Bubblewrap probe seam, async worker, and admin inspection landed (`0065`); no public/untrusted runtime until Phase 5 Gate B sandbox acceptance |
| `governance` | Operator groups, approvals, access review | Deploy-dark (inert/reserved — no `enabled()` consumer or route yet); reserved for Phase 5 Gate B |
| `service_principals` | Remote-app service identities | Deploy-dark (inert/reserved — no `enabled()` consumer or route yet); reserved for Phase 5 Gate B |
| `verified_links` | Verified profile links and richer profile fields | Deploy-dark (inert/reserved — no `enabled()` consumer or route yet); reserved for Phase 5 Gate B |

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
- `wysiwyg_composer` graduated out of deploy-dark on 2026-07-02: its
  `FeatureFlags` default is now `true` (its acceptance evidence had already
  landed with PR #33). The browser-evidence seed intentionally pins it OFF so
  the gate-a screenshot journeys keep capturing the textarea
  progressive-enhancement baseline; `wysiwyg-composer.spec.ts` proves the GA
  default mounts Milkdown with no features override.
- `server_drafts` graduated out of deploy-dark on 2026-07-02 (the first Tier 1
  graduation from the readiness ranking): its `FeatureFlags` default is now
  `true`. Acceptance completed the ADR 0010 evidence list — the browser conflict
  capture moved into the standard `npm run evidence` run, a `.composer-draft-sync`
  conflict-panel axe pass was added, and the operator runbook landed at
  `docs/runbooks/server_drafts.md`. It is retained here for traceability and
  remains reversible via the `features` override; the browser-local `drafts`
  fallback is unaffected when it is disabled.
- `slash_giphy` graduated out of deploy-dark on 2026-07-02: its `FeatureFlags`
  default is now `true`, but it is inert until `giphy_public_key` is configured.
  It is retained here for traceability and remains reversible either through the
  `features` override or by clearing the GIPHY key.
- `badge_rules` graduated out of deploy-dark on 2026-07-02: its `FeatureFlags`
  default is now `true`. It is retained here for traceability, remains
  reversible via the `features` override, and preserves award/revoke history
  across rollback.
- `account_lifecycle` graduated out of deploy-dark on 2026-07-02: its
  `FeatureFlags` default is now `true`. It is retained here for traceability and
  remains reversible via the `features` override; the purge worker keeps its
  `pending_deletion` guard when the feature is enabled.
- `content_references` graduated out of deploy-dark on 2026-07-02: its
  `FeatureFlags` default is now `true`. It is retained here for traceability and
  remains reversible via the `features` override; persisted reference rows stay
  intact when the flag is rolled back and cards simply stop rendering.
- `appeals` graduated out of deploy-dark on 2026-07-02 (the first Tier 2
  graduation from the readiness ranking): its `FeatureFlags` default is now
  `true`. Acceptance completed the ADR 0007 evidence list — the appeals browser
  journey (`tests/browser/appeals.spec.ts`, captures `44-appeals-member` /
  `45-appeals-staff-queue`) moved into the standard `npm run evidence` run, a
  staff `/mod/appeals` `.appeal-resolve` axe scan joined the existing member
  `/appeals` scan, and the operator runbook landed at `docs/runbooks/appeals.md`.
  It is retained here for traceability and remains reversible via the `features`
  override; disabling the flag re-gates every route to 404 but never re-reverses
  a resolution already carried out by the owning moderation service.
- `profile_media` graduated out of deploy-dark on 2026-07-03: its
  `FeatureFlags` default is now `true`. Acceptance added audited admin avatar
  removal beside signature removal, retired local avatar media rows on member or
  admin removal, made `clear_avatar` appealable beside `clear_signature`, added
  desktop/mobile browser captures (`46-profile-media-avatar` /
  `47-profile-media-moderation`), scoped axe scans for the member/admin panels,
  and the operator runbook at `docs/runbooks/profile_media.md`. It is retained
  here for traceability and remains reversible via the `features` override;
  disabling the flag stops new profile-media mutations but does not erase
  already stored profile values.
- `custom_emoji` graduated out of deploy-dark on 2026-07-03: its
  `FeatureFlags` default is now `true`. Acceptance added the no-JS admin
  catalogue panel, default-on/rollback tests, desktop/mobile browser captures
  (`48-custom-emoji-admin` / `49-custom-emoji-thread`), scoped axe scans for the
  admin and rendered post/reaction surfaces, and the media-moderation runbook at
  `docs/runbooks/custom_emoji.md`. It is retained here for traceability and
  remains reversible via the `features` override; disabling the flag stops new
  catalogue mutations and shortcode rendering while preserving existing rows.
- `split_merge` graduated out of deploy-dark on 2026-07-03: its `FeatureFlags`
  default is now `true`. Acceptance completed the browser + runbook + seeded-scale
  repair-rehearsal items its readiness entry had listed as outstanding — the
  `AppThreadSplitMergeRehearsalTest` zero-counter-drift rehearsal
  (`docs/evidence/split-merge-repair-rehearsal.md`), the desktop/mobile browser
  journey (`50-split-merge-panel` / `51-thread-merged`, driven by
  `tests/browser/gate-a.spec.ts`), a `.sm-panel` axe pass, and the operator
  runbook `docs/runbooks/split_merge.md`. It is retained here for traceability and
  remains reversible via the `features` override; disabling the flag re-gates both
  restructure routes to 404 but never reverses a split or merge already performed.
- `custom_profile_fields` graduated out of deploy-dark on 2026-07-03: its
  `FeatureFlags` default is now `true`. Acceptance completed the browser/a11y
  evidence and the profile/privacy copy review its readiness entry was blocked on
  — the desktop/mobile captures (`52-custom-profile-fields-edit` /
  `53-custom-profile-fields-profile`, driven by `tests/browser/gate-a.spec.ts`),
  a `.custom-profile-fields` axe pass, the operator runbook
  `docs/runbooks/custom_profile_fields.md`, and the privacy review
  `docs/evidence/custom-profile-fields-privacy-review.md`. It is retained here for
  traceability and remains reversible via the `features` override; disabling the
  flag hides the editor and the public *Profile details* section while preserving
  stored `user_profile_fields` rows.
- The graduation readiness ranking (added 2026-07-02) orders the Phase 3/4 dark
  flags by remaining evidence effort only; enablement order stays a product
  decision. `group_dms` and `community_memory` additionally require an
  intentional-enablement decision on top of their evidence packages.
