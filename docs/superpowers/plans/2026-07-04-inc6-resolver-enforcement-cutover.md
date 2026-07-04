# Increment 6 — Resolver Enforcement Cutover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Create the isolated workspace via superpowers:using-git-worktrees before Task 0.

**Goal:** Turn the shadow-clean capability resolver into live (mode-gated) authorization behind the dark `capabilities` flag, and build the scoped-assignment lifecycle (grant/revoke/renew with grantor ceiling), the change-role owner-loss path, and the release evidence — per the approved spec `docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md` (read it first; it is the contract).

**Architecture:** One new seam (`AuthorityGate`, three modes: legacy/shadow/enforce) wraps every cutover site's existing legacy expression as a closure; the resolver decides only in enforce mode and fails closed. Legacy authority stays authoritative in its legacy tables (projection = "virtual import", no migration). New write lifecycle for `role_assignments` + a new admin change-role action wire the remaining Gate A owner-loss path.

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB, PHPUnit (strict), Playwright (browser evidence). No new dependencies.

## Global Constraints

- Branch: `phase5-inc6-enforcement` off `main`. Frequent commits, message style `feat(scope): …` / `test(scope): …` / `docs(scope): …`, each ending with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- TDD red→green: write the failing test, run it (expect FAIL), implement minimally, run again (expect PASS), commit test+code together.
- Run tests from repo root: `vendor/bin/phpunit path/to/File.php` or `--filter test_name`. The test DB must be reachable (`docker start rb-mariadb` if using the dev container; local stack per PHASE_5_STATUS operating note uses host port 3307).
- PHPUnit is strict: every test ≥1 assertion, no output, no warnings. Integration tests extend `Tests\Support\TestCase` (per-test transaction rolled back in tearDown, **no savepoints** — code that rolls back inside its own transaction does NOT undo rows; assert observable HTTP/service behavior, not row counts after inner rollbacks).
- Repositories: `final`, constructor `(private Database $db)`, prepared statements only, **never bind LIMIT/OFFSET** (int-cast + concatenate), **never reuse a named placeholder**, UTC everywhere (`UTC_TIMESTAMP()` / `gmdate()`).
- Services own business rules; every multi-table mutation inside `$db->transaction(fn)`; controllers catch `ValidationException` and re-render 422 with `->errors` + `->old` (anti-draft-loss).
- CSRF on every POST (`$this->csrfField()` in templates); escape output with `$this->e()`/`$e`; **no inline `<script>`/`<style>`** (strict CSP); admin pages `X-Robots-Tag: noindex`.
- Feature flags gate availability only; `capabilities` stays **default dark**; posture lives in config (`capabilities.mode`).
- The spec's §2 cutover table is normative: **[STATE-KEEP]** content-state rules (archived/locked/deleted/pending checks inside services) must NOT be removed or moved — only the capability-holding predicate is swapped.
- Do not edit `docs/phase5/requirement-ledger.json` states except in the task that lands the evidence (Task 17); `tests/Unit/Core/Phase5EvidenceMapTest.php` fails on overclaimed states.
- Line numbers in this plan are from `main@3ad11c7`; always locate by symbol name, not line.

---

### Task 0: Branch + config key + `.env.example`

**Files:**
- Modify: `config/config.php` (next to the `'antiabuse'` block, ~line 173)
- Modify: `.env.example`

**Interfaces:**
- Produces: `config('capabilities.mode')` returning `'shadow'|'enforce'` (default `'shadow'`).

- [ ] **Step 1: Create the branch**

```bash
git checkout -b phase5-inc6-enforcement
```

- [ ] **Step 2: Add the config key**

In `config/config.php`, directly after the `'antiabuse' => [...]` entry, add:

```php
// Phase 5 Inc 6: capability-resolver posture. Flags gate availability only
// (DECISIONS); this mode decides whether the resolver ENFORCES or only
// shadow-compares while `capabilities` is enabled. shadow|enforce.
'capabilities' => [
    'mode' => Env::get('CAPABILITIES_MODE', 'shadow'),
],
```

- [ ] **Step 3: Document in `.env.example`**

Add beside the other Phase 5 entries:

```
# Capability resolver posture while features.capabilities is enabled:
# shadow (default; legacy decides, mismatches -> telemetry) or enforce.
CAPABILITIES_MODE=shadow
```

- [ ] **Step 4: Sanity check + commit**

Run: `php -l config/config.php` → `No syntax errors`; `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php` → PASS (unchanged behavior).

```bash
git add config/config.php .env.example
git commit -m "feat(capabilities): CAPABILITIES_MODE posture config (shadow default)"
```

---

### Task 1: `AuthorityGate` — the three-mode decision seam

**Files:**
- Create: `src/Security/AuthorityGate.php`
- Test: `tests/Integration/Security/AuthorityGateTest.php`

**Interfaces:**
- Consumes: `CapabilityResolver::can(?User, string, array, ?\DateTimeImmutable): CapabilityDecision`; `ResolverShadow::compare(bool, ?User, string, array, string): void`; `Telemetry::emit(string, array): void`.
- Produces (all later cutover tasks call exactly these):
  - `AuthorityGate::MODE_LEGACY|MODE_SHADOW|MODE_ENFORCE` (string consts `'legacy'|'shadow'|'enforce'`)
  - `AuthorityGate::legacy(): self`
  - `allows(callable $legacy, ?User $actor, string $capability, array $target, string $site): bool`
  - `assert(callable $legacy, ?User $actor, string $capability, array $target, string $site, string $message): void` (throws `ForbiddenException($message)` on deny)
  - `mode(): string`

- [ ] **Step 1: Write the failing test**

`tests/Integration/Security/AuthorityGateTest.php` — construct the real resolver by hand exactly the way `src/Service/ResolverParityService` consumers do (mirror the repo list from `CapabilityResolver::__construct`):

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Core\Config;
use App\Core\ForbiddenException;
use App\Core\Telemetry;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\ResolverShadow;
use Tests\Support\TestCase;

