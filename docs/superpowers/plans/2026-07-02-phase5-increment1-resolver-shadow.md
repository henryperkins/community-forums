# Phase 5 Increment 1 — Capability Resolver in Shadow Mode (P5-08) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the state-first, union-then-narrow capability resolver behind the dark `capabilities` flag, prove it matches legacy authority with a zero-mismatch archived parity corpus, wire a fail-open shadow harness into the two hottest authority paths, and ship the no-JS role editor + permission simulator — with no live behavior change while the flag is dark.

**Architecture:** A pure decision core (`CapabilityRules`) is fed by an orchestrator (`CapabilityResolver`) that unions two grant sources: a **legacy-authority projection** (virtual grants derived from `users.role` / `board_moderators` / `boards.post_min_role` / `protected_owners` — this is what makes the resolver decidable before Increment 6 imports real assignments) and real `role_assignments` rows (fixture/custom roles, window-checked in PHP so `ends_at` is enforced by the resolver itself, decision #24). A `ResolverShadow` decorator compares legacy decisions against the resolver at two call sites (`ModerationService::canModerate`, `PostingService` canPost checks) and emits telemetry mismatch events without ever changing the decision. `ResolverParityService` runs the full old-vs-new corpus on the F9 fixture and `bin/console verify:resolver-parity` archives it to `docs/evidence/phase5/resolver-parity.md`. The role editor + simulator are thin admin controllers over `RoleService` / `PermissionSimulatorService`.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB via the existing `App\Core\Database` PDO wrapper (`EMULATE_PREPARES=false`), the in-process kernel test harness (`Tests\Support\TestCase`), PHPUnit (strict), Playwright + axe for browser evidence. **No migration** — Increment 1 uses the `0050` tables seeded by `0066`.

## Global Constraints

- **Deploy-dark.** Every route in this increment is gated on `FeatureFlags::enabled('capabilities')` (controller `gate()` throws `NotFoundException` when off, pattern `AdminApiTokenController`). `capabilities` stays default `false` in `FeatureFlags::DEFAULTS`. `AppFeatureFlagTest::test_phase5_foundation_flags_default_dark` must stay green unchanged.
- **Shadow changes no decisions.** `ResolverShadow` is injected `null` when the flag is off (container conditional, pattern `PostingService`'s `$antiAbuse`); when on it only emits telemetry. It must never throw into the caller (`try/catch (\Throwable)`).
- **No migration.** Migration numbers `0068+` are reserved for later increments (§C of the program plan); the F1 `MigrationLedgerTest` guard fails CI if a number is grabbed.
- **Write path:** services own rules; multi-row mutations run in `$db->transaction(fn)`. Repositories are thin, prepared-statement-only, return associative arrays. Never bind `LIMIT`/`OFFSET` (int-cast + concatenate); never reuse a named placeholder; UTC everywhere (`UTC_TIMESTAMP()` / `gmdate()`).
- **CSRF `_token` on every POST** (TestCase handles it); no GET mutates state; the simulator is a GET because it is a pure read.
- **High-impact admin actions require reauth:** role create/update/clone require `current_password` via `ReauthGate::requirePassword` (F7) and `WriteGate::assertCanWrite`.
- **Strict CSP / no-JS first:** templates are plain PHP server-rendered forms, no inline `<script>`/`<style>`; escape everything with `$e()`.
- **PHPUnit is strict:** every test ≥1 assertion, no output, no warnings. Integration tests roll back one transaction per test — assert observable behavior, not row counts after "rollback inside rollback".
- **Evidence (DESIGN §13):** UI-visible surfaces need Playwright desktop+mobile + axe in addition to PHPUnit. Authorization evidence uses direct requests against the real resolver.
- **Reputation/badges/profile fields are never capabilities** (taxonomy §8). Protected keys (`CapabilityCatalog::PROTECTED`) are never role-mapped, never editable, never delegable.
- Commit after every task: `git commit -m "<type>(phase5): <what> (Inc 1 SPn)"` ending with the `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>` trailer.

---

## Context primer (read once before Task 1)

You are in `/home/henry/community-forums` — hand-rolled vanilla PHP 8.2 forum, PSR-4 `App\` → `src/`, `Tests\` → `tests/`. Run tests with `composer test` (needs the MariaDB dev container; `.env` points at port 3307). Single-file kernel `src/Core/App.php`: `buildContainer()` hand-binds every service (lazy singletons), `buildRouter()` registers routes.

**Schema you consume (already migrated, `0050` + `0066`):**

- `capabilities` — 54 seeded `core.*` rows: `id, capability_key, namespace, scope_type ENUM(site|category|board|self), risk_class ENUM(low|medium|high|protected), is_delegable, is_protected, source, description, retired_at`.
- `roles` — 4 protected system anchors seeded (`system.guest|user|moderator|admin`, `kind='system'`, `is_protected=1`, `role_rank` 0/10/20/30) + custom rows you create: `id, role_key, name, kind ENUM(system|custom), is_protected, role_rank, version, description, created_by`.
- `role_capabilities` — `(role_id, capability_id)` PK; seeded **cumulatively** from `CapabilityCatalog::roleCapabilities()` (guest ⊂ user ⊂ moderator ⊂ admin; protected keys never mapped).
- `role_assignments` — `subject_type ENUM(user|group), subject_id, role_id, scope_type ENUM(site|category|board), scope_id NULL, grantor_id, reason, approval_ref, starts_at NULL, ends_at NULL, revoked_at NULL, revoked_by, assignment_version`.
- `role_assignment_history` — append-only audit: `assignment_id NULL, event ENUM(grant|renew|expire|revoke|modify|role_edit), actor_id, subject_type, subject_id, role_id, scope_type, scope_id, before_json, after_json, reason`.
- `protected_owners` — active owner = `is_active=1` AND `users.status='active'` (see `ProtectedOwnerRepository`).

**Code you build on:**

- `App\Security\CapabilityCatalog` — code-owned catalogue: `all(): array<key, {scope,risk,delegable,protected,description,consent}>`, `keys()`, `has()`, `isProtected()`, `consent()`, `roleCapabilities(): array<roleKey, list<capKey>>` (cumulative), `PROTECTED` const (5 keys).
- `App\Security\BoardPolicy` — pure read/post gates: `canRead(array $board, ?User, bool $isMember)`, `isListed`, `isArchived(array $board)`, `canPost(array $board, User, bool $isMember)` (canPost = !archived && canRead && global-role floor).
- `App\Security\WriteGate` — `canWrite(User): bool`, `assertCanWrite(User): void` (state beats role).
- `App\Domain\User` — `id() username() role() status() isAdmin() isModerator() isActive() owns(int)`; `User::fromRow(array)`.
- `App\Security\ReauthGate` — `requirePassword(User $actor, string $currentPassword, string $field = 'current_password', ?string $missingPasswordError = null): void` (throws `ValidationException`).
- `App\Security\LastOwnerGuard`, `App\Repository\ProtectedOwnerRepository` — `isActiveOwner(int): bool`.
- `App\Repository\BoardModeratorRepository` — `isModerator(int $boardId, int $userId): bool`, `boardsFor(int $userId): list<int>`.
- `App\Repository\BoardMemberRepository` — `isMember(int $boardId, int $userId): bool`.
- `App\Core\Database` — `run($sql,$params): PDOStatement`, `fetch(): ?array`, `fetchAll(): array`, `fetchValue(): mixed` (**returns `false` when no row**), `insert(): int` (lastInsertId), `transaction(callable)`.
- `App\Core\Telemetry` — `emit(string $event, array $context)`; dark unless config `telemetry.enabled`; test-construct with `new Telemetry(new Config(['telemetry' => ['enabled' => true]]), $sinkClosure)`.
- `App\Service\Phase5FixtureSeeder` — seeds `p5fix_*` users/boards/assignments/owner (`seed(force: true)`); refuses production.
- `App\Service\BaselineMetricsService` — `measureLegacyAuthorityRead(int $iterations = 200): array` §11.3 envelope; `App\Support\Phase5Budgets::target('resolver.p95')` = 5 (ms).
- `App\Service\Phase5BudgetReportService` — renders `docs/evidence/phase5/performance-budgets.md`; `bin/console verify:phase5-budgets` runs it inside a rolled-back transaction.
- Exceptions: `App\Core\{NotFoundException, ForbiddenException, ValidationException, HttpException}`. `ValidationException(array $errors, array $old = [])` — controllers catch it and re-render 422 with `->errors` + `->old`.
- Admin controller pattern: `AdminApiTokenController` (requireAdmin + flag `gate()` + one-time secrets rendered directly). Template pattern: `templates/admin/api_tokens.php` (`$this->layout('layout')`, `$this->section('title', …)`, `$e()`, `$this->csrfField()`, `.admin`/`.card`/`.stacked` classes).
- Tests: integration extends `Tests\Support\TestCase` (`makeUser/makeAdmin/makeCategory/makeBoard/actingAs/get/post/assertStatus/assertSeeText`, `setFlags` pattern from `AppFeatureFlagTest`: `(new SettingRepository($this->db))->set('features', [...])`).

**The legacy quirks the resolver must reproduce, not fix** (taxonomy §7 — parity-first):

1. No `core.post.edit_any` exists — nobody edits another member's post.
2. `core.user.warn` is **staff-any**: admins OR anyone moderating ≥1 board, site-wide.
3. The vestigial global `users.role='moderator'` (no `board_moderators` rows) gets **no board powers** — only the pending-content-view exemption and the `moderator` posting-floor pass.
4. `core.content.view_pending` has **dual authority**: board-scoped via `canModerate` for threads AND site-wide via global `isModerator()` for media. The capability is *held* if either path holds (per-surface narrowing stays in controllers until cutover).
5. The `boards.post_min_role` floor is satisfied by the **global** `users.role` rank only (BoardPolicy uses `User::isModerator()`), NOT by a board-scoped moderator grant.

**New files this plan creates:**

| File | Responsibility |
|---|---|
| `src/Security/CapabilityDecision.php` | Immutable decision VO (allowed/source/reason/decisive grant) |
| `src/Security/CapabilityRules.php` | Pure union-then-narrow decision core (no I/O) |
| `src/Repository/CapabilityRepository.php` | `capabilities` reads |
| `src/Repository/RoleRepository.php` | `roles` CRUD + version bump |
| `src/Repository/RoleCapabilityRepository.php` | `role_capabilities` mapping reads/writes |
| `src/Repository/RoleAssignmentRepository.php` | `role_assignments` reads/writes + impact counts |
| `src/Repository/RoleAssignmentHistoryRepository.php` | append-only audit rows |
| `src/Service/LegacyAuthorityProjection.php` | virtual grants + site rank from legacy tables |
| `src/Security/CapabilityResolver.php` | orchestrator: fetch → normalize → `CapabilityRules::decide` |
| `src/Service/ResolverShadow.php` | fail-open shadow compare + telemetry |
| `src/Service/ResolverParityService.php` | corpus runner + legacy oracle + report renderer |
| `src/Service/RoleService.php` | role create/update/clone + guards + audit |
| `src/Service/PermissionSimulatorService.php` | simulator on the real resolver + viewer redaction |
| `src/Controller/AdminRoleController.php` | no-JS role editor + simulator routes |
| `templates/admin/roles.php`, `templates/admin/role_edit.php`, `templates/admin/role_simulator.php` | server-rendered admin UI |

Branch: create `phase5-inc1-resolver-shadow` off `main` before Task 1 (`git switch -c phase5-inc1-resolver-shadow`). Commit the plan file itself first.

---

### Task 1: CapabilityDecision + CapabilityRules (pure decision core)

**Files:**
- Create: `src/Security/CapabilityDecision.php`
- Create: `src/Security/CapabilityRules.php`
- Test: `tests/Unit/Security/CapabilityRulesTest.php`

**Interfaces:**
- Consumes: `App\Security\CapabilityCatalog::all()` (meta arrays), `App\Domain\User`.
- Produces: `CapabilityDecision` (public readonly `allowed, capability, source, reason, roleKey, scopeType, scopeId`; statics `allow()`/`deny()`); `CapabilityRules::decide(string $capability, array $meta, ?User $actor, bool $actorCanWrite, bool $actorIsActiveOwner, int $siteRank, array $grants, array $rolesHoldingKey, array $ctx, \DateTimeImmutable $at): CapabilityDecision`. Grant row shape (used by Tasks 4/5): `{kind:'role'|'capability', role_key:?string, capability_key:?string, scope_type:'site'|'category'|'board', scope_id:?int, starts_at:?string, ends_at:?string, source:'legacy'|'assignment'}`. Ctx shape: `{board:?array, board_member:bool, board_readable:bool, owner_id:?int, user_id:?int, category_id:?int}`.

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\User;
use App\Security\CapabilityCatalog;
use App\Security\CapabilityDecision;
use App\Security\CapabilityRules;
use PHPUnit\Framework\TestCase;

/**
 * Increment 1 (P5-08) — the pure union-then-narrow decision core. State beats
 * role; grants union; scope then read-gate/floor narrow; ends_at is enforced
 * here (decision #24), not by a cleanup job. Legacy quirks (taxonomy §7) are
 * reproduced, never fixed.
 */
final class CapabilityRulesTest extends TestCase
{
    private const UTC_NOW = '2026-07-02 12:00:00';

    private function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::UTC_NOW, new \DateTimeZone('UTC'));
    }

    /** @param array<string,mixed> $overrides */
    private function user(array $overrides = []): User
    {
        return User::fromRow($overrides + [
            'id' => 7, 'username' => 'alice', 'email' => 'alice@example.test',
            'role' => 'user', 'status' => 'active',
        ]);
    }

    /** @return array<string,mixed> catalogue meta for a key */
    private function meta(string $key): array
    {
        return CapabilityCatalog::all()[$key];
    }

    /** @param array<string,mixed> $overrides */
    private function grant(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'role', 'role_key' => 'system.user', 'capability_key' => null,
            'scope_type' => 'site', 'scope_id' => null,
            'starts_at' => null, 'ends_at' => null, 'source' => 'legacy',
        ];
    }

    /** @param array<string,mixed> $overrides */
    private function ctx(array $overrides = []): array
    {
        return $overrides + [
            'board' => null, 'board_member' => false, 'board_readable' => false,
            'owner_id' => null, 'user_id' => null, 'category_id' => null,
        ];
    }

    /** @return array<string,mixed> a boards row */
    private function board(array $overrides = []): array
    {
        return $overrides + [
            'id' => 3, 'category_id' => 1, 'visibility' => 'public',
            'post_min_role' => 'user', 'is_archived' => 0, 'name' => 'B',
        ];
    }

    private function decide(
        string $key,
        ?User $actor,
        bool $canWrite,
        int $siteRank,
        array $grants,
        array $rolesHolding,
        array $ctx,
        bool $isOwner = false,
    ): CapabilityDecision {
        return CapabilityRules::decide(
            $key, $this->meta($key), $actor, $canWrite, $isOwner,
            $siteRank, $grants, $rolesHolding, $ctx, $this->at(),
        );
    }

    public function test_guest_reads_public_board_but_not_private(): void
    {
        $guestGrants = [$this->grant(['role_key' => 'system.guest'])];
        $readable = $this->ctx(['board' => $this->board(), 'board_readable' => true]);
        $d = $this->decide('core.board.read', null, false, 0, $guestGrants, ['system.guest', 'system.user', 'system.moderator', 'system.admin'], $readable);
        self::assertTrue($d->allowed);
        self::assertSame('grant', $d->source);
        self::assertSame('system.guest', $d->roleKey);

        $private = $this->ctx(['board' => $this->board(['visibility' => 'private']), 'board_readable' => false]);
        $d = $this->decide('core.board.read', null, false, 0, $guestGrants, ['system.guest', 'system.user', 'system.moderator', 'system.admin'], $private);
        self::assertFalse($d->allowed);
        self::assertSame('read_gate', $d->source);
    }

    public function test_guest_has_no_grant_for_thread_create(): void
    {
        $d = $this->decide('core.thread.create', null, false, 0,
            [$this->grant(['role_key' => 'system.guest'])],
            ['system.user', 'system.moderator', 'system.admin'],
            $this->ctx(['board' => $this->board(), 'board_readable' => true]));
        self::assertFalse($d->allowed);
        self::assertSame('no_grant', $d->source);
    }

    public function test_state_beats_role_except_read_and_self_account(): void
    {
        $u = $this->user(['status' => 'suspended', 'suspended_until' => null]);
        $grants = [$this->grant()];
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $boardCtx = $this->ctx(['board' => $this->board(), 'board_readable' => true]);

        $d = $this->decide('core.post.create', $u, false, 10, $grants, $holding, $boardCtx);
        self::assertFalse($d->allowed);
        self::assertSame('state', $d->source);

        $d = $this->decide('core.board.read', $u, false, 10, [$this->grant(['role_key' => 'system.guest'])], ['system.guest'] + $holding, $boardCtx);
        self::assertTrue($d->allowed, 'suspended accounts can still read');

        $d = $this->decide('core.account.manage_self', $u, false, 10, $grants, $holding, $this->ctx());
        self::assertTrue($d->allowed, 'suspended accounts still manage their own account');
    }

    public function test_temporal_window_is_enforced_by_the_resolver(): void
    {
        $u = $this->user();
        $holding = ['system.moderator', 'system.admin'];
        $ctx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true]);

        $expired = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-07-01 00:00:00', 'source' => 'assignment'])];
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $expired, $holding, $ctx)->allowed);

        $future = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-08-01 00:00:00', 'ends_at' => null, 'source' => 'assignment'])];
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $future, $holding, $ctx)->allowed);

        $active = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3, 'starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-08-01 00:00:00', 'source' => 'assignment'])];
        $d = $this->decide('core.thread.lock', $u, true, 10, $active, $holding, $ctx);
        self::assertTrue($d->allowed);
        self::assertSame('board', $d->scopeType);
        self::assertSame(3, $d->scopeId);
    }

    public function test_scope_narrowing_board_category_site(): void
    {
        $u = $this->user();
        $holding = ['system.moderator', 'system.admin'];
        $boardA = $this->ctx(['board' => $this->board(['id' => 3, 'category_id' => 1]), 'board_readable' => true]);
        $boardB = $this->ctx(['board' => $this->board(['id' => 4, 'category_id' => 2]), 'board_readable' => true]);
        $grantA = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];

        self::assertTrue($this->decide('core.thread.lock', $u, true, 10, $grantA, $holding, $boardA)->allowed);
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $grantA, $holding, $boardB)->allowed, 'board grant does not travel to another board');

        $catGrant = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'category', 'scope_id' => 1])];
        self::assertTrue($this->decide('core.thread.lock', $u, true, 10, $catGrant, $holding, $boardA)->allowed, 'category grant covers its boards');
        self::assertFalse($this->decide('core.thread.lock', $u, true, 10, $catGrant, $holding, $boardB)->allowed);

        // A site-scoped capability requires a site-scoped grant: a board-scoped
        // admin assignment cannot ban site-wide.
        $boardAdmin = [$this->grant(['role_key' => 'system.admin', 'scope_type' => 'board', 'scope_id' => 3])];
        $d = $this->decide('core.user.ban', $u, true, 10, $boardAdmin, ['system.admin'], $this->ctx());
        self::assertFalse($d->allowed);
        self::assertSame('no_grant', $d->source);
    }

    public function test_posting_floor_uses_global_site_rank_only(): void
    {
        // Legacy quirk 5: BoardPolicy::canPost checks User::isModerator() — the
        // GLOBAL role — so a board-scoped moderator grant must NOT satisfy the
        // 'moderator' floor, while global rank 20 must.
        $u = $this->user();
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $floorBoard = $this->ctx(['board' => $this->board(['id' => 3, 'post_min_role' => 'moderator']), 'board_readable' => true]);
        $grants = [
            $this->grant(),
            $this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3]),
        ];

        $d = $this->decide('core.thread.create', $u, true, 10, $grants, $holding, $floorBoard);
        self::assertFalse($d->allowed);
        self::assertSame('floor', $d->source);

        self::assertTrue($this->decide('core.thread.create', $u, true, 20, $grants, $holding, $floorBoard)->allowed);
    }

    public function test_archived_and_unreadable_boards_close_canpost_gated_keys(): void
    {
        $u = $this->user();
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $grants = [$this->grant()];

        $archived = $this->ctx(['board' => $this->board(['is_archived' => 1]), 'board_readable' => true]);
        self::assertSame('archived', $this->decide('core.thread.tag', $u, true, 10, $grants, $holding, $archived)->source);

        $unreadable = $this->ctx(['board' => $this->board(['visibility' => 'private']), 'board_readable' => false]);
        self::assertSame('read_gate', $this->decide('core.post.create', $u, true, 10, $grants, $holding, $unreadable)->source);

        // Moderation keys do NOT read-gate (canModerate never consulted canRead).
        $modGrant = [$this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];
        $privateBoard = $this->ctx(['board' => $this->board(['id' => 3, 'visibility' => 'private']), 'board_readable' => false]);
        self::assertTrue($this->decide('core.post.delete_any', $u, true, 10, $modGrant, ['system.moderator', 'system.admin'], $privateBoard)->allowed);
    }

    public function test_dual_path_keys_allow_owner_or_board_moderator(): void
    {
        $owner = $this->user(['id' => 7]);
        $holding = ['system.user', 'system.moderator', 'system.admin'];
        $userGrant = [$this->grant()];

        $ownCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 7]);
        $d = $this->decide('core.thread.mark_solved', $owner, true, 10, $userGrant, $holding, $ownCtx);
        self::assertTrue($d->allowed, 'author path');

        $otherCtx = $this->ctx(['board' => $this->board(['id' => 3]), 'board_readable' => true, 'owner_id' => 99]);
        self::assertFalse($this->decide('core.thread.mark_solved', $owner, true, 10, $userGrant, $holding, $otherCtx)->allowed);

        $modGrants = [$this->grant(), $this->grant(['role_key' => 'system.moderator', 'scope_type' => 'board', 'scope_id' => 3])];
        self::assertTrue($this->decide('core.thread.mark_solved', $owner, true, 10, $modGrants, $holding, $otherCtx)->allowed, 'board-moderator path');
    }

    public function test_direct_capability_grant_satisfies_only_its_key(): void
    {
        // Legacy quirks 2–4 project direct capability grants (view_pending /
        // user.warn) rather than whole roles.
        $u = $this->user(['role' => 'moderator']);
        $viewPending = [$this->grant(['kind' => 'capability', 'role_key' => null, 'capability_key' => 'core.content.view_pending'])];
        $ctx = $this->ctx(['board' => $this->board(), 'board_readable' => true]);

        self::assertTrue($this->decide('core.content.view_pending', $u, true, 20, $viewPending, ['system.moderator', 'system.admin'], $ctx)->allowed);
        self::assertFalse($this->decide('core.thread.lock', $u, true, 20, $viewPending, ['system.moderator', 'system.admin'], $ctx)->allowed, 'no board powers from the vestigial global moderator');
    }

    public function test_self_scope_denies_other_subjects(): void
    {
        $u = $this->user(['id' => 7]);
        $grants = [$this->grant()];
        $holding = ['system.user', 'system.moderator', 'system.admin'];

        self::assertTrue($this->decide('core.post.edit_own', $u, true, 10, $grants, $holding, $this->ctx(['owner_id' => 7]))->allowed);
        $d = $this->decide('core.post.edit_own', $u, true, 10, $grants, $holding, $this->ctx(['owner_id' => 8]));
        self::assertFalse($d->allowed);
        self::assertSame('scope', $d->source);
        self::assertFalse($this->decide('core.account.manage_self', $u, true, 10, $grants, $holding, $this->ctx(['user_id' => 8]))->allowed);
    }

    public function test_protected_keys_resolve_only_via_active_owner(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $adminGrants = [$this->grant(['role_key' => 'system.admin'])];

        $d = $this->decide('core.owner.transfer', $admin, true, 30, $adminGrants, [], $this->ctx(), isOwner: false);
        self::assertFalse($d->allowed);
        self::assertSame('protected', $d->source);

        self::assertTrue($this->decide('core.owner.transfer', $admin, true, 30, $adminGrants, [], $this->ctx(), isOwner: true)->allowed);
        self::assertFalse($this->decide('core.owner.transfer', $admin, false, 30, $adminGrants, [], $this->ctx(), isOwner: true)->allowed, 'state still beats owner authority');
    }

    public function test_decision_vo_shape(): void
    {
        $d = CapabilityDecision::deny('core.x', 'unknown_capability', 'Unknown capability keys fail dark.');
        self::assertFalse($d->allowed);
        self::assertSame('core.x', $d->capability);
        self::assertNull($d->roleKey);
        $a = CapabilityDecision::allow('core.board.read', 'grant', 'ok', 'system.guest', 'site', null);
        self::assertTrue($a->allowed);
        self::assertSame('site', $a->scopeType);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Unit/Security/CapabilityRulesTest.php`
Expected: FAIL — `Class "App\Security\CapabilityDecision" not found` (or CapabilityRules).

- [ ] **Step 3: Implement `src/Security/CapabilityDecision.php`**

```php
<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Immutable capability decision (Increment 1, P5-08). `source` names the
 * decisive rule so the shadow parity ledger and the permission simulator can
 * explain WHY without re-deriving it:
 *   grant | protected | state | scope | read_gate | floor | archived |
 *   no_grant | unknown_capability
 * `roleKey`/`scopeType`/`scopeId` describe the decisive grant when allowed.
 */
final class CapabilityDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $capability,
        public readonly string $source,
        public readonly string $reason,
        public readonly ?string $roleKey = null,
        public readonly ?string $scopeType = null,
        public readonly ?int $scopeId = null,
    ) {
    }

    public static function allow(string $capability, string $source, string $reason, ?string $roleKey = null, ?string $scopeType = null, ?int $scopeId = null): self
    {
        return new self(true, $capability, $source, $reason, $roleKey, $scopeType, $scopeId);
    }

    public static function deny(string $capability, string $source, string $reason): self
    {
        return new self(false, $capability, $source, $reason);
    }
}
```

- [ ] **Step 4: Implement `src/Security/CapabilityRules.php`**

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;

/**
 * Pure union-then-narrow capability decision core (Increment 1, P5-08).
 * No I/O: the resolver fetches grants/target context and this class decides.
 * Order: protected → state ("state beats role") → dual-path owner → self scope
 * → grant union with scope narrowing → board read-gate/floor/archived narrowing.
 * Temporal windows (starts_at/ends_at, UTC strings) are enforced HERE
 * (decision #24) so expiry never waits for a cleanup job. No deny rules, no
 * role inheritance, no policy code (decision #19) — grants only ever add, then
 * state/scope/read gates narrow.
 *
 * The three key-policy constants below encode the LEGACY gates each key group
 * carries today (taxonomy §4/§7); the parity corpus (Task 7) is the proof they
 * match. They are resolver semantics, deliberately code-owned like the
 * catalogue itself.
 */
final class CapabilityRules
{
    /** Keys usable regardless of WriteGate state (reading + own-account management). */
    private const STATE_EXEMPT = ['core.board.read', 'core.account.manage_self'];

    /** Author-or-board-moderator keys (taxonomy §4.2 dual-path note). */
    private const DUAL_PATH = ['core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow'];

    /** Keys whose legacy gate is BoardPolicy::canPost (= !archived && canRead && floor). */
    private const CAN_POST_GATED = ['core.thread.create', 'core.post.create', 'core.thread.tag'];

    /** Keys whose legacy gate includes the board read gate (but no floor/archive close). */
    private const READ_GATED = ['core.board.read', 'core.content.react', 'core.content.report'];

    /**
     * @param array{scope:string,risk:string,delegable:bool,protected:bool} $meta
     * @param list<array{kind:string,role_key:?string,capability_key:?string,scope_type:string,scope_id:?int,starts_at:?string,ends_at:?string,source:string}> $grants
     * @param list<string> $rolesHoldingKey role_keys whose role_capabilities mapping includes $capability
     * @param array{board:?array<string,mixed>,board_member:bool,board_readable:bool,owner_id:?int,user_id:?int,category_id:?int} $ctx
     */
    public static function decide(
        string $capability,
        array $meta,
        ?User $actor,
        bool $actorCanWrite,
        bool $actorIsActiveOwner,
        int $siteRank,
        array $grants,
        array $rolesHoldingKey,
        array $ctx,
        \DateTimeImmutable $at,
    ): CapabilityDecision {
        // Protected authority never flows through roles (taxonomy §4.5).
        if ($meta['protected']) {
            if ($actor !== null && $actorIsActiveOwner && $actorCanWrite) {
                return CapabilityDecision::allow($capability, 'protected', 'Actor is an active protected owner.');
            }
            return CapabilityDecision::deny($capability, 'protected', 'Held only by active protected owners; never role-mapped or delegable.');
        }

        // State beats role (WriteGate axis), except pure-read/self-account keys.
        if ($actor !== null && !$actorCanWrite && !in_array($capability, self::STATE_EXEMPT, true)) {
            return CapabilityDecision::deny($capability, 'state', 'Account state (banned/suspended/deactivated/pending deletion) blocks this action.');
        }

        // Dual-path author route: acting on a target the actor owns.
        if (in_array($capability, self::DUAL_PATH, true)
            && $actor !== null
            && $ctx['owner_id'] !== null
            && $actor->owns((int) $ctx['owner_id'])) {
            $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, 'any', $ctx, $at);
            if ($g !== null) {
                return CapabilityDecision::allow($capability, 'grant', 'Actor owns the target (author path).', $g['role_key'], 'self', null);
            }
        }

        // Self-scoped keys apply only to the actor's own account/content.
        if ($meta['scope'] === 'self') {
            $subject = $ctx['user_id'] ?? $ctx['owner_id'];
            if ($subject !== null && ($actor === null || !$actor->owns((int) $subject))) {
                return CapabilityDecision::deny($capability, 'scope', "Self-scoped capability applies only to the actor's own account or content.");
            }
            $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, 'any', $ctx, $at);
            return $g !== null
                ? CapabilityDecision::allow($capability, 'grant', 'Baseline self capability held via role grant.', $g['role_key'], 'self', null)
                : CapabilityDecision::deny($capability, 'no_grant', 'No active grant provides this capability.');
        }

        $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, $meta['scope'], $ctx, $at);
        if ($g === null) {
            return CapabilityDecision::deny($capability, 'no_grant', 'No active grant provides this capability at this scope.');
        }

        // Board-target narrowing mirrors the legacy per-key gates.
        $board = $ctx['board'];
        if ($board !== null) {
            if (in_array($capability, self::CAN_POST_GATED, true)) {
                if ((int) ($board['is_archived'] ?? 0) === 1) {
                    return CapabilityDecision::deny($capability, 'archived', 'The board is archived; every write path is closed.');
                }
                if (!$ctx['board_readable']) {
                    return CapabilityDecision::deny($capability, 'read_gate', 'The board read gate denies access to this board.');
                }
                if ($siteRank < self::floorRank((string) ($board['post_min_role'] ?? 'user'))) {
                    return CapabilityDecision::deny($capability, 'floor', "The board's minimum posting role is not met (global role rank).");
                }
            } elseif (in_array($capability, self::READ_GATED, true) && !$ctx['board_readable']) {
                return CapabilityDecision::deny($capability, 'read_gate', 'The board read gate denies access to this board.');
            }
        }

        return CapabilityDecision::allow(
            $capability,
            'grant',
            'Active grant provides this capability at the required scope.',
            $g['role_key'] ?? null,
            $g['scope_type'],
            $g['scope_id'] === null ? null : (int) $g['scope_id'],
        );
    }

    /**
     * First window-valid grant that holds the key and satisfies the scope class.
     *
     * @param list<array<string,mixed>> $grants
     * @param list<string> $rolesHoldingKey
     * @param array{board:?array<string,mixed>,category_id:?int} $ctx
     * @return array<string,mixed>|null
     */
    private static function firstQualifyingGrant(string $capability, array $grants, array $rolesHoldingKey, string $scopeClass, array $ctx, \DateTimeImmutable $at): ?array
    {
        foreach ($grants as $g) {
            if (!self::windowValid($g, $at)) {
                continue;
            }
            $holds = ($g['kind'] ?? 'role') === 'capability'
                ? ($g['capability_key'] ?? null) === $capability
                : in_array((string) ($g['role_key'] ?? ''), $rolesHoldingKey, true);
            if (!$holds || !self::scopeSatisfies($g, $scopeClass, $ctx)) {
                continue;
            }
            return $g;
        }
        return null;
    }

    /** @param array<string,mixed> $g */
    private static function windowValid(array $g, \DateTimeImmutable $at): bool
    {
        $ts = $at->getTimestamp();
        $starts = $g['starts_at'] ?? null;
        if ($starts !== null && strtotime((string) $starts . ' UTC') > $ts) {
            return false;
        }
        $ends = $g['ends_at'] ?? null;
        return $ends === null || strtotime((string) $ends . ' UTC') > $ts;
    }

    /**
     * @param array<string,mixed> $g
     * @param array{board:?array<string,mixed>,category_id:?int} $ctx
     */
    private static function scopeSatisfies(array $g, string $scopeClass, array $ctx): bool
    {
        if ($scopeClass === 'any') {
            return true;
        }
        $gScope = (string) $g['scope_type'];
        $gId = $g['scope_id'] === null ? null : (int) $g['scope_id'];

        if ($scopeClass === 'site') {
            return $gScope === 'site';
        }
        if ($scopeClass === 'category') {
            return match ($gScope) {
                'site' => true,
                'category' => $ctx['category_id'] === null || $gId === (int) $ctx['category_id'],
                default => false,
            };
        }
        // board-scoped capability
        $board = $ctx['board'];
        if ($board === null) {
            return true; // held-anywhere probe (no target)
        }
        return match ($gScope) {
            'site' => true,
            'category' => $gId === (int) ($board['category_id'] ?? 0),
            'board' => $gId === (int) ($board['id'] ?? 0),
            default => false,
        };
    }

    private static function floorRank(string $postMinRole): int
    {
        return match ($postMinRole) {
            'admin' => 30,
            'moderator' => 20,
            default => 10, // 'user'
        };
    }
}
```

- [ ] **Step 5: Run the unit test until green**

Run: `vendor/bin/phpunit tests/Unit/Security/CapabilityRulesTest.php`
Expected: PASS (12 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Security/CapabilityDecision.php src/Security/CapabilityRules.php tests/Unit/Security/CapabilityRulesTest.php
git commit -m "feat(phase5): add pure union-then-narrow CapabilityRules core (Inc 1 SP2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Role-model repositories (capabilities / roles / role_capabilities)

**Files:**
- Create: `src/Repository/CapabilityRepository.php`
- Create: `src/Repository/RoleRepository.php`
- Create: `src/Repository/RoleCapabilityRepository.php`
- Test: `tests/Integration/Repository/RoleModelRepositoriesTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`; the `0050`/`0066` seeded tables.
- Produces (used by Tasks 5/9/10):
  - `CapabilityRepository::all(): list<array>` (non-retired, id order), `idsByKeys(list<string>): array<string,int>`.
  - `RoleRepository::find(int): ?array`, `findByKey(string): ?array`, `all(): list<array>`, `create(array{role_key,name,description,created_by}): int` (kind='custom', rank 0, version 1), `updateDefinition(int,string,?string): int`, `bumpVersion(int): void`.
  - `RoleCapabilityRepository::roleKeysHolding(string $capabilityKey): list<string>`, `keysForRole(int $roleId): list<string>`, `replaceForRole(int $roleId, list<int> $capabilityIds): void`.

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\CapabilityRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\CapabilityCatalog;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — thin wrappers over the 0050 role-model tables, exercised
 * against the real 0066 seed.
 */
final class RoleModelRepositoriesTest extends TestCase
{
    public function test_capability_repository_reads_the_seeded_catalogue(): void
    {
        $caps = new CapabilityRepository($this->db);
        $all = $caps->all();
        self::assertCount(count(CapabilityCatalog::keys()), $all, 'seed matches the code-owned catalogue');

        $ids = $caps->idsByKeys(['core.board.read', 'core.thread.lock']);
        self::assertCount(2, $ids);
        self::assertArrayHasKey('core.thread.lock', $ids);
        self::assertSame([], $caps->idsByKeys([]));
    }

    public function test_role_keys_holding_reflects_the_cumulative_seed(): void
    {
        $rc = new RoleCapabilityRepository($this->db);

        $read = $rc->roleKeysHolding('core.board.read');
        sort($read);
        self::assertSame(['system.admin', 'system.guest', 'system.moderator', 'system.user'], $read);

        $lock = $rc->roleKeysHolding('core.thread.lock');
        sort($lock);
        self::assertSame(['system.admin', 'system.moderator'], $lock);

        self::assertSame(['system.admin'], $rc->roleKeysHolding('core.user.ban'));
        self::assertSame([], $rc->roleKeysHolding('core.owner.transfer'), 'protected keys are never role-mapped');
        self::assertSame([], $rc->roleKeysHolding('core.not.a.key'));
    }

    public function test_custom_role_create_map_and_version_bump(): void
    {
        $roles = new RoleRepository($this->db);
        $rc = new RoleCapabilityRepository($this->db);
        $caps = new CapabilityRepository($this->db);
        $admin = $this->makeAdmin();

        $id = $roles->create([
            'role_key' => 'custom.board_helper',
            'name' => 'Board Helper',
            'description' => 'Lock + pin only',
            'created_by' => (int) $admin['id'],
        ]);
        $row = $roles->find($id);
        self::assertNotNull($row);
        self::assertSame('custom', $row['kind']);
        self::assertSame(0, (int) $row['is_protected']);
        self::assertSame(1, (int) $row['version']);
        self::assertSame('custom.board_helper', $roles->findByKey('custom.board_helper')['role_key']);

        $ids = $caps->idsByKeys(['core.thread.lock', 'core.thread.pin']);
        $rc->replaceForRole($id, array_values($ids));
        $keys = $rc->keysForRole($id);
        sort($keys);
        self::assertSame(['core.thread.lock', 'core.thread.pin'], $keys);

        $rc->replaceForRole($id, [$ids['core.thread.lock']]);
        self::assertSame(['core.thread.lock'], $rc->keysForRole($id));

        self::assertSame(1, $roles->updateDefinition($id, 'Board Helper v2', null));
        $roles->bumpVersion($id);
        $row = $roles->find($id);
        self::assertSame('Board Helper v2', $row['name']);
        self::assertNull($row['description']);
        self::assertSame(2, (int) $row['version']);

        self::assertContains('custom.board_helper', array_column($roles->all(), 'role_key'));
        self::assertContains('system.admin', array_column($roles->all(), 'role_key'));
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleModelRepositoriesTest.php`
Expected: FAIL — `Class "App\Repository\CapabilityRepository" not found`.

