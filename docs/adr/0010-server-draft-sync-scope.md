# ADR 0010: Server-side draft sync scope

**Date:** 2026-06-30
**Status:** Superseded by pull-forward implementation record.

## Context

Phase 3 Gate A completed browser-local drafts and submission resilience. The
remaining server-side draft sync item required cross-device conflict handling,
privacy retention, quota policy, export/delete coverage, and offline
interoperability.

The Phase 2-4 completion train pulled the server-owned part forward as a
deploy-dark slice so acceptance can be gathered without weakening the local
offline draft fallback.

## Decision

- Migration `0064` adds `server_drafts`, keyed by `(user_id, context_key)`,
  with `revision`, `title`, `body`, `metadata`, `updated_at`, and `expires_at`.
- The `server_drafts` flag defaults dark. While dark, server JSON endpoints and
  the server-owned no-JS listing/discard surface are unavailable.
- Retention is 90 days. Quota is 50 active drafts per user. Expired drafts are
  purged opportunistically before reads/lists/saves and by `worker:drafts`.
- JSON load/save/discard endpoints use authenticated user ownership and
  optimistic revisions. Revision mismatch returns `409` with the current server
  draft so the UI can offer keep local, keep server, or save local as the next
  revision.
- `/drafts` remains useful without JavaScript: it lists server drafts and posts
  discard actions when `server_drafts` is enabled; otherwise it keeps the local
  draft guidance.
- Browser `localStorage` remains the offline fallback. When server sync is
  enabled, the enhanced composer loads/saves server drafts in addition to the
  local fallback, and conflict controls let the member keep local, keep server,
  or save local as the next revision.
- Account export includes server drafts. Account purge deletes them.

## Consequences

- Server sync can collect acceptance evidence independently of local draft
  behavior because the flag is deploy-dark.
- A later Phase 7 offline/deferred-submission slice may build richer offline
  queueing on top of this contract, but it should not redefine retention,
  quota, or conflict semantics without another ADR.
- Required evidence before enablement: focused PHPUnit for dark routing,
  conflict handling, no-JS listing/discard, account export, and account purge;
  browser evidence for cross-device conflict resolution; rollback notes that
  disable `server_drafts` before any data repair.
