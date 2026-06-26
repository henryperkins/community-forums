# RetroBoards — Phase 1 Completion & Evidence Index

**Status:** Phase 1 (MVP backend) implemented and verified.
**Date:** 2026-06-26 · **Owner:** Henry (lakefrontdigital.io)

This document is the release-evidence index required by `PHASE_1_PLAN.md` §7. It
maps every definition-of-done item to where it is satisfied and tested, records
the automated/HTTP evidence, and documents the rollback procedure.

## How to verify locally

```bash
composer install
cp .env.example .env          # set DB_*, then: php bin/console key:generate
php bin/console migrate       # 10 migrations on an empty DB
composer test                 # full PHPUnit suite (unit + integration)
```

The suite provisions nothing destructive against your dev DB — integration tests
run against `retroboards_test` (override with `DB_TEST_DATABASE`) and roll back
each test in a transaction.

## Architecture at a glance

- `public/index.php` — front controller; serves static files under `public/assets`
  directly, routes everything else through the kernel.
- `src/Core/App.php` — kernel: builds a per-request service container, runs the
  pipeline (session → setup gate → CSRF → route dispatch), applies security
  headers. `handle(Request): Response` is pure, so the whole stack is tested
  in-process.
- `src/Core/{Router,Request,Response,View,Database,Migrator,Flash}.php` — framework.
- `src/Security/*` — `Session`, `Csrf`, `WriteGate`, `BoardPolicy`,
  `PasswordHasher`, `SecurityHeaders`, rate limiters.
- `src/Repository/*` — PDO data access (prepared statements only).
- `src/Service/*` — `AuthService`, `AccountService`, `PostingService`,
  `ModerationService`, `AdminService`, `SetupService`.
- `src/Controller/*` — thin HTTP controllers.
- `src/Support/{Markdown,HtmlSanitizer,Str}.php` — Markdown render + allowlist
  sanitizer + slug/monogram helpers.
- `templates/*` — server-rendered, no-JS views (three-pane shell).
- `database/migrations/0001..0010` — the Phase-1 lean schema cut.

## Definition-of-done traceability

| DoD item (`PHASE_1_PLAN.md` §2) | Where satisfied | Test evidence |
|---|---|---|
| Every core read/write journey works server-rendered, without JS | All templates are plain `<form>`/links; no flow depends on `app.js` | `AppTest`, `AppPostingTest`, `AuthControllerTest` |
| Fresh DB migrates + initializes via `/setup` without manual SQL | `Migrator`, `SetupService`, `/setup` | `AppSetupTest`, `SetupServiceTest`, migration run |
| Registration, login, logout, sessions, CSRF, password handling | `AuthService`, `Session`, `Csrf`, `AccountService` | `AuthControllerTest`, `AppUserSettingsTest` |
| Guests read public content but cannot write | `BoardPolicy`, `requireUser`, join-bar | `AppTest::test_guest_can_read…`, `AppPostingTest::test_guest_cannot_create_thread` |
| Users create threads/replies, edit + soft-delete own content | `PostingService` | `AppPostingTest` |
| Stored content rendered safely; raw HTML/scripts can't bypass sanitization | `Markdown` + `HtmlSanitizer` | `SanitizationTest`, `AppPostingTest::test_xss…` |
| Locked threads reject replies | `PostingService::reply` | `AppPostingTest::test_locked_thread…`, `AppModerationTest` |
| Suspended/banned blocked from every write path incl. stale sessions | `WriteGate` (centralized) | `AppWriteGateTest` |
| Admins manage community name, categories, boards | `AdminService`, `AdminController` | `AppAdminTest` |
| Admins pin/unpin, lock/unlock, soft-delete any post | `ModerationService` | `AppModerationTest` |
| Every moderation action written to `moderation_log` | `ModerationService`, `AdminService` | `AppModerationTest`, `AppAdminTest`, `SetupServiceTest` |
| `/healthz` with database status | `HealthController` | `AppTest::test_healthz…` |
| Full automated suite passes; UI flows have evidence | PHPUnit + HTTP smoke | this document |
| No unresolved critical/high security/data-integrity defect | adversarial review (below) | §"Security review" |

## Critical acceptance scenarios (`PHASE_1_PLAN.md` §6)

Each row is exercised by an automated test:

| Scenario | Test |
|---|---|
| Fresh install → `/setup` → admin signed in → setup locks | `AppSetupTest`, `SetupServiceTest` |
| Guest browse (join prompt, no private content) | `AppTest`, `AppPrivateBoardAccessTest` |
| Registration & login (no account enumeration) | `AuthControllerTest` |
| Create a thread (counters/timestamps consistent) | `AppPostingTest::test_user_creates_a_thread…` |
| Reply; locked threads reject | `AppPostingTest` |
| Edit/delete own content; others cannot | `AppPostingTest` |
| Sanitization (script/handlers/URLs/raw HTML) | `SanitizationTest`, `AppPostingTest` |
| Account/profile basics; email never public | `AppUserSettingsTest` |
| Admin access; non-admin/guest denied | `AppAdminTest` |
| Board/category management; delete only empty; slug redirects | `AppAdminTest` |
| Inline moderation + audit rows | `AppModerationTest` |
| Suspended account read-only | `AppWriteGateTest` |
| Banned/stale session cannot write, can read | `AppWriteGateTest` |
| No-JS operation | All integration tests drive plain form POSTs |
| Health/failure mode | `AppTest::test_healthz…` |

## Automated evidence

Test files (all under `tests/`):

