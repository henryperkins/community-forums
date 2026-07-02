# RetroBoards registry protocol v1 (P5-01/P5-02, Increment 3)

**Status:** Landed 2026-07-02, deploy-dark behind `package_registry`.
**Scope:** wire contract and verification rules for signed catalogue snapshots,
advisories, key rotation, release documents, manifest validation, and the
deploy-dark install/update lifecycle. Enabled packages do not execute until the
theme and integration runtimes land in later increments.
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

## Release Documents & Artifacts (Inc 3)

The release document is the install artifact. A snapshot pins
`packages[].releases[].digest`, and that digest is `sha256` over the exact
signed `rb-release.v1` JSON bytes. The release document never carries its own
digest field. Maintainer review is inside the signed bytes, so an approval is
bound to the exact digest by construction.

```http
GET {base_url}/releases/{package_uid}/{version}/rb-release-envelope.v1.json
```

```json
{"format":"rb-release-envelope.v1","document":"<signed rb-release.v1 JSON string>","signature":"<base64>","key_id":"..."}
```

`package_releases.source_url`, when present, may override the default path but
must be same-origin with the pinned registry `base_url` (scheme, host, and port).
An offsite source refuses before fetch (`source_mismatch`).

`rb-release.v1` schema:

| Field | Rule |
|---|---|
| `format` | Literal `rb-release.v1`; verified before payload use. |
| `uid` | Package uid, exactly matching the snapshot package row. |
| `version` | Release version, exactly matching the snapshot release row. |
| `review.status` | One of `unreviewed`, `submitted`, `approved`, `rejected`; install/enable require `approved`. |
| `review.decided_at` | Optional UTC instant copied to review evidence when present. |
| `manifest` | Embedded `rb-manifest.v2` object, validated fail-closed. |

Acquisition order is fixed:

1. Resolve the cached artifact by pinned digest, or fetch the release envelope.
2. Refuse if the document bytes do not hash to the snapshot-pinned digest
   (`artifact_digest`).
3. Verify the detached Ed25519 signature over those exact bytes with the pinned
   trust chain and expected format `rb-release.v1`.
4. Refuse package/version mismatch (`release_identity`) or malformed review
   status (`release_review`).
5. Validate and hydrate the embedded manifest.
6. Cache the exact release bytes at
   `PACKAGES_STORAGE_PATH/{sha256}.json` (default `storage/packages/{sha256}.json`)
   and record release-review evidence using the envelope bytes.

`worker:packages` re-hashes the same cached bytes. A byte flip or missing digest
marks the install quarantined; local blocklist or `force_disable`/`revoke`
advisories disable enabled installs and cancel blocked staged updates. Re-verify
may restore health after operators replace the bytes, but it never auto-enables a
package.

## Manifest v2

`rb-manifest.v2` is strict: unknown top-level fields refuse, identities must
match the signed release, and every declaration maps to a typed local permission
snapshot with `risk` and consent label.

| Field | Rule |
|---|---|
| `format` | Literal `rb-manifest.v2`. |
| `uid` | Lowercase `publisher/name`, matching the release. |
| `type` | `theme`, `automation`, `remote_app`, or `local`; Gate A install policy allows only `theme`, `automation`, and `remote_app`. |
| `version` | Non-empty string matching the release. |
| `name` | Required non-empty string, max 190 chars. |
| `description` | Optional string, max 512 chars. |
| `license` | Optional string, max 190 chars. |
| `core` | Object with `min` and optional `max`; values use `CoreVersion` syntax. Compatibility is enforced during install/update. |
| `permissions` | Object of permission-kind lists; duplicate `(kind,key)` declarations refuse. |
| `settings_schema` | Optional `{"fields":[...]}`; non-empty when present. Field keys are lowercase identifiers; types are `string`, `boolean`, `integer`, or `select`; `required` is boolean; only `select` may declare non-empty string `options`. |
| `storage_quota_kb` | Integer `0..10240`. |
| `install.retention_days` | Optional integer `1..365`; otherwise the operator default `PACKAGES_RETENTION_DAYS` applies. |
| `support.homepage`, `support.issues` | Optional `https://` URLs, max 512 chars. |

Permission vocabulary:

