# ADR 0003: Phase 4 closeout deferrals

**Date:** 2026-06-28

**Status:** engineering closeout record; product-owner acceptance can supersede
destinations but must not mark these items as shipped without implementation
evidence.

**2026-06-30 reconciliation:** later carryover branches implement several
deferrals behind dark flags and record evidence in
`docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`. This ADR remains
the historical Phase 4 closeout deferral record; remaining rows below still need
explicit acceptance evidence before broad rollout.

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

The following items were deferred out of Phase 4 and must be re-accepted with
destination-slice evidence before broad rollout or operator promises depend on
them.

| Item | Owner | Destination | Rationale | Risk / control |
|---|---|---|---|---|
| Custom badge rule engine, preview, backfill, revoke UI | Product + Engineering | Phase 5 Milestone 0 carryover ledger | **Implemented in carryover branch:** preview, enable/disable, backfill, revoke, and history exist for the constrained rule vocabulary. | Keep `badge_rules` deploy-dark until operator browser evidence and rollback rehearsal are attached. |
| Board/thread/post/DM/summary reference cards and persisted `content_references` resolution | Product + Engineering | Phase 5 content-polish carryover, or Phase 6 search/projection work if automated | **Implemented in carryover branch:** post, DM-message, and summary references are captured and rendered through read-gated cards. | Keep `content_references` deploy-dark until browser/no-JS and inaccessible-target evidence are attached. |
| Moderator split/merge services and redirects | Product + Engineering + Moderation | Phase 5 moderator-operations carryover | **Implemented in carryover branch:** split/merge routes, redirects, audit, and touched-counter repair exist behind `split_merge`. | Keep `split_merge` deploy-dark until browser evidence, larger repair rehearsal, and moderator runbook evidence are attached. |
| Link previews, embeds, expanded non-image attachments, polls, custom emoji, slash-command/GIF insertion | Product + Security + Engineering | Phase 5/6 by separate scoped adoption record | **Partially implemented in carryover branch:** link previews, expanded files, polls, custom emoji, and slash/GIPHY insertion exist behind dark flags. | Keep flags dark until browser/a11y/crawler/load/privacy/runbook evidence is attached. **Update 2026-06-30: `polls` graduated to default-on once its browser (`25-poll-voted`), `.poll-panel` a11y, and runbook evidence landed (see `docs/runbooks/polls.md` + the deploy-dark inventory); the remaining flags in this row stay dark.** |
| Automated since-last-read context and scheduled related-topic refresh | Product + Engineering | Phase 6/7 knowledge automation decision | **Implemented in carryover branch:** since-last-read context uses local read/post state, and `worker:related-topics` creates deterministic tag-related public-thread links. | Keep `automated_context` dark until browser/no-JS, worker-smoke, replay/disable, and stale-link policy evidence is attached. |
| Avatar uploads, safe signatures, personal board groups/folders, saved feed filters/digest composition | Product + Engineering | Phase 5 profile/organization carryover unless Phase 7 offline/import work absorbs it | **Partially implemented in carryover branch:** avatar upload/removal, signature height cap, moderator signature removal, board folders, saved feed filters, account lifecycle/export/delete, bookmark folders, and bounded custom profile fields exist. Digest composition remains a policy/product follow-up. | Keep shipped portions dark until browser/a11y/moderation runbook evidence is attached. |
| Production browser, accessibility, load, SEO, backup/rollback evidence beyond local automated coverage | Engineering + Release | Release-operations evidence before broad deployment | The local repo now has full PHPUnit evidence and targeted regressions, but no new production-like Playwright/a11y/load artifacts for Phase 4. | Do not call broad production rollout complete until those artifacts are attached to release operations. |

## Consequences

- Phase 5 entry must treat the rows above as carryovers, not accepted Phase 4
  foundations.
- Migration `0048` remains valid additive schema, but inert schema-only tables
  are not evidence of shipped behavior.
- Phase 4 local closeout evidence is scoped to implemented Gate A behavior and
  full PHPUnit regression coverage.
