# Category/Board Reorder + Board Archive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admins drag/up-down reordering of categories and boards plus a reversible board **archive** that makes a board absolute read-only for every role (members, moderators, admins) while it stays listed in nav, readable, and searchable.

**Architecture:** Reorder + archive land as new `AdminService` methods (dense position renumber + `is_archived` toggle, each audited inside `$db->transaction`) wired to new `requireAdmin` `AdminController` actions and `/admin/structure` template buttons. Read-only enforcement is centralised in the pure `BoardPolicy::canPost` (false when archived) and backstopped by explicit `ForbiddenException` guards on the few write paths that do not consult `canPost` (post edit/delete-own, thread status, wiki edit). Archived boards are deliberately excluded from `canRead`/`isListed`/search changes — they stay visible and findable; only writes are closed.

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB, PSR-4 `App\` → `src/`, plain-PHP templates, PHPUnit integration tests via `Tests\Support\TestCase`, Playwright browser evidence. No framework, no new migration (the `boards.is_archived` and `boards.position`/`categories.position` columns already exist — `database/migrations/0012_boards_phase2_columns.php`, `0002_categories.php`, `0004_boards.php`).

## Global Constraints

This plan **incorporates by reference** the binding contract at
`docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md`
(locked decisions, route/flag/nav/audit allocation, idioms, execution order). Where this plan and the contract differ, the contract wins — implement per contract and record any deviation in the manifest.

Group-specific constraints (Group B — reorder + archive):

- **UNGATED.** This surface is part of core admin *structure* and ships with **no feature flag**. Do not add a flag, do not add an `AppFeatureFlagTest` dark assertion.
- **No migration.** `boards.is_archived TINYINT(1) DEFAULT 0`, `boards.position`, and `categories.position` already exist. If you think you need a migration, you have misread the schema.
- **Authority + audit on every mutation.** Every admin action gates on `requireAdmin()` (→ `ForbiddenException`/403 for non-admins, redirecting 302 to `/login?next=` for guests) and routes through `AdminService`, whose `assertAdmin()` already calls `WriteGate::assertCanWrite` (state beats role — a suspended admin is blocked). Every mutation writes exactly one `moderation_log` row via `ModerationLogRepository::log([...])` with the contract-assigned `target_type`: category reorder → `target_type='category'`; board reorder / archive / unarchive → `target_type='board'`. Only existing ENUM values (`category`, `board`) are used — no ENUM change.
- **CSRF on every POST** (`<?= $this->csrfField() ?>`); never a mutating GET.
- **Strict CSP / PE.** No inline `<script>`/`<style>`. The up/down **and** drag flows must work as server-rendered forms; JS in `public/assets/app.js` only decorates via `data-*` hooks (Task 9, optional).
- **Anti-draft-loss.** Controllers catch `App\Core\ValidationException` themselves: the bulk `reorder` action re-renders `admin/structure` at **422** with `reorder_error`; the up/down/archive actions funnel through `AdminController::run()` which redirects with `$e->first()` as a flash.
- **DB rules.** `EMULATE_PREPARES=false`: dense renumber clamps positions to int and never binds `LIMIT`/`OFFSET`; never reuse a named placeholder. UTC everywhere. Every multi-table mutation runs inside `$db->transaction(fn)`.
- **Counters untouched.** Archive must NOT recompute or zero `boards.*_count` — content is preserved. No `RepairService` hook.
- **Absolute read-only.** "Read-only" covers new thread, new reply, post edit, post delete-own, thread status/workflow change, and wiki edit — for **all** roles. No moderator/admin carve-out; only **unarchive** re-enables writes.

---

### Task 1: `BoardPolicy` — archive closes posting, keeps read/list open

**Files:**
- Modify: `src/Security/BoardPolicy.php` (add `isArchived()`; `canPost()` ~54-64 short-circuits on archived; `canRead()` ~27 + `isListed()` ~37 stay unchanged)
- Test: `tests/Unit/Security/BoardPolicyArchiveTest.php` (Create)

**Interfaces:**
- Produces: `App\Security\BoardPolicy::isArchived(array $board): bool`
- Produces (changed): `App\Security\BoardPolicy::canPost(array $board, App\Domain\User $user, bool $isMember): bool` — returns `false` when `(int)($board['is_archived'] ?? 0) === 1`
- Consumes: `App\Domain\User::isAdmin()/isModerator()` (`src/Domain/User.php:57,62`)

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\User;
use App\Security\BoardPolicy;
use PHPUnit\Framework\TestCase;

final class BoardPolicyArchiveTest extends TestCase
{
    private function user(string $role = 'user'): User
    {
        return User::fromRow(['id' => 1, 'username' => 'u', 'role' => $role, 'status' => 'active']);
    }

    public function test_archived_public_board_is_still_readable_and_listed(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'is_archived' => 1];

        self::assertTrue($policy->isArchived($board));
        self::assertTrue($policy->canRead($board, $this->user(), false), 'archived stays readable');
        self::assertTrue($policy->isListed($board, $this->user(), false), 'archived stays listed');
    }

    public function test_archived_board_cannot_be_posted_to_by_any_role(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'post_min_role' => 'user', 'is_archived' => 1];

        self::assertFalse($policy->canPost($board, $this->user('user'), false));
        self::assertFalse($policy->canPost($board, $this->user('moderator'), false));
        self::assertFalse($policy->canPost($board, $this->user('admin'), false), 'no admin carve-out');
    }

    public function test_live_board_still_allows_posting(): void
    {
        $policy = new BoardPolicy();
        $board = ['visibility' => 'public', 'post_min_role' => 'user', 'is_archived' => 0];

        self::assertFalse($policy->isArchived($board));
        self::assertTrue($policy->canPost($board, $this->user('user'), false));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter BoardPolicyArchiveTest`. Fails: `Error: Call to undefined method App\Security\BoardPolicy::isArchived()` (and `canPost` returns `true` for the archived cases once `isArchived` exists).