final class AuthorityGateTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    private function telemetry(): Telemetry
    {
        $this->lines = [];

        return new Telemetry(
            new Config(['telemetry' => ['enabled' => true]]),
            function (string $line): void {
                $this->lines[] = $line;
            },
        );
    }

    /** @return list<string> */
    private function eventNames(): array
    {
        $names = [];
        foreach ($this->lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['event'])) {
                $names[] = (string) $decoded['event'];
            }
        }

        return $names;
    }

    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    public function test_legacy_mode_returns_legacy_verbatim_and_never_consults_a_resolver(): void
    {
        $gate = AuthorityGate::legacy(); // null resolver: consulting it would fatal
        $user = $this->userEntity($this->makeUser());

        self::assertTrue($gate->allows(fn (): bool => true, $user, 'core.thread.lock', ['board_id' => 1], 'test'));
        self::assertFalse($gate->allows(fn (): bool => false, $user, 'core.thread.lock', ['board_id' => 1], 'test'));
        self::assertSame(AuthorityGate::MODE_LEGACY, $gate->mode());
    }

    public function test_shadow_mode_returns_legacy_and_emits_mismatch_telemetry(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate($this->resolver(), new ResolverShadow($this->resolver(), $telemetry), $telemetry, AuthorityGate::MODE_SHADOW);
        $admin = $this->userEntity($this->makeAdmin());
        $board = $this->makeBoard($this->makeCategory());

        // Legacy=false vs resolver=true (admin holds delete_any) -> legacy wins, one mismatch event.
        self::assertFalse($gate->allows(fn (): bool => false, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test'));
        self::assertContains('resolver.shadow_mismatch', $this->eventNames());
    }

    public function test_enforce_mode_returns_resolver_decision_and_flags_reverse_mismatch(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate($this->resolver(), null, $telemetry, AuthorityGate::MODE_ENFORCE);
        $admin = $this->userEntity($this->makeAdmin());
        $member = $this->userEntity($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());
        $target = ['board_id' => (int) $board['id']];

        self::assertTrue($gate->allows(fn (): bool => true, $admin, 'core.post.delete_any', $target, 'test'));
        self::assertFalse($gate->allows(fn (): bool => false, $member, 'core.post.delete_any', $target, 'test'));

        // Resolver denies a plain member even when legacy says yes -> enforce wins + reverse mismatch.
        self::assertFalse($gate->allows(fn (): bool => true, $member, 'core.post.delete_any', $target, 'test'));
        self::assertContains('resolver.enforce_mismatch', $this->eventNames());
        self::assertContains('authority.enforce_denied', $this->eventNames());
    }

    public function test_enforce_mode_fails_closed_when_the_resolver_is_missing(): void
    {
        $telemetry = $this->telemetry();
        $gate = new AuthorityGate(null, null, $telemetry, AuthorityGate::MODE_ENFORCE);
        $admin = $this->userEntity($this->makeAdmin());

        self::assertFalse($gate->allows(fn (): bool => true, $admin, 'core.post.delete_any', [], 'test'));
        self::assertContains('authority.enforce_error', $this->eventNames());
    }

    public function test_assert_throws_forbidden_with_caller_message_on_deny(): void
    {
        $gate = new AuthorityGate($this->resolver(), null, $this->telemetry(), AuthorityGate::MODE_ENFORCE);
        $member = $this->userEntity($this->makeUser());
        $board = $this->makeBoard($this->makeCategory());

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You cannot moderate this board.');
        $gate->assert(fn (): bool => false, $member, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test', 'You cannot moderate this board.');
    }
}
```

Note: `makeBoard` requires a category id — `$this->makeBoard($this->makeCategory())` as shown. `Telemetry`'s sink receives one JSON line, not `(event, context)`, and `emit()` no-ops unless `telemetry.enabled` is true; keep the `Config(['telemetry' => ['enabled' => true]])` + `json_decode` helper above. The missing-resolver test is mandatory because enforce mode must fail closed even if a bad container/test construction gives it no resolver; do not replace it with an unknown-capability case, which only proves a normal resolver deny.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Security/AuthorityGateTest.php`
Expected: FAIL — `Class "App\Security\AuthorityGate" not found`.

- [ ] **Step 3: Implement `src/Security/AuthorityGate.php`**

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ForbiddenException;
use App\Core\Telemetry;
use App\Domain\User;
use App\Service\ResolverShadow;

/**
 * The single authorization decision seam for the capability cutover (Inc 6).
 * legacy  — capabilities flag OFF: return the legacy closure verbatim; the
 *           resolver is null and structurally cannot be consulted.
 * shadow  — flag ON, CAPABILITIES_MODE=shadow (default): legacy decides;
 *           ResolverShadow emits mismatch telemetry (Inc 1 behavior).
 * enforce — flag ON, CAPABILITIES_MODE=enforce: the resolver decides and
 *           FAILS CLOSED on any resolver error; the legacy closure is computed
 *           only for reverse-mismatch telemetry (§13.2 capture-state).
 */
final class AuthorityGate
{
    public const MODE_LEGACY = 'legacy';
    public const MODE_SHADOW = 'shadow';
    public const MODE_ENFORCE = 'enforce';

    public function __construct(
        private ?CapabilityResolver $resolver,
        private ?ResolverShadow $shadow,
        private ?Telemetry $telemetry,
        private string $mode,
    ) {
    }

    /** Passthrough gate for dark installs and hand-constructed test services. */
    public static function legacy(): self
    {
        return new self(null, null, null, self::MODE_LEGACY);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @param callable():bool $legacy the site's current legacy gate expression
     * @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target
     */
    public function allows(callable $legacy, ?User $actor, string $capability, array $target, string $site): bool
    {
        if ($this->mode === self::MODE_ENFORCE) {
            if ($this->resolver === null) {
                $this->telemetry?->emit('authority.enforce_error', [
                    'site' => $site,
                    'capability' => $capability,
                    'error' => 'missing_resolver',
                ]);
                return false; // authorization fails closed
            }

            try {
                $decision = $this->resolver->can($actor, $capability, $target);
            } catch (\Throwable $e) {
                $this->telemetry?->emit('authority.enforce_error', [
                    'site' => $site,
                    'capability' => $capability,
                    'error' => $e::class,
                ]);
                return false; // authorization fails closed
            }

            try {
                $legacyAllowed = (bool) $legacy();
            } catch (\Throwable) {
                $legacyAllowed = $decision->allowed; // legacy is telemetry-only here
            }
            if ($legacyAllowed !== $decision->allowed) {
                $this->telemetry?->emit('resolver.enforce_mismatch', [
                    'site' => $site,
                    'capability' => $capability,
                    'legacy' => $legacyAllowed,
                    'resolver' => $decision->allowed,
                    'source' => $decision->source,
                    'reason' => $decision->reason,
                    'actor_id' => $actor?->id(),
                    'board_id' => $target['board_id'] ?? null,
                ]);
            }
            if (!$decision->allowed) {
                $this->telemetry?->emit('authority.enforce_denied', [
                    'site' => $site,
                    'capability' => $capability,
                    'source' => $decision->source,
                    'reason' => $decision->reason,
                    'actor_id' => $actor?->id(),
                    'board_id' => $target['board_id'] ?? null,
                ]);
            }
            return $decision->allowed;
        }

        $allowed = (bool) $legacy();
        if ($this->mode === self::MODE_SHADOW) {
            $this->shadow?->compare($allowed, $actor, $capability, $target, $site);
        }
        return $allowed;
    }

    /** @param callable():bool $legacy */
    public function assert(callable $legacy, ?User $actor, string $capability, array $target, string $site, string $message): void
    {
        if (!$this->allows($legacy, $actor, $capability, $target, $site)) {
            throw new ForbiddenException($message);
        }
    }
}
```

Check `ForbiddenException`'s namespace/ctor (`src/Core/ForbiddenException.php`) and adjust the import if it differs.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Security/AuthorityGateTest.php`
Expected: PASS (5 tests). Also run `vendor/bin/phpunit tests/Integration/Service/ResolverShadowTest.php` → PASS (untouched).

- [ ] **Step 5: Commit**

```bash
git add src/Security/AuthorityGate.php tests/Integration/Security/AuthorityGateTest.php
git commit -m "feat(capabilities): AuthorityGate three-mode decision seam, fail-closed enforce"
```

---

### Task 2: Container wiring + swap the two shadow-injected services + TestCase enforce helper

**Files:**
- Modify: `src/Core/App.php` (buildContainer: new binding; the `PostingService` binding ~:1596 and `ModerationService` binding ~:1609)
- Modify: `src/Service/PostingService.php` (ctor param + the two gate sites :89/:212)
- Modify: `src/Service/ModerationService.php` (ctor param + `canModerate`/`assertCanModerate`)
- Modify: `tests/Support/TestCase.php` (add `withCapabilitiesEnforced()`)
- Modify: `tests/Integration/Service/ResolverShadowTest.php` (construction updates only, where it passes `ResolverShadow` into services)
- Test: `tests/Integration/Core/AppEnforcementCutoverTest.php` (created here, grows in later tasks)

**Interfaces:**
- Consumes: Task 1's `AuthorityGate` API.
- Produces:
  - Container binding `AuthorityGate::class` (mode from flag+config).
  - `ModerationService::canModerate(User $user, int $boardId, string $capability = 'core.post.delete_any'): bool` and `assertCanModerate(User $user, int $boardId, string $capability = 'core.post.delete_any'): void` — later tasks pass per-action keys.
  - `PostingService`/`ModerationService` ctor: the `?ResolverShadow $shadow = null` param becomes `?AuthorityGate $authority = null`; internally `$this->gate()` returns `$this->authority ?? AuthorityGate::legacy()`.
  - `Tests\Support\TestCase::withCapabilitiesEnforced(array $extraFlags = []): void` — flips `features.capabilities` on via settings AND rebuilds `$this->app` with `capabilities.mode=enforce`.

- [ ] **Step 1: Write the failing test (new file, first two cases)**

`tests/Integration/Core/AppEnforcementCutoverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use Tests\Support\TestCase;

final class AppEnforcementCutoverTest extends TestCase
{
    public function test_enforce_mode_keeps_admin_moderation_working_end_to_end(): void
    {
        $admin = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($admin);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertRedirect($response); // admin still locks under enforcement
    }

    public function test_enforce_mode_denies_plain_member_moderation(): void
    {
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $author = $this->makeUser();
        $t = $this->makeThread($board, $author);
        $this->withCapabilitiesEnforced();

        $this->actingAs($member);
        $response = $this->post('/mod/t/' . $t['thread_id'] . '/lock');
        $this->assertStatus(403, $response);
    }
}
```

First check the real lock route path: `grep -n "'/mod/t/{id}/lock'\|lock'" src/Core/App.php` and use the exact registered path/params (adjust the POST path above if the route differs). Keep the two assertions as-is.

- [ ] **Step 2: Add `withCapabilitiesEnforced` to `tests/Support/TestCase.php`**

Add after `logoutClient()` (imports: `App\Repository\SettingRepository` — Config and App are already imported):

```php
/**
 * Phase 5 Inc 6: enable the capabilities flag (settings override) and rebuild
 * the kernel with CAPABILITIES_MODE=enforce so routes decide via the resolver.
 *
 * @param array<string,bool> $extraFlags additional feature-flag overrides
 */
protected function withCapabilitiesEnforced(array $extraFlags = []): void
{
    (new SettingRepository($this->db))->set('features', ['capabilities' => true] + $extraFlags);
    $items = $this->config->all();
    $items['capabilities']['mode'] = 'enforce';
    $this->app = new App(new Config($items), $this->db, $this->rateLimiter);
}
```

- [ ] **Step 3: Run to verify current state**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php`
Expected: both tests PASS already (legacy still decides both outcomes identically — that is the parity point). These two are **pins**: they must stay green through the swap. The genuinely red test for the enforcement plumbing is Task 4's lock-only granularity case, which cannot pass until the gate + per-action keys exist; this task's steps 4-6 are verified by the suite staying green plus Task 4 turning red-then-green. (`App` exposes no public container accessor, so there is no direct mode assertion — do not add one.)

- [ ] **Step 4: Wire the container + swap the two services**

In `src/Core/App.php::buildContainer`, add (near the ResolverShadow binding ~:1254; match the neighboring closure style exactly):

```php
$c->bind(AuthorityGate::class, function (Container $c) use ($config): AuthorityGate {
    if (!$c->get(FeatureFlags::class)->enabled('capabilities')) {
        return AuthorityGate::legacy();
    }
    $enforce = $config->get('capabilities.mode', 'shadow') === 'enforce';
    return new AuthorityGate(
        $c->get(CapabilityResolver::class),
        $enforce ? null : $c->get(ResolverShadow::class),
        $c->get(Telemetry::class),
        $enforce ? AuthorityGate::MODE_ENFORCE : AuthorityGate::MODE_SHADOW,
    );
});
```

In the `PostingService` binding, replace the trailing
`$flags->enabled('capabilities') ? $c->get(ResolverShadow::class) : null`
argument with `$c->get(AuthorityGate::class)`; same replacement in the `ModerationService` binding. (Exact current expressions: `grep -n 'ResolverShadow' src/Core/App.php`.)

In `src/Service/PostingService.php`:
- ctor: `?ResolverShadow $shadow = null` → `private ?AuthorityGate $authority = null`; delete the `ResolverShadow` import, add `App\Security\AuthorityGate`.
- add a private helper:

```php
private function gate(): AuthorityGate
{
    return $this->authority ?? AuthorityGate::legacy();
}
```

- `createThread` (the gate at ~:89-90): replace

```php
if (!$this->policy->canPost($board, $user, $isMember)) {
    // (existing throw)
}
$this->shadow?->compare(...); // existing compare line
```

with

```php
if (!$this->gate()->allows(
    fn (): bool => $this->policy->canPost($board, $user, $isMember),
    $user,
    'core.thread.create',
    ['board_id' => (int) $board['id']],
    'PostingService::createThread',
)) {
    // keep the exact existing throw statement here unchanged
}
```

(Read the current method first: keep the existing exception type/message verbatim, and delete the now-redundant `$this->shadow?->compare(...)` line. Same transform at `reply` ~:212 with key `core.post.create` and site `'PostingService::reply'`. The locked-thread check just below reply's gate is **[STATE-KEEP]** — leave it.)

In `src/Service/ModerationService.php`:
- ctor: `?ResolverShadow $shadow = null` → `private ?AuthorityGate $authority = null`; same import swap; same `gate()` helper.
- `canModerate` becomes:

```php
/** Non-throwing capability check (admin anywhere, or assigned board moderator). */
public function canModerate(User $user, int $boardId, string $capability = 'core.post.delete_any'): bool
{
    return $this->gate()->allows(
        fn (): bool => $this->writeGate->canWrite($user)
            && ($user->isAdmin() || $this->boardMods->isModerator($boardId, $user->id())),
        $user,
        $capability,
        ['board_id' => $boardId],
        'ModerationService::canModerate',
    );
}
```

- `assertCanModerate` (at ~:305) gains the same `string $capability = 'core.post.delete_any'` param and forwards it to `canModerate`; keep its existing `WriteGate::assertCanWrite` first line ONLY if present today — read the method: it currently does `assertCanWrite` + role test + throw. Replace its body with:

```php
$this->writeGate->assertCanWrite($user);
if (!$this->canModerate($user, $boardId, $capability)) {
    throw new ForbiddenException('You do not have moderation access to this board.');
}
```

(keeping the existing exception message verbatim — read it first). The explicit `assertCanWrite` stays so banned/suspended actors keep getting their specific state error messages instead of a generic 403.

- [ ] **Step 5: Update `ResolverShadowTest` constructions**

`tests/Integration/Service/ResolverShadowTest.php` constructs `PostingService`/`ModerationService` passing a `ResolverShadow` — change those arguments to `new AuthorityGate($resolver, $shadow, $telemetry, AuthorityGate::MODE_SHADOW)` (same resolver/shadow/telemetry instances the test already builds). The direct `ResolverShadow::compare` unit cases stay untouched.

- [ ] **Step 6: Run the affected suites**

```bash
vendor/bin/phpunit tests/Integration/Service/ResolverShadowTest.php tests/Integration/Service/ResolverParityTest.php tests/Integration/Security/AuthorityGateTest.php tests/Integration/Core/AppEnforcementCutoverTest.php tests/Integration/Core/AppFeatureFlagTest.php
```
Expected: PASS. Then the broad regression: `vendor/bin/phpunit --testsuite integration` → PASS (proves the seam swap is behavior-neutral in legacy/shadow).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(capabilities): wire AuthorityGate through container, PostingService and ModerationService"
```

---

### Task 3: Resolver per-request memos + `invalidate()` (TM-PE-03 / TM-PE-08 groundwork)

**Files:**
- Modify: `src/Security/CapabilityResolver.php`
- Test: extend `tests/Integration/Security/CapabilityResolverTest.php`

**Interfaces:**
- Produces: `CapabilityResolver::invalidate(): void` (clears all per-request memos). Memo behavior: repeated `can()` with identical `(actor, capability, target, at-bucket)` does not re-query; any authority mutation calls `invalidate()`.

- [ ] **Step 1: Write the failing tests** (append to `CapabilityResolverTest`)

```php
public function test_memo_serves_repeat_decisions_and_invalidate_clears_it(): void
{
    $modRow = $this->makeUser();
    $mod = $this->userEntity($modRow);
    $board = $this->makeBoard($this->makeCategory());
    $resolver = $this->resolver(); // use the file's existing builder helper
    $target = ['board_id' => (int) $board['id']];
    (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modRow['id']);

    self::assertTrue($resolver->can($mod, 'core.thread.lock', $target)->allowed);

    // Mutate DB-read authority behind the memo's back. This would be visible to
    // a fresh resolver call immediately if bundle/decision memoization were not
    // active within the request.
    $this->db->run('DELETE FROM board_moderators WHERE board_id = ? AND user_id = ?', [(int) $board['id'], (int) $modRow['id']]);

    // Memoized: still allowed within this request scope...
    self::assertTrue($resolver->can($mod, 'core.thread.lock', $target)->allowed);
    // ...until invalidated.
    $resolver->invalidate();
    self::assertFalse($resolver->can($mod, 'core.thread.lock', $target)->allowed);
}

public function test_expiry_denies_despite_warm_memo_tm_pe_03(): void
{
    $user = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $role = $this->makeCustomRole(['core.thread.lock']); // existing helper or create via RoleService as this file already does
    (new \App\Repository\RoleAssignmentRepository($this->db))->create([
        'subject_id' => (int) $user['id'],
        'role_id' => $role,
        'scope_type' => 'board',
        'scope_id' => (int) $board['id'],
        'ends_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    ]);
    $resolver = $this->resolver();
    $entity = $this->userEntity($user);
    $target = ['board_id' => (int) $board['id']];

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    self::assertTrue($resolver->can($entity, 'core.thread.lock', $target, $now)->allowed);
    // Two hours later the grant is expired: a warm memo must NOT resurrect it.
    self::assertFalse($resolver->can($entity, 'core.thread.lock', $target, $now->modify('+2 hours'))->allowed);
}
```

Adapt the two helper references to the file's actual helpers (read the test file first: it already builds a resolver and creates roles — reuse its exact private helpers; if it has no custom-role helper, create the role via `RoleService::create` exactly as its existing cases do, and remove the stray `run !==` guard line — use `$this->db->run(...)` as shown). The first test must mutate authority that `CapabilityResolver` reads from the DB (`board_moderators`/`role_assignments`), not `users.role` on a stale `User` value object, or it will pass without proving memoization.

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Security/CapabilityResolverTest.php`
Expected: FAIL — `invalidate()` undefined; after adding only the method without memoization, the memo case fails at "still allowed" (fresh projection sees the removed board-moderator row).

- [ ] **Step 3: Implement the memos**

In `CapabilityResolver`: add properties + wrap `can()`:

```php
/** @var array<string,CapabilityDecision> */
private array $decisionMemo = [];
/** @var array<string,array{grants:list<array<string,mixed>>,site_rank:int}> */
private array $bundleMemo = [];
/** @var array<int,?array<string,mixed>> */
private array $boardMemo = [];

public function invalidate(): void
{
    $this->decisionMemo = [];
    $this->bundleMemo = [];
    $this->boardMemo = [];
}
```

At the top of `can()` build the memo key and return early; store before returning (read the method and wrap its existing body — do not change decision logic):

```php
$memoKey = implode('|', [
    $capability,
    $actor?->id() ?? 'guest',
    $target['board_id'] ?? '',
    $target['owner_id'] ?? '',
    $target['user_id'] ?? '',
    $target['category_id'] ?? '',
    $at?->format('YmdHi') ?? 'now',
]);
if (isset($this->decisionMemo[$memoKey])) {
    return $this->decisionMemo[$memoKey];
}
// ... existing body, assigning its return value to $decision ...
return $this->decisionMemo[$memoKey] = $decision;
```

Memoize the projection+assignment fetch per actor (`bundleMemo['u' . id]` / `'guest'`) and the board row lookup (`boardMemo[$boardId]`) inside the existing body the same way.

- [ ] **Step 4: Run to verify green + no regression**

Run: `vendor/bin/phpunit tests/Integration/Security/CapabilityResolverTest.php tests/Integration/Service/ResolverParityTest.php tests/Integration/Service/PermissionSimulatorTest.php`
Expected: PASS (parity corpus green through the memo — every corpus tuple has a distinct key).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): per-request decision memos + invalidate() on the resolver"
```

---

### Task 4: Per-action keys through the moderation surface

**Files:**
- Modify: `src/Service/ModerationService.php` (each action passes its key via `requireModeratableThread`/`assertCanModerate`)
- Modify: `src/Controller/PostController.php` (~:162), `src/Controller/ApprovalController.php` (~:110), `src/Controller/ThreadController.php` (~:164-168, :184, :227-228, :262-263, :369), `src/Service/AppealService.php` (~:257), `src/Service/ThreadSplitMergeService.php` (~:35,:114,:117), `src/Service/ReportService.php` (`canHandle` ~:76-80), `src/Service/CommunityMemoryService.php` (`assertCurator` ~:289)
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:**
- Consumes: Task 2's `canModerate(..., string $capability)` / `assertCanModerate(..., string $capability)`; Task 2's `withCapabilitiesEnforced()`.
- Produces: every moderation action gated by its own key (the spec §2 table), so custom roles get per-action granularity.

- [ ] **Step 1: Write the failing granularity test**

Append to `AppEnforcementCutoverTest` (this is the increment's core delegation proof):

```php
public function test_lock_only_custom_role_can_lock_but_not_delete_tm_granularity(): void
{
    $admin = $this->makeAdmin();
    $deputy = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $author = $this->makeUser();
    $t = $this->makeThread($board, $author);

    // Custom role holding ONLY core.thread.lock, assigned at this board.
    $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.lock'], (int) $board['id']);
    self::assertGreaterThan(0, $roleId);
    $this->withCapabilitiesEnforced();

    $this->actingAs($deputy);
    $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/lock'));  // granted key works
    $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$t['thread_id']]);
    $this->assertStatus(403, $this->post('/posts/' . $postId . '/delete'));      // ungranted key refuses
}
```

Add the seeding helper to this test file (private method — RoleService + repo directly, mirroring `RoleServiceTest`):

```php
private function makeCustomRoleWithAssignment(array $adminRow, array $subjectRow, array $keys, int $boardId): int
{
    $service = $this->container()->get(\App\Service\RoleService::class); // if TestCase exposes no container, construct RoleService by hand exactly as tests/Integration/Service/RoleServiceTest.php does
    $roleId = $service->create($this->userEntity($adminRow), 'password123', 'Deputy ' . bin2hex(random_bytes(3)), null, $keys);
    (new \App\Repository\RoleAssignmentRepository($this->db))->create([
        'subject_id' => (int) $subjectRow['id'],
        'role_id' => $roleId,
        'scope_type' => 'board',
        'scope_id' => $boardId,
        'grantor_id' => (int) $adminRow['id'],
    ]);
    return $roleId;
}
```

(Read `RoleServiceTest` first and copy its exact construction; `'password123'` is `makeUser`'s default password. Check the real delete route path via `grep -n "posts/{id}/delete" src/Core/App.php`.)

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php --filter lock_only`
Expected: FAIL — the lock route 403s for the deputy (lock still gates on the default `core.post.delete_any` key, which the role does not hold).

- [ ] **Step 3: Thread the per-action keys**

In `ModerationService` (read each method; the transform is: pass the action's key through the existing `requireModeratableThread`/`assertCanModerate` call — content-state checks stay untouched):

| Method | Key argument |
|---|---|
| `togglePin` | `'core.thread.pin'` |
| `toggleLock` | `'core.thread.lock'` |
| `deletePost` | `'core.post.delete_any'` |
| `restorePost` | `'core.post.restore'` |
| `moveThread` (both boards) | `'core.thread.move'` |
| `revealAuthor` | `'core.post.reveal_author'` |

`requireModeratableThread` gains `string $capability = 'core.post.delete_any'` and forwards it.

External callers (read each site; swap only the predicate/key):
- `ApprovalController` approve/reject actions (~:110 private helper): pass `'core.content.approve'`.
- `ThreadController` pending-thread view (~:369): pass `'core.content.view_pending'`.
- `AppealService` (~:257): pass `'core.appeal.resolve_content'`.
- `ThreadSplitMergeService` (:35 split; :114+:117 merge both boards): pass `'core.thread.split_merge'`.
- `ReportService::canHandle` (~:76-80): replace the inline `isAdmin || boardMods->isModerator` with `$this->moderation->canModerate($user, $boardId, 'core.report.handle')` **if** ModerationService is already a collaborator; if not (read the ctor), inject `AuthorityGate` directly and wrap the existing expression as the legacy closure with key `'core.report.handle'`, site `'ReportService::canHandle'` — choose whichever the ctor already supports; do not create a circular dependency (ModerationService must not depend on ReportService).
- `CommunityMemoryService::assertCurator` (~:289): same pattern, key `'core.memory.curate'`, site `'CommunityMemoryService::assertCurator'`.
- `ThreadController` display flags: the inline recompute at ~:164-168 becomes `$moderation->canModerate($user, $boardId)` (default key — board-wide moderation display); `can_reveal_anon` (~:184) uses `canModerate($user, $boardId, 'core.post.reveal_author')`; the tag/memory repeats (~:227-228, :262-263) call the service likewise. Pull `ModerationService` from the container the way the controller already fetches services.
- `PostController` (~:162) already calls `canModerate($user, $board)` — leave the default key (`core.post.delete_any` is the delete branch's key).

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php tests/Integration/Service/ResolverParityTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS everywhere (legacy/shadow answers unchanged for built-in roles; enforce grants granularity).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): per-action capability keys across the moderation surface"
```

---

### Task 5: Posting-floor + tag sites through the gate

**Files:**
- Modify: `src/Controller/TagController.php` (~:167), `src/Controller/ThreadController.php` (~:101, :229), `src/Controller/PostController.php` (~:197), `src/Controller/BoardController.php` (~:78)
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:**
- Consumes: `AuthorityGate::allows` (controllers pull it from the container: `$this->container->get(AuthorityGate::class)`).
- Produces: every `BoardPolicy::canPost` authorization site consults the gate with its key (`core.thread.create` / `core.post.create` / `core.thread.tag`); `canPost` itself is unchanged.

- [ ] **Step 1: Write the failing test**

```php
public function test_posting_floor_enforced_via_resolver(): void
{
    $member = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory(), ['post_min_role' => 'moderator']);
    $this->withCapabilitiesEnforced();

    $this->actingAs($member);
    $response = $this->post('/b/' . $board['slug'] . '/new', ['title' => 'Nope', 'body' => 'Denied by floor.']);
    self::assertContains($response->status(), [403, 422]); // same refusal as legacy
}
```

(Verify the new-thread route path via `grep -n "new" src/Core/App.php | grep -i thread` and match the legacy refusal status by first running the same POST **without** `withCapabilitiesEnforced()` in a scratch assertion — the enforced status must equal the legacy status; encode the observed value.)

- [ ] **Step 2: Run — expect PASS (parity), then make it meaningful**

This case passes before and after (parity!). Its value is the *pin*: it must STILL pass after the swap. The red step for this task is instead a temporary assertion that the gate is consulted: run with a deliberately wrong key first if you want a visible red, or accept parity-pin semantics (preferred; note it in the commit body).

- [ ] **Step 3: Swap the display/controller sites**

Pattern for each site (read the surrounding code; the legacy expression moves into the closure verbatim):

```php
$gate = $this->container->get(AuthorityGate::class);
$canPost = $gate->allows(
    fn (): bool => $policy->canPost($board, $user, $isMember),
    $user,
    'core.thread.create', // or core.post.create / core.thread.tag per site
    ['board_id' => (int) $board['id']],
    'ThreadController::show', // the actual class::method
);
```

Sites and keys: `ThreadController` ~:101 (`core.thread.create` — new-thread form gate), ~:229 (`core.post.create` — reply form gate); `TagController` ~:167 (`core.thread.tag` — the update action; keep its `assertCanWrite`/`isMember`/archived checks [STATE-KEEP] exactly where they are); `PostController::postableBoards` ~:197 (`core.post.create` per board in the loop — the memo from Task 3 keeps this cheap); `BoardController` ~:78 (`core.thread.create`).
`PostingService` create/reply were swapped in Task 2.

- [ ] **Step 4: Run the affected suites**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): posting-floor and tag sites decide through AuthorityGate"
```

---

### Task 6: Dual-path services (solved / poll / workflow)

**Files:**
- Modify: `src/Service/SolvedAnswerService.php` (`authorize` ~:195-199), `src/Service/PollService.php` (`canManageThread` ~:165-169), `src/Service/ThreadWorkflowService.php` (`canStaffAssign` ~:249 and the staff-only branch of `authorizeStatus` ~:213-220)
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:**
- Consumes: `AuthorityGate` (new ctor collaborator on each service, `?AuthorityGate $authority = null` + `gate()` helper as in Task 2); container bindings updated to pass `$c->get(AuthorityGate::class)`.
- Produces: dual-path decisions flow through the rules' owner-vs-moderator branch in ONE call — target carries `board_id` + `owner_id`.

- [ ] **Step 1: Write the failing test**

```php
public function test_dual_path_solved_still_works_for_op_and_board_mod_under_enforce(): void
{
    $board = $this->makeBoard($this->makeCategory());
    $op = $this->makeUser();
    $bystander = $this->makeUser();
    $t = $this->makeThread($board, $op);
    $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id LIMIT 1', [$t['thread_id']]);
    $this->withCapabilitiesEnforced(['community' => true]);

    $this->actingAs($op);
    $this->assertRedirect($this->post('/posts/' . $postId . '/solved'));   // OP path allowed
    $this->actingAs($bystander);
    $this->assertStatus(403, $this->post('/posts/' . $postId . '/solved')); // stranger denied
}
```

(Find the exact solved route + flag name first: `grep -n 'solved' src/Core/App.php` and `grep -n "community\|mark_solved" src/Core/FeatureFlags.php`; pass whichever flag gates the route through `$extraFlags`. If marking solved requires the *unsolved* state or OP-question shape, mirror an existing passing case from the solved tests: `grep -rn 'solved' tests/Integration --include='*.php' -l` and copy its seeding.)

- [ ] **Step 2: Run — parity pin (see Task 5 Step 2 note)**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php --filter dual_path`
Expected: PASS pre-swap (parity) — this is a pin; the swap must keep it green.

- [ ] **Step 3: Swap the three services**

Each service: add `?AuthorityGate $authority = null` ctor param + `gate()` helper (Task 2 pattern); update its container binding to append `$c->get(AuthorityGate::class)`. Replace the staff-or-owner predicate with one gate call whose closure is the existing expression verbatim:

- `SolvedAnswerService::authorize(...)` — key `'core.thread.mark_solved'`, target `['board_id' => $boardId, 'owner_id' => $threadAuthorId]`, site `'SolvedAnswerService::authorize'`. (Read the method for the exact owner/staff expression and the thread-author variable name.)
- `PollService::canManageThread(...)` — key `'core.poll.manage'`, target `['board_id' => ..., 'owner_id' => ...]`, site `'PollService::canManageThread'`.
- `ThreadWorkflowService` — `canStaffAssign` key `'core.thread.manage_workflow'` target `['board_id' => ...]`; in `authorizeStatus`, ONLY the staff branch (statuses `decision_made`/`archived` per the read) goes through the gate with the same key and target `['board_id' => ..., 'owner_id' => $opId]`; the OP-status branch is the same gate call (dual-path handles it) — read the method and collapse carefully, keeping any status-vocabulary validation [STATE-KEEP].

- [ ] **Step 4: Run affected suites**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php tests/Integration/Service/ResolverParityTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): dual-path solved/poll/workflow decide through AuthorityGate"
```

---

### Task 7: Site-scope quirk keys — warn + the two pending views (owner-approved tightening)

**Files:**
- Modify: `src/Service/UserModerationService.php` (`assertStaff` ~:284), `src/Controller/ApprovalController.php` (~:29), `src/Controller/MediaController.php` (~:204)
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:**
- Consumes: `AuthorityGate`.
- Produces: `core.user.warn` and `core.content.view_pending` (site probes) enforced; ADR-0016 tightening pinned.

- [ ] **Step 1: Write the failing tests**

```php
public function test_suspended_global_moderator_loses_pending_views_under_enforce_adr_0016(): void
{
    $mod = $this->makeUser(['role' => 'moderator', 'status' => 'suspended',
        'suspended_until' => gmdate('Y-m-d H:i:s', time() + 86400)]);
    $this->withCapabilitiesEnforced(['anti_abuse' => true]);

    $this->actingAs($mod);
    $this->assertStatus(403, $this->get('/mod/approvals')); // approved tightening: state beats role
}

public function test_suspended_global_moderator_keeps_pending_view_in_shadow_mode(): void
{
    $mod = $this->makeUser(['role' => 'moderator', 'status' => 'suspended',
        'suspended_until' => gmdate('Y-m-d H:i:s', time() + 86400)]);
    (new \App\Repository\SettingRepository($this->db))->set('features', ['capabilities' => true, 'anti_abuse' => true]);
    // NOTE: no app rebuild — CAPABILITIES_MODE stays shadow (the default config).

    $this->actingAs($mod);
    $response = $this->get('/mod/approvals');
    self::assertNotSame(403, $response->status()); // legacy quirk preserved outside enforce
}
```

(Verify the approval-queue route (`grep -n 'approvals\|/mod/' src/Core/App.php`) and whichever flag gates it; adjust path + flags. The suspended fixture shape mirrors `makeUser`'s status/suspended_until handling. If a GET to the queue redirects for other reasons, assert on the redirect-vs-403 distinction only.)

- [ ] **Step 2: Run to verify the first fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php --filter suspended_global`
Expected: first FAILS (legacy still allows the view — no gate yet), second PASSES.

- [ ] **Step 3: Swap the three sites**

- `ApprovalController` (~:29): the view gate `if (!$user->isModerator()) { throw ... }` becomes

```php
$this->container->get(AuthorityGate::class)->assert(
    fn (): bool => $user->isModerator(),
    $user,
    'core.content.view_pending',
    [], // site probe: no board target — board-scoped grants correctly do not qualify
    'ApprovalController::index',
    'Moderator access required.', // keep the existing message verbatim
);
```

(read the current throw for the exact message; the board data-scoping at ~:32 stays untouched).
- `MediaController` pending-media view (~:204): preserve the existing anti-enumeration 404. Do **not** use `AuthorityGate::assert()` here because that would turn an unauthorized held-media probe into a 403. Instead wrap only the predicate with `allows()` and keep the existing `NotFoundException('Media not found.')` denial:

```php
if ($pending && !$this->container->get(AuthorityGate::class)->allows(
    fn (): bool => $user !== null && $user->isModerator(),
    $user,
    'core.content.view_pending',
    [], // site probe: no board target — board-scoped grants correctly do not qualify
    'MediaController::authorizePendingMedia',
)) {
    throw new NotFoundException('Media not found.');
}
```

Also extend the existing held-media coverage (`tests/Integration/Core/AppImageUploadTest.php::test_held_post_media_is_never_publicly_cacheable` or a sibling test) so a stranger still receives **404, not 403**, with `capabilities` enabled and `CAPABILITIES_MODE=enforce`.
- `UserModerationService::assertStaff` (~:284): keep the leading `assertCanWrite` line, replace the role test with `assert(...)` — closure `fn (): bool => $user->isAdmin() || $this->boardMods->boardsFor($user->id()) !== []`, key `'core.user.warn'`, target `[]`, site `'UserModerationService::assertStaff'`, message verbatim from the existing throw. Service gains the `?AuthorityGate` ctor param (Task 2 pattern) + container binding update.

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php tests/Integration/Core/AppImageUploadTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS (the warn path parity is covered by existing `UserModerationService` suites staying green).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): warn + pending-view site probes through the gate (ADR 0016 tightening)"
```

---

### Task 8: Authority-granting sites + invalidation calls

**Files:**
- Modify: `src/Controller/AdminController.php` (four board roster POST actions switch from `requireAdmin()` to `requireUser()` and rely on service authorization)
- Modify: `src/Service/AdminService.php` (`assignModerator` ~:473, `unassignModerator` ~:503, `addMember` ~:530, `removeMember` ~:554)
- Modify: `src/Service/RoleService.php` (invalidate after create/update/clone)
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:**
- Consumes: `AuthorityGate`, `CapabilityResolver::invalidate()`.
- Produces: `core.board.assign_moderators` / `core.board.manage_members` enforced at the four board-roster POST command endpoints; every authority mutation invalidates resolver memos. The broader admin console GET/edit UI remains admin-only and is still the later admin-console follow-up, but these four POST routes must be live because Task 9 marks their keys grantable.

- [ ] **Step 1: Write the failing test**

```php
public function test_board_roster_assign_moderators_key_can_assign_board_moderator_under_enforce(): void
{
    $admin = $this->makeAdmin();
    $deputy = $this->makeUser();
    $target = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.assign_moderators'], (int) $board['id']);
    $this->withCapabilitiesEnforced();

    $this->actingAs($deputy);
    $this->assertRedirect($this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => $target['username']]));
    self::assertTrue((new \App\Repository\BoardModeratorRepository($this->db))->isModerator((int) $board['id'], (int) $target['id']));
}

