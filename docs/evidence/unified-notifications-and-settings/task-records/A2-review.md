# A2 independent spec and quality review

**Verdict: approved.** No actionable regression or unmet A2 runtime requirement found in commit `8a525d9e683112b1b77036db73f80951e4aae817`.

Reviewed the complete 14-path commit against `AGENTS.md`, `A2-brief.md`, `A2-report.md`, and the combined unified-notifications/account-settings design. Traced the existing OAuth alias, password reauthentication, row locking, transaction, WriteGate, field-error helpers, and lifecycle recovery boundaries as read-only dependencies. Other workstreams and the active N3 correction were excluded.

## Confirmed behavior

- Invalid enrollment code or password leaves the encrypted pending credential unchanged. An ordinary Security GET retains confirmation without returning the provisioning secret or URI. Explicit restart requires the current password and uses the existing `mfa_settings` limiter; invalid and throttled restarts preserve the credential.
- Confirmation rejects absent, disabled, and already-enabled enrollment state. Successful enrollment retains session revocation, one-response recovery-code display, and consumed-code rejection. Secret-bearing input fields remain blank after validation errors.
- Passwordless Security exposes the shared first-password form, including when OAuth is disabled. Connections retains its OAuth-gated route and destination. Both paths use the account service and invalidate other sessions after success.
- First-password creation hashes before entering the transaction, then locks and rereads the authoritative user row. Both the current account-state gate and NULL-password condition are checked while that lock is held, before persistence. Stale identities cannot replace an existing password or bypass a subsequently applied restriction.
- Passwordless TOTP/lifecycle guidance links to Security without demanding an impossible current password. Direct stale/passwordless POSTs produce explanatory 422 responses. Existing password-bearing confirmation remains present. A1 reactivation and cancellation still work without introducing a password prerequisite.
- Password errors are scoped to the originating form. The pending confirmation form's password error has exactly one described-by target and does not mark the restart form invalid. Shared first-password markup preserves label associations, blank inputs, escaped action/error text, and field-linked errors. Existing-password refusal remains visible without marking unrelated Change password fields.

## Independent verification

Ran the existing focused A2 suite plus three temporary reviewer tests under the shared database lock:

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'A2IndependentReviewTest|AppMfaTest|AppAccountConsoleTest|AppOAuth|AppSessionManagementTest|AppAccountLifecycle|AppAccountTest|ReauthGate|TotpTest'
```

**Result: 110 tests, 801 assertions, exit 0**, no skips or warnings reported. This independently reran the supplied 107-test scope and added checks for stale active identities after each restricted state, exhausted authenticated restart attempts preserving all encrypted-secret columns, and unique form-scoped error targets/blank credentials. The temporary test file was removed afterward. No runtime source edits or commits were made; no real mail or OAuth requests were made.

## Review limits

This is A2 source/spec and focused PHP approval, not release approval. Browser rerun, Imladris reconciliation, and the full suite remain parent-owned gates. The initial browser failures described by the parent were harness issues; they are not counted as runtime defects or green browser evidence here. Concurrency was reviewed through the actual transaction/`FOR UPDATE` implementation and tested with stale identity snapshots; no simultaneous-process race harness was run.