- [ ] **Step 3: Minimal implementation**
```php
    /**
     * An archived board is retired: read + list stay open, but every write path
     * is closed (PHASE_2_PLAN §7.1, ADMIN §4.4). Reversible via unarchive.
     *
     * @param array<string,mixed> $board
     */
    public function isArchived(array $board): bool
    {
        return (int) ($board['is_archived'] ?? 0) === 1;
    }

    /**
     * Whether $user may create a thread or reply in $board: the board must not be
     * archived, they must be able to read it, AND they must meet the board's
     * minimum posting role. Roles are cumulative (admin ⊇ moderator ⊇ user),
     * matching User::isModerator/isAdmin.
     *
     * @param array<string,mixed> $board
     */
    public function canPost(array $board, User $user, bool $isMember): bool
    {
        if ($this->isArchived($board)) {
            return false;
        }
        if (!$this->canRead($board, $user, $isMember)) {
            return false;
        }
        return match ((string) ($board['post_min_role'] ?? 'user')) {
            'admin' => $user->isAdmin(),
            'moderator' => $user->isModerator(),
            default => true, // 'user' — any reader may post (write gate applies elsewhere)
        };
    }
```
Replace the existing `canPost()` body (lines ~54-64) with the version above; add `isArchived()` directly before it. Leave `canRead()` and `isListed()` untouched.

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit --filter BoardPolicyArchiveTest`

- [ ] **Step 5: Commit** — `git add src/Security/BoardPolicy.php tests/Unit/Security/BoardPolicyArchiveTest.php && git commit -m "B-reorder/archive: BoardPolicy closes posting on archived boards (read/list stay open)"`

---

### Task 2: Repository primitives — dense renumber + archive toggle + board update writes position

**Files:**
- Modify: `src/Repository/CategoryRepository.php` (add `setPositions()`)
- Modify: `src/Repository/BoardRepository.php` (add `setPositions()`, `setArchived()`; `update()` ~90-112 now writes `position` when supplied)
- Test: `tests/Integration/Core/StructureOrderingRepoTest.php` (Create)

**Interfaces:**
- Produces: `App\Repository\CategoryRepository::setPositions(array $orderedIds): void`
- Produces: `App\Repository\BoardRepository::setPositions(int $categoryId, array $orderedIds): void`
- Produces: `App\Repository\BoardRepository::setArchived(int $id, bool $on): void`
- Changed: `App\Repository\BoardRepository::update(int $id, array $data): void` — writes `position` iff `array_key_exists('position', $data)` (backward compatible)
- Consumes: `App\Core\Database::run()` (`src/Core/Database.php:65`), `fetchValue()` (`:86`)

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use Tests\Support\TestCase;

final class StructureOrderingRepoTest extends TestCase
{
    public function test_category_setPositions_densely_renumbers_in_submitted_order(): void
    {
        $c1 = $this->makeCategory('C1');
        $c2 = $this->makeCategory('C2');
        $c3 = $this->makeCategory('C3');

        (new CategoryRepository($this->db))->setPositions([$c3, $c1, $c2]);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c3]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c1]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c2]));
    }

    public function test_board_setPositions_is_scoped_to_a_category(): void
    {
        $cat = $this->makeCategory();
        $b1 = $this->makeBoard($cat, ['slug' => 'sp1']);
        $b2 = $this->makeBoard($cat, ['slug' => 'sp2']);

        (new BoardRepository($this->db))->setPositions($cat, [(int) $b2['id'], (int) $b1['id']]);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b2['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b1['id']]));
    }

    public function test_board_setArchived_toggles_the_flag_without_touching_counters(): void
    {
        $cat = $this->makeCategory();
        $b = $this->makeBoard($cat, ['slug' => 'arc-prim']);
        $repo = new BoardRepository($this->db);

        $repo->setArchived((int) $b['id'], true);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_archived FROM boards WHERE id = ?', [(int) $b['id']]));

        $repo->setArchived((int) $b['id'], false);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_archived FROM boards WHERE id = ?', [(int) $b['id']]));
    }

    public function test_board_update_writes_position_when_supplied(): void
    {
        $cat = $this->makeCategory();
        $b = $this->makeBoard($cat, ['slug' => 'upd-pos', 'name' => 'UpdPos']);

        (new BoardRepository($this->db))->update((int) $b['id'], [
            'category_id' => $cat,
            'slug' => 'upd-pos',
            'name' => 'UpdPos',
            'description' => null,
            'visibility' => 'public',
            'post_min_role' => 'user',
            'position' => 7,
        ]);

        self::assertSame(7, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b['id']]));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter StructureOrderingRepoTest`. Fails: `Error: Call to undefined method App\Repository\CategoryRepository::setPositions()`.

- [ ] **Step 3: Minimal implementation**

In `src/Repository/CategoryRepository.php`, add after `nextPosition()`:
```php
    /**
     * Dense renumber to 0..n-1 in the submitted order. Caller wraps this in a
     * transaction; categories.position has no unique key, so no offset dance is
     * needed. Ids are clamped to int (EMULATE_PREPARES=false).
     *
     * @param array<int,int> $orderedIds
     */
    public function setPositions(array $orderedIds): void
    {
        $pos = 0;
        foreach ($orderedIds as $id) {
            $this->db->run('UPDATE categories SET position = ? WHERE id = ?', [$pos, (int) $id]);
            $pos++;
        }
    }
```

In `src/Repository/BoardRepository.php`, add after `nextPosition()`:
```php
    /**
     * Dense renumber to 0..n-1 within one category, in the submitted order. The
     * category_id guard makes a stray id from another category a no-op rather
     * than a cross-category move.
     *
     * @param array<int,int> $orderedIds
     */
    public function setPositions(int $categoryId, array $orderedIds): void
    {
        $pos = 0;
        foreach ($orderedIds as $id) {
            $this->db->run(
                'UPDATE boards SET position = ? WHERE id = ? AND category_id = ?',
                [$pos, (int) $id, $categoryId],
            );
            $pos++;
        }
    }

    /** Flip the archive (retired/read-only) flag. Counters are untouched. */
    public function setArchived(int $id, bool $on): void
    {
        $this->db->run('UPDATE boards SET is_archived = ? WHERE id = ?', [$on ? 1 : 0, $id]);
    }
```

In `src/Repository/BoardRepository.php`, replace `update()` (lines ~90-112) so it writes `position` only when the caller supplies it (keeps every existing caller working):
```php
    /**
     * @param array{category_id:int,slug:string,name:string,description:?string,visibility:string,post_min_role:string,allow_anonymous?:int,require_approval?:int,assignment_mode?:string,tags_enabled?:int,wiki_enabled?:int,position?:int} $data
     */
    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE boards SET category_id = :category_id, slug = :slug, name = :name, description = :description,
                visibility = :visibility, post_min_role = :post_min_role, allow_anonymous = :allow_anonymous,
                require_approval = :require_approval, assignment_mode = :assignment_mode,
                tags_enabled = :tags_enabled, wiki_enabled = :wiki_enabled';
        $params = [
            'category_id' => $data['category_id'],
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'],
            'visibility' => $data['visibility'],
            'post_min_role' => $data['post_min_role'],
            'allow_anonymous' => !empty($data['allow_anonymous']) ? 1 : 0,
            'require_approval' => !empty($data['require_approval']) ? 1 : 0,
            'assignment_mode' => $data['assignment_mode'] ?? 'off',
            'tags_enabled' => array_key_exists('tags_enabled', $data) ? (!empty($data['tags_enabled']) ? 1 : 0) : 1,
            'wiki_enabled' => !empty($data['wiki_enabled']) ? 1 : 0,
        ];
        if (array_key_exists('position', $data)) {
            $sql .= ', position = :position';
            $params['position'] = (int) $data['position'];
        }
        $sql .= ' WHERE id = :id';
        $params['id'] = $id;
        $this->db->run($sql, $params);
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit --filter StructureOrderingRepoTest` then `vendor/bin/phpunit tests/Integration/Core/AppAdminTest.php` (confirms the backward-compatible `update()` keeps the slug-change path green).

- [ ] **Step 5: Commit** — `git add src/Repository/CategoryRepository.php src/Repository/BoardRepository.php tests/Integration/Core/StructureOrderingRepoTest.php && git commit -m "B-reorder/archive: repo primitives — dense renumber, archive toggle, board update writes position"`

---

### Task 3: `AdminService` — reorder / move / archive methods (audited) + stale-position fix

