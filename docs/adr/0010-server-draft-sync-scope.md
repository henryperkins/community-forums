# ADR 0010: Server-side draft sync scope

**Date:** 2026-06-30
**Status:** Accepted as a deferral/scope record.

## Context

Phase 3 Gate A completed browser-local drafts and submission resilience. The
remaining server-side draft sync item requires cross-device conflict handling,
privacy retention, quota policy, and offline interoperability. Phase 7 already
owns offline drafts and deferred submissions.

## Decision

- Server-side draft sync remains deferred to Phase 7 unless a later product-owner
  ADR pulls it forward.
- Current Phase 2-4 closeout must not add partial draft sync endpoints, tables,
  or background jobs.
- Existing local draft behavior remains the accepted contract: drafts are scoped
  by browser, user, route/context, and form, and are cleared only after confirmed
  submission/navigation.
- If pulled forward later, the required minimum policy is explicit user-visible
  sync, per-user quota, per-context keys, conflict UI, retention period, export
  inclusion, and no silent overwrite.

## Consequences

- Phase 2-4 completion can close by recording this deferral; no code is required
  for server drafts in this release train.
- Tests should keep proving local drafts work and that no server sync routes
  appear while the feature is not accepted.
