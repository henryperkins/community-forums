# Per-User Admin Record (Badges + Title) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the ADMIN §5.2 per-user admin record at `GET /admin/users/{id}` (plus a minimal §5.1 directory at `GET /admin/users`) that hosts manual BADGE grant/revoke and cosmetic TITLE override, each audited and authorization-gated.

**Architecture:** A new thin `AdminUserController` marshals input and delegates to the existing `BadgeService` (badge grant/revoke) and `UserModerationService` (title override) — both already route writes through `WriteGate::assertCanWrite` so a suspended admin is blocked (state beats role). Each successful mutation writes exactly one `moderation_log` row (`target_type='user'`) and re-renders/redirects per the anti-draft-loss pattern. Two plain-PHP admin templates render the directory and record screen; no new schema, no feature flag (this surface ships UNGATED).

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB, hand-wired DI (`App::buildContainer`), micro-router (`App::buildRouter`), PHPUnit integration tests via `Tests\Support\TestCase`, Playwright browser evidence.

## Global Constraints

This plan is bound by **`docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md`** (incorporated by reference). Group A specifics:

- **UNGATED.** No feature flag. Do **NOT** route-gate behind `badge_rules` (that is Phase-4 custom rules). `community` + `notifications` flags both default ON (`src/Core/FeatureFlags.php:34,28`), so the granted badge is observable on the public profile and a `badge` notification is queued.
- **Authority:** every action's first line is `$this->requireAdmin()` (→ `ForbiddenException`/403 for non-admins; redirecting 302 to `/login?next=` for guests). User-targeted writes go through `BadgeService` / `UserModerationService`, both of which call `WriteGate::assertCanWrite` (a suspended admin is blocked).
- **Audit:** every successful grant/revoke/title write emits one `moderation_log` row via `ModerationLogRepository::log([...])` with `target_type='user'`, `target_id` = subject user id. Actions: `badge.grant`, `badge.revoke`, `set_title`, `clear_title`. `moderation_log.action` is `VARCHAR(40)` and `target_type` ENUM already contains `'user'` (`database/migrations/0010_moderation_log.php`) — no migration, no ENUM extension.
- **`reason`** is optional free-text on grant/revoke/title; persisted into the audit row (`reason`, or `before`/`after` for title) only when provided. Never required.
- **CSRF:** every POST form emits `<?= $this->csrfField() ?>`. No mutating GET.
- **CSP / PE:** no inline `<script>`/`<style>`; server-rendered HTML+forms must work with JS off. All five actions are plain form posts.
- **Anti-draft-loss:** controllers catch `App\Core\ValidationException` themselves (the kernel does not) and re-render the record screen at **422** carrying `$e->errors` + `$e->old`.
- **DB rules:** `EMULATE_PREPARES=false` — `LIMIT`/`OFFSET` are clamped to int and concatenated, never bound; no named placeholder is reused (directory search uses `:q1/:q2/:q3`). UTC everywhere. Multi-table mutations run inside `$db->transaction(fn)`.
- **Counters:** this surface touches no denormalized counters and **must not change `users.reputation`**; no `RepairService` hook.
- **Tests:** PHPUnit is strict (`failOnWarning`/`failOnRisky`, ≥1 assertion/test). Assert observable HTTP behavior, not row counts — **except** `moderation_log` / `notifications` rows, which are visible to the same connection within the test before tearDown rollback (mirrors `tests/Integration/Admin/AdminWebhookTest.php`), so a `$this->db->fetchValue('SELECT COUNT(*) …')` audit/notification assertion IS allowed.

**Verified seams (read before coding):**
- The Router constructs controllers with `new $class($container)` (`src/Core/App.php:231`) — `AdminWebhookController` has **no** container bind, so `AdminUserController` needs **no** bind either. Only a `use` import + route registrations.
- `BadgeService` is wired at `src/Core/App.php:729-738` with positional args; `BadgeRepository`/`UserModerationService`/`UserRepository`/`TitleService`/`ModerationLogRepository` are already imported in `App.php`.

---

### Task 1: BadgeRepository — manual catalogue + held-manual helpers

**Files:**
- Modify: `src/Repository/BadgeRepository.php` (add two methods after `forUser`, ~line 78)
- Test: `tests/Integration/Repository/BadgeRepositoryManualTest.php` (new)

**Interfaces:**
- Produces: `BadgeRepository::manualCatalogue(): array<int,array<string,mixed>>` — `WHERE kind='manual' AND is_enabled=1`, ordered by `display_order, name`.
- Produces: `BadgeRepository::manualHeldByUser(int $userId): array<int,array<string,mixed>>` — manual badges the user currently holds, earliest first.
- Consumes: existing seeded manual slugs `staff`, `founder` (`database/migrations/0040_seed_badges.php`); `badges.is_enabled`/`display_order` columns (`database/migrations/0048_phase4_gate_a.php:215-218`).

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

final class BadgeRepositoryManualTest extends TestCase
{
    public function test_manual_catalogue_lists_enabled_manual_badges_only(): void
    {
        $repo = new BadgeRepository($this->db);
        $slugs = array_map(static fn (array $b): string => (string) $b['slug'], $repo->manualCatalogue());

        self::assertContains('staff', $slugs);
        self::assertContains('founder', $slugs);
        self::assertNotContains('welcome', $slugs); // an auto badge must never appear
    }