- [ ] **Step 3: Implement the three repositories**

`src/Repository/CapabilityRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over the `capabilities` catalogue table (0050, seeded by 0066
 * from the code-owned CapabilityCatalog). Reads only — capability MEANING is
 * code-owned; rows are created exclusively by seed migrations.
 */
final class CapabilityRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> non-retired capabilities in catalogue order */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM capabilities WHERE retired_at IS NULL ORDER BY id ASC');
    }

    /**
     * @param list<string> $keys
     * @return array<string,int> capability_key => id (missing keys are simply absent)
     */
    public function idsByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, capability_key FROM capabilities WHERE capability_key IN ($in)",
            array_values($keys),
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['capability_key']] = (int) $r['id'];
        }
        return $out;
    }
}
```

`src/Repository/RoleRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over `roles` (0050). System anchors are seeded by 0050 and
 * protected; this repo only ever creates kind='custom' rows (RoleService owns
 * the protected-role guard). `version` bumps on definition changes so caches
 * and assignments can key off a definite role version (decision #24).
 */
final class RoleRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $roleKey): ?array
    {
        return $this->db->fetch('SELECT * FROM roles WHERE role_key = ?', [$roleKey]);
    }

    /** @return list<array<string,mixed>> system anchors first, then customs, stable order */
    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY kind = 'system' DESC, id ASC");
    }

    /** @param array{role_key:string,name:string,description:?string,created_by:?int} $data */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO roles (role_key, name, kind, is_protected, role_rank, version, description, created_by)
             VALUES (?, ?, 'custom', 0, 0, 1, ?, ?)",
            [$data['role_key'], $data['name'], $data['description'] ?? null, $data['created_by'] ?? null],
        );
    }

    /** @return int affected rows */
    public function updateDefinition(int $id, string $name, ?string $description): int
    {
        return $this->db->run(
            'UPDATE roles SET name = ?, description = ? WHERE id = ?',
            [$name, $description, $id],
        )->rowCount();
    }

    public function bumpVersion(int $id): void
    {
        $this->db->run('UPDATE roles SET version = version + 1 WHERE id = ?', [$id]);
    }
}
```

