# Phase 5 Increment 3 — Package Manifest, Install & Lifecycle (P5-02 + P5-07-A part 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land P5-02 deploy-dark behind `package_registry`: the locked `rb-manifest.v2` schema with fail-closed validation, verified release acquisition (signed release document = the installable artifact, digest-pinned by the Inc 2 snapshot), an install → consent → enable → disable lifecycle with `granted=0`-until-consent permission snapshots, pin/update-policy, staged updates with permission diff + re-consent (old grant retained; reduction immediate), rollback to a previously verified digest, uninstall + export + retention, a digest/tamper health worker (`worker:packages`) with quarantine and advisory/blocklist enforcement (completing the Inc 2 `force_disable` handoff), immutable history/transparency records, and the **`PackageSecurityGate` + `package_review_decisions`** enforcement primitive (P5-07-A part 1) so install/enable fail closed on revoked / unapproved-digest / locally-blocked releases.

**Architecture:** A pure policy core (`App\Security\Packages`: `ManifestValidator`, `PermissionDiff`, `PackageSecurityGate`) makes every fail-closed decision; stateful lifecycle services (`App\Service\Packages`: `PackageAcquisitionService`, `PackageLifecycleService`, `PackageUpdateService`, `PackageHealthService`) own their `$db->transaction(fn)` write paths over five new thin repositories plus the Inc 2 repositories. **The signed `rb-release.v1` document is the artifact**: its sha256 (over the exact JSON bytes) is the digest the signed snapshot already pins, the bytes are cached content-addressed in `PackageArtifactStore` (`storage/packages/<digest>.json`), and the health worker re-hashes them (TM-SC-07). Verification is validate-first: every fetch/signature/digest/manifest/policy check happens **before** the write transaction opens, so a refusal at any stage leaves zero lifecycle rows behind and the recorded `failure_stage` is exact. Migrations `0069` (lifecycle columns + ENUM widens) and `0070` (review/security tables — see the §C re-baseline in Task 4) are the only schema.

**Tech Stack:** PHP 8.2+, libsodium (Ed25519 via the existing `TrustChainVerifier`), MySQL/MariaDB via `Database`, the in-process kernel harness (`Tests\Support\TestCase`), the F6 `SigningHarness`/`RegistryFixtures` tooling (extended in Task 1), Playwright + axe.

## Global Constraints

*Every task implicitly includes this section. Values copied from CLAUDE.md, the Gate A program plan, and the Inc 2 precedents.*