public function test_board_roster_manage_members_key_can_add_member_under_enforce(): void
{
    $admin = $this->makeAdmin();
    $deputy = $this->makeUser();
    $target = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
    $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.board.manage_members'], (int) $board['id']);
    $this->withCapabilitiesEnforced();

    $this->actingAs($deputy);
    $this->assertRedirect($this->post('/admin/boards/' . $board['id'] . '/members', ['username' => $target['username']]));
    self::assertTrue((new \App\Repository\BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $target['id']));
}

public function test_unrelated_board_role_cannot_change_rosters_under_enforce(): void
{
    $admin = $this->makeAdmin();
    $deputy = $this->makeUser();
    $target = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.lock'], (int) $board['id']);
    $this->withCapabilitiesEnforced();

    $this->actingAs($deputy);
    $this->assertStatus(403, $this->post('/admin/boards/' . $board['id'] . '/moderators', ['username' => $target['username']]));
}
```

(Find the real roster routes: `grep -n 'moderators\|members' src/Core/App.php`. These are intentionally positive tests: before the controller/service swap a delegated actor still dies at `AdminController::requireAdmin()`, which is the bug this task removes for the four POST command endpoints.)

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php --filter roster`
Expected: FAIL — the positive delegated roster tests return 403 before `AdminController` is changed from `requireAdmin()` to service-level capability authorization.

