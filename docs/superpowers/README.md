# Superpowers design archive

This directory is the **design-decision archive** for RetroBoards: the brainstorming design specs and implementation plans behind shipped work. It is historical — the authoritative product/technical source of truth remains the spec chain (`DECISIONS.md` > `PRODUCT_DESIGN.md` > `SCHEMA.md` > the surface specs) and the phase plans/status. ADRs and `PHASE_5_STATUS.md` cite entries here as the rationale record.

**Consolidated 2026-07-09:** the *then-existing* `plans/` + `specs/` split was collapsed — each design spec was merged into its paired implementation plan (the doc now carries an *Archived design record* note and holds design + plan + review in one file), the five WYSIWYG docs merged into one, and `specs/` was emptied. **Entries dated 2026-07-09 and later returned to the paired layout** — an implementation plan in `plans/` with its design spec as a sibling in `specs/` (the table's last column says which). Archived entries retain their original internal references (to since-moved status docs, etc.) as historical records; they are not repointed.

| Date | Document | Title | Spec merged in |
|---|---|---|:--:|
| 2026-06-28 | [`2026-06-28-api-tokens.md`](plans/2026-06-28-api-tokens.md) | API Tokens (read-only slice) — design spec + implementation plan | ✓ |
| 2026-06-28 | [`2026-06-28-service-secret-registry.md`](plans/2026-06-28-service-secret-registry.md) | Encrypted Service-Secret Registry | ✓ |
| 2026-06-28 | [`2026-06-28-webhook-delivery.md`](plans/2026-06-28-webhook-delivery.md) | Webhook Delivery — B2 Sub-project 3 | ✓ |
| 2026-06-29 | [`2026-06-29-phase2-announcements-banner-broadcast.md`](plans/2026-06-29-phase2-announcements-banner-broadcast.md) | Admin Announcements (Site Banner + In-App Broadcast) Implementation Plan |  |
| 2026-06-29 | [`2026-06-29-phase2-board-structure-reorder-archive.md`](plans/2026-06-29-phase2-board-structure-reorder-archive.md) | Category/Board Reorder + Board Archive Implementation Plan |  |
| 2026-06-29 | [`2026-06-29-phase2-email-ops-dashboard.md`](plans/2026-06-29-phase2-email-ops-dashboard.md) | Admin Email Delivery Ops Dashboard Implementation Plan |  |
| 2026-06-29 | [`2026-06-29-phase2-operator-surfaces-contract.md`](plans/2026-06-29-phase2-operator-surfaces-contract.md) | Phase 2 Operator Surfaces — Shared Implementation Contract |  |
| 2026-06-29 | [`2026-06-29-phase2-per-user-admin-record.md`](plans/2026-06-29-phase2-per-user-admin-record.md) | Per-User Admin Record (Badges + Title) Implementation Plan |  |
| 2026-06-30 | [`2026-06-30-phase5-gate-a-program-plan.md`](plans/2026-06-30-phase5-gate-a-program-plan.md) | Phase 5 Gate A — Completion Program Plan |  |
| 2026-07-01 | [`2026-07-01-phase4-tags-feeds-reputation-graduation.md`](plans/2026-07-01-phase4-tags-feeds-reputation-graduation.md) | Phase 4 Tags, Feeds, Reputation Graduation Implementation Plan |  |
| 2026-07-01 | [`2026-07-01-phase5-foundation-f3-f5.md`](plans/2026-07-01-phase5-foundation-f3-f5.md) | Phase 5 Foundation F3 + F5 Implementation Plan |  |
| 2026-07-01 | [`2026-07-01-phase5-foundation-f9.md`](plans/2026-07-01-phase5-foundation-f9.md) | Phase 5 Foundation F9 — Fixture, Baselines & Budget Harness — Implementation Plan |  |
| 2026-07-01 | [`2026-07-01-phase5-foundation-remainder.md`](plans/2026-07-01-phase5-foundation-remainder.md) | Phase 5 Foundation Remainder — F2 · F4 · F6 · F7 · F8 · F10 · F11 — Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-admin-ux-remediation.md`](plans/2026-07-02-admin-ux-remediation.md) | Admin UX Remediation Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-phase5-increment1-resolver-shadow.md`](plans/2026-07-02-phase5-increment1-resolver-shadow.md) | Phase 5 Increment 1 — Capability Resolver in Shadow Mode (P5-08) Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-phase5-increment2-registry-protocol.md`](plans/2026-07-02-phase5-increment2-registry-protocol.md) | Phase 5 Increment 2 — Registry Protocol & Package Identity (P5-01) Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-phase5-increment3-package-lifecycle.md`](plans/2026-07-02-phase5-increment3-package-lifecycle.md) | Phase 5 Increment 3 — Package Manifest, Install & Lifecycle (P5-02 + P5-07-A part 1) Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-phase5-increment4-declarative-themes.md`](plans/2026-07-02-phase5-increment4-declarative-themes.md) | Phase 5 Increment 4 — Declarative Theme Packages (P5-03) Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-phase5-increment7-passkeys.md`](plans/2026-07-02-phase5-increment7-passkeys.md) | Phase 5 Increment 7 — Passkeys (P5-11) Implementation Plan |  |
| 2026-07-02 | [`2026-07-02-wysiwyg-composer.md`](plans/2026-07-02-wysiwyg-composer.md) | WYSIWYG Composer — Consolidated Design & Implementation Record | ✓ |
| 2026-07-03 | [`2026-07-03-dm-reading-room.md`](plans/2026-07-03-dm-reading-room.md) | Direct Messages — "one reading room" (reimagine) | ✓ |
| 2026-07-03 | [`2026-07-03-graduate-custom-profile-fields-split-merge.md`](plans/2026-07-03-graduate-custom-profile-fields-split-merge.md) | Graduate `custom_profile_fields` + `split_merge` — Design + Implementation Plan (Archived) | ✓ |
| 2026-07-03 | [`2026-07-03-phase5-gatea-plan-review-remediation.md`](plans/2026-07-03-phase5-gatea-plan-review-remediation.md) | Phase 5 Gate A Program-Plan Review Remediation — Implementation Plan |  |
| 2026-07-03 | [`2026-07-03-phase5-increment5-package-integrations-security-response.md`](plans/2026-07-03-phase5-increment5-package-integrations-security-response.md) | Design: Phase 5 Increment 5 - Package Integrations and Security Response | ✓ |
| 2026-07-04 | [`2026-07-04-inc6-resolver-enforcement-cutover.md`](plans/2026-07-04-inc6-resolver-enforcement-cutover.md) | Design: Increment 6 — Resolver Enforcement Cutover + Scoped Assignment Lifecycle | ✓ |
| 2026-07-08 | [`2026-07-08-phase5-increment9-invitations.md`](plans/2026-07-08-phase5-increment9-invitations.md) | Phase 5 Increment 9 — Invitations (P5-13) Implementation Plan |  |
| 2026-07-09 | [`2026-07-09-thread-intelligence-graduation.md`](plans/2026-07-09-thread-intelligence-graduation.md) | Thread Intelligence Graduation Implementation Plan | spec in [`specs/`](specs/2026-07-09-thread-intelligence-graduation-design.md) |
| 2026-07-12 | [`2026-07-12-community-inbox-theme-gap-closure.md`](plans/2026-07-12-community-inbox-theme-gap-closure.md) | Community Inbox Theme Gap Closure Implementation Plan | spec in [`specs/`](specs/2026-07-12-community-inbox-theme-gap-closure-design.md) |
| 2026-07-12 | [`2026-07-12-thread-view-study.md`](plans/2026-07-12-thread-view-study.md) | Thread View — The Study Implementation Plan | spec in [`specs/`](specs/2026-07-12-thread-view-study-design.md) |
| 2026-07-13 | [`2026-07-13-composer-slackify.md`](plans/2026-07-13-composer-slackify.md) | Composer Shell — "The Writing Desk" Implementation Plan | spec in [`specs/`](specs/2026-07-13-composer-slackify-design.md) |
| 2026-07-18 | [`2026-07-18-pr44-safety-remediation.md`](plans/2026-07-18-pr44-safety-remediation.md) | PR #44 Safety Remediation — Implementation Plan | spec in [`specs/`](specs/2026-07-18-pr44-safety-remediation-design.md) |
| 2026-07-19 | [`specs/2026-07-19-composer-enter-to-send-hint-design.md`](specs/2026-07-19-composer-enter-to-send-hint-design.md) | Composer — Make Enter-to-send Discoverable (design spec only) | — |
| 2026-08-02 | [`2026-08-02-imladris-board-identity-prototype.md`](plans/2026-08-02-imladris-board-identity-prototype.md) | Imladris Board Identity Prototype Implementation Plan | spec in [`specs/`](specs/2026-08-02-imladris-forum-inbox-board-identity-design.md) |
| 2026-08-02 | [`2026-08-02-imladris-forum-surfaces-production.md`](plans/2026-08-02-imladris-forum-surfaces-production.md) | Imladris Forum Surfaces Production Implementation Plan | spec in [`specs/`](specs/2026-08-02-imladris-forum-surfaces-production-design.md) |
| 2026-08-03 | [`2026-08-03-imladris-admin-account-adoption.md`](plans/2026-08-03-imladris-admin-account-adoption.md) | Imladris → production: admin & account surface adoption — Stage 1 (inventory & comparison) | — |
| 2026-08-03 | [`2026-08-03-imladris-admin-account-ledger.md`](plans/2026-08-03-imladris-admin-account-ledger.md) | LEDGER — consolidated deviation ledger (Stage 1, Imladris admin/account migration) | — |
| 2026-08-03 | [`specs/2026-08-03-board-topic-density-remediation-design.md`](specs/2026-08-03-board-topic-density-remediation-design.md) | Board Topic Density Remediation Design (design spec only) | — |
| 2026-08-03 | [`2026-08-03-forum-index-thread-remediation-checklist.md`](plans/2026-08-03-forum-index-thread-remediation-checklist.md) | Forum Index and Thread Remediation Implementation Plan | — |
| 2026-08-03 | [`2026-08-03-thread-content-presentation-remediation.md`](plans/2026-08-03-thread-content-presentation-remediation.md) | Thread Content Presentation Remediation Implementation Plan | spec in [`specs/`](specs/2026-08-03-thread-content-presentation-remediation-design.md) |
| 2026-08-04 | [`2026-08-04-imladris-admin-account-HANDOFF.md`](plans/2026-08-04-imladris-admin-account-HANDOFF.md) | HANDOFF — Imladris admin/account migration, resuming at Slice 2's evidence | — |
| 2026-08-06 | [`2026-08-06-imladris-admin-account-HANDOFF.md`](plans/2026-08-06-imladris-admin-account-HANDOFF.md) | HANDOFF — finish `feat/imladris-admin-account` and merge it into main | — |
| 2026-08-08 | [`2026-08-08-admin-ui-audit-remediation.md`](plans/2026-08-08-admin-ui-audit-remediation.md) | Admin UI Audit Remediation Implementation Plan | spec in [`specs/`](specs/2026-08-08-admin-ui-audit-remediation-design.md) |
| 2026-08-08 | [`2026-08-08-imladris-admin-account-HANDOFF.md`](plans/2026-08-08-imladris-admin-account-HANDOFF.md) | HANDOFF — finish `feat/imladris-admin-account` (slices 16–19) and merge | — |
| 2026-08-26 | [`2026-08-26-living-brief-redesign.md`](plans/2026-08-26-living-brief-redesign.md) | Living Brief Redesign Implementation Plan | spec in [`specs/`](specs/2026-08-26-living-brief-redesign-design.md) |
| 2026-08-27 | [`2026-08-27-thread-view-p0-p1-remediation.md`](plans/2026-08-27-thread-view-p0-p1-remediation.md) | Thread View P0/P1 Remediation Implementation Plan | spec in [`specs/`](specs/2026-08-27-thread-view-p0-p1-remediation-design.md) |
| 2026-08-27 | [`2026-08-27-member-surfaces-production-transfer.md`](plans/2026-08-27-member-surfaces-production-transfer.md) | Member Surfaces Production Transfer Implementation Plan | spec in [`specs/`](specs/2026-08-27-member-surfaces-production-transfer-design.md) |
| 2026-09-12 | [`2026-09-12-communitysystem-handoff.md`](plans/2026-09-12-communitysystem-handoff.md) | CommunitySystem handoff completion plan | spec: `CommunitySystem.zip` → archived at [`_archive/design_handoff_presence/`](../design-system/imladris/_archive/design_handoff_presence/README.md) |
| 2026-09-20 | [`2026-09-20-account-settings-repairs.md`](plans/2026-09-20-account-settings-repairs.md) | Account Settings Repairs Implementation Plan | spec in [`specs/`](specs/2026-09-20-unified-notifications-and-account-settings-design.md) |
| 2026-09-20 | [`2026-09-20-unified-notifications.md`](plans/2026-09-20-unified-notifications.md) | Unified Notifications and Account Settings Implementation Plan | spec in [`specs/`](specs/2026-09-20-unified-notifications-and-account-settings-design.md) |
| 2026-09-21 | [`2026-09-21-messages-poll-focus-fixes.md`](plans/2026-09-21-messages-poll-focus-fixes.md) | Messages Poll And Focus Fixes Implementation Plan | — |
| 2026-09-23 | [`2026-09-23-image-upload-reliability.md`](plans/2026-09-23-image-upload-reliability.md) | Image Upload Reliability Implementation Plan | — |

**Stage-1 working suite:** [`plans/imladris-admin-account-stage1/`](plans/imladris-admin-account-stage1/) — the Stage 1 admin/account inventory's full working set (F findings · R reconciliation · D deviation ledgers · S synthesis · V verification per surface, plus `README.md`). Cited as the rationale record by ADR 0024 and the slice evidence under `docs/evidence/imladris-admin-account-slice-*/`.