- **Deploy-dark.** `package_registry` stays **default `false`** in `FeatureFlags::DEFAULTS` (do not touch the map). Every new route throws `NotFoundException` when the flag is off (`requireAdmin()` **then** `gate()` — the repo-wide order: 302/403 resolve before the dark 404), with regressions in `tests/Integration/Core/AppFeatureFlagTest.php`. `worker:packages` no-ops dark. No flag default flips anywhere in this increment.
- **Fail closed.** Every verification/policy failure is a thrown, coded exception (`RegistryVerificationException` for trust-chain failures, the new `PackagePolicyException` for manifest/policy/lifecycle refusals — both expose a machine token via `$e->code`). There is no default-allow branch. Tampered, unapproved, revoked, blocked, incompatible, type-forbidden, and unconsented inputs must all refuse.
- **Validate-first transactions.** All fetch/crypto/manifest/policy validation happens **before** `$db->transaction(fn)` opens; the transaction contains only writes that succeed together. (Also required for test fidelity: per-test isolation is one outer transaction with **no savepoints**, so an inner rollback would not undo rows under PHPUnit — with validate-first there is nothing to undo.)
- **Migrations `0069` + `0070` exactly.** `tests/Unit/Core/MigrationLedgerTest.php` enforces **gapless + count==max** numbering, so this increment CANNOT take `0072` while `0070`/`0071` are unbuilt — Task 4 re-baselines the program-plan §C table (review/security tables `0072`→`0070`; themes `0070`→`0071`; integrations `0071`→`0072`), the same procedure as the 2026-07-01 re-baseline. `up()` additive (ENUM widens append values at the END); `down()` deletes rows using new ENUM values before narrowing, drops FKs before columns. Hand-update `SCHEMA.md` (shape + §9 changelog + version bump — changelog is at v1.28 and the **header is stale at v1.27**; land as **v1.29** and fix the header).
- **Write path.** Controllers thin (marshal → one service call per branch → map exceptions); services own rules and run `WriteGate::assertCanWrite()` then `ReauthGate::requirePassword()` (in that order) for high-impact actions; repositories are `final`, single-table-ish, prepared statements only, assoc arrays out. Controllers catch `ValidationException` → re-render 422 with `->errors` + old input; catch `PackagePolicyException`/`RegistryVerificationException` → 422 render with the coded reason (anti-draft-loss; never a bare 500).
- **Reauth policy (locked, mirrors Inc 2):** install, consent, enable, update/apply-staged, rollback, uninstall **require** `current_password` reauth. **disable does NOT** (the deliberate no-friction emergency brake, like blocklist add), nor do pin/unpin, update-policy, export, cancel-staged, reverify — all still WriteGate-checked and audited.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (clamp + concatenate ints); never reuse a named placeholder; `UTC_TIMESTAMP()` / `gmdate('Y-m-d\TH:i:s\Z')` / `gmdate('Y-m-d H:i:s')` everywhere; no `NOW()`.
- **Strict CSP / no-JS-first.** No inline `<script>`/`<style>`; all new admin surfaces are plain server-rendered forms (`$this->csrfField()`, `$this->e()`/`$e`); every flow works without JavaScript.
- **CSRF on every POST; no GET mutates state.** The install **plan** performs network fetch + verified-metadata caching, so it is a **POST** that renders the plan page (never a GET).
- **Per-surface noindex:** every response from the new controller (including redirects) carries `X-Robots-Tag: noindex`.
- **Audit:** every lifecycle operator action writes a `moderation_log` row (`target_type='package'`, `target_id` = `packages.id` — the 0068 ENUM already covers it). Worker/enforcement actions audit with `actor_id=null`.
- **Denormalized state gets repairs.** The advisory/blocklist → installed-state enforcement in `PackageHealthService::enforcePolicy()` gets a DB-only mirror `RepairService::repairInstalledPackageStates()` with **identical WHERE semantics**, wired into `repairAll()`.
- **Public packages are non-critical (decision #10).** Nothing on any core route reads package state; registry fetch happens only on the admin plan/install POST and in workers. An enabled declarative package **executes nothing** in this increment — "enabled" is recorded eligibility; themes activate in Inc 4, integrations in Inc 5.
- **Evidence (DESIGN §13, §F):** PHPUnit unit + integration; browser (desktop+mobile) + axe for the UI surfaces; noindex assertions; telemetry emission; `package.install_update_p95` measured (D11: 10000 ms p95); runbook + protocol-doc updates; threat-model fixtures **TM-SC-06, TM-SC-07, TM-SC-09** flipped to `implemented` with real test paths.
- **Strict PHPUnit:** every test ≥1 assertion; no output; no warnings. Feature flags set per-test via `(new SettingRepository($this->db))->set('features', ['package_registry' => true])`.
- **Never `git add -A`; every commit stages explicit paths.**

## Context — what already exists (read before Task 1)

- **Inc 2 (all landed, dark):** `TrustChainVerifier` (`verify(documentJson, signature, keyId, trustKeys, expectedFormat, now): VerifiedDocument`, throws `RegistryVerificationException` with `$e->code`; accepted formats include `rb-release.v1`), `RegistrySnapshotService` (signed snapshots create `packages`/`package_releases` rows with pinned `digest`; release immutability refusal `release_digest_rewrite`), `RegistryAdvisoryService` (`ACTION_STATUS`, `static escalate()`, `static affectsVersion()`, `evaluatePackage()` — its docblock promises the installed-package disable "lands with the install path in Inc 3"), `LocalBlocklistService` (`isBlocked(?digest, ?packageUid)`), `RegistryCatalogService` (`overview()`/`detail()`), `RegistryTransport`/`CurlRegistryTransport`/`ArrayRegistryTransport`/`RegistryFetchResult` (all in `src/Service/Registry/` — the Array double IS production-namespaced), `RegistryRefreshWorker`, `AdminPackagesController` (read-only `index`/`show`), `AdminRegistryController`, templates `admin/packages.php`, `admin/package_detail.php`, `admin/registries.php`. Container bindings for all of these sit at `src/Core/App.php:1131-1184`; routes at `App.php:1481-1492`.
- **`0049` tables this increment animates:** `installed_packages` (UNIQUE `package_id` — one install per package; `state ENUM('installed','enabled','disabled','quarantined','uninstalling')`, `health`, `digest`, `trust_class`, `review_status`, `compat_min/max`, `installed_by`), `installed_package_permissions` (`kind ENUM('capability','data_class','outbound_host','job','broker_service')`, `declared`/`granted` with `granted DEFAULT 0`, `risk_class ENUM('low','medium','high')`), `package_history` (`event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine','uninstall','consent','health')`, prior/new version+digest, `permission_snapshot_json`, `approval_ref`, `failure_stage`, `detail`; `installed_package_id` has NO FK so history survives uninstall). `package_releases` already carries `manifest_json`, `dependency_json`, `signature VARBINARY(1024)`, `signed_key_id`, `review_status ENUM('unreviewed','submitted','approved','rejected','revoked')`, `source_url` — Inc 2 populates only version/digest/channel/compat via snapshots; the signed columns are hydrated by this increment.
- **Foundation primitives:** `CapabilityCatalog` (`has()`, `isProtected()`, `consent()` — `core.package.manage`/`core.package.review` exist; 5 protected keys never grantable), `DataClasses` (`has()`, `grantable()`, `risk()`, `consent()` — 10 keys; `security.config` is protected/never grantable), `ApiScopes::SCOPES` (read-only scope map), `WebhookEvents` (`all()`; `forSubscription()` excludes admin-test-only `ping`), `CoreVersion::satisfies(?min, ?max, ?version=null)` fail-closed, `ReauthGate::requirePassword(User, string, string $field='current_password')` (throws `ValidationException`), `WriteGate::assertCanWrite(User)`, `Telemetry::emit(string, array)` (dark unless `telemetry.enabled`; contexts redacted), `ModerationLogRepository::log(array)`, `EgressGuard`.
- **Test tooling (F6):** `Tests\Support\Phase5\SigningHarness` — `generate()`, `keyId()`, `publicKey()`, `sign()`, `verify()`, `tamper()`, `mintSnapshot(array $overrides)`, `mintExpiredSnapshot()`, `mintRelease(array $overrides)` (placeholder shape — **Task 1 redefines it**; its comment says "the real manifest.v2 schema is Inc 3 (P5-02 SP1) scope"), `mintAdvisory()`, `mintRotation()`. `Tests\Support\Phase5\RegistryFixtures::seed(Database, SigningHarness)` seeds one dark registry `rb-test` (base `https://registry.invalid`), an active trust key, publisher `acme`, package `acme/midnight-theme` (theme / reviewed_declarative), one signed approved stable release (stores `manifest_json`/`signature`/`signed_key_id`).
- **Repair pattern:** `RepairService::repairPackageLatestReleases()` / `repairPackageAdvisoryStatuses()` + `repairAll()` keys `package_latest`, `package_advisory`.
- **Budgets:** `Phase5Budgets` row **`package.install_update_p95` (target 10000 ms, p95, measurable_at inc3)**; `Phase5BudgetReportService::rows()` uses hardcoded `elseif ($key === '…')` branches pulling samples from `BaselineMetricsService`; `bin/console verify:phase5-budgets` regenerates `docs/evidence/phase5/performance-budgets.md`.
- **Console:** `bin/console` is one `switch ($command)`; workers hand-wire services (see the `worker:registry-refresh` case at `bin/console:437-478`). `verify:*` commands refuse `app.env=production` (CONSOLE-1). `UpgradeRehearsal` runs every migration ≥0011 generically — 0069/0070 need no edit there.
- **Ledger/threat gates:** `Phase5EvidenceMapTest` (every `GA-DOD-*` present; states R3+ require ≥1 on-disk evidence path; "bump a state only in the commit that lands its evidence"), `ThreatModelIndexTest` (fixture `status:'implemented'` requires `test:` naming an existing file), `MigrationLedgerTest` (gapless+count==max).
- **Test config isolation:** `tests/bootstrap.php` overrides config via `array_replace_recursive` (e.g. `uploads.storage_path` → sys temp). Task 6 adds `packages.storage_path` there the same way.
- **Browser evidence:** `tests/browser/seed.php` (has `$evidenceFeatures` with `package_registry => true`), `gate-a.spec.ts` (`login(page, email)`, `visit(page, url)`, `shot(page, info, 'NN-name')`; screenshots 32–34 taken; **the Inc 2 test asserts `Install does not exist yet` — Task 13/15 update it**), `a11y.spec.ts` (`visit()` route list includes `/admin/packages`, `/admin/registries`).

## Locked wire contract (extends `docs/phase5/registry-protocol.md`; Task 16 documents it)

**The signed release document is the installable artifact.** For a release row created by an Inc 2 snapshot, `package_releases.digest` = **sha256 hex of the exact `rb-release.v1` JSON document bytes**. The chain: pinned trust key → signed snapshot → per-release digest → release-document bytes (independently signed AND hash-pinned). The locally cached bytes are what `worker:packages` re-hashes.

```
rb-release.v1 (signed; detached Ed25519 over the exact JSON string):
{
  "format": "rb-release.v1",
  "uid": "acme/midnight-theme",
  "version": "1.1.0",
  "review": {"status": "approved", "decided_at": "2026-07-01T00:00:00Z"},
  "manifest": { ...rb-manifest.v2, see Task 2... }
}
  - review.status ∈ unreviewed|submitted|approved|rejected (revocation travels via advisories/blocklist, never a rewrite)
  - decision #16 by construction: the review assertion lives INSIDE the signed bytes whose sha256 the snapshot pins,
    so approval is bound to the exact digest; any byte change is a new digest the snapshot does not pin (TM-SC-09).

Fetch endpoint (admin plan/install POST only — never a core route):
GET {base_url}/releases/{package_uid}/{version}/rb-release-envelope.v1.json
  → {"format":"rb-release-envelope.v1","document":"<exact rb-release.v1 JSON string>","signature":"<base64>","key_id":"root-1"}
`package_releases.source_url`, when set by the snapshot, overrides the path but MUST be same-origin
(scheme+host+port) with the registry `base_url` → else refusal code `source_mismatch` (TM-SC-05 reinforcement).
Verification order at acquisition: digest(sha256 document == snapshot-pinned release.digest) → signature/format
(TrustChainVerifier) → identity (payload uid+version == release row) → manifest.v2 validation.
```

## Locked interfaces (all tasks must match these exactly)

```
App\Security\Packages\PackagePolicyException     extends \RuntimeException; __construct(string $code, string $message);
                                                 magic __get('code'): string   (mirror RegistryVerificationException)
App\Security\Packages\PackageManifest            readonly VO: string $uid, $type, $version, $name; ?string $description, $license;
                                                 string $coreMin; ?string $coreMax;
                                                 array $permissions   — list<array{kind:string,key:string,risk:string,label:string}>;
                                                 ?array $settingsSchema; int $storageQuotaKb; ?int $retentionDays; array $support;
                                                 coreCompatible(): bool
App\Security\Packages\ManifestValidator          validate(array $manifest, string $expectedUid, string $expectedVersion): PackageManifest
App\Security\Packages\PermissionDiff             static describe(string $kind, string $key): array{kind,key,risk,label}
                                                 static diff(array $old, array $new): array{added:list,removed:list,unchanged:list}
App\Security\Packages\PackageSecurityGate        __construct(LocalPackageBlockRepository, PackageAdvisoryRepository)
                                                 assertInstallable(array $package, array $release): void
                                                 assertEnableable(array $package, array $release): void

App\Repository\InstalledPackageRepository        find(int): ?array · findByPackage(int): ?array · all(): array · activeWithContext(): array
                                                 create(array $row): int · reviveForInstall(int, array $row): void · setState(int, string): void
                                                 setHealth(int, string $health, ?string $quarantineReason, ?string $checkedAtUtc = null): void
                                                 activateRelease(int, int $releaseId, string $digest, ?string $compatMin, ?string $compatMax, string $reviewStatus): void
                                                 stageRelease(int, ?int $releaseId, ?string $digest): void · setPinned(int, bool): void
                                                 setUpdatePolicy(int, string): void · markUninstalled(int, string $retainUntilUtc): void
                                                 storeExport(int, string $exportJson): void · purgeable(string $nowUtc): array · delete(int): void
App\Repository\InstalledPackagePermissionRepository
                                                 forInstall(int): array · replaceDeclared(int, array $perms): void
                                                 grantAll(int, int $grantedBy): int · replaceWithGrants(int, array $perms, int $grantedBy): void
                                                 ungrantedCount(int): int · deleteFor(int): void
App\Repository\PackageHistoryRepository          record(array): int · forPackage(int, int $limit = 50): array
                                                 forInstall(int, int $limit = 50): array · verifiedDigestsFor(int $packageId): array
App\Repository\PackageReviewDecisionRepository   record(array): int · latestForDigest(int $packageId, string $digest): ?array · forPackage(int): array
App\Repository\PackageReleaseRepository          (Inc 2 class, extended) + hydrateSignedMetadata(int $id, string $manifestJson, string $signature, string $keyId, string $reviewStatus): void
App\Repository\PackageTransparencyLogRepository  record(array): int · forPackageUid(string, int $limit = 100): array · all(int $limit = 200): array
                                                 (append-only: no update/delete methods may exist)

App\Service\Packages\PackageArtifactStore        __construct(string $storagePath) · put(string $digest, string $bytes): void
                                                 has(string $digest): bool · get(string $digest): ?string · verify(string $digest): bool
                                                 remove(string $digest): void · pathFor(string $digest): string
App\Service\Packages\PackageAcquisitionService   __construct(Database, TrustChainVerifier, RegistryTrustKeyRepository, PackageReleaseRepository,
                                                   PackageReviewDecisionRepository, PackageTransparencyLogRepository, PackageArtifactStore,
                                                   ManifestValidator, RegistryTransport, ?Telemetry $telemetry = null)
                                                 ensureVerified(array $registry, array $package, array $release, ?\DateTimeImmutable $now = null): PackageManifest
App\Service\Packages\PackageLifecycleService     __construct(Database, PackageRepository, PackageReleaseRepository, PackageRegistryRepository,
                                                   InstalledPackageRepository, InstalledPackagePermissionRepository, PackageHistoryRepository,
                                                   PackageTransparencyLogRepository, PackageReviewDecisionRepository, PackageAcquisitionService,
                                                   PackageSecurityGate, PackageArtifactStore, ReauthGate, WriteGate, ModerationLogRepository,
                                                   int $retentionDays = 30, ?Telemetry $telemetry = null)
                                                 plan(User, int $packageId, ?int $releaseId = null): array
                                                 install(User, string $currentPassword, int $packageId, ?int $releaseId = null): int
                                                 consent(User, string $currentPassword, int $installedId): int
                                                 enable(User, string $currentPassword, int $installedId): void
                                                 disable(User, int $installedId): void
                                                 setPinned(User, int $installedId, bool $pinned): void
                                                 setUpdatePolicy(User, int $installedId, string $policy): void
                                                 uninstall(User, string $currentPassword, int $installedId): array
                                                 export(User, int $installedId): array
                                                 reverify(User, int $installedId): bool
App\Service\Packages\PackageUpdateService        __construct(Database, PackageRepository, PackageReleaseRepository, PackageRegistryRepository,
                                                   InstalledPackageRepository, InstalledPackagePermissionRepository, PackageHistoryRepository,
                                                   PackageTransparencyLogRepository, PackageAcquisitionService, PackageSecurityGate,
                                                   PackageArtifactStore, ReauthGate, WriteGate, ModerationLogRepository, ?Telemetry $telemetry = null)
                                                 updatePlan(User, int $installedId, ?int $targetReleaseId = null): array
                                                 update(User, string $currentPassword, int $installedId, ?int $targetReleaseId = null): array
                                                 applyStaged(User, string $currentPassword, int $installedId): void
                                                 cancelStaged(User, int $installedId): void
                                                 rollbackTargets(int $installedId): array
                                                 rollback(User, string $currentPassword, int $installedId, int $targetReleaseId): array
App\Service\Packages\PackageHealthService        __construct(Database, InstalledPackageRepository, InstalledPackagePermissionRepository,
                                                   PackageRepository, PackageReleaseRepository, PackageAdvisoryRepository,
                                                   LocalPackageBlockRepository, PackageHistoryRepository, PackageTransparencyLogRepository,
                                                   PackageArtifactStore, ModerationLogRepository, ?Telemetry $telemetry = null)
                                                 checkAll(?\DateTimeImmutable $now = null): array{checked:int,quarantined:int,disabled:int,purged:int,updates:int}
                                                 enforcePolicy(): int · purgeExpired(?\DateTimeImmutable $now = null): int
App\Worker\PackageHealthWorker                   __construct(PackageHealthService, bool $enabled, ?Telemetry $telemetry = null)
                                                 run(): array{checked:int,quarantined:int,disabled:int,purged:int,updates:int,skipped:int}

App\Controller\AdminPackageLifecycleController   plan · install · consentForm · consent · enable · disable · pin · updatePolicy ·
                                                 update · cancelUpdate · rollback · uninstall · export · reverify
```

Routes (Task 13; `{id}` is always **`packages.id`** — the controller resolves the install row):

```
POST /admin/packages/{id}/plan            GET  /admin/packages/{id}/consent
POST /admin/packages/{id}/install         POST /admin/packages/{id}/consent
POST /admin/packages/{id}/enable          POST /admin/packages/{id}/disable
POST /admin/packages/{id}/pin             POST /admin/packages/{id}/update-policy
POST /admin/packages/{id}/update          POST /admin/packages/{id}/update/cancel
POST /admin/packages/{id}/rollback        POST /admin/packages/{id}/uninstall
POST /admin/packages/{id}/export          POST /admin/packages/{id}/reverify
```

`PackagePolicyException` codes (locked): `manifest_format`, `manifest_identity`, `manifest_type`, `manifest_name`, `manifest_field`, `manifest_core`, `unknown_field`, `unknown_capability`, `protected_capability`, `unknown_data_class`, `protected_data_class`, `unknown_api_scope`, `unknown_event`, `outbound_host`, `job_declaration`, `settings_schema`, `storage_quota`, `install_policy`, `support_link` (Tasks 2–3); `locally_blocked`, `advisory_blocked`, `advisory_revoked`, `review_not_approved`, `type_forbidden`, `trust_class_forbidden` (Task 7); `source_mismatch`, `fetch_failed`, `artifact_digest`, `release_identity`, `release_review` (Task 8); `incompatible_core`, `no_release`, `already_installed`, `not_installed`, `not_consented`, `artifact_missing`, `artifact_tampered`, `invalid_state`, `pinned`, `update_policy`, `no_staged_update`, `stage_pending`, `same_release`, `rollback_target`, `not_quarantined` (Tasks 9–11).

Audit actions (all `target_type='package'`, `target_id=packages.id`): `package_install`, `package_consent`, `package_enable`, `package_disable`, `package_pin`, `package_unpin`, `package_update_policy`, `package_update_staged`, `package_update_cancel`, `package_update`, `package_rollback`, `package_uninstall`, `package_export`, `package_reverify`, `package_quarantine` (actor null), `package_force_disable` (actor null).

Telemetry events: `package.install`, `package.lifecycle`, `package.update`, `package.health` (worker heartbeat included).

## Decisions this plan locks (surface these in review)

1. **§C re-baseline (Task 4):** review/security tables move `0072`→`0070` because the F1 ledger guard forbids gaps; themes shift to `0071`, integrations to `0072`. The program-plan table gets a dated note (2026-07-01 precedent).
2. **Release document = artifact; digest = sha256 of its exact bytes** (wire contract above). `SigningHarness::mintRelease` is redefined accordingly (its placeholder anticipated this).
3. **Review approval travels inside the signed release document** (`review.status`), bound to the exact digest by construction; `package_review_decisions` caches the relied-upon envelope at acquisition time (§8.2 #5). Review *revocation* is expressed via advisories (`revoke`) or the local blocklist, never a digest rewrite.
4. **`installed_package_permissions.kind` widens with `api_scope` + `event`** (0069): manifest.v2 declares `api_scopes` (⊆ `ApiScopes::SCOPES`) and `events` (⊆ `WebhookEvents::forSubscription()`), snapshotted as permissions now; live credential minting stays Inc 5.
5. **Gate-A install-type policy:** `ManifestValidator` accepts types `theme|automation|remote_app|local`; `PackageSecurityGate` refuses `server_extension` AND `local` for registry installs (`type_forbidden`) — dev-mode local install is out of scope (decision #4). Registry trust classes must be `reviewed_declarative`/`reviewed_remote` (`trust_class_forbidden`).
6. **Reauth split:** disable/pin/policy/export/cancel/reverify are deliberate-friction-free (WriteGate + audit only); everything that grants or changes executable eligibility requires password reauth.
7. **`update_policy` ENUM is `manual|notify`** — `auto` does not exist in Gate A (program plan: auto-update OFF). `notify` only feeds the health worker's `updates` telemetry count and a detail-page banner.
8. **Enabled ≠ executing.** No package code path exists in Gate A Inc 3; `state='enabled'` is recorded eligibility consumed by Inc 4 (themes) / Inc 5 (integrations). The plan/consent/enable surfaces say so explicitly.
9. **Reinstall over an uninstalled row revives the same `installed_packages` row** (the UNIQUE `package_id` key forces this); history continuity is intentional.
10. **Repair writes history rows for state fixes** (`event='disable'`, detail `repair reconcile`, actor null) — idempotent because already-disabled rows never re-match.

## Out of scope (do not build here)

- **Theme build/preview/activation** — Inc 4 (P5-03, `0071`). A theme package can be installed/enabled here; nothing renders it.
- **Settings VALUES, remote-app credentials, scope→gate mapping, registry-outage remote behavior** — Inc 5 (P5-04, `0072`). `settings_json` lands dark; the settings-schema is validated shape-only.
- **Publisher console, advisory worker, transparency-log UI, publisher key lifecycle behavior** — Inc 5 (P5-07-A part 2). `publisher_signing_keys` lands as documented-inert schema (its Inc 5 consumer is recorded in SCHEMA.md + the ledger).
- **Dev-mode local package install; auto-update; dependency resolution beyond the declared inventory display; SBOM tooling.**
- **Capability grants conferring runtime authority** — snapshot rows only; the resolver never reads them (Gate B broker territory).
- **Flag default flips; `docs/evidence/deploy-dark-features.md` edits; staged-rollout config.**

---

## Task 0: Branch

- [ ] **Step 1: Create the working branch from current main**

```bash
cd /home/henry/community-forums
git switch main && git pull --ff-only && git switch -c phase5-inc3-package-lifecycle
```

Expected: branch `phase5-inc3-package-lifecycle` at the main tip (`e6b6f5c` or later). `git status` must be clean before starting.

---
### Task 1: Wire contract in the test harness — redefine `mintRelease`, add `mintManifest` + `mintReleaseEnvelope`, hydrate `RegistryFixtures`

The F6 harness's `mintRelease` is an acknowledged placeholder ("the real manifest.v2 schema is Inc 3 (P5-02 SP1) scope"). Redefine it to the locked wire contract: **digest = sha256 over the exact signed `rb-release.v1` JSON bytes; review approval asserted inside the signed bytes; the manifest is nested**. Every later task consumes these fixtures.

**Files:**
- Modify: `tests/Support/Phase5/SigningHarness.php` (replace `mintRelease`, add `mintManifest`, `mintReleaseEnvelope`)
- Modify: `tests/Support/Phase5/RegistryFixtures.php` (digest-consistent hydrated release; add `seedRelease`)
- Test: `tests/Unit/Support/SigningHarnessTest.php`, `tests/Integration/Core/RegistryFixturesTest.php`

**Interfaces:**
- Consumes: existing private `SigningHarness::signedDoc(array $doc): array{json,signature,key_id}` (`JSON_UNESCAPED_SLASHES`, raw detached signature over the exact string).
- Produces (every later task relies on these exact shapes):
  - `mintManifest(array $overrides = []): array` — a valid `rb-manifest.v2` array. Merge rule: top-level keys replaced; `core` and `permissions` merged one level deep with **permission kind lists replaced wholesale** (never positional `array_replace_recursive` on lists).
  - `mintRelease(array $overrides = []): array{json:string,signature:string,key_id:string,digest:string,manifest:array,manifest_json:string,version:string,uid:string}` — overrides: `uid`, `version`, `review_status`, `manifest` (manifest overrides, same merge rule). `digest === hash('sha256', json)`.
  - `mintReleaseEnvelope(array $overrides = []): array{body:string,release:array}` — `body` is the `rb-release-envelope.v1` JSON (base64 signature) served by `ArrayRegistryTransport`.
  - `RegistryFixtures::seed(Database $db, SigningHarness $root, ?string $artifactDir = null): array` — now also returns `release_digest` + `release_document`; release row hydrated (`manifest_json`, `signature`, `signed_key_id`, `review_status='approved'`, `digest` = the minted digest, `core_min='0.1.0'`); when `$artifactDir` is given, writes `{$artifactDir}/{digest}.json` = the exact document (the Task 6 store layout).
  - `RegistryFixtures::seedRelease(Database $db, SigningHarness $root, array $seeded, array $overrides = [], ?string $artifactDir = null): array{release_id:int,digest:string,document:string,version:string}` — a second hydrated release (default version `1.1.0`) and bumps `packages.latest_release_id` to it.

- [ ] **Step 1: Write the failing unit test**

Append to `tests/Unit/Support/SigningHarnessTest.php`:

```php
public function test_mint_release_digest_pins_exact_document_bytes(): void
{
    $root = SigningHarness::generate();
    $release = $root->mintRelease(['version' => '2.0.0']);

    self::assertSame(hash('sha256', $release['json']), $release['digest']);
    self::assertTrue(SigningHarness::verify($release['json'], $release['signature'], $root->publicKey()));

    $doc = json_decode($release['json'], true, 512, JSON_THROW_ON_ERROR);
    self::assertSame('rb-release.v1', $doc['format']);
    self::assertSame('acme/midnight-theme', $doc['uid']);
    self::assertSame('2.0.0', $doc['version']);
    self::assertSame('approved', $doc['review']['status']);
    self::assertSame('rb-manifest.v2', $doc['manifest']['format']);
    self::assertSame($doc['manifest'], $release['manifest']);
    self::assertSame(json_encode($doc['manifest'], JSON_UNESCAPED_SLASHES), $release['manifest_json']);

    // One flipped byte is a different artifact: different digest, failed signature.
    $tampered = SigningHarness::tamper($release['json']);
    self::assertNotSame($release['digest'], hash('sha256', $tampered));
    self::assertFalse(SigningHarness::verify($tampered, $release['signature'], $root->publicKey()));
}

public function test_mint_manifest_permission_lists_replace_wholesale(): void
{
    $root = SigningHarness::generate();
    $manifest = $root->mintManifest(['permissions' => ['data_classes' => []], 'type' => 'automation']);

    self::assertSame([], $manifest['permissions']['data_classes']);
    self::assertSame('automation', $manifest['type']);
    self::assertSame('rb-manifest.v2', $manifest['format']);
}

public function test_mint_release_envelope_wraps_the_exact_document(): void
{
    $root = SigningHarness::generate();
    $envelope = $root->mintReleaseEnvelope(['review_status' => 'submitted']);

    $body = json_decode($envelope['body'], true, 512, JSON_THROW_ON_ERROR);
    self::assertSame('rb-release-envelope.v1', $body['format']);
    self::assertSame($envelope['release']['json'], $body['document']);
    self::assertSame($envelope['release']['signature'], base64_decode($body['signature'], true));
    self::assertSame($root->keyId(), $body['key_id']);
    self::assertSame('submitted', json_decode($body['document'], true)['review']['status']);
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Support/SigningHarnessTest.php`
Expected: FAIL — `mintManifest`/`mintReleaseEnvelope` undefined; the digest assertion fails against the placeholder `mintRelease`.

- [ ] **Step 3: Implement the harness changes**

In `tests/Support/Phase5/SigningHarness.php`, replace the placeholder `mintRelease` with:

```php
/**
 * A valid rb-manifest.v2 array (P5-02). Top-level overrides replace; `core`
 * and `permissions` merge one level deep with permission KIND LISTS replaced
 * wholesale (positional array_replace_recursive on lists is a fixture trap).
 *
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
public function mintManifest(array $overrides = []): array
{
    $base = [
        'format' => 'rb-manifest.v2',
        'uid' => 'acme/midnight-theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'name' => 'Midnight Theme',
        'description' => 'A dark declarative theme for RetroBoards.',
        'license' => 'MIT',
        'core' => ['min' => '0.1.0', 'max' => null],
        'permissions' => [
            'capabilities' => [],
            'data_classes' => ['package.own_storage'],
            'api_scopes' => [],
            'events' => [],
            'outbound_hosts' => [],
            'jobs' => [],
        ],
        'storage_quota_kb' => 64,
        'support' => ['homepage' => 'https://acme.example/midnight'],
    ];
    foreach ($overrides as $key => $value) {
        if (in_array($key, ['core', 'permissions'], true) && is_array($value)) {
            $base[$key] = array_replace($base[$key], $value);
            continue;
        }
        $base[$key] = $value;
    }
    return $base;
}

/**
 * Mint a signed rb-release.v1 document — THE installable artifact (Inc 3 wire
 * contract): digest = sha256 over the exact JSON string; the review assertion
 * lives inside the signed bytes, binding approval to the exact digest
 * (decision #16, TM-SC-09).
 *
 * @param array<string,mixed> $overrides uid | version | review_status | manifest (mintManifest overrides)
 * @return array{json:string,signature:string,key_id:string,digest:string,manifest:array<string,mixed>,manifest_json:string,version:string,uid:string}
 */
public function mintRelease(array $overrides = []): array
{
    $uid = (string) ($overrides['uid'] ?? 'acme/midnight-theme');
    $version = (string) ($overrides['version'] ?? '1.0.0');
    $manifest = $this->mintManifest(
        ((array) ($overrides['manifest'] ?? [])) + ['uid' => $uid, 'version' => $version]
    );
    $signed = $this->signedDoc([
        'format' => 'rb-release.v1',
        'uid' => $uid,
        'version' => $version,
        'review' => [
            'status' => (string) ($overrides['review_status'] ?? 'approved'),
            'decided_at' => '2026-07-01T00:00:00Z',
        ],
        'manifest' => $manifest,
    ]);
    return $signed + [
        'digest' => hash('sha256', $signed['json']),
        'manifest' => $manifest,
        'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'version' => $version,
        'uid' => $uid,
    ];
}

/**
 * The rb-release-envelope.v1 body the release fetch endpoint serves
 * (consumed via ArrayRegistryTransport in tests and seed tooling).
 *
 * @param array<string,mixed> $overrides passed through to mintRelease()
 * @return array{body:string,release:array<string,mixed>}
 */
public function mintReleaseEnvelope(array $overrides = []): array
{
    $release = $this->mintRelease($overrides);
    return [
        'body' => json_encode([
            'format' => 'rb-release-envelope.v1',
            'document' => $release['json'],
            'signature' => base64_encode($release['signature']),
            'key_id' => $release['key_id'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'release' => $release,
    ];
}
```

Note the manifest-override merge in `mintRelease` uses `+` so an explicit `manifest.uid`/`manifest.version` override (for identity-mismatch fixtures) wins over the derived values.

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/Support/SigningHarnessTest.php`
Expected: PASS.

- [ ] **Step 5: Write the failing fixtures test**

Append to `tests/Integration/Core/RegistryFixturesTest.php`:

```php
public function test_seed_release_row_is_digest_consistent_and_hydrated(): void
{
    $root = SigningHarness::generate();
    $dir = sys_get_temp_dir() . '/rb-fixture-artifacts-' . bin2hex(random_bytes(4));
    $seeded = RegistryFixtures::seed($this->db, $root, $dir);

    $release = $this->db->pdo()->query('SELECT * FROM package_releases WHERE id = ' . (int) $seeded['release_id'])->fetch();
    self::assertSame($seeded['release_digest'], $release['digest']);
    self::assertSame(hash('sha256', $seeded['release_document']), $release['digest']);
    self::assertSame('approved', $release['review_status']);
    self::assertNotEmpty($release['manifest_json']);
    self::assertSame('rb-manifest.v2', json_decode((string) $release['manifest_json'], true)['format']);
    self::assertSame($root->keyId(), $release['signed_key_id']);
    self::assertTrue(SigningHarness::verify($seeded['release_document'], (string) $release['signature'], $root->publicKey()));
    self::assertSame($seeded['release_document'], file_get_contents($dir . '/' . $release['digest'] . '.json'));

    $second = RegistryFixtures::seedRelease($this->db, $root, $seeded, ['version' => '1.1.0'], $dir);
    self::assertSame(hash('sha256', $second['document']), $second['digest']);
    $latest = $this->db->pdo()->query('SELECT latest_release_id FROM packages WHERE id = ' . (int) $seeded['package_id'])->fetchColumn();
    self::assertSame($second['release_id'], (int) $latest);

    array_map('unlink', glob($dir . '/*.json') ?: []);
    @rmdir($dir);
}
```

- [ ] **Step 6: Run to verify failure, then implement the fixtures changes**

Run: `vendor/bin/phpunit tests/Integration/Core/RegistryFixturesTest.php` — expected FAIL (signature/return-shape).

In `tests/Support/Phase5/RegistryFixtures.php`:
1. Change `seed` to `seed(Database $db, SigningHarness $root, ?string $artifactDir = null): array`. Where it currently inserts the release row from `$root->mintRelease()`, use the minted values so the row is digest-consistent: `digest = $minted['digest']`, `manifest_json = $minted['manifest_json']`, `signature = $minted['signature']`, `signed_key_id = $root->keyId()`, `review_status = 'approved'`, `core_min = '0.1.0'`, `core_max = null`, `channel = 'stable'`. Keep everything else (registry/key/publisher/package inserts) unchanged. After the insert, when `$artifactDir !== null`: `if (!is_dir($artifactDir)) { mkdir($artifactDir, 0775, true); } file_put_contents($artifactDir . '/' . $minted['digest'] . '.json', $minted['json']);`. Extend the returned array with `'release_digest' => $minted['digest'], 'release_document' => $minted['json']`.
2. Add:

```php
/**
 * Seed one more hydrated, approved release for the seeded package (update /
 * rollback fixtures). Bumps packages.latest_release_id to the new row.
 *
 * @param array{package_id:int} $seeded  the seed() return value
 * @param array<string,mixed> $overrides mintRelease overrides (default version 1.1.0)
 * @return array{release_id:int,digest:string,document:string,version:string}
 */
public static function seedRelease(
    Database $db,
    SigningHarness $root,
    array $seeded,
    array $overrides = [],
    ?string $artifactDir = null,
): array {
    $minted = $root->mintRelease($overrides + ['version' => '1.1.0']);
    $stmt = $db->pdo()->prepare(
        'INSERT INTO package_releases
            (package_id, version, digest, source_url, license, core_min, core_max, manifest_json,
             signature, signed_key_id, review_status, channel, advisory_status, published_at)
         VALUES (:package_id, :version, :digest, NULL, :license, :core_min, :core_max, :manifest_json,
                 :signature, :signed_key_id, \'approved\', \'stable\', \'none\', UTC_TIMESTAMP())'
    );
    $manifest = $minted['manifest'];
    $stmt->execute([
        'package_id' => $seeded['package_id'],
        'version' => $minted['version'],
        'digest' => $minted['digest'],
        'license' => $manifest['license'] ?? null,
        'core_min' => $manifest['core']['min'] ?? null,
        'core_max' => $manifest['core']['max'] ?? null,
        'manifest_json' => $minted['manifest_json'],
        'signature' => $minted['signature'],
        'signed_key_id' => $root->keyId(),
    ]);
    $releaseId = (int) $db->pdo()->lastInsertId();
    $db->pdo()->prepare('UPDATE packages SET latest_release_id = :rid WHERE id = :id')
        ->execute(['rid' => $releaseId, 'id' => $seeded['package_id']]);
    if ($artifactDir !== null) {
        if (!is_dir($artifactDir)) {
            mkdir($artifactDir, 0775, true);
        }
        file_put_contents($artifactDir . '/' . $minted['digest'] . '.json', $minted['json']);
    }
    return [
        'release_id' => $releaseId,
        'digest' => $minted['digest'],
        'document' => $minted['json'],
        'version' => $minted['version'],
    ];
}
```

(Adapt the INSERT column list to the exact `seed()` insert already in the file — same table, same style. If `seed()` inserts via a helper, reuse it.)

- [ ] **Step 7: Run the fixtures test, then the full Inc 2 regression set**

Run: `vendor/bin/phpunit tests/Integration/Core/RegistryFixturesTest.php` — expected PASS.
Run: `vendor/bin/phpunit tests/Unit/Security/Registry tests/Integration/Service/RegistrySnapshotServiceTest.php tests/Integration/Service/RegistryTrustServiceTest.php tests/Integration/Service/RegistryAdvisoryServiceTest.php tests/Integration/Worker/RegistryRefreshWorkerTest.php tests/Integration/Core/AppRegistryCatalogTest.php tests/Integration/Core/AppRegistryAdminTest.php`
Expected: PASS. If any Inc 2 assertion pinned the placeholder `mintRelease` shape (a top-level `digest` field inside the document, or the old `artifact` override), update **only that fixture usage** — never weaken a verification assertion.

- [ ] **Step 8: Commit**

```bash
git add tests/Support/Phase5/SigningHarness.php tests/Support/Phase5/RegistryFixtures.php tests/Unit/Support/SigningHarnessTest.php tests/Integration/Core/RegistryFixturesTest.php
git commit -m "test(phase5): lock the rb-release.v1 artifact contract - digest over exact bytes, in-band review, manifest.v2 nest (Inc 3 SP1)"
```

---

### Task 2: `PackagePolicyException` + `PermissionDiff` — the coded refusal + the permission vocabulary

The single source of (kind, key) → risk + human consent label, and the §9 permission-increase/reduction diff. Pure; no DB.

**Files:**
- Create: `src/Security/Packages/PackagePolicyException.php`
- Create: `src/Security/Packages/PermissionDiff.php`
- Test: `tests/Unit/Security/Packages/PermissionDiffTest.php`

**Interfaces:**
- Consumes: `CapabilityCatalog::{has,all,consent}`, `DataClasses::{has,risk,consent}`, `ApiScopes::SCOPES`, `WebhookEvents::EVENTS`.
- Produces: `PackagePolicyException` (`__construct(string $code, string $message)`; read via magic `$e->code` exactly like `RegistryVerificationException`); `PermissionDiff::describe(string $kind, string $key): array{kind,key,risk,label}` (risk ∈ `low|medium|high` — `protected` clamps to `high`); `PermissionDiff::diff(array $old, array $new): array{added,removed,unchanged}` (entries need only `kind`+`key`; output entries are `describe()`d).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PermissionDiff;
use PHPUnit\Framework\TestCase;

final class PermissionDiffTest extends TestCase
{
    public function test_describe_maps_each_kind_to_catalogue_risk_and_consent_label(): void
    {
        $capability = PermissionDiff::describe('capability', 'core.thread.lock');
        self::assertSame(['kind' => 'capability', 'key' => 'core.thread.lock'], ['kind' => $capability['kind'], 'key' => $capability['key']]);
        self::assertContains($capability['risk'], ['low', 'medium', 'high']);
        self::assertNotSame('', $capability['label']);

        self::assertSame('high', PermissionDiff::describe('data_class', 'content.private')['risk']);
        self::assertStringContainsString('private', strtolower(PermissionDiff::describe('data_class', 'content.private')['label']));
        self::assertSame('medium', PermissionDiff::describe('api_scope', 'read:boards')['risk']);
        self::assertSame('low', PermissionDiff::describe('event', 'topic.created')['risk']);
        self::assertSame('medium', PermissionDiff::describe('outbound_host', 'api.example.com')['risk']);
        self::assertStringContainsString('api.example.com', PermissionDiff::describe('outbound_host', 'api.example.com')['label']);
        self::assertSame('low', PermissionDiff::describe('job', 'sync')['risk']);
    }

    public function test_protected_capability_clamps_to_high_and_never_yields_a_null_label(): void
    {
        $described = PermissionDiff::describe('capability', 'core.owner.transfer');
        self::assertSame('high', $described['risk']);
        self::assertNotSame('', $described['label']);
    }

    public function test_unknown_kind_refuses_with_coded_exception(): void
    {
        try {
            PermissionDiff::describe('broker_service_typo', 'x');
            self::fail('expected PackagePolicyException');
        } catch (PackagePolicyException $e) {
            self::assertSame('unknown_field', $e->code);
        }
    }

    public function test_diff_partitions_added_removed_unchanged_across_kinds(): void
    {
        $old = [
            ['kind' => 'data_class', 'key' => 'package.own_storage'],
            ['kind' => 'event', 'key' => 'topic.created'],
        ];
        $new = [
            ['kind' => 'data_class', 'key' => 'package.own_storage'],
            ['kind' => 'data_class', 'key' => 'content.private'],
            ['kind' => 'outbound_host', 'key' => 'api.example.com'],
        ];
        $diff = PermissionDiff::diff($old, $new);

        self::assertSame(
            [['data_class', 'content.private'], ['outbound_host', 'api.example.com']],
            array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['added'])
        );
        self::assertSame([['event', 'topic.created']], array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['removed']));
        self::assertSame([['data_class', 'package.own_storage']], array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['unchanged']));
        self::assertSame('high', $diff['added'][0]['risk']);
    }

    public function test_identical_sets_diff_to_no_change(): void
    {
        $set = [['kind' => 'api_scope', 'key' => 'read:boards']];
        $diff = PermissionDiff::diff($set, $set);
        self::assertSame([], $diff['added']);
        self::assertSame([], $diff['removed']);
        self::assertCount(1, $diff['unchanged']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/PermissionDiffTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement**

`src/Security/Packages/PackagePolicyException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

/**
 * Coded, fail-closed package-policy refusal (manifest validation, security
 * gate, lifecycle preconditions — P5-02/P5-07-A). Mirrors
 * RegistryVerificationException: $e->code is the stable machine token for
 * tests/telemetry; getMessage() is the operator-facing sentence.
 */
final class PackagePolicyException extends \RuntimeException
{
    public function __construct(private readonly string $policyCode, string $message)
    {
        parent::__construct($message);
    }

    public function __get(string $name): string
    {
        if ($name === 'code') {
            return $this->policyCode;
        }
        throw new \LogicException('Undefined property: ' . self::class . '::$' . $name);
    }
}
```

`src/Security/Packages/PermissionDiff.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Security\ApiScopes;
use App\Security\CapabilityCatalog;
use App\Security\DataClasses;
use App\Security\WebhookEvents;

/**
 * Pure permission vocabulary for the package lifecycle (P5-02): one source of
 * risk class + human consent label per (kind, key), and the §9
 * permission-increase/reduction diff between two declared sets. risk_class is
 * clamped to the installed_package_permissions ENUM (low|medium|high) —
 * protected entries never reach a grant (ManifestValidator refuses them).
 */
final class PermissionDiff
{
    /** @return array{kind:string,key:string,risk:string,label:string} */
    public static function describe(string $kind, string $key): array
    {
        [$risk, $label] = match ($kind) {
            'capability' => [
                CapabilityCatalog::has($key) ? CapabilityCatalog::all()[$key]['risk'] : 'high',
                (CapabilityCatalog::has($key) ? CapabilityCatalog::consent($key) : null)
                    ?? 'Protected capability — never grantable to a package.',
            ],
            'data_class' => [
                DataClasses::has($key) ? DataClasses::risk($key) : 'high',
                (DataClasses::has($key) ? DataClasses::consent($key) : null)
                    ?? 'Protected data class — never grantable to a package.',
            ],
            'api_scope' => ['medium', 'Use the read-only API: ' . (ApiScopes::SCOPES[$key] ?? $key) . '.'],
            'event' => ['low', 'Receive webhook events: ' . (WebhookEvents::EVENTS[$key] ?? $key) . '.'],
            'outbound_host' => ['medium', 'Send outbound requests to ' . $key . '.'],
            'job' => ['low', 'Run the scheduled job "' . $key . '".'],
            default => throw new PackagePolicyException('unknown_field', 'Unknown permission kind: ' . $kind . '.'),
        };

        return [
            'kind' => $kind,
            'key' => $key,
            'risk' => $risk === 'protected' ? 'high' : (string) $risk,
            'label' => (string) $label,
        ];
    }

    /**
     * @param list<array{kind:string,key:string}> $old
     * @param list<array{kind:string,key:string}> $new
     * @return array{added:list<array{kind:string,key:string,risk:string,label:string}>,
     *               removed:list<array{kind:string,key:string,risk:string,label:string}>,
     *               unchanged:list<array{kind:string,key:string,risk:string,label:string}>}
     */
    public static function diff(array $old, array $new): array
    {
        $index = static function (array $perms): array {
            $out = [];
            foreach ($perms as $p) {
                $out[(string) $p['kind'] . ':' . (string) $p['key']] = ['kind' => (string) $p['kind'], 'key' => (string) $p['key']];
            }
            return $out;
        };
        $oldMap = $index($old);
        $newMap = $index($new);
        $describe = static fn (array $entries): array => array_values(
            array_map(static fn (array $p): array => self::describe($p['kind'], $p['key']), $entries)
        );

        return [
            'added' => $describe(array_diff_key($newMap, $oldMap)),
            'removed' => $describe(array_diff_key($oldMap, $newMap)),
            'unchanged' => $describe(array_intersect_key($newMap, $oldMap)),
        ];
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/PermissionDiffTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Security/Packages/PackagePolicyException.php src/Security/Packages/PermissionDiff.php tests/Unit/Security/Packages/PermissionDiffTest.php
git commit -m "feat(phase5): coded package-policy refusals + permission risk/consent vocabulary and diff (Inc 3, P5-02 SP1)"
```

---

### Task 3: `PackageManifest` + `ManifestValidator` — fail-closed `rb-manifest.v2` validation (pure)

The §9 Manifest-validation scenario: unknown fields, invalid/protected capability names, undeclared-vocabulary scopes/events/data classes, malformed hosts/jobs/settings schema, bad core range, and identity mismatches all refuse **before any lifecycle row or file is written**.

**Files:**
- Create: `src/Security/Packages/PackageManifest.php`
- Create: `src/Security/Packages/ManifestValidator.php`
- Test: `tests/Unit/Security/Packages/ManifestValidatorTest.php`

**Interfaces:**
- Consumes: `PermissionDiff::describe` (Task 2), `PackageIdentity::isValidUid`, `CoreVersion::{isValid,satisfies}`, `CapabilityCatalog::{has,isProtected}`, `DataClasses::{has,grantable}`, `ApiScopes::isValid`, `WebhookEvents::domainEvents` (excludes `ping`), `SigningHarness::mintManifest` (test fixtures).
- Produces: `ManifestValidator::validate(array $manifest, string $expectedUid, string $expectedVersion): PackageManifest`; `PackageManifest` readonly VO with `coreCompatible(): bool` and `->permissions` as `list<array{kind,key,risk,label}>` (the exact list Tasks 8–10 snapshot into `installed_package_permissions`). Constants: `ManifestValidator::FORMAT = 'rb-manifest.v2'`, `ManifestValidator::TYPES`, `ManifestValidator::MAX_STORAGE_QUOTA_KB = 10_240`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

final class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;
    private SigningHarness $harness;

    protected function setUp(): void
    {
        $this->validator = new ManifestValidator();
        $this->harness = SigningHarness::generate();
    }

    /** @param array<string,mixed> $overrides */
    private function manifest(array $overrides = []): array
    {
        return $this->harness->mintManifest($overrides);
    }

    /** @param array<string,mixed> $overrides */
    private function assertRefusal(string $expectedCode, array $overrides): void
    {
        try {
            $this->validator->validate($this->manifest($overrides), 'acme/midnight-theme', '1.0.0');
            self::fail('expected refusal ' . $expectedCode);
        } catch (PackagePolicyException $e) {
            self::assertSame($expectedCode, $e->code);
        }
    }

    public function test_valid_manifest_produces_the_typed_snapshot(): void
    {
        $manifest = $this->validator->validate($this->manifest([
            'permissions' => [
                'data_classes' => ['package.own_storage', 'content.public'],
                'api_scopes' => ['read:boards'],
                'events' => ['topic.created'],
                'outbound_hosts' => ['api.example.com'],
                'jobs' => [['name' => 'sync', 'schedule' => 'daily']],
            ],
            'settings_schema' => ['fields' => [
                ['key' => 'api_key', 'type' => 'string', 'label' => 'API key', 'required' => true],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['light', 'dark']],
            ]],
            'install' => ['retention_days' => 14],
        ]), 'acme/midnight-theme', '1.0.0');

        self::assertSame('acme/midnight-theme', $manifest->uid);
        self::assertSame('theme', $manifest->type);
        self::assertSame('1.0.0', $manifest->version);
        self::assertSame('Midnight Theme', $manifest->name);
        self::assertSame('0.1.0', $manifest->coreMin);
        self::assertNull($manifest->coreMax);
        self::assertTrue($manifest->coreCompatible());
        self::assertSame(14, $manifest->retentionDays);
        self::assertSame(64, $manifest->storageQuotaKb);
        self::assertSame(['homepage' => 'https://acme.example/midnight'], $manifest->support);
        self::assertCount(2, $manifest->settingsSchema['fields']);

        $keys = array_map(static fn (array $p): string => $p['kind'] . ':' . $p['key'], $manifest->permissions);
        self::assertSame([
            'data_class:package.own_storage', 'data_class:content.public',
            'api_scope:read:boards', 'event:topic.created',
            'outbound_host:api.example.com', 'job:sync',
        ], $keys);
        foreach ($manifest->permissions as $p) {
            self::assertContains($p['risk'], ['low', 'medium', 'high']);
            self::assertNotSame('', $p['label']);
        }
    }

    public function test_incompatible_core_range_still_validates_and_reports_incompatibility(): void
    {
        $manifest = $this->validator->validate(
            $this->manifest(['core' => ['min' => '99.0.0', 'max' => null]]),
            'acme/midnight-theme',
            '1.0.0'
        );
        self::assertFalse($manifest->coreCompatible()); // enforcement is the install path's job (Task 9)
    }

    /**
     * @dataProvider providerRefusals
     * @param array<string,mixed> $overrides
     */
    public function test_malformed_manifests_refuse_with_the_exact_code(string $code, array $overrides): void
    {
        $this->assertRefusal($code, $overrides);
    }

    /** @return iterable<string,array{string,array<string,mixed>}> */
    public static function providerRefusals(): iterable
    {
        yield 'wrong format' => ['manifest_format', ['format' => 'rb-manifest.v1']];
        yield 'unknown top-level key' => ['unknown_field', ['sneaky' => true]];
        yield 'uid mismatch' => ['manifest_identity', ['uid' => 'acme/other']];
        yield 'version mismatch' => ['manifest_identity', ['version' => '9.9.9']];
        yield 'invalid uid syntax' => ['manifest_identity', ['uid' => 'NotValid']];
        yield 'server_extension refused' => ['manifest_type', ['type' => 'server_extension']];
        yield 'unknown type' => ['manifest_type', ['type' => 'widget']];
        yield 'empty name' => ['manifest_name', ['name' => '  ']];
        yield 'name too long' => ['manifest_name', ['name' => str_repeat('x', 191)]];
        yield 'description too long' => ['manifest_field', ['description' => str_repeat('x', 513)]];
        yield 'core missing min' => ['manifest_core', ['core' => ['max' => '2.0.0']]];
        yield 'core invalid min' => ['manifest_core', ['core' => ['min' => 'not-semver']]];
        yield 'core unknown key' => ['manifest_core', ['core' => ['min' => '0.1.0', 'pin' => true]]];
        yield 'unknown permission kind' => ['unknown_field', ['permissions' => ['broker_services' => ['db']]]];
        yield 'unknown capability' => ['unknown_capability', ['permissions' => ['capabilities' => ['core.nonsense']]]];
        yield 'protected capability' => ['protected_capability', ['permissions' => ['capabilities' => ['core.owner.transfer']]]];
        yield 'unknown data class' => ['unknown_data_class', ['permissions' => ['data_classes' => ['content.secret']]]];
        yield 'protected data class' => ['protected_data_class', ['permissions' => ['data_classes' => ['security.config']]]];
        yield 'unknown api scope' => ['unknown_api_scope', ['permissions' => ['api_scopes' => ['write:everything']]]];
        yield 'unknown event' => ['unknown_event', ['permissions' => ['events' => ['user.deleted']]]];
        yield 'ping event refused' => ['unknown_event', ['permissions' => ['events' => ['ping']]]];
        yield 'host with scheme' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['https://api.example.com']]]];
        yield 'host uppercase' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['API.example.com']]]];
        yield 'host wildcard' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['*.example.com']]]];
        yield 'host bare label' => ['outbound_host', ['permissions' => ['outbound_hosts' => ['localhost']]]];
        yield 'duplicate permission' => ['manifest_field', ['permissions' => ['data_classes' => ['content.public', 'content.public']]]];
        yield 'job missing schedule' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync']]]]];
        yield 'job bad name' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'Sync!', 'schedule' => 'daily']]]]];
        yield 'job unknown schedule' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync', 'schedule' => 'yearly']]]]];
        yield 'job unknown key' => ['job_declaration', ['permissions' => ['jobs' => [['name' => 'sync', 'schedule' => 'daily', 'cron' => '* * * * *']]]]];
        yield 'settings not fields' => ['settings_schema', ['settings_schema' => ['fielden' => []]]];
        yield 'settings empty fields' => ['settings_schema', ['settings_schema' => ['fields' => []]]];
        yield 'settings bad key' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'Bad Key', 'type' => 'string', 'label' => 'x']]]]];
        yield 'settings duplicate key' => ['settings_schema', ['settings_schema' => ['fields' => [
            ['key' => 'a', 'type' => 'string', 'label' => 'x'],
            ['key' => 'a', 'type' => 'string', 'label' => 'y'],
        ]]]];
        yield 'settings unknown type' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'a', 'type' => 'json', 'label' => 'x']]]]];
        yield 'select without options' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'a', 'type' => 'select', 'label' => 'x']]]]];
        yield 'options on non-select' => ['settings_schema', ['settings_schema' => ['fields' => [['key' => 'a', 'type' => 'string', 'label' => 'x', 'options' => ['y']]]]]];
        yield 'quota negative' => ['storage_quota', ['storage_quota_kb' => -1]];
        yield 'quota over cap' => ['storage_quota', ['storage_quota_kb' => 10_241]];
        yield 'quota non-int' => ['storage_quota', ['storage_quota_kb' => '64']];
        yield 'retention zero' => ['install_policy', ['install' => ['retention_days' => 0]]];
        yield 'retention over cap' => ['install_policy', ['install' => ['retention_days' => 366]]];
        yield 'install unknown key' => ['install_policy', ['install' => ['auto_update' => true]]];
        yield 'support http' => ['support_link', ['support' => ['homepage' => 'http://acme.example']]];
        yield 'support unknown key' => ['support_link', ['support' => ['donate' => 'https://acme.example']]];
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/ManifestValidatorTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement `PackageManifest`**

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Support\CoreVersion;

/** A validated rb-manifest.v2 (P5-02). Constructed only by ManifestValidator. */
final class PackageManifest
{
    /**
     * @param list<array{kind:string,key:string,risk:string,label:string}> $permissions
     * @param ?array{fields:list<array<string,mixed>>} $settingsSchema
     * @param array<string,string> $support
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $type,
        public readonly string $version,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $license,
        public readonly string $coreMin,
        public readonly ?string $coreMax,
        public readonly array $permissions,
        public readonly ?array $settingsSchema,
        public readonly int $storageQuotaKb,
        public readonly ?int $retentionDays,
        public readonly array $support,
    ) {
    }

    public function coreCompatible(): bool
    {
        return CoreVersion::satisfies($this->coreMin, $this->coreMax);
    }
}
```

- [ ] **Step 4: Implement `ManifestValidator`**

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Security\ApiScopes;
use App\Security\CapabilityCatalog;
use App\Security\DataClasses;
use App\Security\Registry\PackageIdentity;
use App\Security\WebhookEvents;
use App\Support\CoreVersion;

/**
 * Fail-closed rb-manifest.v2 validation (P5-02 SP1, §9 Manifest-validation).
 * Every declaration is checked against a code-owned catalogue; unknown fields,
 * unknown or protected permission names, malformed hosts/jobs/schemas, and
 * identity mismatches refuse before any lifecycle row or file is written.
 * Compatibility SYNTAX is validated here; compatibility ENFORCEMENT
 * (refusing an incompatible install) is the install path's job.
 */
final class ManifestValidator
{
    public const FORMAT = 'rb-manifest.v2';
    public const TYPES = ['theme', 'automation', 'remote_app', 'local'];
    public const MAX_STORAGE_QUOTA_KB = 10_240;

    private const TOP_KEYS = ['format', 'uid', 'type', 'version', 'name', 'description', 'license',
        'core', 'permissions', 'settings_schema', 'storage_quota_kb', 'install', 'support'];
    private const PERMISSION_KINDS = [
        'capabilities' => 'capability',
        'data_classes' => 'data_class',
        'api_scopes' => 'api_scope',
        'events' => 'event',
        'outbound_hosts' => 'outbound_host',
        'jobs' => 'job',
    ];
    private const SETTING_TYPES = ['string', 'boolean', 'integer', 'select'];
    private const SETTING_FIELD_KEYS = ['key', 'type', 'label', 'required', 'options'];
    private const JOB_SCHEDULES = ['hourly', 'daily', 'weekly'];
    private const HOST_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/';
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** @param array<string,mixed> $manifest */
    public function validate(array $manifest, string $expectedUid, string $expectedVersion): PackageManifest
    {
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            $this->refuse('manifest_format', 'Manifest must declare format ' . self::FORMAT . '.');
        }
        foreach (array_keys($manifest) as $key) {
            if (!in_array((string) $key, self::TOP_KEYS, true)) {
                $this->refuse('unknown_field', 'Unknown manifest field: ' . $key . '.');
            }
        }

        $uid = is_string($manifest['uid'] ?? null) ? $manifest['uid'] : '';
        if (!PackageIdentity::isValidUid($uid) || $uid !== $expectedUid) {
            $this->refuse('manifest_identity', 'Manifest uid does not match the release identity.');
        }
        $version = is_string($manifest['version'] ?? null) ? $manifest['version'] : '';
        if ($version === '' || $version !== $expectedVersion) {
            $this->refuse('manifest_identity', 'Manifest version does not match the release identity.');
        }
        $type = is_string($manifest['type'] ?? null) ? $manifest['type'] : '';
        if (!in_array($type, self::TYPES, true)) {
            $this->refuse('manifest_type', 'Package type "' . $type . '" is not allowed in Gate A.');
        }

        $name = trim($this->stringField($manifest, 'name', 190, 'manifest_name') ?? '');
        if ($name === '') {
            $this->refuse('manifest_name', 'Manifest name is required (max 190 characters).');
        }
        $description = $this->stringField($manifest, 'description', 512, 'manifest_field');
        $license = $this->stringField($manifest, 'license', 190, 'manifest_field');

        [$coreMin, $coreMax] = $this->core($manifest['core'] ?? null);
        $permissions = $this->permissions($manifest['permissions'] ?? []);
        $settingsSchema = $this->settingsSchema($manifest['settings_schema'] ?? null);
        $storageQuotaKb = $this->storageQuota($manifest['storage_quota_kb'] ?? 0);
        $retentionDays = $this->installPolicy($manifest['install'] ?? null);
        $support = $this->support($manifest['support'] ?? []);

        return new PackageManifest($uid, $type, $version, $name, $description, $license,
            $coreMin, $coreMax, $permissions, $settingsSchema, $storageQuotaKb, $retentionDays, $support);
    }

    private function refuse(string $code, string $message): never
    {
        throw new PackagePolicyException($code, $message);
    }

    /** @param array<string,mixed> $manifest */
    private function stringField(array $manifest, string $key, int $max, string $code): ?string
    {
        if (!array_key_exists($key, $manifest) || $manifest[$key] === null) {
            return null;
        }
        if (!is_string($manifest[$key]) || mb_strlen(trim($manifest[$key])) > $max) {
            $this->refuse($code, 'Manifest field "' . $key . '" must be a string of at most ' . $max . ' characters.');
        }
        return trim($manifest[$key]);
    }

    /** @return array{0:string,1:?string} */
    private function core(mixed $core): array
    {
        if (!is_array($core) || array_diff(array_keys($core), ['min', 'max']) !== []) {
            $this->refuse('manifest_core', 'Manifest core range must be an object with only min/max.');
        }
        $min = $core['min'] ?? null;
        if (!is_string($min) || !CoreVersion::isValid($min)) {
            $this->refuse('manifest_core', 'Manifest core.min must be a valid version.');
        }
        $max = $core['max'] ?? null;
        if ($max !== null && (!is_string($max) || !CoreVersion::isValid($max))) {
            $this->refuse('manifest_core', 'Manifest core.max must be null or a valid version.');
        }
        return [$min, $max];
    }

    /** @return list<array{kind:string,key:string,risk:string,label:string}> */
    private function permissions(mixed $in): array
    {
        if (!is_array($in)) {
            $this->refuse('manifest_field', 'Manifest permissions must be an object of kind lists.');
        }
        foreach (array_keys($in) as $kind) {
            if (!array_key_exists((string) $kind, self::PERMISSION_KINDS)) {
                $this->refuse('unknown_field', 'Unknown permission kind: ' . $kind . '.');
            }
        }

        $out = [];
        $seen = [];
        $add = function (string $kind, string $key) use (&$out, &$seen): void {
            $dedupe = $kind . ':' . $key;
            if (isset($seen[$dedupe])) {
                $this->refuse('manifest_field', 'Duplicate permission declaration: ' . $dedupe . '.');
            }
            $seen[$dedupe] = true;
            $out[] = PermissionDiff::describe($kind, $key);
        };

        foreach (($in['capabilities'] ?? []) as $key) {
            if (!is_string($key) || !CapabilityCatalog::has($key)) {
                $this->refuse('unknown_capability', 'Unknown capability name in manifest.');
            }
            if (CapabilityCatalog::isProtected($key)) {
                $this->refuse('protected_capability', 'Protected capabilities are never grantable to a package.');
            }
            $add('capability', $key);
        }
        foreach (($in['data_classes'] ?? []) as $key) {
            if (!is_string($key) || !DataClasses::has($key)) {
                $this->refuse('unknown_data_class', 'Unknown data class in manifest.');
            }
            if (!DataClasses::grantable($key)) {
                $this->refuse('protected_data_class', 'Protected data classes are never grantable to a package.');
            }
            $add('data_class', $key);
        }
        foreach (($in['api_scopes'] ?? []) as $key) {
            if (!is_string($key) || !ApiScopes::isValid($key)) {
                $this->refuse('unknown_api_scope', 'Unknown API scope in manifest.');
            }
            $add('api_scope', $key);
        }
        foreach (($in['events'] ?? []) as $key) {
            if (!is_string($key) || !isset(WebhookEvents::domainEvents()[$key])) {
                $this->refuse('unknown_event', 'Unknown or non-subscribable event in manifest.');
            }
            $add('event', $key);
        }
        foreach (($in['outbound_hosts'] ?? []) as $host) {
            if (!is_string($host) || preg_match(self::HOST_PATTERN, $host) !== 1) {
                $this->refuse('outbound_host', 'Outbound hosts must be explicit lowercase hostnames (no scheme, port, path, or wildcard).');
            }
            $add('outbound_host', $host);
        }
        foreach (($in['jobs'] ?? []) as $job) {
            if (!is_array($job) || array_diff(array_keys($job), ['name', 'schedule']) !== []) {
                $this->refuse('job_declaration', 'Each job must declare exactly name and schedule.');
            }
            $jobName = $job['name'] ?? null;
            $schedule = $job['schedule'] ?? null;
            if (!is_string($jobName) || preg_match(self::KEY_PATTERN, $jobName) !== 1
                || !is_string($schedule) || !in_array($schedule, self::JOB_SCHEDULES, true)) {
                $this->refuse('job_declaration', 'Job declarations must use a lowercase name and an hourly/daily/weekly schedule.');
            }
            $add('job', $jobName);
        }

        return $out;
    }

    /** @return ?array{fields:list<array<string,mixed>>} */
    private function settingsSchema(mixed $schema): ?array
    {
        if ($schema === null) {
            return null;
        }
        if (!is_array($schema) || array_keys($schema) !== ['fields'] || !is_array($schema['fields']) || $schema['fields'] === []) {
            $this->refuse('settings_schema', 'settings_schema must be {"fields": [non-empty list]}.');
        }
        $fields = [];
        $seenKeys = [];
        foreach ($schema['fields'] as $field) {
            if (!is_array($field) || array_diff(array_keys($field), self::SETTING_FIELD_KEYS) !== []) {
                $this->refuse('settings_schema', 'Unknown settings field property.');
            }
            $key = $field['key'] ?? null;
            $type = $field['type'] ?? null;
            $label = $field['label'] ?? null;
            if (!is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1 || isset($seenKeys[$key])) {
                $this->refuse('settings_schema', 'Settings field keys must be unique lowercase identifiers.');
            }
            if (!is_string($type) || !in_array($type, self::SETTING_TYPES, true)) {
                $this->refuse('settings_schema', 'Settings field type must be string/boolean/integer/select.');
            }
            if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 190) {
                $this->refuse('settings_schema', 'Settings field label is required (max 190 characters).');
            }
            if (array_key_exists('required', $field) && !is_bool($field['required'])) {
                $this->refuse('settings_schema', 'Settings field "required" must be a boolean.');
            }
            $hasOptions = array_key_exists('options', $field);
            if ($type === 'select') {
                $options = $field['options'] ?? null;
                if (!is_array($options) || $options === []
                    || $options !== array_values(array_filter($options, static fn ($o): bool => is_string($o) && $o !== '' && mb_strlen($o) <= 190))) {
                    $this->refuse('settings_schema', 'select fields require a non-empty list of string options.');
                }
            } elseif ($hasOptions) {
                $this->refuse('settings_schema', 'Only select fields may declare options.');
            }
            $seenKeys[$key] = true;
            $fields[] = $field;
        }
        return ['fields' => $fields];
    }

    private function storageQuota(mixed $quota): int
    {
        if (!is_int($quota) || $quota < 0 || $quota > self::MAX_STORAGE_QUOTA_KB) {
            $this->refuse('storage_quota', 'storage_quota_kb must be an integer between 0 and ' . self::MAX_STORAGE_QUOTA_KB . '.');
        }
        return $quota;
    }

    private function installPolicy(mixed $install): ?int
    {
        if ($install === null) {
            return null;
        }
        if (!is_array($install) || array_diff(array_keys($install), ['retention_days']) !== []) {
            $this->refuse('install_policy', 'install policy allows only retention_days.');
        }
        if (!array_key_exists('retention_days', $install)) {
            return null;
        }
        $days = $install['retention_days'];
        if (!is_int($days) || $days < 1 || $days > 365) {
            $this->refuse('install_policy', 'install.retention_days must be an integer between 1 and 365.');
        }
        return $days;
    }

    /** @return array<string,string> */
    private function support(mixed $support): array
    {
        if (!is_array($support) || array_diff(array_keys($support), ['homepage', 'issues']) !== []) {
            $this->refuse('support_link', 'support allows only homepage and issues.');
        }
        $out = [];
        foreach ($support as $key => $url) {
            if (!is_string($url) || !str_starts_with($url, 'https://') || mb_strlen($url) > 512) {
                $this->refuse('support_link', 'Support links must be https:// URLs (max 512 characters).');
            }
            $out[(string) $key] = $url;
        }
        return $out;
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/Security/Packages/ManifestValidatorTest.php`
Expected: PASS (1 typed-snapshot test + 1 compat test + ~44 provider refusal cases).

- [ ] **Step 6: Commit**

```bash
git add src/Security/Packages/PackageManifest.php src/Security/Packages/ManifestValidator.php tests/Unit/Security/Packages/ManifestValidatorTest.php
git commit -m "feat(phase5): fail-closed rb-manifest.v2 validator + typed manifest snapshot (Inc 3, P5-02 SP1)"
```

---
### Task 4: Migrations `0069` + `0070`, the §C re-baseline, and `SCHEMA.md` v1.29

`MigrationLedgerTest` enforces gapless numbering with count==max, so the program plan's `0072` allocation for the review/security tables is **unbuildable** while `0070`/`0071` don't exist. Re-baseline §C (2026-07-01 precedent): review/security tables take **`0070`**, themes shift to `0071`, integrations to `0072`.

**Files:**
- Create: `database/migrations/0069_phase5_package_lifecycle.php`
- Create: `database/migrations/0070_phase5_publisher_review_security.php`
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§C table + dated note)
- Modify: `SCHEMA.md` (§5A shapes, §9 changelog v1.29, header v1.27→v1.29)
- Test: `tests/Integration/Core/AppPhase5PackageLifecycleSchemaTest.php`

**Interfaces:**
- Produces (later tasks rely on these exact columns): `installed_packages.{pinned, update_policy, staged_release_id, staged_digest, settings_json, export_json, exported_at, retain_until, uninstalled_at, quarantine_reason, last_health_check_at}`; `installed_packages.state` gains `'uninstalled'`; `package_history.event` gains `'update_staged','export','purge'`; `installed_package_permissions.kind` gains `'api_scope','event'`; tables `publisher_signing_keys`, `package_review_decisions`, `package_transparency_log`.

- [ ] **Step 1: Write the failing shape test**

`tests/Integration/Core/AppPhase5PackageLifecycleSchemaTest.php` (follow `AppPhase5FoundationSchemaTest`'s style — it queries `information_schema` through `$this->db->pdo()`):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppPhase5PackageLifecycleSchemaTest extends TestCase
{
    /** @return array<string,array{type:string,nullable:string}> */
    private function columns(string $table): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['COLUMN_NAME']] = ['type' => $row['COLUMN_TYPE'], 'nullable' => $row['IS_NULLABLE']];
        }
        return $out;
    }

    public function test_0069_adds_the_lifecycle_columns_and_enum_widens(): void
    {
        $cols = $this->columns('installed_packages');
        foreach (['pinned', 'update_policy', 'staged_release_id', 'staged_digest', 'settings_json', 'export_json',
                  'exported_at', 'retain_until', 'uninstalled_at', 'quarantine_reason', 'last_health_check_at'] as $col) {
            self::assertArrayHasKey($col, $cols, "installed_packages.$col missing");
        }
        self::assertSame("enum('manual','notify')", $cols['update_policy']['type']);
        self::assertSame(
            "enum('installed','enabled','disabled','quarantined','uninstalling','uninstalled')",
            $cols['state']['type']
        );
        self::assertSame(
            "enum('capability','data_class','outbound_host','job','broker_service','api_scope','event')",
            $this->columns('installed_package_permissions')['kind']['type']
        );
        self::assertStringContainsString("'update_staged','export','purge'", $this->columns('package_history')['event']['type']);

        $fk = $this->db->pdo()->query(
            "SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installed_packages' AND COLUMN_NAME = 'staged_release_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->fetchColumn();
        self::assertSame('package_releases', $fk);
    }

    public function test_0070_creates_the_review_security_tables(): void
    {
        $keys = $this->columns('publisher_signing_keys');
        foreach (['publisher_id', 'key_id', 'algorithm', 'public_key', 'status', 'valid_from', 'valid_until'] as $col) {
            self::assertArrayHasKey($col, $keys);
        }
        self::assertSame("enum('active','rotated','revoked')", $keys['status']['type']);

        $decisions = $this->columns('package_review_decisions');
        foreach (['package_id', 'release_id', 'version', 'digest', 'decision', 'decided_at', 'source', 'evidence_json'] as $col) {
            self::assertArrayHasKey($col, $decisions);
        }
        self::assertSame("enum('approved','rejected','revoked')", $decisions['decision']['type']);
        self::assertSame('char(64)', $decisions['digest']['type']);

        $log = $this->columns('package_transparency_log');
        foreach (['package_uid', 'version', 'digest', 'event', 'source', 'actor_id', 'registry_id', 'detail', 'created_at'] as $col) {
            self::assertArrayHasKey($col, $log);
        }
        self::assertArrayNotHasKey('updated_at', $log, 'transparency log is append-only');
        self::assertSame(
            "enum('release_verified','install','update','rollback','uninstall','quarantine','force_disable','revoked')",
            $log['event']['type']
        );
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `RB_TEST_FRESH=1 vendor/bin/phpunit tests/Integration/Core/AppPhase5PackageLifecycleSchemaTest.php`
Expected: FAIL — missing columns/tables. (`RB_TEST_FRESH=1` forces a fresh migrate so the new files apply.)

- [ ] **Step 3: Write `database/migrations/0069_phase5_package_lifecycle.php`**

```php
<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 3 (P5-02): installed-package lifecycle columns.
 * pin/update-policy/staged-update/settings/export/retention/quarantine state
 * on installed_packages, plus ENUM widens: state +'uninstalled',
 * package_history.event +'update_staged'/'export'/'purge',
 * installed_package_permissions.kind +'api_scope'/'event' (manifest.v2
 * declares API scopes and webhook events as consentable permissions now;
 * live credential minting is Inc 5). settings_json stays dark until Inc 5.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              ADD COLUMN pinned               TINYINT(1)  NOT NULL DEFAULT 0 AFTER health,
              ADD COLUMN update_policy        ENUM('manual','notify') NOT NULL DEFAULT 'manual' AFTER pinned,
              ADD COLUMN staged_release_id    BIGINT UNSIGNED NULL AFTER update_policy,
              ADD COLUMN staged_digest        CHAR(64)    NULL AFTER staged_release_id,
              ADD COLUMN settings_json        MEDIUMTEXT  NULL AFTER staged_digest,
              ADD COLUMN export_json          MEDIUMTEXT  NULL AFTER settings_json,
              ADD COLUMN exported_at          DATETIME    NULL AFTER export_json,
              ADD COLUMN retain_until         DATETIME    NULL AFTER exported_at,
              ADD COLUMN uninstalled_at       DATETIME    NULL AFTER retain_until,
              ADD COLUMN quarantine_reason    VARCHAR(255) NULL AFTER uninstalled_at,
              ADD COLUMN last_health_check_at DATETIME    NULL AFTER quarantine_reason,
              ADD CONSTRAINT fk_installed_staged FOREIGN KEY (staged_release_id)
                REFERENCES package_releases(id) ON DELETE SET NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              MODIFY state ENUM('installed','enabled','disabled','quarantined','uninstalling','uninstalled')
                NOT NULL DEFAULT 'installed'
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health','update_staged','export','purge') NOT NULL
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_package_permissions
              MODIFY kind ENUM('capability','data_class','outbound_host','job','broker_service','api_scope','event') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM installed_package_permissions WHERE kind IN ('api_scope','event')");
        $pdo->exec("DELETE FROM package_history WHERE event IN ('update_staged','export','purge')");
        $pdo->exec("DELETE FROM installed_packages WHERE state = 'uninstalled'");
        $pdo->exec('ALTER TABLE installed_packages DROP FOREIGN KEY fk_installed_staged');
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              MODIFY state ENUM('installed','enabled','disabled','quarantined','uninstalling')
                NOT NULL DEFAULT 'installed'
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_packages
              DROP COLUMN last_health_check_at, DROP COLUMN quarantine_reason, DROP COLUMN uninstalled_at,
              DROP COLUMN retain_until, DROP COLUMN exported_at, DROP COLUMN export_json,
              DROP COLUMN settings_json, DROP COLUMN staged_digest, DROP COLUMN staged_release_id,
              DROP COLUMN update_policy, DROP COLUMN pinned
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE package_history
              MODIFY event ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine',
                                'uninstall','consent','health') NOT NULL
        SQL);
        $pdo->exec(<<<'SQL'
            ALTER TABLE installed_package_permissions
              MODIFY kind ENUM('capability','data_class','outbound_host','job','broker_service') NOT NULL
        SQL);
    }
};
```

- [ ] **Step 4: Write `database/migrations/0070_phase5_publisher_review_security.php`**

```php
<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 3 (P5-07-A part 1): review-enforcement + security-response
 * schema. package_review_decisions caches the signed review evidence an
 * install relied on (§8.2 #5); package_transparency_log is the append-only
 * publication/lifecycle history for released/revoked digests;
 * publisher_signing_keys is the Inc 5 publisher-key custody shape landed here
 * per the §C allocation (documented-inert until the P5-07-A part 2 console).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE publisher_signing_keys (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              publisher_id   BIGINT UNSIGNED NOT NULL,
              key_id         VARCHAR(190)    NOT NULL,
              algorithm      VARCHAR(32)     NOT NULL,
              public_key     VARBINARY(1024) NOT NULL,
              status         ENUM('active','rotated','revoked') NOT NULL DEFAULT 'active',
              valid_from     DATETIME        NULL,
              valid_until    DATETIME        NULL,
              revoked_at     DATETIME        NULL,
              revoked_reason VARCHAR(255)    NULL,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_publisher_key (publisher_id, key_id),
              CONSTRAINT fk_pubkey_publisher FOREIGN KEY (publisher_id)
                REFERENCES package_publishers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE package_review_decisions (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_id    BIGINT UNSIGNED NOT NULL,
              release_id    BIGINT UNSIGNED NULL,
              version       VARCHAR(64)     NOT NULL,
              digest        CHAR(64)        NOT NULL,
              decision      ENUM('approved','rejected','revoked') NOT NULL,
              decided_at    DATETIME        NULL,
              source        ENUM('release_document','advisory','local') NOT NULL DEFAULT 'release_document',
              evidence_json MEDIUMTEXT      NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_review_decision (package_id, digest),
              CONSTRAINT fk_review_package FOREIGN KEY (package_id) REFERENCES packages(id)         ON DELETE CASCADE,
              CONSTRAINT fk_review_release FOREIGN KEY (release_id) REFERENCES package_releases(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE package_transparency_log (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_uid VARCHAR(190)    NOT NULL,
              version     VARCHAR(64)     NULL,
              digest      CHAR(64)        NULL,
              event       ENUM('release_verified','install','update','rollback','uninstall','quarantine','force_disable','revoked') NOT NULL,
              source      ENUM('snapshot','release_document','advisory','local') NOT NULL,
              actor_id    BIGINT UNSIGNED NULL,
              registry_id BIGINT UNSIGNED NULL,
              detail      VARCHAR(512)    NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_transparency_package (package_uid, created_at),
              KEY idx_transparency_digest (digest),
              CONSTRAINT fk_transparency_actor    FOREIGN KEY (actor_id)    REFERENCES users(id)              ON DELETE SET NULL,
              CONSTRAINT fk_transparency_registry FOREIGN KEY (registry_id) REFERENCES package_registries(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS package_transparency_log');
        $pdo->exec('DROP TABLE IF EXISTS package_review_decisions');
        $pdo->exec('DROP TABLE IF EXISTS publisher_signing_keys');
    }
};
```

- [ ] **Step 5: Migrate + run the shape test and the ledger guard**

```bash
php bin/console migrate
RB_TEST_FRESH=1 vendor/bin/phpunit tests/Integration/Core/AppPhase5PackageLifecycleSchemaTest.php tests/Unit/Core/MigrationLedgerTest.php tests/Integration/Core/AppPhase5FoundationSchemaTest.php
```
Expected: PASS ×3 (ledger stays gapless at max=0070; the foundation shape test must not regress).

- [ ] **Step 6: Re-baseline §C in the program plan**

In `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md`, update the §C rows for 0070–0072:

```
| `0069` | `phase5_package_lifecycle` — `installed_packages` pin/update_policy/staged/settings/export cols + ENUM widens | Inc 3 (P5-02) |
| `0070` | `phase5_publisher_review_security` — publisher_signing_keys, package_review_decisions, package_transparency_log | Inc 3 (P5-02 / P5-07-A enforcement pre-req) |
| `0071` | `phase5_theme_packages` — theme_packages/versions/assets/state | Inc 4 (P5-03) |
| `0072` | `phase5_package_integrations` — `installed_package_settings` + remote-app credential linkage | Inc 5 (P5-04) |
```

and append to the existing re-baseline blockquote:

```
> **Re-baselined again 2026-07-02 (Inc 3).** The F1 guard requires gapless
> numbering, so the review/security tables could not take `0072` while the
> theme (`0070`) and integration (`0071`) migrations were unbuilt. Inc 3 landed
> them as `0070`; themes move to `0071`, integrations to `0072`. Rows `0073`+
> are unchanged.
```

- [ ] **Step 7: Update `SCHEMA.md`**

1. Header line 3: `**Status:** v1.29` (it is stale at v1.27; the changelog is already at v1.28).
2. §5A — extend the `installed_packages` bullet with the 0069 columns and the widened `state` ENUM; note `update_policy` has no `auto` in Gate A; note `settings_json` is dark until Inc 5; extend the `package_history` bullet with the three new events; extend `installed_package_permissions` with the two new kinds. Add three new bullets after the `registry_snapshots` bullet:

```markdown
- `publisher_signing_keys(id, publisher_id, key_id, algorithm, public_key, status, valid_from, valid_until, revoked_at, revoked_reason, created_at)` with unique `(publisher_id, key_id)` — publisher key custody shape (0070); **documented-inert until the Inc 5 P5-07-A part 2 console** (public key bytes only, mirroring `registry_trust_keys`).
- `package_review_decisions(id, package_id, release_id, version, digest, decision, decided_at, source, evidence_json, created_at)` (0070) — append-only local cache of the signed review evidence an install relied on (§8.2 #5); written at acquisition time; `decision` bound to the exact digest (decision #16).
- `package_transparency_log(id, package_uid, version, digest, event, source, actor_id, registry_id, detail, created_at)` (0070) — append-only publication/lifecycle history for released/revoked digests; no update/delete path exists in code.
```

3. §9 changelog — add:

```markdown
| v1.29 | 2026-07-02 | Phase 5 Increment 3 migrations `0069`+`0070`: installed-package lifecycle columns (pin, update_policy manual|notify, staged update pointer, settings/export/retention/quarantine state, `state`+`'uninstalled'`, history events `update_staged`/`export`/`purge`, permission kinds `api_scope`/`event`) and the P5-07-A review-enforcement tables (`publisher_signing_keys` inert-for-Inc-5, `package_review_decisions`, `package_transparency_log`). |
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/0069_phase5_package_lifecycle.php database/migrations/0070_phase5_publisher_review_security.php tests/Integration/Core/AppPhase5PackageLifecycleSchemaTest.php SCHEMA.md docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "feat(phase5): migrations 0069+0070 - package lifecycle columns + review/security tables, SS re-baseline (Inc 3)"
```

---

### Task 5: Thin repositories over the lifecycle tables

Five new `final` repositories, constructor `(private Database $db)`, prepared statements, assoc arrays. The transparency repository is **append-only by construction** (a reflection test pins its public surface).

**Files:**
- Create: `src/Repository/InstalledPackageRepository.php`
- Create: `src/Repository/InstalledPackagePermissionRepository.php`
- Create: `src/Repository/PackageHistoryRepository.php`
- Create: `src/Repository/PackageReviewDecisionRepository.php`
- Create: `src/Repository/PackageTransparencyLogRepository.php`
- Test: `tests/Integration/Repository/PackageLifecycleRepositoriesTest.php`

**Interfaces:** exactly the "Locked interfaces" block. Notes that matter to later tasks:
- `activeWithContext()` returns every non-`uninstalled` install joined with `packages` (`package_uid`, `package_name`, `package_type`, `package_advisory_status`) and the active release (`release_version`, `release_advisory_status`) — the health/enforcement working set.
- `purgeable($nowUtc)` returns `state='uninstalled' AND retain_until <= :now` rows joined with `packages.package_uid`.
- `verifiedDigestsFor(packageId)` = distinct non-null `new_digest` ∪ `prior_digest` over history events `install|update|rollback` (two different placeholder names — never reuse one).
- `replaceDeclared` writes `declared=1, granted=0`; `replaceWithGrants` rebuilds the set with a per-row `granted` flag (granted rows get `granted_at=UTC_TIMESTAMP(), granted_by`; ungranted rows get NULLs) — Task 10 uses it to inherit grants across updates without ever inventing one; `grantAll` flips only `declared=1 AND granted=0` rows and returns the count.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageLifecycleRepositoriesTest extends TestCase
{
    private array $seeded;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
    }

    private function makeInstall(): int
    {
        return (new InstalledPackageRepository($this->db))->create([
            'package_id' => $this->seeded['package_id'],
            'release_id' => $this->seeded['release_id'],
            'digest' => $this->seeded['release_digest'],
            'source_registry_id' => $this->seeded['registry_id'],
            'publisher_id' => $this->seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => $this->makeAdmin()['id'],
        ]);
    }

    public function test_install_row_lifecycle_round_trip(): void
    {
        $repo = new InstalledPackageRepository($this->db);
        $id = $this->makeInstall();

        $row = $repo->find($id);
        self::assertSame('installed', $row['state']);
        self::assertSame(0, (int) $row['pinned']);
        self::assertSame('manual', $row['update_policy']);
        self::assertSame($row['id'], $repo->findByPackage($this->seeded['package_id'])['id']);

        $repo->setState($id, 'enabled');
        $repo->setPinned($id, true);
        $repo->setUpdatePolicy($id, 'notify');
        $repo->stageRelease($id, $this->seeded['release_id'], $this->seeded['release_digest']);
        $row = $repo->find($id);
        self::assertSame(['enabled', 1, 'notify'], [$row['state'], (int) $row['pinned'], $row['update_policy']]);
        self::assertSame($this->seeded['release_digest'], $row['staged_digest']);
        $repo->stageRelease($id, null, null);
        self::assertNull($repo->find($id)['staged_release_id']);

        $repo->setHealth($id, 'failed', 'digest mismatch');
        $row = $repo->find($id);
        self::assertSame('failed', $row['health']);
        self::assertSame('digest mismatch', $row['quarantine_reason']);
        self::assertNotNull($row['last_health_check_at']);

        $context = $repo->activeWithContext();
        self::assertCount(1, $context);
        self::assertSame('acme/midnight-theme', $context[0]['package_uid']);

        $repo->storeExport($id, '{"format":"rb-install-export.v1"}');
        $repo->markUninstalled($id, '2026-01-01 00:00:00');
        $row = $repo->find($id);
        self::assertSame('uninstalled', $row['state']);
        self::assertNotNull($row['uninstalled_at']);
        self::assertSame([], $repo->activeWithContext());
        self::assertCount(1, $repo->purgeable('2026-06-01 00:00:00'));
        self::assertSame([], $repo->purgeable('2025-06-01 00:00:00'));

        $repo->reviveForInstall($id, [
            'release_id' => $this->seeded['release_id'],
            'digest' => $this->seeded['release_digest'],
            'source_registry_id' => $this->seeded['registry_id'],
            'publisher_id' => $this->seeded['publisher_id'],
            'trust_class' => 'reviewed_declarative',
            'review_status' => 'approved',
            'compat_min' => '0.1.0',
            'compat_max' => null,
            'installed_by' => null,
        ]);
        $row = $repo->find($id);
        self::assertSame('installed', $row['state']);
        self::assertNull($row['export_json']);
        self::assertNull($row['retain_until']);
        self::assertSame(0, (int) $row['pinned']);

        $repo->delete($id);
        self::assertNull($repo->find($id));
    }

    public function test_permission_snapshot_grant_flow(): void
    {
        $perms = new InstalledPackagePermissionRepository($this->db);
        $id = $this->makeInstall();
        $admin = $this->makeAdmin();

        $declared = [
            ['kind' => 'data_class', 'key' => 'package.own_storage', 'risk' => 'low'],
            ['kind' => 'api_scope', 'key' => 'read:boards', 'risk' => 'medium'],
        ];
        $perms->replaceDeclared($id, $declared);
        self::assertSame(2, $perms->ungrantedCount($id));
        $rows = $perms->forInstall($id);
        self::assertCount(2, $rows);
        self::assertSame(0, (int) $rows[0]['granted']);

        self::assertSame(2, $perms->grantAll($id, (int) $admin['id']));
        self::assertSame(0, $perms->ungrantedCount($id));
        self::assertSame(0, $perms->grantAll($id, (int) $admin['id']), 'idempotent');

        $perms->replaceWithGrants($id, [
            ['kind' => 'data_class', 'key' => 'package.own_storage', 'risk' => 'low', 'granted' => true],
            ['kind' => 'outbound_host', 'key' => 'api.example.com', 'risk' => 'medium', 'granted' => false],
        ], (int) $admin['id']);
        $byKey = [];
        foreach ($perms->forInstall($id) as $row) {
            $byKey[$row['permission_key']] = $row;
        }
        self::assertCount(2, $byKey);
        self::assertSame(1, (int) $byKey['package.own_storage']['granted']);
        self::assertNotNull($byKey['package.own_storage']['granted_at']);
        self::assertSame(0, (int) $byKey['api.example.com']['granted']);
        self::assertNull($byKey['api.example.com']['granted_at']);

        $perms->deleteFor($id);
        self::assertSame([], $perms->forInstall($id));
    }

    public function test_history_records_and_verified_digest_set(): void
    {
        $history = new PackageHistoryRepository($this->db);
        $installId = $this->makeInstall();
        $packageId = (int) $this->seeded['package_id'];

        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'install',
            'new_version' => '1.0.0', 'new_digest' => str_repeat('a', 64),
            'permission_snapshot_json' => '[]',
        ]);
        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'update',
            'prior_version' => '1.0.0', 'prior_digest' => str_repeat('a', 64),
            'new_version' => '1.1.0', 'new_digest' => str_repeat('b', 64),
        ]);
        $history->record([
            'package_id' => $packageId, 'installed_package_id' => $installId, 'event' => 'health',
            'failure_stage' => 'digest', 'detail' => 'tamper detected',
        ]);

        self::assertCount(3, $history->forPackage($packageId));
        self::assertSame('health', $history->forInstall($installId, 1)[0]['event']);
        $digests = $history->verifiedDigestsFor($packageId);
        sort($digests);
        self::assertSame([str_repeat('a', 64), str_repeat('b', 64)], $digests);
    }

    public function test_review_decisions_and_transparency_log(): void
    {
        $decisions = new PackageReviewDecisionRepository($this->db);
        $log = new PackageTransparencyLogRepository($this->db);
        $packageId = (int) $this->seeded['package_id'];

        $decisions->record([
            'package_id' => $packageId, 'release_id' => $this->seeded['release_id'], 'version' => '1.0.0',
            'digest' => $this->seeded['release_digest'], 'decision' => 'approved',
            'decided_at' => '2026-07-01 00:00:00', 'source' => 'release_document', 'evidence_json' => '{}',
        ]);
        $latest = $decisions->latestForDigest($packageId, $this->seeded['release_digest']);
        self::assertSame('approved', $latest['decision']);
        self::assertNull($decisions->latestForDigest($packageId, str_repeat('f', 64)));
        self::assertCount(1, $decisions->forPackage($packageId));

        $log->record([
            'package_uid' => 'acme/midnight-theme', 'version' => '1.0.0',
            'digest' => $this->seeded['release_digest'], 'event' => 'install', 'source' => 'local',
            'actor_id' => null, 'registry_id' => $this->seeded['registry_id'], 'detail' => 'installed 1.0.0',
        ]);
        self::assertCount(1, $log->forPackageUid('acme/midnight-theme'));
        self::assertCount(1, $log->all());
    }

    public function test_transparency_log_repository_is_append_only(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(PackageTransparencyLogRepository::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($methods);
        self::assertSame(['__construct', 'all', 'forPackageUid', 'record'], $methods);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Repository/PackageLifecycleRepositoriesTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement the five repositories**

`src/Repository/InstalledPackageRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Local install state over `installed_packages` (0049 + 0069). One row per package (UNIQUE package_id). */
final class InstalledPackageRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        $row = $this->db->pdo()->prepare('SELECT * FROM installed_packages WHERE id = :id');
        $row->execute(['id' => $id]);
        return $row->fetch() ?: null;
    }

    public function findByPackage(int $packageId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM installed_packages WHERE package_id = :pid');
        $stmt->execute(['pid' => $packageId]);
        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->db->pdo()->query(
            'SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type
             FROM installed_packages ip JOIN packages p ON p.id = ip.package_id
             ORDER BY p.package_uid'
        )->fetchAll();
    }

    /** Every non-uninstalled install with its package + active-release context (health/enforcement working set). */
    public function activeWithContext(): array
    {
        return $this->db->pdo()->query(
            "SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type,
                    p.advisory_status AS package_advisory_status,
                    r.version AS release_version, r.advisory_status AS release_advisory_status
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             LEFT JOIN package_releases r ON r.id = ip.release_id
             WHERE ip.state <> 'uninstalled'
             ORDER BY ip.id"
        )->fetchAll();
    }

    public function create(array $row): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO installed_packages
                (package_id, release_id, digest, source_registry_id, publisher_id, trust_class,
                 review_status, state, health, compat_min, compat_max, installed_by)
             VALUES (:package_id, :release_id, :digest, :source_registry_id, :publisher_id, :trust_class,
                     :review_status, \'installed\', \'unknown\', :compat_min, :compat_max, :installed_by)'
        );
        $stmt->execute([
            'package_id' => $row['package_id'], 'release_id' => $row['release_id'], 'digest' => $row['digest'],
            'source_registry_id' => $row['source_registry_id'], 'publisher_id' => $row['publisher_id'],
            'trust_class' => $row['trust_class'], 'review_status' => $row['review_status'],
            'compat_min' => $row['compat_min'], 'compat_max' => $row['compat_max'],
            'installed_by' => $row['installed_by'],
        ]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    /** Reinstall over an uninstalled row: reset every lifecycle field, keep the row identity. */
    public function reviveForInstall(int $id, array $row): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE installed_packages SET
                release_id = :release_id, digest = :digest, source_registry_id = :source_registry_id,
                publisher_id = :publisher_id, trust_class = :trust_class, review_status = :review_status,
                state = \'installed\', health = \'unknown\', pinned = 0, update_policy = \'manual\',
                staged_release_id = NULL, staged_digest = NULL, settings_json = NULL, export_json = NULL,
                exported_at = NULL, retain_until = NULL, uninstalled_at = NULL, quarantine_reason = NULL,
                last_health_check_at = NULL, compat_min = :compat_min, compat_max = :compat_max,
                installed_by = :installed_by, installed_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $stmt->execute([
            'release_id' => $row['release_id'], 'digest' => $row['digest'],
            'source_registry_id' => $row['source_registry_id'], 'publisher_id' => $row['publisher_id'],
            'trust_class' => $row['trust_class'], 'review_status' => $row['review_status'],
            'compat_min' => $row['compat_min'], 'compat_max' => $row['compat_max'],
            'installed_by' => $row['installed_by'], 'id' => $id,
        ]);
    }

    public function setState(int $id, string $state): void
    {
        $this->db->pdo()->prepare('UPDATE installed_packages SET state = :state WHERE id = :id')
            ->execute(['state' => $state, 'id' => $id]);
    }

    public function setHealth(int $id, string $health, ?string $quarantineReason, ?string $checkedAtUtc = null): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE installed_packages
             SET health = :health, quarantine_reason = :reason,
                 last_health_check_at = COALESCE(:checked, UTC_TIMESTAMP())
             WHERE id = :id'
        );
        $stmt->execute(['health' => $health, 'reason' => $quarantineReason, 'checked' => $checkedAtUtc, 'id' => $id]);
    }

    public function activateRelease(int $id, int $releaseId, string $digest, ?string $compatMin, ?string $compatMax, string $reviewStatus): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE installed_packages
             SET release_id = :rid, digest = :digest, compat_min = :cmin, compat_max = :cmax,
                 review_status = :review, staged_release_id = NULL, staged_digest = NULL
             WHERE id = :id'
        );
        $stmt->execute(['rid' => $releaseId, 'digest' => $digest, 'cmin' => $compatMin, 'cmax' => $compatMax, 'review' => $reviewStatus, 'id' => $id]);
    }

    public function stageRelease(int $id, ?int $releaseId, ?string $digest): void
    {
        $this->db->pdo()->prepare('UPDATE installed_packages SET staged_release_id = :rid, staged_digest = :digest WHERE id = :id')
            ->execute(['rid' => $releaseId, 'digest' => $digest, 'id' => $id]);
    }

    public function setPinned(int $id, bool $pinned): void
    {
        $this->db->pdo()->prepare('UPDATE installed_packages SET pinned = :pinned WHERE id = :id')
            ->execute(['pinned' => $pinned ? 1 : 0, 'id' => $id]);
    }

    public function setUpdatePolicy(int $id, string $policy): void
    {
        $this->db->pdo()->prepare('UPDATE installed_packages SET update_policy = :policy WHERE id = :id')
            ->execute(['policy' => $policy, 'id' => $id]);
    }

    public function markUninstalled(int $id, string $retainUntilUtc): void
    {
        $this->db->pdo()->prepare(
            'UPDATE installed_packages
             SET state = \'uninstalled\', uninstalled_at = UTC_TIMESTAMP(), retain_until = :ru,
                 staged_release_id = NULL, staged_digest = NULL
             WHERE id = :id'
        )->execute(['ru' => $retainUntilUtc, 'id' => $id]);
    }

    public function storeExport(int $id, string $exportJson): void
    {
        $this->db->pdo()->prepare('UPDATE installed_packages SET export_json = :ej, exported_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['ej' => $exportJson, 'id' => $id]);
    }

    /** Uninstalled rows whose retention window has lapsed (worker purge set). */
    public function purgeable(string $nowUtc): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT ip.*, p.package_uid FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             WHERE ip.state = 'uninstalled' AND ip.retain_until IS NOT NULL AND ip.retain_until <= :now"
        );
        $stmt->execute(['now' => $nowUtc]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $this->db->pdo()->prepare('DELETE FROM installed_packages WHERE id = :id')->execute(['id' => $id]);
    }
}
```

`src/Repository/InstalledPackagePermissionRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Permission snapshot rows over `installed_package_permissions` (0049 + 0069).
 * declared = the manifest ceiling; granted = the actual local authority
 * (granted=0 until consent — §8.5, decision #7).
 */
final class InstalledPackagePermissionRepository
{
    public function __construct(private Database $db)
    {
    }

    public function forInstall(int $installedId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM installed_package_permissions WHERE installed_package_id = :id ORDER BY kind, permission_key'
        );
        $stmt->execute(['id' => $installedId]);
        return $stmt->fetchAll();
    }

    /** @param list<array{kind:string,key:string,risk:string}> $permissions */
    public function replaceDeclared(int $installedId, array $permissions): void
    {
        $this->deleteFor($installedId);
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted)
             VALUES (:id, :kind, :key, :risk, 1, 0)'
        );
        foreach ($permissions as $p) {
            $stmt->execute(['id' => $installedId, 'kind' => $p['kind'], 'key' => $p['key'], 'risk' => $p['risk']]);
        }
    }

    /**
     * Rebuild the snapshot with a per-row granted flag (update activation:
     * unchanged keys inherit their grant, new keys arrive granted only through
     * an explicit staged approval — a grant is never invented).
     * @param list<array{kind:string,key:string,risk:string,granted:bool}> $permissions
     */
    public function replaceWithGrants(int $installedId, array $permissions, int $grantedBy): void
    {
        $this->deleteFor($installedId);
        $granted = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted, granted_at, granted_by)
             VALUES (:id, :kind, :key, :risk, 1, 1, UTC_TIMESTAMP(), :by)'
        );
        $ungranted = $this->db->pdo()->prepare(
            'INSERT INTO installed_package_permissions
                (installed_package_id, kind, permission_key, risk_class, declared, granted)
             VALUES (:id, :kind, :key, :risk, 1, 0)'
        );
        foreach ($permissions as $p) {
            if (!empty($p['granted'])) {
                $granted->execute(['id' => $installedId, 'kind' => $p['kind'], 'key' => $p['key'], 'risk' => $p['risk'], 'by' => $grantedBy]);
            } else {
                $ungranted->execute(['id' => $installedId, 'kind' => $p['kind'], 'key' => $p['key'], 'risk' => $p['risk']]);
            }
        }
    }

    public function grantAll(int $installedId, int $grantedBy): int
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE installed_package_permissions
             SET granted = 1, granted_at = UTC_TIMESTAMP(), granted_by = :by
             WHERE installed_package_id = :id AND declared = 1 AND granted = 0'
        );
        $stmt->execute(['by' => $grantedBy, 'id' => $installedId]);
        return $stmt->rowCount();
    }

    public function ungrantedCount(int $installedId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM installed_package_permissions
             WHERE installed_package_id = :id AND declared = 1 AND granted = 0'
        );
        $stmt->execute(['id' => $installedId]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteFor(int $installedId): void
    {
        $this->db->pdo()->prepare('DELETE FROM installed_package_permissions WHERE installed_package_id = :id')
            ->execute(['id' => $installedId]);
    }
}
```

`src/Repository/PackageHistoryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Append-only lifecycle history over `package_history` (0049 + 0069). Survives uninstall (no install FK). */
final class PackageHistoryRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(array $entry): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO package_history
                (package_id, installed_package_id, event, actor_id, prior_version, new_version,
                 prior_digest, new_digest, permission_snapshot_json, approval_ref, failure_stage, detail)
             VALUES (:package_id, :installed_package_id, :event, :actor_id, :prior_version, :new_version,
                     :prior_digest, :new_digest, :permission_snapshot_json, :approval_ref, :failure_stage, :detail)'
        );
        $stmt->execute([
            'package_id' => $entry['package_id'] ?? null,
            'installed_package_id' => $entry['installed_package_id'] ?? null,
            'event' => $entry['event'],
            'actor_id' => $entry['actor_id'] ?? null,
            'prior_version' => $entry['prior_version'] ?? null,
            'new_version' => $entry['new_version'] ?? null,
            'prior_digest' => $entry['prior_digest'] ?? null,
            'new_digest' => $entry['new_digest'] ?? null,
            'permission_snapshot_json' => $entry['permission_snapshot_json'] ?? null,
            'approval_ref' => $entry['approval_ref'] ?? null,
            'failure_stage' => $entry['failure_stage'] ?? null,
            'detail' => $entry['detail'] ?? null,
        ]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    public function forPackage(int $packageId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM package_history WHERE package_id = :pid ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute(['pid' => $packageId]);
        return $stmt->fetchAll();
    }

    public function forInstall(int $installedId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM package_history WHERE installed_package_id = :iid ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute(['iid' => $installedId]);
        return $stmt->fetchAll();
    }

    /**
     * Digests this installation previously verified and activated (rollback
     * candidates — §13.2 "previously verified compatible digest").
     * @return list<string>
     */
    public function verifiedDigestsFor(int $packageId): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT DISTINCT new_digest AS digest FROM package_history
              WHERE package_id = :p1 AND event IN ('install','update','rollback') AND new_digest IS NOT NULL
             UNION
             SELECT DISTINCT prior_digest AS digest FROM package_history
              WHERE package_id = :p2 AND event IN ('install','update','rollback') AND prior_digest IS NOT NULL"
        );
        $stmt->execute(['p1' => $packageId, 'p2' => $packageId]);
        return array_map(static fn (array $r): string => (string) $r['digest'], $stmt->fetchAll());
    }
}
```

`src/Repository/PackageReviewDecisionRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Append-only cache of the signed review evidence an install relied on (`package_review_decisions`, 0070; §8.2 #5). */
final class PackageReviewDecisionRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(array $entry): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO package_review_decisions
                (package_id, release_id, version, digest, decision, decided_at, source, evidence_json)
             VALUES (:package_id, :release_id, :version, :digest, :decision, :decided_at, :source, :evidence_json)'
        );
        $stmt->execute([
            'package_id' => $entry['package_id'],
            'release_id' => $entry['release_id'] ?? null,
            'version' => $entry['version'],
            'digest' => $entry['digest'],
            'decision' => $entry['decision'],
            'decided_at' => $entry['decided_at'] ?? null,
            'source' => $entry['source'] ?? 'release_document',
            'evidence_json' => $entry['evidence_json'] ?? null,
        ]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    public function latestForDigest(int $packageId, string $digest): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM package_review_decisions WHERE package_id = :pid AND digest = :digest ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['pid' => $packageId, 'digest' => $digest]);
        return $stmt->fetch() ?: null;
    }

    public function forPackage(int $packageId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM package_review_decisions WHERE package_id = :pid ORDER BY id DESC');
        $stmt->execute(['pid' => $packageId]);
        return $stmt->fetchAll();
    }
}
```

`src/Repository/PackageTransparencyLogRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Append-only publication/lifecycle history for released and revoked digests
 * (`package_transparency_log`, 0070). By design this repository exposes NO
 * update or delete method — a reflection regression pins that surface.
 */
