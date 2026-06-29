# Admin Email Delivery Ops Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the admin Email Delivery Ops dashboard (delivery log + filters, queue status cards, test-send, suppression add/remove with the §7.6 subscription cascade, From/config banner, CSV export) gated behind the existing `email` flag, and record the Phase-3→Phase-2 pull-forward.

**Architecture:** Thin `AdminEmailController` (in `src/Controller`) marshals input and calls a new `EmailOpsService` (in `src/Service`) that owns every mutation (test-send, suppress/unsuppress + cascade, requeue) transactionally with one `moderation_log` audit row each, mirroring `WebhookService`. Two existing repositories gain read/mutate methods (`EmailDeliveryRepository::recent/count/requeue`, `EmailSuppressionRepository::list/count`, plus `SubscriptionRepository` cascade helpers). The view is a plain-PHP template; CSV export is a read-only GET returning a `text/csv` `Response`.

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB (PDO `EMULATE_PREPARES=false`), hand-wired DI in `App::buildContainer()`, PSR-4 `App\` → `src/`, PHPUnit integration tests (`Tests\Support\TestCase`), Playwright browser evidence.

## Global Constraints

- **Binding contract:** `docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md` is incorporated by reference; where this plan conflicts with it, the contract wins. This is **Group D** in that contract (execution order #4 — runs last, after Groups A/B/C; `composer test` green before/after).
- **Flag:** Gate every route action behind the **existing** `email` flag (`FeatureFlags::DEFAULTS` `email => true`, `src/Core/FeatureFlags.php:29`). Do **NOT** add a new flag. Each action calls a private `gate()` that throws `App\Core\NotFoundException` (→404) when the flag is off, **after** `requireAdmin()`.
- **No migration:** every table is schema-ready — `email_deliveries` (`database/migrations/0023`), `email_suppressions` (`database/migrations/0022`), `subscriptions` (`email_enabled` column), `moderation_log`. Do not add a migration.
- **Authority + state-beats-role:** every mutating action calls `requireAdmin()` first; the service calls `WriteGate::assertCanWrite($admin)` first so a suspended admin is blocked (403) even though role passes — mirror `WebhookService`.
- **Audit:** every mutation writes exactly one `moderation_log` row via `ModerationLogRepository::log([...])` with `target_type='setting'`, `target_id=0` (mirror `AdminService::setSiteName`, `src/Service/AdminService.php:61`), specifics in `after`.
- **CSRF / PE / CSP:** every POST form emits `<?= $this->csrfField() ?>`; CSV export is a read-only GET (no CSRF, no mutation). No inline `<script>`/`<style>`; the page works fully server-rendered with JS off.
- **Anti-draft-loss:** controllers catch `App\Core\ValidationException` themselves (the kernel does not) and `redirectWithFlash('/admin/email', $e->first())` — the originating forms live on the index page, so PRG-back-with-flash is the correct shape here (contract §Cross-cutting).
- **DB rules:** `EMULATE_PREPARES=false` — clamp `LIMIT`/`OFFSET` to int and concatenate (never bind); never reuse a named placeholder; build `WHERE` from provided filters with bound values; every multi-table mutation inside `$db->transaction(fn)`. UTC throughout.
- **No counters:** this surface touches no denormalized counter or reputation; no `RepairService` hook.
- **Tests:** PHPUnit strict (`failOnWarning`/`failOnRisky`, ≥1 assertion/test). Assert observable HTTP behavior; `moderation_log`/delivery rows are visible to the same connection before tearDown rollback, so `$this->db->fetchValue('SELECT COUNT(*) ...')` audit assertions are allowed (the webhook test does this). The kernel binds `Mailer` from `config('mail.driver')` (`src/Core/App.php:653`); to exercise a **configured** transport through `App::handle()`, rebuild `$this->app` with `mail.driver='array'` (→ `ArrayMailer`, `isConfigured()===true`); the default test config uses `SendmailMailer('')` (unconfigured).

---

### Task 1: Repository query/mutate methods + subscription cascade helpers

**Files:**
- Modify `src/Repository/EmailDeliveryRepository.php` (append `recent`, `count`, `requeue` after `find()`, ~line 105)
- Modify `src/Repository/EmailSuppressionRepository.php` (append `list`, `count` after `unsuppress()`, ~line 38)
- Modify `src/Repository/SubscriptionRepository.php` (append `disableEmailForUser`, `enableEmailForUser` after `listForUserWithContext()`, ~line 123)
- Test: `tests/Integration/Repository/EmailOpsRepositoryTest.php` (create)

**Interfaces:**
- Consumes: `App\Core\Database::fetchAll(string,array):array`, `::fetchValue(string,array):mixed`, `::run(string,array):\PDOStatement`, `::pdo()`.
- Produces:
  - `EmailDeliveryRepository::recent(int $limit, int $offset, ?string $status = null, ?string $kind = null, ?string $email = null): array`
  - `EmailDeliveryRepository::count(?string $status = null, ?string $kind = null, ?string $email = null): int`
  - `EmailDeliveryRepository::requeue(int $id): int`
  - `EmailSuppressionRepository::list(int $limit, int $offset, ?string $reason = null): array`
  - `EmailSuppressionRepository::count(?string $reason = null): int`
  - `SubscriptionRepository::disableEmailForUser(int $userId): int`
  - `SubscriptionRepository::enableEmailForUser(int $userId): int`

#### Steps

- [ ] **Step 1: Write the failing test** — `tests/Integration/Repository/EmailOpsRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SubscriptionRepository;
use Tests\Support\TestCase;

