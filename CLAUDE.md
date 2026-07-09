# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**RetroBoards** — self-hostable "Community Inbox" forum software: durable forum topics presented through a Slack/email-style three-pane shell. Hand-rolled **vanilla PHP 8.2+ + MySQL/MariaDB**, server-rendered with progressive-enhancement JavaScript, no application framework, designed to run on a single VPS. Namespace `App\` → `src/` (PSR-4); tests `Tests\` → `tests/`.

## Ground truth lives in the spec docs, not the code or the README

This repo is spec-driven. Before changing behavior, find the authoritative doc — and respect the precedence chain (conflicts resolve top-down):

1. **`DECISIONS.md`** — locked decisions; **wins on any conflict**. Owns the replaceable-interface seams (§2) and the priority-tier-vs-delivery-phase rule.
2. **`DESIGN.md`** — product/technical source of truth; the roadmap and the **completion-evidence policy (§13)**.
3. **`SCHEMA.md`** — final consolidated table shapes + the per-phase build cut (§6). *Hand-maintained and can lag the migrations — a column documented here may not yet exist in the DB. Verify against `database/migrations/`.*
4. **`USER.md` / `ADMIN.md` / `COMMUNITY.md` / `COMPOSER.md`** — the member / operator / community-layer / composer surface specs.

`README.md` is an orientation pointer, **not** authoritative. There are no Cursor/Copilot rule files.

Two process rules that are easy to violate:
- **"Done" requires evidence (DESIGN §13).** Adding a column/table is *not* shipping a feature — behavior must be enforced and tested. UI-visible work needs Playwright/browser evidence *in addition to* PHPUnit. "Inert schema is not evidence."
- **`P0`–`P3` are MoSCoW priority tiers, not phase numbers** (DECISIONS §2). A `P3` item can ship in any later phase. Only when a doc clearly writes `P1…P7` as a sequence does it mean Phase 1…7.

Delivery is sequenced in seven **phases** (`PHASE_N_PLAN.md`), each a "release train" split into **Gate A** (minimum release) and **Gate B** (extended slice) with entry/exit gates and a carryover ledger. Deferrals are recorded in `docs/adr/000N-*.md` (never silently dropped); proof artifacts live in `docs/evidence/`. Current state is in `PHASE_5_STATUS.md`.

## Commands

```bash
# Setup
composer install
cp .env.example .env && php bin/console key:generate   # paste APP_KEY into .env
php bin/console migrate
php -S 127.0.0.1:8000 -t public public/index.php        # http://127.0.0.1:8000 → /setup on first run

# Tests  (the test DB must be reachable — `docker start rb-mariadb` if using the dev container)
composer test                                            # full suite (alias for phpunit)
vendor/bin/phpunit --testsuite unit                      # or: integration
vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php          # one file
vendor/bin/phpunit --filter test_tags_flag_gates_public_and_admin_tag_routes   # one method
# Tests run against DB_TEST_DATABASE (default retroboards_test); bootstrap.php drops + re-migrates it on every run.

# Migrations / data integrity
php bin/console migrate:status        # show applied vs pending
php bin/console migrate:fresh         # DROP ALL TABLES + re-migrate (destructive)
php bin/console repair                # recompute every denormalized counter + reputation from authoritative rows
php bin/console verify:upgrade        # rehearse an additive upgrade on a scratch DB (destructive; refuses APP_ENV=production)

# Background workers (run on cron)
php bin/console worker:email          # drain instant notification-email queue
php bin/console worker:digest         # send due daily digests
php bin/console worker:purge-ips      # anonymise captured IPs older than retention window
php bin/console worker:attachments    # sweep orphaned / deleted-parent uploads
php bin/console worker:registry-refresh   # fetch+verify signed registry snapshots/advisories (no-op if package_registry is rolled back)
php bin/console worker:packages       # verify installed package digests, enforce advisories/blocklist, purge retained uninstalls
php bin/console worker:webhooks       # drain the outbound webhook delivery queue (no-op if webhooks is rolled back)

# Browser evidence + backup rehearsal (separate throwaway DBs)
cd tests/browser && npm install && npx playwright install --with-deps chromium && npm run evidence
tests/backup/rehearse.sh
```