final class PackageTransparencyLogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function record(array $entry): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO package_transparency_log
                (package_uid, version, digest, event, source, actor_id, registry_id, detail)
             VALUES (:package_uid, :version, :digest, :event, :source, :actor_id, :registry_id, :detail)'
        );
        $stmt->execute([
            'package_uid' => $entry['package_uid'],
            'version' => $entry['version'] ?? null,
            'digest' => $entry['digest'] ?? null,
            'event' => $entry['event'],
            'source' => $entry['source'],
            'actor_id' => $entry['actor_id'] ?? null,
            'registry_id' => $entry['registry_id'] ?? null,
            'detail' => $entry['detail'] ?? null,
        ]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    public function forPackageUid(string $packageUid, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM package_transparency_log WHERE package_uid = :uid ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute(['uid' => $packageUid]);
        return $stmt->fetchAll();
    }

    public function all(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        return $this->db->pdo()->query(
            'SELECT * FROM package_transparency_log ORDER BY id DESC LIMIT ' . $limit
        )->fetchAll();
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Repository/PackageLifecycleRepositoriesTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Repository/InstalledPackageRepository.php src/Repository/InstalledPackagePermissionRepository.php src/Repository/PackageHistoryRepository.php src/Repository/PackageReviewDecisionRepository.php src/Repository/PackageTransparencyLogRepository.php tests/Integration/Repository/PackageLifecycleRepositoriesTest.php
git commit -m "feat(phase5): thin repositories over the install/permission/history/review/transparency tables (Inc 3)"
```

---

### Task 6: `PackageArtifactStore` — content-addressed local artifact cache + config

Non-web-accessible, digest-named storage for verified release documents. Layout is locked to `{storage_path}/{digest}.json` (Task 1's fixtures already write it). Writing verifies bytes-against-digest; reading never trusts the filename.

**Files:**
- Create: `src/Service/Packages/PackageArtifactStore.php`
- Modify: `config/config.php` (add the `packages` block near the existing `registry` block at ~line 242)
- Modify: `tests/bootstrap.php` (redirect `packages.storage_path` to sys temp, next to the existing `uploads` override)
- Modify: `.gitignore` — ensure `storage/packages/` is ignored (skip if a `storage/*` pattern already covers it)
- Test: `tests/Unit/Service/Packages/PackageArtifactStoreTest.php`

**Interfaces:**
- Produces: the locked `PackageArtifactStore` API. `put()` refuses non-64-hex digests and byte/digest mismatches with `PackagePolicyException('artifact_digest', …)`; `verify()` returns false for missing files or hash mismatches (never throws); digest validation makes path traversal impossible.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\PackageArtifactStore;
use PHPUnit\Framework\TestCase;

final class PackageArtifactStoreTest extends TestCase
{
    private string $dir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rb-artifact-store-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_put_get_verify_remove_round_trip(): void
    {
        $bytes = '{"format":"rb-release.v1","uid":"acme/x"}';
        $digest = hash('sha256', $bytes);

        self::assertFalse($this->store->has($digest));
        $this->store->put($digest, $bytes);
        self::assertTrue($this->store->has($digest));
        self::assertSame($bytes, $this->store->get($digest));
        self::assertTrue($this->store->verify($digest));
        self::assertSame(rtrim($this->dir, '/') . '/' . $digest . '.json', $this->store->pathFor($digest));

        $this->store->remove($digest);
        self::assertFalse($this->store->has($digest));
        self::assertNull($this->store->get($digest));
        self::assertFalse($this->store->verify($digest));
    }

    public function test_put_refuses_bytes_that_do_not_match_the_digest(): void
    {
        try {
            $this->store->put(hash('sha256', 'expected'), 'actual');
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
    }

    public function test_malformed_digest_refuses_before_touching_the_filesystem(): void
    {
        foreach (['../../etc/passwd', strtoupper(str_repeat('a', 64)), 'short', str_repeat('a', 63) . '/'] as $bad) {
            try {
                $this->store->pathFor($bad);
                self::fail('expected artifact_digest refusal for ' . $bad);
            } catch (PackagePolicyException $e) {
                self::assertSame('artifact_digest', $e->code);
            }
        }
    }

    public function test_verify_detects_on_disk_tampering(): void
    {
        $bytes = '{"format":"rb-release.v1"}';
        $digest = hash('sha256', $bytes);
        $this->store->put($digest, $bytes);

        file_put_contents($this->store->pathFor($digest), $bytes . ' ');
        self::assertFalse($this->store->verify($digest), 'a flipped/extra byte must fail verification');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/Service/Packages/PackageArtifactStoreTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the store + config**

`src/Service/Packages/PackageArtifactStore.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Security\Packages\PackagePolicyException;

/**
 * Content-addressed local cache of verified release documents (P5-02): the
 * exact signed rb-release.v1 bytes live at {storage_path}/{digest}.json,
 * outside the web root. put() verifies bytes-against-digest; verify() is the
 * tamper check worker:packages runs (TM-SC-07). The 64-hex digest check makes
 * path traversal structurally impossible.
 */
final class PackageArtifactStore
{
    public function __construct(private string $storagePath)
    {
    }

    public function pathFor(string $digest): string
    {
        $this->assertDigest($digest);
        return rtrim($this->storagePath, '/') . '/' . $digest . '.json';
    }

    public function put(string $digest, string $bytes): void
    {
        $this->assertDigest($digest);
        if (!hash_equals($digest, hash('sha256', $bytes))) {
            throw new PackagePolicyException('artifact_digest', 'Artifact bytes do not match the pinned digest.');
        }
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)) {
            throw new \RuntimeException('Cannot create the package artifact directory.');
        }
        $path = $this->pathFor($digest);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $bytes) !== strlen($bytes) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to persist the package artifact.');
        }
    }

    public function has(string $digest): bool
    {
        return is_file($this->pathFor($digest));
    }

    public function get(string $digest): ?string
    {
        $path = $this->pathFor($digest);
        if (!is_file($path)) {
            return null;
        }
        $bytes = file_get_contents($path);
        return $bytes === false ? null : $bytes;
    }

    /** True only when the cached bytes re-hash to the pinned digest. */
    public function verify(string $digest): bool
    {
        $bytes = $this->get($digest);
        return $bytes !== null && hash_equals($digest, hash('sha256', $bytes));
    }

    public function remove(string $digest): void
    {
        $path = $this->pathFor($digest);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function assertDigest(string $digest): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
            throw new PackagePolicyException('artifact_digest', 'Artifact digests must be 64 lowercase hex characters.');
        }
    }
}
```

`config/config.php` — add directly after the existing `'registry' => [...]` block:

```php
'packages' => [
    // Verified release documents (content-addressed by sha256). Never web-served.
    'storage_path' => Env::get('PACKAGES_STORAGE_PATH', dirname(__DIR__) . '/storage/packages'),
    // Default uninstall retention window (days) when the manifest declares none.
    'retention_days' => (int) Env::get('PACKAGES_RETENTION_DAYS', '30'),
],
```

`tests/bootstrap.php` — inside the existing `array_replace_recursive` override block, next to the `uploads` line:

```php
// Package artifacts go to a throwaway dir, never the real storage root.
'packages' => ['storage_path' => sys_get_temp_dir() . '/rb-test-packages'],
```

`.gitignore` — if `storage/packages/` is not already covered by an existing `storage/` pattern, add `storage/packages/`.

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/Service/Packages/PackageArtifactStoreTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Service/Packages/PackageArtifactStore.php config/config.php tests/bootstrap.php tests/Unit/Service/Packages/PackageArtifactStoreTest.php .gitignore
git commit -m "feat(phase5): content-addressed package artifact store + packages config (Inc 3)"
```

