# Phase 5 threat model - Invitation privilege

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 invitation create/revoke/redeem/bind/expire/use-limit/abuse/no-privilege-escalation scenarios; section 12 invitation risks.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

Invitation tokens, token hashes, issuance authority, redemption binding,
optional email/domain constraints, use limits, expiry/revoke state, audit, and
the account/role outcome created by redemption. An invitation must not become a
privilege escalation primitive.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-IN-01 | Token enumeration discovers redeemable invitations. | Token enumeration rejected and rate-limited; DB holds no raw token. | Inc9 |
| TM-IN-02 | Concurrent redemption consumes a single-use token twice. | Two concurrent redemptions of a single-use token yield exactly one account. | Inc9 |
| TM-IN-03 | Expired or revoked token still creates an account. | Expired and revoked tokens fail with no account created. | Inc9 |
| TM-IN-04 | Email or domain binding is bypassed. | Mismatched email and mismatched domain both rejected. | Inc9 |
| TM-IN-05 | Redeemer forges role/grant fields in POST payload. | Forged role/grant fields in redemption POST yield ordinary membership only. | Inc9 |
| TM-IN-06 | Raw token persists in DB, logs, or list views after issuance. | Post-create, token absent from DB/logs/list views. | Inc9 |
| TM-IN-07 | Unprivileged member issues invitations or floods issuance. | Member issuance denied by default; burst issuance rate-limited. | Inc9 |

## Residual risk

If an inviter intentionally shares a valid token with the wrong person, the
system can only enforce token constraints, expiry, revocation, rate limits, and
audit trails. Human invite policy remains an operator responsibility.
