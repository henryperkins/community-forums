# Design: Phase 5 Increment 5 - Package Integrations and Security Response

**Date:** 2026-07-03  
**Status:** Accepted for implementation planning — the **whole** Increment 5 (SP0 B2 retrofits + P5-04 + P5-07-A) approved as a single implementation plan on 2026-07-03; the §12 open review decisions are adopted per their stated recommendations (reuse `api_tokens.created_by` + `installed_package_credentials` link; link webhooks via that table with no B2 table mutation; no new `worker:advisories`; widen `package_history.event` only if tests prove detail-only history unclear; jobs stay consent/visibility metadata only).  
**Phase / gate:** Phase 5 Gate A, Increment 5. Covers **P5-04** and **P5-07-A part 2**.  
**Primary flags:** `package_registry`, `service_secrets`, `api_tokens`, `webhooks`, `first_party_hooks`.  
**Precedence:** `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > surface specs > this draft. Where this draft conflicts with an authoritative document, correct this draft.

---

## 0. Context

Increments 2-4 made the signed package system useful but not yet integrated:

- Inc 2 verifies registry snapshots, trust roots, source pinning, advisories, and read-only catalogue browse.
- Inc 3 verifies release artifacts, manifests, install/consent/enable/update/rollback/uninstall, permission snapshots, health checks, and install-side exact-digest review enforcement.
- Inc 4 gives `theme` packages a deterministic CSS runtime behind `package_themes`.
- B2 prerequisites exist deploy-dark: `SecretVault`, read-only API tokens, outbound webhooks, and first-party hook producers.

Increment 5 animates **non-theme Gate A packages** without adding untrusted local code. A reviewed `remote_app` or declarative `automation` package can receive configured domain events through the existing webhook delivery engine and can call approved read-only API scopes through an install-scoped token. It also completes the local operator security-response console around publisher keys, exact-digest decisions, advisories, revocation, emergency disable, and transparency history.

## 1. Design Options

### Recommended: install-scoped integration runtime plus local security-response console

Build a narrow runtime bridge over the existing B2 seams. Package manifests remain the source of requested `api_scope`, `event`, `data_class`, `outbound_host`, and `job` declarations; local grants remain the actual authority. Settings are stored per installed package, secrets go through `SecretVault`, API credentials are generated as install-scoped tokens, and outbound event delivery is represented as package-owned webhook endpoints. The security console remains local/operator-owned and records decisions tied to exact digests.

This fits Gate A because it gives operators a real remote/declarative integration path while preserving the rule that public packages cannot execute PHP, run JS, mutate core schema, or become synchronous dependencies of core routes.

### Alternative: security-response console only

Finish publisher/advisory/emergency controls first and leave remote apps inert. This reduces implementation risk, but it would not satisfy P5-04 or GA-DOD-08, and it would keep non-theme packages as installable metadata only.

### Alternative: full service-principal model now

Introduce app identities, callback credentials, rotation policy, and broader API scopes in this increment. That would blur Gate A with Gate B P5-14 and risks turning remote apps into quasi-human actors before the service-principal authorization model is reviewed. Do not do this in Inc 5.

## 2. Scope

In scope:

- `remote_app` and declarative `automation` packages installed through the Inc 3 package lifecycle.
- Per-install settings validation from `rb-manifest.v2` `settings_schema`.
- Secret settings stored as `svcsec_*` references through `SecretVault`.
- Install-scoped API token provisioning for manifest-declared `api_scopes`, constrained to currently supported `ApiScopes`.
- Package-owned webhook endpoint provisioning for manifest-declared domain `events`, using the existing `WebhookService`/delivery worker.
- Endpoint-level data-class disclosure and local grant enforcement for `data_classes`.
- Disable/uninstall/export behavior that revokes package-owned credentials and pauses package-owned event delivery before retaining or deleting data.
- Publisher key lifecycle, publisher suspension, exact-digest review decision visibility, signed publication/revocation evidence, advisory escalation, local emergency disable, and transparency-log views.
- Browser/no-JS/admin evidence, threat-model fixture updates, runbooks, budget updates where measurable, and ledger updates.

Out of scope:

- Public/untrusted PHP, sandbox workers, server-extension SDK, or broker RPC.
- Full Gate B service principals. Inc 5 credentials are install-scoped records linked to existing API-token/webhook primitives, not independent actors with human-equivalent capability grants.
- Write APIs, human impersonation, private-content API scopes, arbitrary mutation endpoints, browser JavaScript supplied by packages, local expression languages, or scheduled local package jobs.
- Generic OIDC provider implementation, invitations, governance groups/approvals/access review, verified profile links, and usernameless passkeys.
- Automatic updates. Gate A stays manual/notify only.

## 3. Entry Conditions

Inc 5 implementation should not begin until these are true:

- `package_registry` Inc 2/3 evidence remains green and package lifecycle surfaces still fail closed while dark.
- B2 slices used by this increment are at least release-evidence ready for their specific use:
  - `service_secrets`: storage/rotate/revoke/prune and redaction evidence.
  - `api_tokens`: authorization matrix for read-only scopes and admin/token a11y evidence.
  - `webhooks`: SSRF/egress adversarial proof, delivery idempotency, browser/a11y evidence.
  - `first_party_hooks`: private-content absence proof for emitted domain events.
- The migration allocation is `0073_phase5_package_integrations`.
- `service_secrets` is enabled before any provider, webhook, or remote-app credential can be minted.

If the B2 slices remain R3, Inc 5 may still be implemented deploy-dark, but it cannot advance GA-DOD-08 to release-verified or be enabled broadly.

## 4. Data Model

Migration `0073_phase5_package_integrations` adds the missing per-install integration state. Existing `installed_packages.settings_json` remains a summary/cache for non-secret display; the normalized table below is authoritative for settings values and secret references.

### `installed_package_settings`

Stores validated settings for one installed package. A setting is either a JSON value or a `SecretVault` reference, never both.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `installed_package_id` | BIGINT UNSIGNED NOT NULL | FK to `installed_packages(id)` ON DELETE CASCADE |
| `setting_key` | VARCHAR(80) NOT NULL | Must match manifest `settings_schema.fields[].key` |
| `value_json` | MEDIUMTEXT NULL | JSON-encoded non-secret value |
| `secret_ref` | VARCHAR(64) NULL | `svcsec_*` reference for secret fields |
| `is_secret` | TINYINT(1) NOT NULL DEFAULT 0 | Rendering hint and invariant |
| `updated_by` | BIGINT UNSIGNED NULL | FK to `users(id)` ON DELETE SET NULL |
| `updated_at` | DATETIME NOT NULL | UTC |

Indexes: unique `(installed_package_id, setting_key)`, index `(secret_ref)`.

### `installed_package_credentials`

Links package-owned credentials to the install. The credential tables remain authoritative for authentication/delivery; this table provides package attribution, revoke/export behavior, and audit joins.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `installed_package_id` | BIGINT UNSIGNED NOT NULL | FK to `installed_packages(id)` ON DELETE CASCADE |
| `kind` | ENUM('api_token','webhook') NOT NULL | |
| `api_token_id` | BIGINT UNSIGNED NULL | FK to `api_tokens(id)` ON DELETE SET NULL |
| `webhook_id` | BIGINT UNSIGNED NULL | FK to `webhooks(id)` ON DELETE SET NULL |
| `label` | VARCHAR(120) NOT NULL | Operator-facing name |
| `scopes_json` | MEDIUMTEXT NULL | API-token scope snapshot |
| `events_json` | MEDIUMTEXT NULL | Webhook event snapshot |
| `created_by` | BIGINT UNSIGNED NULL | FK to `users(id)` ON DELETE SET NULL |
| `created_at` | DATETIME NOT NULL | UTC |
| `revoked_at` | DATETIME NULL | Set when disabled/uninstalled |

Invariants:

- `kind='api_token'` requires `api_token_id` and forbids `webhook_id`.
- `kind='webhook'` requires `webhook_id` and forbids `api_token_id`.
- Revoke/disable operations update the owning API token or webhook before marking this row revoked.

### Existing tables extended by behavior, not shape

- `installed_package_permissions`: Inc 3 already supports `api_scope` and `event`; Inc 5 uses only rows where `declared=1 AND granted=1`.
- `package_history`: existing `event` values are enough for settings/credential updates if detail JSON is explicit. Add a migration enum widen only if implementation needs distinct events such as `settings_update`, `credential_mint`, or `credential_revoke`.
- `package_transparency_log`: already append-only and sufficient for publication/revocation/advisory/security-response entries.
- `publisher_signing_keys`: already exists, but remains inert until Inc 5 services and console actions use it.

## 5. Integration Runtime

### Settings

`PackageSettingsService` validates submitted values against `PackageManifest::$settingsSchema`:

- Supported field types remain the Inc 3 schema types: `string`, `boolean`, `integer`, and `select`.
- Add `secret: true` as an optional setting-field attribute only if the implementation extends `ManifestValidator` and tests unknown-field refusal. Secret fields write through `SecretVault` and store only `secret_ref`.
- Unknown keys, missing required values, invalid select options, oversized strings, and type mismatches fail with `ValidationException` and preserve safe old input.
- Updating settings writes one transaction: settings rows, `installed_packages.settings_json` summary, `package_history`, and `moderation_log`.

### Remote app credentials

`PackageIntegrationService::provisionCredentials()` creates credentials only after:

- package type is `remote_app` or `automation`;
- install state is `enabled`;
- every required `api_scope`/`event` row is granted;
- `service_secrets`, `api_tokens`, and/or `webhooks` flags are enabled for the requested credential type;
- the operator has recent reauthentication for minting or rotating credentials.

API tokens:

- Mint one package-owned token per install using the declared/granted `api_scope` set.
- The token is shown once after provisioning and is stored hash-only in `api_tokens`.
- The token name includes package UID and installed package ID.
- The token never inherits the installing admin's role; audit points to the package install and the creating admin.
- Disabling/uninstalling the package revokes the token before any package state is marked inactive.

Webhooks:

- Provision a package-owned endpoint using settings-provided URL plus granted `event` declarations.
- Use `WebhookService` for egress validation, `SecretVault` storage, HMAC signing, delivery ledger, and worker behavior.
- `ping` remains operator-test only. Package event subscriptions use `WebhookEvents::domainEvents()`.
- Private/hidden-board and DM payload restrictions from `first_party_hooks` remain authoritative; Inc 5 must not broaden payloads just because a package asks for `content.private`.

### Declarative automations

Gate A declarative automation is intentionally narrow: event subscription plus remote delivery, settings, and scoped API reads. There is no local expression evaluator, no local action runner, and no mutation workflow. A package can declare what it wants to observe and what read scopes it needs; RetroBoards either grants and delivers those events or refuses.

## 6. Security Response Console

Inc 5 completes the local operator side of P5-07-A without creating a paid marketplace or publisher self-service portal.

Services:

- `PublisherTrustService`: verifies/suspends publishers, records publisher signing keys, revokes keys, and writes transparency entries.
- `PackageReviewConsoleService`: displays review decisions cached from signed release documents, records local manual decisions where policy allows, and ties every decision to package ID, release ID, version, digest, reviewer, evidence JSON, and timestamp.
- `PackageSecurityResponseService`: ingests signed publication/revocation/advisory envelopes, adds local emergency blocks, force-disables affected installs through `PackageHealthService::enforcePolicy()`, and writes package history/transparency entries.

Console surfaces:

- `/admin/packages/security`: publisher/key/advisory/transparency overview.
- `/admin/packages/publishers/{id}`: publisher status, keys, packages, decisions, suspension/reinstatement.
- Existing `/admin/registries` advisory and blocklist controls stay available; Inc 5 may consolidate links but should not duplicate source-of-truth logic.

Emergency disable:

- A local setting such as `package_execution_disabled=1` pauses all package-owned runtime bridges regardless of registry availability.
- Theme safe mode remains separate and flag-independent.
- Disabling execution must not prevent operators from viewing package records, revoking credentials, exporting data, or uninstalling packages.

## 7. Runtime Authorization Rules

- Manifest declarations are a ceiling; granted permission rows are actual authority.
- `api_scope` grants allow only `ApiScopes` known to the local code. Unknown future scopes remain denied until local code supports them.
- `event` grants allow only `WebhookEvents::domainEvents()`; `ping` cannot be package-granted.
- `data_class` grants disclose risk and review state but do not by themselves expose payloads. Payload-producing services must still implement read gates and minimization.
- `outbound_host` grants restrict remote endpoints. A configured endpoint host must match a granted outbound host unless it is a same-origin operator-controlled test endpoint explicitly allowed by config.
- Revoked, locally blocked, unreviewed, disabled, quarantined, uninstalled, or emergency-disabled installs cannot receive events or authenticate package-owned credentials.

## 8. Error Handling

- Settings validation failures re-render the package detail/settings page with 422 and safe old input.
- Credential minting failures are atomic: no token/webhook row may survive without an `installed_package_credentials` link and package-history audit.
- If `SecretVault` is unavailable or disabled, secret settings and webhook provisioning fail closed with a validation error; existing revoke/export operations remain possible.
- If API token minting fails after a webhook has been created, the transaction rolls back the linkage and revokes any already-created secret/endpoint before returning.
- If a package is force-disabled while deliveries are queued, the worker skips or cancels package-owned deliveries before attempting egress.
- Advisory/key/signature refusals use coded `RegistryVerificationException`/policy errors and never degrade into warnings.

## 9. Operator UX

Package detail gains an "Integration" section when the installed package is `remote_app` or `automation`:

- settings form generated from `settings_schema`;
- permission/grant summary with data classes, API scopes, events, outbound hosts, jobs;
- credential panel showing package-owned API tokens/webhooks by label/status, never plaintext after the one-time reveal;
- buttons for provision/rotate/revoke credentials, disable integration, export settings, and uninstall;
- clear copy that enabled packages still run remotely/declaratively only and execute no local PHP/JS.

No-JS requirement: all operations are ordinary forms with CSRF fields. JavaScript may later enhance copy buttons, but the one-time secret/token reveal must work server-rendered.

## 10. Evidence and Acceptance Targets

Ledger targets:

- `GA-DOD-08`: advance from R1 to R3/R4 when remote/declarative integration install, consent, pin/update/rollback, disable, export/uninstall, secret handling, scope checks, and outage behavior are tested.
- `GA-DOD-09`: advance from R2 once publisher/key/review/advisory/emergency-disable console behavior is implemented and evidenced.
- `SLICE-API-TOKENS`, `SLICE-WEBHOOKS`, `SLICE-SERVICE-SECRETS`, and `SLICE-FIRST-PARTY-HOOKS`: add missing release/adversarial evidence needed for Inc 5 enablement.

Threat fixtures to implement or update:

- `TM-SC-08`: undeclared scope/host exercised at runtime is denied and audited.
- `TM-SE-02`: reading webhook/token/provider config after save returns no plaintext.
- `TM-SE-04`: non-owning consumer reveal attempt fails and audits.
- `TM-SE-05`: forced vault failure yields only `svcsec_*` reference/context, no plaintext.
- Publisher-compromise and revoked-release scenarios from `PHASE_5_PLAN` section 9 should map to security-console tests even if no new threat-model IDs are introduced.

Test layers:

- Unit: settings-schema validation, host/scope/event grant mapping, emergency-disable policy, publisher-key status transitions.
- Integration: migration `0073`, settings storage with secret refs, credential provisioning rollback, token/webhook revocation on disable/uninstall, advisory/revocation enforcement.
- HTTP/application: package settings and credential forms, direct POST denials, noindex, CSRF, reauth.
- Browser/a11y: package integration settings, one-time credential reveal, security-response console.
- Worker: package-owned delivery suppression under disable/quarantine/emergency disable; advisory enforcement through existing registry refresh or explicit advisory worker/alias.
- Operational: remote outage, service-secret unavailable, registry unavailable, emergency disable, backup/restore, uninstall/export.

## 11. Runbooks and Docs

Update or add:

- `docs/runbooks/package_integrations.md`
- `docs/runbooks/package_registry.md` (security-response additions)
- `docs/phase5/registry-protocol.md` (remote-app settings/credential/security-response contract)
- `SCHEMA.md` for migration `0073`
- `PHASE_5_STATUS.md`
- `docs/evidence/deploy-dark-features.md`
- `docs/phase5/requirement-ledger.json`
- `docs/phase5/threat-models/fixtures.json`

## 12. Open Review Decisions

These are explicit review points before implementation planning:

1. **API-token ownership:** existing `api_tokens.created_by` is a human provenance FK. Inc 5 can either reuse it with an install link table, or widen the token model with `owner_type/owner_id`. Recommendation: reuse it for Gate A and link through `installed_package_credentials`; full owner-type principals wait for Gate B P5-14.
2. **Webhook ownership:** existing `webhooks` has no package owner column. Recommendation: link through `installed_package_credentials` rather than mutating the webhook table, so operator-created and package-owned endpoints share the delivery engine without changing B2 semantics.
3. **`worker:advisories`:** the program plan names a dedicated advisory worker, but `RegistryRefreshWorker` already fetches advisories and reconciles installs. Recommendation: avoid a second worker unless operators need advisory-only polling; otherwise add a console/runbook path and optional command alias.
4. **Distinct history events:** existing `package_history.event` may be enough with structured `detail`. Recommendation: only widen the enum in `0073` if tests prove detail-only history is unclear for settings and credential lifecycle.
5. **Jobs declarations:** manifests can declare jobs, but Inc 5 should not run local package jobs. Recommendation: preserve jobs as consent/visibility metadata and defer scheduling/execution to a later explicit runtime.

## 13. Handoff

After review, convert this draft into `docs/superpowers/plans/2026-07-03-phase5-increment5-package-integrations-security-response.md` using the writing-plans workflow. The implementation plan should decompose Inc 5 into at least these task groups:

0. **SP0 — B2 release-evidence retrofits** (prerequisite for enablement, may run parallel to build): `api_tokens` read-only authorization matrix + admin/token a11y; `webhooks` SSRF/egress adversarial proof + delivery idempotency + browser/a11y; `service_secrets` redaction + revoke/prune coverage; `first_party_hooks` private-content-absence proof. Inc 5 code may land deploy-dark before SP0 is green, but GA-DOD-08/09 cannot reach R4/R5 and no staged enablement occurs until SP0 evidence is green;
2. settings-schema validation and secret storage;
3. install-scoped API-token provisioning;
4. package-owned webhook provisioning and event gating;
5. disable/uninstall/export cleanup;
6. publisher/key/security-response console;
7. evidence, runbooks, ledger, and browser/a11y closeout.