---
### Task 7: `PackageSecurityGate` — fail-closed install/enable policy (P5-07-A part 1)

The enforcement primitive that resolves the P5-02↔P5-07-A reverse dependency: install and (re-)enable fail closed on locally-blocked digests/packages, blocking advisories, non-approved review, and Gate-A-forbidden types/trust classes. TM-SC-06's gate half lives here.

**Files:**
- Create: `src/Security/Packages/PackageSecurityGate.php`
- Test: `tests/Integration/Security/PackageSecurityGateTest.php`

**Interfaces:**
- Consumes: `LocalPackageBlockRepository::isBlocked(?string $digest, ?string $packageUid)`, `PackageAdvisoryRepository::forPackage(int)`, `RegistryAdvisoryService::affectsVersion(?string, string)` (static).
- Produces: `assertInstallable(array $package, array $release): void` and `assertEnableable(array $package, array $release): void`. **Semantic split (locked):** advisory action `block_new` blocks **install/update targets only**; `force_disable` and `revoke` block install **and** enable. Both methods also refuse `type_forbidden` (anything but `theme|automation|remote_app` from a registry), `trust_class_forbidden` (anything but `reviewed_declarative|reviewed_remote`), `locally_blocked`, and `review_not_approved`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageSecurityGateTest extends TestCase
{
    private array $seeded;
    private PackageSecurityGate $gate;
    private array $package;
    private array $release;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
        $this->gate = new PackageSecurityGate(
            new LocalPackageBlockRepository($this->db),
            new PackageAdvisoryRepository($this->db),
        );
        $this->package = (new PackageRepository($this->db))->find($this->seeded['package_id']);
        $this->release = (new PackageReleaseRepository($this->db))->find($this->seeded['release_id']);
    }

    private function assertGateRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected gate refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    private function seedAdvisory(string $action, ?string $digest = null, ?string $range = null): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-TEST-' . strtoupper($action),
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => $range,
            'affected_digest' => $digest,
            'severity' => 'high',
            'action' => $action,
            'summary' => 'test advisory',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);
    }

    public function test_approved_reviewed_release_passes_both_gates(): void
    {
        $this->gate->assertInstallable($this->package, $this->release);
        $this->gate->assertEnableable($this->package, $this->release);
        self::assertTrue(true); // no refusal thrown
    }

    public function test_locally_blocked_digest_and_uid_refuse_install_and_enable(): void
    {
        $blocks = new LocalPackageBlockRepository($this->db);
        $blockId = $blocks->add($this->release['digest'], null, 'incident', null);
        $this->assertGateRefusal('locally_blocked', fn () => $this->gate->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('locally_blocked', fn () => $this->gate->assertEnableable($this->package, $this->release));
        $blocks->remove($blockId);

        $blocks->add(null, 'acme/midnight-theme', 'incident', null);
        $this->assertGateRefusal('locally_blocked', fn () => $this->gate->assertInstallable($this->package, $this->release));
    }

    public function test_block_new_advisory_blocks_install_but_not_enable(): void
    {
        $this->seedAdvisory('block_new', null, '<=1.0.0');
        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate->assertInstallable($this->package, $this->release));
        $this->gate->assertEnableable($this->package, $this->release); // existing installs keep their emergency policy
        self::assertTrue(true);
    }

    public function test_force_disable_and_revoke_advisories_block_both(): void
    {
        $this->seedAdvisory('force_disable', null, '<=1.0.0');
        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate->assertEnableable($this->package, $this->release));
    }

    public function test_revoked_digest_refuses_install_and_enable_with_revoked_code(): void
    {
        // TM-SC-06 (gate half): a revoked digest cannot be newly installed or enabled.
        $this->seedAdvisory('revoke', $this->release['digest'], null);
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate->assertEnableable($this->package, $this->release));
    }

    public function test_advisory_with_no_digest_and_no_range_affects_every_version(): void
    {
        $this->seedAdvisory('revoke', null, null);
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate->assertInstallable($this->package, $this->release));
    }

    public function test_non_matching_advisory_does_not_block(): void
    {
        $this->seedAdvisory('revoke', null, '<=0.9.0');
        $this->gate->assertInstallable($this->package, $this->release);
        self::assertTrue(true);
    }

    public function test_unapproved_review_refuses(): void
    {
        foreach (['unreviewed', 'submitted', 'rejected', 'revoked'] as $status) {
            $this->assertGateRefusal('review_not_approved',
                fn () => $this->gate->assertInstallable($this->package, ['review_status' => $status] + $this->release));
        }
    }

    public function test_gate_a_type_and_trust_class_policy(): void
    {
        $this->assertGateRefusal('type_forbidden',
            fn () => $this->gate->assertInstallable(['type' => 'server_extension'] + $this->package, $this->release));
        $this->assertGateRefusal('type_forbidden',
            fn () => $this->gate->assertInstallable(['type' => 'local'] + $this->package, $this->release));
        $this->assertGateRefusal('trust_class_forbidden',
            fn () => $this->gate->assertInstallable(['trust_class' => 'local_dev'] + $this->package, $this->release));
        $this->assertGateRefusal('trust_class_forbidden',
            fn () => $this->gate->assertInstallable(['trust_class' => 'first_party'] + $this->package, $this->release));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Security/PackageSecurityGateTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`src/Security/Packages/PackageSecurityGate.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Service\Registry\RegistryAdvisoryService;

/**
 * The P5-07-A part 1 enforcement primitive: install/enable fail closed on
 * revoked or blocking advisories, locally blocked digests/packages,
 * non-approved review, and Gate-A-forbidden types/trust classes (decisions
 * #16/#17, §9 Revoked-release + Review-digest, TM-SC-06).
 *
 * Advisory semantics: the folded advisory_status column cannot distinguish
 * block_new from force_disable (both store 'blocked'), so the gate re-derives
 * matches from the cached advisory rows' ACTIONS: block_new blocks new
 * installs/update targets only; force_disable and revoke also block enable.
 */
final class PackageSecurityGate
{
    private const INSTALLABLE_TYPES = ['theme', 'automation', 'remote_app'];
    private const INSTALLABLE_TRUST = ['reviewed_declarative', 'reviewed_remote'];

    public function __construct(
        private LocalPackageBlockRepository $blocks,
        private PackageAdvisoryRepository $advisories,
    ) {
    }

    public function assertInstallable(array $package, array $release): void
    {
        $this->assertTypePolicy($package);
        $this->assertNotBlocked($package, $release);
        $this->assertAdvisoriesAllow($package, $release, ['block_new', 'force_disable', 'revoke']);
        $this->assertReviewApproved($release);
    }

    public function assertEnableable(array $package, array $release): void
    {
        $this->assertTypePolicy($package);
        $this->assertNotBlocked($package, $release);
        $this->assertAdvisoriesAllow($package, $release, ['force_disable', 'revoke']);
        $this->assertReviewApproved($release);
    }

    private function assertTypePolicy(array $package): void
    {
        $type = (string) ($package['type'] ?? '');
        if (!in_array($type, self::INSTALLABLE_TYPES, true)) {
            throw new PackagePolicyException('type_forbidden',
                'Package type "' . $type . '" cannot be installed from a registry in Gate A.');
        }
        $trust = (string) ($package['trust_class'] ?? '');
        if (!in_array($trust, self::INSTALLABLE_TRUST, true)) {
            throw new PackagePolicyException('trust_class_forbidden',
                'Trust class "' . $trust . '" cannot be installed from a registry in Gate A.');
        }
    }

    private function assertNotBlocked(array $package, array $release): void
    {
        if ($this->blocks->isBlocked((string) $release['digest'], (string) $package['package_uid'])) {
            throw new PackagePolicyException('locally_blocked',
                'This digest or package is on the local blocklist (registry-independent).');
        }
    }

    /** @param list<string> $blockingActions */
    private function assertAdvisoriesAllow(array $package, array $release, array $blockingActions): void
    {
        foreach ($this->advisories->forPackage((int) $package['id']) as $advisory) {
            $action = (string) $advisory['action'];
            if (!in_array($action, $blockingActions, true)) {
                continue;
            }
            if (!$this->advisoryMatches($advisory, $release)) {
                continue;
            }
            throw new PackagePolicyException(
                $action === 'revoke' ? 'advisory_revoked' : 'advisory_blocked',
                'Advisory ' . (string) $advisory['advisory_uid'] . ' (' . $action . ') applies to this release.'
            );
        }
    }

    private function advisoryMatches(array $advisory, array $release): bool
    {
        if ($advisory['affected_digest'] !== null && $advisory['affected_digest'] !== '') {
            return hash_equals((string) $advisory['affected_digest'], (string) $release['digest']);
        }
        $range = $advisory['affected_version_range'];
        if ($range === null || $range === '') {
            return true; // no digest, no range: the advisory covers the whole package (fail toward affected)
        }
        return RegistryAdvisoryService::affectsVersion((string) $range, (string) $release['version']);
    }

    private function assertReviewApproved(array $release): void
    {
        $status = (string) ($release['review_status'] ?? '');
        if ($status !== 'approved') {
            throw new PackagePolicyException('review_not_approved',
                'Only releases with a verified approved review may install or enable (current: ' . ($status === '' ? 'unknown' : $status) . ').');
        }
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Security/PackageSecurityGateTest.php`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/Packages/PackageSecurityGate.php tests/Integration/Security/PackageSecurityGateTest.php
git commit -m "feat(phase5): PackageSecurityGate - fail-closed install/enable policy over blocklist, advisories, review, type (Inc 3, P5-07-A part 1)"
```

---

### Task 8: `PackageAcquisitionService` — fetch, verify, hydrate, and cache the signed release document

The verification heart of the install path (TM-SC-09): given a release row whose digest the Inc 2 snapshot pinned, produce a validated `PackageManifest` from locally cached bytes — fetching through the SSRF-guarded transport only when the cache is cold, re-verifying **every** time (cached bytes are never trusted), persisting the hydrated metadata + the relied-upon review evidence (§8.2 #5) + a `release_verified` transparency row exactly once.

**Files:**
- Create: `src/Service/Packages/PackageAcquisitionService.php`
- Modify: `src/Repository/PackageReleaseRepository.php` (add `hydrateSignedMetadata`)
- Test: `tests/Integration/Service/PackageAcquisitionServiceTest.php`

**Interfaces:**
- Consumes: `TrustChainVerifier::verify(...)` (format `rb-release.v1`), `RegistryTrustKeyRepository::forRegistry`, `ManifestValidator::validate`, `PackageArtifactStore`, `RegistryTransport::fetch`, `PackageReviewDecisionRepository`, `PackageTransparencyLogRepository`.
- Produces: `ensureVerified(array $registry, array $package, array $release, ?\DateTimeImmutable $now = null): PackageManifest`. Verification order (locked): **cache/fetch → digest pin → signature/format → identity → review block → manifest**. Adds `PackageReleaseRepository::hydrateSignedMetadata(int $id, string $manifestJson, string $signature, string $keyId, string $reviewStatus): void`.
- Refusals: `source_mismatch` (fetch URL not same-origin with the registry), `fetch_failed`, `artifact_digest` (bytes ≠ snapshot-pinned digest — TM-SC-09), `release_identity` (payload uid/version ≠ the release row), `release_review` (malformed/unknown review block); trust-chain failures propagate as `RegistryVerificationException` (`unknown_key`, `bad_signature`, …); manifest failures propagate from Task 3.

- [ ] **Step 1: Add the repository method (small, direct)**

Append to `src/Repository/PackageReleaseRepository.php`:

```php
/** Inc 3: persist the verified signed release metadata (acquisition hydration). */
public function hydrateSignedMetadata(int $id, string $manifestJson, string $signature, string $keyId, string $reviewStatus): void
{
    $stmt = $this->db->pdo()->prepare(
        'UPDATE package_releases
         SET manifest_json = :mj, signature = :sig, signed_key_id = :kid, review_status = :review
         WHERE id = :id'
    );
    $stmt->execute(['mj' => $manifestJson, 'sig' => $signature, 'kid' => $keyId, 'review' => $reviewStatus, 'id' => $id]);
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\Registry\RegistryFetchResult;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageAcquisitionServiceTest extends TestCase
{
    private SigningHarness $root;
    private array $seeded;
    private string $artifactDir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-acquire-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->artifactDir . '/*') ?: []);
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    private function service(array $responses = []): PackageAcquisitionService
    {
        return new PackageAcquisitionService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            $this->store,
            new ManifestValidator(),
            new ArrayRegistryTransport($responses),
        );
    }

    /** @return array{0:array,1:array,2:array} registry, package, release rows */
    private function rows(?int $releaseId = null): array
    {
        return [
            (new PackageRegistryRepository($this->db))->find($this->seeded['registry_id']),
            (new PackageRepository($this->db))->find($this->seeded['package_id']),
            (new PackageReleaseRepository($this->db))->find($releaseId ?? $this->seeded['release_id']),
        ];
    }

    /** Insert an UNHYDRATED release row whose digest pins the given document (the post-snapshot state). */
    private function seedUnhydrated(array $minted): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO package_releases (package_id, version, digest, channel, advisory_status)
             VALUES (:pid, :version, :digest, \'stable\', \'none\')'
        );
        $stmt->execute(['pid' => $this->seeded['package_id'], 'version' => $minted['version'], 'digest' => $minted['digest']]);
        return (int) $this->db->pdo()->lastInsertId();
    }

    private function releaseUrl(string $version): string
    {
        return 'https://registry.invalid/releases/acme/midnight-theme/' . $version . '/rb-release-envelope.v1.json';
    }

    public function test_cold_cache_fetches_verifies_hydrates_and_records_evidence(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        $manifest = $this->service([
            $this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null),
        ])->ensureVerified($registry, $package, $release);

        self::assertSame('acme/midnight-theme', $manifest->uid);
        self::assertSame('2.0.0', $manifest->version);
        self::assertTrue($this->store->verify($envelope['release']['digest']), 'artifact cached content-addressed');

        $hydrated = (new PackageReleaseRepository($this->db))->find($releaseId);
        self::assertSame('approved', $hydrated['review_status']);
        self::assertSame($this->root->keyId(), $hydrated['signed_key_id']);
        self::assertNotEmpty($hydrated['manifest_json']);

        $decision = (new PackageReviewDecisionRepository($this->db))
            ->latestForDigest($this->seeded['package_id'], $envelope['release']['digest']);
        self::assertSame('approved', $decision['decision']);
        self::assertSame('release_document', $decision['source']);
        self::assertNotEmpty($decision['evidence_json']);

        $log = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertSame('release_verified', $log[0]['event']);
    }

    public function test_warm_cache_never_touches_the_transport_and_reverifies(): void
    {
        // Hydrated fixture + cached artifact + an EMPTY transport: any fetch would 404 and fail.
        $this->store->put(hash('sha256', $this->seeded['release_document']), $this->seeded['release_document']);
        [$registry, $package, $release] = $this->rows();

        $manifest = $this->service([])->ensureVerified($registry, $package, $release);
        self::assertSame('1.0.0', $manifest->version);
    }

    public function test_review_digest_binding_a_different_document_cannot_satisfy_the_pinned_digest(): void
    {
        // TM-SC-09: approval travels inside the signed bytes; digest A's approval cannot bless digest B.
        $pinned = $this->root->mintRelease(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($pinned);
        [$registry, $package, $release] = $this->rows($releaseId);

        $other = $this->root->mintReleaseEnvelope(['version' => '2.0.0', 'manifest' => ['description' => 'different bytes']]);
        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $other['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
        self::assertNull(
            (new PackageReviewDecisionRepository($this->db))->latestForDigest($this->seeded['package_id'], $other['release']['digest']),
            'no review evidence may be cached for refused bytes'
        );
    }

    public function test_signature_from_an_unpinned_key_refuses_even_with_a_matching_digest(): void
    {
        $rogue = SigningHarness::generate('rogue-1');
        $envelope = $rogue->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']); // digest pins the rogue bytes
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected trust-chain refusal');
        } catch (RegistryVerificationException $e) {
            self::assertSame('unknown_key', $e->code);
        }
    }

    public function test_identity_mismatch_refuses(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['uid' => 'acme/other-package', 'version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated(['version' => '2.0.0', 'digest' => $envelope['release']['digest']]);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
                ->ensureVerified($registry, $package, $release);
            self::fail('expected release_identity refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('release_identity', $e->code);
        }
    }

    public function test_submitted_review_hydrates_without_recording_a_decision(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0', 'review_status' => 'submitted']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        $this->service([$this->releaseUrl('2.0.0') => new RegistryFetchResult(200, $envelope['body'], null)])
            ->ensureVerified($registry, $package, $release);

        self::assertSame('submitted', (new PackageReleaseRepository($this->db))->find($releaseId)['review_status']);
        self::assertNull((new PackageReviewDecisionRepository($this->db))
            ->latestForDigest($this->seeded['package_id'], $envelope['release']['digest']));
    }

    public function test_source_pinning_refuses_offsite_source_url(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        $this->db->pdo()->prepare('UPDATE package_releases SET source_url = :u WHERE id = :id')
            ->execute(['u' => 'https://evil.invalid/releases/x.json', 'id' => $releaseId]);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([])->ensureVerified($registry, $package, $release);
            self::fail('expected source_mismatch refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('source_mismatch', $e->code);
        }
    }

    public function test_transport_failure_is_a_coded_refusal(): void
    {
        $envelope = $this->root->mintReleaseEnvelope(['version' => '2.0.0']);
        $releaseId = $this->seedUnhydrated($envelope['release']);
        [$registry, $package, $release] = $this->rows($releaseId);

        try {
            $this->service([])->ensureVerified($registry, $package, $release); // ArrayTransport: unknown URL → 404
            self::fail('expected fetch_failed refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('fetch_failed', $e->code);
        }
    }

    public function test_tampered_cached_artifact_fails_closed_on_reverify(): void
    {
        $digest = hash('sha256', $this->seeded['release_document']);
        $this->store->put($digest, $this->seeded['release_document']);
        file_put_contents($this->store->pathFor($digest), $this->seeded['release_document'] . ' ');
        [$registry, $package, $release] = $this->rows();

        try {
            $this->service([])->ensureVerified($registry, $package, $release);
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
    }
}
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageAcquisitionServiceTest.php`
Expected: FAIL — service class not found.

- [ ] **Step 4: Implement `PackageAcquisitionService`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\RegistryTransport;

/**
 * Verified release acquisition (P5-02): ensure the signed rb-release.v1
 * document for a snapshot-pinned release is locally cached, verified, and its
 * manifest validated. The digest pin comes from the signed snapshot (Inc 2);
 * approval travels inside the signed bytes (decision #16, TM-SC-09).
 * Fetches happen only here (admin plan/install POST) — never on a core route
 * (decision #10) — and only same-origin with the registry (TM-SC-05).
 * Cached bytes are re-verified on every call; nothing is trusted stale.
 */
final class PackageAcquisitionService
{
    private const REVIEW_STATUSES = ['unreviewed', 'submitted', 'approved', 'rejected'];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private RegistryTrustKeyRepository $trustKeys,
        private PackageReleaseRepository $releases,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageTransparencyLogRepository $transparency,
        private PackageArtifactStore $artifacts,
        private ManifestValidator $manifests,
        private RegistryTransport $transport,
        private ?Telemetry $telemetry = null,
    ) {
    }

    public function ensureVerified(array $registry, array $package, array $release, ?\DateTimeImmutable $now = null): PackageManifest
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $digest = (string) $release['digest'];

        // 1. Bytes: local cache first; fetch only when cold.
        $document = $this->artifacts->get($digest);
        $signature = is_string($release['signature'] ?? null) && $release['signature'] !== '' ? (string) $release['signature'] : null;
        $keyId = is_string($release['signed_key_id'] ?? null) && $release['signed_key_id'] !== '' ? (string) $release['signed_key_id'] : null;
        $fetched = false;
        if ($document === null || $signature === null || $keyId === null) {
            [$document, $signature, $keyId] = $this->fetchEnvelope($registry, $package, $release);
            $fetched = true;
        }

        // 2. Digest pin (snapshot-signed) — cheapest check first, fail closed.
        if (!hash_equals($digest, hash('sha256', $document))) {
            throw new PackagePolicyException('artifact_digest',
                'Release document bytes do not hash to the snapshot-pinned digest.');
        }

        // 3. Signature + format over the exact bytes.
        $verified = $this->verifier->verify(
            $document, $signature, $keyId,
            $this->trustKeys->forRegistry((int) $registry['id']),
            'rb-release.v1', $now
        );
        $payload = $verified->payload;

        // 4. Identity: the signed payload must name this exact package + version.
        if (($payload['uid'] ?? null) !== (string) $package['package_uid']
            || ($payload['version'] ?? null) !== (string) $release['version']) {
            throw new PackagePolicyException('release_identity',
                'The signed release document does not match the pinned package/version identity.');
        }

        // 5. Review block: validated vocabulary; revocation never travels here (advisories own it).
        $review = $payload['review'] ?? null;
        $reviewStatus = is_array($review) && is_string($review['status'] ?? null) ? $review['status'] : '';
        if (!in_array($reviewStatus, self::REVIEW_STATUSES, true)) {
            throw new PackagePolicyException('release_review', 'The release document review block is malformed.');
        }
        $decidedAt = is_array($review) && is_string($review['decided_at'] ?? null)
            ? str_replace(['T', 'Z'], [' ', ''], $review['decided_at'])
            : null;

        // 6. Manifest (fail-closed vocabulary/schema validation).
        $manifest = $this->manifests->validate(
            is_array($payload['manifest'] ?? null) ? $payload['manifest'] : [],
            (string) $package['package_uid'],
            (string) $release['version']
        );

        // 7. Persist: artifact (idempotent, pre-txn) + hydration/evidence exactly once.
        $needsHydration = $fetched
            || ($release['manifest_json'] ?? null) === null
            || ($release['review_status'] ?? '') !== $reviewStatus;
        if (!$this->artifacts->has($digest)) {
            $this->artifacts->put($digest, $document);
        }
        if ($needsHydration) {
            $manifestJson = json_encode($payload['manifest'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->db->transaction(function () use ($release, $package, $registry, $manifestJson, $signature, $keyId, $reviewStatus, $decidedAt, $digest, $document): void {
                $this->releases->hydrateSignedMetadata((int) $release['id'], $manifestJson, $signature, $keyId, $reviewStatus);
                if (in_array($reviewStatus, ['approved', 'rejected'], true)
                    && $this->reviewDecisions->latestForDigest((int) $package['id'], $digest) === null) {
                    $this->reviewDecisions->record([
                        'package_id' => (int) $package['id'],
                        'release_id' => (int) $release['id'],
                        'version' => (string) $release['version'],
                        'digest' => $digest,
                        'decision' => $reviewStatus,
                        'decided_at' => $decidedAt,
                        'source' => 'release_document',
                        'evidence_json' => json_encode([
                            'format' => 'rb-release-envelope.v1',
                            'document' => $document,
                            'signature' => base64_encode($signature),
                            'key_id' => $keyId,
                        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ]);
                }
                $this->transparency->record([
                    'package_uid' => (string) $package['package_uid'],
                    'version' => (string) $release['version'],
                    'digest' => $digest,
                    'event' => 'release_verified',
                    'source' => 'release_document',
                    'registry_id' => (int) $registry['id'],
                    'detail' => 'review=' . $reviewStatus,
                ]);
            });
            $this->telemetry?->emit('package.release_verified', [
                'package' => (string) $package['package_uid'],
                'version' => (string) $release['version'],
                'digest' => $digest,
                'fetched' => $fetched,
            ]);
        }

        return $manifest;
    }

    /** @return array{0:string,1:string,2:string} document, raw signature, key id */
    private function fetchEnvelope(array $registry, array $package, array $release): array
    {
        $url = $this->releaseUrl($registry, $package, $release);
        $result = $this->transport->fetch($url);
        if ($result->status !== 200 || $result->body === '') {
            throw new PackagePolicyException('fetch_failed',
                'Could not fetch the release document (' . ($result->error ?? ('HTTP ' . $result->status)) . ').');
        }
        $envelope = json_decode($result->body, true);
        if (!is_array($envelope)
            || ($envelope['format'] ?? null) !== 'rb-release-envelope.v1'
            || !is_string($envelope['document'] ?? null)
            || !is_string($envelope['signature'] ?? null)
            || !is_string($envelope['key_id'] ?? null)) {
            throw new PackagePolicyException('fetch_failed', 'The release envelope is malformed.');
        }
        $signature = base64_decode($envelope['signature'], true);
        if ($signature === false) {
            throw new PackagePolicyException('fetch_failed', 'The release envelope signature is not valid base64.');
        }
        return [$envelope['document'], $signature, $envelope['key_id']];
    }

    /** Same-origin source pinning: the release fetch may never leave the registry origin (TM-SC-05). */
    private function releaseUrl(array $registry, array $package, array $release): string
    {
        $base = rtrim((string) $registry['base_url'], '/');
        $sourceUrl = $release['source_url'] ?? null;
        $url = is_string($sourceUrl) && $sourceUrl !== ''
            ? $sourceUrl
            : $base . '/releases/' . (string) $package['package_uid'] . '/'
                . rawurlencode((string) $release['version']) . '/rb-release-envelope.v1.json';

        $baseParts = parse_url($base);
        $urlParts = parse_url($url);
        if (!is_array($baseParts) || !is_array($urlParts)
            || ($urlParts['scheme'] ?? '') !== ($baseParts['scheme'] ?? '-')
            || ($urlParts['host'] ?? '') !== ($baseParts['host'] ?? '-')
            || ($urlParts['port'] ?? null) !== ($baseParts['port'] ?? null)) {
            throw new PackagePolicyException('source_mismatch',
                'The release source URL is not same-origin with its pinned registry.');
        }
        return $url;
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageAcquisitionServiceTest.php tests/Integration/Repository/PackageLifecycleRepositoriesTest.php`
Expected: PASS (9 acquisition tests + no repo regression).

- [ ] **Step 6: Commit**

```bash
git add src/Service/Packages/PackageAcquisitionService.php src/Repository/PackageReleaseRepository.php tests/Integration/Service/PackageAcquisitionServiceTest.php
git commit -m "feat(phase5): verified release acquisition - digest-pinned fetch, hydration, review-evidence cache (Inc 3, TM-SC-09)"
```

---
### Task 9: `PackageLifecycleService` — plan, install, consent, enable, disable

The install state machine over the validate-first discipline. `install` records the **exact failure stage** on refusal (§9 Install-rollback); `enable` re-runs the security gate (§9 Revoked-release / TM-SC-06's enable half) and quarantines on tampered bytes; consent commits the grant snapshot atomically (§8.5). Actor ids use `$admin->id()` (the `User::id(): int` accessor Inc 2 services use).

**Files:**
- Create: `src/Service/Packages/PackageLifecycleService.php` (plan/install/consent/enable/disable + private helpers; Tasks 10–11 add the remaining methods to this class)
- Test: `tests/Integration/Service/PackageLifecycleServiceTest.php`

**Interfaces:**
- Consumes: Tasks 2–8 (`PackageAcquisitionService::ensureVerified`, `PackageSecurityGate`, `PackageArtifactStore`, the five repositories, `ReauthGate`, `WriteGate`, `ModerationLogRepository::log`).
- Produces (locked): `plan()` returns `array{package:array, release:array, registry:?array, manifest:?PackageManifest, permissions:list, compatible:?bool, installed:?array, refusal:?array{code:string,message:string}, warnings:list<string>}` — plan **reports** gate/acquisition refusals instead of throwing (the plan page shows the reason; §9 Revoked-release "the operator sees reason"); `install()` returns the installed id and **throws** on any refusal after writing a history row with `failure_stage` ∈ `acquire|policy|compatibility|persist`; `consent()` returns the number of grants flipped; `enable()`/`disable()` return void. Private helpers later tasks reuse: `resolveTarget(int $packageId, ?int $releaseId): array{0:array,1:array,2:array}` (package, release, registry), `permissionRows(PackageManifest): list<array{kind,key,risk}>`, `installRow(...)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\UserRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageLifecycleServiceTest extends TestCase
{
    private SigningHarness $root;
    private array $seeded;
    private string $artifactDir;
    private PackageArtifactStore $store;
    private \App\Domain\User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-lifecycle-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        // Hydrated fixture + cached artifact: the kernel-equivalent warm path (no transport fetch needed).
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $this->admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->artifactDir . '/*') ?: []);
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    private function service(array $responses = []): PackageLifecycleService
    {
        $acquisition = new PackageAcquisitionService(
            $this->db, new TrustChainVerifier(), new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db), new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db), $this->store, new ManifestValidator(),
            new ArrayRegistryTransport($responses),
        );
        return new PackageLifecycleService(
            $this->db,
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageRegistryRepository($this->db),
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new PackageReviewDecisionRepository($this->db),
            $acquisition,
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            30,
        );
    }

    private function assertPolicyRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    private function lastHistory(): array
    {
        return (new PackageHistoryRepository($this->db))->forPackage((int) $this->seeded['package_id'], 1)[0];
    }

    private function auditActions(): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT action FROM moderation_log WHERE target_type = 'package' AND target_id = :pid ORDER BY id"
        );
        $stmt->execute(['pid' => $this->seeded['package_id']]);
        return array_map(static fn (array $r): string => (string) $r['action'], $stmt->fetchAll());
    }

    public function test_plan_reports_manifest_permissions_compat_and_no_refusal(): void
    {
        $plan = $this->service()->plan($this->admin, (int) $this->seeded['package_id']);

        self::assertNull($plan['refusal']);
        self::assertTrue($plan['compatible']);
        self::assertSame('acme/midnight-theme', $plan['manifest']->uid);
        self::assertNotEmpty($plan['permissions']);
        self::assertNull($plan['installed']);
    }

    public function test_plan_surfaces_gate_refusals_instead_of_throwing(): void
    {
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);
        $plan = $this->service()->plan($this->admin, (int) $this->seeded['package_id']);
        self::assertSame('locally_blocked', $plan['refusal']['code']);
    }

    public function test_install_consent_enable_disable_happy_path(): void
    {
        $service = $this->service();
        $packageId = (int) $this->seeded['package_id'];

        $installedId = $service->install($this->admin, 'password123', $packageId);
        $installs = new InstalledPackageRepository($this->db);
        $perms = new InstalledPackagePermissionRepository($this->db);

        $row = $installs->find($installedId);
        self::assertSame('installed', $row['state']);
        self::assertSame($this->seeded['release_digest'], $row['digest']);
        self::assertSame('reviewed_declarative', $row['trust_class']);
        self::assertGreaterThan(0, $perms->ungrantedCount($installedId), 'granted=0 until consent');

        // enable before consent refuses
        $this->assertPolicyRefusal('not_consented', fn () => $service->enable($this->admin, 'password123', $installedId));

        $granted = $service->consent($this->admin, 'password123', $installedId);
        self::assertSame(1, $granted); // fixture manifest declares one data_class
        self::assertSame(0, $perms->ungrantedCount($installedId));

        $service->enable($this->admin, 'password123', $installedId);
        self::assertSame('enabled', $installs->find($installedId)['state']);

        $service->disable($this->admin, $installedId);
        self::assertSame('disabled', $installs->find($installedId)['state']);

        $service->enable($this->admin, 'password123', $installedId);
        self::assertSame('enabled', $installs->find($installedId)['state']);

        $events = array_map(
            static fn (array $h): string => (string) $h['event'],
            array_reverse((new PackageHistoryRepository($this->db))->forInstall($installedId))
        );
        self::assertSame(['install', 'consent', 'enable', 'disable', 'enable'], $events);
        self::assertSame(
            ['package_install', 'package_consent', 'package_enable', 'package_disable', 'package_enable'],
            $this->auditActions()
        );
        $transparency = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertContains('install', array_column($transparency, 'event'));
    }

    public function test_double_install_refuses(): void
    {
        $service = $this->service();
        $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $this->assertPolicyRefusal('already_installed',
            fn () => $service->install($this->admin, 'password123', (int) $this->seeded['package_id']));
    }

    public function test_wrong_password_is_a_validation_error_and_installs_nothing(): void
    {
        try {
            $this->service()->install($this->admin, 'wrong', (int) $this->seeded['package_id']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }

    public function test_refused_install_writes_the_exact_failure_stage_and_no_rows(): void
    {
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);
        $this->assertPolicyRefusal('locally_blocked',
            fn () => $this->service()->install($this->admin, 'password123', (int) $this->seeded['package_id']));

        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']),
            'validate-first: a policy refusal persists no install row');
        $history = $this->lastHistory();
        self::assertSame('install', $history['event']);
        self::assertSame('policy', $history['failure_stage']);
        self::assertSame('locally_blocked', $history['detail']);
    }

    public function test_incompatible_core_range_refuses_at_the_compatibility_stage(): void
    {
        $future = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '9.0.0',
            'manifest' => ['core' => ['min' => '99.0.0', 'max' => null]],
        ], $this->artifactDir);

        $this->assertPolicyRefusal('incompatible_core',
            fn () => $this->service()->install($this->admin, 'password123', (int) $this->seeded['package_id'], (int) $future['release_id']));
        self::assertSame('compatibility', $this->lastHistory()['failure_stage']);
    }

    public function test_revoked_digest_cannot_be_installed_or_enabled(): void
    {
        // TM-SC-06 (lifecycle half): install refusal, then enable refusal for an existing disabled install.
        $service = $this->service();
        $packageId = (int) $this->seeded['package_id'];
        $installedId = $service->install($this->admin, 'password123', $packageId);
        $service->consent($this->admin, 'password123', $installedId);

        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REVOKE-1', 'registry_id' => $this->seeded['registry_id'],
            'package_id' => $packageId, 'affected_version_range' => null,
            'affected_digest' => $this->seeded['release_digest'], 'severity' => 'critical',
            'action' => 'revoke', 'summary' => 'compromised', 'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $this->assertPolicyRefusal('advisory_revoked', fn () => $service->enable($this->admin, 'password123', $installedId));
    }

    public function test_enable_with_tampered_artifact_quarantines(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);

        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered bytes');
        $this->assertPolicyRefusal('artifact_tampered', fn () => $service->enable($this->admin, 'password123', $installedId));

        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('quarantined', $row['state']);
        self::assertSame('failed', $row['health']);
        self::assertNotNull($row['quarantine_reason']);
    }

    public function test_zero_permission_package_enables_without_a_consent_step(): void
    {
        $bare = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '3.0.0',
            'manifest' => ['permissions' => ['data_classes' => []]],
        ], $this->artifactDir);

        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id'], (int) $bare['release_id']);
        $service->enable($this->admin, 'password123', $installedId); // vacuous consent: nothing declared
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }

    public function test_lifecycle_works_while_the_registry_is_unreachable(): void
    {
        // §9 Registry outage: enable/disable of the pinned install never fetches.
        $service = $this->service([]); // transport 404s everything
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);
        $service->enable($this->admin, 'password123', $installedId);
        $service->disable($this->admin, $installedId);
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageLifecycleServiceTest.php`
Expected: FAIL — service class not found. (If `UserRepository::findEntity` or `WriteGate`'s constructor differ from this test's assumptions, mirror how `tests/Integration/Service/RegistryTrustServiceTest.php` builds its `User` + `WriteGate` and adjust the test, not the design.)

- [ ] **Step 3: Implement `PackageLifecycleService`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * The install → consent → enable/disable state machine (P5-02). Validate-first:
 * every fetch/crypto/manifest/policy check happens before the write
 * transaction, so a refusal persists nothing and the recorded failure_stage is
 * exact (§9 Install-rollback). Consent commits the grant snapshot atomically
 * (§8.5); enable re-runs the security gate and the artifact tamper check
 * (§9 Revoked-release, TM-SC-06/07). "Enabled" is recorded eligibility —
 * no package code executes in Gate A Inc 3.
 */
final class PackageLifecycleService
{
    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageRegistryRepository $registries,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageAcquisitionService $acquisition,
        private PackageSecurityGate $gate,
        private PackageArtifactStore $artifacts,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private int $retentionDays = 30,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** Install preview: resolve + verify + summarize; REPORTS refusals rather than throwing. */
    public function plan(User $admin, int $packageId, ?int $releaseId = null): array
    {
        [$package, $release, $registry] = $this->resolveTarget($packageId, $releaseId);
        $installed = $this->installs->findByPackage($packageId);
        $manifest = null;
        $refusal = null;
        $warnings = [];
        try {
            if ($registry === null) {
                throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
            }
            $manifest = $this->acquisition->ensureVerified($registry, $package, $release);
            $this->gate->assertInstallable($package, $release);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            $refusal = ['code' => (string) $e->code, 'message' => $e->getMessage()];
        }
        if (($package['advisory_status'] ?? 'none') === 'warned' || ($release['advisory_status'] ?? 'none') === 'warned') {
            $warnings[] = 'An advisory warns about this package. Review the trust console before installing.';
        }
        return [
            'package' => $package,
            'release' => $release,
            'registry' => $registry,
            'manifest' => $manifest,
            'permissions' => $manifest?->permissions ?? [],
            'compatible' => $manifest?->coreCompatible(),
            'installed' => $installed,
            'refusal' => $refusal,
            'warnings' => $warnings,
        ];
    }

    /** Atomic install with full provenance; permission snapshot lands granted=0 (§8.5, decision #7). */
    public function install(User $admin, string $currentPassword, int $packageId, ?int $releaseId = null): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        [$package, $release, $registry] = $this->resolveTarget($packageId, $releaseId);
        $existing = $this->installs->findByPackage($packageId);
        if ($existing !== null && $existing['state'] !== 'uninstalled') {
            throw new PackagePolicyException('already_installed', 'This package already has a local installation.');
        }

        $stage = 'acquire';
        try {
            if ($registry === null) {
                throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
            }
            $manifest = $this->acquisition->ensureVerified($registry, $package, $release);
            $stage = 'policy';
            $this->gate->assertInstallable($package, $release);
            $stage = 'compatibility';
            if (!$manifest->coreCompatible()) {
                throw new PackagePolicyException('incompatible_core',
                    'This release does not support the running core version.');
            }
            $stage = 'persist';
            $row = [
                'package_id' => (int) $package['id'],
                'release_id' => (int) $release['id'],
                'digest' => (string) $release['digest'],
                'source_registry_id' => (int) $registry['id'],
                'publisher_id' => $package['publisher_id'] !== null ? (int) $package['publisher_id'] : null,
                'trust_class' => (string) $package['trust_class'],
                'review_status' => (string) $release['review_status'],
                'compat_min' => $manifest->coreMin,
                'compat_max' => $manifest->coreMax,
                'installed_by' => $admin->id(),
            ];
            $installedId = $this->db->transaction(function () use ($existing, $row, $package, $release, $manifest, $admin): int {
                if ($existing !== null) {
                    $installedId = (int) $existing['id'];
                    $this->installs->reviveForInstall($installedId, $row);
                } else {
                    $installedId = $this->installs->create($row);
                }
                $this->permissions->replaceDeclared($installedId, $this->permissionRows($manifest));
                $this->history->record([
                    'package_id' => (int) $package['id'],
                    'installed_package_id' => $installedId,
                    'event' => 'install',
                    'actor_id' => $admin->id(),
                    'new_version' => (string) $release['version'],
                    'new_digest' => (string) $release['digest'],
                    'permission_snapshot_json' => json_encode($manifest->permissions, JSON_UNESCAPED_SLASHES),
                ]);
                $this->transparency->record([
                    'package_uid' => (string) $package['package_uid'],
                    'version' => (string) $release['version'],
                    'digest' => (string) $release['digest'],
                    'event' => 'install',
                    'source' => 'local',
                    'actor_id' => $admin->id(),
                    'registry_id' => $row['source_registry_id'],
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_install',
                    'target_type' => 'package',
                    'target_id' => (int) $package['id'],
                    'after' => ['version' => (string) $release['version'], 'digest' => (string) $release['digest']],
                ]);
                return $installedId;
            });
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            // Validate-first means nothing persisted; record the exact stage (§9 Install-rollback).
            $this->history->record([
                'package_id' => (int) $package['id'],
                'installed_package_id' => $existing !== null ? (int) $existing['id'] : null,
                'event' => 'install',
                'actor_id' => $admin->id(),
                'new_version' => (string) $release['version'],
                'new_digest' => (string) $release['digest'],
                'failure_stage' => $stage,
                'detail' => (string) $e->code,
            ]);
            $this->telemetry?->emit('package.install', [
                'package' => (string) $package['package_uid'],
                'result' => 'refused', 'stage' => $stage, 'reason' => (string) $e->code,
            ]);
            throw $e;
        }

        $this->telemetry?->emit('package.install', [
            'package' => (string) $package['package_uid'],
            'version' => (string) $release['version'],
            'digest' => (string) $release['digest'],
            'result' => 'installed',
        ]);
        return $installedId;
    }

    /** Grant the full declared set in one decision (§8.5: consent + snapshot commit together). */
    public function consent(User $admin, string $currentPassword, int $installedId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'enabled', 'disabled']);
        if ($install['staged_release_id'] !== null) {
            throw new PackagePolicyException('stage_pending',
                'A staged update is awaiting re-consent; approve or cancel it instead.');
        }
        if ($this->permissions->ungrantedCount($installedId) === 0) {
            throw new PackagePolicyException('invalid_state', 'There are no pending permission grants.');
        }
        $package = $this->packages->find((int) $install['package_id']);

        $granted = $this->db->transaction(function () use ($installedId, $install, $package, $admin): int {
            $granted = $this->permissions->grantAll($installedId, $admin->id());
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'consent',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
                'permission_snapshot_json' => json_encode($this->permissions->forInstall($installedId), JSON_UNESCAPED_SLASHES),
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_consent',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'after' => ['granted' => $granted],
            ]);
            return $granted;
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'consent', 'package' => (string) ($package['package_uid'] ?? ''), 'granted' => $granted,
        ]);
        return $granted;
    }

    /** Recorded execution eligibility; re-runs the gate + the artifact tamper check every time. */
    public function enable(User $admin, string $currentPassword, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'disabled']);
        if ($this->permissions->ungrantedCount($installedId) > 0) {
            throw new PackagePolicyException('not_consented',
                'Declared permissions are not consented yet; review and grant them first.');
        }
        $package = $this->packages->find((int) $install['package_id']);
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        if ($package === null || $release === null) {
            throw new PackagePolicyException('invalid_state', 'The installed release is no longer resolvable.');
        }
        $this->gate->assertEnableable($package, $release);

        $digest = (string) $install['digest'];
        if (!$this->artifacts->has($digest)) {
            throw new PackagePolicyException('artifact_missing', 'The verified artifact is missing from the local store.');
        }
        if (!$this->artifacts->verify($digest)) {
            $this->quarantine($install, $package, $admin->id(), 'digest mismatch on enable');
            throw new PackagePolicyException('artifact_tampered',
                'Installed bytes no longer match the reviewed digest; the package was quarantined.');
        }

        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->setState($installedId, 'enabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'enable',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(), 'action' => 'package_enable',
                'target_type' => 'package', 'target_id' => (int) $install['package_id'],
            ]);
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'enable', 'package' => (string) $package['package_uid'],
        ]);
    }

    /** The no-friction emergency brake: WriteGate + audit, deliberately NO reauth. */
    public function disable(User $admin, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['enabled']);
        $package = $this->packages->find((int) $install['package_id']);

        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->setState($installedId, 'disabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'disable',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(), 'action' => 'package_disable',
                'target_type' => 'package', 'target_id' => (int) $install['package_id'],
            ]);
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'disable', 'package' => (string) ($package['package_uid'] ?? ''),
        ]);
    }

    // ── shared helpers (Tasks 10–11 add setPinned/setUpdatePolicy/uninstall/export/reverify here) ──

    /** @return array{0:array,1:array,2:?array} package, release, registry */
    private function resolveTarget(int $packageId, ?int $releaseId): array
    {
        $package = $this->packages->find($packageId);
        if ($package === null) {
            throw new NotFoundException();
        }
        if ($releaseId !== null) {
            $release = $this->releases->find($releaseId);
            if ($release === null || (int) $release['package_id'] !== $packageId) {
                throw new PackagePolicyException('release_identity', 'That release does not belong to this package.');
            }
        } else {
            $latestId = $package['latest_release_id'] !== null ? (int) $package['latest_release_id'] : 0;
            $release = $latestId > 0 ? $this->releases->find($latestId) : null;
            if ($release === null) {
                throw new PackagePolicyException('no_release', 'This package has no installable release.');
            }
        }
        $registry = $package['registry_id'] !== null ? $this->registries->find((int) $package['registry_id']) : null;
        return [$package, $release, $registry];
    }

    private function requireInstall(int $installedId): array
    {
        $install = $this->installs->find($installedId);
        if ($install === null) {
            throw new PackagePolicyException('not_installed', 'No local installation with that id exists.');
        }
        return $install;
    }

    /** @param list<string> $allowed */
    private function assertState(array $install, array $allowed): void
    {
        if (!in_array((string) $install['state'], $allowed, true)) {
            throw new PackagePolicyException('invalid_state',
                'This action is not available while the package is ' . (string) $install['state'] . '.');
        }
    }

    /** @return list<array{kind:string,key:string,risk:string}> */
    private function permissionRows(PackageManifest $manifest): array
    {
        return array_map(
            static fn (array $p): array => ['kind' => $p['kind'], 'key' => $p['key'], 'risk' => $p['risk']],
            $manifest->permissions
        );
    }

    private function versionOf(array $install): ?string
    {
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        return $release !== null ? (string) $release['version'] : null;
    }

    /** Shared with Task 12's health worker semantics: quarantine + history + audit (actor nullable). */
    private function quarantine(array $install, array $package, ?int $actorId, string $reason): void
    {
        $installedId = (int) $install['id'];
        $this->db->transaction(function () use ($install, $installedId, $package, $actorId, $reason): void {
            $this->installs->setState($installedId, 'quarantined');
            $this->installs->setHealth($installedId, 'failed', $reason);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'quarantine',
                'actor_id' => $actorId,
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => $this->versionOf($install),
                'digest' => (string) $install['digest'],
                'event' => 'quarantine',
                'source' => 'local',
                'actor_id' => $actorId,
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => $actorId, 'action' => 'package_quarantine',
                'target_type' => 'package', 'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'quarantine', 'package' => (string) $package['package_uid'], 'reason' => $reason,
        ]);
    }
}
```

Adjust the two collaborator constructors to reality when wiring the test: `ReauthGate` takes the project's `PasswordHasher`; `WriteGate` — construct exactly as `RegistryTrustServiceTest` does (it may be dependency-free or repo-backed). Do not change the service design over it.

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageLifecycleServiceTest.php tests/Integration/Security/PackageSecurityGateTest.php`
Expected: PASS (11 lifecycle tests; no gate regression).

