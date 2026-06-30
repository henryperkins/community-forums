# ADR 0006: Account lifecycle, export, and deletion policy

**Date:** 2026-06-30
**Status:** Accepted as the implementation gate for account lifecycle work.

## Context

Phase 2/3/4 status records still carry self-service export, deactivation,
reactivation, and deletion as open work. `ADMIN.md` requires export/delete
capabilities and says deletion should soft-delete immediately, purge after a
delay, and default to anonymising content while purging PII. This ADR fixes the
policy so implementation can proceed without inventing privacy behavior in code.

## Decision

- Self-service export is available to active signed-in users and admins acting
  on a user record. It produces a downloadable JSON archive of account profile,
  preferences, sessions metadata, subscriptions, notifications, reports filed,
  visible posts, DMs where the requester is a participant, and audit rows where
  the user is the actor or target.
- Deactivation is reversible and user-initiated. A deactivated user cannot write,
  does not appear in presence/leaderboards/follow suggestions, and may reactivate
  by signing in and confirming reactivation.
- Deletion request starts a 30-day grace period. During the grace period the
  account is write-blocked, sessions are revoked except the current confirmation
  flow, and the request can be cancelled by the user or an admin.
- Purge anonymises authored public/community content to a "Deleted user"
  presentation while preserving thread integrity, accepted-answer state, and
  moderation history. PII, OAuth identities, sessions, recovery data, profile
  fields, avatar/signature assets, notifications, preferences, subscriptions,
  follows, blocks, and email suppressions are removed or detached.
- The final active admin/owner cannot deactivate or request deletion until
  another active admin with a recovery path exists.
- Every export, deactivation, reactivation, deletion request, cancellation, and
  purge writes a `moderation_log` row. Self-service rows use the user as actor;
  scheduled purges use a system actor.

## Consequences

- Code must add a durable lifecycle/export table rather than overloading only
  `users.status`.
- Delete is not hard-delete-on-click. It is a scheduled purge after grace.
- Public post bodies are preserved unless a later legal policy overrides this
  ADR; PII is purged.
- Tests must prove final-admin protection, reversible deactivation, grace-period
  cancellation, anonymized content display, PII purge, and export authorization.