- [ ] **Step 3: Swap + invalidate**

`AdminController`: for only the four board-roster POST actions (`assignModerator`, `unassignModerator`, `addMember`, `removeMember`), replace the leading `$admin = $this->requireAdmin();` with `$actor = $this->requireUser();` and pass `$actor` to `AdminService`. Leave `editBoard()`, the board edit template, and the rest of `/admin` on `requireAdmin()` — this task cuts over the command endpoints, not the whole admin console.

`AdminService`: add `?AuthorityGate $authority = null` + `?CapabilityResolver $resolver = null` ctor params (container passes both; `AuthorityGate` unconditionally, resolver unconditionally — it is always bound). In `assignModerator`/`unassignModerator`, replace the existing `assertAdmin` line with a roster-authority helper that preserves state-first behavior and uses the gate with the key and board target — legacy closure `fn (): bool => $actor->isAdmin()`:

```php
$this->writeGate->assertCanWrite($actor);
$this->gate()->assert(
    fn (): bool => $actor->isAdmin(),
    $actor,
    'core.board.assign_moderators',
    ['board_id' => $boardId],
    'AdminService::assignModerator',
    'Administrator access required.',
);
```

(`unassignModerator` site string accordingly; `addMember`/`removeMember` use `'core.board.manage_members'`.) Rename the method variables from `$admin` to `$actor` or be very careful not to imply admin-only semantics; audit `actor_id` remains `$actor->id()`. After each successful mutation (same method, after the transaction), call `$this->resolver?->invalidate();`.

`RoleService`: add `?CapabilityResolver $resolver = null` ctor param (container passes it); call `$this->resolver?->invalidate();` at the end of `create`, `update`, and `clone`.

- [ ] **Step 4: Run affected suites**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php tests/Integration/Service/RoleServiceTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): roster/membership sites gated; authority mutations invalidate memos"
```

---

### Task 9: `EnforcedCapabilities` + the RoleService clamp

**Files:**
- Create: `src/Security/EnforcedCapabilities.php`
- Modify: `src/Service/RoleService.php` (`validateDefinition`)
- Modify: `templates/admin/roles.php` + `templates/admin/role_edit.php` (annotate un-enforced keys)
- Test: create `tests/Unit/Security/EnforcedCapabilitiesTest.php`; extend `tests/Integration/Service/RoleServiceTest.php`

**Interfaces:**
- Produces: `EnforcedCapabilities::keys(): list<string>` and `EnforcedCapabilities::has(string): bool` — the exact 21-key list from the spec §3.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Security/EnforcedCapabilitiesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CapabilityCatalog;
use App\Security\EnforcedCapabilities;
use PHPUnit\Framework\TestCase;

final class EnforcedCapabilitiesTest extends TestCase
{
    public function test_exact_enforced_set_matches_the_inc6_spec(): void
    {
        $expected = [
            'core.thread.create', 'core.post.create', 'core.thread.tag',
            'core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow',
            'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
            'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author',
            'core.content.approve', 'core.content.view_pending', 'core.report.handle',
            'core.appeal.resolve_content', 'core.memory.curate', 'core.user.warn',
            'core.board.assign_moderators', 'core.board.manage_members',
        ];
        sort($expected);
        $actual = EnforcedCapabilities::keys();
        sort($actual);
        self::assertSame($expected, $actual);
    }

    public function test_every_enforced_key_is_catalogued_and_delegable(): void
    {
        foreach (EnforcedCapabilities::keys() as $key) {
            $meta = CapabilityCatalog::all()[$key] ?? null;
            self::assertNotNull($meta, $key);
            self::assertTrue($meta['delegable'], $key);
            self::assertFalse($meta['protected'], $key);
        }
    }
}
```

Extend `RoleServiceTest`:

```php
public function test_create_rejects_a_key_without_live_enforcement(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $this->expectException(ValidationException::class);
    $this->service()->create($admin, 'password123', 'Suspender', null, ['core.user.suspend']);
}
```

(use the file's existing service builder + reauth password conventions — read a neighboring case and mirror it exactly).

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Unit/Security/EnforcedCapabilitiesTest.php tests/Integration/Service/RoleServiceTest.php`
Expected: FAIL — class missing; clamp case fails (create currently accepts any delegable key).

- [ ] **Step 3: Implement**

`src/Security/EnforcedCapabilities.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Keys with LIVE route enforcement after Increment 6. The role editor refuses
 * to grant anything outside this set ("honesty clamp"): a granted capability
 * must actually work. Grows as later increments cut more surface over
 * (remaining admin-console keys are the recorded follow-up). Spec:
 * docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md §3.
 */
final class EnforcedCapabilities
{
    /** @var list<string> */
    private const KEYS = [
        'core.thread.create', 'core.post.create', 'core.thread.tag',
        'core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow',
        'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
        'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author',
        'core.content.approve', 'core.content.view_pending', 'core.report.handle',
        'core.appeal.resolve_content', 'core.memory.curate', 'core.user.warn',
        'core.board.assign_moderators', 'core.board.manage_members',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
```

In `RoleService::validateDefinition` (read it; it already loops the requested keys against the catalogue), add inside the per-key loop:

```php
if (!EnforcedCapabilities::has($key)) {
    $errors['capabilities'] = "'" . $key . "' is not yet enforceable; it can be granted once its routes cut over to the resolver.";
}
```

Templates: in `templates/admin/roles.php` and `templates/admin/role_edit.php`, the capability checkbox loop currently renders every delegable key — add `disabled` + a `(not yet enforceable)` suffix for keys failing `\App\Security\EnforcedCapabilities::has($key)` (use the existing `$e()` escaping and the template's loop variable names verbatim — read the loop first).

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Unit/Security/EnforcedCapabilitiesTest.php tests/Integration/Service/RoleServiceTest.php tests/Integration/Core/AppRoleAdminTest.php`
Expected: PASS. If an existing `RoleServiceTest`/`AppRoleAdminTest` case built a role with a now-clamped key, update that case to use an enforced key (e.g. `core.thread.lock`) — behavior change is intended.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): EnforcedCapabilities honesty clamp in the role editor"
```

---

### Task 10: `RoleAssignmentRepository` write-lifecycle methods

**Files:**
- Modify: `src/Repository/RoleAssignmentRepository.php`
- Test: extend `tests/Integration/Repository/RoleAssignmentRepositoryTest.php`

**Interfaces:**
- Produces (Tasks 11-12 consume exactly these):
  - `find(int $id): ?array` (plain row lookup, no lock — controllers use it for redirect targets)
  - `findForUpdate(int $id): ?array` (row or null; `SELECT ... FOR UPDATE`, transaction-only)
  - `revoke(int $id, int $revokedBy): void` (sets `revoked_at=UTC_TIMESTAMP()`, `revoked_by`, `assignment_version = assignment_version + 1`)
  - `updateEndsAt(int $id, string $endsAt): void` (sets `ends_at`, bumps version)
  - `listForRole(int $roleId): array` (JOIN users on subject; newest first; includes revoked/expired rows)

- [ ] **Step 1: Write the failing tests** (append; mirror the file's existing style)

```php
public function test_revoke_stamps_and_bumps_version(): void
{
    $user = $this->makeUser();
    $admin = $this->makeAdmin();
    $roleId = $this->seedRole(); // reuse the file's existing role seeding helper (read it)
    $repo = new RoleAssignmentRepository($this->db);
    $id = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);

    $repo->revoke($id, (int) $admin['id']);

    $row = $repo->findForUpdate($id);
    self::assertNotNull($row);
    self::assertNotNull($row['revoked_at']);
    self::assertSame((int) $admin['id'], (int) $row['revoked_by']);
    self::assertSame(2, (int) $row['assignment_version']);
}

public function test_update_ends_at_bumps_version_and_list_for_role_includes_all_states(): void
{
    $user = $this->makeUser();
    $roleId = $this->seedRole();
    $repo = new RoleAssignmentRepository($this->db);
    $id = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $roleId]);

    $repo->updateEndsAt($id, gmdate('Y-m-d H:i:s', time() + 3600));
    $rows = $repo->listForRole($roleId);

    self::assertCount(1, $rows);
    self::assertSame($user['username'], $rows[0]['username']);
    self::assertSame(2, (int) $rows[0]['assignment_version']);
}
```

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleAssignmentRepositoryTest.php`
Expected: FAIL — undefined methods.