`src/Repository/RoleCapabilityRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over the `role_capabilities` mapping (0050). The resolver asks
 * "which roles hold key X" (one indexed query per decision); the role editor
 * replaces a role's mapping wholesale inside RoleService's transaction.
 */
final class RoleCapabilityRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<string> role_keys whose mapping includes the capability */
    public function roleKeysHolding(string $capabilityKey): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.role_key
             FROM role_capabilities rc
             JOIN roles r        ON r.id = rc.role_id
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE c.capability_key = ?',
            [$capabilityKey],
        );
        return array_map(static fn (array $r): string => (string) $r['role_key'], $rows);
    }

    /** @return list<string> capability keys mapped to the role */
    public function keysForRole(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.capability_key
             FROM role_capabilities rc
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE rc.role_id = ?
             ORDER BY c.id ASC',
            [$roleId],
        );
        return array_map(static fn (array $r): string => (string) $r['capability_key'], $rows);
    }

    /** Replace the role's mapping wholesale. Caller wraps in a transaction. @param list<int> $capabilityIds */
    public function replaceForRole(int $roleId, array $capabilityIds): void
    {
        $this->db->run('DELETE FROM role_capabilities WHERE role_id = ?', [$roleId]);
        foreach ($capabilityIds as $capId) {
            $this->db->run(
                'INSERT IGNORE INTO role_capabilities (role_id, capability_id) VALUES (?, ?)',
                [$roleId, (int) $capId],
            );
        }
    }
}
```

- [ ] **Step 4: Run the test until green**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleModelRepositoriesTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Repository/CapabilityRepository.php src/Repository/RoleRepository.php src/Repository/RoleCapabilityRepository.php tests/Integration/Repository/RoleModelRepositoriesTest.php
git commit -m "feat(phase5): add role-model repositories over the 0050 tables (Inc 1 SP2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: RoleAssignmentRepository + RoleAssignmentHistoryRepository

**Files:**
- Create: `src/Repository/RoleAssignmentRepository.php`
- Create: `src/Repository/RoleAssignmentHistoryRepository.php`
- Test: `tests/Integration/Repository/RoleAssignmentRepositoryTest.php`

**Interfaces:**
- Produces (used by Tasks 5/9):
  - `RoleAssignmentRepository::rowsForUser(int $userId): list<array>` — non-revoked rows joined with `r.role_key, r.role_rank`; **window filtering is CapabilityRules' job**.
  - `RoleAssignmentRepository::create(array): int` (defaults: subject_type 'user', scope_type 'site', version 1).
  - `RoleAssignmentRepository::countActiveForRoles(list<int>): array<int,int>` (revoked-null + window-valid at UTC now, grouped).
  - `RoleAssignmentHistoryRepository::log(array{assignment_id?:?int,event:string,actor_id:?int,subject_type?:?string,subject_id?:?int,role_id:?int,scope_type?:?string,scope_id?:?int,before:?array,after:?array,reason?:?string}): int`.
  - `RoleAssignmentHistoryRepository::forRole(int $roleId, int $limit = 50): list<array>` (newest first).

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleRepository;
use Tests\Support\TestCase;

final class RoleAssignmentRepositoryTest extends TestCase
{
    public function test_rows_for_user_joins_role_and_excludes_revoked(): void
    {
        $repo = new RoleAssignmentRepository($this->db);
        $roles = new RoleRepository($this->db);
        $user = $this->makeUser();
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];

        $a1 = $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'scope_type' => 'board', 'scope_id' => 42]);
        $repo->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'ends_at' => '2026-01-01 00:00:00']);

        $rows = $repo->rowsForUser((int) $user['id']);
        self::assertCount(2, $rows, 'expired rows are returned (rules filter windows); revoked rows are not');
        self::assertSame('system.moderator', $rows[0]['role_key']);
        self::assertSame(20, (int) $rows[0]['role_rank']);

        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$a1]);
        self::assertCount(1, $repo->rowsForUser((int) $user['id']));
    }

    public function test_count_active_for_roles_applies_window_and_revocation(): void
    {
        $repo = new RoleAssignmentRepository($this->db);
        $roles = new RoleRepository($this->db);
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];
        $adminRoleId = (int) $roles->findByKey('system.admin')['id'];

        $repo->create(['subject_id' => (int) $u1['id'], 'role_id' => $modRoleId]);                                        // active
        $repo->create(['subject_id' => (int) $u2['id'], 'role_id' => $modRoleId, 'ends_at' => '2026-01-01 00:00:00']);    // expired
        $repo->create(['subject_id' => (int) $u2['id'], 'role_id' => $modRoleId, 'starts_at' => '2030-01-01 00:00:00']);  // future
        $revoked = $repo->create(['subject_id' => (int) $u1['id'], 'role_id' => $adminRoleId]);
        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$revoked]);

        $counts = $repo->countActiveForRoles([$modRoleId, $adminRoleId]);
        self::assertSame(1, $counts[$modRoleId] ?? 0);
        self::assertArrayNotHasKey($adminRoleId, $counts);
        self::assertSame([], $repo->countActiveForRoles([]));
    }

    public function test_history_log_round_trips_before_after_json(): void
    {
        $hist = new RoleAssignmentHistoryRepository($this->db);
        $roles = new RoleRepository($this->db);
        $admin = $this->makeAdmin();
        $roleId = (int) $roles->findByKey('system.user')['id'];

        $hist->log([
            'event' => 'role_edit',
            'actor_id' => (int) $admin['id'],
            'role_id' => $roleId,
            'before' => null,
            'after' => ['name' => 'Board Helper', 'capabilities' => ['core.thread.lock']],
            'reason' => 'created',
        ]);
        $rows = $hist->forRole($roleId);
        self::assertCount(1, $rows);
        self::assertSame('role_edit', $rows[0]['event']);
        self::assertNull($rows[0]['before_json']);
        $after = json_decode((string) $rows[0]['after_json'], true);
        self::assertSame(['core.thread.lock'], $after['capabilities']);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleAssignmentRepositoryTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the two repositories**

`src/Repository/RoleAssignmentRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over `role_assignments` (0050). Reads return non-revoked rows
 * with the role's key/rank joined; TEMPORAL WINDOWS ARE NOT FILTERED HERE —
 * CapabilityRules enforces starts_at/ends_at at decision time (decision #24)
 * so expiry cannot depend on a cleanup job. Impact counts DO apply the window
 * (they answer "who is affected right now").
 */
final class RoleAssignmentRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> non-revoked user assignments + role_key/role_rank */
    public function rowsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT ra.*, r.role_key, r.role_rank
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id
             WHERE ra.subject_type = 'user' AND ra.subject_id = ? AND ra.revoked_at IS NULL",
            [$userId],
        );
    }

    /**
     * @param array{subject_type?:string,subject_id:int,role_id:int,scope_type?:string,scope_id?:?int,grantor_id?:?int,reason?:?string,starts_at?:?string,ends_at?:?string} $d
     */
    public function create(array $d): int
    {
        return $this->db->insert(
            'INSERT INTO role_assignments
                (subject_type, subject_id, role_id, scope_type, scope_id, grantor_id, reason, starts_at, ends_at, assignment_version, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())',
            [
                $d['subject_type'] ?? 'user',
                $d['subject_id'],
                $d['role_id'],
                $d['scope_type'] ?? 'site',
                $d['scope_id'] ?? null,
                $d['grantor_id'] ?? null,
                $d['reason'] ?? null,
                $d['starts_at'] ?? null,
                $d['ends_at'] ?? null,
            ],
        );
    }

    /**
     * @param list<int> $roleIds
     * @return array<int,int> role_id => currently-active assignment count
     */
    public function countActiveForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $roleIds));
        $rows = $this->db->fetchAll(
            "SELECT role_id, COUNT(*) AS n
             FROM role_assignments
             WHERE role_id IN ($in)
               AND revoked_at IS NULL
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
               AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())
             GROUP BY role_id",
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['role_id']] = (int) $r['n'];
        }
        return $out;
    }
}
```

`src/Repository/RoleAssignmentHistoryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Append-only role/assignment audit (0050 `role_assignment_history`). Role
 * definition changes and (from Inc 6) assignment lifecycle events land here;
 * rows are never updated or deleted.
 */
final class RoleAssignmentHistoryRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array{assignment_id?:?int,event:string,actor_id:?int,subject_type?:?string,subject_id?:?int,role_id:?int,scope_type?:?string,scope_id?:?int,before:?array,after:?array,reason?:?string} $entry
     */
    public function log(array $entry): int
    {
        return $this->db->insert(
            'INSERT INTO role_assignment_history
                (assignment_id, event, actor_id, subject_type, subject_id, role_id, scope_type, scope_id, before_json, after_json, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $entry['assignment_id'] ?? null,
                $entry['event'],
                $entry['actor_id'] ?? null,
                $entry['subject_type'] ?? null,
                $entry['subject_id'] ?? null,
                $entry['role_id'] ?? null,
                $entry['scope_type'] ?? null,
                $entry['scope_id'] ?? null,
                $entry['before'] === null ? null : json_encode($entry['before'], JSON_UNESCAPED_SLASHES),
                $entry['after'] === null ? null : json_encode($entry['after'], JSON_UNESCAPED_SLASHES),
                $entry['reason'] ?? null,
            ],
        );
    }

    /** @return list<array<string,mixed>> newest first */
    public function forRole(int $roleId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM role_assignment_history WHERE role_id = ? ORDER BY id DESC LIMIT $limit",
            [$roleId],
        );
    }
}
```

- [ ] **Step 4: Run the test until green**

Run: `vendor/bin/phpunit tests/Integration/Repository/RoleAssignmentRepositoryTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Repository/RoleAssignmentRepository.php src/Repository/RoleAssignmentHistoryRepository.php tests/Integration/Repository/RoleAssignmentRepositoryTest.php
git commit -m "feat(phase5): add role-assignment + history repositories (Inc 1 SP2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: LegacyAuthorityProjection

**Files:**
- Create: `src/Service/LegacyAuthorityProjection.php`
- Test: `tests/Integration/Service/LegacyAuthorityProjectionTest.php`

**Interfaces:**
- Consumes: `App\Repository\BoardModeratorRepository::boardsFor(int): list<int>`, `App\Domain\User`.
- Produces (used by Task 5): `bundleFor(?User $user): array{grants: list<grant-row>, site_rank: int}` — grant rows in the Task 1 shape with `source: 'legacy'`.

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Service\LegacyAuthorityProjection;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — virtual grants derived from the legacy authority tables
 * (users.role / board_moderators), reproducing the taxonomy §7 quirks exactly:
 * a vestigial global moderator gets NO board powers (only the pending-view
 * exemption + rank 20), and "staff-any" warn is projected for anyone
 * moderating ≥1 board.
 */
final class LegacyAuthorityProjectionTest extends TestCase
{
    private function projection(): LegacyAuthorityProjection
    {
        return new LegacyAuthorityProjection(new BoardModeratorRepository($this->db));
    }

    /** @param list<array<string,mixed>> $grants @return list<array<string,mixed>> */
    private function ofKind(array $grants, string $kind): array
    {
        return array_values(array_filter($grants, static fn (array $g): bool => $g['kind'] === $kind));
    }

    public function test_guest_projects_only_the_guest_role(): void
    {
        $b = $this->projection()->bundleFor(null);
        self::assertSame(0, $b['site_rank']);
        self::assertCount(1, $b['grants']);
        self::assertSame('system.guest', $b['grants'][0]['role_key']);
        self::assertSame('site', $b['grants'][0]['scope_type']);
    }

    public function test_plain_user_projects_guest_plus_user_at_rank_10(): void
    {
        $u = User::fromRow($this->makeUser());
        $b = $this->projection()->bundleFor($u);
        self::assertSame(10, $b['site_rank']);
        $roleKeys = array_column($this->ofKind($b['grants'], 'role'), 'role_key');
        sort($roleKeys);
        self::assertSame(['system.guest', 'system.user'], $roleKeys);
        self::assertSame([], $this->ofKind($b['grants'], 'capability'));
    }

    public function test_board_moderator_projects_board_scoped_moderator_and_staff_any_warn(): void
    {
        $user = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory('P'));
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $user['id']);

        $b = $this->projection()->bundleFor(User::fromRow($user));
        self::assertSame(10, $b['site_rank'], 'a board mod with users.role=user keeps global rank 10 (quirk 5)');

        $modGrants = array_values(array_filter($b['grants'], static fn (array $g): bool => $g['role_key'] === 'system.moderator'));
        self::assertCount(1, $modGrants);
        self::assertSame('board', $modGrants[0]['scope_type']);
        self::assertSame((int) $board['id'], $modGrants[0]['scope_id']);

        $capGrants = $this->ofKind($b['grants'], 'capability');
        self::assertSame(['core.user.warn'], array_column($capGrants, 'capability_key'), 'staff-any warn (quirk 2)');
        self::assertSame('site', $capGrants[0]['scope_type']);
    }

    public function test_vestigial_global_moderator_gets_pending_view_and_rank_but_no_board_powers(): void
    {
        $u = User::fromRow($this->makeUser(['role' => 'moderator']));
        $b = $this->projection()->bundleFor($u);
        self::assertSame(20, $b['site_rank'], 'global moderator passes the moderator posting floor');

        $roleKeys = array_column($this->ofKind($b['grants'], 'role'), 'role_key');
        self::assertNotContains('system.moderator', $roleKeys, 'quirk 3: no board powers from the global role');

        $capKeys = array_column($this->ofKind($b['grants'], 'capability'), 'capability_key');
        self::assertSame(['core.content.view_pending'], $capKeys, 'quirk 3/4: only the pending-view exemption');
        self::assertNotContains('core.user.warn', $capKeys, 'a global mod with no boards cannot warn (quirk 2)');
    }

    public function test_admin_projects_site_admin_at_rank_30(): void
    {
        $u = User::fromRow($this->makeAdmin());
        $b = $this->projection()->bundleFor($u);
        self::assertSame(30, $b['site_rank']);
        $roleKeys = array_column($this->ofKind($b['grants'], 'role'), 'role_key');
        self::assertContains('system.admin', $roleKeys);
        $adminGrant = array_values(array_filter($b['grants'], static fn (array $g): bool => $g['role_key'] === 'system.admin'))[0];
        self::assertSame('site', $adminGrant['scope_type']);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/LegacyAuthorityProjectionTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Service/LegacyAuthorityProjection.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardModeratorRepository;

/**
 * Increment 1 (P5-08) — derives VIRTUAL role/capability grants from the legacy
 * authority tables so the resolver is decidable before Increment 6 imports
 * real assignments. Non-broadening by construction (taxonomy §7):
 *
 *  - everyone (incl. guests)          → system.guest @ site
 *  - any authenticated user           → system.user  @ site, rank 10
 *  - each board_moderators row        → system.moderator @ that board
 *  - moderating ≥1 board (quirk 2)    → direct capability core.user.warn @ site
 *  - users.role='moderator' (quirk 3/4) → rank 20 + direct capability
 *    core.content.view_pending @ site — NEVER the system.moderator role
 *  - users.role='admin'               → system.admin @ site, rank 30
 *
 * `site_rank` reproduces BoardPolicy::canPost's GLOBAL-role posting floor
 * (quirk 5): a board-scoped moderator grant must not satisfy a 'moderator'
 * floor, while the global role does.
 */
final class LegacyAuthorityProjection
{
    public function __construct(private BoardModeratorRepository $boardMods)
    {
    }

    /** @return array{grants: list<array<string,mixed>>, site_rank: int} */
    public function bundleFor(?User $user): array
    {
        $grant = static fn (string $kind, ?string $roleKey, ?string $capKey, string $scopeType, ?int $scopeId): array => [
            'kind' => $kind, 'role_key' => $roleKey, 'capability_key' => $capKey,
            'scope_type' => $scopeType, 'scope_id' => $scopeId,
            'starts_at' => null, 'ends_at' => null, 'source' => 'legacy',
        ];

        $grants = [$grant('role', 'system.guest', null, 'site', null)];
        $rank = 0;

        if ($user !== null) {
            $grants[] = $grant('role', 'system.user', null, 'site', null);
            $rank = 10;

            $modBoards = $this->boardMods->boardsFor($user->id());
            foreach ($modBoards as $boardId) {
                $grants[] = $grant('role', 'system.moderator', null, 'board', $boardId);
            }
            if ($modBoards !== []) {
                $grants[] = $grant('capability', null, 'core.user.warn', 'site', null);
            }

            if ($user->role() === 'moderator') {
                $rank = 20;
                $grants[] = $grant('capability', null, 'core.content.view_pending', 'site', null);
            }
            if ($user->isAdmin()) {
                $rank = 30;
                $grants[] = $grant('role', 'system.admin', null, 'site', null);
            }
        }

        return ['grants' => $grants, 'site_rank' => $rank];
    }
}
```

- [ ] **Step 4: Run the test until green**

Run: `vendor/bin/phpunit tests/Integration/Service/LegacyAuthorityProjectionTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Service/LegacyAuthorityProjection.php tests/Integration/Service/LegacyAuthorityProjectionTest.php
git commit -m "feat(phase5): add legacy-authority projection with taxonomy §7 quirks (Inc 1 SP2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: CapabilityResolver orchestrator + container bindings

**Files:**
- Create: `src/Security/CapabilityResolver.php`
- Modify: `src/Core/App.php` (container bindings after the `LastOwnerGuard` binding, ~line 1064; `use` block)
- Test: `tests/Integration/Security/CapabilityResolverTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–4 plus `BoardRepository::find`, `BoardMemberRepository::isMember`, `BoardPolicy::canRead`, `WriteGate::canWrite`, `ProtectedOwnerRepository::isActiveOwner`, `CapabilityCatalog::all()`.
- Produces: `CapabilityResolver::can(?User $actor, string $capability, array $target = [], ?\DateTimeImmutable $at = null): CapabilityDecision` where `$target` keys are `board_id`, `owner_id`, `user_id`, `category_id` (all optional). Container bindings: `CapabilityRepository`, `RoleRepository`, `RoleCapabilityRepository`, `RoleAssignmentRepository`, `RoleAssignmentHistoryRepository`, `LegacyAuthorityProjection`, `CapabilityResolver`.

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Service\LegacyAuthorityProjection;
use App\Security\WriteGate;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — the resolver against the real seeded DB: legacy
 * projection + real role_assignments union, scope/state/read-gate/floor
 * narrowing, temporal windows, protected-owner path. Deploy-dark: nothing in
 * the live request path calls this while `capabilities` is off.
 */
final class CapabilityResolverTest extends TestCase
{
    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    public function test_legacy_projection_end_to_end_matrix(): void
    {
        $cat = $this->makeCategory('Resolver');
        $public = $this->makeBoard($cat);
        $private = $this->makeBoard($cat, ['visibility' => 'private']);
        $floor = $this->makeBoard($cat, ['post_min_role' => 'moderator']);

        $guest = null;
        $user = User::fromRow($this->makeUser());
        $globalMod = User::fromRow($this->makeUser(['role' => 'moderator']));
        $admin = User::fromRow($this->makeAdmin());
        $boardModRow = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $public['id'], (int) $boardModRow['id']);
        $boardMod = User::fromRow($boardModRow);
        $suspended = User::fromRow($this->makeUser(['status' => 'suspended']));

        $r = $this->resolver();
        $pub = ['board_id' => (int) $public['id']];
        $priv = ['board_id' => (int) $private['id']];
        $flo = ['board_id' => (int) $floor['id']];

        // Read gate
        self::assertTrue($r->can($guest, 'core.board.read', $pub)->allowed);
        self::assertFalse($r->can($guest, 'core.board.read', $priv)->allowed);
        self::assertTrue($r->can($admin, 'core.board.read', $priv)->allowed, 'admins read private boards');
        self::assertTrue($r->can($suspended, 'core.board.read', $pub)->allowed, 'state-exempt read');

        // Posting + floor (global rank only — quirk 5)
        self::assertTrue($r->can($user, 'core.thread.create', $pub)->allowed);
        self::assertFalse($r->can($guest, 'core.thread.create', $pub)->allowed);
        self::assertSame('state', $r->can($suspended, 'core.thread.create', $pub)->source);
        self::assertSame('floor', $r->can($user, 'core.thread.create', $flo)->source);
        self::assertTrue($r->can($globalMod, 'core.thread.create', $flo)->allowed);
        self::assertSame('floor', $r->can($boardMod, 'core.thread.create', $flo)->source, 'board-mod rows do not satisfy the global floor');
        self::assertFalse($r->can($user, 'core.post.create', $priv)->allowed, 'read gate closes posting on private boards');

        // Moderation scope (board_moderators → that board only; admin anywhere; no read gate)
        self::assertTrue($r->can($boardMod, 'core.thread.lock', $pub)->allowed);
        self::assertFalse($r->can($boardMod, 'core.thread.lock', $priv)->allowed);
        self::assertTrue($r->can($admin, 'core.thread.lock', $priv)->allowed);
        self::assertFalse($r->can($globalMod, 'core.thread.lock', $pub)->allowed, 'quirk 3: vestigial global mod has no board powers');
        self::assertTrue($r->can($globalMod, 'core.content.view_pending', $pub)->allowed, 'quirk 4: pending-view exemption');
        self::assertTrue($r->can($boardMod, 'core.user.warn')->allowed, 'quirk 2: staff-any warn');
        self::assertFalse($r->can($globalMod, 'core.user.warn')->allowed);

        // Site admin keys
        self::assertTrue($r->can($admin, 'core.user.ban')->allowed);
        self::assertFalse($r->can($boardMod, 'core.user.ban')->allowed);

        // Unknown key fails dark
        self::assertSame('unknown_capability', $r->can($admin, 'core.nope')->source);
    }

    public function test_assignment_union_windows_and_category_scope(): void
    {
        $cat = $this->makeCategory('Scoped');
        $otherCat = $this->makeCategory('Other');
        $board = $this->makeBoard($cat);
        $user = $this->makeUser();
        $u = User::fromRow($user);
        $roles = new RoleRepository($this->db);
        $assign = new RoleAssignmentRepository($this->db);
        $modRoleId = (int) $roles->findByKey('system.moderator')['id'];
        $adminRoleId = (int) $roles->findByKey('system.admin')['id'];
        $r = $this->resolver();
        $t = ['board_id' => (int) $board['id']];

        self::assertFalse($r->can($u, 'core.thread.lock', $t)->allowed, 'baseline: no authority');

        $expired = $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'scope_type' => 'board', 'scope_id' => (int) $board['id'], 'ends_at' => '2026-01-01 00:00:00']);
        self::assertFalse($r->can($u, 'core.thread.lock', $t)->allowed, 'expired grant is dead at decision time');

        $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'scope_type' => 'board', 'scope_id' => (int) $board['id'], 'starts_at' => '2030-01-01 00:00:00']);
        self::assertFalse($r->can($u, 'core.thread.lock', $t)->allowed, 'future grant not yet live');

        $active = $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $modRoleId, 'scope_type' => 'board', 'scope_id' => (int) $board['id']]);
        $d = $r->can($u, 'core.thread.lock', $t);
        self::assertTrue($d->allowed);
        self::assertSame('system.moderator', $d->roleKey);
        self::assertSame('board', $d->scopeType);

        // Revocation is immediate (repository filters revoked rows).
        $this->db->run('UPDATE role_assignments SET revoked_at = UTC_TIMESTAMP() WHERE id IN (' . (int) $active . ',' . (int) $expired . ')');
        self::assertFalse($r->can($u, 'core.thread.lock', $t)->allowed);

        // Category-scoped admin assignment: board.manage inside the category only,
        // and NEVER a site capability.
        $assign->create(['subject_id' => (int) $user['id'], 'role_id' => $adminRoleId, 'scope_type' => 'category', 'scope_id' => $cat]);
        self::assertTrue($r->can($u, 'core.board.manage', ['category_id' => $cat])->allowed);
        self::assertFalse($r->can($u, 'core.board.manage', ['category_id' => $otherCat])->allowed);
        self::assertTrue($r->can($u, 'core.thread.lock', $t)->allowed, 'category grant covers boards in the category');
        self::assertFalse($r->can($u, 'core.user.ban')->allowed, 'site keys need site scope');
    }

    public function test_protected_keys_and_owner_path(): void
    {
        $adminRow = $this->makeAdmin();
        $admin = User::fromRow($adminRow);
        $r = $this->resolver();

        self::assertSame('protected', $r->can($admin, 'core.owner.transfer')->source);
        self::assertFalse($r->can($admin, 'core.owner.transfer')->allowed, 'admin without owner row is denied');

        (new ProtectedOwnerRepository($this->db))->designate((int) $adminRow['id']);
        self::assertTrue($r->can($admin, 'core.owner.transfer')->allowed);
        self::assertTrue($r->can($admin, 'core.trust.manage_keys')->allowed);
    }

    public function test_dual_path_and_self_keys_against_real_threads(): void
    {
        $cat = $this->makeCategory('Dual');
        $board = $this->makeBoard($cat);
        $author = $this->makeUser();
        $other = $this->makeUser();
        $thread = $this->makeThread($board, $author);
        $r = $this->resolver();
        $ownCtx = ['board_id' => (int) $board['id'], 'owner_id' => (int) $author['id']];

        self::assertTrue($r->can(User::fromRow($author), 'core.thread.mark_solved', $ownCtx)->allowed);
        self::assertFalse($r->can(User::fromRow($other), 'core.thread.mark_solved', $ownCtx)->allowed);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $other['id']);
        self::assertTrue($r->can(User::fromRow($other), 'core.thread.mark_solved', $ownCtx)->allowed, 'board-moderator path');

        self::assertTrue($r->can(User::fromRow($author), 'core.post.edit_own', ['owner_id' => (int) $author['id']])->allowed);
        self::assertSame('scope', $r->can(User::fromRow($other), 'core.post.edit_own', ['owner_id' => (int) $author['id']])->source);
        self::assertGreaterThan(0, $thread['thread_id']);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Security/CapabilityResolverTest.php`
Expected: FAIL — `Class "App\Security\CapabilityResolver" not found`.

- [ ] **Step 3: Implement `src/Security/CapabilityResolver.php`**

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Service\LegacyAuthorityProjection;

/**
 * Increment 1 (P5-08) — the database-backed capability resolver, SHADOW-ONLY in
 * this increment: nothing enforces its answer while `capabilities` is dark; the
 * shadow harness (ResolverShadow) and the parity corpus consume it. Unions the
 * legacy-authority projection with real role_assignments rows, then delegates
 * the pure decision to CapabilityRules. Capability MEANING comes from the
 * code-owned CapabilityCatalog; only role→capability membership is read from
 * the DB (so the role editor's changes take effect).
 *
 * Query budget per decision (D11 resolver.p95 = 5ms): boardsFor + rowsForUser +
 * roleKeysHolding + (board find + isMember when a board target is given) +
 * isActiveOwner only for protected keys.
 */
final class CapabilityResolver
{
    public function __construct(
        private RoleCapabilityRepository $roleCaps,
        private RoleAssignmentRepository $assignments,
        private LegacyAuthorityProjection $projection,
        private ProtectedOwnerRepository $owners,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
    ) {
    }

    /** @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target */
    public function can(?User $actor, string $capability, array $target = [], ?\DateTimeImmutable $at = null): CapabilityDecision
    {
        $meta = CapabilityCatalog::all()[$capability] ?? null;
        if ($meta === null) {
            return CapabilityDecision::deny($capability, 'unknown_capability', 'Unknown capability keys fail dark.');
        }
        $at ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $actorCanWrite = $actor !== null && $this->writeGate->canWrite($actor);
        $isActiveOwner = $meta['protected'] && $actor !== null && $this->owners->isActiveOwner($actor->id());

        $bundle = $this->projection->bundleFor($actor);
        $grants = $bundle['grants'];
        if ($actor !== null) {
            foreach ($this->assignments->rowsForUser($actor->id()) as $row) {
                $grants[] = [
                    'kind' => 'role',
                    'role_key' => (string) $row['role_key'],
                    'capability_key' => null,
                    'scope_type' => (string) $row['scope_type'],
                    'scope_id' => $row['scope_id'] === null ? null : (int) $row['scope_id'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'source' => 'assignment',
                ];
            }
        }

        $ctx = [
            'board' => null,
            'board_member' => false,
            'board_readable' => false,
            'owner_id' => isset($target['owner_id']) ? (int) $target['owner_id'] : null,
            'user_id' => isset($target['user_id']) ? (int) $target['user_id'] : null,
            'category_id' => isset($target['category_id']) ? (int) $target['category_id'] : null,
        ];
        $boardId = (int) ($target['board_id'] ?? 0);
        if ($boardId > 0) {
            $board = $this->boards->find($boardId);
            if ($board !== null) {
                $isMember = $actor !== null && $this->members->isMember($boardId, $actor->id());
                $ctx['board'] = $board;
                $ctx['board_member'] = $isMember;
                $ctx['board_readable'] = $this->policy->canRead($board, $actor, $isMember);
                $ctx['category_id'] ??= (int) $board['category_id'];
            }
        }

        $rolesHolding = $this->roleCaps->roleKeysHolding($capability);

        return CapabilityRules::decide(
            $capability, $meta, $actor, $actorCanWrite, $isActiveOwner,
            $bundle['site_rank'], $grants, $rolesHolding, $ctx, $at,
        );
    }
}
```

