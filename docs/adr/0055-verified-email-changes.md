# ADR 0055 - Verified email changes

**Date:** 2026-11-01
**Status:** Accepted; default-on after owner-approved graduation on 2026-11-01.

## Context

USER §3.2 and ADR 0014 require verified contact changes without losing recovery
for password, passkey or OAuth-only members. The contact used to be immutable.
Making it mutable also changes the safety of overlapping password-reset and
registration-verification operations.

## Decision

Expose a separate form at `/settings/account/email` when `email_change` is
enabled. Fresh proof is a current password, a session-bound passkey assertion,
or a new round trip through an already-connected provider. Provider proof must
match its exact subject, owner and configured provider identity. It never links
or provisions an account.

Keep the strict self-only form policy and Lax member cookies. The provider start
POST returns a native continuation link. The callback returns a private native
POST handoff before exchanging its code, restoring the member cookie after
Apple's cross-site POST and ensuring a GET does not issue a change token. The
existing signed OAuth state binds owner, session, candidate, expiry and returned
code; no additional CSRF exemption is introduced.

Reuse `verifications` with type `email_change` and a random 32-byte token whose
stored HMAC binds the owner, original contact and password snapshot. The new
address stays inactive until the signed-in owner submits the confirmation form.
Under an account lock, recheck state, expiry, single use and address uniqueness,
then switch and verify the new address, retire recovery/verification links,
revoke other sessions, suppress queued ordinary mail to the old contact, enqueue
the old-address notice, and write an audit event without addresses or tokens.

Password reset and registration verification use the same account-first lock
order and recheck the current contact/token inside their transactions. New
recovery/verification rows record the issuance contact in the existing nullable
`new_email` column; legacy NULL rows remain valid until normal invalidation or
expiry. No schema migration is required.

Use the existing `system` email outbox with an idempotency key per completed
verification. The security notice bypasses paused notification preferences and
the `email` availability switch, while preserving recipient eligibility, address
suppression, domain readiness and transport retry rules. Verification and notice
delivery are separate from proof of a recipient receiving the message.

Default limits are five requests/hour/account, three/hour/destination across
accounts and IPs, and ten confirmation/cancellation attempts per 15 minutes per
account. Store only a normalized destination hash in the shared rate store. The
token lifetime defaults to one hour, bounded to five minutes through one day.
Provider setup has its own ten-per-15-minutes account limit; the ordinary request
and destination budgets are charged at the verified return before mail issuance,
including when an owner retains and reuses a signed start intent with fresh codes.

## Consequences and boundaries

- `email_change` defaults on and has a guarded operator enable/disable control.
  Existing explicit off overrides remain effective.
  Rolling it back hides entry points and rejects routes/services, retaining
  completed changes and original pending-link expiries. Queued security notices
  continue to use the existing worker.
- New-inbox verification sends run after releasing the account lock. Failure
  retires only the failed row, preserving a newer replacement and the current
  contact. Old-address notices are durably queued with the address transition.
- Suppression affects queued work and later eligibility checks; a transport send
  already in progress cannot be recalled. SMTP retry deduplication is not a
  guarantee of exactly-once delivery.
- A pending row can remain visible until cancellation/expiry after a credential
  change invalidates its token binding. Actual preview/confirmation rejects it.
- The broader notification controls in ADR 0014, username changes in ADR 0035
  and deferred branding surfaces in ADR 0054 remain separate work.

Implementation evidence: [2026-11-01 qualification](../evidence/email-change-2026-11-01/README.md).
Operations: [email changes](../runbooks/email_changes.md).

## 2026-11-01 default-on graduation

The owner approved moving the verified subsystem from its initial default-off
posture to default-on after implementation and deployment qualification. Fresh
installations and existing sites without an `email_change` override now expose
the account entry and flow. Explicit off overrides still hide the entry and
reject the routes/service; removing that override resumes the code default.
There is no migration, bulk settings rewrite, change to authentication or token
semantics, or change to sending-domain/address-suppression rules.

The inventory now has 52 default-on and six default-dark flags. The
[graduation evidence](../evidence/email-change-default-on-2026-11-01/README.md)
covers no-override availability, explicit-off rollback/restoration, focused
browser checks and the ordinary release contract.
