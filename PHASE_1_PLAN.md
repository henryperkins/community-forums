# RetroBoards Phase 1 Plan — MVP Backend

**Owner:** Henry  
**Plan type:** Delivery baseline and formal closeout  
**Source of truth:** `DESIGN.md`, with `DECISIONS.md` authoritative where documents conflict. P0/P1/P2 in the source documents are priority tiers, not delivery-phase numbers.  
**Status context:** **Nothing is built yet** — there is no code, schema, or tests on disk; the project is at the planning stage. This plan is the **build-and-acceptance checklist** for Phase 1: follow it in sequence to implement the MVP, then use its definition of done and evidence policy to accept the result.

## 1. Phase objective

Ship a secure, server-rendered forum core that lets a real user:

1. Open a fresh installation and complete first-run setup.
2. Register, log in, and log out.
3. Read public boards and threads as either a guest or authenticated user.
4. Create a thread and post a reply.
5. Edit or soft-delete their own content.
6. Manage basic account details and password.
7. View a basic public profile.

An administrator must also be able to configure the initial community, manage boards and categories, perform basic inline moderation, and rely on audited account-state write gates.

## 2. Definition of done

Phase 1 is accepted only when all of the following are true:

- Every core read and write journey works with server-rendered HTML and without JavaScript.
- A fresh database can be migrated and initialized through `/setup` without manual SQL.
- Registration, login, logout, sessions, CSRF protection, and password handling pass automated and browser tests.
- Guests can read public content but cannot write.
- Authenticated users can create threads, reply, edit their own content, and soft-delete their own content.
- Stored post content is rendered safely; raw user HTML and executable content cannot bypass sanitization.
- Locked threads reject replies.
- Suspended and banned accounts are blocked from every write path, including through stale sessions.
- Administrators can manage the community name, categories, and boards through `/admin`.
- Administrators can pin or unpin threads, lock or unlock threads, and soft-delete any post.
- Every moderation action is written to `moderation_log`.
- The application exposes a working `/healthz` check with database status.
- The full automated suite passes, and every UI-visible flow has browser evidence.
- No unresolved critical or high-severity security or data-integrity defect remains.

## 3. Scope boundary

### In scope

