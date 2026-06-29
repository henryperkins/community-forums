# Admin Announcements (Site Banner + In-App Broadcast) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Phase-2 admin announcements surface — a site-wide dismissible banner (`settings.site_announcement`) plus an opt-in in-app broadcast notification (`notifications.type='announcement'`), gated by a new `announcements` flag, with NO email channel.

**Architecture:** A new `AnnouncementService` owns the `settings.site_announcement` JSON key (active/message/dismissible/version) and, when requested, calls a new set-based `NotificationRepository::broadcastAnnouncement()` — all inside `$db->transaction` with one `moderation_log` audit row (`target_type='setting'`). `App::shareViewGlobals()` defensively reads the banner and shares it; `templates/layout.php` includes a new self-guarding partial after the topbar for both shell variants. A flag-gated `AdminAnnouncementController` renders the compose form and processes set/clear; progressive-enhancement JS in `app.js` remembers per-version dismissal in `localStorage`.

**Tech Stack:** Vanilla PHP 8.2 (`App\` → `src/`, PSR-4), MySQL/MariaDB via `App\Core\Database` (PDO, `EMULATE_PREPARES=false`), plain-PHP templates, PE JavaScript in `public/assets/app.js`, PHPUnit (`Tests\Support\TestCase` cookie-jar kernel), Playwright evidence (`tests/browser`).

## Global Constraints

This plan is bound by the shared contract at `docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md` — incorporated here by reference (locked decisions, route/flag/nav/audit allocation, idioms, execution order). It executes as **Group C** (third, after A reorder/archive… actually after A badges/titles and B board-structure; on its own branch with `composer test` green before D). Group-specific constraints:

- **NO EMAIL CHANNEL.** Do **not** build an email broadcast and do **not** touch `src/Worker/NotificationEmailWorker.php`. The in-app broadcast reuses `notifications.type='announcement'` (the enum already includes it, `database/migrations/0021_notifications.php:20`). The banner lives in `settings.site_announcement`. **No migration** — every surface is schema-ready (verified: notifications enum has `announcement`; `settings` is generic key/value).
- **Flag:** add `'announcements' => true` to `FeatureFlags::DEFAULTS` (Phase-2 block, default ON per convention). Every route action calls `gate()` → `NotFoundException` (404) when off. `AppFeatureFlagTest` asserts the key exists and the routes go dark.
- **Authority + audit:** `requireAdmin()` on every action; `AnnouncementService` mirrors `AdminService::assertAdmin()` (isAdmin **and** `WriteGate::assertCanWrite` so a suspended admin is blocked — state beats role). Every mutation writes **one** `ModerationLogRepository::log([...])` row with `target_type='setting'` (mirroring `AdminService::updateModerationSettings`).
- **CSRF** on every POST (`<?= $this->csrfField() ?>`); no mutating GET.
- **Anti-draft-loss:** `save()` catches `App\Core\ValidationException` and re-renders the form at **422** with `$e->errors` + `$e->old` (the empty-message case).
- **Rate limit:** add `rate_limits['announce'] = [5, 3600]` to `config/config.php`; the controller enforces it via `RateLimitService::enforce('announce', $request, $admin)` before publishing (per the webhooks idiom — services here never take a `Request`). Throws HTTP 429 (kernel-handled).
- **CSP / PE:** no inline `<script>`/`<style>`; the banner works server-rendered (visible without JS); dismissal is PE-only via `data-*` hooks in `public/assets/app.js`.
- **DB rules:** `EMULATE_PREPARES=false` — the broadcast `INSERT … SELECT` never reuses a placeholder (two names `:actor`/`:exclude` for the same value); `UTC_TIMESTAMP()` for time; the multi-table mutation (settings + notifications + audit) runs inside `$db->transaction`. No denormalized counters touched; no `RepairService` hook.
- **Tests:** strict PHPUnit (`failOnWarning`/`failOnRisky`, ≥1 assertion/test). Assert observable HTTP behavior; `moderation_log` / `notifications` row counts are asserted via `$this->db->fetchValue('SELECT COUNT(*) …')` only where the production path commits on the same connection (nested `$db->transaction` reuses the test's transaction — `Database::transaction` has no savepoints; rows are visible to the same connection before tearDown rollback, exactly as `AppFeatureFlagTest::test_group_dms…` asserts `COUNT(*) FROM conversations` and the webhooks tests assert audit rows).

---

### Task 1: Set-based broadcast insert — `NotificationRepository::broadcastAnnouncement()`

**Files:**
- Modify: `src/Repository/NotificationRepository.php` (add a method after `create()`, ~line 41)
- Test: `tests/Integration/Repository/NotificationBroadcastTest.php` (new)

**Interfaces:**
- Produces: `public function broadcastAnnouncement(int $actorId): int` — one `INSERT … SELECT` creating a `type='announcement'` row for every `users.status='active'` member except `$actorId`; returns the recipient count (`PDOStatement::rowCount()`).
- Consumes: `App\Core\Database::run(string, array): \PDOStatement` (`src/Core/Database.php:65`).

Steps:

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\NotificationRepository;
use Tests\Support\TestCase;

final class NotificationBroadcastTest extends TestCase
{
    public function test_broadcast_inserts_for_active_users_excluding_actor_and_inactive(): void
    {
        $actor = $this->makeUser(['username' => 'bcactor']);
        $alice = $this->makeUser(['username' => 'bcalice']);
        $bob = $this->makeUser(['username' => 'bcbob']);
        $banned = $this->makeUser(['username' => 'bcbanned', 'status' => 'banned']);
        $suspended = $this->makeUser(['username' => 'bcsusp', 'status' => 'suspended']);

        $count = (new NotificationRepository($this->db))->broadcastAnnouncement((int) $actor['id']);

        self::assertSame(2, $count, 'only the two active non-actor members are notified');

        // Each active non-actor member gets exactly one row…
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $alice['id']],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $bob['id']],
        ));
        // …the actor and inactive accounts get none.
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id IN (?, ?, ?)",
            [(int) $actor['id'], (int) $banned['id'], (int) $suspended['id']],
        ));

        // Rows carry no body/thread — they signal "see the banner".
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND (thread_id IS NOT NULL OR post_id IS NOT NULL)",
        ));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter test_broadcast_inserts_for_active_users_excluding_actor_and_inactive` → fails with `Error: Call to undefined method App\Repository\NotificationRepository::broadcastAnnouncement()`.

- [ ] **Step 3: Minimal implementation** — add to `src/Repository/NotificationRepository.php` immediately after the `create()` method (after its closing `}` near line 41):

```php
    /**
     * Site-wide announcement broadcast (ADMIN §7.4; SCHEMA §7 #13). One set-based
     * INSERT creates a type='announcement' notification for every active member
     * except the actor. The row carries no body — it signals "see the site
     * banner"; notifications.php renders the 'Announcement' label and the click
     * target resolves to '/'. EMULATE_PREPARES=false: the actor id is bound under
     * two distinct names so no placeholder is reused.
     *
     * @return int the number of recipients notified
     */
    public function broadcastAnnouncement(int $actorId): int
    {
        return $this->db->run(
            "INSERT INTO notifications (user_id, type, actor_id, is_read, created_at)
             SELECT u.id, 'announcement', :actor, 0, UTC_TIMESTAMP()
             FROM users u
             WHERE u.status = 'active' AND u.id <> :exclude",
            ['actor' => $actorId, 'exclude' => $actorId],
        )->rowCount();
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit --filter test_broadcast_inserts_for_active_users_excluding_actor_and_inactive`.

- [ ] **Step 5: Commit** — `git add src/Repository/NotificationRepository.php tests/Integration/Repository/NotificationBroadcastTest.php && git commit -m "Add set-based announcement broadcast to NotificationRepository (Group C)"`

---

### Task 2: Feature flag `announcements` + `rate_limits['announce']`

**Files:**
- Modify: `src/Core/FeatureFlags.php` (DEFAULTS, Phase-2 block, ~line 36)
- Modify: `config/config.php` (`rate_limits`, ~line 203)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (append one method)

**Interfaces:**
- Produces: `FeatureFlags::DEFAULTS['announcements'] = true`; `config('rate_limits.announce') = [5, 3600]`.
- Consumes: `FeatureFlags::all(): array<string,bool>` (`src/Core/FeatureFlags.php:98`); `Config::get()` via `$this->config`.

Steps:

- [ ] **Step 1: Write the failing test** — append this method to the `AppFeatureFlagTest` class (before its closing `}`):

```php
    public function test_announcements_flag_and_rate_limit_are_declared(): void
    {
        // The announcements subsystem is a Phase-2 surface: declared + default ON.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('announcements', $flags->all(), 'announcements must be a declared flag, not an unknown-key false');
        self::assertTrue($flags->enabled('announcements'), 'announcements defaults on (Phase-2 convention)');

        // The broadcast cap needs a real policy (RateLimitService no-ops on unknown names).
        $limits = (array) $this->config->get('rate_limits', []);
        self::assertArrayHasKey('announce', $limits);
        self::assertCount(2, (array) $limits['announce']);
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter test_announcements_flag_and_rate_limit_are_declared` → fails: `Failed asserting that an array has the key 'announcements'`.

- [ ] **Step 3: Minimal implementation**

In `src/Core/FeatureFlags.php`, add to the Phase-2 block of `DEFAULTS` (after the `'presence' => true,` line, before the `// ── Phase 3 (Gate A) ──` comment):

```php
        'announcements' => true,     // admin site banner + opt-in in-app broadcast (ADMIN §7.4; SCHEMA §7 #13)
```

In `config/config.php`, add to the `rate_limits` array (after the `'webhook_test' => [20, 600],` line):

```php
        'announce' => [5, 3600],   // admin announcement publishes per admin (ADMIN §7.4)
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit --filter test_announcements_flag_and_rate_limit_are_declared`.

- [ ] **Step 5: Commit** — `git add src/Core/FeatureFlags.php config/config.php tests/Integration/Core/AppFeatureFlagTest.php && git commit -m "Declare announcements flag + announce rate limit (Group C)"`

---

### Task 3: `AnnouncementService` (set/clear banner, audit, broadcast) + container binding

**Files:**
- Create: `src/Service/AnnouncementService.php`
- Modify: `src/Core/App.php` (add `use App\Service\AnnouncementService;` ~line 106 area; bind in `buildContainer()` after the `NotificationService` bind ~line 678)
- Test: `tests/Integration/Service/AnnouncementServiceTest.php` (new)

**Interfaces:**
- Produces:
  - `public function setBanner(User $admin, string $message, bool $dismissible, bool $inAppBroadcast): void`
  - `public function clearBanner(User $admin): void`
- Consumes: `Database::transaction()`; `SettingRepository::get()/set()` (`src/Repository/SettingRepository.php:18,34`); `ModerationLogRepository::log()` (`:24`); `NotificationRepository::broadcastAnnouncement()` (Task 1); `WriteGate::assertCanWrite(User)` (`src/Security/WriteGate.php:22`); `App\Core\ValidationException` (`:14`); `App\Core\ForbiddenException`; `App\Domain\User::id()/isAdmin()`.

Steps:

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Repository\ModerationLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;
use App\Service\AnnouncementService;
use Tests\Support\TestCase;

final class AnnouncementServiceTest extends TestCase
{
    private function service(): AnnouncementService
    {
        return new AnnouncementService(
            $this->db,
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new NotificationRepository($this->db),
            new WriteGate(),
        );
    }

    public function test_set_banner_persists_active_announcement_and_audits(): void
    {
        $admin = $this->makeAdmin(['username' => 'annsvcadmin']);
        $this->service()->setBanner($this->userEntity($admin), 'Welcome to the new release', true, false);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertIsArray($stored);
        self::assertTrue((bool) ($stored['active'] ?? false));
        self::assertSame('Welcome to the new release', $stored['message'] ?? null);
        self::assertTrue((bool) ($stored['dismissible'] ?? false));
        self::assertSame(1, (int) ($stored['version'] ?? 0));

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'set_announcement' AND target_type = 'setting'",
        ));
    }

    public function test_version_increments_on_each_publish(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annveradmin']));
        $this->service()->setBanner($admin, 'First', false, false);
        $this->service()->setBanner($admin, 'Second', false, false);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertSame(2, (int) ($stored['version'] ?? 0));
    }

    public function test_broadcast_creates_announcement_rows_excluding_actor(): void
    {
        $admin = $this->makeAdmin(['username' => 'annbcadmin']);
        $reader = $this->makeUser(['username' => 'annreader']);

        $this->service()->setBanner($this->userEntity($admin), 'All hands at noon', false, true);

        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $reader['id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement' AND user_id = ?",
            [(int) $admin['id']],
        ));
    }

    public function test_no_broadcast_when_flag_off(): void
    {
        $admin = $this->makeAdmin(['username' => 'annnobcadmin']);
        $this->makeUser(['username' => 'annnobcreader']);

        $this->service()->setBanner($this->userEntity($admin), 'Banner only', false, false);

        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE type = 'announcement'",
        ));
    }

    public function test_empty_message_is_rejected(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annemptyadmin']));
        $this->expectException(ValidationException::class);
        $this->service()->setBanner($admin, '   ', false, false);
    }

    public function test_clear_deactivates_banner_and_audits(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['username' => 'annclearsvc']));
        $this->service()->setBanner($admin, 'Temporary', true, false);
        $this->service()->clearBanner($admin);

        $stored = (new SettingRepository($this->db))->get('site_announcement', []);
        self::assertFalse((bool) ($stored['active'] ?? true));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'clear_announcement' AND target_type = 'setting'",
        ));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Service/AnnouncementServiceTest.php` → fails: `Error: Class "App\Service\AnnouncementService" not found`.

- [ ] **Step 3: Minimal implementation**

Create `src/Service/AnnouncementService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;