**Files:**
- Modify: `src/Service/AdminService.php` (add `reorderCategories`, `reorderBoards`, `moveCategory`, `moveBoard`, `archiveBoard`, `unarchiveBoard`, private `swap`, private `normalizeIdSet`; `updateBoard` ~229-267 computes `position` on category change)
- Test: `tests/Integration/Core/AppAdminStructureReorderTest.php` (Create)

**Interfaces:**
- Produces: `AdminService::reorderCategories(App\Domain\User $admin, array $orderedIds): void`
- Produces: `AdminService::reorderBoards(App\Domain\User $admin, int $categoryId, array $orderedIds): void`
- Produces: `AdminService::moveCategory(App\Domain\User $admin, int $id, string $dir): void`
- Produces: `AdminService::moveBoard(App\Domain\User $admin, int $id, string $dir): void`
- Produces: `AdminService::archiveBoard(App\Domain\User $admin, int $id): void`
- Produces: `AdminService::unarchiveBoard(App\Domain\User $admin, int $id): void`
- Consumes: `CategoryRepository::all()/find()/setPositions()`, `BoardRepository::byCategory()/find()/nextPosition()/setPositions()/setArchived()/update()`, `ModerationLogRepository::log()`, `Database::transaction()`, `assertAdmin()` (`src/Service/AdminService.php:520`)

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\AdminService;
use Tests\Support\TestCase;

