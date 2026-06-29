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
| Moderation appeals | Open | Phase 4 moderator operations | TBD | None | Policy decision, appeal queue, restoration/notification/audit evidence |
| Split/merge operations | Open; schema and flag exist only | Phase 4 moderator operations | `split_merge` | Flag/schema coverage only | Dry-run/apply/repair service, locks, redirects, counter/read-state repair, audit, browser/runbook evidence |
| Account deactivation/reactivation/export/delete | Open | Phase 4 profile/account or Phase 5 identity/governance | TBD | None | Policy decision, data-retention/export semantics, no-JS/browser evidence |
| Bookmark folders and limited custom profile fields | Open | Phase 4 profile/organization or Phase 5 verified-link/profile work | TBD | None | Schema/service/UI, privacy/read-gate rules, browser evidence |
| Server-side draft sync | Deferred | Phase 7 offline/sync unless explicitly pulled forward | `drafts` currently covers local drafts only | Existing local-draft evidence | Cross-device sync, conflict policy, retention/privacy decision |
| Public/plugin ecosystem runtime | Deferred | Phase 5 Gate B sandbox after Gate A acceptance | `server_extensions` remains dark | B2 trusted prerequisites landed only | No public/untrusted PHP until sandbox isolation is implemented and adversarially verified |
| Release operations evidence | Open | Release operations before broad deployment | N/A | Full PHPUnit green | Browser, a11y, load, crawler, backup/restore, upgrade rehearsal, and final runbook evidence |