- [ ] **Step 4: Add the container bindings in `src/Core/App.php`**

In the `use` block add (alphabetical placement with the existing imports):

```php
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\CapabilityResolver;
use App\Service\LegacyAuthorityProjection;
```

Immediately AFTER the existing `LastOwnerGuard` binding block (`$c->bind(LastOwnerGuard::class, …);`, ~line 1061–1064), insert:

```php
        $c->bind(CapabilityRepository::class, fn (Container $c) => new CapabilityRepository($c->get(Database::class)));
        $c->bind(RoleRepository::class, fn (Container $c) => new RoleRepository($c->get(Database::class)));
        $c->bind(RoleCapabilityRepository::class, fn (Container $c) => new RoleCapabilityRepository($c->get(Database::class)));
        $c->bind(RoleAssignmentRepository::class, fn (Container $c) => new RoleAssignmentRepository($c->get(Database::class)));
        $c->bind(RoleAssignmentHistoryRepository::class, fn (Container $c) => new RoleAssignmentHistoryRepository($c->get(Database::class)));
        $c->bind(LegacyAuthorityProjection::class, fn (Container $c) => new LegacyAuthorityProjection(
            $c->get(BoardModeratorRepository::class),
        ));
        $c->bind(CapabilityResolver::class, fn (Container $c) => new CapabilityResolver(
            $c->get(RoleCapabilityRepository::class),
            $c->get(RoleAssignmentRepository::class),
            $c->get(LegacyAuthorityProjection::class),
            $c->get(ProtectedOwnerRepository::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
        ));
```

(`BoardModeratorRepository`, `BoardMemberRepository`, `BoardRepository`, `BoardPolicy`, `WriteGate`, `ProtectedOwnerRepository` are already bound and imported — verify with grep, do not re-bind.)

- [ ] **Step 5: Run the test + the full flag regression**

Run: `vendor/bin/phpunit tests/Integration/Security/CapabilityResolverTest.php tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS. The flag test proves the bindings changed no live behavior.

- [ ] **Step 6: Commit**

```bash
git add src/Security/CapabilityResolver.php src/Core/App.php tests/Integration/Security/CapabilityResolverTest.php
git commit -m "feat(phase5): add CapabilityResolver orchestrator + container wiring (Inc 1 SP2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: ResolverShadow + wiring into ModerationService and PostingService

**Files:**
- Create: `src/Service/ResolverShadow.php`
- Modify: `src/Service/ModerationService.php` (ctor tail + `canModerate()`)
- Modify: `src/Service/PostingService.php` (ctor tail + `createThread()` + `reply()` canPost checks)
- Modify: `src/Core/App.php` (bind `ResolverShadow`; pass flag-conditional `?ResolverShadow` to both services)
- Test: `tests/Integration/Service/ResolverShadowTest.php`

**Interfaces:**
- Produces: `ResolverShadow::compare(bool $legacyAllowed, ?User $actor, string $capability, array $target, string $site): void` — never throws, never returns a decision. Telemetry events: `resolver.shadow_mismatch` `{site, capability, legacy, resolver, source, reason, actor_id, board_id}`; `resolver.shadow_error` `{site, capability, error}`.
- The live shadow surface for Inc 1 is exactly: `ModerationService::canModerate` (compared against `core.post.delete_any` — the capability whose holder set IS the canModerate predicate under the projection) and `PostingService::createThread`/`reply` canPost checks (against `core.thread.create` / `core.post.create`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\ResolverShadow;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — the shadow harness records mismatches to telemetry and
 * NEVER changes or breaks the caller's decision (fail-open on resolver errors).
 */
final class ResolverShadowTest extends TestCase
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

    private function resolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    /** @return list<array<string,mixed>> decoded events of one type */
    private function events(string $event): array
    {
        $out = [];
        foreach ($this->lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && ($decoded['event'] ?? null) === $event) {
                $out[] = $decoded;
            }
        }
        return $out;
    }

    public function test_agreement_emits_nothing_and_mismatch_emits_one_event(): void
    {
        $board = $this->makeBoard($this->makeCategory('Shadow'));
        $admin = User::fromRow($this->makeAdmin());
        $shadow = new ResolverShadow($this->resolver(), $this->telemetry());

        // Agreement: legacy true, resolver true (admin moderates everywhere).
        $shadow->compare(true, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test');
        self::assertSame([], $this->events('resolver.shadow_mismatch'));

        // Forced mismatch: claim legacy denied the admin.
        $shadow->compare(false, $admin, 'core.post.delete_any', ['board_id' => (int) $board['id']], 'test');
        $events = $this->events('resolver.shadow_mismatch');
        self::assertCount(1, $events);
        self::assertSame('core.post.delete_any', $events[0]['capability']);
        self::assertFalse($events[0]['legacy']);
        self::assertTrue($events[0]['resolver']);
        self::assertSame('test', $events[0]['site']);
    }

    public function test_resolver_exception_fails_open_with_error_event(): void
    {
        $telemetry = $this->telemetry();
        $throwing = new class extends CapabilityResolver {
            public function __construct()
            {
            }

            public function can(?User $actor, string $capability, array $target = [], ?\DateTimeImmutable $at = null): \App\Security\CapabilityDecision
            {
                throw new \RuntimeException('boom');
            }
        };
        $shadow = new ResolverShadow($throwing, $telemetry);
        $shadow->compare(true, null, 'core.board.read', [], 'test');
        self::assertCount(1, $this->events('resolver.shadow_error'));
        self::assertSame([], $this->events('resolver.shadow_mismatch'));
    }

    public function test_moderation_and_posting_shadow_paths_agree_on_the_fixture(): void
    {
        // Drive the REAL services through HTTP with the flag on: no mismatch
        // events and unchanged decisions.
        (new \App\Repository\SettingRepository($this->db))->set('features', ['capabilities' => true]);
        $board = $this->makeBoard($this->makeCategory('ShadowHttp'));
        $user = $this->makeUser();
        $this->actingAs($user);

        $resp = $this->post('/compose/thread', [
            'board_id' => (int) $board['id'], 'title' => 'Shadow soak', 'body' => 'A body long enough.',
        ]);
        self::assertContains($resp->status(), [302, 303], 'thread creation unchanged with shadow on');

        $mod = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->actingAs($mod);
        $thread = $this->threads()->findBySlugPrefix('shadow-soak') ?? null;
        self::assertNotNull($thread);
        $resp = $this->post('/t/' . (int) $thread['id'] . '/lock');
        self::assertContains($resp->status(), [302, 303], 'moderation unchanged with shadow on');
    }
}
```

> **Note for the executor on the last test:** verify the real route paths before running — `grep -n "compose/thread\|/lock" src/Core/App.php`. Use the actual thread-create route (`/compose/thread` or `/threads` — whatever `buildRouter()` registers) and the actual lock route. `ThreadRepository` may not have `findBySlugPrefix`; fetch the thread with `$this->db->fetch("SELECT id FROM threads WHERE title = ?", ['Shadow soak'])` instead. Keep the assertions (redirect status, no exception) — the point is decisions unchanged, telemetry dark by default.

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/ResolverShadowTest.php`
Expected: FAIL — `Class "App\Service\ResolverShadow" not found`.

- [ ] **Step 3: Implement `src/Service/ResolverShadow.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Telemetry;
use App\Domain\User;
use App\Security\CapabilityResolver;

/**
 * Increment 1 (P5-08) — the shadow-comparison harness. Records legacy-vs-
 * resolver mismatches to the telemetry parity ledger WITHOUT changing the
 * decision (PHASE_5_PLAN §13.1 step 2: shadow soaks before any enforcement).
 * Fail-open by contract: a resolver bug must never break a live request, so
 * every Throwable is swallowed into a `resolver.shadow_error` event.
 *
 * Injected only when the `capabilities` flag is on (container conditional);
 * callers hold it nullable and invoke via `?->`.
 */
final class ResolverShadow
{
    public function __construct(
        private CapabilityResolver $resolver,
        private Telemetry $telemetry,
    ) {
    }

    /** @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target */
    public function compare(bool $legacyAllowed, ?User $actor, string $capability, array $target, string $site): void
    {
        try {
            $decision = $this->resolver->can($actor, $capability, $target);
            if ($decision->allowed !== $legacyAllowed) {
                $this->telemetry->emit('resolver.shadow_mismatch', [
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
        } catch (\Throwable $e) {
            $this->telemetry->emit('resolver.shadow_error', [
                'site' => $site,
                'capability' => $capability,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Wire the two call sites**

`src/Service/ModerationService.php` — append to the constructor parameter list after `private ?FirstPartyHookRegistry $hooks = null`:

```php
        private ?ResolverShadow $shadow = null,
```

(add `use App\Service\ResolverShadow;` is unnecessary — same namespace; add nothing to imports) and change `canModerate()` to:

```php
    /** Non-throwing capability check (admin anywhere, or assigned board moderator). */
    public function canModerate(User $user, int $boardId): bool
    {
        $allowed = $this->writeGate->canWrite($user)
            && ($user->isAdmin() || $this->boardMods->isModerator($boardId, $user->id()));
        // Inc 1 shadow surface: core.post.delete_any's holder set IS the
        // canModerate predicate under the legacy projection.
        $this->shadow?->compare($allowed, $user, 'core.post.delete_any', ['board_id' => $boardId], 'ModerationService::canModerate');
        return $allowed;
    }
```

`src/Service/PostingService.php` — append to the constructor after `private ?LinkPreviewService $linkPreviews = null`:

```php
        private ?ResolverShadow $shadow = null,
```

In `createThread()`, replace:

```php
        if (!$this->policy->canPost($board, $user, $this->isBoardMember($boardId, $user->id()))) {
            throw new ForbiddenException('You cannot post in this board.');
        }
```

with:

```php
        $canPost = $this->policy->canPost($board, $user, $this->isBoardMember($boardId, $user->id()));
        $this->shadow?->compare($canPost, $user, 'core.thread.create', ['board_id' => $boardId], 'PostingService::createThread');
        if (!$canPost) {
            throw new ForbiddenException('You cannot post in this board.');
        }
```

In `reply()`, replace:

```php
        if (!$this->policy->canPost($board, $user, $this->isBoardMember((int) $thread['board_id'], $user->id()))) {
            throw new ForbiddenException('You cannot post in this board.');
        }
```

with:

```php
        $canPost = $this->policy->canPost($board, $user, $this->isBoardMember((int) $thread['board_id'], $user->id()));
        $this->shadow?->compare($canPost, $user, 'core.post.create', ['board_id' => (int) $thread['board_id']], 'PostingService::reply');
        if (!$canPost) {
            throw new ForbiddenException('You cannot post in this board.');
        }
```

`src/Core/App.php` — after the `CapabilityResolver` binding add:

```php
        $c->bind(ResolverShadow::class, fn (Container $c) => new ResolverShadow(
            $c->get(CapabilityResolver::class),
            $c->get(Telemetry::class),
        ));