There is **no PHPUnit CI**; the only GitHub workflow (`.github/workflows/browser-evidence.yml`) runs the Playwright evidence capture. Run `composer test` locally before claiming a change is green.

## Architecture

### The HTTP kernel (`src/Core/App.php`)
`App::handle(Request): Response` is the whole kernel and is **pure** (no superglobals, no `echo`) so the entire stack runs in-process under test. `App`'s constructor takes optional `Database` + `RateLimiter` so tests inject fakes. Per-request pipeline:

```
buildContainer → Session::start → presence heartbeat → Flash::load → shareViewGlobals
  → process() [ health/robots/sitemap bypass → setup gate → CSRF gate → route dispatch → exception mapping ]
  → SecurityHeaders::apply → Session::commit → Flash::commit
```

- `shareViewGlobals()` runs **before** the setup gate and even against an un-migrated/unreachable DB, so every lookup is individually wrapped in `try/catch(Throwable)` with a safe default. **Anything you add to the global shell must tolerate a missing table.**
- `/healthz`, `/robots.txt`, `/sitemap.xml` are dispatched before the setup gate and CSRF check so they answer pre-setup / DB-down.

### Dependency injection (`src/Core/Container.php`) — hand-wired, no autowiring
`App::buildContainer()` binds ~80 services/repositories as **lazy-singleton** closures (`get()` memoizes; one shared `Database`/PDO per request). Controllers receive only the `Container` and pull collaborators via the base `Controller` helpers. **To add a service/repo, hand-write its `$c->bind(...)` in `buildContainer()`.** Some collaborators are passed **only when a feature flag is on** (e.g. `PostingService` gets `AntiAbuseService`/`AttachmentRepository` as `null` when `anti_abuse`/`uploads` are off) — consumers guard with `?->`.

### Routing (`src/Core/Router.php`)
Routes registered in `App::buildRouter()`. `{id}` compiles to `\d+`, any other `{name}` to `[^/]+`. **First registered match wins**, so register specific patterns before generic ones (`/t/{id}-{slug}` before `/t/{id}`). Path-but-method miss → 405; no match → 404.

### Write path: Controller → Service → Repository → Database
- **Controllers** are thin: marshal the `Request`, inject *server-trusted* fields (`$request->ip()`, idempotency key), call **one** service method, translate exceptions to responses.
- **Services own all business rules**: `WriteGate`/`BoardPolicy` checks, Markdown→`body_html`, anti-abuse, anonymity gating, idempotency. **Every multi-table mutation runs inside `$db->transaction(fn)`** so denormalized counters can't drift.
- **Repositories** are thin single-table SQL wrappers (constructor is always `(private Database $db)`), built only from prepared statements, returning **associative arrays, not objects**. `src/Domain/User.php` is the *only* domain object (via `UserRepository::findEntity()`).
- **Denormalized counters** (`boards.*_count`, `threads.reply_count`, `users.post_count`, `users.reputation`) are maintained transactionally; `RepairService` recomputes them from scratch. **Every counter you increment must have a matching recompute in `RepairService` with identical WHERE clauses** (`is_deleted=0 AND is_pending=0`, board scoping). Pending/held content (`is_pending=1`) **defers** all counters + notifications until a moderator approves it.
- **Reputation is double-booked**: append-only `reputation_events` ledger is the source of truth; `users.reputation` is a reconciled cache. **Submission idempotency**: the composer sends `idempotency_key`; a unique insert in the same transaction makes a double-submit throw `DuplicateSubmissionException`, roll back, and replay the original result.

### Exceptions — what the kernel does and does NOT catch
- Kernel catches **`HttpException`** (a non-null `redirectTo` → redirect; else error page at its status) and any other **`Throwable`** → 500.
- **`ValidationException` and `DuplicateSubmissionException` extend `RuntimeException`, not `HttpException` — the kernel does not handle them.** A controller **must catch `ValidationException` itself** and re-render the form `422` carrying `->errors` + `->old`. This is the **anti-draft-loss pattern**: on a failed write, re-render the originating page with the user's typed text preserved (`reply_old`/`edit_old`/`edit_error`) rather than redirecting and dropping it.
- `Controller::requireUser()` throws a *redirecting* `HttpException(302, …, '/login?next=…')` for guests; `requireAdmin()` throws `ForbiddenException` (403).

