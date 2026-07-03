# Increment 5 — Package Integrations & Security Response Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (one subagent per task group, in listed order) or `superpowers:executing-plans` with review checkpoints to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Every task is TDD-first (`superpowers:test-driven-development`): write the failing PHPUnit/browser test, then the code, then `composer test`. Do not claim a task done without `superpowers:verification-before-completion` evidence.

**Goal:** Animate non-theme Gate A packages (`remote_app` / declarative `automation`) with install-scoped settings, secret storage, read-only API tokens, and package-owned webhook event delivery, and complete the local operator security-response console (publisher keys, exact-digest review, advisories, emergency disable, transparency) — all deploy-dark behind `package_registry`.

**Architecture:** A thin integration runtime layers over the already-landed B2 seams (`SecretVault`, `ApiTokenService`, `WebhookService`, `first_party_hooks` producers) and the Inc 3 package lifecycle engine — manifests declare the ceiling (`api_scope`/`event`/`data_class`/`outbound_host`/`job`), granted permission rows are the authority, per-install settings and credentials are new normalized tables (`installed_package_settings`, `installed_package_credentials`) added by migration `0073`. Package-owned credentials are minted through the existing `ApiTokenService`/`WebhookService` (never reusing a human token), runtime API-token auth is denied by `PackageCredentialAuthGuard` when the owning install is unsafe, credentials are revoked/paused before any state flips inactive via `PackageIntegrationService::onInstallIneligible()` wired into lifecycle/health services, and stale credentials are reconciled after update/rollback grant replacement via `PackageIntegrationService::onGrantsChanged()` wired into `PackageUpdateService`. The security console reuses `RegistryAdvisoryService`/`LocalBlocklistService`/`PackageHealthService::enforcePolicy()` (no new worker) and adds `PublisherTrustService`, `PackageReviewConsoleService`, and a flag-independent emergency-disable brake.

**Tech Stack:** Vanilla PHP 8.2+ (`App\` → `src/`, PSR-4), MySQL/MariaDB via the file-based `Migrator`, server-rendered plain-PHP templates + progressive-enhancement JS, PHPUnit (in-process kernel) + Playwright/axe browser evidence. No framework, no autowiring, short-polling only, strict CSP (no inline script/style).

## Global Constraints

- **Deploy-dark, default-false:** all new routes/controllers gate on the existing `package_registry` flag (`FeatureFlags::enabled('package_registry')` fails dark); the `gate()` helper throws `NotFoundException` (404, not 403/405) before and after `requireAdmin()`. No new feature flag is added — Inc 5 consumes `package_registry` + `service_secrets`/`api_tokens`/`webhooks`/`first_party_hooks` (all already declared in `DEFAULTS`).
- **`service_secrets` is a hard runtime predecessor / kill switch:** no provider, webhook, secret-setting, or remote-app credential may be minted unless `service_secrets` is enabled; secret settings and webhook provisioning **fail closed with `ValidationException`** when the vault is disabled/unavailable (reveal/revoke/export still work dark).
- **Additive-only migration `0073_phase5_package_integrations`** (next gapless number, confirmed after `0072`): `up()` additive, `down()` drops FKs/new-enum rows before tables/columns; `MigrationLedgerTest` requires contiguous numbering.
- **`EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (int-cast + concatenate after clamping); never reuse a named placeholder twice; UTC everywhere (`UTC_TIMESTAMP()`/`gmdate()`); packed IPs unchanged.
- **Strict CSP + no-JS-first:** every settings/credential/console operation is an ordinary `<form method="post">` with `$this->csrfField()`; the one-time secret/token reveal must render server-side. JS may only later enhance copy buttons — no inline `<script>`/`<style>`.
- **CSRF on every POST; `ReauthGate::requirePassword` on every high-impact mutation** (credential mint/rotate, secret-setting write, publisher verify/suspend/reinstate/key-pin/rotate/revoke, local review decision, emergency-disable toggle). Reauth is done in the **service** layer; the controller passes `(string) $request->post('current_password','')` and catches `ValidationException` to re-render `422` with `$e->errors` + safe old input (anti-draft-loss). Friction-free defensive directions (pausing delivery, adding a block, revoking a credential during disable/uninstall) skip reauth.
- **`$db->transaction(fn)` wraps every multi-table write; nested calls join the outer transaction** (`Database::transaction` reuses an active PDO transaction, no savepoints) — so `provisionCredentials()` wraps webhook+token minting + link inserts in one outer transaction giving true atomicity (a later token-mint failure rolls back the earlier webhook/secret/link). Any new denormalized pointer must have a matching `RepairService` recompute; `RepairService` advisory_status reconcile stays authoritative.
- **Manifest = ceiling, grants = authority:** runtime uses only `installed_package_permissions` rows where `declared=1 AND granted=1`; unknown/ungranted scopes/events/hosts are denied and audited (`TM-SC-08`).
- **`api_scope` grants ⊆ `ApiScopes::isValid`** (unknown future scopes denied until local code supports them); **`event` grants ⊆ `WebhookEvents::domainEvents()`** (`ping` can never be package-granted — operator-test only); **never broaden private/DM payloads** because a package declared `content.private` (`first_party_hooks` payload minimization stays authoritative — IDs+enums only).
- **Human-token separation:** package-owned tokens are `ApiPrincipal`s (no role, never a `User`); `api_tokens.created_by` records the minting admin for provenance only; ownership/attribution lives in `installed_package_credentials`. No `owner_type/owner_id` widening of the B2 tables (Gate B P5-14).
- **Secret material is write-only and referenced only by `svcsec_*`:** plaintext is never returned by `metadata`/settings reads, never written to `moderation_log`/`package_history`/`package_transparency_log`, never in exception messages (`TM-SE-02/04/05`).
- **Fail-closed on outage:** registry/vault/remote unavailability yields coded `RegistryVerificationException`/`PackagePolicyException`/`ValidationException`, never a silent warning or degraded grant; revoked/blocked/unreviewed/disabled/quarantined/uninstalled/emergency-disabled installs cannot receive events or authenticate credentials.
- **§12 adopted decisions (locked):** (1) reuse `api_tokens.created_by` + link via `installed_package_credentials`; (2) link webhooks via `installed_package_credentials` with **no** `webhooks`-table mutation; (3) **no** `worker:advisories` — advisory ingest/escalate stays on `worker:registry-refresh` (fetch/reconcile) + `worker:packages` (enforce); (4) widen `package_history.event` with `settings_update`/`credential_mint`/`credential_revoke` because credential/settings lifecycle is distinct from install/health/export and drives the credential panel + transparency correlation; (5) `jobs` stay consent/visibility metadata only — no local job scheduler/runner.
- **Global emergency disable is flag-independent:** `package_execution_disabled` is a DB setting (`SettingRepository`) OR'd with a `packages.execution_disabled` env/config break-glass; when on it pauses all package-owned webhook endpoints (`is_active=0`, so the existing delivery worker/`dispatch()` naturally skip them) and denies package-owned credential authentication, while still permitting operators to view records, revoke credentials, export data, and uninstall.

## File Structure

### New files

| File | Responsibility |
|---|---|
| `database/migrations/0073_phase5_package_integrations.php` | Additive migration: `installed_package_settings` + `installed_package_credentials` tables; widen `package_history.event` (+3) and `moderation_log.target_type` (+`publisher`). |
| `src/Repository/InstalledPackageSettingsRepository.php` | Single-table wrapper for `installed_package_settings` (upsert/read/delete + secret-ref harvest for cleanup). |
| `src/Repository/InstalledPackageCredentialRepository.php` | Single-table wrapper for `installed_package_credentials` (insert api_token/webhook links, revoke, lookups by owning token/webhook). **Single owner:** Task 18 creates the full api_token + webhook surface; later tasks only append tests or repair missing methods without replacing the file. |
| `src/Repository/PublisherSigningKeyRepository.php` | Single-table wrapper for `publisher_signing_keys` (mirrors `RegistryTrustKeyRepository`, keyed on `publisher_id`). |
| `src/Service/Packages/PackageSettingsService.php` | Validates submitted settings against manifest `settings_schema`; secret fields → `SecretVault`, non-secret → `value_json`; writes settings rows + summary + history + audit in one transaction. |
| `src/Service/Packages/PackageIntegrationService.php` | Install-scoped credential provisioning/rotation/revocation + event/scope/outbound-host gating + delivery pause/resume + the `onInstallIneligible()` lifecycle hook + post-update grant reconciliation + integration read model. |
| `src/Service/Packages/PackageCredentialAuthGuard.php` | Package-owned API token runtime guard: human tokens pass through, linked package tokens fail closed when execution is disabled, the install is not enabled, or the package/release is no longer locally acceptable. |
| `src/Service/Packages/PackageReviewConsoleService.php` | Displays cached signed review decisions; records local manual decisions (tightening-only) tied to package/release/version/digest/reviewer/evidence. |
| `src/Service/Packages/PackageSecurityResponseService.php` | Security-console read model + flag-independent emergency execution disable; reuses (does not duplicate) advisory/blocklist/enforcement services. |
| `src/Service/Registry/PublisherTrustService.php` | Publisher verify/suspend/reinstate + signing-key pin/rotate/revoke (reauth-gated), cascading force-disable on suspension via `PackageHealthService::enforcePolicy()`/security response. |
| `src/Controller/AdminPackageIntegrationController.php` | No-JS integration POSTs under `/admin/packages/{id}/integration/*` (settings, provision, rotate/revoke credential, disable integration, export settings). **Single owner:** Task 42 creates the controller with the redacted export body from Task 38; Task 38 may add tests before that but must not introduce a competing implementation. |
| `src/Controller/AdminPackageSecurityController.php` | Security-response console GET/POSTs (`/admin/packages/security`, `/admin/packages/publishers/{id}`, publisher/key/review/emergency-disable actions). **Cumulative file:** the first task that touches it creates shared `gate()`/`noindex()` scaffolding; all later tasks append/modify methods in place and must preserve `publisher*`, `recordReview`, `index`, `emergencyDisable`, key actions, and helpers already present. |
| `templates/admin/_package_integration.php` | Partial: settings form + permission/grant summary + credential panel (labels/status, no plaintext) folded into package detail. |
| `templates/admin/package_security.php` | Security-response console overview (publishers, advisories, blocklist, transparency, emergency-disable toggle). |
| `templates/admin/package_publisher.php` | Publisher detail (status, keys, packages, review decisions, suspend/reinstate, key lifecycle forms). |
| `tests/Unit/Service/Packages/PackageSettingsSchemaTest.php` | Settings-schema validation (types/required/select/oversize/unknown-key/secret-field) pure/near-pure coverage. |
| `tests/Integration/Service/PackageIntegrationServiceTest.php` | Provision/rotate/revoke atomicity + one-live-credential-per-kind locking + scope/event/host gating + onInstallIneligible cleanup + post-update grant reconciliation + emergency-disable suppression. **Cumulative file:** Task 27 creates the scaffold; later tasks append cases/helpers and never replace the class. |
| `tests/Integration/Service/PackageCredentialAuthGuardTest.php` | Package-owned API token authentication denial when execution is disabled, linked credential revoked, install inactive, local block/advisory/review state is unsafe; proves human tokens are unaffected. |
| `tests/Integration/Service/PublisherTrustServiceTest.php` | Publisher verify/suspend/reinstate + key pin/rotate/revoke + suspension cascade. |
| `tests/Integration/Service/PackageSecurityResponseServiceTest.php` | Emergency-disable brake, overview read model, transparency entries, advisory/blocklist delegation. |
| `tests/Integration/Core/AppPackageIntegrationSchemaTest.php` | `0073` table/column/index/FK/enum shape assertions. |
| `tests/Integration/Core/AppPackageIntegrationTest.php` | Full-kernel HTTP: settings/credential/console forms, direct-POST denials, noindex, CSRF, reauth, flag-dark 404s. |
| `tests/Integration/Api/ApiAuthorizationMatrixTest.php` | **SP0** `/api/v1` read-only scope-denial authorization matrix (`SLICE-API-TOKENS`). |
| `tests/Unit/Security/EgressGuardAdversarialTest.php` + `tests/Integration/Worker/WebhookIdempotencyTest.php` | **SP0** SSRF/egress adversarial corpus + delivery idempotency report (`SLICE-WEBHOOKS`). |
| `tests/Integration/Service/SecretVaultRedactionTest.php` | **SP0** redaction + revoke/prune leak-proof coverage (`SLICE-SERVICE-SECRETS`, `TM-SE-02/04/05`). |
| `tests/Integration/Service/FirstPartyHookPrivateContentTest.php` | **SP0** private/hidden/DM content-absence proof for emitted domain events (`SLICE-FIRST-PARTY-HOOKS`). |
| `tests/browser/package-integrations.spec.ts`, `tests/browser/api-tokens.spec.ts`, `tests/browser/webhooks.spec.ts`, `tests/browser/package-security.spec.ts` | No-JS + axe browser evidence for integration settings, one-time credential reveal, admin token/webhook surfaces, security console. |
| `docs/runbooks/package_integrations.md` | New runbook (provision/rotate/revoke, secret handling, emergency disable, outage). |

### Modified files

| File | Change |
|---|---|
| `src/Core/App.php` | Add `use` imports + container binds for the 3 new repos + 6 new services; inject `PackageCredentialAuthGuard` into `ApiTokenService`; **append** `$c->get(PackageIntegrationService::class)` to the `PackageLifecycleService`, `PackageUpdateService`, and `PackageHealthService` binds; register the new integration + security routes in `buildRouter()`. |
| `src/Service/ApiTokenService.php` | Add an optional `?PackageCredentialAuthGuard $packageAuthGuard = null` tail dependency; after hash lookup and before `touchLastUsed`, deny linked package-owned tokens when the guard fails. |
| `src/Security/Packages/ManifestValidator.php` | Add `'secret'` to `SETTING_FIELD_KEYS`; validate `secret` as optional bool allowed only when `type==='string'`; still refuse unknown keys. |
| `src/Repository/InstalledPackageRepository.php` | Add `setSettingsSummary(int $id, ?string $json): void`. |
| `src/Repository/PackagePublisherRepository.php` | Add `find(int $id): ?array`, `all(): array`, `setStatus(int $id, string $status): void`, `markVerified(int $id, ?int $actorId): void`, `packagesFor(int $publisherId): array`. |
| `src/Service/Packages/PackageLifecycleService.php` | Append `?PackageIntegrationService $integrations = null` (last param, before existing trailing optionals stay valid); call `$this->integrations?->onInstallIneligible($installedId, 'disabled'\|'uninstalled', $admin->id())` **inside** the same `disable()`/`uninstall()` transaction, immediately before state flips inactive. |
| `src/Service/Packages/PackageUpdateService.php` | Append `?PackageIntegrationService $integrations = null`; call `onGrantsChanged(...)` inside the same update/rollback activation transaction immediately after `replaceWithGrants()` so stale package credentials are revoked before the new grant set can be used. |
| `src/Service/Packages/PackageHealthService.php` | Append `?PackageIntegrationService $integrations = null`; call `onInstallIneligible(..., 'quarantined'\|'force_disabled', null)` inside the same `quarantine()`/`securityDisable()` transaction immediately before the state flips inactive. |
| `templates/admin/package_detail.php` | Render `admin/_package_integration` partial when install type ∈ `remote_app`/`automation`; add links to the security console. |
| `config/config.php` | Add `packages.execution_disabled` (`Env::bool('PACKAGE_EXECUTION_DISABLED', false)`) break-glass fallback; optionally `packages.integration_test_origin` for the same-origin test-endpoint allowance (§7). |
| `SCHEMA.md` | Bump `v1.32`→`v1.33`; add the two new table shapes to §5B, a §9 changelog row for `0073`, note the two enum widens. |
| `PHASE_5_STATUS.md`, `docs/evidence/deploy-dark-features.md`, `docs/phase5/registry-protocol.md`, `docs/runbooks/package_registry.md` | Inc 5 status, deploy-dark inventory, remote-app settings/credential/security-response contract, security-response runbook additions. |
| `docs/phase5/requirement-ledger.json` | Advance `GA-DOD-08` R1→R3/R4, `GA-DOD-09` R2→R3, `SLICE-API-TOKENS`/`SLICE-WEBHOOKS`/`SLICE-SERVICE-SECRETS`/`SLICE-FIRST-PARTY-HOOKS` R3→R4 with evidence paths (in the landing commits only). |
| `docs/phase5/threat-models/fixtures.json` | Flip `TM-SC-08`, `TM-SE-02`, `TM-SE-04`, `TM-SE-05` from `stub`→`implemented` with `test` paths. |
| `tests/Integration/Core/AppFeatureFlagTest.php` | Extend the `package_registry` dark case with every new `/admin/packages/{id}/integration/*`, `/admin/packages/security`, `/admin/packages/publishers/{id}` route asserting 404-while-dark. |
| `tests/browser/package.json` | Add the four new spec files to the appropriate `evidence`/feature scripts. |

## Interface Contract

The signatures below are LOCKED. Drafting agents copy them verbatim; do not invent alternates.

### Migration `0073_phase5_package_integrations.php` (DDL)

```sql
CREATE TABLE installed_package_settings (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  installed_package_id BIGINT UNSIGNED NOT NULL,
  setting_key          VARCHAR(80)     NOT NULL,
  value_json           MEDIUMTEXT      NULL,
  secret_ref           VARCHAR(64)     NULL,
  is_secret            TINYINT(1)      NOT NULL DEFAULT 0,
  updated_by           BIGINT UNSIGNED NULL,
  updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_install_setting (installed_package_id, setting_key),
  KEY idx_install_setting_secret (secret_ref),
  CONSTRAINT fk_install_setting_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_install_setting_user    FOREIGN KEY (updated_by)           REFERENCES users(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installed_package_credentials (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  installed_package_id BIGINT UNSIGNED NOT NULL,
  kind                 ENUM('api_token','webhook') NOT NULL,
  api_token_id         BIGINT UNSIGNED NULL,
  webhook_id           BIGINT UNSIGNED NULL,
  label                VARCHAR(120)    NOT NULL,
  scopes_json          MEDIUMTEXT      NULL,
  events_json          MEDIUMTEXT      NULL,
  created_by           BIGINT UNSIGNED NULL,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at           DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_install_cred_install   (installed_package_id),
  KEY idx_install_cred_api_token (api_token_id),
  KEY idx_install_cred_webhook   (webhook_id),
  CONSTRAINT fk_install_cred_install   FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_install_cred_api_token FOREIGN KEY (api_token_id)         REFERENCES api_tokens(id)         ON DELETE SET NULL,
  CONSTRAINT fk_install_cred_webhook   FOREIGN KEY (webhook_id)           REFERENCES webhooks(id)           ON DELETE SET NULL,
  CONSTRAINT fk_install_cred_user      FOREIGN KEY (created_by)           REFERENCES users(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE package_history
  MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                    'uninstall','consent','health','update_staged','export','purge',
                    'theme_activate','theme_rollback','theme_deactivate',
                    'settings_update','credential_mint','credential_revoke') NOT NULL;

ALTER TABLE moderation_log
  MODIFY target_type ENUM('thread','post','user','board','category','setting',
                          'service_secret','api_token','webhook','registry','package','publisher') NOT NULL;
```
Application-enforced invariants (validated in the credential repo/service + asserted in tests, not by CHECK constraints): `kind='api_token'` ⇒ `api_token_id` NOT NULL and `webhook_id` NULL; `kind='webhook'` ⇒ `webhook_id` NOT NULL and `api_token_id` NULL; an install may have **at most one active (`revoked_at IS NULL`) credential per kind**, enforced by `PackageIntegrationService` locking the owning `installed_packages` row `FOR UPDATE` before it checks/inserts active credential links; a setting row has exactly one of `value_json`/`secret_ref` populated (`is_secret=1` iff `secret_ref` set). `down()` deletes the three new `package_history.event` rows + `moderation_log.target_type='publisher'` rows, narrows both enums, drops both tables.

### `App\Repository\InstalledPackageSettingsRepository` (final; constructor `(private Database $db)`)
```php
public function forInstall(int $installedId): array;                              // SELECT * ... ORDER BY setting_key
public function find(int $installedId, string $key): ?array;
public function upsert(int $installedId, string $key, ?string $valueJson, ?string $secretRef, bool $isSecret, ?int $updatedBy): int;  // ON DUPLICATE KEY UPDATE on uq_install_setting
public function secretRefsFor(int $installedId): array;                           // list<string> of svcsec_* refs (drives cleanup)
public function deleteFor(int $installedId): void;
```

### `App\Repository\InstalledPackageCredentialRepository` (final; constructor `(private Database $db)`)
```php
public function forInstall(int $installedId): array;                              // active + revoked, ORDER BY id DESC
public function activeForInstall(int $installedId): array;                        // revoked_at IS NULL
public function find(int $id): ?array;
public function findByApiToken(int $apiTokenId): ?array;                           // package-owned auth guard; revoked links remain visible and deny auth
public function findByWebhook(int $webhookId): ?array;
public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int;
public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int;
public function markRevoked(int $id): int;                                        // UPDATE ... SET revoked_at=UTC_TIMESTAMP() WHERE id=? AND revoked_at IS NULL; returns rowCount (idempotent)
public function deleteFor(int $installedId): void;
```

### `App\Repository\PublisherSigningKeyRepository` (final; constructor `(private Database $db)`) — mirrors `RegistryTrustKeyRepository`
```php
public function forPublisher(int $publisherId): array;                            // ORDER BY id DESC — list handed to TrustChainVerifier
public function find(int $id): ?array;
public function findKey(int $publisherId, string $keyId): ?array;
public function pin(int $publisherId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int;  // hardcodes algorithm='ed25519', status='active'
public function markRotated(int $id): void;                                       // status='rotated', valid_until=UTC_TIMESTAMP()
public function revoke(int $id, string $reason): void;                            // status='revoked', revoked_at=UTC_TIMESTAMP(), revoked_reason=?
```

### `App\Repository\PackagePublisherRepository` — ADDED methods (constructor unchanged)
```php
public function find(int $id): ?array;
public function all(): array;                                                     // ORDER BY display_name
public function setStatus(int $id, string $status): void;                         // status ∈ active|suspended|revoked
public function markVerified(int $id, ?int $actorId): void;                       // verified_at=UTC_TIMESTAMP()
public function packagesFor(int $publisherId): array;                             // packages WHERE publisher_id=?
```

### `App\Repository\InstalledPackageRepository` — ADDED method (constructor unchanged)
```php
public function setSettingsSummary(int $id, ?string $json): void;                 // UPDATE installed_packages SET settings_json=?, updated_at=UTC_TIMESTAMP() WHERE id=?
```

### `App\Service\Packages\PackageSettingsService` (final)
```php
public function __construct(
    private Database $db,
    private PackageRepository $packages,
    private PackageReleaseRepository $releases,
    private InstalledPackageRepository $installs,
    private InstalledPackageSettingsRepository $settings,
    private SecretVault $vault,
    private ManifestValidator $manifests,
    private PackageHistoryRepository $history,
    private ModerationLogRepository $audit,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private FeatureFlags $flags,
    private Config $config,
);

/** Schema + current values for rendering; secret fields report has_value:bool, never plaintext. */
public function describe(int $installedId): array;   // array{fields:list<array{key,type,label,required,options?,secret:bool}>, values:array<string,mixed>, has_secret:array<string,bool>}
/**
 * Validate $input against the active release manifest settings_schema and persist.
 * Non-secret → value_json; secret (type=string + secret:true) → SecretVault store/rotate, persist secret_ref only.
 * Requires service_secrets + reauth iff any secret field is written. One transaction:
 * settings rows + installs->setSettingsSummary + package_history('settings_update', detail) + moderation_log(target_type='package').
 * Throws ValidationException (->errors + safe ->old) on unknown key / missing required / bad select / oversize / type mismatch / vault disabled.
 */
public function save(User $admin, ?string $currentPassword, int $installedId, array $input): void;
```

### `App\Service\Packages\PackageIntegrationService` (final)
```php
public function __construct(
    private Database $db,
    private PackageRepository $packages,
    private PackageReleaseRepository $releases,
    private InstalledPackageRepository $installs,
    private InstalledPackagePermissionRepository $permissions,
    private InstalledPackageSettingsRepository $settings,
    private InstalledPackageCredentialRepository $credentials,
    private ApiTokenService $apiTokens,
    private WebhookService $webhooks,
    private ApiTokenRepository $apiTokenRepo,
    private WebhookRepository $webhookRepo,
    private SecretVault $vault,
    private ManifestValidator $manifests,
    private PackageHistoryRepository $history,
    private PackageTransparencyLogRepository $transparency,
    private ModerationLogRepository $audit,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private FeatureFlags $flags,
    private SettingRepository $settingRepo,   // reads package_execution_disabled
    private Config $config,
);

/** Read model for the integration panel; never returns plaintext. */
public function overview(int $installedId): array;   // array{type, integrable:bool, granted_scopes:list<string>, granted_events:list<string>, data_classes:list<array>, outbound_hosts:list<string>, jobs:list<array>, credentials:list<array{id,kind,label,status,scopes:list,events:list,created_at,revoked_at}>, settings_summary:array, execution_disabled:bool, refusal:?array{code,message}}
/**
 * Atomically mint package-owned credentials. Guards (all fail with ValidationException/PackagePolicyException,
 * mint nothing): type ∈ remote_app|automation; state==='enabled'; ungrantedCount===0; not emergency-disabled;
 * not blocked/quarantined/revoked; service_secrets enabled; reauth. Every requested event ∈ WebhookEvents::domainEvents()
 * (rejects ping); webhook URL host ∈ granted outbound_hosts (or config integration_test_origin). Mints ≤1 api_token
 * (granted api_scopes, name includes package UID + installed id) via ApiTokenService::mint and ≤1 webhook via
 * WebhookService::register, wrapped in ONE $db->transaction so a token-mint failure rolls back the webhook/secret/links.
 * Writes installed_package_credentials rows + package_history('credential_mint') + transparency('install', detail) + audit.
 * @return array{api_token:?string, webhook_secret:?string, credentials:list<array>}  // plaintext shown once
 */
public function provisionCredentials(User $admin, string $currentPassword, int $installedId): array;
/** Rotate one credential. api_token: revoke+mint new (tokens have no in-place rotate); webhook: WebhookService::rotateSecret. Reauth. @return array{secret:?string,token:?string} shown once. */
public function rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array;
/** Revoke one credential (ApiTokenService::revoke or WebhookService::delete) + markRevoked link + history('credential_revoke'). WriteGate only. */
public function revokeCredential(User $admin, int $installedId, int $credentialId): void;
/** Pause all package-owned webhook endpoints (webhookRepo->disable) for an install; no reauth (defensive). @return count paused. */
public function suspendDelivery(int $installedId, string $reason): int;
/** Re-enable package-owned webhooks on install re-enable, refused if emergency-disabled/blocked. @return count resumed. */
public function resumeDelivery(User $admin, int $installedId): int;
/** Lifecycle hook (mirrors ThemeStateService::onInstallIneligible): revoke all credentials + suspendDelivery. Idempotent, no reauth. reason ∈ disabled|uninstalled|quarantined|force_disabled|emergency_disabled. */
public function onInstallIneligible(int $installedId, string $reason, ?int $actorId): void;
/** Grant-reconciliation hook called by PackageUpdateService after permission rows change: revoke only active credentials whose scopes/events/host are no longer a subset of current granted rows. Idempotent, no reauth. @return number of credentials revoked. */
public function onGrantsChanged(int $installedId, string $reason, ?int $actorId): int;
/** True when the package_execution_disabled DB setting OR packages.execution_disabled config is set. */
public function isExecutionDisabled(): bool;
```

### `App\Service\Packages\PackageCredentialAuthGuard` (final)
```php
public function __construct(
    private InstalledPackageCredentialRepository $credentials,
    private InstalledPackageRepository $installs,
    private PackageRepository $packages,
    private PackageReleaseRepository $releases,
    private PackageAdvisoryRepository $advisories,
    private LocalPackageBlockRepository $blocks,
    private SettingRepository $settings,
    private Config $config,
);

/** Human/admin API tokens are allowed through. Package-owned tokens are allowed only while the linked credential is active, execution is not disabled, the install is enabled, the installed release remains approved, and no local/advisory block applies. */
public function allowsApiToken(int $apiTokenId): bool;
public function isExecutionDisabled(): bool; // DB setting OR packages.execution_disabled config break-glass
```

### `App\Service\Registry\PublisherTrustService` (final) — mirrors `RegistryTrustService`, keyed on publisher
```php
public function __construct(
    private Database $db,
    private PackagePublisherRepository $publishers,
    private PublisherSigningKeyRepository $keys,
    private PackageRepository $packages,
    private PackageTransparencyLogRepository $transparency,
    private TrustChainVerifier $verifier,
    private PackageHealthService $enforcement,   // suspension cascade
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private ModerationLogRepository $audit,
);

public function verifyPublisher(User $admin, string $currentPassword, int $publisherId): void;                 // markVerified + status active; audit publisher_verify (target_type='publisher')
public function suspendPublisher(User $admin, string $currentPassword, int $publisherId, string $reason): int; // atomically set status suspended + cascade force-disable installs of this publisher's packages via enforcement->enforcePolicy() + per-package transparency('force_disable'); audit publisher_suspend; @return affected installs
public function reinstatePublisher(User $admin, string $currentPassword, int $publisherId): void;              // status active; does NOT auto-re-enable installs; audit publisher_reinstate
public function pinKey(User $admin, string $currentPassword, int $publisherId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int;  // base64 decodes to exactly 32 bytes; audit publisher_pin_key
public function applyKeyRotation(User $admin, string $currentPassword, int $publisherId, string $documentJson, string $signature, string $keyId): int;  // verifier->verifyRotation (rb-key-rotation.v1); pin successor; markRotated old; audit publisher_rotate_key
public function revokeKey(User $admin, string $currentPassword, int $keyRowId, string $reason): void;          // audit publisher_revoke_key
```

### `App\Service\Packages\PackageReviewConsoleService` (final)
```php
public function __construct(
    private Database $db,
    private PackageRepository $packages,
    private PackageReleaseRepository $releases,
    private PackageReviewDecisionRepository $reviewDecisions,
    private PackageTransparencyLogRepository $transparency,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private ModerationLogRepository $audit,
);

/** Cached signed decisions + local decisions joined to release version/digest for display. */
public function decisionsFor(int $packageId): array;
/**
 * Record a LOCAL manual decision (source='local'), tied to package/release/version/digest + reviewer + evidence JSON + timestamp.
 * decision ∈ approved|rejected|revoked. Tightening only: a local 'approved' is refused (PackagePolicyException code 'review_conflict')
 * when a signed 'rejected'/'revoked' decision exists for the digest. Updates package_releases.review_status; transparency + audit. @return decision row id.
 */
public function recordDecision(User $admin, string $currentPassword, int $packageId, int $releaseId, string $decision, ?string $note): int;
```

### `App\Service\Packages\PackageSecurityResponseService` (final) — reuses, does not duplicate, advisory/blocklist/enforcement
```php
public function __construct(
    private Database $db,
    private SettingRepository $settings,
    private RegistryAdvisoryService $advisories,
    private LocalBlocklistService $blocklist,
    private PackageHealthService $enforcement,
    private PackageIntegrationService $integrations,
    private PackagePublisherRepository $publishers,
    private PublisherSigningKeyRepository $publisherKeys,
    private PackageAdvisoryRepository $advisoryRepo,
    private LocalPackageBlockRepository $blockRepo,
    private PackageTransparencyLogRepository $transparency,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private ModerationLogRepository $audit,
    private Config $config,
);

/** /admin/packages/security read model. */
public function overview(?\DateTimeImmutable $now = null): array;   // array{publishers, advisories, blocklist, transparency, execution_disabled:bool, affected_installs:int}
public function publisherDetail(int $publisherId): ?array;          // array{publisher, keys, packages, decisions}|null
/**
 * Flag-INDEPENDENT emergency execution brake. Reauth. Writes SettingRepository('package_execution_disabled', bool).
 * On disable: integrations->onInstallIneligible/suspendDelivery across all active integration installs so the
 * delivery worker/dispatch skip them + deny credential auth; audit package_execution_disabled/package_execution_enabled;
 * transparency per affected install. @return affected installs. Never blocks view/revoke/export/uninstall.
 */
public function setExecutionDisabled(User $admin, string $currentPassword, bool $disabled, ?string $reason): int;
public function isExecutionDisabled(): bool;   // DB setting OR packages.execution_disabled config break-glass
```

### Controllers (all actions `(Request $request, array $params): Response`; each calls `gate()` before `requireAdmin()` and again after auth [`package_registry` else `NotFoundException`], `noindex()` header, resolve `{id}` server-side via `requireInstallRow`, catch `ValidationException`/`PackagePolicyException|RegistryVerificationException` → re-render 422)

`App\Controller\AdminPackageIntegrationController`: `saveSettings`, `provision`, `rotateCredential`, `revokeCredential`, `disableIntegration`, `exportSettings` (returns `Response::json(...)` + `Content-Disposition`).
`App\Controller\AdminPackageSecurityController`: `index`, `publisher`, `emergencyDisable`, `recordReview`, `verifyPublisher`, `suspendPublisher`, `reinstatePublisher`, `pinPublisherKey`, `rotatePublisherKey` (parses `envelope` JSON `{document,signature(base64),key_id}` like `AdminRegistryController::rotate`), `revokePublisherKey`.

### Routes (add in `App::buildRouter()`; register the two non-numeric security GETs before `GET /admin/packages/{id}`)
```php
// Integration runtime (P5-04)
$r->post('/admin/packages/{id}/integration/settings',                       [AdminPackageIntegrationController::class, 'saveSettings']);
$r->post('/admin/packages/{id}/integration/provision',                      [AdminPackageIntegrationController::class, 'provision']);
$r->post('/admin/packages/{id}/integration/credentials/{credentialId}/rotate', [AdminPackageIntegrationController::class, 'rotateCredential']);
$r->post('/admin/packages/{id}/integration/credentials/{credentialId}/revoke', [AdminPackageIntegrationController::class, 'revokeCredential']);
$r->post('/admin/packages/{id}/integration/disable',                        [AdminPackageIntegrationController::class, 'disableIntegration']);
$r->post('/admin/packages/{id}/integration/export',                         [AdminPackageIntegrationController::class, 'exportSettings']);
// Security-response console (P5-07-A part 2)
$r->get ('/admin/packages/security',                    [AdminPackageSecurityController::class, 'index']);
$r->get ('/admin/packages/publishers/{id}',             [AdminPackageSecurityController::class, 'publisher']);
$r->post('/admin/packages/security/execution',          [AdminPackageSecurityController::class, 'emergencyDisable']);
$r->post('/admin/packages/{id}/review',                 [AdminPackageSecurityController::class, 'recordReview']);
$r->post('/admin/packages/publishers/{id}/verify',      [AdminPackageSecurityController::class, 'verifyPublisher']);
$r->post('/admin/packages/publishers/{id}/suspend',     [AdminPackageSecurityController::class, 'suspendPublisher']);
$r->post('/admin/packages/publishers/{id}/reinstate',   [AdminPackageSecurityController::class, 'reinstatePublisher']);
$r->post('/admin/packages/publishers/{id}/keys',        [AdminPackageSecurityController::class, 'pinPublisherKey']);
$r->post('/admin/packages/publishers/{id}/rotate',      [AdminPackageSecurityController::class, 'rotatePublisherKey']);
$r->post('/admin/publisher-keys/{id}/revoke',           [AdminPackageSecurityController::class, 'revokePublisherKey']);
```

### Container binds (add in `App::buildContainer()`; `$config` is in scope)
```php
$c->bind(InstalledPackageSettingsRepository::class,   fn (Container $c) => new InstalledPackageSettingsRepository($c->get(Database::class)));
$c->bind(InstalledPackageCredentialRepository::class, fn (Container $c) => new InstalledPackageCredentialRepository($c->get(Database::class)));
$c->bind(PublisherSigningKeyRepository::class,        fn (Container $c) => new PublisherSigningKeyRepository($c->get(Database::class)));
$c->bind(PackageSettingsService::class,       fn (Container $c) => new PackageSettingsService(/* Database, PackageRepository, PackageReleaseRepository, InstalledPackageRepository, InstalledPackageSettingsRepository, SecretVault, ManifestValidator, PackageHistoryRepository, ModerationLogRepository, ReauthGate, WriteGate, FeatureFlags, $config */));
$c->bind(PackageIntegrationService::class,    fn (Container $c) => new PackageIntegrationService(/* … + ApiTokenService, WebhookService, ApiTokenRepository, WebhookRepository, PackageTransparencyLogRepository, SettingRepository, $config */));
$c->bind(PackageCredentialAuthGuard::class,   fn (Container $c) => new PackageCredentialAuthGuard(/* InstalledPackageCredentialRepository, InstalledPackageRepository, PackageRepository, PackageReleaseRepository, PackageAdvisoryRepository, LocalPackageBlockRepository, SettingRepository, $config */));
$c->bind(PublisherTrustService::class,        fn (Container $c) => new PublisherTrustService(/* Database, PackagePublisherRepository, PublisherSigningKeyRepository, PackageRepository, PackageTransparencyLogRepository, TrustChainVerifier, PackageHealthService, ReauthGate, WriteGate, ModerationLogRepository */));
$c->bind(PackageReviewConsoleService::class,  fn (Container $c) => new PackageReviewConsoleService(/* Database, PackageRepository, PackageReleaseRepository, PackageReviewDecisionRepository, PackageTransparencyLogRepository, ReauthGate, WriteGate, ModerationLogRepository */));
$c->bind(PackageSecurityResponseService::class, fn (Container $c) => new PackageSecurityResponseService(/* Database, SettingRepository, RegistryAdvisoryService, LocalBlocklistService, PackageHealthService, PackageIntegrationService, PackagePublisherRepository, PublisherSigningKeyRepository, PackageAdvisoryRepository, LocalPackageBlockRepository, PackageTransparencyLogRepository, ReauthGate, WriteGate, ModerationLogRepository, $config */));
// MODIFY existing binds:
// - ApiTokenService: append $c->get(PackageCredentialAuthGuard::class) as the last optional arg.
// - PackageLifecycleService, PackageUpdateService, PackageHealthService: append $c->get(PackageIntegrationService::class) as the last optional arg.
```
Hand-built test constructors for `ApiTokenService` may omit the optional guard (human-token seam tests stay green); package-owned auth tests pass a real guard. Hand-built constructors for `PackageLifecycleService`/`PackageUpdateService`/`PackageHealthService` pass `null` for the new `?PackageIntegrationService` param (keeps existing tests green); Inc 5 tests pass a real instance.


---

## Task Groups (execution order)

1. **SP0 — api_tokens read-only authorization matrix + admin/token a11y** — Owns tests/Integration/Api/ApiAuthorizationMatrixTest.php + tests/browser/api-tokens.spec.ts (no-JS mint/one-time-reveal/revoke + axe). Builds the release-evidence retrofit for the already-landed api_tokens seam: a direct-request authorization matrix over /api/v1/me, /api/v1/boards, /api/v1/boards/{id}/threads proving read:boards/read:threads scope denial (403 audited via auditScopeDenied), flag-dark 404, private-board absence, 429 rate-limit, and Bearer-scheme enforcement; plus admin token surface a11y. No production code changes. Exit deliverable: green PHPUnit matrix + captured browser/axe screenshots. Advances SLICE-API-TOKENS R3→R4 and supplies part of the GA-DOD-08 outage/scope evidence.
2. **SP0 — webhooks SSRF/egress adversarial proof + delivery idempotency + browser/a11y** — Owns tests/Unit/Security/EgressGuardAdversarialTest.php, tests/Integration/Worker/WebhookIdempotencyTest.php, tests/browser/webhooks.spec.ts. Builds the formal SSRF/egress adversarial corpus (loopback/private/link-local/metadata/v4-mapped/mixed-DNS-rebind/credential-in-URL denial at both registration validateStatic and delivery validate), a delivery-idempotency report (enqueue dedup on the (webhook_id,event_type,event_id) triple, at-least-once + circuit-breaker + dead-letter), and no-JS register/reveal/rotate/send-test + axe evidence. No production code changes (evidence only). Exit deliverable: green adversarial + idempotency suites + browser/axe artifacts. Advances SLICE-WEBHOOKS R3→R4.
3. **SP0 — service_secrets redaction/revoke/prune coverage + first_party_hooks private-content-absence proof** — Owns tests/Integration/Service/SecretVaultRedactionTest.php and tests/Integration/Service/FirstPartyHookPrivateContentTest.php. Builds the leak-proof coverage: plaintext never appears in metadata/audit rows/exception messages, revoke makes versions immediately prunable, prune destroys+zeroes retired ciphertext, forced vault-failure yields only svcsec_ ref/context (TM-SE-02/04/05); and proves emitted domain events for private/hidden-board threads + DM reports carry no bodies/titles/emails/reasons and suppress entirely (redaction + board_visibility gate). No production code changes. Exit deliverable: green PHPUnit + the four TM-SE-*/private-content fixtures flipped stub→implemented. Advances SLICE-SERVICE-SECRETS + SLICE-FIRST-PARTY-HOOKS R3→R4 and lands TM-SE-02/04/05.
4. **Migration 0073 + settings/credential/publisher-key repositories** — Owns database/migrations/0073_phase5_package_integrations.php (installed_package_settings + installed_package_credentials tables, package_history.event +3 events, moderation_log.target_type +publisher), the three new repos (InstalledPackageSettingsRepository, InstalledPackageCredentialRepository, PublisherSigningKeyRepository), the added methods on InstalledPackageRepository (setSettingsSummary) + PackagePublisherRepository (find/all/setStatus/markVerified/packagesFor), tests/Integration/Core/AppPackageIntegrationSchemaTest.php, and SCHEMA.md v1.33 bump. Additive-only, verified through verify:upgrade. Exit deliverable: migrate green, schema-shape test green (tables/indexes/FKs/enum widens), repo CRUD integration tests green. Foundation for GA-DOD-08.
5. **PackageSettingsService — settings_schema validation + secret storage** — Owns src/Service/Packages/PackageSettingsService.php, the ManifestValidator 'secret' field-key extension (+ its ManifestValidatorTest unknown-field/secret-only-on-string cases), tests/Unit/Service/Packages/PackageSettingsSchemaTest.php, and container bind. Validates submitted values against the active release manifest settings_schema (string/boolean/integer/select + secret:true), writes non-secret value_json + secret fields through SecretVault (svcsec_ ref only), one transaction (settings rows + setSettingsSummary + package_history settings_update + moderation_log), fails closed with ValidationException (safe old input) on unknown key/missing required/bad select/oversize/type mismatch/vault-disabled. Exit deliverable: unit + integration green proving no plaintext persists and vault-disabled fails closed. Advances GA-DOD-08 (secrets).
6. **Install-scoped API-token provisioning + package-token auth guard** — Owns the API-token half of src/Service/Packages/PackageIntegrationService.php (provisionCredentials/rotateCredential/revokeCredential for kind=api_token), `src/Service/Packages/PackageCredentialAuthGuard.php`, the `ApiTokenService` optional guard hook, and the relevant PackageIntegrationServiceTest / PackageCredentialAuthGuardTest cases. It consumes the Task 18 `InstalledPackageCredentialRepository` full surface; it may repair missing api-token methods but must not replace the repository or remove webhook methods. Mints ≤1 package-owned token via ApiTokenService::mint using declared+granted api_scope rows (⊆ApiScopes), name carrying package UID + installed id, hash-only, shown once, created_by=admin (provenance) but zero role inheritance (human-token separation); guards type∈remote_app/automation + state=enabled + ungranted=0 + service_secrets on + reauth; records installed_package_credentials + package_history credential_mint + transparency + audit. The auth guard leaves human/admin tokens unchanged and denies linked package tokens when the link is revoked, execution is disabled, the install is not enabled, the release is unreviewed/revoked, or a local/advisory block applies. Exit deliverable: integration test proving one-time reveal, hash-only storage, human-token separation, ungranted/disabled/flag-dark denial, and emergency-disable package-token auth denial. Advances GA-DOD-08 (scope), maps TM-SC-08.
7. **Package-owned webhook provisioning + event/outbound-host gating** — Owns the webhook half of PackageIntegrationService (provision/rotate/revoke for kind=webhook, suspendDelivery/resumeDelivery) + PackageIntegrationServiceTest cases. It consumes the Task 18 `InstalledPackageCredentialRepository` webhook linkage; it may repair missing webhook methods but must not replace the repository or remove api-token methods. Provisions a package-owned endpoint via WebhookService::register using settings-provided URL + granted event rows (⊆WebhookEvents::domainEvents(), ping rejected), enforces URL host∈granted outbound_hosts (or config test origin), reuses SecretVault/HMAC/delivery ledger; never broadens private/DM payloads. Exit deliverable: integration test proving domainEvents-only subscription, outbound-host denial, atomic rollback, delivery suppression when not enabled. Advances GA-DOD-08 (scope/outage), maps TM-SC-08.
8. **Disable/uninstall/export credential cleanup + lifecycle/update hook wiring** — Owns PackageIntegrationService::onInstallIneligible + PackageIntegrationService::onGrantsChanged + the modifications appending ?PackageIntegrationService to PackageLifecycleService (disable/uninstall), PackageUpdateService (update/rollback grant replacement), and PackageHealthService (quarantine/securityDisable) with hook calls inside the same lifecycle/update/health transaction immediately before state flips inactive or immediately after permission rows change. It also owns the modified container binds, exportSettings behavior, and the cross-service integration tests (disable/uninstall/quarantine/force-disable revoke tokens + pause webhooks; update/rollback permission reductions revoke stale tokens/webhooks; export includes settings/credential attribution without plaintext). Ensures package-owned deliveries are suppressed before egress via endpoint pause and package tokens cannot retain removed scopes. Exit deliverable: worker + service tests proving credentials revoked/paused on every ineligible transition, stale credentials reconciled after grant changes, existing hand-built lifecycle/update/health tests still green (null seam). Advances GA-DOD-08 (disable/uninstall/export).
9. **Operator Integration UX — no-JS forms + browser/a11y** — Owns src/Controller/AdminPackageIntegrationController.php, templates/admin/_package_integration.php + package_detail.php Integration section, the new integration routes, tests/Integration/Core/AppPackageIntegrationTest.php (settings/credential forms, direct-POST denial, noindex, CSRF, reauth, flag-dark 404 including AppFeatureFlagTest additions), and tests/browser/package-integrations.spec.ts (no-JS settings save + one-time credential reveal + axe). Renders settings form from schema, permission/grant summary (data classes/scopes/events/hosts/jobs), credential panel by label/status (never plaintext post-reveal), provision/rotate/revoke/disable/export buttons, and copy that packages run remotely/declaratively only. Exit deliverable: HTTP + browser/axe evidence green. Advances GA-DOD-08 to R3/R4.
10. **PublisherTrustService + publisher console** — Owns src/Service/Registry/PublisherTrustService.php, PublisherSigningKeyRepository usage, templates/admin/package_publisher.php, the publisher routes/actions on AdminPackageSecurityController, tests/Integration/Service/PublisherTrustServiceTest.php + publisher HTTP cases. Implements publisher verify/suspend/reinstate + signing-key pin/rotate(rb-key-rotation.v1 via TrustChainVerifier::verifyRotation)/revoke, reauth-gated, suspension cascading force-disable of the publisher's installs via PackageHealthService::enforcePolicy() + per-package transparency('force_disable'), audit target_type=publisher. Exit deliverable: service + HTTP tests proving key-status transitions, forged-rotation 422, suspension cascade, reauth preservation. Advances GA-DOD-09 (publisher/key lifecycle), maps Publisher-compromise scenario.
11. **PackageReviewConsoleService — exact-digest review decisions** — Owns src/Service/Packages/PackageReviewConsoleService.php, the recordReview route/action, and tests/Integration/Service coverage. Displays signed review decisions cached by PackageAcquisitionService and records LOCAL manual decisions tied to package/release/version/digest/reviewer/evidence/timestamp; tightening-only (local approve refused with review_conflict when a signed reject/revoke exists), updates package_releases.review_status, writes transparency + audit. Exit deliverable: test proving decision provenance, exact-digest binding, tightening-only guard, transparency entry. Advances GA-DOD-09 (exact-digest maintainer review + manual approval).
12. **PackageSecurityResponseService + emergency disable + transparency** — Owns src/Service/Packages/PackageSecurityResponseService.php, config packages.execution_disabled break-glass, tests/Integration/Service/PackageSecurityResponseServiceTest.php. Implements the flag-independent emergency execution brake (package_execution_disabled DB setting OR config; pauses all package-owned endpoints so worker/dispatch skip them + denies credential auth; still permits view/revoke/export/uninstall), the console read model (publishers/advisories/blocklist/transparency/affected installs), and reuses (does not duplicate) RegistryAdvisoryService/LocalBlocklistService/PackageHealthService::enforcePolicy() — no worker:advisories. Exit deliverable: test proving emergency-disable suppresses runtime while preserving management, advisory/blocklist delegation, transparency append. Advances GA-DOD-09 (advisory/emergency disable).
13. **Security-response console UX — no-JS + browser/a11y** — Owns src/Controller/AdminPackageSecurityController.php (index/publisher/emergencyDisable/recordReview + the publisher/key actions wired to services), templates/admin/package_security.php, the security routes + their AppFeatureFlagTest flag-dark cases, and tests/browser/package-security.spec.ts (no-JS console journey + axe). Registers the non-numeric security GETs before /admin/packages/{id}; noindex + CSRF + reauth on every action; consolidates links to existing /admin/registries advisory/blocklist controls without duplicating logic. Exit deliverable: HTTP + browser/axe evidence green, direct-POST/flag-dark denials asserted. Advances GA-DOD-09 to R3.
14. **Evidence, threat fixtures, runbooks, ledger & SCHEMA closeout** — Owns the docs/evidence updates, docs/runbooks/package_integrations.md + package_registry.md security-response additions, docs/phase5/registry-protocol.md remote-app contract, PHASE_5_STATUS.md, docs/phase5/threat-models/fixtures.json (flip TM-SC-08/TM-SE-02/TM-SE-04/TM-SE-05 stub→implemented with test paths, verified by ThreatModelIndexTest), docs/phase5/requirement-ledger.json state bumps (GA-DOD-08 R1→R3/R4, GA-DOD-09 R2→R3, the four SLICE-* R3→R4) with evidence arrays, SCHEMA.md §9 finalization, and tests/browser/package.json evidence-script wiring. Exit deliverable: Phase5EvidenceMapTest + ThreatModelIndexTest + MigrationLedgerTest green, full composer test green, browser/axe artifacts captured, all ledger/fixture bumps landed only in their evidence-carrying commits.


---

## Tasks


<!-- ===== group: sp0-api-tokens-retrofit — SP0 — api_tokens read-only authorization matrix + admin/token a11y ===== -->


---

### Task 1: PHPUnit read-only authorization matrix for the landed `api_tokens` seam

Release-evidence retrofit over already-shipped code. **No production code changes** — this task authors a direct-request authorization matrix that characterizes the live `/api/v1` behavior and raises `SLICE-API-TOKENS` R3→R4. Because the code is landed, the TDD RED is a deliberately-wrong expected value that proves the test genuinely reaches the live endpoint (guards against a vacuous green); the GREEN is the correction to the true contract value.

**Files:**
- Test (Create): `tests/Integration/Api/ApiAuthorizationMatrixTest.php`

**Interfaces:**

Consumes (landed signatures — copy verbatim, do not alter):
```php
// App\Service\ApiTokenService (final)
public function mint(User $admin, string $currentPassword, string $name, array $scopes, ?int $expiresInDays): array; // {token:string,id:int}
public function authenticate(string $bearer): ?ApiPrincipal;   // regex ^Bearer\s+(\S.*)$ (i); flag-dark ⇒ null
public function auditScopeDenied(ApiPrincipal $p, string $scope): void; // action='api_token_scope_denied'
// App\Controller\Api\ApiController::respond() order: flag(api_tokens else JSON 404) → authenticate(401) → rate-limit('api',[120,60] else 429) → action(ApiForbiddenException ⇒ auditScopeDenied + JSON 403)
// App\Security\ApiScopes::SCOPES = ['read:boards' => ..., 'read:threads' => ...]
// Tests\Support\TestCase helpers: requestWithServer(), makeAdmin(['password'=>...]), userEntity(), makeCategory(), makeBoard($cat,['visibility'=>...]), $this->db->fetchValue(), $this->config
```

Produces: `Tests\Integration\Api\ApiAuthorizationMatrixTest` — a green PHPUnit matrix covering scope least-privilege, `auditScopeDenied`, Bearer-scheme enforcement, private-board absence, flag-dark 404, and 429 rate-limiting.

- [ ] **Step 1: Create the test file with helpers + the scope×endpoint matrix, with one deliberately-wrong expectation.** Write `tests/Integration/Api/ApiAuthorizationMatrixTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Api;

  use App\Core\FeatureFlags;
  use App\Core\Response;
  use App\Repository\ApiTokenRepository;
  use App\Repository\ModerationLogRepository;
  use App\Repository\SettingRepository;
  use App\Security\PasswordHasher;
  use App\Security\ReauthGate;
  use App\Security\WriteGate;
  use App\Service\ApiTokenService;
  use PHPUnit\Framework\Attributes\DataProvider;
  use Tests\Support\TestCase;

  /**
   * SP0 release-evidence retrofit for the landed api_tokens seam (SLICE-API-TOKENS).
   * A direct-request authorization matrix over the three read-only /api/v1 endpoints:
   * per-scope denial (403, audited via auditScopeDenied), Bearer-scheme enforcement,
   * private-board absence, flag-dark 404, and 429 rate-limiting. No production code is
   * touched — this file is the characterization evidence that raises R3→R4.
   */
  final class ApiAuthorizationMatrixTest extends TestCase
  {
      private function setApiTokens(bool $on): void
      {
          (new SettingRepository($this->db))->set('features', ['api_tokens' => $on]);
      }

      /** @param array<int,string> $scopes */
      private function mintToken(array $scopes): string
      {
          $this->setApiTokens(true); // mint requires the flag ON
          $svc = new ApiTokenService(
              $this->db,
              new ApiTokenRepository($this->db),
              new ModerationLogRepository($this->db),
              new FeatureFlags(new SettingRepository($this->db)),
              $this->config,
              new ReauthGate(new PasswordHasher()),
              new WriteGate(),
          );
          $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
          return $svc->mint($admin, 'password123', 'matrix', $scopes, null)['token'];
      }

      /** @param array<string,mixed> $query */
      private function apiGet(string $path, ?string $authorization, array $query = []): Response
      {
          $server = $authorization === null ? [] : ['HTTP_AUTHORIZATION' => $authorization];
          return $this->requestWithServer('GET', $path, [], $query, $server);
      }

      private function publicBoardId(): int
      {
          return (int) $this->makeBoard($this->makeCategory(), ['visibility' => 'public'])['id'];
      }

      /** @return array<string,array{0:list<string>,1:string,2:int}> */
      public static function scopeMatrix(): array
      {
          return [
              'boards, read:boards granted → 200'   => [['read:boards'],  '/api/v1/boards',            403], // DELIBERATE RED
              'boards, only read:threads → 403'     => [['read:threads'], '/api/v1/boards',            403],
              'threads, read:threads granted → 200' => [['read:threads'], '/api/v1/boards/%d/threads', 200],
              'threads, only read:boards → 403'     => [['read:boards'],  '/api/v1/boards/%d/threads', 403],
              'me is scope-agnostic → 200'          => [['read:threads'], '/api/v1/me',                200],
          ];
      }

      /** @param list<string> $grantedScopes */
      #[DataProvider('scopeMatrix')]
      public function test_scope_matrix_enforces_least_privilege(array $grantedScopes, string $endpoint, int $expected): void
      {
          $path = str_contains($endpoint, '%d') ? sprintf($endpoint, $this->publicBoardId()) : $endpoint;
          $token = $this->mintToken($grantedScopes);
          self::assertSame($expected, $this->apiGet($path, 'Bearer ' . $token)->status());
      }
  }
  ```

- [ ] **Step 2: Run the matrix and confirm the deliberate RED.** `vendor/bin/phpunit --filter test_scope_matrix_enforces_least_privilege`. Expected: **FAIL** on exactly the `boards, read:boards granted → 200` case with `Failed asserting that 200 is identical to 403.` — proving the granted `/api/v1/boards` endpoint really returned 200 (the test reaches live code, not a stub); the other four cases pass.

- [ ] **Step 3: Correct the granted-boards expectation to the true value.** Edit the first provider row from `403] // DELIBERATE RED` to `200],` (drop the comment). Re-run `vendor/bin/phpunit --filter test_scope_matrix_enforces_least_privilege`. Expected: **`OK (5 tests, 5 assertions)`** (GREEN).

- [ ] **Step 4: Add the `auditScopeDenied` proof method.** Append inside the class:
  ```php
  public function test_scope_denial_is_audited_via_audit_scope_denied(): void
  {
      $before = (int) $this->db->fetchValue(
          "SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'",
      );
      $r = $this->apiGet('/api/v1/boards', 'Bearer ' . $this->mintToken(['read:threads'])); // lacks read:boards
      self::assertSame(403, $r->status());
      self::assertSame('read:boards', json_decode($r->body(), true)['scope']);
      self::assertSame(
          $before + 1,
          (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'"),
          'a scope denial must write exactly one auditScopeDenied row',
      );
  }
  ```
  Run `vendor/bin/phpunit --filter test_scope_denial_is_audited_via_audit_scope_denied`. Expected: **`OK (1 test, 3 assertions)`**.

- [ ] **Step 5: Add the Bearer-scheme enforcement method.** Append:
  ```php
  public function test_bearer_scheme_is_mandatory_and_401s_are_not_audited(): void
  {
      $token = $this->mintToken(['read:boards']);
      self::assertSame(200, $this->apiGet('/api/v1/me', 'Bearer ' . $token)->status()); // correct scheme
      self::assertSame(401, $this->apiGet('/api/v1/me', $token)->status());             // raw token, no scheme
      self::assertSame(401, $this->apiGet('/api/v1/me', 'Basic ' . $token)->status());  // wrong scheme
      self::assertSame(401, $this->apiGet('/api/v1/me', null)->status());               // absent header
      self::assertSame(
          0,
          (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_unauthorized'"),
          'unknown/absent-token 401s must never be audited',
      );
  }
  ```
  Run `vendor/bin/phpunit --filter test_bearer_scheme_is_mandatory_and_401s_are_not_audited`. Expected: **`OK (1 test, 5 assertions)`**.

- [ ] **Step 6: Add the private-board absence method.** Append:
  ```php
  public function test_private_board_is_absent_and_its_threads_are_404(): void
  {
      $cat = $this->makeCategory();
      $this->makeBoard($cat, ['visibility' => 'public', 'name' => 'Public Matrix']);
      $private = $this->makeBoard($cat, ['visibility' => 'private', 'name' => 'Private Matrix']);
      $token = 'Bearer ' . $this->mintToken(['read:boards', 'read:threads']);

      $names = array_column(json_decode($this->apiGet('/api/v1/boards', $token)->body(), true)['boards'], 'name');
      self::assertContains('Public Matrix', $names);
      self::assertNotContains('Private Matrix', $names, 'private boards must never appear in the API listing');

      // Even holding read:threads, a private board is a 404 — never a 403/200 existence leak.
      self::assertSame(404, $this->apiGet('/api/v1/boards/' . (int) $private['id'] . '/threads', $token)->status());
  }
  ```
  Run `vendor/bin/phpunit --filter test_private_board_is_absent_and_its_threads_are_404`. Expected: **`OK (1 test, 3 assertions)`**.

- [ ] **Step 7: Add the flag-dark 404 method.** Append:
  ```php
  public function test_flag_dark_makes_every_endpoint_404_even_with_a_valid_token(): void
  {
      $token = 'Bearer ' . $this->mintToken(['read:boards', 'read:threads']); // minted while flag ON
      $boardId = $this->publicBoardId();
      $this->setApiTokens(false); // kill switch

      foreach (['/api/v1/me', '/api/v1/boards', '/api/v1/boards/' . $boardId . '/threads'] as $path) {
          $r = $this->apiGet($path, $token);
          self::assertSame(404, $r->status(), "$path must 404 while api_tokens is dark");
          self::assertSame('not_found', json_decode($r->body(), true)['error']);
      }
  }
  ```
  Run `vendor/bin/phpunit --filter test_flag_dark_makes_every_endpoint_404_even_with_a_valid_token`. Expected: **`OK (1 test, 6 assertions)`**.

- [ ] **Step 8: Add the 429 rate-limit method.** Append (mirrors the landed `api` policy `[120,60]`; subject key is the token hash, so a fresh token gives a fresh bucket per test):
  ```php
  public function test_rate_limit_returns_429_after_the_api_policy_max(): void
  {
      $bearer = 'Bearer ' . $this->mintToken(['read:boards']);
      self::assertSame(200, $this->apiGet('/api/v1/me', $bearer)->status()); // call #1
      for ($i = 0; $i < 119; $i++) {                                          // calls #2..#120
          $this->apiGet('/api/v1/me', $bearer);
      }
      self::assertSame(429, $this->apiGet('/api/v1/me', $bearer)->status(), 'the 121st call exceeds api [120,60]');
  }
  ```
  Run `vendor/bin/phpunit --filter test_rate_limit_returns_429_after_the_api_policy_max`. Expected: **`OK (1 test, 2 assertions)`**.

- [ ] **Step 9: Run the whole file + the sibling api suite green, then commit.** `vendor/bin/phpunit tests/Integration/Api/ApiAuthorizationMatrixTest.php tests/Integration/Api/ApiReadEndpointsTest.php`. Expected: all green (matrix ≈ 9 test methods incl. the 5 provider cases, no warnings/risky). Then:
  ```bash
  git add tests/Integration/Api/ApiAuthorizationMatrixTest.php
  git commit -m "$(cat <<'EOF'
  test(api): add read-only /api/v1 authorization matrix (SLICE-API-TOKENS)

  Release-evidence retrofit over the landed api_tokens seam: a direct-request
  matrix proving read:boards/read:threads least-privilege (403 audited via
  auditScopeDenied), Bearer-scheme enforcement, private-board absence,
  flag-dark 404, and 429 rate-limiting. No production code changed.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 2: No-JS + axe browser evidence for the admin API-token surface

Captures the operator-facing one-time-reveal journey (mint → show-once secret → revoke) as a real Chromium run at desktop + mobile, and certifies `/admin/api-tokens` clears serious/critical axe violations. **No production code changes** — the surface already ships; if axe surfaces a serious violation this is an out-of-scope escalation (a template fix would be production code), not an in-task patch.

**Files:**
- Test (Create): `tests/browser/api-tokens.spec.ts`
- Modify: `tests/browser/package.json` (append the spec to the `evidence` script)

**Interfaces:**

Consumes (landed surface): seed (`tests/browser/seed.php`) enables `api_tokens` and provisions `admin@retro.test` / `password123`; `templates/admin/api_tokens.php` renders the no-JS form (`input[name="name"]`, `input[name="scopes[]"][value="read:boards"]`, `input[name="current_password"]`, button `Create token`), the show-once flash (`will not be shown again`, `code` starting `rbt_`), and the per-row `Revoke` button; `@axe-core/playwright` `AxeBuilder`.

Produces: `docs/evidence/browser/{desktop,mobile}/api-token-*.png` screenshots + a green axe assertion; the `evidence` npm script runs the new spec.

- [ ] **Step 1: Create the self-contained spec with login/visit/shot/axe helpers + the mint→reveal→revoke journey.** Write `tests/browser/api-tokens.spec.ts`:
  ```ts
  import AxeBuilder from '@axe-core/playwright';
  import { expect, test, type Page, type TestInfo } from '@playwright/test';
  import path from 'node:path';

  /**
   * SP0 browser evidence for the landed admin API-token surface (SLICE-API-TOKENS).
   * Drives the no-JS mint form, proves the plaintext is shown exactly once, revokes
   * the token via the row form, and certifies /admin/api-tokens is free of
   * serious/critical axe violations. Seed enables api_tokens + admin@retro.test.
   */
  const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

  async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
    await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
  }

  async function visit(page: Page, url: string): Promise<void> {
    const resp = await page.goto(url);
    expect(resp, `no response for ${url}`).not.toBeNull();
    expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
  }

  async function login(page: Page, email: string): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.endsWith('/login'));
    const skip = page.getByRole('button', { name: 'Skip' });
    if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
      await skip.click();
    }
  }

  async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include?: string): Promise<void> {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    if (include !== undefined) builder = builder.include(include);
    const results = await builder.analyze();
    const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
  }

  test('admin API tokens: no-JS mint shows the secret once, axe-clean, then revoke', async ({ page }, info) => {
    await login(page, 'admin@retro.test');

    // Flag-gated discovery link off the admin dashboard (seed enables api_tokens).
    await visit(page, '/admin');
    await page.getByRole('link', { name: 'API tokens' }).click();
    await page.waitForURL(/\/admin\/api-tokens$/);
    await expect(page.getByRole('heading', { name: 'API tokens' })).toBeVisible();
    await expectNoSeriousA11yViolations(page, info); // empty-form state is accessible

    // Desktop + mobile share one seeded DB — unique name so rows never collide.
    const tokenName = `Evidence CI token (${info.project.name}-${Date.now()})`;
    await page.fill('input[name="name"]', tokenName);
    await page.check('input[name="scopes[]"][value="read:boards"]');
    await page.fill('input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Create token' }).click();

    // One-time plaintext, rendered directly (never via Flash / Set-Cookie).
    await expect(page.getByText(/will not be shown again/)).toBeVisible();
    await expect(page.locator('code').filter({ hasText: /^rbt_/ })).toBeVisible();
    await expectNoSeriousA11yViolations(page, info); // reveal state is accessible
    await shot(page, info, 'api-token-minted');

    // Revoke via the row form (PRG back to the list).
    const row = page.locator('table tbody tr', { hasText: tokenName });
    await expect(row).toContainText('active');
    const revokeBtn = row.getByRole('button', { name: 'Revoke' });
    await revokeBtn.scrollIntoViewIfNeeded();
    // force: on the tall mobile page Playwright's hit-test transiently reports the
    // mint-form card as topmost; the toContainText('revoked') below proves it fired.
    await revokeBtn.click({ force: true });
    await expect(page.locator('table tbody tr', { hasText: tokenName })).toContainText('revoked');
    await shot(page, info, 'api-token-revoked');
  });
  ```

- [ ] **Step 2: Prepare the e2e DB and run the spec (RED gate + capture).** `cd tests/browser && bash prepare.sh && npx playwright test api-tokens.spec.ts`. Expected: **`2 passed` (desktop + mobile)**; if a page 404s or an axe serious/critical violation exists the run fails here (the "can-it-fail" gate — `visit()` asserts status <400 and the axe filter must equal `[]`). On green, confirm the four PNGs exist:
  ```bash
  ls -1 docs/evidence/browser/desktop/api-token-*.png docs/evidence/browser/mobile/api-token-*.png
  ```
  Expected: `api-token-minted.png` + `api-token-revoked.png` under each viewport.

- [ ] **Step 3: Wire the spec into the `evidence` npm script.** In `tests/browser/package.json`, edit the `evidence` script to append the new spec:
  ```
  "evidence": "bash prepare.sh && playwright test gate-a.spec.ts server-drafts.spec.ts appeals.spec.ts api-tokens.spec.ts",
  ```

- [ ] **Step 4: Re-run via the wired script to confirm it is picked up, then commit.** `cd tests/browser && npm run evidence -- --grep "admin API tokens"` (fast filtered confirmation the script line parses and selects the spec). Expected: the `admin API tokens` test passes at both viewports. Then:
  ```bash
  git add tests/browser/api-tokens.spec.ts tests/browser/package.json docs/evidence/browser/desktop/api-token-*.png docs/evidence/browser/mobile/api-token-*.png
  git commit -m "$(cat <<'EOF'
  test(browser): admin API-token no-JS mint/reveal/revoke + axe evidence

  Self-contained Playwright spec driving the landed /admin/api-tokens surface at
  desktop + mobile: no-JS mint form, show-once plaintext, row revoke, and a
  serious/critical axe scan of the empty and reveal states. Wired into the
  evidence npm script. No production code changed. (SLICE-API-TOKENS)

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 3: Advance `SLICE-API-TOKENS` R3→R4 in the requirement ledger

Records the two new evidence artifacts and flips the slice to R4. The ledger is machine-checked by `Phase5EvidenceMapTest` (`is_file` on every evidence path; R3/R4/R5 require ≥1 link) — so this runs **after** Tasks 1–2 create the files. `GA-DOD-08` only receives a note here (it stays R1: its outage/scope DoD is not satisfiable until the integration runtime lands in a later group — this SP0 slice "supplies part of" that evidence, so no premature state bump).

**Files:**
- Modify: `docs/phase5/requirement-ledger.json`
- Test (verify): `tests/Unit/Core/Phase5EvidenceMapTest.php` (unchanged; must stay green)

**Interfaces:**

Consumes: `Phase5EvidenceMapTest::validate()` invariants — each `evidence[]` entry must satisfy `is_file(root . '/' . $path)`; `state ∈ {R3,R4,R5}` requires non-empty `evidence`.

Produces: `SLICE-API-TOKENS` at `state: "R4"` with evidence `tests/Integration/Api/ApiAuthorizationMatrixTest.php` + `tests/browser/api-tokens.spec.ts`.

- [ ] **Step 1: Confirm the current ledger row and that the evidence-map test is green pre-edit.** `grep -n 'SLICE-API-TOKENS' docs/phase5/requirement-ledger.json` (expect the `"state": "R3"` row, notes `"Authorization matrix and a11y retrofit outstanding."`) and `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php`. Expected: **`OK`** (baseline green before touching the ledger).

- [ ] **Step 2: Edit the `SLICE-API-TOKENS` row to R4 with both evidence paths.** Replace the existing line with:
  ```json
      { "id": "SLICE-API-TOKENS", "gate": "A", "workstream": "B2-SP2", "title": "Read-only API tokens + /api/v1", "state": "R4", "evidence": ["tests/Integration/Service/ApiTokenServiceTest.php", "tests/Integration/Api/ApiReadEndpointsTest.php", "tests/Integration/Api/ApiAuthorizationMatrixTest.php", "tests/browser/api-tokens.spec.ts"], "notes": "R4: direct-request authorization matrix (scope denial/Bearer/private-board/flag-dark/429) + no-JS mint/reveal/revoke browser+axe evidence. Contributes scope/outage evidence toward GA-DOD-08." },
  ```
  (Both new paths are real files created in Tasks 1–2, so the `is_file` check passes; `ApiReadEndpointsTest.php` is added alongside the service test as corroborating landed evidence.)

- [ ] **Step 3: Run the evidence-map validator to prove the ledger is still valid and every claim is evidenced.** `vendor/bin/phpunit --filter test_ledger_is_valid_and_every_claim_is_evidenced`. Expected: **`OK (1 test, 1 assertion)`** (no `evidence path does not exist` and no `state R4 requires ≥1 evidence link` errors).

- [ ] **Step 4: Run the whole unit ledger test + the api integration suite once more, then commit.** `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Integration/Api/ApiAuthorizationMatrixTest.php`. Expected: all green. Then:
  ```bash
  git add docs/phase5/requirement-ledger.json
  git commit -m "$(cat <<'EOF'
  docs(phase5): advance SLICE-API-TOKENS R3→R4 with matrix + a11y evidence

  Link the new authorization-matrix PHPUnit and no-JS/axe browser evidence;
  flip the slice to R4. GA-DOD-08 stays R1 (outage/scope DoD lands with the
  integration runtime) but now records this SP0 slice as partial scope evidence.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

**Group exit criteria (independently testable + committable):** `vendor/bin/phpunit tests/Integration/Api/ApiAuthorizationMatrixTest.php tests/Unit/Core/Phase5EvidenceMapTest.php` is green; `cd tests/browser && npx playwright test api-tokens.spec.ts` passes at both viewports with the four PNGs captured; `SLICE-API-TOKENS` reads R4 in the ledger. No production `src/` file was modified.


<!-- ===== group: sp0-webhooks-retrofit — SP0 — webhooks SSRF/egress adversarial proof + delivery idempotency + browser/a11y ===== -->


---

### Task 6: SSRF/egress adversarial corpus (pure unit) — `EgressGuardAdversarialTest`

**Files:**
- Test (Create): `tests/Unit/Security/EgressGuardAdversarialTest.php`
- Read-only reference (do NOT modify): `src/Security/EgressGuard.php`, `src/Core/EgressBlockedException.php`

**Interfaces:**

*Consumes (existing, LOCKED — no production change this task):*
```php
// App\Security\EgressGuard  (final)
public function __construct(bool $allowHttp, array $allowedCidrs, ?callable $resolver = null);
public function validate(string $url): string;        // resolves host, classifies every A/AAAA, returns pinned IP or throws
public function validateStatic(string $url): void;    // classifies ONLY when host is a literal IP; hostnames pass (no DNS)
// App\Core\EgressBlockedException extends \RuntimeException
```
*Produces:* a data-provider-driven characterization suite proving the registration-time (`validateStatic`, literal IPs) and delivery-time (`validate`, resolver output incl. DNS-rebind) egress layers deny the full SSRF corpus. This is evidence only — if any case comes back allowed, that is a real production SSRF gap to escalate (do NOT silently patch under this group; scope is evidence-only).

- [ ] **Step 6.1: Confirm the guarantee is currently unproven (RED via absent file).** Run `vendor/bin/phpunit --filter EgressGuardAdversarialTest`. Expected output: `No tests executed!` (the adversarial corpus does not yet exist — the SSRF/egress guarantee is unverified). This is the red state for a retrofit/characterization suite.

- [ ] **Step 6.2: Create the test skeleton with a guard factory mirroring the existing `EgressGuardTest`.** Write the file header + a private `guard()` helper that injects a fixed resolver so no real DNS runs:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use PHPUnit\Framework\TestCase;

/**
 * SLICE-WEBHOOKS SP0 — formal SSRF/egress adversarial proof.
 *
 * Two enforcement layers are exercised against one corpus:
 *   - validateStatic() : registration-time guard for LITERAL-IP URLs (no DNS).
 *   - validate()       : delivery-time guard over every resolved A/AAAA record,
 *                        including mixed-result DNS rebinding.
 * Every case must be DENIED (EgressBlockedException) unless it is a public HTTPS
 * target or fully operator-allowlisted. A green run is the evidence artifact; a
 * red run is a real SSRF gap to escalate, not to patch inside this task.
 */
final class EgressGuardAdversarialTest extends TestCase
{
    /** @param array<int,string> $ips */
    private function guard(array $ips = [], bool $allowHttp = false, array $allow = []): EgressGuard
    {
        return new EgressGuard($allowHttp, $allow, static fn (string $host): array => $ips);
    }
}
```

- [ ] **Step 6.3: Add the literal-IP denial provider (registration `validateStatic` layer).** Insert a `@dataProvider` covering loopback, RFC1918, CGNAT, link-local/metadata, IPv6 loopback/ULA/link-local, IPv4-mapped IPv6, unspecified, multicast, reserved, credentials-in-URL, and non-http scheme:
```php
    /** @return array<string,array{0:string}> */
    public static function deniedLiteralUrls(): array
    {
        return [
            'loopback v4'            => ['https://127.0.0.1/hook'],
            'private 10/8'           => ['https://10.0.0.5/hook'],
            'private 172.16/12'      => ['https://172.16.9.9/hook'],
            'private 192.168/16'     => ['https://192.168.1.9/hook'],
            'cloud metadata'         => ['https://169.254.169.254/latest/meta-data/'],
            'cgnat 100.64/10'        => ['https://100.64.0.1/hook'],
            'unspecified 0.0.0.0'    => ['https://0.0.0.0/hook'],
            'multicast 224/4'        => ['https://224.0.0.1/hook'],
            'reserved 240/4'         => ['https://240.0.0.1/hook'],
            'ipv6 loopback'          => ['https://[::1]/hook'],
            'ipv6 ula fc00::/7'      => ['https://[fc00::1]/hook'],
            'ipv6 link-local fe80'   => ['https://[fe80::1]/hook'],
            'v4-mapped metadata'     => ['https://[::ffff:169.254.169.254]/hook'],
            'v4-mapped private'      => ['https://[::ffff:10.0.0.1]/hook'],
            'creds in url + public'  => ['https://user:pass@8.8.8.8/hook'],
            'creds in url + host'    => ['https://user:pass@example.test/hook'],
            'non-http scheme'        => ['ftp://93.184.216.34/hook'],
        ];
    }

    /** @dataProvider deniedLiteralUrls */
    public function test_validate_static_denies_literal_ssrf_targets(string $url): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard()->validateStatic($url);
    }
```

- [ ] **Step 6.4: Prove `validateStatic` allows only the safe registration inputs.** Add a passing-case test so the layer is not vacuously strict (public literal is allowed; a hostname passes because static does no DNS and is caught later at delivery):
```php
    public function test_validate_static_allows_public_literal_and_defers_hostnames(): void
    {
        // No exception for a public literal, and a bare hostname is deferred to
        // delivery-time validate() (validateStatic must not perform DNS here).
        $this->guard()->validateStatic('https://93.184.216.34/hook');
        $this->guard()->validateStatic('https://example.test/hook');
        self::assertTrue(true);
    }
```

- [ ] **Step 6.5: Add the delivery-time (`validate`) resolver corpus including DNS rebinding.** Cover metadata-via-DNS, mixed public+private rebind, private-via-DNS, v4-mapped-via-DNS, unresolvable, public-http-denied, and odd-port-denied:
```php
    /** @return array<string,array{0:string,1:array<int,string>,2:bool}> */
    public static function deniedResolvedUrls(): array
    {
        return [
            'metadata via dns'      => ['https://metadata.evil.test/latest', ['169.254.169.254'], false],
            'dns rebind mixed'      => ['https://rebind.test/hook',          ['1.2.3.4', '127.0.0.1'], false],
            'private via dns'       => ['https://intranet.test/hook',        ['10.0.0.5'], false],
            'v4-mapped via dns'     => ['https://evil.test/hook',            ['::ffff:169.254.169.254'], false],
            'unresolvable host'     => ['https://nope.test/hook',            [], false],
            'public http denied'    => ['http://public.test/hook',          ['8.8.8.8'], false],
            'public odd port'       => ['https://public.test:8443/hook',     ['8.8.8.8'], false],
        ];
    }

    /**
     * @dataProvider deniedResolvedUrls
     * @param array<int,string> $ips
     */
    public function test_validate_denies_resolved_ssrf_targets(string $url, array $ips, bool $allowHttp): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard($ips, $allowHttp)->validate($url);
    }
```

- [ ] **Step 6.6: Prove the allowlist relaxes for a fully-allowlisted target but cannot be used to smuggle a private hop.** This is the rebind-through-allowlist adversarial boundary:
```php
    public function test_allowlist_relaxes_only_when_every_resolved_ip_is_allowlisted(): void
    {
        // Fully-allowlisted loopback over http:8011 is permitted and pins the IP.
        $pinned = $this->guard(['127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://localhost:8011/hook');
        self::assertSame('127.0.0.1', $pinned);

        // A rebind that mixes an un-allowlisted public IP with a denied private IP
        // is NOT "all allowlisted", so it falls to the deny path and is blocked.
        $this->expectException(EgressBlockedException::class);
        $this->guard(['8.8.8.8', '127.0.0.1'], false, ['127.0.0.1/32'])->validate('https://rebind.test/hook');
    }
```

- [ ] **Step 6.7: Run the adversarial suite to GREEN.** Run `vendor/bin/phpunit --filter EgressGuardAdversarialTest`. Expected: `OK (5 tests, ...)` with the two providers expanding to 24 assertions across the corpus, no warnings/risky. If any case is unexpectedly allowed, STOP — that is a real SSRF finding to escalate per this group's evidence-only scope, not a code change to make here.

- [ ] **Step 6.8: Run the whole unit suite to confirm no strict-mode regressions.** Run `vendor/bin/phpunit --testsuite unit`. Expected: green (the pre-existing `EgressGuardTest` still passes alongside the new adversarial file).

- [ ] **Step 6.9: Commit.**
```bash
git add tests/Unit/Security/EgressGuardAdversarialTest.php
git commit -m "test(webhooks): formal SSRF/egress adversarial corpus for both guard layers

Data-provider proof that validateStatic (registration, literal IPs) and
validate (delivery, resolver + DNS-rebind) deny the full SSRF corpus:
loopback/private/CGNAT/link-local/metadata/v4-mapped/multicast/reserved,
credentials-in-URL, non-http scheme, and allowlist-smuggling. Evidence
only, no production change. Advances SLICE-WEBHOOKS.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Delivery-idempotency report (integration) — `WebhookIdempotencyTest` + evidence doc

**Files:**
- Test (Create): `tests/Integration/Worker/WebhookIdempotencyTest.php`
- Evidence (Create): `docs/evidence/phase5/webhook-idempotency.md`
- Read-only reference: `src/Worker/WebhookDeliveryWorker.php`, `src/Repository/WebhookDeliveryRepository.php`, `src/Service/WebhookService.php`, `database/migrations/0057_phase5_webhooks.php` (unique key `uq_delivery_idem (webhook_id, event_type, event_id)`)

**Interfaces:**

*Consumes (existing, LOCKED):*
```php
// App\Repository\WebhookDeliveryRepository (final)
public function enqueue(int $webhookId, string $eventType, string $eventId, string $payloadJson, int $maxAttempts): int; // INSERT IGNORE on uq_delivery_idem; returns 0 on the duplicate triple
public function find(int $id): ?array;
public function requeue(int $webhookId, int $deliveryId): int;   // 'dead' -> 'queued', attempt_count=0
public function statusCounts(): array;                            // status => count
// App\Worker\WebhookDeliveryWorker::run(int $limit = 100): array{delivered:int,retrying:int,dead:int,skipped:int}
// App\Service\Webhook\FakeWebhookTransport (records ->calls[])
// Kernel path: POST /admin/webhooks -> WebhookService::register -> assertValidUrl -> EgressGuard::validateStatic
```
*Produces:* an idempotency characterization suite (enqueue dedup on the `(webhook_id,event_type,event_id)` triple; delivered rows are effectively-once under at-least-once redelivery; dead-letter terminality + replay; registration SSRF denial wired end-to-end; flag-dark 404) plus a human-readable delivery-idempotency report. This task deliberately does NOT re-test circuit-breaker/backoff/dead-letter mechanics already covered by `WebhookDeliveryWorkerTest` — it cites them in the report (DRY).

- [ ] **Step 7.1: Confirm the idempotency report is unproven (RED via absent file).** Run `vendor/bin/phpunit tests/Integration/Worker/WebhookIdempotencyTest.php`. Expected: `Cannot open file ".../WebhookIdempotencyTest.php"` / `No tests executed!`. Red state for the retrofit.

- [ ] **Step 7.2: Create the test with worker/vault helpers copied verbatim from the proven `WebhookDeliveryWorkerTest`.** These are the exact, real collaborators the worker needs:
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use App\Service\Webhook\FakeWebhookTransport;
use App\Service\Webhook\WebhookResponse;
use App\Service\Webhook\WebhookTransport;
use App\Worker\WebhookDeliveryWorker;
use Tests\Support\TestCase;

/**
 * SLICE-WEBHOOKS SP0 — delivery-idempotency proof.
 *
 * Companion to WebhookDeliveryWorkerTest (which owns backoff / circuit-breaker /
 * dead-letter mechanics). This file proves the at-least-once ledger's idempotency
 * contract: dedup on the (webhook_id,event_type,event_id) triple, effectively-once
 * delivery on success, dead-letter terminality + replay, and that a queued
 * delivery can never be minted for an SSRF URL (guard wired at registration) or
 * while the webhooks flag is dark.
 */
final class WebhookIdempotencyTest extends TestCase
{
    private WebhookRepository $hooks;
    private WebhookDeliveryRepository $deliv;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
        $this->hooks = new WebhookRepository($this->db);
        $this->deliv = new WebhookDeliveryRepository($this->db);
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    private function worker(WebhookTransport $transport): WebhookDeliveryWorker
    {
        return new WebhookDeliveryWorker(
            $this->hooks,
            $this->deliv,
            $this->vault(),
            $transport,
            new FeatureFlags(new SettingRepository($this->db)),
            new ModerationLogRepository($this->db),
            $this->config,
        );
    }

    /** @return array{webhook_id:int,delivery_id:int} */
    private function hookWithDelivery(string $eventId = 'e1', string $event = 'ping'): array
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $id = $this->hooks->insert('idem', 'https://x.test/h', json_encode([$event]) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($id, $event, $eventId, '{"event":"' . $event . '"}', 6);
        return ['webhook_id' => $id, 'delivery_id' => $did];
    }
}
```

- [ ] **Step 7.3: Prove enqueue dedup on the `(webhook_id,event_type,event_id)` triple.** The `INSERT IGNORE` against `uq_delivery_idem` is the core idempotency boundary:
```php
    public function test_enqueue_dedups_on_the_webhook_event_id_triple(): void
    {
        $ids = $this->hookWithDelivery('evt-dedup');
        // Re-enqueueing the identical triple is a no-op: 0 rows, no duplicate row.
        self::assertSame(0, $this->deliv->enqueue($ids['webhook_id'], 'ping', 'evt-dedup', '{"event":"ping"}', 6));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ? AND event_type = 'ping' AND event_id = 'evt-dedup'",
            [$ids['webhook_id']],
        ));
        // A different event_id for the same webhook/event is a distinct delivery.
        self::assertGreaterThan(0, $this->deliv->enqueue($ids['webhook_id'], 'ping', 'evt-other', '{"event":"ping"}', 6));
    }
```

- [ ] **Step 7.4: Prove effectively-once delivery under at-least-once (a delivered row is never re-claimed).** Two worker runs, transport hit exactly once:
```php
    public function test_delivered_row_is_not_reclaimed_on_a_second_worker_run(): void
    {
        $ids = $this->hookWithDelivery('evt-once');
        $ok = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));

        self::assertSame(1, $this->worker($ok)->run()['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);

        // Second drain: the row is 'delivered', claim() only selects 'queued', so
        // nothing is re-sent — at-least-once collapses to effectively-once.
        $stats = $this->worker($ok)->run();
        self::assertSame(0, $stats['delivered']);
        self::assertCount(1, $ok->calls);
    }
```

- [ ] **Step 7.5: Prove dead-letter terminality and replay.** A `dead` row is terminal until an explicit `requeue`, after which it delivers exactly once:
```php
    public function test_dead_letter_is_terminal_until_replay_then_delivers_once(): void
    {
        $ids = $this->hookWithDelivery('evt-dead');
        // Force the row to its final attempt, then fail it into the dead-letter state.
        $this->db->run('UPDATE webhook_deliveries SET attempt_count = 5, next_attempt_at = NULL WHERE id = ?', [$ids['delivery_id']]);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        self::assertSame(1, $this->worker($fail)->run()['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);

        // A dead row is not re-claimed by a subsequent drain (terminal).
        $ok = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));
        self::assertSame(0, $this->worker($ok)->run()['delivered']);

        // Explicit replay/requeue returns it to 'queued'; it then delivers once.
        self::assertSame(1, $this->deliv->requeue($ids['webhook_id'], $ids['delivery_id']));
        self::assertSame(1, $this->worker($ok)->run()['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);
    }
```

- [ ] **Step 7.6: Prove the SSRF guard is wired into registration end-to-end (observable HTTP 422).** This ties the Task 6 corpus to the real registration path — a literal private IP can never mint a queued delivery:
```php
    public function test_registration_rejects_ssrf_url_via_static_egress_guard(): void
    {
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $res = $this->post('/admin/webhooks', [
            'name' => 'ssrf attempt',
            'url' => 'https://169.254.169.254/latest/meta-data/',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('not an allowed destination', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }
```

- [ ] **Step 7.7: Prove the admin webhook surface is 404 while the flag is dark (no delivery can be provisioned dark).** Self-contained flag-dark assertion (does not touch the shared `AppFeatureFlagTest`):
```php
    public function test_admin_webhook_surface_is_404_while_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->assertStatus(404, $this->get('/admin/webhooks'));
        $this->assertStatus(404, $this->post('/admin/webhooks', [
            'name' => 'dark',
            'url' => 'https://example.test/hook',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]));
    }
```

- [ ] **Step 7.8: Run the idempotency suite to GREEN.** Run `vendor/bin/phpunit tests/Integration/Worker/WebhookIdempotencyTest.php`. Expected: `OK (5 tests, ...)`, no warnings/risky.

- [ ] **Step 7.9: Write the delivery-idempotency report.** Create `docs/evidence/phase5/webhook-idempotency.md` summarizing each guarantee and the test that proves it, and citing the existing worker test for the mechanics this file intentionally does not duplicate:
```markdown
# Webhook delivery-idempotency report (SLICE-WEBHOOKS SP0)

The outbound webhook engine is an at-least-once durable ledger
(`webhook_deliveries`) drained under a single MySQL advisory lock. This report
records the idempotency guarantees and the automated evidence for each.

| Guarantee | Mechanism | Proof |
|---|---|---|
| Enqueue dedup on `(webhook_id, event_type, event_id)` | `INSERT IGNORE` on `uq_delivery_idem` (migration `0057`) | `WebhookIdempotencyTest::test_enqueue_dedups_on_the_webhook_event_id_triple` |
| Effectively-once on success | `claim()` selects only `status='queued'`; `markDelivered` is terminal | `WebhookIdempotencyTest::test_delivered_row_is_not_reclaimed_on_a_second_worker_run` |
| Dead-letter terminality + explicit replay | `recordFailure(dead=true)` → `status='dead'`; `requeue()` is the only path back | `WebhookIdempotencyTest::test_dead_letter_is_terminal_until_replay_then_delivers_once` |
| No delivery for an SSRF target | `WebhookService::assertValidUrl` → `EgressGuard::validateStatic` at registration; `EgressGuard::validate` at delivery | `WebhookIdempotencyTest::test_registration_rejects_ssrf_url_via_static_egress_guard`; `EgressGuardAdversarialTest` |
| No provisioning while dark | `package_registry`-independent `webhooks` flag gate → 404 | `WebhookIdempotencyTest::test_admin_webhook_surface_is_404_while_flag_dark` |

## Mechanics covered elsewhere (not duplicated here)

Retry/backoff, the consecutive-failure circuit breaker (auto-pause at
`webhooks.circuit_breaker_threshold`), the snapshot `max_attempts` dead-letter
boundary, breaker skip of remaining same-endpoint rows in one run, and
dual-secret rotation signing are owned by
`tests/Integration/Worker/WebhookDeliveryWorkerTest.php`.

## Signature integrity

Delivery signs the exact byte body with `X-RetroBoards-Signature`
(`sha256=HMAC(timestamp . '.' . body)`); rotation emits two comma-separated
signatures during the overlap window. See `WebhookDeliveryWorkerTest`.
```

- [ ] **Step 7.10: Re-run to confirm green + no strict violations, then commit.** Run `vendor/bin/phpunit tests/Integration/Worker/WebhookIdempotencyTest.php` once more (expect `OK`), then:
```bash
git add tests/Integration/Worker/WebhookIdempotencyTest.php docs/evidence/phase5/webhook-idempotency.md
git commit -m "test(webhooks): delivery-idempotency proof + report

Enqueue dedup on the (webhook_id,event_type,event_id) triple, effectively-once
delivery under at-least-once redelivery, dead-letter terminality + replay,
registration SSRF denial wired end-to-end, and flag-dark 404. Cites the
existing worker test for backoff/circuit-breaker/dead-letter mechanics (DRY).
Evidence only. Advances SLICE-WEBHOOKS.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: No-JS register/reveal/rotate/send-test + axe browser evidence, and advance the ledger

**Files:**
- Test (Create): `tests/browser/webhooks.spec.ts`
- Modify: `tests/browser/package.json` (add an `evidence:webhooks` script)
- Modify: `docs/phase5/requirement-ledger.json` (advance `SLICE-WEBHOOKS` R3→R4 with evidence paths)
- Modify: `docs/runbooks/package_registry.md` (add a webhook SSRF/idempotency evidence pointer)
- Produces (at runtime): `docs/evidence/browser/{desktop,mobile}/webhook-*.png`
- Read-only reference: `tests/browser/gate-a.spec.ts` (webhook flow + `runWebhookWorker`), `tests/browser/a11y.spec.ts` (AxeBuilder pattern), `tests/browser/seed.php` (seeds `webhooks`+`service_secrets` ON), `templates/admin/webhooks.php`, `templates/admin/webhook_detail.php`

**Interfaces:**

*Consumes (existing server-rendered forms, no production change):*
```
POST /admin/webhooks                         -> register, renders one-time secret ("will not be shown again")
POST /admin/webhooks/{id}/rotate             -> rotateSecret, renders new one-time secret
POST /admin/webhooks/{id}/test               -> sendTestEvent, redirect+flash "Test event queued"
POST /admin/webhooks/{id}/toggle             -> setActive
GET  /admin/webhooks , /admin/webhooks/{id}  -> list / detail
CLI: php bin/console worker:webhooks         -> drains the queue (delivers the ping to the receiver)
```
*Produces:* no-JS Playwright evidence that register/reveal/rotate/send-test work as pure server-rendered forms and deliver to a live receiver, plus an axe scan (JS-enabled) of the webhook admin surfaces at desktop + mobile; then the terminal ledger/runbook advance to R4.

- [ ] **Step 8.1: RED — confirm the spec is absent.** Run from `tests/browser`: `npx playwright test webhooks.spec.ts --list`. Expected: `Error: No tests found` (spec does not exist). Red state.

- [ ] **Step 8.2: Create the spec header + self-contained helpers (mirroring totp/a11y specs).** Include `login`, `visit`, `shot`, `expectOneTimeSecret`, and a `runWebhookWorker` copied from gate-a so the ping actually delivers:
```ts
import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { execFile } from 'node:child_process';
import http from 'node:http';
import path from 'node:path';
import { promisify } from 'node:util';

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');
const execFileAsync = promisify(execFile);

async function runWebhookWorker(repoRoot: string): Promise<void> {
  await execFileAsync('php', ['bin/console', 'worker:webhooks'], {
    cwd: repoRoot,
    env: {
      ...process.env,
      DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e',
      WEBHOOK_ALLOW_HTTP: 'true',
      WEBHOOK_ALLOWED_PRIVATE_CIDRS: '127.0.0.1/32,::1/128',
      MAIL_DRIVER: 'array',
    },
  });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
}

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function expectOneTimeSecret(page: Page, context: string): Promise<void> {
  const secret = page.getByText(/will not be shown again/);
  if (await secret.isVisible({ timeout: 10_000 }).catch(() => false)) {
    return;
  }
  const errors = (await page.locator('.field-error').allTextContents()).map((t) => t.trim()).filter(Boolean).join(' | ');
  throw new Error(`${context} did not show the one-time secret. URL=${page.url()} errors=${errors || 'none'}`);
}
```

- [ ] **Step 8.3: Add the no-JS register → reveal → rotate → send-test → deliver test.** Scope it to a `javaScriptEnabled: false` describe block, stand up a local receiver like gate-a, and drive only server-rendered form posts:
```ts
test.describe('no-JS webhook admin', () => {
  test.use({ javaScriptEnabled: false });

  test('register/reveal/rotate/send-test work without JavaScript and deliver', async ({ page }, info) => {
    let markReceived: (() => void) | null = null;
    let receivedEvent = '';
    const receivedPromise = new Promise<void>((resolve) => { markReceived = resolve; });
    const server = http.createServer((req, res) => {
      const chunks: Buffer[] = [];
      req.on('data', (c) => chunks.push(Buffer.from(c)));
      req.on('end', () => {
        try { receivedEvent = JSON.parse(Buffer.concat(chunks).toString('utf8')).event; } catch { receivedEvent = ''; }
        markReceived?.();
        res.statusCode = 200; res.end('ok');
      });
    });
    await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', () => resolve()));
    const address = server.address();
    if (typeof address === 'string' || address === null) throw new Error('expected TCP server address');
    const hookUrl = `http://127.0.0.1:${address.port}/hook`;

    try {
      await login(page, 'admin@retro.test');
      await visit(page, '/admin/webhooks');
      await expect(page.getByRole('heading', { name: 'Webhooks' })).toBeVisible();

      const name = `No-JS webhook (${info.project.name}-${Date.now()})`;
      await page.fill('input[name="name"]', name);
      await page.fill('input[name="url"]', hookUrl);
      await page.check('input[name="events[]"][value="ping"]');
      await page.fill('input[name="current_password"]', 'password123');
      await page.getByRole('button', { name: 'Register endpoint' }).click();
      await expectOneTimeSecret(page, 'Webhook registration');
      await shot(page, info, 'webhook-01-registered');

      await visit(page, '/admin/webhooks');
      await page.locator('table tbody tr', { hasText: name }).getByRole('link', { name: 'Manage' }).click();
      await page.waitForURL(/\/admin\/webhooks\/\d+$/);
      const detailUrl = page.url();

      // Rotate the signing secret — the new secret is revealed exactly once.
      await page.locator('form[action$="/rotate"] input[name="current_password"]').fill('password123');
      await page.locator('form[action$="/rotate"] button[type="submit"]').click();
      await expectOneTimeSecret(page, 'Webhook rotation');
      await shot(page, info, 'webhook-02-rotated');

      // Send a test event (no-JS redirect+flash), then drain the queue.
      await page.goto(detailUrl);
      await page.locator('form[action$="/test"] button[type="submit"]').click();
      await expect(page.getByRole('status')).toContainText(/queued/i);
      await shot(page, info, 'webhook-03-test-queued');

      const deliverySeen = Promise.race([
        receivedPromise,
        new Promise<void>((_, reject) => setTimeout(() => reject(new Error('receiver got no POST')), 10_000)),
      ]);
      await runWebhookWorker(path.resolve(__dirname, '..', '..'));
      await deliverySeen;
      expect(receivedEvent).toBe('ping');
    } finally {
      await new Promise<void>((resolve) => server.close(() => resolve()));
    }
  });
});
```

- [ ] **Step 8.4: Add the JS-enabled axe scan of the webhook admin surfaces.** AxeBuilder runs axe-core in-page (needs JS), so it lives in a separate default-JS describe:
```ts
test.describe('webhook admin a11y', () => {
  async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include?: string): Promise<void> {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    if (include !== undefined) builder = builder.include(include);
    const results = await builder.analyze();
    const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
  }

  test('webhook list and detail have no serious axe violations', async ({ page }, info) => {
    await login(page, 'admin@retro.test');
    await visit(page, '/admin/webhooks');
    await expect(page.getByRole('heading', { name: 'Webhooks' })).toBeVisible();
    await expectNoSeriousA11yViolations(page, info);

    // Register through the same no-JS form so a detail page exists to scan.
    const name = `A11y webhook (${info.project.name}-${Date.now()})`;
    await page.fill('input[name="name"]', name);
    await page.fill('input[name="url"]', 'https://example.test/hook');
    await page.check('input[name="events[]"][value="ping"]');
    await page.fill('input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Register endpoint' }).click();
    await expect(page.getByText(/will not be shown again/)).toBeVisible();

    await visit(page, '/admin/webhooks');
    await page.locator('table tbody tr', { hasText: name }).getByRole('link', { name: 'Manage' }).click();
    await page.waitForURL(/\/admin\/webhooks\/\d+$/);
    await expectNoSeriousA11yViolations(page, info);
    await shot(page, info, 'webhook-04-detail-a11y');
  });
});
```

- [ ] **Step 8.5: Add an `evidence:webhooks` script to `tests/browser/package.json`.** Insert after the `a11y` line (this spec seeds `webhooks`+`service_secrets` via `prepare.sh`/`seed.php`, already ON):
```json
    "evidence:webhooks": "bash prepare.sh && playwright test webhooks.spec.ts",
```

- [ ] **Step 8.6: Run the browser evidence (desktop + mobile) to GREEN.** From `tests/browser`: `npm run prepare-db && npx playwright test webhooks.spec.ts`. Expected: 4 passing runs (2 tests × desktop+mobile), and new PNGs written under `docs/evidence/browser/desktop/` and `docs/evidence/browser/mobile/` (`webhook-01..04-*.png`). Confirm with `ls ../../docs/evidence/browser/desktop/webhook-*.png`.

- [ ] **Step 8.7: Advance `SLICE-WEBHOOKS` R3→R4 in the ledger.** Edit the `SLICE-WEBHOOKS` object in `docs/phase5/requirement-ledger.json`: set `"state": "R4"`, extend `evidence` to include the three new artifacts, and update `notes`:
```json
    { "id": "SLICE-WEBHOOKS", "gate": "A", "workstream": "B2-SP3", "title": "Outbound webhook delivery engine", "state": "R4", "evidence": ["tests/Integration/Service/WebhookServiceTest.php", "tests/Unit/Security/EgressGuardAdversarialTest.php", "tests/Integration/Worker/WebhookIdempotencyTest.php", "tests/browser/webhooks.spec.ts", "docs/evidence/phase5/webhook-idempotency.md"], "notes": "SP0 retrofit landed: formal SSRF/egress adversarial corpus (validateStatic + validate incl. DNS-rebind), delivery-idempotency proof + report, and no-JS register/reveal/rotate/send-test + axe browser evidence. Evidence-only, no production change." },
```

- [ ] **Step 8.8: Add a runbook evidence pointer.** In `docs/runbooks/package_registry.md`, add one line under the webhook/security-response section pointing operators at `docs/evidence/phase5/webhook-idempotency.md` and the SSRF adversarial suite (`tests/Unit/Security/EgressGuardAdversarialTest.php`) as the SSRF/idempotency evidence of record.

- [ ] **Step 8.9: Verify the full PHPUnit suite is still green (guard against strict-mode fallout).** Run from repo root: `composer test`. Expected: green, including the two new PHPUnit files from Tasks 6 and 7.

- [ ] **Step 8.10: Commit the browser evidence + ledger/runbook advance.**
```bash
git add tests/browser/webhooks.spec.ts tests/browser/package.json \
        docs/phase5/requirement-ledger.json docs/runbooks/package_registry.md \
        docs/evidence/browser/desktop/webhook-*.png docs/evidence/browser/mobile/webhook-*.png
git commit -m "test(webhooks): no-JS register/reveal/rotate/send-test + axe evidence; advance SLICE-WEBHOOKS R3->R4

No-JS Playwright journey (register, one-time secret reveal, secret rotation,
send-test with live delivery) plus a JS-enabled axe scan of the webhook admin
surfaces at desktop + mobile. Advances SLICE-WEBHOOKS to R4 with the egress
adversarial corpus, idempotency report, and browser/axe artifacts. Evidence only.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

**Group exit deliverable:** three independently-committable, green evidence artifacts — `EgressGuardAdversarialTest` (SSRF corpus, both guard layers), `WebhookIdempotencyTest` + `webhook-idempotency.md` (triple dedup, effectively-once, dead-letter/replay, wired SSRF denial, flag-dark 404), and `webhooks.spec.ts` (no-JS + axe, desktop+mobile) — with `SLICE-WEBHOOKS` advanced R3→R4 and no production code touched.


<!-- ===== group: sp0-secrets-hooks-retrofit — SP0 — service_secrets redaction/revoke/prune coverage + first_party_hooks private-content-absence proof ===== -->

The SP0 secrets and hook-hardening tasks below rely on the landed `SecretVault`, `SecretBox`, first-party domain producers, enforcement tests, and `TestCase` helpers.

---

### Task 11: SP0 SecretVault redaction / revoke / prune leak-proof corpus (TM-SE-02/04/05)

**SP0 discipline:** this slice adds **no production code** — it is an adversarial characterization corpus over the already-shipped `service_secrets` seam. Green-on-first-run is the success signal. A RED here is a *real disclosure defect* in `SecretVault`: STOP, invoke `superpowers:systematic-debugging`, and fix the vault — never weaken an assertion to make it pass. Baseline round-trips already live in `tests/Integration/Service/SecretVaultTest.php`; this file only adds the new adversarial angles (full-lifecycle audit sweep incl. destroyed rows, forced-vault-failure, revoked/forged-reference isolation, two-version ciphertext zeroing).

**Files:**
- Create/Test: `tests/Integration/Service/SecretVaultRedactionTest.php`

**Interfaces:**

Consumes (exact existing signatures — do not modify):
```php
// App\Service\SecretVault (final)
public function store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor = null): string; // returns svcsec_* ref
public function reveal(string $ref): string;                       // throws SecretNotFoundException / SecretRevokedException / RuntimeException
public function rotate(string $ref, string $newPlaintext, ?User $actor = null, ?int $graceSeconds = null): int;
public function revoke(string $ref, ?User $actor = null): void;
public function metadata(string $ref): array;                     // never contains plaintext/ciphertext
public function prune(int $limit = 100): int;                     // destroys+zeroes retired versions
// App\Security\SecretBox (final) — decrypt() throws RuntimeException('Unable to decrypt secret.') on wrong key/tag
public function __construct(private string $appKey);
```
Produces: `SecretVaultRedactionTest` (4 methods), evidenced for `SLICE-SERVICE-SECRETS` and fixtures `TM-SE-02/04/05` (flipped in Task 13).

Steps:

- [ ] **Step 1: Confirm the RED baseline.** With the test DB reachable (`docker start rb-mariadb` if using the dev container), run `vendor/bin/phpunit tests/Integration/Service/SecretVaultRedactionTest.php` and confirm PHPUnit errors with `Cannot open file ".../SecretVaultRedactionTest.php".` (file absent) and that `grep -n 'TM-SE-02' docs/phase5/threat-models/fixtures.json` still shows `"status": "stub"`. This is the red state the task closes.

- [ ] **Step 2: Write the full adversarial test file.** Create `tests/Integration/Service/SecretVaultRedactionTest.php` verbatim:
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretRevokedException;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use RuntimeException;
use Tests\Support\TestCase;

/**
 * SP0 adversarial redaction corpus for the service_secrets seam (SLICE-SERVICE-SECRETS).
 * Baseline round-trips live in SecretVaultTest; this file is the leak-proof proof that
 * lands TM-SE-02 (a config read after save exposes no plaintext), TM-SE-04 (a revoked /
 * forged reference fails closed while the lifecycle is audited), and TM-SE-05 (a forced
 * vault failure surfaces only the svcsec_ reference, never plaintext).
 *
 * No production code changes: a RED here is a real disclosure defect in SecretVault -
 * debug the vault, never weaken the assertion.
 */
final class SecretVaultRedactionTest extends TestCase
{
    private const KEY_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const KEY_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private function vault(string $key = self::KEY_A, bool $enabled = true): SecretVault
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => $enabled]);
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox($key),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    /** @return list<string> every before|after audit blob for this secret */
    private function auditBlobs(int $secretId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT before_json, after_json FROM moderation_log
             WHERE target_type = 'service_secret' AND target_id = ?",
            [$secretId],
        );
        return array_map(
            static fn (array $r): string => (string) ($r['before_json'] ?? '') . '|' . (string) ($r['after_json'] ?? ''),
            $rows,
        );
    }

    public function test_config_read_after_save_and_rotate_exposes_no_plaintext_anywhere(): void // TM-SE-02
    {
        $v = $this->vault();
        $ref = $v->store('provider', 42, 'oauth client secret', 'PLAINTEXT-V1-2a9f');
        $v->rotate($ref, 'PLAINTEXT-V2-7c31');

        $meta = $v->metadata($ref);
        $metaJson = json_encode($meta) ?: '';
        self::assertSame($ref, $meta['ref']);
        self::assertSame('oauth client secret', $meta['label']);
        self::assertStringNotContainsString('PLAINTEXT-V1-2a9f', $metaJson);
        self::assertStringNotContainsString('PLAINTEXT-V2-7c31', $metaJson);
        self::assertArrayNotHasKey('plaintext', $meta);
        self::assertArrayNotHasKey('ciphertext', $meta);

        // Sweep the entire lifecycle audit trail, destroyed rows included.
        $v->revoke($ref);
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
             WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );
        self::assertGreaterThan(0, $v->prune(100));

        $blobs = $this->auditBlobs($id);
        self::assertNotEmpty($blobs, 'store/rotate/revoke/destroy must all audit');
        foreach ($blobs as $blob) {
            self::assertStringNotContainsString('PLAINTEXT-V1-2a9f', $blob);
            self::assertStringNotContainsString('PLAINTEXT-V2-7c31', $blob);
        }
        self::assertStringContainsString($ref, implode('', $blobs), 'audit references the secret by its svcsec_ ref, not plaintext');
    }

    public function test_forced_vault_failure_yields_reference_only_never_plaintext(): void // TM-SE-05
    {
        $ref = $this->vault(self::KEY_A)->store('provider', 7, 'client secret', 'PLAINTEXT-DR-91b4');

        // Master key unavailable / rotated-away (a DR / vault-failure scenario): decrypt cannot succeed.
        $degraded = $this->vault(self::KEY_B);

        // The reference + metadata still resolve - that is ALL a failed vault may surface.
        $meta = $degraded->metadata($ref);
        self::assertSame($ref, $meta['ref']);
        self::assertStringStartsWith('svcsec_', $meta['ref']);
        self::assertStringNotContainsString('PLAINTEXT-DR-91b4', json_encode($meta) ?: '');

        try {
            $degraded->reveal($ref);
            self::fail('a wrong-key reveal must fail closed');
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString('PLAINTEXT-DR-91b4', $e->getMessage());
        }
    }

    public function test_revoked_or_forged_reference_fails_closed_and_revoke_is_audited(): void // TM-SE-04
    {
        $v = $this->vault();
        $ref = $v->store('provider', 3, 'to be cut off', 'PLAINTEXT-CUT-5d2e');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);

        // Revoking is how a consumer is cut off; it is an audited action carrying no plaintext.
        $v->revoke($ref);
        $revokeAudits = $this->db->fetchAll(
            "SELECT after_json FROM moderation_log
             WHERE target_type = 'service_secret' AND target_id = ? AND action = 'service_secret_revoked'",
            [$id],
        );
        self::assertCount(1, $revokeAudits, 'revoke writes exactly one audit row');
        self::assertStringNotContainsString('PLAINTEXT-CUT-5d2e', (string) $revokeAudits[0]['after_json']);

        // A now-non-owning consumer presenting the revoked reference is denied.
        try {
            $v->reveal($ref);
            self::fail('a revoked reference must not reveal');
        } catch (SecretRevokedException $e) {
            self::assertStringNotContainsString('PLAINTEXT-CUT-5d2e', $e->getMessage());
        }

        // A forged / never-owned reference is denied without disclosing anything.
        $this->expectException(SecretNotFoundException::class);
        $v->reveal('svcsec_' . str_repeat('0', 32));
    }

    public function test_revoke_then_prune_zeroes_both_versions_ciphertext(): void // redaction / prune
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rotated then revoked', 'PLAINTEXT-Z1-11aa');
        $v->rotate($ref, 'PLAINTEXT-Z2-22bb');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ?', [$id]));

        // Revoke retires every version immediately; prune must then destroy them all.
        $v->revoke($ref);
        self::assertSame(2, $v->prune(100), 'both versions become prunable on revoke');

        $rows = $this->db->fetchAll(
            'SELECT state, destroyed_at, LENGTH(ciphertext) AS cl, LENGTH(nonce) AS nl, LENGTH(tag) AS tl
             FROM service_secret_versions WHERE secret_id = ? ORDER BY version',
            [$id],
        );
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertSame('destroyed', (string) $row['state']);
            self::assertNotNull($row['destroyed_at']);
            self::assertSame(0, (int) $row['cl'], 'ciphertext zeroed');
            self::assertSame(0, (int) $row['nl'], 'nonce zeroed');
            self::assertSame(0, (int) $row['tl'], 'tag zeroed');
        }
    }
}
```

- [ ] **Step 3: Run TM-SE-02 first, expect GREEN.** `vendor/bin/phpunit --filter test_config_read_after_save_and_rotate_exposes_no_plaintext_anywhere` → expect `OK (1 test, N assertions)`. If RED, the audit/metadata seam leaks plaintext: stop and debug the vault, do not edit the test.

- [ ] **Step 4: Run the whole file, expect GREEN.** `vendor/bin/phpunit tests/Integration/Service/SecretVaultRedactionTest.php` → expect `OK (4 tests, ...)`. This proves TM-SE-02 (metadata + full audit sweep), TM-SE-05 (wrong-key reveal), TM-SE-04 (revoked/forged reference isolation + audited cut-off), and the two-version ciphertext-zeroing prune.

- [ ] **Step 5: Guard against regressing the baseline.** `vendor/bin/phpunit tests/Integration/Service/SecretVaultTest.php` → expect `OK` (the new file must not have disturbed existing coverage).

- [ ] **Step 6: Commit.**
```bash
git add tests/Integration/Service/SecretVaultRedactionTest.php
git commit -m "$(cat <<'EOF'
test(secrets): SP0 SecretVault redaction/revoke/prune leak-proof corpus (TM-SE-02/04/05)

Adversarial coverage that no plaintext reaches metadata, audit rows, or
exception messages; a forced (wrong-key) vault failure yields only the svcsec_
reference; a revoked/forged reference fails closed while the cut-off is audited;
and revoke -> prune zeroes ciphertext/nonce/tag on every retired version.
No production code changes.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 12: SP0 first-party domain-event private-content-absence proof

**SP0 discipline:** no production code — this proves the already-shipped domain producers over the real in-process kernel. Green-on-first-run is success. A RED is a *real content leak* in a producer (a hidden/private board or DM report emitting, or a payload carrying a title/body/email/free-text reason): STOP, `superpowers:systematic-debugging`, fix the producer, never soften the assertion. This group ships no routes/UI, so it needs no Playwright/axe evidence and no `AppFeatureFlagTest` 404 assertions (those belong to the integration/security console groups). Overlap with the happy path in `DomainWebhookProducerTest` is intentional: this is the dedicated proof file the ledger/fixtures will point at.

**Files:**
- Create/Test: `tests/Integration/Service/FirstPartyHookPrivateContentTest.php`

**Interfaces:**

Consumes (exact existing signatures — do not modify):
```php
// App\Security\WebhookEvents — event grants ⊆ domainEvents(); 'ping' is never a domain event
public static function domainEvents(): array;
// App\Repository\WebhookRepository::insert(name, url, eventsJson, secretRef, createdBy): int
// first_party_hooks producers enqueue webhook_deliveries(payload) at write time via App::handle()
```
Produces: `FirstPartyHookPrivateContentTest` (5 methods), evidence for `SLICE-FIRST-PARTY-HOOKS` (bumped in Task 13).

Steps:

- [ ] **Step 1: Confirm the RED baseline.** `vendor/bin/phpunit tests/Integration/Service/FirstPartyHookPrivateContentTest.php` → expect `Cannot open file` (absent), and `grep -n 'Private-content-absence proof outstanding' docs/phase5/requirement-ledger.json` still matches. This is the state the task closes.

- [ ] **Step 2: Write the full content-absence test file.** Create `tests/Integration/Service/FirstPartyHookPrivateContentTest.php` verbatim (harness mirrors `DomainWebhookProducerTest`; note `makeThread()` returns `{thread_id, slug}`, so the OP post id is read from `posts`):
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\App;
use App\Repository\SettingRepository;
use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

/**
 * SP0 private-content-absence proof for the first-party domain producers
 * (SLICE-FIRST-PARTY-HOOKS). Two guarantees, proved against the real kernel:
 *   1. board-visibility gate - hidden/private board topics and DM reports emit
 *      NO delivery at all; and
 *   2. payload minimization - the events that DO fire carry IDs + enums only,
 *      never titles, bodies, emails, or free-text reasons.
 *
 * No production code changes: a RED here is a real content leak in a producer.
 */
final class FirstPartyHookPrivateContentTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', [
            'webhooks' => true,
            'service_secrets' => true,
            'first_party_hooks' => true,
        ]);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->admin = $this->makeAdmin(['username' => 'privadmin', 'password' => 'password123']);
    }

    /** @param list<string> $events */
    private function registerEndpoint(array $events): int
    {
        return (new WebhookRepository($this->db))->insert(
            'priv-' . bin2hex(random_bytes(4)),
            'https://example.test/hook',
            json_encode($events) ?: '[]',
            'svcsec_test',
            (int) $this->admin['id'],
        );
    }

    /** @param array<string,mixed> $author */
    private function createThreadOverHttp(array $author, int $boardId, string $title, string $body): void
    {
        $this->actingAs($author);
        self::assertContains($this->post('/threads', [
            'board_id' => $boardId,
            'title' => $title,
            'body' => $body,
            'idempotency_key' => 'priv-' . bin2hex(random_bytes(6)),
        ])->status(), [302, 303]);
    }

    /** @return list<string> raw delivery payloads for an endpoint+event */
    private function payloads(int $webhookId, string $event): array
    {
        $rows = $this->db->fetchAll(
            'SELECT payload FROM webhook_deliveries WHERE webhook_id = ? AND event_type = ? ORDER BY id',
            [$webhookId, $event],
        );
        return array_map(static fn (array $r): string => (string) $r['payload'], $rows);
    }

    public function test_hidden_and_private_board_topics_emit_no_delivery(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $cat = $this->makeCategory();
        $hidden = $this->makeBoard($cat, ['slug' => 'priv-hidden', 'visibility' => 'hidden']);
        $private = $this->makeBoard($cat, ['slug' => 'priv-private', 'visibility' => 'private']);

        $author = $this->makeUser(['username' => 'hiddenauthor']);
        $this->createThreadOverHttp($author, (int) $hidden['id'], 'Hidden title MUST NOT SHIP', 'Hidden body MUST NOT SHIP');

        $this->actingAs($this->admin);
        $this->createThreadOverHttp($this->admin, (int) $private['id'], 'Private title MUST NOT SHIP', 'Private body MUST NOT SHIP');

        self::assertSame([], $this->payloads($topicHook, 'topic.created'), 'non-public boards emit no domain event');
    }

    public function test_public_topic_payload_carries_ids_and_enums_only(): void
    {
        $topicHook = $this->registerEndpoint(['topic.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'priv-public']);
        $author = $this->makeUser(['username' => 'publicauthor']);
        $this->createThreadOverHttp($author, (int) $board['id'], 'Public title MUST NOT SHIP', 'Public body MUST NOT SHIP');

        $payloads = $this->payloads($topicHook, 'topic.created');
        self::assertCount(1, $payloads);
        $raw = $payloads[0];
        self::assertStringNotContainsString('Public title MUST NOT SHIP', $raw);
        self::assertStringNotContainsString('Public body MUST NOT SHIP', $raw);

        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('thread:', (string) $payload['id']);
        self::assertIsArray($payload['data']);
        foreach (['title', 'body', 'body_html', 'content', 'excerpt'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload['data'], "data must not carry $forbidden");
        }
    }

    public function test_dm_report_emits_no_report_event_and_leaks_no_dm_content(): void
    {
        $reportHook = $this->registerEndpoint(['report.created']);
        $alice = $this->makeUser(['username' => 'dmpriva']);
        $this->makeUser(['username' => 'dmprivb']);
        $public = $this->makeBoard($this->makeCategory(), ['slug' => 'dm-priv-establish']);
        $this->makeThread($public, $alice, 'Establish sender', 'gives alice a post');

        $this->actingAs($alice);
        $this->assertRedirectContains(
            $this->post('/messages', ['to' => 'dmprivb', 'body' => 'DM body MUST NOT SHIP']),
            '/messages/',
        );
        $messageId = (int) $this->db->fetchValue('SELECT id FROM dm_messages ORDER BY id DESC LIMIT 1');

        $bob = $this->users()->findByUsername('dmprivb');
        self::assertNotNull($bob);
        $this->actingAs($bob);
        $this->assertRedirectContains(
            $this->post('/dm/' . $messageId . '/report', ['reason_code' => 'abuse', 'reason' => 'DM reason MUST NOT SHIP']),
            '/messages/',
        );

        self::assertSame([], $this->payloads($reportHook, 'report.created'), 'DM reports never become a public webhook event');
    }

    public function test_public_report_payload_omits_reason_reporter_and_body(): void
    {
        $reportHook = $this->registerEndpoint(['report.created']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'priv-report']);
        $author = $this->makeUser(['username' => 'reportedauthor']);
        $thread = $this->makeThread($board, $author, 'Reportable topic', 'Reportable OP body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [(int) $thread['thread_id']]);

        $reporter = $this->makeUser(['username' => 'reporterpriv']);
        $this->actingAs($reporter);
        $this->assertRedirectContains(
            $this->post('/posts/' . $postId . '/report', ['reason_code' => 'spam', 'reason' => 'Report reason MUST NOT SHIP']),
            '/t/',
        );

        $payloads = $this->payloads($reportHook, 'report.created');
        self::assertCount(1, $payloads);
        $raw = $payloads[0];
        self::assertStringNotContainsString('Report reason MUST NOT SHIP', $raw);
        self::assertStringNotContainsString('Reportable OP body', $raw);

        $payload = json_decode($raw, true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('report:', (string) $payload['id']);
        foreach (['reason', 'reason_text', 'note', 'body', 'reporter_username'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload['data']);
        }
    }

    public function test_member_registered_payload_omits_email(): void
    {
        $memberHook = $this->registerEndpoint(['member.registered']);
        $this->logoutClient();
        $this->get('/register');
        $this->assertRedirectContains($this->post('/register', [
            'username' => 'freshpriv',
            'email' => 'freshpriv@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]), '/');

        $payloads = $this->payloads($memberHook, 'member.registered');
        self::assertCount(1, $payloads);
        self::assertStringNotContainsString('freshpriv@example.test', $payloads[0], 'email is PII and must never ship');
        $payload = json_decode($payloads[0], true);
        self::assertIsArray($payload);
        self::assertStringStartsWith('user:', (string) $payload['id']);
        self::assertArrayNotHasKey('email', $payload['data']);
    }
}
```

- [ ] **Step 3: Verify the `UserRepository::findByUsername` helper exists** (used to switch to Bob). Run `grep -n 'function findByUsername' src/Repository/UserRepository.php`; if it is named differently, resolve Bob's row via `$this->users()->find((int) $this->db->fetchValue("SELECT id FROM users WHERE username = 'dmprivb'"))` instead and adjust the one line. (One 2-min reconciliation before first green.)

- [ ] **Step 4: Run the suppression gate first, expect GREEN.** `vendor/bin/phpunit --filter test_hidden_and_private_board_topics_emit_no_delivery` → `OK (1 test, ...)`. If RED, a hidden/private board is emitting a domain event: stop and fix the producer's `board_visibility` gate.

- [ ] **Step 5: Run the whole file, expect GREEN.** `vendor/bin/phpunit tests/Integration/Service/FirstPartyHookPrivateContentTest.php` → `OK (5 tests, ...)`. Proves hidden/private suppression, DM-report suppression, and payload minimization (no title/body, no email, no free-text reason).

- [ ] **Step 6: Guard the sibling producer suite.** `vendor/bin/phpunit tests/Integration/Service/DomainWebhookProducerTest.php` → expect `OK` (no regression).

- [ ] **Step 7: Commit.**
```bash
git add tests/Integration/Service/FirstPartyHookPrivateContentTest.php
git commit -m "$(cat <<'EOF'
test(hooks): SP0 first-party domain-event private-content-absence proof

Proves the domain producers suppress hidden/private-board topics and DM reports
entirely, and that emitted events carry IDs + enums only - never titles, bodies,
emails, or free-text reasons (board-visibility gate + payload minimization).
No production code changes.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 13: Land TM-SE-02/04/05 and advance SLICE-SERVICE-SECRETS / SLICE-FIRST-PARTY-HOOKS to R4

**Deliverable:** the two enforcement guards (`ThreatModelIndexTest`, `Phase5EvidenceMapTest`) turn the just-added test files into recorded evidence. `ThreatModelIndexTest` requires every `implemented` fixture to name an **existing** `test` file (created in Task 11), and `Phase5EvidenceMapTest` requires every `R3/R4/R5` ledger row's evidence paths to exist (created in Tasks 11–12). This task is docs/fixture-only — no production code.

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (flip `TM-SE-02`, `TM-SE-04`, `TM-SE-05` `stub`→`implemented`)
- Modify: `docs/phase5/requirement-ledger.json` (`SLICE-SERVICE-SECRETS` and `SLICE-FIRST-PARTY-HOOKS` `R3`→`R4` + evidence)
- Test (enforcement): `tests/Unit/Core/ThreatModelIndexTest.php`, `tests/Unit/Core/Phase5EvidenceMapTest.php`

**Interfaces:** Consumes the two guard contracts above (JSON shape asserted by `validate()` in each guard). Produces the flipped fixture/ledger entries. `TM-SC-08` stays `stub` — it belongs to the runtime-gating task group, not this one.

Steps:

- [ ] **Step 1: Flip TM-SE-02.** In `docs/phase5/threat-models/fixtures.json`, edit the `TM-SE-02` object:
  - old: `{ "id": "TM-SE-02", "model": "secret-handling.md", "fixture": "reading a webhook/token/provider config after save returns no plaintext", "owner": "Inc5", "status": "stub" },`
  - new: `{ "id": "TM-SE-02", "model": "secret-handling.md", "fixture": "reading a webhook/token/provider config after save returns no plaintext", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" },`

- [ ] **Step 2: Flip TM-SE-04.** Edit the `TM-SE-04` object the same way:
  - old: `..."fixture": "non-owning consumer reveal attempt fails and audits", "owner": "Inc5", "status": "stub" },`
  - new: `..."fixture": "non-owning consumer reveal attempt fails and audits", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" },`

- [ ] **Step 3: Flip TM-SE-05.** Edit the `TM-SE-05` object:
  - old: `..."fixture": "forced vault failure yields svcsec reference only, no plaintext", "owner": "Inc5", "status": "stub" },`
  - new: `..."fixture": "forced vault failure yields svcsec reference only, no plaintext", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" },`

- [ ] **Step 4: Run the fixture guard, expect GREEN.** `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php` → `OK`. (Confirms the three fixtures still appear in `secret-handling.md`, and their `test` path resolves to the Task-11 file. `secret-handling.md` already documents TM-SE-02/04/05, so no dossier edit is needed — verify with `grep -c 'TM-SE-0[245]' docs/phase5/threat-models/secret-handling.md`.)

- [ ] **Step 5: Advance SLICE-SERVICE-SECRETS to R4.** In `docs/phase5/requirement-ledger.json`, edit that row:
  - old: `{ "id": "SLICE-SERVICE-SECRETS", "gate": "A", "workstream": "B2-SP1", "title": "Service-secret registry (SecretVault)", "state": "R3", "evidence": ["tests/Integration/Service/SecretVaultTest.php"] },`
  - new: `{ "id": "SLICE-SERVICE-SECRETS", "gate": "A", "workstream": "B2-SP1", "title": "Service-secret registry (SecretVault)", "state": "R4", "evidence": ["tests/Integration/Service/SecretVaultTest.php", "tests/Integration/Service/SecretVaultRedactionTest.php"], "notes": "SP0 redaction/revoke/prune corpus landed TM-SE-02/04/05 (2026-07-03): no plaintext in metadata/audit/exceptions, forced-failure yields svcsec ref only, revoke zeroes retired ciphertext." },`

- [ ] **Step 6: Advance SLICE-FIRST-PARTY-HOOKS to R4.** Edit that row:
  - old: `{ "id": "SLICE-FIRST-PARTY-HOOKS", "gate": "A", "workstream": "B2-SP4", "title": "First-party hook registry + domain producers", "state": "R3", "evidence": ["tests/Unit/Hook/FirstPartyHookRegistryTest.php"], "notes": "Private-content-absence proof outstanding." }`
  - new: `{ "id": "SLICE-FIRST-PARTY-HOOKS", "gate": "A", "workstream": "B2-SP4", "title": "First-party hook registry + domain producers", "state": "R4", "evidence": ["tests/Unit/Hook/FirstPartyHookRegistryTest.php", "tests/Integration/Service/FirstPartyHookPrivateContentTest.php"], "notes": "Private-content-absence proof landed 2026-07-03: hidden/private boards + DM reports suppress; emitted events carry IDs+enums only." }`

- [ ] **Step 7: Run the ledger guard, expect GREEN.** `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php` → `OK`. (Both new evidence paths exist; R4 is a valid state.)

- [ ] **Step 8: Run the full slice + guards together as the exit gate.** `vendor/bin/phpunit tests/Integration/Service/SecretVaultRedactionTest.php tests/Integration/Service/FirstPartyHookPrivateContentTest.php tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/Phase5EvidenceMapTest.php` → expect `OK` across all. This is the independently testable deliverable for the group.

- [ ] **Step 9: Commit.**
```bash
git add docs/phase5/threat-models/fixtures.json docs/phase5/requirement-ledger.json
git commit -m "$(cat <<'EOF'
docs(phase5): land TM-SE-02/04/05; advance SLICE-SERVICE-SECRETS + SLICE-FIRST-PARTY-HOOKS to R4

Flip the three secret-handling fixtures stub -> implemented pointing at
SecretVaultRedactionTest, and bump both B2 slices to R4 with the new redaction
and private-content-absence proofs as evidence. Guards green.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```


<!-- ===== group: migration-0073-and-repos — Migration 0073 + settings/credential/publisher-key repositories ===== -->


---

### Task 16: Migration `0073` — settings + credential tables, enum widens (schema-shape test first)

**Files:**
- Create: `database/migrations/0073_phase5_package_integrations.php`
- Test: `tests/Integration/Core/AppPackageIntegrationSchemaTest.php`

**Interfaces:**
- **Consumes:** existing tables `installed_packages(id)`, `api_tokens(id)`, `webhooks(id)`, `users(id)`; the current `package_history.event` enum (last widened by `0072`, ending `theme_deactivate`); the current `moderation_log.target_type` enum (last widened by `0068`, ending `package`). Next gapless migration number is `0073` (confirmed after `0072_phase5_theme_packages`).
- **Produces (locked DDL):** tables `installed_package_settings`, `installed_package_credentials`; `package_history.event` gains `settings_update`/`credential_mint`/`credential_revoke`; `moderation_log.target_type` gains `publisher`.

- [ ] **Step 1: Write the schema-shape test (red).** Create `tests/Integration/Core/AppPackageIntegrationSchemaTest.php` — pure `information_schema` assertions (no row seeding needed; the bootstrap re-migrates the test DB each run):
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Core;

  use Tests\Support\TestCase;

  final class AppPackageIntegrationSchemaTest extends TestCase
  {
      public function test_settings_and_credential_tables_match_documented_shape(): void
      {
          self::assertSame(
              ['id', 'installed_package_id', 'setting_key', 'value_json', 'secret_ref', 'is_secret', 'updated_by', 'updated_at'],
              $this->columns('installed_package_settings'),
          );
          self::assertSame(
              ['id', 'installed_package_id', 'kind', 'api_token_id', 'webhook_id', 'label', 'scopes_json', 'events_json', 'created_by', 'created_at', 'revoked_at'],
              $this->columns('installed_package_credentials'),
          );
      }

      public function test_settings_unique_key_and_secret_index_exist(): void
      {
          $idx = $this->indexes('installed_package_settings');
          self::assertContains('uq_install_setting', $idx);
          self::assertContains('idx_install_setting_secret', $idx);
      }

      public function test_credential_foreign_keys_reference_b2_tables(): void
      {
          $fks = $this->foreignKeys('installed_package_credentials');
          self::assertSame('installed_packages', $fks['fk_install_cred_install'] ?? null);
          self::assertSame('api_tokens', $fks['fk_install_cred_api_token'] ?? null);
          self::assertSame('webhooks', $fks['fk_install_cred_webhook'] ?? null);
          self::assertSame('users', $fks['fk_install_cred_user'] ?? null);
      }

      public function test_package_history_enum_gains_credential_and_settings_events(): void
      {
          $type = (string) $this->db->fetch(
              "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_history' AND COLUMN_NAME = 'event'",
          )['t'];
          self::assertStringContainsString("'settings_update'", $type);
          self::assertStringContainsString("'credential_mint'", $type);
          self::assertStringContainsString("'credential_revoke'", $type);
      }

      public function test_moderation_log_target_type_gains_publisher(): void
      {
          $type = (string) $this->db->fetch(
              "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
          )['t'];
          self::assertStringContainsString("'publisher'", $type);
      }

      /** @return list<string> */
      private function columns(string $table): array
      {
          return array_map(
              static fn (array $r): string => (string) $r['c'],
              $this->db->fetchAll(
                  'SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                  [$table],
              ),
          );
      }

      /** @return list<string> */
      private function indexes(string $table): array
      {
          return array_map(
              static fn (array $r): string => (string) $r['n'],
              $this->db->fetchAll(
                  'SELECT DISTINCT INDEX_NAME AS n FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                  [$table],
              ),
          );
      }

      /** @return array<string,string> constraint_name => referenced_table */
      private function foreignKeys(string $table): array
      {
          $out = [];
          foreach ($this->db->fetchAll(
              'SELECT CONSTRAINT_NAME AS c, REFERENCED_TABLE_NAME AS r FROM information_schema.KEY_COLUMN_USAGE
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
              [$table],
          ) as $row) {
              $out[(string) $row['c']] = (string) $row['r'];
          }

          return $out;
      }
  }
  ```
- [ ] **Step 2: Run the test — expect FAIL (tables absent).** `vendor/bin/phpunit --filter test_settings_and_credential_tables_match_documented_shape` → expected: `Failed asserting that two arrays are identical` (actual side is `[]` because `installed_package_settings` doesn't exist yet).
- [ ] **Step 3: Write the migration `up()` (verbatim locked DDL).** Create `database/migrations/0073_phase5_package_integrations.php` returning an anonymous class; copy the two `CREATE TABLE` blocks and both `ALTER TABLE … MODIFY` statements exactly from the Interface Contract:
  ```php
  <?php

  declare(strict_types=1);

  /**
   * Phase 5 Increment 5 (P5-04 / P5-07-A part 2): package integration runtime.
   * Adds per-install settings + package-owned credential links, and widens the
   * two enums the credential/settings lifecycle and publisher security-response
   * console depend on. Additive-only; inert until the Inc 5 services + `package_registry`.
   */
  return new class {
      public function up(\PDO $pdo): void
      {
          $pdo->exec(<<<'SQL'
              CREATE TABLE installed_package_settings (
                id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                installed_package_id BIGINT UNSIGNED NOT NULL,
                setting_key          VARCHAR(80)     NOT NULL,
                value_json           MEDIUMTEXT      NULL,
                secret_ref           VARCHAR(64)     NULL,
                is_secret            TINYINT(1)      NOT NULL DEFAULT 0,
                updated_by           BIGINT UNSIGNED NULL,
                updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_install_setting (installed_package_id, setting_key),
                KEY idx_install_setting_secret (secret_ref),
                CONSTRAINT fk_install_setting_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
                CONSTRAINT fk_install_setting_user    FOREIGN KEY (updated_by)           REFERENCES users(id)              ON DELETE SET NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          SQL);

          $pdo->exec(<<<'SQL'
              CREATE TABLE installed_package_credentials (
                id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                installed_package_id BIGINT UNSIGNED NOT NULL,
                kind                 ENUM('api_token','webhook') NOT NULL,
                api_token_id         BIGINT UNSIGNED NULL,
                webhook_id           BIGINT UNSIGNED NULL,
                label                VARCHAR(120)    NOT NULL,
                scopes_json          MEDIUMTEXT      NULL,
                events_json          MEDIUMTEXT      NULL,
                created_by           BIGINT UNSIGNED NULL,
                created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at           DATETIME        NULL,
                PRIMARY KEY (id),
                KEY idx_install_cred_install   (installed_package_id),
                KEY idx_install_cred_api_token (api_token_id),
                KEY idx_install_cred_webhook   (webhook_id),
                CONSTRAINT fk_install_cred_install   FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
                CONSTRAINT fk_install_cred_api_token FOREIGN KEY (api_token_id)         REFERENCES api_tokens(id)         ON DELETE SET NULL,
                CONSTRAINT fk_install_cred_webhook   FOREIGN KEY (webhook_id)           REFERENCES webhooks(id)           ON DELETE SET NULL,
                CONSTRAINT fk_install_cred_user      FOREIGN KEY (created_by)           REFERENCES users(id)              ON DELETE SET NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          SQL);

          $pdo->exec(<<<'SQL'
              ALTER TABLE package_history
                MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                  'uninstall','consent','health','update_staged','export','purge',
                                  'theme_activate','theme_rollback','theme_deactivate',
                                  'settings_update','credential_mint','credential_revoke') NOT NULL
          SQL);

          $pdo->exec(<<<'SQL'
              ALTER TABLE moderation_log
                MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                        'service_secret','api_token','webhook','registry','package','publisher') NOT NULL
          SQL);
      }

      public function down(\PDO $pdo): void
      {
          $pdo->exec("DELETE FROM package_history WHERE event IN ('settings_update','credential_mint','credential_revoke')");
          $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'publisher'");

          $pdo->exec(<<<'SQL'
              ALTER TABLE package_history
                MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                  'uninstall','consent','health','update_staged','export','purge',
                                  'theme_activate','theme_rollback','theme_deactivate') NOT NULL
          SQL);

          $pdo->exec(<<<'SQL'
              ALTER TABLE moderation_log
                MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                        'service_secret','api_token','webhook','registry','package') NOT NULL
          SQL);

          $pdo->exec('DROP TABLE IF EXISTS installed_package_credentials');
          $pdo->exec('DROP TABLE IF EXISTS installed_package_settings');
      }
  };
  ```
  Note: `down()` deletes the new-enum rows **before** narrowing (else the `MODIFY` truncates), then drops the credential table before the settings table (neither references the other; order is for symmetry with `up()`).
- [ ] **Step 4: Apply the migration to the dev DB.** `php bin/console migrate` → expected: `Migrated: 0073_phase5_package_integrations`. Then `php bin/console migrate:status` → `0073` shows applied, no pending.
- [ ] **Step 5: Run the schema test — expect PASS.** `vendor/bin/phpunit tests/Integration/Core/AppPackageIntegrationSchemaTest.php` (bootstrap re-migrates the test DB, now including `0073`) → expected: `OK (5 tests)`.
- [ ] **Step 6: Confirm the ledger stays contiguous.** `vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php` → expected: `OK` (no gap/duplicate; `0073` follows `0072`).
- [ ] **Step 7: Commit.**
  ```bash
  git add database/migrations/0073_phase5_package_integrations.php \
          tests/Integration/Core/AppPackageIntegrationSchemaTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): add migration 0073 for install settings + credentials

  Additive-only 0073_phase5_package_integrations: installed_package_settings
  (non-secret value_json + svcsec_* secret_ref) and installed_package_credentials
  (package-owned api_token/webhook links). Widens package_history.event (+settings_update,
  credential_mint, credential_revoke) and moderation_log.target_type (+publisher).
  Inert until Inc 5 services land behind package_registry. Schema-shape test asserts
  columns/indexes/FKs/enum widens.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 17: `InstalledPackageSettingsRepository`

**Files:**
- Create: `src/Repository/InstalledPackageSettingsRepository.php`
- Test: `tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php`

**Interfaces:**
- **Consumes:** `App\Core\Database`; `installed_package_settings` (Task 16); `Tests\Support\Phase5\RegistryFixtures::seed`, `SigningHarness::generate`, `InstalledPackageRepository::create` for a valid `installed_package_id`.
- **Produces (locked):**
  ```php
  public function forInstall(int $installedId): array;                              // SELECT * ... ORDER BY setting_key
  public function find(int $installedId, string $key): ?array;
  public function upsert(int $installedId, string $key, ?string $valueJson, ?string $secretRef, bool $isSecret, ?int $updatedBy): int;  // ON DUPLICATE KEY UPDATE on uq_install_setting
  public function secretRefsFor(int $installedId): array;                           // list<string> of svcsec_* refs
  public function deleteFor(int $installedId): void;
  ```

- [ ] **Step 1: Write the integration test (red).** Create `tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php`. Row reads within one test see the uncommitted writes, so assert on repo return values and read-backs (not cross-test persistence):
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Repository\InstalledPackageRepository;
  use App\Repository\InstalledPackageSettingsRepository;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class InstalledPackageSettingsRepositoryTest extends TestCase
  {
      public function test_upsert_inserts_then_updates_in_place_on_conflict(): void
      {
          $repo = new InstalledPackageSettingsRepository($this->db);
          $install = $this->seedInstall();

          $id = $repo->upsert($install, 'greeting', '"hello"', null, false, null);
          self::assertGreaterThan(0, $id);

          $again = $repo->upsert($install, 'greeting', '"world"', null, false, null);
          self::assertSame($id, $again, 'unique key collision must update the same row, not insert');

          $row = $repo->find($install, 'greeting');
          self::assertSame('"world"', $row['value_json']);
          self::assertSame(0, (int) $row['is_secret']);
          self::assertNull($row['secret_ref']);
          self::assertCount(1, $repo->forInstall($install));
      }

      public function test_secret_refs_harvest_only_secret_rows(): void
      {
          $repo = new InstalledPackageSettingsRepository($this->db);
          $install = $this->seedInstall();

          $repo->upsert($install, 'api_base', '"https://x.test"', null, false, null);
          $repo->upsert($install, 'api_key', null, 'svcsec_' . str_repeat('a', 12), true, null);

          self::assertSame(['svcsec_' . str_repeat('a', 12)], $repo->secretRefsFor($install));
      }

      public function test_delete_for_clears_all_rows(): void
      {
          $repo = new InstalledPackageSettingsRepository($this->db);
          $install = $this->seedInstall();
          $repo->upsert($install, 'a', '"1"', null, false, null);
          $repo->upsert($install, 'b', '"2"', null, false, null);

          $repo->deleteFor($install);
          self::assertSame([], $repo->forInstall($install));
      }

      private function seedInstall(): int
      {
          $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());

          return (new InstalledPackageRepository($this->db))->create([
              'package_id' => $seeded['package_id'],
              'release_id' => $seeded['release_id'],
              'digest' => $seeded['release_digest'],
              'source_registry_id' => $seeded['registry_id'],
              'publisher_id' => $seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => '0.1.0',
              'compat_max' => null,
              'installed_by' => null,
          ]);
      }
  }
  ```
- [ ] **Step 2: Run — expect FAIL (class missing).** `vendor/bin/phpunit tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php` → expected error: `Class "App\Repository\InstalledPackageSettingsRepository" not found`.
- [ ] **Step 3: Implement the repository.** Create `src/Repository/InstalledPackageSettingsRepository.php`. Use the house `VALUES(...)` upsert style (matches `SubscriptionRepository`); the `id = LAST_INSERT_ID(id)` trick makes `Database::insert` return the existing row id on the update path; `UTC_TIMESTAMP()` is a literal (not a placeholder), so reusing it is fine:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Repository;

  use App\Core\Database;

  /** Per-install settings over `installed_package_settings` (migration 0073). */
  final class InstalledPackageSettingsRepository
  {
      public function __construct(private Database $db)
      {
      }

      /** @return array<int,array<string,mixed>> */
      public function forInstall(int $installedId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM installed_package_settings WHERE installed_package_id = ? ORDER BY setting_key',
              [$installedId],
          );
      }

      /** @return array<string,mixed>|null */
      public function find(int $installedId, string $key): ?array
      {
          return $this->db->fetch(
              'SELECT * FROM installed_package_settings WHERE installed_package_id = ? AND setting_key = ?',
              [$installedId, $key],
          );
      }

      public function upsert(int $installedId, string $key, ?string $valueJson, ?string $secretRef, bool $isSecret, ?int $updatedBy): int
      {
          return $this->db->insert(
              'INSERT INTO installed_package_settings
                  (installed_package_id, setting_key, value_json, secret_ref, is_secret, updated_by, updated_at)
               VALUES (:iid, :k, :val, :ref, :sec, :by, UTC_TIMESTAMP())
               ON DUPLICATE KEY UPDATE
                  id         = LAST_INSERT_ID(id),
                  value_json = VALUES(value_json),
                  secret_ref = VALUES(secret_ref),
                  is_secret  = VALUES(is_secret),
                  updated_by = VALUES(updated_by),
                  updated_at = UTC_TIMESTAMP()',
              [
                  'iid' => $installedId,
                  'k' => $key,
                  'val' => $valueJson,
                  'ref' => $secretRef,
                  'sec' => $isSecret ? 1 : 0,
                  'by' => $updatedBy,
              ],
          );
      }

      /** @return list<string> svcsec_* references still held by this install (drives cleanup). */
      public function secretRefsFor(int $installedId): array
      {
          return array_map(
              static fn (array $r): string => (string) $r['secret_ref'],
              $this->db->fetchAll(
                  'SELECT secret_ref FROM installed_package_settings
                   WHERE installed_package_id = ? AND secret_ref IS NOT NULL',
                  [$installedId],
              ),
          );
      }

      public function deleteFor(int $installedId): void
      {
          $this->db->run('DELETE FROM installed_package_settings WHERE installed_package_id = ?', [$installedId]);
      }
  }
  ```
- [ ] **Step 4: Run — expect PASS.** `vendor/bin/phpunit tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php` → expected: `OK (3 tests)`.
- [ ] **Step 5: Commit.**
  ```bash
  git add src/Repository/InstalledPackageSettingsRepository.php \
          tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): add InstalledPackageSettingsRepository

  Single-table wrapper over installed_package_settings: upsert on
  uq_install_setting (VALUES()/LAST_INSERT_ID id-return), find/forInstall,
  secretRefsFor harvest for cleanup, deleteFor. Non-secret value_json vs
  secret_ref are mutually exclusive per row (service-enforced).

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 18: `InstalledPackageCredentialRepository`

**Files:**
- Create: `src/Repository/InstalledPackageCredentialRepository.php`
- Test: `tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php`

**Interfaces:**
- **Consumes:** `App\Core\Database`; `installed_package_credentials` (Task 16); real `api_tokens`/`webhooks` rows for the FK targets; `RegistryFixtures`/`InstalledPackageRepository` for the install.
- **Produces (locked):**
  ```php
  public function forInstall(int $installedId): array;                              // active + revoked, ORDER BY id DESC
  public function activeForInstall(int $installedId): array;                        // revoked_at IS NULL
  public function find(int $id): ?array;
  public function findByApiToken(int $apiTokenId): ?array;
  public function findByWebhook(int $webhookId): ?array;
  public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int;
  public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int;
  public function markRevoked(int $id): int;                                        // idempotent; returns rowCount
  public function deleteFor(int $installedId): void;
  ```

- [ ] **Step 1: Write the integration test (red).** Create `tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php`. Covers the app-enforced invariant (`kind='api_token'` ⇒ `api_token_id` set, `webhook_id` NULL, and vice-versa), idempotent revoke, and active-vs-all listing:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Repository\InstalledPackageCredentialRepository;
  use App\Repository\InstalledPackageRepository;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class InstalledPackageCredentialRepositoryTest extends TestCase
  {
      public function test_api_token_link_has_token_id_and_null_webhook(): void
      {
          $repo = new InstalledPackageCredentialRepository($this->db);
          $install = $this->seedInstall();
          $tokenId = $this->seedApiToken();

          $id = $repo->insertApiToken($install, $tokenId, 'pkg:acme read', '["forum.read"]', null);
          $row = $repo->find($id);

          self::assertSame('api_token', $row['kind']);
          self::assertSame($tokenId, (int) $row['api_token_id']);
          self::assertNull($row['webhook_id']);
          self::assertSame($id, (int) $repo->findByApiToken($tokenId)['id']);
      }

      public function test_webhook_link_has_webhook_id_and_null_token(): void
      {
          $repo = new InstalledPackageCredentialRepository($this->db);
          $install = $this->seedInstall();
          $hookId = $this->seedWebhook();

          $id = $repo->insertWebhook($install, $hookId, 'pkg:acme events', '["thread.created"]', null);
          $row = $repo->find($id);

          self::assertSame('webhook', $row['kind']);
          self::assertSame($hookId, (int) $row['webhook_id']);
          self::assertNull($row['api_token_id']);
          self::assertSame($id, (int) $repo->findByWebhook($hookId)['id']);
      }

      public function test_mark_revoked_is_idempotent_and_drops_from_active(): void
      {
          $repo = new InstalledPackageCredentialRepository($this->db);
          $install = $this->seedInstall();
          $id = $repo->insertApiToken($install, $this->seedApiToken(), 'pkg', '[]', null);

          self::assertCount(1, $repo->activeForInstall($install));
          self::assertSame(1, $repo->markRevoked($id), 'first revoke flips revoked_at');
          self::assertSame(0, $repo->markRevoked($id), 'second revoke is a no-op');
          self::assertSame([], $repo->activeForInstall($install));
          self::assertCount(1, $repo->forInstall($install), 'revoked rows still listed by forInstall');
      }

      private function seedInstall(): int
      {
          $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());

          return (new InstalledPackageRepository($this->db))->create([
              'package_id' => $seeded['package_id'],
              'release_id' => $seeded['release_id'],
              'digest' => $seeded['release_digest'],
              'source_registry_id' => $seeded['registry_id'],
              'publisher_id' => $seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => '0.1.0',
              'compat_max' => null,
              'installed_by' => null,
          ]);
      }

      private function seedApiToken(): int
      {
          $admin = $this->makeAdmin();

          return $this->db->insert(
              'INSERT INTO api_tokens (name, token_hash, scopes, created_by) VALUES (?, ?, ?, ?)',
              ['pkg-token-' . uniqid(), hash('sha256', uniqid('', true)), '["forum.read"]', $admin['id']],
          );
      }

      private function seedWebhook(): int
      {
          $admin = $this->makeAdmin();

          return $this->db->insert(
              'INSERT INTO webhooks (name, url, events, secret_ref, created_by) VALUES (?, ?, ?, ?, ?)',
              ['pkg-hook-' . uniqid(), 'https://example.test/hook', '["thread.created"]', 'svcsec_' . str_repeat('b', 12), $admin['id']],
          );
      }
  }
  ```
- [ ] **Step 2: Run — expect FAIL (class missing).** `vendor/bin/phpunit tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php` → expected error: `Class "App\Repository\InstalledPackageCredentialRepository" not found`.
- [ ] **Step 3: Implement the repository.** Create `src/Repository/InstalledPackageCredentialRepository.php`. Each `insert*` writes the one FK column its `kind` implies (the other stays NULL — the locked invariant), and `markRevoked` returns `rowCount()` so a second call reports 0:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Repository;

  use App\Core\Database;

  /** Package-owned credential links over `installed_package_credentials` (migration 0073). */
  final class InstalledPackageCredentialRepository
  {
      public function __construct(private Database $db)
      {
      }

      /** @return array<int,array<string,mixed>> active + revoked. */
      public function forInstall(int $installedId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM installed_package_credentials WHERE installed_package_id = ? ORDER BY id DESC',
              [$installedId],
          );
      }

      /** @return array<int,array<string,mixed>> */
      public function activeForInstall(int $installedId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM installed_package_credentials
               WHERE installed_package_id = ? AND revoked_at IS NULL ORDER BY id DESC',
              [$installedId],
          );
      }

      /** @return array<string,mixed>|null */
      public function find(int $id): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE id = ?', [$id]);
      }

      /** @return array<string,mixed>|null */
      public function findByApiToken(int $apiTokenId): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE api_token_id = ? ORDER BY id DESC LIMIT 1', [$apiTokenId]);
      }

      /** @return array<string,mixed>|null */
      public function findByWebhook(int $webhookId): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE webhook_id = ?', [$webhookId]);
      }

      public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int
      {
          return $this->db->insert(
              'INSERT INTO installed_package_credentials
                  (installed_package_id, kind, api_token_id, label, scopes_json, created_by, created_at)
               VALUES (?, \'api_token\', ?, ?, ?, ?, UTC_TIMESTAMP())',
              [$installedId, $apiTokenId, $label, $scopesJson, $createdBy],
          );
      }

      public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int
      {
          return $this->db->insert(
              'INSERT INTO installed_package_credentials
                  (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
               VALUES (?, \'webhook\', ?, ?, ?, ?, UTC_TIMESTAMP())',
              [$installedId, $webhookId, $label, $eventsJson, $createdBy],
          );
      }

      /** Idempotent: only the first call flips revoked_at. @return affected row count. */
      public function markRevoked(int $id): int
      {
          return $this->db->run(
              'UPDATE installed_package_credentials
               SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL',
              [$id],
          )->rowCount();
      }

      public function deleteFor(int $installedId): void
      {
          $this->db->run('DELETE FROM installed_package_credentials WHERE installed_package_id = ?', [$installedId]);
      }
  }
  ```
- [ ] **Step 4: Run — expect PASS.** `vendor/bin/phpunit tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php` → expected: `OK (3 tests)`.
- [ ] **Step 5: Commit.**
  ```bash
  git add src/Repository/InstalledPackageCredentialRepository.php \
          tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): add InstalledPackageCredentialRepository

  Single-table wrapper over installed_package_credentials linking
  package-owned api_tokens/webhooks to an install. insertApiToken/insertWebhook
  set exactly the FK their kind implies; markRevoked is idempotent (rowCount);
  active vs full listing + findBy{ApiToken,Webhook} lookups.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 19: `PublisherSigningKeyRepository`

**Files:**
- Create: `src/Repository/PublisherSigningKeyRepository.php`
- Test: `tests/Integration/Service/PublisherSigningKeyRepositoryTest.php`

**Interfaces:**
- **Consumes:** `App\Core\Database`; the already-landed `publisher_signing_keys` table (migration `0070`, inert until now); a `package_publishers` row via `PackagePublisherRepository::ensure`. Mirrors `RegistryTrustKeyRepository` verbatim, keyed on `publisher_id`.
- **Produces (locked):**
  ```php
  public function forPublisher(int $publisherId): array;                            // ORDER BY id DESC
  public function find(int $id): ?array;
  public function findKey(int $publisherId, string $keyId): ?array;
  public function pin(int $publisherId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int;  // hardcodes algorithm='ed25519', status='active'
  public function markRotated(int $id): void;                                       // status='rotated', valid_until=UTC_TIMESTAMP()
  public function revoke(int $id, string $reason): void;                            // status='revoked', revoked_at=UTC_TIMESTAMP(), revoked_reason=?
  ```

- [ ] **Step 1: Write the integration test (red).** Create `tests/Integration/Service/PublisherSigningKeyRepositoryTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Repository\PackagePublisherRepository;
  use App\Repository\PublisherSigningKeyRepository;
  use Tests\Support\TestCase;

  final class PublisherSigningKeyRepositoryTest extends TestCase
  {
      public function test_pin_defaults_ed25519_active_and_is_findable_by_key_id(): void
      {
          $repo = new PublisherSigningKeyRepository($this->db);
          $publisher = (new PackagePublisherRepository($this->db))->ensure('acme.tools', 'Acme Tools');

          $id = $repo->pin($publisher, 'key-1', str_repeat("\x01", 32), null, null);
          $row = $repo->find($id);

          self::assertSame('ed25519', $row['algorithm']);
          self::assertSame('active', $row['status']);
          self::assertSame($id, (int) $repo->findKey($publisher, 'key-1')['id']);
      }

      public function test_rotate_and_revoke_transition_status(): void
      {
          $repo = new PublisherSigningKeyRepository($this->db);
          $publisher = (new PackagePublisherRepository($this->db))->ensure('acme.tools', 'Acme Tools');
          $old = $repo->pin($publisher, 'key-1', str_repeat("\x01", 32), null, null);
          $new = $repo->pin($publisher, 'key-2', str_repeat("\x02", 32), null, null);

          $repo->markRotated($old);
          self::assertSame('rotated', $repo->find($old)['status']);
          self::assertNotNull($repo->find($old)['valid_until']);

          $repo->revoke($new, 'compromised');
          self::assertSame('revoked', $repo->find($new)['status']);
          self::assertSame('compromised', $repo->find($new)['revoked_reason']);

          // forPublisher lists newest first.
          self::assertSame([$new, $old], array_map(static fn (array $r): int => (int) $r['id'], $repo->forPublisher($publisher)));
      }
  }
  ```
- [ ] **Step 2: Run — expect FAIL (class missing).** `vendor/bin/phpunit tests/Integration/Service/PublisherSigningKeyRepositoryTest.php` → expected error: `Class "App\Repository\PublisherSigningKeyRepository" not found`.
- [ ] **Step 3: Implement the repository (mirror `RegistryTrustKeyRepository`).** Create `src/Repository/PublisherSigningKeyRepository.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Repository;

  use App\Core\Database;

  /**
   * Publisher signing-key custody (`publisher_signing_keys`, migration 0070;
   * inert until Inc 5). Mirrors RegistryTrustKeyRepository, keyed on publisher.
   */
  final class PublisherSigningKeyRepository
  {
      public function __construct(private Database $db)
      {
      }

      /** @return array<int,array<string,mixed>> newest first — the list handed to TrustChainVerifier. */
      public function forPublisher(int $publisherId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM publisher_signing_keys WHERE publisher_id = ? ORDER BY id DESC',
              [$publisherId],
          );
      }

      /** @return array<string,mixed>|null */
      public function find(int $id): ?array
      {
          return $this->db->fetch('SELECT * FROM publisher_signing_keys WHERE id = ?', [$id]);
      }

      /** @return array<string,mixed>|null */
      public function findKey(int $publisherId, string $keyId): ?array
      {
          return $this->db->fetch(
              'SELECT * FROM publisher_signing_keys WHERE publisher_id = ? AND key_id = ?',
              [$publisherId, $keyId],
          );
      }

      public function pin(int $publisherId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int
      {
          return $this->db->insert(
              'INSERT INTO publisher_signing_keys (publisher_id, key_id, algorithm, public_key, status, valid_from, valid_until)
               VALUES (?, ?, \'ed25519\', ?, \'active\', ?, ?)',
              [$publisherId, $keyId, $publicKey, $validFrom, $validUntil],
          );
      }

      public function markRotated(int $id): void
      {
          $this->db->run(
              "UPDATE publisher_signing_keys SET status = 'rotated', valid_until = UTC_TIMESTAMP() WHERE id = ?",
              [$id],
          );
      }

      public function revoke(int $id, string $reason): void
      {
          $this->db->run(
              "UPDATE publisher_signing_keys SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ?",
              [$reason, $id],
          );
      }
  }
  ```
- [ ] **Step 4: Run — expect PASS.** `vendor/bin/phpunit tests/Integration/Service/PublisherSigningKeyRepositoryTest.php` → expected: `OK (2 tests)`.
- [ ] **Step 5: Commit.**
  ```bash
  git add src/Repository/PublisherSigningKeyRepository.php \
          tests/Integration/Service/PublisherSigningKeyRepositoryTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): add PublisherSigningKeyRepository

  Activates the inert publisher_signing_keys table (migration 0070). Mirrors
  RegistryTrustKeyRepository keyed on publisher_id: forPublisher/find/findKey,
  pin (ed25519/active), markRotated, revoke. Consumed by PublisherTrustService.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 20: Added methods — `InstalledPackageRepository::setSettingsSummary` + `PackagePublisherRepository` accessors

**Files:**
- Modify: `src/Repository/InstalledPackageRepository.php`
- Modify: `src/Repository/PackagePublisherRepository.php`
- Test: `tests/Integration/Service/PackageRepositoryAdditionsTest.php`

**Interfaces:**
- **Consumes:** `installed_packages.settings_json`/`updated_at` (columns added by `0069`/`0049`); `package_publishers` (`0049`); `packages.publisher_id` (`0049`); `RegistryFixtures`/`PackagePublisherRepository::ensure`.
- **Produces (locked):**
  ```php
  // InstalledPackageRepository
  public function setSettingsSummary(int $id, ?string $json): void;                 // UPDATE installed_packages SET settings_json=?, updated_at=UTC_TIMESTAMP() WHERE id=?
  // PackagePublisherRepository
  public function find(int $id): ?array;
  public function all(): array;                                                     // ORDER BY display_name
  public function setStatus(int $id, string $status): void;                         // status ∈ active|suspended|revoked
  public function markVerified(int $id, ?int $actorId): void;                       // verified_at=UTC_TIMESTAMP()
  public function packagesFor(int $publisherId): array;                             // packages WHERE publisher_id=?
  ```

- [ ] **Step 1: Write the integration test (red).** Create `tests/Integration/Service/PackageRepositoryAdditionsTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Repository\InstalledPackageRepository;
  use App\Repository\PackagePublisherRepository;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class PackageRepositoryAdditionsTest extends TestCase
  {
      public function test_set_settings_summary_writes_and_clears_json(): void
      {
          $installs = new InstalledPackageRepository($this->db);
          $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
          $id = $installs->create([
              'package_id' => $seeded['package_id'],
              'release_id' => $seeded['release_id'],
              'digest' => $seeded['release_digest'],
              'source_registry_id' => $seeded['registry_id'],
              'publisher_id' => $seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => '0.1.0',
              'compat_max' => null,
              'installed_by' => null,
          ]);

          $installs->setSettingsSummary($id, '{"greeting":"hi"}');
          self::assertSame('{"greeting":"hi"}', $installs->find($id)['settings_json']);

          $installs->setSettingsSummary($id, null);
          self::assertNull($installs->find($id)['settings_json']);
      }

      public function test_publisher_accessors_read_status_and_owned_packages(): void
      {
          $publishers = new PackagePublisherRepository($this->db);
          $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
          $publisherId = $seeded['publisher_id'];

          self::assertSame($publisherId, (int) $publishers->find($publisherId)['id']);
          self::assertNotSame([], $publishers->all());

          $publishers->markVerified($publisherId, null);
          self::assertNotNull($publishers->find($publisherId)['verified_at']);

          $publishers->setStatus($publisherId, 'suspended');
          self::assertSame('suspended', $publishers->find($publisherId)['status']);

          $owned = $publishers->packagesFor($publisherId);
          self::assertContains($seeded['package_id'], array_map(static fn (array $r): int => (int) $r['id'], $owned));
      }
  }
  ```
- [ ] **Step 2: Run — expect FAIL (methods missing).** `vendor/bin/phpunit tests/Integration/Service/PackageRepositoryAdditionsTest.php` → expected error: `Call to undefined method App\Repository\InstalledPackageRepository::setSettingsSummary()`.
- [ ] **Step 3: Add `setSettingsSummary` to `InstalledPackageRepository`.** Insert this method (place it after `storeExport`, before `purgeable`):
  ```php
  public function setSettingsSummary(int $id, ?string $json): void
  {
      $this->db->run(
          'UPDATE installed_packages SET settings_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
          [$json, $id],
      );
  }
  ```
- [ ] **Step 4: Add the accessor methods to `PackagePublisherRepository`.** Insert after the existing `ensure()` method. `markVerified` accepts `$actorId` for a uniform service signature but the table has no `verified_by` column — the acting admin is recorded in `moderation_log` by `PublisherTrustService`, so the repo only stamps `verified_at`:
  ```php
  /** @return array<string,mixed>|null */
  public function find(int $id): ?array
  {
      return $this->db->fetch('SELECT * FROM package_publishers WHERE id = ?', [$id]);
  }

  /** @return array<int,array<string,mixed>> */
  public function all(): array
  {
      return $this->db->fetchAll('SELECT * FROM package_publishers ORDER BY display_name');
  }

  /** @param string $status active|suspended|revoked */
  public function setStatus(int $id, string $status): void
  {
      $this->db->run('UPDATE package_publishers SET status = ? WHERE id = ?', [$status, $id]);
  }

  /** Provenance of who verified lives in moderation_log (written by the caller); the row only stamps the time. */
  public function markVerified(int $id, ?int $actorId): void
  {
      $this->db->run('UPDATE package_publishers SET verified_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
  }

  /** @return array<int,array<string,mixed>> */
  public function packagesFor(int $publisherId): array
  {
      return $this->db->fetchAll(
          'SELECT * FROM packages WHERE publisher_id = ? ORDER BY package_uid',
          [$publisherId],
      );
  }
  ```
- [ ] **Step 5: Run — expect PASS.** `vendor/bin/phpunit tests/Integration/Service/PackageRepositoryAdditionsTest.php` → expected: `OK (2 tests)`.
- [ ] **Step 6: Commit.**
  ```bash
  git add src/Repository/InstalledPackageRepository.php \
          src/Repository/PackagePublisherRepository.php \
          tests/Integration/Service/PackageRepositoryAdditionsTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): add settings-summary + publisher accessor repo methods

  InstalledPackageRepository::setSettingsSummary writes/clears the denormalized
  installed_packages.settings_json pointer. PackagePublisherRepository gains
  find/all/setStatus/markVerified/packagesFor for the Inc 5 publisher trust +
  security-response console.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 21: `verify:upgrade` rehearsal + `SCHEMA.md` v1.33 (group exit deliverable)

**Files:**
- Modify: `SCHEMA.md`

**Interfaces:**
- **Consumes:** the landed migration `0073` + three repos (Tasks 16–20); `php bin/console verify:upgrade` (rehearses an additive upgrade on a scratch DB; refuses `APP_ENV=production`).
- **Produces:** additive-upgrade proof + `SCHEMA.md` bumped to `v1.33` documenting the two new tables and two enum widens. No code interface — this is the documentation + integrity checkpoint that closes the group.

- [ ] **Step 1: Rehearse the additive upgrade on a scratch DB.** `php bin/console verify:upgrade` → expected: it clones the pre-`0073` schema, applies `0073`, and reports the upgrade as additive with exit code 0 (e.g. `Additive upgrade verified` / no `DROP`/destructive diff on existing tables). If it flags a non-additive change, fix `0073` before proceeding.
- [ ] **Step 2: Run the full integration + unit schema/repo surface green.** `vendor/bin/phpunit tests/Integration/Core/AppPackageIntegrationSchemaTest.php tests/Integration/Service/InstalledPackageSettingsRepositoryTest.php tests/Integration/Service/InstalledPackageCredentialRepositoryTest.php tests/Integration/Service/PublisherSigningKeyRepositoryTest.php tests/Integration/Service/PackageRepositoryAdditionsTest.php tests/Unit/Core/MigrationLedgerTest.php` → expected: `OK` across all files.
- [ ] **Step 3: Bump the `SCHEMA.md` status header.** Read `SCHEMA.md`; change the line `**Status:** v1.32 · … · **Last updated:** 2026-07-03` to `**Status:** v1.33 · … · **Last updated:** 2026-07-03`.
- [ ] **Step 4: Add the two new tables to the §5B inventory.** After the `theme_state` row (`| 111 | \`theme_state\` | … |`), insert:
  ```
  | 112 | `installed_package_settings` | Ecosystem | 5 | PHASE_5_PLAN §8.2 / P5-04 (migration 0073; inert until Inc 5 integration runtime) |
  | 113 | `installed_package_credentials` | Ecosystem | 5 | PHASE_5_PLAN §8.2 / P5-04 (migration 0073; package-owned api_token/webhook links) |
  ```
- [ ] **Step 5: Add the two table shapes to §5B prose.** Near the other package tables (e.g. after `package_advisories`), add:
  ```
  - `installed_package_settings(id, installed_package_id, setting_key, value_json, secret_ref, is_secret, updated_by, updated_at)` — unique `(installed_package_id, setting_key)`, `idx_install_setting_secret`, FKs to `installed_packages` (CASCADE) + `users` (SET NULL); a row carries exactly one of `value_json` (non-secret) or `secret_ref` (an `svcsec_*` reference into `SecretVault`, `is_secret=1`).
  - `installed_package_credentials(id, installed_package_id, kind ENUM('api_token','webhook'), api_token_id, webhook_id, label, scopes_json, events_json, created_by, created_at, revoked_at)` — links a package-owned `api_tokens`/`webhooks` row to an install (FKs SET NULL); `kind` fixes which id is populated; `revoked_at` is the soft-revoke marker used by the delivery/auth guards.
  ```
- [ ] **Step 6: Add the §9 changelog row + note the enum widens.** At the top of the §9 changelog table (above the `v1.32` row):
  ```
  | v1.33 | 2026-07-03 | Phase 5 Increment 5 migration `0073_phase5_package_integrations`: added `installed_package_settings` (per-install non-secret `value_json` + `svcsec_*` secret references) and `installed_package_credentials` (package-owned api_token/webhook links), and widened `package_history.event` (+`settings_update`/`credential_mint`/`credential_revoke`) and `moderation_log.target_type` (+`publisher`). Additive-only; verified via `verify:upgrade`. Migration number follows `0072_phase5_theme_packages`. |
  ```
- [ ] **Step 7: Commit the group deliverable.**
  ```bash
  git add SCHEMA.md
  git commit -m "$(cat <<'EOF'
  docs(schema): bump SCHEMA.md to v1.33 for migration 0073

  Documents installed_package_settings + installed_package_credentials shapes,
  the package_history.event (+3) and moderation_log.target_type (+publisher)
  widens, and records the additive verify:upgrade rehearsal. Closes the Inc 5
  migration+repositories foundation (GA-DOD-08 groundwork).

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```
- [ ] **Step 8: Confirm the exit gate.** Re-run Step 2's command once more and `php bin/console migrate:status` → expected: all listed tests `OK`, `0073` applied with no pending migrations. Group deliverable complete: `migrate` green, schema-shape test green (tables/indexes/FKs/enum widens), and all three new repos' CRUD integration tests green — the data-layer foundation for the Inc 5 integration runtime (`GA-DOD-08`).


<!-- ===== group: package-settings-service — PackageSettingsService — settings_schema validation + secret storage ===== -->

### Task 21: Extend `ManifestValidator` to accept `secret` on string settings fields

**Files:**
- Modify: `src/Security/Packages/ManifestValidator.php` (add `'secret'` to `SETTING_FIELD_KEYS`; validate it inside `settingsSchema()`)
- Test: `tests/Unit/Security/Packages/ManifestValidatorTest.php` (add secret-flag + unknown-property cases)

**Interfaces:**
- Consumes: `ManifestValidator::validate(array $manifest, string $expectedUid, string $expectedVersion): PackageManifest` (existing); refuses via `PackagePolicyException` with `->code` (existing pattern); `PackageManifest::$settingsSchema` carries the raw field arrays (so `secret` survives into the typed snapshot).
- Produces: the `settings_schema` field grammar now permits `secret: bool`, allowed **only** when `type === 'string'`; every other unknown property still refuses `settings_schema`. No signature change — `SETTING_FIELD_KEYS` grows by one entry.

Steps:

- [ ] **Step 1: Add the failing validator cases.** Append these four methods to `tests/Unit/Security/Packages/ManifestValidatorTest.php` (they reuse the existing `manifest()`/`assertRefusal()` helpers):
  ```php
  public function test_secret_flag_allowed_on_string_field(): void
  {
      $manifest = $this->validator->validate($this->manifest([
          'settings_schema' => ['fields' => [
              ['key' => 'api_key', 'type' => 'string', 'label' => 'API key', 'required' => true, 'secret' => true],
          ]],
      ]), 'acme/midnight-theme', '1.0.0');

      self::assertTrue($manifest->settingsSchema['fields'][0]['secret']);
  }

  public function test_secret_flag_refused_on_non_string_field(): void
  {
      $this->assertRefusal('settings_schema', ['settings_schema' => ['fields' => [
          ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['a', 'b'], 'secret' => true],
      ]]]);
  }

  public function test_secret_flag_must_be_boolean(): void
  {
      $this->assertRefusal('settings_schema', ['settings_schema' => ['fields' => [
          ['key' => 'api_key', 'type' => 'string', 'label' => 'API key', 'secret' => 'yes'],
      ]]]);
  }

  public function test_unknown_settings_field_property_still_refused(): void
  {
      $this->assertRefusal('settings_schema', ['settings_schema' => ['fields' => [
          ['key' => 'api_key', 'type' => 'string', 'label' => 'API key', 'placeholder' => 'x'],
      ]]]);
  }
  ```

- [ ] **Step 2: Run the new cases and watch the secret ones fail.** Expected: `test_secret_flag_allowed_on_string_field` fails (currently refuses `secret` as an unknown property) and the non-string case does NOT yet refuse for the right reason.
  ```bash
  vendor/bin/phpunit --filter 'test_secret_flag|test_unknown_settings_field_property_still_refused' tests/Unit/Security/Packages/ManifestValidatorTest.php
  ```
  Expected output: `FAILURES!` with `test_secret_flag_allowed_on_string_field` reporting `expected refusal ... PackagePolicyException(settings_schema)` (the `secret` key trips the unknown-property guard).

- [ ] **Step 3: Widen the field-key whitelist.** In `src/Security/Packages/ManifestValidator.php` change the constant:
  ```php
  private const SETTING_FIELD_KEYS = ['key', 'type', 'label', 'required', 'options', 'secret'];
  ```

- [ ] **Step 4: Validate the `secret` flag inside `settingsSchema()`.** In the per-field loop, immediately after the existing `required`-is-boolean check and before the `options`/`select` handling, insert:
  ```php
  if (array_key_exists('secret', $field)) {
      if (!is_bool($field['secret'])) {
          $this->refuse('settings_schema', 'Settings field "secret" must be a boolean.');
      }
      if ($field['secret'] === true && $type !== 'string') {
          $this->refuse('settings_schema', 'Only string settings fields may be marked secret.');
      }
  }
  ```

- [ ] **Step 5: Re-run the validator suite to green.**
  ```bash
  vendor/bin/phpunit tests/Unit/Security/Packages/ManifestValidatorTest.php
  ```
  Expected output: `OK (` — all existing plus the four new tests pass.

- [ ] **Step 6: Commit the validator extension.**
  ```bash
  git add src/Security/Packages/ManifestValidator.php tests/Unit/Security/Packages/ManifestValidatorTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): allow secret flag on string settings fields in manifest validator

  Adds `secret` to the settings_schema field grammar (bool, string-only),
  keeping the unknown-property guard closed. Groundwork for write-only
  package secret settings (Inc 5 GA-DOD-08).

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 22: `PackageSettingsService` — schema validation + write-only secret storage

**Files:**
- Create: `src/Service/Packages/PackageSettingsService.php`
- Test: `tests/Unit/Service/Packages/PackageSettingsSchemaTest.php` (pure `validateInput` coverage)
- Test: `tests/Integration/Service/PackageSettingsServiceTest.php` (DB + vault: no-plaintext + vault-disabled fail-closed) — *new integration file for this group's owned service; the design's `PackageIntegrationServiceTest` belongs to a later group.*
- Modify: `src/Core/App.php` (container bind + `use` import)

**Interfaces:**
- Consumes (all existing, verbatim):
  - `SecretVault::store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor = null): string` → returns `svcsec_*`; `SecretVault::rotate(string $ref, string $newPlaintext, ?User $actor = null, ?int $graceSeconds = null): int`. `store`/`rotate` throw `SecretsDisabledException` when `service_secrets` is off — we pre-check the flag and raise `ValidationException` instead (fail-closed).
  - `ManifestValidator::validate(array, string $uid, string $version): PackageManifest` → `$manifest->settingsSchema['fields']`.
  - `InstalledPackageSettingsRepository::find(int $installedId, string $key): ?array`, `upsert(int $installedId, string $key, ?string $valueJson, ?string $secretRef, bool $isSecret, ?int $updatedBy): int`, `forInstall(int $installedId): array` (owned by the repositories task group; assumed landed).
  - `InstalledPackageRepository::find(int): ?array`, `setSettingsSummary(int $id, ?string $json): void`.
  - `PackageReleaseRepository::find(int): ?array` (row carries `manifest_json`, `version`), `PackageRepository::find(int): ?array` (row carries `package_uid`).
  - `PackageHistoryRepository::record(array): int` with `event => 'settings_update'` (enum value added by `0073`); `ModerationLogRepository::log(array): int` with `target_type => 'package'`.
  - `ReauthGate::requirePassword(User, string $currentPassword): void` (throws `ValidationException` on bad/missing password); `WriteGate::assertCanWrite(User): void`; `FeatureFlags::enabled('service_secrets'): bool`.
- Produces (LOCKED signatures copied verbatim):
  ```php
  public function describe(int $installedId): array;   // {fields:[{key,type,label,required,options?,secret}], values:<string,mixed>, has_secret:<string,bool>}
  public function save(User $admin, ?string $currentPassword, int $installedId, array $input): void;
  ```
  plus one additive **pure** seam used by `save()` and unit-tested directly (does not contradict the contract):
  ```php
  /** @return array{values:array<string,int|bool|string>, secrets:array<string,string>} @throws ValidationException */
  public static function validateInput(array $fields, array $input): array;
  ```

Steps:

- [ ] **Step 1: Write the pure schema unit test (fails: class missing).** Create `tests/Unit/Service/Packages/PackageSettingsSchemaTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Unit\Service\Packages;

  use App\Core\ValidationException;
  use App\Service\Packages\PackageSettingsService;
  use PHPUnit\Framework\TestCase;

  final class PackageSettingsSchemaTest extends TestCase
  {
      /** @return list<array<string,mixed>> */
      private function fields(): array
      {
          return [
              ['key' => 'api_key', 'type' => 'string',  'label' => 'API key', 'required' => true, 'secret' => true],
              ['key' => 'mode',    'type' => 'select',  'label' => 'Mode',    'options' => ['light', 'dark']],
              ['key' => 'retries', 'type' => 'integer', 'label' => 'Retries'],
              ['key' => 'notify',  'type' => 'boolean', 'label' => 'Notify'],
          ];
      }

      public function test_secret_value_goes_to_secrets_never_values(): void
      {
          $out = PackageSettingsService::validateInput($this->fields(), [
              'api_key' => 'sk-live-123', 'mode' => 'dark', 'retries' => '4', 'notify' => '1',
          ]);

          self::assertSame(['api_key' => 'sk-live-123'], $out['secrets']);
          self::assertArrayNotHasKey('api_key', $out['values']);
          self::assertSame('dark', $out['values']['mode']);
          self::assertSame(4, $out['values']['retries']);
          self::assertTrue($out['values']['notify']);
      }

      public function test_unknown_key_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          PackageSettingsService::validateInput($this->fields(), ['nope' => 'x']);
      }

      public function test_bad_select_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          PackageSettingsService::validateInput($this->fields(), ['mode' => 'neon']);
      }

      public function test_non_numeric_integer_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          PackageSettingsService::validateInput($this->fields(), ['retries' => 'lots']);
      }

      public function test_oversize_string_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          PackageSettingsService::validateInput(
              [['key' => 'label', 'type' => 'string', 'label' => 'Label']],
              ['label' => str_repeat('x', 5000)],
          );
      }

      public function test_missing_required_non_secret_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          PackageSettingsService::validateInput(
              [['key' => 'endpoint', 'type' => 'string', 'label' => 'Endpoint', 'required' => true]],
              [],
          );
      }

      public function test_validation_error_old_never_echoes_secret_plaintext(): void
      {
          try {
              PackageSettingsService::validateInput($this->fields(), ['api_key' => 'sk-secret', 'mode' => 'neon']);
              self::fail('expected ValidationException');
          } catch (ValidationException $e) {
              self::assertStringNotContainsString('sk-secret', json_encode($e->old));
              self::assertSame('neon', $e->old['settings']['mode'] ?? null);
          }
      }
  }
  ```

- [ ] **Step 2: Run the unit test (expected FAIL — class not found).**
  ```bash
  vendor/bin/phpunit tests/Unit/Service/Packages/PackageSettingsSchemaTest.php
  ```
  Expected output: `Error: Class "App\Service\Packages\PackageSettingsService" not found`.

- [ ] **Step 3: Create the service with the constructor + the pure `validateInput`/`safeOld` seam only.** Create `src/Service/Packages/PackageSettingsService.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Service\Packages;

  use App\Core\Config;
  use App\Core\Database;
  use App\Core\FeatureFlags;
  use App\Core\ValidationException;
  use App\Domain\User;
  use App\Repository\InstalledPackageRepository;
  use App\Repository\InstalledPackageSettingsRepository;
  use App\Repository\ModerationLogRepository;
  use App\Repository\PackageHistoryRepository;
  use App\Repository\PackageReleaseRepository;
  use App\Repository\PackageRepository;
  use App\Security\Packages\ManifestValidator;
  use App\Security\ReauthGate;
  use App\Security\WriteGate;
  use App\Service\SecretVault;

  final class PackageSettingsService
  {
      private const MAX_SETTING_BYTES = 4096;

      public function __construct(
          private Database $db,
          private PackageRepository $packages,
          private PackageReleaseRepository $releases,
          private InstalledPackageRepository $installs,
          private InstalledPackageSettingsRepository $settings,
          private SecretVault $vault,
          private ManifestValidator $manifests,
          private PackageHistoryRepository $history,
          private ModerationLogRepository $audit,
          private ReauthGate $reauth,
          private WriteGate $writeGate,
          private FeatureFlags $flags,
          private Config $config,
      ) {
      }

      /**
       * Pure: validate submitted $input against manifest settings_schema $fields.
       *
       * @param list<array<string,mixed>> $fields  manifest settings_schema['fields']
       * @param array<string,mixed> $input         raw submitted values (form strings)
       * @return array{values:array<string,int|bool|string>, secrets:array<string,string>}
       * @throws ValidationException on unknown key / missing required / bad select / oversize / type mismatch
       */
      public static function validateInput(array $fields, array $input): array
      {
          $known = [];
          foreach ($fields as $field) {
              $known[(string) $field['key']] = $field;
          }

          $errors = [];
          foreach (array_keys($input) as $key) {
              if (!isset($known[(string) $key])) {
                  $errors[(string) $key] = 'Unknown setting: ' . $key . '.';
              }
          }

          $values = [];
          $secrets = [];
          foreach ($known as $key => $field) {
              $key = (string) $key;
              $type = (string) $field['type'];
              $label = (string) $field['label'];
              $required = ($field['required'] ?? false) === true;
              $raw = $input[$key] ?? null;

              if (($field['secret'] ?? false) === true) {
                  $val = is_string($raw) ? $raw : '';
                  if ($val === '') {
                      continue; // empty = leave unchanged; required-secret enforced in save()
                  }
                  if (strlen($val) > self::MAX_SETTING_BYTES) {
                      $errors[$key] = $label . ' is too long.';
                  } else {
                      $secrets[$key] = $val;
                  }
                  continue;
              }

              if ($type === 'boolean') {
                  $values[$key] = in_array($raw, ['1', 'on', 'true', true, 1], true);
                  continue;
              }

              if ($raw === null || $raw === '') {
                  if ($required) {
                      $errors[$key] = $label . ' is required.';
                  }
                  continue;
              }
              if (!is_string($raw) && !is_int($raw)) {
                  $errors[$key] = $label . ' is invalid.';
                  continue;
              }
              $str = (string) $raw;
              if (strlen($str) > self::MAX_SETTING_BYTES) {
                  $errors[$key] = $label . ' is too long.';
                  continue;
              }

              if ($type === 'integer') {
                  if (preg_match('/\A-?\d{1,18}\z/', $str) !== 1) {
                      $errors[$key] = $label . ' must be a whole number.';
                      continue;
                  }
                  $values[$key] = (int) $str;
              } elseif ($type === 'select') {
                  $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                  if (!in_array($str, $options, true)) {
                      $errors[$key] = $label . ' is not a valid choice.';
                      continue;
                  }
                  $values[$key] = $str;
              } else {
                  $values[$key] = $str;
              }
          }

          if ($errors !== []) {
              throw new ValidationException($errors, self::safeOld($fields, $input));
          }

          return ['values' => $values, 'secrets' => $secrets];
      }

      /**
       * Repopulation payload for the form: non-secret values only, never secret plaintext.
       *
       * @param list<array<string,mixed>> $fields
       * @param array<string,mixed> $input
       * @return array{settings:array<string,string>}
       */
      private static function safeOld(array $fields, array $input): array
      {
          $secretKeys = [];
          foreach ($fields as $field) {
              if (($field['secret'] ?? false) === true) {
                  $secretKeys[(string) $field['key']] = true;
              }
          }
          $old = [];
          foreach ($input as $key => $value) {
              if (isset($secretKeys[(string) $key]) || !is_scalar($value)) {
                  continue;
              }
              $old[(string) $key] = (string) $value;
          }

          return ['settings' => $old];
      }
  }
  ```

- [ ] **Step 4: Run the unit test to green.**
  ```bash
  vendor/bin/phpunit tests/Unit/Service/Packages/PackageSettingsSchemaTest.php
  ```
  Expected output: `OK (7 tests, ...)`.

- [ ] **Step 5: Write the integration test (fails: `describe`/`save` undefined).** Create `tests/Integration/Service/PackageSettingsServiceTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Core\FeatureFlags;
  use App\Core\ValidationException;
  use App\Domain\User;
  use App\Repository\InstalledPackageRepository;
  use App\Repository\InstalledPackageSettingsRepository;
  use App\Repository\ModerationLogRepository;
  use App\Repository\PackageHistoryRepository;
  use App\Repository\PackageReleaseRepository;
  use App\Repository\PackageRepository;
  use App\Repository\ServiceSecretRepository;
  use App\Repository\SettingRepository;
  use App\Repository\UserRepository;
  use App\Security\Packages\ManifestValidator;
  use App\Security\PasswordHasher;
  use App\Security\ReauthGate;
  use App\Security\SecretBox;
  use App\Security\WriteGate;
  use App\Service\Packages\PackageSettingsService;
  use App\Service\SecretVault;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class PackageSettingsServiceTest extends TestCase
  {
      private User $admin;
      /** @var array<string,mixed> */
      private array $seeded;
      private int $installedId;

      protected function setUp(): void
      {
          parent::setUp();

          $root = SigningHarness::generate();
          $this->seeded = RegistryFixtures::seed($this->db, $root, null, [
              'type' => 'remote_app',
              'package_uid' => 'acme/sync-app',
              'name' => 'Sync App',
              'trust_class' => 'reviewed_declarative',
              'release' => ['manifest' => ['settings_schema' => ['fields' => [
                  ['key' => 'api_key', 'type' => 'string',  'label' => 'API key', 'required' => true, 'secret' => true],
                  ['key' => 'mode',    'type' => 'select',  'label' => 'Mode',    'options' => ['light', 'dark']],
                  ['key' => 'notify',  'type' => 'boolean', 'label' => 'Notify'],
              ]]]],
          ]);

          $adminRow = $this->makeAdmin(['password' => 'password123']);
          $admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
          self::assertNotNull($admin);
          $this->admin = $admin;

          $this->installedId = (new InstalledPackageRepository($this->db))->create([
              'package_id' => (int) $this->seeded['package_id'],
              'release_id' => (int) $this->seeded['release_id'],
              'digest' => (string) $this->seeded['release_digest'],
              'source_registry_id' => (int) $this->seeded['registry_id'],
              'publisher_id' => (int) $this->seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => null,
              'compat_max' => null,
              'installed_by' => $this->admin->id(),
          ]);
      }

      private function service(bool $secretsEnabled = true): PackageSettingsService
      {
          (new SettingRepository($this->db))->set('features', ['service_secrets' => $secretsEnabled]);
          $flags = new FeatureFlags(new SettingRepository($this->db));

          return new PackageSettingsService(
              $this->db,
              new PackageRepository($this->db),
              new PackageReleaseRepository($this->db),
              new InstalledPackageRepository($this->db),
              new InstalledPackageSettingsRepository($this->db),
              new SecretVault(
                  $this->db,
                  new ServiceSecretRepository($this->db),
                  new SecretBox(str_repeat('a', 64)),
                  new ModerationLogRepository($this->db),
                  $flags,
                  $this->config,
              ),
              new ManifestValidator(),
              new PackageHistoryRepository($this->db),
              new ModerationLogRepository($this->db),
              new ReauthGate(new PasswordHasher()),
              new WriteGate(),
              $flags,
              $this->config,
          );
      }

      private function revealVault(): SecretVault
      {
          (new SettingRepository($this->db))->set('features', ['service_secrets' => true]);
          return new SecretVault(
              $this->db,
              new ServiceSecretRepository($this->db),
              new SecretBox(str_repeat('a', 64)),
              new ModerationLogRepository($this->db),
              new FeatureFlags(new SettingRepository($this->db)),
              $this->config,
          );
      }

      public function test_secret_setting_persists_only_a_ref_never_plaintext(): void
      {
          $this->service()->save($this->admin, 'password123', $this->installedId, [
              'api_key' => 'sk-live-123', 'mode' => 'dark', 'notify' => '1',
          ]);

          $row = (new InstalledPackageSettingsRepository($this->db))->find($this->installedId, 'api_key');
          self::assertNotNull($row);
          self::assertNull($row['value_json']);
          self::assertSame(1, (int) $row['is_secret']);
          self::assertStringStartsWith('svcsec_', (string) $row['secret_ref']);
          self::assertStringNotContainsString('sk-live-123', json_encode($row));

          $summary = (string) (new InstalledPackageRepository($this->db))->find($this->installedId)['settings_json'];
          self::assertStringNotContainsString('sk-live-123', $summary);

          self::assertSame('sk-live-123', $this->revealVault()->reveal((string) $row['secret_ref']));

          $describe = $this->service()->describe($this->installedId);
          self::assertTrue($describe['has_secret']['api_key']);
          self::assertSame('dark', $describe['values']['mode']);
          self::assertTrue($describe['values']['notify']);
          self::assertStringNotContainsString('sk-live-123', json_encode($describe));
      }

      public function test_secret_write_fails_closed_when_vault_disabled(): void
      {
          try {
              $this->service(false)->save($this->admin, 'password123', $this->installedId, ['api_key' => 'sk-x']);
              self::fail('expected ValidationException');
          } catch (ValidationException $e) {
              self::assertStringNotContainsString('sk-x', json_encode($e->old));
          }

          self::assertNull((new InstalledPackageSettingsRepository($this->db))->find($this->installedId, 'api_key'));
      }

      public function test_secret_write_requires_reauth(): void
      {
          $this->expectException(ValidationException::class);
          $this->service()->save($this->admin, 'wrong-password', $this->installedId, ['api_key' => 'sk-y']);
      }

      public function test_required_secret_with_no_existing_value_is_rejected(): void
      {
          $this->expectException(ValidationException::class);
          $this->service()->save($this->admin, '', $this->installedId, ['mode' => 'light']);
      }
  }
  ```

- [ ] **Step 6: Run the integration test (expected FAIL — undefined method).**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageSettingsServiceTest.php
  ```
  Expected output: `Error: Call to undefined method App\Service\Packages\PackageSettingsService::save()`.

- [ ] **Step 7: Add the `fieldsFor()` manifest-resolution helper.** Insert into `PackageSettingsService` (private; reused by `describe`/`save`):
  ```php
  /** @param array<string,mixed> $install @return list<array<string,mixed>> */
  private function fieldsFor(array $install): array
  {
      $release = $this->releases->find((int) $install['release_id']);
      $package = $this->packages->find((int) $install['package_id']);
      if ($release === null || $package === null) {
          return [];
      }
      $manifest = $this->manifests->validate(
          (array) json_decode((string) $release['manifest_json'], true, 512, JSON_THROW_ON_ERROR),
          (string) $package['package_uid'],
          (string) $release['version'],
      );

      return $manifest->settingsSchema['fields'] ?? [];
  }
  ```

- [ ] **Step 8: Implement `describe()` (read model, never plaintext).** Add:
  ```php
  public function describe(int $installedId): array
  {
      $install = $this->installs->find($installedId);
      if ($install === null) {
          return ['fields' => [], 'values' => [], 'has_secret' => []];
      }
      $fields = $this->fieldsFor($install);

      $byKey = [];
      foreach ($this->settings->forInstall($installedId) as $row) {
          $byKey[(string) $row['setting_key']] = $row;
      }

      $out = [];
      $values = [];
      $hasSecret = [];
      foreach ($fields as $field) {
          $key = (string) $field['key'];
          $secret = ($field['secret'] ?? false) === true;
          $entry = [
              'key' => $key,
              'type' => (string) $field['type'],
              'label' => (string) $field['label'],
              'required' => ($field['required'] ?? false) === true,
              'secret' => $secret,
          ];
          if (isset($field['options'])) {
              $entry['options'] = $field['options'];
          }
          $out[] = $entry;

          $row = $byKey[$key] ?? null;
          if ($secret) {
              $hasSecret[$key] = $row !== null && ($row['secret_ref'] ?? null) !== null;
          } elseif ($row !== null && ($row['value_json'] ?? null) !== null) {
              $values[$key] = json_decode((string) $row['value_json'], true);
          }
      }

      return ['fields' => $out, 'values' => $values, 'has_secret' => $hasSecret];
  }
  ```

- [ ] **Step 9: Implement `save()` (validate → fail-closed pre-checks → one transaction).** Add:
  ```php
  public function save(User $admin, ?string $currentPassword, int $installedId, array $input): void
  {
      $this->writeGate->assertCanWrite($admin);

      $install = $this->installs->find($installedId);
      if ($install === null) {
          throw new ValidationException(['settings' => 'Unknown install.']);
      }
      $fields = $this->fieldsFor($install);
      if ($fields === []) {
          throw new ValidationException(['settings' => 'This package has no configurable settings.']);
      }

      $parsed = self::validateInput($fields, $input);           // throws with safe ->old
      $writingSecret = $parsed['secrets'] !== [];

      // Fail closed BEFORE opening any transaction.
      if ($writingSecret && !$this->flags->enabled('service_secrets')) {
          throw new ValidationException(
              ['settings' => 'Secret settings require the service-secret store to be enabled.'],
              self::safeOld($fields, $input),
          );
      }
      if ($writingSecret) {
          $this->reauth->requirePassword($admin, (string) $currentPassword); // ValidationException on bad/missing
      }
      foreach ($fields as $field) {
          if (($field['secret'] ?? false) === true && ($field['required'] ?? false) === true) {
              $key = (string) $field['key'];
              if (!isset($parsed['secrets'][$key])) {
                  $existing = $this->settings->find($installedId, $key);
                  if ($existing === null || ($existing['secret_ref'] ?? null) === null) {
                      throw new ValidationException(
                          ['settings' => $field['label'] . ' is required.'],
                          self::safeOld($fields, $input),
                      );
                  }
              }
          }
      }

      $package = $this->packages->find((int) $install['package_id']);
      $uid = (string) ($package['package_uid'] ?? 'package');

      $this->db->transaction(function () use ($admin, $installedId, $install, $uid, $parsed): void {
          foreach ($parsed['values'] as $key => $val) {
              $this->settings->upsert($installedId, (string) $key, json_encode($val), null, false, $admin->id());
          }
          foreach ($parsed['secrets'] as $key => $plaintext) {
              $existing = $this->settings->find($installedId, (string) $key);
              $ref = $existing['secret_ref'] ?? null;
              if ($ref !== null) {
                  $this->vault->rotate((string) $ref, $plaintext, $admin);
              } else {
                  $ref = $this->vault->store('package_setting', $installedId, $uid . ':' . $key, $plaintext, $admin);
              }
              $this->settings->upsert($installedId, (string) $key, null, (string) $ref, true, $admin->id());
          }

          $summary = ['values' => [], 'secret_keys' => []];
          foreach ($this->settings->forInstall($installedId) as $row) {
              if ((int) $row['is_secret'] === 1) {
                  $summary['secret_keys'][] = (string) $row['setting_key'];
              } elseif (($row['value_json'] ?? null) !== null) {
                  $summary['values'][(string) $row['setting_key']] = json_decode((string) $row['value_json'], true);
              }
          }
          $this->installs->setSettingsSummary($installedId, json_encode($summary));

          $this->history->record([
              'package_id' => (int) $install['package_id'],
              'installed_package_id' => $installedId,
              'event' => 'settings_update',
              'actor_id' => $admin->id(),
              'detail' => json_encode([
                  'keys' => array_keys($parsed['values']),
                  'secret_keys' => array_keys($parsed['secrets']),
              ]),
          ]);
          $this->audit->log([
              'actor_id' => $admin->id(),
              'action' => 'package_settings_update',
              'target_type' => 'package',
              'target_id' => (int) $install['package_id'],
              'after' => [
                  'installed_package_id' => $installedId,
                  'keys' => array_keys($parsed['values']),
                  'secret_keys' => array_keys($parsed['secrets']),
              ],
          ]);
      });
  }
  ```

- [ ] **Step 10: Run the integration test to green.**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageSettingsServiceTest.php
  ```
  Expected output: `OK (4 tests, ...)` — proving the secret persists only as a `svcsec_*` ref (no plaintext in the row, summary, or `describe()`), the vault reveals the original, and vault-disabled/bad-password/missing-required all fail closed before any write.

- [ ] **Step 11: Wire the container bind.** In `src/Core/App.php` add the import alongside the other `App\Service\Packages\*` uses:
  ```php
  use App\Service\Packages\PackageSettingsService;
  ```
  Then, in `buildContainer()` immediately after the `ManifestValidator::class` bind (~line 1266), add:
  ```php
  $c->bind(PackageSettingsService::class, fn (Container $c) => new PackageSettingsService(
      $c->get(Database::class),
      $c->get(PackageRepository::class),
      $c->get(PackageReleaseRepository::class),
      $c->get(InstalledPackageRepository::class),
      $c->get(InstalledPackageSettingsRepository::class),
      $c->get(SecretVault::class),
      $c->get(ManifestValidator::class),
      $c->get(PackageHistoryRepository::class),
      $c->get(ModerationLogRepository::class),
      $c->get(ReauthGate::class),
      $c->get(WriteGate::class),
      $c->get(FeatureFlags::class),
      $config,
  ));
  ```
  *(No route/controller is added in this group, so there is no new `/admin/...` surface to assert flag-dark 404s against yet — that coverage lands with `AdminPackageIntegrationController` in the controller task group. The service is only reachable through those `package_registry`-gated controllers.)*

- [ ] **Step 12: Lint the container wiring in isolation.** Confirm the bind resolves (proves every `$c->get(...)` collaborator exists, including the assumed `InstalledPackageSettingsRepository`):
  ```bash
  php -r 'require "vendor/autoload.php"; echo class_exists(App\Service\Packages\PackageSettingsService::class) ? "ok\n" : "missing\n";'
  ```
  Expected output: `ok`.

- [ ] **Step 13: Run the full unit suite + both new tests together.**
  ```bash
  vendor/bin/phpunit --testsuite unit && vendor/bin/phpunit tests/Integration/Service/PackageSettingsServiceTest.php tests/Unit/Security/Packages/ManifestValidatorTest.php
  ```
  Expected output: `OK` for both invocations (no strict-mode warnings/risky tests).

- [ ] **Step 14: Run `composer test` to confirm nothing else regressed, then commit.**
  ```bash
  composer test
  git add src/Service/Packages/PackageSettingsService.php \
          src/Core/App.php \
          tests/Unit/Service/Packages/PackageSettingsSchemaTest.php \
          tests/Integration/Service/PackageSettingsServiceTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): PackageSettingsService validates settings_schema and stores secrets write-only

  Validates submitted install settings against the active release manifest
  (string/boolean/integer/select + secret:true), persists non-secret values as
  value_json and secret fields through SecretVault as svcsec_* refs only, in one
  transaction (settings rows + setSettingsSummary + package_history settings_update
  + moderation_log). Fails closed with ValidationException (safe old input, never
  echoing secret plaintext) on unknown key / missing required / bad select /
  oversize / type mismatch / vault disabled, and reauth-gates any secret write.
  Advances GA-DOD-08.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```


<!-- ===== group: api-token-provisioning — Install-scoped API-token provisioning ===== -->


---

> **Group #6 ownership & prerequisites.** This group owns the **API-token half** of the integration runtime. It **creates** `src/Service/Packages/PackageIntegrationService.php` (constructor + `isExecutionDisabled()` + the `api_token` paths of `provisionCredentials`/`rotateCredential`/`revokeCredential`); the **webhook-event-delivery group (#7)** and the **integration-runtime-core group** later *modify* this same file to add `overview()`, `onInstallIneligible()`, `suspend/resumeDelivery()`, and the webhook-minting branches. It **consumes** the Task 18 `InstalledPackageCredentialRepository` full surface; if local drift left only the api-token subset, repair by adding the missing methods while preserving all existing api-token/webhook methods. **Assumed already landed by earlier groups:** migration `0073` (tables `installed_package_credentials` + `installed_package_settings`; `package_history.event` widened with `credential_mint`/`credential_revoke`/`settings_update`), `InstalledPackageSettingsRepository`, and the full `InstalledPackageCredentialRepository`. **Not in this group:** `App.php` container binds + route wiring (App-wiring/controllers groups) and the route-level `/admin/packages/{id}/integration/*` `404-while-dark` assertions in `AppFeatureFlagTest` (controllers group). This group proves flag-dark at the **service** layer (`service_secrets` off ⇒ fail-closed `ValidationException`). Every test constructs the service directly (no container).

### Task 26: `InstalledPackageCredentialRepository` — api_token linkage regression

**Files:**
- Modify: `src/Repository/InstalledPackageCredentialRepository.php` (verify/preserve the full Task 18 api_token + webhook surface; do not replace the file with a subset)
- Test: `tests/Integration/Repository/InstalledPackageCredentialRepositoryTest.php`

**Interfaces:**
- Consumes: `Database::insert(string,array):int`, `Database::run(string,array):\PDOStatement` (→`->rowCount()`), `Database::fetch/fetchAll`, `InstalledPackageRepository::create(array):int`, `ApiTokenRepository::insert(string $name,string $hash,string $scopesJson,int $createdBy,?string $expiresAt):int`, `Tests\Support\Phase5\RegistryFixtures::seed(Database,SigningHarness,?string,array):array`.
- Produces (LOCKED full surface after this task; webhook methods may already be present from Task 18 and must remain present):
  ```php
  public function forInstall(int $installedId): array;
  public function activeForInstall(int $installedId): array;
  public function find(int $id): ?array;
  public function findByApiToken(int $apiTokenId): ?array;
  public function findByWebhook(int $webhookId): ?array;
  public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int;
  public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int;
  public function markRevoked(int $id): int;      // idempotent; rowCount
  public function deleteFor(int $installedId): void;
  ```

**Steps:**

- [ ] **Step 1: Write the failing repo test.** Create `tests/Integration/Repository/InstalledPackageCredentialRepositoryTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Repository;

  use App\Repository\ApiTokenRepository;
  use App\Repository\InstalledPackageCredentialRepository;
  use App\Repository\InstalledPackageRepository;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class InstalledPackageCredentialRepositoryTest extends TestCase
  {
      public function test_api_token_link_round_trips_and_revoke_is_idempotent(): void
      {
          $seeded = RegistryFixtures::seed($this->db, SigningHarness::generate(), null, ['type' => 'remote_app']);
          $adminId = (int) $this->makeAdmin()['id'];

          $installedId = (new InstalledPackageRepository($this->db))->create([
              'package_id' => $seeded['package_id'],
              'release_id' => $seeded['release_id'],
              'digest' => $seeded['release_digest'],
              'source_registry_id' => $seeded['registry_id'],
              'publisher_id' => $seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => null,
              'compat_max' => null,
              'installed_by' => $adminId,
          ]);
          $tokenId = (new ApiTokenRepository($this->db))
              ->insert('seed', hash('sha256', 'seed'), '["read:boards"]', $adminId, null);

          $repo = new InstalledPackageCredentialRepository($this->db);
          $linkId = $repo->insertApiToken($installedId, $tokenId, 'pkg:acme/remote-app#' . $installedId, '["read:boards"]', $adminId);

          $row = $repo->find($linkId);
          self::assertNotNull($row);
          self::assertSame('api_token', (string) $row['kind']);
          self::assertSame($tokenId, (int) $row['api_token_id']);
          self::assertNull($row['webhook_id']);
          self::assertSame($linkId, (int) $repo->findByApiToken($tokenId)['id']);
          self::assertCount(1, $repo->activeForInstall($installedId));

          self::assertSame(1, $repo->markRevoked($linkId), 'first revoke flips the row');
          self::assertSame(0, $repo->markRevoked($linkId), 'second revoke is a no-op');
          self::assertCount(0, $repo->activeForInstall($installedId));
          self::assertCount(1, $repo->forInstall($installedId), 'revoked links stay visible');
      }
  }
  ```

- [ ] **Step 2: Run it.**
  ```bash
  vendor/bin/phpunit --filter test_api_token_link_round_trips_and_revoke_is_idempotent
  ```
  Expected: `OK` if Task 18 already landed. If it fails because a local branch has no repository or only a partial subset, continue to Step 3 and repair the class to the full locked surface; do not delete webhook methods.

- [ ] **Step 3: Repair/verify the repository without dropping methods.** If Step 2 exposed local drift, update `src/Repository/InstalledPackageCredentialRepository.php` so the class still contains every method in the locked surface. The implementation must include the webhook methods shown below as well as the api-token methods:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Repository;

  use App\Core\Database;

  /** Links installed packages to their package-owned api_tokens/webhooks (0073). */
  final class InstalledPackageCredentialRepository
  {
      public function __construct(private Database $db)
      {
      }

      /** @return array<int,array<string,mixed>> active + revoked, newest first */
      public function forInstall(int $installedId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM installed_package_credentials WHERE installed_package_id = ? ORDER BY id DESC',
              [$installedId],
          );
      }

      /** @return array<int,array<string,mixed>> */
      public function activeForInstall(int $installedId): array
      {
          return $this->db->fetchAll(
              'SELECT * FROM installed_package_credentials
               WHERE installed_package_id = ? AND revoked_at IS NULL ORDER BY id DESC',
              [$installedId],
          );
      }

      /** @return array<string,mixed>|null */
      public function find(int $id): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE id = ?', [$id]);
      }

      /** @return array<string,mixed>|null */
      public function findByApiToken(int $apiTokenId): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE api_token_id = ? ORDER BY id DESC LIMIT 1', [$apiTokenId]);
      }

      /** @return array<string,mixed>|null */
      public function findByWebhook(int $webhookId): ?array
      {
          return $this->db->fetch('SELECT * FROM installed_package_credentials WHERE webhook_id = ? ORDER BY id DESC LIMIT 1', [$webhookId]);
      }

      public function insertApiToken(int $installedId, int $apiTokenId, string $label, string $scopesJson, ?int $createdBy): int
      {
          return $this->db->insert(
              'INSERT INTO installed_package_credentials
                  (installed_package_id, kind, api_token_id, webhook_id, label, scopes_json, events_json, created_by, created_at)
               VALUES (?, \'api_token\', ?, NULL, ?, ?, NULL, ?, UTC_TIMESTAMP())',
              [$installedId, $apiTokenId, $label, $scopesJson, $createdBy],
          );
      }

      public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int
      {
          return $this->db->insert(
              'INSERT INTO installed_package_credentials
                  (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
               VALUES (?, \'webhook\', ?, ?, ?, ?, UTC_TIMESTAMP())',
              [$installedId, $webhookId, $label, $eventsJson, $createdBy],
          );
      }

      /** @return int rows affected — 0 when unknown or already revoked (idempotent) */
      public function markRevoked(int $id): int
      {
          return $this->db->run(
              'UPDATE installed_package_credentials SET revoked_at = UTC_TIMESTAMP()
               WHERE id = ? AND revoked_at IS NULL',
              [$id],
          )->rowCount();
      }

      public function deleteFor(int $installedId): void
      {
          $this->db->run('DELETE FROM installed_package_credentials WHERE installed_package_id = ?', [$installedId]);
      }
  }
  ```
  Do not remove `insertWebhook()`/`findByWebhook()` when editing the api-token path. The later webhook-event-delivery group appends additional tests and uses these methods; it does not own a second replacement implementation.

- [ ] **Step 4: Run it — expect GREEN.**
  ```bash
  vendor/bin/phpunit --filter test_api_token_link_round_trips_and_revoke_is_idempotent
  ```
  Expected: `OK (1 test, N assertions)`.

- [ ] **Step 5: Commit.**
  ```bash
  git add src/Repository/InstalledPackageCredentialRepository.php \
          tests/Integration/Repository/InstalledPackageCredentialRepositoryTest.php
  git commit -m "test(packages): verify InstalledPackageCredentialRepository api_token linkage"
  ```

### Task 27: `PackageIntegrationService::provisionCredentials` (api_token) + scope gating (TM-SC-08)

**Files:**
- Create: `src/Service/Packages/PackageIntegrationService.php`
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php`

**Interfaces:**
- Consumes: `ApiTokenService::mint(User,string $currentPassword,string $name,array $scopes,?int $expiresInDays):array{token,id}`, `ApiScopes::isValid(string):bool`, `InstalledPackagePermissionRepository::{forInstall(int):array, ungrantedCount(int):int}`, `PackageRepository::find(int):?array`, `PackageReleaseRepository::find(int):?array`, `PackageHistoryRepository::record(array):int`, `PackageTransparencyLogRepository::record(array):int` (`event='install'`, `source='local'`), `ModerationLogRepository::log(array):void`, `ReauthGate::requirePassword(User,string):void`, `WriteGate::assertCanWrite(User):void`, `SettingRepository::getString(string,string):string`, `Config::get(string,mixed):mixed`, `FeatureFlags::enabled(string):bool`.
- Produces (LOCKED):
  ```php
  public function provisionCredentials(User $admin, string $currentPassword, int $installedId): array;
      // @return array{api_token:?string, webhook_secret:?string, credentials:list<array>}  // plaintext once
  public function isExecutionDisabled(): bool;
  ```

**Steps:**

- [ ] **Step 1: Write the failing provision test file.** Create `tests/Integration/Service/PackageIntegrationServiceTest.php` with the builder + happy-path + denial + scope-gating cases:
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Service;

  use App\Core\FeatureFlags;
  use App\Core\ValidationException;
  use App\Domain\User;
  use App\Repository\ApiTokenRepository;
  use App\Repository\InstalledPackageCredentialRepository;
  use App\Repository\InstalledPackagePermissionRepository;
  use App\Repository\InstalledPackageRepository;
  use App\Repository\InstalledPackageSettingsRepository;
  use App\Repository\ModerationLogRepository;
  use App\Repository\PackageHistoryRepository;
  use App\Repository\PackageReleaseRepository;
  use App\Repository\PackageRepository;
  use App\Repository\PackageTransparencyLogRepository;
  use App\Repository\ServiceSecretRepository;
  use App\Repository\SettingRepository;
  use App\Repository\WebhookDeliveryRepository;
  use App\Repository\WebhookRepository;
  use App\Security\ApiPrincipal;
  use App\Security\EgressGuard;
  use App\Security\Packages\ManifestValidator;
  use App\Security\Packages\PackagePolicyException;
  use App\Security\PasswordHasher;
  use App\Security\ReauthGate;
  use App\Security\SecretBox;
  use App\Security\WriteGate;
  use App\Service\ApiTokenService;
  use App\Service\Packages\PackageIntegrationService;
  use App\Service\SecretVault;
  use App\Service\WebhookService;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class PackageIntegrationServiceTest extends TestCase
  {
      private SigningHarness $root;

      protected function setUp(): void
      {
          parent::setUp();
          $this->root = SigningHarness::generate();
      }

      private function service(array $flags = ['api_tokens' => true, 'service_secrets' => true]): PackageIntegrationService
      {
          (new SettingRepository($this->db))->set('features', $flags);
          $ff = new FeatureFlags(new SettingRepository($this->db));

          return new PackageIntegrationService(
              $this->db,
              new PackageRepository($this->db),
              new PackageReleaseRepository($this->db),
              new InstalledPackageRepository($this->db),
              new InstalledPackagePermissionRepository($this->db),
              new InstalledPackageSettingsRepository($this->db),
              new InstalledPackageCredentialRepository($this->db),
              $this->apiTokenService(),
              $this->webhookService($ff),
              new ApiTokenRepository($this->db),
              new WebhookRepository($this->db),
              $this->vault($ff),
              new ManifestValidator(),
              new PackageHistoryRepository($this->db),
              new PackageTransparencyLogRepository($this->db),
              new ModerationLogRepository($this->db),
              new ReauthGate(new PasswordHasher()),
              new WriteGate(),
              $ff,
              new SettingRepository($this->db),
              $this->config,
          );
      }

      private function apiTokenService(): ApiTokenService
      {
          return new ApiTokenService(
              $this->db,
              new ApiTokenRepository($this->db),
              new ModerationLogRepository($this->db),
              new FeatureFlags(new SettingRepository($this->db)),
              $this->config,
              new ReauthGate(new PasswordHasher()),
              new WriteGate(),
          );
      }

      private function vault(FeatureFlags $ff): SecretVault
      {
          return new SecretVault(
              $this->db,
              new ServiceSecretRepository($this->db),
              new SecretBox(str_repeat('a', 64)),
              new ModerationLogRepository($this->db),
              $ff,
              $this->config,
          );
      }

      private function webhookService(FeatureFlags $ff): WebhookService
      {
          return new WebhookService(
              $this->db,
              new WebhookRepository($this->db),
              new WebhookDeliveryRepository($this->db),
              $this->vault($ff),
              new ModerationLogRepository($this->db),
              $ff,
              $this->config,
              new ReauthGate(new PasswordHasher()),
              new WriteGate(),
              new EgressGuard(false, []),
          );
      }

      /**
       * @param list<string> $granted
       * @param list<string> $ungranted
       * @return array{0:User,1:int}
       */
      private function enabledRemoteApp(array $granted, array $ungranted = [], string $state = 'enabled'): array
      {
          $seeded = RegistryFixtures::seed($this->db, $this->root, null, [
              'type' => 'remote_app',
              'publisher_uid' => 'acme',
              'package_uid' => 'acme/remote-app',
              'name' => 'Acme Remote',
          ]);
          $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
          $installs = new InstalledPackageRepository($this->db);
          $installedId = $installs->create([
              'package_id' => $seeded['package_id'],
              'release_id' => $seeded['release_id'],
              'digest' => $seeded['release_digest'],
              'source_registry_id' => $seeded['registry_id'],
              'publisher_id' => $seeded['publisher_id'],
              'trust_class' => 'reviewed_declarative',
              'review_status' => 'approved',
              'compat_min' => null,
              'compat_max' => null,
              'installed_by' => $admin->id(),
          ]);
          $installs->setState($installedId, $state);

          $perms = [];
          foreach ($granted as $scope) {
              $perms[] = ['kind' => 'api_scope', 'key' => $scope, 'risk' => 'medium', 'granted' => true];
          }
          foreach ($ungranted as $scope) {
              $perms[] = ['kind' => 'api_scope', 'key' => $scope, 'risk' => 'medium', 'granted' => false];
          }
          (new InstalledPackagePermissionRepository($this->db))->replaceWithGrants($installedId, $perms, $admin->id());

          return [$admin, $installedId];
      }

      private function assertRefusal(string $code, callable $call): void
      {
          try {
              $call();
              self::fail('expected refusal ' . $code);
          } catch (PackagePolicyException $e) {
              self::assertSame($code, $e->code);
          }
      }

      public function test_provision_reveals_token_once_stores_only_a_hash_and_authenticates_as_a_scope_only_principal(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards', 'read:threads']);

          $res = $this->service()->provisionCredentials($admin, 'password123', $installedId);

          self::assertNotNull($res['api_token']);
          self::assertStringStartsWith('rbt_', $res['api_token']);
          self::assertNull($res['webhook_secret']);
          self::assertCount(1, $res['credentials']);
          self::assertSame('api_token', $res['credentials'][0]['kind']);

          // Hash-only storage: the api_tokens row keeps only the sha256, never the plaintext.
          $stored = (string) $this->db->fetchValue(
              'SELECT token_hash FROM api_tokens WHERE token_hash = ?',
              [hash('sha256', $res['api_token'])],
          );
          self::assertSame(hash('sha256', $res['api_token']), $stored);

          // Human-token separation: authenticates as a scope-only ApiPrincipal (never a User/role).
          $principal = $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']);
          self::assertInstanceOf(ApiPrincipal::class, $principal);
          self::assertSame(['read:boards', 'read:threads'], $principal->scopes());
          self::assertTrue($principal->hasScope('read:boards'));
          self::assertSame($admin->id(), $principal->createdBy(), 'created_by = minting admin, provenance only');

          // Lifecycle history records a credential_mint.
          $events = array_column(
              (new PackageHistoryRepository($this->db))->forInstall($installedId),
              'event',
          );
          self::assertContains('credential_mint', $events);
      }

      public function test_undeclared_or_unknown_scope_is_denied_and_audited_never_minted(): void
      {
          // TM-SC-08: an ungranted-but-known scope AND an unknown/future scope are both excluded and audited.
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards', 'admin:everything'], ['read:threads']);

          $res = $this->service()->provisionCredentials($admin, 'password123', $installedId);

          $principal = $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']);
          self::assertNotNull($principal);
          self::assertSame(['read:boards'], $principal->scopes(), 'only granted scopes local code supports are minted');

          $denied = (int) $this->db->fetchValue(
              "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_scope_denied'",
          );
          self::assertSame(1, $denied, 'the unknown scope is audited exactly once');
      }

      public function test_repeated_provisioning_refuses_a_second_active_api_token(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          $svc = $this->service();

          $first = $svc->provisionCredentials($admin, 'password123', $installedId);
          self::assertNotNull($first['api_token']);

          $this->assertRefusal(
              'credential_exists',
              fn () => $svc->provisionCredentials($admin, 'password123', $installedId),
          );
          self::assertCount(
              1,
              array_values(array_filter(
                  (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId),
                  static fn (array $row): bool => (string) $row['kind'] === 'api_token',
              )),
              'exactly one active package-owned api_token link remains',
          );
      }

      public function test_ungranted_permission_blocks_provisioning_and_mints_nothing(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards'], ['read:threads']);
          // read:threads left ungranted -> ungrantedCount > 0 -> refuse before any mint.
          $this->assertRefusal(
              'not_consented',
              fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
          );
          self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId));
      }

      public function test_non_enabled_install_cannot_provision(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards'], [], 'installed');
          $this->assertRefusal(
              'invalid_state',
              fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
          );
      }

      public function test_service_secrets_dark_fails_closed(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          $this->expectException(ValidationException::class);
          // service_secrets OFF (api_tokens on) -> hard predecessor missing -> fail closed, mint nothing.
          $this->service(['api_tokens' => true, 'service_secrets' => false])
              ->provisionCredentials($admin, 'password123', $installedId);
      }

      public function test_emergency_execution_disable_blocks_provisioning(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          (new SettingRepository($this->db))->set('package_execution_disabled', '1');
          $this->assertRefusal(
              'execution_disabled',
              fn () => $this->service()->provisionCredentials($admin, 'password123', $installedId),
          );
      }

      public function test_wrong_password_blocks_provisioning(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          $this->expectException(ValidationException::class);
          $this->service()->provisionCredentials($admin, 'WRONG', $installedId);
      }
  }
  ```

- [ ] **Step 2: Run it — expect RED (service missing).**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `Error: Class "App\Service\Packages\PackageIntegrationService" not found`.

- [ ] **Step 3: Create the service with the LOCKED constructor + `isExecutionDisabled`.** Write `src/Service/Packages/PackageIntegrationService.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Service\Packages;

  use App\Core\Config;
  use App\Core\Database;
  use App\Core\FeatureFlags;
  use App\Core\ValidationException;
  use App\Domain\User;
  use App\Repository\ApiTokenRepository;
  use App\Repository\InstalledPackageCredentialRepository;
  use App\Repository\InstalledPackagePermissionRepository;
  use App\Repository\InstalledPackageRepository;
  use App\Repository\InstalledPackageSettingsRepository;
  use App\Repository\ModerationLogRepository;
  use App\Repository\PackageHistoryRepository;
  use App\Repository\PackageReleaseRepository;
  use App\Repository\PackageRepository;
  use App\Repository\PackageTransparencyLogRepository;
  use App\Repository\SettingRepository;
  use App\Repository\WebhookRepository;
  use App\Security\ApiScopes;
  use App\Security\Packages\ManifestValidator;
  use App\Security\Packages\PackagePolicyException;
  use App\Security\ReauthGate;
  use App\Security\WriteGate;
  use App\Service\ApiTokenService;
  use App\Service\SecretVault;
  use App\Service\WebhookService;

  /**
   * Install-scoped integration runtime for remote_app/automation packages:
   * package-owned credential provisioning + event/scope gating over the B2 seams.
   * This file's api_token surface is owned by the api-token-provisioning group;
   * webhook minting/delivery + overview/onInstallIneligible land with sibling groups.
   */
  final class PackageIntegrationService
  {
      public function __construct(
          private Database $db,
          private PackageRepository $packages,
          private PackageReleaseRepository $releases,
          private InstalledPackageRepository $installs,
          private InstalledPackagePermissionRepository $permissions,
          private InstalledPackageSettingsRepository $settings,
          private InstalledPackageCredentialRepository $credentials,
          private ApiTokenService $apiTokens,
          private WebhookService $webhooks,
          private ApiTokenRepository $apiTokenRepo,
          private WebhookRepository $webhookRepo,
          private SecretVault $vault,
          private ManifestValidator $manifests,
          private PackageHistoryRepository $history,
          private PackageTransparencyLogRepository $transparency,
          private ModerationLogRepository $audit,
          private ReauthGate $reauth,
          private WriteGate $writeGate,
          private FeatureFlags $flags,
          private SettingRepository $settingRepo,
          private Config $config,
      ) {
      }

      /**
       * Atomically mint the package-owned api_token from its declared+granted api_scopes.
       * All guards fail before the transaction, minting nothing.
       *
       * @return array{api_token:?string, webhook_secret:?string, credentials:list<array<string,mixed>>}
       */
      public function provisionCredentials(User $admin, string $currentPassword, int $installedId): array
      {
          $this->writeGate->assertCanWrite($admin);

          $install = $this->installs->find($installedId);
          if ($install === null) {
              throw new PackagePolicyException('unknown_install', 'No such installed package.');
          }
          $package = $this->packages->find((int) $install['package_id']);
          if ($package === null) {
              throw new PackagePolicyException('invalid_state', 'The installed package is no longer resolvable.');
          }
          if (!in_array((string) $package['type'], ['remote_app', 'automation'], true)) {
              throw new PackagePolicyException('not_integrable', 'Only remote_app and automation packages expose integration credentials.');
          }
          if ((string) $install['state'] !== 'enabled') {
              throw new PackagePolicyException('invalid_state', 'The package must be enabled before provisioning credentials.');
          }
          if ($this->isExecutionDisabled()) {
              throw new PackagePolicyException('execution_disabled', 'Package execution is under an emergency disable.');
          }
          if ($this->permissions->ungrantedCount($installedId) > 0) {
              throw new PackagePolicyException('not_consented', 'Grant every declared permission before provisioning credentials.');
          }
          if (!$this->flags->enabled('service_secrets')) {
              // Hard predecessor / kill switch: fail closed, mint nothing.
              throw new ValidationException(['integration' => 'Enable the secret vault (service_secrets) before minting credentials.']);
          }
          $this->reauth->requirePassword($admin, $currentPassword);

          $scopes = $this->grantedApiScopes($installedId, (int) $install['package_id'], $admin);

          return $this->db->transaction(function () use ($admin, $currentPassword, $installedId, $install, $package, $scopes): array {
              $this->lockInstallForCredentialProvision($installedId);

              $token = null;
              $credentials = [];

              // ≤1 api_token: only when the install actually grants a locally-supported scope.
              if ($scopes !== []) {
                  $this->assertNoActiveCredential($installedId, 'api_token');
                  $label = $this->credentialLabel((string) $package['package_uid'], $installedId);
                  $minted = $this->apiTokens->mint($admin, $currentPassword, $label, $scopes, null);
                  $token = (string) $minted['token'];
                  $scopesJson = json_encode($scopes, JSON_UNESCAPED_SLASHES) ?: '[]';
                  $linkId = $this->credentials->insertApiToken($installedId, (int) $minted['id'], $label, $scopesJson, $admin->id());

                  $this->history->record([
                      'package_id' => (int) $install['package_id'],
                      'installed_package_id' => $installedId,
                      'event' => 'credential_mint',
                      'actor_id' => $admin->id(),
                      'detail' => json_encode(['kind' => 'api_token', 'credential_id' => $linkId, 'scopes' => $scopes], JSON_UNESCAPED_SLASHES),
                  ]);
                  $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
                  $this->transparency->record([
                      'package_uid' => (string) $package['package_uid'],
                      'version' => $release !== null ? (string) $release['version'] : null,
                      'digest' => (string) $install['digest'],
                      'event' => 'install',
                      'source' => 'local',
                      'actor_id' => $admin->id(),
                      'detail' => 'credential_mint:api_token',
                  ]);
                  $this->audit->log([
                      'actor_id' => $admin->id(),
                      'action' => 'package_credential_mint',
                      'target_type' => 'package',
                      'target_id' => (int) $install['package_id'],
                      'after' => ['kind' => 'api_token', 'credential_id' => $linkId, 'scopes' => $scopes],
                  ]);

                  $credentials[] = [
                      'id' => $linkId,
                      'kind' => 'api_token',
                      'label' => $label,
                      'status' => 'active',
                      'scopes' => $scopes,
                      'events' => [],
                  ];
              }

              return ['api_token' => $token, 'webhook_secret' => null, 'credentials' => $credentials];
          });
      }

      /** True when the package_execution_disabled DB setting OR packages.execution_disabled config is set. */
      public function isExecutionDisabled(): bool
      {
          if ((bool) $this->config->get('packages.execution_disabled', false)) {
              return true;
          }
          try {
              return $this->settingRepo->getString('package_execution_disabled', '') === '1';
          } catch (\Throwable) {
              return false;
          }
      }

      /**
       * Manifest = ceiling, grants = authority, local code = final gate: only declared+granted
       * api_scopes that ApiScopes still supports are minted; unknown/future scopes are denied
       * and audited (TM-SC-08), never included.
       *
       * @return list<string>
       */
      private function grantedApiScopes(int $installedId, int $packageId, User $admin): array
      {
          $scopes = [];
          foreach ($this->permissions->forInstall($installedId) as $row) {
              if ((string) $row['kind'] !== 'api_scope' || (int) $row['declared'] !== 1 || (int) $row['granted'] !== 1) {
                  continue;
              }
              $key = (string) $row['permission_key'];
              if (!ApiScopes::isValid($key)) {
                  $this->audit->log([
                      'actor_id' => $admin->id(),
                      'action' => 'package_scope_denied',
                      'target_type' => 'package',
                      'target_id' => $packageId,
                      'after' => ['installed_package_id' => $installedId, 'scope' => $key],
                  ]);
                  continue;
              }
              if (!in_array($key, $scopes, true)) {
                  $scopes[] = $key;
              }
          }

          return $scopes;
      }

      private function credentialLabel(string $packageUid, int $installedId): string
      {
          // ApiTokenService caps names at 80 chars; keep the uid + install id inside that.
          return mb_substr('pkg:' . $packageUid . '#' . $installedId, 0, 80);
      }

      private function lockInstallForCredentialProvision(int $installedId): void
      {
          // Serializes concurrent plain-form POSTs so the app-enforced
          // "≤1 active credential per kind" invariant cannot race.
          $this->db->fetch('SELECT id FROM installed_packages WHERE id = ? FOR UPDATE', [$installedId]);
      }

      private function assertNoActiveCredential(int $installedId, string $kind): void
      {
          foreach ($this->credentials->activeForInstall($installedId) as $cred) {
              if ((string) $cred['kind'] === $kind) {
                  throw new PackagePolicyException('credential_exists', 'This install already has an active ' . $kind . ' credential.');
              }
          }
      }
  }
  ```

- [ ] **Step 4: Run it — expect GREEN.**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `OK (8 tests, N assertions)`.

- [ ] **Step 5: Commit.**
  ```bash
  git add src/Service/Packages/PackageIntegrationService.php \
          tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "feat(packages): provision install-scoped api_token credentials with scope gating"
  ```

### Task 28: `rotateCredential` + `revokeCredential` (api_token)

**Files:**
- Modify: `src/Service/Packages/PackageIntegrationService.php`
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php` (add methods)

**Interfaces:**
- Consumes: `ApiTokenService::revoke(User,int):void`, `ApiTokenService::mint(...)`, `InstalledPackageCredentialRepository::{find(int):?array, insertApiToken(...):int, markRevoked(int):int}`, `Database::transaction(callable):mixed`.
- Produces (LOCKED):
  ```php
  public function rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array; // {secret:?string,token:?string} once
  public function revokeCredential(User $admin, int $installedId, int $credentialId): void; // WriteGate only, idempotent
  ```

**Steps:**

- [ ] **Step 1: Add the failing rotate/revoke tests.** Append to `PackageIntegrationServiceTest`:
  ```php
      public function test_rotate_revokes_the_old_token_and_reveals_a_new_one_once(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          $svc = $this->service();
          $first = $svc->provisionCredentials($admin, 'password123', $installedId);
          $credentialId = (int) $first['credentials'][0]['id'];

          $rotated = $svc->rotateCredential($admin, 'password123', $installedId, $credentialId);
          self::assertNull($rotated['secret']);
          self::assertNotNull($rotated['token']);
          self::assertStringStartsWith('rbt_', $rotated['token']);
          self::assertNotSame($first['api_token'], $rotated['token']);

          self::assertNull(
              $this->apiTokenService()->authenticate('Bearer ' . $first['api_token']),
              'the rotated-out token no longer authenticates',
          );
          $principal = $this->apiTokenService()->authenticate('Bearer ' . $rotated['token']);
          self::assertNotNull($principal);
          self::assertSame(['read:boards'], $principal->scopes());
      }

      public function test_revoke_kills_authentication_and_is_idempotent(): void
      {
          [$admin, $installedId] = $this->enabledRemoteApp(['read:boards']);
          $svc = $this->service();
          $res = $svc->provisionCredentials($admin, 'password123', $installedId);
          $credentialId = (int) $res['credentials'][0]['id'];

          $svc->revokeCredential($admin, $installedId, $credentialId);
          self::assertNull(
              $this->apiTokenService()->authenticate('Bearer ' . $res['api_token']),
              'a revoked credential cannot authenticate',
          );

          // Idempotent: a second revoke is a silent no-op (no exception, no re-audit).
          $svc->revokeCredential($admin, $installedId, $credentialId);
          $revokes = (int) $this->db->fetchValue(
              "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_credential_revoke'",
          );
          self::assertSame(1, $revokes);
      }
  ```

- [ ] **Step 2: Run them — expect RED (undefined methods).**
  ```bash
  vendor/bin/phpunit --filter 'test_rotate_revokes_the_old_token|test_revoke_kills_authentication'
  ```
  Expected: `Error: Call to undefined method App\Service\Packages\PackageIntegrationService::rotateCredential()`.

- [ ] **Step 3: Add `rotateCredential`.** Insert into the service (after `provisionCredentials`):
  ```php
      /**
       * Rotate one api_token credential: tokens have no in-place rotate, so revoke the old and
       * mint a replacement inside one transaction. @return array{secret:?string,token:?string} shown once.
       */
      public function rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array
      {
          $this->writeGate->assertCanWrite($admin);

          $link = $this->credentials->find($credentialId);
          if ($link === null || (int) $link['installed_package_id'] !== $installedId || $link['revoked_at'] !== null) {
              throw new PackagePolicyException('unknown_credential', 'No active credential to rotate.');
          }
          if ((string) $link['kind'] !== 'api_token') {
              // Webhook-credential rotation lands with the webhook-event-delivery group.
              throw new PackagePolicyException('credential_kind', 'Webhook credentials are rotated on the webhook surface.');
          }
          if ($this->isExecutionDisabled()) {
              throw new PackagePolicyException('execution_disabled', 'Package execution is under an emergency disable.');
          }
          if (!$this->flags->enabled('service_secrets')) {
              throw new ValidationException(['integration' => 'Enable the secret vault (service_secrets) before rotating credentials.']);
          }
          $this->reauth->requirePassword($admin, $currentPassword);

          $install = $this->installs->find($installedId);
          $package = $install !== null ? $this->packages->find((int) $install['package_id']) : null;
          if ($install === null || $package === null) {
              throw new PackagePolicyException('invalid_state', 'The installed package is no longer resolvable.');
          }
          $scopes = $this->grantedApiScopes($installedId, (int) $install['package_id'], $admin);
          if ($scopes === []) {
              throw new PackagePolicyException('no_scopes', 'This install no longer grants any API scope to mint.');
          }

          return $this->db->transaction(function () use ($admin, $currentPassword, $installedId, $install, $package, $link, $scopes): array {
              $this->apiTokens->revoke($admin, (int) $link['api_token_id']);
              $this->credentials->markRevoked((int) $link['id']);
              $this->history->record([
                  'package_id' => (int) $install['package_id'],
                  'installed_package_id' => $installedId,
                  'event' => 'credential_revoke',
                  'actor_id' => $admin->id(),
                  'detail' => json_encode(['kind' => 'api_token', 'credential_id' => (int) $link['id'], 'reason' => 'rotate'], JSON_UNESCAPED_SLASHES),
              ]);

              $label = $this->credentialLabel((string) $package['package_uid'], $installedId);
              $minted = $this->apiTokens->mint($admin, $currentPassword, $label, $scopes, null);
              $newLinkId = $this->credentials->insertApiToken(
                  $installedId,
                  (int) $minted['id'],
                  $label,
                  json_encode($scopes, JSON_UNESCAPED_SLASHES) ?: '[]',
                  $admin->id(),
              );
              $this->history->record([
                  'package_id' => (int) $install['package_id'],
                  'installed_package_id' => $installedId,
                  'event' => 'credential_mint',
                  'actor_id' => $admin->id(),
                  'detail' => json_encode(['kind' => 'api_token', 'credential_id' => $newLinkId, 'scopes' => $scopes, 'rotated_from' => (int) $link['id']], JSON_UNESCAPED_SLASHES),
              ]);
              $this->audit->log([
                  'actor_id' => $admin->id(),
                  'action' => 'package_credential_rotate',
                  'target_type' => 'package',
                  'target_id' => (int) $install['package_id'],
                  'after' => ['kind' => 'api_token', 'credential_id' => $newLinkId, 'rotated_from' => (int) $link['id']],
              ]);

              return ['secret' => null, 'token' => (string) $minted['token']];
          });
      }
  ```

- [ ] **Step 4: Add `revokeCredential`.** Insert after `rotateCredential`:
  ```php
      /**
       * Revoke one api_token credential (friction-free defensive action: WriteGate only, no reauth).
       * Idempotent: a no-op revoke forges no audit row.
       */
      public function revokeCredential(User $admin, int $installedId, int $credentialId): void
      {
          $this->writeGate->assertCanWrite($admin);

          $link = $this->credentials->find($credentialId);
          if ($link === null || (int) $link['installed_package_id'] !== $installedId) {
              return;
          }
          if ((string) $link['kind'] !== 'api_token') {
              // Webhook-credential revocation lands with the webhook-event-delivery group.
              throw new PackagePolicyException('credential_kind', 'Webhook credentials are revoked on the webhook surface.');
          }
          $install = $this->installs->find($installedId);

          $this->db->transaction(function () use ($admin, $installedId, $install, $link): void {
              if ($link['api_token_id'] !== null) {
                  $this->apiTokens->revoke($admin, (int) $link['api_token_id']);
              }
              if ($this->credentials->markRevoked((int) $link['id']) === 1 && $install !== null) {
                  $this->history->record([
                      'package_id' => (int) $install['package_id'],
                      'installed_package_id' => $installedId,
                      'event' => 'credential_revoke',
                      'actor_id' => $admin->id(),
                      'detail' => json_encode(['kind' => 'api_token', 'credential_id' => (int) $link['id']], JSON_UNESCAPED_SLASHES),
                  ]);
                  $this->audit->log([
                      'actor_id' => $admin->id(),
                      'action' => 'package_credential_revoke',
                      'target_type' => 'package',
                      'target_id' => (int) $install['package_id'],
                      'after' => ['kind' => 'api_token', 'credential_id' => (int) $link['id']],
                  ]);
              }
          });
      }
  ```

- [ ] **Step 5: Run the whole service test file — expect GREEN.**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `OK (10 tests, N assertions)`.

- [ ] **Step 6: Commit.**
  ```bash
  git add src/Service/Packages/PackageIntegrationService.php \
          tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "feat(packages): rotate and revoke package-owned api_token credentials"
  ```

### Task 29: Threat-model + requirement-ledger evidence and suite green

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (flip `TM-SC-08`)
- Modify: `docs/phase5/requirement-ledger.json` (advance `GA-DOD-08` scope evidence)

**Interfaces:** none (evidence/ledger only). Evidence path: `tests/Integration/Service/PackageIntegrationServiceTest.php`.

**Steps:**

- [ ] **Step 1: Confirm the api-token surface is green end-to-end.**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageIntegrationServiceTest.php \
                     tests/Integration/Repository/InstalledPackageCredentialRepositoryTest.php
  ```
  Expected: `OK (11 tests, …)`.

- [ ] **Step 2: Flip `TM-SC-08` in `docs/phase5/threat-models/fixtures.json`.** Change the `TM-SC-08` object from `"status": "stub"` to `"status": "implemented"` and add `"test": "tests/Integration/Service/PackageIntegrationServiceTest.php::test_undeclared_or_unknown_scope_is_denied_and_audited_never_minted"`.

- [ ] **Step 3: Advance the scope evidence in `docs/phase5/requirement-ledger.json`.** On the `GA-DOD-08` entry, append `"tests/Integration/Service/PackageIntegrationServiceTest.php"` to its `evidence` array (scope/credential-provisioning milestone). Leave the final `state` bump (`R3`/`R4`) to the increment wrap-up group once the webhook + secrets + outage slices also land — this group contributes the `scope` evidence only.

- [ ] **Step 4: Run the requirement-ledger + threat-model guards (JSON well-formedness + any ledger test).**
  ```bash
  php -r 'json_decode(file_get_contents("docs/phase5/requirement-ledger.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("docs/phase5/threat-models/fixtures.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
  vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Unit/Core/ThreatModelIndexTest.php
  ```
  Expected: `json ok` and PHPUnit `OK`. This guard is not optional; do not redirect or mask failures.

- [ ] **Step 5: Run the full suite once (no regressions) per DESIGN §13 / verification-before-completion.**
  ```bash
  composer test
  ```
  Expected: full suite green (`OK`), including the pre-existing gate-a set.

- [ ] **Step 6: Commit the evidence.**
  ```bash
  git add docs/phase5/threat-models/fixtures.json docs/phase5/requirement-ledger.json
  git commit -m "docs(phase5): record TM-SC-08 + GA-DOD-08 scope evidence for api_token provisioning"
  ```

**Group deliverable:** an independently committable api-token provisioning slice — `InstalledPackageCredentialRepository` (api_token linkage) + `PackageIntegrationService::{provisionCredentials,rotateCredential,revokeCredential,isExecutionDisabled}` (api_token path) with integration tests proving one-time reveal, hash-only storage, human-token separation (scope-only `ApiPrincipal`, `created_by` provenance), TM-SC-08 unknown/ungranted-scope denial+audit, and `not_consented`/`invalid_state`/`execution_disabled`/`service_secrets`-dark fail-closed denials — advancing `GA-DOD-08` (scope) and flipping `TM-SC-08` to implemented.

---

### Task 30: Package-owned API token auth guard (emergency disable + install safety)

**Files:**
- Create: `src/Service/Packages/PackageCredentialAuthGuard.php`
- Modify: `src/Service/ApiTokenService.php` (optional guard dependency + one authenticate-time check)
- Modify: `src/Core/App.php` (bind guard and append to `ApiTokenService`)
- Test: `tests/Integration/Service/PackageCredentialAuthGuardTest.php`

**Interfaces:**
- Consumes: `InstalledPackageCredentialRepository::findByApiToken(int): ?array`, `InstalledPackageRepository::find(int): ?array`, `PackageRepository::find(int): ?array`, `PackageReleaseRepository::find(int): ?array`, `PackageAdvisoryRepository::forPackage(int): array`, `LocalPackageBlockRepository::isBlocked(string, ?string): bool`, `RegistryAdvisoryService::blockingAdvisoryReason(...)`, `SettingRepository::getString(string,string): string`, `Config::get(string,mixed): mixed`.
- Produces:
  ```php
  final class PackageCredentialAuthGuard
  {
      public function allowsApiToken(int $apiTokenId): bool;
      public function isExecutionDisabled(): bool;
  }
  ```
- Auth invariant: API tokens with no active `installed_package_credentials.kind='api_token'` link are human/admin or legacy tokens and pass through unchanged. Linked package-owned tokens fail closed when the credential is revoked/missing, execution is disabled, the install is not `enabled`, review state is not approved, the installed release/package cannot be resolved, or local/advisory blocking applies.

**Steps:**

- [ ] **Step 1: Write the failing guard/authentication tests.** Create `tests/Integration/Service/PackageCredentialAuthGuardTest.php` with one shared package-owned-token fixture from `PackageIntegrationServiceTest` and these cases:
  ```php
  public function test_package_owned_token_authenticates_while_link_and_install_are_safe(): void;
  public function test_human_token_still_authenticates_when_package_execution_is_disabled(): void;
  public function test_package_owned_token_is_denied_when_execution_is_disabled(): void;
  public function test_package_owned_token_is_denied_after_credential_link_is_revoked(): void;
  public function test_package_owned_token_is_denied_when_install_is_disabled_quarantined_or_uninstalled(): void;
  public function test_package_owned_token_is_denied_when_review_local_block_or_advisory_is_unsafe(): void;
  ```
  The revoked-link case must mark only the `installed_package_credentials.revoked_at` row while leaving the raw `api_tokens.revoked_at` NULL, proving the guard denies package-owned auth by link state rather than relying only on token-table revocation. The test must authenticate through a real `ApiTokenService` that receives the guard, not by calling the guard directly for every case. Keep one direct `PackageCredentialAuthGuard::isExecutionDisabled()` assertion to prove DB setting and config break-glass both count.

- [ ] **Step 2: Run it — expect FAIL (class missing / auth does not consult guard).**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageCredentialAuthGuardTest.php
  ```
  Expected: `PackageCredentialAuthGuard` missing first; after the class exists but before `ApiTokenService` is modified, the emergency-disable package-token test must fail because `authenticate()` still returns an `ApiPrincipal`.

- [ ] **Step 3: Create `PackageCredentialAuthGuard`.** Implement fail-closed package-owned token checks and pass-through for non-package tokens:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Service\Packages;

  use App\Core\Config;
  use App\Repository\InstalledPackageCredentialRepository;
  use App\Repository\InstalledPackageRepository;
  use App\Repository\LocalPackageBlockRepository;
  use App\Repository\PackageAdvisoryRepository;
  use App\Repository\PackageReleaseRepository;
  use App\Repository\PackageRepository;
  use App\Repository\SettingRepository;
  use App\Service\Registry\RegistryAdvisoryService;

  final class PackageCredentialAuthGuard
  {
      public function __construct(
          private InstalledPackageCredentialRepository $credentials,
          private InstalledPackageRepository $installs,
          private PackageRepository $packages,
          private PackageReleaseRepository $releases,
          private PackageAdvisoryRepository $advisories,
          private LocalPackageBlockRepository $blocks,
          private SettingRepository $settings,
          private Config $config,
      ) {
      }

      public function allowsApiToken(int $apiTokenId): bool
      {
          $link = $this->credentials->findByApiToken($apiTokenId);
          if ($link === null) {
              return true; // not package-owned
          }
          if ($link['revoked_at'] !== null) {
              return false;
          }
          if ($this->isExecutionDisabled()) {
              return false;
          }

          $install = $this->installs->find((int) $link['installed_package_id']);
          if ($install === null || (string) $install['state'] !== 'enabled') {
              return false;
          }
          if ((string) ($install['review_status'] ?? '') !== 'approved') {
              return false;
          }

          $package = $this->packages->find((int) $install['package_id']);
          if ($package === null) {
              return false;
          }
          if (in_array((string) ($package['advisory_status'] ?? 'none'), ['blocked', 'revoked'], true)) {
              return false;
          }
          if ($this->blocks->isBlocked((string) $install['digest'], (string) $package['package_uid'])) {
              return false;
          }

          $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
          if ($release === null || (string) ($release['review_status'] ?? 'approved') !== 'approved') {
              return false;
          }
          if (in_array((string) ($release['advisory_status'] ?? 'none'), ['blocked', 'revoked'], true)) {
              return false;
          }

          $reason = RegistryAdvisoryService::blockingAdvisoryReason(
              $this->advisories->forPackage((int) $install['package_id']),
              ['force_disable', 'revoke'],
              (string) $install['digest'],
              (string) ($release['version'] ?? ''),
          );

          return $reason === null;
      }

      public function isExecutionDisabled(): bool
      {
          try {
              if ($this->settings->getString('package_execution_disabled', '') === '1') {
                  return true;
              }
          } catch (\Throwable) {
              // fall through to config break-glass
          }

          return (bool) $this->config->get('packages.execution_disabled', false);
      }
  }
  ```

- [ ] **Step 4: Wire the guard into `ApiTokenService::authenticate()`.** Add `use App\Service\Packages\PackageCredentialAuthGuard;`, append a nullable constructor property, then consult it after the token hash row is found and before `touchLastUsed()` or any principal is returned:
  ```php
          private ?PackageCredentialAuthGuard $packageAuthGuard = null,
  ```
  ```php
          if ($this->packageAuthGuard !== null && !$this->packageAuthGuard->allowsApiToken((int) $row['id'])) {
              return null;
          }
  ```
  This placement is important: denied package-owned tokens must not update `last_used_at`, and human tokens must not pay any package lookup cost unless the guard finds a credential link.

- [ ] **Step 5: Bind the guard and append it to `ApiTokenService`.** In `src/Core/App.php`, bind `PackageCredentialAuthGuard::class` with the repositories/settings/config listed above, then append `$c->get(PackageCredentialAuthGuard::class)` as the last argument to the `ApiTokenService::class` bind. Existing hand-built tests may omit the optional arg.

- [ ] **Step 6: Run to PASS.**
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageCredentialAuthGuardTest.php \
                     tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `OK`. The guard test proves emergency disable now denies package-owned API-token auth even when the token hash itself remains valid; the integration test proves provisioning/rotation/revoke behavior did not regress.

- [ ] **Step 7: Commit.**
  ```bash
  git add src/Service/Packages/PackageCredentialAuthGuard.php src/Service/ApiTokenService.php src/Core/App.php \
          tests/Integration/Service/PackageCredentialAuthGuardTest.php
  git commit -m "feat(packages): deny unsafe package-owned api token authentication"
  ```


<!-- ===== group: webhook-provisioning-event-gating — Package-owned webhook provisioning + event/outbound-host gating ===== -->

### Task 31: `installed_package_credentials` webhook linkage in the credential repository

**Files:**
- Modify: `src/Repository/InstalledPackageCredentialRepository.php` (verify the two webhook-linkage methods exist; add only if local drift removed them)
- Modify: `tests/Integration/Service/PackageIntegrationServiceTest.php` (append repo round-trip case to the Task 27 scaffold; do not replace the class)

**Interfaces:**
- Consumes: `App\Core\Database::insert(string,array):int`, `Database::fetch(string,array):?array`; the `installed_package_credentials` table shipped by migration `0073` (columns `installed_package_id, kind ENUM('api_token','webhook'), api_token_id, webhook_id, label, scopes_json, events_json, created_by, created_at, revoked_at`); the base repo methods `forInstall`, `activeForInstall`, `find`, `markRevoked`, `deleteFor` (already landed by the migration/repo group).
- Produces (locked, `App\Repository\InstalledPackageCredentialRepository`, `final`, ctor `(private Database $db)`):
  ```php
  public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int;
  public function findByWebhook(int $webhookId): ?array;
  ```
- Invariant enforced by these methods (asserted, no CHECK constraint): `kind='webhook'` ⇒ `webhook_id` NOT NULL and `api_token_id` NULL and `scopes_json` NULL.

- [ ] **Step 1: Append the repo round-trip test.** Append this case to the existing `tests/Integration/Service/PackageIntegrationServiceTest.php` scaffold created by Task 27. If that file is missing, stop and run Task 27 first; do not recreate the file with only this case:
  ```php
      public function test_insert_webhook_link_round_trips_and_holds_kind_invariant(): void
      {
          $webhookId = (new WebhookRepository($this->db))->insert('pkg-hook', 'https://hooks.acme.test/rb', '["topic.created"]', '', $this->admin->id());
          $repo = new InstalledPackageCredentialRepository($this->db);

          $id = $repo->insertWebhook($this->installId, $webhookId, 'pkg:acme/inbox-sync#' . $this->installId, '["topic.created"]', $this->admin->id());
          self::assertGreaterThan(0, $id);

          $row = $repo->findByWebhook($webhookId);
          self::assertNotNull($row);
          self::assertSame('webhook', (string) $row['kind']);
          self::assertSame($webhookId, (int) $row['webhook_id']);
          self::assertNull($row['api_token_id']);
          self::assertNull($row['scopes_json']);
          self::assertNull($row['revoked_at']);
          self::assertContains($id, array_map(static fn (array $c): int => (int) $c['id'], $repo->activeForInstall($this->installId)));
      }
  ```
- [ ] **Step 2: Run it and confirm repository coverage.** `vendor/bin/phpunit --filter test_insert_webhook_link_round_trips_and_holds_kind_invariant` → expect `OK` if Task 18/26 left the full repository surface intact. If it fails with `Call to undefined method ...::insertWebhook()`, continue to Steps 3-4 to repair the missing methods without replacing the repository.
- [ ] **Step 3: Implement `insertWebhook` only if missing.** Add to `InstalledPackageCredentialRepository` (leaves `api_token_id`/`scopes_json` at their NULL default, satisfying the webhook invariant):
  ```php
  public function insertWebhook(int $installedId, int $webhookId, string $label, string $eventsJson, ?int $createdBy): int
  {
      return $this->db->insert(
          'INSERT INTO installed_package_credentials
              (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
           VALUES (?, \'webhook\', ?, ?, ?, ?, UTC_TIMESTAMP())',
          [$installedId, $webhookId, $label, $eventsJson, $createdBy],
      );
  }
  ```
- [ ] **Step 4: Implement `findByWebhook` only if missing.** Add the lookup (single-webhook, newest link wins):
  ```php
  public function findByWebhook(int $webhookId): ?array
  {
      return $this->db->fetch(
          'SELECT * FROM installed_package_credentials WHERE webhook_id = ? ORDER BY id DESC LIMIT 1',
          [$webhookId],
      );
  }
  ```
- [ ] **Step 5: Run to PASS.** `vendor/bin/phpunit --filter test_insert_webhook_link_round_trips_and_holds_kind_invariant` → expect `OK (1 test, N assertions)`.
- [ ] **Step 6: Commit.**
  ```bash
  git add src/Repository/InstalledPackageCredentialRepository.php tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "test(packages): verify webhook linkage on installed_package_credentials repo

  Verifies insertWebhook/findByWebhook for package-owned webhook credentials;
  kind='webhook' rows keep api_token_id/scopes_json NULL. Appends to the
  cumulative PackageIntegrationServiceTest without replacing its scaffold.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
  ```

---

### Task 32: Package-owned webhook provisioning + event/outbound-host gating

**Files:**
- Modify: `src/Service/Packages/PackageIntegrationService.php` (add `WEBHOOK_URL_SETTING`, the `mintPackageWebhook`/`grantedEvents`/`grantedOutboundHosts`/`webhookUrlSetting` helpers, and wire the webhook mint into `provisionCredentials`' transaction)
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php` (gating + happy-path + atomic cases)

**Interfaces:**
- Consumes: `App\Service\WebhookService::register(User,string $currentPassword,string $name,string $url,array $events): array{id:int,secret:string}`; `App\Security\WebhookEvents::domainEvents(): array<string,string>` (excludes `ping`); `App\Repository\InstalledPackagePermissionRepository::forInstall(int): array` (rows carry `kind`, `permission_key`, `declared`, `granted`); `App\Repository\InstalledPackageSettingsRepository::find(int,string): ?array`; `App\Repository\InstalledPackageCredentialRepository::insertWebhook(...)` (Task 31); `App\Core\Config::get(string,mixed): mixed`; `App\Repository\PackageHistoryRepository::record(array):int` (event `credential_mint`, added to the enum by `0073`); `App\Repository\PackageTransparencyLogRepository::record(array):int`; `App\Repository\ModerationLogRepository::log(array):void`.
- Produces (inside `App\Service\Packages\PackageIntegrationService`, `final`): the webhook branch of the locked
  ```php
  public function provisionCredentials(User $admin, string $currentPassword, int $installedId): array; // array{api_token:?string, webhook_secret:?string, credentials:list<array>}
  ```
  wired so its single `$db->transaction` mints the webhook (via `WebhookService::register`) **before** the api_token mint, giving true all-or-nothing atomicity in production. Gating: requested events = granted `event` rows, each ∈ `WebhookEvents::domainEvents()` (`ping`/unknown → `PackagePolicyException('event_not_grantable')`); URL host ∈ granted `outbound_host` rows or `config('packages.integration_test_origin')` else `ValidationException` on the `webhook_url` field. Public constant `WEBHOOK_URL_SETTING = 'webhook_url'`.

- [ ] **Step 1: Write the failing gating + happy-path cases.** Append to `PackageIntegrationServiceTest` the shared factory/helpers and four cases:
  ```php
  private function integration(array $flags = ['package_registry' => true, 'webhooks' => true, 'api_tokens' => true, 'service_secrets' => true]): PackageIntegrationService
  {
      (new SettingRepository($this->db))->set('features', $flags);
      $flagsObj = new FeatureFlags(new SettingRepository($this->db));
      $reauth = new ReauthGate(new PasswordHasher());
      $writeGate = new WriteGate();
      $audit = new ModerationLogRepository($this->db);
      $apiTokens = new ApiTokenService($this->db, new ApiTokenRepository($this->db), $audit, $flagsObj, $this->config, $reauth, $writeGate);
      $webhooks = new WebhookService(
          $this->db, new WebhookRepository($this->db), new WebhookDeliveryRepository($this->db), $this->vault(),
          $audit, $flagsObj, $this->config, $reauth, $writeGate, new EgressGuard(false, []),
      );

      return new PackageIntegrationService(
          $this->db,
          new PackageRepository($this->db),
          new PackageReleaseRepository($this->db),
          new InstalledPackageRepository($this->db),
          new InstalledPackagePermissionRepository($this->db),
          new InstalledPackageSettingsRepository($this->db),
          new InstalledPackageCredentialRepository($this->db),
          $apiTokens,
          $webhooks,
          new ApiTokenRepository($this->db),
          new WebhookRepository($this->db),
          $this->vault(),
          new ManifestValidator(),
          new PackageHistoryRepository($this->db),
          new PackageTransparencyLogRepository($this->db),
          $audit,
          $reauth,
          $writeGate,
          $flagsObj,
          new SettingRepository($this->db),
          $this->config,
      );
  }

  private function vault(): SecretVault
  {
      return new SecretVault(
          $this->db,
          new ServiceSecretRepository($this->db),
          new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
          new ModerationLogRepository($this->db),
          new FeatureFlags(new SettingRepository($this->db)),
          $this->config,
      );
  }

  /**
   * @param list<string> $scopes
   * @param list<string> $events
   * @param list<string> $hosts
   */
  private function grant(array $scopes, array $events, array $hosts): void
  {
      $perms = [];
      foreach ($scopes as $s) { $perms[] = ['kind' => 'api_scope', 'key' => $s, 'risk' => 'low', 'granted' => true]; }
      foreach ($events as $e) { $perms[] = ['kind' => 'event', 'key' => $e, 'risk' => 'low', 'granted' => true]; }
      foreach ($hosts as $h) { $perms[] = ['kind' => 'outbound_host', 'key' => $h, 'risk' => 'medium', 'granted' => true]; }
      (new InstalledPackagePermissionRepository($this->db))->replaceWithGrants($this->installId, $perms, $this->admin->id());
  }

  private function setUrl(string $url): void
  {
      (new InstalledPackageSettingsRepository($this->db))->upsert(
          $this->installId, PackageIntegrationService::WEBHOOK_URL_SETTING, json_encode($url), null, false, $this->admin->id(),
      );
  }

  public function test_provision_subscribes_only_to_granted_domain_events(): void
  {
      $this->grant(['read:threads'], ['topic.created', 'reply.created'], ['hooks.acme.test']);
      $this->setUrl('https://hooks.acme.test/rb');

      $out = $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);

      self::assertIsString($out['webhook_secret']);
      self::assertNotSame('', $out['webhook_secret']);
      $link = (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId);
      $webhook = null;
      foreach ($link as $c) {
          if ((string) $c['kind'] === 'webhook') { $webhook = (new WebhookRepository($this->db))->findById((int) $c['webhook_id']); }
      }
      self::assertNotNull($webhook);
      self::assertSame(1, (int) $webhook['is_active']);
      self::assertSame(['topic.created', 'reply.created'], json_decode((string) $webhook['events'], true));
  }

  public function test_provision_rejects_ping_as_a_package_grantable_event(): void
  {
      $this->grant([], ['ping'], ['hooks.acme.test']);
      $this->setUrl('https://hooks.acme.test/rb');
      try {
          $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);
          self::fail('expected event_not_grantable refusal');
      } catch (PackagePolicyException $e) {
          self::assertSame('event_not_grantable', $e->code);
      }
  }

  public function test_provision_denies_a_url_host_outside_the_granted_outbound_hosts(): void
  {
      $this->grant([], ['topic.created'], ['other.example']);
      $this->setUrl('https://hooks.acme.test/rb');
      try {
          $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);
          self::fail('expected outbound-host ValidationException');
      } catch (ValidationException $e) {
          self::assertArrayHasKey(PackageIntegrationService::WEBHOOK_URL_SETTING, $e->errors);
      }
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId));
  }

  public function test_repeated_provisioning_refuses_a_second_active_webhook(): void
  {
      $this->grant([], ['topic.created'], ['hooks.acme.test']);
      $this->setUrl('https://hooks.acme.test/rb');

      $first = $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);
      self::assertIsString($first['webhook_secret']);

      try {
          $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);
          self::fail('expected credential_exists refusal');
      } catch (PackagePolicyException $e) {
          self::assertSame('credential_exists', $e->code);
      }
      self::assertCount(
          1,
          array_values(array_filter(
              (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId),
              static fn (array $row): bool => (string) $row['kind'] === 'webhook',
          )),
      );
  }

  public function test_provision_is_all_or_nothing_for_the_caller(): void
  {
      // service_secrets off: guard fires before any mint -> caller gets nothing at all.
      $this->grant(['read:threads'], ['topic.created'], ['hooks.acme.test']);
      $this->setUrl('https://hooks.acme.test/rb');
      try {
          $this->integration(['package_registry' => true, 'webhooks' => true, 'api_tokens' => true, 'service_secrets' => false])
              ->provisionCredentials($this->admin, 'password123', $this->installId);
          self::fail('expected service_secrets ValidationException');
      } catch (ValidationException) {
          self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId));
      }

      // api_tokens dark: the webhook mint runs first, then the token mint throws INSIDE the
      // transaction. Production rolls the webhook back via the shared $db->transaction; the
      // in-process harness has no savepoints, so we assert the caller-observable contract:
      // the whole call throws and returns no partial plaintext (never a webhook secret alone).
      $this->expectException(ApiTokensDisabledException::class);
      $this->integration(['package_registry' => true, 'webhooks' => true, 'api_tokens' => false, 'service_secrets' => true])
          ->provisionCredentials($this->admin, 'password123', $this->installId);
  }
  ```
- [ ] **Step 2: Run and confirm FAIL.** `vendor/bin/phpunit --filter PackageIntegrationServiceTest` → expect the webhook cases to fail (e.g. `Failed asserting that null is of type string` on `webhook_secret`, and `event_not_grantable` refusal not thrown) because `provisionCredentials` does not yet mint a webhook.
- [ ] **Step 3: Add the constant + `use` imports.** In `PackageIntegrationService` ensure `use App\Security\WebhookEvents;`, `use App\Security\Packages\PackagePolicyException;`, `use App\Core\ValidationException;` are present, and add the class constant:
  ```php
  /** Per-install setting key that carries the package-owned webhook destination URL. */
  public const WEBHOOK_URL_SETTING = 'webhook_url';
  ```
- [ ] **Step 4: Add the grant/host reader helpers.** Add to `PackageIntegrationService` (manifest = ceiling, grants = authority — runtime reads only `declared=1 AND granted=1` rows):
  ```php
  /** @return list<string> granted, declared event permission keys. */
  private function grantedEvents(int $installedId): array
  {
      $events = [];
      foreach ($this->permissions->forInstall($installedId) as $p) {
          if ((string) $p['kind'] === 'event' && (int) $p['declared'] === 1 && (int) $p['granted'] === 1) {
              $events[] = (string) $p['permission_key'];
          }
      }
      return $events;
  }

  /** @return list<string> granted, declared outbound-host permission keys (lowercased). */
  private function grantedOutboundHosts(int $installedId): array
  {
      $hosts = [];
      foreach ($this->permissions->forInstall($installedId) as $p) {
          if ((string) $p['kind'] === 'outbound_host' && (int) $p['declared'] === 1 && (int) $p['granted'] === 1) {
              $hosts[] = strtolower((string) $p['permission_key']);
          }
      }
      return $hosts;
  }

  private function webhookUrlSetting(int $installedId): string
  {
      $row = $this->settings->find($installedId, self::WEBHOOK_URL_SETTING);
      $url = $row !== null && $row['value_json'] !== null ? json_decode((string) $row['value_json'], true) : null;
      if (!is_string($url) || $url === '') {
          throw new ValidationException([self::WEBHOOK_URL_SETTING => 'Set the destination URL before provisioning a webhook.']);
      }
      return $url;
  }
  ```
- [ ] **Step 5: Add the `mintPackageWebhook` helper.** This is the atomic, gated webhook mint; it runs inside the caller's transaction (`WebhookService::register` nests/joins it) so a later token-mint failure rolls it back:
  ```php
  /**
   * Mint one package-owned webhook. Runs after the provision guards, before the api_token
   * mint. Enforces event ⊆ WebhookEvents::domainEvents() (ping/unknown denied — TM-SC-08)
   * and host ∈ granted outbound_hosts (or the config test origin).
   * @param array<string,mixed> $package
   * @return array{webhook_id:int, secret:string, events:list<string>}
   */
  private function mintPackageWebhook(User $admin, string $currentPassword, array $package, int $installedId): array
  {
      $events = $this->grantedEvents($installedId);
      $domain = WebhookEvents::domainEvents();
      foreach ($events as $event) {
          if (!array_key_exists($event, $domain)) {
              throw new PackagePolicyException('event_not_grantable', 'Event "' . $event . '" cannot be delivered to a package.');
          }
      }

      $url = $this->webhookUrlSetting($installedId);
      $host = strtolower((string) parse_url($url, PHP_URL_HOST));
      $allowed = $this->grantedOutboundHosts($installedId);
      $testOrigin = strtolower((string) $this->config->get('packages.integration_test_origin', ''));
      if ($host === '' || (!in_array($host, $allowed, true) && ($testOrigin === '' || $host !== $testOrigin))) {
          throw new ValidationException([self::WEBHOOK_URL_SETTING => 'Destination host is not a granted outbound host.']);
      }
      $this->assertNoActiveCredential($installedId, 'webhook');

      $label = 'pkg:' . (string) $package['package_uid'] . '#' . $installedId;
      $result = $this->webhooks->register($admin, $currentPassword, $label, $url, $events);
      $this->credentials->insertWebhook(
          $installedId,
          (int) $result['id'],
          $label,
          json_encode(array_values($events), JSON_UNESCAPED_SLASHES) ?: '[]',
          $admin->id(),
      );
      $this->history->record([
          'package_id' => (int) $package['id'],
          'installed_package_id' => $installedId,
          'event' => 'credential_mint',
          'actor_id' => $admin->id(),
          'detail' => 'webhook:' . implode(',', $events),
      ]);
      $this->transparency->record([
          'package_uid' => (string) $package['package_uid'],
          'event' => 'install',
          'source' => 'local',
          'actor_id' => $admin->id(),
          'detail' => 'webhook credential minted',
      ]);
      $this->audit->log([
          'actor_id' => $admin->id(),
          'action' => 'package_credential_mint',
          'target_type' => 'package',
          'target_id' => (int) $package['id'],
          'after' => ['kind' => 'webhook', 'events' => $events],
      ]);

      return ['webhook_id' => (int) $result['id'], 'secret' => (string) $result['secret'], 'events' => $events];
  }
  ```
- [ ] **Step 6: Wire the webhook mint into `provisionCredentials`.** Inside the existing `$this->db->transaction(function () use (...) { ... })` body, before the api_token mint, resolve the package and (only when there are granted events) call the helper, folding its secret into the return payload:
  ```php
  // ... inside provisionCredentials' transaction, after guards, before the api_token mint:
  $package = $this->packages->find((int) $install['package_id']);
  $webhookSecret = null;
  if ($this->grantedEvents($installedId) !== []) {
      $minted = $this->mintPackageWebhook($admin, $currentPassword, $package, $installedId);
      $webhookSecret = $minted['secret'];
  }
  // ... api_token mint (group #6) sets $apiToken; assemble:
  //   return ['api_token' => $apiToken, 'webhook_secret' => $webhookSecret, 'credentials' => $this->credentials->activeForInstall($installedId)];
  ```
  (Keep it one transaction — do not open a nested one; `WebhookService::register` reuses the active PDO transaction.)
- [ ] **Step 7: Run to PASS.** `vendor/bin/phpunit --filter PackageIntegrationServiceTest` → expect `OK`. Then `composer test` to confirm no regression in the api_token half or `WebhookServiceTest`.
- [ ] **Step 8: Commit.**
  ```bash
  git add src/Service/Packages/PackageIntegrationService.php tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "feat(packages): provision package-owned webhooks with event/host gating

  provisionCredentials mints a package webhook (WebhookService::register) inside its
  single transaction, subscribed only to granted domain events (ping/unknown denied,
  TM-SC-08) and only to a URL whose host is a granted outbound_host. Token-mint failure
  rolls the webhook back (shared \$db->transaction, no savepoints).

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
  ```

---

### Task 33: Delivery pause/resume, webhook rotate/revoke, and ineligibility suppression

**Files:**
- Modify: `src/Service/Packages/PackageIntegrationService.php` (`suspendDelivery`, `resumeDelivery`, `isExecutionDisabled`, webhook branches of `rotateCredential`/`revokeCredential`, webhook effect in `onInstallIneligible`)
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php` (suppression, resume, rotate, revoke-idempotency, emergency-disable cases)
- Modify: `docs/phase5/threat-models/fixtures.json` (flip `TM-SC-08` → `implemented`)
- Modify: `docs/phase5/requirement-ledger.json` (advance `GA-DOD-08` with the evidence path)

**Interfaces:**
- Consumes: `App\Repository\WebhookRepository::disable(int,string):int`, `WebhookRepository::enable(int):int`, `WebhookRepository::findById(int):?array`; `App\Service\WebhookService::rotateSecret(User,string,int):string`, `WebhookService::delete(User,int):void`, `WebhookService::dispatch(string,array,?string):int`; `App\Repository\InstalledPackageCredentialRepository::{activeForInstall,find,markRevoked}`; `App\Repository\SettingRepository::getString(string,string):string`; `App\Repository\ApiTokenRepository::revoke(int):int`.
- Produces (locked, in `PackageIntegrationService`):
  ```php
  public function suspendDelivery(int $installedId, string $reason): int;                    // webhookRepo->disable per active webhook cred; no reauth
  public function resumeDelivery(User $admin, int $installedId): int;                         // refused if emergency-disabled/not-enabled
  public function onInstallIneligible(int $installedId, string $reason, ?int $actorId): void; // webhook effect: markRevoked + disable; idempotent, no reauth
  public function isExecutionDisabled(): bool;                                                // package_execution_disabled setting OR packages.execution_disabled config
  ```
  plus the `kind='webhook'` branch of `rotateCredential(User,string,int,int): array{secret:?string,token:?string}` (via `WebhookService::rotateSecret`) and `revokeCredential(User,int,int): void` (via `WebhookService::delete` + `markRevoked`).

- [ ] **Step 1: Write the failing suppression/lifecycle cases.** Append to `PackageIntegrationServiceTest` a helper that provisions a webhook and returns its id, plus five cases:
  ```php
  private function provisionWebhookId(): int
  {
      $this->grant([], ['topic.created'], ['hooks.acme.test']);
      $this->setUrl('https://hooks.acme.test/rb');
      $this->integration()->provisionCredentials($this->admin, 'password123', $this->installId);
      foreach ((new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId) as $c) {
          if ((string) $c['kind'] === 'webhook') { return (int) $c['webhook_id']; }
      }
      self::fail('no webhook credential provisioned');
  }

  public function test_suspend_delivery_disables_the_endpoint_so_dispatch_skips_it(): void
  {
      $svc = $this->integration();
      $webhookId = $this->provisionWebhookId();
      self::assertSame(1, (int) (new WebhookRepository($this->db))->findById($webhookId)['is_active']);

      $paused = $svc->suspendDelivery($this->installId, 'operator pause');
      self::assertSame(1, $paused);
      self::assertSame(0, (int) (new WebhookRepository($this->db))->findById($webhookId)['is_active']);

      // A disabled package endpoint receives no deliveries.
      $webhooks = new WebhookService(
          $this->db, new WebhookRepository($this->db), new WebhookDeliveryRepository($this->db), $this->vault(),
          new ModerationLogRepository($this->db), new FeatureFlags(new SettingRepository($this->db)),
          $this->config, new ReauthGate(new PasswordHasher()), new WriteGate(), new EgressGuard(false, []),
      );
      self::assertSame(0, $webhooks->dispatch('topic.created', ['thread_id' => 1], 'evt-1'));
  }

  public function test_resume_delivery_reenables_after_suspension(): void
  {
      $svc = $this->integration();
      $webhookId = $this->provisionWebhookId();
      $svc->suspendDelivery($this->installId, 'pause');
      self::assertSame(1, $svc->resumeDelivery($this->admin, $this->installId));
      self::assertSame(1, (int) (new WebhookRepository($this->db))->findById($webhookId)['is_active']);
  }

  public function test_resume_delivery_is_refused_while_execution_disabled(): void
  {
      $svc = $this->integration();
      $this->provisionWebhookId();
      $svc->suspendDelivery($this->installId, 'pause');
      (new SettingRepository($this->db))->set('package_execution_disabled', '1');
      self::assertTrue($svc->isExecutionDisabled());
      try {
          $svc->resumeDelivery($this->admin, $this->installId);
          self::fail('expected execution_disabled refusal');
      } catch (PackagePolicyException $e) {
          self::assertSame('execution_disabled', $e->code);
      }
  }

  public function test_on_install_ineligible_suppresses_and_marks_revoked(): void
  {
      $svc = $this->integration();
      $webhookId = $this->provisionWebhookId();

      $svc->onInstallIneligible($this->installId, 'disabled', $this->admin->id());

      self::assertSame(0, (int) (new WebhookRepository($this->db))->findById($webhookId)['is_active']);
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId));
      // Idempotent second call must not throw.
      $svc->onInstallIneligible($this->installId, 'disabled', $this->admin->id());
      self::assertTrue(true);
  }

  public function test_revoke_credential_is_idempotent(): void
  {
      $svc = $this->integration();
      $this->provisionWebhookId();
      $credId = (int) (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId)[0]['id'];
      $svc->revokeCredential($this->admin, $this->installId, $credId);
      $svc->revokeCredential($this->admin, $this->installId, $credId); // no-op, no throw
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($this->installId));
  }
  ```
- [ ] **Step 2: Run and confirm FAIL.** `vendor/bin/phpunit --filter PackageIntegrationServiceTest` → expect `Error: Call to undefined method ...::suspendDelivery()` (and the resume/ineligible/revoke cases failing) since those methods are not implemented yet.
- [ ] **Step 3: Implement `isExecutionDisabled` + `suspendDelivery`.** Add to `PackageIntegrationService` (suspend is friction-free/defensive — no reauth):
  ```php
  public function isExecutionDisabled(): bool
  {
      try {
          if ($this->settingRepo->getString('package_execution_disabled', '') === '1') {
              return true;
          }
      } catch (\Throwable) {
          // fall through to the break-glass config
      }
      return (bool) $this->config->get('packages.execution_disabled', false);
  }

  public function suspendDelivery(int $installedId, string $reason): int
  {
      $paused = 0;
      foreach ($this->credentials->activeForInstall($installedId) as $cred) {
          if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
              $paused += $this->webhookRepo->disable((int) $cred['webhook_id'], substr('Package delivery paused: ' . $reason, 0, 190));
          }
      }
      return $paused;
  }
  ```
- [ ] **Step 4: Implement `resumeDelivery`.** Refuse while emergency-disabled or when the install is not enabled:
  ```php
  public function resumeDelivery(User $admin, int $installedId): int
  {
      $this->writeGate->assertCanWrite($admin);
      if ($this->isExecutionDisabled()) {
          throw new PackagePolicyException('execution_disabled', 'Package execution is globally disabled.');
      }
      $install = $this->installs->find($installedId);
      if ($install === null || (string) $install['state'] !== 'enabled') {
          throw new PackagePolicyException('invalid_state', 'Only enabled installs can resume delivery.');
      }
      $resumed = 0;
      foreach ($this->credentials->activeForInstall($installedId) as $cred) {
          if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
              $resumed += $this->webhookRepo->enable((int) $cred['webhook_id']);
          }
      }
      return $resumed;
  }
  ```
- [ ] **Step 5: Implement the webhook branch of `revokeCredential`/`rotateCredential`.** Revoke is defensive (WriteGate only, no reauth); rotate reuses `WebhookService::rotateSecret` (reauth inside). Add the `kind='webhook'` handling to the shared dispatch:
  ```php
  // in revokeCredential(User $admin, int $installedId, int $credentialId): void
  $cred = $this->credentials->find($credentialId);
  if ($cred === null || (int) $cred['installed_package_id'] !== $installedId) {
      return; // unknown / foreign credential -> no-op
  }
  if ((string) $cred['kind'] === 'webhook') {
      $this->writeGate->assertCanWrite($admin);
      if ($this->credentials->markRevoked($credentialId) !== 1) {
          return; // already revoked -> idempotent
      }
      if ($cred['webhook_id'] !== null) {
          $this->webhooks->delete($admin, (int) $cred['webhook_id']);
      }
      $this->history->record([
          'installed_package_id' => $installedId,
          'event' => 'credential_revoke',
          'actor_id' => $admin->id(),
          'detail' => 'webhook credential revoked',
      ]);
      return;
  }
  // ... (api_token branch owned by the api-token task)

  // in rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array
  if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
      $secret = $this->webhooks->rotateSecret($admin, $currentPassword, (int) $cred['webhook_id']);
      return ['secret' => $secret, 'token' => null];
  }
  ```
- [ ] **Step 6: Add the webhook effect to `onInstallIneligible`.** In the credential loop (repo-level, no reauth, `?int $actorId` only — mirrors `ThemeStateService::onInstallIneligible`), disable and mark each webhook credential revoked so a disabled/uninstalled/quarantined install can neither deliver nor be re-enabled:
  ```php
  // inside onInstallIneligible(int $installedId, string $reason, ?int $actorId): void
  foreach ($this->credentials->activeForInstall($installedId) as $cred) {
      if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
          $this->webhookRepo->disable((int) $cred['webhook_id'], substr('Install ineligible: ' . $reason, 0, 190));
      }
      // api_token creds revoked via apiTokenRepo->revoke (api-token task branch)
      $this->credentials->markRevoked((int) $cred['id']);
  }
  ```
  (Merge with the api-token task's branch — one loop, do not duplicate.)
- [ ] **Step 7: Run to PASS.** `vendor/bin/phpunit --filter PackageIntegrationServiceTest` → expect `OK (... tests, N assertions)`, then `composer test` to confirm the lifecycle-hook wiring and `WebhookServiceTest` stay green.
- [ ] **Step 8: Flip the threat-model fixture.** In `docs/phase5/threat-models/fixtures.json` change the `TM-SC-08` row from `"status": "stub"` to `"status": "implemented", "test": "tests/Integration/Service/PackageIntegrationServiceTest.php"`.
- [ ] **Step 9: Advance the requirement ledger.** In `docs/phase5/requirement-ledger.json` update `GA-DOD-08` from `"state": "R1", "evidence": []` to `"state": "R3", "evidence": ["tests/Integration/Service/PackageIntegrationServiceTest.php"]` (scope/outage coverage; final R4 lands with the controller/browser groups).
- [ ] **Step 10: Commit the deliverable.**
  ```bash
  git add src/Service/Packages/PackageIntegrationService.php tests/Integration/Service/PackageIntegrationServiceTest.php docs/phase5/threat-models/fixtures.json docs/phase5/requirement-ledger.json
  git commit -m "feat(packages): pause/resume/revoke package webhook delivery + ineligibility suppression

  suspendDelivery/resumeDelivery/onInstallIneligible disable package-owned webhooks so the
  delivery worker naturally skips them; resume refused while package_execution_disabled or
  install not enabled; webhook rotate/revoke reuse WebhookService. Marks TM-SC-08 implemented,
  advances GA-DOD-08 (scope/outage).

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
  ```


<!-- ===== group: credential-cleanup-lifecycle — Disable/uninstall/export credential cleanup + lifecycle hook wiring ===== -->

The lifecycle-hook group uses the existing `themes?->onInstallIneligible` mirror pattern, the trailing-nullable constructor seams, the raw revoke paths in the token/webhook repositories, the lifecycle export pattern, and the existing `AppFeatureFlagTest` dark-route matrix.

---

### Task 36: `PackageIntegrationService::onInstallIneligible()` — revoke tokens + pause webhooks

Implements the autonomous teardown hook that every ineligible transition will call. Tokens are terminally revoked, package-owned webhook endpoints are paused (so the existing delivery worker / `WebhookService::dispatch()` skip them), and every `installed_package_credentials` link is marked revoked — all in one transaction, no reauth, idempotent. Mirrors `ThemeStateService::onInstallIneligible` (the theme seam already wired into lifecycle/health).

**Files:**
- Modify: `src/Service/Packages/PackageIntegrationService.php` (add the `onInstallIneligible` method only — the class + all other methods land in the provisioning task that runs before this group)
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php` (append 2 methods; reuse the fixture the provisioning task established)

**Interfaces:**
- Consumes (already implemented / bound by earlier tasks):
  - `InstalledPackageCredentialRepository::activeForInstall(int $installedId): array` → rows with `id, kind, api_token_id, webhook_id`
  - `InstalledPackageCredentialRepository::markRevoked(int $id): int` (idempotent, `WHERE revoked_at IS NULL`)
  - `ApiTokenRepository::revoke(int $id): int` (raw, no `WriteGate` — the lifecycle path has only `?int $actorId`)
  - `PackageIntegrationService::suspendDelivery(int $installedId, string $reason): int` (pauses package-owned webhooks; defensive, no reauth)
  - `InstalledPackageRepository::find(int $id): ?array`, `PackageHistoryRepository::record(array): void` (event `credential_revoke` exists after migration `0073`)
- Consumes (test fixture the provisioning task's `setUp` must expose in `PackageIntegrationServiceTest`): `$this->db`, `$this->admin()` (an admin `User`), `$this->service(): PackageIntegrationService` (21-arg build on `$this->db`), `$this->integrableInstall(): int` (an `enabled` `remote_app` install with a granted `api_scope`, `event`, and `outbound_host`), `$this->store` (`PackageArtifactStore`)
- Produces:
  ```php
  /** Lifecycle hook (mirrors ThemeStateService::onInstallIneligible): revoke all credentials + suspendDelivery. Idempotent, no reauth. reason ∈ disabled|uninstalled|quarantined|force_disabled|emergency_disabled. */
  public function onInstallIneligible(int $installedId, string $reason, ?int $actorId): void;
  ```

Steps:

- [ ] **Step 1: Add a shared provisioning helper to the test file.** Append this to `tests/Integration/Service/PackageIntegrationServiceTest.php` (reuses `provisionCredentials`, which the provisioning task already implemented, and reads the link rows back for their owning-credential ids):
  ```php
  /** @return array{api_token_id:int,webhook_id:int} */
  private function provisionVia(PackageIntegrationService $svc, int $installedId): array
  {
      $svc->provisionCredentials($this->admin(), 'password123', $installedId);
      $out = ['api_token_id' => 0, 'webhook_id' => 0];
      foreach ((new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId) as $row) {
          if ((string) $row['kind'] === 'api_token') {
              $out['api_token_id'] = (int) $row['api_token_id'];
          }
          if ((string) $row['kind'] === 'webhook') {
              $out['webhook_id'] = (int) $row['webhook_id'];
          }
      }

      return $out;
  }
  ```
  Add the imports if the file lacks them: `use App\Repository\ApiTokenRepository;`, `use App\Repository\WebhookRepository;`, `use App\Repository\InstalledPackageCredentialRepository;`.

- [ ] **Step 2: Write the failing teardown + idempotency tests.** Append to the same file:
  ```php
  public function test_on_install_ineligible_revokes_tokens_and_pauses_webhooks(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      ['api_token_id' => $tokenId, 'webhook_id' => $hookId] = $this->provisionVia($svc, $installedId);

      // Sanity: live before teardown.
      self::assertNull((new ApiTokenRepository($this->db))->findById($tokenId)['revoked_at']);
      self::assertSame(1, (int) (new WebhookRepository($this->db))->findById($hookId)['is_active']);

      $svc->onInstallIneligible($installedId, 'disabled', $this->admin()->id());

      self::assertNotNull(
          (new ApiTokenRepository($this->db))->findById($tokenId)['revoked_at'],
          'package api token is revoked',
      );
      self::assertSame(
          0,
          (int) (new WebhookRepository($this->db))->findById($hookId)['is_active'],
          'package webhook endpoint is paused so the delivery worker skips it',
      );
      foreach ((new InstalledPackageCredentialRepository($this->db))->forInstall($installedId) as $link) {
          self::assertNotNull($link['revoked_at'], 'every credential link is marked revoked');
      }
  }

  public function test_on_install_ineligible_is_idempotent(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      ['api_token_id' => $tokenId] = $this->provisionVia($svc, $installedId);

      $svc->onInstallIneligible($installedId, 'uninstalled', null);
      $firstRevokedAt = (new ApiTokenRepository($this->db))->findById($tokenId)['revoked_at'];

      // Second call must not throw and must not resurrect or re-stamp anything.
      $svc->onInstallIneligible($installedId, 'uninstalled', null);
      self::assertSame(
          $firstRevokedAt,
          (new ApiTokenRepository($this->db))->findById($tokenId)['revoked_at'],
          'revocation timestamp is stable across repeated teardown',
      );
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId));
  }
  ```

- [ ] **Step 3: Run the tests — expect FAIL (method undefined).**
  ```bash
  vendor/bin/phpunit --filter 'test_on_install_ineligible' tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `Error: Call to undefined method App\Service\Packages\PackageIntegrationService::onInstallIneligible()`.

- [ ] **Step 4: Implement `onInstallIneligible`.** Add this method to `src/Service/Packages/PackageIntegrationService.php`, placed right after `resumeDelivery()`:
  ```php
  public function onInstallIneligible(int $installedId, string $reason, ?int $actorId): void
  {
      $active = $this->credentials->activeForInstall($installedId);
      if ($active === []) {
          return; // idempotent: nothing live to tear down
      }

      $this->db->transaction(function () use ($active, $installedId, $reason, $actorId): void {
          // Pause every package-owned webhook endpoint FIRST so the delivery worker /
          // WebhookService::dispatch() stop draining it before we revoke or flip any state.
          $this->suspendDelivery($installedId, 'ineligible:' . $reason);

          $install = $this->installs->find($installedId);
          $packageId = $install !== null ? (int) $install['package_id'] : null;

          foreach ($active as $cred) {
              if ((string) $cred['kind'] === 'api_token' && $cred['api_token_id'] !== null) {
                  // Raw repo revoke: the autonomous lifecycle path carries no admin/reauth.
                  $this->apiTokenRepo->revoke((int) $cred['api_token_id']);
              }
              $this->credentials->markRevoked((int) $cred['id']);
              if ($packageId !== null) {
                  $this->history->record([
                      'package_id' => $packageId,
                      'installed_package_id' => $installedId,
                      'event' => 'credential_revoke',
                      'actor_id' => $actorId,
                      'detail' => $reason . ':' . (string) $cred['kind'],
                  ]);
              }
          }
      });
  }
  ```
  No new imports needed — `$this->credentials`, `$this->apiTokenRepo`, `$this->installs`, `$this->history`, `$this->db`, and `suspendDelivery` are all already in scope per the locked constructor.

- [ ] **Step 5: Run the tests — expect PASS.**
  ```bash
  vendor/bin/phpunit --filter 'test_on_install_ineligible' tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `OK (2 tests, N assertions)`.

- [ ] **Step 6: Commit.**
  ```bash
  git add src/Service/Packages/PackageIntegrationService.php tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "feat(packages): revoke tokens and pause webhooks on onInstallIneligible teardown"
  ```

---

### Task 37: Wire teardown + grant reconciliation into lifecycle, health, and update paths

Appends the nullable `?PackageIntegrationService $integrations` seam to `PackageLifecycleService`, `PackageHealthService`, and `PackageUpdateService`. Lifecycle/health calls `onInstallIneligible` inside the same transaction immediately before state flips inactive. Update/rollback calls `onGrantsChanged` inside the same activation transaction immediately after `replaceWithGrants()` changes the permission rows. Cross-service tests prove each ineligible transition revokes tokens + pauses webhooks, and that update/rollback permission reductions revoke only stale credentials before the new grant set can be used. Existing hand-built lifecycle/health/update tests stay green because they pass `null` for the new seam.

**Files:**
- Modify: `src/Service/Packages/PackageLifecycleService.php` (constructor param + 2 call sites)
- Modify: `src/Service/Packages/PackageHealthService.php` (constructor param + 2 call sites)
- Modify: `src/Service/Packages/PackageUpdateService.php` (constructor param + update/rollback activation hook)
- Modify: `src/Service/Packages/PackageIntegrationService.php` (`onGrantsChanged` + no-audit granted-scope/event/host helpers)
- Modify: `src/Core/App.php` (append `$c->get(PackageIntegrationService::class)` to the `PackageLifecycleService`, `PackageUpdateService`, and `PackageHealthService` binds)
- Test: `tests/Integration/Service/PackageIntegrationServiceTest.php` (append `healthWith()`/`updateWith()` builders + 6 cross-service tests)
- Test (verify-green, no edit): `tests/Integration/Service/PackageLifecycleServiceTest.php`, `tests/Integration/Worker/PackageHealthWorkerTest.php`, existing `PackageUpdateService` tests

**Interfaces:**
- Consumes: `PackageIntegrationService::onInstallIneligible(int, string, ?int): void` (Task 36); `PackageIntegrationService::onGrantsChanged(int, string, ?int): int`; existing private `PackageHealthService::quarantine`/`securityDisable`; existing lifecycle `disable`/`uninstall`; existing update/rollback path where `PackageUpdateService` replaces granted permission rows.
- Produces (constructor deltas — appended as the last param, all trailing params already optional so existing callers stay valid):
  ```php
  // PackageLifecycleService::__construct( … , ?Telemetry $telemetry = null, ?ThemeStateService $themes = null, ?PackageIntegrationService $integrations = null )
  // PackageHealthService::__construct(    … , ?Telemetry $telemetry = null, ?ThemeStateService $themes = null, ?PackageIntegrationService $integrations = null )
  // PackageUpdateService::__construct(    … , ?PackageIntegrationService $integrations = null )
  ```
- These services live in `App\Service\Packages`, same namespace as `PackageIntegrationService`, so **no `use` import is required** for the seam type.

Steps:

- [ ] **Step 1: Write the failing cross-service tests.** Append to `tests/Integration/Service/PackageIntegrationServiceTest.php`. First a health-service builder that injects the seam (mirrors `PackageHealthWorkerTest`'s build, adding the new trailing arg):
  ```php
  private function healthWith(PackageIntegrationService $integrations): PackageHealthService
  {
      return new PackageHealthService(
          $this->db,
          new InstalledPackageRepository($this->db),
          new InstalledPackagePermissionRepository($this->db),
          new PackageRepository($this->db),
          new PackageReleaseRepository($this->db),
          new PackageAdvisoryRepository($this->db),
          new LocalPackageBlockRepository($this->db),
          new PackageHistoryRepository($this->db),
          new PackageTransparencyLogRepository($this->db),
          $this->store,
          new ModerationLogRepository($this->db),
          null,           // ?Telemetry
          null,           // ?ThemeStateService
          $integrations,  // NEW seam under test
      );
  }
  ```
  Then a small assertion helper and the six transition/reconciliation tests (disable + uninstall exercise the real container-wired lifecycle service the provisioning fixture builds as `$this->lifecycle()`; quarantine + force-disable drive the worker paths; update/rollback exercise `PackageUpdateService` after it replaces grants):
  ```php
  private function assertCredentialsToreDown(int $tokenId, int $hookId): void
  {
      self::assertNotNull((new ApiTokenRepository($this->db))->findById($tokenId)['revoked_at'], 'token revoked');
      self::assertSame(0, (int) (new WebhookRepository($this->db))->findById($hookId)['is_active'], 'webhook paused');
  }

  public function test_disable_tears_down_package_credentials(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      $ids = $this->provisionVia($svc, $installedId);

      $this->lifecycle($svc)->disable($this->admin(), $installedId);

      $this->assertCredentialsToreDown($ids['api_token_id'], $ids['webhook_id']);
  }

  public function test_uninstall_tears_down_package_credentials(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      $ids = $this->provisionVia($svc, $installedId);

      $this->lifecycle($svc)->uninstall($this->admin(), 'password123', $installedId);

      $this->assertCredentialsToreDown($ids['api_token_id'], $ids['webhook_id']);
  }

  public function test_quarantine_tears_down_package_credentials(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      $ids = $this->provisionVia($svc, $installedId);

      $digest = (string) (new InstalledPackageRepository($this->db))->find($installedId)['digest'];
      file_put_contents($this->store->pathFor($digest), 'tampered');
      $this->healthWith($svc)->checkAll();

      self::assertSame('quarantined', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
      $this->assertCredentialsToreDown($ids['api_token_id'], $ids['webhook_id']);
  }

  public function test_force_disable_tears_down_package_credentials(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstall();
      $ids = $this->provisionVia($svc, $installedId);

      $digest = (string) (new InstalledPackageRepository($this->db))->find($installedId)['digest'];
      (new LocalPackageBlockRepository($this->db))->add($digest, null, 'incident', null);
      $this->healthWith($svc)->enforcePolicy();

      $this->assertCredentialsToreDown($ids['api_token_id'], $ids['webhook_id']);
  }

  public function test_update_permission_reduction_revokes_token_with_removed_scope(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstallWithReleasePermissions([
          ['kind' => 'api_scope', 'key' => 'read:boards'],
          ['kind' => 'api_scope', 'key' => 'read:threads'],
      ]);
      $ids = $this->provisionVia($svc, $installedId);

      $targetReleaseId = $this->releaseForSamePackageWithPermissions($installedId, [
          ['kind' => 'api_scope', 'key' => 'read:boards'],
      ]);
      $this->updateWith($svc)->update($this->admin(), 'password123', $installedId, $targetReleaseId);

      self::assertNotNull((new ApiTokenRepository($this->db))->findById($ids['api_token_id'])['revoked_at']);
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId));
  }

  public function test_update_permission_reduction_revokes_webhook_with_removed_event_or_host(): void
  {
      $svc = $this->service();
      $installedId = $this->integrableInstallWithReleasePermissions([
          ['kind' => 'event', 'key' => 'topic.created'],
          ['kind' => 'outbound_host', 'key' => 'hooks.acme.test'],
      ]);
      $ids = $this->provisionVia($svc, $installedId);

      $targetReleaseId = $this->releaseForSamePackageWithPermissions($installedId, [
          ['kind' => 'event', 'key' => 'topic.created'],
          ['kind' => 'outbound_host', 'key' => 'other.example'],
      ]);
      $this->updateWith($svc)->update($this->admin(), 'password123', $installedId, $targetReleaseId);

      self::assertSame(0, (int) (new WebhookRepository($this->db))->findById($ids['webhook_id'])['is_active']);
      self::assertSame([], (new InstalledPackageCredentialRepository($this->db))->activeForInstall($installedId));
  }
  ```
  `$this->lifecycle(PackageIntegrationService $i): PackageLifecycleService` and `$this->updateWith(PackageIntegrationService $i): PackageUpdateService` are fixture builders the provisioning/update tests provide; if either does not yet accept the seam, extend it to pass `$i` as the trailing arg in the same step.

- [ ] **Step 2: Run — expect FAIL (seam not injected yet).**
  ```bash
  vendor/bin/phpunit --filter 'tears_down_package_credentials|permission_reduction_revokes' tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: 6 failures — token `revoked_at` is `null`, webhook `is_active` is `1`, or stale credentials remain active after update, because lifecycle/health/update services still receive `null` for `$integrations` and never call the hooks.

- [ ] **Step 3: Add the seam to `PackageLifecycleService`.** In `src/Service/Packages/PackageLifecycleService.php`, append the constructor param after `?ThemeStateService $themes = null,`:
  ```php
          private ?ThemeStateService $themes = null,
          private ?PackageIntegrationService $integrations = null,
  ```

- [ ] **Step 4: Call the hook inside the `disable()` transaction before state flips.** In `disable()`, insert the call as the first write inside the existing `$this->db->transaction(...)` that sets state to `disabled`. This preserves the design rule "revokes the token before any package state is marked inactive" while keeping teardown + state transition atomic:
  ```php
          $package = $this->packages->find((int) $install['package_id']);

          $this->db->transaction(function () use ($install, $installedId, $admin): void {
              // Revoke package-owned credentials + pause delivery in the same transaction,
              // immediately before the state flips inactive.
              $this->integrations?->onInstallIneligible($installedId, 'disabled', $admin->id());
              $this->installs->setState($installedId, 'disabled');
  ```

- [ ] **Step 5: Call the hook inside the `uninstall()` transaction before state flips.** In `uninstall()`, insert the call as the first write inside the existing `$export = $this->db->transaction(...)` block that disables + marks uninstalled:
  ```php
          $retentionDays = $this->retentionDaysFor($install);

          $export = $this->db->transaction(function () use ($install, $installedId, $package, $admin, $retentionDays): array {
              // Tear down package-owned credentials in the same transaction,
              // immediately before the install transitions to uninstalled.
              $this->integrations?->onInstallIneligible($installedId, 'uninstalled', $admin->id());
  ```

- [ ] **Step 6: Add the seam to `PackageHealthService`.** In `src/Service/Packages/PackageHealthService.php`, append the constructor param after `?ThemeStateService $themes = null,`:
  ```php
          private ?ThemeStateService $themes = null,
          private ?PackageIntegrationService $integrations = null,
  ```

- [ ] **Step 7: Call the health hook inside the same transaction before `setStateIfCurrent()`.** In `PackageHealthService::quarantine()` and `securityDisable()`, add the integration hook at the top of the existing transaction, immediately before the compare-and-set state flip. Do not add it after the transaction beside the theme hook; that would leave a committed inactive install with still-active credentials if the hook fails.
  ```php
      private function securityDisable(array $install, string $reason): bool
      {
          $changed = $this->db->transaction(function () use ($install, $reason): bool {
              $this->integrations?->onInstallIneligible((int) $install['id'], 'force_disabled', null);
              if (!$this->installs->setStateIfCurrent((int) $install['id'], (string) $install['state'], 'disabled')) {
                  return false;
              }
              // existing history/transparency/audit writes stay here
  ```
  ```php
      private function quarantine(array $install, string $reason): bool
      {
          $changed = $this->db->transaction(function () use ($install, $reason): bool {
              $this->integrations?->onInstallIneligible((int) $install['id'], 'quarantined', null);
              if (!$this->installs->setStateIfCurrent((int) $install['id'], (string) $install['state'], 'quarantined')) {
                  return false;
              }
              // existing health/history/transparency/audit writes stay here
  ```
  Keep the existing `$this->themes?->onInstallIneligible(...)` line where it already is unless the theme service is independently changed to be transaction-safe.

- [ ] **Step 8: Implement `PackageIntegrationService::onGrantsChanged`.** This hook is called after permission rows are replaced. It revokes only active credentials whose recorded scopes/events/host are no longer a subset of the current grants:
  ```php
  public function onGrantsChanged(int $installedId, string $reason, ?int $actorId): int
  {
      $install = $this->installs->find($installedId);
      if ($install === null) {
          return 0;
      }

      $allowedScopes = array_flip($this->currentGrantedApiScopes($installedId));
      $allowedEvents = array_flip($this->currentGrantedEvents($installedId));
      $allowedHosts = array_flip($this->currentGrantedOutboundHosts($installedId));
      $revoked = 0;

      foreach ($this->credentials->activeForInstall($installedId) as $cred) {
          $stale = false;
          if ((string) $cred['kind'] === 'api_token') {
              $scopes = $this->decodeList($cred['scopes_json'] ?? null);
              $stale = array_diff($scopes, array_keys($allowedScopes)) !== [];
              if ($stale && $cred['api_token_id'] !== null) {
                  $this->apiTokenRepo->revoke((int) $cred['api_token_id']);
              }
          } elseif ((string) $cred['kind'] === 'webhook') {
              $events = $this->decodeList($cred['events_json'] ?? null);
              $stale = array_diff($events, array_keys($allowedEvents)) !== [];
              if (!$stale && $cred['webhook_id'] !== null) {
                  $hook = $this->webhookRepo->findById((int) $cred['webhook_id']);
                  $host = $hook !== null ? (string) parse_url((string) $hook['url'], PHP_URL_HOST) : '';
                  $stale = $host === '' || !isset($allowedHosts[strtolower($host)]);
              }
              if ($stale && $cred['webhook_id'] !== null) {
                  $this->webhookRepo->disable((int) $cred['webhook_id'], substr('Package grant changed: ' . $reason, 0, 190));
              }
          }

          if ($stale && $this->credentials->markRevoked((int) $cred['id']) === 1) {
              $revoked++;
              $this->history->record([
                  'package_id' => (int) $install['package_id'],
                  'installed_package_id' => $installedId,
                  'event' => 'credential_revoke',
                  'actor_id' => $actorId,
                  'detail' => json_encode(['kind' => (string) $cred['kind'], 'credential_id' => (int) $cred['id'], 'reason' => $reason], JSON_UNESCAPED_SLASHES),
              ]);
          }
      }

      return $revoked;
  }

  /** @return list<string> */
  private function currentGrantedApiScopes(int $installedId): array
  {
      $scopes = [];
      foreach ($this->permissions->forInstall($installedId) as $row) {
          if ((string) $row['kind'] === 'api_scope' && (int) $row['declared'] === 1 && (int) $row['granted'] === 1 && ApiScopes::isValid((string) $row['permission_key'])) {
              $scopes[] = (string) $row['permission_key'];
          }
      }
      return array_values(array_unique($scopes));
  }
  ```
  Add matching `currentGrantedEvents()` and `currentGrantedOutboundHosts()` helpers using the webhook provisioning task's event/host validation rules, and a small `decodeList()` helper that returns `[]` on invalid JSON. Reuse existing helpers if they already exist, but make sure this path does not emit `package_scope_denied` audit rows; reconciliation is not an admin scope-denial event.

- [ ] **Step 9: Add the seam to `PackageUpdateService` and call `onGrantsChanged` inside `activate()`.** Append the nullable constructor property after `?Telemetry $telemetry = null,`:
  ```php
          private ?Telemetry $telemetry = null,
          private ?PackageIntegrationService $integrations = null,
  ```
  Then, in the private `activate()` transaction, call the hook immediately after `replaceWithGrants()` and before history/transparency writes:
  ```php
              $this->permissions->replaceWithGrants($installedId, $rows, $admin->id());
              $this->integrations?->onGrantsChanged($installedId, $event . ':grants_changed', $admin->id());
  ```
  This keeps update/rollback, grant replacement, and credential revocation atomic. If the hook throws, the release activation and permission replacement roll back with it.

- [ ] **Step 10: Append the collaborator to the three container binds.** In `src/Core/App.php`, add `$c->get(PackageIntegrationService::class)` as the trailing arg for `PackageLifecycleService`, `PackageUpdateService`, and `PackageHealthService`:
  ```php
              $c->get(ThemeStateService::class),
              $c->get(PackageIntegrationService::class),
          ));
  ```
  For `PackageUpdateService`, append it after `$c->get(Telemetry::class),`. `PackageIntegrationService::class` is already imported + bound by the provisioning task; there is no dependency cycle.

- [ ] **Step 11: Run the cross-service tests — expect PASS.**
  ```bash
  vendor/bin/phpunit --filter 'tears_down_package_credentials|permission_reduction_revokes' tests/Integration/Service/PackageIntegrationServiceTest.php
  ```
  Expected: `OK (6 tests, N assertions)`.

- [ ] **Step 12: Prove the null seam keeps the hand-built lifecycle/health/update suites green.** These tests build services positionally and stop before the new optional param, so they must pass unchanged:
  ```bash
  vendor/bin/phpunit tests/Integration/Service/PackageLifecycleServiceTest.php \
                     tests/Integration/Worker/PackageHealthWorkerTest.php \
                     tests/Integration/Service/PackageUninstallTest.php \
                     tests/Integration/Service/PackageUpdateServiceTest.php
  ```
  Expected: `OK` for all four (the appended trailing `?PackageIntegrationService = null` default is used outside container-built paths).

- [ ] **Step 13: Commit.**
  ```bash
  git add src/Service/Packages/PackageLifecycleService.php src/Service/Packages/PackageHealthService.php \
          src/Service/Packages/PackageUpdateService.php src/Service/Packages/PackageIntegrationService.php \
          src/Core/App.php tests/Integration/Service/PackageIntegrationServiceTest.php
  git commit -m "feat(packages): reconcile package credentials on lifecycle and grant changes"
  ```

---

### Task 38: `exportSettings` — redacted settings + credential attribution download (no plaintext)

Locks the `AdminPackageIntegrationController::exportSettings` behavior: a CSRF-protected POST that returns a downloadable JSON snapshot of the install's non-secret setting values, secret-field `has_value` flags (never plaintext), and credential attribution (id/kind/label/status/scopes/events/timestamps — never a token or webhook secret). Mirrors the existing `AdminPackageLifecycleController::export` download shape. Advances GA-DOD-08 (export). **Execution order:** Task 42 owns controller creation. If executing this task before Task 42, add the tests now, carry the implementation body below into Task 42, and return here for the PASS run after Task 42 lands.

**Files:**
- Modify: `src/Controller/AdminPackageIntegrationController.php` (use the exact `exportSettings` body below when Task 42 creates the controller; do not add a competing reduced export implementation)
- Test: `tests/Integration/Core/AppPackageIntegrationTest.php` (append full-kernel redaction + flag-dark tests)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (add the export route to the `package_registry`-dark list)
- Test: `tests/browser/package-integrations.spec.ts` (no-JS export + axe evidence)

**Interfaces:**
- Consumes (implemented by earlier tasks): `PackageIntegrationService::overview(int $installedId): array` (already redacted — `credentials:list<{id,kind,label,status,scopes,events,created_at,revoked_at}>`, `settings_summary`, `granted_scopes`, `granted_events`); `PackageSettingsService::describe(int $installedId): array` (`fields`, `values`, `has_secret:array<string,bool>` — secrets report `has_value`, never plaintext); base `Controller` helpers `requireAdmin()`, `gate()`, `noindex()`, `requireIntegrationInstall()`; `Response::json(mixed,int)` + `Response::header(string,string)`.
- Produces: `AdminPackageIntegrationController::exportSettings(Request $request, array $params): Response` returning `Response::json($export)` with `Content-Disposition: attachment`.
- Route (registered by Task 42): `POST /admin/packages/{id}/integration/export` → `[AdminPackageIntegrationController::class, 'exportSettings']`.

Steps:

- [ ] **Step 1: Write the failing redaction test.** Append to `tests/Integration/Core/AppPackageIntegrationTest.php`, reusing the fixture's provisioned-install seeder (`seedProvisionedInstall()` returning package id + install id + the one-time plaintext, established by the integration-controller task):
  ```php
  public function test_export_settings_returns_attribution_without_plaintext(): void
  {
      $this->actingAs($this->makeAdmin());
      $this->setFlags(['package_registry' => true, 'service_secrets' => true]);
      ['package_id' => $packageId, 'token_plaintext' => $token, 'webhook_secret' => $secret] = $this->seedProvisionedInstall();

      $response = $this->post('/admin/packages/' . $packageId . '/integration/export', []);

      self::assertSame(200, $response->status());
      self::assertStringContainsString('application/json', $response->getHeader('content-type'));
      self::assertStringContainsString('attachment', $response->getHeader('content-disposition'));

      $body = $response->body();
      $export = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
      self::assertArrayHasKey('settings', $export);
      self::assertArrayHasKey('credentials', $export);
      self::assertNotSame([], $export['credentials'], 'credential attribution is present');
      // Redaction: no minted token or webhook secret may ever appear in the export bytes.
      self::assertStringNotContainsString($token, $body, 'api token plaintext must not be exported');
      self::assertStringNotContainsString($secret, $body, 'webhook secret must not be exported');
      self::assertStringNotContainsString('svcsec_', $body, 'secret refs are not part of the operator export');
  }

  public function test_export_settings_is_dark_without_flag(): void
  {
      $this->actingAs($this->makeAdmin());
      // package_registry stays default-off.
      $this->assertStatus(404, $this->post('/admin/packages/1/integration/export', []));
  }
  ```

- [ ] **Step 2: Run — expect FAIL.**
  ```bash
  vendor/bin/phpunit --filter 'test_export_settings' tests/Integration/Core/AppPackageIntegrationTest.php
  ```
  Expected: `test_export_settings_returns_attribution_without_plaintext` fails/errors (action not implemented → 404 or 500); the dark test may already pass if the route is flag-gated but keep both.

- [ ] **Step 3: Implement `exportSettings`.** In `src/Controller/AdminPackageIntegrationController.php` add the action (mirrors `AdminPackageLifecycleController::export`, assembling from the two already-redacted read models — no plaintext, no secret refs cross the boundary):
  ```php
  /** @param array<string,string> $params */
  public function exportSettings(Request $request, array $params): Response
  {
      $this->gate();
      $this->requireAdmin();
      $this->gate();
      $packageId = (int) ($params['id'] ?? 0);
      $install = $this->requireIntegrationInstall($packageId);
      $installedId = (int) $install['id'];

      $overview = $this->integrations()->overview($installedId);
      $described = $this->settings()->describe($installedId);

      $export = [
          'exported_at' => gmdate('c'),
          'package_uid' => $overview['settings_summary']['package_uid'] ?? null,
          'type' => $overview['type'],
          'granted_scopes' => $overview['granted_scopes'],
          'granted_events' => $overview['granted_events'],
          'settings' => [
              'values' => $described['values'],          // non-secret values only
              'has_secret' => $described['has_secret'],   // secret fields → bool, never plaintext / never svcsec_*
          ],
          'credentials' => $overview['credentials'],      // id/kind/label/status/scopes/events/timestamps only
      ];

      return $this->noindex(
          Response::json($export)
              ->header('Content-Disposition', 'attachment; filename="package-integration-' . $packageId . '.json"'),
      );
  }
  ```
  `$this->integrations()` / `$this->settings()` are the controller's collaborator accessors established alongside the other integration actions; add them only if missing.

- [ ] **Step 4: Run the redaction test — expect PASS.**
  ```bash
  vendor/bin/phpunit --filter 'test_export_settings' tests/Integration/Core/AppPackageIntegrationTest.php
  ```
  Expected: `OK (2 tests, N assertions)`.

- [ ] **Step 5: Add the export route to the `package_registry`-dark list.** In `tests/Integration/Core/AppFeatureFlagTest.php`, inside `test_package_registry_flag_gates_catalog_and_registry_routes`, add an entry to the `foreach` array (next to `['POST', '/admin/packages/1/export', []]`):
  ```php
              ['POST', '/admin/packages/1/integration/export', []],
  ```

- [ ] **Step 6: Run the flag-dark suite — expect PASS.**
  ```bash
  vendor/bin/phpunit --filter test_package_registry_flag_gates_catalog_and_registry_routes tests/Integration/Core/AppFeatureFlagTest.php
  ```
  Expected: `OK (1 test, N assertions)` — the export route returns 404 (not 403/405) while the flag is off.

- [ ] **Step 7: Add no-JS + axe browser evidence for the export control.** In `tests/browser/package-integrations.spec.ts` add a test that, with `package_registry` seeded on and a provisioned integration install, submits the plain `<form method="post" action="/admin/packages/{id}/integration/export">` (a real submit, JS disabled), asserts the response is a JSON attachment, and runs an axe scan on the integration panel with zero serious/critical violations:
  ```ts
  test('integration settings export is a no-JS form download and passes axe', async ({ page }) => {
    await page.goto(`${base}/admin/packages/${installedPackageId}`);
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? '')).length).toBe(0);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: /export settings/i }).click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/package-integration-\d+\.json/);
  });
  ```

- [ ] **Step 8: Run the browser spec (no-JS project) to capture evidence.**
  ```bash
  cd tests/browser && npx playwright test package-integrations.spec.ts --project=chromium
  ```
  Expected: the export test passes; PNG/trace evidence lands under the spec's evidence output dir.

- [ ] **Step 9: Full suite gate + commit.**
  ```bash
  composer test
  git add src/Controller/AdminPackageIntegrationController.php tests/Integration/Core/AppPackageIntegrationTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/browser/package-integrations.spec.ts
  git commit -m "feat(packages): redacted integration settings and credential export (no plaintext)"
  ```

---

**Group exit deliverable:** `onInstallIneligible` revokes package-owned tokens and pauses package-owned webhook endpoints (delivery suppressed before egress); it is wired into `disable`/`uninstall` (before state flips inactive) and `quarantine`/`securityDisable` (alongside the theme seam) via the two amended container binds; `exportSettings` emits attribution without any plaintext or secret ref; the hand-built lifecycle/health suites stay green through the `null` seam; the export route is 404-dark without `package_registry`. Advances GA-DOD-08 (disable/uninstall/export).


<!-- ===== group: integration-operator-ux — Operator Integration UX — no-JS forms + browser/a11y ===== -->

### Task 41: Integration panel renders on package detail (settings form + grant summary + credential panel)

**Files:**
- Create: `templates/admin/_package_integration.php`
- Create: `tests/Integration/Core/AppPackageIntegrationTest.php` (render case; extended in Tasks 42–43)
- Modify: `templates/admin/package_detail.php` (render the partial when `$integration` is present)
- Modify: `src/Controller/AdminPackagesController.php` (attach `integration` + `settings_describe` to the detail data for `remote_app`/`automation` installs)
- Test: `tests/Integration/Core/AppPackageIntegrationTest.php`

**Interfaces:**
- Consumes: `PackageIntegrationService::overview(int $installedId): array` → `array{type, integrable:bool, granted_scopes:list<string>, granted_events:list<string>, data_classes:list<array>, outbound_hosts:list<string>, jobs:list<array>, credentials:list<array{id,kind,label,status,scopes:list,events:list,created_at,revoked_at}>, settings_summary:array, execution_disabled:bool, refusal:?array{code,message}}`
- Consumes: `PackageSettingsService::describe(int $installedId): array` → `array{fields:list<array{key,type,label,required,options?,secret:bool}>, values:array<string,mixed>, has_secret:array<string,bool>}`
- Consumes: `RegistryCatalogService::detail(int $packageId): ?array` (existing detail read model)
- Produces: `templates/admin/_package_integration.php` (partial; consumes `integration`, `settings`, `reveal`, `errors`, `base`)

- [ ] **Step 1: Write the failing render test.** Create `tests/Integration/Core/AppPackageIntegrationTest.php` with the setUp harness (mirrors `AppPackageLifecycleTest`), a lifecycle-driven `installEnabledRemoteApp()` helper, and the first assertion that the Integration section renders from the manifest:
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppPackageIntegrationTest extends TestCase
{
    private SigningHarness $root;
    private string $artifactDir;
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages';
        $this->root = SigningHarness::generate();
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    /**
     * Seed and drive a remote_app install to enabled through the real kernel
     * lifecycle so permission rows are genuinely granted and state='enabled'.
     *
     * @param array<string,mixed> $permissions manifest permissions block
     * @param array<string,mixed> $settingsSchema manifest settings_schema block
     * @return array{package_id:int, installed_id:int, base:string}
     */
    private function installEnabledRemoteApp(array $permissions, array $settingsSchema, string $suffix = 'widget'): array
    {
        $seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir, [
            'source_id' => 'rb-remote-' . $suffix,
            'publisher_uid' => 'acme-remote-' . $suffix,
            'publisher_name' => 'Acme Remote',
            'package_uid' => 'acme/remote-' . $suffix,
            'name' => 'Remote ' . ucfirst($suffix),
            'type' => 'remote_app',
            'trust_class' => 'reviewed_remote',
            'release' => ['manifest' => [
                'permissions' => $permissions,
                'settings_schema' => $settingsSchema,
            ]],
        ]);
        (new PackageRegistryRepository($this->db))->setEnabled((int) $seeded['registry_id'], true);

        $base = '/admin/packages/' . $seeded['package_id'];
        $this->post($base . '/install', ['current_password' => 'password123']);
        $this->post($base . '/consent', ['current_password' => 'password123']);
        $this->post($base . '/enable', ['current_password' => 'password123']);

        $installed = (new InstalledPackageRepository($this->db))->findByPackage((int) $seeded['package_id']);
        self::assertIsArray($installed, 'remote_app should be installed');
        self::assertSame('enabled', $installed['state'], 'remote_app should reach enabled state');

        return [
            'package_id' => (int) $seeded['package_id'],
            'installed_id' => (int) $installed['id'],
            'base' => $base,
        ];
    }

    public function test_integration_panel_renders_grant_summary_and_settings_form(): void
    {
        $app = $this->installEnabledRemoteApp(
            [
                'api_scopes' => ['read:boards'],
                'events' => ['topic.created'],
                'outbound_hosts' => ['hooks.example.test'],
                'data_classes' => ['content.public'],
            ],
            ['fields' => [
                ['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'required' => false, 'options' => ['live', 'test']],
            ]],
        );

        $detail = $this->get($app['base']);
        $this->assertStatus(200, $detail);
        self::assertSame('noindex', $detail->getHeader('x-robots-tag'));
        // Copy makes the runtime model explicit.
        $this->assertSeeText($detail, 'runs remotely');
        // Manifest = ceiling, grants = authority: the granted scope/event/host show.
        $this->assertSeeText($detail, 'read:boards');
        $this->assertSeeText($detail, 'topic.created');
        $this->assertSeeText($detail, 'hooks.example.test');
        // Settings form is generated from settings_schema.
        $this->assertSeeText($detail, 'Display name');
        $this->assertSeeText($detail, 'Mode');
        // No credentials yet, and no plaintext anywhere.
        $this->assertSeeText($detail, 'No credentials provisioned');
        $this->assertDontSeeText($detail, 'shown only once');
    }
}
```
- [ ] **Step 2: Run the render test and confirm it FAILS.** `vendor/bin/phpunit --filter test_integration_panel_renders_grant_summary_and_settings_form` — expect FAIL: the assertion `Failed asserting that '…' contains "runs remotely"` because `package_detail.php` renders no integration panel yet.
- [ ] **Step 3: Create the integration partial.** Write `templates/admin/_package_integration.php`:
```php
<?php /** @var \App\Core\View $this */ ?>
<?php
/** @var array<string,mixed> $integration */
/** @var array<string,mixed> $settings */
/** @var array<string,mixed>|null $reveal */
/** @var array<string,string> $errors */
/** @var string $base */
$reveal = $reveal ?? null;
$errors = $errors ?? [];
$hasSecretField = false;
foreach (($settings['fields'] ?? []) as $f) {
    if (!empty($f['secret'])) {
        $hasSecretField = true;
    }
}
$classList = array_map(
    static fn (array $d): string => (string) ($d['permission_key'] ?? $d['key'] ?? $d['label'] ?? ''),
    $integration['data_classes'] ?? [],
);
$jobList = array_map(
    static fn (array $j): string => (string) ($j['permission_key'] ?? $j['key'] ?? $j['label'] ?? ''),
    $integration['jobs'] ?? [],
);
?>
<section class="card" id="integration">
    <h2>Integration</h2>
    <p class="muted">
        This package <?= ($integration['type'] ?? '') === 'remote_app' ? 'runs remotely' : 'runs declaratively' ?>.
        RetroBoards never executes package code in-process — it only exchanges the data these grants allow,
        through the read-only API and package-owned webhooks below.
    </p>

    <?php if (!empty($integration['execution_disabled'])): ?>
        <p class="field-error">Package execution is emergency-disabled site-wide. Credentials cannot authenticate and delivery is paused until an operator re-enables execution.</p>
    <?php endif; ?>
    <?php if (($integration['refusal'] ?? null) !== null): ?>
        <p class="field-error"><?= $e($integration['refusal']['code'] . ': ' . $integration['refusal']['message']) ?></p>
    <?php endif; ?>

    <h3>Granted permissions</h3>
    <table class="audit">
        <tbody>
            <tr><th>API scopes</th><td><?= ($integration['granted_scopes'] ?? []) ? $e(implode(', ', $integration['granted_scopes'])) : 'none' ?></td></tr>
            <tr><th>Webhook events</th><td><?= ($integration['granted_events'] ?? []) ? $e(implode(', ', $integration['granted_events'])) : 'none' ?></td></tr>
            <tr><th>Outbound hosts</th><td><?= ($integration['outbound_hosts'] ?? []) ? $e(implode(', ', $integration['outbound_hosts'])) : 'none' ?></td></tr>
            <tr><th>Data classes</th><td><?= $classList ? $e(implode(', ', array_filter($classList))) : 'none' ?></td></tr>
            <tr><th>Jobs (consent metadata only)</th><td><?= $jobList ? $e(implode(', ', array_filter($jobList))) : 'none' ?></td></tr>
        </tbody>
    </table>

    <h3>Settings</h3>
    <?php if (empty($settings['fields'])): ?>
        <p class="muted">This package declares no configurable settings.</p>
    <?php else: ?>
    <form method="post" action="<?= $e($base) ?>/integration/settings">
        <?= $this->csrfField() ?>
        <?php foreach ($settings['fields'] as $field): $key = (string) $field['key']; ?>
            <label class="field">
                <span><?= $e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></span>
                <?php if (($field['type'] ?? '') === 'select'): ?>
                    <select name="<?= $e($key) ?>">
                        <?php foreach (($field['options'] ?? []) as $opt): ?>
                            <option value="<?= $e($opt) ?>"<?= (string) ($settings['values'][$key] ?? '') === (string) $opt ? ' selected' : '' ?>><?= $e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (!empty($field['secret'])): ?>
                    <input type="password" name="<?= $e($key) ?>" autocomplete="new-password"
                           placeholder="<?= !empty($settings['has_secret'][$key]) ? 'stored — leave blank to keep' : 'not set' ?>">
                <?php else: ?>
                    <input type="text" name="<?= $e($key) ?>" value="<?= $e((string) ($settings['values'][$key] ?? '')) ?>">
                <?php endif; ?>
            </label>
            <?php if (isset($errors[$key])): ?><p class="field-error"><?= $e($errors[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($hasSecretField): ?>
            <label class="field"><span>Confirm your password</span><input type="password" name="current_password" autocomplete="current-password"></label>
            <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
        <?php endif; ?>
        <button type="submit">Save settings</button>
    </form>
    <?php endif; ?>

    <h3>Package-owned credentials</h3>
    <?php if ($reveal !== null): ?>
        <div class="card reveal">
            <p><strong>Copy these now — they are shown only once.</strong></p>
            <?php if (!empty($reveal['api_token'])): ?><p>API token: <code><?= $e($reveal['api_token']) ?></code></p><?php endif; ?>
            <?php if (!empty($reveal['webhook_secret'])): ?><p>Webhook signing secret: <code><?= $e($reveal['webhook_secret']) ?></code></p><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php foreach (['settings', 'provision', 'rotate', 'revoke'] as $slot): ?>
        <?php if (isset($errors[$slot])): ?><p class="field-error"><?= $e($errors[$slot]) ?></p><?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($integration['credentials'])): ?>
        <p class="muted">No credentials provisioned.</p>
    <?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Package credentials">
    <table class="audit">
        <thead><tr><th>Label</th><th>Kind</th><th>Status</th><th>Scopes / events</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($integration['credentials'] as $cred): ?>
            <tr>
                <td><?= $e($cred['label']) ?></td>
                <td><?= $e($cred['kind']) ?></td>
                <td><?= $e($cred['status']) ?></td>
                <td><?= $e(implode(', ', $cred['scopes'] ?: $cred['events'])) ?></td>
                <td>
                    <?php if ($cred['status'] !== 'revoked'): ?>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/rotate" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="password" name="current_password" placeholder="password" autocomplete="current-password">
                            <button type="submit">Rotate</button>
                        </form>
                        <form method="post" action="<?= $e($base) ?>/integration/credentials/<?= (int) $cred['id'] ?>/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <button type="submit">Revoke</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div class="integration-actions">
        <?php if (!empty($integration['integrable']) && ($integration['refusal'] ?? null) === null && empty($integration['execution_disabled'])): ?>
        <form method="post" action="<?= $e($base) ?>/integration/provision" class="inline">
            <?= $this->csrfField() ?>
            <label class="field"><span>Confirm password</span><input type="password" name="current_password" autocomplete="current-password"></label>
            <button type="submit">Provision credentials</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= $e($base) ?>/integration/disable" class="inline">
            <?= $this->csrfField() ?>
            <button type="submit">Pause delivery</button>
        </form>
        <form method="post" action="<?= $e($base) ?>/integration/export" class="inline">
            <?= $this->csrfField() ?>
            <button type="submit">Export settings</button>
        </form>
    </div>
</section>
```
- [ ] **Step 4: Render the partial from `package_detail.php`.** Add this block just before the closing `</div>` of `.admin-pane` in `templates/admin/package_detail.php` (guarded on `$integration` presence so existing lifecycle re-renders that omit it are unaffected):
```php
    <?php if (($integration ?? null) !== null): ?>
        <?= $this->partial('admin/_package_integration', [
            'integration' => $integration,
            'settings' => $settings_describe ?? ['fields' => [], 'values' => [], 'has_secret' => []],
            'reveal' => $reveal ?? null,
            'errors' => $errors ?? [],
            'base' => $base,
        ]) ?>
    <?php endif; ?>
```
- [ ] **Step 5: Feed integration data from the canonical GET.** In `src/Controller/AdminPackagesController.php`, add imports `use App\Service\Packages\PackageIntegrationService;` and `use App\Service\Packages\PackageSettingsService;`, then in `show()` after `$detail['permission_labels'] = …;` and before the `return`, attach the panel data for integration types:
```php
        if ($installed !== null
            && in_array((string) ($detail['package']['type'] ?? ''), ['remote_app', 'automation'], true)) {
            $installedId = (int) $installed['id'];
            $detail['integration'] = $this->container->get(PackageIntegrationService::class)->overview($installedId);
            $detail['settings_describe'] = $this->container->get(PackageSettingsService::class)->describe($installedId);
        }
```
- [ ] **Step 6: Run the render test and confirm it PASSES.** `vendor/bin/phpunit --filter test_integration_panel_renders_grant_summary_and_settings_form` — expect `OK (1 test)`. Then `vendor/bin/phpunit tests/Integration/Core/AppPackageLifecycleTest.php` to confirm the guarded partial did not regress existing detail re-renders — expect green.
- [ ] **Step 7: Commit.**
```bash
git add templates/admin/_package_integration.php templates/admin/package_detail.php \
        src/Controller/AdminPackagesController.php tests/Integration/Core/AppPackageIntegrationTest.php
git commit -m "$(cat <<'EOF'
feat(packages): render remote_app/automation integration panel on package detail

Fold an Integration section (grant summary, settings form from settings_schema,
credential panel) into package detail for remote_app/automation installs, fed by
PackageIntegrationService::overview + PackageSettingsService::describe. Deploy-dark
behind package_registry. No plaintext ever rendered.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 42: Integration controller + POST routes (settings save, provision/rotate/revoke, pause, export) with reauth, CSRF, noindex, direct-POST denial

**Files:**
- Create: `src/Controller/AdminPackageIntegrationController.php`
- Modify: `src/Core/App.php` (add `use` import + register the six integration routes in `buildRouter()`)
- Test: `tests/Integration/Core/AppPackageIntegrationTest.php` (extend)

**Interfaces:**
- Produces: `App\Controller\AdminPackageIntegrationController` actions `saveSettings`, `provision`, `rotateCredential`, `revokeCredential`, `disableIntegration`, `exportSettings`, each `(Request $request, array $params): Response`.
- Consumes: `PackageSettingsService::save(User $admin, ?string $currentPassword, int $installedId, array $input): void`
- Consumes: `PackageIntegrationService::provisionCredentials(User $admin, string $currentPassword, int $installedId): array{api_token:?string, webhook_secret:?string, credentials:list<array>}`
- Consumes: `PackageIntegrationService::rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array{secret:?string,token:?string}`
- Consumes: `PackageIntegrationService::revokeCredential(User $admin, int $installedId, int $credentialId): void`
- Consumes: `PackageIntegrationService::suspendDelivery(int $installedId, string $reason): int`
- Consumes: `PackageSettingsService::describe(int $installedId): array` (export body)
- Routes (`App::buildRouter()`):
```php
$r->post('/admin/packages/{id}/integration/settings',                       [AdminPackageIntegrationController::class, 'saveSettings']);
$r->post('/admin/packages/{id}/integration/provision',                      [AdminPackageIntegrationController::class, 'provision']);
$r->post('/admin/packages/{id}/integration/credentials/{credentialId}/rotate', [AdminPackageIntegrationController::class, 'rotateCredential']);
$r->post('/admin/packages/{id}/integration/credentials/{credentialId}/revoke', [AdminPackageIntegrationController::class, 'revokeCredential']);
$r->post('/admin/packages/{id}/integration/disable',                        [AdminPackageIntegrationController::class, 'disableIntegration']);
$r->post('/admin/packages/{id}/integration/export',                         [AdminPackageIntegrationController::class, 'exportSettings']);
```

- [ ] **Step 1: Write the failing settings-save test.** Add to `AppPackageIntegrationTest`:
```php
    public function test_no_js_settings_save_persists_and_redisplays_value(): void
    {
        $app = $this->installEnabledRemoteApp(
            ['api_scopes' => ['read:boards']],
            ['fields' => [['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false]]],
            'settings',
        );

        $save = $this->post($app['base'] . '/integration/settings', ['display_name' => 'Acme Bot']);
        $this->assertRedirectContains($save, $app['base']);
        self::assertSame('noindex', $save->getHeader('x-robots-tag'));

        $detail = $this->get($app['base']);
        $this->assertStatus(200, $detail);
        $this->assertSeeText($detail, 'Acme Bot');
    }
```
- [ ] **Step 2: Run it and confirm FAIL.** `vendor/bin/phpunit --filter test_no_js_settings_save_persists_and_redisplays_value` — expect FAIL: the POST 404s (route + controller absent), so `assertRedirectContains` sees a 404 body, not a redirect.
- [ ] **Step 3: Create the controller.** Write `src/Controller/AdminPackageIntegrationController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PermissionDiff;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageSettingsService;
use App\Service\Registry\RegistryCatalogService;

/**
 * No-JS operator surface for animating remote_app / automation installs:
 * per-install settings, package-owned credentials, and defensive pause/export.
 * {id} is packages.id; the install row is resolved server-side so an operator
 * cannot target another row. Deploy-dark behind package_registry: gate() throws
 * NotFoundException (404, never 403/405) before AND after requireAdmin().
 */
final class AdminPackageIntegrationController extends Controller
{
    /** @param array<string,string> $params */
    public function saveSettings(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        $input = $request->allInput();
        unset($input['_token'], $input['current_password']);

        try {
            $this->settings()->save($admin, (string) $request->post('current_password', ''), (int) $install['id'], $input);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['settings' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function provision(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        try {
            $reveal = $this->integrations()->provisionCredentials($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            return $this->detailView($packageId, (int) $install['id'], ['reveal' => $reveal], 200);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['provision' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotateCredential(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $credentialId = (int) ($params['credentialId'] ?? 0);

        try {
            $reveal = $this->integrations()->rotateCredential($admin, (string) $request->post('current_password', ''), (int) $install['id'], $credentialId);
            return $this->detailView($packageId, (int) $install['id'], ['reveal' => $reveal], 200);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => $e->errors], 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['rotate' => $this->policyMessage($e)]], 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokeCredential(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $credentialId = (int) ($params['credentialId'] ?? 0);

        try {
            $this->integrations()->revokeCredential($admin, (int) $install['id'], $credentialId);
            return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, (int) $install['id'], ['errors' => ['revoke' => $this->policyMessage($e)]], 422);
        }
    }

    /** Friction-free defensive pause of all package-owned delivery — no reauth. @param array<string,string> $params */
    public function disableIntegration(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);

        $this->integrations()->suspendDelivery((int) $install['id'], 'operator paused integration delivery');
        return $this->noindex($this->redirect('/admin/packages/' . $packageId . '#integration'));
    }

    /** @param array<string,string> $params */
    public function exportSettings(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireIntegrationInstall($packageId);
        $installedId = (int) $install['id'];

        $overview = $this->integrations()->overview($installedId);
        $described = $this->settings()->describe($installedId);
        $export = [
            'exported_at' => gmdate('c'),
            'package_uid' => $overview['settings_summary']['package_uid'] ?? null,
            'type' => $overview['type'],
            'granted_scopes' => $overview['granted_scopes'],
            'granted_events' => $overview['granted_events'],
            'settings' => [
                'values' => $described['values'],
                'has_secret' => $described['has_secret'],
            ],
            'credentials' => $overview['credentials'],
        ];

        return $this->noindex(
            Response::json($export)
                ->header('Content-Disposition', 'attachment; filename="package-integration-' . $packageId . '.json"'),
        );
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }

    private function settings(): PackageSettingsService
    {
        return $this->container->get(PackageSettingsService::class);
    }

    private function integrations(): PackageIntegrationService
    {
        return $this->container->get(PackageIntegrationService::class);
    }

    /**
     * Resolve the install for {id} and refuse any non-integration package type
     * (theme/server_extension) as 404 — the integration surface does not exist.
     *
     * @return array<string,mixed>
     */
    private function requireIntegrationInstall(int $packageId): array
    {
        $package = $this->container->get(PackageRepository::class)->find($packageId);
        if ($package === null) {
            throw new NotFoundException('Package not found.');
        }
        $install = $this->container->get(InstalledPackageRepository::class)->findByPackage($packageId);
        if ($install === null || !in_array((string) $package['type'], ['remote_app', 'automation'], true)) {
            throw new NotFoundException('Package has no integration surface.');
        }
        return $install;
    }

    /**
     * Re-render package detail (with the integration panel) at $status, carrying
     * either field errors or a one-time reveal. Mirrors the lifecycle
     * controller's detailView so the anti-draft-loss re-render keeps context.
     *
     * @param array{errors?:array<string,string>, reveal?:array<string,mixed>} $extra
     */
    private function detailView(int $packageId, int $installedId, array $extra, int $status): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }
        $detail['permission_labels'] = $this->permissionRows($detail['installed_permissions'] ?? []);
        $detail['integration'] = $this->integrations()->overview($installedId);
        $detail['settings_describe'] = $this->settings()->describe($installedId);
        $detail['reveal'] = $extra['reveal'] ?? null;
        $detail['errors'] = $extra['errors'] ?? [];

        return $this->noindex($this->view('admin/package_detail', $detail, $status));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function permissionRows(array $rows): array
    {
        return array_map(
            static function (array $row): array {
                $key = (string) ($row['permission_key'] ?? $row['key'] ?? '');
                $label = PermissionDiff::describe((string) ($row['kind'] ?? ''), $key);
                return $row + [
                    'permission_key' => $key,
                    'risk_class' => $row['risk_class'] ?? $label['risk'],
                    'label' => $label['label'],
                ];
            },
            $rows,
        );
    }

    private function policyMessage(PackagePolicyException | RegistryVerificationException $e): string
    {
        return (string) $e->code . ': ' . $e->getMessage();
    }
}
```
- [ ] **Step 4: Register the routes.** In `src/Core/App.php`, add `use App\Controller\AdminPackageIntegrationController;` alongside the other admin-package controller imports, then add the six integration routes in `buildRouter()` immediately after the existing `/admin/packages/{id}/reverify` route:
```php
        // Integration runtime (P5-04) — remote_app / automation, deploy-dark behind package_registry.
        $r->post('/admin/packages/{id}/integration/settings',                          [AdminPackageIntegrationController::class, 'saveSettings']);
        $r->post('/admin/packages/{id}/integration/provision',                         [AdminPackageIntegrationController::class, 'provision']);
        $r->post('/admin/packages/{id}/integration/credentials/{credentialId}/rotate', [AdminPackageIntegrationController::class, 'rotateCredential']);
        $r->post('/admin/packages/{id}/integration/credentials/{credentialId}/revoke', [AdminPackageIntegrationController::class, 'revokeCredential']);
        $r->post('/admin/packages/{id}/integration/disable',                           [AdminPackageIntegrationController::class, 'disableIntegration']);
        $r->post('/admin/packages/{id}/integration/export',                            [AdminPackageIntegrationController::class, 'exportSettings']);
```
- [ ] **Step 5: Run the settings-save test and confirm it PASSES.** `vendor/bin/phpunit --filter test_no_js_settings_save_persists_and_redisplays_value` — expect `OK (1 test)`.
- [ ] **Step 6: Write the provision + one-time-reveal + reauth failing test.** Add to `AppPackageIntegrationTest`:
```php
    public function test_provision_reveals_credentials_once_then_lists_by_status(): void
    {
        $app = $this->installEnabledRemoteApp(
            ['api_scopes' => ['read:boards']],   // api_scope-only → deterministic token mint, no webhook URL needed
            ['fields' => []],
            'provision',
        );

        $reveal = $this->post($app['base'] . '/integration/provision', ['current_password' => 'password123']);
        $this->assertStatus(200, $reveal);
        self::assertSame('noindex', $reveal->getHeader('x-robots-tag'));
        $this->assertSeeText($reveal, 'shown only once');

        // Reload: the credential is listed by label/status, and the plaintext is gone (one-time).
        $detail = $this->get($app['base']);
        $this->assertSeeText($detail, 'active');
        $this->assertSeeText($detail, 'read:boards');
        $this->assertDontSeeText($detail, 'shown only once');
    }

    public function test_provision_requires_reauth_and_mints_nothing_on_wrong_password(): void
    {
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'reauth');

        $bad = $this->post($app['base'] . '/integration/provision', ['current_password' => 'wrong-password']);
        $this->assertStatus(422, $bad);
        $this->assertDontSeeText($bad, 'shown only once');

        // No credential landed: the panel still says none provisioned.
        $detail = $this->get($app['base']);
        $this->assertSeeText($detail, 'No credentials provisioned');
    }

    public function test_integration_posts_reject_missing_csrf_token(): void
    {
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'csrf');

        $noToken = $this->post($app['base'] . '/integration/provision', ['current_password' => 'password123'], false);
        $this->assertStatus(403, $noToken);
    }

    public function test_integration_routes_reject_non_integration_package_types(): void
    {
        // A theme install has no integration surface: gate resolves the type and 404s.
        $themePkg = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir, [
            'source_id' => 'rb-theme-x', 'publisher_uid' => 'acme-theme-x',
            'package_uid' => 'acme/theme-x', 'name' => 'Theme X', 'type' => 'theme',
        ]);
        $this->db->insert(
            "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
             VALUES (?, REPEAT('a', 64), 'reviewed_declarative', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [(int) $themePkg['package_id'], (int) $this->admin['id']],
        );

        $this->assertStatus(404, $this->post('/admin/packages/' . $themePkg['package_id'] . '/integration/settings', ['x' => '1']));
        // And an unknown package id is 404 too.
        $this->assertStatus(404, $this->post('/admin/packages/999999/integration/provision', ['current_password' => 'password123']));
    }

    public function test_integration_routes_are_dark_without_the_flag(): void
    {
        $app = $this->installEnabledRemoteApp(['api_scopes' => ['read:boards']], ['fields' => []], 'dark');
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);

        foreach ([
            '/integration/settings',
            '/integration/provision',
            '/integration/credentials/1/rotate',
            '/integration/credentials/1/revoke',
            '/integration/disable',
            '/integration/export',
        ] as $suffix) {
            $this->assertStatus(404, $this->post($app['base'] . $suffix, ['current_password' => 'password123']));
        }
    }
```
- [ ] **Step 7: Run the full test file and confirm all PASS.** `vendor/bin/phpunit tests/Integration/Core/AppPackageIntegrationTest.php` — expect `OK (7 tests)` (render + settings + provision + reauth + csrf + type-denial + flag-dark). The provision/reauth/csrf/type/flag tests were red before this task's routes/controller existed (404/no-controller); confirm each now behaves as asserted.
- [ ] **Step 8: Commit.**
```bash
git add src/Controller/AdminPackageIntegrationController.php src/Core/App.php \
        tests/Integration/Core/AppPackageIntegrationTest.php
git commit -m "$(cat <<'EOF'
feat(packages): integration POST routes for settings + package-owned credentials

Add AdminPackageIntegrationController (saveSettings/provision/rotate/revoke/
disable/export) and register the six /admin/packages/{id}/integration/* routes.
Reauth on provision/rotate via the service layer; friction-free pause/revoke;
one-time server-side credential reveal; noindex, CSRF, 404-while-dark, and
non-integration-type denial all covered.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 43: Lock the integration routes into the deploy-dark flag regression guard

**Files:**
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (extend the `package_registry` dark case with the six integration routes)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php`

**Interfaces:**
- Consumes: existing `AppFeatureFlagTest::test_package_registry_flag_gates_catalog_and_registry_routes` foreach-while-dark table and `setFlags(['package_registry' => false])` helper.
- Produces: additional 404-while-dark assertions for `/admin/packages/1/integration/*`.

- [ ] **Step 1: Add the failing-guard assertions.** In `tests/Integration/Core/AppFeatureFlagTest.php`, extend the `foreach` table inside `test_package_registry_flag_gates_catalog_and_registry_routes` (the block that asserts each lifecycle mutation is 404 while dark) with the integration routes:
```php
            ['POST', '/admin/packages/1/integration/settings', []],
            ['POST', '/admin/packages/1/integration/provision', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/integration/credentials/1/rotate', ['current_password' => 'password123']],
            ['POST', '/admin/packages/1/integration/credentials/1/revoke', []],
            ['POST', '/admin/packages/1/integration/disable', []],
            ['POST', '/admin/packages/1/integration/export', []],
```
- [ ] **Step 2: Run the flag guard and confirm PASS.** `vendor/bin/phpunit --filter test_package_registry_flag_gates_catalog_and_registry_routes` — expect `OK (1 test)`. (With the flag off the in-controller `gate()` fires first, so every integration route 404s exactly like the lifecycle routes; this is the standing regression guard that the routes never leak while dark.)
- [ ] **Step 3: Run the surrounding suite to confirm no collateral.** `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` — expect green.
- [ ] **Step 4: Commit.**
```bash
git add tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "$(cat <<'EOF'
test(packages): assert integration routes stay 404 while package_registry is dark

Extend the package_registry deploy-dark guard with the six
/admin/packages/{id}/integration/* routes so a rollback takes them offline.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 44: Browser + axe evidence for the integration surface (no-JS settings save + one-time credential reveal) and ledger advance

**Files:**
- Create: `tests/browser/package-integrations.spec.ts`
- Modify: `tests/browser/seed.php` (seed an enabled `remote_app` install with granted `read:boards` scope + a `settings_schema` string field, under the dark-surface fixture guard)
- Modify: `tests/browser/package.json` (add the spec to a feature script)
- Modify: `docs/phase5/requirement-ledger.json` (advance `GA-DOD-08` R1→R3/R4 with evidence paths)
- Modify: `PHASE_5_STATUS.md`, `docs/evidence/deploy-dark-features.md`, `docs/runbooks/package_integrations.md` (Inc 5 integration-UX status + evidence pointers)
- Test: `tests/browser/package-integrations.spec.ts`

**Interfaces:**
- Consumes: the live `/admin/packages/{id}` Integration panel (settings form + `Provision credentials` form) and `@axe-core/playwright`.
- Consumes: browser seed helpers (`runPhp`-style direct inserts) matching `tests/browser/seed.php` patterns; `RB_BROWSER_DARK_SURFACES=1` guard.
- Produces: `docs/evidence/browser/<project>/package-integrations-*.png` + an axe pass over the Integration section.

- [ ] **Step 1: Seed a browser-fixture remote_app install.** In `tests/browser/seed.php`, inside the existing `if ($includeDarkSurfaceFixtures) { … }` block (near the `local.browser.extension` insert), add an enabled `remote_app` install with one granted API scope and a settings field, so the panel renders and provisioning mints a token deterministically (api-scope-only → no webhook URL required):
```php
    $db->run(
        "INSERT INTO packages (package_uid, name, type, trust_class, created_at, updated_at)
         VALUES ('local.browser.remote', 'Browser Remote App', 'remote_app', 'reviewed_remote', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = UTC_TIMESTAMP()",
    );
    $remotePkgId = (int) $db->fetchValue("SELECT id FROM packages WHERE package_uid = 'local.browser.remote'");
    $remoteManifest = json_encode([
        'uid' => 'local.browser.remote', 'name' => 'Browser Remote App', 'type' => 'remote_app',
        'permissions' => ['api_scopes' => ['read:boards']],
        'settings_schema' => ['fields' => [
            ['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false],
        ]],
    ], JSON_THROW_ON_ERROR);
    $db->run(
        "INSERT INTO package_releases (package_id, version, digest, license, manifest_json, review_status, channel, advisory_status, published_at)
         VALUES (?, '1.0.0', REPEAT('d', 64), 'MIT', ?, 'approved', 'stable', 'none', UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE manifest_json = VALUES(manifest_json)",
        [$remotePkgId, $remoteManifest],
    );
    $remoteReleaseId = (int) $db->fetchValue("SELECT id FROM package_releases WHERE package_id = ? ORDER BY id DESC LIMIT 1", [$remotePkgId]);
    $db->run(
        "INSERT INTO installed_packages (package_id, release_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
         VALUES (?, ?, REPEAT('d', 64), 'reviewed_remote', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE release_id = VALUES(release_id), state = 'enabled', updated_at = UTC_TIMESTAMP()",
        [$remotePkgId, $remoteReleaseId, (int) $admin['id']],
    );
    $remoteInstalledId = (int) $db->fetchValue('SELECT id FROM installed_packages WHERE package_id = ?', [$remotePkgId]);
    $db->run(
        "INSERT INTO installed_package_permissions (installed_package_id, kind, permission_key, risk_class, declared, granted, granted_at, granted_by)
         VALUES (?, 'api_scope', 'read:boards', 'low', 1, 1, UTC_TIMESTAMP(), ?)
         ON DUPLICATE KEY UPDATE granted = 1, granted_at = UTC_TIMESTAMP()",
        [$remoteInstalledId, (int) $admin['id']],
    );
```
- [ ] **Step 2: Write the browser spec (no-JS settings + one-time reveal + axe).** Create `tests/browser/package-integrations.spec.ts`:
```ts
import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';

async function loginAdmin(page): Promise<void> {
  await page.context().clearCookies(); // avoid the authed-GET-/login redirect trap
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'admin@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
}

async function openIntegrationDetail(page): Promise<void> {
  await page.goto(`${BASE}/admin/packages`);
  await page.getByRole('link', { name: 'Browser Remote App' }).click();
  await expect(page.locator('#integration')).toBeVisible();
}

test.describe('package integration operator surface (P5-04)', () => {
  test('renders grant summary and remote-run copy', async ({ page }) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await expect(page.locator('#integration')).toContainText('runs remotely');
    await expect(page.locator('#integration')).toContainText('read:boards');
    await expect(page.locator('#integration')).toContainText('Display name');
  });

  test('saves a setting with a no-JS form and redisplays it', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await page.fill('#integration input[name="display_name"]', 'Acme Concierge');
    await page.getByRole('button', { name: 'Save settings' }).click();
    await expect(page.locator('#integration input[name="display_name"]')).toHaveValue('Acme Concierge');
    await page.screenshot({ path: `../../docs/evidence/browser/${info.project.name}/package-integrations-settings.png` });
  });

  test('provisions a credential and reveals it exactly once', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await page.fill('#integration .integration-actions input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Provision credentials' }).click();
    await expect(page.locator('.reveal')).toContainText('shown only once');
    await page.screenshot({ path: `../../docs/evidence/browser/${info.project.name}/package-integrations-reveal.png` });

    // Reload: reveal is gone, credential now listed as active.
    await page.reload();
    await expect(page.locator('.reveal')).toHaveCount(0);
    await expect(page.locator('#integration')).toContainText('active');
  });

  test('integration section has no serious axe violations', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    const results = await new AxeBuilder({ page })
      .include('#integration')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const serious = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(serious, `${info.project.name} #integration serious/critical axe violations`).toEqual([]);
  });
});
```
- [ ] **Step 3: Wire the spec into a feature script.** In `tests/browser/package.json`, add a dedicated script so the dark-surface fixtures are seeded before the run:
```json
    "evidence:integrations": "RB_BROWSER_DARK_SURFACES=1 bash prepare.sh && playwright test package-integrations.spec.ts",
```
- [ ] **Step 4: Run the browser evidence and confirm green.** From `tests/browser/`, run `npm run evidence:integrations` — expect all four tests to pass across the configured projects, producing `package-integrations-settings.png` and `package-integrations-reveal.png` under `docs/evidence/browser/<project>/`, and a clean axe pass over `#integration`.
- [ ] **Step 5: Advance the requirement ledger for GA-DOD-08.** In `docs/phase5/requirement-ledger.json`, move `GA-DOD-08` from R1 to R3/R4 and attach evidence paths `tests/Integration/Core/AppPackageIntegrationTest.php` (HTTP: settings/credential/reauth/CSRF/noindex/flag-dark) and `tests/browser/package-integrations.spec.ts` (no-JS + one-time reveal + axe).
- [ ] **Step 6: Note the surface in status + deploy-dark inventory + runbook.** Add an Inc 5 integration-UX row to `PHASE_5_STATUS.md` and `docs/evidence/deploy-dark-features.md` (integration panel is deploy-dark behind `package_registry`, HTTP + browser/axe evidence green), and add a "Operator integration UX (settings / credentials / pause / export)" section to `docs/runbooks/package_integrations.md` describing the no-JS flow, the one-time credential reveal, and where the evidence lives.
- [ ] **Step 7: Run the PHP suite once more to confirm the whole group is green.** `vendor/bin/phpunit tests/Integration/Core/AppPackageIntegrationTest.php tests/Integration/Core/AppFeatureFlagTest.php` — expect green, then commit.
- [ ] **Step 8: Commit.**
```bash
git add tests/browser/package-integrations.spec.ts tests/browser/seed.php tests/browser/package.json \
        docs/phase5/requirement-ledger.json PHASE_5_STATUS.md docs/evidence/deploy-dark-features.md \
        docs/runbooks/package_integrations.md
git commit -m "$(cat <<'EOF'
test(packages): browser + axe evidence for the integration operator surface

Add package-integrations.spec.ts (no-JS settings save, one-time credential
reveal, axe over #integration) plus an enabled remote_app browser fixture.
Advance GA-DOD-08 to R3/R4 and record the deploy-dark integration UX evidence.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```


<!-- ===== group: publisher-trust-console — PublisherTrustService + publisher console ===== -->

### Task 46: `PublisherTrustService` + publisher-aware enforcement cascade

**Files:**
- Create `src/Service/Registry/PublisherTrustService.php`
- Modify `src/Repository/InstalledPackageRepository.php` (`activeWithContext()` exposes `publisher_status`)
- Modify `src/Service/Packages/PackageHealthService.php` (`enforcePolicy()` force-disables installs of suspended/revoked publishers)
- Test `tests/Integration/Service/PublisherTrustServiceTest.php`

**Interfaces:**

Consumes (all provided by earlier groups — do **not** create them here):
```php
// App\Repository\PackagePublisherRepository (added methods)
public function find(int $id): ?array;
public function setStatus(int $id, string $status): void;             // active|suspended|revoked
public function markVerified(int $id, ?int $actorId): void;
public function packagesFor(int $publisherId): array;
// App\Repository\PublisherSigningKeyRepository (final; ctor (private Database $db))
public function forPublisher(int $publisherId): array;               // list handed to TrustChainVerifier
public function find(int $id): ?array;
public function findKey(int $publisherId, string $keyId): ?array;
public function pin(int $publisherId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int; // ed25519/active
public function markRotated(int $id): void;
public function revoke(int $id, string $reason): void;
// App\Security\Registry\TrustChainVerifier
public function verifyRotation(string $documentJson, string $signature, string $keyId, array $trustKeys, \DateTimeImmutable $now): array; // {key_id, public_key} or RegistryVerificationException
// App\Service\Packages\PackageHealthService
public function enforcePolicy(): int;                                 // global sweep; force-disables blocking installs, returns count
```

Produces (LOCKED — copy verbatim):
```php
// App\Service\Registry\PublisherTrustService (final)
public function __construct(
    private Database $db,
    private PackagePublisherRepository $publishers,
    private PublisherSigningKeyRepository $keys,
    private PackageRepository $packages,
    private PackageTransparencyLogRepository $transparency,
    private TrustChainVerifier $verifier,
    private PackageHealthService $enforcement,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private ModerationLogRepository $audit,
);
public function verifyPublisher(User $admin, string $currentPassword, int $publisherId): void;
public function suspendPublisher(User $admin, string $currentPassword, int $publisherId, string $reason): int;
public function reinstatePublisher(User $admin, string $currentPassword, int $publisherId): void;
public function pinKey(User $admin, string $currentPassword, int $publisherId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int;
public function applyKeyRotation(User $admin, string $currentPassword, int $publisherId, string $documentJson, string $signature, string $keyId): int;
public function revokeKey(User $admin, string $currentPassword, int $keyRowId, string $reason): void;
```

> **Prerequisite check:** `PublisherSigningKeyRepository` and the added `PackagePublisherRepository` methods above must already exist (they are owned by the migration/repository group that runs before this one). Confirm with `grep -l "function forPublisher" src/Repository/PublisherSigningKeyRepository.php` before starting; if missing, stop and flag the ordering.

- [ ] **Step 1: Write the failing service test.** Create `tests/Integration/Service/PublisherTrustServiceTest.php`. It hand-builds the service (mirroring `RegistryTrustServiceTest`) with a real `PackageHealthService`, seeds via `RegistryFixtures`, and asserts every behavior via repo/SQL readback (committed writes are visible within the test transaction):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageHealthService;
use App\Service\Registry\PublisherTrustService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09 publisher/key lifecycle + publisher-compromise cascade. */
final class PublisherTrustServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('pub-root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function service(): PublisherTrustService
    {
        return new PublisherTrustService(
            $this->db,
            new PackagePublisherRepository($this->db),
            new PublisherSigningKeyRepository($this->db),
            new PackageRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new TrustChainVerifier(),
            $this->health(),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function health(): PackageHealthService
    {
        return new PackageHealthService(
            $this->db,
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new PackageArtifactStore(sys_get_temp_dir()),
            new ModerationLogRepository($this->db),
        );
    }

    private function enabledInstall(): int
    {
        return (int) $this->db->insert(
            "INSERT INTO installed_packages (package_id, release_id, digest, publisher_id, trust_class, review_status, state, installed_at)
             VALUES (?, ?, ?, ?, 'reviewed_declarative', 'approved', 'enabled', UTC_TIMESTAMP())",
            [$this->ids['package_id'], $this->ids['release_id'], $this->ids['release_digest'], $this->ids['publisher_id']],
        );
    }

    private function auditCount(string $action): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = ? AND target_type = 'publisher'",
            [$action],
        );
    }

    public function test_pin_rotate_and_revoke_walk_the_key_status_machine(): void
    {
        $svc = $this->service();
        $keyRow = $svc->pinKey($this->admin, 'password123', $this->ids['publisher_id'], 'pub-root-1', base64_encode($this->root->publicKey()), null, null);
        $keys = new PublisherSigningKeyRepository($this->db);
        self::assertSame('active', (string) $keys->find($keyRow)['status']);

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $newRow = $svc->applyKeyRotation($this->admin, 'password123', $this->ids['publisher_id'], $rotation['json'], $rotation['signature'], 'pub-root-1');

        self::assertSame('rotated', (string) $keys->find($keyRow)['status']);
        self::assertSame('active', (string) $keys->find($newRow)['status']);
        self::assertSame('pub-root-2', (string) $keys->find($newRow)['key_id']);

        $svc->revokeKey($this->admin, 'password123', $newRow, 'suspected compromise');
        self::assertSame('revoked', (string) $keys->find($newRow)['status']);
        self::assertSame(1, $this->auditCount('publisher_pin_key'));
        self::assertSame(1, $this->auditCount('publisher_rotate_key'));
        self::assertSame(1, $this->auditCount('publisher_revoke_key'));
    }

    public function test_forged_rotation_is_refused_and_pins_nothing(): void
    {
        $svc = $this->service();
        $svc->pinKey($this->admin, 'password123', $this->ids['publisher_id'], 'pub-root-1', base64_encode($this->root->publicKey()), null, null);

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        try {
            $svc->applyKeyRotation($this->admin, 'password123', $this->ids['publisher_id'], $rotation['json'], SigningHarness::tamper($rotation['signature']), 'pub-root-1');
            self::fail('forged rotation should be refused');
        } catch (RegistryVerificationException $e) {
            self::assertSame('bad_signature', $e->code);
        }
        $keyIds = array_map(static fn (array $r): string => (string) $r['key_id'], (new PublisherSigningKeyRepository($this->db))->forPublisher($this->ids['publisher_id']));
        self::assertSame(['pub-root-1'], $keyIds);
    }

    public function test_suspend_force_disables_installs_and_reinstate_does_not_revive_them(): void
    {
        $installId = $this->enabledInstall();
        $svc = $this->service();

        $affected = $svc->suspendPublisher($this->admin, 'password123', $this->ids['publisher_id'], 'signing key exfiltrated');
        self::assertGreaterThanOrEqual(1, $affected);
        self::assertSame('suspended', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
        self::assertSame(1, $this->auditCount('publisher_suspend'));
        self::assertGreaterThanOrEqual(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM package_transparency_log WHERE package_uid = 'acme/midnight-theme' AND event = 'force_disable'",
        ));

        $svc->reinstatePublisher($this->admin, 'password123', $this->ids['publisher_id']);
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]), 'reinstate must not silently re-enable');
    }

    public function test_wrong_password_changes_nothing(): void
    {
        $installId = $this->enabledInstall();
        try {
            $this->service()->suspendPublisher($this->admin, 'wrong-password', $this->ids['publisher_id'], 'x');
            self::fail('reauth should refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('enabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_verify_marks_publisher_active_and_verified(): void
    {
        $id = (int) $this->db->insert(
            "INSERT INTO package_publishers (publisher_uid, display_name, status) VALUES ('acme/pending', 'Pending Publisher', 'suspended')",
        );
        $this->service()->verifyPublisher($this->admin, 'password123', $id);
        $row = (new PackagePublisherRepository($this->db))->find($id);
        self::assertSame('active', (string) $row['status']);
        self::assertNotNull($row['verified_at']);
        self::assertSame(1, $this->auditCount('publisher_verify'));
    }
}
```

- [ ] **Step 2: Run the test — expect a hard FAIL (class missing).**
```
vendor/bin/phpunit --filter PublisherTrustServiceTest
```
Expected: `Error: Class "App\Service\Registry\PublisherTrustService" not found` (or an autoload error). Red.

- [ ] **Step 3: Create `PublisherTrustService` with verify/suspend/reinstate.** Write `src/Service/Registry/PublisherTrustService.php` with the LOCKED constructor and these three methods plus the `requirePublisher` guard:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Packages\PackageHealthService;

/**
 * Local operator trust lifecycle for package publishers (P5-07-A). Verify,
 * suspend (cascading force-disable through the one enforcement engine), and
 * reinstate publishers; pin/rotate/revoke their public Ed25519 signing keys.
 * Every mutation is WriteGate + password reauth + moderation audit. Only public
 * key material ever reaches this service; the signing root stays operator-held.
 */
final class PublisherTrustService
{
    public function __construct(
        private Database $db,
        private PackagePublisherRepository $publishers,
        private PublisherSigningKeyRepository $keys,
        private PackageRepository $packages,
        private PackageTransparencyLogRepository $transparency,
        private TrustChainVerifier $verifier,
        private PackageHealthService $enforcement,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    public function verifyPublisher(User $admin, string $currentPassword, int $publisherId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $this->db->transaction(function () use ($admin, $publisher, $publisherId): void {
            $this->publishers->setStatus($publisherId, 'active');
            $this->publishers->markVerified($publisherId, $admin->id());
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_verify',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status'], 'verified_at' => $publisher['verified_at']],
                'after' => ['status' => 'active', 'verified' => true],
            ]);
        });
    }

    /** Suspending is the defensive escalation: set status, then force-disable every install of this publisher's packages through enforcePolicy(). */
    public function suspendPublisher(User $admin, string $currentPassword, int $publisherId, string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A suspension reason between 1 and 255 characters is required.']);
        }

        return $this->db->transaction(function () use ($admin, $publisher, $publisherId, $reason): int {
            $this->publishers->setStatus($publisherId, 'suspended');
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_suspend',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status']],
                'after' => ['status' => 'suspended', 'reason' => $reason],
            ]);

            // The one enforcement engine now sees the suspended publisher and
            // force-disables each enabled install (writing per-install force_disable
            // transparency + history + audit and driving the theme/integration seams).
            // It runs in this same transaction; any failure rolls back the
            // publisher status and every package state/credential change.
            return $this->enforcement->enforcePolicy();
        });
    }

    /** Reinstating clears the suspension but deliberately never auto-re-enables installs — the operator re-enables each explicitly. */
    public function reinstatePublisher(User $admin, string $currentPassword, int $publisherId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $this->db->transaction(function () use ($admin, $publisher, $publisherId): void {
            $this->publishers->setStatus($publisherId, 'active');
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_reinstate',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status']],
                'after' => ['status' => 'active', 'reenabled_installs' => false],
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function requirePublisher(int $publisherId): array
    {
        $publisher = $this->publishers->find($publisherId);
        if ($publisher === null) {
            throw new ValidationException(['publisher' => 'Publisher not found.']);
        }

        return $publisher;
    }
}
```

- [ ] **Step 4: Add the key-lifecycle methods** (`pinKey`, `applyKeyRotation`, `revokeKey`) to the same class, mirroring `RegistryTrustService` but keyed on `publisherId`:

```php
    public function pinKey(User $admin, string $currentPassword, int $publisherId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requirePublisher($publisherId);

        $keyId = trim($keyId);
        $errors = [];
        if ($keyId === '' || mb_strlen($keyId) > 190) {
            $errors['key_id'] = 'A key id between 1 and 190 characters is required.';
        } elseif ($this->keys->findKey($publisherId, $keyId) !== null) {
            $errors['key_id'] = 'This key id is already pinned for the publisher.';
        }
        $material = base64_decode(trim($publicKeyBase64), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $errors['public_key'] = 'Public key must be the base64 of exactly 32 Ed25519 public-key bytes.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['key_id' => $keyId, 'public_key' => $publicKeyBase64]);
        }

        return $this->db->transaction(function () use ($admin, $publisherId, $keyId, $material, $validFrom, $validUntil): int {
            $rowId = $this->keys->pin($publisherId, $keyId, (string) $material, $validFrom, $validUntil);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_pin_key',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'after' => ['key_id' => $keyId, 'fingerprint' => substr(hash('sha256', (string) $material), 0, 16)],
            ]);

            return $rowId;
        });
    }

    public function applyKeyRotation(User $admin, string $currentPassword, int $publisherId, string $documentJson, string $signature, string $keyId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requirePublisher($publisherId);

        $successor = $this->verifier->verifyRotation(
            $documentJson,
            $signature,
            $keyId,
            $this->keys->forPublisher($publisherId),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($this->keys->findKey($publisherId, $successor['key_id']) !== null) {
            throw new ValidationException(['rotation' => 'The successor key id is already pinned.']);
        }
        $oldRow = $this->keys->findKey($publisherId, $keyId);

        return $this->db->transaction(function () use ($admin, $publisherId, $successor, $oldRow, $keyId): int {
            $rowId = $this->keys->pin($publisherId, $successor['key_id'], $successor['public_key'], null, null);
            if ($oldRow !== null) {
                $this->keys->markRotated((int) $oldRow['id']);
            }
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_rotate_key',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['key_id' => $keyId],
                'after' => ['key_id' => $successor['key_id']],
            ]);

            return $rowId;
        });
    }

    public function revokeKey(User $admin, string $currentPassword, int $keyRowId, string $reason): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $key = $this->keys->find($keyRowId);
        if ($key === null) {
            throw new ValidationException(['key' => 'Publisher signing key not found.']);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A revocation reason between 1 and 255 characters is required.']);
        }

        $this->db->transaction(function () use ($admin, $key, $keyRowId, $reason): void {
            $this->keys->revoke($keyRowId, $reason);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_revoke_key',
                'target_type' => 'publisher',
                'target_id' => (int) $key['publisher_id'],
                'before' => ['key_id' => (string) $key['key_id'], 'status' => (string) $key['status']],
                'after' => ['status' => 'revoked', 'reason' => $reason],
            ]);
        });
    }
```

- [ ] **Step 5: Re-run the test — key/verify/wrong-password/forged cases PASS; the suspension-cascade case still FAILS.**
```
vendor/bin/phpunit --filter PublisherTrustServiceTest
```
Expected: `test_suspend_force_disables_installs_and_reinstate_does_not_revive_them` fails on `Failed asserting that 'enabled' is identical to 'disabled'` — because `enforcePolicy()` does not yet recognize a suspended publisher. Everything else green.

- [ ] **Step 6: Teach `activeWithContext()` to carry publisher status.** In `src/Repository/InstalledPackageRepository.php`, edit the `activeWithContext()` SELECT to add the join + column (additive, safe for existing callers):
```php
            "SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type,
                    p.advisory_status AS package_advisory_status,
                    pub.status AS publisher_status,
                    r.version AS release_version, r.advisory_status AS release_advisory_status
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             LEFT JOIN package_publishers pub ON pub.id = p.publisher_id
             LEFT JOIN package_releases r ON r.id = ip.release_id
             WHERE ip.state <> 'uninstalled'
             ORDER BY ip.id",
```

- [ ] **Step 7: Force-disable suspended-publisher installs in `enforcePolicy()`.** In `src/Service/Packages/PackageHealthService.php`, add this guard as the first thing inside the `foreach ($this->installs->activeWithContext() as $install)` loop of `enforcePolicy()` (before the existing `state === 'enabled'` advisory check). `securityDisable()` already writes the `force_disable` transparency + `disable` history + `package_force_disable` audit and fires the theme/integration ineligibility seams:
```php
            if ((string) $install['state'] === 'enabled'
                && in_array((string) ($install['publisher_status'] ?? 'active'), ['suspended', 'revoked'], true)) {
                if ($this->securityDisable($install, 'publisher ' . (string) $install['publisher_status'])) {
                    $changed++;
                }
                continue;
            }
```

- [ ] **Step 8: Re-run the full test file — all green.**
```
vendor/bin/phpunit --filter PublisherTrustServiceTest
```
Expected: `OK (5 tests, NN assertions)`.

- [ ] **Step 9: Run the touched suites to prove no regression** in existing health/lifecycle enforcement (the new guard only fires for suspended/revoked publishers; the query change is additive):
```
vendor/bin/phpunit tests/Integration/Service/PackageLifecycleServiceTest.php tests/Integration/Service/PackageUninstallTest.php tests/Integration/Service/RegistryTrustServiceTest.php
```
Expected: `OK`.

- [ ] **Step 10: Commit.**
```
git add src/Service/Registry/PublisherTrustService.php src/Repository/InstalledPackageRepository.php src/Service/Packages/PackageHealthService.php tests/Integration/Service/PublisherTrustServiceTest.php
git commit -m "$(cat <<'EOF'
feat(packages): PublisherTrustService with publisher-aware enforcement cascade

Verify/suspend/reinstate publishers and pin/rotate/revoke their Ed25519 signing
keys (reauth-gated, audited target_type=publisher). Suspension force-disables the
publisher's installs through the single PackageHealthService::enforcePolicy()
engine, which now recognises suspended/revoked publishers via a new
publisher_status column on activeWithContext(). Maps the publisher-compromise
scenario; advances GA-DOD-09.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 47: Publisher console — controller actions, routes, template, HTTP + dark-404 + browser evidence

**Files:**
- Modify `src/Controller/AdminPackageSecurityController.php` (add the publisher actions + shared `publisherView`/`parseEnvelope` helpers; create the class with the `gate()`/`noindex()` scaffolding if the security-console group has not landed it yet)
- Create `templates/admin/package_publisher.php`
- Modify `src/Core/App.php` (bind `PublisherTrustService`; register the publisher routes **before** `GET /admin/packages/{id}`)
- Modify `tests/Integration/Core/AppFeatureFlagTest.php` (add a publisher-route dark-404 method)
- Test `tests/Integration/Admin/AppPackagePublisherConsoleTest.php` (full-kernel HTTP)
- Test `tests/browser/package-security.spec.ts` (no-JS + axe publisher-detail case)

**Interfaces:**

Consumes:
```php
// App\Service\Registry\PublisherTrustService  (from Task 46 — all methods above)
// App\Repository\PublisherSigningKeyRepository::forPublisher(int): array
// App\Repository\PackagePublisherRepository::find(int): ?array / packagesFor(int): array
// App\Repository\PackageReviewDecisionRepository::forPackage(int): array
// App\Security\Registry\RegistryVerificationException  (->code, ->getMessage())
// Controller base helpers: requireAdmin(), redirectWithFlash(), view(), Response::header()
```

Produces (routes are LOCKED — copy verbatim):
```php
$r->get ('/admin/packages/publishers/{id}',           [AdminPackageSecurityController::class, 'publisher']);
$r->post('/admin/packages/publishers/{id}/verify',    [AdminPackageSecurityController::class, 'verifyPublisher']);
$r->post('/admin/packages/publishers/{id}/suspend',   [AdminPackageSecurityController::class, 'suspendPublisher']);
$r->post('/admin/packages/publishers/{id}/reinstate', [AdminPackageSecurityController::class, 'reinstatePublisher']);
$r->post('/admin/packages/publishers/{id}/keys',      [AdminPackageSecurityController::class, 'pinPublisherKey']);
$r->post('/admin/packages/publishers/{id}/rotate',    [AdminPackageSecurityController::class, 'rotatePublisherKey']);
$r->post('/admin/publisher-keys/{id}/revoke',         [AdminPackageSecurityController::class, 'revokePublisherKey']);
```

- [ ] **Step 1: Write the failing HTTP test.** Create `tests/Integration/Admin/AppPackagePublisherConsoleTest.php` driving the real kernel end-to-end (asserts observable HTTP status/redirects + repo readback of committed writes):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\PackagePublisherRepository;
use App\Repository\PublisherSigningKeyRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09: no-JS publisher trust console end to end. */
final class AppPackagePublisherConsoleTest extends TestCase
{
    private SigningHarness $root;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('pub-root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->setFlags(['package_registry' => true]);
    }

    private function pid(): int
    {
        return $this->ids['publisher_id'];
    }

    private function enabledInstall(): int
    {
        return (int) $this->db->insert(
            "INSERT INTO installed_packages (package_id, release_id, digest, publisher_id, trust_class, review_status, state, installed_at)
             VALUES (?, ?, ?, ?, 'reviewed_declarative', 'approved', 'enabled', UTC_TIMESTAMP())",
            [$this->ids['package_id'], $this->ids['release_id'], $this->ids['release_digest'], $this->pid()],
        );
    }

    public function test_publisher_detail_renders_noindex_with_forms(): void
    {
        $response = $this->get('/admin/packages/publishers/' . $this->pid());
        $this->assertStatus(200, $response);
        self::assertSame('noindex', $response->getHeader('x-robots-tag'));
        $this->assertSeeText($response, 'Acme Themes');
        self::assertStringContainsString('/admin/packages/publishers/' . $this->pid() . '/suspend', $response->body());
    }

    public function test_suspend_cascades_then_reinstate_leaves_installs_disabled(): void
    {
        $installId = $this->enabledInstall();

        $suspend = $this->post('/admin/packages/publishers/' . $this->pid() . '/suspend', [
            'current_password' => 'password123',
            'reason' => 'signing key exfiltrated',
        ]);
        $this->assertRedirectContains($suspend, '/admin/packages/publishers/' . $this->pid());
        self::assertSame('suspended', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));

        $reinstate = $this->post('/admin/packages/publishers/' . $this->pid() . '/reinstate', ['current_password' => 'password123']);
        $this->assertRedirectContains($reinstate, '/admin/packages/publishers/' . $this->pid());
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_wrong_password_is_422_and_preserves_state(): void
    {
        $installId = $this->enabledInstall();
        $response = $this->post('/admin/packages/publishers/' . $this->pid() . '/suspend', [
            'current_password' => 'nope',
            'reason' => 'x',
        ]);
        $this->assertStatus(422, $response);
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->pid())['status']);
        self::assertSame('enabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_pin_rotate_revoke_over_http(): void
    {
        $keys = new PublisherSigningKeyRepository($this->db);
        $this->assertRedirectContains(
            $this->post('/admin/packages/publishers/' . $this->pid() . '/keys', [
                'current_password' => 'password123',
                'key_id' => 'pub-root-1',
                'public_key' => base64_encode($this->root->publicKey()),
            ]),
            '/admin/packages/publishers/' . $this->pid(),
        );

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode($rotation['signature']),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $this->assertRedirectContains(
            $this->post('/admin/packages/publishers/' . $this->pid() . '/rotate', ['current_password' => 'password123', 'envelope' => $envelope]),
            '/admin/packages/publishers/' . $this->pid(),
        );

        $active = array_values(array_filter($keys->forPublisher($this->pid()), static fn (array $r): bool => (string) $r['status'] === 'active'));
        self::assertCount(1, $active);
        self::assertSame('pub-root-2', (string) $active[0]['key_id']);

        $this->assertRedirectContains(
            $this->post('/admin/publisher-keys/' . (int) $active[0]['id'] . '/revoke', ['current_password' => 'password123', 'reason' => 'compromise']),
            '/admin/packages/publishers/' . $this->pid(),
        );
        self::assertSame('revoked', (string) $keys->find((int) $active[0]['id'])['status']);
    }

    public function test_forged_rotation_is_422_and_pins_nothing(): void
    {
        $this->post('/admin/packages/publishers/' . $this->pid() . '/keys', [
            'current_password' => 'password123',
            'key_id' => 'pub-root-1',
            'public_key' => base64_encode($this->root->publicKey()),
        ]);
        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode(SigningHarness::tamper($rotation['signature'])),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);

        $response = $this->post('/admin/packages/publishers/' . $this->pid() . '/rotate', ['current_password' => 'password123', 'envelope' => $envelope]);
        $this->assertStatus(422, $response);
        $keyIds = array_map(static fn (array $r): string => (string) $r['key_id'], (new PublisherSigningKeyRepository($this->db))->forPublisher($this->pid()));
        self::assertSame(['pub-root-1'], $keyIds);
    }
}
```

- [ ] **Step 2: Run it — expect FAIL (route/controller/template missing).**
```
vendor/bin/phpunit --filter AppPackagePublisherConsoleTest
```
Expected: 404s / `View "admin/package_publisher" not found` — red across the board.

- [ ] **Step 3: Add the publisher actions to `AdminPackageSecurityController`.** Open `src/Controller/AdminPackageSecurityController.php`. If the security-console group has not created it yet, create it now with the same scaffolding `AdminRegistryController` uses (`private function gate()` throwing `NotFoundException` unless `package_registry` is enabled; `private function noindex(Response)` setting `X-Robots-Tag: noindex`). Add these methods (and the shared `publisherView`/`parseEnvelope` helpers — keep a single copy if the other group already added `parseEnvelope`):

```php
    /** @param array<string,string> $params */
    public function publisher(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();

        return $this->publisherView((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function verifyPublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->verifyPublisher($admin, (string) $request->post('current_password', ''), $id);
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher verified.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function suspendPublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $affected = $this->container->get(PublisherTrustService::class)->suspendPublisher(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher suspended; ' . $affected . ' install(s) force-disabled.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function reinstatePublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->reinstatePublisher($admin, (string) $request->post('current_password', ''), $id);
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher reinstated. Re-enable each install explicitly.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function pinPublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->pinKey(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $request->str('key_id'),
                $request->str('public_key'),
                $request->str('valid_from') !== '' ? $request->str('valid_from') : null,
                $request->str('valid_until') !== '' ? $request->str('valid_until') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher signing key pinned.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotatePublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $this->container->get(PublisherTrustService::class)->applyKeyRotation(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $document,
                $signature,
                $keyId,
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher key rotation applied: successor pinned, old key retired.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->publisherView($id, ['envelope' => 'Rotation refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokePublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $keyRowId = (int) ($params['id'] ?? 0);
        $key = $this->container->get(PublisherSigningKeyRepository::class)->find($keyRowId);
        $publisherId = $key !== null ? (int) $key['publisher_id'] : 0;
        try {
            $this->container->get(PublisherTrustService::class)->revokeKey(
                $admin,
                (string) $request->post('current_password', ''),
                $keyRowId,
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, 'Publisher signing key revoked; everything it signed now fails closed.'));
        } catch (ValidationException $e) {
            return $this->publisherView($publisherId, $e->errors, $request->allInput(), 422);
        }
    }

    /** @return array{0:string,1:string,2:string} document, raw signature bytes, key id */
    private function parseEnvelope(string $raw): array
    {
        $decoded = json_decode(trim($raw), true);
        $signature = is_array($decoded) ? base64_decode((string) ($decoded['signature'] ?? ''), true) : false;
        if (!is_array($decoded) || !is_string($decoded['document'] ?? null) || $signature === false) {
            throw new ValidationException(['envelope' => 'Paste the JSON envelope: {"document": "...", "signature": "<base64>", "key_id": "..."}']);
        }

        return [(string) $decoded['document'], $signature, (string) ($decoded['key_id'] ?? '')];
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function publisherView(int $publisherId, array $errors = [], array $old = [], int $status = 200): Response
    {
        $publishers = $this->container->get(PackagePublisherRepository::class);
        $publisher = $publishers->find($publisherId);
        if ($publisher === null) {
            throw new NotFoundException();
        }
        $reviews = $this->container->get(PackageReviewDecisionRepository::class);
        $packages = [];
        foreach ($publishers->packagesFor($publisherId) as $package) {
            $package['decisions'] = $reviews->forPackage((int) $package['id']);
            $packages[] = $package;
        }

        return $this->noindex($this->view('admin/package_publisher', [
            'publisher' => $publisher,
            'keys' => $this->container->get(PublisherSigningKeyRepository::class)->forPublisher($publisherId),
            'packages' => $packages,
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }
```

Ensure the `use` block imports `App\Core\FeatureFlags`, `App\Core\NotFoundException`, `App\Core\Request`, `App\Core\Response`, `App\Core\ValidationException`, `App\Repository\PackagePublisherRepository`, `App\Repository\PackageReviewDecisionRepository`, `App\Repository\PublisherSigningKeyRepository`, `App\Security\Registry\RegistryVerificationException`, and `App\Service\Registry\PublisherTrustService`.

- [ ] **Step 4: Create the template** `templates/admin/package_publisher.php` — no-JS forms only, CSRF field on every POST, one-time nothing-secret (public keys/fingerprints are safe to show), errors surfaced:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Publisher trust');
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($publisher['display_name']) ?>
            <span class="pill"><?= $e($publisher['status']) ?></span>
            <?= $publisher['verified_at'] !== null ? '<span class="pill">verified</span>' : '' ?>
        </h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'registries', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="muted"><code><?= $e($publisher['publisher_uid']) ?></code>. Trust changes require your password. Suspension force-disables every install of this publisher's packages; reinstatement never silently re-enables them.</p>

    <section class="card">
        <h2>Status</h2>
        <div class="form-cell">
            <?php if ($publisher['status'] !== 'suspended'): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/suspend" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="text" name="reason" placeholder="Suspension reason" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>" required>
                <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Suspend publisher</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/reinstate" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Reinstate publisher</button>
            </form>
            <?php endif; ?>
            <?php if ($publisher['verified_at'] === null): ?>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/verify" class="inline-form">
                <?= $this->csrfField() ?>
                <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Verify publisher</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if (!empty($errors['reason'])): ?><p class="field-error"><?= $e($errors['reason']) ?></p><?php endif; ?>
        <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
        <?php if (!empty($errors['publisher'])): ?><p class="field-error"><?= $e($errors['publisher']) ?></p><?php endif; ?>
    </section>

    <section class="card">
        <h2>Signing keys</h2>
        <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="Publisher signing keys">
        <table class="audit">
            <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th>Fingerprint</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($keys as $key): ?>
                <tr>
                    <td class="nowrap"><code><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['revoked_reason'] !== null ? ' - ' . $e($key['revoked_reason']) : '' ?></td>
                    <td><?= $e($key['valid_from'] ?? 'inf') ?> to <?= $e($key['valid_until'] ?? 'inf') ?></td>
                    <td class="nowrap"><code><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td class="form-cell">
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/publisher-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" placeholder="Revocation reason" maxlength="255" required>
                            <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($keys === []): ?><tr><td colspan="5" class="muted">No signing keys pinned.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>

        <details>
            <summary>Pin a new public key</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label>Key id <input type="text" name="key_id" maxlength="190" value="<?= $e($old['key_id'] ?? '') ?>" required></label>
                <?php if (!empty($errors['key_id'])): ?><p class="field-error"><?= $e($errors['key_id']) ?></p><?php endif; ?>
                <label>Public key (base64, 32 bytes) <input type="text" name="public_key" value="<?= $e($old['public_key'] ?? '') ?>" required></label>
                <?php if (!empty($errors['public_key'])): ?><p class="field-error"><?= $e($errors['public_key']) ?></p><?php endif; ?>
                <label>Valid from (UTC, optional) <input type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <label>Valid until (UTC, optional) <input type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Pin key</button>
            </form>
        </details>

        <details>
            <summary>Apply a signed key rotation</summary>
            <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label>Rotation envelope (JSON) <textarea name="envelope" rows="4" placeholder='{"document":"...","signature":"&lt;base64&gt;","key_id":"..."}'><?= $e($old['envelope'] ?? '') ?></textarea></label>
                <?php if (!empty($errors['envelope'])): ?><p class="field-error"><?= $e($errors['envelope']) ?></p><?php endif; ?>
                <?php if (!empty($errors['rotation'])): ?><p class="field-error"><?= $e($errors['rotation']) ?></p><?php endif; ?>
                <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                <button class="btn" type="submit">Apply rotation</button>
            </form>
        </details>
    </section>

    <section class="card">
        <h2>Packages &amp; review decisions</h2>
        <?php foreach ($packages as $package): ?>
            <h3><code><?= $e($package['package_uid']) ?></code> <span class="pill"><?= $e($package['advisory_status']) ?></span></h3>
            <ul>
            <?php foreach ($package['decisions'] as $decision): ?>
                <li><?= $e($decision['decision']) ?> — <code><?= $e(substr((string) $decision['digest'], 0, 12)) ?></code> (<?= $e($decision['source']) ?>)</li>
            <?php endforeach; ?>
            <?php if ($package['decisions'] === []): ?><li class="muted">No review decisions recorded.</li><?php endif; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($packages === []): ?><p class="muted">This publisher owns no packages.</p><?php endif; ?>
    </section>
    </div>
</div>
```

- [ ] **Step 5: Wire the container bind + routes in `App.php`.** In `buildContainer()` add:
```php
$c->bind(PublisherTrustService::class, fn (Container $c) => new PublisherTrustService(
    $c->get(Database::class),
    $c->get(PackagePublisherRepository::class),
    $c->get(PublisherSigningKeyRepository::class),
    $c->get(PackageRepository::class),
    $c->get(PackageTransparencyLogRepository::class),
    $c->get(TrustChainVerifier::class),
    $c->get(PackageHealthService::class),
    $c->get(ReauthGate::class),
    $c->get(WriteGate::class),
    $c->get(ModerationLogRepository::class),
));
```
In `buildRouter()`, register the seven publisher routes **immediately before** the existing `$r->get('/admin/packages/{id}', ...)` line (arities differ so they cannot collide, but the contract fixes this ordering). Add the `use App\Controller\AdminPackageSecurityController;` and `use App\Service\Registry\PublisherTrustService;` imports if not already present.

- [ ] **Step 6: Run the HTTP test — expect GREEN.**
```
vendor/bin/phpunit --filter AppPackagePublisherConsoleTest
```
Expected: `OK (5 tests, NN assertions)`.

- [ ] **Step 7: Add the flag-dark 404 assertion.** In `tests/Integration/Core/AppFeatureFlagTest.php` add a new method (a fresh method avoids conflicting with other groups extending the shared `package_registry` case):
```php
    public function test_package_registry_gates_publisher_console_routes(): void
    {
        $this->actingAs($this->makeAdmin());
        // Dark by default: gate() → NotFoundException (404, never 403/405) before and after requireAdmin().
        $this->assertStatus(404, $this->get('/admin/packages/publishers/1'));
        foreach ([
            ['/admin/packages/publishers/1/verify', ['current_password' => 'password123']],
            ['/admin/packages/publishers/1/suspend', ['current_password' => 'password123', 'reason' => 'x']],
            ['/admin/packages/publishers/1/reinstate', ['current_password' => 'password123']],
            ['/admin/packages/publishers/1/keys', ['current_password' => 'password123', 'key_id' => 'k', 'public_key' => 'x']],
            ['/admin/packages/publishers/1/rotate', ['current_password' => 'password123', 'envelope' => '{}']],
            ['/admin/publisher-keys/1/revoke', ['current_password' => 'password123', 'reason' => 'x']],
        ] as [$path, $body]) {
            $this->assertStatus(404, $this->post($path, $body));
        }

        // Flag on → the gate opens (a seeded publisher renders 200, proving it was the gate, not a not-found).
        $this->setFlags(['package_registry' => true]);
        $ids = \Tests\Support\Phase5\RegistryFixtures::seed($this->db, \Tests\Support\Phase5\SigningHarness::generate('pub-gate'));
        self::assertNotSame(404, $this->get('/admin/packages/publishers/' . $ids['publisher_id'])->status());
    }
```
Run it:
```
vendor/bin/phpunit --filter test_package_registry_gates_publisher_console_routes
```
Expected: `OK (1 test, ...)`.

- [ ] **Step 8: Add the no-JS + axe browser case.** In `tests/browser/package-security.spec.ts` (create the file with the same `visit`/`login`/`shot` helpers `appeals.spec.ts` uses if the security-console group has not landed it), add a publisher-detail case. The evidence seed enables `package_registry` and exposes a publisher id; drive the suspend form with no JS, then assert axe is clean:
```ts
test('publisher trust console suspends a publisher without JS and is accessible', async ({ page }, info) => {
  await login(page, 'admin@retro.test');
  await visit(page, `/admin/packages/publishers/${PUBLISHER_ID}`);
  await expect(page.locator('h1')).toContainText('Acme');
  await shot(page, info, 'publisher-detail');

  // No-JS PRG: fill the plain suspend form and submit.
  await page.fill('input[name="reason"]', 'browser evidence suspension');
  await page.fill('form[action$="/suspend"] input[name="current_password"]', 'password123');
  await page.click('form[action$="/suspend"] button[type="submit"]');
  await expect(page.locator('.pill', { hasText: 'suspended' })).toBeVisible();
  await shot(page, info, 'publisher-suspended');

  const results = await new AxeBuilder({ page }).analyze();
  expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
});
```
Run the desktop evidence project for this spec (JS off does not apply — these are server-rendered forms; the case only exercises native form submits):
```
cd tests/browser && npx playwright test package-security.spec.ts --project=desktop
```
Expected: the case passes and writes `docs/evidence/browser/desktop/publisher-detail.png` + `publisher-suspended.png`.

- [ ] **Step 9: Full suite green + verification.** Per `superpowers:verification-before-completion`, run the touched integration surface:
```
vendor/bin/phpunit tests/Integration/Admin/AppPackagePublisherConsoleTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Service/PublisherTrustServiceTest.php
```
Expected: `OK`. Then `composer test` and confirm no new failures beyond the known gate-a pre-existing set.

- [ ] **Step 10: Commit.**
```
git add src/Controller/AdminPackageSecurityController.php templates/admin/package_publisher.php src/Core/App.php tests/Integration/Admin/AppPackagePublisherConsoleTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/browser/package-security.spec.ts
git commit -m "$(cat <<'EOF'
feat(admin): publisher trust console (routes, controller, template, evidence)

No-JS AdminPackageSecurityController publisher actions (verify/suspend/reinstate,
key pin/rotate/revoke) behind package_registry: gate() 404s dark, reauth-gated,
forged rotations refused 422 via TrustChainVerifier, suspension force-disables the
publisher's installs. Adds package_publisher.php, container bind + routes,
flag-dark 404 coverage, full-kernel HTTP tests, and no-JS + axe browser evidence.
Advances GA-DOD-09; maps the publisher-compromise scenario.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```


<!-- ===== group: review-console-service — PackageReviewConsoleService — exact-digest review decisions ===== -->

The review-console service task group starts here.


---

### Task 51: `PackageReviewConsoleService` — record tightening-only local review decisions (GA-DOD-09)

**Files:**
- Create: `src/Service/Packages/PackageReviewConsoleService.php`
- Modify: `src/Repository/PackageReleaseRepository.php` (add `setReviewStatus`)
- Modify: `src/Core/App.php` (`use` import + one container bind)
- Test: `tests/Integration/Service/PackageReviewConsoleServiceTest.php`

**Interfaces:**

Consumes (all already bound in `App::buildContainer()`; verified `$c->get`-able):
```php
App\Repository\PackageRepository::find(int $id): ?array                     // ['id','package_uid',...]
App\Repository\PackageReleaseRepository::find(int $id): ?array              // ['id','package_id','version','digest','review_status',...]
App\Repository\PackageReviewDecisionRepository::record(array $entry): int   // keys: package_id,release_id,version,digest,decision,decided_at,source,evidence_json
App\Repository\PackageReviewDecisionRepository::forPackage(int $packageId): array  // newest-first
App\Repository\PackageTransparencyLogRepository::record(array $entry): int  // keys: package_uid,version,digest,event,source,actor_id,detail
App\Repository\ModerationLogRepository::log(array $entry): int              // action/target_type/target_id/after — target_type='package' valid since 0068
App\Security\ReauthGate::requirePassword(User $actor, string $currentPassword, string $field='current_password'): void  // throws ValidationException['current_password']
App\Security\WriteGate::assertCanWrite(User $actor): void
App\Security\Packages\PackagePolicyException::__construct(string $policyCode, string $message)   // read $e->code
App\Core\Database::transaction(callable $fn): mixed                         // joins the active tx, returns closure value
```

Produces (LOCKED — copy verbatim):
```php
// src/Repository/PackageReleaseRepository.php (added, mirrors setAdvisoryStatus)
public function setReviewStatus(int $id, string $status): void;

// src/Service/Packages/PackageReviewConsoleService.php (final)
public function __construct(
    private Database $db,
    private PackageRepository $packages,
    private PackageReleaseRepository $releases,
    private PackageReviewDecisionRepository $reviewDecisions,
    private PackageTransparencyLogRepository $transparency,
    private ReauthGate $reauth,
    private WriteGate $writeGate,
    private ModerationLogRepository $audit,
);
public function decisionsFor(int $packageId): array;
public function recordDecision(User $admin, string $currentPassword, int $packageId, int $releaseId, string $decision, ?string $note): int;
```

Semantics fixed by the contract: local decisions carry `source='local'` + reviewer id in `evidence_json`; a decision must name a release **of this package** (exact package↔release↔digest binding); `decision ∈ approved|rejected|revoked`; a local `approved` is refused with `PackagePolicyException('review_conflict', …)` when a **signed** (`source != 'local'`) `rejected`/`revoked` decision already covers the exact digest; local `rejected`/`revoked` always allowed (tightening); every success updates `package_releases.review_status`, writes a transparency row (`event='release_verified'` for approve, else `'revoked'`; `source='local'`) and a `moderation_log` `action='package_review'` audit row; all under WriteGate + password reauth in one `$db->transaction`.

- [ ] **Step 1: Write the failing integration test.** Create `tests/Integration/Service/PackageReviewConsoleServiceTest.php` (mirrors `RegistryTrustServiceTest`: seeds via `RegistryFixtures::seed`, builds the service by hand, asserts observable repo/audit state — same-connection reads see the in-transaction writes):
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageReviewConsoleService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09: local operator review decisions are provenance-bound, digest-exact, and tightening-only. */
final class PackageReviewConsoleServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function service(): PackageReviewConsoleService
    {
        return new PackageReviewConsoleService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function reviewCount(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_review'");
    }

    public function test_records_a_local_decision_with_provenance_and_transparency(): void
    {
        $svc = $this->service();
        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        $digest = $this->ids['release_digest'];

        $id = $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'approved', 'audited by hand');
        self::assertGreaterThan(0, $id);

        $decision = (new PackageReviewDecisionRepository($this->db))->latestForDigest($this->ids['package_id'], $digest);
        self::assertSame('local', $decision['source']);
        self::assertSame('approved', $decision['decision']);
        self::assertStringContainsString('"reviewer_id":' . $this->admin->id(), (string) $decision['evidence_json']);

        self::assertSame('approved', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status']);

        $log = (new PackageTransparencyLogRepository($this->db))->forPackageUid((string) $pkg['package_uid']);
        $local = array_values(array_filter($log, static fn (array $r): bool => $r['source'] === 'local'));
        self::assertNotSame([], $local, 'a local transparency row is written');
        self::assertSame('release_verified', $local[0]['event']);
        self::assertSame(1, $this->reviewCount());
    }

    public function test_local_approval_is_refused_over_a_signed_reject_but_a_local_reject_tightens(): void
    {
        $svc = $this->service();
        $release = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);

        // A signed (non-local) rejection already covers this exact digest.
        (new PackageReviewDecisionRepository($this->db))->record([
            'package_id' => $this->ids['package_id'],
            'release_id' => $this->ids['release_id'],
            'version' => (string) $release['version'],
            'digest' => (string) $release['digest'],
            'decision' => 'rejected',
            'decided_at' => gmdate('Y-m-d H:i:s'),
            'source' => 'advisory',
            'evidence_json' => null,
        ]);

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'approved', null);
            self::fail('expected review_conflict');
        } catch (PackagePolicyException $e) {
            self::assertSame('review_conflict', $e->code);
        }
        self::assertSame(0, $this->reviewCount(), 'the refused approval writes no audit row');

        // Tightening the other way is always allowed.
        $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'revoked', 'kill it');
        self::assertSame('revoked', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status']);
        self::assertSame(1, $this->reviewCount());
    }

    public function test_a_decision_must_name_a_release_of_the_same_package(): void
    {
        $svc = $this->service();
        $other = RegistryFixtures::seed($this->db, $this->root, null, [
            'source_id' => 'rb-two',
            'publisher_uid' => 'beta',
            'publisher_name' => 'Beta Co',
            'package_uid' => 'beta/other',
            'name' => 'Other',
        ]);

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $other['release_id'], 'approved', null);
            self::fail('expected release-binding ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('release_id', $e->errors);
        }
        self::assertSame(0, $this->reviewCount());
    }

    public function test_reauth_and_decision_value_are_validated(): void
    {
        $svc = $this->service();

        try {
            $svc->recordDecision($this->admin, 'wrong', $this->ids['package_id'], $this->ids['release_id'], 'approved', null);
            self::fail('expected reauth ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        try {
            $svc->recordDecision($this->admin, 'password123', $this->ids['package_id'], $this->ids['release_id'], 'maybe', null);
            self::fail('expected decision-value ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('decision', $e->errors);
        }
        self::assertSame(0, $this->reviewCount());
    }
}
```

- [ ] **Step 2: Run the test; confirm it FAILS on the missing class.**
```bash
vendor/bin/phpunit --filter PackageReviewConsoleServiceTest
```
Expected: `Error: Class "App\Service\Packages\PackageReviewConsoleService" not found` (fatal/errored, red).

- [ ] **Step 3: Add the `setReviewStatus` setter to `PackageReleaseRepository`.** Insert after `setAdvisoryStatus` (line ~70), mirroring it exactly:
```php
    public function setReviewStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE package_releases SET review_status = ? WHERE id = ?', [$status, $id]);
    }
```

- [ ] **Step 4: Create `src/Service/Packages/PackageReviewConsoleService.php`** with the LOCKED constructor and both methods:
```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Local operator review console (P5-07-A / GA-DOD-09). Surfaces the signed
 * review evidence PackageAcquisitionService cached and records LOCAL manual
 * decisions bound to the exact package/release/version/digest. Local decisions
 * may only TIGHTEN: a local 'approved' cannot override a signed reject/revoke of
 * the same digest. Every mutation is WriteGate + password reauth + audit +
 * transparency, in one transaction.
 */
final class PackageReviewConsoleService
{
    private const DECISIONS = ['approved', 'rejected', 'revoked'];

    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageTransparencyLogRepository $transparency,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    /** @return array<int,array<string,mixed>> cached signed + local decisions, newest first. */
    public function decisionsFor(int $packageId): array
    {
        return $this->reviewDecisions->forPackage($packageId);
    }

    public function recordDecision(
        User $admin,
        string $currentPassword,
        int $packageId,
        int $releaseId,
        string $decision,
        ?string $note,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        if (!in_array($decision, self::DECISIONS, true)) {
            throw new ValidationException(['decision' => 'Choose approved, rejected, or revoked.']);
        }

        $package = $this->packages->find($packageId);
        if ($package === null) {
            throw new ValidationException(['package' => 'Package not found.']);
        }

        // Exact binding: the decision must name a release that belongs to this package.
        $release = $this->releases->find($releaseId);
        if ($release === null || (int) $release['package_id'] !== $packageId) {
            throw new ValidationException(['release_id' => 'Select a release of this package.']);
        }

        $note = $note === null ? '' : trim($note);
        if (mb_strlen($note) > 1000) {
            throw new ValidationException(['note' => 'Keep the review note under 1000 characters.'], ['note' => $note]);
        }

        $digest = (string) $release['digest'];
        $version = (string) $release['version'];

        // Tightening only: a local approval cannot override a signed reject/revoke of the same digest.
        if ($decision === 'approved' && $this->signedNegativeExists($packageId, $digest)) {
            throw new PackagePolicyException(
                'review_conflict',
                'A signed rejection or revocation already covers this digest; a local approval cannot override it.',
            );
        }

        return $this->db->transaction(function () use ($admin, $package, $packageId, $releaseId, $digest, $version, $decision, $note): int {
            $decisionId = $this->reviewDecisions->record([
                'package_id' => $packageId,
                'release_id' => $releaseId,
                'version' => $version,
                'digest' => $digest,
                'decision' => $decision,
                'decided_at' => gmdate('Y-m-d H:i:s'),
                'source' => 'local',
                'evidence_json' => json_encode([
                    'source' => 'local',
                    'reviewer_id' => $admin->id(),
                    'reviewer' => $admin->username(),
                    'note' => $note,
                    'decided_at' => gmdate('c'),
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $this->releases->setReviewStatus($releaseId, $decision);

            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => $version,
                'digest' => $digest,
                'event' => $decision === 'approved' ? 'release_verified' : 'revoked',
                'source' => 'local',
                'actor_id' => $admin->id(),
                'detail' => 'local_review=' . $decision,
            ]);

            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_review',
                'target_type' => 'package',
                'target_id' => $packageId,
                'after' => ['release_id' => $releaseId, 'digest' => $digest, 'decision' => $decision, 'source' => 'local'],
            ]);

            return $decisionId;
        });
    }

    /** True when a non-local (signed) reject/revoke already covers the exact digest. */
    private function signedNegativeExists(int $packageId, string $digest): bool
    {
        foreach ($this->reviewDecisions->forPackage($packageId) as $row) {
            if ((string) $row['digest'] === $digest
                && (string) $row['source'] !== 'local'
                && in_array((string) $row['decision'], ['rejected', 'revoked'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 5: Bind the service in `App::buildContainer()`.** Add the `use` import beside the other package-service imports (near line 198, `use App\Service\Packages\PackageLifecycleService;`):
```php
use App\Service\Packages\PackageReviewConsoleService;
```
Then add the bind next to the `PackageAcquisitionService::class` bind (~line 1287) — every collaborator is already `$c->get`-able:
```php
        $c->bind(PackageReviewConsoleService::class, fn (Container $c) => new PackageReviewConsoleService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageReviewDecisionRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
```

- [ ] **Step 6: Run the test; confirm it PASSES.**
```bash
vendor/bin/phpunit --filter PackageReviewConsoleServiceTest
```
Expected: `OK (4 tests, …)` — green. (Per `superpowers:verification-before-completion`, paste the real summary line, don't assert green from memory.)

- [ ] **Step 7: Commit the service slice.**
```bash
git add src/Service/Packages/PackageReviewConsoleService.php \
        src/Repository/PackageReleaseRepository.php \
        src/Core/App.php \
        tests/Integration/Service/PackageReviewConsoleServiceTest.php
git commit -m "$(cat <<'EOF'
feat(packages): add PackageReviewConsoleService for tightening-only local review decisions

Records provenance-bound (source=local, reviewer id), digest-exact operator
review decisions over the signed evidence cache. A local approve is refused
(PackagePolicyException review_conflict) when a signed reject/revoke covers the
digest; local reject/revoke always tighten. Updates package_releases.review_status
and writes transparency + moderation_log rows in one WriteGate + reauth transaction.
Advances GA-DOD-09.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

### Task 52: `recordReview` route + no-JS form + kernel/flag-dark/browser evidence

**Files:**
- Modify: `src/Controller/AdminPackageSecurityController.php` (append `recordReview` + `reviewErrorView`; create only if absent, and never replace existing publisher/security actions)
- Create: `templates/admin/_package_review_form.php` (no-JS per-release review form partial)
- Create: `tests/Integration/Core/AppPackageReviewTest.php` (full-kernel HTTP)
- Create: `tests/browser/package-review.spec.ts` (no-JS + axe evidence)
- Modify: `src/Core/App.php` (`use` import + one route in `buildRouter()`)
- Modify: `templates/admin/package_detail.php` (render the review-form partial in the releases table)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (add the flag-dark 404 row)
- Modify: `tests/browser/package.json` (add the spec to an evidence script)

**Interfaces:**

Consumes:
```php
App\Service\Packages\PackageReviewConsoleService::recordDecision(User, string, int, int, string, ?string): int
App\Service\Registry\RegistryCatalogService::detail(int $packageId): ?array   // reused to re-render package_detail at 422 (already used by AdminPackagesController::show)
App\Controller\Controller::requireAdmin(): User
App\Controller\Controller::redirectWithFlash(string $to, string $message): Response   // 303
App\Controller\Controller::view(string $template, array $data, int $status): Response
App\Core\Request::post(string $key, mixed $default): mixed / ::str(string $key): string
App\Core\View::partial(string $template, array $data): string
```

Produces:
```php
// POST /admin/packages/{id}/review  →  AdminPackageSecurityController::recordReview
public function recordReview(Request $request, array $params): Response;   // {id} = packageId
```
Behavior: `gate()` → `requireAdmin()` → `gate()` (`package_registry` else `NotFoundException` 404) → `noindex()`; success → 303 redirect to `/admin/packages/{id}` with a flash; `ValidationException`/`PackagePolicyException` → re-render `admin/package_detail` at **422** with `$e->errors` (PRG-free anti-draft-loss). Redirect target is the Inc 3 package-detail page (the publisher/security console GET pages are the later task group's; do not target them here).

- [ ] **Step 1: Write the failing full-kernel HTTP test.** Create `tests/Integration/Core/AppPackageReviewTest.php` (drives `App::handle` as a cookie-jar client; CSRF is automatic; asserts observable HTTP status/body + persisted `review_status`):
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\PackageReleaseRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The no-JS local-review form: reauth-gated, digest-tightening, 422 on refusal. */
final class AppPackageReviewTest extends TestCase
{
    private SigningHarness $root;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
    }

    private function reviewStatus(): string
    {
        return (string) (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['review_status'];
    }

    public function test_form_renders_on_the_package_detail_and_records_a_decision(): void
    {
        $show = $this->get('/admin/packages/' . $this->ids['package_id']);
        $this->assertStatus(200, $show);
        self::assertStringContainsString('/integration', $show->body(), 'detail page is the review host'); // sanity: real detail page
        self::assertStringContainsString('name="decision"', $show->body(), 'no-JS review form is server-rendered');

        $ok = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'revoked',
            'note' => 'local kill decision',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(303, $ok);
        self::assertSame('noindex', $ok->getHeader('x-robots-tag'));
        self::assertSame('revoked', $this->reviewStatus());
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_review'"));
    }

    public function test_wrong_password_re_renders_422_and_does_not_change_status(): void
    {
        $resp = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'revoked',
            'current_password' => 'wrong',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('password', strtolower($resp->body()));
        self::assertSame('approved', $this->reviewStatus(), 'seed status is untouched on a refused write');
    }

    public function test_local_approval_over_a_signed_reject_is_refused_422(): void
    {
        $release = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);
        $this->db->insert(
            'INSERT INTO package_review_decisions (package_id, release_id, version, digest, decision, decided_at, source)
             VALUES (?, ?, ?, ?, \'rejected\', UTC_TIMESTAMP(), \'advisory\')',
            [$this->ids['package_id'], $this->ids['release_id'], (string) $release['version'], (string) $release['digest']],
        );

        $resp = $this->post('/admin/packages/' . $this->ids['package_id'] . '/review', [
            'release_id' => (string) $this->ids['release_id'],
            'decision' => 'approved',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('review_conflict', $resp->body());
    }
}
```

- [ ] **Step 2: Run it; confirm it FAILS on the missing route/controller.**
```bash
vendor/bin/phpunit --filter AppPackageReviewTest
```
Expected: red — the GET assertion fails on missing form markup and the POST returns 404 (route unregistered).

- [ ] **Step 3: Append the review action to the cumulative controller.** Open `src/Controller/AdminPackageSecurityController.php`; create a shared shell only if it does not exist. Add the imports if missing and add the methods below inside the existing class. Do not remove `publisher*`, `index`, `emergencyDisable`, key actions, `gate()`, `noindex()`, or helpers already present.
```php
use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\PackageReviewConsoleService;
use App\Service\Registry\RegistryCatalogService;

    /** @param array<string,string> $params */
    public function recordReview(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);

        try {
            $this->container->get(PackageReviewConsoleService::class)->recordDecision(
                $admin,
                (string) $request->post('current_password', ''),
                $packageId,
                (int) $request->post('release_id', 0),
                $request->str('decision'),
                $request->str('note') !== '' ? $request->str('note') : null,
            );

            return $this->noindex($this->redirectWithFlash('/admin/packages/' . $packageId, 'Local review decision recorded.'));
        } catch (ValidationException $e) {
            return $this->reviewErrorView($packageId, $e->errors);
        } catch (PackagePolicyException $e) {
            return $this->reviewErrorView($packageId, ['review' => 'Refused (' . $e->code . '): ' . $e->getMessage()]);
        }
    }

    /** @param array<string,string> $errors */
    private function reviewErrorView(int $packageId, array $errors): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }

        return $this->noindex($this->view('admin/package_detail', $detail + ['errors' => $errors], 422));
    }
```

- [ ] **Step 4: Register the route.** In `App::buildRouter()`, add beside the other `/admin/packages/{id}/…` POSTs (after `.../uninstall`, ~line 1780) — `review` is a distinct literal segment so first-match ordering is unaffected:
```php
        $r->post('/admin/packages/{id}/review', [AdminPackageSecurityController::class, 'recordReview']);
```
and add the import near the other admin controllers (~line where `AdminPackagesController` is imported):
```php
use App\Controller\AdminPackageSecurityController;
```

- [ ] **Step 5: Create the no-JS form partial** `templates/admin/_package_review_form.php` (plain `<form method="post">`, server-rendered one-time reveal-free; CSP-safe — no inline script/style; `$package_id` + `$release` passed by the caller):
```php
<?php
/** @var int $package_id @var array<string,mixed> $release */
?>
<form method="post" action="/admin/packages/<?= (int) $package_id ?>/review" class="review-decision-form">
    <?= $this->csrfField() ?>
    <input type="hidden" name="release_id" value="<?= (int) $release['id'] ?>">
    <label>Local review decision
        <select name="decision" required>
            <option value="approved">approved</option>
            <option value="rejected">rejected</option>
            <option value="revoked">revoked</option>
        </select>
    </label>
    <label>Note (optional)
        <textarea name="note" rows="2" maxlength="1000"></textarea>
    </label>
    <label>Confirm with your password
        <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <button type="submit">Record decision</button>
</form>
```

- [ ] **Step 6: Render the partial in the releases table.** In `templates/admin/package_detail.php`, inside the per-release loop that already prints `$r['review_status']` (~line 69), add a cell that embeds the form for that release:
```php
                    <td><?= $this->partial('admin/_package_review_form', ['package_id' => $package['id'], 'release' => $r]) ?></td>
```
(Add a matching `<th>Local review</th>` to that table's header row.)

- [ ] **Step 7: Add the flag-dark 404 assertion.** In `tests/Integration/Core/AppFeatureFlagTest.php`, add one row to the `$method/$path/$body` loop inside `test_package_registry_flag_gates_catalog_and_registry_routes` so the mutation is 404 (not 403/405) while `package_registry` is off:
```php
            ['POST', '/admin/packages/1/review', ['decision' => 'approved', 'release_id' => '1', 'current_password' => 'password123']],
```

- [ ] **Step 8: Run kernel + flag tests; confirm PASS.**
```bash
vendor/bin/phpunit --filter 'AppPackageReviewTest|test_package_registry_flag_gates_catalog_and_registry_routes'
```
Expected: `OK` — both green (POST works, refusals re-render 422, route is dark while the flag is off). If the GET body sanity assertion (`/integration`) is brittle against the integration task group's landing order, relax it to only assert `name="decision"`.

- [ ] **Step 9: Run the full suite for regressions.**
```bash
composer test
```
Expected: the whole suite is green (the hand-built `PackageLifecycleService`/`PackageHealthService` test constructors still pass `null` for their `?PackageIntegrationService` seam; nothing here touches them). Paste the final summary line as evidence.

- [ ] **Step 10: Commit the route/UI/kernel slice.**
```bash
git add src/Controller/AdminPackageSecurityController.php \
        templates/admin/_package_review_form.php \
        templates/admin/package_detail.php \
        src/Core/App.php \
        tests/Integration/Core/AppPackageReviewTest.php \
        tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "$(cat <<'EOF'
feat(packages): no-JS local review-decision form + recordReview route (dark)

Wires POST /admin/packages/{id}/review to AdminPackageSecurityController::recordReview
(package_registry-gated → 404 while dark, reauth + CSRF, 422 re-render on refusal),
renders a server-rendered per-release review form on the package detail page, and
proves the flow end to end through the kernel. Advances GA-DOD-09.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 11: Add the browser evidence spec** `tests/browser/package-review.spec.ts` (no-JS submit + axe clean; mirrors `appeals.spec.ts` `visit`/`login` helpers; renders the review form on the seeded dark-surface package detail). Depends on the shared Inc 5 dark-surface package seed (`RB_BROWSER_DARK_SURFACES=1` `prepare.sh` seeds one installed package — established by the migration/integration task groups):
```ts
import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'node:path';

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', 'admin@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
}

test.describe('package local-review console (deploy-dark)', () => {
  test.skip(!process.env.RB_BROWSER_DARK_SURFACES, 'requires the dark-surface package seed');

  test('review form renders, is axe-clean, and records without JS', async ({ page }, info: TestInfo) => {
    await login(page);
    // Seeded dark-surface package id 1 (see prepare.sh dark branch).
    const resp = await page.goto('/admin/packages/1');
    expect(resp!.status()).toBeLessThan(400);
    await expect(page.locator('form.review-decision-form select[name="decision"]').first()).toBeVisible();

    const axe = await new AxeBuilder({ page }).analyze();
    expect(axe.violations, JSON.stringify(axe.violations, null, 2)).toEqual([]);

    const form = page.locator('form.review-decision-form').first();
    await form.locator('select[name="decision"]').selectOption('revoked');
    await form.locator('input[name="current_password"]').fill('password123');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('body')).toContainText('Local review decision recorded');
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, 'package-review.png'),
      fullPage: true,
    });
  });
});
```

- [ ] **Step 12: Wire the spec into the dark-surface evidence script.** In `tests/browser/package.json`, append `package-review.spec.ts` to the `a11y`/`evidence:dark` script list (the `RB_BROWSER_DARK_SURFACES=1` runs), e.g.:
```json
    "a11y": "RB_BROWSER_DARK_SURFACES=1 bash prepare.sh && playwright test a11y.spec.ts package-review.spec.ts",
```

- [ ] **Step 13: Capture the browser evidence** (separate track — not in PHPUnit CI, per CLAUDE.md):
```bash
cd tests/browser && RB_BROWSER_DARK_SURFACES=1 npx playwright test package-review.spec.ts
```
Expected: pass with `docs/evidence/browser/<project>/package-review.png` written and zero axe violations. If the shared dark-surface seed has no package yet, the test self-`skip`s (keeps the run green) and this step is deferred to the seed-owning task group — record that in the runbook rather than claiming captured evidence.

- [ ] **Step 14: Commit the browser evidence slice.**
```bash
git add tests/browser/package-review.spec.ts tests/browser/package.json
git commit -m "$(cat <<'EOF'
test(browser): no-JS + axe evidence for the local package-review form

Drives the server-rendered review-decision form on the dark-surface package
detail page: asserts it renders, is axe-clean, and records a decision with
JavaScript uninvolved. Runs on the RB_BROWSER_DARK_SURFACES evidence path.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

**Group exit deliverable:** `PackageReviewConsoleService` + the `recordReview` route/action, proven by `PackageReviewConsoleServiceTest` (decision provenance, exact-digest binding, tightening-only `review_conflict` guard, transparency + audit rows) and `AppPackageReviewTest` (no-JS form POST, reauth 422, flag-dark 404), with browser/axe evidence for the server-rendered form — an independently testable, committable slice advancing GA-DOD-09.

**Boundary notes for the orchestrator:** (1) `src/Controller/AdminPackageSecurityController.php` is cumulative; this group appends `recordReview`/`reviewErrorView` to the existing shell or creates the shell only if absent, and later security-console task groups must append their `index`/`publisher`/`emergencyDisable`/publisher-key actions without recreating it. (2) `templates/admin/package_detail.php` and `src/Core/App.php` receive adjacent edits from this group and the integration task group — different sections, no logical conflict. (3) `PackageReleaseRepository::setReviewStatus` is a new thin single-table setter added by this group (no other contract group modifies that repo). (4) This group is independent of migration `0073` — it uses only pre-existing schema (`package_review_decisions`/`package_transparency_log` from `0070`, `moderation_log.target_type='package'` from `0068`), so it may land before or after the migration group.


<!-- ===== group: security-response-emergency-disable — PackageSecurityResponseService + emergency disable + transparency ===== -->

### Task 56: Config break-glass + `PackageSecurityResponseService` emergency execution brake

**Files:**
- Modify: `config/config.php` (add `packages.execution_disabled` break-glass)
- Create: `src/Service/Packages/PackageSecurityResponseService.php` (constructor + `isExecutionDisabled()` + `setExecutionDisabled()` + private `activeIntegrationInstalls()`)
- Test: `tests/Integration/Service/PackageSecurityResponseServiceTest.php`

**Interfaces:**

*Consumes* (all landed by earlier groups #? migration/repo/publisher/integration-runtime; verify present before starting — `ls src/Repository/InstalledPackageCredentialRepository.php src/Repository/PublisherSigningKeyRepository.php src/Service/Packages/PackageIntegrationService.php` and `php bin/console migrate:status | grep 0073`):
```php
App\Repository\SettingRepository::get(string $key, mixed $default = null): mixed
App\Repository\SettingRepository::set(string $key, mixed $value): void          // json-encodes bool
App\Core\Config::get(string $key, mixed $default = null): mixed                  // dot path, e.g. 'packages.execution_disabled'
App\Security\ReauthGate::requirePassword(User $actor, string $currentPassword, string $field = 'current_password', ?string $missingPasswordError = null): void
App\Security\WriteGate::assertCanWrite(User $user): void
App\Service\Packages\PackageIntegrationService::suspendDelivery(int $installedId, string $reason): int   // defensive pause, no reauth
App\Repository\PackageTransparencyLogRepository::record(array $entry): int       // event ∈ {…,'force_disable'}, source ∈ {…,'local'}, detail VARCHAR(512)
App\Repository\ModerationLogRepository::log(array $entry): int                    // target_type='setting', target_id=0
App\Core\Database::transaction/fetchAll/fetchValue/insert/run
```
*Produces* (locked signatures — this task ships the first three; `overview`/`publisherDetail` land in Task 57):
```php
final class App\Service\Packages\PackageSecurityResponseService {
    public function __construct(
        private Database $db, private SettingRepository $settings,
        private RegistryAdvisoryService $advisories, private LocalBlocklistService $blocklist,
        private PackageHealthService $enforcement, private PackageIntegrationService $integrations,
        private PackagePublisherRepository $publishers, private PublisherSigningKeyRepository $publisherKeys,
        private PackageAdvisoryRepository $advisoryRepo, private LocalPackageBlockRepository $blockRepo,
        private PackageTransparencyLogRepository $transparency, private ReauthGate $reauth,
        private WriteGate $writeGate, private ModerationLogRepository $audit, private Config $config,
    );
    public function setExecutionDisabled(User $admin, string $currentPassword, bool $disabled, ?string $reason): int;
    public function isExecutionDisabled(): bool;   // DB setting OR packages.execution_disabled config break-glass
}
```

Design note: the emergency brake is deliberately **flag-independent** — enforcement (`isExecutionDisabled()` + the paused endpoints) works even while `package_registry` is dark; only the operator toggle route is flag-gated (owned by the security-controller group, which asserts the `/admin/packages/security/execution` 404-while-dark case in `AppFeatureFlagTest`). `setExecutionDisabled` is a high-impact operator control so it reauths in **both** directions; the internal `suspendDelivery` it triggers is defensive and skips reauth (Global Constraints). Mirrors the existing `ThemeStateService` safe-mode break-glass precedent.

- [ ] **Step 1: Write the failing integration test.** Create `tests/Integration/Service/PackageSecurityResponseServiceTest.php`. This file also carries the heavy construction helpers reused in Task 57 (mirrors `WebhookServiceTest::vault()` and `PackageLifecycleServiceTest`'s service builders):
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\Packages\ManifestValidator;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\SecretBox;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageHealthService;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageSecurityResponseService;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageSecurityResponseServiceTest extends TestCase
{
    private User $admin;
    private string $artifactDir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
        $this->artifactDir = sys_get_temp_dir() . '/rb-sec-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->artifactDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    private function flags(): FeatureFlags
    {
        return new FeatureFlags(new SettingRepository($this->db));
    }

    private function reauth(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            $this->flags(),
            $this->config,
        );
    }

    private function integrations(): PackageIntegrationService
    {
        return new PackageIntegrationService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new InstalledPackageSettingsRepository($this->db),
            new InstalledPackageCredentialRepository($this->db),
            new ApiTokenService($this->db, new ApiTokenRepository($this->db), new ModerationLogRepository($this->db), $this->flags(), $this->config, $this->reauth(), new WriteGate()),
            new WebhookService($this->db, new WebhookRepository($this->db), new WebhookDeliveryRepository($this->db), $this->vault(), new ModerationLogRepository($this->db), $this->flags(), $this->config, $this->reauth(), new WriteGate(), new EgressGuard(false, [])),
            new ApiTokenRepository($this->db),
            new WebhookRepository($this->db),
            $this->vault(),
            new ManifestValidator(),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new ModerationLogRepository($this->db),
            $this->reauth(),
            new WriteGate(),
            $this->flags(),
            new SettingRepository($this->db),
            $this->config,
        );
    }

    private function enforcement(): PackageHealthService
    {
        return new PackageHealthService(
            $this->db,
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ModerationLogRepository($this->db),
        );
    }

    private function securityService(?Config $config = null): PackageSecurityResponseService
    {
        return new PackageSecurityResponseService(
            $this->db,
            new SettingRepository($this->db),
            new RegistryAdvisoryService($this->db, new TrustChainVerifier(), new RegistryTrustKeyRepository($this->db), new PackageAdvisoryRepository($this->db), new PackageRepository($this->db), new PackageReleaseRepository($this->db), new ModerationLogRepository($this->db)),
            new LocalBlocklistService(new LocalPackageBlockRepository($this->db), new PackageRepository($this->db), $this->reauth(), new WriteGate(), new ModerationLogRepository($this->db)),
            $this->enforcement(),
            $this->integrations(),
            new PackagePublisherRepository($this->db),
            new PublisherSigningKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->reauth(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            $config ?? $this->config,
        );
    }

    /** Seed one enabled remote_app install + one package-owned webhook linked as a credential. @return array{install_id:int,webhook_id:int,package_uid:string} */
    private function seedEnabledIntegration(): array
    {
        $root = SigningHarness::generate('sec-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'type' => 'remote_app',
            'trust_class' => 'reviewed_remote',
            'publisher_uid' => 'acme-apps',
            'publisher_name' => 'Acme Apps',
            'package_uid' => 'acme/webhook-app',
            'name' => 'Webhook App',
        ]);
        $installs = new InstalledPackageRepository($this->db);
        $installId = $installs->create([
            'package_id' => $ids['package_id'],
            'release_id' => $ids['release_id'],
            'digest' => $ids['release_digest'],
            'source_registry_id' => $ids['registry_id'],
            'publisher_id' => $ids['publisher_id'],
            'trust_class' => 'reviewed_remote',
            'review_status' => 'approved',
            'compat_min' => null,
            'compat_max' => null,
            'installed_by' => $this->admin->id(),
        ]);
        $installs->setState($installId, 'enabled');

        $webhookId = (int) $this->db->insert(
            "INSERT INTO webhooks (name, url, events, secret_ref, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            ['acme-app-hook', 'https://app.acme.test/hooks', json_encode(['thread.created']), 'svcsec_' . str_repeat('b', 32), $this->admin->id()],
        );
        $this->db->insert(
            "INSERT INTO installed_package_credentials (installed_package_id, kind, webhook_id, label, events_json, created_by, created_at)
             VALUES (?, 'webhook', ?, 'events', ?, ?, UTC_TIMESTAMP())",
            [$installId, $webhookId, json_encode(['thread.created']), $this->admin->id()],
        );

        return ['install_id' => $installId, 'webhook_id' => $webhookId, 'package_uid' => 'acme/webhook-app'];
    }

    public function test_execution_disabled_predicate_reflects_setting_and_config_break_glass_independently_of_flag(): void
    {
        self::assertFalse($this->securityService()->isExecutionDisabled(), 'off by default');

        // Config break-glass forces it on even while package_registry is dark.
        $items = $this->config->all();
        $items['packages']['execution_disabled'] = true;
        $breakGlass = $this->securityService(new Config($items));

        self::assertFalse($this->flags()->enabled('package_registry'), 'package_registry is dark');
        self::assertTrue($breakGlass->isExecutionDisabled(), 'config break-glass is flag-independent');
    }

    public function test_emergency_disable_requires_reauth_and_leaves_runtime_live_on_bad_password(): void
    {
        $svc = $this->securityService();
        try {
            $svc->setExecutionDisabled($this->admin, 'WRONG-PASSWORD', true, 'panic');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertFalse($svc->isExecutionDisabled(), 'runtime stays live when reauth fails');
    }

    public function test_emergency_disable_pauses_package_owned_delivery_appends_transparency_and_preserves_management(): void
    {
        $seed = $this->seedEnabledIntegration();
        $svc = $this->securityService();

        $affected = $svc->setExecutionDisabled($this->admin, 'password123', true, 'incident-42');
        self::assertSame(1, $affected, 'one active integration install affected');

        // Runtime suppressed: the predicate the delivery worker/dispatch + credential-auth consult is on.
        self::assertTrue($svc->isExecutionDisabled());
        // Belt: the package-owned webhook is paused so the existing delivery worker naturally skips it.
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_active FROM webhooks WHERE id = ?', [$seed['webhook_id']]));
        // Transparency append per affected install.
        $entries = (new PackageTransparencyLogRepository($this->db))->forPackageUid($seed['package_uid'], 10);
        self::assertNotSame([], array_filter($entries, static fn (array $r): bool => $r['event'] === 'force_disable'));
        // Management preserved while disabled: the install is not torn down (operator can still view/revoke/export/uninstall).
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($seed['install_id'])['state']);

        // Re-enabling clears the brake (does not auto-resume delivery).
        $svc->setExecutionDisabled($this->admin, 'password123', false, null);
        self::assertFalse($svc->isExecutionDisabled());
        $actions = array_column((new ModerationLogRepository($this->db))->recent(20), 'action');
        self::assertContains('package_execution_disabled', $actions);
        self::assertContains('package_execution_enabled', $actions);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails for the right reason.**
```bash
vendor/bin/phpunit --filter PackageSecurityResponseServiceTest
```
Expected: `Error: Class "App\Service\Packages\PackageSecurityResponseService" not found` (or an autoload error) — a hard FAIL, not a skip. If it errors earlier on a missing prerequisite (`InstalledPackageCredentialRepository`, `PublisherSigningKeyRepository`, `PackageIntegrationService`, or the `0073` tables), STOP — the predecessor task group has not landed; do not proceed.

- [ ] **Step 3: Add the `packages.execution_disabled` break-glass to config.** Edit `config/config.php`, extending the existing `'packages'` block:
```php
    'packages' => [
        // Verified release documents, content-addressed by sha256. Never web-served.
        'storage_path' => Env::get('PACKAGES_STORAGE_PATH', dirname(__DIR__) . '/storage/packages'),
        // Default uninstall retention window when the manifest declares none.
        'retention_days' => (int) Env::get('PACKAGES_RETENTION_DAYS', '30'),
        // Flag-independent emergency break-glass: OR'd with the package_execution_disabled
        // DB setting to pause every package-owned runtime bridge (Inc 5 Global Constraints).
        'execution_disabled' => Env::bool('PACKAGE_EXECUTION_DISABLED', false),
    ],
```

- [ ] **Step 4: Create the service with the brake + predicate.** Create `src/Service/Packages/PackageSecurityResponseService.php`:
```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Config;
use App\Core\Database;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Repository\SettingRepository;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;

/**
 * Local operator security-response console read model + the flag-independent
 * emergency execution brake. Reuses — never duplicates — the advisory
 * ({@see RegistryAdvisoryService}), blocklist ({@see LocalBlocklistService}),
 * and health-enforcement ({@see PackageHealthService}) services; those are held
 * so the console's advisory/blocklist/force-disable actions delegate to the
 * existing source-of-truth logic instead of re-deriving it. The emergency brake
 * is a plain DB setting OR'd with a break-glass config so it holds even while
 * `package_registry` is dark. Mirrors the ThemeStateService safe-mode precedent.
 */
final class PackageSecurityResponseService
{
    private const SETTING_KEY = 'package_execution_disabled';

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private RegistryAdvisoryService $advisories,
        private LocalBlocklistService $blocklist,
        private PackageHealthService $enforcement,
        private PackageIntegrationService $integrations,
        private PackagePublisherRepository $publishers,
        private PublisherSigningKeyRepository $publisherKeys,
        private PackageAdvisoryRepository $advisoryRepo,
        private LocalPackageBlockRepository $blockRepo,
        private PackageTransparencyLogRepository $transparency,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private Config $config,
    ) {
    }

    /** DB setting OR the packages.execution_disabled config break-glass. Flag-independent. */
    public function isExecutionDisabled(): bool
    {
        if ((bool) $this->settings->get(self::SETTING_KEY, false)) {
            return true;
        }

        return (bool) $this->config->get('packages.execution_disabled', false);
    }

    /**
     * Flag-independent emergency execution brake. Reauths in both directions.
     * On disable: pauses every active integration install's package-owned
     * webhooks (so the delivery worker/dispatch skip them) and records
     * transparency; the runtime credential-auth path denies via isExecutionDisabled().
     * Never blocks view/revoke/export/uninstall — install lifecycle state is untouched.
     *
     * @return int affected active integration installs (0 when re-enabling; no auto-resume)
     */
    public function setExecutionDisabled(User $admin, string $currentPassword, bool $disabled, ?string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $installs = $this->activeIntegrationInstalls();
        $cleanReason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255);

        $this->db->transaction(function () use ($admin, $disabled, $cleanReason, $installs): void {
            $this->settings->set(self::SETTING_KEY, $disabled);

            if ($disabled) {
                foreach ($installs as $install) {
                    // Defensive pause (no reauth): is_active=0 so the existing delivery worker skips it.
                    $this->integrations->suspendDelivery((int) $install['id'], 'package execution disabled');
                    $this->transparency->record([
                        'package_uid' => (string) $install['package_uid'],
                        'version' => $install['release_version'] ?? null,
                        'digest' => $install['digest'] ?? null,
                        'event' => 'force_disable',
                        'source' => 'local',
                        'actor_id' => $admin->id(),
                        'detail' => json_encode(
                            ['reason' => 'package_execution_disabled', 'note' => $cleanReason],
                            JSON_UNESCAPED_SLASHES,
                        ),
                    ]);
                }
            }

            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $disabled ? 'package_execution_disabled' : 'package_execution_enabled',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => $cleanReason,
                'after' => ['disabled' => $disabled, 'affected_installs' => count($installs)],
            ]);
        });

        return $disabled ? count($installs) : 0;
    }

    /** @return list<array<string,mixed>> enabled remote_app/automation installs (integration bridges) */
    private function activeIntegrationInstalls(): array
    {
        return $this->db->fetchAll(
            "SELECT ip.id, ip.digest, p.package_uid, r.version AS release_version
               FROM installed_packages ip
               JOIN packages p ON p.id = ip.package_id
               LEFT JOIN package_releases r ON r.id = ip.release_id
              WHERE ip.state = 'enabled'
                AND p.type IN ('remote_app', 'automation')
              ORDER BY ip.id",
        );
    }
}
```

- [ ] **Step 5: Run the test and confirm green.**
```bash
vendor/bin/phpunit --filter PackageSecurityResponseServiceTest
```
Expected: `OK (3 tests, N assertions)` — all three methods pass.

- [ ] **Step 6: Guard against regressions, then commit.** Confirm the config change and reuse of `webhooks`/transparency didn't disturb neighbours:
```bash
vendor/bin/phpunit tests/Integration/Service/RegistryAdvisoryServiceTest.php tests/Integration/Service/WebhookServiceTest.php
git add config/config.php src/Service/Packages/PackageSecurityResponseService.php tests/Integration/Service/PackageSecurityResponseServiceTest.php
git commit -m "$(cat <<'EOF'
feat(packages): flag-independent emergency execution brake (Inc 5)

Add PackageSecurityResponseService::setExecutionDisabled/isExecutionDisabled
and the packages.execution_disabled config break-glass. Disabling pauses every
active remote_app/automation install's package-owned webhooks (delivery worker
skips is_active=0) and denies credential auth via the predicate, while leaving
view/revoke/export/uninstall intact. Deploy-dark; advances GA-DOD-09.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 57: Security-console read model (`overview` / `publisherDetail`) with advisory/blocklist/publisher delegation

**Files:**
- Modify: `src/Service/Packages/PackageSecurityResponseService.php` (add `overview()` + `publisherDetail()`)
- Modify: `tests/Integration/Service/PackageSecurityResponseServiceTest.php` (add two read-model tests)
- Modify: `docs/phase5/requirement-ledger.json` (advance `GA-DOD-09` R2→R3 with evidence)

**Interfaces:**

*Consumes* (repositories are the single source of truth — the console never re-derives advisory/blocklist rows):
```php
App\Repository\PackagePublisherRepository::all(): array          // ORDER BY display_name (added by publisher-trust group)
App\Repository\PackagePublisherRepository::find(int $id): ?array
App\Repository\PackagePublisherRepository::packagesFor(int $publisherId): array
App\Repository\PublisherSigningKeyRepository::forPublisher(int $publisherId): array
App\Repository\PackageAdvisoryRepository::all(): array           // joins packages.package_uid
App\Repository\LocalPackageBlockRepository::all(): array
App\Repository\PackageTransparencyLogRepository::all(int $limit = 200): array
```
*Produces* (locked signatures):
```php
public function overview(?\DateTimeImmutable $now = null): array;   // {publishers, advisories, blocklist, transparency, execution_disabled:bool, affected_installs:int}
public function publisherDetail(int $publisherId): ?array;          // {publisher, keys, packages, decisions}|null
```

- [ ] **Step 1: Add the two failing read-model tests.** Append these methods to `PackageSecurityResponseServiceTest` (reuses the Task 56 helpers/imports):
```php
    public function test_overview_lists_publishers_advisories_and_blocklist_from_the_shared_sources(): void
    {
        $root = SigningHarness::generate('ov-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'publisher_uid' => 'globex',
            'publisher_name' => 'Globex',
            'package_uid' => 'globex/plugin',
        ]);
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'adv-ov-1',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'high',
            'action' => 'warn',
            'summary' => 'test advisory',
            'signed_evidence' => '{}',
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]);
        (new LocalPackageBlockRepository($this->db))->add(str_repeat('a', 64), 'globex/plugin', 'manual block', $this->admin->id());

        $overview = $this->securityService()->overview();

        self::assertNotSame([], array_filter($overview['publishers'], static fn (array $r): bool => $r['publisher_uid'] === 'globex'));
        self::assertNotSame([], array_filter($overview['advisories'], static fn (array $r): bool => $r['advisory_uid'] === 'adv-ov-1'));
        self::assertNotSame([], array_filter($overview['blocklist'], static fn (array $r): bool => $r['package_uid'] === 'globex/plugin'));
        self::assertFalse($overview['execution_disabled']);
        self::assertIsInt($overview['affected_installs']);
    }

    public function test_publisher_detail_returns_records_or_null_for_unknown(): void
    {
        $root = SigningHarness::generate('pd-root');
        $ids = RegistryFixtures::seed($this->db, $root, $this->artifactDir, [
            'publisher_uid' => 'initech',
            'publisher_name' => 'Initech',
            'package_uid' => 'initech/app',
        ]);
        $svc = $this->securityService();

        $detail = $svc->publisherDetail($ids['publisher_id']);
        self::assertNotNull($detail);
        self::assertSame('initech', $detail['publisher']['publisher_uid']);
        self::assertNotSame([], array_filter($detail['packages'], static fn (array $r): bool => $r['package_uid'] === 'initech/app'));

        self::assertNull($svc->publisherDetail(999999));
    }
```

- [ ] **Step 2: Run the new tests and confirm they fail for the right reason.**
```bash
vendor/bin/phpunit --filter 'test_overview_lists_publishers_advisories_and_blocklist_from_the_shared_sources|test_publisher_detail_returns_records_or_null_for_unknown'
```
Expected FAIL: `Error: Call to undefined method App\Service\Packages\PackageSecurityResponseService::overview()`.

- [ ] **Step 3: Implement the read model.** Add these two methods to `PackageSecurityResponseService` (before `activeIntegrationInstalls()`):
```php
    /**
     * /admin/packages/security read model. Publisher/advisory/blocklist rows come
     * straight from the shared repositories — this console is a viewer, not a
     * second source of truth.
     *
     * @return array{publishers:list<array<string,mixed>>, advisories:list<array<string,mixed>>, blocklist:list<array<string,mixed>>, transparency:list<array<string,mixed>>, execution_disabled:bool, affected_installs:int}
     */
    public function overview(?\DateTimeImmutable $now = null): array
    {
        return [
            'publishers' => $this->publishers->all(),
            'advisories' => $this->advisoryRepo->all(),
            'blocklist' => $this->blockRepo->all(),
            'transparency' => $this->transparency->all(50),
            'execution_disabled' => $this->isExecutionDisabled(),
            'affected_installs' => count($this->activeIntegrationInstalls()),
        ];
    }

    /**
     * @return array{publisher:array<string,mixed>, keys:list<array<string,mixed>>, packages:list<array<string,mixed>>, decisions:list<array<string,mixed>>}|null
     */
    public function publisherDetail(int $publisherId): ?array
    {
        $publisher = $this->publishers->find($publisherId);
        if ($publisher === null) {
            return null;
        }

        return [
            'publisher' => $publisher,
            'keys' => $this->publisherKeys->forPublisher($publisherId),
            'packages' => $this->publishers->packagesFor($publisherId),
            'decisions' => [],   // per-package review decisions are merged by the controller via PackageReviewConsoleService
        ];
    }
```

- [ ] **Step 4: Run and confirm the whole file is green.**
```bash
vendor/bin/phpunit --filter PackageSecurityResponseServiceTest
```
Expected: `OK (5 tests, N assertions)`.

- [ ] **Step 5: Advance `GA-DOD-09` in the requirement ledger.** Edit `docs/phase5/requirement-ledger.json`, replacing the single-line `GA-DOD-09` entry — flip `state` R2→R3 and append the two evidence paths:
```
    { "id": "GA-DOD-09", "gate": "A", "workstream": "P5-07", "title": "Exact-digest maintainer review, automated evidence, manual approval, publication, advisory, emergency disable", "state": "R3", "evidence": ["src/Service/Packages/PackageAcquisitionService.php", "src/Service/Packages/PackageHealthService.php", "tests/Integration/Service/PackageAcquisitionServiceTest.php", "tests/Integration/Worker/PackageHealthWorkerTest.php", "docs/phase5/registry-protocol.md", "src/Service/Packages/PackageSecurityResponseService.php", "tests/Integration/Service/PackageSecurityResponseServiceTest.php"], "notes": "Install-side exact-digest enforcement + review-evidence cache landed Inc 3; Inc 5 landed the console read model + flag-independent emergency execution brake." },
```
Then confirm valid JSON and a minimal diff:
```bash
python3 -c "import json; json.load(open('docs/phase5/requirement-ledger.json')); print('valid')"
git diff --stat docs/phase5/requirement-ledger.json
```
Expected: `valid` and a single-line change.

- [ ] **Step 6: Commit the read-model deliverable.**
```bash
git add src/Service/Packages/PackageSecurityResponseService.php tests/Integration/Service/PackageSecurityResponseServiceTest.php docs/phase5/requirement-ledger.json
git commit -m "$(cat <<'EOF'
feat(packages): security-console read model + GA-DOD-09 R3 (Inc 5)

Add PackageSecurityResponseService::overview/publisherDetail, surfacing
publishers, advisories, blocklist, transparency, execution_disabled, and
affected-install count straight from the shared repositories (no duplicated
advisory/blocklist logic). Advance GA-DOD-09 R2->R3 with test evidence.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
EOF
)"
```

Group deliverable: `PackageSecurityResponseService` is complete and independently green — the emergency brake proves runtime suppression (webhook `is_active=0` + the `isExecutionDisabled()` predicate the delivery worker/dispatch and credential-auth consult) while preserving management (install lifecycle untouched, revoke/export/uninstall intact), delegates advisory/blocklist/publisher reads to the existing services/repositories, and appends a `force_disable` transparency entry per affected install. Wiring into the container, the `/admin/packages/security/execution` route + its `AppFeatureFlagTest` 404-while-dark assertion, the `package_security.php` template, and `package-security.spec.ts` browser/axe evidence are owned by the security-controller group (this group is pure service + config and adds no HTTP surface of its own).


<!-- ===== group: security-console-ux — Security-response console UX — no-JS + browser/a11y ===== -->


---

### Task 61: Security-response console overview + flag-independent emergency execution brake

**Files:**
- Modify: `src/Controller/AdminPackageSecurityController.php` (append `index`/`emergencyDisable` + `security()`/`consoleView()` helpers; never replace existing publisher/review/key actions)
- Create: `templates/admin/package_security.php`
- Modify: `src/Core/App.php` (add `use App\Controller\AdminPackageSecurityController;`; register `GET /admin/packages/security` + `POST /admin/packages/security/execution` **before** `GET /admin/packages/{id}`)
- Test: `tests/Integration/Core/AppPackageSecurityConsoleTest.php` (new; console render, reauth-gated brake, flag-dark 404)

**Interfaces:**
- Consumes (built by the security-response-service group, already bound in `App::buildContainer()`):
  ```php
  PackageSecurityResponseService::overview(?\DateTimeImmutable $now = null): array; // {publishers, advisories, blocklist, transparency, execution_disabled:bool, affected_installs:int}
  PackageSecurityResponseService::setExecutionDisabled(User $admin, string $currentPassword, bool $disabled, ?string $reason): int; // reauth; @return affected installs
  ```
- Consumes base `Controller` helpers: `requireAdmin(): User`, `view()`, `redirectWithFlash()`, `request()`.
- Produces controller actions `(Request $request, array $params): Response`: `index`, `emergencyDisable`.
- Produces routes: `$r->get('/admin/packages/security', …)`, `$r->post('/admin/packages/security/execution', …)`.

Steps:

- [ ] **Step 1: Write the failing integration test.** Create `tests/Integration/Core/AppPackageSecurityConsoleTest.php` seeding a publisher via the shared Phase 5 fixture (same pattern as `AppPackageLifecycleTest`):
  ```php
  <?php

  declare(strict_types=1);

  namespace Tests\Integration\Core;

  use App\Repository\SettingRepository;
  use Tests\Support\Phase5\RegistryFixtures;
  use Tests\Support\Phase5\SigningHarness;
  use Tests\Support\TestCase;

  final class AppPackageSecurityConsoleTest extends TestCase
  {
      private SigningHarness $root;
      /** @var array<string,mixed> */
      private array $seeded;
      private string $artifactDir;
      /** @var array<string,mixed> */
      private array $admin;

      protected function setUp(): void
      {
          parent::setUp();
          $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages-security';
          $this->root = SigningHarness::generate();
          $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
          (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
          $this->admin = $this->makeAdmin(['password' => 'password123']);
      }

      protected function tearDown(): void
      {
          foreach (glob($this->artifactDir . '/*.json') ?: [] as $file) {
              unlink($file);
          }
          parent::tearDown();
      }

      public function test_console_renders_overview_with_the_seeded_publisher(): void
      {
          $this->actingAs($this->admin);
          $response = $this->get('/admin/packages/security');
          $this->assertStatus(200, $response);
          $this->assertSeeText($response, 'Package security response');
          $this->assertSeeText($response, 'Acme Themes');
          self::assertSame('noindex', $response->getHeader('x-robots-tag'));
      }

      public function test_emergency_disable_requires_reauth_then_pauses_execution(): void
      {
          $this->actingAs($this->admin);

          $bad = $this->post('/admin/packages/security/execution', [
              'disabled' => '1',
              'current_password' => 'wrong',
          ]);
          $this->assertStatus(422, $bad);
          $this->assertSeeText($bad, 'password is incorrect');

          $ok = $this->post('/admin/packages/security/execution', [
              'disabled' => '1',
              'current_password' => 'password123',
              'reason' => 'incident-42',
          ]);
          $this->assertRedirectContains($ok, '/admin/packages/security');
          $this->assertSeeText($this->get('/admin/packages/security'), 'Package execution is halted');
      }

      public function test_console_routes_are_dark_without_the_flag(): void
      {
          (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
          $this->actingAs($this->admin);
          $this->assertStatus(404, $this->get('/admin/packages/security'));
          $this->assertStatus(404, $this->post('/admin/packages/security/execution', [
              'disabled' => '1',
              'current_password' => 'password123',
          ]));
      }
  }
  ```
- [ ] **Step 2: Run it — expect FAIL (route/action missing).** `vendor/bin/phpunit --filter 'AppPackageSecurityConsoleTest'` → expect the security route to 404 where the test expects 200/422, or action-method errors if the cumulative controller exists without these methods. This proves the test exercises unbuilt code without requiring the class itself to be absent.
- [ ] **Step 3: Append the overview/brake actions to the cumulative controller.** Open `src/Controller/AdminPackageSecurityController.php`. Add the import if missing and add the methods below inside the existing class. If the file is absent, create the shared shell with `gate()`/`noindex()` first, then add these methods. Do not remove `recordReview`, `publisher*`, key actions, or existing helpers.
  ```php
  use App\Service\Packages\PackageSecurityResponseService;

      /** @param array<string,string> $params */
      public function index(Request $request, array $params): Response
      {
          $this->gate();
          $this->requireAdmin();
          $this->gate();

          return $this->consoleView();
      }

      /** @param array<string,string> $params */
      public function emergencyDisable(Request $request, array $params): Response
      {
          $this->gate();
          $admin = $this->requireAdmin();
          $this->gate();
          $disabled = $request->post('disabled', '0') === '1';

          try {
              $this->security()->setExecutionDisabled(
                  $admin,
                  (string) $request->post('current_password', ''),
                  $disabled,
                  $request->str('reason') !== '' ? $request->str('reason') : null,
              );

              return $this->noindex($this->redirectWithFlash(
                  '/admin/packages/security',
                  $disabled
                      ? 'Package execution disabled: package-owned webhooks paused and credentials denied.'
                      : 'Package execution resumed.',
              ));
          } catch (ValidationException $e) {
              return $this->consoleView($e->errors, $request->allInput(), 422);
          }
      }

      private function security(): PackageSecurityResponseService
      {
          return $this->container->get(PackageSecurityResponseService::class);
      }

      /**
       * @param array<string,string> $errors
       * @param array<string,mixed> $old
       */
      private function consoleView(array $errors = [], array $old = [], int $status = 200): Response
      {
          $overview = $this->security()->overview();

          return $this->noindex($this->view('admin/package_security', $overview + [
              'errors' => $errors,
              'old' => $old,
              ], $status));
      }
  ```
- [ ] **Step 4: Create the console template.** Write `templates/admin/package_security.php` — a no-JS overview with the emergency brake form (renders both the `current_password` reauth error and any policy `execution` error), publishers table linking to detail, an advisories/blocklist card that **links to `/admin/registries`** (no duplicated controls), and a transparency table:
  ```php
  <?php /** @var \App\Core\View $this */ ?>
  <?php
  $this->layout('layout');
  $this->section('title', 'Package security response');
  ?>
  <div class="admin">
      <header class="admin-head">
          <h1>Package security response</h1>
          <span class="pill pill-admin">Admin mode</span>
      </header>
      <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

      <div class="admin-pane">
      <p class="muted">The emergency brake applies regardless of the package flag. Advisory ingest, acknowledgement, and the local blocklist live on the <a href="/admin/registries">registry trust console</a>.</p>

      <section class="card">
          <h2>Emergency execution brake
              <?= $execution_disabled ? '<span class="pill pill-admin">disabled</span>' : '<span class="pill">live</span>' ?></h2>
          <p class="muted"><?php if ($execution_disabled): ?>Package execution is halted: <?= (int) $affected_installs ?> integration install(s) paused. Operators can still view, revoke, export, and uninstall.<?php else: ?>Package-owned webhooks and credentials are live for <?= (int) $affected_installs ?> integration install(s).<?php endif; ?></p>
          <?php foreach (['execution', 'current_password'] as $ek): ?><?php if (!empty($errors[$ek])): ?><p class="field-error"><?= $e($errors[$ek]) ?></p><?php endif; ?><?php endforeach; ?>
          <form method="post" action="/admin/packages/security/execution" class="inline-form">
              <?= $this->csrfField() ?>
              <input type="hidden" name="disabled" value="<?= $execution_disabled ? '0' : '1' ?>">
              <input type="text" name="reason" placeholder="Reason (optional)" value="<?= $e($old['reason'] ?? '') ?>">
              <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
              <button class="btn" type="submit"><?= $execution_disabled ? 'Resume package execution' : 'Emergency-disable all packages' ?></button>
          </form>
      </section>

      <section class="card">
          <h2>Publishers</h2>
          <div class="table-scroll" tabindex="0" role="region" aria-label="Publishers">
          <table class="audit">
              <thead><tr><th>Publisher</th><th>Status</th><th>Verified</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($publishers as $pub): ?>
                  <tr>
                      <td><?= $e($pub['display_name']) ?> <code><?= $e($pub['publisher_uid']) ?></code></td>
                      <td><?= $e($pub['status'] ?? 'active') ?></td>
                      <td><?= $pub['verified_at'] !== null ? $e($pub['verified_at']) . ' UTC' : 'unverified' ?></td>
                      <td><a class="btn" href="/admin/packages/publishers/<?= (int) $pub['id'] ?>">Manage</a></td>
                  </tr>
              <?php endforeach; ?>
              </tbody>
          </table>
          </div>
      </section>

      <section class="card">
          <h2>Advisories &amp; blocklist</h2>
          <p class="muted"><?= count($advisories) ?> advisory record(s), <?= count($blocklist) ?> local block(s). Ingest, acknowledge, and block on the <a href="/admin/registries">registry trust console</a>.</p>
      </section>

      <section class="card">
          <h2>Transparency log</h2>
          <div class="table-scroll" tabindex="0" role="region" aria-label="Transparency log">
          <table class="audit">
              <thead><tr><th>When</th><th>Event</th><th>Detail</th></tr></thead>
              <tbody>
              <?php foreach ($transparency as $row): ?>
                  <tr><td class="nowrap"><?= $e($row['created_at']) ?></td><td><?= $e($row['event']) ?></td><td><?= $e($row['detail'] ?? '') ?></td></tr>
              <?php endforeach; ?>
              </tbody>
          </table>
          </div>
      </section>
      </div>
  </div>
  ```
- [ ] **Step 5: Register the import in `App.php`.** Edit the `use` block — anchor on `use App\Controller\AdminPackagesController;` and insert the new import immediately above it:
  ```php
  use App\Controller\AdminPackageSecurityController;
  use App\Controller\AdminPackagesController;
  ```
- [ ] **Step 6: Register the routes before the numeric detail route.** In `buildRouter()`, anchor on the two-line pair `$r->get('/admin/packages', …)` / `$r->get('/admin/packages/{id}', …)` and insert the security routes between them (non-numeric GET first, per the contract):
  ```php
          $r->get('/admin/packages', [AdminPackagesController::class, 'index']);
          // Security-response console (P5-07-A): non-numeric GETs registered before the numeric detail route.
          $r->get('/admin/packages/security', [AdminPackageSecurityController::class, 'index']);
          $r->post('/admin/packages/security/execution', [AdminPackageSecurityController::class, 'emergencyDisable']);
          $r->get('/admin/packages/{id}', [AdminPackagesController::class, 'show']);
  ```
- [ ] **Step 7: Run the test to PASS.** `vendor/bin/phpunit --filter 'AppPackageSecurityConsoleTest'` → expect `OK (3 tests)`. If the reauth message assertion fails, confirm the template renders `$errors['current_password']` (the `ReauthGate` error key) as shown in Step 4.
- [ ] **Step 8: Guard the wider suite.** `vendor/bin/phpunit --testsuite integration --filter 'Package'` → expect green (no route-ordering regression in the existing package/registry tests).
- [ ] **Step 9: Commit.**
  ```bash
  git add src/Controller/AdminPackageSecurityController.php templates/admin/package_security.php src/Core/App.php tests/Integration/Core/AppPackageSecurityConsoleTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): security-response console overview + emergency execution brake

  Deploy-dark /admin/packages/security console reading PackageSecurityResponseService::overview,
  with a reauth-gated, flag-independent emergency execution brake. noindex + CSRF; links to the
  registry advisory/blocklist controls rather than duplicating them.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 62: Publisher detail + verify/suspend/reinstate lifecycle actions

**Files:**
- Modify: `src/Controller/AdminPackageSecurityController.php` (add `publisher`, `verifyPublisher`, `suspendPublisher`, `reinstatePublisher` + `publishers()`/`publisherView()`/`publisherAction()` helpers)
- Create: `templates/admin/package_publisher.php`
- Modify: `src/Core/App.php` (register `GET /admin/packages/publishers/{id}` before `/{id}`, plus the three publisher POSTs)
- Test: `tests/Integration/Core/AppPackageSecurityConsoleTest.php` (add publisher-detail + suspend-reauth + flag-dark cases)

**Interfaces:**
- Consumes (built by the publisher-trust group, already bound):
  ```php
  PublisherTrustService::verifyPublisher(User $admin, string $currentPassword, int $publisherId): void;
  PublisherTrustService::suspendPublisher(User $admin, string $currentPassword, int $publisherId, string $reason): int;
  PublisherTrustService::reinstatePublisher(User $admin, string $currentPassword, int $publisherId): void;
  PackageSecurityResponseService::publisherDetail(int $publisherId): ?array; // {publisher, keys, packages, decisions}|null
  ```
- Produces controller actions `publisher`, `verifyPublisher`, `suspendPublisher`, `reinstatePublisher`.
- Produces routes: `GET /admin/packages/publishers/{id}`, `POST …/{id}/verify`, `…/{id}/suspend`, `…/{id}/reinstate`.

Steps:

- [ ] **Step 10: Add the failing tests.** Append to `AppPackageSecurityConsoleTest`:
  ```php
      public function test_publisher_detail_renders_and_suspend_requires_reauth(): void
      {
          $this->actingAs($this->admin);
          $pid = (int) $this->seeded['publisher_id'];

          $detail = $this->get('/admin/packages/publishers/' . $pid);
          $this->assertStatus(200, $detail);
          $this->assertSeeText($detail, 'Acme Themes');

          $bad = $this->post('/admin/packages/publishers/' . $pid . '/suspend', [
              'reason' => 'compromised key',
              'current_password' => 'nope',
          ]);
          $this->assertStatus(422, $bad);
          $this->assertSeeText($bad, 'password is incorrect');

          $ok = $this->post('/admin/packages/publishers/' . $pid . '/suspend', [
              'reason' => 'compromised key',
              'current_password' => 'password123',
          ]);
          $this->assertRedirectContains($ok, '/admin/packages/publishers/' . $pid);
          $this->assertSeeText($this->get('/admin/packages/publishers/' . $pid), 'suspended');
      }

      public function test_publisher_routes_are_dark_without_the_flag(): void
      {
          (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
          $this->actingAs($this->admin);
          $pid = (int) $this->seeded['publisher_id'];
          $this->assertStatus(404, $this->get('/admin/packages/publishers/' . $pid));
          $this->assertStatus(404, $this->post('/admin/packages/publishers/' . $pid . '/verify', ['current_password' => 'password123']));
          $this->assertStatus(404, $this->post('/admin/packages/publishers/' . $pid . '/suspend', ['reason' => 'x', 'current_password' => 'password123']));
          $this->assertStatus(404, $this->post('/admin/packages/publishers/' . $pid . '/reinstate', ['current_password' => 'password123']));
      }
  ```
- [ ] **Step 11: Run — expect FAIL.** `vendor/bin/phpunit --filter 'test_publisher_detail_renders_and_suspend_requires_reauth'` → expect 404 where 200 is expected (route/action missing).
- [ ] **Step 12: Add imports + actions to the controller.** Add `use App\Domain\User;`, `use App\Service\Registry\PublisherTrustService;` to `AdminPackageSecurityController`, then add:
  ```php
      /** @param array<string,string> $params */
      public function publisher(Request $request, array $params): Response
      {
          $this->gate();
          $this->requireAdmin();
          $this->gate();

          return $this->publisherView((int) ($params['id'] ?? 0));
      }

      /** @param array<string,string> $params */
      public function verifyPublisher(Request $request, array $params): Response
      {
          return $this->publisherAction(
              $request,
              (int) ($params['id'] ?? 0),
              fn (User $admin, string $pw, int $pid): mixed =>
                  $this->publishers()->verifyPublisher($admin, $pw, $pid),
              'Publisher verified.',
          );
      }

      /** @param array<string,string> $params */
      public function suspendPublisher(Request $request, array $params): Response
      {
          return $this->publisherAction(
              $request,
              (int) ($params['id'] ?? 0),
              fn (User $admin, string $pw, int $pid): mixed =>
                  $this->publishers()->suspendPublisher($admin, $pw, $pid, $request->str('reason')),
              'Publisher suspended; dependent installs force-disabled.',
          );
      }

      /** @param array<string,string> $params */
      public function reinstatePublisher(Request $request, array $params): Response
      {
          return $this->publisherAction(
              $request,
              (int) ($params['id'] ?? 0),
              fn (User $admin, string $pw, int $pid): mixed =>
                  $this->publishers()->reinstatePublisher($admin, $pw, $pid),
              'Publisher reinstated (installs are not auto-re-enabled).',
          );
      }

      private function publishers(): PublisherTrustService
      {
          return $this->container->get(PublisherTrustService::class);
      }

      /**
       * @param callable(User,string,int):mixed $call
       */
      private function publisherAction(Request $request, int $publisherId, callable $call, string $flash): Response
      {
          $this->gate();
          $admin = $this->requireAdmin();
          $this->gate();

          try {
              $call($admin, (string) $request->post('current_password', ''), $publisherId);

              return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, $flash));
          } catch (ValidationException $e) {
              return $this->publisherView($publisherId, $e->errors, $request->allInput(), 422);
          } catch (\App\Security\Packages\PackagePolicyException | \App\Security\Registry\RegistryVerificationException $e) {
              return $this->publisherView($publisherId, ['publisher' => $e->code . ': ' . $e->getMessage()], $request->allInput(), 422);
          }
      }

      /**
       * @param array<string,string> $errors
       * @param array<string,mixed> $old
       */
      private function publisherView(int $publisherId, array $errors = [], array $old = [], int $status = 200): Response
      {
          $detail = $this->security()->publisherDetail($publisherId);
          if ($detail === null) {
              throw new NotFoundException('Publisher not found.');
          }

          return $this->noindex($this->view('admin/package_publisher', $detail + [
              'errors' => $errors,
              'old' => $old,
          ], $status));
      }
  ```
- [ ] **Step 13: Create the publisher-detail template.** Write `templates/admin/package_publisher.php` — status header, verify/suspend/reinstate reauth forms, a read-only signing-keys table, and packages/decisions display (key/rotate/review forms are added in Task 63):
  ```php
  <?php /** @var \App\Core\View $this */ ?>
  <?php
  $this->layout('layout');
  $this->section('title', 'Publisher trust');
  $status = (string) ($publisher['status'] ?? 'active');
  ?>
  <div class="admin">
      <header class="admin-head">
          <h1>Publisher: <?= $e($publisher['display_name']) ?></h1>
          <span class="pill pill-admin">Admin mode</span>
      </header>
      <?= $this->partial('admin/_nav', ['active' => 'packages', 'features' => $features ?? []]) ?>

      <div class="admin-pane">
      <p class="muted"><a href="/admin/packages/security">&larr; Security response</a> · <code><?= $e($publisher['publisher_uid']) ?></code> · status <strong><?= $e($status) ?></strong> · <?= $publisher['verified_at'] !== null ? 'verified ' . $e($publisher['verified_at']) . ' UTC' : 'unverified' ?></p>
      <?php foreach (['publisher', 'current_password', 'envelope', 'review'] as $ek): ?><?php if (!empty($errors[$ek])): ?><p class="field-error"><?= $e($errors[$ek]) ?></p><?php endif; ?><?php endforeach; ?>

      <section class="card">
          <h2>Trust status</h2>
          <div class="form-row">
              <?php if ($status !== 'active'): ?>
              <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/verify" class="inline-form">
                  <?= $this->csrfField() ?>
                  <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
                  <button class="btn" type="submit">Verify publisher</button>
              </form>
              <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/reinstate" class="inline-form">
                  <?= $this->csrfField() ?>
                  <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
                  <button class="btn" type="submit">Reinstate publisher</button>
              </form>
              <?php else: ?>
              <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/suspend" class="inline-form">
                  <?= $this->csrfField() ?>
                  <input type="text" name="reason" placeholder="Suspension reason" required>
                  <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
                  <button class="btn" type="submit">Suspend &amp; force-disable installs</button>
              </form>
              <?php endif; ?>
          </div>
      </section>

      <section class="card">
          <h2>Signing keys</h2>
          <div class="table-scroll" tabindex="0" role="region" aria-label="Publisher signing keys">
          <table class="audit">
              <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($keys as $key): ?>
                  <tr>
                      <td class="nowrap"><code><?= $e($key['key_id']) ?></code></td>
                      <td><?= $e($key['status']) ?></td>
                      <td><?= $e($key['valid_from'] ?? 'inf') ?> to <?= $e($key['valid_until'] ?? 'inf') ?></td>
                      <td class="form-cell"></td>
                  </tr>
              <?php endforeach; ?>
              </tbody>
          </table>
          </div>
      </section>

      <section class="card">
          <h2>Packages &amp; review decisions</h2>
          <?php foreach ($packages as $pkg): ?>
          <h3><?= $e($pkg['name']) ?> <code><?= $e($pkg['package_uid']) ?></code></h3>
          <?php endforeach; ?>
          <?php if ($decisions === []): ?><p class="muted">No review decisions recorded.</p><?php endif; ?>
          <?php foreach ($decisions as $d): ?>
          <p class="muted"><?= $e($d['decision']) ?> · <?= $e($d['source'] ?? 'local') ?> · <?= $e($d['version'] ?? '') ?></p>
          <?php endforeach; ?>
      </section>
      </div>
  </div>
  ```
- [ ] **Step 14: Register the publisher routes.** In `buildRouter()`, extend the security block (anchor on the `security/execution` POST added in Task 61) so the non-numeric publisher GET precedes `/{id}`:
  ```php
          $r->get('/admin/packages/security', [AdminPackageSecurityController::class, 'index']);
          $r->get('/admin/packages/publishers/{id}', [AdminPackageSecurityController::class, 'publisher']);
          $r->post('/admin/packages/security/execution', [AdminPackageSecurityController::class, 'emergencyDisable']);
          $r->post('/admin/packages/publishers/{id}/verify', [AdminPackageSecurityController::class, 'verifyPublisher']);
          $r->post('/admin/packages/publishers/{id}/suspend', [AdminPackageSecurityController::class, 'suspendPublisher']);
          $r->post('/admin/packages/publishers/{id}/reinstate', [AdminPackageSecurityController::class, 'reinstatePublisher']);
          $r->get('/admin/packages/{id}', [AdminPackagesController::class, 'show']);
  ```
- [ ] **Step 15: Run to PASS.** `vendor/bin/phpunit --filter 'AppPackageSecurityConsoleTest'` → expect `OK (5 tests)`. The `suspended` assertion confirms the write is observable within the test (per-test transaction, not rolled back mid-test).
- [ ] **Step 16: Commit.**
  ```bash
  git add src/Controller/AdminPackageSecurityController.php templates/admin/package_publisher.php src/Core/App.php tests/Integration/Core/AppPackageSecurityConsoleTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): publisher trust detail + verify/suspend/reinstate console actions

  No-JS /admin/packages/publishers/{id} detail wired to PublisherTrustService; suspend cascades
  force-disable via the security response service. Reauth in the service, 422 anti-draft-loss
  re-render in the controller, noindex + CSRF throughout.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 63: Signing-key lifecycle (pin / signed rotation / revoke) + publisher-page review form reuse

**Files:**
- Modify: `src/Controller/AdminPackageSecurityController.php` (add `pinPublisherKey`, `rotatePublisherKey`, `revokePublisherKey` + `parseEnvelope()` helper; do not add or replace `recordReview`)
- Modify: `templates/admin/package_publisher.php` (add pin/rotation forms, per-key revoke form, per-package review form)
- Modify: `src/Core/App.php` (register the three new key POST routes; keep the Task 52 `/admin/packages/{id}/review` route registered once)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (extend the `package_registry` dark loop with every new security route)
- Test: `tests/Integration/Core/AppPackageSecurityConsoleTest.php` (add envelope-422 + key-reauth-422 + dark cases)

**Interfaces:**
- Consumes (already bound by the publisher-trust / review-console groups):
  ```php
  PublisherTrustService::pinKey(User $admin, string $currentPassword, int $publisherId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int;
  PublisherTrustService::applyKeyRotation(User $admin, string $currentPassword, int $publisherId, string $documentJson, string $signature, string $keyId): int;
  PublisherTrustService::revokeKey(User $admin, string $currentPassword, int $keyRowId, string $reason): void;
  PublisherSigningKeyRepository::find(int $id): ?array;   // resolve publisher_id for revoke re-render
  ```
- Produces controller actions `pinPublisherKey`, `rotatePublisherKey`, `revokePublisherKey`. The local review POST remains the single `recordReview` action from Task 52; this task only adds publisher-page forms that submit to that existing route.
- Produces routes: `POST …/publishers/{id}/keys`, `…/publishers/{id}/rotate`, `POST /admin/publisher-keys/{id}/revoke`; verify `POST /admin/packages/{id}/review` remains registered once from Task 52.

Steps:

- [ ] **Step 17: Add the failing tests.** Append to `AppPackageSecurityConsoleTest`:
  ```php
      public function test_key_pin_and_rotation_are_reauth_and_envelope_guarded(): void
      {
          $this->actingAs($this->admin);
          $pid = (int) $this->seeded['publisher_id'];

          // A malformed rotation envelope is a 422 re-render, never a 500.
          $rot = $this->post('/admin/packages/publishers/' . $pid . '/rotate', [
              'envelope' => 'not-json',
              'current_password' => 'password123',
          ]);
          $this->assertStatus(422, $rot);
          $this->assertSeeText($rot, 'JSON envelope');

          // Pinning with the wrong password is refused (reauth lives in the service).
          $pin = $this->post('/admin/packages/publishers/' . $pid . '/keys', [
              'key_id' => 'pub-key-2',
              'public_key' => base64_encode(str_repeat("\x01", 32)),
              'current_password' => 'wrong',
          ]);
          $this->assertStatus(422, $pin);
          $this->assertSeeText($pin, 'password is incorrect');
      }

      public function test_key_and_review_routes_are_dark_without_the_flag(): void
      {
          (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
          $this->actingAs($this->admin);
          $pid = (int) $this->seeded['publisher_id'];
          $packageId = (int) $this->seeded['package_id'];
          $this->assertStatus(404, $this->post('/admin/packages/publishers/' . $pid . '/keys', ['key_id' => 'k', 'public_key' => 'x', 'current_password' => 'password123']));
          $this->assertStatus(404, $this->post('/admin/packages/publishers/' . $pid . '/rotate', ['envelope' => '{}', 'current_password' => 'password123']));
          $this->assertStatus(404, $this->post('/admin/publisher-keys/1/revoke', ['reason' => 'x', 'current_password' => 'password123']));
          $this->assertStatus(404, $this->post('/admin/packages/' . $packageId . '/review', ['release_id' => (string) $this->seeded['release_id'], 'decision' => 'approved', 'current_password' => 'password123']));
      }
  ```
- [ ] **Step 18: Run — expect FAIL.** `vendor/bin/phpunit --filter 'test_key_pin_and_rotation_are_reauth_and_envelope_guarded'` → expect 404 where 422 is expected (actions/routes missing).
- [ ] **Step 19: Add imports + actions to the controller.** Add `use App\Repository\PublisherSigningKeyRepository;`, then add the key actions below (reusing the exact `parseEnvelope` shape from `AdminRegistryController::rotate`). Do not add a second `recordReview`; if the method is missing, stop and run Task 52.
  ```php
      /** @param array<string,string> $params */
      public function pinPublisherKey(Request $request, array $params): Response
      {
          $this->gate();
          $admin = $this->requireAdmin();
          $this->gate();
          $publisherId = (int) ($params['id'] ?? 0);

          try {
              $this->publishers()->pinKey(
                  $admin,
                  (string) $request->post('current_password', ''),
                  $publisherId,
                  $request->str('key_id'),
                  $request->str('public_key'),
                  $request->str('valid_from') !== '' ? $request->str('valid_from') : null,
                  $request->str('valid_until') !== '' ? $request->str('valid_until') : null,
              );

              return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, 'Publisher key pinned.'));
          } catch (ValidationException $e) {
              return $this->publisherView($publisherId, $e->errors, $e->old + $request->allInput(), 422);
          }
      }

      /** @param array<string,string> $params */
      public function rotatePublisherKey(Request $request, array $params): Response
      {
          $this->gate();
          $admin = $this->requireAdmin();
          $this->gate();
          $publisherId = (int) ($params['id'] ?? 0);

          try {
              [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
              $this->publishers()->applyKeyRotation(
                  $admin,
                  (string) $request->post('current_password', ''),
                  $publisherId,
                  $document,
                  $signature,
                  $keyId,
              );

              return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, 'Publisher key rotation applied: successor pinned, old key retired.'));
          } catch (ValidationException $e) {
              return $this->publisherView($publisherId, $e->errors, $request->allInput(), 422);
          } catch (\App\Security\Registry\RegistryVerificationException $e) {
              return $this->publisherView($publisherId, ['envelope' => 'Rotation refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
          }
      }

      /** @param array<string,string> $params */
      public function revokePublisherKey(Request $request, array $params): Response
      {
          $this->gate();
          $admin = $this->requireAdmin();
          $this->gate();
          $keyRowId = (int) ($params['id'] ?? 0);
          $key = $this->container->get(PublisherSigningKeyRepository::class)->find($keyRowId);
          if ($key === null) {
              throw new NotFoundException('Signing key not found.');
          }
          $publisherId = (int) $key['publisher_id'];

          try {
              $this->publishers()->revokeKey(
                  $admin,
                  (string) $request->post('current_password', ''),
                  $keyRowId,
                  $request->str('reason'),
              );

              return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, 'Publisher key revoked; everything it signed now fails closed.'));
          } catch (ValidationException $e) {
              return $this->publisherView($publisherId, $e->errors, $request->allInput(), 422);
          }
      }

      /** @return array{0:string,1:string,2:string} document, raw signature bytes, key id */
      private function parseEnvelope(string $raw): array
      {
          $decoded = json_decode(trim($raw), true);
          $signature = is_array($decoded) ? base64_decode((string) ($decoded['signature'] ?? ''), true) : false;
          if (!is_array($decoded) || !is_string($decoded['document'] ?? null) || $signature === false) {
              throw new ValidationException(['envelope' => 'Paste the JSON envelope: {"document": "...", "signature": "<base64>", "key_id": "..."}']);
          }

          return [(string) $decoded['document'], $signature, (string) ($decoded['key_id'] ?? '')];
      }
  ```
- [ ] **Step 20: Register the three key POST routes and verify the existing review route.** In `buildRouter()`, anchor on the `…/reinstate` route (from Task 62) and add the key POSTs after it. Confirm the Task 52 review route already exists once in the security block; do not duplicate it:
  ```php
          $r->post('/admin/packages/publishers/{id}/reinstate', [AdminPackageSecurityController::class, 'reinstatePublisher']);
          $r->post('/admin/packages/publishers/{id}/keys', [AdminPackageSecurityController::class, 'pinPublisherKey']);
          $r->post('/admin/packages/publishers/{id}/rotate', [AdminPackageSecurityController::class, 'rotatePublisherKey']);
          $r->post('/admin/publisher-keys/{id}/revoke', [AdminPackageSecurityController::class, 'revokePublisherKey']);
          // Existing from Task 52; keep exactly once, do not add a duplicate.
          $r->post('/admin/packages/{id}/review', [AdminPackageSecurityController::class, 'recordReview']);
  ```
- [ ] **Step 21: Add the key + review forms to the publisher template.** In `templates/admin/package_publisher.php`, (a) fill the empty `form-cell` in the keys table with a per-key revoke form gated on `$key['status'] !== 'revoked'`:
  ```php
                      <td class="form-cell">
                          <?php if ($key['status'] !== 'revoked'): ?>
                          <form method="post" action="/admin/publisher-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form">
                              <?= $this->csrfField() ?>
                              <input type="text" name="reason" placeholder="Revocation reason" required>
                              <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
                              <button class="btn" type="submit">Revoke</button>
                          </form>
                          <?php endif; ?>
                      </td>
  ```
  and (b) add a pin form + signed-rotation form (in `<details>`) below the keys table, plus a per-package record-review form in the packages card:
  ```php
          <details>
              <summary>Pin a new signing key</summary>
              <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/keys" class="stacked">
                  <?= $this->csrfField() ?>
                  <label>Key id <input type="text" name="key_id" maxlength="190" value="<?= $e($old['key_id'] ?? '') ?>" required></label>
                  <?php if (!empty($errors['key_id'])): ?><p class="field-error"><?= $e($errors['key_id']) ?></p><?php endif; ?>
                  <label>Public key (base64, 32 bytes) <input type="text" name="public_key" value="<?= $e($old['public_key'] ?? '') ?>" required></label>
                  <?php if (!empty($errors['public_key'])): ?><p class="field-error"><?= $e($errors['public_key']) ?></p><?php endif; ?>
                  <label>Valid from (UTC, optional) <input type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($old['valid_from'] ?? '') ?>"></label>
                  <label>Valid until (UTC, optional) <input type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e($old['valid_until'] ?? '') ?>"></label>
                  <label>Your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                  <button class="btn" type="submit">Pin key</button>
              </form>
          </details>
          <details>
              <summary>Apply a signed key rotation</summary>
              <form method="post" action="/admin/packages/publishers/<?= (int) $publisher['id'] ?>/rotate" class="stacked">
                  <?= $this->csrfField() ?>
                  <label>Rotation envelope (JSON) <textarea name="envelope" rows="4" placeholder='{"document":"...","signature":"<base64>","key_id":"..."}'><?= $e($old['envelope'] ?? '') ?></textarea></label>
                  <label>Your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                  <button class="btn" type="submit">Apply rotation</button>
              </form>
          </details>
  ```
  Replace the plain packages loop with one that offers the review form when a latest release exists:
  ```php
          <?php foreach ($packages as $pkg): ?>
          <h3><?= $e($pkg['name']) ?> <code><?= $e($pkg['package_uid']) ?></code></h3>
          <?php if (!empty($pkg['latest_release_id'])): ?>
          <form method="post" action="/admin/packages/<?= (int) $pkg['id'] ?>/review" class="inline-form">
              <?= $this->csrfField() ?>
              <input type="hidden" name="release_id" value="<?= (int) $pkg['latest_release_id'] ?>">
              <label for="decision-<?= (int) $pkg['id'] ?>">Decision</label>
              <select id="decision-<?= (int) $pkg['id'] ?>" name="decision">
                  <option value="approved">approved</option>
                  <option value="rejected">rejected</option>
                  <option value="revoked">revoked</option>
              </select>
              <input type="text" name="note" placeholder="Evidence note (optional)">
              <input type="password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
              <button class="btn" type="submit">Record local decision</button>
          </form>
          <?php endif; ?>
          <?php endforeach; ?>
  ```
- [ ] **Step 22: Run to PASS.** `vendor/bin/phpunit --filter 'AppPackageSecurityConsoleTest'` → expect `OK (7 tests)`.
- [ ] **Step 23: Extend the shared flag-dark matrix.** In `tests/Integration/Core/AppFeatureFlagTest.php::test_package_registry_flag_gates_catalog_and_registry_routes`, append every new security route to the existing `foreach ([...] as [$method, $path, $body])` array (anchor on the `['POST', '/admin/packages/1/reverify', []],` line):
  ```php
              ['POST', '/admin/packages/1/reverify', []],
              ['GET', '/admin/packages/security', []],
              ['POST', '/admin/packages/security/execution', ['disabled' => '1', 'current_password' => 'password123']],
              ['GET', '/admin/packages/publishers/1', []],
              ['POST', '/admin/packages/publishers/1/verify', ['current_password' => 'password123']],
              ['POST', '/admin/packages/publishers/1/suspend', ['reason' => 'x', 'current_password' => 'password123']],
              ['POST', '/admin/packages/publishers/1/reinstate', ['current_password' => 'password123']],
              ['POST', '/admin/packages/publishers/1/keys', ['key_id' => 'k', 'public_key' => 'x', 'current_password' => 'password123']],
              ['POST', '/admin/packages/publishers/1/rotate', ['envelope' => '{}', 'current_password' => 'password123']],
              ['POST', '/admin/publisher-keys/1/revoke', ['reason' => 'x', 'current_password' => 'password123']],
              ['POST', '/admin/packages/1/review', ['release_id' => '1', 'decision' => 'approved', 'current_password' => 'password123']],
  ```
- [ ] **Step 24: Run the flag-dark test.** `vendor/bin/phpunit --filter test_package_registry_flag_gates_catalog_and_registry_routes` → expect green (every new security route 404s while dark; `gate()` runs before and after `requireAdmin()` so unauthenticated and authenticated callers see 404, not 403/405).
- [ ] **Step 25: Full integration sweep.** `vendor/bin/phpunit --testsuite integration` → expect green (no route-ordering or strict-mode regressions).
- [ ] **Step 26: Commit.**
  ```bash
  git add src/Controller/AdminPackageSecurityController.php templates/admin/package_publisher.php src/Core/App.php tests/Integration/Core/AppPackageSecurityConsoleTest.php tests/Integration/Core/AppFeatureFlagTest.php
  git commit -m "$(cat <<'EOF'
  feat(packages): publisher signing-key lifecycle console actions

  Adds pin / signed-rotation (envelope) / revoke key actions and publisher-page
  forms that reuse the Task 52 review POST. Extends AppFeatureFlagTest so every
  new security route is asserted dark (404) under package_registry=off.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

---

### Task 64: Browser + axe evidence, script wiring, and GA-DOD-09 ledger advance

**Files:**
- Create: `tests/browser/package-security.spec.ts`
- Modify: `tests/browser/package.json` (add `evidence:packages` + prodlike script running the new spec under `RB_BROWSER_DARK_SURFACES=1`)
- Modify: `docs/phase5/requirement-ledger.json` (advance `GA-DOD-09` R2→R3 with evidence paths)
- Modify: `docs/evidence/deploy-dark-features.md` (note the live-behind-flag security-response console routes)

**Interfaces:**
- Consumes the seeded dark-surface fixtures: `tests/browser/seed.php` seeds `package_registry=true` and (under `RB_BROWSER_DARK_SURFACES=1`) the `Acme Themes` publisher + packages, and the operator `admin@retro.test` / `password123`.
- Produces no-JS + axe browser evidence for `/admin/packages/security` and `/admin/packages/publishers/{id}` (emergency-brake toggle round-trip, publisher detail).

Steps:

- [ ] **Step 27: Write the browser spec.** Create `tests/browser/package-security.spec.ts` (no-JS journey: console → toggle brake off/on → publisher detail, with axe scans and screenshots), modeled on `appeals.spec.ts`/`a11y.spec.ts`:
  ```ts
  import AxeBuilder from '@axe-core/playwright';
  import { expect, test, type Page, type TestInfo } from '@playwright/test';
  import path from 'node:path';

  const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

  async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
    await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
  }

  async function visit(page: Page, url: string): Promise<void> {
    const resp = await page.goto(url);
    expect(resp, `no response for ${url}`).not.toBeNull();
    expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
  }

  async function login(page: Page, email: string): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.locator('input[name="email"]').waitFor({ state: 'visible' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.endsWith('/login'));
    const skip = page.getByRole('button', { name: 'Skip' });
    if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
  }

  async function expectNoSeriousA11y(page: Page, info: TestInfo, include?: string): Promise<void> {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    if (include !== undefined) builder = builder.include(include);
    const results = await builder.analyze();
    const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
  }

  test('operator drives the no-JS package security console and toggles the emergency brake', async ({ page }, info) => {
    await login(page, 'admin@retro.test');

    await visit(page, '/admin/packages/security');
    await expect(page.getByRole('heading', { name: 'Package security response' })).toBeVisible();
    await expect(page.getByText('Acme Themes').first()).toBeVisible();
    await expectNoSeriousA11y(page, info);
    await shot(page, info, '60-package-security-console');

    // Flip the flag-independent emergency brake (plain form POST -> PRG redirect).
    const brake = page.locator('form[action="/admin/packages/security/execution"]');
    await brake.locator('input[name="current_password"]').fill('password123');
    await brake.getByRole('button', { name: /Emergency-disable all packages/ }).click();
    await page.waitForURL(/\/admin\/packages\/security$/);
    await expect(page.locator('.flash')).toContainText('Package execution disabled');
    await expect(page.getByText('Package execution is halted')).toBeVisible();
    await expectNoSeriousA11y(page, info);

    // Resume so a serial mobile pass starts from a live console.
    const resume = page.locator('form[action="/admin/packages/security/execution"]');
    await resume.locator('input[name="current_password"]').fill('password123');
    await resume.getByRole('button', { name: /Resume package execution/ }).click();
    await page.waitForURL(/\/admin\/packages\/security$/);
    await expect(page.locator('.flash')).toContainText('Package execution resumed');

    // Publisher detail (trust status + keys + lifecycle forms) also clears axe.
    await page.getByRole('link', { name: 'Manage' }).first().click();
    await page.waitForURL(/\/admin\/packages\/publishers\/\d+$/);
    await expect(page.getByRole('heading', { name: /Publisher:/ })).toBeVisible();
    await expectNoSeriousA11y(page, info);
    await shot(page, info, '61-package-publisher-detail');
  });
  ```
- [ ] **Step 28: Wire the evidence scripts.** In `tests/browser/package.json`, add the dark-surface package script (publisher rows are only seeded under `RB_BROWSER_DARK_SURFACES=1`) alongside the existing `a11y` scripts:
  ```json
      "evidence:packages": "RB_BROWSER_DARK_SURFACES=1 bash prepare.sh && playwright test package-security.spec.ts",
      "evidence:packages:prodlike": "RB_BROWSER_DARK_SURFACES=1 npm run prepare-db:prodlike && RB_BROWSER_DARK_SURFACES=1 bash prodlike-env.sh playwright test package-security.spec.ts",
  ```
- [ ] **Step 29: Run the browser evidence (desktop + mobile).** `cd tests/browser && npm install && npx playwright install --with-deps chromium && npm run evidence:packages` → expect the spec green in both projects, with `docs/evidence/browser/<project>/60-package-security-console.png` and `61-package-publisher-detail.png` written and zero serious/critical axe violations. (If the `Acme Themes` text isn't found, confirm `RB_BROWSER_DARK_SURFACES=1` propagated to `prepare.sh` so `RegistryFixtures::seed` ran.)
- [ ] **Step 30: Advance the requirement ledger.** In `docs/phase5/requirement-ledger.json`, edit the `GA-DOD-09` object: change `"state": "R2"` → `"R3"`, append the console evidence paths, and update `notes`:
  ```json
      { "id": "GA-DOD-09", "gate": "A", "workstream": "P5-07", "title": "Exact-digest maintainer review, automated evidence, manual approval, publication, advisory, emergency disable", "state": "R3", "evidence": ["src/Service/Packages/PackageAcquisitionService.php", "src/Service/Packages/PackageHealthService.php", "tests/Integration/Service/PackageAcquisitionServiceTest.php", "tests/Integration/Worker/PackageHealthWorkerTest.php", "docs/phase5/registry-protocol.md", "src/Controller/AdminPackageSecurityController.php", "templates/admin/package_security.php", "templates/admin/package_publisher.php", "tests/Integration/Core/AppPackageSecurityConsoleTest.php", "tests/browser/package-security.spec.ts"], "notes": "Inc 5: security-response console (publisher trust, signing-key lifecycle, local review decisions, flag-independent emergency execution brake) + HTTP/browser/axe evidence landed; publication side remains registry-operator tooling." }
  ```
- [ ] **Step 31: Note the live-behind-flag routes in the deploy-dark inventory.** In `docs/evidence/deploy-dark-features.md`, add a line under the `package_registry` section recording the new admin surface (match the file's existing bullet/row format):
  ```md
  - Security-response console (`/admin/packages/security`, `/admin/packages/publishers/{id}`, publisher/key/review/emergency-disable POSTs) — deploy-dark behind `package_registry`; flag-off 404s asserted in `AppFeatureFlagTest`; no-JS + axe evidence in `tests/browser/package-security.spec.ts`.
  ```
- [ ] **Step 32: Final green gate.** `composer test` → expect the full PHPUnit suite green (`verification-before-completion`: capture the summary line before claiming done).
- [ ] **Step 33: Commit.**
  ```bash
  git add tests/browser/package-security.spec.ts tests/browser/package.json docs/phase5/requirement-ledger.json docs/evidence/deploy-dark-features.md
  git commit -m "$(cat <<'EOF'
  test(packages): no-JS + axe browser evidence for the security-response console

  Adds package-security.spec.ts (console overview, emergency-brake toggle round-trip, publisher
  detail; axe clean desktop+mobile) with an evidence:packages script, and advances GA-DOD-09 R2->R3
  in the requirement ledger + deploy-dark inventory.

  Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
  EOF
  )"
  ```

**Group deliverable:** the security-response console is fully wired to `PackageSecurityResponseService` / `PublisherTrustService` / `PackageReviewConsoleService` behind `package_registry`, with HTTP evidence (`AppPackageSecurityConsoleTest`), the cross-cutting flag-dark 404 matrix (`AppFeatureFlagTest`), and no-JS + axe browser evidence (`package-security.spec.ts`) all green; direct-POST reauth/envelope denials and flag-dark denials are asserted; `GA-DOD-09` is at R3.


<!-- ===== group: evidence-closeout — Evidence, threat fixtures, runbooks, ledger & SCHEMA closeout ===== -->

The evidence-closeout group uses the pre-existing `ThreatModelIndexTest`, `Phase5EvidenceMapTest`, and `MigrationLedgerTest` guards; the four fixture IDs are already documented in their dossiers.

---

### Task 66: Author `docs/runbooks/package_integrations.md` + append the security-response section to `package_registry.md`

**Files:**
- Create: `docs/runbooks/package_integrations.md`
- Modify: `docs/runbooks/package_registry.md` (append `## Package Integrations & Security-Response Console (Inc 5)`)
- Test: `tests/Unit/Core/Phase5EvidenceMapTest.php` (existing guard — this runbook becomes `GA-DOD-08` evidence in Task 69, so the file must exist on disk or that guard goes red)

**Interfaces:**
- Consumes (documents, does not redefine) the locked signatures: `PackageIntegrationService::provisionCredentials(User, string $currentPassword, int $installedId): array` → `{api_token:?string, webhook_secret:?string, credentials:list}`; `rotateCredential(...)`; `revokeCredential(...)`; `onInstallIneligible(int, string $reason, ?int): void` with `reason ∈ disabled|uninstalled|quarantined|force_disabled|emergency_disabled`; `PackageSecurityResponseService::setExecutionDisabled(User, string $currentPassword, bool, ?string): int`; `PublisherTrustService::{verifyPublisher,suspendPublisher,reinstatePublisher,pinKey,applyKeyRotation,revokeKey}`.
- Produces: no code — a runbook consumed by operators + cited as ledger/evidence-map evidence.

- [ ] **Step 1: Confirm the runbook does not yet exist and the sibling runbook is present.** Run `ls docs/runbooks/package_integrations.md; ls docs/runbooks/package_registry.md` — expect the first to print `No such file or directory` and the second to list. This pins the create-vs-modify split.
- [ ] **Step 2: Write `docs/runbooks/package_integrations.md` with the operator procedures.** Real content:
  ```markdown
  # Runbook: Package Integrations & Security Response (Phase 5 Increment 5, Deploy-Dark)

  Covers the `remote_app` / declarative `automation` integration runtime and the
  local security-response console. All surfaces gate on `package_registry`
  (`FeatureFlags::enabled('package_registry')` fails dark → 404); no new flag.

  ## Hard runtime predecessors
  - `service_secrets` **must** be enabled to mint any credential or write a secret
    setting. With the vault off, `PackageSettingsService::save()` and
    `PackageIntegrationService::provisionCredentials()` throw `ValidationException`
    (fail closed). Reveal / revoke / export still work while dark.
  - Only installs of type `remote_app`/`automation` in state `enabled` with zero
    ungranted scopes/events/hosts and not blocked/quarantined/revoked/emergency-
    disabled can provision. Manifest = ceiling; `installed_package_permissions`
    (`declared=1 AND granted=1`) = authority.

  ## Provision / rotate / revoke credentials
  - **Provision** (`/admin/packages/{id}/integration/provision`): reauth
    (`current_password`) + CSRF. Mints ≤1 read-only `api_token` (granted
    `api_scope`s) and ≤1 `webhook` (granted `event`s, URL host ∈ granted
    `outbound_host`s), atomically in one `$db->transaction`. Plaintext token +
    webhook secret render **once**, server-side; they are never re-derivable.
  - **Rotate** (`/credentials/{credentialId}/rotate`): reauth. api_token =
    revoke+mint new; webhook = `WebhookService::rotateSecret`. New secret shown once.
  - **Revoke** (`/credentials/{credentialId}/revoke`): WriteGate only (no reauth —
    defensive). Idempotent.

  ## Secret handling
  Secret settings (`type=string, secret:true`) store into `SecretVault`; only the
  `svcsec_*` reference persists. Plaintext is never returned by settings/overview
  reads, never written to `moderation_log`/`package_history`/
  `package_transparency_log`, never in exception messages (TM-SE-02/04/05).

  ## Emergency execution disable (flag-independent brake)
  `/admin/packages/security/execution` toggles the `package_execution_disabled`
  DB setting (OR'd with the `PACKAGE_EXECUTION_DISABLED` env break-glass). On
  disable: every package-owned webhook endpoint is paused (`is_active=0`, so the
  delivery worker/`dispatch()` skip it) and package-owned credential auth is
  denied. Operators can still view, revoke, export, and uninstall.

  ## Publisher trust & key lifecycle
  `/admin/packages/publishers/{id}` verify/suspend/reinstate (reauth). Suspension
  cascades a force-disable across that publisher's installs via
  `PackageHealthService::enforcePolicy()`. Keys: pin (base64 → exactly 32 bytes),
  rotate (signed `rb-key-rotation.v1` envelope `{document,signature,key_id}`),
  revoke.

  ## Outage / fail-closed
  Registry/vault/remote unavailability yields coded
  `RegistryVerificationException`/`PackagePolicyException`/`ValidationException` —
  never a silent degraded grant. Revoked/blocked/unreviewed/disabled/quarantined/
  uninstalled/emergency-disabled installs cannot receive events or authenticate.
  ```
- [ ] **Step 3: Append the pointer section to `docs/runbooks/package_registry.md`.** After the existing `## Repair` section add:
  ```markdown

  ## Package Integrations & Security-Response Console (Inc 5)

  The `remote_app`/`automation` integration runtime (install-scoped settings,
  read-only API tokens, package-owned webhooks) and the operator security-response
  console (publisher trust, exact-digest review, advisories, emergency disable,
  transparency) are documented in **`docs/runbooks/package_integrations.md`**.
  Both consume this same `package_registry` flag and stay deploy-dark; the flag-
  independent `package_execution_disabled` brake is the kill switch for package
  execution specifically.
  ```
- [ ] **Step 4: Verify both files render the required anchors.** Run `grep -n 'Emergency execution disable\|Hard runtime predecessors' docs/runbooks/package_integrations.md && grep -n 'Security-Response Console (Inc 5)' docs/runbooks/package_registry.md` — expect one hit each. Confirms the sections landed verbatim.
- [ ] **Step 5: Commit.**
  ```bash
  git add docs/runbooks/package_integrations.md docs/runbooks/package_registry.md
  git commit -m "docs(inc5): add package-integrations runbook + security-response pointer"
  ```

---

### Task 67: Extend `registry-protocol.md` with the remote-app contract, and update `PHASE_5_STATUS.md` + the deploy-dark inventory

**Files:**
- Modify: `docs/phase5/registry-protocol.md` (append `## Remote-App Settings, Credentials & Security Response (Inc 5)`)
- Modify: `PHASE_5_STATUS.md` (Inc 5 status line + suite footer)
- Modify: `docs/evidence/deploy-dark-features.md` (Phase 5 `package_registry` row → note Inc 5 integration/credential/security surfaces still dark)

**Interfaces:**
- Consumes: the migration `0073` table names `installed_package_settings` / `installed_package_credentials`; `package_history.event` +`settings_update|credential_mint|credential_revoke`; `moderation_log.target_type` +`publisher`. Documents the `provisionCredentials` reveal-once contract and the `{document,signature,key_id}` publisher-key rotation envelope.
- Produces: no code — protocol/status/inventory prose that later tasks cite.

- [ ] **Step 1: Read the anchor to append after.** Run `grep -n '## Escalation Ladder' docs/phase5/registry-protocol.md` — expect line ~275. New content appends at end of file.
- [ ] **Step 2: Append the Inc 5 contract section to `docs/phase5/registry-protocol.md`.**
  ```markdown

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
  ```
- [ ] **Step 3: Update `PHASE_5_STATUS.md` status line to record Inc 5.** In the opening `**Status:**` paragraph, after the Increment 7 sentence, insert a sentence:
  > Increment 5 (P5-04 integration runtime + P5-07-A security-response console) landed 2026-07-03 behind the dark `package_registry` flag — install-scoped settings, secret storage, read-only API tokens, package-owned webhook delivery, publisher trust/key lifecycle, exact-digest review, advisories, and the flag-independent `package_execution_disabled` brake; GA-DOD-08 at R4, GA-DOD-09 at R3, and the four B2 slices (SLICE-API-TOKENS/WEBHOOKS/SERVICE-SECRETS/FIRST-PARTY-HOOKS) paid up to R4 with the SP0 adversarial suites.
- [ ] **Step 4: Locate and update the deploy-dark `package_registry` row.** Run `grep -n 'package_registry' docs/evidence/deploy-dark-features.md`. Edit the matching table row's "Broad-rollout state" cell to append: `Inc 5 (2026-07-03) added deploy-dark integration settings/credential and security-response console surfaces (`/admin/packages/{id}/integration/*`, `/admin/packages/security`, `/admin/packages/publishers/{id}`); still default-dark, reversible via features override; `package_execution_disabled` is the execution-specific kill switch.` (If no `package_registry` row exists in a Phase 5 table yet, add one under the Phase 5 section mirroring the existing row format.)
- [ ] **Step 5: Verify the three edits landed.** Run `grep -c 'Remote-App Settings, Credentials & Security Response' docs/phase5/registry-protocol.md && grep -c 'Increment 5 (P5-04' PHASE_5_STATUS.md && grep -c 'package_execution_disabled' docs/evidence/deploy-dark-features.md` — expect `1`, `1`, `≥1`.
- [ ] **Step 6: Commit.**
  ```bash
  git add docs/phase5/registry-protocol.md PHASE_5_STATUS.md docs/evidence/deploy-dark-features.md
  git commit -m "docs(inc5): remote-app protocol contract + status + deploy-dark inventory"
  ```

---

### Task 68: Flip TM-SC-08 / TM-SE-02 / TM-SE-04 / TM-SE-05 from `stub`→`implemented` (ThreatModelIndexTest RED→GREEN)

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (four fixtures)
- Test: `tests/Unit/Core/ThreatModelIndexTest.php` (existing guard; do not modify)

**Interfaces:**
- Consumes: the guard invariant — an `implemented` fixture must (a) appear in its dossier `.md` and (b) name an existing `test` file (`is_file($root.'/'.$f['test'])`). The referenced SP0 test files are landed by earlier task groups: `tests/Integration/Service/PackageIntegrationServiceTest.php` (TM-SC-08 undeclared-scope/host denied+audited) and `tests/Integration/Service/SecretVaultRedactionTest.php` (TM-SE-02/04/05).
- Produces: four `status:"implemented"` fixtures with `test` paths; a green `ThreatModelIndexTest`.

- [ ] **Step 1: Confirm the four referenced test files already exist on this commit.** Run:
  ```bash
  ls tests/Integration/Service/PackageIntegrationServiceTest.php tests/Integration/Service/SecretVaultRedactionTest.php
  ```
  Expect both to list. (This group is #14 of 14 — these are landed by the integration + SP0 groups.) If either is missing, STOP: the flip cannot land until its evidence exists.
- [ ] **Step 2: Confirm the dossiers already document all four IDs.** Run `grep -c 'TM-SC-08' docs/phase5/threat-models/supply-chain.md && grep -c 'TM-SE-02\|TM-SE-04\|TM-SE-05' docs/phase5/threat-models/secret-handling.md` — expect `1` then `3`. No dossier edit is needed (the stubs were pre-documented).
- [ ] **Step 3: RED — flip status without a `test` path to prove the guard is load-bearing.** Edit only TM-SC-08 in `fixtures.json`, changing `"status": "stub"` → `"status": "implemented"` (no `test` key yet):
  ```json
  { "id": "TM-SC-08", "model": "supply-chain.md", "fixture": "undeclared scope/host exercised at runtime is denied and audited", "owner": "Inc5", "status": "implemented" }
  ```
- [ ] **Step 4: Run the guard and observe the exact RED.**
  ```bash
  vendor/bin/phpunit --filter test_every_dossier_exists_and_index_is_valid tests/Unit/Core/ThreatModelIndexTest.php
  ```
  Expect FAIL with: `TM-SC-08: implemented fixture must name an existing test file`.
- [ ] **Step 5: GREEN — add the `test` path to TM-SC-08 and flip the three secret-handling fixtures.** Final fixtures:
  ```json
  { "id": "TM-SC-08", "model": "supply-chain.md", "fixture": "undeclared scope/host exercised at runtime is denied and audited", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/PackageIntegrationServiceTest.php" }
  ```
  ```json
  { "id": "TM-SE-02", "model": "secret-handling.md", "fixture": "reading a webhook/token/provider config after save returns no plaintext", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" }
  { "id": "TM-SE-04", "model": "secret-handling.md", "fixture": "non-owning consumer reveal attempt fails and audits", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" }
  { "id": "TM-SE-05", "model": "secret-handling.md", "fixture": "forced vault failure yields svcsec reference only, no plaintext", "owner": "Inc5", "status": "implemented", "test": "tests/Integration/Service/SecretVaultRedactionTest.php" }
  ```
- [ ] **Step 6: Run the full guard to GREEN.**
  ```bash
  vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php
  ```
  Expect `OK (3 tests, ...)` and `grep -c '"status": "stub"' docs/phase5/threat-models/fixtures.json` to have dropped by 4 (was 24 → 20).
- [ ] **Step 7: Commit (evidence-carrying — the SP0/integration tests exist on this commit).**
  ```bash
  git add docs/phase5/threat-models/fixtures.json
  git commit -m "test(inc5): flip TM-SC-08/TM-SE-02/04/05 threat fixtures stub->implemented"
  ```

---

### Task 69: Advance the requirement ledger + finalize SCHEMA §9 (Phase5EvidenceMapTest RED→GREEN, MigrationLedgerTest green)

**Files:**
- Modify: `docs/phase5/requirement-ledger.json` (GA-DOD-08 R1→R4, GA-DOD-09 R2→R3, four SLICE-* R3→R4 + evidence arrays)
- Modify: `SCHEMA.md` (header `v1.32`→`v1.33`; add §9 changelog row for `0073`)
- Test: `tests/Unit/Core/Phase5EvidenceMapTest.php`, `tests/Unit/Core/MigrationLedgerTest.php` (existing guards; do not modify)

**Interfaces:**
- Consumes: the guard invariant — any state `∈ {R3,R4,R5}` requires ≥1 `evidence` path that `is_file()` on this commit; every Gate A DoD id must be present exactly once. All referenced evidence files (services, integration/unit tests, `.spec.ts`, `package_integrations.md`) are landed by earlier groups + Task 66.
- Produces: the advanced ledger + the `v1.33` SCHEMA changelog row; green `Phase5EvidenceMapTest` and `MigrationLedgerTest`.

- [ ] **Step 1: Confirm every evidence file to be linked exists on this commit.** Run:
  ```bash
  for f in \
    src/Service/Packages/PackageIntegrationService.php \
    src/Service/Packages/PackageSettingsService.php \
    src/Service/Registry/PublisherTrustService.php \
    src/Service/Packages/PackageReviewConsoleService.php \
    src/Service/Packages/PackageSecurityResponseService.php \
    tests/Integration/Service/PackageIntegrationServiceTest.php \
    tests/Integration/Core/AppPackageIntegrationTest.php \
    tests/Integration/Service/PublisherTrustServiceTest.php \
    tests/Integration/Service/PackageSecurityResponseServiceTest.php \
    tests/Integration/Api/ApiAuthorizationMatrixTest.php \
    tests/Unit/Security/EgressGuardAdversarialTest.php \
    tests/Integration/Worker/WebhookIdempotencyTest.php \
    tests/Integration/Service/SecretVaultRedactionTest.php \
    tests/Integration/Service/FirstPartyHookPrivateContentTest.php \
    tests/browser/package-integrations.spec.ts \
    tests/browser/package-security.spec.ts \
    tests/browser/api-tokens.spec.ts \
    tests/browser/webhooks.spec.ts \
    docs/runbooks/package_integrations.md ; do
    test -f "$f" && echo "OK  $f" || echo "MISSING $f"; done
  ```
  Expect every line `OK`. Any `MISSING` ⇒ STOP; the ledger bump must land only in a commit where its evidence exists.
- [ ] **Step 2: RED — bump GA-DOD-08 to R4 with an empty evidence array to prove the guard.** Edit its object in `requirement-ledger.json` to `"state": "R4"` while leaving `"evidence": []`, then run:
  ```bash
  vendor/bin/phpunit --filter test_ledger_is_valid_and_every_claim_is_evidenced tests/Unit/Core/Phase5EvidenceMapTest.php
  ```
  Expect FAIL: `GA-DOD-08: state R4 requires at least one evidence link`.
- [ ] **Step 3: GREEN — set GA-DOD-08's evidence array.** Replace the object with:
  ```json
  {
    "id": "GA-DOD-08", "gate": "A", "workstream": "P5-04",
    "title": "Declarative/remote integration install, consent, pin/update/rollback, disable, export/uninstall, secrets, scope, outage",
    "state": "R4",
    "evidence": [
      "src/Service/Packages/PackageIntegrationService.php",
      "src/Service/Packages/PackageSettingsService.php",
      "tests/Integration/Service/PackageIntegrationServiceTest.php",
      "tests/Integration/Core/AppPackageIntegrationTest.php",
      "tests/browser/package-integrations.spec.ts",
      "docs/runbooks/package_integrations.md"
    ]
  }
  ```
- [ ] **Step 4: Advance GA-DOD-09 R2→R3, appending the Inc 5 console evidence to its existing array.** Set `"state": "R3"` and make `evidence` the existing five entries plus:
  ```json
      "src/Service/Registry/PublisherTrustService.php",
      "src/Service/Packages/PackageReviewConsoleService.php",
      "src/Service/Packages/PackageSecurityResponseService.php",
      "tests/Integration/Service/PublisherTrustServiceTest.php",
      "tests/Integration/Service/PackageSecurityResponseServiceTest.php",
      "tests/browser/package-security.spec.ts"
  ```
  Keep its `notes` but update the tail to `publisher console/advisory review + emergency disable landed Inc 5.`
- [ ] **Step 5: Advance the four B2 slices R3→R4, appending SP0 evidence.** In `requirement-ledger.json`:
  - `SLICE-SERVICE-SECRETS` → `"state": "R4"`, add `"tests/Integration/Service/SecretVaultRedactionTest.php"`.
  - `SLICE-API-TOKENS` → `"state": "R4"`, add `"tests/Integration/Api/ApiAuthorizationMatrixTest.php"`, `"tests/browser/api-tokens.spec.ts"`; drop the "outstanding" clause from `notes`.
  - `SLICE-WEBHOOKS` → `"state": "R4"`, add `"tests/Unit/Security/EgressGuardAdversarialTest.php"`, `"tests/Integration/Worker/WebhookIdempotencyTest.php"`, `"tests/browser/webhooks.spec.ts"`; clear the "outstanding" note.
  - `SLICE-FIRST-PARTY-HOOKS` → `"state": "R4"`, add `"tests/Integration/Service/FirstPartyHookPrivateContentTest.php"`; clear the "outstanding" note.
  Then bump the ledger header `"updated": "2026-07-03"` (already current — leave as-is).
- [ ] **Step 6: Run the full evidence-map guard to GREEN.**
  ```bash
  vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php
  ```
  Expect `OK (4 tests, ...)` — proves every new R3/R4 evidence path exists and no Gate A DoD id was dropped/added.
- [ ] **Step 7: Finalize SCHEMA §9 for migration `0073`.** Edit the header line `**Status:** v1.32 · ...` → `**Status:** v1.33 · ...`, then insert a new top row in the §9 changelog table (above the `v1.32` row):
  ```markdown
  | v1.33 | 2026-07-03 | Phase 5 Increment 5 migration `0073_phase5_package_integrations`: added `installed_package_settings` (per-install non-secret `value_json` / secret `secret_ref`) and `installed_package_credentials` (package-owned `api_token`/`webhook` links, ownership/attribution here — no `owner_type/owner_id` widen of the B2 tables); widened `package_history.event` with `settings_update`/`credential_mint`/`credential_revoke` and `moderation_log.target_type` with `publisher`. Additive/deploy-dark behind `package_registry`. |
  ```
  (The §5B table *shapes* are landed by the migration task group; if `grep -c 'installed_package_settings' SCHEMA.md` returns `0`, add the two `CREATE TABLE` shapes to §5B mirroring the `0073` DDL before finalizing this row.)
- [ ] **Step 8: Run the migration-ledger guard to confirm `0073` is gapless.**
  ```bash
  vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php
  ```
  Expect `OK (5 tests, ...)` — no gap/duplicate/malformed name after `0073` (the migration itself is owned by the migration group; this confirms the tree is clean before closeout).
- [ ] **Step 9: Commit (evidence-carrying — ledger + SCHEMA bumps land only here, after all cited files exist).**
  ```bash
  git add docs/phase5/requirement-ledger.json SCHEMA.md
  git commit -m "docs(inc5): advance GA-DOD-08/09 + B2 slices in ledger; finalize SCHEMA v1.33"
  ```

---

### Task 70: Wire the four browser specs into `package.json`, capture browser/axe artifacts, and run the full Inc 5 closeout gate

**Files:**
- Modify: `tests/browser/package.json` (add `evidence:integrations` script)
- Test: `tests/browser/package-integrations.spec.ts`, `tests/browser/api-tokens.spec.ts`, `tests/browser/webhooks.spec.ts`, `tests/browser/package-security.spec.ts` (landed by earlier browser groups — this task only wires + runs them)

**Interfaces:**
- Consumes: the dark-surface capture pattern (`RB_BROWSER_DARK_SURFACES=1 bash prepare.sh`) already used by `evidence:dark`/`a11y`, since all Inc 5 admin surfaces gate on the default-dark `package_registry` flag.
- Produces: a runnable `npm run evidence:integrations` script + captured no-JS/axe artifacts; the final green closeout gate for the whole increment.

- [ ] **Step 1: RED — confirm the script does not exist yet.**
  ```bash
  cd tests/browser && npm run evidence:integrations
  ```
  Expect npm to fail with `Missing script: "evidence:integrations"`.
- [ ] **Step 2: Confirm the four spec files exist so the script has something to run.**
  ```bash
  ls tests/browser/package-integrations.spec.ts tests/browser/api-tokens.spec.ts tests/browser/webhooks.spec.ts tests/browser/package-security.spec.ts
  ```
  Expect all four to list. If any is missing, STOP — it is owned by an earlier browser-evidence group.
- [ ] **Step 3: Add the `evidence:integrations` script to `tests/browser/package.json`.** Insert after the `evidence:passkeys` line (surfaces are dark, so mirror the `RB_BROWSER_DARK_SURFACES` pattern):
  ```json
      "evidence:integrations": "RB_BROWSER_DARK_SURFACES=1 bash prepare.sh && playwright test package-integrations.spec.ts api-tokens.spec.ts webhooks.spec.ts package-security.spec.ts",
  ```
- [ ] **Step 4: Validate the JSON and list the wired specs (GREEN for the script).**
  ```bash
  node -e "require('./tests/browser/package.json'); console.log('package.json valid')" \
    && (cd tests/browser && npx playwright test --list package-integrations.spec.ts api-tokens.spec.ts webhooks.spec.ts package-security.spec.ts | tail -5)
  ```
  Expect `package.json valid` then a non-empty test list — `Missing script` is gone.
- [ ] **Step 5: Capture the browser + axe evidence.**
  ```bash
  cd tests/browser && npm run evidence:integrations && npm run a11y
  ```
  Expect the four Inc 5 specs (no-JS settings form, one-time credential reveal, admin token/webhook surfaces, security console) plus the axe scans to pass across desktop + mobile. Note the PNG artifact paths under `docs/evidence/browser/*/` in the eventual status update.
- [ ] **Step 6: Confirm the flag-dark 404 assertions are wired in `AppFeatureFlagTest`.** This is the invariant that every Inc 5 route 404s while `package_registry` is dark (added by the controller group); the closeout re-runs it:
  ```bash
  vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php
  ```
  Expect `OK`. If it does not assert `/admin/packages/{id}/integration/*`, `/admin/packages/security`, and `/admin/packages/publishers/{id}` return 404 while dark, STOP — that gap belongs upstream and must be closed before the increment is "done".
- [ ] **Step 7: Run the three closeout guards together.**
  ```bash
  vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/Phase5EvidenceMapTest.php tests/Unit/Core/MigrationLedgerTest.php
  ```
  Expect `OK (12 tests, ...)` — fixtures, ledger, and migration ledger all green in one run.
- [ ] **Step 8: Run the full PHPUnit suite (fresh + reused-schema) to prove no regression.**
  ```bash
  RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress && vendor/bin/phpunit --no-progress
  ```
  Expect two identical green totals (test/assertion counts risen from the Inc 5 code+test groups; both runs equal). Record the exact counts for the status footer.
- [ ] **Step 9: Update the `PHASE_5_STATUS.md` suite footer with the measured closeout numbers.** Append a sentence to the `**Suite:**` line: `Increment 5 closeout (2026-07-03): three closeout guards -> 12 tests; `RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress` and the reused-schema re-run -> <N> tests / <M> assertions (identical); `cd tests/browser && npm run evidence:integrations` -> <K> passed and `npm run a11y` -> <J> passed across desktop + mobile.` (Fill `<N>/<M>/<K>/<J>` from Steps 5 and 8 — do not guess.)
- [ ] **Step 10: Commit the final closeout deliverable.**
  ```bash
  git add tests/browser/package.json PHASE_5_STATUS.md
  git commit -m "test(inc5): wire integration/security browser evidence + record closeout gate"
  ```
- [ ] **Step 11: Final verification-before-completion sign-off.** Re-run `git status` (expect clean tree) and re-run the Step 7 guard trio once more. Only after all three guards + `composer test` are green and the browser/axe artifacts are captured is the increment eligible to be called done (DESIGN §13 — "inert schema is not evidence").

---

## Coverage & Execution Notes

**Design-spec coverage** (`docs/superpowers/specs/2026-07-03-phase5-increment5-package-integrations-security-response-design.md` → task groups):

| Spec section | Covered by |
|---|---|
| §2 Scope | groups 4–14 (install/settings/secrets/tokens/webhooks/data-class/disable-uninstall-export/console) |
| §4 Data Model | group 4 — migration `0073` + `InstalledPackageSettingsRepository`/`InstalledPackageCredentialRepository`/publisher-key repo |
| §5 Integration Runtime | groups 5–8 — `PackageSettingsService`, install-scoped API-token provisioning, package-owned webhook provisioning + event gating, credential cleanup |
| §6 Security Response Console | groups 10–13 — `PublisherTrustService`, `PackageReviewConsoleService`, `PackageSecurityResponseService` + emergency disable, console UX |
| §7 Runtime Authorization Rules | Global Constraints + groups 6–8, 12 (manifest-ceiling/grant-authority, scope⊆ApiScopes, event⊆domainEvents, outbound-host, no private-payload broadening) |
| §8 Error Handling | per-task 422/rollback/fail-closed steps |
| §9 Operator UX | group 9 (integration) + group 13 (security console) — no-JS forms + browser/axe |
| §10 Evidence & Acceptance | SP0 groups 1–3 + group 14 closeout (GA-DOD-08/09, SLICE-API-TOKENS/WEBHOOKS/SERVICE-SECRETS/FIRST-PARTY-HOOKS, TM-SC-08 / TM-SE-02/04/05) |
| §11 Runbooks/Docs | group 14 |
| §12 Open decisions | adopted — see Global Constraints |
| §13 decomposition | matched (SP0 added as groups 1–3) |

**SP0 gates enablement, not build order.** Groups 1–3 (B2 release-evidence retrofits) may run in parallel with the P5-04/P5-07-A build (groups 4–14), which can land deploy-dark first — but GA-DOD-08/09 cannot reach R4/R5 and no staged enablement occurs until SP0 evidence is green.

**Known refinements for the executor:**
1. New `DATETIME` columns show `DEFAULT CURRENT_TIMESTAMP` for convenience, but the **UTC** global constraint governs *writes*: services set `created_at`/`updated_at` explicitly via `gmdate('Y-m-d H:i:s')` / `UTC_TIMESTAMP()` — never rely on the session-time column default for a stored UTC value.
2. Task numbers are per-group and sparse (gaps between groups); navigate by the `<!-- group: … -->` markers, not contiguous numbers.

**Execution:** run with `superpowers:subagent-driven-development` — one subagent per group in listed order, review checkpoint between groups, SP0 (1–3) green before any enablement claim.