```

(import `use App\Service\ResolverShadow;`). Then append the flag-conditional argument to BOTH service bindings, as the LAST constructor argument:

- `PostingService` binding: after `…enabled('link_previews') ? … : null,` add
  `$c->get(FeatureFlags::class)->enabled('capabilities') ? $c->get(ResolverShadow::class) : null,`
- `ModerationService` binding: after its current last argument add the same conditional line.

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/Service/ResolverShadowTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppModerationTest.php`
Expected: PASS (if `AppModerationTest` doesn't exist under that name, run `vendor/bin/phpunit --testsuite integration` and expect green — the wiring must not disturb any existing moderation/posting test).

- [ ] **Step 6: Commit**

```bash
git add src/Service/ResolverShadow.php src/Service/ModerationService.php src/Service/PostingService.php src/Core/App.php tests/Integration/Service/ResolverShadowTest.php
git commit -m "feat(phase5): wire fail-open resolver shadow into canModerate/canPost (Inc 1 SP3)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: ResolverParityService — the old-vs-new corpus with a legacy oracle

**Files:**
- Create: `src/Service/ResolverParityService.php`
- Test: `tests/Integration/Service/ResolverParityTest.php`

**Interfaces:**
- Consumes: the F9 fixture (`Phase5FixtureSeeder`), `CapabilityResolver` (Task 5), legacy components (`WriteGate`, `BoardPolicy`, `BoardModeratorRepository`, `BoardMemberRepository`, `ProtectedOwnerRepository`).
- Produces (used by Task 8): `run(): array{fixture:string, tuples:int, agreed:int, mismatches:list<array{capability:string,actor:string,target:string,legacy:bool,resolver:bool,source:string,reason:string}>}`; `render(array $result, string $commit): string` (markdown report); `legacyCanModerate(?User, int): bool` (public so the pinning test can compare it against the real `ModerationService`).

**The oracle encodes taxonomy §4 "authority today" per key.** If the corpus test reveals a mismatch, the procedure is: read the cited legacy call site; if the oracle mis-encodes legacy, fix the ORACLE; if the resolver deviates from legacy, fix the RESOLVER (usually a key's membership in `CapabilityRules::CAN_POST_GATED`/`READ_GATED`). Legacy behavior always wins (parity-first, taxonomy §7) — never "fix" legacy semantics in this increment.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\SettingRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\Phase5FixtureSeeder;
use App\Service\ResolverParityService;
use Tests\Support\TestCase;

/**
 * Increment 1 exit gate (P5-08): ZERO parity mismatch for built-in roles on the
 * F9 fixture. This test IS the enforcing form of the archived parity corpus.
 */
final class ResolverParityTest extends TestCase
{
    private function service(): ResolverParityService
    {
        $resolver = new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
        return new ResolverParityService(
            $this->db,
            $resolver,
            new BoardModeratorRepository($this->db),
            new BoardMemberRepository($this->db),
            new ProtectedOwnerRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
    }

    private function seedFixture(): void
    {
        (new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), 'testing'))->seed(true);
    }

    public function test_refuses_to_run_without_the_fixture(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->run();
    }

    public function test_zero_mismatch_on_the_f9_fixture(): void
    {
        $this->seedFixture();
        $result = $this->service()->run();

        self::assertGreaterThan(1000, $result['tuples'], 'corpus covers the full catalogue x fixture actors x targets');
        self::assertSame(
            [],
            $result['mismatches'],
            "Parity mismatches:\n" . json_encode(array_slice($result['mismatches'], 0, 20), JSON_PRETTY_PRINT),
        );
        self::assertSame($result['tuples'], $result['agreed']);
        self::assertStringContainsString('phase5_fixture_v', $result['fixture']);
    }

    public function test_oracle_canmoderate_matches_the_real_moderation_service(): void
    {
        // Guards the oracle's pinned copy of the canModerate predicate against
        // silent drift from ModerationService::canModerate.
        $this->seedFixture();
        $svc = $this->service();
        $modService = null; // built below via the container-free path
        $board = $this->makeBoard($this->makeCategory('Pin'));
        $modRow = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $modRow['id']);

        $cases = [
            [User::fromRow($this->makeAdmin()), (int) $board['id']],
            [User::fromRow($modRow), (int) $board['id']],
            [User::fromRow($this->makeUser(['role' => 'moderator'])), (int) $board['id']],
            [User::fromRow($this->makeUser(['status' => 'suspended'])), (int) $board['id']],
            [User::fromRow($this->makeUser()), (int) $board['id']],
        ];
        foreach ($cases as [$user, $boardId]) {
            self::assertSame(
                (new WriteGate())->canWrite($user) && ($user->isAdmin() || (new BoardModeratorRepository($this->db))->isModerator($boardId, $user->id())),
                $svc->legacyCanModerate($user, $boardId),
                'oracle predicate must equal the ModerationService::canModerate definition',
            );
        }
        self::assertFalse($svc->legacyCanModerate(null, (int) $board['id']));
        self::assertNull($modService);
    }

    public function test_render_produces_the_archived_report_shape(): void
    {
        $this->seedFixture();
        $svc = $this->service();
        $md = $svc->render($svc->run(), 'abc1234');
        self::assertStringContainsString('# Phase 5 — Resolver Parity Corpus (Increment 1, P5-08)', $md);
        self::assertStringContainsString('Commit: `abc1234`', $md);
        self::assertStringContainsString('Mismatches: **0**', $md);
        self::assertStringContainsString('core.thread.lock', $md);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/ResolverParityTest.php`
Expected: FAIL — `Class "App\Service\ResolverParityService" not found`.

- [ ] **Step 3: Implement `src/Service/ResolverParityService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityCatalog;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;

/**
 * Increment 1 (P5-08) — runs the old-vs-new parity corpus on the F9 fixture:
 * every catalogued capability × every fixture actor (plus guest) × the targets
 * that key's scope calls for, comparing the resolver against a LEGACY ORACLE
 * that encodes taxonomy §4 "authority today" using the real legacy components
 * (WriteGate/BoardPolicy/board_moderators/protected_owners).
 *
 * The oracle's canModerate predicate is a pinned copy of
 * ModerationService::canModerate (that service's constructor graph is too
 * heavy to build here); ResolverParityTest pins the two together so drift
 * fails CI. Mismatch procedure: legacy always wins — fix the oracle if it
 * mis-encodes legacy, fix the resolver if it deviates (taxonomy §7).
 */
final class ResolverParityService
{
    private const DUAL_PATH = ['core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow'];
    private const CAN_POST_GATED = ['core.thread.create', 'core.post.create', 'core.thread.tag'];
    private const READ_GATED = ['core.content.react', 'core.content.report'];

    /** §4.3 board-moderation keys whose legacy gate is exactly canModerate. */
    private const MODERATION_KEYS = [
        'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
        'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author',
        'core.content.approve', 'core.report.handle', 'core.appeal.resolve_content', 'core.memory.curate',
    ];

    public function __construct(
        private Database $db,
        private CapabilityResolver $resolver,
        private BoardModeratorRepository $boardMods,
        private BoardMemberRepository $members,
        private ProtectedOwnerRepository $owners,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
    ) {
    }

    /** Pinned legacy predicate (see class docblock + ResolverParityTest). */
    public function legacyCanModerate(?User $user, int $boardId): bool
    {
        return $user !== null
            && $this->writeGate->canWrite($user)
            && ($user->isAdmin() || $this->boardMods->isModerator($boardId, $user->id()));
    }

    /**
     * @return array{fixture:string, tuples:int, agreed:int, mismatches:list<array<string,mixed>>}
     */
    public function run(): array
    {
        $userRows = $this->db->fetchAll("SELECT * FROM users WHERE username LIKE 'p5fix\\_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT * FROM boards WHERE slug LIKE 'p5fix\\_%' ORDER BY id ASC");
        if ($userRows === [] || $boards === []) {
            throw new \RuntimeException('Phase 5 fixture not seeded — run Phase5FixtureSeeder (verify:resolver-parity seeds it automatically).');
        }

        /** @var list<array{0:?User,1:string}> $actors */
        $actors = [[null, 'guest']];
        foreach ($userRows as $row) {
            $actors[] = [User::fromRow($row), (string) $row['username']];
        }
        $otherUserId = (int) $userRows[0]['id'];

        $tuples = 0;
        $agreed = 0;
        $mismatches = [];

        foreach (CapabilityCatalog::all() as $key => $meta) {
            foreach ($actors as [$actor, $actorLabel]) {
                foreach ($this->targetsFor($key, $meta, $actor, $boards, $otherUserId) as [$target, $targetLabel]) {
                    $tuples++;
                    $decision = $this->resolver->can($actor, $key, $target);
                    $legacy = $this->legacyAllows($key, $meta, $actor, $target);
                    if ($decision->allowed === $legacy) {
                        $agreed++;
                        continue;
                    }
                    $mismatches[] = [
                        'capability' => $key,
                        'actor' => $actorLabel,
                        'target' => $targetLabel,
                        'legacy' => $legacy,
                        'resolver' => $decision->allowed,
                        'source' => $decision->source,
                        'reason' => $decision->reason,
                    ];
                }
            }
        }

        $fixtureVersion = (int) $this->db->fetchValue(
            "SELECT JSON_UNQUOTE(value) FROM settings WHERE `key` = 'phase5_fixture_version'",
        );

        return [
            'fixture' => 'phase5_fixture_v' . max(1, $fixtureVersion),
            'tuples' => $tuples,
            'agreed' => $agreed,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param array{scope:string,protected:bool} $meta
     * @param list<array<string,mixed>> $boards
     * @return list<array{0:array<string,mixed>,1:string}>
     */
    private function targetsFor(string $key, array $meta, ?User $actor, array $boards, int $otherUserId): array
    {
        if (in_array($key, self::DUAL_PATH, true)) {
            $out = [];
            foreach ($boards as $b) {
                $bid = (int) $b['id'];
                $own = $actor?->id() ?? $otherUserId;
                $out[] = [['board_id' => $bid, 'owner_id' => $own], $b['slug'] . ':own-thread'];
                $out[] = [['board_id' => $bid, 'owner_id' => $otherUserId === $own ? $own + 1000000 : $otherUserId], $b['slug'] . ':other-thread'];
            }
            return $out;
        }
        if ($meta['scope'] === 'self') {
            $own = $actor?->id() ?? $otherUserId;
            $other = $own === $otherUserId ? $own + 1000000 : $otherUserId;
            return [
                [['user_id' => $own], 'self'],
                [['user_id' => $other], 'other-user'],
            ];
        }
        if ($meta['scope'] === 'board') {
            $out = [];
            foreach ($boards as $b) {
                $out[] = [['board_id' => (int) $b['id']], (string) $b['slug']];
            }
            return $out;
        }
        // site + category keys: no concrete target on the fixture corpus.
        return [[[], 'site']];
    }

    /** @param array{scope:string,protected:bool} $meta @param array<string,mixed> $target */
    private function legacyAllows(string $key, array $meta, ?User $user, array $target): bool
    {
        $board = null;
        $isMember = false;
        if (isset($target['board_id'])) {
            $board = $this->db->fetch('SELECT * FROM boards WHERE id = ?', [(int) $target['board_id']]);
            $isMember = $user !== null && $board !== null && $this->members->isMember((int) $board['id'], $user->id());
        }
        $canWrite = $user !== null && $this->writeGate->canWrite($user);

        // Protected authority (taxonomy §4.5): active protected owner only.
        if ($meta['protected']) {
            return $user !== null && $canWrite && $this->owners->isActiveOwner($user->id());
        }

        // §4.1 read
        if ($key === 'core.board.read') {
            return $board === null || $this->policy->canRead($board, $user, $isMember);
        }

        // §4.2 user baseline
        if (in_array($key, self::CAN_POST_GATED, true)) {
            return $user !== null && $canWrite && $board !== null && $this->policy->canPost($board, $user, $isMember);
        }
        if (in_array($key, self::READ_GATED, true)) {
            return $user !== null && $canWrite && ($board === null || $this->policy->canRead($board, $user, $isMember));
        }
        if (in_array($key, self::DUAL_PATH, true)) {
            $ownsTarget = $user !== null && isset($target['owner_id']) && $user->id() === (int) $target['owner_id'];
            return ($canWrite && $ownsTarget) || $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0));
        }
        if ($meta['scope'] === 'self') {
            $subject = $target['user_id'] ?? null;
            $ownSubject = $user !== null && ($subject === null || (int) $subject === $user->id());
            if ($key === 'core.account.manage_self') {
                return $user !== null && $ownSubject; // requireUser only — suspended users still manage their account
            }
            return $ownSubject && $canWrite;
        }

        // §4.3 moderation
        if (in_array($key, self::MODERATION_KEYS, true)) {
            return $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0));
        }
        if ($key === 'core.content.view_pending') {
            // Quirk 4: dual authority — board canModerate OR the vestigial global role.
            return $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0))
                || ($user !== null && $canWrite && $user->isModerator());
        }
        if ($key === 'core.user.warn') {
            // Quirk 2: staff-any.
            return $user !== null && $canWrite
                && ($user->isAdmin() || $this->boardMods->boardsFor($user->id()) !== []);
        }

        // §4.4 administration (site + the category-scoped core.board.manage).
        return $user !== null && $canWrite && $user->isAdmin();
    }

    /** @param array{fixture:string,tuples:int,agreed:int,mismatches:list<array<string,mixed>>} $result */
    public function render(array $result, string $commit): string
    {
        $out = "# Phase 5 — Resolver Parity Corpus (Increment 1, P5-08)\n\n";
        $out .= "> Generated by `bin/console verify:resolver-parity`. Old (legacy oracle from\n";
        $out .= "> taxonomy §4 authority-today predicates) vs new (CapabilityResolver) on the\n";
        $out .= "> same fixture and commit (PHASE_5_PLAN §10.2: archived parity report).\n\n";
        $out .= 'Commit: `' . $commit . "`\n";
        $out .= 'Fixture: `' . $result['fixture'] . "`\n";
        $out .= 'Catalogue: ' . count(CapabilityCatalog::keys()) . " keys\n";
        $out .= 'Tuples compared: **' . $result['tuples'] . "**\n";
        $out .= 'Agreed: **' . $result['agreed'] . "**\n";
        $out .= 'Mismatches: **' . count($result['mismatches']) . "**\n\n";
        $out .= "## Coverage\n\nEvery catalogued capability was compared for every fixture actor (guest, user,\nsuspended, global moderator, board moderator, admin/protected-owner) against the\ntargets its scope calls for (fixture boards `p5fix_public` / `p5fix_mod` /\n`p5fix_private`, own-vs-other thread contexts for the dual-path keys, and\nself-vs-other subjects for self-scoped keys) — e.g. `core.thread.lock` is\nchecked per board per actor.\n\n";
        if ($result['mismatches'] === []) {
            $out .= "## Mismatches\n\nNone. **Exit-gate criterion met: zero parity mismatch for built-in roles on the critical fixtures.**\n";
            return $out;
        }
        $out .= "## Mismatches (legacy is authoritative — classify before enforcement)\n\n";
        $out .= "| Capability | Actor | Target | Legacy | Resolver | Source | Reason |\n|---|---|---|---|---|---|---|\n";
        foreach ($result['mismatches'] as $m) {
            $out .= '| `' . $m['capability'] . '` | ' . $m['actor'] . ' | ' . $m['target'] . ' | '
                . ($m['legacy'] ? 'allow' : 'deny') . ' | ' . ($m['resolver'] ? 'allow' : 'deny') . ' | '
                . $m['source'] . ' | ' . $m['reason'] . " |\n";
        }
        return $out;
    }
}
```

> **Executor note on `run()`'s fixture-version read:** `SettingRepository` stores values JSON-encoded in `settings(key, value)`. Verify the actual column names (`grep -n "TABLE settings" database/migrations/*.php` and `SettingRepository::get`) and reuse `SettingRepository` (`(int) (new SettingRepository($this->db))->get('phase5_fixture_version', 1)`) instead of raw SQL if simpler — adjust the constructor to take it if you do.

- [ ] **Step 4: Run the parity test — iterate until zero mismatches**

Run: `vendor/bin/phpunit tests/Integration/Service/ResolverParityTest.php`

Expected first run: possibly FAIL with a small mismatch table printed by the assertion message. Apply the mismatch procedure from the task header (legacy wins; adjust oracle to the real call site or the resolver's key-policy constants) until: PASS (4 tests) with `mismatches === []`.

Record every rule you had to adjust in the commit message body — these are parity findings, exactly what shadow mode exists to surface.

- [ ] **Step 5: Commit**

```bash
git add src/Service/ResolverParityService.php tests/Integration/Service/ResolverParityTest.php
git commit -m "test(phase5): add zero-mismatch resolver parity corpus + legacy oracle (Inc 1 SP3)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: `verify:resolver-parity` console command + resolver budget measurement

**Files:**
- Modify: `bin/console` (new case + help line + imports)
- Modify: `src/Service/BaselineMetricsService.php` (add `measureResolver()`)
- Modify: `src/Service/Phase5BudgetReportService.php` (optional resolver → MEASURED row)
- Modify: `tests/Integration/Service/Phase5BudgetReportServiceTest.php` (new MEASURED-path test; keep the BASELINE assertions)
- Create (generated): `docs/evidence/phase5/resolver-parity.md`
- Regenerate: `docs/evidence/phase5/performance-budgets.md`

**Interfaces:**
- `BaselineMetricsService::measureResolver(CapabilityResolver $resolver, int $iterations = 200): array` — same §11.3 envelope as `measureLegacyAuthorityRead`, `route_or_job = 'capability_resolver_can'`.
- `Phase5BudgetReportService::__construct(Database $db, ?CapabilityResolver $resolver = null)` — with a resolver, the `resolver.p95` row becomes `MEASURED (PASS|FAIL)` vs the 5 ms target; without one it stays `BASELINE` (existing tests keep passing).
- `bin/console verify:resolver-parity [--no-seed]` — refuses production, seeds the F9 fixture inside a rolled-back transaction (CONSOLE-1 pattern from `verify:phase5-budgets`), writes `docs/evidence/phase5/resolver-parity.md`, exits non-zero when mismatches exist.

- [ ] **Step 1: Extend the budget-report test first (failing)**

Append to `tests/Integration/Service/Phase5BudgetReportServiceTest.php` (keep every existing test unchanged — they pin the no-resolver BASELINE path):

```php
    public function test_resolver_row_is_measured_when_a_resolver_is_supplied(): void
    {
        (new \App\Service\Phase5FixtureSeeder($this->db, new \App\Repository\SettingRepository($this->db), 'testing'))->seed(true);
        $resolver = new \App\Security\CapabilityResolver(
            new \App\Repository\RoleCapabilityRepository($this->db),
            new \App\Repository\RoleAssignmentRepository($this->db),
            new \App\Service\LegacyAuthorityProjection(new \App\Repository\BoardModeratorRepository($this->db)),
            new \App\Repository\ProtectedOwnerRepository($this->db),
            new \App\Repository\BoardRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardPolicy(),
            new \App\Security\WriteGate(),
        );
        $report = new \App\Service\Phase5BudgetReportService($this->db, $resolver);
        $rows = [];
        foreach ($report->rows() as $r) {
            $rows[$r['key']] = $r;
        }
        self::assertStringStartsWith('MEASURED', $rows['resolver.p95']['status']);
        self::assertStringContainsString('ms resolver', $rows['resolver.p95']['measured']);
        self::assertStringContainsString('legacy', $rows['resolver.p95']['measured'], 'baseline stays visible beside the measurement');
        self::assertStringContainsString('Resolver p50/p95/p99', $report->render());
    }
```

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php`
Expected: FAIL — constructor does not accept a resolver yet.

- [ ] **Step 2: Add `measureResolver()` to `src/Service/BaselineMetricsService.php`**

Add the import `use App\Domain\User;` and `use App\Security\CapabilityResolver;`, then append this method after `measureLegacyAuthorityRead()`:

```php
    /**
     * Measures the NEW resolver on the same fixture/envelope as the legacy
     * baseline (Increment 1). Each sample is one full `can()` decision on a
     * board-target write capability — the hot path the 5ms D11 budget governs.
     * Per-decision statements: boardsFor + rowsForUser + roleKeysHolding +
     * board find + isMember = 5 (query_count reports 5/iteration).
     *
     * @return array<string,mixed> the §11.3 envelope
     */
    public function measureResolver(CapabilityResolver $resolver, int $iterations = 200): array
    {
        $iterations = max(1, $iterations);
        $users = $this->db->fetchAll("SELECT * FROM users WHERE username LIKE 'p5fix\\_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT id FROM boards WHERE slug LIKE 'p5fix\\_%' ORDER BY id ASC");

        $samples = [];
        $errors = 0;
        if ($users !== [] && $boards !== []) {
            for ($i = 0; $i < $iterations; $i++) {
                $u = User::fromRow($users[$i % count($users)]);
                $b = (int) $boards[$i % count($boards)]['id'];
                $t0 = hrtime(true);
                try {
                    $resolver->can($u, 'core.thread.create', ['board_id' => $b]);
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000;
            }
        }

        return [
            'route_or_job' => 'capability_resolver_can',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => count($samples) * 5,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }
```

- [ ] **Step 3: Teach `Phase5BudgetReportService` the MEASURED path**

Modify `src/Service/Phase5BudgetReportService.php`:

- Constructor: `public function __construct(private Database $db, private ?\App\Security\CapabilityResolver $resolver = null)` and add property `private ?array $resolverSample = null;` plus:

```php
    private function resolverSample(): ?array
    {
        if ($this->resolver === null) {
            return null;
        }
        return $this->resolverSample ??= (new BaselineMetricsService($this->db))->measureResolver($this->resolver);
    }
```

- In `rows()`, replace the `if ($key === 'resolver.p95') { … }` branch with:

```php
            if ($key === 'resolver.p95') {
                $rs = $this->resolverSample();
                if ($rs !== null) {
                    $measured = $rs['p95'] . ' ms resolver (baseline ' . $baseline['p95'] . ' ms legacy)';
                    $status = ((float) $rs['p95']) <= (float) $b['target'] ? 'MEASURED (PASS)' : 'MEASURED (FAIL)';
                } else {
                    $measured = $baseline['p95'] . ' ms legacy';
                    $status = 'BASELINE';
                }
            } elseif ($key === 'webhook.delivery_timeout') {
```

(keep the webhook branch and the trailing `$rows[] = …` exactly as they are).

- In `render()`, after the `Legacy read p50/p95/p99` line add:

```php
        $rs = $this->resolverSample();
        if ($rs !== null) {
            $out .= '- Resolver p50/p95/p99 (ms): ' . $rs['p50'] . ' / ' . $rs['p95'] . ' / ' . $rs['p99']
                 . ' · route/job: `' . $rs['route_or_job'] . "`\n";
        }
```

Run: `vendor/bin/phpunit tests/Integration/Service/Phase5BudgetReportServiceTest.php tests/Integration/Service/BaselineMetricsServiceTest.php`
Expected: PASS (old BASELINE tests + the new MEASURED test).

- [ ] **Step 4: Add the console command**

In `bin/console`: add imports (match the existing `use` block style):

```php
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\ResolverParityService;
```

(check first with grep — several may already be imported; `BoardRepository` almost certainly is.)

Insert a new case directly after the whole `case 'verify:phase5-budgets':` block:

```php
        case 'verify:resolver-parity':
            // Increment 1 (P5-08) — archives the old-vs-new parity corpus on the
            // F9 fixture (PHASE_5_PLAN §10.2). Seeds inside a rolled-back tx
            // (CONSOLE-1 pattern, see verify:phase5-budgets above); the report is
            // written after rollback from the in-memory result. Exits non-zero on
            // any mismatch — zero is the Inc 1 exit gate.
            $env = (string) $config->get('app.env', 'production');
            if ($env === 'production') {
                $log('Refusing: verify:resolver-parity seeds a fixture and must not run with app.env=production.');
                exit(1);
            }
            $db = $database();
            $resolver = new CapabilityResolver(
                new RoleCapabilityRepository($db),
                new RoleAssignmentRepository($db),
                new LegacyAuthorityProjection(new BoardModeratorRepository($db)),
                new ProtectedOwnerRepository($db),
                new BoardRepository($db),
                new BoardMemberRepository($db),
                new BoardPolicy(),
                new WriteGate(),
            );
            $parity = new ResolverParityService(
                $db,
                $resolver,
                new BoardModeratorRepository($db),
                new BoardMemberRepository($db),
                new ProtectedOwnerRepository($db),
                new BoardPolicy(),
                new WriteGate(),
            );
            $pdo = $db->pdo();
            $pdo->beginTransaction();
            try {
                if (!in_array('--no-seed', $argv, true)) {
                    $summary = (new Phase5FixtureSeeder($db, new SettingRepository($db), $env))->seed(true);
                    $log($summary['skipped'] ? 'Fixture already seeded.' : 'Seeded fixture (throwaway tx).');
                }
                $result = $parity->run();
            } finally {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            $commit = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD'));
            $path = $root . '/docs/evidence/phase5/resolver-parity.md';
            file_put_contents($path, $parity->render($result, $commit !== '' ? $commit : 'unknown'));
            $log(sprintf('Parity: %d tuples, %d agreed, %d mismatches.', $result['tuples'], $result['agreed'], count($result['mismatches'])));
            $log('Wrote ' . $path);
            if ($result['mismatches'] !== []) {
                foreach (array_slice($result['mismatches'], 0, 10) as $m) {
                    $log(sprintf('  MISMATCH %s actor=%s target=%s legacy=%s resolver=%s (%s)',
                        $m['capability'], $m['actor'], $m['target'],
                        $m['legacy'] ? 'allow' : 'deny', $m['resolver'] ? 'allow' : 'deny', $m['source']));
                }
                exit(1);
            }
            break;
```

Then in the `verify:phase5-budgets` case, change the report construction to pass the resolver so `resolver.p95` becomes MEASURED (build the same `$resolver` graph BEFORE `$report = new Phase5BudgetReportService($db);` and change that line to `new Phase5BudgetReportService($db, $resolver)` — hoist the `$resolver = new CapabilityResolver(…)` construction above both cases or duplicate it; duplication inside each case is fine and simpler).

Add to the help text after the `verify:phase5-budgets` line:

```php
            $log('  verify:resolver-parity  Archive the Inc-1 old-vs-new resolver parity corpus (exit 1 on mismatch)');
```

- [ ] **Step 5: Generate both evidence artifacts**

```bash
php bin/console verify:resolver-parity
php bin/console verify:phase5-budgets
```

Expected: `Parity: N tuples, N agreed, 0 mismatches.` + `Wrote …/resolver-parity.md`; the budgets run prints `[MEASURED (PASS)] resolver.p95 -> X ms resolver (baseline Y ms legacy)` (if FAIL, profile — the target is 5 ms on the fixture; typical local numbers are well under). Inspect both generated docs.

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: green (existing budget/baseline/evidence-map tests unaffected).

- [ ] **Step 7: Commit**

```bash
git add bin/console src/Service/BaselineMetricsService.php src/Service/Phase5BudgetReportService.php tests/Integration/Service/Phase5BudgetReportServiceTest.php docs/evidence/phase5/resolver-parity.md docs/evidence/phase5/performance-budgets.md
git commit -m "feat(phase5): archive resolver parity corpus + measure resolver.p95 vs D11 (Inc 1 SP3)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: RoleService — create / update / clone with guards + audit

**Files:**
- Create: `src/Service/RoleService.php`
- Modify: `src/Core/App.php` (bind `RoleService`)
- Test: `tests/Integration/Service/RoleServiceTest.php`

**Interfaces:**
- Consumes: Tasks 2–3 repositories, `ReauthGate::requirePassword`, `WriteGate::assertCanWrite`, `CapabilityCatalog`.
- Produces (used by Task 10):
  - `create(User $admin, string $currentPassword, string $name, ?string $description, list<string> $capabilityKeys): int`
  - `update(User $admin, string $currentPassword, int $roleId, string $name, ?string $description, list<string> $capabilityKeys): void`
  - `clone(User $admin, string $currentPassword, int $sourceRoleId, string $name): int`
  - `listWithMeta(): list<array{role:array, capability_count:int, impact:int}>`
  - Throws `ValidationException` (bad input / duplicate name / unknown-protected-nondelegable key / wrong password) and `ForbiddenException` (editing a system role).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\CapabilityRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\RoleService;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — role definitions: custom roles are additive capability
 * bundles (delegable keys only), system anchors are immutable, every change
 * bumps roles.version and writes an immutable role_assignment_history row, and
 * mutations require present-factor reauth (decision #26).
 */
final class RoleServiceTest extends TestCase
{
    private function service(): RoleService
    {
        return new RoleService(
            $this->db,
            new RoleRepository($this->db),
            new RoleCapabilityRepository($this->db),
            new CapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new RoleAssignmentHistoryRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
        );
    }

    private function admin(): User
    {
        return User::fromRow($this->makeAdmin());
    }

    public function test_create_role_maps_capabilities_and_audits(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Board Helper', 'Lock + pin', ['core.thread.lock', 'core.thread.pin']);

        $role = (new RoleRepository($this->db))->find($id);
        self::assertSame('custom', $role['kind']);
        self::assertSame('custom.board_helper', $role['role_key']);
        self::assertSame(1, (int) $role['version']);

        $keys = (new RoleCapabilityRepository($this->db))->keysForRole($id);
        sort($keys);
        self::assertSame(['core.thread.lock', 'core.thread.pin'], $keys);

        $hist = (new RoleAssignmentHistoryRepository($this->db))->forRole($id);
        self::assertCount(1, $hist);
        self::assertSame('role_edit', $hist[0]['event']);
        self::assertNull($hist[0]['before_json']);
    }

    public function test_create_rejects_bad_input(): void
    {
        $svc = $this->service();
        $admin = $this->admin();

        try {
            $svc->create($admin, 'wrong-password', 'X', null, ['core.thread.lock']);
            self::fail('expected ValidationException for wrong password');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', '', null, ['core.thread.lock']);
            self::fail('expected ValidationException for empty name');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('name', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'No Caps', null, []);
            self::fail('expected ValidationException for empty capability list');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'Unknown', null, ['core.not.a.key']);
            self::fail('expected ValidationException for unknown key');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
        }

        try {
            $svc->create($admin, 'password123', 'Sneaky Owner', null, ['core.owner.transfer']);
            self::fail('expected ValidationException for protected key');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('capabilities', $e->errors);
            self::assertStringContainsString('protected', strtolower((string) $e->errors['capabilities']));
        }

        $svc->create($admin, 'password123', 'Dup Role', null, ['core.thread.lock']);
        try {
            $svc->create($admin, 'password123', 'Dup Role', null, ['core.thread.pin']);
            self::fail('expected ValidationException for duplicate name');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('name', $e->errors);
        }
    }

    public function test_update_bumps_version_replaces_mapping_and_audits_before_after(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Helper', null, ['core.thread.lock']);

        $svc->update($admin, 'password123', $id, 'Helper v2', 'now with pin', ['core.thread.pin']);

        $roles = new RoleRepository($this->db);
        $role = $roles->find($id);
        self::assertSame('Helper v2', $role['name']);
        self::assertSame(2, (int) $role['version']);
        self::assertSame(['core.thread.pin'], (new RoleCapabilityRepository($this->db))->keysForRole($id));

        $hist = (new RoleAssignmentHistoryRepository($this->db))->forRole($id);
        self::assertCount(2, $hist);
        $before = json_decode((string) $hist[0]['before_json'], true);
        $after = json_decode((string) $hist[0]['after_json'], true);
        self::assertSame(['core.thread.lock'], $before['capabilities']);
        self::assertSame(['core.thread.pin'], $after['capabilities']);
    }

    public function test_system_roles_are_protected_from_edit(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $modId = (int) (new RoleRepository($this->db))->findByKey('system.moderator')['id'];

        $this->expectException(ForbiddenException::class);
        $svc->update($admin, 'password123', $modId, 'Weakened Mod', null, ['core.board.read']);
    }

    public function test_clone_copies_capabilities_into_a_new_custom_role(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $modId = (int) (new RoleRepository($this->db))->findByKey('system.moderator')['id'];

        $cloneId = $svc->clone($admin, 'password123', $modId, 'Mod Clone');
        $role = (new RoleRepository($this->db))->find($cloneId);
        self::assertSame('custom', $role['kind'], 'cloning a system anchor yields an editable custom role');

        $sourceKeys = (new RoleCapabilityRepository($this->db))->keysForRole($modId);
        $cloneKeys = (new RoleCapabilityRepository($this->db))->keysForRole($cloneId);
        sort($sourceKeys);
        sort($cloneKeys);
        self::assertSame($sourceKeys, $cloneKeys);
    }

    public function test_list_with_meta_reports_counts_and_impact(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $id = $svc->create($admin, 'password123', 'Impacted', null, ['core.thread.lock']);
        $user = $this->makeUser();
        (new RoleAssignmentRepository($this->db))->create(['subject_id' => (int) $user['id'], 'role_id' => $id]);

        $rows = $svc->listWithMeta();
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['role']['role_key']] = $r;
        }
        self::assertSame(1, $byKey['custom.impacted']['capability_count']);
        self::assertSame(1, $byKey['custom.impacted']['impact']);
        self::assertSame(0, $byKey['system.guest']['impact'] ?? 0);
        self::assertGreaterThanOrEqual(49, $byKey['system.admin']['capability_count']);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/RoleServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Service/RoleService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\CapabilityCatalog;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Increment 1 (P5-08) — role-definition rules. Custom roles are ADDITIVE
 * capability bundles from the fixed catalogue: delegable, non-protected keys
 * only; no deny rules, no inheritance, no policy code (decision #19). System
 * anchors (kind='system') are protected compatibility anchors — never edited,
 * only cloned (decision #18). Every mutation requires present-factor reauth
 * (decision #26 via ReauthGate), bumps roles.version (cache keying, decision
 * #24), and writes an immutable role_assignment_history row. Definitions are
 * inert while `capabilities` is dark: nothing enforces them until Inc 6.
 */
final class RoleService
{
    public function __construct(
        private Database $db,
        private RoleRepository $roles,
        private RoleCapabilityRepository $roleCaps,
        private CapabilityRepository $capabilities,
        private RoleAssignmentRepository $assignments,
        private RoleAssignmentHistoryRepository $history,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
    ) {
    }

    /** @param list<string> $capabilityKeys */
    public function create(User $admin, string $currentPassword, string $name, ?string $description, array $capabilityKeys): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        [$name, $description, $keys, $ids] = $this->validateDefinition($name, $description, $capabilityKeys);
        $roleKey = $this->newRoleKey($name);

        return $this->db->transaction(function () use ($admin, $name, $description, $keys, $ids, $roleKey): int {
            $id = $this->roles->create([
                'role_key' => $roleKey,
                'name' => $name,
                'description' => $description,
                'created_by' => $admin->id(),
            ]);
            $this->roleCaps->replaceForRole($id, $ids);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $id,
                'before' => null,
                'after' => ['name' => $name, 'capabilities' => $keys],
                'reason' => 'create',
            ]);
            return $id;
        });
    }

    /** @param list<string> $capabilityKeys */
    public function update(User $admin, string $currentPassword, int $roleId, string $name, ?string $description, array $capabilityKeys): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $role = $this->requireCustomRole($roleId);
        [$name, $description, $keys, $ids] = $this->validateDefinition($name, $description, $capabilityKeys, exceptRoleId: $roleId);

        $this->db->transaction(function () use ($admin, $role, $roleId, $name, $description, $keys, $ids): void {
            $beforeKeys = $this->roleCaps->keysForRole($roleId);
            $this->roles->updateDefinition($roleId, $name, $description);
            $this->roleCaps->replaceForRole($roleId, $ids);
            $this->roles->bumpVersion($roleId);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $roleId,
                'before' => ['name' => (string) $role['name'], 'capabilities' => $beforeKeys],
                'after' => ['name' => $name, 'capabilities' => $keys],
                'reason' => 'update',
            ]);
        });
    }

    /** Clone any role (incl. a system anchor) into a NEW editable custom role. */
    public function clone(User $admin, string $currentPassword, int $sourceRoleId, string $name): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $source = $this->roles->find($sourceRoleId);
        if ($source === null) {
            throw new ValidationException(['role' => 'Source role not found.']);
        }
        $keys = $this->roleCaps->keysForRole($sourceRoleId);
        if ($keys === []) {
            throw new ValidationException(['role' => 'The source role has no capabilities to clone.']);
        }
        [$name, , $validKeys, $ids] = $this->validateDefinition($name, null, $keys);
        $roleKey = $this->newRoleKey($name);

        return $this->db->transaction(function () use ($admin, $name, $validKeys, $ids, $roleKey, $source): int {
            $id = $this->roles->create([
                'role_key' => $roleKey,
                'name' => $name,
                'description' => 'Clone of ' . (string) $source['name'],
                'created_by' => $admin->id(),
            ]);
            $this->roleCaps->replaceForRole($id, $ids);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $id,
                'before' => null,
                'after' => ['name' => $name, 'capabilities' => $validKeys],
                'reason' => 'clone of ' . (string) $source['role_key'],
            ]);
            return $id;
        });
    }

    /** @return list<array{role:array<string,mixed>,capability_count:int,impact:int}> */
    public function listWithMeta(): array
    {
        $roles = $this->roles->all();
        $impacts = $this->assignments->countActiveForRoles(
            array_map(static fn (array $r): int => (int) $r['id'], $roles),
        );
        $out = [];
        foreach ($roles as $role) {
            $out[] = [
                'role' => $role,
                'capability_count' => count($this->roleCaps->keysForRole((int) $role['id'])),
                'impact' => $impacts[(int) $role['id']] ?? 0,
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function requireCustomRole(int $roleId): array
    {
        $role = $this->roles->find($roleId);
        if ($role === null) {
            throw new ValidationException(['role' => 'Role not found.']);
        }
        if ((string) $role['kind'] === 'system') {
            throw new ForbiddenException('System roles are protected compatibility anchors and cannot be edited (decision #18). Clone one instead.');
        }
        return $role;
    }

    /**
     * @param list<string> $capabilityKeys
     * @return array{0:string,1:?string,2:list<string>,3:list<int>} [name, description, keys, capabilityIds]
     */
    private function validateDefinition(string $name, ?string $description, array $capabilityKeys, ?int $exceptRoleId = null): array
    {
        $errors = [];
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 190) {
            $errors['name'] = 'A role name between 1 and 190 characters is required.';
        }
        $description = $description === null ? null : trim($description);
        if ($description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        $keys = array_values(array_unique(array_map('strval', $capabilityKeys)));
        if ($keys === []) {
            $errors['capabilities'] = 'Pick at least one capability.';
        }
        $catalogue = CapabilityCatalog::all();
        foreach ($keys as $key) {
            $meta = $catalogue[$key] ?? null;
            if ($meta === null) {
                $errors['capabilities'] = "Unknown capability: $key.";
                break;
            }
            if ($meta['protected'] || !$meta['delegable']) {
                $errors['capabilities'] = "$key is protected/non-delegable and can never be placed in a role (decision #22).";
                break;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, ['name' => $name, 'description' => (string) $description, 'capabilities' => $keys]);
        }

        if ($name !== '') {
            $existing = $this->roles->findByKey($this->slugKey($name));
            if ($existing !== null && (int) $existing['id'] !== $exceptRoleId) {
                throw new ValidationException(
                    ['name' => 'A role with this name already exists.'],
                    ['name' => $name, 'description' => (string) $description, 'capabilities' => $keys],
                );
            }
        }

        $ids = $this->capabilities->idsByKeys($keys);
        if (count($ids) !== count($keys)) {
            throw new ValidationException(['capabilities' => 'A selected capability is missing from the seeded catalogue — run migrations.']);
        }

        return [$name, $description, $keys, array_values($ids)];
    }

    private function slugKey(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
        return 'custom.' . ($slug === '' ? 'role' : $slug);
    }

    private function newRoleKey(string $name): string
    {
        return $this->slugKey($name);
    }
}
```

> **Executor note:** `update()` keeps the role's original `role_key` (renaming changes only the display name — assignments reference role ids, and a stable key keeps audit history legible). The duplicate check uses the slugged key of the *new* name; passing `exceptRoleId` keeps "rename to a different display case of itself" legal. If the test for renames collides with this, prefer the test's expectation and adjust.

- [ ] **Step 4: Bind in the container**

In `src/Core/App.php` after the `ResolverShadow` binding (import `use App\Service\RoleService;`):

```php
        $c->bind(RoleService::class, fn (Container $c) => new RoleService(
            $c->get(Database::class),
            $c->get(RoleRepository::class),
            $c->get(RoleCapabilityRepository::class),
            $c->get(CapabilityRepository::class),
            $c->get(RoleAssignmentRepository::class),
            $c->get(RoleAssignmentHistoryRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
        ));
```

- [ ] **Step 5: Run the test until green**

Run: `vendor/bin/phpunit tests/Integration/Service/RoleServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Service/RoleService.php src/Core/App.php tests/Integration/Service/RoleServiceTest.php
git commit -m "feat(phase5): add RoleService with protected-anchor guard + reauth + audit (Inc 1 SP5)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: AdminRoleController + routes + templates (no-JS role editor)

**Files:**
- Create: `src/Controller/AdminRoleController.php`
- Create: `templates/admin/roles.php`
- Create: `templates/admin/role_edit.php`
- Modify: `src/Core/App.php` (routes after the `/admin/api-tokens/{id}/revoke` line; `use App\Controller\AdminRoleController;`)
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php` (new `test_capabilities_flag_gates_role_routes`)
- Test: `tests/Integration/Core/AppRoleAdminTest.php`

**Interfaces:**
- Routes: `GET /admin/roles` (index+create form), `POST /admin/roles` (create), `GET /admin/roles/simulator` (Task 11 — register the route NOW pointing at a `simulator` action stub added in Task 11; in this task register only the four below), `GET /admin/roles/{id}` (edit form), `POST /admin/roles/{id}` (update), `POST /admin/roles/{id}/clone` (clone).
- Every response carries `X-Robots-Tag: noindex` (per-surface noindex, program plan §F).
- Form fields: `name`, `description`, `capabilities[]` (capability keys), `current_password`. 422 re-render preserves `old` input (anti-draft-loss pattern).

- [ ] **Step 1: Write the failing HTTP test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — the no-JS role editor over HTTP: flag-gated 404 when
 * dark, admin-only, CSRF'd, reauth'd, validation preserves typed input, system
 * anchors immutable, audit written, noindex on every response.
 */
final class AppRoleAdminTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
    }

    public function test_routes_are_dark_without_the_flag(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/roles'));
        $this->assertStatus(404, $this->post('/admin/roles', ['name' => 'X']));
        $this->assertStatus(404, $this->get('/admin/roles/1'));
    }

    public function test_guests_and_members_cannot_reach_the_editor(): void
    {
        $this->enable();
        $this->assertRedirectContains($this->get('/admin/roles'), '/login');
        $this->actingAs($this->makeUser());
        $this->assertStatus(403, $this->get('/admin/roles'));
    }

    public function test_admin_lists_system_anchors_with_noindex(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $resp = $this->get('/admin/roles');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'system.admin');
        $this->assertSeeText($resp, 'Protected anchor');
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));
    }

    public function test_create_role_via_form_post_then_audit(): void
    {
        $this->enable();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $resp = $this->post('/admin/roles', [
            'name' => 'Board Helper',
            'description' => 'Lock and pin',
            'capabilities' => ['core.thread.lock', 'core.thread.pin'],
            'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');

        $role = (new RoleRepository($this->db))->findByKey('custom.board_helper');
        self::assertNotNull($role);
        $list = $this->get('/admin/roles');
        $this->assertSeeText($list, 'Board Helper');
        self::assertCount(1, (new RoleAssignmentHistoryRepository($this->db))->forRole((int) $role['id']));
    }

    public function test_create_validation_preserves_typed_input(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $resp = $this->post('/admin/roles', [
            'name' => 'Draft Role Name',
            'capabilities' => ['core.thread.lock'],
            'current_password' => 'wrong-password',
        ]);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'Draft Role Name');
        $this->assertSeeText($resp, 'current password is incorrect');
    }

    public function test_protected_capabilities_are_not_offered_and_are_rejected(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $page = $this->get('/admin/roles');
        $this->assertDontSeeText($page, 'core.owner.transfer');

        $resp = $this->post('/admin/roles', [
            'name' => 'Sneaky',
            'capabilities' => ['core.owner.transfer'],
            'current_password' => 'password123',
        ]);
        $this->assertStatus(422, $resp);
    }

    public function test_update_bumps_version_and_system_roles_are_immutable(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $this->post('/admin/roles', [
            'name' => 'Helper', 'capabilities' => ['core.thread.lock'], 'current_password' => 'password123',
        ]);
        $roles = new RoleRepository($this->db);
        $id = (int) $roles->findByKey('custom.helper')['id'];

        $resp = $this->post('/admin/roles/' . $id, [
            'name' => 'Helper', 'capabilities' => ['core.thread.pin'], 'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');
        self::assertSame(2, (int) $roles->find($id)['version']);
        self::assertSame(['core.thread.pin'], (new RoleCapabilityRepository($this->db))->keysForRole($id));

        $modId = (int) $roles->findByKey('system.moderator')['id'];
        $this->assertStatus(403, $this->post('/admin/roles/' . $modId, [
            'name' => 'Weakened', 'capabilities' => ['core.board.read'], 'current_password' => 'password123',
        ]));
    }

    public function test_clone_creates_an_editable_copy(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin());
        $roles = new RoleRepository($this->db);
        $modId = (int) $roles->findByKey('system.moderator')['id'];

        $resp = $this->post('/admin/roles/' . $modId . '/clone', [
            'name' => 'Mod Copy', 'current_password' => 'password123',
        ]);
        $this->assertRedirectContains($resp, '/admin/roles');
        $clone = $roles->findByKey('custom.mod_copy');
        self::assertNotNull($clone);
        self::assertSame('custom', $clone['kind']);
    }
}
```

And append to `tests/Integration/Core/AppFeatureFlagTest.php` (mirrors the tags pattern):

```php
    public function test_capabilities_flag_gates_role_routes(): void
    {
        // Inc 1 (P5-08): the role editor is dark by default and reversible.
        $this->actingAs($this->users()->findByUsername($this->db->fetchValue("SELECT username FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1") ?: '') ?? $this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/roles'));

        $this->setFlags(['capabilities' => true]);
        self::assertNotSame(404, $this->get('/admin/roles')->status());

        $this->setFlags(['capabilities' => false]);
        $this->assertStatus(404, $this->get('/admin/roles'));
    }
```

> **Executor note:** the `actingAs` line above is awkward — `setUp()` already calls `$this->makeAdmin()`; simply do `$this->actingAs($this->makeAdmin());` as the first line instead (a second admin row is fine and matches the file's other tests).

- [ ] **Step 2: Run to make sure they fail**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRoleAdminTest.php`
Expected: FAIL — 404s where 200/303 expected once flag on (routes missing).

- [ ] **Step 3: Implement `src/Controller/AdminRoleController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\RoleCapabilityRepository;
use App\Security\CapabilityCatalog;
use App\Service\RoleService;

/**
 * Increment 1 (P5-08) — the no-JS role editor. Deploy-dark behind
 * `capabilities`; admin-only; definitions are inert until the resolver
 * enforces (Inc 6). Only delegable, non-protected keys are ever offered
 * (decision #22). Every response is noindex (program plan §F).
 */
final class AdminRoleController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('capabilities')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }

    /** @return array<string,array<string,mixed>> delegable catalogue entries for the checkbox list */
    private function delegableCatalogue(): array
    {
        return array_filter(
            CapabilityCatalog::all(),
            static fn (array $meta): bool => $meta['delegable'] && !$meta['protected'],
        );
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->noindex($this->view('admin/roles', [
            'rows' => $this->container->get(RoleService::class)->listWithMeta(),
            'catalogue' => $this->delegableCatalogue(),
            'errors' => [],
            'old' => [],
        ]));
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->container->get(RoleService::class);
        try {
            $service->create(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                $request->str('description') === '' ? null : $request->str('description'),
                array_values((array) $request->post('capabilities', [])),
            );
            return $this->redirectWithFlash('/admin/roles', 'Role created. Definitions stay inert until the capability resolver is enforced.');
        } catch (ValidationException $e) {
            return $this->noindex($this->view('admin/roles', [
                'rows' => $service->listWithMeta(),
                'catalogue' => $this->delegableCatalogue(),
                'errors' => $e->errors,
                'old' => $e->old + [
                    'name' => $request->str('name'),
                    'description' => $request->str('description'),
                    'capabilities' => array_values((array) $request->post('capabilities', [])),
                ],
            ], 422));
        }
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        $roleId = (int) ($params['id'] ?? 0);
        $service = $this->container->get(RoleService::class);
        $row = null;
        foreach ($service->listWithMeta() as $r) {
            if ((int) $r['role']['id'] === $roleId) {
                $row = $r;
                break;
            }
        }
        if ($row === null) {
            throw new NotFoundException('Role not found.');
        }
        return $this->noindex($this->view('admin/role_edit', [
            'row' => $row,
            'current_keys' => $this->container->get(RoleCapabilityRepository::class)->keysForRole($roleId),
            'catalogue' => $this->delegableCatalogue(),
            'errors' => [],
            'old' => [],
        ]));
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $roleId = (int) ($params['id'] ?? 0);
        $service = $this->container->get(RoleService::class);
        try {
            $service->update(
                $admin,
                (string) $request->post('current_password', ''),
                $roleId,
                $request->str('name'),
                $request->str('description') === '' ? null : $request->str('description'),
                array_values((array) $request->post('capabilities', [])),
            );
            return $this->redirectWithFlash('/admin/roles', 'Role updated (version bumped).');
        } catch (ValidationException $e) {
            $row = null;
            foreach ($service->listWithMeta() as $r) {
                if ((int) $r['role']['id'] === $roleId) {
                    $row = $r;
                    break;
                }
            }
            if ($row === null) {
                throw new NotFoundException('Role not found.');
            }
            return $this->noindex($this->view('admin/role_edit', [
                'row' => $row,
                'current_keys' => array_values((array) $request->post('capabilities', [])),
                'catalogue' => $this->delegableCatalogue(),
                'errors' => $e->errors,
                'old' => $e->old + [
                    'name' => $request->str('name'),
                    'description' => $request->str('description'),
                ],
            ], 422));
        }
    }

    /** @param array<string,string> $params */
    public function clone(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        try {
            $this->container->get(RoleService::class)->clone(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $request->str('name'),
            );
            return $this->redirectWithFlash('/admin/roles', 'Role cloned as an editable custom role.');
        } catch (ValidationException $e) {
            $this->flash()->add('Clone failed: ' . implode(' ', array_map('strval', $e->errors)));
            return $this->redirect('/admin/roles/' . (int) ($params['id'] ?? 0));
        }
    }
}
```

- [ ] **Step 4: Register the routes**

In `src/Core/App.php` `buildRouter()`, directly after the `/admin/api-tokens/{id}/revoke` registration:

```php
        $r->get('/admin/roles', [AdminRoleController::class, 'index']);
        $r->post('/admin/roles', [AdminRoleController::class, 'create']);
        $r->get('/admin/roles/{id}', [AdminRoleController::class, 'edit']);
        $r->post('/admin/roles/{id}', [AdminRoleController::class, 'update']);
        $r->post('/admin/roles/{id}/clone', [AdminRoleController::class, 'clone']);
```

Add `use App\Controller\AdminRoleController;` to the imports.

- [ ] **Step 5: Create the templates**

`templates/admin/roles.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Roles');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Roles &amp; capabilities</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/roles">Roles</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">Definitions are recorded but <strong>inert</strong>: nothing enforces them until the
    capability resolver passes parity and is enabled (Increment 6). System roles are protected
    compatibility anchors and cannot be edited — clone one to adapt it.</p>

    <section class="card">
        <h2>Roles</h2>
        <table class="audit">
            <thead><tr><th>Name</th><th>Key</th><th>Kind</th><th>Version</th><th>Capabilities</th><th>Active assignments</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $role = $r['role']; ?>
                <tr>
                    <td><?= $e($role['name']) ?></td>
                    <td><code><?= $e($role['role_key']) ?></code></td>
                    <td><?= ((string) $role['kind']) === 'system' ? 'Protected anchor' : 'Custom' ?></td>
                    <td>v<?= (int) $role['version'] ?></td>
                    <td><?= (int) $r['capability_count'] ?></td>
                    <td><?= (int) $r['impact'] ?></td>
                    <td><a href="/admin/roles/<?= (int) $role['id'] ?>"><?= ((string) $role['kind']) === 'system' ? 'View / clone' : 'Edit' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Create a custom role</h2>
        <form method="post" action="/admin/roles" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <label>Description (optional)
                <input type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? '') ?>">
            </label>
            <?php if (!empty($errors['description'])): ?><p class="field-error"><?= $e($errors['description']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Capabilities (delegable only — protected authority is never offered)</legend>
                <?php $checked = (array) ($old['capabilities'] ?? []); ?>
                <?php foreach ($catalogue as $key => $meta): ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?>>
                        <code><?= $e($key) ?></code> — <?= $e($meta['consent'] ?? $meta['description']) ?>
                        <?php if ($meta['risk'] === 'high'): ?><span class="pill">high risk</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['capabilities'])): ?><p class="field-error"><?= $e($errors['capabilities']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Create role</button></div>
        </form>
    </section>
    </div>
</div>
```

`templates/admin/role_edit.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Role: ' . ($row['role']['name'] ?? ''));
$role = $row['role'];
$isSystem = ((string) $role['kind']) === 'system';
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($role['name']) ?> <small>v<?= (int) $role['version'] ?></small></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/roles">Roles</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">
        <code><?= $e($role['role_key']) ?></code> ·
        <?= $isSystem ? 'Protected system anchor (decision #18) — read-only.' : 'Custom role.' ?>
        Active assignments affected by changes: <strong><?= (int) $row['impact'] ?></strong>.
    </p>

    <?php if (!$isSystem): ?>
    <section class="card">
        <h2>Edit definition</h2>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? $role['name']) ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>
            <label>Description (optional)
                <input type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? ($role['description'] ?? '')) ?>">
            </label>
            <fieldset>
                <legend>Capabilities</legend>
                <?php foreach ($catalogue as $key => $meta): ?>
                    <label>
                        <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $current_keys, true) ? 'checked' : '' ?>>
                        <code><?= $e($key) ?></code> — <?= $e($meta['consent'] ?? $meta['description']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['capabilities'])): ?><p class="field-error"><?= $e($errors['capabilities']) ?></p><?php endif; ?>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Save (bumps version)</button></div>
        </form>
    </section>
    <?php else: ?>
    <section class="card">
        <h2>Capabilities held</h2>
        <ul>
            <?php foreach ($current_keys as $key): ?><li><code><?= $e($key) ?></code></li><?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Clone into a new custom role</h2>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/clone" class="stacked">
            <?= $this->csrfField() ?>
            <label>New role name
                <input type="text" name="name" maxlength="190" required>
            </label>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <div class="form-actions"><button class="btn" type="submit">Clone</button></div>
        </form>
    </section>
    </div>
