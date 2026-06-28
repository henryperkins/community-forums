# Design: Encrypted Service-Secret Registry

**Date:** 2026-06-28
**Status:** Approved design (brainstorming output) — pending implementation plan.
**Branch:** `phase-5-foundation`
**Phase / gate:** Phase 5, Gate A prerequisite. This is **sub-project 1 of 4** of the
B2 "trusted hook/webhook/API-token/service-secret foundation" (ADR 0004 Part B, row
B2/B3).

> Precedence reminder (CLAUDE.md): `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` >
> surface specs. This design implements requirements that those docs already locked;
> where this doc and an authoritative doc disagree, the authoritative doc wins and
> this design must be corrected.

---

## 0. Context — the B2 program and why this piece is first

ADR 0004 (Milestone-0 entry record) makes **B2** an explicit Phase 5 Gate A
*prerequisite*: the public ecosystem (P5-04 remote/declarative integrations, P5-12
provider registry) assumes a trusted hook/webhook/API-token/secret foundation that
was deferred in Phase 3 (ADR 0002) and **never built**. A repo audit confirms zero
migrations exist for `hooks`/`plugins`/`webhooks`/`webhook_deliveries`/`api_tokens`/
`service_secrets`; the only secret reference in the schema is the column
`identity_providers.client_secret_ref` (migration `0052`), which points into a secret
service that itself does not yet exist.

B2 decomposes into four sub-projects, built **foundation-first** in dependency order,
each behind its own dark flag with its own release evidence (matching the repo's
staged-rollout philosophy, ADR 0004 D12):

1. **Encrypted service-secret registry (B3)** — *this document.* Webhook HMAC secrets
   and provider client secrets both depend on it, so it is the root.
2. **API tokens + scope vocabulary** — hash-only, shown-once, scoped, expirable,
   revocable; human admin tokens vs independent (non-human) service credentials.
3. **Webhook delivery** — outbound HTTP, HMAC-signed, durable per-attempt ledger,
   retry/backoff/dead-letter, idempotent event identity, SSRF/egress control, 5s
   timeout, drained by a `GET_LOCK` worker.
4. **First-party hook registry** — in-process event/filter dispatch for first-party /
   vetted code only, disable-on-error, unable to take down the core request path.

**No public/untrusted PHP execution is in scope for any B2 Gate A sub-project.** The
sandbox is Gate B (`server_extensions`). Full service-principal identities are Gate B
(P5-14); B2 only provides the non-human scoped *credentials* and *secrets* those will
later attach to.

### Authoritative requirements this sub-project satisfies

- **ADR 0004 B3** (`docs/adr/0004-phase-5-entry-and-carryover.md`): "a registry of
  service-secret references and rotation/revocation state … Include service-secret
  storage, rotation, revocation, audit, and redaction in B2 before any
  provider/remote-app code writes a secret."
- **PHASE_5_PLAN decision #35**: "Provider/client secrets use the accepted encrypted
  secret service. They are **write-only after save, redacted in logs/exports,
  versioned, and rotatable**."
- **PHASE_5_PLAN §8.2 #15 / SCHEMA.md**: `client_secret_ref` is "a reference into the
  encrypted secret service — never plaintext."
- **ADR 0004 D11**: "no high-impact audit write may be skipped silently."
- **PHASE_5_PLAN decision #40**: "Every subsystem has an independent disable path."

### Brainstorming decisions locked for this increment

- **Scope:** standalone primitive + PHPUnit evidence. **No** admin UI, **no**
  provider/webhook wiring, **no** HTTP routes (nothing consumes it yet, so a UI would
  be synthetic). It is a tested seam, like `SecretBox` itself.
- **Rotation model:** versioned with a **grace window** — rotation retires the prior
  version but keeps it decryptable for a configurable window (so in-flight webhook
  signature verification can overlap old+new), then prunes it.

---

## 1. Purpose & boundary

A reversible-secret **vault seam**. Code stashes a sensitive value and receives an
**opaque reference string**; the consumer table persists only the reference. Plaintext
is encrypted at rest with the existing `App\Security\SecretBox` (AES-256-GCM, fresh
12-byte nonce per encryption, separate GCM tag — three separate binary columns, never
concatenated) and is **write-only**: no UI, log, export, or metadata path returns it.
It is revealed only server-side at the point of use (signing a webhook, calling a
provider token endpoint).

