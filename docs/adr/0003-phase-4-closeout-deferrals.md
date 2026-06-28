# ADR 0003: Phase 4 closeout deferrals

**Date:** 2026-06-28

**Status:** engineering closeout record; product-owner acceptance can supersede
destinations but must not mark these items as shipped without implementation
evidence.

## Context

`PHASE_4_PLAN.md` allows Phase 4 to close only when accepted items have evidence
and omitted items have an explicit destination. The codebase now implements the
Gate A advanced-community slice: topic workflow, group-DM boundaries/reporting,
advanced Markdown tables/tasks/rules, tags and expanded follows/feeds, the
reputation ledger and windowed leaderboards, summary/related/wiki memory
rollback, and feature-flag rollback gates.

Several Phase 4 plan items remain intentionally unshipped. Some have additive
schema from migration `0048_phase4_gate_a.php`, but schema alone is not accepted
behavior.

## Decision

The following items are deferred out of Phase 4 and must be re-accepted in their
destination phase before any UI, route, worker, or operator promise exposes them.

| Item | Owner | Destination | Rationale | Risk / control |
|---|---|---|---|---|
| Custom badge rule engine, preview, backfill, revoke UI | Product + Engineering | Phase 5 Milestone 0 carryover ledger | The `badge_rules` and `badge_award_history` tables exist, but rule activation needs population preview, repeatability policy, notification behavior, and rollback UX. | Keep `badge_rules` deploy-dark and expose no rule-management UI. Fixed badges remain the accepted behavior. |
| Board/thread/post reference cards and persisted `content_references` resolution | Product + Engineering | Phase 5 content-polish carryover, or Phase 6 search/projection work if automated | The metadata table exists, but parser/rendering/read-gate fallback work is not built. | Plain Markdown links remain canonical; inaccessible references are not enriched. |
| Moderator split/merge services and redirects | Product + Engineering + Moderation | Phase 5 moderator-operations carryover | Operation/redirect tables exist, but safe post moves require locks, counter/read-state repair, redirect behavior, notification/reputation invariants, and rollback rehearsal. | Expose no split/merge routes; moderators retain existing pin/lock/delete/restore tools. |
| Link previews, embeds, expanded non-image attachments, polls, custom emoji, slash-command/GIF insertion | Product + Security + Engineering | Phase 5/6 by separate scoped adoption record | These require SSRF controls, scanners/quarantine, vote concurrency, media moderation, provider privacy, and accessibility evidence beyond Gate A. | No preview fetchers, poll tables, emoji upload UI, or provider calls are enabled. |
| Automated since-last-read context and scheduled related-topic refresh | Product + Engineering | Phase 6/7 knowledge automation decision | Gate A ships manual summaries and curated related topics only. Automated context needs provenance, review metrics, source range policy, and disable/replay controls. | Computed context remains absent; canonical summaries are always human-published. |
| Avatar uploads, safe signatures, personal board groups/folders, saved feed filters/digest composition | Product + Engineering | Phase 5 profile/organization carryover unless Phase 7 offline/import work absorbs it | These are profile and organization polish, not required for the accepted Gate A community workflow. | Existing monograms/OAuth avatar cache and current notification digest behavior remain the only exposed surfaces. |
| Production browser, accessibility, load, SEO, backup/rollback evidence beyond local automated coverage | Engineering + Release | Release-operations evidence before broad deployment | The local repo now has full PHPUnit evidence and targeted regressions, but no new production-like Playwright/a11y/load artifacts for Phase 4. | Do not call broad production rollout complete until those artifacts are attached to release operations. |

## Consequences

- Phase 5 entry must treat the rows above as carryovers, not accepted Phase 4
  foundations.
- Migration `0048` remains valid additive schema, but inert schema-only tables
  are not evidence of shipped behavior.
- Phase 4 local closeout evidence is scoped to implemented Gate A behavior and
  full PHPUnit regression coverage.
