# Phase 5 Status

**Status:** **Gate A prerequisite work in progress — Milestone 0 decisions accepted for the release train, foundation schema landed, migration ledger reconciled, TOTP/recovery implemented before passkey enforcement, and the first three B2 sub-projects (service-secret registry, read-only API tokens, webhook delivery) landed deploy-dark.** Package, capability, passkey, provider, invitation, sandbox, governance, first-party hook-registry, service-principal, and verified-link behavior remains gated until each workstream has release evidence.
**Last updated:** 2026-06-28
**Branch:** `b2-webhook-delivery`
**Suite:** `./vendor/bin/phpunit` → **568 tests / 2035 assertions, green**. Browser evidence `npm run evidence` → **14/14 Playwright checks, green** across desktop + mobile. Focused Phase 5 prerequisite checks are included: `TotpTest`, `AppMfaTest`, `AppUserSettingsTest`, `AuthControllerTest`, `AppFeatureFlagTest`, `AppPhase5FoundationSchemaTest`, `AppServiceSecretsSchemaTest`, `SecretVaultTest`, the B2 API-token suite (`AppApiTokensSchemaTest`, `ApiScopesTest`, `ApiTokenServiceTest`, `ApiReadEndpointsTest`, `AdminApiTokenTest`), and the B2 webhook-delivery suite (`AppWebhooksSchemaTest`, `WebhookEventsTest`, `EgressGuardTest`, `WebhookSignerTest`, `WebhookTransportTest`, `WebhookRepositoryTest`, `WebhookDeliveryRepositoryTest`, `WebhookServiceTest`, `WebhookDeliveryWorkerTest`, `AdminWebhookTest`).

## What this increment is (and is not)

The first slice was the **safe foundation** for Phase 5: additive, reversible,
**inert** schema and deploy-dark flags so later workstreams have a documented
shape to build on (`PHASE_5_PLAN` §7 Milestone 1). This increment also resolves
the explicit B1 blocker by adding opt-in TOTP/recovery-code behavior before any
passkey enforcement, and lands the first three B2 sub-projects: encrypted
service-secret storage/rotation/revocation/prune as a tested seam for later
provider/webhook consumers, admin-minted, hash-only, scope-gated read-only API
tokens over a `/api/v1` surface, and a deploy-dark outbound webhook delivery
engine with operator admin UI, HMAC signing, durable retry/backoff/dead-letter
ledger, SSRF egress controls, a `worker:webhooks` drainer, and a `ping` test path.

> "Inert schema is not evidence" (DESIGN §13). The `0049`–`0053` foundation
> remains inert and every Phase 5 flag is dark by default. Migration `0054`
> is opt-in account-security behavior used only when a member enrolls in TOTP.
> Migration `0055` is a deploy-dark service seam: tested storage behavior exists,
> and webhook delivery consumes it; provider/remote-app consumers remain deferred. Migration `0056`
> (API tokens) is deploy-dark behind `api_tokens`: the `/api/v1` read surface and
> admin UI exist and are tested, but the flag is off by default.
> Migration `0057` (webhook delivery) is deploy-dark behind `webhooks`: the engine,
> admin UI, queue, worker, and test-event path exist and are tested, but real
> domain-event producer wiring is deferred to B2 sub-project 4.

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
- **`SCHEMA.md` v1.19**: §5A for foundation schema, §5B for the B2
  service-secret registry, §3 `api_tokens` (0056) + `webhooks`/`webhook_deliveries` (0057), table-index rows 55–83, and §9 changelog entries.
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
  default. Real domain-event wiring is deferred to B2 sub-project 4.
- **B2 decomposition recorded** (design:
  `docs/superpowers/specs/2026-06-28-service-secret-registry-design.md`): 1)
  service-secret registry — landed, 2) API tokens + scopes — landed, 3) webhook
  delivery — landed, 4) first-party hook registry. No public/untrusted PHP execution is included in
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
- **B2 — PARTIAL, decomposed.** Sub-projects 1–3 have landed deploy-dark — the
  service-secret registry (behind `service_secrets`), the read-only API-token
  slice (behind `api_tokens`), and the webhook delivery engine (behind
  `webhooks`); sub-project 4 (first-party hook registry and real producer wiring)
  remains Gate A prerequisite work. No plugin runtime or
  public/untrusted PHP execution exists.
- **B3 — STORAGE LAYER LANDED, NOT CONSUMED.** `SecretBox` provides
  app-key-backed AES-256-GCM and `SecretVault` now provides the broader
  service-secret registry for webhook/provider/remote-app credentials. Webhook
  delivery now consumes it; provider and remote-app consumers remain deferred.

## Requirement-ledger snapshot (`PHASE_5_PLAN` §11.1)

- **Foundation schema:** R2 (implemented behind disabled flags) + R3-partial
  (shape auto-verified). It is **not** R4/R5 — no behavior, no release evidence.