- [ ] **Step 5: Commit**

```bash
git add src/Service/Packages/PackageLifecycleService.php tests/Integration/Service/PackageLifecycleServiceTest.php
git commit -m "feat(phase5): install/consent/enable/disable lifecycle - validate-first, failure stages, quarantine-on-tamper (Inc 3)"
```

---
### Task 10: Pin / update-policy + `PackageUpdateService` — staged updates, permission diff + re-consent, rollback

The §9 Permission-increase / Permission-reduction scenarios: an update that **adds** permissions stages until fresh consent (the old version keeps its old grant only); an update that only removes/keeps applies immediately with the reduction effective at activation. Rollback reuses the same machinery against a **previously verified digest** (§13.2). Pin blocks updates (not rollback — pin exists to stop forward movement; rollback is the explicit recovery action). Grants are never invented: unchanged keys inherit their granted flag; staged approval is itself the consent for the new set.

**Files:**
- Modify: `src/Service/Packages/PackageLifecycleService.php` (add `setPinned`, `setUpdatePolicy`)
- Create: `src/Service/Packages/PackageUpdateService.php` (consumes `InstalledPackagePermissionRepository::replaceWithGrants` from Task 5)
- Test: `tests/Integration/Service/PackageUpdateServiceTest.php`

**Interfaces:**
- Produces: `updatePlan(User, int $installedId, ?int $targetReleaseId = null): array{install,package,current_release,target,manifest:?PackageManifest,diff,requires_consent:bool,compatible:?bool,refusal:?array}` (reports refusals like `plan()`); `update(...): array{status:'staged'|'applied'}`; `applyStaged(...): void` (re-validates gate/compat/artifact against the staged target — §8.5 #3 — and derives the history event `rollback` vs `update` by `version_compare(target, prior)`); `cancelStaged(...): void`; `rollbackTargets(int): list<array>` (release rows whose digest ∈ `PackageHistoryRepository::verifiedDigestsFor` ∧ artifact cached ∧ ≠ current digest); `rollback(...): array{status:'staged'|'applied'}`.
- Refusals: `pinned`, `same_release`, `stage_pending`, `no_staged_update`, `rollback_target`, plus everything the gate/acquisition throws for the target release.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\UserRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\PasswordHasher;
use App\Security\Registry\TrustChainVerifier;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\ArrayRegistryTransport;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageUpdateServiceTest extends TestCase
{
    private SigningHarness $root;
    private array $seeded;
    private string $artifactDir;
    private PackageArtifactStore $store;
    private \App\Domain\User $admin;
    private int $installedId;
    private array $expanded;   // 1.1.0: adds api_scope + outbound_host
    private array $reduced;    // 1.2.0: empty permission set

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate();
        $this->artifactDir = sys_get_temp_dir() . '/rb-update-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->artifactDir);
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        $adminRow = $this->makeAdmin(['password' => 'password123']);
        $this->admin = (new UserRepository($this->db))->findEntity((int) $adminRow['id']);

        $this->installedId = $this->lifecycle()->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $this->lifecycle()->consent($this->admin, 'password123', $this->installedId);
        $this->lifecycle()->enable($this->admin, 'password123', $this->installedId);

        $this->expanded = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'api_scopes' => ['read:boards'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);
        $this->reduced = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.2.0',
            'manifest' => ['permissions' => ['data_classes' => []]],
        ], $this->artifactDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->artifactDir . '/*') ?: []);
        @rmdir($this->artifactDir);
        parent::tearDown();
    }

    private function lifecycle(): PackageLifecycleService
    {
        return new PackageLifecycleService(
            $this->db, new PackageRepository($this->db), new PackageReleaseRepository($this->db),
            new PackageRegistryRepository($this->db), new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db), new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db), new PackageReviewDecisionRepository($this->db), $this->acquisition(),
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store, new ReauthGate(new PasswordHasher()), new WriteGate(),
            new ModerationLogRepository($this->db), 30,
        );
    }

    private function updates(): PackageUpdateService
    {
        return new PackageUpdateService(
            $this->db, new PackageRepository($this->db), new PackageReleaseRepository($this->db),
            new PackageRegistryRepository($this->db), new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db), new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db), $this->acquisition(),
            new PackageSecurityGate(new LocalPackageBlockRepository($this->db), new PackageAdvisoryRepository($this->db)),
            $this->store, new ReauthGate(new PasswordHasher()), new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function acquisition(): PackageAcquisitionService
    {
        return new PackageAcquisitionService(
            $this->db, new TrustChainVerifier(), new RegistryTrustKeyRepository($this->db),
            new PackageReleaseRepository($this->db), new PackageReviewDecisionRepository($this->db),
            new PackageTransparencyLogRepository($this->db), $this->store, new ManifestValidator(),
            new ArrayRegistryTransport([]),
        );
    }

    private function assertPolicyRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    /** @return array<string,int> "kind:key" => granted */
    private function grants(): array
    {
        $out = [];
        foreach ((new InstalledPackagePermissionRepository($this->db))->forInstall($this->installedId) as $row) {
            $out[$row['kind'] . ':' . $row['permission_key']] = (int) $row['granted'];
        }
        return $out;
    }

    public function test_update_plan_reports_the_permission_diff(): void
    {
        $plan = $this->updates()->updatePlan($this->admin, $this->installedId, (int) $this->expanded['release_id']);

        self::assertNull($plan['refusal']);
        self::assertTrue($plan['requires_consent']);
        $addedKeys = array_map(static fn (array $p): string => $p['kind'] . ':' . $p['key'], $plan['diff']['added']);
        sort($addedKeys);
        self::assertSame(['api_scope:read:boards', 'outbound_host:api.example.com'], $addedKeys);
        self::assertSame([], $plan['diff']['removed']);
    }

    public function test_permission_increase_stages_and_the_old_version_keeps_its_old_grant_only(): void
    {
        $result = $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);
        self::assertSame('staged', $result['status']);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest'], 'old version stays active');
        self::assertSame($this->expanded['digest'], $row['staged_digest']);
        self::assertSame('enabled', $row['state'], 'staging never touches execution eligibility');
        self::assertSame(['data_class:package.own_storage' => 1], $this->grants(), 'grant snapshot untouched while staged');
        self::assertSame('update_staged',
            (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0]['event']);
    }

    public function test_apply_staged_swaps_release_and_commits_the_consented_set(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);
        $updates->applyStaged($this->admin, 'password123', $this->installedId);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame((int) $this->expanded['release_id'], (int) $row['release_id']);
        self::assertSame($this->expanded['digest'], $row['digest']);
        self::assertNull($row['staged_release_id']);
        self::assertSame([
            'api_scope:read:boards' => 1,
            'data_class:package.own_storage' => 1,
            'outbound_host:api.example.com' => 1,
        ], $this->grants(), 'staged approval is the consent for the full new set');

        $history = (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0];
        self::assertSame('update', $history['event']);
        self::assertSame('1.0.0', $history['prior_version']);
        self::assertSame('1.1.0', $history['new_version']);
    }

    public function test_permission_reduction_applies_immediately_and_preserves_inherited_grants(): void
    {
        $result = $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']);
        self::assertSame('applied', $result['status']);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame((int) $this->reduced['release_id'], (int) $row['release_id']);
        self::assertSame([], $this->grants(), 'reduction takes effect at activation (§9)');
    }

    public function test_pinned_install_refuses_update_but_unpin_restores_it(): void
    {
        $this->lifecycle()->setPinned($this->admin, $this->installedId, true);
        $this->assertPolicyRefusal('pinned',
            fn () => $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']));

        $this->lifecycle()->setPinned($this->admin, $this->installedId, false);
        self::assertSame('applied',
            $this->updates()->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id'])['status']);
        $events = array_column((new PackageHistoryRepository($this->db))->forInstall($this->installedId), 'event');
        self::assertContains('pin', $events);
        self::assertContains('unpin', $events);
    }

    public function test_same_release_and_pending_stage_refuse(): void
    {
        $updates = $this->updates();
        $this->assertPolicyRefusal('same_release',
            fn () => $updates->update($this->admin, 'password123', $this->installedId, (int) $this->seeded['release_id']));

        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);
        $this->assertPolicyRefusal('stage_pending',
            fn () => $updates->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']));

        $updates->cancelStaged($this->admin, $this->installedId);
        self::assertNull((new InstalledPackageRepository($this->db))->find($this->installedId)['staged_release_id']);
        $this->assertPolicyRefusal('no_staged_update', fn () => $updates->applyStaged($this->admin, 'password123', $this->installedId));
    }

    public function test_staged_target_revoked_before_apply_fails_closed(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']);

        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REVOKE-STAGED', 'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'], 'affected_version_range' => null,
            'affected_digest' => $this->expanded['digest'], 'severity' => 'critical', 'action' => 'revoke',
            'summary' => 'compromised', 'signed_evidence' => null, 'issued_at' => '2026-07-01 00:00:00',
        ]);

        $this->assertPolicyRefusal('advisory_revoked', fn () => $updates->applyStaged($this->admin, 'password123', $this->installedId));
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest'], 'prior version remains active (§8.5 #4)');
    }

    public function test_rollback_targets_and_rollback_after_an_update(): void
    {
        $updates = $this->updates();
        $updates->update($this->admin, 'password123', $this->installedId, (int) $this->reduced['release_id']); // applied

        $targets = $updates->rollbackTargets($this->installedId);
        self::assertSame([$this->seeded['release_digest']], array_column($targets, 'digest'), 'only previously verified digests');

        // Rolling back to 1.0.0 re-adds data_class:package.own_storage → expansion vs current → stages.
        $result = $updates->rollback($this->admin, 'password123', $this->installedId, (int) $this->seeded['release_id']);
        self::assertSame('staged', $result['status']);
        $updates->applyStaged($this->admin, 'password123', $this->installedId);

        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame($this->seeded['release_digest'], $row['digest']);
        self::assertSame('rollback',
            (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0]['event'],
            'applyStaged derives the rollback event from version order');
    }

    public function test_rollback_to_a_never_verified_release_refuses(): void
    {
        $this->assertPolicyRefusal('rollback_target',
            fn () => $this->updates()->rollback($this->admin, 'password123', $this->installedId, (int) $this->expanded['release_id']));
    }

    public function test_update_policy_stores_only_the_gate_a_vocabulary(): void
    {
        $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'notify');
        self::assertSame('notify', (new InstalledPackageRepository($this->db))->find($this->installedId)['update_policy']);
        $this->assertPolicyRefusal('update_policy',
            fn () => $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'auto'));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageUpdateServiceTest.php`
Expected: FAIL — `PackageUpdateService` not found, `setPinned`/`setUpdatePolicy` missing.

- [ ] **Step 3: Add `setPinned` / `setUpdatePolicy` to `PackageLifecycleService`**

```php
/** Pin blocks forward updates (never rollback or security enforcement). WriteGate + audit, no reauth. */
public function setPinned(User $admin, int $installedId, bool $pinned): void
{
    $this->writeGate->assertCanWrite($admin);
    $install = $this->requireInstall($installedId);
    $this->assertState($install, ['installed', 'enabled', 'disabled', 'quarantined']);
    $this->db->transaction(function () use ($install, $installedId, $pinned, $admin): void {
        $this->installs->setPinned($installedId, $pinned);
        $this->history->record([
            'package_id' => (int) $install['package_id'],
            'installed_package_id' => $installedId,
            'event' => $pinned ? 'pin' : 'unpin',
            'actor_id' => $admin->id(),
            'new_version' => $this->versionOf($install),
            'new_digest' => (string) $install['digest'],
        ]);
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => $pinned ? 'package_pin' : 'package_unpin',
            'target_type' => 'package', 'target_id' => (int) $install['package_id'],
        ]);
    });
}

