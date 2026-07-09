# RetroBoards — Phase 1 Migration Manifest

**Status:** v1.1 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-06-26
**This file is the authoritative cut of [SCHEMA.md](../../SCHEMA.md) for the Phase 1 build.** SCHEMA.md owns each table's *final* shape (all phases folded in); this file says **exactly which tables, columns, and indexes the Phase 1 migrations create**, and which columns are **held back** to a later phase (with the phase and reason). Where the two differ, SCHEMA.md is right about the final shape and this file is right about the Phase 1 cut. Source inputs: SCHEMA.md §1–3/§6, PHASE_1_PLAN.md §3, PHASE_2_PLAN.md §3/§7, PHASE_3_PLAN.md §8, DECISIONS.md.

> **Nothing is built yet.** These are migrations to *write*, not a description of an existing database.

---

## 1. Migration philosophy

**Lean / additive.** Each phase's migrations create only what that phase's features use; later phases add columns/indexes with additive `ALTER TABLE`. This matches the phase plans (PHASE_2 builds FULLTEXT in P2‑06; PHASE_3 §8.1 creates `users.onboarded_at` in Phase 3; PHASE_2 §7.1 adds `threads.accepted_answer_post_id`).

Two rules decide each column:

1. **Create in Phase 1** if a Phase 1 feature reads/writes it, **or** a source doc explicitly says the column "ships in Phase 1."
2. **Defer** to the phase that builds its feature if the column belongs to a distinct subsystem or carries an obligation (PII + retention, presence, digests, onboarding, search index, extended profile, privacy, "solved").

**Cheap‑flag judgment call.** A few deferred columns are cheap, no‑op‑by‑default boolean/enum flags (`boards.require_approval`/`edit_window_seconds`/`is_archived`, `threads.is_pending`, `posts.is_pending`). The manifest defers them to keep each migration feature‑aligned, **but a team may instead create them in Phase 1 with their default values to avoid later `ALTER`s** — they carry no behavior until their feature ships. Either choice is valid. The columns that must **not** be created early are the subsystem/PII ones (avatars, presence, digests, onboarding, privacy, IP, FULLTEXT) — see the §4 master list.

---

## 2. Migration order & dependencies