| Manifest key | Stored kind | Rule |
|---|---|---|
| `capabilities[]` | `capability` | Must exist in `CapabilityCatalog`; protected capabilities are never grantable. |
| `data_classes[]` | `data_class` | Must exist in `DataClasses`; protected data classes are never grantable. |
| `api_scopes[]` | `api_scope` | Must be in `ApiScopes` (`read:boards`, `read:threads` in Inc 3). Declared/consented only; credential minting is deferred. |
| `events[]` | `event` | Must be a domain event from `WebhookEvents::domainEvents()`; `ping` is admin-test-only and refused. Declared/consented only until runtime consumers land. |
| `outbound_hosts[]` | `outbound_host` | Explicit lowercase hostnames only; no scheme, wildcard, localhost, or bare label. |
| `jobs[]` | `job` | Objects with lowercase `name` and schedule `hourly`, `daily`, or `weekly`; declared/consented only until runtime consumers land. |

Package-policy refusal codes exposed via `PackagePolicyException::$code`:

| Area | Codes |
|---|---|
| Release acquisition | `fetch_failed`, `source_mismatch`, `artifact_digest`, `release_identity`, `release_review` |
| Manifest shape | `manifest_format`, `unknown_field`, `manifest_identity`, `manifest_type`, `manifest_name`, `manifest_field`, `manifest_core`, `settings_schema`, `storage_quota`, `install_policy`, `support_link` |
| Permission vocabulary | `unknown_capability`, `protected_capability`, `unknown_data_class`, `protected_data_class`, `unknown_api_scope`, `unknown_event`, `outbound_host`, `job_declaration` |
| Gate policy | `type_forbidden`, `trust_class_forbidden`, `locally_blocked`, `advisory_blocked`, `advisory_revoked`, `review_not_approved` |

## Verification Rules (Fail Closed)

Every failure refuses the document.

1. `key_id` must be pinned for this registry; algorithm `ed25519`; status
   `active` or `rotated` within its validity window; never `revoked`.
2. Detached signature must verify over the exact document bytes.
3. `format` must equal the expected format.
4. Timestamps (`generated_at`, `expires_at`, advisory `issued_at`) are UTC
   instants in strict `YYYY-MM-DDTHH:MM:SSZ` form (what `gmdate()` emits). A
   value carrying a timezone offset is refused (`malformed_snapshot`) rather
   than reinterpreted — a lenient parse would store the offset's wall-clock as
   UTC and corrupt the anti-replay watermark.
5. Snapshots: `expires_at > now` (not expired), `generated_at <= now+300s`
   (skew), `expires_at <= generated_at + 86400s` (the 24h freshness window, D2,
   is enforced at ingest — a longer declared window is refused
   `freshness_window`), and `generated_at` strictly greater than the last
   applied snapshot (anti-replay); a byte-identical re-fetch is an idempotent
   no-op.
6. Identity: `package_uid` matches `publisher/name` (lowercase); a uid already
   owned by another registry refuses the whole snapshot (source pinning /
   dependency-confusion refusal).
7. Releases are immutable: a known `(package, version)` presenting a different
   digest refuses the whole snapshot. A changed byte is a new release.
8. Registries cannot assert local trust: `trust_class` of `first_party` or
   `vetted` in a snapshot entry refuses.
9. Advisories: a registry may only ingest advisories it owns — re-signing
   another registry's `advisory_uid` (`advisory_registry_conflict`) or targeting
   a package pinned to another registry (`advisory_package_conflict`) refuses,
   so a second registry cannot overwrite or de-escalate another's advisory.
10. Rotations must be signed by a currently active pinned key naming itself as
    `old_key_id` and a distinct successor with valid 32-byte key material.

Machine-readable refusal codes are exposed via `$e->code` on
`RegistryVerificationException` (`bad_signature`, `unknown_key`, `revoked_key`,
`key_window`, `expired_snapshot`, `replayed_snapshot`, `uid_conflict`,
`release_digest_rewrite`, and related codes). The TM-SC-01..05 fixtures assert
these refusal paths.

## Escalation Ladder (Advisories)

`warn -> warned`, `block_new -> blocked`, `force_disable -> blocked` plus
worker-enforced disable of enabled installed packages, and `revoke -> revoked`.

The registry-independent local blocklist (`local_package_blocks`) refuses
regardless of registry or trust-root state.