- [ ] **Step 3: Implement** (append to the repository)

```php
/** @return array<string,mixed>|null */
public function find(int $id): ?array
{
    return $this->db->fetch('SELECT * FROM role_assignments WHERE id = ?', [$id]);
}

/** @return array<string,mixed>|null */
public function findForUpdate(int $id): ?array
{
    return $this->db->fetch('SELECT * FROM role_assignments WHERE id = ? FOR UPDATE', [$id]);
}

public function revoke(int $id, int $revokedBy): void
{
    $this->db->run(
        'UPDATE role_assignments
         SET revoked_at = UTC_TIMESTAMP(), revoked_by = ?, assignment_version = assignment_version + 1
         WHERE id = ? AND revoked_at IS NULL',
        [$revokedBy, $id],
    );
}

public function updateEndsAt(int $id, string $endsAt): void
{
    $this->db->run(
        'UPDATE role_assignments
         SET ends_at = ?, assignment_version = assignment_version + 1
         WHERE id = ?',
        [$endsAt, $id],
    );
}

/** @return list<array<string,mixed>> */
public function listForRole(int $roleId): array
{
    return $this->db->fetchAll(
        "SELECT ra.*, u.username
         FROM role_assignments ra
         JOIN users u ON u.id = ra.subject_id AND ra.subject_type = 'user'
         WHERE ra.role_id = ?
         ORDER BY ra.id DESC",
        [$roleId],
    );
}
```

(`Database::fetch` returns `?array`; check the actual nullable-return signature at `src/Core/Database.php:73` and match.)

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleAssignmentRepositoryTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): role-assignment revoke/renew/list repository methods"
```

---

### Task 11: `RoleAssignmentService` — grant / revoke / renew with grantor ceiling

**Files:**
- Create: `src/Service/RoleAssignmentService.php`
- Modify: `src/Core/App.php` (bind it)
- Test: create `tests/Integration/Service/RoleAssignmentServiceTest.php`

**Interfaces:**
- Consumes: Task 10 repo methods; `ReauthGate::requirePassword`; `CapabilityResolver::can`/`invalidate`; `RoleCapabilityRepository::keysForRole(int): list<string>`; `RoleAssignmentHistoryRepository::log(array): int`; `ModerationLogRepository` (read its `log(array)` field shape first and mirror an existing caller such as `RoleService`).
- Produces (Task 12 consumes):
  - `grant(User $admin, string $currentPassword, int $roleId, string $username, string $scopeType, ?int $scopeId, ?string $startsAt, ?string $endsAt, ?string $reason): int`
  - `revoke(User $admin, int $assignmentId, ?string $reason): void`
  - `renew(User $admin, string $currentPassword, int $assignmentId, string $endsAt): void`
  - `listForRole(int $roleId): array` (rows + computed `status` of `active|scheduled|expired|revoked`)

- [ ] **Step 1: Write the failing tests**

`tests/Integration/Service/RoleAssignmentServiceTest.php` — build the service by hand (mirror `RoleServiceTest`'s constructions; the resolver builder from Task 1's test):

```php
public function test_grant_creates_row_history_and_audit(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $subject = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $roleId = $this->makeRole(['core.thread.lock']); // helper mirroring RoleServiceTest role creation

    $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', (int) $board['id'], null, null, 'pilot');

    $row = (new RoleAssignmentRepository($this->db))->findForUpdate($id);
    self::assertSame('board', $row['scope_type']);
    self::assertSame((int) $admin->id(), (int) $row['grantor_id']);
    $history = (new RoleAssignmentHistoryRepository($this->db))->forRole($roleId);
    self::assertSame('grant', $history[0]['event']);
}

public function test_grant_requires_reauth_scope_id_and_custom_role(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $subject = $this->makeUser();
    $roleId = $this->makeRole(['core.thread.lock']);

    try { $this->service()->grant($admin, 'wrong', $roleId, $subject['username'], 'site', null, null, null, null); self::fail(); }
    catch (ValidationException $e) { self::assertArrayHasKey('current_password', $e->errors); }

    try { $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'board', null, null, null, null); self::fail(); }
    catch (ValidationException $e) { self::assertArrayHasKey('scope_id', $e->errors); }

    $systemRoleId = (int) $this->db->fetchValue("SELECT id FROM roles WHERE role_key = 'system.moderator'");
    try { $this->service()->grant($admin, 'password123', $systemRoleId, $subject['username'], 'site', null, null, null, null); self::fail(); }
    catch (ValidationException $e) { self::assertArrayHasKey('role', $e->errors); }
}

public function test_grant_refuses_custom_role_with_unenforced_capability_even_if_row_predates_the_clamp(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $subject = $this->makeUser();
    $roles = new RoleRepository($this->db);
    $roleId = $roles->create([
        'role_key' => 'custom.legacy_suspender',
        'name' => 'Legacy Suspender',
        'description' => null,
        'created_by' => $admin->id(),
    ]);
    $ids = (new CapabilityRepository($this->db))->idsByKeys(['core.user.suspend']);
    (new RoleCapabilityRepository($this->db))->replaceForRole($roleId, array_values($ids));

    try {
        $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, null, null, null);
        self::fail('pre-existing custom roles with unenforced keys must not be assignable');
    } catch (ValidationException $e) {
        self::assertArrayHasKey('capabilities', $e->errors);
    }
}

public function test_grantor_ceiling_blocks_and_audits_out_of_scope_grants_tm_pe_02(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $deputy = $this->makeUser();          // board-scoped grantor
    $pawn = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $roleId = $this->makeRole(['core.thread.lock']);
    (new RoleAssignmentRepository($this->db))->create([
        'subject_id' => (int) $deputy['id'], 'role_id' => $roleId,
        'scope_type' => 'board', 'scope_id' => (int) $board['id'],
    ]);
    $deputyEntity = $this->userEntity($deputy);

    // Board-scoped deputy CANNOT mint SITE scope (E1 fail-closed does the math).
    try {
        $this->service()->grant($deputyEntity, 'password123', $roleId, $pawn['username'], 'site', null, null, null, null);
        self::fail('site-scope grant must refuse');
    } catch (ValidationException $e) {
        self::assertArrayHasKey('scope_type', $e->errors);
    }
    $audit = $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'role_assignment_denied'");
    self::assertSame(1, (int) $audit);

    // Same-or-narrower scope IS grantable by the deputy at their board.
    $ok = $this->service()->grant($deputyEntity, 'password123', $roleId, $pawn['username'], 'board', (int) $board['id'], null, null, null);
    self::assertGreaterThan(0, $ok);
}

public function test_revoke_is_fast_and_renew_reauths_and_row_locks_are_deterministic(): void
{
    $admin = $this->userEntity($this->makeAdmin());
    $subject = $this->makeUser();
    $roleId = $this->makeRole(['core.thread.lock']);
    $id = $this->service()->grant($admin, 'password123', $roleId, $subject['username'], 'site', null, null, gmdate('Y-m-d H:i:s', time() + 3600), null);

    $this->service()->revoke($admin, $id, 'over'); // no password argument — fast path
    try { $this->service()->renew($admin, 'password123', $id, gmdate('Y-m-d H:i:s', time() + 7200)); self::fail(); }
    catch (ValidationException $e) { self::assertArrayHasKey('assignment', $e->errors); } // revoked rows refuse renew

    try { $this->service()->revoke($admin, $id, null); self::fail(); }
    catch (ValidationException $e) { self::assertArrayHasKey('assignment', $e->errors); } // double-revoke refuses deterministically
}
```

(`moderation_log` action-string: read `ModerationLogRepository::log` callers for the exact field names — adjust the audit assertion column names to the real shape. The stale-role clamp test needs imports for `CapabilityRepository`, `RoleRepository`, and `RoleCapabilityRepository`; it deliberately bypasses `RoleService` to model a custom role row created before Task 9's `EnforcedCapabilities` clamp.)

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Service/RoleAssignmentServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Service/RoleAssignmentService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\CapabilityResolver;
use App\Security\EnforcedCapabilities;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * P5-09 scoped-assignment lifecycle. Custom roles only (built-in authority is
 * legacy-managed); grants reauth, revokes stay fast (narrowing-only); the
 * grantor ceiling requires every capability in the role to resolve allowed for
 * the grantor at the target scope. Expiry is decision-time (CapabilityRules).
 */
final class RoleAssignmentService
{
    public function __construct(
        private Database $db,
        private RoleRepository $roles,
        private RoleCapabilityRepository $roleCapabilities,
        private RoleAssignmentRepository $assignments,
        private RoleAssignmentHistoryRepository $history,
        private UserRepository $users,
        private BoardRepository $boards,
        private CategoryRepository $categories,
        private CapabilityResolver $resolver,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $modLog,
        private ?Telemetry $telemetry = null,
    ) {
    }

    public function grant(
        User $admin,
        string $currentPassword,
        int $roleId,
        string $username,
        string $scopeType,
        ?int $scopeId,
        ?string $startsAt,
        ?string $endsAt,
        ?string $reason,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $role = $this->roles->find($roleId);
        if ($role === null || ($role['kind'] ?? '') !== 'custom') {
            throw new ValidationException(['role' => 'Only custom roles can be assigned here; built-in authority is managed by the board-moderator and member tools.']);
        }
        $this->assertRoleOnlyContainsEnforcedCapabilities($roleId);
        $subject = $this->users->findByUsername($username);
        if ($subject === null) {
            throw new ValidationException(['username' => 'No such member.']);
        }
        [$scopeType, $scopeId] = $this->validateScope($scopeType, $scopeId);
        [$startsAt, $endsAt] = $this->validateWindow($startsAt, $endsAt);
        $this->assertGrantorCeiling($admin, $roleId, $scopeType, $scopeId);

        return (int) $this->db->transaction(function () use ($admin, $subject, $roleId, $scopeType, $scopeId, $startsAt, $endsAt, $reason): int {
            $id = $this->assignments->create([
                'subject_id' => (int) $subject['id'],
                'role_id' => $roleId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'grantor_id' => $admin->id(),
                'reason' => $reason,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $this->logHistory('grant', $id, $admin, $subject, $roleId, $scopeType, $scopeId, null, [
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'reason' => $reason,
            ]);
            $this->audit($admin, 'assign_role', (int) $subject['id'], ['assignment_id' => $id, 'role_id' => $roleId]);
            $this->telemetry?->emit('role_assignment.granted', ['assignment_id' => $id, 'role_id' => $roleId, 'scope_type' => $scopeType, 'actor_id' => $admin->id()]);
            $this->resolver->invalidate();
            return $id;
        });
    }

    public function revoke(User $admin, int $assignmentId, ?string $reason): void
    {
        $this->writeGate->assertCanWrite($admin);

        $this->db->transaction(function () use ($admin, $assignmentId, $reason): void {
            $row = $this->assignments->findForUpdate($assignmentId);
            if ($row === null || $row['revoked_at'] !== null) {
                throw new ValidationException(['assignment' => 'This assignment is not active.']);
            }
            $this->assignments->revoke($assignmentId, (int) $admin->id());
            $this->logHistory('revoke', $assignmentId, $admin, null, (int) $row['role_id'], (string) $row['scope_type'], $row['scope_id'] === null ? null : (int) $row['scope_id'], $row, ['reason' => $reason]);
            $this->audit($admin, 'revoke_role', (int) $row['subject_id'], ['assignment_id' => $assignmentId]);
            $this->telemetry?->emit('role_assignment.revoked', ['assignment_id' => $assignmentId, 'role_id' => (int) $row['role_id'], 'actor_id' => $admin->id()]);
            $this->resolver->invalidate();
        });
    }

    public function renew(User $admin, string $currentPassword, int $assignmentId, string $endsAt): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        [, $endsAt] = $this->validateWindow(null, $endsAt);
        if ($endsAt === null) {
            throw new ValidationException(['ends_at' => 'A renewal needs a new expiry.']);
        }

        $this->db->transaction(function () use ($admin, $assignmentId, $endsAt): void {
            $row = $this->assignments->findForUpdate($assignmentId);
            if ($row === null || $row['revoked_at'] !== null) {
                throw new ValidationException(['assignment' => 'Revoked assignments cannot be renewed; create a new grant.']);
            }
            $this->assignments->updateEndsAt($assignmentId, $endsAt);
            $this->logHistory('renew', $assignmentId, $admin, null, (int) $row['role_id'], (string) $row['scope_type'], $row['scope_id'] === null ? null : (int) $row['scope_id'], $row, ['ends_at' => $endsAt]);
            $this->audit($admin, 'renew_role', (int) $row['subject_id'], ['assignment_id' => $assignmentId, 'ends_at' => $endsAt]);
            $this->telemetry?->emit('role_assignment.renewed', ['assignment_id' => $assignmentId, 'role_id' => (int) $row['role_id'], 'actor_id' => $admin->id()]);
            $this->resolver->invalidate();
        });
    }

    /** @return list<array<string,mixed>> rows + computed status */
    public function listForRole(int $roleId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        return array_map(static function (array $row) use ($now): array {
            $row['status'] = $row['revoked_at'] !== null ? 'revoked'
                : (($row['starts_at'] !== null && $row['starts_at'] > $now) ? 'scheduled'
                : (($row['ends_at'] !== null && $row['ends_at'] <= $now) ? 'expired' : 'active'));
            return $row;
        }, $this->assignments->listForRole($roleId));
    }

    /** @return array{0:string,1:?int} */
    private function validateScope(string $scopeType, ?int $scopeId): array
    {
        if (!in_array($scopeType, ['site', 'category', 'board'], true)) {
            throw new ValidationException(['scope_type' => 'Scope must be site, category, or board.']);
        }
        if ($scopeType === 'site') {
            return ['site', null];
        }
        if ($scopeId === null || $scopeId <= 0) {
            throw new ValidationException(['scope_id' => 'Pick the target ' . $scopeType . '.']);
        }
        $exists = $scopeType === 'board' ? $this->boards->find($scopeId) !== null : $this->categories->find($scopeId) !== null;
        if (!$exists) {
            throw new ValidationException(['scope_id' => 'No such ' . $scopeType . '.']);
        }
        return [$scopeType, $scopeId];
    }

    /** @return array{0:?string,1:?string} validated UTC datetimes */
    private function validateWindow(?string $startsAt, ?string $endsAt): array
    {
        $parse = static function (?string $value, string $field): ?string {
            if ($value === null || trim($value) === '') {
                return null;
            }
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($value), new \DateTimeZone('UTC'))
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value), new \DateTimeZone('UTC'));
            if ($dt === false || $dt === null) {
                throw new ValidationException([$field => 'Use the format YYYY-MM-DD HH:MM (UTC).']);
            }
            return $dt->format('Y-m-d H:i:s');
        };
        $s = $parse($startsAt, 'starts_at');
        $e = $parse($endsAt, 'ends_at');
        if ($e !== null && $e <= gmdate('Y-m-d H:i:s')) {
            throw new ValidationException(['ends_at' => 'The expiry must be in the future.']);
        }
        if ($s !== null && $e !== null && $e <= $s) {
            throw new ValidationException(['ends_at' => 'The expiry must be after the start.']);
        }
        return [$s, $e];
    }

    private function assertGrantorCeiling(User $grantor, int $roleId, string $scopeType, ?int $scopeId): void
    {
        $target = match ($scopeType) {
            'board' => ['board_id' => (int) $scopeId],
            'category' => ['category_id' => (int) $scopeId],
            default => [],
        };
        foreach ($this->roleCapabilities->keysForRole($roleId) as $key) {
            if (!$this->resolver->can($grantor, $key, $target)->allowed) {
                $this->audit($grantor, 'role_assignment_denied', $grantor->id(), [
                    'role_id' => $roleId, 'capability' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                ]);
                throw new ValidationException(['scope_type' => 'You do not hold every capability in this role at that scope.']);
            }
        }
    }

    private function assertRoleOnlyContainsEnforcedCapabilities(int $roleId): void
    {
        foreach ($this->roleCapabilities->keysForRole($roleId) as $key) {
            if (!EnforcedCapabilities::has($key)) {
                throw new ValidationException(['capabilities' => "'" . $key . "' is not yet enforceable; it can be assigned once its routes cut over to the resolver."]);
            }
        }
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function logHistory(string $event, int $assignmentId, User $actor, ?array $subject, int $roleId, string $scopeType, ?int $scopeId, ?array $before, array $after): void
    {
        $this->history->log([
            'assignment_id' => $assignmentId,
            'event' => $event,
            'actor_id' => $actor->id(),
            'subject_type' => 'user',
            'subject_id' => $subject !== null ? (int) $subject['id'] : null,
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /** @param array<string,mixed> $detail */
    private function audit(User $actor, string $action, int $targetId, array $detail): void
    {
        $this->modLog->log([
            'actor_id' => $actor->id(),
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $targetId,
            'before' => null,
            'after' => $detail,
        ]);
    }
}
```