/**
 * Admin announcements (ADMIN §7.4, PHASE_2_PLAN §7; SCHEMA §7 #13). Owns the
 * site-wide banner stored in settings.site_announcement — a JSON key carrying an
 * active flag, message, dismissible flag and an incrementing version — plus an
 * opt-in in-app broadcast. NO email channel and NO `announcements` table: the
 * broadcast reuses notifications.type='announcement'. The version increments on
 * every publish so a member's per-version dismissal never hides a newer banner.
 */
final class AnnouncementService
{
    private const MAX_MESSAGE = 500;

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private NotificationRepository $notifications,
        private WriteGate $writeGate,
    ) {
    }

    public function setBanner(User $admin, string $message, bool $dismissible, bool $inAppBroadcast): void
    {
        $this->assertAdmin($admin);

        $message = trim($message);
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE) {
            throw new ValidationException(
                ['message' => 'Announcement message must be 1–' . self::MAX_MESSAGE . ' characters.'],
                ['message' => $message, 'dismissible' => $dismissible, 'broadcast' => $inAppBroadcast],
            );
        }

        $version = $this->currentVersion() + 1;

        $this->db->transaction(function () use ($admin, $message, $dismissible, $inAppBroadcast, $version): void {
            $this->settings->set('site_announcement', [
                'active' => true,
                'message' => $message,
                'dismissible' => $dismissible,
                'version' => $version,
            ]);
            if ($inAppBroadcast) {
                $this->notifications->broadcastAnnouncement($admin->id());
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'set_announcement',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'site_announcement',
                'after' => ['active' => true, 'version' => $version, 'broadcast' => $inAppBroadcast],
            ]);
        });
    }

    public function clearBanner(User $admin): void
    {
        $this->assertAdmin($admin);
        $version = $this->currentVersion();

        $this->db->transaction(function () use ($admin, $version): void {
            // Preserve the version so it never decreases across publish/clear cycles.
            $this->settings->set('site_announcement', ['active' => false, 'version' => $version]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'clear_announcement',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'site_announcement',
                'after' => ['active' => false],
            ]);
        });
    }

    private function currentVersion(): int
    {
        $current = $this->settings->get('site_announcement', []);
        if (is_array($current) && isset($current['version']) && is_numeric($current['version'])) {
            return (int) $current['version'];
        }
        return 0;
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        // State beats role: a suspended/banned admin cannot publish.
        $this->writeGate->assertCanWrite($admin);
    }
}
```

In `src/Core/App.php`, add the import alongside the other `App\Service\…` uses (e.g. after `use App\Service\AdminService;` at line 106):

```php
use App\Service\AnnouncementService;
```

And register the binding in `buildContainer()` immediately after the `NotificationService` bind (which ends at line 678):

```php
        $c->bind(AnnouncementService::class, fn (Container $c) => new AnnouncementService(
            $c->get(Database::class),
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(NotificationRepository::class),
            $c->get(WriteGate::class),
        ));
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Service/AnnouncementServiceTest.php`.

- [ ] **Step 5: Commit** — `git add src/Service/AnnouncementService.php src/Core/App.php tests/Integration/Service/AnnouncementServiceTest.php && git commit -m "Add AnnouncementService (banner + opt-in broadcast + audit) (Group C)"`

---

### Task 4: Defensive shell read + banner partial + layout slot

**Files:**
- Modify: `src/Core/App.php` (`shareViewGlobals()` — add a defensive read before the `share([...])` call ~line 393, and the `'site_announcement'` key inside `share()`)
- Create: `templates/partials/announcement_banner.php`
- Modify: `templates/layout.php` (include the partial after the topbar partial, line 41)
- Test: `tests/Integration/Core/AppAnnouncementBannerTest.php` (new)

**Interfaces:**
- Produces: View global `site_announcement` (`?array{active:bool,message:string,dismissible:bool,version:int}`, default `null`); rendered banner with `data-announcement`, `data-announcement-version`, `data-dismissible` hooks.
- Consumes: `SettingRepository::get('site_announcement', null)`; `View::partial()` (`src/Core/View.php:70`); the `$e` escape closure + shared globals available in partials via `extract($this->shared)` (`src/Core/View.php:89`).

Steps:

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppAnnouncementBannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist so the setup gate treats the app as initialized
        // and '/' renders the shell instead of redirecting to /setup.
        $this->makeAdmin(['username' => 'bannerinit']);
    }

    private function setBanner(array $value): void
    {
        (new SettingRepository($this->db))->set('site_announcement', $value);
    }

    public function test_active_banner_renders_in_shell_for_guest_and_member(): void
    {
        $this->setBanner(['active' => true, 'message' => 'Scheduled maintenance tonight', 'dismissible' => true, 'version' => 3]);

        $guest = $this->get('/');
        $this->assertStatus(200, $guest);
        $this->assertSeeText($guest, 'Scheduled maintenance tonight');
        $this->assertSeeText($guest, 'data-announcement-version="3"');

        $member = $this->makeUser(['username' => 'bannermember']);
        $this->actingAs($member);
        $signedIn = $this->get('/');
        $this->assertStatus(200, $signedIn);
        $this->assertSeeText($signedIn, 'Scheduled maintenance tonight');
    }

    public function test_inactive_banner_is_not_rendered(): void
    {
        $this->setBanner(['active' => false, 'version' => 1]);
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, 'site-announcement-message');
    }

    public function test_malformed_announcement_value_never_500s_the_shell(): void
    {
        // A garbled value (not an array) must default to null, not break the shell.
        $this->setBanner('not-an-array');
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, 'site-announcement-message');
    }

    public function test_message_is_html_escaped_in_the_banner(): void
    {
        $this->setBanner(['active' => true, 'message' => 'Heads up <script>x</script>', 'dismissible' => false, 'version' => 1]);
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, '<script>x</script>');
        $this->assertSeeText($resp, 'Heads up &lt;script&gt;');
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Core/AppAnnouncementBannerTest.php` → fails: the banner text / `data-announcement-version` is absent from the shell (the partial + share key do not exist yet).