- `tests/Unit/SanitizationTest.php` — XSS/malicious-payload regression (P0).
- `tests/Unit/StrTest.php` — slug/monogram helpers.
- `tests/Integration/Core/AppTest.php` — health, security headers, setup gate,
  guest read, canonical URLs, pagination, 404s.
- `tests/Integration/Controller/AuthControllerTest.php` — register/login/logout,
  CSRF rejection, rate limiting, no enumeration, banned/suspended login.
- `tests/Integration/Core/AppPostingTest.php` — thread/reply/edit/delete, counters,
  locked guard, XSS render, guest/owner gates.
- `tests/Integration/Core/AppUserSettingsTest.php` — profile/password, profile page,
  email-never-public, settings authz.
- `tests/Integration/Core/AppSetupTest.php` — first-run wizard over HTTP.
- `tests/Integration/Service/SetupServiceTest.php` — setup service unit/integration.
- `tests/Integration/Core/AppAdminTest.php` — admin access matrix, site name,
  category/board CRUD, delete-empty, slug 301.
- `tests/Integration/Core/AppModerationTest.php` — pin/lock/delete-any + audit.
- `tests/Integration/Core/AppWriteGateTest.php` — suspended/banned write denial.
- `tests/Integration/Core/AppPrivateBoardAccessTest.php` — hidden/private read gates.

**Result:** `79 tests, 274 assertions — OK` (PHPUnit 11, PHP 8.5, MariaDB 11.8).
Includes the healthy/unhealthy `/healthz` contract, registration + login rate
limiting, lock/unlock + audit, hidden vs private board gates, and cross-user
edit/delete denial.

## HTTP smoke matrix

Run against `php -S` with a real cookie jar (server-rendered, JS-disabled by
nature of `curl`):

| Check | Result |
|---|---|
| `GET /healthz` | `200` + `{"status":"ok","database":"ok"}` + security headers |
| `GET /` on fresh install | `302 → /setup` |
| `POST /setup` (wizard) | `303 → /admin`, admin auto signed-in |
| `GET /` as admin | renders community name + "Log out" + "Admin" |
| `POST /threads` with XSS payload | `303`; rendered body has `<strong>bold</strong>`, no `<script>alert`, no `href="javascript:` |
| `POST /threads` with no CSRF token | `403` |
| `GET /assets/app.css` | `200 text/css` |
| `GET /nope` | `404` |

## Security review

An adversarial multi-agent review covered six dimensions (XSS/sanitization,
CSRF/session/auth, authorization/write-gate, SQL/migrations/counters,
templates/no-JS correctness, acceptance coverage), with each finding independently
verified before action. No critical or high-severity defect remained after the
pass. The confirmed issues were fixed and locked with tests:

- **Profile activity leak (high → fixed):** `recentByUser` now filters to public
  boards so a member's activity never reveals hidden/private board threads.
- **`/healthz` 500-instead-of-503 (medium → fixed):** the health check is now
  dispatched before the DB-querying setup gate, so it reports `503
  {"status":"error","database":"down"}` when the database is unreachable.
- **Counter double-decrement on concurrent delete (medium → fixed):** post
  soft-delete now reports affected rows and only adjusts counters when it actually
  performed the delete; board last-activity is recomputed too.
- **Open redirect via backslash `next` (low → fixed)** and **protocol-relative
  link bypass (low → fixed):** both `safeNext` and the link sanitizer now reject
  every `//`, `/\`, `\/`, `\\` form.
- **Login timing oracle (low → fixed):** a dummy Argon2id verify equalizes
  response time when no account matches.

Key posture:

- All SQL uses PDO prepared statements; `LIMIT`/`OFFSET` are integer-cast, never
  string-bound.
- The XSS surface is defended in depth: CommonMark escapes raw HTML and unsafe
  link schemes, then a DOM allowlist sanitizer keeps only a fixed safe tag set.
- CSRF is enforced by the kernel on every POST (timing-safe HMAC of a
  per-session/guest secret); session cookies are `HttpOnly`, `SameSite=Lax`, and
  `Secure` in production; the cookie holds an opaque token, only its SHA-256 is
  stored.
- Write authorization is centralized in `WriteGate` and re-checked at the
  controller/service boundary (verified via direct POST tests), so suspended and
  banned accounts are blocked even through stale sessions.
- Private/hidden board read gates are enforced before slug-change redirects, so a
  private board's existence is never revealed.

## Rollback procedure

Phase 1 is a greenfield install, so rollback is straightforward:

1. **Application:** deploy is a git checkout. To roll back, check out the previous
   tag/commit and run `composer install`. No build step.
2. **Database:** migrations are reversible. `php bin/console migrate:rollback`
   drops the Phase-1 tables in reverse FK order (greenfield only — there is no
   production data to preserve before first launch). For a full reset during
   testing, `php bin/console migrate:fresh`.
3. **Backup before launch:** take a logical dump (`mysqldump retroboards`) before
   the first production migration so the empty-but-configured baseline can be
   restored.
4. **Verification after rollback:** `php bin/console migrate:status` and
   `GET /healthz` confirm schema + database health.

## Deferred to later phases

Per `PHASE_1_PLAN.md` §3 and `DECISIONS.md`: reactions/stars/subscriptions/unread,
notifications/email/digests/mentions, FULLTEXT search, DMs, reports queue and the
in-app warn/suspend/ban tooling (+`bans` table), per-board moderators, OAuth, the
rich composer/uploads, IP capture (`posts.ip`/`sessions.ip`), and the configurable
anti-spam/rate-limit service. The Phase-1 `verifications` table ships but is
dormant until the Phase-2 email worker.
