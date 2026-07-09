# Phase 5 Status

**Status:** **Gate A accepted; Gate B remains reserved - Milestone 0 decisions accepted for the release train, foundation schema landed, migration ledger reconciled, TOTP/recovery implemented before passkey enforcement, all four B2 sub-projects (service-secret registry, read-only API tokens, webhook delivery, first-party hook producers) landed deploy-dark, and the Foundation increment (F1-F11) is COMPLETE.** Increment 1 (P5-08 resolver shadow), Increment 2 (P5-01 registry protocol + package identity), Increment 3 (P5-02 package install/manifest/lifecycle), and Increment 4 (P5-03 declarative theme packages) landed 2026-07-02 behind dark flags. Increment 7 (P5-11 passkeys) landed 2026-07-03 behind the dark `passkeys` flag — enrollment/sign-in/step-up/recovery with CDP browser evidence; GA-DOD-13 at R4; SLICE-TOTP retrofit paid. Increment 5 (P5-04 integration runtime + P5-07-A security-response console) landed 2026-07-03 behind the dark `package_registry` flag — install-scoped settings, secret storage, read-only API tokens, package-owned webhook delivery, publisher trust/key lifecycle, exact-digest review, advisories, and the flag-independent `package_execution_disabled` brake; GA-DOD-08 at R4, GA-DOD-09 at R3, and the four B2 slices (SLICE-API-TOKENS/WEBHOOKS/SERVICE-SECRETS/FIRST-PARTY-HOOKS) paid up to R4 with the SP0 adversarial suites. Inc 4 remains deploy-dark: only reviewed declarative theme packages can affect external CSS, no remote code executes, and integrations remain Inc 5. Increment 6 (P5-08/09 enforcement cutover + assignment lifecycle) landed 2026-07-04–2026-07-05 deploy-dark: AuthorityGate mode lever (CAPABILITIES_MODE, shadow default), per-action keys across the board/content surface plus board-roster POST commands, grant/revoke/renew with grantor ceiling, change-role owner-loss guard, ADR 0016; the shadow soak is a §13.1 enablement step (runbook: docs/runbooks/capabilities.md). Increment 8 (P5-12 provider registry + generic OIDC) landed 2026-07-07 deploy-dark: the D8 verification core (issuer-pinned discovery/JWKS/JwtVerifier/ClaimMapper) through the shared OAuth core, the 0074 identity backfill + 0075 audit widen, the `/admin/providers` console with the TM-ID-09 sole-method disable confirm, TM-ID-01..04 implemented, both oidc D11 rows MEASURED (PASS), and the A2 GitLab.com target as pure configuration (runbook: docs/runbooks/provider_registry.md). Increment 9 (P5-13 invitations) landed 2026-07-08 deploy-dark and merged to `main` via PR #40 (`c72b884`, 2026-07-09): the `/admin/invitations` issue/list/revoke console, atomic redemption through `/register` + `/invite/{token}`, explicit `registration_mode=invite` via `RegistrationPolicy` (fails closed while the `invitations` flag is dark, so the flag doubles as the invitation-pause switch), the `0076` invitation-audit widen, and `invitation.redemption_p95` 461.79 ms MEASURED (PASS) (production-cost Argon2id) (runbook: docs/runbooks/invitations.md). Sandbox, governance, service-principal, and verified-link behavior remains gated until each workstream has release evidence. The WYSIWYG composer stream that shipped alongside Inc 4 (PR #33) graduated on 2026-07-02: `wysiwyg_composer` is now default-ON (`docs/runbooks/wysiwyg_composer.md`); the gate-a browser-evidence seed pins the textarea baseline, and `wysiwyg-composer.spec.ts` proves the GA default with no override.
**Last updated:** 2026-07-09
**Branch:** `main` holds everything through Increment 9 (invitations, PR #40 `c72b884`, merged 2026-07-08/09); Increment 8 (provider registry, PR #39 `0aeda29`) merged 2026-07-07, and the Inc 6 follow-ups merged ff `88267b7`, 2026-07-07
**Suite:** Prior Inc 4 closeout gates were green on 2026-07-02. `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/Phase5EvidenceMapTest.php` -> **7 tests / 33 assertions**. `vendor/bin/phpunit tests/Integration/Core/AppThemePackageTest.php` -> **12 tests / 91 assertions**. Post-fix focused Phase 5/theme group -> **27 tests / 225 assertions**. `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` -> **1268 tests / 6619 assertions**. `vendor/bin/phpunit --no-progress` -> **1268 tests / 6619 assertions** on two consecutive reused-schema runs. Increment 7 focused gates through Task 19 (2026-07-03): passkey PHP regression set (`Phase5BudgetReportServiceTest`, `WebAuthnPolicyTest`, and the three `AppPasskey*` suites) -> **45 tests / 326 assertions**; `ThreatModelIndexTest` + `Phase5EvidenceMapTest` -> **7 tests / 33 assertions**; `tests/browser/passkeys.spec.ts` -> **4 passed** across desktop + mobile. `APP_ENV=testing php bin/console verify:phase5-budgets` -> **registry.signature_verify_p95 0.548 ms MEASURED (PASS); package.install_update_p95 46.1962 ms MEASURED (PASS); theme.build_apply_p95 8.5253 ms MEASURED (PASS); resolver.p95 3.7825 ms MEASURED (PASS); webauthn.ceremony_p95 2.1703 ms MEASURED (PASS); registry.snapshot_freshness CONFIG; registry.fetch_p95 staged-enablement pending**. Task 20 closeout (2026-07-03) is green: `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` -> **1384 tests / 7225 assertions**, and `vendor/bin/phpunit --no-progress` -> **1384 tests / 7225 assertions** (reused-schema); `cd tests/browser && npm run evidence` -> **57 passed / 1 skipped**, `npm run a11y` -> **18 passed**, `npx playwright test passkeys.spec.ts totp.spec.ts` -> **6 passed** across desktop + mobile; `APP_ENV=testing php bin/console verify:phase5-budgets` -> **webauthn.ceremony_p95 2.2343 ms MEASURED (PASS)** against the 2000 ms D11 target (a re-run under concurrent test load transiently flagged the unrelated `resolver.p95` at 6.60 ms, then MEASURED (PASS) at 4.50 ms idle — measurement noise; the resolver is untouched by this branch); `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **17/17 checks passed** (no new migration in Inc 7). Regenerated screenshots and the timing-only budget report were restored (not committed) per the deploy-dark evidence policy. Prior Inc 4 gate: `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **17/17 checks passed** through migration `0072_phase5_theme_packages`. `DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console worker:packages` -> **checked=0 quarantined=0 disabled=0 purged=0 updates=0 skipped=1** while dark. `tests/backup/rehearse.sh` default container mode could not run because Docker is unavailable; documented host mode with `retroboards_test` -> `retroboards_e2e` passed: **109 tables / 290 rows / 179986-byte backup restored byte-for-byte and booted**. Browser evidence from Inc 4: `npm run evidence` -> **53 passed / 1 skipped** and `npm run a11y` -> **14 passed** across desktop + mobile. Increment 5 closeout (2026-07-03): three closeout guards (`ThreatModelIndexTest` + `Phase5EvidenceMapTest` + `MigrationLedgerTest`) -> **12 tests / 53 assertions**; `AppFeatureFlagTest` -> **28 tests / 263 assertions** (every Inc 5 route 404s while `package_registry` is dark); `vendor/bin/phpunit --no-progress` -> **1558 tests / 7946 assertions** on two consecutive runs (identical); `cd tests/browser && npm run evidence:integrations` -> **16 passed** (integration settings/credential reveal, API tokens, webhooks, security console) and `npm run a11y` -> **22 passed / 2 skipped** across desktop + mobile; security-console screenshots captured at `docs/evidence/browser/{desktop,mobile}/60-package-security-console.png` and `61-package-publisher-detail.png`. **Inc 5 closeout-readiness follow-ups (2026-07-03, merged to main `b45d005`):** a 35-agent adversarial audit (`docs/evidence/phase5/inc5-closeout-readiness-audit.md`) confirmed 0 blockers and drove fixes for one content-boundary gap + the actionable minors — anonymous authorship is now masked in every first-party domain-event payload (`WebhookEvents::maskAnonymousAuthor`), `moderation.auto_action` only fires for public boards, credential provisioning fails closed with a 422 (not a 500) when `api_tokens` is dark, the integration settings 422 preserves typed edits, and SecretVault store/rotate-blocked-vs-reveal/revoke/prune/usableSecrets-work asymmetry is now regression-pinned. `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` -> **1573 tests / 8002 assertions** (1559 baseline + 14 new). `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **17/17 checks passed** through migration `0073_phase5_package_integrations` (the previously-unrecorded Inc 5 migration rehearsal). Five hardening nits (WriteGate on suspend/export, brake-predicate DRY, GCM AAD, inert crypto-agility columns, obfuscated-IP egress corpus) are recorded as deferred in the audit doc. **Increment 6 closeout (2026-07-05):** Task 14's 3× fresh+reused full-suite gate (commit `684210f`) -> **1633 tests / 8276 assertions**, all green, identical across both reused-schema runs; Task 15 added further focused unit coverage on top (budget generator + sampler tests) -> full **unit** suite **357 tests / 1817 assertions**. `php bin/console verify:resolver-parity` -> **1551 tuples, 1551 agreed, 0 mismatches** (re-run, same corpus). `APP_ENV=testing php bin/console verify:phase5-budgets` -> **resolver.p95 ~0.97 ms MEASURED (PASS)** against the 5 ms D11 target (re-measured multiple times across follow-up work: 0.94-1.7 ms observed, always comfortably under budget), plus two new measured-only (no D11 target) rows for assignment-change propagation and simulator duration, both durable in the generator behind an opt-in flag. `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **17/17 checks passed** (no new migration — still through `0073`). `CAPABILITIES_MODE=enforce RB_BROWSER_DARK_SURFACES=1 npx playwright test role-assignments.spec.ts` -> **2 passed** (desktop + mobile), axe-clean on both the active- and revoked-assignment states; 6 new PNGs (`62`/`63`/`64` × desktop/mobile). Fallback-lever rehearsal run live on the local dev instance: both `CAPABILITIES_MODE=shadow` and `features.capabilities=false` verified to revert live behavior exactly as documented (`docs/evidence/phase5/capabilities-fallback-rehearsal.md`). Closeout guards `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/MigrationLedgerTest.php` -> **12 tests / 53 assertions**, all green (validates the ledger edits below, the threat-model dossier states, and the migration ledger). `GA-DOD-10`/`GA-DOD-11` -> **R4**, `GA-DOD-12` -> **R3**. **Task 18 final verification sweep (2026-07-05, commit `4339e5f`):** three full-suite runs (one fresh drop+remigrate via `RB_TEST_FRESH=1`, two reused-schema) -> **1638 tests / 8320 assertions**, identical across all three (determinism confirmed — the increase over Task 14's 1633/8276 reflects the intervening Task 15-17 coverage additions). `php bin/console verify:resolver-parity` -> **1551 tuples, 1551 agreed, 0 mismatches**. `APP_ENV=testing php bin/console verify:phase5-budgets` -> **resolver.p95 1.6104 ms MEASURED (PASS)** against the 5 ms D11 target (baseline 4.8926 ms legacy). `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **17/17 checks passed** (no new migration — still through `0073`). `CAPABILITIES_MODE=enforce RB_BROWSER_DARK_SURFACES=1 npx playwright test role-assignments.spec.ts` -> **2 passed** (desktop + mobile). `cd tests/browser && npm run evidence` -> **66 passed / 1 failed / 1 skipped** (the failure is the pre-existing `gate-a.spec.ts` badge-rules-preview issue introduced by commit `d9d07e71`, predating Inc 6 — not chased, per instruction). `npm run a11y` -> **26 passed / 2 skipped**. Regenerated browser-evidence PNGs and the timing-only parity/budget reports were restored, not committed, per the deploy-dark evidence policy. Branch `phase5-inc6-enforcement` was **merged to `main` via PR #38** (merge `1a89cc8`, 2026-07-05) and the branch/worktree removed. **Increment 8 gates (2026-07-07, branch `phase5-inc8-provider-registry`):** `RB_TEST_FRESH=1 vendor/bin/phpunit` + two reused-schema runs -> **1760 tests / 9022 assertions**, identical ×3; `verify:resolver-parity` -> **1551/1551, 0 mismatches**; `verify:upgrade` -> **17/17 through 0075**; `verify:phase5-budgets` -> **oidc.discovery_p95_cached 0.7275 ms + oidc.discovery_p95_cold 1.2766 ms MEASURED (PASS)**; `npm run evidence` (now incl. `providers.spec.ts`) -> **68 passed / 1 failed (pre-existing gate-a badge-rules mobile) / 1 skipped**; `npm run a11y` -> **26 passed / 2 skipped** (full detail in "Increment 8 landed").

**P5-16 closeout suite (2026-07-09):** `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` -> **1831 tests / 9396 assertions**, with two reused-schema passes at the same count; focused route-permission matrix -> **240 tests / 2139 assertions**; all-flags-off filter -> **2 tests / 29 assertions**; package-execution-disabled pins -> **25 tests / 93 assertions**; browser evidence -> **71 passed / 1 skipped**, passkeys -> **6 passed**, integrations -> **16 passed**, packages -> **2 passed**, a11y -> **26 passed / 2 skipped**; migration/runbook rehearsal -> clean install, rollback/reapply, and `verify:upgrade` through `0076` all passed; backup/restore -> **114 tables / 310 rows** restored and verified; resolver parity -> **1551/1551, 0 mismatches**; Gate A performance budgets re-measured green; security regression -> **330 tests / 2150 assertions**.

## Phase 5 review follow-ups (2026-07-04, merged to main as part of the Inc 6 PR base)

A comprehensive Phase 5 review (9 adversarial subagents across the security-critical
surfaces, plus independent verification of the crown-jewel code and the full suite)
found **no Critical and no live-High** defects: the WebAuthn verifier, Ed25519 trust
chain, AES-256-GCM vault, SSRF resolve-then-pin, declarative theme sandbox, deploy-dark
discipline, and migrations `0049`–`0073` are all correct and fail-closed, and Gate A is
correctly **not** claimed done. The review's actionable findings are fixed here (TDD,
red→green); the full suite is **1585 tests / 8070 assertions** green (1573 baseline + 12 new).

- **A1–A3 (LIVE) — MFA hardening** (`f915269`): TOTP second-factor guessing was bounded
  only per challenge token, so an attacker with the password could brute-force TOTP with
  unlimited fresh 5-guess budgets, no lockout, silently. Now a per-account
  `mfa_account [8,900]` throttle bounds it across tokens/IPs, failed second factors are
  audited (`mfa_login_failed`), the TOTP settings + password-change endpoints carry the
  `mfa_settings` limiter, and enabling/disabling TOTP revokes other sessions.
- **E1 (latent, `capabilities`) — resolver fail-open** (`49db9a8`):
  `CapabilityRules::scopeSatisfies` returned true for a board/category-scoped grant with
  no target in context; now fails closed (site-scoped grants still hold). Must land before
  the Inc 6 enforcement cutover; the parity corpus never exercised it (always supplies a
  board id), so a unit case pins it.
- **C1/C2 (latent, egress) — SSRF** (`f737a67`): added the missing IPv6 DENY ranges
  (`::/96`, `2002::/16`, `64:ff9b::/96`) and made the guard ignore a `/0` allowlist entry.
  One fix for every `EgressGuard` consumer (webhooks, link previews, registry).
- **F1 (latent, `api_tokens`)** (`7191c96`): API-token auth now refuses a token whose owner
  is banned/deactivated/deleted (suspended may still read) — pre-positioned before any
  write/PII scope is added.
- **D1/G1/G2 (latent, `package_registry`)** (`65203bd`): advisory ingest refuses a stale
  (older `issued_at`) replay; the theme asset scanner caps pixel dimensions before the GD
  decode; the publisher key-revoke flash no longer overstates its effect.

**Deferred (owner decision / lower priority):**
- **G1 deep cascade** — retroactively force-disabling a compromised publisher's installed
  packages on *key* revoke needs a semantics decision (per-release key linkage vs "no usable
  signing key ⇒ fail closed") before `package_registry` graduates. Today "suspend publisher"
  is the cascading control (the flash now says so).
- **B3 — `APP_KEY` rotation** currently makes every stored SecretVault/TOTP secret
  undecryptable (fail-closed 500, never wrong plaintext); the inert `cipher`/`key_version`
  columns need a key-version migration path documented in the ops runbook before
  `service_secrets` graduates.
- **I4 — evidence reproducibility**: plain `npm run evidence` skips the Phase 5
  dark-surface specs (they `test.skip()` unless `RB_BROWSER_DARK_SURFACES=1`); run the
  targeted `evidence:*` scripts for dark-surface PNGs.

## Gate A entry-gate artifacts (recorded 2026-06-30; accepted 2026-07-01)

The remaining `PHASE_5_PLAN.md` §2 entry-gate artifacts are recorded; **ADR 0012**
(`docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md`) carries the gate record and
the owner sign-offs (received 2026-06-30), **accepted 2026-07-01** on the owner's
final acceptance pass. The acceptance is recorded inline in that same **ADR 0012** — there is no separate acceptance ADR (`docs/adr/0013` is the WYSIWYG composer ADR).

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
executable TDD plan — the next step. A2 (first named OIDC provider) is **recorded** —
GitLab.com, accepted by the owner 2026-07-02 (`docs/phase5/first-oidc-provider.md`);
P5-12/Inc 8 is no longer decision-blocked.

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
  `docs/superpowers/plans/2026-06-28-service-secret-registry.md`): 1)
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