/** Gate A vocabulary is manual|notify — 'auto' does not exist (program plan: auto-update OFF). */
public function setUpdatePolicy(User $admin, int $installedId, string $policy): void
{
    $this->writeGate->assertCanWrite($admin);
    if (!in_array($policy, ['manual', 'notify'], true)) {
        throw new PackagePolicyException('update_policy', 'Update policy must be manual or notify.');
    }
    $install = $this->requireInstall($installedId);
    $this->assertState($install, ['installed', 'enabled', 'disabled', 'quarantined']);
    $this->db->transaction(function () use ($install, $installedId, $policy, $admin): void {
        $this->installs->setUpdatePolicy($installedId, $policy);
        $this->audit->log([
            'actor_id' => $admin->id(), 'action' => 'package_update_policy',
            'target_type' => 'package', 'target_id' => (int) $install['package_id'],
            'after' => ['policy' => $policy],
        ]);
    });
}
```

- [ ] **Step 4: Implement `PackageUpdateService`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\Packages\PermissionDiff;
use App\Security\Registry\RegistryVerificationException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Staged updates + rollback (P5-02 SP4/SP5). Permission expansion stages until
 * fresh consent — the running version keeps its exact prior grant (§8.5 #2);
 * reduction/no-change applies immediately with removal effective at activation
 * (§9 Permission-reduction). Rollback activates only a previously verified
 * digest (§13.2) through the same staged/immediate machinery; applyStaged
 * re-validates gate + compatibility + artifact so a digest revoked after
 * staging can never activate (§8.5 #3).
 */
final class PackageUpdateService
{
    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageRegistryRepository $registries,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private PackageAcquisitionService $acquisition,
        private PackageSecurityGate $gate,
        private PackageArtifactStore $artifacts,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    public function updatePlan(User $admin, int $installedId, ?int $targetReleaseId = null): array
    {
        $install = $this->requireInstall($installedId);
        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, $targetReleaseId);
        $manifest = null;
        $refusal = null;
        $diff = ['added' => [], 'removed' => [], 'unchanged' => []];
        try {
            $manifest = $this->verifyTarget($registry, $package, $target);
            $diff = $this->diffAgainstCurrent($installedId, $manifest);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            $refusal = ['code' => (string) $e->code, 'message' => $e->getMessage()];
        }
        return [
            'install' => $install,
            'package' => $package,
            'current_release' => $current,
            'target' => $target,
            'manifest' => $manifest,
            'diff' => $diff,
            'requires_consent' => $diff['added'] !== [],
            'compatible' => $manifest?->coreCompatible(),
            'refusal' => $refusal,
        ];
    }

    /** @return array{status:'staged'|'applied'} */
    public function update(User $admin, string $currentPassword, int $installedId, ?int $targetReleaseId = null): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $install = $this->requireInstall($installedId);
        $this->assertUpdatable($install);
        if ((int) $install['pinned'] === 1) {
            throw new PackagePolicyException('pinned', 'This installation is pinned; unpin it before updating.');
        }
        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, $targetReleaseId);
        return $this->stageOrApply($admin, $install, $package, $registry, $current, $target, isRollback: false);
    }

    public function applyStaged(User $admin, string $currentPassword, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $install = $this->requireInstall($installedId);
        if ($install['staged_release_id'] === null) {
            throw new PackagePolicyException('no_staged_update', 'There is no staged update to approve.');
        }
        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, (int) $install['staged_release_id']);
        // Re-validate everything at approval time — a digest revoked after staging cannot activate (§8.5 #3).
        $manifest = $this->verifyTarget($registry, $package, $target);
        $this->gate->assertEnableable($package, $target);
        $this->assertCompatible($manifest);
        $this->activate($admin, $install, $package, $current, $target, $manifest, allGranted: true);
    }

    public function cancelStaged(User $admin, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $install = $this->requireInstall($installedId);
        if ($install['staged_release_id'] === null) {
            throw new PackagePolicyException('no_staged_update', 'There is no staged update to cancel.');
        }
        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->stageRelease($installedId, null, null);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'update_staged',
                'actor_id' => $admin->id(),
                'detail' => 'cancelled',
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(), 'action' => 'package_update_cancel',
                'target_type' => 'package', 'target_id' => (int) $install['package_id'],
            ]);
        });
    }

    /** Releases this installation may roll back to: previously verified digests with cached artifacts. */
    public function rollbackTargets(int $installedId): array
    {
        $install = $this->requireInstall($installedId);
        $verified = array_flip($this->history->verifiedDigestsFor((int) $install['package_id']));
        $targets = [];
        foreach ($this->releases->forPackage((int) $install['package_id']) as $release) {
            $digest = (string) $release['digest'];
            if ($digest === (string) $install['digest'] || !isset($verified[$digest]) || !$this->artifacts->has($digest)) {
                continue;
            }
            $targets[] = $release;
        }
        return $targets;
    }

    /** @return array{status:'staged'|'applied'} */
    public function rollback(User $admin, string $currentPassword, int $installedId, int $targetReleaseId): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $install = $this->requireInstall($installedId);
        $this->assertUpdatable($install);
        $eligible = array_column($this->rollbackTargets($installedId), 'id');
        if (!in_array($targetReleaseId, array_map('intval', $eligible), true)) {
            throw new PackagePolicyException('rollback_target',
                'Rollback targets must be previously verified digests with cached artifacts (§13.2).');
        }
        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, $targetReleaseId);
        return $this->stageOrApply($admin, $install, $package, $registry, $current, $target, isRollback: true);
    }

    // ── internals ──

    /** @return array{status:'staged'|'applied'} */
    private function stageOrApply(User $admin, array $install, array $package, array $registry, ?array $current, array $target, bool $isRollback): array
    {
        $installedId = (int) $install['id'];
        $manifest = $this->verifyTarget($registry, $package, $target);
        $this->gate->assertInstallable($package, $target); // an update/rollback target is a new install decision (block_new applies)
        $this->assertCompatible($manifest);

        $diff = $this->diffAgainstCurrent($installedId, $manifest);
        if ($diff['added'] !== []) {
            $this->db->transaction(function () use ($install, $installedId, $target, $diff, $admin): void {
                $this->installs->stageRelease($installedId, (int) $target['id'], (string) $target['digest']);
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => $installedId,
                    'event' => 'update_staged',
                    'actor_id' => $admin->id(),
                    'prior_version' => $this->versionOf($install),
                    'new_version' => (string) $target['version'],
                    'prior_digest' => (string) $install['digest'],
                    'new_digest' => (string) $target['digest'],
                    'permission_snapshot_json' => json_encode($diff, JSON_UNESCAPED_SLASHES),
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(), 'action' => 'package_update_staged',
                    'target_type' => 'package', 'target_id' => (int) $install['package_id'],
                    'after' => ['version' => (string) $target['version'], 'added' => count($diff['added'])],
                ]);
            });
            $this->telemetry?->emit('package.update', [
                'package' => (string) $package['package_uid'], 'from' => $this->versionOf($install),
                'to' => (string) $target['version'], 'result' => 'staged', 'added' => count($diff['added']),
            ]);
            return ['status' => 'staged'];
        }

        $this->activate($admin, $install, $package, $current, $target, $manifest, allGranted: false);
        return ['status' => 'applied'];
    }

    /** Atomic activation (§8.5 #4): pointer swap + permission snapshot + history/transparency/audit together. */
    private function activate(User $admin, array $install, array $package, ?array $current, array $target, PackageManifest $manifest, bool $allGranted): void
    {
        $installedId = (int) $install['id'];
        $digest = (string) $target['digest'];
        if (!$this->artifacts->verify($digest)) {
            throw new PackagePolicyException('artifact_tampered', 'The target artifact failed its digest check.');
        }
        $priorVersion = $current !== null ? (string) $current['version'] : null;
        $event = $isRollback = ($priorVersion !== null && version_compare((string) $target['version'], $priorVersion, '<'))
            ? 'rollback' : 'update';

        $previouslyGranted = [];
        foreach ($this->permissions->forInstall($installedId) as $row) {
            if ((int) $row['granted'] === 1) {
                $previouslyGranted[$row['kind'] . ':' . $row['permission_key']] = true;
            }
        }
        $rows = [];
        foreach ($manifest->permissions as $p) {
            $rows[] = [
                'kind' => $p['kind'], 'key' => $p['key'], 'risk' => $p['risk'],
                'granted' => $allGranted || isset($previouslyGranted[$p['kind'] . ':' . $p['key']]),
            ];
        }
        $priorSnapshot = json_encode($this->permissions->forInstall($installedId), JSON_UNESCAPED_SLASHES);

        $this->db->transaction(function () use ($install, $installedId, $package, $target, $manifest, $rows, $admin, $event, $priorVersion, $priorSnapshot): void {
            $this->installs->activateRelease(
                $installedId, (int) $target['id'], (string) $target['digest'],
                $manifest->coreMin, $manifest->coreMax, (string) $target['review_status']
            );
            $this->permissions->replaceWithGrants($installedId, $rows, $admin->id());
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => $event,
                'actor_id' => $admin->id(),
                'prior_version' => $priorVersion,
                'new_version' => (string) $target['version'],
                'prior_digest' => (string) $install['digest'],
                'new_digest' => (string) $target['digest'],
                'permission_snapshot_json' => $priorSnapshot,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => (string) $target['version'],
                'digest' => (string) $target['digest'],
                'event' => $event,
                'source' => 'local',
                'actor_id' => $admin->id(),
                'registry_id' => $install['source_registry_id'] !== null ? (int) $install['source_registry_id'] : null,
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $event === 'rollback' ? 'package_rollback' : 'package_update',
                'target_type' => 'package', 'target_id' => (int) $install['package_id'],
                'before' => ['version' => $priorVersion, 'digest' => (string) $install['digest']],
                'after' => ['version' => (string) $target['version'], 'digest' => (string) $target['digest']],
            ]);
        });
        $this->telemetry?->emit('package.update', [
            'package' => (string) $package['package_uid'], 'from' => $priorVersion,
            'to' => (string) $target['version'], 'result' => $event,
        ]);
    }

    private function verifyTarget(array $registry, array $package, array $target): PackageManifest
    {
        return $this->acquisition->ensureVerified($registry, $package, $target);
    }

    private function assertCompatible(PackageManifest $manifest): void
    {
        if (!$manifest->coreCompatible()) {
            throw new PackagePolicyException('incompatible_core', 'The target release does not support the running core version.');
        }
    }

    /** @return array{added:list,removed:list,unchanged:list} */
    private function diffAgainstCurrent(int $installedId, PackageManifest $target): array
    {
        $current = array_map(
            static fn (array $row): array => ['kind' => (string) $row['kind'], 'key' => (string) $row['permission_key']],
            $this->permissions->forInstall($installedId)
        );
        return PermissionDiff::diff($current, $target->permissions);
    }

    private function requireInstall(int $installedId): array
    {
        $install = $this->installs->find($installedId);
        if ($install === null) {
            throw new PackagePolicyException('not_installed', 'No local installation with that id exists.');
        }
        return $install;
    }

    private function assertUpdatable(array $install): void
    {
        if (!in_array((string) $install['state'], ['installed', 'enabled', 'disabled'], true)) {
            throw new PackagePolicyException('invalid_state',
                'Updates are not available while the package is ' . (string) $install['state'] . '.');
        }
        if ($install['staged_release_id'] !== null) {
            throw new PackagePolicyException('stage_pending', 'A staged update is already pending; approve or cancel it first.');
        }
    }

    /** @return array{0:array,1:array,2:array,3:?array} package, target release, registry, current release */
    private function resolveUpdateTarget(array $install, ?int $targetReleaseId): array
    {
        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null) {
            throw new NotFoundException();
        }
        if ($targetReleaseId !== null) {
            $target = $this->releases->find($targetReleaseId);
            if ($target === null || (int) $target['package_id'] !== (int) $package['id']) {
                throw new PackagePolicyException('release_identity', 'That release does not belong to this package.');
            }
        } else {
            $latestId = $package['latest_release_id'] !== null ? (int) $package['latest_release_id'] : 0;
            $target = $latestId > 0 ? $this->releases->find($latestId) : null;
            if ($target === null) {
                throw new PackagePolicyException('no_release', 'This package has no release to update to.');
            }
        }
        if ((string) $target['digest'] === (string) $install['digest']) {
            throw new PackagePolicyException('same_release', 'The installation is already on that release.');
        }
        $registry = $package['registry_id'] !== null ? $this->registries->find((int) $package['registry_id']) : null;
        if ($registry === null) {
            throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
        }
        $current = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        return [$package, $target, $registry, $current];
    }

    private function versionOf(array $install): ?string
    {
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        return $release !== null ? (string) $release['version'] : null;
    }
}
```

Note: in `activate()` fix the `$event` assignment to plain readable form when implementing:

```php
$event = ($priorVersion !== null && version_compare((string) $target['version'], $priorVersion, '<')) ? 'rollback' : 'update';
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageUpdateServiceTest.php tests/Integration/Service/PackageLifecycleServiceTest.php`
Expected: PASS (11 update tests; no lifecycle regression).

- [ ] **Step 6: Commit**

```bash
git add src/Service/Packages/PackageUpdateService.php src/Service/Packages/PackageLifecycleService.php src/Repository/InstalledPackagePermissionRepository.php tests/Integration/Service/PackageUpdateServiceTest.php
git commit -m "feat(phase5): staged updates with permission diff/re-consent, reduction-immediate, pin, verified-digest rollback (Inc 3)"
```

---

### Task 11: Uninstall + export + retention + reverify

§9 Uninstall/data: uninstall **disables execution first**, auto-creates the export, observes the retention window (`manifest install.retention_days` else config default), and leaves history intact. Reinstall over an uninstalled row revives it. `reverify` is the operator exit from quarantine (restore bytes → re-check → `disabled`, never auto-enable).

**Files:**
- Modify: `src/Service/Packages/PackageLifecycleService.php` (add `uninstall`, `export`, `reverify` + private `buildExport`)
- Test: `tests/Integration/Service/PackageUninstallTest.php`

**Interfaces:**
- Produces: `uninstall(User, string $currentPassword, int $installedId): array` (returns the export payload; state → `uninstalled`, `retain_until` set); `export(User, int $installedId): array` (payload also persisted to `export_json`/`exported_at`; history `export`); `reverify(User, int $installedId): bool` (quarantined-only; pass → state `disabled`, health `ok`; fail → stays quarantined). Export payload (locked):

```
{format:'rb-install-export.v1', exported_at, package:{uid,name,type,trust_class},
 install:{version,digest,state,health,pinned,update_policy,installed_at,uninstalled_at,retain_until},
 provenance:{registry_source_id,publisher_uid,review:{decision,digest,decided_at}|null},
 permissions:[…forInstall rows], history:[…forInstall rows], settings:null}
```

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageHistoryRepository;
use App\Security\Packages\PackagePolicyException;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\TestCase;

/**
 * Builds services exactly like PackageLifecycleServiceTest (same helpers);
 * extract a shared trait tests/Support/Phase5/BuildsPackageServices.php in
 * this step if the duplication annoys — keep the public behavior identical.
 */
final class PackageUninstallTest extends TestCase
{
    // …same setUp/service()/assertPolicyRefusal helpers as PackageLifecycleServiceTest
    // (fixture seeded with artifacts; admin with password123; $this->store; $this->seeded)…

    public function test_uninstall_disables_first_exports_and_sets_retention_from_the_manifest(): void
    {
        $withRetention = RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '2.0.0',
            'manifest' => ['install' => ['retention_days' => 14]],
        ], $this->artifactDir);

        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id'], (int) $withRetention['release_id']);
        $service->consent($this->admin, 'password123', $installedId);
        $service->enable($this->admin, 'password123', $installedId);

        $export = $service->uninstall($this->admin, 'password123', $installedId);

        self::assertSame('rb-install-export.v1', $export['format']);
        self::assertSame('acme/midnight-theme', $export['package']['uid']);
        self::assertNull($export['settings']);
        self::assertNotEmpty($export['permissions']);

        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('uninstalled', $row['state']);
        self::assertNotNull($row['export_json']);
        self::assertNotNull($row['uninstalled_at']);
        $expected = (new \DateTimeImmutable($row['uninstalled_at'], new \DateTimeZone('UTC')))->modify('+14 days');
        self::assertEqualsWithDelta($expected->getTimestamp(), (new \DateTimeImmutable($row['retain_until'], new \DateTimeZone('UTC')))->getTimestamp(), 5.0);

        $events = array_column((new PackageHistoryRepository($this->db))->forInstall($installedId), 'event');
        self::assertSame(['uninstall', 'disable'], array_slice($events, 0, 2), 'execution is disabled before removal');

        self::assertNotEmpty((new InstalledPackagePermissionRepository($this->db))->forInstall($installedId),
            'grant snapshot is retained through the retention window');
    }

    public function test_export_is_available_standalone_and_records_history(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $export = $service->export($this->admin, $installedId);

        self::assertSame('rb-install-export.v1', $export['format']);
        self::assertSame('approved', $export['provenance']['review']['decision']);
        self::assertNotNull((new InstalledPackageRepository($this->db))->find($installedId)['exported_at']);
        self::assertSame('export', (new PackageHistoryRepository($this->db))->forInstall($installedId, 1)[0]['event']);
    }

    public function test_uninstall_twice_refuses_and_reinstall_revives_the_row(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->uninstall($this->admin, 'password123', $installedId);
        $this->assertPolicyRefusal('invalid_state', fn () => $service->uninstall($this->admin, 'password123', $installedId));

        $revivedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        self::assertSame($installedId, $revivedId, 'UNIQUE(package_id) forces row revival');
        $row = (new InstalledPackageRepository($this->db))->find($revivedId);
        self::assertSame('installed', $row['state']);
        self::assertNull($row['export_json']);
    }

    public function test_reverify_restores_a_quarantined_install_to_disabled_only_when_bytes_match(): void
    {
        $service = $this->service();
        $installedId = $service->install($this->admin, 'password123', (int) $this->seeded['package_id']);
        $service->consent($this->admin, 'password123', $installedId);

        $path = $this->store->pathFor($this->seeded['release_digest']);
        $original = file_get_contents($path);
        file_put_contents($path, 'tampered');
        $this->assertPolicyRefusal('artifact_tampered', fn () => $service->enable($this->admin, 'password123', $installedId));

        self::assertFalse($service->reverify($this->admin, $installedId), 'bytes still wrong → stays quarantined');
        self::assertSame('quarantined', (new InstalledPackageRepository($this->db))->find($installedId)['state']);

        file_put_contents($path, $original);
        self::assertTrue($service->reverify($this->admin, $installedId));
        $row = (new InstalledPackageRepository($this->db))->find($installedId);
        self::assertSame('disabled', $row['state'], 'never auto-enables');
        self::assertSame('ok', $row['health']);
        self::assertNull($row['quarantine_reason']);

        $this->assertPolicyRefusal('not_quarantined', fn () => $service->reverify($this->admin, $installedId));
    }
}
```

(Fill the elided helpers by copying `PackageLifecycleServiceTest`'s `setUp`/`tearDown`/`service()`/`assertPolicyRefusal` verbatim, or extract the shared trait — either is acceptable; the trait is preferred if it keeps both test files under strict-PHPUnit rules.)

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageUninstallTest.php`
Expected: FAIL — methods missing.

- [ ] **Step 3: Implement `uninstall`, `export`, `reverify` on `PackageLifecycleService`**

```php
/** §9 Uninstall/data: disable first, auto-export, observe retention; history survives (no FK). */
public function uninstall(User $admin, string $currentPassword, int $installedId): array
{
    $this->writeGate->assertCanWrite($admin);
    $this->reauth->requirePassword($admin, $currentPassword);
    $install = $this->requireInstall($installedId);
    $this->assertState($install, ['installed', 'enabled', 'disabled', 'quarantined']);
    $package = $this->packages->find((int) $install['package_id']);
    $retentionDays = $this->retentionDaysFor($install);

    $export = $this->db->transaction(function () use ($install, $installedId, $package, $admin, $retentionDays): array {
        if ((string) $install['state'] === 'enabled') {
            $this->installs->setState($installedId, 'disabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'disable',
                'actor_id' => $admin->id(),
                'detail' => 'uninstall disables execution first',
            ]);
        }
        $export = $this->buildExport($install, $package);
        $this->installs->storeExport($installedId, json_encode($export, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $retainUntil = gmdate('Y-m-d H:i:s', time() + $retentionDays * 86_400);
        $this->installs->markUninstalled($installedId, $retainUntil);
        $this->history->record([
            'package_id' => (int) $install['package_id'],
            'installed_package_id' => $installedId,
            'event' => 'uninstall',
            'actor_id' => $admin->id(),
            'prior_version' => $this->versionOf($install),
            'prior_digest' => (string) $install['digest'],
            'permission_snapshot_json' => json_encode($this->permissions->forInstall($installedId), JSON_UNESCAPED_SLASHES),
            'detail' => 'retain until ' . $retainUntil,
        ]);
        $this->transparency->record([
            'package_uid' => (string) $package['package_uid'],
            'version' => $this->versionOf($install),
            'digest' => (string) $install['digest'],
            'event' => 'uninstall',
            'source' => 'local',
            'actor_id' => $admin->id(),
        ]);
        $this->audit->log([
            'actor_id' => $admin->id(), 'action' => 'package_uninstall',
            'target_type' => 'package', 'target_id' => (int) $install['package_id'],
            'after' => ['retain_until' => $retainUntil],
        ]);
        return $export;
    });
    $this->telemetry?->emit('package.lifecycle', [
        'action' => 'uninstall', 'package' => (string) ($package['package_uid'] ?? ''),
    ]);
    return $export;
}

/** Point-in-time export; also persisted so the retention window keeps a copy (§8.2 #3). */
public function export(User $admin, int $installedId): array
{
    $this->writeGate->assertCanWrite($admin);
    $install = $this->requireInstall($installedId);
    $package = $this->packages->find((int) $install['package_id']);
    $export = $this->buildExport($install, $package);
    $this->db->transaction(function () use ($install, $installedId, $package, $admin, $export): void {
        $this->installs->storeExport($installedId, json_encode($export, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->history->record([
            'package_id' => (int) $install['package_id'],
            'installed_package_id' => $installedId,
            'event' => 'export',
            'actor_id' => $admin->id(),
        ]);
        $this->audit->log([
            'actor_id' => $admin->id(), 'action' => 'package_export',
            'target_type' => 'package', 'target_id' => (int) $install['package_id'],
        ]);
    });
    return $export;
}

/** Operator exit from quarantine: bytes restored → disabled (never auto-enabled). */
public function reverify(User $admin, int $installedId): bool
{
    $this->writeGate->assertCanWrite($admin);
    $install = $this->requireInstall($installedId);
    if ((string) $install['state'] !== 'quarantined') {
        throw new PackagePolicyException('not_quarantined', 'Only quarantined installations can be re-verified.');
    }
    $package = $this->packages->find((int) $install['package_id']);
    if (!$this->artifacts->verify((string) $install['digest'])) {
        $this->installs->setHealth($installedId, 'failed', (string) $install['quarantine_reason']);
        return false;
    }
    $this->db->transaction(function () use ($install, $installedId, $admin): void {
        $this->installs->setState($installedId, 'disabled');
        $this->installs->setHealth($installedId, 'ok', null);
        $this->history->record([
            'package_id' => (int) $install['package_id'],
            'installed_package_id' => $installedId,
            'event' => 'health',
            'actor_id' => $admin->id(),
            'detail' => 'reverified: digest matches again',
        ]);
        $this->audit->log([
            'actor_id' => $admin->id(), 'action' => 'package_reverify',
            'target_type' => 'package', 'target_id' => (int) $install['package_id'],
        ]);
    });
    $this->telemetry?->emit('package.lifecycle', [
        'action' => 'reverify', 'package' => (string) ($package['package_uid'] ?? ''),
    ]);
    return true;
}

/** Manifest install.retention_days wins; config default otherwise. */
private function retentionDaysFor(array $install): int
{
    $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
    $manifest = $release !== null && $release['manifest_json'] !== null
        ? json_decode((string) $release['manifest_json'], true)
        : null;
    $days = is_array($manifest) && is_int($manifest['install']['retention_days'] ?? null)
        ? $manifest['install']['retention_days']
        : $this->retentionDays;
    return max(1, min(365, $days));
}

private function buildExport(array $install, array $package): array
{
    $installedId = (int) $install['id'];
    $registry = $install['source_registry_id'] !== null ? $this->registries->find((int) $install['source_registry_id']) : null;
    $review = null;
    $decision = $this->reviewDecisions->latestForDigest((int) $install['package_id'], (string) $install['digest']);
    if ($decision !== null) {
        $review = ['decision' => (string) $decision['decision'], 'digest' => (string) $decision['digest'], 'decided_at' => $decision['decided_at']];
    }
    return [
        'format' => 'rb-install-export.v1',
        'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'package' => [
            'uid' => (string) $package['package_uid'], 'name' => (string) $package['name'],
            'type' => (string) $package['type'], 'trust_class' => (string) $install['trust_class'],
        ],
        'install' => [
            'version' => $this->versionOf($install), 'digest' => (string) $install['digest'],
            'state' => (string) $install['state'], 'health' => (string) $install['health'],
            'pinned' => (int) $install['pinned'] === 1, 'update_policy' => (string) $install['update_policy'],
            'installed_at' => (string) $install['installed_at'],
            'uninstalled_at' => $install['uninstalled_at'], 'retain_until' => $install['retain_until'],
        ],
        'provenance' => [
            'registry_source_id' => $registry !== null ? (string) $registry['source_id'] : null,
            'publisher_uid' => $this->publisherUid($package),
            'review' => $review,
        ],
        'permissions' => $this->permissions->forInstall($installedId),
        'history' => $this->history->forInstall($installedId, 100),
        'settings' => null, // Inc 5 (P5-04) owns settings values
    ];
}

private function publisherUid(array $package): ?string
{
    if ($package['publisher_id'] === null) {
        return null;
    }
    $stmt = $this->db->pdo()->prepare('SELECT publisher_uid FROM package_publishers WHERE id = :id');
    $stmt->execute(['id' => (int) $package['publisher_id']]);
    $uid = $stmt->fetchColumn();
    return $uid === false ? null : (string) $uid;
}
```

