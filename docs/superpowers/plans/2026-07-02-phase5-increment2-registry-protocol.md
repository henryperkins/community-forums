# Phase 5 Increment 2 — Registry Protocol & Package Identity (P5-01) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the P5-01 registry protocol deploy-dark behind `package_registry`: a pure fail-closed Ed25519 trust-chain verifier, signed + expiring catalogue snapshots with anti-replay and an offline cache, source-pinning / dependency-confusion refusal, key rotation/revocation via signed transitions, advisory ingest with the `warn → block_new → force_disable → revoke` ladder, a registry-independent local blocklist, an EgressGuard-guarded refresh worker, and a staff-only read-only catalogue browse — **no install path exists anywhere in this increment**.

**Architecture:** A pure verification core (`App\Security\Registry\TrustChainVerifier`) does every stateless check (signature, trust-key status/window, format); stateful rules (anti-replay monotonicity, source pinning, release immutability, the advisory ladder) live in services that own their `$db->transaction(fn)` write paths over thin repositories wrapping the inert `0049` tables. The only new schema is migration `0068` (`registry_snapshots` offline cache + a `moderation_log.target_type` widen). Nothing on the request path performs network I/O — fetching happens only in `worker:registry-refresh` (PHASE_5_PLAN §5 decision #10: the registry is never a synchronous dependency of core routes).

**Tech Stack:** PHP 8.2+, libsodium (`sodium_crypto_sign_verify_detached`, already required via `ext-sodium`), MySQL/MariaDB via the existing `Database` helper, the in-process kernel test harness (`Tests\Support\TestCase`), the F6 `SigningHarness`/`RegistryFixtures` test tooling, Playwright + axe.

## Global Constraints

*Every task implicitly includes this section. Values copied from CLAUDE.md, the Gate A program plan, and `docs/phase5/registry-signing-key-custody.md`.*

- **Deploy-dark.** `package_registry` stays **default `false`** in `FeatureFlags::DEFAULTS` (it already is — do not touch the map). Every new route throws `NotFoundException` when the flag is off, with a regression in `tests/Integration/Core/AppFeatureFlagTest.php`. The worker no-ops when the flag is off.
- **PUBLIC-KEY-ONLY (A4 §1, non-negotiable).** The application stores/reads **public** trust-key bytes only (`registry_trust_keys.public_key`). No code path may generate, read, persist, or log a registry **private** key. Signing exists only in the dev/test-only `Tests\Support\Phase5\SigningHarness`.
- **Fail closed.** Every verification failure is a thrown, coded exception; there is no default-allow branch. Tampered, expired, replayed, wrong-key, revoked-key, source-switched, and identity-malformed inputs must all refuse.
- **Migration `0068` exactly** (§C allocation: `phase5_registry_snapshots` — snapshot cache + `moderation_log.target_type` widen). Additive `up()`; `down()` drops FKs/rows before narrowing; `INSERT IGNORE` for any seed (none planned). Hand-update `SCHEMA.md` (shape + §9 changelog + version bump v1.26 → v1.27). `tests/Unit/Core/MigrationLedgerTest.php` enforces gapless-unique numbering.
- **Write path.** Controllers thin (marshal request → one service call → map exceptions); services own rules inside `$db->transaction(fn)`; repositories are final, single-table-ish, prepared statements only, return assoc arrays. Controllers catch `ValidationException` and re-render the form `422` with `->errors` + `->old` (anti-draft-loss).
- **Denormalized pointers get repairs.** This increment starts writing `packages.latest_release_id` and `packages`/`package_releases.advisory_status` — each needs a matching recompute in `RepairService` with identical semantics.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (clamp + concatenate ints); never reuse a named placeholder; `UTC_TIMESTAMP()` / `gmdate()` everywhere; no `NOW()`.
- **Strict CSP / no-JS-first.** No inline `<script>`/`<style>`; the new admin surfaces are plain server-rendered forms (`$this->csrfField()`, `$this->e()`/`$e`); every flow works without JavaScript.
- **CSRF on every POST; no GET mutates state.** High-impact **trust-root changes** (create/enable registry, pin/rotate/revoke keys, blocklist *removal*) require recent reauthentication via `ReauthGate::requirePassword($admin, $currentPassword)` (PHASE_5_PLAN §5 #26 extends to trust-root changes). Blocklist **add** is the deliberate no-friction emergency brake (registry-independent, §5 #40) and is audited but not password-gated.
- **Per-surface noindex:** every admin response (including redirects) carries `X-Robots-Tag: noindex` (Inc 1 review precedent).
- **Audit:** every trust/blocklist/advisory operator action writes a `moderation_log` row (`target_type` `'registry'` or `'package'`, added by `0068`).
- **Evidence (DESIGN §13, §F distributed discipline):** PHPUnit unit + integration; browser (Playwright desktop+mobile) + axe for the UI surfaces; per-surface noindex assertions; telemetry emission; the D11 budget rows measured on the F9 fixture; runbook entry; threat-model fixtures TM-SC-01…05 flipped to `implemented` with real test paths (`tests/Unit/Core/ThreatModelIndexTest.php` enforces the paths exist).
- **Strict PHPUnit:** every test ≥1 assertion; no output; no warnings. Per-test isolation is one rolled-back transaction — **no savepoints**, so assert observable behavior, not rollback effects.
- **Do not touch `docs/evidence/deploy-dark-features.md`** — it has uncommitted operator edits in the working tree. Never `git add -A`; every commit stages explicit paths.

## Context — what already exists (read before Task 1)

- **`database/migrations/0049_phase5_registry_packages.php`** — the ten inert tables this increment animates: `package_registries` (with `last_snapshot_digest/last_snapshot_at/snapshot_expires_at`), `registry_trust_keys` (public bytes, `status active|rotated|revoked`, validity window), `package_publishers`, `packages` (`package_uid` globally unique, `advisory_status`, denormalized `latest_release_id`), `package_releases` (immutable `(package_id, version)` + `digest`, `channel`, `advisory_status`), `installed_packages`/`installed_package_permissions`/`package_history` (**untouched until Inc 3**), `package_advisories`, `local_package_blocks`.
- **`tests/Support/Phase5/SigningHarness.php`** (F6) — mints `rb-registry-snapshot.v1`, `rb-release.v1`, `rb-advisory.v1`, `rb-key-rotation.v1` documents as `{json, signature, key_id}` (detached Ed25519 over the exact JSON bytes; sha256 hex digests), plus `tamper()`, `mintExpiredSnapshot()`, `mintRotation(successor)`. **This is the wire contract** — the verifier consumes exactly these shapes.
- **`tests/Support/Phase5/RegistryFixtures.php`** (F6) — seeds one dark registry (`rb-test`), one active trust key, publisher `acme`, package `acme/midnight-theme`, one signed approved release.
- **`docs/phase5/registry-signing-key-custody.md`** (A4, signed off) — 24 h snapshot freshness, annual rotation via signed transition, revocation fails closed, local blocklist always works.
- **`docs/phase5/threat-models/supply-chain.md` + `fixtures.json`** — TM-SC-01…05 are owned by this increment (byte-flip, expiry/replay, wrong/revoked key, forged rotation, source substitution).
- **`src/Support/CoreVersion.php`** (F2) — `CoreVersion::satisfies(?string $min, ?string $max, ?string $version = null): bool`, fail-closed on malformed versions. `App::CORE_VERSION` is `0.5.0-dev`.
- **`src/Security/EgressGuard.php`** — SSRF policy (used by webhooks/link previews); `validate(string $url): string` returns the pinned IP or throws `EgressBlockedException`. Mirror `src/Service/Webhook/CurlWebhookTransport.php` for the cURL discipline (no redirects, `CURLOPT_RESOLVE` pin, byte cap).
- **`src/Core/Telemetry.php`** (F8) — `emit(string $event, array $context)`; dark unless `telemetry.enabled`; contexts pass `LogRedactor`. Container-bound.
- **`src/Repository/ModerationLogRepository.php`** — `log(array{actor_id:?int, action:string, target_type:string, target_id:int, reason?:?string, before?:mixed, after?:mixed}): int`.
- **`src/Security/ReauthGate.php`** (F7) — `requirePassword(User $admin, string $currentPassword): void` (throws `ValidationException` keyed `current_password` on mismatch); `src/Security/WriteGate.php` — `assertCanWrite(User): void`.
- **Admin controller pattern** — `src/Controller/AdminRoleController.php`: `requireAdmin()` **then** `gate()` (the repo-wide order: 302/403 resolve before the dark 404), `noindex()` wraps every response including redirects, `ValidationException` re-renders 422 with `errors`+`old`.
- **Console pattern** — `bin/console` `worker:webhooks` (hand-built transport + EgressGuard from config) and `worker:extensions` (flag boolean passed into the worker so it no-ops dark).
- **Budget harness** — `src/Support/Phase5Budgets.php` rows `registry.snapshot_freshness` (86400 s max), `registry.fetch_p95` (2000 ms), `registry.signature_verify_p95` (250 ms) are `PENDING (inc2)`; `src/Service/BaselineMetricsService.php` + `Phase5BudgetReportService` show the measurement/report pattern; `bin/console verify:phase5-budgets` regenerates `docs/evidence/phase5/performance-budgets.md`.

## Locked interfaces (all tasks must match these exactly)

```
App\Security\Registry\PackageIdentity           isValidUid(string): bool · publisherUid(string): string · isValidSourceId(string): bool
App\Security\Registry\RegistryVerificationException  extends \RuntimeException; public readonly string $code
App\Security\Registry\VerifiedDocument          readonly: string $format, array $payload, string $keyId
App\Security\Registry\TrustChainVerifier        verify(string $documentJson, string $signature, string $keyId, array $trustKeys, string $expectedFormat, \DateTimeImmutable $now): VerifiedDocument
                                                verifyRotation(string $documentJson, string $signature, string $keyId, array $trustKeys, \DateTimeImmutable $now): array{key_id:string,public_key:string}

App\Repository\PackageRegistryRepository        all() · find(int) · findBySourceId(string) · enabled() · create(string,string,string): int · setEnabled(int,bool) · recordSnapshot(int,string,string,string)
App\Repository\RegistryTrustKeyRepository       forRegistry(int) · find(int) · findKey(int,string) · pin(int,string,string,?string,?string): int · markRotated(int) · revoke(int,string)
App\Repository\PackagePublisherRepository       findByUid(string) · ensure(string,string): int
App\Repository\PackageRepository                find(int) · findByUid(string) · create(array): int · setLatestRelease(int,?int) · setAdvisoryStatus(int,string) · catalog(): array
App\Repository\PackageReleaseRepository         find(int) · findVersion(int,string) · create(array): int · forPackage(int): array · setAdvisoryStatus(int,string)
App\Repository\PackageAdvisoryRepository        findByUid(string) · upsert(array): int · forPackage(int): array · all(): array · acknowledge(int,int)
App\Repository\LocalPackageBlockRepository      all() · find(int) · add(?string,?string,?string,?int): int · remove(int) · isBlocked(?string,?string): bool
App\Repository\RegistrySnapshotRepository       latestFor(int) · findByDigest(int,string) · record(int,string,string,string,string,string,string): int

App\Service\Registry\RegistrySnapshotService    applySnapshot(int $registryId, string $documentJson, string $signature, string $keyId, ?\DateTimeImmutable $now = null): array{status:string,packages:int,releases:int}
                                                isFresh(array $registryRow, ?\DateTimeImmutable $now = null): bool
App\Service\Registry\RegistryTrustService       createRegistry(User,string,string,string,string): int · setEnabled(User,?string,int,bool): void
                                                pinKey(User,string,int,string,string,?string,?string): int · applyRotation(User,string,int,string,string,string): int · revokeKey(User,string,int,string): void
App\Service\Registry\RegistryAdvisoryService    ingest(int,string,string,string,?\DateTimeImmutable): array{advisory_id:int,action:string} · acknowledge(User,int): void
                                                static escalate(string,string): string · static affectsVersion(?string,string): bool
App\Service\Registry\LocalBlocklistService      block(User,?string,?string,?string): int · unblock(User,string,int): void · isBlocked(?string,?string): bool
App\Service\Registry\RegistryCatalogService     overview(?\DateTimeImmutable $now = null): array · detail(int): ?array

App\Service\Registry\RegistryTransport          interface: fetch(string $url): RegistryFetchResult
App\Service\Registry\RegistryFetchResult        readonly: int $status, string $body, ?string $error
App\Service\Registry\CurlRegistryTransport      __construct(EgressGuard, int $maxBytes, int $timeoutSeconds)
App\Service\Registry\ArrayRegistryTransport     __construct(array<string, RegistryFetchResult> $responses)
App\Worker\RegistryRefreshWorker                __construct(PackageRegistryRepository, RegistrySnapshotService, RegistryAdvisoryService, RegistryTransport, bool $enabled, ?Telemetry $telemetry = null)
                                                run(): array{refreshed:int,unchanged:int,advisories:int,failed:int,skipped:int}

App\Controller\AdminPackagesController          index · show          (GET /admin/packages, GET /admin/packages/{id})
App\Controller\AdminRegistryController          index · create · setEnabled · pinKey · rotate · revokeKey · ingestAdvisory · ackAdvisory · block · unblock
```

Wire contract (documented in Task 12's `docs/phase5/registry-protocol.md`, consumed by the worker in Task 7):

```
GET {base_url}/rb-snapshot-envelope.v1.json
  → {"format":"rb-snapshot-envelope.v1","document":"<exact signed JSON string>","signature":"<base64>","key_id":"root-1"}
GET {base_url}/rb-advisory-envelopes.v1.json          (404 = no advisories, not an error)
  → {"format":"rb-advisory-envelopes.v1","advisories":[{"document":"...","signature":"<base64>","key_id":"root-1"}, ...]}
```

The `document` string is verified byte-for-byte (the signature covers the exact string, never a re-encoding).

## Out of scope (do not build here)

- **Install/enable/pin/update/uninstall of any package** — Inc 3 (P5-02). The exit gate asserts install is *absent*.
- **manifest.v2 validation** — Inc 3. Snapshots carry release metadata only; `package_releases.manifest_json` stays untouched by the snapshot path.
- **`PackageSecurityGate`, publisher review console, transparency log, `0072`** — Inc 3/5.
- **`installed_packages`/`installed_package_permissions`/`package_history` writes** — Inc 3. `force_disable` advisories therefore only mark status here; the plan records the Inc 3 handoff in the ladder comment.
- **Flag default flips, `docs/evidence/deploy-dark-features.md` edits, staged-rollout config.**

---

## Task 0: Branch

- [ ] **Step 1: Create the working branch from current main**

```bash
cd /home/henry/community-forums
git switch main && git pull --ff-only && git switch -c phase5-inc2-registry-protocol
```

Expected: branch `phase5-inc2-registry-protocol` at origin/main tip. (`git status` will show two pre-existing local items — modified `docs/evidence/deploy-dark-features.md` and the untracked foundation-remainder plan. Leave both alone; never stage them.)

---

### Task 1: Pure verification core — `PackageIdentity`, `RegistryVerificationException`, `VerifiedDocument`, `TrustChainVerifier`

The stateless heart of the increment: given a signed document, a detached signature, a key id, and the registry's pinned public-key rows, either return the decoded payload or throw a coded, fail-closed exception. TM-SC-01 (tamper), TM-SC-03 (wrong/revoked key), and TM-SC-04 (forged rotation) live here.

**Files:**
- Create: `src/Security/Registry/PackageIdentity.php`
- Create: `src/Security/Registry/RegistryVerificationException.php`
- Create: `src/Security/Registry/VerifiedDocument.php`
- Create: `src/Security/Registry/TrustChainVerifier.php`
- Test: `tests/Unit/Security/Registry/TrustChainVerifierTest.php`
- Test: `tests/Unit/Security/Registry/PackageIdentityTest.php`

**Interfaces:**
- Consumes: `Tests\Support\Phase5\SigningHarness` (F6) in tests only; `SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES` (32).
- Produces: the four classes exactly as in *Locked interfaces*. Exception codes emitted by `verify()`: `wrong_format`, `unknown_key`, `algorithm`, `revoked_key`, `inactive_key`, `key_window`, `bad_signature`, `malformed_document`. Extra codes from `verifyRotation()`: `rotation_signer_mismatch`, `rotation_key_id`, `rotation_key_material`. Later tasks match on `->code`.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/Security/Registry/PackageIdentityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Registry;

use App\Security\Registry\PackageIdentity;
use PHPUnit\Framework\TestCase;

final class PackageIdentityTest extends TestCase
{
    public function test_uid_validation_is_fail_closed(): void
    {
        self::assertTrue(PackageIdentity::isValidUid('acme/midnight-theme'));
        self::assertTrue(PackageIdentity::isValidUid('a1/b2.c-d_e'));

        foreach ([
            '', 'acme', '/theme', 'acme/', 'Acme/Theme', 'acme//theme',
            'acme/theme/extra', '-acme/theme', 'acme/-theme', "acme/th\u{00e9}me",
            'acme/' . str_repeat('x', 94), '../etc/passwd',
        ] as $bad) {
            self::assertFalse(PackageIdentity::isValidUid($bad), "should reject: $bad");
        }
    }

    public function test_publisher_uid_is_the_namespace_prefix(): void
    {
        self::assertSame('acme', PackageIdentity::publisherUid('acme/midnight-theme'));
    }

    public function test_source_id_validation(): void
    {
        self::assertTrue(PackageIdentity::isValidSourceId('rb-test'));
        self::assertFalse(PackageIdentity::isValidSourceId(''));
        self::assertFalse(PackageIdentity::isValidSourceId('RB Test'));
        self::assertFalse(PackageIdentity::isValidSourceId('-rb'));
    }
}
```

Create `tests/Unit/Security/Registry/TrustChainVerifierTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Registry;

use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

/**
 * TM-SC-01 (byte-flip rejected), TM-SC-03 (wrong-key / revoked-key rejected),
 * TM-SC-04 (forged rotation rejected) — pure, no DB.
 */
final class TrustChainVerifierTest extends TestCase
{
    private TrustChainVerifier $verifier;
    private SigningHarness $root;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->verifier = new TrustChainVerifier();
        $this->root = SigningHarness::generate('root-1');
        $this->now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function keyRow(SigningHarness $key, array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'registry_id' => 1,
            'key_id' => $key->keyId(),
            'algorithm' => 'ed25519',
            'public_key' => $key->publicKey(),
            'status' => 'active',
            'valid_from' => null,
            'valid_until' => null,
        ], $overrides);
    }

    /** @param array<string,mixed> $overrides */
    private function expectCode(string $code, callable $fn): void
    {
        try {
            $fn();
            self::fail("expected RegistryVerificationException($code)");
        } catch (RegistryVerificationException $e) {
            self::assertSame($code, $e->code);
        }
    }

    public function test_valid_snapshot_verifies_and_decodes(): void
    {
        $snap = $this->root->mintSnapshot();
        $doc = $this->verifier->verify(
            $snap['json'], $snap['signature'], $snap['key_id'],
            [$this->keyRow($this->root)], 'rb-registry-snapshot.v1', $this->now,
        );
        self::assertSame('rb-registry-snapshot.v1', $doc->format);
        self::assertSame('acme/midnight-theme', $doc->payload['packages'][0]['uid']);
        self::assertSame('root-1', $doc->keyId);
    }

    public function test_one_flipped_byte_is_rejected(): void
    {
        $snap = $this->root->mintSnapshot();
        $keys = [$this->keyRow($this->root)];
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            SigningHarness::tamper($snap['json']), $snap['signature'], 'root-1', $keys, 'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            $snap['json'], SigningHarness::tamper($snap['signature']), 'root-1', $keys, 'rb-registry-snapshot.v1', $this->now,
        ));
    }

    public function test_unknown_wrong_and_revoked_keys_are_rejected(): void
    {
        $snap = $this->root->mintSnapshot();
        $stranger = SigningHarness::generate('root-1'); // same id, different key material
        $this->expectCode('unknown_key', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1', [], 'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('bad_signature', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1', [$this->keyRow($stranger)], 'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('revoked_key', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1',
            [$this->keyRow($this->root, ['status' => 'revoked'])], 'rb-registry-snapshot.v1', $this->now,
        ));
    }

    public function test_key_validity_window_and_algorithm_are_enforced(): void
    {
        $snap = $this->root->mintSnapshot();
        $this->expectCode('key_window', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1',
            [$this->keyRow($this->root, ['valid_until' => $this->now->modify('-1 hour')->format('Y-m-d H:i:s')])],
            'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('key_window', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1',
            [$this->keyRow($this->root, ['valid_from' => $this->now->modify('+1 hour')->format('Y-m-d H:i:s')])],
            'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('algorithm', fn () => $this->verifier->verify(
            $snap['json'], $snap['signature'], 'root-1',
            [$this->keyRow($this->root, ['algorithm' => 'rsa'])], 'rb-registry-snapshot.v1', $this->now,
        ));
    }

    public function test_rotated_key_still_verifies_within_window_but_cannot_sign_a_rotation(): void
    {
        $snap = $this->root->mintSnapshot();
        $rotatedRow = $this->keyRow($this->root, [
            'status' => 'rotated',
            'valid_until' => $this->now->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $doc = $this->verifier->verify($snap['json'], $snap['signature'], 'root-1', [$rotatedRow], 'rb-registry-snapshot.v1', $this->now);
        self::assertSame('rb-registry-snapshot.v1', $doc->format);

        $rotation = $this->root->mintRotation(SigningHarness::generate('root-2'));
        $this->expectCode('inactive_key', fn () => $this->verifier->verifyRotation(
            $rotation['json'], $rotation['signature'], 'root-1', [$rotatedRow], $this->now,
        ));
    }

    public function test_malformed_and_wrong_format_documents_are_rejected(): void
    {
        $keys = [$this->keyRow($this->root)];
        $raw = 'not-json';
        $this->expectCode('malformed_document', fn () => $this->verifier->verify(
            $raw, $this->root->sign($raw), 'root-1', $keys, 'rb-registry-snapshot.v1', $this->now,
        ));
        $adv = $this->root->mintAdvisory();
        $this->expectCode('wrong_format', fn () => $this->verifier->verify(
            $adv['json'], $adv['signature'], 'root-1', $keys, 'rb-registry-snapshot.v1', $this->now,
        ));
        $this->expectCode('wrong_format', fn () => $this->verifier->verify(
            $adv['json'], $adv['signature'], 'root-1', $keys, 'rb-not-a-format.v9', $this->now,
        ));
    }

    public function test_valid_rotation_yields_the_successor_key_material(): void
    {
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);
        $out = $this->verifier->verifyRotation(
            $rotation['json'], $rotation['signature'], 'root-1', [$this->keyRow($this->root)], $this->now,
        );
        self::assertSame('root-2', $out['key_id']);
        self::assertSame($successor->publicKey(), $out['public_key']);
    }

    public function test_forged_rotations_are_rejected(): void
    {
        $attacker = SigningHarness::generate('evil-1');
        $successor = SigningHarness::generate('root-2');
        $keys = [$this->keyRow($this->root)];

        // Signed by a key the registry never pinned (TM-SC-04).
        $forged = $attacker->mintRotation($successor);
        $this->expectCode('unknown_key', fn () => $this->verifier->verifyRotation(
            $forged['json'], $forged['signature'], 'evil-1', $keys, $this->now,
        ));

        // old_key_id in the document does not match the signing key.
        $mismatch = $attacker->mintRotation($successor); // old_key_id = 'evil-1'
        $this->expectCode('rotation_signer_mismatch', fn () => $this->verifier->verifyRotation(
            $mismatch['json'], $this->root->sign($mismatch['json']), 'root-1', $keys, $this->now,
        ));

        // Garbage successor key material.
        $badMaterial = $this->root->mintRotation($successor);
        $payload = json_decode($badMaterial['json'], true);
        $payload['new_public_key'] = base64_encode('short');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->expectCode('rotation_key_material', fn () => $this->verifier->verifyRotation(
            (string) $json, $this->root->sign((string) $json), 'root-1', $keys, $this->now,
        ));

        // A rotation must name a *different* successor id.
        $selfRotation = $this->root->mintRotation(SigningHarness::generate('root-1'));
        $this->expectCode('rotation_key_id', fn () => $this->verifier->verifyRotation(
            $selfRotation['json'], $selfRotation['signature'], 'root-1', $keys, $this->now,
        ));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Security/Registry/ 2>&1 | tail -5`
Expected: `Error: Class "App\Security\Registry\PackageIdentity" not found` (and the same for `TrustChainVerifier`).

- [ ] **Step 3: Implement the four classes**

Create `src/Security/Registry/PackageIdentity.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * Canonical package/registry identity (P5-01). Package identity is globally
 * namespaced `publisher/name`; registry sources are one lowercase label.
 * Malformed identity FAILS CLOSED — it never enters the catalogue.
 */
final class PackageIdentity
{
    private const UID = '/^[a-z0-9][a-z0-9\-_.]{0,92}\/[a-z0-9][a-z0-9\-_.]{0,92}$/';
    private const SOURCE = '/^[a-z0-9][a-z0-9\-_.]{0,92}$/';

    public static function isValidUid(string $uid): bool
    {
        return preg_match(self::UID, $uid) === 1;
    }

    /** The namespace prefix of a valid uid ("acme" for "acme/midnight-theme"). */
    public static function publisherUid(string $uid): string
    {
        return explode('/', $uid, 2)[0];
    }

    public static function isValidSourceId(string $sourceId): bool
    {
        return preg_match(self::SOURCE, $sourceId) === 1;
    }
}
```

Create `src/Security/Registry/RegistryVerificationException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * A fail-closed refusal from the registry trust chain. `code` is a stable
 * machine token (telemetry + tests match on it); the message is operator-facing.
 */
final class RegistryVerificationException extends \RuntimeException
{
    public function __construct(public readonly string $code, string $message)
    {
        parent::__construct($message);
    }
}
```

Create `src/Security/Registry/VerifiedDocument.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Registry;

/** A signature-verified, format-checked rb-*.v1 document. */
final class VerifiedDocument
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly string $format,
        public readonly array $payload,
        public readonly string $keyId,
    ) {
    }
}
```

Create `src/Security/Registry/TrustChainVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Registry;