## Increment 1 landed (2026-07-02) — P5-08 resolver shadow, deploy-dark

The capability resolver shadow increment is complete behind the dark `capabilities`
flag. Live authorization remains legacy; the new resolver is used for parity
measurement, shadow comparison, role definition workflows, and the permission
simulator.

- **Resolver core + projection:** `CapabilityRules` implements union-then-narrow
  decisions, temporal assignment windows, protected-key fail-dark behavior, and
  board/self/site/category scoping. `LegacyAuthorityProjection` maps accepted
  `users.role`, `board_moderators`, `post_min_role`, and `protected_owners`
  authority into resolver grants without changing live behavior.
- **Shadow harness:** `ResolverShadow` is wired only when `capabilities` is on and
  compares `canModerate`/`canPost` decisions fail-open; mismatches emit telemetry
  without changing the legacy answer.
- **Parity corpus:** `php bin/console verify:resolver-parity` archived
  `docs/evidence/phase5/resolver-parity.md` on fixture `phase5_fixture_v2` (adds a
  suspended global moderator, a plain-user board moderator, and an archived board
  after the Inc 1 review): **1551 tuples, 1551 agreed, 0 mismatches**. Two known
  legacy divergences are recorded, not modeled (capability-taxonomy.md §7 #5-#6):
  pending-view state gate and service-owned content-state closes — owner decision
  due at the Inc 6 cutover.
- **Role editor + simulator:** no-JS admin routes are flag-gated and noindexed.
  `RoleService` creates/updates/clones custom roles with reauth, audit, protected
  anchor guards, `roles.version` bumps, and active-assignment impact counts.
  `PermissionSimulatorService` runs the real resolver and redacts target board
  labels from viewers without read access.
- **Performance:** `docs/evidence/phase5/performance-budgets.md` records
  `resolver.p95` as **MEASURED (PASS)** at 2.1204 ms vs the 5 ms D11 target.
- **Browser/a11y:** desktop + mobile evidence landed for role creation and the
  simulator (`30-admin-role-created.png`, `31-admin-role-simulator.png`), and axe
  found no serious/critical violations on `/admin/roles` or
  `/admin/roles/simulator`.

## Increment 2 landed (2026-07-02) - P5-01 registry protocol, deploy-dark

The registry protocol and package identity increment is complete behind the dark
`package_registry` flag. Staff can browse signed metadata and operate trust
response controls; package install, manifest validation, lifecycle, and runtime
behavior remain absent until Inc 3.

- **Trust-chain verifier:** `TrustChainVerifier` verifies detached Ed25519
  signatures over exact JSON bytes, fail-closed, public-key-only, with explicit
  refusal codes for bad signatures, unknown/revoked/windowed keys, malformed
  documents, wrong formats, and forged rotations. TM-SC-01 and TM-SC-03 point to
  `tests/Unit/Security/Registry/TrustChainVerifierTest.php`.
- **Snapshots + package identity:** `RegistrySnapshotService` ingests signed,
  expiring snapshots with anti-replay, stale/offline cache behavior, source
  pinning, uid-conflict refusal, immutable release digests, local trust-class
  rejection, and core-version compatibility badges. TM-SC-02 and TM-SC-05 point
  to `tests/Integration/Service/RegistrySnapshotServiceTest.php`.
- **Rotation, revocation, advisories, and blocklist:** the admin trust console
  pins public keys, applies signed rotations, revokes keys, ingests advisories,
  acknowledges advisories, toggles a registry source, and manages the
  registry-independent local blocklist. TM-SC-04 points to
  `tests/Integration/Core/AppRegistryAdminTest.php`.
- **Refresh worker:** `php bin/console worker:registry-refresh` fetches the
  snapshot/advisory envelopes through `EgressGuard`, applies verification, emits
  telemetry, and no-ops while `package_registry` is dark.
- **Read-only browse:** `/admin/packages` and `/admin/registries` are staff-only,
  noindexed, server-rendered/no-JS surfaces. They intentionally show that
  **install does not exist yet**.
- **Evidence:** protocol contract `docs/phase5/registry-protocol.md`, operator
  runbook `docs/runbooks/package_registry.md`, browser evidence
  `docs/evidence/browser/{desktop,mobile}/32-admin-package-catalogue.png`,
  `33-admin-package-detail.png`, `34-admin-registry-trust.png`, and axe coverage
  in `tests/browser/a11y.spec.ts`.
- **Performance:** `docs/evidence/phase5/performance-budgets.md` records
  `registry.signature_verify_p95` as **MEASURED (PASS)**, snapshot freshness as
  **CONFIG**, and `registry.fetch_p95` as staged-enablement pending.

## Increment 3 landed (2026-07-02) - P5-02 package lifecycle, deploy-dark

The package install and lifecycle increment is complete behind the dark
`package_registry` flag. Staff can verify a signed release, install, consent,
enable, disable, update, rollback, uninstall, export, and re-verify package
state. Theme packages now have the Inc 4 declarative CSS runtime behind
`package_themes`; non-theme integrations still execute nothing until Inc 5.
Publisher console and credential minting remain Inc 5.

- **Release acquisition + digest binding:** `PackageAcquisitionService` treats
  the signed `rb-release.v1` document as the artifact. The snapshot-pinned
  digest is `sha256` over exact signed bytes, review approval is inside those
  bytes, `source_url` must be same-origin with the registry, and release-review
  evidence is cached from the exact envelope. TM-SC-09 is implemented by
  `tests/Integration/Service/PackageAcquisitionServiceTest.php`.
- **Manifest v2 + permission vocabulary:** `ManifestValidator` validates strict
  `rb-manifest.v2`, package type, core range, settings schema, storage quota,
  retention, support links, and capability/data/api/event/outbound-host/job
  declarations. `PermissionDiff` produces added/removed/unchanged consent
  summaries with risk labels.
- **Lifecycle services:** `PackageLifecycleService` and `PackageUpdateService`
  implement validate-first install/consent/enable/disable, manual/notify update
  policy, staged update re-consent, reduction-immediate updates, pinning,
  verified-digest rollback, uninstall with disable-first + export snapshot +
  retention, and quarantine re-verify. Password reauthentication is required for
  install, consent, enable, update, rollback, and uninstall; disable, pin,
  export, cancel staged update, update-policy changes, and re-verify are
  deliberately low-friction.
- **Security gate + worker enforcement:** `PackageSecurityGate` refuses blocked,
  revoked, unreviewed, unsupported, locally blocked, and wrong-trust releases.
  `worker:packages` verifies cached bytes, quarantines tamper, enforces
  advisory/blocklist force-disable, cancels blocked staged updates, reports
  notify-policy updates, and purges retained uninstalls. TM-SC-06 and TM-SC-07
  are implemented by package lifecycle and health-worker tests.
- **Admin surface + evidence:** `/admin/packages` is still staff-only,
  noindexed, and no-JS. Browser evidence now includes shots 35-38 for install
  plan, consent, enabled detail, and update diff on desktop + mobile; `npm run
  evidence` and `npm run a11y` refreshed the package lifecycle coverage.
- **Performance:** `docs/evidence/phase5/performance-budgets.md` records
  `package.install_update_p95` as **MEASURED (PASS)** at 47.1601 ms on Inc 3
  install/update samples against the 10000 ms D11 budget.
- **Docs and ledger:** `docs/phase5/registry-protocol.md` now covers release
  documents, artifact cache layout, manifest v2, refusal codes, and worker
  enforcement. `docs/runbooks/package_registry.md` now covers install/update/
  rollback/uninstall/export/quarantine/emergency operation. GA-DOD-05 and
  GA-DOD-06 are R3; GA-DOD-09 is R2 for install-side exact-digest enforcement
  while publisher-console review remains Inc 5.

## Increment 4 landed (2026-07-02) - P5-03 declarative theme packages, deploy-dark

The declarative theme runtime is complete behind the dark `package_themes` flag.
Only enabled, reviewed `theme` packages can produce CSS; packages still execute
no PHP or JavaScript, and integrations remain Inc 5.

- **Manifest and token policy:** theme packages must include a strict `theme`
  manifest block, non-theme packages must not, and `ThemeTokenPolicy` enforces a
  closed allowlist of CSS custom properties, value grammars, asset references,
  and contrast pairs. TM-TH-01 and TM-TH-02 point to
  `ThemeTokenPolicyTest` and `ThemeBuildCssTest`.
- **Asset scanning and deterministic builds:** `ThemeAssetScanner` accepts only
  bounded raster assets, re-encodes them through GD, verifies declared digests,
  and rejects SVG/HTML/script-capable media. `ThemeBuildService` emits
  deterministic CSS, stores a `css_digest`, and serves only active package
  assets through digest-addressed immutable routes. TM-TH-03 and TM-TH-04 point
  to `ThemeAssetScannerTest` and `ThemeBuildServiceTest`.
- **Admin state, preview, and safe mode:** `/admin/themes` is staff-only,
  noindexed, and no-JS. Preview is isolated to the admin session, activation and
  rollback require password reauth, LKG rollback serves exact prior bytes, and
  `/admin/themes/safe-mode` remains flag-independent for emergency recovery.
  Safe mode, including `THEME_SAFE_MODE`, suppresses active CSS, preview CSS, and
  theme assets fail-dark. TM-TH-05, TM-TH-06, and TM-TH-07 point to
  `AppThemePackageTest`.
- **Lifecycle, repair, and evidence:** health-worker/quarantine/advisory
  enforcement deactivates or rolls back active themes fail-safe, `bin/console repair`
  clears invalid theme state, `theme.build_apply_p95` measured
  **11.3736 ms MEASURED (PASS)** against the 10000 ms D11 budget, and browser
  evidence covers preview, activate, safe mode, and rollback shots 39-42 on
  desktop + mobile. Post-fix focused browser rerun
  `npx playwright test gate-a.spec.ts --grep "theme packages"` -> **2 passed**.
- **Docs and ledger:** `docs/phase5/registry-protocol.md` documents the Inc 4
  theme manifest/build/serving contract, `docs/runbooks/package_themes.md`
  covers staged rollout and emergency operation, and GA-DOD-07 is R3 in
  `docs/phase5/requirement-ledger.json`.

## Increment 7 landed (2026-07-03) - P5-11 passkeys, deploy-dark

The passkey increment is complete behind the dark `passkeys` flag. Members can
enroll, name, list, rename, revoke, use for sign-in, and use passkeys for
fresh-factor step-up once the flag is enabled. Password, OAuth, TOTP, and
recovery-code fallback remain independent of the flag.

- **Protocol core:** `src/Security/WebAuthn/*` implements strict base64url,
  CBOR/COSE, authenticatorData/clientData parsing, ES256 and RS256 verification,
  origin/RP-ID checks from config, production HTTPS refusal, UP/UV policy, and
  coded fail-closed exceptions. Attestation is parsed but not trusted.
- **Stateful ceremonies:** `PasskeyService` uses the already-landed `0051`
  WebAuthn tables for one-time session-bound challenges, public-key credential
  storage, duplicate refusal, counter bookkeeping, audit, telemetry, and
  security notification mail. Add/revoke require a present factor (password or
  passkey step-up), revoke and add revoke parallel sessions, and rename remains
  session+CSRF metadata only.
- **Sign-in and recovery:** email-first passkey login uses fixed-shape decoys for
  unknown/passkeyless accounts, refuses replay/cross-account/altered assertions,
  treats counter anomalies as risk signals rather than lockouts, and requires UV
  when TOTP is enrolled. `AppPasskeyRecoveryTest` proves password/TOTP/recovery
  journeys still work with passkeys enrolled and that account export contains
  passkey metadata only.
- **Browser, threat-model, and budget evidence:** `tests/browser/passkeys.spec.ts`
  drives Chromium's CDP virtual authenticator on desktop+mobile and includes an
  axe scan of the panel. TM-ID-05..09 are `implemented` with real test paths, and
  GA-DOD-13 is R4 in `docs/phase5/requirement-ledger.json`.
  `webauthn.ceremony_p95` is **2.1703 ms MEASURED (PASS)** against the 2000 ms
  D11 budget.
- **Docs and handoff:** `docs/evidence/phase5/passkeys.md` indexes the Gate A
  evidence, `docs/runbooks/passkeys.md` covers rollback, staged rollout,
  lost-authenticator recovery, counter-anomaly review, and RP-ID/domain changes.
  Inc 8 handoff: before an OAuth provider-disable UI ships, wire
  `OAuthIdentityRepository::soleMethodAccounts(string $provider)` into the
  operator flow so sole-method accounts are listed before disablement.

## Decisions recorded with Inc 7

These implementation decisions refine the approved artifacts and are surfaced
for owner acknowledgment, especially the A5 RP-ID refinement:

1. **RP-ID resolution.** True eTLD+1 derivation requires the Public Suffix List — a dependency the no-library posture excludes, and a naive "last two labels" rule would derive the public suffix `co.uk` for `*.co.uk` operators (browsers then refuse every ceremony). Implementation: optional env `WEBAUTHN_RP_ID` (validated: equal to, or a dot-suffix of, the `APP_URL` host), **default = the full `APP_URL` host**. The full host is always a valid, strictly-narrower RP ID; operators who want A5 §2 subdomain portability set `WEBAUTHN_RP_ID` to their registrable domain (documented in the runbook + `.env.example`). Browsers enforce the PSL boundary client-side.
2. **Algorithm allowlist:** ES256 (−7, mandatory per program plan) + RS256 (−257, for Windows Hello-era authenticators). Everything else refuses with `unsupported_algorithm`.
3. **User-verification policy:** ceremonies request `userVerification:'preferred'`; the server **requires** UV for `step_up`; for `login`, UV is required iff the account has TOTP enrolled (see #4); `register` records the UV bit as reported. UP is always required.
4. **TOTP × passkey sign-in:** a UV-verified passkey assertion is multi-factor by itself and signs the user straight in (no TOTP interstitial). If the account has TOTP enrolled and the assertion lacks UV, the sign-in refuses with guidance ("use a passkey with a screen lock, or sign in with your password and code") — this avoids splicing a JSON ceremony into the HTML interstitial and never weakens a TOTP-enrolled account.
5. **Fresh-factor scope:** credential **add** and **revoke** require a present factor (password or passkey step-up assertion); **rename** requires only session + CSRF.
6. **TM-ID-09 clause 2** ("provider disable lists sole-method accounts"): no provider-disable surface exists until Inc 8. This increment ships the tested capability `OAuthIdentityRepository::soleMethodAccounts(string $provider)` and records an explicit Inc 8 handoff (wire it into the provider-disable UI) in PHASE_5_STATUS; the fixture flips to `implemented` on the strength of the removal-block + detector tests.
7. **No `0074`, no privileged-MFA policy scaffolding, no enrollment-audience config.** The §13.1 step-9 staff pilot is an operational procedure (enable the flag for the pilot window; runbook documents it), not a code gate.
8. **`ext-openssl`** is added to `composer.json` `require` (it was undeclared; ES256/RS256 verification now depends on it).

## Increment 6 landed (2026-07-05) - P5-08/09 resolver enforcement cutover + assignment lifecycle, deploy-dark

The enforcement cutover is complete behind the dark `capabilities` flag, gated
further by the `CAPABILITIES_MODE` posture lever. Merged to `main` via PR #38
(merge `1a89cc8`, 2026-07-05). Full decisions recorded in **ADR 0016**
(`docs/adr/0016-inc6-enforcement-cutover-decisions.md`).

- **Seam and mode:** `src/Security/AuthorityGate.php` is the single decision
  choke point — `legacy` (flag off, resolver never constructed), `shadow`
  (flag on, `CAPABILITIES_MODE=shadow` default — Inc 1 behavior, relocated),
  `enforce` (flag on, `CAPABILITIES_MODE=enforce` — the resolver decides and
  **fails closed** on any resolver error). Posture lives in config
  (`capabilities.mode`, env `CAPABILITIES_MODE`), not the flag, mirroring the
  `antiabuse.mode` precedent.
- **Cutover surface:** ~30 call sites across `ModerationService`
  (delete/restore/lock/pin/move/reveal-author/split-merge), the posting floor,
  the three dual-path services (solved-answer, polls, thread workflow), the
  two quirk-key sites (`core.user.warn`, `core.content.view_pending`), the
  approval/report/appeal/community-memory services, plus the four
  board-roster POST commands (`assign/unassign-moderator`,
  `add/remove-member`). The `/admin` console (~19 controllers on bare
  `requireAdmin()`) stays legacy — a recorded follow-up bridged by the
  `EnforcedCapabilities` honesty clamp. **Per-button moderation display
  flags** (owner-approved scope addition, Task 4b): the moderation toolbar
  now computes one display flag per action (`can_pin`, `can_lock`,
  `can_split_merge`, `can_delete_posts`, re-keyed `can_curate_memory`/
  `can_edit_tags`) instead of one coarse `can_moderate_board` flag, so a
  custom role's per-action grant actually renders its own control — proven by
  `docs/evidence/browser/{desktop,mobile}/64-deputy-sees-lock-control.png`
  (a lock-only deputy sees Lock and nothing else).
- **The clamp:** `src/Security/EnforcedCapabilities.php` lists the 21 of 54
  catalogued keys with a live route after this increment;
  `RoleService::validateDefinition` refuses any custom role holding a key
  outside it. ADR 0016 records that route-enforcement and meaningful
  custom-role delegability are separate axes — ~15 of the 21 are genuinely
  custom-delegable; the 3 posting keys and 3 dual-path keys pass the clamp but
  give a custom role no more than it already has (baseline) or only the owner
  half (dual-path, restricted to `system.moderator`/`system.admin` role-kind
  by `CapabilityRules::DUAL_PATH_BOARD_AUTHORITY`).
- **Assignment lifecycle:** `src/Service/RoleAssignmentService.php`
  (custom roles only) — grant (reauth + grantor-ceiling check, TM-PE-02),
  revoke (fast, no reauth, narrowing-only), renew (reauth), all
  transactional with audit + telemetry + `CapabilityResolver::invalidate()`.
  No-JS admin UI at `/admin/roles/{id}` (assignments section) +
  `/admin/role-assignments/{id}/revoke|renew`, all `requireAdmin()` +
  flag-gated 404-dark. **Recorded scope boundary:** `revoke`/`renew` have no
  per-assignment authority ceiling (only `WriteGate`) — safe today because all
  three routes are admin-only; a symmetric ceiling model is required before
  any future increment delegates role-management itself to a non-admin.
- **Change-role action:** `UserModerationService::changeRole()`
  (`POST /admin/users/{id}/role`, **flag-independent** — confirmed reachable,
  422 not 404, with `capabilities` dark in the fallback rehearsal). Demoting
  an admin runs `LastOwnerGuard::assertNotLastOwnerForUpdate` in the same
  transaction as the role write, the `protected_owners` deactivation, and full
  session revocation for the target (TM-PE-07).
- **Evidence:** full suite ~1633+ tests (Task 14's 3× fresh+reused gate at
  commit `684210f` measured 1633 tests / 8276 assertions; Task 15 added
  further unit coverage on top). Parity re-run: **1551 tuples, 1551 agreed, 0
  mismatches** (`docs/evidence/phase5/resolver-parity.md`). `resolver.p95`
  **MEASURED (PASS)** at ~0.97 ms against the 5 ms D11 target (re-measured
  multiple times across follow-up work, consistently well under budget, even
  faster than the legacy read path it replaces in-sample). Two additional
  measured-only (no D11 target) rows: `role_assignment.change_propagation_p95`
  and `permission_simulator.duration_p95`, both durable in the budget
  generator. `verify:upgrade` **17/17** (no new migration — still through
  `0073`). Browser/a11y: `tests/browser/role-assignments.spec.ts` — 2 passed
  (desktop+mobile), axe-clean on both the active- and revoked-assignment
  states; 6 PNGs (`62`/`63`/`64` × desktop/mobile). Fallback rehearsal
  transcript: `docs/evidence/phase5/capabilities-fallback-rehearsal.md` (both
  rollback levers verified live on the dev instance). `GA-DOD-10` -> **R4**,
  `GA-DOD-11` -> **R4**, `GA-DOD-12` -> **R3**
  (`docs/phase5/requirement-ledger.json`). Runbook:
  `docs/runbooks/capabilities.md`.
- **Deferred (recorded, not silent):** the remaining `/admin` console
  cutover (largest source of clamped keys); a symmetric revoke/renew
  authority ceiling (required before role-management itself is delegated to a
  non-admin); an expiry sweeper/notification worker (decision-time expiry
  enforcement was judged sufficient for Gate A — no gap, a recorded
  non-goal); and any legacy-assignment import (the projection stays
  authoritative by design — "no migration ships" is permanent for Gate A, not
  a stopgap).

### Pre-merge review hardening (2026-07-05, merged with PR #38)

A max-effort multi-agent review of PR #38 (10 finder angles → adversarial
verify → sweep, 15 findings) surfaced no Critical and one authority-broadening
High; nine findings were fixed on the branch under TDD (each red-first), the
rest recorded below. Full suite after the fixes: **1649 tests / 8349
assertions** green (11 new regression tests). Fixes:

- **S1 (High) — pending-thread view broadening.** Under `enforce`, the
  held-thread gate keyed on `core.content.view_pending`, which the legacy
  projection grants *site-wide* to every `role='moderator'` user (to mirror the
  bare `isModerator()` site probes at `/mod/approvals` and held media), so a
  vestigial global moderator with **no** board assignment could open held
  threads they never could pre-cutover (404→200) — a delta ADR 0016 does not
  record and taxonomy quirk 3 forbids. Fix: `ThreadController` decides the
  held-thread gate with the board-scoped coarse `canModerate($user,$boardId)`
  (its exact pre-cutover check), so the site-wide projection grant no longer
  satisfies it; author / assigned board-mod / admin are unaffected. Also
  removes a per-view shadow-mismatch source.
- **V1 (live while dark) — roster board-id existence oracle.** The four
  board-roster POST commands ran `boardOrFail()` (404) before authorization, so
  a non-admin saw 404-for-missing vs 403-for-existing — enumerating hidden and
  private board ids, live even with the flag off. Fix: authorize in
  `AdminService` (which already gates before its own `boardOrFail`) *before* the
  controller resolves the row; a non-admin now gets a uniform 403.
- **V8 (live while dark) — change-role reauth trimmed the password.**
  `AdminUserController::changeRole` read the reauth password via `str()`
  (trims), the only reauth site to do so; a legitimately space-edged password
  could never pass. Fix: raw `post()`, matching every other reauth site.
- **S3 — duplicate active assignment.** `grant()` had no duplicate guard and
  `role_assignments` has no unique key, so a double-clicked assign minted two
  identical active grants; revoking one left the twin silently authorizing. Fix:
  `RoleAssignmentRepository::findActiveDuplicate` pre-check in `grant()` (mirrors
  `addMember`'s guard). *Residual:* a true cross-connection double-submit can
  still race the read — a DB partial-unique guarantee is a follow-up.
- **S2 — renew ignored the row's start.** Renewing a scheduled assignment to an
  expiry before its own `starts_at` succeeded, minting an ends≤starts window
  that can never activate. Fix: `renew()` cross-checks the new expiry against
  the row's `starts_at`.
- **V5 — renew error swallowed on a concurrently-revoked row.** The renew error
  paragraphs sat inside the `status!=='revoked'` guard, so the "Revoked
  assignments cannot be renewed" error (which only fires when the row *is*
  revoked) never rendered. Fix: the row's error block renders outside that
  guard.
- **V4 — category scope mislabelled.** The assignments table resolved every
  `scope_id` through the board-name map, so a category-scoped grant showed an
  unrelated board's name. Fix: a category-name map, branched on `scope_type`
  (controller now passes `categories`).
- **V9 — history reason always NULL.** `logHistory()` never set the top-level
  `reason`, so `role_assignment_history.reason` stayed NULL for every lifecycle
  event (the value survived only inside `after_json`). Fix: thread `reason`
  through to the dedicated column for grant/revoke.
- **V3 — mark-solved phantom control.** The accept/clear-answer display flag
  keyed on the coarse `can_moderate_board` (`core.post.delete_any`) while the
  write path enforces `core.thread.mark_solved`, so a `delete_any`-only deputy
  saw an Accept control that always 403'd. Fix: the moderator arm keys on
  `core.thread.mark_solved` (matching `SolvedAnswerService::authorize`); the now
  fully-unused `can_moderate_board` view flag was dropped. Also removes a
  shadow-mismatch source.

**Review follow-ups (recorded, not fixed here — all six resolved 2026-07-07
on `phase5-inc6-followups`; see "Inc 6 follow-ups resolved" below. The list
is kept verbatim as the original record):**

- **V7 (medium) — `canEditTags` telemetry noise.** The moderator arm pairs the
  staff-only legacy closure with the user-baseline key `core.thread.tag`, so
  under shadow/enforce almost every ordinary-member thread view emits a
  `resolver.*_mismatch` — a class the runbook calls a stop-the-line parity bug,
  making a clean shadow soak unreachable. Deferred because a correct fix is a
  design question (whether staff may tag on boards they cannot post in) that
  risks changing tag-edit authority; needs its own pass plus a parity-oracle
  update. Live outcome is unchanged in all modes (the OR with the member arm).
- **Queue discovery for custom deputies (medium).** `/mod/approvals` gates on
  `core.content.view_pending` but still row-scopes via legacy `board_moderators`
  (empty queue for a custom deputy), and `/mod/reports` was not cut over
  (`core.report.handle` deputy 404s). Belongs with the deferred `/admin`
  console / queue-discovery increment; both fail *closed*.
- **`CAPABILITIES_MODE` unknown value → silent shadow (low, fail-safe).** No
  trim/case-normalize or unknown-value rejection; a typo'd mode silently runs
  shadow. Runbook step 4's pilot grant detects it functionally. Follow-up:
  normalize + log/reject unknown, and surface the effective mode operationally.
- **Capability keys as free string literals (medium hazard, latent).** No
  per-key constants and no test scanning call-site literals against the
  catalogue; a typo'd key fail-darks under enforce (denies everyone incl.
  admins) invisibly to CI. Follow-up: `Cap::*` constants + a literal-vs-catalogue
  invariant test.