(`$this->reviewDecisions` is the constructor dependency Task 9 already declared.)

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Service/PackageUninstallTest.php tests/Integration/Service/PackageLifecycleServiceTest.php tests/Integration/Service/PackageUpdateServiceTest.php`
Expected: PASS (all lifecycle suites green).

- [ ] **Step 5: Commit**

```bash
git add src/Service/Packages/PackageLifecycleService.php tests/Integration/Service/PackageUninstallTest.php tests/Integration/Service/PackageLifecycleServiceTest.php tests/Integration/Service/PackageUpdateServiceTest.php
git commit -m "feat(phase5): uninstall with disable-first + export + retention, quarantine reverify (Inc 3)"
```

---
### Task 12: `PackageHealthService` + `worker:packages` + enforcement wiring + `RepairService` reconcile

Three jobs in one sweep, completing the Inc 2 advisory-ladder handoff ("the installed-package disable lands with the install path in Inc 3"):
1. **Tamper health** (TM-SC-07, §9 Tampered-local-files): re-hash every installed artifact; mismatch → quarantine.
2. **Local emergency-disable enforcement**: `force_disable`/`revoke` advisories and local blocklist hits disable enabled installs and cancel matching staged targets — run by the worker, and **inline** after admin/worker advisory ingest + blocklist add (via a nullable collaborator appended to the two Inc 2 services, the established `?->` pattern).
3. **Retention purge**: uninstalled rows past `retain_until` lose their permission rows, row, and artifact (history survives).

`RepairService::repairInstalledPackageStates()` is the DB-only mirror of (2) with identical match semantics.

**Files:**
- Create: `src/Service/Packages/PackageHealthService.php`
- Create: `src/Worker/PackageHealthWorker.php`
- Modify: `src/Service/Registry/RegistryAdvisoryService.php` (append ctor param `?PackageHealthService $enforcement = null`; call `$this->enforcement?->enforcePolicy();` at the end of `ingest()` after `evaluatePackage`)
- Modify: `src/Service/Registry/LocalBlocklistService.php` (same appended param; call at the end of `block()`)
- Modify: `src/Core/App.php` (container: bind `PackageArtifactStore` — mirror the `AttachmentService` binding's config access for `packages.storage_path` — and `PackageHealthService`; update the `RegistryAdvisoryService`/`LocalBlocklistService` bindings to pass it)
- Modify: `bin/console` (new `worker:packages` case + help line; pass a hand-built `PackageHealthService` into the `worker:registry-refresh` case's `RegistryAdvisoryService` so worker-ingested advisories enforce)
- Modify: `src/Service/RepairService.php` (add `repairInstalledPackageStates(): int`; add `'installed_packages'` key to `repairAll()` after `package_advisory`)
- Test: `tests/Integration/Worker/PackageHealthWorkerTest.php`, `tests/Integration/Service/RepairInstalledPackagesTest.php`

**Interfaces:**
- Produces: the locked `PackageHealthService` / `PackageHealthWorker` APIs. Match rule (locked, shared verbatim with the repair mirror): an install is *security-disabled* when `state='enabled'` AND (blocklist hits `(digest, package_uid)` OR an advisory with action ∈ `force_disable|revoke` matches — digest via `hash_equals`, else version range via `RegistryAdvisoryService::affectsVersion`, else no-digest-no-range = affected). A *staged target* is cancelled when its staged digest/release matches a blocklist hit or an advisory with action ∈ `block_new|force_disable|revoke`. Tamper checks run for states `installed|enabled|disabled` only (quarantined rows are the operator's `reverify` to fix; §9: quarantine is sticky).
- Telemetry: one `package.health` heartbeat per `checkAll()` with `{checked, quarantined, disabled, purged, updates}`; `updates` counts `update_policy='notify'` installs whose package `latest_release_id` differs from the active release.

- [ ] **Step 1: Write the failing worker test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Service\Packages\PackageHealthService;
use App\Worker\PackageHealthWorker;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\TestCase;

/**
 * Shares the service-construction helpers with PackageLifecycleServiceTest
 * (fixture + artifacts + admin + lifecycle()) — reuse the extracted trait or
 * copy the same setUp. New pieces shown below.
 */
final class PackageHealthWorkerTest extends TestCase
{
    // …setUp seeds RegistryFixtures::seed($this->db, $this->root, $this->artifactDir), builds $this->admin,
    //  installs + consents + enables one package via lifecycle(): $this->installedId…

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
            $this->store,
            new ModerationLogRepository($this->db),
        );
    }

    public function test_dark_flag_makes_the_worker_a_pure_noop(): void
    {
        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered');
        $stats = (new PackageHealthWorker($this->health(), false))->run();

        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, $stats['checked']);
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
    }

    public function test_tampered_bytes_quarantine_the_install(): void
    {
        // TM-SC-07: on-disk byte change → digest/health failure → quarantine; not reported as reviewed-good.
        file_put_contents($this->store->pathFor($this->seeded['release_digest']), 'tampered');
        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['quarantined']);
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('quarantined', $row['state']);
        self::assertSame('failed', $row['health']);
        self::assertNotNull($row['last_health_check_at']);

        $history = (new PackageHistoryRepository($this->db))->forInstall($this->installedId, 1)[0];
        self::assertSame('quarantine', $history['event']);
        self::assertNull($history['actor_id'], 'worker actions carry no actor');
        $stmt = $this->db->pdo()->prepare(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'package_quarantine' AND target_id = :pid AND actor_id IS NULL"
        );
        $stmt->execute(['pid' => $this->seeded['package_id']]);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_healthy_bytes_mark_health_ok_and_touch_the_check_timestamp(): void
    {
        $stats = (new PackageHealthWorker($this->health(), true))->run();
        self::assertSame(1, $stats['checked']);
        self::assertSame(0, $stats['quarantined']);
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('ok', $row['health']);
        self::assertNotNull($row['last_health_check_at']);
    }

    public function test_force_disable_advisory_disables_the_enabled_install(): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-FD-1', 'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'], 'affected_version_range' => '<=1.0.0',
            'affected_digest' => null, 'severity' => 'critical', 'action' => 'force_disable',
            'summary' => 'incident', 'signed_evidence' => null, 'issued_at' => '2026-07-01 00:00:00',
        ]);
        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['disabled']);
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        $transparency = (new PackageTransparencyLogRepository($this->db))->forPackageUid('acme/midnight-theme');
        self::assertSame('force_disable', $transparency[0]['event']);
        // idempotent: a second sweep changes nothing
        self::assertSame(0, (new PackageHealthWorker($this->health(), true))->run()['disabled']);
    }

    public function test_blocklist_hit_disables_and_cancels_a_matching_staged_target(): void
    {
        (new InstalledPackageRepository($this->db))->stageRelease($this->installedId, (int) $this->seeded['release_id'], $this->seeded['release_digest']);
        (new LocalPackageBlockRepository($this->db))->add($this->seeded['release_digest'], null, 'incident', null);

        $stats = (new PackageHealthWorker($this->health(), true))->run();
        $row = (new InstalledPackageRepository($this->db))->find($this->installedId);
        self::assertSame('disabled', $row['state']);
        self::assertNull($row['staged_release_id'], 'blocked staged target is cancelled');
        self::assertGreaterThanOrEqual(1, $stats['disabled']);
    }

    public function test_retention_purge_removes_rows_permissions_and_artifact(): void
    {
        $this->lifecycle()->uninstall($this->admin, 'password123', $this->installedId);
        $this->db->pdo()->prepare("UPDATE installed_packages SET retain_until = '2020-01-01 00:00:00' WHERE id = :id")
            ->execute(['id' => $this->installedId]);

        $stats = (new PackageHealthWorker($this->health(), true))->run();

        self::assertSame(1, $stats['purged']);
        self::assertNull((new InstalledPackageRepository($this->db))->find($this->installedId));
        self::assertSame([], (new InstalledPackagePermissionRepository($this->db))->forInstall($this->installedId));
        self::assertFalse($this->store->has($this->seeded['release_digest']), 'artifact removed after retention');
        $events = array_column((new PackageHistoryRepository($this->db))->forPackage((int) $this->seeded['package_id']), 'event');
        self::assertContains('purge', $events, 'history survives the purge');
    }

    public function test_notify_policy_counts_available_updates(): void
    {
        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, ['version' => '1.1.0'], $this->artifactDir);
        $this->lifecycle()->setUpdatePolicy($this->admin, $this->installedId, 'notify');

        self::assertSame(1, (new PackageHealthWorker($this->health(), true))->run()['updates']);
    }

    public function test_admin_advisory_ingest_and_blocklist_add_enforce_inline(): void
    {
        // The Inc 2 services gain the nullable enforcement collaborator; when wired, force_disable
        // ingest and blocklist add disable the install without waiting for the worker.
        $advisories = $this->advisoryServiceWithEnforcement(); // RegistryAdvisoryService built as in RegistryAdvisoryServiceTest + $this->health() appended
        $minted = $this->root->mintAdvisory(['action' => 'force_disable', 'affected_version_range' => '<=1.0.0']);
        $advisories->ingest((int) $this->seeded['registry_id'], $minted['json'], $minted['signature'], $minted['key_id'], null, null);
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);

        $this->lifecycle()->enable($this->admin, 'password123', $this->installedId); // FAILS: advisory now blocks enable
    }
}
```

The final test's last line is intentionally wrong as written — replace it while writing the test with the correct expectation: re-enabling must now refuse (`assertPolicyRefusal('advisory_blocked', …)` since `force_disable` blocks enable via the gate). Build `advisoryServiceWithEnforcement()` exactly like `tests/Integration/Service/RegistryAdvisoryServiceTest.php` constructs its service, appending `$this->health()` as the new final constructor argument.

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Worker/PackageHealthWorkerTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement `PackageHealthService`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Service\Registry\RegistryAdvisoryService;

/**
 * Installed-package health + local security enforcement (P5-02 SP5 +
 * P5-07-A): digest/tamper checks with quarantine (TM-SC-07), the
 * force_disable/revoke + blocklist disable sweep the Inc 2 advisory ladder
 * deferred to this increment, staged-target cancellation, and retention
 * purge. Every state change is recorded in history + transparency + audit
 * with a NULL actor. RepairService::repairInstalledPackageStates() mirrors
 * the enforcement WHERE semantics — keep them identical.
 */
final class PackageHealthService
{
    public function __construct(
        private Database $db,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageAdvisoryRepository $advisories,
        private LocalPackageBlockRepository $blocks,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private PackageArtifactStore $artifacts,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{checked:int,quarantined:int,disabled:int,purged:int,updates:int} */
    public function checkAll(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $checked = 0;
        $quarantined = 0;
        $updates = 0;

        foreach ($this->installs->activeWithContext() as $install) {
            $state = (string) $install['state'];
            if (in_array($state, ['installed', 'enabled', 'disabled'], true)) {
                $checked++;
                if ($this->artifacts->verify((string) $install['digest'])) {
                    $this->installs->setHealth((int) $install['id'], 'ok', null);
                } else {
                    $this->quarantine($install, 'digest mismatch or missing artifact');
                    $quarantined++;
                }
            }
            if ((string) $install['update_policy'] === 'notify' && $this->updateAvailable($install)) {
                $updates++;
            }
        }

        $disabled = $this->enforcePolicy();
        $purged = $this->purgeExpired($now);

        $stats = ['checked' => $checked, 'quarantined' => $quarantined, 'disabled' => $disabled, 'purged' => $purged, 'updates' => $updates];
        $this->telemetry?->emit('package.health', $stats);
        return $stats;
    }

    /**
     * Disable enabled installs matched by force_disable/revoke advisories or
     * the local blocklist; cancel staged targets matched by block_new too.
     * Returns the number of state changes. Idempotent.
     */
    public function enforcePolicy(): int
    {
        $changed = 0;
        foreach ($this->installs->activeWithContext() as $install) {
            $version = $install['release_version'] !== null ? (string) $install['release_version'] : '';
            if ((string) $install['state'] === 'enabled') {
                $reason = $this->blockingReason((int) $install['package_id'], (string) $install['package_uid'],
                    (string) $install['digest'], $version, ['force_disable', 'revoke']);
                if ($reason !== null) {
                    $this->securityDisable($install, $reason);
                    $changed++;
                }
            }
            if ($install['staged_digest'] !== null) {
                $stagedVersion = $this->stagedVersion($install);
                $reason = $this->blockingReason((int) $install['package_id'], (string) $install['package_uid'],
                    (string) $install['staged_digest'], $stagedVersion, ['block_new', 'force_disable', 'revoke']);
                if ($reason !== null) {
                    $this->cancelStage($install, $reason);
                    $changed++;
                }
            }
        }
        return $changed;
    }

    /** Delete uninstalled rows past their retention window; history rows survive. */
    public function purgeExpired(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $purged = 0;
        foreach ($this->installs->purgeable($now->format('Y-m-d H:i:s')) as $install) {
            $digest = (string) $install['digest'];
            $this->db->transaction(function () use ($install): void {
                $this->permissions->deleteFor((int) $install['id']);
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => (int) $install['id'],
                    'event' => 'purge',
                    'detail' => 'retention window lapsed',
                ]);
                $this->installs->delete((int) $install['id']);
            });
            $this->artifacts->remove($digest);
            $purged++;
        }
        return $purged;
    }

    // ── internals (match rule shared verbatim with RepairService::repairInstalledPackageStates) ──

    /** @param list<string> $actions */
    private function blockingReason(int $packageId, string $packageUid, string $digest, string $version, array $actions): ?string
    {
        if ($this->blocks->isBlocked($digest, $packageUid)) {
            return 'local blocklist';
        }
        foreach ($this->advisories->forPackage($packageId) as $advisory) {
            if (!in_array((string) $advisory['action'], $actions, true)) {
                continue;
            }
            $matches = ($advisory['affected_digest'] !== null && $advisory['affected_digest'] !== '')
                ? hash_equals((string) $advisory['affected_digest'], $digest)
                : (($advisory['affected_version_range'] === null || $advisory['affected_version_range'] === '')
                    ? true
                    : ($version !== '' && RegistryAdvisoryService::affectsVersion((string) $advisory['affected_version_range'], $version)));
            if ($matches) {
                return 'advisory ' . (string) $advisory['advisory_uid'] . ' (' . (string) $advisory['action'] . ')';
            }
        }
        return null;
    }

    private function securityDisable(array $install, string $reason): void
    {
        $this->db->transaction(function () use ($install, $reason): void {
            $this->installs->setState((int) $install['id'], 'disabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'disable',
                'new_version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $install['package_uid'],
                'version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'digest' => (string) $install['digest'],
                'event' => 'force_disable',
                'source' => 'local',
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => null,
                'action' => 'package_force_disable',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'force_disable', 'package' => (string) $install['package_uid'], 'reason' => $reason,
        ]);
    }

    private function cancelStage(array $install, string $reason): void
    {
        $this->db->transaction(function () use ($install, $reason): void {
            $this->installs->stageRelease((int) $install['id'], null, null);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'update_staged',
                'detail' => 'cancelled: ' . $reason,
            ]);
        });
    }

    private function quarantine(array $install, string $reason): void
    {
        $this->db->transaction(function () use ($install, $reason): void {
            $this->installs->setState((int) $install['id'], 'quarantined');
            $this->installs->setHealth((int) $install['id'], 'failed', $reason);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'quarantine',
                'new_version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $install['package_uid'],
                'version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'digest' => (string) $install['digest'],
                'event' => 'quarantine',
                'source' => 'local',
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => null,
                'action' => 'package_quarantine',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);
        });
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'quarantine', 'package' => (string) $install['package_uid'], 'reason' => $reason,
        ]);
    }

    private function updateAvailable(array $install): bool
    {
        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null || $package['latest_release_id'] === null) {
            return false;
        }
        $latest = $this->releases->find((int) $package['latest_release_id']);
        return $latest !== null && (string) $latest['digest'] !== (string) $install['digest'];
    }

    private function stagedVersion(array $install): string
    {
        if ($install['staged_release_id'] === null) {
            return '';
        }
        $staged = $this->releases->find((int) $install['staged_release_id']);
        return $staged !== null ? (string) $staged['version'] : '';
    }
}
```

- [ ] **Step 4: Implement `PackageHealthWorker` + console case + service wiring**

`src/Worker/PackageHealthWorker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Telemetry;
use App\Service\Packages\PackageHealthService;

/** Cron sweep for installed-package tamper/enforcement/retention. Pure no-op while package_registry is dark. */
final class PackageHealthWorker
{
    public function __construct(
        private PackageHealthService $health,
        private bool $enabled,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{checked:int,quarantined:int,disabled:int,purged:int,updates:int,skipped:int} */
    public function run(): array
    {
        if (!$this->enabled) {
            return ['checked' => 0, 'quarantined' => 0, 'disabled' => 0, 'purged' => 0, 'updates' => 0, 'skipped' => 1];
        }
        return $this->health->checkAll() + ['skipped' => 0];
    }
}
```

`bin/console` — add the case (mirror the `worker:registry-refresh` construction style; reuse its `$db`/`$config`/`$telemetry` setup):

```php
case 'worker:packages':
    $db = $database();
    $telemetry = new Telemetry($config);
    $health = new PackageHealthService(
        $db,
        new InstalledPackageRepository($db),
        new InstalledPackagePermissionRepository($db),
        new PackageRepository($db),
        new PackageReleaseRepository($db),
        new PackageAdvisoryRepository($db),
        new LocalPackageBlockRepository($db),
        new PackageHistoryRepository($db),
        new PackageTransparencyLogRepository($db),
        new PackageArtifactStore((string) $config->get('packages.storage_path')),
        new ModerationLogRepository($db),
        $telemetry,
    );
    $stats = (new PackageHealthWorker(
        $health,
        (new FeatureFlags(new SettingRepository($db)))->enabled('package_registry'),
        $telemetry,
    ))->run();
    $log(sprintf(
        'packages: checked=%d quarantined=%d disabled=%d purged=%d updates=%d skipped=%d',
        $stats['checked'], $stats['quarantined'], $stats['disabled'], $stats['purged'], $stats['updates'], $stats['skipped']
    ));
    break;
```

Help line (next to `worker:registry-refresh`): `  worker:packages       Verify installed package digests, enforce advisories/blocklist, purge lapsed uninstalls`.

Also in the existing `worker:registry-refresh` case: construct the same `PackageHealthService` and pass it as the new final argument of the hand-built `RegistryAdvisoryService`, so a `force_disable` advisory fetched by refresh disables installs in the same run.

Service wiring — `RegistryAdvisoryService`: append `private ?PackageHealthService $enforcement = null` to the constructor; at the end of `ingest()` (after `evaluatePackage`, before returning) add `$this->enforcement?->enforcePolicy();`. `LocalBlocklistService`: same appended param; call at the end of `block()` after the audit write. Container (`App::buildContainer()`): bind `PackageArtifactStore` + `PackageHealthService`, then append `$c->get(PackageHealthService::class)` to the `RegistryAdvisoryService` and `LocalBlocklistService` bindings:

```php
$c->bind(PackageArtifactStore::class, fn (Container $c) => new PackageArtifactStore(
    (string) $c->get(Config::class)->get('packages.storage_path'),   // mirror AttachmentService's config accessor exactly
));
$c->bind(PackageHealthService::class, fn (Container $c) => new PackageHealthService(
    $c->get(Database::class),
    $c->get(InstalledPackageRepository::class),
    $c->get(InstalledPackagePermissionRepository::class),
    $c->get(PackageRepository::class),
    $c->get(PackageReleaseRepository::class),
    $c->get(PackageAdvisoryRepository::class),
    $c->get(LocalPackageBlockRepository::class),
    $c->get(PackageHistoryRepository::class),
    $c->get(PackageTransparencyLogRepository::class),
    $c->get(PackageArtifactStore::class),
    $c->get(ModerationLogRepository::class),
    $c->get(Telemetry::class),
));
```

(The five Task-5 repository bindings land here too if Task 13 hasn't yet — each is the one-line `fn (Container $c) => new X($c->get(Database::class))` pattern.)

- [ ] **Step 5: Add the repair mirror + its test**

`tests/Integration/Service/RepairInstalledPackagesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\InstalledPackageRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Service\RepairService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\TestCase;

final class RepairInstalledPackagesTest extends TestCase
{
    // …same install-and-enable setUp as PackageHealthWorkerTest (trait/copy)…

    public function test_repair_disables_enabled_installs_matched_by_revoking_advisories(): void
    {
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-REPAIR-1', 'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'], 'affected_version_range' => null,
            'affected_digest' => $this->seeded['release_digest'], 'severity' => 'critical',
            'action' => 'revoke', 'summary' => 'compromised', 'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);

        $repair = new RepairService($this->db);
        self::assertSame(1, $repair->repairInstalledPackageStates());
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($this->installedId)['state']);
        self::assertSame(0, $repair->repairInstalledPackageStates(), 'idempotent');

        self::assertArrayHasKey('installed_packages', $repair->repairAll());
    }
}
```

`src/Service/RepairService.php` — add (following the file's existing raw-SQL style; reuse `RegistryAdvisoryService::affectsVersion` exactly as `repairPackageAdvisoryStatuses` already does):

```php
/**
 * DB-only mirror of PackageHealthService::enforcePolicy() — identical match
 * semantics (blocklist hit on digest/uid, or advisory action
 * force_disable/revoke matching digest via hash_equals / version range /
 * no-constraint = affected): enabled installs are disabled, matching staged
 * targets (block_new included) are cleared. Filesystem tamper checks stay in
 * worker:packages (repair never touches disk). Writes a history row per fix
 * (event 'disable' / 'update_staged', detail 'repair reconcile', actor NULL)
 * so recovered state is auditable; idempotent because fixed rows no longer match.
 */
