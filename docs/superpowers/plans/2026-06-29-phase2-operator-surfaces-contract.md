# Phase 2 Operator Surfaces — Shared Implementation Contract

> Binding contract for the four Phase 2 operator-surface plans. Every plan's
> Global Constraints section incorporates this file by reference. Where a plan
> conflicts with this contract, **this contract wins** (it encodes the locked
> product decisions + DECISIONS.md/ADMIN.md precedence).

**Date locked:** 2026-06-29
**Authoritative specs:** DECISIONS.md > DESIGN.md > SCHEMA.md > ADMIN.md / COMMUNITY.md (CLAUDE.md precedence chain).
**Migrations:** none required — all six surfaces are schema-ready (verified against `database/migrations/` up to `0057`). Do **not** add a migration; if a plan thinks it needs one, stop and escalate.

---

## Locked product decisions (2026-06-29)

1. **Email-ops** — build the full admin email delivery dashboard now (delivery log, test-send, suppression view + add/remove, From/config status, CSV export). It was re-scoped to Phase 3 in `docs/PHASE_2_STATUS.md:382`, so the work **must** record the pull-forward: update `docs/PHASE_2_STATUS.md` and add `docs/adr/0005-phase2-operator-surface-closeout.md`. Never silently reverse a recorded deferral.
2. **Announcements** — first cut is **site banner + in-app broadcast notification only**. Do **NOT** build the email-broadcast channel and do **NOT** modify `src/Worker/NotificationEmailWorker.php`. (The worker silently drops `kind='system'` rows; that fix is explicitly deferred and recorded in ADR 0005.)
3. **Per-user admin record** — build the ADMIN §5.2 screen at `GET /admin/users/{id}` (plus a minimal §5.1 directory at `GET /admin/users`) as the host for **both** badge grant/revoke **and** title override. Do not bolt these onto the public profile.
4. **Board archive** — archived board is **absolute read-only for every role** (members, moderators, admins); only **unarchive** re-enables writes. The board stays **listed in nav, readable, and searchable**. "Read-only" covers **every content-mutation path that targets the board, with no carve-out** — verified complete by the Group B whole-branch review (2026-06-29) and closed accordingly: new thread, new reply, post edit, post delete-own, thread status/workflow change, wiki make/edit/revert, **reactions** (engagement, moves reputation), **tag editing** (carve-out removed — tagging follows `canPost`), **moderator content actions** (delete/restore/move-source+dest/pin/lock), **accept/unmark solved answer** (incl. re-mark on an already-solved thread), and **thread assign/unassign**. The plan's original 6-path enumeration was incomplete; the additional 5 paths were closed under decision "close everything" (a board-mod who is not a member of a *private* board can no longer tag there — a deliberate tightening, flagged for product-owner signoff).

---

## Cross-cutting defaults (apply to all four plans)

- **Authority:** every mutating admin action gates on `requireAdmin()` (→ `ForbiddenException`/403 for non-admins; redirecting 302 to `/login?next=` for guests). For **user-targeted writes** (badges, title) route through services that already call `WriteGate::assertCanWrite` so a *suspended admin* is blocked (state beats role) — reuse `UserModerationService` patterns; do not hand-roll `isAdmin()`.
- **Audit:** every mutating admin action writes one `moderation_log` row via `ModerationLogRepository::log([...])`, mirroring `UserModerationService::audit()` / `AdminService` audit writes. Use existing `target_type` ENUM values only — **no ENUM extension, no migration**:
  - badges / title → `target_type='user'`, `target_id` = subject user id
  - board archive/unarchive → `target_type='board'`, `target_id` = board id
  - category reorder → `target_type='category'`; board reorder → `target_type='board'`
  - announcements → `target_type='setting'` (mirror `AdminService::updateSettings` audit shape)
  - email-ops (test-send / suppress / unsuppress / requeue) → `target_type='setting'`