- **Resolver memo gaps + `postableBoards` N+1 (efficiency, within budget).**
  `isMember()`/`roleKeysHolding()` are unmemoized on the decision-miss path
  (~10 duplicate queries per authed thread render under shadow/enforce);
  `postableBoards` re-resolves per board on the 422 compose re-render. p95 has
  3× headroom, so non-blocking. Follow-up: memoize both alongside the existing
  per-request memos.
- **Minor cleanups.** Duplicated roster gate/deputy-catch blocks
  (`AdminService`×4, `AdminController`×4), the dead `core.post.delete_any`
  defaults on `ModerationService`'s private helpers, and ~10 hand-wired
  `CapabilityResolver` construction sites — DRY-only, no behavior change.

## Inc 6 follow-ups resolved (2026-07-07, branch `phase5-inc6-followups`)

All six recorded pre-merge follow-ups above are fixed (TDD, red→green), plus
the doc-staleness findings from the 2026-07-07 phase review:

- **V7 — canEditTags shadow noise → phantom control removed.** The write path
  (`TagController::updateThread`, `[STATE-KEEP]`) already answered the design
  question: posting rights are the single tagging gate, no staff carve-out.
  The display flag's staff-only moderator arm was therefore a phantom control
  (rendered, then 403 on submit) and the source of the per-member-view
  mismatch noise. Display is now the same single `canPost`-closure gate the
  write path uses (archive-aware); `core.thread.tag` stays a CAN_POST_GATED
  member-baseline key; no resolver/oracle change. Taxonomy quirk 7 records
  the resolution; `AppThreadTagDisplayTest` pins display ⟷ write agreement.
  **The shadow soak is unblocked.**
