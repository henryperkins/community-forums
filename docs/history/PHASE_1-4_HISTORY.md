# RetroBoards — Phase 1-4 History

This document consolidates the completed-phase status and completion records for Phases 1-4 — previously maintained as separate files (`docs/PHASE_1_COMPLETION.md`, `docs/PHASE_2_STATUS.md`, `docs/PHASE_3_STATUS.md`, `PHASE_4_STATUS.md`) — into a single history record. The Phase 1 migration manifest is retained as a standalone sibling reference at [`PHASE_1_MIGRATIONS.md`](PHASE_1_MIGRATIONS.md). This record is retained for traceability and as DESIGN §13 completion evidence. Each source's original content is reproduced faithfully under its section below.

## Phase 1 completion

### RetroBoards — Phase 1 Completion & Evidence Index

**Status:** Phase 1 (MVP backend) implemented and verified.
**Date:** 2026-06-26 · **Owner:** Henry (lakefrontdigital.io)

This document is the release-evidence index required by `PHASE_1_PLAN.md` §7. It
maps every definition-of-done item to where it is satisfied and tested, records
the automated/HTTP evidence, and documents the rollback procedure.

### How to verify locally

```bash
composer install
cp .env.example .env          # set DB_*, then: php bin/console key:generate
php bin/console migrate       # 10 migrations on an empty DB
composer test                 # full PHPUnit suite (unit + integration)
```

The suite provisions nothing destructive against your dev DB — integration tests
run against `retroboards_test` (override with `DB_TEST_DATABASE`) and roll back
each test in a transaction.

### Architecture at a glance

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

### Definition-of-done traceability

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

### Critical acceptance scenarios (`PHASE_1_PLAN.md` §6)

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

### Automated evidence

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

### HTTP smoke matrix

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

### Security review

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

### Rollback procedure

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

### Deferred to later phases

Per `PHASE_1_PLAN.md` §3 and `DECISIONS.md`: reactions/stars/subscriptions/unread,
notifications/email/digests/mentions, FULLTEXT search, DMs, reports queue and the
in-app warn/suspend/ban tooling (+`bans` table), per-board moderators, OAuth, the
rich composer/uploads, IP capture (`posts.ip`/`sessions.ip`), and the configurable
anti-spam/rate-limit service. The Phase-1 `verifications` table ships but is
dormant until the Phase-2 email worker.

## Phase 2 status

### RetroBoards — Phase 2 Implementation Status & Evidence Index

**Status:** M0–M6 + Gate A/B engineering closeout complete; final product-owner acceptance artifact pending Henry signature · **Date:** 2026-06-30 · **Owner:** Henry (lakefrontdigital.io)

Living evidence index for `PHASE_2_PLAN.md` (Community Essentials). Tracks which
milestone/workstream is implemented, where, and how it is verified. Entry gate
(green Phase 1 baseline) confirmed before any Phase 2 migration was written.

### How to verify locally

```bash
composer install
php bin/console migrate        # Phase 1 (0001-0010) + Phase 2 (0011-0041)
composer test                  # full PHPUnit suite (current closeout target: 803 tests)
php bin/console repair         # reconcile all denormalised counters + reputation
php bin/console verify:upgrade # rehearse a Phase-1→Phase-2 upgrade (scratch DB)
```

Integration tests run against `retroboards_test` (override `DB_TEST_DATABASE`),
fresh-migrated by the bootstrap and rolled back per-test in a transaction. Every
integration test drives the real kernel server-side over form POST → redirect, so
the suite is itself the no-JavaScript proof for the Gate A write paths.

### Milestone status

| Milestone | Workstreams | Status |
|---|---|---|
| M0 — Foundation | P2-00 | ✅ Done |
| M1 — Inbox state + reactions/reputation | P2-01, P2-02 | ✅ Done |
| M2 — Notifications, mentions, email | P2-03, P2-04, P2-05 | ✅ Done (core) |
| M3 — Search + DMs | P2-06, P2-07 | ✅ Done |
| M4 — Scoped moderation + operator controls | P2-08 | ✅ Done (core) |
| M5 — Community identity + account expansion | P2-09, P2-10, P2-11 | ✅ Done |
| M6 — Hardening + phase close | P2-12 | ✅ Done (acceptance pending) |

### M0 — Foundation (P2-00) ✅

**Schema (additive migrations `0011`–`0038`).** All Phase 2 tables/columns from
`SCHEMA.md`, applied in FK-dependency order matching `PHASE_2_PLAN.md` §7.1
migration groups:

- **Group 1 (core state):** `0011` users Phase-2 columns (title, signature,
  website, pronouns, avatar_source, profile_visibility, allow_dms, show_presence,
  timezone, digest_hour, last_daily_digest_at, last_seen_at + idx); `0012` boards
  (edit_window_seconds, is_archived); `0013` threads (accepted_answer_post_id +
  `ft_threads_title`); `0014` posts (ip + `ft_posts_body`); `0015` sessions (ip);
  `0016` board_moderators; `0017` thread_user; `0018` blocks.
- **Group 2 (engagement/notification):** `0019` reactions; `0020` subscriptions;
  `0021` notifications; `0022` email_suppressions; `0023` email_deliveries
  (with `uq_deliv_idem` idempotency key).
- **Group 3 (DMs):** `0024` conversations; `0025` conversation_participants;
  `0026` dm_messages.
- **Group 4 (moderation/access):** `0027` reports; `0028` bans; `0029` warnings;
  `0030` user_notes; `0031` board_members.
- **Group 5 (member controls):** `0032` oauth_identities; `0033` user_preferences;
  `0034` user_board_prefs; `0035` username_history.
- **Group 6 (community):** `0036` follows; `0037` badges; `0038` user_badges.

**Shared services.**
- `src/Core/FeatureFlags.php` — per-subsystem flags backed by the `features`
  setting (code defaults ON; operators disable per flag for a dark/staged
  rollout). Exposed to templates as `$features`.
- `src/Repository/BlockRepository.php` — block list + the canonical
  `blockedEitherWay()` interaction predicate (used later by mentions/DMs/fan-out).
- `src/Service/RepairService.php` + `bin/console repair*` — idempotent counter &
  reputation reconciliation.
- `bin/console engagement:cutover [UTC]` — records the unread cutover timestamp
  (`settings.engagement_cutover_at`); content before it starts read.

**Evidence.**
- Clean-install migration + Phase 1 regression: `composer test` → all green.
- Populated Phase-1 → Phase-2 upgrade (no data loss, defaults on existing rows,
  FULLTEXT works, idempotency key enforced): verified via a two-pass migration
  harness (`tests/Support` upgrade scenario; see `AppPhase2FoundationTest` for
  the service-level evidence).
- `tests/Integration/Core/AppPhase2FoundationTest.php` — flags, block predicate,
  counter/reputation repair (incl. self-reaction exclusion).

