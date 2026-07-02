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
- The `server_drafts` flag originally shipped dark for acceptance. It graduated
  to default-on on 2026-07-02; when an operator disables it through the
  `features` override, server JSON endpoints and the server-owned no-JS
  listing/discard surface are unavailable.
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

- The initial deploy-dark slice let server sync collect acceptance evidence
  independently of local draft behavior; after graduation, the same flag remains
  the non-destructive rollback control.
- A later Phase 7 offline/deferred-submission slice may build richer offline
  queueing on top of this contract, but it should not redefine retention,
  quota, or conflict semantics without another ADR.
- Graduation evidence completed on 2026-07-02: focused PHPUnit for rollback
  routing, conflict handling, no-JS listing/discard, account export, and account
  purge; browser evidence for cross-device conflict resolution; rollback notes
  that disable `server_drafts` before any data repair.

## Status update — graduated 2026-07-02

The required-evidence list above is complete, so the `server_drafts` flag
graduated out of deploy-dark on 2026-07-02 (default-ON, reversible via the
`features` override). The dark-routing PHPUnit was rewritten to the
available-by-default + operator-rollback pattern
(`AppServerDraftsTest::test_server_draft_endpoints_are_available_by_default_and_can_be_disabled`),
the cross-device conflict browser capture (`28-server-draft-conflict`) moved into
the standard `npm run evidence` run, a `.composer-draft-sync` conflict-panel axe
pass was added, and the rollback/operating notes now live in the operator
runbook `docs/runbooks/server_drafts.md`. Retention, quota, and conflict
semantics are unchanged from the Decision above.