- **Queue discovery** (pulled forward from the deferred `/admin`-console
  increment per the follow-up instruction): `/mod/approvals` row scope and
  the `/mod/reports` door+rows derive from
  `ModerationService::moderableBoardIds()` — a per-board gate check on the
  queue's *action* key (`core.content.approve`/`core.report.handle`).
  Legacy/shadow doors and rows are byte-identical to pre-cutover (pinned);
  under enforce a custom deputy's grant surfaces its rows, the approvals
  door additionally admits (enforce-only) a deputy with ≥1 discovered board,
  and a vestigial global moderator gains **no** rows (dedicated
  no-broadening test — the projection's site-wide `view_pending` mirror
  cannot leak into row scope because discovery keys on the action).
  `AppModQueueDiscoveryTest`; runbook cutover-surface updated.
- **`CAPABILITIES_MODE` normalization**: `AuthorityGate::fromConfig()` trims/
  case-folds; unknown values (incl. `legacy`, which is the flag-off state,
  not a posture) run fail-safe shadow and emit
  `capabilities.mode_invalid {raw, effective}`; `/admin/roles` displays the
  effective posture (replacing the pre-Inc-6 "inert" copy). Runbook updated.
- **`Cap::*` per-key constants + literal scanner**: `src/Security/Cap.php`
  (54 constants, catalogue-parity-pinned by `CapTest`); the 19 enforcement
  files reference constants (typo = fatal, not silent fail-dark);
  `CapabilityLiteralsTest` validates every remaining quoted
  `core.<area>.<action>` literal in `src/` against the catalogue with a
  planted-typo self-test (definitional tables keep literals by design).
