# Verified email change design

Implement the USER section 3.2 and ADR 0014 carryover under the owner's instruction
to proceed. Keep the existing contact/login address active until its signed-in
owner confirms a new address. Preserve password, OAuth and passkey recovery.

## Approach

Implementation choice: reuse the verification table, ReauthGate/PasskeyService,
signed OAuth state and durable system-mail outbox. A separate reauthentication
subsystem or additional token table would duplicate existing infrastructure.
Requiring every OAuth member to create a password would exclude an existing
account type; use an already-linked provider's fresh, session-bound round trip.

## Behavior and security

- A new `email_change` flag defaults false, gates every new route/service and
  account entry point, and has an operator runbook. Enabling it is separate from
  its default posture; notification preferences and other carryovers stay intact.
- The dedicated account email page accepts a new email and a current password or
  existing passkey assertion. A configured, already-linked OAuth provider is an
  alternative that also works without JavaScript. Never provision or link an
  identity during this step; compare provider subject and owner exactly.
- OAuth start is CSRF-protected POST. Signed state contains the target address,
  actor id and current-session binding alongside existing PKCE/state/nonce. The
  callback rejects a changed/missing session, different actor or unlinked subject.
  Existing OAuth login/link flows retain their behavior and callback exemption.
  The start POST renders a native external continuation link under the strict
  self-only form policy. Every provider return renders a private same-origin
  POST handoff before exchange/issuance; this restores the Lax member cookie
  after Apple form_post and keeps GET callbacks free of email-change writes.
  The returned provider code is bound into the signed cookie, not exposed in
  the handoff HTML or accepted from an independently edited form field.
- Only a random 32-byte token is mailed. Store a SHA-256 HMAC bound to actor id,
  original address and credential snapshot, with a one-hour default expiry
  (configurable within 300–86400 seconds). Only one outstanding change per owner.
  Replacement/cancel retires earlier links; address/password changes invalidate
  old links through the token binding. Never put raw tokens in audit entries,
  delivery payloads or application diagnostic messages.
- GET confirmation is read-only and private/no-store/no-referrer. POST plus CSRF
  requires the same signed-in owner, rechecks expiry, account state and uniqueness
  under the account lock, consumes once and switches atomically. No GET writes.
- The transition verifies the new address, retires old recovery/verification links,
  suppresses pending ordinary mail addressed to the old contact, revokes other
  sessions, keeps the current valid session, and appends an audit
  event without raw tokens or addresses. A generic duplicate-address error avoids
  exposing another member's identity; DB uniqueness remains authoritative.
  Recovery and registration issuance/completion share the same account-first
  lock order and recheck the contact and current token under that lock. Store
  issuance contacts in the existing nullable token-address field; preserve
  legacy NULL-address links until normal invalidation or expiry.
- Queue the old-address notice atomically in the existing durable `system` outbox
  with a unique verification-based idempotency key. Security notices bypass the
  member pause-all preference/global notification email switch, while respecting
  address suppression and domain/transport blocking. No suppression row is cleared.
- Unconfigured/blocked mail and verification-send failures produce an actionable
  error, preserve the typed address, retire only the failed issued token, and
  leave the old address active. Confirmation notice retries use the existing worker.
- Named policies: request 5/hour/account, destination 3/hour/hashed subject shared
  across accounts and IPs,
  confirm 10/15 minutes/account. Provider setup has a separate 10/15 minutes
  budget; actual issuance limits are charged at the verified callback before
  issue, so repeated fresh codes using a retained intent do not bypass limits.
  Account state beats role on every mutation.

## UI and verification

Use existing settings panels, fields, errors and native forms; introduce no new
visual tokens or layout. Keep profile saving separate from changing email. Show
pending address/expiry and cancellation. Passwords and assertions never re-render.
Passkey enhancement reuses the external script; password and provider forms work
without JavaScript. Token links never commit changes on navigation or mail scans.

Prove feature-dark and rollback behavior, ownership/CSRF/write gates, current
password/passkey/provider authentication, mismatched provider/session/target,
expiry/reuse/replacement, address collisions, stale credentials, session revocation,
old-address outbox retries and mail failure. Browser evidence covers Chromium and
WebKit at desktop/mobile with JavaScript enabled/disabled. Final PHP suite and
affected asset/Worker checks follow ADR 0051; publish once when qualified and keep
post-deploy receipts outside proof-only commits. Physical-device and mailbox
receipt claims require their own observation.
