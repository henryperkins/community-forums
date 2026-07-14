# ADR 0020 - Composer shell follow-ups

**Date:** 2026-07-13
**Status:** Engineering deferral. This record accepts bounded carryover; it does
not claim the work below is shipped.

## Context

The Slack-style composer shell now provides the shared server-rendered markup,
progressive enhancement, context-aware send keys, emoji discovery, visible image
attachment, and guarded Inbox-fragment enhancement described in `COMPOSER.md`
v0.7. Four adjacent behaviors need separate product or engineering decisions and
were intentionally excluded from this slice. Recording them here prevents the
completed shell work from silently implying broader behavior.

## Decision

| Follow-up | Owner | Destination | Rationale | Risk and current control |
|---|---|---|---|---|
| Optimistic send, reconcile, and rollback | Composer engineering | First composer behavior follow-up | Reply and DM reconciliation need a client state model, failure rollback, idempotent acknowledgement handling, and anti-draft-loss acceptance beyond the shell refactor. | Until that follow-up ships, an accepted Inbox send performs a full navigation to the canonical thread and posted-reply anchor; server validation and the existing idempotency guard remain authoritative. |
| Global `r`, `c`, and command-palette keys | Product design and navigation engineering | Separate keyboard/navigation design | Global listeners need a conflict map for browser, assistive-technology, form, and page-level shortcuts rather than being inferred from composer-local keys. | This slice adds no global listeners. Visible navigation, New Thread, Reply, and Search controls remain the supported paths. |
| `↑` edit-last-post | Composer and authorization engineering | Separate edit selection/authorization behavior | Selecting the eligible last post must account for ownership, edit windows, moderation state, deletion, thread locks, and an empty active composer. | No edit-last key is registered. Existing edit affordances and authorization disclosures remain authoritative. |
| Replying-to quote chip | Product design and composer engineering | Composer visual follow-up | A visual wrapper needs lifecycle, removal, accessibility, source/rich parity, and canonical serialization rules. | Study **Quote** Markdown insertion is shipped and tested through the active source and rich adapters; only the visual chip wrapper is deferred. |

## Consequences

- None of these items may be reported as completed by the v0.7 shell evidence.
- Follow-up implementations must preserve the no-JavaScript and source-mode
  baseline and receive their own behavior and browser evidence.
- This ADR does not absorb unrelated Phase 5 carryover work.