- [ ] **Step 3: Minimal implementation**

In `src/Core/App.php` `shareViewGlobals()`, insert this block immediately before the `$container->get(View::class)->share([` call (after the `$needsTour` try/catch that ends ~line 391):

```php
        // Site announcement banner (ADMIN §7.4; SCHEMA §7 #13): a defensive read
        // so the global shell can show an operator notice. Its own try/catch keeps
        // a missing or garbled settings row from 500ing the shell (it renders
        // pre-setup and against an un-migrated DB).
        $siteAnnouncement = null;
        try {
            $value = $container->get(SettingRepository::class)->get('site_announcement', null);
            if (is_array($value) && !empty($value['active'])) {
                $siteAnnouncement = $value;
            }
        } catch (Throwable) {
            $siteAnnouncement = null;
        }
```

Then add the key inside the `share([...])` array (after `'needs_tour' => $needsTour,`):

```php
            'site_announcement' => $siteAnnouncement,
```

Create `templates/partials/announcement_banner.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$ann = $site_announcement ?? null;
if (!is_array($ann) || empty($ann['active'])) {
    return;
}
$message = (string) ($ann['message'] ?? '');
$dismissible = !empty($ann['dismissible']);
$version = (int) ($ann['version'] ?? 0);
?>
<div class="site-announcement" role="status"
     data-announcement
     data-announcement-version="<?= $version ?>"
     data-dismissible="<?= $dismissible ? '1' : '0' ?>">
    <p class="site-announcement-message"><?= $e($message) ?></p>
    <?php if ($dismissible): ?>
        <button type="button" class="site-announcement-dismiss" data-announcement-dismiss aria-label="Dismiss announcement">&times;</button>
    <?php endif; ?>
</div>
```

