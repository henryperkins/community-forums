# Changelog

All notable changes to RetroBoards are recorded here. Dates are UTC.

## [Unreleased] — Phase 2 (M5): community identity & account completion

Milestone 5 of the Phase 2 release train. Builds on M0–M4 (engagement,
notifications/email, mentions, search, DMs, scoped moderation). Additive
migrations only; with the M6 hardening below the full suite is green at 215 tests / 694 assertions.

### Added

- **Community identity (P2-09)** — user→user **follows** with a new-follower
  notification (block-aware), a paginated, query-time **Following feed** (`/feed`)
  gated to accessible content, the fixed **badge** catalogue (migration `0040`,
  seeded idempotently) with automatic milestone awards + admin manual grants,
  accepted/**"solved" answers** (OP or board moderator; +5 reputation to the
  answerer with self-answer exclusion, the Problem Solver badge, an in-app + email
  notification, and an audit row — all transactional), an all-time
  **Top Contributors** leaderboard (`/leaderboard`, opt-out + banned excluded),
  and cosmetic **titles** derived from reputation with an admin override. The
  public profile gains follower/following counts, a badge row, title, presence
  pill, activity, and Follow / Message / Block actions; renamed handles 301-redirect.
- **Member controls (P2-10)** — `/settings/privacy` (profile visibility, DM policy,
  presence, leaderboard opt-out, email discoverability), `/settings/preferences`
  (server-enforced pagination + reading/appearance prefs in a JSON blob),
  `/settings/notifications` (timezone-aware digest hour + subscription management),
  `/settings/blocks`, `/settings/boards` (favorite / mute, muted boards leave the
  sidebar), and **active sessions/devices** (`/settings/sessions`: list, revoke one,
  log out everywhere else). Extended profile fields — website and pronouns (shown
  on the profile) plus a stored signature (rendering under posts is a follow-up).
- **OAuth (P2-10)** — a pluggable provider abstraction (Google/GitHub/Apple) with
  `state` + **PKCE** + nonce, strict callback verification via a signed state
  cookie, and the security-critical account-resolution tree: returning login,
  new signup with **avatar import**, **verified-email collision that never
  auto-merges**, banned-account refusal, explicit linking, and
  **last-login-method protection** on unlink. OAuth-only accounts can set a
  password. Tokens are never persisted.
- **Presence (P2-11)** — a throttled `last_seen_at` heartbeat and a short-poll
  roster endpoint (`/presence`) that never exposes a hidden user, a stale user,
  the viewer, or a blocked member; a sidebar "who's online" widget.
- **CLI** — `community:backfill-badges` (idempotent auto-award incl. Anniversary,
  cron-safe) and `repair:reputation` now layers the solved-answer bonus onto the
  reaction base.
- **UI/accessibility** — community/settings/presence component styles, `:focus-visible`
  outlines, ≥44px mobile tap targets, and a `prefers-reduced-motion` guard.

### Hardening & closeout (M6 / P2-12)

- **Upgrade rehearsal** — `bin/console verify:upgrade` (`App\Support\UpgradeRehearsal`)
  builds the Phase 1 schema, seeds data, applies the Phase 2 migrations, and
  asserts no data loss: 17/17 checks (row counts + sample values preserved, 23 new
  tables + 11 new columns present, every Phase 1 column retained (full 90-column
  before/after diff), 11 badges seeded).
- **Feature-flag rollback** — `AppFeatureFlagTest`: each Phase 2 flag disables its
  routes (404) with the core forum still serving, no data change.
- **Queue backlog** — bounded oldest-first drain that resumes without loss
  (`NotificationEmailWorkerTest`).
- **Index review** — migration `0041` adds `idx_users_reputation`; the leaderboard
  goes from a full scan + filesort (`type=ALL`) to a **filesort-free** index range
  scan — the `reputation DESC, id DESC` order is served directly by the index
  (InnoDB appends the PK), verified by EXPLAIN (`Using where`, no `Using filesort`).
- **Docs** — `docs/PHASE_2_STATUS.md` evidence index + Gate A/B checklist;
  `docs/PHASE_2_RUNBOOK.md` operations (pause email, flags, drain/replay queue,
  repair counters, rebuild search, restore).

### Tests

- New integration suites: follows/feed, badges + solved answers, leaderboard,
  community profile, member preferences, session management, OAuth resolution,
  presence, and feature-flag rollback (+58 tests over the Phase 1 baseline →
  215 total / 694 assertions).

## [0.1.0] — 2026-06-26 — Phase 1: MVP backend

First implemented release. Ships a secure, server-rendered forum core that works
fully without JavaScript. See `PHASE_1_PLAN.md` for the delivery baseline and
`docs/PHASE_1_COMPLETION.md` for the acceptance-evidence index.

### Added

- **Foundation** — vanilla-PHP front controller (`public/index.php`), micro-router,
  PSR-4 service container, PDO database layer (prepared statements only),
  environment/config loader, plain-PHP template engine, and a `/healthz` endpoint
  that reports database status.
- **Migrations** — the 10 Phase-1 tables (`users`, `categories`, `settings`,
  `boards`, `sessions`, `verifications`, `board_slug_history`, `threads`, `posts`,
  `moderation_log`) as the lean cut from `PHASE_1_MIGRATIONS.md`, runnable via
  `bin/console` (`migrate`, `migrate:fresh`, `migrate:rollback`, `migrate:status`).
- **Read path & shell** — three-pane "Community Inbox" layout, category/board
  index, paginated board thread lists and thread views, monogram avatars, public
  profiles (`/u/{username}`), guest read access with a join bar.
- **Authentication & request security** — registration, login, logout, Argon2id
  password hashing, DB-backed sessions (opaque cookie token, SHA-256 at rest),
  per-session/guest CSRF protection enforced on every state change, baseline
  login/registration rate limiting + posting throttle (file/array store behind a
  limiter interface), and security headers (CSP, `X-Content-Type-Options`,
  `Referrer-Policy`, `X-Frame-Options`, optional HSTS).
- **Posting** — create thread, reply, edit own post, soft-delete own post, with
  transactional denormalized counters and PRG redirects. Canonical Markdown is
  stored in `posts.body` and rendered to sanitised HTML (`posts.body_html`) via
  `league/commonmark` + a DOM allowlist sanitizer (no raw HTML/scripts, no images
  or tables, headings clamped to `##`/`###`, links forced to `rel="nofollow ugc
  noopener noreferrer"`).
- **Account & profile** — edit display name, bio, and location; change password
  (current password required); public profile with post count and reputation.
- **First-run setup** — `/setup` wizard creates the first admin, community name,
  and starter categories/boards, then signs the admin in; it locks once an admin
  exists.
- **Minimal admin console** — `/admin` site naming, category/board create/edit and
  delete-when-empty, board slug-change 301 redirects via `board_slug_history`, and
  a baseline audit feed.
- **Moderation & write gates** — inline pin/unpin, lock/unlock, and soft-delete
  any post, each written to `moderation_log`; locked threads reject replies; a
  centralized account-state write gate blocks suspended/banned accounts on every
  write path, including via stale sessions.
- **Tests** — PHPUnit unit + integration suites (79 tests, 274 assertions) driving
  the real kernel in-process, plus an HTTP smoke matrix.

### Hardened (post adversarial review)

- Public profiles list only public-board activity (no hidden/private leak).
- `/healthz` reports `503` when the database is unreachable (dispatched ahead of
  the DB-querying setup gate).
- Post soft-delete is idempotent under concurrent/duplicate deletes (counters no
  longer double-decrement); board last-activity is recomputed on delete.
- `next`-param redirect and link sanitizer reject protocol-relative URLs in every
  slash/backslash form; login timing is equalized against account enumeration;
  `HEAD` requests are served by the matching `GET` route.

### Deferred (per `PHASE_1_PLAN.md` / `DECISIONS.md`)

Reactions, stars, subscriptions, unread tracking, notifications/email, search,
DMs, reports queue, per-board moderators, OAuth, the rich composer/uploads, the
in-app warn/suspend/ban tooling and `bans` table, IP capture, and the configurable
anti-spam limiter — all land in later phases.
