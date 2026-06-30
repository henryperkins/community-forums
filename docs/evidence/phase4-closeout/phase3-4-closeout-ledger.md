# Phase 3/4 Closeout Carryover Ledger

Date: 2026-06-29
Branch: `phase3-4-closeout-completion`

This ledger records the current release-train boundary. It reconciles stale
Phase 3/4 deferral wording against later landed code without treating the whole
carryover train as accepted.

## Later Phase 5 Work Not Counted As Open Phase 3

| Item | Current state | Flag | Evidence pointer | Owner |
|---|---|---|---|---|
| TOTP and recovery codes | Resolved as the Phase 5 Gate A identity fallback prerequisite | Account opt-in behavior; no global enablement flag | `PHASE_5_STATUS.md`, `AppMfaTest`, `TotpTest` | Product + Security |
| Read-only API tokens | Landed deploy-dark with admin mint/revoke and `/api/v1` read endpoints | `api_tokens` | `PHASE_5_STATUS.md`, API-token unit/integration/browser evidence | Platform + Security |
| Webhook delivery | Landed deploy-dark with SecretVault-backed signing, queue, worker, retry/dead-letter, and admin UI | `webhooks`, `service_secrets` | `PHASE_5_STATUS.md`, webhook service/worker/admin/browser evidence | Platform + Security |
| First-party hook producers | Landed deploy-dark as code-only domain producers into the webhook ledger | `first_party_hooks` | `PHASE_5_STATUS.md`, `DomainWebhookProducerTest`, `FirstPartyHookRegistryTest` | Platform + Engineering |

## Phase 3/4 Carryover Train

| Carryover | Current state | Destination | Flag | Evidence now | Remaining acceptance evidence |
|---|---|---|---|---|---|
| Restricted non-image attachments | Implemented deploy-dark for PDF/text-family upload, scanner/quarantine state, private access checks, download-only serving, and cleanup helpers | Phase 4 closeout | `expanded_files` plus `uploads` | `AppExpandedFilesTest`; full suite green | Browser/no-JS evidence, scanner-outage worker smoke, operator runbook |
| Link previews | Implemented deploy-dark queue/fetch/admin purge-refresh with allowlist and SSRF controls | Phase 4 closeout | `link_previews` | `AppLinkPreviewTest`; full suite green | Browser, crawler/noindex, load, and runbook evidence |
| Polls | Implemented deploy-dark one-poll-per-thread no-JS create/vote/close/result flow | Phase 4 closeout | `polls` | `AppPollTest`; full suite green | Browser/a11y evidence and release runbook |
| Custom emoji | Implemented deploy-dark operator records, shortcode rendering, and optional reactions | Phase 4 closeout | `custom_emoji` | Custom emoji regressions; full suite green | Browser/a11y evidence and media moderation runbook |
| GIPHY/slash insertion | Implemented deploy-dark slash menu with approved Markdown snippets and direct GIPHY media insertion | Phase 4/5 UI polish | `slash_giphy` | `AppCustomEmojiGiphyTest`; desktop/mobile `26-slash-menu` + `27-giphy-inserted`; full suite green | Broader privacy/provider runbook and a11y sign-off |
| Badge rules | Implemented deploy-dark preview, enable/disable, backfill, revoke, and history for constrained rule vocabulary | Phase 4 closeout | `badge_rules` | Badge-rule regressions; full suite green | Operator browser evidence and rollback rehearsal |
| Content references | Implemented post, DM-message, and summary capture/render with read-gated cards | Phase 4 closeout | `content_references` | `AppContentReferenceTest`; full suite green | Browser/no-JS and inaccessible-target evidence |
| Board folders and saved feed filters | Implemented private personal organization records and settings routes | Phase 4 closeout | `board_folders`, `saved_feeds` | `AppBoardFoldersSavedFeedsTest`; full suite green | Browser/a11y evidence and digest-composition decision |
| Since-last-read context | Implemented deterministic local-data assembly before advancing read state | Phase 4 closeout | `automated_context` | `AppAutomatedContextTest`; full suite green | Browser/no-JS evidence, replay/disable runbook |
| Scheduled related-topic refresh | Implemented deterministic tag-based public-thread refresh worker | Phase 4 closeout | `community_memory`, `automated_context`, `tags` | `RelatedTopicRefreshWorkerTest`; dark console smoke; full suite green | Populated worker smoke, runbook, stale-link cleanup policy |
| Avatar upload/removal and safe signatures | Implemented deploy-dark local avatar upload/removal, three-line signature height cap, and moderator signature removal audit | Phase 4 closeout | `profile_media` | `AppProfileMediaTest`; full suite green | Browser/a11y evidence and moderation runbook |
| Moderation appeals | Implemented member appeal submission, staff queue, reverse/uphold outcomes, restoration, notifications, and audit | Phase 4 moderator operations | `appeals` | `AppModerationAppealsTest`; focused suite green | Browser/no-JS queue evidence and moderation runbook |
| Split/merge operations | Implemented service/routes using existing `0048` thread operation/redirect schema with repair pass and audit | Phase 4 moderator operations | `split_merge` | `AppThreadSplitMergeTest`; focused suite green | Browser/runbook evidence and larger repair rehearsal |
| Account deactivation/reactivation/export/delete | Implemented lifecycle states, export, 30-day deletion request/cancel, purge/anonymization, and final-active-admin guard | Phase 4 profile/account or Phase 5 identity/governance | Always available through account settings | `AppAccountLifecycleTest`; focused suite green | Browser/no-JS evidence and scheduled purge runbook |
| Bookmark folders and limited custom profile fields | Implemented private folders for starred threads and bounded public profile fields | Phase 4 profile/organization or Phase 5 verified-link/profile work | `bookmark_folders`, `custom_profile_fields` | `AppBoardFoldersSavedFeedsTest`; focused suite green | Browser/a11y evidence and profile privacy copy review |
| Advanced local theming | Implemented retro preset, light/dark logo variants, and guarded custom CSS behind dark flag | Phase 4/branding carryover | `custom_css` plus `branding` | `AppBrandingThemeTest`; focused suite green | Browser safe-mode/mobile evidence and operator runbook |
| Email broadcast/domain verification | Implemented announcement email broadcast, `system` worker rendering, cached SPF/DKIM status, manual refresh, opt-in send blocking | Phase 2/4 operator carryover | Setting/config-gated send blocking | `AppAdminEmailTest`, email service/worker suites; focused suites green | Browser/operator runbook and production DNS smoke |
| Server-side draft sync | Explicitly deferred | Phase 7 offline/sync unless explicitly pulled forward | `drafts` currently covers local drafts only | ADR 0010; existing local-draft evidence | Cross-device sync, conflict policy, retention/privacy decision if pulled forward |
| Public/plugin ecosystem runtime | Explicitly deferred | Phase 5 Gate B sandbox after Gate A acceptance | `server_extensions` remains dark | ADR 0011; B2 trusted prerequisites landed only | No public/untrusted PHP until sandbox isolation is implemented and adversarially verified |
| Release operations evidence | Open | Release operations before broad deployment | N/A | Full PHPUnit green | Browser, a11y, load, crawler, backup/restore, upgrade rehearsal, and final runbook evidence |
