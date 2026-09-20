# A2 implementation report

Status: runtime and focused PHP verification complete in commit 8a525d9e; source ownership released. Parent owns browser and release gates.

## Implemented

- Pending TOTP confirmation remains available after invalid OTP/password and ordinary reload, with the same encrypted pending secret retained. Fresh provisioning data appears only on the authenticated enrollment/restart response. Pending enrollment exposes an explicit Restart setup button using the existing reauthenticated, mfa_settings-limited enroll POST.
- Disabled or already-enabled TOTP records cannot be confirmed as pending enrollment. Password/code inputs remain blank after validation errors. Existing recovery code one-response visibility, consumed-code rejection, and session invalidation remain intact.
- Security exposes Set a password at #set-password for actual NULL-password accounts; the new POST /settings/security/set-password applies mfa_settings. Connections retains its existing feature-gated alias; both use AccountService::setInitialPassword and the new shared partial. OAuth-dark Security still works.
- First-password persistence locks and rechecks the authoritative user row in the mutation transaction, checking both WriteGate and password absence. A stale passwordless User cannot replace an existing credential. Both controller routes revoke other sessions and preserve the current session.
- Passwordless TOTP and lifecycle entry states omit impossible current-password prompts and link directly to /settings/security#set-password. Direct password-required POSTs explain how to set a password. First-password errors have field-linked 422 responses with blank fields; stale existing-password refusals remain visible and do not mark unrelated change-password fields.
- A1 recovery remains unchanged: passwordless deactivated members can reactivate, and pending-deletion members can cancel without adding a password requirement. Only the missing-password error text changed in AccountLifecycleService.

## Tests and evidence

RED before runtime edits: 8 tests, 15 assertions, 8 expected failures, exit 1. Evidence: docs/evidence/unified-notifications-and-settings/a2-phpunit-red.log (sanitized; response HTML and credentials omitted).

GREEN command:

```
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppMfaTest|AppAccountConsoleTest|AppOAuth|AppSessionManagementTest|AppAccountLifecycle|AppAccountTest|ReauthGate|TotpTest'
```

Result: 107 tests, 767 assertions, exit 0; no skips, warnings, or deprecations reported. Evidence: docs/evidence/unified-notifications-and-settings/a2-phpunit-green.log. Existing OAuth alias validation, MFA no-JS flow, session revocation, A1 restriction/expiry/state-machine tests, and reauthentication tests are included. git diff --check on owned paths passed.

New regression coverage: invalid-code/password/reload/same-pending-secret confirmation; explicit password-gated restart and new secret; disabled-enrollment refusal; one-response recovery codes and consumed enrollment-OTP replay refusal; genuine NULL-password Security/lifecycle/TOTP states; OAuth-dark first-password mismatch/success/existing-password refusal; stale credential overwrite refusal; first-password rate limit/restricted states; both aliases revoke other sessions; passwordless recovery operations.

## Self-review and limits

Reviewed every owned source/template/test diff for credential redisclosure, error-context ownership, feature gating, WriteGate, authoritative locking, and A1 recovery preservation. No migrations, CSS, actual OAuth, real mail, or browser-spec changes. Parent is running the existing A2 browser contracts and owns generated Imladris bindings/release full suite. Full suite not run by this implementer per orchestration instruction. Concurrency correctness is exercised with stale identity snapshots and implemented with FOR UPDATE; no separate simultaneous-process race harness was introduced.

## Ownership release

Runtime source and PHP tests are stable and released to parent after commit. Parent-owned documentation, browser runner/specs, and other working-tree changes are excluded from the exact-path commit.

Browser triage after commit: initial parent run exposed harness issues (page.reload replayed the enrollment POST, a singular locator matched both valid lifecycle links, and the existing TOTP logout action targeted a closed identity menu). Parent confirmed ownership of corrections and rerun; no A2 runtime regression was identified by those failures. Do not treat this initial browser run as green.