### Security & authorization (`src/Security/`)
Authorization is **three orthogonal axes**:
1. **Global role** — `users.role` ∈ `user|moderator|admin`, read via `User::isAdmin()/isModerator()` (cumulative: admin ⊇ moderator ⊇ user). Reputation/badges grant **no** power.
2. **Account state** — `WriteGate::assertCanWrite()` throws for banned/suspended on **every** write path. **"State beats role"**: a suspended admin can read but not write. `suspended_until` auto-expires.
3. **Per-board authority** — *not* in `users.role`; comes from `board_moderators`/`board_members` tables fed into the **pure** `BoardPolicy` (`canRead/isListed/canPost` over visibility `public|hidden|private` + `post_min_role`). `BoardPolicy` never queries the DB — resolve membership in the caller and pass `$isMember`. Moderation actions gate on `canModerate(user, boardId)` (admin-any OR assigned board mod), not a bare `isModerator()`.

Invariants to preserve:
- **CSRF** is enforced on every POST (field `_token`); the token is a stable HMAC of the session/guest `csrf_secret` (round-trips GET→POST), verified with `hash_equals`. The **only** exemption is the OAuth callback (`^/auth/[^/]+/callback$`), protected by a signed `state` cookie instead. Never add another exemption; never let a GET mutate state.
- **Sessions** are DB-backed: the cookie holds an opaque random token; the DB stores only its `sha256`. `login()` rotates id + `csrf_secret`. Call `revokeOtherSessionsFor()` after any credential change.
- **Rate limiting** (`RateLimitService`) keys per-account (`u{id}`) or per-IP, with named policies in `config/config.php` (`login`, `register`, `post`, `dm`, `upload`, …). It **fails open**; an unknown policy name silently no-ops. Pass user identifiers via `enforceSubject` (it hashes them — never put raw PII in a key). Trust `X-Forwarded-For` only through `ClientIdentifier` (inert unless `TRUSTED_PROXIES` is set).
- **Strict CSP** (`SecurityHeaders`, applied to every response): `script-src 'self'; style-src 'self'`, **no `'unsafe-inline'`**. No inline `<script>`/`<style>` anywhere — PE JS stays in the external files or pages silently break.
- `AntiAbuseService` is a separate content-scoring layer (not the auth gate): moderators are exempt, it defaults to `observe` mode (clamped per operator mode, capped at `hold` — never auto-blocks by default), and every decision writes a `moderation_log` audit row.

### Feature flags & replaceable seams (`src/Core/FeatureFlags.php`)
Every post-MVP subsystem is gated by a flag. `DEFAULTS` map + a `features` JSON override in `settings`. **Phase 2/3 flags default ON; Phase 4 Gate A defaults are mixed**: `topic_workflow` graduated to default-ON on 2026-07-01 (see `docs/runbooks/topic_workflow.md`); `tags`, `expanded_feeds`, and `reputation_ledger` graduated to default-ON on 2026-07-01 (see `docs/runbooks/phase4-tags-feeds-reputation.md`); `badge_rules` graduated to default-ON on 2026-07-02 (see `docs/runbooks/badge_rules.md`); `wysiwyg_composer` (Phase 5 composer stream) graduated to default-ON on 2026-07-02 (see `docs/runbooks/wysiwyg_composer.md`); `content_references` graduated to default-ON on 2026-07-02; `appeals` graduated to default-ON on 2026-07-02 (see `docs/runbooks/appeals.md`); Phase 5 Gate A/B2 flags `package_registry`, `package_themes`, `capabilities`, `passkeys`, `provider_registry`, `invitations`, `service_secrets`, `api_tokens`, `webhooks`, and `first_party_hooks` graduated to default-ON on 2026-07-09 (see `docs/adr/0018-phase-5-gate-a-default-on.md` and the flag runbooks where present — the B2 quartet `service_secrets`/`api_tokens`/`webhooks`/`first_party_hooks` has no runbook yet; rollback mechanics live in `docs/runbooks/operations.md` §2); `group_dms`, `community_memory`, `custom_css`, `link_previews`, `expanded_files`, `automated_context`, `server_extensions`, `governance`, `service_principals`, and `verified_links` remain default OFF. `enabled('typo')` returns `false` (fails dark). Flags gate **availability only** — staged-rollout posture (anti-abuse mode) lives in config, not flags. New subsystems ship behind a flag defaulting dark, route-gated, with a regression test asserting they're dark (`tests/Integration/Core/AppFeatureFlagTest.php`).

