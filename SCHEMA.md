# RetroBoards — Consolidated Database Schema

**Status:** v1.11 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-06-26
**This file is the single authoritative reference for the full database schema.** It consolidates the DDL that is otherwise scattered across [DESIGN.md](DESIGN.md) §8, [USER.md](USER.md) §7, [ADMIN.md](ADMIN.md) §10, [COMPOSER.md](COMPOSER.md) §16, and [COMMUNITY.md](COMMUNITY.md) §11 into one place, with each doc's *"additions to existing tables"* folded directly into the table definition.

Those source docs remain the narrative source of truth for *why* each field exists; this file is the source of truth for the *final shape* of each table. When the two disagree, the reconciliations in §7 below are authoritative (they were applied to fix genuine drift between the docs).

## Conventions

- **Engine / charset:** InnoDB, `utf8mb4` everywhere.
- **Keys:** `BIGINT UNSIGNED` surrogate PKs; `DATETIME` stored UTC.
- **Soft deletes** where history matters (`is_deleted`, `lifted_at`, etc.).
- **Denormalised counters** (`post_count`, `reply_count`, `thread_count`, `last_post_*`, `reputation`) are maintained transactionally on write (DESIGN.md §8.1).
- Inline `-- comments` mark **provenance** (which doc contributed a column) and **phase**.
- Lengths/types are sensible starting points, not immutable (DESIGN.md §8.1).

## Table index

| # | Table | Domain | Phase | Source doc |
|---|---|---|---|---|
| 1 | `users` | Core | 1 | DESIGN §8.2 (+USER §7.2, ADMIN §10.2, DESIGN §8.3) |
| 2 | `sessions` | Core / auth | 1 | Canonical DDL consolidated in §1 (origin: the auth-design slice `2026-06-20-auth-design.md` §7.1, a planned/historical artifact — the DDL now lives here) |
| 3 | `categories` | Core | 1 | DESIGN §8.2 |
| 4 | `boards` | Core | 1 | DESIGN §8.2 (+ADMIN §10.2) |
| 5 | `board_slug_history` | Core | 1 | Admin Console slug redirects |
| 6 | `board_moderators` | Core / mod | 2 | DESIGN §8.2 |
| 7 | `threads` | Core | 1 | DESIGN §8.2 (+DESIGN §8.3, ADMIN §10.2, COMMUNITY §11) |
| 8 | `posts` | Core | 1 | DESIGN §8.2 (+ADMIN §10.2) |
| 9 | `reactions` | Engagement | 2 | DESIGN §8.2 |
| 10 | `thread_user` | Engagement | 1–2 | DESIGN §8.2 (is_subscribed removed — §7.4) |
| 11 | `subscriptions` | Notifications | 2 | DESIGN §8.3 |
| 12 | `notifications` | Notifications | 2 | DESIGN §8.2 (enum reconciled — §7.3) |
| 13 | `conversations` | DMs | 2 | DESIGN §8.2 |
| 14 | `conversation_participants` | DMs | 2 | DESIGN §8.2 |
| 15 | `dm_messages` | DMs | 2 | DESIGN §8.2 |
| 16 | `reports` | Moderation | 2 | DESIGN §8.2 (+ADMIN §10.2; status/reason — §7.5) |
| 17 | `moderation_log` | Moderation | 1 | DESIGN §8.2 (+ADMIN §10.2; actor — §7.6) |
| 18 | `oauth_identities` | Accounts | 2 | USER §7.1 |
| 19 | `user_preferences` | Accounts | 2 | USER §7.1 |
| 20 | `user_board_prefs` | Accounts | 2 | USER §7.1 |
| 21 | `blocks` | Accounts | 2 | USER §7.1 |
| 22 | `verifications` | Accounts / auth | 1 | USER §7.1 |
| 23 | `username_history` | Accounts | 1–2 | USER §7.1 |
| 24 | `settings` | Admin | 1 | ADMIN §10.1 |
| 25 | `bans` | Admin / mod | 2 | ADMIN §10.1 |
| 26 | `warnings` | Admin / mod | 2 | ADMIN §10.1 |
| 27 | `user_notes` | Admin / mod | 2 | ADMIN §10.1 |
| 28 | `board_members` | Admin | 2 | ADMIN §10.1 |
| 29 | `plugins` | Admin / integrations | 3 | ADMIN §10.1 |
| 30 | `webhooks` | Admin / integrations | 3 | ADMIN §10.1 |
| 31 | `api_tokens` | Admin / integrations | 3 | ADMIN §10.1 |
| 32 | `email_suppressions` | Email | 2 | ADMIN §10.1 |
| 33 | `email_deliveries` | Email | 2–3 | ADMIN §10.1 |
| 34 | `attachments` | Composer | 3 | COMPOSER §16.2 |
| 35 | `follows` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 36 | `badges` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 37 | `user_badges` | Community | 2 (P1 of community) | COMMUNITY §11 |
| 38 | `reputation_events` | Community | 4 | COMMUNITY §11 |

> "Phase" reflects the seven-phase delivery plan (PHASE_1 through PHASE_7), which subdivides the DESIGN.md §13 roadmap. See §6 for the full per-phase build cut and the crosswalk to DESIGN §13.

---

## 1. Core forum (DESIGN.md §8)

