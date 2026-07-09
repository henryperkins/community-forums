# Phase 5 Increment 9 — Invitations (P5-13) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the P5-13 invitation lifecycle — admin issuance console, hash-only tokens, atomic redemption bound into registration, email/domain binding, expiry/use limits, an explicit `registration_mode = invite`, audit, rate limits — deploy-dark behind the existing `invitations` flag, with the seven TM-IN threat fixtures implemented and the §13 evidence set (PHPUnit + Playwright + runbook + budgets + ledger).

**Architecture:** Follows the established Phase 5 console pattern (AdminApiTokenController/AdminProviderController): thin controller → `InvitationService` (all rules, one `$db->transaction` per mutation) → `InvitationRepository` (prepared-statement SQL over the existing 0053 tables). Registration-mode interpretation is centralized in one new seam, `App\Security\RegistrationPolicy`, consumed by both `AuthController` and `OAuthService` so the password form and the OAuth provisioning channel can never disagree. Redemption is atomic with account creation: one transaction ordered *validate → bind-check → guarded consume → register → record/grant* so a concurrent loser exits before creating anything and a registration validation failure rolls the consumed use back.

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB (PDO, `EMULATE_PREPARES=false`), PHPUnit (strict), Playwright evidence harness.

## Product decision (Henry, 2026-07-08 — record in `docs/phase5/invitation-defaults.md`)

