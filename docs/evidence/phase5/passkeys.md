# P5-11 Passkeys - Gate A evidence index (Increment 7)

Recorded 2026-07-03. Flag `passkeys` remains **default OFF**; enablement follows
PHASE_5_PLAN section 13.1 step 9.

| Section 9 scenario / requirement | Evidence |
| --- | --- |
| Registration validates challenge/origin/RP/signature/UP/UV; replay, cross-account, wrong origin/RP, duplicate, stale rejected | `WebAuthnPolicyTest` (protocol negatives), `AppPasskeyRegistrationTest` (TM-ID-05/06/07 over HTTP) |
| Sign-in: correct account; unknown credential/altered signature fail without account info | `AppPasskeyLoginTest` (fixed 8-slot decoy shape, stable decoys, generic errors) |
| Synced counter -> risk signal, no auto-ban (TM-ID-08, decision #30) | `AppPasskeyLoginTest::test_non_increasing_counter_signs_in_and_writes_a_risk_audit_row` |
| Removal: last-usable-method + final-owner blocked (TM-ID-09, F5 Inc-7 path) | `AppPasskeyRegistrationTest` last-method/owner tests |
| Fallback: password/TOTP/recovery journeys with passkeys enrolled; lost-authenticator recovery | `AppPasskeyRecoveryTest`; no-JS TOTP journey `tests/browser/totp.spec.ts` |
| Supported-browser + authenticator evidence | `tests/browser/passkeys.spec.ts` - Chromium CDP virtual authenticator, desktop+mobile, PNGs `docs/evidence/browser/*/passkeys-0*.png` |
| Accessibility | axe scan of the passkey panel inside `passkeys.spec.ts` |
| D11 budget `webauthn.ceremony_p95` <= 2000 ms | `docs/evidence/phase5/performance-budgets.md` (MEASURED PASS) |
| Audit + security notification on add/remove/login/anomaly | `moderation_log` assertions across the three App suites; fail-closed Mailer notices |
| RP-ID/origin policy (A5/D6) incl. production hard-refuse | `WebAuthnPolicyTest` RelyingParty cases |

Suite counts at Task 18 landing: `ThreatModelIndexTest` + `Phase5EvidenceMapTest`
cover 7 guard tests; the passkey PHP regression set covers 45 tests / 326
assertions; `tests/browser/passkeys.spec.ts` covers 4 Chromium WebAuthn tests.
Full gate counts are recorded by Task 20.