final class AppAdminStructureReorderTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $user;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->user = $this->makeUser(['username' => 'plain']);
        $this->categoryId = $this->makeCategory('General');
    }

    private function adminService(): AdminService
    {
        return new AdminService(
            $this->db,
            new CategoryRepository($this->db),
            new BoardRepository($this->db),
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            new UserRepository($this->db),
            new BoardModeratorRepository($this->db),
            new BoardMemberRepository($this->db),
        );
    }

    public function test_reorder_boards_densely_renumbers_and_audits(): void
    {
        $admin = $this->userEntity($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'r1', 'name' => 'R1']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'r2', 'name' => 'R2']);
        $b3 = $this->makeBoard($this->categoryId, ['slug' => 'r3', 'name' => 'R3']);

        $this->adminService()->reorderBoards($admin, $this->categoryId, [(int) $b3['id'], (int) $b1['id'], (int) $b2['id']]);

        self::assertSame(0, (int) $this->boards()->find((int) $b3['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(2, (int) $this->boards()->find((int) $b2['id'])['position']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_boards' AND target_type = 'board'",
        ));
    }

    public function test_reorder_with_foreign_id_is_rejected_and_order_unchanged(): void
    {
        $admin = $this->userEntity($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'f1', 'name' => 'F1']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'f2', 'name' => 'F2']);

        $threw = false;
        try {
            $this->adminService()->reorderBoards($admin, $this->categoryId, [(int) $b2['id'], 999999]);
        } catch (ValidationException) {
            $threw = true;
        }
        self::assertTrue($threw, 'a foreign id must be rejected');
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_move_top_category_up_is_a_safe_noop(): void
    {
        $admin = $this->userEntity($this->admin);
        $catB = $this->makeCategory('Second');

        $this->adminService()->moveCategory($admin, $this->categoryId, 'up'); // already at top

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$this->categoryId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$catB]));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_categories'"));
    }

    public function test_updateBoard_appends_at_destination_when_category_changes(): void
    {
        $admin = $this->userEntity($this->admin);
        $catB = $this->makeCategory('Dest');
        $existing = $this->makeBoard($catB, ['slug' => 'dx', 'name' => 'DestExisting']); // pos 0 in catB
        $mover = $this->makeBoard($this->categoryId, ['slug' => 'mv', 'name' => 'Mover']); // pos 0 in catA

        $this->adminService()->updateBoard($admin, (int) $mover['id'], [
            'category_id' => $catB,
            'name' => 'Mover',
            'slug' => 'mv',
            'visibility' => 'public',
        ]);

        $row = $this->boards()->find((int) $mover['id']);
        self::assertSame($catB, (int) $row['category_id']);
        self::assertSame(1, (int) $row['position'], 'appended after the existing board (no collision)');
        self::assertSame(0, (int) $this->boards()->find((int) $existing['id'])['position']);
    }

    public function test_archive_and_unarchive_toggle_flag_and_audit(): void
    {
        $admin = $this->userEntity($this->admin);
        $b = $this->makeBoard($this->categoryId, ['slug' => 'svc-arc', 'name' => 'SvcArc']);

        $this->adminService()->archiveBoard($admin, (int) $b['id']);
        self::assertSame(1, (int) $this->boards()->find((int) $b['id'])['is_archived']);

        $this->adminService()->unarchiveBoard($admin, (int) $b['id']);
        self::assertSame(0, (int) $this->boards()->find((int) $b['id'])['is_archived']);

        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'archive_board' AND target_type = 'board'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unarchive_board' AND target_type = 'board'"));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Core/AppAdminStructureReorderTest.php`. Fails: `Error: Call to undefined method App\Service\AdminService::reorderBoards()`.

- [ ] **Step 3: Minimal implementation**

In `src/Service/AdminService.php`, insert the structure-ordering + archive block right after `deleteBoard()` (before the `// ---- Board roster` section, ~line 291):
```php
    // ---- Structure ordering + archive (Phase 2) ---------------------------

    /**
     * Replace category ordering with the submitted permutation. The submitted
     * id-set must EQUAL the current set (no adds, drops, foreign, or duplicate
     * ids), then we dense-renumber 0..n-1 inside one transaction and audit.
     *
     * @param array<int,mixed> $orderedIds
     */
    public function reorderCategories(User $admin, array $orderedIds): void
    {
        $this->assertAdmin($admin);
        $current = array_map(static fn (array $c): int => (int) $c['id'], $this->categories->all());
        $ordered = $this->normalizeIdSet($orderedIds, $current, 'order');

        $this->db->transaction(function () use ($admin, $ordered, $current): void {
            $this->categories->setPositions($ordered);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'reorder_categories',
                'target_type' => 'category',
                'target_id' => 0,
                'before' => ['order' => $current],
                'after' => ['order' => $ordered],
            ]);
        });
    }

    /**
     * Replace one category's board ordering with the submitted permutation
     * (same id-set rule as reorderCategories), scoped + audited.
     *
     * @param array<int,mixed> $orderedIds
     */
    public function reorderBoards(User $admin, int $categoryId, array $orderedIds): void
    {
        $this->assertAdmin($admin);
        if ($this->categories->find($categoryId) === null) {
            throw new NotFoundException('Category not found.');
        }
        $current = array_map(static fn (array $b): int => (int) $b['id'], $this->boards->byCategory($categoryId));
        $ordered = $this->normalizeIdSet($orderedIds, $current, 'order');

        $this->db->transaction(function () use ($admin, $categoryId, $ordered, $current): void {
            $this->boards->setPositions($categoryId, $ordered);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'reorder_boards',
                'target_type' => 'board',
                'target_id' => 0,
                'before' => ['category_id' => $categoryId, 'order' => $current],
                'after' => ['category_id' => $categoryId, 'order' => $ordered],
            ]);
        });
    }

    /** Nudge a category up/down by one slot (funnels through reorderCategories). */
    public function moveCategory(User $admin, int $id, string $dir): void
    {
        $this->assertAdmin($admin);
        $ids = array_map(static fn (array $c): int => (int) $c['id'], $this->categories->all());
        $reordered = $this->swap($ids, $id, $dir);
        if ($reordered === null) {
            return; // boundary (top-up / bottom-down) or unknown id: safe no-op
        }
        $this->reorderCategories($admin, $reordered);
    }

    /** Nudge a board up/down by one slot within its category. */
    public function moveBoard(User $admin, int $id, string $dir): void
    {
        $this->assertAdmin($admin);
        $board = $this->boards->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $categoryId = (int) $board['category_id'];
        $ids = array_map(static fn (array $b): int => (int) $b['id'], $this->boards->byCategory($categoryId));
        $reordered = $this->swap($ids, $id, $dir);
        if ($reordered === null) {
            return;
        }
        $this->reorderBoards($admin, $categoryId, $reordered);
    }

    /** Retire a board: it becomes absolute read-only (reversible). */
    public function archiveBoard(User $admin, int $id): void
    {
        $this->setArchivedState($admin, $id, true);
    }

    /** Restore a retired board: writes re-enabled. */
    public function unarchiveBoard(User $admin, int $id): void
    {
        $this->setArchivedState($admin, $id, false);
    }

    private function setArchivedState(User $admin, int $id, bool $on): void
    {
        $this->assertAdmin($admin);
        $board = $this->boards->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $this->db->transaction(function () use ($admin, $id, $on, $board): void {
            $this->boards->setArchived($id, $on);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $on ? 'archive_board' : 'unarchive_board',
                'target_type' => 'board',
                'target_id' => $id,
                'before' => ['is_archived' => (int) ($board['is_archived'] ?? 0)],
                'after' => ['is_archived' => $on ? 1 : 0],
            ]);
        });
    }

    /**
     * Swap $id with its up/down neighbour. Returns the new full ordering, or null
     * when the move is a boundary no-op or the id is not present.
     *
     * @param array<int,int> $ids
     * @return array<int,int>|null
     */
    private function swap(array $ids, int $id, string $dir): ?array
    {
        $idx = array_search($id, $ids, true);
        if ($idx === false) {
            return null;
        }
        $target = $dir === 'up' ? $idx - 1 : $idx + 1;
        if ($target < 0 || $target >= count($ids)) {
            return null;
        }
        [$ids[$idx], $ids[$target]] = [$ids[$target], $ids[$idx]];
        return $ids;
    }

    /**
     * Validate that $submitted is a permutation of $current (no adds, drops,
     * foreign ids, or duplicates) and return it as a clean int list.
     *
     * @param array<int,mixed> $submitted
     * @param array<int,int> $current
     * @return array<int,int>
     */
    private function normalizeIdSet(array $submitted, array $current, string $field): array
    {
        $ids = array_values(array_map('intval', $submitted));
        $sortedSubmitted = $ids;
        sort($sortedSubmitted);
        $sortedCurrent = $current;
        sort($sortedCurrent);
        if ($sortedSubmitted !== $sortedCurrent || count($ids) !== count(array_unique($ids))) {
            throw new ValidationException([$field => 'The submitted order must contain exactly the existing items.']);
        }
        return $ids;
    }
```

Then fix the stale-position bug in `updateBoard()` (~line 236-266). After the `$oldSlug`/`$slugChanged` lines, compute the destination position, and add `'position' => $position` to the `boards->update([...])` array. Replace from the `$oldSlug` line through the transaction:
```php
        $oldSlug = (string) $board['slug'];
        $slugChanged = $slug !== $oldSlug;

        // Reassigning a board to a different category must append it at the
        // destination, not carry its old (now-colliding) position over.
        $position = (int) $board['position'];
        if ($categoryId !== (int) $board['category_id']) {
            $position = $this->boards->nextPosition($categoryId);
        }

        $this->db->transaction(function () use ($admin, $id, $categoryId, $name, $slug, $description, $visibility, $role, $allowAnon, $requireApproval, $assignmentMode, $tagsEnabled, $wikiEnabled, $oldSlug, $slugChanged, $position, $board): void {
            if ($slugChanged) {
                $this->boards->recordSlugChange($id, $oldSlug);
            }
            $this->boards->update($id, [
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility,
                'post_min_role' => $role,
                'allow_anonymous' => $allowAnon,
                'require_approval' => $requireApproval,
                'assignment_mode' => $assignmentMode,
                'tags_enabled' => $tagsEnabled,
                'wiki_enabled' => $wikiEnabled,
                'position' => $position,
            ]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'update_board',
                'target_type' => 'board',
                'target_id' => $id,
                'before' => ['name' => $board['name'], 'slug' => $oldSlug, 'visibility' => $board['visibility'], 'allow_anonymous' => (int) ($board['allow_anonymous'] ?? 0), 'require_approval' => (int) ($board['require_approval'] ?? 0), 'assignment_mode' => $board['assignment_mode'] ?? 'off', 'tags_enabled' => (int) ($board['tags_enabled'] ?? 1), 'wiki_enabled' => (int) ($board['wiki_enabled'] ?? 0)],
                'after' => ['name' => $name, 'slug' => $slug, 'visibility' => $visibility, 'allow_anonymous' => $allowAnon, 'require_approval' => $requireApproval, 'assignment_mode' => $assignmentMode, 'tags_enabled' => $tagsEnabled, 'wiki_enabled' => $wikiEnabled],
            ]);
        });
```
(`User`, `NotFoundException`, `ValidationException` are already imported at the top of `AdminService.php`.)

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAdminStructureReorderTest.php tests/Integration/Core/AppAdminTest.php`

- [ ] **Step 5: Commit** — `git add src/Service/AdminService.php tests/Integration/Core/AppAdminStructureReorderTest.php && git commit -m "B-reorder/archive: AdminService reorder/move/archive (audited) + category-change position append"`

---

### Task 4: `AdminController` actions + routes + `/admin/structure` buttons

**Files:**
- Modify: `src/Controller/AdminController.php` (add `moveCategory`, `moveBoard`, `reorder`, `archiveBoard`, `unarchiveBoard`)
- Modify: `src/Core/App.php` (register 5 routes after `/admin/boards/{id}/delete` ~line 1093)
- Modify: `templates/admin/structure.php` (per-category + per-board move/archive buttons, stable `data-*` containers, optional `reorder_error` banner)
- Test: `tests/Integration/Core/AppAdminStructureReorderTest.php` (append HTTP cases)

**Interfaces:**
- Produces: `AdminController::moveCategory/moveBoard/reorder/archiveBoard/unarchiveBoard(Request, array): Response`
- Consumes: `Controller::requireAdmin()` (`src/Controller/Controller.php:82`), `Controller::run()` (`AdminController.php:236`), `Request::post()` (`src/Core/Request.php:117`), `AdminService` methods (Task 3), `CategoryRepository::all()`, `AdminController::boardsByCategory()` (`:247`)
- Routes (contract): `POST /admin/categories/{id}/move`, `POST /admin/boards/{id}/move`, `POST /admin/structure/reorder`, `POST /admin/boards/{id}/archive`, `POST /admin/boards/{id}/unarchive`

Steps:

- [ ] **Step 1: Write the failing test** (append these methods to `AppAdminStructureReorderTest`)
```php
    public function test_move_board_up_swaps_rendered_order_and_audits(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'alpha', 'name' => 'AlphaBoard']); // pos 0
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'beta', 'name' => 'BetaBoard']);   // pos 1
        $this->get('/admin/structure');

        $res = $this->post('/admin/boards/' . $b2['id'] . '/move', ['dir' => 'up']);
        $this->assertRedirectContains($res, '/admin/structure');

        self::assertSame(0, (int) $this->boards()->find((int) $b2['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b1['id'])['position']);

        $body = $this->get('/admin/structure')->body();
        self::assertLessThan(strpos($body, 'AlphaBoard'), strpos($body, 'BetaBoard'), 'Beta now renders before Alpha');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_boards'"));
    }

    public function test_move_top_board_up_is_safe_noop_over_http(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'top', 'name' => 'TopBoard']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'bot', 'name' => 'BotBoard']);
        $this->get('/admin/structure');

        $res = $this->post('/admin/boards/' . $b1['id'] . '/move', ['dir' => 'up']);
        $this->assertRedirectContains($res, '/admin/structure');
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_bulk_reorder_with_foreign_id_is_422_and_order_unchanged(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'one', 'name' => 'OneBoard']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'two', 'name' => 'TwoBoard']);
        $this->get('/admin/structure');

        $res = $this->post('/admin/structure/reorder', [
            'scope' => 'board',
            'category_id' => $this->categoryId,
            'ids' => [(int) $b2['id'], 999999],
        ]);
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_structure_mutations_require_admin(): void
    {
        $b = $this->makeBoard($this->categoryId, ['slug' => 'guard', 'name' => 'GuardBoard']);

        $this->actingAs($this->user);
        $this->get('/');
        $this->assertStatus(403, $this->post('/admin/boards/' . $b['id'] . '/move', ['dir' => 'up']));
        $this->assertStatus(403, $this->post('/admin/boards/' . $b['id'] . '/archive'));

        $this->logoutClient();
        $this->get('/');
        $this->assertRedirectContains(
            $this->post('/admin/structure/reorder', ['scope' => 'category', 'ids' => [(int) $this->categoryId]]),
            '/login',
        );
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter "test_move_board_up_swaps_rendered_order_and_audits|test_bulk_reorder_with_foreign_id_is_422_and_order_unchanged"`. Fails: 404 (routes/actions don't exist yet).

- [ ] **Step 3: Minimal implementation**

In `src/Controller/AdminController.php`, add after `deleteBoard()` (~line 209):
```php
    // ---- Structure ordering + archive (Phase 2) ---------------------------

    /** @param array<string,string> $params */
    public function moveCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->moveCategory($admin, $id, (string) $request->post('dir', '')),
            '/admin/structure',
            'Order updated.',
        );
    }

    /** @param array<string,string> $params */
    public function moveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->moveBoard($admin, $id, (string) $request->post('dir', '')),
            '/admin/structure',
            'Order updated.',
        );
    }

    /**
     * Bulk reorder target for the optional JS drag enhancement. On a bad id-set
     * it re-renders the structure page at 422 (no redirect) so the AJAX caller
     * sees the failure and the no-JS up/down buttons stay the working path.
     *
     * @param array<string,string> $params
     */
    public function reorder(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $scope = (string) $request->post('scope', '');
        $rawIds = $request->post('ids', []);
        $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];

        try {
            if ($scope === 'category') {
                $this->container->get(AdminService::class)->reorderCategories($admin, $ids);
            } else {
                $this->container->get(AdminService::class)->reorderBoards($admin, (int) $request->post('category_id', 0), $ids);
            }
        } catch (ValidationException $e) {
            return $this->view('admin/structure', [
                'categories' => $this->container->get(CategoryRepository::class)->all(),
                'boards_by_category' => $this->boardsByCategory(),
                'reorder_error' => $e->first(),
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Order updated.');
    }

    /** @param array<string,string> $params */
    public function archiveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->archiveBoard($admin, $id),
            '/admin/structure',
            'Board archived — it is now read-only.',
        );
    }

    /** @param array<string,string> $params */
    public function unarchiveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->unarchiveBoard($admin, $id),
            '/admin/structure',
            'Board restored — posting re-enabled.',
        );
    }