final class EmailOpsRepositoryTest extends TestCase
{
    public function test_recent_filters_by_kind_and_email_and_counts(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $deliv->enqueue(null, 'a@example.test', 'instant', 'Hi A', 'k-a');
        $deliv->enqueue(null, 'b@example.test', 'digest', 'Hi B', null);

        $instant = $deliv->recent(50, 0, null, 'instant', null);
        self::assertCount(1, $instant);
        self::assertSame('a@example.test', (string) $instant[0]['email']);

        self::assertSame(2, $deliv->count(null, null, null));
        self::assertSame(1, $deliv->count(null, 'instant', null));
        self::assertSame(1, $deliv->count(null, null, 'b@example.test'));
        self::assertCount(1, $deliv->recent(50, 0, null, null, 'b@example.test'));
    }

    public function test_requeue_only_affects_failed_rows(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $id = $deliv->enqueue(null, 'c@example.test', 'instant', null, 'k-c');
        self::assertSame(0, $deliv->requeue($id)); // still queued → no-op

        $deliv->markFailed($id, 'boom');
        self::assertSame(1, $deliv->requeue($id));
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id = ?', [$id]));
        self::assertNull($this->db->fetchValue('SELECT error FROM email_deliveries WHERE id = ?', [$id]));
    }

    public function test_suppression_list_and_count_filter_by_reason(): void
    {
        $supp = new EmailSuppressionRepository($this->db);
        $supp->suppress('one@example.test', 'manual');
        $supp->suppress('two@example.test', 'bounce');

        self::assertCount(2, $supp->list(50, 0, null));
        self::assertSame(2, $supp->count(null));
        self::assertSame(1, $supp->count('manual'));
        self::assertCount(1, $supp->list(50, 0, 'bounce'));
    }

    public function test_subscription_cascade_helpers_toggle_email_channel(): void
    {
        $u = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $subs = new SubscriptionRepository($this->db);
        $subs->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');

        $subs->disableEmailForUser((int) $u['id']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));

        $subs->enableEmailForUser((int) $u['id']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Repository/EmailOpsRepositoryTest.php` — fails with `Error: Call to undefined method App\Repository\EmailDeliveryRepository::recent()`.

- [ ] **Step 3: Minimal implementation** — append to `src/Repository/EmailDeliveryRepository.php` (after `find()`):

```php
    /**
     * Filtered, paginated delivery log for the admin dashboard. LIMIT/OFFSET are
     * clamped + concatenated (never bound: EMULATE_PREPARES=false); filters bind.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit, int $offset, ?string $status = null, ?string $kind = null, ?string $email = null): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        $where = [];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'kind = :kind';
            $params['kind'] = $kind;
        }
        if ($email !== null && $email !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        return $this->db->fetchAll(
            'SELECT id, user_id, email, kind, subject, status, error, message_id, created_at, sent_at
             FROM email_deliveries' . $clause . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function count(?string $status = null, ?string $kind = null, ?string $email = null): int
    {
        $where = [];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'kind = :kind';
            $params['kind'] = $kind;
        }
        if ($email !== null && $email !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_deliveries' . $clause, $params);
    }

    /** Re-queue a failed send for the worker. Returns rows affected (0 if not failed). */
    public function requeue(int $id): int
    {
        return $this->db->run(
            "UPDATE email_deliveries SET status = 'queued', error = NULL WHERE id = ? AND status = 'failed'",
            [$id],
        )->rowCount();
    }
```

Append to `src/Repository/EmailSuppressionRepository.php` (after `unsuppress()`):

```php
    /**
     * Suppression list for the admin dashboard. LIMIT/OFFSET clamped + concatenated.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(int $limit, int $offset, ?string $reason = null): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        $where = '';
        $params = [];
        if ($reason !== null && $reason !== '') {
            $where = ' WHERE reason = :reason';
            $params['reason'] = $reason;
        }
        return $this->db->fetchAll(
            'SELECT email, reason, created_at FROM email_suppressions' . $where
            . ' ORDER BY created_at DESC, email ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function count(?string $reason = null): int
    {
        $where = '';
        $params = [];
        if ($reason !== null && $reason !== '') {
            $where = ' WHERE reason = :reason';
            $params['reason'] = $reason;
        }
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_suppressions' . $where, $params);
    }
```

Append to `src/Repository/SubscriptionRepository.php` (after `listForUserWithContext()`):

```php
    /**
     * Suppression cascade (ADMIN §7.6): turn the email channel OFF on every
     * subscription a user owns when their address is suppressed. Returns rows changed.
     */
    public function disableEmailForUser(int $userId): int
    {
        return $this->db->run('UPDATE subscriptions SET email_enabled = 0 WHERE user_id = ?', [$userId])->rowCount();
    }

    /** Re-enable the email channel on a user's subscriptions when they are un-suppressed. */
    public function enableEmailForUser(int $userId): int
    {
        return $this->db->run('UPDATE subscriptions SET email_enabled = 1 WHERE user_id = ?', [$userId])->rowCount();
    }
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Repository/EmailOpsRepositoryTest.php`.

- [ ] **Step 5: Commit** — `git add src/Repository/EmailDeliveryRepository.php src/Repository/EmailSuppressionRepository.php src/Repository/SubscriptionRepository.php tests/Integration/Repository/EmailOpsRepositoryTest.php && git commit -m "Email-ops repos: delivery recent/count/requeue, suppression list/count, subscription email cascade"`

---

### Task 2: EmailOpsService + container binding

**Files:**
- Create `src/Service/EmailOpsService.php`
- Modify `src/Core/App.php` (add `use App\Service\EmailOpsService;` near the service imports ~line 125; add the `$c->bind(EmailOpsService::class, ...)` after the `NotificationService` binding, ~line 678)
- Test: `tests/Integration/Service/EmailOpsServiceTest.php` (create)

**Interfaces:**
- Consumes: `EmailDeliveryRepository::{enqueue,markSent,markFailed,requeue}`, `EmailSuppressionRepository::{suppress,unsuppress,isSuppressed}`, `SubscriptionRepository::{disableEmailForUser,enableEmailForUser}`, `UserRepository::findByEmail(string):?array` (`src/Repository/UserRepository.php:35`), `ModerationLogRepository::log(array):int`, `WriteGate::assertCanWrite(User):void`, `Mailer::{isConfigured,send}`, `Database::transaction(callable):mixed`, `App\Domain\User::{id,email}`.
- Produces:
  - `EmailOpsService::sendTest(User $admin): void`
  - `EmailOpsService::manualSuppress(User $admin, string $email): void`
  - `EmailOpsService::unsuppress(User $admin, string $email): void`
  - `EmailOpsService::requeueFailed(User $admin, int $id): void`

#### Steps

- [ ] **Step 1: Write the failing test** — `tests/Integration/Service/EmailOpsServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Mail\SendmailMailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\EmailOpsService;
use Tests\Support\TestCase;

final class EmailOpsServiceTest extends TestCase
{
    private function service(?Mailer $mailer = null): EmailOpsService
    {
        return new EmailOpsService(
            $this->db,
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new SubscriptionRepository($this->db),
            new UserRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            $mailer ?? new ArrayMailer(),
        );
    }

    public function test_send_test_enqueues_a_test_row_marks_sent_and_audits(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['email' => 'ops@example.test']));
        $this->service()->sendTest($admin);

        self::assertSame(
            'sent',
            (string) $this->db->fetchValue("SELECT status FROM email_deliveries WHERE kind = 'test' AND email = ?", ['ops@example.test']),
        );
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_test_sent'"));
    }

