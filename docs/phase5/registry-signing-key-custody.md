# A4 — Registry signing-key custody & rotation runbook (Phase 5 entry-gate artifact)

**Date:** 2026-06-30
**Status:** Recorded as the A4 entry-gate artifact required by `PHASE_5_PLAN.md`
§2/§7 (#1); operationalizes ADR 0004 **D2**. **Pending product-owner sign-off**
on the rotation cadence and the key custodian (§7).
**Precedence:** subordinate to `DECISIONS.md` → `DESIGN.md` → ADR 0004 (**D2**).

> **Scope.** This runbook covers the **package-registry / publisher signing trust
> root** (the supply-chain ed25519 root behind P5-01/02/04/07). It is *not*
> WebAuthn (see A5, `canonical-origin-and-rp-id.md`) and *not* the operator
> service-secret vault (`SecretVault`/`SecretBox`). Those use different key
> material and lifecycles.

---

## 1. The non-negotiable invariant

The **private** trust-root key never enters the application database, the repo, a
backup, an env var, or any app-readable path. The application stores and uses
**public key material only** — `registry_trust_keys.public_key`
(`VARBINARY(1024)`, `algorithm='ed25519'`) in migration `0049`. This is asserted
by the migration's design (`0049` header) and must be asserted by a Foundation
test (F6/F10): no code path reads or persists a registry **private** key.

Signing happens **offline, out-of-band, by the operator**. The server only ever
*verifies* detached signatures against pinned public keys (constant-time,
fail-closed, public-key-only — P5-01).

## 2. Trust model (from D2)

- **One first-party registry to start** (`package_registries`, one row,
  `is_enabled=0` until approved).
- **Offline Ed25519 signing root**, operator-held out-of-band.
- **Snapshot freshness/expiry = 24h** (`package_registries.snapshot_expires_at`;
  D11 budget). A stale snapshot fails closed (no new installs/enables) until
  refreshed.
- **Key rotation** via a *signed transition* (the new key is introduced through
  material signed by the currently-trusted key).
- **Registry-independent local blocklist** (`local_package_blocks`) always works,
  regardless of registry availability or trust-root state (D3 / decision #15).

## 3. Key material & identifiers

| Item | Where | Notes |
|---|---|---|
| Private root key | **operator custody, offline** | air-gapped; never in DB/repo/backup |
| Public root key | `registry_trust_keys.public_key` | bytes only; pinned at registry onboarding |
| Key version id | `registry_trust_keys.key_id` | every signature records `package_releases.signed_key_id` |
| Algorithm | `registry_trust_keys.algorithm='ed25519'` | ES/ed25519 only; no algorithm agility into weaker primitives |
| Validity window | `valid_from` / `valid_until` | verifier rejects signatures outside the window |
| Status | `active` \| `rotated` \| `revoked` | `revoked_at` + `revoked_reason` recorded |

## 4. Custody options (operator chooses one; record which in §7)

Listed by security strength; the **approved product default is option 2** (air-gapped
offline media), with **option 1 (hardware-backed) a stronger acceptable variant** (§7):

1. **Hardware token / HSM** (e.g. a FIDO/HSM device or cloud KMS that can do
   ed25519) — private key is non-exportable; signing is an explicit device
   operation. Strongest; recommended if available.
2. **Air-gapped offline media** — private key generated and kept on encrypted
   offline storage (two copies, geographically separated); only mounted on an
   offline machine to sign.
3. **Encrypted offline file with split backup** — passphrase-protected key file,
   with the passphrase and file custodied separately; paper/metal backup of the
   seed in a safe.

Whichever is chosen: **two independent backups**, an **offline** primary, and a
documented custodian + successor (so the root survives staff turnover —
complements the `protected_owners` break-glass path, A1 §4.5).

## 5. Procedures (runbook)

### 5.1 Initial trust-root setup (once, before enabling `package_registry`)
1. On an **offline** machine, generate the Ed25519 keypair; assign `key_id` (e.g.
   `root-2026a`).
2. Store the private key per §4; verify both backups restore.
3. Transfer the **public** key (and `key_id`, validity window) to the operator,
   who pins it via the admin registry-trust UI → row in `registry_trust_keys`
   (`status='active'`).
4. Keep `package_registries.is_enabled=0` until the rest of P5-01 evidence is in.

### 5.2 Signing a release (per publication)
1. Offline: compute the artifact `sha256` digest; sign the release metadata
   (digest + manifest identity) with the private root.
2. Publish the detached `signature` + `signed_key_id` into the registry
   (`package_releases.signature`,`signed_key_id`). Any byte change ⇒ a **new**
   release/digest, never an in-place replace (`0049` design; decision #16).

### 5.3 Routine rotation (signed transition)
1. Offline: generate the next key (`root-2026b`).
2. **Sign the new public key with the current (old) key** to prove continuity.
3. Pin the new key (`status='active'`, validity window); mark the old key
   `status='rotated'` with `valid_until=now` (it still verifies historically
   within its window, but signs nothing new).
4. Re-sign current snapshots/releases as needed so live verification uses the new
   key before the old window closes.

### 5.4 Revocation / compromise response
1. Mark the affected key `status='revoked'` (`revoked_at`,`revoked_reason`).
2. Verification of anything signed by a revoked key fails closed.
3. Use the **local blocklist** (`local_package_blocks`) to block specific
   digests/package_uids **immediately**, independent of the registry, while a
   clean key + re-signed releases are prepared (§5.3).
4. Issue/escalate advisories (`warn → block_new → force_disable → revoke`, D3) for
   any releases whose trust is now in doubt.

### 5.5 Disaster recovery (lost private root, no compromise)
1. Restore from an offline backup (§4). If unrecoverable, treat as rotation
   (§5.3) bootstrapped by re-pinning a fresh public key out-of-band (the operator
   re-establishes the root; there is no in-DB recovery path by design).
2. Re-sign live snapshots/releases under the new key.

## 6. Cadence (approved 2026-06-30)

- **Routine rotation: annually**, plus **immediately on suspected compromise** (owner-approved).
- Snapshot refresh: within the **24h** freshness window (worker
  `worker:registry-refresh`, P5-01).
- Backup-restore of the offline key tested **at least annually** (fold into the
  backup rehearsal, `tests/backup/rehearse.sh`).

## 7. Product-owner sign-off (approved 2026-06-30)

- [x] **Rotation cadence** — **annual + immediate-on-compromise**.
- [x] **Custodian + successor** — **deployment-local operator custody with a named
      successor per deployment** (each self-hosted instance designates its own key
      holder + backup custodian; complements the `protected_owners` break-glass path).
- [x] **Custody mechanism** — **default: air-gapped offline media with two backups**
      (§4 option 2); **hardware-backed non-exportable storage (§4 option 1) is a
      stronger acceptable variant**.

These are operational decisions, not code; the Foundation increment can proceed. The
verification side (public-key-only, fail-closed, constant-time) and the
local-blocklist control are built in P5-01 regardless.
