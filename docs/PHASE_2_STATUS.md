# RetroBoards — Phase 2 Implementation Status & Evidence Index

**Status:** In progress · **Date:** 2026-06-26 · **Owner:** Henry (lakefrontdigital.io)

Living evidence index for `PHASE_2_PLAN.md` (Community Essentials). Tracks which
milestone/workstream is implemented, where, and how it is verified. Entry gate
(green Phase 1 baseline) confirmed before any Phase 2 migration was written.

## How to verify locally

```bash
composer install
php bin/console migrate        # Phase 1 (0001-0010) + Phase 2 (0011-0038)
composer test                  # full PHPUnit suite (unit + integration)
php bin/console repair         # reconcile all denormalised counters + reputation
```

Integration tests run against `retroboards_test` (override `DB_TEST_DATABASE`),
fresh-migrated by the bootstrap and rolled back per-test in a transaction.

## Milestone status

| Milestone | Workstreams | Status |
|---|---|---|
| M0 — Foundation | P2-00 | ✅ Done |
| M1 — Inbox state + reactions/reputation | P2-01, P2-02 | ✅ Done |
| M2 — Notifications, mentions, email | P2-03, P2-04, P2-05 | ✅ Done (core) |
| M3 — Search + DMs | P2-06, P2-07 | ⬜ Planned |
| M4 — Scoped moderation + operator controls | P2-08 | ⬜ Planned |
| M5 — Community identity + account expansion | P2-09, P2-10, P2-11 | ⬜ Planned |
| M6 — Hardening + phase close | P2-12 | ⬜ Planned |

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
