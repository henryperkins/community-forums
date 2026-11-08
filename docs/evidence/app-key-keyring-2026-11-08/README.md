# APP_KEY key ring (ADR 0059)

**Date:** 2026-11-08 (UTC) · **Branch:** `feat/app-key-keyring`, based on
`fix/mfa-unreadable-totp-secret` (PR #97).

Every key here is a dummy value:

- `'1a'×32` (K1), `'2b'×32` (K2), `'3c'×32` and `'ab'×32`;
- the suite's `'0'×64`;
- the browser run's `'0'×64` active key with K1 as a previous key.

No real key, account or deployment was touched.

## What changed

- **Key ring.** `AppKey` and `AppKeyRing` implement the configuration and
  permission model. `SecretBox` encrypts with the active key and decrypts with
  the attributed key, or trials every key for legacy rows.
- **Migration `0087`.** It adds five nullable key-id columns and one index.
  `NULL` means "written before `0087`", so the key is unknown.
- **Recovery codes.** They are verified under each key permitted to verify. A
  verified legacy code attributes its batch; unused legacy batches stay unknown.
- **Email-change confirmations and the OAuth state cookie** verify under each
  permitted key.
- **Unsubscribe.**
  - Opt-out verifies under any permitted key.
  - Resubscribe needs the active key.
  - A new signed-in route, `POST /settings/notifications/email/resubscribe`,
    undoes only the member's own `unsubscribe` suppression.
- **Mail.** Links are signed at send time, and the signing key is recorded in
  `email_deliveries.unsubscribe_key_id`.
- **`php bin/console key:check`** prints key ids and permissions. It exits 1 on
  any configuration error or a missing `APP_KEY`.
- **Unchanged:** derived identifiers (passkey decoys, the Thread Intelligence
  latch, the safety identifier, the request fingerprint) stay on the active key.
  Their rotation effects are now pinned by tests.

No CSS changed. The two template changes reuse existing classes (`scribe-panel`,
`scribe-panel-head`, `muted`, `btn`).

## Tests

| Log | Result |
|---|---|
| `logs/new-tests.txt`: `AppKeyRingTest`, `SecretBoxTest`, `KeyCheckCommandTest`, `AppKeyRingRotationTest`, `AppMfaUnreadableSecretTest` | `OK (40 tests, 574 assertions)` |
| `logs/derived-identifiers.txt`: the new safety-identifier and latch tests | `OK (2 tests, 8 assertions)` |
| `logs/focused.txt`: the 85 files in `logs/focused-files.txt`, every test touching a changed path | `OK (1011 tests, 11134 assertions)` |
| Full suite on the commit | reported in the PR |

The server-log lines in `logs/*-server-errors.log` are expected:

- the unreadable-secret operator line from PR #97, for the cases that refuse a
  secret;
- one "Ignoring invalid key configuration" line from the compromise case's
  deliberately bad verify list. It reports the pasted raw key by its position
  and never echoes it.

### The rotation matrix (`tests/Integration/Core/AppKeyRingRotationTest.php`)

- **S0 → S1a → planned S1 → S3**:
  - **S0.** It starts with three members: one whose rows have no ids (legacy),
    one with an unused legacy batch, and one with K1 ids.
  - **S1a.** Still writes K1 ids, yet reads a record and verifies a link that
    are both K2.
  - **Planned S1.**
    - The legacy and K1 TOTP secrets open, and legacy and K1 codes sign in.
    - The first verified legacy code attributes its batch to K1, while the
      unused legacy batch stays `NULL`.
    - The vault reveals a legacy version, and rotating writes a K2 version.
    - A K1 email-change link confirms, and a K1 unsubscribe link opts out.
  - **S3, after re-encryption, regeneration and grace expiry.** K2 records keep
    working. Whatever still needs K1 fails closed: the authenticator gets a 422
    with guidance, codes get a 422, and links get a 404, never a 500.
- **Mixed S1a and S1 runtimes.** Each runtime accepts the other's TOTP secrets,
  recovery codes, unsubscribe links and email-change links.
- **Pre-keyring code.** It reads the key ring's K1 writes, ciphertext and
  recovery hashes alike, using its own derivation. Its enrollment restart leaves
  `secret_key_id` untouched, which is harmless under K1. Under K2 it leaves an
  id naming the wrong key, so the key ring refuses the secret. That is the
  reason for the rollback floor.
- **Compromise S1.** The compromised key decrypts the vault and TOTP, but its
  recovery codes and links never verify. A planned previous key still verifies.
  A raw key and an unknown id in the verify list are ignored.
- **Unsubscribe.**
  - A previous-key link opts out without a Re-subscribe form, and
    `POST /resubscribe` with it answers 404.
  - The signed-in route needs a session and restores only the member's own
    `unsubscribe`; a bounce stays.
  - An active-key link still resubscribes.
- **OAuth.**
  - A K1-signed state cookie completes a Google sign-in under planned S1.
  - Under the compromise configuration it is refused before any token exchange,
    with the "expired or was invalid" message.
- **Send-time attribution.**
  - A message sent under K1 records K1.
  - A failed attempt records nothing; its retry under K2 records K2, and its
    link survives K1's retirement while the K1 message's link does not.
  - A digest records the active key.
- **Historical spellings** (uppercase hex, hex past the first 64, trailing
  newline, surrounding spaces). They share K's encryption id but not its HMAC
  id.
  - Kept as a verifying previous key, codes and links work.
  - Without that entry, secrets still open but codes and links are refused.
- **Derived identifiers.**
  - **Passkeys.** Real credential ids stay while all decoys change, so a
    before-and-after comparison separates them. This is not harmless; see
    ADR 0059 §5.
  - **Thread Intelligence.** The latch reads clear under a new key. With a
    persisting failure, the next worker run makes exactly one provider request,
    latches again and makes no more (`ThreadIntelligenceWorkerTest`).
  - **Safety identifier.** It changes with the key.

The migration case asserts that a second `up()` issues no DDL: the test
transaction stays open, and another connection does not see the uncommitted
`setUp()` admin. An earlier draft also called `Migrator`, whose ledger
`CREATE TABLE IF NOT EXISTS` implicitly committed that admin. The committed row
then failed `AnnouncementServiceTest`'s active-member count in a focused run.
That call was removed, and the leak no longer reproduces.

## Browser (`browser/`, `logs/browser-run.log`)

`tests/browser/app-key-keyring.spec.ts` ran with
`tests/browser/app-key-keyring-fixture.php`, `APP_PREVIOUS_KEY_1` = K1 and
`APP_PREVIOUS_KEY_VERIFY_IDS=24bf049193f1639b` (K1's HMAC id), against
`retroboards_e2e_app_key_keyring`. Desktop (1280×800) and mobile (390×844), each
with JavaScript off and on: `8 passed`, 20 captures.

- A previous-key link opts out (200). There is no Re-subscribe form; "Sign in"
  links to notification settings.
- After sign-in, the "Notification emails are off" panel turns emails back on,
  with a flash and no panel.
- An active-key link opts out and offers Re-subscribe, which works.
- Each step checks for no horizontal overflow, and axe finds no serious or
  critical issue (JavaScript pass).

Capture `03` is the main pane: a full-page capture of the settings shell clips
its sticky rail.

## Reproduce

```bash
export APP_KEY=0000000000000000000000000000000000000000000000000000000000000000 MAIL_DRIVER=array DB_TEST_DATABASE=<private test DB>
vendor/bin/phpunit tests/Unit/Security tests/Integration/Core/AppKeyRingRotationTest.php

K1=$(printf '1a%.0s' $(seq 32))
APP_ENV=test DB_DATABASE=retroboards_e2e_app_key_keyring \
  RATELIMIT_PATH=$PWD/storage/ratelimit-e2e-app-key-keyring \
  PACKAGES_STORAGE_PATH=$PWD/storage/packages-e2e-app-key-keyring bash tests/browser/prepare.sh
(cd tests/browser && APP_ENV=test DB_DATABASE=retroboards_e2e_app_key_keyring \
  RATELIMIT_PATH=$PWD/../../storage/ratelimit-e2e-app-key-keyring \
  PACKAGES_STORAGE_PATH=$PWD/../../storage/packages-e2e-app-key-keyring \
  APP_PREVIOUS_KEY_1=$K1 APP_PREVIOUS_KEY_VERIFY_IDS=24bf049193f1639b E2E_PORT=8048 \
  npx playwright test app-key-keyring.spec.ts)
```

## Limits

- This change does not include `key:status`, `key:reencrypt`, the recovery-code
  notices and retirement command, the compromise fresh-link route, the
  entrypoint gate, Cloud Run configuration, or the rotation runbook and
  rehearsal. Those are the next change, so **no real rotation should start on
  this change alone**.
- Operator two-factor recovery (`user:mfa-reset`, re-enrollment) is a separate
  change.
- Nothing restores material whose key is gone.
- `SecretVault::usableSecrets()` still throws when any in-grace retired version
  needs a key that is no longer configured. The retirement criteria (ADR 0059
  §8) count those versions, and re-encryption will cover them.