- **`reason`:** optional free-text on grant/revoke/title; persisted into the audit row (`reason` / `after_json`) when provided. Never required.
- **CSRF:** every POST form emits `<?= $this->csrfField() ?>`. No mutating GET, ever. (CSV export is a read-only GET — allowed, admin-scoped.)
- **CSP / PE:** no inline `<script>`/`<style>`. Server-rendered HTML+forms must work with JS off. JS only decorates via `public/assets/app.js` + `data-*` hooks (e.g. drag-reorder, banner dismissal).
- **Anti-draft-loss:** controllers catch `App\Core\ValidationException` themselves (kernel does NOT) and re-render the form at **422** carrying `$e->errors` + `$e->old` (render-in-place pattern), or `redirectWithFlash($to, $e->first())` for actions whose form lives elsewhere (mirror `AdminController::run()`).
- **DB rules:** `EMULATE_PREPARES=false` — never bind `LIMIT`/`OFFSET` (clamp to int + concatenate); never reuse a named placeholder. UTC everywhere. Every multi-table mutation runs inside `$db->transaction(fn)`.
- **Counters:** none of these surfaces touch denormalized counters or reputation. Do **not** add `RepairService` hooks. (Archive must NOT recompute/zero `boards.*_count` — content is preserved.)
- **Tests:** PHPUnit is strict (`failOnWarning`/`failOnRisky`, ≥1 assertion/test). Per-test isolation is one rolled-back transaction with no savepoints — **assert observable HTTP behavior, not row counts**, except where the production path commits its own transaction. Every UI-visible surface also needs Playwright evidence in `tests/browser` (DESIGN §13).

---

## Feature-flag allocation (`src/Core/FeatureFlags.php` DEFAULTS)

