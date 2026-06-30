# ADR 0011: Public plugin runtime scope

**Date:** 2026-06-30
**Status:** Superseded by Phase 5 Gate B pull-forward implementation record.

## Context

Phase 5 trusted prerequisites now exist behind dark flags: service secrets,
read-only API tokens, webhook delivery, and first-party hook producers. The open
item was the public/untrusted server-extension runtime and sandbox. That is a
security boundary, not a normal feature toggle.

The Phase 2-4 completion train pulled forward only the deploy-dark runtime
shape, admin inspection surface, and async worker seam needed to gather
acceptance evidence. It still does not make untrusted code part of login,
authorization, reading, posting, moderation, recovery, or rendering.

## Decision

- Migration `0065` adds runtime tables for extension handlers, queued async
  jobs, run history, and per-install key/value storage.
- The `server_extensions` flag defaults dark. While dark, `/admin/extensions`
  is unavailable and `worker:extensions` skips without claiming jobs.
- Public extension manifests use `server_extension.v1` and must declare an
  entrypoint, events/jobs, permissions, resource limits, and storage quota.
  Outbound hosts are denied by default; entrypoints may not escape the package
  root.
- No untrusted code runs during web requests. Entrypoints are async event/job
  workers only and communicate over stdin/stdout JSON RPC. Direct DB, session,
  environment, secret, and core-file access is denied by policy; privileged work
  must go through a capability broker.
- Bubblewrap is the primary local isolation profile. Unsupported hosts fail
  closed: the admin page reports the failed probe and the worker leaves jobs
  queued instead of running them.
- Runtime execution must stay quota-limited for CPU, memory, wall time, output,
  disk, and package storage. The first adapter implementation deliberately
  records a fail-closed placeholder until package bytes and broker RPC transport
  have explicit host approval.
- Admins can inspect sandbox probe status, handlers, run history, and the global
  kill switch boundary. Enable/disable/quarantine and permission grant workflows
  remain Gate B follow-up surfaces before external enablement.

## Consequences

- The runtime is an evidence harness, not a public extension marketplace.
  Product, security/privacy, and accessibility sign-off remain final enablement
  gates.
- Trusted hooks, webhooks, and API tokens still do not count as public extension
  runtime acceptance.
- Required evidence before enablement: manifest validation, broker
  authorization, sandbox probe/fail-closed behavior, worker run/quarantine
  behavior, adversarial package tests, browser evidence for admin runtime
  surfaces, and rollback notes that disable `server_extensions`.
