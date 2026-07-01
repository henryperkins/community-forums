# Phase 5 Status

**Status:** **Gate A prerequisite work in progress — Milestone 0 decisions accepted for the release train, foundation schema landed, migration ledger reconciled, TOTP/recovery implemented before passkey enforcement, all four B2 sub-projects (service-secret registry, read-only API tokens, webhook delivery, first-party hook producers) landed deploy-dark, and the Foundation authorization spine F3 (capability catalogue + coverage) and F5 (protected-owner seed + `LastOwnerGuard`) landed deploy-dark behind `capabilities`.** Package, capability, passkey, provider, invitation, sandbox, governance, service-principal, and verified-link behavior remains gated until each workstream has release evidence. The remaining §2 entry-gate artifacts (A1/A4/A5/A8) are recorded with owner sign-offs and **accepted 2026-07-01** in ADR 0012; the Foundation increment (F1–F11) is underway — **F3 + F5 landed 2026-07-01**.
**Last updated:** 2026-07-01
**Branch:** `main`
**Suite:** `./vendor/bin/phpunit` → **857 tests / 4456 assertions, green** (post-F3/F5; +28 tests over the prior 829 baseline, incl. the owner-status adversarial-review regression; verified on two consecutive plain runs — both the fresh-migrate and the reused-schema `composer test` path). Browser evidence `npm run evidence` → **14/14 Playwright checks, green** across desktop + mobile (F3/F5 add no UI surface — PHPUnit + `verify:upgrade` only). Focused Phase 5 prerequisite checks are included: `TotpTest`, `AppMfaTest`, `AppUserSettingsTest`, `AuthControllerTest`, `AppFeatureFlagTest`, `AppPhase5FoundationSchemaTest`, `AppServiceSecretsSchemaTest`, `SecretVaultTest`, the B2 API-token suite (`AppApiTokensSchemaTest`, `ApiScopesTest`, `ApiTokenServiceTest`, `ApiReadEndpointsTest`, `AdminApiTokenTest`), the B2 webhook-delivery suite (`AppWebhooksSchemaTest`, `WebhookEventsTest`, `EgressGuardTest`, `WebhookSignerTest`, `WebhookTransportTest`, `WebhookRepositoryTest`, `WebhookDeliveryRepositoryTest`, `WebhookServiceTest`, `WebhookDeliveryWorkerTest`, `AdminWebhookTest`), the B2 hook/producer suite (`FirstPartyHookRegistryTest`, `DomainWebhookProducerTest`), and the Foundation F3/F5 suite (`CapabilityCatalogTest`, `CapabilityInventoryCoverageTest`, `AppPhase5CapabilitySeedTest`, `ProtectedOwnerRepositoryTest`, `RepairProtectedOwnersTest`, `AppProtectedOwnerTest`).

## Gate A entry-gate artifacts (recorded 2026-06-30; accepted 2026-07-01)

The remaining `PHASE_5_PLAN.md` §2 entry-gate artifacts are recorded; **ADR 0012**
(`docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md`) carries the gate record and
the owner sign-offs (received 2026-06-30), **accepted 2026-07-01** on the owner's
final acceptance pass. The acceptance ADR is renumbered **0013**.