**In scope:** store, reveal (current), reveal usable set (grace overlap), rotate,
revoke, metadata (no plaintext), prune; versioning; non-lossy audit; redaction;
deploy-dark flag that doubles as a write kill switch.

**Out of scope (YAGNI guards — do not build in this increment):**
- Admin UI / HTTP routes (deferred until a consumer surface — provider config or
  webhook config — exists to give a page meaning).
- Provider / webhook wiring (separate B2 sub-projects).
- Envelope encryption / APP_KEY rotation. A `key_version` column is reserved at `1`
  for forward-compat only; APP_KEY rotation remains an operational runbook.
- Service-principal identity model (Gate B, P5-14).

---

## 2. Data model — migration `0055_phase5_service_secrets.php`

Additive, reversible, **inert until a consumer uses it** (additive-only / forward-only
runner; `up()` only creates, `down()` drops in reverse). Two tables: a reference parent
plus per-version encrypted material, because grace-window overlap requires more than
one live version per reference.

### `service_secrets` — reference / identity (holds no ciphertext)

| column | type | notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `secret_ref` | VARCHAR(64) | opaque handle, `svcsec_` + 32 hex (39 chars); UNIQUE; fits `client_secret_ref VARCHAR(190)` |
| `owner_type` | VARCHAR(32) | informational: `provider` / `webhook` / `remote_app` / `generic`. The authoritative link is the consumer's own `_ref` column, not a back-pointer here. |
| `owner_id` | BIGINT UNSIGNED NULL | informational owner row id when applicable |
| `label` | VARCHAR(190) NOT NULL | human description; never the secret |
| `status` | ENUM('active','revoked') NOT NULL DEFAULT 'active' | |
| `current_version` | INT UNSIGNED NOT NULL DEFAULT 0 | points at the live version number |
| `created_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `revoked_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `created_at` | DATETIME NOT NULL | UTC |
| `updated_at` | DATETIME NOT NULL | UTC |
| `revoked_at` | DATETIME NULL | |

Indexes: `UNIQUE KEY uq_service_secret_ref (secret_ref)`, `KEY idx_owner (owner_type, owner_id)`. `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

### `service_secret_versions` — encrypted material (one row per version)

| column | type | notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `secret_id` | BIGINT UNSIGNED NOT NULL | FK `service_secrets(id)` ON DELETE CASCADE |
| `version` | INT UNSIGNED NOT NULL | monotonic per secret |
| `ciphertext` | VARBINARY(4096) NOT NULL | AES-256-GCM output; zeroed on destroy |
| `nonce` | VARBINARY(12) NOT NULL | per-encryption random nonce |
| `tag` | VARBINARY(16) NOT NULL | GCM auth tag |
| `cipher` | VARCHAR(32) NOT NULL DEFAULT 'aes-256-gcm' | forward-compat label |
| `key_version` | INT UNSIGNED NOT NULL DEFAULT 1 | forward-compat; only v1 now |
| `state` | ENUM('current','retired','destroyed') NOT NULL DEFAULT 'current' | |
| `created_at` | DATETIME NOT NULL | UTC |
| `retire_after` | DATETIME NULL | grace deadline; set when retired |
| `retired_at` | DATETIME NULL | |
| `destroyed_at` | DATETIME NULL | set when ciphertext zeroed |

Indexes: `UNIQUE KEY uq_secret_version (secret_id, version)`, `KEY idx_prune (state, retire_after)`. `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

**Destroy semantics:** prune does **not** delete the row — it keeps the version history
(audit) and sets `state=destroyed`, `destroyed_at`, and overwrites
`ciphertext`/`nonce`/`tag` with zero bytes so no plaintext-recoverable material
remains.

`SCHEMA.md` is updated after the migration lands: shape rows for both tables, a §9
changelog entry, and a version bump.

---

## 3. Components & API

Layering mirrors the MFA slice exactly: thin SQL repository, a service that owns all
rules, crypto orchestration, audit, and transactions.

### `App\Repository\ServiceSecretRepository` (constructor `(private Database $db)`)
Prepared-statement SQL only, returns associative arrays:
- `insertSecret(...)`, `insertVersion(...)`, `findByRef(string)`,
  `currentVersionRow(int secretId)`, `usableVersionRows(int secretId, string nowUtc)`
  (current + retired with `retire_after >= now`), `retireVersion(int versionId, string retireAfterUtc)`,
  `bumpCurrentVersion(int secretId, int version)`, `markRevoked(int secretId, ?int actorId)`,
  `retireAllVersions(int secretId)`, `pruneRetiredBefore(string nowUtc, int limit)` →
  rows eligible, `destroyVersion(int versionId)` (zero ciphertext/nonce/tag, set state/destroyed_at).

