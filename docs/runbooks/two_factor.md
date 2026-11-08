# Runbook — Two-factor sign-in (TOTP and recovery codes)

## What depends on APP_KEY

- **Authenticator (TOTP) secrets** are stored encrypted with AES-256-GCM under a
  key derived from `APP_KEY` (`SecretBox`). If the trimmed value is all hex and at
  least 64 characters long, the key is its first 64 hex digits as bytes;
  otherwise it is SHA-256 of the trimmed value.
- **Recovery codes** are stored only as HMAC-SHA256 values keyed by the exact
  `APP_KEY` string, untrimmed.

Changing `APP_KEY` therefore makes every existing TOTP secret unreadable and every
existing recovery code unverifiable, unless the old key stays in the key ring.
Some changes keep the encryption key but alter the HMAC key: adding surrounding
whitespace or a trailing newline, or, for a hex key, changing letter case or
appending hex digits after the first 64. Those leave secrets readable but still
break recovery codes. Never edit `APP_KEY` in place; rotate it (ADR 0059).

## The key ring (ADR 0059)

`APP_PREVIOUS_KEY_1` to `_3` hold earlier keys, each byte for byte. Every previous
key may decrypt TOTP secrets and vault versions. A previous key may also verify
recovery codes, links and cookies only when its HMAC key id is listed in
`APP_PREVIOUS_KEY_VERIFY_IDS`. `php bin/console key:check` prints each key's
encryption id, HMAC id and permissions, never the key, and exits 1 on a
configuration error. It checks configuration only, not whether stored records
can be read.

Since migration `0087`, each TOTP secret records the encryption key that sealed
it (`secret_key_id`) and each recovery-code batch records its HMAC key
(`hmac_key_id`). Rows from before `0087` have `NULL`: their secret is tried
against every decrypting key, active first, and their codes against every
verifying key. The first legacy code that verifies records its key for the whole
batch. Unused legacy batches keep unknown attribution.

**After an accidental `APP_KEY` change** (including a whitespace, case or
trailing-digit change): put the previous value back as `APP_PREVIOUS_KEY_1`,
byte for byte, run `key:check`, and add its printed HMAC id to
`APP_PREVIOUS_KEY_VERIFY_IDS`. Do not add it to the verify list if the old key
may be compromised: it then still decrypts secrets, but no recovery code, link
or cookie made with it verifies.

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
  previous `APP_KEY` value still exists, configuring it as a previous key is the
  only way to make those records work again.
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
