# Design: Admin/Service API Tokens + Scopes (read-only slice)

**Date:** 2026-06-28
**Status:** Approved design (brainstorming output) — pending implementation plan.
**Phase / gate:** Phase 5, Gate A prerequisite. **Sub-project 2 of 4** of the B2
"trusted hook/webhook/API-token/secret foundation" (ADR 0004 Part B, row B2).

> Precedence (CLAUDE.md): `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > surface specs.
> Where this design and an authoritative doc disagree, the authoritative doc wins.

---

## 0. Context — the B2 program and where this fits

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

### Authoritative requirements this slice satisfies

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

### Brainstorming decisions locked

- **Scope:** read-only first slice. **No** write/mutation endpoints, **no** CSRF-realm
  change, **no** private/membership-gated content (public content only), **no** service
  principals beyond admin-minted tokens (Gate B, P5-14), **no** webhook scopes.
- **Principal model:** a token is a **standalone non-human principal** carrying only its
  scopes. It never becomes a `User` and never inherits its creator's role. `/api`
  controllers authorize by **scope**, not `requireUser/requireAdmin`.

---

## 1. Why this needs zero CSRF changes (the central design fact)

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

## 2. Data model — migration `0056_phase5_api_tokens.php`

Additive, matching the `SCHEMA.md §3` target DDL exactly (no extra columns — tokens are
identified in the UI by their required `name`, so no secret fragment is stored).

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

`SCHEMA.md` is updated after the migration lands (shape row + §9 changelog + version
bump); the `api_tokens` "not built" notes are cleared.

---

## 3. Scope vocabulary — `App\Security\ApiScopes`

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

## 4. Components

Layering follows the existing thin-controller → service → repository pattern.

- **`App\Security\ApiPrincipal`** — immutable value object `{tokenId:int, name:string,
  scopes:string[], createdBy:int}` + `hasScope(string): bool`. **Not** a `User`; carries
  no role.
- **`App\Repository\ApiTokenRepository`** (`(private Database $db)`):
  - `insert(name, tokenHash, scopesJson, createdBy, ?expiresAt): int`
  - `findActiveByHash(string $hash): ?array` — `WHERE token_hash = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())`
  - `touchLastUsed(int $id): void`
  - `revoke(int $id): void` — set `revoked_at = UTC_TIMESTAMP()` (gated `WHERE revoked_at IS NULL`)
  - `list(): array` — for the admin UI; selects everything **except** `token_hash`, newest first
  - `findById(int): ?array`
- **`App\Service\ApiTokenService`** `(Database, ApiTokenRepository, ModerationLogRepository, FeatureFlags, Config)`:
  - `mint(User $admin, string $name, array $scopes, ?int $expiresInDays): array{token:string, id:int}` — validate each scope via `ApiScopes::isValid` (else `ValidationException`); generate plaintext `'rbt_' . bin2hex(random_bytes(24))`; store `sha256` hash + scopes + optional `expires_at`; audit `api_token_minted` (scopes + name, **no token**); return the plaintext **once**. Txn (insert + audit).
  - `authenticate(string $bearer): ?ApiPrincipal` — strip the `Bearer ` prefix; `sha256`; `findActiveByHash`; on hit `touchLastUsed` and return `ApiPrincipal`; else `null`.
  - `revoke(User $admin, int $tokenId): void` — `revoke` + audit `api_token_revoked`. Txn. Idempotent.
  - `list(): array` — passthrough for the admin UI.
- **`App\Controller\Api\ApiController`** (base, extends the existing `Controller`). **All
  `/api` error handling is contained here — the kernel is never involved**, so the kernel's
  HTML error rendering (and the rest of the app) is untouched. A single wrapper runs each
  action, in this order:
  - `respond(Request $req, callable(ApiPrincipal): Response $action): Response` —
    1. **flag gate** — `api_tokens` dark → `Response::json(['error'=>'not_found'], 404)`;
    2. **authenticate** — read `Authorization` via `Request::header()`, strip `Bearer `,
       `ApiTokenService::authenticate`; `null` → `Response::json(['error'=>'unauthorized'], 401)`;
    3. **rate limit** — `enforceSubject('api', $req, $tokenHash)` for the now-authenticated
       token (wrap the limiter's `HttpException(429)` → `Response::json(['error'=>'rate_limited'], 429)`);
    4. run `$action($principal)`, catching `ApiForbiddenException` → `Response::json(['error'=>'forbidden','scope'=>…], 403)`.
  - `requireScope(ApiPrincipal $p, string $scope): void` — `$p->hasScope($scope)` else throw
    the small internal `App\Security\ApiForbiddenException($scope)` (caught by `respond`).

  Each `/api` action is then one line: `return $this->respond($req, fn (ApiPrincipal $p) => …)`.
- **`App\Controller\Api\MeController`** — `GET /api/v1/me` → valid token, no scope → `{name, scopes, created_at}`.
- **`App\Controller\Api\BoardsController`** — `GET /api/v1/boards` (`read:boards`) → public boards `[{id, slug, name, thread_count, post_count}]`; `GET /api/v1/boards/{id}/threads` (`read:threads`) → public board's threads `[{id, slug, title, reply_count}]` (404 JSON if the board isn't public).
- **`App\Controller\AdminApiTokenController`** — `requireAdmin()` + normal session/CSRF:
  - `GET /admin/api-tokens` → list + mint form
  - `POST /admin/api-tokens` → mint; flash the plaintext **once** ("copy now — it won't be shown again"); on `ValidationException` re-render 422 with old input
  - `POST /admin/api-tokens/{id}/revoke` → revoke
  - All 404 when the flag is dark.
- **Template:** `templates/admin/api_tokens.php` — token list (name, scopes, created, last-used, status), mint form (name + scope checkboxes + optional expiry days), and the one-time plaintext banner.

JSON responses use the existing `Response::json($data, $status)`.

---

## 5. Data flow

**Machine (read):** `GET /api/v1/boards` + `Authorization: Bearer rbt_…` → `ApiController`
flag-gate → `authenticate()` → `ApiPrincipal` → `requireScope('read:boards')` →
`BoardsController` returns public boards JSON. Revoked / expired / invalid / missing token
→ **401**; valid token lacking the scope → **403**; over the rate limit → **429**.

**Admin (browser):** `/admin/api-tokens` lists tokens; the mint form POSTs (session+CSRF)
→ `ApiTokenService::mint` → the **plaintext is flashed once**; a revoke button POSTs →
`revoke` → the token stops authenticating on the next request.

---

## 6. Error handling

Resolution order inside `ApiController::respond` is **flag → authenticate → per-token
rate-limit → scope → action**; every `/api` failure is JSON emitted by `ApiController`
itself (the kernel's HTML error path is never reached for `/api`). The admin pages, being
ordinary browser routes, throw `NotFoundException` (HTML 404) when the flag is dark.

- **401** — no/invalid/expired/revoked token (one undifferentiated response — no oracle).
- **403** — valid token, missing scope.
- **404** — `api_tokens` flag dark (the `/api` JSON 404 and admin HTML 404 both read as absent).
- **429** — per-token rate limit exceeded (only an *authenticated* token consumes the budget; the 192-bit token entropy, not the limiter, is what makes guessing infeasible).
- **422** — admin mint with an invalid/empty scope or name → `ValidationException` caught
  by the controller, form re-rendered with `->errors` + old input (anti-draft-loss).
- Every audit row is written **inside the same transaction** as its mutation (mint/revoke).
- **`HTTP_AUTHORIZATION` portability:** some SAPIs strip the header; absent → 401. The spec
  notes the deploy must pass `Authorization` through (e.g. an Apache rewrite); no
  `getallheaders()` fallback is added in this slice.
- **Redaction:** the token plaintext appears in no audit JSON, no log, no list/response,
  and no exception message — only its `sha256` hash is stored.

---

## 7. Feature flag & rate limit

- **Flag** `'api_tokens' => false` in `FeatureFlags::DEFAULTS` (B2-foundation, deploy-dark).
  Gates the `/api/v1/*` surface **and** the admin pages (404 when dark) and doubles as a
  kill switch (dark → all token auth stops, admin UI hidden). A regression test asserts it
  defaults dark.
- **Rate limit** a new `'api' => [120, 60]` policy in `config/config.php` `rate_limits`
  (120 requests/min per token, tunable), keyed by the **token hash** via
  `RateLimitService::enforceSubject` (which sha256-hashes the subject).

---

## 8. Container wiring (additive)

`App::buildContainer()` binds `ApiTokenRepository` then `ApiTokenService` (closure pulling
`Database` + collaborators via `$c->get(...)`). Unlike sub-project 1, these bindings **are**
exercised — the `/api` controllers resolve `ApiTokenService` from the container at dispatch
— so they are covered by the endpoint tests (no unexercised-wiring risk). `App::buildRouter()`
registers the `/api/v1/*` GET routes and the `/admin/api-tokens` GET/POST routes (literal
prefixes — first-match-wins is not at risk).

---

## 9. Testing & evidence (the "done" bar)

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
8. **Admin mint:** `POST /admin/api-tokens` → the response shows the plaintext **once**;
   a reload of `/admin/api-tokens` does **not** show it again; only the hash is stored.
9. **Audit + redaction:** `api_token_minted` / `api_token_revoked` rows exist in
   `moderation_log`; the plaintext appears in **no** audit JSON.
10. **Flag-dark:** with `api_tokens` off, `/api/v1/me`, `/api/v1/boards`, and
    `/admin/api-tokens` all → 404; flipping it on restores them.
11. **Hash-only schema:** no plaintext/`token` column; `token_hash` is uniquely indexed.

**`AppFeatureFlagTest`:** extend to assert `api_tokens` defaults dark.

**Schema-shape test** — `tests/Integration/Core/AppApiTokensSchemaTest`: a fresh migrate
applies `0056`; `api_tokens` has the documented columns/types; `token_hash` is
`CHAR(64)` + uniquely indexed; no raw-token column.

**Browser/no-JS (Playwright):** an admin opens `/admin/api-tokens`, mints a token with a
no-JS form submit, sees the one-time plaintext banner, sees it in the list (by name), and
revokes it. Capture evidence artifacts.

**Suite + upgrade rehearsal:** `./vendor/bin/phpunit` green; `verify:upgrade --force`
rehearses `0056` additively on seeded data.

**Docs:** `SCHEMA.md` (shape + §9 changelog + version bump, clear the `api_tokens` "not
built" notes); `PHASE_5_STATUS.md` records this increment + the B2 ledger (sub-project 2
landed).

---

## 10. Component isolation summary

| Unit | Does | Used via | Depends on |
|---|---|---|---|
| `ApiScopes` | the scope catalogue + validation | `isValid()` / `all()` | — (pure) |
| `ApiPrincipal` | a non-human scoped identity | `hasScope()` | — (value object) |
| `ApiTokenRepository` | single-table SQL for `api_tokens` | typed methods returning arrays | `Database` |
| `ApiTokenService` | mint/authenticate/revoke/list, audit, txns, scope validation | those four methods | `Database`, repo, `ModerationLogRepository`, `FeatureFlags`, `Config` |
| `Api\ApiController` (+ `Me`/`Boards`) | Bearer auth, scope enforcement, flag gate, rate limit, JSON | `/api/v1/*` GET routes | `ApiTokenService`, `RateLimitService` |
| `AdminApiTokenController` | admin mint/list/revoke UI (session+CSRF) | `/admin/api-tokens` routes | `ApiTokenService` |

Consumers depend only on `ApiTokenService`'s methods and the `ApiPrincipal`/scope surface,
never on the table shape. The `/api` realm is GET-only and self-authenticating; the
existing session/CSRF path is untouched.