/**
 * Pure Ed25519 trust-chain verifier for rb-*.v1 signed documents (P5-01 SP2).
 *
 * PUBLIC-KEY-ONLY: candidate keys are registry_trust_keys rows (public bytes);
 * private material never reaches this class (custody runbook A4 §1). Every
 * failure is a coded RegistryVerificationException — callers fail closed.
 * Signature comparison is libsodium's constant-time verify; nothing here
 * compares secret-derived bytes in PHP.
 */
final class TrustChainVerifier
{
    /** Tolerated clock skew for future-dated documents (checked by callers). */
    public const CLOCK_SKEW_SECONDS = 300;

    private const FORMATS = [
        'rb-registry-snapshot.v1',
        'rb-release.v1',
        'rb-advisory.v1',
        'rb-key-rotation.v1',
    ];

    /**
     * @param list<array<string,mixed>> $trustKeys registry_trust_keys rows for ONE registry
     */
    public function verify(
        string $documentJson,
        string $signature,
        string $keyId,
        array $trustKeys,
        string $expectedFormat,
        \DateTimeImmutable $now,
    ): VerifiedDocument {
        if (!in_array($expectedFormat, self::FORMATS, true)) {
            throw new RegistryVerificationException('wrong_format', 'Unsupported document format: ' . $expectedFormat . '.');
        }

        $key = $this->selectKey($keyId, $trustKeys, $now, requireActive: false);

        $valid = false;
        try {
            $valid = sodium_crypto_sign_verify_detached($signature, $documentJson, (string) $key['public_key']);
        } catch (\SodiumException) {
            $valid = false;
        }
        if (!$valid) {
            throw new RegistryVerificationException('bad_signature', 'Detached signature does not verify for key ' . $keyId . '.');
        }

        $payload = json_decode($documentJson, true);
        if (!is_array($payload)) {
            throw new RegistryVerificationException('malformed_document', 'Signed document is not a JSON object.');
        }
        if (($payload['format'] ?? null) !== $expectedFormat) {
            throw new RegistryVerificationException('wrong_format', 'Expected ' . $expectedFormat . ', got ' . (string) ($payload['format'] ?? '(none)') . '.');
        }

        return new VerifiedDocument($expectedFormat, $payload, $keyId);
    }

    /**
     * A rotation transition must be signed by a currently ACTIVE key (custody
     * runbook §5.3): rotated or revoked keys cannot introduce a successor, and
     * the successor cannot introduce itself.
     *
     * @param list<array<string,mixed>> $trustKeys
     * @return array{key_id:string,public_key:string} successor key material (raw bytes)
     */
    public function verifyRotation(
        string $documentJson,
        string $signature,
        string $keyId,
        array $trustKeys,
        \DateTimeImmutable $now,
    ): array {
        $this->selectKey($keyId, $trustKeys, $now, requireActive: true);
        $doc = $this->verify($documentJson, $signature, $keyId, $trustKeys, 'rb-key-rotation.v1', $now);

        if ((string) ($doc->payload['old_key_id'] ?? '') !== $keyId) {
            throw new RegistryVerificationException('rotation_signer_mismatch', 'Rotation old_key_id must equal the signing key id.');
        }
        $newKeyId = trim((string) ($doc->payload['new_key_id'] ?? ''));
        if ($newKeyId === '' || $newKeyId === $keyId) {
            throw new RegistryVerificationException('rotation_key_id', 'Rotation must name a distinct new_key_id.');
        }
        $material = base64_decode((string) ($doc->payload['new_public_key'] ?? ''), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RegistryVerificationException('rotation_key_material', 'new_public_key must be a base64 32-byte Ed25519 public key.');
        }

        return ['key_id' => $newKeyId, 'public_key' => $material];
    }

    /** @param list<array<string,mixed>> $trustKeys @return array<string,mixed> */
    private function selectKey(string $keyId, array $trustKeys, \DateTimeImmutable $now, bool $requireActive): array
    {
        $key = null;
        foreach ($trustKeys as $row) {
            if ((string) $row['key_id'] === $keyId) {
                $key = $row;
                break;
            }
        }
        if ($key === null) {
            throw new RegistryVerificationException('unknown_key', 'No pinned trust key with id ' . $keyId . '.');
        }
        if ((string) $key['algorithm'] !== 'ed25519') {
            throw new RegistryVerificationException('algorithm', 'Only ed25519 trust keys are accepted.');
        }

        $status = (string) $key['status'];
        if ($status === 'revoked') {
            throw new RegistryVerificationException('revoked_key', 'Trust key ' . $keyId . ' is revoked.');
        }
        if ($requireActive && $status !== 'active') {
            throw new RegistryVerificationException('inactive_key', 'A rotation must be signed by a currently active key.');
        }
        if (!in_array($status, ['active', 'rotated'], true)) {
            throw new RegistryVerificationException('inactive_key', 'Trust key ' . $keyId . ' is not usable.');
        }

        $from = $key['valid_from'] ?? null;
        if ($from !== null && $now < new \DateTimeImmutable((string) $from, new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('key_window', 'Trust key ' . $keyId . ' is not yet valid.');
        }
        $until = $key['valid_until'] ?? null;
        if ($until !== null && $now > new \DateTimeImmutable((string) $until, new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('key_window', 'Trust key ' . $keyId . ' is outside its validity window.');
        }

        return $key;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Security/Registry/ 2>&1 | tail -3`
Expected: `OK (10 tests, ...)` — all green, no warnings.

- [ ] **Step 5: Commit**

```bash
git add src/Security/Registry/ tests/Unit/Security/Registry/
git commit -m "feat(phase5): pure fail-closed Ed25519 trust-chain verifier + package identity (Inc 2, P5-01 SP2)"
```

---

### Task 2: Thin repositories over the `0049` tables

Seven final repositories, constructor `(private Database $db)`, prepared statements, assoc arrays out. No business rules here — services own those.

**Files:**
- Create: `src/Repository/PackageRegistryRepository.php`
- Create: `src/Repository/RegistryTrustKeyRepository.php`
- Create: `src/Repository/PackagePublisherRepository.php`
- Create: `src/Repository/PackageRepository.php`
- Create: `src/Repository/PackageReleaseRepository.php`
- Create: `src/Repository/PackageAdvisoryRepository.php`
- Create: `src/Repository/LocalPackageBlockRepository.php`
- Test: `tests/Integration/Repository/RegistryRepositoriesTest.php`

**Interfaces:**
- Consumes: `App\Core\Database` (`run`/`fetch`/`fetchAll`/`fetchValue`/`insert`); `Tests\Support\Phase5\{SigningHarness,RegistryFixtures}` in the test.
- Produces: the method signatures from *Locked interfaces*, used verbatim by Tasks 4–9.

- [ ] **Step 1: Write the failing integration test**

Create `tests/Integration/Repository/RegistryRepositoriesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryRepositoriesTest extends TestCase
{
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private function fixture(): array
    {
        return RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
    }

    public function test_registry_rows_round_trip(): void
    {
        $ids = $this->fixture();
        $repo = new PackageRegistryRepository($this->db);

        $row = $repo->findBySourceId('rb-test');
        self::assertNotNull($row);
        self::assertSame($ids['registry_id'], (int) $row['id']);
        self::assertSame([], $repo->enabled(), 'seeded registry starts dark');

        $repo->setEnabled($ids['registry_id'], true);
        self::assertCount(1, $repo->enabled());

        $repo->recordSnapshot($ids['registry_id'], str_repeat('a', 64), '2026-07-02 00:00:00', '2026-07-03 00:00:00');
        $row = $repo->find($ids['registry_id']);
        self::assertSame(str_repeat('a', 64), $row['last_snapshot_digest']);
        self::assertSame('2026-07-03 00:00:00', $row['snapshot_expires_at']);

        $newId = $repo->create('rb-two', 'Second', 'https://two.invalid');
        self::assertNotNull($repo->find($newId));
        self::assertCount(2, $repo->all());
    }

    public function test_trust_key_lifecycle_rows(): void
    {
        $ids = $this->fixture();
        $repo = new RegistryTrustKeyRepository($this->db);

        $keys = $repo->forRegistry($ids['registry_id']);
        self::assertCount(1, $keys);
        self::assertSame('root-1', $keys[0]['key_id']);
        self::assertNotNull($repo->findKey($ids['registry_id'], 'root-1'));

        $successor = SigningHarness::generate('root-2');
        $newId = $repo->pin($ids['registry_id'], 'root-2', $successor->publicKey(), null, null);
        self::assertSame('active', $repo->find($newId)['status']);

        $repo->markRotated($ids['trust_key_id']);
        $old = $repo->find($ids['trust_key_id']);
        self::assertSame('rotated', $old['status']);
        self::assertNotNull($old['valid_until']);

        $repo->revoke($newId, 'compromise drill');
        $revoked = $repo->find($newId);
        self::assertSame('revoked', $revoked['status']);
        self::assertSame('compromise drill', $revoked['revoked_reason']);
        self::assertNotNull($revoked['revoked_at']);
    }

    public function test_publisher_ensure_is_idempotent(): void
    {
        $this->fixture();
        $repo = new PackagePublisherRepository($this->db);
        $existing = $repo->findByUid('acme');
        self::assertNotNull($existing);
        self::assertSame((int) $existing['id'], $repo->ensure('acme', 'Acme Themes'));
        $fresh = $repo->ensure('umbrella', 'Umbrella Corp');
        self::assertSame($fresh, (int) $repo->findByUid('umbrella')['id']);
    }

    public function test_package_and_release_rows(): void
    {
        $ids = $this->fixture();
        $packages = new PackageRepository($this->db);
        $releases = new PackageReleaseRepository($this->db);

        $pkg = $packages->findByUid('acme/midnight-theme');
        self::assertNotNull($pkg);
        self::assertSame('none', $pkg['advisory_status']);

        $rel = $releases->findVersion($ids['package_id'], '1.0.0');
        self::assertNotNull($rel);
        self::assertNull($releases->findVersion($ids['package_id'], '9.9.9'));

        $newRelease = $releases->create([
            'package_id' => $ids['package_id'],
            'version' => '1.1.0',
            'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.1.0'),
            'source_url' => null,
            'license' => 'MIT',
            'core_min' => '0.1.0',
            'core_max' => null,
            'channel' => 'stable',
        ]);
        self::assertCount(2, $releases->forPackage($ids['package_id']));

        $packages->setLatestRelease($ids['package_id'], $newRelease);
        self::assertSame($newRelease, (int) $packages->find($ids['package_id'])['latest_release_id']);

        $packages->setAdvisoryStatus($ids['package_id'], 'warned');
        $releases->setAdvisoryStatus($newRelease, 'blocked');
        self::assertSame('warned', $packages->find($ids['package_id'])['advisory_status']);
        self::assertSame('blocked', $releases->find($newRelease)['advisory_status']);

        $catalog = $packages->catalog();
        self::assertCount(1, $catalog);
        self::assertSame('rb-test', $catalog[0]['registry_source_id']);
        self::assertSame('Acme Themes', $catalog[0]['publisher_name']);
    }

    public function test_advisories_upsert_and_acknowledge(): void
    {
        $ids = $this->fixture();
        $repo = new PackageAdvisoryRepository($this->db);
        $admin = $this->makeAdmin();

        $advisoryId = $repo->upsert([
            'advisory_uid' => 'RB-TEST-0001',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'high',
            'action' => 'warn',
            'summary' => 'first pass',
            'signed_evidence' => '{"doc":1}',
            'issued_at' => '2026-07-01 00:00:00',
        ]);
        // Re-ingest with an escalated action updates in place (same uid, same row).
        $again = $repo->upsert([
            'advisory_uid' => 'RB-TEST-0001',
            'registry_id' => $ids['registry_id'],
            'package_id' => $ids['package_id'],
            'affected_version_range' => '<=1.0.0',
            'affected_digest' => null,
            'severity' => 'critical',
            'action' => 'block_new',
            'summary' => 'escalated',
            'signed_evidence' => '{"doc":2}',
            'issued_at' => '2026-07-02 00:00:00',
        ]);
        self::assertSame($advisoryId, $again);
        $row = $repo->findByUid('RB-TEST-0001');
        self::assertSame('block_new', $row['action']);
        self::assertSame('critical', $row['severity']);
        self::assertCount(1, $repo->forPackage($ids['package_id']));
        self::assertCount(1, $repo->all());

        $repo->acknowledge($advisoryId, (int) $admin['id']);
        $row = $repo->findByUid('RB-TEST-0001');
        self::assertNotNull($row['acknowledged_at']);
        self::assertSame((int) $admin['id'], (int) $row['acknowledged_by']);
    }

    public function test_local_blocks(): void
    {
        $this->fixture();
        $repo = new LocalPackageBlockRepository($this->db);
        $admin = $this->makeAdmin();
        $digest = str_repeat('b', 64);

        self::assertFalse($repo->isBlocked($digest, null));
        $blockId = $repo->add($digest, null, 'incident 42', (int) $admin['id']);
        self::assertTrue($repo->isBlocked($digest, null));
        self::assertTrue($repo->isBlocked($digest, 'acme/midnight-theme'));
        self::assertFalse($repo->isBlocked(null, 'acme/midnight-theme'));

        $uidBlock = $repo->add(null, 'acme/midnight-theme', null, null);
        self::assertTrue($repo->isBlocked(null, 'acme/midnight-theme'));
        self::assertCount(2, $repo->all());

        $repo->remove($blockId);
        self::assertFalse($repo->isBlocked($digest, null));
        self::assertNotNull($repo->find($uidBlock));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/RegistryRepositoriesTest.php 2>&1 | tail -4`
Expected: `Error: Class "App\Repository\PackageRegistryRepository" not found`.

- [ ] **Step 3: Implement the seven repositories**

Create `src/Repository/PackageRegistryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Registry sources (`package_registries`, migration 0049). */
final class PackageRegistryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM package_registries ORDER BY id ASC');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM package_registries WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySourceId(string $sourceId): ?array
    {
        return $this->db->fetch('SELECT * FROM package_registries WHERE source_id = ?', [$sourceId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function enabled(): array
    {
        return $this->db->fetchAll('SELECT * FROM package_registries WHERE is_enabled = 1 ORDER BY id ASC');
    }

    public function create(string $sourceId, string $displayName, string $baseUrl): int
    {
        return $this->db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 0)',
            [$sourceId, $displayName, $baseUrl],
        );
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->db->run('UPDATE package_registries SET is_enabled = ? WHERE id = ?', [$enabled ? 1 : 0, $id]);
    }

    /** Record the last verified snapshot (digest + doc-declared window). */
    public function recordSnapshot(int $id, string $digest, string $generatedAt, string $expiresAt): void
    {
        $this->db->run(
            'UPDATE package_registries SET last_snapshot_digest = ?, last_snapshot_at = ?, snapshot_expires_at = ? WHERE id = ?',
            [$digest, $generatedAt, $expiresAt, $id],
        );
    }
}
```

Create `src/Repository/RegistryTrustKeyRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** PUBLIC trust-key material only (`registry_trust_keys`, 0049; A4 §1). */
final class RegistryTrustKeyRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function forRegistry(int $registryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM registry_trust_keys WHERE registry_id = ? ORDER BY id DESC',
            [$registryId],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM registry_trust_keys WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findKey(int $registryId, string $keyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_trust_keys WHERE registry_id = ? AND key_id = ?',
            [$registryId, $keyId],
        );
    }

    public function pin(int $registryId, string $keyId, string $publicKey, ?string $validFrom, ?string $validUntil): int
    {
        return $this->db->insert(
            'INSERT INTO registry_trust_keys (registry_id, key_id, algorithm, public_key, status, valid_from, valid_until)
             VALUES (?, ?, \'ed25519\', ?, \'active\', ?, ?)',
            [$registryId, $keyId, $publicKey, $validFrom, $validUntil],
        );
    }

    /** Rotated keys stop signing anything new: window closes now. */
    public function markRotated(int $id): void
    {
        $this->db->run(
            "UPDATE registry_trust_keys SET status = 'rotated', valid_until = UTC_TIMESTAMP() WHERE id = ?",
            [$id],
        );
    }

    public function revoke(int $id, string $reason): void
    {
        $this->db->run(
            "UPDATE registry_trust_keys SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ?",
            [$reason, $id],
        );
    }
}
```

Create `src/Repository/PackagePublisherRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Publisher identities (`package_publishers`, 0049). */
final class PackagePublisherRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $publisherUid): ?array
    {
        return $this->db->fetch('SELECT * FROM package_publishers WHERE publisher_uid = ?', [$publisherUid]);
    }

    /** Find-or-create by uid; snapshot ingest derives the uid from the package namespace. */
    public function ensure(string $publisherUid, string $displayName): int
    {
        $existing = $this->findByUid($publisherUid);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO package_publishers (publisher_uid, display_name) VALUES (?, ?)',
            [$publisherUid, $displayName],
        );
    }
}
```

Create `src/Repository/PackageRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Registry package identities (`packages`, 0049). */
final class PackageRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $packageUid): ?array
    {
        return $this->db->fetch('SELECT * FROM packages WHERE package_uid = ?', [$packageUid]);
    }