### M1 — Inbox state + reactions/reputation (P2-01, P2-02) ✅

- `src/Repository/ThreadUserRepository.php` — read position + star, unread
  derivation (post-id watermark + launch cutover), and the read-gated Inbox
  queries (`inbox`/`countInbox`/`unreadCount`/`unreadFlags`).
- `src/Repository/ReactionRepository.php` + `src/Service/ReactionService.php` —
  idempotent emoji toggle (unique key), grouped counts (batched, no N+1),
  transactional reputation update excluding self-reactions, write-gated + read-gated.
- `UserRepository::incrementReputation` (clamped ≥ 0); `PostingService`
  delete/restore now reverses/re-adds the post's reaction reputation in the
  same transaction.
- Controllers/routes: `EngagementController` (`POST /posts/{id}/react`,
  `POST /t/{id}/star` — JSON + no-JS PRG), `InboxController` (`GET /inbox`).
  `ThreadController` advances read position on view; `BoardController` annotates
  unread flags.
- UI: reaction bar + star button + unread dots + inbox tabs (templates), plus
  CSS and a CSRF-safe progressive-enhancement reaction toggle in `app.js`.
- **Evidence:** `tests/Integration/Core/AppReactionTest.php` (7),
  `tests/Integration/Core/AppThreadStateTest.php` (9) — read isolation, star
  idempotency/per-user, unread+cutover, inbox filters, **private-board
  exclusion**, self-reaction = 0 rep, delete adjusts rep, write gate, render.
  Full suite: **100 tests / 342 assertions** green.
- **Deferred to natural milestones:** cosmetic reputation/post-count titles →
  M5 (profile display); reaction→author notification → M2 (needs the
  notification domain).

### M2 — Notifications, mentions, email (P2-03/P2-04/P2-05) ✅ core

- **Subscriptions** (`SubscriptionRepository`): in-app/email channels +
  instant/daily/off; `subscribersForThread` resolves **thread-over-board
  precedence**; thread subscribe control on the thread page
  (`SubscriptionController`, `POST /t/{id}/subscribe`, `POST /b/{id}/subscribe`).
- **In-app notifications** (`NotificationRepository`, `NotificationController`):
  bell short-poll JSON (`/notifications/bell`), list page, mark-read / mark-all /
  clear; **deep links re-check the board read gate at click time**.
- **Fan-out** (`NotificationService`, run inside the post write transaction):
  excludes the actor, blocked pairs (either direction), and recipients without
  board access (re-checked); idempotent reaction notice; auto-subscribes a
  thread author to their own thread.
- **@mentions** (`MentionParser` + service): parsed from canonical Markdown,
  cap 10, deduped, code-span-aware, block-aware; **edits notify only newly added
  mentions**.
- **Email** (`App\Mail\Mailer` + `ArrayMailer`/`SendmailMailer`,
  `EmailDeliveryRepository`, `EmailSuppressionRepository`): durable outbox with
  the `post:user` idempotency key; `NotificationEmailWorker` (instant,
  at-most-once, suppression, **fail-closed when unconfigured**, failure
  recording); `DailyDigestWorker` (timezone-aware, watermarked, never empty/
  duplicated). `bin/console worker:email` / `worker:digest`.
- **Login-free signed unsubscribe** (`UnsubscribeController` + `SignedToken`
  HMAC): GET confirm (prefetch-safe) → POST suppress; re-subscribe recovery.
- **Evidence:** `AppNotificationTest` (6), `AppMentionTest` (5),
  `MentionParserTest` (5), `NotificationEmailWorkerTest` (4),
  `DailyDigestWorkerTest` (3), `AppUnsubscribeTest` (4). Full suite **127 tests**.
- **Deferred within M2** (smaller, no blocker): board-page subscribe control,
  a subscriptions settings list, and admin announcements/broadcast
  (schema home already exists — `notifications.type='announcement'`,
  `settings.site_announcement`, `email_deliveries.kind='system'`).

### M3 — Search + direct messages (P2-06, P2-07) ✅

- **Search** (`SearchService` interface + `MysqlSearchService`): FULLTEXT over
  thread titles + post bodies; **read gate = isListed semantics** (guest →
  public; member → + private boards they belong to; admin → all; hidden excluded);
  deleted/pending excluded; HTML-escaped snippets from canonical Markdown.
  `SearchController` (`GET /search`), results page, topbar search box;
  `search` feature-flag gated.
