# RetroBoards — Phase 2 Implementation Status & Evidence Index

**Status:** M0–M6 + Gate A follow-ups implemented; Gate A/B product-owner acceptance pending · **Date:** 2026-06-27 · **Owner:** Henry (lakefrontdigital.io)

Living evidence index for `PHASE_2_PLAN.md` (Community Essentials). Tracks which
milestone/workstream is implemented, where, and how it is verified. Entry gate
(green Phase 1 baseline) confirmed before any Phase 2 migration was written.

## How to verify locally

```bash
composer install
php bin/console migrate        # Phase 1 (0001-0010) + Phase 2 (0011-0041)
composer test                  # full PHPUnit suite — 275 tests / 987 assertions
php bin/console repair         # reconcile all denormalised counters + reputation
php bin/console verify:upgrade # rehearse a Phase-1→Phase-2 upgrade (scratch DB)
```

Integration tests run against `retroboards_test` (override `DB_TEST_DATABASE`),
fresh-migrated by the bootstrap and rolled back per-test in a transaction. Every
integration test drives the real kernel server-side over form POST → redirect, so
the suite is itself the no-JavaScript proof for the Gate A write paths.

## Milestone status

| Milestone | Workstreams | Status |
|---|---|---|
| M0 — Foundation | P2-00 | ✅ Done |
| M1 — Inbox state + reactions/reputation | P2-01, P2-02 | ✅ Done |
| M2 — Notifications, mentions, email | P2-03, P2-04, P2-05 | ✅ Done (core) |
| M3 — Search + DMs | P2-06, P2-07 | ✅ Done |
| M4 — Scoped moderation + operator controls | P2-08 | ✅ Done (core) |
| M5 — Community identity + account expansion | P2-09, P2-10, P2-11 | ✅ Done |
| M6 — Hardening + phase close | P2-12 | ✅ Done (acceptance pending) |

## M0 — Foundation (P2-00) ✅

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

## M1 — Inbox state + reactions/reputation (P2-01, P2-02) ✅

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

## M2 — Notifications, mentions, email (P2-03/P2-04/P2-05) ✅ core

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

## M3 — Search + direct messages (P2-06, P2-07) ✅

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

## M4 — Scoped moderation + operator controls (P2-08) ✅ core

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

## Adversarial review (M0+M1) — applied

A multi-agent review of the M0+M1 diff produced 6 confirmed findings, **all
fixed**: hidden-board leak in inbox/unread listing (now public-only +
`board_members` in M4); engagement write/inbox routes now feature-flag gated;
`star` now runs the WriteGate; `safeReturn` open-redirect (backslash) closed;
`dm_messages.body_html` and `reports.notify_reporter` reconciled into SCHEMA.md
(§7 #14/#15). The same hardening patterns were applied proactively to M2.

### Deviations / decisions recorded during build

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

## M5 — Community identity + account expansion (P2-09/10/11) ✅

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

## Adversarial review (M5) — applied

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

## M6 — Hardening + phase close (P2-12) ✅

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
  harness (`tests/browser/`) drives the real app in Chromium and captures 14 Gate A
  surfaces at both widths (`docs/evidence/browser/{desktop,mobile}/`), regenerated in
  CI by `.github/workflows/browser-evidence.yml` (on pushes touching the app or
  harness, and on demand) against an ephemeral MariaDB service.

## Gate A follow-ups — auth flows + masked-anonymous posting (PR #5 / #6)

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

## Operations runbook

See `docs/PHASE_2_RUNBOOK.md` for the documented procedures required by
PHASE_2_PLAN §10: pause email, disable a feature flag, drain/replay the queue,
recompute counters, rebuild search indexes, and restore from backup.

## Gate A acceptance checklist (PHASE_2_PLAN §13)

- [x] Scope, deferrals, and evidence map approved (this document).
- [x] Phase 1 regression baseline remains green (157 → 275 tests, additive only).
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
- [x] Feature-flag rollback rehearsed (`AppFeatureFlagTest`) and pause-worker fail-closed tested (`NotificationEmailWorkerTest`); [ ] backup-restore and staged-enablement procedures documented in the runbook but **not executed** in this environment.
- [x] README, changelog, schema, and completion evidence updated.
- [ ] **Gate A product-owner acceptance recorded** — pending Henry's sign-off.

## Gate B acceptance checklist

- [x] Follows/feed, badges, solved answers, activity profiles, and all-time leaderboard pass privacy + idempotency tests.
- [x] OAuth provider, collision, linking/unlinking, and banned-account tests pass.
- [x] Saved/board preferences and session/device controls pass.
- [~] Approved export/delete behaviour — **formally re-scoped to Phase 3** (retention/anonymisation policy not yet approved; USER §3.5). Recorded below.
- [x] Presence passes; mobile/keyboard/accessibility CSS in place. [x] Browser evidence — see Gate A.
- [~] Email delivery visibility/test/recovery tools — `statusCounts` + worker stats + suppression recovery present; a dedicated admin delivery dashboard is **re-scoped to Phase 3**.
- [x] All Gate B deferrals recorded here rather than silently omitted.
- [ ] **Full Phase 2 evidence index + product-owner closeout recorded** — pending sign-off.

## Known gaps / formally re-scoped (carry to Phase 3)

- ~~**Browser/Playwright evidence** at desktop + mobile widths~~ — **DONE.** Playwright
  harness in `tests/browser/` captures 14 Gate A surfaces at 1280×800 and 390×844
  (`docs/evidence/browser/`), with `.github/workflows/browser-evidence.yml`
  regenerating them in CI against a MariaDB service.
- **Self-service data export/delete** (USER §3.5): deferred pending an approved
  retention/anonymisation/grace-period policy (the plan explicitly permits this).
- **Admin assignment UIs**: board moderator/member assignment **shipped** (see M4
  follow-up above). Manual badge grant and cosmetic title override still have no
  operator page — data layer + scope enforcement are complete; both are set via
  repo/console today and remain Phase-3 polish.
- **Admin announcements/broadcast** and **board archive/reorder** (Gate B extended,
  reuse existing tables/flags): wiring deferred — recorded as Phase-3 carryover.
- **Signature rendering under posts**: the field is stored/editable; display is a
  small follow-up.
- **Failed-email auto-retry**: failed rows require operator replay (runbook); an
  automatic backoff retry is a Phase-3 enhancement.