In `templates/layout.php`, insert immediately after the topbar partial (line 41, `<?= $this->partial('partials/topbar') ?>`), before the `variant` branch — so it shows for BOTH `variant=app` and `variant=plain`:

```php
<?php if (is_array($site_announcement ?? null) && !empty($site_announcement['active'])): ?>
<?= $this->partial('partials/announcement_banner') ?>
<?php endif; ?>
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Core/AppAnnouncementBannerTest.php`.

- [ ] **Step 5: Commit** — `git add src/Core/App.php templates/partials/announcement_banner.php templates/layout.php tests/Integration/Core/AppAnnouncementBannerTest.php && git commit -m "Render site announcement banner in the global shell (Group C)"`

---

### Task 5: `AdminAnnouncementController` + routes + admin form + nav + notification link

**Files:**
- Create: `src/Controller/AdminAnnouncementController.php`
- Create: `templates/admin/announcements.php`
- Modify: `src/Core/App.php` (`use App\Controller\AdminAnnouncementController;` ~line 8 area; register two routes in `buildRouter()` after the `/admin/webhooks/...` block, before `/admin/structure`, ~line 1084)
- Modify: `templates/admin/dashboard.php` (subnav, after the Webhooks link, line 13)
- Modify: `src/Controller/NotificationController.php` (`resolveTarget()` — return `/` for `announcement`, after the `mod` branch ~line 119)
- Test: `tests/Integration/Admin/AdminAnnouncementTest.php` (new) + append one method to `tests/Integration/Core/AppFeatureFlagTest.php`

