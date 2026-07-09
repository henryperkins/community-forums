> Archived design record — implementation plan + design spec(s) merged during the api-tokens doc consolidation; see the ADR / runbook / PR referenced below for shipped status.

# API Tokens (read-only slice) — design spec + implementation plan

## Design spec — Admin/Service API Tokens + Scopes (read-only slice)

**Date:** 2026-06-28
**Status:** Approved design (brainstorming output) — pending implementation plan.
**Phase / gate:** Phase 5, Gate A prerequisite. **Sub-project 2 of 4** of the B2
"trusted hook/webhook/API-token/secret foundation" (ADR 0004 Part B, row B2).

> Precedence (CLAUDE.md): `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > surface specs.
> Where this design and an authoritative doc disagree, the authoritative doc wins.

---

### 0. Context — the B2 program and where this fits

ADR 0004 makes **B2** an explicit Phase 5 Gate A prerequisite: the public ecosystem
(P5-04 remote apps mapping onto "accepted webhooks/API scopes") assumes a trusted
hook/webhook/API-token/secret foundation that was deferred in Phase 3 (ADR 0002) and
never built. B2 is built foundation-first as four sub-projects:

1. **Encrypted service-secret registry (B3)** — **landed** (`SecretVault`, migration
   `0055`).
2. **API tokens + scope vocabulary** — *this document.*
3. **Webhook delivery** — outbound HTTP, HMAC-signed, durable delivery ledger.
4. **First-party hook registry** — in-process event/filter dispatch for vetted code.

This sub-project delivers the **read-only first slice** of API tokens: the full token
lifecycle + scope vocabulary + Bearer authentication + a small read-only `/api/v1/*`
surface, proving the machinery end-to-end with **no change to the kernel's CSRF gate,
session resolution, or request pipeline**.

#### Authoritative requirements this slice satisfies

- **PHASE_3_PLAN P3-13 / §DoD:** "Admin API tokens are shown once, stored only as
  hashes, scoped, expirable, revocable, rate-limited, and fully audited." "Minimal
  versioned admin API with scoped, hashed, expiring tokens and a deliberately narrow
  supported endpoint set."
- **Acceptance (`PHASE_3_PLAN:403`):** "A token can call only its scopes, cannot recover
  its plaintext, stops immediately after revoke/expiry, is rate-limited, and leaves an
  audit record."
- **PHASE_5_PLAN #13 / P5-04:** remote apps "use service identities. They do not borrow
  an Admin session or share a human API token." Migration rule (`:482`): "Existing API
  tokens remain human-created tokens until explicitly migrated. Do not silently convert
  them into service principals."
- **`SCHEMA.md §3` / `ADMIN.md §10.1`:** the `api_tokens` target DDL (hash-only,
  `UNIQUE uq_token_hash`) — **target-only, never migrated**.
- **CLAUDE.md security invariant:** the OAuth callback is the **only** CSRF exemption;
  "never add another exemption." This slice respects that by **not touching CSRF at all**
  (see §1).

#### Brainstorming decisions locked

- **Scope:** read-only first slice. **No** write/mutation endpoints, **no** CSRF-realm
  change, **no** private/membership-gated content (public content only), **no** service
  principals beyond admin-minted tokens (Gate B, P5-14), **no** webhook scopes.
- **Principal model:** a token is a **standalone non-human principal** carrying only its
  scopes. It never becomes a `User` and never inherits its creator's role. `/api`
  controllers authorize by **scope**, not `requireUser/requireAdmin`.

---

### 1. Why this needs zero CSRF changes (the central design fact)

A Bearer-token machine client cannot send a CSRF `_token`. The kernel's CSRF gate
(`src/Core/App.php:206`) rejects **every POST** lacking a valid `_token`, before
dispatch, with the single sanctioned exemption for `^/auth/[^/]+/callback$`. CLAUDE.md:
"never add another exemption."

This slice sidesteps the gate entirely:
- **Every `/api/v1/*` endpoint is GET** — GET never reaches the CSRF gate (`isPost()` only).
- The base `ApiController` **self-authenticates by Bearer** inside the controller, exactly
  as other controllers self-authenticate by session via `requireUser()`. The kernel's
  `Session::start()` and the CSRF gate are **untouched**.
- The admin token-management pages (`/admin/api-tokens`) are ordinary **browser** pages
  using the **normal session + CSRF** path — no exception there either.

The eventual write API (a later increment) will require carving `/api/*` out as a
token-only realm exempt from the *cookie*-CSRF gate (a DECISIONS-level change, since a
header Bearer token is inherently CSRF-proof). That decision is **explicitly deferred** —
not made here.

**What this slice does change in the kernel (all additive):** route registrations in
`App::buildRouter()` and two container bindings in `App::buildContainer()`. It does **not**
change the CSRF gate, `Session` resolution, or the request pipeline.

---

### 2. Data model — migration `0056_phase5_api_tokens.php`

Additive. Columns match the `SCHEMA.md §3` target DDL (no extra columns — tokens are
identified in the UI by their required `name`, so no secret fragment is stored), but the
migration **intentionally hardens and updates the authoritative target DDL**: it renames
the unique key to the Phase 5 `uq_<table>_<col>` convention, adds a `created_by` FK, and
adds two supporting indexes (every other Phase 5 table carries a `users` FK). The
migration's `up()` becomes the new authoritative shape; **`SCHEMA.md §3` and `ADMIN.md
§10.1` are rewritten to match** (and their "not built" notes cleared).

**The migration also extends the `moderation_log.target_type` ENUM** — currently
`ENUM('thread','post','user','board','category','setting','service_secret')` after
`0055` — to add `'api_token'` (`ALTER TABLE moderation_log MODIFY target_type ENUM(…,'api_token')`),
exactly as `0055` added `'service_secret'`. Without this the `api_token_minted` /
`api_token_revoked` audit inserts fail with an invalid-enum error. `down()` reverts the
enum (after deleting any `target_type='api_token'` rows) and drops `api_tokens`.

| column | type | notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `name` | VARCHAR(80) NOT NULL | admin-chosen label; the UI identifier |
| `token_hash` | CHAR(64) NOT NULL | `sha256(plaintext)` — **hash-only**, never the plaintext |
| `scopes` | JSON NOT NULL | array of scope strings (validated against `ApiScopes`) |
| `created_by` | BIGINT UNSIGNED NOT NULL | FK `users(id)` ON DELETE CASCADE — provenance/accountability only |
| `created_at` | DATETIME NOT NULL | UTC |
| `last_used_at` | DATETIME NULL | touched on each authenticated call |
| `expires_at` | DATETIME NULL | optional expiry |
| `revoked_at` | DATETIME NULL | set on revoke |

Indexes: `UNIQUE KEY uq_api_token_hash (token_hash)`, `KEY idx_api_token_created_by
(created_by)`, `KEY idx_api_token_active (revoked_at, expires_at)`. `ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4`.

**`created_by` lifecycle:** `ON DELETE CASCADE` — an admin-minted token dies with the
admin who minted it (acceptable for this slice). A token outliving its creator is a
Gate B service-principal concern (P5-14), out of scope here.

Beyond the `SCHEMA.md §3` / `ADMIN.md §10.1` DDL rewrite noted above, the `SCHEMA.md`
table index gets/updates its `api_tokens` row, the §9 changelog gains an entry, the doc
version bumps, and the `api_tokens` "not built" notes are cleared.

---

### 3. Scope vocabulary — `App\Security\ApiScopes`

A code catalogue (`const SCOPES = [scope => description]`), with `isValid(string): bool`
and `all(): array`. Designed to extend. Read-only slice ships exactly two:

- `read:boards` — list public boards.
- `read:threads` — read threads/posts in a public board.

`/api/v1/me` requires only a **valid token** (no scope). Scopes gate **endpoint access**;
returned data still honors the existing **public-read gate** (no private/hidden content —
a non-human principal resolves no board membership, so the API serves public content
only). Two distinct scopes let the tests prove **per-scope granularity** (a token with
`read:boards` but not `read:threads` gets 200 on boards, 403 on threads).

---

### 4. Components

Layering follows the existing thin-controller → service → repository pattern.

- **`App\Security\ApiPrincipal`** — immutable value object `{tokenId:int, name:string,
  scopes:string[], createdBy:int, createdAt:string, tokenHash:string}` + `hasScope(string): bool`.
  **Not** a `User`; carries no role. `tokenHash` is the non-secret one-way `sha256` (not the
  plaintext), used by `respond()` for rate-limit keying; `createdAt` backs `/api/v1/me`.
- **`App\Repository\ApiTokenRepository`** (`(private Database $db)`):
  - `insert(name, tokenHash, scopesJson, createdBy, ?expiresAt): int`
  - `findActiveByHash(string $hash): ?array` — `WHERE token_hash = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())`
  - `touchLastUsed(int $id): void`
  - `revoke(int $id): int` — set `revoked_at = UTC_TIMESTAMP()` (gated `WHERE revoked_at IS NULL`); returns **rows affected** (0 when the id is unknown or already revoked)
  - `list(): array` — for the admin UI; selects everything **except** `token_hash`, newest first
  - `findById(int): ?array`
- **`App\Service\ApiTokenService`** `(Database, ApiTokenRepository, ModerationLogRepository, FeatureFlags, Config, PasswordHasher, UserRepository, WriteGate)`:
  - `mint(User $admin, string $currentPassword, string $name, array $scopes, ?int $expiresInDays): array{token:string, id:int}` — in order: **(a) write gate** — `WriteGate::assertCanWrite($admin)` (state beats role: a suspended/banned admin with a stale session cannot mint, even though `requireAdmin` passed); **(b) flag gate** — throw `ApiTokensDisabledException` if `api_tokens` is dark (defense in depth — the admin UI is already 404 when dark); **(c) reauth** — verify `$currentPassword` via `PasswordHasher` (`PHASE_3_PLAN:538` "reauth for creation"); mismatch → `ValidationException(['current_password'=>…])`, mints **nothing**; **(d) validate** (see below). Then generate `'rbt_' . bin2hex(random_bytes(24))`, store `sha256` + scopes + `expires_at`, audit `api_token_minted` (name + scopes — **no token, no password**), return the plaintext **once**. Txn (insert + audit). MFA is not separately re-challenged (the codebase reauths sensitive changes with the password, not a per-action MFA ceremony).
  - `authenticate(string $bearer): ?ApiPrincipal` — **return `null` immediately if `api_tokens` is dark** (service-level kill switch, so even a future non-controller caller cannot authenticate); else strip the `Bearer ` prefix, `$hash = sha256(token)`, `findActiveByHash($hash)`; on hit `touchLastUsed` and return an `ApiPrincipal` carrying `tokenHash` + `createdAt`; else `null`.
  - `revoke(User $admin, int $tokenId): void` — `WriteGate::assertCanWrite($admin)`; `revoke` + audit `api_token_revoked` **only when the repo reports a real state change** (rows-affected = 1); a no-op revoke of an unknown / already-revoked id forges **no** audit row. Txn. Idempotent. Allowed when the flag is dark (cleanup).
  - `auditScopeDenied(ApiPrincipal $p, string $scope): void` — writes an `api_token_scope_denied` `moderation_log` row (`target_id` = token id; attempted scope; **no secret**). Called by `respond()` on a 403.
  - `list(): array` — passthrough for the admin UI.

  **Validation (`mint`):** `name` required, trimmed, **1–80 chars**; `scopes` a **non-empty** array of **distinct** values each in `ApiScopes` (empty / unknown / duplicate → `ValidationException`); `expiresInDays`, when given, an int in **1–365** (`null` = no expiry; out of range → `ValidationException`).

  **Audit policy (matching "fully audited" / `:403` proportionately):** lifecycle (`api_token_minted`, `api_token_revoked`) and **scope denial by a valid token** (`api_token_scope_denied`) write `moderation_log` rows; routine **successful use** is recorded via `last_used_at` only (a `moderation_log` row per read would be noise). **Unknown-token 401s are not audited** (each guess is a new hash → unbounded → audit-flood/DoS) and **rate-limit 429s are not** (the limiter already records the throttle). This is the deliberate, bounded reading of "leaves an audit record" for this slice.
- **`App\Controller\Api\ApiController`** (base, extends the existing `Controller`). Every
  failure from a **registered `/api/v1/*` GET endpoint** (flag / auth / scope / rate-limit)
  is emitted as JSON by this wrapper — the kernel is never reached for those.
  **Caveat (the cost of the zero-kernel-change property):** an *unknown* `/api` path or a
  *wrong method* is decided by the router inside `App::process()` **before** any controller
  runs, so it returns the kernel's **HTML** 404/405, not JSON. A correct client hitting a
  real endpoint with the right method always gets JSON; only malformed requests get HTML.
  The deferred write-API increment (which already touches the kernel for the CSRF realm)
  can add JSON 404/405 for `/api` then. A single wrapper runs each action, in this order:
  - `respond(Request $req, callable(ApiPrincipal): Response $action): Response` —
    1. **flag gate** — `api_tokens` dark → `Response::json(['error'=>'not_found'], 404)`;
    2. **authenticate** — read `Authorization` via `Request::header()`, strip `Bearer `,
       `ApiTokenService::authenticate`; `null` → `Response::json(['error'=>'unauthorized'], 401)`;
    3. **rate limit** — `enforceSubject('api', $req, $principal->tokenHash)` for the
       now-authenticated token (wrap the limiter's `HttpException(429)` → `Response::json(['error'=>'rate_limited'], 429)`);
    4. run `$action($principal)`, catching `ApiForbiddenException` → `ApiTokenService::auditScopeDenied($principal, $deniedScope)` then `Response::json(['error'=>'forbidden','scope'=>…], 403)`.
  - `requireScope(ApiPrincipal $p, string $scope): void` — `$p->hasScope($scope)` else throw
    the small internal `App\Security\ApiForbiddenException($scope)` (caught by `respond`).

  Each `/api` action is then one line: `return $this->respond($req, fn (ApiPrincipal $p) => …)`.
- **`App\Controller\Api\MeController`** — `GET /api/v1/me` → valid token, no scope → `{name, scopes, created_at}`.
- **`App\Controller\Api\BoardsController`** — `GET /api/v1/boards` (`read:boards`) → the full public-board set `[{id, slug, name, thread_count, post_count}]` (inherently small/operator-bounded, no paging); `GET /api/v1/boards/{id}/threads` (`read:threads`) → the public board's **most recent** threads, **bounded**: default **20**, optional `?limit` clamped to **1–50** (no cursor paging in this slice — YAGNI), `[{id, slug, title, reply_count}]`; 404 JSON if the board isn't public. The `LIMIT` is int-clamped + concatenated (never bound — `EMULATE_PREPARES=false`).
- **`App\Controller\AdminApiTokenController`** — `requireAdmin()` + normal session/CSRF:
  - `GET /admin/api-tokens` → list + mint form
  - `POST /admin/api-tokens` → **reauth (`current_password`) + mint**; on success flash the plaintext **once** ("copy now — it won't be shown again"); on `ValidationException` (bad reauth, or invalid scope/name) re-render 422 with `->errors` + old input (the typed name/scopes — **never** the password)
  - `POST /admin/api-tokens/{id}/revoke` → revoke
  - All 404 (via `NotFoundException`, HTML) when the flag is dark.
- **Template:** `templates/admin/api_tokens.php` — token list (name, scopes, created, last-used, status), mint form (name + scope checkboxes + optional expiry days + **`current_password` reauth field**), and the one-time plaintext banner.

JSON responses use the existing `Response::json($data, $status)`.

---

### 5. Data flow

**Machine (read):** `GET /api/v1/boards` + `Authorization: Bearer rbt_…` → `ApiController`
flag-gate → `authenticate()` → `ApiPrincipal` → `requireScope('read:boards')` →
`BoardsController` returns public boards JSON. Revoked / expired / invalid / missing token
→ **401**; valid token lacking the scope → **403**; over the rate limit → **429**.

**Admin (browser):** `/admin/api-tokens` lists tokens; the mint form POSTs (session+CSRF)
→ `ApiTokenService::mint` → the **plaintext is flashed once**; a revoke button POSTs →
`revoke` → the token stops authenticating on the next request.

---

### 6. Error handling

Resolution order inside `ApiController::respond` is **flag → authenticate → per-token
rate-limit → scope → action**; every failure from a registered `/api/v1/*` GET endpoint is
JSON emitted by `ApiController` itself. The admin pages, being ordinary browser routes,
throw `NotFoundException` (HTML 404) when the flag is dark.

- **401** — no/invalid/expired/revoked token (one undifferentiated response — no oracle).
- **403** — valid token, missing scope.
- **404 (JSON)** — registered `/api` endpoint with the `api_tokens` flag dark. **404/405
  (HTML)** — an *unknown* `/api` path or *wrong method*: the router decides this in
  `App::process()` before the controller, so it falls through to the kernel's HTML error
  page (accepted limitation of the zero-kernel-change slice; see §4 caveat).
- **429** — per-token rate limit exceeded (only an *authenticated* token consumes the budget; the 192-bit token entropy, not the limiter, is what makes guessing infeasible).
- **422** — admin mint with a **failed `current_password` reauth**, or an invalid/empty
  scope or name → `ValidationException` caught by the controller, form re-rendered with
  `->errors` + old input (the password is never echoed back — anti-draft-loss).
- Every audit row is written **inside the same transaction** as its mutation (mint/revoke).
- **`HTTP_AUTHORIZATION` portability:** some SAPIs strip the header; absent → 401. The spec
  notes the deploy must pass `Authorization` through (e.g. an Apache rewrite); no
  `getallheaders()` fallback is added in this slice.
- **Redaction:** the token plaintext appears in no audit JSON, no log, no list/response,
  and no exception message — only its `sha256` hash is stored.

---

### 7. Feature flag & rate limit

- **Flag** `'api_tokens' => false` in `FeatureFlags::DEFAULTS` (B2-foundation, deploy-dark).
  Gates the `/api/v1/*` surface **and** the admin pages (404 when dark) and doubles as a
  kill switch (dark → all token auth stops, admin UI hidden). A regression test asserts it
  defaults dark.
- **Rate limit** a new `'api' => [120, 60]` policy in `config/config.php` `rate_limits`
  (120 requests/min per token, tunable), keyed by the **token hash** via
  `RateLimitService::enforceSubject` (which sha256-hashes the subject).

---

### 8. Container wiring (additive)

`App::buildContainer()` binds `ApiTokenRepository` then `ApiTokenService` (closure pulling
`Database` + collaborators via `$c->get(...)`). Unlike sub-project 1, these bindings **are**
exercised — the `/api` controllers resolve `ApiTokenService` from the container at dispatch
— so they are covered by the endpoint tests (no unexercised-wiring risk). `App::buildRouter()`
registers the `/api/v1/*` GET routes and the `/admin/api-tokens` GET/POST routes (literal
prefixes — first-match-wins is not at risk).

---

### 9. Testing & evidence (the "done" bar)

This slice is **UI-visible** (admin token management), so per DESIGN §13 it needs
**PHPUnit *and* browser/no-JS evidence**.

**Integration (kernel HTTP via `TestCase`; Bearer sent through the `$server`
`HTTP_AUTHORIZATION` entry):**
1. `GET /api/v1/me` with a valid token → 200 + the token's `name`/`scopes`.
2. `/api/v1/me` with no token → 401; with a garbage token → 401.
3. `GET /api/v1/boards` with `read:boards` → 200 (public boards only — a private/hidden
   board is absent from the payload).
4. `/api/v1/boards` with a token lacking `read:boards` → 403; `/boards/{id}/threads` with
   `read:boards` but **not** `read:threads` → 403 (per-scope granularity).
5. **Immediate revoke:** mint → call succeeds → revoke → the next call → 401.
6. **Expiry:** a token with `expires_at` in the past → 401.
7. **Rate limit:** exceeding the `api` policy → 429.
8. **Admin mint (with reauth):** `POST /admin/api-tokens` carrying the correct
   `current_password` → the response shows the plaintext **once**; a reload of
   `/admin/api-tokens` does **not** show it again; only the hash is stored.
9. **Reauth required:** `POST /admin/api-tokens` with a **wrong/empty `current_password`**
   → 422, the form re-renders with the typed name/scopes preserved, and **no** token row is
   created (assert the `api_tokens` count is unchanged).
10. **Audit + enum + redaction:** `api_token_minted` / `api_token_revoked` rows exist in
    `moderation_log` (proving `target_type='api_token'` is a valid enum value after `0056`);
    neither the plaintext **nor the password** appears in any audit JSON.
11. **Flag-dark:** with `api_tokens` off, `/api/v1/me`, `/api/v1/boards`, and
    `/admin/api-tokens` all → 404; flipping it on restores them.
12. **Hash-only schema:** no plaintext/`token` column; `token_hash` is uniquely indexed.
13. **Router-caveat (documented limitation):** `GET /api/v1/does-not-exist` → 404 and
    `POST /api/v1/me` (wrong method) → 405 are served by the kernel as **HTML**, not JSON
    — asserted so the limitation is pinned, not silently assumed.
14. **Write gate (state beats role):** a **suspended** admin (active session) → `POST
    /admin/api-tokens` and `.../revoke` are blocked by `WriteGate`, and no token is
    minted/revoked — even though `requireAdmin` passes.
15. **Service-level kill switch:** calling `ApiTokenService::authenticate()` **directly**
    with a valid token while `api_tokens` is dark returns `null` (not just the controller
    404) — a unit-style service test.
16. **Validation:** mint with an **empty scope array** → 422; an **unknown scope** → 422;
    a **blank** or **>80-char** name → 422; `expiresInDays` of `0` / `400` → 422; each
    creates no token.
17. **Scope-denial audit:** a valid token denied a scope (403) writes an
    `api_token_scope_denied` `moderation_log` row (token id + attempted scope, no secret);
    an unknown-token 401 and a 429 write **no** audit row.
18. **Threads bound:** `/api/v1/boards/{id}/threads?limit=999` returns at most 50; the
    default (no `?limit`) returns at most 20.
19. **`/api/v1/me` `created_at`:** the response's `created_at` matches the token row.

**`AppFeatureFlagTest`:** extend to assert `api_tokens` defaults dark.

**Schema-shape test** — `tests/Integration/Core/AppApiTokensSchemaTest`: a fresh migrate
applies `0056`; `api_tokens` has the documented columns/types (FK + indexes); `token_hash`
is `CHAR(64)` + uniquely indexed (`uq_api_token_hash`); no raw-token column; and
`moderation_log.target_type` includes `'api_token'`.

**Browser/no-JS (Playwright):** an admin opens `/admin/api-tokens`, mints a token with a
no-JS form submit, sees the one-time plaintext banner, sees it in the list (by name), and
revokes it. Capture evidence artifacts.

**Suite + upgrade rehearsal:** `./vendor/bin/phpunit` green; `verify:upgrade --force`
rehearses `0056` additively on seeded data.

**Docs:** `SCHEMA.md` §3 DDL rewrite + table index + §9 changelog + version bump (clear
the `api_tokens` "not built" notes); `ADMIN.md §10.1` DDL rewrite to match;
`PHASE_5_STATUS.md` records this increment + the B2 ledger (sub-project 2 landed).

---

### 10. Component isolation summary

| Unit | Does | Used via | Depends on |
|---|---|---|---|
| `ApiScopes` | the scope catalogue + validation | `isValid()` / `all()` | — (pure) |
| `ApiPrincipal` | a non-human scoped identity | `hasScope()` | — (value object) |
| `ApiTokenRepository` | single-table SQL for `api_tokens` | typed methods returning arrays | `Database` |
| `ApiTokenService` | mint (write-gate + flag-gate + password reauth + validation) / authenticate (flag kill switch) / revoke / scope-denied audit / list, txns | those methods | `Database`, repo, `ModerationLogRepository`, `FeatureFlags`, `Config`, `PasswordHasher`, `UserRepository`, `WriteGate` |
| `Api\ApiController` (+ `Me`/`Boards`) | Bearer auth, scope enforcement, flag gate, rate limit, JSON | `/api/v1/*` GET routes | `ApiTokenService`, `RateLimitService` |
| `AdminApiTokenController` | admin mint/list/revoke UI (session+CSRF) | `/admin/api-tokens` routes | `ApiTokenService` |

Consumers depend only on `ApiTokenService`'s methods and the `ApiPrincipal`/scope surface,
never on the table shape. The `/api` realm is GET-only and self-authenticating; the
existing session/CSRF path is untouched.

---

## Implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin-minted Bearer API tokens authenticating machine clients to a read-only `/api/v1/*` surface, authorized by scope — hash-only, shown once, reauth-on-mint, expirable, immediately revocable, rate-limited, audited; B2 sub-project 2 of 4.

**Architecture:** A new `api_tokens` table + an `ApiTokenService` (mint/authenticate/revoke) over a thin repo. A base `ApiController` self-authenticates by Bearer and emits all `/api` errors as JSON itself (the kernel renders HTML, so GET-only `/api` routes touch no CSRF/pipeline code). A standalone `ApiPrincipal` carries only scopes. Admin management is an ordinary session+CSRF browser page.

**Tech Stack:** Vanilla PHP 8.2, MySQL via PDO (native prepares), PHPUnit + Playwright. Namespace `App\` → `src/`, `Tests\` → `tests/`.

**Design spec:** the design section above (formerly `docs/superpowers/specs/2026-06-28-api-tokens-design.md`, now merged into this record).

### Global Constraints

- **PHP 8.2+, vanilla.** Repositories `final`, constructor `(private Database $db)`, prepared statements only, return associative arrays. Services own transactions (`$this->db->transaction(fn)`); the audit row is written inside the same transaction.
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT` — int-clamp + concatenate. UTC everywhere (`UTC_TIMESTAMP()`, `gmdate()`).
- **Migrations additive-only.** Next number is **`0056`**. Anonymous class `up(\PDO)/down(\PDO)`, `<<<'SQL'` nowdoc. `moderation_log.target_type` is an **ENUM** — extend it with `'api_token'` or audit inserts fail. Update `SCHEMA.md §3` + `ADMIN.md §10.1`.
- **No CSRF/pipeline change.** `/api/v1/*` is **GET-only**; the base `ApiController` returns `Response::json(...)` for every failure. Router 404/405 for unknown `/api` path or wrong method fall through to kernel HTML — an accepted limitation.
- **Standalone principal:** an `ApiPrincipal` is never a `User`; `/api` controllers authorize by **scope**, not `requireUser/requireAdmin`. Service-level kill switch: `authenticate()` returns `null` when the flag is dark.
- **Token = `'rbt_' . bin2hex(random_bytes(24))`**, stored only as `hash('sha256', token)`, shown once. Plaintext + password appear in **no** audit/log/response/exception.
- **PHPUnit strict** (`failOnWarning`/`failOnRisky`): ≥1 assertion per test, no stray output. Integration tests extend `Tests\Support\TestCase`; assert observable HTTP behavior.
- **Test DB** is the `forum-software-db-1` container (port 3307, `retro`/`retro`); `tests/bootstrap.php` migrate:fresh-es it every run, so `0056` applies automatically.
- **Commit trailer:** `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`. Stage only each task's named files (never `git add -A`; the tree has a stray `DESIGN.md`).
- **Branch:** `b2-service-secret-registry` (current B2 branch).

---

### Task 1: Deploy-dark feature flag `api_tokens`

**Files:**
- Modify: `src/Core/FeatureFlags.php` (DEFAULTS, after the `service_secrets` line)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php`

**Interfaces:**
- Produces: flag key `api_tokens` (bool, default `false`).

- [ ] **Step 1: Extend the dark-flag assertion.** In `tests/Integration/Core/AppFeatureFlagTest.php`, in `test_phase5_foundation_flags_default_dark`, add `'api_tokens'` to the Gate A list and assert it is a declared key:

```php
            // Gate A
            'package_registry', 'package_themes', 'capabilities', 'passkeys',
            'provider_registry', 'invitations', 'service_secrets', 'api_tokens',
```

and after the `foreach`:

```php
        self::assertArrayHasKey('api_tokens', $flags->all(), 'api_tokens must be a declared flag');
```

- [ ] **Step 2: Run it — expect FAIL** (`assertArrayHasKey` fails; key not in DEFAULTS).

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark tests/Integration/Core/AppFeatureFlagTest.php`
Expected: FAIL.

- [ ] **Step 3: Add the flag.** In `src/Core/FeatureFlags.php`, directly after the `'service_secrets' => false,` line:

```php
        'api_tokens' => false,        // admin/service Bearer API tokens + read-only /api/v1 (B2 sub-project 2)
```

- [ ] **Step 4: Run it — expect PASS.**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "Add deploy-dark api_tokens feature flag (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Migration `0056` (api_tokens + enum) + schema test + DDL docs

**Files:**
- Create: `database/migrations/0056_phase5_api_tokens.php`
- Create: `tests/Integration/Core/AppApiTokensSchemaTest.php`
- Modify: `SCHEMA.md` (§3 DDL rewrite + table index + §9 changelog + version), `ADMIN.md` (§10.1 DDL)

**Interfaces:**
- Produces: table `api_tokens`; `moderation_log.target_type` includes `'api_token'`.

- [ ] **Step 1: Write the failing schema test.** Create `tests/Integration/Core/AppApiTokensSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/** Schema-shape checks for the B2 api_tokens table (migration 0056). */
final class AppApiTokensSchemaTest extends TestCase
{
    /** @return array{type:string,column_type:string}|null */
    private function column(string $table, string $col): ?array
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS type, COLUMN_TYPE AS column_type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col],
        );
        return $row === null ? null : ['type' => (string) $row['type'], 'column_type' => (string) $row['column_type']];
    }

    public function test_api_tokens_table_shape(): void
    {
        $hash = $this->column('api_tokens', 'token_hash');
        self::assertNotNull($hash);
        self::assertSame('char(64)', $hash['column_type']);
        self::assertNull($this->column('api_tokens', 'token'), 'no raw-token column may exist');
        self::assertNotNull($this->column('api_tokens', 'scopes'));
        self::assertSame('json', $this->column('api_tokens', 'scopes')['type']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_tokens'
                   AND INDEX_NAME = 'uq_api_token_hash' AND NON_UNIQUE = 0",
            ),
            'token_hash must be uniquely indexed',
        );
    }

    public function test_moderation_log_enum_accepts_api_token(): void
    {
        $colType = (string) $this->db->fetchValue(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
        );
        self::assertStringContainsString("'api_token'", $colType);
    }
}
```

- [ ] **Step 2: Run it — expect FAIL** (table absent).

Run: `vendor/bin/phpunit tests/Integration/Core/AppApiTokensSchemaTest.php`
Expected: FAIL.

- [ ] **Step 3: Write the migration.** Create `database/migrations/0056_phase5_api_tokens.php`:

```php
<?php

