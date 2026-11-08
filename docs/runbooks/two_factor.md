# Runbook — Two-factor sign-in (TOTP and recovery codes)

## What depends on APP_KEY

- **Authenticator (TOTP) secrets** are stored encrypted with AES-256-GCM under a
  key derived from `APP_KEY` (`SecretBox`). If the trimmed value is all hex and at
  least 64 characters long, the key is its first 64 hex digits as bytes;
  otherwise it is SHA-256 of the trimmed value.
- **Recovery codes** are stored only as HMAC-SHA256 values keyed by the exact
  `APP_KEY` string, untrimmed.

Changing `APP_KEY` therefore makes every existing TOTP secret unreadable and every
existing recovery code unverifiable, unless the old key is still available.
Some changes keep the encryption key but alter the HMAC key: adding surrounding
whitespace or a trailing newline, or, for a hex key, changing letter case or
appending hex digits after the first 64. Those leave secrets readable but still
break recovery codes. Never change `APP_KEY` outside a documented key-rotation
procedure.

## When a secret cannot be opened

If a member's TOTP secret was sealed under a key this deployment does not have:

- **Sign-in:** the authenticator check is skipped and can never satisfy two-factor
  sign-in. The member's recovery codes are still checked as usual: same challenge,
  account and attempt-limit checks, and each code works once. An authenticator
  code is refused with a 422: "Authenticator codes cannot be checked for this
  account right now. Enter one of your recovery codes instead, or contact the
  forum operator."
- **Settings:** a recovery code also disables two-factor authentication
  (Security → Disable), after which the member can enroll again under the
  current key. A pending setup that cannot be opened asks the member to restart
  setup.
- **Signals:** each occurrence logs
  `[RetroBoards] Two-factor secret (enabled|pending enrollment) for user #N cannot be opened with the configured APP_KEY.`,
  and the sign-in or disable audit row (`mfa_recovery_login`,
  `mfa_login_failed`, `mfa_totp_disabled`) carries `"totp_unavailable": true`.

A missing `APP_KEY` is a configuration failure, not an unreadable record. It
still fails as a server error.

## Limits

- Nothing here recovers a secret or recovery code whose key is gone. If the
  previous `APP_KEY` value still exists, restoring it is the only way to make
  those records work again.
- A member whose secret is unreadable and who has no valid recovery codes cannot
  complete two-factor sign-in. There is not yet an operator procedure to reset a
  member's two-factor setup; `user:password` leaves two-factor in place.

## Concurrency check

`tests/concurrency/mfa-recovery-code.php` races two sign-ins for one recovery code
on separate connections, while the account's TOTP secret is unreadable. Run it
against an empty schema that the configured DB user can access:

```bash
DB_MFA_RACE_DATABASE=retroboards_mfa_recovery_race php tests/concurrency/mfa-recovery-code.php
```