- **Direct messages** (`ConversationRepository`, `DmMessageRepository`,
  `DirectMessageService`): one-to-one conversations, send/reply, per-participant
  unread, in-app `dm` notifications; **eligibility** = write gate + blocks (either
  direction) + recipient `allow_dms` + **new-user throttle** (start only) +
  per-sender rate limit. **DM reporting** stores `reports.dm_message_id`
  (migration `0039`, SCHEMA §7 #16), participant-only, deduped — staff see only
  the reported message, no DM browser. `ConversationController` + `dm/*`
  templates + Messages topbar link; `dms` feature-flag gated.
- **Evidence:** `tests/Integration/Core/AppSearchTest.php` (5 — visibility gate,
  deleted exclusion, snippet XSS, route; runs on committed fixtures because
  InnoDB FULLTEXT doesn't index uncommitted rows) and
  `tests/Integration/Core/AppDirectMessageTest.php` (8 — exchange, block,
  allow_dms=none, suspended, new-user throttle, report privacy/dedupe, reply,
  read-on-view). Full suite: **140 tests / 434 assertions**.
- **Deferred within M3** (small): "Message" button on profiles; the full reports
  queue/triage UI for DM reports is M4 (P2-08) — submission + storage land here.

### M4 — Scoped moderation + operator controls (P2-08) ✅ core

- **Capability/scope** (`BoardModeratorRepository`, `ModerationService::canModerate`):
  a user moderates a board iff admin OR assigned board moderator. Content actions
  (pin/lock/**delete/restore**/**move**) are scope-checked; a move requires the
  capability on BOTH source and destination and updates both boards' counters +
  last-post atomically with an audit row. Existing admin moderation still works.
- **Reports queue** (`ReportRepository`, `ReportService`, `ReportController`):
  post reporting (`POST /posts/{id}/report`) with one-open-report dedupe + opt-in
  `notify_reporter`; board-scoped queue (`/mod/reports`, claim/resolve/dismiss);
  new-report **staff alerts** and **reporter outcome-notifications**;
  `moderation_queue` flag-gated. (DM report submission shipped in M3.)
- **Private-board membership** (`BoardMemberRepository`): `BoardPolicy` now takes
  an `$isMember` flag; board/thread reads, the sidebar nav, the **inbox** (OR-in
  `board_members`), **search**, **notification fan-out**, and posting all honor
  membership. Added member gains read+post; removal revokes immediately.
- **User moderation** (`UserModerationService`, `UserModerationController`):
  warn/note (staff) and suspend/ban/lift (admin) → `bans` system-of-record +
  `users.status` fast-path (WriteGate-enforced) + immutable audit; cannot target
  self or another admin.
- **Ban-evasion signals:** `posts.ip` + `sessions.ip` captured (packed
  `inet_pton`) on post/login; Admin-only display + 90-day purge are a Phase 3 seam.
- **Evidence:** `AppModeratorScopeTest` (5), `AppReportQueueTest` (4),
  `AppPrivateBoardMembershipTest` (3), `AppUserModerationTest` (5). Full suite:
  **157 tests / 494 assertions**.
- **Operator UI shipped (post-M4 follow-up):** the admin board-edit page
  (`/admin/boards/{id}/edit`) now assigns/removes board **moderators** and
  private/hidden-board **members** — admin-gated, CSRF-protected, validated
  (unknown/`@`-prefixed/blank username, admin-as-moderator, duplicate), and audited
  (`assign_moderator`/`unassign_moderator`/`add_member`/`remove_member`, written
  exactly-once). `AdminService` + `AdminController` + `templates/admin/board_edit.php`;
  evidence `AppAdminBoardRosterTest` (16).
- **Still deferred within M4** (operator-UI polish, not security): aging-report
  alerts and a richer DM-report triage view.

### Adversarial review (M0+M1) — applied

A multi-agent review of the M0+M1 diff produced 6 confirmed findings, **all
fixed**: hidden-board leak in inbox/unread listing (now public-only +
`board_members` in M4); engagement write/inbox routes now feature-flag gated;
`star` now runs the WriteGate; `safeReturn` open-redirect (backslash) closed;
`dm_messages.body_html` and `reports.notify_reporter` reconciled into SCHEMA.md
(§7 #14/#15). The same hardening patterns were applied proactively to M2.

#### Deviations / decisions recorded during build

- **Feature-flag default = ON** (operators opt into a dark deploy by setting
  `features`), so a fresh install is fully functional and tests exercise every
  path. The flag *mechanism* required by the plan exists; only the default
  posture differs from "deploy dark."
- **`dm_messages.body_html`** added (nullable) to cache the sanitised DM render,
  mirroring `posts.body_html` and the unified composer. Additive vs SCHEMA.
- **`reports.notify_reporter`** added (the committed reporter outcome-notification,
  PHASE_2 §3 / ADMIN §3.1). DM reporting (post-only `reports` today) will get its
  own additive migration in its milestone.
- A few extra secondary indexes (`idx_blocks_blocked`, `idx_reports_post`,
  `idx_bans_board`, `idx_cp_user`, `idx_bm_user`, `fk_notif_user`) — additive,
  consistent with SCHEMA's "sensible starting points" note.

### M5 — Community identity + account expansion (P2-09/10/11) ✅

- **P2-09 community identity.** `follows` (block-aware) + new-follower notification
  (`FollowService`, `FollowRepository`, `FollowController`); query-time **Following
  feed** (`FeedService`, `/feed`) gated to public + member-private boards, excluding
  deleted/blocked; fixed **badge** catalogue seeded idempotently (migration `0040`,
  `BadgeRepository`/`BadgeService`, auto-milestone + admin manual + revoke);
  accepted/**"solved" answers** (`SolvedAnswerService`/`SolvedController`): OP or
  board moderator, +5 reputation to the answerer with **self-answer exclusion**,
  Problem Solver badge, in-app + email notification, audit row — one transaction;
  all-time **leaderboard** (`/leaderboard`, opt-out + banned excluded); cosmetic
  **titles** (`TitleService`, reputation thresholds + admin override). Profile
  revamp: counts, badges, title, presence, Follow/Message/Block, renamed-handle
  301 redirects (`username_history`).
- **P2-10 member controls + account expansion.** `/settings/{privacy,preferences,
  notifications,blocks,boards}` (`SettingsController`, `PreferenceService`,
  `UserPreferenceRepository`, `UserBoardPrefRepository`) — server-enforced
  pagination, muted boards leave the sidebar, leaderboard opt-out; **active
  sessions/devices** (list, revoke one user-scoped, log out everywhere else);
  **OAuth** (`OAuthService` + `App\Service\OAuth\*`: Google/GitHub/Apple,
  `ProviderRegistry`) with `state` + PKCE + nonce, a signed state cookie, the
  account-resolution tree (returning / link / **verified-email collision that
  never auto-merges** / banned-refusal / new-signup with avatar import) and
  **last-login-method protection** on unlink; OAuth-only accounts can set a
  password.
- **P2-11 presence.** Throttled `last_seen_at` heartbeat in the kernel; privacy-safe
  roster (`/presence`, `PresenceController`) that never exposes a hidden / stale /
  self / blocked member; sidebar widget + short-poll; focus-visible / 44px tap
  target / reduced-motion CSS.
- **Evidence:** `AppFollowFeedTest` (5), `AppBadgeSolvedTest` (13),
  `AppLeaderboardTest` (1), `AppCommunityProfileTest` (6), `AppUserPreferencesTest`
  (7), `AppSessionManagementTest` (4), `AppOAuthTest` (12), `AppPresenceTest` (4).

### Adversarial review (M5) — applied

A 4-dimension multi-agent review (authz/privacy, OAuth security, reputation
integrity, injection/XSS/CSRF) with skeptical per-finding verification produced
**3 confirmed findings, all fixed + regression-tested**:

1. OAuth state cookie was `SameSite=Lax`, so Apple's `form_post` (cross-site POST)
   callback dropped it and Apple sign-in failed closed → now `SameSite=None; Secure`
   when secure, `Lax` fallback for non-secure local dev.
2. Soft-deleting an accepted-answer post left the +5 solved bonus and a dangling
   `accepted_answer_post_id` (runtime ↔ `repair` drift) → `applyDeletionCounters`
   now clears it and reverses the bonus (author ≠ OP).
3. `solvedAnswerCount` counted self-accepts, allowing badge self-farming → now
   excludes self-answers, matching the reputation rule.

Two further reports were correctly **refuted** (board-pref rows for inaccessible
boards are re-gated downstream by `BoardPolicy::isListed`; the unused OIDC nonce is
covered by state + PKCE).

### M6 — Hardening + phase close (P2-12) ✅

- **Clean-install migration.** `migrate:fresh` applies all 41 migrations; proven on
  every test run (the bootstrap fresh-migrates `retroboards_test`).
- **Phase-1 → Phase-2 upgrade rehearsal.** `php bin/console verify:upgrade`
  (`App\Support\UpgradeRehearsal`) builds the Phase 1 schema (0001–0010), seeds
  representative data, applies the Phase 2 migrations, and asserts no data loss:
  **17/17 checks PASS** — all Phase 1 row counts + sample values preserved, 23 new
  tables and 11 new columns present, **every Phase 1 column retained** (an
  exhaustive 90-column before/after `information_schema` diff), 11 badges seeded.
- **Feature-flag rollback.** `AppFeatureFlagTest` (4): disabling any Phase 2 flag
  (`engagement`, `notifications`, `search`, `dms`, `community`, `moderation_queue`,
  `oauth`, `presence`) 404s its routes while the core forum still serves; re-enabling
  restores it — no data change.
- **Worker / queue operations.** `NotificationEmailWorkerTest` (5): at-most-once per
  `(post, recipient)`, suppression, **fail-closed transport** (rows stay queued),
  failure marking, and **bounded backlog drain that resumes without loss**;
  `DailyDigestWorkerTest` covers timezone/watermark/no-empty-send. Failed rows are
  not auto-retried (operator replay — see runbook); `EmailDeliveryRepository::
  statusCounts()` exposes queue depth.
- **Query / index review.** Added migration `0041` (`idx_users_reputation`): the
  leaderboard went from `type=ALL` (full scan + filesort) to a **filesort-free**
  `type=range` index scan — its `reputation DESC, id DESC` order is served directly
  by the index (InnoDB appends the PK `id`), verified by EXPLAIN (`Using where`, no
  `Using filesort`). Presence uses `idx_users_last_seen`;
  feed uses `idx_posts_author`; follows, notifications, and the email queue are
  covered by existing composite indexes. No N+1: feed/leaderboard/presence/follows
  are single bounded queries.
- **No-JS / responsive.** Every Gate A action has a server-rendered POST→redirect
  path exercised by the (JS-free) integration suite. Mobile widths get ≥44px tap
  targets, a `prefers-reduced-motion` guard, and focus-visible outlines. **Browser
  capture at desktop (1280×800) + mobile (390×844) widths is now done** — a Playwright
  harness (`tests/browser/`) drives the real app in Chromium and captures 28 named
  surfaces at both widths (`docs/evidence/browser/{desktop,mobile}/`), regenerated in
  CI by `.github/workflows/browser-evidence.yml` (on pushes touching the app or
  harness, and on demand) against an ephemeral MariaDB service.

### Gate A follow-ups — auth flows + masked-anonymous posting (PR #5 / #6)

Three security-sensitive Gate A flows shipped after the initial M0–M6 build and
are tracked here for completeness:

- **Email verification** (`EmailVerificationService`, `AuthController`): single-use,
  expiring tokens consumed on `GET /verify`; `POST /verify/resend` capped at 3/hour
  (`AuthController::VERIFY_RESEND_MAX`), each accepted resend retiring the prior token.
- **Password reset** (`PasswordResetService`): single-use expiring tokens, a generic
  request response that only issues for real accounts, and weak-password rejection
  that does **not** consume the token.
- **Masked-anonymous posting** (`PostingService`, `NotificationRepository`,
  `PostRepository`, `ThreadController` reveal): anonymous threads/replies collapse the
  public byline, the notification actor, profile activity, and the Following feed to
  "Anonymous" while preserving the real author's reputation/post-count and an audited
  admin/board-moderator reveal.

**PR #5** (merged) delivered the feature code. **PR #6** (merged 2026-06-27) is a
**test-only** fast-follow that closes the coverage gaps the PR #5 multi-agent
adversarial review surfaced (0 blockers / 0 high, but several enforced properties
had no asserting test, so a future regression would pass silently). The 9 added
regression guards:

- *Email verification* — a used token cannot be reused (verified timestamp left
  untouched on the second hit); an expired token is rejected (drives the
  `expires_at > UTC_TIMESTAMP()` clause); resend is throttled past the 3/hour cap.
- *Password reset* — an expired token is rejected on **both** the `/reset` form and
  the POST submit, and the password is not rotated.
- *Masked-anonymous* — an anonymous **reply** (not just the OP) is excluded from
  profile activity and the Following feed; the notification actor is masked for the
  `new_thread` and `mention` types (previously only `reply` was asserted); reputation
  and `post_count` are unaffected by anonymity (an anon post still counts, and a
  reaction on it still credits the real author).

**Evidence:** `tests/Integration/Core/AppEmailVerificationTest.php`,
`AppPasswordResetTest.php`, `AppAnonymousPostingTest.php`. Full suite:
**259 tests / 919 assertions** green (was 250 / 870). PR #6 carries no production,
schema, or runtime changes.

### Operations runbook

See `docs/runbooks/operations.md` for the documented procedures required by
PHASE_2_PLAN §10: pause email, disable a feature flag, drain/replay the queue,
recompute counters, rebuild search indexes, and restore from backup.

### Gate A acceptance checklist (PHASE_2_PLAN §13)

- [x] Scope, deferrals, and evidence map approved (this document).
- [x] Phase 1 regression baseline remains green; the closeout suite target is now
      the full 803-test repository suite.
- [x] Clean-install and populated-upgrade migrations pass (`verify:upgrade` 17/17).
- [x] Email idempotency/outbox schema gap resolved (`email_deliveries.idempotency_key`, M0).
- [x] Unread cutover policy implemented and verified (M1 + `engagement:cutover`).
- [x] Reactions, stars, unread, subscriptions, notifications, mentions, search, DMs,
      reports, scoped moderation, and minimal reputation pass acceptance.
- [x] Notification / privacy / block / DM settings pass their server-side enforcement matrix.
- [x] Worker, instant email, digest, suppression, and unsubscribe paths pass operational tests.
- [x] Search / private-board / notification deep-link leakage tests pass.
- [x] Guest, User, suspended, banned, scoped Moderator, out-of-scope Moderator, and Admin matrices pass.
- [x] Gate A paths pass without JavaScript (server-rendered suite). [x] Browser capture at desktop/mobile widths (`tests/browser/` Playwright harness → `docs/evidence/browser/`, CI-reproduced).
- [x] Counter-repair and queue-operating procedures are documented (runbook).
- [x] No critical/high defects remain (M5 review: 3 medium/low fixed).
- [x] Feature-flag rollback rehearsed (`AppFeatureFlagTest`) and pause-worker fail-closed tested (`NotificationEmailWorkerTest`); [x] **backup-restore rehearsed** (`tests/backup/rehearse.sh` → `docs/evidence/backup-restore/`: 34 tables / 76 rows backed up + restored, row count + `CHECKSUM TABLE` match, schema intact, app boots). Staged-enablement order is documented in the runbook (§8); executing it is an operator/deploy step.
- [x] README, changelog, schema, and completion evidence updated.
- [ ] **Gate A product-owner acceptance recorded** — pending Henry's sign-off.

### Gate B acceptance checklist

- [x] Follows/feed, badges, solved answers, activity profiles, and all-time leaderboard pass privacy + idempotency tests.
- [x] OAuth provider, collision, linking/unlinking, and banned-account tests pass.
- [x] Saved/board preferences and session/device controls pass.
- [~] Approved export/delete behaviour — **formally re-scoped to Phase 3** (retention/anonymisation policy not yet approved; USER §3.5). Recorded below.
- [x] Presence passes; mobile/keyboard/accessibility CSS in place. [x] Browser evidence — see Gate A.
- [x] Email delivery visibility/test/recovery tools — `statusCounts` + worker stats + suppression recovery present; the dedicated admin delivery dashboard (`/admin/email`: delivery log + status/kind/email filters, queue status cards, test-send, failed-delivery requeue, suppression add/remove with the §7.6 subscription cascade, From/config banner, CSV export) was originally **re-scoped to Phase 3** but was **pulled back into the Phase 2 closeout on 2026-06-29** rather than left deferred (see `docs/adr/0005-phase2-operator-surface-closeout.md`). The 2026-06-30 carryover slice adds the email-broadcast announcement channel, `NotificationEmailWorker` `kind='system'` rendering, and the §7.5 SPF/DKIM domain-status / sending-blocked gate.
- [x] All Gate B deferrals recorded here rather than silently omitted.
- [x] **Full Phase 2 evidence captured** — consolidated Playwright evidence covers the operator surfaces at desktop + mobile in `docs/evidence/browser/`; the production-like profile is documented under `tests/prodlike/` and drives the closeout scripts in `tests/browser/package.json`. **Product-owner closeout sign-off remains pending** in `docs/evidence/phase2-final-acceptance.md`.

### Known gaps / formally re-scoped (carry to Phase 3)

- ~~**Browser/Playwright evidence** at desktop + mobile widths~~ — **DONE.** Playwright
  harness in `tests/browser/` captures 28 named surfaces at 1280×800 and 390×844
  (`docs/evidence/browser/`), with `.github/workflows/browser-evidence.yml`
  regenerating them in CI against a MariaDB service.
- **Self-service data export/delete** (USER §3.5): originally deferred pending an
  approved retention/anonymisation/grace-period policy; implemented in the
  2026-06-30 account lifecycle carryover slice under ADR 0006.
- **Admin assignment UIs**: board moderator/member assignment **shipped** (see M4
  follow-up above). Manual badge grant + cosmetic title override **shipped** in the
  Phase 2 closeout (2026-06-29): the ADMIN §5.2 per-user admin record at
  `/admin/users/{id}` (plus the §5.1 directory at `/admin/users`) hosts audited badge
  grant/revoke and the cosmetic title override (see
  `docs/adr/0005-phase2-operator-surface-closeout.md`).
- **Board archive + category/board reorder** and **admin announcements** (site banner +
  in-app broadcast) **shipped** in the Phase 2 closeout (2026-06-29), reusing existing
  tables/flags (ADR 0005). The **email-broadcast** announcement channel and
  `NotificationEmailWorker` `kind='system'` path shipped in the 2026-06-30
  carryover slice. The archive read-only policy is final for Phase 2 closeout:
  archived boards remain readable/listed, but all writes are frozen until
  unarchive, including thread/reply writes, board edits, and tag edits. There is
  no tag-edit carve-out.
- **Failed-email auto-retry**: failed rows require operator replay from the
  `/admin/email` dashboard or the runbook; an automatic backoff retry is a
  later enhancement.
- **Email domain send-blocking**: SPF/DKIM policy is now gated by
  `docs/adr/0008-email-domain-send-blocking-policy.md`; implementation shipped
  in the 2026-06-30 carryover slice with cached domain status, manual refresh,
  opt-in send blocking, and worker queued-row blocking reasons.

## Phase 3 status

### Phase 3 status and closeout ledger

**Reconciled:** 2026-06-30

**Engineering state:** Gate A implementation and in-repo evidence are complete for
the current RetroBoards codebase. The final closeout harness now includes a
production-like Nginx/PHP-FPM/MariaDB/k6 profile under `tests/prodlike/`.

**Formal state:** product-owner acceptance is still required. This document records
engineering status and evidence; it is not a product-owner sign-off.

### Summary

- Gate A core polish is implemented: preferences, shared Markdown composer, local
  drafts, image uploads, central anti-abuse controls, IP retention purge, branding,
  SEO, product tour, and feature-flag rollback paths.
- The final closeout pass fixed the remaining small Gate A code gaps: reading
  defaults now present as 20/20, emoji shortcodes render through the server
  Markdown pipeline, uploads fail safely under configured disk pressure, branded
  emails use operator `site_name`, the tour has a replay entry point and focus
  handling, and browser-local drafts now clear only after a confirmed navigation.
- Browser evidence now drives the Phase 3 JavaScript paths, not only page loads:
  preferences, toolbar/preview/emoji, draft reload/discard/success-clear, upload
  thumbnail/alt text, branding live preview, and tour replay.
- Gate B work that is not in this codebase is explicitly deferred in
  [ADR 0002](../adr/0002-phase-3-gate-b-deferrals.md).
- Later release-train work has resolved most original Gate B deferrals without
  reopening Phase 3: restricted non-image attachments, TOTP/recovery, read-only
  API tokens, webhook delivery, first-party hook producers, avatar upload/removal,
  safe signature moderation, board folders, and saved feed filters now have
  deploy-dark implementation evidence in their destination slices. The 2026-06-30
  carryover slice also implements appeals, advanced theming, account lifecycle
  export/delete, bookmark folders, bounded custom profile fields, and server-side
  draft sync. Public/untrusted plugin runtime is not a Phase 3 deliverable; ADR
  0011 records it as a Phase 5 Gate B security boundary.

### Evidence Index

| Evidence | Result |
|---|---|
| PHPUnit full suite | Current closeout target: `composer test` (803 tests as of 2026-06-30) |
| Focused Phase 3 PHP suite | Preferences, composer, upload, tour, branded email, notification/digest workers |
| Browser evidence | `cd tests/browser && npm run evidence` -> 27 passed / 1 skipped; screenshots in `docs/evidence/browser/{desktop,mobile}` |
| Production-like browser evidence | `npm run evidence:prodlike`, `npm run evidence:dark:prodlike`, and `npm run a11y:prodlike` target `http://127.0.0.1:8021` when `tests/prodlike/compose.yml` is running |
| Load/soak evidence path | Pending final 20-VU / 15-minute run. `docs/evidence/phase3-load/phase3-load-summary.json` currently records an interrupted diagnostic run after the full gate was skipped at operator direction on 2026-06-30. |
| Production-like worker smoke | `docker compose -f tests/prodlike/compose.yml exec -T app php bin/console ...` for `worker:email 100`, `worker:digest`, `worker:drafts`, `worker:attachments`, `worker:attachment-scans 60`, `worker:webhooks 100`, and `worker:extensions 100` all exited 0 against `retroboards_prodlike` |
| Browser coverage map | [docs/evidence/browser/README.md](../evidence/browser/README.md) |
| Closeout evidence note | [docs/evidence/phase3-closeout.md](../evidence/phase3-closeout.md) |
| Final acceptance artifact | [docs/evidence/phase3-final-acceptance.md](../evidence/phase3-final-acceptance.md) |
| Formal AT audit artifact | [docs/evidence/phase3-at-audit.md](../evidence/phase3-at-audit.md) |
| Security/privacy review artifact | [docs/evidence/phase3-security-privacy-review.md](../evidence/phase3-security-privacy-review.md) |
| Backup/restore rehearsal | [docs/evidence/backup-restore/README.md](../evidence/backup-restore/README.md) and the latest saved closeout log `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log` |
| Composer ADR | [docs/adr/0001-composer-engine.md](../adr/0001-composer-engine.md) |
| Gate B deferrals | [docs/adr/0002-phase-3-gate-b-deferrals.md](../adr/0002-phase-3-gate-b-deferrals.md) |

### Workstream Status

| ID | Workstream | Status | Evidence / notes |
|---|---|---|---|
| P3-00 | Entry gate, scope, baselines, flags | Gate A complete | `FeatureFlags`, `config/config.php`, this ledger |
| P3-01 | Preferences and settings IA | Gate A complete | `PreferenceSchemaTest`, `AppUserPreferencesTest`, browser screenshot `15-reading-preferences` |
| P3-02 | Composer engine and shared core | Gate A complete | `MarkdownRoundTripTest`, `AppComposerTest`, browser composer journey |
| P3-03 | Drafts and submission resilience | Gate A complete; server sync graduated to default-on 2026-07-02 | Browser journey covers autosave, reload restore, Drafts view, discard, and success-clear. Server-side draft sync graduated out of deploy-dark on 2026-07-02 (ADR 0010): flag default-on, focused PHPUnit, standard-run browser conflict capture, `.composer-draft-sync` axe pass, and runbook `docs/runbooks/server_drafts.md`. |
| P3-04 | Media and attachment safety | Gate A complete for images; non-image carryover implemented later | `AppImageUploadTest`, private/DM media tests, disk-pressure guard, browser upload evidence. Restricted PDF/text-family uploads are now implemented behind the Phase 4 `expanded_files` flag and still need final browser/runbook evidence. |
| P3-05 | Rate limits, anti-abuse, IP purge | Gate A complete | Rate-limit, content-approval, audit, spam seam, and IP retention tests |
| P3-06 | Appeals and moderator-scope extensions | Resolved in carryover slice | Moderation appeals now have member submission, staff queue, reverse/uphold outcomes, restoration, notification, and audit coverage. Moderator split/merge is implemented behind `split_merge` in the Phase 4 carryover train. |
| P3-07 | Branding and themes | Gate A plus advanced local theming implemented | Branding settings/live preview/contrast/cache busting and branded emails are covered. Retro preset, light/dark logo variants, and guarded custom CSS behind the dark `custom_css` flag are implemented per ADR 0009. |
| P3-08 | Performance, queries, caching | Gate A engineering complete for current architecture | Indexes/bounded queries/cache headers landed. No fragment/render cache was introduced, so cache-isolation risk is limited to existing private-media and public asset rules. Production-like load/soak remains a release-environment evidence item. |
| P3-09 | Accessibility and interaction quality | Gate A engineering complete | Keyboard toolbar, `aria-pressed`, reduced motion, touch targets, tour focus trap/Escape/restore, mobile browser evidence. Formal AT audit remains a release sign-off artifact. |
| P3-10 | SEO and public discovery | Gate A complete | `AppSeoVisibilityTest`, sitemap/robots/canonical/noindex behavior |
| P3-11 | Onboarding and learnability | Gate A complete | `AppProductTourTest`, replay button, browser tour replay evidence |
| P3-12 | Account security and polish | Resolved in later slices, pending rollout evidence | TOTP/recovery is implemented as the Phase 5 Gate A identity fallback. Avatar upload/removal and signature hardening are behind `profile_media`. Account deactivation/reactivation/export/delete, bookmark folders, and bounded custom profile fields are implemented in the 2026-06-30 carryover slice. |
| P3-13 | Internal extensions, webhooks, API | Trusted prerequisites resolved; public runtime is Phase 5 Gate B | Read-only API tokens, webhook delivery, service secrets, first-party hook producers, and the server-extension inspection/worker seam landed as deploy-dark Phase 5 prerequisites (the first four graduated default-on 2026-07-09, ADR 0018). Public/untrusted plugin execution remains outside Phase 3 until the Gate B sandbox is accepted. |
| P3-14 | Operations, release, closeout | Engineering complete; signoffs pending | Evidence index, browser harness, prodlike profile, backup/restore rehearsal, feature flags, and boundary ADRs are present. Product-owner, formal AT, full load/soak, and security/privacy signoffs are recorded as external gates. |

### Gate B Deferrals

The following remains open after the 2026-06-30 carryover implementation pass and
must not be treated as accepted because adjacent scaffolding exists:

- public/untrusted plugin runtime and sandboxed server extensions.

Later destination slices have resolved the following original Phase 3 deferrals
without making them Phase 3 work again: restricted non-image attachments
(`expanded_files`), TOTP/recovery (`0054`), avatar upload/removal and signature
hardening (`profile_media`), board folders/saved feeds, read-only API tokens,
webhook delivery, service secrets, and first-party hook producers.
The 2026-06-30 carryover train additionally resolves appeals, advanced local
theming/custom CSS, account lifecycle/export/delete, bookmark folders, and
bounded custom profile fields, and server-side draft sync with focused PHPUnit
and dark browser evidence.

The owner, rationale, risk, and destination for each item are recorded in
[ADR 0002](../adr/0002-phase-3-gate-b-deferrals.md). The current implementation
gates are [ADR 0006](../adr/0006-account-lifecycle-export-delete-policy.md)
for account lifecycle/export/delete,
[ADR 0007](../adr/0007-moderation-appeals-policy.md) for appeals,
[ADR 0009](../adr/0009-advanced-theming-custom-css-policy.md) for advanced
theming/custom CSS, [ADR 0010](../adr/0010-server-draft-sync-scope.md) for
server draft sync, and [ADR 0011](../adr/0011-public-plugin-runtime-scope.md)
for the public plugin runtime boundary.

### Release Sign-Off Items

These are process or environment-specific gates that code changes cannot complete
inside the repository:

- product-owner Phase 3 acceptance (`docs/evidence/phase3-final-acceptance.md`);
- production-like load/soak run on the target deployment profile
  (`docs/evidence/phase3-load/`);
- formal accessibility/assistive-technology audit sign-off
  (`docs/evidence/phase3-at-audit.md`);
- formal security/privacy review sign-off for the target deployment
  (`docs/evidence/phase3-security-privacy-review.md`).

No critical/high engineering blocker is known in the current codebase after the
closeout fixes and test runs.

## Phase 4 status

### Phase 4 Status

**Status:** engineering closeout complete with explicit deferrals; product-owner accepted as the Phase 5 entry baseline on 2026-06-28
**Last updated:** 2026-07-12
**Branch:** accepted baseline on `main`; current checkout includes later deploy-dark carryover code and closeout evidence
**Suite:** accepted baseline `./vendor/bin/phpunit` → 456 tests / 1635 assertions, green. Current checkout `composer test` → 866 tests / 4519 assertions, green

> 2026-06-30 carryover note: later branches implement additional ADR 0003
> carryovers behind dark flags, but they do not convert those carryovers into
> broad-rollout acceptance. See
> `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md` and
> `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.

### Thread Intelligence Follow-on (Pre-flip)

ADR 0019 separately authorizes the bounded automatic-publication design that
the original human-only Phase 4 boundary required. Migration `0077`, the
public-only evidence/worker/provider pipeline, Living Brief and curator/admin
surfaces, redacted operations, retention, and rollback controls are implemented,
but `community_memory` and `automated_context` both remain default `false`.
This is a pending graduation record, not a rewrite of the 2026-06-28 Phase 4
acceptance and not a claim that automatic publication is default-on.

The Task 12 live comparison selected `low` reasoning and a `16000` output-token
ceiling. It completed 46/46 runs with 149/149 supported material claims and zero
incomplete responses, private-sentinel transmissions, or fabricated decisions.
Evidence is in
`docs/evidence/phase4-closeout/thread-intelligence-live-eval.md` and
`thread-intelligence-live-rubric.json`; operations are in
`docs/runbooks/thread_intelligence.md`. Browser/mobile/no-JS/a11y,
security/privacy, concurrency, migration/upgrade, backup/restore, and rehearsed
runtime rollback remain pre-flip gates. The defaults may change only in the
separate final graduation change followed by two identical complete suites.

### Accepted Gate A Scope

Phase 4 Gate A has a reconciled additive schema
(`database/migrations/0048_phase4_gate_a.php`, originally `SCHEMA.md` v1.14),
reversible Phase 4 flags, and local regression coverage for the accepted Gate A
advanced-community slice. `topic_workflow`, `tags`, `expanded_feeds`, and
`reputation_ledger` now default on; the remaining Gate A workstreams stay
deploy-dark until intentionally enabled. The current consolidated schema is
reconciled through `SCHEMA.md` v1.24 for later deploy-dark carryovers.

- Topic workflow: canonical status/history, personal snooze, assignment, inbox filters, and staff-set status protection.
- Group DMs: bounded creation, membership intervals, owner actions, unread/history boundaries, admin-actionable reports, inactive-account rejection, and DM-report rate limiting.
- Advanced canonical content: task lists, tables, horizontal rules, and sanitizer coverage on the existing Markdown pipeline.
- Discovery graph: board/tag follows distinct from subscriptions, public tag lifecycle controls, tag aliases/merge, hidden/disabled tag gating, board `tags_enabled` enforcement, and follower removal.
- Recognition: reputation-event ledger, week/month/all-time and board-scoped leaderboards, opt-out handling, delete/restore reversal, no `FOR UPDATE` ledger hot path, and legacy `repair:reputation` routed through the ledger rebuild.
- Community memory: manual summaries with source display, publish/retire/restore, curated related topics, wiki edit history, board `wiki_enabled` enforcement, and wiki revert.
- Rollback controls: graduated Gate A features remain reversible through the
  `features` override; non-graduated Gate A flags default dark until intentionally
  enabled.

### Explicit Deferrals

`docs/adr/0003-phase-4-closeout-deferrals.md` is the Phase 4 carryover ledger.
The 2026-06-28 Phase 5 release-train instruction accepts these deferrals as
explicit carryovers, not shipped behavior.

The following remain not accepted for broad rollout:

- 2026-06-30 carryover implementations for moderation appeals, moderator
  split/merge, advanced theming, email domain/broadcast, and limited custom
  profile fields still need browser/a11y/runbook evidence before broad
  enablement. (Account lifecycle/export/delete graduated to default-on on
  2026-07-02 and profile media graduated to default-on on 2026-07-03 — see
  Operating Notes.)
- Production rollout/a11y/load/SEO artifacts beyond the local automated suite,
  Playwright browser capture, and backup/restore rehearsal.

Implementation gates are now recorded for policy-heavy carryovers:
`docs/adr/0006-account-lifecycle-export-delete-policy.md`,
`docs/adr/0007-moderation-appeals-policy.md`,
`docs/adr/0008-email-domain-send-blocking-policy.md`,
`docs/adr/0009-advanced-theming-custom-css-policy.md`,
`docs/adr/0010-server-draft-sync-scope.md`, and
`docs/adr/0011-public-plugin-runtime-scope.md`.

2026-06-30 review-hardening pass: `appeals` and `account_lifecycle` shipped
deploy-dark and route-gated with dark-assertion coverage (both have since
graduated to default-on on 2026-07-02 — see Operating Notes); account lifecycle
request/deactivate/reactivate/cancel and profile updates run inside
`$db->transaction()`; the deletion purge is wired to
`php bin/console worker:purge-accounts` and refuses to anonymize any account no
longer `pending_deletion`; the staff appeal queue is board-scoped like the report
queue; and broadcast announcement emails carry an unsubscribe link. The previously
tracked split/merge, appeals, and schema reconciliation gaps are now addressed by
focused tests and `SCHEMA.md` v1.23+.

The carryover branch has deploy-dark implementation evidence for
badge rules, post/DM/summary content references, link previews, expanded files,
polls, custom emoji, slash/GIPHY insertion, board folders, saved feed filters,
deterministic since-last-read context, scheduled related-topic refresh, avatar
upload/removal, signature hardening, moderation appeals, moderator split/merge,
account lifecycle/export/delete, email domain/broadcast, advanced theming,
bookmark folders, and bounded custom profile fields. These remain behind flags
or operator gates where applicable until the missing browser/a11y/upgrade/worker
runbook evidence is attached, except for polls, slash/GIPHY insertion, badge
rules, account lifecycle/export/delete, profile media, plus the
personal-organization slice (`board_folders`, `bookmark_folders`, `saved_feeds`)
that have graduated to default-on (`slash_giphy` stays inert until an operator
sets `giphy_public_key`).

Deploy-dark defaults are inventoried in
`docs/evidence/deploy-dark-features.md`; `src/Core/FeatureFlags.php` remains the
runtime source of truth.

### Evidence Index

- Standalone index: `docs/evidence/phase4-gate-a.md`.
- Deferral ADR: `docs/adr/0003-phase-4-closeout-deferrals.md`.
- Carryover ledger: `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`.
- Thread Intelligence pre-flip live evaluation:
  `docs/evidence/phase4-closeout/thread-intelligence-live-eval.md` and
  `thread-intelligence-live-rubric.json` (`low`/`16000`, 46/46 runs, 149/149
  supported, zero incomplete/private-sentinel/fabricated-decision outcomes).
- Thread Intelligence operations: `docs/runbooks/thread_intelligence.md`.
- Current carryover stopping point: `docs/evidence/phase4-closeout/carryover-partial-stopping-point.md`.
- Full suite: `composer test` → 984 tests / 5213 assertions.
- Current Phase 4 focused spine: `AppPhase4GateATest`, `AppPhase4CarryoverFoundationTest`, `AppAdminBadgeRulesTest`, `AppExpandedFilesTest`, `AppLinkPreviewTest`, `AppPollTest`, `AppCustomEmojiGiphyTest`, `AppContentReferenceTest`, `AppAutomatedContextTest`, `RelatedTopicRefreshWorkerTest`, `AppProfileMediaTest`, `AppThreadSplitMergeTest`, `AppBoardFoldersSavedFeedsTest` → 83 tests / 546 assertions.
- Later carryover-adjacent focused suite: `AppModerationAppealsTest`, `AppAccountLifecycleTest`, `AppBrandingThemeTest`, `AppAdminEmailTest` → 36 tests / 218 assertions.
- Focused Phase 4 regressions: `tests/Integration/Core/AppPhase4GateATest.php`.
- Deploy-dark flag regression: `tests/Integration/Core/AppFeatureFlagTest.php`.
- Graduated tags/feeds/reputation focused sweep:
  `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppFollowFeedTest`,
  `AppLeaderboardTest` → 47 tests / 286 assertions.
- Markdown sanitizer regression: `tests/Unit/SanitizationTest.php`.
- Browser evidence: `cd tests/browser && npm run evidence` → 41 passed / 1
  skipped across 42 Playwright tests, refreshing
  `docs/evidence/browser/{desktop,mobile}`.
- Accessibility: `cd tests/browser && npm run a11y` → 12 passed.
- Slash/GIPHY browser + a11y evidence: the `phase 4 slash menu` journey (now part
  of the standard `npm run evidence` run) drives `26-slash-menu` and
  `27-giphy-inserted` and asserts the ARIA combobox roles / arrow-key selection /
  Enter-insert / Escape-close; `a11y.spec.ts` adds a `.composer-slash-menu` scoped
  axe + keyboard-operability check.
- Backup/restore evidence: `tests/backup/rehearse.sh` → latest saved closeout log
  `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log`, current
  result 105 tables / 116 rows.
- Adjacent regression sweeps covered by full suite: `AppFollowFeedTest`, `AppLeaderboardTest`, `AppReactionTest`, `AppBadgeSolvedTest`, `AppDirectMessageTest`, `AppPostingTest`, `AppModeratorScopeTest`, `AppModerationTest`.

### Operating Notes

- `php bin/console repair:reputation`, `repair:reputation-ledger`, and `reputation:reconcile` now rebuild `reputation_events` from canonical reactions/accepted answers, reverse stale events, and reconcile `users.reputation`.
- Phase 4 Gate A feature flags still default `false`: `group_dms`,
  `community_memory`; the follow-on `automated_context` flag also remains
  default `false`. Thread Intelligence requires the latter two together and is
  still pre-flip.
- `topic_workflow` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppFeatureFlagTest::test_topic_workflow_is_available_by_default_and_can_be_disabled`, browser `29-topic-workflow`, `.wf-actions`/`.wf-bar` axe pass, `docs/runbooks/topic_workflow.md`). Reversible via the `features` override.
- `tags`, `expanded_feeds`, and `reputation_ledger` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppFeatureFlagTest`, `AppPhase4GateATest`, `AppFollowFeedTest`, `AppLeaderboardTest`, `docs/runbooks/phase4-tags-feeds-reputation.md`, and `docs/design-system/imladris/ACTIVATED_FEATURES.md`). Reversible via the `features` override.
- `board_folders`, `bookmark_folders`, and `saved_feeds` graduated to default-ON on 2026-07-01 (acceptance evidence: `AppPhase4CarryoverFoundationTest`, `AppBoardFoldersSavedFeedsTest`, and `docs/design-system/imladris/ACTIVATED_FEATURES.md`). Reversible via the `features` override.
- `slash_giphy` graduated to default-ON on 2026-07-02 (acceptance evidence: `AppCustomEmojiGiphyTest` incl. `test_slash_giphy_is_default_on_and_operator_rollback_regates_route_and_csp`, `AppPhase4CarryoverFoundationTest`, browser `26-slash-menu`/`27-giphy-inserted`, `.composer-slash-menu` axe + keyboard pass, `docs/runbooks/slash_giphy.md`). **Inert until an operator sets `giphy_public_key`** (the picker config 404s and the composer slash menu does not render without a key); reversible via the `features` override or by clearing the key.
- `badge_rules` graduated to default-ON on 2026-07-02 (acceptance evidence: `AppAdminBadgeRulesTest` incl. `test_badge_rule_admin_routes_are_available_by_default_and_can_be_disabled` and `test_badge_rules_flag_rollback_preserves_award_history`, `AppFeatureFlagTest`, browser `32-badge-rules`/`33-badge-rule-preview`/`34-badge-rule-backfilled`, `/admin/badge-rules` axe pass, `docs/runbooks/badge_rules.md`). Awards happen only on an explicit Backfill (no cron); reversible via the `features` override.
- `account_lifecycle` graduated to default-ON on 2026-07-02 (acceptance evidence: `AppAccountLifecycleTest` against the shipped default, `AppFeatureFlagTest` incl. `test_account_lifecycle_carryover_defaults_on_and_is_operator_reversible`, browser `35-account-lifecycle`/`36-account-deletion-scheduled` no-JS member journey, `/settings/account/lifecycle` axe pass, `docs/runbooks/account_lifecycle.md`). Deletion is a 30-day grace + scheduled anonymizing purge (`worker:purge-accounts`, which ignores the flag — pause the cron to halt purges); reversible via the `features` override. Graduation also fixed the `worker:purge-accounts` `ReauthGate` construction bug in `bin/console`.
- `profile_media` graduated to default-ON on 2026-07-03 (acceptance evidence: `AppProfileMediaTest`, `AppFeatureFlagTest` incl. `test_profile_media_carryover_defaults_on_and_is_operator_reversible`, browser `46-profile-media-avatar`/`47-profile-media-moderation`, `.profile-media-panel`/`.profile-media-card` axe pass, `docs/runbooks/profile_media.md`). Reversible via the `features` override; rollback stops new profile-media mutations but does not erase existing profile values.
- All-time leaderboard remains governed by the existing `community` flag; windowed/board leaderboard modes require `reputation_ledger`, which is now default-on.