**Interfaces:**
- Produces:
  - `AdminAnnouncementController::form(Request, array): Response` (GET `/admin/announcements`)
  - `AdminAnnouncementController::save(Request, array): Response` (POST `/admin/announcements`)
- Consumes: `Controller::requireAdmin()/view()/redirectWithFlash()` (`src/Controller/Controller.php:82,54,65`); `RateLimitService::enforce(string,Request,?User)` (`src/Service/RateLimitService.php:34`); `AnnouncementService::setBanner()/clearBanner()` (Task 3); `Request::str()/post()` (`src/Core/Request.php:111,117`); `FeatureFlags::enabled()`; `NotFoundException`; `ValidationException`.

Steps:

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Admin/AdminAnnouncementTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminAnnouncementTest extends TestCase
{
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_admin_can_load_the_compose_form(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annform']));
        $resp = $this->get('/admin/announcements');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'name="message"');
        $this->assertSeeText($resp, 'name="broadcast"');
    }

    public function test_admin_publish_shows_banner_to_guest_and_returns_200(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annpub']));
        $resp = $this->post('/admin/announcements', ['message' => 'Read-only window at 02:00 UTC', 'dismissible' => '1']);
        $this->assertRedirectContains($resp, '/admin/announcements');

        $this->logoutClient();
        $guest = $this->get('/');
        $this->assertStatus(200, $guest);
        $this->assertSeeText($guest, 'Read-only window at 02:00 UTC');
    }

    public function test_publish_with_broadcast_notifies_members_and_appears_at_notifications(): void
    {
        $admin = $this->makeAdmin(['username' => 'annbcadmin2']);
        $reader = $this->makeUser(['username' => 'annbcreader2']);

        $this->actingAs($admin);
        $this->post('/admin/announcements', ['message' => 'All hands at noon', 'broadcast' => '1']);

        $this->actingAs($reader);
        $list = $this->get('/notifications');
        $this->assertStatus(200, $list);
        $this->assertSeeText($list, 'Announcement');
    }

    public function test_clear_deactivates_the_banner(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annclear2']));
        $this->post('/admin/announcements', ['message' => 'Temporary outage', 'dismissible' => '1']);
        $this->assertSeeText($this->get('/'), 'Temporary outage');

        $this->post('/admin/announcements', ['action' => 'clear']);
        $cleared = $this->get('/');
        $this->assertStatus(200, $cleared);
        $this->assertDontSeeText($cleared, 'Temporary outage');
    }

    public function test_empty_message_re_renders_form_at_422(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annempty2']));
        $resp = $this->post('/admin/announcements', ['message' => '   ']);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'Announcement message must be');
    }

    public function test_non_admin_post_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(['username' => 'annnonadmin']));
        $this->assertStatus(403, $this->post('/admin/announcements', ['message' => 'Nope']));
    }

    public function test_missing_csrf_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'anncsrf']));
        $this->assertStatus(403, $this->post('/admin/announcements', ['message' => 'No token'], false));
    }

    public function test_publish_is_rate_limited(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annrl']));
        for ($i = 0; $i < 5; $i++) {
            $this->assertContains(
                $this->post('/admin/announcements', ['message' => 'Notice ' . $i])->status(),
                [302, 303],
            );
        }
        $this->assertStatus(429, $this->post('/admin/announcements', ['message' => 'Too many']));
    }

    public function test_flag_off_takes_routes_dark(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annoff']));
        $this->setFlags(['announcements' => false]);
        $this->assertStatus(404, $this->get('/admin/announcements'));
        $this->assertStatus(404, $this->post('/admin/announcements', ['message' => 'Hidden']));
    }
}
```

Also append this method to `tests/Integration/Core/AppFeatureFlagTest.php` (before its closing `}`):

```php
    public function test_announcements_flag_takes_admin_routes_dark(): void
    {
        $admin = $this->makeAdmin(['username' => 'annflagroutes']);
        $this->actingAs($admin);

        // Reachable while the flag is on (default).
        self::assertNotSame(404, $this->get('/admin/announcements')->status());

        // 404 once the flag is off — the GET form and the POST both go dark.
        $this->setFlags(['announcements' => false]);
        $this->assertStatus(404, $this->get('/admin/announcements'));
        $this->assertStatus(404, $this->post('/admin/announcements', ['message' => 'Hidden']));

        // The home page still serves while the flag is off.
        $this->assertStatus(200, $this->get('/'));
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Admin/AdminAnnouncementTest.php` → fails: the `/admin/announcements` routes are unregistered (404 for the GET-form test) and `AdminAnnouncementController` does not exist.

- [ ] **Step 3: Minimal implementation**

Create `src/Controller/AdminAnnouncementController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\SettingRepository;
use App\Service\AnnouncementService;
use App\Service\RateLimitService;

/**
 * Admin announcements console (ADMIN §7.4). Compose the site banner and opt into
 * an in-app broadcast, or clear the banner. Flag-gated behind `announcements`;
 * every action requires an admin. No email channel.
 */
final class AdminAnnouncementController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('announcements')) {
            throw new NotFoundException();
        }
    }

    private function service(): AnnouncementService
    {
        return $this->container->get(AnnouncementService::class);
    }

    /** @param array<string,string> $params */
    public function form(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/announcements', [
            'announcement' => $this->currentAnnouncement(),
            'errors' => [],
            'old' => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function save(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        // Clearing is a distinct, un-validated, un-throttled action.
        if ($request->str('action') === 'clear') {
            $this->service()->clearBanner($admin);
            return $this->redirectWithFlash('/admin/announcements', 'Announcement cleared.');
        }

        // Throttle publishes per-admin (RateLimitService keys signed-in callers by
        // account); throws HTTP 429 which the kernel renders.
        $this->container->get(RateLimitService::class)->enforce('announce', $request, $admin);

        try {
            $this->service()->setBanner(
                $admin,
                $request->str('message'),
                $request->post('dismissible') !== null,
                $request->post('broadcast') !== null,
            );
        } catch (ValidationException $e) {
            return $this->view('admin/announcements', [
                'announcement' => $this->currentAnnouncement(),
                'errors' => $e->errors,
                'old' => $e->old + [
                    'message' => $request->str('message'),
                    'dismissible' => $request->post('dismissible') !== null,
                    'broadcast' => $request->post('broadcast') !== null,
                ],
            ], 422);
        }
        return $this->redirectWithFlash('/admin/announcements', 'Announcement published.');
    }

    /** @return array<string,mixed> */
    private function currentAnnouncement(): array
    {
        $current = $this->container->get(SettingRepository::class)->get('site_announcement', []);
        return is_array($current) ? $current : [];
    }
}
```

Create `templates/admin/announcements.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Announcements');
$ann = $announcement ?? [];
$active = is_array($ann) && !empty($ann['active']);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Announcements</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/announcements">Announcements</a>
    </nav>

    <section class="card">
        <h2>Current banner</h2>
        <?php if ($active): ?>
            <p class="site-announcement-current"><?= $e((string) ($ann['message'] ?? '')) ?></p>
            <p class="muted">
                <?= !empty($ann['dismissible']) ? 'Dismissible' : 'Not dismissible' ?>
                &middot; version <?= (int) ($ann['version'] ?? 0) ?>
            </p>
            <form method="post" action="/admin/announcements" class="inline">
                <?= $this->csrfField() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-small danger" type="submit">Clear banner</button>
            </form>
        <?php else: ?>
            <p class="muted">No banner is currently shown.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Publish a banner</h2>
        <form method="post" action="/admin/announcements" class="stacked">
            <?= $this->csrfField() ?>
            <label>Message
                <textarea name="message" rows="3" maxlength="500" required><?= $e((string) ($old['message'] ?? '')) ?></textarea>
            </label>
            <?php if (!empty($errors['message'])): ?><p class="field-error"><?= $e($errors['message']) ?></p><?php endif; ?>

            <label><input type="checkbox" name="dismissible" value="1" <?= !empty($old['dismissible']) ? 'checked' : '' ?>> Members can dismiss this banner</label>
            <label><input type="checkbox" name="broadcast" value="1" <?= !empty($old['broadcast']) ? 'checked' : '' ?>> Also send an in-app broadcast notification to all members</label>

            <div class="form-actions"><button class="btn" type="submit">Publish banner</button></div>
        </form>
    </section>
