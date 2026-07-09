# Superpowers design archive

This directory is the **design-decision archive** for RetroBoards: the brainstorming design specs and implementation plans behind shipped work. It is historical — the authoritative product/technical source of truth remains the spec chain (`DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > the surface specs) and the phase plans/status. ADRs and `PHASE_5_STATUS.md` cite entries here as the rationale record.

**Consolidated 2026-07-09:** the former `plans/` + `specs/` split was collapsed — each design spec was merged into its paired implementation plan (the doc now carries an *Archived design record* note and holds design + plan + review in one file), the five WYSIWYG docs merged into one, and the empty `specs/` directory was removed. Archived entries retain their original internal references (to since-moved status docs, etc.) as historical records; they are not repointed.

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