```
(`Request`, `Response`, `ValidationException`, `CategoryRepository`, `AdminService` are already imported in `AdminController.php`.)

In `src/Core/App.php`, register the routes immediately after `$r->post('/admin/boards/{id}/delete', ...)` (~line 1093):
```php
        // Structure ordering + archive (Phase 2). Static reorder before any
        // generic /admin/structure/{...}; {id} compiles to \d+ so the /move,
        // /archive, /unarchive suffixes never collide with /admin/boards/{id}.
        $r->post('/admin/categories/{id}/move', [AdminController::class, 'moveCategory']);
        $r->post('/admin/boards/{id}/move', [AdminController::class, 'moveBoard']);
        $r->post('/admin/structure/reorder', [AdminController::class, 'reorder']);
        $r->post('/admin/boards/{id}/archive', [AdminController::class, 'archiveBoard']);
        $r->post('/admin/boards/{id}/unarchive', [AdminController::class, 'unarchiveBoard']);
```

Replace the whole of `templates/admin/structure.php` with:
```php
<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Boards & categories'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Boards &amp; categories</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/structure">Boards &amp; categories</a>
    </nav>

    <?php if (!empty($reorder_error ?? null)): ?>
        <div class="flash flash-error"><?= $e($reorder_error) ?></div>
    <?php endif; ?>

    <div class="admin-structure" data-reorder-categories>
        <?php foreach ($categories as $category): ?>
            <section class="card admin-cat" data-category-id="<?= (int) $category['id'] ?>">
                <div class="admin-cat-head">
                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>" class="inline-form">
                        <?= $this->csrfField() ?>
                        <input type="text" name="name" class="input" value="<?= $e($category['name']) ?>" maxlength="64" required>
                        <button class="btn btn-small" type="submit">Save</button>
                    </form>
                    <span class="admin-cat-actions">
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="up">
                            <button class="linkbtn" type="submit" aria-label="Move category <?= $e($category['name']) ?> up">↑</button>
                        </form>
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/move" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="dir" value="down">
                            <button class="linkbtn" type="submit" aria-label="Move category <?= $e($category['name']) ?> down">↓</button>
                        </form>
                        <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" class="inline">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn danger" type="submit">Delete category</button>
                        </form>
                    </span>
                </div>

                <ul class="admin-board-list" data-reorder-boards data-category-id="<?= (int) $category['id'] ?>">
                    <?php foreach (($boards_by_category[(int) $category['id']] ?? []) as $board): ?>
                        <li class="admin-board-row" data-board-id="<?= (int) $board['id'] ?>">
                            <span><span class="hash">#</span><?= $e($board['name']) ?>
                                <span class="muted">/c/<?= $e($board['slug']) ?></span>
                                <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                                <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?><span class="tag tag-archived">Archived</span><?php endif; ?>
                                <span class="muted">· <?= (int) $board['thread_count'] ?> threads</span>
                            </span>
                            <span class="admin-board-actions">
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="up">
                                    <button class="linkbtn" type="submit" aria-label="Move <?= $e($board['name']) ?> up">↑</button>
                                </form>
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/move" class="inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="dir" value="down">
                                    <button class="linkbtn" type="submit" aria-label="Move <?= $e($board['name']) ?> down">↓</button>
                                </form>
                                <a class="linkbtn" href="/admin/boards/<?= (int) $board['id'] ?>/edit">Edit</a>
                                <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?>
                                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/unarchive" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn" type="submit">Unarchive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/archive" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn" type="submit">Archive</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/delete" class="inline">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn danger" type="submit">Delete</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="card">
        <h2>Add a category</h2>
        <form method="post" action="/admin/categories" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="name" class="input" placeholder="Category name" maxlength="64" required>
            <button class="btn btn-small" type="submit">Add category</button>
        </form>
    </section>

    <?php if (!empty($categories)): ?>
        <section class="card">
            <h2>Add a board</h2>
            <form method="post" action="/admin/boards" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field"><span>Category</span>
                    <select name="category_id" class="input">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">#<?= $e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Name</span><input type="text" name="name" class="input" maxlength="80" required></label>
                <label class="field"><span>Slug <span class="muted">(optional — derived from name)</span></span><input type="text" name="slug" class="input" maxlength="64"></label>
                <label class="field"><span>Description</span><input type="text" name="description" class="input" maxlength="255"></label>
                <label class="field"><span>Visibility</span>
                    <select name="visibility" class="input">
                        <option value="public">Public</option>
                        <option value="hidden">Hidden (unlisted)</option>
                        <option value="private">Private (admins only)</option>
                    </select>
                </label>
                <label class="field"><span>Assignment mode</span>
                    <select name="assignment_mode" class="input">
                        <option value="off">Off</option>
                        <option value="self">Members can assign themselves</option>
                        <option value="staff">Staff can assign members</option>
                    </select>
                </label>
                <label class="checkline"><input type="checkbox" name="allow_anonymous" value="1"> Allow anonymous posting</label>
                <label class="checkline"><input type="checkbox" name="tags_enabled" value="1" checked> Allow approved tags</label>
                <label class="checkline"><input type="checkbox" name="wiki_enabled" value="1"> Allow wiki-style post editing</label>
                <button class="btn btn-small" type="submit">Add board</button>
            </form>
        </section>
    <?php endif; ?>