One migration per table, FK‑parent before child. `0000` is the framework's own bookkeeping table (`schema_migrations` or equivalent). `sessions = 0005` matches SCHEMA §7 #7's target filename; `moderation_log = 0010` (SCHEMA's v1.1 entry cited `0007` illustratively — this manifest is canonical).

| # | Migration | Creates | Depends on (FK) |
|---|---|---|---|
| 0001 | `0001_users` | `users` | — |
| 0002 | `0002_categories` | `categories` | — |
| 0003 | `0003_settings` | `settings` | — |
| 0004 | `0004_boards` | `boards` | `categories` |
| 0005 | `0005_sessions` | `sessions` | `users` |
| 0006 | `0006_verifications` | `verifications` | `users` |
| 0007 | `0007_board_slug_history` | `board_slug_history` | `boards` |
| 0008 | `0008_threads` | `threads` | `boards`, `users` |
| 0009 | `0009_posts` | `posts` | `threads`, `users` |
| 0010 | `0010_moderation_log` | `moderation_log` | `users` |

**Forward references stay un‑FK'd.** `boards.last_thread_id`, `threads.last_post_id`/`last_post_user_id`, and `posts.parent_post_id` point at rows in tables created later (or the same table); SCHEMA defines **no** FK constraint on them, so leave them as plain `BIGINT UNSIGNED NULL` columns. Only the real FKs below are enforced.

---

## 3. Phase 1 table DDL (the lean cut)

Conventions (from SCHEMA): **InnoDB**, **`utf8mb4`**, `BIGINT UNSIGNED` surrogate PKs, `DATETIME` stored **UTC**. Each block is the Phase 1 shape; the note lists what is **omitted vs SCHEMA's final shape** and when it lands.

### 0001 · `users`
```sql
CREATE TABLE users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username          VARCHAR(32)     NOT NULL,
  email             VARCHAR(255)    NOT NULL,
  password_hash     VARCHAR(255)    NULL,                 -- Argon2id; NULLable (OAuth-only accounts arrive P2)
  display_name      VARCHAR(64)     NULL,
  role              ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
  location          VARCHAR(64)     NULL,
  bio               TEXT            NULL,
  post_count        INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  reputation        INT             NOT NULL DEFAULT 0,   -- shown on profile; stays 0 until P2 reactions populate it
  status            ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  suspended_until   DATETIME        NULL,                 -- fast-path suspension; the `bans` history table is P2
  email_verified_at DATETIME        NULL,                 -- column ships P1; the verify FLOW is P2 (needs the email worker)
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Omitted (final shape adds later):** `title` (P2), `signature` (P2), `website` (P2), `pronouns` (P2), `avatar_path` (P3 — uploads), `avatar_source` (P2 — first set by OAuth avatar‑import; §6), `profile_visibility` (P2), `allow_dms` (P2), `show_presence` (P2), `onboarded_at` (P3), `timezone`/`digest_hour`/`last_daily_digest_at` (P2), `last_seen_at` + `idx_users_last_seen` (P2). Phase 1 renders avatars as **monograms computed from the username** — no avatar column needed.

### 0002 · `categories`
```sql
CREATE TABLE categories (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name     VARCHAR(64)     NOT NULL,
  position INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Omitted:** the "default‑collapsed" flag ADMIN §4.1 hints at is unspecced (SCHEMA §8) — not in any phase yet.

### 0003 · `settings`
```sql
CREATE TABLE settings (
  `key`      VARCHAR(64) NOT NULL,
  `value`    JSON        NULL,
  updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Phase 1 uses keys like `site_name`, `registration_mode`, and theme tokens. Full table ships P1.

### 0004 · `boards`
```sql
CREATE TABLE boards (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id     BIGINT UNSIGNED NOT NULL,
  slug            VARCHAR(64)     NOT NULL,
  name            VARCHAR(80)     NOT NULL,
  description     VARCHAR(255)    NULL,
  position        INT             NOT NULL DEFAULT 0,
  post_min_role   ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- column P1; enforcement P2 (PHASE_2 §3)
  visibility      ENUM('public','hidden','private') NOT NULL DEFAULT 'public', -- read gates used in P1
  allow_anonymous TINYINT(1)      NOT NULL DEFAULT 0,   -- column P1; masked-anon posting P2 (PHASE_2 §3)
  thread_count    INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  post_count      INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  last_thread_id  BIGINT UNSIGNED NULL,                 -- denormalised; no FK (threads built later)
  last_post_at    DATETIME        NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_boards_slug (slug),
  KEY idx_boards_cat_pos (category_id, position),
  CONSTRAINT fk_boards_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Kept rationale:** `post_min_role` + `allow_anonymous` ship in P1 because PHASE_2_PLAN §3 explicitly says these columns ship in Phase 1 (enforcement comes in P2); `visibility` is used by P1's hidden/private read gates. **Omitted:** `require_approval` (P3 — approval holds, P3‑05), `edit_window_seconds` (P2 — board edit policy), `is_archived` (P2 — board archive). All three are cheap flags you may pre‑ship instead (see §1).

### 0005 · `sessions`
```sql
CREATE TABLE sessions (
  id           CHAR(64)        NOT NULL,                 -- SHA-256 of the raw cookie token
  user_id      BIGINT UNSIGNED NOT NULL,
  csrf_secret  CHAR(64)        NOT NULL,                 -- per-session CSRF secret
  user_agent   VARCHAR(255)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME        NULL,                     -- session activity (not presence)
  expires_at   DATETIME        NOT NULL,
  revoked_at   DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_active (expires_at, revoked_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Omitted:** `ip` (P2 — login‑IP / ban‑evasion signal; PII with a 90‑day purge job in P3). SCHEMA annotates `sessions.ip` → **Phase 2** (reconciliation #11), matching `posts.ip`; both are added by the Phase 2 migration (first used by P2‑08), keeping IP capture consistent across the two tables.

### 0006 · `verifications`
```sql
CREATE TABLE verifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       ENUM('email_verify','email_change','password_reset') NOT NULL,
  token_hash CHAR(64)        NOT NULL,                   -- store only the hash
  new_email  VARCHAR(255)    NULL,                       -- for email_change
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NOT NULL,
  used_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verif_token (token_hash),
  KEY idx_verif_user (user_id, type),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Created but dormant in P1.** USER §8 says this table ships in Phase 1, but every flow that writes it (email verify, password reset, email change) needs the **Phase 2** email worker. Full table now; rows first appear in P2. *Optional:* defer the whole table to P2 — it has zero P1 readers/writers.

### 0007 · `board_slug_history`
```sql
CREATE TABLE board_slug_history (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id   BIGINT UNSIGNED NOT NULL,
  old_slug   VARCHAR(64)     NOT NULL,
  changed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_board_slug_history_old_slug (old_slug),
  KEY idx_board_slug_history_board (board_id),
  CONSTRAINT fk_board_slug_history_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Powers the P1 admin's slug‑change **301 redirects** (PHASE_1_PLAN P1‑07). Full table P1.

### 0008 · `threads`
```sql
CREATE TABLE threads (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id          BIGINT UNSIGNED NOT NULL,
  user_id           BIGINT UNSIGNED NOT NULL,            -- OP author
  title             VARCHAR(160)    NOT NULL,
  slug              VARCHAR(180)    NOT NULL,
  is_pinned         TINYINT(1)      NOT NULL DEFAULT 0,
  is_locked         TINYINT(1)      NOT NULL DEFAULT 0,
  is_deleted        TINYINT(1)      NOT NULL DEFAULT 0,
  reply_count       INT UNSIGNED    NOT NULL DEFAULT 0,  -- denormalised (excludes OP)
  view_count        INT UNSIGNED    NOT NULL DEFAULT 0,
  last_post_id      BIGINT UNSIGNED NULL,                -- denormalised; no FK
  last_post_user_id BIGINT UNSIGNED NULL,                -- denormalised; no FK
  last_post_at      DATETIME        NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_threads_inbox (board_id, is_pinned DESC, last_post_at DESC),
  KEY idx_threads_author (user_id),
  CONSTRAINT fk_threads_board FOREIGN KEY (board_id) REFERENCES boards(id),
  CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Omitted:** `accepted_answer_post_id` (P2 — "solved", added in PHASE_2 §7.1 group 6), `is_pending` (P3 — approval hold), `FULLTEXT KEY ft_threads_title` (P2 — search, built in P2‑06).

### 0009 · `posts`
```sql
CREATE TABLE posts (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id      BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,              -- always the REAL author
  parent_post_id BIGINT UNSIGNED NULL,                  -- quote/reply target; no FK (self-ref)
  body           MEDIUMTEXT      NOT NULL,              -- canonical Markdown
  body_html      MEDIUMTEXT      NULL,                  -- cached sanitised render (pick a renderer+sanitizer — §7)
  is_op          TINYINT(1)      NOT NULL DEFAULT 0,
  is_anonymous   TINYINT(1)      NOT NULL DEFAULT 0,    -- column P1; masked-anon render P2 (PHASE_2 §3)
  is_deleted     TINYINT(1)      NOT NULL DEFAULT 0,    -- soft delete
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at      DATETIME        NULL,
  edited_by      BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_posts_thread (thread_id, created_at),
  KEY idx_posts_author (user_id),
  CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id),
  CONSTRAINT fk_posts_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
**Omitted:** `ip` (P2 — post‑IP / ban‑evasion; PII, purge job P3), `is_pending` (P3 — approval hold), `FULLTEXT KEY ft_posts_body` (P2 — search, built in P2‑06).

### 0010 · `moderation_log`
```sql
CREATE TABLE moderation_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                     -- NULL = system/automated
  action      VARCHAR(40)     NOT NULL,                 -- pin, lock, delete_post, suspend, ...
  target_type ENUM('thread','post','user','board','category','setting') NOT NULL,
  target_id   BIGINT UNSIGNED NOT NULL,                 -- polymorphic; no FK
  reason      VARCHAR(255)    NULL,
  before_json JSON            NULL,                     -- edit snapshot
  after_json  JSON            NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modlog_target (target_type, target_id),
  KEY idx_modlog_actor (actor_id, created_at),
  CONSTRAINT fk_modlog_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Full table P1 (target is polymorphic, so it needs no FK to threads/posts and can be created last). Every P1 moderation/admin action writes a row.

---

## 4. Deferred‑columns master list (what later phases `ALTER … ADD`)

| Table | Deferred column / index | Phase | Why it's not in P1 |
|---|---|---|---|
| `users` | `title` | 2 | cosmetic rank (community layer) |
| `users` | `signature` | 2 | profile signature (unlock‑after‑N‑posts) |
| `users` | `website`, `pronouns` | 2 | extended profile fields |
| `users` | `profile_visibility`, `allow_dms`, `show_presence` | 2 | privacy / DMs / presence |
| `users` | `timezone`, `digest_hour`, `last_daily_digest_at` | 2 | daily email digests |
| `users` | `last_seen_at` + `idx_users_last_seen` | 2 | presence heartbeat |
| `users` | `avatar_source` | 2 | first non‑monogram source (OAuth avatar‑import) |
| `users` | `avatar_path` | 3 | avatar uploads pipeline |
| `users` | `onboarded_at` | 3 | product tour (PHASE_3 §8.1) |
| `sessions` | `ip` | 2 | login‑IP / ban‑evasion; PII (purge job P3) |
| `boards` | `edit_window_seconds`, `is_archived` | 2 | board edit policy / archive |
| `boards` | `require_approval` | 3 | approval‑hold queue (P3‑05) |
| `threads` | `accepted_answer_post_id` | 2 | "solved" (PHASE_2 §7.1 grp 6) |
| `threads` | `ft_threads_title` (FULLTEXT) | 2 | search (P2‑06) |
| `threads` | `is_pending` | 3 | new‑thread approval hold |
| `posts` | `ip` | 2 | post‑IP / ban‑evasion; PII (purge job P3) |
| `posts` | `ft_posts_body` (FULLTEXT) | 2 | search (P2‑06) |
| `posts` | `is_pending` | 3 | approval hold |

*(The cheap flags — `boards.require_approval`/`edit_window_seconds`/`is_archived`, `threads.is_pending`, `posts.is_pending` — may be pulled into Phase 1 with their no‑op defaults instead; see §1.)*

---

## 5. Cross‑cutting notes

- **No seed migration.** Phase 1's data comes from the **first‑run setup wizard** (creates the first admin, `settings.site_name`, and starter categories/boards) — application code, not a fixed‑data migration. Optional dev fixtures live outside the migration set.
- **Registration without verification.** Open registration creates a user with `email_verified_at = NULL`; Phase 1 does **not** gate posting on verification (the first‑post email gate is Phase 2 — PHASE_1_PLAN §3). `email_verified_at` simply stays `NULL` until the P2 flow lands.
- **Rollback.** On a greenfield install each `down` migration just drops its table (reverse order). Within Phase 1 there is no data to preserve.
- **FULLTEXT.** Phase 1 ships **no** search. `ft_threads_title` and `ft_posts_body` are added by Phase 2's P2‑06 migration on the populated tables.

---

## 6. Conflicts surfaced (status)

1. **Avatar / Gravatar phase — ✅ Resolved (2026‑06‑26).** DECISIONS §5 #4 now reads **monogram P1; OAuth avatar‑import P2; Gravatar + uploads P3**, matching USER §8/§9. Phase 1 stays **monogram‑only** (computed, no avatar column); `avatar_source` lands in P2 (first set by OAuth import), `avatar_path` in P3 (uploads).
2. **`sessions.ip` vs `posts.ip` — ✅ Resolved (2026‑06‑26).** SCHEMA now annotates `sessions.ip` → **Phase 2** (reconciliation #11), mirroring `posts.ip`. Both are omitted from the Phase 1 `sessions`/`posts` migrations and added by Phase 2 (login/post IP for ban‑evasion; 90‑day purge job in P3).
3. **`verifications` table timing.** Ships P1 per USER §8 but has no P1 reader/writer (all flows are P2). Keep as a dormant P1 table (current choice) or defer the whole table to P2 — no functional difference to Phase 1.

---

## 7. App‑level prerequisites (not schema, but block P1)

- **Markdown renderer + HTML sanitizer** for `posts.body_html` — the P0 XSS surface. Pick concretely (e.g. `league/commonmark` + an allowlist sanitizer); store canonical Markdown in `body`, cache sanitized HTML in `body_html`, never trust raw HTML.
- **Password hashing:** Argon2id (`password_hash(PASSWORD_ARGON2ID)`), stored in `users.password_hash`.
- **Session tokens:** opaque random token in the cookie; store its **SHA‑256** as `sessions.id`; per‑session `csrf_secret`.
- **Rate‑limit / throttle store (non‑schema):** the Phase 1 baseline login/registration/posting limits (PHASE_1_PLAN §3) keep their counters in a **fast process or shared store** (APCu, or a file/cache backend) behind a small limiter interface — **no Phase 1 DB table**. The configurable, MySQL‑backed limiter is Phase 3 (P3‑05; rate‑limit storage specced in PHASE_3_PLAN §8.2 #10).

---

## 8. Acceptance — the migration layer is "done" when

- All ten migrations apply cleanly on an **empty** MySQL 8 / MariaDB database, in order, and each has a working `down`.
- Re‑running is a no‑op (tracked by `schema_migrations`); a clean install + full migrate is exercised in CI.
- Every table is `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`; all FKs in §3 resolve; no migration depends on a table created after it.
- The deployed schema matches this manifest: the diff against SCHEMA.md is the §4 deferred list, **plus** — at the team's option — the five cheap, no‑op flags named in §1 (`boards.require_approval`/`edit_window_seconds`/`is_archived`, `threads.is_pending`, `posts.is_pending`) if pre‑shipped. No *subsystem/PII* later‑phase column (avatars, presence, digests, onboarding, privacy, IP, FULLTEXT) leaks in early.
- The first‑run wizard populates the first admin + starter categories/boards on the migrated schema.

## 9. Changelog

| Version | Date | Notes |
|---|---|---|
| v1.2 | 2026-06-26 | Cross‑doc review fixes: §3 corrected the stale `sessions.ip` note (SCHEMA now annotates it → Phase 2, reconciliation #11 — it no longer "shows it in the P1 table"); §7 added the **rate‑limit / throttle store** prerequisite (non‑DB in Phase 1, behind a limiter interface; the MySQL limiter is P3‑05); §8 acceptance reconciled with the §1 cheap‑flag option (pre‑shipping the five no‑op flags is permitted — only subsystem/PII columns must not leak in early). |
| v1.1 | 2026-06-26 | Resolved §6 conflicts #1 (avatar/Gravatar — DECISIONS §5 #4 aligned to "Gravatar P3"; `avatar_source` → P2, `avatar_path` → P3) and #2 (`sessions.ip` → Phase 2, SCHEMA reconciliation #11); fixed internal cross‑references (§4/§6/§7). |
| v1.0 | 2026-06-26 | Initial Phase 1 migration manifest: 10 migrations (`0001`–`0010`, `sessions=0005`), per‑table lean DDL, the §4 deferred‑column master list, and the §6 conflicts (avatar/Gravatar phase, `sessions.ip` consistency, dormant `verifications`). Derived from SCHEMA.md §1–3/§6 and the Phase 1–3 plans. |
