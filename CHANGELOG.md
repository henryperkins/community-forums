# Changelog

All notable changes to RetroBoards are recorded here. Dates are UTC.

## [Unreleased] — Phase 3 Gate A (polish, trust & scale)

Implements the Phase 3 Gate A core slice on top of Phase 2. Suite green at **392 tests / 1347 assertions**. See `docs/PHASE_3_STATUS.md` for the full evidence index, the acceptance-bar audit (§11), and carryover ledger. All migrations additive; every subsystem is behind an independent feature flag.

### Gate A gap closure (post-audit)

- **Reading preferences are now server-enforced** (P3-01) — `show_avatars`,
  `show_signatures`, `show_reactions`, and `thread_sort` previously persisted but
  were never read by any render path. Avatars now hide in the post stream and the
  board listing; author signatures render under posts (never for an anonymous
  post — the masked byline must not be deanonymised); the reaction bar hides; and
  the board list honours last-activity / newest / reply-count ordering (validated
  enum → whitelisted `ORDER BY`, never raw SQL). New coverage:
  `tests/Integration/Core/AppReadingPreferencesTest.php` (5 tests).
- **Preferences can be exported** (P3-01) — `GET /settings/preferences/export`
  returns the user's appearance/reading/composing preferences as a
  self-describing JSON download (grouped by section, schema-versioned; non-schema
  blob keys excluded). Owner-scoped and read-only.
- **Preference schema upgrade path + corrupt-blob recovery** (P3-01) —
  `PreferenceSchema::upgrade()` brings a stored blob to the current schema
  version (version-stepped transform hook for future renames, drops values that
  no longer validate, preserves other subsystems' keys, never downgrades a blob
  written by a newer deploy), wired into `resolve()`. A corrupt/non-object stored
  JSON value already recovered to defaults via the repository guard; both are now
  covered by tests. **Only the composing toggles remain open for P3-01.**

### Hardening (post adversarial review)

- **Held content no longer leaks** into FULLTEXT search, the Following feed, the
  daily digest, or a direct thread URL — every read surface now filters
  `is_pending = 0` (matching the listing/sitemap/profile gates).
- **Idempotent submit is concurrency-safe** — the idempotency key is claimed
  inside the transaction before side effects, so a true double-submit rolls back
  and replays instead of leaving a duplicate (`DuplicateSubmissionException`).
- **Attachment retention** — media of a soft-deleted post is reclaimed only after
  a configurable grace window (`uploads.deleted_grace_days`, default 30; new
  `posts.deleted_at`), so a restored/appealed post keeps its images.
- Re-encoded upload output is re-checked against the size cap; the onboarding
  `next` redirect is constrained to same-origin paths.

### Hardening (second review pass)

- **Held content no longer leaks into the Inbox** — `inbox`/`countInbox`/
  `unreadCount`/`unreadFlags` now filter `is_pending = 0`, so a held thread's
  title/author/existence and the unread badge stay hidden until approval.
- **Editing a post to add an image no longer destroys it** — `editOwnPost` now
  finalizes newly referenced `/media/{id}` uploads in its transaction, so the
  attachment is bound (not left `temp` and swept by the orphan cleaner).
- **Access-restricted media is never publicly cached** — the `/media/{id}`
  `Cache-Control` is derived from the live authorization (held-post or
  private-board media → `private, no-store`), not the stored columns.
- **Held posts never become cached "last activity"** — `recomputeLastPost`
  (thread + board) and every `RepairService` counter exclude `is_pending = 1`,
  matching the runtime that defers counters until approval.
- New-user anti-abuse throttle treats an account as established only once it
  clears **both** the post-count and account-age thresholds (per config).
- Feature-flag gating completed for SEO (`/sitemap.xml`, `/robots.txt`),
  branding (`/admin/branding`, `/brand.css`), and the onboarding tour; the
  sitemap also excludes archived public boards' threads.
- Smaller fixes: idempotency replay tolerates lock-wait/deadlock and is
  result-type-scoped; per-post image cap enforced; CIDR prefix bounds-checked;
  composer upload placeholders are unique per file.

