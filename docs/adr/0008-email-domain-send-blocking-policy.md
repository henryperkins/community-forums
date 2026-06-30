# ADR 0008: Email domain verification and send-blocking policy

**Date:** 2026-06-30
**Status:** Accepted as the implementation gate for email domain send-blocking.

## Context

Phase 2 email operations now have an admin dashboard, queue visibility, test
send, suppression management, and CSV export. ADR 0005 explicitly left
SPF/DKIM domain-status verification and a sending-blocked gate deferred. This
ADR fixes the minimum policy for completing that feature.

## Decision

- `Mailer::isConfigured()` remains the local readiness check for development and
  tests: a non-empty From address is enough to queue and send when production
  send-blocking is disabled.
- Production send-blocking is opt-in through config/settings. When enabled, new
  outbound email sends are blocked unless the From domain has passing SPF and
  DKIM status recorded by the verifier.
- SPF passes when the From domain has at least one TXT record containing an
  `v=spf1` policy. DKIM passes when the configured selector has a TXT record at
  `<selector>._domainkey.<domain>` containing `v=DKIM1`.
- Verification results are cached with checked-at timestamps and visible in
  `/admin/email`. Operators can refresh status manually and via a console
  command.
- Blocking never deletes queued mail. It prevents new send attempts and causes
  workers to leave rows queued with a visible blocked reason until status passes.
- Test-send must report domain-blocked separately from transport failures.

## Consequences

- Code must add a small domain-status persistence model and a verifier service.
- Tests must avoid live DNS by injecting deterministic lookup results.
- Production deployments can require SPF/DKIM without breaking local development.