- **Resolver memo gaps + `postableBoards` N+1**: `isMember()`/
  `roleKeysHolding()` now sit in per-request memos (cleared by the same
  `invalidate()`), and `postableBoards` primes the board-row/membership
  memos from fetches it already holds (`primeBoards`/`primeMembership`;
  legacy mode never constructs the resolver, so priming is skipped).
  `resolver.p95` measured **0.4655 ms** post-change (5 ms budget).
- **Minor cleanups**: `AdminService::assertRosterAuthority()` +
  `AdminController::rosterCommand()` collapse the 4×2 duplicated roster
  blocks (V1 authorize-before-boardOrFail ordering preserved in the doc
  comments); dead private-helper defaults removed; the five default-shaped
  hand-wired test resolvers delegate to `TestCase::capabilityResolver()`
  (`ResolverShadowTest` keeps its parameterized bad-DB builder).

**Gates (2026-07-07):** `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` →
**1670 tests / 8587 assertions**, and the reused-schema run → **1670 / 8587**
(identical). `php bin/console verify:resolver-parity` → **1551 tuples, 1551
agreed, 0 mismatches** (no resolver-semantics change; regenerated timing-only
report restored, not committed). `APP_ENV=testing php bin/console
verify:phase5-budgets` → all owned D11 rows **MEASURED (PASS)**;
`resolver.p95` **0.4655 ms** (memoization), `webauthn.ceremony_p95` 2.0911 ms.
`APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade
--force` → **17/17** (no new migration — still through `0073`).
`cd tests/browser && CAPABILITIES_MODE=enforce RB_BROWSER_DARK_SURFACES=1
bash prepare.sh && … npx playwright test role-assignments.spec.ts` →
**4 passed** (desktop + mobile): the two prior Inc 6 journeys plus a new
deputy-queue-discovery journey — admin creates two approval-required boards,
grants carol an approve-only custom role on one, bob's topics are held, carol
enters `/mod/approvals` via discovery, sees only her board's hold (axe-clean;
PNG `65-deputy-approvals-queue`), approves it, and still 404s at
`/mod/reports`; the roles page asserts "Resolver posture: enforce". The new
axe scan surfaced a **pre-existing site-wide contrast defect**: the gold
monogram bucket (`--gold-700` #9A7530 ink on `--gold-100` #F4EBCF, 3.55:1 at
11.5 px) — fixed by a new `--gold-800` #6B5120 ink for `.mono-2`/`.mono-6`/
`.badge-staff` (6.2:1 / 5.3:1; chip grounds are theme-invariant so dark mode
is unaffected; `.reaction-on` deliberately untouched — its dark-mode ground
is translucent-dark and a darker ink would regress it there). Regenerated
PNGs `62`–`64` were restored, not committed, per the evidence convention;
`65` is new and committed.

Still open from the Inc 6 closeout "Deferred (recorded, not silent)" list
(unchanged by this batch): the `/admin` console cutover itself, the
symmetric revoke/renew authority ceiling, the expiry sweeper non-goal, the
S3 cross-connection double-submit residual (DB partial-unique guard), and
no legacy-assignment import (permanent).

## Increment 8 landed (2026-07-07) - P5-12 provider registry + generic OIDC, deploy-dark

Generic OIDC is live behind the dark `provider_registry` flag (branch
`phase5-inc8-provider-registry`): registry rows become sign-in providers by
configuration alone, through the shared Phase-2 OAuth core — no
provider-specific code, exactly the ADR 0004 D8 seam. **The A2 acceptance
target (GitLab.com) is implemented as pure configuration** and exercised
end-to-end by GitLab-shaped fixtures; the live gitlab.com registration
walkthrough is in the runbook for staged enablement.

- **Verification core** (`src/Service/OAuth/Oidc/`): `OidcDiscovery`
  (HTTPS-pinned issuer; the document must assert the identical issuer;
  endpoints HTTPS; JWKS URI same-origin with the issuer) → issuer-pinned
  `JwksCache` (24 h row cache, exactly one forced refresh on an unknown kid,
  stale-on-outage for reads but live-only for the rotation refresh, and no
  fetch can ever leave the pinned origin — even from a poisoned cache) →
  `JwtVerifier` (RS256 via ext-openssl with JWK→SPKI conversion; alg
  allowlist refusing `none`/HS256-confusion before touching keys; iss
  exact, aud/azp against the client id, nonce required-equal, exp/iat/nbf
  with 300 s leeway; `unknown_kid` as the single refresh signal) →
  `ClaimMapper` (subject fixed to `sub` — never operator-remappable;
  `email_verified` strict-boolean; `claim_map_json` renames cosmetic claims
  only; https-only avatars).
- **Shared-core reuse, no fork:** `OidcProvider` implements the existing
  `OAuthProvider` interface from an `identity_providers` row; account
  resolution/linking/collision/unlink-protection is the untouched
  `OAuthService`. The interface gained `label()` and an expected-nonce
  parameter on `identity()` (builtins ignore it — Phase-2 behavior
  unchanged); the callback now passes the flow nonce, and the redirect leg
  fails soft on discovery outage. New identities persist
  `provider_config_id` linkage; `NormalizedIdentity` carries it.