Add the `use App\Core\Telemetry;` import. Keep `RoleAssignmentHistoryRepository::log` on its real `before`/`after` field shape, and mirror `ModerationLogRepository::log` callers for audit fields. Bind in `App::buildContainer` next to `RoleService`, passing the thirteen collaborators (all already bound; Telemetry unconditionally).

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Service/RoleAssignmentServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): RoleAssignmentService grant/revoke/renew with grantor ceiling (TM-PE-02)"
```

---

### Task 12: Assignment routes, controller actions, template section

**Files:**
- Modify: `src/Core/App.php` (three routes beside the /admin/roles block ~:1854)
- Modify: `src/Controller/AdminRoleController.php` (three actions + assignments data in `roleEditView`)
- Modify: `templates/admin/role_edit.php` (assignments section)
- Test: create `tests/Integration/Core/AppRoleAssignmentTest.php`; extend `tests/Integration/Core/AppFeatureFlagTest.php`

**Interfaces:**
- Consumes: Task 11's service API.
- Produces: `POST /admin/roles/{id}/assignments`, `POST /admin/role-assignments/{id}/revoke`, `POST /admin/role-assignments/{id}/renew` — all requireAdmin + flag-gated (404 dark) + noindex + CSRF; grant and renew validation failures re-render 422 with typed input preserved (revoke is narrowing-only and can use a flash redirect).

- [ ] **Step 1: Write the failing tests**

`tests/Integration/Core/AppRoleAssignmentTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppRoleAssignmentTest extends TestCase
{
    private function enableCapabilities(): void
    {
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
    }

    /** @return array{admin:array<string,mixed>,roleId:int} */
    private function seedRole(): array
    {
        $admin = $this->makeAdmin();
        $this->enableCapabilities();
        $this->actingAs($admin);
        $this->post('/admin/roles', [
            'name' => 'Deputy', 'description' => '', 'capabilities' => ['core.thread.lock'],
            'current_password' => 'password123',
        ]);
        $roleId = (int) $this->db->fetchValue("SELECT id FROM roles WHERE role_key LIKE 'custom.%' ORDER BY id DESC LIMIT 1");
        return ['admin' => $admin, 'roleId' => $roleId];
    }

    public function test_routes_are_dark_without_the_flag(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $this->assertStatus(404, $this->post('/admin/roles/1/assignments', ['username' => 'x', 'current_password' => 'password123']));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/revoke'));
        $this->assertStatus(404, $this->post('/admin/role-assignments/1/renew', ['ends_at' => '2030-01-01 00:00', 'current_password' => 'password123']));
    }

    public function test_grant_revoke_renew_journey_no_js(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();

        $r = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $this->assertRedirect($r);

        $page = $this->get('/admin/roles/' . $roleId);
        $this->assertSeeText($page, $subject['username']);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        $this->assertRedirect($this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => gmdate('Y-m-d H:i', time() + 7200), 'current_password' => 'password123',
        ]));
        $this->assertRedirect($this->post('/admin/role-assignments/' . $assignmentId . '/revoke', []));
        $this->assertSeeText($this->get('/admin/roles/' . $roleId), 'revoked');
    }

    public function test_validation_error_rerenders_422_preserving_input(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $r = $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => 'nobody-here', 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => '', 'reason' => 'keep me',
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'keep me'); // anti-draft-loss: typed input survives
    }

    public function test_renew_validation_error_rerenders_422_preserving_input(): void
    {
        ['roleId' => $roleId] = $this->seedRole();
        $subject = $this->makeUser();
        $this->post('/admin/roles/' . $roleId . '/assignments', [
            'username' => $subject['username'], 'scope_type' => 'site', 'scope_id' => '',
            'starts_at' => '', 'ends_at' => gmdate('Y-m-d H:i', time() + 3600),
            'reason' => 'pilot', 'current_password' => 'password123',
        ]);
        $assignmentId = (int) $this->db->fetchValue('SELECT id FROM role_assignments ORDER BY id DESC LIMIT 1');

        $r = $this->post('/admin/role-assignments/' . $assignmentId . '/renew', [
            'ends_at' => 'not-a-date', 'current_password' => 'password123',
        ]);

        $this->assertStatus(422, $r);
        $this->assertSeeText($r, 'not-a-date');
    }
}
```

(Delete the deliberate first-namespace line — it is a copy/paste tripwire; the file has ONE namespace: `Tests\Integration\Core`.)

Extend `AppFeatureFlagTest`: add the three routes to whichever data-provider/matrix asserts capability routes 404 while dark (read `test_capabilities_flag_gates_role_routes` and extend it in kind).

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRoleAssignmentTest.php`
Expected: FAIL — 404 on the grant POST with the flag ON (routes don't exist yet), so the journey test fails.

- [ ] **Step 3: Implement routes + controller + template**

Routes in `App::buildRouter`, immediately after the existing `/admin/roles/{id}/clone` registration (copy the neighboring call style exactly):

```php
$router->post('/admin/roles/{id}/assignments', [AdminRoleController::class, 'assign']);
$router->post('/admin/role-assignments/{id}/revoke', [AdminRoleController::class, 'revokeAssignment']);
$router->post('/admin/role-assignments/{id}/renew', [AdminRoleController::class, 'renewAssignment']);
```

`AdminRoleController` — three actions (mirror `create`'s shape: requireAdmin → gate → try/catch ValidationException. Grant and renew re-render via `roleEditView` with 422 and old assignment input; revoke may flash+redirect because its form is narrowing-only and has no required typed state):

```php
/** @param array<string,string> $params */
public function assign(Request $request, array $params): Response
{
    $admin = $this->requireAdmin();
    $this->gate();
    $roleId = (int) ($params['id'] ?? 0);

    try {
        $this->container->get(RoleAssignmentService::class)->grant(
            $admin,
            (string) $request->post('current_password', ''),
            $roleId,
            $request->str('username'),
            $request->str('scope_type'),
            $request->str('scope_id') !== '' ? (int) $request->str('scope_id') : null,
            $request->str('starts_at') !== '' ? $request->str('starts_at') : null,
            $request->str('ends_at') !== '' ? $request->str('ends_at') : null,
            $request->str('reason') !== '' ? $request->str('reason') : null,
        );
        return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Role assigned.'));
    } catch (ValidationException $e) {
        return $this->roleEditView($roleId, $e->errors, [
            'assignment' => [
                'username' => $request->str('username'), 'scope_type' => $request->str('scope_type'),
                'scope_id' => $request->str('scope_id'), 'starts_at' => $request->str('starts_at'),
                'ends_at' => $request->str('ends_at'), 'reason' => $request->str('reason'),
            ],
        ], 422);
    }
}

/** @param array<string,string> $params */
public function revokeAssignment(Request $request, array $params): Response
{
    $admin = $this->requireAdmin();
    $this->gate();
    $id = (int) ($params['id'] ?? 0);
    $row = $this->container->get(\App\Repository\RoleAssignmentRepository::class)->find($id);
    $roleId = $row === null ? 0 : (int) $row['role_id'];

    try {
        $this->container->get(RoleAssignmentService::class)->revoke($admin, $id, $request->str('reason') !== '' ? $request->str('reason') : null);
        return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Assignment revoked.'));
    } catch (ValidationException $e) {
        $this->flash()->add('Revoke failed: ' . implode(' ', array_map('strval', $e->errors)));
        return $this->noindex($this->redirect('/admin/roles' . ($roleId > 0 ? '/' . $roleId : '')));
    }
}
```

`renewAssignment` mirrors the lookup part of revoke, but **not** the error shape: on `ValidationException`, re-render the role edit page with status 422 so the attempted expiry survives:

```php
/** @param array<string,string> $params */
public function renewAssignment(Request $request, array $params): Response
{
    $admin = $this->requireAdmin();
    $this->gate();
    $id = (int) ($params['id'] ?? 0);
    $row = $this->container->get(\App\Repository\RoleAssignmentRepository::class)->find($id);
    if ($row === null) {
        throw new NotFoundException('Assignment not found.');
    }
    $roleId = (int) $row['role_id'];

    try {
        $this->container->get(RoleAssignmentService::class)->renew(
            $admin,
            (string) $request->post('current_password', ''),
            $id,
            $request->str('ends_at'),
        );
        return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Assignment renewed.'));
    } catch (ValidationException $e) {
        return $this->roleEditView($roleId, $e->errors, [
            'renew_assignment_id' => $id,
            'renew' => ['ends_at' => $request->str('ends_at')],
        ], 422);
    }
}
```

(FOR UPDATE stays inside the service only — controllers use the plain `find()` from Task 10. Import `NotFoundException` if the controller does not already have it.)

`roleEditView` additions: pass `'assignments' => $this->container->get(RoleAssignmentService::class)->listForRole($roleId)` plus `'boards' => $this->container->get(\App\Repository\BoardRepository::class)->all()` (read the repo for its list method name) into the view data.

`templates/admin/role_edit.php` — append an assignments section for custom roles (match the file's existing form/table markup and `$e()` escaping; read the file first). Structure (no inline styles/scripts):

```php
<h2>Assignments</h2>
<table>
  <tr><th>Member</th><th>Scope</th><th>Window</th><th>Status</th><th></th></tr>
  <?php foreach ($assignments as $a): ?>
  <tr>
    <td><?= $e($a['username']) ?></td>
    <td><?= $e($a['scope_type']) ?><?= $a['scope_id'] !== null ? ' #' . (int) $a['scope_id'] : '' ?></td>
    <td><?= $e((string) ($a['starts_at'] ?? 'now')) ?> → <?= $e((string) ($a['ends_at'] ?? 'no expiry')) ?></td>
    <td><?= $e($a['status']) ?></td>
    <td>
      <?php if ($a['status'] !== 'revoked'): ?>
      <form method="post" action="/admin/role-assignments/<?= (int) $a['id'] ?>/revoke"><?= $this->csrfField() ?><button type="submit">Revoke</button></form>
      <form method="post" action="/admin/role-assignments/<?= (int) $a['id'] ?>/renew"><?= $this->csrfField() ?>
        <input type="text" name="ends_at" placeholder="YYYY-MM-DD HH:MM" value="<?= (int) ($old['renew_assignment_id'] ?? 0) === (int) $a['id'] ? $e((string) ($old['renew']['ends_at'] ?? '')) : '' ?>" required>
        <input type="password" name="current_password" placeholder="Your password" required>
        <button type="submit">Renew</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<h3>Assign this role</h3>
<form method="post" action="/admin/roles/<?= (int) $row['role']['id'] ?>/assignments">
  <?= $this->csrfField() ?>
  <label>Member username <input type="text" name="username" value="<?= $e((string) ($old['assignment']['username'] ?? '')) ?>" required></label>
  <label>Scope
    <select name="scope_type">
      <option value="site">site</option><option value="board">board</option><option value="category">category</option>
    </select>
  </label>
  <label>Board/category id <input type="text" name="scope_id" value="<?= $e((string) ($old['assignment']['scope_id'] ?? '')) ?>"></label>
  <label>Starts (UTC) <input type="text" name="starts_at" placeholder="YYYY-MM-DD HH:MM" value="<?= $e((string) ($old['assignment']['starts_at'] ?? '')) ?>"></label>
  <label>Ends (UTC) <input type="text" name="ends_at" placeholder="YYYY-MM-DD HH:MM" value="<?= $e((string) ($old['assignment']['ends_at'] ?? '')) ?>"></label>
  <label>Reason <input type="text" name="reason" value="<?= $e((string) ($old['assignment']['reason'] ?? '')) ?>"></label>
  <label>Your password <input type="password" name="current_password" required></label>
  <button type="submit">Assign role</button>
</form>
```

(Selected-option preservation for `scope_type` old input: add the usual `<?= ... ? ' selected' : '' ?>` per option, matching the file's idiom. Hide the whole section for `kind === 'system'` rows.)

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRoleAssignmentTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppRoleAdminTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(capabilities): no-JS assignment grant/revoke/renew admin surface, dark by flag"
```

---

### Task 13: Change-role action + LastOwnerGuard demote wiring (TM-PE-07)

**Files:**
- Modify: `src/Repository/UserRepository.php` (`setRole`), `src/Repository/ProtectedOwnerRepository.php` (`deactivate`), `src/Repository/SessionRepository.php` (`revokeAllForUser` — only if missing: `grep -n 'revoke' src/Repository/SessionRepository.php` first)
- Modify: `src/Service/UserModerationService.php` (`changeRole`), `src/Core/App.php` (route + ensure `LastOwnerGuard` + `ProtectedOwnerRepository` reach `UserModerationService` **unconditionally**), `src/Controller/AdminUserController.php` (action), the admin users template (role form — locate via `grep -rn 'suspend' templates/admin/` and put the role form beside the suspend/ban controls)
- Test: create `tests/Integration/Core/AppChangeRoleTest.php`

**Interfaces:**
- Consumes: `LastOwnerGuard::assertNotLastOwnerForUpdate(User $user, string $field)`; `ProtectedOwnerRepository::designateOrReactivate(int, ?int)`.
- Produces: `POST /admin/users/{id}/role` (flag-INDEPENDENT); `UserRepository::setRole(int, string): void`; `ProtectedOwnerRepository::deactivate(int): bool`; `SessionRepository::revokeAllForUser(int): void`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppChangeRoleTest extends TestCase
{
    public function test_admin_promotes_member_to_moderator_with_reauth_and_audit(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'moderator', 'current_password' => 'password123',
        ]);
        $this->assertRedirect($r);
        self::assertSame('moderator', $this->users()->find((int) $member['id'])['role']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'change_role' AND target_id = ?",
            [(int) $member['id']],
        ));
    }

    public function test_wrong_password_refuses_and_role_is_unchanged(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'moderator', 'current_password' => 'nope',
        ]);
        self::assertContains($r->status(), [302, 303, 422]); // controller decides the shape; role must not change
        self::assertSame('user', $this->users()->find((int) $member['id'])['role']);
    }

    public function test_demoting_the_last_owner_is_blocked_tm_pe_07(): void
    {
        $admin = $this->makeAdmin();
        // Seed the owner set (0066 semantics): the sole active admin is the owner.
        (new \App\Repository\ProtectedOwnerRepository($this->db))->designate((int) $admin['id'], null);
        $this->actingAs($admin);

        $r = $this->post('/admin/users/' . $admin['id'] . '/role', [
            'role' => 'user', 'current_password' => 'password123',
        ]);
        self::assertContains($r->status(), [302, 303, 422]); // refused (flash or 422)
        self::assertSame('admin', $this->users()->find((int) $admin['id'])['role']);
    }

    public function test_demoting_a_non_last_owner_deactivates_their_owner_row_and_revokes_sessions(): void
    {
        $owner1 = $this->makeAdmin();
        $owner2 = $this->makeAdmin();
        $owners = new \App\Repository\ProtectedOwnerRepository($this->db);
        $owners->designate((int) $owner1['id'], null);
        $owners->designate((int) $owner2['id'], null);
        (new \App\Repository\SessionRepository($this->db))->create([
            'id' => hash('sha256', 'target-session-' . $owner2['id']),
            'user_id' => (int) $owner2['id'],
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => 'phpunit-target',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        $this->actingAs($owner1);

        $this->assertRedirect($this->post('/admin/users/' . $owner2['id'] . '/role', [
            'role' => 'user', 'current_password' => 'password123',
        ]));
        self::assertSame('user', $this->users()->find((int) $owner2['id'])['role']);
        self::assertFalse($owners->isActiveOwner((int) $owner2['id']));
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ? AND revoked_at IS NULL',
            [(int) $owner2['id']],
        ));
    }

    public function test_promote_to_admin_designates_protected_owner(): void
    {
        $admin = $this->makeAdmin();
        (new \App\Repository\ProtectedOwnerRepository($this->db))->designate((int) $admin['id'], null);
        $member = $this->makeUser();
        $this->actingAs($admin);

        $this->assertRedirect($this->post('/admin/users/' . $member['id'] . '/role', [
            'role' => 'admin', 'current_password' => 'password123',
        ]));
        self::assertTrue((new \App\Repository\ProtectedOwnerRepository($this->db))->isActiveOwner((int) $member['id']));
    }
}
```

(The route works with `capabilities` DARK — no flag setup anywhere in this file; that IS the assertion of flag-independence.)

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/phpunit tests/Integration/Core/AppChangeRoleTest.php`
Expected: FAIL — 404 (route missing).

- [ ] **Step 3: Implement**

`UserRepository` (append; mirror `setStatus`'s style):

```php
public function setRole(int $id, string $role): void
{
    $this->db->run('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
}
```

`ProtectedOwnerRepository` (append):

```php
/** Demote-path reconcile: the owner row must not outlive the authority it mirrors. */
public function deactivate(int $userId): bool
{
    return $this->db->run('UPDATE protected_owners SET is_active = 0 WHERE user_id = ?', [$userId])->rowCount() > 0;
}
```

`SessionRepository`: if a revoke-all method is missing, append:

```php
public function revokeAllForUser(int $userId): void
{
    $this->db->run('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$userId]);
}
```

`UserModerationService::changeRole` (new method; ctor gains `private ?LastOwnerGuard $ownerGuard = null`, `private ?ProtectedOwnerRepository $owners = null`, `private ?SessionRepository $sessions = null`, `private ?ReauthGate $reauth = null`, `private ?CapabilityResolver $resolver = null` — **the container passes ALL of these unconditionally**; nullable only so existing hand-constructed test instances keep compiling. Read the ctor first and follow its parameter style):

```php
/** @param 'user'|'moderator'|'admin' $newRole */
public function changeRole(User $admin, string $currentPassword, int $userId, string $newRole): void
{
    if (!$admin->isAdmin()) {
        throw new ForbiddenException('Admin access required.');
    }
    if ($this->ownerGuard === null || $this->owners === null || $this->sessions === null || $this->reauth === null || $this->resolver === null) {
        throw new \LogicException('Role-change dependencies are not wired.');
    }
    $this->writeGate->assertCanWrite($admin);
    $this->reauth->requirePassword($admin, $currentPassword);
    if (!in_array($newRole, ['user', 'moderator', 'admin'], true)) {
        throw new ValidationException(['role' => 'Unknown role.']);
    }
    $row = $this->users->find($userId);
    if ($row === null || ($row['status'] ?? '') === 'deleted') {
        throw new ValidationException(['role' => 'No such member.']);
    }
    if (($row['role'] ?? '') === $newRole) {
        throw new ValidationException(['role' => 'The member already has this role.']);
    }
    $target = User::fromRow($row);

    $this->db->transaction(function () use ($admin, $target, $userId, $row, $newRole): void {
        if ($target->isAdmin() && $newRole !== 'admin') {
            $this->ownerGuard->assertNotLastOwnerForUpdate($target, 'role');
            $this->owners->deactivate($userId);
        }
        $this->users->setRole($userId, $newRole);
        if ($newRole === 'admin') {
            $this->owners->designateOrReactivate($userId, $admin->id());
        }
        $this->sessions->revokeAllForUser($userId);
        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => 'change_role',
            'target_type' => 'user',
            'target_id' => $userId,
            'before' => ['role' => (string) $row['role']],
            'after' => ['role' => $newRole],
        ]);
    });
    $this->resolver->invalidate();
}
```

(The ctor params stay nullable only to keep existing hand-constructed tests compiling, but `changeRole()` must throw before mutating if any required dependency is absent; do not use nullsafe calls in this method. Match `log->log` field names to the service's existing audit calls — read one. `ForbiddenException`/`ValidationException`/`\LogicException` imports per the file.)

Route (beside the other `/admin/users` registrations): `$router->post('/admin/users/{id}/role', [AdminUserController::class, 'changeRole']);`

`AdminUserController::changeRole` (mirror the neighboring suspend/ban action shape — requireAdmin, call service, flash+redirect on success, on `ValidationException` flash the errors and redirect back):

```php
/** @param array<string,string> $params */
public function changeRole(Request $request, array $params): Response
{
    $admin = $this->requireAdmin();
    $userId = (int) ($params['id'] ?? 0);

    try {
        $this->container->get(UserModerationService::class)->changeRole(
            $admin,
            (string) $request->post('current_password', ''),
            $userId,
            $request->str('role'),
        );
        return $this->redirectWithFlash($this->backToUsers($request), 'Role updated.');
    } catch (ValidationException $e) {
        $this->flash()->add('Role change failed: ' . implode(' ', array_map('strval', $e->errors)));
        return $this->redirect($this->backToUsers($request));
    }
}
```

(`backToUsers` = whatever return-path helper the controller already uses for its other POST actions — read them and reuse; if none exists, redirect to `'/admin/users'`.) Container: update the `UserModerationService` binding to append the five new collaborators unconditionally. Template: in the admin users surface (grep target above), beside the suspend form add:

```php
<form method="post" action="/admin/users/<?= (int) $u['id'] ?>/role">
  <?= $this->csrfField() ?>
  <select name="role">
    <option value="user"<?= $u['role'] === 'user' ? ' selected' : '' ?>>user</option>
    <option value="moderator"<?= $u['role'] === 'moderator' ? ' selected' : '' ?>>moderator</option>
    <option value="admin"<?= $u['role'] === 'admin' ? ' selected' : '' ?>>admin</option>
  </select>
  <input type="password" name="current_password" placeholder="Your password" required>
  <button type="submit">Change role</button>
</form>
```

(match the template's actual row variable name and form markup idiom).

- [ ] **Step 4: Run to verify green**

Run: `vendor/bin/phpunit tests/Integration/Core/AppChangeRoleTest.php tests/Integration/Core/AppProtectedOwnerTest.php tests/Integration/Service/RepairProtectedOwnersTest.php && vendor/bin/phpunit --testsuite integration`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(admin): change-role action with LastOwnerGuard demote block (TM-PE-07 slice)"
```

---

### Task 14: Role-edit propagation + full-suite gate (TM-PE-08)

**Files:**
- Test: extend `tests/Integration/Core/AppEnforcementCutoverTest.php`

**Interfaces:** consumes everything above; produces the TM-PE-08 evidence test.

- [ ] **Step 1: Write the failing/pinning test**

```php
public function test_capability_removed_from_role_denies_all_assignees_tm_pe_08(): void
{
    $admin = $this->makeAdmin();
    $deputy = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory());
    $author = $this->makeUser();
    $t = $this->makeThread($board, $author);
    $roleId = $this->makeCustomRoleWithAssignment($admin, $deputy, ['core.thread.pin', 'core.thread.lock'], (int) $board['id']);
    $this->withCapabilitiesEnforced();

    $this->actingAs($deputy);
    $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/pin'));    // holds the key (pin toggles, no side effects on posting)

    // Admin edits the role: pin removed, lock kept.
    $this->actingAs($admin);
    $this->assertRedirect($this->post('/admin/roles/' . $roleId, [
        'name' => 'Deputy', 'description' => '', 'capabilities' => ['core.thread.lock'],
        'current_password' => 'password123',
    ]));

    // Next direct request: every assignee is denied the removed key.
    $this->actingAs($deputy);
    $this->assertStatus(403, $this->post('/mod/t/' . $t['thread_id'] . '/pin'));
    // The kept key still works — propagation is per-key, not per-role.
    $this->assertRedirect($this->post('/mod/t/' . $t['thread_id'] . '/lock'));
}
```

(Verify the pin/lock route paths via `grep -n "'/mod/t/{id}" src/Core/App.php` and adjust; the LAST pin request must be a clean 403.)

- [ ] **Step 2: Run to verify it fails/passes for the right reason**

Run: `vendor/bin/phpunit tests/Integration/Core/AppEnforcementCutoverTest.php --filter tm_pe_08`
Expected: PASS immediately (update flows bump `roles.version`, decisions read live tables per request). This is the acceptance pin. If it unexpectedly fails, the memo (Task 3) is leaking across app rebuilds — fix there, not here.

- [ ] **Step 3: Full-suite gate**

```bash
RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress
vendor/bin/phpunit --no-progress
vendor/bin/phpunit --no-progress
```
Expected: all green (baseline was 1585 tests / 8070 assertions; expect ~+45-60 tests). Two consecutive reused-schema runs must match (reference-table seed gotcha: this increment adds NO seed migration, so no `AppSearchTest` preserve-list change is expected — if reused runs differ, investigate before proceeding).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "test(capabilities): role-edit propagation pin (TM-PE-08) + full-suite gate"
```

---

### Task 15: Evidence commands — parity, budgets, upgrade, fixtures flips

**Files:**
- Modify: `docs/phase5/threat-models/fixtures.json` (TM-PE-02/03/06/07/08 → `implemented` + `test=`)
- Regenerate: `docs/evidence/phase5/resolver-parity.md`; update `docs/evidence/phase5/performance-budgets.md` (resolver re-measure + the two new MEASURED-no-target rows)

**Interfaces:** consumes the finished behavior; produces the machine-checked evidence.

- [ ] **Step 1: Flip the five fixtures**

In `fixtures.json`, set for each (keep the existing JSON shape — read a neighboring `implemented` entry and mirror its fields exactly):
- TM-PE-02 → `tests/Integration/Service/RoleAssignmentServiceTest.php`
- TM-PE-03 → `tests/Integration/Security/CapabilityResolverTest.php`
- TM-PE-06 → `tests/Integration/Service/ResolverParityTest.php` (corpus under the enforcing resolver) — note the second path `tests/Integration/Core/AppEnforcementCutoverTest.php` if the schema supports multiple; otherwise cite the parity test.
- TM-PE-07 → `tests/Integration/Core/AppChangeRoleTest.php`
- TM-PE-08 → `tests/Integration/Core/AppEnforcementCutoverTest.php`

Run: `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php` → PASS (it validates statuses/paths).

- [ ] **Step 2: Re-run the parity corpus + budgets + upgrade rehearsal**

```bash
php bin/console verify:resolver-parity
APP_ENV=testing php bin/console verify:phase5-budgets
APP_ENV=testing DB_DATABASE=retroboards_e2e php bin/console verify:upgrade --force
```
Expected: parity `1551 tuples, 1551 agreed, 0 mismatches` (regenerates `resolver-parity.md` — commit the refreshed doc); budgets `resolver.p95 ... MEASURED (PASS)` vs 5 ms; upgrade `17/17 checks passed` (no new migration). If parity tuple count changed, the fixture seeder changed — it must not have; investigate.

- [ ] **Step 3: Record the two no-target measurements**

Append to `docs/evidence/phase5/performance-budgets.md` (match the doc's row format) two rows labeled `MEASURED (no D11 target)` — §11.3 requires the measurement; no gate value exists:
- `assignment-change propagation`: structurally zero cross-request (decisions read live tables; `invalidate()` covers in-request) — record the p95 of 200 iterations of `revoke → resolver->can` measured with a short throwaway script: `php -r` loop constructing the service exactly as `RoleAssignmentServiceTest` does against the test DB, `hrtime()` around the pair; paste the number and the loop into the doc's methodology note.
- `simulator duration`: p95 of 200 `PermissionSimulatorService::simulate()` calls on the F9 fixture, same `hrtime()` technique.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "test(phase5): TM-PE fixtures implemented; parity/budgets/upgrade evidence refreshed"
```

---

### Task 16: Browser + a11y evidence (dark-surface pattern)

**Files:**
- Create: `tests/browser/role-assignments.spec.ts`
- Evidence: `docs/evidence/browser/{desktop,mobile}/62-admin-role-assigned.png`, `63-admin-role-assignment-revoked.png`

**Interfaces:** consumes the Task 12 UI. Follow the existing dark-surface spec pattern — read `tests/browser/api-tokens.spec.ts` first (it has the `RB_BROWSER_DARK_SURFACES` skip guard, the theme-safe-mode neutralization, and the admin-login helper; the user-switch trap: call `page.context().clearCookies()` before a second same-page login).

- [ ] **Step 1: Write the spec**

Mirror `api-tokens.spec.ts` structure exactly; journey: enable `capabilities` via the admin features surface (or the seed hook the pattern uses), create a custom role (`core.thread.lock`), assign it to a seeded member at board scope (fill the no-JS form), screenshot `62-admin-role-assigned.png`, revoke it, assert the `revoked` status renders, screenshot `63-admin-role-assignment-revoked.png`, then run the spec's axe scan block on `/admin/roles/{id}` and assert no serious/critical violations — copy the axe helper invocation from `a11y.spec.ts`/`api-tokens.spec.ts`.

- [ ] **Step 2: Run it (both projects)**

```bash
cd tests/browser && RB_BROWSER_DARK_SURFACES=1 npx playwright test role-assignments.spec.ts
```
Expected: all passed across desktop + mobile; PNGs written under `docs/evidence/browser/`. Then `npm run evidence` → the standing count (57 passed / 1 skipped baseline; dark-surface specs still skip without the env var) and `npm run a11y` → all passed. Note: 3 gate-a.spec.ts failures (tests 326/943/1005) are pre-existing on main — do not chase them (see repo memory).

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "test(browser): role-assignment no-JS journey + axe evidence (62-63)"
```

---

### Task 17: Docs, ADR 0016, runbook + rehearsal, ledger, status

**Files:**
- Create: `docs/adr/0016-inc6-enforcement-cutover-decisions.md`, `docs/runbooks/capabilities.md`, `docs/evidence/phase5/capabilities-fallback-rehearsal.md`
- Modify: `docs/phase5/capability-taxonomy.md` (§7 #5/#6 resolutions, §8 DM-override note, re-anchor touched §4 lines), `docs/phase5/requirement-ledger.json` (states + evidence), `PHASE_5_STATUS.md`, `docs/evidence/**/deploy-dark-features.md` (route counts — locate via `ls docs/evidence` / grep)

**Interfaces:** consumes everything; produces the process closure. This task is last-but-one because `Phase5EvidenceMapTest` requires every cited evidence file to exist on the commit.

- [ ] **Step 1: ADR 0016**

Follow the ADR 0012 header/sign-off pattern (read it). Content: Context (Inc 6 cutover; parity + E1 prerequisites met) → Decisions: (1) taxonomy §7 quirk 5 resolved as the tightening, owner-accepted 2026-07-04, test-pinned in `AppEnforcementCutoverTest`; (2) quirk 6 boundary ratified; (3) cutover breadth = board/content surface plus the four board-roster POST command endpoints; the remaining admin console is recorded follow-up with the EnforcedCapabilities clamp as bridge; (4) projection stays authoritative, no import migration; (5) change-role action built, LastOwnerGuard demote wiring; (6) CAPABILITIES_MODE two-mode posture. Consequences: enforcement behavior deltas (suspended-staff views), rollback levers, the clamp. Owner acceptance line: accepted 2026-07-04 via the interactive design review (this session's AskUserQuestion decisions).

- [ ] **Step 2: Runbook `docs/runbooks/capabilities.md`**

Follow `docs/runbooks/passkeys.md` structure. Sections: What the flag/mode control; Staged rollout (flag on shadow → soak `resolver.shadow_mismatch` [expected known mismatch: suspended-staff pending views] → `CAPABILITIES_MODE=enforce` → pilot one narrow custom role on a test board → broaden); Emergency fallback (capture `resolver.enforce_mismatch`/`authority.enforce_denied` telemetry + `moderation_log` state FIRST, then lever 1 `CAPABILITIES_MODE=shadow` [decisions revert, UI stays] or lever 2 `features.capabilities=false` [all dark, grants inert]); Immediate revoke procedure (`/admin/roles/{id}` → Revoke — no password needed by design); Parity reconcile (`php bin/console verify:resolver-parity`, `php bin/console repair`); Owner recovery pointer (existing owner runbook / `repair`); The clamp (what "not yet enforceable" means).

- [ ] **Step 3: Rehearse the fallback drill and transcript it**

On the local dev instance (NOT production; uses the dev DB):

```bash
php bin/console migrate   # ensure current
php -S 127.0.0.1:8000 -t public public/index.php &
# In settings: enable capabilities (features override) — via /admin features UI or SQL insert mirroring AppFeatureFlagTest's SettingRepository call.
CAPABILITIES_MODE=enforce php -S 127.0.0.1:8001 -t public public/index.php &
# Exercise: curl -s -o /dev/null -w '%{http_code}' each of: a lock POST as admin (expect 302/303), the approval queue as a suspended mod fixture (expect 403 on :8001, non-403 on :8000).
# Fallback: kill :8001, restart WITHOUT the env var, repeat the suspended-mod probe (expect legacy behavior) — lever 1 verified.
# Then set features.capabilities=false in settings and probe /admin/roles (expect 404) — lever 2 verified.
```

Capture the command/output transcript into `docs/evidence/phase5/capabilities-fallback-rehearsal.md` with date + commit. Kill the servers when done.

- [ ] **Step 4: Taxonomy + ledger + status edits**

- `capability-taxonomy.md` §7 #5: append "**Resolved 2026-07-04 (ADR 0016):** the tightening is accepted; under `CAPABILITIES_MODE=enforce` suspended staff are denied `core.content.view_pending`; pinned by `AppEnforcementCutoverTest`." §7 #6: append "**Ratified 2026-07-04 (ADR 0016).**" §8: add the `DirectMessageService` admin override of member DM-off preference as a recorded non-capability bypass. Re-anchor the §4 lines your tasks touched (at minimum `SolvedAnswerService::authorize:195`, `assertStaff:284`, `AntiAbuseService:62`, the ModerationService drift) and bump the doc's pinned commit reference.
- `requirement-ledger.json`: GA-DOD-10 → `"state": "R4"`, evidence += `tests/Integration/Core/AppEnforcementCutoverTest.php`, `docs/runbooks/capabilities.md`; notes: enforcement landed Inc 6, mode-gated, two divergences resolved via ADR 0016. GA-DOD-11 → `"state": "R4"`, evidence: `src/Service/RoleAssignmentService.php`, `tests/Integration/Service/RoleAssignmentServiceTest.php`, `tests/Integration/Core/AppRoleAssignmentTest.php`, `tests/browser/role-assignments.spec.ts`, `docs/runbooks/capabilities.md`; notes: R5 at staged enablement. GA-DOD-12 → `"state": "R3"`, evidence: `tests/Integration/Core/AppChangeRoleTest.php` (+ keep existing notes, append: demote path wired Inc 6; provider-unlink Inc 8, invitations Inc 9). Run `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php` → PASS.
- `PHASE_5_STATUS.md`: replace the sentence `Increment 6 (enforcement cutover) stays blocked until shadow soak.` with `Increment 6 (P5-08/09 enforcement cutover + assignment lifecycle) landed 2026-07-04 deploy-dark: AuthorityGate mode lever (CAPABILITIES_MODE, shadow default), per-action keys across the board/content surface plus board-roster POST commands, grant/revoke/renew with grantor ceiling, change-role owner-loss guard, ADR 0016; the shadow soak is a §13.1 enablement step (runbook: docs/runbooks/capabilities.md).` Add an "## Increment 6 landed (2026-07-04)" section following the Inc 4/7 section format: bullets for seam/mode, cutover surface, clamp, assignment lifecycle, change-role, evidence (test counts from Task 14's run, parity re-run numbers, budget lines, browser/a11y counts, upgrade 17/17), and the deferred list (remaining admin-console cutover, expiry sweeper, import). Update the `**Last updated:**` line and the suite numbers in the header.
- deploy-dark inventory doc: +3 flag-gated routes (assignments), +1 flag-independent admin route (change-role — listed as not-flag-gated), counts reconciled.

- [ ] **Step 5: Final gates + commit**

```bash
vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php tests/Unit/Core/ThreatModelIndexTest.php tests/Unit/Core/MigrationLedgerTest.php
RB_TEST_FRESH=1 vendor/bin/phpunit --no-progress && vendor/bin/phpunit --no-progress
```
Expected: all green. Record the final numbers in PHASE_5_STATUS (edit before the final commit).

```bash
git add -A
git commit -m "docs(phase5): Inc 6 closeout — ADR 0016, capabilities runbook + rehearsal, ledger R4/R4/R3"
```

---

### Task 18: Final verification sweep

- [ ] Run the verify skill flow end-to-end on the branch: fresh suite, two reused runs, browser evidence (`npm run evidence`, `npm run a11y`, the dark-surface spec), `verify:resolver-parity`, `verify:phase5-budgets`, `verify:upgrade` — every command's actual output must match what PHASE_5_STATUS claims (fix the doc, not the claim, if they differ).
- [ ] `git log --oneline main..HEAD` — review the commit narrative; no fixup noise.
- [ ] Leave the branch UNMERGED; report completion with the evidence summary (owner decides merge/PR — repo precedent: Inc 7 passkeys).