    public function test_manual_held_by_user_returns_only_granted_manual_badges(): void
    {
        $repo = new BadgeRepository($this->db);
        $user = $this->makeUser(['username' => 'badgeholder']);
        $uid = (int) $user['id'];

        self::assertSame([], $repo->manualHeldByUser($uid));

        $repo->awardBySlug($uid, 'staff');   // manual
        $repo->awardBySlug($uid, 'welcome'); // auto — must be excluded

        $held = array_map(static fn (array $b): string => (string) $b['slug'], $repo->manualHeldByUser($uid));
        self::assertSame(['staff'], $held);
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Repository/BadgeRepositoryManualTest.php` — fails with `Error: Call to undefined method App\Repository\BadgeRepository::manualCatalogue()`.

- [ ] **Step 3: Minimal implementation** — add to `src/Repository/BadgeRepository.php` immediately after the `forUser()` method (before the closing `}` of the class):
```php
    /**
     * Manual badges available for admin grant (ADMIN §5.2): enabled, manual-kind,
     * in display order then name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function manualCatalogue(): array
    {
        return $this->db->fetchAll(
            "SELECT id, slug, name, description, icon
             FROM badges
             WHERE kind = 'manual' AND is_enabled = 1
             ORDER BY display_order ASC, name ASC",
        );
    }

    /**
     * Manual badges $userId currently holds, so the record screen can render a
     * revoke control per held manual badge. Earliest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function manualHeldByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT b.slug, b.name, b.icon, ub.awarded_at
             FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = ? AND b.kind = 'manual'
             ORDER BY ub.awarded_at ASC, b.id ASC",
            [$userId],
        );
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Repository/BadgeRepositoryManualTest.php`

- [ ] **Step 5: Commit** — `git add src/Repository/BadgeRepository.php tests/Integration/Repository/BadgeRepositoryManualTest.php && git commit -m "BadgeRepository: manual catalogue + held-manual helpers (Group A)"`

---

### Task 2: BadgeService — reason + audit on manual grant/revoke

**Files:**
- Modify: `src/Service/BadgeService.php` (add `use` import line ~10; append constructor param ~line 36; rewrite `grantManual` ~110, `revokeManual` ~122; add private `audit()` helper)
- Modify: `src/Core/App.php` (BadgeService bind, append `ModerationLogRepository` arg, ~line 729-738)
- Test: `tests/Integration/Service/BadgeServiceManualTest.php` (new)

**Interfaces:**
- Produces: `BadgeService::grantManual(User $admin, int $userId, string $slug, ?string $reason = null): void` — on a row that actually changed (`BadgeRepository::awardBySlug` true) writes a `badge.grant` audit row and fires `notifyBadge`; throws `ValidationException` when the slug is not a `kind='manual'` badge.
- Produces: `BadgeService::revokeManual(User|int $actor, int $userId, string $slug, ?string $reason = null): bool` — throws `ValidationException` for a non-manual slug (symmetric with grant); on a row that actually changed (`revokeBySlug` true) writes a `badge.revoke` audit row and returns `true`; revoke is silent (no notification).
- Consumes: `ModerationLogRepository::log(array $entry): int` (`src/Repository/ModerationLogRepository.php:24`); `User::id(): int`; `BadgeRepository::awardBySlug(int,string,?int): bool` / `revokeBySlug(int,string): bool`.

> Constructor param is **appended last** (nullable, defaulting `null`) so existing positional callers `new BadgeService($db, $badges, $users)` (`tests/Integration/Core/AppEmailVerificationTest.php:37`) and the 8-arg `tests/Integration/Core/AppBadgeSolvedTest.php:30` keep compiling. `Database::transaction` returns the callback value and no-ops a nested begin under the test's outer transaction (`src/Core/Database.php:108`).

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Repository\BadgeRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Tests\Support\TestCase;

final class BadgeServiceManualTest extends TestCase
{
    private function service(): BadgeService
    {
        return new BadgeService(
            $this->db,
            new BadgeRepository($this->db),
            new UserRepository($this->db),
            null, // notifications
            10,
            10,
            100,
            1000,
            new ModerationLogRepository($this->db),
        );
    }

    public function test_grant_manual_awards_and_audits_with_reason(): void
    {
        $admin = $this->makeAdmin(['username' => 'grant_admin']);
        $user = $this->makeUser(['username' => 'grantee']);
        $uid = (int) $user['id'];

        $this->service()->grantManual($this->userEntity($admin), $uid, 'staff', 'core team');

        self::assertTrue((new BadgeRepository($this->db))->hasBadgeSlug($uid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log
             WHERE action = 'badge.grant' AND target_type = 'user' AND target_id = ? AND reason = ?",
            [$uid, 'core team'],
        ));
    }

    public function test_grant_manual_rejects_auto_slug(): void
    {
        $admin = $this->makeAdmin(['username' => 'grant_admin2']);
        $user = $this->makeUser(['username' => 'grantee2']);
        $this->expectException(ValidationException::class);
        $this->service()->grantManual($this->userEntity($admin), (int) $user['id'], 'welcome');
    }

    public function test_revoke_manual_removes_and_audits(): void
    {
        $admin = $this->makeAdmin(['username' => 'revoke_admin']);
        $user = $this->makeUser(['username' => 'revokee']);
        $uid = (int) $user['id'];
        $badges = new BadgeRepository($this->db);
        $badges->awardBySlug($uid, 'staff');

        $removed = $this->service()->revokeManual($this->userEntity($admin), $uid, 'staff');

        self::assertTrue($removed);
        self::assertFalse($badges->hasBadgeSlug($uid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.revoke' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_revoke_manual_rejects_auto_slug(): void
    {
        $admin = $this->makeAdmin(['username' => 'revoke_admin2']);
        $user = $this->makeUser(['username' => 'revokee2']);
        $this->expectException(ValidationException::class);
        $this->service()->revokeManual($this->userEntity($admin), (int) $user['id'], 'welcome');
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Service/BadgeServiceManualTest.php` — fails: `grantManual` takes no `$reason`, `revokeManual` signature mismatch (`User given, int expected`), and no `badge.grant`/`badge.revoke` audit rows exist.

- [ ] **Step 3: Minimal implementation**

Add the import after the existing `use App\Repository\BadgeRepository;` line in `src/Service/BadgeService.php`:
```php
use App\Repository\ModerationLogRepository;
```

Append the audit-log dependency to the constructor (after `private int $wellLikedRep = 1000,`):
```php
    public function __construct(
        private Database $db,
        private BadgeRepository $badges,
        private UserRepository $users,
        private ?NotificationService $notifications = null,
        private int $conversationStarterThreads = 10,
        private int $trustedAnswererSolved = 10,
        private int $appreciatedRep = 100,
        private int $wellLikedRep = 1000,
        private ?ModerationLogRepository $log = null,
    ) {
    }
```

Replace `grantManual` and `revokeManual` in their entirety:
```php
    /** Admin manual grant of a `kind=manual` badge (COMMUNITY §6, ADMIN §5.2). */
    public function grantManual(User $admin, int $userId, string $slug, ?string $reason = null): void
    {
        $badge = $this->badges->findBySlug($slug);
        if ($badge === null || $badge['kind'] !== 'manual') {
            throw new ValidationException(['slug' => 'That badge cannot be granted manually.']);
        }
        $this->db->transaction(function () use ($admin, $userId, $slug, $reason): void {
            if ($this->badges->awardBySlug($userId, $slug, $admin->id())) {
                $this->audit($admin->id(), 'badge.grant', $userId, $reason);
                $this->notifications?->notifyBadge($userId);
            }
        });
    }

