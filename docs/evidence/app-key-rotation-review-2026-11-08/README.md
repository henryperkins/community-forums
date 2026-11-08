# APP_KEY rotation review: consumer inventory and dummy-key diagnostics

**Date:** 2026-11-08 (UTC) · **Revision:** `eeceed780aa9ec92e2a85e9554020a6c6d85d503`
**Status:** review evidence only. No product code changed, no real key was read,
used or rotated. All keys below are dummy values (`'1a'×32`, `'2b'×32`).

## Consumer inventory (current code)

| Consumer | Primitive | Stored? | Rotation consequence |
|---|---|---|---|
| TOTP shared secrets: `MfaService` → `user_totp_credentials.secret_*` | AES-256-GCM via `SecretBox` (key = hex2bin(first 64 hex) or sha256(raw)) | ciphertext, no key id | Re-wrap possible: decrypt under K1, encrypt under K2; secret unchanged |
| Vault secrets: `SecretVault` → `service_secret_versions` (webhook signing, OIDC client, package secrets) | same | ciphertext; `key_version` always written as 1 (`ServiceSecretRepository.php:33`) | Re-wrap possible; versions, states and grace windows untouched |
| Recovery codes: `MfaService::recoveryHash` → `user_recovery_codes.code_hash` | HMAC-SHA256(raw APP_KEY, code); 10 × 40-bit codes | one-way verifier | Not migratable: valid only while K1 verifies |
| Email-change confirmation: `EmailChangeService::tokenHash` → `verifications.token_hash` | HMAC(raw key, bound JSON incl. raw token) | verifier; TTL default 3600 s, max 86400 s | Pending requests need K1 until they expire |
| Unsubscribe links: `SignedToken` via `UnsubscribeController` (instant and digest mail) | HMAC(raw key, `"unsubscribe\0"`+email); deterministic, no timestamp or expiry; also authorizes resubscribe | stateless | Old emails' links need K1 to verify |
| OAuth `rb_oauth` state cookie: `OAuthController` | HMAC(raw key, base64 payload) | stateless; cookie TTL 600 s | In-flight sign-ins need K1 for ≤ 10 min |
| Passkey login decoys: `PasskeyService` | HMAC(raw key, email+slot), generated, never verified | no | Decoys change once at promotion |
| Thread Intelligence latch fingerprint, `safety_identifier`, `request_fingerprint` | HMAC(raw key, …) | latch fingerprint in settings; fingerprint per generation | Latch clears; identifier changes; fingerprint never recomputed |

Not APP_KEY-dependent (verified by source): session tokens, CSRF (per-session
secret), password reset, email verification, invitations, API tokens, MFA login
challenges, idempotency keys and rate-limit subjects (plain SHA-256), and webhook
signatures (each endpoint's own vault secret).

## Probe (`probe-crypto.php` → `results/probe-crypto.json`)

- GCM fails closed: 0 of 2000 random wrong keys and a 1-bit tamper were accepted.
- One APP_KEY string feeds two derivations: an uppercased or extra-hex variant of
  K1 decrypts K1 ciphertext but yields different HMACs. Key material must be copied
  exactly, and a keyring must reject two entries with the same encryption root.
- A K1 unsubscribe token verifies under a [K2, K1] keyring and fails under [K2].
  The same holds for recovery-code, email-change and OAuth verifiers.

## Kernel diagnostic (`diagnostics/KeyRotationDiagnosticTest.php`)

It pins **current** behavior when APP_KEY changes from K1 to K2 with no keyring.
Results: `results/diagnostic-results.json`; server log
`results/diagnostic-server-errors.log` (three × `Unable to decrypt secret. @ SecretBox.php:35`).

| Observation | Result |
|---|---|
| Valid TOTP code after switch | **500** |
| K1 recovery code after switch | **500**; 10/10 codes still unused |
| Recovery code valid under K2 while TOTP secret still wrapped under K1 | **500**; codes unused (`MfaService.php:190` decrypts before the recovery check at `:198`) |
| Control: re-wrap the same TOTP secret under K2 | secret unchanged; recovery code and TOTP code both sign in (303 → `/inbox`) |
| K1 unsubscribe link under K2 / under K1 | 404 "invalid or has expired" / 200 |

Run 1 misused `TestCase::get()` by putting the query string in the path, so both
unsubscribe requests hit the router 404. Those two values were invalid and were
kept only as `results/run1-harness-error-*`. Its MFA results matched run 2.

No supported operator path resets a member's two-factor setup:
`user:password` does not touch TOTP, and self-service disable needs a working
second factor.

## Follow-up

The narrow failure fix turns the MFA half of the diagnostic into
`tests/Integration/Core/AppMfaUnreadableSecretTest.php`
(`../mfa-unreadable-secret-2026-11-08/`). The diagnostic here still pins the
pre-fix behavior at `eeceed78`. It asserts the 500s, so it fails against the
fixed code by design, and it is not part of the suite.

## Reproduce

```sh
php docs/evidence/app-key-rotation-review-2026-11-08/probe-crypto.php "$PWD"
# Disposable DB inside the retro user's retroboards% grant, then:
DB_TEST_DATABASE=retroboards_keyrot_review RB_DIAG_OUT=<out.json> \
  php -d error_log=<log> vendor/bin/phpunit --no-progress \
  docs/evidence/app-key-rotation-review-2026-11-08/diagnostics/KeyRotationDiagnosticTest.php
```

Result: `OK (2 tests, 31 assertions)` on PHP 8.5.4 against the local MariaDB
container. The disposable database and its bootstrap stamp and scratch files were
removed afterwards.