</div>
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAdminStructureReorderTest.php tests/Integration/Core/AppAdminTest.php`

- [ ] **Step 5: Commit** — `git add src/Controller/AdminController.php src/Core/App.php templates/admin/structure.php tests/Integration/Core/AppAdminStructureReorderTest.php && git commit -m "B-reorder/archive: admin move/reorder/archive routes + structure page buttons"`

---

### Task 5: Absolute read-only on the posting paths (create / reply / edit / delete)

**Files:**
- Modify: `src/Repository/ThreadRepository.php` (`findWithBoard()` ~69-79 selects `b.is_archived AS board_is_archived`)
- Modify: `src/Service/PostingService.php` (reply ~199-202 synthetic board carries `is_archived`; `editOwnPost` ~301 + `deleteOwnPost` ~359 add an archived guard after the ownership check)
- Test: `tests/Integration/Core/AppAdminArchiveTest.php` (Create)

**Interfaces:**
- Consumes: `BoardPolicy::isArchived()` (Task 1), `BoardRepository::find()` (`src/Repository/BoardRepository.php:33`), `App\Core\ForbiddenException` (already imported in `PostingService`)
- Changed: `ThreadRepository::findWithBoard(int): ?array` now includes `board_is_archived`
- Behaviour: `PostingService::createThread`/`reply` reject in an archived board via `canPost` → `ForbiddenException` (→ 403); `editOwnPost`/`deleteOwnPost` reject via explicit guard

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

final class AppAdminArchiveTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->categoryId = $this->makeCategory('General');
    }

    public function test_member_cannot_create_thread_or_reply_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcboard', 'name' => 'ArcBoard']);
        $thread = $this->makeThread($board, $member, 'Pre-archive topic'); // seeded BEFORE archive
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $create = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'New topic after archive',
            'body' => 'Should be rejected.',
        ]);
        $this->assertStatus(403, $create);

        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Should be rejected too.']);
        $this->assertStatus(403, $reply);
    }

    public function test_owner_cannot_edit_or_delete_a_post_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcowner']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcedit', 'name' => 'ArcEdit']);
        $thread = $this->makeThread($board, $member, 'Editable topic', 'Original body here.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/edit', ['body' => 'Trying to edit after archive.']));
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/delete'));
    }

    public function test_admin_and_board_moderator_are_also_blocked_from_writing(): void
    {
        $author = $this->makeUser(['username' => 'arcauth']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcmod', 'name' => 'ArcMod']);
        $thread = $this->makeThread($board, $author, 'Topic in a soon-archived board');
        $mod = $this->makeUser(['username' => 'arcmoduser']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'admin reply blocked']));

        $this->logoutClient();
        $this->actingAs($mod);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'mod reply blocked']));
    }

    public function test_unarchive_restores_writability(): void
    {
        $member = $this->makeUser(['username' => 'rearcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'rearc', 'name' => 'ReArc']);
        $thread = $this->makeThread($board, $member, 'Reopenable topic');

        // Archive then unarchive through the admin service path (not raw SQL).
        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $this->post('/admin/boards/' . $board['id'] . '/archive');
        $this->post('/admin/boards/' . $board['id'] . '/unarchive');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unarchive_board'"));

        $this->logoutClient();
        $this->actingAs($member);
        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Posting works again.']);
        $this->assertRedirectContains($reply, '/t/' . $thread['thread_id']);
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Core/AppAdminArchiveTest.php`. `createThread` already 403s (Task 1 made `canPost` archive-aware via `BoardRepository::find`), but `reply`/`edit`/`delete` still succeed (200/redirect) because the reply synthetic board lacks `is_archived` and edit/delete never consult `canPost` — so the moderator/admin/edit/delete cases fail.

- [ ] **Step 3: Minimal implementation**

In `src/Repository/ThreadRepository.php`, add the archive flag to `findWithBoard()`'s SELECT (~line 72) — insert after `b.wiki_enabled AS board_wiki_enabled,`:
```php
                    b.is_archived AS board_is_archived,
```

In `src/Service/PostingService.php`, make the reply's synthetic board carry the flag (replace lines ~199-202):
```php
        $board = [
            'visibility' => $thread['board_visibility'],
            'post_min_role' => $thread['board_post_min_role'] ?? 'user',
            'is_archived' => (int) ($thread['board_is_archived'] ?? 0),
        ];
```

In `src/Service/PostingService.php::editOwnPost()`, add the archived guard right after the ownership check (after line ~303 `throw new ForbiddenException('You can only edit your own posts.');` block closes):
```php
        $editBoard = $this->boards->find((int) $post['board_id']);
        if ($editBoard !== null && $this->policy->isArchived($editBoard)) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
```