- **A1 — capability taxonomy** → `docs/phase5/capability-taxonomy.md`: 54 `core.*`
  keys (hybrid granularity), scope/risk per the `0050` ENUMs, 5 non-delegable
  protected keys (decision #22), parity-first with documented legacy quirks. Source
  of truth the Foundation **F3** generates `CapabilityCatalog.php` + the `0066` seed from.
- **A4 — signing-key custody** → `docs/phase5/registry-signing-key-custody.md`:
  offline Ed25519, public-key-only in DB, signed-transition rotation; approved
  cadence annual + on-compromise, deployment-local custody, air-gapped-media default.
- **A5 — canonical origin / RP ID** → `docs/phase5/canonical-origin-and-rp-id.md`:
  RP-ID = registrable domain of `APP_URL`, origin-equality validation, prod-HTTPS
  hard-refuse, domain-change/DR runbook (self-host framing).
- **A8 — product-demand review** → ADR 0012: declarative-first Gate A ecosystem
  approved, conditioned on the non-critical + no-untrusted-PHP guarantees.

Still dark/inert — no behavior enables. With the §2 entry-gate artifacts **accepted
2026-07-01** (ADR 0012), the **Foundation increment (F1–F11)** may now proceed as an
executable TDD plan — the next step. A2 (first named OIDC provider) is still required
before P5-12 acceptance.

## What this increment is (and is not)

The first slice was the **safe foundation** for Phase 5: additive, reversible,
**inert** schema and deploy-dark flags so later workstreams have a documented
shape to build on (`PHASE_5_PLAN` §7 Milestone 1). This increment also resolves
the explicit B1 blocker by adding opt-in TOTP/recovery-code behavior before any
passkey enforcement, and lands all four B2 sub-projects: encrypted service-secret
storage/rotation/revocation/prune as a tested seam for later provider/webhook
consumers, admin-minted, hash-only, scope-gated read-only API tokens over a
`/api/v1` surface, a deploy-dark outbound webhook delivery engine with operator
admin UI, HMAC signing, durable retry/backoff/dead-letter ledger, SSRF egress
controls, a `worker:webhooks` drainer, and a code-only first-party hook registry
that routes catalogued public-board domain events into the existing webhook
dispatch path.

> "Inert schema is not evidence" (DESIGN §13). The `0049`–`0053` foundation
> remains inert and every Phase 5 flag is dark by default. Migration `0054`
> is opt-in account-security behavior used only when a member enrolls in TOTP.
> Migration `0055` is a deploy-dark service seam: tested storage behavior exists,
> and webhook delivery consumes it; provider/remote-app consumers remain deferred. Migration `0056`
> (API tokens) is deploy-dark behind `api_tokens`: the `/api/v1` read surface and
> admin UI exist and are tested, but the flag is off by default.
> Migration `0057` (webhook delivery) is deploy-dark behind `webhooks`: the engine,
> admin UI, queue, worker, and test-event path exist and are tested. The B2 SP4
> hook registry adds no migration; it is code-only behind `first_party_hooks`,
> keeps `ping` admin-test-only, and currently emits only IDs/state payloads for
> public-board domain content until endpoint-level data-class permissions exist.

## Landed in this increment

- **Deploy-dark feature flags** (`src/Core/FeatureFlags.php`): Gate A —
  `package_registry`, `package_themes`, `capabilities`, `passkeys`,
  `provider_registry`, `invitations`, `service_secrets`, `api_tokens`,
  `webhooks`, `first_party_hooks`; Gate B reserves — `server_extensions`,
  `governance`, `service_principals`, `verified_links`. All default `false`;
  `service_secrets` also acts as the B2 write/rotate kill switch while reveal/
  revoke/prune remain available.
- **Additive foundation migrations** `0049`–`0053` (`SCHEMA.md` §5A documents every shape):
  - `0049` registry/packages/releases/installs/permissions/history/advisories/local-blocks (§8.2 #1–5).
  - `0050` capability registry, 4 protected system-role anchors, role-capability map, scoped/temporary assignments + audit, protected-owner authority (§8.2 #8/#9/#13). Capability catalogue **seeded empty** (taxonomy now recorded — `docs/phase5/capability-taxonomy.md`, ADR 0012; the `0066` seed lands with Foundation F3).
  - `0051` WebAuthn credentials + one-time challenges (§8.2 #14) — public credential material only.
  - `0052` identity-provider registry + the `oauth_identities.provider` **ENUM→VARCHAR(64)** widen + nullable `provider_config_id` linkage; seeds google/apple/github as dark builtin rows + aliases (§8.2 #15).
  - `0053` invitations + redemptions — hash-only tokens, non-privileged onboarding only (§8.2 #16).
- **`SCHEMA.md` v1.20**: §5A for foundation schema, §5B for the B2
  service-secret registry, §3 `api_tokens` (0056) + `webhooks`/`webhook_deliveries` (0057), the B2 hook-registry schema note, table-index rows 55–83, and §9 changelog entries.
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
- **B2 API tokens — sub-project 2 landed** (`0056`,
  `src/Repository/ApiTokenRepository.php`, `src/Service/ApiTokenService.php`,
  `src/Security/ApiScopes.php` / `ApiPrincipal.php`, `src/Controller/Api/*`,
  `src/Controller/AdminApiTokenController.php`): admin-minted, hash-only (`sha256`,
  shown once) scope-gated Bearer tokens behind the deploy-dark `api_tokens` flag
  (a service-level kill switch); reauth-on-mint via `WriteGate` + password,
  expirable, immediately revocable (audited only on a real state change),
  per-token rate-limited; a read-only `/api/v1/me|boards|boards/{id}/threads`
  surface that self-authenticates by Bearer and emits JSON without touching the
  CSRF/HTML kernel; admin management UI with desktop + mobile browser evidence.
- **B2 webhook delivery — sub-project 3 landed** (`0057`,
  `src/Repository/WebhookRepository.php`,
  `src/Repository/WebhookDeliveryRepository.php`,
  `src/Service/WebhookService.php`, `src/Worker/WebhookDeliveryWorker.php`,
  `src/Controller/AdminWebhookController.php`, `src/Service/Webhook/*`,
  `src/Security/EgressGuard.php`, `src/Security/WebhookEvents.php`): endpoint
  config stores only a SecretVault `svcsec_*` reference, signed requests use
  GitHub-style timestamp/body HMAC headers with rotation overlap, deliveries are
  queued idempotently with retry/backoff/dead-letter and circuit-breaker pause,
  outbound HTTP is SSRF-guarded and resolve-then-pinned, and the admin UI can
  register/rotate/pause/test/delete/replay while the feature remains dark by
  default.
- **B2 first-party hook registry + domain producers — sub-project 4 landed**
  (`src/Hook/FirstPartyHookRegistry.php`, `src/Hook/HookEvent.php`,
  `src/Core/App.php`, `src/Service/*` producers): a code-only, in-process
  first-party hook registry behind `first_party_hooks`, with outbound webhooks
  registered as the first listener for the existing webhook event catalogue.
  Producer hooks emit after successful core mutations, fail open on listener
  errors, disable a failing listener for the current registry lifetime, and use
  deterministic event IDs plus IDs/state-only payloads. Public-board topic,
  reply, edit, delete, solved, report, member, ban, and anti-abuse auto-action
  events are wired; `ping` remains admin-test-only. Private/hidden board content
  and DM reports are suppressed until endpoint-level data-class permissions
  exist. No public plugin loading, sandbox, plugin lifecycle schema, or
  third-party PHP execution is included.
- **B2 decomposition recorded** (design:
  `docs/superpowers/specs/2026-06-28-service-secret-registry-design.md`): 1)
  service-secret registry — landed, 2) API tokens + scopes — landed, 3) webhook
  delivery — landed, 4) first-party hook registry + producer wiring — landed.
  No public/untrusted PHP execution is included in these Gate A sub-projects.

## Foundation F3/F5 landed (2026-07-01) — authorization spine, deploy-dark

The Foundation authorization spine landed behind the dark `capabilities` flag;
no live behavior changed and `AppFeatureFlagTest::test_phase5_foundation_flags_default_dark`
still proves every Phase 5 flag dark.

- **F3 — code-owned capability catalogue** (`src/Security/CapabilityCatalog.php`):
  the A1 taxonomy (`docs/phase5/capability-taxonomy.md` §4, ADR 0012) transcribed as a
  pure static enumeration of the **54 `core.*` keys** (scope/risk/delegable/protected/
  description/consent), the 5-key non-delegable protected set (decision #22), and the
  cumulative `system.guest/user/moderator/admin` role maps (**1 / 15 / 28 / 49**).
  Mirrors `ApiScopes`. `CapabilityCatalogTest` pins the count, the
  `risk='protected' ⇔ is_protected ⇔ ¬delegable` invariant, the consent-string rule,
  and the cumulative counts.
- **F3 — capability golden matrix + coverage** (`src/Service/CapabilityInventoryService.php`):
  every non-protected key maps to ≥1 authoritative call-site anchor (taxonomy §4) plus
  the §8 non-capability exclusions; `CapabilityInventoryCoverageTest` enforces
  catalogue⟺matrix parity (A1 enforcement — adding/removing a key without wiring fails CI).
- **F3 — seed migration `0066`** (seed-only, additive, `INSERT IGNORE`): populates the
  empty `0050` `capabilities` catalogue + `role_capabilities` from `CapabilityCatalog`;
  `AppPhase5CapabilitySeedTest` proves the seeded rows match the code and no protected
  capability is ever role-mapped. `SCHEMA.md` → **v1.25** (the `0066` seed); a
  follow-on `0067` owner-lifecycle locking index (`idx_users_role_status_id`)
  landed with the phase-4 graduation on this branch and bumped `SCHEMA.md` to
  **v1.26**.
- **F5 — protected-owner spine** (decision #27, "≥1 active recoverable owner"):
  `0066` backfills `protected_owners` from existing active admins;
  `src/Repository/ProtectedOwnerRepository.php` is the thin single-table wrapper;
  `src/Security/LastOwnerGuard.php` is the shared invariant — **parity-safe**: while the
  owner set is unseeded (dark / fresh install) it defers to the legacy last-active-admin
  rule, and enforces the owner invariant directly once populated;
  `RepairService::repairProtectedOwners()` reconciles the invariant idempotently and is
  surfaced by `bin/console repair`.
- **F5 — wired one owner-loss path** (`src/Service/AccountLifecycleService.php`,
  `src/Core/App.php`): `deactivate()`/`requestDeletion()` consult `LastOwnerGuard` via
  `?->` — the guard is injected **only when `capabilities` is on**, so dark = today's
  legacy behavior unchanged. The four not-yet-built owner-loss paths (role revoke/demote
  Inc 6, passkey removal Inc 7, sole-provider unlink Inc 8, invitations Inc 9) have the
  guard's public API + a documented future hook, not silent omissions.
- **Populated-data proof** (`verify:upgrade`, which the empty-users PHPUnit bootstrap
  cannot show): `0066` applied on a seeded Phase-1 DB → catalogue = 54, role maps
  1/15/28/49, 0 protected keys role-mapped, and the pre-existing `legacy_admin` backfilled
  as an active protected owner; `bin/console repair` designated 1 owner then 0 (idempotent).
- **Adversarial review + fix** (commit `35dcd39`): a post-implementation review of the F5
  owner spine found a reachable fail-open — `ProtectedOwnerRepository` derived owner
  activeness from the write-once `protected_owners.is_active` flag, so a deactivated
  co-owner's stale row still counted as a live owner and the deactivate path returned 303
  instead of 422 for the last recoverable owner. Fixed by deriving activeness from
  `users.status='active'` (JOIN `users`) in the repository reads and the repair check;
  four TDD regression tests (red→green) pin it. Details in
  `docs/evidence/phase5/foundation-f3-f5.md`.

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
- **B2 — RESOLVED in this increment, decomposed.** Sub-projects 1–4 have landed
  deploy-dark — the service-secret registry (behind `service_secrets`), the
  read-only API-token slice (behind `api_tokens`), the webhook delivery engine
  (behind `webhooks`), and the first-party hook registry/domain producers
  (behind `first_party_hooks`). No plugin runtime or public/untrusted PHP
  execution exists.
- **B3 — STORAGE LAYER LANDED, PROVIDER CONSUMERS DEFERRED.** `SecretBox`
  provides app-key-backed AES-256-GCM and `SecretVault` now provides the broader
  service-secret registry for webhook/provider/remote-app credentials. Webhook
  delivery consumes it; provider and remote-app consumers remain deferred.

## Requirement-ledger snapshot (`PHASE_5_PLAN` §11.1)

- **Foundation schema:** R2 (implemented behind disabled flags) + R3-partial
  (shape auto-verified). It is **not** R4/R5 — no behavior, no release evidence.
- **TOTP/recovery:** R3 implementation + focused release evidence. Full suite is
  green; browser/no-JS evidence is still needed before any Gate A acceptance
  package.
- **Service-secret registry:** R3 storage/service implementation + focused release
  evidence. Webhook endpoints consume `svcsec_*` references; provider and
  remote-app consumers remain deferred.
- **API tokens (read-only slice):** R3 implementation + focused release evidence —
  PHPUnit across flag/schema/service/endpoints/admin, plus desktop + mobile browser
  evidence of the admin mint → show-once → revoke flow. Deploy-dark behind
  `api_tokens`; no write surface yet.
- **Webhook delivery + first-party producers:** R3 implementation + focused
  release evidence — PHPUnit across flag/schema/security/transport/repository/
  service/worker/admin, hook registry, and domain producers, plus desktop +
  mobile browser evidence of register → show-once → public topic creation →
  worker delivery log for `topic.created`. Deploy-dark behind `webhooks` and
  `first_party_hooks`.
- **Capabilities / roles + protected-owner spine (Foundation F3/F5):** R2
  (implemented behind the disabled `capabilities` flag) + **R3-partial** — the
  code-owned catalogue (54 keys), the `0066` catalogue/role-map seed, the
  `protected_owners` backfill, `LastOwnerGuard`, and its reconcile are
  implemented with enforcing PHPUnit + `verify:upgrade` evidence, but the
  **capability resolver (P5-08, Increment 1)** and the **remaining four
  owner-loss enforcement paths (Increment 6)** are not yet built, so this is not
  R4/R5. Deploy-dark; no live behavior.
- **All other Phase 5 subsystems (registry, themes, passkeys,
  providers, invitations, sandbox, governance, service principals, verified
  links):** R0/R1 — pending implementation and workstream-specific evidence.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` … `0057_phase5_webhooks.php`.
- Schema doc: `SCHEMA.md` §5A/§5B + §3 `api_tokens`/`webhooks`/`webhook_deliveries` + B2 hook-registry note + §9 changelog (v1.20).
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- Service-secret schema regression: `tests/Integration/Core/AppServiceSecretsSchemaTest.php`.
- Service-secret behavior/redaction regression: `tests/Integration/Service/SecretVaultTest.php`.
- API-token regression (B2 sub-project 2): `tests/Integration/Core/AppApiTokensSchemaTest.php`, `tests/Unit/Security/ApiScopesTest.php`, `tests/Integration/Service/ApiTokenServiceTest.php`, `tests/Integration/Api/ApiReadEndpointsTest.php`, `tests/Integration/Api/AdminApiTokenTest.php`.
- API-token browser evidence: `docs/evidence/browser/{desktop,mobile}/20-admin-api-token-minted.png` + `21-admin-api-token-revoked.png` (admin mint → show-once → revoke via no-JS form posts).
- Webhook regression (B2 sub-project 3): `tests/Integration/Core/AppWebhooksSchemaTest.php`, `tests/Unit/Support/CidrTest.php`, `tests/Unit/Security/WebhookEventsTest.php`, `tests/Unit/Security/EgressGuardTest.php`, `tests/Unit/Service/WebhookSignerTest.php`, `tests/Unit/Service/WebhookTransportTest.php`, `tests/Integration/Repository/WebhookRepositoryTest.php`, `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`, `tests/Integration/Service/WebhookServiceTest.php`, `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`, `tests/Integration/Admin/AdminWebhookTest.php`.
- First-party hook/domain producer regression (B2 sub-project 4): `tests/Unit/Hook/FirstPartyHookRegistryTest.php`, `tests/Integration/Service/DomainWebhookProducerTest.php`.
- Webhook browser evidence: `docs/evidence/browser/{desktop,mobile}/22-admin-webhook-registered.png` + `23-admin-webhook-delivery-log.png` (admin register → show-once signing secret → create public topic → worker delivery log with `topic.created`).
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all 57 migrations on every PHPUnit run; full suite **579 tests / 2190 assertions green**.
- **Browser evidence:** `npm run evidence` → **14/14 Playwright checks green** on desktop + mobile; includes admin API-token and domain webhook no-JS journeys.
- **Worker smoke:** `DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32 MAIL_DRIVER=array php bin/console worker:webhooks` → `delivered=0 retrying=0 dead=0 skipped=0`.
- **Populated-upgrade rehearsal:** `APP_ENV=testing DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force` → **PASS 17/17** (`0049`–`0057` applied on seeded Phase-1 data; `oauth_identities` ENUM→VARCHAR widen included; 90 Phase-1 columns intact; zero data loss).
- **Legacy ledger probe:** scratch `schema_migrations(version, applied_at)` normalized to `schema_migrations(name, applied_at)` and `Migrator::status()` reported `0001_users` as applied (`status-ok columns=name,applied_at`).
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
3. **Passkeys** (P5-11) on top of the TOTP/recovery fallback.
4. **Generic OIDC + provider migration** (P5-12) and **invitations** (P5-13).

## Operating note

Local dev/test DB is the `forum-software-db-1` container (mysql:8.4, host port
**3307**, user/pass `retro`/`retro`). A gitignored `.env` points at it
(`DB_PORT=3307`); the test DB `retroboards_test` and the `retroboards_upgrade_verify`
scratch DB are provisioned with grants for `retro`.