- **TOTP/recovery:** R3 implementation + focused release evidence. Full suite is
  green; browser/no-JS evidence is still needed before any Gate A acceptance
  package.
- **Service-secret registry:** R3 storage/service implementation + focused release
  evidence. It has no consumer surface yet, so it is not R4/R5 product behavior.
- **API tokens (read-only slice):** R3 implementation + focused release evidence —
  PHPUnit across flag/schema/service/endpoints/admin, plus desktop + mobile browser
  evidence of the admin mint → show-once → revoke flow. Deploy-dark behind
  `api_tokens`; no write surface yet.
- **Webhook delivery (engine + admin UI):** R3 implementation + focused release
  evidence — PHPUnit across flag/schema/security/transport/repository/service/
  worker/admin plus desktop + mobile browser evidence of register → show-once →
  test event → worker delivery log. Deploy-dark behind `webhooks`; real domain
  event producer wiring is deferred to the first-party hook registry (B2 SP4).
- **All other Phase 5 subsystems (registry, themes, capabilities/roles, passkeys,
  providers, invitations, first-party hook registry,
  sandbox, governance, service principals, verified links):** R0/R1 — pending
  implementation and workstream-specific evidence.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` … `0057_phase5_webhooks.php`.
- Schema doc: `SCHEMA.md` §5A/§5B + §3 `api_tokens`/`webhooks`/`webhook_deliveries` + §9 changelog (v1.19).
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- Service-secret schema regression: `tests/Integration/Core/AppServiceSecretsSchemaTest.php`.
- Service-secret behavior/redaction regression: `tests/Integration/Service/SecretVaultTest.php`.
- API-token regression (B2 sub-project 2): `tests/Integration/Core/AppApiTokensSchemaTest.php`, `tests/Unit/Security/ApiScopesTest.php`, `tests/Integration/Service/ApiTokenServiceTest.php`, `tests/Integration/Api/ApiReadEndpointsTest.php`, `tests/Integration/Api/AdminApiTokenTest.php`.
- API-token browser evidence: `docs/evidence/browser/{desktop,mobile}/20-admin-api-token-minted.png` + `21-admin-api-token-revoked.png` (admin mint → show-once → revoke via no-JS form posts).
- Webhook regression (B2 sub-project 3): `tests/Integration/Core/AppWebhooksSchemaTest.php`, `tests/Unit/Support/CidrTest.php`, `tests/Unit/Security/WebhookEventsTest.php`, `tests/Unit/Security/EgressGuardTest.php`, `tests/Unit/Service/WebhookSignerTest.php`, `tests/Unit/Service/WebhookTransportTest.php`, `tests/Integration/Repository/WebhookRepositoryTest.php`, `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`, `tests/Integration/Service/WebhookServiceTest.php`, `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`, `tests/Integration/Admin/AdminWebhookTest.php`.
- Webhook browser evidence: `docs/evidence/browser/{desktop,mobile}/22-admin-webhook-registered.png` + `23-admin-webhook-delivery-log.png` (admin register → show-once signing secret → test event → worker delivery log).
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all 57 migrations on every PHPUnit run; full suite **568 tests / 2035 assertions green**.
- **Browser evidence:** `npm run evidence` → **14/14 Playwright checks green** on desktop + mobile; includes admin API-token and webhook no-JS journeys.
- **Worker smoke:** `DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32 MAIL_DRIVER=array php bin/console worker:webhooks` → `delivered=0 retrying=0 dead=0 skipped=0`.
- **Populated-upgrade rehearsal:** `APP_ENV=testing DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force` → **PASS 17/17** (`0049`–`0057` applied on seeded Phase-1 data; `oauth_identities` ENUM→VARCHAR widen included; 90 Phase-1 columns intact; zero data loss).
- **Legacy ledger probe:** scratch `schema_migrations(version, applied_at)` normalized to `schema_migrations(name, applied_at)` and `Migrator::status()` reported `0001_users` as applied (`status-ok columns=name,applied_at`).
- **Independent adversarial review:** 5-dimension review (DDL correctness, §8.2 completeness, security invariants, inertness, doc accuracy) with each finding re-verified against source. 5 findings confirmed / 10 rejected; all 5 resolved in this increment — `credential_id` widened to `VARBINARY(1023)` (WebAuthn L2 max), secret/separation invariants locked in the shape test, and two ADR-0004 wording fixes.
- Entry/decision record: `docs/adr/0004-phase-5-entry-and-carryover.md`; carryover source `docs/adr/0003-phase-4-closeout-deferrals.md`.

## Recommended next increments (after Milestone-0 approval)

Per `PHASE_5_PLAN` §13.1 staged order — each behind its dark flag, with shadow/
parity and adversarial evidence before enablement:

1. **Finish trusted extension foundation** (ADR 0004 B2): first-party hook
   registry and real producer wiring on top of the landed service-secret,
   API-token, and webhook-delivery slices. This is a Gate A prerequisite for
   public remote packages and must not expose untrusted PHP.
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
