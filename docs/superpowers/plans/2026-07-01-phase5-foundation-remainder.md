# Phase 5 Foundation Remainder — F2 · F4 · F6 · F7 · F8 · F10 · F11 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the seven outstanding Foundation increment items from the Gate A program plan §B — `App::CORE_VERSION` + the compatibility-version primitive (**F2**), the `DataClasses` catalogue (**F4**), the Ed25519 signed-artifact test harness + dev/test registry fixtures (**F6**), the unified `ReauthGate` (**F7**), config-gated `Telemetry` + `LogRedactor` (**F8**), the machine-checkable R0–R5 requirement ledger + evidence-map test + all-flags-off core-survival regression (**F10**), and the six-dossier threat-model set with its negative-fixture index (**F11**) — all deploy-dark, no migrations, no flag flips, closing the Foundation exit gate so Increment 1 (resolver shadow) and Increment 2 (registry) can start.

**Architecture:** Every item follows an existing house pattern. `CoreVersion` and `DataClasses` are pure static catalogues mirroring `ApiScopes`/`CapabilityCatalog`. The signing harness is test-support code (`Tests\Support\Phase5\`) minting real Ed25519 artifacts with libsodium — production trust roots stay out-of-band; only the 0049 tables' PUBLIC key column is ever seeded. `ReauthGate` consolidates the five scattered `hasher->verify` + `ValidationException(['current_password' => …])` sites behind one behavior-preserving class (the present-factor window; passkey becomes a second factor in Inc 7). `Telemetry` is a per-request-container service whose `emit()` no-ops unless `telemetry.enabled` and always passes context through `LogRedactor`. The requirement ledger is checked-in JSON validated by a pure unit test in the `MigrationLedgerTest` analyzer style; the threat-model dossiers get the same treatment via a fixtures index. **No migration is created anywhere in this plan** (§C allocates the Foundation only `0066`/`0067`, both landed; the F1 guard enforces this).

**Tech Stack:** PHP 8.2+ (ext-sodium — bundled, verified present via `php -m`), MySQL/MariaDB (PDO, `EMULATE_PREPARES=false`), PHPUnit 11 via the in-process kernel harness (`Tests\Support\TestCase`), no new Composer packages (one new `ext-*` declaration).

## Global Constraints

*Every task implicitly includes this section. Values copied verbatim from CLAUDE.md, the Gate A program plan §B/Global Constraints, ADR 0004, and `PHASE_5_PLAN.md`.*

- **Deploy-dark, zero behavior change.** No feature flag is added or flipped. `AppFeatureFlagTest::test_phase5_foundation_flags_default_dark` must pass unchanged after every task. F7 is a **behavior-preserving refactor**: every user-visible message, exception type, and field key stays byte-identical (`'Your current password is incorrect.'`, `'current_password'`, `'Set a password before managing two-factor authentication.'`). F8 telemetry defaults `TELEMETRY_ENABLED=false`.
- **No migrations.** The §C allocation table gives this work **no number**; the next free number `0068` belongs to Increment 2. `tests/Unit/Core/MigrationLedgerTest.php` (F1) fails CI on any new file — do not create one. All F6 registry rows are inserted **at test runtime** into the inert 0049 tables and roll back with the per-test transaction.
- **Private keys never touch the database or production code.** Only `registry_trust_keys.public_key` (VARBINARY, PUBLIC bytes) is written. The signing harness lives under `tests/Support/` (autoload-dev only — not shipped) and exposes no secret-key accessor.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET`; never reuse a named placeholder (use `?` positional); **UTC everywhere** (`UTC_TIMESTAMP()` in SQL, `gmdate()` in PHP).
- **PHPUnit is strict** (`failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`): ≥1 assertion per test, no stray output, no PHP warnings. Never `echo`/`print` from harness code; telemetry's default sink is `error_log` (stderr — the established kernel pattern at `src/Core/App.php:348`), and unit tests inject a capture sink instead.
- **Escaping/CSP untouched:** no UI surface anywhere in this plan ⇒ no template, no JS, no Playwright/axe requirement (DESIGN §13 applies to UI-visible work only). Evidence here is enforcing PHPUnit + recorded docs.
- **Docs are artifacts:** F11 dossiers are **Recorded — pending owner review** (the exact posture A1/A4/A5 used before ADR 0012 accepted them). Do not mark them accepted.
- **Commits:** small, per task, message prefix `feat(phase5):`/`test(phase5):`/`docs(phase5):`/`refactor(phase5):`, and end every commit message with:
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
- **Suite invariant:** `composer test` green after every task (the F7 task additionally lists its focused suites). Current baseline: 857 tests / 4456 assertions green on this branch.

---

## File Structure

| File | Create/Modify | Responsibility |
|---|---|---|
| `src/Core/App.php` | Modify ×3 | F2 `CORE_VERSION` constant; F7 `ReauthGate` binding + 5 rebinding edits; F8 `Telemetry` binding + kernel `http.request` emit |
| `src/Support/CoreVersion.php` | Create | F2 — semver-ish validation + inclusive-range `satisfies()` (fail-closed) |
| `src/Security/DataClasses.php` | Create | F4 — code-owned data-class catalogue + consent vocabulary (mirrors `CapabilityCatalog`) |
| `composer.json` / `composer.lock` | Modify | F6 — declare `ext-sodium` |
| `tests/Support/Phase5/SigningHarness.php` | Create | F6 — Ed25519 keypair; mint signed snapshot/release/advisory/rotation + tampered/expired variants |
| `tests/Support/Phase5/RegistryFixtures.php` | Create | F6 — dev/test-only seed of registry + trust key (public only) + publisher/package/release |
| `src/Security/ReauthGate.php` | Create | F7 — single present-factor reauth policy (decision #26) |
| `src/Service/{ApiTokenService,WebhookService,MfaService,AccountLifecycleService,AccountService}.php` | Modify | F7 — consume `ReauthGate` |
| `src/Support/LogRedactor.php` | Create | F8 — key/value-shape redaction for every log line |
| `src/Core/Telemetry.php` | Create | F8 — config-gated correlation-ID emitter (dark by default) |
| `config/config.php` | Modify | F8 — `telemetry` section (`TELEMETRY_ENABLED`, default false) |
| `docs/phase5/requirement-ledger.json` | Create | F10 — machine-checkable R0–R5 ledger + per-flag rollback map |
| `docs/phase5/threat-models/*.md` (6 files) + `fixtures.json` | Create | F11 — dossiers + negative-fixture stub index |
| `tests/Unit/Support/CoreVersionTest.php` | Create | F2 boundary cases |
| `tests/Unit/Security/DataClassesTest.php` | Create | F4 invariants |
| `tests/Unit/Support/SigningHarnessTest.php` | Create | F6 mint→verify + tamper/expiry/rotation self-test |
| `tests/Integration/Core/RegistryFixturesTest.php` | Create | F6 seeded rows verify harness signatures; public-key-only invariant |
| `tests/Integration/Security/ReauthGateTest.php` | Create | F7 gate behavior (incl. no-password account) |
| `tests/Unit/Support/LogRedactorTest.php` | Create | F8 redaction matrix |
| `tests/Unit/Core/TelemetryRedactionTest.php` | Create | F8 emit gating + redaction + correlation IDs |
| `tests/Unit/Core/Phase5EvidenceMapTest.php` | Create | F10 ledger schema/coverage/evidence-existence guard |
| `tests/Integration/Core/AppFeatureFlagTest.php` | Modify | F10 all-flags-off core-survival regression |
| `tests/Unit/Core/ThreatModelIndexTest.php` | Create | F11 dossier ↔ fixture-index parity guard |
| Test files at 6 direct-construction sites | Modify | F7 constructor updates |
| `PHASE_5_STATUS.md`, program plan §B | Modify | Closeout — record the landed items |

---

## Task 1: F2 — `App::CORE_VERSION` + `CoreVersion` compatibility primitive

**Files:**
- Modify: `src/Core/App.php` (class header, line ~185)
- Create: `src/Support/CoreVersion.php`
- Test: `tests/Unit/Support/CoreVersionTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `App::CORE_VERSION` (string `'0.5.0-dev'`); `CoreVersion::current(): string`; `CoreVersion::isValid(string): bool`; `CoreVersion::satisfies(?string $min, ?string $max, ?string $version = null): bool` (inclusive bounds, null = unbounded, malformed input **fails closed**). Increment 2's compatibility resolver (P5-01 SP3) and Increment 3's manifest validation consume these against `package_releases.core_min/core_max` (`0049`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/CoreVersionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Core\App;
use App\Support\CoreVersion;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F2 — the compatibility-version primitive the Inc-2 resolver
 * (P5-01 SP3; §10.3 target evidence "CompatibilityResolverTest") builds on.
 * `package_releases.core_min/core_max` (0049) are "semver-ish" strings; this
 * pins the exact semantics: MAJOR.MINOR.PATCH[-prerelease], inclusive bounds,
 * version_compare ordering, malformed input fails closed.
 */
final class CoreVersionTest extends TestCase
{
    public function test_core_version_constant_is_wellformed(): void
    {
        self::assertTrue(CoreVersion::isValid(App::CORE_VERSION));
        self::assertSame(App::CORE_VERSION, CoreVersion::current());
    }

    public function test_null_bounds_are_unbounded(): void
    {
        self::assertTrue(CoreVersion::satisfies(null, null, '0.5.0'));
    }

    public function test_bounds_are_inclusive(): void
    {
        self::assertTrue(CoreVersion::satisfies('0.5.0', '0.5.0', '0.5.0'));
        self::assertTrue(CoreVersion::satisfies('0.4.0', '0.6.0', '0.5.0'));
    }

    public function test_outside_bounds_fail(): void
    {
        self::assertFalse(CoreVersion::satisfies('0.6.0', null, '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, '0.4.9', '0.5.0'));
    }

    public function test_dev_prerelease_orders_before_the_bare_release(): void
    {
        // A '-dev' core must NOT satisfy a package that requires the released
        // core (fail closed), but must satisfy one accepting the prior minor.
        self::assertFalse(CoreVersion::satisfies('0.5.0', null, '0.5.0-dev'));
        self::assertTrue(CoreVersion::satisfies('0.4.0', null, '0.5.0-dev'));
    }

    public function test_malformed_input_fails_closed(): void
    {
        self::assertFalse(CoreVersion::satisfies('banana', null, '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, '1.0', '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, null, 'not-a-version'));
        self::assertFalse(CoreVersion::isValid('1.0'));
        self::assertFalse(CoreVersion::isValid('v1.0.0'));
        self::assertTrue(CoreVersion::isValid('1.0.0-rc.1'));
    }

    public function test_default_version_is_the_core_constant(): void
    {
        self::assertTrue(CoreVersion::satisfies('0.1.0', null));
        self::assertFalse(CoreVersion::satisfies('99.0.0', null));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Support/CoreVersionTest.php`
Expected: **Error** — `Class "App\Support\CoreVersion" not found` (and `App::CORE_VERSION` undefined).

- [ ] **Step 3: Add the constant and the class**

In `src/Core/App.php`, immediately after `final class App` / `{` (line ~185), add the constant **above** `private Router $router;`:

```php
final class App
{
    /**
     * Core compatibility version (Foundation F2). Package releases declare
     * `core_min`/`core_max` (0049 package_releases) against this identity;
     * `App\Support\CoreVersion::satisfies()` is the comparison the Inc-2
     * compatibility resolver builds on. `-dev` orders BEFORE the bare release
     * (version_compare), so a dev core fails closed for packages that require
     * the released core. Bump on release; distinct from the cosmetic
     * `brand_version` setting (cache-busting only).
     */
    public const CORE_VERSION = '0.5.0-dev';

    private Router $router;
```

Create `src/Support/CoreVersion.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;

/**
 * Semver-ish core-version comparisons (Foundation F2). Compatibility bounds
 * are `MAJOR.MINOR.PATCH[-prerelease]` strings; anything else is malformed
 * and FAILS CLOSED (never satisfies). Ordering is PHP's version_compare —
 * notably '0.5.0-dev' < '0.5.0'. Consumed by the Inc-2 compatibility
 * resolver (P5-01 SP3) and Inc-3 manifest validation (P5-02).
 */
final class CoreVersion
{
    private const PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.\-]*)?$/';

    public static function current(): string
    {
        return App::CORE_VERSION;
    }

    public static function isValid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    /** Inclusive-bounds range check; null bound = unbounded; malformed input fails closed. */
    public static function satisfies(?string $min, ?string $max, ?string $version = null): bool
    {
        $version ??= self::current();
        if (!self::isValid($version)) {
            return false;
        }
        if ($min !== null && (!self::isValid($min) || version_compare($version, $min, '<'))) {
            return false;
        }
        if ($max !== null && (!self::isValid($max) || version_compare($version, $max, '>'))) {
            return false;
        }
        return true;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Support/CoreVersionTest.php`
Expected: **OK (7 tests)**.

- [ ] **Step 5: Commit**

```bash
git add src/Core/App.php src/Support/CoreVersion.php tests/Unit/Support/CoreVersionTest.php
git commit -m "feat(phase5): add App::CORE_VERSION + CoreVersion range primitive (F2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 2: F4 — `DataClasses` catalogue + consent vocabulary

**Files:**
- Create: `src/Security/DataClasses.php`
- Test: `tests/Unit/Security/DataClassesTest.php`

**Interfaces:**
- Consumes: nothing (pure static catalogue; mirrors `App\Security\CapabilityCatalog` / `ApiScopes`).
- Produces: `DataClasses::all(): array<string,array{0:string,1:string,2:?string}>` (key → [risk, description, consent]); `keys(): list<string>`; `has(string): bool`; `risk(string): string`; `isProtected(string): bool`; `grantable(string): bool`; `consent(string): ?string`. Inc-3 manifest validation (`installed_package_permissions.kind = 'data_class'`, `0049`), Inc-5 consent summaries, and Inc-1/6 risk labeling validate against these keys.

The catalogue instantiates ADR 0004 **D4** ("high-risk data classes … consent vocabulary") and `PHASE_5_PLAN.md` §5 #8, which names the high-risk set verbatim: *private-board content, DMs, user email/PII, moderation data, auth events, and security configuration*. Risk vocabulary matches the capability catalogue: `low|medium|high|protected`; `protected` ⇒ never grantable to a package ⇒ `consent === null` (same invariant shape F3 pinned).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Security/DataClassesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DataClasses;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F4 — the approved local data-class catalogue (ADR 0004 D4,
 * PHASE_5_PLAN §5 #8). Mirrors CapabilityCatalogTest's invariants: pinned
 * count, namespaced keys, closed risk vocabulary, protected ⇔ non-grantable
 * ⇔ no consent string, and the six §5 #8 high-risk classes present.
 */
final class DataClassesTest extends TestCase
{
    public function test_catalogue_has_exactly_10_keys(): void
    {
        self::assertCount(10, DataClasses::all());
        self::assertCount(10, DataClasses::keys());
    }

    public function test_every_key_is_namespaced_with_valid_risk(): void
    {
        foreach (DataClasses::all() as $key => $def) {
            self::assertMatchesRegularExpression('/^[a-z]+(?:\.[a-z_]+)+$/', $key);
            self::assertContains($def[0], ['low', 'medium', 'high', 'protected'], "$key risk");
            self::assertNotSame('', trim($def[1]), "$key description");
        }
    }

    public function test_protected_invariant_holds(): void
    {
        foreach (DataClasses::keys() as $key) {
            $protected = DataClasses::isProtected($key);
            self::assertSame($protected, DataClasses::risk($key) === 'protected', $key);
            self::assertSame($protected, !DataClasses::grantable($key), $key);
            if ($protected) {
                self::assertNull(DataClasses::consent($key), "$key must have no consent string");
            } else {
                self::assertNotNull(DataClasses::consent($key), $key);
                self::assertNotSame('', trim((string) DataClasses::consent($key)), $key);
            }
        }
    }

    public function test_the_six_high_risk_classes_from_spec_are_present(): void
    {
        // PHASE_5_PLAN §5 #8, verbatim set. security.config is the protected one.
        foreach (['content.private', 'messages.direct', 'user.pii', 'moderation.records', 'auth.events'] as $key) {
            self::assertTrue(DataClasses::has($key), $key);
            self::assertSame('high', DataClasses::risk($key), $key);
        }
        self::assertTrue(DataClasses::has('security.config'));
        self::assertSame('protected', DataClasses::risk('security.config'));
    }

    public function test_unknown_key_is_rejected(): void
    {
        self::assertFalse(DataClasses::has('content.everything'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Security/DataClassesTest.php`
Expected: **Error** — `Class "App\Security\DataClasses" not found`.

- [ ] **Step 3: Write the catalogue**

Create `src/Security/DataClasses.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

/**
 * Code-owned data-class catalogue + human consent vocabulary (Foundation F4;
 * ADR 0004 D4; PHASE_5_PLAN §5 #8). A data class names WHAT DATA a package
 * may access or receive; manifests declare them, operators grant them, and
 * `installed_package_permissions.kind='data_class'` (0049) stores them. High-
 * risk access is exceptional and separately named; `protected` classes are
 * never grantable to any package. Mirrors CapabilityCatalog (a static
 * catalogue, not a service). Deploy-dark: nothing validates against this
 * until Inc 3 (P5-02 manifest validation) lands.
 *
 * key => [risk(low|medium|high|protected), description, consent (null iff protected)].
 *
 * @phpstan-type DataClassDef array{0:string,1:string,2:?string}
 */
final class DataClasses
{
    /** @var array<string,array{0:string,1:string,2:?string}> */
    private const CLASSES = [
        'content.metadata' => ['low', 'Content identifiers and state only — thread/post/board IDs, timestamps, status transitions; never bodies.', 'See which public topics and replies changed (IDs and status only, never the text).'],
        'content.public' => ['low', 'Bodies and titles of content on public boards.', 'Read the text of public topics and replies.'],
        'content.private' => ['high', 'Bodies, titles, or existence of content on private or hidden boards.', 'Read content from private or hidden boards.'],
        'messages.direct' => ['high', 'Direct-message and group-conversation content or metadata.', "Read members' direct and group messages."],
        'user.directory' => ['medium', 'Public member-directory data — usernames, display names, public profile fields, join dates.', 'See the public member directory (usernames and public profiles).'],
        'user.pii' => ['high', 'Member email addresses, IP-derived data, and verification state.', "Access members' email addresses and other personal data."],
        'moderation.records' => ['high', 'Reports, moderation-log entries, appeals, and sanction history.', 'Read moderation reports and action history.'],
        'auth.events' => ['high', 'Authentication activity — sign-ins, MFA events, credential changes.', 'See sign-in and credential-change activity.'],
        'security.config' => ['protected', 'Secrets, signing keys, provider configuration, and trust roots. Never grantable to a package.', null],
        'package.own_storage' => ['low', "The package's own quota-limited storage namespace.", 'Store its own settings and data in an isolated package storage area.'],
    ];

    /** @return array<string,array{0:string,1:string,2:?string}> */
    public static function all(): array
    {
        return self::CLASSES;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CLASSES);
    }

    public static function has(string $key): bool
    {
        return isset(self::CLASSES[$key]);
    }

    public static function risk(string $key): string
    {
        return self::def($key)[0];
    }

    public static function isProtected(string $key): bool
    {
        return self::risk($key) === 'protected';
    }

    /** A protected data class can never be declared, granted, or consented for a package. */
    public static function grantable(string $key): bool
    {
        return !self::isProtected($key);
    }

    public static function consent(string $key): ?string
    {
        return self::def($key)[2];
    }

    /** @return array{0:string,1:string,2:?string} */
    private static function def(string $key): array
    {
        if (!isset(self::CLASSES[$key])) {
            throw new InvalidArgumentException("Unknown data class: {$key}");
        }
        return self::CLASSES[$key];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Security/DataClassesTest.php`
Expected: **OK (5 tests)**.

- [ ] **Step 5: Commit**

```bash
git add src/Security/DataClasses.php tests/Unit/Security/DataClassesTest.php
git commit -m "feat(phase5): add DataClasses catalogue + consent vocabulary (F4)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 3: F6 (part 1) — declare ext-sodium + `SigningHarness`

**Files:**
- Modify: `composer.json` + `composer.lock` (via the composer CLI)
- Create: `tests/Support/Phase5/SigningHarness.php`
- Test: `tests/Unit/Support/SigningHarnessTest.php`

**Interfaces:**
- Consumes: ext-sodium (`sodium_crypto_sign_*` — already loaded; `php -m | grep sodium` confirms).
- Produces (consumed by P5-01/02/03/04/07-A/16 fixture corpora — this is the **contract Inc 2's verifier must accept**):
  - `SigningHarness::generate(string $keyId = 'test-root-1'): self`
  - `->keyId(): string`, `->publicKey(): string` (raw 32 bytes; **no secret-key accessor exists**)
  - `->sign(string $bytes): string` (detached Ed25519), `SigningHarness::verify(string $bytes, string $signature, string $publicKey): bool`
  - `->mintSnapshot(array $overrides = []): array{json:string,signature:string,key_id:string}` — doc format `rb-registry-snapshot.v1`
  - `->mintExpiredSnapshot(): array{json:string,signature:string,key_id:string}`
  - `->mintRelease(array $overrides = []): array{json:string,signature:string,key_id:string,digest:string,manifest_json:string,version:string,uid:string}` — doc format `rb-release.v1`
  - `->mintAdvisory(array $overrides = []): array{json:string,signature:string,key_id:string}` — doc format `rb-advisory.v1`
  - `->mintRotation(self $successor): array{json:string,signature:string,key_id:string}` — doc format `rb-key-rotation.v1`, signed by the **old** key
  - `SigningHarness::tamper(string $bytes): string` (flips one byte)

**Signed-document contract (fixture format v1).** Signatures are detached Ed25519 over the **exact JSON bytes** as encoded (no re-canonicalization — the verifier stores and verifies the bytes it fetched):

```json
{"format":"rb-registry-snapshot.v1","registry":"rb-test","generated_at":"2026-07-01T00:00:00Z","expires_at":"2026-07-02T00:00:00Z","packages":[{"uid":"acme/midnight-theme","type":"theme","releases":[{"version":"1.0.0","digest":"<64-hex sha256>","core_min":"0.1.0","core_max":null,"channel":"stable","advisory":"none"}]}]}
{"format":"rb-release.v1","uid":"acme/midnight-theme","version":"1.0.0","digest":"<64-hex>","manifest":{...}}
{"format":"rb-advisory.v1","advisory_uid":"RB-TEST-0001","package_uid":"acme/midnight-theme","affected_version_range":"<=1.0.0","severity":"high","action":"block_new","summary":"test advisory","issued_at":"..."}
{"format":"rb-key-rotation.v1","registry":"rb-test","old_key_id":"test-root-1","new_key_id":"test-root-2","new_public_key":"<base64>","effective_at":"..."}
```

- [ ] **Step 1: Declare ext-sodium**

Run: `composer require ext-sodium:"*" --no-interaction`
Expected: `composer.json` gains `"ext-sodium": "*"` under `require`; lock file updated; no packages downloaded. Then run `composer validate` → `./composer.json is valid`.

- [ ] **Step 2: Write the failing self-test**

Create `tests/Unit/Support/SigningHarnessTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\Phase5\SigningHarness;

/**
 * Foundation F6 — the signed-artifact harness's own contract. The real
 * verifier is Inc 2 (P5-01 SP2); until then this proves the fixtures are
 * genuine Ed25519 artifacts: mint→verify round-trips, one flipped byte
 * fails, expiry/rotation docs carry the right claims. Real crypto, not
 * mocks (PHASE_5_PLAN §10.2).
 */
final class SigningHarnessTest extends TestCase
{
    public function test_snapshot_mints_and_verifies_and_tamper_fails(): void
    {
        $root = SigningHarness::generate();
        $snap = $root->mintSnapshot();

        self::assertSame('test-root-1', $snap['key_id']);
        self::assertTrue(SigningHarness::verify($snap['json'], $snap['signature'], $root->publicKey()));
        self::assertFalse(SigningHarness::verify(SigningHarness::tamper($snap['json']), $snap['signature'], $root->publicKey()));

        $doc = json_decode($snap['json'], true);
        self::assertSame('rb-registry-snapshot.v1', $doc['format']);
        self::assertSame('rb-test', $doc['registry']);
        self::assertGreaterThan(strtotime($doc['generated_at']), strtotime($doc['expires_at']));
    }

    public function test_wrong_key_fails_verification(): void
    {
        $root = SigningHarness::generate();
        $other = SigningHarness::generate('test-root-2');
        $snap = $root->mintSnapshot();

        self::assertNotSame($root->publicKey(), $other->publicKey());
        self::assertFalse(SigningHarness::verify($snap['json'], $snap['signature'], $other->publicKey()));
    }

    public function test_expired_snapshot_carries_past_expiry(): void
    {
        $snap = SigningHarness::generate()->mintExpiredSnapshot();
        $doc = json_decode($snap['json'], true);
        self::assertLessThan(time(), strtotime($doc['expires_at']));
    }

    public function test_release_digest_is_sha256_and_doc_verifies(): void
    {
        $root = SigningHarness::generate();
        $rel = $root->mintRelease();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $rel['digest']);
        self::assertTrue(SigningHarness::verify($rel['json'], $rel['signature'], $root->publicKey()));
        $doc = json_decode($rel['json'], true);
        self::assertSame('rb-release.v1', $doc['format']);
        self::assertSame($rel['digest'], $doc['digest']);
        self::assertSame('acme/midnight-theme', $rel['uid']);
        self::assertSame('1.0.0', $rel['version']);
        self::assertJson($rel['manifest_json']);
    }

    public function test_advisory_and_rotation_docs_verify_with_expected_claims(): void
    {
        $root = SigningHarness::generate();
        $adv = $root->mintAdvisory(['action' => 'revoke']);
        self::assertTrue(SigningHarness::verify($adv['json'], $adv['signature'], $root->publicKey()));
        self::assertSame('revoke', json_decode($adv['json'], true)['action']);

        $next = SigningHarness::generate('test-root-2');
        $rot = $root->mintRotation($next);
        // Signed by the OLD key; names the NEW key + its public key.
        self::assertSame('test-root-1', $rot['key_id']);
        self::assertTrue(SigningHarness::verify($rot['json'], $rot['signature'], $root->publicKey()));
        $doc = json_decode($rot['json'], true);
        self::assertSame('rb-key-rotation.v1', $doc['format']);
        self::assertSame('test-root-2', $doc['new_key_id']);
        self::assertSame($next->publicKey(), base64_decode($doc['new_public_key'], true));
    }

    public function test_tamper_changes_exactly_one_byte_and_length_is_preserved(): void
    {
        $bytes = '{"a":1}';
        $tampered = SigningHarness::tamper($bytes);
        self::assertSame(strlen($bytes), strlen($tampered));
        self::assertNotSame($bytes, $tampered);
    }

    public function test_harness_exposes_no_secret_key(): void
    {
        // Public-key-only invariant (§8.2 #1): the harness API surface must
        // not leak signing material to fixtures or (worse) seeded rows.
        $methods = array_map(
            static fn (\ReflectionMethod $m) => $m->getName(),
            (new \ReflectionClass(SigningHarness::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        foreach ($methods as $name) {
            self::assertDoesNotMatchRegularExpression('/secret|private/i', $name);
        }
        self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen(SigningHarness::generate()->publicKey()));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Support/SigningHarnessTest.php`
Expected: **Error** — `Class "Tests\Support\Phase5\SigningHarness" not found`.

- [ ] **Step 4: Write the harness**

Create `tests/Support/Phase5/SigningHarness.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

/**
 * Foundation F6 — Ed25519 test-key tooling minting the signed artifacts the
 * supply-chain evidence corpus needs (program plan §B-F6): catalogue
 * snapshots, releases, advisories, key-rotation transitions, plus tampered/
 * expired variants. TEST/DEV ONLY (autoload-dev): production trust roots are
 * operator-held out-of-band (ADR 0004 D2) and never generated here. The
 * secret key is private to this object — no accessor, never persisted.
 *
 * Contract for Inc 2 (P5-01 SP2): detached Ed25519 signature over the EXACT
 * JSON bytes of a `rb-*.v1` document; digests are sha256 hex.
 */
final class SigningHarness
{
    private function __construct(
        private string $keyId,
        private string $publicKeyBytes,
        private string $secretKeyBytes,
    ) {
    }

    public static function generate(string $keyId = 'test-root-1'): self
    {
        $pair = sodium_crypto_sign_keypair();

        return new self(
            $keyId,
            sodium_crypto_sign_publickey($pair),
            sodium_crypto_sign_secretkey($pair),
        );
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    /** Raw 32-byte Ed25519 public key — the only key material that may reach a DB row. */
    public function publicKey(): string
    {
        return $this->publicKeyBytes;
    }

    public function sign(string $bytes): string
    {
        return sodium_crypto_sign_detached($bytes, $this->secretKeyBytes);
    }

    public static function verify(string $bytes, string $signature, string $publicKey): bool
    {
        try {
            return sodium_crypto_sign_verify_detached($signature, $bytes, $publicKey);
        } catch (\SodiumException) {
            return false;
        }
    }

    /** Flip one bit of the last byte — length-preserving, guaranteed different. */
    public static function tamper(string $bytes): string
    {
        $i = strlen($bytes) - 1;
        $bytes[$i] = chr(ord($bytes[$i]) ^ 0x01);

        return $bytes;
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    public function mintSnapshot(array $overrides = []): array
    {
        $doc = array_replace([
            'format' => 'rb-registry-snapshot.v1',
            'registry' => 'rb-test',
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
            'packages' => [[
                'uid' => 'acme/midnight-theme',
                'type' => 'theme',
                'releases' => [[
                    'version' => '1.0.0',
                    'digest' => hash('sha256', 'artifact:acme/midnight-theme:1.0.0'),
                    'core_min' => '0.1.0',
                    'core_max' => null,
                    'channel' => 'stable',
                    'advisory' => 'none',
                ]],
            ]],
        ], $overrides);

        return $this->signedDoc($doc);
    }

    /** @return array{json:string,signature:string,key_id:string} */
    public function mintExpiredSnapshot(): array
    {
        return $this->mintSnapshot([
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 172800),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400),
        ]);
    }

    /**
     * @param array<string,mixed> $overrides keys: uid, version, artifact (bytes the digest hashes), manifest (array)
     * @return array{json:string,signature:string,key_id:string,digest:string,manifest_json:string,version:string,uid:string}
     */
    public function mintRelease(array $overrides = []): array
    {
        $uid = (string) ($overrides['uid'] ?? 'acme/midnight-theme');
        $version = (string) ($overrides['version'] ?? '1.0.0');
        $artifact = (string) ($overrides['artifact'] ?? "artifact:{$uid}:{$version}");
        $manifest = (array) ($overrides['manifest'] ?? [
            // Placeholder fixture shape; the real manifest.v2 schema is Inc 3
            // (P5-02 SP1) scope and will extend this without breaking the
            // signed-doc contract.
            'format' => 'rb-manifest.v2',
            'uid' => $uid,
            'type' => 'theme',
            'version' => $version,
        ]);
        $digest = hash('sha256', $artifact);

        $signed = $this->signedDoc([
            'format' => 'rb-release.v1',
            'uid' => $uid,
            'version' => $version,
            'digest' => $digest,
            'manifest' => $manifest,
        ]);

        return $signed + [
            'digest' => $digest,
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES) ?: '{}',
            'version' => $version,
            'uid' => $uid,
        ];
    }

    /** @param array<string,mixed> $overrides @return array{json:string,signature:string,key_id:string} */
    public function mintAdvisory(array $overrides = []): array
    {
        return $this->signedDoc(array_replace([
            'format' => 'rb-advisory.v1',
            'advisory_uid' => 'RB-TEST-0001',
            'package_uid' => 'acme/midnight-theme',
            'affected_version_range' => '<=1.0.0',
            'severity' => 'high',
            'action' => 'block_new',
            'summary' => 'Test advisory fixture',
            'issued_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $overrides));
    }

    /** Old key signs the transition naming the successor. @return array{json:string,signature:string,key_id:string} */
    public function mintRotation(self $successor): array
    {
        return $this->signedDoc([
            'format' => 'rb-key-rotation.v1',
            'registry' => 'rb-test',
            'old_key_id' => $this->keyId,
            'new_key_id' => $successor->keyId(),
            'new_public_key' => base64_encode($successor->publicKey()),
            'effective_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /** @param array<string,mixed> $doc @return array{json:string,signature:string,key_id:string} */
    private function signedDoc(array $doc): array
    {
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode fixture document.');
        }

        return ['json' => $json, 'signature' => $this->sign($json), 'key_id' => $this->keyId];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Support/SigningHarnessTest.php`
Expected: **OK (7 tests)**.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock tests/Support/Phase5/SigningHarness.php tests/Unit/Support/SigningHarnessTest.php
git commit -m "test(phase5): add Ed25519 signed-artifact harness + ext-sodium (F6)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 4: F6 (part 2) — `RegistryFixtures` dev/test seed over the 0049 tables

**Files:**
- Create: `tests/Support/Phase5/RegistryFixtures.php`
- Test: `tests/Integration/Core/RegistryFixturesTest.php`

**Interfaces:**
- Consumes: `App\Core\Database` (`insert()/fetch()`), `Tests\Support\Phase5\SigningHarness` (Task 3), the inert `0049` tables (`package_registries`, `registry_trust_keys`, `package_publishers`, `packages`, `package_releases`).
- Produces: `RegistryFixtures::seed(Database $db, SigningHarness $root): array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int}` — the "first registry" corpus every Inc-2/3/4/5 integration test starts from. Rows insert inside the per-test transaction (rolled back in tearDown); nothing needs a migration and `package_registries.is_enabled` stays `0` (dark).

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Core/RegistryFixturesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/**
 * Foundation F6 — the dev/test-only trust-root + first-registry seed
 * (program plan §B-F6). Production roots stay out-of-band; this proves the
 * seeded rows are usable fixtures: the stored PUBLIC key verifies harness
 * signatures, the release row carries the signed metadata, and no secret
 * material exists anywhere in the seeded shape.
 */
final class RegistryFixturesTest extends TestCase
{
    public function test_seed_creates_a_dark_registry_with_verifying_trust_key(): void
    {
        $root = SigningHarness::generate();
        $ids = RegistryFixtures::seed($this->db, $root);

        $registry = $this->db->fetch('SELECT * FROM package_registries WHERE id = ?', [$ids['registry_id']]);
        self::assertNotNull($registry);
        self::assertSame('rb-test', $registry['source_id']);
        self::assertSame(0, (int) $registry['is_enabled'], 'seeded registry must stay dark');

        $key = $this->db->fetch('SELECT * FROM registry_trust_keys WHERE id = ?', [$ids['trust_key_id']]);
        self::assertNotNull($key);
        self::assertSame('ed25519', $key['algorithm']);
        self::assertSame('active', $key['status']);
        self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($key['public_key']), 'public key only — 32 bytes, never the 64-byte secret');

        // The stored public key verifies a fresh harness-signed snapshot…
        $snap = $root->mintSnapshot();
        self::assertTrue(SigningHarness::verify($snap['json'], $snap['signature'], $key['public_key']));
        // …and rejects a tampered one.
        self::assertFalse(SigningHarness::verify(SigningHarness::tamper($snap['json']), $snap['signature'], $key['public_key']));
    }

    public function test_seed_creates_publisher_package_and_signed_approved_release(): void
    {
        $root = SigningHarness::generate();
        $ids = RegistryFixtures::seed($this->db, $root);

        $package = $this->db->fetch('SELECT * FROM packages WHERE id = ?', [$ids['package_id']]);
        self::assertNotNull($package);
        self::assertSame('acme/midnight-theme', $package['package_uid']);
        self::assertSame('theme', $package['type']);
        self::assertSame('reviewed_declarative', $package['trust_class']);

        $release = $this->db->fetch('SELECT * FROM package_releases WHERE id = ?', [$ids['release_id']]);
        self::assertNotNull($release);
        self::assertSame('1.0.0', $release['version']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $release['digest']);
        self::assertSame($root->keyId(), $release['signed_key_id']);
        self::assertSame('approved', $release['review_status']);
        self::assertJson((string) $release['manifest_json']);
        self::assertNotSame('', (string) $release['signature']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/RegistryFixturesTest.php`
Expected: **Error** — `Class "Tests\Support\Phase5\RegistryFixtures" not found`.
(The test DB must be reachable — `docker start rb-mariadb` / the `forum-software-db-1` container per `PHASE_5_STATUS.md` Operating note.)

- [ ] **Step 3: Write the fixture seeder**

Create `tests/Support/Phase5/RegistryFixtures.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support\Phase5;

use App\Core\Database;

/**
 * Foundation F6 — seeds the dev/test-only first registry over the inert 0049
 * tables: one dark registry (`is_enabled=0`), one ACTIVE ed25519 trust key
 * (PUBLIC bytes only), one publisher, one reviewed declarative theme package,
 * and one signed approved release minted by the given SigningHarness. Rows
 * live inside the caller's test transaction. Production trust roots are an
 * operator ceremony (docs/phase5/registry-signing-key-custody.md) — never
 * seeded by code.
 */
final class RegistryFixtures
{
    /** @return array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    public static function seed(Database $db, SigningHarness $root): array
    {
        $registryId = $db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 0)',
            ['rb-test', 'RB Test Registry', 'https://registry.invalid'],
        );

        $trustKeyId = $db->insert(
            'INSERT INTO registry_trust_keys (registry_id, key_id, algorithm, public_key, status, valid_from) VALUES (?, ?, ?, ?, \'active\', UTC_TIMESTAMP())',
            [$registryId, $root->keyId(), 'ed25519', $root->publicKey()],
        );

        $publisherId = $db->insert(
            'INSERT INTO package_publishers (publisher_uid, display_name, verified_at) VALUES (?, ?, UTC_TIMESTAMP())',
            ['acme', 'Acme Themes'],
        );

        $packageId = $db->insert(
            'INSERT INTO packages (package_uid, registry_id, publisher_id, name, type, trust_class) VALUES (?, ?, ?, ?, ?, ?)',
            ['acme/midnight-theme', $registryId, $publisherId, 'Midnight Theme', 'theme', 'reviewed_declarative'],
        );

        $release = $root->mintRelease();
        $releaseId = $db->insert(
            'INSERT INTO package_releases (package_id, version, digest, license, core_min, core_max, manifest_json, signature, signed_key_id, review_status, channel, published_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'approved\', \'stable\', UTC_TIMESTAMP())',
            [
                $packageId,
                $release['version'],
                $release['digest'],
                'MIT',
                '0.1.0',
                null,
                $release['manifest_json'],
                $release['signature'],
                $release['key_id'],
            ],
        );

        return [
            'registry_id' => (int) $registryId,
            'trust_key_id' => (int) $trustKeyId,
            'publisher_id' => (int) $publisherId,
            'package_id' => (int) $packageId,
            'release_id' => (int) $releaseId,
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Core/RegistryFixturesTest.php`
Expected: **OK (2 tests)**.

- [ ] **Step 5: Commit**

```bash
git add tests/Support/Phase5/RegistryFixtures.php tests/Integration/Core/RegistryFixturesTest.php
git commit -m "test(phase5): add dev/test registry + trust-root fixtures over 0049 (F6)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 5: F7 (part 1) — `ReauthGate`

**Files:**
- Create: `src/Security/ReauthGate.php`
- Test: `tests/Integration/Security/ReauthGateTest.php` (new directory — PSR-4 `Tests\Integration\Security\` maps automatically)

**Interfaces:**
- Consumes: `App\Security\PasswordHasher::verify(string, ?string): bool`, `App\Domain\User::passwordHash(): ?string`, `App\Core\ValidationException`.
- Produces (consumed by Task 6 and by P5-02/04/07-A/09/11/12/13 high-impact surfaces; passkey becomes a second factor here in Inc 7):
  - `ReauthGate::FACTOR_PASSWORD = 'password'`
  - `->requirePassword(User $actor, string $currentPassword, string $field = 'current_password', ?string $missingPasswordError = null): void` — throws `ValidationException([$field => …])`, messages byte-identical to today's.
  - `->verifyPassword(User $actor, string $currentPassword): bool` — for collect-errors flows (`AccountService::changePassword`).

The test is an integration test (not unit) because constructing a real `App\Domain\User` requires the seeding helpers (`makeUser` / `userEntity`); `PasswordHasher` runs at the cheap test cost set in `tests/bootstrap.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Security/ReauthGateTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\ValidationException;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use Tests\Support\TestCase;

/**
 * Foundation F7 — the unified present-factor reauthentication gate
 * (decision #26). One window/factor policy: the factor is presented with the
 * request itself (window zero — exactly the accepted behavior of the five
 * call sites it consolidates); Inc 7 adds passkey as a second factor.
 * Messages/field keys are pinned byte-identical to the pre-refactor strings.
 */
final class ReauthGateTest extends TestCase
{
    private function gate(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    public function test_correct_password_passes(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        $this->gate()->requirePassword($user, 'password123');
        self::assertTrue($this->gate()->verifyPassword($user, 'password123'));
    }

    public function test_wrong_password_throws_the_exact_legacy_message(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        try {
            $this->gate()->requirePassword($user, 'nope');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Your current password is incorrect.'], $e->errors);
        }
    }

    public function test_custom_field_key_is_honored(): void
    {
        $user = $this->userEntity($this->makeUser(['password' => 'password123']));
        try {
            $this->gate()->requirePassword($user, 'nope', 'admin_password');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('admin_password', $e->errors);
        }
    }

    public function test_account_without_password_uses_missing_password_message_when_given(): void
    {
        // OAuth-only account: password_hash NULL (the MfaService pre-check case).
        $row = $this->makeUser(['username' => 'oauthonly']);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$row['id']]);
        $user = $this->users()->findEntity((int) $row['id']);
        self::assertNotNull($user);

        try {
            $this->gate()->requirePassword($user, 'anything', 'current_password', 'Set a password before managing two-factor authentication.');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Set a password before managing two-factor authentication.'], $e->errors);
        }
    }

    public function test_account_without_password_falls_through_to_incorrect_when_no_message_given(): void
    {
        // The other four call sites have no null pre-check today:
        // PasswordHasher::verify(null hash) === false → the standard message.
        $row = $this->makeUser(['username' => 'oauthonly2']);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$row['id']]);
        $user = $this->users()->findEntity((int) $row['id']);
        self::assertNotNull($user);

        self::assertFalse($this->gate()->verifyPassword($user, 'anything'));
        try {
            $this->gate()->requirePassword($user, 'anything');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['current_password' => 'Your current password is incorrect.'], $e->errors);
        }
    }
}
```

> If `UserRepository::findEntity()` has a different name in the current tree, check `src/Repository/UserRepository.php` and use its real single-row entity accessor — CLAUDE.md documents `findEntity()` as the `User` factory.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Security/ReauthGateTest.php`
Expected: **Error** — `Class "App\Security\ReauthGate" not found`.

- [ ] **Step 3: Write the gate**

Create `src/Security/ReauthGate.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ValidationException;
use App\Domain\User;

/**
 * Unified recent-reauthentication gate for high-impact actions (Foundation
 * F7; PHASE_5_PLAN decision #26). Policy: ONE factor list and ONE window,
 * owned here. The current window is "present the factor with the request
 * itself" (window zero) — exactly the accepted behavior of the call sites
 * this consolidates (API-token mint, webhook register/rotate/delete, MFA
 * enroll/disable, account lifecycle, password change). A non-zero session
 * window would require session-scoped state and lands, if ever needed, with
 * the increment that needs it. Inc 7 (P5-11) adds FACTOR_PASSKEY beside
 * FACTOR_PASSWORD as a step-up alternative behind this same API.
 */
final class ReauthGate
{
    public const FACTOR_PASSWORD = 'password';

    public function __construct(private PasswordHasher $hasher)
    {
    }

    /**
     * Assert the actor just re-presented their password; throws the exact
     * legacy ValidationException on failure. $missingPasswordError, when
     * given, is thrown instead if the account has no password at all
     * (OAuth-only accounts — the MfaService pre-check).
     */
    public function requirePassword(
        User $actor,
        string $currentPassword,
        string $field = 'current_password',
        ?string $missingPasswordError = null,
    ): void {
        if ($missingPasswordError !== null && $actor->passwordHash() === null) {
            throw new ValidationException([$field => $missingPasswordError]);
        }
        if (!$this->hasher->verify($currentPassword, $actor->passwordHash())) {
            throw new ValidationException([$field => 'Your current password is incorrect.']);
        }
    }

    /** Boolean form for collect-errors flows (AccountService::changePassword). */
    public function verifyPassword(User $actor, string $currentPassword): bool
    {
        return $this->hasher->verify($currentPassword, $actor->passwordHash());
    }
}
```

> `ValidationException` lives in `App\Core` and exposes public `$errors` — confirm the constructor is `new ValidationException(array $errors)` as used at `src/Service/ApiTokenService.php:49`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Security/ReauthGateTest.php`
Expected: **OK (5 tests)**.

- [ ] **Step 5: Commit**

```bash
git add src/Security/ReauthGate.php tests/Integration/Security/ReauthGateTest.php
git commit -m "feat(phase5): add unified ReauthGate present-factor policy (F7)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 6: F7 (part 2) — route the five services through `ReauthGate`

**Files:**
- Modify: `src/Service/ApiTokenService.php`, `src/Service/WebhookService.php`, `src/Service/MfaService.php`, `src/Service/AccountLifecycleService.php`, `src/Service/AccountService.php`
- Modify: `src/Core/App.php` (add binding; update 5 service bindings)
- Modify: `tests/Integration/Core/AppAccountLifecycleTest.php`, `tests/Integration/Service/ApiTokenServiceTest.php`, `tests/Integration/Service/WebhookServiceTest.php` (×2 sites), `tests/Integration/Service/DomainWebhookProducerTest.php`, `tests/Integration/Api/ApiReadEndpointsTest.php`

**Interfaces:**
- Consumes: `ReauthGate` (Task 5).
- Produces: constructor changes — in **ApiTokenService / WebhookService / MfaService / AccountLifecycleService** the `private PasswordHasher $hasher` parameter becomes `private ReauthGate $reauth` **in the same position** (each used the hasher only for reauth verification); **AccountService** keeps `PasswordHasher $hasher` (it hashes new passwords) and gains `private ReauthGate $reauth` inserted directly after it.

This is a behavior-preserving refactor: the existing suites (`AppMfaTest`, `ApiTokenServiceTest`, `AdminApiTokenTest`, `WebhookServiceTest`, `AdminWebhookTest`, `AppAccountLifecycleTest`, `AppUserSettingsTest`, `AuthControllerTest`) are the red/green net — no new tests.

- [ ] **Step 1: Refactor `ApiTokenService`**

In `src/Service/ApiTokenService.php`: replace the import `use App\Security\PasswordHasher;` with `use App\Security\ReauthGate;`; change the constructor parameter `private PasswordHasher $hasher,` → `private ReauthGate $reauth,`; replace the mint-time check (lines ~48–50):

```php
        if (!$this->hasher->verify($currentPassword, $admin->passwordHash())) {
            throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
        }
```

with:

```php
        $this->reauth->requirePassword($admin, $currentPassword);
```

- [ ] **Step 2: Refactor `WebhookService`**

Same import/parameter swap (`PasswordHasher $hasher` → `ReauthGate $reauth`). Replace the private helper body:

```php
    private function assertPassword(User $admin, string $password): void
    {
        $this->reauth->requirePassword($admin, $password);
    }
```

- [ ] **Step 3: Refactor `MfaService`**

Same import/parameter swap. Replace the private helper body (keeps the MFA-specific no-password copy):

```php
    private function requirePassword(User $user, string $currentPassword): void
    {
        $this->reauth->requirePassword(
            $user,
            $currentPassword,
            'current_password',
            'Set a password before managing two-factor authentication.',
        );
    }
```

- [ ] **Step 4: Refactor `AccountLifecycleService`**

Same import/parameter swap (position 7, before `private ?LastOwnerGuard $ownerGuard = null`). Replace the private helper body:

```php
    private function assertPassword(User $user, string $currentPassword): void
    {
        $this->reauth->requirePassword($user, $currentPassword);
    }
```

- [ ] **Step 5: Refactor `AccountService`**

Keep `use App\Security\PasswordHasher;` and add `use App\Security\ReauthGate;`. Insert the new parameter directly after the hasher:

```php
    public function __construct(
        private Database $db,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private Config $config,
        private ?UserPreferenceRepository $prefs = null,
        private ?FeatureFlags $flags = null,
        private ?UserProfileFieldRepository $profileFields = null,
    ) {
    }
```

In `changePassword()` (line ~197) replace:

```php
        if (!$this->hasher->verify($current, $user->passwordHash())) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
```

with:

```php
        if (!$this->reauth->verifyPassword($user, $current)) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
```

(`setInitialPassword()` and the two `$this->hasher->hash($new)` calls are untouched.)

- [ ] **Step 6: Wire the container**

In `src/Core/App.php`: add `use App\Security\ReauthGate;` to the imports. Directly after the `PasswordHasher` binding (line ~617):

```php
        $c->bind(ReauthGate::class, fn (Container $c) => new ReauthGate($c->get(PasswordHasher::class)));
```

Then update the five bindings:
- `ApiTokenService` binding (~line 639): `$c->get(PasswordHasher::class),` → `$c->get(ReauthGate::class),`
- `WebhookService` binding (~line 666): same swap.
- `AccountService` binding (~line 1028): insert `$c->get(ReauthGate::class),` on a new line directly after `$c->get(PasswordHasher::class),`.
- `AccountLifecycleService` binding (~line 1045): `$c->get(PasswordHasher::class),` → `$c->get(ReauthGate::class),`
- `MfaService` binding (~line 1055): `$c->get(PasswordHasher::class),` → `$c->get(ReauthGate::class),`

- [ ] **Step 7: Update the six direct-construction test sites**

Each site adds `use App\Security\ReauthGate;` and swaps the constructor argument `new PasswordHasher(),` → `new ReauthGate(new PasswordHasher()),`:

- `tests/Integration/Core/AppAccountLifecycleTest.php` (~line 27, `lifecycleService()`)
- `tests/Integration/Service/ApiTokenServiceTest.php` (~line 26, `service()`)
- `tests/Integration/Service/WebhookServiceTest.php` (~line 30 `service()` **and** ~line 112 `test_dispatch_is_noop_when_dark`)
- `tests/Integration/Service/DomainWebhookProducerTest.php` (~line 292, `webhookService()` — the `new PasswordHasher(),` argument inside the `WebhookService` construction)
- `tests/Integration/Api/ApiReadEndpointsTest.php` (~line 24, `mintToken()`)

Verify no site is missed: `grep -rn "new PasswordHasher()" tests/ | grep -v bootstrap` — every remaining hit must be a place where a hasher (not a reauth check) is genuinely needed, e.g. inside `new ReauthGate(new PasswordHasher())`.

- [ ] **Step 8: Run the focused suites, then the full suite**

Run:
```bash
vendor/bin/phpunit tests/Integration/Service/ApiTokenServiceTest.php tests/Integration/Api/ApiReadEndpointsTest.php tests/Integration/Api/AdminApiTokenTest.php \
  tests/Integration/Service/WebhookServiceTest.php tests/Integration/Admin/AdminWebhookTest.php tests/Integration/Service/DomainWebhookProducerTest.php \
  tests/Integration/Core/AppAccountLifecycleTest.php tests/Integration/Core/AppMfaTest.php tests/Integration/Core/AppUserSettingsTest.php
composer test
```
Expected: all green; full suite ≥ 857 tests + the new ones, 0 failures. (If `AppMfaTest` lives at a different path, locate it with `ls tests/Integration/Core/ | grep -i mfa`.)

- [ ] **Step 9: Commit**

```bash
git add src/Service/ApiTokenService.php src/Service/WebhookService.php src/Service/MfaService.php src/Service/AccountLifecycleService.php src/Service/AccountService.php src/Core/App.php tests/
git commit -m "refactor(phase5): consolidate scattered reauth checks behind ReauthGate (F7)

Behavior-preserving: messages, field keys, and exception types are
byte-identical; the five services now share one factor/window policy.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 7: F8 (part 1) — `LogRedactor`

**Files:**
- Create: `src/Support/LogRedactor.php`
- Test: `tests/Unit/Support/LogRedactorTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `LogRedactor::REDACTED = '[redacted]'`; `LogRedactor::redact(array $fields): array` — recursive; redacts by sensitive **key** (substring list + boundary list for short ambiguous keys) and by sensitive **value shape** (`rbt_*` API tokens, `svcsec_*` secret references, `Bearer …` header values). Over-redaction is acceptable; leakage is not. Task 8's `Telemetry` and every later workstream's log lines pass through this.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/LogRedactorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F8 — secrets/challenges/tokens/PII/private content never reach
 * a log line (PHASE_5_PLAN §10.2 "Security-sensitive evidence…", §11.4).
 */
final class LogRedactorTest extends TestCase
{
    public function test_sensitive_keys_are_redacted(): void
    {
        $out = LogRedactor::redact([
            'password' => 'hunter2',
            'current_password' => 'hunter2',
            'api_token' => 'abc',
            'client_secret' => 'abc',
            'challenge' => 'abc',
            'credential_id' => 'abc',
            'authorization' => 'Bearer abc',
            'cookie' => 'rb_session=abc',
            'signature' => 'abc',
            'private_key' => 'abc',
            'recovery_code' => 'abc',
            'totp_secret' => 'abc',
            'email' => 'a@b.test',
            'body' => 'private post text',
            'content' => 'private post text',
            'ciphertext' => 'abc',
        ]);
        foreach ($out as $key => $value) {
            self::assertSame(LogRedactor::REDACTED, $value, $key);
        }
    }

    public function test_short_ambiguous_keys_use_boundary_matching(): void
    {
        $out = LogRedactor::redact([
            'ip' => '203.0.113.9',
            'user_ip' => '203.0.113.9',
            'ip_hash' => 'abc',
            'tag' => 'gcm-tag-bytes',
            'otp' => '123456',
            // …but ordinary words containing those fragments survive:
            'description' => 'ship it',
            'tags' => ['help', 'question'],
            'zip' => '49001',
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['ip']);
        self::assertSame(LogRedactor::REDACTED, $out['user_ip']);
        self::assertSame(LogRedactor::REDACTED, $out['ip_hash']);
        self::assertSame(LogRedactor::REDACTED, $out['tag']);
        self::assertSame(LogRedactor::REDACTED, $out['otp']);
        self::assertSame('ship it', $out['description']);
        self::assertSame(['help', 'question'], $out['tags']);
        self::assertSame('49001', $out['zip']);
    }

    public function test_sensitive_value_shapes_are_redacted_under_innocent_keys(): void
    {
        $out = LogRedactor::redact([
            'note' => 'rbt_' . str_repeat('a1', 24),
            'ref' => 'svcsec_' . str_repeat('b2', 16),
            'header' => 'Bearer rbt_deadbeef',
            'ok' => 'plain value',
            'digest' => hash('sha256', 'x'), // digests are provenance, not secrets
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['note']);
        self::assertSame(LogRedactor::REDACTED, $out['ref']);
        self::assertSame(LogRedactor::REDACTED, $out['header']);
        self::assertSame('plain value', $out['ok']);
        self::assertSame(hash('sha256', 'x'), $out['digest']);
    }

    public function test_redaction_is_recursive_and_type_safe(): void
    {
        $out = LogRedactor::redact([
            'meta' => ['inner' => ['password' => 'x', 'thread_id' => 42]],
            'token' => 12345, // non-string under a sensitive key still redacts
            'count' => 7,
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['meta']['inner']['password']);
        self::assertSame(42, $out['meta']['inner']['thread_id']);
        self::assertSame(LogRedactor::REDACTED, $out['token']);
        self::assertSame(7, $out['count']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Support/LogRedactorTest.php`
Expected: **Error** — `Class "App\Support\LogRedactor" not found`.

- [ ] **Step 3: Write the redactor**

Create `src/Support/LogRedactor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Structured-log field redaction (Foundation F8). Every telemetry/log context
 * array passes through redact() so secrets, credentials, challenges, tokens,
 * PII, and private content can never leak into ordinary logs
 * (PHASE_5_PLAN §10.2/§11.4). Matching is by sensitive KEY — a substring
 * list, plus a boundary list for short fragments that occur inside ordinary
 * words — and by sensitive VALUE shape (known secret prefixes). Digests and
 * numeric IDs are provenance, not secrets, and pass through. Over-redaction
 * is acceptable; leakage is not.
 */
final class LogRedactor
{
    public const REDACTED = '[redacted]';

    /** Case-insensitive substring match anywhere in the key. */
    private const SENSITIVE_KEY_SUBSTRINGS = [
        'password', 'passphrase', 'secret', 'token', 'challenge', 'credential',
        'authorization', 'cookie', 'signature', 'private', 'api_key',
        'recovery', 'totp', 'nonce', 'email', 'ciphertext', 'body', 'content',
    ];

    /** Short/ambiguous fragments: exact key, `x_*`, or `*_x` only. */
    private const SENSITIVE_KEY_BOUNDARY = ['ip', 'tag', 'otp'];

    /** Value shapes that redact regardless of key. */
    private const SENSITIVE_VALUE_PATTERNS = [
        '/^rbt_[0-9a-f]+$/i',    // API bearer tokens (ApiTokenService)
        '/^svcsec_[0-9a-f]+$/i', // service-secret references (SecretVault)
        '/^Bearer\s+\S+/i',      // raw Authorization header values
    ];

    /**
     * @param array<array-key,mixed> $fields
     * @return array<array-key,mixed>
     */
    public static function redact(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $out[$key] = self::REDACTED;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::redact($value);
                continue;
            }
            if (is_string($value) && self::isSensitiveValue($value)) {
                $out[$key] = self::REDACTED;
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEY_SUBSTRINGS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        foreach (self::SENSITIVE_KEY_BOUNDARY as $frag) {
            if ($lower === $frag || str_starts_with($lower, $frag . '_') || str_ends_with($lower, '_' . $frag)) {
                return true;
            }
        }

        return false;
    }

    private static function isSensitiveValue(string $value): bool
    {
        foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Support/LogRedactorTest.php`
Expected: **OK (4 tests)**.

- [ ] **Step 5: Commit**

```bash
git add src/Support/LogRedactor.php tests/Unit/Support/LogRedactorTest.php
git commit -m "feat(phase5): add LogRedactor key/value-shape redaction (F8)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 8: F8 (part 2) — `Telemetry` + config gate + kernel emission

**Files:**
- Create: `src/Core/Telemetry.php`
- Modify: `config/config.php` (add `telemetry` section after the `security` block)
- Modify: `src/Core/App.php` (container binding + one `emit` in `handle()`)
- Test: `tests/Unit/Core/TelemetryRedactionTest.php`

**Interfaces:**
- Consumes: `App\Core\Config::get()`, `App\Support\LogRedactor` (Task 7).
- Produces: `Telemetry::enabled(): bool`; `Telemetry::correlationId(): string` (lazy, stable per instance — one instance per request container); `Telemetry::emit(string $event, array $context = []): void` (no-op unless `telemetry.enabled`; JSON line `{"ts","cid","event",…redacted context}` to an injectable `$sink`, default `error_log`). Workstreams **emit at build time; Increment 10 only verifies** (program plan §B-F8).

Note on integration coverage: the kernel emission ships **dark** (`TELEMETRY_ENABLED` defaults `false`), so the entire existing suite exercises the no-op path on every request; the enabled path is covered by the unit test with a capture sink (the default `error_log` sink would write to stderr during tests — same reason `FirstPartyHookRegistry` takes an injectable logger).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Core/TelemetryRedactionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Core\Telemetry;
use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F8 — config-gated correlation-ID telemetry whose emitted lines
 * can never contain secrets/challenges/tokens/PII/private content
 * (PHASE_5_PLAN §11.4; program plan §B-F8).
 */
final class TelemetryRedactionTest extends TestCase
{
    /** @param bool $enabled @param list<string> $captured */
    private function telemetry(bool $enabled, array &$captured): Telemetry
    {
        return new Telemetry(
            new Config(['telemetry' => ['enabled' => $enabled]]),
            function (string $line) use (&$captured): void {
                $captured[] = $line;
            },
        );
    }

    public function test_disabled_telemetry_emits_nothing(): void
    {
        $captured = [];
        $t = $this->telemetry(false, $captured);
        self::assertFalse($t->enabled());
        $t->emit('http.request', ['path' => '/']);
        self::assertSame([], $captured);
    }

    public function test_enabled_telemetry_emits_structured_json_with_correlation_id(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $t->emit('http.request', ['method' => 'GET', 'path' => '/', 'status' => 200]);

        self::assertCount(1, $captured);
        $doc = json_decode($captured[0], true);
        self::assertIsArray($doc);
        self::assertSame('http.request', $doc['event']);
        self::assertSame($t->correlationId(), $doc['cid']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $doc['ts']);
        self::assertSame(200, $doc['status']);
    }

    public function test_emitted_lines_are_redacted(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $secret = 'rbt_' . str_repeat('ab', 24);
        $t->emit('api.token', ['note' => $secret, 'password' => 'hunter2', 'thread_id' => 42]);

        self::assertCount(1, $captured);
        self::assertStringNotContainsString($secret, $captured[0]);
        self::assertStringNotContainsString('hunter2', $captured[0]);
        self::assertStringContainsString(LogRedactor::REDACTED, $captured[0]);
        self::assertSame(42, json_decode($captured[0], true)['thread_id']);
    }

    public function test_correlation_id_is_stable_per_instance_and_distinct_across_instances(): void
    {
        $captured = [];
        $a = $this->telemetry(true, $captured);
        $b = $this->telemetry(true, $captured);

        self::assertSame($a->correlationId(), $a->correlationId());
        self::assertNotSame($a->correlationId(), $b->correlationId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $a->correlationId());
    }

    public function test_reserved_envelope_keys_win_over_context_keys(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $t->emit('x', ['event' => 'spoofed', 'cid' => 'spoofed', 'ts' => 'spoofed']);
        $doc = json_decode($captured[0], true);
        self::assertSame('x', $doc['event']);
        self::assertSame($t->correlationId(), $doc['cid']);
        self::assertNotSame('spoofed', $doc['ts']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Core/TelemetryRedactionTest.php`
Expected: **Error** — `Class "App\Core\Telemetry" not found`.

- [ ] **Step 3: Write `Telemetry`, the config section, and the kernel wiring**

Create `src/Core/Telemetry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\LogRedactor;

/**
 * Config-gated structured telemetry with per-request correlation IDs
 * (Foundation F8; PHASE_5_PLAN §11.4). Dark by default: emit() is a no-op
 * unless `telemetry.enabled` (TELEMETRY_ENABLED), so the seam ships with no
 * behavior change. Every context passes through LogRedactor before encoding —
 * secrets/challenges/tokens/PII/private content never reach a log line. One
 * instance is bound per request container, so correlationId() is stable
 * within a request and distinct across requests; workers construct their own
 * instance per run. Workstreams emit at build time; Inc 10 (P5-16) verifies.
 */
final class Telemetry
{
    private ?string $correlationId = null;

    /** @var (callable(string):void)|null */
    private $sink;

    public function __construct(private Config $config, ?callable $sink = null)
    {
        $this->sink = $sink;
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('telemetry.enabled', false);
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= bin2hex(random_bytes(8));
    }

    /** @param array<string,mixed> $context */
    public function emit(string $event, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }
        $line = json_encode([
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'cid' => $this->correlationId(),
            'event' => $event,
        ] + LogRedactor::redact($context), JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        if ($this->sink !== null) {
            ($this->sink)($line);

            return;
        }
        error_log('[RetroBoards telemetry] ' . $line);
    }
}
```

In `config/config.php`, directly after the `'security' => [ … ],` block, add:

```php
    'telemetry' => [
        // Foundation F8: structured correlation-ID telemetry (PHASE_5_PLAN
        // §11.4). Dark by default; emitted context is always redacted
        // (App\Support\LogRedactor) — secrets/PII never reach a log line.
        'enabled' => Env::bool('TELEMETRY_ENABLED', false),
    ],
```

In `src/Core/App.php`:

1. In `buildContainer()`, next to the other core bindings (e.g. directly after the `PasswordHasher`/`ReauthGate` bindings), add:

```php
        $c->bind(Telemetry::class, fn () => new Telemetry($config));
```

(`Telemetry` is in `App\Core` — no import needed. `$config` is the local variable the surrounding bindings already close over — e.g. the `ApiTokenService` binding passes bare `$config`.)

2. In `handle()`, immediately after `$response = $this->process($container, $request);`, add:

```php
        $container->get(Telemetry::class)->emit('http.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->status(),
        ]);
```

- [ ] **Step 4: Run the test + the kernel smoke**

Run: `vendor/bin/phpunit tests/Unit/Core/TelemetryRedactionTest.php tests/Integration/Core/AppFeatureFlagTest.php`
Expected: **OK** — the unit tests pass and the kernel still serves with telemetry dark (the emit call no-ops on every existing kernel test).

- [ ] **Step 5: Run the full suite**

Run: `composer test`
Expected: green (proves the `handle()` emission is a true no-op under default config).

- [ ] **Step 6: Commit**

```bash
git add src/Core/Telemetry.php src/Core/App.php config/config.php tests/Unit/Core/TelemetryRedactionTest.php
git commit -m "feat(phase5): add config-gated Telemetry with redacted correlation-ID lines (F8)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 9: F10 (part 1) — requirement ledger + `Phase5EvidenceMapTest`

**Files:**
- Create: `docs/phase5/requirement-ledger.json`
- Test: `tests/Unit/Core/Phase5EvidenceMapTest.php`

**Interfaces:**
- Consumes: `PHASE_5_PLAN.md` §11.1 (R0–R5 states) + §14 Gate A checklist (the 23 `- [ ]` items — the "Gate A DoD items"), `PHASE_5_STATUS.md` §"Requirement-ledger snapshot" (initial states), the 14 Phase 5 flags.
- Produces: the machine-checkable ledger every later increment updates when it lands (bump `state`, append `evidence` paths), and the test that fails CI when a state overclaims (R3+ without existing evidence files), a Gate A DoD item goes missing, or a flag lacks a rollback path. Task 12 bumps F2/F4/F6/F7/F8/F10/F11 to R3 here.

**Ledger schema** (`version`, `updated`, `requirements[]`, `flags{}`): each requirement = `{id, gate: "A"|"B", workstream, title, state: R0–R5, evidence: [repo-relative paths], notes?}`. Rule: `state >= R3` ⇒ `evidence` non-empty; **every listed path must exist** regardless of state.

- [ ] **Step 1: Verify the §14 item count and the evidence paths the initial ledger will cite**

Run:
```bash
grep -n '^- \[ \]' PHASE_5_PLAN.md | sed -n '1,23p'   # Gate A checklist — expect 23 items before the "Gate B and phase close" heading
for p in tests/Unit/Core/MigrationLedgerTest.php src/Security/CapabilityCatalog.php tests/Unit/Security/CapabilityCatalogTest.php \
  database/migrations/0066_phase5_seed_capabilities_owners.php src/Security/LastOwnerGuard.php docs/evidence/phase5/foundation-f3-f5.md \
  src/Support/Phase5Budgets.php docs/evidence/phase5/performance-budgets.md tests/Unit/Security/TotpTest.php \
  tests/Integration/Service/SecretVaultTest.php tests/Integration/Service/ApiTokenServiceTest.php \
  tests/Integration/Service/WebhookServiceTest.php tests/Unit/Hook/FirstPartyHookRegistryTest.php \
  docs/adr/0003-phase-4-closeout-deferrals.md docs/adr/0004-phase-5-entry-and-carryover.md docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md \
  docs/phase5/capability-taxonomy.md docs/phase5/registry-signing-key-custody.md docs/phase5/canonical-origin-and-rp-id.md; do
  [ -f "$p" ] && echo "OK  $p" || echo "MISSING $p"; done
```
Expected: 23 checklist lines; every path `OK`. If the Gate A checklist count differs, adjust `GATE_A_DOD_IDS` in the test and the JSON to the real count — the ids must mirror §14 order exactly.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Core/Phase5EvidenceMapTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Foundation F10 — the machine-checkable R0–R5 requirement ledger
 * (PHASE_5_PLAN §11.1). Gate-pass evaluability: every Gate A DoD item (§14)
 * exists in the ledger; any state ≥ R3 must link evidence that exists on
 * this commit; every Phase 5 flag has a documented rollback path. Analyzer
 * split mirrors MigrationLedgerTest so the detection logic itself is proven.
 */
final class Phase5EvidenceMapTest extends TestCase
{
    private const STATES = ['R0', 'R1', 'R2', 'R3', 'R4', 'R5'];

    /** §14 "Gate A" checklist, in order (verified 23 items). */
    private const GATE_A_DOD_IDS = [
        'GA-DOD-01', 'GA-DOD-02', 'GA-DOD-03', 'GA-DOD-04', 'GA-DOD-05',
        'GA-DOD-06', 'GA-DOD-07', 'GA-DOD-08', 'GA-DOD-09', 'GA-DOD-10',
        'GA-DOD-11', 'GA-DOD-12', 'GA-DOD-13', 'GA-DOD-14', 'GA-DOD-15',
        'GA-DOD-16', 'GA-DOD-17', 'GA-DOD-18', 'GA-DOD-19', 'GA-DOD-20',
        'GA-DOD-21', 'GA-DOD-22', 'GA-DOD-23',
    ];

    /** Must stay in lock-step with FeatureFlags::DEFAULTS' Phase 5 block (AppFeatureFlagTest pins those). */
    private const PHASE5_FLAGS = [
        'package_registry', 'package_themes', 'capabilities', 'passkeys',
        'provider_registry', 'invitations', 'service_secrets', 'api_tokens',
        'webhooks', 'first_party_hooks',
        'server_extensions', 'governance', 'service_principals', 'verified_links',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return array<string,mixed> */
    private static function loadLedger(): array
    {
        $path = self::root() . '/docs/phase5/requirement-ledger.json';
        self::assertFileExists($path);
        $doc = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($doc, 'ledger must be valid JSON');

        return $doc;
    }

    /**
     * Pure validator over a decoded ledger. @param array<string,mixed> $doc
     * @return list<string> human-readable errors (empty = valid)
     */
    private static function validate(array $doc, string $root): array
    {
        $errors = [];
        $requirements = $doc['requirements'] ?? null;
        if (!is_array($requirements) || $requirements === []) {
            return ['requirements missing or empty'];
        }

        $seen = [];
        foreach ($requirements as $i => $req) {
            $id = is_array($req) ? ($req['id'] ?? "#$i") : "#$i";
            foreach (['id', 'gate', 'workstream', 'title', 'state'] as $field) {
                if (!is_string($req[$field] ?? null) || trim((string) ($req[$field] ?? '')) === '') {
                    $errors[] = "$id: missing/empty $field";
                }
            }
            if (isset($seen[$id])) {
                $errors[] = "$id: duplicate id";
            }
            $seen[$id] = true;

            $state = (string) ($req['state'] ?? '');
            if (!in_array($state, self::STATES, true)) {
                $errors[] = "$id: unknown state '$state'";
            }
            if (!in_array($req['gate'] ?? '', ['A', 'B'], true)) {
                $errors[] = "$id: gate must be A or B";
            }

            $evidence = $req['evidence'] ?? [];
            if (!is_array($evidence)) {
                $errors[] = "$id: evidence must be an array";
                $evidence = [];
            }
            foreach ($evidence as $path) {
                if (!is_string($path) || !is_file($root . '/' . $path)) {
                    $errors[] = "$id: evidence path does not exist: " . (is_string($path) ? $path : gettype($path));
                }
            }
            if (in_array($state, ['R3', 'R4', 'R5'], true) && $evidence === []) {
                $errors[] = "$id: state $state requires at least one evidence link";
            }
        }

        foreach (self::GATE_A_DOD_IDS as $dodId) {
            if (!isset($seen[$dodId])) {
                $errors[] = "missing Gate A DoD item $dodId";
            }
        }
        foreach (array_keys($seen) as $id) {
            if (str_starts_with((string) $id, 'GA-DOD-') && !in_array($id, self::GATE_A_DOD_IDS, true)) {
                $errors[] = "$id: not a known §14 Gate A item";
            }
        }

        $flags = $doc['flags'] ?? null;
        if (!is_array($flags)) {
            $errors[] = 'flags map missing';
        } else {
            foreach (self::PHASE5_FLAGS as $flag) {
                if (!is_string($flags[$flag]['rollback'] ?? null) || trim((string) ($flags[$flag]['rollback'] ?? '')) === '') {
                    $errors[] = "flag $flag: missing rollback path";
                }
            }
            foreach (array_keys($flags) as $flag) {
                if (!in_array($flag, self::PHASE5_FLAGS, true)) {
                    $errors[] = "flag $flag: not a declared Phase 5 flag";
                }
            }
        }

        return $errors;
    }

    public function test_ledger_is_valid_and_every_claim_is_evidenced(): void
    {
        $errors = self::validate(self::loadLedger(), self::root());
        self::assertSame([], $errors, "requirement-ledger.json invalid:\n- " . implode("\n- ", $errors));
    }

    public function test_validator_flags_overclaimed_state_without_evidence(): void
    {
        $doc = self::minimalValidDoc();
        $doc['requirements'][0]['state'] = 'R3';
        $doc['requirements'][0]['evidence'] = [];
        self::assertContains('GA-DOD-01: state R3 requires at least one evidence link', self::validate($doc, self::root()));
    }

    public function test_validator_flags_missing_dod_item_unknown_state_and_dead_evidence(): void
    {
        $doc = self::minimalValidDoc();
        unset($doc['requirements'][22]); // drop GA-DOD-23
        $doc['requirements'] = array_values($doc['requirements']);
        $doc['requirements'][0]['state'] = 'R9';
        $doc['requirements'][1]['evidence'] = ['no/such/file.md'];
        $errors = self::validate($doc, self::root());
        self::assertContains('missing Gate A DoD item GA-DOD-23', $errors);
        self::assertContains("GA-DOD-01: unknown state 'R9'", $errors);
        self::assertContains('GA-DOD-02: evidence path does not exist: no/such/file.md', $errors);
    }

    public function test_validator_flags_missing_flag_rollback(): void
    {
        $doc = self::minimalValidDoc();
        unset($doc['flags']['passkeys']);
        self::assertContains('flag passkeys: missing rollback path', self::validate($doc, self::root()));
    }

    /** @return array<string,mixed> a synthetic doc that passes validation */
    private static function minimalValidDoc(): array
    {
        $requirements = [];
        foreach (self::GATE_A_DOD_IDS as $id) {
            $requirements[] = ['id' => $id, 'gate' => 'A', 'workstream' => 'P5-16', 'title' => 't', 'state' => 'R1', 'evidence' => []];
        }
        $flags = [];
        foreach (self::PHASE5_FLAGS as $flag) {
            $flags[$flag] = ['gate' => 'A', 'rollback' => 'features override → dark'];
        }

        return ['version' => 1, 'updated' => '2026-07-01', 'requirements' => $requirements, 'flags' => $flags];
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php`
Expected: **FAIL** — `assertFileExists` for `docs/phase5/requirement-ledger.json`.

- [ ] **Step 4: Write the initial ledger**

Create `docs/phase5/requirement-ledger.json`. States mirror `PHASE_5_STATUS.md` §"Requirement-ledger snapshot" (conservative — never overclaim; Task 12 bumps this plan's own items to R3):

```json
{
  "version": 1,
  "updated": "2026-07-01",
  "notes": "Machine-checkable R0-R5 ledger (PHASE_5_PLAN §11.1); enforced by tests/Unit/Core/Phase5EvidenceMapTest.php. GA-DOD-* rows mirror the §14 Gate A checklist in order. Bump a state only in the commit that lands its evidence.",
  "requirements": [
    { "id": "GA-DOD-01", "gate": "A", "workstream": "P5-00", "title": "Phase 4 acceptance or explicit deferrals recorded", "state": "R5", "evidence": ["docs/adr/0003-phase-4-closeout-deferrals.md", "docs/adr/0004-phase-5-entry-and-carryover.md"] },
    { "id": "GA-DOD-02", "gate": "A", "workstream": "P5-00", "title": "Carryover ledger, trust models, taxonomy, protected capabilities, RP ID, provider/invitation decisions, budgets approved", "state": "R5", "evidence": ["docs/adr/0004-phase-5-entry-and-carryover.md", "docs/adr/0012-phase-5-gate-a-entry-gate-artifacts.md", "docs/phase5/capability-taxonomy.md", "docs/phase5/registry-signing-key-custody.md", "docs/phase5/canonical-origin-and-rp-id.md"] },
    { "id": "GA-DOD-03", "gate": "A", "workstream": "P5-00", "title": "Schema reconciled with SCHEMA.md; all Gate A migrations pass clean-install/upgrade/rollback/backup", "state": "R1", "evidence": [], "notes": "0049-0067 landed + verify:upgrade PASS; Gate A migration set (0068-0075) not yet authored" },
    { "id": "GA-DOD-04", "gate": "A", "workstream": "P5-01", "title": "Registry identity, signed/expiring snapshots, trust roots, rotation/revocation, digests, pinning, advisories, offline behavior", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-05", "gate": "A", "workstream": "P5-02", "title": "Manifest v2, package types, permission/data/network/job declarations, lifecycle, risk summary", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-06", "gate": "A", "workstream": "P5-02", "title": "Tampered/stale/incompatible/source-switched/revoked/unreviewed packages fail closed", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-07", "gate": "A", "workstream": "P5-03", "title": "Declarative themes: verification, asset scan, preview isolation, contrast/a11y, CSP/cache, safe mode, rollback", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-08", "gate": "A", "workstream": "P5-04", "title": "Declarative/remote integration install, consent, pin/update/rollback, disable, export/uninstall, secrets, scope, outage", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-09", "gate": "A", "workstream": "P5-07", "title": "Exact-digest maintainer review, automated evidence, manual approval, publication, advisory, emergency disable", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-10", "gate": "A", "workstream": "P5-08", "title": "Protected built-in roles/capabilities seeded; old-vs-new resolver parity complete", "state": "R1", "evidence": [], "notes": "catalogue+seed landed (F3); resolver+parity is Increment 1/6" },
    { "id": "GA-DOD-11", "gate": "A", "workstream": "P5-09", "title": "Custom role creation, scope, grantor authority, expiry, cache invalidation, audit, simulator, fallback", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-12", "gate": "A", "workstream": "P5-10", "title": "Protected owner/last-admin and recent-reauthentication safeguards", "state": "R1", "evidence": [], "notes": "LastOwnerGuard (F5) + ReauthGate (F7) landed; four owner-loss paths + high-impact wiring land in Inc 6-9" },
    { "id": "GA-DOD-13", "gate": "A", "workstream": "P5-11", "title": "Passkey registration/login/list/remove/step-up/fallback/recovery scenarios on supported browsers", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-14", "gate": "A", "workstream": "P5-12", "title": "Provider-registry migration preserves identities; generic OIDC negative tests pass", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-15", "gate": "A", "workstream": "P5-12", "title": "Selected additional provider works through the normalized identity contract", "state": "R0", "evidence": [], "notes": "A2 provider not yet named — owner decision required before P5-12 acceptance" },
    { "id": "GA-DOD-16", "gate": "A", "workstream": "P5-13", "title": "Invitation create/revoke/redeem/bind/expire/use-limit/abuse/no-privilege-escalation tests", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-17", "gate": "A", "workstream": "P5-16", "title": "Accessibility, responsive, keyboard, screen-reader, no-JS evidence for Gate A surfaces", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-18", "gate": "A", "workstream": "P5-16", "title": "Registry/signature/install/theme/role/passkey/provider/invitation budgets pass on production-like fixtures", "state": "R1", "evidence": [], "notes": "targets approved (D11) + resolver baseline measured (F9); nine budgets PENDING their increments" },
    { "id": "GA-DOD-19", "gate": "A", "workstream": "P5-16", "title": "Rotation, revoke/rollback, outage, safe mode, resolver fallback, owner/passkey recovery, provider disable, invitation pause, backup/restore runbooks rehearsed", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-20", "gate": "A", "workstream": "P5-16", "title": "Full Phase 1-4 regression and route-permission matrix green with all public packages disabled", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-21", "gate": "A", "workstream": "P5-16", "title": "No critical/high defects remain", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-22", "gate": "A", "workstream": "P5-16", "title": "README, changelog, schema, capability catalogue, review policy, runbooks, evidence index updated", "state": "R1", "evidence": [] },
    { "id": "GA-DOD-23", "gate": "A", "workstream": "P5-16", "title": "Gate A product-owner acceptance recorded (ADR 0013)", "state": "R1", "evidence": [] },
    { "id": "F1", "gate": "A", "workstream": "Foundation", "title": "Migration-number ledger guard", "state": "R3", "evidence": ["tests/Unit/Core/MigrationLedgerTest.php"] },
    { "id": "F2", "gate": "A", "workstream": "Foundation", "title": "App::CORE_VERSION + CoreVersion range primitive", "state": "R1", "evidence": [] },
    { "id": "F3", "gate": "A", "workstream": "Foundation", "title": "Capability catalogue (code + 0066 seed + coverage)", "state": "R3", "evidence": ["src/Security/CapabilityCatalog.php", "tests/Unit/Security/CapabilityCatalogTest.php", "database/migrations/0066_phase5_seed_capabilities_owners.php"] },
    { "id": "F4", "gate": "A", "workstream": "Foundation", "title": "DataClasses catalogue + consent vocabulary", "state": "R1", "evidence": [] },
    { "id": "F5", "gate": "A", "workstream": "Foundation", "title": "protected_owners seed + LastOwnerGuard", "state": "R3", "evidence": ["src/Security/LastOwnerGuard.php", "docs/evidence/phase5/foundation-f3-f5.md"], "notes": "wired on account deactivate/delete; role-revoke (Inc6), passkey-removal (Inc7), provider-unlink (Inc8), invitations (Inc9) pending" },
    { "id": "F6", "gate": "A", "workstream": "Foundation", "title": "Ed25519 signed-artifact harness + dev/test registry fixtures", "state": "R1", "evidence": [] },
    { "id": "F7", "gate": "A", "workstream": "Foundation", "title": "Unified ReauthGate present-factor policy", "state": "R1", "evidence": [] },
    { "id": "F8", "gate": "A", "workstream": "Foundation", "title": "Telemetry + LogRedactor seam", "state": "R1", "evidence": [] },
    { "id": "F9", "gate": "A", "workstream": "Foundation", "title": "Fixture + baselines + budget harness (A3)", "state": "R3", "evidence": ["src/Support/Phase5Budgets.php", "docs/evidence/phase5/performance-budgets.md"] },
    { "id": "F10", "gate": "A", "workstream": "Foundation", "title": "Requirement ledger + evidence map + all-flags-off regression", "state": "R1", "evidence": [] },
    { "id": "F11", "gate": "A", "workstream": "Foundation", "title": "Threat-model dossiers + negative-fixture index", "state": "R1", "evidence": [] },
    { "id": "SLICE-TOTP", "gate": "A", "workstream": "B1", "title": "TOTP + recovery codes (opt-in)", "state": "R3", "evidence": ["tests/Unit/Security/TotpTest.php"], "notes": "browser/no-JS evidence outstanding before flag-flip (program plan §F retrofit)" },
    { "id": "SLICE-SERVICE-SECRETS", "gate": "A", "workstream": "B2-SP1", "title": "Service-secret registry (SecretVault)", "state": "R3", "evidence": ["tests/Integration/Service/SecretVaultTest.php"] },
    { "id": "SLICE-API-TOKENS", "gate": "A", "workstream": "B2-SP2", "title": "Read-only API tokens + /api/v1", "state": "R3", "evidence": ["tests/Integration/Service/ApiTokenServiceTest.php"], "notes": "authorization-matrix + a11y retrofit outstanding (§F)" },
    { "id": "SLICE-WEBHOOKS", "gate": "A", "workstream": "B2-SP3", "title": "Outbound webhook delivery engine", "state": "R3", "evidence": ["tests/Integration/Service/WebhookServiceTest.php"], "notes": "formal SSRF/egress adversarial review + idempotency report outstanding (§F)" },
    { "id": "SLICE-FIRST-PARTY-HOOKS", "gate": "A", "workstream": "B2-SP4", "title": "First-party hook registry + domain producers", "state": "R3", "evidence": ["tests/Unit/Hook/FirstPartyHookRegistryTest.php"], "notes": "private-content-absence proof outstanding (§F)" }
  ],
  "flags": {
    "package_registry": { "gate": "A", "rollback": "Set features.package_registry=false (settings override) — registry/catalogue/install routes 404; installed rows stay inert; the local blocklist and flag-independent emergency disable remain available (PHASE_5_PLAN §13.2)." },
    "package_themes": { "gate": "A", "rollback": "Set features.package_themes=false — theme routes 404 and the system theme serves; safe mode is flag-independent and restores built-in branding against a broken theme (PHASE_5_PLAN §13.2)." },
    "capabilities": { "gate": "A", "rollback": "Set features.capabilities=false — resolver/role routes 404 and authorization falls back to the legacy users.role/board_moderators/post_min_role authority, preserved as the rollback source per decision #41; new grants go inactive, never approximated as Admin." },
    "passkeys": { "gate": "A", "rollback": "Set features.passkeys=false — WebAuthn ceremonies stop; credential rows are preserved for later recovery; password/OAuth/TOTP/recovery sign-in paths are unaffected (PHASE_5_PLAN §13.2)." },
    "provider_registry": { "gate": "A", "rollback": "Set features.provider_registry=false — registry-driven providers disable; builtin google/apple/github continue on the accepted fixed-provider path; identity rows and secret versions are retained (PHASE_5_PLAN §13.2)." },
    "invitations": { "gate": "A", "rollback": "Set features.invitations=false — issuance and redemption pause (routes 404); token hashes and audit are preserved for investigation and expiry (PHASE_5_PLAN §13.2)." },
    "service_secrets": { "gate": "A", "rollback": "Set features.service_secrets=false — the write/rotate kill switch: new secrets refuse; reveal/revoke/prune remain available so dependent consumers can be safely decommissioned." },
    "api_tokens": { "gate": "A", "rollback": "Set features.api_tokens=false — /api/v1 authenticates nothing (service-level kill switch); tokens stay revocable through the admin surface." },
    "webhooks": { "gate": "A", "rollback": "Set features.webhooks=false — dispatch no-ops and the worker drains nothing new; endpoint config and delivery ledger are preserved." },
    "first_party_hooks": { "gate": "A", "rollback": "Set features.first_party_hooks=false — domain producers no-op; no queued state exists to reconcile." },
    "server_extensions": { "gate": "B", "rollback": "Reserved dark (Gate B). No behavior exists; the flag stays false until the sandbox runtime passes adversarial acceptance." },
    "governance": { "gate": "B", "rollback": "Reserved dark (Gate B). No behavior exists; groups/approvals/access-review land after Gate A acceptance." },
    "service_principals": { "gate": "B", "rollback": "Reserved dark (Gate B). No behavior exists; remote-app identities land after Gate A acceptance." },
    "verified_links": { "gate": "B", "rollback": "Reserved dark (Gate B). No behavior exists; verified profile links land after Gate A acceptance." }
  }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php`
Expected: **OK (4 tests)**. If an evidence path errors, fix the path in the JSON to the file that actually exists (Step 1 verified them).

- [ ] **Step 6: Commit**

```bash
git add docs/phase5/requirement-ledger.json tests/Unit/Core/Phase5EvidenceMapTest.php
git commit -m "feat(phase5): add machine-checkable R0-R5 requirement ledger + evidence map (F10)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 10: F10 (part 2) — all-flags-off core-survival regression

**Files:**
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (append one test method)

**Interfaces:**
- Consumes: existing helpers — `setFlags()`, `makeUser/makeAdmin/makeCategory/makeBoard/makeThread`, `actingAs`, `assertStatus/assertRedirectContains`; `FeatureFlags::all()` (returns every declared flag).
- Produces: the F10 regression PHASE_5_PLAN §5 decision #40 requires — *"Every subsystem has an independent disable path … without disabling core reading/posting."*

- [ ] **Step 1: Write the failing-or-passing test (it must pass; a failure is a real product bug)**

Append to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
    public function test_core_forum_survives_with_every_feature_flag_disabled(): void
    {
        // Foundation F10 (program plan §B): the emergency posture "all flags
        // off" must leave the Phase 1 core forum fully operable — anonymous
        // reading, authenticated posting, and the admin dashboard
        // (PHASE_5_PLAN §5 decision #40). If this fails, fix the offending
        // flag guard; do not weaken the test.
        $allOff = array_map(
            static fn () => false,
            (new FeatureFlags(new SettingRepository($this->db)))->all(),
        );
        $this->setFlags($allOff);

        $author = $this->makeUser(['username' => 'allflagsoff']);
        $board = $this->makeBoard($this->makeCategory('Dark Ops'));
        $thread = $this->makeThread($board, $author, 'Core survives dark', 'Opening post.');

        $this->assertStatus(200, $this->get('/'));
        $this->assertStatus(200, $this->get('/t/' . $thread['thread_id']));

        $this->actingAs($author);
        $this->assertRedirectContains(
            $this->post('/t/' . $thread['thread_id'] . '/reply', [
                'body' => 'Still posting with every flag dark.',
                'idempotency_key' => 'allflagsoff-' . bin2hex(random_bytes(6)),
            ]),
            '/t/' . $thread['thread_id'],
        );

        $admin = $this->makeAdmin(['username' => 'darkadmin']);
        $this->actingAs($admin);
        $this->assertStatus(200, $this->get('/admin'));
    }
```

- [ ] **Step 2: Run the test**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php`
Expected: **OK** (all methods, including the new one). If the new test fails, the failing route has a flag guard that breaks the core when its flag is off — fix that guard (wrap the offending lookup in the flag check or a `try/catch` per the `shareViewGlobals` rule) and re-run; record the fix in the commit message.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "test(phase5): prove the core forum survives all-flags-off (F10)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 11: F11 — threat-model dossiers + negative-fixture index

**Files:**
- Create: `docs/phase5/threat-models/supply-chain.md`, `identity-account-takeover.md`, `privilege-escalation.md`, `theme-phishing.md`, `secret-handling.md`, `invitation-privilege.md`
- Create: `docs/phase5/threat-models/fixtures.json`
- Test: `tests/Unit/Core/ThreatModelIndexTest.php`

**Interfaces:**
- Consumes: `PHASE_5_PLAN.md` §9 (acceptance scenarios) and §12 (risks/controls) — every threat below traces to one of those rows; ADR 0004 D1–D12.
- Produces: the six reviewed dossiers §6 P5-00 requires, each threat carrying a stable ID (`TM-XX-NN`) and a **negative-fixture stub** in `fixtures.json` that the owning increment turns into a real adversarial test (flipping `status: "stub"` → `"implemented"` and adding a `test` path). The index test enforces doc ↔ index parity forever.

**fixtures.json schema:** `{"version":1,"fixtures":[{"id","model","fixture","owner","status"(,"test")}]}` — `model` is the dossier filename; `owner` ∈ `Foundation|Inc1…Inc10|GateB`; `status` ∈ `stub|implemented`; `test` (repo-relative path) required once implemented.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Core/ThreatModelIndexTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Foundation F11 — the threat-model dossiers and their negative-fixture
 * index stay in lock-step (program plan §B-F11). Each TM-XX-NN in
 * fixtures.json must appear in its dossier; implemented fixtures must point
 * at an existing test file. Analyzer split mirrors MigrationLedgerTest.
 */
final class ThreatModelIndexTest extends TestCase
{
    private const DIR = 'docs/phase5/threat-models';

    private const MODELS = [
        'supply-chain.md',
        'identity-account-takeover.md',
        'privilege-escalation.md',
        'theme-phishing.md',
        'secret-handling.md',
        'invitation-privilege.md',
    ];

    private const OWNERS = [
        'Foundation', 'Inc1', 'Inc2', 'Inc3', 'Inc4', 'Inc5',
        'Inc6', 'Inc7', 'Inc8', 'Inc9', 'Inc10', 'GateB',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,string> $modelContents filename => markdown
     * @return list<string> errors
     */
    private static function validate(array $doc, array $modelContents, string $root): array
    {
        $errors = [];
        $fixtures = $doc['fixtures'] ?? null;
        if (!is_array($fixtures) || $fixtures === []) {
            return ['fixtures missing or empty'];
        }

        $seen = [];
        $perModel = array_fill_keys(self::MODELS, 0);
        foreach ($fixtures as $i => $f) {
            $id = is_array($f) ? (string) ($f['id'] ?? "#$i") : "#$i";
            if (preg_match('/^TM-[A-Z]{2,4}-\d{2}$/', $id) !== 1) {
                $errors[] = "$id: malformed id";
            }
            if (isset($seen[$id])) {
                $errors[] = "$id: duplicate id";
            }
            $seen[$id] = true;

            $model = (string) ($f['model'] ?? '');
            if (!in_array($model, self::MODELS, true)) {
                $errors[] = "$id: unknown model '$model'";
            } elseif (!isset($modelContents[$model])) {
                $errors[] = "$id: dossier missing on disk: $model";
            } elseif (!str_contains($modelContents[$model], $id)) {
                $errors[] = "$id: not documented in $model";
            } else {
                $perModel[$model]++;
            }

            if (trim((string) ($f['fixture'] ?? '')) === '') {
                $errors[] = "$id: empty fixture description";
            }
            if (!in_array($f['owner'] ?? '', self::OWNERS, true)) {
                $errors[] = "$id: unknown owner";
            }
            $status = $f['status'] ?? '';
            if (!in_array($status, ['stub', 'implemented'], true)) {
                $errors[] = "$id: status must be stub|implemented";
            }
            if ($status === 'implemented' && (!is_string($f['test'] ?? null) || !is_file($root . '/' . $f['test']))) {
                $errors[] = "$id: implemented fixture must name an existing test file";
            }
        }

        foreach ($perModel as $model => $count) {
            if ($count === 0) {
                $errors[] = "$model: no fixtures indexed";
            }
        }

        return $errors;
    }

    /** @return array{doc:array<string,mixed>,contents:array<string,string>} */
    private static function loadReal(): array
    {
        $dir = self::root() . '/' . self::DIR;
        $doc = json_decode((string) file_get_contents($dir . '/fixtures.json'), true);
        self::assertIsArray($doc, 'fixtures.json must be valid JSON');

        $contents = [];
        foreach (self::MODELS as $model) {
            $path = $dir . '/' . $model;
            self::assertFileExists($path);
            $contents[$model] = (string) file_get_contents($path);
        }

        return ['doc' => $doc, 'contents' => $contents];
    }

    public function test_every_dossier_exists_and_index_is_valid(): void
    {
        ['doc' => $doc, 'contents' => $contents] = self::loadReal();
        $errors = self::validate($doc, $contents, self::root());
        self::assertSame([], $errors, "threat-model index invalid:\n- " . implode("\n- ", $errors));
    }

    public function test_every_dossier_is_recorded_pending_owner_review(): void
    {
        ['contents' => $contents] = self::loadReal();
        foreach ($contents as $model => $markdown) {
            self::assertStringContainsString('pending owner review', $markdown, $model);
        }
    }

    public function test_validator_flags_undocumented_id_bad_owner_and_fake_test_path(): void
    {
        $contents = array_fill_keys(self::MODELS, 'doc mentions TM-SC-01 only');
        $doc = ['version' => 1, 'fixtures' => [
            ['id' => 'TM-SC-01', 'model' => 'supply-chain.md', 'fixture' => 'x', 'owner' => 'Inc2', 'status' => 'stub'],
            ['id' => 'TM-SC-99', 'model' => 'supply-chain.md', 'fixture' => 'x', 'owner' => 'Inc2', 'status' => 'stub'],
            ['id' => 'TM-ID-01', 'model' => 'identity-account-takeover.md', 'fixture' => 'x', 'owner' => 'NotATeam', 'status' => 'stub'],
            ['id' => 'TM-PE-01', 'model' => 'privilege-escalation.md', 'fixture' => 'x', 'owner' => 'Inc6', 'status' => 'implemented', 'test' => 'no/such/Test.php'],
        ]];
        $errors = self::validate($doc, $contents, self::root());
        self::assertContains('TM-SC-99: not documented in supply-chain.md', $errors);
        self::assertContains('TM-ID-01: unknown owner', $errors);
        self::assertContains('TM-PE-01: implemented fixture must name an existing test file', $errors);
        self::assertContains('theme-phishing.md: no fixtures indexed', $errors);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php`
Expected: **FAIL** — `assertFileExists` / json error for the missing directory.

- [ ] **Step 3: Write the six dossiers**

> Every threat row traces to a `PHASE_5_PLAN.md` §9 scenario or §12 risk (cited per row); IDs are permanent — never renumber, only append.

Create `docs/phase5/threat-models/supply-chain.md`:

```markdown
# Phase 5 threat model — Supply chain (registry, packages, publishers)

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Registry signature/Source pinning/Key rotation/Revoked release/Registry outage/Tampered local files/Review digest/Publisher compromise), §12 rows 1–8; decisions #3–#17; ADR 0004 D1–D3; docs/phase5/registry-signing-key-custody.md
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
The signed public catalogue and everything an operator installs from it: registry snapshots, trust roots (`registry_trust_keys` — public material only), publishers, packages, immutable releases (version + sha256 digest + detached Ed25519 signature), advisories, and the local blocklist. Protected asset: the integrity chain from reviewed bytes to executing/rendering bytes.

## Trust boundaries
Registry → installer (network, signed metadata); publisher → registry (signing keys); reviewed digest → installed bytes (filesystem); registry availability → local operation (offline cache + pinning).

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-SC-01 | Tampered snapshot or release metadata (one changed byte) | Malicious package accepted as signed | Detached Ed25519 over exact bytes; fail-closed verify (§9 Registry signature; D2) | Byte-flipped snapshot/release rejected by verifier | Inc2 |
| TM-SC-02 | Stale/replayed snapshot served past freshness window | Operator installs a version the registry already superseded/revoked | Signed expiry + anti-replay on snapshot age (D2 24h; §9 Registry outage) | Expired snapshot refuses install decisions; cached view labeled stale | Inc2 |
| TM-SC-03 | Signature by an untrusted or revoked key | Attacker-signed catalogue accepted | Trust-chain pinning to `registry_trust_keys` status/validity (§9 Key rotation) | Wrong-key + revoked-key signatures rejected | Inc2 |
| TM-SC-04 | Forged key-rotation transition (attacker-supplied new key) | Persistent trust hijack | Rotation only via transition signed by the approved old key (§9 Key rotation; D2) | Rotation doc signed by non-approved key rejected | Inc2 |
| TM-SC-05 | Dependency confusion / source substitution (same name, other registry) | Look-alike package replaces the pinned source | Globally-namespaced `(source_id, package_uid)`; no implicit registry fallback (§9 Source pinning; §12 row 4; decision #5) | Same-uid package on second registry cannot satisfy install/update | Inc2 |
| TM-SC-06 | Revoked/blocked digest newly installed or enabled | Known-bad code activates | Advisory `revoke` + registry-independent `local_package_blocks` checked at enable (§9 Revoked release; D3; decision #17) | Revoked and locally-blocked digests fail install AND enable | Inc3 |
| TM-SC-07 | Installed bytes modified after review (local tamper) | Reviewed digest no longer matches executing bytes | Digest re-verification health worker → quarantine (§9 Tampered local files) | On-disk byte flip → health failure + quarantine, not "reviewed" | Inc3 |
| TM-SC-08 | Manifest lies: undeclared permissions/hosts/jobs used at runtime | Consent screen understates actual authority | Declared = ceiling; grants enforced at the real gates, not the manifest (§12 row 5; decision #7) | Package exercising an undeclared scope/host is denied + audited | Inc5 |
| TM-SC-09 | Review approves digest A, publisher publishes digest B under same version | Users install unreviewed bytes | Review decision bound to exact digest; any byte change = new release (§9 Review digest; decision #16) | Approval for digest A does not authorize digest B | Inc3 |

## Residual risk & review notes
A compromised operator machine or a malicious first-party maintainer is out of scope (the operator IS the trust root, per the self-host framing in registry-signing-key-custody.md). Gate A has no third-party publisher self-service — publisher-compromise response (§9 Publisher compromise) is exercised at console level in Inc 5 and matures in Gate B (P5-07-B).
```

Create `docs/phase5/threat-models/identity-account-takeover.md`:

```markdown
# Phase 5 threat model — Identity & account takeover (passkeys, OIDC, providers)

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Passkey registration/sign-in/removal, Synced counter, OIDC issuer mix-up/key rotation, Provider collision/migration/disable), §12 rows 26–32; decisions #28–#35; ADR 0004 D6–D8; docs/phase5/canonical-origin-and-rp-id.md
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
Authentication material and account linkage: WebAuthn credentials/challenges (`0051`), provider identities (`oauth_identities` + provider registry `0052`), the collision/linking rules, recovery paths (password, TOTP/recovery codes, verified email), and session issuance. Protected asset: one member's account cannot be entered or merged by anyone but its owner.

## Trust boundaries
Browser/authenticator → server (WebAuthn ceremony); identity provider → server (OIDC callback); email inbox → account recovery; one account's credentials → another account (must be impossible).

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-ID-01 | OIDC issuer mix-up / token from wrong issuer or audience | Login as victim via confused deputy | iss/aud/azp validation pinned to provider config (§9 OIDC issuer mix-up; decision #33) | Cross-issuer token, wrong aud, wrong azp all rejected | Inc8 |
| TM-ID-02 | Replayed or forged state/nonce/PKCE on callback | Session fixation / code injection | state cookie + nonce + PKCE verified one-time (§9; D8) | Replayed state, missing nonce, wrong verifier rejected | Inc8 |
| TM-ID-03 | JWKS poisoning via attacker-controlled key URL or redirect | Forged tokens verify | Issuer-pinned discovery/JWKS refresh; no redirect-following to new origins (§9 OIDC key rotation) | JWKS fetch from off-issuer URL refused; rotated real key accepted | Inc8 |
| TM-ID-04 | Email-collision silent merge (provider asserts victim's email) | Account takeover via new provider | Email is never a merge key; explicit proof-to-link (decision #32; §9 Provider collision) | Verified-email match still requires linked-login proof; unverified never matches | Inc8 |
| TM-ID-05 | WebAuthn challenge replay or cross-account consumption | Credential registered/asserted against wrong account | Challenge one-time, purpose/user/session-bound, short-lived (`0051`; §8.5) | Reused challenge and cross-user challenge rejected | Inc7 |
| TM-ID-06 | Passkey registered onto an attacker-controlled session after credential theft | Persistent backdoor credential | Recent-reauth step-up before add/remove; notification + audit (§12 row 29; decision #26 via ReauthGate) | Credential add without fresh factor rejected | Inc7 |
| TM-ID-07 | Wrong origin / RP ID accepted in ceremony | Phishing site completes a real ceremony | Origin equality + RP-ID = registrable domain of APP_URL, hard-refuse non-HTTPS prod (D6; A5 doc) | Mismatched origin/rpIdHash rejected | Inc7 |
| TM-ID-08 | Synced-passkey counter anomaly treated as compromise → lockout | Valid user permanently locked out (availability attack) | Counter anomaly = risk signal, never auto-ban (§9 Synced counter; decision #30) | Non-increasing counter logs risk event, sign-in follows policy | Inc7 |
| TM-ID-09 | Removing last strong credential / disabling sole provider strands or weakens account | Orphaned account or weaker bypass recovery | Last-method block + LastOwnerGuard for owners; sole-method inventory before provider disable (§9 Passkey removal/Provider disable; decision #27) | Last-usable-method removal blocked; provider disable lists sole-method accounts | Inc7 |

## Residual risk & review notes
SMS, government-ID, and biometric proofing are explicitly out of Phase 5 (§4 deferrals). Malware on the member's device defeats any credential scheme and is out of scope. Provider-side breaches are mitigated only by stable-subject keying + explicit linking; the provider registry must never let a package alter collision rules (decision #34 — enforced by keeping account resolution core-owned, verified in Inc 8).
```

Create `docs/phase5/threat-models/privilege-escalation.md`:

```markdown
# Phase 5 threat model — Privilege & role escalation (capabilities, scopes, owners)

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Built-in role parity, Custom role scope, Non-delegable capability, Grantor authority, State precedence, Private read gate, Temporary grant, Role edit, Permission simulator, Last owner), §12 rows 13–18; decisions #18–#27; docs/phase5/capability-taxonomy.md
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
The database-backed authorization spine: capability catalogue (`CapabilityCatalog`, `0050`/`0066`), role definitions and scoped assignments, the union-then-narrow resolver, `protected_owners` + `LastOwnerGuard`, and the legacy compatibility authority kept as rollback. Protected asset: no path — role, grant, cache, simulator, migration, or workflow — may yield authority beyond the accepted model.

## Trust boundaries
Role editor → resolver (definitions become authority); grantor → grantee (delegation); cache → resolver (staleness); legacy authority ↔ new resolver (parity during migration); any actor → protected-owner invariant.

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-PE-01 | Custom role acquires a protected capability (direct, clone, plugin namespace, or API) | Site-ownership powers delegated | Non-delegable set never role-mapped; enforced at write + seed (decision #22; F3 `0066` test) | Role create/edit/clone with protected key rejected; seed test pins zero mappings | Inc1 |
| TM-PE-02 | Grantor assigns beyond their own scope or capability (board mod grants site role) | Lateral/upward escalation | Pure `GrantorAuthority`: same-or-narrower scope + must-hold + delegable (§9 Grantor authority; §12 row 15) | Direct POST by board-scoped manager for site scope fails + audited | Inc6 |
| TM-PE-03 | Expired temporary grant still honored via stale permission cache | Zombie privilege | Expiry checked in the resolver itself; version-keyed cache invalidation (§9 Temporary grant; decision #24) | Direct request 1s after `ends_at` denied despite warm cache | Inc6 |
| TM-PE-04 | Suspended/banned actor exercises a custom role | State gate bypassed by role machinery | State-first resolution — WriteGate precedes any grant union (§9 State precedence; decision #20) | Suspended user with active custom role denied on every write | Inc1 |
| TM-PE-05 | Custom role leaks private-board content via search/simulator/counts/notifications | Read gate bypassed by side channel | Canonical read gate narrows after union; simulator redacts targets (§9 Private read gate/Permission simulator; decision #25) | Simulator + search + counts show nothing for non-member role | Inc1 |
| TM-PE-06 | Legacy migration broadens a board/category grant to site scope | Silent mass escalation at cutover | Non-broadening import; ambiguous grants held non-enforcing (§8.4; §9 Built-in role parity) | Parity corpus: every migrated grant ≤ legacy scope; ambiguous rows flagged | Inc6 |
| TM-PE-07 | Removal/demotion/deactivation of the final protected owner | Permanent loss of recovery authority | `LastOwnerGuard` on all five owner-loss paths; transactional owner transfer (§9 Last owner; decision #27; §8.5) | Each path (lifecycle ✓ landed, role-revoke, passkey-removal, provider-unlink, invitation) blocks at last owner | Inc6 |
| TM-PE-08 | Role edit removes a capability but assignments keep old authority | Revocation doesn't propagate | `roles.version` bump + cache transition + impact count (§9 Role edit) | Capability removed → next direct request denied for all assignees | Inc6 |
| TM-PE-09 | Self-approval of one's own elevation (Gate B approvals) | Dual control defeated | No-self-approval; approver must hold capability+scope (§9 No self-approval; decision #26) | Requester counted as approver → request stays pending | GateB |

## Residual risk & review notes
Parity-first stance (ADR 0012): documented legacy quirks (`core.user.warn` staff-any, vestigial global-moderator) are reproduced, not fixed, during migration — correcting them is a post-parity owner decision. Collusion between two admins is out of scope for Gate A (dual-control arrives with Gate B approvals).
```

Create `docs/phase5/threat-models/theme-phishing.md`:

```markdown
# Phase 5 threat model — Theme phishing & malicious styling

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Theme safety/Theme phishing/Theme accessibility), §12 row 11; decisions #14–#15; P5-03 workstream brief (program plan Inc 4)
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
Declarative theme packages: token sets, approved local assets, build/cache pipeline, preview, active/default + last-known-good pointers, safe mode. Protected assets: the authenticity of security-critical UI (login, MFA, consent, warnings, safe-mode controls) and the strict CSP (no inline styles, no external requests at page load).

## Trust boundaries
Package author → operator preview → site default (styling reaches every visitor); theme CSS → security UI (must not be able to imitate or hide it); package assets → browser (must be local, scanned, digest-pinned).

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-TH-01 | Stylesheet hides/overlays/replaces login, MFA, consent, or warning UI | Phishing inside the real site | Gate A token whitelist — no selectors at all; Gate B AST + anti-phishing checks on security-UI regions (§9 Theme phishing) | Token package attempting selector/`content`/overlay constructs rejected at validation | Inc4 |
| TM-TH-02 | Remote fonts/images/`@import`/tracker URLs in theme values | Visitor exfiltration/tracking at page load | Zero-outbound rule: local scanned assets only; no `url()` to remote, no `@import` (§9 Theme safety; decision #14) | Each remote-reference vector rejected; built CSS provably free of external URLs | Inc4 |
| TM-TH-03 | JavaScript/PHP smuggled via asset (SVG script, polyglot file) | Code execution under theme trust | `AttachmentService`-style sniff/re-encode + digest pin; no executable media (§9 Theme safety) | SVG-with-script and polyglot assets rejected/neutralized by scan | Inc4 |
| TM-TH-04 | Malicious theme breaks contrast/focus so warnings are invisible | Security-relevant text unreadable | WCAG contrast/a11y validation; critical failures hard-block default (§9 Theme accessibility) | Sub-threshold contrast token set cannot become site default | Inc4 |
| TM-TH-05 | Theme package hides the controls needed to disable it | Operator lock-in to hostile styling | Flag-independent safe mode rendering built-in baseline without package CSS (decision #15; §13.2) | Safe-mode route renders system theme while a hostile theme is active | Inc4 |
| TM-TH-06 | Preview mutates the live default (preview/live confusion) | Site-wide styling change without activation | Per-admin isolated preview; activation is a separate transactional step with LKG capture (Inc 4 scope; §8.5) | Preview session changes nothing for a second browser/user | Inc4 |
| TM-TH-07 | Cache poisoning: stale/tampered built CSS served after rollback | Rolled-back theme still styles pages | Deterministic build, content-digest cache key, atomic pointer swap (Inc 4 scope) | After LKG rollback the served stylesheet digest equals LKG digest | Inc4 |

## Residual risk & review notes
Gate A themes are tokens + assets only — the selector-level attack surface (TM-TH-01) fully opens only with Gate B restricted stylesheet modules; the Gate A fixture proves the whitelist rejects the construct class outright. Operator-authored custom CSS (Phase 3) keeps its existing safe-mode path and is out of scope here (ADR 0009).
```

Create `docs/phase5/threat-models/secret-handling.md`:

```markdown
# Phase 5 threat model — Secret handling (vault, provider/client secrets, logs, backups)

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Backup/restore), §10.2 (security-sensitive evidence), §11.4 (telemetry redaction rules); decisions #6, #35; ADR 0004 B3/D2; SecretVault/SecretBox implementation (0055)
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
Everything `SecretVault`/`SecretBox` protect: webhook signing secrets, future OIDC client secrets and remote-app credentials (`svcsec_*` references, AES-256-GCM at rest under APP_KEY), API-token plaintexts (shown once, stored hash-only), registry signing material (never server-side at all), plus every channel a secret could leak through: logs, telemetry, exports, error pages, backups.

## Trust boundaries
Application ↔ database (only ciphertext/hashes cross); application ↔ logs/telemetry (only redacted context crosses — F8); backup ↔ restore (revoked material must not resurrect); admin UI ↔ operator (show-once, never redisplay).

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-SE-01 | Secret/token/challenge printed to logs or telemetry | Credential harvest from log files | `LogRedactor` on every telemetry line; value-shape redaction for `rbt_`/`svcsec_`/Bearer (F8; §11.4) | Emit context containing live secret → line carries `[redacted]`, never the value (✓ landed in `TelemetryRedactionTest`) | Foundation |
| TM-SE-02 | Secret redisplayed after save (UI or API echo) | Shoulder-surf/replay exposure | Write-only-after-save: show-once at mint/rotate, metadata-only reads (decision #35; SecretVault contract) | Fetching a webhook/token/provider config after save returns no plaintext | Inc5 |
| TM-SE-03 | Backup restore resurrects a revoked/rotated secret version | Revoked credential valid again | Restore reconciliation: revoked/destroyed versions stay destroyed (§9 Backup/restore; §13.2) | Post-restore, a revoked `svcsec_*` version cannot decrypt/sign | Inc10 |
| TM-SE-04 | Unauthorized caller dereferences a `svcsec_*` reference | Cross-subsystem secret read | Vault access via owning service only; audited reveal; flag kill-switch (0055 contract) | Non-owning consumer reveal attempt fails + audits | Inc5 |
| TM-SE-05 | Exception/error page includes secret material from a failed operation | Leak via 500 page or debug output | Exception messages carry references, never plaintext (SecretVault redaction tests); `app.debug` off in prod | Forced vault failure → message contains `svcsec_*` ref only | Inc5 |
| TM-SE-06 | Provider client-secret exposed via provider health/test output | OIDC client compromise | Health/test flows report status only; secrets encrypted via vault (decision #35; Inc 8 scope) | Provider test-flow response/logs free of client secret | Inc8 |
| TM-SE-07 | APP_KEY loss or rotation bricks every encrypted secret | Total vault loss (availability) | Documented DR: re-mint path per consumer; rotation prefers re-issue over decrypt-migrate (§8.4 last bullet) | Runbook rehearsal: vault unreadable → webhooks/providers re-key without data loss | Inc10 |

## Residual risk & review notes
Server-side plaintext IS readable to a root attacker at decrypt time — encryption at rest protects backups/dumps, not a fully compromised host (accepted single-VPS posture, DESIGN §11). Registry trust-root private keys are deliberately absent from this system (operator-held, A4) so no server compromise can sign releases.
```

Create `docs/phase5/threat-models/invitation-privilege.md`:

```markdown
# Phase 5 threat model — Invitation abuse & privilege injection

**Status:** Recorded 2026-07-01 — pending owner review (program plan §B-F11; PHASE_5_PLAN §6 P5-00)
**Sources:** PHASE_5_PLAN §9 (Invitation replay/binding/privilege), §12 row 33; decisions #36; ADR 0004 D9; 0053 schema
**Fixture index:** `fixtures.json` (enforced by `tests/Unit/Core/ThreatModelIndexTest.php`)

## Scope & assets
The invitation lifecycle (`0053`): hash-only tokens, expiry/use limits, email/domain binding, redemption→registration atomicity, optional non-privileged onboarding grants. Protected assets: registration-mode enforcement (invite-only stays invite-only) and the rule that a token is onboarding evidence, never authority.

## Trust boundaries
Inviter → invitee (token travels out-of-band); token → registration (redemption); invitation payload → granted role/membership (must stay non-privileged); public redemption endpoint → anti-abuse.

## Threats

| ID | Threat | Impact | Control (spec ref) | Negative fixture (stub) | Owner |
|---|---|---|---|---|---|
| TM-IN-01 | Token guessing/brute force against the redemption endpoint | Uninvited registration on an invite-only site | High-entropy random token, hash-only storage, rate-limited endpoint (§9 Invitation binding; D9) | Low-entropy/enumeration attempts rejected + rate-limited; DB holds no raw token | Inc9 |
| TM-IN-02 | Concurrent redemption exceeds `max_uses` (replay race) | One invite becomes many accounts | Guarded consume UPDATE + atomic redeem-and-register transaction (§9 Invitation replay; §8.5) | Two concurrent redemptions of a single-use token → exactly one account | Inc9 |
| TM-IN-03 | Expired or revoked token still redeems | Withdrawn access honored | Expiry/revocation checked inside the consume transaction (§9 Invitation binding) | Expired and revoked tokens both fail with no account created | Inc9 |
| TM-IN-04 | Email/domain binding bypass (redeem with different address) | Targeted invite harvested by a third party | Binding validated against the registering address pre-commit (D9) | Mismatched email and mismatched domain both rejected | Inc9 |
| TM-IN-05 | Privilege injection: crafted payload/direct POST turns onboarding into admin/protected authority | Instant escalation at account creation | Non-privileged grants only; privileged roles require the separate authenticated assignment path; `capabilities` gate (§9 Invitation privilege; decision #36) | Forged `role`/grant fields in redemption POST yield ordinary membership only | Inc9 |
| TM-IN-06 | Raw token in logs, DB, or invitation-list UI | Token theft after issuance | Hash-only at rest; show-once at creation; redacted logs (F8) (§12 row 33) | Post-create, token absent from DB/logs/list views | Inc9 |
| TM-IN-07 | Invitation spam floods (mass issuance by a compromised/abusive member) | Abuse vector + registration flooding | Admin-only default (A6); per-creator rate limits + revoke-all; audit (D9) | Member issuance denied by default; burst issuance rate-limited | Inc9 |

## Residual risk & review notes
An invitee forwarding their unbound token is accepted product behavior (bind to email/domain where it matters). Member-created invitations stay OFF unless the owner opts in via A6 — this dossier's fixtures assume the admin-only default and gain a member-quota case only if A6 flips.
```

- [ ] **Step 4: Write the fixture index**

Create `docs/phase5/threat-models/fixtures.json`:

```json
{
  "version": 1,
  "notes": "Negative-fixture stubs (program plan §B-F11). The owning increment turns each stub into a real adversarial test: set status=implemented and add test=<repo-relative path>. Enforced by tests/Unit/Core/ThreatModelIndexTest.php.",
  "fixtures": [
    { "id": "TM-SC-01", "model": "supply-chain.md", "fixture": "byte-flipped snapshot/release rejected by verifier", "owner": "Inc2", "status": "stub" },
    { "id": "TM-SC-02", "model": "supply-chain.md", "fixture": "expired snapshot refuses install decisions", "owner": "Inc2", "status": "stub" },
    { "id": "TM-SC-03", "model": "supply-chain.md", "fixture": "wrong-key and revoked-key signatures rejected", "owner": "Inc2", "status": "stub" },
    { "id": "TM-SC-04", "model": "supply-chain.md", "fixture": "rotation doc signed by non-approved key rejected", "owner": "Inc2", "status": "stub" },
    { "id": "TM-SC-05", "model": "supply-chain.md", "fixture": "same-uid package on second registry cannot satisfy install/update", "owner": "Inc2", "status": "stub" },
    { "id": "TM-SC-06", "model": "supply-chain.md", "fixture": "revoked and locally-blocked digests fail install and enable", "owner": "Inc3", "status": "stub" },
    { "id": "TM-SC-07", "model": "supply-chain.md", "fixture": "on-disk byte flip triggers health failure + quarantine", "owner": "Inc3", "status": "stub" },
    { "id": "TM-SC-08", "model": "supply-chain.md", "fixture": "undeclared scope/host exercised at runtime is denied + audited", "owner": "Inc5", "status": "stub" },
    { "id": "TM-SC-09", "model": "supply-chain.md", "fixture": "approval for digest A does not authorize digest B", "owner": "Inc3", "status": "stub" },
    { "id": "TM-ID-01", "model": "identity-account-takeover.md", "fixture": "cross-issuer token / wrong aud / wrong azp rejected", "owner": "Inc8", "status": "stub" },
    { "id": "TM-ID-02", "model": "identity-account-takeover.md", "fixture": "replayed state, missing nonce, wrong PKCE verifier rejected", "owner": "Inc8", "status": "stub" },
    { "id": "TM-ID-03", "model": "identity-account-takeover.md", "fixture": "JWKS fetch from off-issuer URL refused; rotated real key accepted", "owner": "Inc8", "status": "stub" },
    { "id": "TM-ID-04", "model": "identity-account-takeover.md", "fixture": "verified-email match still requires linked-login proof", "owner": "Inc8", "status": "stub" },
    { "id": "TM-ID-05", "model": "identity-account-takeover.md", "fixture": "reused and cross-user WebAuthn challenges rejected", "owner": "Inc7", "status": "stub" },
    { "id": "TM-ID-06", "model": "identity-account-takeover.md", "fixture": "credential add without fresh reauth factor rejected", "owner": "Inc7", "status": "stub" },
    { "id": "TM-ID-07", "model": "identity-account-takeover.md", "fixture": "mismatched origin/rpIdHash rejected in ceremony", "owner": "Inc7", "status": "stub" },
    { "id": "TM-ID-08", "model": "identity-account-takeover.md", "fixture": "non-increasing counter logs risk event without auto-lockout", "owner": "Inc7", "status": "stub" },
    { "id": "TM-ID-09", "model": "identity-account-takeover.md", "fixture": "last-usable-method removal blocked; provider disable lists sole-method accounts", "owner": "Inc7", "status": "stub" },
    { "id": "TM-PE-01", "model": "privilege-escalation.md", "fixture": "role create/edit/clone with a protected key rejected; seed pins zero protected mappings", "owner": "Inc1", "status": "stub" },
    { "id": "TM-PE-02", "model": "privilege-escalation.md", "fixture": "board-scoped grantor cannot assign site scope via direct POST", "owner": "Inc6", "status": "stub" },
    { "id": "TM-PE-03", "model": "privilege-escalation.md", "fixture": "direct request after ends_at denied despite warm cache", "owner": "Inc6", "status": "stub" },
    { "id": "TM-PE-04", "model": "privilege-escalation.md", "fixture": "suspended user with active custom role denied on every write", "owner": "Inc1", "status": "stub" },
    { "id": "TM-PE-05", "model": "privilege-escalation.md", "fixture": "simulator/search/counts leak nothing for a non-member role", "owner": "Inc1", "status": "stub" },
    { "id": "TM-PE-06", "model": "privilege-escalation.md", "fixture": "parity corpus proves no migrated grant exceeds legacy scope", "owner": "Inc6", "status": "stub" },
    { "id": "TM-PE-07", "model": "privilege-escalation.md", "fixture": "every owner-loss path blocks at the last recoverable owner", "owner": "Inc6", "status": "stub" },
    { "id": "TM-PE-08", "model": "privilege-escalation.md", "fixture": "capability removed from role denies all assignees on next direct request", "owner": "Inc6", "status": "stub" },
    { "id": "TM-PE-09", "model": "privilege-escalation.md", "fixture": "requester counted as approver leaves request pending", "owner": "GateB", "status": "stub" },
    { "id": "TM-TH-01", "model": "theme-phishing.md", "fixture": "token package attempting selector/overlay constructs rejected", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-02", "model": "theme-phishing.md", "fixture": "remote url()/@import/tracker vectors rejected; built CSS free of external URLs", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-03", "model": "theme-phishing.md", "fixture": "SVG-with-script and polyglot assets neutralized by scan", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-04", "model": "theme-phishing.md", "fixture": "sub-threshold contrast token set cannot become site default", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-05", "model": "theme-phishing.md", "fixture": "safe mode renders system theme while a hostile theme is active", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-06", "model": "theme-phishing.md", "fixture": "preview session changes nothing for a second user", "owner": "Inc4", "status": "stub" },
    { "id": "TM-TH-07", "model": "theme-phishing.md", "fixture": "after LKG rollback the served stylesheet digest equals the LKG digest", "owner": "Inc4", "status": "stub" },
    { "id": "TM-SE-01", "model": "secret-handling.md", "fixture": "telemetry line with live secret carries [redacted], never the value", "owner": "Foundation", "status": "implemented", "test": "tests/Unit/Core/TelemetryRedactionTest.php" },
    { "id": "TM-SE-02", "model": "secret-handling.md", "fixture": "reading a webhook/token/provider config after save returns no plaintext", "owner": "Inc5", "status": "stub" },
    { "id": "TM-SE-03", "model": "secret-handling.md", "fixture": "post-restore, a revoked svcsec version cannot decrypt/sign", "owner": "Inc10", "status": "stub" },
    { "id": "TM-SE-04", "model": "secret-handling.md", "fixture": "non-owning consumer reveal attempt fails + audits", "owner": "Inc5", "status": "stub" },
    { "id": "TM-SE-05", "model": "secret-handling.md", "fixture": "forced vault failure yields svcsec reference only, no plaintext", "owner": "Inc5", "status": "stub" },
    { "id": "TM-SE-06", "model": "secret-handling.md", "fixture": "provider test-flow response and logs free of client secret", "owner": "Inc8", "status": "stub" },
    { "id": "TM-SE-07", "model": "secret-handling.md", "fixture": "vault-unreadable DR rehearsal re-keys webhooks/providers without data loss", "owner": "Inc10", "status": "stub" },
    { "id": "TM-IN-01", "model": "invitation-privilege.md", "fixture": "token enumeration rejected + rate-limited; DB holds no raw token", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-02", "model": "invitation-privilege.md", "fixture": "two concurrent redemptions of a single-use token yield exactly one account", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-03", "model": "invitation-privilege.md", "fixture": "expired and revoked tokens fail with no account created", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-04", "model": "invitation-privilege.md", "fixture": "mismatched email and mismatched domain both rejected", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-05", "model": "invitation-privilege.md", "fixture": "forged role/grant fields in redemption POST yield ordinary membership only", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-06", "model": "invitation-privilege.md", "fixture": "post-create, token absent from DB/logs/list views", "owner": "Inc9", "status": "stub" },
    { "id": "TM-IN-07", "model": "invitation-privilege.md", "fixture": "member issuance denied by default; burst issuance rate-limited", "owner": "Inc9", "status": "stub" }
  ]
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php`
Expected: **OK (3 tests)** — note TM-SE-01 is already `implemented` because Task 8's `TelemetryRedactionTest` IS its fixture; the validator confirms that path exists.

- [ ] **Step 6: Commit**

```bash
git add docs/phase5/threat-models/ tests/Unit/Core/ThreatModelIndexTest.php
git commit -m "docs(phase5): add six threat-model dossiers + negative-fixture index (F11)

Recorded pending owner review; each threat carries a stable TM id and a
stub the owning increment turns into a real adversarial test.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 12: Closeout — ledger bump, status docs, Foundation exit gate

**Files:**
- Modify: `docs/phase5/requirement-ledger.json` (bump F2/F4/F6/F7/F8/F10/F11 to R3 with evidence)
- Modify: `PHASE_5_STATUS.md` (record the Foundation remainder landing; refresh suite counts)
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§B row annotations)

**Interfaces:**
- Consumes: everything Tasks 1–11 landed.
- Produces: the **Foundation exit gate** record (program plan §B): "catalogue + protected_owners seeded (dark); CORE_VERSION, DataClasses, LastOwnerGuard, ReauthGate, telemetry/redaction, signing harness, fixture, baseline+budget report, requirement ledger, threat-models all landed; full suite green; AppFeatureFlagTest still proves all Phase 5 flags dark." Increments 1 (resolver shadow) and 2 (registry) are unblocked.

- [ ] **Step 1: Bump the ledger states**

In `docs/phase5/requirement-ledger.json`, update these requirement rows (state + evidence; leave every other row untouched):

```json
{ "id": "F2", "gate": "A", "workstream": "Foundation", "title": "App::CORE_VERSION + CoreVersion range primitive", "state": "R3", "evidence": ["src/Support/CoreVersion.php", "tests/Unit/Support/CoreVersionTest.php"] },
{ "id": "F4", "gate": "A", "workstream": "Foundation", "title": "DataClasses catalogue + consent vocabulary", "state": "R3", "evidence": ["src/Security/DataClasses.php", "tests/Unit/Security/DataClassesTest.php"] },
{ "id": "F6", "gate": "A", "workstream": "Foundation", "title": "Ed25519 signed-artifact harness + dev/test registry fixtures", "state": "R3", "evidence": ["tests/Support/Phase5/SigningHarness.php", "tests/Unit/Support/SigningHarnessTest.php", "tests/Integration/Core/RegistryFixturesTest.php"] },
{ "id": "F7", "gate": "A", "workstream": "Foundation", "title": "Unified ReauthGate present-factor policy", "state": "R3", "evidence": ["src/Security/ReauthGate.php", "tests/Integration/Security/ReauthGateTest.php"] },
{ "id": "F8", "gate": "A", "workstream": "Foundation", "title": "Telemetry + LogRedactor seam", "state": "R3", "evidence": ["src/Core/Telemetry.php", "src/Support/LogRedactor.php", "tests/Unit/Core/TelemetryRedactionTest.php"] },
{ "id": "F10", "gate": "A", "workstream": "Foundation", "title": "Requirement ledger + evidence map + all-flags-off regression", "state": "R3", "evidence": ["docs/phase5/requirement-ledger.json", "tests/Unit/Core/Phase5EvidenceMapTest.php", "tests/Integration/Core/AppFeatureFlagTest.php"] },
{ "id": "F11", "gate": "A", "workstream": "Foundation", "title": "Threat-model dossiers + negative-fixture index", "state": "R3", "evidence": ["docs/phase5/threat-models/fixtures.json", "tests/Unit/Core/ThreatModelIndexTest.php"] }
```

Also update GA-DOD-12's note to reflect ReauthGate landing: `"LastOwnerGuard (F5) + ReauthGate (F7) landed; owner-loss paths 2-5 and high-impact wiring land in Inc 6-9"` (it already says this — verify it still matches reality). Bump the top-level `"updated"` to the real date.

Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php` → **OK** (every new evidence path must exist — they all landed in Tasks 1–11).

- [ ] **Step 2: Run the Foundation exit-gate checks**

```bash
composer test                                  # full suite, run 1
composer test                                  # run 2 — the reused-schema path must also be green
vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter test_phase5_foundation_flags_default_dark
```
Expected: both full runs green (record the new test/assertion counts); the flags-dark regression unchanged. No migrations were added, so `verify:upgrade` needs no re-run — the F1 ledger guard inside the suite already proves the migration set is untouched.

- [ ] **Step 3: Update `PHASE_5_STATUS.md`**

- In the header **Status** line, replace “the Foundation authorization spine F3 … landed deploy-dark behind `capabilities`” context with the completed-Foundation statement, e.g.: `…and the Foundation increment (F1–F11) is COMPLETE (F2/F4/F6/F7/F8/F10/F11 landed 2026-0M-DD): CORE_VERSION, DataClasses, the Ed25519 signing harness + registry fixtures, ReauthGate, Telemetry/LogRedactor, the machine-checkable requirement ledger + all-flags-off regression, and the six threat-model dossiers (recorded, pending owner review). Increments 1 (resolver shadow) and 2 (registry) are unblocked.` (Use the real date.)
- Update the **Suite** line with the counts from Step 2.
- In the **Requirement-ledger snapshot** section, add: `**Foundation remainder (F2/F4/F6/F7/F8/F10/F11):** R3 — see docs/phase5/requirement-ledger.json (now the machine-checked source of truth for states; this section is narrative).`
- Add the new evidence bullets to the **Evidence index** section:

```markdown
- Foundation remainder (F2/F4/F6/F7/F8/F10/F11): `tests/Unit/Support/CoreVersionTest.php`,
  `tests/Unit/Security/DataClassesTest.php`, `tests/Unit/Support/SigningHarnessTest.php`,
  `tests/Integration/Core/RegistryFixturesTest.php`, `tests/Integration/Security/ReauthGateTest.php`,
  `tests/Unit/Support/LogRedactorTest.php`, `tests/Unit/Core/TelemetryRedactionTest.php`,
  `tests/Unit/Core/Phase5EvidenceMapTest.php`, `tests/Unit/Core/ThreatModelIndexTest.php`,
  `AppFeatureFlagTest::test_core_forum_survives_with_every_feature_flag_disabled`.
- Requirement ledger: `docs/phase5/requirement-ledger.json` (R0–R5 states + per-flag rollback map, machine-checked).
- Threat models: `docs/phase5/threat-models/` (6 dossiers + `fixtures.json`, 48 negative-fixture stubs; TM-SE-01 already implemented).
```

- [ ] **Step 4: Annotate the program plan §B rows**

In `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` §B, append to each row (mirroring the F1/F9 row style, real date):

- **F2**: `**Landed 2026-0M-DD** → App::CORE_VERSION ('0.5.0-dev') + src/Support/CoreVersion.php.`
- **F4**: `**Landed 2026-0M-DD** → src/Security/DataClasses.php (10 classes; §5 #8 high-risk set).`
- **F6**: `**Landed 2026-0M-DD** → tests/Support/Phase5/{SigningHarness,RegistryFixtures}.php + ext-sodium; rb-*.v1 signed-doc contract.`
- **F7**: `**Landed 2026-0M-DD** → src/Security/ReauthGate.php; five services consolidated, behavior-preserving.`
- **F8**: `**Landed 2026-0M-DD** → src/Core/Telemetry.php + src/Support/LogRedactor.php; kernel http.request emit, dark by default.`
- **F10**: `**Landed 2026-0M-DD** → docs/phase5/requirement-ledger.json + Phase5EvidenceMapTest + all-flags-off regression.`
- **F11**: `**Landed 2026-0M-DD** → docs/phase5/threat-models/ (6 dossiers + fixtures.json, 48 stubs), recorded pending owner review.`

Also update the **Foundation exit gate** paragraph at the end of §B: append `— **Exit gate met 2026-0M-DD** (pending F11 owner review sign-off).`

- [ ] **Step 5: Commit**

```bash
git add docs/phase5/requirement-ledger.json PHASE_5_STATUS.md docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "docs(phase5): record Foundation remainder landed — exit gate met (F2/F4/F6/F7/F8/F10/F11)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Self-Review vs spec (program plan §B)

- **F2 coverage:** `App::CORE_VERSION` constant + comparison surface + boundary tests (Task 1). The §B row's "CompatibilityResolverTest" evidence name is satisfied by `CoreVersionTest` — §10.3 explicitly allows different names; the registry-aware resolver itself is Inc 2 SP3 and consumes `CoreVersion::satisfies()`. ✔
- **F4 coverage:** catalogue + consent vocabulary mirroring `ApiScopes`/`WebhookEvents`/`CapabilityCatalog`; §5 #8's six high-risk classes pinned by test; `protected ⇔ ¬grantable ⇔ null consent` mirrors the F3 invariant (Task 2). ✔
- **F6 coverage:** Ed25519 tooling minting signed snapshots, releases, advisories, rotations + tampered/expired variants (Task 3); dev/test-only trust-root + first-registry seed with production roots out-of-band (Task 4); the `rb-*.v1` signed-doc contract is written down for Inc 2 SP2. Revoked-key behavior is exercised via `registry_trust_keys.status` fixtures (TM-SC-03) once the Inc-2 verifier exists — the harness provides the keys and docs. ✔
- **F7 coverage:** one class owning the factor/window policy; all five named services consolidated; behavior-preserving (messages pinned by test); passkey-factor seam documented for Inc 7 (Tasks 5–6). Window explicitly = present-factor-per-request, matching every accepted call site — a session-window variant is deliberately deferred to the first increment that needs one (YAGNI, recorded in the class docblock). ✔
- **F8 coverage:** `Telemetry` (config-gated correlation IDs) + `LogRedactor` + `TelemetryRedactionTest` proving secrets/challenges/tokens/PII/private content never logged (Tasks 7–8); kernel emits `http.request` so the seam is exercised, dark by default; workstreams emit at build time per §B-F8. ✔
- **F10 coverage:** machine-checkable ledger with R0–R5 states, §14 Gate A DoD coverage, evidence-existence enforcement, per-flag rollback map (Task 9); `AppFeatureFlagTest` extension proves core survives all-flags-off (Task 10); states bumped only with landed evidence (Task 12). ✔
- **F11 coverage:** six reviewed-posture dossiers matching §B-F11's exact list (extension/supply-chain, identity/ATO, privilege/role-escalation, theme-phishing, secret-handling, invitation-privilege), each threat traced to §9/§12 and producing a stub in `fixtures.json`; parity enforced by `ThreatModelIndexTest`; TM-SE-01 lands already-implemented via Task 8. ✔
- **Foundation exit gate:** Task 12 runs the full suite twice (fresh + reused schema), re-proves flags dark, bumps the ledger, and records the landing in `PHASE_5_STATUS.md` + program plan §B. F3/F5/F9/F1 were already landed; with this plan the §B table is complete. ✔
- **Placeholder scan:** no TBD/TODO/"add validation"/"similar to Task N"; every created file's full content is in its step; the two date placeholders (`2026-0M-DD`) are explicit "use the real date" instructions, mirroring the F9 plan's convention. ✔
- **Type consistency:** `ReauthGate::requirePassword(User, string, string, ?string)` used identically in Tasks 5/6; `SigningHarness` return shapes match between Tasks 3/4; `LogRedactor::REDACTED` referenced in Tasks 7/8; ledger/fixture schemas identical between the JSON (Tasks 9/11) and their validators. ✔

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-01-phase5-foundation-remainder.md`. Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration (`superpowers:subagent-driven-development`).
2. **Inline Execution** — execute tasks in this session with checkpoints (`superpowers:executing-plans`).