declare(strict_types=1);

/**
 * 0056 · Phase 5 Gate A prerequisite (B2) — admin/service API tokens.
 *
 * ADDITIVE. Scoped, hash-only Bearer tokens. Also extends the
 * moderation_log.target_type ENUM with 'api_token' (mirroring 0055's
 * 'service_secret') so api_token_* audit rows are valid.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE api_tokens (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(80)     NOT NULL,
              token_hash   CHAR(64)        NOT NULL,
              scopes       JSON            NOT NULL,
              created_by   BIGINT UNSIGNED NOT NULL,
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_used_at DATETIME        NULL,
              expires_at   DATETIME        NULL,
              revoked_at   DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_api_token_hash (token_hash),
              KEY idx_api_token_created_by (created_by),
              KEY idx_api_token_active (revoked_at, expires_at),
              CONSTRAINT fk_api_token_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'api_token'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS api_tokens');
    }
};
```

- [ ] **Step 4: Run the schema test — expect PASS.**

Run: `vendor/bin/phpunit tests/Integration/Core/AppApiTokensSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Update the DDL docs.** In `SCHEMA.md`: replace the §3 `api_tokens` `CREATE TABLE` block with the migration's exact DDL (the `up()` table above), update/confirm the table-index row, add a §9 changelog line (`- 0056 — B2 api_tokens (scoped hash-only admin/service tokens) + moderation_log.target_type 'api_token'`), bump the doc version, and remove `api_tokens` from the "not built" notes. In `ADMIN.md §10.1`, replace its `api_tokens` DDL with the same shape.

- [ ] **Step 6: Rehearse the upgrade.**

Run: `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`
Expected: `Result: PASS ✓`.

- [ ] **Step 7: Commit.**

```bash
git add database/migrations/0056_phase5_api_tokens.php tests/Integration/Core/AppApiTokensSchemaTest.php SCHEMA.md ADMIN.md
git commit -m "Add migration 0056: api_tokens + moderation_log enum (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Primitives — `ApiScopes`, `ApiPrincipal`, exceptions

**Files:**
- Create: `src/Security/ApiScopes.php`, `src/Security/ApiPrincipal.php`, `src/Security/ApiForbiddenException.php`, `src/Core/ApiTokensDisabledException.php`
- Test: `tests/Unit/Security/ApiScopesTest.php`

**Interfaces:**
- Produces:
  - `ApiScopes::isValid(string): bool`, `ApiScopes::all(): array<string,string>`, `ApiScopes::SCOPES`
  - `ApiPrincipal::__construct(int $tokenId, string $name, string[] $scopes, int $createdBy, string $createdAt, string $tokenHash)`; accessors `tokenId/name/scopes/createdBy/createdAt/tokenHash`; `hasScope(string): bool`
  - `App\Security\ApiForbiddenException` (extends `RuntimeException`) with `scope(): string`
  - `App\Core\ApiTokensDisabledException` (extends `RuntimeException`)

- [ ] **Step 1: Write the failing unit test.** Create `tests/Unit/Security/ApiScopesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ApiPrincipal;
use App\Security\ApiScopes;
use PHPUnit\Framework\TestCase;

final class ApiScopesTest extends TestCase
{
    public function test_catalogue_validates_known_scopes(): void
    {
        self::assertTrue(ApiScopes::isValid('read:boards'));
        self::assertTrue(ApiScopes::isValid('read:threads'));
        self::assertFalse(ApiScopes::isValid('write:everything'));
        self::assertArrayHasKey('read:boards', ApiScopes::all());
    }

    public function test_principal_scope_check(): void
    {
        $p = new ApiPrincipal(7, 'ci', ['read:boards'], 3, '2026-06-28 00:00:00', str_repeat('a', 64));
        self::assertTrue($p->hasScope('read:boards'));
        self::assertFalse($p->hasScope('read:threads'));
        self::assertSame('ci', $p->name());
        self::assertSame(str_repeat('a', 64), $p->tokenHash());
        self::assertSame('2026-06-28 00:00:00', $p->createdAt());
    }
}
```

- [ ] **Step 2: Run it — expect FAIL** (classes not found).

Run: `vendor/bin/phpunit tests/Unit/Security/ApiScopesTest.php`
Expected: FAIL.

- [ ] **Step 3: Create the four classes.**

`src/Security/ApiScopes.php`:
```php
<?php

declare(strict_types=1);

namespace App\Security;

/** The read-only API scope catalogue (designed to extend with write/PII scopes later). */
final class ApiScopes
{
    /** @var array<string,string> scope => human description */
    public const SCOPES = [
        'read:boards' => 'List public boards',
        'read:threads' => 'Read threads in a public board',
    ];