- Vanilla PHP 8.x front controller and micro-router.
- MySQL/MariaDB schema, migrations, and seed data through PDO prepared statements.
- Server-rendered templates using the selected three-pane hybrid shell.
- Public board and thread reading, including pagination.
- Email/password registration and authentication.
- DB-backed sessions, CSRF protection, logout, and password change.
- Baseline auth abuse protection: rate limiting on login and registration, plus basic posting throttles (P0 security per DESIGN §11). _(Throttle/counter state is held in a fast process or shared store — e.g. APCu, or a file/cache backend — behind a small limiter interface; it is **not a DB table** in Phase 1. The configurable, MySQL-backed limiter is Phase 3 / P3-05. See PHASE_1_MIGRATIONS §7.)_
- Baseline security headers: HSTS, a starter Content-Security-Policy, `X-Content-Type-Options`, and `Referrer-Policy` (P0 per DESIGN §11).
- Thread and post creation, editing, soft deletion, sanitization, and authorization.
- Basic account settings: display name, bio, and location.
- Basic public profile: username/display name, monogram avatars (computed from the username; no avatar column), bio, location, join date, post count, and reputation field. _(Clarified 2026-06-26: monogram avatars are the assigned Phase-1 avatar deliverable — DECISIONS §5 #4, PHASE_1_MIGRATIONS; uploads/Gravatar come in later phases.)_
- First-run setup wizard for the first administrator, community name, starter categories, and starter boards.
- Minimal admin console for site naming and board/category management.
- Hidden/private-board read gates as specified for the admin slice.
- Inline administrator moderation: pin, lock, and soft-delete.
- Moderation audit logging.
- Suspended/banned-account write gates.
- Health check, automated tests, HTTP smoke tests, and browser verification.

### Explicitly deferred

The following must not delay Phase 1 acceptance:

- Reactions, stars, subscriptions, and unread tracking.
- Notifications, email fan-out, daily digests, and `@mentions`.
- MySQL FULLTEXT search.
- Direct messages.
- Reports queue, complete user moderation, and per-board moderator workflows.
- OAuth providers and account linking.
- Rich composer enhancements, uploads, embeds, tables, and server-synced drafts.
- Following feeds, badges, leaderboards, accepted answers, and the broader reputation system.
- Presence, advanced preferences, 2FA, passkeys, plugin ecosystem, PWA, multi-community support, and retro theme toggle.
- Full board/category drag-and-drop reorder unless a release-blocking defect depends on it.
- The configurable/tunable anti-spam + rate-limit service (per-action limits, new-user throttles, link/word filters, spam scoring) — deferred to Phase 3 (P3-05). Phase 1 ships only the baseline auth/posting limits listed in scope above.
- Email-verification-gated first post — lands in Phase 2 alongside email verification and fan-out (Phase 1 defers all email). Until then, first-post spam is mitigated by the Phase 1 baseline rate limits and inline moderation.

> **Decision (2026-06-26):** DESIGN §11 marks rate limiting, the first-post email gate, and security headers as **P0**, but no phase previously owned them for the MVP (the configurable limiter is Phase 3 / P3-05, and the email gate needs Phase 2 email). To avoid shipping public auth with no brute-force protection, *baseline* login/registration rate limiting and security headers are pulled into Phase 1 (see P1-03). The *configurable* anti-spam service (P3-05) and the *email-verification* first-post gate (Phase 2) stay deferred because they depend on infrastructure not built in Phase 1 (a tunable limiter; email delivery). Note: these baseline items are **P0 and part of Phase 1's build scope** (like everything else in this phase — nothing is built yet). Flip any of these if you'd rather sequence differently.

## 4. Delivery workstreams

| ID | Workstream | Deliverables | Acceptance evidence | Dependency | Status |
|---|---|---|---|---|---|
| P1-01 | Foundation | Front controller, router, environment config, PDO connection, error handling, migrations, seed command, test harness, `/healthz` | Clean install; migration/seed tests; `/healthz` returns HTTP 200 and database `ok`; full test command runs | None | Planned — build & verify |
| P1-02 | Read path and shell | DB-backed category/board index, thread list, paginated thread view, hybrid templates, guest read access, join bar | HTTP and browser smoke on `/`, `/c/{slug}`, and `/t/{id}-{slug}` with JS disabled | P1-01 | Planned — build & verify |
| P1-03 | Authentication and request security | Register, login, logout, password hashing, session persistence, CSRF tokens, auth middleware, baseline login/registration rate limiting + posting throttle, security headers (HSTS/CSP/`X-Content-Type-Options`/`Referrer-Policy`) | Auth integration tests; invalid credentials fail safely; CSRF failure rejects state change; session cookie settings reviewed; repeated-failure rate-limit test; response-header assertions | P1-01 | **Planned — build & verify** (incl. baseline rate limiting + security headers, P0) |
| P1-04 | Core posting | Create thread, reply, edit own post, soft-delete own post, validation, PRG redirects, sanitized Markdown render | Posting/controller/service/repository tests; browser proof for create, reply, edit, and delete; XSS payload regression test | P1-02, P1-03 | Planned — build & verify |
| P1-05 | Basic account and profile | Account settings, password change, public profile | User-settings integration tests; profile render tests; unauthorized settings access blocked | P1-03 | Planned — build & verify |
| P1-06 | First-run setup | Fresh-install route gate, first administrator creation, community name, starter categories/boards, automatic sign-in | Setup service and route integration tests; smoke before and after initialization; setup unavailable after completion | P1-01, P1-03 | Planned — build & verify |
| P1-07 | Minimal admin | Admin-only `/admin`, site naming, category/board create/edit/hide/delete-empty, baseline audit feed | Admin integration tests; role/access matrix checks; browser proof as admin and non-admin | P1-06 | Planned — build & verify |
| P1-08 | Moderation and account-state gates | Pin/unpin, lock/unlock, soft-delete any post, moderation log, suspended/banned write denial | Moderation, write-gate, and private-board tests; direct POST attempts return the expected denial; every action audited | P1-04, P1-07 | Planned — build & verify |
| P1-09 | Hardening and release | Security review, no-JS regression, responsive/browser checks, documentation, release notes, rollback procedure | Full suite green; smoke matrix complete; no critical/high defects; evidence index attached to release | All | Closeout required |

> **P1-08 — the write-gate trigger state is fixture-set in Phase 1 (2026-06-26):** Phase 1 *builds and verifies* the suspended/banned write-gate, but it ships **no in-app action that sets** a user to suspended or banned. The user-facing warn/suspend/ban tooling, the user-management screen, and the `bans` history table are deliberately Phase 2 (`PHASE_2_PLAN` P2-08; ADMIN §11 keeps full user moderation there). In Phase 1 the `users.status` / `suspended_until` trigger state is therefore set **out-of-band** — via the first-run/seed path, directly in the database, or in test fixtures — and the gate is exercised against those fixture-set states. Phase 1 delivers the gate, not the in-app control that flips the state; the suspended/banned scenarios in §6 assume a fixture-set state, not a Phase-1 admin action.

## 5. Recommended execution sequence

### Milestone 0 — Scope lock and evidence map

- Freeze the Phase 1 boundary defined above.
- Create a traceability matrix from each definition-of-done item to one or more tests.
- Run the existing full suite and record a baseline.
- Classify failures as release blockers, non-blocking defects, or Phase 2 work.
- Confirm that documentation and code agree on routes, permissions, and data fields.

**Exit gate:** Every Phase 1 requirement has an owner, status, and evidence location; no Phase 2 feature is mislabeled as a release blocker.

### Milestone 1 — Installation, read path, and security baseline

- Verify empty-database migration and optional seed flows.
- Verify `/setup` gating on an uninitialized installation.
- Verify guest reading for public boards and threads.
- Review PDO prepared-statement use, output escaping, session cookie flags, CSRF enforcement, and error disclosure.
- Verify `/healthz` behavior for both healthy and unavailable database states.

**Exit gate:** A new operator can install and browse the application safely without editing the database manually.

### Milestone 2 — Member journey

- Verify registration, login, logout, and session persistence.
- Verify create-thread and reply flows with and without JavaScript.
- Verify editing and soft deletion are owner-scoped.
- Verify post validation and sanitization against XSS and malformed markup.
- Verify account settings, password change, and public profile.

**Exit gate:** A newly registered user can complete the full read-to-first-post journey without assistance.

### Milestone 3 — Operator and moderation journey

- Verify the first administrator can finish setup and enter `/admin`.
- Verify category and board creation, editing, visibility changes, slug redirects, and empty-item deletion.
- Verify non-admin users cannot reach or invoke admin actions.
- Verify pin, lock, and soft-delete actions from the thread view.
- Verify locked-thread enforcement and complete moderation audit rows.
- Verify suspended and banned accounts cannot bypass write gates through direct requests or stale sessions.

**Exit gate:** The operator can launch and safely govern a basic community without direct database access.

### Milestone 4 — Release candidate and acceptance

- Run the complete automated suite on a clean test database.
- Execute the critical browser and HTTP smoke matrix below.
- Test the supported responsive breakpoints and keyboard-only navigation for core flows.
- Verify the server-rendered paths with JavaScript disabled.
- Resolve all release blockers and document accepted low-risk defects.
- Update README status, changelog, deployment steps, and completion evidence.
- Tag a release candidate, perform deployment rehearsal, and validate rollback.

**Exit gate:** Product owner signs off on the definition of done and the evidence index is complete.

## 6. Critical acceptance scenarios

| Scenario | Expected result |
|---|---|
| Fresh install | Normal routes redirect to `/setup`; setup creates the first admin, site name, and starter boards; the admin is signed in; setup cannot be rerun casually |
| Guest browse | Guest can open public board and thread pages, sees a join/login prompt instead of a writable composer, and receives no private content |
| Registration and login | Valid registration creates an account; valid credentials log in; invalid credentials fail without leaking account details; logout destroys access |
| Create a thread | Authenticated, permitted user creates a titled thread and first post; counters and timestamps remain consistent |
| Reply | Authenticated, permitted user replies; locked threads reject the same request |
| Edit/delete own content | Owner can edit and soft-delete within the implemented rules; another normal user cannot |
| Sanitization | Script tags, event handlers, dangerous URLs, and raw HTML do not execute in rendered posts |
| Basic account/profile | User updates display name, bio, and location; changes password; profile renders public fields correctly |
| Admin access | Admin can enter `/admin`; normal user and guest cannot invoke admin routes or actions |
| Board/category management | Admin can create and edit items, hide a board, and delete only eligible empty items; protected redirects do not reveal private boards |
| Inline moderation | Admin can pin/unpin, lock/unlock, and soft-delete any post; each action produces a moderation-log record |
| Suspended account | User remains able to authenticate and read but every write endpoint returns the designed denial |
| Banned/stale session | Existing session cannot write once the account's stored state is banned (set out-of-band in Phase 1 — see the P1-08 scope note; no in-app ban action ships until Phase 2) |
| No-JS operation | Registration, login, browsing, posting, editing, deleting, setup, and admin forms retain a functional server-rendered path |
| Health and failure mode | `/healthz` accurately distinguishes application/database health without exposing secrets |

## 7. Test and evidence policy

Each completed slice must include all applicable evidence:

1. **Automated tests:** unit and integration coverage for services, repositories, controllers, routing, permissions, and rendering.
2. **HTTP smoke tests:** status code, redirect, authorization, and response-content checks against a running application.
3. **Browser verification:** required for every user-visible flow and responsive change.
4. **Security regressions:** CSRF, unauthorized direct POST, XSS payload, session invalidation, and hidden/private-board access tests.
5. **Clean-install proof:** migration, setup, and seed paths on an empty database.

The release evidence index should reference at minimum:

- `tests/Integration/Controller/AuthControllerTest.php`
- `tests/Integration/Core/AppTest.php`
- `tests/Integration/Core/AppPostingTest.php`
- `tests/Integration/Core/AppUserSettingsTest.php`
- `tests/Integration/Core/AppSetupTest.php`
- `tests/Integration/Service/SetupServiceTest.php`
- `tests/Integration/Core/AppAdminTest.php`
- `tests/Integration/Core/AppModerationTest.php`
- `tests/Integration/Core/AppWriteGateTest.php`
- `tests/Integration/Core/AppPrivateBoardAccessTest.php`
- Layout/view tests, migration/seeder tests, `/healthz` smoke, and browser evidence for the corresponding UI paths

## 8. Risks and controls

| Risk | Control |
|---|---|
| Phase 2 scope leaks into MVP | Enforce the explicit deferral list and require product-owner approval for any scope change |
| Documentation says “Live” without executable proof | Require the completion-evidence rule before acceptance |
| Authorization is checked only in the UI | Test every privileged action through direct HTTP requests at the controller/service boundary |
| Sanitization or Markdown rendering permits XSS | Maintain an allowlist renderer and permanent malicious-payload regression tests |
| Account-state restrictions miss an endpoint | Centralize write authorization and exercise a route inventory for suspended/banned users |
| Setup or migrations work only on a seeded developer database | Run clean-database installation in CI and before release |
| Admin slug or visibility changes leak private content | Apply the same read gate before redirects and include private-board tests |
| JavaScript becomes required accidentally | Include a no-JS browser pass in every release candidate |
| Denormalized counters drift | Update writes transactionally and assert counters in integration tests |

## 9. Release checklist

- [ ] Scope and deferrals approved.
- [ ] Traceability matrix complete.
- [ ] Empty-database migrations pass.
- [ ] First-run setup passes.
- [ ] Full automated suite passes.
- [ ] `/healthz` healthy/unhealthy behavior verified.
- [ ] Guest, member, suspended, banned, moderator-equivalent/admin permission matrix verified.
- [ ] Core paths pass with JavaScript disabled.
- [ ] Browser smoke completed at desktop and mobile widths.
- [ ] CSRF, XSS, direct-request authorization, and session regressions pass.
- [ ] No critical or high-severity defects remain.
- [ ] Deployment backup and rollback procedure rehearsed.
- [ ] README, changelog, and evidence references updated.
- [ ] Product-owner acceptance recorded.

## 10. Phase handoff

After Phase 1 acceptance, the next prioritized slice is Phase 2 community essentials: persisted reactions/stars/subscriptions and unread state, notifications and mentions, search, direct messages, broader moderation, and richer profile/reputation behavior. None of these should be partially enabled in the Phase 1 release unless it is complete, permission-safe, tested, and intentionally re-scoped.

## 11. Source references

- `README.md` — current status, Phase 1 roadmap, setup instructions, and completion evidence.
- `DESIGN.md` §§2, 6, 9–13 — product goals, feature catalog, architecture, permissions, non-functional requirements, success metrics, and Phase 1 definition of done.
- `DECISIONS.md` — authoritative stack, session, role, rendering, hosting, and deferral decisions.
- `ADMIN.md` §§1.2, 3, 9–11 — account states (§1.2), moderation workflows/actions (§3) and the audit log (§3.6), plus first-run setup, the minimal admin console, and the data-model/roadmap deltas (§§9–11).
- `USER.md` §8 — Phase 1 account/profile slice and deferred account features.
- `COMPOSER.md` §17 — composer phasing and Phase 1/P2 boundary.
- `COMMUNITY.md` §14 — community-layer features intentionally outside the core MVP.
