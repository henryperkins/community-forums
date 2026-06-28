# Phase 5 Status

**Status:** **Gate A prerequisite work in progress — Milestone 0 decisions accepted for the release train, foundation schema landed, migration ledger reconciled, and TOTP/recovery implemented before passkey enforcement.** Package, capability, passkey, provider, invitation, sandbox, governance, service-principal, and verified-link behavior remains gated until each workstream has release evidence.
**Last updated:** 2026-06-28
**Branch:** `phase-5-foundation`
**Suite:** `./vendor/bin/phpunit` → **468 tests / 1730 assertions, green**. Focused Phase 5 prerequisite checks are included: `TotpTest`, `AppMfaTest`, `AppUserSettingsTest`, `AuthControllerTest`, `AppFeatureFlagTest`, and `AppPhase5FoundationSchemaTest`.

## What this increment is (and is not)

The first slice was the **safe foundation** for Phase 5: additive, reversible,
**inert** schema and deploy-dark flags so later workstreams have a documented
shape to build on (`PHASE_5_PLAN` §7 Milestone 1). This increment also resolves
the explicit B1 blocker by adding opt-in TOTP/recovery-code behavior before any
passkey enforcement.

> "Inert schema is not evidence" (DESIGN §13). The `0049`–`0053` foundation
> remains inert and every Phase 5 flag is dark by default. Migration `0054`
> is different: it is opt-in account-security behavior used only when a member
> enrolls in TOTP.

## Landed in this increment

- **Deploy-dark feature flags** (`src/Core/FeatureFlags.php`): Gate A —
  `package_registry`, `package_themes`, `capabilities`, `passkeys`,
  `provider_registry`, `invitations`; Gate B reserves — `server_extensions`,
  `governance`, `service_principals`, `verified_links`. All default `false`.
- **Additive foundation migrations** `0049`–`0053` (`SCHEMA.md` §5A documents every shape):
  - `0049` registry/packages/releases/installs/permissions/history/advisories/local-blocks (§8.2 #1–5).
  - `0050` capability registry, 4 protected system-role anchors, role-capability map, scoped/temporary assignments + audit, protected-owner authority (§8.2 #8/#9/#13). Capability catalogue **seeded empty** (taxonomy pending approval).
  - `0051` WebAuthn credentials + one-time challenges (§8.2 #14) — public credential material only.
  - `0052` identity-provider registry + the `oauth_identities.provider` **ENUM→VARCHAR(64)** widen + nullable `provider_config_id` linkage; seeds google/apple/github as dark builtin rows + aliases (§8.2 #15).
  - `0053` invitations + redemptions — hash-only tokens, non-privileged onboarding only (§8.2 #16).
- **`SCHEMA.md` v1.15**: new §5A, table-index rows 55–77, §9 changelog entry.
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
- **B2 — No plugin runtime / webhooks / API tokens exist.** The public ecosystem
  (esp. P5-04 remote apps mapping onto "accepted webhooks/API scopes") assumes a
  Phase 3 trusted-extension foundation that was deferred (ADR 0002) and never
  built. Treat that runtime as explicit Gate A prerequisite work, not an assumed
  foundation.
- **B3 — PARTIAL.** `SecretBox` provides app-key-backed AES-256-GCM storage for
  TOTP secrets. The broader service-secret registry needed by provider/remote-app
  credentials is still part of the B2 trusted-extension prerequisite.

## Requirement-ledger snapshot (`PHASE_5_PLAN` §11.1)

- **Foundation schema:** R2 (implemented behind disabled flags) + R3-partial
  (shape auto-verified). It is **not** R4/R5 — no behavior, no release evidence.
- **TOTP/recovery:** R3 implementation + focused release evidence. Needs full
  suite and browser/no-JS evidence before any Gate A acceptance package.
- **All other Phase 5 subsystems (registry, themes, capabilities/roles, passkeys,
  providers, invitations, sandbox, governance, service principals, verified
  links):** R0/R1 — pending implementation and workstream-specific evidence.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` … `0054_phase5_totp_recovery.php`.
- Schema doc: `SCHEMA.md` §5A + §9 changelog (v1.16).
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all 54 migrations on every PHPUnit run; full suite **468 tests / 1730 assertions green**.
- **Populated-upgrade rehearsal:** `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force` → **PASS 17/17** (`0049`–`0054` applied on seeded Phase-1 data; `oauth_identities` ENUM→VARCHAR widen included; 90 Phase-1 columns intact; zero data loss).
- **Legacy ledger probe:** scratch `schema_migrations(version, applied_at)` normalized to `schema_migrations(name, applied_at)` and `Migrator::status()` reported `0001_users` as applied (`status-ok columns=name,applied_at`).
- **Independent adversarial review:** 5-dimension review (DDL correctness, §8.2 completeness, security invariants, inertness, doc accuracy) with each finding re-verified against source. 5 findings confirmed / 10 rejected; all 5 resolved in this increment — `credential_id` widened to `VARBINARY(1023)` (WebAuthn L2 max), secret/separation invariants locked in the shape test, and two ADR-0004 wording fixes.
- Entry/decision record: `docs/adr/0004-phase-5-entry-and-carryover.md`; carryover source `docs/adr/0003-phase-4-closeout-deferrals.md`.

## Recommended next increments (after Milestone-0 approval)

Per `PHASE_5_PLAN` §13.1 staged order — each behind its dark flag, with shadow/
parity and adversarial evidence before enablement:

1. **Trusted extension foundation** (ADR 0004 B2): hook registry, webhook
   delivery ledger, admin/API tokens, service-secret storage, credential
   rotation/revocation, audit, rate limits, and disable switches. This is a Gate
   A prerequisite for public remote packages and must not expose untrusted PHP.
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