- **Migrations:** `0074` adds the discovery-document cache columns and
  backfills `oauth_identities.provider_config_id` via the 0052 alias map —
  idempotent, NULL-linkage-only, unmapped strings tolerated; resolution
  stays string-keyed (keys are 1:1 with registry rows, satisfying the
  decision-#32 identity key without repointing the physical unique key).
  `0075` widens `moderation_log.target_type` (+`identity_provider`).
  Reconciliation evidence: `AppProviderRegistryMigrationTest` (no
  duplication/orphaning, alias indirection, idempotence, distinct subjects
  never converge) + `verify:upgrade` 17/17 through `0075`. SCHEMA.md →
  v1.35.
- **Operator console** (`/admin/providers`, flag-gated + admin +
  password-reauth mutations): add (validated pinned issuer; client secret
  straight into the SecretVault as an `svcsec_*` reference — refuses 422
  while `service_secrets` is dark, the §E rule-1 sequencing), explicit
  health probe (validates discovery+JWKS through the pinned path, primes
  both caches, records `ok`/`down`), enable, and disable via a confirm page
  that lists sole-method accounts FIRST — **the TM-ID-09 clause-2 Inc 8
  handoff is resolved** (`OAuthIdentityRepository::soleMethodAccounts` wired
  into the operator flow). Builtin rows are visible (env-config guidance +
  sole-method counts) but immutable from the console. Disable retains
  identities. Audit rows on create/enable/disable.
- **Registry expansion fails dark:** `ProviderRegistry` grows a lazy loader
  (flag-gated in the container) that can never shadow a builtin key and
  yields an empty dynamic set on any Throwable; `App` gained a fourth
  constructor seam (scripted outbound OAuth HTTP) so the whole flow runs
  in-process under test. Sign-in/connections surfaces render provider
  labels.
- **Threat fixtures implemented (TM-ID-01..04)** in
  `AppOidcProviderTest` (20 kernel-level tests): cross-issuer/wrong-aud/
  wrong-azp; state mismatch pre-exchange, replay-after-completion,
  missing/wrong nonce, PKCE possession (S256 verifier↔challenge pinned);
  off-issuer JWKS never fetched + rotated-kid single pinned refresh;
  verified-email collision refuses sign-in/merge and links only after
  explicit login. Plus §9 arms: token-endpoint outage fails soft (no 500,
  no account), disable retains identities/404s routes/removes the button,
  dark flag hides enabled rows, closed-registration parity, sole-method
  protection covers generic identities.
- **Budgets:** `oidc.discovery_p95_cached` **0.7275 ms** / 2000 ms and
  `oidc.discovery_p95_cold` **1.2766 ms** / 5000 ms — **MEASURED (PASS)**
  via the new self-fixturing `measureOidcDiscovery` sampler (cache-only
  proof on the warm arm; in-process transport on the cold arm, remote RTT
  excluded by design and stated in the report).
- **Browser evidence:** `providers.spec.ts` (add → probe → enable →
  sign-in button on `/login` → sole-method disable confirm → disable →
  button gone), axe-clean on the console + confirm page, PNGs `66`-`68`
  (desktop+mobile) committed; the spec joined `npm run evidence` for
  standing CI coverage. Docs: `docs/runbooks/provider_registry.md`,
  `docs/evidence/phase5/oidc-provider-registry.md`; deploy-dark inventory
  updated. Ledger: **GA-DOD-14 → R4, GA-DOD-15 → R4**; GA-DOD-03/18 notes
  updated (held at R1 for the Inc 9 invitation allocations only).

**Inc 8 gates (2026-07-07):** `RB_TEST_FRESH=1 vendor/bin/phpunit` →
**1760 tests / 9022 assertions**, and two consecutive reused-schema runs →
**identical 1760/9022** (the transient mid-session failures during this
work were the bootstrap's documented interrupted-rebuild scenario, recovered
via `RB_TEST_FRESH=1` and not reproducible — three consecutive full-green
runs). `php bin/console verify:resolver-parity` → **1551 tuples, 1551
agreed, 0 mismatches**. `APP_ENV=testing DB_DATABASE=retroboards_e2e php
bin/console verify:upgrade --force` → **PASS 17/17 through 0075**.
`APP_ENV=testing DB_DATABASE=retroboards_test php bin/console
verify:phase5-budgets` → both oidc rows MEASURED (PASS) (report committed;
all previously-owned rows remain MEASURED PASS). Closeout guards
(`Phase5EvidenceMapTest` + `ThreatModelIndexTest` + `MigrationLedgerTest`)
→ **12 tests / 53 assertions**. `cd tests/browser && npm run evidence`
(now incl. providers.spec.ts) → **68 passed / 1 failed / 1 skipped** — the
failure is the pre-existing `gate-a.spec.ts` badge-rules mobile issue
(predates Inc 6, proven on main via stash-baseline; unchanged). `npm run
a11y` → **26 passed / 2 skipped**. Re-shot PNGs and the timing-only
parity report were restored, not committed, per convention.

**Decisions recorded with Inc 8:**

1. **Scopes fixed at `openid profile email`** for generic providers (matches
   the accepted GitLab A2 config and the Google builtin); a per-provider
   scopes column is deferred until a real IdP needs it.
2. **Discovery URL always derived from the issuer.** The 0052
   `discovery_url` column is honored by the verification core when present
   (same-origin validated) but is not yet settable from the console.
3. **Health is probe-driven** (explicit "Test connection"), not passive:
   live-flow failures fail soft for the member but do not write health —
   deterministic operator signal over noisy auto-degradation.
4. **Physical unique-key repoint deferred:** `uq_provider_identity
   (provider, provider_user_id)` stands; the alias map + unique provider
   keys make the string key 1:1 with `(provider_config, sub)`. Revisit only
   if provider-key reuse ever becomes a requirement (keys are immutable in
   the console precisely so it cannot).
5. **`oauth` remains the master flag:** generic providers require both
   `oauth` (Phase 2, default-ON) and `provider_registry`.

### Pre-merge review hardening (2026-07-07, merged with PR #39)

A max-effort multi-agent review (8 finder angles → 25 verified candidates:
24 confirmed / 1 refuted) fixed ten findings before merge, all deploy-dark:

- **Issuer pin stored verbatim** — `create()` no longer strips a trailing
  slash; spec-legal slash-carrying issuers (Auth0-style) previously failed
  `issuer_mismatch` forever. Length bounds added for `issuer` (512) and
  `claim_map_json` (64 KB) BEFORE the vault write — an oversized value used
  to 500 after the secret was stored (orphaned `svcsec_*`, typed form lost).
- **Authorize URL query join** — an `authorization_endpoint` already carrying
  a query (RFC 6749 §3.1, e.g. Azure B2C) now joins with `&`, not a second `?`.
- **Stale-cache fallback now covers HTTP-error outages** — the OAuth
  `HttpClient` treats an HTTP ≥400 response without a JSON body as a failed
  fetch (thrown like a transport error), so JwksCache/discovery stale-on-outage
  applies to the most common outage shape (LB maintenance pages); structured
  JSON error bodies (token-endpoint `invalid_grant`) still return.
- **Kid-less rotation recovery** — a `signature_invalid` against the cached
  key now earns the same single pinned JWKS refresh as `unknown_kid` (RFC
  7515 makes `kid` optional; single-key IdPs rotate too).
- **Discovery resolved once per callback** — `OidcProvider::document()` is
  memoized per instance; exchange()+identity() no longer double-fetch/double-
  UPDATE on a stale cache (a transient failure on the second fetch aborted
  sign-in after the code was consumed).
- **Builtin probe guard** — `healthProbe()` now refuses non-`generic_oidc`
  rows like every other console mutation (a hand-crafted POST could fire live
  fetches and overwrite health/caches on env-managed builtin rows).
- **Sole-method counts tell the truth for disabled rows** — the console
  passes the enforcement-side usable set (oauth-flag-gated, matching the
  App.php lockout closures) UNION the row's own key, so a disabled provider
  keeps reporting its dependent members instead of dropping to 0; the index
  uses a new COUNT-only `soleMethodCount()` (no per-row hydration N+1).
- **Retained identities stay visible** — `/settings/connections` renders a
  linked identity whose registry provider was disabled (or flag rolled back)
  with its label and Disconnect form; previously it vanished undisconnectably.
- **Row-scoped reauth errors** — a wrong password on a row's inline Enable
  form renders beside that row, not under the add-provider form.
- **Member-facing labels** — OAuth flash messages use the provider `label()`
  seam (slugs like `acme_sso` no longer leak); plus hygiene: per-request
  shell builds the sign-in menu from a narrow name+label query (no MEDIUMTEXT
  cache hydration per request), one shared usable-provider closure feeds both
  lockout guards, `JwtVerifier` reuses the strict `App\Support\Base64Url`
  decoder and emits canonical minimal-DER integers (CoseKey rule), and the
  orphaned WebAuthn docblock/`setEnabled()` re-SELECT were cleaned up.

**Deferred (recorded, not fixed here):** extract one shared RSA-SPKI DER
builder for CoseKey + JwtVerifier (WebAuthn is outside this PR's diff);
resolve `provider_config_id` at link time for builtin providers (post-0074
builtin links store NULL — a decision-#32 cutover prerequisite, no production
reader today); remove the legacy null-usable-set fallbacks (test-only);
derive RESERVED_KEYS from ProviderRegistry + make its silent builtin-shadow
drop loud; a single `isConsoleManaged()` predicate for the four
`type !== 'generic_oidc'` sites (the enum already holds a third value
`package`); hoist the triplicated JWT/JWKS test fixtures into tests/Support;
hoist the 8× duplicated `noindex()` helper into the base Controller; cap the
disable-confirm sole-method listing for very large SSO installs.

This instruction accepts ADR 0004 as the Milestone-0 decision record using its
recommended defaults unless a later owner scope-change record overrides them. It
also accepts the Phase 4 engineering closeout and ADR 0003 deferrals as explicit
carryovers, not shipped behavior.

**A2 accepted 2026-07-02:** the owner named **GitLab.com** as the first
additional OIDC provider (`docs/phase5/first-oidc-provider.md`) — the last open
§2 entry-gate decision; P5-12/Inc 8 end-to-end acceptance is decision-unblocked.

## Increment 9 landed (2026-07-08) - P5-13 invitations, deploy-dark

The invitation lifecycle is live behind the dark `invitations` flag (branch
`phase5-inc9-invitations`): admin-issued, hash-only, expiring, revocable,
optionally email/domain-bound invitations redeemed atomically with account
creation — plus an **explicit `registration_mode = invite`** (owner decision
2026-07-08, `docs/phase5/invitation-defaults.md`). `closed` stays absolute
(a valid token cannot reopen it) and `invite` **fails closed while the flag
is dark**, so pausing the subsystem never silently reopens registration.

- **Policy seam** (`src/Security/RegistrationPolicy.php`): one interpreter
  for `registration_mode` (`open|invite|closed`, `effectiveMode()` degrades
  `invite` → `closed` while dark) consumed by BOTH account-creation channels
  — the password form and OAuth provisioning (`OAuthService` returns the new
  `registration_invite_only` action; returning logins/linking untouched) —
  so the two paths can never disagree. Dashboard gains the `invite` option
  plus an "effectively closed" warning while the flag is dark.
- **Service/repository** (`InvitationService`/`InvitationRepository`):
  issuance validates email XOR exact-match domain binding, `max_uses` 1–100,
  mandatory expiry 1–365 days (default 14); 256-bit tokens
  (`bin2hex(random_bytes(32))`) stored sha256-only and returned exactly once
  (direct render — never the cookie-backed flash). Redemption is one
  transaction ordered *uniform validity → binding → guarded
  `consumeUse` → register → redemption row (packed IP) → board grant →
  audit*: a concurrent loser exits before creating anything (TM-IN-02), a
  registration failure rolls the consumed use back, and enumeration is
  uniform (`INVALID_MESSAGE` for missing/expired/revoked/exhausted,
  TM-IN-01/03). **No role application, ever** (decision #36): the console
  never issues `onboarding_role_id` and redemption ignores planted values —
  forged POST fields and DB-planted role ids both yield ordinary membership
  only (TM-IN-05); `onboarding_board_id` grants plain board membership.
- **Operator console** (`/admin/invitations`, flag-gated + admin-only +
  noindex): issue (show-once link panel), list with derived
  active/revoked/expired/exhausted status, revoke (idempotent, audited).
  Issuance is rate-limited (`invite_create` 30/h per admin; TM-IN-07 —
  member/moderator access is 403 on GET and POST).
- **Public flow:** `GET /invite/{token}` (noindex, 404 while dark) is a
  pure normalizing redirect into `/register?invite=…`; register renders the
  mode × validity matrix (invited banner / uniform invalid / invite-required
  / closed, form suppressed only on pristine blocked renders) and keeps the
  token + typed draft through 422s and 429s (anti-draft-loss). `invite_redeem`
  (30/15 min per client) covers invite-bearing register GET/POST — the single
  verdict endpoint, charged once per token evaluation (hardening below);
  the stricter standard `register` policy applies only to uninvited public
  signups. Invited members still get the verification email — an invitation
  bypasses nothing (decision #36).
- **Migration:** `0076` widens `moderation_log.target_type`
  (+`invitation`; mirrors 0075) for `invitation_created` /
  `invitation_revoked` / `invitation_redeemed` audit rows; the 0053 tables
  are animated unchanged. SCHEMA.md → v1.36.
- **Threat fixtures:** TM-IN-01..07 all implemented
  (`docs/phase5/threat-models/fixtures.json` → `InvitationServiceTest` +
  `AppInvitationsTest`), including a real-commit/rollback single-use race
  proof (harness-transaction-suspended pattern) and hash-only-at-rest scans
  of rows, audit JSON, and list views (TM-IN-06). Canonical dark pin in
  `AppFeatureFlagTest`: a planted VALID invitation is inert while dark
  (ordinary open-mode signup, zero consumption, no grant).
- **Budget:** `invitation.redemption_p95` **461.79 ms MEASURED (PASS)**
  (target 500 ms; 60 samples of the full redeem+register path with a
  **production-cost Argon2id hash** inside the timed region — the harness's
  process-wide hasher weakening is suspended for the bench; see the
  hardening below, which replaced the original harness-weakened 5.59 ms
  figure) via `BaselineMetricsService::measureInvitationRedemption`,
  wired into `verify:phase5-budgets` behind its own opt-in —
  every increment-owned Gate A D11 row is now MEASURED (PASS)
  (GA-DOD-18 → R3).
- **Browser evidence** (`tests/browser/invitations.spec.ts`, desktop +
  mobile, axe-clean, in the standard `npm run evidence` set): PNGs `69`-`74`
  — console show-once + revoke list, invite-mode blocked register,
  invite-banner redemption, signed-in landing, uniform invalid banner
  (`docs/evidence/phase5/invitations.md`). The first `/register` axe scan
  surfaced a pre-existing `link-in-text-block` violation on the auth-card
  "Log in" link — fixed (`.auth-links a` underline in `app.css`).
- **Docs:** `docs/phase5/invitation-defaults.md` (the Milestone-0
  invitation-defaults decision, recorded + owner-accepted),
  `docs/runbooks/invitations.md` (enable, **invitation pause** = flag off
  per decision #40 with an effect matrix, bulk revoke, rate-limit tuning,
  token hygiene, troubleshooting), GA-DOD-16 → **R4**, F5 note resolved
  (no LastOwnerGuard mutation site exists because redemption grants no
  authority — explicit, not silent).

**Deferred (explicit):** onboarding-role grants via invitation (schema
affordance stays inert pending an owner-approved policy — decision #36);
member-created invitations (issuance stays admin-only; a future policy
setting); carrying an invite token through the OAuth first-login flow
(invite-mode OAuth users redeem on /register first, then link); a console
bulk-revoke affordance (runbook documents the SQL sweep). R5 at staged
enablement.

### Pre-merge review hardening (2026-07-08, merged with PR #40)

A max-effort multi-agent review (10 finder angles → 46 raw candidates
converging on 15 fix clusters, every one applied TDD) hardened the branch
before merge, all deploy-dark:

- **Honest budget measurement (§13 evidence integrity — the headline).**
  The originally published 5.59 ms p95 was measured under the harness's
  process-wide Argon2id weakening (both the test bootstrap and
  `verify:phase5-budgets` downgrade `PasswordHasher` for fixture speed), so
  the "includes the real password hash" claim was false — production cost
  is ~80× higher. `measureInvitationRedemption` now suspends the override
  inside its timed region and restores it in a `finally` (restoration
  pinned by test), runs 60 production-cost iterations, and no longer
  fabricates `query_count` (was a hardcoded 9×iterations estimate).
  Republished: **461.79 ms p95 (PASS)** vs the 500 ms target. The report
  row also moved behind its own `includeInvitationSample` opt-in — the
  pre-existing Inc 6 lifecycle report tests had started silently paying a
  200-registration hash-heavy bench.
- **POST-429 validity oracle closed (TM-IN-01).** The rate-limited register
  POST/GET previously still ran `inviteContext()` and could render the
  valid-token banner, so a client past the cap could keep reading token
  validity off 429 bodies — defeating the cap's purpose. Limited responses
  are now uniform (no token lookup, no preview, identical body for valid
  and invalid tokens — pinned both ways), while the draft AND the token
  survive for the retry.
- **Anti-draft-loss on blocked renders.** Invite-mode POST failures (token
  died mid-draft; token-less POST carrying a draft) suppressed the whole
  form via `registration_blocked`, silently discarding the typed draft —
  violating the repo invariant. The form now always renders when a draft is
  present (suppression is for pristine renders only); the blocked notice
  comes from the template's own state chain.
- **Dead token dropped so the retry can succeed.** A 422 caused by the
  token's own death (`errors['invite']`) now removes the token from `old`
  — the re-rendered form's next submit succeeds in open mode instead of
  re-failing forever on an unremovable hidden field. Field-level 422s keep
  the still-live token **without re-probing it**.
- **GET-429 grant bypass closed.** An open-mode `GET /register?invite=…`
  429 used to render a live plain form: the invitee would register with the
  grant never consumed (board membership silently lost). The limited GET
  keeps the token in the form (and the response noindex).
- **Landing double-charge removed.** `/invite/{token}` no longer charges
  `invite_redeem` before redirecting into `/register` (which charged
  again): the landing is a pure normalizing redirect — one charge per
  request that actually evaluates a token, and an exhausted client lands on
  the friendly register page instead of a bare kernel 429.
- **One clock for expiry.** `preview()`/`list()` judged expiry with PHP
  `gmdate()` while `consumeUse` compares against DB `UTC_TIMESTAMP()` —
  under web-host/DB clock skew the console and the redemption gate disagree
  near expiry (console says Active, every redemption refuses, or vice
  versa). All validity decisions now use the DB clock.
- **Redemption-IP retention gap (privacy).** This increment is the first
  writer of `invitation_redemptions.ip`, but `worker:purge-ips` only
  anonymised `sessions.ip`/`posts.ip` — redemption IPs would have been
  retained forever. The purger now covers the new column (idempotent,
  audited in the same system-actor row, tested), and the runbook documents
  the coverage.
- **Default-deny registration modes.** Both mode consumers (the register
  POST and OAuth provisioning) treated any unknown mode as `open`, so a
  hypothetical future restrictive mode would ship fail-open. Both now
  allow-list `open`/`invite` and deny the rest; the dashboard select
  derives its options from `RegistrationPolicy::MODES`.
- **Indexing hygiene.** `/invite` joined the robots.txt Disallow list
  (token-bearing URLs); the `X-Robots-Tag: noindex` decision is keyed on
  the RAW request token, so limiter or flag state can never drop the header
  from a URL that carries a secret (429 and dark-flag renders pinned).
- **Domain-binding regex.** Label-wise validation (no empty labels, no edge
  hyphens): `example..com` previously minted an invitation no valid email
  address could ever satisfy — silently unredeemable. Board ids get the
  same strict string round-trip as the other numeric fields.
- **Array-shaped parameters.** `?invite[]=x` / array-shaped POST bodies no
  longer raise an E_WARNING (strict-PHPUnit red) or coerce to `'Array'` —
  malformed carriers are treated as absent.
- **Console list clamp.** Invitations are never purged (revoked/expired
  rows are retained by design), so the console list's unbounded scan would
  grow for the life of the install — clamped to the newest 200 rows.
- **`noindex()` hoist (Inc 8 deferred follow-up resolved).** This PR would
  have added the 9th per-console copy; the helper moved into the base
  `Controller` and all nine private copies are gone.
- **Hygiene.** Rate-limit policy names became constants
  (`InvitationService::LIMIT_CREATE`/`LIMIT_REDEEM` — the limiter fails
  open on a typo'd name); the console form ranges render from the service
  constants instead of hardcoded 1–100 / 1–365; the auth-card
  link-contrast fix generalized into one shared muted-prose rule
  (`.auth-links/.notice/.muted/.empty a` — root cause instead of a third
  leaf-selector patch); the browser spec's mode/theme restore moved to a
  timeout-safe `afterAll` (a hang after `setRegistrationMode('invite')`
  would have left the shared evidence DB invite-only for every following
  spec).

**Deferred (recorded, not fixed here):** hoist the triplicated invitation
test-fixture builders (issue/redeem helpers duplicated across the three
invitation test files) into `tests/Support`; console pagination if a real
archive-browsing need ever outgrows the 200-row clamp.

**Gates at merge:** suite 1826 tests / 9376 assertions green ×2 consecutive
(was 1819/9315 at closeout); invitations.spec.ts 2 passed (desktop+mobile,
axe-clean; PNGs 69/70/73 re-shot for the shared muted-prose link rule,
71/72/74 pixel-identical); `verify:phase5-budgets` re-measured every owned
row within target (invitation p95 461.79 ms = 7.6% headroom under 500 ms).

### Second-pass review hardening (2026-07-09, merged with PR #40)

A fresh max-effort multi-agent review (6 finder angles + a full independent
read) over the already-hardened branch surfaced five more items, all
low-severity and deploy-dark, all fixed TDD:

- **Invite redemptions no longer double-charged against the public `register`
  cap (NAT lockout).** The register POST enforced BOTH the dedicated
  `invite_redeem` [30/900] policy AND the stricter public-signup `register`
  [5/3600] cap on every redemption, so an invite-only site onboarding a team
  behind one shared NAT locked out after the 5th acceptance/hour — well within
  the invite budget, and the dedicated policy was effectively moot. Invite
  redemptions now enforce `invite_redeem` only (the admin-issued, use-capped
  invitation is the abuse control on that path); the general cap still governs
  uninvited open-mode signups. Pinned by a two-redemptions-behind-one-IP test.
- **Admin console `create()` array-shape guard.** The first pass guarded the
  public invite carrier against `?invite[]=…`, but the console's own
  `email`/`domain`/`max_uses`/`expires_in_days`/`onboarding_board_id` fields
  still `(string)`-coerced, so a crafted `email[]=…` POST raised five
  `Array to string conversion` warnings (a strict-suite red waiting to happen).
  A shared `stringField()` guard now treats array-shaped console fields as
  absent, matching the public carrier.
- **`invitations.used_count` reconciliation wired into `repair`.** The
  denormalised consumption counter had no `RepairService` recompute — unlike
  every other counter in the system, breaking the CLAUDE.md "every incremented
  counter has a matching recompute" invariant (the authoritative
  `invitation_redemptions` ledger's `redemptionCount()` hook was wired only
  into a test). `repairInvitationUsedCounts()` recomputes `used_count` from the
  ledger and joins `repairAll()`, so `php bin/console repair` picks it up.
- **One clock for expiry, completed at the write side.** The first pass moved
  the read gates (`preview`/`list`) onto the DB clock, but `create()` still
  STAMPED `expires_at` from PHP `time()`, so an app/DB clock skew shifted the
  real TTL. Issuance now stamps `expires_at` via
  `DATE_ADD(UTC_TIMESTAMP(), INTERVAL n DAY)` — the same clock the gates read.
- **`preview()`/`list()` round-trip folded.** The DB-clock read added a
  standalone `SELECT UTC_TIMESTAMP()` per preview (i.e. per redemption) and per
  console render; `now_utc` now rides along in the row-fetch statement
  (`findByTokenHash`/`all`) with identical single-statement-constant semantics,
  so `nowUtc()` and its extra round-trip are gone.

**Gates at merge:** suite **1831 tests / 9396 assertions** green ×2 consecutive
(1828 after the interim `db3beaa` unknown-mode fail-closed commit, plus 3 new
regression tests); `invitations.spec.ts` **2 passed** (desktop + mobile,
axe-clean). No schema, template, CSS, or JS change — the six dynamic-content
evidence PNGs regenerate byte-for-byte-different (fresh token/timestamp) but
visually identical, so they are left as first-pass-committed.

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
- **TOTP/recovery:** R4 implementation + browser/no-JS release evidence. The
  Inc 7 B1 predecessor retrofit added `tests/browser/totp.spec.ts` and PNGs
  under `docs/evidence/browser/*/totp-*.png`; password/TOTP/recovery remains
  the passkey fallback path.
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
- **Capability resolver shadow (Inc 1, P5-08):** R3 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-10). Shadow-only: live
  authorization is unchanged; enforcement is Inc 6.
- **Package lifecycle (Inc 3, P5-02):** R3 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-05/06). Deploy-dark behind
  `package_registry`; non-theme integrations still execute nothing until Inc 5
  runtimes.
- **Declarative themes (Inc 4, P5-03):** R3 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-07). Deploy-dark behind
  `package_themes`; theme packages can only emit deterministic external CSS and
  sanitized raster assets, with preview/activation/safe-mode/LKG rollback
  release evidence.
- **Passkeys (Inc 7, P5-11):** R4 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-13) and
  `docs/evidence/phase5/passkeys.md`. Deploy-dark behind `passkeys`; enrollment,
  sign-in, list/remove, step-up, fallback/recovery, virtual-authenticator browser
  evidence, TM-ID-05..09, and the `webauthn.ceremony_p95` budget are evidenced.
  R5 waits for staged enablement (§13.1 step 9); privileged-MFA enforcement and
  usernameless sign-in remain Gate B.
- **Fixture + baselines + budget harness (Foundation F9):** R2 (implemented,
  deploy-dark — no flag; the seeder refuses `app.env=production`) +
  **R3-partial** — `Phase5FixtureSeeder` (representative role/assignment/
  provider/moderator corpus), `BaselineMetricsService` (legacy
  authority-read baseline), the code-owned `Phase5Budgets` D11 catalogue,
  and `Phase5BudgetReportService` wired via `bin/console verify:phase5-budgets`
  are implemented with enforcing PHPUnit evidence. The **A3** entry-gate
  artifact (`docs/evidence/phase5/performance-budgets.md`) is generated and
  measures `resolver.p95`, `registry.signature_verify_p95`,
  `package.install_update_p95`, `theme.build_apply_p95`, and
  `webauthn.ceremony_p95` as real numbers
  against the F9 fixture
  (`registry.snapshot_freshness` and `webhook.delivery_timeout` report CONFIG
  caps); the unowned future budgets stay **PENDING** until each owning increment
  measures them on this same fixture — not R4/R5.
- **Foundation remainder (F2/F4/F6/F7/F8/F10/F11):** R3 — see
  `docs/phase5/requirement-ledger.json`, now the machine-checked source of
  truth for states. This lands `CORE_VERSION`, `DataClasses`, the Ed25519
  signing harness + registry fixtures, `ReauthGate`, `Telemetry`/`LogRedactor`,
  the all-flags-off core-survival regression, and six recorded-pending-review
  threat-model dossiers.
- **Provider registry / generic OIDC (Inc 8, P5-12):** R4 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-14/15) and
  `docs/evidence/phase5/oidc-provider-registry.md`. Deploy-dark behind
  `provider_registry`; R5 at staged enablement.