</div>
```

In `src/Core/App.php`, add the controller import alongside the other `App\Controller\…` uses (e.g. after `use App\Controller\AdminApiTokenController;` line 8):

```php
use App\Controller\AdminAnnouncementController;
```

Register the routes in `buildRouter()` just before `$r->get('/admin/structure', …)` (line 1084):

```php
        $r->get('/admin/announcements', [AdminAnnouncementController::class, 'form']);
        $r->post('/admin/announcements', [AdminAnnouncementController::class, 'save']);
```

In `templates/admin/dashboard.php`, add the conditional nav link after the Webhooks link (line 13):

```php
        <?php if (!empty($features['announcements'])): ?><a href="/admin/announcements">Announcements</a><?php endif; ?>
```

In `src/Controller/NotificationController.php` `resolveTarget()`, make the announcement click land on the home page (where the banner lives). Insert after the `mod` branch (the block ending `return '/mod/reports';` near line 119), before the `if ($n['thread_id'] === null)` check:

```php
        // An announcement has no thread/post; it points at the site banner ('/').
        if ($n['type'] === 'announcement') {
            return '/';
        }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Admin/AdminAnnouncementTest.php tests/Integration/Core/AppFeatureFlagTest.php`.

- [ ] **Step 5: Commit** — `git add src/Controller/AdminAnnouncementController.php templates/admin/announcements.php src/Core/App.php templates/admin/dashboard.php src/Controller/NotificationController.php tests/Integration/Admin/AdminAnnouncementTest.php tests/Integration/Core/AppFeatureFlagTest.php && git commit -m "Add admin announcements console + routes + nav (Group C)"`

---

### Task 6: Dismissal PE JS + styles + browser evidence

**Files:**
- Modify: `public/assets/app.js` (add a dismissal handler before the closing `})();` line 151)
- Modify: `public/assets/app.css` (append a `.site-announcement` rule)
- Modify: `tests/browser/seed.php` (enable `announcements` in BOTH `features` arrays, lines 53 + 94)
- Modify: `tests/browser/gate-a.spec.ts` (append one `test(...)` block)
- Evidence: `docs/evidence/browser/desktop/20-announcement-banner.png`, `.../21-announcement-dismissed.png` (+ mobile)

**Interfaces:**
- Consumes (server-rendered, from Task 4): `[data-announcement]`, `[data-announcement-version]`, `[data-dismissible]`, `[data-announcement-dismiss]` hooks.
- Produces: `localStorage['rb-announcement-dismissed'] = <version>`; `element.hidden = true` on dismiss; PNG evidence at desktop (1280×800) + mobile (390×844).

Steps:

- [ ] **Step 1: Write the failing test** — append to `tests/browser/gate-a.spec.ts` (after the last `test(...)` block):

```ts
test('site announcement banner: publish, render, dismiss, and persist', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  // Publish a dismissible banner through the real admin form (a no-JS POST).
  await visit(page, '/admin/announcements');
  await page.fill('textarea[name="message"]', 'Scheduled maintenance at 02:00 UTC.');
  await page.check('input[name="dismissible"]');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => u.pathname === '/admin/announcements');

  // It renders in the global shell at this viewport.
  await visit(page, '/');
  const banner = page.locator('[data-announcement]');
  await expect(banner).toBeVisible();
  await expect(banner).toContainText('Scheduled maintenance at 02:00 UTC.');
  await shot(page, info, '20-announcement-banner');

  // PE dismissal hides it and records the version in localStorage…
  await page.locator('[data-announcement-dismiss]').click();
  await expect(banner).toBeHidden();

  // …and the dismissal persists across navigation.
  await visit(page, '/c/general');
  await expect(page.locator('[data-announcement]')).toBeHidden();
  await shot(page, info, '21-announcement-dismissed');

  // Clean up so the banner does not bleed into later evidence runs.
  await visit(page, '/admin/announcements');
  await page.locator('form.inline button[type="submit"]').click();
});
```

- [ ] **Step 2: Run it, expect FAIL** — from `tests/browser`: `npm run evidence` (after `DB_DATABASE=retroboards_e2e php tests/browser/seed.php` against a freshly-migrated evidence DB). It fails: after clicking dismiss the banner stays visible (no `app.js` handler yet, so `[data-announcement]` is not hidden, and it reappears on `/c/general`).

- [ ] **Step 3: Minimal implementation**

In `public/assets/app.js`, insert before the final `})();` (line 151):

```js
    // Site announcement banner (ADMIN §7.4): a dismissible operator notice. With
    // JS off the server-rendered banner simply stays visible; this only remembers
    // a per-version dismissal in localStorage and hides the bar on later loads.
    var announcement = document.querySelector('[data-announcement]');
    if (announcement && announcement.getAttribute('data-dismissible') === '1') {
        var annVersion = announcement.getAttribute('data-announcement-version') || '0';
        var annKey = 'rb-announcement-dismissed';
        var annDismissed = null;
        try { annDismissed = window.localStorage.getItem(annKey); } catch (e) { annDismissed = null; }
        if (annDismissed === annVersion) {
            announcement.hidden = true;
        } else {
            var annBtn = announcement.querySelector('[data-announcement-dismiss]');
            if (annBtn) {
                annBtn.addEventListener('click', function () {
                    announcement.hidden = true;
                    try { window.localStorage.setItem(annKey, annVersion); } catch (e) { /* ignore */ }
                });
            }
        }
    }
