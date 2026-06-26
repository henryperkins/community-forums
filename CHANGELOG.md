# Changelog

All notable changes to RetroBoards are recorded here. Dates are UTC.

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