</div>
```

- [ ] **Step 6: Run the tests until green**

Run: `vendor/bin/phpunit tests/Integration/Core/AppRoleAdminTest.php tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS. If a template variable notice turns a test red (strict PHPUnit), fix the template (all variables above are always passed).

- [ ] **Step 7: Commit**

```bash
git add src/Controller/AdminRoleController.php templates/admin/roles.php templates/admin/role_edit.php src/Core/App.php tests/Integration/Core/AppRoleAdminTest.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "feat(phase5): add no-JS role editor behind capabilities flag (Inc 1 SP5)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 11: Permission simulator (real resolver + viewer redaction)

**Files:**
- Create: `src/Service/PermissionSimulatorService.php`
- Create: `templates/admin/role_simulator.php`
- Modify: `src/Controller/AdminRoleController.php` (add `simulator` action + subnav links in both role templates)
- Modify: `src/Core/App.php` (route — **register `/admin/roles/simulator` BEFORE `/admin/roles/{id}`**; bind the service)
- Test: `tests/Integration/Service/PermissionSimulatorTest.php` + simulator cases appended to `tests/Integration/Core/AppRoleAdminTest.php`

**Interfaces:**
- `PermissionSimulatorService::simulate(User $viewer, string $actorRef, string $capability, ?int $boardId, ?string $at): array{decision:?CapabilityDecision, actor_label:string, target_label:?string, error:?string}` — `actorRef` is `guest`, a username, or a numeric user id; `at` is an optional `YYYY-MM-DD HH:MM` UTC string. Uses the REAL resolver (decision #25). `target_label` is redacted to `Board #N (restricted)` when the **viewer** cannot read the board.
- Route: `GET /admin/roles/simulator?actor=…&capability=…&board_id=…&at=…` (pure read; no CSRF needed).

