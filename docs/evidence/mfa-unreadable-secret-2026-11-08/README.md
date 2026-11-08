# Two-factor sign-in with an unreadable TOTP secret

**Date:** 2026-11-08 (UTC) · **Base:** `main` at `eeceed78` · **Branch:** `fix/mfa-unreadable-totp-secret`
All keys used here are dummy values (`'1a'×32`, `'2b'×32`, and the browser
harness's `'0'×64`). No real key, account or deployment was touched.

## Defect

`MfaService::verifySecondFactor` decrypted the TOTP secret before checking for a
recovery code. `SecretBox::decrypt` threw a plain `RuntimeException`, and
`AuthController::completeMfa` catches only `HttpException` and
`ValidationException`. So when the secret had been sealed under a key the
deployment no longer has, every second-factor attempt answered 500. That
included a recovery code valid under the active key. `confirmEnrollment` and
`disable` failed the same way. Origin: `../app-key-rotation-review-2026-11-08/`.

## Change

- `SecretBox::decrypt` throws `App\Security\SecretUnavailableException`, a final
  `RuntimeException` subclass, when the authentication tag does not verify. A
  missing `APP_KEY` still throws a plain `RuntimeException`, the configuration
  failure.
- `verifySecondFactor` catches only that exception. The authenticator step is
  skipped and cannot succeed. The recovery code is then evaluated by the
  unchanged atomic `consumeRecoveryCodeHash` (a single conditional `UPDATE … WHERE
  used_at IS NULL`).
- Unchanged: the challenge, account, ban and rate-limit checks, and challenge
  consumption.
- Denials keep the normal 422 path and the `mfa_login_failed` audit row. When
  the secret is unavailable, the message points the member to recovery codes, and
  the audit payload carries `totp_unavailable: true`. An operator line is
  written with `error_log`.
- `disable` evaluates a recovery code the same way. `confirmEnrollment` answers
  422 and asks the member to restart setup, which reseals the pending row under
  the current key.

### Consumers of the exception change

| Caller of `SecretBox::decrypt` | Handling | Behavior change |
|---|---|---|
| `MfaService` (sign-in, disable, confirm enrollment) | now catches `SecretUnavailableException` | 500 → recovery-code fallback or 422 |
| `SecretVault::reveal` ← `OidcProvider::exchange` ← `OAuthController` callback | `catch (Throwable)` → failed-flow page | none |
| `SecretVault::usableSecrets` ← `WebhookDeliveryWorker` | `catch (Throwable)` → counted `skipped` | none |

No source code catches `RuntimeException` by name or matches the
`Unable to decrypt secret.` message, and the message is unchanged. Two places
record `$e::class`: `FirstPartyHookRegistry::logFailure` and `AuthorityGate`'s
`authority.enforce_error` telemetry. Neither runs on a decrypt path. If one ever
did, it would record the more specific class name. Other `SecretVault` users only
store, rotate or revoke secrets.

## Tests

| Run | Result |
|---|---|
| `logs/baseline-red.txt`: new tests against the unfixed code | 13 of 15 red: 9 integration failures from 500s (`baseline-red-server-errors.log`: 9 × `Unable to decrypt secret`) and 4 errors because the exception class did not exist. The expired-challenge and round-trip guards pass on both sides. |
| `logs/green.txt`: same tests with the fix | `OK (15 tests, 178 assertions)`; the server log holds only the operator lines |
| Existing MFA, vault, OIDC, webhook, package and security tests | `OK (306 tests, 2073 assertions)` |

`logs/baseline-run1-harness-error.txt` is an earlier baseline that called
`assertSeeText` with its arguments reversed. Its failures were test-harness
errors, so it was kept only under that name and rerun.

`tests/Integration/Core/AppMfaUnreadableSecretTest.php` covers:

- A valid recovery code signs in. The session belongs to the challenge account,
  the code and the challenge are consumed, and the audit row is flagged.
- The real authenticator code is refused with guidance. Nothing is consumed and
  the same challenge then accepts a recovery code.
- Invalid codes, another account's code and an already-used code are all
  refused. The other account's codes stay unused.
- An expired challenge refuses a valid recovery code without consuming it.
- The per-account attempt limit still bites, and a throttled valid code is not
  consumed.
- Disable with a recovery code works, then the member enrolls again.
- An unreadable pending setup asks for a restart, and the restart succeeds.
- The original key-switch diagnostic, without a keyring, produces no 500s. A
  recovery code issued under the active key works, and re-wrapping the same secret
  restores the authenticator.
- A missing `APP_KEY` is not converted into a denial.

## Concurrency (`concurrency/mfa-recovery-code.json`)

`tests/concurrency/mfa-recovery-code.php` follows the `account-lifecycle.php`
pattern. The parent completes a recovery-code sign-in inside an open transaction.
A separate PHP process runs the competing sign-in through the same service on its
own connection. The parent sees that process blocked on
`UPDATE user_recovery_codes` in `SHOW FULL PROCESSLIST` before committing.

| Case | Parent | Child | Code | Losing challenge |
|---|---|---|---|---|
| Two sign-ins, same code | signed in | denied | used once | not consumed |
| One challenge submitted twice, same code | signed in | denied | used once | — |
| Uncontended control | — | signed in | used once | — |

Fixture rows were removed (`fixture_rows_remaining: 0`). Schema:
`retroboards_mfa_recovery_race`, dropped afterwards.

## Browser (`browser/`, `logs/browser-run.log`)

`tests/browser/mfa-unreadable-secret.spec.ts`, with
`tests/browser/mfa-unreadable-secret-fixture.php` sealing the member's secret under
a key the server never receives. It ran desktop (1280×800) and mobile (390×844),
each with JavaScript off and on: `8 passed`.

The spec asserts:

- The 422 status, the guidance text and `aria-invalid` on the code field, with
  no horizontal overflow.
- No serious or critical axe violations. axe runs in the JavaScript pass because
  it injects a script; the server-rendered markup is the same in both passes.
- A recovery code signs in, and disable succeeds with a recovery code.
- The pending setup restarts with a new secret and then enables.

Captures `01`–`02` are full pages. `03`–`05` are panel captures, because a
full-page capture of the scrolled settings page repaints the sticky top bar
mid-page. `logs/browser-run1-axe-in-no-js.log` is the first run, where axe
failed in the JavaScript-disabled pass, and was kept for that reason.

## Limits (unchanged by this fix)

- Nothing here restores a secret or recovery code whose key is gone. With a
  changed `APP_KEY`, recovery codes hashed under the old key are refused, by the
  normal 422 path. The keyring work restores verification during a planned
  rotation.
- A member with an unreadable secret and no valid recovery code cannot finish
  two-factor sign-in. An operator recovery procedure is a separate change.
- Pre-existing ordering, unchanged (from reading the code; these tests do not
  exercise it): `completeLoginChallenge` consumes a recovery code before
  consuming the challenge, outside one transaction. One challenge submitted twice
  concurrently with two *different* codes can therefore spend the second code
  without signing in. No code ever signs in twice.