- **Invitations (Inc 9, P5-13):** R4 — see
  `docs/phase5/requirement-ledger.json` (GA-DOD-16),
  `docs/evidence/phase5/invitations.md`, and
  `docs/phase5/invitation-defaults.md`. Deploy-dark behind `invitations`;
  TM-IN-01..07 implemented, `invitation.redemption_p95` MEASURED (PASS),
  browser/axe evidence `69`-`74`, runbook recorded. R5 at staged enablement.
- **All other Phase 5 subsystems (sandbox, governance,
  service principals, verified links):** R0/R1 — pending implementation and
  workstream-specific evidence.

## Evidence index (this increment)

- Migrations: `database/migrations/0049_phase5_registry_packages.php` ...
  `0057_phase5_webhooks.php`, plus `0066_phase5_seed_capabilities_owners.php`,
  `0068_phase5_registry_snapshots.php`, `0069_phase5_package_lifecycle.php`,
  `0070_phase5_publisher_review_security.php`, and
  `0072_phase5_theme_packages.php`.
- Schema doc: `SCHEMA.md` §5A/§5B + §3 `api_tokens`/`webhooks`/`webhook_deliveries` + B2 hook-registry note + package/theme table entries.
- Flag-dark regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Schema-shape + secret/separation-invariant regression: `tests/Integration/Core/AppPhase5FoundationSchemaTest.php`.
- Service-secret schema regression: `tests/Integration/Core/AppServiceSecretsSchemaTest.php`.
- Service-secret behavior/redaction regression: `tests/Integration/Service/SecretVaultTest.php`.
- API-token regression (B2 sub-project 2): `tests/Integration/Core/AppApiTokensSchemaTest.php`, `tests/Unit/Security/ApiScopesTest.php`, `tests/Integration/Service/ApiTokenServiceTest.php`, `tests/Integration/Api/ApiReadEndpointsTest.php`, `tests/Integration/Api/AdminApiTokenTest.php`.
- API-token browser evidence: `docs/evidence/browser/{desktop,mobile}/20-admin-api-token-minted.png` + `21-admin-api-token-revoked.png` (admin mint → show-once → revoke via no-JS form posts).
- Webhook regression (B2 sub-project 3): `tests/Integration/Core/AppWebhooksSchemaTest.php`, `tests/Unit/Support/CidrTest.php`, `tests/Unit/Security/WebhookEventsTest.php`, `tests/Unit/Security/EgressGuardTest.php`, `tests/Unit/Service/WebhookSignerTest.php`, `tests/Unit/Service/WebhookTransportTest.php`, `tests/Integration/Repository/WebhookRepositoryTest.php`, `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`, `tests/Integration/Service/WebhookServiceTest.php`, `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`, `tests/Integration/Admin/AdminWebhookTest.php`.
- First-party hook/domain producer regression (B2 sub-project 4): `tests/Unit/Hook/FirstPartyHookRegistryTest.php`, `tests/Integration/Service/DomainWebhookProducerTest.php`.
- Webhook browser evidence: `docs/evidence/browser/{desktop,mobile}/22-admin-webhook-registered.png` + `23-admin-webhook-delivery-log.png` (admin register → show-once signing secret → create public topic → worker delivery log with `topic.created`).
- Foundation remainder (F2/F4/F6/F7/F8/F10/F11): `tests/Unit/Support/CoreVersionTest.php`,
  `tests/Unit/Security/DataClassesTest.php`, `tests/Unit/Support/SigningHarnessTest.php`,
  `tests/Integration/Core/RegistryFixturesTest.php`, `tests/Integration/Security/ReauthGateTest.php`,
  `tests/Unit/Support/LogRedactorTest.php`, `tests/Unit/Core/TelemetryRedactionTest.php`,
  `tests/Unit/Core/Phase5EvidenceMapTest.php`, `tests/Unit/Core/ThreatModelIndexTest.php`,
  `AppFeatureFlagTest::test_core_forum_survives_with_every_feature_flag_disabled`.
