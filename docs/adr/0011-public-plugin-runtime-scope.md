# ADR 0011: Public plugin runtime scope

**Date:** 2026-06-30
**Status:** Accepted as a deferral/scope record.

## Context

Phase 5 trusted prerequisites now exist behind dark flags: service secrets,
read-only API tokens, webhook delivery, and first-party hook producers. The open
item is the public/untrusted server-extension runtime and sandbox. That is a
security boundary, not a normal feature toggle.

## Decision

- No public/untrusted PHP or server-extension runtime ships as part of Phase 2-4
  completion.
- `server_extensions` remains dark and unavailable except for schema/flag
  compatibility checks.
- Gate A package work may continue only for declarative themes, remote apps, and
  reviewed first-party/trusted integrations that do not execute untrusted server
  code in the web request or worker loop.
- Gate B server extensions require a host isolation profile, capability broker,
  resource limits, storage quota, denial/quarantine behavior, adversarial tests,
  and product-owner acceptance before any public package can run code.

## Consequences

- Phase 3/4 closeout must not count trusted hooks/webhooks/API tokens as public
  plugin runtime acceptance.
- Tests should keep public server-extension routes absent or 404 while dark.
- Future implementation must happen under Phase 5 Gate B, not hidden in Phase
  2-4 completion.