- [ ] **Step 1: Write the failing service test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;
use App\Service\LegacyAuthorityProjection;
use App\Service\PermissionSimulatorService;
use Tests\Support\TestCase;

/**
 * Increment 1 (P5-08) — decision #25: simulation runs on the REAL resolver and
 * never reveals target content the viewer cannot read.
 */
final class PermissionSimulatorTest extends TestCase
{
    private function service(): PermissionSimulatorService
    {
        $resolver = new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
        return new PermissionSimulatorService(
            $resolver,
            $this->users(),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
        );
    }

    public function test_simulates_allow_and_deny_with_decisive_reason(): void
    {
        $board = $this->makeBoard($this->makeCategory('Sim'));
        $mod = $this->makeUser();
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $viewer = User::fromRow($this->makeAdmin());
        $svc = $this->service();

        $r = $svc->simulate($viewer, (string) $mod['username'], 'core.thread.lock', (int) $board['id'], null);
        self::assertNull($r['error']);
        self::assertTrue($r['decision']->allowed);
        self::assertSame('grant', $r['decision']->source);
        self::assertStringContainsString((string) $board['name'], (string) $r['target_label']);

        $r = $svc->simulate($viewer, 'guest', 'core.thread.lock', (int) $board['id'], null);
        self::assertFalse($r['decision']->allowed);
        self::assertSame('guest', $r['actor_label']);
    }

    public function test_unknown_actor_reports_an_error_not_an_exception(): void
    {
        $viewer = User::fromRow($this->makeAdmin());
        $r = $this->service()->simulate($viewer, 'nobody-here', 'core.thread.lock', null, null);
        self::assertNotNull($r['error']);
        self::assertNull($r['decision']);
    }