| Group | Surface | Flag | Default | Gating |
|---|---|---|---|---|
| A | badges + titles (per-user admin record) | **none — UNGATED** | — | Fixed-set manual badge grant is a Phase-2 P1 carryover and ships ungated; title override is core. Do **NOT** route-gate behind `badge_rules` (that's Phase-4 custom rules). |
| B | reorder + archive | **none — UNGATED** | — | Part of core admin *structure*, which is unflagged. |
| C | announcements | **new `announcements`** | `true` (Phase-2 convention) | Every route action calls a `gate()` that throws `NotFoundException` (→404) when the flag is off; `AppFeatureFlagTest` asserts the routes 404 when off + the flag key exists. |
| D | email-ops | **existing `email`** (`:31`, default `true`) | — | Gate every route action behind `email`; routes 404 when off; add the regression assertion. |

New-subsystem flag convention (announcements): add `'announcements' => true,` in the Phase-2 block of `DEFAULTS` with an inline comment naming the subsystem + `ADMIN §7.4`, gate every route, add the dashboard nav link conditionally, enable it in `tests/browser/seed.php`.

---

## Route allocation (register in `App::buildRouter()`, specific-before-generic, all `requireAdmin`)

**Group A — per-user admin record:**
```
GET  /admin/users                         AdminUserController::index   (directory, paginated/searchable)
GET  /admin/users/{id}                    AdminUserController::show     (record screen)
POST /admin/users/{id}/title              AdminUserController::setTitle
POST /admin/users/{id}/badges/grant       AdminUserController::grantBadge
POST /admin/users/{id}/badges/revoke      AdminUserController::revokeBadge
```

**Group B — board structure (register next to the existing `/admin/categories` + `/admin/boards` block):**
```
POST /admin/categories/{id}/move          AdminController::moveCategory   (body dir=up|down)
POST /admin/boards/{id}/move              AdminController::moveBoard       (body dir=up|down)
POST /admin/structure/reorder             AdminController::reorder         (bulk ordered-id list; JS-drag target)
POST /admin/boards/{id}/archive           AdminController::archiveBoard
POST /admin/boards/{id}/unarchive         AdminController::unarchiveBoard
```

**Group C — announcements (flag-gated `announcements`):**
```
GET  /admin/announcements                 AdminAnnouncementController::form
POST /admin/announcements                 AdminAnnouncementController::save   (set/clear banner + opt in-app broadcast)
```

**Group D — email-ops (flag-gated `email`):**
```
GET  /admin/email                         AdminEmailController::index
GET  /admin/email/export                  AdminEmailController::export        (read-only CSV)
POST /admin/email/test                    AdminEmailController::test          (rate-limit email_test)
POST /admin/email/suppressions            AdminEmailController::suppress
POST /admin/email/suppressions/remove     AdminEmailController::unsuppress
```

No path collisions across groups. `{id}` compiles to `\d+`; register static `/admin/users` before `/admin/users/{id}`, and `/admin/structure/reorder` before any `/admin/structure/{...}` generic (none exists today).

---

## Admin nav

Each surface adds **exactly one** `<a>` to the subnav in `templates/admin/dashboard.php` (after the existing `Boards & categories` / conditional `API tokens` / `Webhooks` links), flag-gated with `$features[...]` where the surface is flag-gated (mirror the `webhooks` link). Add: `Users` (Group A, unconditional), `Announcements` (Group C, `$features['announcements']`), `Email` (Group D, `$features['email']`). Reorder/archive live inside the existing `/admin/structure` page (no new nav link).

---

## Config additions (`config/config.php`)

- Group C: add a `rate_limits['announce']` policy (e.g. `[5, 3600]` — 5 broadcasts/hour, keyed per-admin) — `RateLimitService` no-ops on unknown names, so the "rate-limited" requirement needs a real policy.
- Group D: add `rate_limits['email_test']` mirroring `webhook_test` (e.g. `[20, 600]`).

---

## Implementation idioms (condensed from the webhooks/structure reference)

- **Controllers** extend `App\Controller\Controller`, no constructor (inherit `__construct(protected Container $container)`); pull collaborators via `$this->container->get(X::class)`. Base helpers: `view($tpl,$data,$status)`, `redirect()`, `redirectWithFlash($to,$msg)` (303), `requireAdmin()`, `currentUser()`. Marshal input via `$request->str()/int()/post()`.
- **Flag gate** (Groups C/D): private `gate()` calling `$this->container->get(FeatureFlags::class)->enabled('<flag>')`, throw `NotFoundException` when off; call right after `requireAdmin()` in every action.
- **Services** are hand-wired lazy singletons in `App::buildContainer()`: `use` the class at the top of `App.php`, add `$c->bind(X::class, fn (Container $c) => new X(...))`. Repos are `new X($c->get(Database::class))`. `$config` is in scope inside `buildContainer()`.
- **Templates** open with the `View` docblock + `$this->layout('layout'); $this->section('title', '…');`. Escape every dynamic value with `$this->e()`/`$e`; cast ints inline. Errors render from `$errors['field']`; inputs repopulate from `$old`. Each admin template hardcodes its own `<nav class="subnav">` with `class="active"` on the current link.
- **Tests** extend `Tests\Support\TestCase`: `actingAs($this->makeAdmin([...]))`, `post('/admin/...', [...])` (CSRF auto-threaded), assert via `assertStatus()`, `assertRedirectContains()`, `assertStringContainsString()`. Flag-dark regression lives in `tests/Integration/Core/AppFeatureFlagTest.php`.
- **Browser evidence**: add a spec in `tests/browser/gate-a.spec.ts`, enable any new flag in `tests/browser/seed.php`, capture named PNGs into `docs/evidence/browser/<viewport>/` via the `shot()` helper. Seeded admin: `admin@retro.test` / `password123`.

---

## Execution order (serializes the shared-file seams)

`App::buildRouter()`, `App::buildContainer()`, and `templates/admin/dashboard.php` are touched by every group — so groups execute **sequentially**, each on its own branch, `composer test` green before the next:

1. **Group A** — per-user admin record (badges + titles)
2. **Group B** — board structure (reorder + archive)
3. **Group C** — announcements (banner + in-app broadcast)
4. **Group D** — email-ops dashboard (+ PHASE_2_STATUS update + ADR 0005)

Final pass after all four: full `composer test`, Playwright evidence run, `SCHEMA.md` §9 changelog touch (no shape change), `docs/PHASE_2_STATUS.md` closeout, request product-owner acceptance.