In `src/Service/PostingService.php::deleteOwnPost()`, add the same guard right after its ownership check (after line ~361):
```php
        $deleteBoard = $this->boards->find((int) $post['board_id']);
        if ($deleteBoard !== null && $this->policy->isArchived($deleteBoard)) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
```
(`$this->policy` is the injected `BoardPolicy`; `$this->boards` is `BoardRepository`; `ForbiddenException` is already imported in `PostingService`.)

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAdminArchiveTest.php tests/Integration/Core/AppSeoVisibilityTest.php`

- [ ] **Step 5: Commit** — `git add src/Repository/ThreadRepository.php src/Service/PostingService.php tests/Integration/Core/AppAdminArchiveTest.php && git commit -m "B-reorder/archive: absolute read-only on create/reply/edit/delete for archived boards"`

---

### Task 6: Read-only on the workflow + wiki paths (status change, wiki edit)

**Files:**
- Modify: `src/Service/ThreadWorkflowService.php` (`setStatus()` ~49 archived guard after `threadOrFail`)
- Modify: `src/Service/CommunityMemoryService.php` (`assertWikiEnabled()` ~292 archived guard before the wiki-enabled check — covers `makeWiki`/`editWiki`/`revertWiki`)
- Test: `tests/Integration/Core/AppAdminArchiveTest.php` (append service-level cases)

**Interfaces:**
- Consumes: `ThreadRepository::findWithBoard()` (now exposes `board_is_archived` after Task 5), `App\Core\ForbiddenException` (already imported in both services)
- Behaviour: `ThreadWorkflowService::setStatus` + `CommunityMemoryService::{makeWiki,editWiki,revertWiki}` throw `ForbiddenException` on an archived board, for every role

Steps:

- [ ] **Step 1: Write the failing test** (append to `AppAdminArchiveTest`; add the `use` imports listed in the methods)
```php
    public function test_thread_status_change_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wfauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wfboard', 'name' => 'WFBoard']);
        $thread = $this->makeThread($board, $author, 'Workflow topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $svc = new \App\Service\ThreadWorkflowService(
            $this->db,
            new \App\Repository\ThreadRepository($this->db),
            new \App\Repository\ThreadAssignmentRepository($this->db),
            new \App\Repository\UserRepository($this->db),
            new \App\Repository\BoardModeratorRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
            new \App\Security\WriteGate(),
        );

        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->setStatus($this->userEntity($author), $thread['thread_id'], 'solved');
    }

    public function test_wiki_make_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wikiauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wikiboard', 'name' => 'WikiBoard']);
        $thread = $this->makeThread($board, $author, 'Wiki candidate');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $svc = new \App\Service\CommunityMemoryService(
            $this->db,
            new \App\Repository\ThreadRepository($this->db),
            new \App\Repository\PostRepository($this->db),
            new \App\Repository\BoardModeratorRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardPolicy(),
            new \App\Security\WriteGate(),
            new \App\Support\Markdown(new \App\Support\HtmlSanitizer()),
        );

        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->makeWiki($this->userEntity($this->admin), $opId);
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter "test_thread_status_change_is_blocked_on_archived_board|test_wiki_make_is_blocked_on_archived_board"`. Fails: no exception thrown (status change + wiki currently ignore `is_archived`).

- [ ] **Step 3: Minimal implementation**

In `src/Service/ThreadWorkflowService.php::setStatus()`, add the guard right after `$thread = $this->threadOrFail($threadId);` (line ~49):
```php
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
```

In `src/Service/CommunityMemoryService.php::assertWikiEnabled()`, add the guard right after the `is_deleted` check and before the `board_wiki_enabled` check (~line 298):
```php
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
```
(`ForbiddenException` is already imported in both services; `assertWikiEnabled` is called by `makeWiki`, `editWiki`, and `revertWiki`, so all three wiki mutations are covered.)

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAdminArchiveTest.php`

- [ ] **Step 5: Commit** — `git add src/Service/ThreadWorkflowService.php src/Service/CommunityMemoryService.php tests/Integration/Core/AppAdminArchiveTest.php && git commit -m "B-reorder/archive: read-only on thread status + wiki edit for archived boards"`

---

### Task 7: Board page renders the retired/read-only banner and suppresses affordances

**Files:**
- Modify: `templates/board.php` (~28-35 — archived banner branch precedes the New-Topic + guest-joinbar branches)
- Test: `tests/Integration/Core/AppAdminArchiveTest.php` (append)

**Interfaces:**
- Consumes: `$board['is_archived']` (already in the view — `BoardController::show` passes the full `$board` row from `BoardRepository::findBySlug`, `SELECT *`), `$can_post` (auto-false via `canPost`, `src/Controller/BoardController.php:76-78`), `$current_user` (shared view global)

Steps:

- [ ] **Step 1: Write the failing test** (append to `AppAdminArchiveTest`)
```php
    public function test_archived_board_page_is_readable_with_banner_and_no_new_topic(): void
    {
        $author = $this->makeUser(['username' => 'arcreader']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcread', 'name' => 'ArcRead']);
        $this->makeThread($board, $author, 'Still readable topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($author);
        $res = $this->get('/c/arcread');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Still readable topic');   // content preserved + readable
        $this->assertSeeText($res, 'retired and read-only');  // banner copy
        $this->assertDontSeeText($res, 'New Topic');          // affordance suppressed
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter test_archived_board_page_is_readable_with_banner_and_no_new_topic`. Fails: banner text absent (`assertSeeText` for "retired and read-only").

- [ ] **Step 3: Minimal implementation**

In `templates/board.php`, replace the New-Topic / guest block (lines ~28-35) so the archived banner wins first:
```php
    <?php if ((int) ($board['is_archived'] ?? 0) === 1): ?>
        <div class="joinbar joinbar-archived" data-archived-banner>This board is retired and read-only. You can still read and search its topics, but new topics and replies are closed.</div>
    <?php elseif ($can_post): ?>
        <details class="composer-details">
            <summary class="btn">New Topic</summary>
            <?= $this->partial('partials/new_thread_form', ['board' => $board, 'errors' => [], 'old' => []]) ?>
        </details>
    <?php elseif ($current_user === null): ?>
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/c/<?= $e($board['slug']) ?>">log in</a> to start a topic.</div>
    <?php endif; ?>
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAdminArchiveTest.php`

- [ ] **Step 5: Commit** — `git add templates/board.php tests/Integration/Core/AppAdminArchiveTest.php && git commit -m "B-reorder/archive: board page shows retired read-only banner, hides New Topic"`

---

### Task 8: Archived boards stay searchable (and SEO stays correct)

**Files:**
- Modify: `tests/Integration/Core/AppSearchTest.php` (append a committed-fixtures case — no source change to `src/Search/MysqlSearchService.php`, which intentionally does NOT filter `is_archived`)
- Test: same file

**Interfaces:**
- Consumes: `MysqlSearchService::search(string, ?User, int): array` (`src/Search/MysqlSearchService.php:26`), `BoardRepository::setArchived()` (Task 2)
- Asserts: an archived board's content still appears in search; `src/Search/MysqlSearchService.php` and `src/Controller/SeoController.php` are unchanged (search keeps archived content; sitemap keeps excluding it)

Steps:

