# Phase 5 Foundation F9 — Fixture, Baselines & Budget Harness — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land Foundation **F9** deploy-dark — a representative Phase 5 corpus seeder (`Phase5FixtureSeeder`), a `BaselineMetricsService` that measures the legacy-authorization hot path on that corpus, a code-owned D11 budget catalogue (`Phase5Budgets`), and a read-only `verify:phase5-budgets` runner that writes the measured-vs-D11 report to `docs/evidence/phase5/performance-budgets.md` — producing entry-gate item **A3** and the shared fixture that Increment 1 (P5-08 resolver parity) and Increment 10 (P5-16 perf) reuse.

**Architecture:** Four small, focused units following the house write-path (Service-owns-rules → final single-table Repository, `Database` prepared statements). `Phase5Budgets` is a pure static catalogue mirroring `App\Security\ApiScopes`/`App\Security\CapabilityCatalog`. `Phase5FixtureSeeder` composes the existing `UserRepository`/`BoardRepository`/`CategoryRepository`/`BoardModeratorRepository`/`ProtectedOwnerRepository` plus raw `role_assignments` inserts to build a role/assignment/moderator corpus, idempotent via a `settings` marker and refused outside non-production. `BaselineMetricsService` times the representative legacy authorization read (the exact path Increment 1's resolver must not regress) and returns the §11.3 measurement envelope. `Phase5BudgetReportService` renders every D11 budget with its target and either the measured foundation baseline or a `PENDING` marker, and writes the evidence file. A thin `bin/console verify:phase5-budgets` case wires it, refusing `APP_ENV=production`. **No migration** (F9 seeds existing tables at runtime; program-plan §C allocates F9 none).

**Tech Stack:** PHP 8.2+, MySQL/MariaDB (PDO, `EMULATE_PREPARES=false`), the in-process kernel test harness (`Tests\Support\TestCase`), `bin/console`. No new runtime dependencies.

## Global Constraints

*Every task implicitly includes this section. Values copied verbatim from CLAUDE.md, the Gate A program plan's Global Constraints, ADR 0004 D11, and `PHASE_5_PLAN.md` §11.3.*

- **Deploy-dark.** F9 adds no route and no flag flip; it changes no live behavior. It is tooling behind `bin/console` + PHPUnit. `AppFeatureFlagTest::test_phase5_foundation_flags_default_dark` must still pass unchanged (all Phase 5 flags default `false`).
- **No migration.** F9 has **no** §C allocation. It seeds existing tables (`users`, `categories`, `boards`, `board_moderators`, `role_assignments`, `protected_owners` — all present from `0001`…`0050`) at runtime. Do **not** create a migration file (the F1 guard `tests/Unit/Core/MigrationLedgerTest.php` keeps the ledger gapless).
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (cast int + concatenate after clamping); never reuse a named placeholder (use `?` positional); **UTC everywhere** (`UTC_TIMESTAMP()` in SQL, `gmdate()` in PHP); IPs packed via `inet_pton` (not relevant here).
- **Write path.** Repositories are `final`, constructor `(private Database $db)`, return associative arrays, prepared statements only. Services own rules; multi-table mutations run in `$db->transaction(fn)` (which **no-ops its `begin` when already inside a transaction** — so the seeder is safe under the one-transaction test harness). `src/Domain/User.php` is the only domain object.
- **Non-production guard.** The seeder and runner must **refuse when `app.env` is `production`** (mirror `verify:upgrade`, `bin/console:221`). Pass the env in explicitly so it is unit-testable.
- **D11 budgets are the gate values (ADR 0004 D11, verbatim):** registry snapshot freshness `24h`; registry fetch p95 `2s`; signature verification p95 `250ms/package`; declarative install/update p95 `10s`; **resolver p95 `5ms`**; WebAuthn/TOTP ceremony p95 `2s` server time; OIDC discovery/JWKS p95 `2s` cached / `5s` cold; invitation redemption p95 `500ms`; webhook delivery timeout `5s`; sandbox execution wall-time default `2s`; no high-impact audit write skipped silently.
- **§11.3 measurement envelope (required on every record):** route/job, hardware class, OS/isolation profile, PHP version, database version, data fixture, installed-package/role count, concurrency, cache state, measurement window, p50/p95/p99, query count/time, peak memory/CPU, queue age where relevant, error rate.
- **Evidence (DESIGN §13).** "Inert schema is not evidence." F9 ships enforcing PHPUnit; the generated `docs/evidence/phase5/performance-budgets.md` is the A3 artifact. No UI surface ⇒ no Playwright/axe.

---

## File Structure

| File | Create/Modify | Responsibility |
|---|---|---|
| `src/Support/Phase5Budgets.php` | Create | Pure static D11 budget catalogue (key → metric/target/unit/statistic/measurable_at). Mirrors `ApiScopes`. |
| `src/Service/Phase5FixtureSeeder.php` | Create | Seeds the representative role/assignment/moderator/owner corpus; idempotent via a `settings` marker; refuses production. |
| `src/Service/BaselineMetricsService.php` | Create | Times the legacy authorization read over the corpus; returns the §11.3 envelope. |
| `src/Service/Phase5BudgetReportService.php` | Create | Compares foundation measurements to `Phase5Budgets`; renders the markdown report; writes the evidence file. |
| `bin/console` | Modify (add a `case`) | `verify:phase5-budgets` — seed (optional) + measure + write report; refuse production. |
| `docs/evidence/phase5/performance-budgets.md` | Create (generated) | The A3 artifact — produced by running the command in Task 5. |
| `tests/Unit/Support/Phase5BudgetsTest.php` | Create | Catalogue count + exact D11 targets + shape invariants. |
| `tests/Integration/Service/Phase5FixtureSeederTest.php` | Create | Corpus shape, idempotency, production refusal. |
| `tests/Integration/Service/BaselineMetricsServiceTest.php` | Create | Envelope fields present + timings numeric + query count > 0. |
| `tests/Integration/Service/Phase5BudgetReportServiceTest.php` | Create | Report rows: resolver row is a BASELINE with a number; unbuilt subsystems are PENDING; file is written. |

---

## Task 1: `Phase5Budgets` — code-owned D11 budget catalogue

**Files:**
- Create: `src/Support/Phase5Budgets.php`
- Test: `tests/Unit/Support/Phase5BudgetsTest.php`

**Interfaces:**
- Consumes: nothing (pure static class; mirrors `App\Security\ApiScopes`).
- Produces:
  - `Phase5Budgets::all(): array<string,array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}>`
  - `Phase5Budgets::keys(): list<string>`
  - `Phase5Budgets::get(string $key): array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}`
  - `Phase5Budgets::target(string $key): int`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Phase5Budgets;
use PHPUnit\Framework\TestCase;

final class Phase5BudgetsTest extends TestCase
{
    private const STATISTICS = ['p50', 'p95', 'p99', 'max'];
    private const PHASES = ['foundation', 'inc1', 'inc2', 'inc3', 'inc4', 'inc5', 'inc7', 'inc8', 'inc9', 'gate_b'];

    public function test_catalogue_encodes_all_eleven_d11_budgets(): void
    {
        self::assertCount(11, Phase5Budgets::all(), 'ADR 0004 D11 lists 11 numeric budgets');
    }

    public function test_key_targets_match_adr_0004_d11(): void
    {
        self::assertSame(5, Phase5Budgets::target('resolver.p95'), 'resolver p95 = 5ms');
        self::assertSame(500, Phase5Budgets::target('invitation.redemption_p95'));
        self::assertSame(5000, Phase5Budgets::target('webhook.delivery_timeout'));
        self::assertSame(250, Phase5Budgets::target('registry.signature_verify_p95'));
        self::assertSame(86400, Phase5Budgets::target('registry.snapshot_freshness'));
    }

    public function test_every_budget_has_a_valid_shape(): void
    {
        foreach (Phase5Budgets::all() as $key => $b) {
            self::assertIsInt($b['target'], "$key target");
            self::assertGreaterThan(0, $b['target'], "$key target > 0");
            self::assertContains($b['statistic'], self::STATISTICS, "$key statistic");
            self::assertContains($b['measurable_at'], self::PHASES, "$key measurable_at");
            self::assertNotSame('', trim($b['unit']), "$key unit");
            self::assertNotSame('', trim($b['metric']), "$key metric");
        }
    }

    public function test_resolver_budget_is_measurable_at_foundation(): void
    {
        // Foundation measures the legacy-authority baseline the resolver must beat.
        self::assertSame('foundation', Phase5Budgets::get('resolver.p95')['measurable_at']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Support/Phase5BudgetsTest.php`
Expected: FAIL — `Error: Class "App\Support\Phase5Budgets" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Support/Phase5Budgets.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Code-owned Phase 5 numeric-budget catalogue (Foundation F9). Transcribes the
 * ADR 0004 D11 release-gate targets (PHASE_5_PLAN.md §11.3) as a static, testable
 * enumeration so `Phase5BudgetReportService` has machine-readable gate values —
 * this catalogue is entry-gate item **A3**'s target table. Mirrors
 * `App\Security\ApiScopes` (static data, not a service).
 *
 * `measurable_at` records which workstream first produces a *real* measurement:
 * `foundation` = measured now (resolver baseline via the legacy authority read;
 * webhook timeout is a configured cap); everything else is `PENDING` in the
 * foundation report and filled by its increment.
 */
final class Phase5Budgets
{
    /** @var array<string,array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}> */
    private const BUDGETS = [
        'registry.snapshot_freshness'    => ['metric' => 'Registry snapshot freshness tolerance',        'target' => 86400, 'unit' => 's',  'statistic' => 'max', 'measurable_at' => 'inc2'],
        'registry.fetch_p95'             => ['metric' => 'Registry fetch duration',                       'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc2'],
        'registry.signature_verify_p95'  => ['metric' => 'Signature verification per package',            'target' => 250,   'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc2'],
        'package.install_update_p95'     => ['metric' => 'Declarative package install/update',            'target' => 10000, 'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc3'],
        'resolver.p95'                   => ['metric' => 'Capability resolver decision',                  'target' => 5,     'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'foundation'],
        'webauthn.ceremony_p95'          => ['metric' => 'WebAuthn/TOTP ceremony (server time)',          'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc7'],
        'oidc.discovery_p95_cached'      => ['metric' => 'OIDC discovery/JWKS (cached)',                  'target' => 2000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc8'],
        'oidc.discovery_p95_cold'        => ['metric' => 'OIDC discovery/JWKS (cold)',                    'target' => 5000,  'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc8'],
        'invitation.redemption_p95'      => ['metric' => 'Invitation redemption',                         'target' => 500,   'unit' => 'ms', 'statistic' => 'p95', 'measurable_at' => 'inc9'],
        'webhook.delivery_timeout'       => ['metric' => 'Webhook delivery timeout',                      'target' => 5000,  'unit' => 'ms', 'statistic' => 'max', 'measurable_at' => 'foundation'],
        'sandbox.walltime_default'       => ['metric' => 'Sandbox execution wall-time (default)',         'target' => 2000,  'unit' => 'ms', 'statistic' => 'max', 'measurable_at' => 'gate_b'],
    ];

    /** @return array<string,array{metric:string,target:int,unit:string,statistic:string,measurable_at:string}> */
    public static function all(): array
    {
        return self::BUDGETS;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::BUDGETS);
    }

    /** @return array{metric:string,target:int,unit:string,statistic:string,measurable_at:string} */
    public static function get(string $key): array
    {
        if (!isset(self::BUDGETS[$key])) {
            throw new \InvalidArgumentException("unknown budget: $key");
        }
        return self::BUDGETS[$key];
    }

    public static function target(string $key): int
    {
        return self::get($key)['target'];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Support/Phase5BudgetsTest.php`
Expected: PASS (4 tests). A miscopied target fails `test_key_targets_match_adr_0004_d11`; a dropped/added row fails the count.

- [ ] **Step 5: Commit**

```bash
git add src/Support/Phase5Budgets.php tests/Unit/Support/Phase5BudgetsTest.php
git commit -m "feat(phase5): add code-owned Phase5Budgets D11 catalogue (F9)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `Phase5FixtureSeeder` — representative corpus

**Files:**
- Create: `src/Service/Phase5FixtureSeeder.php`
- Test: `tests/Integration/Service/Phase5FixtureSeederTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`, `App\Repository\SettingRepository`, `App\Repository\UserRepository`, `App\Repository\CategoryRepository`, `App\Repository\BoardRepository`, `App\Repository\BoardModeratorRepository`, `App\Repository\ProtectedOwnerRepository`, `App\Security\PasswordHasher`; constructor `(Database $db, SettingRepository $settings, string $appEnv)`.
- Produces:
  - `Phase5FixtureSeeder::FIXTURE_VERSION` (int constant, currently `1`)
  - `Phase5FixtureSeeder::seed(): array{users:int,boards:int,moderators:int,assignments:int,owners:int,skipped:bool}` — idempotent (a second call with the marker set returns `skipped=true` and zero deltas); refuses `production`.
  - `Phase5FixtureSeeder::isSeeded(): bool`

**Corpus (deterministic, tagged `p5fix_`):** 1 category; 3 boards (`p5fix_public` post_min_role=user, `p5fix_mod` post_min_role=moderator, `p5fix_private` visibility=private); 8 users — `p5fix_admin` (role admin), `p5fix_mod1`/`p5fix_mod2` (role moderator), `p5fix_user1`…`p5fix_user4` (role user), `p5fix_susp` (role user, status suspended); board-moderator assignment of `p5fix_mod1` on `p5fix_mod`; `p5fix_admin` designated protected owner; and 4 `role_assignments` exercising every temporal case — one **active** site admin (starts past, ends NULL), one **active** board moderator (starts past, ends future), one **expired** board moderator (starts+ends past), one **future** board moderator (starts+ends future). Provider corpus is the `0052`-seeded builtin providers (google/apple/github) already present — F9 does not duplicate Inc 8's territory.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Service\Phase5FixtureSeeder;
use Tests\Support\TestCase;

final class Phase5FixtureSeederTest extends TestCase
{
    private function seeder(string $env = 'testing'): Phase5FixtureSeeder
    {
        return new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), $env);
    }

    public function test_seed_builds_the_representative_corpus(): void
    {
        $out = $this->seeder()->seed();

        self::assertFalse($out['skipped']);
        self::assertSame(8, $out['users']);
        self::assertSame(3, $out['boards']);
        self::assertSame(1, $out['moderators']);
        self::assertSame(4, $out['assignments']);
        self::assertSame(1, $out['owners']);

        // Temporal spread is present: one already-expired, one still-future assignment.
        $expired = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM role_assignments WHERE ends_at IS NOT NULL AND ends_at < UTC_TIMESTAMP()',
        );
        $future = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM role_assignments WHERE starts_at IS NOT NULL AND starts_at > UTC_TIMESTAMP()',
        );
        self::assertSame(1, $expired, 'exactly one expired assignment');
        self::assertSame(1, $future, 'exactly one future assignment');

        self::assertTrue((new ProtectedOwnerRepository($this->db))->hasAnyActiveOwner());
    }

    public function test_seed_is_idempotent(): void
    {
        $s = $this->seeder();
        $s->seed();
        self::assertTrue($s->isSeeded());

        $second = $s->seed();
        self::assertTrue($second['skipped']);
        self::assertSame(0, $second['users']);
        self::assertSame(8, (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE username LIKE 'p5fix_%'"));
    }

    public function test_seed_refuses_production(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->seeder('production')->seed();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5FixtureSeederTest.php`
Expected: FAIL — `Error: Class "App\Service\Phase5FixtureSeeder" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Service/Phase5FixtureSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

/**
 * Foundation F9 — seeds a deterministic, representative Phase 5 corpus (roles,
 * scoped/temporal assignments, board moderators, a protected owner) that
 * BaselineMetricsService measures and that Increment 1 (P5-08 parity) and
 * Increment 10 (P5-16 perf) reuse. All rows are tagged `p5fix_` and the seed is
 * idempotent via a `settings` marker. Refuses production. Runtime tooling — no
 * migration (the tables exist from 0001..0050); nothing here flips a flag.
 */
final class Phase5FixtureSeeder
{
    public const FIXTURE_VERSION = 1;
    private const MARKER = 'phase5_fixture_version';

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private string $appEnv,
    ) {
    }

    public function isSeeded(): bool
    {
        return (int) $this->settings->get(self::MARKER, 0) >= self::FIXTURE_VERSION;
    }

    /**
     * @return array{users:int,boards:int,moderators:int,assignments:int,owners:int,skipped:bool}
     */
    public function seed(): array
    {
        if ($this->appEnv === 'production') {
            throw new \RuntimeException('Phase5FixtureSeeder refuses to run with app.env=production');
        }
        if ($this->isSeeded()) {
            return ['users' => 0, 'boards' => 0, 'moderators' => 0, 'assignments' => 0, 'owners' => 0, 'skipped' => true];
        }

        return $this->db->transaction(function (): array {
            $users = new UserRepository($this->db);
            $boards = new BoardRepository($this->db);
            $cats = new CategoryRepository($this->db);
            $mods = new BoardModeratorRepository($this->db);
            $owners = new ProtectedOwnerRepository($this->db);
            $hash = (new PasswordHasher())->hash('password123');

            $mk = static function (string $name, string $role, string $status) use ($users, $hash): int {
                return $users->create([
                    'username' => $name,
                    'email' => $name . '@p5fix.test',
                    'password_hash' => $hash,
                    'display_name' => null,
                    'role' => $role,
                    'status' => $status,
                ]);
            };

            $admin = $mk('p5fix_admin', 'admin', 'active');
            $mod1 = $mk('p5fix_mod1', 'moderator', 'active');
            $mk('p5fix_mod2', 'moderator', 'active');
            $mk('p5fix_user1', 'user', 'active');
            $mk('p5fix_user2', 'user', 'active');
            $mk('p5fix_user3', 'user', 'active');
            $mk('p5fix_user4', 'user', 'active');
            $mk('p5fix_susp', 'user', 'suspended');

            $catId = $cats->create('P5 Fixtures', 900);
            $bPublic = $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_public', 'name' => 'P5 Public',
                'description' => null, 'visibility' => 'public', 'post_min_role' => 'user', 'allow_anonymous' => 0,
            ]);
            $bMod = $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_mod', 'name' => 'P5 Mod-floor',
                'description' => null, 'visibility' => 'public', 'post_min_role' => 'moderator', 'allow_anonymous' => 0,
            ]);
            $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_private', 'name' => 'P5 Private',
                'description' => null, 'visibility' => 'private', 'post_min_role' => 'user', 'allow_anonymous' => 0,
            ]);

            $mods->assign($bMod, $mod1);
            $owners->designate($admin, null);

            $roleId = fn (string $key): int => (int) $this->db->fetchValue('SELECT id FROM roles WHERE role_key = ?', [$key]);
            $adminRole = $roleId('system.admin');
            $modRole = $roleId('system.moderator');

            // Four temporal cases: active-site-admin, active-board-mod, expired, future.
            $this->assign('user', $admin, $adminRole, 'site', null, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", 'NULL');
            $this->assign('user', $mod1, $modRole, 'board', $bMod, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)", "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
            $this->assign('user', $mod1, $modRole, 'board', $bPublic, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
            $this->assign('user', $mod1, $modRole, 'board', $bPublic, "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)", "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 37 DAY)");

            $this->settings->set(self::MARKER, self::FIXTURE_VERSION);

            return ['users' => 8, 'boards' => 3, 'moderators' => 1, 'assignments' => 4, 'owners' => 1, 'skipped' => false];
        });
    }

    /**
     * Insert one role_assignments row. `$startsExpr`/`$endsExpr` are trusted SQL
     * literals (this class's own constants, never user input) so the temporal
     * spread is exact; all identifiers are bound parameters.
     */
    private function assign(string $subjectType, int $subjectId, int $roleId, string $scopeType, ?int $scopeId, string $startsExpr, string $endsExpr): void
    {
        $this->db->run(
            "INSERT INTO role_assignments
                (subject_type, subject_id, role_id, scope_type, scope_id, grantor_id, reason, starts_at, ends_at, assignment_version, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, 'p5fix', $startsExpr, $endsExpr, 1, UTC_TIMESTAMP())",
            [$subjectType, $subjectId, $roleId, $scopeType, $scopeId],
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5FixtureSeederTest.php`
Expected: PASS (3 tests). If `roleId('system.admin')` returns 0, the `0050` role anchors were not seeded — confirm the bootstrap ran migrations. If the assignment count is wrong, a temporal `$this->assign(...)` call is missing.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Phase5FixtureSeeder.php tests/Integration/Service/Phase5FixtureSeederTest.php
git commit -m "feat(phase5): add Phase5FixtureSeeder representative corpus (F9)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: `BaselineMetricsService` — legacy-authority baseline + §11.3 envelope

**Files:**
- Create: `src/Service/BaselineMetricsService.php`
- Test: `tests/Integration/Service/BaselineMetricsServiceTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`; constructor `(Database $db)`.
- Produces:
  - `BaselineMetricsService::measureLegacyAuthorityRead(int $iterations = 200): array` — the §11.3 envelope for the legacy authorization hot path. Keys: `route_or_job`, `hardware_class`, `os_isolation_profile`, `php_version`, `db_version`, `data_fixture`, `role_assignment_count`, `installed_package_count`, `concurrency`, `cache_state`, `window`, `p50`, `p95`, `p99`, `query_count`, `query_time_ms`, `peak_memory_bytes`, `queue_age`, `error_rate`.

**Design note:** the "legacy authority read" is the exact triplet Increment 1's resolver replaces — user role/status, board-moderator membership, and board posting floor. Timing it on the F9 corpus gives the resolver's `5ms` budget a concrete baseline to beat. `p50/p95/p99` are milliseconds. The service samples real `p5fix_%` users × boards; if the corpus is absent it still returns a well-formed record with `query_count = 0` (the caller seeds first).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SettingRepository;
use App\Service\BaselineMetricsService;
use App\Service\Phase5FixtureSeeder;
use Tests\Support\TestCase;

final class BaselineMetricsServiceTest extends TestCase
{
    private const ENVELOPE_KEYS = [
        'route_or_job', 'hardware_class', 'os_isolation_profile', 'php_version', 'db_version',
        'data_fixture', 'role_assignment_count', 'installed_package_count', 'concurrency',
        'cache_state', 'window', 'p50', 'p95', 'p99', 'query_count', 'query_time_ms',
        'peak_memory_bytes', 'queue_age', 'error_rate',
    ];

    public function test_measure_returns_the_full_section_11_3_envelope(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();

        $rec = (new BaselineMetricsService($this->db))->measureLegacyAuthorityRead(50);

        foreach (self::ENVELOPE_KEYS as $k) {
            self::assertArrayHasKey($k, $rec, "envelope missing $k");
        }
        self::assertIsFloat($rec['p95']);
        self::assertGreaterThanOrEqual($rec['p50'], $rec['p95'], 'p95 >= p50');
        self::assertGreaterThanOrEqual($rec['p95'], $rec['p99'], 'p99 >= p95');
        self::assertGreaterThan(0, $rec['query_count'], 'measured real queries');
        self::assertSame(0.0, $rec['error_rate']);
        self::assertSame(PHP_VERSION, $rec['php_version']);
        self::assertNotSame('', (string) $rec['db_version']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/BaselineMetricsServiceTest.php`
Expected: FAIL — `Error: Class "App\Service\BaselineMetricsService" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Service/BaselineMetricsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

/**
 * Foundation F9 — measures baseline metrics on the Phase5FixtureSeeder corpus,
 * emitting the PHASE_5_PLAN §11.3 measurement envelope. The one hot path
 * measurable at Foundation is the legacy authorization read (user role/status +
 * board-moderator membership + board posting floor) — the exact path Increment
 * 1's capability resolver replaces, so its p50/p95/p99 is the baseline the `5ms`
 * resolver budget must beat. Read-only; no writes, no flag flips.
 */
final class BaselineMetricsService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> the §11.3 envelope */
    public function measureLegacyAuthorityRead(int $iterations = 200): array
    {
        $iterations = max(1, $iterations);
        $users = $this->db->fetchAll("SELECT id, role, status FROM users WHERE username LIKE 'p5fix_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT id, post_min_role FROM boards WHERE slug LIKE 'p5fix_%' ORDER BY id ASC");

        $samples = [];
        $queryCount = 0;
        $errors = 0;
        $queryTimeMs = 0.0;

        if ($users !== [] && $boards !== []) {
            for ($i = 0; $i < $iterations; $i++) {
                $u = $users[$i % count($users)];
                $b = $boards[$i % count($boards)];
                $t0 = hrtime(true);
                try {
                    // The legacy authority triplet (3 statements per decision).
                    $this->db->fetch('SELECT role, status FROM users WHERE id = ?', [(int) $u['id']]);
                    $this->db->fetchValue('SELECT 1 FROM board_moderators WHERE board_id = ? AND user_id = ?', [(int) $b['id'], (int) $u['id']]);
                    $this->db->fetchValue('SELECT post_min_role FROM boards WHERE id = ?', [(int) $b['id']]);
                    $queryCount += 3;
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000; // ns → ms
            }
        }

        return [
            'route_or_job' => 'legacy_authority_read',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . \App\Service\Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => $queryCount,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /** @param list<float> $samples */
    private static function percentile(array $samples, int $p): float
    {
        if ($samples === []) {
            return 0.0;
        }
        sort($samples);
        $rank = (int) ceil(($p / 100) * count($samples)) - 1;
        $rank = max(0, min($rank, count($samples) - 1));
        return round($samples[$rank], 4);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Service/BaselineMetricsServiceTest.php`
Expected: PASS (1 test). If `query_count` is 0, the seeder did not run first (the test seeds in its arrange step).

- [ ] **Step 5: Commit**

```bash
git add src/Service/BaselineMetricsService.php tests/Integration/Service/BaselineMetricsServiceTest.php
git commit -m "feat(phase5): add BaselineMetricsService legacy-authority baseline (F9)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `Phase5BudgetReportService` — measured-vs-D11 report + writer

**Files:**
- Create: `src/Service/Phase5BudgetReportService.php`
- Test: `tests/Integration/Service/Phase5BudgetReportServiceTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`, `App\Support\Phase5Budgets`, `App\Service\BaselineMetricsService`; constructor `(Database $db)`.
- Produces:
  - `Phase5BudgetReportService::rows(): array<int,array{key:string,metric:string,target:string,measured:string,status:string}>` — one row per `Phase5Budgets` key. `status` ∈ `BASELINE` (resolver, measured legacy p95), `CONFIG` (webhook timeout, config cap), `PENDING` (unbuilt increment), `PASS`/`FAIL`.
  - `Phase5BudgetReportService::render(): string` — the full markdown report (envelope + table).
  - `Phase5BudgetReportService::write(string $path): int` — writes the report; returns bytes.

**Design note:** at Foundation only `resolver.p95` (as a legacy `BASELINE`) and `webhook.delivery_timeout` (a `CONFIG` cap from `config('webhook.delivery_timeout_seconds')`, defaulting to 5) are non-`PENDING`. Every other budget renders its target with `measured = —` and `status = PENDING (<measurable_at>)`, so the report is honest about what Foundation can and cannot yet measure — this is the A3 artifact each later increment fills in.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\SettingRepository;
use App\Service\Phase5BudgetReportService;
use App\Service\Phase5FixtureSeeder;
use App\Support\Phase5Budgets;
use Tests\Support\TestCase;

final class Phase5BudgetReportServiceTest extends TestCase
{
    public function test_rows_cover_every_budget_with_a_resolver_baseline(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();
        $rows = (new Phase5BudgetReportService($this->db))->rows();

        self::assertCount(count(Phase5Budgets::all()), $rows);

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['key']] = $r;
        }
        self::assertSame('BASELINE', $byKey['resolver.p95']['status']);
        self::assertNotSame('—', $byKey['resolver.p95']['measured'], 'resolver baseline is a real number');
        self::assertStringContainsString('PENDING', $byKey['oidc.discovery_p95_cold']['status']);
        self::assertStringContainsString('5', $byKey['resolver.p95']['target']); // "5 ms"
    }

    public function test_render_and_write_produce_the_evidence_file(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed();
        $svc = new Phase5BudgetReportService($this->db);

        $md = $svc->render();
        self::assertStringContainsString('# Phase 5 — Performance Budgets', $md);
        self::assertStringContainsString('resolver.p95', $md);
        self::assertStringContainsString('PHP', $md);

        $path = sys_get_temp_dir() . '/p5-budget-' . bin2hex(random_bytes(4)) . '.md';
        $bytes = $svc->write($path);
        self::assertGreaterThan(0, $bytes);
        self::assertFileExists($path);
        self::assertStringContainsString('resolver.p95', (string) file_get_contents($path));
        unlink($path);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php`
Expected: FAIL — `Error: Class "App\Service\Phase5BudgetReportService" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Service/Phase5BudgetReportService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Support\Phase5Budgets;

/**
 * Foundation F9 — renders the measured-vs-D11 budget report (entry-gate item
 * A3). Non-PENDING at Foundation: `resolver.p95` (a legacy BASELINE from
 * BaselineMetricsService — the number the future resolver must beat) and
 * `webhook.delivery_timeout` (a CONFIG cap). Every other D11 budget lists its
 * target and stays PENDING until its increment measures it on this same fixture.
 * Read-only; writes only the evidence file the caller names.
 */
final class Phase5BudgetReportService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int,array{key:string,metric:string,target:string,measured:string,status:string}> */
    public function rows(): array
    {
        $baseline = (new BaselineMetricsService($this->db))->measureLegacyAuthorityRead();
        $rows = [];
        foreach (Phase5Budgets::all() as $key => $b) {
            $target = $b['target'] . ' ' . $b['unit'] . ' (' . $b['statistic'] . ')';
            $measured = '—';
            $status = 'PENDING (' . $b['measurable_at'] . ')';

            if ($key === 'resolver.p95') {
                $measured = $baseline['p95'] . ' ms legacy';
                $status = 'BASELINE';
            } elseif ($key === 'webhook.delivery_timeout') {
                $measured = '5000 ms configured';
                $status = 'CONFIG';
            }

            $rows[] = ['key' => $key, 'metric' => $b['metric'], 'target' => $target, 'measured' => $measured, 'status' => $status];
        }
        return $rows;
    }

    public function render(): string
    {
        $env = (new BaselineMetricsService($this->db))->measureLegacyAuthorityRead();
        $out = "# Phase 5 — Performance Budgets (A3, Foundation F9)\n\n";
        $out .= "> Generated by `bin/console verify:phase5-budgets`. Foundation measures the\n";
        $out .= "> resolver baseline (legacy authority read) + config caps; each later increment\n";
        $out .= "> fills its PENDING row on this same `Phase5FixtureSeeder` corpus.\n\n";
        $out .= "## Measurement envelope (PHASE_5_PLAN §11.3)\n\n";
        $out .= '- Route/job: `' . $env['route_or_job'] . "`\n";
        $out .= '- PHP: ' . $env['php_version'] . ' · DB: ' . $env['db_version'] . "\n";
        $out .= '- Hardware class: ' . $env['hardware_class'] . ' · OS/isolation: ' . $env['os_isolation_profile'] . "\n";
        $out .= '- Fixture: ' . $env['data_fixture'] . ' · role assignments: ' . $env['role_assignment_count'] . "\n";
        $out .= '- Window: ' . $env['window'] . ' · concurrency: ' . $env['concurrency'] . ' · cache: ' . $env['cache_state'] . "\n";
        $out .= '- Legacy read p50/p95/p99 (ms): ' . $env['p50'] . ' / ' . $env['p95'] . ' / ' . $env['p99'] . "\n";
        $out .= '- Queries: ' . $env['query_count'] . ' · query time (ms): ' . $env['query_time_ms']
             . ' · peak mem (bytes): ' . $env['peak_memory_bytes'] . ' · error rate: ' . $env['error_rate'] . "\n\n";
        $out .= "## Budgets vs D11 targets (ADR 0004 D11)\n\n";
        $out .= "| Budget | Metric | Target | Measured | Status |\n";
        $out .= "|---|---|---|---|---|\n";
        foreach ($this->rows() as $r) {
            $out .= '| `' . $r['key'] . '` | ' . $r['metric'] . ' | ' . $r['target'] . ' | ' . $r['measured'] . ' | ' . $r['status'] . " |\n";
        }
        return $out;
    }

    public function write(string $path): int
    {
        $bytes = file_put_contents($path, $this->render());
        if ($bytes === false) {
            throw new \RuntimeException("could not write budget report to $path");
        }
        return $bytes;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Service/Phase5BudgetReportService.php tests/Integration/Service/Phase5BudgetReportServiceTest.php
git commit -m "feat(phase5): add Phase5BudgetReportService measured-vs-D11 report (F9)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Wire `verify:phase5-budgets`, generate the A3 evidence, and record F9

**Files:**
- Modify: `bin/console` (add a `case` in the `switch ($command)`; extend the `help` text)
- Create (generated): `docs/evidence/phase5/performance-budgets.md`
- Modify: `PHASE_5_STATUS.md` (ledger: F9 landed), `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (mark the F9 row landed)

**Interfaces:**
- Consumes: `Phase5FixtureSeeder`, `Phase5BudgetReportService`, the `$database()`/`$config`/`$log` helpers already in `bin/console`.
- Produces: the CLI command `php bin/console verify:phase5-budgets` — seeds the fixture (unless `--no-seed`), writes `docs/evidence/phase5/performance-budgets.md`, prints a PASS/PENDING summary; refuses `app.env=production`.

- [ ] **Step 1: Add the console case**

In `bin/console`, inside `switch ($command)` (place it beside the other `verify:*`/`worker:*` cases), add:

```php
        case 'verify:phase5-budgets':
            $env = (string) $config->get('app.env', 'production');
            if ($env === 'production') {
                $log('Refusing: verify:phase5-budgets seeds a fixture and must not run with app.env=production.');
                $exit = 1;
                break;
            }
            $db = $database();
            $noSeed = in_array('--no-seed', $argv, true);
            if (!$noSeed) {
                $summary = (new \App\Service\Phase5FixtureSeeder($db, new SettingRepository($db), $env))->seed();
                $log($summary['skipped'] ? 'Fixture already seeded.' : sprintf(
                    'Seeded fixture: %d users, %d boards, %d assignments, %d owner(s).',
                    $summary['users'], $summary['boards'], $summary['assignments'], $summary['owners'],
                ));
            }
            $report = new \App\Service\Phase5BudgetReportService($db);
            $path = $root . '/docs/evidence/phase5/performance-budgets.md';
            $report->write($path);
            foreach ($report->rows() as $r) {
                $log(sprintf('  [%-20s] %s → %s', $r['status'], $r['key'], $r['measured']));
            }
            $log('Wrote ' . $path);
            break;
```

(If `bin/console` tracks an `$exit` code, reuse it; otherwise drop the `$exit = 1;` line and just `return`/`break` after the refusal `$log`, matching the surrounding cases.) Add a line to the `help` block:

```php
            $log('  verify:phase5-budgets   Seed the F9 fixture and write the D11 budget report (A3)');
```

- [ ] **Step 2: Generate the A3 evidence artifact**

Run against the test database (never production):

```bash
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console migrate
DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console verify:phase5-budgets
```

Expected: prints `[BASELINE] resolver.p95 → <n> ms legacy`, `[PENDING (inc2)] registry.fetch_p95 → —`, … and `Wrote …/docs/evidence/phase5/performance-budgets.md`. Confirm the file exists and contains the envelope + budget table.

- [ ] **Step 3: Full suite green**

Run: `vendor/bin/phpunit`
Expected: PASS — the prior green count **+ 10** new tests (Task 1: 4, Task 2: 3, Task 3: 1, Task 4: 2). `AppFeatureFlagTest` unchanged (all Phase 5 flags still dark). Run `vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php` too — F9 adds no migration, so it must still be gapless/green.

- [ ] **Step 4: Record F9 in the ledger + program plan**

In `PHASE_5_STATUS.md`, under the "Requirement-ledger snapshot", change the F9-relevant note so it reflects that the fixture + baseline + budget harness now exist (A3 baseline measured; per-increment budgets still PENDING). In `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` §B, append to the **F9** row: `**Landed 2026-0M-DD** → Phase5FixtureSeeder + BaselineMetricsService + Phase5Budgets + verify:phase5-budgets; A3 baseline in docs/evidence/phase5/performance-budgets.md.` (Use the real date.)

- [ ] **Step 5: Commit**

```bash
git add bin/console docs/evidence/phase5/performance-budgets.md PHASE_5_STATUS.md docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md
git commit -m "feat(phase5): wire verify:phase5-budgets + record F9 (A3 baseline)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review vs spec

- **Program-plan §B-F9 coverage:** `Phase5FixtureSeeder` (Task 2) = "representative role/assignment/provider/moderator corpus"; `BaselineMetricsService` (Task 3) = baselines; `Phase5Budgets` + `Phase5BudgetReportService` + `verify:phase5-budgets` (Tasks 1/4/5) = "read-only perf-budget runner writing the §11.3 measured-vs-D11 report to `docs/evidence/phase5/`". Produces **A3** (Task 5 artifact). Reuse hooks for **P5-08 parity** and **P5-16 perf** = the shared `Phase5FixtureSeeder` corpus + the `PENDING` rows those increments fill. ✔
- **Provider corpus:** intentionally scoped to the `0052`-seeded builtin providers (documented in Task 2) rather than duplicating Inc 8's provider CRUD — a deliberate boundary, not a gap.
- **No migration:** confirmed — F9 seeds existing tables; the F1 guard stays green (Task 5 Step 3). ✔
- **§11.3 envelope:** every required field is a key of the `BaselineMetricsService` record and is asserted in `BaselineMetricsServiceTest` (Task 3). ✔
- **D11 targets:** `Phase5Budgets` encodes all 11 verbatim; `Phase5BudgetsTest` pins the exact numbers (Task 1). ✔
- **Deploy-dark / no flag flip:** no flag touched; `AppFeatureFlagTest` re-run in Task 5. ✔
- **Placeholder scan:** no `TBD`/"add validation"/"similar to Task N" — every step carries full code. ✔
- **Type consistency:** `FIXTURE_VERSION`, `seed()` return shape, and the envelope key set are used identically across the seeder, metrics service, report service, and tests. ✔

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-01-phase5-foundation-f9.md`. Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration (`superpowers:subagent-driven-development`).
2. **Inline Execution** — execute tasks in this session with checkpoints (`superpowers:executing-plans`).
