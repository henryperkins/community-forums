# Phase 5 threat model - Supply chain

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 registry, source pinning, key rotation, revoked release, outage, local tamper, review digest, publisher compromise scenarios; section 12 supply-chain risks; ADR 0004 D1-D3.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

Signed registry snapshots, trust roots, publishers, packages, immutable releases,
advisories, the local blocklist, and the integrity chain from reviewed bytes to
installed or rendered bytes. Private signing keys are outside the application
database by design.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-SC-01 | Tampered snapshot or release metadata is accepted. | Byte-flipped snapshot/release rejected by verifier. | Inc2 |
| TM-SC-02 | Stale or replayed snapshot is used after expiry. | Expired snapshot refuses install decisions. | Inc2 |
| TM-SC-03 | Signature from an untrusted or revoked key verifies. | Wrong-key and revoked-key signatures rejected. | Inc2 |
| TM-SC-04 | Forged key-rotation transition installs attacker key. | Rotation doc signed by non-approved key rejected. | Inc2 |
| TM-SC-05 | Source substitution satisfies a pinned package identity. | Same-uid package on second registry cannot satisfy install/update. | Inc2 |
| TM-SC-06 | Revoked or locally blocked digest installs or enables. | Revoked and locally-blocked digests fail install and enable. | Inc3 |
| TM-SC-07 | Installed bytes differ from reviewed digest. | On-disk byte flip triggers health failure and quarantine. | Inc3 |
| TM-SC-08 | Manifest omits authority that runtime later exercises. | Undeclared scope/host exercised at runtime is denied and audited. | Inc5 |
| TM-SC-09 | Review approves digest A but digest B ships under same version. | Approval for digest A does not authorize digest B. | Inc3 |

## Residual risk

A compromised operator host or malicious first-party maintainer is out of scope
for Gate A. The accepted control is explicit operator trust-root custody,
exact-digest review, local emergency disable, and fail-closed verification.