### Added

- **Preferences & settings IA (P3-01)** — versioned, validated preference schema
  (`PreferenceSchema`) with separate Appearance / Reading / Composing forms, a
  reset, and theme/density/font-size/reduced-motion stamped on `<html>` (no flash,
  no-JS themed). Dark theme + brand colors via CSS variables.
- **Shared composer (P3-02)** — one server render+sanitize pipeline for new
  thread / reply / DM / edit; live preview endpoint `/composer/preview`; `||spoiler||`
  Markdown extension; same-origin `/media/{id}` images allowed in the sanitizer;
  `composer.js` progressive enhancement (toolbar, counter, preview, paste/drop
  upload, local draft autosave). ADR `docs/adr/0001-composer-engine.md`.
- **Submission idempotency (P3-03)** — `submission_idempotency` table; double-click /
  retry / resend creates one logical thread/reply/DM.
- **Image uploads (P3-04)** — `attachments` lifecycle table; content-sniffed +
  GD-re-encoded uploads (strips metadata/polyglots), dimension/size/decompression
  guards; authorization-gated `/media/{id}` delivery (private boards + DMs);
  finalize-on-publish; `worker:attachments` orphan sweep.
- **Anti-abuse + holds (P3-05)** — central `RateLimitService` (named policies,
  trusted-proxy-aware client IP); `AntiAbuseService` word/link/duplicate/flood rules
  with observe→flag→hold→block modes; board approval holds; `/mod/approvals` queue;
  immutable system-actor audit; `worker:purge-ips` 90-day IP-retention purge.
- **Branding (P3-07)** — `/admin/branding` for name/logo/favicon/colors + signed-out
  default theme; dynamic `/brand.css` (CSP-safe); placeholder retirement; reset + audit.
- **SEO (P3-10)** — `/sitemap.xml` (public-only, excludes private/hidden/deleted/held),
  `/robots.txt`, canonical/OpenGraph/description meta, noindex on private surfaces.
- **Onboarding tour (P3-11)** — `users.onboarded_at`; `/onboarding/complete`·`/replay`;
  skippable/replayable `tour.js` (missing-target tolerant, no-JS safe).

### Schema

- Migrations `0042`–`0047`: `users.avatar_path`/`onboarded_at`; `attachments`;
  `submission_idempotency`; `threads.is_pending`/`posts.is_pending`;
  `boards.require_approval`; `posts.deleted_at`. Reconciled in `SCHEMA.md`.

## [Unreleased] — Phase 2 review follow-ups

Post-merge fixes from the PR #2 review (no schema changes; suite green at 221 tests / 726 assertions).

### Fixed

- **Engagement on private boards (ENG-1)** — `BoardPolicy::canRead/isListed/canPost`
  now require an explicit `$isMember`, and the engagement, star, subscription,
  notification deep-link, home index and post-target gates resolve board
  membership before calling them. A private-board member can now react/star/
  subscribe (previously every such action 404'd); non-members are still blocked.
- **OAuth email squatting (OAUTH-1)** — a new account from an **unverified**
  provider email no longer occupies the globally-unique `users.email`; the
  address is parked on a synthetic placeholder and preserved on the
  `oauth_identities` row, so an attacker can't deny a victim registration.
- **Outbox double-send (EMAIL-1)** — the instant email worker takes a
  connection-scoped advisory lock (`rb_email_outbox`) so concurrent/overlapping
  runs can't send the same queued row twice.
- **Sessions survive a password change (SESS-1)** — changing or first-setting a
  password now revokes all other sessions, keeping only the current one.
- **DM read state (DM-1)** — a conversation opens on its newest page and is
  marked read up to the latest message, so the unread badge clears for
  conversations longer than one page.
- **Destructive `verify:upgrade` (CONSOLE-1)** — the rehearsal command refuses
  when `APP_ENV=production` and otherwise requires an interactive `yes` (or
  `--force`) before dropping tables, instead of wiping the configured database
  on sight.

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