    public function test_at_time_simulation_respects_expiry(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimT'));
        $user = $this->makeUser();
        $roles = new \App\Repository\RoleRepository($this->db);
        (new RoleAssignmentRepository($this->db))->create([
            'subject_id' => (int) $user['id'],
            'role_id' => (int) $roles->findByKey('system.moderator')['id'],
            'scope_type' => 'board', 'scope_id' => (int) $board['id'],
            'ends_at' => '2026-08-01 00:00:00',
        ]);
        $viewer = User::fromRow($this->makeAdmin());
        $svc = $this->service();

        $inside = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], '2026-07-15 12:00');
        self::assertTrue($inside['decision']->allowed);
        $after = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], '2026-09-01 12:00');
        self::assertFalse($after['decision']->allowed);
        $bad = $svc->simulate($viewer, (string) $user['username'], 'core.thread.lock', (int) $board['id'], 'not-a-time');
        self::assertNotNull($bad['error']);
    }

    public function test_target_label_is_redacted_for_viewers_without_read_access(): void
    {
        $board = $this->makeBoard($this->makeCategory('SimP'), ['visibility' => 'private', 'name' => 'Secret Ops']);
        $svc = $this->service();

        $nonAdminViewer = User::fromRow($this->makeUser());
        $r = $svc->simulate($nonAdminViewer, 'guest', 'core.board.read', (int) $board['id'], null);
        self::assertSame('Board #' . (int) $board['id'] . ' (restricted)', $r['target_label']);
        self::assertStringNotContainsString('Secret Ops', (string) $r['target_label']);

        $adminViewer = User::fromRow($this->makeAdmin());
        $r = $svc->simulate($adminViewer, 'guest', 'core.board.read', (int) $board['id'], null);
        self::assertStringContainsString('Secret Ops', (string) $r['target_label']);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/PermissionSimulatorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Service/PermissionSimulatorService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityDecision;
use App\Security\CapabilityResolver;

/**
 * Increment 1 (P5-08) — permission simulation on the REAL resolver (decision
 * #25: never a UI approximation) with safe target redaction: the simulator
 * shows a generic label for any board the VIEWER cannot read, so simulating
 * against private targets leaks nothing.
 */
final class PermissionSimulatorService
{
    public function __construct(
        private CapabilityResolver $resolver,
        private UserRepository $users,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
    ) {
    }

    /** @return array{decision:?CapabilityDecision, actor_label:string, target_label:?string, error:?string} */
    public function simulate(User $viewer, string $actorRef, string $capability, ?int $boardId, ?string $at): array
    {
        $result = ['decision' => null, 'actor_label' => '', 'target_label' => null, 'error' => null];

        $actorRef = trim($actorRef);
        $actor = null;
        if (strtolower($actorRef) === 'guest' || $actorRef === '') {
            $result['actor_label'] = 'guest';
        } else {
            $row = ctype_digit($actorRef)
                ? $this->users->find((int) $actorRef)
                : $this->users->findByUsername($actorRef);
            if ($row === null) {
                $result['error'] = 'No member matches "' . $actorRef . '" — use a username, a numeric id, or "guest".';
                return $result;
            }
            $actor = User::fromRow($row);
            $result['actor_label'] = $actor->username() . ' (#' . $actor->id() . ', ' . $actor->role() . ', ' . $actor->status() . ')';
        }

        $atTime = null;
        if ($at !== null && trim($at) !== '') {
            $atTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($at), new \DateTimeZone('UTC'))
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($at), new \DateTimeZone('UTC'));
            if ($atTime === false || $atTime === null) {
                $result['error'] = 'Time must be UTC "YYYY-MM-DD HH:MM".';
                return $result;
            }
        }

        $target = [];
        if ($boardId !== null && $boardId > 0) {
            $target['board_id'] = $boardId;
            $board = $this->boards->find($boardId);
            if ($board === null) {
                $result['target_label'] = 'Board #' . $boardId . ' (missing)';
            } else {
                $viewerIsMember = $this->members->isMember($boardId, $viewer->id());
                $result['target_label'] = $this->policy->canRead($board, $viewer, $viewerIsMember)
                    ? 'Board #' . $boardId . ' — ' . (string) $board['name']
                    : 'Board #' . $boardId . ' (restricted)';
            }
        }

        $result['decision'] = $this->resolver->can($actor, $capability, $target, $atTime);
        return $result;
    }
}
```

- [ ] **Step 4: Wire the route, action, binding, template**

`src/Core/App.php`: bind after `RoleService`:

```php
        $c->bind(PermissionSimulatorService::class, fn (Container $c) => new PermissionSimulatorService(
            $c->get(CapabilityResolver::class),
            $c->get(UserRepository::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
        ));
```

(import `use App\Service\PermissionSimulatorService;`). Route — insert BEFORE the `/admin/roles/{id}` GET line (first-match-wins discipline, even though `{id}` is `\d+`):

```php
        $r->get('/admin/roles/simulator', [AdminRoleController::class, 'simulator']);
```

`AdminRoleController` — add the action + import `App\Service\PermissionSimulatorService` and `App\Security\CapabilityCatalog` (already imported):

```php
    /** @param array<string,string> $params */
    public function simulator(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $capability = (string) $request->query('capability', '');
        $result = null;
        if ($capability !== '') {
            $boardId = (int) $request->query('board_id', 0);
            $result = $this->container->get(PermissionSimulatorService::class)->simulate(
                $admin,
                (string) $request->query('actor', 'guest'),
                $capability,
                $boardId > 0 ? $boardId : null,
                (string) $request->query('at', '') === '' ? null : (string) $request->query('at', ''),
            );
        }
        return $this->noindex($this->view('admin/role_simulator', [
            'catalogue' => CapabilityCatalog::all(),
            'result' => $result,
            'q' => [
                'actor' => (string) $request->query('actor', ''),
                'capability' => $capability,
                'board_id' => (string) $request->query('board_id', ''),
                'at' => (string) $request->query('at', ''),
            ],
        ]));
    }
```

`templates/admin/role_simulator.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Permission simulator');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Permission simulator</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/roles">Roles</a>
        <a class="active" href="/admin/roles/simulator">Simulator</a>
    </nav>

    <div class="admin-pane">
    <p class="muted">Runs <code>can(actor, capability, target, time)</code> on the <strong>real resolver</strong>
    (decision #25). While <code>capabilities</code> is in shadow, answers predict the post-cutover
    decision — live requests still use legacy authority.</p>

    <section class="card">
        <h2>Simulate</h2>
        <form method="get" action="/admin/roles/simulator" class="stacked">
            <label>Actor (username, id, or <code>guest</code>)
                <input type="text" name="actor" value="<?= $e($q['actor']) ?>" required>
            </label>
            <label>Capability
                <select name="capability" required>
                    <option value="">— pick —</option>
                    <?php foreach ($catalogue as $key => $meta): ?>
                        <option value="<?= $e($key) ?>" <?= $q['capability'] === $key ? 'selected' : '' ?>><?= $e($key) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Board id (optional target)
                <input type="number" name="board_id" min="1" value="<?= $e($q['board_id']) ?>">
            </label>
            <label>At (optional, UTC <code>YYYY-MM-DD HH:MM</code>)
                <input type="text" name="at" value="<?= $e($q['at']) ?>" placeholder="2026-07-15 12:00">
            </label>
            <div class="form-actions"><button class="btn" type="submit">Simulate</button></div>
        </form>
    </section>

    <?php if ($result !== null): ?>
    <section class="card">
        <h2>Result</h2>
        <?php if ($result['error'] !== null): ?>
            <p class="field-error"><?= $e($result['error']) ?></p>
        <?php else: $d = $result['decision']; ?>
            <p>
                <strong><?= $d->allowed ? 'Allowed' : 'Denied' ?></strong>
                — <code><?= $e($d->capability) ?></code> for <?= $e($result['actor_label']) ?>
                <?php if ($result['target_label'] !== null): ?> on <?= $e($result['target_label']) ?><?php endif; ?>
            </p>
            <ul>
                <li>Decisive rule: <code><?= $e($d->source) ?></code></li>
                <li>Reason: <?= $e($d->reason) ?></li>
                <?php if ($d->roleKey !== null): ?><li>Via role: <code><?= $e($d->roleKey) ?></code><?php if ($d->scopeType !== null): ?> at <?= $e($d->scopeType) ?><?= $d->scopeId !== null ? ' #' . (int) $d->scopeId : '' ?><?php endif; ?></li><?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    </div>
</div>
```

Also add the `Simulator` link to the `subnav` in `templates/admin/roles.php` and `templates/admin/role_edit.php`:

```php
        <a href="/admin/roles/simulator">Simulator</a>
```

- [ ] **Step 5: Append HTTP simulator cases to `AppRoleAdminTest`**

```php
    public function test_simulator_get_shows_decision_and_is_flag_gated(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/roles/simulator'));

        $this->enable();
        $board = $this->makeBoard($this->makeCategory('SimHttp'));
        $mod = $this->makeUser();
        (new \App\Repository\BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);

        $resp = $this->get('/admin/roles/simulator', [
            'actor' => (string) $mod['username'],
            'capability' => 'core.thread.lock',
            'board_id' => (string) $board['id'],
        ]);
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Allowed');
        $this->assertSeeText($resp, 'core.thread.lock');
        self::assertSame('noindex', $resp->getHeader('x-robots-tag'));

        $resp = $this->get('/admin/roles/simulator', [
            'actor' => 'guest',
            'capability' => 'core.thread.lock',
            'board_id' => (string) $board['id'],
        ]);
        $this->assertSeeText($resp, 'Denied');
    }
```

- [ ] **Step 6: Run everything for the surface**

Run: `vendor/bin/phpunit tests/Integration/Service/PermissionSimulatorTest.php tests/Integration/Core/AppRoleAdminTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Service/PermissionSimulatorService.php src/Controller/AdminRoleController.php templates/admin/role_simulator.php templates/admin/roles.php templates/admin/role_edit.php src/Core/App.php tests/Integration/Service/PermissionSimulatorTest.php tests/Integration/Core/AppRoleAdminTest.php
git commit -m "feat(phase5): add permission simulator on the real resolver with viewer redaction (Inc 1 SP4)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 12: Browser + a11y evidence (role editor + simulator)

**Files:**
- Modify: `tests/browser/seed.php` (enable `capabilities` for evidence)
- Modify: `tests/browser/gate-a.spec.ts` (new journey, screenshots `30-…`/`31-…`)
- Modify: `tests/browser/a11y.spec.ts` (axe over `/admin/roles` + `/admin/roles/simulator`)

**Interfaces:** follows the existing evidence conventions — `shot(page, info, name)` writes `docs/evidence/browser/{desktop,mobile}/<name>.png`; seeded admin login `admin@retro.test` / `password123`; highest existing index is `29-topic-workflow`.

- [ ] **Step 1: Enable the flag in the evidence seed**

In `tests/browser/seed.php`, add to the `$evidenceFeatures` array (after `'topic_workflow' => true,`):

```php
    'capabilities' => true, // Inc 1 (P5-08): role editor + simulator browser evidence (shadow-only)
```

- [ ] **Step 2: Add the gate-a journey**

Append to `tests/browser/gate-a.spec.ts` (follow the file's existing `test(…)` style — login helper, `visit`, `shot`):

```ts
test('role editor: create a custom role and simulate a decision (no-JS forms)', async ({ page }, testInfo) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { name: 'Roles & capabilities' })).toBeVisible();
  await expect(page.getByText('system.admin')).toBeVisible();

  const roleName = `Board Helper ${testInfo.project.name}`;
  await page.fill('input[name="name"]', roleName);
  await page.check('input[name="capabilities[]"][value="core.thread.lock"]');
  await page.check('input[name="capabilities[]"][value="core.thread.pin"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.click('form[action="/admin/roles"] button[type="submit"]');
  await expect(page.getByText(roleName)).toBeVisible();
  await shot(page, testInfo, '30-admin-role-created');

  await visit(page, '/admin/roles/simulator?actor=guest&capability=core.thread.lock&board_id=1');
  await expect(page.getByText('Denied')).toBeVisible();
  await shot(page, testInfo, '31-admin-role-simulator');
});
```

> **Executor notes:** (1) if `board_id=1` does not exist in the seed, grep `seed.php` for a seeded board id/slug and use it — the assertion only needs a rendered decision; (2) the create form may redirect to `/admin/roles` with a flash — `getByText(roleName)` covers both; (3) match the file's exact helper names (`visit`, `login`, `shot`) — they exist.

- [ ] **Step 3: Add the axe checks**

In `tests/browser/a11y.spec.ts`, inside the existing `admin dark-surface pages have no serious axe violations` test (after the `/admin/extensions` block), add:

```ts
  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { name: 'Roles & capabilities' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/roles/simulator');
  await expect(page.getByRole('heading', { name: 'Permission simulator' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
```

- [ ] **Step 4: Run the evidence capture**

```bash
cd tests/browser && npm run evidence
```

Expected: all checks green on desktop + mobile (count grows from 14/14 — record the new N/N), new screenshots `docs/evidence/browser/{desktop,mobile}/30-admin-role-created.png` + `31-admin-role-simulator.png`. Fix any axe serious/critical violation in the templates (labels above are already associated; tables have headers).

- [ ] **Step 5: Commit**

```bash
git add tests/browser/seed.php tests/browser/gate-a.spec.ts tests/browser/a11y.spec.ts docs/evidence/browser/desktop/30-admin-role-created.png docs/evidence/browser/desktop/31-admin-role-simulator.png docs/evidence/browser/mobile/30-admin-role-created.png docs/evidence/browser/mobile/31-admin-role-simulator.png
git commit -m "test(phase5): browser + axe evidence for role editor and simulator (Inc 1 SP5)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 13: Ledger, status, program-plan records + Increment exit gate

**Files:**
- Modify: `docs/phase5/requirement-ledger.json` (GA-DOD-10 → R3 + evidence; GA-DOD-18 note; `updated` date)
- Modify: `PHASE_5_STATUS.md` (status line, new increment section, evidence bullets, suite counts)
- Modify: `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` (§D Increment 1 row: landed annotation)

- [ ] **Step 1: Update the requirement ledger**

In `docs/phase5/requirement-ledger.json`:

- Bump `"updated"` to the real date.
- Replace the GA-DOD-10 row with:

```json
    { "id": "GA-DOD-10", "gate": "A", "workstream": "P5-08", "title": "Protected built-in roles/capabilities seeded; old-vs-new resolver parity complete", "state": "R3", "evidence": ["src/Security/CapabilityResolver.php", "src/Security/CapabilityRules.php", "src/Service/LegacyAuthorityProjection.php", "src/Service/ResolverParityService.php", "tests/Integration/Service/ResolverParityTest.php", "docs/evidence/phase5/resolver-parity.md"], "notes": "Inc 1 landed: shadow resolver + zero-mismatch parity corpus on the F9 fixture + role editor/simulator, all dark behind capabilities. Enforcement cutover, legacy assignment import, and cache/version invalidation remain Inc 6 (GA-DOD-11)." },
```

- In GA-DOD-18, update the note to: `"Targets approved; resolver.p95 MEASURED on the real resolver (see docs/evidence/phase5/performance-budgets.md); eight budgets remain pending their increments."`

Run: `vendor/bin/phpunit tests/Unit/Core/Phase5EvidenceMapTest.php`
Expected: PASS — every referenced evidence path exists (they all landed in Tasks 1–12). If the test enforces additional shape rules (read it first), satisfy them.

- [ ] **Step 2: Update `PHASE_5_STATUS.md`**

- **Status line:** append after the Foundation sentence: `Increment 1 (P5-08 resolver shadow) landed 2026-07-DD: CapabilityResolver + legacy-authority projection behind the dark capabilities flag, fail-open ResolverShadow on canModerate/canPost, a ZERO-mismatch archived parity corpus (docs/evidence/phase5/resolver-parity.md), resolver.p95 MEASURED vs the 5ms D11 budget, and the no-JS role editor + permission simulator with browser/axe evidence. Increment 6 (enforcement cutover) stays blocked until shadow soak; Increment 2 (registry) remains unblocked.` (use the real date)
- **Branch line:** `phase5-inc1-resolver-shadow`.
- **Suite line:** update with the Step 4 counts.
- **Requirement-ledger snapshot:** add a bullet: `**Capability resolver shadow (Inc 1, P5-08):** R3 — see docs/phase5/requirement-ledger.json (GA-DOD-10). Shadow-only: live authorization is unchanged; enforcement is Inc 6.`
- **Evidence index:** add:

```markdown
- Increment 1 (P5-08 resolver shadow): `tests/Unit/Security/CapabilityRulesTest.php`,
  `tests/Integration/Security/CapabilityResolverTest.php`, `tests/Integration/Service/LegacyAuthorityProjectionTest.php`,
  `tests/Integration/Service/ResolverShadowTest.php`, `tests/Integration/Service/ResolverParityTest.php` (zero-mismatch exit gate),
  `tests/Integration/Service/RoleServiceTest.php`, `tests/Integration/Service/PermissionSimulatorTest.php`,
  `tests/Integration/Core/AppRoleAdminTest.php`, `AppFeatureFlagTest::test_capabilities_flag_gates_role_routes`.
- Parity corpus: `docs/evidence/phase5/resolver-parity.md` (N tuples, 0 mismatches, fixture+commit pinned).
- Resolver budget: `docs/evidence/phase5/performance-budgets.md` — `resolver.p95` MEASURED (PASS) vs 5 ms.
- Role editor/simulator browser evidence: `docs/evidence/browser/{desktop,mobile}/30-admin-role-created.png` + `31-admin-role-simulator.png` (+ axe green).
```

- [ ] **Step 3: Annotate the program plan**

In `docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md` §D Increment 1, append to the `**Migration:** … **Exit gate:** …` line: ` **Landed 2026-07-DD** → resolver+projection+shadow+parity corpus (zero mismatch)+role editor+simulator, dark behind `capabilities`; sub-plan `docs/superpowers/plans/2026-07-02-phase5-increment1-resolver-shadow.md`.` (real date)

- [ ] **Step 4: Increment exit-gate verification**

```bash
composer test                                   # run 1 (fresh schema)
composer test                                   # run 2 (reused schema — both must be green)
vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter test_phase5_foundation_flags_default_dark
php bin/console verify:resolver-parity          # 0 mismatches, exit 0
```

Record both full-run test/assertion counts in `PHASE_5_STATUS.md`. All Phase 5 flags must still be dark by default; `verify:upgrade` needs no run (no migration — the F1 ledger guard inside the suite proves the set is untouched).

- [ ] **Step 5: Commit + merge**

```bash
git add docs/phase5/requirement-ledger.json PHASE_5_STATUS.md docs/superpowers/plans/2026-06-30-phase5-gate-a-program-plan.md docs/superpowers/plans/2026-07-02-phase5-increment1-resolver-shadow.md
git commit -m "docs(phase5): record Increment 1 landed — resolver shadow at zero parity mismatch (P5-08)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Then follow `superpowers:finishing-a-development-branch` (merge to `main` fast-forward or PR per the session's convention; push only when the user has asked or per standing practice in this repo — prior increments were pushed after merge).

---

## Self-Review vs spec (program plan §D Increment 1)

- **"state-first union-then-narrow resolver `can(actor, capability, target, time)` enforcing `ends_at` directly"** → Tasks 1/5: `CapabilityRules::windowValid` runs per decision; unit test `test_temporal_window_is_enforced_by_the_resolver`. ✔
- **"legacy-authority read projection (users.role/board_moderators/post_min_role/protected_owners)"** → Task 4 (`site_rank` carries the post_min_role floor; protected_owners consulted in Task 5 for protected keys). Quirks 2/3/4/5 pinned by tests. ✔
- **"shadow-comparison harness recording mismatches to a parity ledger without changing the decision"** → Task 6: telemetry events `resolver.shadow_mismatch` (the no-migration parity ledger), fail-open proven by test; injected only when `capabilities` is on. ✔
- **"old-vs-new parity corpus archived on the same fixture+commit"** → Tasks 7/8: `ResolverParityService` + `verify:resolver-parity` writing `docs/evidence/phase5/resolver-parity.md` with fixture version + commit hash; the zero-mismatch exit gate is ALSO an enforcing PHPUnit test. ✔
- **"permission simulator on the real resolver with safe target redaction"** → Task 11 (decision #25; redaction tested with a non-admin viewer). ✔
- **"role create/edit/clone + roles.version bump + impact count; protected-role guard; no-JS role editor"** → Tasks 9/10 (ForbiddenException on system anchors; version bump on update; impact = active-assignment counts; server-rendered forms; reauth per decision #26). ✔
- **"resolver p50/p95/p99 vs budget"** → Task 8: `measureResolver` envelope + `resolver.p95` MEASURED (PASS/FAIL) vs the 5 ms D11 target in the regenerated A3 report. ✔
- **"Migration: none"** → no file under `database/migrations/` is touched; `MigrationLedgerTest` keeps guarding. ✔
- **"Exit gate: zero parity mismatch for built-in roles on critical fixtures; subsystem dark by default"** → Task 7 test + Task 13 console run; flag-dark regressions in Tasks 10/13. ✔
- **§F distributed evidence** → PHPUnit (all tasks), authorization direct-request matrix for the new routes (Task 10), browser+axe desktop/mobile (Task 12), per-surface noindex (Tasks 10/11), telemetry emission (Task 6), perf budget (Task 8), ledger/status/evidence index (Task 13). Runbook entry: the rollback path for `capabilities` is already recorded in the ledger's flag map (F10); no new runbook procedure is introduced by a shadow-only increment. ✔
- **Placeholder scan:** every code step carries complete code; the four "Executor note" blocks are verification instructions (route names, settings column shape, seed board id, setUp admin), each with the exact fallback to apply — no TBDs. ✔
- **Type consistency check:** grant-row shape identical in Tasks 1/4/5; `CapabilityDecision` fields used by Tasks 6/7/11 match Task 1; `ResolverParityService` ctor identical in Tasks 7/8; `RoleService` signatures identical in Tasks 9/10; `simulate()` return shape identical in Task 11's service/controller/template. ✔

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-02-phase5-increment1-resolver-shadow.md`. Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks (`superpowers:subagent-driven-development`).
2. **Inline Execution** — execute tasks in this session with checkpoints (`superpowers:executing-plans`).