    /** Moderator lever: clear a manually-granted badge (COMMUNITY §10). Silent (no notification). */
    public function revokeManual(User|int $actor, int $userId, string $slug, ?string $reason = null): bool
    {
        $badge = $this->badges->findBySlug($slug);
        if ($badge === null || $badge['kind'] !== 'manual') {
            throw new ValidationException(['slug' => 'That badge cannot be revoked manually.']);
        }
        $actorId = $actor instanceof User ? $actor->id() : $actor;
        return $this->db->transaction(function () use ($actorId, $userId, $slug, $reason): bool {
            if ($this->badges->revokeBySlug($userId, $slug)) {
                $this->audit($actorId, 'badge.revoke', $userId, $reason);
                return true;
            }
            return false;
        });
    }

    private function audit(?int $actorId, string $action, int $userId, ?string $reason): void
    {
        $this->log?->log([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $userId,
            'reason' => $reason,
        ]);
    }
```

Wire the audit-log repo into the container — in `src/Core/App.php`, change the `BadgeService` bind (line ~729-738) to append the argument:
```php
        $c->bind(BadgeService::class, fn (Container $c) => new BadgeService(
            $c->get(Database::class),
            $c->get(BadgeRepository::class),
            $c->get(UserRepository::class),
            $c->get(NotificationService::class),
            (int) $config->get('community.badge_conversation_starter_threads', 10),
            (int) $config->get('community.badge_trusted_answerer_solved', 10),
            (int) $config->get('community.badge_appreciated_rep', 100),
            (int) $config->get('community.badge_well_liked_rep', 1000),
            $c->get(ModerationLogRepository::class),
        ));
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Service/BadgeServiceManualTest.php` then `vendor/bin/phpunit tests/Integration/Core/AppBadgeSolvedTest.php tests/Integration/Core/AppEmailVerificationTest.php` (confirm the appended param didn't break positional callers).

- [ ] **Step 5: Commit** — `git add src/Service/BadgeService.php src/Core/App.php tests/Integration/Service/BadgeServiceManualTest.php && git commit -m "BadgeService: reason + moderation_log audit on manual grant/revoke (Group A)"`

---

### Task 3: UserModerationService — cosmetic title override

**Files:**
- Modify: `src/Service/UserModerationService.php` (add public `setTitle()` after `lift()` ~line 120; reuses existing `assertAdmin`/`requireSubject`/`$this->log`/`$this->users`)
- Test: `tests/Integration/Service/UserModerationSetTitleTest.php` (new)

**Interfaces:**
- Produces: `UserModerationService::setTitle(User $actor, int $subjectId, ?string $title): void` — `assertAdmin` (routes through `WriteGate::assertCanWrite`, blocking a suspended admin), `requireSubject` (404 via `NotFoundException`), trims + strips control chars, rejects `> 64` chars (`ValidationException` carrying `old['title']`), empty → `NULL` (clear), then inside `$db->transaction` calls `UserRepository::setTitle` + audits `set_title`/`clear_title` with `before`/`after`.
- Consumes: `UserRepository::setTitle(int $id, ?string $title): void` (`src/Repository/UserRepository.php:225`); `ModerationLogRepository::log` (`before`/`after` keys, `src/Repository/ModerationLogRepository.php:24`); existing `assertAdmin`/`requireSubject` guards (`src/Service/UserModerationService.php:132,141`). `users.title` is `VARCHAR(64) NULL` (`database/migrations/0011_users_phase2_columns.php:17`).

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\UserModerationService;
use Tests\Support\TestCase;

final class UserModerationSetTitleTest extends TestCase
{
    private function service(): UserModerationService
    {
        return new UserModerationService(
            $this->db,
            new UserRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            new BoardModeratorRepository($this->db),
        );
    }

    public function test_set_title_persists_trimmed_override_and_audits_set_title(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler']);
        $user = $this->makeUser(['username' => 'titlee']);
        $uid = (int) $user['id'];

        $this->service()->setTitle($this->userEntity($admin), $uid, '  Champion  ');

        self::assertSame('Champion', (string) $this->db->fetchValue('SELECT title FROM users WHERE id = ?', [$uid]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_title' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_empty_title_clears_to_null_and_audits_clear_title(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler2']);
        $user = $this->makeUser(['username' => 'titlee2']);
        $uid = (int) $user['id'];
        $this->users()->setTitle($uid, 'Champion');

        $this->service()->setTitle($this->userEntity($admin), $uid, '');

        self::assertNull($this->db->fetchValue('SELECT title FROM users WHERE id = ?', [$uid]));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_title' AND target_type = 'user' AND target_id = ?",
            [$uid],
        ));
    }

    public function test_title_over_64_chars_throws_validation(): void
    {
        $admin = $this->makeAdmin(['username' => 'titler3']);
        $user = $this->makeUser(['username' => 'titlee3']);
        $this->expectException(ValidationException::class);
        $this->service()->setTitle($this->userEntity($admin), (int) $user['id'], str_repeat('x', 65));
    }

    public function test_suspended_admin_cannot_set_title(): void
    {
        $admin = $this->makeUser(['username' => 'titler4', 'role' => 'admin', 'status' => 'suspended']);
        $user = $this->makeUser(['username' => 'titlee4']);
        $this->expectException(ForbiddenException::class);
        $this->service()->setTitle($this->userEntity($admin), (int) $user['id'], 'Nope');
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Service/UserModerationSetTitleTest.php` — fails with `Error: Call to undefined method App\Service\UserModerationService::setTitle()`.

- [ ] **Step 3: Minimal implementation** — add to `src/Service/UserModerationService.php` immediately after the `lift()` method (before the `// ---- guards ----` comment):
```php
    /**
     * Admin cosmetic-title override (COMMUNITY §8, ADMIN §5.2). Trims and strips
     * control characters; an empty result clears the override (NULL → derived
     * ladder). Caps at 64 chars (users.title VARCHAR(64)). Routes through
     * assertAdmin so a suspended admin is blocked (state beats role). Audits
     * set_title / clear_title with the before/after value.
     */
    public function setTitle(User $actor, int $subjectId, ?string $title): void
    {
        $this->assertAdmin($actor);
        $subject = $this->requireSubject($subjectId);

        $stripped = preg_replace('/[\x00-\x1F\x7F]+/', '', $title ?? '') ?? '';
        $clean = trim($stripped);
        if (mb_strlen($clean) > 64) {
            throw new ValidationException(
                ['title' => 'Title must be 64 characters or fewer.'],
                ['title' => (string) $title],
            );
        }
        $newTitle = $clean === '' ? null : $clean;
        $before = isset($subject['title']) && $subject['title'] !== null ? (string) $subject['title'] : null;

        $this->db->transaction(function () use ($actor, $subjectId, $newTitle, $before): void {
            $this->users->setTitle($subjectId, $newTitle);
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => $newTitle !== null ? 'set_title' : 'clear_title',
                'target_type' => 'user',
                'target_id' => $subjectId,
                'before' => $before,
                'after' => $newTitle,
            ]);
        });
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Service/UserModerationSetTitleTest.php`

- [ ] **Step 5: Commit** — `git add src/Service/UserModerationService.php tests/Integration/Service/UserModerationSetTitleTest.php && git commit -m "UserModerationService: audited cosmetic title override (Group A)"`

---

### Task 4: UserRepository — admin directory listing

**Files:**
- Modify: `src/Repository/UserRepository.php` (add `directory()` after `leaderboard()` ~line 325)
- Test: `tests/Integration/Repository/UserRepositoryDirectoryTest.php` (new)

**Interfaces:**
- Produces: `UserRepository::directory(string $q = '', int $limit = 50, int $offset = 0): array<int,array<string,mixed>>` — newest-first; optional substring search over `username`/`display_name`/`email`. `LIMIT`/`OFFSET` clamped to int and concatenated (never bound, per `EMULATE_PREPARES=false`); distinct named placeholders `:q1/:q2/:q3` (never reuse a placeholder).
- Consumes: `Database::fetchAll(string, array): array` (`src/Core/Database.php:80`).

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\UserRepository;
use Tests\Support\TestCase;

final class UserRepositoryDirectoryTest extends TestCase
{
    public function test_directory_returns_users_newest_first(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'dir_alpha']);
        $this->makeUser(['username' => 'dir_beta']); // created after alpha → higher id