- [ ] **Step 1: Write the failing test** (append to `AppSearchTest`; it uses the file's committed-fixtures `setUp`/`service()`)
```php
    public function testArchivedBoardContentStaysSearchable(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $author, 'Stegosaurus retrospective', 'Public thread before archive.');
        $this->boards()->setArchived((int) $board['id'], true); // archive AFTER seeding

        $results = $this->service()->search('Stegosaurus', null, 20);
        self::assertContains(
            'Stegosaurus retrospective',
            array_column($results, 'title'),
            'archived boards remain searchable — read-only is not hidden',
        );
    }
```

- [ ] **Step 2: Run it, expect FAIL-then-confirm** — `vendor/bin/phpunit --filter testArchivedBoardContentStaysSearchable`. This test should pass immediately because `MysqlSearchService` has no archive filter. Run it RED-first by temporarily asserting the opposite (`assertNotContains`) to prove the harness exercises the path, then restore to `assertContains` and confirm green. (This is the contract's "archived must stay searchable — KEEP" guard, locked by a regression test.)

- [ ] **Step 3: Minimal implementation** — None in `src/`. The guarantee is the *absence* of an `is_archived` filter in `MysqlSearchService::search()`; this task pins it with a test. Confirm `git diff --stat src/` shows no search/SEO source change.

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppSearchTest.php tests/Integration/Core/AppSeoVisibilityTest.php`

- [ ] **Step 5: Commit** — `git add tests/Integration/Core/AppSearchTest.php && git commit -m "B-reorder/archive: regression test — archived boards stay searchable"`

---

### Task 9: Optional JS drag enhancement + Playwright evidence

**Files:**
- Modify (optional/stretch): `public/assets/app.js` (drag/keyboard reorder decoration over the working up/down forms, posting to `/admin/structure/reorder` via `data-*` hooks; no inline handlers)
- Modify: `tests/browser/gate-a.spec.ts` (admin reorder + archive journey; desktop + mobile PNGs via `shot()`)

**Interfaces:**
- Consumes: server-rendered `data-reorder-categories` / `data-reorder-boards[data-category-id]` / `[data-board-id]` hooks (Task 4) and the per-row hidden `_token` field; POSTs `{scope, category_id, ids[]}` to `/admin/structure/reorder`. The up/down buttons remain the no-JS path — JS is pure enhancement and never re-enables a write on an archived board.
- Browser seed: `admin@retro.test` / `password123`; boards `#general`, `#feedback`, `#announcements` (`tests/browser/seed.php`).

Steps:

- [ ] **Step 1: Write the failing test** (append to `tests/browser/gate-a.spec.ts`, reusing its `visit`/`login`/`shot` helpers)
```ts
test('admin can reorder and archive boards', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/structure');
  await shot(page, info, '20-structure-before');

  // Move #feedback up one slot via the server-rendered button (no-JS path).
  const feedbackRow = page.locator('li.admin-board-row[data-board-id]', { hasText: 'Feedback' });
  await feedbackRow.getByRole('button', { name: /Move Feedback up/i }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);
  await shot(page, info, '21-structure-after-move');

  // Archive #feedback, then confirm the board page is read-only.
  await page.locator('li.admin-board-row', { hasText: 'Feedback' })
    .getByRole('button', { name: 'Archive' }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);

  await visit(page, '/c/feedback');
  await expect(page.locator('[data-archived-banner]')).toBeVisible();
  await expect(page.locator('details.composer-details')).toHaveCount(0);
  await shot(page, info, '22-board-archived-readonly');

  // Unarchive restores the composer affordance.
  await visit(page, '/admin/structure');
  await page.locator('li.admin-board-row', { hasText: 'Feedback' })
    .getByRole('button', { name: 'Unarchive' }).click();
  await visit(page, '/c/feedback');
  await expect(page.locator('details.composer-details')).toBeVisible();
  await shot(page, info, '23-board-unarchived');
});
```

- [ ] **Step 2: Run it, expect FAIL** — `cd tests/browser && npm run evidence -- --grep "reorder and archive"`. Fails until Tasks 4 + 7 are deployed in the evidence build (buttons/banner absent).

- [ ] **Step 3: Minimal implementation** — Optional `public/assets/app.js` drag decoration (additive, after the existing IIFE blocks):
```js
    // Admin structure: optional drag reorder over the working up/down forms.
    document.querySelectorAll('[data-reorder-boards]').forEach(function (list) {
        var token = (list.closest('.admin') || document).querySelector('input[name="_token"]');
        list.querySelectorAll('li[data-board-id]').forEach(function (li) { li.draggable = true; });
        var dragging = null;
        list.addEventListener('dragstart', function (e) {
            dragging = e.target.closest('li[data-board-id]');
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('li[data-board-id]');
            if (over && dragging && over !== dragging) {
                var rect = over.getBoundingClientRect();
                list.insertBefore(dragging, (e.clientY - rect.top) > rect.height / 2 ? over.nextSibling : over);
            }
        });
        list.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!token) { return; }
            var ids = Array.prototype.map.call(list.querySelectorAll('li[data-board-id]'), function (li) {
                return li.getAttribute('data-board-id');
            });
            var params = new URLSearchParams();
            params.set('_token', token.value);
            params.set('scope', 'board');
            params.set('category_id', list.getAttribute('data-category-id'));
            ids.forEach(function (id) { params.append('ids[]', id); });
            fetch('/admin/structure/reorder', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
                .then(function () { window.location.reload(); })
                .catch(function () { window.location.reload(); });
        });
    });
```
This is decorative only; if it is skipped, the up/down buttons (and the Playwright journey, which clicks buttons) still pass.

- [ ] **Step 4: Run it, expect PASS** — `cd tests/browser && npm run evidence` (captures `20`–`23` PNGs under `docs/evidence/browser/<viewport>/` for both projects).

- [ ] **Step 5: Commit** — `git add public/assets/app.js tests/browser/gate-a.spec.ts docs/evidence/browser && git commit -m "B-reorder/archive: optional drag enhancement + Playwright reorder/archive evidence"`

---

## Self-check coverage

- **Checklist 1** (CategoryRepository::setPositions, BoardRepository::setPositions/setArchived, board update writes position) → **Task 2**
- **Checklist 2** (AdminService reorderCategories/reorderBoards with id-set EQUAL validation; moveCategory/moveBoard funnel through them; updateBoard category-change appends via nextPosition; archiveBoard/unarchiveBoard audited) → **Task 3**
- **Checklist 3** (AdminController moveCategory/moveBoard/reorder/archiveBoard/unarchiveBoard — requireAdmin, run()/422, redirect+flash) → **Task 4**
- **Checklist 4** (BoardPolicy::canPost false when archived; canRead/isListed stay true; isArchived helper) → **Task 1**
- **Checklist 5** (absolute read-only for all roles: create/reply via canPost, edit + delete-own explicit guards, thread status + wiki guards; no admin/mod carve-out) → **Tasks 5 + 6**
- **Checklist 6** (BoardController passes is_archived; board.php renders retired/read-only banner, suppresses New-Topic/reply affordances server-side) → **Task 7** (controller already passes the full `$board` row)
- **Checklist 7** (structure.php per-category + per-board Move up/down, Archive/Unarchive, stable data-* container) → **Task 4**
- **Checklist 8** (optional app.js drag reorder posting to /admin/structure/reorder, pure enhancement) → **Task 9**
- **Checklist 9** (reorder swaps order; move boundary no-ops; foreign/missing id → 422 unchanged; non-admin 403 / guest 302; category reassignment appends without collision; archive → write 403 + readable 200 banner + no affordance; unarchive restores; archived still readable + searchable; moderator/admin also blocked; audit rows via same-connection COUNT; AppSeoVisibilityTest stays green) → **Tasks 3 (service), 4 (HTTP), 5 (read-only), 6 (workflow/wiki), 7 (banner), 8 (search/SEO)**
- **Checklist 10** (Playwright: Move-up changes order; archive → read-only; unarchive restores; desktop+mobile PNGs) → **Task 9**