```

Append to `public/assets/app.css` (styling only — no behavior, keeps CSP strict with an external sheet):

```css
/* Site announcement banner (ADMIN §7.4) */
.site-announcement {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .6rem 1rem;
    background: var(--accent, #2f6fed);
    color: #fff;
}
.site-announcement-message { margin: 0; flex: 1; }
.site-announcement-dismiss {
    background: transparent;
    border: 0;
    color: inherit;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
}
.site-announcement[hidden] { display: none; }
```

In `tests/browser/seed.php`, add `'announcements' => true` to BOTH `features` overrides — the already-seeded short-circuit (line 53) and the fresh-seed write (line 94):

```php
    $settings->set('features', ['api_tokens' => true, 'webhooks' => true, 'service_secrets' => true, 'first_party_hooks' => true, 'announcements' => true]); // B2 admin pages + domain webhook evidence + announcements
```

- [ ] **Step 4: Run it, expect PASS** — from `tests/browser`: `npm run evidence`. Confirm PNGs land in `docs/evidence/browser/desktop/` and `docs/evidence/browser/mobile/` (`20-announcement-banner.png`, `21-announcement-dismissed.png`).

- [ ] **Step 5: Commit** — `git add public/assets/app.js public/assets/app.css tests/browser/seed.php tests/browser/gate-a.spec.ts docs/evidence/browser && git commit -m "Announcement banner dismissal PE JS + browser evidence (Group C)"`

---

## Self-check coverage

- **Checklist 1 (AnnouncementService set/clear, transaction, audit, validation):** Task 3. (Rate-limit enforcement lives in the controller per the webhooks idiom — see Global Constraints + Task 5; the service signature in checklist 1 carries no `Request`, so enforcement is controller-level. Logged in `contractDeviations`.)
- **Checklist 2 (NotificationRepository::broadcastAnnouncement set-based insert, NOT-NULL cols, no body column, link '/'):** Task 1 (insert) + Task 5 (`resolveTarget` → `/`).
- **Checklist 3 (shareViewGlobals defensive read + share `site_announcement`):** Task 4.
- **Checklist 4 (layout includes partial after topbar, both variants, only when active):** Task 4.
- **Checklist 5 (announcement_banner.php partial, data-* attrs, escaped, no inline script/style):** Task 4.
- **Checklist 6 (admin/announcements.php compose form + state + clear + subnav + csrfField, flag-gated):** Task 5.
- **Checklist 7 (AdminAnnouncementController form/save, requireAdmin + gate + rate-limit + ValidationException→422):** Task 5.
- **Checklist 8 (FeatureFlags DEFAULTS `announcements => true`; routes 404 when off):** Task 2 (flag) + Task 5 (gate()/404).
- **Checklist 9 (config rate_limits['announce']):** Task 2.
- **Checklist 10 (app.js dismissal handler, localStorage by version, data-* hooks, no inline JS):** Task 6.
- **Checklist 11 (dashboard.php conditional Announcements nav link):** Task 5.
- **Checklist 12 (tests/browser/seed.php enables `announcements`):** Task 6.
- **Checklist 13 (all PHPUnit assertions: banner to guest+member 200; non-admin 403; missing CSRF; flag-off 404 + AppFeatureFlagTest key/dark; missing-table tolerance; broadcast rows excl. actor appear at /notifications; rate-limited 2nd; audit row; clear deactivates):** Task 1 (broadcast rows excl. actor/inactive), Task 2 (flag key exists), Task 3 (audit row, broadcast, validation, clear), Task 4 (banner renders guest+member 200, malformed-value tolerance, inactive hidden, escaping), Task 5 (form 200, publish→banner, broadcast appears at /notifications, clear, 422, non-admin 403, missing CSRF 403, rate-limit 429, flag-off 404 + AppFeatureFlagTest routes-dark).
- **Checklist 14 (Playwright: banner desktop+mobile, dismissible, persists; PNGs):** Task 6.