Time is UTC everywhere (`UTC_TIMESTAMP()` / `gmdate()`). `LIMIT` is clamped to int and
concatenated, never bound (PDO `EMULATE_PREPARES=false`).

### `App\Service\SecretVault` (constructor `(ServiceSecretRepository, SecretBox, ModerationLogRepository, FeatureFlags, Config)`)

| method | behavior |
|---|---|
| `store(string ownerType, ?int ownerId, string label, string plaintext, ?User actor = null): string` | flag-gated (see §5); validate `strlen(plaintext) <= max_secret_bytes`; `SecretBox::encrypt`; **txn**: insert parent (`active`, `current_version=1`) + version 1 (`current`); audit `service_secret_stored`. Returns `secret_ref`. |
| `reveal(string ref): string` | load secret; throw `SecretNotFoundException` if absent, `SecretRevokedException` if revoked; decrypt the **current** version and return plaintext. The single egress; server-side only; never logged. |
| `usableSecrets(string ref): string[]` | decrypt current **plus** still-in-grace retired versions, newest first — for webhook signature-verification overlap. Throws `SecretNotFoundException` if missing, `SecretRevokedException` if revoked. |
| `rotate(string ref, string newPlaintext, ?User actor = null, ?int graceSeconds = null): int` | flag-gated; **txn**: retire current (`state=retired`, `retire_after = now + grace`), insert new `current`, bump `current_version`, touch `updated_at`; audit `service_secret_rotated`. Returns new version number. `graceSeconds` defaults to `secrets.rotation_grace_seconds`. |
| `revoke(string ref, ?User actor = null): void` | **txn**: parent `status=revoked` + `revoked_at`/`revoked_by`; retire all versions immediately (`retire_after = now`); audit `service_secret_revoked`. Subsequent `reveal`/`usableSecrets` throw `SecretRevokedException`. Allowed even when the flag is dark. |
| `metadata(string ref): array` | status, current_version, owner_type/id, label, created/updated/revoked timestamps, version count. **Never** plaintext or ciphertext. For the future admin listing. |
| `prune(int limit = 100): int` | destroy retired versions past `retire_after`; per-version audit `service_secret_version_destroyed`. Allowed when the flag is dark. Returns count destroyed. |

**Console:** add a `bin/console worker:secret-prune` case (news up `Database` →
`ServiceSecretRepository` → `SecretVault`, calls `prune`, prints `destroyed=N`),
plus a help line — mirroring the existing `worker:*` cases.

### Audit shape (`ModerationLogRepository::log`)
`target_type = 'service_secret'`, `target_id = service_secrets.id`. `before`/`after`
JSON carry only non-secret metadata:
- `service_secret_stored`: after `{ref, owner_type, owner_id, label, version: 1}`
- `service_secret_rotated`: before `{version: old}`, after `{version: new, grace_seconds}`
- `service_secret_revoked`: before `{status: 'active'}`, after `{status: 'revoked'}`
- `service_secret_version_destroyed`: before `{version, state: 'retired'}`, after `{version, state: 'destroyed'}`

---

## 4. Error handling

New domain exceptions (placed alongside the existing `ValidationException`):
`SecretNotFoundException`, `SecretRevokedException`, `SecretsDisabledException` —
all `RuntimeException`-based. This is server-side infrastructure with no user-facing
form path, so the kernel is not involved; consumers handle these. **No exception
message ever contains a secret value.**