**Replaceable-interface seams** (DECISIONS §2) — swap by rebinding the interface in `App.php`, never touch callers: `Mailer` (`SendmailMailer`/`ArrayMailer`), `SearchService` (`MysqlSearchService` — impls **must** apply the read gate), `FeedService`, `SpamScorer` (`NullSpamScorer` abstains). Email **fails closed**: no `From` configured ⇒ email skipped, in-app notifications still deliver.

### Database & migrations
File-based runner (`src/Core/Migrator.php`). A migration is `database/migrations/NNNN_name.php` that **`return`s an anonymous class with `up(\PDO)`/`down(\PDO)`**, ordered by the zero-padded 4-digit prefix, tracked in `schema_migrations`. **To add one: use the next number (currently `0077`)**, write DDL in a `<<<'SQL'` nowdoc, make `up()` additive (drop FKs before columns in `down()`), then `php bin/console migrate`. Migrations are **additive-only / forward-only**; `migrate:rollback` cascades through *all* migrations and is greenfield-only. Data seeds live inside a numbered migration using `INSERT IGNORE` (pattern: `0040_seed_badges.php`). After landing a schema migration, hand-update `SCHEMA.md` (shape + §9 changelog + version bump).

PDO is configured `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, **`EMULATE_PREPARES=false`** — so **never bind `LIMIT`/`OFFSET`** (cast to int + concatenate after clamping) and **never reuse a named placeholder twice** (give it two names). Use UTC everywhere (`UTC_TIMESTAMP()` / `gmdate()`); IPs are stored packed via `inet_pton` into `VARBINARY(16)`.

### Views & progressive enhancement (`src/Core/View.php`, `templates/`, `public/assets/`)
Plain-PHP templates rendered with `$this` bound to the `View`; globals + per-render data are `extract()`ed. **Single-level layout**: a leaf template calls `$this->layout('layout')` + `$this->section(...)`; `templates/layout.php` is the one shell (three-pane `variant=app` vs centered `variant=plain` for auth/setup/errors). Escape output with `$this->e()` / the `$e` closure; the **only** sanctioned raw echo is pre-sanitized HTML (`$p['body_html']`, sanitized at write time by `App\Support\Markdown`). Emit CSRF with `$this->csrfField()`. Global template helpers (`mask_author`, `human_datetime`, `monogram_*`) are autoloaded functions in `src/Support/helpers.php`.

JS (`app.js`, `composer.js`, `tour.js`) is **strictly progressive enhancement** — every flow must work as server-rendered HTML+forms first; JS only decorates via specific JSON endpoints (`/composer/preview`, `/notifications/bell`, `/presence`, `/upload`, `/onboarding/*`) and hooks via `data-*` attributes. Live composer preview re-uses the **exact same server render pipeline** (no client Markdown engine). Theming is flash-free (server stamps `data-theme/density/...` on `<html>`); operator branding is a separate generated `/brand.css` overriding `--accent` tokens. Anonymous authorship is masked at **render** time, never stored masked. Short-polling only (DECISIONS) — no WebSockets.

### Testing (`tests/`, `tests/Support/TestCase.php`)
Integration tests extend `Tests\Support\TestCase`, which drives the real kernel in-process via `App::handle()` as a cookie-jar HTTP client (`get`/`post`/`postFile`/`actingAs`), with seeding helpers (`makeUser`/`makeAdmin`/`makeBoard`/`makeThread`) and CSRF handled automatically. **Per-test isolation is one DB transaction rolled back in tearDown — there are no savepoints, so code that "rolls back" inside its own transaction does NOT undo rows in tests; assert observable HTTP behavior, not row counts.** Unit tests extend PHPUnit's `TestCase` directly. PHPUnit is **strict** (`failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`): every test needs ≥1 assertion, no stray `echo`/`var_dump`, no PHP warnings — any of these turns a green run red. Repositories are `final`; exercise the real test DB rather than mocking.
