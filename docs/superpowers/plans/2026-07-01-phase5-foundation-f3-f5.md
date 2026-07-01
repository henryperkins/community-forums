# Phase 5 Foundation F3 + F5 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the Phase 5 Gate A **authorization spine** deploy-dark — the code-owned capability catalogue + role-capability seed (F3) and the `protected_owners` seed + shared `LastOwnerGuard` (F5) — behind the `capabilities` flag, changing no live behavior.

**Architecture:** Two Foundation sub-plans that share one seed migration (`0066`). F3 turns the authored taxonomy (`docs/phase5/capability-taxonomy.md`) into `src/Security/CapabilityCatalog.php` (a static code-owned enumeration, mirroring `ApiScopes`) plus a `CapabilityInventoryService` golden matrix whose coverage test *is* the enforcement of A1; `0066` seeds the empty `0050` `capabilities`/`role_capabilities` tables from the catalogue. F5 seeds `protected_owners` from existing admins, adds a `ProtectedOwnerRepository` + a shared `LastOwnerGuard` (the "≥1 active recoverable owner" invariant, decision #27), a `RepairService` reconcile, and wires the guard into the one owner-loss path that exists today (account lifecycle) **behind the `capabilities` flag** so Foundation stays dark; the four not-yet-built paths get the guard's public API + documented future hooks.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB (PDO, `EMULATE_PREPARES=false`), the in-process kernel test harness (`Tests\Support\TestCase`), file-based `Migrator`, `bin/console migrate` / `repair` / `verify:upgrade`. No new runtime dependencies.

## Global Constraints

*Every task implicitly includes this section. Values copied verbatim from CLAUDE.md, the Gate A program plan's Global Constraints, and ADR 0004.*

- **Deploy-dark.** Everything ships behind the existing `capabilities` flag, **default `false`** (`src/Core/FeatureFlags.php:84`). No live behavior changes; `tests/Integration/Core/AppFeatureFlagTest.php::test_phase5_foundation_flags_default_dark` must still prove `capabilities` (and every Phase 5 flag) is dark after this increment. "Inert schema is not evidence" — F3/F5 ship enforcing tests, not just seeds.
- **Migration.** Additive-only / forward-only; use **exactly `0066`** named `0066_phase5_seed_capabilities_owners.php` (next free number — highest on disk is `0065`). `up()` is seed-only (no DDL — the tables exist from `0050`); `down()` deletes only the rows it seeded. Seeds use `INSERT IGNORE` (pattern: `database/migrations/0040_seed_badges.php`). Hand-update `SCHEMA.md` (§5A note + §9 changelog + version bump v1.24 → v1.25).
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (cast int + concatenate after clamping); never reuse a named placeholder (use `?` positional here); **UTC everywhere** (`UTC_TIMESTAMP()` in SQL, `gmdate()` in PHP); IPs packed via `inet_pton` (not relevant here).
- **Write path.** Repositories are `final`, constructor `(private Database $db)`, return **associative arrays**, prepared statements only. Services own rules; multi-table mutations run in `$db->transaction(fn)`. `src/Domain/User.php` is the only domain object.
- **Authorization is three orthogonal axes** (global role · account **state** · per-board authority). The catalogue is *policy data*, not new enforcement — the live path keeps using `users.role`/`board_moderators`/`boards.post_min_role` until the resolver (Increment 1, out of scope here). Reputation/badges/profile fields are **never** capabilities (`docs/phase5/capability-taxonomy.md` §8).
- **Strict CSP / no inline JS-CSS.** This increment adds **no UI** (catalogue, seed, guard, repository, reconcile only) — so no Playwright/axe surface is introduced. All F3/F5 evidence is PHPUnit + `verify:upgrade`.
- **A1 is the source of truth.** `docs/phase5/capability-taxonomy.md` (54 keys, §4 tables; §6 role maps; §8 exclusions; §7 legacy quirks) is authoritative; where code and taxonomy drift, the coverage test fails and the taxonomy wins (fix code or taxonomy, never paper over).

---

## File Structure

| File | Create/Modify | Responsibility |
|---|---|---|
| `src/Security/CapabilityCatalog.php` | Create | Code-owned static enumeration of the 54 `core.*` keys (scope/risk/delegable/protected/description/consent) + the 5-key protected set + the cumulative role→capability increments. Mirrors `ApiScopes`. |
| `src/Service/CapabilityInventoryService.php` | Create | The route/permission **golden matrix**: capability_key → authoritative call-site anchor(s), plus the §8 non-capability exclusions. Its coverage test enforces A1. |
| `src/Repository/ProtectedOwnerRepository.php` | Create | Thin single-table wrapper over `protected_owners` (existence/count/designate). |
| `src/Security/LastOwnerGuard.php` | Create | Shared "≥1 active recoverable owner" invariant (decision #27); parity-safe fallback to the legacy last-admin rule while unseeded. |
| `database/migrations/0066_phase5_seed_capabilities_owners.php` | Create | Seed `capabilities` + `role_capabilities` from `CapabilityCatalog`; backfill `protected_owners` from existing active admins. |
| `src/Service/RepairService.php` | Modify | Add `repairProtectedOwners()` (idempotent owner-invariant reconcile) + include it in `repairAll()`. |
| `bin/console` | Modify | Call `repairProtectedOwners()` in the `repair` command path. |
| `src/Core/App.php` | Modify (`buildContainer`, ~:1036) | Bind `ProtectedOwnerRepository` + `LastOwnerGuard`; pass `LastOwnerGuard` into `AccountLifecycleService` **only when `capabilities` is on** (else `null`). |
| `src/Service/AccountLifecycleService.php` | Modify | Accept a nullable `LastOwnerGuard` (8th ctor arg); consult it (`?->`) in `deactivate()`/`requestDeletion()`. |
| `SCHEMA.md` | Modify | §5A note that `0066` seeds the catalogue/roles/owners; §9 changelog entry; version v1.24 → v1.25. |
| `tests/Unit/Security/CapabilityCatalogTest.php` | Create | F3 catalogue invariants (count, protected set, schema-conformance, consent, role-map counts). |
| `tests/Integration/Core/CapabilityInventoryCoverageTest.php` | Create | F3 coverage: matrix ⟺ catalogue; exclusions ∈ §8. |
| `tests/Integration/Core/AppPhase5CapabilitySeedTest.php` | Create | `0066` seeded rows match the catalogue; role maps reproduce guest/user/mod/admin; flag still dark. |
| `tests/Integration/Repository/ProtectedOwnerRepositoryTest.php` | Create | Repository behavior. |
| `tests/Integration/Service/RepairProtectedOwnersTest.php` | Create | Reconcile is correct + idempotent + no-op on empty. |
| `tests/Integration/Core/AppProtectedOwnerTest.php` | Create | `LastOwnerGuard` parity fallback + owner-set logic + wired through the account-lifecycle path behind `capabilities`. |

---

## Task 1: `CapabilityCatalog` — code-owned capability enumeration (F3)

**Files:**
- Create: `src/Security/CapabilityCatalog.php`
- Test: `tests/Unit/Security/CapabilityCatalogTest.php`

**Interfaces:**
- Consumes: nothing (pure static class; mirrors `App\Security\ApiScopes`).
- Produces:
  - `CapabilityCatalog::all(): array<string,array{scope:string,risk:string,delegable:bool,protected:bool,description:string,consent:?string}>`
  - `CapabilityCatalog::keys(): list<string>`
  - `CapabilityCatalog::has(string $key): bool`
  - `CapabilityCatalog::isProtected(string $key): bool`
  - `CapabilityCatalog::consent(string $key): ?string`
  - `CapabilityCatalog::PROTECTED` (list of 5 keys)
  - `CapabilityCatalog::roleCapabilities(): array<string,list<string>>` (cumulative: `system.guest|user|moderator|admin` → keys)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CapabilityCatalog;
use PHPUnit\Framework\TestCase;

final class CapabilityCatalogTest extends TestCase
{
    private const SCOPES = ['site', 'category', 'board', 'self'];
    private const RISKS  = ['low', 'medium', 'high', 'protected'];

    public function test_catalogue_has_exactly_54_keys(): void
    {
        self::assertCount(54, CapabilityCatalog::all(), 'A1 taxonomy defines 54 core.* keys');
    }

    public function test_every_key_is_namespaced_core_with_valid_scope_and_risk(): void
    {
        foreach (CapabilityCatalog::all() as $key => $meta) {
            self::assertStringStartsWith('core.', $key, "$key must be core-namespaced");
            self::assertContains($meta['scope'], self::SCOPES, "$key scope");
            self::assertContains($meta['risk'], self::RISKS, "$key risk");
        }
    }

    public function test_protected_invariant_holds(): void
    {
        // §2 invariant: risk='protected' <=> is_protected <=> NOT is_delegable.
        self::assertCount(5, CapabilityCatalog::PROTECTED);
        foreach (CapabilityCatalog::all() as $key => $meta) {
            $isProtected = in_array($key, CapabilityCatalog::PROTECTED, true);
            self::assertSame($isProtected, $meta['protected'], "$key protected flag");
            self::assertSame($isProtected, $meta['risk'] === 'protected', "$key risk<=>protected");
            self::assertSame(!$isProtected, $meta['delegable'], "$key delegable<=>!protected");
        }
    }

    public function test_every_non_protected_key_has_a_non_empty_consent_string(): void
    {
        foreach (CapabilityCatalog::all() as $key => $meta) {
            if (in_array($key, CapabilityCatalog::PROTECTED, true)) {
                self::assertNull($meta['consent'], "$key (protected) must have no consent string");
                continue;
            }
            self::assertIsString($meta['consent']);
            self::assertNotSame('', trim((string) $meta['consent']), "$key needs a consent string");
        }
    }

    public function test_role_capabilities_are_cumulative_with_expected_counts(): void
    {
        $roles = CapabilityCatalog::roleCapabilities();
        self::assertCount(1, $roles['system.guest']);
        self::assertCount(15, $roles['system.user']);       // guest(1) + §4.2 (14)
        self::assertCount(28, $roles['system.moderator']);  // user(15) + §4.3 (13)
        self::assertCount(49, $roles['system.admin']);      // moderator(28) + §4.4 (21)

        // Cumulative: each tier is a superset of the previous.
        self::assertSame([], array_diff($roles['system.guest'], $roles['system.user']));
        self::assertSame([], array_diff($roles['system.user'], $roles['system.moderator']));
        self::assertSame([], array_diff($roles['system.moderator'], $roles['system.admin']));

        // Every role key is catalogued and no protected key is ever role-mapped.
        foreach ($roles['system.admin'] as $key) {
            self::assertTrue(CapabilityCatalog::has($key), "$key mapped but not catalogued");
            self::assertNotContains($key, CapabilityCatalog::PROTECTED, "$key protected keys are never role-mapped");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Security/CapabilityCatalogTest.php`
Expected: FAIL — `Error: Class "App\Security\CapabilityCatalog" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Security/CapabilityCatalog.php`. Transcribe every row from `docs/phase5/capability-taxonomy.md` §4 into `CAPABILITIES` using the exact tuple order `[scope, risk, delegable, protected, description, consent]`. The full catalogue (all 54 keys — the test in Step 1 pins the count, the invariant, and the consent requirement, so any transcription slip fails):

```php
<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Code-owned core capability catalogue (Foundation F3). The single source of
 * truth `docs/phase5/capability-taxonomy.md` §4 (A1, ADR 0012) is transcribed
 * here; the `0066` seed populates the `capabilities`/`role_capabilities` tables
 * from this class, and `CapabilityInventoryService`'s coverage test enforces
 * parity. Mirrors `App\Security\ApiScopes` (a static catalogue, not a service).
 *
 * Deploy-dark: nothing resolves against this until the `capabilities` flag +
 * the resolver (P5-08, Increment 1) land. Reputation/badges/profile fields are
 * NEVER capabilities (taxonomy §8).
 */
final class CapabilityCatalog
{
    /** Non-delegable protected authority (taxonomy §4.5 / decision #22) — never role-mapped, never delegated. */
    public const PROTECTED = [
        'core.owner.transfer',
        'core.owner.recovery',
        'core.trust.manage_keys',
        'core.signature.override',
        'core.audit.integrity',
    ];

    /**
     * key => [scope, risk, delegable, protected, description, consent].
     * `consent` is the human string shown on grant/increase (taxonomy §5);
     * protected keys have null consent (never delegated).
     *
     * @var array<string,array{0:string,1:string,2:bool,3:bool,4:string,5:?string}>
     */
    private const CAPABILITIES = [
        // ── §4.1 read / visibility (system.guest) ───────────────────────────
        'core.board.read' => ['board', 'low', true, false, 'Read boards, threads, posts, and public site surfaces, subject to the board read gate.', "Read boards, threads, and posts you're allowed to see."],

        // ── §4.2 user baseline (system.user) ────────────────────────────────
        'core.thread.create'          => ['board', 'low', true, false, 'Start a topic in a board you can post to.', 'Start new topics in boards you can post to.'],
        'core.post.create'            => ['board', 'low', true, false, 'Post a reply (thread not locked).', 'Post replies in threads you can post to.'],
        'core.post.edit_own'          => ['self',  'low', true, false, 'Edit your own post.', 'Edit your own posts.'],
        'core.post.delete_own'        => ['self',  'low', true, false, 'Delete your own post.', 'Delete your own posts.'],
        'core.content.react'          => ['board', 'low', true, false, 'React to posts, star threads, and vote in polls.', 'React to posts, star threads, and vote in polls.'],
        'core.content.report'         => ['board', 'low', true, false, 'Report a post or message to moderators.', 'Report posts or messages to moderators.'],
        'core.thread.tag'             => ['board', 'low', true, false, 'Add or change tags on a thread you can post in.', 'Add or change tags on threads you can post in.'],
        'core.thread.mark_solved'     => ['board', 'low', true, false, 'Accept/clear the answer on your own thread (or any thread in a board you moderate).', 'Accept or clear the answer on your own threads.'],
        'core.poll.manage'            => ['board', 'low', true, false, 'Create or close polls on your own thread (or any thread in a board you moderate).', 'Create or close polls on your own threads.'],
        'core.thread.manage_workflow' => ['board', 'low', true, false, "Manage a thread's status and assignment (authors on their own threads; staff for staff-only statuses/assignment).", 'Manage the status and assignment of your own threads.'],
        'core.message.participate'    => ['self',  'low', true, false, 'Send and manage your own DMs and group conversations.', 'Send and manage your own direct and group messages.'],
        'core.upload.create'          => ['self',  'low', true, false, 'Upload images and files in the composer.', 'Upload images and files in the composer.'],
        'core.draft.manage_own'       => ['self',  'low', true, false, 'Save and restore your own composer drafts.', 'Save and restore your own composer drafts.'],
        'core.account.manage_self'    => ['self',  'low', true, false, 'View and manage your own member surfaces and account (profile, security, preferences, sessions, blocks, follows, subscriptions, organization, export/deactivate/delete-request).', 'View and manage your own account, profile, and preferences.'],

        // ── §4.3 moderation, board-scoped via canModerate (system.moderator) ─
        'core.post.delete_any'        => ['board', 'medium', true, false, "Delete any member's post in a board you moderate.", "Delete other members' posts in boards this role moderates."],
        'core.post.restore'           => ['board', 'medium', true, false, 'Restore a soft-deleted post.', 'Restore soft-deleted posts in boards this role moderates.'],
        'core.thread.lock'            => ['board', 'medium', true, false, 'Lock or unlock a thread.', 'Lock or unlock threads in boards this role moderates.'],
        'core.thread.pin'             => ['board', 'medium', true, false, 'Pin or unpin a thread.', 'Pin or unpin threads in boards this role moderates.'],
        'core.thread.move'            => ['board', 'medium', true, false, 'Move a thread (moderator on both boards).', 'Move threads between boards this role moderates.'],
        'core.thread.split_merge'     => ['board', 'medium', true, false, 'Split or merge threads (moderator on both).', 'Split or merge threads in boards this role moderates.'],
        'core.post.reveal_author'     => ['board', 'high',   true, false, 'Reveal the author of an anonymous post.', 'Reveal the author of an anonymous post in boards this role moderates.'],
        'core.content.approve'        => ['board', 'medium', true, false, 'Approve or reject held/pending content.', 'Approve or reject held content in boards this role moderates.'],
        'core.content.view_pending'   => ['board', 'low',    true, false, 'View held/pending content awaiting moderation.', 'View held content awaiting moderation in boards this role moderates.'],
        'core.report.handle'          => ['board', 'medium', true, false, 'Triage reports: view queue, claim, resolve, dismiss.', 'Triage and resolve reports in boards this role moderates.'],
        'core.appeal.resolve_content' => ['board', 'medium', true, false, 'Resolve appeals against post/content actions.', 'Resolve appeals about content actions in boards this role moderates.'],
        'core.memory.curate'          => ['board', 'medium', true, false, 'Curate community memory: summaries, related topics, wiki posts.', 'Curate community memory in boards this role moderates.'],
        'core.user.warn'              => ['site',  'medium', true, false, 'Issue a formal warning and add staff notes to a member (staff-any, site-wide).', 'Issue formal warnings and staff notes to members, across the whole site.'],

        // ── §4.4 administration (system.admin) ──────────────────────────────
        'core.user.suspend'           => ['site',     'high',   true, false, 'Suspend a member and lift suspensions.', 'Suspend members and lift suspensions across the whole site.'],
        'core.user.ban'               => ['site',     'high',   true, false, 'Ban a member and lift bans.', 'Ban members and lift bans across the whole site.'],
        'core.user.manage'            => ['site',     'medium', true, false, 'Administer member records: directory view, cosmetic title, clear signature, manual badge grant/revoke.', 'Administer member records: titles, signatures, and manual badges.'],
        'core.appeal.resolve_user'    => ['site',     'high',   true, false, 'Resolve appeals against account actions (warn/suspend/ban).', 'Resolve appeals about account actions (warnings, suspensions, bans).'],
        'core.category.manage'        => ['site',     'medium', true, false, 'Create, edit, delete, reorder categories.', 'Create, edit, delete, and reorder categories.'],
        'core.board.manage'           => ['category', 'medium', true, false, 'Create, edit, delete, archive, move, reorder boards; set posting floor.', 'Create, edit, archive, move, and reorder boards, and set their posting floor.'],
        'core.board.assign_moderators'=> ['board',    'high',   true, false, 'Assign or remove board moderators.', 'Assign or remove moderators on boards.'],
        'core.board.manage_members'   => ['board',    'medium', true, false, 'Add or remove members of a private board.', 'Add or remove members of private boards.'],
        'core.site.configure'         => ['site',     'medium', true, false, 'Configure site name, structure, moderation settings.', 'Configure site name, structure, and moderation settings.'],
        'core.site.branding'          => ['site',     'medium', true, false, 'Manage branding, theme, custom CSS.', 'Manage site branding, theme, and custom CSS.'],
        'core.site.tags'              => ['site',     'low',    true, false, 'Administer the tag catalogue.', 'Administer the tag catalogue.'],
        'core.site.badges'            => ['site',     'low',    true, false, 'Administer badge rules.', 'Administer badge rules.'],
        'core.site.emoji'             => ['site',     'low',    true, false, 'Administer custom emoji.', 'Administer custom emoji.'],
        'core.site.announcements'     => ['site',     'low',    true, false, 'Set or clear the announcement banner.', 'Set or clear the site announcement banner.'],
        'core.site.link_previews'     => ['site',     'low',    true, false, 'Refresh or purge link previews.', 'Refresh or purge link previews.'],
        'core.site.email'             => ['site',     'medium', true, false, 'Operate email: dashboard, test, domain verify, requeue, suppressions, export.', 'Operate site email: delivery, testing, domains, and suppressions.'],
        'core.site.api_tokens'        => ['site',     'high',   true, false, 'Mint and revoke read-only API tokens.', 'Mint and revoke read-only API tokens for the whole site.'],
        'core.site.webhooks'          => ['site',     'high',   true, false, 'Manage outbound webhooks (create, rotate secret, test, replay, delete).', 'Create and manage outbound webhooks, including their signing secrets, for the whole site.'],
        'core.site.secrets'           => ['site',     'high',   true, false, 'Manage service secrets in the vault.', 'Manage service secrets in the vault for the whole site.'],
        'core.package.manage'         => ['site',     'high',   true, false, 'Install, update, pin, roll back, enable, disable, uninstall packages/themes; manage registries.', 'Install, update, roll back, and remove packages and themes, and manage registries.'],
        'core.package.review'         => ['site',     'high',   true, false, 'Operate the publisher/review/advisory console.', 'Operate the package publisher, review, and advisory console.'],

        // ── §4.5 protected — non-delegable (no consent; never role-mapped) ───
        'core.owner.transfer'    => ['site', 'protected', false, true, 'Designate or transfer site ownership.', null],
        'core.owner.recovery'    => ['site', 'protected', false, true, 'Perform break-glass account/owner recovery.', null],
        'core.trust.manage_keys' => ['site', 'protected', false, true, 'Manage registry trust roots and signing keys (rotation/revocation).', null],
        'core.signature.override'=> ['site', 'protected', false, true, 'Override or bypass package signature verification.', null],
        'core.audit.integrity'   => ['site', 'protected', false, true, 'Authority over audit-log integrity.', null],
    ];

    /**
     * Cumulative role → capability increments (taxonomy §6). Guest ⊂ user ⊂
     * moderator ⊂ admin; protected keys (§4.5) are intentionally absent.
     *
     * @var array<string,list<string>>
     */
    private const ROLE_INCREMENTS = [
        'system.guest' => ['core.board.read'],
        'system.user' => [
            'core.thread.create', 'core.post.create', 'core.post.edit_own', 'core.post.delete_own',
            'core.content.react', 'core.content.report', 'core.thread.tag', 'core.thread.mark_solved',
            'core.poll.manage', 'core.thread.manage_workflow', 'core.message.participate',
            'core.upload.create', 'core.draft.manage_own', 'core.account.manage_self',
        ],
        'system.moderator' => [
            'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
            'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author', 'core.content.approve',
            'core.content.view_pending', 'core.report.handle', 'core.appeal.resolve_content',
            'core.memory.curate', 'core.user.warn',
        ],
        'system.admin' => [
            'core.user.suspend', 'core.user.ban', 'core.user.manage', 'core.appeal.resolve_user',
            'core.category.manage', 'core.board.manage', 'core.board.assign_moderators',
            'core.board.manage_members', 'core.site.configure', 'core.site.branding', 'core.site.tags',
            'core.site.badges', 'core.site.emoji', 'core.site.announcements', 'core.site.link_previews',
            'core.site.email', 'core.site.api_tokens', 'core.site.webhooks', 'core.site.secrets',
            'core.package.manage', 'core.package.review',
        ],
    ];

    /** @return array<string,array{scope:string,risk:string,delegable:bool,protected:bool,description:string,consent:?string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::CAPABILITIES as $key => $t) {
            $out[$key] = [
                'scope' => $t[0], 'risk' => $t[1], 'delegable' => $t[2],
                'protected' => $t[3], 'description' => $t[4], 'consent' => $t[5],
            ];
        }
        return $out;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    public static function has(string $key): bool
    {
        return isset(self::CAPABILITIES[$key]);
    }

    public static function isProtected(string $key): bool
    {
        return in_array($key, self::PROTECTED, true);
    }

    public static function consent(string $key): ?string
    {
        return self::CAPABILITIES[$key][5] ?? null;
    }

    /**
     * Cumulative role maps (guest ⊂ user ⊂ moderator ⊂ admin).
     *
     * @return array<string,list<string>>
     */
    public static function roleCapabilities(): array
    {
        $out = [];
        $acc = [];
        foreach (self::ROLE_INCREMENTS as $role => $keys) {
            $acc = array_merge($acc, $keys);
            $out[$role] = $acc;
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Security/CapabilityCatalogTest.php`
Expected: PASS (5 tests). If the count assertion fails, a §4 row was dropped/duplicated; if the cumulative-count assertion fails, a key sits in the wrong tier.

- [ ] **Step 5: Commit**

```bash
git add src/Security/CapabilityCatalog.php tests/Unit/Security/CapabilityCatalogTest.php
git commit -m "feat(phase5): add code-owned CapabilityCatalog (F3) from A1 taxonomy

Deploy-dark: pure static enumeration of the 54 core.* keys + protected set +
cumulative role maps; nothing resolves against it until the capabilities flag.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `CapabilityInventoryService` golden matrix + coverage test (F3)

**Files:**
- Create: `src/Service/CapabilityInventoryService.php`
- Test: `tests/Integration/Core/CapabilityInventoryCoverageTest.php` (extends `Tests\Support\TestCase` only to reuse the autoloaded kernel; it exercises no DB)

**Interfaces:**
- Consumes: `CapabilityCatalog::keys()`, `CapabilityCatalog::PROTECTED`.
- Produces:
  - `CapabilityInventoryService::matrix(): array<string,list<string>>` (capability_key → authoritative call-site anchors from taxonomy §4)
  - `CapabilityInventoryService::exclusions(): array<string,string>` (call-site → §8 recorded rationale)

**Design note:** Foundation's golden matrix is keyed by capability (each catalogued non-protected key must have ≥1 real call-site anchor) rather than by every source line — full static call-site scanning is layered in by the resolver (Increment 1), which exercises the real sites. The coverage test asserts the matrix and catalogue are in exact correspondence, so adding a key without wiring it (or removing one) fails CI.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Security\CapabilityCatalog;
use App\Service\CapabilityInventoryService;
use Tests\Support\TestCase;

final class CapabilityInventoryCoverageTest extends TestCase
{
    private const EXCLUSION_REASONS = [
        'account_state', 'board_read_gate', 'feature_flag', 'api_scope',
        'reputation_badges', 'profile_fields', 'bootstrap_auth', 'structural_invariant',
    ];

    public function test_every_non_protected_catalogued_key_has_at_least_one_call_site(): void
    {
        $svc = new CapabilityInventoryService();
        $matrix = $svc->matrix();
        foreach (CapabilityCatalog::keys() as $key) {
            if (CapabilityCatalog::isProtected($key)) {
                self::assertArrayNotHasKey($key, $matrix, "$key is protected — never a role/route call site");
                continue;
            }
            self::assertArrayHasKey($key, $matrix, "$key has no authoritative call-site anchor");
            self::assertNotEmpty($matrix[$key], "$key anchor list is empty");
        }
    }

    public function test_matrix_references_no_unknown_capability(): void
    {
        foreach (array_keys((new CapabilityInventoryService())->matrix()) as $key) {
            self::assertTrue(CapabilityCatalog::has($key), "matrix references uncatalogued key $key");
            self::assertFalse(CapabilityCatalog::isProtected($key), "matrix must not map protected key $key");
        }
    }

    public function test_exclusions_use_only_recorded_section_8_reasons(): void
    {
        foreach ((new CapabilityInventoryService())->exclusions() as $site => $reason) {
            self::assertContains($reason, self::EXCLUSION_REASONS, "$site uses an unrecorded exclusion reason '$reason'");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/CapabilityInventoryCoverageTest.php`
Expected: FAIL — `Error: Class "App\Service\CapabilityInventoryService" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Service/CapabilityInventoryService.php`. Transcribe the "Authority today (primary site)" anchor from each taxonomy §4 row into `MATRIX` (one entry per non-protected key), and the §8 rows into `EXCLUSIONS`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The route/permission golden matrix (Foundation F3). Maps every non-protected
 * core capability to its authoritative call-site anchor(s) from
 * docs/phase5/capability-taxonomy.md §4, plus the §8 non-capability exclusions.
 * CapabilityInventoryCoverageTest asserts this matrix and CapabilityCatalog stay
 * in exact correspondence — the enforcement of A1.
 */
final class CapabilityInventoryService
{
    /** @var array<string,list<string>> capability_key => authoritative call-site anchors */
    private const MATRIX = [
        'core.board.read' => ['src/Security/BoardPolicy.php:27', 'src/Security/BoardPolicy.php:37'],
        'core.thread.create' => ['src/Service/PostingService.php:80', 'src/Security/BoardPolicy.php:66'],
        'core.post.create' => ['src/Service/PostingService.php:197'],
        'core.post.edit_own' => ['src/Service/PostingService.php:300'],
        'core.post.delete_own' => ['src/Service/PostingService.php:364'],
        'core.content.react' => ['src/Controller/EngagementController.php:32', 'src/Service/PollService.php:80'],
        'core.content.report' => ['src/Controller/ReportController.php:29', 'src/Service/DirectMessageService.php:207'],
        'core.thread.tag' => ['src/Controller/TagController.php:149'],
        'core.thread.mark_solved' => ['src/Service/SolvedAnswerService.php:196'],
        'core.poll.manage' => ['src/Service/PollService.php:151'],
        'core.thread.manage_workflow' => ['src/Service/ThreadWorkflowService.php:197', 'src/Service/ThreadWorkflowService.php:217', 'src/Service/ThreadWorkflowService.php:236'],
        'core.message.participate' => ['src/Service/DirectMessageService.php:57', 'src/Service/DirectMessageService.php:80'],
        'core.upload.create' => ['src/Controller/MediaController.php:31', 'src/Controller/MediaController.php:70'],
        'core.draft.manage_own' => ['src/Controller/DraftController.php:23'],
        'core.account.manage_self' => ['src/Controller/AccountController.php', 'src/Controller/SettingsController.php'],
        'core.post.delete_any' => ['src/Service/ModerationService.php:97'],
        'core.post.restore' => ['src/Service/ModerationService.php:139'],
        'core.thread.lock' => ['src/Service/ModerationService.php:76'],
        'core.thread.pin' => ['src/Service/ModerationService.php:55'],
        'core.thread.move' => ['src/Service/ModerationService.php:177'],
        'core.thread.split_merge' => ['src/Service/ThreadSplitMergeService.php:35', 'src/Service/ThreadSplitMergeService.php:114'],
        'core.post.reveal_author' => ['src/Service/ModerationService.php:249'],
        'core.content.approve' => ['src/Controller/ApprovalController.php:67', 'src/Controller/ApprovalController.php:86'],
        'core.content.view_pending' => ['src/Controller/ApprovalController.php:29', 'src/Controller/ThreadController.php:366', 'src/Controller/MediaController.php:204'],
        'core.report.handle' => ['src/Service/ReportService.php:74'],
        'core.appeal.resolve_content' => ['src/Service/AppealService.php:256'],
        'core.memory.curate' => ['src/Service/CommunityMemoryService.php:284'],
        'core.user.warn' => ['src/Service/UserModerationService.php:179'],
        'core.user.suspend' => ['src/Service/UserModerationService.php:69', 'src/Service/UserModerationService.php:187'],
        'core.user.ban' => ['src/Service/UserModerationService.php:86'],
        'core.user.manage' => ['src/Controller/AdminUserController.php:32', 'src/Controller/AdminUserController.php:56'],
        'core.appeal.resolve_user' => ['src/Service/AppealService.php:249'],
        'core.category.manage' => ['src/Controller/AdminController.php:76'],
        'core.board.manage' => ['src/Service/AdminService.php:198', 'src/Service/AdminService.php:229'],
        'core.board.assign_moderators' => ['src/Service/AdminService.php:466', 'src/Service/AdminService.php:496'],
        'core.board.manage_members' => ['src/Service/AdminService.php:523', 'src/Service/AdminService.php:547'],
        'core.site.configure' => ['src/Controller/AdminController.php:54', 'src/Controller/AdminController.php:65'],
        'core.site.branding' => ['src/Controller/BrandingController.php:73'],
        'core.site.tags' => ['src/Controller/TagController.php:84'],
        'core.site.badges' => ['src/Controller/AdminBadgeRuleController.php:21'],
        'core.site.emoji' => ['src/Controller/AdminCustomEmojiController.php:20'],
        'core.site.announcements' => ['src/Controller/AdminAnnouncementController.php:38'],
        'core.site.link_previews' => ['src/Controller/AdminLinkPreviewController.php:16'],
        'core.site.email' => ['src/Controller/AdminEmailController.php:35'],
        'core.site.api_tokens' => ['src/Controller/AdminApiTokenController.php:26'],
        'core.site.webhooks' => ['src/Controller/AdminWebhookController.php:33'],
        'core.site.secrets' => ['src/Service/SecretVault.php'],
        'core.package.manage' => ['src/Controller/AdminExtensionController.php:19'],
        'core.package.review' => ['src/Controller/AdminExtensionController.php:19'],
    ];

    /** @var array<string,string> non-capability call site => taxonomy §8 reason code */
    private const EXCLUSIONS = [
        'src/Security/WriteGate.php:22' => 'account_state',
        'src/Security/BoardPolicy.php (visibility/membership)' => 'board_read_gate',
        'src/Core/FeatureFlags.php::enabled' => 'feature_flag',
        'src/Security/ApiScopes.php' => 'api_scope',
        'src/Service/ReputationLedgerService.php' => 'reputation_badges',
        'src/Service/BadgeService.php::evaluateForUser' => 'reputation_badges',
        'src/Service/*ProfileField* (owner self-edit)' => 'profile_fields',
        'src/Controller/AuthController.php (login/register/reset/verify)' => 'bootstrap_auth',
        'src/Service/AccountLifecycleService.php:230 (last-admin guard)' => 'structural_invariant',
    ];

    /** @return array<string,list<string>> */
    public function matrix(): array
    {
        return self::MATRIX;
    }

    /** @return array<string,string> */
    public function exclusions(): array
    {
        return self::EXCLUSIONS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Core/CapabilityInventoryCoverageTest.php`
Expected: PASS (3 tests). A missing/extra matrix key fails `test_every_non_protected_catalogued_key_has_at_least_one_call_site` or `test_matrix_references_no_unknown_capability`.

- [ ] **Step 5: Commit**

```bash
git add src/Service/CapabilityInventoryService.php tests/Integration/Core/CapabilityInventoryCoverageTest.php
git commit -m "feat(phase5): add CapabilityInventoryService golden matrix + coverage test (F3)

Every non-protected core capability maps to an authoritative call-site anchor;
the coverage test enforces catalogue<->matrix parity (A1 enforcement).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Migration `0066` — seed capabilities, role_capabilities, protected_owners (F3 + F5)

**Files:**
- Create: `database/migrations/0066_phase5_seed_capabilities_owners.php`
- Test: `tests/Integration/Core/AppPhase5CapabilitySeedTest.php`

**Interfaces:**
- Consumes: `CapabilityCatalog::all()`, `CapabilityCatalog::roleCapabilities()`; the `0050` tables `capabilities`, `roles`, `role_capabilities`, `protected_owners`.
- Produces: seeded rows (54 capabilities; 1/15/28/49 role_capabilities per system role; protected_owners backfilled from active admins).

**Note on the `protected_owners` backfill + tests:** the test bootstrap runs `migrate:fresh` on an **empty** `users` table, so `0066` seeds zero owners in the suite — that path is proven by `verify:upgrade` on populated data (Task 8) and by the reconcile logic (Task 5), not by a PHPUnit row count (per the one-transaction isolation model). This seed test asserts the catalogue/role rows only.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use App\Security\CapabilityCatalog;
use Tests\Support\TestCase;

final class AppPhase5CapabilitySeedTest extends TestCase
{
    public function test_0066_seeds_the_full_catalogue_matching_the_code(): void
    {
        self::assertSame(54, (int) $this->db->fetchValue('SELECT COUNT(*) FROM capabilities'));

        foreach (CapabilityCatalog::all() as $key => $meta) {
            $row = $this->db->fetch('SELECT scope_type, risk_class, is_delegable, is_protected FROM capabilities WHERE capability_key = ?', [$key]);
            self::assertNotNull($row, "capability $key not seeded");
            self::assertSame($meta['scope'], $row['scope_type'], "$key scope_type");
            self::assertSame($meta['risk'], $row['risk_class'], "$key risk_class");
            self::assertSame($meta['delegable'] ? 1 : 0, (int) $row['is_delegable'], "$key is_delegable");
            self::assertSame($meta['protected'] ? 1 : 0, (int) $row['is_protected'], "$key is_protected");
        }
    }

    public function test_role_capabilities_reproduce_cumulative_authority(): void
    {
        $expected = ['system.guest' => 1, 'system.user' => 15, 'system.moderator' => 28, 'system.admin' => 49];
        foreach ($expected as $roleKey => $count) {
            $actual = (int) $this->db->fetchValue(
                'SELECT COUNT(*) FROM role_capabilities rc
                 JOIN roles r ON r.id = rc.role_id
                 WHERE r.role_key = ?',
                [$roleKey],
            );
            self::assertSame($count, $actual, "$roleKey capability count");
        }
    }

    public function test_no_protected_capability_is_ever_role_mapped(): void
    {
        $mapped = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM role_capabilities rc
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE c.is_protected = 1",
        );
        self::assertSame(0, $mapped, 'protected capabilities must never appear in role_capabilities');
    }

    public function test_seeding_the_catalogue_does_not_enable_the_capabilities_flag(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($flags->enabled('capabilities'), 'catalogue seed must stay deploy-dark');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `RB_TEST_FRESH=1 vendor/bin/phpunit tests/Integration/Core/AppPhase5CapabilitySeedTest.php`
Expected: FAIL — `SELECT COUNT(*) FROM capabilities` returns `0` (catalogue seeded empty by `0050`; `0066` doesn't exist yet). `RB_TEST_FRESH=1` forces the bootstrap to rebuild the schema so the new migration is picked up.

- [ ] **Step 3: Write the migration**

Create `database/migrations/0066_phase5_seed_capabilities_owners.php`:

```php
<?php

declare(strict_types=1);

use App\Security\CapabilityCatalog;

/**
 * 0066 · Phase 5 Foundation F3+F5 seed. Populates the empty `0050` capability
 * catalogue + role→capability map from the code-owned CapabilityCatalog, and
 * backfills `protected_owners` from existing active admins.
 *
 * SEED-ONLY (no DDL — the tables exist from 0050). Additive/forward-only and
 * idempotent (INSERT IGNORE, pattern: 0040_seed_badges). Deploy-dark: nothing
 * resolves against these rows until the `capabilities` flag + the resolver land.
 * On a fresh install `users` is empty at migrate time, so the owner backfill is
 * a no-op there; RepairService::repairProtectedOwners + setup designation cover
 * that case at runtime.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Capabilities catalogue (F3) ──────────────────────────────────────
        $cap = $pdo->prepare(
            'INSERT IGNORE INTO capabilities
                (capability_key, namespace, scope_type, risk_class, is_delegable, is_protected, source, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach (CapabilityCatalog::all() as $key => $meta) {
            $namespace = explode('.', $key)[0]; // 'core'
            $cap->execute([
                $key,
                $namespace,
                $meta['scope'],
                $meta['risk'],
                $meta['delegable'] ? 1 : 0,
                $meta['protected'] ? 1 : 0,
                'core',
                $meta['description'],
            ]);
        }

        // ── Role → capability map (F3) ───────────────────────────────────────
        // Set-based per (role_key, capability_key): resolve both ids at insert.
        $map = $pdo->prepare(
            'INSERT IGNORE INTO role_capabilities (role_id, capability_id)
             SELECT r.id, c.id FROM roles r, capabilities c
             WHERE r.role_key = ? AND c.capability_key = ?',
        );
        foreach (CapabilityCatalog::roleCapabilities() as $roleKey => $capKeys) {
            foreach ($capKeys as $capKey) {
                $map->execute([$roleKey, $capKey]);
            }
        }

        // ── Protected owners backfill (F5) ───────────────────────────────────
        // Existing active admins become protected owners so decision #27's guard
        // is enforceable. No-op on a fresh (empty-users) install.
        $pdo->exec(
            "INSERT IGNORE INTO protected_owners (user_id, is_active, designated_by, designated_at, created_at)
             SELECT id, 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM users WHERE role = 'admin' AND status = 'active'",
        );
    }

    public function down(\PDO $pdo): void
    {
        // Seed-only rollback: remove the core catalogue (role_capabilities cascade
        // via FK) and the backfilled owners. 0050's down() drops the tables wholesale.
        $pdo->exec("DELETE FROM role_capabilities WHERE capability_id IN (SELECT id FROM capabilities WHERE source = 'core')");
        $pdo->exec("DELETE FROM capabilities WHERE source = 'core'");
        $pdo->exec('DELETE FROM protected_owners WHERE designated_by IS NULL');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `RB_TEST_FRESH=1 vendor/bin/phpunit tests/Integration/Core/AppPhase5CapabilitySeedTest.php`
Expected: PASS (4 tests). Then run the whole flag suite to confirm no regression:
Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS (unchanged — `capabilities` still dark).

- [ ] **Step 5: Update `SCHEMA.md` §5A + changelog (do it now while the migration is fresh)**

In `SCHEMA.md`, under §5A where the `capabilities` line says "seeded empty", append a sentence; add a §9 changelog row; bump the header version. Exact edits:

- §5A `capabilities(...)` bullet — change "the catalogue is **seeded empty**" to: "the catalogue is seeded by **`0066`** from `src/Security/CapabilityCatalog.php` (54 core keys); `role_capabilities` reproduces the cumulative guest/user/mod/admin authority and `protected_owners` is backfilled from existing active admins (F3/F5, deploy-dark)."
- §9 changelog — add above the `v1.24` row:
  ```
  | v1.25 | 2026-07-01 | Phase 5 Foundation F3/F5 seed migration `0066` (seed-only): populated the `0050` `capabilities` catalogue (54 core keys) + `role_capabilities` (cumulative system.guest/user/moderator/admin) from the code-owned `CapabilityCatalog`, and backfilled `protected_owners` from existing active admins. Deploy-dark behind `capabilities`; no shape change. |
  ```
- Header line 3 — `**Status:** v1.24` → `**Status:** v1.25`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/0066_phase5_seed_capabilities_owners.php tests/Integration/Core/AppPhase5CapabilitySeedTest.php SCHEMA.md
git commit -m "feat(phase5): seed capabilities/roles/protected_owners via 0066 (F3+F5)

Seed-only, deploy-dark. Populates the empty 0050 catalogue + role map from
CapabilityCatalog and backfills protected_owners from active admins.
SCHEMA.md -> v1.25.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `ProtectedOwnerRepository` (F5)

**Files:**
- Create: `src/Repository/ProtectedOwnerRepository.php`
- Test: `tests/Integration/Repository/ProtectedOwnerRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`.
- Produces:
  - `hasAnyActiveOwner(): bool`
  - `isActiveOwner(int $userId): bool`
  - `activeOwnerCountExcluding(int $userId): int`
  - `designate(int $userId, ?int $designatedBy = null): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ProtectedOwnerRepository;
use Tests\Support\TestCase;

final class ProtectedOwnerRepositoryTest extends TestCase
{
    public function test_designate_and_query_active_owners(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_a'])['id'];
        $b = (int) $this->makeAdmin(['username' => 'owner_b'])['id'];

        self::assertFalse($repo->hasAnyActiveOwner());
        self::assertFalse($repo->isActiveOwner($a));

        self::assertTrue($repo->designate($a, null));
        self::assertTrue($repo->hasAnyActiveOwner());
        self::assertTrue($repo->isActiveOwner($a));
        self::assertFalse($repo->isActiveOwner($b));

        // With only A designated, excluding A leaves zero other active owners.
        self::assertSame(0, $repo->activeOwnerCountExcluding($a));

        $repo->designate($b, $a);
        self::assertSame(1, $repo->activeOwnerCountExcluding($a));
    }

    public function test_designate_is_idempotent_on_the_unique_user(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_dup'])['id'];

        self::assertTrue($repo->designate($a, null));
        self::assertFalse($repo->designate($a, null), 'second designation is a no-op (INSERT IGNORE)');
        self::assertSame(0, $repo->activeOwnerCountExcluding($a));
    }
}
```

*(Harness facts, verified against `tests/Support/TestCase.php`: `makeAdmin()`/`makeUser()` return the **users row as an `array`** — use `['id']`, not `->id()`; the seeded password is `password123`; `Database`'s single-row method is `fetch()` (not `fetchOne`), alongside `fetchValue()`/`fetchAll()`/`run()`/`transaction()`. Build a `User` object with `$this->userEntity($row)` when a signature needs one.)*

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/ProtectedOwnerRepositoryTest.php`
Expected: FAIL — `Error: Class "App\Repository\ProtectedOwnerRepository" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Repository/ProtectedOwnerRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Thin wrapper over `protected_owners` (Foundation F5). Backs the "≥1 active
 * recoverable owner" invariant (decision #27) consumed by LastOwnerGuard and
 * RepairService. Returns scalars/bools; all business logic lives in the guard.
 */
final class ProtectedOwnerRepository
{
    public function __construct(private Database $db)
    {
    }

    public function hasAnyActiveOwner(): bool
    {
        return (int) $this->db->fetchValue('SELECT EXISTS(SELECT 1 FROM protected_owners WHERE is_active = 1)') === 1;
    }

    public function isActiveOwner(int $userId): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT EXISTS(SELECT 1 FROM protected_owners WHERE user_id = ? AND is_active = 1)',
            [$userId],
        ) === 1;
    }

    public function activeOwnerCountExcluding(int $userId): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM protected_owners WHERE is_active = 1 AND user_id <> ?',
            [$userId],
        );
    }

    public function designate(int $userId, ?int $designatedBy = null): bool
    {
        return $this->db->run(
            'INSERT IGNORE INTO protected_owners (user_id, is_active, designated_by, designated_at, created_at)
             VALUES (?, 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$userId, $designatedBy],
        )->rowCount() > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Repository/ProtectedOwnerRepositoryTest.php`
Expected: PASS (2 tests). (`Database` exposes `fetchValue()` / `fetch()` / `fetchAll()` / `run()` / `transaction()` — the same methods `RepairService`, `AccountLifecycleService`, and `UserRepository` use.)

- [ ] **Step 5: Commit**

```bash
git add src/Repository/ProtectedOwnerRepository.php tests/Integration/Repository/ProtectedOwnerRepositoryTest.php
git commit -m "feat(phase5): add ProtectedOwnerRepository (F5)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: `RepairService::repairProtectedOwners()` reconcile (F5)

**Files:**
- Modify: `src/Service/RepairService.php` (add method + include in `repairAll()`)
- Modify: `bin/console` (call it in the `repair` command; ~:133-136 block)
- Test: `tests/Integration/Service/RepairProtectedOwnersTest.php`

**Interfaces:**
- Consumes: `App\Core\Database`.
- Produces: `RepairService::repairProtectedOwners(): int` (rows designated; 0 when already satisfied or no admins), and a `'protected_owners'` key in `repairAll()`'s return array.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\ProtectedOwnerRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

final class RepairProtectedOwnersTest extends TestCase
{
    public function test_designates_an_owner_when_admins_exist_but_none_designated(): void
    {
        $admin = $this->makeAdmin(['username' => 'repair_admin']);
        $repo = new ProtectedOwnerRepository($this->db);
        self::assertFalse($repo->hasAnyActiveOwner());

        $changed = (new RepairService($this->db))->repairProtectedOwners();
        self::assertSame(1, $changed);
        self::assertTrue($repo->isActiveOwner((int) $admin['id']));
    }

    public function test_is_idempotent_once_an_owner_exists(): void
    {
        $this->makeAdmin(['username' => 'repair_admin2']);
        $svc = new RepairService($this->db);
        self::assertSame(1, $svc->repairProtectedOwners());
        self::assertSame(0, $svc->repairProtectedOwners(), 'second pass is a no-op');
    }

    public function test_no_op_when_no_active_admin_exists(): void
    {
        // Non-admins present, no admin — nothing to designate.
        $this->makeUser(['username' => 'repair_plain']);
        self::assertSame(0, (new RepairService($this->db))->repairProtectedOwners());
        self::assertFalse((new ProtectedOwnerRepository($this->db))->hasAnyActiveOwner());
    }

    public function test_repair_all_includes_protected_owners(): void
    {
        $this->makeAdmin(['username' => 'repair_all_admin']);
        $out = (new RepairService($this->db))->repairAll();
        self::assertArrayHasKey('protected_owners', $out);
    }
}
```

*(The suite seeds an admin per test; the default `TestCase` may already create one in some flows — if `hasAnyActiveOwner()` is unexpectedly true at the start, assert `>=0`/`>=1` deltas instead of exact `1`. Prefer the explicit `makeAdmin` above so the count is deterministic within the test transaction.)*

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/RepairProtectedOwnersTest.php`
Expected: FAIL — `Error: Call to undefined method App\Service\RepairService::repairProtectedOwners()`.

- [ ] **Step 3: Write the implementation**

In `src/Service/RepairService.php`, add the method (before `repairAll()`):

```php
    /**
     * Ensure the "≥1 active recoverable owner" invariant (decision #27) is
     * satisfiable: if any active admin exists but no active protected owner is
     * designated, designate the earliest active admin. Idempotent — a no-op once
     * an owner exists or when there is no admin (fresh install pre-setup).
     * @return int rows inserted
     */
    public function repairProtectedOwners(): int
    {
        if ((int) $this->db->fetchValue('SELECT EXISTS(SELECT 1 FROM protected_owners WHERE is_active = 1)') === 1) {
            return 0;
        }
        $adminId = $this->db->fetchValue(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1",
        );
        if ($adminId === null) {
            return 0;
        }
        return $this->db->run(
            'INSERT IGNORE INTO protected_owners (user_id, is_active, designated_by, designated_at, created_at)
             VALUES (?, 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [(int) $adminId],
        )->rowCount();
    }
```

Then add it to the `repairAll()` array (inside the existing `$this->db->transaction(...)`), after `'thread_statuses'`:

```php
                'thread_statuses' => $this->repairThreadStatuses(),
                'protected_owners' => $this->repairProtectedOwners(),
                'reputation' => $this->repairReputation(),
```

In `bin/console`, in the granular `repair` block (near the existing `$svc->repairBoardCounters();` at ~:133-136), add:

```php
            $svc->repairProtectedOwners();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Service/RepairProtectedOwnersTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Service/RepairService.php bin/console tests/Integration/Service/RepairProtectedOwnersTest.php
git commit -m "feat(phase5): add repairProtectedOwners reconcile for the owner invariant (F5)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: `LastOwnerGuard` (F5)

**Files:**
- Create: `src/Security/LastOwnerGuard.php`
- Test: `tests/Integration/Core/AppProtectedOwnerTest.php` (guard-direct cases; the wired-path cases arrive in Task 7 — this file grows)

**Interfaces:**
- Consumes: `ProtectedOwnerRepository`, `UserRepository::activeAdminCountExcluding(int): int`, `App\Domain\User`, `App\Core\ValidationException`.
- Produces: `LastOwnerGuard::assertNotLastOwner(User $user, string $field = 'account'): void` — throws `ValidationException` when the action would drop below one active recoverable owner.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\ValidationException;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\LastOwnerGuard;
use Tests\Support\TestCase;

final class AppProtectedOwnerTest extends TestCase
{
    private function guard(): LastOwnerGuard
    {
        return new LastOwnerGuard(new ProtectedOwnerRepository($this->db), new UserRepository($this->db));
    }

    public function test_parity_fallback_blocks_removing_the_last_admin_when_owner_set_is_empty(): void
    {
        // No protected_owners rows -> defer to the legacy last-active-admin rule.
        $onlyAdmin = $this->userEntity($this->makeAdmin(['username' => 'solo_admin']));
        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwner($onlyAdmin, 'current_password');
    }

    public function test_parity_fallback_allows_removal_when_another_active_admin_exists(): void
    {
        $a = $this->userEntity($this->makeAdmin(['username' => 'parity_a']));
        $this->makeAdmin(['username' => 'parity_b']);
        $this->guard()->assertNotLastOwner($a, 'current_password'); // no throw
        $this->addToAssertionCount(1);
    }

    public function test_owner_set_blocks_removing_the_last_active_owner(): void
    {
        $aRow = $this->makeAdmin(['username' => 'owner_only']);
        $this->makeAdmin(['username' => 'admin_not_owner']); // an admin, but NOT a designated owner
        (new ProtectedOwnerRepository($this->db))->designate((int) $aRow['id'], null);

        // Owner set is populated and A is the sole active owner -> blocked, even
        // though another admin exists (owners, not admins, are the authority now).
        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwner($this->userEntity($aRow), 'current_password');
    }

    public function test_owner_set_allows_removal_when_another_active_owner_exists(): void
    {
        $aRow = $this->makeAdmin(['username' => 'owner_a2']);
        $bRow = $this->makeAdmin(['username' => 'owner_b2']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $aRow['id'], null);
        $repo->designate((int) $bRow['id'], (int) $aRow['id']);

        $this->guard()->assertNotLastOwner($this->userEntity($aRow), 'current_password'); // no throw
        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppProtectedOwnerTest.php`
Expected: FAIL — `Error: Class "App\Security\LastOwnerGuard" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Security/LastOwnerGuard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\UserRepository;

/**
 * The shared "≥1 active recoverable owner" invariant (decision #27, Foundation
 * F5). Consulted by every owner-loss path: role revoke/demote (Inc 6), passkey
 * removal (Inc 7), sole-provider unlink (Inc 8), invitation (Inc 9), and the
 * account-lifecycle delete/deactivate path (wired now, Task 7).
 *
 * Parity-safe: while `protected_owners` is unseeded (Foundation/dark, or a fresh
 * install pre-setup) it defers to the legacy last-active-admin rule so live
 * behavior is identical to today. Once the owner set is populated it enforces
 * the owner invariant directly.
 */
final class LastOwnerGuard
{
    public function __construct(
        private ProtectedOwnerRepository $owners,
        private UserRepository $users,
    ) {
    }

    /**
     * @param string $field the form field to attach the error to (so callers can
     *                       re-render 422 with the anti-draft-loss pattern).
     * @throws ValidationException when the action would remove the last owner.
     */
    public function assertNotLastOwner(User $user, string $field = 'account'): void
    {
        if (!$this->owners->hasAnyActiveOwner()) {
            // Legacy parity: block only when this is the last active admin.
            if ($user->isAdmin() && $this->users->activeAdminCountExcluding($user->id()) === 0) {
                throw new ValidationException([$field => 'Add another active admin before removing the last one.']);
            }
            return;
        }

        if ($this->owners->isActiveOwner($user->id())
            && $this->owners->activeOwnerCountExcluding($user->id()) === 0) {
            throw new ValidationException([$field => 'Designate another site owner before removing the last one.']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Core/AppProtectedOwnerTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Security/LastOwnerGuard.php tests/Integration/Core/AppProtectedOwnerTest.php
git commit -m "feat(phase5): add shared LastOwnerGuard invariant (F5)

Parity-safe: defers to the legacy last-admin rule while the owner set is
unseeded; enforces the owner invariant once populated.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: Wire `LastOwnerGuard` into the account-lifecycle path behind `capabilities` (F5)

**Files:**
- Modify: `src/Core/App.php` (`buildContainer` — bind `ProtectedOwnerRepository`, `LastOwnerGuard`; pass the guard into `AccountLifecycleService` only when `capabilities` is on)
- Modify: `src/Service/AccountLifecycleService.php` (nullable 8th ctor arg; consult it in `deactivate()`/`requestDeletion()`)
- Test: `tests/Integration/Core/AppProtectedOwnerTest.php` (append the wired-path cases)

**Interfaces:**
- Consumes: `LastOwnerGuard::assertNotLastOwner()`, `FeatureFlags::enabled('capabilities')`.
- Produces: the guard is consulted on account deactivate/delete-request **iff `capabilities` is on** (else `null` → today's behavior unchanged). The four other owner-loss paths remain documented future hooks.

- [ ] **Step 1: Write the failing test (append to `AppProtectedOwnerTest`)**

```php
    public function test_capabilities_dark_leaves_account_lifecycle_behavior_unchanged(): void
    {
        // With capabilities dark (default), the guard is not wired: the legacy
        // last-admin rule alone governs, so a lone admin is blocked by the
        // existing check — proving Foundation added no new live behavior.
        $this->setFlags(['account_lifecycle' => true]); // capabilities stays dark
        $admin = $this->makeAdmin(['username' => 'dark_lone_admin']);
        $this->actingAs($admin);

        // Sole admin cannot deactivate (legacy assertNotFinalActiveAdmin fires) -> 422.
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    }

    public function test_capabilities_on_enforces_owner_invariant_on_deactivate(): void
    {
        // Two admins (legacy last-admin rule passes), but only A is a designated
        // owner. With capabilities on, LastOwnerGuard blocks A's deactivation.
        $this->setFlags(['account_lifecycle' => true, 'capabilities' => true]);
        $a = $this->makeAdmin(['username' => 'wired_owner_a']);
        $b = $this->makeAdmin(['username' => 'wired_admin_b']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $a['id'], null);

        $this->actingAs($a);
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));

        // Designate B too -> A is no longer the last owner -> allowed (redirect).
        $repo->designate((int) $b['id'], (int) $a['id']);
        $this->assertRedirect($this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }
```

*(All three harness facts are already handled above: `makeAdmin()` returns the row array (use `['id']`), the seeded password is `password123`, and `setFlags()` is defined inline via `SettingRepository::set('features', …)` — the same mechanism `AppFeatureFlagTest` uses. `assertRedirect()` accepts any 3xx, so the success path doesn't hard-code the deactivate redirect target.)*

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppProtectedOwnerTest.php --filter test_capabilities_on_enforces_owner_invariant_on_deactivate`
Expected: FAIL — the second `deactivate` still 422s (or the guard isn't consulted) because `AccountLifecycleService` doesn't yet accept/consult `LastOwnerGuard`.

- [ ] **Step 3: Wire the service**

In `src/Service/AccountLifecycleService.php`, add the import and the nullable ctor arg:

```php
use App\Security\LastOwnerGuard;
use App\Security\PasswordHasher;
```

```php
        private ServerDraftRepository $serverDrafts,
        private PasswordHasher $hasher,
        private ?LastOwnerGuard $ownerGuard = null,
    ) {
```

In `deactivate()` and `requestDeletion()`, consult the guard right after the existing legacy check (both already call `assertNotFinalActiveAdmin($user)` before the transaction):

```php
        $this->assertNotFinalActiveAdmin($user);
        $this->ownerGuard?->assertNotLastOwner($user, 'current_password');
```

*(Guard runs before `$this->db->transaction(...)`, so a throw makes no partial write. `?->` means dark = unchanged behavior.)*

In `src/Core/App.php` `buildContainer()`, bind the new collaborators (place near the other Phase 5 / repository binds) and inject the guard into the existing `AccountLifecycleService` bind (~:1036) as the 8th argument:

```php
        $c->bind(ProtectedOwnerRepository::class, fn (Container $c) => new ProtectedOwnerRepository(
            $c->get(Database::class),
        ));
        $c->bind(LastOwnerGuard::class, fn (Container $c) => new LastOwnerGuard(
            $c->get(ProtectedOwnerRepository::class),
            $c->get(UserRepository::class),
        ));
```

```php
        $c->bind(AccountLifecycleService::class, fn (Container $c) => new AccountLifecycleService(
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(AccountDeletionRepository::class),
            $c->get(SessionRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(ServerDraftRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(FeatureFlags::class)->enabled('capabilities') ? $c->get(LastOwnerGuard::class) : null,
        ));
```

Add the imports at the top of `App.php` (near the other `use App\...` lines):

```php
use App\Repository\ProtectedOwnerRepository;
use App\Security\LastOwnerGuard;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Core/AppProtectedOwnerTest.php`
Expected: PASS (6 tests) — parity cases, dark-behavior-unchanged, and the capabilities-on owner enforcement.

- [ ] **Step 5: Document the four future hooks**

Append to the class doc-comment of `src/Security/LastOwnerGuard.php` a short "wiring status" note so the deferral is explicit (not a silent omission):

```php
 * Wiring status (Foundation): the account-lifecycle deactivate/delete-request
 * path consults this guard now (behind `capabilities`). The other four paths
 * call it when their subsystems land — role revoke/demote (Increment 6, the
 * resolver's role_assignments), passkey removal (Increment 7), sole-provider
 * unlink (Increment 8, alongside OAuthService::unlink's existing login-method
 * guard), and invitations (Increment 9). Each is a one-line
 * `$guard->assertNotLastOwner($user, $field)` at its mutation site.
```

- [ ] **Step 6: Commit**

```bash
git add src/Core/App.php src/Service/AccountLifecycleService.php src/Security/LastOwnerGuard.php tests/Integration/Core/AppProtectedOwnerTest.php
git commit -m "feat(phase5): consult LastOwnerGuard on account lifecycle behind capabilities (F5)

Dark by default (null guard -> legacy behavior); enforces the owner invariant
when capabilities is on. Documents the four not-yet-built owner-loss hooks.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Foundation exit-gate verification (F3 + F5)

**Files:** none created — this task runs the gate checks the Foundation exit criteria require ("full suite green; `verify:upgrade` passes through the new seeds; `AppFeatureFlagTest` still proves all Phase 5 flags dark").

- [ ] **Step 1: Prove the flags are still dark**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS — including `test_phase5_foundation_flags_default_dark` (asserts `capabilities` dark) and `test_topic_workflow_is_available_by_default_and_can_be_disabled` (unchanged).

- [ ] **Step 2: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: green (prior baseline was **829** tests post-`topic_workflow` graduation; this increment adds the F3/F5 tests). **One required fixup, known in advance:** `tests/Integration/Core/AppPhase5FoundationSchemaTest.php::test_capability_catalogue_is_not_seeded()` (≈line 118) asserts `SELECT COUNT(*) FROM capabilities = 0` with the message "capability catalogue must stay empty until the taxonomy is approved" — the `0066` seed makes that false by design. Rename it to `test_capability_catalogue_is_seeded_by_0066()` and change the assertion to:

```php
        self::assertSame(
            54,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM capabilities'),
            'capability catalogue is seeded by migration 0066 from CapabilityCatalog',
        );
```

(Sanity-check for any other now-stale assertions: `grep -rn "FROM capabilities" tests/`.)

- [ ] **Step 3: Rehearse the populated upgrade (proves the `0066` seed + owner backfill on real data)**

Run: `APP_ENV=testing DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`
Expected: PASS — `0066` applies on seeded data; the capabilities catalogue populates (54); existing admins are backfilled as `protected_owners`; zero data loss. (This is the authoritative proof of the owner backfill that the in-suite bootstrap can't show, since it migrates an empty `users` table.)

- [ ] **Step 4: Rehearse repair convergence**

Run: `DB_DATABASE=${DB_TEST_DATABASE:-retroboards_test} php bin/console repair`
Expected: the summary includes `protected_owners` and re-running is idempotent (0 on the second pass).

- [ ] **Step 5: Commit any test fixups**

```bash
git add -A
git commit -m "test(phase5): reconcile catalogue-empty assertions with the 0066 seed; Foundation gate green

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Status + evidence docs (F3 + F5)

**Files:**
- Modify: `PHASE_5_STATUS.md` (record the Foundation F3/F5 seed as landed; note the requirement-ledger move to R2/R3)
- Create: `docs/evidence/phase5/foundation-f3-f5.md` (the evidence index for this increment)

- [ ] **Step 1: Update `PHASE_5_STATUS.md`**

Under "Landed in this increment" (or a new "Foundation F3/F5" subsection), add bullets: `CapabilityCatalog` (54 keys) + `CapabilityInventoryService` coverage test; `0066` seed of catalogue/role_capabilities/protected_owners; `ProtectedOwnerRepository` + `LastOwnerGuard` + `repairProtectedOwners`; all deploy-dark behind `capabilities`, `AppFeatureFlagTest` still proves dark. In the requirement-ledger snapshot, move capabilities/roles from "R0/R1" to **R2 (implemented behind disabled flag) + R3-partial** for the catalogue/owner spine (the resolver + enforcement remain Increments 1/6).

- [ ] **Step 2: Write the evidence index**

Create `docs/evidence/phase5/foundation-f3-f5.md` listing: the test files + what each proves, the `verify:upgrade` result, the `SCHEMA.md` v1.25 changelog line, and the explicit statement that this is a dark, no-UI increment (no Playwright/axe surface), with the four LastOwnerGuard future hooks named.

- [ ] **Step 3: Commit**

```bash
git add PHASE_5_STATUS.md docs/evidence/phase5/foundation-f3-f5.md
git commit -m "docs(phase5): record Foundation F3/F5 landed + evidence index

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage** (program plan §B rows F3/F5 + §C + Foundation exit gate + the verifiers' 9-item DoD):

| Requirement | Task |
|---|---|
| `CapabilityCatalog.php` (code-owned core.* enum + protected set) | Task 1 |
| `CapabilityInventoryService.php` (route/permission golden matrix) | Task 2 |
| Coverage test: every call site → exactly one catalogued key OR §8 exclusion | Task 2 |
| Every key has scope_type + risk_class + consent | Tasks 1 (code) + 3 (seeded rows) |
| `0066` seeds catalogue + role_capabilities (cumulative guest/user/mod/admin) | Task 3 |
| `protected_owners` seed from existing admins | Task 3 (backfill) + Task 5 (runtime reconcile) |
| `LastOwnerGuard.php` across the five paths, honest re: unbuilt paths | Tasks 6 + 7 (wired: account lifecycle; documented: role/passkey/provider/invitation) |
| `AppProtectedOwnerTest` across the paths | Tasks 6 + 7 |
| Foundation exit gate (flags dark; suite green; `verify:upgrade` through seeds) | Task 8 |
| `SCHEMA.md` hand-update + version bump | Task 3 Step 5 (v1.25) |

**2. Placeholder scan:** the 54-key catalogue, the golden matrix, the migration, the repository, the guard, and the reconcile are all written out in full. The only "transcribe from the taxonomy" instruction (Task 1 Step 3) ships with the complete array *and* a test that pins the count/invariant/consent — not a placeholder. Consent copy for all 49 non-protected keys is authored inline.

**3. Type consistency:** `CapabilityCatalog::all()` returns the keyed-meta shape used identically by `CapabilityCatalogTest`, the `0066` seed, and `AppPhase5CapabilitySeedTest`; `roleCapabilities()` (cumulative) is the single producer of the 1/15/28/49 counts asserted in Tasks 1 and 3; `ProtectedOwnerRepository`'s four methods are consumed unchanged by `LastOwnerGuard` (Task 6), the wiring (Task 7), and `RepairService` uses the same SQL predicate (`is_active = 1`). `assertNotLastOwner(User, string)` has one signature across Tasks 6–7. `LastOwnerGuard` throws `App\Core\ValidationException` (the same type `AccountLifecycleService` already throws and its controller already catches), so the wired path re-renders 422 via the existing anti-draft-loss handling.

**Open judgment calls (flag on execution):**
- The owner backfill designates **all** existing active admins as owners (safe superset); if the owner intends a single designated owner, narrow the `0066` `SELECT` to `ORDER BY id ASC LIMIT 1` and rely on `repairProtectedOwners` for the rest.
- Harness API is pinned (verified against `tests/Support/TestCase.php`): `makeAdmin()`/`makeUser()` return the users **row array** (use `['id']`), the seeded password is `password123`, the single-row fetch is `Database::fetch()`, and `$this->userEntity($row)` builds a `User` where a signature needs one. No open question remains here.