        $usernames = array_map(
            static fn (array $r): string => (string) $r['username'],
            $repo->directory('', 200, 0),
        );

        self::assertContains('dir_alpha', $usernames);
        self::assertContains('dir_beta', $usernames);
        self::assertLessThan(
            array_search('dir_alpha', $usernames, true),
            array_search('dir_beta', $usernames, true), // beta (newer) appears before alpha
        );
    }

    public function test_directory_search_filters_by_handle(): void
    {
        $repo = new UserRepository($this->db);
        $this->makeUser(['username' => 'needle_user']);
        $this->makeUser(['username' => 'other_person']);

        $usernames = array_map(
            static fn (array $r): string => (string) $r['username'],
            $repo->directory('needle', 200, 0),
        );

        self::assertContains('needle_user', $usernames);
        self::assertNotContains('other_person', $usernames);
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Repository/UserRepositoryDirectoryTest.php` — fails with `Error: Call to undefined method App\Repository\UserRepository::directory()`.

- [ ] **Step 3: Minimal implementation** — add to `src/Repository/UserRepository.php` after the `leaderboard()` method (before the closing `}` of the class):
```php
    /**
     * Admin user directory (ADMIN §5.1): newest first, optional substring search
     * over username / display name / email. LIMIT/OFFSET are clamped + inlined
     * (EMULATE_PREPARES=false forbids binding them); search placeholders are
     * distinct (no placeholder is reused).
     *
     * @return array<int,array<string,mixed>>
     */
    public function directory(string $q = '', int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $q = trim($q);
        if ($q === '') {
            return $this->db->fetchAll(
                'SELECT id, username, display_name, email, role, status, reputation, created_at
                 FROM users ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            );
        }
        $like = '%' . $q . '%';
        return $this->db->fetchAll(
            'SELECT id, username, display_name, email, role, status, reputation, created_at
             FROM users
             WHERE username LIKE :q1 OR display_name LIKE :q2 OR email LIKE :q3
             ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['q1' => $like, 'q2' => $like, 'q3' => $like],
        );
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Repository/UserRepositoryDirectoryTest.php`

- [ ] **Step 5: Commit** — `git add src/Repository/UserRepository.php tests/Integration/Repository/UserRepositoryDirectoryTest.php && git commit -m "UserRepository: admin directory listing with clamped LIMIT/OFFSET (Group A)"`

---

### Task 5: AdminUserController + routes + templates + nav

**Files:**
- Create: `src/Controller/AdminUserController.php`
- Create: `templates/admin/users.php` (directory)
- Create: `templates/admin/user_record.php` (record screen)
- Modify: `src/Core/App.php` (add `use App\Controller\AdminUserController;` after line 10; register 5 routes in `buildRouter` after the board-roster block ~line 1099)
- Modify: `templates/admin/dashboard.php` (add unconditional `Users` nav link after line 11)
- Test: `tests/Integration/Admin/AppAdminUserRecordTest.php` (new)

**Interfaces:**
- Produces: `AdminUserController::index(Request, array): Response` (directory), `::show(Request, array): Response` (record), `::setTitle/::grantBadge/::revokeBadge(Request, array): Response`.
- Consumes: `Controller::requireAdmin(): User`, `::view`, `::redirectWithFlash`, `::redirect` (`src/Controller/Controller.php`); `Request::str/int` (`src/Core/Request.php:111,127`); `UserRepository::find/directory`; `BadgeService::grantManual/revokeManual`; `UserModerationService::setTitle`; `BadgeRepository::manualCatalogue/manualHeldByUser`; `TitleService::resolve/derive` (`src/Service/TitleService.php:34,44`); `ValidationException->errors/->old`; `NotFoundException`.
- Routes (contract Group A): `GET /admin/users`, `GET /admin/users/{id}`, `POST /admin/users/{id}/title`, `POST /admin/users/{id}/badges/grant`, `POST /admin/users/{id}/badges/revoke`. Static `/admin/users` registered before `/admin/users/{id}` (`{id}`→`\d+`). No container bind needed (Router uses `new $class($container)`, `src/Core/App.php:231`).

Steps:

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\BadgeRepository;
use Tests\Support\TestCase;

final class AppAdminUserRecordTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $user = $this->makeUser(['username' => 'subject0']);
        $this->assertRedirectContains($this->get('/admin/users/' . (int) $user['id']), '/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(['username' => 'plainuser']));
        $user = $this->makeUser(['username' => 'subject1']);
        $this->assertStatus(403, $this->get('/admin/users/' . (int) $user['id']));
    }

    public function test_missing_subject_is_404(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/users/999999'));
    }

    public function test_directory_lists_a_user(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->makeUser(['username' => 'listedperson']);
        self::assertStringContainsString('listedperson', $this->get('/admin/users')->body());
    }

    public function test_admin_grants_manual_badge_visible_and_notified(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'grantadmin']));
        $sub = $this->makeUser(['username' => 'badgeme']);
        $sid = (int) $sub['id'];

        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff', 'reason' => 'core team']),
            '/admin/users/' . $sid,
        );

        self::assertStringContainsString('Staff', $this->get('/u/badgeme')->body()); // visible on the profile
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'badge'",
            [$sid],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.grant' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_grant_auto_slug_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noauto'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'welcome']));
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug($sid, 'welcome'));
    }

    public function test_revoke_auto_slug_is_422(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'noauto2'])['id'];
        $this->assertStatus(422, $this->post('/admin/users/' . $sid . '/badges/revoke', ['slug' => 'welcome']));
    }

    public function test_revoke_clears_held_manual_badge(): void
    {
        $this->actingAs($this->makeAdmin());
        $sub = $this->makeUser(['username' => 'revoke_me']);
        $sid = (int) $sub['id'];
        (new BadgeRepository($this->db))->awardBySlug($sid, 'staff');

        $this->assertRedirectContains(
            $this->post('/admin/users/' . $sid . '/badges/revoke', ['slug' => 'staff']),
            '/admin/users/' . $sid,
        );
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug($sid, 'staff'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'badge.revoke' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_title_override_wins_then_clear_reverts_to_derived(): void
    {
        $this->actingAs($this->makeAdmin());
        $sub = $this->makeUser(['username' => 'titled']);
        $sid = (int) $sub['id'];
        $this->db->run('UPDATE users SET reputation = 60 WHERE id = ?', [$sid]); // derived ladder = 'Regular'

        $this->post('/admin/users/' . $sid . '/title', ['title' => 'Grand Poobah']);
        self::assertStringContainsString('Grand Poobah', $this->get('/u/titled')->body());

        $this->post('/admin/users/' . $sid . '/title', ['title' => '']);
        $profile = $this->get('/u/titled')->body();
        self::assertStringNotContainsString('Grand Poobah', $profile);
        self::assertStringContainsString('Regular', $profile);

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_title' AND target_id = ?",
            [$sid],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_title' AND target_id = ?",
            [$sid],
        ));
    }

    public function test_title_over_64_chars_is_422_and_preserves_typed_text(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'longtitle'])['id'];
        $long = str_repeat('x', 65);
        $res = $this->post('/admin/users/' . $sid . '/title', ['title' => $long]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString($long, $res->body()); // anti-draft-loss: typed text re-rendered
    }

    public function test_csrf_rejected_without_token(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'csrfsub'])['id'];
        $this->assertStatus(403, $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff'], withToken: false));
    }

    public function test_grant_does_not_change_reputation(): void
    {
        $this->actingAs($this->makeAdmin());
        $sid = (int) $this->makeUser(['username' => 'repsub'])['id'];
        $before = (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$sid]);
        $this->post('/admin/users/' . $sid . '/badges/grant', ['slug' => 'staff']);
        self::assertSame($before, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [$sid]));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php` — fails: routes `/admin/users*` 404 (not registered) so the redirect/status assertions miss.

- [ ] **Step 3: Minimal implementation**

Create `src/Controller/AdminUserController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\TitleService;
use App\Service\UserModerationService;

/**
 * Per-user admin record (ADMIN §5.1 directory + §5.2 record screen): hosts the
 * manual badge grant/revoke and the cosmetic title override. UNGATED. Every
 * action requires an admin; the user-targeted writes route through services
 * that block a suspended admin (state beats role) and write one moderation_log
 * row each.
 */
final class AdminUserController extends Controller
{
    private const PER_PAGE = 50;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $q = trim($request->str('q'));
        $page = max(0, $request->int('page', 0));
        $rows = $this->container->get(UserRepository::class)
            ->directory($q, self::PER_PAGE, $page * self::PER_PAGE);

        return $this->view('admin/users', [
            'users' => $rows,
            'q' => $q,
            'page' => $page,
            'has_next' => count($rows) === self::PER_PAGE,
        ]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->record((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function setTitle(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(UserModerationService::class)
                ->setTitle($admin, $id, $request->str('title'));
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Title updated.');
    }

    /** @param array<string,string> $params */
    public function grantBadge(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        $reason = $request->str('reason');
        try {
            $this->container->get(BadgeService::class)
                ->grantManual($admin, $id, $request->str('slug'), $reason !== '' ? $reason : null);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Badge granted.');
    }

    /** @param array<string,string> $params */
    public function revokeBadge(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        $reason = $request->str('reason');
        try {
            $this->container->get(BadgeService::class)
                ->revokeManual($admin, $id, $request->str('slug'), $reason !== '' ? $reason : null);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Badge revoked.');
    }

    /** Render the per-user admin record (ADMIN §5.2). */
    private function record(int $id, ?ValidationException $error = null, int $status = 200): Response
    {
        $subject = $this->requireSubject($id);
        $badges = $this->container->get(BadgeRepository::class);
        $titles = $this->container->get(TitleService::class);
        $reputation = (int) ($subject['reputation'] ?? 0);

        return $this->view('admin/user_record', [
            'subject' => $subject,
            'stored_title' => $subject['title'] ?? null,
            'effective_title' => $titles->resolve($subject['title'] ?? null, $reputation),
            'derived_title' => $titles->derive($reputation),
            'held_manual' => $badges->manualHeldByUser($id),
            'catalogue' => $badges->manualCatalogue(),
            'errors' => $error?->errors ?? [],
            'old' => $error?->old ?? [],
        ], $status);
    }

    /** @return array<string,mixed> */
    private function requireSubject(int $id): array
    {
        $subject = $this->container->get(UserRepository::class)->find($id);
        if ($subject === null) {
            throw new NotFoundException('User not found.');
        }
        return $subject;
    }
}
```

Add the import in `src/Core/App.php` after `use App\Controller\AdminWebhookController;` (line 10):
```php
use App\Controller\AdminUserController;
```

Register the routes in `buildRouter()`, immediately after the board-roster block (after `src/Core/App.php:1099`):
```php
        // Per-user admin record (ADMIN §5.1/§5.2): directory + record screen,
        // manual badges + cosmetic title. Static before generic.
        $r->get('/admin/users', [AdminUserController::class, 'index']);
        $r->get('/admin/users/{id}', [AdminUserController::class, 'show']);
        $r->post('/admin/users/{id}/title', [AdminUserController::class, 'setTitle']);
        $r->post('/admin/users/{id}/badges/grant', [AdminUserController::class, 'grantBadge']);
        $r->post('/admin/users/{id}/badges/revoke', [AdminUserController::class, 'revokeBadge']);
```

Add the unconditional nav link in `templates/admin/dashboard.php` after the `Boards &amp; categories` link (line 11):
```php
        <a href="/admin/users">Users</a>
```

Create `templates/admin/users.php`:
```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Users');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Users</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/users">Users</a>
    </nav>

    <section class="card">
        <form method="get" action="/admin/users" class="inline-form">
            <input type="search" name="q" class="input" maxlength="80" value="<?= $e($q) ?>" placeholder="Search username, name, or email">
            <button class="btn btn-small" type="submit">Search</button>
        </form>

        <table class="audit">
            <thead><tr><th>User</th><th>Role</th><th>State</th><th>Reputation</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <a href="/admin/users/<?= (int) $u['id'] ?>"><?= $e($u['username']) ?></a>
                        <?php if (($u['display_name'] ?? '') !== ''): ?><span class="muted">(<?= $e($u['display_name']) ?>)</span><?php endif; ?>
                    </td>
                    <td><?= $e($u['role']) ?></td>
                    <td><?= $e($u['status']) ?></td>
                    <td><?= (int) $u['reputation'] ?></td>
                    <td><?= $e(human_date($u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="muted">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <nav class="pager">
            <?php if ($page > 0): ?><a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(['q' => $q, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
            <?php if (!empty($has_next)): ?><a class="btn btn-small" href="/admin/users?<?= $e(http_build_query(['q' => $q, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    </section>
</div>
```

Create `templates/admin/user_record.php`:
```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'User · ' . ($subject['username'] ?? ''));
$display = ($subject['display_name'] ?? '') !== '' ? $subject['display_name'] : ($subject['username'] ?? '');
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($display) ?> <span class="muted">@<?= $e($subject['username']) ?></span></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/users">Users</a>
    </nav>

    <section class="card">
        <h2>Identity</h2>
        <dl class="profile-stats">
            <div><dt>Role</dt><dd><?= $e($subject['role']) ?></dd></div>
            <div><dt>State</dt><dd><?= $e($subject['status']) ?></dd></div>
            <div><dt>Reputation</dt><dd><?= (int) $subject['reputation'] ?></dd></div>
            <div><dt>Profile</dt><dd><a href="/u/<?= $e($subject['username']) ?>">View public profile</a></dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Cosmetic title</h2>
        <p class="muted">Effective: <strong><?= $e($effective_title) ?></strong> · Derived ladder: <?= $e($derived_title) ?></p>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/title" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Title override</span>
                <input type="text" name="title" class="input" maxlength="64" value="<?= $e($old['title'] ?? ($stored_title ?? '')) ?>">
            </label>
            <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Save title</button></div>
        </form>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/title" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="title" value="">
            <button class="btn btn-small" type="submit">Clear (revert to derived)</button>
        </form>
    </section>

    <section class="card">
        <h2>Badges</h2>
        <h3>Grant a manual badge</h3>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/badges/grant" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Badge</span>
                <select name="slug" class="input" required>
                    <?php foreach ($catalogue as $b): ?>
                        <option value="<?= $e($b['slug']) ?>"><?= $e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['slug'])): ?><p class="field-error"><?= $e($errors['slug']) ?></p><?php endif; ?>
            <label class="field">
                <span>Reason (optional)</span>
                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>">
            </label>
            <div class="form-actions"><button class="btn" type="submit">Grant badge</button></div>
        </form>

        <h3>Held manual badges</h3>
        <?php if (empty($held_manual)): ?>
            <p class="muted">No manual badges granted.</p>
        <?php else: ?>
            <ul class="link-list">
                <?php foreach ($held_manual as $b): ?>
                    <li>
                        <span class="badge-icon" aria-hidden="true"><?= $e($b['icon'] ?? '🏷️') ?></span>
                        <?= $e($b['name']) ?>
                        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/badges/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="slug" value="<?= $e($b['slug']) ?>">
                            <button class="linkbtn muted" type="submit">Revoke</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php` then `composer test` (full suite green).

- [ ] **Step 5: Commit** — `git add src/Controller/AdminUserController.php templates/admin/users.php templates/admin/user_record.php templates/admin/dashboard.php src/Core/App.php tests/Integration/Admin/AppAdminUserRecordTest.php && git commit -m "AdminUserController: per-user record (badges + title) + directory, routes, nav (Group A)"`

---

### Task 6: Playwright browser evidence

**Files:**
- Modify: `tests/browser/gate-a.spec.ts` (append a new `test(...)` block; reuse the existing `login`, `visit`, `shot` helpers — `tests/browser/gate-a.spec.ts:24,31,37`)
- Output: `docs/evidence/browser/<project>/14-admin-users.png`, `15-admin-user-record.png` (desktop + mobile, one per configured Playwright project)

**Interfaces:**
- Consumes: seeded admin `admin@retro.test` / `password123` and member `bob` (`tests/browser/seed.php:97-99`). No seed change required — Group A is UNGATED and `community`/`notifications` default ON.

Steps:

- [ ] **Step 1: Write the failing test** — append to `tests/browser/gate-a.spec.ts`:
```ts
test('admin per-user record: badges + title', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/users');
  await expect(page.getByRole('link', { name: 'bob' })).toBeVisible();
  await shot(page, info, '14-admin-users');

  await page.getByRole('link', { name: 'bob' }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.getByRole('heading', { name: /bob/ })).toBeVisible();

  // Grant a manual badge (no-JS form post).
  await page.locator('form[action$="/badges/grant"] select[name="slug"]').selectOption('staff');
  await page.locator('form[action$="/badges/grant"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.getByText('Staff').first()).toBeVisible();
  await shot(page, info, '15-admin-user-record');

  // Revoke it.
  await page.locator('form[action$="/badges/revoke"] button[type="submit"]').first().click();
  await page.waitForURL(/\/admin\/users\/\d+$/);

  // Set then clear a cosmetic title.
  await page.locator('form.stacked[action$="/title"] input[name="title"]').fill('Community Hero');
  await page.locator('form.stacked[action$="/title"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.locator('form.stacked[action$="/title"] input[name="title"]')).toHaveValue('Community Hero');

  await page.locator('form.inline-form[action$="/title"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.locator('form.stacked[action$="/title"] input[name="title"]')).toHaveValue('');
});
```

- [ ] **Step 2: Run it, expect FAIL** — from `tests/browser`: `npm run evidence` (or `npx playwright test gate-a.spec.ts -g "per-user record"`) — fails because `/admin/users` is unreachable until Task 5 is merged (run this task only after Task 5).

- [ ] **Step 3: Minimal implementation** — no app code; this task is the spec + evidence capture. Ensure the dev server + seed are running per `tests/browser/README.md` / `prepare.sh`, then the new spec drives the surface and writes the PNGs.

- [ ] **Step 4: Run it, expect PASS** — from `tests/browser`: `npm run evidence`; confirm `docs/evidence/browser/<project>/14-admin-users.png` and `15-admin-user-record.png` exist for each viewport.

- [ ] **Step 5: Commit** — `git add tests/browser/gate-a.spec.ts docs/evidence/browser && git commit -m "Browser evidence: per-user admin record badges + title (Group A)"`

---

## Self-check coverage

| # | Spec requirement | Task |
|---|---|---|
| 1 | `grantManual` gains `?string $reason`; `revokeManual` gains actor + `?string $reason`; inject `ModerationLogRepository`; audit only on a row that changed (`badge.grant`/`badge.revoke`, `target_type='user'`); keep `notifyBadge` on grant, revoke silent; preserve `kind!='manual'` validation | Task 2 |
| 2 | `BadgeRepository::manualCatalogue()` (`kind='manual' AND is_enabled=1`, ordered) + `manualHeldByUser(int)` | Task 1 |
| 3 | `UserModerationService::setTitle(User,int,?string)`: `assertAdmin`→WriteGate, `requireSubject`, trim+strip-control+reject `>64`, empty→NULL, transactional `setTitle` + `set_title`/`clear_title` audit with before/after | Task 3 |
| 4 | `AdminUserController`: `index` directory, `show` record, `setTitle`/`grantBadge`/`revokeBadge`; `requireAdmin` first; catch `ValidationException`→422 re-render with `errors`+`old`; resolve subject (404) | Tasks 4 (directory query) + 5 (controller) |
| 5 | Routes registered in `buildRouter` (static before generic); no container bind needed (verified `new $class($container)`) | Task 5 |
| 6 | `templates/admin/users.php` + `templates/admin/user_record.php` (title set/clear, badge grant w/ reason, per-held revoke); subnav + `csrfField` + `$e`; no inline JS/CSS | Task 5 |
| 7 | `templates/admin/dashboard.php`: unconditional `Users` nav link | Task 5 |
| 8 | Integration tests: guest→302; non-admin→403; grant manual→success + profile + `badge` notification; grant/revoke AUTO→422; revoke clears held; title set wins; clear reverts; `>64`→422; CSRF→403; one `moderation_log` per grant/revoke/title (COUNT); grant does not change `users.reputation` | Tasks 2, 3, 5 |
| 9 | Playwright `gate-a.spec.ts`: admin opens `/admin/users/{id}`, grant+revoke badge, set+clear title; desktop + mobile PNGs into `docs/evidence/browser/` | Task 6 |
| — | UNGATED — no feature flag; not gated behind `badge_rules` | Global Constraints (all tasks) |
