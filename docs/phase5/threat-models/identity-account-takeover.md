# Phase 5 threat model - Identity and account takeover

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 passkey and provider scenarios; section 12 identity risks; ADR 0004 D6-D8; `docs/phase5/canonical-origin-and-rp-id.md`.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

WebAuthn credentials/challenges, OIDC provider identities and provider config,
email and recovery paths, account linking, and session issuance. The protected
asset is that one member's account cannot be entered, linked, or merged by
anyone but its owner.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-ID-01 | OIDC issuer mix-up or wrong token audience logs in a victim. | Cross-issuer token, wrong audience, and wrong azp rejected. | Inc8 |
| TM-ID-02 | Replayed state, nonce, or PKCE callback is accepted. | Replayed state, missing nonce, wrong verifier rejected. | Inc8 |
| TM-ID-03 | JWKS poisoning verifies forged provider tokens. | JWKS fetch from off-issuer URL refused; real rotation accepted. | Inc8 |
| TM-ID-04 | Provider email collision silently merges accounts. | Verified-email match still requires linked-login proof. | Inc8 |
| TM-ID-05 | WebAuthn challenge replay or cross-user challenge succeeds. | Reused and cross-user WebAuthn challenges rejected. | Inc7 |
| TM-ID-06 | Attacker adds a passkey without fresh reauthentication. | Credential add without fresh reauth factor rejected. | Inc7 |
| TM-ID-07 | Wrong origin or RP ID completes a WebAuthn ceremony. | Mismatched origin/rpIdHash rejected in ceremony. | Inc7 |
| TM-ID-08 | Synced-passkey counter anomaly causes permanent lockout. | Non-increasing counter logs risk event without auto-lockout. | Inc7 |
| TM-ID-09 | Last usable credential removal strands account. | Last-usable-method removal blocked; provider-disable sole-method listing remains an Inc8 handoff. | Inc7 |

## Residual risk

Malware on the member device and provider-side account compromise are outside
application control. Phase 5 mitigates provider risk through stable subject
binding, explicit linking, and core-owned collision rules.
