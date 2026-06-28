# Phase 5 Status

**Status:** **Gate A prerequisite work in progress — Milestone 0 decisions accepted for the release train, foundation schema landed, migration ledger reconciled, TOTP/recovery implemented before passkey enforcement, and the B2 service-secret registry landed deploy-dark.** Package, capability, passkey, provider, invitation, sandbox, governance, API-token, webhook, hook-registry, service-principal, and verified-link behavior remains gated until each workstream has release evidence.
**Last updated:** 2026-06-28
**Branch:** `phase-5-foundation`
**Suite:** `./vendor/bin/phpunit` → **489 tests / 1804 assertions, green**. Focused Phase 5 prerequisite checks are included: `TotpTest`, `AppMfaTest`, `AppUserSettingsTest`, `AuthControllerTest`, `AppFeatureFlagTest`, `AppPhase5FoundationSchemaTest`, `AppServiceSecretsSchemaTest`, and `SecretVaultTest`.

## What this increment is (and is not)

The first slice was the **safe foundation** for Phase 5: additive, reversible,
**inert** schema and deploy-dark flags so later workstreams have a documented
shape to build on (`PHASE_5_PLAN` §7 Milestone 1). This increment also resolves
the explicit B1 blocker by adding opt-in TOTP/recovery-code behavior before any
passkey enforcement, and lands the first B2 sub-project: encrypted service-secret
storage/rotation/revocation/prune as a tested seam for later provider/webhook
consumers.

> "Inert schema is not evidence" (DESIGN §13). The `0049`–`0053` foundation
> remains inert and every Phase 5 flag is dark by default. Migration `0054`
> is opt-in account-security behavior used only when a member enrolls in TOTP.
> Migration `0055` is a deploy-dark service seam: tested storage behavior exists,
> but no provider/webhook/remote-app consumer is wired yet.

## Landed in this increment

- **Deploy-dark feature flags** (`src/Core/FeatureFlags.php`): Gate A —
  `package_registry`, `package_themes`, `capabilities`, `passkeys`,
  `provider_registry`, `invitations`, `service_secrets`; Gate B reserves —
  `server_extensions`, `governance`, `service_principals`, `verified_links`.
  All default `false`; `service_secrets` also acts as the B2 write/rotate kill
  switch while reveal/revoke/prune remain available.