- `registration_mode` gains an explicit third value: `open | invite | closed`.
- `open`: normal public registration. A *present* valid invite is honored (binding checks + board grant); a *present invalid* invite errors (never silently dropped); absent invite = plain registration.
- `invite`: account creation requires a valid invitation AND `features.invitations = true`.
- `closed`: no new account creation, ever — even with a valid invitation. OAuth provisioning stays blocked (existing behavior, untouched).
- `invite` while the `invitations` flag is dark **fails closed** (effective mode = `closed`).
- Issuance is **admin-only** (TM-IN-07: member issuance denied by default).
- **No role grant via invitation** in Inc 9: `onboarding_role_id` is never issued by the console and never applied at redemption (decision #36 requires "an approved policy"; none is recorded). Board membership grant (`onboarding_board_id`) IS delivered. Explicitly documented, not silently dropped.
- Binding is email **or** domain (mutually exclusive), exact-match domain (no subdomains).
- Tokens: `bin2hex(random_bytes(32))` (64 lowercase hex chars, 256-bit); stored as `sha256` only; shown exactly once at creation (direct render, never cookie flash).
- Expiry always set: default 14 days, range 1–365. `max_uses` default 1, range 1–100.

## Global Constraints

- Deploy-dark: every new route 404s while `features.invitations` is false; a planted valid invitation must be inert while dark (`AppFeatureFlagTest` pin).
- CSRF on every POST via `$this->csrfField()`; no GET mutations; no new CSRF exemptions.
- Strict CSP: no inline `<script>`/`<style>` anywhere in new templates.
- PDO `EMULATE_PREPARES=false`: never bind LIMIT/OFFSET; never reuse a named placeholder.
- UTC everywhere (`UTC_TIMESTAMP()` / `gmdate('Y-m-d H:i:s')`); IPs packed via `inet_pton` into VARBINARY(16).
- Anti-draft-loss: every failed register POST re-renders 422 with `errors` + `old` including the invite token.
- Uniform enumeration responses: missing/expired/revoked/exhausted tokens are indistinguishable (`InvitationService::INVALID_MESSAGE`).
- Raw token never persisted or logged: not in `invitations`, not in `moderation_log` before/after JSON, not in list views (TM-IN-06).
- Admin console + `/invite/*` responses carry `X-Robots-Tag: noindex` (PHASE_5_PLAN §103).
- Tests: strict PHPUnit (≥1 assertion, no output); per-test rollback has **no savepoints** — inner "rollbacks" don't undo rows; use the own-transaction pattern (`$this->pdo->rollBack()` … work … cleanup … `beginTransaction()`, per `PackageInstallBudgetTest`) where real atomicity must be proven.
- Migrations additive-only; next number is **0076**; hand-update `SCHEMA.md` (shape + §9 changelog + version bump) after landing it.
- "Done" requires evidence (DESIGN §13): PHPUnit + Playwright PNGs + runbook + budget measurement + ledger/fixture updates.

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/0076_phase5_invitation_audit.php` (create) | Widen `moderation_log.target_type` ENUM with `invitation` (0075 pattern) |
| `src/Security/RegistrationPolicy.php` (create) | Single seam: configured vs effective registration mode (invite+dark → closed) |
| `src/Repository/InvitationRepository.php` (create) | Prepared-statement SQL over `invitations` + `invitation_redemptions`; guarded `consumeUse` |
| `src/Service/InvitationService.php` (create) | create/list/revoke/preview/redeem rules, audit rows, token hashing |
| `src/Controller/AdminInvitationController.php` (create) | Console: list/create(show-once)/revoke, flag-gated, noindex, issuance rate limit |
| `src/Controller/AuthController.php` (modify) | `/invite/{token}` handler; mode-matrix register GET/POST; invite round-trip |
| `src/Service/OAuthService.php` (modify) | `completeLogin` consumes `RegistrationPolicy`; new `registration_invite_only` action |
| `src/Controller/OAuthController.php` (modify) | Flash message for `registration_invite_only` |
| `src/Service/AdminService.php` (modify) | `REGISTRATION_MODES` ← `RegistrationPolicy::MODES` |
| `src/Core/App.php` (modify) | Container bindings (policy/repo/service) + 4 routes + OAuthService binding update |
| `src/Service/BaselineMetricsService.php` (modify) | `measureInvitationRedemption()` sampler |
| `src/Service/Phase5BudgetReportService.php` (modify) | `invitation.redemption_p95` row |
| `config/config.php` (modify) | `invite_create` + `invite_redeem` rate policies |
| `templates/admin/invitations.php` (create) | Console UI incl. show-once panel |
| `templates/admin/_nav.php` (modify) | Dark-nav entry |
| `templates/admin/dashboard.php` (modify) | `invite` option + dark-flag warning |
| `templates/auth/register.php` (modify) | Mode notices, hidden invite field, form suppression when blocked |
| `tests/Integration/Security/RegistrationPolicyTest.php` (create) | Mode seam |
| `tests/Integration/Service/InvitationServiceTest.php` (create) | Service rules + TM-IN-02..05 (service level) |
| `tests/Integration/Core/AppInvitationsTest.php` (create) | HTTP flows + TM-IN-01/05/06/07 + mode matrix |
| `tests/Integration/Core/AppFeatureFlagTest.php` (modify) | Dark pin for invitation routes/behavior |
| `tests/Integration/Core/AppOAuthTest.php` (modify) | invite-mode provisioning block |
| `tests/Integration/Service/InvitationRedemptionBudgetTest.php` (create) | Budget sampler + report row |
| `tests/browser/invitations.spec.ts` (create) + `tests/browser/seed.php` (modify) | Playwright evidence |
| Docs: `docs/phase5/invitation-defaults.md` (create), `docs/runbooks/invitations.md` (create), `docs/phase5/threat-models/fixtures.json`, `docs/phase5/requirement-ledger.json`, `docs/evidence/phase5/performance-budgets.md`, `docs/evidence/phase5/invitations.md` (create), `docs/evidence/phase5/foundation-f3-f5.md` (F5 note), `SCHEMA.md`, `PHASE_5_STATUS.md`, `CLAUDE.md` (0077), deploy-dark inventory doc | Evidence + process |

Environment: `docker start rb-mariadb` before any `composer test`. Worktree via superpowers:using-git-worktrees, branch `phase5-inc9-invitations`.

---

### Task 1: Migration 0076 — audit target `invitation`

**Files:** Create `database/migrations/0076_phase5_invitation_audit.php`; modify `SCHEMA.md`.

**Interfaces:** Produces ENUM value `'invitation'` usable in `moderation_log.target_type` (consumed by Tasks 3–4 audit rows).

- [ ] **Step 1: Write the migration** (exact 0075 pattern, adding `invitation` to the full current value list)

```php
<?php

declare(strict_types=1);

/**
 * 0076 · Phase 5 Inc 9 (P5-13) — audit target for the invitation lifecycle.
 * Widens `moderation_log.target_type` with `invitation` so issuance/revoke/
 * redemption land in the standing audit trail (mirrors the 0075
 * `identity_provider` widen). Additive; `down()` removes the rows then narrows.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher',
                                      'identity_provider','invitation') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'invitation'");

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher',
                                      'identity_provider') NOT NULL
        SQL);
    }
};
```

- [ ] **Step 2: Apply + verify** — `php bin/console migrate` then `php bin/console migrate:status` shows 0076 applied. (`MigrationLedgerTest` is analyzer-based — no edit needed; run `vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php` to confirm no gap/dupe.)
- [ ] **Step 3: Update `SCHEMA.md`** — moderation_log `target_type` value list, §9 changelog entry ("0076 — widen moderation_log.target_type with invitation (Inc 9)"), version bump; confirm the 0053 `invitations`/`invitation_redemptions` shapes are already documented (they lag-check against the migration).
- [ ] **Step 4: Commit** `feat(phase5): 0076 — moderation_log audit target for invitations`

### Task 2: RegistrationPolicy seam + `invite` mode + OAuth channel

**Files:** Create `src/Security/RegistrationPolicy.php`, `tests/Integration/Security/RegistrationPolicyTest.php`; modify `src/Service/AdminService.php`, `src/Service/OAuthService.php`, `src/Controller/OAuthController.php`, `src/Core/App.php` (bindings), `templates/admin/dashboard.php`, `src/Controller/AdminController.php` (pass flag state), `tests/Integration/Core/AppOAuthTest.php`, `tests/Integration/Core/AppAdminModerationTest.php` (mode persistence).

**Interfaces (produced):**
```php
final class RegistrationPolicy {
    public const MODES = ['open', 'invite', 'closed'];
    public function __construct(private SettingRepository $settings, private FeatureFlags $flags) {}
    public function configuredMode(): string;   // stored value, defaulted/clamped to 'open'
    public function effectiveMode(): string;    // 'invite' → 'closed' while features.invitations is dark
}
```
`AdminService::REGISTRATION_MODES = RegistrationPolicy::MODES;` (validation unchanged otherwise). `OAuthService::completeLogin` returns new action string `'registration_invite_only'` when effective mode is `invite`.

- [ ] **Step 1: Failing tests** — `RegistrationPolicyTest`: stored `open|invite|closed` map to themselves with flag on; `invite`+flag-dark → `effectiveMode()==='closed'` while `configuredMode()==='invite'`; unknown stored value → `open`. `AppOAuthTest`: clone the `registration_closed` test (~line 195): mode=`invite`, flag on → `completeLogin` action `registration_invite_only` (no user row created); mode=`invite`, flag dark → `registration_closed`. `AppAdminModerationTest`: console POST persisting `registration_mode=invite` round-trips; bogus mode still clamps to `open`.
- [ ] **Step 2: Run** — expect failures (class missing / action mismatch).
- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;

/**
 * The single interpretation seam for `registration_mode` (P3-05 setting,
 * P5-13 `invite` value). Both the password form (AuthController) and the
 * OAuth provisioning channel (OAuthService) consume THIS class so the two
 * account-creation paths can never disagree.
 *
 * `invite` requires the `invitations` feature: while the flag is dark the
 * effective mode degrades to `closed` (fail closed — a paused invitation
 * subsystem must not silently reopen public registration, and a configured
 * invite-only site must not admit uninvited members).
 */
final class RegistrationPolicy
{
    public const MODES = ['open', 'invite', 'closed'];

    public function __construct(
        private SettingRepository $settings,
        private FeatureFlags $flags,
    ) {
    }

    public function configuredMode(): string
    {
        $mode = $this->settings->getString('registration_mode', 'open');
        return in_array($mode, self::MODES, true) ? $mode : 'open';
    }

    public function effectiveMode(): string
    {
        $mode = $this->configuredMode();
        if ($mode === 'invite' && !$this->flags->enabled('invitations')) {
            return 'closed';
        }
        return $mode;
    }
}
```

`AdminService`: `public const REGISTRATION_MODES = RegistrationPolicy::MODES;` (+ use-import). `OAuthService`: constructor gains `private RegistrationPolicy $registrationPolicy`; replace the `registration_mode === 'closed'` check with:

```php
$mode = $this->registrationPolicy->effectiveMode();
if ($mode === 'closed') {
    return ['action' => 'registration_closed'];
}
if ($mode === 'invite') {
    // Invite-only sites provision no accounts from a provider identity: the
    // invitation must be redeemed on /register first (returning logins and
    // signed-in linking above are unaffected — neither creates an account).
    return ['action' => 'registration_invite_only'];
}
```

`OAuthController` switch gains:
```php
case 'registration_invite_only':
    $response = $this->redirectWithFlash('/login', 'New accounts require an invitation. Use your invitation link to sign up first, then connect ' . $label . ' from settings.');
    break;
```

`App.php`: bind `RegistrationPolicy` (`fn ($c) => new RegistrationPolicy($c->get(SettingRepository::class), $c->get(FeatureFlags::class))`), add it to the `OAuthService` binding args. `dashboard.php`: annotation map `['open' => '', 'invite' => ' (invitation required)', 'closed' => ' (no new sign-ups)']` replacing the closed-only ternary; below the select, when `($registration_mode ?? '') === 'invite' && empty($invitations_flag_on)`, render `<p class="muted">Registration mode is “invite” but the invitations feature is off — registration is effectively closed.</p>`; `AdminController` passes `'invitations_flag_on' => $this->container->get(FeatureFlags::class)->enabled('invitations')`.
- [ ] **Step 4: Run tests → green**; run neighbouring suites: `vendor/bin/phpunit tests/Integration/Core/AppOAuthTest.php tests/Integration/Core/AppAdminModerationTest.php tests/Integration/Security/RegistrationPolicyTest.php`.
- [ ] **Step 5: Commit** `feat(phase5): explicit invite registration mode via RegistrationPolicy seam (fails closed while dark)`

### Task 3: InvitationRepository + InvitationService (create/list/revoke/preview)

**Files:** Create `src/Repository/InvitationRepository.php`, `src/Service/InvitationService.php`, `tests/Integration/Service/InvitationServiceTest.php`; modify `src/Core/App.php` (bindings).

**Interfaces (produced):**
```php
final class InvitationRepository {
    public function __construct(private Database $db) {}
    public function create(array $data): int;                       // token_hash, created_by, email, domain, onboarding_board_id, max_uses, expires_at
    public function find(int $id): ?array;
    public function findByTokenHash(string $hash): ?array;
    public function all(): array;                                   // newest first, LEFT JOIN users for creator_username
    public function revoke(int $id, int $revokedBy): int;           // WHERE revoked_at IS NULL (idempotent)
    public function consumeUse(int $id): int;                       // guarded UPDATE — the TM-IN-02 gate
    public function recordRedemption(int $invitationId, ?int $userId, ?string $ip): int;
    public function redemptionCount(int $invitationId): int;
}
final class InvitationService {
    public const INVALID_MESSAGE = 'This invitation link is invalid or no longer active.';
    public function __construct(private Database $db, private InvitationRepository $invitations,
        private AuthService $auth, private BoardRepository $boards,
        private BoardMemberRepository $boardMembers, private ModerationLogRepository $log) {}
    public function create(User $admin, array $input): array;       // {id:int, token:string} — raw token returned ONCE
    public function list(): array;                                  // rows + derived 'status' active|revoked|expired|exhausted
    public function revoke(User $admin, int $id): void;
    public function preview(string $rawToken): ?array;              // null = invalid for ANY reason (uniform)
    public function redeem(string $rawToken, array $input, ?string $ip): User;  // Task 4
    public static function hash(string $rawToken): string;
}
```

- [ ] **Step 1: Failing tests** (`InvitationServiceTest`, extends `Tests\Support\TestCase`):
  - `test_create_returns_64_hex_token_and_stores_only_its_sha256` — token matches `/^[0-9a-f]{64}$/`, row `token_hash === hash('sha256', $token)`, raw token not equal to any stored column value.
  - `test_tokens_are_unique_across_creates` — 5 creates, 5 distinct tokens/hashes.
  - `test_create_validates_email_domain_mutual_exclusivity_bounds_and_board` — both email+domain → `ValidationException`; bad email; bad domain (`'not a domain'`); `max_uses` 0 and 101 rejected; `expires_in_days` 0/366 rejected; blank expiry defaults to ~14 days (assert `expires_at` non-null, between +13d and +15d); nonexistent `onboarding_board_id` rejected; `onboarding_role_id` in input is ignored (row stores NULL).
  - `test_create_and_revoke_write_audit_rows_without_raw_token` — `moderation_log` rows `invitation_created`/`invitation_revoked` exist with `target_type='invitation'`, and neither `before_json` nor `after_json` contains the raw token.
  - `test_revoke_is_idempotent_and_second_call_writes_no_second_audit_row`.
  - `test_list_derives_status` — active / revoked / expired (insert with past `expires_at` via repository) / exhausted (`used_count = max_uses`).
  - `test_preview_is_uniform_null_for_missing_expired_revoked_exhausted` — all four return null; valid returns the row.
- [ ] **Step 2: Run to fail.**
- [ ] **Step 3: Implement repository:**

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Single-table SQL over `invitations` + `invitation_redemptions` (0053). */
final class InvitationRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $this->db->run(
            'INSERT INTO invitations (token_hash, created_by, email, domain, onboarding_board_id, max_uses, expires_at)
             VALUES (:token_hash, :created_by, :email, :domain, :board_id, :max_uses, :expires_at)',
            [
                'token_hash' => $data['token_hash'],
                'created_by' => $data['created_by'],
                'email' => $data['email'],
                'domain' => $data['domain'],
                'board_id' => $data['onboarding_board_id'],
                'max_uses' => $data['max_uses'],
                'expires_at' => $data['expires_at'],
            ],
        );
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM invitations WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findByTokenHash(string $hash): ?array
    {
        return $this->db->first('SELECT * FROM invitations WHERE token_hash = :h', ['h' => $hash]);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all(
            'SELECT i.*, u.username AS creator_username
               FROM invitations i
          LEFT JOIN users u ON u.id = i.created_by
           ORDER BY i.id DESC',
        );
    }

    public function revoke(int $id, int $revokedBy): int
    {
        return $this->db->run(
            'UPDATE invitations SET revoked_at = UTC_TIMESTAMP(), revoked_by = :by
              WHERE id = :id AND revoked_at IS NULL',
            ['by' => $revokedBy, 'id' => $id],
        )->rowCount();
    }

    /**
     * The atomic use-consumption gate (TM-IN-02): the WHERE re-checks every
     * validity condition so two racers on the same row serialize on the row
     * lock and at most `max_uses` ever win.
     */
    public function consumeUse(int $id): int
    {
        return $this->db->run(
            'UPDATE invitations SET used_count = used_count + 1
              WHERE id = :id AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                AND used_count < max_uses',
            ['id' => $id],
        )->rowCount();
    }

    public function recordRedemption(int $invitationId, ?int $userId, ?string $ip): int
    {
        $packed = $ip !== null && $ip !== '' ? (inet_pton($ip) ?: null) : null;
        $this->db->run(
            'INSERT INTO invitation_redemptions (invitation_id, user_id, ip, redeemed_at)
             VALUES (:invitation_id, :user_id, :ip, UTC_TIMESTAMP())',
            ['invitation_id' => $invitationId, 'user_id' => $userId, 'ip' => $packed],
        );
        return (int) $this->db->lastInsertId();
    }

    public function redemptionCount(int $invitationId): int
    {
        return (int) ($this->db->first(
            'SELECT COUNT(*) AS c FROM invitation_redemptions WHERE invitation_id = :id',
            ['id' => $invitationId],
        )['c'] ?? 0);
    }
}
```
(Adapt `run/first/all/lastInsertId` to the actual `Database` helper names — copy whichever accessors `IdentityProviderRepository` uses.)

- [ ] **Step 4: Implement service (create/list/revoke/preview + shared validity):**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;

/**
 * Invitation lifecycle (P5-13). Tokens are 256-bit, hash-only at rest, shown
 * once at creation. An invitation is onboarding evidence, NOT authority
 * (decision #36): redemption grants ordinary membership plus at most the
 * stored board membership — never a role. Enumeration responses are uniform
 * (INVALID_MESSAGE) across missing/expired/revoked/exhausted (TM-IN-01).
 */
final class InvitationService
{
    public const INVALID_MESSAGE = 'This invitation link is invalid or no longer active.';
    private const TOKEN_BYTES = 32;
    private const DEFAULT_EXPIRY_DAYS = 14;

    public function __construct(
        private Database $db,
        private InvitationRepository $invitations,
        private AuthService $auth,
        private BoardRepository $boards,
        private BoardMemberRepository $boardMembers,
        private ModerationLogRepository $log,
    ) {
    }

    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** @param array<string,mixed> $input @return array{id:int, token:string} */
    public function create(User $admin, array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $domain = strtolower(ltrim(trim((string) ($input['domain'] ?? '')), '@'));
        $maxUsesRaw = trim((string) ($input['max_uses'] ?? ''));
        $expiresRaw = trim((string) ($input['expires_in_days'] ?? ''));
        $boardRaw = trim((string) ($input['onboarding_board_id'] ?? ''));

        $errors = [];
        $old = ['email' => $email, 'domain' => $domain, 'max_uses' => $maxUsesRaw,
                'expires_in_days' => $expiresRaw, 'onboarding_board_id' => $boardRaw];

        if ($email !== '' && $domain !== '') {
            $errors['domain'] = 'Bind to an email address or a domain, not both.';
        }
        if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255)) {
            $errors['email'] = 'Enter a valid email address to bind, or leave blank.';
        }
        if ($domain !== '' && (strlen($domain) > 190 || preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1)) {
            $errors['domain'] = 'Enter a bare domain like example.com, or leave blank.';
        }
        $maxUses = $maxUsesRaw === '' ? 1 : (int) $maxUsesRaw;
        if ($maxUses < 1 || $maxUses > 100 || ($maxUsesRaw !== '' && (string) $maxUses !== $maxUsesRaw)) {
            $errors['max_uses'] = 'Max uses must be between 1 and 100.';
        }
        $days = $expiresRaw === '' ? self::DEFAULT_EXPIRY_DAYS : (int) $expiresRaw;
        if ($days < 1 || $days > 365 || ($expiresRaw !== '' && (string) $days !== $expiresRaw)) {
            $errors['expires_in_days'] = 'Expiry must be between 1 and 365 days.';
        }
        $boardId = $boardRaw === '' ? null : (int) $boardRaw;
        if ($boardId !== null && $this->boards->find($boardId) === null) {
            $errors['onboarding_board_id'] = 'That board does not exist.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $old);
        }

        // NOTE: onboarding_role_id is deliberately never read from input and
        // never stored by this console — no approved onboarding-role policy
        // exists (decision #36; docs/phase5/invitation-defaults.md).
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $days * 86400);

        $id = $this->db->transaction(function () use ($admin, $token, $email, $domain, $boardId, $maxUses, $expiresAt): int {
            $id = $this->invitations->create([
                'token_hash' => self::hash($token),
                'created_by' => $admin->id(),
                'email' => $email !== '' ? $email : null,
                'domain' => $domain !== '' ? $domain : null,
                'onboarding_board_id' => $boardId,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
            ]);
            // Audit carries the CONSTRAINTS, never the token (TM-IN-06).
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'invitation_created',
                'target_type' => 'invitation',
                'target_id' => $id,
                'after' => ['email' => $email !== '' ? $email : null, 'domain' => $domain !== '' ? $domain : null,
                            'onboarding_board_id' => $boardId, 'max_uses' => $maxUses, 'expires_at' => $expiresAt],
            ]);
            return $id;
        });

        return ['id' => $id, 'token' => $token];
    }

    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $rows = [];
        foreach ($this->invitations->all() as $row) {
            $row['status'] = $this->status($row, $now);
            $rows[] = $row;
        }
        return $rows;
    }

    public function revoke(User $admin, int $id): void
    {
        if ($this->invitations->find($id) === null) {
            throw new NotFoundException('Invitation not found.');
        }
        $this->db->transaction(function () use ($admin, $id): void {
            if ($this->invitations->revoke($id, $admin->id()) === 1) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'invitation_revoked',
                    'target_type' => 'invitation',
                    'target_id' => $id,
                ]);
            }
        });
    }

    /** @return array<string,mixed>|null null = invalid for ANY reason (uniform, TM-IN-01) */
    public function preview(string $rawToken): ?array
    {
        if (preg_match('/^[0-9a-f]{64}$/', $rawToken) !== 1) {
            return null;
        }
        $row = $this->invitations->findByTokenHash(self::hash($rawToken));
        if ($row === null || $this->status($row, gmdate('Y-m-d H:i:s')) !== 'active') {
            return null;
        }
        return $row;
    }

    /** @param array<string,mixed> $row */
    private function status(array $row, string $nowUtc): string
    {
        if ($row['revoked_at'] !== null) {
            return 'revoked';
        }
        if ($row['expires_at'] !== null && (string) $row['expires_at'] <= $nowUtc) {
            return 'expired';
        }
        if ((int) $row['used_count'] >= (int) $row['max_uses']) {
            return 'exhausted';
        }
        return 'active';
    }
}
```

- [ ] **Step 5: Bind in `App::buildContainer`** next to the other Phase 5 repos/services:
```php
$c->bind(InvitationRepository::class, fn ($c) => new InvitationRepository($c->get(Database::class)));
$c->bind(InvitationService::class, fn ($c) => new InvitationService(
    $c->get(Database::class), $c->get(InvitationRepository::class), $c->get(AuthService::class),
    $c->get(BoardRepository::class), $c->get(BoardMemberRepository::class), $c->get(ModerationLogRepository::class),
));
```
- [ ] **Step 6: Run tests → green. Commit** `feat(phase5): invitation repository + service — issuance, revoke, uniform preview, audit`

### Task 4: `redeem()` — atomic redemption + TM-IN-02..05 (service level)

**Files:** Modify `src/Service/InvitationService.php`; extend `tests/Integration/Service/InvitationServiceTest.php`.

**Interfaces:** `redeem(string $rawToken, array $input, ?string $ip): User` — throws `ValidationException` (uniform `['invite' => INVALID_MESSAGE]`, or field errors from binding/registration with `old` preserving typed input).

**Ordering rationale (bake into a comment):** consume BEFORE register — a concurrent loser exits before creating anything; a registration validation failure throws after consume, and the wrapping transaction restores the use. Tests run inside the harness rollback transaction (no savepoints), so any assertion about restored `used_count` after an inner failure MUST use the own-transaction pattern.

- [ ] **Step 1: Failing tests:**
  - `test_redeem_creates_member_records_redemption_and_grants_board` — invitation with `onboarding_board_id`; redeem with valid register input + ip `'203.0.113.9'`; assert returned User role `user`, `used_count=1`, one redemption row (user_id set), `BoardMemberRepository::isMember` true, audit row `invitation_redeemed` with `actor_id = new user id`.
  - `test_redeem_rejects_bound_email_mismatch_and_preserves_draft` — email-bound invitation; mismatched submitted email → `ValidationException` with `errors['email']`, `old['username']` preserved; `used_count` stays 0 (binding check precedes consume — assertable inside harness txn).
  - `test_redeem_rejects_bound_domain_mismatch_including_subdomains` — domain `example.com`: `a@other.com` and `a@sub.example.com` rejected; `a@EXAMPLE.com` accepted (case-insensitive).
  - `test_redeem_expired_revoked_exhausted_all_yield_uniform_error_and_no_account` — three invitations in each state; redeem each → `errors['invite'] === INVALID_MESSAGE` for all; `UserRepository::emailExists(...)` false (register never ran — consume/validity precede it, assertable in harness txn) (TM-IN-03).
  - `test_redeem_never_applies_onboarding_role` — plant invitation row with `onboarding_role_id` = id of a `roles` row (insert a custom role directly); redeem; assert created user's `users.role === 'user'` and `user_role_assignments` (or the actual assignment table from 0050) has no row for the new user (TM-IN-05, DB-planted half).
  - `test_single_use_token_admits_exactly_one_account_with_real_transactions` — **own-transaction pattern**: `$this->pdo->rollBack();` then: create single-use invitation via service, redeem once (commit path) → account A exists; redeem again with different email/username → uniform invite error AND second account does not exist (`emailExists` false — the real rollback undid `register`); also assert `used_count === 1`, redemption count 1. `finally`: DELETE created users/invitation/redemption/moderation_log rows by tracked ids/emails, then `$this->pdo->beginTransaction();` (TM-IN-02).
  - In the same own-transaction test, before cleanup: redeem a *third* time with an input that fails registration validation (password too short) against a fresh 2-use invitation → after the failure `used_count` is unchanged (real rollback restored the consumed use).
- [ ] **Step 2: Run to fail.**
- [ ] **Step 3: Implement:**

```php
    /**
     * Atomic redemption + registration (§8.5). Single transaction ordered:
     *   validate row (uniform) → binding check → guarded consumeUse →
     *   AuthService::register → redemption row → board grant → audit.
     * Consume-before-register means a concurrent loser exits before creating
     * anything, and a registration validation failure rolls the consumed use
     * back with the transaction. onboarding_role_id is NEVER applied
     * (decision #36 — no approved onboarding-role policy; TM-IN-05).
     *
     * @param array<string,mixed> $input @throws ValidationException
     */
    public function redeem(string $rawToken, array $input, ?string $ip): User
    {
        $old = [
            'username' => trim((string) ($input['username'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'display_name' => trim((string) ($input['display_name'] ?? '')),
            'invite' => $rawToken,
        ];

        return $this->db->transaction(function () use ($rawToken, $input, $ip, $old): User {
            $row = $this->preview($rawToken);
            if ($row === null) {
                throw new ValidationException(['invite' => self::INVALID_MESSAGE], $old);
            }

            $email = strtolower(trim((string) ($input['email'] ?? '')));
            if ($row['email'] !== null && strcasecmp((string) $row['email'], $email) !== 0) {
                throw new ValidationException(['email' => 'This invitation is for a different email address.'], $old);
            }
            if ($row['domain'] !== null) {
                $at = strrpos($email, '@');
                $submittedDomain = $at === false ? '' : substr($email, $at + 1);
                if (strcasecmp($submittedDomain, (string) $row['domain']) !== 0) {
                    throw new ValidationException(['email' => 'This invitation requires an email address at ' . $row['domain'] . '.'], $old);
                }
            }

            if ($this->invitations->consumeUse((int) $row['id']) !== 1) {
                // Lost a concurrent race (or state changed since preview).
                throw new ValidationException(['invite' => self::INVALID_MESSAGE], $old);
            }

            $user = $this->auth->register($input);

            $this->invitations->recordRedemption((int) $row['id'], $user->id(), $ip);
            if ($row['onboarding_board_id'] !== null && $this->boards->find((int) $row['onboarding_board_id']) !== null) {
                $this->boardMembers->add((int) $row['onboarding_board_id'], $user->id(), $row['created_by'] !== null ? (int) $row['created_by'] : null);
            }
            $this->log->log([
                'actor_id' => $user->id(),
                'action' => 'invitation_redeemed',
                'target_type' => 'invitation',
                'target_id' => (int) $row['id'],
                'after' => ['user_id' => $user->id()],
            ]);
            return $user;
        });
    }
```
(`AuthService::register` throws `ValidationException(errors, old-without-invite)` — controller re-adds the invite token to `old` (Task 6); service-level callers get the register exception as-is. If keeping the invite in `old` at the service boundary matters for the controller, catch and rethrow: `catch (ValidationException $e) { throw new ValidationException($e->errors, $e->old + ['invite' => $rawToken]); }` around the `register` call — do this so every 422 path preserves the token.)
- [ ] **Step 4: Run → green. Full file:** `vendor/bin/phpunit tests/Integration/Service/InvitationServiceTest.php`
- [ ] **Step 5: Commit** `feat(phase5): atomic invitation redemption — bindings, guarded consume, board grant, no role application`

### Task 5: Admin console — `/admin/invitations`

**Files:** Create `src/Controller/AdminInvitationController.php`, `templates/admin/invitations.php`; modify `src/Core/App.php` (routes), `templates/admin/_nav.php`, `config/config.php` (`invite_create`), `tests/Integration/Core/AppInvitationsTest.php` (create file with console coverage).

**Interfaces:** Routes `GET /admin/invitations`, `POST /admin/invitations`, `POST /admin/invitations/{id}/revoke`. View vars: `rows`, `boards`, `errors`, `old`, `new_invitation` (`['token' => string, 'url' => string]|null`).

- [ ] **Step 1: Failing tests** (in new `AppInvitationsTest`; enable flag via `(new SettingRepository($this->db))->set('features', ['invitations' => true])` helper method `enableInvitations(array $extra = [])`):
  - `test_console_requires_admin` — guest → 302 `/login`; member and moderator → 403 on GET and POST (TM-IN-07 first half).
  - `test_console_404_while_dark` — admin, flag off → GET/POST 404.
  - `test_create_shows_raw_token_exactly_once_and_never_persists_it` — POST create → 200 body contains a `/^[0-9a-f]{64}$/` token and the `/invite/<token>` URL; follow-up GET body does NOT contain the token; DB scan: token absent from every `invitations` column and from `moderation_log.after_json` (TM-IN-06).
  - `test_create_validation_rerenders_422_with_old` — both email+domain → 422, typed values present in body.
  - `test_revoke_marks_row_and_audits` — POST revoke → redirect; list shows Revoked; `moderation_log` has `invitation_revoked`.
  - `test_issuance_is_rate_limited` — with real `RateLimiter` config `invite_create => [3, 3600]` via config override (construct App with modified config, mirroring `withCapabilitiesEnforced`'s config-rebuild pattern) → 4th create 429 (TM-IN-07 second half).
  - `test_console_responses_carry_noindex`.
- [ ] **Step 2: Run to fail.**
- [ ] **Step 3: Implement controller** (AdminApiTokenController show-once pattern + provider gate/noindex pattern):

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Service\InvitationService;
use App\Service\RateLimitService;

/**
 * Operator console for the invitation lifecycle (P5-13), behind the dark
 * `invitations` flag. Issuance is admin-only (TM-IN-07). The raw token is
 * rendered DIRECTLY in the create response — exactly once, never via the
 * cookie-backed Flash (which would leak it into a Set-Cookie header).
 */
final class AdminInvitationController extends Controller
{
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
            $this->container->get(RateLimitService::class)->enforce('invite_create', $request, $admin);
        } catch (HttpException) {
            return $this->consoleView(['create' => 'Too many invitations created just now. Please wait before issuing more.'], $this->oldCreate($request), 429);
        }

        try {
            $result = $this->container->get(InvitationService::class)->create($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old, 422);
        }

        $base = rtrim((string) $this->container->get(Config::class)->get('app.url', ''), '/');
        return $this->consoleView([], [], 200, [
            'token' => $result['token'],
            'url' => $base . '/invite/' . $result['token'],
        ]);
    }

    /** @param array<string,string> $params */
    public function revoke(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(InvitationService::class)->revoke($admin, (int) ($params['id'] ?? 0));
        return $this->noindex($this->redirectWithFlash('/admin/invitations', 'Invitation revoked.'));
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @return array<string,string> */
    private function oldCreate(Request $request): array
    {
        return [
            'email' => $request->str('email'),
            'domain' => $request->str('domain'),
            'max_uses' => $request->str('max_uses'),
            'expires_in_days' => $request->str('expires_in_days'),
            'onboarding_board_id' => $request->str('onboarding_board_id'),
        ];
    }

    /**
     * @param array<string,string> $errors @param array<string,mixed> $old
     * @param array{token:string,url:string}|null $newInvitation
     */
    private function consoleView(array $errors = [], array $old = [], int $status = 200, ?array $newInvitation = null): Response
    {
        return $this->noindex($this->view('admin/invitations', [
            'rows' => $this->container->get(InvitationService::class)->list(),
            'boards' => $this->container->get(BoardRepository::class)->allOrdered(),
            'errors' => $errors,
            'old' => $old,
            'new_invitation' => $newInvitation,
        ], $status));
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }
}
```

- [ ] **Step 4: Template `templates/admin/invitations.php`** (match `admin/api_tokens.php` structure/classes — check it first and reuse its section/heading/table markup):
  - Show-once panel when `new_invitation`: the raw URL + token in a `<code>` block, copy-by-hand hint, "This link is shown once — store it now."
  - Create form: email (optional), domain (optional), max_uses (default 1), expires_in_days (default 14), board `<select>` from `$boards` (`allOrdered()`, option value id, label name) with a blank "No board grant" default; `$this->csrfField()`; field errors under each input; `create` top-level error slot.
  - Table: ID, Created (`human_datetime`), Creator (`creator_username`), Binding (email/domain/"—"), Uses (`used_count`/`max_uses`), Expires, Status badge (active/revoked/expired/exhausted), Revoke button (its own POST form + csrf) only for status `active`.
  - No raw tokens anywhere in the table (only `new_invitation` shows one).
- [ ] **Step 5: Wire** — routes after the api-tokens cluster in `App::buildRouter` (`$r->get('/admin/invitations', [AdminInvitationController::class, 'index']);` etc.); `_nav.php` `$dark[]` row `['key' => 'invitations', 'label' => 'Invitations', 'href' => '/admin/invitations', 'flag' => 'invitations']`; config policy `'invite_create' => [30, 3600],` next to `register`.
- [ ] **Step 6: Run console tests → green. Commit** `feat(phase5): admin invitations console — issue (show-once), list, revoke, rate-limited, noindex`

### Task 6: Public redemption — `/invite/{token}` + register integration + dark pins

**Files:** Modify `src/Controller/AuthController.php`, `templates/auth/register.php`, `src/Core/App.php` (route), `config/config.php` (`invite_redeem`); extend `tests/Integration/Core/AppInvitationsTest.php`, `tests/Integration/Core/AppFeatureFlagTest.php`.

**Interfaces:** `GET /invite/{token}` → noindex 302 to `/register?invite=<token>`. Register view vars change: `registration_mode` (effective), `invite_token` (string), `invite_valid` (bool), `registration_blocked` (bool) — replacing `registration_closed` (grep for other usages; keep the closed notice text and the POST-closed 403 message `'Registration is currently closed.'` verbatim).

- [ ] **Step 1: Failing tests:**
  - `test_invite_landing_redirects_to_register_and_is_noindex` — flag on: GET `/invite/<64hex>` → 302 `/register?invite=…` + `X-Robots-Tag: noindex`; flag off → 404 (also covered in flag test).
  - `test_register_get_shows_uniform_banner_for_all_invalid_reasons` — bogus, expired, revoked, exhausted tokens → 200 body contains `INVALID_MESSAGE` exactly, and the *same* body-relevant markup (no reason leak); valid token → invite welcome banner, hidden input carries token.
  - `test_invite_probing_is_rate_limited` — config override `invite_redeem => [3, 900]`; 4th GET `/invite/...` (distinct bogus tokens) → 429 (TM-IN-01, with hash-only-at-rest asserted in Task 5's TM-IN-06 test).
  - `test_full_invite_registration_flow` — mode `invite`, flag on: POST /register with valid invite + fields → 302 `/`, logged in (GET / shows username), board membership granted, `used_count=1`.
  - `test_invite_mode_blocks_missing_and_invalid_tokens` — POST without invite → 403 with invite-required message; with invalid → 422 uniform message; no accounts created.
  - `test_closed_mode_blocks_even_valid_invitations` — mode `closed`, flag on, valid token: GET shows closed notice (no invite banner), POST → 403 `'Registration is currently closed.'`, `used_count` stays 0.
  - `test_invite_mode_with_dark_flag_fails_closed` — mode `invite`, flag off, valid token planted → POST 403 closed message; `used_count` 0.
  - `test_open_mode_honors_valid_invite_and_errors_on_invalid` — mode `open`, flag on: valid invite → account + board grant; invalid invite present → 422 uniform error, no account.
  - `test_forged_post_grants_are_ignored` — valid unbound invite, POST includes `role=admin&onboarding_role_id=1&onboarding_board_id=<other>` → account role `user`, no assignment rows, membership ONLY in the invitation's stored board (TM-IN-05 HTTP half).
  - `test_validation_failure_preserves_draft_and_invite` — short password → 422, body keeps username/email values AND hidden invite token (anti-draft-loss).
  - In `AppFeatureFlagTest`: `test_invitations_flag_gates_invitation_routes` — dark: `/admin/invitations` 404 (as admin), `/invite/<hex>` 404, and with mode `open` + planted valid invitation a plain POST /register?invite succeeds as an ORDINARY registration with `used_count` still 0 (token ignored while dark); flag on: routes live.
- [ ] **Step 2: Run to fail.**
- [ ] **Step 3: Implement `AuthController`:**

```php
    /** Public invite landing: normalize into the register flow (P5-13). */
    public function invite(Request $request, array $params): Response
    {
        $this->gateInvitations();
        try {
            $this->container->get(RateLimitService::class)->enforce('invite_redeem', $request);
        } catch (HttpException $e) {
            throw $e; // 429 via kernel error page — probing gets no free retries.
        }
        $token = (string) ($params['token'] ?? '');
        return $this->redirect('/register?invite=' . urlencode($token))->header('X-Robots-Tag', 'noindex');
    }

    private function gateInvitations(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @return array{token:string, valid:bool, row:array<string,mixed>|null} */
    private function inviteContext(string $token): array
    {
        if ($token === '' || !$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            return ['token' => '', 'valid' => false, 'row' => null];
        }
        $row = $this->container->get(InvitationService::class)->preview($token);
        return ['token' => $token, 'valid' => $row !== null, 'row' => $row];
    }
```

`showRegister` becomes:
```php
    public function showRegister(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        $mode = $this->container->get(RegistrationPolicy::class)->effectiveMode();
        $invite = $this->inviteContext((string) $request->query('invite', ''));
        if ($invite['token'] !== '') {
            try {
                $this->container->get(RateLimitService::class)->enforce('invite_redeem', $request);
            } catch (HttpException) {
                return $this->registerView($mode, $invite, ['invite' => 'Too many invitation attempts. Please try again later.'], [], 429);
            }
        }
        return $this->registerView($mode, $invite, $invite['token'] !== '' && !$invite['valid'] ? ['invite' => InvitationService::INVALID_MESSAGE] : [], []);
    }

    /** @param array{token:string, valid:bool, row:array<string,mixed>|null} $invite */
    private function registerView(string $mode, array $invite, array $errors, array $old, int $status = 200): Response
    {
        $blocked = $mode === 'closed' || ($mode === 'invite' && !$invite['valid']);
        $response = $this->view('auth/register', [
            'errors' => $errors,
            'old' => $old,
            'registration_mode' => $mode,
            'invite_token' => $invite['valid'] ? $invite['token'] : '',
            'invite_valid' => $invite['valid'],
            'registration_blocked' => $blocked,
        ], $status);
        return $invite['token'] !== '' ? $response->header('X-Robots-Tag', 'noindex') : $response;
    }
```

`register` (POST) becomes:
```php
    public function register(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        $mode = $this->container->get(RegistrationPolicy::class)->effectiveMode();
        $inviteToken = $this->container->get(FeatureFlags::class)->enabled('invitations')
            ? trim((string) $request->post('invite', ''))
            : '';
        $invite = $this->inviteContext($inviteToken);

        // Registration mode (P3-05 / P5-13). `closed` is absolute — a valid
        // invitation does not reopen it; `invite` requires a token.
        if ($mode === 'closed') {
            return $this->registerView($mode, ['token' => '', 'valid' => false, 'row' => null],
                ['email' => 'Registration is currently closed.'], [], 403);
        }
        if ($mode === 'invite' && $inviteToken === '') {
            return $this->registerView($mode, $invite,
                ['invite' => 'Registration is by invitation only. Use your invitation link to sign up.'], $this->oldRegister($request), 403);
        }

        $limiter = $this->container->get(RateLimitService::class);
        if ($inviteToken !== '') {
            try {
                $limiter->enforce('invite_redeem', $request);
            } catch (HttpException) {
                return $this->registerView($mode, $invite, ['invite' => 'Too many invitation attempts. Please try again later.'], $this->oldRegister($request) + ['invite' => $inviteToken], 429);
            }
        }
        try {
            $limiter->enforce('register', $request);
        } catch (HttpException) {
            return $this->registerView($mode, $invite, ['email' => 'Too many sign-up attempts from your network. Please try again later.'], $this->oldRegister($request) + ['invite' => $inviteToken], 429);
        }

        try {
            $user = $inviteToken !== ''
                ? $this->container->get(InvitationService::class)->redeem($inviteToken, $request->allInput(), $request->ip())
                : $this->container->get(AuthService::class)->register($request->allInput());
        } catch (ValidationException $e) {
            $stillValid = $this->inviteContext($inviteToken);
            return $this->registerView($mode, $stillValid, $e->errors, $e->old + ['invite' => $inviteToken], 422);
        }

        $this->session()->login($user);
        $this->container->get(EmailVerificationService::class)->issue($user->id(), $user->email());
        return $this->redirectWithFlash('/', 'Welcome to the community, ' . $user->displayName() . '! Please check your email to verify your address.');
    }
```
(Remove the now-unused `registrationClosed()`; add imports. Grep `registration_closed` across templates/tests and update every usage of the view var.)
- [ ] **Step 4: Template `auth/register.php`:** replace the closed notice block with the matrix:
```php
    <?php if (($registration_mode ?? 'open') === 'closed'): ?>
        <p class="notice" role="status">New sign-ups are currently closed. Please check back later or contact an administrator.</p>
    <?php elseif (!empty($errors['invite'])): ?>
        <p class="notice" role="alert"><?= $e($errors['invite']) ?></p>
    <?php elseif (($registration_mode ?? 'open') === 'invite' && empty($invite_valid)): ?>
        <p class="notice" role="status">Registration is by invitation only. Use your invitation link to sign up.</p>
    <?php elseif (!empty($invite_valid)): ?>
        <p class="notice" role="status">You’ve been invited to join this community. Complete the form to accept your invitation.</p>
    <?php endif; ?>
    <?php if (empty($registration_blocked)): ?>
    <form method="post" action="/register" class="auth-form">
        <?= $this->csrfField() ?>
        <?php if (!empty($invite_token)): ?><input type="hidden" name="invite" value="<?= $e($invite_token) ?>"><?php endif; ?>
        …existing fields unchanged…
    </form>
    <?php endif; ?>
```
with `old['invite']` also feeding `invite_token` on 422 re-renders (controller passes it via `$stillValid`/`old`; hidden field uses `$e($invite_token !== '' ? $invite_token : ($old['invite'] ?? ''))`).
- [ ] **Step 5: Wire** — route `$r->get('/invite/{token}', [AuthController::class, 'invite']);` beside `/register`; config `'invite_redeem' => [30, 900],`.
- [ ] **Step 6: Run the two test files → green; also `vendor/bin/phpunit --testsuite integration` for regressions (auth/OAuth/admin tests all touch register). Commit** `feat(phase5): invite redemption through /register — mode matrix, uniform errors, rate limits, dark pins`

### Task 7: Redemption performance budget

**Files:** Modify `src/Service/BaselineMetricsService.php`, `src/Service/Phase5BudgetReportService.php`; create `tests/Integration/Service/InvitationRedemptionBudgetTest.php`; update `docs/evidence/phase5/performance-budgets.md` + ledger GA-DOD-18 note (Task 8 groups doc edits — measurement lands here).

- [ ] **Step 1: Failing test** mirroring `PackageInstallBudgetTest`: `measureInvitationRedemption` returns `samples`, `p95 > 0`, `p95 < 500.0` sanity; report `rows()` gains `invitation.redemption_p95` with status `MEASURED…` when the service is wired; `render()` mentions the row.
- [ ] **Step 2: Implement sampler** in `BaselineMetricsService` (mirror `measureOidcDiscovery`'s shape + the install sampler's caller-owned-rollback guard): per iteration — `InvitationService::create` (admin fixture) then `redeem` with unique `user{i}@budget.test` inputs; collect ms timings; return p50/p95/p99 array. Wire `Phase5BudgetReportService` constructor + `rows()` branch exactly like the `oidc.discovery_p95_*` entries (~line 208).
- [ ] **Step 3: Run → green.** Record the measured p95 number for the docs task.
- [ ] **Step 4: Commit** `feat(phase5): invitation.redemption_p95 sampler + budget report row`

### Task 8: Threat fixtures, ledger, decision doc, runbook, F5 note

**Files:** Modify `docs/phase5/threat-models/fixtures.json` (7 × `status: "implemented"` + `"test": "tests/Integration/…"` paths per fixture), `docs/phase5/threat-models/invitation-privilege.md` (status line → implemented Inc 9), `docs/phase5/requirement-ledger.json` (GA-DOD-16 → R4 with evidence list + notes; GA-DOD-18 note + invitation row measured; F5 notes), `docs/evidence/phase5/performance-budgets.md` (row → `MEASURED (PASS)` with the Task 7 number), `docs/evidence/phase5/foundation-f3-f5.md` (F5 wiring line: Inc 9 shipped no role-granting redemption → no LastOwnerGuard mutation site; hook deferred until an approved onboarding-role policy exists); create `docs/phase5/invitation-defaults.md`, `docs/runbooks/invitations.md`.

- [ ] **Step 1: fixtures.json** — TM-IN-01→`AppInvitationsTest.php`, TM-IN-02→`InvitationServiceTest.php`, TM-IN-03→`InvitationServiceTest.php`, TM-IN-04→`InvitationServiceTest.php`, TM-IN-05→`AppInvitationsTest.php`, TM-IN-06→`AppInvitationsTest.php`, TM-IN-07→`AppInvitationsTest.php`. Run `vendor/bin/phpunit tests/Unit/Core/ThreatModelIndexTest.php` (it enforces the shape/paths).
- [ ] **Step 2: `invitation-defaults.md`** — record the product-decision block from this plan's header verbatim (modes, fail-closed, admin-only, no-role, binding XOR, entropy, expiry/use defaults, open-mode invalid-token-errors rationale), owner-accepted 2026-07-08.
- [ ] **Step 3: `docs/runbooks/invitations.md`** — mirror `docs/runbooks/provider_registry.md` structure: what it is; enable playbook (set flag → optionally set mode invite → issue first invite → verify redemption); **invitation pause** (flag off = decision #40 independent disable; effect matrix incl. invite-mode-fails-closed); revoke-all guidance (SQL + console); rate-limit tuning; token hygiene (hash-only, show-once, no logs); troubleshooting table (bound-email mismatch, expired, exhausted, 429s); rollback (flag off — no schema change needed).
- [ ] **Step 4: Ledger** — GA-DOD-16 → `"state": "R4"`, evidence: migration, repo/service/controller files, the three test files, browser spec, runbook, defaults doc; notes summarizing TM-IN-01..07 implementation + deploy-dark R5-at-enablement. GA-DOD-18: append invitation measurement note.
- [ ] **Step 5: Commit** `docs(phase5): Inc 9 fixtures implemented, GA-DOD-16 R4, invitation defaults + runbook`

### Task 9: Browser evidence + a11y

**Files:** Create `tests/browser/invitations.spec.ts`; modify `tests/browser/seed.php` (`'invitations' => true` in `$evidenceFeatures` with an Inc 9 comment, plus seed one expired + one revoked invitation row for list variety); create `docs/evidence/phase5/invitations.md`; update the evidence index (wherever `oidc-provider-registry.md` PNGs 66–68 are indexed — continue numbering from the current max).

- [ ] **Step 1: Read `tests/browser/providers.spec.ts` + `api-tokens.spec.ts` first** — reuse: EVIDENCE_DIR/screenshot helper, login helper, **theme safe-mode neutralization** (theme-bleed trap), `page.context().clearCookies()` before any second same-page login (user-switch trap).
- [ ] **Step 2: Scenes** (desktop + mobile projects, PNG per scene): (1) admin console list incl. seeded expired/revoked statuses; (2) create → show-once token panel; (3) logged-out `/invite/<token>` → register with invite banner (server-rendered, no JS dependence); (4) submit registration → landed home as new member; (5) invalid token → uniform banner; (6) mode=invite without token → blocked register (set mode via admin dashboard UI or seed, restore after); (7) basic a11y pass on console + invite register (mirror whichever axe/a11y helper providers/api-tokens use, if any — else keyboard-focus screenshot).
- [ ] **Step 3: Run** `cd tests/browser && npm run evidence -- --grep invitations` (or the project's spec-file invocation used for `wysiwyg-composer.spec.ts` per memory); copy PNGs into `docs/evidence/phase5/` naming per index convention; write `invitations.md` describing each PNG; remember the 3 pre-existing gate-a failures (326/943/1005) are NOT ours.
- [ ] **Step 4: Commit** `test(phase5): invitations browser evidence + a11y (desktop/mobile)`

### Task 10: Status/docs closeout, full verification, review, PR

**Files:** Modify `PHASE_5_STATUS.md` (Inc 9 section: scope, decision link, TM coverage, budgets, deferred: onboarding-role grants pending approved policy, member-issuance setting, OAuth-carried invites), `CLAUDE.md` (next migration number → 0077), deploy-dark inventory doc (`docs/…deploy-dark-features.md` — add/annotate `invitations` row; find exact path via `grep -rln "deploy-dark" docs/`), `docs/evidence/phase5/performance-budgets.md` cross-check.

- [ ] **Step 1:** Docs edits above.
- [ ] **Step 2:** `composer test` full suite **twice in a row** (reused-schema trap from memory — second plain run must stay green), plus `php bin/console verify:upgrade` (through 0076) on the scratch DB.
- [ ] **Step 3:** superpowers:requesting-code-review / `/code-review` at max effort on the branch diff; fix findings TDD-style; re-run suite.
- [ ] **Step 4:** Push branch, open PR (`gh pr create`) titled `feat(phase5): Inc 9 — P5-13 invitations (invite-mode registration, console, atomic redemption)` with evidence summary; body ends with the standard generated-with footer.
- [ ] **Step 5: Commit any doc stragglers** `docs(phase5): Inc 9 closeout — status, inventory, migration counter`

## Self-Review (performed at write time)

1. **Spec coverage** — P5-13 row: create/revoke/redeem ✓(T3–T6), email/domain binding ✓(T4), expiry/use limits ✓(T3/T4), registration integration ✓(T2/T6), anti-abuse/rate limits ✓(T5/T6), optional board grant ✓(T4), onboarding-role grant → explicitly deferred with recorded decision (T8, decision #36 "approved policy" absent), audit ✓(T1/T3/T4), token entropy/hash/replay/concurrency ✓(T3/T4), expired/revoked/domain-mismatch ✓(T4/T6), privilege-injection denial ✓(T4/T6), browser/no-JS ✓(T9 — flows are server-rendered forms), noindex ✓(T5/T6), budgets ✓(T7), runbook/pause ✓(T8), fixtures/ledger ✓(T8), dark-by-default + flag pin ✓(T6).
2. **Placeholder scan** — the only intentionally deferred-to-execution lookups are named concretely (Database helper method names, budget `rows()` mirror at ~line 208, evidence index path, a11y helper choice), each with the exact precedent file to copy from.
3. **Type consistency** — `redeem(string,array,?string): User`; `preview(string): ?array`; `create(User,array): array{id,token}`; consumed consistently in T5/T6 controllers and T7 sampler.