    public function test_send_test_fails_closed_when_transport_unconfigured(): void
    {
        $admin = $this->userEntity($this->makeAdmin(['email' => 'noconf@example.test']));
        $this->expectException(ValidationException::class);
        $this->service(new SendmailMailer(''))->sendTest($admin);
    }

    public function test_manual_suppress_cascades_email_off_then_unsuppress_restores(): void
    {
        $u = $this->makeUser(['email' => 'sub@example.test']);
        $board = $this->makeBoard($this->makeCategory());
        (new SubscriptionRepository($this->db))->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');
        $admin = $this->userEntity($this->makeAdmin());

        $this->service()->manualSuppress($admin, 'sub@example.test');
        self::assertTrue((new EmailSuppressionRepository($this->db))->isSuppressed('sub@example.test'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_suppressed'"));

        $this->service()->unsuppress($admin, 'sub@example.test');
        self::assertFalse((new EmailSuppressionRepository($this->db))->isSuppressed('sub@example.test'));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_unsuppressed'"));
    }

    public function test_requeue_failed_resets_to_queued_with_audit(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $id = $deliv->enqueue(null, 'x@example.test', 'instant', null, 'p1:u1');
        $deliv->markFailed($id, 'smtp 550');
        $admin = $this->userEntity($this->makeAdmin());

        $this->service()->requeueFailed($admin, $id);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM email_deliveries WHERE id = ?', [$id]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_requeued'"));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Service/EmailOpsServiceTest.php` — fails with `Error: Class "App\Service\EmailOpsService" not found`.

- [ ] **Step 3: Minimal implementation** — create `src/Service/EmailOpsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use Throwable;

/**
 * Admin email-operations service (ADMIN §7.5/§7.6/§10.1). Owns the test-send,
 * manual suppression add/remove (with the §7.6 per-user subscription cascade) and
 * failed-delivery requeue. Every mutation runs through WriteGate (state beats
 * role) and writes one moderation_log audit row, mirroring WebhookService.
 */
final class EmailOpsService
{
    public function __construct(
        private Database $db,
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private SubscriptionRepository $subs,
        private UserRepository $users,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private Mailer $mailer,
    ) {
    }

    /**
     * Queue + synchronously send a one-off test message to the admin's own
     * address. Fails closed when the transport is not configured (no From).
     */
    public function sendTest(User $admin): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$this->mailer->isConfigured()) {
            throw new ValidationException(['email' => 'Configure your sending domain first.']);
        }

        $email = $admin->email();
        $subject = 'RetroBoards email delivery test';
        $id = $this->db->transaction(function () use ($admin, $email, $subject): int {
            $newId = $this->deliveries->enqueue($admin->id(), $email, 'test', $subject, null);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_test_sent',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => $email],
            ]);
            return $newId;
        });

        try {
            $messageId = $this->mailer->send(
                $email,
                $subject,
                "This is a test email from RetroBoards. If you received it, your outbound email is working.",
            );
            $this->deliveries->markSent($id, $messageId);
        } catch (Throwable $e) {
            $this->deliveries->markFailed($id, $e->getMessage());
        }
    }

    /** Manually suppress an address + cascade its subscriptions' email channel off. */
    public function manualSuppress(User $admin, string $email): void
    {
        $this->writeGate->assertCanWrite($admin);
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            throw new ValidationException(['email' => 'Enter a valid email address.']);
        }
        $this->db->transaction(function () use ($admin, $email): void {
            $this->suppress->suppress($email, 'manual');
            $user = $this->users->findByEmail($email);
            if ($user !== null) {
                $this->subs->disableEmailForUser((int) $user['id']);
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_suppressed',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => strtolower($email)],
            ]);
        });
    }

    /** Remove an address from the suppression list + re-enable its subscriptions' email channel. */
    public function unsuppress(User $admin, string $email): void
    {
        $this->writeGate->assertCanWrite($admin);
        $email = trim($email);
        if ($email === '') {
            throw new ValidationException(['email' => 'Enter an email address.']);
        }
        $this->db->transaction(function () use ($admin, $email): void {
            $this->suppress->unsuppress($email);
            $user = $this->users->findByEmail($email);
            if ($user !== null) {
                $this->subs->enableEmailForUser((int) $user['id']);
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_unsuppressed',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['email' => strtolower($email)],
            ]);
        });
    }

    /** Re-queue a failed delivery for the worker. No-op (no audit) when not failed. */
    public function requeueFailed(User $admin, int $id): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($admin, $id): void {
            if ($this->deliveries->requeue($id) !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'email_requeued',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['delivery_id' => $id],
            ]);
        });
    }
}
```

Wire it into `src/Core/App.php`. Add the import beside the other `use App\Service\...;` lines (near `use App\Service\RateLimitService;`, line 125):

```php
use App\Service\EmailOpsService;
```

Add the binding immediately after the `NotificationService` binding closes (after `src/Core/App.php:678`):

```php
        $c->bind(EmailOpsService::class, fn (Container $c) => new EmailOpsService(
            $c->get(Database::class),
            $c->get(EmailDeliveryRepository::class),
            $c->get(EmailSuppressionRepository::class),
            $c->get(SubscriptionRepository::class),
            $c->get(UserRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(Mailer::class),
        ));
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Service/EmailOpsServiceTest.php`.

- [ ] **Step 5: Commit** — `git add src/Service/EmailOpsService.php src/Core/App.php tests/Integration/Service/EmailOpsServiceTest.php && git commit -m "EmailOpsService: test-send, suppress/unsuppress cascade, requeue with audit"`

---

### Task 3: AdminEmailController + routes + template + nav + config

**Files:**
- Create `src/Controller/AdminEmailController.php`
- Create `templates/admin/email.php`
- Modify `src/Core/App.php` (add `use App\Controller\AdminEmailController;` near line 10; register five routes after the webhooks block at `src/Core/App.php:1083`, before `/admin/structure`)
- Modify `templates/admin/dashboard.php` (add conditional `Email` nav link after the Webhooks link, `templates/admin/dashboard.php:13`)
- Modify `config/config.php` (add `rate_limits['email_test']`, after `'webhook_test' => [20, 600],` at `config/config.php:203`)
- Test: `tests/Integration/Admin/AppAdminEmailTest.php` (create)

**Interfaces:**
- Consumes: `Controller::{requireAdmin,view,redirectWithFlash,config,container}`, `FeatureFlags::enabled('email')`, `RateLimitService::enforce('email_test', Request, User)`, `EmailOpsService::{sendTest,manualSuppress,unsuppress}`, `EmailDeliveryRepository::{recent,count,statusCounts}`, `EmailSuppressionRepository::{list,count}`, `Mailer::isConfigured()`, `Request::{str,int}`, `App\Core\Response`.
- Produces: `AdminEmailController::{index,test,suppress,unsuppress,export}(Request $request, array $params): Response`; routes `GET /admin/email`, `GET /admin/email/export`, `POST /admin/email/test`, `POST /admin/email/suppressions`, `POST /admin/email/suppressions/remove`.

#### Steps

- [ ] **Step 1: Write the failing test** — `tests/Integration/Admin/AppAdminEmailTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\App;
use App\Core\Config;
use App\Repository\EmailDeliveryRepository;
use Tests\Support\TestCase;

final class AppAdminEmailTest extends TestCase
{
    /** Rebuild the kernel so its Mailer is a configured ArrayMailer. */
    private function useArrayMailer(): void
    {
        $cfg = new Config(array_replace_recursive($this->config->all(), ['mail' => ['driver' => 'array']]));
        $this->app = new App($cfg, $this->db, $this->rateLimiter);
    }

    public function test_index_requires_admin(): void
    {
        // Guest → redirect to login.
        $this->assertRedirectContains($this->get('/admin/email'), '/login');

        // Non-admin → 403.
        $this->actingAs($this->makeUser(['username' => 'plainuser']));
        $this->assertStatus(403, $this->get('/admin/email'));

        // Admin → 200.
        $this->logoutClient();
        $this->actingAs($this->makeAdmin(['username' => 'emailadmin']));
        $this->assertStatus(200, $this->get('/admin/email'));
    }

    public function test_unconfigured_transport_blocks_test_send_and_shows_blocked_banner(): void
    {
        // Default kernel uses SendmailMailer('') → not configured.
        $this->actingAs($this->makeAdmin(['email' => 'blocked@example.test']));

        $res = $this->post('/admin/email/test', []);
        $this->assertRedirectContains($res, '/admin/email');
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE kind = 'test'"));

        self::assertStringContainsString('Configure your sending domain', $this->get('/admin/email')->body());
    }

    public function test_test_send_with_configured_transport_enqueues_and_marks_sent_with_audit(): void
    {
        $this->useArrayMailer();
        $this->actingAs($this->makeAdmin(['email' => 'sender@example.test']));

        $this->assertRedirectContains($this->post('/admin/email/test', []), '/admin/email');
        self::assertSame(
            'sent',
            (string) $this->db->fetchValue("SELECT status FROM email_deliveries WHERE kind = 'test' AND email = ?", ['sender@example.test']),
        );
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_test_sent'"));
    }

    public function test_suppress_then_remove_round_trip_via_http(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->assertRedirectContains($this->post('/admin/email/suppressions', ['email' => 'spam@example.test']), '/admin/email');
        self::assertStringContainsString('spam@example.test', $this->get('/admin/email')->body());

        $this->assertRedirectContains($this->post('/admin/email/suppressions/remove', ['email' => 'spam@example.test']), '/admin/email');
        self::assertStringNotContainsString('spam@example.test', $this->get('/admin/email')->body());
    }

    public function test_suppress_cascades_subscription_email_channel_off(): void
    {
        $u = $this->makeUser(['email' => 'casc@example.test']);
        $board = $this->makeBoard($this->makeCategory());
        (new \App\Repository\SubscriptionRepository($this->db))->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');

        $this->actingAs($this->makeAdmin());
        $this->post('/admin/email/suppressions', ['email' => 'casc@example.test']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
    }

    public function test_delivery_log_filters_by_kind(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $deliv->enqueue(null, 'inst@example.test', 'instant', 'Hi', 'k1');
        $deliv->enqueue(null, 'dig@example.test', 'digest', 'Hi', null);

        $this->actingAs($this->makeAdmin());
        $body = $this->get('/admin/email', ['kind' => 'instant'])->body();
        self::assertStringContainsString('inst@example.test', $body);
        self::assertStringNotContainsString('dig@example.test', $body);
    }

    public function test_export_returns_csv_attachment(): void
    {
        (new EmailDeliveryRepository($this->db))->enqueue(null, 'csv@example.test', 'instant', 'Hi', 'kx');
        $this->actingAs($this->makeAdmin());

        $res = $this->get('/admin/email/export');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('text/csv', (string) $res->getHeader('content-type'));
        self::assertStringContainsString('attachment', (string) $res->getHeader('content-disposition'));
        self::assertStringContainsString('csv@example.test', $res->body());
    }

    public function test_test_send_is_rate_limited(): void
    {
        $this->useArrayMailer();
        $this->actingAs($this->makeAdmin(['email' => 'rl@example.test']));

        for ($i = 0; $i < 20; $i++) {
            self::assertContains($this->post('/admin/email/test', [])->status(), [302, 303]);
        }
        $this->assertStatus(429, $this->post('/admin/email/test', []));
    }
}
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit tests/Integration/Admin/AppAdminEmailTest.php` — fails: `GET /admin/email` 404 (route not registered) so `assertRedirectContains` fails.

- [ ] **Step 3: Minimal implementation** — create `src/Controller/AdminEmailController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Service\EmailOpsService;
use App\Service\RateLimitService;

/** Admin email delivery operations dashboard (ADMIN §7.5/§7.6/§10.1), gated by the `email` flag. */
final class AdminEmailController extends Controller
{
    private const STATUSES = ['queued', 'sent', 'bounced', 'complained', 'suppressed', 'failed'];
    private const KINDS = ['instant', 'digest', 'test', 'system'];

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('email')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $status = $this->oneOf($request->str('status'), self::STATUSES);
        $kind = $this->oneOf($request->str('kind'), self::KINDS);
        $email = trim($request->str('email'));
        $page = max(1, $request->int('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $deliveries = $this->container->get(EmailDeliveryRepository::class);
        $suppress = $this->container->get(EmailSuppressionRepository::class);
        $mailer = $this->container->get(Mailer::class);

        return $this->view('admin/email', [
            'deliveries' => $deliveries->recent($perPage, $offset, $status, $kind, $email !== '' ? $email : null),
            'total' => $deliveries->count($status, $kind, $email !== '' ? $email : null),
            'status_counts' => $deliveries->statusCounts(),
            'suppressions' => $suppress->list(100, 0, null),
            'suppression_count' => $suppress->count(null),
            'mailer_configured' => $mailer->isConfigured(),
            'mail_from' => (string) $this->config()->get('mail.from', ''),
            'f_status' => $status ?? '',
            'f_kind' => $kind ?? '',
            'f_email' => $email,
            'page' => $page,
        ]);
    }

    /** @param array<string,string> $params */
    public function test(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(RateLimitService::class)->enforce('email_test', $request, $admin);
        try {
            $this->container->get(EmailOpsService::class)->sendTest($admin);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Test email sent to ' . $admin->email() . '.');
    }

    /** @param array<string,string> $params */
    public function suppress(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        try {
            $this->container->get(EmailOpsService::class)->manualSuppress($admin, $request->str('email'));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Address added to the suppression list.');
    }

    /** @param array<string,string> $params */
    public function unsuppress(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        try {
            $this->container->get(EmailOpsService::class)->unsuppress($admin, $request->str('email'));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Address removed from the suppression list.');
    }

    /** Read-only CSV export of the (filtered) delivery log. GET → no CSRF. @param array<string,string> $params */
    public function export(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $status = $this->oneOf($request->str('status'), self::STATUSES);
        $kind = $this->oneOf($request->str('kind'), self::KINDS);
        $email = trim($request->str('email'));

        $rows = $this->container->get(EmailDeliveryRepository::class)
            ->recent(10000, 0, $status, $kind, $email !== '' ? $email : null);

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['id', 'created_at', 'kind', 'status', 'email', 'subject', 'message_id', 'error', 'sent_at']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                (int) $r['id'],
                (string) $r['created_at'],
                (string) $r['kind'],
                (string) $r['status'],
                (string) $r['email'],
                (string) ($r['subject'] ?? ''),
                (string) ($r['message_id'] ?? ''),
                (string) ($r['error'] ?? ''),
                (string) ($r['sent_at'] ?? ''),
            ]);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="retroboards-email-deliveries.csv"',
        ]);
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(string $value, array $allowed): ?string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : null;
    }
}
```

Create `templates/admin/email.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Email delivery');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Email delivery</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <?php if (!empty($features['webhooks'])): ?><a href="/admin/webhooks">Webhooks</a><?php endif; ?>
        <a class="active" href="/admin/email">Email</a>
    </nav>

    <?php if (empty($mailer_configured)): ?>
        <div class="flash" role="alert">
            <strong>Email is not ready to send.</strong>
            Configure your sending domain (set a From address) before sending. Queued mail waits until the transport is configured.
        </div>
    <?php else: ?>
        <p class="muted">Sending is configured<?php if (($mail_from ?? '') !== ''): ?> from <code><?= $e($mail_from) ?></code><?php endif; ?>. The delivery worker drains queued mail.</p>
    <?php endif; ?>

    <section class="card">
        <h2>Queue status</h2>
        <ul class="stat-cards">
            <?php foreach (['queued', 'sent', 'failed', 'suppressed', 'bounced', 'complained'] as $s): ?>
                <li class="stat-card"><span class="stat-num"><?= (int) ($status_counts[$s] ?? 0) ?></span> <span class="stat-label"><?= $e($s) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h2>Send a test email</h2>
        <form method="post" action="/admin/email/test" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-small" type="submit"<?= empty($mailer_configured) ? ' disabled' : '' ?>>Send test email</button>
        </form>
        <p class="muted">Sends a one-off message to your own account address and records it in the log below.</p>
    </section>

    <section class="card">
        <h2>Delivery log</h2>
        <form method="get" action="/admin/email" class="inline-form">
            <label>Status
                <select name="status" class="input">
                    <option value="">Any</option>
                    <?php foreach (['queued', 'sent', 'bounced', 'complained', 'suppressed', 'failed'] as $s): ?>
                        <option value="<?= $e($s) ?>"<?= ($f_status ?? '') === $s ? ' selected' : '' ?>><?= $e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kind
                <select name="kind" class="input">
                    <option value="">Any</option>
                    <?php foreach (['instant', 'digest', 'test', 'system'] as $k): ?>
                        <option value="<?= $e($k) ?>"<?= ($f_kind ?? '') === $k ? ' selected' : '' ?>><?= $e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Email
                <input type="text" name="email" class="input" value="<?= $e($f_email ?? '') ?>">
            </label>
            <button class="btn btn-small" type="submit">Filter</button>
            <a class="btn btn-small" href="/admin/email/export">Download CSV</a>
        </form>
        <table class="audit">
            <thead><tr><th>When</th><th>To</th><th>Kind</th><th>Status</th><th>Subject</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= $e(human_datetime($d['created_at'])) ?></td>
                    <td><?= $e($d['email']) ?></td>
                    <td><?= $e($d['kind']) ?></td>
                    <td><?= $e($d['status']) ?></td>
                    <td><?= $e((string) ($d['subject'] ?? '')) ?></td>
                    <td><?= $e((string) ($d['error'] ?? $d['message_id'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deliveries)): ?>
                <tr><td colspan="6" class="muted">No deliveries match.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p class="muted"><?= (int) $total ?> total matching deliveries.</p>
    </section>

    <section class="card">
        <h2>Suppressed addresses</h2>
        <form method="post" action="/admin/email/suppressions" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="email" name="email" class="input" placeholder="address@example.com" required>
            <button class="btn btn-small" type="submit">Suppress</button>
        </form>
        <table class="audit">
            <thead><tr><th>Email</th><th>Reason</th><th>Since</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($suppressions as $row): ?>
                <tr>
                    <td><?= $e($row['email']) ?></td>
                    <td><?= $e($row['reason']) ?></td>
                    <td><?= $e(human_datetime($row['created_at'])) ?></td>
                    <td>
                        <form method="post" action="/admin/email/suppressions/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="email" value="<?= $e($row['email']) ?>">
                            <button class="btn btn-small" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($suppressions)): ?>
                <tr><td colspan="4" class="muted">No suppressed addresses.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
```

Register routes in `src/Core/App.php` after `src/Core/App.php:1083` (the webhooks `replay` line), before `$r->get('/admin/structure', ...)`:

```php
        $r->get('/admin/email', [AdminEmailController::class, 'index']);
        $r->get('/admin/email/export', [AdminEmailController::class, 'export']);
        $r->post('/admin/email/test', [AdminEmailController::class, 'test']);
        $r->post('/admin/email/suppressions', [AdminEmailController::class, 'suppress']);
        $r->post('/admin/email/suppressions/remove', [AdminEmailController::class, 'unsuppress']);
```

Add the import near `src/Core/App.php:10` (beside `use App\Controller\AdminWebhookController;`):

```php
use App\Controller\AdminEmailController;
```

Add the nav link in `templates/admin/dashboard.php` after the Webhooks link (`templates/admin/dashboard.php:13`):

```php
        <?php if (!empty($features['email'])): ?><a href="/admin/email">Email</a><?php endif; ?>
```

Add the rate-limit policy in `config/config.php` after `'webhook_test' => [20, 600],` (`config/config.php:203`):

```php
        'email_test' => [20, 600],
```

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit tests/Integration/Admin/AppAdminEmailTest.php`.

- [ ] **Step 5: Commit** — `git add src/Controller/AdminEmailController.php templates/admin/email.php templates/admin/dashboard.php config/config.php src/Core/App.php tests/Integration/Admin/AppAdminEmailTest.php && git commit -m "Admin email-ops dashboard: log/filters, test-send, suppression add/remove, CSV export"`

---

### Task 4: Flag-dark regression (`AppFeatureFlagTest`)

**Files:**
- Modify `tests/Integration/Core/AppFeatureFlagTest.php` (add one test method)

**Interfaces:**
- Consumes: `TestCase::{actingAs,makeAdmin,get,post,assertStatus}`, `setFlags(array)` (private helper, `tests/Integration/Core/AppFeatureFlagTest.php:26`).
- Produces: regression that every `/admin/email*` route 404s when `email` is off. (Note: admin routes run `requireAdmin()` before `gate()`, so the assertion must act as an admin — a guest would get 302, not 404; this is why it is a dedicated authenticated method, not part of the unauthenticated `test_disabling_a_flag_...` loop.)

#### Steps

- [ ] **Step 1: Write the failing test** — add to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
    public function test_email_flag_gates_admin_email_routes(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'flagemailadmin']));

        // Flag on (default): the dashboard is reachable.
        self::assertNotSame(404, $this->get('/admin/email')->status());

        // Flag off: every route 404s (the gate fires right after requireAdmin).
        $this->setFlags(['email' => false]);
        $this->assertStatus(404, $this->get('/admin/email'));
        $this->assertStatus(404, $this->get('/admin/email/export'));
        $this->assertStatus(404, $this->post('/admin/email/test', []));
        $this->assertStatus(404, $this->post('/admin/email/suppressions', ['email' => 'x@example.test']));
        $this->assertStatus(404, $this->post('/admin/email/suppressions/remove', ['email' => 'x@example.test']));
    }
```

- [ ] **Step 2: Run it, expect FAIL** — `vendor/bin/phpunit --filter test_email_flag_gates_admin_email_routes` — fails before Task 3 lands the routes; run it after Task 3 if implementing strictly TDD, where it then passes immediately, confirming the gate. (If implementing this task in isolation against a tree without Task 3, the first assertion fails with a 404 while the flag is on.)

- [ ] **Step 3: Minimal implementation** — no production code; the gate already exists from Task 3. (If FAIL persists, the bug is an action missing its `gate()` call — add `$this->gate();` immediately after `requireAdmin()`/`$admin = $this->requireAdmin();` in that action of `src/Controller/AdminEmailController.php`.)

- [ ] **Step 4: Run it, expect PASS** — `vendor/bin/phpunit --filter test_email_flag_gates_admin_email_routes`.

- [ ] **Step 5: Commit** — `git add tests/Integration/Core/AppFeatureFlagTest.php && git commit -m "Regression: /admin/email* routes 404 when the email flag is dark"`

---

### Task 5: Carryover docs — PHASE_2_STATUS update + ADR 0005

**Files:**
- Modify `docs/PHASE_2_STATUS.md` (rewrite the email line at `docs/PHASE_2_STATUS.md:382`)
- Create `docs/adr/0005-phase2-operator-surface-closeout.md` (mirror `docs/adr/0004-phase-5-entry-and-carryover.md` structure)

**Interfaces:** Documentation only — no PHP. "Test" is a grep-based presence check (docs are not PHPUnit-testable).

#### Steps

- [ ] **Step 1: Write the failing test** — run the presence check and expect it to FAIL (the pull-forward is not yet recorded):

```bash
test -f docs/adr/0005-phase2-operator-surface-closeout.md \
  && grep -q "pulled back into the Phase 2 closeout" docs/PHASE_2_STATUS.md \
  && grep -q "0005-phase2-operator-surface-closeout" docs/PHASE_2_STATUS.md \
  && echo PASS || echo FAIL
```

- [ ] **Step 2: Run it, expect FAIL** — run the Step 1 block; it prints `FAIL` (ADR file absent; line 382 still says "re-scoped to Phase 3").

- [ ] **Step 3: Minimal implementation** — replace the line at `docs/PHASE_2_STATUS.md:382` with:

```markdown
- [x] Email delivery visibility/test/recovery tools — `statusCounts` + worker stats + suppression recovery present; the dedicated admin delivery dashboard (`/admin/email`: delivery log + filters, queue status cards, test-send, suppression add/remove with the §7.6 subscription cascade, From/config banner, CSV export) was **pulled back into the Phase 2 closeout on 2026-06-29** rather than re-scoped to Phase 3 (see `docs/adr/0005-phase2-operator-surface-closeout.md`). Still deferred: the email-broadcast announcement channel, the `NotificationEmailWorker` `kind='system'` render path, and the §7.5 SPF/DKIM domain-status / sending-blocked gate (only `isConfigured()` From-presence is enforced today).
```

Create `docs/adr/0005-phase2-operator-surface-closeout.md`:

```markdown
# ADR 0005: Phase 2 operator-surface closeout — email-ops dashboard pull-forward

**Date:** 2026-06-29
**Status:** **Accepted as the Phase 2 operator-surface closeout decision record.**
Recorded under the locked product decisions in
`docs/superpowers/plans/2026-06-29-phase2-operator-surfaces-contract.md` (decision #1).
Reverses a recorded Gate B deferral deliberately rather than silently
(DESIGN §13 / CLAUDE.md "never silently dropped").

## Context

`docs/PHASE_2_STATUS.md:382` re-scoped the dedicated admin email delivery
dashboard to Phase 3, while `statusCounts`, worker stats, and one-click
unsubscribe suppression recovery already shipped in Phase 2. The Phase 2
operator-surface closeout pulls the dashboard back into Phase 2 so operators can
observe and recover the email queue from the admin console without a console/DB
detour. A recorded deferral must not be silently reversed, so this ADR documents
both what is now shipped and what remains deferred.

## Decision

1. **Pulled forward into the Phase 2 closeout (built now):** the admin
   `/admin/email` dashboard — filterable delivery log, queue status cards
   (`statusCounts`), test-send (rate-limited `email_test`, fails closed when the
   transport is unconfigured), manual suppression add/remove with the ADMIN §7.6
   per-user subscription email cascade, a From/config status banner, and a
   read-only CSV export. Gated behind the existing `email` flag (no new flag).
   Every mutation is `requireAdmin` + `WriteGate` gated and writes one
   `moderation_log` audit row (`target_type='setting'`).

2. **Remains deferred (explicitly NOT built here):**
   - the **email-broadcast announcement channel** (Group C ships banner +
     in-app broadcast only; no email broadcast);
   - the **`NotificationEmailWorker` `kind='system'` render path** — the worker
     silently drops `kind='system'` rows; `src/Worker/NotificationEmailWorker.php`
     is intentionally NOT modified;
   - the **ADMIN §7.5 SPF/DKIM domain-status / sending-blocked gate** — only
     `Mailer::isConfigured()` (From-address presence) is enforced today; full
     domain authentication status is future work.

## Consequences

- The Phase 2 email surface is observable/recoverable from the admin console.
- The three deferred items above carry forward as explicit, owned deferrals
  (not silently reclassified), to be picked up in a later phase with their own
  scope record.
- No schema change: the dashboard reads/writes only existing tables
  (`email_deliveries`, `email_suppressions`, `subscriptions`, `moderation_log`).
```

- [ ] **Step 4: Run it, expect PASS** — re-run the Step 1 block; it prints `PASS`.

- [ ] **Step 5: Commit** — `git add docs/PHASE_2_STATUS.md docs/adr/0005-phase2-operator-surface-closeout.md && git commit -m "Docs: record email-ops dashboard pull-forward (ADR 0005) + Phase 2 status"`

---

### Task 6: Playwright browser evidence

**Files:**
- Modify `tests/browser/gate-a.spec.ts` (append one `test(...)` block, mirroring the existing admin-webhooks/API-token specs and reusing the file's `shot`/`visit`/`login` helpers)

**Interfaces:**
- Consumes: `login(page, 'admin@retro.test')`, `visit(page, url)`, `shot(page, info, name)` (`tests/browser/gate-a.spec.ts:24-44`). The evidence web server runs with `MAIL_DRIVER=array` (`tests/browser/playwright.config.ts:36`), so the kernel Mailer is configured (`ArrayMailer`); the `email` flag defaults on (no seed change needed — it is not a new flag).
- Produces: desktop + mobile PNGs at `docs/evidence/browser/<project>/22-admin-email-*.png`.

#### Steps

- [ ] **Step 1: Write the failing test** — append to `tests/browser/gate-a.spec.ts`:

```typescript
test('admin email delivery: dashboard, suppress/remove, and a test-send', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  // The email link appears on the admin dashboard subnav (email flag defaults on).
  await visit(page, '/admin');
  await page.getByRole('link', { name: 'Email' }).click();
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.getByRole('heading', { name: 'Email delivery' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Queue status' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Delivery log' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Suppressed addresses' })).toBeVisible();
  await shot(page, info, '22-admin-email-dashboard');

  // Suppress a unique address (desktop + mobile share one DB), confirm it lists, then remove it.
  const target = `evidence-${info.project.name}-${Date.now()}@example.test`;
  await page.fill('form[action="/admin/email/suppressions"] input[name="email"]', target);
  await page.locator('form[action="/admin/email/suppressions"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/email$/);
  const row = page.locator('table tbody tr', { hasText: target });
  await expect(row).toBeVisible();
  await shot(page, info, '23-admin-email-suppressed');

  await row.getByRole('button', { name: 'Remove' }).click({ force: true });
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.locator('table tbody tr', { hasText: target })).toHaveCount(0);

  // Test-send (transport is the configured ArrayMailer in evidence runs) → flash confirmation.
  await page.locator('form[action="/admin/email/test"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.locator('.flash')).toContainText(/Test email sent/);
  await shot(page, info, '24-admin-email-test-sent');
});
```

- [ ] **Step 2: Run it, expect FAIL** — `cd tests/browser && npm run evidence -- -g "admin email delivery"` — fails before Task 3 (no `Email` link / no `/admin/email`). (Requires `npm install` + `npx playwright install --with-deps chromium` once, and the test DB reachable; this is the evidence harness, not the PHPUnit suite.)

- [ ] **Step 3: Minimal implementation** — no production code; the surface is delivered by Task 3. (If the `Email` link is not found, confirm `templates/admin/dashboard.php` has the `$features['email']` link from Task 3 and that the evidence server is on the migrated seed DB.)

- [ ] **Step 4: Run it, expect PASS** — `cd tests/browser && npm run evidence -- -g "admin email delivery"`; confirm `docs/evidence/browser/*/22-admin-email-dashboard.png`, `23-admin-email-suppressed.png`, `24-admin-email-test-sent.png` exist at both viewports.

- [ ] **Step 5: Commit** — `git add tests/browser/gate-a.spec.ts docs/evidence/browser && git commit -m "Browser evidence: admin email delivery dashboard, suppression, test-send"`

---

## Self-check coverage

- **Coverage 1** (EmailDeliveryRepository `recent`/`count`/`requeue`, clamp+concat LIMIT/OFFSET, bound filters) → **Task 1**
- **Coverage 2** (EmailSuppressionRepository `list`/`count`, clamp+concat) → **Task 1**
- **Coverage 3** (EmailOpsService `sendTest` fail-closed / `manualSuppress`+`unsuppress` with §7.6 cascade in `$db->transaction` / `requeueFailed`; per-mutation audit + WriteGate) → **Task 2** (cascade helpers on SubscriptionRepository land in **Task 1**)
- **Coverage 4** (AdminEmailController: `gate()` on `email` flag → 404; `index`/`test`/`suppress`/`unsuppress`/`export`; `requireAdmin` first; rate-limit; From banner) → **Task 3**
- **Coverage 5** (`templates/admin/email.php`: status cards, filterable log, From banner, test-send form, suppression list add/remove, CSV link, subnav, csrfField, no inline JS/CSS) → **Task 3**
- **Coverage 6** (CSV export via `fputcsv`→`php://temp`, `text/csv` + `Content-Disposition: attachment`, read-only GET) → **Task 3**
- **Coverage 7** (`config/config.php` `rate_limits['email_test']`) → **Task 3**
- **Coverage 8** (`templates/admin/dashboard.php` conditional `Email` nav link) → **Task 3**
- **Coverage 9** (carryover docs: `PHASE_2_STATUS.md:382` rewrite + ADR 0005 with what shipped + what remains deferred) → **Task 5**
- **Coverage 10** (tests: 200/403/302; flag-off 404; configured ArrayMailer test-send → `kind='test'` row marked `sent`; unconfigured blocked banner; suppress→list→remove round trip; subscription cascade; log filters; CSV export headers; rate-limit; same-connection audit COUNT) → **Tasks 1, 2, 3, 4**
- **Coverage 11** (Playwright: load `/admin/email` with log+cards+suppression+banner, perform a test-send and a suppress/remove, desktop+mobile PNGs) → **Task 6**