    /** @param array{package_uid:string,registry_id:?int,publisher_id:?int,name:string,type:string,trust_class:string} $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO packages (package_uid, registry_id, publisher_id, name, type, trust_class)
             VALUES (:package_uid, :registry_id, :publisher_id, :name, :type, :trust_class)',
            $row,
        );
    }

    public function setLatestRelease(int $id, ?int $releaseId): void
    {
        $this->db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$releaseId, $id]);
    }

    public function setAdvisoryStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE packages SET advisory_status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * Read model for the staff catalogue (registry + publisher labels joined in,
     * `ModerationLogRepository::recent()` precedent).
     *
     * @return array<int,array<string,mixed>>
     */
    public function catalog(): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, r.source_id AS registry_source_id, r.display_name AS registry_name,
                    pub.publisher_uid, pub.display_name AS publisher_name
             FROM packages p
             LEFT JOIN package_registries r ON r.id = p.registry_id
             LEFT JOIN package_publishers pub ON pub.id = p.publisher_id
             ORDER BY p.package_uid ASC',
        );
    }
}
```

Create `src/Repository/PackageReleaseRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Immutable releases (`package_releases`, 0049): one row = one exact (version, digest). */
final class PackageReleaseRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM package_releases WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findVersion(int $packageId, string $version): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM package_releases WHERE package_id = ? AND version = ?',
            [$packageId, $version],
        );
    }

    /** @param array{package_id:int,version:string,digest:string,source_url:?string,license:?string,core_min:?string,core_max:?string,channel:string} $row */
    public function create(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO package_releases (package_id, version, digest, source_url, license, core_min, core_max, channel, published_at)
             VALUES (:package_id, :version, :digest, :source_url, :license, :core_min, :core_max, :channel, UTC_TIMESTAMP())',
            $row,
        );
    }

    /** @return array<int,array<string,mixed>> newest first */
    public function forPackage(int $packageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_releases WHERE package_id = ? ORDER BY id DESC',
            [$packageId],
        );
    }

    public function setAdvisoryStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE package_releases SET advisory_status = ? WHERE id = ?', [$status, $id]);
    }
}
```

Create `src/Repository/PackageAdvisoryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Cached signed advisories (`package_advisories`, 0049). */
final class PackageAdvisoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $advisoryUid): ?array
    {
        return $this->db->fetch('SELECT * FROM package_advisories WHERE advisory_uid = ?', [$advisoryUid]);
    }

    /**
     * Insert or refresh by advisory_uid (re-ingest of an escalated advisory
     * updates in place; acknowledgements survive because ack columns are not
     * touched here). Runs inside the caller's transaction.
     *
     * @param array{advisory_uid:string,registry_id:?int,package_id:?int,affected_version_range:?string,affected_digest:?string,severity:string,action:string,summary:?string,signed_evidence:?string,issued_at:?string} $row
     */
    public function upsert(array $row): int
    {
        $existing = $this->findByUid($row['advisory_uid']);
        if ($existing === null) {
            return $this->db->insert(
                'INSERT INTO package_advisories
                   (advisory_uid, registry_id, package_id, affected_version_range, affected_digest, severity, action, summary, signed_evidence, issued_at)
                 VALUES (:advisory_uid, :registry_id, :package_id, :affected_version_range, :affected_digest, :severity, :action, :summary, :signed_evidence, :issued_at)',
                $row,
            );
        }

        $this->db->run(
            'UPDATE package_advisories
                SET registry_id = :registry_id, package_id = :package_id,
                    affected_version_range = :affected_version_range, affected_digest = :affected_digest,
                    severity = :severity, action = :action, summary = :summary,
                    signed_evidence = :signed_evidence, issued_at = :issued_at
              WHERE id = :id',
            [
                'registry_id' => $row['registry_id'],
                'package_id' => $row['package_id'],
                'affected_version_range' => $row['affected_version_range'],
                'affected_digest' => $row['affected_digest'],
                'severity' => $row['severity'],
                'action' => $row['action'],
                'summary' => $row['summary'],
                'signed_evidence' => $row['signed_evidence'],
                'issued_at' => $row['issued_at'],
                'id' => (int) $existing['id'],
            ],
        );

        return (int) $existing['id'];
    }

    /** @return array<int,array<string,mixed>> */
    public function forPackage(int $packageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_advisories WHERE package_id = ? ORDER BY id DESC',
            [$packageId],
        );
    }

    /** @return array<int,array<string,mixed>> newest first, package uid joined for the admin list */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, p.package_uid
             FROM package_advisories a
             LEFT JOIN packages p ON p.id = a.package_id
             ORDER BY a.id DESC',
        );
    }

    public function acknowledge(int $id, int $userId): void
    {
        $this->db->run(
            'UPDATE package_advisories SET acknowledged_at = UTC_TIMESTAMP(), acknowledged_by = ? WHERE id = ?',
            [$userId, $id],
        );
    }
}
```

Create `src/Repository/LocalPackageBlockRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Registry-INDEPENDENT local emergency blocklist (`local_package_blocks`,
 * 0049): a blocked digest/uid refuses regardless of registry availability or
 * trust-root state (custody runbook §5.4, decision #40).
 */
final class LocalPackageBlockRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM local_package_blocks ORDER BY id DESC');
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM local_package_blocks WHERE id = ?', [$id]);
    }

    public function add(?string $digest, ?string $packageUid, ?string $reason, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO local_package_blocks (digest, package_uid, reason, created_by) VALUES (?, ?, ?, ?)',
            [$digest, $packageUid, $reason, $createdBy],
        );
    }

    public function remove(int $id): void
    {
        $this->db->run('DELETE FROM local_package_blocks WHERE id = ?', [$id]);
    }

    /** True when the exact digest OR the whole package identity is blocked. */
    public function isBlocked(?string $digest, ?string $packageUid): bool
    {
        if ($digest !== null) {
            $hit = $this->db->fetchValue('SELECT 1 FROM local_package_blocks WHERE digest = ? LIMIT 1', [$digest]);
            if ($hit !== false && $hit !== null) {
                return true;
            }
        }
        if ($packageUid !== null) {
            $hit = $this->db->fetchValue('SELECT 1 FROM local_package_blocks WHERE package_uid = ? LIMIT 1', [$packageUid]);
            if ($hit !== false && $hit !== null) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Repository/RegistryRepositoriesTest.php 2>&1 | tail -3`
Expected: `OK (6 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Repository/PackageRegistryRepository.php src/Repository/RegistryTrustKeyRepository.php \
        src/Repository/PackagePublisherRepository.php src/Repository/PackageRepository.php \
        src/Repository/PackageReleaseRepository.php src/Repository/PackageAdvisoryRepository.php \
        src/Repository/LocalPackageBlockRepository.php tests/Integration/Repository/RegistryRepositoriesTest.php
git commit -m "feat(phase5): thin repositories over the 0049 registry tables (Inc 2, P5-01 SP1)"
```

---

### Task 3: Migration `0068` — snapshot cache + `moderation_log.target_type` widen, `RegistrySnapshotRepository`, SCHEMA.md

The one schema change of the increment (§C allocation). `registry_snapshots` is the **offline cache**: the last verified snapshot document survives a registry outage so staff can keep browsing cached signed metadata within policy, and its `generated_at` history is the anti-replay watermark. The enum widen gives the new audit rows first-class targets.

**Files:**
- Create: `database/migrations/0068_phase5_registry_snapshots.php`
- Create: `src/Repository/RegistrySnapshotRepository.php`
- Modify: `SCHEMA.md` (table inventory + `0049` section neighbourhood + §9 changelog v1.27)
- Test: `tests/Integration/Repository/RegistrySnapshotRepositoryTest.php`

**Interfaces:**
- Produces: table `registry_snapshots(id, registry_id, digest, document, signature, key_id, generated_at, expires_at, applied_at)` with `UNIQUE (registry_id, digest)`; `moderation_log.target_type` gains `'registry'` and `'package'`; repository methods `latestFor(int): ?array`, `findByDigest(int,string): ?array`, `record(int,string,string,string,string,string,string): int`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Repository/RegistrySnapshotRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ModerationLogRepository;
use App\Repository\RegistrySnapshotRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistrySnapshotRepositoryTest extends TestCase
{
    public function test_snapshot_rows_round_trip_and_latest_is_by_generated_at(): void
    {
        $ids = RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
        $repo = new RegistrySnapshotRepository($this->db);
        $registryId = $ids['registry_id'];

        self::assertNull($repo->latestFor($registryId));

        $repo->record($registryId, str_repeat('a', 64), '{"v":1}', 'sig-a', 'root-1', '2026-07-01 00:00:00', '2026-07-02 00:00:00');
        $repo->record($registryId, str_repeat('b', 64), '{"v":2}', 'sig-b', 'root-1', '2026-07-02 00:00:00', '2026-07-03 00:00:00');

        $latest = $repo->latestFor($registryId);
        self::assertNotNull($latest);
        self::assertSame(str_repeat('b', 64), $latest['digest']);
        self::assertSame('{"v":2}', $latest['document']);

        self::assertNotNull($repo->findByDigest($registryId, str_repeat('a', 64)));
        self::assertNull($repo->findByDigest($registryId, str_repeat('c', 64)));
    }

    public function test_moderation_log_accepts_the_new_target_types(): void
    {
        $log = new ModerationLogRepository($this->db);
        $a = $log->log(['actor_id' => null, 'action' => 'registry_pin_key', 'target_type' => 'registry', 'target_id' => 1]);
        $b = $log->log(['actor_id' => null, 'action' => 'package_block', 'target_type' => 'package', 'target_id' => 0]);
        self::assertGreaterThan(0, $a);
        self::assertGreaterThan($a, $b);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/RegistrySnapshotRepositoryTest.php 2>&1 | tail -4`
Expected: class-not-found for the repository; the moderation_log test would fail with a `SQLSTATE[01000]: Warning: 1265 Data truncated` / constraint error on the enum until `0068` lands (bootstrap re-migrates every run, so just add both files in the next step).

- [ ] **Step 3: Write the migration and repository**

Create `database/migrations/0068_phase5_registry_snapshots.php`:

```php
<?php

declare(strict_types=1);

/**
 * 0068 · Phase 5 Increment 2 (P5-01 SP4) — verified-snapshot offline cache +
 * moderation_log target widen for registry/package audit rows.
 *
 * ADDITIVE. `registry_snapshots` caches the last VERIFIED signed catalogue
 * snapshots per registry: the document bytes + detached signature are kept so
 * (a) staff catalogue browse keeps working from cached signed metadata during
 * a registry outage (PHASE_5_PLAN §9 "Registry outage"), and (b) the
 * generated_at history is the anti-replay watermark (a re-presented older
 * snapshot refuses, PHASE_5_PLAN §3). Only documents that passed the
 * TrustChainVerifier are ever written here.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE registry_snapshots (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              registry_id  BIGINT UNSIGNED NOT NULL,
              digest       CHAR(64)        NOT NULL,             -- sha256 hex of the exact signed document bytes
              document     MEDIUMTEXT      NOT NULL,             -- offline cache of the verified snapshot JSON
              signature    VARBINARY(1024) NOT NULL,             -- detached ed25519 signature over `document`
              key_id       VARCHAR(190)    NOT NULL,             -- registry_trust_keys.key_id that verified it
              generated_at DATETIME        NOT NULL,             -- doc-declared; anti-replay watermark
              expires_at   DATETIME        NOT NULL,             -- doc-declared freshness window (D2: 24h)
              applied_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_snapshot_digest (registry_id, digest),
              KEY idx_snapshot_generated (registry_id, generated_at),
              CONSTRAINT fk_snapshot_registry FOREIGN KEY (registry_id) REFERENCES package_registries(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // Widen the audit-target enum for trust-root / blocklist / advisory
        // operator actions (0057 precedent).
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook','registry','package') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type IN ('registry','package')");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS registry_snapshots');
    }
};
```

Create `src/Repository/RegistrySnapshotRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Verified-snapshot offline cache + anti-replay watermark (`registry_snapshots`, 0068). */
final class RegistrySnapshotRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null the newest applied snapshot by doc-declared generated_at */
    public function latestFor(int $registryId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_snapshots WHERE registry_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1',
            [$registryId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findByDigest(int $registryId, string $digest): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM registry_snapshots WHERE registry_id = ? AND digest = ?',
            [$registryId, $digest],
        );
    }

    public function record(
        int $registryId,
        string $digest,
        string $document,
        string $signature,
        string $keyId,
        string $generatedAt,
        string $expiresAt,
    ): int {
        return $this->db->insert(
            'INSERT INTO registry_snapshots (registry_id, digest, document, signature, key_id, generated_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$registryId, $digest, $document, $signature, $keyId, $generatedAt, $expiresAt],
        );
    }
}
```

- [ ] **Step 4: Migrate and run the tests**

```bash
php bin/console migrate
vendor/bin/phpunit tests/Integration/Repository/RegistrySnapshotRepositoryTest.php tests/Unit/Core/MigrationLedgerTest.php 2>&1 | tail -3
```
Expected: `Applied 1 migration(s).` then `OK` for both files (the ledger guard confirms `0068` is the next gapless number).

- [ ] **Step 5: Hand-update SCHEMA.md**

Three edits (CLAUDE.md rule — shape + changelog + version):

1. In the numbered table inventory (the block listing `| 55 | package_registries | Ecosystem | 5 | ... |`), add the next number after the current last row:
   `| <next#> | registry_snapshots | Ecosystem | 5 | PHASE_5_PLAN §8.2 #1 (offline snapshot cache, migration 0068) |`
2. In the ecosystem table-shape section (immediately after the `registry_trust_keys(...)` bullet around line 1010), add:
   `- registry_snapshots(id, registry_id, digest, document, signature, key_id, generated_at, expires_at, applied_at)` with unique `(registry_id, digest)` and registry FK; verified-snapshot offline cache + anti-replay watermark (0068). — and extend the documented `moderation_log.target_type` enum mention to include `registry`,`package`.
3. Prepend to the §9 changelog table (top row) and bump the version header:
   `| v1.27 | 2026-07-02 | Phase 5 Increment 2 migration 0068: added registry_snapshots (verified-snapshot offline cache / anti-replay watermark for P5-01) and widened moderation_log.target_type with 'registry' and 'package' for trust-root, blocklist, and advisory audit rows. |`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/0068_phase5_registry_snapshots.php src/Repository/RegistrySnapshotRepository.php \
        tests/Integration/Repository/RegistrySnapshotRepositoryTest.php SCHEMA.md
git commit -m "feat(phase5): migration 0068 - registry_snapshots offline cache + moderation_log registry/package targets (Inc 2)"
```

---

### Task 4: `RegistrySnapshotService` — verify → freshness → anti-replay → source-pinning → transactional catalogue apply

The stateful half of the protocol. A snapshot is applied only when: the signature verifies against a pinned key (Task 1), it is not expired (TM-SC-02), not future-dated beyond skew, strictly newer than the last applied snapshot (anti-replay; identical digest = idempotent no-op), every entry has valid canonical identity, no entry claims a `package_uid` owned by another registry (TM-SC-05 dependency-confusion/source-substitution), no entry rewrites an existing `(version)` with a different digest (release immutability, decision #16), and no entry asserts a local trust tier. **One hostile entry refuses the whole snapshot** — an applied snapshot implies every entry passed.

**Files:**
- Create: `src/Service/Registry/RegistrySnapshotService.php`
- Test: `tests/Integration/Service/RegistrySnapshotServiceTest.php`

**Interfaces:**
- Consumes: Task 1 verifier (`verify(...)`, `RegistryVerificationException`), Task 2 repos, Task 3 `RegistrySnapshotRepository`, `App\Core\Telemetry` (nullable), `App\Support\CoreVersion` (not needed here — compatibility is a *read-time* concern in Task 8).
- Produces: `applySnapshot(int, string, string, string, ?\DateTimeImmutable = null): array{status:string,packages:int,releases:int}` (`status` ∈ `'applied'|'unchanged'`), `isFresh(array $registryRow, ?\DateTimeImmutable = null): bool`. New exception codes (same class): `malformed_snapshot`, `expired_snapshot`, `future_snapshot`, `replayed_snapshot`, `invalid_uid`, `uid_conflict`, `entry_type`, `entry_trust_class`, `entry_release`, `release_digest_rewrite`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Service/RegistrySnapshotServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\RegistrySnapshotService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** TM-SC-01/02 end-to-end on the ingest path; TM-SC-05 source substitution. */
final class RegistrySnapshotServiceTest extends TestCase
{
    private SigningHarness $root;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
    }

    private function service(): RegistrySnapshotService
    {
        return new RegistrySnapshotService(
            $this->db,
            new TrustChainVerifier(),
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new RegistrySnapshotRepository($this->db),
            new PackagePublisherRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
        );
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    private function snapshot(array $overrides = []): array
    {
        return $this->root->mintSnapshot($overrides);
    }

    private function expectCode(string $code, callable $fn): void
    {
        try {
            $fn();
            self::fail("expected RegistryVerificationException($code)");
        } catch (RegistryVerificationException $e) {
            self::assertSame($code, $e->code);
        }
    }

    public function test_a_valid_snapshot_applies_and_reapply_is_an_unchanged_noop(): void
    {
        $snap = $this->snapshot(['packages' => [[
            'uid' => 'acme/midnight-theme',
            'type' => 'theme',
            'name' => 'Midnight Theme',
            'releases' => [
                ['version' => '1.0.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
                ['version' => '1.1.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.1.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
            ],
        ]]]);

        $out = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('applied', $out['status']);
        self::assertSame(1, $out['packages']);
        self::assertSame(1, $out['releases'], 'only 1.1.0 is new; 1.0.0 exists from the fixture');

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        $latest = (new PackageReleaseRepository($this->db))->find((int) $pkg['latest_release_id']);
        self::assertSame('1.1.0', $latest['version'], 'latest pointer follows the highest stable version');

        $registry = (new PackageRegistryRepository($this->db))->find($this->ids['registry_id']);
        self::assertSame(hash('sha256', $snap['json']), $registry['last_snapshot_digest']);
        self::assertTrue($this->service()->isFresh($registry));

        $again = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('unchanged', $again['status']);
    }

    public function test_new_packages_create_publisher_and_package_rows(): void
    {
        $snap = $this->snapshot(['packages' => [[
            'uid' => 'umbrella/hive-automation',
            'type' => 'automation',
            'name' => 'Hive Automation',
            'releases' => [['version' => '0.9.0', 'digest' => str_repeat('c', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'beta', 'advisory' => 'none']],
        ]]]);
        $out = $this->service()->applySnapshot($this->ids['registry_id'], $snap['json'], $snap['signature'], $snap['key_id']);
        self::assertSame('applied', $out['status']);

        $pkg = (new PackageRepository($this->db))->findByUid('umbrella/hive-automation');
        self::assertNotNull($pkg);
        self::assertSame('automation', $pkg['type']);
        self::assertSame('reviewed_declarative', $pkg['trust_class']);
        self::assertNull($pkg['latest_release_id'], 'a beta-only package has no stable latest');
        self::assertNotNull((new PackagePublisherRepository($this->db))->findByUid('umbrella'));
    }

    public function test_expired_replayed_future_and_tampered_snapshots_refuse(): void
    {
        $svc = $this->service();
        $rid = $this->ids['registry_id'];

        $expired = $this->root->mintExpiredSnapshot();
        $this->expectCode('expired_snapshot', fn () => $svc->applySnapshot($rid, $expired['json'], $expired['signature'], $expired['key_id']));

        $future = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400)]);
        $this->expectCode('future_snapshot', fn () => $svc->applySnapshot($rid, $future['json'], $future['signature'], $future['key_id']));

        $now = $this->snapshot();
        $svc->applySnapshot($rid, $now['json'], $now['signature'], $now['key_id']);
        $older = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600)]);
        $this->expectCode('replayed_snapshot', fn () => $svc->applySnapshot($rid, $older['json'], $older['signature'], $older['key_id']));

        $tampered = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 60)]);
        $this->expectCode('bad_signature', fn () => $svc->applySnapshot($rid, SigningHarness::tamper($tampered['json']), $tampered['signature'], $tampered['key_id']));
    }

    public function test_source_substitution_cannot_claim_a_pinned_uid(): void
    {
        // A second registry with its own trusted root presents a snapshot
        // claiming the uid pinned to rb-test (TM-SC-05).
        $registries = new PackageRegistryRepository($this->db);
        $otherId = $registries->create('rb-evil', 'Evil Mirror', 'https://mirror.invalid');
        $otherRoot = SigningHarness::generate('evil-root');
        (new RegistryTrustKeyRepository($this->db))->pin($otherId, 'evil-root', $otherRoot->publicKey(), null, null);

        $snap = $otherRoot->mintSnapshot(); // default payload claims acme/midnight-theme
        $this->expectCode('uid_conflict', fn () => $this->service()->applySnapshot($otherId, $snap['json'], $snap['signature'], 'evil-root'));

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        self::assertSame($this->ids['registry_id'], (int) $pkg['registry_id'], 'ownership unchanged');
    }

    public function test_release_immutability_identity_and_trust_class_rules(): void
    {
        $svc = $this->service();
        $rid = $this->ids['registry_id'];

        // Same version, different digest = attempted in-place replacement.
        $rewrite = $this->snapshot(['packages' => [[
            'uid' => 'acme/midnight-theme', 'type' => 'theme',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('d', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('release_digest_rewrite', fn () => $svc->applySnapshot($rid, $rewrite['json'], $rewrite['signature'], $rewrite['key_id']));

        $badUid = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 1), 'packages' => [[
            'uid' => '../etc/passwd', 'type' => 'theme',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('e', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('invalid_uid', fn () => $svc->applySnapshot($rid, $badUid['json'], $badUid['signature'], $badUid['key_id']));

        $selfTrust = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 2), 'packages' => [[
            'uid' => 'acme/sneaky', 'type' => 'theme', 'trust_class' => 'first_party',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('f', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('entry_trust_class', fn () => $svc->applySnapshot($rid, $selfTrust['json'], $selfTrust['signature'], $selfTrust['key_id']));

        $badType = $this->snapshot(['generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3), 'packages' => [[
            'uid' => 'acme/odd', 'type' => 'rootkit',
            'releases' => [['version' => '1.0.0', 'digest' => str_repeat('a', 64), 'core_min' => null, 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none']],
        ]]]);
        $this->expectCode('entry_type', fn () => $svc->applySnapshot($rid, $badType['json'], $badType['signature'], $badType['key_id']));

        // A refused snapshot applies NOTHING (whole-snapshot atomicity).
        self::assertNull((new PackageRepository($this->db))->findByUid('acme/sneaky'));
        self::assertNull((new PackageRepository($this->db))->findByUid('acme/odd'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistrySnapshotServiceTest.php 2>&1 | tail -4`
Expected: `Error: Class "App\Service\Registry\RegistrySnapshotService" not found`.

- [ ] **Step 3: Implement the service**

Create `src/Service/Registry/RegistrySnapshotService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\PackageIdentity;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;

/**
 * Verifies and applies signed catalogue snapshots (P5-01 SP2/SP4).
 *
 * Fail-closed and atomic: signature/trust (TrustChainVerifier), doc freshness
 * (24h window, D2), anti-replay (strictly monotonic generated_at; identical
 * digest is an idempotent no-op), canonical identity, source pinning (a
 * package_uid stays owned by the registry that introduced it — dependency
 * confusion refusal), release immutability (a (version) can never change
 * digest — decision #16), and no registry-asserted local trust tier. ONE bad
 * entry refuses the WHOLE snapshot, so an applied snapshot implies every entry
 * passed. Applied documents are cached in registry_snapshots (offline cache).
 */
final class RegistrySnapshotService
{
    private const TYPES = ['theme', 'automation', 'remote_app', 'server_extension', 'local'];
    private const REGISTRY_TRUST_CLASSES = ['reviewed_declarative', 'reviewed_remote', 'isolated_server', 'local_dev'];
    private const CHANNELS = ['stable', 'beta', 'dev'];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private PackageRegistryRepository $registries,
        private RegistryTrustKeyRepository $trustKeys,
        private RegistrySnapshotRepository $snapshots,
        private PackagePublisherRepository $publishers,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{status:string,packages:int,releases:int} */
    public function applySnapshot(int $registryId, string $documentJson, string $signature, string $keyId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $registry = $this->registries->find($registryId);
        if ($registry === null) {
            throw new \RuntimeException("Unknown registry id $registryId.");
        }

        try {
            $result = $this->verifyAndApply($registryId, $documentJson, $signature, $keyId, $now);
        } catch (RegistryVerificationException $e) {
            $this->telemetry?->emit('registry.snapshot', [
                'registry' => (string) $registry['source_id'],
                'result' => 'refused',
                'reason' => $e->code,
            ]);
            throw $e;
        }

        $this->telemetry?->emit('registry.snapshot', [
            'registry' => (string) $registry['source_id'],
            'result' => $result['status'],
            'packages' => $result['packages'],
            'releases' => $result['releases'],
            'digest' => hash('sha256', $documentJson),
        ]);

        return $result;
    }

    /** Freshness = the doc-declared expiry window has not lapsed (D2: 24h cadence). */
    public function isFresh(array $registryRow, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expires = $registryRow['snapshot_expires_at'] ?? null;

        return $expires !== null && $now <= new \DateTimeImmutable((string) $expires, new \DateTimeZone('UTC'));
    }

    /** @return array{status:string,packages:int,releases:int} */
    private function verifyAndApply(int $registryId, string $documentJson, string $signature, string $keyId, \DateTimeImmutable $now): array
    {
        $doc = $this->verifier->verify(
            $documentJson,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry($registryId),
            'rb-registry-snapshot.v1',
            $now,
        );

        $generatedAt = $this->parseUtc($doc->payload['generated_at'] ?? null);
        $expiresAt = $this->parseUtc($doc->payload['expires_at'] ?? null);
        $entries = $doc->payload['packages'] ?? null;
        if ($generatedAt === null || $expiresAt === null || !is_array($entries)) {
            throw new RegistryVerificationException('malformed_snapshot', 'Snapshot must carry generated_at, expires_at, and a packages list.');
        }
        if ($expiresAt <= $now) {
            throw new RegistryVerificationException('expired_snapshot', 'Snapshot expired at ' . $expiresAt->format('Y-m-d H:i:s') . ' UTC; refusing install decisions until refreshed.');
        }
        if ($generatedAt > $now->modify('+' . TrustChainVerifier::CLOCK_SKEW_SECONDS . ' seconds')) {
            throw new RegistryVerificationException('future_snapshot', 'Snapshot generated_at is in the future beyond tolerated skew.');
        }

        $digest = hash('sha256', $documentJson);
        if ($this->snapshots->findByDigest($registryId, $digest) !== null) {
            return ['status' => 'unchanged', 'packages' => count($entries), 'releases' => 0];
        }
        $latest = $this->snapshots->latestFor($registryId);
        if ($latest !== null && $generatedAt <= new \DateTimeImmutable((string) $latest['generated_at'], new \DateTimeZone('UTC'))) {
            throw new RegistryVerificationException('replayed_snapshot', 'Snapshot is not newer than the last applied snapshot (anti-replay).');
        }

        return $this->db->transaction(function () use ($registryId, $documentJson, $signature, $keyId, $digest, $generatedAt, $expiresAt, $entries): array {
            $newReleases = 0;
            foreach ($entries as $entry) {
                $newReleases += $this->applyEntry($registryId, is_array($entry) ? $entry : []);
            }

            $this->snapshots->record(
                $registryId,
                $digest,
                $documentJson,
                $signature,
                $keyId,
                $generatedAt->format('Y-m-d H:i:s'),
                $expiresAt->format('Y-m-d H:i:s'),
            );
            $this->registries->recordSnapshot($registryId, $digest, $generatedAt->format('Y-m-d H:i:s'), $expiresAt->format('Y-m-d H:i:s'));

            return ['status' => 'applied', 'packages' => count($entries), 'releases' => $newReleases];
        });
    }

    /** @param array<string,mixed> $entry @return int newly created releases */
    private function applyEntry(int $registryId, array $entry): int
    {
        $uid = (string) ($entry['uid'] ?? '');
        if (!PackageIdentity::isValidUid($uid)) {
            throw new RegistryVerificationException('invalid_uid', "Snapshot entry has a malformed package uid: '$uid'.");
        }
        $type = (string) ($entry['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new RegistryVerificationException('entry_type', "Snapshot entry '$uid' has unknown type '$type'.");
        }
        $trustClass = (string) ($entry['trust_class'] ?? 'reviewed_declarative');
        if (!in_array($trustClass, self::REGISTRY_TRUST_CLASSES, true)) {
            // first_party / vetted are LOCAL trust decisions; a registry can
            // never assert them (PHASE_5_PLAN §5 #4).
            throw new RegistryVerificationException('entry_trust_class', "Snapshot entry '$uid' asserts a non-registry trust class '$trustClass'.");
        }

        $package = $this->packages->findByUid($uid);
        if ($package !== null && (int) $package['registry_id'] !== $registryId) {
            throw new RegistryVerificationException('uid_conflict', "Package '$uid' is pinned to another source; refusing source substitution.");
        }
        if ($package === null) {
            $publisherUid = PackageIdentity::publisherUid($uid);
            $publisherId = $this->publishers->ensure($publisherUid, (string) ($entry['publisher_name'] ?? $publisherUid));
            $packageId = $this->packages->create([
                'package_uid' => $uid,
                'registry_id' => $registryId,
                'publisher_id' => $publisherId,
                'name' => (string) ($entry['name'] ?? explode('/', $uid, 2)[1]),
                'type' => $type,
                'trust_class' => $trustClass,
            ]);
        } else {
            $packageId = (int) $package['id'];
        }

        $created = 0;
        foreach ((array) ($entry['releases'] ?? []) as $release) {
            $created += $this->applyRelease($packageId, $uid, is_array($release) ? $release : []);
        }
        $this->packages->setLatestRelease($packageId, $this->latestStableId($packageId));

        return $created;
    }

    /** @param array<string,mixed> $release @return int 1 when a row was created */
    private function applyRelease(int $packageId, string $uid, array $release): int
    {
        $version = trim((string) ($release['version'] ?? ''));
        $digest = strtolower((string) ($release['digest'] ?? ''));
        $channel = (string) ($release['channel'] ?? 'stable');
        if ($version === '' || strlen($version) > 64 || preg_match('/^[0-9a-f]{64}$/', $digest) !== 1 || !in_array($channel, self::CHANNELS, true)) {
            throw new RegistryVerificationException('entry_release', "Snapshot release for '$uid' is malformed (version/digest/channel).");
        }

        $existing = $this->releases->findVersion($packageId, $version);
        if ($existing !== null) {
            if (!hash_equals((string) $existing['digest'], $digest)) {
                throw new RegistryVerificationException('release_digest_rewrite', "Snapshot tries to change the digest of '$uid' $version; releases are immutable (a changed byte is a new release).");
            }

            return 0; // already known, byte-identical
        }

        $this->releases->create([
            'package_id' => $packageId,
            'version' => $version,
            'digest' => $digest,
            'source_url' => isset($release['source_url']) ? (string) $release['source_url'] : null,
            'license' => isset($release['license']) ? (string) $release['license'] : null,
            'core_min' => isset($release['core_min']) ? (string) $release['core_min'] : null,
            'core_max' => isset($release['core_max']) ? (string) $release['core_max'] : null,
            'channel' => $channel,
        ]);

        return 1;
    }

    /** Highest stable version by version_compare; matched by RepairService::repairPackageLatestReleases. */
    private function latestStableId(int $packageId): ?int
    {
        $best = null;
        foreach ($this->releases->forPackage($packageId) as $row) {
            if ((string) $row['channel'] !== 'stable') {
                continue;
            }
            if ($best === null || version_compare((string) $row['version'], (string) $best['version'], '>')) {
                $best = $row;
            }
        }

        return $best === null ? null : (int) $best['id'];
    }

    private function parseUtc(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistrySnapshotServiceTest.php 2>&1 | tail -3`
Expected: `OK (5 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Registry/RegistrySnapshotService.php tests/Integration/Service/RegistrySnapshotServiceTest.php
git commit -m "feat(phase5): signed snapshot ingest - freshness, anti-replay, source pinning, release immutability (Inc 2)"
```

---

### Task 5: `RegistryTrustService` — registry sources + trust-key pin / signed rotation / revocation

Operator-side trust-root lifecycle from the custody runbook: pin the offline-generated public key (§5.1), apply a signed rotation transition (§5.3), revoke on compromise (§5.4). All are high-impact trust-root changes: `WriteGate` + `ReauthGate` + a `moderation_log` audit row (`target_type='registry'`). TM-SC-04 gets its end-to-end fixture here.

**Files:**
- Create: `src/Service/Registry/RegistryTrustService.php`
- Test: `tests/Integration/Service/RegistryTrustServiceTest.php`

**Interfaces:**
- Consumes: `TrustChainVerifier::verifyRotation`, Task 2 repos, `ReauthGate::requirePassword(User, string)`, `WriteGate::assertCanWrite(User)`, `ModerationLogRepository::log`, `PackageIdentity::isValidSourceId`, `ValidationException(array $errors, array $old = [])`.
- Produces: the five methods from *Locked interfaces*. `applyRotation` lets `RegistryVerificationException` bubble (Task 9's controller maps it to a 422); everything else throws `ValidationException` for user errors.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Service/RegistryTrustServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Registry\RegistryTrustService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** TM-SC-04 end-to-end: only a transition signed by the active root can rotate. */
final class RegistryTrustServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function service(): RegistryTrustService
    {
        return new RegistryTrustService(
            $this->db,
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new TrustChainVerifier(),
            $this->reauthGate(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function auditCount(string $action): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_log WHERE action = ?', [$action]);
    }

    public function test_create_enable_and_disable_a_registry(): void
    {
        $svc = $this->service();
        $id = $svc->createRegistry($this->admin, 'password123', 'rb-main', 'Main Registry', 'https://registry.example');
        $registries = new PackageRegistryRepository($this->db);
        self::assertSame(0, (int) $registries->find($id)['is_enabled']);

        $svc->setEnabled($this->admin, 'password123', $id, true);
        self::assertSame(1, (int) $registries->find($id)['is_enabled']);

        // Disable is the defensive direction: no password needed.
        $svc->setEnabled($this->admin, null, $id, false);
        self::assertSame(0, (int) $registries->find($id)['is_enabled']);
        self::assertSame(1, $this->auditCount('registry_create'));
        self::assertSame(1, $this->auditCount('registry_enable'));
        self::assertSame(1, $this->auditCount('registry_disable'));

        foreach ([['rb main', 'https://x.example'], ['rb-main', 'https://dup.example'], ['rb-two', 'ftp://x.example']] as [$sourceId, $url]) {
            try {
                $svc->createRegistry($this->admin, 'password123', $sourceId, 'X', $url);
                self::fail("expected ValidationException for $sourceId $url");
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_reauth_gates_every_trust_mutation(): void
    {
        $svc = $this->service();
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);

        foreach ([
            fn () => $svc->createRegistry($this->admin, 'wrong', 'rb-x', 'X', 'https://x.example'),
            fn () => $svc->setEnabled($this->admin, 'wrong', $this->ids['registry_id'], true),
            fn () => $svc->pinKey($this->admin, 'wrong', $this->ids['registry_id'], 'k2', base64_encode($successor->publicKey()), null, null),
            fn () => $svc->applyRotation($this->admin, 'wrong', $this->ids['registry_id'], $rotation['json'], $rotation['signature'], $rotation['key_id']),
            fn () => $svc->revokeKey($this->admin, 'wrong', $this->ids['trust_key_id'], 'drill'),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('expected ValidationException (reauth)');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('current_password', $e->errors);
            }
        }
    }

    public function test_pin_key_validates_material_and_uniqueness(): void
    {
        $svc = $this->service();
        $fresh = SigningHarness::generate('root-2');
        $keyRowId = $svc->pinKey($this->admin, 'password123', $this->ids['registry_id'], 'root-2', base64_encode($fresh->publicKey()), null, null);
        $row = (new RegistryTrustKeyRepository($this->db))->find($keyRowId);
        self::assertSame('active', $row['status']);
        self::assertSame($fresh->publicKey(), $row['public_key']);
        self::assertSame(1, $this->auditCount('registry_pin_key'));

        foreach ([
            ['root-3', base64_encode('too-short')],
            ['root-3', 'not-base64!!'],
            ['root-1', base64_encode($fresh->publicKey())], // duplicate key_id for this registry
            ['', base64_encode($fresh->publicKey())],
        ] as [$keyId, $material]) {
            try {
                $svc->pinKey($this->admin, 'password123', $this->ids['registry_id'], $keyId, $material, null, null);
                self::fail("expected ValidationException for key '$keyId'");
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_signed_rotation_pins_the_successor_and_retires_the_old_key(): void
    {
        $svc = $this->service();
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);

        $newRowId = $svc->applyRotation($this->admin, 'password123', $this->ids['registry_id'], $rotation['json'], $rotation['signature'], $rotation['key_id']);

        $keys = new RegistryTrustKeyRepository($this->db);
        self::assertSame('active', $keys->find($newRowId)['status']);
        self::assertSame('root-2', $keys->find($newRowId)['key_id']);
        self::assertSame('rotated', $keys->find($this->ids['trust_key_id'])['status']);
        self::assertSame(1, $this->auditCount('registry_rotate_key'));
    }

    public function test_forged_rotation_is_rejected_and_pins_nothing(): void
    {
        $svc = $this->service();
        $attacker = SigningHarness::generate('evil-1');
        $forged = $attacker->mintRotation(SigningHarness::generate('root-2'));

        try {
            $svc->applyRotation($this->admin, 'password123', $this->ids['registry_id'], $forged['json'], $forged['signature'], $forged['key_id']);
            self::fail('expected RegistryVerificationException');
        } catch (RegistryVerificationException $e) {
            self::assertSame('unknown_key', $e->code);
        }
        self::assertNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));
        self::assertSame(0, $this->auditCount('registry_rotate_key'));
    }

    public function test_revoked_key_fails_verification_afterwards(): void
    {
        $svc = $this->service();
        $svc->revokeKey($this->admin, 'password123', $this->ids['trust_key_id'], 'compromise drill');

        $snap = $this->root->mintSnapshot();
        try {
            (new TrustChainVerifier())->verify(
                $snap['json'], $snap['signature'], 'root-1',
                (new RegistryTrustKeyRepository($this->db))->forRegistry($this->ids['registry_id']),
                'rb-registry-snapshot.v1',
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
            self::fail('expected revoked_key');
        } catch (RegistryVerificationException $e) {
            self::assertSame('revoked_key', $e->code);
        }
        self::assertSame(1, $this->auditCount('registry_revoke_key'));
    }
}
```

Note: `$this->reauthGate()` is not a `TestCase` helper — define this private method in this test class (and again in Task 6's; `ReauthGate` wraps only the `PasswordHasher`, see `App::buildContainer()` ~line 646 and `tests/Integration/Security/ReauthGateTest.php`):

```php
    private function reauthGate(): ReauthGate
    {
        return new ReauthGate(new \App\Security\PasswordHasher());
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistryTrustServiceTest.php 2>&1 | tail -4`
Expected: class-not-found for `RegistryTrustService`.

- [ ] **Step 3: Implement the service**

Create `src/Service/Registry/RegistryTrustService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\PackageIdentity;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;

/**
 * Trust-root lifecycle for package registries (P5-01, custody runbook A4 §5).
 *
 * Every mutation is a high-impact trust-root change: WriteGate + recent
 * reauthentication + an immutable moderation_log row (target_type 'registry').
 * The ONE deliberate exception is disabling a registry — the defensive
 * direction stays friction-free. PUBLIC key material only, always.
 */
final class RegistryTrustService
{
    public function __construct(
        private Database $db,
        private PackageRegistryRepository $registries,
        private RegistryTrustKeyRepository $trustKeys,
        private TrustChainVerifier $verifier,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    public function createRegistry(User $admin, string $currentPassword, string $sourceId, string $displayName, string $baseUrl): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $errors = [];
        $sourceId = trim($sourceId);
        $displayName = trim($displayName);
        $baseUrl = trim($baseUrl);
        if (!PackageIdentity::isValidSourceId($sourceId)) {
            $errors['source_id'] = 'Source id must be one lowercase label (a-z, 0-9, ., -, _).';
        } elseif ($this->registries->findBySourceId($sourceId) !== null) {
            $errors['source_id'] = 'A registry with this source id already exists.';
        }
        if ($displayName === '' || mb_strlen($displayName) > 190) {
            $errors['display_name'] = 'A display name between 1 and 190 characters is required.';
        }
        $scheme = strtolower((string) (parse_url($baseUrl, PHP_URL_SCHEME) ?? ''));
        if (strlen($baseUrl) > 512 || !in_array($scheme, ['https', 'http'], true) || parse_url($baseUrl, PHP_URL_HOST) === null) {
            $errors['base_url'] = 'Base URL must be a valid http(s) URL (EgressGuard still applies at fetch time).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['source_id' => $sourceId, 'display_name' => $displayName, 'base_url' => $baseUrl]);
        }

        return $this->db->transaction(function () use ($admin, $sourceId, $displayName, $baseUrl): int {
            $id = $this->registries->create($sourceId, $displayName, $baseUrl);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_create',
                'target_type' => 'registry',
                'target_id' => $id,
                'after' => ['source_id' => $sourceId, 'base_url' => $baseUrl],
            ]);

            return $id;
        });
    }

    /** Enabling is a trust decision (reauth); disabling is defensive (no password). */
    public function setEnabled(User $admin, ?string $currentPassword, int $registryId, bool $enabled): void
    {
        $this->writeGate->assertCanWrite($admin);
        if ($enabled) {
            $this->reauth->requirePassword($admin, (string) $currentPassword);
        }
        $registry = $this->requireRegistry($registryId);

        $this->db->transaction(function () use ($admin, $registry, $registryId, $enabled): void {
            $this->registries->setEnabled($registryId, $enabled);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $enabled ? 'registry_enable' : 'registry_disable',
                'target_type' => 'registry',
                'target_id' => $registryId,
                'before' => ['is_enabled' => (int) $registry['is_enabled']],
                'after' => ['is_enabled' => $enabled ? 1 : 0],
            ]);
        });
    }

    public function pinKey(User $admin, string $currentPassword, int $registryId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requireRegistry($registryId);

        $errors = [];
        $keyId = trim($keyId);
        if ($keyId === '' || mb_strlen($keyId) > 190) {
            $errors['key_id'] = 'A key id between 1 and 190 characters is required.';
        } elseif ($this->trustKeys->findKey($registryId, $keyId) !== null) {
            $errors['key_id'] = 'This key id is already pinned for the registry.';
        }
        $material = base64_decode(trim($publicKeyBase64), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $errors['public_key'] = 'Public key must be the base64 of exactly 32 Ed25519 public-key bytes.';
        }
        $validFrom = $this->normalizeDate($validFrom, $errors, 'valid_from');
        $validUntil = $this->normalizeDate($validUntil, $errors, 'valid_until');
        if ($errors !== []) {
            throw new ValidationException($errors, ['key_id' => $keyId, 'public_key' => $publicKeyBase64]);
        }

        return $this->db->transaction(function () use ($admin, $registryId, $keyId, $material, $validFrom, $validUntil): int {
            $rowId = $this->trustKeys->pin($registryId, $keyId, (string) $material, $validFrom, $validUntil);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_pin_key',
                'target_type' => 'registry',
                'target_id' => $registryId,
                'after' => ['key_id' => $keyId, 'fingerprint' => substr(hash('sha256', (string) $material), 0, 16)],
            ]);

            return $rowId;
        });
    }

    /**
     * Custody §5.3: the successor is introduced ONLY through a transition signed
     * by the currently active key. RegistryVerificationException bubbles to the
     * controller (422). Returns the new trust-key row id.
     */
    public function applyRotation(User $admin, string $currentPassword, int $registryId, string $documentJson, string $signature, string $keyId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requireRegistry($registryId);

        $successor = $this->verifier->verifyRotation(
            $documentJson,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry($registryId),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($this->trustKeys->findKey($registryId, $successor['key_id']) !== null) {
            throw new ValidationException(['rotation' => 'The successor key id is already pinned.']);
        }
        $oldRow = $this->trustKeys->findKey($registryId, $keyId);

        return $this->db->transaction(function () use ($admin, $registryId, $successor, $oldRow, $keyId): int {
            $rowId = $this->trustKeys->pin($registryId, $successor['key_id'], $successor['public_key'], null, null);
            if ($oldRow !== null) {
                $this->trustKeys->markRotated((int) $oldRow['id']);
            }
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_rotate_key',
                'target_type' => 'registry',
                'target_id' => $registryId,
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
        $key = $this->trustKeys->find($keyRowId);
        if ($key === null) {
            throw new ValidationException(['key' => 'Trust key not found.']);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A revocation reason between 1 and 255 characters is required.']);
        }

        $this->db->transaction(function () use ($admin, $key, $keyRowId, $reason): void {
            $this->trustKeys->revoke($keyRowId, $reason);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_revoke_key',
                'target_type' => 'registry',
                'target_id' => (int) $key['registry_id'],
                'before' => ['key_id' => (string) $key['key_id'], 'status' => (string) $key['status']],
                'after' => ['status' => 'revoked', 'reason' => $reason],
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function requireRegistry(int $registryId): array
    {
        $registry = $this->registries->find($registryId);
        if ($registry === null) {
            throw new ValidationException(['registry' => 'Registry not found.']);
        }

        return $registry;
    }

    /** @param array<string,string> $errors */
    private function normalizeDate(?string $value, array &$errors, string $field): ?string
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            $errors[$field] = 'Use a UTC datetime (YYYY-MM-DD HH:MM:SS) or leave blank.';

            return null;
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistryTrustServiceTest.php 2>&1 | tail -3`
Expected: `OK (6 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Registry/RegistryTrustService.php tests/Integration/Service/RegistryTrustServiceTest.php
git commit -m "feat(phase5): trust-root lifecycle - reauth-gated pin, signed rotation, revocation, audited (Inc 2)"
```

---

### Task 6: Advisories, local blocklist, and the `RepairService` recomputes

The security-response layer. Signed advisories ingest through the same verifier and escalate `advisory_status` down the ladder (`warn→warned`, `block_new→blocked`, `force_disable→blocked`*, `revoke→revoked`; escalate-only — an advisory can never *lower* a status). The local blocklist is the registry-independent brake: adding a block is friction-free; removing one (re-enabling) needs reauth. Because this increment starts writing the denormalized `packages.latest_release_id` and `advisory_status` columns, `RepairService` gains matching recomputes (CLAUDE.md counter rule).

\* `force_disable` also disables an *installed* package — installs do not exist until Inc 3; the ladder comment records that handoff so `PackageSecurityGate` (Inc 3) consumes the same map.

**Files:**
- Create: `src/Service/Registry/RegistryAdvisoryService.php`
- Create: `src/Service/Registry/LocalBlocklistService.php`
- Modify: `src/Service/RepairService.php` (add two methods + register in `repairAll()`)
- Test: `tests/Integration/Service/RegistryAdvisoryServiceTest.php`

**Interfaces:**
- Consumes: Task 1 verifier, Task 2 repos, `ReauthGate`, `WriteGate`, `ModerationLogRepository`, `Telemetry` (nullable).
- Produces: `RegistryAdvisoryService::ingest(int,string,string,string,?\DateTimeImmutable=null): array{advisory_id:int,action:string}`, `acknowledge(User,int): void`, `public static escalate(string $current, string $candidate): string`, `public static affectsVersion(?string $range, string $version): bool`; `LocalBlocklistService::block(User,?string,?string,?string): int`, `unblock(User,string,int): void`, `isBlocked(?string,?string): bool`; `RepairService::repairPackageLatestReleases(): int`, `repairPackageAdvisoryStatuses(): int`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Service/RegistryAdvisoryServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\RepairService;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryAdvisoryServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function advisories(): RegistryAdvisoryService
    {
        return new RegistryAdvisoryService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new ModerationLogRepository($this->db),
        );
    }

    private function blocklist(): LocalBlocklistService
    {
        return new LocalBlocklistService(
            new LocalPackageBlockRepository($this->db),
            new PackageRepository($this->db),
            $this->reauthGate(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    public function test_the_escalation_ladder_is_escalate_only(): void
    {
        self::assertSame('warned', RegistryAdvisoryService::escalate('none', 'warned'));
        self::assertSame('blocked', RegistryAdvisoryService::escalate('warned', 'blocked'));
        self::assertSame('revoked', RegistryAdvisoryService::escalate('blocked', 'revoked'));
        self::assertSame('revoked', RegistryAdvisoryService::escalate('revoked', 'warned'), 'never de-escalates');
        self::assertSame('blocked', RegistryAdvisoryService::escalate('blocked', 'none'));
    }

    public function test_version_range_grammar_fails_toward_affected(): void
    {
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<=1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<=1.0.0', '0.9.0'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('<=1.0.0', '1.1.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<2.0.0', '1.9.9'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('>1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('>=1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('1.0.0', '1.0.0'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('=1.0.0', '1.0.1'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('*', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion(null, '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('~nonsense~', '1.0.0'), 'malformed ranges affect everything (fail closed)');
    }

    public function test_signed_advisory_ingests_and_escalates_statuses(): void
    {
        $adv = $this->root->mintAdvisory(['action' => 'block_new', 'severity' => 'high']); // affects <=1.0.0
        $out = $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);
        self::assertSame('block_new', $out['action']);

        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        $rel = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);
        self::assertSame('blocked', $pkg['advisory_status']);
        self::assertSame('blocked', $rel['advisory_status']);

        // Re-ingest escalated to revoke: same advisory row, statuses climb.
        $rev = $this->root->mintAdvisory(['action' => 'revoke', 'severity' => 'critical']);
        $out2 = $this->advisories()->ingest($this->ids['registry_id'], $rev['json'], $rev['signature'], $rev['key_id']);
        self::assertSame($out['advisory_id'], $out2['advisory_id']);
        self::assertSame('revoked', (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status']);

        // Tampered advisory refuses.
        $bad = $this->root->mintAdvisory(['advisory_uid' => 'RB-TEST-0002']);
        $this->expectException(\App\Security\Registry\RegistryVerificationException::class);
        $this->advisories()->ingest($this->ids['registry_id'], SigningHarness::tamper($bad['json']), $bad['signature'], $bad['key_id']);
    }

    public function test_acknowledge_records_actor_and_audit(): void
    {
        $adv = $this->root->mintAdvisory();
        $out = $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);

        $this->advisories()->acknowledge($this->admin, $out['advisory_id']);
        $row = (new PackageAdvisoryRepository($this->db))->findByUid('RB-TEST-0001');
        self::assertNotNull($row['acknowledged_at']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'advisory_ack'"));
    }

    public function test_blocklist_add_is_frictionless_and_remove_needs_reauth(): void
    {
        $bl = $this->blocklist();
        $digest = (string) (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['digest'];

        $blockId = $bl->block($this->admin, $digest, null, 'incident drill');
        self::assertTrue($bl->isBlocked($digest, null));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_block'"));

        try {
            $bl->unblock($this->admin, 'wrong-password', $blockId);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertTrue($bl->isBlocked($digest, null));

        $bl->unblock($this->admin, 'password123', $blockId);
        self::assertFalse($bl->isBlocked($digest, null));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_unblock'"));

        try {
            $bl->block($this->admin, null, null, 'nothing targeted');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('target', $e->errors);
        }
    }

    public function test_repair_recomputes_the_denormalized_columns(): void
    {
        // Ingest an advisory, then corrupt the denormalized columns and repair.
        $adv = $this->root->mintAdvisory(['action' => 'block_new']);
        $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);

        $this->db->run('UPDATE packages SET latest_release_id = NULL, advisory_status = \'none\' WHERE id = ?', [$this->ids['package_id']]);
        $this->db->run('UPDATE package_releases SET advisory_status = \'none\' WHERE id = ?', [$this->ids['release_id']]);

        $repair = new RepairService($this->db);
        self::assertGreaterThan(0, $repair->repairPackageLatestReleases());
        self::assertGreaterThan(0, $repair->repairPackageAdvisoryStatuses());

        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        self::assertSame($this->ids['release_id'], (int) $pkg['latest_release_id']);
        self::assertSame('blocked', $pkg['advisory_status']);
        self::assertSame('blocked', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['advisory_status']);
    }
}
```

(Same `reauthGate()` note as Task 5.)

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistryAdvisoryServiceTest.php 2>&1 | tail -4`
Expected: class-not-found for `RegistryAdvisoryService`.

- [ ] **Step 3: Implement the two services**

Create `src/Service/Registry/RegistryAdvisoryService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\Telemetry;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;

/**
 * Signed-advisory ingest + evaluation (P5-01 SP5). Actions escalate down the
 * approved ladder and NEVER de-escalate:
 *
 *   warn → 'warned' · block_new → 'blocked' · force_disable → 'blocked'
 *   revoke → 'revoked'
 *
 * `force_disable` additionally disables an installed package — installs do not
 * exist until Inc 3; PackageSecurityGate (Inc 3, migration 0072) must consume
 * ACTION_STATUS + isBlocked() so install/enable fail closed on
 * blocked/revoked. De-escalation (advisory withdrawn) is a deliberate operator
 * action via repair, never automatic.
 */
final class RegistryAdvisoryService
{
    public const ACTION_STATUS = [
        'warn' => 'warned',
        'block_new' => 'blocked',
        'force_disable' => 'blocked',
        'revoke' => 'revoked',
    ];

    private const RANK = ['none' => 0, 'warned' => 1, 'blocked' => 2, 'revoked' => 3];

    public function __construct(
        private Database $db,
        private TrustChainVerifier $verifier,
        private RegistryTrustKeyRepository $trustKeys,
        private PackageAdvisoryRepository $advisories,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** Escalate-only merge of two advisory statuses. */
    public static function escalate(string $current, string $candidate): string
    {
        $currentRank = self::RANK[$current] ?? 0;
        $candidateRank = self::RANK[$candidate] ?? 0;

        return $candidateRank > $currentRank ? $candidate : $current;
    }

    /**
     * Range grammar: null/''/'*' = all; '<=X' '<X' '>=X' '>X' '=X' or a bare
     * exact version. A malformed range affects EVERYTHING — for a signed
     * security advisory the fail-closed direction is "affected".
     */
    public static function affectsVersion(?string $range, string $version): bool
    {
        $range = trim((string) $range);
        if ($range === '' || $range === '*') {
            return true;
        }
        if (preg_match('/^(<=|>=|<|>|=)?\s*(\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.\-]*)?)$/', $range, $m) !== 1) {
            return true;
        }
        $op = $m[1] === '' ? '=' : $m[1];
        $bound = $m[2];

        return match ($op) {
            '<=' => version_compare($version, $bound, '<='),
            '<' => version_compare($version, $bound, '<'),
            '>=' => version_compare($version, $bound, '>='),
            '>' => version_compare($version, $bound, '>'),
            default => version_compare($version, $bound, '=='),
        };
    }

    /** @return array{advisory_id:int,action:string} */
    public function ingest(int $registryId, string $documentJson, string $signature, string $keyId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $doc = $this->verifier->verify($documentJson, $signature, $keyId, $this->trustKeys->forRegistry($registryId), 'rb-advisory.v1', $now);

        $advisoryUid = trim((string) ($doc->payload['advisory_uid'] ?? ''));
        $action = (string) ($doc->payload['action'] ?? '');
        $severity = (string) ($doc->payload['severity'] ?? 'medium');
        if ($advisoryUid === '' || !isset(self::ACTION_STATUS[$action]) || !in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new RegistryVerificationException('malformed_advisory', 'Advisory must carry advisory_uid, a known action, and a known severity.');
        }

        $packageUid = trim((string) ($doc->payload['package_uid'] ?? ''));
        $package = $packageUid === '' ? null : $this->packages->findByUid($packageUid);
        $range = isset($doc->payload['affected_version_range']) ? (string) $doc->payload['affected_version_range'] : null;
        $affectedDigest = isset($doc->payload['affected_digest']) ? strtolower((string) $doc->payload['affected_digest']) : null;
        $issuedAt = isset($doc->payload['issued_at']) ? (string) $doc->payload['issued_at'] : null;
        try {
            $issuedAt = $issuedAt === null ? null : (new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            $issuedAt = null;
        }

        $result = $this->db->transaction(function () use ($registryId, $advisoryUid, $package, $range, $affectedDigest, $severity, $action, $doc, $documentJson, $issuedAt): array {
            $advisoryId = $this->advisories->upsert([
                'advisory_uid' => $advisoryUid,
                'registry_id' => $registryId,
                'package_id' => $package === null ? null : (int) $package['id'],
                'affected_version_range' => $range,
                'affected_digest' => $affectedDigest,
                'severity' => $severity,
                'action' => $action,
                'summary' => isset($doc->payload['summary']) ? mb_substr((string) $doc->payload['summary'], 0, 512) : null,
                'signed_evidence' => $documentJson,
                'issued_at' => $issuedAt,
            ]);

            if ($package !== null) {
                $this->evaluatePackage((int) $package['id']);
            }

            return ['advisory_id' => $advisoryId, 'action' => $action];
        });

        $this->telemetry?->emit('registry.advisory', [
            'advisory' => $advisoryUid,
            'action' => $action,
            'severity' => $severity,
            'package' => $packageUid !== '' ? $packageUid : null,
            'resolved' => $package !== null,
        ]);

        return $result;
    }

    /** Recompute one package's denormalized statuses from its advisory rows (escalate-only fold). */
    public function evaluatePackage(int $packageId): void
    {
        $rows = $this->advisories->forPackage($packageId);

        $packageStatus = 'none';
        foreach ($rows as $advisory) {
            $packageStatus = self::escalate($packageStatus, self::ACTION_STATUS[(string) $advisory['action']] ?? 'none');
        }
        $this->packages->setAdvisoryStatus($packageId, $packageStatus);

        foreach ($this->releases->forPackage($packageId) as $release) {
            $status = 'none';
            foreach ($rows as $advisory) {
                $digest = $advisory['affected_digest'] ?? null;
                $hit = $digest !== null
                    ? hash_equals((string) $digest, (string) $release['digest'])
                    : self::affectsVersion($advisory['affected_version_range'] ?? null, (string) $release['version']);
                if ($hit) {
                    $status = self::escalate($status, self::ACTION_STATUS[(string) $advisory['action']] ?? 'none');
                }
            }
            $this->releases->setAdvisoryStatus((int) $release['id'], $status);
        }
    }

    public function acknowledge(User $admin, int $advisoryId): void
    {
        $advisory = $this->db->fetch('SELECT * FROM package_advisories WHERE id = ?', [$advisoryId]);
        if ($advisory === null) {
            throw new ValidationException(['advisory' => 'Advisory not found.']);
        }

        $this->db->transaction(function () use ($admin, $advisory, $advisoryId): void {
            $this->advisories->acknowledge($advisoryId, $admin->id());
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'advisory_ack',
                'target_type' => 'package',
                'target_id' => (int) ($advisory['package_id'] ?? 0),
                'reason' => (string) $advisory['advisory_uid'],
            ]);
        });
    }
}
```

Create `src/Service/Registry/LocalBlocklistService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRepository;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * The registry-INDEPENDENT emergency brake (P5-01 SP5, decision #40). Adding a
 * block is deliberately friction-free (incident response must not wait on a
 * password prompt); REMOVING one re-enables execution eligibility and is the
 * privilege-relevant direction, so it requires recent reauthentication. Both
 * directions are audited. Inc 3's PackageSecurityGate consults isBlocked().
 */
final class LocalBlocklistService
{
    public function __construct(
        private LocalPackageBlockRepository $blocks,
        private PackageRepository $packages,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    public function block(User $admin, ?string $digest, ?string $packageUid, ?string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $digest = $digest === null ? null : strtolower(trim($digest));
        $packageUid = $packageUid === null ? null : trim($packageUid);
        $digest = $digest === '' ? null : $digest;
        $packageUid = $packageUid === '' ? null : $packageUid;

        $errors = [];
        if ($digest === null && $packageUid === null) {
            $errors['target'] = 'Block a release digest, a package uid, or both.';
        }
        if ($digest !== null && preg_match('/^[0-9a-f]{64}$/', $digest) !== 1) {
            $errors['digest'] = 'Digest must be 64 hex characters (sha256).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['digest' => (string) $digest, 'package_uid' => (string) $packageUid, 'reason' => (string) $reason]);
        }

        $package = $packageUid === null ? null : $this->packages->findByUid($packageUid);
        $blockId = $this->blocks->add($digest, $packageUid, $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255), $admin->id());
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => 'package_block',
            'target_type' => 'package',
            'target_id' => $package === null ? 0 : (int) $package['id'],
            'after' => ['digest' => $digest, 'package_uid' => $packageUid],
            'reason' => $reason,
        ]);

        return $blockId;
    }

    public function unblock(User $admin, string $currentPassword, int $blockId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $block = $this->blocks->find($blockId);
        if ($block === null) {
            throw new ValidationException(['block' => 'Blocklist entry not found.']);
        }

        $package = isset($block['package_uid']) && $block['package_uid'] !== null
            ? $this->packages->findByUid((string) $block['package_uid'])
            : null;
        $this->blocks->remove($blockId);
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => 'package_unblock',
            'target_type' => 'package',
            'target_id' => $package === null ? 0 : (int) $package['id'],
            'before' => ['digest' => $block['digest'], 'package_uid' => $block['package_uid']],
        ]);
    }

    public function isBlocked(?string $digest, ?string $packageUid): bool
    {
        return $this->blocks->isBlocked($digest, $packageUid);
    }
}
```

- [ ] **Step 4: Add the RepairService recomputes**

In `src/Service/RepairService.php`, add two public methods (mirror the style of the existing `repair*` methods) and register both in `repairAll()`'s result map as `'package_latest'` and `'package_advisory'`:

```php
    /**
     * Recompute packages.latest_release_id: highest stable version per package
     * (identical rule to RegistrySnapshotService::latestStableId).
     */
    public function repairPackageLatestReleases(): int
    {
        $changed = 0;
        foreach ($this->db->fetchAll('SELECT id, latest_release_id FROM packages') as $package) {
            $best = null;
            foreach ($this->db->fetchAll("SELECT id, version FROM package_releases WHERE package_id = ? AND channel = 'stable'", [(int) $package['id']]) as $release) {
                if ($best === null || version_compare((string) $release['version'], (string) $best['version'], '>')) {
                    $best = $release;
                }
            }
            $target = $best === null ? null : (int) $best['id'];
            $current = $package['latest_release_id'] === null ? null : (int) $package['latest_release_id'];
            if ($target !== $current) {
                $this->db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$target, (int) $package['id']]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Recompute packages/package_releases advisory_status from the cached
     * signed advisories — the exact escalate-only fold the ingest path applies
     * (shared code: RegistryAdvisoryService::escalate/affectsVersion/ACTION_STATUS).
     */
    public function repairPackageAdvisoryStatuses(): int
    {
        $changed = 0;
        foreach ($this->db->fetchAll('SELECT id, advisory_status FROM packages') as $package) {
            $advisories = $this->db->fetchAll('SELECT * FROM package_advisories WHERE package_id = ?', [(int) $package['id']]);

            $status = 'none';
            foreach ($advisories as $advisory) {
                $status = \App\Service\Registry\RegistryAdvisoryService::escalate(
                    $status,
                    \App\Service\Registry\RegistryAdvisoryService::ACTION_STATUS[(string) $advisory['action']] ?? 'none',
                );
            }
            if ($status !== (string) $package['advisory_status']) {
                $this->db->run('UPDATE packages SET advisory_status = ? WHERE id = ?', [$status, (int) $package['id']]);
                $changed++;
            }

            foreach ($this->db->fetchAll('SELECT id, version, digest, advisory_status FROM package_releases WHERE package_id = ?', [(int) $package['id']]) as $release) {
                $releaseStatus = 'none';
                foreach ($advisories as $advisory) {
                    $digest = $advisory['affected_digest'] ?? null;
                    $hit = $digest !== null
                        ? hash_equals((string) $digest, (string) $release['digest'])
                        : \App\Service\Registry\RegistryAdvisoryService::affectsVersion($advisory['affected_version_range'] ?? null, (string) $release['version']);
                    if ($hit) {
                        $releaseStatus = \App\Service\Registry\RegistryAdvisoryService::escalate(
                            $releaseStatus,
                            \App\Service\Registry\RegistryAdvisoryService::ACTION_STATUS[(string) $advisory['action']] ?? 'none',
                        );
                    }
                }
                if ($releaseStatus !== (string) $release['advisory_status']) {
                    $this->db->run('UPDATE package_releases SET advisory_status = ? WHERE id = ?', [$releaseStatus, (int) $release['id']]);
                    $changed++;
                }
            }
        }

        return $changed;
    }
```

And inside `repairAll()`, extend the returned map (match the existing key style):

```php
        $result['package_latest'] = $this->repairPackageLatestReleases();
        $result['package_advisory'] = $this->repairPackageAdvisoryStatuses();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Service/RegistryAdvisoryServiceTest.php 2>&1 | tail -3`
Expected: `OK (7 tests, ...)`. Then run the existing repair coverage to prove no regression: `vendor/bin/phpunit --filter Repair 2>&1 | tail -3` → green.

- [ ] **Step 6: Commit**

```bash
git add src/Service/Registry/RegistryAdvisoryService.php src/Service/Registry/LocalBlocklistService.php \
        src/Service/RepairService.php tests/Integration/Service/RegistryAdvisoryServiceTest.php
git commit -m "feat(phase5): advisory ladder + registry-independent blocklist + repair recomputes (Inc 2, P5-01 SP5)"
```

---

### Task 7: Fetch transport + `worker:registry-refresh` + config

The only network I/O of the increment, worker-only (decision #10). The transport mirrors `CurlWebhookTransport`: EgressGuard-validated, DNS-pinned via `CURLOPT_RESOLVE`, no redirects, hard byte cap. The worker walks enabled registries, fetches the snapshot envelope, applies it, then fetches the advisory envelope list (404 = none). Flag off ⇒ pure no-op (`worker:extensions` pattern).

**Files:**
- Create: `src/Service/Registry/RegistryFetchResult.php`
- Create: `src/Service/Registry/RegistryTransport.php`
- Create: `src/Service/Registry/CurlRegistryTransport.php`
- Create: `src/Service/Registry/ArrayRegistryTransport.php`
- Create: `src/Worker/RegistryRefreshWorker.php`
- Modify: `config/config.php` (add the `registry` block after `link_previews`, ~line 240)
- Modify: `bin/console` (new `worker:registry-refresh` case + help line + imports)
- Test: `tests/Integration/Worker/RegistryRefreshWorkerTest.php`

**Interfaces:**
- Consumes: `EgressGuard::validate`, Task 4/6 services, `PackageRegistryRepository::enabled()`.
- Produces: everything in *Locked interfaces* under Transport/Worker. Wire paths: `SNAPSHOT_PATH = '/rb-snapshot-envelope.v1.json'`, `ADVISORIES_PATH = '/rb-advisory-envelopes.v1.json'` (public consts on the worker).

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Worker/RegistryRefreshWorkerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\ModerationLogRepository;
use App\Security\Registry\TrustChainVerifier;
use App\Service\Registry\ArrayRegistryTransport;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistryFetchResult;
use App\Service\Registry\RegistrySnapshotService;
use App\Worker\RegistryRefreshWorker;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryRefreshWorkerTest extends TestCase
{
    private SigningHarness $root;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        (new PackageRegistryRepository($this->db))->setEnabled($this->ids['registry_id'], true);
    }

    /** @param array<string,RegistryFetchResult> $responses */
    private function worker(array $responses, bool $enabled = true): RegistryRefreshWorker
    {
        $snapshots = new RegistrySnapshotService(
            $this->db,
            new TrustChainVerifier(),
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new RegistrySnapshotRepository($this->db),
            new PackagePublisherRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
        );
        $advisories = new RegistryAdvisoryService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new ModerationLogRepository($this->db),
        );

        return new RegistryRefreshWorker(
            new PackageRegistryRepository($this->db),
            $snapshots,
            $advisories,
            new ArrayRegistryTransport($responses),
            $enabled,
        );
    }

    /** @return array{0:string,1:string} snapshot + advisory envelope bodies */
    private function envelopes(): array
    {
        $snap = $this->root->mintSnapshot(['packages' => [[
            'uid' => 'acme/midnight-theme', 'type' => 'theme',
            'releases' => [
                ['version' => '1.0.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
                ['version' => '1.2.0', 'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.2.0'), 'core_min' => '0.1.0', 'core_max' => null, 'channel' => 'stable', 'advisory' => 'none'],
            ],
        ]]]);
        $adv = $this->root->mintAdvisory(['action' => 'warn']);

        $snapshotEnvelope = json_encode([
            'format' => 'rb-snapshot-envelope.v1',
            'document' => $snap['json'],
            'signature' => base64_encode($snap['signature']),
            'key_id' => $snap['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $advisoryEnvelopes = json_encode([
            'format' => 'rb-advisory-envelopes.v1',
            'advisories' => [[
                'document' => $adv['json'],
                'signature' => base64_encode($adv['signature']),
                'key_id' => $adv['key_id'],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        return [(string) $snapshotEnvelope, (string) $advisoryEnvelopes];
    }

    public function test_refresh_applies_snapshot_and_advisories(): void
    {
        [$snapBody, $advBody] = $this->envelopes();
        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(200, $advBody, null),
        ])->run();

        self::assertSame(1, $stats['refreshed']);
        self::assertSame(1, $stats['advisories']);
        self::assertSame(0, $stats['failed']);

        $pkg = (new PackageRepository($this->db))->findByUid('acme/midnight-theme');
        self::assertSame('warned', $pkg['advisory_status']);
        self::assertSame('1.2.0', (new PackageReleaseRepository($this->db))->find((int) $pkg['latest_release_id'])['version']);
    }

    public function test_flag_off_is_a_pure_noop(): void
    {
        [$snapBody] = $this->envelopes();
        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
        ], enabled: false)->run();

        self::assertSame(['refreshed' => 0, 'unchanged' => 0, 'advisories' => 0, 'failed' => 0, 'skipped' => 1], $stats);
        self::assertNull((new RegistrySnapshotRepository($this->db))->latestFor($this->ids['registry_id']));
    }

    public function test_tampered_snapshot_counts_failed_and_missing_advisories_are_fine(): void
    {
        [$snapBody] = $this->envelopes();
        $decoded = json_decode($snapBody, true);
        $decoded['document'] = SigningHarness::tamper((string) $decoded['document']);
        $tampered = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES);

        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $tampered, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(404, '', null),
        ])->run();
        self::assertSame(1, $stats['failed']);
        self::assertSame(0, $stats['refreshed']);

        // Transport-level failure (oversize/egress/etc.) also fails safe.
        $stats = $this->worker([
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(0, '', 'response exceeded byte cap'),
        ])->run();
        self::assertSame(1, $stats['failed']);
    }

    public function test_second_run_with_the_same_snapshot_is_unchanged(): void
    {
        [$snapBody, $advBody] = $this->envelopes();
        $responses = [
            'https://registry.invalid' . RegistryRefreshWorker::SNAPSHOT_PATH => new RegistryFetchResult(200, $snapBody, null),
            'https://registry.invalid' . RegistryRefreshWorker::ADVISORIES_PATH => new RegistryFetchResult(200, $advBody, null),
        ];
        $this->worker($responses)->run();
        $stats = $this->worker($responses)->run();
        self::assertSame(['refreshed' => 0, 'unchanged' => 1, 'advisories' => 1, 'failed' => 0, 'skipped' => 0], $stats);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Worker/RegistryRefreshWorkerTest.php 2>&1 | tail -4`
Expected: class-not-found for `ArrayRegistryTransport` / `RegistryRefreshWorker`.

- [ ] **Step 3: Implement transport + worker**

Create `src/Service/Registry/RegistryFetchResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** One outbound registry fetch: status 0 + error = transport-level failure. */
final class RegistryFetchResult
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $error,
    ) {
    }
}
```

Create `src/Service/Registry/RegistryTransport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** Replaceable-seam boundary for registry fetches (DECISIONS §2 pattern). */
interface RegistryTransport
{
    public function fetch(string $url): RegistryFetchResult;
}
```

Create `src/Service/Registry/CurlRegistryTransport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;

/**
 * SSRF-hardened GET for registry documents (CurlWebhookTransport discipline):
 * EgressGuard-validated, DNS answer pinned via CURLOPT_RESOLVE, redirects
 * refused, response capped at maxBytes (a snapshot bigger than the D11 cap is
 * a refusal, not a partial read).
 */
final class CurlRegistryTransport implements RegistryTransport
{
    public function __construct(
        private EgressGuard $guard,
        private int $maxBytes = 1_048_576,
        private int $timeoutSeconds = 10,
    ) {
    }

    public function fetch(string $url): RegistryFetchResult
    {
        try {
            $ip = $this->guard->validate($url);
        } catch (EgressBlockedException $e) {
            return new RegistryFetchResult(0, '', 'egress blocked: ' . $e->getMessage());
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        $body = '';
        $overflow = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$overflow): int {
                $body .= $chunk;
                if (strlen($body) > $this->maxBytes) {
                    $overflow = true;

                    return -1;
                }

                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($overflow) {
            return new RegistryFetchResult(0, '', 'response exceeded ' . $this->maxBytes . ' byte cap');
        }
        if ($status === 0) {
            return new RegistryFetchResult(0, '', 'curl error ' . $errno);
        }

        return new RegistryFetchResult($status, $body, null);
    }
}
```

Create `src/Service/Registry/ArrayRegistryTransport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** Canned in-memory transport for tests (ArrayMailer pattern). Unknown URL = 404. */
final class ArrayRegistryTransport implements RegistryTransport
{
    /** @param array<string,RegistryFetchResult> $responses url => result */
    public function __construct(private array $responses)
    {
    }

    public function fetch(string $url): RegistryFetchResult
    {
        return $this->responses[$url] ?? new RegistryFetchResult(404, '', null);
    }
}
```

Create `src/Worker/RegistryRefreshWorker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Telemetry;
use App\Repository\PackageRegistryRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistrySnapshotService;
use App\Service\Registry\RegistryTransport;

/**
 * Cron worker keeping enabled registries inside the 24h freshness window
 * (P5-01 SP4, custody §6). Every refusal fails safe: the previously cached
 * verified snapshot stays authoritative, core routes never depend on this
 * (decision #10). Flag off = pure no-op (worker:extensions pattern).
 */
final class RegistryRefreshWorker
{
    public const SNAPSHOT_PATH = '/rb-snapshot-envelope.v1.json';
    public const ADVISORIES_PATH = '/rb-advisory-envelopes.v1.json';

    public function __construct(
        private PackageRegistryRepository $registries,
        private RegistrySnapshotService $snapshots,
        private RegistryAdvisoryService $advisories,
        private RegistryTransport $transport,
        private bool $enabled,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{refreshed:int,unchanged:int,advisories:int,failed:int,skipped:int} */
    public function run(): array
    {
        $stats = ['refreshed' => 0, 'unchanged' => 0, 'advisories' => 0, 'failed' => 0, 'skipped' => 0];
        if (!$this->enabled) {
            $stats['skipped'] = 1;

            return $stats;
        }

        foreach ($this->registries->enabled() as $registry) {
            $base = rtrim((string) $registry['base_url'], '/');
            try {
                $result = $this->applySnapshotFrom($base . self::SNAPSHOT_PATH, (int) $registry['id']);
                $stats[$result === 'applied' ? 'refreshed' : 'unchanged']++;
                $stats['advisories'] += $this->ingestAdvisoriesFrom($base . self::ADVISORIES_PATH, (int) $registry['id']);
            } catch (RegistryVerificationException | \RuntimeException $e) {
                $stats['failed']++;
                $this->telemetry?->emit('registry.refresh', [
                    'registry' => (string) $registry['source_id'],
                    'result' => 'failed',
                    'reason' => $e instanceof RegistryVerificationException ? $e->code : $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function applySnapshotFrom(string $url, int $registryId): string
    {
        $envelope = $this->fetchJson($url);
        if (($envelope['format'] ?? null) !== 'rb-snapshot-envelope.v1') {
            throw new \RuntimeException('snapshot envelope has an unknown format');
        }
        $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
        if (!is_string($envelope['document'] ?? null) || $signature === false) {
            throw new \RuntimeException('snapshot envelope is malformed');
        }

        return $this->snapshots->applySnapshot(
            $registryId,
            (string) $envelope['document'],
            $signature,
            (string) ($envelope['key_id'] ?? ''),
        )['status'];
    }

    private function ingestAdvisoriesFrom(string $url, int $registryId): int
    {
        $result = $this->transport->fetch($url);
        if ($result->status === 404) {
            return 0; // publishing no advisories is not a failure
        }
        if ($result->error !== null || $result->status !== 200) {
            throw new \RuntimeException('advisory fetch failed: ' . ($result->error ?? ('HTTP ' . $result->status)));
        }
        $doc = json_decode($result->body, true);
        if (!is_array($doc) || ($doc['format'] ?? null) !== 'rb-advisory-envelopes.v1' || !is_array($doc['advisories'] ?? null)) {
            throw new \RuntimeException('advisory envelope list is malformed');
        }

        $ingested = 0;
        foreach ($doc['advisories'] as $envelope) {
            if (!is_array($envelope)) {
                continue;
            }
            $signature = base64_decode((string) ($envelope['signature'] ?? ''), true);
            if (!is_string($envelope['document'] ?? null) || $signature === false) {
                continue; // one bad envelope must not block the others
            }
            $this->advisories->ingest($registryId, (string) $envelope['document'], $signature, (string) ($envelope['key_id'] ?? ''));
            $ingested++;
        }

        return $ingested;
    }

    /** @return array<string,mixed> */
    private function fetchJson(string $url): array
    {
        $result = $this->transport->fetch($url);
        if ($result->error !== null || $result->status !== 200) {
            throw new \RuntimeException('fetch failed: ' . ($result->error ?? ('HTTP ' . $result->status)));
        }
        $decoded = json_decode($result->body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('response is not a JSON object');
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Wire config + console**

In `config/config.php`, after the `link_previews` block, add:

```php
    'registry' => [
        'fetch_timeout_seconds' => (int) Env::get('REGISTRY_FETCH_TIMEOUT_SECONDS', '10'),
        'max_snapshot_bytes' => (int) Env::get('REGISTRY_MAX_SNAPSHOT_BYTES', '1048576'),
        'allow_http' => Env::bool('REGISTRY_ALLOW_HTTP', false),
        'allowed_private_cidrs' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('REGISTRY_ALLOWED_PRIVATE_CIDRS', ''))))),
    ],
```

In `bin/console`: add `use` lines for `App\Repository\PackageAdvisoryRepository;`, `App\Repository\PackagePublisherRepository;`, `App\Repository\PackageRegistryRepository;`, `App\Repository\PackageReleaseRepository;`, `App\Repository\PackageRepository;`, `App\Repository\RegistrySnapshotRepository;`, `App\Repository\RegistryTrustKeyRepository;`, `App\Security\Registry\TrustChainVerifier;`, `App\Service\Registry\CurlRegistryTransport;`, `App\Service\Registry\RegistryAdvisoryService;`, `App\Service\Registry\RegistrySnapshotService;`, `App\Worker\RegistryRefreshWorker;` — then a new case before `'help'` (mirror `worker:webhooks`):

```php
        case 'worker:registry-refresh':
            // P5-01 SP4: keep enabled registries inside the 24h freshness
            // window. No-ops while the package_registry flag is dark.
            $db = $database();
            $snapshotService = new RegistrySnapshotService(
                $db,
                new TrustChainVerifier(),
                new PackageRegistryRepository($db),
                new RegistryTrustKeyRepository($db),
                new RegistrySnapshotRepository($db),
                new PackagePublisherRepository($db),
                new PackageRepository($db),
                new PackageReleaseRepository($db),
            );
            $advisoryService = new RegistryAdvisoryService(
                $db,
                new TrustChainVerifier(),
                new RegistryTrustKeyRepository($db),
                new PackageAdvisoryRepository($db),
                new PackageRepository($db),
                new PackageReleaseRepository($db),
                new ModerationLogRepository($db),
            );
            $stats = (new RegistryRefreshWorker(
                new PackageRegistryRepository($db),
                $snapshotService,
                $advisoryService,
                new CurlRegistryTransport(
                    new EgressGuard(
                        (bool) $config->get('registry.allow_http', false),
                        (array) $config->get('registry.allowed_private_cidrs', []),
                    ),
                    (int) $config->get('registry.max_snapshot_bytes', 1_048_576),
                    (int) $config->get('registry.fetch_timeout_seconds', 10),
                ),
                (new FeatureFlags(new SettingRepository($db)))->enabled('package_registry'),
            ))->run();
            $log(sprintf(
                'Registry refresh: refreshed=%d unchanged=%d advisories=%d failed=%d skipped=%d',
                $stats['refreshed'],
                $stats['unchanged'],
                $stats['advisories'],
                $stats['failed'],
                $stats['skipped'],
            ));
            break;
```

And in the help text, after the `worker:webhooks` line:

```php
            $log('  worker:registry-refresh  Fetch + verify enabled package-registry snapshots/advisories (no-op while dark)');
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
vendor/bin/phpunit tests/Integration/Worker/RegistryRefreshWorkerTest.php 2>&1 | tail -3
php bin/console worker:registry-refresh
```
Expected: `OK (4 tests, ...)`; the console prints `Registry refresh: refreshed=0 unchanged=0 advisories=0 failed=0 skipped=1` (flag dark).

- [ ] **Step 6: Commit**

```bash
git add src/Service/Registry/RegistryFetchResult.php src/Service/Registry/RegistryTransport.php \
        src/Service/Registry/CurlRegistryTransport.php src/Service/Registry/ArrayRegistryTransport.php \
        src/Worker/RegistryRefreshWorker.php config/config.php bin/console \
        tests/Integration/Worker/RegistryRefreshWorkerTest.php
git commit -m "feat(phase5): worker:registry-refresh - EgressGuard-pinned fetch, envelope verify, flag-dark no-op (Inc 2)"
```

---

### Task 8: Staff-only read-only catalogue browse — service, controller, templates, routes, bindings, flag regression

The increment's visible surface: `/admin/packages` (catalogue with freshness banner, compatibility + advisory + blocked badges) and `/admin/packages/{id}` (provenance detail). **Read-only** — the exit gate asserts install is absent. Compatibility is computed at read time via `CoreVersion::satisfies` against each release's `core_min`/`core_max`.

**Files:**
- Create: `src/Service/Registry/RegistryCatalogService.php`
- Create: `src/Controller/AdminPackagesController.php`
- Create: `templates/admin/packages.php`
- Create: `templates/admin/package_detail.php`
- Modify: `src/Core/App.php` (imports; container bindings after the `PermissionSimulatorService` block ~line 1109; routes after the `/admin/roles` block ~line 1410)
- Modify: `templates/admin/dashboard.php` (flag-gated subnav link, line ~17)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (dark-route regression)
- Test: `tests/Integration/Core/AppRegistryCatalogTest.php`

**Interfaces:**
- Consumes: Tasks 2/4/6 (`PackageRepository::catalog`, `RegistrySnapshotService::isFresh`, `LocalBlocklistService::isBlocked`, `PackageAdvisoryRepository::forPackage`, `PackageReleaseRepository::forPackage`), `CoreVersion::satisfies`, the `AdminRoleController` pattern (`requireAdmin()` → `gate()` → `noindex()`).
- Produces: `RegistryCatalogService::overview(): array{registries:list<array<string,mixed>>,packages:list<array<string,mixed>>}` and `detail(int): ?array{package:array<string,mixed>,registry:?array<string,mixed>,releases:list<array<string,mixed>>,advisories:list<array<string,mixed>>,blocked:bool}`; routes `GET /admin/packages`, `GET /admin/packages/{id}`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Integration/Core/AppFeatureFlagTest.php` (next to `test_capabilities_flag_gates_role_routes`):

```php
    public function test_package_registry_flag_gates_catalog_and_registry_routes(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/packages'));
        $this->assertStatus(404, $this->get('/admin/registries'));

        $this->setFlags(['package_registry' => true]);
        self::assertNotSame(404, $this->get('/admin/packages')->status());
        self::assertNotSame(404, $this->get('/admin/registries')->status());

        $this->setFlags(['package_registry' => false]);
        $this->assertStatus(404, $this->get('/admin/packages'));
    }
```

(The `/admin/registries` assertions go red until Task 9 registers that route — implement Tasks 8 and 9 back-to-back before calling this test done, or temporarily assert only the `/admin/packages` pair in this task and extend in Task 9. Prefer the latter: keep every commit green.)

Create `tests/Integration/Core/AppRegistryCatalogTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\PackageRegistryRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** Inc 2 exit gate: staff browse renders; install is ABSENT. */
final class AppRegistryCatalogTest extends TestCase
{
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private function seedCatalog(): array
    {
        $ids = RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
        $this->setFlags(['package_registry' => true]);

        return $ids;
    }

    public function test_guests_redirect_and_members_are_forbidden(): void
    {
        $this->seedCatalog();
        $this->assertStatus(302, $this->get('/admin/packages'));

        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/packages'));
    }

    public function test_admin_catalogue_lists_packages_with_badges_and_noindex(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages');
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('acme/midnight-theme', $resp->body());
        self::assertStringContainsString('Midnight Theme', $resp->body());
        self::assertStringContainsString('reviewed_declarative', $resp->body());
        self::assertStringContainsString('Stale snapshot', $resp->body(), 'the fixture registry has no verified snapshot yet, so the freshness banner shows');
    }

    public function test_detail_shows_provenance_and_release_rows(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $resp = $this->get('/admin/packages/' . $ids['package_id']);
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('1.0.0', $resp->body());
        self::assertStringContainsString(substr(hash('sha256', 'artifact:acme/midnight-theme:1.0.0'), 0, 16), $resp->body(), 'digest (abbreviated) is displayed');
        self::assertStringContainsString('root-1', $resp->body(), 'signing key id is displayed');
        self::assertStringContainsString('rb-test', $resp->body(), 'pinned source registry is displayed');

        $this->assertStatus(404, $this->get('/admin/packages/999999'));
    }

    public function test_install_is_absent_everywhere(): void
    {
        $ids = $this->seedCatalog();
        $this->actingAs($this->makeAdmin());

        $page = $this->get('/admin/packages/' . $ids['package_id'])->body();
        self::assertStringNotContainsStringIgnoringCase('install', $page, 'no install affordance may render in Inc 2');

        // No POST routes exist under /admin/packages at all.
        $this->assertStatus(404, $this->post('/admin/packages/' . $ids['package_id'] . '/install', []));
        $this->assertStatus(405, $this->post('/admin/packages/' . $ids['package_id'], []));
    }
}
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRegistryCatalogTest.php 2>&1 | tail -4`
Expected: 404s everywhere (`assertStatus(302, ...)` fails first) because the routes do not exist yet.

- [ ] **Step 3: Implement service, controller, templates**

Create `src/Service/Registry/RegistryCatalogService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Support\CoreVersion;

/**
 * Read model for the staff-only catalogue browse (P5-01 SP6). Read-only by
 * construction: it exposes no mutation and the controller registers no POST.
 * Compatibility/advisory/blocked badges are computed at read time.
 */
final class RegistryCatalogService
{
    public function __construct(
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageAdvisoryRepository $advisories,
        private PackageRegistryRepository $registries,
        private RegistrySnapshotService $snapshots,
        private LocalBlocklistService $blocklist,
    ) {
    }

    /** @return array{registries:list<array<string,mixed>>,packages:list<array<string,mixed>>} */
    public function overview(?\DateTimeImmutable $now = null): array
    {
        $registries = [];
        foreach ($this->registries->all() as $registry) {
            $registry['fresh'] = $this->snapshots->isFresh($registry, $now);
            $registries[] = $registry;
        }

        $packages = [];
        foreach ($this->packages->catalog() as $package) {
            $latest = $package['latest_release_id'] === null ? null : $this->releases->find((int) $package['latest_release_id']);
            $package['latest'] = $latest;
            $package['compatible'] = $latest === null
                ? null
                : CoreVersion::satisfies(
                    $latest['core_min'] !== null ? (string) $latest['core_min'] : null,
                    $latest['core_max'] !== null ? (string) $latest['core_max'] : null,
                );
            $package['blocked'] = $this->blocklist->isBlocked(
                $latest === null ? null : (string) $latest['digest'],
                (string) $package['package_uid'],
            );
            $packages[] = $package;
        }

        return ['registries' => $registries, 'packages' => $packages];
    }

    /** @return array{package:array<string,mixed>,registry:?array<string,mixed>,releases:list<array<string,mixed>>,advisories:list<array<string,mixed>>,blocked:bool}|null */
    public function detail(int $packageId): ?array
    {
        $package = $this->packages->find($packageId);
        if ($package === null) {
            return null;
        }

        $releases = [];
        foreach ($this->releases->forPackage($packageId) as $release) {
            $release['compatible'] = CoreVersion::satisfies(
                $release['core_min'] !== null ? (string) $release['core_min'] : null,
                $release['core_max'] !== null ? (string) $release['core_max'] : null,
            );
            $release['blocked'] = $this->blocklist->isBlocked((string) $release['digest'], (string) $package['package_uid']);
            $releases[] = $release;
        }

        return [
            'package' => $package,
            'registry' => $package['registry_id'] === null ? null : $this->registries->find((int) $package['registry_id']),
            'releases' => $releases,
            'advisories' => $this->advisories->forPackage($packageId),
            'blocked' => $this->blocklist->isBlocked(null, (string) $package['package_uid']),
        ];
    }
}
```

Create `src/Controller/AdminPackagesController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\Registry\RegistryCatalogService;

/**
 * Deploy-dark, READ-ONLY staff catalogue browse (P5-01 SP6). This controller
 * deliberately has no POST actions: install does not exist until Inc 3.
 */
final class AdminPackagesController extends Controller
{
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

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->noindex($this->view('admin/packages', [
            'data' => $this->container->get(RegistryCatalogService::class)->overview(),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $detail = $this->container->get(RegistryCatalogService::class)->detail((int) ($params['id'] ?? 0));
        if ($detail === null) {
            throw new NotFoundException('Package not found.');
        }

        return $this->noindex($this->view('admin/package_detail', $detail));
    }
}
```

Create `templates/admin/packages.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package catalogue');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Package catalogue</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/packages">Packages</a>
        <a href="/admin/registries">Registry trust</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">Read-only staff browse of signed registry metadata. <strong>Install does not exist yet</strong> —
    it arrives with the Increment 3 lifecycle behind the same flag. A signature proves byte provenance
    under a pinned key, not safety or review.</p>

    <?php foreach ($data['registries'] as $registry): ?>
        <?php if (!$registry['fresh']): ?>
            <p class="field-error">Stale snapshot: <strong><?= $e($registry['source_id']) ?></strong> has no
            verified snapshot inside its freshness window
            (<?= $registry['snapshot_expires_at'] !== null ? 'expired ' . $e($registry['snapshot_expires_at']) . ' UTC' : 'never fetched' ?>).
            Cached metadata below remains viewable; install decisions would refuse. Run
            <code>php bin/console worker:registry-refresh</code>.</p>
        <?php endif; ?>
    <?php endforeach; ?>

    <section class="card">
        <h2>Packages</h2>
        <?php if ($data['packages'] === []): ?>
            <p class="muted">No packages yet — pin a trust key, enable the registry, and run the refresh worker.</p>
        <?php else: ?>
        <table class="audit">
            <thead><tr><th>Package</th><th>Type</th><th>Trust class</th><th>Latest</th><th>Compatibility</th><th>Advisory</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($data['packages'] as $p): ?>
                <tr>
                    <td>
                        <strong><?= $e($p['name']) ?></strong><br>
                        <code><?= $e($p['package_uid']) ?></code>
                        <span class="muted">via <?= $e($p['registry_source_id'] ?? 'local') ?> · <?= $e($p['publisher_name'] ?? 'unknown publisher') ?></span>
                    </td>
                    <td><?= $e($p['type']) ?></td>
                    <td><code><?= $e($p['trust_class']) ?></code></td>
                    <td><?= $p['latest'] !== null ? $e($p['latest']['version']) : '<span class="muted">none stable</span>' ?></td>
                    <td>
                        <?php if ($p['compatible'] === null): ?><span class="muted">n/a</span>
                        <?php elseif ($p['compatible']): ?><span class="pill">compatible</span>
                        <?php else: ?><span class="pill">incompatible with this core</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['blocked']): ?><span class="pill">locally blocked</span><?php endif; ?>
                        <?php if ($p['advisory_status'] !== 'none'): ?><span class="pill"><?= $e($p['advisory_status']) ?></span>
                        <?php elseif (!$p['blocked']): ?><span class="muted">none</span><?php endif; ?>
                    </td>
                    <td><a href="/admin/packages/<?= (int) $p['id'] ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    </div>
</div>
```

Create `templates/admin/package_detail.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package: ' . $package['name']);
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($package['name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/packages">Packages</a>
        <a href="/admin/registries">Registry trust</a>
    </nav>

    <div class="admin-pane">
    <section class="card">
        <h2>Provenance</h2>
        <table class="audit">
            <tbody>
                <tr><th>Package identity</th><td><code><?= $e($package['package_uid']) ?></code></td></tr>
                <tr><th>Pinned source</th><td><?= $registry !== null ? $e($registry['source_id']) . ' (' . $e($registry['base_url']) . ')' : 'local' ?></td></tr>
                <tr><th>Type</th><td><?= $e($package['type']) ?></td></tr>
                <tr><th>Trust class</th><td><code><?= $e($package['trust_class']) ?></code> — trust is never implied by being listed</td></tr>
                <tr><th>Advisory status</th><td><?= $e($package['advisory_status']) ?><?= $blocked ? ' · locally blocked' : '' ?></td></tr>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Releases (immutable: any changed byte is a new release)</h2>
        <table class="audit">
            <thead><tr><th>Version</th><th>Channel</th><th>Digest (sha256)</th><th>Signed by</th><th>Review</th><th>Core range</th><th>Advisory</th></tr></thead>
            <tbody>
            <?php foreach ($releases as $r): ?>
                <tr>
                    <td><?= $e($r['version']) ?></td>
                    <td><?= $e($r['channel']) ?></td>
                    <td><code><?= $e(substr((string) $r['digest'], 0, 16)) ?>…</code><?= $r['blocked'] ? ' <span class="pill">blocked</span>' : '' ?></td>
                    <td><?= $r['signed_key_id'] !== null ? '<code>' . $e($r['signed_key_id']) . '</code>' : '<span class="muted">snapshot-listed</span>' ?></td>
                    <td><?= $e($r['review_status']) ?></td>
                    <td>
                        <code><?= $e($r['core_min'] ?? '*') ?> – <?= $e($r['core_max'] ?? '*') ?></code>
                        <?= $r['compatible'] ? '<span class="pill">compatible</span>' : '<span class="pill">incompatible</span>' ?>
                    </td>
                    <td><?= $e($r['advisory_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Advisories</h2>
        <?php if ($advisories === []): ?>
            <p class="muted">No advisories recorded for this package.</p>
        <?php else: ?>
        <table class="audit">
            <thead><tr><th>Advisory</th><th>Severity</th><th>Action</th><th>Affected</th><th>Acknowledged</th></tr></thead>
            <tbody>
            <?php foreach ($advisories as $a): ?>
                <tr>
                    <td><code><?= $e($a['advisory_uid']) ?></code><br><span class="muted"><?= $e($a['summary'] ?? '') ?></span></td>
                    <td><?= $e($a['severity']) ?></td>
                    <td><code><?= $e($a['action']) ?></code></td>
                    <td><?= $a['affected_digest'] !== null ? 'digest ' . $e(substr((string) $a['affected_digest'], 0, 16)) . '…' : $e($a['affected_version_range'] ?? 'all versions') ?></td>
                    <td><?= $a['acknowledged_at'] !== null ? $e($a['acknowledged_at']) . ' UTC' : 'not yet' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    </div>
</div>
```

- [ ] **Step 4: Wire container, routes, dashboard nav**

In `src/Core/App.php`:

1. Imports (alphabetical among the existing `use` lines): `App\Controller\AdminPackagesController`, `App\Repository\LocalPackageBlockRepository`, `App\Repository\PackageAdvisoryRepository`, `App\Repository\PackagePublisherRepository`, `App\Repository\PackageRegistryRepository`, `App\Repository\PackageReleaseRepository`, `App\Repository\PackageRepository`, `App\Repository\RegistrySnapshotRepository`, `App\Repository\RegistryTrustKeyRepository`, `App\Security\Registry\TrustChainVerifier`, `App\Service\Registry\LocalBlocklistService`, `App\Service\Registry\RegistryAdvisoryService`, `App\Service\Registry\RegistryCatalogService`, `App\Service\Registry\RegistrySnapshotService`, `App\Service\Registry\RegistryTrustService`.
2. In `buildContainer()`, after the `PermissionSimulatorService` binding, add lazy-singleton binds (every repository takes only `Database`):

```php
        $c->bind(TrustChainVerifier::class, fn () => new TrustChainVerifier());
        $c->bind(PackageRegistryRepository::class, fn (Container $c) => new PackageRegistryRepository($c->get(Database::class)));
        $c->bind(RegistryTrustKeyRepository::class, fn (Container $c) => new RegistryTrustKeyRepository($c->get(Database::class)));
        $c->bind(PackagePublisherRepository::class, fn (Container $c) => new PackagePublisherRepository($c->get(Database::class)));
        $c->bind(PackageRepository::class, fn (Container $c) => new PackageRepository($c->get(Database::class)));
        $c->bind(PackageReleaseRepository::class, fn (Container $c) => new PackageReleaseRepository($c->get(Database::class)));
        $c->bind(PackageAdvisoryRepository::class, fn (Container $c) => new PackageAdvisoryRepository($c->get(Database::class)));
        $c->bind(LocalPackageBlockRepository::class, fn (Container $c) => new LocalPackageBlockRepository($c->get(Database::class)));
        $c->bind(RegistrySnapshotRepository::class, fn (Container $c) => new RegistrySnapshotRepository($c->get(Database::class)));
        $c->bind(RegistrySnapshotService::class, fn (Container $c) => new RegistrySnapshotService(
            $c->get(Database::class),
            $c->get(TrustChainVerifier::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(RegistrySnapshotRepository::class),
            $c->get(PackagePublisherRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(RegistryTrustService::class, fn (Container $c) => new RegistryTrustService(
            $c->get(Database::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(TrustChainVerifier::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(RegistryAdvisoryService::class, fn (Container $c) => new RegistryAdvisoryService(
            $c->get(Database::class),
            $c->get(TrustChainVerifier::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(LocalBlocklistService::class, fn (Container $c) => new LocalBlocklistService(
            $c->get(LocalPackageBlockRepository::class),
            $c->get(PackageRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(RegistryCatalogService::class, fn (Container $c) => new RegistryCatalogService(
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistrySnapshotService::class),
            $c->get(LocalBlocklistService::class),
        ));
```

3. In `buildRouter()`, immediately after the `/admin/roles/{id}/clone` line:

```php
        $r->get('/admin/packages', [AdminPackagesController::class, 'index']);
        $r->get('/admin/packages/{id}', [AdminPackagesController::class, 'show']);
```

In `templates/admin/dashboard.php`, after the Webhooks link:

```php
        <?php if (!empty($features['package_registry'])): ?><a href="/admin/packages">Packages</a><?php endif; ?>
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRegistryCatalogTest.php tests/Integration/Core/AppFeatureFlagTest.php 2>&1 | tail -3`
Expected: `OK` (with the flag test asserting only the `/admin/packages` pair until Task 9).

- [ ] **Step 6: Commit**

```bash
git add src/Service/Registry/RegistryCatalogService.php src/Controller/AdminPackagesController.php \
        templates/admin/packages.php templates/admin/package_detail.php src/Core/App.php \
        templates/admin/dashboard.php tests/Integration/Core/AppRegistryCatalogTest.php \
        tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(phase5): staff-only read-only package catalogue browse, dark behind package_registry (Inc 2, P5-01 SP6)"
```

---

### Task 9: Registry operations console — trust keys, rotation, revocation, blocklist, advisories

The operator surface for everything Tasks 5–6 built: one page (`/admin/registries`) listing sources, pinned keys, snapshots, the blocklist, and advisories, with plain no-JS forms for each action. Paste-forms accept the signed rotation/advisory *envelope JSON* (same shape the worker fetches). `RegistryVerificationException` maps to a 422 re-render with the code as the field error — the pasted document is preserved (anti-draft-loss).

**Files:**
- Create: `src/Controller/AdminRegistryController.php`
- Create: `templates/admin/registries.php`
- Modify: `src/Core/App.php` (import + routes after the Task 8 lines)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (extend the Task 8 test with the `/admin/registries` assertions)
- Test: `tests/Integration/Core/AppRegistryAdminTest.php`

**Interfaces:**
- Consumes: `RegistryTrustService`, `RegistryAdvisoryService`, `LocalBlocklistService`, Task 2 repos for the read model.
- Produces: routes `GET /admin/registries`, `POST /admin/registries` (create), `POST /admin/registries/{id}/enabled`, `POST /admin/registries/{id}/keys` (pin), `POST /admin/registries/{id}/rotate`, `POST /admin/registries/{id}/advisories` (ingest), `POST /admin/registry-keys/{id}/revoke`, `POST /admin/advisories/{id}/ack`, `POST /admin/blocklist`, `POST /admin/blocklist/{id}/remove`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Core/AppRegistryAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\RegistryTrustKeyRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** The trust console: reauth-gated mutations, forged-rotation refusal (TM-SC-04), audit. */
final class AppRegistryAdminTest extends TestCase
{
    private SigningHarness $root;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->setFlags(['package_registry' => true]);
        $this->admin = $this->makeAdmin(['password' => 'password123']);
        $this->actingAs($this->admin);
    }

    public function test_console_renders_sources_keys_blocklist_and_advisories(): void
    {
        $resp = $this->get('/admin/registries');
        $this->assertStatus(200, $resp);
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
        self::assertStringContainsString('rb-test', $resp->body());
        self::assertStringContainsString('root-1', $resp->body());
        self::assertStringContainsString('Local blocklist', $resp->body());
    }

    public function test_pin_requires_reauth_and_preserves_the_form_on_error(): void
    {
        $fresh = SigningHarness::generate('root-2');
        $resp = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/keys', [
            'key_id' => 'root-2',
            'public_key' => base64_encode($fresh->publicKey()),
            'current_password' => 'wrong',
        ]);
        $this->assertStatus(422, $resp);
        self::assertStringContainsString('root-2', $resp->body(), 'typed key id survives the failed post');
        self::assertNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));

        $ok = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/keys', [
            'key_id' => 'root-2',
            'public_key' => base64_encode($fresh->publicKey()),
            'current_password' => 'password123',
        ]);
        $this->assertStatus(302, $ok);
        self::assertSame('noindex', $ok->getHeader('x-robots-tag'), 'redirects carry noindex too');
        self::assertNotNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'registry_pin_key'"));
    }

    public function test_signed_rotation_succeeds_and_forged_rotation_renders_422(): void
    {
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);
        $envelope = json_encode([
            'document' => $rotation['json'],
            'signature' => base64_encode($rotation['signature']),
            'key_id' => $rotation['key_id'],
        ], JSON_UNESCAPED_SLASHES);

        $ok = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/rotate', [
            'envelope' => (string) $envelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(302, $ok);
        $keys = new RegistryTrustKeyRepository($this->db);
        self::assertSame('active', $keys->findKey($this->ids['registry_id'], 'root-2')['status']);
        self::assertSame('rotated', $keys->find($this->ids['trust_key_id'])['status']);

        // TM-SC-04: attacker-supplied transition signed by an unpinned key.
        $attacker = SigningHarness::generate('evil-1');
        $forged = $attacker->mintRotation(SigningHarness::generate('root-3'));
        $badEnvelope = json_encode([
            'document' => $forged['json'],
            'signature' => base64_encode($forged['signature']),
            'key_id' => $forged['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        $bad = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/rotate', [
            'envelope' => (string) $badEnvelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $bad);
        self::assertStringContainsString('unknown_key', $bad->body());
        self::assertNull($keys->findKey($this->ids['registry_id'], 'root-3'));
    }

    public function test_revoke_blocklist_and_advisory_ack_flows(): void
    {
        // Revoke the fixture key.
        $resp = $this->post('/admin/registry-keys/' . $this->ids['trust_key_id'] . '/revoke', [
            'reason' => 'compromise drill',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(302, $resp);
        self::assertSame('revoked', (new RegistryTrustKeyRepository($this->db))->find($this->ids['trust_key_id'])['status']);

        // Blocklist add needs NO password (emergency brake); remove does.
        $digest = str_repeat('a', 64);
        $this->assertStatus(302, $this->post('/admin/blocklist', ['digest' => $digest, 'reason' => 'drill']));
        $blockId = (int) $this->db->fetchValue('SELECT id FROM local_package_blocks WHERE digest = ?', [$digest]);
        $this->assertStatus(422, $this->post('/admin/blocklist/' . $blockId . '/remove', ['current_password' => 'wrong']));
        $this->assertStatus(302, $this->post('/admin/blocklist/' . $blockId . '/remove', ['current_password' => 'password123']));

        // Advisory paste-ingest + ack.
        $adv = $this->root->mintAdvisory();
        $envelope = json_encode([
            'document' => $adv['json'],
            'signature' => base64_encode($adv['signature']),
            'key_id' => $adv['key_id'],
        ], JSON_UNESCAPED_SLASHES);
        // Note: root-1 was revoked above, so ingest must now FAIL (fail-closed end-to-end).
        $refused = $this->post('/admin/registries/' . $this->ids['registry_id'] . '/advisories', [
            'envelope' => (string) $envelope,
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $refused);
        self::assertStringContainsString('revoked_key', $refused->body());
    }

    public function test_member_and_guest_access(): void
    {
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/registries'));
    }
}
```

Also extend `test_package_registry_flag_gates_catalog_and_registry_routes` in `AppFeatureFlagTest` with the `/admin/registries` pair (as written in Task 8 Step 1).

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php 2>&1 | tail -4`
Expected: 404s — the routes do not exist.

- [ ] **Step 3: Implement the controller**

Create `src/Controller/AdminRegistryController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistryTrustService;

/**
 * Deploy-dark registry trust console (P5-01): sources, pinned keys, signed
 * rotation, revocation, local blocklist, advisory ingest/ack. Trust mutations
 * are reauth-gated in the service layer; verification refusals re-render 422
 * with the pasted document preserved.
 */
final class AdminRegistryController extends Controller
{
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

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->consoleView();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->createRegistry(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('source_id'),
                $request->str('display_name'),
                $request->str('base_url'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Registry added (disabled until you enable it).'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function setEnabled(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $enabled = $request->post('enabled', '0') === '1';

        try {
            $this->container->get(RegistryTrustService::class)->setEnabled(
                $admin,
                $enabled ? (string) $request->post('current_password', '') : null,
                (int) ($params['id'] ?? 0),
                $enabled,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', $enabled ? 'Registry enabled.' : 'Registry disabled.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function pinKey(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->pinKey(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $request->str('key_id'),
                $request->str('public_key'),
                $request->str('valid_from') !== '' ? $request->str('valid_from') : null,
                $request->str('valid_until') !== '' ? $request->str('valid_until') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Trust key pinned.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotate(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $registryId = (int) ($params['id'] ?? 0);

        try {
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $this->container->get(RegistryTrustService::class)->applyRotation(
                $admin,
                (string) $request->post('current_password', ''),
                $registryId,
                $document,
                $signature,
                $keyId,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Key rotation applied: successor pinned, old key retired.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->consoleView(['envelope' => 'Rotation refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokeKey(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->revokeKey(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Trust key revoked; everything it signed now fails closed.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function ingestAdvisory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $registryId = (int) ($params['id'] ?? 0);

        try {
            // Reauth here (controller-level): manual paste-ingest is an operator
            // trust decision, unlike the worker's signed-fetch path.
            $this->container->get(\App\Security\ReauthGate::class)->requirePassword($admin, (string) $request->post('current_password', ''));
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $out = $this->container->get(RegistryAdvisoryService::class)->ingest($registryId, $document, $signature, $keyId);
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Advisory ingested (action: ' . $out['action'] . ').'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->consoleView(['advisory_envelope' => 'Advisory refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function ackAdvisory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryAdvisoryService::class)->acknowledge($admin, (int) ($params['id'] ?? 0));
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Advisory acknowledged.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, [], 422);
        }
    }

    /** @param array<string,string> $params */
    public function block(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(LocalBlocklistService::class)->block(
                $admin,
                $request->str('digest') !== '' ? $request->str('digest') : null,
                $request->str('package_uid') !== '' ? $request->str('package_uid') : null,
                $request->str('reason') !== '' ? $request->str('reason') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Local block added; it applies regardless of registry state.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function unblock(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(LocalBlocklistService::class)->unblock(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Local block removed.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /**
     * @return array{0:string,1:string,2:string} document, raw signature bytes, key id
     */
    private function parseEnvelope(string $raw): array
    {
        $decoded = json_decode(trim($raw), true);
        $signature = is_array($decoded) ? base64_decode((string) ($decoded['signature'] ?? ''), true) : false;
        if (!is_array($decoded) || !is_string($decoded['document'] ?? null) || $signature === false) {
            throw new ValidationException(['envelope' => 'Paste the JSON envelope: {"document": "...", "signature": "<base64>", "key_id": "..."}.']);
        }

        return [(string) $decoded['document'], $signature, (string) ($decoded['key_id'] ?? '')];
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function consoleView(array $errors = [], array $old = [], int $status = 200): Response
    {
        $registryRepo = $this->container->get(PackageRegistryRepository::class);
        $keyRepo = $this->container->get(RegistryTrustKeyRepository::class);
        $snapshotRepo = $this->container->get(RegistrySnapshotRepository::class);

        $registries = [];
        foreach ($registryRepo->all() as $registry) {
            $registry['keys'] = $keyRepo->forRegistry((int) $registry['id']);
            $registry['latest_snapshot'] = $snapshotRepo->latestFor((int) $registry['id']);
            $registries[] = $registry;
        }

        return $this->noindex($this->view('admin/registries', [
            'registries' => $registries,
            'blocks' => $this->container->get(LocalPackageBlockRepository::class)->all(),
            'advisories' => $this->container->get(PackageAdvisoryRepository::class)->all(),
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }
}
```

`Request::allInput()` (src/Core/Request.php:134) is the existing bulk accessor — it re-fills the console forms with whatever the admin typed on a failed post.

- [ ] **Step 4: Write the template**

Create `templates/admin/registries.php` (one dense operator page; every form is plain POST + `csrfField()`, password fields only where the service demands reauth):

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Registry trust');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Registry trust &amp; security response</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/packages">Packages</a>
        <a class="active" href="/admin/registries">Registry trust</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">The private signing root lives <strong>offline with the operator</strong> (custody runbook,
    <code>docs/phase5/registry-signing-key-custody.md</code>); this console pins/rotates/revokes <strong>public</strong>
    keys only. Trust changes require your password. The local blocklist works regardless of registry state.</p>

    <?php foreach ($registries as $reg): ?>
    <section class="card">
        <h2><?= $e($reg['display_name']) ?> <code><?= $e($reg['source_id']) ?></code>
            <?= ((int) $reg['is_enabled']) === 1 ? '<span class="pill">enabled</span>' : '<span class="pill">disabled</span>' ?></h2>
        <p class="muted"><?= $e($reg['base_url']) ?> ·
            <?php if ($reg['latest_snapshot'] !== null): ?>
                last verified snapshot <?= $e($reg['latest_snapshot']['generated_at']) ?> UTC (expires <?= $e($reg['latest_snapshot']['expires_at']) ?> UTC)
            <?php else: ?>no verified snapshot yet<?php endif; ?></p>

        <table class="audit">
            <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th>Fingerprint</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reg['keys'] as $key): ?>
                <tr>
                    <td><code><?= $e($key['key_id']) ?></code></td>
                    <td><?= $e($key['status']) ?><?= $key['revoked_reason'] !== null ? ' — ' . $e($key['revoked_reason']) : '' ?></td>
                    <td><?= $e($key['valid_from'] ?? '∞') ?> → <?= $e($key['valid_until'] ?? '∞') ?></td>
                    <td><code><?= $e(substr(hash('sha256', (string) $key['public_key']), 0, 16)) ?></code></td>
                    <td>
                        <?php if ($key['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/registry-keys/<?= (int) $key['id'] ?>/revoke" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" placeholder="Revocation reason" required>
                            <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <details>
            <summary>Pin a new public key (initial setup, custody §5.1)</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/keys" class="stacked">
                <?= $this->csrfField() ?>
                <label>Key id <input type="text" name="key_id" maxlength="190" value="<?= $e($old['key_id'] ?? '') ?>" required></label>
                <?php if (!empty($errors['key_id'])): ?><p class="field-error"><?= $e($errors['key_id']) ?></p><?php endif; ?>
                <label>Public key (base64, 32 bytes) <input type="text" name="public_key" value="<?= $e($old['public_key'] ?? '') ?>" required></label>
                <?php if (!empty($errors['public_key'])): ?><p class="field-error"><?= $e($errors['public_key']) ?></p><?php endif; ?>
                <label>Valid from (UTC, optional) <input type="text" name="valid_from" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <label>Valid until (UTC, optional) <input type="text" name="valid_until" placeholder="YYYY-MM-DD HH:MM:SS"></label>
                <?php if (!empty($errors['valid_from'])): ?><p class="field-error"><?= $e($errors['valid_from']) ?></p><?php endif; ?>
                <?php if (!empty($errors['valid_until'])): ?><p class="field-error"><?= $e($errors['valid_until']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
                <div class="form-actions"><button class="btn" type="submit">Pin key</button></div>
            </form>
        </details>

        <details>
            <summary>Apply a signed key rotation (custody §5.3)</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/rotate" class="stacked">
                <?= $this->csrfField() ?>
                <label>Rotation envelope JSON
                    <textarea name="envelope" rows="4" required><?= $e($old['envelope'] ?? '') ?></textarea>
                </label>
                <?php if (!empty($errors['envelope'])): ?><p class="field-error"><?= $e($errors['envelope']) ?></p><?php endif; ?>
                <?php if (!empty($errors['rotation'])): ?><p class="field-error"><?= $e($errors['rotation']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Apply rotation</button></div>
            </form>
        </details>

        <details>
            <summary>Ingest a signed advisory manually (outage fallback)</summary>
            <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/advisories" class="stacked">
                <?= $this->csrfField() ?>
                <label>Advisory envelope JSON
                    <textarea name="envelope" rows="4" required><?= $e($old['envelope'] ?? '') ?></textarea>
                </label>
                <?php if (!empty($errors['advisory_envelope'])): ?><p class="field-error"><?= $e($errors['advisory_envelope']) ?></p><?php endif; ?>
                <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Ingest advisory</button></div>
            </form>
        </details>

        <form method="post" action="/admin/registries/<?= (int) $reg['id'] ?>/enabled" class="stacked">
            <?= $this->csrfField() ?>
            <?php if (((int) $reg['is_enabled']) === 1): ?>
                <input type="hidden" name="enabled" value="0">
                <div class="form-actions"><button class="btn" type="submit">Disable registry (no password — defensive)</button></div>
            <?php else: ?>
                <input type="hidden" name="enabled" value="1">
                <label>Confirm your password to enable
                    <input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions"><button class="btn" type="submit">Enable registry</button></div>
            <?php endif; ?>
        </form>
    </section>
    <?php endforeach; ?>

    <section class="card">
        <h2>Add a registry source</h2>
        <form method="post" action="/admin/registries" class="stacked">
            <?= $this->csrfField() ?>
            <label>Source id <input type="text" name="source_id" maxlength="190" value="<?= $e($old['source_id'] ?? '') ?>" required></label>
            <?php if (!empty($errors['source_id'])): ?><p class="field-error"><?= $e($errors['source_id']) ?></p><?php endif; ?>
            <label>Display name <input type="text" name="display_name" maxlength="190" value="<?= $e($old['display_name'] ?? '') ?>" required></label>
            <?php if (!empty($errors['display_name'])): ?><p class="field-error"><?= $e($errors['display_name']) ?></p><?php endif; ?>
            <label>Base URL <input type="url" name="base_url" maxlength="512" value="<?= $e($old['base_url'] ?? '') ?>" required></label>
            <?php if (!empty($errors['base_url'])): ?><p class="field-error"><?= $e($errors['base_url']) ?></p><?php endif; ?>
            <label>Confirm your password <input type="password" name="current_password" autocomplete="current-password" required></label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Add registry (starts disabled)</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Local blocklist (registry-independent)</h2>
        <table class="audit">
            <thead><tr><th>Digest</th><th>Package uid</th><th>Reason</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $block): ?>
                <tr>
                    <td><?= $block['digest'] !== null ? '<code>' . $e(substr((string) $block['digest'], 0, 16)) . '…</code>' : '—' ?></td>
                    <td><?= $block['package_uid'] !== null ? '<code>' . $e($block['package_uid']) . '</code>' : '—' ?></td>
                    <td><?= $e($block['reason'] ?? '') ?></td>
                    <td>
                        <form method="post" action="/admin/blocklist/<?= (int) $block['id'] ?>/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="password" name="current_password" placeholder="Your password" autocomplete="current-password" required>
                            <button class="btn" type="submit">Remove (re-enables)</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="/admin/blocklist" class="stacked">
            <?= $this->csrfField() ?>
            <label>Release digest (sha256 hex, optional) <input type="text" name="digest" value="<?= $e($old['digest'] ?? '') ?>"></label>
            <?php if (!empty($errors['digest'])): ?><p class="field-error"><?= $e($errors['digest']) ?></p><?php endif; ?>
            <label>Package uid (optional) <input type="text" name="package_uid" value="<?= $e($old['package_uid'] ?? '') ?>"></label>
            <?php if (!empty($errors['target'])): ?><p class="field-error"><?= $e($errors['target']) ?></p><?php endif; ?>
            <label>Reason (optional) <input type="text" name="reason" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>"></label>
            <div class="form-actions"><button class="btn" type="submit">Block now (no password — emergency brake)</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Advisories</h2>
        <?php if ($advisories === []): ?><p class="muted">None ingested.</p><?php else: ?>
        <table class="audit">
            <thead><tr><th>Advisory</th><th>Package</th><th>Severity</th><th>Action</th><th>Acknowledged</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($advisories as $a): ?>
                <tr>
                    <td><code><?= $e($a['advisory_uid']) ?></code></td>
                    <td><?= $a['package_uid'] !== null ? '<code>' . $e($a['package_uid']) . '</code>' : '<span class="muted">unresolved</span>' ?></td>
                    <td><?= $e($a['severity']) ?></td>
                    <td><code><?= $e($a['action']) ?></code></td>
                    <td><?= $a['acknowledged_at'] !== null ? $e($a['acknowledged_at']) . ' UTC' : 'not yet' ?></td>
                    <td>
                        <?php if ($a['acknowledged_at'] === null): ?>
                        <form method="post" action="/admin/advisories/<?= (int) $a['id'] ?>/ack" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button class="btn" type="submit">Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    </div>
</div>
```

- [ ] **Step 5: Register routes**

In `src/Core/App.php` `buildRouter()`, directly after the Task 8 package routes (exact-string routes before `{id}` patterns):

```php
        $r->get('/admin/registries', [AdminRegistryController::class, 'index']);
        $r->post('/admin/registries', [AdminRegistryController::class, 'create']);
        $r->post('/admin/registries/{id}/enabled', [AdminRegistryController::class, 'setEnabled']);
        $r->post('/admin/registries/{id}/keys', [AdminRegistryController::class, 'pinKey']);
        $r->post('/admin/registries/{id}/rotate', [AdminRegistryController::class, 'rotate']);
        $r->post('/admin/registries/{id}/advisories', [AdminRegistryController::class, 'ingestAdvisory']);
        $r->post('/admin/registry-keys/{id}/revoke', [AdminRegistryController::class, 'revokeKey']);
        $r->post('/admin/advisories/{id}/ack', [AdminRegistryController::class, 'ackAdvisory']);
        $r->post('/admin/blocklist', [AdminRegistryController::class, 'block']);
        $r->post('/admin/blocklist/{id}/remove', [AdminRegistryController::class, 'unblock']);
```

Plus the `AdminRegistryController` import.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Core/AppFeatureFlagTest.php 2>&1 | tail -3`
Expected: `OK` (flag test now covers both surfaces).

- [ ] **Step 7: Commit**

```bash
git add src/Controller/AdminRegistryController.php templates/admin/registries.php src/Core/App.php \
        tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(phase5): registry trust console - pin/rotate/revoke, blocklist, advisory ingest/ack (Inc 2)"
```

---

### Task 10: D11 budgets — measure `registry.signature_verify_p95`, enforce-record `registry.snapshot_freshness`, re-home `registry.fetch_p95`

Fill the increment's rows in the A3 budget report. Signature verification is measured through the **real** `TrustChainVerifier` on an in-memory signed snapshot minted by the F6 harness (signing stays in `Tests\Support`; the measurement runs only where autoload-dev exists, which `verify:phase5-budgets` already guarantees by refusing production). Snapshot freshness is an enforced cap, reported like `webhook.delivery_timeout` (CONFIG). The fetch duration needs a live registry over the network, which does not exist until staged enablement — re-home its `measurable_at` honestly instead of leaving a forever-PENDING `inc2` label.

**Files:**
- Modify: `src/Support/Phase5Budgets.php` (one value + comment)
- Modify: `src/Service/BaselineMetricsService.php` (add `measureSignatureVerify`)
- Modify: `src/Service/Phase5BudgetReportService.php` (third ctor param + two row branches + envelope line)
- Modify: `bin/console` (`verify:phase5-budgets` passes the verifier)
- Test: `tests/Integration/Service/Phase5BudgetReportServiceTest.php` (extend)

- [ ] **Step 1: Write the failing test**

Add to `tests/Integration/Service/Phase5BudgetReportServiceTest.php` (match the existing construction style in that file; the fixture seed is NOT needed for the signature rows):

```php
    public function test_inc2_registry_rows_measure_and_config(): void
    {
        $service = new \App\Service\Phase5BudgetReportService(
            $this->db,
            null,
            new \App\Security\Registry\TrustChainVerifier(),
        );
        $rows = [];
        foreach ($service->rows() as $row) {
            $rows[$row['key']] = $row;
        }

        self::assertStringStartsWith('MEASURED', $rows['registry.signature_verify_p95']['status']);
        self::assertStringContainsString('ms', $rows['registry.signature_verify_p95']['measured']);
        self::assertSame('CONFIG', $rows['registry.snapshot_freshness']['status']);
        self::assertStringContainsString('86400', $rows['registry.snapshot_freshness']['measured']);
        self::assertStringContainsString('staged-enablement', $rows['registry.fetch_p95']['status']);
    }
```

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php 2>&1 | tail -4` → FAIL (constructor takes two args; statuses read `PENDING (inc2)`).

- [ ] **Step 2: Implement**

`src/Support/Phase5Budgets.php` — change the `registry.fetch_p95` row's `measurable_at` from `'inc2'` to `'staged-enablement'` and add a line to the class docblock: `registry.fetch_p95 needs a live registry endpoint over the network; it is measured at §13.1 step 3 (staff browse enablement), not on the local fixture.` If `tests/Unit/Support/Phase5BudgetsTest.php` pins the old value, update that assertion in the same commit.

`src/Service/BaselineMetricsService.php` — add:

```php
    /**
     * Measures Ed25519 verification through the real TrustChainVerifier on an
     * in-memory ~100-package snapshot. Signing stays in the F6 test harness
     * (autoload-dev): where the harness is absent (--no-dev production), this
     * returns null and the budget row stays PENDING. The bench key is minted
     * by the harness and never persisted — the A4 public-key-only invariant
     * holds (nothing here touches registry_trust_keys).
     *
     * @return array<string,mixed>|null the §11.3 envelope
     */
    public function measureSignatureVerify(\App\Security\Registry\TrustChainVerifier $verifier, int $iterations = 200): ?array
    {
        if (!class_exists(\Tests\Support\Phase5\SigningHarness::class)) {
            return null;
        }
        $iterations = max(1, $iterations);
        $root = \Tests\Support\Phase5\SigningHarness::generate('bench-root');

        $packages = [];
        for ($i = 0; $i < 100; $i++) {
            $packages[] = [
                'uid' => "bench/pkg-$i",
                'type' => 'theme',
                'releases' => [[
                    'version' => '1.0.' . $i,
                    'digest' => hash('sha256', "bench-artifact-$i"),
                    'core_min' => '0.1.0',
                    'core_max' => null,
                    'channel' => 'stable',
                    'advisory' => 'none',
                ]],
            ];
        }
        $snap = $root->mintSnapshot(['packages' => $packages]);
        $keyRow = [
            'key_id' => 'bench-root',
            'algorithm' => 'ed25519',
            'public_key' => $root->publicKey(),
            'status' => 'active',
            'valid_from' => null,
            'valid_until' => null,
        ];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $samples = [];
        $errors = 0;
        for ($i = 0; $i < $iterations; $i++) {
            $t0 = hrtime(true);
            try {
                $verifier->verify($snap['json'], $snap['signature'], 'bench-root', [$keyRow], 'rb-registry-snapshot.v1', $now);
            } catch (\Throwable) {
                $errors++;
            }
            $samples[] = (hrtime(true) - $t0) / 1_000_000;
        }

        return [
            'route_or_job' => 'registry_signature_verify',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'in-memory rb-registry-snapshot.v1 (100 packages, ' . strlen($snap['json']) . ' bytes)',
            'role_assignment_count' => 0,
            'installed_package_count' => 100,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => 0,
            'query_time_ms' => 0.0,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }
```

`src/Service/Phase5BudgetReportService.php` — constructor becomes
`public function __construct(private Database $db, private ?CapabilityResolver $resolver = null, private ?\App\Security\Registry\TrustChainVerifier $trustVerifier = null)`;
add a memoized `private ?array $signatureSample = null;` +

```php
    private function signatureSample(): ?array
    {
        if ($this->trustVerifier === null) {
            return null;
        }

        return $this->signatureSample ??= (new BaselineMetricsService($this->db))->measureSignatureVerify($this->trustVerifier);
    }
```

and in `rows()` add two branches beside the existing ones:

```php
            } elseif ($key === 'registry.signature_verify_p95') {
                $sample = $this->signatureSample();
                if ($sample !== null) {
                    $measured = $sample['p95'] . ' ms verify (' . $sample['data_fixture'] . ')';
                    $status = ((float) $sample['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                }
            } elseif ($key === 'registry.snapshot_freshness') {
                $measured = '86400 s enforced fail-closed (RegistrySnapshotService expired_snapshot refusal)';
                $status = 'CONFIG';
            }
```

plus, in `render()` beside the resolver line:

```php
        $signatureSample = $this->signatureSample();
        if ($signatureSample !== null) {
            $out .= '- Signature verify p50/p95/p99 (ms): ' . $signatureSample['p50'] . ' / ' . $signatureSample['p95'] . ' / ' . $signatureSample['p99']
                . ' · route/job: `' . $signatureSample['route_or_job'] . "`\n";
        }
```

`bin/console` — in `verify:phase5-budgets`, change `new Phase5BudgetReportService($db, $resolver)` to `new Phase5BudgetReportService($db, $resolver, new TrustChainVerifier())`.

- [ ] **Step 3: Run + regenerate the evidence**

```bash
vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php tests/Unit/Support/Phase5BudgetsTest.php 2>&1 | tail -3
php bin/console verify:phase5-budgets
```
Expected: `OK`; the report prints `[MEASURED (PASS)] registry.signature_verify_p95 -> <n> ms ...` (Ed25519 verify of a ~40 KB doc is well under the 250 ms budget) and writes `docs/evidence/phase5/performance-budgets.md`.

- [ ] **Step 4: Commit**

```bash
git add src/Support/Phase5Budgets.php src/Service/BaselineMetricsService.php src/Service/Phase5BudgetReportService.php \
        bin/console tests/Integration/Service/Phase5BudgetReportServiceTest.php tests/Unit/Support/Phase5BudgetsTest.php \
        docs/evidence/phase5/performance-budgets.md
git commit -m "feat(phase5): measure registry.signature_verify_p95 vs D11; freshness recorded as enforced CONFIG (Inc 2)"
```

---

### Task 11: Browser + axe evidence for the two admin surfaces

Playwright captures at desktop + mobile widths and an axe pass, per the §F distributed-evidence discipline. Both pages are plain server-rendered forms — the capture doubles as the no-JS-first proof (no `data-*` JS hooks exist on them).

**Files:**
- Modify: `tests/browser/seed.php` (enable flag + deterministic registry fixture)
- Modify: `tests/browser/gate-a.spec.ts` (one new journey, three screenshots)
- Modify: `tests/browser/a11y.spec.ts` (two new pages in the admin dark-surface axe test)

- [ ] **Step 1: Extend the seed**

In `tests/browser/seed.php`: add `'package_registry' => true, // Inc 2 (P5-01): staff catalogue browse evidence (read-only)` to `$evidenceFeatures` (after the `capabilities` line). Then, after the existing evidence-fixture seeding (near the webhook/API-token blocks), add the deterministic registry fixture (idempotent: delete-then-reseed; `SigningHarness` mints a fresh root per run, which is fine because the seed re-pins it):

```php
// Inc 2 (P5-01): registry catalogue + trust console evidence. Reseed
// deterministically: the fixture rows carry fixed uids, so clear them first.
$db->transaction(function () use ($db): void {
    $db->run("DELETE FROM packages WHERE package_uid LIKE 'acme/%'");           // releases cascade
    $db->run("DELETE FROM package_publishers WHERE publisher_uid = 'acme'");
    $db->run("DELETE FROM package_advisories WHERE advisory_uid LIKE 'RB-TEST-%'");
    $db->run("DELETE FROM local_package_blocks WHERE reason = 'Evidence blocklist entry'");
    $db->run("DELETE FROM package_registries WHERE source_id = 'rb-test'");    // trust keys + snapshots cascade
});
$registryRoot = \Tests\Support\Phase5\SigningHarness::generate('root-1');
$registryIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot);
(new \App\Repository\PackageRegistryRepository($db))->setEnabled($registryIds['registry_id'], true);
$advisory = $registryRoot->mintAdvisory(['action' => 'warn', 'summary' => 'Evidence advisory: upgrade past 1.0.0']);
(new \App\Service\Registry\RegistryAdvisoryService(
    $db,
    new \App\Security\Registry\TrustChainVerifier(),
    new \App\Repository\RegistryTrustKeyRepository($db),
    new \App\Repository\PackageAdvisoryRepository($db),
    new \App\Repository\PackageRepository($db),
    new \App\Repository\PackageReleaseRepository($db),
    new \App\Repository\ModerationLogRepository($db),
))->ingest($registryIds['registry_id'], $advisory['json'], $advisory['signature'], $advisory['key_id']);
(new \App\Repository\LocalPackageBlockRepository($db))->add(null, 'acme/legacy-widget', 'Evidence blocklist entry', null);
```

- [ ] **Step 2: Add the Playwright journey**

In `tests/browser/gate-a.spec.ts`, after the roles/simulator test: the highest existing screenshot prefix is `31`, so these use 32–34 (if other work landed indices meanwhile, re-derive with `grep -o "shot(page, info, '[0-9]*" tests/browser/gate-a.spec.ts | grep -o '[0-9]*$' | sort -n | tail -1`):

```ts
test('package registry: staff-only read-only catalogue browse (Inc 2)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/packages');
  await expect(page.getByRole('heading', { name: 'Package catalogue' })).toBeVisible();
  await expect(page.locator('code', { hasText: 'acme/midnight-theme' }).first()).toBeVisible();
  await expect(page.getByText('Install does not exist yet')).toBeVisible();
  await shot(page, info, '32-admin-package-catalogue');

  await page.getByRole('link', { name: 'Details' }).first().click();
  await expect(page.getByRole('heading', { name: /Releases \(immutable/ })).toBeVisible();
  await shot(page, info, '33-admin-package-detail');

  await visit(page, '/admin/registries');
  await expect(page.getByRole('heading', { name: 'Registry trust & security response' })).toBeVisible();
  await expect(page.getByText('Local blocklist', { exact: false }).first()).toBeVisible();
  await shot(page, info, '34-admin-registry-trust');
});
```

- [ ] **Step 3: Add the axe pages**

In `tests/browser/a11y.spec.ts`, inside `admin dark-surface pages have no serious axe violations`, duplicate the exact two-line stanza the file uses for `/admin/roles` (visit + axe assert) twice — once for `/admin/packages` and once for `/admin/registries` — keeping the file's own assertion helper verbatim.

- [ ] **Step 4: Run the evidence**

```bash
cd tests/browser && npm run evidence && npm run a11y
```
Expected: the prior pass counts plus one new evidence test (desktop + mobile) and the a11y test still green with the two extra pages. Screenshots land in `docs/evidence/browser/<project>/`.

- [ ] **Step 5: Commit**

```bash
git add tests/browser/seed.php tests/browser/gate-a.spec.ts tests/browser/a11y.spec.ts docs/evidence/browser/
git commit -m "test(phase5): browser + axe evidence for the package catalogue and registry trust console (Inc 2)"
```

---

### Task 12: Closeout — threat-model index, ledger, protocol doc, runbook, status, final gates

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (TM-SC-01…05 → implemented)
- Modify: `docs/phase5/requirement-ledger.json` (GA-DOD-04 → R3; GA-DOD-18 note)
- Create: `docs/phase5/registry-protocol.md`
- Create: `docs/runbooks/package_registry.md`
- Modify: `PHASE_5_STATUS.md`, `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§D Inc 2 line), `CLAUDE.md` (workers list)

- [ ] **Step 1: Flip the five supply-chain fixtures**

In `docs/phase5/threat-models/fixtures.json` set `status: "implemented"` and add `"test": <path>` (ThreatModelIndexTest requires the file to exist):

- TM-SC-01 → `tests/Unit/Security/Registry/TrustChainVerifierTest.php`
- TM-SC-02 → `tests/Integration/Service/RegistrySnapshotServiceTest.php`
- TM-SC-03 → `tests/Unit/Security/Registry/TrustChainVerifierTest.php`
- TM-SC-04 → `tests/Integration/Core/AppRegistryAdminTest.php`
- TM-SC-05 → `tests/Integration/Service/RegistrySnapshotServiceTest.php`

Run: `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php 2>&1 | tail -2` → `OK`.

- [ ] **Step 2: Advance the ledger**

In `docs/phase5/requirement-ledger.json`, GA-DOD-04 (mirror the GA-DOD-10 precedent):
- `"state": "R3"`
- `"evidence": ["src/Security/Registry/TrustChainVerifier.php", "src/Service/Registry/RegistrySnapshotService.php", "tests/Unit/Security/Registry/TrustChainVerifierTest.php", "tests/Integration/Service/RegistrySnapshotServiceTest.php", "tests/Integration/Core/AppRegistryAdminTest.php", "docs/phase5/registry-protocol.md", "docs/runbooks/package_registry.md"]`
- `"notes": "Inc 2 landed 2026-07-02: fail-closed Ed25519 trust chain, signed+expiring snapshots with anti-replay + offline cache, source-pinning/uid-conflict refusal, signed key rotation + revocation, advisory ladder, registry-independent blocklist, worker:registry-refresh, staff-only read-only browse — all dark behind package_registry; install absent by design. Manifest/install/lifecycle are Inc 3 (P5-02). Browser+axe evidence captured; signature_verify_p95 MEASURED (PASS); fetch_p95 measured at staged enablement (§13.1 step 3)."`

Extend GA-DOD-18's note with: `registry.signature_verify_p95 MEASURED (PASS) and registry.snapshot_freshness enforced (CONFIG) on 2026-07-02; registry.fetch_p95 re-homed to staged enablement.`

Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php 2>&1 | tail -2` → `OK` (it verifies evidence paths exist).

- [ ] **Step 3: Write the protocol contract doc**

Create `docs/phase5/registry-protocol.md`:

```markdown
# RetroBoards registry protocol v1 (P5-01, Increment 2)

**Status:** Landed 2026-07-02, deploy-dark behind `package_registry`.
**Scope:** wire contract + verification rules for signed catalogue snapshots,
advisories, and key rotation. Install/manifest semantics are Inc 3 (P5-02).
**Authority:** subordinate to `DECISIONS.md` → `DESIGN.md` → ADR 0004 (D1–D3)
→ `docs/phase5/registry-signing-key-custody.md` (A4).

## Documents (rb-*.v1)

All documents are JSON objects signed with a **detached Ed25519 signature over
the exact JSON bytes** (never a re-encoding). Digests are sha256 hex. The
signing key is identified by `key_id` and must match a pinned row in
`registry_trust_keys` (public bytes only; A4 §1).

| Format | Purpose | Minted by (test) |
|---|---|---|
| `rb-registry-snapshot.v1` | expiring catalogue snapshot (`generated_at`, `expires_at`, `packages[]`) | `SigningHarness::mintSnapshot` |
| `rb-advisory.v1` | one advisory (`advisory_uid`, `package_uid`, `affected_version_range`/`affected_digest`, `severity`, `action`) | `mintAdvisory` |
| `rb-key-rotation.v1` | signed transition: old ACTIVE key names its successor (`old_key_id`, `new_key_id`, `new_public_key` base64) | `mintRotation` |
| `rb-release.v1` | per-release signed metadata (consumed at install — Inc 3) | `mintRelease` |

## Fetch endpoints (worker:registry-refresh)

```
GET {base_url}/rb-snapshot-envelope.v1.json
  {"format":"rb-snapshot-envelope.v1","document":"<signed JSON string>","signature":"<base64>","key_id":"..."}
GET {base_url}/rb-advisory-envelopes.v1.json          (404 = none)
  {"format":"rb-advisory-envelopes.v1","advisories":[{"document":"...","signature":"<base64>","key_id":"..."}]}
```

Fetches are EgressGuard-validated, DNS-pinned, redirect-free, and capped at
`registry.max_snapshot_bytes` (default 1 MiB). Only worker/cron code fetches —
never a web request (decision #10).

## Verification rules (fail closed — every failure refuses)

1. `key_id` must be pinned for THIS registry; algorithm `ed25519`; status
   `active` (or `rotated` within its validity window); never `revoked`.
2. Detached signature must verify over the exact document bytes.
3. `format` must equal the expected format.
4. Snapshots: `expires_at > now` (24h freshness, D2), `generated_at ≤ now+300s`,
   and `generated_at` strictly greater than the last applied snapshot
   (anti-replay); a byte-identical re-fetch is an idempotent no-op.
5. Identity: `package_uid` matches `publisher/name` (lowercase); a uid already
   owned by another registry refuses the WHOLE snapshot (source pinning /
   dependency-confusion refusal).
6. Releases are immutable: a known `(package, version)` presenting a different
   digest refuses the whole snapshot (a changed byte is a new release).
7. Registries cannot assert local trust: `trust_class` of `first_party` or
   `vetted` in a snapshot entry refuses.
8. Rotations must be signed by a currently ACTIVE pinned key naming itself as
   `old_key_id` and a distinct successor with valid 32-byte key material.

Machine-readable refusal codes live in `RegistryVerificationException::$code`
(`bad_signature`, `unknown_key`, `revoked_key`, `key_window`, `expired_snapshot`,
`replayed_snapshot`, `uid_conflict`, `release_digest_rewrite`, …) and are
asserted one-by-one in the TM-SC-01…05 fixtures.

## Escalation ladder (advisories)

`warn → warned`, `block_new → blocked`, `force_disable → blocked` (plus
disabling the installed package once installs exist — Inc 3),
`revoke → revoked`. Escalate-only; the registry-independent local blocklist
(`local_package_blocks`) refuses regardless of registry/trust state.
```

- [ ] **Step 4: Write the operator runbook**

Create `docs/runbooks/package_registry.md`:

```markdown
# Runbook — `package_registry` (Phase 5 Increment 2, deploy-dark)

**State:** default **OFF**. On = staff-only read-only catalogue browse +
registry trust console + refresh worker. Install does not exist until Inc 3.

## Enable / disable / rollback
- Enable (staged §13.1 step 3): set `features.package_registry=true` in the
  settings `features` JSON. Surfaces: `/admin/packages`, `/admin/registries`;
  worker `worker:registry-refresh` starts acting.
- Rollback: set the flag false — routes 404, the worker no-ops, rows stay
  inert. A registry rollback never rewrites recorded digests (§13.2).
- Independent of the flag: `package_registries.is_enabled=0` disables ONE
  source (no password — defensive); the local blocklist always works.

## Cron
    */30 * * * * php bin/console worker:registry-refresh
(24h freshness window, D2 — twice-hourly gives headroom; the worker is
idempotent: unchanged snapshots are no-ops.)

## Outage / staleness
A stale snapshot (`Stale snapshot` banner on /admin/packages) blocks nothing
in Inc 2 except freshness; cached signed metadata stays browsable
(`registry_snapshots`). From Inc 3 on, stale = no new install decisions.
Diagnose: run the worker manually; check telemetry `registry.refresh` events
(reason codes) and EgressGuard messages.

## Key ceremonies
Custody, rotation, and revocation procedures live in
`docs/phase5/registry-signing-key-custody.md` §5 (A4). Console mappings:
- Pin (initial setup §5.1): /admin/registries → “Pin a new public key” (password).
- Rotate (§5.3): paste the signed `rb-key-rotation.v1` envelope (password).
- Revoke (§5.4): per-key Revoke button (password + reason). Everything signed
  by the key immediately fails closed.

## Emergency response
1. Block first: /admin/registries → Local blocklist → digest or package uid
   (NO password — the brake must not wait). Applies regardless of registry state.
2. Then escalate: ingest/acknowledge advisories, revoke keys, disable the
   registry source, or dark the flag — in whatever order the incident needs.
3. Unblocking (re-enabling) requires reauthentication and is audited.

## Repair
`php bin/console repair` now also reconciles `packages.latest_release_id` and
package/release `advisory_status` from authoritative rows
(`package_latest`, `package_advisory` in the output).
```

- [ ] **Step 5: Update status + program plan + CLAUDE.md**

- `PHASE_5_STATUS.md`: in the **Status** paragraph replace “Increment 2 (registry) remains unblocked” with a landed sentence (registry protocol + identity dark behind `package_registry`; staff browse read-only; install absent until Inc 3). Update the **Suite** line with the real counts from Step 6's runs. Add a `## Increment 2 landed (2026-07-02) — P5-01 registry protocol, deploy-dark` section mirroring the Increment 1 section: scope bullets (verifier, snapshots/anti-replay/offline cache, source pinning, rotation/revocation, advisory ladder, blocklist, worker, staff browse, budgets, browser/axe), evidence pointers, and the explicit “install absent” note.
- `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` §D Increment 2: append `**Landed 2026-07-02** → trust-chain verifier + snapshot ingest (anti-replay, source pinning) + rotation/revocation + advisory ladder + local blocklist + worker:registry-refresh + staff read-only browse, dark behind package_registry; sub-plan docs/superpowers/plans/2026-07-02-phase5-increment2-registry-protocol.md.`
- `CLAUDE.md` background-workers block: add `php bin/console worker:registry-refresh   # fetch+verify signed registry snapshots/advisories (no-op while package_registry is dark)`.

- [ ] **Step 6: Final exit gates**

```bash
composer test                                   # run 1 (fresh schema via bootstrap)
composer test                                   # run 2 (reused-schema path) — both green
php bin/console verify:resolver-parity          # unchanged: 1551 tuples / 0 mismatches (Inc 1 regression guard)
php bin/console verify:phase5-budgets           # registry rows MEASURED/CONFIG
DB_DATABASE=retroboards_upgrade_rehearsal php bin/console verify:upgrade --force   # additive 0068 rehearses clean (scratch DB)
tests/backup/rehearse.sh                        # backup/restore covers the new table
```

Expected: everything green; record the two consecutive `composer test` counts for the status file. Exit-gate checklist (program plan §D Inc 2): signature / tamper / replay / staleness / key-rotation / dependency-confusion fixtures pass (TM-SC-01…05 implemented); staff browse renders (browser evidence); **install absent** (asserted by `AppRegistryCatalogTest::test_install_is_absent_everywhere`); flag dark by default (`AppFeatureFlagTest`).

- [ ] **Step 7: Commit**

```bash
git add docs/phase5/threat-models/fixtures.json docs/phase5/requirement-ledger.json \
        docs/phase5/registry-protocol.md docs/runbooks/package_registry.md \
        PHASE_5_STATUS.md docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md CLAUDE.md
git commit -m "docs(phase5): Inc 2 closeout - TM-SC-01..05 implemented, GA-DOD-04 -> R3, protocol contract + runbook"
```

Then request the final whole-branch review before merging `phase5-inc2-registry-protocol` into `main` (Inc 1 precedent: review → fix → `git merge --ff-only`).

---

## Plan self-review

**Spec coverage (program plan §D Increment 2 scope, line by line):** thin repos over `0049` → Task 2; canonical `(source_id + package_uid)` identity → Tasks 1/4 (`PackageIdentity`, uid_conflict); pure trust-chain + ed25519 detached-signature verifier (constant-time via libsodium, fail-closed, public-key-only) → Task 1; sha256 digest discipline → Tasks 1/4 (hex-validated digests; `hash_equals` comparisons; full artifact-digest verification is an install-time concern → Inc 3, recorded in Out-of-scope); key rotation/revocation via signed transition → Tasks 1/5/9; signed+expiring snapshots with anti-replay + offline cache → Tasks 3/4; source-pinning / dependency-confusion refusal → Task 4 (TM-SC-05); compatibility resolution → Task 8 (`CoreVersion::satisfies` badges; the F2 resolver itself landed at Foundation); advisory ingest/evaluate ladder → Task 6; registry-independent local blocklist → Tasks 2/6/9; refresh worker with EgressGuard-guarded fetch → Task 7; staff-only read-only catalogue browse, no install → Task 8 (+ negative assertions); migration `0068` incl. `moderation_log.target_type` widen → Task 3; exit-gate fixtures → Tasks 1/4/5/9 + Task 12 index flips; evidence discipline (browser/axe, noindex, telemetry, budgets, runbook, ledger) → Tasks 4/6/7 (telemetry), 8/9 (noindex), 10 (budgets), 11 (browser/axe), 12 (docs/ledger).

**Deliberate scope decisions:** whole-snapshot refusal on any bad entry (an applied snapshot implies every entry passed — simpler invariant, strictly safer); blocklist add password-free / remove reauth-gated (emergency brake vs privilege-restoring direction); manual advisory paste reauth-gated at the controller (operator trust decision) while the worker's fetch path is authenticated by the signature itself; `registry.fetch_p95` re-homed to staged enablement (no live registry exists to measure honestly).

**Type consistency:** verified — `RegistryFetchResult(status, body, error)` construction matches Task 7's class; `applySnapshot` return keys (`status/packages/releases`) match worker + tests; exception `->code` strings match between implementation and every `expectCode` call; repository signatures in Tasks 4–9 match Task 2; `Response::body()/getHeader()/status()`, `Request::allInput()`, `new ReauthGate(new PasswordHasher())` all verified against the current tree.

## Execution note

Implementer: Henry (self-implementing, Inc 1 precedent). Suggested run order is task order; Tasks 8 and 9 share `App.php`/`AppFeatureFlagTest` edits and are most comfortable back-to-back. When done, hand the branch back for the final whole-branch review with: base `main` (pre-branch SHA), head = branch tip, this plan as PLAN_OR_REQUIREMENTS.
