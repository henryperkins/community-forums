# RetroBoards registry protocol v1 (P5-01, Increment 2)

**Status:** Landed 2026-07-02, deploy-dark behind `package_registry`.
**Scope:** wire contract and verification rules for signed catalogue snapshots,
advisories, and key rotation. Install/manifest semantics are Inc 3 (P5-02).
**Authority:** subordinate to `DECISIONS.md` -> `DESIGN.md` -> ADR 0004 (D1-D3)
-> `docs/phase5/registry-signing-key-custody.md` (A4).

## Documents (`rb-*.v1`)

All documents are JSON objects signed with a detached Ed25519 signature over the
exact JSON bytes, never a re-encoding. Digests are sha256 hex. The signing key
is identified by `key_id` and must match a pinned row in `registry_trust_keys`
(public bytes only; A4 section 1).

| Format | Purpose | Minted by (test) |
|---|---|---|
| `rb-registry-snapshot.v1` | Expiring catalogue snapshot (`generated_at`, `expires_at`, `packages[]`). | `SigningHarness::mintSnapshot` |
| `rb-advisory.v1` | One advisory (`advisory_uid`, `package_uid`, `affected_version_range` or `affected_digest`, `severity`, `action`). | `mintAdvisory` |
| `rb-key-rotation.v1` | Signed transition: old active key names its successor (`old_key_id`, `new_key_id`, `new_public_key` base64). | `mintRotation` |
| `rb-release.v1` | Per-release signed metadata consumed at install time in Inc 3. | `mintRelease` |

## Fetch Endpoints (`worker:registry-refresh`)

```http
GET {base_url}/rb-snapshot-envelope.v1.json
```

```json
{"format":"rb-snapshot-envelope.v1","document":"<signed JSON string>","signature":"<base64>","key_id":"..."}
```

```http
GET {base_url}/rb-advisory-envelopes.v1.json
```

`404` means no advisories.

```json
{"format":"rb-advisory-envelopes.v1","advisories":[{"document":"...","signature":"<base64>","key_id":"..."}]}
```

Fetches are EgressGuard-validated, DNS-pinned, redirect-free, and capped at
`registry.max_snapshot_bytes` (default 1 MiB). Only worker/cron code fetches;
web requests never fetch registry data directly (decision #10).

## Verification Rules (Fail Closed)

Every failure refuses the document.

1. `key_id` must be pinned for this registry; algorithm `ed25519`; status
   `active` or `rotated` within its validity window; never `revoked`.
2. Detached signature must verify over the exact document bytes.
3. `format` must equal the expected format.
4. Snapshots: `expires_at > now` (24h freshness, D2), `generated_at <= now+300s`,
   and `generated_at` strictly greater than the last applied snapshot
   (anti-replay); a byte-identical re-fetch is an idempotent no-op.
5. Identity: `package_uid` matches `publisher/name` (lowercase); a uid already
   owned by another registry refuses the whole snapshot (source pinning /
   dependency-confusion refusal).
6. Releases are immutable: a known `(package, version)` presenting a different
   digest refuses the whole snapshot. A changed byte is a new release.
7. Registries cannot assert local trust: `trust_class` of `first_party` or
   `vetted` in a snapshot entry refuses.
8. Rotations must be signed by a currently active pinned key naming itself as
   `old_key_id` and a distinct successor with valid 32-byte key material.

Machine-readable refusal codes are exposed via `$e->code` on
`RegistryVerificationException` (`bad_signature`, `unknown_key`, `revoked_key`,
`key_window`, `expired_snapshot`, `replayed_snapshot`, `uid_conflict`,
`release_digest_rewrite`, and related codes). The TM-SC-01..05 fixtures assert
these refusal paths.

## Escalation Ladder (Advisories)

`warn -> warned`, `block_new -> blocked`, `force_disable -> blocked` (plus
disabling the installed package once installs exist in Inc 3), and
`revoke -> revoked`.

The registry-independent local blocklist (`local_package_blocks`) refuses
regardless of registry or trust-root state.
