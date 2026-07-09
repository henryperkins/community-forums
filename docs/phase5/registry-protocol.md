# RetroBoards registry protocol v1 (P5-01/P5-02/P5-03, Increment 4)

**Status:** Landed 2026-07-02 behind `package_registry` and `package_themes`
(both default-on since 2026-07-09, ADR 0018; operator-reversible).
**Scope:** wire contract and verification rules for signed catalogue snapshots,
advisories, key rotation, release documents, manifest validation, and the
flag-gated install/update lifecycle. Inc 4 adds the declarative theme runtime:
verified theme packages can build deterministic token-only stylesheets and serve
them only after explicit operator activation.
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
| `theme` | Required iff `type` is `theme`; forbidden for every other type. See [Theme Manifest Block](#theme-manifest-block-inc-4). |
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

### Theme Manifest Block (Inc 4)

Theme packages are Gate A declarative packages only: closed design-token
assignments plus approved local raster assets. They cannot ship selectors,
stylesheet modules, remote fonts, remote images, JavaScript, PHP, templates, or
raw CSS.

```json
{
  "theme": {
    "schema_version": 1,
    "tokens": {"--accent": "#8f3d12", "--radius": "7px", "--surface-texture": "parchment"},
    "dark_tokens": {"--surface": "#141210"},
    "assets": [{"name": "parchment", "kind": "png", "sha256": "<hex64>", "data_base64": "..."}]
  }
}
```

Rules:

- `schema_version` must be `1`.
- `tokens` is required and non-empty; `dark_tokens` is optional. Keys must be in
  `ThemeTokenPolicy::TOKENS`. Values are capped at 256 bytes and every grammar
  refuses declaration/selector escapes (`{}`, `;`, `@`, comments, `url(...)`,
  `expression(...)`, `javascript:`, `data:`, and `!important`).
- Color tokens are 6-digit hex values. Length tokens are `0` or bounded
  `px`/`rem`/`em` values. Font tokens are local font-family stacks made from
  quoted family names and generic CSS family keywords only. Asset tokens must
  name a declared asset.
- Assets are optional, max 4 per theme, max 128 KiB decoded per asset and
  256 KiB total after neutralization. Kinds are `png`, `jpeg`, `gif`, and
  `webp`; `sha256` is checked against decoded manifest bytes before build.
- `ThemeAssetScanner` sniffs with `finfo`, decodes with GD, and re-encodes the
  raster bytes before storage. SVG, kind mismatches, decode failures, and
  polyglots refuse with `theme_asset`.
- Contrast is evaluated for light and dark variants against the app.css
  baseline for partial token sets. Every policy pair must meet 4.5:1 or the
  build refuses with `theme_contrast`.

Package-policy refusal codes exposed via `PackagePolicyException::$code`:

| Area | Codes |
|---|---|
| Release acquisition | `fetch_failed`, `source_mismatch`, `artifact_digest`, `release_identity`, `release_review` |
| Manifest shape | `manifest_format`, `unknown_field`, `manifest_identity`, `manifest_type`, `manifest_name`, `manifest_field`, `manifest_core`, `settings_schema`, `storage_quota`, `install_policy`, `support_link` |
| Permission vocabulary | `unknown_capability`, `protected_capability`, `unknown_data_class`, `protected_data_class`, `unknown_api_scope`, `unknown_event`, `outbound_host`, `job_declaration` |
| Gate policy | `type_forbidden`, `trust_class_forbidden`, `locally_blocked`, `advisory_blocked`, `advisory_revoked`, `review_not_approved` |
| Theme policy | `theme_missing`, `theme_forbidden`, `theme_schema`, `theme_token`, `theme_asset`, `theme_contrast`, `theme_no_lkg`, `theme_lkg_invalid`, `artifact_tampered`, `invalid_state` |

## Theme Builds & Serving (Inc 4)

Theme builds are content-addressed by deterministic CSS bytes. The cache key is
`(installed_package_id, source_digest)` where `source_digest` is the verified
signed `rb-release.v1` digest on the installed package row. The emitted CSS is
ordered by the code-owned token catalogue and contains no timestamps, locale
formatting, random IDs, or network references.

Emission format:

```css
:root{--token:value;}
[data-theme="dark"]{--token:value;}
@media (prefers-color-scheme: dark){:root[data-theme="system"]{--token:value;}}
```

Asset token values are rewritten to same-origin immutable asset URLs:
`url("/theme/asset/{digest}")`, where `digest` is the sha256 of the neutralized
stored bytes. `css_digest` is `sha256` of the final CSS bytes.

Activation is transactional. A theme becomes active only after the installed
package is still enabled, package policy permits enablement, cached release
bytes exist and hash to the reviewed digest, the manifest validates, the build
and all assets are stored, and the last-known-good pointer is captured. Any
failure leaves the prior active theme unchanged.

Cascade precedence is:

1. `/assets/app.css` baseline.
2. Active or preview package theme stylesheet.
3. Operator `/brand.css` overrides.

When a package theme is active, `/brand.css` suppresses the built-in retro preset
layer but still serves operator color overrides and custom CSS, so local
operator configuration wins over package themes.

Serving contract:

| Route | Success | Cache | 404 conditions |
|---|---|---|---|
| `GET /theme/{css_digest}.css` | Active build CSS only, `text/css`, `ETag: "{css_digest}"` | `public, max-age=31536000, immutable` | `package_themes` off; safe mode on; digest malformed; no active build; digest does not equal the active build; active build bytes fail their digest check; owning install no longer `enabled` |
| `GET /theme/preview.css` | Current admin session preview CSS only, `text/css`, `ETag` of preview digest | `private, no-store` | `package_themes` off; safe mode on; no admin session; no session preview; preview build no longer serveable |
| `GET /theme/asset/{digest}` | Asset bytes for the current active build only, stored `image/*` MIME, `ETag: "{digest}"` | `public, max-age=31536000, immutable` | `package_themes` off; safe mode on; digest malformed; no active build; asset digest unknown; asset belongs to a non-active build |

Safe mode is independent of `package_themes`. The recovery page
`/admin/themes/safe-mode` remains available while the flag is off and renders
with the plain layout, never with package theme CSS. Safe mode can be entered
from the admin UI without password reauthentication, exited only with password
reauthentication, and forced by `THEME_SAFE_MODE=1`. While safe mode is on, all
public theme routes fail dark and the shell links no package theme stylesheet.

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

## Remote-App Settings, Credentials & Security Response (Inc 5)

Non-theme Gate A packages (`remote_app`, declarative `automation`) are animated
by a thin runtime over the B2 seams. Two normalized tables (migration `0073`):

- `installed_package_settings` — one row per `(installed_package_id, setting_key)`.
  Non-secret → `value_json`; secret (`type=string, secret:true`) → `SecretVault`,
  persisting only `secret_ref` (`svcsec_*`), `is_secret=1`. Exactly one of the two
  columns is populated per row.
- `installed_package_credentials` — `kind ∈ api_token|webhook`, FK to
  `api_tokens`/`webhooks` (ownership + attribution live here; the B2 tables are
  never widened with `owner_type/owner_id`). `created_by` is the minting admin for
  provenance only — package tokens are `ApiPrincipal`s, never a `User`.

**Ceiling vs authority:** runtime honours only `installed_package_permissions`
with `declared=1 AND granted=1`. `api_scope ⊆ ApiScopes::isValid`; `event ⊆
WebhookEvents::domainEvents()` (`ping` is operator-test-only and can never be
package-granted); webhook host ∈ granted `outbound_host`s. Private/DM payloads are
never broadened (IDs+enums only, `first_party_hooks` minimization authoritative).

**Provisioning** mints ≤1 read-only api_token + ≤1 webhook in one transaction;
plaintext is shown once. **Security response** adds publisher verify/suspend/
reinstate, signing-key pin/rotate (signed `rb-key-rotation.v1` envelope
`{document,signature,key_id}`)/revoke, local exact-digest review (tightening-only),
advisory/blocklist reuse (no new worker — stays on `worker:registry-refresh` +
`worker:packages`), and the flag-independent `package_execution_disabled` brake.
Lifecycle events widen `package_history.event` (+`settings_update`,
`credential_mint`, `credential_revoke`) and `moderation_log.target_type`
(+`publisher`).