public function repairInstalledPackageStates(): int
{
    $fixed = 0;
    $installs = $this->db->pdo()->query(
        "SELECT ip.id, ip.package_id, ip.state, ip.digest, ip.staged_release_id, ip.staged_digest,
                p.package_uid, r.version AS release_version, sr.version AS staged_version
         FROM installed_packages ip
         JOIN packages p ON p.id = ip.package_id
         LEFT JOIN package_releases r ON r.id = ip.release_id
         LEFT JOIN package_releases sr ON sr.id = ip.staged_release_id
         WHERE ip.state <> 'uninstalled'"
    )->fetchAll();

    $isBlocked = function (string $digest, string $uid): bool {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM local_package_blocks WHERE (digest IS NOT NULL AND digest = :d) OR (package_uid IS NOT NULL AND package_uid = :u)'
        );
        $stmt->execute(['d' => $digest, 'u' => $uid]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $advisoriesFor = function (int $packageId): array {
        $stmt = $this->db->pdo()->prepare('SELECT advisory_uid, action, affected_digest, affected_version_range FROM package_advisories WHERE package_id = :pid');
        $stmt->execute(['pid' => $packageId]);
        return $stmt->fetchAll();
    };
    $matchReason = function (array $advisories, array $actions, string $digest, string $version, string $uid) use ($isBlocked): ?string {
        if ($isBlocked($digest, $uid)) {
            return 'local blocklist';
        }
        foreach ($advisories as $advisory) {
            if (!in_array((string) $advisory['action'], $actions, true)) {
                continue;
            }
            $matches = ($advisory['affected_digest'] !== null && $advisory['affected_digest'] !== '')
                ? hash_equals((string) $advisory['affected_digest'], $digest)
                : (($advisory['affected_version_range'] === null || $advisory['affected_version_range'] === '')
                    ? true
                    : ($version !== '' && \App\Service\Registry\RegistryAdvisoryService::affectsVersion((string) $advisory['affected_version_range'], $version)));
            if ($matches) {
                return 'advisory ' . (string) $advisory['advisory_uid'] . ' (' . (string) $advisory['action'] . ')';
            }
        }
        return null;
    };

    $disable = $this->db->pdo()->prepare("UPDATE installed_packages SET state = 'disabled' WHERE id = :id");
    $clearStage = $this->db->pdo()->prepare('UPDATE installed_packages SET staged_release_id = NULL, staged_digest = NULL WHERE id = :id');
    $historyDisable = $this->db->pdo()->prepare(
        "INSERT INTO package_history (package_id, installed_package_id, event, new_digest, detail)
         VALUES (:pid, :iid, 'disable', :digest, :detail)"
    );
    $historyStage = $this->db->pdo()->prepare(
        "INSERT INTO package_history (package_id, installed_package_id, event, detail)
         VALUES (:pid, :iid, 'update_staged', :detail)"
    );

    foreach ($installs as $install) {
        $advisories = $advisoriesFor((int) $install['package_id']);
        if ((string) $install['state'] === 'enabled') {
            $reason = $matchReason($advisories, ['force_disable', 'revoke'], (string) $install['digest'],
                (string) ($install['release_version'] ?? ''), (string) $install['package_uid']);
            if ($reason !== null) {
                $disable->execute(['id' => (int) $install['id']]);
                $historyDisable->execute([
                    'pid' => (int) $install['package_id'], 'iid' => (int) $install['id'],
                    'digest' => (string) $install['digest'], 'detail' => 'repair reconcile: ' . $reason,
                ]);
                $fixed++;
            }
        }
        if ($install['staged_digest'] !== null) {
            $reason = $matchReason($advisories, ['block_new', 'force_disable', 'revoke'], (string) $install['staged_digest'],
                (string) ($install['staged_version'] ?? ''), (string) $install['package_uid']);
            if ($reason !== null) {
                $clearStage->execute(['id' => (int) $install['id']]);
                $historyStage->execute([
                    'pid' => (int) $install['package_id'], 'iid' => (int) $install['id'],
                    'detail' => 'cancelled: repair reconcile: ' . $reason,
                ]);
                $fixed++;
            }
        }
    }
    return $fixed;
}
```

And in `repairAll()`, after the `package_advisory` entry: `'installed_packages' => $this->repairInstalledPackageStates(),`.

- [ ] **Step 6: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Worker/PackageHealthWorkerTest.php tests/Integration/Service/RepairInstalledPackagesTest.php tests/Integration/Service/RegistryAdvisoryServiceTest.php`
Expected: PASS (8 worker tests + repair test; the Inc 2 advisory suite stays green — its constructions pass `null` implicitly for the new param).

Then smoke the console paths against the test DB:

```bash
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console worker:packages
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console repair
```
Expected: `packages: checked=0 … skipped=1` (flag dark) and `repair` printing the new `installed_packages` line.

- [ ] **Step 7: Commit**

```bash
git add src/Service/Packages/PackageHealthService.php src/Worker/PackageHealthWorker.php src/Service/Registry/RegistryAdvisoryService.php src/Service/Registry/LocalBlocklistService.php src/Service/RepairService.php src/Core/App.php bin/console tests/Integration/Worker/PackageHealthWorkerTest.php tests/Integration/Service/RepairInstalledPackagesTest.php
git commit -m "feat(phase5): worker:packages - tamper quarantine, advisory/blocklist enforcement, retention purge + repair mirror (Inc 3, TM-SC-07)"
```

---
### Task 13: No-JS admin surface — controller, routes, bindings, templates, kernel + authorization evidence

The operator-facing lifecycle: plan → install → consent → enable/disable → update/rollback → uninstall/export, all plain server-rendered forms behind `requireAdmin()` + the dark-flag gate, noindexed, CSRF'd, anti-draft-loss on refusals. This task also **retires the Inc 2 "install absent" posture**: `templates/admin/packages.php` / `package_detail.php` lose the "Install does not exist yet" copy, and `AppRegistryCatalogTest`'s install-absent assertions flip to the new reality.

**Files:**
- Create: `src/Controller/AdminPackageLifecycleController.php`
- Create: `templates/admin/package_plan.php`, `templates/admin/package_consent.php`
- Modify: `templates/admin/package_detail.php` (installation panel + lifecycle forms + permissions + history), `templates/admin/packages.php` (install-state badge; drop the "Install does not exist yet" line)
- Modify: `src/Service/Registry/RegistryCatalogService.php` (append required ctor deps `InstalledPackageRepository`, `InstalledPackagePermissionRepository`, `PackageHistoryRepository`; enrich `detail()` with `installed`, `installed_permissions`, `history`)
- Modify: `src/Core/App.php` (routes + bindings: `ManifestValidator`, `PackageSecurityGate`, `RegistryTransport` → `CurlRegistryTransport`, `PackageAcquisitionService`, `PackageLifecycleService`, `PackageUpdateService`; update the `RegistryCatalogService` binding)
- Modify: `tests/Integration/Core/AppRegistryCatalogTest.php` (install-absent → install-present expectations)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (lifecycle routes dark)
- Test: `tests/Integration/Core/AppPackageLifecycleTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–12 via the container.
- Produces: the locked route table (all `{id}` = `packages.id`); `RegistryCatalogService::detail()` now returns `+ {installed:?array, installed_permissions:list, history:list}`; container bindings:

```php
$c->bind(ManifestValidator::class, fn () => new ManifestValidator());
$c->bind(PackageSecurityGate::class, fn (Container $c) => new PackageSecurityGate(
    $c->get(LocalPackageBlockRepository::class), $c->get(PackageAdvisoryRepository::class)));
$c->bind(RegistryTransport::class, fn (Container $c) => new CurlRegistryTransport(
    new EgressGuard(
        (bool) $c->get(Config::class)->get('registry.allow_http', false),
        (array) $c->get(Config::class)->get('registry.allowed_private_cidrs', []),
    ),
    (int) $c->get(Config::class)->get('registry.max_snapshot_bytes', 1_048_576),
    (int) $c->get(Config::class)->get('registry.fetch_timeout_seconds', 10),
));
$c->bind(PackageAcquisitionService::class, fn (Container $c) => new PackageAcquisitionService(
    $c->get(Database::class), $c->get(TrustChainVerifier::class), $c->get(RegistryTrustKeyRepository::class),
    $c->get(PackageReleaseRepository::class), $c->get(PackageReviewDecisionRepository::class),
    $c->get(PackageTransparencyLogRepository::class), $c->get(PackageArtifactStore::class),
    $c->get(ManifestValidator::class), $c->get(RegistryTransport::class), $c->get(Telemetry::class)));
$c->bind(PackageLifecycleService::class, fn (Container $c) => new PackageLifecycleService(
    $c->get(Database::class), $c->get(PackageRepository::class), $c->get(PackageReleaseRepository::class),
    $c->get(PackageRegistryRepository::class), $c->get(InstalledPackageRepository::class),
    $c->get(InstalledPackagePermissionRepository::class), $c->get(PackageHistoryRepository::class),
    $c->get(PackageTransparencyLogRepository::class), $c->get(PackageReviewDecisionRepository::class),
    $c->get(PackageAcquisitionService::class), $c->get(PackageSecurityGate::class),
    $c->get(PackageArtifactStore::class), $c->get(ReauthGate::class), $c->get(WriteGate::class),
    $c->get(ModerationLogRepository::class),
    (int) $c->get(Config::class)->get('packages.retention_days', 30), $c->get(Telemetry::class)));
$c->bind(PackageUpdateService::class, fn (Container $c) => new PackageUpdateService(
    $c->get(Database::class), $c->get(PackageRepository::class), $c->get(PackageReleaseRepository::class),
    $c->get(PackageRegistryRepository::class), $c->get(InstalledPackageRepository::class),
    $c->get(InstalledPackagePermissionRepository::class), $c->get(PackageHistoryRepository::class),
    $c->get(PackageTransparencyLogRepository::class), $c->get(PackageAcquisitionService::class),
    $c->get(PackageSecurityGate::class), $c->get(PackageArtifactStore::class), $c->get(ReauthGate::class),
    $c->get(WriteGate::class), $c->get(ModerationLogRepository::class), $c->get(Telemetry::class)));
```

Routes (append after the existing `/admin/packages` GETs in `registerRoutes`):

```php
$r->post('/admin/packages/{id}/plan', [AdminPackageLifecycleController::class, 'plan']);
$r->post('/admin/packages/{id}/install', [AdminPackageLifecycleController::class, 'install']);
$r->get('/admin/packages/{id}/consent', [AdminPackageLifecycleController::class, 'consentForm']);
$r->post('/admin/packages/{id}/consent', [AdminPackageLifecycleController::class, 'consent']);
$r->post('/admin/packages/{id}/enable', [AdminPackageLifecycleController::class, 'enable']);
$r->post('/admin/packages/{id}/disable', [AdminPackageLifecycleController::class, 'disable']);
$r->post('/admin/packages/{id}/pin', [AdminPackageLifecycleController::class, 'pin']);
$r->post('/admin/packages/{id}/update-policy', [AdminPackageLifecycleController::class, 'updatePolicy']);
$r->post('/admin/packages/{id}/update', [AdminPackageLifecycleController::class, 'update']);
$r->post('/admin/packages/{id}/update/cancel', [AdminPackageLifecycleController::class, 'cancelUpdate']);
$r->post('/admin/packages/{id}/rollback', [AdminPackageLifecycleController::class, 'rollback']);
$r->post('/admin/packages/{id}/uninstall', [AdminPackageLifecycleController::class, 'uninstall']);
$r->post('/admin/packages/{id}/export', [AdminPackageLifecycleController::class, 'export']);
$r->post('/admin/packages/{id}/reverify', [AdminPackageLifecycleController::class, 'reverify']);
```

- [ ] **Step 1: Write the failing kernel test**

`tests/Integration/Core/AppPackageLifecycleTest.php` — drives the real kernel; the artifact store reads the bootstrap-overridden `packages.storage_path`, so the fixture writes artifacts there:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\InstalledPackageRepository;
use App\Repository\SettingRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class AppPackageLifecycleTest extends TestCase
{
    private SigningHarness $root;
    private array $seeded;
    private string $artifactDir;
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactDir = sys_get_temp_dir() . '/rb-test-packages'; // MUST equal the tests/bootstrap.php override
        $this->root = SigningHarness::generate();
        $this->seeded = RegistryFixtures::seed($this->db, $this->root, $this->artifactDir);
        (new SettingRepository($this->db))->set('features', ['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->artifactDir . '/*.json') ?: []);
        parent::tearDown();
    }

    private function detailPath(): string
    {
        return '/admin/packages/' . $this->seeded['package_id'];
    }

    public function test_lifecycle_routes_are_dark_without_the_flag(): void
    {
        (new SettingRepository($this->db))->set('features', ['package_registry' => false]);
        $this->actingAs($this->admin);
        $this->assertStatus($this->post($this->detailPath() . '/plan', []), 404);
        $this->assertStatus($this->get($this->detailPath() . '/consent'), 404);
        $this->assertStatus($this->post($this->detailPath() . '/install', ['current_password' => 'password123']), 404);
    }

    public function test_guest_and_non_admin_are_denied_before_anything_else(): void
    {
        $response = $this->post($this->detailPath() . '/install', ['current_password' => 'x']);
        $this->assertRedirectContains($response, '/login');

        $this->actingAs($this->makeUser());
        $this->assertStatus($this->post($this->detailPath() . '/install', ['current_password' => 'x']), 403);
        $this->assertStatus($this->post($this->detailPath() . '/plan', []), 403);
    }

    public function test_full_no_js_install_journey(): void
    {
        $this->actingAs($this->admin);

        // Plan (POST — it may fetch/cache): shows manifest, permission labels, and the install form.
        $plan = $this->post($this->detailPath() . '/plan', []);
        $this->assertStatus($plan, 200);
        $this->assertSeeText($plan, 'Midnight Theme');
        $this->assertSeeText($plan, 'Install plan');
        $this->assertSeeText($plan, 'Store its own settings and data'); // DataClasses consent string
        self::assertSame('noindex', $plan->headers['X-Robots-Tag'] ?? null);

        // Install → redirect to consent.
        $install = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->assertRedirectContains($install, $this->detailPath() . '/consent');
        $installedId = (int) (new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id'])['id'];

        // Consent page lists the pending grants with human labels.
        $consentForm = $this->get($this->detailPath() . '/consent');
        $this->assertStatus($consentForm, 200);
        $this->assertSeeText($consentForm, 'Store its own settings and data');
        $this->assertSeeText($consentForm, 'Grant and continue');

        $consent = $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);
        $this->assertRedirectContains($consent, $this->detailPath());

        $enable = $this->post($this->detailPath() . '/enable', ['current_password' => 'password123']);
        $this->assertRedirectContains($enable, $this->detailPath());
        self::assertSame('enabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);

        $detail = $this->get($this->detailPath());
        $this->assertSeeText($detail, 'Enabled');
        $this->assertSeeText($detail, 'Disable');
        $this->assertDontSeeText($detail, 'Install does not exist yet');

        $disable = $this->post($this->detailPath() . '/disable', []); // deliberately no password: emergency brake
        $this->assertRedirectContains($disable, $this->detailPath());
        self::assertSame('disabled', (new InstalledPackageRepository($this->db))->find($installedId)['state']);
    }

    public function test_update_with_expansion_stages_then_consent_applies(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);

        RegistryFixtures::seedRelease($this->db, $this->root, $this->seeded, [
            'version' => '1.1.0',
            'manifest' => ['permissions' => [
                'data_classes' => ['package.own_storage'],
                'outbound_hosts' => ['api.example.com'],
            ]],
        ], $this->artifactDir);

        $update = $this->post($this->detailPath() . '/update', []);
        // Reauth missing → 422 re-render, form preserved (anti-draft-loss).
        $this->assertStatus($update, 422);

        $update = $this->post($this->detailPath() . '/update', ['current_password' => 'password123']);
        $this->assertRedirectContains($update, $this->detailPath() . '/consent');

        $consentForm = $this->get($this->detailPath() . '/consent');
        $this->assertSeeText($consentForm, 'New permissions');
        $this->assertSeeText($consentForm, 'api.example.com');

        $apply = $this->post($this->detailPath() . '/consent', ['current_password' => 'password123']);
        $this->assertRedirectContains($apply, $this->detailPath());
        $this->assertSeeText($this->get($this->detailPath()), '1.1.0');
    }

    public function test_refusals_render_422_with_the_coded_reason(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/blocklist', ['digest' => $this->seeded['release_digest'], 'reason' => 'incident']);

        $plan = $this->post($this->detailPath() . '/plan', []);
        $this->assertStatus($plan, 200); // plan REPORTS the refusal
        $this->assertSeeText($plan, 'local blocklist');

        $install = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        $this->assertStatus($install, 422);
        $this->assertSeeText($install, 'locally_blocked');
        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }

    public function test_uninstall_and_export_round_trip(): void
    {
        $this->actingAs($this->admin);
        $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);

        $export = $this->post($this->detailPath() . '/export', []);
        $this->assertStatus($export, 200);
        self::assertStringContainsString('application/json', (string) ($export->headers['Content-Type'] ?? ''));
        self::assertStringContainsString('rb-install-export.v1', $export->body);

        $uninstall = $this->post($this->detailPath() . '/uninstall', ['current_password' => 'password123']);
        $this->assertRedirectContains($uninstall, $this->detailPath());
        $this->assertSeeText($this->get($this->detailPath()), 'Uninstalled');
    }

    public function test_suspended_admin_cannot_mutate(): void
    {
        $suspended = $this->makeAdmin(['password' => 'password123']);
        $this->db->pdo()->prepare("UPDATE users SET status = 'suspended', suspended_until = '2099-01-01 00:00:00' WHERE id = :id")
            ->execute(['id' => $suspended['id']]);
        $this->actingAs($suspended);

        $response = $this->post($this->detailPath() . '/install', ['current_password' => 'password123']);
        self::assertGreaterThanOrEqual(400, $response->status, 'state beats role on every write path');
        self::assertNull((new InstalledPackageRepository($this->db))->findByPackage((int) $this->seeded['package_id']));
    }
}
```

Adjust helper names (`assertRedirectContains`, `$response->headers`, `$response->body`, `$response->status`, the suspension column names) to the exact `Tests\Support\TestCase` API — copy whatever `AppRegistryAdminTest` uses; do not invent new helpers.

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Core/AppPackageLifecycleTest.php`
Expected: FAIL — routes 404 (controller absent).

- [ ] **Step 3: Implement the controller**

`src/Controller/AdminPackageLifecycleController.php` — every action: `requireAdmin()` → `gate()` → marshal → one service call per branch → exceptions mapped; every response `noindex()`ed:

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
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Registry\RegistryCatalogService;

/**
 * P5-02 lifecycle surface: install plan/preview, install, consent, enable,
 * disable, pin, update policy, staged update + re-consent, rollback,
 * uninstall, export, reverify. Server-rendered, no-JS, flag-dark, noindexed.
 * {id} is always packages.id; the install row is resolved server-side.
 */
final class AdminPackageLifecycleController extends Controller
{
    public function plan(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $releaseId = $request->str('release_id') !== '' ? (int) $request->str('release_id') : null;
        try {
            $plan = $this->lifecycle()->plan($admin, $packageId, $releaseId);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['plan' => $e->code . ': ' . $e->getMessage()], 422);
        }
        return $this->noindex($this->view('admin/package_plan', ['plan' => $plan, 'errors' => []]));
    }

    public function install(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $releaseId = $request->str('release_id') !== '' ? (int) $request->str('release_id') : null;
        try {
            $this->lifecycle()->install($admin, (string) $request->post('current_password', ''), $packageId, $releaseId);
        } catch (ValidationException $e) {
            return $this->planView($admin, $packageId, $releaseId, $e->errors);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['install' => $e->code . ': ' . $e->getMessage()], 422);
        }
        return $this->noindex($this->redirect('/admin/packages/' . $packageId . '/consent'));
    }

    public function consentForm(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        return $this->consentView($packageId, [], 200);
    }

    public function consent(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        try {
            if ($install['staged_release_id'] !== null) {
                $this->updates()->applyStaged($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            } else {
                $this->lifecycle()->consent($admin, (string) $request->post('current_password', ''), (int) $install['id']);
            }
        } catch (ValidationException $e) {
            return $this->consentView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->consentView($packageId, ['consent' => $e->code . ': ' . $e->getMessage()], 422);
        }
        return $this->noindex($this->redirect('/admin/packages/' . $packageId));
    }

    public function enable(Request $request, array $params): Response
    {
        return $this->passwordAction($request, $params, 'enable',
            fn ($admin, $install, $password) => $this->lifecycle()->enable($admin, $password, (int) $install['id']));
    }

    public function disable(Request $request, array $params): Response
    {
        return $this->simpleAction($request, $params, 'disable',
            fn ($admin, $install) => $this->lifecycle()->disable($admin, (int) $install['id']));
    }

    public function pin(Request $request, array $params): Response
    {
        $pinned = $request->str('pinned') === '1';
        return $this->simpleAction($request, $params, 'pin',
            fn ($admin, $install) => $this->lifecycle()->setPinned($admin, (int) $install['id'], $pinned));
    }

    public function updatePolicy(Request $request, array $params): Response
    {
        $policy = $request->str('policy');
        return $this->simpleAction($request, $params, 'update_policy',
            fn ($admin, $install) => $this->lifecycle()->setUpdatePolicy($admin, (int) $install['id'], $policy));
    }

    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        $releaseId = $request->str('release_id') !== '' ? (int) $request->str('release_id') : null;
        try {
            $result = $this->updates()->update($admin, (string) $request->post('current_password', ''), (int) $install['id'], $releaseId);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['update' => $e->code . ': ' . $e->getMessage()], 422);
        }
        $target = $result['status'] === 'staged' ? '/admin/packages/' . $packageId . '/consent' : '/admin/packages/' . $packageId;
        return $this->noindex($this->redirect($target));
    }

    public function cancelUpdate(Request $request, array $params): Response
    {
        return $this->simpleAction($request, $params, 'update_cancel',
            fn ($admin, $install) => $this->updates()->cancelStaged($admin, (int) $install['id']));
    }

    public function rollback(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        try {
            $result = $this->updates()->rollback(
                $admin, (string) $request->post('current_password', ''),
                (int) $install['id'], (int) $request->str('release_id')
            );
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, ['rollback' => $e->code . ': ' . $e->getMessage()], 422);
        }
        $target = $result['status'] === 'staged' ? '/admin/packages/' . $packageId . '/consent' : '/admin/packages/' . $packageId;
        return $this->noindex($this->redirect($target));
    }

    public function uninstall(Request $request, array $params): Response
    {
        return $this->passwordAction($request, $params, 'uninstall',
            fn ($admin, $install, $password) => $this->lifecycle()->uninstall($admin, $password, (int) $install['id']));
    }

    public function export(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        try {
            $export = $this->lifecycle()->export($admin, (int) $install['id']);
        } catch (PackagePolicyException $e) {
            return $this->detailView($packageId, ['export' => $e->code . ': ' . $e->getMessage()], 422);
        }
        // Build the download exactly like the app's existing raw-body responses (match src/Core/Response's API).
        $body = json_encode($export, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response = new Response(200, $body, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="package-export-' . $packageId . '.json"',
        ]);
        return $this->noindex($response);
    }

    public function reverify(Request $request, array $params): Response
    {
        return $this->simpleAction($request, $params, 'reverify',
            fn ($admin, $install) => $this->lifecycle()->reverify($admin, (int) $install['id']));
    }

    // ── helpers ──

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        $response->headers['X-Robots-Tag'] = 'noindex';
        return $response;
    }

    private function lifecycle(): PackageLifecycleService
    {
        return $this->container->get(PackageLifecycleService::class);
    }

    private function updates(): PackageUpdateService
    {
        return $this->container->get(PackageUpdateService::class);
    }

    private function requireInstallRow(int $packageId): array
    {
        if ($this->container->get(PackageRepository::class)->find($packageId) === null) {
            throw new NotFoundException();
        }
        $install = $this->container->get(InstalledPackageRepository::class)->findByPackage($packageId);
        if ($install === null) {
            throw new NotFoundException();
        }
        return $install;
    }

    /** Password-reauth POST that redirects back to the detail page on success. */
    private function passwordAction(Request $request, array $params, string $errorKey, callable $call): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        try {
            $call($admin, $install, (string) $request->post('current_password', ''));
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, [$errorKey => $e->code . ': ' . $e->getMessage()], 422);
        }
        return $this->noindex($this->redirect('/admin/packages/' . $packageId));
    }

    /** Reauth-free POST (emergency brake / low-impact toggles), same rendering rules. */
    private function simpleAction(Request $request, array $params, string $errorKey, callable $call): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $packageId = (int) ($params['id'] ?? 0);
        $install = $this->requireInstallRow($packageId);
        try {
            $call($admin, $install);
        } catch (ValidationException $e) {
            return $this->detailView($packageId, $e->errors, 422);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            return $this->detailView($packageId, [$errorKey => $e->code . ': ' . $e->getMessage()], 422);
        }
        return $this->noindex($this->redirect('/admin/packages/' . $packageId));
    }

    /** Re-render the detail page with errors (anti-draft-loss; mirrors AdminPackagesController::show data). */
    private function detailView(int $packageId, array $errors, int $status): Response
    {
        $detail = $this->container->get(RegistryCatalogService::class)->detail($packageId);
        if ($detail === null) {
            throw new NotFoundException();
        }
        $detail['rollback_targets'] = $detail['installed'] !== null
            ? $this->updates()->rollbackTargets((int) $detail['installed']['id'])
            : [];
        return $this->noindex($this->view('admin/package_detail', $detail + ['errors' => $errors], $status));
    }

    private function planView(\App\Domain\User $admin, int $packageId, ?int $releaseId, array $errors): Response
    {
        $plan = $this->lifecycle()->plan($admin, $packageId, $releaseId);
        return $this->noindex($this->view('admin/package_plan', ['plan' => $plan, 'errors' => $errors], 422));
    }
}
```

Match the base-`Controller` helper signatures (`view`, `redirect`, header mutation on `Response`) to the codebase — `AdminRegistryController` is the reference; if `Response` headers are set through a method instead of an array property, mirror its `noindex()` implementation verbatim.

`AdminPackagesController::show` must also pass `rollback_targets` (same enrichment as `detailView`) so the detail template renders from either path — extract nothing; just add the two lines there.

- [ ] **Step 4: Extend `RegistryCatalogService::detail()` + templates**

`RegistryCatalogService`: append the three repository params; in `detail()`, after the existing assembly:

```php
$installed = $this->installs->findByPackage($packageId);
return [
    // …existing keys…,
    'installed' => $installed,
    'installed_permissions' => $installed !== null ? $this->installPermissions->forInstall((int) $installed['id']) : [],
    'history' => $this->packageHistory->forPackage($packageId, 50),
];
```

`templates/admin/package_detail.php` — insert an **Installation** section after the releases block (all output through `$e()`, CSRF via `$this->csrfField()`; forms only — no JS). Render:
- Not installed: an "Install plan" form → `POST /admin/packages/{id}/plan` (optional `release_id` select over `$releases`).
- Installed: state/health/pinned/policy badges (`Enabled`, `Disabled`, `Quarantined`, `Uninstalled`, version, digest `<code>`); the error banner `<?php foreach (($errors ?? []) as $err): ?><p class="field-error"><?= $e($err) ?></p><?php endforeach; ?>`; then per-state forms, each a small `<form method="post">` with `csrfField()`:
  - `enable` (password field) when state ∈ installed|disabled; `disable` (no password) when enabled; `reverify` when quarantined.
  - `pin` toggle (`pinned` hidden input flipping 0/1), `update-policy` select (manual|notify).
  - `update` (password + optional `release_id`) with an "Update available" banner when `installed.release_id !== package.latest_release_id`; `update/cancel` + a "staged, awaiting re-consent → Review & approve" link to `/consent` when `staged_release_id` set.
  - `rollback`: select over `$rollback_targets` (version + digest) + password.
  - `uninstall` (password) + `export` (no password).
- Pending grants notice ("N permissions await consent → Review consent") when any `installed_permissions` row has `granted=0`.
- Permission table (kind, key, risk, granted?) from `installed_permissions` using `PermissionDiff::describe`-shaped labels passed from the controller — pass `permission_labels` from `detailView` if the template needs them, or render `label` via a tiny helper; keep it server-side.
- History table (event, versions, digests, failure_stage, detail, created_at) from `$history`.

`templates/admin/package_plan.php` (new, `variant=app` layout like the other admin templates): plan header (`Install plan — {name} {version}`), refusal banner when `$plan['refusal']` (`<?= $e($plan['refusal']['code'] . ': ' . $plan['refusal']['message']) ?>`), compat badge, warning list, the permission consent-preview table (`$plan['permissions']` rows: risk badge + `label`), provenance (registry source, publisher, digest), and — when `$plan['refusal'] === null && $plan['installed'] === null` — the install form (`current_password` + submit "Install (disabled until consent)"). State that **enabling happens later**: "Installing records provenance and permissions; nothing executes until you consent and enable."

`templates/admin/package_consent.php` (new): if the install has `staged_release_id` → title "Approve update to {staged version}" and the **diff** sections ("New permissions" / "Removed" / "Unchanged" from `PackageUpdateService::updatePlan`'s diff, passed by `consentView`); else → title "Consent to permissions" listing pending grants. Both end in one form: `current_password` + submit "Grant and continue". Include the `field-error` block for `$errors['current_password']`.

`consentView` (controller helper) assembles: install row, package, pending permissions (`forInstall` where `granted=0`) with `PermissionDiff::describe` labels, or the staged diff via `$this->updates()->updatePlan($admin, (int) $install['id'], (int) $install['staged_release_id'])`.

`templates/admin/packages.php`: replace the "Install does not exist yet" line with an install-state badge column (`Installed`/`Enabled`/`—` from a new `installed_states` map the controller passes: `InstalledPackageRepository::all()` keyed by `package_id`).

- [ ] **Step 5: Update the Inc 2 kernel tests + flag regression**

- `tests/Integration/Core/AppRegistryCatalogTest.php`: the "install absent everywhere" assertions flip — the catalogue no longer shows "Install does not exist yet"; the detail page shows the "Install plan" form for admins. Keep (and strengthen) the guest/member denial assertions.
- `tests/Integration/Core/AppFeatureFlagTest.php`: extend the `package_registry` dark-route regression with three lifecycle routes (`POST …/plan`, `GET …/consent`, `POST …/install` → 404 when the flag is off) following the file's existing route-matrix style.

- [ ] **Step 6: Run to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Core/AppPackageLifecycleTest.php tests/Integration/Core/AppRegistryCatalogTest.php tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS. Then the full suite once: `composer test` — everything green (strict PHPUnit: no output, no warnings).

- [ ] **Step 7: Commit**

```bash
git add src/Controller/AdminPackageLifecycleController.php src/Controller/AdminPackagesController.php src/Service/Registry/RegistryCatalogService.php src/Core/App.php templates/admin/package_plan.php templates/admin/package_consent.php templates/admin/package_detail.php templates/admin/packages.php tests/Integration/Core/AppPackageLifecycleTest.php tests/Integration/Core/AppRegistryCatalogTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(phase5): no-JS package lifecycle admin surface - plan/install/consent/enable/update/rollback/uninstall/export (Inc 3)"
```

---
### Task 14: Measure the D11 budget — `package.install_update_p95`

`Phase5Budgets` already carries the row (`target 10000 ms, p95, measurable_at inc3`). Following the Inc 2 pattern: a sampler on `BaselineMetricsService`, a hardcoded `elseif` branch in `Phase5BudgetReportService::rows()`, wiring in `bin/console verify:phase5-budgets`, and a regenerated `docs/evidence/phase5/performance-budgets.md`.

**Files:**
- Modify: `src/Service/BaselineMetricsService.php` (add `measureInstallUpdate`)
- Modify: `src/Service/Phase5BudgetReportService.php` (append nullable ctor params `?PackageLifecycleService`, `?PackageUpdateService`, `?PackageArtifactStore`; add the `elseif ($key === 'package.install_update_p95')` branch)
- Modify: `bin/console` (`verify:phase5-budgets` case: build the package service stack against a temp artifact store, drop the hasher cost like `tests/bootstrap.php` does, pass the collaborators)
- Test: `tests/Integration/Service/PackageInstallBudgetTest.php`

**Interfaces:**
- Produces: `BaselineMetricsService::measureInstallUpdate(PackageLifecycleService $lifecycle, PackageUpdateService $updates, Database $db, PackageArtifactStore $store, int $samples = 8): array{p95:float,samples:int}`. The sampler is **self-contained src/ code** (no `Tests\` dependency — mirror how `measureSignatureVerify` mints its own sodium material): per sample it seeds a fresh synthetic registry/package (`bench/pkg-{n}`, inline `sodium_crypto_sign_keypair`, release document signed over exact bytes, digest = sha256, artifact `put()` into the store, cheap-hash admin user), then times **install → consent → enable → update-to-v2 (no permission change → applied immediately)** and records milliseconds; p95 over samples.

- [ ] **Step 1: Write the failing test** — build the service stack exactly as `PackageLifecycleServiceTest` does (temp store), then:

```php
public function test_measure_install_update_produces_a_p95_sample(): void
{
    $result = (new BaselineMetricsService($this->db))->measureInstallUpdate(
        $this->lifecycle(), $this->updates(), $this->db, $this->store, samples: 2,
    );
    self::assertSame(2, $result['samples']);
    self::assertGreaterThan(0.0, $result['p95']);
    self::assertLessThan(10_000.0, $result['p95'], 'D11 target sanity on the test fixture');
}
```

(Match `BaselineMetricsService`'s real constructor when writing — reuse whatever `verify:phase5-budgets` already passes it.)

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit tests/Integration/Service/PackageInstallBudgetTest.php` → method missing.

- [ ] **Step 3: Implement** the sampler (per the interface note above; seed rows with plain prepared INSERTs mirroring `RegistryFixtures` column-for-column, but namespaced `bench/pkg-{n}` and signed with an inline keypair pinned into `registry_trust_keys` as public bytes), the report-service branch (result `MEASURED (PASS)` when `p95 <= target`, else `MEASURED (FAIL)`), and the console wiring:

```php
// in the verify:phase5-budgets case, before building the report service:
PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]); // synthetic fixture only; command already refuses production
$benchStore = new PackageArtifactStore(sys_get_temp_dir() . '/rb-budget-packages-' . bin2hex(random_bytes(4)));
// …hand-wire PackageAcquisitionService (ArrayRegistryTransport([]) — the sampler pre-caches artifacts),
//  PackageSecurityGate, PackageLifecycleService, PackageUpdateService exactly as the tests do…
// pass them + $benchStore into Phase5BudgetReportService; recursively delete the temp dir in a finally block.
```

- [ ] **Step 4: Verify** — `vendor/bin/phpunit tests/Integration/Service/PackageInstallBudgetTest.php` → PASS, then:

```bash
APP_ENV=testing php bin/console verify:phase5-budgets
```
Expected: the regenerated `docs/evidence/phase5/performance-budgets.md` shows `package.install_update_p95` as **MEASURED (PASS)** with a real number; every other row unchanged.

- [ ] **Step 5: Commit**

```bash
git add src/Service/BaselineMetricsService.php src/Service/Phase5BudgetReportService.php bin/console tests/Integration/Service/PackageInstallBudgetTest.php docs/evidence/phase5/performance-budgets.md
git commit -m "feat(phase5): measure package.install_update_p95 against the D11 budget (Inc 3)"
```

---

### Task 15: Browser + axe evidence (desktop + mobile)

Playwright drives the real no-JS journey; screenshots continue the Gate A index at **35–38**. The Inc 2 spec's `Install does not exist yet` assertion is retired (Task 13 removed the copy).

**Files:**
- Modify: `tests/browser/seed.php` (package-lifecycle fixtures)
- Modify: `tests/browser/gate-a.spec.ts` (update the Inc 2 registry test; add the Inc 3 journey)
- Modify: `tests/browser/a11y.spec.ts` (consent + installed-detail routes)

- [ ] **Step 1: Extend `seed.php`**

Append a Phase 5 Inc 3 block (the file already sets `$evidenceFeatures['package_registry'] = true`). Use the dev-autoloaded harness (`Tests\Support\Phase5\{SigningHarness, RegistryFixtures}`) against the e2e database and write artifacts to the app's real store path:

```php
// ── Phase 5 Inc 3: package install/lifecycle evidence fixtures ──
$root = \Tests\Support\Phase5\SigningHarness::generate('evidence-root-1');
$artifactDir = dirname(__DIR__, 2) . '/storage/packages';
$pkg = \Tests\Support\Phase5\RegistryFixtures::seed($db, $root, $artifactDir);               // acme/midnight-theme 1.0.0, approved, hydrated
\Tests\Support\Phase5\RegistryFixtures::seedRelease($db, $root, $pkg, [
    'version' => '1.1.0',
    'manifest' => ['permissions' => [
        'data_classes' => ['package.own_storage'],
        'outbound_hosts' => ['api.example.com'],
    ]],
], $artifactDir);                                                                            // update target with a permission increase

// Second package pre-installed awaiting consent — stable target for the a11y scan of /consent.
$pkg2 = \Tests\Support\Phase5\RegistryFixtures::seed... // NOTE: seed() is single-fixture; add a $uid override
```

`RegistryFixtures::seed` is single-package — extend it in this step with an optional `array $overrides = []` (uid/name pass-through to `mintRelease`) so the second call can seed `acme/consent-demo`; then create its install row + declared-ungranted permissions directly (mirror `PackageLifecycleServiceTest`'s repo usage: `InstalledPackageRepository::create` + `InstalledPackagePermissionRepository::replaceDeclared`). Echo the seeded ids like the file's existing summary lines.

- [ ] **Step 2: Update `gate-a.spec.ts`**

In the Inc 2 registry test, replace `await expect(page.getByText('Install does not exist yet')).toBeVisible();` with `await expect(page.getByRole('button', { name: 'Install plan' })).toBeVisible();`. Then add:

```ts
test('package lifecycle: plan → consent → enable → update re-consent (Inc 3)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/packages');
  await page.getByRole('link', { name: 'Details' }).first().click();

  await page.getByRole('button', { name: 'Install plan' }).click();      // POST /plan
  await expect(page.getByRole('heading', { name: /Install plan/ })).toBeVisible();
  await expect(page.getByText('Store its own settings and data', { exact: false })).toBeVisible();
  await shot(page, info, '35-admin-package-install-plan');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: /Install/ }).click();           // POST /install → consent
  await expect(page.getByRole('heading', { name: /Consent to permissions/ })).toBeVisible();
  await shot(page, info, '36-admin-package-consent');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Grant and continue' }).click();

  await page.fill('form[action$="/enable"] input[name="current_password"]', 'password123');
  await page.locator('form[action$="/enable"] button[type="submit"]').click();
  await expect(page.getByText('Enabled')).toBeVisible();
  await shot(page, info, '37-admin-package-enabled');

  await page.fill('form[action$="/update"] input[name="current_password"]', 'password123');
  await page.locator('form[action$="/update"] button[type="submit"]').click();  // stages → consent diff
  await expect(page.getByRole('heading', { name: /Approve update/ })).toBeVisible();
  await expect(page.getByText('api.example.com')).toBeVisible();
  await shot(page, info, '38-admin-package-update-diff');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Grant and continue' }).click();
  await expect(page.getByText('1.1.0')).toBeVisible();
});
```

(Adapt selectors to the exact template markup from Task 13; keep the assert-then-shoot discipline the file already uses.)

- [ ] **Step 3: Extend `a11y.spec.ts`** — after the `/admin/registries` visit, add visits for the seeded consent-demo package's detail page and its `/consent` page (ids echoed by seed.php are stable per fresh e2e DB; resolve them the same way the spec resolves other seeded ids — follow the file's existing pattern).

- [ ] **Step 4: Run the evidence + a11y suites**

```bash
cd tests/browser && npm run evidence && npm run a11y
```
Expected: all passing (was 33 passed / 1 skipped + 8 a11y — now +1 journey ×2 viewports and +2 a11y routes); screenshots `35–38` present under `docs/evidence/browser/{desktop,mobile}/`; axe reports no serious/critical violations on the new pages.

- [ ] **Step 5: Commit**

```bash
git add tests/browser/seed.php tests/browser/gate-a.spec.ts tests/browser/a11y.spec.ts tests/Support/Phase5/RegistryFixtures.php docs/evidence/browser
git commit -m "test(phase5): browser + axe evidence for the package install lifecycle (Inc 3)"
```

---

### Task 16: Closeout — protocol doc, runbook, ledger, threat-model flips, status, final gates

**Files:**
- Modify: `docs/phase5/registry-protocol.md`, `docs/runbooks/package_registry.md`, `CLAUDE.md` (workers list), `docs/phase5/requirement-ledger.json`, `docs/phase5/threat-models/fixtures.json`, `PHASE_5_STATUS.md`

- [ ] **Step 1: Protocol doc** — add a "Release documents & artifacts (Inc 3)" section to `docs/phase5/registry-protocol.md`: the `rb-release.v1` schema (uid/version/review/manifest; **no self-digest — the snapshot pins sha256 of the exact document bytes**), the `rb-release-envelope.v1` fetch endpoint `GET {base_url}/releases/{uid}/{version}/rb-release-envelope.v1.json` + the `source_url` same-origin pinning rule, the full `rb-manifest.v2` field table (types, permission kinds + vocabularies, settings-schema shape, quota/retention/support constraints, every refusal code from Tasks 2–3), the acquisition verification order (digest → signature/format → identity → review → manifest), the artifact store layout + `worker:packages` tamper/quarantine behavior, and the now-complete advisory ladder (`force_disable` disables installed packages — Inc 2's deferred note resolved).

- [ ] **Step 2: Runbook** — extend `docs/runbooks/package_registry.md`: install/consent/enable procedure; staged-update approval + cancel; rollback (previously verified digests only); uninstall/export/retention (+ purge timing); quarantine response (inspect → restore bytes → **Re-verify** → re-enable; never auto-enable); emergency disable paths (blocklist add now force-disables enabled installs inline; `worker:packages` as the sweep); cron rows (`worker:packages` hourly next to `worker:registry-refresh`); `repair` note (`installed_packages` reconcile). Add `worker:packages` to CLAUDE.md's background-workers block.

- [ ] **Step 3: Ledger + threat models**
- `docs/phase5/requirement-ledger.json`: `GA-DOD-05` → `R3`, evidence `["src/Security/Packages/ManifestValidator.php","tests/Unit/Security/Packages/ManifestValidatorTest.php","tests/Integration/Core/AppPackageLifecycleTest.php","docs/phase5/registry-protocol.md"]`; `GA-DOD-06` → `R3`, evidence `["src/Security/Packages/PackageSecurityGate.php","tests/Integration/Security/PackageSecurityGateTest.php","tests/Integration/Service/PackageAcquisitionServiceTest.php","tests/Integration/Worker/PackageHealthWorkerTest.php"]`; `GA-DOD-09` → `R2` with evidence for the enforcement seam + a note "install-side exact-digest enforcement + review-evidence cache landed Inc 3; publisher console/advisory worker are Inc 5"; update the `GA-DOD-03` note (0069–0070 authored) and the `GA-DOD-18` note (`package.install_update_p95` measured). States bump **in this commit** because this commit lands their evidence.
- `docs/phase5/threat-models/fixtures.json`: `TM-SC-06` → `implemented`, `test: "tests/Integration/Service/PackageLifecycleServiceTest.php"`; `TM-SC-07` → `implemented`, `test: "tests/Integration/Worker/PackageHealthWorkerTest.php"`; `TM-SC-09` → `implemented`, `test: "tests/Integration/Service/PackageAcquisitionServiceTest.php"`.
- Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/MigrationLedgerTest.php` → PASS.

- [ ] **Step 4: `PHASE_5_STATUS.md`** — add an "Increment 3 landed (2026-07-02) — P5-02 install & lifecycle, deploy-dark" section (mirror the Inc 2 section's structure: what landed per sub-area, evidence pointers, TM flips, budget row, browser shots 35–38) and update the header status paragraph, the suite-numbers line, and the branch line (`phase5-inc3-package-lifecycle`). State explicitly: **still dark behind `package_registry`; enabled packages execute nothing until Inc 4 (themes) / Inc 5 (integrations); publisher console = Inc 5.**

- [ ] **Step 5: Final gates (§F distributed discipline)**

```bash
RB_TEST_FRESH=1 composer test                            # full suite green on a fresh schema
composer test && composer test                           # two consecutive reused-schema (plain) runs — reference-table gotcha check
APP_ENV=testing php bin/console verify:phase5-budgets    # package.install_update_p95 MEASURED (PASS)
APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force   # 0069+0070 additive on populated data
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console worker:packages    # skipped=1 while dark
tests/backup/rehearse.sh                                 # backup includes the two new tables
```
Every command's expected result recorded in PHASE_5_STATUS. Any red = stop and fix before the closeout commit.

- [ ] **Step 6: Commit**

```bash
git add docs/phase5/registry-protocol.md docs/runbooks/package_registry.md CLAUDE.md docs/phase5/requirement-ledger.json docs/phase5/threat-models/fixtures.json PHASE_5_STATUS.md
git commit -m "docs(phase5): Inc 3 closeout - TM-SC-06/07/09 implemented, GA-DOD-05/06 -> R3, protocol + runbook + status"
```

---

## Plan self-review

- **Program-plan §D Inc 3 scope coverage:** manifest.v2 + fail-closed validation (T3) · Gate-A type allowlist incl. `server_extension` rejection (T3 validator + T7 gate) · compatibility check (T3 syntax, T9 enforcement) · install plan/preview resolving the exact digest without executing (T8+T9 `plan`) · atomic install with provenance + `granted=0` snapshot (T9) · enable/disable (T9) · pin/unpin (T10) · update + diff + re-consent, staged, old grant retained, reduction immediate (T10) · rollback to a verified digest (T10) · uninstall/export/retention (T11) · digest/tamper health worker → quarantine (T12) · immutable history (T5 + every mutation) · local emergency-disable enforcement (T12, closing the Inc 2 ladder handoff) · no-JS admin surface (T13) · `PackageSecurityGate` + `package_review_decisions` fail-closed (T7+T8) · migrations `0069`+`0070` (T4, §C re-baselined for the gapless guard).
- **§9 exit-gate scenarios:** Manifest-validation (T3), Install-rollback/failure-stage (T9), Permission-increase + Permission-reduction (T10), Review-digest (T8, TM-SC-09), Tampered-local-files (T12, TM-SC-07), Revoked-release install/enable (T7+T9, TM-SC-06), Uninstall/data (T11), Registry-outage lifecycle continuity (T9 last test).
- **Known adapt-points for the implementer** (verify against the file, keep the design): `Tests\Support\TestCase` helper/response accessor names (T13 note), base `Controller`/`Response` header + raw-body API (T13 note), `WriteGate`/`ReauthGate` constructor shapes (T9 note), `BaselineMetricsService` constructor (T14 note), seed.php/a11y id-resolution style (T15 note).
- **Type consistency:** locked interfaces in the header were reconciled against every task after drafting (`replaceWithGrants`, `PackageReviewDecisionRepository` in the lifecycle constructor, `release_review`/`no_release` codes, `hydrateSignedMetadata`).
- **Strict-PHPUnit traps:** validate-first keeps refusal tests row-free despite no savepoints; `RegistryAdvisoryService`/`LocalBlocklistService` gain only APPENDED nullable params so every Inc 2 construction stays valid; no new seed migrations, so the `AppSearchTest` preserve-list gotcha does not trigger (still verified by the double plain run in T16).

## Execution note

Branch: `phase5-inc3-package-lifecycle` (Task 0). Execute task-by-task in order — Tasks 1→8 are strictly sequential dependencies; 9→11 build on 8; 12 and 13 both need 9–11; 14–16 close out. Commit messages avoid `§` (repo convention from Inc 2: ASCII "SS" when needed). If any locked interface must change mid-execution, update this plan file in the same commit so the document stays the source of truth.
