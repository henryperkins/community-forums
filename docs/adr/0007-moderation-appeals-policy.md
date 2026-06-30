# ADR 0007: Moderation appeals policy

**Date:** 2026-06-30
**Status:** Accepted as the implementation gate for appeals.

## Context

Appeals remain open across the Phase 3 and Phase 4 closeout ledgers. The existing
moderation model already has scoped reports, bans, warnings, user notes, post
deletion/restoration, and immutable audit rows. Appeals need a narrow policy so
they do not rewrite history or create an unbounded support inbox.

## Decision

- Eligible appeal targets are warnings, site or board suspensions/bans, removed
  posts, removed signatures, and moderation actions explicitly marked
  appealable by their service.
- Users may open one active appeal per target within 30 days of the action. A
  resolved or dismissed appeal stays immutable; a second appeal requires staff to
  reopen it.
- Appellants see only their own appeal and the original public/user-facing action
  summary. Staff see the full target snapshot according to their board/site
  scope.
- Admins can resolve any appeal. Board moderators can resolve appeals for
  board-scoped actions in their boards, except site bans, admin actions, and
  final-account decisions.
- Resolution outcomes are `upheld`, `modified`, or `reversed`. Reversal/restoral
  must run through the same service that owns the original action so counters,
  read gates, notifications, and audit stay consistent.
- Every appeal state change writes a `moderation_log` row and appends an appeal
  event record. Appeal records are never physically deleted by ordinary cleanup.

## Consequences

- Code must add appeal tables and an appeal service, not just links in existing
  moderation templates.
- Appeal links must stay hidden until the feature flag and policy-backed service
  are available.
- Tests must cover duplicate prevention, 30-day eligibility, board-scope
  enforcement, admin-only site-ban handling, notification delivery, and immutable
  audit/event history.