- **Additive foundation migrations** `0049`–`0053` (`SCHEMA.md` §5A documents every shape):
  - `0049` registry/packages/releases/installs/permissions/history/advisories/local-blocks (§8.2 #1–5).
  - `0050` capability registry, 4 protected system-role anchors, role-capability map, scoped/temporary assignments + audit, protected-owner authority (§8.2 #8/#9/#13). Capability catalogue **seeded empty** (taxonomy pending approval).
  - `0051` WebAuthn credentials + one-time challenges (§8.2 #14) — public credential material only.
  - `0052` identity-provider registry + the `oauth_identities.provider` **ENUM→VARCHAR(64)** widen + nullable `provider_config_id` linkage; seeds google/apple/github as dark builtin rows + aliases (§8.2 #15).
  - `0053` invitations + redemptions — hash-only tokens, non-privileged onboarding only (§8.2 #16).
- **`SCHEMA.md` v1.17**: §5A for foundation schema, §5B for the B2
  service-secret registry, table-index rows 55–82, and §9 changelog entries.
- **Regression tests**:
  - `tests/Integration/Core/AppFeatureFlagTest.php::test_phase5_foundation_flags_default_dark` — all Phase 5 flags deploy dark; per-flag override is isolated.
  - `tests/Integration/Core/AppPhase5FoundationSchemaTest.php` — clean-install applies `0049`–`0053`; tables/columns match the documented shape; the provider widen preserves legacy values; tokens are hash-only; system roles are protected anchors; capability catalogue is empty.
- **Milestone-0 entry record**: `docs/adr/0004-phase-5-entry-and-carryover.md`.
- **Migration-ledger reconciliation** (`src/Core/Migrator.php`): an existing
  `schema_migrations.version` ledger is normalized to the canonical `name`
  column before `migrate`, `rollback`, or `migrate:status` read it.
- **TOTP + recovery-code prerequisite** (`0054`, `src/Security/Totp.php`,
  `src/Service/MfaService.php`, account/login templates): opt-in enrollment,
  verification, encrypted TOTP secret storage, replay-safe `last_used_step`,
  hash-only one-time recovery codes, rotation, disable, password reauth for
  settings changes, one-time login challenges, OAuth/password login step-up for
  enrolled users, and audit events in `moderation_log`.
- **B2 service-secret registry — sub-project 1 landed** (`0055`,
  `src/Repository/ServiceSecretRepository.php`, `src/Service/SecretVault.php`):
  opaque `svcsec_*` references, AES-256-GCM material split into ciphertext/nonce/tag,
  versioned rotate with grace overlap, revoke, metadata without secret material,
  idempotent prune that preserves destroyed version rows, `worker:secret-prune`,
  non-lossy audit in `moderation_log`, and redaction tests for audit/metadata/
  exception paths.
- **B2 decomposition recorded** (design:
  `docs/superpowers/specs/2026-06-28-service-secret-registry-design.md`): 1)
  service-secret registry — landed, 2) API tokens + scopes, 3) webhook delivery,
  4) first-party hook registry. No public/untrusted PHP execution is included in
  these Gate A sub-projects.

## Product-owner approvals recorded

This instruction accepts ADR 0004 as the Milestone-0 decision record using its
recommended defaults unless a later owner scope-change record overrides them. It
also accepts the Phase 4 engineering closeout and ADR 0003 deferrals as explicit
carryovers, not shipped behavior.

## Blocking conflicts surfaced (R0 — need a Milestone-0 decision)

These were found during the readiness audit and are recorded in ADR 0004 Part B:

- **B1 — RESOLVED in this increment.** TOTP/recovery now exists as opt-in
  account-security behavior and is covered by focused unit/integration tests.
  Passkey enforcement may build on it; ordinary users are still not required to
  enroll by default.
- **B2 — PARTIAL, decomposed.** Sub-project 1 (service-secret registry) has landed
  behind `service_secrets`; sub-projects 2–4 (API tokens + scopes, webhook
  delivery, first-party hook registry) remain Gate A prerequisite work. No plugin
  runtime or public/untrusted PHP execution exists.
- **B3 — STORAGE LAYER LANDED, NOT CONSUMED.** `SecretBox` provides
  app-key-backed AES-256-GCM and `SecretVault` now provides the broader
  service-secret registry for provider/remote-app credentials. No provider,
  webhook, or remote-app code consumes it yet.

## Requirement-ledger snapshot (`PHASE_5_PLAN` §11.1)

- **Foundation schema:** R2 (implemented behind disabled flags) + R3-partial
  (shape auto-verified). It is **not** R4/R5 — no behavior, no release evidence.
- **TOTP/recovery:** R3 implementation + focused release evidence. Full suite is
  green; browser/no-JS evidence is still needed before any Gate A acceptance
  package.
- **Service-secret registry:** R3 storage/service implementation + focused release
  evidence. It has no consumer surface yet, so it is not R4/R5 product behavior.
- **All other Phase 5 subsystems (registry, themes, capabilities/roles, passkeys,
  providers, invitations, API tokens, webhook delivery, first-party hook registry,
  sandbox, governance, service principals, verified links):** R0/R1 — pending
  implementation and workstream-specific evidence.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` … `0055_phase5_service_secrets.php`.
- Schema doc: `SCHEMA.md` §5A/§5B + §9 changelog (v1.17).
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- Service-secret schema regression: `tests/Integration/Core/AppServiceSecretsSchemaTest.php`.
- Service-secret behavior/redaction regression: `tests/Integration/Service/SecretVaultTest.php`.
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all 55 migrations on every PHPUnit run; full suite **489 tests / 1804 assertions green**.
- **Populated-upgrade rehearsal:** `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force` → **PASS 17/17** (`0049`–`0055` applied on seeded Phase-1 data; `oauth_identities` ENUM→VARCHAR widen included; 90 Phase-1 columns intact; zero data loss).
- **Legacy ledger probe:** scratch `schema_migrations(version, applied_at)` normalized to `schema_migrations(name, applied_at)` and `Migrator::status()` reported `0001_users` as applied (`status-ok columns=name,applied_at`).
- **Independent adversarial review:** 5-dimension review (DDL correctness, §8.2 completeness, security invariants, inertness, doc accuracy) with each finding re-verified against source. 5 findings confirmed / 10 rejected; all 5 resolved in this increment — `credential_id` widened to `VARBINARY(1023)` (WebAuthn L2 max), secret/separation invariants locked in the shape test, and two ADR-0004 wording fixes.
- Entry/decision record: `docs/adr/0004-phase-5-entry-and-carryover.md`; carryover source `docs/adr/0003-phase-4-closeout-deferrals.md`.

## Recommended next increments (after Milestone-0 approval)

Per `PHASE_5_PLAN` §13.1 staged order — each behind its dark flag, with shadow/
parity and adversarial evidence before enablement:

1. **Continue trusted extension foundation** (ADR 0004 B2): API tokens + scopes,
   webhook delivery ledger, and first-party hook registry on top of the landed
   service-secret registry. This is a Gate A prerequisite for public remote
   packages and must not expose untrusted PHP.
2. **Capability resolver in shadow mode** (P5-08): seed the approved catalogue +
   map system roles, run the new resolver beside the accepted one, archive a
   parity corpus — no enforcement switch until parity is clean.
3. **Registry + signed declarative themes** (P5-01/03): signature/digest/staleness
   verification, isolated theme preview, safe mode.
4. **Passkeys** (P5-11) on top of the TOTP/recovery fallback.
5. **Generic OIDC + provider migration** (P5-12) and **invitations** (P5-13).

## Operating note

Local dev/test DB is the `forum-software-db-1` container (mysql:8.4, host port
**3307**, user/pass `retro`/`retro`). A gitignored `.env` points at it
(`DB_PORT=3307`); the test DB `retroboards_test` and the `retroboards_upgrade_verify`
scratch DB are provisioned with grants for `retro`.