    public static function isValid(string $scope): bool
    {
        return isset(self::SCOPES[$scope]);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::SCOPES;
    }
}
```

`src/Security/ApiPrincipal.php`:
```php
<?php

declare(strict_types=1);

namespace App\Security;

/** A non-human, scope-only API principal. Never a User; carries no role. */
final class ApiPrincipal
{
    /** @param string[] $scopes */
    public function __construct(
        private int $tokenId,
        private string $name,
        private array $scopes,
        private int $createdBy,
        private string $createdAt,
        private string $tokenHash,
    ) {
    }

    public function tokenId(): int { return $this->tokenId; }
    public function name(): string { return $this->name; }
    /** @return string[] */
    public function scopes(): array { return $this->scopes; }
    public function createdBy(): int { return $this->createdBy; }
    public function createdAt(): string { return $this->createdAt; }
    public function tokenHash(): string { return $this->tokenHash; }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
```

`src/Security/ApiForbiddenException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/** Internal control-flow signal: ApiController::respond() catches it and returns JSON 403. */
final class ApiForbiddenException extends RuntimeException
{
    public function __construct(private string $scope)
    {
        parent::__construct('Missing scope: ' . $scope);
    }

    public function scope(): string
    {
        return $this->scope;
    }
}
```

`src/Core/ApiTokensDisabledException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by ApiTokenService::mint() when the api_tokens kill switch is dark. */
final class ApiTokensDisabledException extends RuntimeException
{
}
```

- [ ] **Step 4: Run the test — expect PASS.**

Run: `vendor/bin/phpunit tests/Unit/Security/ApiScopesTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit.**

```bash
git add src/Security/ApiScopes.php src/Security/ApiPrincipal.php src/Security/ApiForbiddenException.php src/Core/ApiTokensDisabledException.php tests/Unit/Security/ApiScopesTest.php
git commit -m "Add ApiScopes, ApiPrincipal, API exceptions (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: `ApiTokenRepository` + `ApiTokenService` + wiring + rate-limit policy

**Files:**
- Create: `src/Repository/ApiTokenRepository.php`, `src/Service/ApiTokenService.php`
- Modify: `src/Core/App.php` (two container bindings), `config/config.php` (`api` rate-limit policy)
- Test: `tests/Integration/Service/ApiTokenServiceTest.php`

**Interfaces:**
- Consumes: Task 2 table; Task 3 primitives; `SecretBox`-style idioms; `WriteGate::assertCanWrite(User)`; `PasswordHasher::verify(string, ?string): bool`; `User::passwordHash(): ?string` / `id(): int`; `ModerationLogRepository::log(array): int`; `FeatureFlags::enabled(string): bool`; `Config::get(string, mixed)`.
- Produces:
  - `ApiTokenRepository`: `insert(string $name, string $hash, string $scopesJson, int $createdBy, ?string $expiresAt): int`, `findActiveByHash(string): ?array`, `touchLastUsed(int): void`, `revoke(int): void`, `list(): array`, `findById(int): ?array`
  - `ApiTokenService`: `mint(User, string $currentPassword, string $name, array $scopes, ?int $expiresInDays): array{token:string,id:int}`, `authenticate(string $bearer): ?ApiPrincipal`, `revoke(User, int): void`, `auditScopeDenied(ApiPrincipal, string): void`, `list(): array`
  - Container: `ApiTokenRepository::class`, `ApiTokenService::class`
  - Config: `rate_limits.api = [120, 60]`

> **Deviation from spec §4 (note for reviewer):** `ApiTokenService` does **not** take `UserRepository` — reauth reads the admin's hash directly via `$admin->passwordHash()`, mirroring `MfaService::requirePassword` (no re-fetch needed). Constructor is `(Database, ApiTokenRepository, ModerationLogRepository, FeatureFlags, Config, PasswordHasher, WriteGate)`.

- [ ] **Step 1: Write the failing service test.** Create `tests/Integration/Service/ApiTokenServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ApiTokensDisabledException;
use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use Tests\Support\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    private function service(bool $enabled = true): ApiTokenService
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => $enabled]);
        return new ApiTokenService(
            $this->db,
            new ApiTokenRepository($this->db),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new PasswordHasher(),
            new WriteGate(),
        );
    }

    private function admin(): \App\Domain\User
    {
        return $this->userEntity($this->makeAdmin(['password' => 'password123']));
    }

    public function test_mint_returns_plaintext_and_stores_only_hash(): void
    {
        $res = $this->service()->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        self::assertStringStartsWith('rbt_', $res['token']);
        $stored = (string) $this->db->fetchValue('SELECT token_hash FROM api_tokens WHERE id = ?', [$res['id']]);
        self::assertSame(hash('sha256', $res['token']), $stored);
    }

    public function test_authenticate_round_trips_then_revoke_and_expiry_deny(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $res = $svc->mint($admin, 'password123', 'ci', ['read:boards'], null);
        $p = $svc->authenticate('Bearer ' . $res['token']);
        self::assertNotNull($p);
        self::assertSame(['read:boards'], $p->scopes());
        self::assertTrue($p->hasScope('read:boards'));

        $svc->revoke($admin, $res['id']);
        self::assertNull($svc->authenticate('Bearer ' . $res['token']), 'revoked token must not authenticate');

        $res2 = $svc->mint($admin, 'password123', 'ci2', ['read:boards'], null);
        $this->db->run('UPDATE api_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?', [$res2['id']]);
        self::assertNull($svc->authenticate('Bearer ' . $res2['token']), 'expired token must not authenticate');
    }

    public function test_revoke_is_idempotent_and_audits_only_real_changes(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $res = $svc->mint($admin, 'password123', 'ci', ['read:boards'], null);

        $revokedRows = fn (int $id): int => (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_revoked' AND target_id = ?",
            [$id],
        );

        $svc->revoke($admin, $res['id']);
        self::assertSame(1, $revokedRows($res['id']), 'a real revoke writes exactly one audit row');

        // Already revoked -> repo affects 0 rows -> no second audit row (idempotent).
        $svc->revoke($admin, $res['id']);
        self::assertSame(1, $revokedRows($res['id']), 'a no-op revoke forges no audit row');

        // Unknown id -> nothing changes, nothing audited.
        $svc->revoke($admin, 999999);
        self::assertSame(0, $revokedRows(999999), 'revoking an unknown id forges no audit row');
    }

    public function test_flag_dark_kill_switch_on_authenticate(): void
    {
        $res = $this->service(true)->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        self::assertNull($this->service(false)->authenticate('Bearer ' . $res['token']));
    }

    public function test_wrong_password_blocks_mint(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->mint($this->admin(), 'WRONG', 'ci', ['read:boards'], null);
    }

    public function test_suspended_admin_cannot_mint(): void
    {
        $admin = $this->userEntity($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->expectException(ForbiddenException::class);
        $this->service()->mint($admin, 'password123', 'ci', ['read:boards'], null);
    }

    public function test_flag_dark_blocks_mint(): void
    {
        $this->expectException(ApiTokensDisabledException::class);
        $this->service(false)->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
    }

    /** @return array<string,array{0:array<int,mixed>,1:string,2:?int}> name => [scopes, name, expiresInDays] */
    public static function invalidMintCases(): array
    {
        return [
            'empty scopes' => [[], 'ci', null],
            'duplicate scopes' => [['read:boards', 'read:boards'], 'ci', null],
            'unknown scope' => [['write:all'], 'ci', null],
            'blank name' => [['read:boards'], '   ', null],
            'long name' => [['read:boards'], str_repeat('x', 81), null],
            'expiry too big' => [['read:boards'], 'ci', 400],
            'expiry zero' => [['read:boards'], 'ci', 0],
        ];
    }

    /**
     * @dataProvider invalidMintCases
     * @param array<int,mixed> $scopes
     */
    public function test_mint_validation_rejects(array $scopes, string $name, ?int $days): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->mint($this->admin(), 'password123', $name, $scopes, $days);
    }

    public function test_mint_writes_audit_without_secret(): void
    {
        $res = $this->service()->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        $row = $this->db->fetch(
            "SELECT after_json FROM moderation_log WHERE action = 'api_token_minted' AND target_id = ?",
            [$res['id']],
        );
        self::assertNotNull($row);
        self::assertStringNotContainsString($res['token'], (string) $row['after_json']);
        self::assertStringNotContainsString('password123', (string) $row['after_json']);
    }
}
```

- [ ] **Step 2: Run it — expect FAIL** (classes not found).

Run: `vendor/bin/phpunit tests/Integration/Service/ApiTokenServiceTest.php`
Expected: FAIL.

- [ ] **Step 3: Create the repository.** `src/Repository/ApiTokenRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Single-table SQL for api_tokens (hash-only, scoped Bearer tokens). */
final class ApiTokenRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insert(string $name, string $hash, string $scopesJson, int $createdBy, ?string $expiresAt): int
    {
        return $this->db->insert(
            'INSERT INTO api_tokens (name, token_hash, scopes, created_by, created_at, expires_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?)',
            [$name, $hash, $scopesJson, $createdBy, $expiresAt],
        );
    }

    public function findActiveByHash(string $hash): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
            [$hash],
        );
    }

    public function touchLastUsed(int $id): void
    {
        $this->db->run('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    /** @return int rows affected — 0 when the id is unknown or already revoked */
    public function revoke(int $id): int
    {
        return $this->db->run(
            'UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND revoked_at IS NULL',
            [$id],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> admin listing; excludes token_hash */
    public function list(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, scopes, created_by, created_at, last_used_at, expires_at, revoked_at
             FROM api_tokens ORDER BY id DESC',
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM api_tokens WHERE id = ?', [$id]);
    }
}
```

- [ ] **Step 4: Create the service.** `src/Service/ApiTokenService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\ApiTokensDisabledException;
use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Security\ApiPrincipal;
use App\Security\ApiScopes;
use App\Security\PasswordHasher;
use App\Security\WriteGate;

/**
 * Mint/authenticate/revoke admin/service API tokens. Tokens are shown once and
 * stored only as sha256 hashes. A token is a standalone scoped principal —
 * never a User. The api_tokens flag is a service-level kill switch.
 */
final class ApiTokenService
{
    public function __construct(
        private Database $db,
        private ApiTokenRepository $tokens,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
        private PasswordHasher $hasher,
        private WriteGate $writeGate,
    ) {
    }

    /**
     * @param array<int,mixed> $scopes
     * @return array{token:string,id:int}
     */
    public function mint(User $admin, string $currentPassword, string $name, array $scopes, ?int $expiresInDays): array
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$this->flags->enabled('api_tokens')) {
            throw new ApiTokensDisabledException('API tokens are disabled.');
        }
        if (!$this->hasher->verify($currentPassword, $admin->passwordHash())) {
            throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Name must be 1–80 characters.']);
        }
        $clean = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !ApiScopes::isValid($scope)) {
                throw new ValidationException(['scopes' => 'Unknown scope.']);
            }
            if (in_array($scope, $clean, true)) {
                // Spec: distinct scopes — a duplicate is a client error, not silently deduped.
                throw new ValidationException(['scopes' => 'Duplicate scope.']);
            }
            $clean[] = $scope;
        }
        if ($clean === []) {
            throw new ValidationException(['scopes' => 'Select at least one scope.']);
        }
        $expiresAt = null;
        if ($expiresInDays !== null) {
            if ($expiresInDays < 1 || $expiresInDays > 365) {
                throw new ValidationException(['expires_in_days' => 'Expiry must be 1–365 days.']);
            }
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $expiresInDays * 86400);
        }

        $plaintext = 'rbt_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $plaintext);

        $id = $this->db->transaction(function () use ($name, $hash, $clean, $admin, $expiresAt): int {
            $id = $this->tokens->insert($name, $hash, json_encode($clean) ?: '[]', $admin->id(), $expiresAt);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'api_token_minted',
                'target_type' => 'api_token',
                'target_id' => $id,
                'after' => ['name' => $name, 'scopes' => $clean, 'expires_at' => $expiresAt],
            ]);
            return $id;
        });

        return ['token' => $plaintext, 'id' => $id];
    }

    public function authenticate(string $bearer): ?ApiPrincipal
    {
        if (!$this->flags->enabled('api_tokens')) {
            return null;
        }
        // Require the "Bearer " scheme — a raw token without it must NOT authenticate.
        if (!preg_match('/^Bearer\s+(\S.*)$/i', trim($bearer), $m)) {
            return null;
        }
        $token = trim($m[1]);
        if ($token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $row = $this->tokens->findActiveByHash($hash);
        if ($row === null) {
            return null;
        }
        $this->tokens->touchLastUsed((int) $row['id']);
        $scopes = json_decode((string) $row['scopes'], true);
        return new ApiPrincipal(
            (int) $row['id'],
            (string) $row['name'],
            is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
            (int) $row['created_by'],
            (string) $row['created_at'],
            $hash,
        );
    }

    public function revoke(User $admin, int $tokenId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($admin, $tokenId): void {
            // Audit only a real state change: a no-op revoke (unknown id, or one already
            // revoked) must NOT forge an `api_token_revoked` row. Idempotent either way.
            if ($this->tokens->revoke($tokenId) !== 1) {
                return;
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'api_token_revoked',
                'target_type' => 'api_token',
                'target_id' => $tokenId,
            ]);
        });
    }

    public function auditScopeDenied(ApiPrincipal $p, string $scope): void
    {
        $this->log->log([
            'actor_id' => null,
            'action' => 'api_token_scope_denied',
            'target_type' => 'api_token',
            'target_id' => $p->tokenId(),
            'after' => ['scope' => $scope],
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->tokens->list();
    }
}
```

- [ ] **Step 5: Wire the container + rate-limit policy.** In `src/Core/App.php` add imports near the other repo/service `use` lines:

```php
use App\Repository\ApiTokenRepository;
use App\Service\ApiTokenService;
```

In `buildContainer()`, after the `ServiceSecretRepository` binding, add:

```php
        $c->bind(ApiTokenRepository::class, fn (Container $c) => new ApiTokenRepository($c->get(Database::class)));
        $c->bind(ApiTokenService::class, fn (Container $c) => new ApiTokenService(
            $c->get(Database::class),
            $c->get(ApiTokenRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
            $c->get(PasswordHasher::class),
            $c->get(WriteGate::class),
        ));
```

In `config/config.php`, inside the `rate_limits` array (after `'mfa_settings' => [10, 900],`), add:

```php
        'api' => [120, 60],
```

- [ ] **Step 6: Run the service test — expect PASS.**

Run: `vendor/bin/phpunit tests/Integration/Service/ApiTokenServiceTest.php`
Expected: PASS (all cases green).

- [ ] **Step 7: Commit.**

```bash
git add src/Repository/ApiTokenRepository.php src/Service/ApiTokenService.php src/Core/App.php config/config.php tests/Integration/Service/ApiTokenServiceTest.php
git commit -m "ApiTokenService: mint (writegate+reauth+validation) / authenticate / revoke (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: `ApiController` + read endpoints + `/api/v1/*` routes

**Files:**
- Create: `src/Controller/Api/ApiController.php`, `src/Controller/Api/MeController.php`, `src/Controller/Api/BoardsController.php`
- Modify: `src/Core/App.php` (`buildRouter()` — three GET routes + imports)
- Test: `tests/Integration/Api/ApiReadEndpointsTest.php`

**Interfaces:**
- Consumes: Task 4 `ApiTokenService`; `ApiPrincipal`/`ApiScopes`/`ApiForbiddenException`; `BoardRepository::allOrdered(): array` + `find(int): ?array`; `ThreadRepository::listByBoard(int $boardId, int $limit, int $offset, string $sort): array`; `RateLimitService::enforceSubject`; `Request::header('Authorization')`, `Request::int('limit', 20)`; `Response::json($data, $status)`.
- Produces: routes `GET /api/v1/me`, `GET /api/v1/boards`, `GET /api/v1/boards/{id}/threads`.

- [ ] **Step 1: Write the failing endpoint test.** Create `tests/Integration/Api/ApiReadEndpointsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\Response;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use Tests\Support\TestCase;

final class ApiReadEndpointsTest extends TestCase
{
    /** @param array<int,string> $scopes */
    private function mintToken(array $scopes, ?int $days = null): string
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => true]);
        $svc = new ApiTokenService(
            $this->db, new ApiTokenRepository($this->db), new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)), $this->config,
            new PasswordHasher(), new WriteGate(),
        );
        $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
        return $svc->mint($admin, 'password123', 'ci', $scopes, $days)['token'];
    }

    /** @param array<string,mixed> $query */
    private function apiGet(string $path, ?string $token, array $query = []): Response
    {
        $server = $token === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        return $this->requestWithServer('GET', $path, [], $query, $server);
    }

    public function test_me_requires_a_valid_token(): void
    {
        $token = $this->mintToken(['read:boards']); // /me ignores scopes, but mint needs >= 1
        $ok = $this->apiGet('/api/v1/me', $token);
        self::assertSame(200, $ok->status());
        $body = json_decode($ok->body(), true);
        self::assertSame('ci', $body['name']);
        self::assertNotEmpty($body['created_at']);

        self::assertSame(401, $this->apiGet('/api/v1/me', null)->status());
        self::assertSame(401, $this->apiGet('/api/v1/me', 'garbage')->status());

        // A raw token WITHOUT the "Bearer " scheme must be rejected.
        self::assertSame(401, $this->requestWithServer('GET', '/api/v1/me', [], [], ['HTTP_AUTHORIZATION' => $token])->status());
    }

    public function test_boards_scope_gating_and_public_only(): void
    {
        $catId = $this->makeCategory();
        $pub = $this->makeBoard($catId, ['visibility' => 'public', 'name' => 'Public B']);
        $this->makeBoard($catId, ['visibility' => 'private', 'name' => 'Secret B']);

        $token = $this->mintToken(['read:boards']);
        $r = $this->apiGet('/api/v1/boards', $token);
        self::assertSame(200, $r->status());
        $names = array_column(json_decode($r->body(), true)['boards'], 'name');
        self::assertContains('Public B', $names);
        self::assertNotContains('Secret B', $names, 'private boards must be absent from the API');

        // A token without read:boards is 403.
        self::assertSame(403, $this->apiGet('/api/v1/boards', $this->mintToken(['read:threads']))->status());
    }

    public function test_per_scope_granularity_and_threads_bound(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $author = $this->makeUser();
        for ($i = 0; $i < 25; $i++) {
            $this->makeThread($board, $author, 'T' . $i, 'body');
        }
        // read:boards but NOT read:threads → 403 on threads.
        self::assertSame(403, $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $this->mintToken(['read:boards']))->status());

        // read:threads → 200, default bound 20.
        $token = $this->mintToken(['read:threads']);
        $def = $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $token);
        self::assertSame(200, $def->status());
        self::assertLessThanOrEqual(20, count(json_decode($def->body(), true)['threads']));

        // limit=999 is clamped to 50 (query passed separately — requestWithServer does not parse '?').
        $big = $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $token, ['limit' => '999']);
        self::assertLessThanOrEqual(50, count(json_decode($big->body(), true)['threads']));
    }

    public function test_revoke_and_expiry_and_flag_dark(): void
    {
        $token = $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', $token)->status());

        // Immediate revoke → 401.
        $this->db->run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ?', [hash('sha256', $token)]);
        self::assertSame(401, $this->apiGet('/api/v1/me', $token)->status());

        // Flag dark → 404 for everyone. Mint a valid token WHILE the flag is on, then go
        // dark and reuse it (mintToken() itself re-enables the flag, so it must run first).
        $valid = $this->mintToken(['read:boards']);
        (new SettingRepository($this->db))->set('features', ['api_tokens' => false]);
        self::assertSame(404, $this->apiGet('/api/v1/me', $valid)->status());
    }

    public function test_scope_denial_is_audited_but_401_is_not(): void
    {
        $token = $this->mintToken(['read:threads']); // lacks read:boards
        $this->apiGet('/api/v1/boards', $token); // 403
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'"),
        );
        $this->apiGet('/api/v1/me', 'garbage'); // 401 unknown token
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_unauthorized'"),
            'unknown-token 401 must not be audited',
        );
    }

    public function test_router_caveat_unknown_path_is_html_not_json(): void
    {
        // The zero-kernel-change limitation: an unknown /api path is a kernel HTML 404.
        $r = $this->apiGet('/api/v1/does-not-exist', $this->mintToken(['read:boards']));
        self::assertSame(404, $r->status());
        self::assertStringNotContainsString('application/json', (string) $r->getHeader('content-type'));
    }

    public function test_rate_limit_returns_429_after_policy_max(): void
    {
        $token = $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', $token)->status());
        for ($i = 0; $i < 119; $i++) {
            $this->apiGet('/api/v1/me', $token);
        }
        self::assertSame(429, $this->apiGet('/api/v1/me', $token)->status(), 'the 121st call exceeds the api policy [120,60]');
    }
}
```

- [ ] **Step 2: Run it — expect FAIL** (routes/controllers missing → likely 404 HTML, assertions fail).

Run: `vendor/bin/phpunit tests/Integration/Api/ApiReadEndpointsTest.php`
Expected: FAIL.

- [ ] **Step 3: Create the base `ApiController`.** `src/Controller/Api/ApiController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Controller;
use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Security\ApiForbiddenException;
use App\Security\ApiPrincipal;
use App\Service\ApiTokenService;
use App\Service\RateLimitService;

/**
 * Base for /api/v1 controllers. Self-authenticates by Bearer and emits every
 * failure as JSON itself — the kernel (HTML errors, CSRF) is never reached for
 * a registered GET endpoint. Order: flag → authenticate → rate-limit → action.
 */
abstract class ApiController extends Controller
{
    /** @param callable(ApiPrincipal):Response $action */
    protected function respond(Request $request, callable $action): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('api_tokens')) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $service = $this->container->get(ApiTokenService::class);
        $principal = $service->authenticate((string) $request->header('Authorization'));
        if ($principal === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        try {
            $this->container->get(RateLimitService::class)->enforceSubject('api', $request, $principal->tokenHash());
        } catch (HttpException) {
            return Response::json(['error' => 'rate_limited'], 429);
        }
        try {
            return $action($principal);
        } catch (ApiForbiddenException $e) {
            $service->auditScopeDenied($principal, $e->scope());
            return Response::json(['error' => 'forbidden', 'scope' => $e->scope()], 403);
        }
    }

    protected function requireScope(ApiPrincipal $principal, string $scope): void
    {
        if (!$principal->hasScope($scope)) {
            throw new ApiForbiddenException($scope);
        }
    }
}
```

- [ ] **Step 4: Create `MeController` + `BoardsController`.**

`src/Controller/Api/MeController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Request;
use App\Core\Response;
use App\Security\ApiPrincipal;

final class MeController extends ApiController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        return $this->respond($request, fn (ApiPrincipal $p): Response => Response::json([
            'name' => $p->name(),
            'scopes' => $p->scopes(),
            'created_at' => $p->createdAt(),
        ]));
    }
}
```

`src/Controller/Api/BoardsController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardRepository;
use App\Repository\ThreadRepository;
use App\Security\ApiPrincipal;

final class BoardsController extends ApiController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return $this->respond($request, function (ApiPrincipal $p): Response {
            $this->requireScope($p, 'read:boards');
            $public = array_filter(
                $this->container->get(BoardRepository::class)->allOrdered(),
                static fn (array $b): bool => ($b['visibility'] ?? '') === 'public',
            );
            return Response::json(['boards' => array_map(static fn (array $b): array => [
                'id' => (int) $b['id'],
                'slug' => (string) $b['slug'],
                'name' => (string) $b['name'],
                'thread_count' => (int) ($b['thread_count'] ?? 0),
                'post_count' => (int) ($b['post_count'] ?? 0),
            ], array_values($public))]);
        });
    }

    /** @param array<string,string> $params */
    public function threads(Request $request, array $params): Response
    {
        return $this->respond($request, function (ApiPrincipal $p) use ($request, $params): Response {
            $this->requireScope($p, 'read:threads');
            $boardId = (int) ($params['id'] ?? 0);
            $board = $this->container->get(BoardRepository::class)->find($boardId);
            if ($board === null || ($board['visibility'] ?? '') !== 'public') {
                return Response::json(['error' => 'not_found'], 404);
            }
            $limit = min(50, max(1, $request->int('limit', 20)));
            $rows = $this->container->get(ThreadRepository::class)->listByBoard($boardId, $limit, 0, 'newest');
            return Response::json(['threads' => array_map(static fn (array $t): array => [
                'id' => (int) $t['id'],
                'slug' => (string) $t['slug'],
                'title' => (string) $t['title'],
                'reply_count' => (int) ($t['reply_count'] ?? 0),
            ], $rows)]);
        });
    }
}
```

- [ ] **Step 5: Register routes.** In `src/Core/App.php` add imports:

```php
use App\Controller\Api\BoardsController as ApiBoardsController;
use App\Controller\Api\MeController as ApiMeController;
```

In `buildRouter()` (with the other route registrations), add:

```php
        $r->get('/api/v1/me', [ApiMeController::class, 'show']);
        $r->get('/api/v1/boards', [ApiBoardsController::class, 'index']);
        $r->get('/api/v1/boards/{id}/threads', [ApiBoardsController::class, 'threads']);
```

(If `BoardRepository::find(int): ?array` does not already exist, add it: `public function find(int $id): ?array { return $this->db->fetch('SELECT * FROM boards WHERE id = ?', [$id]); }` — verify first; most board read paths already use it.)

- [ ] **Step 6: Run the endpoint test — expect PASS.**

Run: `vendor/bin/phpunit tests/Integration/Api/ApiReadEndpointsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit.**

```bash
git add src/Controller/Api/ApiController.php src/Controller/Api/MeController.php src/Controller/Api/BoardsController.php src/Core/App.php tests/Integration/Api/ApiReadEndpointsTest.php
git commit -m "Read-only /api/v1 endpoints with Bearer auth + scope gating (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Admin token-management UI + Playwright evidence

**Files:**
- Create: `src/Controller/AdminApiTokenController.php`, `templates/admin/api_tokens.php`
- Modify: `src/Core/App.php` (`buildRouter()` — admin routes + import); `templates/admin/dashboard.php` (flag-gated discovery link)
- Test: `tests/Integration/Api/AdminApiTokenTest.php`; browser evidence under `tests/browser/` (incl. `tests/browser/seed.php` — enable the `api_tokens` flag so the dark-by-default page is reachable)

**Interfaces:**
- Consumes: `ApiTokenService`; `ApiScopes::all()`; base `Controller` helpers (`requireAdmin`, `view`, `redirectWithFlash`); `Request::str/post`; `NotFoundException`.
- Produces: routes `GET/POST /admin/api-tokens`, `POST /admin/api-tokens/{id}/revoke`.

- [ ] **Step 1: Write the failing admin test.** Create `tests/Integration/Api/AdminApiTokenTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminApiTokenTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => true]);
    }

    public function test_admin_mints_a_token_shown_once(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'tokadmin', 'password' => 'password123']));

        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'password123', 'expires_in_days' => '',
        ]);
        // The plaintext is shown ONCE, directly in the mint response (200, not a redirect —
        // the token must never travel through the cookie-backed Flash).
        $this->assertStatus(200, $res);
        self::assertStringContainsString('rbt_', $res->body());

        // A later GET does not show it again (nothing persisted it — no cookie, no DB plaintext).
        self::assertStringNotContainsString('rbt_', $this->get('/admin/api-tokens')->body());

        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
    }

    public function test_mint_requires_correct_reauth(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));

        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'WRONG',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('CI', $res->body(), 'the typed name is preserved');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'), 'no token on bad reauth');
    }

    public function test_routes_are_404_when_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/api-tokens'));
    }

    public function test_suspended_admin_cannot_mint(): void
    {
        $this->enable();
        $admin = $this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']);
        $this->actingAs($admin);
        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'password123',
        ]);
        $this->assertStatus(403, $res); // WriteGate -> ForbiddenException
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
    }
}
```

- [ ] **Step 2: Run it — expect FAIL** (routes missing).

Run: `vendor/bin/phpunit tests/Integration/Api/AdminApiTokenTest.php`
Expected: FAIL.

- [ ] **Step 3: Create the admin controller.** `src/Controller/AdminApiTokenController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\ApiScopes;
use App\Service\ApiTokenService;

final class AdminApiTokenController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(\App\Core\FeatureFlags::class)->enabled('api_tokens')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/api_tokens', [
            'tokens' => $this->container->get(ApiTokenService::class)->list(),
            'scopes_catalogue' => ApiScopes::all(),
            'errors' => [],
            'old' => [],
            'new_token' => null,
        ]);
    }

    /** @param array<string,string> $params */
    public function mint(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->container->get(ApiTokenService::class);
        $days = $request->str('expires_in_days');
        try {
            $result = $service->mint(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                (array) $request->post('scopes', []),
                $days === '' ? null : (int) $days,
            );
            // One-time plaintext: render DIRECTLY (not via the cookie-backed Flash, which
            // would leak the token into a Set-Cookie header). A later GET has no new_token,
            // so the secret is shown exactly once. A reload re-POSTs (mints again) — an
            // accepted minor wart; the alternative (cookie flash) leaks the secret.
            return $this->view('admin/api_tokens', [
                'tokens' => $service->list(),
                'scopes_catalogue' => ApiScopes::all(),
                'errors' => [],
                'old' => [],
                'new_token' => $result['token'],
            ]);
        } catch (ValidationException $e) {
            return $this->view('admin/api_tokens', [
                'tokens' => $service->list(),
                'scopes_catalogue' => ApiScopes::all(),
                'errors' => $e->errors,
                'old' => $e->old + ['name' => $request->str('name')],
                'new_token' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function revoke(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(ApiTokenService::class)->revoke($admin, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/api-tokens', 'API token revoked.');
    }
}
```

- [ ] **Step 4: Create the template.** `templates/admin/api_tokens.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'API tokens');
?>
<h1>API tokens</h1>

<?php if (!empty($new_token)): ?>
    <div class="flash" role="status">
        <strong>Copy this token now — it will not be shown again:</strong>
        <code><?= $e($new_token) ?></code>
    </div>
<?php endif; ?>

<form method="post" action="/admin/api-tokens" class="stacked">
    <?= $this->csrfField() ?>
    <label>Name
        <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" required>
    </label>
    <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

    <fieldset>
        <legend>Scopes</legend>
        <?php foreach ($scopes_catalogue as $scope => $desc): ?>
            <label><input type="checkbox" name="scopes[]" value="<?= $e($scope) ?>"> <?= $e($scope) ?> — <?= $e($desc) ?></label>
        <?php endforeach; ?>
    </fieldset>
    <?php if (!empty($errors['scopes'])): ?><p class="field-error"><?= $e($errors['scopes']) ?></p><?php endif; ?>

    <label>Expires in days (optional)
        <input type="number" name="expires_in_days" min="1" max="365">
    </label>
    <?php if (!empty($errors['expires_in_days'])): ?><p class="field-error"><?= $e($errors['expires_in_days']) ?></p><?php endif; ?>

    <label>Confirm your password
        <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

    <button type="submit">Create token</button>
</form>

<table>
    <thead><tr><th>Name</th><th>Scopes</th><th>Created</th><th>Last used</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tokens as $t): ?>
        <tr>
            <td><?= $e($t['name']) ?></td>
            <td><?= $e(implode(', ', json_decode((string) $t['scopes'], true) ?: [])) ?></td>
            <td><?= $e((string) $t['created_at']) ?></td>
            <td><?= $e((string) ($t['last_used_at'] ?? '—')) ?></td>
            <td><?= $t['revoked_at'] ? 'revoked' : 'active' ?></td>
            <td>
                <?php if (!$t['revoked_at']): ?>
                <form method="post" action="/admin/api-tokens/<?= (int) $t['id'] ?>/revoke">
                    <?= $this->csrfField() ?>
                    <button type="submit">Revoke</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
```

(Template API confirmed against `templates/admin/branding.php` + `src/Core/View.php`: a leaf calls `$this->layout('layout')` + `$this->section('title', …)` and then **echoes its body directly** — the body is captured by `View::renderTemplate` and passed to the layout as `$content`. There is **no** `section('content')`/`endSection()` here; the capture API is `start()`/`stop()`, which this page does not need. `$this->csrfField()` and the `$e` escaper closure are both real.)

- [ ] **Step 5: Register the admin routes.** In `src/Core/App.php` add the import:

```php
use App\Controller\AdminApiTokenController;
```

and in `buildRouter()` (with the other `/admin/*` routes):

```php
        $r->get('/admin/api-tokens', [AdminApiTokenController::class, 'index']);
        $r->post('/admin/api-tokens', [AdminApiTokenController::class, 'mint']);
        $r->post('/admin/api-tokens/{id}/revoke', [AdminApiTokenController::class, 'revoke']);
```

- [ ] **Step 6: Run the admin test — expect PASS.**

Run: `vendor/bin/phpunit tests/Integration/Api/AdminApiTokenTest.php`
Expected: PASS.

- [ ] **Step 7: Flag-gated discovery link + enable the flag for evidence + Playwright capture.**
  1. **Discovery link.** Add a flag-gated "API tokens" link to the dashboard subnav in `templates/admin/dashboard.php` — inside the existing `<nav class="subnav">`, mirroring the sibling `<a href="/admin/structure">…</a>`, wrapped in `<?php if (!empty($features['api_tokens'])): ?> … <?php endif; ?>`. (`$features` is a shared view global — confirmed `App::shareViewGlobals()` → `View::share(['features' => …])` — so **no controller change is needed**. Gating on the flag keeps the link from ever pointing at a dark-flag 404. Note: this codebase does **not** nav-link every admin subpage — `/admin/branding` has no nav entry at all — so a single gated dashboard entry is the discovery surface; the new page carries its own subnav back to Dashboard.)
  2. **Enable the flag in the evidence DB.** The `api_tokens` flag defaults **dark**, so `/admin/api-tokens` 404s on a freshly-seeded evidence DB (and the gated link stays hidden) — see the dark-flag test at Task 6 Step 1. In `tests/browser/seed.php`, inside the settings block of the seed transaction (next to `registration_mode`), add `$settings->set('features', ['api_tokens' => true]);`.
  3. **Capture browser evidence.** Extend the Playwright evidence flow (`tests/browser/`, run `npm run evidence`) with a step that signs in as the seeded admin, navigates to `/admin/api-tokens`, submits the mint form **without JS** (plain form submit), asserts the one-time `rbt_` banner appears, then revokes the token. Save the screenshot(s) under `docs/evidence/browser/`.

Run: `cd tests/browser && npm run evidence`
Expected: the new admin-api-token screenshots are produced; the flow passes.

- [ ] **Step 8: Commit.**

```bash
git add src/Controller/AdminApiTokenController.php templates/admin/api_tokens.php templates/admin/dashboard.php src/Core/App.php tests/Integration/Api/AdminApiTokenTest.php tests/browser docs/evidence
git commit -m "Admin API-token management UI + Playwright evidence (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: Status docs + full-suite + upgrade closeout

**Files:**
- Modify: `PHASE_5_STATUS.md`

- [ ] **Step 1: Run the full suite.**

Run: `./vendor/bin/phpunit`
Expected: PASS (the prior green total + the new `ApiScopesTest`, `ApiTokenServiceTest`, `ApiReadEndpointsTest`, `AdminApiTokenTest`, `AppApiTokensSchemaTest`, and the extended flag assertion). Record the exact totals.

- [ ] **Step 2: Rehearse the upgrade.**

Run: `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`
Expected: `Result: PASS ✓`.

- [ ] **Step 3: Update `PHASE_5_STATUS.md`.** Add a "Landed in this increment" group for the API-token read-only slice (migration `0056`; `ApiTokenService`/`ApiTokenRepository`; `ApiScopes`/`ApiPrincipal`; the `api_tokens` dark flag + service-level kill switch; reauth + WriteGate on mint; read-only `/api/v1/me|boards|boards/{id}/threads`; admin UI; the `api` rate-limit; the deliberate audit policy). Update the B2 ledger: **sub-project 2 of 4 landed** (registry ✓, **api-tokens ✓**, webhooks ⬜, hook registry ⬜). Update the suite total from Step 1.

- [ ] **Step 4: Commit.**

```bash
git add PHASE_5_STATUS.md
git commit -m "Record B2 API-tokens increment (Phase 5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (spec § → task): §1 zero-CSRF (GET-only + `respond` JSON) → Tasks 5; §2 migration + enum → Task 2; §3 scopes → Task 3; §4 repo/service/`ApiController`/controllers (mint reauth+writegate+validation, authenticate kill switch, revoke, auditScopeDenied) → Tasks 3/4/5; §5 data flow → Tasks 5/6; §6 error handling (401/403/404/422/429 + router caveat) → Tasks 5/6; §7 flag + rate-limit → Tasks 1/4; §8 wiring → Tasks 4/5/6; §9 evidence — the 19 behaviors map to: `ApiTokenServiceTest` (mint/auth/revoke/expiry/validation/writegate/flag/audit), `ApiReadEndpointsTest` (me 200/401, boards scope + public-only, per-scope, threads bound, revoke/expiry/flag-dark, scope-denial audit, router caveat, rate-limit), `AdminApiTokenTest` (mint-once, reauth-required, flag-dark, suspended-admin), `AppApiTokensSchemaTest`, `AppFeatureFlagTest`, Playwright (Task 6).

**Placeholder scan:** none — every code step shows complete code; commands show expected output. The only conditional ("if `BoardRepository::find` doesn't exist") gives the exact method body to add.

**Type consistency:** `ApiTokenService` constructor `(Database, ApiTokenRepository, ModerationLogRepository, FeatureFlags, Config, PasswordHasher, WriteGate)` is identical in the container binding (Task 4 Step 5), the service test helper (Task 4 Step 1), and the endpoint test helper (Task 5 Step 1). `ApiPrincipal` constructor arg order `(tokenId, name, scopes, createdBy, createdAt, tokenHash)` matches the unit test (Task 3) and `authenticate()` (Task 4). `mint(User, currentPassword, name, scopes, expiresInDays)` matches all call sites. `respond()`/`requireScope()` signatures match the controllers (Task 5).