```sql
-- USERS ---------------------------------------------------------------
-- Consolidated: DESIGN §8.2 base + DESIGN §8.3 (onboarding/digest) + USER §7.2 (profile/privacy/avatar)
-- + ADMIN §10.2 (suspended_until). Role value 'user' and NULLable password_hash per §7.1/§7.2 reconciliations.
CREATE TABLE users (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username             VARCHAR(32)     NOT NULL,
  email                VARCHAR(255)    NOT NULL,
  password_hash        VARCHAR(255)    NULL,                 -- Argon2id/bcrypt. NULLable: OAuth-only until "set password" (USER §2.4)
  display_name         VARCHAR(64)     NULL,
  role                 ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- 'user' (was 'member') per DECISIONS §4
  title                VARCHAR(64)     NULL,                 -- cosmetic rank (COMMUNITY §8); never gates anything
  signature            TEXT            NULL,
  location             VARCHAR(64)     NULL,
  bio                  TEXT            NULL,                 -- USER §7.2
  website              VARCHAR(255)    NULL,                 -- USER §7.2 (rendered nofollow)
  pronouns             VARCHAR(32)     NULL,                 -- USER §7.2
  avatar_path          VARCHAR(255)    NULL,                 -- USER §7.2; ADDED in Phase 3 (uploads/Gravatar pipeline — PHASE_3_PLAN P3-12), not Phase 1
  avatar_source        ENUM('monogram','upload','gravatar','oauth') NOT NULL DEFAULT 'monogram', -- USER §7.2 (fallback chain); ADDED in Phase 2 (first set by OAuth avatar-import — PHASE_2_PLAN P2-10), not Phase 1
  post_count           INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  reputation           INT             NOT NULL DEFAULT 0,   -- Σ reactions received (+ solved bonus) — COMMUNITY §2
  profile_visibility   ENUM('public','members') NOT NULL DEFAULT 'public',          -- USER §7.2 (server-enforced)
  allow_dms            ENUM('everyone','members','none') NOT NULL DEFAULT 'members', -- USER §7.2; default 'members' per DECISIONS §5 #8
  show_presence        TINYINT(1)      NOT NULL DEFAULT 1,   -- USER §7.2
  status               ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  suspended_until      DATETIME        NULL,                 -- ADMIN §10.2 (auto-restore on expiry)
  email_verified_at    DATETIME        NULL,
  onboarded_at         DATETIME        NULL,                 -- DESIGN §8.3 (product tour, cross-device); ADDED in Phase 3 (PHASE_3_PLAN §8.1 / P3-11), not Phase 1
  timezone             VARCHAR(64)     NULL,                 -- DESIGN §8.3 (daily digests); added in Phase 2 (digest worker)
  digest_hour          TINYINT         NULL,                 -- DESIGN §8.3 (0–23 local); added in Phase 2 (digests)
  last_daily_digest_at DATETIME        NULL,                 -- DESIGN §8.3 (digest watermark: never duplicate/empty); added in Phase 2
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at         DATETIME        NULL,                 -- presence heartbeat (DESIGN §6.15)
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SESSIONS ------------------------------------------------------------
-- Canonical per the auth design (2026-06-20-auth-design.md §7.1). Opaque-token sessions:
-- id = SHA-256 of the raw cookie token; csrf_secret backs per-session CSRF; revoked_at powers logout/revocation.
CREATE TABLE sessions (
  id           CHAR(64)        NOT NULL,                     -- SHA-256 hash of the raw cookie token
  user_id      BIGINT UNSIGNED NOT NULL,
  csrf_secret  CHAR(64)        NOT NULL,                     -- per-session CSRF secret
  ip           VARBINARY(16)   NULL,                         -- login IP (ADMIN §5.4 ban-evasion); ADDED in the Phase 2 migration (first used by P2-08), not Phase 1; 90-day retention, Admin-only audited (purge job P3)
  user_agent   VARCHAR(255)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME        NULL,
  expires_at   DATETIME        NOT NULL,
  revoked_at   DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_active (expires_at, revoked_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CATEGORIES & BOARDS -------------------------------------------------
CREATE TABLE categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(64)     NOT NULL,
  position    INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: ADMIN §4.1 mentions a "default-collapsed" flag for categories; no column is specced. See §8.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (visibility/anon/approval/edit_window). post_min_role value 'user'.
CREATE TABLE boards (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id         BIGINT UNSIGNED NOT NULL,
  slug                VARCHAR(64)     NOT NULL,              -- the #channel handle
  name                VARCHAR(80)     NOT NULL,
  description         VARCHAR(255)    NULL,
  position            INT             NOT NULL DEFAULT 0,
  post_min_role       ENUM('user','moderator','admin') NOT NULL DEFAULT 'user', -- min role to post (DECISIONS §4)
  visibility          ENUM('public','hidden','private') NOT NULL DEFAULT 'public', -- ADMIN §10.2 / §4.3
  allow_anonymous     TINYINT(1)      NOT NULL DEFAULT 0,   -- ADMIN §10.2 (masked-identity posting)
  require_approval    TINYINT(1)      NOT NULL DEFAULT 0,   -- ADMIN §10.2 (hold queue)
  edit_window_seconds INT             NOT NULL DEFAULT 0,   -- ADMIN §10.2 (0 = unlimited)
  is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
  thread_count        INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  post_count          INT UNSIGNED    NOT NULL DEFAULT 0,   -- denormalised
  last_thread_id      BIGINT UNSIGNED NULL,
  last_post_at        DATETIME        NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_boards_slug (slug),
  KEY idx_boards_cat_pos (category_id, position),
  CONSTRAINT fk_boards_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE board_moderators (
  board_id  BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (board_id, user_id),
  CONSTRAINT fk_bmod_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_bmod_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- THREADS & POSTS -----------------------------------------------------
-- Consolidated: DESIGN §8.2 base + DESIGN §8.3 (ft_threads_title) + ADMIN §10.2 (is_pending) + COMMUNITY §11 (accepted_answer_post_id).
CREATE TABLE threads (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id                BIGINT UNSIGNED NOT NULL,
  user_id                 BIGINT UNSIGNED NOT NULL,          -- author of the OP
  title                   VARCHAR(160)    NOT NULL,
  slug                    VARCHAR(180)    NOT NULL,
  is_pinned               TINYINT(1)      NOT NULL DEFAULT 0,
  is_locked               TINYINT(1)      NOT NULL DEFAULT 0,
  is_deleted              TINYINT(1)      NOT NULL DEFAULT 0,
  is_pending              TINYINT(1)      NOT NULL DEFAULT 0,-- ADMIN §10.2 (new-thread approval hold)
  accepted_answer_post_id BIGINT UNSIGNED NULL,             -- COMMUNITY §11 ("Solved" / DESIGN §6.18)
  reply_count             INT UNSIGNED    NOT NULL DEFAULT 0,-- denormalised (excludes OP)
  view_count              INT UNSIGNED    NOT NULL DEFAULT 0,
  last_post_id            BIGINT UNSIGNED NULL,
  last_post_user_id       BIGINT UNSIGNED NULL,
  last_post_at            DATETIME        NULL,
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_threads_inbox (board_id, is_pinned DESC, last_post_at DESC),
  KEY idx_threads_author (user_id),
  FULLTEXT KEY ft_threads_title (title),                    -- DESIGN §8.3 (global search §6.9); index BUILT in Phase 2 (search, P2-06), not Phase 1
  CONSTRAINT fk_threads_board FOREIGN KEY (board_id) REFERENCES boards(id),
  CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- FORESHADOWED (DESIGN §6.18, not yet committed): topic_status ENUM (Solved/Needs Answer/Decision/…). See §8.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (is_anonymous, is_pending).
CREATE TABLE posts (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id      BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,                  -- always the REAL author (even when is_anonymous)
  parent_post_id BIGINT UNSIGNED NULL,                      -- quote/reply target (DESIGN §6.4)
  body           MEDIUMTEXT      NOT NULL,                  -- canonical Markdown (COMPOSER.md)
  body_html      MEDIUMTEXT      NULL,                      -- cached sanitised render (DESIGN §9.5)
  is_op          TINYINT(1)      NOT NULL DEFAULT 0,
  is_anonymous   TINYINT(1)      NOT NULL DEFAULT 0,        -- ADMIN §10.2 (masked PUBLIC render only; mods reveal — audited)
  is_pending     TINYINT(1)      NOT NULL DEFAULT 0,        -- ADMIN §10.2 (approval hold)
  is_deleted     TINYINT(1)      NOT NULL DEFAULT 0,        -- soft delete
  ip             VARBINARY(16)   NULL,                      -- author IP at post time (DECISIONS §4 #5; ban-evasion ADMIN §5.4); ADDED in the Phase 2 migration (first used by P2-08), not Phase 1; 90-day retention, Admin-only audited
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at      DATETIME        NULL,
  edited_by      BIGINT UNSIGNED NULL,
  deleted_by     BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_posts_thread (thread_id, created_at),
  KEY idx_posts_author (user_id),
  FULLTEXT KEY ft_posts_body (body),                        -- search (DESIGN §6.9); index BUILT in Phase 2 (P2-06), not Phase 1
  CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id),
  CONSTRAINT fk_posts_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ENGAGEMENT ----------------------------------------------------------
CREATE TABLE reactions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  emoji      VARCHAR(16)     NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reaction (post_id, user_id, emoji),         -- one per (user, post, emoji)
  CONSTRAINT fk_react_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: self-reactions are excluded from reputation in app logic, not by a DB constraint (COMMUNITY §2.1/§10).

-- Per-user thread state: read position + star. (is_subscribed REMOVED — superseded by `subscriptions`; see §7.4.)
CREATE TABLE thread_user (
  user_id           BIGINT UNSIGNED NOT NULL,
  thread_id         BIGINT UNSIGNED NOT NULL,
  last_read_post_id BIGINT UNSIGNED NULL,                   -- unread = thread.last_post_id > this
  is_starred        TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, thread_id),
  KEY idx_tu_starred (user_id, is_starred),
  CONSTRAINT fk_tu_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_tu_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- FORESHADOWED (DESIGN §6.18, not yet committed): snoozed_until DATETIME, assigned_to BIGINT UNSIGNED. See §8.

-- SUBSCRIPTIONS (DESIGN §8.3) — supersedes thread_user.is_subscribed; per-channel + frequency.
CREATE TABLE subscriptions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('board','thread') NOT NULL,
  target_id      BIGINT UNSIGNED NOT NULL,
  email_enabled  TINYINT(1) NOT NULL DEFAULT 1,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  frequency      ENUM('instant','daily','off') NOT NULL DEFAULT 'instant', -- a thread setting overrides its board
  created_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub (user_id, target_type, target_id),
  KEY idx_sub_target (target_type, target_id),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTIFICATIONS (DESIGN §8.2) — enum reconciled to the full union; column 'type'; read flag 'is_read' (see §7.3).
CREATE TABLE notifications (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,                 -- recipient
  type            ENUM('reply','mention','reaction','dm','mod',
                       'new_post','new_thread','follow','badge','solved',
                       'announcement') NOT NULL,                 -- 'announcement' = admin broadcast / "System / announcement" (ADMIN §7.4 Phase 2; USER §4.6); see §7 #13
  actor_id        BIGINT UNSIGNED NULL,
  thread_id       BIGINT UNSIGNED NULL,
  post_id         BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  is_read         TINYINT(1)      NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DIRECT MESSAGES -----------------------------------------------------
CREATE TABLE conversations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_message_at DATETIME        NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE conversation_participants (
  conversation_id      BIGINT UNSIGNED NOT NULL,
  user_id              BIGINT UNSIGNED NOT NULL,
  last_read_message_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (conversation_id, user_id),
  CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dm_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  body            TEXT            NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dm_conv (conversation_id, created_at),
  CONSTRAINT fk_dm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_dm_user FOREIGN KEY (user_id)         REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- REPORTS & MODERATION LOG --------------------------------------------
-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (assigned_to, reason_code) + 'triaged' state (ADMIN §3.2). See §7.5.
CREATE TABLE reports (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NOT NULL,
  post_id     BIGINT UNSIGNED NOT NULL,
  reason_code ENUM('spam','harassment','off_topic','nsfw','illegal','other') NULL, -- ADMIN §3.1 reasons (derived enum)
  reason      VARCHAR(255)    NULL,                         -- free-text (required for 'other')
  status      ENUM('open','triaged','resolved','dismissed') NOT NULL DEFAULT 'open', -- 'triaged' = claimed (ADMIN §3.2)
  assigned_to BIGINT UNSIGNED NULL,                         -- ADMIN §10.2 (queue claim)
  handled_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME        NULL,
  PRIMARY KEY (id),
  KEY idx_reports_status (status, created_at),
  CONSTRAINT fk_report_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: "one open report per (user, post)" dedup (ADMIN §3.1) is enforced in app logic.

-- Consolidated: DESIGN §8.2 base + ADMIN §10.2 (before/after JSON, system actor). actor_id NULLable = system (see §7.6).
CREATE TABLE moderation_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    BIGINT UNSIGNED NULL,                         -- NULL = system/automated action (ADMIN §3.8/§10.2)
  action      VARCHAR(40)     NOT NULL,                     -- pin, lock, delete_post, ban, anon_reveal, ...
  target_type ENUM('thread','post','user','board','category','setting') NOT NULL,
  target_id   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(255)    NULL,
  before_json JSON            NULL,                         -- ADMIN §10.2 (edit snapshot)
  after_json  JSON            NULL,                         -- ADMIN §10.2
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modlog_target (target_type, target_id),
  KEY idx_modlog_actor (actor_id, created_at),
  CONSTRAINT fk_modlog_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 2. Accounts & auth (USER.md §7.1)

```sql
-- Linked third-party logins (a user may have several)
CREATE TABLE oauth_identities (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  provider         ENUM('google','apple','github') NOT NULL,
  provider_user_id VARCHAR(191)    NOT NULL,                -- stable id (sub / numeric id)
  email            VARCHAR(255)    NULL,                    -- may be a relay/private address
  email_verified   TINYINT(1)      NOT NULL DEFAULT 0,
  avatar_url       VARCHAR(512)    NULL,                    -- cached provider avatar (USER §8); OAuth avatar-import sets users.avatar_source='oauth'. Build: Phase 2 (P2-10). Local copy via users.avatar_path arrives with the Phase 3 uploads pipeline. See §7 #12.
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_identity (provider, provider_user_id),
  KEY idx_oauth_user (user_id),
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user preference blob (theme, reading, notifications, composing, privacy, leaderboard opt-out, ...)
CREATE TABLE user_preferences (
  user_id    BIGINT UNSIGNED NOT NULL,
  prefs      JSON            NOT NULL,                      -- defaults inherited from site settings
  updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user board organization (favorite / mute / order) — powers the custom sidebar
CREATE TABLE user_board_prefs (
  user_id     BIGINT UNSIGNED NOT NULL,
  board_id    BIGINT UNSIGNED NOT NULL,
  is_favorite TINYINT(1)      NOT NULL DEFAULT 0,
  is_muted    TINYINT(1)      NOT NULL DEFAULT 0,
  position    INT             NULL,
  PRIMARY KEY (user_id, board_id),
  CONSTRAINT fk_ubp_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ubp_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Block list (blocked users can't DM/@mention; their notifications to you are suppressed)
CREATE TABLE blocks (
  user_id         BIGINT UNSIGNED NOT NULL,                 -- the blocker
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, blocked_user_id),
  CONSTRAINT fk_block_user    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_block_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Short-lived tokens: email verify, email change, password reset (store only the hash)
CREATE TABLE verifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       ENUM('email_verify','email_change','password_reset') NOT NULL,
  token_hash CHAR(64)        NOT NULL,
  new_email  VARCHAR(255)    NULL,                          -- for email_change
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NOT NULL,
  used_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verif_token (token_hash),
  KEY idx_verif_user (user_id, type),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: the /unsubscribe one-click token (ADMIN §7.6) reuses this verifications-style signed-token pattern.

-- Username change history (redirects + moderation)
CREATE TABLE username_history (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  old_username VARCHAR(32)     NOT NULL,
  changed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uh_user (user_id),
  KEY idx_uh_old (old_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. Admin, moderation & email (ADMIN.md §10.1)

```sql
-- Typed key/value site configuration (theming tokens, registration mode, site_announcement banner, toggles, ...)
CREATE TABLE settings (
  `key`      VARCHAR(64) NOT NULL,
  `value`    JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bans & board-bans (source of truth + history; users.status is a denormalised cache)
CREATE TABLE bans (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  scope      ENUM('site','board') NOT NULL DEFAULT 'site',
  board_id   BIGINT UNSIGNED NULL,                          -- required when scope='board'
  type       ENUM('post','full') NOT NULL DEFAULT 'post',
  reason     VARCHAR(255)    NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME        NULL,                          -- NULL = permanent
  lifted_at  DATETIME        NULL,
  lifted_by  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_bans_active (user_id, expires_at, lifted_at),
  CONSTRAINT fk_bans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Formal warnings (user-visible; optional points toward auto-escalation)
CREATE TABLE warnings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  issued_by  BIGINT UNSIGNED NOT NULL,
  board_id   BIGINT UNSIGNED NULL,
  reason     VARCHAR(255)    NOT NULL,
  points     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_warn_user (user_id, created_at),
  CONSTRAINT fk_warn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Private staff notes on an account (never user-visible)
CREATE TABLE user_notes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_user_id BIGINT UNSIGNED NOT NULL,
  author_id       BIGINT UNSIGNED NOT NULL,                 -- staff member
  body            TEXT            NOT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_subject (subject_user_id, created_at),
  CONSTRAINT fk_notes_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Membership for private/hidden boards (read-gate)
CREATE TABLE board_members (
  board_id   BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  added_by   BIGINT UNSIGNED NULL,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (board_id, user_id),
  CONSTRAINT fk_bm_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_bm_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Installed plugins / integrations
CREATE TABLE plugins (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(64)     NOT NULL,
  name         VARCHAR(120)    NOT NULL,
  version      VARCHAR(20)     NOT NULL,
  is_enabled   TINYINT(1)      NOT NULL DEFAULT 0,
  config       JSON            NULL,
  installed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plugins_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Outbound webhooks (HMAC-signed; Slack/Discord/Zapier)
CREATE TABLE webhooks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  url         VARCHAR(512)    NOT NULL,
  events      JSON            NOT NULL,                     -- list of event names to deliver
  secret      VARCHAR(128)    NULL,                         -- HMAC signing key
  is_active   TINYINT(1)      NOT NULL DEFAULT 1,
  created_by  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_status INT             NULL,                         -- last delivery HTTP status
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scoped admin API tokens
CREATE TABLE api_tokens (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)     NOT NULL,
  token_hash   CHAR(64)        NOT NULL,                    -- store only the hash
  scopes       JSON            NOT NULL,
  created_by   BIGINT UNSIGNED NOT NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME        NULL,
  expires_at   DATETIME        NULL,
  revoked_at   DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email suppression list (bounces, complaints, unsubscribes) — fan-out skips these
CREATE TABLE email_suppressions (
  email      VARCHAR(255) NOT NULL,
  reason     ENUM('bounce','complaint','unsubscribe','manual') NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-send delivery log (activity view, statuses, CSV export, troubleshooting)
CREATE TABLE email_deliveries (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NULL,
  email      VARCHAR(255) NOT NULL,
  kind       ENUM('instant','digest','test','system') NOT NULL,
  subject    VARCHAR(255) NULL,
  status     ENUM('queued','sent','bounced','complained','suppressed','failed') NOT NULL DEFAULT 'queued',
  error      VARCHAR(255) NULL,
  message_id VARCHAR(191) NULL,
  idempotency_key VARCHAR(191) NULL,                          -- DESIGN §9.6: post_id+':'+user_id for transactional 'instant' fan-out; NULL for digest/test/system
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deliv_idem (idempotency_key),                 -- dedupes one send per (post,recipient); InnoDB allows multiple NULLs
  KEY idx_deliv_user (user_id, created_at),
  KEY idx_deliv_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Composer (COMPOSER.md §16.2)

```sql
-- Uploaded files referenced from Markdown; tracks ownership, limits, moderation.
CREATE TABLE attachments (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  post_id        BIGINT UNSIGNED NULL,                      -- set when attached to a post
  dm_message_id  BIGINT UNSIGNED NULL,                      -- set when attached to a DM
  kind           ENUM('image','file') NOT NULL DEFAULT 'image',
  path           VARCHAR(512)    NOT NULL,                  -- served from a non-exec path (S3/CDN later)
  mime           VARCHAR(100)    NOT NULL,
  size_bytes     INT UNSIGNED    NOT NULL,
  width          INT UNSIGNED    NULL,
  height         INT UNSIGNED    NULL,
  alt            VARCHAR(255)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attach_post (post_id),
  KEY idx_attach_dm (dm_message_id),
  CONSTRAINT fk_attach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Community layer (COMMUNITY.md §11)

```sql
-- Asymmetric follow graph (user→user in v1; tag/board P2)
CREATE TABLE follows (
  user_id     BIGINT UNSIGNED NOT NULL,                     -- the follower
  target_type ENUM('user','tag','board') NOT NULL DEFAULT 'user',
  target_id   BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, target_type, target_id),
  KEY idx_follow_target (target_type, target_id),
  CONSTRAINT fk_follow_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Badge catalogue (fixed set in v1; admin-defined P2)
CREATE TABLE badges (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(48)  NOT NULL,
  name        VARCHAR(64)  NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon        VARCHAR(64)  NULL,
  kind        ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  PRIMARY KEY (id),
  UNIQUE KEY uq_badge_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Awarded badges
CREATE TABLE user_badges (
  user_id    BIGINT UNSIGNED NOT NULL,
  badge_id   BIGINT UNSIGNED NOT NULL,
  awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  awarded_by BIGINT UNSIGNED NULL,                          -- set for manual grants
  PRIMARY KEY (user_id, badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional reputation ledger (audit + time-windowed leaderboards). users.reputation is the canonical counter.
-- NOTE: Phase 4 PREVIEW ONLY — not canonical. The committed DDL is owned by PHASE_4_PLAN §8.2 (#12), which adds a
-- logical-event/idempotency key, event time, and board attribution this sketch lacks. Do not build from this block;
-- it is kept here for context only, consistent with §6/§7 (Phases 4–7 DDL lives in each phase plan until accepted).
CREATE TABLE reputation_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  delta       INT NOT NULL,
  reason      ENUM('reaction','solved','adjust') NOT NULL,
  source_type ENUM('post','thread') NULL,
  source_id   BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_repev_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. Phase map (suggested build cut)

This maps the consolidated tables onto the **seven-phase delivery plan** (PHASE_1 through PHASE_7), which subdivides the DESIGN.md §13 three-phase roadmap (DoD: *register → log in → read → start a thread → reply, server-rendered*) and the USER §8 / ADMIN §11 deltas. Phases 1–2 are fully consolidated below. **Phase 3 is partially consolidated:** `attachments`, `plugins`, `webhooks`, `api_tokens`, and `email_deliveries` have DDL above, but its remaining tables (TOTP/recovery, appeals, automation-rules, `drafts`, bookmark-folders, custom-profile-fields, and a durable webhook-delivery ledger) are **identified as schema gaps in PHASE_3_PLAN §8.2 and are not yet specced as DDL**. Phases 4–7 list their domains here as **schema requirements**, with DDL defined in each phase plan and folded back here on acceptance.

- **Phase 1 (MVP backend):** `users`, `sessions`, `verifications`, `categories`, `boards`, `board_slug_history`, `threads`, `posts`, `settings`, `moderation_log`. → See **[PHASE_1_MIGRATIONS.md](PHASE_1_MIGRATIONS.md)** for the exact Phase‑1 column cut, migration order (`0001`–`0010`), and which columns are held back to Phases 2–3.
- **Phase 2 (community essentials):** `reactions`, `thread_user` (star), `subscriptions`, `notifications`, `conversations`/`conversation_participants`/`dm_messages`, `reports`, `board_moderators`, `bans`, `warnings`, `user_notes`, `board_members`, search FULLTEXT indexes, `oauth_identities`, `user_preferences`, `user_board_prefs`, `blocks`, `username_history`, `email_suppressions`, `email_deliveries`, `follows`, `badges`, `user_badges`.
- **Phase 3 (polish, trust & scale):** `attachments` (image uploads + lifecycle), `plugins` (first-party/vetted), `webhooks` (durable delivery), `api_tokens`, plus the TOTP/recovery, appeals, automation-rule, draft-sync, bookmark-folder, and custom-profile-field tables **identified as schema gaps in PHASE_3_PLAN §8.2** (to be specced as DDL at its Milestone 1, then folded back here).
- **Phase 4 (advanced community & content):** `reputation_events` + time-windowed-leaderboard support, custom-badge rule tables, group-conversation extensions, a tag catalogue, and the foreshadowed `threads.topic_status` / `thread_user.snoozed_until` / `thread_user.assigned_to` columns (§8). DDL in PHASE_4_PLAN.
- **Phase 5 (ecosystem, identity & governance):** signed-package/manifest, custom roles/capabilities, passkey credentials, generic-OIDC provider config, invitations, service principals, and verified-link tables. DDL in PHASE_5_PLAN.
- **Phase 6 (realtime & scale):** transactional-outbox/event + job tables, external-search projection state, object-storage/media metadata, and feed-projection/checkpoint tables. DDL in PHASE_6_PLAN.
- **Phase 7 (platform expansion):** per-tenant `community_id` ownership, locale/translation packs, Web Push subscriptions, import source-ID mappings, community domains, and any federation tables. DDL in PHASE_7_PLAN.

> **Crosswalk to DESIGN §13 (three-phase strategic roadmap):** DESIGN's *Phase 3 / Later (P2)* bucket is delivered across Phases 3–7 above. Phases 4–7 schema is **defined in each phase plan's data-model section** (as requirements, not yet committed DDL) and folded back into this file on acceptance; only Phases 1–2 are fully consolidated here today, and Phase 3 only partially (see above).

> FK note: a few columns reference rows in not-yet-built tables (e.g. `notifications.conversation_id`, `posts`/`threads` self-references like `last_post_id`). Add the FK constraints in the migration that introduces the *referenced* table, or leave them as plain indexed columns until then — don't block Phase 1 on Phase 2 tables.

---

## 7. Reconciliation decisions (applied here; please review)

Where the docs genuinely disagreed, this file picks one answer. Each is reversible — flag any you'd rather flip.

1. **Role enum value `'member'` → `'user'`** on `users.role` and `boards.post_min_role` (+ defaults). DECISIONS §4 standardised the role on **"User"** and DESIGN claimed it was "updated to match," but the §8.2 DDL still read `'member'`. Source-doc DDL in DESIGN.md was also corrected.
2. **`users.password_hash` is NULLable.** USER §2.4/§7.2 require OAuth-only accounts to exist before a password is set; the DESIGN §8.2 base had it `NOT NULL`.
3. **`notifications` enum unified; column is `type`; read flag is `is_read`.** The base DDL (`type`, `is_read`, 5 values), the §8.3 prose (`kind`/`read_at`, +`new_post`/`new_thread`), and COMMUNITY §9 (`kind`, +`follow`/`badge`/`solved`) disagreed on both column names and contents. Canonical: column **`type`**, read flag **`is_read`** (keeps the existing `idx_notif_user` index coherent), enum = the **full union** (now 11 values, incl. `announcement` — see #13).
4. **`thread_user.is_subscribed` dropped.** DESIGN §8.3 + USER §7.3 mark it *superseded by `subscriptions`*. On a greenfield build there's nothing to migrate, so it's omitted rather than left as dead weight. (`is_starred` + `last_read_post_id` stay.)
5. **`reports`: added `'triaged'` status + a derived `reason_code` enum.** ADMIN §3.2's lifecycle is open→**triaged**→resolved/dismissed (the base enum lacked `triaged`); `reason_code` values are derived from ADMIN §3.1's fixed reason list (free-text `reason` retained for "other").
6. **`moderation_log.actor_id` is NULLable, NULL = system.** ADMIN §3.8/§10.2 require accountable *automated* actions; the base had `actor_id NOT NULL`.
7. **`sessions` DDL is canonical (reconciled 2026-06-20).** DECISIONS §5 #9 scheduled a `sessions` table for Phase 1; the auth slice (`2026-06-20-auth-design.md` §7.1) set the canonical DDL — adding `csrf_secret`, `expires_at NOT NULL`, `revoked_at`, and `idx_sessions_active` — and the §1 definition above is the canonical Phase 1 shape (target migration `0005_sessions.sql`, not yet built). (IP retention per ADMIN §5.5 remains a later purge-job seam.)
8. **`moderation_log.target_type` widened to include `category` and `setting`.** The planned admin console audits board/category structure changes and site-name changes, so the original four target types were too narrow for real operator actions.
9. **`email_deliveries.idempotency_key` added (`UNIQUE`).** DESIGN §9.6 mandates `idempotency_key = post_id + ':' + user_id` for transactional fan-out, and PHASE_2 (P2-00 / Milestone 0) treats the missing column as a blocker for email work — but the consolidated DDL had no such column. Added as `VARCHAR(191) NULL` with `UNIQUE KEY uq_deliv_idem` (NULL for digest/test/system sends; InnoDB permits multiple NULLs). (Reconciled 2026-06-26.)
10. **`posts.ip` added (`VARBINARY(16) NULL`).** DECISIONS §4 #5 commits to storing *post* IPs — not just the login IP in `sessions.ip` — as a ban-evasion signal (ADMIN §5.4); the consolidated `posts` table had no IP column, leaving that decision and feature with no schema home. Added with the same 90-day-retention / Admin-only-audited posture as `sessions.ip`; the purge job is a later seam (ADMIN §5.5). **Build phase: added in the Phase 2 migration** — the phase that first uses it (ban-evasion P2-08). It was previously slated to ship with `posts` in Phase 1, but no Phase 1 plan item built it; PHASE_2_PLAN §7.1 (group 4) now owns it. (Reconciled 2026-06-26; build phase clarified.)
11. **Forward-phase columns tagged with their build phase.** Several columns live in Phase 1 *table* definitions (so each table has one consolidated final shape) but are *built* later, and the phase plans say so: `users.onboarded_at` → **Phase 3** (PHASE_3_PLAN §8.1 / P3-11 creates it explicitly); `users.timezone` / `digest_hour` / `last_daily_digest_at` and the `ft_threads_title` / `ft_posts_body` FULLTEXT indexes → **Phase 2** (PHASE_1_PLAN defers FULLTEXT; PHASE_2_PLAN P2-06 builds it); `posts.ip` and `sessions.ip` → **Phase 2** (post/login IP, ban-evasion ADMIN §5.4; `posts.ip` per #10; both omitted from the Phase 1 migrations — PHASE_1_MIGRATIONS.md §3). These are now annotated inline rather than left implicitly Phase 1, honouring the Conventions note that inline comments mark each column's phase. The owning *table* still appears under its creation phase in §6. (Reconciled 2026-06-26.)
12. **`oauth_identities.avatar_url` added (`VARCHAR(512) NULL`).** DECISIONS §5 #4 schedules **OAuth avatar-import for Phase 2**, and PHASE_1_MIGRATIONS §4 puts `users.avatar_source` in Phase 2 — but the consolidated schema gave the imported provider avatar nowhere to live (`users.avatar_path` is Phase 3, for the local uploads/Gravatar pipeline). Added `oauth_identities.avatar_url` as the Phase-2 cache of the provider avatar: import sets `users.avatar_source='oauth'` and renders from it (monogram fallback). The local copy (`users.avatar_path`), user uploads, and Gravatar stay Phase 3 (PHASE_3_PLAN P3-12). PHASE_2_PLAN P2-10 / §7.1 group 5 now build `avatar_source` + `avatar_url`. (Reconciled 2026-06-26.)

13. **`notifications` enum gains `'announcement'`; admin announcements/broadcast get a schema home (no new table).** ADMIN §7.4 and PHASE_2_PLAN §3 (L92) commit Phase 2 to admin **announcements/broadcast** — a site-wide banner or pinned announcement plus an opt-in broadcast notification/email — and USER §4.6 lists a "System / announcement" notification type, but the `notifications` enum had no value for it. Added `'announcement'` to `notifications.type` for the in-app broadcast/system notice. The **banner** lives in `settings` (a `site_announcement` JSON key: active flag, message, dismissible); a **pinned announcement** is an ordinary pinned thread; the broadcast **email** reuses `email_deliveries.kind='system'`. No `announcements` table is required. Build phase: **Phase 2** (PHASE_2_PLAN §7). (Reconciled 2026-06-26.)

## 8. Foreshadowed but not yet committed

Mentioned in the docs as future schema, deliberately **not** added here until specced:

- **`threads.topic_status`** — an `ENUM` for the Community-Inbox status chips (Solved · Needs Answer · Decision Made · …), DESIGN §6.18. Values not yet enumerated.
- **`thread_user.snoozed_until` / `thread_user.assigned_to`** — inbox triage (snooze, assignment), DESIGN §6.18.
- **`categories` default-collapsed flag** — ADMIN §4.1 describes an admin-set default-collapsed flag; no column specced. If built, add it as a cheap `categories.is_collapsed_default TINYINT(1)` in the Phase 3 admin-polish pass. (Per-user collapse state is separate — it lives in `user_preferences`.)
- **`drafts`** table — server-side draft sync is P2; v1 drafts are `localStorage` only (COMPOSER §16.2).
- **`post_mentions`** lookup table — optional, to speed "who was mentioned" queries (COMPOSER §16.2).
- **post-submission idempotency** — COMPOSER §9.2/§14.3 dedupes double-submit + brief client retries via a **short-lived/transient** key; v1 commits no column. A durable `posts`/`dm_messages` idempotency column (e.g. `client_token`, `UNIQUE`) would be added here only if durable cross-retry dedupe is later required. (Distinct from `email_deliveries.idempotency_key`, which dedupes email fan-out.)

## 9. Changelog

| Version | Date | Notes |
|---|---|---|
| v1.12 | 2026-06-26 | Gave the Phase-2 **admin announcements/broadcast** feature a schema home (§7 #13): added `'announcement'` to `notifications.type` (now 11 values) for the in-app broadcast/system notice; documented the banner in `settings` (`site_announcement`), the pinned announcement as a pinned thread, and the broadcast email via `email_deliveries.kind='system'` — no new table. Noted the `categories` default-collapsed flag as a Phase-3 cheap flag (§8); de-referenced the not-yet-created `2026-06-20-auth-design.md` (sessions DDL is consolidated in §1). |
| v1.11 | 2026-06-26 | Foreshadowed a **post-submission idempotency** column in §8 (COMPOSER §9.2/§14.3 uses a short-lived/transient dedupe; no column committed in v1). Doc-only; no table shape change. |
| v1.10 | 2026-06-26 | Added `oauth_identities.avatar_url` (`VARCHAR(512) NULL`) as the **Phase-2** cache for OAuth avatar-import, giving `users.avatar_source` a writer (§7 #12); inline-tagged `users.avatar_source` → Phase 2 and `users.avatar_path` → Phase 3 build phases. Companion phase-plan edits (no SCHEMA shape change beyond `avatar_url`): `sessions.ip` added to the Phase-2 build (PHASE_2_PLAN P2-08 / §7.1 grp 4), avatar uploads + `users.avatar_path` given a Phase-3 owner (PHASE_3_PLAN P3-12), and the 90-day IP-retention purge job assigned to PHASE_3_PLAN P3-05 — resolving review findings 1/2/4/5. |
| v1.9 | 2026-06-26 | Annotated `sessions.ip` → **Phase 2** (login-IP/ban-evasion, omitted from the Phase 1 migration like `posts.ip`); recorded in reconciliation #11. No shape change to the final consolidated table. |
| v1.8 | 2026-06-26 | Added a §6 pointer to the new **PHASE_1_MIGRATIONS.md** (the authoritative Phase‑1 column cut + migration order). No schema change. |
| v1.7 | 2026-06-26 | **Status-truth pass (nothing is built yet):** removed "shipped" wording implying existing code — the `0005_sessions.sql` / `0007_moderation_log.sql` references (§7 #7, table index, v1.1 entry) are **target** migrations, and §7 #8 now says the **planned** admin console. No DDL/scope changes. |
| v1.6 | 2026-06-26 | Consistency pass vs the phase plans: corrected §6 — only Phases **1–2** are fully consolidated; **Phase 3 is partial** (its TOTP/appeals/automation/`drafts`/bookmark/custom-field/webhook-ledger tables are schema *gaps* in PHASE_3_PLAN §8.2, not specced DDL) and Phases 4–7 hold *requirements*, not DDL. Tagged forward-phase columns with their real build phase as new §7 reconciliation #11 (`onboarded_at` → Phase 3; digest columns, `ft_*` FULLTEXT indexes, and `posts.ip` → Phase 2), and moved `posts.ip`'s build from Phase 1 to the Phase 2 migration (§7 #10) so it has an owner. Header bumped (was v1.4, behind its own v1.5 row). |
| v1.5 | 2026-06-26 | Consistency pass: set `users.allow_dms` default to `'members'` (was `'everyone'`) to match DECISIONS §5 #8; added `posts.ip VARBINARY(16) NULL` to give the "store post IPs" decision (DECISIONS §4 #5) a schema home, recorded as §7 reconciliation #10; flagged the §5 `reputation_events` block as a non-canonical Phase-4 preview (committed DDL owned by PHASE_4_PLAN §8.2). |
| v1.4 | 2026-06-26 | Added `email_deliveries.idempotency_key` (`VARCHAR(191) NULL`, `UNIQUE KEY uq_deliv_idem`) to close the DESIGN §9.6 / PHASE_2 Milestone-0 idempotency gap; recorded as §7 reconciliation #9; bumped the stale header (was "v1.2 · 2026-06-21"). |
| v1.3 | 2026-06-25 | Consistency pass: extended the §6 phase map to the full seven-phase delivery plan with a DESIGN §13 crosswalk; moved `attachments` to Phase 3 and `reputation_events` to Phase 4 to match the delivery plans; clarified the "Phase" column note. |
| v1.2 | 2026-06-21 | Added `board_slug_history` as the Phase 1 alias table for Admin Console board slug changes, preserving old `/c/{slug}` links with 301 redirects. |
| v1.1 | 2026-06-21 | Added the `0007_moderation_log.sql` table to the canonical Phase 1 schema (planned); widened `moderation_log.target_type` to include `category` and `setting`; recorded the actor FK (`ON DELETE SET NULL`); moved `moderation_log` into the explicit Phase 1 build cut. |
| v1.0 | 2026-06-19 | Initial consolidation of all 37 tables from DESIGN/USER/ADMIN/COMPOSER/COMMUNITY into one reference; folded "additions to existing tables" into each definition; recorded 7 reconciliation decisions (§7) and foreshadowed-but-unspecced schema (§8); added a per-phase build cut (§6). |
