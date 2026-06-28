# Phase 5 Status

**Status:** **Foundation only — Milestone 1 schema reconciliation landed (additive, deploy-dark, inert). Phase 5 feature implementation is GATED** on Milestone-0 trust approvals and Phase 4 product-owner acceptance.
**Last updated:** 2026-06-28
**Branch:** `phase-5-foundation`
**Suite:** `./vendor/bin/phpunit` → **465 tests / 1699 assertions, green** (Phase 4 baseline 456/1635; +9 tests = the Phase 5 flag-dark test + the schema-shape suite). Run `phpunit` directly, not `composer test` — the composer wrapper's 300s Symfony-Process cap is shorter than this suite's ~6½ min runtime against the containerised DB.

## What this increment is (and is not)

This is the **safe foundation** slice chosen for Phase 5: it lays down additive,
reversible, **inert** schema and deploy-dark flags so later workstreams have a
documented shape to build on (`PHASE_5_PLAN` §7 Milestone 1), **without** starting
any Phase 5 *behavior* and **without** encoding any of the Milestone-0 trust
decisions that are the product owner's to make (`PHASE_5_PLAN` §2/§7).

> "Inert schema is not evidence" (DESIGN §13). Nothing here ships a feature. Every
> Phase 5 flag is dark; no application code reads or writes the new tables.

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

## Pending product-owner approvals (blocks feature work)

See **`docs/adr/0004-phase-5-entry-and-carryover.md`** for the full decision
register (D1–D12) with recommended defaults. In summary, no Phase 5 subsystem may
be enabled until the owner approves: package trust classes; registry trust
roots/signing-key custody; advisory/emergency-disable policy; the permission
taxonomy + non-delegable list; the isolation profile (Gate B); the canonical HTTPS
origin + WebAuthn RP ID; privileged-auth/passkey-recovery policy; generic OIDC +
the first added provider; invitation defaults; verified-link methods; and the
numeric budgets — plus recording **Phase 4 product-owner acceptance**.

## Blocking conflicts surfaced (R0 — need a Milestone-0 decision)

These were found during the readiness audit and are recorded in ADR 0004 Part B:

- **B1 — No TOTP/recovery exists.** Passkey recovery and "privileged MFA =
  passkey-or-TOTP" (P5-11) depend on an "accepted Phase 3 TOTP/recovery" subsystem
  that is not in the codebase. Either build it first or scope Gate A passkey
  recovery to email + operator support and defer the TOTP dependency. Do not
  enforce privileged MFA until a real second factor exists.
- **B2 — No plugin runtime / webhooks / API tokens exist.** The public ecosystem
  (esp. P5-04 remote apps mapping onto "accepted webhooks/API scopes") assumes a
  Phase 3 trusted-extension foundation that was deferred (ADR 0002) and never
  built. Treat that runtime as explicit Gate A prerequisite work, not an assumed
  foundation.
- **B3 — Confirm the encrypted secret service** before any provider/remote-app
  code persists a secret (`identity_providers.client_secret_ref`).

## Requirement-ledger snapshot (`PHASE_5_PLAN` §11.1)

- **Foundation schema:** R2 (implemented behind disabled flags) + R3-partial
  (shape auto-verified). It is **not** R4/R5 — no behavior, no release evidence.
- **All Phase 5 subsystems (registry, themes, capabilities/roles, passkeys,
  providers, invitations, sandbox, governance, service principals, verified
  links):** R0/R1 — pending the Milestone-0 approvals above. None are implemented.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` … `0053_phase5_invitations.php`.
- Schema doc: `SCHEMA.md` §5A + §9 changelog (v1.15).
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all 53 migrations on every run; full suite **465 tests / 1699 assertions green**.
- **Populated-upgrade rehearsal:** `php bin/console verify:upgrade` on a scratch DB → **PASS 17/17** (0049–0053 applied on seeded Phase-1 data; `oauth_identities` ENUM→VARCHAR widen included; 90 Phase-1 columns intact; zero data loss).
- **Independent adversarial review:** 5-dimension review (DDL correctness, §8.2 completeness, security invariants, inertness, doc accuracy) with each finding re-verified against source. 5 findings confirmed / 10 rejected; all 5 resolved in this increment — `credential_id` widened to `VARBINARY(1023)` (WebAuthn L2 max), secret/separation invariants locked in the shape test, and two ADR-0004 wording fixes.
- Entry/decision record: `docs/adr/0004-phase-5-entry-and-carryover.md`; carryover source `docs/adr/0003-phase-4-closeout-deferrals.md`.

## Recommended next increments (after Milestone-0 approval)

Per `PHASE_5_PLAN` §13.1 staged order — each behind its dark flag, with shadow/
parity and adversarial evidence before enablement:

1. **Capability resolver in shadow mode** (P5-08): seed the approved catalogue +
   map system roles, run the new resolver beside the accepted one, archive a
   parity corpus — no enforcement switch until parity is clean.
2. **Registry + signed declarative themes** (P5-01/03): signature/digest/staleness
   verification, isolated theme preview, safe mode.
3. **Passkeys** (P5-11) — only after B1 is resolved.
4. **Generic OIDC + provider migration** (P5-12) and **invitations** (P5-13).

## Operating note

Local dev/test DB is the `forum-software-db-1` container (mysql:8.4, host port
**3307**, user/pass `retro`/`retro`). A gitignored `.env` points at it
(`DB_PORT=3307`); the test DB `retroboards_test` and the `retroboards_upgrade_verify`
scratch DB are provisioned with grants for `retro`.
