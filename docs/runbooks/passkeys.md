# Runbook - Passkeys (WebAuthn, P5-11)

## What the flag gates

`passkeys` (default **OFF**) gates: the Passkeys panel on `/settings/security`,
the JSON ceremony endpoints (`/settings/security/passkeys/*`,
`/login/passkey/*`), the login-page "Sign in with a passkey" affordance, and
`/assets/passkeys.js`. Password, OAuth, TOTP, and recovery sign-in are
independent of this flag (decision #31).

## Roll back (disable)

Merge `"passkeys": false` into the `features` settings JSON; do not clobber
other keys. Ceremonies stop with 404s. Credential rows are preserved for later
re-enablement (PHASE_5_PLAN section 13.2). No schema action is needed. Sessions
created via passkey sign-in remain valid ordinary sessions.

## Re-enable

Merge `"passkeys": true`. Existing credentials work again immediately because
they are bound to the RP ID, not to the flag.

## Staged rollout (section 13.1 step 9 - staff pilot)

1. Enable on a staging copy; run `tests/browser/passkeys.spec.ts` against it.
2. Enable in production; announce to owners/staff only; watch `moderation_log`
   actions `passkey_registered`/`passkey_login` and the error rate.
3. Announce to privileged users, then all members. Do not enable any
   privileged-MFA enforcement. That is Gate B (A7 stays off).

## Lost authenticator / account recovery

Passkeys augment, never replace (D7): the member signs in with password, or
password plus TOTP/recovery code, removes the lost passkey on
`/settings/security`, and enrolls a new one. Operators never need to touch the
database. If a member lost every factor, the standard password-reset path
applies. Removal of the last usable sign-in method is blocked server-side; the
final owner additionally sits behind `LastOwnerGuard`, so a stranded-owner state
cannot be reached via this surface.

## Counter-anomaly review policy (decision #30)

A non-increasing `signCount` writes `moderation_log` action
`passkey_counter_anomaly` and telemetry `passkey.counter_anomaly`, and the
sign-in still succeeds. Synced passkeys, such as iCloud and Google passkeys,
legitimately report zero or non-monotonic counters. Review the account's recent
sessions and audit rows. If compromise is suspected, advise the member to remove
the credential and rotate their password. Never auto-revoke on the counter
alone.

## RP ID / domain changes

RP ID equals the `APP_URL` host by default, or `WEBAUTHN_RP_ID` when set; the
override must be the host or a parent domain. Changing the registrable domain
invalidates every passkey. Follow `docs/phase5/canonical-origin-and-rp-id.md`
section 5: pre-announce, freeze enrollment, cut over, and have members
re-enroll via fallback sign-in. A subdomain-only move keeps passkeys working
only if `WEBAUTHN_RP_ID` was set to the registrable domain before enrollment
began. Production requires HTTPS: ceremonies hard-refuse a non-localhost
`http://` `APP_URL`.

## Monitoring & known limits

- Audit actions: `passkey_registered`, `passkey_renamed`, `passkey_revoked`,
  `passkey_login`, `passkey_counter_anomaly` (`moderation_log`, target_type
  `user`).
- Rate limits: `passkey_challenge` 30/15 min per email, `passkey_login` 10/15
  min per email subject, cleared on successful passkey login, and management via
  `mfa_settings` 10/15 min.
- Accounts with TOTP enrolled require user-verified, screen-lock assertions.
- Usernameless/discoverable sign-in and privileged-MFA enforcement are Gate B.
- Inc 8 handoff: before disabling an OAuth provider, list sole-method accounts
  via `OAuthIdentityRepository::soleMethodAccounts()`; UI arrives with provider
  registry.

## Acceptance evidence

See `docs/evidence/phase5/passkeys.md` for tests, browser PNGs, and the D11
budget row.