- Increment 1 (P5-08 resolver shadow): `tests/Unit/Security/CapabilityRulesTest.php`,
  `tests/Integration/Security/CapabilityResolverTest.php`, `tests/Integration/Service/LegacyAuthorityProjectionTest.php`,
  `tests/Integration/Service/ResolverShadowTest.php`, `tests/Integration/Service/ResolverParityTest.php` (zero-mismatch exit gate),
  `tests/Integration/Service/RoleServiceTest.php`, `tests/Integration/Service/PermissionSimulatorTest.php`,
  `tests/Integration/Core/AppRoleAdminTest.php`, `AppFeatureFlagTest::test_capabilities_flag_gates_role_routes`.
- Parity corpus: `docs/evidence/phase5/resolver-parity.md` (1551 tuples, 0 mismatches, fixture v2 + commit pinned).
- Resolver budget: `docs/evidence/phase5/performance-budgets.md` — `resolver.p95` MEASURED (PASS) vs 5 ms.
- Role editor/simulator browser evidence: `docs/evidence/browser/{desktop,mobile}/30-admin-role-created.png` + `31-admin-role-simulator.png` (+ axe green).
- Increment 4 (P5-03 declarative themes): `tests/Unit/Security/Packages/ThemeTokenPolicyTest.php`,
  `tests/Unit/Service/Packages/ThemeBuildCssTest.php`,
  `tests/Unit/Service/Packages/ThemeAssetScannerTest.php`,
  `tests/Integration/Service/ThemeBuildServiceTest.php`,
  `tests/Integration/Service/ThemeLifecycleIntegrationTest.php`,
  `tests/Integration/Service/ThemeBudgetTest.php`,
  `tests/Integration/Worker/PackageHealthWorkerTest.php`,
  `tests/Integration/Core/AppPhase5ThemeSchemaTest.php`,
  `tests/Integration/Core/AppThemePackageTest.php`.
- Theme browser evidence: `docs/evidence/browser/{desktop,mobile}/39-admin-themes-preview.png`,
  `40-admin-theme-active.png`, `41-admin-theme-safe-mode.png`, and
  `42-admin-theme-rollback.png` (+ axe green).
- Theme operations docs: `docs/runbooks/package_themes.md`,
  `docs/phase5/registry-protocol.md` (Inc 4 theme block/build/serving
  contract), and `docs/evidence/phase5/performance-budgets.md`
  (`theme.build_apply_p95` MEASURED (PASS)).
- TOTP browser/no-JS retrofit: `tests/browser/totp.spec.ts` and
  `docs/evidence/browser/{desktop,mobile}/totp-*.png`.
- Increment 7 passkeys: `tests/Unit/Auth/WebAuthnPolicyTest.php`,
  `tests/Integration/Core/AppPasskeyRegistrationTest.php`,
  `tests/Integration/Core/AppPasskeyLoginTest.php`,
  `tests/Integration/Core/AppPasskeyRecoveryTest.php`,
  `tests/browser/passkeys.spec.ts`, `docs/evidence/phase5/passkeys.md`,
  `docs/runbooks/passkeys.md`, and `docs/evidence/phase5/performance-budgets.md`
  (`webauthn.ceremony_p95` MEASURED (PASS)).
- Requirement ledger: `docs/phase5/requirement-ledger.json` (R0-R5 states + per-flag rollback map, machine-checked).
- Threat models: `docs/phase5/threat-models/` (6 dossiers + `fixtures.json`, 48 negative-fixture stubs; TM-SE-01 and TM-TH-01..07 implemented).
- **Clean-install evidence:** the test bootstrap (`tests/bootstrap.php`) `migrate:fresh`-es all migrations on every PHPUnit run; full suite **1268 tests / 6619 assertions green** on the Inc 4 post-fix fresh-schema gate, with two consecutive reused-schema runs also green.
- **Browser evidence:** `npm run evidence` -> **53 passed / 1 skipped** on desktop + mobile; includes admin API-token, domain webhook, role-editor, permission-simulator, package lifecycle, and theme preview/activate/safe-mode/rollback no-JS journeys. `npm run a11y` -> **14 passed**.
- **Worker smoke:** `DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32 MAIL_DRIVER=array php bin/console worker:webhooks` → `delivered=0 retrying=0 dead=0 skipped=0`.
- **Populated-upgrade rehearsal:** `APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force` -> **PASS 17/17** (`0049`-`0072` applied on seeded Phase-1 data; `oauth_identities` ENUM->VARCHAR widen included; Phase-1 columns intact; zero data loss).
- **Legacy ledger probe:** scratch `schema_migrations(version, applied_at)` normalized to `schema_migrations(name, applied_at)` and `Migrator::status()` reported `0001_users` as applied (`status-ok columns=name,applied_at`).
- **Independent adversarial review:** 5-dimension review (DDL correctness, §8.2 completeness, security invariants, inertness, doc accuracy) with each finding re-verified against source. 5 findings confirmed / 10 rejected; all 5 resolved in this increment — `credential_id` widened to `VARBINARY(1023)` (WebAuthn L2 max), secret/separation invariants locked in the shape test, and two ADR-0004 wording fixes.
- Entry/decision record: `docs/adr/0004-phase-5-entry-and-carryover.md`; carryover source `docs/adr/0003-phase-4-closeout-deferrals.md`.

## Recommended next increments

Per `PHASE_5_PLAN` §13.1 staged order - each behind its dark flag, with shadow/
parity and adversarial evidence before enablement. Items 1-2 below landed
(retained for the original staged-order context; see their own "Increment N
landed" sections above for the full record):

1. ~~**Package integrations / publisher console** (Inc 5)~~ — **landed
   2026-07-03**, deploy-dark behind `package_registry`.
2. ~~**Resolver enforcement cutover** (P5-08/P5-09, Inc 6)~~ — **landed
   2026-07-05**, deploy-dark behind `capabilities` (`CAPABILITIES_MODE`
   posture lever). The remaining protected-owner-loss paths (passkey removal,
   sole-provider unlink, invitations) stay with Inc 7-9 as originally staged.
3. ~~**Generic OIDC + provider migration** (P5-12, Inc 8)~~ — **landed
   2026-07-07**, deploy-dark behind `provider_registry`;
   `soleMethodAccounts()` is wired into the provider-disable confirm page as
   originally required (see "Increment 8 landed" above).
4. ~~**Invitations** (P5-13, Inc 9)~~ — **landed 2026-07-08**, deploy-dark
   behind `invitations` with an explicit `registration_mode = invite`
   (see "Increment 9 landed" above).

With the staged §13.1 increment list complete through Inc 9, P5-16 closeout
evidence has now been collected for GA-DOD-17 through GA-DOD-22, and ADR 0017
records product-owner acceptance for GA-DOD-23 on 2026-07-09. Staged enablement
(R5) remains per subsystem. Gate B workstreams (P5-05/06 sandbox, P5-10
governance, P5-14/15) stay reserved behind their dark flags.

## P5-16 closeout evidence (2026-07-09)

P5-16 closeout evidence has been collected for Gate A migration/backup rehearsal, accessibility/no-JS browser coverage, runbook rehearsals, full regression and route-permission matrix, public-package-disabled operation, defect sweep, and documentation/evidence reconciliation. Evidence entry point: `docs/evidence/phase5/gate-a-closeout.md`.

Gate A product-owner acceptance is recorded in `docs/adr/0017-phase-5-gate-a-closeout.md`. Gate B workstreams (`server_extensions`, `governance`, `service_principals`, `verified_links`) remain reserved and are not silently completed by this closeout.

## Operating note

Local dev/test DB is the `forum-software-db-1` container (mysql:8.4, host port
**3307**, user/pass `retro`/`retro`). A gitignored `.env` points at it
(`DB_PORT=3307`); the test DB `retroboards_test` and the `retroboards_upgrade_verify`
scratch DB are provisioned with grants for `retro`.