- Oversize plaintext (`> secrets.max_secret_bytes`, default 4096) → `ValidationException`.
- Every audit write is **inside the same transaction** as its mutation, so a failed
  audit rolls the whole operation back — honoring D11 ("no high-impact audit write may
  be skipped silently").
- A `SecretBox` GCM tag mismatch (tampered/corrupt ciphertext) surfaces as a thrown
  decrypt error, not a silent empty string.

---

## 5. Feature flag & disable switch

Add `'service_secrets' => false` to `App\Core\FeatureFlags::DEFAULTS` (deploy-dark;
this is a B2-foundation flag, distinct from the original Phase 5 workstream flags).

Disable semantics double as a write kill switch:
- **Dark (off):** `store` and `rotate` throw `SecretsDisabledException`.
- **`reveal` / `usableSecrets` / `revoke` / `prune` stay available even when dark** —
  so an operator can freeze creation of new secrets while still reading existing ones
  at point of use, revoking compromised secrets, and cleaning up — the
  "pause-without-losing-control" shape required by decision #40.

A regression test asserts the flag defaults dark.

---

## 6. Wiring & config

- `App::buildContainer()`: bind `ServiceSecretRepository` (takes the shared
  `Database`), then `SecretVault` (one-liner closure pulling its collaborators via
  `$c->get(...)`), following the `MfaService` binding template.
- `config/config.php`: a `secrets` block —
  `'rotation_grace_seconds' => 86400` (24h) and `'max_secret_bytes' => 4096`.

---

## 7. Testing & evidence (the "done" bar)

Per DESIGN §13: this increment is non-UI infrastructure, so PHPUnit is the evidence
(no browser/no-JS capture required — there is no rendered surface yet).

**`tests/Integration/Service/SecretVaultTest`** (real test DB; assert observable
behavior, not row counts — per-test isolation is one outer transaction, and
`Database::transaction` is reentrant so the vault's inner txns do not independently
commit):
1. `store` → `reveal` returns the exact plaintext.
2. `store` → `metadata` shows `active`, version 1; no plaintext in the returned array.
3. `rotate` → `reveal` returns the **new** value; `usableSecrets` returns
   `[new, old]` within grace; `current_version` bumped.
4. rotate, then simulate grace expiry by directly updating the retired version's
   `retire_after` to a past UTC timestamp (the vault is clock-free — grace is compared
   in SQL via `UTC_TIMESTAMP()`), `prune` → old version destroyed: `usableSecrets`
   returns `[new]` only, the destroyed row's ciphertext is zero-length.
5. `revoke` → `reveal`/`usableSecrets` throw `SecretRevokedException`; `metadata`
   shows `revoked`.
6. **Redaction:** after store/rotate/revoke, assert the plaintext string appears in
   **no** `moderation_log` `before`/`after` JSON, and in no exception message.
7. **Flag-dark kill switch:** with `service_secrets` off, `store`/`rotate` throw
   `SecretsDisabledException`; `reveal`/`revoke`/`prune` still operate on a
   previously-stored secret.
8. Ciphertext-at-rest ≠ plaintext (the stored `ciphertext` bytes do not contain the
   plaintext).
9. Oversize plaintext → `ValidationException`.
10. Two stores yield distinct refs; a ref round-trips and fits `VARCHAR(190)`.
11. Unknown ref → `SecretNotFoundException`.

**`tests/Integration/Core/AppFeatureFlagTest`**: extend to assert `service_secrets`
defaults dark (and per-flag override stays isolated).

**Schema-shape test** — add `tests/Integration/Core/AppServiceSecretsSchemaTest`: a
fresh migrate applies `0055`; both tables exist with the documented columns/types;
`ciphertext`/`nonce`/`tag` are `VARBINARY`; `secret_ref` is unique; FKs and the
`(state, retire_after)` index exist.

**Suite + upgrade rehearsal:** `./vendor/bin/phpunit` green; `DB_DATABASE=… php
bin/console verify:upgrade --force` rehearses `0055` additively on seeded Phase-1 data
with zero data loss.

**Docs:** `SCHEMA.md` (shapes + §9 changelog + version bump); `PHASE_5_STATUS.md`
records this increment and the B2 decomposition (4 sub-projects, this one landed); the
B2 program decomposition is recorded so it is an explicit decision, not implied work.

---

## 8. Component isolation summary

| Unit | Does | Used via | Depends on |
|---|---|---|---|
| `SecretBox` (existing) | AES-256-GCM encrypt/decrypt | `encrypt()/decrypt()` | APP_KEY |
| `ServiceSecretRepository` | single-table SQL for the two tables | typed methods returning arrays | `Database` |
| `SecretVault` | rules, crypto orchestration, audit, txns, flag gate | `store/reveal/usableSecrets/rotate/revoke/metadata/prune` | repo, `SecretBox`, `ModerationLogRepository`, `FeatureFlags`, `Config` |
| `worker:secret-prune` | scheduled destroy of expired retired versions | `bin/console` | `SecretVault` |

Each unit is independently understandable and testable; consumers (providers,
webhooks) depend only on `SecretVault`'s opaque-ref API, never on its internals or the
table shapes.
